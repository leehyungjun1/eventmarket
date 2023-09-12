<?php
class Goodsmodel extends CI_Model {
	public function __construct(){
		$this->load->helper('goods');

		$cfg_order					= config_load('order');
		$this->reservation_field	= 'reservation'.$cfg_order['ableStockStep'];
		$this->dayautotype			= array("month"=>"해당 월","day"=>"해당 일","next"=>"익월");
		$this->dayautoday			= array("day"=>"동안","end"=>"이 되는 월의 말일");

		# 환율(KRW)
		$this->exchange_rate_krw	= get_exchange_rate('KRW');

		if( $this->db->es_use === true ){
			$this->load->library('elasticsearch');

			$this->platform = 'P';
			if($this->fammerceMode || $this->storefammerceMode){
				$this->platform	= 'F';
			} elseif ($this->_is_mobile_agent || $this->mobileMode || $this->storemobileMode){
				$this->platform	= 'M';
			}
		}
	}

	// 상품등록/수정 상단 tab 메뉴 지정
	public function admin_goods_regist_tab_list($goods_type='goods'){

		$list = array(
					'01' => '카테고리',
					'02' => '브랜드/지역',
					'03' => '기본정보',
					'06' => '판매정보',
					'07' => '옵션',
					'08' => '사진',
					'09' => '상세설명',
					'10' => '공통정보',
					'11' => '배송비',
					'12' => '이벤트',
					'13' => '기타정보',
					'14' => '추천상품',
					'15' => '오픈마켓',
					'16' => '입점마케팅',
					'19' => '메모',
				);
		unset($list['15']);

		if(defined('__SELLERADMIN__') === true) unset($list['16']);

		if($goods_type == 'social'){
			$list['04'] = '티켓 정보';
			$list['05'] = '환불 정보';
			$list['11'] = '티켓 발송';
			unset($list['11'],$list['15'],$list['16']);
		}else if($goods_type == 'gift') {
			$list = array(
				'01' => '기본정보',
				'02' => '판매정보',
				'03' => '옵션',
				'04' => '사진',
				'05' => '기타정보',
				'06' => '메모',
			);
		}
		ksort($list);
		return $list;
	}

	public function goods_temp_image_upload($filename,$folder){
		$tmp = getimagesize($_FILES['Filedata']['tmp_name']);
		$_FILES['Filedata']['type'] 	= $tmp['mime'];
		$config['upload_path'] 			= $folder;
		$config['allowed_types'] 		= 'jpeg|jpg|png|gif';
		$config['max_size']				= $this->config_system['uploadLimit'];
		$config['file_name'] 			= $filename;
		$this->load->library('upload', $config);
		if ( ! $this->upload->do_upload('Filedata'))
		{
			$result = array('status' => '0','error' => $this->upload->display_errors());
		}else{
			$result = array('status' => 1,'fileInfo'=>$this->upload->data());
		}
		return $result;
	}

	public function goods_temp_image_resize($source,$target,$width,$height=100){
		$this->load->library('Image_lib');
		if((int)$height == 0) {
			$height = "100"; // height 0이면 이미지 생성이 안되서 임의값 추가
		}
		$config['image_library'] 	= 'gd2';
		$config['source_image'] 	= $source;
		$config['new_image'] 		= $target;
		$config['quality'] 			= '100%';
		$config['maintain_ratio'] 	= TRUE;
		$config['width'] 			= $width;
		$config['height'] 			= $height;
		$config['master_dim'] 		= "width";		// 상품 이미지 업로드 시 추가 옵션 (리사이징 기준 : 가로)

		$this->image_lib->initialize($config);
		if ( ! $this->image_lib->resize())
		{
			$result = array('status' => '0','error' => $this->image_lib->display_errors());
		}else{
			$result = array('status' => 1, 'config'=>$config);
		}
		$this->image_lib->clear();
		return $result;
	}

	/* 상품등록 파라미터 검증*/
	public function check_param_regist(){

		$goodsParam = $this->input->post();

		$_POST['chkPrice'] = $_POST['price'][0];
		$_POST['chkStock'] = array_sum($_POST['stock']);
		if($_POST['chkStock'] < 1) unset($_POST['chkStock']);

		if($_POST['goods_type']=='gift'){
			$_POST['tax']					= 'tax';
			$_POST['tax_chk']				= 'Y';
			$_POST['minPurchaseLimit']		= 'unlimit';
			$_POST['maxPurchaseLimit']		= 'unlimit';
			$_POST['reserve_policy']		= 'shop';
			$_POST['sub_reserve_policy']	= 'shop';
			$_POST['shippingWeightPolicy']	= 'shop';
			$_POST['relation_type']			= 'AUTO';
			$_POST['relation_seller_type']	= 'AUTO';
			$_POST['info_select_seq']		= 0;
			$_POST['package_yn']			= 'n';
			$_POST['package_yn_suboption']	= 'n';
		}else{
			$_POST['goods_type'] = 'goods';
		}

		if( !isset($_POST['string_price_use']) ) $_POST['string_price_use'] = 0;
		if( !isset($_POST['member_string_price_use']) ) $_POST['member_string_price_use'] = 0;
		if( !isset($_POST['allmember_string_price_use']) ) $_POST['allmember_string_price_use'] = 0;

		if( !isset($_POST['multiDiscountUse']) ) $_POST['multiDiscountUse'] = 0;
		if( !isset($_POST['optionUse']) ) $_POST['optionUse'] = 0;
		if( !isset($_POST['subOptionUse']) ) $_POST['subOptionUse'] = 0;
		if( !isset($_POST['memberInputUse']) ) $_POST['memberInputUse'] = 0;
		if( !isset($_POST['restockNotifyUse']) ) $_POST['restockNotifyUse'] = 0;
		if( !isset($_POST['provider_status']) ) $_POST['provider_status'] = 0;

		$chkArr['string_price']						= "";
		$chkArr['string_price_link']				= "";
		$chkArr['string_price_link_url']			= "";
		$chkArr['member_string_price']				= "";
		$chkArr['member_string_price_link']			= "";
		$chkArr['member_string_price_link_url']		= "";
		$chkArr['allmember_string_price']			= "";
		$chkArr['allmember_string_price_link']		= "";
		$chkArr['allmember_string_price_link_url']	= "";

		$chkArr['multiDiscountEa']			= "";
		$chkArr['multiDiscount']			= "";
		$chkArr['multiDiscountUnit']		= "";
		$chkArr['minPurchaseEa']			= "";
		$chkArr['maxPurchaseOrderLimit']	= "";
		$chkArr['maxPurchaseEa']			= "";
		$chkArr['optionViewType']			= "";
		$chkArr['goodsShippingPolicy']		= "";
		$chkArr['unlimitShippingPrice']		= "";
		$chkArr['limitShippingEa']			= "";
		$chkArr['limitShippingPrice']		= "";
		$chkArr['limitShippingSubPrice']	= "";
		$chkArr['goodsWeight']				= "";

		//-----------------------------------------------------------------------------------------------
		// 본사/입점사 상품 체크
		if(!$_POST['goodsSeq']){
			if($_POST['goods_gubun'] == "provider" || defined('__SELLERADMIN__') == true){
				if($_POST['provider_seq'] == 1) $_POST['provider_seq'] = '';
				$this->validation->set_rules('provider_seq', '입점사','trim|required');//|xss_clean
			}else{
				if($_POST['provider_seq'] > 1) $_POST['provider_seq'] = 1;
			}
		}
		//-----------------------------------------------------------------------------------------------
		// 카테고리
			$this->validation->set_rules('firstCategory', '대표카테고리','trim|xss_clean');
			$this->validation->set_rules('connectCategory[]', '카테고리연결','trim|xss_clean');
		//-----------------------------------------------------------------------------------------------
		// 상품명
			if($_POST['goods_type']=='gift'){
				$this->validation->set_rules('goodsName', '사은품명','trim|required');//|xss_clean
			}else{
				$this->validation->set_rules('goodsName', '상품명','trim|required');//|xss_clean
			}
			if( $_POST['useMarket'] == '1' && $_POST['goods_kind'] != 'coupon'){
				$this->validation->set_rules('goodsNameLinkage', '오픈마켓 상품명','trim|required');
			}
			$this->validation->set_rules('purchaseGoodsName', '매입용 상품명','trim');//|xss_clean
			$this->validation->set_rules('summary', '간략 설명','trim');//|xss_clean
			$this->validation->set_rules('keyword', '상품 검색 태그','trim');//|xss_clean
		//-----------------------------------------------------------------------------------------------
		// 판매정보
			if($_POST['tmp_goodsView'] != "reservation"){
				$_POST['display_terms'] 		= '';
				$_POST['display_terms_type'] 	= '';
			}
			$this->validation->set_rules('viewLayout', '상품상세페이지','trim|required|xss_clean');
			$this->validation->set_rules('goodsStatus', '판매 상태','trim|required|xss_clean');
			$this->validation->set_rules('goodsView', '노출 여부','trim|required|xss_clean');
			$this->validation->set_rules('tax', '부가세','trim|required|xss_clean');
		//-----------------------------------------------------------------------------------------------
		// 필수옵션
			if( $_POST['optionUse'] == '1' ){
				$this->validation->set_rules('optionViewType', '옵션 출력 형식','trim|required|xss_clean');
				$chkArr['optionViewType']	= $_POST['optionViewType'];
			}

			$this->validation->set_rules('chkPrice', '할인가(판매가)','trim|numeric|required|xss_clean');
			$this->validation->set_rules('chkStock', '재고','trim|numeric|xss_clean');
			if( isset($_POST['opt']) ){
				$this->validation->set_rules('defaultOption', '기준할인가','trim|required|xss_clean');
			}
			$this->validation->set_rules('goodsCode', '상품 코드','trim|xss_clean');
		//-----------------------------------------------------------------------------------------------
		// 상품 설명/공통 정보
			//$this->validation->set_rules('contents', '상품 설명','trim|xss_clean');
			//$this->validation->set_rules('commonContents', '공용 정보','trim|xss_clean');
			$this->validation->set_rules('info_name', '공용 정보명','trim|xss_clean');
		//-----------------------------------------------------------------------------------------------
		// 배송비
			$this->validation->set_rules('shippingPolicy', '국내 배송','trim|required|xss_clean');
			if( $_POST['shippingPolicy'] == 'goods' ){
				$this->validation->set_rules('goodsShippingPolicy', '개별 배송비 정책','trim|required|xss_clean');
				$chkArr['goodsShippingPolicy']	= $_POST['goodsShippingPolicy'];
				if( $_POST['goodsShippingPolicy'] == 'unlimit' ){
					$this->validation->set_rules('unlimitShippingPrice', '개별 배송비 정책','trim|numeric|required|xss_clean');
					$chkArr['unlimitShippingPrice']	= $_POST['unlimitShippingPrice'];
				}else if( $_POST['goodsShippingPolicy'] == 'limit' ){
					$this->validation->set_rules('limitShippingEa', '개별 배송비 정책','trim|numeric|required|xss_clean');
					$this->validation->set_rules('limitShippingPrice', '개별 배송비 정책','trim|numeric|required|xss_clean');
					$this->validation->set_rules('limitShippingSubPrice', '개별 배송비 정책','trim|numeric|required|xss_clean');
					$chkArr['limitShippingEa']			= $_POST['limitShippingEa'];
					$chkArr['limitShippingPrice']		= $_POST['limitShippingPrice'];
					$chkArr['limitShippingSubPrice']	= $_POST['limitShippingSubPrice'];
				}
			}
			$goods['shipping_group_seq']	= $_POST['shipping_group_seq'];
			$goods['trust_shipping']		= $_POST['trust_shipping'];

			$this->validation->set_rules('shippingWeightPolicy', '해외 배송','trim|xss_clean');

			if( $_POST['shippingWeightPolicy'] == 'goods' ){
				$this->validation->set_rules('goodsWeight', '상품 중량 ','trim|numeric|required|xss_clean');
				$chkArr['goodsWeight']	= $_POST['goodsWeight'];
			}
		//-----------------------------------------------------------------------------------------------
		// 이벤트
			if( $_POST['multiDiscountUse'] ){
				if($_POST['multiDiscountEa'] < 2){
					openDialogAlert('복수구매 할인은 최소 2개 이상부터 가능합니다.',400,140,'parent','');
					exit;
				}
			}
		//-----------------------------------------------------------------------------------------------
		// 기타 정보(최소/최대/구매대상제한)
			$this->validation->set_rules('minPurchaseLimit', '최소 구매수량','trim|required|xss_clean');

			if( $_POST['minPurchaseLimit'] == 'limit' ){
				$this->validation->set_rules('minPurchaseEa', '최소 구매수량','trim|numeric|required|xss_clean');
				if($_POST['minPurchaseEa'] < 2){
					echo("<script>parent.able_save();</script>");
					openDialogAlert('최소 구매수량은 2개 이상 입력하셔야 합니다.',400,140,'parent','');
					exit;
				}
				$chkArr['minPurchaseEa']	= $_POST['minPurchaseEa'];
			}else{
				$_POST['minPurchaseEa'] = 1;
			}
			$this->validation->set_rules('maxPurchaseLimit', '최대 구매수량','trim|required|xss_clean');
			if( $_POST['maxPurchaseLimit'] == 'limit' ){
				$this->validation->set_rules('maxPurchaseEa', '최대 구매수량','trim|numeric|required|xss_clean');
				if( $_POST['minPurchaseEa'] > $_POST['maxPurchaseEa'] ){
					echo("<script>parent.able_save();</script>");
					openDialogAlert('최대 구매수량은 최소 구매수량('.$_POST['minPurchaseEa'].'개) 이상 입력하셔야 합니다.',400,140,'parent','');
					exit;
				}

				$chkArr['maxPurchaseEa']			= $_POST['maxPurchaseEa'];
			}

			// 가격 디스플레이 수정 2014-03-14 lwh
			if( $_POST['string_price_use'] == 1 ){
				$this->validation->set_rules('string_price', '가격 대체 문구','trim|required|xss_clean');
				$chkArr['string_price']							= $_POST['string_price'];
				$chkArr['string_price_color']					= $_POST['string_price_color'];
				$chkArr['string_price_link']					= $_POST['string_price_link'];
				$chkArr['string_price_link_url']				= $_POST['string_price_link_url'];
				$chkArr['string_price_link_target']				= $_POST['string_price_link_target'];
			}
			if( $_POST['member_string_price_use'] == 1 ){
				$this->validation->set_rules('member_string_price', '가격 대체 문구','trim|required|xss_clean');
				$chkArr['member_string_price']					= $_POST['member_string_price'];
				$chkArr['member_string_price_color']			= $_POST['member_string_price_color'];
				$chkArr['member_string_price_link']				= $_POST['member_string_price_link'];
				$chkArr['member_string_price_link_url']			= $_POST['member_string_price_link_url'];
				$chkArr['member_string_price_link_target']		= $_POST['member_string_price_link_target'];
			}
			if( $_POST['allmember_string_price_use'] == 1 ){
				$this->validation->set_rules('allmember_string_price', '가격 대체 문구','trim|required|xss_clean');
				$chkArr['allmember_string_price']				= $_POST['allmember_string_price'];
				$chkArr['allmember_string_price_color']			= $_POST['allmember_string_price_color'];
				$chkArr['allmember_string_price_link']			= $_POST['allmember_string_price_link'];
				$chkArr['allmember_string_price_link_url']		= $_POST['allmember_string_price_link_url'];
				$chkArr['allmember_string_price_link_target']	= $_POST['allmember_string_price_link_target'];
			}

			// 버튼 디스플레이 수정
			if( $_POST['string_button_use'] == 1 ){
				$this->validation->set_rules('string_button', '버튼 대체 문구','trim|required|xss_clean');
				$chkArr['string_button']						= $_POST['string_button'];
				$chkArr['string_button_color']					= $_POST['string_button_color'];
				$chkArr['string_button_link']					= $_POST['string_button_link'];
				$chkArr['string_button_link_url']				= $_POST['string_button_link_url'];
				$chkArr['string_button_link_target']			= $_POST['string_button_link_target'];
			}
			if( $_POST['member_string_button_use'] == 1 ){
				$this->validation->set_rules('member_string_button', '버튼 대체 문구','trim|required|xss_clean');
				$chkArr['member_string_button']					= $_POST['member_string_button'];
				$chkArr['member_string_button_color']			= $_POST['member_string_button_color'];
				$chkArr['member_string_button_link']			= $_POST['member_string_button_link'];
				$chkArr['member_string_button_link_url']		= $_POST['member_string_button_link_url'];
				$chkArr['member_string_button_link_target']		= $_POST['member_string_button_link_target'];
			}
			if( $_POST['allmember_string_button_use'] == 1 ){
				$this->validation->set_rules('allmember_string_button', '버튼 대체 문구','trim|required|xss_clean');
				$chkArr['allmember_string_button']				= $_POST['allmember_string_button'];
				$chkArr['allmember_string_button_color']		= $_POST['allmember_string_button_color'];
				$chkArr['allmember_string_button_link']			= $_POST['allmember_string_button_link'];
				$chkArr['allmember_string_button_link_url']		= $_POST['allmember_string_button_link_url'];
				$chkArr['allmember_string_button_link_target']	= $_POST['allmember_string_button_link_target'];
			}
		//-----------------------------------------------------------------------------------------------
		// 관리자 메모
			$this->validation->set_rules('adminMemo', '관리자 메모','trim|xss_clean');
		//-----------------------------------------------------------------------------------------------

		if($this->validation->exec()===false){
			$err = $this->validation->error_array;
			$callback = "if(parent.document.getElementsByName('{$err['key']}')[0]) parent.document.getElementsByName('{$err['key']}')[0].focus();";
			if( $err['key'] == 'chkPrice' )	$callback = "parent.document.getElementsByName('price[]')[0].focus();";
			if( $err['key'] == 'chkStock' ) $callback = "parent.document.getElementsByName('stock[]')[0].focus();";
			echo("<script>parent.able_save();</script>");
			openDialogAlert($err['value'],400,140,'parent',$callback);
			exit;
		}

		// 배송그룹 :: 2016-07-01 lwh - 기본값 지정
		if($_POST['goods_type'] != 'gift' && $_POST['goods_kind'] != 'coupon') {
			if(!$_POST['shipping_group_seq']) {
				openDialogAlert('배송방법을 선택해 주세요.',400,140,'parent','top.moveBookmark("11");');
				exit;
			} else {
				$this->load->model("shippingmodel");
				$provider_seq = $this->input->post('provider_seq');
				$shipping_group_seq = $this->input->post('shipping_group_seq');
				$ship_grp = false;

				if($provider_seq > 1){
					$ship_tmp = $this->shippingmodel->get_shipping_group_simple($provider_seq);

					if(!$ship_tmp) {
						openDialogAlert('해당 입점사의 배송그룹을 다시 설정해 주세요',400,140,'parent','top.moveBookmark("11");');
						exit;
					}

					foreach($ship_tmp as $val) {
						if($shipping_group_seq == $val['shipping_group_seq']) {
							$ship_grp = true;
						}
					}
				}

				$admin_ship_tmp = $this->shippingmodel->get_shipping_group_simple('1');

				foreach($admin_ship_tmp as $val) {
					if($shipping_group_seq == $val['shipping_group_seq']) {
						$ship_grp = true;
					}
				}

				if($ship_grp === false) {
					openDialogAlert('배송방법을 다시 선택해 주세요.',400,140,'parent','top.moveBookmark("11");');
					exit;
				}
			}
		}

		// 구매수량 할인
		if($_POST['multiDiscountSet'] == "n"){
			$_POST['discountUnit'] 			= '';
			$_POST['discountOverQty'] 		= '';
			$_POST['discountUnderQty'] 		= '';
			$_POST['discountAmount'] 		= '';
			$_POST['discountMaxOverQty'] 	= '';
			$_POST['discountMaxAmount'] 	= '';
		}
		$goods['multi_discount_policy']				= '';
		if ($_POST['multiDiscountSet'] == "y" && count($_POST['discountOverQty']) > 0) {

			$discountPolicy							= array();
			foreach ($_POST['discountOverQty'] as $key => $val) {
				$nowPolicy							= array();
				$nowPolicy['discountOverQty']		= $val;
				$nowPolicy['discountUnderQty']		= $_POST['discountUnderQty'][$key];
				$nowPolicy['discountAmount']		= $_POST['discountAmount'][$key];

				$discountPolicy['policyList'][]		= $nowPolicy;
			}

			$discountPolicy['discountMaxOverQty']	= $_POST['discountMaxOverQty'];
			$discountPolicy['discountMaxAmount']	= $_POST['discountMaxAmount'];
			$discountPolicy['discountUnit']			= ($_POST['discountUnit'] == 'PRI') ? 'PRI' : 'PER';

			$goods['multi_discount_policy']			= json_encode($discountPolicy);
		}
		// 에디터 이미지 경로 재정의 :: 2016-04-21 lwh -> 네임태그/정식도메인 개선 @2016-12-21
		$contents			= adjustEditorImages($_POST['contents'], "/data/editor/");
		$common_contents	= adjustEditorImages($_POST['commonContents'], "/data/editor/");
		$mobile_contents	= adjustEditorImages($_POST['mobile_contents'], "/data/editor/");

		$_POST['goodsName']	= preg_replace("!<iframe(.*?)<\/iframe>!is","",$_POST['goodsName']);
		$_POST['goodsNameLinkage']	= preg_replace("!<iframe(.*?)<\/iframe>!is","",$_POST['goodsNameLinkage']);
		$_POST['keyword']					= strip_tags($_POST['keyword']);
		$_POST['keywordLinkage']			= strip_tags($_POST['keywordLinkage']);

		$goods['view_layout'] 				= $_POST['viewLayout'];
		$goods['goods_status']				= $_POST['goodsStatus'];
		$goods['provider_status']			= ($_POST['provider_status'])?$_POST['provider_status']:'0';
		$goods['goods_view'] 				= $_POST['goodsView'];
		$goods['goods_code'] 				= $_POST['goodsCode'];
		$goods['goods_name'] 				= $_POST['goodsName'];
		$goods['goods_name_linkage']		= $_POST['goodsNameLinkage'];
		$goods['purchase_goods_name'] 		= $_POST['purchaseGoodsName'];
		$goods['summary'] 					= $_POST['summary'];
		$goods['keyword'] 					= $_POST['keyword'];
		$goods['keyword_linkage']			= $_POST['keywordLinkage'];
		$goods['package_yn'] 				= $_POST['package_yn'];
		$goods['package_yn_suboption'] 		= $_POST['package_yn_suboption'];
		//반응형 스킨 사용 시 PC/Mobile 상품 상세 내용 통일
		if( $this->config_system['operation_type'] == 'light') {
			$goods['contents'] = $mobile_contents;
		}else{
			$goods['contents'] = $contents;
		}

		// 해외배송여부
		$goods['option_international_shipping_status'] = 'n';
		if( $_POST['option_international_shipping_status'] ){
			$goods['option_international_shipping_status'] = $_POST['option_international_shipping_status'];
		}

		//이미지호스팅체크 변환대상갯수
		if ( $contents ) {
			$this->load->model("imagehosting");
			$this->imagehosting->get_contents_cnt($goods['contents'],$goods['convert_image_cnt'],$goods['noconvert_image_cnt']);
		}

		$goods['common_contents'] 			= $common_contents;
		$goods['mobile_contents'] 			= $mobile_contents;
		// 구매대상제한
		$string_price_arr = array('string_price','member_string_price','allmember_string_price','string_button','member_string_button','allmember_string_button');
		foreach($string_price_arr as $_string){
			if($_POST['stringPriceUse'] == "n"){
				$goods[$_string.'_use'] 			= '';
				$goods[$_string] 					= '';
				$goods[$_string.'_color'] 			= '';
				$goods[$_string.'_link']			= '';
				$goods[$_string.'_link_url']		= '';
				$goods[$_string.'_link_target']		= '';
			}else{
				$goods[$_string.'_use'] 			= $_POST[$_string.'_use'];
				$goods[$_string] 					= $chkArr[$_string];
				$goods[$_string.'_color'] 			= $chkArr[$_string.'_color'];
				$goods[$_string.'_link']			= $chkArr[$_string.'_link'];
				$goods[$_string.'_link_url']		= $chkArr[$_string.'_link_url'];
				$goods[$_string.'_link_target']		= ($chkArr[$_string.'_link_target'] == 'NOW') ? 'NOW' : 'NEW';
			}
		}
		// 구매대상제한 끝.
		$goods['tax'] 						= $_POST['tax'];
		$goods['multi_discount_use'] 		= $_POST['multiDiscountUse'];
		$goods['multi_discount_ea'] 		= $chkArr['multiDiscountEa'];
		$goods['multi_discount'] 			= get_cutting_price($chkArr['multiDiscount']);
		$goods['multi_discount_unit'] 		= $chkArr['multiDiscountUnit'];
		$goods['min_purchase_limit'] 		= $_POST['minPurchaseLimit'];
		$goods['min_purchase_ea'] 			= $chkArr['minPurchaseEa'];
		$goods['max_purchase_limit'] 		= $_POST['maxPurchaseLimit'];
		$goods['max_purchase_order_limit'] 	= $chkArr['maxPurchaseOrderLimit'];
		$goods['max_purchase_ea'] 			= $chkArr['maxPurchaseEa'];
		$goods['option_use'] 				= $_POST['optionUse'];
		$goods['reserve_policy'] 			= $_POST['reserve_policy'];
		$goods['sub_reserve_policy'] 		= $_POST['sub_reserve_policy'];
		$goods['option_view_type'] 			= $chkArr['optionViewType'];
		$goods['option_suboption_use']		= $_POST['subOptionUse'];
		$goods['member_input_use'] 			= $_POST['memberInputUse'];
		$goods['shipping_policy'] 			= $_POST['shippingPolicy'];
		$goods['goods_shipping_policy'] 	= $chkArr['goodsShippingPolicy'];
		$goods['unlimit_shipping_price'] 	= $chkArr['unlimitShippingPrice'];
		$goods['limit_shipping_ea'] 		= $chkArr['limitShippingEa'];
		$goods['limit_shipping_price'] 		= $chkArr['limitShippingPrice'];
		$goods['limit_shipping_subprice'] 	= $chkArr['limitShippingSubPrice'];
		$goods['shipping_weight_policy'] 	= ($_POST['shippingWeightPolicy']) ? $_POST['shippingWeightPolicy'] : 'shop';
		$goods['goods_weight'] 				= $chkArr['goodsWeight'];
		$goods['admin_memo'] 				= $_POST['adminMemo'];
		$goods['restock_notify_use'] 		= $_POST['restockNotifyUse'];
		$goods['individual_refund']			= $_POST['individual_refund'];
		$goods['individual_refund_inherit']	= $_POST['individual_refund_inherit'];
		$goods['individual_export']			= $_POST['individual_export'];
		$goods['individual_return']			= $_POST['individual_return'];
		$goods['package_yn'] 				= $_POST['package_yn'];

		/*
		* 티켓 위치서비스 설정 :: 2014-04-02 lwh
		*/
		if($this->operation_type == 'light'){
			$goods['pc_mapview'] = $goods['m_mapview'] = ($_POST['pc_mapView'])	? $_POST['pc_mapView']	: 'N';
		}else{
			$goods['pc_mapview']				= ($_POST['pc_mapView'])	? $_POST['pc_mapView']	: 'N';
			$goods['m_mapview']					= ($_POST['m_mapView'])		? $_POST['m_mapView']	: 'N';
		}

		/*
		* 성인상품 여부설정 :: 2015-03-17 lwh
		*/
		$goods['adult_goods']				= ($_POST['adult_goods'])	? $_POST['adult_goods']	: 'N';

		/*
		* 모바일용 PC 동일여부 설정 :: 2016-05-11 lwh
		*/
		$goods['mobile_contents_copy']		= ($_POST['mobile_contents_copy'])	? $_POST['mobile_contents_copy']	: 'N';

		/*
		*자주사용하는 옵션여부 저장
		*/
		$goods['frequentlyopt'] 			= ($_POST['frequentlyopt'])?'1':'0';
		$goods['frequentlysub'] 			= ($_POST['frequentlysub'])?'1':'0';
		$goods['frequentlyinp'] 			= ($_POST['frequentlyinp'])?'1':'0';

		// 입점마케팅 관련 추가
		if(defined('__SELLERADMIN__') === false){
			$goods['feed_status']				= $_POST['feed_status'];
			if	($_POST['feed_status'] != 'N')
				$goods['feed_status'] 				= 'Y';
			$goods['feed_evt_sdate'] 			= $_POST['feed_evt_sdate'];
			$goods['feed_evt_edate'] 			= $_POST['feed_evt_edate'];
			$goods['feed_evt_text'] 			= $_POST['feed_evt_text'];

			// 배송비 EP 데이터 저장 :: 2017-02-23 lwh
			$goods['feed_ship_type'] 			= $_POST['feed_ship_type'];
			if($_POST['feed_ship_type'] == 'E'){
				$goods['feed_pay_type'] 		= $_POST['feed_pay_type'];
				$goods['feed_std_fixed'] 		= $_POST['feed_std_fixed'];
				$goods['feed_add_txt'] 			= $_POST['feed_add_txt'];
			}
		}

		// EP 데이터 추가 get_goods_common_info저장 :: 2018-08-06 lwh
		$goods['feed_condition'] 			= $_POST['feed_condition'];		// 상품상태
		$goods['product_flag']				= $_POST['product_flag'];		// 판매방식 구분
		$goods['installation_costs']		= $_POST['installation_costs'];	// 별도 설치비 유무
		$goods['compound_state']			= $_POST['compound_state']; // 병행수입 및 주문제작여부 (parallel_import:병행수입/order_made:주문제작)

		$goods['info_seq'] 					= $_POST['info_select_seq'];
		$goods['provider_seq'] 				= $_POST['provider_seq'];
		$goods['goods_type'] 				= $_POST['goods_type'];
		$goods['goods_sub_info'] 			= ($_POST['goods_sub_info'])?$_POST['goods_sub_info'] : '';

		$goods['cancel_type'] 				= ($_POST['cancel_type'])?'1':'0';//청약철회상품여부
		$goods['socialcp_event'] 			= ($_POST['socialcp_event'])?$_POST['socialcp_event']:'0';

		if( $_POST['goods_kind'] == 'coupon' ){//티켓상품 상품이면
			$goods['goods_kind'] 					= 'coupon';
			$goods['socialcp_input_type'] 	= ($_POST['socialcp_input_type'])?$_POST['socialcp_input_type']:'pass';
			$goods['socialcp_cancel_type'] 	= ($_POST['socialcp_cancel_type'])?$_POST['socialcp_cancel_type']:'pay';
			$goods['socialcp_use_return'] 	= ($_POST['socialcp_use_return'])?$_POST['socialcp_use_return']:'0';

			$goods['socialcp_use_emoney_day'] 		= ($_POST['socialcp_use_emoney_day'])?$_POST['socialcp_use_emoney_day']:0;
			$goods['socialcp_use_emoney_percent']		= ($_POST['socialcp_use_emoney_percent'])?$_POST['socialcp_use_emoney_percent']:0;

			$goods['socialcp_cancel_use_refund'] 	= ($_POST['socialcp_cancel_use_refund'])?$_POST['socialcp_cancel_use_refund']:'0';
			$goods['socialcp_cancel_payoption'] 	= ($_POST['socialcp_cancel_payoption'])?$_POST['socialcp_cancel_payoption']:'0';
			$goods['socialcp_cancel_payoption_percent'] 	= ($_POST['socialcp_cancel_payoption_percent'])?$_POST['socialcp_cancel_payoption_percent']:0;

			$goods['shipping_weight_policy'] 	= 'shop';
		}else{
			$goods['socialcp_input_type'] 	= 'price';
			$goods['goods_kind'] 			= 'goods';
		}

		if( $_POST['social_goods_group_name'] ){
			if( $_POST['social_goods_group'] && trim($_POST['social_goods_group_name']) == trim($_POST['social_goods_group_name_tmp']) ){
				$goods['social_goods_group']		= $_POST['social_goods_group'];
			}else{
				$this->load->model('socialgoodsgroupmodel');
				$social_goods_group_name =  trim($_POST['social_goods_group_name']);
				$social_goods_group_data = $this->socialgoodsgroupmodel->get_data_numrow(array("select"=>" group_seq ","whereis"=>" and name = '".$social_goods_group_name."' "));
				if( $social_goods_group_data ) {
					openDialogAlert("이미 등록된 티켓상품그룹명입니다.",400,140,'parent',$callback);
					exit;
				}else{
					if( defined('__SELLERADMIN__') === true ){
						$insertdata['provider_seq'] = $this->providerInfo['provider_seq'];
					}else{
						$insertdata['provider_seq'] = ($goods['provider_seq'])?$goods['provider_seq']:1;
					}
					$insertdata['name'] = trim($_POST['social_goods_group_name']);
					$insertdata['regist_date'] = date("Y-m-d H:i:s",time());
					$social_goods_group_idx = $this->socialgoodsgroupmodel->sggroup_write($insertdata);
					$goods['social_goods_group']		= ($social_goods_group_idx)?$social_goods_group_idx:0;
				}
			}
		}

		// 입점사상품인경우 상태자동처리@2013-08-12
		//  승인 상태 + 입점사 상품 + 판매상태 정상 인 경우
		if($goods['goods_type']=='goods' && $goods['provider_seq'] != 1 && ( $goods['provider_status']==1 && $goods['goods_status']!='unsold' ) ) {
			$this->load->helper('goods');
			if($_POST['goodsSeq'] ) {//update
				$goodsinfochangeuse = goodsinfochange();//상품명/정가/할인가격
				if($goodsinfochangeuse['result'] === true) {
					if( defined('__SELLERADMIN__') === true ) {	// 입점사
						$goods['provider_status']				= 0;			// 미승인처리
						$goods['goods_status']					= 'unsold';		// 판매중지처리
						$goods['goods_view']					= 'notLook';	// 미노출처리
						$goods['provider_status_reason_type']	= '3';
						$goods['provider_status_reason']		= $this->providerInfo['provider_id'] . '에 의해 ' . $goodsinfochangeuse['msg'];
						$goods['admin_log']						= '<div>'.date('Y-m-d H:i:s').'  '.$this->providerInfo['provider_id'].'가(이) '.$goodsinfochangeuse['type'].' 수정. 미승인+판매중지+미노출로 자동 처리됨</div>'."\n";
						if($_POST['goodsinfochage'] != 'ok' ){
							$yescallback = "parent.goodinfochangeok();";//
							$nocallback = "parent.goodinfochangeno();";
							if( $_POST['goods_kind'] == 'coupon' ){//티켓상품 상품이면
								$msg = $goodsinfochangeuse['msg']."<br/><br/>상품명, 정가, 할인가, 구매 대상자 (가격노출 및 버튼노출 제어), 유효기간전 후 취소(환불) 또는 미사용 티켓환불 설정이 변경될 경우 해당 상품은 \\'미승인\\'처리되어 상품은  \\'판매중지\\',\\'미노출\\'이 됩니다.<br/>저장하시겠습니까?";
							}else{
								$msg = $goodsinfochangeuse['msg']."<br/><br/>상품명, 정가, 할인가, 구매 대상자 (가격노출 및 버튼노출 제어)가 변경될 경우 해당 상품은 \\'미승인\\'처리되어 상품은  \\'판매중지\\',\\'미노출\\'이 됩니다.<br/>저장하시겠습니까?";
							}
							openDialogConfirm($msg,650,290,'parent',$yescallback,$nocallback);
							exit;
						}
					}
				}
			}else{//insert
				if( defined('__SELLERADMIN__') === true ) {	// 입점사
					$goods['provider_status']				= 0;			// 미승인처리
					$goods['goods_status']					= 'unsold';		// 판매중지처리
					$goods['goods_view']					= 'notLook';	// 미노출처리
					$goods['provider_status_reason_type']	= '1';
					$goods['provider_status_reason']		= '입점 관리자 ' . $this->providerInfo['provider_id'] . '에 의해 상품이 등록되었습니다.';
				}
			}
		}

		// 관리자 승인/미승인 변화에 따른 처리
		if( isset($_POST['provider_status']) && defined('__SELLERADMIN__') === true ) {
			if	($_POST['goodsSeq']){
				$oldgoods	= $this->goodsmodel->get_goods($_POST['goodsSeq']);
				if	($oldgoods['provider_status'] != $_POST['provider_status']){
					if	($_POST['provider_status'] == '1'){
						$goods['provider_status_reason_type']	= '0';
						$goods['provider_status_reason']		= '';
					}else{
						$goods['provider_status_reason_type']	= '2';
						$goods['provider_status_reason']		= '관리자 ' . $this->managerInfo['manager_id'] . '에 의해 미승인 처리되었습니다.';
						$goods['admin_log']						= '<div>'.$goods['provider_status_reason']."</div>\n";
					}
				}
			}else{
				# 사은품의 경우 옵션이 달라지므로 분기합니다.
				switch($goods['goods_type']) {
					case 'gift':
						$goods['provider_status']							= '1';				// 승인처리
						$goods['goods_view']									= 'look';		// 노출처리
						$goods['provider_status_reason_type']		= '0';
						$goods['provider_status_reason']				= '입점 관리자 ' . $this->providerInfo['provider_id'] . '에 의해 상품이 등록되었습니다.';
						break;
					default:
						$goods['provider_status']							= '0';			// 미승인처리
						$goods['goods_status']					= 'unsold';		// 판매중지처리
						$goods['goods_view']					= 'notLook';	// 미노출처리
						$goods['provider_status_reason_type']	= '1';
						$goods['provider_status_reason']		= '입점 관리자 ' . $this->providerInfo['provider_id'] . '에 의해 상품이 등록되었습니다.';
				}
			}
		}



		//동영상
		foreach($_POST['videofiles']['image'] as $videoimageseq) {//상품이미지영역은 1개뿐
			$videoimageseq = ($videoimageseq)?$videoimageseq:0;
			$goods['video_use']		= (!empty($_POST['viewer_use']['image'][$videoimageseq]))?'Y':'N';//노출여부
			$goods['video_position']		= (!empty($_POST['viewer_position']['image'][$videoimageseq]))?$_POST['viewer_position']['image'][$videoimageseq]:'first';//노출위치(맨앞/맨뒤)
			$goods['video_view_type']		= ($goods['video_use'] == 'Y')?1:0;//상단 상품이미지
			if($_POST['pc_width']['image'][$videoimageseq]) $goods['video_size'] =$_POST['pc_width']['image'][$videoimageseq]."X".$_POST['pc_height']['image'][$videoimageseq];//화면크기
			if($_POST['mobile_width']['image'][$videoimageseq]) $goods['video_size_mobile'] = $_POST['mobile_width']['image'][$videoimageseq]."X".$_POST['mobile_height']['image'][$videoimageseq];//화면크기


			if($_POST['video_del']['image'][$videoimageseq] == 1) {
				$goods['file_key_w'] = '';//원본파일코드초기화
				$goods['file_key_i'] = '';//원본파일코드초기화
			}else{
				if($_POST['file_key_w']['image'][$videoimageseq]) $goods['file_key_w'] = $_POST['file_key_w']['image'][$videoimageseq];
				if($_POST['file_key_i']['image'][$videoimageseq]) $goods['file_key_i'] = $_POST['file_key_i']['image'][$videoimageseq];
			}
		}
		$goods['videototal'] = count($_POST['videofiles']['contents']);//상품설명영역동영상여부if( count($_POST['videofiles']['contents']) > 0 )
		$goods['videousetotal'] = count($_POST['viewer_use']['image']) + count($_POST['viewer_use']['contents']);//노출동영상갯수

		if($_POST['videotmpcode']){//$this->session->userdata('videotmpcode')
			$goods['videotmpcode'] = $_POST['videotmpcode'];//코드
		}

		//개별재고 여부
		$goods['runout_policy']		= $_POST['runout_policy'];
		$goods['able_stock_limit']	= $_POST['able_stock_limit'];

		// 외부 티켓상품 저장
		if	($_POST['coupon_serial_type'] == 'n')
			$goods['coupon_serial_type'] = 'n';
		else
			$goods['coupon_serial_type'] = 'a';


		if ($_POST['feed_goods_use']=='Y') {
				$goods['feed_goods_use'] = 'Y';
			} else {
				$goods['feed_goods_use'] = 'N';
			}

		$_POST['feed_goods_use']	= ($goods['feed_goods_use'] == 'Y') ? 'Y' : 'N';

		// 입점 마케팅 상품명 개별설정
		if ($_POST['feed_goods_use'] == 'Y') {
			if (empty($_POST['feed_goods_name'])) {
				$callback = "parent.document.getElementsByName('feed_goods_name')[0].focus();";
				openDialogAlert('입점 마케팅 상품명을 입력해 주세요.',400,140,'parent',$callback);
				exit;
			}

			$goods['feed_goods_name'] = $_POST['feed_goods_name'];
		}

		// 추가(구성/입력)옵션 화면설정
 		$goods['suboption_layout_group']		= $_POST['suboption_layout_group'];
 		$goods['suboption_layout_position']		= $_POST['suboption_layout_position'];
 		$goods['inputoption_layout_group']		= $_POST['inputoption_layout_group'];
 		$goods['inputoption_layout_position']	= $_POST['inputoption_layout_position'];

 		// 수출입상품코드
 		$goods['hscode']						= $_POST['hscode'];

 		// 외부마켓용 검색단어
		if ($goodsParam['openmarket_keyword']) {
			$goods['openmarket_keyword'] = $goodsParam['openmarket_keyword'];
		}

		// 본사만 추천상품 수정 가능
		$this->load->model('goodsdisplay');
		if(defined('__SELLERADMIN__') === false){
			$goods['relation_type'] 				= $_POST['relation_type'];
			$goods['relation_criteria'] 			= $this->goodsdisplay->check_criteria($_POST['relation_criteria']);
			$goods['relation_criteria_light'] 		= $this->goodsdisplay->check_criteria($_POST['relation_criteria_light']);

			//추천상품 (빅데이터 큐레이션) 자동선정사용시 체크 @2016-06-29 ysm
			if( $_POST['auto_condition_use'] || ( $goods['relation_criteria']!='' && preg_match('/∀/',$goods['relation_criteria'])) ){
				$goods['auto_condition_use'] 	= 1;
			}
			$goods['bigdata_criteria']					= $this->goodsdisplay->check_criteria($_POST['bigdataCriteria']);
		}

		$goods['relation_seller_type']				= $_POST['relation_seller_type'];
		$goods['relation_seller_criteria']			= $this->goodsdisplay->check_criteria($_POST['relation_seller_criteria']);
		$goods['relation_seller_criteria_light']	= $this->goodsdisplay->check_criteria($_POST['relation_seller_criteria_light']);

		//검색용 색상
		$goods['color_pick']						= implode(',', (array)$_POST['color_pick']);

		//노출 제어
		$goods['display_terms']			= ($_POST['display_terms'] == 'AUTO') ? 'AUTO' : 'MENUAL';					// 자동관려 여부
		$goods['display_terms_type']	= ($_POST['display_terms_type'] == 'LAYAWAY') ? 'LAYAWAY' : 'SELLING';		// 예약판매상품 여부
		$goods['display_terms_before']	= ($_POST['display_terms_before'] == 'DISPLAY') ? 'DISPLAY' : 'CONCEAL';	// 노출 시작일 전 노출 여부
		$goods['display_terms_after']	= ($_POST['display_terms_after'] == 'DISPLAY') ? 'DISPLAY' : 'CONCEAL';		// 노출 종료일 후 노출 여부
		$goods['display_terms_begin']	= $_POST['display_terms_begin'];	// 노출 시작일
		$goods['display_terms_end']		= $_POST['display_terms_end'];		// 노출 종료일
		$goods['display_terms_text']	= $_POST['display_terms_text'];		// 예약판매 등 앞문구
		$goods['display_terms_color']	= $_POST['display_terms_color'];	// 예약판매 등 앞문구 색상

		if ($goods['display_terms_type'] == 'LAYAWAY') {
			$goods['possible_shipping_date']	= $_POST['possible_shipping_date'];
			$goods['possible_shipping_text']	= $_POST['possible_shipping_text'];
		} else {
			$goods['possible_shipping_date']	= NULL;
			$goods['possible_shipping_text']	= NULL;
		}

		$display_terms_begin			= strtotime($goods['display_terms_begin']);
		$display_terms_end				= strtotime($goods['display_terms_end']);

		// 입점사 상품이면
		if( defined('__SELLERADMIN__') === true ){
			unset($goods['sub_reserve_policy'], $goods['reserve_policy']);
		}

		// 자동 노출일경우 초기설정
		if ($goods['display_terms'] == 'AUTO') {

			// 유효기간 시작일 전 노출여부에 따라 처리
			if ($display_terms_begin > time()){
				$goods['goods_view']	= ($_POST['display_terms_before'] == 'DISPLAY') ? 'look' : 'notLook' ;
			}

			// 유효기간 종료일 후 노출여부에 따라 처리
			if ($display_terms_end < time()) {
				$goods['goods_view']	= ($_POST['display_terms_after'] == 'DISPLAY') ? 'look' : 'notLook' ;
			}
		}

		// 선물하기
		$goods['present_use'] = ($this->input->post('present_use') === '1') ? '1' : '0';

		return $goods;
	}

	// 이미지 경로 재정의 :: 2016-04-22 lwh
	public function set_goodImages($seq, $aPostParams=''){
		if(!$aPostParams){
		    // without xss filter
		    $aPostParams = $this->input->post(null, false);
		}
		// 에디터 이미지 경로 재정의 :: 2016-04-21 lwh
		$r_date		= ($aPostParams['regist_date']) ? $aPostParams['regist_date'] : date('YmdHis');
		$editor_dir = ROOTPATH.'data/editor/goods/';
		$date		= date('Y',strtotime($r_date)).'/'.date('m',strtotime($r_date));
		$pseq		= ($aPostParams['provider_seq']) ? $aPostParams['provider_seq'] : '1';
		$dir		= $editor_dir . $pseq . '/' . $date;

		if(!file_exists($dir)){
			if(!file_exists($editor_dir)){
				@mkdir($editor_dir);
				@chmod($editor_dir,0777);
			}
			if(!file_exists($editor_dir.$pseq)){
				@mkdir($editor_dir.$pseq);
				@chmod($editor_dir.$pseq,0777);
			}
			if(!file_exists($editor_dir.$pseq.'/'.date('Y',strtotime($r_date)))){
				@mkdir($editor_dir.$pseq.'/'.date('Y',strtotime($r_date)));
				@chmod($editor_dir.$pseq.'/'.date('Y',strtotime($r_date)),0777);
			}
			@mkdir($dir);
		}
		@chmod($dir,0777);
		$dir = str_replace(ROOTPATH,"",$dir);

		// 해당 Editor 만 작업진행 :: 2016-05-09 lwh
		if($aPostParams['contents']){
			$res['contents'] = adjustEditorImages($aPostParams['contents'], '/'.$dir.'/',$seq);
		}
		if($aPostParams['common_contents']){
			$res['common_contents'] = adjustEditorImages($aPostParams['common_contents'], '/'.$dir.'/',$seq);
		}
		if($aPostParams['mobile_contents']){
			$mobile_contents = adjustEditorImages($aPostParams['mobile_contents'], '/'.$dir.'/',$seq);
			// 컨텐츠에 포함된 이미지태그를 찾아서 일정 사이즈로 분할 (모바일용)
			$res['mobile_contents']	= $this->split_images($mobile_contents);
		}

		return $res;
	}

	public function set_goodsImageSize($type,$width,$height){
		$arrNames = $this->get_goodsImageSize_name();
		config_save( 'goodsImageSize', array($type =>array('name'=>$arrNames[$type],'width'=>$width,'height'=>$height)));
	}

	public function get_goodsImageSize(){
		$goodsImageSizeArr = config_load('goodsImageSize');
		@asort($goodsImageSizeArr);
		$arrNames = $this->goodsmodel->get_goodsImageSize_name();
		foreach($goodsImageSizeArr as $k=>$v){
			$v['key'] 		= $k;
			$v['name'] 		= $arrNames[$k];
			$r_img_size[] 	= $v;
			$goodsImageSizeArr[$k] = $v;
		}
		return array($goodsImageSizeArr,$r_img_size);
	}

	public function get_goodsImageSize_name(){
		$arrNames['large'] 			= '상품상세(확대)';
		$arrNames['view'] 			= '대표 사진';
		$arrNames['list1'] 			= '리스트(1)';
		$arrNames['list2'] 			= '리스트(2)';
		$arrNames['thumbView'] 		= '상품상세(썸네일)';
		$arrNames['thumbCart'] 		= '장바구니/주문';
		$arrNames['thumbScroll'] 	= '스크롤';
		return $arrNames;
	}

	// 상품 이미지 업로드 경로 정의 :: 2016-04-20 lwh
	public function upload_goodsImage_dir(){
		$r_date	= ($_POST['regist_date']) ? $_POST['regist_date'] : date('YmdHis');
		$date	= date('Y',strtotime($r_date)).'/'.date('m',strtotime($r_date));
		$pseq	= ($_POST['provider_seq']) ? $_POST['provider_seq'] : '1';
		$g_dir	= ROOTPATH.'data/goods/';
		$dir	= $g_dir . $pseq . '/' . $date;

		if(!file_exists($dir)){
			if(!file_exists($g_dir.$pseq)){
				@mkdir($g_dir.$pseq);
				@chmod($g_dir.$pseq,0777);
			}
			if(!file_exists($g_dir.$pseq.'/'.date('Y',strtotime($r_date)))){
				@mkdir($g_dir.$pseq.'/'.date('Y',strtotime($r_date)));
				@chmod($g_dir.$pseq.'/'.date('Y',strtotime($r_date)),0777);
			}
			@mkdir($dir);
		}
		@chmod($dir,0777);
		$dir = str_replace(ROOTPATH,'/',$dir);
		return $dir;
	}

	// 상품 이미지 파일명 정의 :: 2016-04-20 lwh
	public function get_target_goodsImage($file){
		$goodsSeq = ($this->goodsSeq)? $this->goodsSeq : $_POST['goodsSeq'];

		if( (substr_count($file,'/data/goods/') > 0 || substr_count($file,'/data/tmp/') > 0) && !preg_match('/http:\/\//',$file) ){
			$dir = $this->upload_goodsImage_dir();
			$arr = explode('/', $file);
			$fn = $arr[count($arr)-1];
			$arr2 = explode('_', $fn);

			if($arr2[0]==$goodsSeq){
				$seqLen=strlen($arr2[0]);
				$fn = substr($fn,$seqLen+1);
			}

			$target = $dir.'/'.$goodsSeq.'_'.$fn;
		}else{
			$target= $file;
		}

		return $target;
	}

	public function upload_goodsImage($arr,$oldImg=''){

		foreach( $arr as $i => $file ){

			if(substr_count($file,'/data/goods/') > 0 || substr_count($file,'/data/tmp/') > 0){
				if($oldImg && (substr_count($oldImg,'/data/goods/') > 0 || substr_count($oldImg,'/data/tmp/') > 0)){
					$tmpfile	= explode('.',$file);
					$tmpoldImg	= explode('.',$oldImg);
					$target		= $tmpoldImg[0].'.'.$tmpfile[1];
				}else{
					$target = $this->get_target_goodsImage($file);
				}

				//이미지 압축 18.06.18 kmj
				$tmpExe = explode(".", $file);
				$exe = end($tmpExe);

				if(extension_loaded('imagick') && ($exe == "jpg" || $exe == "jpeg")){
					$img = new Imagick();
					$img->readImage(ROOTPATH.''.substr($file,1));
					$img->setImageCompression(Imagick::COMPRESSION_JPEG);
					$img->setImageCompressionQuality(90);
					$img->stripImage();
					$img->writeImage(ROOTPATH.''.substr($target,1));
					unset($img);
				} else {
					rename('.'.$file,'.'.$target);
				}

				@chmod('.'.$target,0777);

				$res[] = $target;
			}
		}

		return $res;
	}

	// 대표컷의 리스트1,리스트2,썸네일(장바구니 스크롤)
	public function list_image_create()
	{
		$arr_first_keyhead = array('list1','list2','thumbCart','thumbScroll');
		if($_POST['largeGoodsImage'][0]){
			foreach($arr_first_keyhead as $first_keyhead){
				if(!$_POST[$first_keyhead.'GoodsImage'][0]){
					$_POST[$first_keyhead.'GoodsImage'][0] = str_replace('large',$first_keyhead,$_POST['largeGoodsImage'][0]);

					$source = '.'.$_POST['largeGoodsImage'][0];
					$target = '.'.$_POST[$first_keyhead.'GoodsImage'][0];

					$width = $_POST[$first_keyhead.'ImageWidth'];
					$height = $_POST[$first_keyhead.'ImageHeight'];
					if(!$width){
						$width = $_POST[$first_keyhead.'Width'];
						$height = $_POST[$first_keyhead.'Height'];
					}

					$this->goods_temp_image_resize($source,$target,$width,$height);
				}
			}
		}
	}

	// 대표컷2의 리스트1,리스트2
	public function cut2_list_image_create()
	{
		$arr_first_keyhead = array('list1','list2');
		if($_POST['largeGoodsImage'][1]){
			foreach($arr_first_keyhead as $first_keyhead){
				if(!$_POST[$first_keyhead.'GoodsImage'][1]){
					$_POST[$first_keyhead.'GoodsImage'][1] = str_replace('large',$first_keyhead,$_POST['largeGoodsImage'][1]);

					$source = '.'.$_POST['largeGoodsImage'][1];
					$target = '.'.$_POST[$first_keyhead.'GoodsImage'][1];

					$width = $_POST[$first_keyhead.'ImageWidth'];
					$height = $_POST[$first_keyhead.'ImageHeight'];
					if(!$width){
						$width = $_POST[$first_keyhead.'Width'];
						$height = $_POST[$first_keyhead.'Height'];
					}

					$this->goods_temp_image_resize($source,$target,$width,$height);
				}
			}
		}
	}

	// 이미지 연결 :: 2016-04-29 lwh
	public function insert_goodsImage($key,$goodsSeq,$file='',$cut_num=''){

		if($file)	$img_arr = $file;
		else		$img_arr = $_POST[$key];

		$tmp_cnt = ($cut_num) ? $cut_num : 0;
		// 기존 이미지 연결 지우기 :: 2016-05-02 lwh
		$this->db->delete('fm_goods_image', array('goods_seq' => $goodsSeq, 'cut_number'=>$cut_num, 'image_type'=>$key));

		foreach($img_arr as $i => $img){
			if(!$img) continue;

			// 대표컷1~2의 리스트1,리스트2,썸네일(장바구니 스크롤)
			if($key == 'largeGoodsImage' && $tmp_cnt ==0 && $img) $this->list_image_create();
			if($key == 'largeGoodsImage' && $tmp_cnt ==1 && $img) $this->cut2_list_image_create();

			$labelKey = str_replace('Image','Label',$key);
			$type = str_replace('GoodsImage','',$key);

			$imgs = array();
			$imgs['image_type'] = $type;
			$imgs['goods_seq'] = $goodsSeq;
			$imgs['cut_number'] = $tmp_cnt+1;

			// 파일을 통해 넘겨받은경우 그대로 연결 :: 2016-05-12 lwh
			if($file){
				$imgs['image'] = $img;
			}else{
				$imgs['image'] = $this->get_target_goodsImage($img);
			}

			if($_POST["goodsImageColor"][$i]) {
				$imgs["match_color"] = $_POST["goodsImageColor"][$i];
			}

			$imgs['label'] = $_POST[$labelKey][$i];
			$result = $this->db->insert('fm_goods_image', $imgs);
			unset($imgs);
			$tmp_cnt++;


		}
	}

	// 이미지 정보 업데이트 :: 2016-05-02 lwh
	public function set_goodsImg_update($goodsSeq, $division, $idx, $imglabel='', $match_color=''){
		$query = "update fm_goods_image set label = '".$imglabel."', match_color = '".$match_color."' where goods_seq = '".$goodsSeq."' and image_type = '".$division."' and cut_number = '".($idx+1)."'";
		$this->db->query($query);

		if ( $division == 'view' ) {
			$query = "update fm_goods_image set match_color = '".$match_color."' where goods_seq = '".$goodsSeq."' and cut_number = '".($idx+1)."'";
			$this->db->query($query);
		}
	}

	public function get_goods_category($no){
		$CI =& get_instance();
		$result = false;
		if (isset($CI->goods_category[$no])) {
			$result = $CI->goods_category[$no];
		} else {
			$query = "select c.title, c.category_goods_code, l.* from fm_category_link l, fm_category c where l.category_code=c.category_code and l.goods_seq=?";
			$query = $this->db->query($query,array($no));
			foreach($query->result_array() as $data){
				$result[] = $data;
			}
			$CI->goods_category[$no] = $result;
		}
		return $result;
	}

	public function get_goods_brand($no){

		$result = false;
		$query = "select c.title, c.brand_goods_code,c.title_eng,l.*,d.charge
		from fm_brand_link l
		inner join fm_brand c on l.category_code=c.category_code
		inner join fm_goods g on l.goods_seq=g.goods_seq
		left join fm_provider p on g.provider_seq=p.provider_seq
		left join fm_provider_charge d on (l.category_code=d.category_code and p.provider_seq=d.provider_seq)
		where l.goods_seq=?
		group by l.category_link_seq
		";
		$query = $this->db->query($query,array($no));
		foreach($query->result_array() as $data){
			$result[] = $data;
		}
		return $result;
	}

	public function get_goods_location($no){

		$result = false;
		$query = "select c.title, '' as location_goods_code, l.* from fm_location_link l,fm_location c where l.location_code=c.location_code and l.goods_seq=?";
		$query = $this->db->query($query,array($no));
		foreach($query->result_array() as $data){
			$result[] = $data;
		}
		return $result;
	}

	public function get_goods_category_default($no){
		$result = false;
		$query = "select c.title,l.* from fm_category_link l,fm_category c where l.category_code=c.category_code and l.goods_seq=? and l.link=1";
		$query = $this->db->query($query,array($no));
		$result = $query->row_array();
		return $result;
	}

	public function get_goods_brand_default($no){
		$result = false;
		$query = "select c.title,l.* from fm_brand_link l,fm_brand c where l.category_code=c.category_code and l.goods_seq=? and l.link=1";
		$query = $this->db->query($query,array($no));
		$result = $query->row_array();
		return $result;
	}

	public function get_goods_location_default($no){
		$result = false;
		$query = "select c.title,l.* from fm_location_link l,fm_location c where l.location_code=c.location_code and l.goods_seq=? and l.link=1";
		$query = $this->db->query($query,array($no));
		$result = $query->row_array();
		return $result;
	}

	public function get_goods($no){
		$result = false;
		$query = "select * from fm_goods where goods_seq=? limit 1";
		$query = $this->db->query($query,array($no));
		$result = $query->result_array();

		$ea = 1;
		if($result[0]['min_purchase_limit'] == 'limit' && $result[0]['min_purchase_ea']){
			$ea = $result[0]['min_purchase_ea'];
		}
		$result[0]['min_purchase_ea'] = $ea;

		$ea = 0;
		if($result[0]['max_purchase_limit'] == 'limit' && $result[0]['max_purchase_ea']){
			$ea = $result[0]['max_purchase_ea'];
		}
		$result[0]['max_purchase_ea'] = $ea;

		$basic = config_load("basic");	//모바일 PC와 동일 화면 여부 확인 2015-07-13 pjm

		### 공용정보 노출 :: 2014-01-14 lwh
		###  => goods/view_contents, goodsmodel/get_goods_view에서 이쪽으로 옮김. 2015-03-13 pjm
		$infoquery = "select * from fm_goods_info where info_seq=? limit 1";
		$infoquery = $this->db->query($infoquery,array($result[0]['info_seq']));
		$info			= $infoquery->result_array();
		if($result[0]['info_seq'] && $info){
			$result[0]['common_contents'] = $info[0]['info_value'];
			// 반응형일때는 css 에서 제어하기 때문에 예외처리 2020-03-19
			if( $basic['general_m_use'] != "P" && $this->operation_type != 'light' && ( $this->mobileMode || $this->storemobileMode || $this->_is_mobile_agent )) {
				$result[0]['common_contents'] = $this->set_mobile_common_contents($result[0]['common_contents']);
			}
		}

		if (trim($result[0]['multi_discount_policy'])){
			$result[0]['multi_discount_policy']	= json_decode($result[0]['multi_discount_policy'], 1);
			$result[0]['multi_discount_policy_count']	= count($result[0]['multi_discount_policy']['policyList']);
		}else{
			$result[0]['multi_discount_policy']	= '';
		}

		return $result[0];
	}

	public function get_goods_option($no, $sc = array()){
		$op1tArr	= array();
		$op2tArr	= array();
		$op3tArr	= array();
		$op4tArr	= array();
		$op5tArr	= array();
		$result		= false;

		$addBind[]	= $no;
		if	(trim($sc['option1'])){
			$addWhere	.= " and o.option1 = ? ";
			$addBind[]	= $sc['option1'];
		}
		if	(trim($sc['option2'])){
			$addWhere	.= " and o.option2 = ? ";
			$addBind[]	= $sc['option2'];
		}
		if	(trim($sc['option3'])){
			$addWhere	.= " and o.option3 = ? ";
			$addBind[]	= $sc['option3'];
		}
		if	(trim($sc['option4'])){
			$addWhere	.= " and o.option4 = ? ";
			$addBind[]	= $sc['option4'];
		}
		if	(trim($sc['option5'])){
			$addWhere	.= " and o.option5 = ? ";
			$addBind[]	= $sc['option5'];
		}
		if	(trim($sc['option_view'])){
			$addWhere	.= " and o.option_view = ? ";
			$addBind[]	= $sc['option_view'];
		}

		$sql = "select o.*,s.badstock, s.stock, s.supply_price,s.exchange_rate, s.reservation15, s.reservation25, s.safe_stock, s.total_stock, s.total_supply_price, s.total_badstock,g.scm_auto_warehousing from fm_goods_option o left join fm_goods_supply s ON s.option_seq = o.option_seq AND s.goods_seq = o.goods_seq inner join fm_goods as g on g.goods_seq=o.goods_seq where o.goods_seq=? "  . $addWhere . " order by o.option_seq asc";
		$query = $this->db->query($sql,$addBind);
		foreach($query->result_array() as $data){
			$optJoin = "";$optcodeJoin = "";
			if( $data['option_title'] ) $data['option_divide_title'] = explode(',',$data['option_title']);

			if( $data['option_type'] ) $data['option_divide_type'] = explode(',',$data['option_type']);
			if( $data['code_seq'] ) $data['option_divide_codeseq'] = explode(',',$data['code_seq']);

			if( $data['newtype'] ) $data['divide_newtype'] = explode(',',$data['newtype']);
			if( $data['tmpprice'] ) $data['divide_tmpprice'] = explode(',',$data['tmpprice']);

			if( $data['dayauto_type'] ) $data['dayauto_type_title'] = $this->dayautotype[$data['dayauto_type']];
			if( $data['dayauto_day'] ) $data['dayauto_day_title'] = $this->dayautoday[$data['dayauto_day']];
			if( $data['color']!='' ) $data['color'] = trim($data['color']);

			if( $data['option1']!='' ) $optJoin[] = $data['option1'];
			if( $data['option2']!='' ) $optJoin[] = $data['option2'];
			if( $data['option3']!='' ) $optJoin[] = $data['option3'];
			if( $data['option4']!='' ) $optJoin[] = $data['option4'];
			if( $data['option5']!='' ) $optJoin[] = $data['option5'];
			if( $optJoin ){
				$data['opts'] = $optJoin;
			}
			if( $data['optioncode1'] ) $optcodeJoin[] = $data['optioncode1'];
			if( $data['optioncode2'] ) $optcodeJoin[] = $data['optioncode2'];
			if( $data['optioncode3'] ) $optcodeJoin[] = $data['optioncode3'];
			if( $data['optioncode4'] ) $optcodeJoin[] = $data['optioncode4'];
			if( $data['optioncode5'] ) $optcodeJoin[] = $data['optioncode5'];
			if( $optcodeJoin ){
				$data['optcodes'] = $optcodeJoin;
			}

			if( $data['option1']!='' && !in_array($data['option1'], $op1tArr))
				$op1tArr[] = $data['option1'];
			if( $data['option2'] != '' && !in_array($data['option2'], $op2tArr) )
				$op2tArr[] = $data['option2'];
			if( $data['option3'] != '' && !in_array($data['option3'], $op3tArr) )
				$op3tArr[] = $data['option3'];
			if( $data['option4'] != '' && !in_array($data['option4'], $op4tArr) )
				$op4tArr[] = $data['option4'];
			if( $data['option5'] != '' && !in_array($data['option5'], $op5tArr) )
				$op5tArr[] = $data['option5'];

			if	($data['consumer_price']){
				$data['supplyRate'] = get_cutting_price($data['supply_price'] / $data['consumer_price'] * 100);
				//$data['discountRate'] = (int) ( ($data['consumer_price'] - $data['price']) / $data['consumer_price'] * 100 );
				$data['discountRate'] = 100 - get_cutting_price($data['price'] / $data['consumer_price'] * 100);
			}
			$data['tax']		= get_cutting_price($data['price'] - ($data['price'] / 1.1));
			$data['net_profit']	= $data['price'] - $data['supply_price'];

			$data['rstock'] = $data['stock'] - $data[$this->reservation_field];

			// 패키지 상품의 재고 가져오기
			$data_min = '';
			if($data['package_option_seq1']){
				for($i=1;$i<6;$i++){
					if($data['package_option_seq'.$i]){
						$data_package = $this->get_package_by_option_seq($data['package_option_seq'.$i]);
						$data['package_goods_seq'.$i] = $data_package['package_goods_seq'];
						$data['package_stock'.$i] = $data_package['package_stock'];
						$data['package_badstock'.$i] = $data_package['package_badstock'];
						$data['package_ablestock'.$i] = $data_package['package_ablestock'];
						$data['package_safe_stock'.$i] = $data_package['package_safe_stock'];

						if(!$data_min || $data_min['package_stock'] > $data_package['package_stock']){
							$data_min = $data_package;
						}
					}
				}
				if($data_min){
					$data['rstock'] = (int) ($data_min['package_ablestock']);
					$data['badstock'] = (int) $data_min['package_badstock'];
					$data['stock'] = (int) $data_min['package_stock'];
					$data['reservation15'] = (int) $data_min['package_reservation15'];
					$data['reservation25'] = (int) $data_min['package_reservation25'];
					$data['safe_stock'] = (int) $data_min['package_safe_stock'];
				}
			}

			$result[] = $data;
		}
		if	($result[0]){
			$result[0]['optionArr'][] = $op1tArr;
			$result[0]['optionArr'][] = $op2tArr;
			$result[0]['optionArr'][] = $op3tArr;
			$result[0]['optionArr'][] = $op4tArr;
			$result[0]['optionArr'][] = $op5tArr;
		}

		return $result;
	}


	public function get_goods_default_option($no){
		$result = false;
		$sql = "select o.*,s.badstock, s.stock, s.supply_price, s.exchange_rate, s.reservation15, s.reservation25 from fm_goods_option o left join fm_goods_supply s on o.option_seq=s.option_seq where o.goods_seq=? order by o.default_option, o.option_seq asc";
		$query = $this->db->query($sql,array($no));
		foreach($query->result_array() as $data){
			$optJoin = "";
			if( $data['option_title'] ) $data['option_divide_title'] = explode(',',$data['option_title']);

			if( $data['option_type'] ) $data['option_divide_type'] = explode(',',$data['option_type']);
			if( $data['code_seq'] ) $data['option_divide_codeseq'] = explode(',',$data['code_seq']);

			if( $data['newtype'] ) $data['divide_newtype'] = explode(',',$data['newtype']);

			if( $data['dayauto_type'] ) $data['dayauto_type_title'] = $this->dayautotype[$data['dayauto_type']];
			if( $data['dayauto_day'] ) $data['dayauto_day_title'] = $this->dayautoday[$data['dayauto_day']];
			if( $data['color']!='' ) $data['color'] = trim($data['color']);

			if( $data['option1']!='' ) $optJoin[] = $data['option1'];
			if( $data['option2']!='' ) $optJoin[] = $data['option2'];
			if( $data['option3']!='' ) $optJoin[] = $data['option3'];
			if( $data['option4']!='' ) $optJoin[] = $data['option4'];
			if( $data['option5']!='' ) $optJoin[] = $data['option5'];
			if( $optJoin ){
				$data['opts'] = $optJoin;
			}
			if( $data['optioncode1'] ) $optcodeJoin[] = $data['optioncode1'];
			if( $data['optioncode2'] ) $optcodeJoin[] = $data['optioncode2'];
			if( $data['optioncode3'] ) $optcodeJoin[] = $data['optioncode3'];
			if( $data['optioncode4'] ) $optcodeJoin[] = $data['optioncode4'];
			if( $data['optioncode5'] ) $optcodeJoin[] = $data['optioncode5'];
			if( $optcodeJoin ){
				$data['optcodes'] = $optcodeJoin;
			}

			// 패키지 상품의 재고 가져오기
			$data_min = '';
			if($data['package_option_seq1']){
				for($i=1;$i<6;$i++){
					if($data['package_option_seq'.$i]){
						$data_package = $this->get_package_by_option_seq($data['package_option_seq'.$i]);
						$data['package_goods_seq'.$i] = $data_package['package_goods_seq'];
						$data['package_stock'.$i] = $data_package['package_stock'];
						$data['package_badstock'.$i] = $data_package['package_badstock'];
						$data['package_ablestock'.$i] = $data_package['package_ablestock'];
						$data['package_safe_stock'.$i] = $data_package['package_safe_stock'];

						if(!$data_min || $data_min['package_stock'] > $data_package['package_stock']){
							$data_min = $data_package;
						}
					}
				}
				if($data_min){
					$data['rstock']				= (int) $data_min['package_ablestock'];
					$data['badstock']			= (int) $data_min['package_badstock'];
					$data['stock']				= (int) $data_min['package_stock'];
					$data['reservation15']		= (int) $data_min['package_reservation15'];
					$data['reservation25']		= (int) $data_min['package_reservation25'];
					$data['safe_stock']			= (int) $data_min['package_safe_stock'];
				}
			}

			$result[] = $data;
		}
		return $result;
	}


	public function get_default_option($no){
		$result = false;
		$query = "select
					o.*,s.badstock, s.stock, s.safe_stock, s.supply_price, s.exchange_rate, s.reservation15, s.reservation25
				from
					fm_goods_option o,fm_goods_supply s
				where
					o.option_seq=s.option_seq and o.default_option='y' and o.goods_seq=?";
		$query = $this->db->query($query,array($no));
		foreach($query->result_array() as $data){
			$optJoin = "";
			$optcodeJoin = "";
			if( $data['option_title'] ) $data['option_divide_title'] = explode(',',$data['option_title']);

			if( $data['option_type'] ) $data['option_divide_type'] = explode(',',$data['option_type']);
			if( $data['code_seq'] ) $data['option_divide_codeseq'] = explode(',',$data['code_seq']);

			if( $data['newtype'] ) $data['divide_newtype'] = explode(',',$data['newtype']);

			if( $data['dayauto_type'] ) $data['dayauto_type_title'] = $this->dayautotype[$data['dayauto_type']];
			if( $data['dayauto_day'] ) $data['dayauto_day_title'] = $this->dayautoday[$data['dayauto_day']];
			if( $data['color']!='' ) $data['color'] = trim($data['color']);

			if( $data['option1']!='' ) $optJoin[] = $data['option1'];
			if( $data['option2']!='' ) $optJoin[] = $data['option2'];
			if( $data['option3']!='' ) $optJoin[] = $data['option3'];
			if( $data['option4']!='' ) $optJoin[] = $data['option4'];
			if( $data['option5']!='' ) $optJoin[] = $data['option5'];
			if( $optJoin ){
				$data['opts'] = $optJoin;
			}

			if( $data['optioncode1'] ) $optcodeJoin[] = $data['optioncode1'];
			if( $data['optioncode2'] ) $optcodeJoin[] = $data['optioncode2'];
			if( $data['optioncode3'] ) $optcodeJoin[] = $data['optioncode3'];
			if( $data['optioncode4'] ) $optcodeJoin[] = $data['optioncode4'];
			if( $data['optioncode5'] ) $optcodeJoin[] = $data['optioncode5'];
			if( $optcodeJoin ){
				$data['optcodes'] = $optcodeJoin;
			}
			$data['rstock'] = $data['stock']-$data[$this->reservation_field];

			// 패키지 상품의 재고 가져오기
			$data_min = '';
			if($data['package_option_seq1']){
				for($i=1;$i<6;$i++){
					if($data['package_option_seq'.$i]){
						$data_package = $this->get_package_by_option_seq($data['package_option_seq'.$i]);
						$data['package_goods_seq'.$i] = $data_package['package_goods_seq'];
						$data['package_stock'.$i] = $data_package['package_stock'];
						$data['package_badstock'.$i] = $data_package['package_badstock'];
						$data['package_ablestock'.$i] = $data_package['package_ablestock'];
						$data['package_safe_stock'.$i] = $data_package['package_safe_stock'];

						if(!$data_min || $data_min['package_stock'] > $data_package['package_stock']){
							$data_min = $data_package;
						}
					}
				}
				if($data_min){
					$data['rstock']				= (int) $data_min['package_ablestock'];
					$data['badstock']			= (int) $data_min['package_badstock'];
					$data['stock']				= (int) $data_min['package_stock'];
					$data['reservation15']		= (int) $data_min['package_reservation15'];
					$data['reservation25']		= (int) $data_min['package_reservation25'];
					$data['safe_stock']			= (int) $data_min['package_safe_stock'];
				}
			}

			$result = $data;
		}
		return $result;
	}

	public function get_tot_option($no){
		$result = false;
		$query = "select
			o.*,
			s.stock as stock,
			case when s.stock <= 0 then 1 else 0 end as stocknothing,
			s.badstock as badstock,
			s.reservation15 as reservation15,
			s.reservation25 as reservation25,
			s.total_supply_price	as total_supply_price,
			s.total_stock			as total_stock,
			s.total_badstock		as total_badstock,
			case when ( CONVERT(s.stock * 1, SIGNED) - CONVERT(s.".$this->reservation_field." * 1, SIGNED)) <= 0 then 1 else 0 end as rstocknothing
			from fm_goods_option o,fm_goods_supply s where o.option_seq=s.option_seq and o.goods_seq=?";
		$query = $this->db->query($query,array($no));
		$result['a_stock_cnt'] = 0;
		$result['b_stock_cnt'] = 0;
		foreach($query->result_array() as $data){
			$data['rstock'] = $data['stock']-$data['badstock']-$data[$this->reservation_field];
			// 패키지 상품의 재고 가져오기
			$data_min = '';
			if($data['package_option_seq1']){
				for($i=1;$i<6;$i++){
					if($data['package_option_seq'.$i]){
						$data_package = $this->get_package_by_option_seq($data['package_option_seq'.$i]);
						$data['package_goods_seq'.$i] = $data_package['package_goods_seq'];
						$data['package_stock'.$i] = $data_package['package_stock'];
						$data['package_badstock'.$i] = $data_package['package_badstock'];
						$data['package_ablestock'.$i] = $data_package['package_ablestock'];
						$data['package_safe_stock'.$i] = $data_package['package_safe_stock'];

						if(!$data_min || $data_min['package_stock'] > $data_package['package_stock']){
							$data_min = $data_package;
						}
					}
				}
				if($data_min){
					$data['rstock']			= (int) $data_min['package_ablestock'];
					$data['badstock']		= (int) $data_min['package_badstock'];
					$data['stock']			= (int) $data_min['package_stock'];
					$data['reservation15']	= (int) $data_min['package_reservation15'];
					$data['reservation25']	= (int) $data_min['package_reservation25'];
					$data['safe_stock']		= (int) $data_min['package_safe_stock'];
				}
			}
			else { //if($data['package_option_seq1'] == '') { /*사은품 카운트 오류... else로 변경*/
				if($data['stock'] > 0) {
					$result['a_rstock']				+= $data['rstock'];
					$result['a_stock']				+= $data['stock']; //가용재고 > 0인 옵션
					$result['a_stock_cnt']++;
				} else {
					$result['b_rstock']				+= $data['rstock'];
					$result['b_stock']				+= $data['stock']; //가용재고 <= 0인 옵션
					$result['b_stock_cnt']++;
				}
			}

			$result['rstock']				+= $data['rstock'];
			$result['stock']				+= $data['stock'];
			$result['badstock']				+= $data['badstock'];
			$result['reservation15']		+= $data['reservation15'];
			$result['reservation25']		+= $data['reservation25'];
			$result['rtotal_supply_price']	+= $data['total_supply_price'];
			$result['rtotal_stock']			+= $data['total_stock'];
			$result['rtotal_badstock']		+= $data['total_badstock'];
		}

		if($result['a_rstock'] == 0 && $result['a_stock'] == 0 && $result['a_stock_cnt'] == 0) {
			$result['a_rstock'] = "-";
			$result['a_stock'] = "-";
		}
		if($result['b_rstock'] == 0 && $result['b_stock'] == 0 && $result['b_stock_cnt'] == 0) {
			$result['b_rstock'] = "-";
			$result['b_stock'] = "-";
		}

		return $result;
	}

	public function get_goods_addition ($no){
		$result = false;
		$sql = "select addition_seq, title, contents, type, code_seq , contents_title , linkage_val,
				CASE WHEN type = 'model' THEN '모델명'
					WHEN type = 'brand' THEN '브랜드'
					WHEN type = 'manufacture' THEN '제조사'
					WHEN type = 'orgin' THEN '원산지'
					WHEN type = 'direct' THEN title
					ELSE title END AS name from fm_goods_addition where goods_seq=? order by addition_seq asc";
		$query = $this->db->query($sql,array($no));
		//$this->db->where('goods_seq', $no);
		//$query = $this->db->get('fm_goods_addition');
		foreach($query->result_array() as $data){
			$data['contents_view'] = $data['contents_title'];
			if($data['contents_view']=="") {
				$data['contents_view'] = $data['contents'];
			}
			$result[] = $data;
		}
		return $result;
	}

	public function get_goods_icon ($no, $admin=null){
		$result = false;
		$today = date('Y-m-d');

		$sql = "select * from fm_goods_icon where goods_seq=?";

		if(empty($admin)){
			$sql .= " and (
				(ifnull(start_date,'0000-00-00') = '0000-00-00' and ifnull(end_date,'0000-00-00') = '0000-00-00')
				or
				(curdate() between start_date and end_date)
				or
				(start_date <= curdate() and ifnull(end_date,'0000-00-00') = '0000-00-00')
				or
				(end_date >= curdate() and ifnull(start_date,'0000-00-00') = '0000-00-00')
			)
			";
		}

		$sql .= " order by icon_seq asc";

		$query = $this->db->query($sql,$no);
		foreach($query->result_array() as $data){
			$result[] = $data;
		}
		return $result;
	}

	//티켓상품취소(환불)
	public function get_goods_socialcpcancel($no){
		$result = false;
		$sql = "select * from fm_goods_socialcp_cancel where goods_seq=?";
		$sql .= " order by seq asc limit 1";//% 취소(환불) 가능 1개
		$query = $this->db->query($sql,array($no));
		foreach($query->result_array() as $data){
			$result[] = $data;
			$firstpercent = $data['seq'];
		}

		$sql = "select * from fm_goods_socialcp_cancel where goods_seq=? and seq != ? ";
		$sql .= " order by socialcp_cancel_day desc";//% 공제 후 취소(환불) 가능
		$query = $this->db->query($sql,array($no,$firstpercent));
		foreach($query->result_array() as $data){
			$result[] = $data;
		}
		return $result;
	}

	public function get_goods_suboption($no, $sc = array()){
		$result = false;
		$arr	= array();

		$addBind[]	= $no;
		if	(trim($sc['suboption_title'])){
			$addWhere	.= " and o.suboption_title = ? ";
			$addBind[]	= $sc['suboption_title'];
		}
		if	(trim($sc['suboption'])){
			$addWhere	.= " and o.suboption = ? ";
			$addBind[]	= $sc['suboption'];
		}
		if	(trim($sc['option_view'])){
			$addWhere	.= " and o.option_view = ? ";
			$addBind[]	= $sc['option_view'];
		}

		$query = "select o.*,s.stock,s.badstock,s.supply_price, s.reservation15, s.reservation25, s.safe_stock, s.total_supply_price, s.total_stock, s.total_badstock from fm_goods_suboption o,fm_goods_supply s where o.suboption_seq=s.suboption_seq and o.goods_seq=? " . $addWhere . " order by o.suboption_seq asc";
		$query = $this->db->query($query,$addBind);
		foreach($query->result_array() as $data){
			if(!in_array($data['suboption_title'],$arr)) $arr[] = $data['suboption_title'];
			list($key) = array_keys($arr,$data['suboption_title']);

			if	($data['consumer_price']){
				$data['supplyRate'] = floor($data['supply_price'] / $data['consumer_price'] * 100);
				$data['discountRate'] = 100 - floor($data['price'] / $data['consumer_price'] * 100);
			}
			$data['tax']		= floor($data['price'] - ($data['price'] / 1.1));
			$data['net_profit']	= $data['price'] - $data['supply_price'];

			// 패키지 상품의 재고 가져오기
			$data_min = '';
			if($data['package_option_seq1']){
				$data_package = $this->get_package_by_option_seq($data['package_option_seq1']);
				$data['package_goods_seq1']		= $data_package['package_goods_seq'];
				$data['package_stock1']			= $data_package['package_stock'];
				$data['package_badstock1']		= $data_package['package_badstock'];
				$data['package_ablestock1']		= $data_package['package_ablestock'];
				$data['package_safe_stock1']	= $data_package['package_safe_stock'];
				$data['package_goods_code1']	= $data_package['package_goods_code'];
				$data['package_option_code1']	= $data_package['package_option_code'];
				$data['weight1']				= $data_package['weight'];

				if(!$data_min || $data_min['package_stock'] > $data_package['package_stock']){
					$data_min = $data_package;
				}
				if($data_min){
					$data['rstock']				= (int) $data_min['package_ablestock'];
					$data['badstock']			= (int) $data_min['package_badstock'];
					$data['stock']				= (int) $data_min['package_stock'];
					$data['reservation15']		= (int) $data_min['package_reservation15'];
					$data['reservation25']		= (int) $data_min['package_reservation25'];
					$data['safe_stock']			= (int) $data_min['package_safe_stock'];
				}
			}

			$result[$key][] = $data;
		}
		return $result;
	}

	public function get_goods_images($goods_seq_array){
		$result = false;
		$this->db->where_in('goods_seq', $goods_seq_array);
		$this->db->order_by('cut_number asc, image_seq asc');
		$query = $this->db->get('fm_goods_image');

		// 성인인증 세션 및 상품 성인여부 검색
		$adult_auth		= $this->session->userdata('auth_intro');
		$this->db->where('goods_seq', $no);
		$goods_query	= $this->db->get('fm_goods');
		$goods			= $goods_query->row_array();

		$this->load->library('goodsList');
		// 19mark 이미지
		$markingAdultImg = $this->goodslist->checkingMarkingAdultImg($goods);

		foreach($query->result_array()  as $data){
			if(preg_match("/^\//",$data['image']) && !file_exists(ROOTPATH.$data['image'])) continue;

			if ($markingAdultImg) {
				$data['image']	= $this->goodslist->adultImg;
			}
			$result[$data['goods_seq']][$data['cut_number']][$data['image_type']] = $data;
		}

		return $result;
	}

	public function get_goods_image($no,$arr=array()){
		$this->load->library('goodsList');

		$result = false;
		$this->db->where('goods_seq', $no);
		if($arr){
			foreach($arr as $key=>$val){
				$this->db->where($key, $val);
			}
		}
		$this->db->order_by('cut_number asc, image_seq asc');
		$query = $this->db->get('fm_goods_image');

		$this->db->where('goods_seq', $no);
		$goods_query	= $this->db->get('fm_goods');
		$goods		= $goods_query->row_array();
		// 19mark 이미지
		$markingAdultImg = $this->goodslist->checkingMarkingAdultImg($goods);

		$cut_idx 		= 0;
		$old_cut_idx 	= '';
		foreach($query->result_array()  as $key => $data){
			// 실제 파일이 존재하지 않아도 관리자 상세화면에 표시되도록 수정 - 이정록 - 2016-07-08
			//if(preg_match("/^\//",$data['image']) && !file_exists(ROOTPATH.$data['image'])) continue;

			if($markingAdultImg){
				$data['image']	= $this->goodslist->adultImg;
			}

			list($imageWidth, $imageHeight) = getimagesize(ROOTPATH.$data['image']);

			$data['imageWidth'] = $imageWidth > 0 ? $imageWidth : 0;
			$data['imageHeight'] = $imageHeight > 0 ? $imageHeight : 0;

			if($old_cut_idx != $data['cut_number']){
				$cut_idx++;
				$old_cut_idx = $data['cut_number'];
			}
			$result[$cut_idx][$data['image_type']] = $data;

		}
		return $result;
	}

	public function get_goods_input($no){
		$result = false;
		$this->db->where('goods_seq', $no);
		$this->db->order_by("input_seq asc");
		$query = $this->db->get('fm_goods_input');
		foreach($query->result_array() as $data){
			$result[] = $data;
		}
		return $result;
	}

	/*
	 상품(단품) 해당하는 배송정보를 가져온다. 가격비교 및 상품상세에서 사용
	 shipping_policy : goods,shop
	 goods_shipping_policy limited,unlimited  (shipping_unit > 0 ? limited :  unlimited)
	 unlimit_shipping_price : 합포장이 무제한인 경우 개별배송비(goods_shipping_cost)
	 limit_shipping_price : 개별배송비(basic_shipping_cost)
	 limit_shipping_ea 합포장 단위(shipping_unit)
	 limit_shipping_subprice 추가 합포장 배송비(add_shipping_cost)
	*/
	public function get_shipping_policy($data_goods){

		if($data_goods['goods_kind']=='coupon'){
			$data_shipping_policy['shipping_method']['coupon'] = "티켓배송";//쿠폰배송->티켓배송
			return $data_shipping_policy;
		}

		$this->load->model('providermodel');
		$this->load->model('providershipping');

		$data_provider = $this->providermodel->get_provider($data_goods['provider_seq']);
		$shipping_provider_seq = $data_goods['provider_seq'];
		if( $data_goods['trust_shipping'] == 'Y' ) $shipping_provider_seq = 1;
		$provider_shipping_policy = $this->providershipping->get_provider_shipping($shipping_provider_seq);

		if($data_goods['shipping_policy'] == 'shop'){
			$data_shipping_policy = $provider_shipping_policy;
		}else{
			$data_shipping_policy['goods_shipping_policy'] = $data_goods['goods_shipping_policy'];
			$data_shipping_policy['unlimit_shipping_price'] = $data_goods['unlimit_shipping_price'];
			$data_shipping_policy['limit_shipping_price'] = $data_goods['limit_shipping_price'];
			$data_shipping_policy['limit_shipping_subprice'] = $data_goods['limit_shipping_subprice'];
			$data_shipping_policy['limit_shipping_ea'] = $data_goods['limit_shipping_ea'];
			$data_shipping_policy['use_yn'] = $provider_shipping_policy['use_yn'];
			$data_shipping_policy['postpaid_yn'] = $provider_shipping_policy['postpaid_yn'];
			//입점사 선불설정 또는 상품개별배송비 택배(선불) 설정시 @2016-11-30
			if( $provider_shipping_policy['shipping_method']['delivery'] || $data_goods['goods_shipping_policy'] == 'unlimit' ){
				$data_shipping_policy['shipping_method']['each_delivery'] = "택배개별(선불)";
				if( $data_goods['goods_shipping_policy'] == 'unlimit' ){
					if($data_shipping_policy['unlimit_shipping_price']){
						$data_shipping_policy['summary']['each_delivery'] = get_currency_price($data_shipping_policy['unlimit_shipping_price'],3);
					}else{
						$data_shipping_policy['summary']['each_delivery'] = "무료배송";
					}
				}else{
					if($data_shipping_policy['limit_shipping_price']){
						$data_shipping_policy['summary']['each_delivery'] = get_currency_price($data_shipping_policy['limit_shipping_price'],3);
					}else{
						$data_shipping_policy['summary']['each_delivery'] = "무료배송";
					}
				}
			}
		}

		$data_shipping_policy['policy'] = $data_goods['shipping_policy'];

		// 구) 배송정보에 신) 배송설정 매칭 :: START 2016-10-27 lwh
		$this->load->model("shippingmodel");
		$ship_set_arr = $this->shippingmodel->get_shipping_set($data_goods['shipping_group_seq']);
		$dupli_arr = array();
		foreach($ship_set_arr as $k => $val){
			$method_key = $val['shipping_set_code'];
			$method_seq = $val['shipping_set_seq'];

			// 예외상황 처리
			if($method_key == 'direct_store' || $method_key == 'direct_delivery') continue;
			if($dupli_arr[$method_key]) continue;
			$shipping_policy[$method_seq] = $val['shipping_set_name'];
			$dupli_arr[$method_key] = $method_key;
		}
		unset($data_shipping_policy['shipping_method']);
		$data_shipping_policy['shipping_method'] = $shipping_policy;
		// 구) 배송정보에 신) 배송설정 매칭 :: END

		return $data_shipping_policy;
	}

	/*
	 상품(단품) 해당하는 배송정보를 가져온다. 가격비교 및 상품상세에서 사용
	 shipping_policy : goods,shop
	 goods_shipping_policy limited,unlimited  (shipping_unit > 0 ? limited :  unlimited)
	 unlimit_shipping_price : 합포장이 무제한인 경우 개별배송비(goods_shipping_cost)
	 limit_shipping_price : 개별배송비(basic_shipping_cost)
	 limit_shipping_ea 합포장 단위(shipping_unit)
	 limit_shipping_subprice 추가 합포장 배송비(add_shipping_cost)
	*/
	public function get_goods_delivery($goods,$ea=1,$shipping_group=''){
		$this->load->model('providermodel');
		$this->load->model('providershipping');

		$delivery['policy'] = $goods['shipping_policy'];
		$provider			= $this->providermodel->get_provider($goods['provider_seq']);

		/* 본사배송이면 본사의 배송정보 */
		# 위탁배송설정이 입점사별 설정에서 상품별 설정으로 변경됨. @2016-
		if($goods['trust_shipping']=='Y'){
			$data_shipping		= $this->providershipping->get_provider_shipping(1);
			$shipping_provider	= $this->providermodel->get_provider(1);
		}else{
			$data_shipping		= $this->providershipping->get_provider_shipping($goods['provider_seq']);
			$shipping_provider	= $this->providermodel->get_provider($goods['provider_seq']);
		}

		$delivery['shipping_provider']	= $shipping_provider;
		$delivery['provider_name']		= $provider['provider_name'];
		// 실제 입점사 코드 저장 2015-04-29 pjm
		$delivery['real_provider_seq']	= $goods['provider_seq'];
		// 배송 책임 입점사 코드 저장 :: 2017-01-12 lwh
		$delivery['provider_seq']		= $shipping_provider['provider_seq'];
		$delivery['sigungu']			= array();
		$delivery['addDeliveryCost']	= array();

		$delivery['addDeliveryCost']	= $data_shipping['addDeliveryCost'];
		$delivery['sigungu']			= $data_shipping['sigungu'];
		$delivery['sigungu_street']		= $data_shipping['sigungu_street'];

		$delivery['deliGroup']			= $provider['deli_group'];

		if(!$goods['shipping_method']) $goods['shipping_method'] = 'delivery';

		if( $goods['shipping_method'] != 'delivery' ){
			$delivery['summary'] = $data_shipping[$goods['shipping_method'].'_summary'];
		}

		// 개별 배송비 계산
		if( $goods['shipping_policy']=='goods'){

			$delivery['price'] = 0;
			$delivery['type'] = 'delivery';
			$delivery['box_ea'] = 1;

			if( $goods['goods_shipping_policy'] == 'unlimit' ){
				if( $goods['unlimit_shipping_price'] > 0 ) $delivery['price'] = $goods['unlimit_shipping_price'];
			}else if( $goods['goods_shipping_policy'] == 'limit' ){

				if($ea > $goods['limit_shipping_ea']){
					$delivery['box_ea'] = ceil($ea / $goods['limit_shipping_ea']);
				}

				if( $goods['limit_shipping_price'] >= 0 ){
					$delivery['price'] = $goods['limit_shipping_price'];
					if($ea > $goods['limit_shipping_ea']){
						$delivery['price'] += ( ceil($ea / $goods['limit_shipping_ea']) - 1 ) * $goods['limit_shipping_subprice'];
					}
				}
			}
		}else{

			$delivery['type'] = $goods['shipping_method'];

			// 택배 선불
			if($data_shipping['use_yn'] == 'y' && $goods['shipping_method']=='delivery'){

				$delivery['deliGroup'] = $data_shipping['deliGroup'] ? $data_shipping['deliGroup'] : $delivery['deliGroup'];

				$delivery['price'] = 0;


				if( $goods['r_category'] ) $goods['category_code'] = $goods['r_category'];
				if(!$goods['brand_code']) $goods['brand_code'] = $goods['r_brand'];

				if( $data_shipping['order_delivery_free'] == 'free' ){

					if( $data_shipping['issue_goods'] &&  $goods['goods_seq'] ){
						$arr_issue_goods =  explode('|',$data_shipping['issue_goods']);
						if( in_array($goods['goods_seq'],$arr_issue_goods) ){
							$free = true;
						}
					}

					if( $data_shipping['issue_category_code'] && $goods['category_code'] ){
						$arr_issue_category_code =  explode('|',$data_shipping['issue_category_code']);

						foreach($goods['category_code'] as $category_code){
							if( in_array($category_code,$arr_issue_category_code) ){
								$free = true;
							}
						}
					}


					if( $data_shipping['issue_brand_code'] &&  $goods['brand_code']){
						$arr_issue_brand_code =  explode('|',$data_shipping['issue_brand_code']);
						foreach($goods['brand_code'] as $brand_code){
							if( in_array($brand_code,$arr_issue_brand_code) ){
								$free = true;
							}
						}
					}

					if( $data_shipping['except_issue_goods'] &&  in_array($goods['goods_seq'],$data_shipping['except_issue_goods']) ){
						$free = false;
					}

				}

				if( $free ){
					$data_shipping['delivery_cost_policy'] = 'free';
				}

				switch($data_shipping['delivery_cost_policy']){
					case "ifpay" :
						if($data_shipping['ifpay_free_price'] > 0){
							$delivery['price'] = $data_shipping['ifpay_delivery_cost'];
							$delivery['free'] = $data_shipping['ifpay_free_price'];
						}
						break;
					case "pay" :
						if($data_shipping['pay_delivery_cost'] > 0){
							$delivery['price'] = $data_shipping['pay_delivery_cost'];
						}
						break;
					case "free":
							$delivery['price'] = 0;
						break;
				}

			}
		}

		#착불
		if( $data_shipping['postpaid_use_yn'] == 'y' && in_array($goods['shipping_method'],array('postpaid','each_postpaid')) ){

			switch($data_shipping['delivery_cost_policy']){
				case "ifpay" :
					$delivery['postpaid']  = $data_shipping['ifpostpaid_delivery_cost'];
					break;
				case "pay" :
					$delivery['postpaid']  = $data_shipping['postpaid_delivery_cost'];
					break;
			}
			$delivery['postpaid']  = $data_shipping['postpaid_delivery_cost'];

		}

		return $delivery;
	}

	public function get_goods_list($arrNo,$imageType='list1', $limit=null){

		$this->load->model('categorymodel');
		$this->load->library('sale');
		$this->load->library('goodsList');

		if(is_object($arrNo)) $arrNo = (array)$arrNo;

		if (preg_match('/[^0-9,]/', implode(',', $arrNo))) {
			return [];
		};	

		//--> sale library 할인 적용 사전값 전달
		if	($imageType == 'thumbScroll')	$applypage	= 'lately_scroll';
		else								$applypage	= 'list';
		$param['cal_type']				= 'list';
		$param['member_seq']			= $this->userInfo['member_seq'];
		$param['group_seq']				= $this->userInfo['group_seq'];
		$this->sale->set_init($param);
		$this->sale->preload_set_config($applypage);
		//<-- //sale library 할인 적용 사전값 전달
		$this->db->select("g.*, o.*, g.goods_seq AS goods_seq, p.provider_name");
		$this->db->from("fm_goods AS g");
		$this->db->join("fm_provider AS p", "p.provider_seq=g.provider_seq", "INNER");
		$this->db->join("fm_goods_option AS o", "g.goods_seq=o.goods_seq AND o.default_option='y'", "INNER");
		$this->db->where_in("g.goods_seq", $arrNo);
		$this->db->order_by("field(g.goods_seq, ".implode(',',$arrNo).")");

		// limit 조건 추가 :: 2019-09-30 pjw 
		if(!empty($limit))	$query = $this->db->limit($limit);

		$query = $this->db->get();

		foreach($query->result_array() as $data){

			$query2 = "SELECT * FROM fm_goods_image WHERE goods_seq=? AND cut_number='1' AND image_type=? LIMIT 1";
			$query2 = $this->db->query($query2,array($data['goods_seq'],$imageType));
			$row2	= $query2->row_array();
			if($row2) $data  = array_merge($data,$row2);
			// 19mark 이미지
			$markingAdultImg = $this->goodslist->checkingMarkingAdultImg($data);
			if ($markingAdultImg) {
				$data['image'] = $this->goodslist->adultImg;
			}

			$query2 = "select * from fm_goods_icon where goods_seq=?";
			$query2 = $this->db->query($query2,$data['goods_seq']);
			foreach($query2->result_array() as $data2){
				$data['icons'][] = str_replace('.gif','',$data2);
			}

			$data['org_price']	= ($data['consumer_price'] > 0) ? $data['consumer_price'] : $data['price'];

			$data['goods_kind_icon'] = "";
			if($data['goods_kind'] == "coupon"){
				$data['goods_kind_icon'] = "<img src='../skin/default/images/design/icon_order_ticket.gif' align='absmiddle'>&nbsp;";
			}elseif($data['package_yn'] == "y"){
				$data['goods_kind_icon'] = "<img src='../skin/default/images/design/icon_order_package.gif' align='absmiddle'>&nbsp;";
			}

			// 카테고리정보
			$tmparr2	= array();
			$categorys	= $this->get_goods_category($data['goods_seq']);
			if	($categorys)foreach($categorys as $val){
				$tmparr = $this->categorymodel->split_category($val['category_code']);
				foreach($tmparr as $cate) $tmparr2[] = $cate;
			}
			if($tmparr2){
				$tmparr2 = array_values(array_unique($tmparr2));
				$data['r_category']	= $tmparr2;
			}

			//----> sale library 적용
			unset($param, $sales);
			$param['consumer_price']		= $data['consumer_price'];
			$param['price']					= $data['price'];
			$param['total_price']			= $data['price'];
			$param['ea']					= 1;
			$param['category_code']			= $goods['r_category'];
			$param['goods_seq']				= $data['goods_seq'];
			$param['goods']					= $data;
			$this->sale->set_init($param);
			$sales	= $this->sale->calculate_sale_price($applypage);
			$data['sale_price']				= $sales['result_price'];
			$data['tot_reserve']			= $data['reserve'] + $sales['tot_reserve'];
			$this->sale->reset_init();
			unset($sales);
			//<---- sale library 적용

			//예약 상품의 경우 문구를 넣어준다 2016-11-07
			$data['goods_name']				= get_goods_pre_name($data,true);

			$result[$data['goods_seq']] = $data;
		}

		return $result;
	}

	/* 상품 뷰 증가 */
	public function increase_page_view($no){
		$bind[] = $no;
		$query = "update fm_goods set page_view=page_view+1 where goods_seq=?";
		$this->db->query($query,$bind);

		/* 상품분석 수집 */
		$this->load->model('goodslog');
		$this->goodslog->add('view',$no);
	}

	/* 상품 리뷰 등록/삭제시 증가/차감 */
	public function goods_review_count($no, $type = 'plus'){
		$bind[] = $no;
		$query = "update fm_goods a set a.review_count= ifnull((SELECT count(*) FROM `fm_goods_review` WHERE find_in_set(a.goods_seq,goods_seq)),0)
		, a.review_sum = ifnull((SELECT sum(ifnull(score,0)) FROM `fm_goods_review` WHERE find_in_set(a.goods_seq,goods_seq)),0)
		where a.goods_seq=?";
		$this->db->query($query,$bind);
	}

	/* 상품 리뷰 수정시 평점만 증가/차감 */
	public function goods_review_sum($no){
		$bind[] = $no;
		$query = "update fm_goods a set a.review_sum = ifnull((SELECT sum(ifnull(score,0)) FROM `fm_goods_review` WHERE find_in_set(a.goods_seq,goods_seq)),0)
		where a.goods_seq=?";
		$this->db->query($query,$bind);
	}

	/* 상품 문의 증가/차감 */
	public function goods_qna_count($no, $type = 'plus'){
		$bind[] = $no;
		$query = "update fm_goods a set a.qna_count= ifnull((SELECT count(*) FROM `fm_goods_qna` WHERE find_in_set(a.goods_seq,goods_seq)),0)
		where a.goods_seq=?";
		$this->db->query($query,$bind);
	}


	/* 상품 like 증가 */
	public function goods_like_count($no, $count, $mode = null){
		if($this->db->es_use === true){
			$this->goods_like_count_es($no, $count, $mode);
		} else {
			$bind[] = $no;
			if( $this->__APP_ID__ == '455616624457601' && $this->__APP_VER__ == "1.0") {//기본앱 1.0버전
			$like_count = $count['like_count'] + $count['share_count'];
			$query = "update fm_goods set like_count='".$like_count."',fb_update='".date('Y-m-d')."' where goods_seq=?";
			}else{
				$query = "update fm_goods a set a.like_count= ifnull((SELECT count(*) FROM `fm_goods_fblike` WHERE a.goods_seq = goods_seq),0),fb_update='".date('Y-m-d')."' where a.goods_seq=?";
			}
			$this->db->query($query,$bind);
		}
	}

	public function goods_like_count_es($no, $count, $mode = 'like'){
		$cid = $this->elasticsearch->index_check('stats_goods');
		if($cid !== false){
			$params['referer']			= 'none';
			$params['referer_domain']	= 'none';
			if( $_COOKIE['shopReferer'] && preg_match('/^http[s]*\:\/\//', $_COOKIE['shopReferer']) ) {
				$tmp = parse_url($_COOKIE['shopReferer']);
				if ($tmp['host']){
					$domain						= $tmp['host'];
					$domain						= preg_replace('/^(www\.|m\.)/', '', $domain);
					$params['referer_domain']	= $domain;
				}
				$params['referer']				= $_COOKIE['shopReferer'];
			}

			$params['goods_seq']		= $no;
			$params['goods_name']		= $goods['goods_name'];
			$params['provider_seq']		= $goods['provider_seq'];
			$params['platform']			= $this->platform;
			if($mode == 'like'){ //????
				$params['ea'] = 1;
			} else {
				$params['ea'] = 0;
			}

			$esParams = $this->elasticsearch->get_stats_params($cid, 'stats_goods', 'like', $params, $this->userInfo);
			if($esParams){
				$this->elasticsearch->esClientM->index($esParams);
			}
		}
	}

	/* 상품 like 정보가져오기 */
	public function goods_like_viewer($no){
		$query = "SELECT like_count  FROM `fm_goods` where goods_seq='{$no}'";
		$query = $this->db->query($query);
		list($row) = $query->result_array();
		return $row;
	}

	// input array(goods_seq,goods_seq)
	public function get_category_codes($goods_seq_array)
	{
		if	(is_array($goods_seq_array) && count($goods_seq_array) > 0){
			$query = $this->db->query("select category_code, goods_seq from fm_category_link where link=1 and goods_seq in (". implode(',', $goods_seq_array) .")");
			foreach($query->result_array() as $data_category) $result_category[$data_category['goods_seq']]['category_code'] = $data_category['category_code'];
			return $result_category;
		}else{
			return false;
		}
	}
	// input array(goods_seq,goods_seq)
	public function get_provider_names($goods_seq_array)
	{
		if	(is_array($goods_seq_array) && count($goods_seq_array) > 0){
			$query = "SELECT g.goods_seq, p.provider_name, pg.pgroup_name, pg.pgroup_icon FROM fm_goods as g LEFT JOIN fm_provider as p ON g.provider_seq = p.provider_seq LEFT JOIN fm_provider_group as pg ON p.pgroup_seq = pg.pgroup_seq WHERE g.goods_seq in (". implode(',', $goods_seq_array) .")";
			$query = $this->db->query($query);
			foreach($query->result_array() as $data_provider){
				$result_provider[$data_provider['goods_seq']]['provider_name']	= $data_provider['provider_name'];
				$result_provider[$data_provider['goods_seq']]['pgroup_name']	= $data_provider['pgroup_name'];
				$result_provider[$data_provider['goods_seq']]['pgroup_icon']	= $data_provider['pgroup_icon'];
			}
			return $result_provider;
		}else{
			return false;
		}
	}
	// input array(goods_seq,goods_seq)
	public function get_colors($goods_seq_array)
	{
		if	(is_array($goods_seq_array) && count($goods_seq_array) > 0){
			$query = $this->db->query("select goods_seq, group_concat(DISTINCT ifnull(color,'')) colors from fm_goods_option where color != '' and goods_seq in (". implode(',', $goods_seq_array) .") group by goods_seq");
			foreach($query->result_array() as $data_option) $result_option[$data_option['goods_seq']] = $data_option['colors'];
			return $result_option;
		}else{
			return false;
		}
	}
	// input array(goods_seq,goods_seq), 이미지타입
	public function get_images($goods_seq_array, $image_size)
	{
		$this->db->where_in('image_type', array($image_size, 'view', 'large'));
		$this->db->where_in('goods_seq', $goods_seq_array);
		$query = $this->db->get('fm_goods_image');
		foreach($query->result_array() as $data_image){
			if($data_image['image_type'] == $image_size)
			{
				if($data_image['cut_number']==1)	$result_image[$data_image['goods_seq']]['image1'] = $data_image['image'];
				if($data_image['cut_number']==2)	$result_image[$data_image['goods_seq']]['image2'] = $data_image['image'];
			}
			if($data_image['image_type']=='view')	$result_image[$data_image['goods_seq']]['image_cnt']++;
			if($data_image['cut_number']==1 && $data_image['image_type']=='large')	$result_image[$data_image['goods_seq']]['image1_large'] = $data_image['image'];
			if($data_image['cut_number']==2 && $data_image['image_type']=='large')	$result_image[$data_image['goods_seq']]['image2_large'] = $data_image['image'];
		}
		return $result_image;
	}
	// input array(goods_seq,goods_seq)
	public function get_goods_category_codes($goods_seq_array)
	{
		$this->load->model('categorymodel');
		$query = "select goods_seq, category_code from fm_category_link  where goods_seq in ('".implode("','",$goods_seq_array)."')";
		$query = $this->db->query($query);
		foreach($query->result_array() as $data){
			foreach($this->categorymodel->split_category($data['category_code']) as $cate) $tmp_category_arr[$data['goods_seq']][] = $cate;
			$result[$data['goods_seq']]['r_category'] = array_values(array_unique($tmp_category_arr[$data['goods_seq']]));
		}
		return $result;
	}
	// input array(goods_seq,goods_seq)
	public function get_goods_brands($goods_seq_array){
		$this->load->model('brandmodel');
		$query = "select category_code, goods_seq  from fm_brand_link where link='1' and goods_seq in ('".implode("','",$goods_seq_array)."')";
		$query = $this->db->query($query);
		foreach($query->result_array() as $data){
				$brand_codear = $this->brandmodel->split_brand($data['category_code']);
				$result[$data['goods_seq']]['r_brand'] = $brand_codear;
		}
		return $result;
	}
	// input array(goods_seq => category_code)
	public function get_goods_categorys($goods_codes)
	{
		foreach($goods_codes as $goods_seq => $category_code){
			if(is_array($category_code)) $category_codes[] = $category_code['category_code'];
			else $category_codes[] = $category_code;
		}
		$category_codes = array_unique($category_codes);
		$query = $this->db->query("select * from fm_category where category_code in ('".implode("','",$category_codes)."')");
		foreach($query->result_array() as $data_category){
			foreach($goods_codes as $goods_seq => $category_code){
				if(is_array($category_code)){
					if($category_code['category_code'] == $data_category['category_code']) $result[$goods_seq] = $data_category;
				}else{
					if($category_code == $data_category['category_code']) $result[$goods_seq] = $data_category;
				}
			}
		}
		return $result;
	}

	// input array(goods_seq,goods_seq)
	public function get_goods_wish($goods_seq_array,$member_seq){
		$bind[] = $member_seq;
		$query = "select if(wish_seq is not null,1,0) as wish, goods_seq  from fm_goods_wish where member_seq=? and goods_seq in ('".implode("','",$goods_seq_array)."')";
		$query = $this->db->query($query, $bind);
		foreach($query->result_array() as $data){
				$result[$data['goods_seq']]['wish'] = $data['wish'];
		}
		return $result;
	}

	// input array(goods_seq,goods_seq), 배송정보
	public function get_goods_shipping_summary($goods_seq_array, $shipping_seq)
	{
		$this->load->model("shippingmodel");
		foreach ($shipping_seq as $key => $val) {
			if ( ! $val) {
				unset($shipping_seq[$key]);
			}
		}
		if ($shipping_seq) {
			$this->db->where_in('shipping_group_seq', $shipping_seq);
			$query = $this->db->get('fm_shipping_group_summary');
			$this->shippingmodel->get_shipping_type_txt();
			foreach($query->result_array() as $shipping){
				$shipping_msg = $this->shippingmodel->default_type_code;
				if	($shipping['default_type'] == 'fixed') {
					$shipping_msg['fixed'] = sprintf($shipping_msg['fixed'],get_currency_price($shipping['first_cost']));
				}
				$shipping['default_type_code'] = $shipping_msg;
				$data[$shipping['shipping_group_seq']] = $shipping;
			}
			foreach($goods_seq_array as $goods_seq => $shipping){
				$ret[$goods_seq]['shipping_group'] = $data[$shipping];
			}
		}
		return $ret;
	}

	public function filter_stats_goods($params)
	{
		$auto_order	= $params['auto_order'];
		$result			= $params['result'];
		foreach($result as $data) if( $data['goods_seq'] ) $goods_seqs[] = $data['goods_seq'];

		if(!$goods_seqs) return $result;

		$query	= "SELECT goods_seq, sum(abs(cnt)) cnt FROM fm_stats_goods WHERE type='[:STAT_TYPE:]' AND stats_date BETWEEN '{$auto_start_date}' AND '{$auto_end_date}' AND goods_seq IN (".implode(',',$goods_seqs).") GROUP BY goods_seq";
		switch($auto_order){
			case "deposit":
			case "best":
				$query	= str_replace('[:STAT_TYPE:]',	'deposit',			$query);
				$filter		= true;
			break;
			case "deposit_price":
				$query	= str_replace('[:STAT_TYPE:]',	'deposit_price',	$query);
				$filter		= true;
			break;
			case "popular":
			case "view":
				$query	= str_replace('[:STAT_TYPE:]',	'view',				$query);
				$filter		= true;
			break;
			case "review":
				$query	= str_replace('[:STAT_TYPE:]',	'review',			$query);
				$filter		= true;
			break;
			case "cart":
				$query	= str_replace('[:STAT_TYPE:]',	'cart',				$query);
				$filter		= true;
			break;
			case "wish":
				$query	= str_replace('[:STAT_TYPE:]',	'wish',				$query);
				$filter		= true;
			break;
		}
		if( $filter	)
		{
			$query = $this->db->query($query);
			foreach($query->result_array() as $data) $result_cnt[$data['goods_seq']] = $data['cnt'];
			foreach($result as $k=>$data)
			{
				$data['gcnt'] = (int) $result_cnt[$data['goods_seq']];
				$result_filter[$data['gcnt']]	[]= $data;
			}
			if($result_filter)
			{
				$num	= 0;
				unset($result);
				rsort($result_filter);
				foreach($result_filter as $k=>$sub_filter) foreach($sub_filter as $k=>$data)
				{
					$data['_no'] = $num;
					$result[$num] = $data;
					$num++;
				}
			}
		}
		return $result;
	}

	/* 사용자화면 상품리스트 */
	public function goods_list($sc)
	{
		// ----- 기본 선언 ---- //
		$sc['page']       = $sc['page'] ?: 1;
		$sc['perpage']    = $sc['perpage'] ?: 10;
		$sc['image_size'] = $sc['image_size'] ?: 'view';

		if ($sc['category_code']) {
			$sc['category']  =  $sc['category_code'];
		}
		if ($sc['brand_code']) {
			$sc['brand']  =  $sc['brand_code'];
		}
		if ($sc['location_code']) {
			$sc['location']  =  $sc['location_code'];
		}

		if ($sc['brand'] && !is_array($sc['brand'])) {
			$sc['brands'] = [ $sc['brand'] ];
		}
		$platform = $this->_is_mobile_agent ? 'M' : 'P';

		// ----- 기본 로드 ---- //
		$this->load->model('membermodel');
		$this->load->model('categorymodel');
		$this->load->library('sale');
		$this->load->library('goodsList');

		//
		$seo_info       = $CI->seo ?: config_load('seo');
		$base_image_alt = $seo_info['image_alt'];

		//image alt replace_code
		$replace = [
			'shop_name'   => '{쇼핑몰명}',
			'goods_name'  => '{상품명}',
			'summary'     => '{간략설명}',
			'brand_title' => '{브랜드명}',
			'category'    => '{카테고리명}',
			'keyword'     => '{검색어}',
		];

		// 회원 등급
		if ($this->userInfo['group_seq'] > 0) {
			$data_member  = $this->membermodel->get_member_data($this->userInfo['member_seq']);
			$member_group = $this->userInfo['group_seq'];
			$sc['member_group_seq'] = $this->userInfo['group_seq'];
			$sc['member_type']      = ($data_member['business_seq'] > 0) ? 'business' : 'default';
		} else {
			$member_group = 0;
			$sc['member_group_seq'] = 0;
			$sc['member_type']      = '';
		}

		//--> sale library 할인 적용 사전값 전달
		$param = [
			'cal_type'   => 'list',
			'member_seq' => $this->userInfo['member_seq'],
			'group_seq'  => $member_group,
		];
		$this->sale->set_init($param);
		$this->sale->preload_set_config('list');
		//<-- //sale library 할인 적용 사전값 전달

		$now_date = date('Y-m-d');

		// ----- 기본 Query ---- //
		$this->db->reset_query();
		$sub_db = (clone $this->db);

		//
		$selectedFields = [
			'g.goods_seq', 'g.sale_seq', 'g.goods_status', 'g.goods_kind', 'g.socialcp_event', 'g.goods_name', 'g.goods_code', 'g.summary', 'g.string_price_use','g.string_price_color',
			'g.string_price', 'g.string_price_link', 'g.string_price_link_url', 'g.member_string_price_use', 'g.member_string_price', 'g.member_string_price_link','g.member_string_price_color',
			'g.member_string_price_link_url', 'g.allmember_string_price_use', 'g.allmember_string_price', 'g.allmember_string_price_link','g.allmember_string_price_color',
			'g.allmember_string_price_link_url', 'g.file_key_w', 'g.file_key_i', 'g.videotmpcode', 'g.videousetotal', 'g.purchase_ea', 'g.shipping_policy',
			'g.review_count', 'g.review_sum', 'g.reserve_policy', 'g.multi_discount_use', 'g.multi_discount_ea', 'g.multi_discount', 'g.multi_discount_unit',
			'g.adult_goods', 'g.keyword', 'g.goods_shipping_policy', 'g.unlimit_shipping_price', 'g.limit_shipping_price', 'g.provider_seq', 'g.shipping_group_seq',
			'g.package_yn', 'g.regist_date', 'g.page_view', 'g.wish_count', 'g.default_discount', 'g.tax',
			'o.consumer_price', 'o.price', 'o.reserve_rate', 'o.reserve_unit', 'o.reserve', 'o.price as default_price',
			'gls.brand_title as brand_title', 'gls.brand_title_eng as brand_title_eng', 'gls.brand_code	as brand_code', 'gls.today_icon	as icons',
			'gls.price_' . date('H') . ' as sale_price', 'gls.today_solo_start', 'gls.today_solo_end',
			'gls.price_00', 'gls.price_01', 'gls.price_02', 'gls.price_03', 'gls.price_04', 'gls.price_05', 'gls.price_06', 'gls.price_07',
			'gls.price_08', 'gls.price_09', 'gls.price_10', 'gls.price_11', 'gls.price_12', 'gls.price_13', 'gls.price_14', 'gls.price_15',
			'gls.price_16', 'gls.price_17', 'gls.price_18', 'gls.price_19', 'gls.price_20', 'gls.price_21', 'gls.price_22', 'gls.price_23',
			'g.display_terms', 'g.display_terms_text', 'g.display_terms_color', 'g.display_terms_begin', 'g.display_terms_end', 'g.color_pick'
		];

		//
		$this->db->from('fm_goods g')
			->join('fm_goods_option o', "o.goods_seq = g.goods_seq AND o.default_option = 'y'", 'straight')
			->join('fm_provider p', "p.provider_seq = g.provider_seq AND p.provider_status = 'Y'", 'straight')
			->join('fm_goods_list_summary gls', "gls.goods_seq = g.goods_seq AND gls.platform = '" . $platform . "'", 'left')
			->where('g.goods_type', 'goods')
			->where('g.provider_status', '1');

		$order_by   = 'g.goods_seq';
		$order_sort = 'desc';

		// ----- 검색조건 추가 ---- //

		// 모바일 요약페이지에서는 큰이미지 사용
		if (!empty($sc['list_style']) && $sc['list_style'] == 'mobile_zoom') {
			$sc['image_size'] = 'large';
		}

		//
		$not_in_goods_seq     = [];
		$not_in_category_code = [];
		$in_goods_seq         = [];
		$in_category_code     = [];

		/* 상품 자동노출일때 */
		if (!empty($sc) && $sc['auto_use'] == 'y') {
			if ($sc['auto_term_type'] == 'relative') {
				$auto_start_date = date('Y-m-d', strtotime("-{$sc['auto_term']} day"));
				$auto_end_date   = date('Y-m-d');
			} else {
				$auto_start_date = $sc['auto_start_date'];
				$auto_end_date   = $sc['auto_end_date'];
			}

			switch ($sc['auto_order']) {
				case 'deposit':
				case 'best':
				case 'deposit_price':
					$this->db->where("`g`.`update_date` BETWEEN '" . $auto_start_date . " 00:00:00' AND '" . $auto_end_date . " 23:59:59'", null, false);
					$order_by = 'g.purchase_ea';
					break;
				case 'popular':
				case 'view':
					$this->db->where("`g`.`update_date` BETWEEN '" . $auto_start_date . " 00:00:00' AND '" . $auto_end_date . " 23:59:59'", null, false);
					$order_by = 'g.page_view';
					break;
				case 'review':
					$this->db->where("`g`.`update_date` BETWEEN '" . $auto_start_date . " 00:00:00' AND '" . $auto_end_date . " 23:59:59'", null, false);
					$order_by = 'g.review_count';
					break;
				case 'cart':
					$this->db->where("`g`.`update_date` BETWEEN '" . $auto_start_date . " 00:00:00' AND '" . $auto_end_date . " 23:59:59'", null, false);
					$order_by = 'g.cart_count';
					break;
				case 'wish':
					$this->db->where("`g`.`update_date` BETWEEN '" . $auto_start_date . " 00:00:00' AND '" . $auto_end_date . " 23:59:59'", null, false);
					$order_by = 'g.wish_count';
					break;
				case 'discount':
					$this->db->where("`g`.`update_date` BETWEEN '" . $auto_start_date . " 00:00:00' AND '" . $auto_end_date . " 23:59:59'", null, false);
					$order_by = 'g.default_discount';
					break;
				default:
				$this->db->where("`g`.`regist_date` BETWEEN '" . $auto_start_date . " 00:00:00' AND '" . $auto_end_date . " 23:59:59'", null, false);
					$order_by = 'g.goods_seq';
					break;
			}

			//이미지영역 동영상여부
			if ($sc['auto_file_key_w']) {
				$this->db->where('g.file_key_w !=', '');
			}
			//이미지영역 동영상 있으면서 노출여부 포함
			if ($sc['auto_file_key_w'] && $sc['auto_video_use_image']) {
				$this->db->where('g.video_use', $sc['auto_video_use_image']);
			}
			//설명영역 동영상여부
			if($sc['auto_videototal']) {
				$this->db->where('g.videototal >', 0);
			}

			if ($sc['selectGoodsView']) {
				$this->db->where('g.goods_view', $sc['selectGoodsView']);
			} else {
				$this->db->group_start()
					->where('g.goods_view', 'look')
					->or_group_start()
						->where('g.display_terms', 'AUTO')
						->where('g.display_terms_begin <=', $now_date)
						->where('g.display_terms_end >=', $now_date)
					->group_end()
					->group_end();
			}

			// 해당 이벤트 상품 추출
			if (!empty($sc['selectEvent'])) {
				$sub_db->select('goods_seq, category_code, choice_type')
					->from('fm_event_choice')
					->where_in('choice_type', ['except_goods','except_category','category','goods'])
					->where('event_seq', $sc['selectEvent']);
				$query = $sub_db->get();
				foreach ($query->result_array() as $event_choice_data) {
					if ($event_choice_data['choice_type'] == 'except_goods' && !in_array($event_choice_data['goods_seq'], $not_in_goods_seq)) {
						$not_in_goods_seq[] = $event_choice_data['goods_seq'];
					}
					if ($event_choice_data['choice_type'] == 'goods' && !in_array($event_choice_data['goods_seq'], $in_goods_seq)) {
						$in_goods_seq[]	= $event_choice_data['goods_seq'];
					}
					if ($event_choice_data['choice_type'] == 'except_category' && !in_array($event_choice_data['category_code'], $not_in_category_code)) {
						$not_in_category_code[]	= $event_choice_data['category_code'];
					}
					if ($event_choice_data['choice_type'] == 'category' && !in_array($event_choice_data['category_code'], $in_category_code)) {
						$in_category_code[]	= $event_choice_data['category_code'];
					}
				}

				//
				$sub_db->select('provider_list')
					->from('fm_event_benefits')
					->where('event_seq', $sc['selectEvent'])
					->limit(1);
				$query = $sub_db->get();
				$event_benefit_data = $query->row_array();

				$in_provider_list = [];
				if ($event_benefit_data) {
					$in_provider_list = array_values(array_filter(explode('|', $event_benefit_data['provider_list'])));
				}

				if ($in_provider_list) {
					$this->db->where_in('g.provider_seq', $in_provider_list);
				} else {
					$this->db->where('g.provider_seq', '1');
				}
			}

			// 사은품과 상품의 1:N 구조로 where절 in query로 변경할 수 있게 수정해야 함.
			if(!empty($sc['selectGift'])){
				$sub_db->select('*')
					->from('fm_gift_choice')
					->where('gift_seq', $sc['selectGift']);
				$query = $sub_db->get();
				foreach ($query->result_array() as $gift_choice_data) {
					if ($gift_choice_data['choice_type'] == 'goods' && !in_array($gift_choice_data['goods_seq'], $in_goods_seq)) {
						$in_goods_seq[] = $gift_choice_data['goods_seq'];
					}
					if ($gift_choice_data['choice_type'] == 'category' && !in_array($gift_choice_data['category_code'], $in_category_code)) {
						$in_category_code[] = $gift_choice_data['category_code'];
					}
				}
			}
		} elseif (!empty($sc['display_seq'])) {
			if (!isset($sc['display_tab_index'])) {
				$sc['display_tab_index'] = 0;
			}
			$selectedFields[] = 'fddti.display_tab_item_seq';
			$this->db->join('fm_design_display_tab_item fddti', "fddti.goods_seq = g.goods_seq AND fddti.display_seq = '" . $sc['display_seq'] . "' AND fddti.display_tab_index = '" . $sc['display_tab_index'] . "'", 'inner')
				->group_start()
					->where('g.goods_view', 'look')
					->or_group_start()
						->where('g.display_terms', 'AUTO')
						->where('g.display_terms_begin <=', $now_date)
						->where('g.display_terms_end >=', $now_date)
					->group_end()
				->group_end();
			$order_by   = 'fddti.display_tab_item_seq';
			$order_sort = 'asc';
		} else {
			$this->db->group_start()
				->where('g.goods_view', 'look')
				->or_group_start()
					->where('g.display_terms', 'AUTO')
					->where('g.display_terms_begin <=', $now_date)
					->where('g.display_terms_end >=', $now_date)
				->group_end()
				->group_end();
		}

		switch ($sc['sort']) {
			case 'popular':
				if (!empty($sc['category'])) {
					if ($sc['m_list_use'] == 'y') {
						$selectedFields[] = 'cl.mobile_sort';
						$this->db->order_by('cl.mobile_sort', 'asc');
						$order_by   = 'g.goods_seq';
						$order_sort = 'desc';
					} else {
						$selectedFields[] = 'cl.sort';
						$this->db->order_by('cl.sort', 'asc');
						$order_by   = 'g.goods_seq';
						$order_sort = 'desc';
					}
				} elseif (!empty($sc['brand'])) {
					if ($sc['m_list_use'] == 'y') {
						$this->db->order_by('bl.mobile_sort', 'asc');
						$order_by   = 'g.goods_seq';
						$order_sort = 'desc';
					} else {
						$this->db->order_by('bl.sort', 'asc');
						$order_by   = 'g.goods_seq';
						$order_sort = 'desc';
					}
				} elseif (!empty($sc['location'])) {
					if ($sc['m_list_use'] == 'y') {
						$this->db->order_by('ll.mobile_sort', 'asc');
						$order_by   = 'g.goods_seq';
						$order_sort = 'desc';
					} else {
						$this->db->order_by('ll.sort', 'asc');
						$order_by   = 'g.goods_seq';
						$order_sort = 'desc';
					}
				} else {
					$order_by   = 'g.page_view';
					$order_sort = 'desc';
				}
				break;
			case 'newly':
				$order_by   = 'g.goods_seq';
				$order_sort = 'desc';
				break;
			case 'popular_sales':
				$order_by   = 'g.purchase_ea';
				$order_sort = 'desc';
				break;
			case 'low_price':
				$order_by   = 'g.default_price';
				$order_sort = 'asc';
				break;
			case 'high_price':
				$order_by   = 'g.default_price';
				$order_sort = 'desc';
				break;
			case 'review':
				$order_by   = 'g.review_count';
				$order_sort = 'desc';
				break;
		}

		if ($sc['goods_status']) {
			if (is_array($sc['goods_status'])) {
				$this->db->where_in('g.goods_status', $sc['goods_status']);
			}
		}

		if (!empty($sc['goods_seq_string'])) {
			$arr_goods_seq_string = explode(',', preg_replace("/[^0-9,]/", "", $sc['goods_seq_string']));
			$in_goods_seq = array_merge($in_goods_seq, $arr_goods_seq_string);
		}

		if (!empty($sc['goods_seq_exclude'])) {
			if (!in_array($sc['goods_seq_exclude'], $not_in_goods_seq)) {
				$not_in_goods_seq[] = $sc['goods_seq_exclude'];
			}
		}

		if (!empty($sc['category'])) {
			if (!in_array($sc['category'], $in_category_code)) {
				$in_category_code[] = $sc['category'];
			}
		}

		if (!empty($sc['color'])) {
			$sub_db->select('1')
				->from('fm_goods_option oc')
				->where('`oc`.`goods_seq` = `g`.`goods_seq`', null, false)
				->where('oc.color !=', '')
				->where("IFNULL(`oc`.`color`, '') = '" . $sc['color'] . "'", null, false);
			$this->db->where('EXISTS (' . $sub_db->get_compiled_select() . ')', null, false);
		}

		if (!empty($sc['brands'])) {
			$selectedFields[] = 'bl.sort bl_sort';
			$selectedFields[] = 'bl.mobile_sort bl_mobile_sort';
			$this->db->join('fm_brand_link bl', 'bl.goods_seq = g.goods_seq', 'inner')
				->where_in('bl.category_code', $sc['brands']);
		}

		//mobilever3 검색 +2017-08-08 웹 브랜드검색에도 사용함 ldb
		if (!empty($sc['categoryar'])) {
			//2017-08-08 브랜드 category 검색 오류 수정(if문으로 in_category_code없을때 sc['categoryar']로) ldb
			if (count($in_category_code) == 0) {
				$in_category_code = $sc['categoryar'];
			} else {
				$in_category_code = array_merge($in_category_code, $sc['categoryar']);
			}
		}

		if (!empty($sc['location'])) {
			$selectedFields[] = 'll.location_link_seq';
			$selectedFields[] = 'll.sort ll_sort';
			$selectedFields[] = 'll.mobile_sort ll_mobile_sort';
			$selectedFields[] = 'll.location_code';
			$this->db->join('fm_location_link ll', 'll.goods_seq = g.goods_seq', 'left')
				->where('ll.location_code', $sc['location']);
		}

		if (!empty($sc['list_goods_status'])) {
			$this->db->where_in('g.goods_status', explode('|', $sc['list_goods_status']));
		}

		if ($sc['start_price']) {
			$sc['start_price'] = (int) preg_replace('/[^0-9]*/', '', $sc['start_price']);
			$this->db->where('g.default_price >=', $sc['start_price']);
		}

		if ($sc['end_price']) {
			$sc['end_price'] = (int) preg_replace('/[^0-9]*/', '', $sc['end_price']);
			$this->db->where('g.default_price <=', $sc['end_price']);
		}

		//비회원이 상품가격검색시 가격대체상품은 검색제외
		if (!$this->userInfo['member_seq'] && ($sc['start_price'] || $sc['end_price'])) {
			$this->db->where('g.string_price_use !=', '1')
				->where('g.member_string_price_use !=', '1')
				->where('g.allmember_string_price_use !=', '1');
		}

		### 입점사
		if ($sc['provider_seq']) {
			$this->db->where('g.provider_seq', $sc['provider_seq']);
		}

		/*
		if (!empty($sc['search_text'])) {
			if (!is_array($sc['search_text'])) {
				$sc['search_text'] = [ $sc['search_text'] ];
			}

			if ((!empty($sc['insearch']) && $sc['insearch'] == 1) && $_GET['old_search_text']) {
				$arr_search_text = explode("\n", $_GET['old_search_text']);

				foreach ($arr_search_text as $search_text) {
					$old_text = [];
					if (trim($search_text) && !in_array($search_text, $sc['search_text'])) {
						$old_text[] = trim($search_text);
					}
				}
			}

			$arr_keyword = [];
			foreach ($sc['search_text'] as $search_text) {
				$search_text = trim(preg_replace('/-/', ' ', $search_text));
				$arr_keyword_tmp = explode(' ', $search_text);
				foreach ($arr_keyword_tmp as $keyword_str) {
					$keyword_str = trim(preg_replace('/[+><\(\)~*\"@]+/', '', $keyword_str));
					if ($keyword_str && mb_strlen($keyword_str) > 1) { //특수기호 검색 치환
						$arr_keyword[] = $keyword_str;
					}
				}
			}

			foreach ($old_text as $old_search_text) {
				$old_search_text = trim(preg_replace('/-/', ' ', $old_search_text));
				$arr_old_keyword_tmp = explode(' ', $old_search_text);
				foreach ($arr_old_keyword_tmp as $old_keyword_str) {
					$old_keyword_str = trim(preg_replace('/[+><\(\)~*\"@]+/', '', $old_keyword_str));
					if ($old_keyword_str && mb_strlen($old_keyword_str) > 1) { //특수기호 검색 치환
						$arr_keyword[] = $old_keyword_str;
					}
				}
			}
			$arr_keyword = array_unique($arr_keyword);

			//
			$bind_keyword = '';
			foreach ($arr_keyword as $keyword) {
				if ($keyword) {
					$bind_keyword .= ' +"' . $this->db->escape_str($keyword) . '"';
				}
			}
			if ($bind_keyword) {
				$this->db->where("MATCH(`g`.`goods_name`, `g`.`keyword`) AGAINST('" . trim($bind_keyword) . "' IN BOOLEAN MODE)", null, false);
			} else {
				pageBack('검색어가 올바르지않습니다.');
			}
		}
		*/

		if (!empty($sc['search_text'])) {

			if (!is_array($sc['search_text'])) {
				$sc['search_text'] = array($sc['search_text']);
			}

			if ((!empty($sc['insearch']) && $sc['insearch']==1) && $_GET['old_search_text']) {
				$arr_search_text = explode("\n",$_GET['old_search_text']);

				foreach ($arr_search_text as $search_text) {
					$old_text = array();
					if (trim($search_text) && !in_array($search_text,$sc['search_text'])) {
						$old_text[] = trim($search_text);
					}
				}
			}

			$arr_keyword = array();
			foreach ($sc['search_text'] as $search_text) {
				$arr_keyword_tmp = explode(' ',$search_text);
				foreach ($arr_keyword_tmp as $keyword_str) {
					if ($keyword_str) {//특수기호 검색 치환
						$arr_keyword[] = preg_replace("/\?/i", "", $keyword_str);
					}
				}

				$arr_keyword = array_unique($arr_keyword);
			}

			$arr_old_keyword = array();

			foreach ($old_text as $old_search_text) {
				$arr_old_keyword_tmp = explode(' ', $old_search_text);
				foreach ($arr_old_keyword_tmp as $old_keyword_str) {
					if ($old_keyword_str) {
						$arr_old_keyword[] = $old_keyword_str;
					}
				}
				$arr_old_keyword = array_unique($arr_old_keyword);
			}

			foreach ($arr_keyword as $k => $str_keyword) {
				$this->db->group_start()
					->like('g.goods_name', $str_keyword, 'both')
					->or_like('g.keyword', $str_keyword, 'both')
					->group_end();
			}

			foreach ($arr_old_keyword as $k1 => $str_old_keyword) {
				$this->db->group_start()
					->like('g.goods_name', $str_old_keyword, 'both')
					->or_like('g.keyword', $str_old_keyword, 'both')
					->group_end();
			}
		}

		if (!empty($sc['relation'])) {
			$selectedFields[] = 'r.relation_seq';
			$this->db->join('fm_goods_relation r', 'r.relation_goods_seq = g.goods_seq', 'inner')
				->where('r.goods_seq', $sc['relation']);
			$order_by   = 'r.relation_seq';
			$order_sort = 'asc';
		}

		if (!empty($sc['relation_seller'])) {
			$selectedFields[] = 'rs.relation_seq';
			$this->db->join('fm_goods_relation_seller rs', 'rs.relation_goods_seq = g.goods_seq', 'inner')
				->where('rs.goods_seq', $sc['relation_seller']);
			$order_by   = 'rs.relation_seq';
			$order_sort = 'asc';
		}

		if (!empty($sc['provider_relation'])) {
			$selectedFields[] = 'r.relation_seq';
			$this->db->join('fm_provider_relation r', 'r.relation_goods_seq = g.goods_seq', 'inner')
				->where('r.provider_seq', $sc['provider_relation']);
			$order_by   = 'r.relation_seq';
			$order_sort = 'asc';
		}

		if (is_array($sc['src_seq']) && count($sc['src_seq']) > 0) {
			$this->db->where_in('g.goods_seq', $sc['src_seq']);
		}

		// 배송그룹 상품 검색 :: 2016-08-31 lwh
		if ($sc['ship_grp_seq']) {
			$this->db->where_in('g.shipping_group_seq', $sc['ship_grp_seq']);
		}

		if (!empty($sc['limit'])) {
			$this->db->limit($sc['limit']);
		}

		//
		if ($in_goods_seq) {
			$this->db->where_in('g.goods_seq', $in_goods_seq);
		}
		if ($not_in_goods_seq) {
			$this->db->where_not_in('g.goods_seq', $not_in_goods_seq);
		}
		if ($in_category_code[0] || $not_in_category_code[0]) {
			if (!in_array('cl.sort', $selectedFields)) {
				$selectedFields[] = 'cl.sort';
			}
			if (!in_array('cl.mobile_sort', $selectedFields)) {
				$selectedFields[] = 'cl.mobile_sort';
			}

			$this->db->join('fm_category_link cl', 'cl.goods_seq = g.goods_seq', 'inner');
			if ($in_category_code) {
				$this->db->where_in('cl.category_code', $in_category_code);
			}
			if ($not_in_category_code) {
				$this->db->where_not_in('cl.category_code', $not_in_category_code);
			}
		}

		//
		$this->db->select($selectedFields)
			->order_by($order_by, $order_sort);

		if (!empty($sc['limit'])) {
			$query = $this->db->get();
			$result['record'] = $query->result_array();

			// 전체 카운트 보정
			$result['page']['totalcount'] = count($result['record']);
		} else {
			$sql = $this->db->get_compiled_select('', false);
			if ($sc['m_code'] == 'all_item') {
				$result = select_page($sc['perpage'], $sc['page'], 10, $sql, [], null, $sc['m_code']);
			} elseif ($sc['sc_top']) {
				$aRecord = [];
				for ($i = 1; $i <= $sc['page']; $i++) {
					$result = select_page($sc['perpage'], $i, 10, $sql, []);
					foreach ($result['record'] as $dataRecord) {
						$aRecord[]	= $dataRecord;
					}
				}
				$result['record'] = $aRecord;
			} else {
				$result = select_page($sc['perpage'], $sc['page'], 10, $sql, []);
			}

			// 전체 카운트 보정
			$result['page']['totalcount'] = $this->db->count_all_results();
		}

		//
		$params_filter = [
			'auto_order' => $sc['auto_order'],
			'result'     => $result['record'],
		];
		$result['record'] = $this->filter_stats_goods($params_filter);

		$cfg_reserve = $this->reserves ?: config_load('reserve');
		if ($result['record']) {
			$goods_seq_array = [];
			foreach ($result['record'] as $k => $data) {
				$goods_seq_array[] = $data['goods_seq'];
				$shipping_group_seq_array[$data['goods_seq']] = $data['shipping_group_seq'];
				$shipping_seq_array[] = $data['shipping_group_seq'];
			}
			if ($goods_seq_array) {
				$result_image          = $this->get_images($goods_seq_array, $sc['image_size']);
				$result_option         = $this->get_colors($goods_seq_array);
				$result_provider       = $this->get_provider_names($goods_seq_array);
				$result_category_code  = $this->get_category_codes($goods_seq_array);
				$result_category_codes = $this->get_goods_category_codes($goods_seq_array);
				$result_brand          = $this->get_goods_brands($goods_seq_array);
				$result_shipping       = $this->get_goods_shipping_summary($shipping_group_seq_array, $shipping_seq_array);
			}

			if (!empty($this->userInfo['member_seq'])) {
				$result_wish = $this->get_goods_wish($goods_seq_array, $this->userInfo['member_seq']);
			}

			foreach($result['record'] as $k => $data){
				$data['image']          = $result_image[$data['goods_seq']]['image1'];
				$data['image2']         = $result_image[$data['goods_seq']]['image2'];
				$data['image_cnt']      = $result_image[$data['goods_seq']]['image_cnt'];
				$data['image1_large']   = $result_image[$data['goods_seq']]['image1_large'];
				$data['image2_large']   = $result_image[$data['goods_seq']]['image2_large'];
				$data['colors']         = $result_option[$data['goods_seq']];
				// 배송사명이 본사일 경우
				if ($result_provider[$data['goods_seq']]['provider_name'] == '본사') {
					$result_provider[$data['goods_seq']]['provider_name'] = getAlert('sy009');    // "본사";
				}
				$data['provider_name']  = $result_provider[$data['goods_seq']]['provider_name'];
				$data['pgroup_name']    = $result_provider[$data['goods_seq']]['pgroup_name'];
				$data['pgroup_icon']    = $result_provider[$data['goods_seq']]['pgroup_icon'];
				$data['category_code']  = $result_category_code[$data['goods_seq']]['category_code'];
				$data['r_category']     = $result_category_codes[$data['goods_seq']]['r_category'];
				$data['r_brand']        = $result_brand[$data['goods_seq']]['r_brand'];
				$data['wish']           = $result_wish[$data['goods_seq']]['wish'];
				$data['shipping_group'] = $result_shipping[$data['goods_seq']]['shipping_group'];
				$goods_category_codes[$data['goods_seq']] = $data['category_code'];
				$data['goods_index']	= $k+1;

				$result['record'][$k] = $data;
			}

			//
			$result_category = $this->get_goods_categorys($goods_category_codes);

			//
			foreach ($result['record'] as $k => $data) {
				$data['goods_shipping_price'] = ($data['goods_shipping_policy'] == 'unlimit') ? 'unlimit_shipping_price' : 'limit_shipping_price';
				$data['category']             = $result_category[$data['goods_seq']]['title'];

				//--> sale library 적용
				$param = [
					'consumer_price' => $data['consumer_price'],
					'price'          => $data['price'],
					'total_price'    => $data['price'],
					'ea'             => 1,
					'category_code'  => $data['r_category'],
					'brand_code'     => $data['r_brand'],
					'goods_seq'      => $data['goods_seq'],
					'goods'          => $data,
				];
				$this->sale->set_init($param);
				$sales = $this->sale->calculate_sale_price('list');

				// GA4연동으로 인해 sale_list 전달
				$data['sale_list']       = $sales['sale_list'];
				$data['sale_price']      = $sales['result_price'];
				$data['org_price']       = $data['consumer_price'] ?: $data['price'];
				$data['sale_per']        = $sales['sale_per'];
				$data['sale_price']      = $sales['result_price'];
				$data['eventEnd']        = $sales['eventEnd'];
				$data['event_text']      = trim($sales['text_list']['event']);
				$data['event_order_ea']  = $sales['event_order_ea'];
				$data['event_order_cnt'] = $sales['event_order_cnt'];
				$data['reserve']         = $this->get_reserve_with_policy($data['reserve_policy'], $sales['result_price'], $cfg_reserve['default_reserve_percent'], $data['reserve_rate'], $data['reserve_unit'], $data['reserve']) + $sales['tot_reserve'];
				$this->sale->reset_init();
				//<-- sale library 적용

				//예약 상품의 경우 문구를 넣어준다 2020-03-31
				$data['goods_name']	= get_goods_pre_name($data, true, true);

				$data['string_price'] = get_string_price($data);
				$data['string_price_use'] = 0;
				if ($data['string_price'] != '') {
					$data['string_price_use'] = 1;
				}

				// 19mark 이미지
				$markingAdultImg = $this->goodslist->checkingMarkingAdultImg($data);
				if ($markingAdultImg) {
					$data['image'] = $data['image2'] = $data['image1_large'] = $data['image2_large'] = $this->goodslist->adultImg;
				}

				// 아이콘에서 .gif 제거 및 이미지 크기 추출
				$data['icons']	= str_replace('.gif', '', $data['icons']);
				if (file_exists(ROOTPATH . $data['image'])) {
					$data['image_size']	= getimagesize(ROOTPATH . $data['image']);
				}
				if (!empty($data['icons']) && !is_array($data['icons'])) {
					$data['icons'] = explode(',', $data['icons']);
				}

				//image alt tag 추가
				$image_alt = '';
				if ($base_image_alt) {
					$image_alt = $base_image_alt;
					$data['shop_name'] = $this->config_basic['shopName'];

					foreach ($replace as $key => $code) {
						$image_alt = str_replace($code, $data[$key], $image_alt);
					}

					$image_alt = htmlspecialchars(strip_tags($image_alt));
				}

				$data['image_alt']     = $image_alt;
				$data['review_divide'] = $data['review_sum'] / $data['review_count'];

				if (is_nan($data['review_divide'])) {
					$data['review_divide'] = (int) $data['review_divide'];
				} elseif (is_infinite($data['review_divide'])) {
					$data['review_divide'] = 0;
				}

				$result['record'][$k] = $data;

				// 이벤트 상품 검색 시 해당 상품 이벤트 event_seq 가져옴
				if (isset($sc['selectEvent'])) {
					// 각 상품마다 걸려있는 이벤트가 달라 event_list 리셋해줌
					$event_list = [];
					foreach ($this->sale->eventSales as $row) {
						$event_list[] = $row['event_seq'];
					}
					// 해당 상품이 속해있는 이벤트리스트에 조회하는 이벤트가 없으면 제외
					if (!(in_array($sc['selectEvent'], $event_list))) {
						unset($result['record'][$k]);
					}
				}

				//이벤트 상품 검색시 0원 또는 마이너스 금액 영향으로 이벤트에서 제외된 경우 처리
				if (!empty($sc['selectEvent']) && empty($data['event_text'])) {
					unset($result['record'][$k]);
				}
			}
		}

		if (!empty($sc['selectEvent'])) {
			$result['record'] = array_merge($result['record']);
		}

		//
		$result['page']['querystring'] = get_args_list();

		//
		return $result;
	}

	public function get_bigdata($params){
		$big_ip_arr			= array();
		$main_condition	= array();

		$isBigdata = false;
		if	(!empty($params['bigdata'])) $isBigdata = true;

		## CASE2 VIEW TABLE은 1,3,6 개월 마다 테이블이 따로 존재함
		if( $isBigdata ){
			$bigdata_month = date("Y-m-d", mktime(0, 0, 0, date("m") - $params['bigdata_month'], date("d"), date("Y")));
			$bigdata_sql = "select
									daily_member_seq
								from
									".$params['bigdata_table']."
								where
									daily_date >= '".$bigdata_month."' and
									daily_goods_seq = '".$params['goods_seq_exclude']."'
									group by daily_member_seq
									order by daily_cnt desc
									limit 100	";
			$big_query	= $this->db->query( $bigdata_sql );
			$big_result	= $big_query->result_array();
			foreach($big_result as $big_ip){
				$big_ip_arr[] = '\''.$big_ip['daily_member_seq'].'\'';
			}
			if	($big_ip_arr){
				$main_condition[]			= 'daily_member_seq in('.implode(',',$big_ip_arr).')';
				$result['big_ip_arr']			= $big_ip_arr;
				$result['main_condition']	= $main_condition;
			}
		}
		return $result;
	}

	// 반응형인 경우 기준이 없을때 해당 함수를 사용 :: 2019-01-23 pjw
	public function get_stat_light($params, $main_condition)
	{
		$isBigdata = false;
		if (! empty($params['bigdata'])) {
			$isBigdata = true;
		}
		if (empty($params['stats_table'])) {
			return false;
		}

		// 기준이 되는 코드 설정
		$join_link_field = 'category';
		if ($params['standard'] == 'location') {
			$join_link_field = 'location';
		}

		// 조건에 맞는 테이블 가져오나, 기본 조건이 없으면 category 에서 가져오도록 수정
		$join_link_table = in_array($params['standard'], ['brand','category','location']) ? $params['standard'] : 'category';

		//
		$this->db->select('fds.daily_goods_seq')
			->from($params['stats_table'] . ' fds')
			->join('fm_' . $join_link_table . '_link fl', "fl.goods_seq = fds.daily_goods_seq AND fl." . $join_link_field . "_code = '" . $params['category_code'] . "'", 'inner');

		## 기준 통계테이블에서 데이터를 가져옴
		if ($params['month']) {
			$act_month = date('Y-m-d', mktime(0, 0, 0, date('m') - $params['month'], date('d'), date('Y')));
			if (! $isBigdata) {
				$act_month = str_replace('-', '', $act_month).'00000000';
			}
			$this->db->where('fds.daily_date >=', $act_month);
		}

		## 통계테이블 기준 정렬값 정의
		switch ($params['act']) {
			case 'order_ea':
				$this->db->select('SUM(`fds`.`daily_ea`) `total_order`');
				break;
			case 'review_sum':
				$this->db->select('((sum(fds.daily_score_5)*5)+(sum(fds.daily_score_4)*4)+(sum(fds.daily_score_3)*3)+(sum(fds.daily_score_2)*2)+(sum(fds.daily_score_1))*1)/sum(fds.daily_cnt) as total_order ');
				break;
			default:
				$this->db->select('SUM(`fds`.`daily_cnt`) `total_order`');
		}

		## 기준 통계테이의 조건
		if ($params['act'] == 'review_sum' && $params['review_cnt'] > 0) {
			$this->db->where('fds.daily_cnt >=', $params['review_cnt']);
		}

		// 나이대
		if ($params['age'] == 'each') {
			$this->db->group_start();
			for ($i = 1; $i < 7; $i++) {
				if ($params['each_age_'. $i . '0']) {
					$this->db->or_where('fds.daily_age_' . $i . '0 >', 0);
				}
			}
			$this->db->group_end();
		} elseif ($params['age'] == 'same' && $params['member_age']) {
			$this->db->where('fds.daily_age_' . $params['member_age'] . '0 >', 0);
		}

		// 성별
		if ($params['sex'] == 'each') {
			$this->db->group_start();
			if ($params['each_sex_male']) {
				$this->db->or_where('fds.daily_sex_male >', 0);
			}
			if ($params['each_sex_female']) {
				$this->db->or_where('fds.daily_sex_female >', 0);
			}
			if ($params['each_sex_none']) {
				$this->db->or_where('fds.daily_sex_none >', 0);
			}
			$this->db->group_end();
		} elseif ($params['sex'] == 'same' && $params['member_sex']) {
			$this->db->where('fds.daily_sex_' . $params['member_sex'] . ' >', 0);
		}

		// 접속환경
		if ($params['agent'] == 'each') {
			$this->db->group_start();
			if ($params['each_agent_pc']) {
				$this->db->or_where('fds.daily_pc >', 0);
			}
			if ($params['each_agent_mobile']) {
				$this->db->or_where('fds.daily_mobile >', 0);
			}
			if ($params['each_agent_none']) {
				$this->db->or_where('fds.daily_none >', 0);
			}
			$this->db->group_end();
		} elseif ($params['agent'] == 'same' && $params['member_agent']) {
			$this->db->where('fds.daily_' . $params['member_agent'] . ' >', 0);
		}

		//
		$query = $this->db->group_by('fds.daily_goods_seq')
			->order_by('`total_order`', 'desc')
			->limit(100)
			->get();

		$result = [];
		foreach ($query->result_array() as $data) {
			$result[$data['daily_goods_seq']] = $data;
		}

		//
		return $result;
	}

	public function get_stat($params, $main_condition){
		$act_sex		= array();
		$act_age		= array();
		$act_agent	= array();

		$isBigdata = false;
		if	(!empty($params['bigdata']))	$isBigdata = true;
		if	(empty($params['stats_table']))	return false;

		## 기준 통계테이블에서 데이터를 가져옴
		if	($params['month']){
			$act_month = date("Y-m-d", mktime(0, 0, 0, date("m") - $params['month'], date("d"), date("Y")));
			if(!$isBigdata) $act_month = str_replace('-','',$act_month).'00000000';
			$main_condition[]	= " daily_date >= '{$act_month}' ";
		}

		## 통계테이블 기준 정렬값 정의
		switch($params['act']){
			case 'order_ea':
				$sqlColOrderBy = ' sum(fdsvr.daily_ea) as total_order ';
				break;
			case 'review_sum':
				$sqlColOrderBy = ' ((sum(fdsvr.daily_score_5)*5)+(sum(fdsvr.daily_score_4)*4)+(sum(fdsvr.daily_score_3)*3)+(sum(fdsvr.daily_score_2)*2)+(sum(fdsvr.daily_score_1))*1)/sum(fdsvr.daily_cnt) as total_order ';
				break;
			default :
				$sqlColOrderBy = ' sum(fdsvr.daily_cnt) as total_order ';
		}

		## 기준 통계테이의 조건
		if	($params['act'] == 'review_sum' && $params['review_cnt'] > 0) $act_condition[]	= " daily_cnt >= '".$params['review_cnt']."' ";
		if	($params['age'] == 'each'){
			for($i=1; $i<7; $i++){
				if($params['each_age_'.$i.'0'])
					$act_age[]	= ' daily_age_'.$i.'0 > 0 ';
			}
			$act_condition[]	= ' ('.implode($act_age,'or').') ';
		}else if($params['age'] == 'same' && $params['member_age']){
			$act_condition[]	= " daily_age_{$params['member_age']} > 0 ";
		}

		if	($params['sex'] == 'each'){
			if	($params['each_sex_male'])
				$act_sex[]		= ' daily_sex_male > 0 ';
			if	($params['each_sex_female'])
				$act_sex[]		= ' daily_sex_female > 0 ';
			if	($params['each_sex_none'])
				$act_sex[]		= ' daily_sex_none > 0 ';
			$act_condition[]	= ' ('.implode($act_sex,'or').') ';
		}else if($params['sex'] == 'same' && $params['member_sex']){
			$act_condition[]	= " daily_sex_{$params['member_sex']} > 0 ";
		}

		if	($params['agent'] == 'each'){
			if	($params['each_agent_pc'])
				$act_agent[]	= ' daily_pc > 0 ';
			if	($params['each_agent_mobile'])
				$act_agent[]	= ' daily_mobile > 0 ';
			if	($params['each_agent_none'])
				$act_agent[]	= ' daily_none > 0 ';
			$act_condition[]	= ' ('.implode($act_agent,'or').') ';
		}else if($params['agent'] == 'same' && $params['member_agent']){
			$act_condition[]	= " daily_{$params['member_agent']} > 0 ";
		}

		$main_condition	= implode(' and ',$main_condition);
		$act_condition		= implode(' and ',$act_condition);

		if	($act_condition) $act_condition = ' where ' . $act_condition;

		$query = "
		SELECT
		fdsvr.daily_goods_seq, [:ACT_ORDERBY:]
		FROM (
				SELECT daily_seq, count( * ) cnt
				FROM ".$params['stats_table']."
				WHERE [:ACT_MAIN_CONDITION:]
				GROUP BY daily_seq
			) rfdsvr
			INNER JOIN ".$params['stats_table']." fdsvr ON rfdsvr.daily_seq = fdsvr.daily_seq
			[:ACT_CONDITION:]
		GROUP BY daily_goods_seq
		ORDER BY  `total_order` DESC LIMIT 100";
		$query = str_replace('[:ACT_MAIN_CONDITION:]',	$main_condition,	$query);
		$query = str_replace('[:ACT_CONDITION:]',			$act_condition,	$query);
		$query = str_replace('[:ACT_ORDERBY:]',				$sqlColOrderBy,	$query);

		$query = $this->db->query($query);
		foreach($query->result_array() as $data) $result[$data['daily_goods_seq']] = $data;
		return $result;
	}

	/* 사용자화면 자동검색 전용 상품리스트 */
	public function auto_condition_goods_list($sc)
	{
		// ----- 기본 선언 ---- //
		$data = array();
		$platform = ($this->mobileMode || $this->_is_mobile_agent) ? 'M' : 'P';

		// ----- 기본 로드 ---- //
		$this->load->model('membermodel');
		$this->load->model('categorymodel');
		$this->load->library('sale');
		$this->load->library('goodsList');
		$cfg_reserve = $this->reserves ?: config_load('reserve');

		// 회원 등급
		$member_group = '0';
		if ($this->userInfo['group_seq'] > 0) {
			$member_group = $this->userInfo['group_seq'];
		}

		//--> sale library 할인 적용 사전값 전달
		$param = [
			'cal_type'   => 'list',
			'member_seq' => $this->userInfo['member_seq'],
			'group_seq'  => $member_group,
		];
		$this->sale->set_init($param);
		$this->sale->preload_set_config('list');
		//<-- //sale library 할인 적용 사전값 전달

		$selectedFields = [
			'g.goods_seq', 'g.sale_seq', 'g.goods_status', 'g.goods_kind', 'g.socialcp_event', 'g.goods_name', 'g.goods_code', 'g.summary',
			'g.string_price_use', 'g.string_price', 'g.string_price_color', 'g.string_price_link', 'g.string_price_link_url', 'g.member_string_price_use', 'g.member_string_price', 'g.member_string_price_color',
			'g.member_string_price_link', 'g.member_string_price_link_url', 'g.allmember_string_price_use', 'g.allmember_string_price', 'g.allmember_string_price_color',
			'g.allmember_string_price_link', 'g.allmember_string_price_link_url', 'g.file_key_w', 'g.file_key_i', 'g.videotmpcode', 'g.videousetotal',
			'g.purchase_ea', 'g.shipping_policy', 'g.review_count', 'g.review_sum', 'g.reserve_policy', 'g.multi_discount_use', 'g.multi_discount_ea',
			'g.multi_discount', 'g.multi_discount_unit', 'g.adult_goods', 'g.goods_shipping_policy', 'g.unlimit_shipping_price',
			'g.limit_shipping_price', 'g.shipping_group_seq', 'g.package_yn', 'g.regist_date', 'g.page_view', 'g.wish_count',
			'o.consumer_price', 'o.price', 'o.reserve_rate', 'o.reserve_unit', 'o.reserve', 'g.provider_seq',
			'(select provider_name from fm_provider where provider_seq = g.provider_seq) as provider_name',
			'gls.brand_title as brand_title', 'gls.brand_title_eng as brand_title_eng', 'gls.brand_code as brand_code', 'gls.today_icon as icons',
			'gls.price_' . date('H') . ' as sale_price', 'gls.today_solo_start', 'gls.today_solo_end',
			'g.display_terms', 'g.display_terms_text', 'g.display_terms_color', 'g.color_pick',
		];

		foreach ($sc as $key => $params) {
			$dbGoods = (clone $this->db)->reset_query();

			//
			$dbGoods->select($selectedFields)
				->from('fm_goods g')
				->join('fm_goods_option o', "o.goods_seq = g.goods_seq AND o.default_option = 'y'", 'inner')
				->join('fm_goods_list_summary gls', "gls.goods_seq = g.goods_seq AND gls.platform = '" . $platform . "'", 'left')
				->where('g.goods_type', 'goods')
				->where('g.goods_view', 'look')
				->where('g.goods_status', 'normal');

			if ($params['bigdata']) {
				if ($key == 'admin' || !$params['goods_seq_exclude']) {
					continue;
				}
				if (empty($params['same_category'])) {
					$params['same_category'] = 1;
				}
			}

			// none 일 경우 따로 goods_seq 리스트를 만들지 않음
			if ($params['act'] != 'recently') {
				$result_bigdata	= $this->get_bigdata($params);
				$main_condition	= $result_bigdata['main_condition'];

				if ($key != 'none') {
					$result_stat	= $this->get_stat($params, $main_condition);
				} else {
					// none 조건용 상품 고유번호 조회 쿼리 신규 추가 :: 2019-01-23 pjw
					$result_stat	= $this->get_stat_light($params, $main_condition);
				}

				//
				$stat_goods_seqs = [];
				foreach ($result_stat as $goods_seq => $data_stat) {
					if ($goods_seq) {
						$stat_goods_seqs[] = $goods_seq;
					}
				}

				if ($stat_goods_seqs) {
					$dbGoods->where_in('g.goods_seq', $stat_goods_seqs);
				}
			} else {
				## 최근 등록순
				## 베스트 상품후기의 경우 누적기간이므로 기간없이 처리
				if($params['act'] != 'review_sum'){
					$act_month = date('Y-m-d', mktime(0, 0, 0, date('m') - $params['month'], date('d'), date('Y'))) . ' 00:00:00';
					$dbGoods->where('g.regist_date >=', $act_month);
				}
			}

			## 행위에 따른 기준 값 정의
			$act_goods_seq = ($key == 'admin' || (!empty($params['bigdata'])) || !empty($params['bigdata_test'])) ? $params['goods_seq_exclude'] : $this->get_act_condition($key,$params);

			$params['goods_seq_exclude'] = $act_goods_seq;

			## 로그인이 필요한 페이지에서 로그인이 안되어 있는 경우 블락
			if ($act_goods_seq == 'login_no') {
				continue;
			}

			## none 조건에 카테고리, 브랜드, 지역 기준인 경우엔 fm_{분류}_link 테이블 조인을 하지않는다 (데이터가 많을경우 대비)
			$is_none = $act_goods_seq == 'disallow_seq' ? true : false;

			## 기본 리스팅 조건 설정
			if (serviceLimit('H_AD')) {
				$dbGoods->where('g.provider_status', '1');
			}

			############# 상품기준 동일한 카테고리,브랜드,지역 ###############
			if (! empty($params['same_category'])) {
				$dbGoods->join('`fm_category_link` `l` USE INDEX (`ix_goods_seq_category_code`)', 'l.goods_seq = g.goods_seq', 'inner');

				// none 인경우 해당 항목 자체가 기준이 됨
				if ($is_none) {
					$dbGoods->where('l.category_code', $params['category_code']);
				} else {
					$dbGoods->where("l.category_code = (select category_code from fm_category_link where goods_seq = '" . $act_goods_seq ."' and link = 1 limit 1)", null, false);
				}
			}

			if (! empty($params['same_brand'])) {
				$dbGoods->join('`fm_brand_link` `bl` USE INDEX (`ix_goods_seq_category_code`)', 'bl.goods_seq = g.goods_seq', 'inner');

				// none 인경우 해당 항목 자체가 기준이 됨
				if ($is_none) {
					$dbGoods->where('bl.category_code', $params['category_code']);
				} else {
					$dbGoods->where("bl.category_code = (select category_code from fm_brand_link where goods_seq = '" . $act_goods_seq ."' and link = 1 limit 1)", null, false);
				}
			}

			if (! empty($params['same_location'])) {
				$dbGoods->join('`fm_location_link` `ll` USE INDEX (`ix_goods_seq_location_code`)', 'll.goods_seq = g.goods_seq', 'inner');

				// none 인경우 해당 항목 자체가 기준이 됨
				if ($is_none) {
					$dbGoods->where('ll.location_code', $params['category_code']);
				} else {
					$dbGoods->where("ll.location_code = (select location_code from fm_location_link where goods_seq = '" . $act_goods_seq ."' and link = 1 limit 1)", null, false);
				}
			}

			if	(! empty($params['goods_seq_exclude']) && ! $is_none) {
				$dbGoods->where('g.goods_seq !=', $params['goods_seq_exclude']);
			}

			################# 관리자지정 카테고리,브랜드,지역 #################
			if (! empty($params['category'])) {
				$dbGoods->join('`fm_category_link` `l` USE INDEX (`ix_goods_seq_category_code`)', 'l.goods_seq = g.goods_seq', 'inner');
				$dbGoods->where('l.category_code', $params['category']);
			}

			if (! empty($params['brand'])) {
				$dbGoods->join('`fm_brand_link` `bl` USE INDEX (`ix_goods_seq_category_code`)', 'bl.goods_seq = g.goods_seq AND bl.link = 1', 'inner');
				$dbGoods->where('bl.category_code', $params['brand']);
			}

			if	(! empty($params['location'])) {
				$dbGoods->join('`fm_location_link` `ll` USE INDEX (`ix_goods_seq_location_code`)', 'll.goods_seq = g.goods_seq', 'inner');
				$dbGoods->where('ll.location_code', $params['location']);
			}

			############################# 입점사 ###############################
			if (! empty($params['same_seller'])) {
				$dbGoods->where("g.provider_seq = (select provider_seq from fm_goods where goods_seq = '" . $act_goods_seq ."' limit 1)", null, false);
			}
			if ($params['provider'] == 1) {
				$dbGoods->where('g.provider_seq', 1);
			}

			if ($params['provider_seq']) {
				$dbGoods->where('g.provider_seq', $params['provider_seq']);
			}

			if (! empty($params['limit'])) {
				$dbGoods->limit($params['limit']);
			}

			//
			$sqlOrderbyClause = 'g.goods_seq desc';//total_order desc,

			if ($params['act'] != 'recently' && $stat_goods_seqs) {
				$sqlOrderbyClause = "field(g.goods_seq, " . implode(',', $stat_goods_seqs) . ")";//total_order desc,
			}

			switch ($params['sort']) {
				case 'recently':
					$sqlOrderbyClause = 'g.goods_seq desc';
					break;
				case 'popular':
					if (! empty($params['category'])) {
						$sqlOrderbyClause = 'l.sort asc';
					} elseif (! empty($params['brand'])) {
						$sqlOrderbyClause = 'bl.sort asc';
					} else {
						$sqlOrderbyClause = 'g.page_view desc';
					}
					break;
				case 'newly':
					$sqlOrderbyClause = 'g.goods_seq desc';
					break;
				case 'popular_sales':
					$sqlOrderbyClause = 'g.purchase_ea desc';
					break;
				case 'low_price':
					$sqlOrderbyClause = 'g.default_price asc';
					break;
				case 'high_price':
					$sqlOrderbyClause = 'g.default_price desc';
					break;
				case 'review':
					$sqlOrderbyClause = 'order by g.review_count desc';
					break;
			}

			$dbGoods->order_by($sqlOrderbyClause);

			//
			$sql = $dbGoods->get_compiled_select();

			//
			if (! empty($params['limit'])) {
				$query = $this->db->query($sql);
				$result['record'] = $query->result_array();
				$record_cnt = $query->num_rows();
			} else {
				if ($params['m_code'] == 'all_item') {
					$result = select_page($params['perpage'], $params['page'], 10, $sql, [], null, $params['m_code']);
				} else {
					$result = select_page($params['perpage'], $params['page'], 10, $sql, []);
				}
				$record_cnt = sizeof($result['record']);
			}

			//
			$goods_seq_array = [];
			foreach($result['record'] as $data) {
				$goods_seq_array[] = $data['goods_seq'];
				$shipping_group_seq_array[$data['goods_seq']] = $data['shipping_group_seq'];
				$shipping_seq_array[] = $data['shipping_group_seq'];
			}
			if ($goods_seq_array) {
				$result_image         = $this->get_images($goods_seq_array, $params['image_size']);
				$result_option        = $this->get_colors($goods_seq_array);
				$result_provider      = $this->get_provider_names($goods_seq_array);
				$result_category_code = $this->get_category_codes($goods_seq_array);
				$result_brand         = $this->get_goods_brands($goods_seq_array);
				$result_shipping      = $this->get_goods_shipping_summary($shipping_group_seq_array, $shipping_seq_array);
			}

			if (! empty($this->userInfo['member_seq']))	{
				$result_wish = $this->get_goods_wish($goods_seq_array,$this->userInfo['member_seq']);
			}

			foreach ($result['record'] as $k => &$data) {
                $goods_seq = $data['goods_seq'];
				$data['image']          = $result_image[$goods_seq]['image1'];
				$data['image2']         = $result_image[$goods_seq]['image2'];
				$data['image_cnt']      = $result_image[$goods_seq]['image_cnt'];
				$data['image1_large']   = $result_image[$goods_seq]['image1_large'];
				$data['image2_large']   = $result_image[$goods_seq]['image2_large'];
				$data['colors']         = $result_option[$goods_seq];
				$data['provider_name']  = $result_provider[$goods_seq]['provider_name'];
				$data['pgroup_name']    = $result_provider[$goods_seq]['pgroup_name'];
				$data['pgroup_icon']    = $result_provider[$goods_seq]['pgroup_icon'];
				$data['category_code']  = $result_category_code[$goods_seq]['category_code'];
				$data['r_category']     = $result_category_code[$goods_seq]['r_category'];
				$data['r_brand']        = $result_brand[$goods_seq]['r_brand'];
				$data['wish']           = $result_wish[$goods_seq]['wish'];
				$data['shipping_group'] = $result_shipping[$goods_seq]['shipping_group'];
				$data['goods_index']    = $k + 1;

				if ($result_stat[$goods_seq]) {
					foreach ($result_stat[$goods_seq] as  $field_stat => $data_stat) {
						$data[$field_stat] = $data_stat;
					}
				}
				$goods_category_codes[$goods_seq] = $data['category_code'];
			}

			/*
				최고 수량 만족하지 못할 시 다음 조건 태우기 위해 continue
				만약 조건 만족하면 바로 foreach 종료를 위한 break !!!!!
			*/
			if ($record_cnt < $params['min_ea'] && ! $isBigdata) {
				$result['record'] = [];
				continue;
			}
			break;
		}
		$result_category = $this->get_goods_categorys($goods_category_codes);
		$result['display_title'] = $params['display_title'];

		if ($result['record']) {
			$this->load->model('categorymodel');

			$seo_info       = $CI->seo ?: config_load('seo');
			$base_image_alt = $seo_info['image_alt'];

			//image alt replace_code
			$replace['shop_name']		= '{쇼핑몰명}';
			$replace['goods_name']		= '{상품명}';
			$replace['summary']			= '{간략설명}';
			$replace['brand_title']		= '{브랜드명}';
			$replace['category']		= '{카테고리명}';
			$replace['keyword']			= '{검색어}';

			foreach($result['record'] as $k => &$data){

				$data['goods_shipping_price']	= ($data['goods_shipping_policy'] == 'unlimit') ? 'unlimit_shipping_price' : 'limit_shipping_price';
				$data['category']	= $result_category[$data['goods_seq']]['title'];

				//--> sale library 적용
				$param = [
					'consumer_price' => $data['consumer_price'],
					'price'          => $data['price'],
					'price_2'        => $data['price_2'],
					'total_price'    => $data['price'],
					'ea'             => 1,
					'category_code'  => $data['r_category'],
					'brand_code'     => $data['r_brand'],
					'goods_seq'      => $data['goods_seq'],
					'goods'          => $data,
				];
				$this->sale->set_init($param);
				$sales	= $this->sale->calculate_sale_price('list');

				$data['sale_price']     = $sales['result_price'];
				$data['org_price']      = $data['consumer_price'] ?: $data['price'];
				$data['sale_per']       = $sales['sale_per'];
				$data['sale_price']     = $sales['result_price'];
				$data['eventEnd']       = $sales['eventEnd'];
				$data['event_text']     = trim($sales['text_list']['event']);
				$data['event_order_ea'] = $sales['event_order_ea'];
				$data['reserve']        = $this->get_reserve_with_policy($data['reserve_policy'], $sales['result_price'], $cfg_reserve['default_reserve_percent'], $data['reserve_rate'], $data['reserve_unit'], $data['reserve']) + $sales['tot_reserve'];
				$this->sale->reset_init();
				//<-- sale library 적용

				$data['string_price']     = get_string_price($data);
				$data['string_price_use'] = 0;
				if ($data['string_price'] != '') {
					$data['string_price_use'] = 1;
				}

				// 아이콘에서 .gif 제거 및 이미지 크기 추출
				$data['icons'] = str_replace('.gif', '', $data['icons']);
				if (file_exists(ROOTPATH.$data['image'])) {
					$data['image_size'] = getimagesize(ROOTPATH.$data['image']);
				}
				if (! empty($data['icons']) && !is_array($data['icons'])) {
					$data['icons'] = explode(',', $data['icons']);
				}

				// 19mark 이미지
				$markingAdultImg = $this->goodslist->checkingMarkingAdultImg($data);
				if ($markingAdultImg) {
					$data['image'] = $data['image2'] = $data['image1_large'] = $data['image2_large'] = $this->goodslist->adultImg;
				}

				//image alt tag 추가
				$image_alt = '';
				if ($base_image_alt) {
					$image_alt = $base_image_alt;
					$data['shop_name'] = $this->config_basic['shopName'];
					foreach ($replace as $key => $code) {
						$image_alt	= str_replace($code, $data[$key], $image_alt);
					}
					$image_alt = htmlspecialchars(strip_tags($image_alt));
				}

				//이벤트 상품 검색시 0원 또는 마이너스 금액 영향으로 이벤트에서 제외된 경우 처리
				if (! empty($sc['selectEvent']) && empty($data['event_text'])) {
					unset($data);
				}

				$data['review_divide']	= $data['review_sum'] / $data['review_count'];

				if (is_nan($data['review_divide'])) {
					$data['review_divide'] = (int) $data['review_divide'];
				}

			}
		}

		if (!empty($sc['selectEvent'])) {
			$result['record'] = array_merge($result['record']);
		}

		$result['page']['querystring'] = get_args_list();
		return $result;
	}

	public function get_act_condition($act,$params){
		$ret = '';

		//로그인 필수 행위들
		if(in_array($act,array('cart','wish','restock','order')) && !$this->userInfo['member_seq'] || ($act == 'fblike' && !$this->session->userdata('fbuser')))
			return $ret = 'login_no';

		if	($act == 'view'){
			$today_view = $_COOKIE['today_view'];
			if( $today_view ) {
				$today_view = unserialize($today_view, ['allowed_classes' => false]);
				$ret = end($today_view);
			}
		}else if ($act == 'cart'){
			$this->load->model('cartmodel');
			$ret = $this->cartmodel->get_recent_cart($this->userInfo['member_seq']);
		}else if ($act == 'wish'){
			$this->load->model('wishmodel');
			$ret = $this->wishmodel->get_recent_wish($this->userInfo['member_seq']);
		}else if ($act == 'fblike'){
			$this->load->model('goodsfblike');
			$ret = $this->goodsfblike->get_recent_fblike($this->session->userdata('fbuser'));
		}else if ($act == 'restock'){
			$query = "select goods_seq from fm_goods_restock_notify where member_seq = ? order by 1 desc";
			$query = $this->db->query($query,array($this->userInfo['member_seq']));
			$row = $query->result_array();
			$ret = $row[0]['goods_seq'];
		}else if ($act == 'search'){
			$ret = unserialize($_COOKIE['today_search_top'], ['allowed_classes' => false]);
		}else if ($act == 'order'){
			$this->load->model('ordermodel');
			$ret = $this->ordermodel->get_recent_order($this->userInfo['member_seq']);

		}else if($act == 'none'){	// 반응형에서만 쓰이는 조건이므로 함수자체를 따로 분기 처리 하지않음
			// 각 페이지별 기준 재정의
			$standard = $params['standard'];
			switch($standard){
				case "relation":
				case "relation_seller":
					$ret = $params['goods_seq_exclude'];
					break;
				case "mshop":
					// 추후 개발
					break;
				case "category":
				case "brand":
				case "location":
					// 해당 조건들은 상품전체를 가져오기는 무리이므로 호출하는쪽에서 바로 조인
					$ret = 'disallow_seq';
					break;

				default:
				    $ret = 'disallow_seq';
					break;
			}
		}

		return $ret;
	}

	/* 상품 옵션 재고 */
	public function get_goods_option_stock($goods_seq,$option1,$option2,$option3,$option4,$option5,$mode=1){
		$where_val[] = $goods_seq;
		$where[] = "o.goods_seq=?";
		if($option1!=''){
			$where[] = "o.option1=?";
			$where_val[] = $option1;
		}
		if($option2!=''){
			$where[] = "o.option2=?";
			$where_val[] = $option2;
		}
		if($option3!=''){
			$where[] = "o.option3=?";
			$where_val[] = $option3;
		}
		if($option4!=''){
			$where[] = "o.option4=?";
			$where_val[] = $option4;
		}
		if($option5!=''){
			$where[] = "o.option5=?";
			$where_val[] = $option5;
		}

		if($option1=='' && $option2=='' && $option3=='' && $option4=='' && $option5==''){
			$where[] = "o.option1=''";
			$where[] = "o.option2=''";
			$where[] = "o.option3=''";
			$where[] = "o.option4=''";
			$where[] = "o.option5=''";
		}

		$where_str = " and ". implode(' and ',$where);

		$query = "select o.*,s.stock from fm_goods_option o,fm_goods_supply s where o.option_seq=s.option_seq ".$where_str;
		$query = $this->db->query($query,$where_val);
		$tot = '미매칭';
		foreach($query->result_array() as $data){
			// 패키지 상품의 재고 가져오기(주문당 개수 계산) 2018-04-16
			$data_min = '';
			if($data['package_option_seq1']){
				for($i=1;$i<6;$i++){
					if($data['package_option_seq'.$i]){
						$data_package = $this->get_package_stock($data['package_option_seq'.$i],$data['package_unit_ea'.$i]);
						$data_package['unit_ablestock'] = (int) $data_package['unit_stock'] - $data_package['unit_badstock'] - (int) $data_package['unit_'.$this->reservation_field];

						if(!$data_min || $data_min['unit_stock'] > $data_package['unit_stock']){
							$data_min = $data_package;
						}
					}
				}
				if($data_min){
					$data['rstock'] = (int) $data_min['unit_ablestock'];
					$data['badstock'] = (int) $data_min['unit_badstock'];
					$data['stock'] = (int) $data_min['unit_stock'];
					$data['reservation15'] = (int) $data_min['unit_reservation15'];
					$data['reservation25'] = (int) $data_min['unit_reservation25'];
					$data['safe_stock'] = (int) $data_min['package_safe_stock'];
				}
			}
			// 패키지 상품의 재고 가져오기(주문당 개수 계산) 2018-04-16
			$tot += (int) $data['stock'];
			$result[] = $data;
		}
		if($mode == 1){
			#  재고가 null 이면 재고 미매칭 @2016-03-18 pjm
			if($tot === '미매칭'){
				$tot = '미매칭';
			}else{
				$tot = (int) $tot;
			}

			return $tot;
		}else{
			return array($tot,$result);
		}
	}

	/* 상품 옵션 가격 */
	public function get_goods_option_price($goods_seq,$option1,$option2,$option3,$option4,$option5){
		$where_val[] = $goods_seq;
		$where[] = "goods_seq=?";
		if($option1!=''){
			$where[] = "option1=?";
			$where_val[] = $option1;
		}
		if($option2!=''){
			$where[] = "option2=?";
			$where_val[] = $option2;
		}
		if($option3!=''){
			$where[] = "option3=?";
			$where_val[] = $option3;
		}
		if($option4!=''){
			$where[] = "option4=?";
			$where_val[] = $option4;
		}
		if($option5!=''){
			$where[] = "option5=?";
			$where_val[] = $option5;
		}

		if($option1=='' && $option2=='' && $option3=='' && $option4=='' && $option5==''){
			$where[] = "option1=''";
			$where[] = "option2=''";
			$where[] = "option3=''";
			$where[] = "option4=''";
			$where[] = "option5=''";
		}

		$where_str = implode(' and ',$where);

		$query = "select price,reserve from fm_goods_option where ".$where_str." limit 1";
		$query = $this->db->query($query,$where_val);
		$data = $query->result_array();
		return array($data[0]['price'],$data[0]['reserve']);
	}

	/* 상품 옵션 코드 */
	public function get_goods_option_code($goods_seq,$option1,$option2,$option3,$option4,$option5){
		$where_val[] = $goods_seq;
		$where[] = "goods_seq=?";
		if($option1!=''){
			$where[] = "option1=?";
			$where_val[] = $option1;
		}
		if($option2!=''){
			$where[] = "option2=?";
			$where_val[] = $option2;
		}
		if($option3!=''){
			$where[] = "option3=?";
			$where_val[] = $option3;
		}
		if($option4!=''){
			$where[] = "option4=?";
			$where_val[] = $option4;
		}
		if($option5!=''){
			$where[] = "option5=?";
			$where_val[] = $option5;
		}

		if($option1=='' && $option2=='' && $option3=='' && $option4=='' && $option5==''){
			$where[] = "option1=''";
			$where[] = "option2=''";
			$where[] = "option3=''";
			$where[] = "option4=''";
			$where[] = "option5=''";
		}

		$where_str = implode(' and ',$where);
		$query = "select * from fm_goods_option where ".$where_str." limit 1";
		$query = $this->db->query($query,$where_val);
		$data = $query->result_array();

		$optioninfo = array($data[0]['optioncode1'],$data[0]['optioncode2'],$data[0]['optioncode3'],$data[0]['optioncode4'],$data[0]['optioncode5'],$data[0]['color'],$data[0]['zipcode'],$data[0]['address_type'],$data[0]['address'],$data[0]['address_street'],$data[0]['addressdetail'],$data[0]['biztel'],$data[0]['coupon_input'],$data[0]['codedate'],$data[0]['sdayinput'],$data[0]['fdayinput'],$data[0]['dayauto_type'],$data[0]['sdayauto'],$data[0]['fdayauto'],$data[0]['dayauto_day'],$data[0]['newtype'],$data[0]['address_commission']);
		return $optioninfo;
	}


	/* 상품 서브옵션 재고 */
	public function get_goods_suboption_stock($goods_seq,$title,$suboption){
		$query = "select o.*,s.stock from fm_goods_suboption o,fm_goods_supply s where o.goods_seq=? and o.suboption_seq=s.suboption_seq and o.suboption_title=? and o.suboption=? limit 1";
		$query = $this->db->query($query,array($goods_seq,$title,$suboption));
		$result = $query->result_array();
		$data = $result[0];

		// 패키지 상품의 재고 가져오기
		$data_min = '';
		if($data['package_option_seq1']){
			$data_package = $this->get_package_by_option_seq($data['package_option_seq1']);

			// 패키지 상품 주문당 구매개수로 계산 2018-04-16
			$data['stock'] = $data_package['package_stock'] / $data['package_unit_ea1'];
		}

		#  재고가 null 이면 재고 미매칭 @2016-03-18 pjm
		if(is_null($data['stock'])){
			$stock = "미매칭";
		}else{
			$stock = (int) $data['stock'];
		}

		return $stock;
	}

	/* 상품 서브옵션 코드 */
	public function get_goods_suboption_code($goods_seq,$title,$suboption){
		$query = "select * from fm_goods_suboption  where goods_seq=? and suboption_title=? and suboption=? limit 1";
		$query = $this->db->query($query,array($goods_seq,$title,$suboption));
		$data = $query->result_array();

		$suboptioninfo = array($data[0]['suboption_code'],$data[0]['color'],$data[0]['zipcode'],$data[0]['address_type'],$data[0]['address'],$data[0]['address_street'],$data[0]['addressdetail'],$data[0]['biztel'],$data[0]['coupon_input'],$data[0]['codedate'],$data[0]['sdayinput'],$data[0]['fdayinput'],$data[0]['dayauto_type'],$data[0]['sdayauto'],$data[0]['fdayauto'],$data[0]['dayauto_day'],$data[0]['newtype']);
		return $suboptioninfo;
	}

	/* 상품 리스트 */
	public function admin_goods_list($sc) {

		$CI =& get_instance();

		$this->load->model("providermodel");
		$provider_tmp	= $this->providermodel->provider_goods_list();
		$provider_list	= array();
		foreach((array)$provider_tmp as $val){
			$provider_list[$val['provider_seq']]	= $val;
		}
		$provider_name = getAlert("sy009");

		if(!isset($_GET['page']))$_GET['page'] = 1;

		$addInnerJoin	= '';

		if(!$sc['event_seq']) $sc['event_seq'] = $_GET['event_seq'];
		if(!$sc['gift_seq']) $sc['gift_seq'] = $_GET['gift_seq'];
		if(!$sc['search_type']) $sc['search_type'] = $_GET['search_type'];
		if(!$sc['search_type_text']) $sc['search_type_text'] = $_GET['search_type_text'];

		if ($sc['layaway_product'] == 'Y') {
			$today				= date('Y-m-d');
			$where[]			= "C.display_terms = 'AUTO'";
			$where[]			= "C.display_terms_type = 'LAYAWAY'";
			$where[]			= "C.display_terms_begin <= '{$today}'";
			$where[]			= "C.display_terms_end >= '{$today}'";
		}

		$runout_policy			= array();
		if ($_GET['sale_for_stock'] == 'stock')
			$runout_policy[]	= 'stock';

		if ($_GET['sale_for_ableStock'] == 'ableStock')
			$runout_policy[]	= 'ableStock';

		if ($_GET['sale_for_unlimited'] == 'unlimited')
			$runout_policy[]	= 'unlimited';


		### 색상검색
		if (count($sc['color_pick']) > 0) {
			/*
			$colorKeys	= implode(' + ', $sc['color_pick']);
			$where[] = " MATCH (C.color_pick) AGAINST ('{$colorKeys}') ";
			*/
			$where_color = '(';
			foreach ($sc['color_pick'] as $k => $v) {
				if ($k > 0) {
					$where_color .= ' OR ';
				}
				$where_color .= "C.color_pick LIKE '%{$v}%'";
			}
			$where_color .= ')';
			$where[] = " {$where_color} ";
		}

		### goods_type
		if( !empty($sc['goods_type']) )
		{
			$where[] = " C.goods_type = '{$sc['goods_type']}' ";
		}

		## 관리자 주문시 사은품은 제외 / 필수 옵션이 있는 상품만 조회
		if($sc['adminOrder'] == "Y"){
			$where[] = "C.goods_type != 'gift'";
			$where[] = "E.default_option = 'Y'";
		}

		if($sc['fblike_goods_seq']){
			$where[] = "C.goods_seq in ('".$sc['fblike_goods_seq']."')";
			$_GET['cart_table'] = 1;
		}

		if( !empty($sc['goodsKind']) ){
			if(!is_array($sc['goodsKind'])) $sc['goodsKind'] = array($sc['goodsKind']);
			$where[] = " C.goods_kind in ('".implode("','",$sc['goodsKind'])."') ";//일반상품
		}else if($_GET['cart_table']){ //관리자 주문, 개인결제 2016-02-01 @nsg
			$where[] = " C.goods_kind in ('coupon', 'goods')";
		}else{
			if( SOCIALCPUSE === true ) {
				$where[] = " C.goods_kind = 'coupon' ";//티켓상품
			}else{
				$where[] = " C.goods_kind = 'goods' ";//일반상품
			}
		}

		if	( PACKAGEUSE !== 'all' ) {
			if	( PACKAGEUSE === true ) {
				$where[] = " C.package_yn = 'y' ";//패키지
			}else{
				$where[] = " C.package_yn = 'n' ";//일반상품
			}
		}

		### 카테고리 미연결상품
		if( !empty($sc['goods_category_no']) ){
			$where[] = " (SELECT E.category_link_seq FROM fm_category_link E WHERE E.goods_seq = C.goods_seq limit 1) is null";
		}

		### 브랜드 미연결상품
		if( !empty($sc['goods_brand_no']) ){
			$where[] = "(SELECT E.category_link_seq FROM fm_brand_link E WHERE E.goods_seq = C.goods_seq limit 1) is null";
		}

		### 지역 미연결상품
		if( !empty($sc['goods_location_no']) ){
			$where[] = "(SELECT location_link_seq FROM fm_location_link E WHERE E.goods_seq = C.goods_seq  limit 1) is null";
		}

		### 티켓상품그룹
		if( !empty($sc['social_goods_group']) && !empty($sc['social_goods_group_name']) )
		{
			$where[] = " C.social_goods_group = '{$sc['social_goods_group']}' ";
		}

		if( !empty($sc['keyword'])) {
			$keyword = trim(addslashes(str_replace(' ','',$sc['keyword'])));
			$arr_where_str['goods_name']	= "REPLACE(C.goods_name,' ','') like '%{$keyword}%'";
			$arr_where_str['goods_seq']		= "C.goods_seq = '{$keyword}'";
			$arr_where_str['goods_code']	= "C.goods_code like '%{$keyword}%'";
			$arr_where_str['keyword']		= "REPLACE(C.keyword,' ','') like '%{$keyword}%'";
			$arr_where_str['summary']		= "REPLACE(C.summary,' ','') like '%{$keyword}%'";
			$arr_where_str['hscode']		= "REPLACE(C.hscode,' ','') like '%{$keyword}%'";
			if( PACKAGEUSE === true ){ //패키지상품일 경우 조건 변경
				$arr_where_str['goods_code'] = "C.goods_seq = any(
					select ao.goods_seq from fm_goods_option ao,
						(
							select so.option_seq from fm_goods_option so,fm_goods sg where so.goods_seq=sg.goods_seq and (so.package_count is null or so.package_count=0) and goods_code like '%{$keyword}'
						) aso
					where
						ao.package_option_seq1 = aso.option_seq
						or ao.package_option_seq2 = aso.option_seq
						or ao.package_option_seq3 = aso.option_seq
						or ao.package_option_seq4 = aso.option_seq
						or ao.package_option_seq5 = aso.option_seq
				)";
				$arr_where_str['hscode'] = "C.goods_seq  = any(
					select ao.goods_seq from fm_goods_option ao,
						(
							select so.option_seq from fm_goods_option so,fm_goods sg where so.goods_seq=sg.goods_seq and (so.package_count is null or so.package_count=0) and hscode like '%{$keyword}'
						) aso
					where
						ao.package_option_seq1 = aso.option_seq
						or ao.package_option_seq2 = aso.option_seq
						or ao.package_option_seq3 = aso.option_seq
						or ao.package_option_seq4 = aso.option_seq
						or ao.package_option_seq5 = aso.option_seq
				)";
			}
			if ($sc['search_type'] == "" || $sc['search_type'] == "all") {
				$where[] = "(".implode(' or ',$arr_where_str).")";
			} else if ($sc['search_type'] == "goods_name") {
				$where[] = $arr_where_str['goods_name'];
			} else if ($sc['search_type'] == "goods_seq") {
				$where[] = $arr_where_str['goods_seq'];
			} else if ($sc['search_type'] == "goods_code") {
				$where[] = $arr_where_str['goods_code'];
			} else if ($sc['search_type'] == "keyword") {
				$where[] = $arr_where_str['keyword'];
			} else if ($sc['search_type'] == "summary") {
				$where[] = $arr_where_str['summary'];
			} else if ($sc['search_type'] == "hscode") {
				$where[] = $arr_where_str['hscode'];
			}
		} //고유번호,상품명,상품 코드,간략 설명,상품 검색 태그

		// 검색어 설명 문구
		$arr_search_type = array('all'=>'전체검색','goods_name'=>'상품명','goods_seq'=>'상품 고유값','goods_code'=>'상품코드','keyword'=>'태그','summary'=>'간략설명','hscode'=>'수출입상품코드');
		if($sc['search_type'] != ""){
			$sc['search_type_text'] = sprintf("%s : %s", $arr_search_type[$sc['search_type']], $keyword);
		}

		### CATEGORY
		$tmp_link_str = !empty($sc['goods_category']) ? " and CL.link=1 " : "";
		if( !empty($sc['category4']) ) $sc_category = $sc['category4'];
		else if( !empty($sc['category3']) ) $sc_category = $sc['category3'];
		else if( !empty($sc['category2']) ) $sc_category = $sc['category2'];
		else if( !empty($sc['category1']) ) $sc_category = $sc['category1'];
		if( $sc_category ) $froms[] = "inner join fm_category_link CL on CL.goods_seq=C.goods_seq and CL.category_code='".$sc_category."'".$tmp_link_str;

		### BRAND
		$tmp_link_str = !empty($sc['goods_brand']) ? " and BL.link=1 " : "";
		if( !empty($sc['brands4']) )			$sc_brand = $sc['brands4'];
		else if( !empty($sc['brands3']) )	$sc_brand = $sc['brands3'];
		else if( !empty($sc['brands2']) )	$sc_brand = $sc['brands2'];
		else if( !empty($sc['brands1']) )	$sc_brand = $sc['brands1'];
		if( $sc_brand )	$froms[] = "inner join fm_brand_link BL on BL.goods_seq=C.goods_seq and BL.category_code='".$sc_brand."'".$tmp_link_str;

		### LOCATION
		$tmp_link_str = !empty($sc['goods_location']) ? " and link=1 " : "";
		if( !empty($sc['location4']) )		$sc_location = $sc['location4'];
		else if( !empty($sc['location3']) )	$sc_location = $sc['location3'];
		else if( !empty($sc['location2']) )	$sc_location = $sc['location2'];
		else if( !empty($sc['location1']) )	$sc_location = $sc['location1'];
		if( $sc_location )	$froms[] = "inner join fm_location_link LL on LL.goods_seq=C.goods_seq and LL.location_code='".$sc_location."'".$tmp_link_str;

		//동영상
		if( $sc['file_key_w'] ){
			$where[] = " ( C.file_key_w != '') ";// or file_key_w is not null

			if( !empty($sc['video_use']) && $sc['video_use'] !="전체" ){
				$where[] = "C.video_use = '{$sc['video_use']}' ";
			}

		}
		if( $sc['videototal'] ){
			$where[] = "C.videototal > 0 ";
		}

		### DATE
		if( !empty($sc['sdate']) && !empty($sc['edate']) ){
			$where[] = "C.{$sc['date_gb']} between '{$sc['sdate']} 00:00:00' and '{$sc['edate']} 23:59:59' ";
		}else if( !empty($sc['sdate']) && empty($sc['edate']) ){
			$where[] = "C.{$sc['date_gb']} >= '{$sc['sdate']}' ";
		}else if( empty($sc['sdate']) && !empty($sc['edate']) ){
			$where[] = "C.{$sc['date_gb']} <= '{$sc['edate']}' ";
		}


		### 재고검색
		if($sc['stock_compare'] == 'less'){
			$where[] = "(select goods_seq from fm_goods_supply where (option_seq IS NOT NULL OR option_seq != '') AND goods_seq = C.goods_seq AND stock < safe_stock limit 1)";
		}else if($sc['stock_compare'] == 'greater'){
			$where[] = "(select goods_seq from fm_goods_supply where (option_seq IS NOT NULL OR option_seq != '') AND goods_seq = C.goods_seq AND stock < safe_stock+'".$sc['sstock']."' limit 1)";
		}else if($sc['stock_compare'] == 'stock'){
			$where[] = "(select goods_seq from fm_goods_supply where (option_seq IS NOT NULL OR option_seq != '') AND goods_seq = C.goods_seq AND stock BETWEEN '".$sc['sstock']."' AND '".$sc['estock']."' limit 1)";
		}else if($sc['stock_compare'] == 'safe'){
			$where[] = "(select goods_seq from fm_goods_supply where (option_seq IS NOT NULL OR option_seq != '') AND goods_seq = C.goods_seq AND safe_stock BETWEEN '".$sc['sstock']."' AND '".$sc['estock']."' limit 1)";
		}

		### WEIGHT
		if( $sc['sweight']!='' && $sc['eweight'] == '' ){
			$where[] = "C.goods_seq = any( select W.goods_seq from fm_goods_option W where W.goods_seq = C.goods_seq AND W.weight >= '{$sc['sweight']}' ) ";
		}
		if( $sc['eweight']!='' && $sc['sweight'] == '' ){
			$where[] = "C.goods_seq = any( select W.goods_seq from fm_goods_option W where W.goods_seq = C.goods_seq AND W.weight <= '{$sc['eweight']}' ) ";
		}
		if( $sc['sweight']!='' && $sc['eweight']!=''){
			$where[] = "C.goods_seq = any( select W.goods_seq from fm_goods_option W where W.goods_seq = C.goods_seq AND W.weight between '{$sc['sweight']}' and '{$sc['eweight']}' ) ";
		}


		### PAGE_VIEW
		if( $sc['spage_view']!='' ){
			$where[] = "C.page_view >= '{$sc['spage_view']}' ";
		}
		if( $sc['epage_view']!='' ){
			$where[] = "C.page_view <= '{$sc['epage_view']}' ";
		}

		### 청약철회여부 0:가능 1:불가능
		if($sc['cancel_type'][1] == '1' )	$where[] = " (  C.cancel_type = '". implode('\' OR C.cancel_type= \'',$sc['cancel_type'])."' ) ";
		else if( isset($sc['cancel_type']) )	$where[] = " (  C.cancel_type = '0' or C.cancel_type is null ) ";

		### GOODSVIEW
		if (count($sc['goodsView']) == 1) {

			if ($sc['goodsView'][0] == 'auto') {
				$where[] = "C.display_terms = 'AUTO'";
			} else {
				$where[] = "C.display_terms = 'MENUAL'";
				$where[] = "C.goods_view = '{$sc['goodsView'][0]}'";
			}


		} else if (count($sc['goodsView']) == 2) {
			$searchAutoKey		= array_search('auto', $sc['goodsView']);
			$wherePrefix		= '';

			if ($searchAutoKey !== false)
				unset($sc['goodsView'][$searchAutoKey]);

			$viewSearchValue	= implode("','", $sc['goodsView']);

			if (count($sc['goodsView']) == 1)
				$where[]		= "(C.display_terms = 'AUTO' OR (C.display_terms = 'MENUAL' AND C.goods_view = '{$viewSearchValue}'))";
			else
				$where[]		= "C.display_terms = 'MENUAL'";
		}

		### TAX
		if( !empty($sc['taxView']) && count($sc['taxView'])=='1' )
		{
			$where[] = "C.tax = '{$sc['taxView'][0]}' ";
		}

		### GOODS STATUS
		if( !empty($sc['goodsStatus']) ){
			foreach($sc['goodsStatus'] as $k){
				$tmp[] = "'".$k."'";
			}
			$tmp_text = implode(",",$tmp);
			$where[] = "C.goods_status in ( {$tmp_text} ) ";
		}

		### FEED STATUS
		if		( $sc['feed_status'] ){
			if		( !is_array($sc['feed_status']) )	$sc['feed_status']	= array($sc['feed_status']);
			if		( count($sc['feed_status']) == 1){
				if		( in_array('Y', $sc['feed_status']) ){
					$where[]	= "C.feed_status = 'Y'";
				}elseif	( in_array('N', $sc['feed_status']) ){
					$where[]	= "C.feed_status != 'Y'";
				}
			}
		}

		### search_reserve 0:기본 1:개별
		if($sc['search_reserve'][0] && $sc['search_reserve'][1]){
		}else{
			if($sc['search_reserve'][0]){
				$where[] = "C.reserve_policy='shop'";
			}
			if($sc['search_reserve'][1]){
				$where[] = "C.reserve_policy='goods'";
			}
		}

		#### 승인/미승인 provider_status 0:기본 1:개별 @2013-08-12
		$search_provider_status_arr = array();

		if ($sc['search_provider_status']!= "all" && isset($sc['search_provider_status'])) {
			$where2 = "C.provider_status = '{$sc['search_provider_status']}'";
			if($sc['search_provider_status'] === '0')
				$where2 = "(C.provider_status = '{$sc['search_provider_status']}' or C.provider_status is null or C.provider_status='')";
			$where[] = $where2;
		}

		### search_delivery 0:국내기본 1:국내개별 2:해외
		if($sc['search_delivery'][0]){
			$where[] = "C.shipping_policy='shop'";
		}
		if($sc['search_delivery'][1]){
			$where[] = "C.shipping_policy='goods'";
		}

		## 추가정보 검색 2015-04-29
		if ($sc['goods_addinfo']=='model' && $sc['goods_addinfo_title']!='') $sc['model'] = $sc['goods_addinfo_title'];
		if ($sc['goods_addinfo']=='brand' && $sc['goods_addinfo_title']!='') $sc['brand'] = $sc['goods_addinfo_title'];
		if ($sc['goods_addinfo']=='manufacture' && $sc['goods_addinfo_title']!='') $sc['manufacture'] = $sc['goods_addinfo_title'];
		if ($sc['goods_addinfo']=='orgin' && $sc['goods_addinfo_title']!='') $sc['orgin'] = $sc['goods_addinfo_title'];
		if ($sc['goods_addinfo']=='direct' && $sc['goods_addinfo_title']!='') $sc['direct'] = $sc['goods_addinfo_title'];
		if( $sc['goods_addinfo_title']!='' && preg_match('/^goodsaddinfo_/',$sc['goods_addinfo']) ){
			$where[] = "C.goods_seq = any( select K.goods_seq from fm_goods_addition K where K.goods_seq = C.goods_seq AND K.contents = '{$sc['goods_addinfo_title']}' AND K.type = '{$sc['goods_addinfo']}') ";
		}

		### MOEDEL
		if( !empty($sc['model']) )
		{
			$where[] = "C.goods_seq = any( select K.goods_seq from fm_goods_addition K where K.goods_seq = C.goods_seq AND K.contents LIKE '{$sc['model']}%' ) ";
		}
		### BRAND
		if( !empty($sc['brand']) )
		{
			$where[] = "C.goods_seq = any( select K.goods_seq from fm_goods_addition K where K.goods_seq = C.goods_seq AND K.contents LIKE '{$sc['brand']}%' ) ";
		}
		### MANUFACTURE
		if( !empty($sc['manufacture']) )
		{
			$where[] = "C.goods_seq = any( select K.goods_seq from fm_goods_addition K where K.goods_seq = C.goods_seq AND K.contents LIKE '{$sc['manufacture']}%' AND K.type ='manufacture' ) ";
		}
		### ORGIN
		if( !empty($sc['orgin']) )
		{
			$where[] = "C.goods_seq = any( select K.goods_seq from fm_goods_addition K where K.goods_seq = C.goods_seq AND K.contents LIKE '{$sc['orgin']}%' AND K.type ='orgin' ) ";
		}
		### DIRECT
		if( !empty($sc['direct']) )
		{
			$where[] = "C.goods_seq = any( select K.goods_seq from fm_goods_addition K where K.goods_seq = C.goods_seq AND K.contents LIKE '{$sc['direct']}%' AND K.type ='direct' ) ";
		}

		### 매입상품만
		if( !empty($sc['provider_base']) ){
			$where[] = " C.provider_seq='1' ";
		}

		### 매입상품제외
		if( !empty($sc['provider_base_ban']) ){
			$where[] = " provider_seq  <> '1' ";
		}

		### 입점사
		if( !empty($sc['provider_seq']) ){
			$where[] = " C.provider_seq='{$sc['provider_seq']}' ";
		}

		### 필수옵션의 해외배송여부
		if($sc['search_option_international_shipping']){
			$where[] = "C.option_international_shipping_status ='".$sc['search_option_international_shipping']."'";
		}

		//일괄업데이트시 미변환추가
		if( !empty($sc['gabiaimagehostign']) )
		{
			$where[] = " C.convert_image_date IS NULL AND C.convert_image_cnt = 0";
		}

		### icon
		if( !empty($sc['goodsIconCode']) ){
			foreach($sc['goodsIconCode'] as $icon => $icon_use){
				if($icon_use) $r_icon[] = $icon;
			}
		}

		// 선택한 아이콘 검색 2015-04-29
		if (!empty($sc['select_search_icon'])) $r_icon = explode(",", $sc['select_search_icon']);

		if( $r_icon )
		{
			$where[] = " C.goods_seq in (select goods_seq codecd from fm_goods_icon where codecd in (".implode(',',$r_icon)."))";
			$search_yn = 'y';
		}

		// 판매마켓 검색
		if(is_array($sc["openmarket"]) && count($sc['openmarket']) > 0){
			$openmarket	= $sc["openmarket"];
			$malllist	= $sc["malllist"];
			if(is_array($malllist) && count($malllist) > 0 && in_array('etc', $openmarket)){
				$add_sub_where[]	= "mall_code not in ('".implode("', '", $malllist)."')";
				$etc_key			= array_search('etc', $openmarket);
				unset($openmarket[$etc_key]);
			}
			if(is_array($openmarket) && count($openmarket) > 0){
				$add_sub_where[]	= "mall_code in ('".implode("', '", $openmarket)."')";
			}
			$where[]	= " C.goods_seq in (select goods_seq from fm_linkage_goods_mall where ".implode(' or ', $add_sub_where) . " ) ";
		}

		// 오픈마켓 검색
		if($sc["market"] || $sc["sellerId"]){
			if($sc["market"]){
				$add_sub_where[]	= " market = '".$sc["market"]."' ";
			}
			if($sc["sellerId"]){
				$add_sub_where[]	= " seller_id = '".$sc["sellerId"]."' ";
			}
			$where[]	= " C.goods_seq in (select goods_seq from fm_market_product_info where ".implode(' and ', $add_sub_where) . " ) ";
		}

		## 미승인 사유 검색
		if(is_array($sc['provider_status_reason_type']) && count($sc['provider_status_reason_type']) > 0){
			$where[] = "(C.provider_status='0' or C.provider_status is null or C.provider_status='')";
			if	(in_array('e', $sc['provider_status_reason_type'])){
				$rType	= $sc['provider_status_reason_type'];
				$ekey	= array_search('e', $rType);
				unset($rType[$ekey]);
				$where[] = "(C.provider_status_reason_type in ('".implode("', '", $rType)."') or C.provider_status_reason_type not in ('1', '2', '3') )";
			}else{
				$where[]	= "C.provider_status_reason_type in ('".implode("', '", $sc['provider_status_reason_type'])."') ";
			}
		}

		## 성인검색 추가 :: 2015-03-17 lwh
		if($sc["adult_goods"] != ""){
			if ($sc["adult_goods"][0] && $sc["adult_goods"][1]){
			}else{
				if ($sc["adult_goods"][0]) $where[] = " C.adult_goods = 'N'";
				if ($sc["adult_goods"][1]) $where[] = " C.adult_goods = 'Y'";
			}
		}

		## 일괄 업데이트 16.가격대체문구 에서도 string_price 사용되어 배열여부로 판단함
		if ( is_array($sc['string_price']) ) {
			## 비회원 구매제한
			if ($sc['string_price'][0])
				$where[] = "C.string_price_use = '1'";

			## 회원 + 일반등급 구매제한
			if ($sc['string_price'][1])
				$where[] = "C.member_string_price_use = '1'";
		}elseif($sc['string_price']){
			if($sc['string_price'] == 1){
				$where[] = "C.string_price_use = '1'";			// 비회원
			}elseif($sc['string_price'] == 2){
				$where[] = "C.member_string_price_use = '1'";	// 기본등급
			}elseif($sc['string_price'] == 3){
				$where[] = "C.member_string_price_use = '1'";	// 추가등급
			}

		}

		## 별표시 검색 favorite_chk 0:체크 1:미체크
		if(is_array($sc['favorite_chk'])){
			if ($sc['favorite_chk'][0] && !$sc['favorite_chk'][1]) {
				$where[] = "C.favorite_chk = 'checked'";
			}

			if ($sc['favorite_chk'][1] && !$sc['favorite_chk'][0]) {
				$where[] = "C.favorite_chk = 'none'";
			}
		}elseif($sc['favorite_chk']){
			$where[] = "C.favorite_chk = '".$sc['favorite_chk']."'";
		}

		### PRICE
		if( $sc['sprice']!='' )	$where[]	= "g.default_{$sc['price_gb']} >= '{$sc['sprice']}'";
		if( $sc['eprice']!='' )	$where[]	= "g.default_{$sc['price_gb']} <= '{$sc['eprice']}'";


		## 수수료 검색
		if ($sc['commission_type_sel']!='') {
			$where[] = "C.goods_seq = any( select N.goods_seq from fm_goods_option N where N.goods_seq = C.goods_seq AND N.commission_type = '{$sc['commission_type_sel']}' AND C.provider_seq > 1 ) ";
		}
		if ($sc['s_commission_rate']!='' && $sc['e_commission_rate']=='') {
			$where[] = "C.goods_seq = any( select N.goods_seq from fm_goods_option N where N.goods_seq = C.goods_seq AND N.commission_rate >= '{$sc['s_commission_rate']}' ) ";
		}
		if ($sc['e_commission_rate']!='' && $sc['s_commission_rate']=='') {
			$where[] = "C.goods_seq = any( select N.goods_seq from fm_goods_option N where N.goods_seq = C.goods_seq AND N.commission_rate <= '{$sc['e_commission_rate']}' ) ";
		}
		if ($sc['e_commission_rate']!='' && $sc['s_commission_rate']!='') {
			$where[] = "C.goods_seq = any( select N.goods_seq from fm_goods_option N where N.goods_seq = C.goods_seq AND N.commission_rate between '{$sc['s_commission_rate']}' and '{$sc['e_commission_rate']}' ) ";
		}

		## 등급혜택 검색
		if ($sc['sale_seq'] != '') {
			$where[] = "C.sale_seq = '{$sc['sale_seq']}'";
		}

		## 할인이벤트 검색 all:전체 상품 category:카테고리 goods_view:상품
		if ($sc['event_seq']!='') {
			$query = $this->db->query("SELECT goods_rule FROM fm_event WHERE event_seq =? ",array($sc['event_seq']));
			$event_row = $query->row_array();

			// 제외상품
			$sql_except_goods = "C.goods_seq NOT IN (SELECT goods_seq FROM fm_event_choice WHERE choice_type='except_goods' AND event_seq = '{$sc['event_seq']}')";

			// 제외카테고리상품
			$sql_except_category = "C.goods_seq NOT IN (SELECT cl.goods_seq FROM fm_event_choice evtc INNER JOIN fm_category_link cl ON evtc.category_code=cl.category_code WHERE evtc.choice_type='except_category' AND evtc.event_seq = '{$sc['event_seq']}' group by cl.goods_seq)";

			if ($event_row['goods_rule']=='all') { //구매대상 전체상품
				$where[] = $sql_except_goods;
				$where[] = $sql_except_category;
			} else if ($event_row['goods_rule']=='category') {
				$where[] = "C.goods_seq IN (SELECT cl.goods_seq FROM fm_event_choice evtc INNER JOIN fm_category_link cl ON evtc.category_code=cl.category_code WHERE evtc.choice_type='category' AND evtc.event_seq = '{$sc['event_seq']}' group by cl.goods_seq)";
				$where[] = $sql_except_goods;
				$where[] = $sql_except_category;
			} else if ($event_row['goods_rule']=='goods_view'){
				$where[] = "C.goods_seq IN (SELECT goods_seq FROM fm_event_choice WHERE event_seq='{$sc['event_seq']}' AND choice_type='goods')";
			}
		}

		## 사은품이벤트 검색 all:전체 상품 category:카테고리 goods:상품
		if ($sc['gift_seq']!='') {
			$query = $this->db->query("SELECT goods_rule FROM fm_gift WHERE gift_seq =? ",array($sc['gift_seq']));
			$gift_row = $query->row_array();

			if ($gift_row['goods_rule']=='all') { //구매대상 전체상품
			} else if($gift_row['goods_rule']=='category') { //해당카테고리상품
				$where[] = "C.goods_seq IN (SELECT cl.goods_seq FROM fm_gift_choice gftc INNER JOIN fm_category_link cl ON gftc.category_code=cl.category_code WHERE gftc.choice_type='category' AND gftc.gift_seq = '{$sc['gift_seq']}' group by cl.goods_seq)";
			} else if($gift_row['goods_rule']=='goods') { //상품선정
				$where[] = "C.goods_seq IN (SELECT goods_seq FROM fm_gift_choice WHERE gift_seq='{$sc['gift_seq']}' AND choice_type='goods')";
			}
		}

		if ($sc['referersale_seq'] != '') {
			$query				= $this->db->query("SELECT issue_type, provider_list FROM fm_referersale WHERE referersale_seq = ? ",array($sc['referersale_seq']));
			$referersale_row	= $query->row_array();

			if ($referersale_row['provider_list']) {
				// 입점사 지정
				preg_match_all('/([0-9]+)\|/', $referersale_row['provider_list'], $tmpProviderList);

				if (count($tmpProviderList[1]) > 0) {
					$providerList	= implode(',', $tmpProviderList[1]);
					$where[]		= "C.provider_seq IN ({$providerList})";
				}

			} else {
				// 입점사 미지정
				$where[]			= 'C.provider_seq = 1';
			}

			if ($referersale_row['issue_type'] != 'all') {
				$queryGoods			= $this->db->query("SELECT GROUP_CONCAT(goods_seq) AS goods_seq FROM fm_referersale_issuegoods WHERE referersale_seq = '{$sc['referersale_seq']}' AND type = '{$referersale_row['issue_type']}' GROUP BY referersale_seq");
				$targetGoods		= $queryGoods->row_array();

				$queryCategories	= $this->db->query("SELECT GROUP_CONCAT(category_code) AS category_code FROM fm_referersale_issuecategory WHERE referersale_seq = '{$sc['referersale_seq']}' AND type = '{$referersale_row['issue_type']}' GROUP BY referersale_seq");
				$targetCategories	= $queryCategories->row_array();

				if ($targetGoods['goods_seq'] || $targetCategories['category_code']) {
					$whereType		= ($referersale_row['issue_type'] == 'except') ? 'NOT IN' : 'IN';
					$joinType		= ($whereType == 'IN') ? 'OR' : 'AND';

					if ($targetGoods['goods_seq'] && !$targetCategories['category_code'])
						$where[]	= "C.goods_seq {$whereType}({$targetGoods['goods_seq']})";

					if (!$targetGoods['goods_seq'] && $targetCategories['category_code'])
						$where[]	= "C.goods_seq IN (select goods_seq from fm_category_link where category_code {$whereType} ({$targetCategories['category_code']}))";

					if ($targetGoods['goods_seq'] && $targetCategories['category_code'])
						$where[]	= "C.goods_seq IN (select goods_seq from fm_category_link where category_code {$whereType} ({$targetCategories['category_code']})) {$joinType} C.goods_seq {$whereType}({$targetGoods['goods_seq']})";

				} else {
					// 성립하지 않는 조건
					$where[]		= 'C.goods_seq < 1';
				}
			}
		}

		## 배송그룹 검색 :: 2016-08-31 lwh
		if($sc['ship_grp_seq'] && !$sc['shipping_group_seq']) $sc['shipping_group_seq'] = $sc['ship_grp_seq'];	//배송정책으로 부터 링크 되어 넘어온 경우
		if ($sc['shipping_group_seq'] != '') {
			if (is_array($sc['shipping_group_seq'])){
				$where[] = "C.shipping_group_seq IN ('" . implode("', '",$sc['shipping_group_seq']) . "')";
			}else{
				if($sc['shipping_group_seq'] == '-1'){
					$where[] = "C.trust_shipping = 'Y'";
				}else{
					$where[] = "C.shipping_group_seq = '" . $sc['shipping_group_seq'] . "'";
				}
			}
		}


		## 배송방법 검색
		if (count($sc['shipping_set_code'])) {

			// 국내 배송방범 검색
			if (isset($sc['shipping_set_code']['domestic'])) {
				foreach ($sc['shipping_set_code']['domestic'] as $val)
					$where[]	= "S.kr_{$val}_yn ='Y'";
			}

			// 해외 배송방법 검색
			if (isset($sc['shipping_set_code']['international'])) {
				foreach ($sc['shipping_set_code']['international'] as $val)
					$where[]	= "S.gl_{$val}_yn ='Y'";
			}
		}


		## 재고판매 개발설정
		if (count($runout_policy) > 0) {
			$runout_policy_list	= implode("','" , $runout_policy);
			$where[] = "C.runout_policy IN ('{$runout_policy_list}')";
		}

		##대량구매
		if ($sc['multi_discount'] == 'Y')
			$where[] = "C.multi_discount_policy != ''";

		if($where)	$AwhereSql = " where " . implode(' and ',$where);

		// 상품 번호 절대 검색 ( 상품 번호 절대 검색 시 위에서 만든 검색 조건은 모두 무시함 )
		if	(is_array($sc['abs_goods_seq']) && count($sc['abs_goods_seq']) > 0) {
			$AwhereSql = " where C.goods_seq in ('".implode("', '", $sc['abs_goods_seq'])."') ";
		}

		$str_orderby = $sc['orderby'];
		if( $sc['orderby'] == 'price') $str_orderby = "default_price";
		if( $sc['orderby'] == 'consumer_price') $str_orderby = "default_consumer_price";

		//정렬관련 추가 (정가, 할인가, 재고 오름/내림 차순 정렬)
		if	($str_orderby)		$orderby = "C.".$str_orderby." ".$sc['sort'];
		else						$orderby = "C.goods_seq desc";

		if( !$orderby ) $orderby = 'C.goods_seq';
		$sqlSelect		= "select C.*, SG.shipping_group_name ";
		$sqlFrom		= "from fm_goods as C";

		if(!$sc['excel_spout']){ //엑셀 다운로드 용 쿼리 리턴 kmj
			$sqlFrom .= " LEFT JOIN fm_shipping_grouping SG ON C.shipping_group_seq = SG.shipping_group_seq ";
			$sqlFrom .= " LEFT JOIN fm_shipping_group_summary S ON C.shipping_group_seq = S.shipping_group_seq ";
			$sqlFrom .= " INNER JOIN fm_goods_option E ON C.goods_seq = E.goods_seq ";
		}

		if( $froms ) $sqlFrom .= ' ' . implode(' ', $froms);
		$sqlGroupby	= "";
		$sqlOrderby	= " order by " . $orderby;

		$sql		= str_replace('C.','g.',$sqlSelect . $sqlFrom . $AwhereSql . $sqlGroupby);
		$sql		= str_replace('as C','as g',$sql);
		if	($this->get_from_mode){
			return $sqlFrom . $AwhereSql . $sqlGroupby . $sqlOrderby;
		}
		if($this->batch_mode){
			return $sql;
		}

		if($sc['excel_spout']){
			$excel_sql = $sqlFrom . $AwhereSql;
			$excel_sql = str_replace('g.','C.', $excel_sql);
			return $excel_sql;
		}

		$sql .= str_replace('C.','g.',$sqlOrderby);

		$params['query']	= $sql;
		$params['bind']	= '';
		$params['mode']	= 'query1';
		$this->blockpage->perpage			= 10;
		$this->blockpage->page				= $sc['page'];
		$this->blockpage->page_number	= $sc['perpage'];
		$this->blockpage->set();
		$sql = $this->blockpage->page_query($params);
		$sql = "
		select C.*,CASE WHEN C.goods_status = 'unsold' THEN '판매중지'
							WHEN C.goods_status = 'purchasing' THEN '재고확보중'
							WHEN C.goods_status = 'runout' THEN '품절'
							ELSE '정상' END AS goods_status_text
						,D.consumer_price,D.price
		from (".$sql.") as C
			left join fm_goods_option as D on C.goods_seq=D.goods_seq and D.default_option='y' " . $sqlOrderby;
		$params['query'] = $sql;
		$params['mode'] = 'query2';
		$sql = $this->blockpage->page_query($params);
		$result = $this->blockpage->page_html($sql);
		$result['page']['querystring'] = get_args_list();

		foreach((array)$result['record'] as $key => $val){
			if(serviceLimit('H_AD')){
				$result['record'][$key]['provider_name']	= ($val['provider_seq'] == 1) ? $provider_name : $provider_list[$val['provider_seq']]['provider_name'];	// 본사
			}

			$result['record'][$key]['default_commission_type']	= ($provider_list[$val['provider_seq']]['commission_type']) ? $provider_list[$val['provider_seq']]['commission_type'] : 'SACO';

			//예약 상품의 경우 문구를 넣어준다 2016-11-07
			$result['record'][$key]['goods_name']	= get_goods_pre_name($val,true);
		}

		return $result;
	}

	/* 상품 리스트 */
	public function admin_goods_list_new($sc) {

		$CI =& get_instance();

		/* SQL INJECTION 관련 특정 parameter에 대한 data binding 처리 */
		$bindData = [];
		$this->load->model("providermodel");
		$provider_tmp	= $this->providermodel->provider_goods_list();
		$provider_list	= array();
		foreach((array)$provider_tmp as $val){
			$provider_list[$val['provider_seq']] = $val;
		}

		$provider_name = getAlert("sy009");

		$sc['page']		= $sc['page'] > 1 ? $sc['page'] : 0;
		$sc['perpage']	= isset($sc['perpage']) ? $sc['perpage'] : 10;

		if(!$sc['event_seq'])			$sc['event_seq']		= $sc['event_seq'];
		if(!$sc['gift_seq'])			$sc['gift_seq']			= $sc['gift_seq'];
		if(!$sc['search_type'])			$sc['search_type']		= $sc['search_type'];
		if(!$sc['search_type_text'])	$sc['search_type_text'] = $sc['search_type_text'];

		$today		= date('Y-m-d');
		$whereStr	= $countWhereStr = array();
		$joinStr	= array();

		$orderby 	= "goods_seq";
		$sort 		= "desc";

		if( strpos($sc['sort'], "asc") !== false){
			$sort = "asc";
		}
		if(preg_match('/asc_|desc_/i', $sc['sort'])) {
			$orderby = preg_replace(array('/desc_/','/asc_/'),'',$sc['sort']);
			if( preg_match('/price/', $orderby) ){
				$orderby = "default_".$orderby;
			}
		}
		// if( preg_match('/price/', $sc['sort']) ){
		// 	$orderStr = " ORDER BY OP.{$orderby} {$sort}";
		// }else{
			$orderStr = " ORDER BY C.{$orderby} {$sort}";
		// }

		$limitStr = " LIMIT {$sc['page']}, {$sc['perpage']}";

		if ( $sc['provider_seq'] > 0 || defined('__SELLERADMIN__')) {
			$whereStr['goods'] .=  " AND C.provider_seq = '{$sc['provider_seq']}'";

			# 입점사 어드민 일때에만 입점사 정보를 전체 카운트 조건으로 포함
			if(defined('__SELLERADMIN__')) $countWhereStr['goods'] .= " AND C.provider_seq = '{$sc['provider_seq']}' ";
		}

		if(trim($sc['select_providers']) != '' && !preg_match("/all/",$sc['select_providers'])){
			$select_providers		= array_filter(explode("|",trim($sc['select_providers'])));
			$whereStr['goods']		.=  " AND C.provider_seq in(".implode(",",$select_providers).")";
			$countWhereStr['goods'] .=  " AND C.provider_seq in(".implode(",",$select_providers).")";
		}

		# 카테고리
		if( $sc['category4'] ){
			$sc_category = $sc['category4'];
		} else if( $sc['category3'] ){
			$sc_category = $sc['category3'];
		} else if( $sc['category2'] ){
			$sc_category = $sc['category2'];
		} else if( $sc['category1'] ){
			$sc_category = $sc['category1'];
		}

		if( $sc_category ){
			$joinStr['category_link']	 = " INNER JOIN fm_category_link CL ON CL.goods_seq = C.goods_seq";
			$whereStr['category_link']	.= " AND CL.category_code= ?";
			$bindData[] = $sc_category;
		}

		## 대표카테고리 기준 검색
		if( $sc_category && $sc['goods_category'] ){
			$whereStr['category_link'] .= " AND CL.link=1";
		}

		## 카테고리 미연결
		if( $sc['goods_category_no'] ){
			// $joinStr['category_link']	= " LEFT JOIN fm_category_link CL ON CL.goods_seq = C.goods_seq";
			// $whereStr['category_link'] .= " AND CL.goods_seq IS NULL";
			$whereStr['category_link'] .= " AND NOT EXISTS (SELECT 1 FROM fm_category_link WHERE\t goods_seq = C.goods_seq)";
		}

		# 브랜드
		if( $sc['brands4'] ){
			$sc_brand = $sc['brands4'];
		} else if( $sc['brands3'] ){
			$sc_brand = $sc['brands3'];
		} else if( $sc['brands2'] ){
			$sc_brand = $sc['brands2'];
		} else if( $sc['brands1'] ){
			$sc_brand = $sc['brands1'];
		}

		if( $sc_brand ){
			$joinStr['brand_link']	 = " INNER JOIN fm_brand_link BL ON BL.goods_seq = C.goods_seq";
			$whereStr['brand_link']	.= " AND BL.category_code='{$sc_brand}'";
		}

		## 대표브랜드 기준 검색
		if( $sc_brand && $sc['goods_brand'] ){
			$whereStr['brand_link'] .= " AND BL.link=1";
		}

		## 브랜드 미연결상품
		if( $sc['goods_brand_no'] ){
			// $joinStr['brand_link']	 = " LEFT JOIN fm_brand_link BL ON BL.goods_seq = C.goods_seq";
			// $whereStr['brand_link'] .= " AND BL.goods_seq is NULL";
			$whereStr['category_link'] .= " AND NOT EXISTS (SELECT 1 FROM fm_brand_link WHERE\t goods_seq = C.goods_seq)";
		}

		## 지역
		if( $sc['location4'] ){
			$sc_location = $sc['location4'];
		} else if( $sc['location3'] ){
			$sc_location = $sc['location3'];
		} else if( $sc['location2'] ){
			$sc_location = $sc['location2'];
		} else if( $sc['location1'] ){
			$sc_location = $sc['location1'];
		}

		if( $sc_location ){
			$joinStr['location_link']	 = " INNER JOIN fm_location_link LL ON LL.goods_seq = C.goods_seq";
			$whereStr['location_link']	.= " AND LL.location_code='{$sc_location}'";
		}

		## 대표지역 기준 검색
		if( $sc_location && $sc['goods_location'] ){
			$whereStr['location_link'] .= " AND LL.link=1";
		}

		## 지역 미연결상품
		if( $sc['goods_location_no'] ){
			// $joinStr['location_link']	= " LEFT JOIN fm_location_link LL ON LL.goods_seq = C.goods_seq";
			// $whereStr['location_link'] .= " AND LL.goods_seq is NULL";
			$whereStr['category_link'] .= " AND NOT EXISTS (SELECT 1 FROM fm_location_link WHERE\t goods_seq = C.goods_seq)";
		}

		## 날짜
		if( $sc['sdate'] && $sc['edate'] ) {
			$whereStr['goods'] .= " AND C.{$sc['date_gb']} BETWEEN '{$sc['sdate']} 00:00:00' AND '{$sc['edate']} 23:59:59'";
		} else if( $sc['sdate'] ) {
			$whereStr['goods'] .= " AND C.{$sc['date_gb']} >= '{$sc['sdate']}'";
		} else if( $sc['edate'] ) {
			$whereStr['goods'] .= " AND C.{$sc['date_gb']} <= '{$sc['edate']}'";
		}

		## 가격
		// 가격 0원도 검색 할 수 있도록 하기 위해 조건 수정
		$is_sprice = ($sc['sprice']==0 && $sc['sprice']!='' || $sc['sprice']) ? true : false;
		$is_eprice = ($sc['eprice']==0 && $sc['eprice']!='' || $sc['eprice']) ? true : false;

		if( $is_sprice ){
			$whereStr['goods'] .= " AND C.default_{$sc['price_gb']} >= ? ";
			$bindData[] = $sc['sprice'];
		}
		if( $is_eprice ){
			$whereStr['goods'] .= " AND C.default_{$sc['price_gb']} <= ? ";
			$bindData[] = $sc['eprice'];
		}

		# 구매제한
		## 일괄 업데이트 16.가격대체문구 에서도 string_price 사용되어 배열여부로 판단함
		if ( is_array($sc['string_price']) ) {
			## 비회원 구매제한
			if ($sc['string_price'][0]){
				$whereStr['goods'] .= " AND C.string_price_use = '1'";
			}

			## 회원 + 일반등급 구매제한
			if ($sc['string_price'][1]){
				$whereStr['goods'] .= " AND C.member_string_price_use = '1'";
			}
		}elseif($sc['search_string_price']){
			if($sc['search_string_price'] == 1){
				$whereStr['goods'] .= " AND C.string_price_use = '1'";	// 비회원
			}elseif($sc['search_string_price'] == 2){
				$whereStr['goods'] .= " AND C.member_string_price_use = '1'";// 기본등급
			}elseif($sc['search_string_price'] == 3){
				$whereStr['goods'] .= " AND C.allmember_string_price_use = '1'";// 추가등급
			}
		}

		# 등급
		if ($sc['sale_seq']) {
			$whereStr['goods'] .= " AND C.sale_seq = ? ";
			$bindData[] = $sc['sale_seq'];
		}

		## 이벤트 상품의 경우 일반적으로 대량 등록 하지 않으므로, 각 테이블에서 goods_seq 검색해서 in 검색 하는 것으로 kmj
		## 할인이벤트 검색 all:전체 상품 category:카테고리 goods_view:상품
		if ( $sc['event_seq'] ) {
			$event_row = $this->db->query("SELECT goods_rule FROM fm_event WHERE event_seq =? ",array($sc['event_seq']))->row_array();

			if($event_row['goods_rule']=='goods_view'){ //단독 이벤트&상품 이벤트는 제외 상품 등록이 없음
				$event_goods = array();
				$sql_goods = $this->db->query("SELECT goods_seq FROM fm_event_choice WHERE event_seq=? AND choice_type='goods'", array($sc['event_seq']))->result_array();
				foreach($sql_goods as $row){
					$event_goods[] = $row['goods_seq'];
				}

				if($event_goods){
					$whereStr['goods'] .= " AND C.goods_seq IN ('".join("', '", $event_goods)."')";
				} else {
					$whereStr['goods'] .= " AND C.goods_seq < 1";
				}
			} else {
				$except_goods = array();

				// 제외상품
				$sql_except_goods = $this->db->query("SELECT goods_seq FROM fm_event_choice WHERE choice_type='except_goods' AND event_seq = ?", array($sc['event_seq']))->result_array();
				foreach($sql_except_goods as $row){
					$except_goods[] = $row['goods_seq'];
				}

				//제외 카테고리 상품
				$sql_except_category = $this->db->query("SELECT cl.goods_seq FROM fm_event_choice evtc LEFT JOIN fm_category_link cl ON evtc.category_code=cl.category_code WHERE evtc.choice_type='except_category' AND evtc.event_seq = ? group by cl.goods_seq", array($sc['event_seq']))->result_array();
				foreach($sql_except_category as $row){
					$except_goods[] = $row['goods_seq'];
				}
				$except_goods = array_unique($except_goods);

				if ($event_row['goods_rule']=='category') { //상품카테고리 등록 & 제외상품,카테고리 등록
					$event_goods = array();
					$sql_goods = $this->db->query("SELECT cl.goods_seq FROM fm_event_choice evtc LEFT JOIN fm_category_link cl ON evtc.category_code=cl.category_code WHERE evtc.choice_type='category' AND evtc.event_seq = ?", array($sc['event_seq']))->result_array();
					foreach($sql_goods as $row){
						$event_goods[] = $row['goods_seq'];
					}

					if( $except_goods ){
						$event_goods = array_diff($event_goods, $except_goods);
					}

					if($event_goods){
						$whereStr['goods'] .= " AND C.goods_seq IN ('".join("', '", $event_goods)."')";
					} else {
						$whereStr['goods'] .= " AND C.goods_seq < 1";
					}
				} else if ($event_row['goods_rule']=='all') {
					if( $except_goods ){ //제외 상품, 카테고리 만 등록
						$whereStr['goods'] .= " AND C.goods_seq NOT IN ('".join("', '", $except_goods)."')";
					}
				}
			}
		}


		## 사은품이벤트 검색 all:전체 상품 category:카테고리 goods:상품
		if ( $sc['gift_seq'] ) {
			$gift_row = $this->db->query("SELECT goods_rule FROM fm_gift WHERE gift_seq =? ", array($sc['gift_seq']))->row_array();

			if( $gift_row['goods_rule'] !== "all" ){
				$gift_goods = array();
				if($gift_row['goods_rule'] == 'category') { //해당카테고리상품
					$sql_goods = $this->db->query("SELECT cl.goods_seq FROM fm_gift_choice gftc LEFT JOIN fm_category_link cl ON gftc.category_code=cl.category_code WHERE gftc.choice_type='category' AND gftc.gift_seq = ? GROUP BY cl.goods_seq", array($sc['gift_seq']))->result_array();
				} else if($gift_row['goods_rule']=='goods') { //상품선정
					$sql_goods = $this->db->query("SELECT goods_seq FROM fm_gift_choice WHERE gift_seq = ? AND choice_type='goods'", array($sc['gift_seq']))->result_array();
				}

				foreach($sql_goods as $row){
					$gift_goods[] = $row['goods_seq'];
				}

				if($gift_goods){
					$whereStr['goods'] .= " AND C.goods_seq IN ('".join("', '", $gift_goods)."')";
				} else {
					$whereStr['goods'] .= " AND C.goods_seq < 1";
				}
			}
		}

		##유입경로 할인
		if ( $sc['referersale_seq'] ) {
			$referersale_row = $this->db->query("SELECT issue_type, provider_list FROM fm_referersale WHERE referersale_seq = ?", array($sc['referersale_seq']))->row_array();

			if( $referersale_row['provider_list'] ) {
				// 입점사 지정
				$providerList = explode('|', $referersale_row['provider_list']);
				$providerList = array_filter($providerList);

				if ($providerList) {
					$whereStr['goods'] .= " AND C.provider_seq IN ('".join("', '", $providerList)."')";
				}
			} else {
				$whereStr['goods'] .= " AND C.provider_seq = 1";
			}

			if ($referersale_row['issue_type'] !== "all") {
				$refere_goods = array();
				$targetGoods = $this->db->query("SELECT goods_seq FROM fm_referersale_issuegoods WHERE referersale_seq = ? AND type = ? GROUP BY goods_seq", array($sc['referersale_seq'], $referersale_row['issue_type']))->result_array();
				foreach($targetGoods as $row){
					$refere_goods[] = $row['goods_seq'];
				}

				$targetCategories = $this->db->query("SELECT CL.goods_seq AS goods_seq FROM fm_referersale_issuecategory as RC LEFT JOIN fm_category_link CL ON RC.category_code=CL.category_code WHERE RC.referersale_seq = ? AND RC.type = ? GROUP BY CL.goods_seq", array($sc['referersale_seq'], $referersale_row['issue_type']))->result_array();
				foreach($targetCategories as $row){
					$refere_goods[] = $row['goods_seq'];
				}

				$refere_goods = array_unique($refere_goods);

				if($referersale_row['issue_type'] === "issue"){
					if($refere_goods){
						$whereStr['goods'] .= " AND C.goods_seq IN ('".join("', '", $refere_goods)."')";
					} else {
						$whereStr['goods'] .= " AND C.goods_seq < 1";
					}
				} else if($referersale_row['issue_type'] === "except"){
					if($refere_goods){
						$whereStr['goods'] .= " AND C.goods_seq NOT IN ('".join("', '", $refere_goods)."')";
					}
				}
			}
		}

		## 사은품리스트에서 사은품이벤트에 해당하는 사은품 추출
		if($sc['gift_goods_seq']) {
			$gift_row = $this->db->query("SELECT gift_goods_seq FROM fm_gift_benefit WHERE gift_seq = ?", array($sc['gift_goods_seq']))->result_array();
			$gift_goods = array();
			foreach($gift_row as $k => $v){
				$gift_goods[] = $v['gift_goods_seq'];
			}
			$gift_goods = array_unique(explode("|",implode("|",$gift_goods)));
			$whereStr['goods'] .= " AND C.goods_seq IN ('".join("', '", $gift_goods)."')";
		}

		##대량구매
		if($sc['multi_discount']) {
			$filter_str = '{\"discountMaxOverQty\":\"\",\"discountMaxAmount\":\"\",\"discountUnit\":\"PER\"}';

			if ($sc['multi_discount'] == 'Y'){
				$whereStr['goods'] .= " AND (LENGTH(C.multi_discount_policy) > 0
										AND C.multi_discount_policy != '{}'
										AND C.multi_discount_policy != '".$filter_str."')";
			}else if ($sc['multi_discount'] == 'N'){
				$whereStr['goods'] .= " AND (LENGTH(C.multi_discount_policy) = 0
										OR C.multi_discount_policy IS NULL
										OR C.multi_discount_policy = '{}'
										OR C.multi_discount_policy = '".$filter_str."')";
			}
		}

		## 오픈마켓 검색
		## 오픈마켓 등록 상품이 대량일 수도 있으므로 join query
		if($sc['market'] || $sc['sellerId']){
			$joinStr['market_product_info']	 = " INNER JOIN fm_market_product_info MP ON MP.goods_seq = C.goods_seq";

			if($sc["market"]){
				$joinStr['market_product_info']	.= " AND MP.market = ? ";
				$bindData[] = $sc['market'];
			}

			if($sc["sellerId"]){
				$joinStr['market_product_info']	.= " AND MP.seller_id = ? ";
				$bindData[] = $sc['sellerId'];
			}
		}

		#### 승인/미승인 provider_status 0:기본 1:개별 @2013-08-12
		if ($sc['search_provider_status'] === '0' || $sc['search_provider_status'] === '1') {
			if($sc['search_provider_status'] === '0'){
				$whereStr['goods'] .= " AND (C.provider_status = '{$sc['search_provider_status']}' OR LENGTH(C.provider_status) <= 0)";

				if($sc['provider_status_reason_type']){
					if(!is_array($sc['provider_status_reason_type'])){
						$provider_status_reason_type[] = $sc['provider_status_reason_type'];
					}else{
						$provider_status_reason_type = $sc['provider_status_reason_type'];
					}
					foreach($provider_status_reason_type as $key => $seq){
						if($key == 0){
							$whereStr['goods'] .= " AND (";
						}

						if($key > 0){
							$whereStr['goods'] .= " OR ";
						}

						if($seq === "e"){
							$whereStr['goods'] .= "C.provider_status_reason_type > 3 OR C.provider_status_reason_type <= 0";
						} else {
							$whereStr['goods'] .= "C.provider_status_reason_type = '{$seq}'";
						}
					}
					$whereStr['goods'] .= ")";
				}
			} else {
				$whereStr['goods'] .= " AND C.provider_status = '{$sc['search_provider_status']}'";
			}
		}

		//동영상
		if( $sc['file_key_w'] ){
			$whereStr['file_key_w'] .= " AND ( C.file_key_w != '') ";// or file_key_w is not null

			if( !empty($sc['video_use']) && $sc['video_use'] !="전체" ){
				$whereStr['video_use'] .= " AND  C.video_use = '{$sc['video_use']}' ";
			}
		}

		//설명영역 동영상여부
		if($sc['videototal'])
			$whereStr['videototal'] .= " and ( C.videototal > 0 ) ";


		## 배송정책
		if($sc['ship_grp_seq'] && !$sc['shipping_group_seq']){ //배송정책으로 부터 링크 되어 넘어온 경우
			$sc['shipping_group_seq'] = $sc['ship_grp_seq'];
		}

		if ($sc['shipping_group_seq']) {
			if (is_array($sc['shipping_group_seq'])){
				$whereStr['goods'] .= " AND C.shipping_group_seq IN ? ";
				$bindData[] = $sc['shipping_group_seq'];
			}else{
				if($sc['shipping_group_seq'] == '-1'){
					$whereStr['goods'] .= " AND C.trust_shipping = 'Y' ";
				}else{
					$whereStr['goods'] .= " AND C.shipping_group_seq = ? ";
					$bindData[] = $sc['shipping_group_seq'];
				}
			}
		}

		if(is_array($sc['shipping_set_code']['domestic'])) $sc['shipping_set_code']['domestic'] = $sc['shipping_set_code']['domestic'][0];
		if(is_array($sc['shipping_set_code']['international'])) $sc['shipping_set_code']['international'] = $sc['shipping_set_code']['international'][0];

		## 배송방법
		if (count($sc['shipping_set_code']) && ( $sc['shipping_set_code']['domestic'] ||  $sc['shipping_set_code']['international'])) {
			$joinStr['shipping_group_summary'] = " INNER JOIN fm_shipping_group_summary S ON C.shipping_group_seq = S.shipping_group_seq";

			// 국내 배송방범 검색
			if (isset($sc['shipping_set_code']['domestic']) && $sc['shipping_set_code']['domestic']) {
				$whereStr['shipping_group_summary'] .= " AND (";
				$whereStr['shipping_group_summary'] .= "S.kr_{$sc['shipping_set_code']['domestic']}_yn = 'Y'";
				$whereStr['shipping_group_summary'] .= ")";
			}

			// 해외 배송방법 검색
			if (isset($sc['shipping_set_code']['international']) && $sc['shipping_set_code']['international']) {
				$whereStr['shipping_group_summary'] .= " AND (";
				$whereStr['shipping_group_summary'] .= "S.gl_{$sc['shipping_set_code']['international']}_yn = 'Y'";
				$whereStr['shipping_group_summary'] .= ")";
			}
		}

		##입점마켓
		if( $sc['search_feed_status'] ){
			if( !is_array($sc['search_feed_status']) ){
				$sc['search_feed_status']	= array($sc['search_feed_status']);
			}
			if( count($sc['search_feed_status']) == 1){
				if( in_array('Y', $sc['search_feed_status']) ){
					$whereStr['goods'] .= " AND C.feed_status = 'Y'";
				}elseif	( in_array('N', $sc['search_feed_status']) ){
					$whereStr['goods'] .= " AND C.feed_status != 'Y'";
				}
			}
		}

		## 중요상품 favorite_chk 0:체크 1:미체크
		if(is_array($sc['favorite_chk'])){
			if ($sc['favorite_chk'][0] && !$sc['favorite_chk'][1]) {
				$whereStr['goods'] .= " AND C.favorite_chk = 'checked'";
			} else if (!$sc['favorite_chk'][0] && $sc['favorite_chk'][1]) {
				$whereStr['goods'] .= " AND C.favorite_chk != 'checked'";
			}
		}elseif($sc['favorite_chk']){
			if($sc['favorite_chk'] == "none"){
				$whereStr['goods'] .= " AND C.favorite_chk != 'checked'";
			}else{
				$whereStr['goods'] .= " AND C.favorite_chk = ? ";
				$bindData[] = $sc['favorite_chk'];
			}
		}

		### 아이콘
		if( $sc['goodsIconCode'] ){
			foreach($sc['goodsIconCode'] as $icon => $icon_use){
				if($icon_use) $r_icon[] = $icon;
			}
		}

		### 선택한 아이콘 검색 2015-04-29
		if( $sc['select_search_icon'] ){
			$r_icon = explode(",", $sc['select_search_icon']);
		}

		if( $r_icon ){
			$joinStr['goods_icon'] = " INNER JOIN fm_goods_icon AS IC ON IC.goods_seq = C.goods_seq";
			$whereStr['goods_icon'] = " AND IC.codecd IN ('".join("', '", $r_icon)."')";
		}

		### 색상검색
		if (count($sc['color_pick']) > 0) {
			$whereStr['goods'] .= " AND (";
			foreach ($sc['color_pick'] as $k => $v) {
				if ($k > 0) {
					$whereStr['goods'] .= ' OR ';
				}
				$whereStr['goods'] .= "C.color_pick LIKE '%{$v}%'";
			}
			$whereStr['goods'] .= ")";
		}

		## 정산
		$commission = "";
		if($sc['commission_type_sel'] && $sc['commission_type_sel'] !== "전체"){
			$commission .= " AND (OP.commission_type = '{$sc['commission_type_sel']}')";
		}

		if ($sc['s_commission_rate'] && $sc['e_commission_rate']) {
			$commission .= " AND (OP.commission_rate between '{$sc['s_commission_rate']}' AND '{$sc['e_commission_rate']}')";
		} else if($sc['s_commission_rate']) {
			$commission .= " AND (OP.commission_rate >= '{$sc['s_commission_rate']}')";
		} else if($sc['e_commission_rate']) {
			$commission .= " AND (OP.commission_rate <= '{$sc['e_commission_rate']}')";
		}

		if($commission) {
			$whereStr['goods_option'] = $commission;
			$whereStr['goods'] .= " AND C.provider_seq 	> 1";
		}

		### 과세
		if( $sc['taxView'] && $sc['taxView'] == 'exempt'){
			$whereStr['goods'] .= " AND C.tax = '{$sc['taxView']}' ";
		} else if( $sc['taxView'] && $sc['taxView'] != 'exempt'){
			$whereStr['goods'] .= " AND C.tax != 'exempt' ";
		}

		### 상태
		if( is_array($sc['goodsStatus']) ){
			$whereStr['goods'] .= " AND C.goods_status in ('".join("', '", $sc['goodsStatus'])."') ";
		}elseif($sc['goodsStatus'] && $sc['goodsStatus'] != "all"){
			$whereStr['goods'] .= " AND C.goods_status ='". $sc['goodsStatus']."' ";
		}

		### 노출
		if ($sc['goodsView'] == 'auto') {
			$whereStr['goods'] .= " AND C.display_terms = 'AUTO'";
		} else if ($sc['goodsView']){
			if(is_array($sc['goodsView'])) {
				$goodsView = "AND C.goods_view in('".implode("',',",$sc['goodsView'])."')";
			}else{
				$goodsView = "AND C.goods_view = '{$sc['goodsView']}'";
			}
			$whereStr['goods'] .= " AND (C.display_terms = 'MENUAL' {$goodsView})";
		}
		##재고판매
		if(trim($sc['goods_runout']) != ""){
			$whereStr['goods'] .= " AND C.runout_policy =? ";
			$bindData[] = $sc['goods_runout'];
		}else{
			$runout_policy = array();
			if ($sc['sale_for_stock'] == 'stock'){
				$runout_policy[]	= 'stock';
			}

			if ($sc['sale_for_ableStock'] == 'ableStock'){
				$runout_policy[]	= 'ableStock';
			}

			if ($sc['sale_for_unlimited'] == 'unlimited'){
				$runout_policy[]	= 'unlimited';
			}

			if (count($runout_policy) > 0) {
				$whereStr['goods'] .= " AND C.runout_policy IN ('".join("', '", $runout_policy)."')";
			}

		}

		### 재고
		if($sc['stock_compare']){

			// 재고 수량 0도 검색 할 수 있도록 하기 위해 조건 수정 by hed #43283
			$is_sstock = ($sc['sstock']==0 && $sc['sstock']!='' || $sc['sstock']) ? true : false;
			$is_estock = ($sc['estock']==0 && $sc['estock']!='' || $sc['estock']) ? true : false;

			$stock_wheres = "";
			if($sc['stock_compare'] == 'less'){													// 안전재고보다 재고 부족
				$stock_wheres = " AND C.safe_stock_status='y'";
			}else if($sc['stock_compare'] == 'greater' && $is_sstock){							// 안전재고보다 몇개 많은
				$stock_wheres = " AND GS.stock < GS.safe_stock + {$sc['sstock']}";
			}else if($sc['stock_compare'] == 'stock'){											// 재고 항목, 검색시작갯수, 검색 종료갯수가 있을 때
				if($is_sstock) $stock_wheres = " AND GS.stock >= {$sc['sstock']}";
				if($is_estock) $stock_wheres .= " AND GS.stock <= {$sc['estock']}";
			}else if($sc['stock_compare'] == 'safe'){											// 안전재고 항목, 검색시작갯수, 검색 종료갯수가 있을 때
				if($is_sstock) $stock_wheres = " AND GS.safe_stock >= {$sc['sstock']}";
				if($is_estock) $stock_wheres .= " AND GS.safe_stock <= {$sc['estock']}";
			}

			// 재고관련 검색 조건(상세조건)이 있을 때 table join
			if($stock_wheres){
				$whereStr['goods'] .= $stock_wheres;

				$joinStr['goods_supply'] = " INNER JOIN fm_goods_supply as GS ON OP.option_seq = GS.option_seq";
				$groupBy = " GROUP BY C.goods_seq ";
			}

		}

		### 무게
		if( $sc['sweight'] && $sc['eweight'] ) {
			$whereStr['goods_option'] .= " AND OP.weight BETWEEN '{$sc['sweight']}' AND '{$sc['eweight']}'";
		} else if( $sc['eweight'] ) {
			$whereStr['goods_option'] .= " AND OP.weight <= '{$sc['eweight']}'";
		} else if( $sc['sweight'] ) {
			$whereStr['goods_option'] .= " AND OP.weight >= '{$sc['sweight']}'";
		}

		### PAGE_VIEW
		if( $sc['spage_view'] && $sc['epage_view'] ){
			$whereStr['goods'] .= " AND C.page_view BETWEEN '{$sc['spage_view']}' AND '{$sc['epage_view']}'";
		} else if( $sc['spage_view'] ){
			$whereStr['goods'] .= " AND C.page_view >= '{$sc['spage_view']}'";
		} else if( $sc['epage_view'] ){
			$whereStr['goods'] .= " AND C.page_view <= '{$sc['epage_view']}' ";
		}

		### 청약철회여부 0:가능 1:불가능
		if(is_array($sc['cancel_type'])){
			if( $sc['cancel_type'][0] !== '0' && $sc['cancel_type'][1] === '1' ) {
				$whereStr['goods'] .= " AND C.cancel_type = '1'";
			} else if(!$sc['cancel_type'][1] &&  $sc['cancel_type'][0] === '0' ){
				$whereStr['goods'] .= " AND (C.cancel_type = '0' or C.cancel_type is null)";
			}
		}elseif($sc['cancel_type']){
			if( $sc['cancel_type'] == "y"){
				$whereStr['goods'] .= " AND (C.cancel_type = '0' or C.cancel_type is null)";
			}else{
				$whereStr['goods'] .= " AND C.cancel_type = '1'";
			}
		}

		##예약 판매
		if( $sc['layaway_product'] == 'Y' ) {
			$whereStr['goods'] .= " AND (C.display_terms = 'AUTO'
			AND C.display_terms_type = 'LAYAWAY'
			AND C.display_terms_begin <= '{$today}'
			AND C.display_terms_end >= '{$today}')";
		}

		## 성인검색 추가 :: 2015-03-17 lwh
		if( is_array($sc["adult_goods"]) ){
			if ($sc["adult_goods"][0] && !$sc["adult_goods"][1]){
				$whereStr['goods'] .= " AND  C.adult_goods = 'N'";
			} else if (!$sc["adult_goods"][0] && $sc["adult_goods"][1]){
				$whereStr['goods'] .= " AND C.adult_goods = 'Y'";
			}
		}elseif($sc["adult_goods"] ){
			$whereStr['goods'] .= " AND  C.adult_goods = ? ";
			$bindData[] = $sc["adult_goods"];
		}

		## 해외구매대행
		if( $sc['search_option_international_shipping'] ){
			$whereStr['goods'] .= " AND C.option_international_shipping_status = ? ";
			$bindData[] = $sc['search_option_international_shipping'];
		}

		### 티켓상품그룹
		if( !empty($sc['social_goods_group']) && !empty($sc['social_goods_group_name']) )
		{
			$whereStr['goods'] .=  " AND C.social_goods_group = '{$sc['social_goods_group']}' ";
		}

		## 검색어
		if( $sc['keyword'] ) {
			// keyword 공백제거 후 검색하도록 수정 2020-06-04
			$trim_keyword 	= trim($sc['keyword']);
			$sc['keyword'] 	= str_replace(' ','',$trim_keyword);
			$keyword 		= $this->db->escape($sc['keyword']);
			$keyword_like 	= $this->db->escape('%'.$sc['keyword'].'%');


			$keywordStr['goods_name']	= "REPLACE(C.goods_name,' ','') like " . $keyword_like;
			$keywordStr['goods_seq']	= "C.goods_seq = ".$keyword;
			$keywordStr['goods_code']	= "C.goods_code like ".$keyword_like;
			$keywordStr['keyword']		= "REPLACE(C.keyword,' ','') like " . $keyword_like;
			$keywordStr['summary']		= "REPLACE(C.summary,' ','') like ".$keyword_like;
			$keywordStr['hscode']		= "REPLACE(C.hscode,' ','') like ".$keyword_like;

			if($sc['search_field']) $sc['search_type'] = $sc['search_field'];

			if($sc['search_type'] == "" || $sc['search_type'] == "all"){
				$whereStr['goods'] .= " AND (" . implode(' or ',$keywordStr) . ")";
			}else{
				$whereStr['goods'] .= " AND " . $keywordStr[$sc['search_type']];
			}
		}

		## 검색어 설명 문구
		$arr_search_type = array(
				'all'			=>'전체검색',
				'goods_name'	=>'상품명',
				'goods_seq'		=>'상품 고유값',
				'goods_code'	=>'상품코드',
				'keyword'		=>'태그',
				'summary'		=>'간략설명',
				'hscode'		=>'수출입상품코드'
		);

		if( $sc['search_type'] ){
			$sc['search_type_text'] = sprintf("%s : %s", $arr_search_type[$sc['search_type']], $keyword);
		}

		if( (defined("PACKAGEUSE") == true) || defined("SOCIALCPUSE") === true) {
			//패키지 상품
			if( defined("PACKAGEUSE") == true ) {
				//(패키지상품 옵션 연결용 검색 아닐때)
				if( $sc['searchType'] != 'packageGoods') {
					$whereStr['goods'] .= " AND C.package_yn = 'y' ";
					$countWhereStr['goods'] .= " AND C.package_yn = 'y' ";
				}else{
					$whereStr['goods'] .= " AND C.package_yn = 'n' ";
					$countWhereStr['goods'] .= " AND C.package_yn = 'n' ";
				}
			}

			//티켓상품
			if( defined("SOCIALCPUSE") === true) {
				$whereStr['goods'] 		.= " AND C.goods_kind = 'coupon' ";
				$countWhereStr['goods'] .= " AND C.goods_kind = 'coupon' ";
			}

		}else{
			if($sc['goodsKind'] != "all"){
				switch($sc['goodsKind']){
					case "coupon": 		// 티켓 상품
						$whereStr['goods'] 		.= " AND C.goods_kind = 'coupon' ";
						$countWhereStr['goods'] .= " AND C.goods_kind = 'coupon' ";
					break;
					case "package": 	// 패키지 상품
						$whereStr['goods']		.= " AND C.goods_kind = 'goods' ";
						$countWhereStr['goods'] .= " AND C.goods_kind = 'goods' ";
						$whereStr['goods'] 		.= " AND C.package_yn = 'y' ";
						$countWhereStr['goods'] .= " AND C.package_yn = 'y' ";
					break;
					case "goods": 		// 일반 상품
						$whereStr['goods']		.= " AND C.goods_kind = 'goods' ";
						$countWhereStr['goods'] .= " AND C.goods_kind = 'goods' ";
						$whereStr['goods'] 		.= " AND C.package_yn = 'n' ";
						$countWhereStr['goods'] .= " AND C.package_yn = 'n' ";
					break;
					default:			// 기본 검색 : 일반 + 패키지
						$whereStr['goods']		.= " AND C.goods_kind = 'goods' ";
						$countWhereStr['goods'] .= " AND C.goods_kind = 'goods' ";
					break;
				}
			}
		}

		//사은품
		if( $sc['goods_type'] ){
			$whereStr['goods'] .= " AND C.goods_type = '{$sc['goods_type']}' ";
			$countWhereStr['goods'] .= " AND C.goods_type = '{$sc['goods_type']}' ";
		}

		// 선물하기
		if( in_array($sc['present_chk'],['0','1']) ){
			$whereStr['goods'] .= " AND C.present_use = '{$sc['present_chk']}' ";
		}

		// 관리자 주문시 사은품은 제외
		if($sc['adminOrder'] == "Y"){
			$whereStr['goods'] .= " AND C.goods_type != 'gift'";
			$countWhereStr['goods'] .= " AND C.goods_type != 'gift'";
		}

		// LIMIT 키워드 삭제
		if($sc['all_rows'] == 'Y')
		{
		    $limitStr = "";
		}

		### 쿼리 정리
		$where	= '';
		$join = $countTable = " fm_goods AS C STRAIGHT_JOIN fm_goods_option AS OP USE INDEX (`fk_fm_goods_option_fm_goods`) ON C.goods_seq = OP.goods_seq";

		### 상품 번호 절대 검색 ( 상품 번호 절대 검색 시 위에서 만든 검색 조건은 모두 무시함 )
		if( is_array($sc['abs_goods_seq']) && count($sc['abs_goods_seq']) > 0 ) {
			$where	= " WHERE C.goods_seq in ('".implode("', '", $sc['abs_goods_seq'])."') ";
		} else {
			$where	= " WHERE OP.default_option = 'y'"; //대표옵션상품만 노출
			$countWhere = " OP.default_option = 'y'"; //대표옵션상품만 노출
			if($countWhereStr) $countWhere .= implode(" AND ",$countWhereStr);
			foreach($whereStr as $tableName => $wheres){
				$where .= $wheres;
			}

			foreach($joinStr as $tableName => $joins){
				$join .= $joins;
			}
		}

		if($sc['excel_spout']){
			$sql = " FROM ".$this->db->compile_binds($join . $where, $bindData);
			return $sql;
		}

		if($sc['batch_mode']){
			$sql = "SELECT C.goods_seq FROM ".$join . $where;
			$sql = $this->db->compile_binds($sql, $bindData);
			return $sql;
		}

		/* search */
		$search_field		= "C.*,
								CASE WHEN C.goods_status = 'unsold' THEN  '판매중지'
								WHEN C.goods_status = 'purchasing' THEN  '재고확보중'
								WHEN C.goods_status = 'runout' THEN  '품절'
								ELSE '정상' END AS goods_status_text";

		$sql				= array();
		$sql['field']		= $search_field;
		$sql['table']		= $join;
		$sql['countTable']	= $countTable;
		$sql['wheres']		= $where;
		$sql['countWheres']	= $countWhere;
		$sql['groupby']		= $groupBy;
		$sql['orderby']		= $orderStr;
		$sql['limit']		= $limitStr;
		$sc['debug']		= 1;

		$result				= pagingNumbering($sql, $sc, true, $bindData);
		//debug($sql);
		//debug($result['query']);
		if(count($result['record']) > 0){

			//배송 관련 데이터
			$shippingInfo = array();
			$shippingSQL = $this->db->query("SELECT shipping_group_seq, shipping_group_name FROM fm_shipping_grouping")->result_array();
			foreach($shippingSQL as $k => $v){
				$shippingInfo[$v['shipping_group_seq']] = $v['shipping_group_name'];
			}

			foreach($result['record'] as $key => $val ){
				$result['record'][$key]['shipping_group_name']	= $shippingInfo[$val['shipping_group_seq']];
				if(serviceLimit('H_AD')){ //입점형
					if( $val['provider_seq'] == 1 ){ //본사상품
						$result['record'][$key]['provider_name'] = '본사';
					} else {
						$result['record'][$key]['provider_name'] = $provider_list[$val['provider_seq']]['provider_name'];
					}
				}

				if( $provider_list[$val['provider_seq']]['commission_type'] ){
					$result['record'][$key]['default_commission_type'] = $provider_list[$val['provider_seq']]['commission_type'];
				} else {
					$result['record'][$key]['default_commission_type'] = 'SACO';
				}

				//예약 상품의 경우 문구를 넣어준다 2016-11-07
				$result['record'][$key]['goods_name'] = get_goods_pre_name($val,true);
			}
		}

		return $result;
	}

	public function goods_addition_list($type) {
		$sql = "select distinct A.contents, A.* from fm_goods_addition A where A.type = '{$type}' group by A.contents order by A.addition_seq desc";
		$query = $this->db->query($sql);
		$data = $query->result_array();
		return $data;
	}


	public function goods_addition_list_all(){
		$sql = "select distinct A.contents, A.*  from fm_goods_addition A where A.type != 'direct' group by A.contents,A.type ";
		$query = $this->db->query($sql);
		foreach($query->result_array() as $row){
			$result[$row['type']][] = $row;
		}
		return $result;
	}

	###
	public function delete_goods($goodSeq){
		if	(!$this->scm_cfg)	$this->scm_cfg	= config_load('scm');

		$data = $this->get_goods($goodSeq);

		// 본사상품인 경우에만 재고 체크해 삭제처리한다.
		if	($this->scm_cfg['use'] == 'Y' && $data['provider_seq'] == 1){
			// 재고 체크 후 재고가 없는 경우만 삭제 가능
			$sql	= "select sum(total_stock) as total_stock from fm_goods_supply
						where goods_seq = ? group by goods_seq";
			$query	= $this->db->query($sql, array($goodSeq));
			$data	= $query->row_array();
			if	($data['total_stock'] > 0){
				return array('status' => false, 'msg' => '재고가 존재하는 상품은 삭제할 수 없습니다.');
			}
		}

		### DEFAULT
		$result = $this->db->delete('fm_category_link', array('goods_seq' => $goodSeq));
		$result = $this->db->delete('fm_goods_addition', array('goods_seq' => $goodSeq));
		$result = $this->db->delete('fm_goods_icon', array('goods_seq' => $goodSeq));
		$result = $this->db->delete('fm_goods_input', array('goods_seq' => $goodSeq));
		$result = $this->db->delete('fm_goods_option', array('goods_seq' => $goodSeq));
		$result = $this->db->delete('fm_goods_relation', array('goods_seq' => $goodSeq));
		$result = $this->db->delete('fm_goods_relation_seller', array('goods_seq' => $goodSeq));
		$result = $this->db->delete('fm_goods_suboption', array('goods_seq' => $goodSeq));
		$result = $this->db->delete('fm_goods_supply', array('goods_seq' => $goodSeq));

		//동영상삭제
		$result = $this->db->delete('fm_videofiles', array('upkind' => 'goods','parentseq' => $goodSeq));

		// qrcode 이미지 삭제
		$domain = !empty($this->config_system['domain']) ? $this->config_system['domain'] : $this->config_system['sub_domain'];
		$domain = $domain ? $domain : $_SERVER['HTTP_HOST'];
		for($i=1;$i<=10;$i++){
			if(file_exists(ROOTPATH."data/qrcode/qrcode_".md5("http://{$domain}/goods/view?no={$goodSeq}"."|".$i).".png")){
				@unlink(ROOTPATH."data/qrcode/qrcode_".md5("http://{$domain}/goods/view?no={$goodSeq}"."|".$i).".png");
			}
		}

		### IMAGE
		$this->db->where('goods_seq', $goodSeq);
		$query = $this->db->get('fm_goods_image');
		foreach($query->result_array() as $data){
			###
			if(isset($data['image'])){
				$target = ".".$data['image'];
				$result = unlink($target);
			}
			$result = $this->db->delete('fm_goods_image', array('image_seq' => $data['image_seq']));
		}
		$result = $this->db->delete('fm_goods_image', array('goods_seq' => $goodSeq));

		//미사용 티켓 삭제 2015-07-01 jhr #835
		$result = $this->db->delete('fm_goods_coupon_serial', array('goods_seq' => $goodSeq, 'export_date' => NULL));

		$result = $this->db->delete('fm_goods', array('goods_seq' => $goodSeq));
		return $result;
	}


	###
	public function copy_goods($old_goods_seq){

		$now = date("Y-m-d H:i:s");
		$fields = "provider_seq, view_layout, goods_status,cancel_type, sale_seq, provider_status, goods_view, favorite_chk,
						goods_code, goods_name, purchase_goods_name, summary, keyword, contents, mobile_contents, info_seq,
						common_contents, string_price_use,string_price, tax, multi_discount_use, multi_discount_ea, multi_discount,
						multi_discount_unit, min_purchase_limit,min_purchase_ea, max_purchase_limit, max_purchase_order_limit,
						max_purchase_ea, reserve_policy, option_use,option_view_type, option_suboption_use, member_input_use,
						shipping_policy, goods_shipping_policy, unlimit_shipping_price,	limit_shipping_ea, limit_shipping_price,
						limit_shipping_subprice, shipping_weight_policy, goods_weight, relation_type, admin_memo,
						goods_type, goods_sub_info, sub_info_desc, goods_kind, socialcp_event,	socialcp_input_type,
						socialcp_use_return, socialcp_use_emoney_day, socialcp_use_emoney_percent,social_goods_group,
						socialcp_cancel_type,socialcp_cancel_use_refund,socialcp_cancel_payoption,socialcp_cancel_payoption_percent,
						provider_status_reason_type,provider_status_reason,adult_goods,hscode,option_international_shipping_status,
						openmarket_keyword,relation_criteria,relation_seller_criteria,relation_seller_type,auto_condition_use,bigdata_criteria,package_yn,
						color_pick, display_terms, display_terms_begin, display_terms_end, display_terms_type, display_terms_text, display_terms_color,
						display_terms_before, sub_reserve_policy, multi_discount_policy,
						string_price_color, member_string_price, member_string_price_color, allmember_string_price_color, string_button_link_target,
						member_string_button_link_target, allmember_string_button_link_target, string_button_use, string_button, string_button_color, string_button_link, string_button_link_url, string_price_link, string_price_link_target, member_string_price_use, member_string_button_use, member_string_button, member_string_button_color, member_string_button_link, member_string_button_link_url, member_string_price_link, member_string_price_link_url, member_string_price_link_target, allmember_string_button_use, allmember_string_button, allmember_string_button_color, allmember_string_button_link, allmember_string_button_link_url, allmember_string_price_use, allmember_string_price, allmember_string_price_link, allmember_string_price_link_url, allmember_string_price_link_target, shipping_group_seq, trust_shipping, possible_pay_type, possible_pay, possible_mobile_pay, goods_name_linkage, suboption_layout_group, suboption_layout_position, inputoption_layout_group, inputoption_layout_position, mobile_contents_copy, package_yn_suboption, runout_policy, able_stock_limit, individual_refund, individual_refund_inherit, individual_export, individual_return, frequentlyopt, frequentlysub, frequentlyinp, coupon_serial_type, pc_mapview, m_mapview, present_use";

		// 입점마케팅 설정 관련 필드 추가
		$fields .= ", feed_status, feed_goods_use, feed_goods_name, feed_condition, product_flag, installation_costs, compound_state, feed_evt_sdate, feed_evt_edate, feed_evt_text, feed_ship_type, feed_pay_type, feed_add_txt, feed_std_fixed";

		if	(!$this->scm_cfg)	$this->scm_cfg	= config_load('scm');
		if	($this->scm_cfg['use'] == 'Y'){
			// oldSeq 로 provider_seq 검색
			$sql = "SELECT provider_seq FROM fm_goods WHERE goods_seq=?;";
			$query = $this->db->query($sql,$old_goods_seq);
			$row  = $query->row_array();
			// 올인원인경우 입점사 상품은 업데이트
			if($row['provider_seq'] > 1) $fields	.= ",tot_stock";
		} else {
			$fields	.= ",tot_stock";
		}
		$select_fields = $fields;

		if( defined('__SELLERADMIN__') === true ) { //입점사 -> 미승인처리, 판매중지처리
			$select_fields = str_replace(array('goods_status','provider_status'),array('\'unsold\'','\'0\''),$select_fields);
		}

		$sql = "INSERT INTO fm_goods
					(".$fields.", regist_date )
				SELECT
					".$select_fields.", '{$now}'
				FROM
					fm_goods
				WHERE
					goods_seq = '{$old_goods_seq}'";

		$result = $this->db->query($sql);
		$goods_seq = $this->db->insert_id();

		// 검색어 치환
		$query = "UPDATE fm_goods set keyword=REPLACE(keyword,?,?) WHERE goods_seq=?";
		$this->db->query($query,array($old_goods_seq,$goods_seq,$goods_seq));

		return $goods_seq;
	}

	###
	public function copy_goods_default($table, $oldSeq, $goodSeq, $unset_seq){
		$this->db->where('goods_seq', $oldSeq);
		$query = $this->db->get($table);
		foreach($query->result_array() as $data){
			$params = filter_keys($data, $this->db->list_fields($table));
			unset($params[$unset_seq]);
			if(isset($params['regist_date'])) $params['regist_date'] = date("Y-m-d H:i:s");
			$params['goods_seq'] = $goodSeq;
			$params	= $this->copy_goods_exception($table, $params);
			$result = $this->db->insert($table, $params);
		}
		return $result;
	}

	// 정렬순서 복사
	public function copy_goods_exception($table, $params){
		switch($table){
			case 'fm_category_link':
				$this->load->model('categorymodel');
				$minsort				= $this->categorymodel->getSortValue($params['category_code'], 'min');
				$mobile_minsort			= $this->categorymodel->getSortValue($params['category_code'], 'mobile_min');
				$params['sort']			= $minsort - 1;
				$params['mobile_sort']	= $mobile_minsort - 1;
			break;
			case 'fm_brand_link':
				$this->load->model('brandmodel');
				$minsort				= $this->brandmodel->getSortValue($params['category_code'], 'min');
				$mobile_minsort			= $this->brandmodel->getSortValue($params['category_code'], 'mobile_min');
				$params['sort']			= $minsort - 1;
				$params['mobile_sort']	= $mobile_minsort - 1;
			break;
			case 'fm_location_link':
				$this->load->model('locationmodel');
				$minsort				= $this->locationmodel->getSortValue($params['location_code'], 'min');
				$mobile_minsort			= $this->locationmodel->getSortValue($params['category_code'], 'mobile_min');
				$params['sort']			= $minsort - 1;
				$params['mobile_sort']	= $mobile_minsort - 1;
			break;
		}

		return $params;
	}

	public function copy_goods_option($oldSeq, $goodsSeq){

		if	(!$this->scm_cfg)	$this->scm_cfg	= config_load('scm');

		if	($this->scm_cfg['use'] == 'Y'){
			// 올인원인 경우 본사만 재고 복사 안되도록 추가 2020-01-16
			$supply_update = false;
			// oldSeq 로 provider_seq 검색
			$sql = "SELECT provider_seq FROM fm_goods WHERE goods_seq=?;";
			$query = $this->db->query($sql,$oldSeq);
			$row  = $query->row_array();
			if($row['provider_seq'] > 1) $supply_update = true;
		}

		### OPTION
		$sql = "SELECT distinct A.*, B.* FROM fm_goods_option A LEFT JOIN fm_goods_supply B ON A.option_seq = B.option_seq WHERE A.goods_seq = '{$oldSeq}' AND B.goods_seq = '{$oldSeq}' AND B.option_seq is not null;";
		$query = $this->db->query($sql);
		foreach($query->result_array() as $data){
			$oparams['goods_seq']		= $goodsSeq;
			$oparams['default_option']	= $data['default_option'];
			$oparams['option_title']	= $data['option_title'];
			$oparams['option1']			= $data['option1'];
			$oparams['option2']			= $data['option2'];
			$oparams['option3']			= $data['option3'];
			$oparams['option4']			= $data['option4'];
			$oparams['option5']			= $data['option5'];

			$oparams['option_type']			= $data['option_type'];
			$oparams['code_seq']				= $data['code_seq'];
			$oparams['optioncode1']			= $data['optioncode1'];
			$oparams['optioncode2']			= $data['optioncode2'];
			$oparams['optioncode3']			= $data['optioncode3'];
			$oparams['optioncode4']			= $data['optioncode4'];
			$oparams['optioncode5']			= $data['optioncode5'];

			// 패키지 정보 복사
			$oparams['package_count']			= $data['package_count'];
			$oparams['package_goods_name1']			= $data['package_goods_name1'];
			$oparams['package_goods_name2']			= $data['package_goods_name2'];
			$oparams['package_goods_name3']			= $data['package_goods_name3'];
			$oparams['package_goods_name4']			= $data['package_goods_name4'];
			$oparams['package_goods_name5']			= $data['package_goods_name5'];
			$oparams['package_option_seq1']			= $data['package_option_seq1'];
			$oparams['package_option_seq2']			= $data['package_option_seq2'];
			$oparams['package_option_seq3']			= $data['package_option_seq3'];
			$oparams['package_option_seq4']			= $data['package_option_seq4'];
			$oparams['package_option_seq5']			= $data['package_option_seq5'];
			$oparams['package_option1']			= $data['package_option1'];
			$oparams['package_option2']			= $data['package_option2'];
			$oparams['package_option3']			= $data['package_option3'];
			$oparams['package_option4']			= $data['package_option4'];
			$oparams['package_option5']			= $data['package_option5'];
			$oparams['package_unit_ea1']			= $data['package_unit_ea1'];
			$oparams['package_unit_ea2']			= $data['package_unit_ea2'];
			$oparams['package_unit_ea3']			= $data['package_unit_ea3'];
			$oparams['package_unit_ea4']			= $data['package_unit_ea4'];
			$oparams['package_unit_ea5']			= $data['package_unit_ea5'];

			$oparams['tmpprice']							= $data['tmpprice'];
			$oparams['color']								= trim($data['color']);
			$oparams['zipcode']							= $data['zipcode'];
			$oparams['address_type']					= $data['address_type'];
			$oparams['address']							= $data['address'];
			$oparams['address_street']					= $data['address_street'];
			$oparams['addressdetail']					= $data['addressdetail'];
			$oparams['biztel']								= $data['biztel'];
			$oparams['address_commission']		= $data['address_commission'];
			$oparams['newtype']							= $data['newtype'];

			$oparams['coupon_input']		= $data['coupon_input'];//티켓상품의 1장값어치 횟수-금액
			$oparams['codedate']				= $data['codedate'];
			$oparams['sdayinput']			= $data['sdayinput'];
			$oparams['fdayinput']				= $data['fdayinput'];
			$oparams['dayauto_type']		= $data['dayauto_type'];
			$oparams['sdayauto']				= $data['sdayauto'];
			$oparams['fdayauto']				= $data['fdayauto'];
			$oparams['dayauto_day']		= $data['dayauto_day'];
			$oparams['commission_rate']	= $data['commission_rate'];
			$oparams['commission_type']	= $data['commission_type'];

			$oparams['consumer_price']		= $data['consumer_price'];
			$oparams['price']						= $data['price'];
			$oparams['reserve_rate']			= $data['reserve_rate'];
			$oparams['reserve_unit']			= $data['reserve_unit'];
			$oparams['reserve']					= $data['reserve'];
			$oparams['infomation']					= $data['infomation'];
			$oparams['weight']					= $data['weight'];

			/*추가 option_view 노출 복사 2017-08-23 ldb*/
			$oparams['option_view']					= $data['option_view'];

			//색상옵션일때 값이 없으면 기본 흰색으로 저장합니다. @2016-07-27 ysm 1
			if( strstr($oparams['newtype'],'color') && !$oparams['color'] ) $oparams['color'] = '#fff';

			$result = $this->db->insert('fm_goods_option', $oparams);
			$option_seq = $this->db->insert_id();
			$sparams['goods_seq']		= $goodsSeq;
			$sparams['option_seq']		= $option_seq;

			if	($this->scm_cfg['use'] == 'Y' && $supply_update == false){
				$sparams['supply_price']	= '0';
				$sparams['exchange_rate']		= '0';
				$sparams['stock']				= '0';
			}else{
				$sparams['supply_price']		= $data['supply_price'];
				$sparams['exchange_rate']		= $data['exchange_rate'];
				$sparams['stock']				= $data['stock'];
				$sparams['badstock']			= $data['badstock']; //추가 201708 ldb
				$sparams['safe_stock']			= $data['safe_stock']; // 추가 201708 ldb
			}
			$result = $this->db->insert('fm_goods_supply', $sparams);
		}
		unset($oparams);
		unset($sparams);
		### SUBOPTION
		$sql = "SELECT A.suboption_seq, A.*, B.* FROM fm_goods_suboption A LEFT JOIN fm_goods_supply B ON A.suboption_seq = B.suboption_seq WHERE A.goods_seq = '{$oldSeq}' AND B.goods_seq = '{$oldSeq}' AND B.suboption_seq is not null;";
		$query = $this->db->query($sql);
		foreach($query->result_array() as $data){
			$oparams['goods_seq']			= $goodsSeq;

			$oparams['suboption_title']		= $data['suboption_title'];
			$oparams['sub_required']		= $data['sub_required'];
			$oparams['sub_sale']			= $data['sub_sale'];

			$oparams['suboption_type']		= $data['suboption_type'];
			$oparams['suboption_code']		= $data['suboption_code'];
			$oparams['code_seq']			= $data['code_seq'];

			$oparams['color']				= trim($data['color']);
			$oparams['zipcode']				= $data['zipcode'];
			$oparams['address_type']		= $data['address_type'];
			$oparams['address']				= $data['address'];
			$oparams['address_street']		= $data['address_street'];
			$oparams['addressdetail']		= $data['addressdetail'];
			$oparams['biztel']				= $data['biztel'];
			$oparams['newtype']				= $data['newtype'];

			$oparams['coupon_input']		= $data['coupon_input'];//티켓상품의 1장값어치 횟수-금액
			$oparams['codedate']			= $data['codedate'];
			$oparams['sdayinput']			= $data['sdayinput'];
			$oparams['fdayinput']			= $data['fdayinput'];
			$oparams['dayauto_type']		= $data['dayauto_type'];
			$oparams['sdayauto']			= $data['sdayauto'];
			$oparams['fdayauto']			= $data['fdayauto'];
			$oparams['dayauto_day']			= $data['dayauto_day'];
			$oparams['commission_rate']		= $data['commission_rate'];
			$oparams['commission_type']		= $data['commission_type'];

			// 패키지 정보 복사
			$oparams['package_count']			= $data['package_count'];
			$oparams['package_goods_name1']			= $data['package_goods_name1'];
			$oparams['package_option_seq1']			= $data['package_option_seq1'];
			$oparams['package_option1']			= $data['package_option1'];
			$oparams['package_unit_ea1']			= $data['package_unit_ea1'];
			$oparams['suboption']				= $data['suboption'];
			$oparams['consumer_price']	= $data['consumer_price'];
			$oparams['price']						= $data['price'];
			$oparams['reserve_rate']		= $data['reserve_rate'];
			$oparams['reserve_unit']			= $data['reserve_unit'];
			$oparams['reserve']					= $data['reserve'];

			/*추가 option_view 노출 복사 2017-08-23 ldb*/
			$oparams['option_view']					= $data['option_view'];

			$result = $this->db->insert('fm_goods_suboption', $oparams);
			$suboption_seq = $this->db->insert_id();
			$sparams['goods_seq']		= $goodsSeq;
			$sparams['suboption_seq']	= $suboption_seq;
			if	($this->scm_cfg['use'] == 'Y' && $supply_update == false){
				$sparams['supply_price']		= '0';
				$sparams['exchange_rate']		= '0';
				$sparams['stock']				= '0';
			}else{
				$sparams['supply_price']		= $data['supply_price'];
				$sparams['exchange_rate']		= $data['exchange_rate'];
				$sparams['stock']				= $data['stock'];
				$sparams['badstock']			= $data['badstock']; //추가 201708 ldb
				$sparams['safe_stock']			= $data['safe_stock']; //추가 201708 ldb
			}
			$result = $this->db->insert('fm_goods_supply', $sparams);
		}
		return $result;
	}


	###
	public function copy_goods_image($table, $oldSeq, $goodSeq, $unset_seq){
		$this->db->where('goods_seq', $oldSeq);
		$query = $this->db->get($table);
		$cnt = 0;
		foreach($query->result_array() as $data){
			$params = filter_keys($data, $this->db->list_fields($table));
			unset($params[$unset_seq]);
			if(isset($params['regist_date'])) $params['regist_date'] = date("Y-m-d H:i:s");
			$params['goods_seq'] = $goodSeq;

			// 이미지 복사
			// #30318 외부 링크 이미지 일 경우 그대로 복사 19.03.13 kmj
			if (!preg_match('/^(http:\/\/www\.|https:\/\/www\.|http:\/\/|https:\/\/)?[a-z0-9]+([\-\.]{1}[a-z0-9]+)*\.[a-z]{2,5}(:[0-9]{1,5})?(\/.*)?$/', $params['image'])) {
				$target = $this->clone_image($params['image'], $cnt, $goodSeq.'_');
				$params['image'] = $target;
			}

			$result = $this->db->insert($table, $params);
			$cnt++;
		}
		return $result;
	}


	public function clone_image($file, $idx, $prefix=''){
		$dir = $this->upload_goodsImage_dir();
		$ext = end(explode('.', $file));
		$filenm	= $prefix.date('YmdHis').$idx.rand(0,9);
			if(strpos($file, ".")!=false){
			$target = $dir.'/'.$filenm.'.'.$ext;
		}else{
			$target = $dir.'/'.$filenm;
		}
		$result = copy(ROOTPATH.$file, ROOTPATH.$target);
		return $target;
	}

	// 옵션재고
	public function stock_option($mode,$ea,$goods_seq,$option1,$option2,$option3='',$option4='',$option5='',$export_mode=false){

		$params['goods_seq']	= $goods_seq;
		$params['option1']		= $option1;
		$params['option2']		= $option2;
		$params['option3']		= $option3;
		$params['option4']		= $option4;
		$params['option5']		= $option5;
		$query_option = $this->get_option($params);
		$data_option = $query_option->row_array();
		if($mode == '+'){
			$query = "
			update fm_goods_supply set stock = stock + ? where option_seq = ?";
			$this->db->query($query,array($ea,$data_option['option_seq']));
			if( $data_option['package_count'] ){
				for($i=1;$i<=$data_option['package_count'];$i++){
					$p_opt_seq = $data_option['package_option_seq'.$i];
					$p_unit_ea = $data_option['package_unit_ea'.$i];
					$p_ea = $ea * $p_unit_ea;
					$query = "update fm_goods_supply set stock = stock + ? where option_seq = ?";
					$this->db->query($query,array($p_ea,$p_opt_seq));
				}
			}
		}

		if($mode == '-'){
			if($export_mode){
				$query = "
				update fm_goods_supply set stock = stock - IF(stock>=?,?,stock),reservation15 = reservation15 - ?,reservation25 = reservation25 - ? where option_seq = ?";
				$this->db->query($query,array($ea,$ea,$ea,$ea,$data_option['option_seq']));
				if( $data_option['package_count'] ){
					for($i=1;$i<=$data_option['package_count'];$i++){
						$p_opt_seq = $data_option['package_option_seq'.$i];
						$p_unit_ea = $data_option['package_unit_ea'.$i];
						$p_ea = $ea * $p_unit_ea;
						$query = "update fm_goods_supply set stock = stock - IF(stock>=?,?,stock),reservation15 = reservation15 - ?,reservation25 = reservation25 - ? where option_seq = ?";
						$this->db->query($query,array($p_ea,$p_ea,$p_ea,$p_ea,$p_opt_seq));
					}
				}
			}else{
				$query = "
				update fm_goods_supply set stock = stock - IF(stock>=?,?,stock) where option_seq = ?";
				$this->db->query($query,array($ea,$ea,$data_option['option_seq']));
				if( $data_option['package_count'] ){
					for($i=1;$i<=$data_option['package_count'];$i++){
						$p_opt_seq = $data_option['package_option_seq'.$i];
						$p_unit_ea = $data_option['package_unit_ea'.$i];
						$p_ea = $ea * $p_unit_ea;
						$query = "update fm_goods_supply set stock = stock - IF(stock>=?,?,stock) where option_seq = ?";
						$this->db->query($query,array($p_ea,$p_ea,$p_opt_seq));
					}
				}
			}
		}

		if(!$export_mode){
			$this->runout_check($goods_seq);
		}

		// 재고 차감에 따른 다중판매처 상품정보 전달
		$this->load->model('openmarketmodel');
		$this->openmarketmodel->request_send_goods($goods_seq);
	}


	// 재고 총 수량
	public function total_stock($goods_seq){
		$safe_out = 'n';
		$option_seq = array();
		$query = "select
						o.*,
						s.stock,
						s.safe_stock
					from
						fm_goods_option as o
						left join fm_goods_supply as s on o.option_seq=s.option_seq
					where
						o.goods_seq=?";
		$res	= $this->db->query($query,array($goods_seq));
		foreach($res->result_array() as $data){
			// 패키지 상품의 재고 가져오기
			if($data['package_option_seq1'])
			{
				for($i=1;$i<6;$i++)
				{
					if($data['package_option_seq'.$i])
					{
						$data_package = $this->get_package_by_option_seq($data['package_option_seq'.$i]);
						$data['package_goods_seq'.$i] = $data_package['package_goods_seq'];
						$data['package_stock'.$i] = $data_package['package_stock'];
						$data['package_badstock'.$i] = $data_package['package_badstock'];
						$data['package_ablestock'.$i] = $data_package['package_ablestock'];
						$data['package_safe_stock'.$i] = $data_package['package_safe_stock'];

						if(!$data_min || $data_min['package_stock'] > $data_package['package_stock'])
						{
							$data_min = $data_package;
						}

						if( $data_package['package_safe_stock'] && ($data_package['package_stock'] < $data_package['package_safe_stock']) )
						{
							$safe_out = 'y';
						}
					}
				}
				if($data_min)
				{
					$data['rstock'] = (int) $data_min['package_ablestock'];
					$data['badstock'] = (int) $data_min['package_badstock'];
					$data['stock'] = (int) $data_min['package_stock'];
					$data['reservation15'] = (int) $data_min['package_reservation15'];
					$data['reservation25'] = (int) $data_min['package_reservation25'];
					$data['safe_stock'] = (int) $data_min['package_safe_stock'];
				}
			}
			else
			{
				if($data['safe_stock'] && ($data['stock'] < $data['safe_stock']) )
				{
					$safe_out = 'y';
				}
				$option_seq[] = $data['option_seq'];
			}
			$total += $data['stock'];
		}

		$query = "update fm_goods set tot_stock=?, safe_stock_status=? where goods_seq=?";
		$result = $this->db->query($query,array($total, $safe_out, $goods_seq));

		// 현재 상품의 옵션이 다른 패키지 상품에 연결되어있는지 체크
		if(count($option_seq) > 0){
			foreach($option_seq as $seq) {
				$query = $this->db->select("goods_seq")->from("fm_goods_option")->or_where('package_option_seq1', $seq)
						->or_where('package_option_seq2', $seq)
						->or_where('package_option_seq3', $seq)
						->or_where('package_option_seq4', $seq)
						->or_where('package_option_seq5', $seq);
				$query = $query->get();
				// 다른 패키지 상품의 옵션이라면 해당 패키지 상품도 재고체크 다시 계산
				foreach($query->result_array() as $package_goods){
					// 동일한 상품은 한번만 실행
					if(!in_array($package_goods['goods_seq'], $search_goods)) {
						$package_safe_stock = "n";
						$search_goods[] = $package_goods['goods_seq'];
						// 패키지 상품을 가져와서 패키지에 연결된 옵션을 검색함
						$subquery = "select
									o.*,
									s.stock,
									s.safe_stock
								from
									fm_goods_option as o
									left join fm_goods_supply as s on o.option_seq=s.option_seq
								where
									o.goods_seq=?";
						$res	= $this->db->query($subquery,array($package_goods['goods_seq']));
						foreach($res->result_array() as $data){

							for($i=1;$i<6;$i++)
							{
								if($data['package_option_seq'.$i])
								{
									$data_package = $this->get_package_by_option_seq($data['package_option_seq'.$i]);
									if( $data_package['package_safe_stock'] && ($data_package['package_stock'] < $data_package['package_safe_stock']) )
									{
										$package_safe_stock = "y";
									}
								}
							}
						}
						$this->db->where("goods_seq", $package_goods['goods_seq'])->update("fm_goods", array('safe_stock_status' => $package_safe_stock));
					}
				}
			}
		}
	}

	// 재고 총 수량 일괄 처리 (total_stock 함수를 속도 개선)
	public function total_stock_multi($goods_seq_arr){
		if(empty($goods_seq_arr)) return;

		// 기본값 설정
		$safe_out = 'n';
		$option_seq = array();

		// 쿼리빌더사용
		// 상품 옵션의 재고 정보 조회
		$this->db->select('o.*, s.stock, s.safe_stock');
		$this->db->from('fm_goods_option as o');
		$this->db->join('fm_goods_supply as s', 'o.option_seq = s.option_seq', 'left');
		$this->db->where_in('o.goods_seq', $goods_seq_arr);
		$result = $this->db->get()->result_array();

		// 상품 옵션 정보 가공
		foreach($result as $data){

			// 패키지 상품의 재고 가져오기
			if($data['package_option_seq1'])
			{
				// 패키지 옵션 갯수만큼 반복
				for($i=1;$i<6;$i++){

					// 옵션 번호가 있는 경우
					if($data['package_option_seq'.$i]){
						$data_package = $this->get_package_by_option_seq($data['package_option_seq'.$i]);
						$data['package_goods_seq'.$i] = $data_package['package_goods_seq'];
						$data['package_stock'.$i] = $data_package['package_stock'];
						$data['package_badstock'.$i] = $data_package['package_badstock'];
						$data['package_ablestock'.$i] = $data_package['package_ablestock'];
						$data['package_safe_stock'.$i] = $data_package['package_safe_stock'];

						if(!$data_min || $data_min['package_stock'] > $data_package['package_stock'])
						{
							$data_min = $data_package;
						}

						if( $data_package['package_safe_stock'] && ($data_package['package_stock'] < $data_package['package_safe_stock']) )
						{
							$safe_out = 'y';
						}
					}
				}

				if($data_min){
					$data['rstock'] = (int) $data_min['package_ablestock'];
					$data['badstock'] = (int) $data_min['package_badstock'];
					$data['stock'] = (int) $data_min['package_stock'];
					$data['reservation15'] = (int) $data_min['package_reservation15'];
					$data['reservation25'] = (int) $data_min['package_reservation25'];
					$data['safe_stock'] = (int) $data_min['package_safe_stock'];
				}

			}else{

				// 패키지가 아닌 실물 상품인 경우 안전재고가 현재 재고보다 크면 안전상태 유지
				if($data['safe_stock'] && ($data['stock'] < $data['safe_stock']) ){
					$safe_out = 'y';
				}
				$option_seq[] = $data['option_seq'];
			}

			// 옵션들의 전체 재고 수를 누적
			$total += $data['stock'];
		}

		// 상품 마스터 데이터에 재고 정보 업데이트
		$query = "update fm_goods set tot_stock=?, safe_stock_status=? where goods_seq=?";
		$result = $this->db->query($query,array($total, $safe_out, $goods_seq));

		// 현재 상품의 옵션이 다른 패키지 상품에 연결되어있는지 체크 (실물상품만 실행함)
		if(count($option_seq) > 0){

			// 패키지상품에 연결되어있는 상품번호 목록
			$package_goods_seq = array();

			// 실물상품이 패키지상품에 연결되어 있는지 package_option_seq1 ~ package_option_seq5 컬럼에서 확인
			for($i=1; $i<=5; $i++){

				// option_seq를 in 조건으로 한 컬럼씩 한번에 검색
				$this->db->select("goods_seq");
				$this->db->from("fm_goods_option");
				$this->db->where_in('package_option_seq'.$i, $option_seq);
				$result = $this->db->get_compiled_select();

				// 동일한 상품은 한번만 진행할 수 있게 중복 제거 처리 키 값이 곧 goods_seq 이다.
				foreach($result as $data){
					$package_goods_seq[$data['goods_seq']] = true;
				}
			}

			// 연결된 상품이 있을때만 실행
			if(!empty($package_goods_seq)){
				// 상품 옵션 정보를 가져옴
				$this->db->select('*');
				$this->db->from('fm_goods_option');
				$this->db->where_in('goods_seq', array_keys($package_goods_seq));
				$result = $this->db->get()->result_array();

				foreach($result as $data){
					// 안전재고 상태
					$package_safe_stock = "n";

					// 5개 옵션들 중 한개라도 안전재고보다 재고가 적으면 y 로 업데이트
					for($i=1;$i<6;$i++){
						if($data['package_option_seq'.$i])
						{
							$data_package = $this->get_package_by_option_seq($data['package_option_seq'.$i]);
							if( $data_package['package_safe_stock'] && ($data_package['package_stock'] < $data_package['package_safe_stock']) )
							{
								$package_safe_stock = "y";
							}
						}
					}

					// 상품의 안전재고상태를 업데이트
					$this->db->where("goods_seq", $data['goods_seq'])->update("fm_goods", array('safe_stock_status' => $package_safe_stock));
				}
			}
		}
	}

	// 서브옵션재고
	public function stock_suboption($mode,$ea,$goods_seq,$title,$option,$export_mode=false){

		$params['goods_seq']		= $goods_seq;
		$params['suboption_title']	= $title;
		$params['suboption']		= $option;
		$query_option = $this->get_suboption($params);
		$data_option = $query_option->row_array();

		$p_opt_seq = $data_option['package_option_seq1'];
		$p_unit_ea = $data_option['package_unit_ea1'];
		$p_ea = $ea * $p_unit_ea;

		if($mode == '+'){
			$query = "
			update fm_goods_supply set stock = stock + ? where suboption_seq = ?";
			$this->db->query($query,array($ea,$data_option['suboption_seq']));
			if( $data_option['package_count'] ){
				$query = "update fm_goods_supply set stock = stock + ? where option_seq = ?";
				$this->db->query($query,array($p_ea,$p_opt_seq));
			}
		}

		if($mode == '-'){
			if($export_mode){
				$query = "
				update fm_goods_supply set stock = stock - IF(stock>=?,?,stock),reservation15 = reservation15 - ?,reservation25 = reservation25 - ? where suboption_seq = ?";
				$this->db->query($query,array($ea,$ea,$ea,$ea,$data_option['suboption_seq']));
				if( $data_option['package_count'] ){
					$query = "
					update fm_goods_supply set stock = stock - IF(stock>=?,?,stock),reservation15 = reservation15 - ?,reservation25 = reservation25 - ? where option_seq = ?";
					$this->db->query($query,array($p_ea,$p_ea,$p_ea,$p_ea,$p_opt_seq));
				}
			}else{
				$query = "
				update fm_goods_supply set stock = stock - IF(stock>=?,?,stock) where suboption_seq = ?";
				$this->db->query($query,array($ea,$ea,$data_option['suboption_seq']));
				if( $data_option['package_count'] ){
					$query = "
					update fm_goods_supply set stock = stock - IF(stock>=?,?,stock) where option_seq = ?";
					$this->db->query($query,array($p_ea,$p_ea,$p_opt_seq));
				}
			}
		}


	}

	// 복수구매 할인 계산
	public function get_multi_sale_price($ea,$price,$arr_multi){
		$discount = 0;
		if(!$arr_multi['multi_discount_use']
			||!$arr_multi['multi_discount_ea']
			||!$arr_multi['multi_discount']
			||!$arr_multi['multi_discount_unit']) return $price;
		if($ea < $arr_multi['multi_discount_ea']) return $price;

		if( $arr_multi['multi_discount_unit'] == 'percent' && $arr_multi['multi_discount'] < 100 ){
			$discount = ( $price * $arr_multi['multi_discount'] / 100 );
		}else if($price > $arr_multi['multi_discount'] ) {
			$discount = $arr_multi['multi_discount'];
		}

		$discount = get_price_point($discount);
		$price -= $discount;

		return $price;
	}

	// 상품 구매수 증가
	public function get_purchase_ea($ea,$goods_seq){
		$query = "update fm_goods set purchase_ea = purchase_ea + ? where goods_seq=?";
		$this->db->query($query,array($ea,$goods_seq));
	}

	// 적립금 설정 별 적립금액 구하기
	public function get_reserve_with_policy($policy,$price,$shop_rate,$reserve_rate,$reserve_unit,$reserve){
		if($policy == 'shop'){
			$reserve = get_cutting_price($price * $shop_rate / 100);
		}else{
			if($reserve_unit == 'percent'){
				$reserve = get_cutting_price($price * $reserve_rate / 100);
			}else{
				$reserve = get_cutting_price($reserve_rate);
			}
		}
		//$reserve = get_price_point($reserve);
		return $reserve;
	}

	public function get_point_with_policy($price){
		if(!$price) return 0;
		if	(!$this->reserves)	$this->reserves	= config_load('reserve');
		$reserves = $this->reserves;
		$point = 0;
		if($reserves['point_use']=='Y'){
			switch($reserves['default_point_type']){
				case "per":
					if( $reserves['default_point_percent'] ) $point = get_cutting_price($price * $reserves['default_point_percent'] / 100);
					break;
				case "app":
					$point = $price / $reserves['default_point_app'] * $reserves['default_point'];
					break;
				default :
					$point = 0;
					break;
			}
		}else{
			$point = 0;
		}
		return get_cutting_price($point);
	}

	// 모든 판매 가능한 상품 정보
	public function get_goods_all($update_date,$image_type,$isFeed=false){
		if(!$image_type) $where_val[] = 'list1';
		else $where_val[] = $image_type;
		$result = "";

		// 상품 아이콘 서브쿼리
		$goods_icon_subquery = "
		select group_concat(c.codecd) from fm_goods_icon c where c.goods_seq=g.goods_seq and
		(
			(ifnull(start_date,'0000-00-00') = '0000-00-00' and ifnull(end_date,'0000-00-00') = '0000-00-00')
			or
			(curdate() between start_date and end_date)
			or
			(start_date <= curdate() and ifnull(end_date,'0000-00-00') = '0000-00-00')
			or
			(end_date >= curdate() and ifnull(start_date,'0000-00-00') = '0000-00-00')
		)
		";

		$query = "
		select
			g.*,
			i.image,
			o.consumer_price,
			o.price,
			o.reserve_rate,
			o.reserve_unit,
			o.reserve,
			({$goods_icon_subquery}) as icons,
			l.category_link_seq,l.sort,l.category_code,
			( select contents from fm_goods_addition where goods_seq=g.goods_seq and type='model' ) model,
			( select contents from fm_goods_addition where goods_seq=g.goods_seq and type='brand' ) brand,
			( select contents from fm_goods_addition where goods_seq=g.goods_seq and type='manufacture' ) manufacture,
			( select contents from fm_goods_addition where goods_seq=g.goods_seq and type='orgin' ) orgin
		from
			fm_goods g
			left join fm_goods_image i on ( i.goods_seq=g.goods_seq and i.cut_number=1 and i.image_type = ? )
			left join fm_goods_option o on ( o.goods_seq=g.goods_seq and o.default_option ='y' )
			,fm_category_link l
		where
			l.goods_seq=g.goods_seq and link = 1 and g.goods_type='goods'";

		$where[] = "goods_status = ?";
		$where_val[] = 'normal';
		$where[] = "goods_view = ?";
		$where_val[] = 'look';

		//승인상품만 @2013-08-12
		$where[] = "provider_status = ?";
		$where_val[] = '1';

		if($update_date){
			$where[] = "update_date > ?";
			$where_val[] = $update_date;
		}
		if($isFeed){
			$where[] = "g.feed_status != ?";
			$where_val[] = 'N';
		}

		$query .= ' and ' . implode(' and ', $where);
		$query = $this->db->query($query,$where_val);

		foreach($query->result_array() as $data){
			$result[] = $data;
		}
		return $result;
	}


	// 모든 판매 가능한 상품 정보
	public function get_goods_all_partner($update_date, $image_type, $isFeed=false){
		$result			= '';
		$now_date		= date('Y-m-d');

		if(!$image_type)	$where_val[] = 'list1';
		else				$where_val[] = $image_type;

		// 상품 아이콘 서브쿼리
		$goods_icon_subquery = "
		select group_concat(c.codecd) from fm_goods_icon c where c.goods_seq=g.goods_seq and
		(
			(ifnull(start_date,'0000-00-00') = '0000-00-00' and ifnull(end_date,'0000-00-00') = '0000-00-00')
			or
			(curdate() between start_date and end_date)
			or
			(start_date <= curdate() and ifnull(end_date,'0000-00-00') = '0000-00-00')
			or
			(end_date >= curdate() and ifnull(start_date,'0000-00-00') = '0000-00-00')
		)
		";

		$query = "
		select
			g.*,
			i.image,
			o.consumer_price,
			o.price,
			o.reserve_rate,
			o.reserve_unit,
			o.reserve,
			o.weight,
			(".$goods_icon_subquery.") as icons,
			l.category_link_seq,l.sort,l.category_code,
			( select contents from fm_goods_addition where goods_seq=g.goods_seq and type='model' limit 1) model,
			( select contents from fm_goods_addition where goods_seq=g.goods_seq and type='brand' limit 1) brand,
			( select contents from fm_goods_addition where goods_seq=g.goods_seq and type='manufacture' limit 1) manufacture,
			( select contents from fm_goods_addition where goods_seq=g.goods_seq and type='orgin' limit 1) orgin,
			( select category_code from fm_brand_link bl where goods_seq=g.goods_seq and link=1 limit 1) brand_code,
			( select b.title from fm_brand_link brl,fm_brand b where brl.category_code=b.category_code and brl.goods_seq=g.goods_seq and brl.link=1 limit 1) brand_title,
			( select c.title from fm_category_link cl,fm_category c where cl.category_code=c.category_code and cl.goods_seq=g.goods_seq and cl.link=1 limit 1) category_title
		from
			fm_goods g
			left join fm_goods_image i on ( i.goods_seq=g.goods_seq and i.cut_number=1 and i.image_type = '".$image_type."' )
			left join fm_goods_option o on ( o.goods_seq=g.goods_seq and o.default_option ='y' )
			,fm_category_link l
		where
			l.goods_seq=g.goods_seq
			and link = 1
			and g.goods_type='goods'
			and g.goods_status = 'normal'
			and
			(
				g.goods_view = 'look'
				or ( g.display_terms = 'AUTO' and g.display_terms_begin <= '".$now_date."' and g.display_terms_end >= '".$now_date."' )
			)
			and g.string_price_use != '1'";


		//승인상품만 @2013-08-12
		$query .= " and g.provider_status = '1'";

		if($update_date){
			$query .= " and update_date > '$update_date'";
		}

		if($isFeed){
			$query .= " and (g.feed_status = 'Y' or g.feed_status is NULL)";
		}

		return $query;
	}

	// 모든 판매 가능한 상품 정보
	public function get_goods_all_partner_count($update_date,$image_type,$isFeed=false){
		if(!$image_type) $where_val[] = 'list1';
		else $where_val[] = $image_type;
		$result = "";

		$now_date = date('Y-m-d');

		// 상품 아이콘 서브쿼리
		$goods_icon_subquery = "
		select group_concat(c.codecd) from fm_goods_icon c where c.goods_seq=g.goods_seq and
		(
			(ifnull(start_date,'0000-00-00') = '0000-00-00' and ifnull(end_date,'0000-00-00') = '0000-00-00')
			or
			(curdate() between start_date and end_date)
			or
			(start_date <= curdate() and ifnull(end_date,'0000-00-00') = '0000-00-00')
			or
			(end_date >= curdate() and ifnull(start_date,'0000-00-00') = '0000-00-00')
		)
		";

		$query = "
		select
			count(*) as cnt

		from
			fm_goods g,fm_goods_option o

		where
			o.goods_seq=g.goods_seq and o.default_option ='y'

			and g.goods_type='goods'
			and g.goods_status = 'normal'
			and (g.goods_view = 'look' or ( g.display_terms = 'AUTO' and g.display_terms_begin <= '".$now_date."' and g.display_terms_end >= '".$now_date."'))
			and g.string_price_use != '1'
			and (g.feed_status = 'Y' or g.feed_status is NULL)";

		if($update_date){
			$query .= " and update_date > '$update_date'";
		}
		if($isFeed){
			$query .= " and (g.feed_status = 'Y' or g.feed_status is NULL)";

		}

		return $query;
	}


	// 모바일 상세설명 없는 상품 일괄적으로 모바일 상품설명 일괄등록
	public function all_mobile_contents() {
		$query = "select goods_seq, contents from fm_goods where contents!='' and (mobile_contents IS NULL or mobile_contents='') ";
		$query = $this->db->query($query);
		foreach($query -> result_array() as $data){
			$this->set_mobile_contents($data['contents'], $data['goods_seq']);
		}
	}

	// 모바일 상세 설명 생성
	public function set_mobile_contents($contents,$goods_seq='')
	{
		$this->load->library('Image_lib');
		$cnt = preg_match_all("/<IMG[^>]*src=[\"']?([^>\"']+)[\"']?[^>]*>/i",$contents, $matches);
		foreach($matches[1] as $img_key => $ori_img){
			$img = $ori_img;
			### preg_match_all 후 이미지 주소에 띄어쓰기가 없을 것으로 판단하여 이미지 명에 띄어쓰기가 있을 경우 오류로 인하여 주석처리 2015-09-16
			//$t_arr_img = explode(' ',$ori_img);
			//$ori_img = $t_arr_img[0];

			if( preg_match('/http:\/\//',$img) ){
				$arr_img = explode('/',$img);
				unset($arr_img[0],$arr_img[1],$arr_img[2]);
				$img = implode('/',$arr_img);
			}else{
				if(substr($img,0,1) == '/') $img = substr($img,1);
			}

			$img_tag = '<img src="'.$ori_img.'" border="0" />';

			$size = @getimagesize($img);
			if( $size ){
				$limit = ($size[0]*$size[1]*$size[bits]) * 0.9;
				if($limit < 20000000)
				{
					if($size[0] > 550) $img_tag = '<img src="'.$ori_img.'" width="550" border="0" />';
					if( substr($img,0,4)=='data' && is_file($img)){
						if($size[0] > 550){
							$arr_img = explode('/',$img);
							$target = 'mobile_'.str_replace(array('mobile_','temp_'),'',$arr_img[count($arr_img)-1]);
							$config['image_library'] = 'gd2';
							$config['source_image'] = $img;
							$config['new_image'] = $target;
							$config['maintain_ratio'] = TRUE;
							$config['width'] = 550;
							$config['height'] = ($config['width'] / $size[0]) * $size[1];
							$this->image_lib->initialize($config);
							if($this->image_lib->resize()){
								unset($arr_img[count($arr_img)-1]);
								$mobile_img = implode('/',$arr_img).'/'.$target;
								@chmod($mobile_img,0777);
								//$img_tag = '<img src="'.'http://'.$_SERVER['HTTP_HOST'].'/'.$mobile_img.'" border="0" />';
								$img_tag = '<img src="'.'/'.$mobile_img.'" border="0" />';
							}
							$this->image_lib->clear();
						}
					}

				}
			}
			$replace[$img_key] = $img_tag;
		}
		$mobile_contents = str_replace($matches[0],$replace,$contents);

		// 컨텐츠에 포함된 이미지태그를 찾아서 일정 사이즈로 분할 (모바일용)
		$mobile_contents	= $this->split_images($mobile_contents);

		if($goods_seq){
			$query = "update fm_goods set mobile_contents=?, mobile_contents_copy='Y' where goods_seq=?";
			$this->db->query($query,array($mobile_contents,$goods_seq));
		}

		return $mobile_contents;
	}

	// 공용정보 모바일 이미지 강제줄임
	public function set_mobile_common_contents($contents)
	{
		$cnt = preg_match_all("/<IMG[^>]*src=[\"']?([^>\"']+)[\"']?[^>]*>/i",$contents, $matches);
		foreach($matches[1] as $img_key => $ori_img){
			$img = $ori_img;
			### preg_match_all 후 이미지 주소에 띄어쓰기가 없을 것으로 판단하여 이미지 명에 띄어쓰기가 있을 경우 오류로 인하여 주석처리 2015-09-17
			//$t_arr_img = explode(' ',$ori_img);
			//$ori_img = $t_arr_img[0];

			if( preg_match('/http:\/\//',$img) ){
				$arr_img = explode('/',$img);
				unset($arr_img[0],$arr_img[1],$arr_img[2]);
				$img = implode('/',$arr_img);
			}else{
				if(substr($img,0,1) == '/') $img = substr($img,1);
			}

			/*$img_tag = '<img src="'.$ori_img.'" border="0" />';

			$size = @getimagesize($img);
			if( $size ){
				$limit = ($size[0]*$size[1]*$size[bits]) * 0.9;
				if($limit < 20000000){
					if($size[0] > 300) $img_tag = '<img src="'.$ori_img.'" width="300" border="0" />';
				}
			}*/

			$img_tag = '<img src="'.$ori_img.'" width="100%" border="0" />';
			$replace[$img_key] = $img_tag;
		}
		$mobile_contents = str_replace($matches[0],$replace,$contents);
		return $mobile_contents;
	}

	###
	public function option_check($seq){
		$datas = get_data("fm_goods_option",array("goods_seq"=>$seq,"default_option"=>'y'));
		if(empty($datas) || !$datas[0]['option_seq']){
			$sql = "UPDATE fm_goods_option A SET A.default_option = 'y' WHERE A.option_seq = (SELECT B.option_seq FROM (SELECT min(option_seq) as option_seq FROM fm_goods_option WHERE goods_seq = '{$seq}') B)";
			$this->db->query($sql);
		}
		return;
	}

	/* 재입고알림 요첨 상품 리스트 */
	public function restock_notify_list($sc) {

		$CI 	=& get_instance();
		$key 	= get_shop_key();
		$wheres = array();

		if(!isset($sc['page']))$sc['page'] = 0;

		$sc['orderby']			= (isset($sc['orderby'])) ?	$sc['orderby']:'restock_notify_seq';
		$sc['sort']				= (isset($sc['sort'])) ?	$sc['sort']:'desc';

		$bindData = [];
		$bindLike = '';

		# 카테고리
		if( $sc['category4'] ){
			$bindData['category_code'] = $sc['category4'];
		} else if( $sc['category3'] ){
			$bindLike = $sc['category3'];
		} else if( $sc['category2'] ){
			$bindLike = $sc['category2'];
		} else if( $sc['category1'] ){
			$bindLike = $sc['category1'];
		}

		if( $bindData || $bindLike){
			if($bindData) $this->db->where($bindData);
			if($bindLike) $this->db->like('category_code', $bindLike, 'after');

			$this->db->select('goods_seq');
			$this->db->from('fm_category_link');
			if( $sc['search_link_category'] ){
				$this->db->where('link');
			}

			$subCategory = $this->db->get_compiled_select();
		}

		$bindData = [];
		$bindLike = '';

		# 브랜드
		if( $sc['brands4'] ){
			$bindData['category_code'] = $sc['brands4'];
		} else if( $sc['brands3'] ){
			$bindLike = $sc['brands3'];
		} else if( $sc['brands2'] ){
			$bindLike = $sc['brands2'];
		} else if( $sc['brands1'] ){
			$bindLike = $sc['brands1'];
		}

		if( $bindData || $bindLike){
			if($bindData) $this->db->where($bindData);
			if($bindLike) $this->db->like('category_code', $bindLike, 'after');

			$this->db->select('goods_seq');
			$this->db->from('fm_brand_link');
			if( $sc['search_link_category'] ){
				$this->db->where('link');
			}

			$subBrand = $this->db->get_compiled_select();
		}

		$this->db->select("
			A.restock_notify_seq,
			A.member_seq,
			A.goods_seq,
			A.notify_status,
			A.notify_date,
			A.regist_date,
			AES_DECRYPT(UNHEX(A.cellphone), '{$key}') as cellphone,
			CASE WHEN A.notify_status = 'none' THEN '미통보'
				WHEN A.notify_status = 'complete' THEN '통보'
				END AS goods_status_text,
			B.consumer_price, B.price,
			C.stock,
			C.badstock,
			C.reservation15,
			C.reservation25,
			F.goods_name,
			F.goods_code,
			F.cancel_type,
			F.goods_status,
			F.goods_view,
			F.provider_seq,
			F.provider_status,
			F.tax,
			F.page_view,
			H.restock_option_seq,
			H.title1,
			H.option1,
			H.title2,
			H.option2,
			H.title3,
			H.option3,
			H.title4,
			H.option4,
			H.title5,
			H.option5"
		);

		$this->db->from('fm_goods_restock_notify A');

		$this->db->join('fm_goods_restock_option H', 'A.restock_notify_seq = H.restock_notify_seq', 'inner');
		$this->db->join('fm_goods_option B', "A.goods_seq = B.goods_seq AND IFNULL(H.option1,'') = IFNULL(B.option1,'') AND IFNULL(H.option2,'') = IFNULL(B.option2,'') AND IFNULL(H.option3,'') = IFNULL(B.option3,'') AND IFNULL(H.option4,'') = IFNULL(B.option4,'') AND IFNULL(H.option5,'') = IFNULL(B.option5,'')", 'inner');
		$this->db->join('fm_goods_supply C', 'A.goods_seq	= C.goods_seq AND B.option_seq = C.option_seq', 'inner');
		$this->db->join('fm_goods F', 'A.goods_seq = F.goods_seq', 'inner');

		if ($subCategory) {
			$this->db->where("F.goods_seq IN ($subCategory)");
		}

		if ($subBrand) {
			$this->db->where("F.goods_seq IN ($subBrand)");
		}

		$subQuery = $this->db->get_compiled_select();
		$this->db->select(
			"K.*,
				(SELECT E.category_code FROM fm_category_link E WHERE E.goods_seq = K.goods_seq AND E.link = '1' limit 1) as category_code,
				(SELECT F.category_code FROM fm_brand_link F WHERE F.goods_seq = K.goods_seq AND F.link = '1' limit 1) as brand_code"
		);

		$this->db->from("({$subQuery}) AS K");

		$subQuery = $this->db->get_compiled_select();

		$tables = '('.$subQuery.') AS Z';

		###
		if($sc['keyword']=='상품명, 상품코드' || $sc['keyword']=='사은품명, 상품코드') unset($sc['keyword']);
		if( !empty($sc['keyword'])){
			$wheres[] = " ( Z.goods_name like '%{$sc['keyword']}%' or Z.goods_code like '%{$sc['keyword']}%' or Z.goods_seq like '%{$sc['keyword']}%' ) ";
		}


		if( $sc['not_regist_category'] ){
			$wheres[] = " Z.category_code IS NULL ";
		}

		if( $sc['not_regist_brand'] ){
			$wheres[] = " Z.brand_code IS NULL ";
		}


		### DATE
		if( !empty($sc['sdate']) && !empty($sc['edate']) ){
			$wheres[] = " Z.{$sc['date_gb']} between '{$sc['sdate']} 00:00:00' and '{$sc['edate']} 23:59:59' ";
		}else if( !empty($sc['sdate']) && empty($sc['edate']) ){
			$wheres[] = " Z.{$sc['date_gb']} >= '{$sc['sdate']}' ";
		}else if( empty($sc['sdate']) && !empty($sc['edate']) ){
			$wheres[] = " Z.{$sc['date_gb']} <= '{$sc['edate']}' ";
		}

		### PRICE
		if( $sc['sprice'] ){
			$wheres[] = " Z.{$sc['price_gb']} >= '{$sc['sprice']}' ";
		}
		if( $sc['eprice'] ){
			$wheres[] = " Z.{$sc['price_gb']} <= '{$sc['eprice']}' ";
		}

		### STOCK
		if( $sc['sstock'] ){
			$wheres[] = " Z.stock >= '{$sc['sstock']}' ";
		}
		if( $sc['estock'] ){
			$wheres[] = " Z.stock <= '{$sc['estock']}' ";
		}

		### PAGE_VIEW
		if( $sc['spage_view'] ){
			$wheres[] = " Z.page_view >= '{$sc['spage_view']}' ";
		}
		if( $sc['epage_view'] ){
			$wheres[] = " Z.page_view <= '{$sc['epage_view']}' ";
		}


		if($sc['goodsStatus'] == 'all') $sc['goodsStatus'] = '';
		if($sc['goodsView'] == 'all') $sc['goodsView'] = '';
		if($sc['taxView'] == 'all') $sc['taxView'] = '';
		if($sc['notifyStatus'] == 'all') $sc['notifyStatus'] = '';

		### GOODSVIEW
		if( !empty($sc['goodsView']) && count($sc['goodsView'])=='1' && is_array($sc['goodsView']) )
		{
			$wheres[] = " Z.goods_view = '{$sc['goodsView'][0]}' ";
		}elseif(!empty($sc['goodsView'])) {
			$wheres[] = " Z.goods_view = '{$sc['goodsView']}' ";
		}

		### provider_status
		if( !empty($sc['provider_status']) && count($sc['provider_status'])=='1' )
		{
			$wheres[] = " Z.provider_status = '{$sc['provider_status'][0]}' ";
		}

		### TAX
		if( !empty($sc['taxView']) && count($sc['taxView'])=='1' && is_array($sc['taxView']) )
		{
			$wheres[] = " Z.tax = '{$sc['taxView'][0]}' ";
		}elseif(!empty($sc['taxView'])) {
			$wheres[] = " Z.tax = '{$sc['taxView']}' ";
		}

		### GOODS STATUS
		if( !empty($sc['goodsStatus']) ){
			if(is_array($sc['goodsStatus'])){
				foreach($sc['goodsStatus'] as $k){
					$tmp[] = "'".$k."'";
				}
				$tmp_text = implode(",",$tmp);
				$wheres[] = " Z.goods_status in ( {$tmp_text} ) ";
			}else{
				$wheres[] = " Z.goods_status = '{$sc['goodsStatus']}' ";
			}
		}

		### NOTIFY STATUS
		if( !empty($sc['notifyStatus']) ){
				if(is_array($sc['notifyStatus'])){
				foreach($sc['notifyStatus'] as $k){
					$tmp[] = "'".$k."'";
				}
				$tmp_text = implode(",",$tmp);
				$wheres[] = " Z.notify_status in ( {$tmp_text} ) ";
			}else{
				$wheres[] = " Z.notify_status = '{$sc['notifyStatus']}' ";
			}
		}

		### MOEDEL
		if( !empty($sc['model']) )
		{
			$wheres[] = " Z.goods_seq = any( select K.goods_seq from fm_goods_addition K where K.goods_seq = Z.goods_seq AND K.contents = '{$sc['model']}' ) ";
		}
		### BRAND
		if( !empty($sc['brand']) )
		{
			$wheres[] = " Z.goods_seq = any( select K.goods_seq from fm_goods_addition K where K.goods_seq = K.goods_seq AND K.contents = '{$sc['brand']}' ) ";
		}
		### MANUFACTURE
		if( !empty($sc['manufacture']) )
		{
			$wheres[] = " Z.goods_seq = any( select K.goods_seq from fm_goods_addition K where K.goods_seq = Z.goods_seq AND K.contents = '{$sc['manufacture']}' ) ";
		}
		### ORIGN
		if( !empty($sc['orign']) )
		{
			$wheres[] = " K.goods_seq = any( select K.goods_seq from fm_goods_addition K where K.goods_seq = Z.goods_seq AND K.contents = '{$sc['orign']}' ) ";
		}

		### GOODS_SEQ
		if( !empty($sc['goods_seq']) )
		{
			$wheres[] = " Z.goods_seq = '{$sc['goods_seq']}' ";
		}

		### 입점사
		if( !empty($sc['provider_seq']) ){
			$wheres[] = " Z.provider_seq='{$sc['provider_seq']}' ";
		}

		if($sc['restock_notify_seq']){
			$wheres[] = " Z.restock_notify_seq in (".$sc['restock_notify_seq'].") ";
		}

		if($sc['member_seq']){
			$wheres[] = "Z.member_seq = '".$sc['member_seq']."' ";
	}

		$orderStr = "{$sc['orderby']} {$sc['sort']}";
		$limitStr = " LIMIT {$sc['page']}, {$sc['perpage']}";

		/* search */
		$search_field		= "Z.*";

		$sql				= array();
		$sql['field']		= $search_field;
		$sql['table']		= $tables;
		$sql['wheres']		= $wheres;
		$sql['countWheres']	= $countWhere;
		$sql['groupby']		= $groupBy;
		$sql['orderby']		= $orderStr;
		$sql['limit']		= $limitStr;
		$sc['debug'] = 1;

		$result				= pagingNumbering($sql,$sc);
		//debug($result['query']);
		$aMemberSeq = array();
		foreach($result['record'] as $i=>$data) if($data['member_seq']) $aMemberSeq[] = $data['member_seq'];

		$result['page']['querystring'] = get_args_list();

		foreach($result['record'] as $i=>$data){
			if($data['member_seq']){
				$aMemberSeq[] = $data['member_seq'];
			}
		}
		if( $aMemberSeq ){

			$aMember = array();
			$rQuery = $this->db->select('member_seq, rute as mbinfo_rute, user_name as mbinfo_user_name, userid, user_name, b.group_name')
					->from('fm_member a')
					->join('fm_member_group AS b','a.group_seq = b.group_seq')
					->where_in('a.member_seq',$aMemberSeq)->get();
			foreach ($rQuery->result_array() as $aData) {
				$aMember[$aData['member_seq']] = $aData;
			}
			$rQuery = $this->db->select('member_seq, business_seq as mbinfo_business_seq, bname as mbinfo_bname')
								->from('fm_member_business')
								->where_in('member_seq',$aMemberSeq)->get();
			foreach ($rQuery->result_array() as $aData) $aBusiness[$aData['member_seq']] = $aData;
		}

		foreach($result['record'] as $i=>$data){
			if($data['member_seq']){
				if ($aMember[$data['member_seq']]) {
					$data['mbinfo_rute'] 				= $aMember[$data['member_seq']]['mbinfo_rute'];
					$data['mbinfo_user_name'] 			= $aMember[$data['member_seq']]['mbinfo_user_name'];
					$data['userid'] 					= $aMember[$data['member_seq']]['userid'];
					$data['user_name'] 					= $aMember[$data['member_seq']]['user_name'];
					$data['group_name'] 				= $aMember[$data['member_seq']]['group_name'];
				}
				if ($aBusiness[$data['member_seq']]) {
					$data['mbinfo_business_seq'] 		= $aBusiness[$data['member_seq']]['mbinfo_business_seq'];
					$data['mbinfo_bname'] 				= $aBusiness[$data['member_seq']]['mbinfo_bname'];
				}
				$result['record'][$i] 					= $data;
				$result['record'][$i]['member_type']	= $data['mbinfo_business_seq'] ? '기업' : '개인';
			}

			// 패키지 상품의 재고 가져오기
			$data_min = '';
			if($data['package_option_seq1']){
				for($i=1;$i<6;$i++){
					if($data['package_option_seq'.$i]){
						$data_package = $this->get_package_by_option_seq($data['package_option_seq'.$i]);
						$data['package_goods_seq'.$i] 	= $data_package['package_goods_seq'];
						$data['package_stock'.$i] 		= $data_package['package_stock'];
						$data['package_badstock'.$i] 	= $data_package['package_badstock'];
						$data['package_ablestock'.$i] 	= $data_package['package_ablestock'];
						$data['package_safe_stock'.$i] 	= $data_package['package_safe_stock'];

						if(!$data_min || $data_min['package_stock'] > $data_package['package_stock']){
							$data_min = $data_package;
						}
					}
				}
				if($data_min){
					$result['record'][$i]['rstock']			= (int) $data_min['package_ablestock'];
					$result['record'][$i]['badstock']		= (int) $data_min['package_badstock'];
					$result['record'][$i]['stock']			= (int) $data_min['package_stock'];
					$result['record'][$i]['reservation15']	= (int) $data_min['package_reservation15'];
					$result['record'][$i]['reservation25']	= (int) $data_min['package_reservation25'];
					$result['record'][$i]['safe_stock']		= (int) $data_min['package_safe_stock'];
				}
			}
		}
		return $result;
	}

	###
	public function delete_restock_notify($restock_notify_seq){
		### DEFAULT
		$result = $this->db->delete('fm_goods_restock_notify', array('restock_notify_seq' => $restock_notify_seq));
		$result .= $this->db->delete('fm_goods_restock_option', array('restock_notify_seq' => $restock_notify_seq));
		return $result;
	}

	### 서브 옵션 출고예약량 업데이트($mode : plus,minus,modify)
	public function modify_reservation_suboption($ea,$goods_seq,$title,$option,$ablestock_step,$mode='modify')
	{
		$reservation_field = 's.reservation'.$ablestock_step;
		if( $mode == 'modify' ) $set_query = $reservation_field." = ?";
		else if($mode == 'plus') $set_query = $reservation_field." = ifnull(". $reservation_field .",0) + ?";
		else if($mode == 'minus') $set_query = $reservation_field." = ifnull(". $reservation_field .",0) - ?";
		$val[] = $ea;
		$val[] = $goods_seq;
		$val[] = $title;
		$val[] = $option;
		$query = "update fm_goods_suboption o,fm_goods_supply s set ".$set_query." where o.suboption_seq=s.suboption_seq and o.goods_seq=? and o.suboption_title=? and o.suboption=?";
		$this->db->query($query,$val);
		$this->runout_check($goods_seq);///if($mode != 'minus')
	}

	### 옵션 출고예약량 업데이트($mode : plus,minus,modify)
	public function modify_reservation_option($ea,$goods_seq,$option1,$option2,$option3,$option4,$option5,$ablestock_step,$mode='modify')
	{

		$reservation_field = 's.reservation'.$ablestock_step;
		if( $mode == 'modify' ) $set_query = $reservation_field." = ?";
		else if($mode == 'plus') $set_query = $reservation_field." = ifnull(". $reservation_field .",0) + ?";
		else if($mode == 'minus') $set_query = $reservation_field." = ifnull(". $reservation_field .",0) - ?";

		$val[] = $ea;
		$val[] = $goods_seq;
		$where[] = "o.goods_seq=?";
		if($option1){
			$where[] = "o.option1=?";
			$val[] = $option1;
		}
		if($option2){
			$where[] = "o.option2=?";
			$val[] = $option2;
		}
		if($option3){
			$where[] = "o.option3=?";
			$val[] = $option3;
		}
		if($option4){
			$where[] = "o.option4=?";
			$val[] = $option4;
		}
		if($option5){
			$where[] = "o.option5=?";
			$val[] = $option5;
		}

		if(!$option1 && !$option2 && !$option3 && !$option4 && !$option5 ){
			$where[] = "o.option1=''";
			$where[] = "o.option2=''";
			$where[] = "o.option3=''";
			$where[] = "o.option4=''";
			$where[] = "o.option5=''";
		}

		$where_str = " and ". implode(' and ',$where);
		$query = "update fm_goods_option o,fm_goods_supply s set ".$set_query." where o.option_seq=s.option_seq ".$where_str;
		$this->db->query($query,$val);

		$this->runout_check($goods_seq);//if($mode != 'minus')

	}

	### 상품 품절처리
	public function runout_check($goods_seq,$mode='auto')
	{
		$cfg = config_load('order');

		$this->total_stock($goods_seq);

		//상품개별재고
		$data_goods = $this->get_goods($goods_seq);
		if ($data_goods['runout_policy']) {
			$cfg['runout'] = $data_goods['runout_policy'];
			$cfg['ableStockLimit'] = $data_goods['able_stock_limit'];
		}

		$affected_rows = false;
		// 재고 상관없거나 auto 가 아니면 return;
		if ($cfg['runout'] == 'unlimited' || $mode !== 'auto') {
			return $affected_rows;
		}

		$field = 's.reservation' . $cfg['ableStockStep'];
		// 가용재고 = 재고 - 출고예약량 - 불량재고 2020-06-30 by hyem
		// 재고 - 불량재고가 마이너스(-) 값인 경우 sql 오류 발생 수정
		$query = 'select
						if(sum(s.stock) < sum(IFNULL(s.badstock,0)), 0, sum(s.stock)-sum(IFNULL(s.badstock,0))) stock,
						sum(if(IFNULL(if(s.stock < s.badstock, 0, s.stock-s.badstock),0) < IFNULL(' . $field . ',0),0,IFNULL(if(s.stock < s.badstock, 0, s.stock-s.badstock),0) - IFNULL(' . $field . ',0))) ablestock 
				from
					fm_goods_supply s,fm_goods_option o
				where
					o.option_seq = s.option_seq and o.goods_seq=?';
		$query = $this->db->query($query, [$goods_seq]);
		$data = $query->row_array();

		// 재고로 계산
		$tstock = $data['stock'];
		$able_stock_limit = 0;
		// 가용재고로 계산
		if ($cfg['runout'] === 'ableStock') {
			$tstock = $data['ablestock'];
			$able_stock_limit = $cfg['ableStockLimit'];
		}

		if ($tstock <= $able_stock_limit) { // 재고가 없을 경우 품절로
			$now_goods_status = 'normal';
			$after_goods_status = 'runout';
			$change_text = '품절';
		} elseif ($tstock > $able_stock_limit) { // 재고가 있을 경우 정상으로
			$now_goods_status = 'runout';
			$after_goods_status = 'normal';
			$change_text = '정상';
		}

		// 상품 상태 변경
		$set_params = ['goods_status' => $after_goods_status];
		$where_params = [
			'goods_seq' => $goods_seq,
			'goods_status' => $now_goods_status,
		];
		$this->goodsmodel->set_goods($set_params, $where_params);
		$affected_rows = $this->db->affected_rows() > 0 ? true : false;

		// 상태 변경 됐으면 (기존과 동일한 상태이면 update 안해서 아래 로그 안남음)
		if ($affected_rows === true) {
			$admin_log = '<div>' . date('Y-m-d H:i:s') . " 자동으로 상품 상태가 '" . $change_text . "'(으)로 변경됐습니다.</div>";
			$this->db->where('goods_seq', $goods_seq);
			$this->db->set('admin_log', 'CONCAT("' . $admin_log . '",ifnull(admin_log, ""))', false);
			$this->db->update('fm_goods');
		}

		// 상품 상태 변경됐으면 true, 안됐으면 false
		return $affected_rows;
	}

	public function get_option_reservation($cfg,$goods_seq,$option1,$option2,$option3,$option4,$option5){

		$where[] = "o.goods_seq=?";
		$val[] = $goods_seq;
		if($option1!=null){
			$where[] = "o.option1=?";
			$val[] = $option1;
		}
		if($option2!=null){
			$where[] = "o.option2=?";
			$val[] = $option2;
		}
		if($option3!=null){
			$where[] = "o.option3=?";
			$val[] = $option3;
		}
		if($option4!=null){
			$where[] = "o.option4=?";
			$val[] = $option4;
		}
		if($option5!=null){
			$where[] = "o.option5=?";
			$val[] = $option5;
		}

		if($option1==null && $option2==null && $option3==null && $option4==null && $option5==null ){
			$where[] = "o.option1=''";
			$where[] = "o.option2=''";
			$where[] = "o.option3=''";
			$where[] = "o.option4=''";
			$where[] = "o.option5=''";
		}
		$reservation_field = $this->reservation_field;
		$query = "select o.*,s.".$reservation_field." from fm_goods_option o,fm_goods_supply s where o.option_seq = s.option_seq and o.goods_seq=s.goods_seq and ".implode(' and ',$where);
		$query = $this->db->query($query,$val);
		$data = $query -> row_array($query);

		// 패키지 상품의 재고 가져오기
		$data_min = '';
		if($data['package_option_seq1']){
			for($i=1;$i<6;$i++){
				if($data['package_option_seq'.$i]){
					$data_package = $this->get_package_stock($data['package_option_seq'.$i],$data['package_unit_ea'.$i]);
					$data_package['unit_ablestock'] = (int) $data_package['unit_stock'] - $data_package['unit_badstock'];

					if(!$data_min || $data_min['unit_stock'] > $data_package['unit_stock']){
						$data_min = $data_package;
					}
				}
			}
			if($data_min){
				$data['rstock'] = (int) $data_min['unit_ablestock'];
				$data['badstock'] = (int) $data_min['unit_badstock'];
				$data['stock'] = (int) $data_min['unit_stock'];
				$data['reservation15'] = ceil($data_min['unit_reservation15']);
				$data['reservation25'] = ceil($data_min['unit_reservation25']);
				$data['safe_stock'] = (int) $data_min['package_safe_stock'];
			}
		}

		return $data[$reservation_field];

	}

	public function get_suboption_reservation($cfg,$goods_seq,$title,$suboption){
		$where[] = "o.goods_seq=?";
		$val[] = $goods_seq;
		$where[] = "o.suboption_title=?";
		$val[] = $title;
		$where[] = "o.suboption=?";
		$val[] = $suboption;

		$reservation_field = $this->reservation_field;
		$query = "select o.*,s.".$reservation_field." from fm_goods_suboption o,fm_goods_supply s where o.suboption_seq = s.suboption_seq and o.goods_seq=s.goods_seq and ".implode(' and ',$where);
		$query = $this->db->query($query,$val);
		$data = $query -> row_array($query);

		// 패키지 상품의 재고 가져오기
		$data_min = '';
		if($data['package_option_seq1']){
			$data_package = $this->get_package_by_option_seq($data['package_option_seq1']);
			$data['package_goods_seq1'] = $data_package['package_goods_seq'];
			$data['package_stock1'] = $data_package['package_stock'];
			$data['package_badstock1'] = $data_package['package_badstock'];
			$data['package_ablestock1'] = $data_package['package_ablestock'];
			$data['package_safe_stock1'] = $data_package['package_safe_stock'];
			if(!$data_min || $data_min['package_stock'] > $data_package['package_stock']){
				$data_min = $data_package;
			}
			if($data_min){
				$data['rstock']			= (int) $data_min['package_ablestock'];
				$data['badstock']		= (int) $data_min['package_badstock'];
				$data['stock']			= (int) $data_min['package_stock'];
				$data['reservation15']	= (int) $data_min['package_reservation15'];
				$data['reservation25']	= (int) $data_min['package_reservation25'];
				$data['safe_stock']		= (int) $data_min['package_safe_stock'];
			}
		}

		return (int) $data[$reservation_field];
	}

	public function get_goods_icon_codecd($no){
		$result = '';
		$tmp = $this->get_goods_icon($no);
		if($tmp) foreach($tmp as $data){
			$result[] = $data['codecd'];
		}
		return $result;
	}

	public function get_goods_sub_info($category){
		if($category != ""){
			$this->db->select('*');
			$this->db->from('fm_goods_sub_info');
			$this->db->where('category', $category);
			$this->db->order_by('regdate', 'ASC');
			$this->db->order_by('seq', 'ASC');
			$query = $this->db->get();
			$result = $query->result_array();
		}else{
			$result = "";
		}
		return $result;
	}

	//상품정보고시 품목별 그룹 @2017-02-20
	public function get_goods_sub_info_group(){
		$query = "select * from fm_goods_sub_info group by category order by category";
		$query = $this->db->query($query);
		$result = $query->result_array();
		return $result;
	}


	/* 상품코드 일괄 업데이트 리스트 */
	public function goodscode_batch_goods_list($sc) {
		$CI =& get_instance();

		if(!isset($_GET['page']))$_GET['page'] = 1;

		$sql = "select goods_seq from fm_goods where goods_type = 'goods' ";

		$sql .=" order by goods_seq desc ";
		$result = select_page($sc['limitnum'],$_GET['page'],10,$sql,'');
		$result['page']['querystring'] = get_args_list();

		return $result;
	}

	public function get_sale_price($goods_seq, $goods_price, $category, $sale_seq, $consumer_price =0, $goodsinfo = null){
		$this->load->library('sale');

		$applypage	= 'saleprice';

		//----> sale library 적용
		unset($param,$row['reserve'],$row['point']);
		$param['cal_type']				= 'each';
		$param['member_seq']			= $this->userInfo['member_seq'];
		$param['group_seq']				= $this->userInfo['group_seq'];
		$param['consumer_price']		= $consumer_price;
		$param['price']					= $goods_price;
		$param['ea']					= 1;
		$param['category_code']			= $category;
		$param['goods_seq']				= $goods_seq;
		$param['goods']					= $goodsinfo;
		$this->sale->set_init($param);
		$sales		= $this->sale->calculate_sale_price($applypage);
		$sale_price	= $sales['result_price'];
		$this->sale->reset_init();
		unset($sales);
		//<---- sale library 적용

		return $sale_price;
	}

	// 패키지 상품 가용재고
	public function modify_reservation_package_option($goods_seq){
		$goods_seqs = array();
		$query = "select po.goods_seq from fm_order_package_option po,fm_order_item i where po.item_seq=i.item_seq and i.goods_seq=?";
		$query = $this->db->query($query,array($goods_seq));
		foreach($query -> result_array() as $data){
			if( !in_array($data['goods_seq'],$goods_seqs) && $data['goods_seq'] ){
				$goods_seqs[] = $data['goods_seq'];
			}
		}
		foreach($goods_seqs as $real_goods_seq){
			if( $real_goods_seq ) $this->real_reservation_option($real_goods_seq);
		}

	}

	// 패키지 상품 가용재고
	public function modify_reservation_package_suboption($goods_seq)
	{
		$goods_seqs = array();
		$query = "select po.goods_seq from fm_order_package_suboption po,fm_order_item i where po.item_seq=i.item_seq and i.goods_seq=?";
		$query = $this->db->query($query,array($goods_seq));
		foreach($query -> result_array() as $data){
			if( !in_array($data['goods_seq'],$goods_seqs) && $data['goods_seq'] ){
				$goods_seqs[] = $data['goods_seq'];
			}
		}
		foreach($goods_seqs as $real_goods_seq){
			if( $real_goods_seq ) $this->real_reservation_option($real_goods_seq);
		}
	}

	public function real_reservation_option($goods_seq)
	{
		$query = "update fm_goods_supply set reservation15 = 0,reservation25 = 0 where goods_seq=? and option_seq";
		$this->db->query($query,array($goods_seq));
		$query = "update fm_goods_supply s,(
					select o.option_seq,ord.ea25,ord.ea15,ord.step35 from fm_goods_option o,
					(
						select *,if(part_ea_all>part_ea_send,part_ea_all-part_ea_send,0) as step35 from (
							select sum(if(io.step>=25 and io.step<=45,if(io.ea>io.refund_ea,io.ea-refund_ea,0),0)) ea25,
								sum(if(io.step>=15 and io.step<=45,if(io.ea>io.refund_ea,io.ea-refund_ea,0),0)) ea15,
								sum(if(io.step in(50,60,70),io.step55+io.step65+io.step75+io.refund_ea,0)) as part_ea_send,
								sum(if(io.step in(50,60,70),io.ea,0)) as part_ea_all,
								io.option1,io.option2,io.option3,io.option4,io.option5
							from fm_order_item i,fm_order_item_option io
							where i.goods_seq=?
								and i.item_seq=io.item_seq
								and ((io.step >= 15 and io.step <= 45) or io.step in (50,60,70))
								and (io.package_yn != 'y' OR io.package_yn is null)
							group by io.option1,io.option2,io.option3,io.option4,io.option5
						) tmp_opt
					) ord
					where o.goods_seq=?
					and ord.option1=o.option1
					and ord.option2=o.option2
					and ord.option3=o.option3
					and ord.option4=o.option4
					and ord.option5=o.option5
				) opt
				set s.reservation25 = opt.ea25+opt.step35,
					s.reservation15 = opt.ea15+opt.step35
				where
				s.option_seq=opt.option_seq";
		$this->db->query($query,array($goods_seq,$goods_seq));

		$query = "update fm_goods_supply s,
					(
						select o.option_seq,ord.ea25,ord.ea15,ord.step35 from fm_goods_option o,
						(
							select *,if(part_ea_all>part_ea_send,part_ea_all-part_ea_send,0) as step35 from
							(
								select sum(if(io.step>=25 and io.step<=45,if(io.ea>io.refund_ea,io.ea-refund_ea,0),0)*po.unit_ea) ea25,
									sum(if(io.step>=15 and io.step<=45,if(io.ea>io.refund_ea,io.ea-refund_ea,0),0)*po.unit_ea) ea15,
									sum(if(io.step in(50,60,70),io.step55+io.step65+io.step75+io.refund_ea,0)*po.unit_ea) as part_ea_send,
									sum(if(io.step in(50,60,70),io.ea,0)*po.unit_ea) as part_ea_all,
									po.option1,po.option2,po.option3,po.option4,po.option5,po.goods_seq
								from fm_order_package_option po,fm_order_item_option io,fm_order_item i
								where
									po.item_option_seq=io.item_option_seq
									and po.item_seq=i.item_seq
									and po.goods_seq=?
									and ((io.step >= 15 and io.step <= 45) or io.step in (50,60,70))
								group by po.goods_seq,po.option1,po.option2,po.option3,po.option4,po.option5
							) tmp_opt
						) ord
						where o.goods_seq=ord.goods_seq
						and o.option1=ord.option1
						and o.option2=ord.option2
						and o.option3=ord.option3
						and o.option4=ord.option4
						and o.option5=ord.option5
					) opt
				set s.reservation25 = s.reservation25 + opt.ea25 + opt.step35,
					s.reservation15 = s.reservation15 + opt.ea15 + opt.step35
				where
				s.option_seq=opt.option_seq
				";
		$query = $this->db->query($query,array($goods_seq));
		$query = "update fm_goods_supply s,
					(
						select o.option_seq,ord.ea25,ord.ea15,ord.step35 from fm_goods_option o,
						(
							select *,if(part_ea_all>part_ea_send,part_ea_all-part_ea_send,0) as step35 from
							(
								select sum(if(io.step>=25 and io.step<=45,if(io.ea>io.refund_ea,io.ea-refund_ea,0),0)*po.unit_ea) ea25,
									sum(if(io.step>=15 and io.step<=45,if(io.ea>io.refund_ea,io.ea-refund_ea,0),0)*po.unit_ea) ea15,
									sum(if(io.step in(50,60,70),io.step55+io.step65+io.step75+io.refund_ea,0)*po.unit_ea) as part_ea_send,
									sum(if(io.step in(50,60,70),io.ea,0)*po.unit_ea) as part_ea_all,
									po.option1,po.option2,po.option3,po.option4,po.option5,po.goods_seq
								from fm_order_package_suboption po,fm_order_item_suboption io,fm_order_item i
								where
									po.item_suboption_seq=io.item_suboption_seq
									and po.item_seq=i.item_seq
									and po.goods_seq=?
									and ((io.step >= 15 and io.step <= 45) or io.step in (50,60,70))
								group by po.goods_seq,po.option1,po.option2,po.option3,po.option4,po.option5
							) tmp_opt
						) ord
						where o.goods_seq=ord.goods_seq
						and o.option1=ord.option1
						and o.option2=ord.option2
						and o.option3=ord.option3
						and o.option4=ord.option4
						and o.option5=ord.option5
					) opt
				set s.reservation25 = s.reservation25 + opt.ea25 + opt.step35,
					s.reservation15 = s.reservation15 + opt.ea15 + opt.step35
				where
				s.option_seq=opt.option_seq
				";
		$query = $this->db->query($query,array($goods_seq));
	}

	public function real_reservation_suboption($goods_seq)
	{
		$query = "update fm_goods_supply set reservation15 = 0,reservation25 = 0 where goods_seq=? and suboption_seq";
		$this->db->query($query,array($goods_seq));
		$query = "update fm_goods_supply s,(
					select o.suboption_seq,ord.ea25,ord.ea15,ord.step35 from fm_goods_suboption o,
					(
						select *,if(part_ea_all>part_ea_send,part_ea_all-part_ea_send,0) as step35 from (
							select sum(if(io.step>=25 and io.step<=45,if(io.ea>io.refund_ea,io.ea-refund_ea,0),0)) ea25,
								sum(if(io.step>=15 and io.step<=45,if(io.ea>io.refund_ea,io.ea-refund_ea,0),0)) ea15,
								sum(if(io.step in(50,60,70),io.step55+io.step65+io.step75+io.refund_ea,0)) as part_ea_send,
								sum(if(io.step in(50,60,70),io.ea,0)) as part_ea_all,
								io.title,io.suboption
							from fm_order_item i,fm_order_item_suboption io
							where i.goods_seq=?
								and i.item_seq=io.item_seq
								and ((io.step >= 15 and io.step <= 45) or io.step in (50,60,70))
								and (io.package_yn != 'y' OR io.package_yn is null)
							group by io.title,io.suboption
						) tmp_opt
					) ord
					where o.goods_seq=?
					and ord.title = o.suboption_title
					and ord.suboption = o.suboption
				) opt
				set s.reservation25 = opt.ea25+opt.step35,
					s.reservation15 = opt.ea15+opt.step35
				where
				s.suboption_seq=opt.suboption_seq";
		$this->db->query($query,array($goods_seq,$goods_seq));
	}

	// 실제 주문을 검색하여 출고예약량을 업데이트합니다.
	public function modify_reservation_real($goods_seq,$mode='auto')
	{
		$data_goods = $this->get_goods($goods_seq);
		if( $data_goods['package_yn'] == 'y' ){
			$this->modify_reservation_package_option($goods_seq);
		}else{
			$this->real_reservation_option($goods_seq);
		}
		if( $data_goods['package_yn_suboption'] == 'y' ) {
			$this->modify_reservation_package_suboption($goods_seq);
		}else{
			$this->real_reservation_suboption($goods_seq);
		}

		$this->runout_check($goods_seq,$mode);
	}

	public function get_add_option_code(){
		$this->load->helper("goods");
		$query		= "select * from fm_goods_code_form "
					. "where label_type ='goodsoption' order by label_type, sort_seq";
		$rs			= $this->db->query($query);
		foreach($rs->result_array() as $code_datarow){
			$code_datarow['label_write']	= get_labelitem_type($code_datarow,'','');
			$codes							= explode('|', $code_datarow['label_code']);
			$values							= explode('|', $code_datarow['label_value']);
			$defaults						= explode('|', $code_datarow['label_default']);
			$colors							= explode('|', $code_datarow['label_color']);
			$zipcodes						= explode('|', $code_datarow['label_zipcode']);
			$address_type				= explode('|', $code_datarow['label_address_type']);
			$address						= explode('|', $code_datarow['label_address']);
			$address_street				= explode('|', $code_datarow['label_address_street']);
			$addressdetail				= explode('|', $code_datarow['label_addressdetail']);
			$biztel							= explode('|', $code_datarow['label_biztel']);
			$address_commission		= explode('|', $code_datarow['label_address_commission']);

			$codedate							= explode('|', $code_datarow['label_date']);
			$sdayinput							= explode('|', $code_datarow['label_sdayinput']);
			$fdayinput							= explode('|', $code_datarow['label_fdayinput']);
			$dayauto_type					= explode('|', $code_datarow['label_dayauto_type']);
			$sdayauto							= explode('|', $code_datarow['label_sdayauto']);
			$fdayauto							= explode('|', $code_datarow['label_fdayauto']);
			$dayauto_day					= explode('|', $code_datarow['label_dayauto_day']);

			$code_arr						= array();
			$codes_cnt						= count($codes);
			for ($c = 0; $c < $codes_cnt; $c++){
				if	($codes[$c]){
					$code_arr[]	= array(
						'code'=>$codes[$c],'value'=>$values[$c],'default'=>$defaults[$c],
						'colors'=>$colors[$c],
						'zipcode'=>$zipcodes[$c],'address_type'=>$address_type[$c],'address'=>$address[$c],'address_street'=>$address_street[$c],'addressdetail'=>$addressdetail[$c],'biztel'=>$biztel[$c],
						'codedate'=>$codedate[$c],
						'sdayinput'=>$sdayinput[$c],'fdayinput'=>$fdayinput[$c],
						'dayauto_type'=>$dayauto_type[$c],'sdayauto'=>$sdayauto[$c],'fdayauto'=>$fdayauto[$c],'dayauto_day'=>$dayauto_day[$c],'address_commission'=>$address_commission[$c]);
				}
			}
			$code_datarow['code_arr']		= $code_arr;

			$result[]						= $code_datarow;
		}

		return $result;
	}

	public function get_add_suboption_code(){
		$this->load->helper("goods");
		$query		= "select * from fm_goods_code_form  "
					. "where label_type ='goodssuboption'  order by label_type, sort_seq";
		$rs			= $this->db->query($query);
		foreach($rs->result_array() as $code_datarow){
			$code_datarow['label_write']	= get_labelitem_type($code_datarow,'','');
			$codes							= explode('|', $code_datarow['label_code']);
			$values							= explode('|', $code_datarow['label_value']);
			$defaults						= explode('|', $code_datarow['label_default']);

			$colors							= explode('|', $code_datarow['label_color']);
			$zipcodes						= explode('|', $code_datarow['label_zipcode']);
			$address_type					= explode('|', $code_datarow['label_address_type']);
			$address						= explode('|', $code_datarow['label_address']);
			$address_street					= explode('|', $code_datarow['label_address_street']);
			$addressdetail					= explode('|', $code_datarow['label_addressdetail']);
			$biztel							= explode('|', $code_datarow['label_biztel']);

			$codedate						= explode('|', $code_datarow['label_date']);
			$sdayinput						= explode('|', $code_datarow['label_sdayinput']);
			$fdayinput						= explode('|', $code_datarow['label_fdayinput']);
			$dayauto_type					= explode('|', $code_datarow['label_dayauto_type']);
			$sdayauto						= explode('|', $code_datarow['label_sdayauto']);
			$fdayauto						= explode('|', $code_datarow['label_fdayauto']);
			$dayauto_day					= explode('|', $code_datarow['label_dayauto_day']);

			$code_arr						= array();
			$codes_cnt						= count($codes);
			for ($c = 0; $c < $codes_cnt; $c++){
				if	($codes[$c]){
					$code_arr[]	= array('code'=>$codes[$c],'value'=>$values[$c],'default'=>$defaults[$c],
						'colors'=>$colors[$c],
						'zipcode'=>$zipcodes[$c],'address_type'=>$address_type[$c],'address'=>$address[$c],'address_street'=>$address_street[$c],'addressdetail'=>$addressdetail[$c],'biztel'=>$biztel[$c],
						'codedate'=>$codedate[$c],
						'sdayinput'=>$sdayinput[$c],'fdayinput'=>$fdayinput[$c],
						'dayauto_type'=>$dayauto_type[$c],'sdayauto'=>$sdayauto[$c],'fdayauto'=>$fdayauto[$c],'dayauto_day'=>$dayauto_day[$c]);
				}
			}
			$code_datarow['code_arr']		= $code_arr;

			$result[]						= $code_datarow;
		}

		return $result;
	}

	/**
	* 최초생성된 임시정보 가져오기
	**/
	public function get_option_tmp_list($tmp_seq){
		$opt1Arr	= array();$opt2Arr	= array();$opt3Arr	= array();$opt4Arr	= array();$opt5Arr	= array();
		$code1Arr	= array();$code2Arr	= array();$code3Arr	= array();$code4Arr	= array();$code5Arr	= array();
		$price1Arr	= array();$price2Arr	= array();$price3Arr	= array();$price4Arr	= array();$price5Arr	= array();
		$color1Arr	= array();$color2Arr	= array();	$color3Arr= array();$color4Arr	= array();$color5Arr	= array();
		$zipcode1Arr	= array();$zipcode2Arr	= array();$zipcode3Arr	= array();$zipcode4Arr	= array();$zipcode5Arr	= array();
		$address1Arr	= array();$address_typeArr	= array();$address_streetArr	= array();
		$address2Arr	= array();$address3Arr	= array();$address4Arr	= array();$address5Arr	= array();
		$addressdetail1Arr	= array();$addressdetail2Arr	= array();$addressdetail3Arr	= array();$addressdetail4Arr	= array();$addressdetail5Arr	= array();

		$address_commission1Arr	= array();$address_commission2Arr	= array();$address_commission3Arr	= array();$address_commission4Arr	= array();$address_commission5Arr	= array();


		$biztel1Arr	= array();$biztel2Arr	= array();$biztel3Arr	= array();$biztel4Arr	= array();$biztel5Arr	= array();
		$codedate1Arr	= array();$codedate2Arr	= array();$codedate3Arr	= array();$codedate4Arr	= array();$codedate5Arr	= array();

		$result		= false;
		$sql = "select
					o.*,s.badstock, s.stock, s.supply_price, s.exchange_rate,
					s.reservation15, s.reservation25, s.total_supply_price,
					s.safe_stock, s.total_stock, s.total_badstock
				from
					fm_goods_option_tmp o left join fm_goods_supply_tmp s on o.option_seq=s.option_seq
				where
					o.tmp_no=?
				order by o.option_seq asc";
		$query = $this->db->query($sql,array($tmp_seq));
		foreach($query->result_array() as $data){
			$optJoin = "";$optcodeJoin = "";
			if( $data['option_title'] ) $data['option_divide_title'] = explode(',',$data['option_title']);

			if( $data['option_type'] ) $data['option_divide_type'] = explode(',',$data['option_type']);
			if( $data['code_seq'] ) $data['option_divide_codeseq'] = explode(',',$data['code_seq']);

			if( $data['tmpprice'] ) $data['divide_tmpprice'] = explode(',',$data['tmpprice']);
			if( $data['newtype'] ) $data['divide_newtype'] = explode(',',$data['newtype']);

			if( $data['color'] )					$data['divide_color']				= trim($data['color']);
			if( $data['zipcode'] )				$data['divide_zipcode']			= ($data['zipcode']);
			if( $data['address_type'] )			$data['divide_address_type']			= ($data['address_type']);
			if( $data['address'] )			$data['divide_address']			= ($data['address']);
			if( $data['address_street'] )			$data['divide_address_street']			= ($data['address_street']);
			if( $data['addressdetail'] )	$data['divide_addressdetail']	= ($data['addressdetail']);
			if( $data['biztel'] )				$data['divide_biztel']					= ($data['biztel']);
			if( $data['address_commission'] )	$data['divide_address_commission']	= ($data['address_commission']);
			if( $data['codedate'] )			$data['divide_codedate']			= ($data['codedate']);

			if( $data['dayauto_type'] ) $data['dayauto_type_title'] = $this->dayautotype[$data['dayauto_type']];
			if( $data['dayauto_day'] ) $data['dayauto_day_title'] = $this->dayautoday[$data['dayauto_day']];

			if( $data['newtype'] )			$data['divide_newtype']			= explode(',',$data['newtype']);

			if( $data['option1']!='' ) $optJoin[] = $data['option1'];
			if( $data['option2']!='' ) $optJoin[] = $data['option2'];
			if( $data['option3']!='' ) $optJoin[] = $data['option3'];
			if( $data['option4']!='' ) $optJoin[] = $data['option4'];
			if( $data['option5']!='' ) $optJoin[] = $data['option5'];
			if( $optJoin ){
				$data['opts'] = $optJoin;
			}
			if( $data['option1']!='' ) $optcodeJoin[] = $data['optioncode1'];
			if( $data['option2']!='' ) $optcodeJoin[] = $data['optioncode2'];
			if( $data['option3']!='' ) $optcodeJoin[] = $data['optioncode3'];
			if( $data['option4']!='' ) $optcodeJoin[] = $data['optioncode4'];
			if( $data['option5']!='' ) $optcodeJoin[] = $data['optioncode5'];
			if( $optcodeJoin ){
				$data['optcodes'] = $optcodeJoin;
			}

			// 세로로 묶음
			if( $data['option1']!='' && !in_array($data['option1'], $opt1Arr)){
				$opt1Arr[]		= $data['option1'];
				$code1Arr[]		= $data['optioncode1'];
				$price1Arr[]		= $data['divide_tmpprice'][0];
			}
			if( $data['option2'] != '' && !in_array($data['option2'], $opt2Arr) ){
				$opt2Arr[]		= $data['option2'];
				$code2Arr[]		= $data['optioncode2'];
				$price2Arr[]		= $data['divide_tmpprice'][1];
			}

			if( $data['option3'] != '' && !in_array($data['option3'], $opt3Arr) ){
				$opt3Arr[]		= $data['option3'];
				$code3Arr[]		= $data['optioncode3'];
				$price3Arr[]		= $data['divide_tmpprice'][2];
			}
			if( $data['option4'] != '' && !in_array($data['option4'], $opt4Arr) ){
				$opt4Arr[]		= $data['option4'];
				$code4Arr[]		= $data['optioncode4'];
				$price4Arr[]		= $data['divide_tmpprice'][3];
			}
			if( $data['option5'] != '' && !in_array($data['option5'], $opt5Arr) ){
				$opt5Arr[]		= $data['option5'];
				$code5Arr[]		= $data['optioncode5'];
				$price5Arr[]		= $data['divide_tmpprice'][4];
			}

			# 정산금액
			# 정산율 : 매입가(기준통화) / 정가(기준통화) * 100
			# 할인율 : 판매가 / 정가 * 100
			if	($data['consumer_price']){
				$data['supplyRate'] = floor($data['supply_price'] / $data['consumer_price'] * 100);
				$data['discountRate'] = 100 - floor($data['price'] / $data['consumer_price'] * 100);
			}
			$data['tax']		= floor($data['price'] - ($data['price'] / 1.1));
			$data['net_profit']	= $data['price'] - $data['supply_price'];
			$arr = explode(',',$data['option_title']);
			$data['title1'] 	= $arr[0];
			$data['title2'] 	= $arr[1];
			$data['title3'] 	= $arr[2];
			$data['title4'] 	= $arr[3];
			$data['title5'] 	= $arr[4];

			// 패키지 옵션 재고 가져오기 2018-04-12
			if($data['package_option_seq1']){
				for($i=1;$i<=5;$i++){
					if($data['package_option_seq'.$i]){
						$data_package 					= $this->get_package_by_option_seq($data['package_option_seq'.$i]);
						$data['package_goods_seq'.$i] 	= $data_package['package_goods_seq'];
						$data['package_stock'.$i] 		= $data_package['package_stock'];
						$data['package_badstock'.$i] 	= $data_package['package_badstock'];
						$data['package_ablestock'.$i] 	= $data_package['package_ablestock'];
						$data['package_goods_code'.$i] 	= $data_package['package_goods_code'];
						$data['package_option_code'.$i] = $data_package['package_option_code'];
						$data['weight'.$i] 				= $data_package['weight'];

						if(empty($data_min) || ($data_min['package_stock'] > $data_package['package_stock']) ) {
							$data_min = $data_package;
						}
					}
				}

				$data['rstock'] = (int) $data_min['package_ablestock'];
				$data['stock'] = (int) $data_min['package_stock'];
				$data['badstock'] = (int) $data_min['package_badstock'];
				$data['reservation15'] = (int) $data_min['package_reservation15'];
				$data['reservation25'] = (int) $data_min['package_reservation25'];
				$data['unit_ea'] = (int) $data_min['unit_ea'];
				$data['safe_stock'] = (int) $data_min['package_safe_stock'];
			}

			$data['option_count'] = count($optJoin);

			$result[] = $data;
		}

		if	($result[0]){
			$result[0]['optionArr'][]	= $opt1Arr;
			$result[0]['optionArr'][]	= $opt2Arr;
			$result[0]['optionArr'][]	= $opt3Arr;
			$result[0]['optionArr'][]	= $opt4Arr;
			$result[0]['optionArr'][]	= $opt5Arr;
			$result[0]['codeArr'][]		= $code1Arr;
			$result[0]['codeArr'][]		= $code2Arr;
			$result[0]['codeArr'][]		= $code3Arr;
			$result[0]['codeArr'][]		= $code4Arr;
			$result[0]['codeArr'][]		= $code5Arr;
			$result[0]['priceArr'][]	= $price1Arr;
			$result[0]['priceArr'][]	= $price2Arr;
			$result[0]['priceArr'][]	= $price3Arr;
			$result[0]['priceArr'][]	= $price4Arr;
			$result[0]['priceArr'][]	= $price5Arr;

			//등록된 옵션정보의 상품코드 정보 가져오기
			for ($o = 0; $o < 5; $o++) {
				if ($result[0]['divide_newtype'][$o] == 'color' ) {
					$codeqry = "select * from fm_goods_code_form  where label_type ='goodsoption' and codeform_seq = '".$result[0]['option_divide_codeseq'][$o]."' order by label_type, sort_seq";
					$codequery = $this->db->query($codeqry);
					$code_arr = $codequery -> result_array();
					foreach ($code_arr as $code_datarow){
						$label_code_ar = explode("|", $code_datarow['label_code']);
						$label_color_ar = explode("|", $code_datarow['label_color']);
						$idx=0;
						foreach($label_code_ar as $code) {
							if( in_array( $code , $result[0]['codeArr'][$o]) ) {
								$colorArr[] = $label_color_ar[$idx];
							}
							$idx++;
						}
					}
					$result[0]['colorArr'][]					= $colorArr;
				}elseif($result[0]['divide_newtype'][$o] == 'address' ) {
					$codeqry = "select * from fm_goods_code_form  where label_type ='goodsoption' and codeform_seq = '".$result[0]['option_divide_codeseq'][$o]."' order by label_type, sort_seq";
					$codequery = $this->db->query($codeqry);
					$code_arr = $codequery -> result_array();
					foreach ($code_arr as $code_datarow){
						$label_code_ar = explode("|", $code_datarow['label_code']);
						$label_zipcode_ar = explode("|", $code_datarow['label_zipcode']);
						$label_address_type_ar = explode("|", $code_datarow['label_address_type']);
						$label_address_ar = explode("|", $code_datarow['label_address']);
						$label_address_street_ar = explode("|", $code_datarow['label_address_street']);
						$label_addressdetail_ar = explode("|", $code_datarow['label_addressdetail']);
						$label_biztel_ar = explode("|", $code_datarow['label_biztel']);
						$label_address_commission_ar = explode("|", $code_datarow['label_address_commission']);
						$idx=0;
						foreach($label_code_ar as $code) {
							if( in_array( $code , $result[0]['codeArr'][$o]) ) {
								$zipcodeArr[] = $label_zipcode_ar[$idx];
								$address_typeArr[] = $label_address_type_ar[$idx];
								$addressArr[] = $label_address_ar[$idx];
								$address_streetArr[] = $label_address_street_ar[$idx];
								$addressdetailArr[] = $label_addressdetail_ar[$idx];
								$biztelArr[] = $label_biztel_ar[$idx];
								$address_commissionArr[] = $label_address_commission_ar[$idx];
							}
							$idx++;
						}
					}
					$result[0]['zipcodeArr'][]				= $zipcodeArr;
					$result[0]['address_typeArr'][]			= $address_typeArr;
					$result[0]['addressArr'][]				= $addressArr;
					$result[0]['address_streetArr'][]		= $address_streetArr;
					$result[0]['addressdetailArr'][]	= $addressdetailArr;
					$result[0]['biztelArr'][]					= $biztelArr;
					$result[0]['address_commissionArr'][]					= $address_commissionArr;
				}elseif($result[0]['divide_newtype'][$o] == 'date' ){
					$codeqry = "select * from fm_goods_code_form  where label_type ='goodsoption' and codeform_seq = '".$result[0]['option_divide_codeseq'][$o]."' order by label_type, sort_seq";
					$codequery = $this->db->query($codeqry);
					$code_arr = $codequery -> result_array();
					foreach ($code_arr as $code_datarow){
						$label_code_ar = explode("|", $code_datarow['label_code']);
						$label_date_ar = explode("|", $code_datarow['label_date']);
						$idx=0;
						foreach($label_code_ar as $code) {
							if( in_array( $code , $result[0]['codeArr'][$o]) ) {
								$codedateArr[] = $label_date_ar[$idx];
							}
							$idx++;
						}
					}
					$result[0]['codedateArr'][]			= $codedateArr;
				}
			}
		}

		return $result;
	}

	public function get_suboption_tmp_list($tmp_seq, $mode = ''){
		$result	= false;
		$arr	= array();
		// 적립금 기본 정책
		$reserves		= config_load('reserve');
		$reserve_rate	= $reserves['default_reserve_percent'];
		$cfg_order		= config_load('order');

		$query	= "select o.*,s.stock,s.badstock,s.supply_price,s.exchange_rate, s.reservation15, s.reservation25, s.safe_stock, s.total_supply_price, s.total_stock, s.total_badstock from fm_goods_suboption_tmp o,fm_goods_supply_tmp s where o.suboption_seq=s.suboption_seq and o.tmp_no=? order by o.suboption_seq asc";
		$query	= $this->db->query($query,array($tmp_seq));
		$idx	= 0;
		foreach($query->result_array() as $data){

			if(!in_array($data['suboption_title'],$arr)) $arr[] = $data['suboption_title'];
			list($key) = array_keys($arr,$data['suboption_title']);
			$data['idx']		= $idx;
			if	($data['consumer_price']){
				$data['supplyRate']		= get_cutting_price($data['supply_price'] / $data['consumer_price'] * 100);
				$data['discountRate']	= 100 - get_cutting_price($data['price'] / $data['consumer_price'] * 100);
			}
			$data['tax']		= get_cutting_price($data['price'] - ($data['price'] / 1.1));
			$data['net_profit']	= $data['price'] - $data['supply_price'];

			if($data['package_option_seq1']){
				$data_package					= $this->get_package_by_option_seq($data['package_option_seq1']);
				$data['package_goods_seq1']		= $data_package['package_goods_seq'];
				$data['package_stock1']			= $data_package['package_stock'];
				$data['package_badstock1']		= $data_package['package_badstock'];
				$data['package_ablestock1']		= $data_package['package_ablestock'];
				$data['package_safe_stock1']	= $data_package['package_safe_stock'];
				$data['package_option_code1'] = $data_package['package_option_code'];
				$data['weight1'] = $data_package['weight'];
			}

			if	($key != $bkey){
				$result[$bkey][0]['optArr']				= $optArr;
				$result[$bkey][0]['codeArr']			= $codeArr;
				$result[$bkey][0]['priceArr']			= $priceArr;

				$result[$bkey][0]['colorArr']			= $colorArr;
				$result[$bkey][0]['zipcodeArr']			= $zipcodeArr;
				$result[$bkey][0]['address_typeArr']	= $address_typeArr;
				$result[$bkey][0]['addressArr']			= $addressArr;
				$result[$bkey][0]['address_streetArr']	= $address_streetArr;
				$result[$bkey][0]['addressdetailArr']	= $addressdetailArr;
				$result[$bkey][0]['biztelArr']			= $biztelArr;
				$result[$bkey][0]['codedateArr']		= $codedateArr;

				unset($optArr,$codeArr,$codeArr,$priceArr,$colorArr,$zipcodeArr,$address_typeArr,$addressArr,$address_streetArr,$addressdetailArr,$biztelArr,$codedateArr);
				/**
				$optArr		= '';
				$codeArr	= '';
				$priceArr	= '';
				$colorArr		= '';
				$zipcodeArr	= '';
				$addressArr	= '';
				$addressdetailArr	= '';
				$biztelArr	= '';
				$codedateArr	= '';
				**/

			}
			$bkey		= $key;
			$optArr[]	= $data['suboption'];
			$codeArr[]	= $data['suboption_code'];

			$colorArr[]							= trim($data['color']);
			$zipcodeArr[]						= $data['zipcode'];
			$address_typeArr[]					= $data['address_type'];
			$addressArr[]						= $data['address'];
			$address_streetArr[]				= $data['address_street'];
			$addressdetailArr[]					= $data['addressdetail'];
			$biztelArr[]						= $data['biztel'];
			$codedateArr[]						= $data['codedate'];

			$priceArr[]	= floor($data['price']);

			if	($mode == 'chgPolicy'){
				$data['reserve_rate']	= $reserve_rate;
				$data['reserve_unit']	= 'percent';
				$data['reserve']		= 0;
				if	($data['price'] > 0){
					$data['reserve']	= round($data['price'] * ($reserve_rate * 0.01));
				}

				$params['reserve_rate']	= $data['reserve_rate'];
				$params['reserve_unit']	= $data['reserve_unit'];
				$params['reserve']		= $data['reserve'];
				$this->db->where(array("tmp_no"=>$tmp_seq,"suboption_seq"=>$data['suboption_seq']));
				$this->db->update('fm_goods_suboption_tmp', $params);
			}

			if( $data['dayauto_type'] ) $data['dayauto_type_title'] = $this->dayautotype[$data['dayauto_type']];
			if( $data['dayauto_day'] ) $data['dayauto_day_title'] = $this->dayautoday[$data['dayauto_day']];

			$result[$key][] = $data;
			$idx++;
		}

		if	($result[$key][0]){
			$result[$key][0]['optArr']		= $optArr;
			$result[$key][0]['codeArr']	= $codeArr;
			$result[$key][0]['priceArr']	= $priceArr;

			$result[$key][0]['colorArr']					= $colorArr;
			$result[$key][0]['zipcodeArr']				= $zipcodeArr;
			$result[$key][0]['address_typeArr']				= $address_typeArr;
			$result[$key][0]['addressArr']				= $addressArr;
			$result[$key][0]['address_streetArr']				= $address_streetArr;
			$result[$key][0]['addressdetailArr']		= $addressdetailArr;
			$result[$key][0]['biztelArr']					= $biztelArr;
			$result[$key][0]['codedateArr']			= $codedateArr;
		}

		return $result;
	}

	/**
	* 필수옵션 수정시 임시옵션정보 생성 (opt 2단계)
	**/
	public function add_option_tmp_to_option_org($goods_seq, $fromType = '', $provider_info = false){

		$this->load->model('scmmodel');
		$tmp_seq	= date('YmdHis').$this->managerInfo['manager_id'];

		if	($goods_seq){
			$query		= "select * from fm_goods_option where goods_seq = ? ";
			$rs			= $this->db->query($query,array($goods_seq));
			foreach($rs->result_array() as $list){
				$tmp_policy = ($_GET['tmp_policy']=='goods')? 'goods' : 'shop';
				$org_option_seq	= $list['option_seq'];
				$default		= 'n';
				$reserve_unit	= 'percent';

				$supplySql		= "select * from fm_goods_supply where option_seq = ? ";
				$supplyRs		= $this->db->query($supplySql,array($org_option_seq));
				$org_supply		= $supplyRs->result_array();
				$orgSupply		= $org_supply[0];

				if($list['default_option'] == 'y' && $setDefaultY != 'y'){
					$setDefaultY = $default = 'y';
				}
				if(!is_null($list['reserve_unit']))
					$reserve_unit	= $list['reserve_unit'];

				$options['goods_seq']			= $this->optionReplace($goods_seq, 'int');
				$options['code_seq']			= $list['code_seq'];
				$options['option_type']			= $list['option_type'];
				$options['default_option']		= $default;
				$options['option_title']		= $this->optionReplace($list['option_title']);
				$options['option1']				= $this->optionReplace($list['option1']);
				$options['option2']				= $this->optionReplace($list['option2']);
				$options['option3']				= $this->optionReplace($list['option3']);
				$options['option4']				= $this->optionReplace($list['option4']);
				$options['option5']				= $this->optionReplace($list['option5']);
				$options['optioncode1']			= $list['optioncode1'];
				$options['optioncode2']			= $list['optioncode2'];
				$options['optioncode3']			= $list['optioncode3'];
				$options['optioncode4']			= $list['optioncode4'];
				$options['optioncode5']			= $list['optioncode5'];
				$options['coupon_input']		= $this->optionReplace($list['coupon_input'], 'float');
				$options['consumer_price']		= $this->optionReplace($list['consumer_price'], 'float');
				$options['price']				= $this->optionReplace($list['price'], 'float');
				$options['reserve_rate']		= $this->optionReplace($list['reserve_rate'], 'float');
				$options['reserve_unit']		= $reserve_unit;
				$options['reserve']				= $this->optionReplace($list['reserve'], 'float');
				$options['infomation']			= $list['infomation'];
				$options['tmp_policy']			= $tmp_policy;
				$options['tmp_date']			= date('Ymd');
				$options['tmp_no']				= $tmp_seq;
				$options['weight']				= $list['weight'];
				$options['option_view']			= $list['option_view'];

				if($list['commission_type'] == $provider_info['commission_type']){
					$options['commission_rate']	= $this->optionReplace($list['commission_rate'], 'float', false);
					$options['commission_type']	= $list['commission_type'];
				}else if($list['commission_type'] == 'SACO' && $provider_info['commission_type'] != 'SACO'){
					$options['commission_rate']	= $provider_info['charge'];
					$options['commission_type']	= ($provider_info['commission_type']) ? $provider_info['commission_type'] : 'SACO';
				}else if($list['commission_type'] != 'SACO' && $provider_info['commission_type'] != 'SACO'){
					$options['commission_rate']	= $this->optionReplace($list['commission_rate'], 'float', false);
					$options['commission_type']	= $list['commission_type'];
				}

				$options['newtype']				= $list['newtype'];
				$options['tmpprice']				= $list['tmpprice'];
				$options['color']						= trim($list['color']);
				$options['zipcode']					= $list['zipcode'];
				$options['address_type']			= $list['address_type'];
				$options['address']					= $list['address'];
				$options['address_street']		= $list['address_street'];
				$options['addressdetail']			= $list['addressdetail'];
				$options['biztel']						= $list['biztel'];
				$options['address_commission']				= $list['address_commission'];

				$options['codedate']	= $list['codedate'];
				$options['sdayinput']	= $list['sdayinput'];
				$options['fdayinput']	= $list['fdayinput'];
				$options['dayauto_type']	= $list['dayauto_type'];
				$options['sdayauto']	= $list['sdayauto'];
				$options['fdayauto']	= $list['fdayauto'];
				$options['dayauto_day']	= $list['dayauto_day'];

				// 패키지 상품
				$options['package_count']	= $list['package_count'];
				$options['package_option1']	= $list['package_option1'];
				$options['package_option2']	= $list['package_option2'];
				$options['package_option3']	= $list['package_option3'];
				$options['package_option4']	= $list['package_option4'];
				$options['package_option5']	= $list['package_option5'];
				$options['package_option_seq1']	= $list['package_option_seq1'];
				$options['package_option_seq2']	= $list['package_option_seq2'];
				$options['package_option_seq3']	= $list['package_option_seq3'];
				$options['package_option_seq4']	= $list['package_option_seq4'];
				$options['package_option_seq5']	= $list['package_option_seq5'];
				$options['package_goods_name1']	= $list['package_goods_name1'];
				$options['package_goods_name2']	= $list['package_goods_name2'];
				$options['package_goods_name3']	= $list['package_goods_name3'];
				$options['package_goods_name4']	= $list['package_goods_name4'];
				$options['package_goods_name5']	= $list['package_goods_name5'];
				$options['package_unit_ea1']	= $list['package_unit_ea1'];
				$options['package_unit_ea2']	= $list['package_unit_ea2'];
				$options['package_unit_ea3']	= $list['package_unit_ea3'];
				$options['package_unit_ea4']	= $list['package_unit_ea4'];
				$options['package_unit_ea5']	= $list['package_unit_ea5'];

				$options['fix_option_seq']	= $list['fix_option_seq'];
				if	($fromType == 'import')	$options['org_option_seq']		= '0';
				else						$options['org_option_seq']		= $org_option_seq;

				//색상옵션일때 값이 없으면 기본 흰색으로 저장합니다. @2016-07-27 ysm 2
				if( strstr($options['newtype'],'color') && !$options['color'] ) $options['color'] = '#fff';

				$this->db->insert( 'fm_goods_option_tmp', $options );
				$option_seq	= $this->db->insert_id();

				if	($option_seq){
					unset($supply);
					$supply['goods_seq']			= $this->optionReplace($goods_seq, 'int');
					$supply['option_seq']			= $option_seq;
					//올인원 본사상품은 재고 0처리 @2017-04-26
					if	($this->scmmodel->chkScmConfig(true) && $_GET['provider_seq'] == 1 && defined('__ADMIN__') === true && $_GET['add_goods_seq'] ){
							$supply['supply_price']		= '0';
							$supply['stock']			= '0';
							$supply['badstock']			= '0';
							$supply['reservation15']	= '0';
							$supply['reservation25']	= '0';
							$supply['ablestock15']		= '0';
							$supply['safe_stock']		= '0';
							$supply['total_stock']		= '0';
							$supply['total_badstock']	= '0';
					}else{
						$supply['supply_price']			= $this->optionReplace($orgSupply['supply_price'], 'float');
						$supply['stock']				= $this->optionReplace($orgSupply['stock'], 'int');
						$supply['badstock']				= $this->optionReplace($orgSupply['badstock'], 'int');
						$supply['reservation15']		= $this->optionReplace($orgSupply['reservation15'], 'int');
						$supply['reservation25']		= $this->optionReplace($orgSupply['reservation25'], 'int');
						$supply['ablestock15']			= $this->optionReplace($orgSupply['ablestock15'], 'int');
						$supply['safe_stock']			= $this->optionReplace($orgSupply['safe_stock'], 'int');
						$supply['total_stock']			= $this->optionReplace($orgSupply['total_stock'], 'int');
						$supply['total_badstock']		= $this->optionReplace($orgSupply['total_badstock'], 'int');
					}
					$supply['total_supply_price']	= $this->optionReplace($orgSupply['total_supply_price'], 'float');
					$supply['exchange_rate']		= $this->optionReplace($orgSupply['exchange_rate'], 'float');
					$supply['tmp_date']				= date('Ymd');
					$supply['tmp_no']				= $tmp_seq;
					$this->db->insert( 'fm_goods_supply_tmp', $supply );
				}
			}
		}

		return $tmp_seq;
	}


	/**
	* 추가구성옵션 수정시 임시옵션정보 생성 (subopt 2단계)
	**/
	public function add_suboption_tmp_to_suboption_org($goods_seq, $mode = '', $fromType = ''){

		$tmp_seq	= date('YmdHis').$this->managerInfo['manager_id'];
		// 적립금 기본 정책
		$reserves		= config_load('reserve');
		$reserve_rate	= $reserves['default_reserve_percent'];

		$query		= "select * from fm_goods_suboption where goods_seq = ? ";
		$rs			= $this->db->query($query,array($goods_seq));
		foreach($rs->result_array() as $list){
			$org_suboption_seq	= $list['suboption_seq'];
			$supplySql			= "select * from fm_goods_supply where suboption_seq = ? ";
			$supplyRs			= $this->db->query($supplySql,array($org_suboption_seq));
			$org_supply			= $supplyRs->result_array();
			$orgSupply			= $org_supply[0];

			if	($list['sub_required'] != 'y')	$list['sub_required']	= 'n';
			if	($list['sub_sale'] != 'y')		$list['sub_sale']		= 'n';

			if	($mode == 'chgPolicy'){
				$list['reserve_rate']	= $reserve_rate;
				$list['reserve_unit']	= 'percent';
				$list['reserve']		= 0;
				if	($list['price'] > 0){
					$list['reserve']	= round($list['price'] * ($reserve_rate * 0.01));
				}
			}


			$options['goods_seq']		= $this->optionReplace($goods_seq, 'int');
			$options['code_seq']		= $list['code_seq'];
			$options['sub_required']	= $list['sub_required'];
			$options['sub_sale']		= $list['sub_sale'];
			$options['suboption_type']	= $list['suboption_type'];
			$options['suboption_title']	= $this->optionReplace($list['suboption_title']);
			$options['suboption_code']	= $list['suboption_code'];
			$options['suboption']		= $this->optionReplace($list['suboption']);
			$options['coupon_input']	= $this->optionReplace($list['coupon_input'], 'float');
			$options['consumer_price']	= $this->optionReplace($list['consumer_price'], 'float');
			$options['price']			= $this->optionReplace($list['price'], 'float');
			$options['reserve_rate']	= $this->optionReplace($list['reserve_rate'],'float',false);
			$options['reserve_unit']	= $list['reserve_unit'];
			$options['commission_rate']	= $this->optionReplace($list['commission_rate'], 'float', false);
			$options['commission_type']	= $list['commission_type'];
			$options['reserve']			= $list['reserve'];
			$options['tmp_date']		= date('Ymd');
			$options['tmp_no']			= $tmp_seq;

			$options['newtype']			= $list['newtype'];
			$options['color']			= trim($list['color']);
			$options['zipcode']			= $list['zipcode'];
			$options['address_type']	= $list['address_type'];
			$options['address']			= $list['address'];
			$options['address_street']	= $list['address_street'];
			$options['addressdetail']	= $list['addressdetail'];
			$options['biztel']			= $list['biztel'];

			$options['codedate']		= $list['codedate'];
			$options['sdayinput']		= $list['sdayinput'];
			$options['fdayinput']		= $list['fdayinput'];
			$options['dayauto_type']	= $list['dayauto_type'];
			$options['sdayauto']		= $list['sdayauto'];
			$options['fdayauto']		= $list['fdayauto'];
			$options['dayauto_day']		= $list['dayauto_day'];
			$options['weight']			= $list['weight'];
			$options['option_view']		= ($list['option_view'] == 'N') ? 'N' : 'Y';

			// 패키지 상품
			$options['package_count']		= $list['package_count'];
			$options['package_option1']		= $list['package_option1'];
			$options['package_option_seq1']	= $list['package_option_seq1'];
			$options['package_goods_name1']	= $list['package_goods_name1'];
			$options['package_unit_ea1']	= $list['package_unit_ea1'];

			if	($fromType == 'import')	$options['org_suboption_seq']		= '0';
			else						$options['org_suboption_seq']		= $org_suboption_seq;
			$this->db->insert( 'fm_goods_suboption_tmp', $options );
			$suboption_seq	= $this->db->insert_id();

			if	($suboption_seq){
				unset($supply);
				$supply['goods_seq']			= $this->optionReplace($goods_seq, 'int');
				$supply['suboption_seq']		= $suboption_seq;
				$supply['supply_price']			= $this->optionReplace($orgSupply['supply_price'], 'float');
				$supply['exchange_rate']		= $this->optionReplace($orgSupply['exchange_rate'], 'float');
				$supply['stock']				= $this->optionReplace($orgSupply['stock'], 'int');
				$supply['badstock']				= $this->optionReplace($orgSupply['badstock'], 'int');
				$supply['reservation15']		= $this->optionReplace($orgSupply['reservation15'], 'int');
				$supply['reservation25']		= $this->optionReplace($orgSupply['reservation25'], 'int');
				$supply['ablestock15']			= $this->optionReplace($orgSupply['ablestock15'], 'int');
				$supply['safe_stock']			= $this->optionReplace($orgSupply['safe_stock'], 'int');
				$supply['total_supply_price']	= $this->optionReplace($orgSupply['total_supply_price'], 'float');
				$supply['total_stock']			= $this->optionReplace($orgSupply['total_stock'], 'int');
				$supply['total_badstock']		= $this->optionReplace($orgSupply['total_badstock'], 'int');
				$supply['tmp_date']				= date('Ymd');
				$supply['tmp_no']				= $tmp_seq;
				$this->db->insert( 'fm_goods_supply_tmp', $supply );
			}
		}

		return $tmp_seq;
	}

	/**
	* 필수옵션 임시옵션정보 생성 (opt 1단계)
	**/
	public function make_tmp_option($params) {

		if( $params['socialcpuseopen'] == 'price' || $params['socialcpuseopen'] == 'pass' ) {
			if( !( in_array('date',$params['optionMakenewtype'])
					|| in_array('dayinput',$params['optionMakenewtype'])
					|| in_array('dayauto',$params['optionMakenewtype'])) ){//coupon goods
				$msg = "[티켓상품]의 유효기간(지역, 날짜, 자동기간, 수동기간)옵션을 추가해 주세요.";
				openDialogAlert($msg,470,170,'parent',$callback);
				exit;
			}
		}

		$query	= "delete from fm_goods_option_tmp where tmp_no = '".$params['tmp_seq']."'";
		$this->db->query($query);
		$query	= "delete from fm_goods_supply_tmp "
				. "where tmp_no = '".$params['tmp_seq']."' and option_seq > 0";
		$this->db->query($query);

		$goods_seq					= trim($params['goods_seq']);
		$default_commission_rate	= ($params['default_commission_rate']) ? $params['default_commission_rate'] : 0;
		$default_commission_type	= ($params['default_commission_type']) ? $params['default_commission_type'] : 'SACO';

		$tmp_seq					= trim($params['tmp_seq']);
		$socialcpuseopen			= trim($params['socialcpuseopen']);
		$defaults					= 'y';

		// 적립금 기본 정책
		$reserves		= config_load('reserve');
		$reserve_rate	= $reserves['default_reserve_percent'];

		// 입점사 정보 ( 기본 수수료 정보 )
		if	($params['provider_seq'] > 1 && !$default_commission_rate){
			$this->load->model('providermodel');
			$provider					= $this->providermodel->get_provider($params['provider_seq']);
			$default_commission_rate	= $provider['charge'];
			$default_commission_type	= $provider['commission_type'];
		}

		// 옵션 재정의
		$optionList	= array();
		$totalRow	= 1;
		for ($o = 0; $o < 5; $o++){
			if ( trim($params['optionMakeValue'][$o])) {
				$idx++;
				if	($idx > 1)	$addComma	= ',';
				$titles		.= $addComma.$params['optionMakeName'][$o];
				$types		.= $addComma.$params['optionMakeId'][$o];

				$newtypes		.= $addComma.$params['optionMakenewtype'][$o];
				$newtypesar[$o]		= $params['optionMakenewtype'][$o];
				$code_seq		.= $addComma.str_replace("goodsoption_","",$params['optionMakeId'][$o]);

				if($params['optionMakenewtype'][$o] != 'direct' ) {//상품코드
					//색상, 주소
					if($params['optionMakenewtype'][$o] == 'color' && $params['optionMakecolor'][$o]) $colors = explode(",",$params['optionMakecolor'][$o]);
					if($params['optionMakenewtype'][$o] == 'address' && $params['optionMakezipcode'][$o]) $zipcodes	= explode(",",$params['optionMakezipcode'][$o]);
					if($params['optionMakenewtype'][$o] == 'address' && $params['optionMakeaddress_type'][$o]) $address_types	= explode(",",$params['optionMakeaddress_type'][$o]);
					if($params['optionMakenewtype'][$o] == 'address' && $params['optionMakeaddress'][$o]) $addresss	= explode(",",$params['optionMakeaddress'][$o]);
					if($params['optionMakenewtype'][$o] == 'address' && $params['optionMakeaddress_street'][$o]) $address_streets	= explode(",",$params['optionMakeaddress_street'][$o]);
					if($params['optionMakenewtype'][$o] == 'address' && $params['optionMakeaddressdetail'][$o]) $addressdetails	= explode(",",$params['optionMakeaddressdetail'][$o]);
					if($params['optionMakenewtype'][$o] == 'address' && $params['optionMakebiztel'][$o]) $biztels	= explode(",",$params['optionMakebiztel'][$o]);
					if($params['optionMakenewtype'][$o] == 'address' && $params['optionMakeaddress_commission'][$o]) $address_commissions	= explode(",",$params['optionMakeaddress_commission'][$o]);

					//날짜, 수동기간, 자동기간추가
					if($params['optionMakenewtype'][$o] == 'date' && $params['optionMakecodedate'][$o]) $codedate	= explode(",",$params['optionMakecodedate'][$o]);

					if($params['optionMakenewtype'][$o] == 'dayinput' && $params['optionMakesdayinput'][$o])$sdayinput	=explode(",",$params['optionMakesdayinput'][$o]);
					if($params['optionMakenewtype'][$o] == 'dayinput' && $params['optionMakefdayinput'][$o]) $fdayinput	= explode(",",$params['optionMakefdayinput'][$o]);
					if($params['optionMakenewtype'][$o] == 'dayauto' && $params['optionMakedayauto_type'][$o]) $dayauto_type= explode(",",$params['optionMakedayauto_type'][$o]);
					if($params['optionMakenewtype'][$o] == 'dayauto' && $params['optionMakesdayauto'][$o]>=0) $sdayauto = explode(",",$params['optionMakesdayauto'][$o]);
					if($params['optionMakenewtype'][$o] == 'dayauto' && $params['optionMakefdayauto'][$o]) $fdayauto	= explode(",",$params['optionMakefdayauto'][$o]);
					if($params['optionMakenewtype'][$o] == 'dayauto' && $params['optionMakedayauto_day'][$o]) $dayauto_day	= explode(",",$params['optionMakedayauto_day'][$o]);
				}

				${'option'.$idx.'_arr'}	= explode(',', trim($params['optionMakeValue'][$o]));
				${'option'.$idx.'_cnt'}	= count(${'option'.$idx.'_arr'});
				${'price'.$idx.'_arr'}	= explode(',', trim($params['optionMakePrice'][$o]));
				${'code'.$idx.'_arr'}	= explode(',', trim($params['optionMakeCode'][$o]));
				$totalRow				= $totalRow * ${'option'.$idx.'_cnt'};
				$lastOpt				= $idx;
			}
		}
		$colordepth = array_keys($newtypesar, "color");
		$addressdepth = array_keys($newtypesar, "address");
		$datedepth = array_keys($newtypesar, "date");
		$dayinputdepth = array_keys($newtypesar, "dayinput");
		$dayautodepth = array_keys($newtypesar, "dayauto");
		$create_package_count = $params['create_package_count'];

		$optidx = $o1 = $o2 = $o3 = $o4 = $o5 = 0;
		for ($o = 1; $o <= $totalRow; $o++){

			$nOpt	= $lastOpt;
			while ($nOpt > 0){
				$nCnt	= ${'option'.$nOpt.'_cnt'} - 1;
				if	(${'o'.$nOpt} > $nCnt){
					${'o'.$nOpt}	= 0;
					$aOpt	= $nOpt - 1;
					${'o'.$aOpt}++;
				}
				$nOpt	= $nOpt - 1;
			}

			$price	= $price1_arr[$o1] + $price2_arr[$o2] + $price3_arr[$o3]
					+ $price4_arr[$o4] + $price5_arr[$o5];

			$reserve	= 0;
			if	($price > 0){
				$reserve		= round($price * ($reserve_rate * 0.01));
			}

			$option1		= (!is_null($option1_arr[$o1])) ? $option1_arr[$o1] : '';
			$option2		= (!is_null($option2_arr[$o2])) ? $option2_arr[$o2] : '';
			$option3		= (!is_null($option3_arr[$o3])) ? $option3_arr[$o3] : '';
			$option4		= (!is_null($option4_arr[$o4])) ? $option4_arr[$o4] : '';
			$option5		= (!is_null($option5_arr[$o5])) ? $option5_arr[$o5] : '';

			$options['goods_seq']		= $this->optionReplace($goods_seq, 'int');
			$options['code_seq']		= $this->optionReplace($code_seq);
			$options['default_option']	= ($defaults) ? $defaults : 'n';
			$options['option_type']		= $this->optionReplace($types);
			$options['option_title']			= $this->optionReplace($titles);
			$options['option1']			= $this->optionReplace($option1);
			$options['option2']			= $this->optionReplace($option2);
			$options['option3']			= $this->optionReplace($option3);
			$options['option4']			= $this->optionReplace($option4);
			$options['option5']			= $this->optionReplace($option5);
			$options['optioncode1']		= $code1_arr[$o1];
			$options['optioncode2']		= $code2_arr[$o2];
			$options['optioncode3']		= $code3_arr[$o3];
			$options['optioncode4']		= $code4_arr[$o4];
			$options['optioncode5']		= $code5_arr[$o5];

			$tmpprices			= $price1_arr[$o1]. ',' . $price2_arr[$o2]. ',' . $price3_arr[$o3]. ',' . $price4_arr[$o4]. ',' . $price5_arr[$o5];

			$options['newtype']				= $this->optionReplace($newtypes);
			$options['tmpprice']				= $tmpprices;

			$oo = ($optidx%5);
			$coo = array_keys(${'code'.($colordepth[0]+1).'_arr'}, $options['optioncode'.($colordepth[0]+1)]);
			$aoo = array_keys(${'code'.($addressdepth[0]+1).'_arr'}, $options['optioncode'.($addressdepth[0]+1)]);
			$doo = array_keys(${'code'.($datedepth[0]+1).'_arr'}, $options['optioncode'.($datedepth[0]+1)]);

			$dioo = array_keys(${'code'.($dayinputdepth[0]+1).'_arr'}, $options['optioncode'.($dayinputdepth[0]+1)]);
			$daoo = array_keys(${'code'.($dayautodepth[0]+1).'_arr'}, $options['optioncode'.($dayautodepth[0]+1)]);

			//색상, 주소
			$options['color']					= $colors[$coo[0]];
			$options['zipcode']				= $zipcodes[$aoo[0]];
			$options['address_type']				= $address_types[$aoo[0]];
			$options['address']				= $addresss[$aoo[0]];
			$options['address_street']				= $address_streets[$aoo[0]];
			$options['addressdetail']		= $addressdetails[$aoo[0]];
			$options['biztel']					= $biztels[$aoo[0]];
			$options['address_commission']					= $address_commissions[$aoo[0]];

			//날짜, 수동기간, 자동기간추가
			$options['codedate']			= $codedate[$doo[0]];

			$options['sdayinput']			= $sdayinput[0];//$dioo[0]
			$options['fdayinput']			= $fdayinput[0];//$dioo[0]
			$options['dayauto_type']		= $dayauto_type[0];//$daoo[0]
			$options['sdayauto']			= $sdayauto[0];//$daoo[0]
			$options['fdayauto']				= $fdayauto[0];//$daoo[0]
			$options['dayauto_day']		= $dayauto_day[0];//$daoo[0]

			$options['coupon_input']		= ($socialcpuseopen == 'pass' || $price == 0 )?1:$this->optionReplace($price, 'int');
			$options['consumer_price']	= '0';
			$options['price']					= $this->optionReplace($price, 'float');
			$options['reserve_rate']		= $this->optionReplace($reserve_rate,'float',false);
			$options['reserve_unit']		= 'percent';
			$options['reserve']				= $this->optionReplace($reserve, 'int');
			$options['infomation']			= '';
			$options['commission_rate']	= $default_commission_rate;
			$options['commission_type']	= $default_commission_type;
			$options['tmp_date']		= date('Ymd');
			$options['tmp_no']			= $tmp_seq;
			$options['package_count']	= $this->optionReplace($create_package_count,'int');

			//색상옵션일때 값이 없으면 기본 흰색으로 저장합니다. @2016-07-27 ysm 3
			if( strstr($options['newtype'],'color') && !$options['color'] ) $options['color'] = '#fff';

			$this->db->insert( 'fm_goods_option_tmp', $options );
			$option_seq	= $this->db->insert_id();

			if	($option_seq){
				$supply['goods_seq']		= $this->optionReplace($goods_seq, 'int');
				$supply['option_seq']		= $option_seq;
				$supply['supply_price']		= '0';
				$supply['exchange_rate']	= '0';
				$supply['stock']			= '0';
				$supply['badstock']			= '0';
				$supply['reservation15']	= '0';
				$supply['reservation25']	= '0';
				$supply['ablestock15']		= '0';
				$supply['tmp_date']			= date('Ymd');
				$supply['tmp_no']			= $tmp_seq;
				$this->db->insert( 'fm_goods_supply_tmp', $supply );
			}

			$defaults = 'n';
			${'o'.$lastOpt}++;
			$optidx++;
		}
		//exit;
	}

	/**
	* 추가구성옵션 임시옵션정보 최초생성 (subopt 1단계)
	**/
	public function make_suboption_tmp($params) {

		$query	= "delete from fm_goods_suboption_tmp where tmp_no = '".$params['tmp_seq']."'";
		$this->db->query($query);
		$query	= "delete from fm_goods_supply_tmp "
				. "where tmp_no = '".$params['tmp_seq']."' and suboption_seq > 0";
		$this->db->query($query);

		$reserves		= config_load('reserve');
		$reserve_rate	= $reserves['default_reserve_percent'];

		$goods_seq	= ($params['goods_seq']) ? $params['goods_seq'] : '0';
		$default_commission_rate	= ($params['default_commission_rate']) ? $params['default_commission_rate'] : 0;
		$default_commission_type	= ($params['default_commission_type']) ? $params['default_commission_type'] : 'SACO';
		$socialcpuseopen	= trim($params['socialcpuseopen']);

		// 입점사 정보 ( 기본 수수료 정보 )
		if	($params['provider_seq'] > 1 && !$default_commission_rate){
			$this->load->model('providermodel');
			$provider					= $this->providermodel->get_provider($params['provider_seq']);
			$default_commission_rate	= $provider['charge'];
			$default_commission_type	= $provider['commission_type'];
		}

		$titleCnt	= count($params['suboptionMakeName']);
		for ($lo = 0; $lo < $titleCnt; $lo++){
			if	($params['suboptionMakeValue'][$lo] ) {

				$subTitle	= $params['suboptionMakeName'][$lo];

				$subType	= $params['suboptionMakeId'][$lo];
				$code_seq	= str_replace('goodssuboption_', '', $subType);

				$prices	= explode(',', $params['suboptionMakePrice'][$lo]);
				$codes	= explode(',', $params['suboptionMakeCode'][$lo]);
				$values	= explode(',', $params['suboptionMakeValue'][$lo]);

				$package_count = $params['suboption_package_count'][$lo];

				$newtype	= $params['suboptionMakenewtype'][$lo];

				//색상, 주소
				$colors					= ($newtype == 'color')?explode(",",$params['suboptionMakecolor'][$lo]):'';
				$zipcodes				= ($newtype == 'address')?explode(",",$params['suboptionMakezipcode'][$lo]):'';
				$address_types			= ($newtype == 'address')?explode(",",$params['suboptionMakeaddress_type'][$lo]):'';
				$addresss			= ($newtype == 'address')?explode(",",$params['suboptionMakeaddress'][$lo]):'';
				$address_streets			= ($newtype == 'address')?explode(",",$params['suboptionMakeaddress_street'][$lo]):'';
				$addressdetails	= ($newtype == 'address')?explode(",",$params['suboptionMakeaddressdetail'][$lo]):'';
				$biztels				= ($newtype == 'address')?explode(",",$params['suboptionMakebiztel'][$lo]):'';

				//날짜, 수동기간, 자동기간추가
				$codedates			= ($newtype == 'date')?explode(",",$params['suboptionMakecodedate'][$lo]):'';

				$sdayinputs			= ($newtype == 'dayinput')?($params['suboptionMakesdayinput'][$lo]):'';
				$fdayinputs			= ($newtype == 'dayinput')?($params['suboptionMakefdayinput'][$lo]):'';
				$dayauto_types	= ($newtype == 'dayauto')?($params['suboptionMakedayauto_type'][$lo]):'';
				$sdayautos			= ($newtype == 'dayauto')?($params['suboptionMakesdayauto'][$lo]):'';
				$fdayautos			= ($newtype == 'dayauto')?($params['suboptionMakefdayauto'][$lo]):'';
				$dayauto_days	= ($newtype == 'dayauto')?($params['suboptionMakedayauto_day'][$lo]):'';

				$valCnt	= count($values);
				for ($o = 0; $o < $valCnt; $o++){
					$reserve	= 0;
					if	($prices[$o] > 0){
						$reserve	= round($prices[$o] * ($reserve_rate * 0.01));
					}

					$options['goods_seq']		= $this->optionReplace($goods_seq, 'int');
					$options['code_seq']		= $this->optionReplace($code_seq, 'int');
					$options['package_count']	= $this->optionReplace($package_count, 'int');

					$options['sub_required']	= 'n';
					$options['sub_sale']		= 'n';
					$options['suboption_type']	= $this->optionReplace($subType);
					$options['suboption_title']	= $this->optionReplace($subTitle);
					$options['suboption_code']	= $codes[$o];

					$options['newtype']				= $this->optionReplace($newtype);

					$options['color']					= ($colors[$o]);
					$options['zipcode']				= ($zipcodes[$o]);
					$options['address_type']				= ($address_types[$o]);
					$options['address']				= ($addresss[$o]);
					$options['address_street']				= ($address_streets[$o]);
					$options['addressdetail']		= ($addressdetails[$o]);
					$options['biztel']					= ($biztels[$o]);

					$options['codedate']			= ($codedates[$o]);

					$options['sdayinput']			= ($sdayinputs);
					$options['fdayinput']			= ($fdayinputs);
					$options['dayauto_type']		= ($dayauto_types);
					$options['sdayauto']			= ($sdayautos);
					$options['fdayauto']				= ($fdayautos);
					$options['dayauto_day']		= ($dayauto_days);

					$options['suboption']			= $this->optionReplace($values[$o]);
					$options['coupon_input']		=  ($socialcpuseopen == 'pass' || $prices[$o] == 0)?1:$this->optionReplace($prices[$o], 'int');
					$options['consumer_price']	= '0';
					$options['price']					= $this->optionReplace($prices[$o], 'int');
					$options['reserve_rate']	= $this->optionReplace($reserve_rate,'float',false);
					$options['reserve_unit']	= 'percent';
					$options['reserve']			= $this->optionReplace($reserve, 'int');
					$options['commission_rate']	= $default_commission_rate;
					$options['commission_type']	= $default_commission_type;
					$options['tmp_date']		= date('Ymd');
					$options['tmp_no']			= $params['tmp_seq'];

					//색상옵션일때 값이 없으면 기본 흰색으로 저장합니다.
					if( strstr($options['newtype'],'color') && !$options['color'] ) $options['color'] = '#fff';

					$this->db->insert( 'fm_goods_suboption_tmp', $options );
					$suboption_seq	= $this->db->insert_id();

					if ($suboption_seq){
						$supply['goods_seq']		= $this->optionReplace($goods_seq, 'int');
						$supply['suboption_seq']	= $suboption_seq;
						$supply['supply_price']		= '0';
						$supply['exchange_rate']	= '0';
						$supply['stock']			= '0';
						$supply['badstock']			= '0';
						$supply['reservation15']	= '0';
						$supply['reservation25']	= '0';
						$supply['ablestock15']		= '0';
						$supply['tmp_date']			= date('Ymd');
						$supply['tmp_no']			= $params['tmp_seq'];
						$this->db->insert( 'fm_goods_supply_tmp', $supply );
					}
				}
			}
		}
		//exit;
	}

	/**
	* 필수옵션 새창 임시옵션정보 최종생성 (opt 3단계)
	**/
	public function save_option_tmp($params){
		foreach($params as $k => $v){	$$k	= $v;	}
		$today = date("Y-m-d");

		if( $params['socialcpuseopen'] == 'price' || $params['socialcpuseopen'] == 'pass' ) {
			foreach($params as $k => $v) {
				$$k	= $v;
				if( $k == 'coupon_input' &&  in_array(0,$v) ) {
					$msg = "[티켓상품]의 티켓1장의 값어치를 정확히 입력해 주세요.";
					openDialogAlert($msg,450,140,'parent',$callback);
					exit;
				}

				if( $k == 'optnewtype') {
					$couponexpire =  false;
					if( !( in_array('date',$v) || in_array('dayinput',$v) || in_array('dayauto',$v) ) ){//coupon goods
						$msg = "[티켓상품]의 유효기간(날짜, 자동기간, 수동기간)옵션을 추가해 주세요.";
						openDialogAlert($msg,470,150,'parent',$callback);
						exit;
					}

					if( in_array('date', $v) ) {
						foreach($params['codedate'] as $key => $codedate){
							if( $codedate >= $today ) {
								$couponexpire = true;
								break;
							}else{
								$social_start_date	= $codedate;
								$social_end_date	= $codedate;
							}
						}
						if( $couponexpire === false ) {
							$msg = "[티켓상품]의 유효기간을 정확히 입력해 주세요.";
							if( strstr($social_start_date,'0000-00-00') || strstr($social_end_date,'0000-00-00') || !$social_start_date  || !$social_end_date){
								$msg .= "<br/>유효기간이 없습니다.";
							}else{
								$msg .= "<br/>유효기간이 ".$codedate." 입니다.";
							}
							openDialogAlert($msg,450,160,'parent',$callback);
							exit;
						}
					}elseif( in_array('dayinput', $v) ) {
						foreach($params['fdayinput'] as $key => $fdayinput){
							if( $fdayinput >= $today ) {
								$couponexpire = true;
								break;
							}else{
								$social_start_date = $params['sdayinput'][$key];
								$social_end_date = $fdayinput;
							}
						}
						if( $couponexpire === false ) {
							$msg = "[티켓상품]의 유효기간을 정확히 입력해 주세요.";
							if( strstr($social_start_date,'0000-00-00') || strstr($social_end_date,'0000-00-00')  || !$social_start_date  || !$social_end_date){
								$msg .= "<br/>유효기간이 없습니다.";
							}else{
								$msg .= "<br/>유효기간이 ".$social_start_date." ~ ".$social_end_date." 입니다.";
							}
							openDialogAlert($msg,450,160,'parent',$callback);
							exit;
						}
					}
				}
			}//endforeach
		}//endif

		// 기존 옵션 정보 삭제
		$query	= "delete from fm_goods_option_tmp where tmp_no = '".$tmp_seq."' ";
		$this->db->query($query);
		// 기존 재고 정보 삭제
		$query	= "delete from fm_goods_supply_tmp where option_seq > 0 and tmp_no = '".$tmp_seq."' ";
		$this->db->query($query);

		$reserves		= config_load('reserve');
		$default_rate	= $reserves['default_reserve_percent'];

		$titles		= implode(',', $optionTitle);
		$typs		= implode(',', $optionType);
		$newtypes		= implode(',', $optnewtype);

		$optCnt		= count($opt);
		$sOptCnt	= count($opt[0]);
		for ($s = 0; $s < $sOptCnt; $s++){
			$optionStr			= '';
			$sqlOptionFld		= '';
			$sqlOptionCodeFld	= '';
			$sqlOption			= '';
			$sqlOptionCode		= '';
			$default			= 'n';
			$optionArr			= array();

			$newtypeArr		= array();
			$tmppriceArr		= array();
			$colorArr				= array();
			$zipcodeArr			= array();
			$address_typeArr			= array();
			$addressArr			= array();
			$address_streetArr			= array();
			$addressdetailArr= array();
			$biztelArr				= array();
			$address_commissionArr= array();

			for ($o = 0; $o < $optCnt; $o++){
				${'option'.($o+1)}		= $opt[$o][$s];
				${'optioncode'.($o+1)}	= $optcode[$o][$s];

				$tmppriceArr[]				= $opttmpprice[$o][$s];

				if	(!is_null($opt[$o][$s]))
					$optionArr[]			= $opt[$o][$s];
			}
			if	($aleady_default != 'y' && $defaultOption == implode(',', $optionArr)){
				$aleady_default	= 'y';
				$default		= 'y';
			}

			if	($reserve_policy == 'shop'){
				$reserveRate[$s]	= $default_rate;
				$reserveUnit[$s]	= 'percent';
				$reserve[$s]		= round($price[$s] * ($default_rate * 0.01));
			}

			$codes			= 0;
			if	($types != 'direct') $code_seq = str_replace('goodsoption_', '', $typs);

			$options['goods_seq']			= $this->optionReplace($goods_seq, 'int');
			$options['code_seq']			= $code_seq;
			$options['default_option']	= ($default) ? $default : 'n';
			$options['option_type']		= $this->optionReplace($typs);
			$options['option_title']	= $this->optionReplace($titles);
			$options['option1']			= $this->optionReplace($option1);
			$options['option2']			= $this->optionReplace($option2);
			$options['option3']			= $this->optionReplace($option3);
			$options['option4']			= $this->optionReplace($option4);
			$options['option5']			= $this->optionReplace($option5);
			$options['optioncode1']		= $optioncode1;
			$options['optioncode2']		= $optioncode2;
			$options['optioncode3']		= $optioncode3;
			$options['optioncode4']		= $optioncode4;
			$options['optioncode5']		= $optioncode5;

			$options['tmpprice']				= implode(',', $tmppriceArr);

			$options['newtype']				= $this->optionReplace($newtypes);

			$colors									= $optcolor[$s];//implode(',', $optcolor);
			$zipcodes								= $optzipcode[$s];//implode(',', $optzipcode);
			$address_types					= $optaddress_type[$s];//implode(',', $optaddress);
			$addresss							= $optaddress[$s];//implode(',', $optaddress);
			$address_streets				= $optaddress_street[$s];//implode(',', $optaddress);
			$addressdetails					= $optaddressdetail[$s];//implode(',', $optaddressdetail);
			$biztels								= $optbiztel[$s];//implode(',', $optbiztel);
			$address_commissions		= $optaddress_commission[$s];

			$codedates							= $codedate[$s];//implode(',', $codedate);
			$sdayinputs							= $sdayinput[$s];//implode(',', $sdayinput);
			$fdayinputs							= $fdayinput[$s];//implode(',', $fdayinput);
			$dayauto_types					= $dayauto_type[$s];//implode(',', $dayauto_type);
			$sdayautos							= $sdayauto[$s];//implode(',', $sdayauto);
			$fdayautos							= $fdayauto[$s];//implode(',', $fdayauto);
			$dayauto_days					= $dayauto_day[$s];//implode(',', $dayauto_day);

			$options['color']								= ($colors);
			$options['zipcode']							= ($zipcodes);
			$options['address_type']					= ($address_types);
			$options['address']							= ($addresss);
			$options['address_street']				= ($address_streets);
			$options['addressdetail']					= ($addressdetails);
			$options['biztel']								= ($biztels);
			$options['address_commission']		= ($address_commissions);

			$options['codedate']		= ($codedates);
			$options['sdayinput']		= ($sdayinputs);
			$options['fdayinput']		= ($fdayinputs);
			$options['dayauto_type']	= ($dayauto_types);
			$options['sdayauto']		= ($sdayautos);
			$options['fdayauto']		= ($fdayautos);
			$options['dayauto_day']		= ($dayauto_days);
			$options['coupon_input']	= $this->optionReplace($coupon_input[$s], 'float');
			$options['consumer_price']	= $this->optionReplace($consumerPrice[$s], 'float');
			$options['price']			= $this->optionReplace($price[$s], 'float');
			$options['reserve_rate']	= $this->optionReplace($reserveRate[$s],'float',false);
			$options['reserve_unit']	= ($reserveUnit[$s]) ? $reserveUnit[$s] : 'percent';
			$options['reserve']			= $this->optionReplace($reserve[$s], 'float');
			$options['infomation']		= addslashes($infomation[$s]);
			$options['commission_rate']	= $this->optionReplace($commissionRate[$s], 'float', false);
			$options['commission_type']	= $commissionType[$s];
			$options['weight']			= $weight[$s];
			$options['tmp_policy']		= $reserve_policy;
			$options['tmp_date']		= date('Ymd');
			$options['tmp_no']			= $tmp_seq;

			//색상옵션일때 값이 없으면 기본 흰색으로 저장합니다. @2016-07-27 ysm 4
			if( strstr($options['newtype'],'color') && !$options['color'] ) $options['color'] = '#fff';

			$this->db->insert( 'fm_goods_option_tmp', $options );
			$option_seq	= $this->db->insert_id();

			if	($option_seq){
				$supply						= array();
				$supply['goods_seq']		= $this->optionReplace($goods_seq, 'int');
				$supply['option_seq']		= $option_seq;
				$supply['supply_price']		= $this->optionReplace($supplyPrice[$s], 'float');
				$supply['exchange_rate']	= $this->exchange_rate_krw;
				$supply['stock']			= $this->optionReplace($stock[$s], 'int');
				$supply['badstock']			= $this->optionReplace($badstock[$s], 'int');
				$supply['reservation15']	= $this->optionReplace($reservation15[$s], 'int');
				$supply['reservation25']	= $this->optionReplace($reservation25[$s], 'int');
				$supply['ablestock15']		= $this->optionReplace($unUsableStock[$s], 'int');
				$supply['tmp_date']			= date('Ymd');
				$supply['tmp_no']			= $tmp_seq;
				$this->db->insert( 'fm_goods_supply_tmp', $supply );
			}
		}//exit;
	}
	public function insert_option_tmp($set_params){
		$this->db->where('tmp_no', $set_params['tmp_no']);
		$this->db->delete('fm_goods_option_tmp');
		$this->db->insert('fm_goods_option_tmp', $set_params);
	}

	/**
	* 추가구성옵션 새창 임시옵션정보 최종생성 (opt 3단계)
	**/
	public function save_suboption_tmp($params){
		$suboptTitle = trim($params['suboptTitle'][0]);
		if(strlen($suboptTitle) <= 0){
			openDialogAlert("옵션명을 입력 하세요.",450,160,'parent','');
			exit;
		}

		foreach($params as $k => $v){	$$k	= $v;	}

		// 기존 옵션 정보 삭제
		$query	= "delete from fm_goods_suboption_tmp where tmp_no = '".$tmp_seq."' ";
		$this->db->query($query);
		// 기존 재고 정보 삭제
		$query	= "delete from fm_goods_supply_tmp where suboption_seq > 0 and tmp_no = '".$tmp_seq."' ";
		$this->db->query($query);

		$reserves		= config_load('reserve');
		$default_rate	= $reserves['default_reserve_percent'];

		$idx		= 0;
		$s			= 0;

		foreach($subopt as $k => $sopt){
			$titles				= $suboptTitle[$s];
			$types				= $suboptType[$s];
			$subopt_s			= $subopt[$k];
			$subRequiredVal		= (in_array($s, $subRequired))	? 'y' : 'n';
			$subSaleVal			= (in_array($s, $subSale))		? 'y' : 'n';
			$codes				= 0;
			if	($types != 'direct')
				$codes			= str_replace('goodssuboption_', '', $types);

			$subopt_scnt	= count($subopt_s);
			for ($z = 0; $z < $subopt_scnt; $z++){
				$suboptCodeVal		= $suboptCode[$k][$z];
				$org_suboption_seq	= $orgSuboptionSeq[$k][$z];
				$reserve			= 0;
				if	($subReservePolicy != 'goods'){
					if	($subPrice[$idx] > 0){
						$reserve	= round($subPrice[$idx] * ($default_rate * 0.01));
					}

					$reserve_rate	= $this->optionReplace($default_rate,'float',false);
					$reserve_unit	= 'percent';
					$reserve		= $this->optionReplace($reserve, 'float');
				}else{
					$reserve_rate	= $this->optionReplace($subReserveRate[$idx],'float',false);
					$reserve_unit	= ($subReserveUnit[$idx]) ? $subReserveUnit[$idx] : 'percent';
					$reserve		= $this->optionReplace($subReserve[$idx], 'float');
				}


				$newtypes					= $suboptionnewtype[$idx];
				$suboptcolors				= ($newtypes == 'color' ) ? ($suboptcolor[$idx]):'';
				$suboptzipcodes			= ($newtypes == 'address' ) ? ($suboptzipcode[$idx]):'';
				$suboptaddress_types			= ($newtypes == 'address' ) ? ($suboptaddress_type[$idx]):'';
				$suboptaddresss			= ($newtypes == 'address' ) ? ($suboptaddress[$idx]):'';
				$suboptaddress_streets			= ($newtypes == 'address' ) ? ($suboptaddress_street[$idx]):'';
				$suboptaddressdetails= ($newtypes == 'address' ) ? ($suboptaddressdetail[$idx]):'';
				$suboptbiztels= ($newtypes == 'address' ) ? ($suboptbiztel[$idx]):'';

				$codedates			= ($newtypes == 'date' ) ? ($codedate[$idx]):'';
				$sdayinputs			= ($newtypes == 'dayinput' ) ? ($sdayinput[$idx]):'';
				$fdayinputs			= ($newtypes == 'dayinput' ) ? ($fdayinput[$idx]):'';
				$dayauto_types		= ($newtypes == 'dayauto' ) ? ($dayauto_type[$idx]):'';
				$sdayautos			= ($newtypes == 'dayauto' ) ? ($sdayauto[$idx]):'';
				$fdayautos			= ($newtypes == 'dayauto' ) ? ($fdayauto[$idx]):'';
				$dayauto_days		= ($newtypes == 'dayauto' ) ? ($dayauto_day[$idx]):'';

				$weight				= (float)$weightVal[$idx];
				$option_view		= ($optionView[$idx] == 'N') ? 'N' : 'Y';

				$options['goods_seq']			= $this->optionReplace($goods_seq, 'int');
				$options['code_seq']			= $codes;
				$options['sub_required']		= ($subRequiredVal) ? $subRequiredVal : 'n';
				$options['sub_sale']			= ($subSaleVal) ? $subSaleVal : 'n';
				$options['suboption_type']		= $this->optionReplace($types);
				$options['suboption_title']		= $this->optionReplace($titles);
				$options['suboption_code']		= $this->optionReplace($suboptCodeVal);
				$options['newtype']				= ($newtypes);
				$options['color']				= ($suboptcolors);
				$options['zipcode']				= ($suboptzipcodes);
				$options['address_type']		= ($suboptaddress_types);
				$options['address']				= ($suboptaddresss);
				$options['address_street']		= ($suboptaddress_streets);
				$options['addressdetail']		= ($suboptaddressdetails);
				$options['biztel']				= ($suboptbiztels);
				$options['codedate']			= ($codedates);
				$options['sdayinput']			= ($sdayinputs);
				$options['fdayinput']			= ($fdayinputs);
				$options['dayauto_type']		= ($dayauto_types);
				$options['sdayauto']			= ($sdayautos);
				$options['fdayauto']			= ($fdayautos);
				$options['dayauto_day']			= ($dayauto_days);
				$options['suboption']			= $this->optionReplace($subopt_s[$z]);
				$options['coupon_input']		= $this->optionReplace($subcoupon_input[$idx], 'float');
				$options['consumer_price']		= $this->optionReplace($subConsumerPrice[$idx], 'float');
				$options['price']				= $this->optionReplace($subPrice[$idx], 'float');
				$options['reserve_rate']		= $reserve_rate;
				$options['reserve_unit']		= $reserve_unit;
				$options['reserve']				= $reserve;
				$options['commission_rate']		= $this->optionReplace($subCommissionRate[$idx], 'float', false);
				$options['commission_type']		= $subCommissionType[$idx];
				$options['tmp_date']			= date('Ymd');
				$options['tmp_no']				= $tmp_seq;
				$options['org_suboption_seq']	= $org_suboption_seq;
				$options['weight']				= $weight;
				$options['option_view']			= $option_view;

				if (defined('__SELLERADMIN__') === true) {
					$query_option				= $this->get_suboption(array('goods_seq'=>$options['goods_seq'],'suboption_title'=>$options['suboption_title'],'suboption'=>$options['suboption']));
					$aDataSub					= $query_option->row_array();
					if( !$aDataSub['suboption_seq'] ){
						$aDataSub['reserve_rate']	= $reserve_rate;
						$aDataSub['reserve']		= $reserve;
						$aDataSub['reserve_unit']	= $reserve_unit;
					}
					$options['reserve_rate']	= $aDataSub['reserve_rate'];
					$options['reserve_unit']	= $aDataSub['reserve_unit'];
					$options['reserve']			= $aDataSub['reserve'];
				}
				/*
				정산방식이 공급가액 방식이 아닐 때(수수료율/공급율)
				정산수수료가 100%가 넘으면 최대치 100으로 강제 업데이트
				*/
				if($options['commission_type'] != "SUPR" && $options['commission_rate'] > 100){
					$options['commission_rate'] = 100;
				}

				if( $tmp_package_option_seq1[$idx] ){ // 패키지상품일 경우
					$options['package_count'] 		= 1;
					$options['package_goods_name1'] = $tmp_package_goods_name1[$idx];
					$options['package_option_seq1'] = $tmp_package_option_seq1[$idx];
					$options['package_option1'] 	= $tmp_package_option1[$idx];
					$options['package_unit_ea1'] 	= $tmp_package_unit_ea1[$idx];
				}

				$this->db->insert( 'fm_goods_suboption_tmp', $options );
				$suboption_seq	= $this->db->insert_id();

				if	($suboption_seq){

					$supply_price = $subSupplyPrice[$idx];

					$supply							= array();
					$supply['goods_seq']			= $this->optionReplace($goods_seq, 'int');
					$supply['suboption_seq']		= $suboption_seq;
					$supply['supply_price']			= $this->optionReplace($supply_price, 'float');
					$supply['exchange_rate']		= $this->exchange_rate_krw;
					$supply['stock']				= $this->optionReplace($subStock[$idx], 'int');
					$supply['badstock']				= $this->optionReplace($subBadStock[$idx], 'int');
					$supply['reservation15']		= '0';
					$supply['reservation25']		= '0';
					$supply['ablestock15']			= '0';
					$supply['safe_stock']			= $this->optionReplace($subSafeStock[$idx], 'int');
					if( $tmp_package_option_seq1[$idx] ){
						$poption_seq = $tmp_package_option_seq1[$idx];
						$punit_ea = $tmp_package_unit_ea1[$idx];
						if(!$punit_ea) $punit_ea = 1;
						if($poption_seq){
							$data_package = $this->get_package_stock($poption_seq,$punit_ea);
							$supply['stock']			= (int) $data_package['unit_stock'];
							$supply['badstock']			= (int) $data_package['unit_badstock'];
							$supply['reservation15']	= (int) $data_package['unit_reservation15'];
							$supply['reservation25']	= (int) $data_package['unit_reservation25'];
						}
					}

					$supply['total_stock']			= $this->optionReplace($subTotalStock[$idx], 'int');
					$supply['total_badstock']		= $this->optionReplace($subTotalBadStock[$idx], 'int');
					$supply['total_supply_price']	= $this->optionReplace($subTotalSupplyPrice[$idx], 'float');
					$supply['tmp_date']				= date('Ymd');
					$supply['tmp_no']				= $_POST['tmp_seq'];
					$this->db->insert( 'fm_goods_supply_tmp', $supply );
				}

				$idx++;
			}

			$s++;
		}
		//exit;
	}

	public function moveTmpToOption($goods_seq, $tmp_seq){

		$this->load->model('scmmodel');
		$this->delete_option_info($goods_seq);

		$query		= "select * from fm_goods_option_tmp where tmp_no = ? order by option_seq asc";
		$rs			= $this->db->query($query,array($tmp_seq));
		$result		= $rs->result_array();

		foreach($result as $tmp_list){
			$tmp_option_seq	= $tmp_list['option_seq'];
			$org_option_seq	= $tmp_list['org_option_seq'];
			$supplySql		= "select * from fm_goods_supply_tmp where tmp_no = ? and option_seq = ? ";
			$supplyRs		= $this->db->query($supplySql,array($tmp_seq, $tmp_option_seq));
			$tmp_supply		= $supplyRs->result_array();
			$tmpSupply		= $tmp_supply[0];

			if	($default == 'y' && $tmp_list['default_option'] == 'y'){
				$tmp_list['default_option']	= 'n';
			}
			if	($tmp_list['default_option'] == 'y')	$default	= 'y';

			// 기존 옵션번호가 있으면 유지
			unset($options['option_seq']);
			if	($org_option_seq > 0)
				$options['option_seq']	= $this->optionReplace($org_option_seq, 'int');

			$options['goods_seq']		= $this->optionReplace($goods_seq, 'int');
			$options['code_seq']		= $tmp_list['code_seq'];
			$options['default_option']	= $tmp_list['default_option'];
			$options['option_type']		= $tmp_list['option_type'];
			$options['option_title']		= $this->optionReplace($tmp_list['option_title']);
			$options['option1']			= $this->optionReplace($tmp_list['option1']);
			$options['option2']			= $this->optionReplace($tmp_list['option2']);
			$options['option3']			= $this->optionReplace($tmp_list['option3']);
			$options['option4']			= $this->optionReplace($tmp_list['option4']);
			$options['option5']			= $this->optionReplace($tmp_list['option5']);
			$options['optioncode1']		= $tmp_list['optioncode1'];
			$options['optioncode2']		= $tmp_list['optioncode2'];
			$options['optioncode3']		= $tmp_list['optioncode3'];
			$options['optioncode4']		= $tmp_list['optioncode4'];
			$options['optioncode5']		= $tmp_list['optioncode5'];

			$options['tmpprice']				= $tmp_list['tmpprice'];
			$options['color']					= trim($tmp_list['color']);
			$options['zipcode']					= $tmp_list['zipcode'];
			$options['address_type']			= $tmp_list['address_type'];
			$options['address']					= $tmp_list['address'];
			$options['address_street']			= $tmp_list['address_street'];
			$options['addressdetail']			= $tmp_list['addressdetail'];
			$options['biztel']					= $tmp_list['biztel'];
			$options['address_commission']		= $tmp_list['address_commission'];

			$options['newtype']					= $tmp_list['newtype'];

			$options['codedate']				= $tmp_list['codedate'];
			$options['sdayinput']				= $tmp_list['sdayinput'];
			$options['fdayinput']				= $tmp_list['fdayinput'];
			$options['dayauto_type']			= $tmp_list['dayauto_type'];
			$options['sdayauto']				= $tmp_list['sdayauto'];
			$options['fdayauto']				= $tmp_list['fdayauto'];
			$options['dayauto_day']				= $tmp_list['dayauto_day'];

			$options['package_count']			= $tmp_list['package_count'];
			$options['package_goods_name1']		= $tmp_list['package_goods_name1'];
			$options['package_goods_name2']		= $tmp_list['package_goods_name2'];
			$options['package_goods_name3']		= $tmp_list['package_goods_name3'];
			$options['package_goods_name4']		= $tmp_list['package_goods_name4'];
			$options['package_goods_name5']		= $tmp_list['package_goods_name5'];

			$options['package_option_seq1']		= $tmp_list['package_option_seq1'];
			$options['package_option_seq2']		= $tmp_list['package_option_seq2'];
			$options['package_option_seq3']		= $tmp_list['package_option_seq3'];
			$options['package_option_seq4']		= $tmp_list['package_option_seq4'];
			$options['package_option_seq5']		= $tmp_list['package_option_seq5'];

			$options['package_option1']			= $tmp_list['package_option1'];
			$options['package_option2']			= $tmp_list['package_option2'];
			$options['package_option3']			= $tmp_list['package_option3'];
			$options['package_option4']			= $tmp_list['package_option4'];
			$options['package_option5']			= $tmp_list['package_option5'];

			$options['package_unit_ea1']		= $tmp_list['package_unit_ea1'];
			$options['package_unit_ea2']		= $tmp_list['package_unit_ea2'];
			$options['package_unit_ea3']		= $tmp_list['package_unit_ea3'];
			$options['package_unit_ea4']		= $tmp_list['package_unit_ea4'];
			$options['package_unit_ea5']		= $tmp_list['package_unit_ea5'];

			$options['coupon_input']			= $this->optionReplace($tmp_list['coupon_input'], 'float');
			$options['consumer_price']			= $this->optionReplace($tmp_list['consumer_price'], 'float');
			$options['price']					= $this->optionReplace($tmp_list['price'], 'float');
			$options['reserve_rate']			= $this->optionReplace($tmp_list['reserve_rate'],'float',false);
			$options['reserve_unit']			= $tmp_list['reserve_unit'];
			$options['reserve']					= $this->optionReplace($tmp_list['reserve'], 'float');
			$options['infomation']				= $tmp_list['infomation'];
			$options['commission_rate']			= $this->optionReplace($tmp_list['commission_rate'], 'float', false);
			$options['commission_type']			= $tmp_list['commission_type'];
			$options['fix_option_seq']			= $tmp_list['fix_option_seq'];
			$options['weight']					= $tmp_list['weight'];
			$options['option_view']				= ($tmp_list['option_view'] == 'N') ? 'N' : 'Y';

			//색상옵션일때 값이 없으면 기본 흰색으로 저장합니다. @2016-07-27 ysm 5
			if( strstr($options['newtype'],'color') && !$options['color'] ) $options['color'] = '#fff';

			$this->db->insert( 'fm_goods_option', $options );
			$option_seq	= $this->db->insert_id();
			if	(!$tmp_list['fix_option_seq']){
				$this->db->where(array('option_seq'=>$option_seq));
				$this->db->update('fm_goods_option', array('fix_option_seq'=>$option_seq));
			}
			$chg_seq_result[]	= array($tmp_option_seq => $option_seq);

			if	($option_seq){
				unset($supply);
				$supply['goods_seq']			= $this->optionReplace($goods_seq, 'int');
				$supply['option_seq']			= $option_seq;
				$supply['supply_price']			= $this->optionReplace($tmpSupply['supply_price'], 'float');
				$supply['exchange_rate']			= $this->exchange_rate_krw;
				$supply['stock']				= $this->optionReplace($tmpSupply['stock'], 'int');
				$supply['badstock']				= $this->optionReplace($tmpSupply['badstock'], 'int');
				$supply['reservation15']		= $this->optionReplace($tmpSupply['reservation15'], 'int');
				$supply['reservation25']		= $this->optionReplace($tmpSupply['reservation25'], 'int');
				$supply['ablestock15']			= $this->optionReplace($tmpSupply['ablestock15'], 'int');
				$supply['safe_stock']			= $this->optionReplace($tmpSupply['safe_stock'], 'int');
				$supply['total_supply_price']	= $this->optionReplace($tmpSupply['total_supply_price'], 'float');
				$supply['total_stock']			= $this->optionReplace($tmpSupply['total_stock'], 'int');
				$supply['total_badstock']		= $this->optionReplace($tmpSupply['total_badstock'], 'int');
				$this->db->insert( 'fm_goods_supply', $supply );

				// 물류관리 실 재고 재 계산
				if	($this->scmmodel->chkScmConfig(true)){
					$this->scmmodel->change_store_stock(array(array('goods_seq'		=> $goods_seq,
																	'option_type'	=> 'option',
																	'option_seq'	=> $option_seq )
														), '', '');
				}
			}
		}

		$query		= "delete from fm_goods_option_tmp where tmp_no = ? ";
		$this->db->query($query,array($tmp_seq));
		$query		= "delete from fm_goods_supply_tmp where tmp_no = ? and option_seq > 0";
		$this->db->query($query,array($tmp_seq));

		return $chg_seq_result;
	}

	public function moveTmpToSubOption($goods_seq, $tmp_seq){

		$this->delete_sub_option_info($goods_seq);

		$query		= "select * from fm_goods_suboption_tmp where tmp_no = ? ";
		$rs			= $this->db->query($query,array($tmp_seq));
		foreach($rs->result_array() as $tmp_list){
			$tmp_suboption_seq	= $tmp_list['suboption_seq'];
			$org_suboption_seq	= $tmp_list['org_suboption_seq'];
			$supplySql			= "select * from fm_goods_supply_tmp "
								. "where tmp_no = ? and suboption_seq = ? ";
			$supplyRs			= $this->db->query($supplySql,array($tmp_seq, $tmp_suboption_seq));
			$tmp_supply			= $supplyRs->result_array();
			$tmpSupply			= $tmp_supply[0];

			// 기존 옵션번호가 있으면 유지
			unset($options['suboption_seq']);
			if	($org_suboption_seq > 0)
				$options['suboption_seq']	= $this->optionReplace($org_suboption_seq, 'int');

			$options['goods_seq']			= $this->optionReplace($goods_seq, 'int');
			$options['code_seq']			= $tmp_list['code_seq'];
			$options['sub_required']		= $tmp_list['sub_required'];
			$options['sub_sale']			= $tmp_list['sub_sale'];
			$options['suboption_type']		= $tmp_list['suboption_type'];
			$options['suboption_title']		= $this->optionReplace($tmp_list['suboption_title']);
			$options['suboption_code']		= $tmp_list['suboption_code'];

			$options['color']				= trim($tmp_list['color']);
			$options['zipcode']				= $tmp_list['zipcode'];
			$options['address_type']		= $tmp_list['address_type'];
			$options['address']				= $tmp_list['address'];
			$options['address_street']		= $tmp_list['address_street'];
			$options['addressdetail']		= $tmp_list['addressdetail'];
			$options['biztel']				= $tmp_list['biztel'];

			$options['newtype']				= $tmp_list['newtype'];

			$options['codedate']			= $tmp_list['codedate'];
			$options['sdayinput']			= $tmp_list['sdayinput'];
			$options['fdayinput']			= $tmp_list['fdayinput'];
			$options['dayauto_type']		= $tmp_list['dayauto_type'];
			$options['sdayauto']			= $tmp_list['sdayauto'];
			$options['fdayauto']			= $tmp_list['fdayauto'];
			$options['dayauto_day']			= $tmp_list['dayauto_day'];

			$options['suboption']			= $this->optionReplace($tmp_list['suboption']);
			$options['coupon_input']		= $this->optionReplace($tmp_list['coupon_input'], 'float');
			$options['consumer_price']		= $this->optionReplace($tmp_list['consumer_price'], 'float');
			$options['price']				= $this->optionReplace($tmp_list['price'], 'float');

			$options['reserve_rate']		= $this->optionReplace($tmp_list['reserve_rate'], 'float',false);
			$options['reserve_unit']		= $tmp_list['reserve_unit'];
			$options['reserve']				= $this->optionReplace($tmp_list['reserve'], 'float');
			$options['commission_rate']		= $this->optionReplace($tmp_list['commission_rate'], 'float', false);
			$options['commission_type']		= $tmp_list['commission_type'];

			$options['package_count']		= $tmp_list['package_count'];
			$options['package_goods_name1']	= $tmp_list['package_goods_name1'];
			$options['package_option_seq1']	= $tmp_list['package_option_seq1'];
			$options['package_option1']		= $tmp_list['package_option1'];
			$options['package_unit_ea1']	= $tmp_list['package_unit_ea1'];
			$options['package_unit_ea1']	= $tmp_list['package_unit_ea1'];
			$options['weight']				= $tmp_list['weight'];
			$options['option_view']			= $tmp_list['option_view'];

			$this->db->insert( 'fm_goods_suboption', $options );
			$suboption_seq	= $this->db->insert_id();

			if($suboption_seq){
				unset($supply);
				$supply['goods_seq']			= $this->optionReplace($goods_seq, 'int');
				$supply['suboption_seq']		= $suboption_seq;
				$supply['supply_price']			= $this->optionReplace($tmpSupply['supply_price'], 'float');
				$supply['exchange_rate']			= $this->exchange_rate_krw;
				$supply['stock']				= $this->optionReplace($tmpSupply['stock'], 'int');
				$supply['badstock']				= $this->optionReplace($tmpSupply['badstock'], 'int');
				$supply['reservation15']		= $this->optionReplace($tmpSupply['reservation15'], 'int');
				$supply['reservation25']		= $this->optionReplace($tmpSupply['reservation25'], 'int');
				$supply['ablestock15']			= $this->optionReplace($tmpSupply['ablestock15'], 'int');
				$supply['safe_stock']			= $this->optionReplace($tmpSupply['safe_stock'], 'int');
				$supply['total_supply_price']	= $this->optionReplace($tmpSupply['total_supply_price'], 'float');
				$supply['total_stock']			= $this->optionReplace($tmpSupply['total_stock'], 'int');
				$supply['total_badstock']		= $this->optionReplace($tmpSupply['total_badstock'], 'int');
				$this->db->insert( 'fm_goods_supply', $supply );
			}
		}

		$pacakge_yn = 'n';
		if( $tmp_list['package_count'] ){
			$pacakge_yn = 'y';
		}
		$query = "update fm_goods set package_yn_suboption=? where goods_seq=?";
		$this->db->query($query,array($pacakge_yn,$options['goods_seq']));


		$query		= "delete from fm_goods_suboption_tmp where tmp_no = ? ";
		$this->db->query($query,array($tmp_seq));
		$query		= "delete from fm_goods_supply_tmp where tmp_no = ? and suboption_seq > 0";
		$this->db->query($query,array($tmp_seq));
	}

	public function delete_option_info($goods_seq){
		$query	= "delete from fm_goods_option where goods_seq = '".$goods_seq."' ";
		$this->db->query($query);
		$query	= "delete from fm_goods_supply "
				. "where goods_seq = '".$goods_seq."' and option_seq > 0";
		$this->db->query($query);
	}

	public function delete_sub_option_info($goods_seq){
		$query	= "delete from fm_goods_suboption where goods_seq = '".$goods_seq."' ";
		$this->db->query($query);
		$query	= "delete from fm_goods_supply "
				. "where goods_seq = '".$goods_seq."' and suboption_seq > 0";
		$this->db->query($query);
	}

	public function get_possible_pay_text($possible_pay){

		$possible_pay = str_replace("card", "신용카드", $possible_pay);
		$possible_pay = str_replace("escrow_account", "에스크로 계좌이체", $possible_pay);
		$possible_pay = str_replace("escrow_virtual", "에스크로 가상계좌", $possible_pay);
		$possible_pay = str_replace("account", "계좌이체", $possible_pay);
		$possible_pay = str_replace("virtual", "가상계좌", $possible_pay);
		$possible_pay = str_replace("cellphone", "핸드폰", $possible_pay);
		$possible_pay = str_replace("bank", "무통장 입금", $possible_pay);
		$possible_pay = str_replace("kakaopay", "카카오페이", $possible_pay);
		$possible_pay = str_replace("payco", "페이코", $possible_pay);
		$possible_pay = str_replace("naverpayment", "네이버페이 결제형", $possible_pay);
		$possible_pay = str_replace("paypal", "페이팔", $possible_pay);
		$possible_pay = str_replace("alipay", "알리페이", $possible_pay);
		$possible_pay = str_replace("axes", "엑시즈", $possible_pay);
		$possible_pay = str_replace("eximbay", "엑심베이", $possible_pay);

		return $possible_pay;
	}

	// 옵션명 가공 함수 :: 2017-05-24 lwh
	public function optionToStr($data){
		if($data['option1'] === null) return false;

		$returnStr = array();
		if($data['title1'])		$returnStr[1] .= $data['title1'] . ':';
		if($data['option1'])	$returnStr[1] .= $data['option1'];
		if($data['title2'])		$returnStr[2] .= $data['title2'] . ':';
		if($data['option2'])	$returnStr[2] .= $data['option2'];
		if($data['title3'])		$returnStr[3] .= $data['title3'] . ':';
		if($data['option3'])	$returnStr[3] .= $data['option3'];
		if($data['title4'])		$returnStr[4] .= $data['title4'] . ':';
		if($data['option4'])	$returnStr[4] .= $data['option4'];
		if($data['title5'])		$returnStr[5] .= $data['title5'] . ':';
		if($data['option5'])	$returnStr[5] .= $data['option5'];

		return implode(', ', $returnStr);
	}

	public function optionReplace($val, $valType = 'str', $cutting = true){
		if($val) $val = trim($val);
		switch($valType){
			case 'float':
				$tmp	= explode('.', $val);
				$tmp[0]	= preg_replace('/[^0-9]/', '', $tmp[0]);
				if(is_null($tmp[0]))	$tmp[0]	= '0';
				$val	= implode('.', $tmp);
			break;
			case 'int':
				$val	= preg_replace('/[^0-9]/', '', $val);
				if(is_null($val))	$val	= '0';
			break;
			case 'str':
				$val	= preg_replace('/[\"]/', '', $val);
				if(is_null($val))	$val	= '';
			break;
		}

		if($valType != "str" && $cutting) $val = get_cutting_price($val);

		return $val;
	}

	//자주사용하는 상품 필수/추가구성/추가입력 옵션
	public function frequentlygoods($Type='opt',$goods_seq=null,$socialcp=null,$package_yn=null){
		$result = false;
		if($goods_seq){
			$query = "select goods_name,goods_seq from fm_goods where frequently".$Type."=1 and goods_seq!='".$goods_seq."'";
		}else{
			$query = "select goods_name,goods_seq from fm_goods where frequently".$Type."=1 ";
		}
		// 입점사 일경우 입점사 상품 목록내에서만 노출되도록 수정 :: 2018-01-29 lkh
		if (defined('__SELLERADMIN__') === true) {
			$query .= " and provider_seq = '".$this->providerInfo['provider_seq']."'";
		}
		$query .= ($socialcp)? " and goods_kind ='coupon' ":" and goods_kind ='goods' ";

		$package_field = "package_yn";
		if( $Type == 'sub' ) {
			$package_field = "package_yn_suboption";
		}

		// 올인원이면 무조건 package_yn = 'y' - selleradmin 은 항상 모두가져옴
		// 일반은 package_yn 값에 따라 'y', 'n', ''(all)
		if	(!$this->scm_cfg)	$this->scm_cfg	= config_load('scm');
		if	($this->scm_cfg['use'] == 'Y' && defined('__SELLERADMIN__') === false){
			$query .=" and ".$package_field."='y'";
		} else if($package_yn){
			$query .=" and ".$package_field."='".$package_yn."'";
		}

		$query = $this->db->query($query);
		$result = $query->result_array();
		return $result;
	}

	//자주사용하는 상품 필수/추가구성/추가입력 옵션
	public function frequentlygoodsPaging($Type='opt', $goods_seq=null, $socialcp=null, $package_yn=null, $page = null, $perpage = null,$provider_seq=null){

		$return = array();

		if(!$page){
			$offset = 0;
		} else {
			$offset = ($page-1) * $perpage;
		}

		if(!$perpage){
			$perpage = 10;
		}

		$package_field = "package_yn";
		if( $Type == 'sub' ) {
			$package_field = "package_yn_suboption";
		}

		// 입력옵션은 패키지/올인원 상관없이 공통으로 사용함 - 재고체크안하기 때문에
		if( $Type == 'inp') {
			$package_yn = "";
		}

		$result = false;
		if($goods_seq){
			$querySQL = "select goods_name,goods_seq,".$package_field." from fm_goods where frequently".$Type."=1 and goods_seq!='".$goods_seq."'";
		}else{
			$querySQL = "select goods_name,goods_seq,".$package_field." from fm_goods where frequently".$Type."=1";
		}
		// 입점사 일경우 입점사 상품 목록내에서만 노출되도록 수정 :: 2018-01-29 lkh
		if (defined('__SELLERADMIN__') === true) {
			$querySQL .= " and provider_seq = '".$this->providerInfo['provider_seq']."'";
		}else if($provider_seq){
			$querySQL .= " and provider_seq = '".$provider_seq."'";
		}
		$querySQL .= ($socialcp)? " and goods_kind ='coupon' ":" and goods_kind ='goods' ";

		// 올인원이면 무조건 package_yn = 'y' - selleradmin 은 항상 모두가져옴
		// 일반은 package_yn 값에 따라 'y', 'n', ''(all)
		if	(!$this->scm_cfg)	$this->scm_cfg	= config_load('scm');
		if	($this->scm_cfg['use'] == 'Y' && defined('__SELLERADMIN__') === false && $Type == "sub"){
			$querySQL .=" and ".$package_field."='y'";
		} else if($package_yn){
			$querySQL .=" and ".$package_field."='".$package_yn."'";
		}

		$query 				= $this->db->query($querySQL);
		$result 			= $query->result_array();
		$return['total'] 	= count($result);

		if($page == 'all'){
			$querySQL .=" order by goods_seq DESC";
		} else {
			$querySQL .=" order by goods_seq DESC limit {$offset}, {$perpage}";
		}
		$query = $this->db->query($querySQL);
		//debug($querySQL);
		$result = $query->result_array();
		$return['result'] = $result;
		return $return;
	}

	// 필수옵션에서 지역정보 데이터를 추출
	public function get_option_address($goods_seq){
		$sql		= "select newtype, option1, option2, option3, option4, option5
						from fm_goods_option where goods_seq = ? and newtype like '%address%' ";
		$query		= $this->db->query($sql, array($goods_seq));
		$result		= $query->result_array();
		$address	= array();
		if	($result){
			foreach($result as $k => $data){
				$address_fld	= 'option1';
				if	(preg_match('/\,/', $data['newtype'])){
					$typeArr		= explode(',', $data['newtype']);
					$tmp_no			= array_search('address', $typeArr) + 1;
					$address_fld	= 'option'.$tmp_no;
				}

				$address[]	= $data[$address_fld];
			}
		}

		return array_unique($address);
	}

	// 필수옵션에서 지역 수수료 데이터를 추출
	public function get_option_address_commission($goods_seq,$use_coupon_area){
		$sql		= "select newtype, option1, option2, option3, option4, option5, address_commission
						from fm_goods_option where goods_seq = ? and newtype like '%address%' ";
		$query		= $this->db->query($sql, array($goods_seq));
		$result		= $query->result_array();
		$address_commission = 0;
		if	($result){
			foreach($result as $k => $data){
				$address_fld	= 'option1';
				if	(preg_match('/\,/', $data['newtype'])){
					$typeArr		= explode(',', $data['newtype']);
					$tmp_no			= array_search('address', $typeArr) + 1;
					$address_fld	= 'option'.$tmp_no;
				}
				if( trim($data[$address_fld]) == trim($use_coupon_area) ) {
					$address_commission = $data['address_commission'];
					break;
				}
			}//end foreach
		}
		return $address_commission;
	}

	public function getCategoryGoodsColors($sc){

		$binds = $colors = array();

		$sql = "select DISTINCT ifnull(o.color,'') as colors from fm_goods g ";

		if($sc['category_code']){
			$sql .= " inner join fm_category_link c on (g.goods_seq = c.goods_seq and c.category_code=?) ";
			$binds[] = $sc['category_code'];
		}

		if($sc['brand_code']){
			$sql .= " inner join fm_brand_link b on (g.goods_seq = b.goods_seq and b.category_code=?) ";
			$binds[] = $sc['brand_code'];
		}

		if($sc['brands']){
			$sql .= " inner join fm_brand_link b on (g.goods_seq = b.goods_seq and b.category_code in ('".implode("','",$sc['brands'])."')) ";
		}

		$sql .= " inner join fm_goods_option o on g.goods_seq = o.goods_seq  and o.color != '' ";
		if(!empty($this->categoryData['list_goods_status'])) $sql .= " and g.goods_status in ('".str_replace('|',"','",$this->categoryData['list_goods_status'])."')";
		$sql .= "  WHERE g.`provider_status`='1' AND g.`goods_view`='look'";
		$sql .= "  GROUP BY o.color";
		$query = $this->db->query($sql,$binds);

		foreach ($query->result_array() as $row) {
			$colors[] = $row['colors'];
		}

		return $colors;
	}

	// 티켓번호 중복 체크
	public function chkDuple_coupon_serial($coupon_serial){
		$sql	= "select * from fm_goods_coupon_serial where coupon_serial = '".$coupon_serial."' ";
		$query	= $this->db->query($sql);
		$result	= $query->row_array();

		if	($result['coupon_serial'])	return true;
		else							return false;
	}

	// 외부 티켓번호 추출
	public function get_outcoupon_list($goods_seq){
		$sql	= "select * from fm_goods_coupon_serial where goods_seq = ? ";
		$query	= $this->db->query($sql, array($goods_seq));
		$result	= $query->result_array();

		return $result;
	}

	// 외부 티켓번호 일괄등록
	public function coupon_serial_upload($filename){

		$this->load->library('pxl');
		set_time_limit(0);
		ini_set('memory_limit', '3500M');

		$cacheMethod		= PHPExcel_CachedObjectStorageFactory:: cache_to_phpTemp;
		$cacheSettings		= array( ' memoryCacheSize ' => '5120MB');
		PHPExcel_Settings::setCacheStorageMethod($cacheMethod, $cacheSettings);
		$this->objPHPExcel	= new PHPExcel();

		// 업로드 된 엑셀 형식에 맞는 Reader객체를 만든다.
		$objReader		= IOFactory::createReaderForFile($filename);
		// 읽기전용으로 설정
		$objReader->setReadDataOnly(true);
		// 엑셀파일을 읽는다
		$objExcel		= $objReader->load($filename);
		// 첫번째 시트를 선택
		$objExcel->setActiveSheetIndex(0);
		$objWorksheet	= $objExcel->getActiveSheet();
		$maxRow			= $objWorksheet->getHighestRow();
		$maxCol			= $objWorksheet->getHighestColumn();

		for ($i = 1 ; $i <= $maxRow ; $i++) {
			$coupon_serial			= $objWorksheet->getCell('A'.$i)->getValue();
			$coupon_serial			= preg_replace('/[^a-zA-Z0-9\-\_]/', '', trim($coupon_serial));
			$result[$coupon_serial]	= 'y';
			if($this->chkDuple_coupon_serial($coupon_serial) || !$coupon_serial){
				$result[$coupon_serial]	= 'n';
			}
		}

		return $result;
	}

	// 출고 시 외부 티켓번호 추출
	public function get_out_coupon_serial_code($goods_seq){
		$goods	= $this->get_goods($goods_seq);
		if	($goods['coupon_serial_type'] == 'n'){
			$sql	= "select coupon_serial from fm_goods_coupon_serial where goods_seq = ? "
					. "and (export_code = '' or export_code is null) "
					. "order by coupon_serial limit 1 ";
			$query	= $this->db->query($sql, array($goods_seq));
			$result	= $query->row_array();
			if	($result['coupon_serial']){
				return $result['coupon_serial'];
			}else{
				return false;
			}
		}else{
			return 'a';
		}

		return false;
	}

	// 출고 시 외부 티켓번호 사용처리
	public function use_out_coupon_serial_code($coupon_serial, $goods_seq, $export_code){

		$where_arr['coupon_serial']	= $coupon_serial;
		$where_arr['goods_seq']		= $goods_seq;
		$update_arr['export_code']	= $export_code;
		$update_arr['export_date']	= date('Y-m-d H:i:s');
		$this->db->where($where_arr);
		$this->db->update('fm_goods_coupon_serial', $update_arr);

		// 외부 티켓일 경우 티켓 코드가 없으면 품절 처리한다.
		$sql	= "select coupon_serial from fm_goods_coupon_serial where goods_seq = ? "
				. "and (export_code = '' or export_code is null) "
				. "order by coupon_serial limit 1 ";
		$query	= $this->db->query($sql, array($goods_seq));
		$result	= $query->row_array();
		if	(!$result['coupon_serial']){
			$where_arr2['goods_seq']		= $goods_seq;
			$update_arr2['goods_status']	= 'runout';
			$this->db->where($where_arr2);
			$this->db->update('fm_goods', $update_arr2);
		}
	}

	// 상품 공통 관련상품디스플레이 설정키값 가져오기
	// 2015-12-30 jhr 모바일 관련상품 추가
	// 2019-01-02 pjw 반응형 관련상품 추가
	public function get_goods_relation_display_seq(){
		$query  = $this->db->query("select * from fm_design_display where kind='relation' and platform != 'responsive' ");
		$display = $query->row_array();
		$query  = $this->db->query("select * from fm_design_display where kind='relation_mobile'");
		$m_display = $query->row_array();
		$query  = $this->db->query("select * from fm_design_display where kind='relation' and platform='responsive' ");
		$r_display = $query->row_array();

		if(!$display){
			$this->db->insert("fm_design_display",array(
				'admin_comment'	=> '관련상품',
				'kind'			=> 'relation',
				'count_w'		=> 4,
				'count_h'		=> 1,
				'style'			=> 'lattice_a',
				'image_size'	=> 'list1',
				'text_align'	=> 'left',
				'info_settings' => '[{"kind":"goods_name", "font_decoration":"{\\"color\\":\\"#000000\\", \\"bold\\":\\"normal\\", \\"underline\\":\\"none\\"}"},{"kind":"summary", "font_decoration":"{\\"color\\":\\"#000000\\", \\"bold\\":\\"normal\\", \\"underline\\":\\"none\\"}"},{"kind":"icon"},{"kind":"consumer_price", "font_decoration":"{\\"color\\":\\"#000000\\", \\"bold\\":\\"normal\\", \\"underline\\":\\"none\\"}", "postfix":"'.$this->config_system['basic_currency'].'"},{"kind":"price", "font_decoration":"{\\"color\\":\\"#000000\\", \\"bold\\":\\"normal\\", \\"underline\\":\\"none\\"}", "postfix":"'.$this->config_system['basic_currency'].'"},{"kind":"fblike"},{"kind":"status_icon"}]',
			));
			$query		= $this->db->query("select * from fm_design_display where kind='relation'");
			$display	= $query->row_array();
		}
		if(!$m_display){
			$this->db->insert("fm_design_display",array(
				'admin_comment'	=> '관련상품',
				'kind'			=> 'relation_mobile',
				'count_w'		=> 4,
				'count_h'		=> 1,
				'count_w_swipe'	=> 4,
				'count_h_swipe'	=> 1,
				'style'			=> 'newswipe',
				'image_size'	=> 'list1',
				'text_align'	=> 'left',
				'platform '		=> 'mobile',
				'navigation_paging_style' => 'paging_style_1',
				'info_settings' => '[{"kind":"goods_name", "font_decoration":"{\\"color\\":\\"#000000\\", \\"bold\\":\\"normal\\", \\"underline\\":\\"none\\"}"},{"kind":"summary", "font_decoration":"{\\"color\\":\\"#000000\\", \\"bold\\":\\"normal\\", \\"underline\\":\\"none\\"}"},{"kind":"icon"},{"kind":"consumer_price", "font_decoration":"{\\"color\\":\\"#000000\\", \\"bold\\":\\"normal\\", \\"underline\\":\\"none\\"}", "postfix":"'.$this->config_system['basic_currency'].'"},{"kind":"price", "font_decoration":"{\\"color\\":\\"#000000\\", \\"bold\\":\\"normal\\", \\"underline\\":\\"none\\"}", "postfix":"'.$this->config_system['basic_currency'].'"},{"kind":"fblike"},{"kind":"status_icon"}]',
			));
			$query		= $this->db->query("select * from fm_design_display where kind='relation_mobile'");
			$m_display	= $query->row_array();
		}
		if(!$r_display){
			$this->db->insert("fm_design_display",array(
				'admin_comment'					=> '관련상품',
				'kind'							=> 'relation',
				'count_r'						=> 8,
				'style'							=> 'sizeswipe',
				'image_size'					=> 'list1',
				'text_align'					=> 'center',
				'platform '						=> 'responsive',
				'navigation_paging_style'		=> 'paging_style_1',
				'info_settings'					=> '{}',
				'goods_decoration_type'			=> 'favorite',
				'goods_decoration_favorite_key' => 'goods_info_style_1',
				'goods_decoration_favorite'		=> '',
			));
			$query		= $this->db->query("select * from fm_design_display where kind='relation' and platform='responsive' ");
			$r_display	= $query->row_array();
		}
		return array('display_seq'=>$display['display_seq'],'m_display_seq'=>$m_display['display_seq'], 'r_display_seq'=>$r_display['display_seq']);
	}

	public function get_goods_relation_seller_display_seq(){
		$query  = $this->db->query("select * from fm_design_display where kind='relation_seller' and platform != 'responsive' ");
		$display = $query->row_array();
		$query  = $this->db->query("select * from fm_design_display where kind='relation_seller_mobile'");
		$m_display = $query->row_array();
		$query  = $this->db->query("select * from fm_design_display where kind='relation_seller' and platform = 'responsive' ");
		$r_display = $query->row_array();

		if(!$display){
			$this->db->insert("fm_design_display",array(
				'admin_comment'	=> '관련상품',
				'kind'			=> 'relation_seller',
				'count_w'		=> 4,
				'count_h'		=> 1,
				'style'			=> 'lattice_a',
				'image_size'	=> 'list1',
				'text_align'	=> 'left',
				'info_settings' => '[{"kind":"goods_name", "font_decoration":"{\\"color\\":\\"#000000\\", \\"bold\\":\\"normal\\", \\"underline\\":\\"none\\"}"},{"kind":"summary", "font_decoration":"{\\"color\\":\\"#000000\\", \\"bold\\":\\"normal\\", \\"underline\\":\\"none\\"}"},{"kind":"icon"},{"kind":"consumer_price", "font_decoration":"{\\"color\\":\\"#000000\\", \\"bold\\":\\"normal\\", \\"underline\\":\\"none\\"}", "postfix":"'.$this->config_system['basic_currency'].'"},{"kind":"price", "font_decoration":"{\\"color\\":\\"#000000\\", \\"bold\\":\\"normal\\", \\"underline\\":\\"none\\"}", "postfix":"'.$this->config_system['basic_currency'].'"},{"kind":"fblike"},{"kind":"status_icon"}]',
			));
			$query			= $this->db->query("select * from fm_design_display where kind='relation_seller' and platform != 'responsive'");
			$display		= $query->row_array();
		}
		if(!$m_display){
			$this->db->insert("fm_design_display",array(
				'admin_comment'	=> '관련상품',
				'kind'			=> 'relation_seller_mobile',
				'count_w'		=> 4,
				'count_h'		=> 1,
				'count_w_swipe'	=> 4,
				'count_h_swipe'	=> 1,
				'style'			=> 'newswipe',
				'image_size'	=> 'list1',
				'text_align'	=> 'left',
				'platform '		=> 'mobile',
				'navigation_paging_style' => 'paging_style_1',
				'info_settings' => '[{"kind":"goods_name", "font_decoration":"{\\"color\\":\\"#000000\\", \\"bold\\":\\"normal\\", \\"underline\\":\\"none\\"}"},{"kind":"summary", "font_decoration":"{\\"color\\":\\"#000000\\", \\"bold\\":\\"normal\\", \\"underline\\":\\"none\\"}"},{"kind":"icon"},{"kind":"consumer_price", "font_decoration":"{\\"color\\":\\"#000000\\", \\"bold\\":\\"normal\\", \\"underline\\":\\"none\\"}", "postfix":"'.$this->config_system['basic_currency'].'"},{"kind":"price", "font_decoration":"{\\"color\\":\\"#000000\\", \\"bold\\":\\"normal\\", \\"underline\\":\\"none\\"}", "postfix":"'.$this->config_system['basic_currency'].'"},{"kind":"fblike"},{"kind":"status_icon"}]',
			));
			$query			= $this->db->query("select * from fm_design_display where kind='relation_seller_mobile'");
			$m_display		= $query->row_array();
		}
		if(!$r_display){
			$this->db->insert("fm_design_display",array(
				'admin_comment'					=> '관련상품',
				'kind'							=> 'relation_seller',
				'count_w'						=> 4,
				'count_h'						=> 1,
				'style'							=> 'sizeswipe',
				'image_size'					=> 'list1',
				'text_align'					=> 'center',
				'platform '						=> 'responsive',
				'info_settings'					=> '{}',
				'goods_decoration_type'			=> 'favorite',
				'goods_decoration_favorite_key' => 'goods_info_style_1',
				'goods_decoration_favorite'		=> '',
			));
			$query			= $this->db->query("select * from fm_design_display where kind='relation_seller' and platform = 'responsive'");
			$r_display		= $query->row_array();
		}

		return array('display_seq'=>$display['display_seq'],'m_display_seq'=>$m_display['display_seq'], 'r_display_seq'=>$r_display['display_seq']);
	}

	//2016-03-25 빅데이터 전용 디스플레이
	public function get_goods_bigdata_display_seq(){
		$query  = $this->db->query("select * from fm_design_display where kind='bigdata' and platform != 'responsive' ");
		$display = $query->row_array();
		$query  = $this->db->query("select * from fm_design_display where kind='bigdata_mobile'");
		$m_display = $query->row_array();
		$query  = $this->db->query("select * from fm_design_display where kind='bigdata' and platform = 'responsive' ");
		$r_display = $query->row_array();

		if(!$display){
			$this->db->insert("fm_design_display",array(
				'admin_comment'	=> '빅데이터 추천상품 디스플레이',
				'kind'			=> 'bigdata',
				'count_w'		=> 4,
				'count_h'		=> 1,
				'style'			=> 'lattice_a',
				'image_size'	=> 'list1',
				'text_align'	=> 'left',
				'info_settings' => '[{"kind":"goods_name", "font_decoration":"{\\"color\\":\\"#000000\\", \\"bold\\":\\"normal\\", \\"underline\\":\\"none\\"}"},{"kind":"summary", "font_decoration":"{\\"color\\":\\"#000000\\", \\"bold\\":\\"normal\\", \\"underline\\":\\"none\\"}"},{"kind":"icon"},{"kind":"consumer_price", "font_decoration":"{\\"color\\":\\"#000000\\", \\"bold\\":\\"normal\\", \\"underline\\":\\"none\\"}", "postfix":"'.$this->config_system['basic_currency'].'"},{"kind":"price", "font_decoration":"{\\"color\\":\\"#000000\\", \\"bold\\":\\"normal\\", \\"underline\\":\\"none\\"}", "postfix":"'.$this->config_system['basic_currency'].'"},{"kind":"fblike"},{"kind":"status_icon"}]',
			));
			$query  = $this->db->query("select * from fm_design_display where kind='bigdata' and platform != 'responsive'");
			$display = $query->row_array();
		}
		if(!$m_display){
			$this->db->insert("fm_design_display",array(
				'admin_comment'	=> '빅데이터 추천상품 모바일 디스플레이',
				'kind'			=> 'bigdata_mobile',
				'count_w'		=> 4,
				'count_h'		=> 1,
				'count_w_swipe'	=> 4,
				'count_h_swipe'	=> 1,
				'style'			=> 'newswipe',
				'image_size'	=> 'list1',
				'text_align'	=> 'left',
				'platform '		=> 'mobile',
				'navigation_paging_style' => 'paging_style_1',
				'info_settings' => '[{"kind":"goods_name", "font_decoration":"{\\"color\\":\\"#000000\\", \\"bold\\":\\"normal\\", \\"underline\\":\\"none\\"}"},{"kind":"summary", "font_decoration":"{\\"color\\":\\"#000000\\", \\"bold\\":\\"normal\\", \\"underline\\":\\"none\\"}"},{"kind":"icon"},{"kind":"consumer_price", "font_decoration":"{\\"color\\":\\"#000000\\", \\"bold\\":\\"normal\\", \\"underline\\":\\"none\\"}", "postfix":"'.$this->config_system['basic_currency'].'"},{"kind":"price", "font_decoration":"{\\"color\\":\\"#000000\\", \\"bold\\":\\"normal\\", \\"underline\\":\\"none\\"}", "postfix":"'.$this->config_system['basic_currency'].'"},{"kind":"fblike"},{"kind":"status_icon"}]',
			));
			$query  = $this->db->query("select * from fm_design_display where kind='bigdata_mobile'");
			$m_display = $query->row_array();
		}
		if(!$r_display){
			$this->db->insert("fm_design_display",array(
				'admin_comment'					=> '빅데이터 추천상품 디스플레이',
				'kind'							=> 'bigdata',
				'count_w'						=> 4,
				'count_h'						=> 1,
				'style'							=> 'sizeswipe',
				'image_size'					=> 'list1',
				'text_align'					=> 'center',
				'platform '						=> 'responsive',
				'info_settings'					=> '[{"kind":"goods_name", "font_decoration":"{\\"color\\":\\"#000000\\", \\"bold\\":\\"normal\\", \\"underline\\":\\"none\\"}"},{"kind":"summary", "font_decoration":"{\\"color\\":\\"#000000\\", \\"bold\\":\\"normal\\", \\"underline\\":\\"none\\"}"},{"kind":"icon"},{"kind":"consumer_price", "font_decoration":"{\\"color\\":\\"#000000\\", \\"bold\\":\\"normal\\", \\"underline\\":\\"none\\"}", "postfix":"'.$this->config_system['basic_currency'].'"},{"kind":"price", "font_decoration":"{\\"color\\":\\"#000000\\", \\"bold\\":\\"normal\\", \\"underline\\":\\"none\\"}", "postfix":"'.$this->config_system['basic_currency'].'"},{"kind":"fblike"},{"kind":"status_icon"}]',
				'goods_decoration_type'			=> 'favorite',
				'goods_decoration_favorite_key' => 'goods_info_style_1',
				'goods_decoration_favorite'		=> '',
			));
			$query  = $this->db->query("select * from fm_design_display where kind='bigdata' and platform = 'responsive'");
			$r_display = $query->row_array();
		}
		return array('display_seq'=>$display['display_seq'],'m_display_seq'=>$m_display['display_seq'], 'r_display_seq'=>$r_display['display_seq']);
	}

	//상품디스플레이 생성 기본 모듈추가 @2016-07-08 ysm
	public function get_goods_display_insert($type = 'pc',$title,$kind) {
		$this->load->model('goodsdisplay');
		$display = $this->goodsdisplay->get_display_type($kind, $type);
		if( $type == 'responsive' ) {
			if(!$display){
				$this->db->insert("fm_design_display",array(
					'admin_comment'					=> $title,
					'kind'							=> $kind,
					'count_w'						=> 4,
					'count_h'						=> 1,
					'count_w_swipe'					=> 4,
					'count_h_swipe'					=> 1,
					'style'							=> 'sizeswipe',
					'image_size'					=> 'list1',
					'text_align'					=> 'center',
					'platform '						=> 'responsive',
					'navigation_paging_style'		=> 'paging_style_1',
					'info_settings'					=> '{}',
					'regdate'						=> date('Y-m-d H:i:s'),
					'goods_decoration_type'			=> 'favorite',
					'goods_decoration_favorite_key' => 'goods_info_style_1',
					'goods_decoration_favorite'		=> '',
				));
				$query  = $this->db->query("select * from fm_design_display where kind='{$kind}'  and platform ='responsive' order by display_seq desc");
				$display = $query->row_array();
			}
		}else if( $type == 'mobile' ) {
			if(!$display){
				$this->db->insert("fm_design_display",array(
					'admin_comment'	=> $title,
					'kind'			=> $kind,
					'count_w'		=> 4,
					'count_h'		=> 1,
					'count_w_swipe'	=> 4,
					'count_h_swipe'	=> 1,
					'style'			=> 'newswipe',
					'image_size'	=> 'list1',
					'text_align'	=> 'left',
					'platform '		=> 'mobile',
					'navigation_paging_style' => 'paging_style_1',
					'info_settings' => '[{"kind":"goods_name", "font_decoration":"{\\"color\\":\\"#000000\\", \\"bold\\":\\"normal\\", \\"underline\\":\\"none\\"}"},{"kind":"summary", "font_decoration":"{\\"color\\":\\"#000000\\", \\"bold\\":\\"normal\\", \\"underline\\":\\"none\\"}"},{"kind":"icon"},{"kind":"consumer_price", "font_decoration":"{\\"color\\":\\"#000000\\", \\"bold\\":\\"normal\\", \\"underline\\":\\"none\\"}", "postfix":"won"},{"kind":"price", "font_decoration":"{\\"color\\":\\"#000000\\", \\"bold\\":\\"normal\\", \\"underline\\":\\"none\\"}", "postfix":"원"},{"kind":"fblike"},{"kind":"status_icon"}]',
					'regdate' => date('Y-m-d H:i:s')
				));
				$query  = $this->db->query("select * from fm_design_display where kind='{$kind}'  and platform ='mobile' order by display_seq desc");
				$display = $query->row_array();
			}
		}else{
			if(!$display){
				$this->db->insert("fm_design_display",array(
					'admin_comment'	=> $title,
					'kind'			=> $kind,
					'count_w'		=> 4,
					'count_h'		=> 1,
					'style'			=> 'lattice_a',
					'image_size'	=> 'list1',
					'text_align'	=> 'left',
					'info_settings' => '[{"kind":"goods_name", "font_decoration":"{\\"color\\":\\"#000000\\", \\"bold\\":\\"normal\\", \\"underline\\":\\"none\\"}"},{"kind":"summary", "font_decoration":"{\\"color\\":\\"#000000\\", \\"bold\\":\\"normal\\", \\"underline\\":\\"none\\"}"},{"kind":"icon"},{"kind":"consumer_price", "font_decoration":"{\\"color\\":\\"#000000\\", \\"bold\\":\\"normal\\", \\"underline\\":\\"none\\"}", "postfix":"won"},{"kind":"price", "font_decoration":"{\\"color\\":\\"#000000\\", \\"bold\\":\\"normal\\", \\"underline\\":\\"none\\"}", "postfix":"원"},{"kind":"fblike"},{"kind":"status_icon"}]',
					'regdate' => date('Y-m-d H:i:s')
				));
				$query  = $this->db->query("select * from fm_design_display where kind='{$kind}' and platform ='pc' order by display_seq desc");
				$display = $query->row_array();
			}
		}
		return array('display_seq'=>$display['display_seq'],'display'=>$display);
	}


	// 입점사의 모든 상품의 상태 변경
	public function change_all_provider_status($provider_seq,$provider_status,$goods_status,$goods_view)
	{
		$bind = array();
		$set_field_arr = '';

		if($provider_status)
		{
			// enum('0', '1')
			$set_field_arr[] = "provider_status=?";
			$bind[]	= $provider_status;
		}
		if($goods_status)
		{
			// enum('normal', 'runout', 'purchasing', 'unsold')
			$set_field_arr[] = "goods_status=?";
			$bind[]	= $goods_status;
		}
		if($goods_view)
		{
			// enum('look', 'notLook')
			$set_field_arr[] = "goods_view=?";
			$bind[]	= $goods_view;
		}
		$bind[]	= $provider_seq;

		if(count($bind)>1 && $set_field_arr){
			$query = "update fm_goods set ".implode(',',$set_field_arr)." where provider_seq=?";
			$this->db->query($query,$bind);
		}

	}

	// 임시 필수 옵션 단순 저장
	public function save_tmp_option($whrParam, $upParam, $target = null, $reserve_policy = null){

		//기본옵션은 무조건 노출
		if ($target == 'option_view' && $upParam['option_view'] == 'N')
			$whrParam['default_option']	= 'n';

		/* 가격관련 필드 기본통화 기준에 맞게 절사처리 */
		foreach($upParam as $key=>$val){
			if(in_array($key,array("consumer_price","price","reserve","tmpprice"))){
				$upParam[$key] = get_cutting_price($val);
			}else{
				$upParam[$key] = $val;
			}
		}
		$this->db->where($whrParam);
		$data	= $this->db->update('fm_goods_option_tmp', $upParam);
		$addWhere = array();
		foreach($whrParam as $fld => $val){
			$addWhere[]	= $fld . " = '".$val."' ";
		}

		$sql	= "select price,reserve_rate from fm_goods_option_tmp where ".implode(' and ', $addWhere);
		$query  = $this->db->query($sql);
		$res	= $query->row_array();

		if( $target == 'price' && $reserve_policy == 'goods' ) {//할인가(판매가) 일괄변경하면서 적립금 개별정책일때

				$reserve = get_cutting_price($upParam['price'] * $res['reserve_rate'] / 100);
				$sql	= "update fm_goods_option_tmp set reserve = '".$reserve."' where ".implode(' and ', $addWhere);
				$this->db->query($sql);
		}else{
			// 1) 적립금 지급 정책 변경 시 통합설정 값으로 일괄 변경
			// 2) 할인가(판매가) 일괄적용시 적립금이 통합정책시
			if	(isset($upParam['reserve_rate']) && !empty($upParam['reserve_unit'])){

				$reserve_rate	= $upParam['reserve_rate'];
				$reserve_unit	= $upParam['reserve_unit'];
				if	($reserve_unit == 'percent'){
					$reserve = get_cutting_price($res['price'] * $reserve_rate / 100);
					$sql	= "update fm_goods_option_tmp set reserve = '".$reserve."' where ".implode(' and ', $addWhere);
				}else{
					$sql	= "update fm_goods_option_tmp set reserve = '".get_cutting_price($reserve_rate)."' where ".implode(' and ', $addWhere);
				}

				$this->db->query($sql);
			}
		}
	}

	// 임시 추가 옵션 단순 저장
	public function save_tmp_suboption($whrParam, $upParam, $target = null, $reserve_policy = null){
		$this->db->where($whrParam);
		$data	= $this->db->update('fm_goods_suboption_tmp', $upParam);

		if( $target == 'price' && $reserve_policy == 'goods' ) {//할인가(판매가) 일괄변경하면서 적립금 개별정책일때
				foreach($whrParam as $fld => $val){
					$addWhere[]	= $fld . " = '".$val."' ";
				}
				$addWhere[] = "reserve_unit = 'percent'";
				$sql	= "update fm_goods_suboption_tmp set reserve = (FLOOR(".$upParam['price']." * reserve_rate/100)) where ".implode(' and ', $addWhere);
				$this->db->query($sql);
		}else{
			// 1) 적립금 지급 정책 변경 시 통합설정 값으로 일괄 변경
			// 2) 할인가(판매가) 일괄적용시 적립금이 통합정책시
			if	(isset($upParam['reserve_rate']) && !empty($upParam['reserve_unit'])){
				foreach($whrParam as $fld => $val){
					$addWhere[]	= $fld . " = '".$val."' ";
				}
				$reserve_rate	= $upParam['reserve_rate'];
				$reserve_unit	= $upParam['reserve_unit'];
				if	($reserve_unit == 'percent')
					$sql	= "update fm_goods_suboption_tmp set reserve = (FLOOR(price * ".$reserve_rate."/100)) where ".implode(' and ', $addWhere);
				else
					$sql	= "update fm_goods_suboption_tmp set reserve = ".$reserve_rate." where ".implode(' and ', $addWhere);
				$this->db->query($sql);
			}
		}
	}

	// 임시 재고 정보 단순 저장
	public function save_tmp_supply($whrParam, $upParam, $target = null, $reserve_policy = null){
		$this->db->where($whrParam);

		foreach($upParam as $key=>$val){
			$upParam[$key] = $val;
		}

		$this->db->update('fm_goods_supply_tmp', $upParam);
	}

	// 적립금 정책 저장
	public function save_goods_policy($goods_seq, $reserve_policy){
		$this->db->where(array('goods_seq' => $goods_seq));
		$this->db->update('fm_goods', array('reserve_policy' => $reserve_policy));
	}

	// 동일 옵션에 동일한 값으로 적용
	public function save_same_tmp_option($tmp_no, $option_seq, $option_no, $upParam){

		$sql	= "select * from fm_goods_option_tmp where tmp_no = ? "
				. "and option_seq = ? ";
		$query	= $this->db->query($sql, array($tmp_no, $option_seq));
		$opt	= $query->row_array();
		if	($opt['option_seq']){
			foreach($upParam as $fld => $val){
				$u++;
				if	($u > 1)	$addUpdate	.= ", ";
				$addUpdate	.= $fld . "='".$val."' ";
			}
			$sql	= "update fm_goods_option_tmp set " . $addUpdate
					. "where tmp_no = ? and option".$option_no."=? ";
			$this->db->query($sql, array($tmp_no, $opt['option'.$option_no]));
		}
	}

	// option 복사/삭제
	public function save_option_one_row($type, $tmpSeq, $seq){
		if	($type && $seq){
			if		($type == 'add'){
				// option insert
				$sql	= "select * from fm_goods_option_tmp where tmp_no = ? and default_option = 'y' order by option_seq limit 1";
				$query	= $this->db->query($sql, array($tmpSeq));
				$option	= $query->row_array();
				$org_option_seq	= $option['option_seq'];
				if	($option){
					unset($option['option_seq']);
					unset($option['default_option']);
					unset($option['fix_option_seq']);
					unset($option['org_option_seq']);

					//색상옵션일때 값이 없으면 기본 흰색으로 저장합니다. @2016-07-27 ysm 6
					if( strstr($option['newtype'],'color') && !$option['color'] ) $option['color'] = '#fff';

					$this->db->insert('fm_goods_option_tmp', $option);
					$option_seq	= $this->db->insert_id();
				}
				// supply insert
				$sql	= "select * from fm_goods_supply_tmp where tmp_no = ? and option_seq = ? ";
				$query	= $this->db->query($sql, array($tmpSeq, $org_option_seq));
				$supply	= $query->row_array();
				if	($supply){

					unset($supply['supply_seq']);
					$supply['option_seq']				= $option_seq;
					$supply['reservation15']			= 0;
					$supply['reservation25']			= 0;
					$supply['ablestock15']				= 0;
					$supply['stock']					= 0;
					$supply['safe_stock']				= 0;
					$supply['supply_price']				= 0;
					$supply['exchange_rate']			= 0;
					$supply['total_stock']				= 0;
					$supply['total_badstock']			= 0;
					$supply['total_supply_price']		= 0;
					$this->db->insert('fm_goods_supply_tmp', $supply);
				}
			}elseif	($type == 'del'){
				if	($tmpSeq && $seq){
					$sql	= "select * from fm_goods_option_tmp where tmp_no = ? and option_seq = ? ";
					$query	= $this->db->query($sql, array($tmpSeq, $seq));
					$option	= $query->row_array();
					if	($option['default_option'] == 'y'){
						$sql		= "select * from fm_goods_option_tmp where tmp_no = ? order by option_seq limit 1";
						$query		= $this->db->query($sql, array($tmpSeq));
						$tmpoption	= $query->row_array();
						$upParam['default_option']	= 'y';
						$this->db->where(array('tmp_no'=>$tmpSeq,'option_seq'=>$tmpoption['option_seq']));
						$this->db->update('fm_goods_option_tmp', $upParam);
					}
					$this->db->where(array('tmp_no'=>$tmpSeq,'option_seq'=>$seq));
					$this->db->delete('fm_goods_option_tmp');
					$this->db->where(array('tmp_no'=>$tmpSeq,'option_seq'=>$seq));
					$this->db->delete('fm_goods_supply_tmp');
					$option_seq	= $seq;
				}
			}
		}

		return $option_seq;
	}

	/* 우측 퀵메뉴 추천상품 가져오기 */
	public function get_recommend_goods_count(){
		$query = "SELECT count(*) as cnt FROM fm_design_recommend_item ORDER BY recommend_item_seq ASC";
		$query = $this->db->query($query);
		$cnt = 0;
		foreach ($query->result() as $row) {
			$cnt = $row->cnt;
		}
		return $cnt;
	}

	/* 우측 퀵메뉴 추천상품 가져오기 */
	public function get_recommend_goods_list($page,$limit,$admin=""){

		if ($admin){
			$limit = '';
		} else {
			if (!$page) $page = 1;
			$start = ($page-1)*$limit;
			$limit = "LIMIT {$start} , {$limit}";
		}

		$query = "SELECT goods_seq FROM fm_design_recommend_item ORDER BY recommend_item_seq ASC {$limit}";
		$query = $this->db->query($query);
		$data = array();
		foreach ($query->result() as $row) {
			$data[] = $row->goods_seq;
		}
		return $data;
	}

	/* 우측 퀵메뉴 추천상품목록 반환 */
	public function get_recommend_item($data){
		$display_item = array();
		$goods_seqs = join(',',$data);
		if (!$goods_seqs) return;

		$this->load->library('sale');
		$cfg_reserve	= ($this->reserves) ? $this->reserves : config_load('reserve');

		//----> sale library 적용
		$applypage						= 'lately_scroll';
		$param['cal_type']				= 'list';
		$param['reserve_cfg']			= $cfg_reserve;
		$param['member_seq']			= $this->userInfo['member_seq'];
		$param['group_seq']				= $this->userInfo['group_seq'];
		$this->sale->set_init($param);
		$this->sale->preload_set_config($applypage);
		//<---- sale library 적용

		$query = $this->db->query("
		select
			g.goods_seq,
			g.goods_name,
			g.sale_seq,
			(select image from fm_goods_image where goods_seq=g.goods_seq and cut_number=1 and image_type='thumbScroll' limit 1) as image,
			o.price,
			o.consumer_price,
			g.display_terms,
			g.display_terms_text,
			g.display_terms_color,
			display_terms_begin,
			display_terms_end
		from
			fm_design_recommend_item r
			inner join fm_goods g on (g.goods_seq=r.goods_seq and g.goods_type = 'goods')
			left join fm_goods_option o on (o.goods_seq=g.goods_seq and o.default_option ='y')
		where r.goods_seq in ( ".$goods_seqs." ) order by r.recommend_item_seq asc");

		foreach ($query->result_array() as $data) {

			// 해당 상품의 전체 카테고리
			$category	= array();
			$tmp		= $this->goodsmodel->get_goods_category($data['goods_seq']);
			foreach($tmp as $row)	$category[]		= $row['category_code'];

			//----> sale library 적용
			unset($param, $sales);
			$param['option_type']				= 'option';
			$param['consumer_price']			= $data['consumer_price'];
			$param['price']						= $data['price'];
			$param['total_price']				= $data['price'];
			$param['ea']						= 1;
			$param['goods_ea']					= 1;
			$param['category_code']				= $category;
			$param['goods_seq']					= $data['goods_seq'];
			$param['goods']						= $data;
			$this->sale->set_init($param);
			$sales								= $this->sale->calculate_sale_price($applypage);
			$data['sale_price']					= $sales['result_price'];
			$this->sale->reset_init();
			//<---- sale library 적용

			//예약 상품의 경우 문구를 넣어준다 2016-11-07
			$data['goods_name']					= get_goods_pre_name($data,true);

			$display_item[] = $data;
		}
		return $display_item;
	}

	/* 적립금 설정 별 구매 시 적립금액 제한 조건 B*/
	public function get_reserve_standard_pay($standard_price, $ea, $tot_real_price, $use_emoney) {
		$give_reserve = get_cutting_price(((($standard_price*$ea)/$tot_real_price)*$use_emoney)/$ea);
		return $give_reserve;
	}

	/* 적립금 설정 별 구매 시 적립금액 제한 조건 C */
	public function get_reserve_limit($tot_reserve_one, $ea, $appointed_reserve, $use_emoney) {
		$reserve_subtract = $appointed_reserve - $use_emoney;
		$give_reserve = get_cutting_price((($tot_reserve_one / $appointed_reserve)*$use_emoney)/$ea);
		return $give_reserve;
	}

	/* 티켓번호 갯수 가져오기 */
	public function get_count_coupon_serial($goods_seq) {
		$query = "select count(*) stock from fm_goods_coupon_serial where goods_seq=? and (export_code is null or export_code='')";

		$query = $this->db->query($query,$goods_seq);

		list($coupon_data) = $query->result_array();

		$couopn_stock = $coupon_data['stock'];
		return $couopn_stock;
	}


	// 상품 상세 노출용 데이터 추출
	public function get_goods_view($no, $no_related = false, $no_bigdata = false)
	{
		$this->load->model('categorymodel');
		$this->load->model('brandmodel');
		$this->load->model('membermodel');
		$this->load->model('wishmodel');
		$this->load->model('videofiles');
		$this->load->model('providermodel');
		$this->load->helper('order');
		$this->load->library('sale');
		$this->load->model('giftmodel');
		$this->load->model('bigdatamodel');

		$this->reserves = $this->reserves ?: config_load('reserve');
		$cfg_reserve    = $this->reserves;
		$cfg_order      = config_load('order');

		//
		$goods = $this->get_goods($no);

		$goods['string_price']     = get_string_price($goods);
		$goods['string_price_use'] = 0;
		if ($goods['string_price'] != '') {
			$goods['string_price_use'] = 1;
		}
		$goods['string_button']     = get_string_button($goods);
		$goods['string_button_use'] = 0;
		if ($goods['string_button'] != '') {
			$goods['string_button_use'] = 1;
		}

		$runout = true;
		$goods['title'] = strip_tags($goods['goods_name']);
		$videosc = [
			'tmpcode'    => $goods['videotmpcode'],
			'upkind'     => 'goods',
			'type'       => 'contents',
			'viewer_use' => 'Y',
			'orderby'    => 'sort ',
			'sort'       => 'asc, seq desc ',
		];
		$alerts           = [];
		$goodsStatusImage = [];
		$now_date         = date('Y-m-d');

		if ($goods['goods_type'] == 'gift') {
			//해당상품이 존재하지 않습니다.
			return [
				'status'  => 'error',
				'errType' => 'echo',
				'msg'     => '<script>alert("'.getAlert('gv022').'");top.location.href="/main";</script>'
			];
		}

		if (! isset($goods['goods_seq'])) {
			//해당상품이 존재하지 않습니다.
			return [
				'status'  => 'error',
				'errType' => 'back',
				'msg'     => getAlert('gv023')
			];
		}

		// 예약상품 정의 :: 2016-11-14 lwh
		if ($goods['display_terms'] == 'AUTO' && ($goods['display_terms_begin'] <= $now_date && $goods['display_terms_end'] >= $now_date) && $goods['display_terms_type'] == 'LAYAWAY') {
			$goods['reserve_ship_txt']  = $goods['possible_shipping_date'] . ' ' .$goods['possible_shipping_text'];
			$goods['reserve_ship_flag'] = true;
		} else {
			$goods['reserve_ship_flag'] = false;
		}

		//
		if ($goods['video_use'] == 'Y') {
			$video_size = explode('X' , $goods['video_size']);
			$goods['video_size0'] = $video_size[0];
			$goods['video_size1'] = $video_size[1];

			$video_size_mobile = explode('X', $goods['video_size_mobile']);
			$goods['video_size_mobile0'] = $video_size_mobile[0];
			$goods['video_size_mobile1'] = $video_size_mobile[1];

			$goods['video_view'] = 'n';
			//상품 이미지 영역 노출 동영상
			if ($this->session->userdata('setMode') == 'mobile' && $goods['file_key_i']) {//모바일이면서 file_key_i 값이 있는 경우
				$goods['uccdomain_thumbnail'] = uccdomain('thumbnail',$goods['file_key_i']);
				$goods['uccdomain_fileswf']   = uccdomain('fileswf',$goods['file_key_i']);
				$goods['uccdomain_fileurl']   = uccdomain('fileurl',$goods['file_key_i']);
				if ($goods['video_view_type'] != 2) {
					$goods['video_view'] = 'y';
				}
				$video_size0 = $goods['video_size_mobile0'];
				$video_size1 = $goods['video_size_mobile1'];
			} elseif (uccdomain('thumbnail',$goods['file_key_w']) && $goods['file_key_w']) {
				$goods['uccdomain_thumbnail'] = uccdomain('thumbnail',$goods['file_key_w']);
				$goods['uccdomain_fileswf']   = uccdomain('fileswf',$goods['file_key_w']);
				$goods['uccdomain_fileurl']   = uccdomain('fileurl',$goods['file_key_w']);
				if ($goods['video_view_type'] != 2) {
					$goods['video_view'] = 'y';
				}
				$video_size0 = $goods['video_size0'];
				$video_size1 = $goods['video_size1'];
			}
		} else {
			unset($goods['file_key_w'],$goods['file_key_i'],$goods['video_size']);
			$goods['video_use']	= 'N';
		}

		//동영상리스트
		$goodsvideofiles = $this->videofiles->videofiles_list_all($videosc);
		if ($goodsvideofiles['result']) {
			foreach($goodsvideofiles['result']as $k => $data){
				//동영상
				if ($this->session->userdata('setMode')=='mobile' && $data['file_key_i']) {//모바일이면서 file_key_i 값이 있는 경우
					$goodsvideofiles['result'][$k]['uccdomain_thumbnail'] = uccdomain('thumbnail',$data['file_key_i']);
					$goodsvideofiles['result'][$k]['uccdomain_fileswf']   = uccdomain('fileswf',$data['file_key_i']);
					$goodsvideofiles['result'][$k]['uccdomain_fileurl']   = uccdomain('fileurl',$data['file_key_i']);
				} elseif (uccdomain('thumbnail',$data['file_key_w']) && $data['file_key_w']) {
					$goodsvideofiles['result'][$k]['uccdomain_thumbnail'] = uccdomain('thumbnail',$data['file_key_w']);
					$goodsvideofiles['result'][$k]['uccdomain_fileswf']   = uccdomain('fileswf',$data['file_key_w']);
					$goodsvideofiles['result'][$k]['uccdomain_fileurl']   = uccdomain('fileurl',$data['file_key_w']);
				}
			}
		}

		$i	= 0;
		$goods['sub_info_desc'] = json_decode($goods['sub_info_desc']);
		foreach ($goods['sub_info_desc'] as $key => $value) {
			if ($key != '_empty_' && $key != '') {
				$goods_sub['subInfo'][$i]["title"] = $key;
				$goods_sub['subInfo'][$i]["desc"]  = $value;
				$i++;
			}
		}
		$goods['sub_info_desc'] = $goods_sub;

		// 입점사 로그인 정보
		$provider_data = $this->session->userdata['provider'];

		//노출관리 자동 설정의 경우 해당 날짜에 포함이 되면 무조건 노출 2016-11-07
		if ($goods['display_terms'] == 'AUTO' && ($goods['display_terms_begin'] <= $now_date && $goods['display_terms_end'] >= $now_date)) {
			$goods['goods_name'] = get_goods_pre_name($goods,'',true);
			$goods['goods_view'] = 'look';
		}

		// 페이지 로딩 후 경고메시지
		if ($goods['goods_view'] == 'notLook') {
			//미노출 상품 입니다.
			$msg = getAlert('gv020');
			if (empty($this->managerInfo['manager_seq']) && $provider_data['provider_seq'] != $goods['provider_seq']) {
				return [
					'status'  => 'error',
					'errType' => 'back',
					'msg'     => $msg
				];
			} else {
				$alerts[] = $msg;
			}
		}

		if ($goods['provider_status'] != 1) {
			//미승인 상품 입니다.
			$msg = getAlert('gv021');
			if (empty($this->managerInfo['manager_seq']) && $provider_data['provider_seq'] != $goods['provider_seq']) {
				return [
					'status'  => 'error',
					'errType' => 'back',
					'msg'     => $msg
				];
			} else {
				$alerts[]		= $msg;
			}

			if ($goods['goods_status'] != 'unsold' ) {
				$goods['goods_status'] = 'unsold';
			}
		}

		// 상품상태별 아이콘
		$tmp = code_load('goodsStatusImage');
		foreach ($tmp as $row) {
			$goodsStatusImage[$row['codecd']] = $row['value'];
		}

		// 회원정보 가져오기
		if ($this->userInfo) {
			$data_member = $this->membermodel->get_member_data($this->userInfo['member_seq']);
			if ($data_member['business_seq'] > 0) {
				$user_type = 'business';
				$user_text = '기업';
			} else {
				$user_type = 'default';
				$user_text = '개인';
			}
		}

		// 등급 체크
		$allow_category_connect = $this->categorymodel->get_category_group_for_goods($no,'all');
		$allow_group_seq        = $allow_category_connect['user_group'];
		$allow_user_type        = $allow_category_connect['user_type'];

		if (($allow_group_seq || $allow_user_type) && !$this->userInfo) {
			//회원 전용 상품 입니다
			$msg = getAlert('gv024');
			if (empty($this->managerInfo['manager_seq'])  && $provider_data['provider_seq'] != $goods['provider_seq']) {
				return [
					'status'  => 'error',
					'errType' => 'back',
					'msg'     => $msg
				];
			} else {
				$alerts[] = $msg;
			}
		}

		if ($allow_group_seq && !in_array($data_member['group_seq'],$allow_group_seq)) {
			//회원 그룹은 접근 권한이 없습니다.
			$msg = $data_member['group_name'].getAlert('gv025');
			if (empty($this->managerInfo['manager_seq'])  && $provider_data['provider_seq'] != $goods['provider_seq']) {
				return [
					'status'  => 'error',
					'errType' => 'back',
					'msg'     => $msg
				];
			} else {
				$alerts[] = $msg;
			}
		}

		if( $allow_user_type && !in_array($user_type,$allow_user_type)) {
			//회원은 접근 권한이 없습니다.
			$msg = $user_text.getAlert('gv026');
			if (empty($this->managerInfo['manager_seq'])  && $provider_data['provider_seq'] != $goods['provider_seq']) {
				return [
					'status'  => 'error',
					'errType' => 'back',
					'msg'     => $msg
				];
			} else {
				$alerts[] = $msg;
			}
		}

		// 브랜드 등급체크
		$allow_brand_connect = $this->brandmodel->get_brand_group_for_goods($no, 'all');

		$allow_group_seq = $allow_brand_connect['user_group'];
		$allow_user_type = $allow_brand_connect['user_type'];

		if (($allow_group_seq || $allow_user_type) && !$this->userInfo) {
			//회원 전용 상품 입니다
			$msg = getAlert('gv024');
			if (empty($this->managerInfo['manager_seq'])  && $provider_data['provider_seq'] != $goods['provider_seq']) {
				return [
					'status'  => 'error',
					'errType' => 'back',
					'msg'     => $msg
				];
			} else {
				$alerts[] = $msg;
			}
		}

		if ($allow_group_seq && !in_array($data_member['group_seq'],$allow_group_seq)) {
			//회원 그룹은 접근 권한이 없습니다.
			$msg = $data_member['group_name'].getAlert('gv025');
			if (empty($this->managerInfo['manager_seq']) && $provider_data['provider_seq'] != $goods['provider_seq']) {
				return [
					'status'  => 'error',
					'errType' => 'back',
					'msg'     => $msg
				];
			} else {
				$alerts[] = $msg;
			}
		}

		if ($allow_user_type && !in_array($user_type,$allow_user_type)) {
			//회원은 접근 권한이 없습니다.
			$msg = $user_text.getAlert('gv026');
			if (empty($this->managerInfo['manager_seq']) && $provider_data['provider_seq'] != $goods['provider_seq']) {
				return [
					'status'  => 'error',
					'errType' => 'back',
					'msg'     => $msg
				];
			} else {
				$alerts[] = $msg;
			}
		}

		//
		$sessionMember = $data_member;

		/* 쇼핑몰 타이틀 */
		//구스킨 사용중일때 타이틀에 상품명 노출안되는 문제로 주석처리함. #8961
		//if($this->config_basic['shopGoodsTitleTag'] && $goods['title']){
			$title		= str_replace("{상품명}",$goods['title'],$this->config_basic['shopGoodsTitleTag']);
			$shopTitle	= $title;
		//}

		$images    = $this->get_goods_image($no);
		$additions = $this->get_goods_addition($no);
		if ($images[1]['view']['image']) {
			list($imageWidth,$imageHeight) = @getimagesize(ROOTPATH.$images[1]['view']['image']);
			$imageWidth  = $imageWidth ?: 415;
			$imageHeight = $imageHeight ?: 554;
		}

		## 상품이미지 영역에 동영상 포함
		if ($goods['video_view'] == 'y') {
			$imagesVideo['view'] = [
				'goods_seq'   => $no,
				'cut_number'  => 1,
				'image_type'  => 'video',
				'image'       => $goods['uccdomain_fileurl'] . '&g=tag&width=' . $video_size0 . '&height=' . $video_size1,
				'match_color' => '',
				'label'       => '',
			];
			$imagesVideo['thumbView'] = [
				'goods_seq'   => $no,
				'cut_number'  => 1,
				'image_type'  => 'video',
				'image'       => '/data/skin/' . $this->skin . '/images/common/icon_video_100.jpg',
				'match_color' => '',
				'label'       => '',
			];
			$k=1;
			if ($goods['video_position'] == "first") {
				$imageloop[$k] = $imagesVideo; $k++;
			}
			foreach ($images as $imagesItem) {
				$imageloop[$k] = $imagesItem;
				$k++;
			}
			if ($goods['video_position'] == "last") {
				$imageloop[$k] = $imagesVideo;
			}
			$images = $imageloop;
		}

		//추가정보의 모델명추출
		foreach ($additions as $data_additions) {
			if (strstr($data_additions['type'],"goodsaddinfo_")) {
				$data_additions['contents_code'] = $data_additions['contents'];
				$data_additions['contents']      = $data_additions['contents_title'];
			}
			$newadditions[] = $data_additions;
		}
		$additions  = $newadditions;    //재정의
		$options    = $this->get_goods_option($no,array('option_view'=>'Y'));
		$suboptions = $this->get_goods_suboption($no,array('option_view'=>'Y'));
		$inputs     = $this->get_goods_input($no);
		$icons      = $this->get_goods_icon($no);

		$provider = null;
		if ($goods['provider_seq']) {
			$provider = $this->providermodel->get_provider($goods['provider_seq']);
			if ($this->userInfo['member_seq']) {
				$this->load->model('myminishopmodel');
				$sMemberSeq = $this->userInfo['member_seq'];
				$chk        = $this->myminishopmodel->chk_myminishop($sMemberSeq, $provider['provider_seq']);
				$provider['thisshop'] = $chk;
			}

			if (! $this->managerInfo && $provider['provider_status']!='Y') {
				//접근권한이 없습니다.
				return [
					'status'  => 'error',
					'errType' => 'redirect',
					'msg'     => getAlert('gv027'),
					'url'     => '/main/index',
				];
			}
		}

		// 카테고리정보
		$tmparr2 = [];
		$categorys = $this->get_goods_category($goods['goods_seq']);
		foreach ($categorys as $key => $val) {
			if ($val['link'] == 1) {
				$goods['category_code'] = $this->categorymodel->split_category($val['category_code']);
				$category_code=$goods['category_code'][count($goods['category_code'])-1];
			} else {
				if ($goods['category_code']) {
					$goods['sub_category_code'] = $this->categorymodel->split_category($val['category_code']);
				}
			}

			$tmparr = $this->categorymodel->split_category($val['category_code']);
			foreach ($tmparr as $cate) {
				$tmparr2[] = $cate;
			}
		}
		if ($tmparr2) {
			$tmparr2 = array_values(array_unique($tmparr2));
			$goods['r_category'] = $tmparr2;
		}
		if ($goods['category_code']) {
			foreach ($goods['category_code'] as $code) {
				$goods['category'][] = $this->categorymodel->one_category_name($code);
			}
		}

		// 브랜드 정보
		$brands = $this->get_goods_brand($goods['goods_seq']);
		if ($brands) {
			foreach ($brands as $key => $data) {
				if ($data['link'] == 1) {
					$goods['brand_code']      = $this->brandmodel->split_brand($data['category_code']);
					$goods['brand_title_eng'] = $data['title_eng'];
					$goods['brand_title']     = $data['title'];
				}
			}
		}
		if ($goods['brand_code']) {
			foreach ($goods['brand_code'] as $code) {
				$goods['brand'][] = $this->brandmodel->one_brand_name($code);
				$last_code  = $code;
				$last_brand = $this->brandmodel->one_brand_name($code);
			}
		}
		$view_brand = '<a href="./brand?code=' . $last_code . '">' . $last_brand . '</a>';
		if ($last_code) {
			$brandInfo = $this->brandmodel->get_brand_info($last_code);
		}

		/* 카테고리/브랜드의 회원등급/회원유형 접근제한 */
		if (! $this->managerInfo) { //관리자제외
			$this->get_goods_permcheck($goods);
		}

		// 티켓상품 위치서비스 사용여부 :: 2014-04-01 lwh
		if ($this->mobileMode) {
			$mapview_use	= $goods['m_mapview'];
		} else {
			$mapview_use	= $goods['pc_mapview'];
		}

		//----> sale library 적용
		$applypage = 'option';
		$param = [
			'cal_type'    => 'list',
			'reserve_cfg' => $cfg_reserve,
			'member_seq'  => $this->userInfo['member_seq'],
			'group_seq'   => $this->userInfo['group_seq'],
		];
		$this->sale->set_init($param);
		$this->sale->preload_set_config($applypage);
		//<---- sale library 적용
		if ($options) {
			foreach($options as $k => $opt){
				## 연결상품 체크
				if ($goods['package_yn'] == 'y') {
					for ($i_chk = 1; $i_chk <= 5; $i_chk++) {
						if ($opt['package_option_seq' . $i_chk]) {
							$params_chk = [
								'mode'               => 'option',
								'goods_seq'          => $opt['goods_seq'],
								'option_seq'         => $opt['option_seq'],
								'package_option_seq' => $opt['package_option_seq'.$i_chk],
								'package_option'     => $opt['package_option'.$i_chk],
								'no'                 => $i_chk,
							];
							if (! check_package_option($params_chk)) {
								$opt['package_error_type'] = 'option';
								$opt['package_error']      = true;
							}
						}
					}
				}
				if ($opt['package_error']) {
					unset($options[$k]);
					continue;
				}

				// 대표옵션
				if ($opt['default_option'] == 'y') {
					$defOption	= $opt;
				}

				if ($opt['price']) {
					//----> sale library 적용 ( 정가기준 목록에서는 sale_price를 넘기지 않음 )
					unset($param, $sales);
					$param = [
						'option_type'    => 'option',
						'consumer_price' => $opt['consumer_price'],
						'price'          => $opt['price'],
						'ea'             => 1,
						'goods_ea'       => 1,
						'category_code'  => $goods['r_category'],
						'brand_code'     => $goods['brand_code'],
						'goods_seq'      => $goods['goods_seq'],
						'goods'          => $goods,
					];
					$this->sale->set_init($param);
					$sales = $this->sale->calculate_sale_price($applypage);

					$opt['org_price'] = ($opt['consumer_price']) ? $opt['consumer_price'] : $opt['org_price'];
					$opt['sales']     = $sales;

					// sale_price가 없을 시 여기서 event까지 계산함
					if (! $opt['event'] && $this->sale->cfgs['event']) {
						$opt['event']    = $this->sale->cfgs['event'];
						$opt['eventEnd'] = $sales['eventEnd'];
					}

					$this->sale->reset_init();
					//<---- sale library 적용
				}

				//통합세팅이면서 가용재고 사용시 @2015-11-13
				if (!$goods['runout_policy'] && $cfg_order['runout'] == 'ableStock') {
					$opt['stock'] = $opt['rstock'];
				}

				// 재고 체크
				$opt['chk_stock'] = check_stock_option_list($goods, $cfg_order, $opt['stock'], $opt['reserve15'], $opt['reserve25'], 0, 'view');
				if ($opt['chk_stock']) {
					$runout = false;
				}

				$opt['opspecial_location'] = get_goods_options_print_array($opt);



			if($data['newtype']) {
				$data['infomation'] = ($data['infomation'])?$data['infomation'].'<br/>'.get_goods_special_option_print($data):get_goods_special_option_print($data);
			}

				$options[$k] = $opt;
			}
		}

		## 옵션 순번 정렬
		if ($options) {
			foreach ($options as $data_option) {
				$sort_options[] = $data_option;
			}
		}
		if ($sort_options) {
			$options = $sort_options;
		}

		/**
		 * 티켓상품 지도 표기 (2개 이상 옵션 시 옵션개수만큼 지점 반복노출 개선)
		 */
		if($mapview_use=='Y'){
			// 옵션 중 주소 옵션 위치 찾기
			$address_key = array_search("address",$defOption['divide_newtype']);
			if($address_key !== false) {
				$map_key = 0;
				foreach($options as $opt) {
				
					// 주소 옵션 명
					$address_option_value = $opt['option'.($address_key+1)];
					// $mapNameArr 중복체크
					if(!in_array($address_option_value,$mapNameArr)) {
						$mapArr[$map_key] = array(
							'o_seq' => $opt['option_seq'],
							'option' => $opt['option'.$opt['opspecial_location']['address']],
							'address' => $opt['address']. " " .$opt['addressdetail'],
							'address_street' => $opt['address_street'],
							'biztel' => $opt['biztel'],
						);
						$map_key += 1;
						$mapNameArr[] = $address_option_value;
					}
				}
			}
			$assign_mapArr	= $mapArr;
		}

		// 재고가 없을 시 품절로 상태 표기
		if ($goods['goods_status'] == 'normal' &&  $runout) {
			$goods['goods_status'] = 'runout';
		}

		unset($opt);
		$sub_runout = false;
		$suboption_required = false;
		if ($suboptions) {
			foreach ($suboptions as $key => $tmp) {
				foreach ($tmp as $k => $opt) {
					## 연결상품 체크
					if ($goods['package_yn_suboption'] == 'y') {
						if ($opt['package_option_seq1']) {
							$params_chk = [
								'mode'               => 'suboption',
								'goods_seq'          => $opt['goods_seq'],
								'option_seq'         => $opt['suboption_seq'],
								'package_option_seq' => $opt['package_option_seq1'],
								'package_option'     => $opt['package_option1'],
								'no'                 => 1,
							];
							if (!check_package_option($params_chk)) {
								$opt['package_error_type'] = 'suboption';
								$opt['package_error']      = true;
							}
						}
					}
					if ($opt['package_error']) {
						unset($suboptions[$key][$k]);
						continue;
					}

					$opt['chk_stock'] = check_stock_suboption($goods['goods_seq'],$opt['suboption_title'],$opt['suboption'],0,$cfg_order,'view');

					if ( $opt['chk_stock']) {
						$sub_runout = true;
					}

					if ($opt['sub_required'] == 'y') {
						$suboption_required = true;
					}
					// 회원등급할인
					//----> sale library 적용
					unset($param, $sales);
					$param = [
						'option_type'    => 'suboption',
						'sub_sale'       => $opt['sub_sale'],
						'consumer_price' => $opt['consumer_price'],
						'price'          => $opt['price'],
						'total_price'    => $opt['price'],
						'ea'             => 1,
						'category_code'  => $goods['r_category'],
						'brand_code'     => $goods['brand_code'],
						'goods_seq'      => $goods['goods_seq'],
						'goods'          => $goods,
					];
					$this->sale->set_init($param);
					$sales = $this->sale->calculate_sale_price($applypage);
					$opt['price'] = $sales['result_price'];
					$this->sale->reset_init();
					unset($sales);
					//<---- sale library 적용

					$suboptions[$key][$k] = $opt;
				}
			}
		}

		//----> sale library 적용 ( 대표옵션에 대해서 별도 적용 )
		unset($param, $sales);
		$param = [
			'cal_type'       => 'each',
			'option_type'    => 'option',
			'reserve_cfg'    => $cfg_reserve,
			'member_seq'     => $this->userInfo['member_seq'],
			'group_seq'      => $this->userInfo['group_seq'],
			'consumer_price' => $defOption['consumer_price'],
			'price'          => $defOption['price'],
			'total_price'    => $defOption['price'],
			'ea'             => 1,
			'goods_ea'       => 1,
			'category_code'  => $goods['r_category'],
			'brand_code'     => $goods['brand_code'],
			'goods_seq'      => $goods['goods_seq'],
			'goods'          => $goods,
		];
		$this->sale->set_init($param);
		$sales = $this->sale->calculate_sale_price('view');
		$this->goodsmodelsales = $sales;

		$goods['sales'] = $sales;

		$goods['org_price'] = ($defOption['consumer_price']!='0.00') ? $defOption['consumer_price'] : $defOption['price'];
		$goods['price']     = $sales['result_price'];
		// 기존 스킨 유지를 위해 추가.
		$goods['consumer_price']    = $defOption['consumer_price'];
		$goods['basic_sale']        = $sales['sale_list']['basic'];
		$goods['event_sale_unit']   = $sales['sale_list']['event'];
		$goods['referer_sale_unit'] = $sales['sale_list']['referer'];
		$goods['mobile_sale_unit']  = $sales['sale_list']['mobile'];
		$goods['fblike_sale_unit']  = $sales['sale_list']['like'];
		$goods['member_sale_unit']  = $sales['sale_list']['member'];
		$goods['event']             = $this->sale->cfgs['event'];
		$goods['member_group']      = $this->sale->cfgs['member'] ?: $this->sale->cfgs['no_member'];
		$goods['sum_sale_price']    = $sales['total_sale_price'];
		$goods['sale_price']        = $sales['result_price'];
		$goods['sale_rate']         = $sales['sale_per'];
		$goods['referer_sale']      = $this->sale->refererSales;
		$goods['mobile_sale']       = $this->sale->mobileSales;
		$goods['like_sale']         = $this->sale->get_fblikesale_config_list();
		$goods['group_benifits']    = $this->sale->get_groupsale_config();
		$goods['eventEnd']          = $sales['eventEnd'];
		$goods['org_basic_price']   = $defOption['price'];
		$goods['point']             = $this->goodsmodel->get_point_with_policy($sales['result_price']) + $sales['tot_point'];
		$goods['reserve']           = $this->goodsmodel->get_reserve_with_policy($goods['reserve_policy'],$sales['result_price'],$cfg_reserve['default_reserve_percent'],$defOption['reserve_rate'],$defOption['reserve_unit'],$defOption['reserve']) + $sales['tot_reserve'];

		$this->sale->reset_init();
		//<---- sale library 적용

		// 필수인 추가옵션이 있는 경우 무조건 쌍으로 묶임 2019-04-22 by hyem
		if ($suboption_required) {
			$goods['suboption_layout_group'] = 'group';
		}

		// 필수옵션 미사용일 때 입력옵션은 항상 옵션선택 영역에 노출 2020-01-15 by hyem
		if( $goods['option_use'] != '1' && $goods['inputoption_layout_position'] == "down") {
			$goods['inputoption_layout_position'] = "up";
		}

		if (isset($options[0]['option_divide_title'])) {
			$goods['option_divide_title'] = $options[0]['option_divide_title'];
		}
		if (isset($options[0]['divide_newtype'])) {
			$goods['divide_newtype'] = $options[0]['divide_newtype'];
		}

		// 배송정보 가져오기 :: START 2016-07-08 lwh
		$this->load->model('shippingmodel');

		// 배송그룹 검증 :: 2017-01-12 lwh
		if (! $goods['shipping_group_seq']) {
			$query = $this->db->select('shipping_group_seq')
				->from('fm_shipping_grouping')
				->where('shipping_provider_seq', $goods['provider_seq'])
				->where('default_yn', 'Y')
				->limit(1)
				->get();
			$res = $query->row_array();
			if (! $res['shipping_group_seq']) {
				openDialogAlert('배송할 수 없는 상품 입니다..',400,140,'parent','parent.history.back(-1);');
				exit;
			} else {
				$goods['shipping_group_seq'] = $res['shipping_group_seq'];
			}
		}
		// 요약 정보 추출
		$ship_summary = $this->shippingmodel->get_shipping_group_summary($goods['shipping_group_seq']);
		// 해외 정보 추출
		if ($ship_summary['gl_shipping_yn'] == 'Y') {
			$ship_gl_arr = $this->shippingmodel->get_gl_shipping($goods['shipping_group_seq']);
			if (! $ship_gl_arr) {
				$ship_gl_arr = $this->shippingmodel->get_gl_shipping();
			}
			$ship_gl_list = $this->shippingmodel->split_nation_str($ship_gl_arr);
		}
		// set 정보 추출
		$ship_set_arr = $this->shippingmodel->get_shipping_set($goods['shipping_group_seq']);
		foreach ($ship_set_arr as $k => $val) {
			// 희망배송일 - 가장 빠른 일자 검색
			if ($val['hop_use'] == 'Y') {
				$val['hop_date'] = $this->shippingmodel->get_hop_date($val);

				// 희망배송일 문구 추출 :: 2017-01-11 lwh 추가.
				if (date('Y-m-d') == $val['hop_date']) {
					$today_possible = '(오늘)';
				} else {
					$today_possible = '';
				}

				// 필수여부 문구 추출
				if ($val['hopeday_required'] == 'Y') {
					$required_txt	= '(필수)';
				} else {
					$required_txt	= '(선택)';
				}

				$val['delivery_hop_input']	= date('m월 d일',strtotime($val['hop_date'])) . $today_possible . '부터 배송가능<span class="desc">' . $required_txt . '</span>';
			}

			//#30402 2019-03-12 ycg 전자결제 설정에서 네이버페이 미사용인 경우 npay사용 조건을 만족해도 N으로 처리
			$navercheckout = config_load('navercheckout');
			if ($navercheckout['use'] == 'n') {
				$val['npay_order_possible'] = 'N';
			}

			$talkbuy = config_load('talkbuy');
			if ($talkbuy['use'] == 'n') {
				$val['talkbuy_order_possible'] = 'N';
			}

			// 언어별 자동 문구 변경 :: 2017-01-11 lwh
			if ($this->config_system['language'] && $this->config_system['language'] != 'KR' && $val['delivery_std_type'] != 'N') {
				$lan_str = strtolower($this->config_system['language']);
				$val['delivery_std_input'] = ($val['delivery_std_input_'.$lan_str]) ? $val['delivery_std_input_'.$lan_str] : $val['delivery_std_input'];
				$val['delivery_add_input'] = ($val['delivery_add_input_'.$lan_str]) ? $val['delivery_add_input_'.$lan_str] : $val['delivery_add_input'];
			}

			$ship_set[$val['shipping_set_seq']] = $val;
			$ship_set[$val['shipping_set_seq']]['prepay_txt'] = $this->shippingmodel->prepay_info_txt[$val['prepay_info']];

			// 구) 배송정보에 신) 배송설정 매칭 :: START 2016-10-24 lwh
			$method_key = $val['shipping_set_code'];
			$method_seq = $val['shipping_set_seq'];

			// 예외상황 처리 - 매장 수령인 경우 매장리스트 추출
			if ($method_key == 'direct_store') {
				$store_info = $this->shippingmodel->get_shipping_join_store($val['shipping_set_seq']);
				foreach ($store_info as $s => $store) {
					// 창고 연결의 경우 정보 추출
					$store_list['shipping_store_use'] = 'Y';
					if ($store['store_type'] == 'scm') {
						$tmp_sc['wh_seq'] = $store['shipping_address_seq'];
						$scm_return = $this->shippingmodel->shipping_warehouse_list($tmp_sc,'limit');
						$store = array_merge($store, $scm_return);
						$store_list['shipping_store_name'] = $store['address_name'];

						// 창고 사용여부 검색
						if (! $this->scm_cfg) {
							$this->scm_cfg = config_load('scm');
						}
						$use_wh_seqs = array_keys($this->scm_cfg['use_warehouse']);
						// 노출여부 결정
						if (array_search($tmp_sc['wh_seq'], $use_wh_seqs) === false) {
							$store_list['shipping_store_use'] = 'N';
						}
					} else {
						$store_list['shipping_store_name'] = $store['shipping_store_name'];
					}
					$store_list['shipping_address_seq']      = $store['shipping_address_seq'];
					$store_list['shipping_address_category'] = $store['address_category'];
					$store_list['store_phone']               = $store['shipping_phone'];
					$store_list['shipping_address_nation']   = ($store['address_nation']=='korea') ? 'N' : 'Y';
					if ($store['address_zipcode']) {
						if ($store['address_nation']=='korea') {
							$tmpAddress = ($store['address_type']=='street') ? $store['address_street'] : $store['address'];
							$store_list['shipping_address_full'] = '(' . $store['address_zipcode'] . ') ' . $tmpAddress . ' ' . $store['address_detail'];
						} else {
							$store_list['shipping_address_full'] = '(' . $store['address_zipcode'] . ') ' . $store['international_country'] . ' ' . $store['international_town_city'] . ' ' . $store['international_county'] . ' ' . $store['international_address'];
						}
					}
					$store_list['store_supply_set']       = $store['store_supply_set'];
					$store_list['store_supply_set_view']  = $store['store_supply_set_view'];
					$store_list['store_supply_set_order'] = $store['store_supply_set_order'];
					$store_list['store_scm_type']         = $store['store_scm_type'];

					$params['store_type'][$s]    = $store['store_type'];
					$params['store_scm_seq'][$s] = $store['store_scm_seq'];

					// 해당 상품 재고 추출 :: 2017-01-05 lwh
					if ($store['store_supply_set'] == 'Y') {
						$this->load->model('scmmodel');
						if ($this->scmmodel->chkScmConfig(true)) {
							$sc['wh_seq']    = $store['store_scm_seq'];
							$sc['goods_seq'] = $goods['goods_seq'];
							$sc['get_type']  = 'wh';
							$wh_res = $this->scmmodel->get_location_stock($sc);
							$wh_stock = $wh_res[0];
							$store_list['store_stock'] = $wh_stock['ea'];
						}
					} else {
						$query = $this->db->select('stock')
							->from('fm_goods_supply')
							->where('goods_seq', $goods['goods_seq'])
							->get();
						$goods_stock	= $query->row_array();
						$store_list['store_stock'] = $goods_stock['stock'];
					}
					// 재고 검증 :: 2017-04-06 lwh
					if ($store_list['store_stock'] < 0 || !$store_list['store_stock']) {
						$store_list['store_stock'] = '0';
					}
					$ship_set[$val['shipping_set_seq']]['store_list'][] = $store_list;
				}
				continue;
			}
			if ($shipping_policy['shipping_method_key'][$method_key]) {
				continue;
			}

			$shipping_policy['shipping_method'][$method_seq] = $val['shipping_set_name'] . '(' . $ship_set[$val['shipping_set_seq']]['prepay_txt'] . ')';

			// 구스킨 배송비 안내 처리
			if ($val['delivery_std_input']) {
				$ship_msg_front = '기본배송비 : ' . $val['delivery_std_input'];
			}
			if ($val['add_use'] == 'Y' && $val['delivery_std_input']) {
				$ship_msg_front .= '<br/>추가배송비 : ' . $val['delivery_add_input'];
			}
			$shipping_policy['summary'][$method_seq] = $ship_msg_front;
			$shipping_policy['shipping_method_key'][$method_key] = $method_key;
			// 구) 배송정보에 신) 배송설정 매칭 :: END
		}
		// 신) 배송정보 가져오기 :: END 2016-07-08 lwh

		// 오늘본 상품 쿠키
		$today_num = 0;
		$today_view = $_COOKIE['today_view'];
		if ($today_view) {
			$today_view = unserialize($today_view, ['allowed_classes' => false]);
		}
		if ($today_view ) {
			foreach($today_view as $v){
				$today_num++;
				if (count($today_view) > 50 && $today_num == 1) {
					continue;
				}
				$data_today_view[] = $v;
			}
		}
		if (! in_array($no , $today_view)) {
			$data_today_view[] = $no;
			//페이지뷰 증가
			$this->goodsmodel->increase_page_view($no);
		}
		if ($data_today_view) {
			$data_today_view = serialize($data_today_view);
		}
		setcookie('today_view',$data_today_view,time()+86400,'/');

		/* 동영상/플래시매직 치환 */
		$goods['contents']        = showdesignEditor($goods['contents']);
		$goods['mobile_contents'] = showdesignEditor($goods['mobile_contents']);
		$goods['common_contents'] = showdesignEditor($goods['common_contents']);
		// 모바일 상세 설명 생성
		if (!$goods['mobile_contents']) {
			$goods['mobile_contents'] = $this->goodsmodel->set_mobile_contents($goods['contents'],$goods['goods_seq']);
		}

		//페이스북회원인경우
		if ($this->__APP_USE__ == 'f') {
			//카테고리추가
			$category_title = [];
			foreach ($categorys as $fbtitlecategorys) {
				$category_title[] = $fbtitlecategorys['title'];
			}
			if (is_array($category_title)) {
				$fbcategory_title = implode(" > ", $category_title);
			}
		}
		// 19mark 이미지
		$markingAdultImg = $this->goodslist->checkingMarkingAdultImg($goods);
		if($images){
			foreach($images as $key => $image){

				if ($markingAdultImg) {
					$images[$key]['view']['image'] = $this->goodslist->adultImg;
				}

				if ($image['view']['image']) {
					$filetypetmp = @getimagesize(ROOTPATH.$image['view']['image']);
					if ($filetypetmp[0] >= 400) {
						$APP_IMG = $image['view']['image'];
						break;
					} else {
						$APP_IMG = $image['large']['image'];
						break;
					}
				} elseif($image['large']['image']) {
					$APP_IMG = $image['large']['image'];
					break;
				}
			}
		}

		// 사은품정보
		$today			= date('Y-m-d');
		$gift_goods[]	= $goods['goods_seq'];
		$gift_categorys	= $goods['r_category'];
		$goods['gift']	= $this->giftmodel->get_gift($today, $goods['price'], $gift_goods, $gift_categorys, $goods['provider_seq'],$goods['shipping_group_seq']);
		// 사은품을 선택할 수 있는 조건(사은품 재고는 필수)
		if (! $goods['gift']['benifits']) {
			unset($goods['gift']);
		}

		// 무이자 할인
		$pg = config_load($this->config_system['pgCompany']);
		if ($pg['nonInterestTerms'] == 'manual') {
			$tmp = code_load($this->config_system['pgCompany'].'CardCompanyCode');
			foreach ($tmp as $company_code) {
				$r_card_company[$company_code['codecd']] = $company_code['value'];
			}
			if ($pg['pcCardCompanyCode']) {
				foreach ($pg['pcCardCompanyCode'] as $key => $code) {
					$goods['nointerest'][] = $r_card_company[$code] . " " . $pg['pcCardCompanyTerms'][$key];
				}
			}
		}
		$this->protocol = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']=='on' ? 'https://' : 'http://';

		// 위시여부 2014-01-10 lwh
		$wish_seq = $this->wishmodel->confirm_wish($_GET['no']);

		// 선물하기 노출여부
		$this->load->library('goodslibrary');
		$goods['present_usable'] = $this->goodslibrary->present_usable($goods);

		// 관련상품 로딩
		if (! $no_related) {
			$this->load->model('goodsdisplay');

			$mobile_flag = false;
			$kind_str    = 'relation';
			$platform    = 'pc';
			if ($this->mobileMode && $this->realMobileSkinVersion > 2) {
				$kind_str = 'relation_mobile';
				$platform = 'mobile';
			}

			// 반응형일 경우 반응형 설정 가져옴
			if ($this->operation_type == 'light') {
				$kind_str = 'relation';
				$platform = 'responsive';
			}

			$display = $this->goodsdisplay->get_display_type($kind_str, $platform);

			// 모바일 페이지에서 모바일 설정 사용 여부 확인 2016-01-05 jhr
			// 운영방식이 heavy 일 경우만 동작해야하므로  get_display_type 함수에 pc 플랫폼 조건 추가 :: 2019-01-02 pjw
			if (! $display || ($kind_str == 'relation_mobile' && $this->mobileMode && $display['m_list_use'] != 'y')) {
				$this->get_goods_relation_display_seq();
				$display = $this->goodsdisplay->get_display_type('relation', 'pc');
				$mobile_flag = true;
			}

			// 상품 디스플레이 데코레이션 설정
			$display['decorations'] = json_decode(base64_decode($display['image_decorations']) , true);

			// 반응형 light 일 경우 추천상품 조건을 light 조건으로 가져옴 :: 2019-01-18 pjw
			if ($this->operation_type == 'light') {
				$display['auto_criteria'] = $goods['relation_criteria_light'];
			} else {
				$display['auto_criteria'] = $goods['relation_criteria'];
			}

			//
			$sc = [
				'limit'      => $this->operation_type == 'light' ? $display['count_r'] : $display['count_w']*$display['count_h'],
				'image_size' => $display['image_size'],
			];

			// light 운영방식 일 경우 무시
			if ($this->operation_type != 'light' && $this->mobileMode  && !$mobile_flag) {
				if ($display['style'] == 'newswipe') {
					$display['count_w'] = $display['count_w_swipe'];
					$display['count_h'] = $display['count_h_swipe'];
					$sc['limit'] = $display['count_max_swipe'];
				} elseif ($display['style'] == 'sizeswipe') {
					$sc['limit'] = 20;
				}
			}

			if ($goods['relation_type'] == 'AUTO') {
				if ($goods['auto_condition_use'] != 1) {
					$sc = $this->goodsdisplay->search_condition($display['auto_criteria'], array(),'relation');
					if (! $sc['category']) {
						$sc['category'] = $category_code;
					}
				}

				// light 운영방식일 경우 perpage 값 수정 :: 2019-01-07 pjw
				if ($this->operation_type == 'light') {
					$perpage = $display['count_r'];
				} else {
					$perpage = $display['count_w'] * $display['count_h'];
				}

				$sc['sort']              = $sc['auto_order'];
				$sc['display_seq']       = $display['display_seq'];
				$sc['display_tab_index'] = 0;
				$sc['page']              = 1;
				$sc['perpage']           = $perpage;
				$sc['image_size']        = $display['image_size'];
				$sc['goods_seq_exclude'] = $goods['goods_seq'];
				if (! empty($category_code)) {
				    $sc['category_code'] = $category_code;
				}

				if ($this->goodsdisplay->info_settings_have_eventprice($display['info_settings'])) {
					$sc['join_event'] = true;
				}

				// 데이터가 없는경우 기본 조건을 설정하는데 light 일경우는 조건이 none 이여야함 :: 2019-01-18 pjw
				if ($goods['auto_condition_use'] == 1) {
					if (! $display['auto_criteria']) {
						if ($this->operation_type == 'light') {
							$display['auto_criteria'] = 'none∀type=select_auto,provider=all,month=3,age=all,sex=all,agent=all,act=view,min_ea=1';
						} else {
							$display['auto_criteria'] = 'admin∀type=select_auto,provider=all,month=3,age=all,sex=all,agent=all,act=view,min_ea=1';
						}
					}
					$sc['standard'] = 'relation';
					$sc = $this->goodsdisplay->auto_select_condition($display['auto_criteria'], $sc,'relation');
					$list = $this->auto_condition_goods_list($sc);
				} else {
					$list = $this->goods_list($sc);
				}
			} else {
				$sc['relation'] = $goods['goods_seq'];
				$list = $this->goods_list($sc);
			}

			//
			$goods['relation_count_w']    = $goods['relation_count_w'] ?: $display['count_w'];
			$goods['relation_count_h']    = $goods['relation_count_h'] ?: $display['count_h'];
			$goods['relation_count_r']    = $goods['relation_count_r'] ?:  $display['count_r'];
			$goods['relation_image_size'] = $goods['relation_image_size'] ?: $display['image_size'];

			if ($list['record']) {
				// light 일 경우 :: 2019-01-03 pjw
				if ($this->operation_type == 'light') {
					$template_path    = $this->__tmp_template_path ? $this->__tmp_template_path : $this->template_path;
					$display_key      = $this->goodsdisplay->make_display_key();
					$tabRecords       = $this->goodsmodel->get_goodslist_display_light($list['record'], $display);
					$displayClass     = 'designGoodsRelationDisplay display_'.$display['kind'];
					$goods_image_size = config_load('goodsImageSize');                                                   // 이미지 사이즈 로드
					$goodsImageSize   = $goods_image_size[$display['image_size']];

					$this->template->assign($display);
					$this->template->assign('displayClass', $displayClass);
					$this->template->assign('displayElement', 'goodsRelationDisplay');
					$this->template->assign('display_key', $display_key);
					$this->template->assign('displayTabsList', array($list));
					$this->template->assign('goodsList', $tabRecords);
					$this->template->assign('template_path', $template_path);
					$this->template->assign('display_seq', $display['display_seq']);
					$this->template->assign('displayStyle', $display['style']);
					$this->template->assign('ajax_call', $ajax_call);
					$this->template->assign('skin', $this->skin);
					$this->template->assign('goods_image_size', $goods_image_size);
					$this->template->assign('goodsImageSize', $goodsImageSize);
					$this->template->define('paging', $this->skin."/_modules/display/display_paging.html");
					$this->template->define('goods_list', "../design/{$display['goods_decoration_favorite_key']}.html");
					$this->template->define('tpl', $this->skin."/_modules/display/goods_display_{$display['style']}.html");
					$goodsRelationDisplayHTML = $this->template->fetch("tpl", '', true);
				} else {
					$display_key = $this->goodsdisplay->make_display_key();
					$this->goodsdisplay->set('display_key', $display_key);
					// $this->goodsdisplay->set('title',$display['title']);
					$this->goodsdisplay->set('style', $display['style']);
					$this->goodsdisplay->set('count_w', $display['count_w']);
					$this->goodsdisplay->set('count_h', $display['count_h']);
					$this->goodsdisplay->set('count_w_swipe', $display['count_w_swipe']);
					$this->goodsdisplay->set('count_h_swipe', $display['count_h_swipe']);
					$this->goodsdisplay->set('image_decorations', $display['image_decorations']);
					$this->goodsdisplay->set('image_size', $display['image_size']);
					$this->goodsdisplay->set('text_align', $display['text_align']);
					$this->goodsdisplay->set('info_settings', $display['info_settings']);
					$this->goodsdisplay->set('displayGoodsList', $list['record']);
					$this->goodsdisplay->set('displayTabsList', array($list));
					$this->goodsdisplay->set('tab_design_type', $display['tab_design_type']);
					$this->goodsdisplay->set('platform', $display['platform']);
					$this->goodsdisplay->set('kind', $display['kind']);
					$this->goodsdisplay->set('navigation_paging_style', $display['navigation_paging_style']);
					$this->goodsdisplay->set('m_list_use', $display['m_list_use']);
					$this->goodsdisplay->set('mobile_h', $display['mobile_h']);
					$this->goodsdisplay->set('img_optimize', $display['img_opt_lattice_a']);
					$this->goodsdisplay->set('img_padding', $display['img_padding_lattice_a']);

					//슬라이딩 스타일 기능 추가 2015-10-13 jhr
					$remain = '';
					if ($display['style']=='rolling_h' && $display['h_rolling_type'] != 'moveSlides') {
						$remain_cnt = $display['count_w']-(count($list['record'])%$display['count_w']);
						if ($remain_cnt <= 0 || $remain_cnt >= $display['count_w']) {
							$remain_cnt = 0;
						}
						for ($r_i=0;$r_i<$remain_cnt;$r_i++) {
							$remain .= '<div class="slide">&nbsp;</div>';
						}
					}
					$this->goodsdisplay->set('remain',$remain);
					$this->goodsdisplay->set('h_rolling_type',$display['h_rolling_type']);

					$goodsRelationDisplayHTML = "<div id='{$display_key}' class='designGoodsRelationDisplay' designElement='goodsRelationDisplay' displaySeq='{$display['display_seq']}' displaystyle='{$display['style']}'>";
					$goodsRelationDisplayHTML .= $this->goodsdisplay->print_(true);
					$goodsRelationDisplayHTML .= "</div>";
				}
			}
			unset($display);

			// 판매자 인기상품
			$mobile_flag = false;
			$kind_str    = 'relation_seller';
			$platform    = 'pc';
			if ($this->mobileMode && $this->realMobileSkinVersion > 2) {
				$kind_str = 'relation_seller_mobile';
				$platform = 'mobile';
			}

			// 반응형일 경우 반응형 설정 가져옴
			if ($this->operation_type == 'light') {
				$kind_str = 'relation_seller';
				$platform = 'responsive';
			}

			$display = $this->goodsdisplay->get_display_type($kind_str, $platform);

			// 모바일 페이지에서 모바일 설정 사용 여부 확인 2016-01-05 jhr
			// 운영방식이 heavy 일 경우만 동작해야하므로  get_display_type 함수에 pc 플랫폼 조건 추가 :: 2019-01-02 pjw
			if (! $display || ($kind_str == 'relation_seller_mobile' && $this->mobileMode && $display['m_list_use'] != 'y')) {
				$this->get_goods_relation_seller_display_seq();
				$display = $this->goodsdisplay->get_display_type('relation_seller', 'pc');
				$mobile_flag = true;
			}

			// 상품 디스플레이 데코레이션 설정
			$display['decorations'] = json_decode(base64_decode($display['image_decorations']) , true);

			// 반응형 light 일 경우 추천상품 조건을 light 조건으로 가져옴 :: 2019-01-18 pjw
			// light 운영방식일 경우 perpage 값 수정 :: 2019-01-07 pjw
			if ($this->operation_type == 'light') {
				$display['auto_criteria'] = $goods['relation_seller_criteria_light'];
				$perpage = $display['count_r'];
			} else {
				$display['auto_criteria'] = $goods['relation_seller_criteria'];
				$perpage = $display['count_w']*$display['count_h'];
			}

			//
			$sc_s = [
				'limit'      => $perpage,
				'image_size' => $display['image_size'],
			];

			// light 운영방식일 경우 무시
			if ($this->operation_type != 'light' && $this->mobileMode  && !$mobile_flag) {
				if ($display['style'] == 'newswipe') {
					$display['count_w'] = $display['count_w_swipe'];
					$display['count_h'] = $display['count_h_swipe'];
					$sc_s['limit'] = $display['count_max_swipe'];
				} elseif($display['style'] == 'sizeswipe') {
					$sc_s['limit'] = 20;
				}
			}

			if ($goods['relation_seller_type']=='AUTO') {
				$sc_s['sort']              = $sc_s['auto_order'];
				$sc_s['display_seq']       = $display['display_seq'];
				$sc_s['display_tab_index'] = 0;
				$sc_s['page']              = 1;
				$sc_s['perpage']           = $display['count_w']*$display['count_h'];
				$sc_s['image_size']        = $display['image_size'];
				$sc_s['goods_seq_exclude'] = $goods['goods_seq'];
				if (! empty($category_code)) {
				    $sc_s['category_code'] = $category_code;
				}

				if ($this->goodsdisplay->info_settings_have_eventprice($display['info_settings'])) {
					$sc_s['join_event']	= true;
				}

				// 데이터가 없는경우 기본 조건을 설정하는데 light 일경우는 조건이 none 이여야함 :: 2019-01-18 pjw
				if (! $display['auto_criteria']) {
					if ($this->operation_type == 'light') {
						$display['auto_criteria'] = 'none∀type=select_auto,provider=all,month=3,age=all,sex=all,agent=all,act=view,min_ea=1';
					} else {
						$display['auto_criteria'] = 'admin∀type=select_auto,provider=all,month=3,age=all,sex=all,agent=all,act=view,min_ea=1';
					}
				}

				// 판매자 추천상품은 provider_seq 를 추가적으로 넘겨야 함 2019-05-28 hyem
				$display['auto_criteria'] .= ",provider_seq=".$goods['provider_seq'];
				$sc_s['standard'] = 'relation_seller';
				$sc_s = $this->goodsdisplay->auto_select_condition($display['auto_criteria'], $sc_s,'relation');

				//
				$list_s = $this->auto_condition_goods_list($sc_s);
			} else {
				$sc_s['relation_seller'] = $goods['goods_seq'];
				$list_s = $this->goods_list($sc_s);
			}

			if ($list_s['record']) {
				// light 일 경우 :: 2019-01-03 pjw
				if ($this->operation_type == 'light') {
					$template_path    = $this->__tmp_template_path ?: $this->template_path;
					$display_key      = $this->goodsdisplay->make_display_key();
					$tabRecords       = $this->goodsmodel->get_goodslist_display_light($list_s['record'], $display);
					$displayClass     = 'designGoodsRelationDisplay display_' . $display['kind'];
					$goods_image_size = config_load('goodsImageSize');                                                   // 이미지 사이즈 로드
					$goodsImageSize   = $goods_image_size[$display['image_size']];

					$this->template->assign($display);
					$this->template->assign('displayClass', $displayClass);
					$this->template->assign('displayElement', 'goodsSellerRelationDisplay');
					$this->template->assign('display_key', $display_key);
					$this->template->assign('displayTabsList', array($list_s));
					$this->template->assign('goodsList', $tabRecords);
					$this->template->assign('template_path', $template_path);
					$this->template->assign('display_seq', $display['display_seq']);
					$this->template->assign('displayStyle', $display['style']);
					$this->template->assign('ajax_call', $ajax_call);
					$this->template->assign('skin', $this->skin);
					$this->template->assign('goods_image_size', $goods_image_size);
					$this->template->assign('$goodsImageSize', $goodsImageSize);

					$this->template->define('paging', $this->skin."/_modules/display/display_paging.html");
					$this->template->define('goods_list', "../design/{$display['goods_decoration_favorite_key']}.html");
					$this->template->define('tpl', $this->skin."/_modules/display/goods_display_{$display['style']}.html");
					$goodsRelationSellerDisplayHTML = $this->template->fetch('tpl', '', true);
				} else {
					$display_key = $this->goodsdisplay->make_display_key();
					$this->goodsdisplay->set('display_key', $display_key);
					$this->goodsdisplay->set('style', $display['style']);
					$this->goodsdisplay->set('platform', $display['platform']);
					$this->goodsdisplay->set('count_w', $display['count_w']);
					$this->goodsdisplay->set('count_h', $display['count_h']);
					$this->goodsdisplay->set('image_decorations', $display['image_decorations']);
					$this->goodsdisplay->set('image_size', $display['image_size']);
					$this->goodsdisplay->set('text_align', $display['text_align']);
					$this->goodsdisplay->set('info_settings', $display['info_settings']);
					$this->goodsdisplay->set('displayGoodsList', $list_s['record']);
					$this->goodsdisplay->set('displayTabsList', array($list_s));
					$this->goodsdisplay->set('tab_design_type', $display['tab_design_type']);
					$this->goodsdisplay->set('img_optimize', $display['img_opt_lattice_a']);
					$this->goodsdisplay->set('img_padding', $display['img_padding_lattice_a']);

					//슬라이딩 스타일 기능 추가 2015-10-13 jhr
					$remain = '';
					if ($display['style']=='rolling_h' && $display['h_rolling_type'] != 'moveSlides') {
						$remain_cnt = $display['count_w'] - (count($list_s['record']) % $display['count_w']);
						if ($remain_cnt <= 0 || $remain_cnt >= $display['count_w']) {
							$remain_cnt = 0;
						}
						for ($r_i = 0; $r_i < $remain_cnt; $r_i++) {
							$remain .= '<div class="slide">&nbsp;</div>';
						}
					}
					$this->goodsdisplay->set('remain',$remain);
					$this->goodsdisplay->set('h_rolling_type',$display['h_rolling_type']);

					$goodsRelationSellerDisplayHTML = "<div id='{$display_key}' class='designGoodsRelationDisplay' designElement='goodsSellerRelationDisplay' displaySeq='{$display['display_seq']}'>";
					$goodsRelationSellerDisplayHTML .= $this->goodsdisplay->print_(true);
					$goodsRelationSellerDisplayHTML .= "</div>";
				}
			}
		}

		/* 관련된 이벤트 배너*/
		$goods_seq = $no;
		$where_category = "'" . implode("','",$goods['r_category']) . "'";

		// 현재 시간
		$now = date('Y-m-d H:i:s');

		// 특정요일에만
		$week_number = date('w');
		if ($week_number == 0) {
			$week_number = 7;
		}
		$str_where_week = "(e.app_week = '' or e.app_week = '0' or  e.app_week is null or e.app_week like '%".$week_number."%')";

		// 특정 시간 에만
		$todaytime = date('H');
		$str_where_time = "(e.app_start_time is null OR e.app_start_time='' OR LEFT(e.app_start_time,2) <= '".$todaytime."') and (e.app_start_time is null OR e.app_start_time='' OR LEFT(e.app_end_time,2) >= '".$todaytime."')";

		$r_query[] = "select e.event_seq AS seq, e.title, e.start_date, e.end_date, e.tpl_path, e.banner_filename, e.goods_desc_popup, 'event' as type
			from fm_event e where
				e.goods_rule='all' and e.display='y'
				and e.start_date <= '$now' and e.end_date >= '$now'
				and ".$str_where_week."
				and ".$str_where_time."
				and	(select count(*) from fm_event_choice where event_seq = e.event_seq and choice_type = 'except_category' and category_code in ( $where_category ) )=0
				and	(select count(*) from fm_event_choice where event_seq = e.event_seq and choice_type = 'except_goods' and goods_seq = '$goods_seq')=0
				and ( e.apply_goods_kind = (select goods_kind from fm_goods where goods_seq='$goods_seq') OR e.apply_goods_kind='all' OR e.apply_goods_kind is null)
				and e.banner_view='y'
		";
		$r_query[] = "select e.event_seq AS seq, e.title, e.start_date, e.end_date, e.tpl_path, e.banner_filename, e.goods_desc_popup, 'event' as type
			from fm_event e where
				e.goods_rule='category' and e.display='y'
				and e.start_date <= '$now' and e.end_date >= '$now'
				and ".$str_where_week."
				and ".$str_where_time."
				and	(select count(*) from fm_event_choice where event_seq = e.event_seq and choice_type = 'category' and category_code in ( $where_category ) ) > 0
				and	(select count(*) from fm_event_choice where event_seq = e.event_seq and choice_type = 'except_category' and category_code in ( $where_category ) )= 0
				and	(select count(*) from fm_event_choice where event_seq = e.event_seq and choice_type = 'except_goods' and goods_seq = '$goods_seq') = 0
				and e.banner_view='y'
		";
		$r_query[] = "select e.event_seq AS seq, e.title, e.start_date, e.end_date, e.tpl_path, e.banner_filename, e.goods_desc_popup, 'event' as type
		from fm_event e where
			e.goods_rule='goods_view' and e.display='y'
			and e.start_date <= '$now' and e.end_date >= '$now'
			and ".$str_where_week."
			and ".$str_where_time."
			and	(select count(*) from fm_event_choice where event_seq = e.event_seq and choice_type = 'goods' and goods_seq = '$goods_seq') > 0
			and e.banner_view='y'
		";
		$r_query[] = "select e.gift_seq AS seq, e.title, e.start_date, e.end_date, e.tpl_path, e.banner_filename, e.goods_desc_popup, 'gift' as type
			from fm_gift e where
				e.goods_rule='all' and e.display='y'
				and e.start_date <= '$now' and e.end_date >= '$now'
				and e.banner_view='y'
		";
		$r_query[] = "select e.gift_seq AS seq, e.title, e.start_date, e.end_date, e.tpl_path, e.banner_filename, e.goods_desc_popup, 'gift' as type
			from fm_gift e where
				e.goods_rule='category' and e.display='y'
				and e.start_date <= '$now' and e.end_date >= '$now'
				and	(select count(*) from fm_gift_choice where gift_seq = e.gift_seq and choice_type = 'category' and category_code in ( $where_category ) ) > 0
				and e.banner_view='y'
		";
		$r_query[] = "select e.gift_seq AS seq, e.title, e.start_date, e.end_date, e.tpl_path, e.banner_filename, e.goods_desc_popup, 'gift' as type
		from fm_gift e where
			e.goods_rule='goods' and e.display='y'
			and e.start_date <= '$now' and e.end_date >= '$now'
			and	(select count(*) from fm_gift_choice where gift_seq = e.gift_seq and choice_type = 'goods' and goods_seq = '$goods_seq') > 0
			and e.banner_view='y'
		";

		$query = 'select * from (('.implode(') union (',$r_query).')) t order by t.start_date desc';
		$query = $this->db->query($query);
		$assignData['event_banner'] = $query->result_array();

		// 빅데이터 추가 무료몰 제외 추가 2017-06-01
		if (! $no_bigdata && $this->isplusfreenot) {
			$reKinds = $this->bigdatamodel->get_bigdata_goods_display($no, 'view', $goods['bigdata_criteria']);
		}
		if ($goods['event']['end_date'] && $goods['event']['event_type'] == 'solo') {
			$assignData['eventEnd'] = $goods['eventEnd'];
		}
		if ($goodsvideofiles['result']) {
			$assignData['goodsvideofiles'] = $goodsvideofiles['result'];
		}

		$goods['review_divide'] = $goods['review_sum']/$goods['review_count'];

		if (is_nan($goods['review_divide'])) {
			$goods['review_divide'] = (int) $goods['review_divide'];
		}

		$assignData['shopTitle']                = $shopTitle;
		$assignData['imageWidth']               = $imageWidth;
		$assignData['imageHeight']              = $imageHeight;
		$assignData['brandInfo']                = $brandInfo;
		$assignData['mapArr']                   = $assign_mapArr;
		$assignData['goodsRelationDisplayHTML'] = $goodsRelationDisplayHTML;
		$assignData['shipping_policy']          = $shipping_policy;
		$assignData['shipping_info']            = $shipping_info;
		$assignData['fbcategory_title']         = $fbcategory_title;
		if (substr($APP_IMG,0,1) == '/') {
			$APP_IMG = substr($APP_IMG,1,strlen($APP_IMG));
		}
		$assignData['APP_IMG']          = $APP_IMG;
		$assignData['sales']            = $goods['sales'];
		$assignData['mobilesale']       = $goods['mobile_sale'];
		$assignData['fblikesale']       = $goods['like_sale'];
		$assignData['goodsStatusImage'] = $goodsStatusImage;
		$assignData['goodsImageSize']   = config_load('goodsImageSize');
		$assignData['sub_runout']       = $sub_runout;
		$assignData['sessionMember']    = $sessionMember;
		$assignData['goods']            = $goods;
		$assignData['options']          = $options;
		$assignData['additions']        = $additions;
		$assignData['suboptions']       = $suboptions;
		$assignData['inputs']           = $inputs;
		$assignData['images']           = $images;
		$assignData['icons']            = $icons;
		$assignData['delivery']         = $delivery;
		$assignData['view_brand']       = $view_brand;
		$assignData['cfg_reserve']      = $cfg_reserve;
		$assignData['wish_seq']         = $wish_seq;
		$assignData['bigdata']          = $reKinds;
		$assignData['shipping_set']     = $ship_set;
		$assignData['ship_summary']     = $ship_summary;
		$assignData['ship_gl_list']     = $ship_gl_list;
		if (serviceLimit('H_AD')) { // 입점사 관련 변수 제한
			$assignData['goodsRelationSellerDisplayHTML'] = $goodsRelationSellerDisplayHTML;
			$assignData['provider']                       = $provider;
		}

		//
		return [
			'assign'     => $assignData,
			'category'   => $category,
			'goods'      => $goods,
			'options'    => $options,
			'suboptions' => $suboptions,
			'inputs'     => $inputs,
			'alerts'     => $alerts
		];
	}

	// 컨텐츠에 포함된 이미지태그를 찾아서 일정 사이즈로 분할 (모바일용) - ocw 2015-02-02
	public function split_images($contents,$split_height=1000)
	{
		$this->load->library('Image_lib');

		$cnt = preg_match_all("/<IMG([^>]*)src=([\"']?)(\/data\/[^>\"']+)[\"']?([^>]*)>/i",$contents, $matches);

		foreach($matches[0] as $k=>$v){
			$html = "";

			$tag_string		= $matches[0][$k];
			$tag_quotation	= $matches[2][$k];
			$tag_src		= $matches[3][$k];
			$tag_else_1		= $matches[1][$k];
			$tag_else_2		= $matches[4][$k];

			$file_path	= ROOTPATH.preg_replace("/^\//i","",$tag_src);

			if(file_exists($file_path)){
				$file_ext	= array_pop(explode(".",$file_path));
				$file_name	= preg_replace("/\.".$file_ext."$/i","",array_pop(explode("/",$file_path)));

				$tag_else_1 = preg_replace("/\s(width|height)([\s\r\n])*=([\s\r\n])*([\"'])?[0-9%]+([\"'])?/i","",$tag_else_1);
				$tag_else_2 = preg_replace("/\s(width|height)([\s\r\n])*=([\s\r\n])*([\"'])?[0-9%]+([\"'])?/i","",$tag_else_2);

				$tag_else_2 = preg_replace("/\/$/i","",$tag_else_2);

				$tmp = @getimagesize($file_path);
				$image_width = $tmp[0];
				$image_height = $tmp[1];

				if(!preg_match("/^mobile_/i",$file_name)) continue;
				if(!$image_width || !$image_height) continue;

				$split_count = ceil($image_height/$split_height);
				$last_image_height = $image_height%$split_height;

				if($image_height > $split_height && $split_count==2 && $last_image_height<$split_height/2) continue;

				if($last_image_height<$split_height/2){
					$split_count--;
					$last_image_height = $split_height+$last_image_height;
				}

				$success_result = array();

				for($i=0;$i<$split_count;$i++){
					$new_height = $i<$split_count-1?$split_height:$last_image_height;
					$new_file_name = $file_name.'_split_'.$i.'.'.$file_ext;
					$new_file_src = dirname($tag_src).'/'.$new_file_name;
					$new_file_path = ROOTPATH.preg_replace("/^\//i","",$new_file_src);

					$config['image_library'] = 'gd2';
					$config['source_image'] = $file_path;
					$config['new_image'] = $new_file_path;
					$config['quality'] = '100%';
					$config['maintain_ratio'] = FALSE;
					$config['width'] = $image_width;
					$config['height'] = $new_height;
					$config['x_axis'] = 0;
					$config['y_axis'] = $i*$split_height;
					$this->image_lib->initialize($config);
					if ( ! $this->image_lib->crop())
					{
						$success_result[] = array('status' => '0','error' => $this->image_lib->display_errors());
					}else{
						@chmod($new_file_path,0777);
						$success_result[] = array(
							'new_file_src'=>$new_file_src,
						);
					}
					$this->image_lib->clear();
				}

				// 분할 성공시
				if($split_count && $split_count == count($success_result)){
					@unlink($file_path);
					$image_tags = array();
					foreach($success_result as $values){
						$image_tags[] = "<img {$tag_else_1} src={$tag_quotation}{$values['new_file_src']}{$tag_quotation} {$tag_else_2} splitimage />";
					}
					$html = str_replace("  "," ",trim(implode("<br />\n",$image_tags)));
					$contents = str_replace($tag_string,$html,$contents);
				}
			}
		}

		return $contents;
	}

	// 추가옵션 중 필수인 것 중 기본 옵션 추출
	public function get_goods_suboption_required($no){
		$result		= array();
		if	($no > 0){
			$bind[]	= $no;
			$sql	= "select * from fm_goods_suboption
						where goods_seq = ? and sub_required = 'y'";
			$query	= $this->db->query($sql, $bind);
			$result	= $query->result_array();
			if	($result)foreach($result as $k => $sub){
				$return[$sub['suboption_title']][]		= $sub;
			}
		}

		return $return;
	}

	// 필수옵션 분리형, 조합형에 따른 첫번째 옵션 데이터
	public function get_first_options($goods, $options, $applyPage=''){

		$this->load->helper('order');

		$cfg_order			= config_load('order');
		$foption_group		= array();
		$z					= 0;
		$option_view_type	= $goods['option_view_type'];
		$goods_price		= $goods['price'];
		$option_depth		= 0;
		if	($goods['price'] > $goods['sale_price'] && $goods['sale_price'] > 0)
			$goods_price	= $goods['sale_price'];
		if	($option_view_type == 'divide')
			$option_depth	= count($goods['option_divide_title']) - 1;


		if	($options)foreach($options as $k => $opt){

			//----> sale library 적용
			unset($param, $sales);
			$applypage = 'option';
			if($applyPage)	$applypage	= $applyPage;
			$param['option_type']			= 'option';
			$param['consumer_price']	= $opt['consumer_price'];
			$param['price']					= $opt['price'];

			$param['ea']						= 1;
			$param['goods_ea']			= 1;
			$param['category_code']	= $goods['r_category'];
			$param['brand_code']		= $goods['brand_code'];
			$param['goods_seq']			= $goods['goods_seq'];
			$param['goods']					= $goods;
			$this->sale->set_init($param);
			$sales	= $this->sale->calculate_sale_price($applypage);

			$opt['sales']				= $sales;
			$opt['org_price']			= ($opt['consumer_price']) ? $opt['consumer_price'] : $opt['price'];
			// 기존 스킨 유지를 위해 추가.
			$opt['consumer_price']		= $opt['consumer_price'];
			$opt['basic_sale']				= $sales['sale_list']['basic'];
			$opt['event_sale_unit']		= $sales['sale_list']['event'];
			$opt['referer_sale_unit']		= $sales['sale_list']['referer'];
			$opt['mobile_sale_unit']		= $sales['sale_list']['mobile'];
			$opt['fblike_sale_unit']			= $sales['sale_list']['like'];
			$opt['member_sale_unit']	= $sales['sale_list']['member'];
			$opt['sum_sale_price']		= $sales['total_sale_price'];
			$opt['sale_price']		= $sales['result_price'];
			$opt['sale_rate']			= $sales['sale_per'];
			$opt['eventEnd']			= $sales['eventEnd'];
			$opt['org_basic_price']		= $opt['price'];

			$opt['point']	= $this->goodsmodel->get_point_with_policy($sales['result_price']) + $sales['tot_point'];
			$opt['reserve']	= $this->goodsmodel->get_reserve_with_policy($goods['reserve_policy'],$sales['result_price'],$cfg_reserve['default_reserve_percent'],$opt['reserve_rate'],$opt['reserve_unit'],$opt['reserve']) + $sales['tot_reserve'];
			$this->sale->reset_init();
			//<---- sale library 적용


			## 연결상품 체크
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
				unset($options[$k]);
				continue;
			}

			unset($tmp_option);
			// 옵션값 0 도 tmp_option 에 추가되도록 isset 추가
			if	(isset($opt['option1']) && $opt['option1']!="")		$tmp_option[]	= $opt['option1'];
			if	(isset($opt['option2']) && $opt['option2']!="")		$tmp_option[]	= $opt['option2'];
			if	(isset($opt['option3']) && $opt['option3']!="")		$tmp_option[]	= $opt['option3'];
			if	(isset($opt['option4']) && $opt['option4']!="")		$tmp_option[]	= $opt['option4'];
			if	(isset($opt['option5']) && $opt['option5']!="")		$tmp_option[]	= $opt['option5'];


			$optstr			= $opt['option1'];
			if	($option_view_type == 'join'){
				$optstr		= implode('/', $tmp_option);
			}

			// 이벤트 할인은 js에서 재계산하지 않으므로 적용함.
			/* 정산 이후부터는 js에서 재계산 하므로 주석처리 함.2018-10-02
			if	($opt['sales']['one_sale_list']['event'] > 0){
				$opt['price']	= $opt['sales']['after_price']['event'];
			}
			*/

			// first is end
			$chk_stock_class	= '';
			$opt_string			= $optstr;
			$option_price		= $opt['price'];

			# 상품에 원가 전달 후 js구문에서 재계산 하도록 작업해야 해서 주석처리함 (2017-01-20 채우형)
			if	($opt['price'] > $opt['sale_price'] && $opt['sale_price'] > 0)
				$option_price	= $opt['sale_price'];
			$add_price			= $option_price - $goods_price;

			if	($option_depth == 0){
				if		($add_price > 0)
					$opt_string	.= '(+' . get_currency_price($add_price,2).')';
				elseif	($add_price < 0)
					$opt_string	.= '(-' . get_currency_price($add_price*-1,2).')';
			}

			// 품절표시
			if	(!$opt['chk_stock'] || $opt['package_error']){
				$chk_stock_class			= 'soldout';
				$opt_string_tmp			= $opt_string . ' ('.getAlert('sy001').')'; //(품절)
			}else{
				$chk_stock_class			= '';
				$opt_string_tmp			= $opt_string;
			}

			if	($option_view_type == 'divide' && in_array($optstr, $foption_group)){
				$key								= array_search($optstr, $foption_group);
				$foption[$key]['stock']				+= $opt['stock'];
				if($opt['chk_stock']){
					$foption[$key]['chk_stock_class'] = '';
					$foption[$key]['opt_string']		= $opt_string;
					$foption[$key]['chk_stock']		= $opt['chk_stock'];
				}
			}else{
				$foption[$z]['chk_stock_class']		= $chk_stock_class;
				$foption[$z]['opt_string']			= $opt_string_tmp;
				$foption[$z]['opt']					= $optstr;
				$foption[$z]['option1']				= $opt['option1'];
				$foption[$z]['option2']				= $opt['option2'];
				$foption[$z]['option3']				= $opt['option3'];
				$foption[$z]['option4']				= $opt['option4'];
				$foption[$z]['option5']				= $opt['option5'];
				$foption[$z]['stock']				= $opt['stock'];
				$foption[$z]['org_price']			= $opt['price'];
				$foption[$z]['price']				= $option_price;
				$foption[$z]['consumer_price']		= $opt['consumer_price'];
				$foption[$z]['reservation']			= $opt['reservation15'];
				$foption[$z]['infomation']			= $opt['infomation'];
				$foption[$z]['color']				= $opt['color']?$opt['color']:'white';
				$foption[$z]['zipcode']				= $opt['zipcode'];
				$foption[$z]['address_type']		= $opt['address_type'];
				$foption[$z]['address']				= $opt['address'];
				$foption[$z]['addressdetail']		= $opt['addressdetail'];
				$foption[$z]['address_street']		= $opt['address_street'];
				$foption[$z]['newtype']				= $opt['divide_newtype'][0];
				$foption[$z]['codedate']			= $opt['codedate'];
				$foption[$z]['sdayinput']			= $opt['sdayinput'];
				$foption[$z]['fdayinput']			= $opt['fdayinput'];
				$foption[$z]['dayauto_type']		= $opt['dayauto_type'];
				$foption[$z]['sdayauto']			= $opt['sdayauto'];
				$foption[$z]['fdayauto']			= $opt['fdayauto'];
				$foption[$z]['dayauto_day']			= $opt['dayauto_day'];
				$foption[$z]['biztel']				= $opt['biztel'];
				$foption[$z]['coupon_input']		= $opt['coupon_input'];
				$foption[$z]['chk_stock']			= $opt['chk_stock'];
				$foption[$z]['opspecial_location']	= $opt['opspecial_location'];
				$foption[$z]['org_basic_price']		= $opt['org_basic_price'];

				if	($cfg_order['ableStockStep']){
					$foption[$z]['reservation']		= $opt['reservation'.$cfg_order['ableStockStep']];
				}

				if	($option_view_type == 'divide')	$foption_group[]	= $optstr;
				$z++;
			}
		}

		return $foption;
	}

	// controllers/goods.php에 있던 분리형 옵션 만드는 함수
	public function option($goods_seq=null){

		if($goods_seq){
			$no = (int) $goods_seq;
			$return = true;
		}else{
			$no = (int) $_GET['no'];
			$return = false;
		}

		$options			= array();
		$option1			= "";
		$option2			= "";
		$option3			= "";
		$option4			= "";
		$option5			= "";
		$applypage			= 'option';
		$member_seq			= trim($_GET['member_seq']);

		$this->load->helper('order');
		$cfg_order = config_load('order');
		$this->load->model('categorymodel');
		$this->load->model('membermodel');
		$this->load->library('sale');

		$goods			= $this->get_goods($no);
		$default_option	= $this->get_goods_default_option($no);

		$data_member['member_seq']		= $this->userInfo['member_seq'];
		$data_member['group_seq']		= $this->userInfo['group_seq'];
		if	($member_seq > 0){
			$data_member				= $this->membermodel->get_member_data($member_seq);
			$data_member['member_seq']	= (int) $data_member['member_seq'];
			$data_member['group_seq']	= (int) $data_member['group_seq'];
		}

		//----> sale library 적용
		$param['cal_type']				= 'list';
		$param['total_price']			= $default_option[0]['price'];
		$param['member_seq']			= $data_member['member_seq'];
		$param['group_seq']				= $data_member['group_seq'];
		$this->sale->set_init($param);
		$this->sale->preload_set_config($applypage);
		//<---- sale library 적용

		if(isset($_GET['options'])) $options  = $_GET['options'];
		$max = $_GET['max'];
		if(!$max) $max = "1";

		// 카테고리정보
		$categorys = $this->get_goods_category($no);
		$tmparr2 = array();
		foreach($categorys as $key => $val){
			if( $val['link'] == 1 ){
				$category_code = $this->categorymodel->split_category($val['category_code']);
			}
			$tmparr = $this->categorymodel->split_category($val['category_code']);
			foreach($tmparr as $cate) $tmparr2[] = $cate;
		}
		if($tmparr2){
			$tmparr2 = array_values(array_unique($tmparr2));
			$goods['r_category'] = $tmparr2;
		}

		$whereVal[] = $no;
		$where[] = 'o.goods_seq'.'=?';
		$where[] = 'o.option_seq=s.option_seq';
		$field = 'o.option1';
		$optionssel = 'detail';
		foreach($options as $key => $option){
			$whereVal[] = $option;
			$optionssel = ($key+1);
			$field = 'o.option'.($key+2);
			$where[] = 'o.option'.($key+1) .'=?';
			${'option'.($key+1)} = $option;
		}
		$where[]	= "o.option_view = 'Y'";

		$reservation_field = "s.reservation15";
		if($cfg_order['ableStockStep']){
			$reservation_field = "s.reservation".$cfg_order['ableStockStep'];
		}

		$query = "select ".$field." as opt,sum(s.stock) as stock,o.price,o.consumer_price,sum(".$reservation_field.") as reservation, ifnull(o.infomation,'') as infomation,option1,option2,option3,option4,option5, color, zipcode, address_type, address, addressdetail, address_street,  newtype, codedate, sdayinput, fdayinput, dayauto_type, sdayauto, fdayauto, dayauto_day, biztel, coupon_input,
		o.package_option_seq1,o.package_option_seq2,o.package_option_seq3,o.package_option_seq4,o.package_option_seq5,o.package_option1,o.package_option2,o.package_option3,o.package_option4,o.package_option5,o.option_seq,o.goods_seq
		from fm_goods_option o, fm_goods_supply s where ".implode(' and ',$where)." group by ".$field." order by o.option_seq asc";
		$query = $this->db->query($query,$whereVal);
		$result = array();
		foreach($query -> result_array() as $data){
			## 연결상품 체크
			$data['package_error']	= false;
			if($goods['package_yn'] == 'y'){
				for($i_chk=1;$i_chk<=5;$i_chk++){
					if($data['package_option_seq'.$i_chk]){
						$params_chk = array(
							'mode'=>'option',
							'goods_seq'=>$data['goods_seq'],
							'option_seq'=>$data['option_seq'],
							'package_option_seq'=>$data['package_option_seq'.$i_chk],
							'package_option'=>$data['package_option'.$i_chk],
							'no'=>$i_chk,
						);
						if( !check_package_option($params_chk) ){
							$data['package_error_type']	=  'option';
							$data['package_error']	=  true;
						}
					}
				}
			}
			if($data['package_error']){
				continue;
			}
			$data['chk_stock'] = true;

			if($cfg_order['runout'] != 'ableStock') $data['reservation'] = 0;
			$data['color'] = trim($data['color']);

			//----> sale library 적용
			unset($param);
			$param['option_type']			= 'option';
			$param['consumer_price']	= $data['consumer_price'];
			$param['price']					= $data['price'];
			$param['total_price']			= $data['price'];
			$param['ea']						= 1;
			$param['category_code']	= $goods['r_category'];
			$param['goods_seq']			= $goods['goods_seq'];
			$param['goods']					= $goods;
			$this->sale->set_init($param);
			$sales	= $this->sale->calculate_sale_price($applypage);
			$this->sale->reset_init();
			$data['ori_price']	= $sales['sale_price'];
			$data['price']		= $sales['result_price'];
			//<---- sale library 적용

			if( $field == 'o.option'.$max )	${'option'.$max} = $data['opt'];

			for($i=1;$i<=5;$i++) if($i>$max) unset($data['option'.$i]);
			$data['chk_stock'] = check_stock_option($no,$option1,$option2,$option3,$option4,$option5,0,$cfg_order,'view');

			if( $goods['goods_kind'] == 'coupon' ) {
				// 티켓상품 기간체크
				$chkcouponexpire = check_coupon_date_option($no,$option1,$option2,$option3,$option4,$option5, $optionssel, $data);
				if( $chkcouponexpire['couponexpire'] === false ){
					$data['chk_stock'] = 0;//재고품절
					$data['social_start_date'] = $chkcouponexpire['social_start_date'];
					$data['social_end_date'] = $chkcouponexpire['social_end_date'];
				}
			}

			for($i=1;$i<=5;$i++) unset($data['option'.$i]);

			$data['opspecial_location'] = get_goods_options_print_array($data);

			if($data['newtype']) {
				$data['infomation'] = ($data['infomation'])?$data['infomation'].'<br/>'.get_goods_special_option_print($data):get_goods_special_option_print($data);
			}

			if( $this->mobileMode || $this->storemobileMode ) $data['ismobile'] = true;

			$result[] = $data;
		}

		if($return){
			return $result;
		}else{
			echo json_encode($result);
		}
	}

	// controllers/goods.php에 있던 합체형 옵션 만드는 함수
	public function option_join($goods_seq=null){

		if($goods_seq){
			$no = (int) $goods_seq;
			$return = true;
		}else{
			$no = (int) $_GET['no'];
			$return = false;
		}

		$options			= array();
		$option1			= "";
		$option2			= "";
		$option3			= "";
		$option4			= "";
		$option5			= "";
		$applypage			= 'option';
		$member_seq			= trim($_GET['member_seq']);

		$this->load->helper('order');
		$cfg_order = config_load('order');
		$this->load->model('categorymodel');
		$this->load->model('goodsmodel');
		$this->load->model('membermodel');
		$this->load->library('sale');

		$goods = $this->goodsmodel->get_goods($no);

		$data_member['member_seq']		= $this->userInfo['member_seq'];
		$data_member['group_seq']		= $this->userInfo['group_seq'];
		if	($member_seq > 0){
			$data_member				= $this->membermodel->get_member_data($member_seq);
			$data_member['member_seq']	= (int) $data_member['member_seq'];
			$data_member['group_seq']	= (int) $data_member['group_seq'];
		}

		$param['cal_type']				= 'list';
		$param['member_seq']			= $data_member['member_seq'];
		$param['group_seq']				= $data_member['group_seq'];
		$this->sale->set_init($param);
		$this->sale->preload_set_config($applypage);

		if(isset($_GET['options'])) $options  = $_GET['options'];
		$max = $_GET['max'];
		if(!$max) $max = "1";

		// 카테고리정보
		$categorys = $this->goodsmodel->get_goods_category($no);
		$tmparr2 = array();
		foreach($categorys as $key => $val){
			if( $val['link'] == 1 ){
				$category_code = $this->categorymodel->split_category($val['category_code']);
			}
			$tmparr = $this->categorymodel->split_category($val['category_code']);
			foreach($tmparr as $cate) $tmparr2[] = $cate;
		}
		if($tmparr2){
			$tmparr2 = array_values(array_unique($tmparr2));
			$goods['r_category'] = $tmparr2;
		}

		$whereVal[] = $no;
		$where[] = 'o.goods_seq'.'=?';
		$where[] = 'o.option_seq=s.option_seq';
		$field = 'o.option1';
		$optionssel = 'detail';
		foreach($options as $key => $option){
			$whereVal[] = $option;
			$optionssel = ($key+1);
			$field = 'o.option'.($key+2);
			$where[] = 'o.option'.($key+1) .'=?';
			${'option'.($key+1)} = $option;
		}

		$reservation_field = "s.reservation15";
		if($cfg_order['ableStockStep']){
			$reservation_field = "s.reservation".$cfg_order['ableStockStep'];
		}

		$query = "select ".$field." as opt,sum(s.stock) as stock,o.price,o.consumer_price,sum(".$reservation_field.") as reservation, ifnull(o.infomation,'') as infomation,option1,option2,option3,option4,option5, color, zipcode,  address_type, address, addressdetail, address_street,  newtype, codedate, sdayinput, fdayinput, dayauto_type, sdayauto, fdayauto, dayauto_day, biztel, coupon_input,
		o.package_option_seq1,o.package_option_seq2,o.package_option_seq3,o.package_option_seq4,o.package_option_seq5,o.package_option1,o.package_option2,o.package_option3,o.package_option4,o.package_option5,o.option_seq,o.goods_seq
		from fm_goods_option o, fm_goods_supply s where ".implode(' and ',$where)." group by ".$field." order by o.option_seq asc";
		$query = $this->db->query($query,$whereVal);
		$result = array();
		foreach($query -> result_array() as $data){
			## 연결상품 체크
			$data['package_error']	= false;
			if($goods['package_yn'] == 'y'){
				for($i_chk=1;$i_chk<=5;$i_chk++){
					if($data['package_option_seq'.$i_chk]){
						$params_chk = array(
							'mode'=>'option',
							'goods_seq'=>$data['goods_seq'],
							'option_seq'=>$data['option_seq'],
							'package_option_seq'=>$data['package_option_seq'.$i_chk],
							'package_option'=>$data['package_option'.$i_chk],
							'no'=>$i_chk,
						);
						if( !check_package_option($params_chk) ){
							$data['package_error_type']	=  'option';
							$data['package_error']	=  true;
						}
					}
				}
			}
			if($data['package_error']){
				continue;
			}
			$data['chk_stock'] = true;

			if($cfg_order['runout'] != 'ableStock') $data['reservation'] = 0;
			$data['color'] = trim($data['color']);

			//----> sale library 적용
			unset($param);
			$param['option_type']			= 'option';
			$param['consumer_price']		= $data['consumer_price'];
			$param['price']					= $data['price'];
			$param['ea']					= 1;
			$param['category_code']			= $goods['r_category'];
			$param['goods_seq']				= $goods['goods_seq'];
			$param['goods']					= $goods;
			$this->sale->set_init($param);
			$sales	= $this->sale->calculate_sale_price($applypage);
			$this->sale->reset_init();
			$data['price']	= $sales['result_price'];
			//<---- sale library 적용

			if( $field == 'o.option'.$max )	${'option'.$max} = $data['opt'];

			for($i=1;$i<=5;$i++) if($i>$max) unset($data['option'.$i]);
			$data['chk_stock'] = check_stock_option($no,$option1,$option2,$option3,$option4,$option5,0,$cfg_order,'view');

			if( $goods['goods_kind'] == 'coupon' ) {
				// 티켓상품 기간체크
				$chkcouponexpire = check_coupon_date_option($no,$option1,$option2,$option3,$option4,$option5, $optionssel, $data);
				if( $chkcouponexpire['couponexpire'] === false ){
					$data['chk_stock'] = 0;//재고품절
					$data['social_start_date'] = $chkcouponexpire['social_start_date'];
					$data['social_end_date'] = $chkcouponexpire['social_end_date'];
				}
			}

			for($i=1;$i<=5;$i++) unset($data['option'.$i]);

			$data['opspecial_location'] = get_goods_options_print_array($data);

			if($data['newtype']) {
				$data['infomation'] = ($data['infomation'])?$data['infomation'].'<br/>'.get_goods_special_option_print($data):get_goods_special_option_print($data);
			}

			if( $this->mobileMode || $this->storemobileMode ) $data['ismobile'] = true;

			$result[] = $data;
		}

		if($return){
			return $result;
		}else{
			echo json_encode($result);
		}
	}

	// 관리자 (실물/티켓)상품관리 기본값 설정 조회
	public function get_goods_default_config($gkind, $sc_provider_seq = ''){
		$provider_seq	= '';
		$manager_seq	= (int)$this->managerInfo['manager_seq'];
		if (defined('__SELLERADMIN__') === true) {
			$provider_seq	= $this->providerInfo['provider_seq'];
		}else if	($sc_provider_seq > 0){
			$provider_seq	= $sc_provider_seq;
		}else{
			$this->db->where("manager_seq",$manager_seq);
			$provider_seq = 1;
		}

		$this->db->where("goods_kind",$gkind);
		if($provider_seq) $this->db->where("provider_seq",$provider_seq);
		$query = $this->db->get("fm_goods_default_config");
		$result = $query->row_array();

		return $result;
	}

	// 관리자 (실물/티켓)상품관리 기본값 설정 저장
	public function set_goods_default_config($gkind='goods', $data=array(),$result=array()){

		$provider_seq	= 1;
		$wheres			= array();
		$manager_seq	= (int)$this->managerInfo['manager_seq'];
		if (defined('__SELLERADMIN__') === true) {
			$provider_seq	= $this->providerInfo['provider_seq'];
			if	($sc_provider_seq > 0){
				$provider_seq	= $sc_provider_seq;
			}
		}else{
			$wheres['manager_seq'] 	= $manager_seq;
			$provider_seq			= 1;
		}
		if	($provider_seq > 1){
			$manager_seq	= '0';
		}

		$wheres['goods_kind'] 	= $gkind;
		$wheres['provider_seq'] = $provider_seq;

		if ($result) {
			$data['update_date'] = date('Y-m-d H:i:s');
			$this->db->where($wheres);
			$this->db->update('fm_goods_default_config',$data);
		} else {
			$data['regist_date'] 	= date('Y-m-d H:i:s');
			$data = array_merge($data,$wheres);
			$this->db->insert('fm_goods_default_config',$data);
		}
	}

	// 관리자 리스트 페이지별 기본검색설정 조회
	public function get_search_default_config($search_page){
		if (defined('__SELLERADMIN__') === true) {
			$provider_seq 	= $this->providerInfo['provider_seq'];
			$manager_seq 	= '0';
		}else{
			$provider_seq 	= '1';
			$manager_seq 	= (int)$this->managerInfo['manager_seq'];
		}

		$query 		= $this->db->query("SELECT * FROM fm_search_default_config WHERE manager_seq=? AND provider_seq=? AND search_page=?",array($manager_seq, $provider_seq, $search_page));
		$result 	= $query->row_array();

		return $result;
	}
	//구글애널리틱스에서 사용 goods_seq 문자열 받아와서 카테고리와 브랜드를 배열로 리턴 2015-06-09 jhr
	public function all_brand_category($goods_seq){
		$ret = array();
		if($goods_seq){
			$query = "select c.title, c.category_goods_code, l.* from fm_category_link l,fm_category c where l.category_code=c.category_code and l.goods_seq in ({$goods_seq})";
			$query = $this->db->query($query);
			foreach($query->result_array() as $data){
				if($ret[$data['goods_seq']]['categoryData']) $ret[$data['goods_seq']]['categoryData'] .= "/";
				$title = '';
				$title = str_replace("'","\'",$data['title']);
				$title = str_replace("/","／",$title);
				$ret[$data['goods_seq']]['categoryData'] .= $title;
			}

			$query = "select c.title, c.brand_goods_code, l.* from fm_brand_link l,fm_brand c where l.category_code=c.category_code and l.goods_seq in ({$goods_seq})";
			$query = $this->db->query($query);
			foreach($query->result_array() as $data){
				if($ret[$data['goods_seq']]['brandData']) $ret[$data['goods_seq']]['brandData'] .= "/";
				$title = '';
				$title = str_replace("'","\'",$data['title']);
				$title = str_replace("/","／",$title);
				$ret[$data['goods_seq']]['brandData'] .= $title;
			}
		}

		return $ret;
	}


	# 임시장바구니 상품정보 불러오기 @2015-08-20 pjm
	public function get_cart_tmp_option($tmp_arr,$goods_seq,$tmp_num){

		$k		= $tmp_num;
		$opt_ea = $tmp_arr['optionEa'][$k];
		$option = $tmp_arr['option'][$k];

		$default_opt = false;

		if(empty($k) && !$opt_ea) $default_opt = true;
		if(!$k) $k = "0";
		if(!$opt_ea) $opt_ea = 1;

		$where	= $bind = array();
		$bind[] = $goods_seq;

		if($default_opt){
			$where[]	= "goods_opt.default_option=?";
			$bind[]		= 'y';
		}else{
			for($i=0; $i < 5; $i++){
				if($option[$i]==null) $option[$i] = '';
				$where[]	= "goods_opt.option".($i+1)."=?";
				$bind[]		= $option[$i];
			}
		}
		$query = "
		SELECT
			goods.goods_seq,goods.goods_name,goods.goods_code,goods.goods_kind,
			goods.shipping_weight_policy,goods.goods_weight,goods.sale_seq,
			goods.shipping_policy,goods.goods_shipping_policy,
			goods.unlimit_shipping_price,goods.limit_shipping_price,
			goods.limit_shipping_ea,goods.limit_shipping_subprice,
			goods_img.image,
			(select supply_price from fm_goods_supply where option_seq=goods_opt.option_seq) supply_price,
			goods_opt.price,
			goods_opt.consumer_price,
			goods_opt.reserve_rate,
			goods_opt.reserve_unit as reserve_unit,
			goods_opt.reserve*".$opt_ea." as reserve,
			goods.reserve_policy,
			goods.multi_discount_use,
			goods.multi_discount_ea,
			goods.multi_discount,
			goods.multi_discount_unit,
			goods.tax,
			goods.adult_goods,
			(select provider_name from fm_provider where provider_seq=goods.provider_seq) provider_name,
			goods_opt.option1,
			goods_opt.option2,
			goods_opt.option3,
			goods_opt.option4,
			goods_opt.option5,
			goods_opt.option_title,
			".$opt_ea." as ea,
			".$k." as opt_no,
			goods.display_terms,
			goods.display_terms_text,
			goods.display_terms_color,
			goods.display_terms_begin,
			goods.display_terms_end,
			goods.multi_discount_policy
		FROM
			fm_goods_option as goods_opt
			,fm_goods as goods
			left join fm_goods_image as goods_img on goods_img.cut_number = 1 AND goods_img.image_type = 'thumbCart' AND goods.goods_seq = goods_img.goods_seq
		WHERE
			goods.goods_seq=?
			AND goods_opt.goods_seq = goods.goods_seq
			AND goods.goods_status = 'normal'";

		if(count($where) > 0) {
			$query .= " and ".implode(" and ",$where);
		}
		$query		= $this->db->query($query, $bind);
		$option_data = $query->row_array();

		if($option_data['option_title']){
			$title_tmp = explode(",",$option_data['option_title']);
			for($i=0; $i < 5; $i++){
				if(!$title_tmp[$i]) $option_data['title'.($i+1)] = '';
				$option_data['title'.($i+1)] = $title_tmp[$i];
			}
		}

		return $option_data;

	}

	# 임시장바구니 상품 옵션정보 불러오기 @2015-08-20 pjm
	public function get_cart_tmp_etc_option($opt_no,$data,$goods_seq='')
	{
		if(!$goods_seq) $goods_seq = $data['option_select_goods_seq'];

		$returnArr = array();

		if($data['suboption'][$opt_no]){
			foreach($data['suboption'][$opt_no] as $k=>$subopt){

				if(!$data['suboptionEa'][$opt_no][$k]){
					$ea = 0;
				}else{
					$ea = $data['suboptionEa'][$opt_no][$k];
				}

				$query = "
					SELECT
						sub.price,
						sub.consumer_price,
						sub.goods_seq,
						sub.sub_sale,
						sub.reserve_rate,
						sub.reserve_unit,
						sub.reserve,
						sub.commission_rate,
						(select supply_price from fm_goods_supply where suboption_seq=sub.suboption_seq and goods_seq=sub.goods_seq) supply_price,
						".$ea." as ea,
						sub.suboption,
						sub.suboption_title
					FROM
						fm_goods_suboption as sub
					WHERE
						sub.goods_seq = ?
						and sub.suboption = ? and sub.suboption_title=?
						";

				$query = $this->db->query($query,array($goods_seq,$subopt,$data['suboptionTitle'][$opt_no][$k]));
				foreach ($query->result_array() as $row){
					$returnArr['suboption'][] = $row;
				}
				$query->free_result();
			}
		}

		$tmp_inputopt	= array();
		$inputsValue	= '';
		$inputsTitle	= '';
		$inputsType		= '';

		# 입력옵션
		# viewInputs : 필수옵션 없을때
		if($data['inputsValue']) {
			# inputsValue : 필수옵션 있을때
			if(is_array($data['inputsValue'][$opt_no])){
				$inputsValue	= $data['inputsValue'][$opt_no];
				$inputsTitle	= $data['inputsTitle'][$opt_no];
				$inputsType		= $data['inputsType'][$opt_no];
			}else{
				$inputsValue	= $data['inputsValue'];
				$inputsTitle	= $data['inputsTitle'];
				$inputsType		= $data['inputsType'];
			}
		}else{
			if(is_array($data['viewInputs'][$opt_no])){
				$inputsValue			= $data['viewInputs'][$opt_no];
				$inputsTitle	= $data['viewInputsTitle'][$opt_no];
				$inputsType		= $data['viewInputsType'][$opt_no];
			}else{
				$inputsValue	= $data['viewInputs'];
				$inputsTitle	= $data['viewInputsTitle'];
				$inputsType		= $data['viewInputsType'];
			}
		}

		# 상품 입력옵션 정보
		$query = "select
					input_name as input_title, input_form as type, input_limit, input_require
				from fm_goods_input where goods_seq=?";
		$query = $this->db->query($query,array($goods_seq));
		foreach($query->result_array() as $row){
			$tmp_inputopt[] = $row;
		}

		if($viewInputs || $inputsValue){

			$row = array();

			foreach($tmp_inputopt as $tmp_k => $tmp_opt){

				$inputVal = $inputsValue[$tmp_k];

				if($tmp_opt['type'] == "file"){

					$file_path	= str_replace(realpath(ROOTPATH), '', realpath($inputVal));
					if	(preg_match("/\/tmp\//i", $file_path) && file_exists(realpath(ROOTPATH) . $file_path)){
						$path = "data/tmp/";
					}else{
						$path = "data/order/";
					}

					$tmp_img = explode("/",$inputVal);
					if(count($tmp_img) > 0){
						$inputVal = $tmp_img[count($tmp_img)-1];
						$row['input_img_path']	= $path;
					}else{
						$row['input_img_path']	= '';
					}
				}

				if($inputsValue[$tmp_k]){
					$row['input_title']		= $inputsTitle[$tmp_k];
					$row['input_value']		= $inputVal;
					$row['type']			= $tmp_opt['type'];
					$tmp_inputopt[$tmp_k]	= $row;
				}
			}

		}

		if($tmp_inputopt) $returnArr['inputoption'] = $tmp_inputopt;

		return $returnArr;
	}

	public function explode_search_str($str){
		$str = trim($str);
		$str = str_replace(array('{','}','[',']','(',')',','),array(' ',' ',' ',' ',' ',' ',' '),$str);
		$arr_str = explode(" ",$str);
		foreach($arr_str as $key_str){
			if( $key_str ) $arr_auto_keyword[] = trim(strip_tags($key_str));
		}
		$arr_auto_keyword[] = str_replace(' ','',$str);
		return $arr_auto_keyword;
	}

	// 상품 검색어 자동
	public function set_search_keyword($goods_seq,$goods_code,$goods_name,$summary,$keyword){

		$arr_auto_goods_name = array();
		$arr_auto_summary = array();
		$arr_auto_goods_name = $this->explode_search_str($goods_name);
		if( $summary ) $arr_auto_summary = $this->explode_search_str($summary);
		$arr_auto_keyword = array_merge($arr_auto_goods_name,$arr_auto_summary);

		if( $goods_seq ) $arr_auto_keyword[] = $goods_seq;
		if( $goods_code ) $arr_auto_keyword[] = $goods_code;
		$arr_auto_keyword = array_unique($arr_auto_keyword);

		$auto_keyword_str = implode(',',$arr_auto_keyword);
		$arr_keyword = explode(',',$keyword);
		foreach($arr_keyword as $key_keyword => $keyword_tmp){
			$keyword_tmp = trim($keyword_tmp);
			if( $keyword_tmp && !in_array($keyword_tmp,$arr_auto_keyword) ){
				$arr_keyword_result[] = $keyword_tmp;
			}
		}

		$keyword = implode(',',$arr_keyword_result);

		return array('auto_keyword'=>$auto_keyword_str,'keyword'=>$keyword);

	}

	// 상품등록 시 키워드 조합하여 update
	public function update_keyword($goods_seq,$keyword){
		$query = "update fm_goods set keyword=? where goods_seq=?";
		$this->db->query($query,array($keyword,$goods_seq));
	}

	public function get_goods_simple($no){
		$result = false;
		$query = "select * from fm_goods where goods_seq=? limit 1";
		$query = $this->db->query($query,array($no));
		$result = $query->result_array();
		$ea = 1;
		if($result[0]['min_purchase_limit'] == 'limit' && $result[0]['min_purchase_ea']){
			$ea = $result[0]['min_purchase_ea'];
		}
		$result[0]['min_purchase_ea'] = $ea;

		$ea = 0;
		if($result[0]['max_purchase_limit'] == 'limit' && $result[0]['max_purchase_ea']){
			$ea = $result[0]['max_purchase_ea'];
		}
		$result[0]['max_purchase_ea'] = $ea;


		return $result[0];
	}

	// 카테고리/브랜드의 회원등급/회원유형 체크
	public function get_goods_permcheck($goods){
		$default_category = $this->goodsmodel->get_goods_category_default($goods['goods_seq']);
		$defaultcategoryGroup = array();
		for($i=4;$i<=strlen($default_category[category_code]);$i+=4){
			$tmpCode = substr($default_category[category_code],0,$i);
			$categoryGroupTmp = $this->categorymodel->get_category_groups($tmpCode);
			if($categoryGroupTmp) $defaultcategoryGroup = $categoryGroupTmp;
			//else break;
		}

		if($defaultcategoryGroup){
			if($this->userInfo){
				$memberData = $this->membermodel->get_member_data($this->userInfo['member_seq']);

				$groupPms = array();
				$typePms = array();
				foreach($defaultcategoryGroup as $data) {
					if($data["group_seq"]) {
						$groupPms[] = $data;
					}
					if($data["user_type"]) {
						$typePms[] = $data;
					}
				}

				$allowGroup = true;
				if(count($groupPms) > 0) {
					$allowGroup = false;
					foreach($groupPms as $data) {
						if($data['group_seq'] == $memberData['group_seq']){
							$allowGroup = true;
							break;
						}
					}
				}

				$allowType = true;
				if(count($typePms) > 0) {
					$allowType = false;
					foreach($typePms as $data) {
						if($data['user_type'] == 'default' && ! $memberData['business_seq']){
							$allowType = true;
							break;
						}
						if($data['user_type'] == 'business' && $memberData['business_seq']){
							$allowType = true;
							break;
						}
					}
				}

				if(!$allowType || !$allowGroup){
					$this->load->helper('javascript');
					//해당 상품의 카테고리에 접근권한이 없습니다.
					pageBack(getAlert('et068'));
				}
			}else{
				$this->load->helper('javascript');
				//해당 상품의 카테고리에 접근권한이 없습니다.
				alert(getAlert('et068'));
				$url = "/member/login?return_url=".$_SERVER["REQUEST_URI"];
				pageRedirect($url,'');
				exit;
			}
		}
		$default_brand = $this->goodsmodel->get_goods_brand_default($goods['goods_seq']);
		$defaultcategoryGroup = array();
		for($i=4;$i<=strlen($default_brand[category_code]);$i+=4){
			$tmpCode = substr($default_brand[category_code],0,$i);
			$categoryGroupTmp = $this->brandmodel->get_brand_groups($tmpCode);
			if($categoryGroupTmp) $defaultcategoryGroup = $categoryGroupTmp;
			//else break;
		}
		if($defaultcategoryGroup){
			if($this->userInfo){
				$memberData = $this->membermodel->get_member_data($this->userInfo['member_seq']);

				$groupPms = array();
				$typePms = array();
				foreach($defaultcategoryGroup as $data) {
					if($data["group_seq"]) {
						$groupPms[] = $data;
					}
					if($data["user_type"]) {
						$typePms[] = $data;
					}
				}

				$allowGroup = true;
				if(count($groupPms) > 0) {
					$allowGroup = false;
					foreach($groupPms as $data) {
						if($data['group_seq'] == $memberData['group_seq']){
							$allowGroup = true;
							break;
						}
					}
				}

				$allowType = true;
				if(count($typePms) > 0) {
					$allowType = false;
					foreach($typePms as $data) {
						if($data['user_type'] == 'default' && ! $memberData['business_seq']){
							$allowType = true;
							break;
						}
						if($data['user_type'] == 'business' && $memberData['business_seq']){
							$allowType = true;
							break;
						}
					}
				}

				if(!$allowType || !$allowGroup){
					$this->load->helper('javascript');
					//해당 상품의 브랜드에 접근권한이 없습니다.
					pageBack(getAlert('et069'));
				}
			}else{
				$this->load->helper('javascript');
				//해당 상품의 브랜드에 접근권한이 없습니다.
				alert(getAlert('et069'));
				$url = "/member/login?return_url=".$_SERVER["REQUEST_URI"];
				pageRedirect($url,'');
				exit;
			}
		}
	}

	public function get_package_stock($option_seq,$package_unit_ea){
		$bind = array($package_unit_ea,$package_unit_ea,$package_unit_ea,$package_unit_ea,$option_seq);
		$query_package = "select
								(stock / ?) as unit_stock,
								(badstock / ?) as unit_badstock,
								(reservation15 / ?) as unit_reservation15,
								(reservation25 / ?) as unit_reservation25,
								stock,
								badstock,
								reservation15,
								reservation25,
								supply_price,
								exchange_rate,
								safe_stock,
								option_seq
							from `fm_goods_supply` where `option_seq`=?";
		$query_package = $this->db->query($query_package, $bind);
		$data_package = $query_package->row_array();
		return $data_package;
	}

	public function update_option_stock($bind){
		$query_update = "update `fm_goods_supply` set stock=?,badstock=?,reservation15=?,reservation25=? where `option_seq`=?";
		$this->db->query($query_update,$bind);
	}

	public function update_suboption_stock($bind){
		$query_update = "update `fm_goods_supply` set stock=?,badstock=?,reservation15=?,reservation25=? where `suboption_seq`=?";
		$this->db->query($query_update,$bind);
	}

	public function update_package_stock($goods_seq){
		$query = "select * from `fm_goods_option` where `goods_seq`=? and package_count > 0";
		$query = $this->db->query($query, array($goods_seq));
		foreach( $query->result_array() as $data ){
			for($i=1;$i<6;$i++){
				if( $data['package_option_seq'.$i] ){
					$data_package = $this->get_package_stock($data['package_option_seq'.$i],$data['package_unit_ea'.$i]);
					if( $data_min['stock'] < $data_package['stock'] )$data_min = $data_package;
				}
			}
			$bind = array(
				(int) $data_min['unit_stock'],
				(int) $data_min['unit_badstock'],
				(int) $data_min['unit_reservation15'],
				(int) $data_min['unit_reservation25'],
				$data['option_seq']
			);

			$this->update_option_stock($bind);
		}

		$query = "select * from `fm_goods_suboption` where `goods_seq`=? and package_count > 0";
		$query = $this->db->query($query, array($goods_seq));
		foreach( $query->result_array() as $data ){
			if( $data['package_option_seq1'] ){
				$data_package = $this->get_package_stock($data['package_option_seq1'],$data['package_unit_ea1']);
				$data_min = $data_package;
			}
			$bind = array(
				(int) $data_min['unit_stock'],
				(int) $data_min['unit_badstock'],
				(int) $data_min['unit_reservation15'],
				(int) $data_min['unit_reservation25'],
				$data['suboption_seq']
			);
			$this->update_suboption_stock($bind);
		}
	}

	public function get_option_package_info($option_seq){
		$query = "select
					o.*, g.goods_seq,g.goods_code, g.goods_name,g.purchase_goods_name,g.hscode,
					i.image,s.supply_price,s.exchange_rate
				from
					fm_goods_option o
					left join fm_goods_image i on o.goods_seq = i.goods_seq
						and i.image_type='list1' and i.cut_number='1',
					fm_goods g,
					fm_goods_supply s
				where o.goods_seq=g.goods_seq and o.option_seq=s.option_seq and o.option_seq=?";
		$query = $this->db->query($query,array($option_seq));
		$data =  $query->row_array();
		$arr_title = explode(',',$data['option_title']);
		foreach($arr_title as $k => $title){
			$num = $k+1;
			$data['title'.$num] = $title;
		}
		return $data;
	}

	public function get_option_stock($goods_seq,$arr_option){
		$bind[] = $goods_seq;
		foreach($arr_option as $field => $val){
			$where[] = '`'.$field.'` = ?';
			$bind[] = $val;
		}

		if($where){
			$where_str = ' and '. implode(' and ',$where);
		}

		$query = "
		select
			o.*,g.scm_auto_warehousing,  g.purchase_goods_name,s.stock,s.badstock,s.reservation15,s.reservation25,s.safe_stock
		from
			fm_goods_option o,fm_goods_supply s,fm_goods g
		where
			o.option_seq=s.option_seq and o.goods_seq=g.goods_seq
			and o.goods_seq=?
		".$where_str;

		$query =  $this->db->query($query,$bind);
		$result =  $query->row_array();
		if($result['package_count']>0){ // 패키지 상품일 경우
			for($i=1;$i<6;$i++){
				$poption_seq = $result['package_option_seq'.$i];
				$punit_ea = $result['package_unit_ea'.$i];

				$data_package = $this->get_package_stock($poption_seq,$punit_ea);
				if($data_package['option_seq'] && (!$data_min || $data_min['stock'] > $data_package['stock'])) {
					$data_min = $data_package;
					$data_min['unit_ea'] = $punit_ea;
				}
			}
			$result['stock'] = (int) $data_min['unit_stock'];
			$result['badstock'] = (int) $data_min['unit_badstock'];
			$result['reservation15'] = (int) $data_min['unit_reservation15'];
			$result['reservation25'] = (int) $data_min['unit_reservation25'];
			$result['unit_ea'] = (int) $data_min['unit_ea'];
		}

		return $result;
	}

	public function save_tmp_option_package($params){

		foreach($params['save_tmp_package_option_seq'] as $k=>$option_seq){
			$bind = array();
			for($i=1;$i < 6;$i++){
				if($params['save_tmp_package_option_seq'.$i][$k]){
					$data_package = $this->goodsmodel->get_option_package_info($params['save_tmp_package_option_seq'.$i][$k]);
					$params['save_tmp_package_goods_name'.$i][$k] = $data_package['goods_name'];
					$arr_option = array($data_package['option1'],$data_package['option2'],$data_package['option3'],$data_package['option4'],$data_package['option5']);
					$params['save_tmp_package_option'.$i][$k] = option_to_package_str($arr_option);
				}
			}
			for($i=1;$i < 6;$i++) $bind[] = $params['save_tmp_package_goods_name'.$i][$k];
			for($i=1;$i < 6;$i++) $bind[] = $params['save_tmp_package_option_seq'.$i][$k];
			for($i=1;$i < 6;$i++) $bind[] = $params['save_tmp_package_option'.$i][$k];
			for($i=1;$i < 6;$i++) $bind[] = $params['save_tmp_package_unit_ea'.$i][$k];
			$bind[] = $option_seq;

			$query = "update fm_goods_option_tmp set package_goods_name1=?,package_goods_name2=?,package_goods_name3=?,
			package_goods_name4=?,package_goods_name5=?,package_option_seq1=?,
			package_option_seq2=?,package_option_seq3=?,package_option_seq4=?,
			package_option_seq5=?,package_option1=?,package_option2=?,
			package_option3=?,package_option4=?,package_option5=?,
			package_unit_ea1=?,package_unit_ea2=?,package_unit_ea3=?,
			package_unit_ea4=?,package_unit_ea5=? where option_seq=?";
			$this->db->query($query,$bind);

			$result_stock = 0;
			$result_badstock = 0;
			$result_reservation15 = 0;
			$result_reservation25 = 0;
			for($i=1;$i < 6;$i++){
				$poption_seq = $params['save_tmp_package_option_seq'.$i][$k];
				$punit_ea = $params['save_tmp_package_unit_ea'.$i][$k];
				if(!$punit_ea) $punit_ea = 1;
				if($poption_seq){
					$data_package = $this->get_package_stock($poption_seq,$punit_ea);
					if(!$data_min || $data_min['stock'] > $data_package['stock']){
						$data_min = $data_package;
					}
				}
			}
			$result_stock			= (int) $data_min['unit_stock'];
			$result_badstock		= (int) $data_min['unit_stock'];
			$result_reservation15	= (int) $data_min['unit_stock'];
			$result_reservation25	= (int) $data_min['unit_stock'];
			$query = "update fm_goods_supply_tmp set stock=?,badstock=?,reservation15=?,reservation25=? where option_seq=?";
			$this->db->query($query,array($result_stock,$result_badstock,$result_reservation15,$result_reservation25,$option_seq));
		}
	}

	public function get_package_by_option_seq($option_seq){
			$data_package = $this->get_option_package_info($option_seq);

			if($data_package['option1']) $arr_option['option1'] = $data_package['option1'];
			if($data_package['option2']) $arr_option['option2'] = $data_package['option2'];
			if($data_package['option3']) $arr_option['option3'] = $data_package['option3'];
			if($data_package['option4']) $arr_option['option4'] = $data_package['option4'];
			if($data_package['option5']) $arr_option['option5'] = $data_package['option5'];
			$data_package_stock = $this->get_option_stock($data_package['goods_seq'],$arr_option);
			$data['package_goods_seq'] = $data_package['goods_seq'];
			$data['package_stock'] = (int) $data_package_stock['stock'];
			$data['package_badstock'] = (int) $data_package_stock['badstock'];
			$data['package_safe_stock'] = (int) $data_package_stock['safe_stock'];
			$data['package_ablestock'] = $data_package_stock['stock'] - $data_package_stock['badstock'] - (int) $data_package_stock[$this->reservation_field];
			$data['package_safe_stock'] = (int) $data_package_stock['safe_stock'];
			$data['package_reservation15'] = (int) $data_package_stock['reservation15'];
			$data['package_reservation25'] = (int) $data_package_stock['reservation25'];
			$data['package_unit_ea'] = (int) $data_package_stock['unit_ea'];


			$data_package['optioncode1']	= trim($data_package['optioncode1']);
			$data_package['optioncode2']	= trim($data_package['optioncode2']);
			$data_package['optioncode3']	= trim($data_package['optioncode3']);
			$data_package['optioncode4']	= trim($data_package['optioncode4']);
			$data_package['optioncode5']	= trim($data_package['optioncode5']);

			$data['package_goods_code']		= trim($data_package['goods_code']);

			$data['package_option_code']	= $data['package_goods_code'];
			$data['package_option_code']	.= $data_package['optioncode1'];
			$data['package_option_code']	.= $data_package['optioncode2'];
			$data['package_option_code']	.= $data_package['optioncode3'];
			$data['package_option_code']	.= $data_package['optioncode4'];
			$data['package_option_code']	.= $data_package['optioncode5'];

			$data['weight']					= $data_package['weight'];
			return $data;
	}

	public function get_option_info_by_optionval($param)
	{
		$query = "select * from fm_goods_option where goods_seq=?
					and ifnull(option1,'')=? and ifnull(option2,'')=? and ifnull(option3,'')=?
					and ifnull(option4,'')=? and ifnull(option5,'')=?";
		$bind[] = $param['goods_seq'];
		$bind[] = $param['option1'];
		$bind[] = $param['option2'];
		$bind[] = $param['option3'];
		$bind[] = $param['option4'];
		$bind[] = $param['option5'];
		$query = $this->db->query($query,$bind);
		return $query->row_array();
	}

	public function get_suboption_info_by_suboptionval($param)
	{
		$query = "select * from fm_goods_suboption where goods_seq=?
					and ifnull(suboption_title,'')=? and ifnull(suboption,'')=?";
		$bind[] = $param['goods_seq'];
		$bind[] = $param['suboption_title'];
		$bind[] = $param['suboption'];
		$query = $this->db->query($query,$bind);
		return $query->row_array();
	}

	// 특정 옵션이나 상품의 전체 옵션 정보 가져오기
	public function get_option_all($goods_seq,$arr_option){
		$bind[] = $goods_seq;
		foreach($arr_option as $field => $val){
			$where[] = $field.' = ?';
			$bind[] = $val;
		}

		if($where){
			$where_str = ' and '. implode(' and ',$where);
		}

		$query = "
		select
			o.*,s.stock,s.badstock,s.reservation15,s.reservation25,s.safe_stock,s.supply_price,s.exchange_rate
		from
			fm_goods_option o,fm_goods_supply s
		where
			o.option_seq=s.option_seq
			and o.goods_seq=?
		".$where_str;

		$query =  $this->db->query($query,$bind);
		foreach($query->result_array() as $data){
			$supply_price 		= 0;
			$data['package_yn'] = 'n';
			if($data['package_count'] > 0){ // 패키지 상품일 경우
				$data['package_yn'] = 'y';
				for($i=1;$i<6;$i++){

					$poption_seq = $data['package_option_seq'.$i];
					if( $poption_seq ){
						$punit_ea = $data['package_unit_ea'.$i];

						$query = "select goods_seq from fm_goods_option where option_seq=?";
						$query = $this->db->query($query,array($poption_seq));
						$row_goods_seq = $query->row_array();

						$data_package = $this->get_package_stock($poption_seq,$punit_ea);
						$data_package['package_goods_seq']	= $row_goods_seq['goods_seq'];
						$data_package['unit_ablestock']		= (int) $data_package['unit_stock']
							- (int) $data_package['unit_badstock']
							- (int) $data_package['unit_'.$this->reservation_field];
						$data_package['ablestock'] = (int) $data_package['stock']
							- (int) $data_package['badstock']
							- (int) $data_package[$this->reservation_field];

						$supply_price += $data_package['supply_price'] * $punit_ea;
						if( $data_package && (!$data_min || $data_min['stock'] > $data_package['stock']) ){
							$data_min = $data_package;
							$data_min['unit_ea'] = $punit_ea;
						}
						$data['packages'][] = $data_package;
					}
				}
				$data['stock']			= (int) $data_min['unit_stock'];
				$data['badstock']		= (int) $data_min['unit_badstock'];
				$data['reservation15']	= (int) $data_min['unit_reservation15'];
				$data['reservation25']	= (int) $data_min['unit_reservation25'];
				$data['ablestock']		= (int) $data_min['unit_'.'stock']
											- (int) $data_min['unit_'.'badstock']
											- (int) $data_min['unit_'.$this->reservation_field];
				$data['safe_stock']		= (int) $data_min['safe_stock'];
				$data['supply_price']	= get_cutting_price($supply_price);
				$data['exchange_rate']		= $data_min['exchange_rate'];

			}

			$result[] = $data;
		}



		return $result;
	}

	public function get_goods_only($params){
		$this->db->where($params);
		return $this->db->get('fm_goods');
	}

	public function get_option($params,$orderbys=''){
		$this->db->where($params);
		if( $orderbys ) foreach($orderbys as $orderby1=>$orderby2){
			$this->db->order_by($orderby1, $orderby2);
		}
		return $this->db->get('fm_goods_option');
	}

	public function get_suboption($params,$orderbys=''){
		$this->db->where($params);
		if( $orderbys ) foreach($orderbys as $orderby1=>$orderby2){
			$this->db->order_by($orderby1, $orderby2);
		}
		return $this->db->get('fm_goods_suboption');
	}

	public function set_goods($set_params,$where_params)
	{
		$this->db->where($where_params);
		$this->db->update('fm_goods', $set_params);
	}

	public function set_goods_option($set_params,$where_params)
	{
		$this->db->where($where_params);
		$this->db->update('fm_goods_option', $set_params);
	}

	public function set_goods_suboption($set_params,$where_params)
	{
		$this->db->where($where_params);
		$this->db->update('fm_goods_suboption', $set_params);
	}

	public function package_check($goods_seq,$mode){
		$this->load->helper('goods');
		$params = array('goods_seq'=>$goods_seq);
		$query_goods		= $this->get_goods_only($params);
		$data_goods			= $query_goods->row_array();
		$query_option		= $this->get_option($params);
		$query_suboption	= $this->get_suboption($params);

		// 연결 상품 검증
		$goods_where = array('goods_seq'=>$goods_seq);
		if($data_goods['package_yn'] == 'y' && ($mode == 'option'||$mode == 'all')){
			$goods_set = array('package_err'=>'n');
			$this->errorpackage->del_error(array('goods_seq'=>$goods_seq,'type'=>'option'));
			$this->goodsmodel->set_goods($goods_set,$goods_where);
			foreach($query_option->result_array() as $data_option){
				for($cpi=0;$cpi<=5;$cpi++){
					if( $data_option['package_option_seq'.$cpi] ){
						$params_check = array(
							'mode'					=> 'option',
							'goods_seq'				=> $data_option['goods_seq'],
							'option_seq'			=> $data_option['option_seq'],
							'package_option_seq'	=> $data_option['package_option_seq'.$cpi],
							'package_option'		=> $data_option['package_option'.$cpi],
							'no'					=> $cpi,
							'del_mode'				=> 'n'
						);
						if( !check_package_option($params_check,'',true) ){
							$num++;
							$result[$num]['type'] = 'option';
							$result[$num]['option_seq'] = $data_option['option_seq'];
						}
					}
				}
			}
		}
		if($data_goods['package_yn_suboption'] == 'y' && ($mode == 'suboption'||$mode == 'all')){
			$goods_set = array('package_err_suboption'=>'n');
			$this->errorpackage->del_error(array('goods_seq'=>$goods_seq,'type'=>'suboption'));
			$this->goodsmodel->set_goods($goods_set,$goods_where);
			foreach($query_suboption->result_array() as $data_suboption){
				if($data_suboption['package_option_seq1']){
					$params_check = array(
						'mode'					=> 'suboption',
						'goods_seq'				=> $data_suboption['goods_seq'],
						'option_seq'			=> $data_suboption['suboption_seq'],
						'package_option_seq'	=> $data_suboption['package_option_seq1'],
						'package_option'		=> $data_suboption['package_option1'],
						'no'					=> 1,
						'del_mode'				=> 'n'
					);
					if( !check_package_option($params_check,'',true) ){
						$num++;
						$result[$num]['type'] = 'suboption';
						$result[$num]['option_seq'] = $data_suboption['suboption_seq'];
					}
				}
			}
		}

		return $result;
	}

	/* 상품 옵션 불량재고 */
	public function get_goods_option_badstock($goods_seq,$option1,$option2,$option3,$option4,$option5,$mode=1){
		$where_val[] = $goods_seq;
		$where[] = "o.goods_seq=?";
		if($option1!=''){
			$where[] = "o.option1=?";
			$where_val[] = $option1;
		}
		if($option2!=''){
			$where[] = "o.option2=?";
			$where_val[] = $option2;
		}
		if($option3!=''){
			$where[] = "o.option3=?";
			$where_val[] = $option3;
		}
		if($option4!=''){
			$where[] = "o.option4=?";
			$where_val[] = $option4;
		}
		if($option5!=''){
			$where[] = "o.option5=?";
			$where_val[] = $option5;
		}

		if($option1=='' && $option2=='' && $option3=='' && $option4=='' && $option5==''){
			$where[] = "o.option1=''";
			$where[] = "o.option2=''";
			$where[] = "o.option3=''";
			$where[] = "o.option4=''";
			$where[] = "o.option5=''";
		}

		$where_str = " and ". implode(' and ',$where);

		$query = "select o.*,s.badstock from fm_goods_option o,fm_goods_supply s where o.option_seq=s.option_seq ".$where_str;
		$query = $this->db->query($query,$where_val);
		$tot = 0;
		foreach($query->result_array() as $data){
			$tot += (int) $data['badstock'];
			$result[] = $data;
		}
		if	($mode == 1){
			return $tot;
		}else{
			return array($tot,$result);
		}
	}

	/* 상품 서브옵션 재고 */
	public function get_goods_suboption_badstock($goods_seq,$title,$suboption){
		$query = "select o.*,s.badstock from fm_goods_suboption o,fm_goods_supply s where o.goods_seq=? and o.suboption_seq=s.suboption_seq and o.suboption_title=? and o.suboption=? limit 1";
		$query = $this->db->query($query,array($goods_seq,$title,$suboption));
		$result = $query->result_array();
		$data = $result[0];

		#  재고가 null 이면 재고 미매칭 @2016-03-18 pjm
		if(is_null($data['badstock'])){
			$badstock = 0;
		}else{
			$badstock = (int) $data['badstock'];
		}

		return $badstock;
	}

	// 임시 상품 데이터 추출 or 신규 등록
	public function get_tmp_goods_data($tmp_seq){
		if	($tmp_seq > 0){
			$this->load->model('scmmodel');

			$sql		= "select * from fm_tmp_goods where tmp_seq = ? ";
			$query		= $this->db->query($sql, array($tmp_seq));
			$tmpData	= $query->row_array();
			if	($tmpData['tmp_seq'] > 0){

				// 입점사 정산 정보
				$commission_type		= 'SACO';
				$charge					= '0';
				if	($tmpData['provider_seq'] > 1){
					$sql		= "select * from fm_provider_charge where provider_seq = ? ";
					$query		= $this->db->query($sql, array($tmpData['provider_seq']));
					$provider	= $query->row_array();
					$commission_type	= $provider['commission_type'];
					$charge				= $provider['charge'];
				}

				$sql		= "select * from fm_tmp_goods_subdata where tmp_seq = ? ";
				$query		= $this->db->query($sql, array($tmp_seq));
				$goodsData	= $query->result_array();
				if	($goodsData) foreach($goodsData as $k => $g){
					if	($g['goods_seq'] > 0){
						$sql		= "select * from fm_tmp_goods_option where tmp_seq = ? and goods_seq = ? ";
						$query		= $this->db->query($sql, array($tmp_seq, $g['goods_seq']));
						$optionData	= $query->result_array();
						if	($optionData) foreach($optionData as $j => $o){
							unset($valueArr, $codeArr);
							if	($o['option_seq'] > 0){
								if	($this->scmmodel->chkScmConfig(true)){
									$revision	= $this->scmmodel->get_tmp_revision($tmp_seq, $g['goods_seq'], $o['option_seq']);
									if	($revision) foreach($revision as $k => $data){
										if	($data['wh_seq'] > 0 && !$location[$data['wh_seq']]){
											$location[$data['wh_seq']]	= $this->scmmodel->get_location(array('wh_seq' => $data['wh_seq']));
										}
										if	(!$location[$data['wh_seq']])
											$location[$data['wh_seq']][1][1][1]		= '1-1-1';
										$data['location']		= $location[$data['wh_seq']];
										$data['position_arr']	= explode('-', $data['location_position']);

										$revision[$k]			= $data;
									}
									$o['revision']			= $revision;
								}
								if	($o['option1']){
									$valueArr[]	= $o['option1'];
									$codeArr[]	= $o['optioncode1'];
								}
								if	($o['option2']){
									$valueArr[]	= $o['option2'];
									$codeArr[]	= $o['optioncode2'];
								}
								if	($o['option3']){
									$valueArr[]	= $o['option3'];
									$codeArr[]	= $o['optioncode3'];
								}
								if	($o['option4']){
									$valueArr[]	= $o['option4'];
									$codeArr[]	= $o['optioncode4'];
								}
								if	($o['option5']){
									$valueArr[]	= $o['option5'];
									$codeArr[]	= $o['optioncode5'];
								}
								$o['opt_values']	= $valueArr;
								$o['opt_codes']		= $codeArr;
								$o['newtype']		= explode(',', $o['newtype']);

								// 정산금액 계산
								$o['commission_price']	= 0;
								if	($o['commission_rate'] > 0){
									if		($o['commission_type'] == 'SUPR'){
										$o['commission_price']		= $o['commission_rate'];
									}elseif	($o['commission_type'] == 'SUCO'){
										$o['commission_price']		= $o['consumer_price'] * ($o['commission_rate'] * 0.01);
									}else{
										$o['commission_price']		= $o['price'] * ($o['commission_rate'] * 0.01);
									}
								}
								$g['options'][]		= $o;
							}
						}
						$tmpData['goods'][]		= $g;
					}
				}
			}else{
				return false;
			}
		}else{
			$session_id		= session_id();

			// 기존 임시 데이터 삭제
			$this->db->where(array('session_id' => $session_id));
			$query		= $this->db->get('fm_tmp_goods');
			$oldData	= $query->result_array();
			if	($oldData) foreach($oldData as $k => $old){
				unset($params);
				$params['tmp_seq']	= $old['tmp_seq'];
				$this->db->where($params);
				$this->db->delete('fm_tmp_goods');
				$this->db->where($params);
				$this->db->delete('fm_tmp_goods_subdata');
				$this->db->where($params);
				$this->db->delete('fm_tmp_goods_option');
				$this->db->where($params);
				$this->db->delete('fm_tmp_scm_revision');
			}

			$provider_seq	= '1';
			if	( defined('__SELLERADMIN__') === true )	$provider_seq	= $this->providerInfo['provider_seq'];

			$this->load->model('shippingmodel');
			$base_shipping	= $this->shippingmodel->get_shipping_base($provider_seq);
			// 배송그룹이 없는 경우
			if	(!$base_shipping['shipping_group_seq']){
				return false;
			}

			unset($insParams);
			$insParams['session_id']			= $session_id;
			$insParams['provider_seq']			= $provider_seq;
			$insParams['provider_status']		= '1';
			$insParams['shipping_group_seq']	= $base_shipping['shipping_group_seq'];
			$insParams['goods_view']			= 'look';
			$insParams['goods_status']			= 'unsold';
			$insParams['tax']					= 'tax';
			$insParams['runout_policy']			= '';
			$insParams['able_stock_limit']		= '0';
			$insParams['scm_category']			= '';
			$insParams['regist_date']			= date('Y-m-d H:i:s');
			// 입점사는 미승인, 미노출로 상품 등록 되도록
			if	( defined('__SELLERADMIN__') === true ) {
				$insParams['provider_status']		= '0';
				$insParams['goods_view']			= 'notLook';
			}
			$this->db->insert('fm_tmp_goods', $insParams);
			$tmp_seq							= $this->db->insert_id();
			$tmpData							= $insParams;
			$tmpData['tmp_seq']					= $tmp_seq;

			$goods								= $this->create_tmp_goods($tmp_seq);
			$tmpData['goods']					= $goods['goods'];
		}

		return $tmpData;
	}

	// 임시 상품 데이터 정보 추출
	public function get_tmp_goodsinfo($tmp_seq, $goods_seq){
		$sql		= "select * from fm_tmp_goods_subdata where tmp_seq = ? and goods_seq = ? ";
		$query		= $this->db->query($sql, array($tmp_seq, $goods_seq));
		return $query->row_array();
	}

	// 임시 상품 목록 초기화
	public function reset_tmp_goods($tmp_seq){
		$sql		= "select * from fm_tmp_goods_subdata where tmp_seq = ? ";
		$query		= $this->db->query($sql, array($tmp_seq));
		$goodsData	= $query->result_array();
		if	($goodsData) foreach($goodsData as $k => $goods){
			$this->remove_tmp_goods($tmp_seq, $goods['goods_seq']);
		}
		$result	= $this->create_tmp_goods($tmp_seq);

		return $result;
	}

	// 임시 상품 정보 생성
	public function create_tmp_goods($tmp_seq, $goodsData = array(), $optionData = array()){

		unset($insParams);
		if	($goodsData){
			$insParams['tmp_seq']				= $tmp_seq;
			$insParams['goods_name']			= $goodsData['goods_name'];
			$insParams['goods_code']			= $goodsData['goods_code'];
			$insParams['option_use']			= $goodsData['option_use'];
			$this->db->insert('fm_tmp_goods_subdata', $insParams);
		}else{
			$insParams['tmp_seq']				= $tmp_seq;
			$insParams['goods_name']			= '';
			$insParams['goods_code']			= '';
			$insParams['option_use']			= 'N';
			$this->db->insert('fm_tmp_goods_subdata', $insParams);
		}
		$goods_seq							= $this->db->insert_id();
		$return['goods'][0]					= $insParams;
		$return['goods'][0]['goods_seq']	= $goods_seq;

		$options							= $this->create_tmp_option($tmp_seq, $goods_seq, $optionData);
		$return['goods'][0]['options']		= $options['optionData'];
		$return['tmpData']					= $options['tmpData'];

		return $return;
	}

	// 임시 옵션 정보 생성
	public function create_tmp_option($tmp_seq, $goods_seq, $post){

		$return		= false;
		if	($tmp_seq > 0 && $goods_seq > 0){
			$this->load->model('scmmodel');

			// 기본 창고 및 로케이션 정보 추출
			if	($this->scmmodel->chkScmConfig(true)){
				$whData		= $this->scmmodel->get_warehouse(array('orderby' => 'wh_name asc'));
				$whData		= $whData[0];

				$location	= $this->scmmodel->get_location(array('wh_seq' => $whData['wh_seq']));
				$whData['location_position']		= '1-1-1';
			}

			// 기본 임시 정보 추출
			$sql		= "select * from fm_tmp_goods where tmp_seq = ? ";
			$query		= $this->db->query($sql, array($tmp_seq));
			$tmpData	= $query->row_array();
			if	($tmpData){

				// 입점사 정산 정보
				$commission_type		= 'SUCO';
				$charge					= '0';
				if	($tmpData['provider_seq'] > 1){
					$sql		= "select * from fm_provider_charge where provider_seq = ? ";
					$query		= $this->db->query($sql, array($tmpData['provider_seq']));
					$provider	= $query->row_array();
					$commission_type	= $provider['commission_type'];
					$charge				= $provider['charge'];
				}

				// 신규 생성
				if	($post){
					$this->db->where(array('tmp_seq' => $tmp_seq, 'goods_seq' => $goods_seq));
					$this->db->delete('fm_tmp_goods_option');
					if	($this->scmmodel->chkScmConfig(true)){
						$this->scmmodel->delete_tmp_revision($tmp_seq, $goods_seq);
					}

					$option_title		= implode(',', $post['option_title']);
					$option_type		= implode(',', $post['option_type']);
					$code_seq			= str_replace('goodsoption_', '', $option_type);
					$new_type			= implode(',', $post['option_new_type']);
					$loopCnt			= count($post['option_value']);
					$totalCount			= 1;
					for	($i = 0; $i < $loopCnt; $i++){
						$optValues[$i]	= explode(',', $post['option_value'][$i]);
						$optCnt[$i]		= count($optValues[$i]);
						$optCodes[$i]	= explode(',', $post['option_code'][$i]);
						$optPrice[$i]	= explode(',', $post['option_price'][$i]);
						$totalCount		= $totalCount * $optCnt[$i];
					}
					if	(in_array('color', $post['option_new_type'])){
						$colorKey		= array_search('color', $post['option_new_type']);
						$colorArr		= explode(',', $post['option_color'][$colorKey]);
						$colorKey++;
					}

					$optKey1 = $optKey2 = $optKey3 = $optKey4 = $optKey5 = 0;
					for	($r = 0; $r < $totalCount; $r++){
						$price								= 0;
						$valueArr							= array();
						$codeArr							= array();
						$color								= $colorArr[${'optKey'.$colorKey}];
						for	($o = 0; $o < 5; $o++){
							if	($optValues[$o]){
								$k							= 'optKey' . ($o + 1);
								${'option' . ($o + 1)}		= $optValues[$o][$$k];
								${'optioncode' . ($o + 1)}	= $optCodes[$o][$$k];
								$price						+= $optPrice[$o][$$k];

								$valueArr[]					= $optValues[$o][$$k];
								$codeArr[]					= $optCodes[$o][$$k];
							}
						}
						for	($o = 0; $o < 5; $o++){
							$num	= $o + 1;
							if	($o == 0)	$optKey1++;
							if	(${'optKey' . $num} > (count($optValues[$o]) - 1)){
								${'optKey' . $num}	= 0;
								$num	= $num + 1;
								${'optKey' . $num}	= ${'optKey' . $num} + 1;
							}
						}

						unset($insParams);
						$insParams['tmp_seq']				= $tmp_seq;
						$insParams['goods_seq']				= $goods_seq;
						$insParams['code_seq']				= $code_seq;
						$insParams['default_option']		= ($r > 0) ? 'n' : 'y';
						$insParams['option_type']			= $option_type;
						$insParams['option_title']			= $option_title;
						$insParams['option1']				= ($option1)		? $option1		: '';
						$insParams['option2']				= ($option2)		? $option2		: '';
						$insParams['option3']				= ($option3)		? $option3		: '';
						$insParams['option4']				= ($option4)		? $option4		: '';
						$insParams['option5']				= ($option5)		? $option5		: '';
						$insParams['optioncode1']			= ($optioncode1)	? $optioncode1	: '';
						$insParams['optioncode2']			= ($optioncode2)	? $optioncode2	: '';
						$insParams['optioncode3']			= ($optioncode3)	? $optioncode3	: '';
						$insParams['optioncode4']			= ($optioncode4)	? $optioncode4	: '';
						$insParams['optioncode5']			= ($optioncode5)	? $optioncode5	: '';
						$insParams['consumer_price']		= '0';
						$insParams['price']					= ($price > 0)		? $price		: '0';
						$insParams['commission_rate']		= $charge;
						$insParams['commission_type']		= $commission_type;
						$insParams['newtype']				= $new_type;
						$insParams['color']					= $color;
						$insParams['weight']				= '0';
						$insParams['option_view']			= 'Y';
						$insParams['stock']					= '1';
						$insParams['badstock']				= '0';
						$insParams['safe_stock']			= '0';
						$insParams['supply_price']			= '0';
						$this->db->insert('fm_tmp_goods_option', $insParams);
						$option_seq							= $this->db->insert_id();

						$optionData[$r]						= $insParams;
						$optionData[$r]['option_seq']		= $option_seq;
						$optionData[$r]['opt_titles']		= $post['option_title'];
						$optionData[$r]['opt_values']		= $valueArr;
						$optionData[$r]['opt_codes']		= $codeArr;
						$optionData[$r]['newtype']			= $post['option_new_type'];

						// 정산금액 계산
						if	($charge > 0){
							if		($commission_type == 'SUPR'){
								$optionData[$r]['commission_price']		= $charge;
							}elseif	($commission_type == 'SUCO'){
								$optionData[$r]['commission_price']		= 0;
							}else{
								$optionData[$r]['commission_price']		= $price * ($charge * 0.01);
							}
						}

						if	($this->scmmodel->chkScmConfig(true)){
							unset($default_data);
							$default_data['wh_seq']				= $whData['wh_seq'];
							$default_data['location_position']	= $whData['location_position'];
							$revision	= $this->scmmodel->create_tmp_revision($tmp_seq, $goods_seq, $option_seq, $default_data);
							if	(!$location){
								$locationData				= $this->get_location(array('wh_seq' => $whData['wh_seq']));
								if	($locationData)		$location			= $locationData;
								else					$location[1][1][1]	= '1-1-1';
							}
							$revision['position_arr']		= explode('-', $whData['location_position']);
							$revision['location']			= $location;
							$optionData[$r]['revision'][0]	= $revision;
						}
					}

					$this->db->where(array('tmp_seq' => $tmp_seq, 'goods_seq' => $goods_seq));
					$this->db->update('fm_tmp_goods_subdata', array('option_use' => 'Y'));
				}else{
					$this->db->where(array('tmp_seq' => $tmp_seq, 'goods_seq' => $goods_seq));
					$this->db->delete('fm_tmp_goods_option');

					unset($insParams);
					$insParams['tmp_seq']					= $tmp_seq;
					$insParams['goods_seq']					= $goods_seq;
					$insParams['code_seq']					= '';
					$insParams['default_option']			= 'y';
					$insParams['option_type']				= 'direct';
					$insParams['option_title']				= '';
					$insParams['option1']					= '';
					$insParams['option2']					= '';
					$insParams['option3']					= '';
					$insParams['option4']					= '';
					$insParams['option5']					= '';
					$insParams['optioncode1']				= '';
					$insParams['optioncode2']				= '';
					$insParams['optioncode3']				= '';
					$insParams['optioncode4']				= '';
					$insParams['optioncode5']				= '';
					$insParams['consumer_price']			= '0';
					$insParams['price']						= '0';
					$insParams['commission_rate']			= $charge;
					$insParams['commission_type']			= $commission_type;
					$insParams['newtype']					= '';
					$insParams['color']						= '';
					$insParams['weight']					= '0';
					$insParams['option_view']				= 'Y';
					$insParams['stock']						= '1';
					$insParams['badstock']					= '0';
					$insParams['safe_stock']				= '0';
					$insParams['supply_price']				= '0';
					$this->db->insert('fm_tmp_goods_option', $insParams);
					$option_seq								= $this->db->insert_id();

					$optionData[0]								= $insParams;
					$optionData[0]['option_seq']				= $option_seq;
					$optionData[0]['opt_titles']				= array();
					$optionData[0]['opt_values']				= array();
					$optionData[0]['opt_codes']					= array();
					$optionData[0]['newtype']					= array();

					// 정산금액 계산
					if	($charge > 0){
						$optionData[0]['commission_price']		= 0;
						if		($commission_type == 'SUPR'){
							$optionData[0]['commission_price']		= $charge;
						}
					}

					if	($this->scmmodel->chkScmConfig(true)){
						unset($default_data);
						$default_data['wh_seq']				= $whData['wh_seq'];
						$default_data['location_position']	= $whData['location_position'];
						$revision	= $this->scmmodel->create_tmp_revision($tmp_seq, $goods_seq, $option_seq, $default_data);
						if	(!$location){
							$locationData				= $this->scmmodel->get_location(array('wh_seq' => $whData['wh_seq']));
							if	($locationData)		$location			= $locationData;
							else					$location[1][1][1]	= '1-1-1';
						}
						$revision['position_arr']			= explode('-', $whData['location_position']);
						$revision['location']				= $location;
						$optionData[0]['revision'][0]		= $revision;
					}

					$this->db->where(array('tmp_seq' => $tmp_seq, 'goods_seq' => $goods_seq));
					$this->db->update('fm_tmp_goods_subdata', array('option_use' => 'N'));
				}

				$return['tmpData']		= $tmpData;
				$return['optionData']	= $optionData;
			}
		}

		return $return;
	}

	// 임시 상품 및 옵션 정보 삭제
	public function remove_tmp_goods($tmp_seq, $goods_seq){

		$this->load->model('scmmodel');

		$where['tmp_seq']	= $tmp_seq;
		$where['goods_seq']	= $goods_seq;
		$this->db->where($where);
		$this->db->delete('fm_tmp_goods_subdata');
		$this->db->where($where);
		$this->db->delete('fm_tmp_goods_option');
		if	($this->scmmodel->chkScmConfig(true)){
			$revision	= $this->scmmodel->delete_tmp_revision($tmp_seq, $goods_seq);
		}
	}

	// 임시 상품 및 옵션 정보 복사
	public function copy_tmp_goods($tmp_seq, $goods_seq){

		$this->load->model('scmmodel');
		$g = $o = $r = 0;

		// 기본 임시 정보 추출
		$sql		= "select * from fm_tmp_goods where tmp_seq = ? ";
		$query		= $this->db->query($sql, array($tmp_seq));
		$tmpData	= $query->row_array();

		$this->db->from('fm_tmp_goods_subdata');
		$this->db->where(array('tmp_seq' => $tmp_seq));
		$this->db->where_in('goods_seq', $goods_seq);
		$goodsQuery	= $this->db->get();
		while ($goods = $goodsQuery->unbuffered_row('array')){
			if	($goods['goods_seq'] > 0){
				$old_goods_seq	= $goods['goods_seq'];

				// 상품 정보 복사
				unset($goods['goods_seq']);
				$this->db->insert('fm_tmp_goods_subdata', $goods);
				$goods['goods_seq']		= $this->db->insert_id();
				$goodsData[$g]	= $goods;

				$o = $r = 0;
				$this->db->from('fm_tmp_goods_option');
				$this->db->where(array('tmp_seq' => $tmp_seq, 'goods_seq' => $old_goods_seq));
				$optionQuery	= $this->db->get();
				while ($options = $optionQuery->unbuffered_row('array')){
					if	($options['option_seq'] > 0){
						$old_option_seq	= $options['option_seq'];
						$opt_values		= array();
						$opt_codes		= array();
						$opt_titles		= explode(',', $options['option_title']);
						$newtype		= explode(',', $options['newtype']);
						if	($options['option1']){
							$opt_values[]	= $options['option1'];
							$opt_codes[]	= $options['optioncode1'];
						}
						if	($options['option2']){
							$opt_values[]	= $options['option2'];
							$opt_codes[]	= $options['optioncode2'];
						}
						if	($options['option3']){
							$opt_values[]	= $options['option3'];
							$opt_codes[]	= $options['optioncode3'];
						}
						if	($options['option4']){
							$opt_values[]	= $options['option4'];
							$opt_codes[]	= $options['optioncode4'];
						}
						if	($options['option5']){
							$opt_values[]	= $options['option5'];
							$opt_codes[]	= $options['optioncode5'];
						}

						// 옵션 정보 복사
						unset($options['option_seq']);
						$options['goods_seq']	= $goods['goods_seq'];
						$this->db->insert('fm_tmp_goods_option', $options);
						$options['option_seq']	= $this->db->insert_id();
						$goodsData[$g]['options'][$o]					= $options;
						$goodsData[$g]['options'][$o]['opt_titles']	= $opt_titles;
						$goodsData[$g]['options'][$o]['opt_values']	= $opt_values;
						$goodsData[$g]['options'][$o]['opt_codes']		= $opt_codes;
						$goodsData[$g]['options'][$o]['newtype']		= $newtype;

						if	($this->scmmodel->chkScmConfig(true)){
							$revisionData		= $this->scmmodel->get_tmp_revision($tmp_seq, $old_goods_seq, $old_option_seq);
							$r					= 0;
							if	($revisionData) foreach($revisionData as $r => $data){
								unset($data['tmp_seq'], $data['goods_seq'], $data['option_seq']);
								unset($data['location'], $data['position_arr'], $data['code_arr']);
								$revision			= $this->scmmodel->create_tmp_revision($tmp_seq, $goods['goods_seq'], $options['option_seq'], $data);
								if	(!$location[$data['wh_seq']]){
									$locationData	= $this->scmmodel->get_location(array('wh_seq' => $data['wh_seq']));
									if	($locationData)
										$location[$data['wh_seq']]			= $locationData;
									else
										$location[$data['wh_seq']][1][1][1]	= '1-1-1';
								}
								$revision['location']		= $location[$data['wh_seq']];
								$revision['position_arr']	= explode('-', $data['location_position']);

								$goodsData[$g]['options'][$o]['revision'][$r]	= $revision;

								$r++;
							}
						}
						$o++;
					}
				}
				$g++;
			}
		}
		$return['tmpData']	= $tmpData;
		$return['goods']	= $goodsData;

		return $return;
	}

	// 임시 데이터 cell 단위 update
	public function tmp_save_cell_data($type, $seq, $updParams){
		switch($type){
			case 'tmp'		: $tbName	= 'fm_tmp_goods';			$seqFld	= 'tmp_seq';	break;
			case 'goods'	: $tbName	= 'fm_tmp_goods_subdata';	$seqFld	= 'goods_seq';	break;
			case 'option'	: $tbName	= 'fm_tmp_goods_option';	$seqFld	= 'option_seq';	break;
		}
		$this->db->where(array($seqFld => $seq));
		$this->db->update($tbName, $updParams);
	}

	// 임시 데이터 컬럼 단위 일괄 변경
	public function tmp_save_all_option($post){
		if	($post) foreach($post as $k => $v){
			if	($k == 'tmp_seq' || $k == 'goods_seq')	$whrParams[$k]	= $v;
			else										$updParams[$k]	= $v;
		}
		$this->db->where($whrParams);
		$this->db->update('fm_tmp_goods_option', $updParams);
	}

	// 실데이터로 저장
	public function save_batch_regist($tmp_seq, $params_goods_seq){
		$this->load->model('goodsHandlermodel');
		$this->load->model('scmmodel');

		// 물류관리 관련 설정 정보
		$scmuse			= false;
		if	($this->scmmodel->chkScmConfig(true)){
			$scmuse			= true;
			if	(!$this->scm_cfg){
				if	($this->scmmodel->scm_cfg)	$this->scm_cfg		= $this->scmmodel->scm_cfg;
				else							$this->scm_cfg		= config_load('scm');
			}
		}

		// 기본 임시 정보 추출
		$sql			= "select * from fm_tmp_goods where tmp_seq = ? ";
		$query			= $this->db->query($sql, array($tmp_seq));
		$tmpData		= $query->row_array();
		if	($tmpData['provider_seq'] == 1)	$providerInfo	= false;
		else								$providerInfo	= true;

		$this->db->from('fm_tmp_goods_subdata');
		$this->db->where(array('tmp_seq' => $tmp_seq));
		$this->db->where_in('goods_seq', $params_goods_seq);
		$goodsQuery	= $this->db->get();
		while ($goods = $goodsQuery->unbuffered_row('array')){
			$goods_status	= 'normal';
			if	( serviceLimit('H_AD') ){
				if	($tmpData['provider_seq'] > 1 && $tmpData['provider_status'] != '1')
					$goods_status	= 'unsold';
			}else{
				$tmpData['provider_status']		= '1';
			}

			// 상품 정보 저장
			unset($insertGoods);
			$insertGoods['provider_seq']		= $tmpData['provider_seq'];
			$insertGoods['provider_status']		= $tmpData['provider_status'];
			$insertGoods['shipping_group_seq']	= $tmpData['shipping_group_seq'];
			$insertGoods['goods_view']			= $tmpData['goods_view'];
			$insertGoods['goods_status']		= $goods_status;
			$insertGoods['tax']					= $tmpData['tax'];
			$insertGoods['runout_policy']		= $tmpData['runout_policy'];
			$insertGoods['able_stock_limit']	= $tmpData['able_stock_limit'];
			$insertGoods['scm_category']		= $tmpData['scm_category'];
			$insertGoods['goods_name']			= $goods['goods_name'];
			$insertGoods['goods_code']			= $goods['goods_code'];
			$insertGoods['option_use']			= ($goods['option_use'] == 'Y' )?'1':'0';
			$goods_seq		= $this->goodsHandlermodel->goodsRegist($insertGoods, $providerInfo);
			/**
			 * 빠른 상품 등록시 배송그룹 실물상품 개수 재계산
			 * 2019-06-20
			 * @author Sunha Ryu
			 */
			if(!empty($goods_seq) && !empty($insertGoods['shipping_group_seq'])) {
			    $this->load->model('shippingmodel');
			    $this->shippingmodel->group_cnt_adjust(array($insertGoods['shipping_group_seq']));
			}

			$this->db->from('fm_tmp_goods_option');
			$this->db->where(array('tmp_seq' => $tmp_seq, 'goods_seq' => $goods['goods_seq']));
			$optionsQuery	= $this->db->get();
			while ($options = $optionsQuery->unbuffered_row('array')){
				// 옵션 정보 저장
				unset($insertOptions);
				$insertOptions['goods_seq']				= $goods_seq;
				$insertOptions['code_seq']				= $options['code_seq'];
				$insertOptions['default_option']		= $options['default_option'];
				$insertOptions['option_type']			= $options['option_type'];
				$insertOptions['option_title']			= $options['option_title'];
				$insertOptions['option1']				= $options['option1'];
				$insertOptions['option2']				= $options['option2'];
				$insertOptions['option3']				= $options['option3'];
				$insertOptions['option4']				= $options['option4'];
				$insertOptions['option5']				= $options['option5'];
				$insertOptions['optioncode1']			= $options['optioncode1'];
				$insertOptions['optioncode2']			= $options['optioncode2'];
				$insertOptions['optioncode3']			= $options['optioncode3'];
				$insertOptions['optioncode4']			= $options['optioncode4'];
				$insertOptions['optioncode5']			= $options['optioncode5'];
				$insertOptions['consumer_price']		= $options['consumer_price'];
				$insertOptions['price']					= $options['price'];
				$insertOptions['commission_rate']		= $options['commission_rate'];
				$insertOptions['commission_type']		= $options['commission_type'];
				$insertOptions['newtype']				= $options['newtype'];
				$insertOptions['color']					= $options['color'];
				$insertOptions['weight']				= $options['weight'];
				$insertOptions['option_view']			= $options['option_view'];
				$this->db->insert('fm_goods_option', $insertOptions);
				$option_seq								= $this->db->insert_id();

				// 매입정보 저장
				if	($options['badstock'] > $options['stock']) $options['badstock']	= '0';
				unset($insertSupplys);
				$insertSupplys['goods_seq']				= $goods_seq;
				$insertSupplys['option_seq']			= $option_seq;
				// 올인원 사용중이고 본사인 경우
				if ($scmuse && $tmpData['provider_seq'] == 1) {
					$insertSupplys['supply_price'] = 0;
					$insertSupplys['stock'] = 0;
					$insertSupplys['badstock'] = 0;
				}else{
					// 올인원 사용안하거나, 올인원 사용중이고 입점사인경우
					$insertSupplys['supply_price'] = $options['supply_price'];
					$insertSupplys['stock'] = $options['stock'];
					$insertSupplys['badstock'] = $options['badstock'];
				}
				$insertSupplys['safe_stock']			= $options['safe_stock'];
				$insertSupplys['reservation15']			= 0;
				$insertSupplys['reservation25']			= 0;
				$this->db->insert('fm_goods_supply', $insertSupplys);

				unset($option_name, $goods_code);
				$goods_code		= $goods['goods_code'];
				if	($options['optioncode1'])	$goods_code		.= $options['optioncode1'];
				if	($options['optioncode2'])	$goods_code		.= $options['optioncode2'];
				if	($options['optioncode3'])	$goods_code		.= $options['optioncode3'];
				if	($options['optioncode4'])	$goods_code		.= $options['optioncode4'];
				if	($options['optioncode5'])	$goods_code		.= $options['optioncode5'];
				if	($options['option1'])		$option_name	.= $options['option1'];
				if	($options['option2'])		$option_name	.= $options['option2'];
				if	($options['option3'])		$option_name	.= $options['option3'];
				if	($options['option4'])		$option_name	.= $options['option4'];
				if	($options['option5'])		$option_name	.= $options['option5'];
				$savedData[]	= array('tmp_goods_seq'		=> $goods['goods_seq'],
										'tmp_option_seq'	=> $options['option_seq'],
										'goods_name'		=> $goods['goods_name'],
										'tax'				=> $tmpData['tax'],
										'goods_code'		=> $goods_code,
										'option_name'		=> $option_name,
										'goods_seq'			=> $goods_seq,
										'option_seq'		=> $option_seq,
										'option_type'		=> 'option');
			}
		}

		// 기초조정 및 창고 저장 ( 재고 및 매입가 재정의함 )
		if	($scmuse && $tmpData['provider_seq'] == 1){
			$revisionResult	= $this->scmmodel->save_batch_revision($tmp_seq, $savedData);
		}

		// 저장된 상품 삭제
		if	($params_goods_seq) foreach($params_goods_seq as $k => $seq){
			$this->remove_tmp_goods($tmp_seq, $seq);
		}
	}

	// 빠른상품등록 임시 데이터 삭제
	public function truncate_tmp_goods_data(){
		$chkDate	= date('Y-m-d H:i:s', strtotime('-4 hour'));	// 4시간 기준
		$sql		= "select count(*) as cnt from fm_tmp_goods "
					. "where regist_date >= '" . $chkDate . "' ";
		$query		= $this->db->query($sql);
		$result		= $query->row_array();
		if	($result['cnt'] > 0){
			$sql		= "select count(*) as cnt from fm_tmp_goods "
						. "where regist_date < '" . $chkDate . "' ";
			$query		= $this->db->query($sql);
			$result		= $query->result_array();
			if	($result) foreach($result as $k => $data){
				$tmp_seq	= $data['tmp_seq'];
				$this->db->query("delete from fm_tmp_goods where tmp_seq = ? ", array($tmp_seq));
				$this->db->query("delete from fm_tmp_goods_option where tmp_seq = ? ", array($tmp_seq));
				$this->db->query("delete from fm_tmp_goods_subdata where tmp_seq = ? ", array($tmp_seq));
				$this->db->query("delete from fm_tmp_scm_revision where tmp_seq = ? ", array($tmp_seq));
			}
		}else{
			truncate_to_drop('fm_tmp_goods', $this->db->conn_id);
			truncate_to_drop('fm_tmp_goods_option', $this->db->conn_id);
			truncate_to_drop('fm_tmp_goods_subdata', $this->db->conn_id);
			truncate_to_drop('fm_tmp_scm_revision', $this->db->conn_id);
		}
	}

	// 예약 판매 상품 검색
	public function get_reserve_goods($goods_seq){

		$now_date = date('Y-m-d');
		$sql = "SELECT * FROM fm_goods WHERE goods_seq = '" . $goods_seq . "' AND display_terms = 'AUTO' AND display_terms_begin <=  '" . $now_date . "' AND display_terms_end >= '" . $now_date . "' AND display_terms_type = 'LAYAWAY'";
		$query		= $this->db->query($sql);
		$result		= $query->row_array();

		if($result){
			$result['reserve_ship_txt'] = $result['possible_shipping_date'] . ' ' .$result['possible_shipping_text'];
		}

		return $result;
	}

	public function default_price($goods_seq){
		$query = "update fm_goods g,fm_goods_option o set g.default_consumer_price=o.consumer_price, g.default_price=o.price, g.default_discount=if(o.consumer_price>o.price, o.consumer_price-o.price, 0) where g.goods_seq=o.goods_seq and o.default_option='y' and g.goods_seq=?";
		$this->db->query($query,array($goods_seq));
	}

	public function get_view($goods_seq){
		$this->db->select('goods_seq, goods_view, display_terms, display_terms_begin, display_terms_end');
		$this->db->from('fm_goods');
		if( is_array($goods_seq) ){
			$this->db->where_in('goods_seq', $goods_seq);
		}else{
			$this->db->where('goods_seq', $goods_seq);
		}
		return $this->db->get();
	}

	// 상품 공용정보 추출
	public function get_goods_common_info($sc){
		if	($sc['provider_seq'] > 0){
			$addWhere	= " and info_provider_seq = ? ";
			$addBinds[]	= $sc['provider_seq'];
		}

		$sql			= "select * from fm_goods_info where info_seq in('1','3') || (info_name != '' " . $addWhere .") "
						. " order by info_seq desc";
		$query			= $this->db->query($sql, $addBinds);
		$result			= $query->result_array();

		return $result;
	}

	// 게시판 글번호로 상품정보 불러오기 :: 2017-08-17 lwh
	public function get_board_for_provider($board_id, $board_seq){
		$src_tb = "fm_" . $board_id;
		$sql = "
		SELECT g.*
		FROM
			" . $src_tb ." AS stb LEFT JOIN fm_goods AS g
			ON stb.goods_seq = g.goods_seq
		WHERE
			stb.seq = ?
		";

		$query	= $this->db->query($sql,array($board_seq));
		$data	= $query->row_array();
		return $data;
	}

	// 마켓별 검색어 기준에 따라 노출
	public function get_openmarket_keyword($keyword, $markets=array()) {
		$result = array();
		// market 이 넘어오지 않은경우
		// 현재 사용중인 마켓 리스트 가져오기
		if(empty($markets)) {
			$MarketLinkage	= config_load('MarketLinkage');

			if($MarketLinkage['shopCode'] == "firstmall"){
				// 직접연동
				$this->load->model('connectormodel');
				$useMarketList	= $this->connectormodel->getUseMarketList();
				foreach ($useMarketList as $key => $val){
					$chkShoplinker  = $this->connectormodel->checkShoplinkMarket($key);
					if($chkShoplinker === true){
						unset($useMarketList[$key]);
					}
				}
				$markets = array_keys($useMarketList);
			} else if($MarketLinkage['shopCode'] == "shoplinker"){
				// 샵링커
				$markets[] = "shoplinker";
			} else {
				return $result;
			}
		}

		foreach($markets as $market) {
			// 스마트 스토어(최대10개-쉼표(,)구분)
			// 쿠팡(최대40개-쉼표(,)구분)
			if( $market == "storefarm" || $market == "coupang") {
				$len = $market=="storefarm" ? "10" : "40";
				$tmp = explode(",",$keyword);
				$tmp = array_slice($tmp, 0, $len);
				$result[$market] = implode(",",$tmp);
			}

			// 11번가(최대40Byte)
			// 샵링커(최대40자)
			if( $market == "open11st" || $market == "shoplinker") {
				$len = "40";
				$tmp = $keyword;
				if( mb_strlen($keyword, "UTF-8") > $len ) {
					$tmp = mb_substr($keyword, 0, $len, "UTF-8"); // len 만큼 자르기
					$chk = mb_strrpos($tmp,","); // 뒤에서 , 있는 위치 찾기
					$tmp = mb_substr($keyword,0,$chk,"UTF-8"); // 마지막, 뒤로 자르기
				}
				$result[$market] = $tmp;
			}
		}


		return $result;
	}

	// 바코드로 상품 옵션 정보 얻기
	public function get_goods_option_by_barcode($sc = array()){
		$result = false;
		$arr	= array();

		if(!empty($sc['full_barcode'])){
			$tmp_arr_full_barcode = array();
			if(is_array($sc['full_barcode'])){
				$tmp_arr_full_barcode = $sc['full_barcode'];
			}else{
				$tmp_arr_full_barcode[] = $sc['full_barcode'];
			}
			if($tmp_arr_full_barcode){
				$addWhere	.= " and o.full_barcode in ? ";
				$addBind[]	= $tmp_arr_full_barcode;
			}
		}

		$sql = "
			select
				o.*
				, s.badstock
				, s.stock
				, s.supply_price
				, s.exchange_rate
				, s.reservation15
				, s.reservation25
				, s.safe_stock
				, s.total_stock
				, s.total_supply_price
				, s.total_badstock
			from
				fm_goods_option o
				left join fm_goods_supply s on o.option_seq=s.option_seq
			where 1=1
			" . $addWhere . "
			order by o.option_seq asc
		";
		$query = $this->db->query($sql,$addBind);
		foreach($query->result_array() as $data){
			$optJoin = "";$optcodeJoin = "";
			if( $data['option_title'] ) $data['option_divide_title'] = explode(',',$data['option_title']);

			if( $data['option_type'] ) $data['option_divide_type'] = explode(',',$data['option_type']);
			if( $data['code_seq'] ) $data['option_divide_codeseq'] = explode(',',$data['code_seq']);

			if( $data['newtype'] ) $data['divide_newtype'] = explode(',',$data['newtype']);
			if( $data['tmpprice'] ) $data['divide_tmpprice'] = explode(',',$data['tmpprice']);

			if( $data['dayauto_type'] ) $data['dayauto_type_title'] = $this->dayautotype[$data['dayauto_type']];
			if( $data['dayauto_day'] ) $data['dayauto_day_title'] = $this->dayautoday[$data['dayauto_day']];
			if( $data['color']!='' ) $data['color'] = trim($data['color']);

			if( $data['option1']!='' ) $optJoin[] = $data['option1'];
			if( $data['option2']!='' ) $optJoin[] = $data['option2'];
			if( $data['option3']!='' ) $optJoin[] = $data['option3'];
			if( $data['option4']!='' ) $optJoin[] = $data['option4'];
			if( $data['option5']!='' ) $optJoin[] = $data['option5'];
			if( $optJoin ){
				$data['opts'] = $optJoin;
			}
			if( $data['optioncode1'] ) $optcodeJoin[] = $data['optioncode1'];
			if( $data['optioncode2'] ) $optcodeJoin[] = $data['optioncode2'];
			if( $data['optioncode3'] ) $optcodeJoin[] = $data['optioncode3'];
			if( $data['optioncode4'] ) $optcodeJoin[] = $data['optioncode4'];
			if( $data['optioncode5'] ) $optcodeJoin[] = $data['optioncode5'];
			if( $optcodeJoin ){
				$data['optcodes'] = $optcodeJoin;
			}

			// 옵션타이틀 추출
			if( $data['option_divide_title'] ) {
				if( $data['option1']!='' ) $data['title1'] = $data['option_divide_title'][0];
				if( $data['option2']!='' ) $data['title2'] = $data['option_divide_title'][1];
				if( $data['option3']!='' ) $data['title3'] = $data['option_divide_title'][2];
				if( $data['option4']!='' ) $data['title4'] = $data['option_divide_title'][3];
				if( $data['option5']!='' ) $data['title5'] = $data['option_divide_title'][4];
			}

			if( $data['option1']!='' && !in_array($data['option1'], $op1tArr))
				$op1tArr[] = $data['option1'];
			if( $data['option2'] != '' && !in_array($data['option2'], $op2tArr) )
				$op2tArr[] = $data['option2'];
			if( $data['option3'] != '' && !in_array($data['option3'], $op3tArr) )
				$op3tArr[] = $data['option3'];
			if( $data['option4'] != '' && !in_array($data['option4'], $op4tArr) )
				$op4tArr[] = $data['option4'];
			if( $data['option5'] != '' && !in_array($data['option5'], $op5tArr) )
				$op5tArr[] = $data['option5'];

			if	($data['consumer_price']){
				$data['supplyRate'] = get_cutting_price($data['supply_price'] / $data['consumer_price'] * 100);
				//$data['discountRate'] = (int) ( ($data['consumer_price'] - $data['price']) / $data['consumer_price'] * 100 );
				$data['discountRate'] = 100 - get_cutting_price($data['price'] / $data['consumer_price'] * 100);
			}
			$data['tax']		= get_cutting_price($data['price'] - ($data['price'] / 1.1));
			$data['net_profit']	= $data['price'] - $data['supply_price'];

			$data['rstock'] = $data['stock'] - $data[$this->reservation_field];

			// 패키지 상품의 재고 가져오기
			$data_min = '';
			if($data['package_option_seq1']){
				for($i=1;$i<6;$i++){
					if($data['package_option_seq'.$i]){
						$data_package = $this->get_package_by_option_seq($data['package_option_seq'.$i]);
						$data['package_goods_seq'.$i] = $data_package['package_goods_seq'];
						$data['package_stock'.$i] = $data_package['package_stock'];
						$data['package_badstock'.$i] = $data_package['package_badstock'];
						$data['package_ablestock'.$i] = $data_package['package_ablestock'];
						$data['package_safe_stock'.$i] = $data_package['package_safe_stock'];

						if(!$data_min || $data_min['package_stock'] > $data_package['package_stock']){
							$data_min = $data_package;
						}
					}
				}
				if($data_min){
					$data['rstock'] = (int) ($data_min['package_ablestock']);
					$data['badstock'] = (int) $data_min['package_badstock'];
					$data['stock'] = (int) $data_min['package_stock'];
					$data['reservation15'] = (int) $data_min['package_reservation15'];
					$data['reservation25'] = (int) $data_min['package_reservation25'];
					$data['safe_stock'] = (int) $data_min['package_safe_stock'];
				}
			}

			$result[] = $data;
		}
		if	($result[0]){
			$result[0]['optionArr'][] = $op1tArr;
			$result[0]['optionArr'][] = $op2tArr;
			$result[0]['optionArr'][] = $op3tArr;
			$result[0]['optionArr'][] = $op4tArr;
			$result[0]['optionArr'][] = $op5tArr;
		}
		return $result;
	}

	// 바코드로 상품 추가옵션 정보 얻기
	public function get_goods_suboption_by_barcode($sc = array()){
		$result = false;
		$arr	= array();

		if(!empty($sc['full_barcode'])){
			$tmp_arr_full_barcode = array();
			if(is_array($sc['full_barcode'])){
				$tmp_arr_full_barcode = $sc['full_barcode'];
			}else{
				$tmp_arr_full_barcode[] = $sc['full_barcode'];
			}
			if($tmp_arr_full_barcode){
				$addWhere	.= " and o.sub_full_barcode in ? ";
				$addBind[]	= $tmp_arr_full_barcode;
			}
		}

		$query = "
			select
				o.*
				, o.sub_full_barcode as full_barcode
				, s.stock
				, s.badstock
				, s.supply_price
				, s.reservation15
				, s.reservation25
				, s.safe_stock
				, s.total_supply_price
				, s.total_stock
				, s.total_badstock
			from
				fm_goods_suboption o
				,fm_goods_supply s
			where 1=1
				and o.suboption_seq = s.suboption_seq
			" . $addWhere . "
			order by o.suboption_seq asc
		";

		$query = $this->db->query($query,$addBind);
		foreach($query->result_array() as $data){
			if(!in_array($data['suboption_title'],$arr)) $arr[] = $data['suboption_title'];
			list($key) = array_keys($arr,$data['suboption_title']);

			if	($data['consumer_price']){
				$data['supplyRate'] = floor($data['supply_price'] / $data['consumer_price'] * 100);
				$data['discountRate'] = 100 - floor($data['price'] / $data['consumer_price'] * 100);
			}
			$data['tax']		= floor($data['price'] - ($data['price'] / 1.1));
			$data['net_profit']	= $data['price'] - $data['supply_price'];

			// 패키지 상품의 재고 가져오기
			$data_min = '';
			if($data['package_option_seq1']){
				$data_package = $this->get_package_by_option_seq($data['package_option_seq1']);
				$data['package_goods_seq1']		= $data_package['package_goods_seq'];
				$data['package_stock1']			= $data_package['package_stock'];
				$data['package_badstock1']		= $data_package['package_badstock'];
				$data['package_ablestock1']		= $data_package['package_ablestock'];
				$data['package_safe_stock1']	= $data_package['package_safe_stock'];
				$data['package_goods_code1']	= $data_package['package_goods_code'];
				$data['package_option_code1']	= $data_package['package_option_code'];
				$data['weight1']				= $data_package['weight'];

				if(!$data_min || $data_min['package_stock'] > $data_package['package_stock']){
					$data_min = $data_package;
				}
				if($data_min){
					$data['rstock']				= (int) $data_min['package_ablestock'];
					$data['badstock']			= (int) $data_min['package_badstock'];
					$data['stock']				= (int) $data_min['package_stock'];
					$data['reservation15']		= (int) $data_min['package_reservation15'];
					$data['reservation25']		= (int) $data_min['package_reservation25'];
					$data['safe_stock']			= (int) $data_min['package_safe_stock'];
				}
			}

			$result[$key][] = $data;
		}
		return $result;
	}


	/*  상품후기 리스트
	*	2018-12-11 pjw
	*/
	public function get_goods_review_list($sc){
		$whereSql	= array();

		// #### 조건 추가 ####

		// 상품번호
		if(!empty($sc['goods_seq']))	$whereSql[] = " and goods_seq = '".$sc['goods_seq']."'";

		// 비밀글여부
		if(!empty($sc['hidden']))		$whereSql[] = " and hidden = '".$sc['hidden']."'";
		else							$whereSql[] = " and hidden = '0' ";

		// 노출여부
		if(!empty($sc['display']))		$whereSql[] = " and display = '".$sc['display']."'";
		else							$whereSql[] = " and display = '0' ";

		// 베스트상품여부
		if(!empty($sc['best']))			$whereSql[] = " and best = '".$sc['best']."'";
		else							$whereSql[] = " and best = 'none' ";

		// order 설정
		if(!empty($sc['orderby']))		$whereSql[] = " order by ".implode(' ', $sc['orderby']);

		// limit 설정
		if(!empty($sc['limit']))		$whereSql[] = " limit ".$sc['limit'];


		$sql = "select * from fm_goods_review where 1=1 ".implode(' ', $whereSql);
		$query	= $this->db->query($sql,array($board_seq));
		$data	= $query->result_array();
		return $data;
	}

	/*  리뷰에서 평가정보 기준 가져오기
	*	2019-01-21 pjw
	*/
	public function get_review_option_standard(){
		$CI =& get_instance();
		if (isset($CI->review_option_standard)) {
			$data = $CI->review_option_standard;
		} else {
			$data = $this->db->select('label_value')
			->from('fm_boardform')
			->where('boardid', 'goods_review')
			->order_by('bulkorderform_seq', 'ASC')
			->limit(1)
			->get()
			->row_array();
			$data = explode('|', $data['label_value']); // 가공하여 첫번째 값을 반환 (첫번째 항목이 기준임)
			$CI->review_option_standard = $data;
		}
		return $data[0];
	}

	/*  상품별 평가정보
	*	2018-12-11 pjw
	*/
	public function get_review_rating_sub($goods_seq){
		$sql	= "SELECT COUNT( adddata ) AS cnt, adddata FROM  fm_goods_review WHERE adddata != '' and goods_seq = '".$goods_seq."' GROUP BY adddata";
		$query	= $this->db->query($sql,array($board_seq));
		$data	= $query->result_array();
		return $data;
	}

	// 상품디스플레이 텍스트 아이콘 치환 후 가져오기 :: 2018-12-11 pjw
	function get_goods_info_icon(&$data, &$goods_info){
		$icon = array();

		// 해당 타입의 입력값 반복문
		foreach($data['contents'] as $con){
			// 사용 체크한 입력값만 가공
			if($con['use']){
				$tmp_txt = $con['txt'];

				// 치환코드를 실제 상품 데이터와 매칭시켜 치환한다
				$tmp_txt = str_replace("{discount}",	$goods_info['sale_per'], $tmp_txt);			// 할인율
				$tmp_txt = str_replace("{brand}",		$goods_info['brand_title'], $tmp_txt);		// 대표 브랜드명
				$tmp_txt = str_replace("{brandeng}",	$goods_info['brand_title_eng'], $tmp_txt);	// 대표 브랜드 영문명
				$tmp_txt = str_replace("{bestnum}",		$goods_info['goods_index'], $tmp_txt);		// 순위 (오름차순)

				// 무료배송 조건이 아닌 경우엔 무조건 추가
				if($con['txt_type'] != 'shipping_free'){
					$icon[] = $tmp_txt;
				}

				// 무료배송인 경우엔 실제 상품이 무료배송인지 검사 후 추가
				if($con['txt_type'] == 'shipping_free' && $goods_info['shipping_group']['default_type'] == 'free'){
					$icon[] = $tmp_txt;
				}
			}
		}

		return $icon;
	}

	// 상품디스플레이 상품 데이터 가공 light 용 :: 2019-01-03 pjw
	function get_goodslist_display_light(&$goodslist, &$display_data){

		foreach($goodslist as $k=>$goods_info){
			// ######################## 상품 검색색상 정보 #######################
			$color_pick				= explode(',', $goods_info['color_pick']);

			// ######################## 상품 평가정보 ########################
			$review_toprate_key		= $this->get_review_option_standard();
			$review_rating			= $this->get_review_rating_sub($goods_info['goods_seq']);
			$tmp_review_rating		= array();
			$total_review_rating	= 0;

			foreach($review_rating as $rating){

				// 평가정보가 있는 리뷰만 가공
				if(!empty($rating['adddata'])){

					// 평가정보 파싱
					$review_label	= explode('|', $rating['adddata']);
					$review_label	= explode('^^', $review_label[0]);
					foreach($review_label as $label){
						$tmp_label						= explode('=', $label);

						// 기준이 되는 평가정보만 가져온다
						if($tmp_label[0] == 'label_value'){
							if($review_toprate_key == $tmp_label[1]){
								$tmp_review_rating					= $rating;
								$tmp_review_rating[$tmp_label[0]]	= $tmp_label[1];
							}

							$total_review_rating				+= $rating['cnt'];
						}

					}

					// 총 평가정보 카운트 합산
					$tmp_review_rating['review_usercnt']	= $total_review_rating;
					$tmp_review_rating['review_toprate']	= round(($tmp_review_rating['cnt'] / $total_review_rating) * 100);
				}
			}
			$review_rating = $tmp_review_rating;

			// ######################## 리뷰 리스트 ########################
			$sc				= array( 'goods_seq' => $goods_info['goods_seq'], 'best' => 'checked', 'orderby' => array('m_date desc'), 'limit' => '2',	);
			$review_list	= $this->get_goods_review_list($sc);
			$review_info	= array();
			if(!empty($review_list)){
				foreach($review_list as $data){

					// 해당 리뷰의 상품평가정보를 추출
					$review_label	= explode('^^', $data['adddata']);
					$label_value	= '';
					foreach($review_label as $label){
						$tmp_label	= explode('=', $label);

						// 평가정보 항목값만 가져온다
						if($tmp_label[0] == 'label_value'){
							$label_value = $tmp_label[1];
							break;
						}
					}

					$review_info[] = array(
						'toplabel'		=> $label_value,
						'subject'		=> getstrcut(strip_tags($data['subject']), 10),
						'contents'		=> getstrcut(strip_tags($data['contents']), 60),
					);
				}
			}

			// ######################## 상품 아이콘 꾸미기 ########################

			// 상품 아이콘을 텍스트형으로 설정하였을 경우에만
			$icon_condition		= array();	// 아이콘에 뿌려줄 텍스트 데이터 배열
			$icon_background	= '';		// 아이콘 배경색
			$icon_type			= '';		// 아이콘 타입
			if($display_data['decorations']['image_icon_type'] == 'condition'){
				$tmp_condition = $display_data['decorations']['image_icon_condition'];
				// 상품 아이콘 노출 조건
				foreach($tmp_condition as $key => $con){
					$icon_use_flag		= false;
					$icon_background	= $con['background']->color;

					switch($key){

						case 'package' :		// 패키지 상품일 경우
							if($goods_info['package_yn'] == 'y'){
								$icon_type		= $key;
								$icon_condition = $this->get_goods_info_icon($con, $goods_info);
								$icon_use_flag	= true;
							}
							break;

						case 'discount_per' :	// 상품 할인가 기준
							if($con['discount'] <= $goods_info['sale_per']){
								$icon_type		= $key;
								$icon_condition = $this->get_goods_info_icon($con, $goods_info);
								$icon_use_flag	= true;
							}
							break;

						case 'solo' :			// 단독 이벤트 설정 시 노출
							if($goods_info['eventEnd']){
								$icon_type		= $key;
								$icon_condition = $this->get_goods_info_icon($con, $goods_info);
								$icon_use_flag  = true;
							}
							break;

						case 'discount' :		// 할인이벤트 설정 시 노출
							if($goods_info['event_text']){
								$icon_type		= $key;
								$icon_condition = $this->get_goods_info_icon($con, $goods_info);
								$icon_use_flag  = true;
							}

							break;

						case 'date' :			// 상품 등록 지정 날짜 설정 시 노출
							// 상품 등록일
							$regist_date = strtotime($goods_info['regist_date']);

							// 특정 날짜 별로 노출 여부 정함
							if($con['date_type'] == 'after'){
								// 지정한 날짜에서 특정 일 수가 지난 시점에 등록된 상품만 노출
								$after_date = strtotime('+'.$con['date_after'].' days', $con['date']);

								if($after_date <= $regist_date){
									$icon_condition = $this->get_goods_info_icon($con, $goods_info);
									$icon_use_flag = true;
								}
							}else{
								// 현재 시간을 기준으로 설정한 날짜 안에 등록된 상품만 노출
								$before_date = strtotime('-'.$con['date'].' days');

								if($before_date <= $regist_date){
									$icon_condition = $this->get_goods_info_icon($con, $goods_info);
									$icon_use_flag = true;
								}
							}

							$icon_type		= $key;

							break;

						case 'empty' :			// 무조건인 경우 바로 아이콘 정보 리턴
							$icon_type		= $key;
							$icon_condition = $this->get_goods_info_icon($con, $goods_info);
							$icon_use_flag  = true;
							break;

					}

					if($icon_use_flag) break;
				}

				// 텍스트 아이콘을 상품정보에 추가
				$goodslist[$k]['text_icon_type']		= $icon_type;
				$goodslist[$k]['text_icon']			= $icon_condition;
				$goodslist[$k]['text_background']		= $icon_background;
			}

			// 가공한 정보를 상품리스트에 추가
			$goodslist[$k]['review_usercnt']	= $review_rating['review_usercnt'];
			$goodslist[$k]['review_toprate']	= $review_rating['review_toprate'];
			$goodslist[$k]['review_info']		= $review_info;
			$goodslist[$k]['colors']			= $color_pick;
		}

		return $goodslist;
	}

	// 입점사 추천상품 리스트 :: 2019-01-22 pjw
	public function get_mshop_auto_goodslist($provider_seq){

		// 입점사 서비스가 아닌 경우 블락
		if(!serviceLimit('H_AD')) return false;

		// 기본 모듈 로드
		$this->load->model('providermodel');
		$this->load->model('bigdatamodel');

		// 입점사 추천상품 html
		$providerRelationDisplayHTML = '';

		// 입점사 정보
		$provider = null;
		if($provider_seq){
			$provider = $this->providermodel->get_provider($provider_seq);
			if(!$this->managerInfo && $provider['provider_status']!='Y')
				//접근권한이 없습니다.
				return array('status'=>'error', 'errType'=>'redirect', 'msg'=>getAlert('gv027'), 'url'=>'/main/index');
		}

		// 추천상품 설정이 있는경우 로딩
		if(!empty($provider['auto_criteria_type'])){

			$this->load->model('goodsdisplay');

			// light 설정일 경우만 가져오므로 기본값을 반응형으로 지정
			$kind_str		= 'mshop';
			$platform		= 'responsive';

			// 상품디스플레이 정보 가져옴
			$display		= $this->goodsdisplay->get_display_type($kind_str, $platform);

			// 상품디스플레이 정보가 없는 경우 새로 추가
			if(!$display){
				$this->get_goods_mshop_display_seq();
				$display = $this->goodsdisplay->get_display_type($kind_str, $platform);
			}

			// 상품 디스플레이 데코레이션 설정
			$display['decorations'] = json_decode(base64_decode($display['image_decorations']) , true);

			// 검색 기본 조건 설정
			$sc['limit']			= $display['count_r'];
			$sc['image_size']		= $display['image_size'];

			if( $provider['minishop_goods_info_image'] ){
				$sc['image_size']		= $provider['minishop_goods_info_image'];
			}

			// 자동 (1) 인 경우 (AUTO_SUB는 자동(2)이므로 인자값으로 들어올 일이 없음)
			if($provider['auto_criteria_type'] == 'AUTO'){
				$sc['sort']				= $sc['auto_order'];
				$sc['display_seq']		= $display['display_seq'];
				$sc['display_tab_index']= 0;
				$sc['page']				= 1;
				$sc['perpage']			= $display['count_r'];
				$sc['image_size']		= $display['image_size'];
				$sc['provider_seq']		= $provider_seq;

				// 조건 설정이 없는 경우 기본값으로 설정해줌
				if(!$provider['auto_criteria']){
					$provider['auto_criteria'] = 'none∀type=select_auto,display_title=,same_mshop=1,month=1,age=all,sex=all,agent=all,act=order_cnt,review_cnt=1,min_ea=1';
				}

				$sc				= $this->goodsdisplay->auto_select_condition($provider['auto_criteria'], $sc,'mshop');
				$list			= $this->auto_condition_goods_list($sc);
			}else if($provider['auto_criteria_type'] == 'MANUAL'){
				$sc['provider_relation'] = $provider_seq;
				$list			= $this->goods_list($sc);
			}else if($provider['auto_criteria_type'] == 'TEXT'){
				$list['contents_type']			= strtolower($provider['auto_criteria_type']);
				$list['tab_contents']			= $provider['auto_contents'];
				$list['tab_contents_mobile']	= $provider['auto_contents'];
			}

			// 상품 디스플레이 노출
			$template_path		= $this->__tmp_template_path ? $this->__tmp_template_path : $this->template_path;
			$display_key		= $this->goodsdisplay->make_display_key();
			$tabRecords			= $this->goodsmodel->get_goodslist_display_light($list['record'], $display);
			$displayClass		= 'designGoodsRelationDisplay display_'.$display['kind'];
			$goods_image_size	= config_load('goodsImageSize');// 이미지 사이즈 로드
			$goodsImageSize		= $goods_image_size[$display['image_size']];

			$this->template->assign($display);
			$this->template->assign('displayClass', $displayClass);
			$this->template->assign('displayElement', 'mshopGoodsDisplay');
			$this->template->assign('display_key',$display_key);
			$this->template->assign('displayTabsList',array($list));
			$this->template->assign('goodsList',$tabRecords);
			$this->template->assign('template_path',$template_path);
			$this->template->assign('display_seq',$display['display_seq']);
			$this->template->assign('displayStyle',$display['style']);
			$this->template->assign('ajax_call',$ajax_call);
			$this->template->assign('skin',$this->skin);
			$this->template->assign('goodsImageSize', $goodsImageSize);
			if(count($tabRecords)>0){
				$this->template->assign('isRecommend',true);
			}else{
				$this->template->assign('isRecommend',false);
			}
			$this->template->define('paging',		$this->skin."/_modules/display/display_paging.html");
			$this->template->define('goods_list',	"../design/{$display['goods_decoration_favorite_key']}.html");
			$this->template->define('tpl',			$this->skin."/_modules/display/goods_display_{$display['style']}.html");
			$providerRelationDisplayHTML = $this->template->fetch("tpl", '', true);

			unset($display);

		}

		return $providerRelationDisplayHTML;
	}

	// 입점사 상품디스플레이 데이터 키 가져오기 :: 2019-01-22 pjw
	public function get_goods_mshop_display_seq(){
		// 입점사 서비스가 아닌 경우 블락
		if(!serviceLimit('H_AD')) return false;

		$query			= $this->db->query("select * from fm_design_display where kind='mshop' and platform = 'responsive' ");
		$display		= $query->row_array();

		if(!$display){
			$this->db->insert("fm_design_display",array(
				'admin_comment'					=> '미니샵 추천상품',
				'kind'							=> 'mshop',
				'count_r'						=> 8,
				'style'							=> 'sizeswipe',
				'image_size'					=> 'list1',
				'text_align'					=> 'center',
				'platform '						=> 'responsive',
				'navigation_paging_style'		=> 'paging_style_1',
				'info_settings'					=> '{}',
				'goods_decoration_type'			=> 'favorite',
				'goods_decoration_favorite_key' => 'goods_info_style_1',
				'goods_decoration_favorite'		=> '',
			));
			$query		= $this->db->query("select * from fm_design_display where kind='mshop' and platform = 'responsive'");
			$display	= $query->row_array();
		}

		return array('display_seq' => $display['display_seq']);
	}

	/**
	 * 상품번호로 shipping_group_seq 를 반환한다.
	 *
	 * @param int|array $goods_seq
	 * @return array
	 */
	public function get_ship_grp_seq_by_goods_seq($goods_seq)
	{
	    if(empty($goods_seq))  return false;
	    if(is_array($goods_seq) === false) {
	        $goods_seq = array($goods_seq);
	    }
	    $query = $this->db->select("DISTINCT shipping_group_seq as shipping_group_seq", false)
	    ->from("fm_goods")->where_in("goods_seq", $goods_seq)
	    ->get();
	    $result = $query->result_array();

	    $data = array();
	    if(count($result)>0) {
	        foreach($result as $row) {
	            $data[] = $row['shipping_group_seq'];
	        }
	    }
	    return $data;
	}


	public function get_gift_list($sc){

		$adminOrder		= $sc['adminOrder'];
		$sc['page']		= $sc['page'] > 1 ? $sc['page'] : 0;

		$where = $subWhere = $whereStr = $countWhere = "";
		$bind = array();

		$arg_list = func_get_args();

		if($subWhere){
			$where[] = "g.goods_seq in (select goods_seq from fm_category_link where ".$subWhere.")";
		}
		if( isset($sc['selectGoodsName']) && $sc['selectGoodsName'] ){
			//$where[] = "g.goods_name like ?";
			//$bind[] = '%'.$sc['selectGoodsName'].'%';
			$where[] = " (g.goods_name like '%".$sc['selectGoodsName']."%' or g.goods_code like '%".$sc['selectGoodsName']."%' ) ";
		}

		if( isset($sc['selectStartconsumerPrice']) && $sc['selectStartconsumerPrice'] ){
			$where[] = "o.consumer_price >= '".$sc['selectStartconsumerPrice']."'";
		}
		if( isset($sc['selectEndconsumerPrice']) && $sc['selectEndconsumerPrice'] ){
			$where[] = "o.consumer_price <= '".$sc['selectEndconsumerPrice']."'";
		}

		if( $sc['goodsStatus'] ){
			$where[] = "g.goods_status = '".$sc['goodsStatus']."'";
		}

		if( $sc['goodsView'] ){
			$where[] = "g.goods_view = '".$sc['goodsView']."'";
		}

		if( $sc['provider_seq'] || $sc['select_provider']){
			if(!$sc['provider_seq']) $sc['provider_seq'] = $sc['select_provider'];
			$where[]		= "g.provider_seq = '".$sc['provider_seq']."'";
			$countWhere[]	= "g.provider_seq = '".$sc['provider_seq']."'";
		}

		if( $sc['ship_grp_seq'] ){
			$where[] = "g.shipping_group_seq = '".$sc['ship_grp_seq']."'";
		}

		if($sc['mode'] == "gSelectGift"){
			$sortStr	= " ORDER BY " .$sc['orderby']." ".$sc['sort'];
		}else{
			$arrSort	= array('g.goods_seq desc','g.goods_seq asc','g.purchase_ea desc','g.purchase_ea asc','g.page_view desc','g.page_view asc','g.review_count desc','g.review_count asc');
			$sortStr	= " ORDER BY " .$arrSort[$sc['sort']];
		}

		$limitStr		= " LIMIT {$sc['page']}, {$sc['perpage']}";
		$search_field	= " g.goods_seq,g.goods_name,g.goods_type,o.price, o.consumer_price";

		$join			= "
						fm_goods g
						inner join fm_goods_option o on o.goods_seq=g.goods_seq";

		$whereStr		= " g.goods_type='gift' and o.default_option ='y'";

		if(!empty($sc['selectEvent']) || !empty($sc['selectEventBenefits'])){
			$join .= "
				left join fm_event_choice e on g.goods_seq = e.goods_seq
			";

			$where[] = "e.event_seq = '".$sc['selectEventBenefits']."'";

			if(!empty($sc['selectEventBenefits'])){
				$where[] = "e.event_benefits_seq = '".$sc['selectEventBenefits']."'";
			}
		}

		if($where){
			$whereStr .= ' and '.implode(' and ',$where);
		}

		//$query = "select ".$search_field." from ".$join.$whereStr.$sortStr.$limitStr;
		if($countWhere) $countWheres = implode(" AND ",$countWhere);

		$sql				= array();
		$sql['field']		= $search_field;
		$sql['table']		= $join;
		$sql['wheres']		= $whereStr;
		$sql['countWheres']	= $countWheres;
		$sql['orderby']		= $sortStr;
		$sql['limit']		= $limitStr;

		$result				= pagingNumbering($sql,$sc);

		return $result;
	}

	// 공용 :: 선택한 상품 리스트 : 노출 필드 정리
	public function get_select_goods_list($issuegoods = array()){

		if(!$issuegoods) return null;

		$arrGoodsSeq = array();
		$_field_list = array("provider_seq","provider_name","goods_name","goods_code","price","goods_kind","goods_kind_icon");
		foreach($issuegoods as $key => $tmp) if($tmp['goods_seq']) $arrGoodsSeq[] =  $tmp['goods_seq'];
		if(count($arrGoodsSeq) > 0){
			$goods = $this->get_goods_list($arrGoodsSeq,'thumbView');
			foreach($issuegoods as $key => $data){
				foreach($_field_list as $_key) $issuegoods[$key][$_key] = $goods[$data['goods_seq']][$_key];
			}
		}

		return $issuegoods;
	}

	/**
	 * 상품의 옵션 데이터를 가져온다.
	 * @param array $goods_seqs
	 */
	public function get_goods_option_by_goods_seqs($goods_seqs)
	{
		$query = $this->db->select("goods_seq,option_seq,option1,option2,option3,option4,option5,price");
		$query = $query->from("fm_goods_option")->where_in("goods_seq", $goods_seqs)->get();
		$result = $query->result_array();
		return $result;
	}

	// 최근 연결 카테고리/브랜드/지역
	public function get_last_category_link_list($sc){

		$limitStr =" LIMIT {$sc['page']}, {$sc['perpage']} ";

		$sql = array();

		if($sc['categoryType'] == "brand"){
			$this->load->model('brandmodel');
			$sql['table'] = "fm_brand_link";
			$sql['field'] = "category_code";
			$sql['groupby'] = "category_code";
			$sql['orderby'] = "MAX( category_link_seq ) DESC";
		}elseif($sc['categoryType'] == "location"){
			$this->load->model('locationmodel');
			$sql['table'] = "fm_location_link";
			$sql['field'] = "location_code AS category_code";
			$sql['groupby'] = "location_code";
			$sql['orderby'] = "MAX( location_link_seq ) DESC";
		}else{
			$this->load->model('categorymodel');
			$sql['table'] = "fm_category_link";
			$sql['field'] = "category_code";
			$sql['groupby'] = "category_code";
			$sql['orderby'] = "MAX( category_link_seq ) DESC";
		}
		$sc['debug'] = 1;
		$sql['limit']	= $limitStr;

		$result = pagingNumbering($sql,$sc);

		$return 	= array("page"=>$result['page']);
		foreach($result['record'] as $row){
			if($sc['categoryType'] == "brand"){
				$row['title'] =  $this->brandmodel->get_brand_name($row['category_code']);
			}elseif($sc['categoryType'] == "location"){
				$row['title'] =  $this->locationmodel->get_location_name($row['category_code']);
			}else{
				$row['title'] =  $this->categorymodel->get_category_name($row['category_code']);
			}
			if(trim($row['title'])) $return['record'][] = $row;
		}

		return $return;

	}

	// 관련상품, 판매자 인기 상품 선택 가져오기
	public function get_goods_relation($goods_seq, $relation_type='relation'){

		$this->load->model('providermodel');

		$this->db->select("r.*, g.goods_seq, g.goods_name, o.price, g.provider_seq");
		if($relation_type == "relation_seller"){
			$this->db->from("fm_goods_relation_seller AS r");
		}else{
			$this->db->from("fm_goods_relation AS r");
		}
		$this->db->join("fm_goods AS g","r.relation_goods_seq = g.goods_seq");
		$this->db->join("fm_goods_option AS o","g.goods_seq = o.goods_seq AND o.default_option = 'y'");
		$query = $this->db->where("r.goods_seq",$goods_seq);
		$query = $this->db->get();
		$provider_list 	= array();
		$relation 		= array();
		foreach ($query->result_array() as $row){
			if(serviceLimit('H_AD')){
				if($row['provider_seq'] == 1){
					$row['provider_name'] = "본사";
				}else{
					if(!$provider_list[$row['provider_seq']]){
						$provider = $this->providermodel->get_provider_one($row['provider_seq']);
						$row['provider_name'] = $provider['provider_name'];
						$provider_list[$row['provider_seq']] = $provider['provider_name'];
					}else{
						$row['provider_name'] = $provider_list[$row['provider_seq']];
					}
				}
			}

			$relation[] = $row;
		}
		return $relation;
	}

	/*
	 상품 등록(일반/패키지/티켓) 항목 별 열림/닫힘 기본 값
	 2020.08.11 pjm
	*/
	/*
		상품 등록 시 항목별 box 열기 고정 저장
	*/
	public function set_registbox_fixing($mode='goods',$bxopenfixing = array()){
		if($mode && $bxopenfixing){
			config_save('bxOpenFixing', array($mode => json_encode($bxopenfixing)));
		}
	}

	/*
	 상품 등록(일반/패키지/티켓) 항목 별 열림/닫힘 기본 값
	 2020.08.11 pjm
	  - 기본 설정 값 : OPEN/CLOSE 열기고정 여부
	  - 열기고정 옵션 제공 여부 : true/false
	*/
	public function goods_regist_bxopen_default($goodsKind='goods'){
		if($goodsKind == "social"){
			$_list = array(
						'provider'				=> array('OPEN',false)
						,'category'				=> array('OPEN',false)
						,'brand'				=> array('CLOSE',true)
						,'info'					=> array('OPEN',false)
						,'social_info'			=> array('OPEN',false)
						,'social_refund'		=> array('OPEN',false)
						,'sales_info'			=> array('OPEN',false)
						,'options'				=> array('OPEN',false)
						,'inputoptions'			=> array('CLOSE',true)
						,'photo'				=> array('OPEN',false)
						,'contents'				=> array('OPEN',false)
						,'common_contents'		=> array('CLOSE',true)
						,'events'				=> array('OPEN',false)
						,'etc_info'				=> array('CLOSE',true)
						,'bigdata'				=> array('CLOSE',true)
						,'openmarket'			=> array('CLOSE',true)
						,'marketing'			=> array('OPEN',true)
						,'video'				=> array('CLOSE',true)
						,'qrcode'				=> array('CLOSE',true)
						,'memo'					=> array('OPEN',true)
						,'history'				=> array('CLOSE',true)
			);
		}elseif($goodsKind == "gift"){
			$_list = array(
						'provider'				=> array('OPEN',false)
						,'info'					=> array('OPEN',false)
						,'photo'				=> array('OPEN',false)
						,'etc_info'				=> array('CLOSE',true)
						,'memo'					=> array('OPEN',true)
						,'history'				=> array('CLOSE',true)
			);
		}else {

			$_list = array(
				'provider'				=> array('OPEN',false)
				,'category'				=> array('OPEN',false)
				,'brand'				=> array('CLOSE',true)
				,'info'					=> array('OPEN',false)
				,'sales_info'			=> array('OPEN',false)
				,'options'				=> array('OPEN',false)
				,'suboptions'			=> array('CLOSE',true)
				,'inputoptions'			=> array('CLOSE',true)
				,'photo'				=> array('OPEN',false)
				,'contents'				=> array('OPEN',false)
				,'common_contents'		=> array('CLOSE',true)
				,'shipping'				=> array('OPEN',false)
				,'events'				=> array('OPEN',false)
				,'etc_info'				=> array('CLOSE',true)
				,'bigdata'				=> array('CLOSE',true)
				,'openmarket'			=> array('CLOSE',true)
				,'marketing'			=> array('OPEN',true)
				,'video'				=> array('CLOSE',true)
				,'qrcode'				=> array('CLOSE',true)
				,'memo'					=> array('OPEN',true)
				,'history'				=> array('CLOSE',true)
			);
		}

		return $_list;
	}

	// 최근 매입처 가져오기
	function get_supplier_name($goods_seq){

		$this->db->select("c.supplier_name");
		$this->db->from("fm_stock_history_item AS a");
		$this->db->join("fm_stock_history AS b","a.stock_code = b.stock_code","inner");
		$this->db->join("fm_supplier AS c","b.supplier_seq = c.supplier_seq","inner");
		$this->db->where(" a.goods_seq",$goods_seq);
		$this->db->order_by("b.stock_date","desc");
		$this->db->order_by("b.regist_date","desc");
		$query 			= $this->db->limit(1)->get();
		$tmp		 	= $query->row_array();
		$supplier_name 	= $tmp['supplier_name'];

		return $supplier_name;
	}

	function get_goodsaddinfo($gdtype='goodsaddinfo', $wheres=array())	{

		$this->db->where("label_type",$gdtype);
		if($gdtype == 'goodsaddinfo' && !$wheres){
			$this->db->where("base_type !=","1");
		}
		if($wheres){
			foreach($wheres as $_val) $this->db->where($_val);
		}
		$this->db->order_by("label_type");
		$this->db->order_by("sort_seq");
		$codequery 	= $this->db->get("fm_goods_code_form");
		$code_arr 	= $codequery -> result_array();

		return $code_arr;
	}

	// 상품 공통 정보 최대 갯수 가져오기
	function get_max_count_common_info($info_provider_seq = 1) {

		$this->db->select("count(*) count");
		$this->db->from("fm_goods_info");
		$this->db->where("info_provider_seq", $info_provider_seq);
		$this->db->where("info_name !=", "");
		$query 			= $this->db->get();
		$tmp		 	= $query->row_array();
		$count 	= (int) $tmp['count'];
		return $count;
	}

	/**
	 * 네이버페이/카카오톡구매 주문하기 상품 정리
	 */
	function partnerOrderProducts($ship_ini = [], $cart = [])
	{
		$this->load->library('shipping');
		$this->load->library('naverpaylib');
		$this->load->library("sale");
		$this->load->model('categorymodel');
		$this->load->model('naverpaymodel');

		## 유입경로 full url, 유입경로 도메인, 입점마케팅 EP
		$refererDomain 	= $_COOKIE['refererDomain'];
		$referer		= $_COOKIE['shopReferer'];
		$marketplace 	= $_COOKIE['marketplace'];

		// cart list 정리 : 제외 key
		$excpt_opt = array();
		$excpt_opt['shipping']			= "";
		$excpt_opt['cart_suboptions']	= "";
		$excpt_opt['cart_inputs']		= "";
		$excpt_opt['option1']			= "";
		$excpt_opt['option2']			= "";
		$excpt_opt['option3']			= "";
		$excpt_opt['option4']			= "";
		$excpt_opt['option5']			= "";
		$excpt_opt['title1']			= "";
		$excpt_opt['title2']			= "";
		$excpt_opt['title3']			= "";
		$excpt_opt['title4']			= "";
		$excpt_opt['title5']			= "";
		$excpt_opt['tot_ori_price']		= "";
		$excpt_opt['basic_sale']		= "";
		$excpt_opt['event_sale_target'] = "";
		$excpt_opt['event_sale']		= "";
		$excpt_opt['multi_sale']		= "";
		$excpt_opt['event']				= "";
		$excpt_opt['eventEnd']			= "";
		$excpt_opt['ori_price']			= "";
		$excpt_opt['tot_price']			= "";
		// cart list 정리 : 제외 key

		//#22459 2018-09-18 ycg 네이버페이 이벤트 할인 적용 오류 수정
		//----> sale library 적용
		unset($param, $sales);
		$param['npay']					= true;
		$param['cal_type']				= 'list';
		$param['total_price']			= $cart['total'];
		$param['reserve_cfg']			= $cfg_reserve;
		$param['member_seq']			= $this->userInfo['member_seq'];
		$param['group_seq']				= $this->userInfo['group_seq'];
		$this->sale->set_init($param);
		$this->sale->preload_set_config("order");
		//<---- sale library 적용

		$ini_info				= $this->shipping->set_ini($ship_ini);
		$shipping_group_list	= $this->shipping->get_shipping_groupping($cart['list']);

		// 상품별 수량 계산하기
		foreach ($cart['list'] as $row){
			$goods_ea[$row['goods_seq']]	+= $row['ea'];
		}

		$cart_sort		= [];
		foreach($shipping_group_list as $shipping_group_id => $shipping_group){

			foreach($shipping_group['goods'] as $k=>$list){

				# 주문시 선택한 배송정책
				$shipping_set								= $shipping_group['cfg'];
				$shipping_set['shipping_group_id']			= $shipping_group_id;		//새로운 배송정책의 배송그룹
				# 배송그룹 무료배송[묶음배송] 정책 사용시 함께 주문하는 상품 모두 무료.
				$shipping_set['shipping_std_group_free']	= $shipping_group['shipping_std_group_free'];	//기본배송비 무료
				$shipping_set['shipping_add_group_free']	= $shipping_group['shipping_add_group_free'];	//추가배송비 무료

				$goods_seq			= $list['goods_seq'];
				$cart_seq			= $list['cart_seq'];
				$cart_option_seq	= $list['cart_option_seq'];

				// 카테고리정보
				$list['r_category'] = $tmparr2			= array();
				$categorys			= $this->get_goods_category($goods_seq);
				foreach($categorys as $key => $data){
					$tmparr			= $this->categorymodel->split_category($data['category_code']);
					foreach($tmparr as $cate)	$tmparr2[]	= $cate;
				}
				if($tmparr2){
					$tmparr2			= array_values(array_unique($tmparr2));
					$list['r_category']	= $tmparr2;
				}

				//$list['event_seq'] = $list['event']['event_seq'];

				// 옵션 기본 판매가
				$list['default_price'] = floor($this->naverpaymodel->option_default_price($goods_seq));

				$opt_seq		= $list['option_seq'];
				$opt_info		= $this->naverpaymodel->select_option_code(array($goods_seq,$opt_seq));

				//basic,multi 할인은 기본가로 적용됨.
				//할인적용항목 : 기본, 이벤트, 복수구매, 회원, 좋아요, 유입경로, 모바일
				unset($param, $sales);
				$param['npay']					= true;
				$param['npay_sale']				= array('basic','event','multi','member','like','referer','mobile');
				$param['npay_member_group_seq']	= "0";	//비회원 할인 정책 적용
				$param['option_type']			= 'option';
				$param['consumer_price']		= $list['consumer_price'];
				$param['price']					= $list['org_price'];
				$param['sale_price']			= $list['price'];
				$param['ea']					= $list['ea'];
				$param['goods_ea']				= $goods_ea[$goods_seq];
				$param['option_ea']				= $goods_ea[$goods_seq];
				$param['category_code']			= $list['r_category'];
				$param['goods_seq']				= $goods_seq;
				$param['goods']					= $list;

				$this->sale->set_init($param);
				$sales	= $this->sale->calculate_sale_price('order');

				$list['event'] = $this->sale->cfgs['event'];
				$list['event_seq'] = $list['event']['event_seq'];

			// options 따로 정리
				$options = array();
				$options['option_seq']			= $opt_seq;
				$options['cart_seq']			= $list['cart_seq'];
				$options['cart_option_seq']		= $list['cart_option_seq'];
				$options['ea']					= $list['ea'];
				$options['type']				= 'single';
				for($i=1; $i<=5; $i++){
					$options['option'.$i]		= $list['option'.$i];
					$options['title'.$i]		= $list['title'.$i];
					$options['optioncode'.$i]	= $opt_info['optioncode'.$i];
					if($options['title'.$i]) $options['type'] = "multi";
				}

				//기본할인액 합계(multi,basic)
				//이벤트할인액
				//추가할인 적용가(member,like,referer,mobile)
				$sale_price_txt1 = $list['default_price'] - $list['price'];
				$sale_price_txt2 = $list['price'] - $sales['one_result_price'];

				// 할인 부담금, 정산금액 계산 @2019-03-07 pjm
				$list['one_sale_list_event'] = $sales['one_sale_list']['event'];
				$list['pay_price']			 = $sales['one_result_price'];
				$_sales_return = $this->naverpaymodel->get_buy_sales($list);

				//옵션별 할인액(기본판매액 - 총 할인가)
				$default_sales	= $sales['one_result_price'] - $list['default_price'];

				//옵션 추가 비용=최종할인가(기본할인가-추가할인) - 옵션기본판매가
				$options['opt_add_price']		= $default_sales;
				$options['goods_seq']			= $goods_seq;
				$options['goods_code']			= $list['goods_code'];
				$options['consumer_price']		= $list['consumer_price'];
				$options['supply_price']		= $list['supply_price'];

				// 할인 부담금, 정산금액 계산 @2019-03-07 pjm
				$list['one_sale_list_event'] = $sales['one_sale_list']['event'];
				$_sales_return = $this->naverpaymodel->get_buy_sales($list);

				$options['commission_type']				= $_sales_return['commission_type'];
				$options['commission_price']			= $_sales_return['commission_price'];			//정산금액
				$options['commission_rate']				= $_sales_return['commission_rate'];			//정산수수료
				$options['salescost_provider']			= $_sales_return['salescost_provider'];			//입점사 이벤트할인 부담액
				$options['salescost_provider_coupon']	= $_sales_return['salescost_provider_coupon'];	//입점사 쿠폰할인 부담액
				$options['salescost_provider_referer']	= $_sales_return['salescost_provider_referer'];	//입점사 유입경로할인 부담액

				$options['org_price']			= $list['org_price'];		//할인전 판매가
				$options['tot_ori_price']		= $list['tot_ori_price'];	//할인전 판매가 * ea
				$options['ori_price']			= $list['ori_price'];		//할인후 판매가
				$options['tot_price']			= $list['tot_price'];		//할인후 판매가 * ea
				$options['basic_sale']			= $list['basic_sale'];
				$options['event_sale_target']	= $list['event_sale_target'];
				$options['event_sale']			= $sales['sale_list']['event'];
				$options['multi_sale']			= $sales['sale_list']['multi'];
				$options['event']				= $list['event'];
				$options['event_seq']			= $list['event_seq'];
				$options['eventEnd']			= $list['eventEnd'];
				$options['inputs']				= $list['cart_inputs'];
				$options['member_sale']			= $sales['one_sale_list']['member'];	//수량별할인
				$options['like_sale']			= $sales['sale_list']['like'];			//할인*수량
				$options['referer_sale']		= $sales['sale_list']['referer'];
				$options['mobile_sale']			= $sales['sale_list']['mobile'];

				$options['goods_price']			= $list['price'];		//할인미적용 판매가(ori_price / ori_price 변질)
				$options['original_price']		= $sales['one_after_price']['original'];	//할인미적용 정가
				$options['sale_price'] 			= $list['sale_price'];		//할인가격(개당)
				$options['referer_sale_unit']	= $sales['one_sale_list']['referer'];//유입경로할인(개당)
				$options['mobile_sale_unit']	= $sales['one_sale_list']['mobile'];//모바일할인(개당)

				$options['referer_domain']		= $refererDomain;
				$options['referer']				= $referer;
				$options['shipping_store_seq']	= $list['shipping_store_seq'];		# 수령매장 정보
				$options['shipping_charge']		= $shipping_group['shipping_charge'];	# 배송비 수수료
				$options['return_shipping_charge']	= $shipping_group['return_shipping_charge'];	# 반품배송비 수수료
				$options['marketplace']			= $marketplace;
				$options['bs_seq']				= $list['bs_seq'];		// 라이브 방송 seq
				$options['bs_type']				= $list['bs_type'];		// 라이브 방송 type

				$suboptions = $list['cart_suboptions'];

				if($old_goods_seq != $goods_seq) $sub_sort[$goods_seq] = 0;

				$this->sale->reset_init();
				//<---- sale library 적용
				//기본할인, 멤버할인만 적용됨.
				foreach($suboptions as $sub_k=> $subdata){

					unset($subdata['event']);

					$sub_info = $this->naverpaymodel->select_suboption_code(array($goods_seq,$subdata['suboption_seq']));
					$subdata['goods_code']			= $list['goods_code'];
					$subdata['optioncode']			= $sub_info['suboption_code'];

					//----> sale library 적용 ( 정가기준 목록에서는 sale_price를 넘기지 않음 )
					unset($param, $sales);
					$param['option_type']			= 'suboption';
					$param['sub_sale']				= $subdata['sub_sale'];
					$param['consumer_price']		= $subdata['consumer_price'];
					$param['price']					= $subdata['price'];
					$param['sale_price']			= $subdata['price'];
					$param['ea']					= $subdata['ea'];
					$param['goods_ea']				= '';
					$param['category_code']			= $list['r_category'];
					$param['goods_seq']				= $goods_seq;
					$param['goods']					= $list;
					$this->sale->set_init($param);
					$sales								= $this->sale->calculate_sale_price('order');

					// 할인 부담금, 정산금액 계산 @2019-03-07 pjm
					$subdata['one_sale_list_event'] = $sales['one_sale_list']['event'];
					$subdata['provider_seq']		= $list['provider_seq'];
					$_sales_return = $this->naverpaymodel->get_buy_sales($subdata);

					$subdata['commission_type']				= $_sales_return['commission_type'];
					$subdata['commission_price']			= $_sales_return['commission_price'];			//정산금액 (구)정산금액저장, (신)정산금액은 수집 시 재계산
					$subdata['commission_rate']				= $_sales_return['commission_rate'];			//정산수수료
					$subdata['salescost_provider']			= $_sales_return['salescost_provider'];			//입점사 이벤트할인 부담액
					$subdata['salescost_provider_coupon']	= $_sales_return['salescost_provider_coupon'];	//입점사 쿠폰할인 부담액
					$subdata['salescost_provider_referer']	= $_sales_return['salescost_provider_referer'];	//입점사 유입경로할인 부담액

					$subdata['option_seq']					= $opt_seq;
					$subdata['original_price']				= $sales['one_sale_list']['original'];
					$subdata['basic_sale']					= $sales['one_sale_list']['basic'];
					$subdata['member_sale']					= $sales['one_sale_list']['member'];
					$subdata['referer_domain']				= $refererDomain;
					$subdata['referer']						= $referer;
					$subdata['shipping_charge']				= $shipping_group['shipping_charge'];	# 배송비 수수료
					$subdata['return_shipping_charge']		= $shipping_group['return_shipping_charge'];	# 반품배송비 수수료
					$subdata['marketplace']					= $marketplace;

					$suboptions[$sub_k] = $subdata;
				}

				$old_goods_seq = $goods_seq;

				$list = array_diff_key($list,$excpt_opt);

				$images = $this->get_goods_image($goods_seq);
				$list['images'] = $images[1]['list1'];

				if(!$cart_sort[$cart_seq][$goods_seq][$cart_option_seq]){
					//$list['ea']			= $goods[$goods_seq]['ea'];
					$cart_sort[$cart_seq][$goods_seq][$cart_option_seq] = $list;
					//개별배송비 합
					$cart_sort[$cart_seq][$goods_seq][$cart_option_seq]['goods_shipping'] = $cart['shipping_group_price'][$list['shipping_group']]['goods'];
				}

				$cart_sort[$cart_seq][$goods_seq][$cart_option_seq]['options'][]		= $options;
				$cart_sort[$cart_seq][$goods_seq][$cart_option_seq]['shipping_set']		= $shipping_set;

				foreach($suboptions as $sub_k=> $subdata){
					$cart_sort[$cart_seq][$goods_seq][$cart_option_seq]['suboptions'][]	= $subdata;
				}

			}

		}

		return $cart_sort;
	}

}

/* End of file goods.php */
/* Location: ./app/models/goods.php */
