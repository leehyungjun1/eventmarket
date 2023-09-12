<?php
if ( ! defined('BASEPATH')) exit('No direct script access allowed');
/**
 * 파트너 주문(API 주문) - 카카오페이 구매 / 네이버페이
 * 2021-04-07
 * by hyem
 */
class partnerlib
{
	var $session_id		= '';
	var $mktime			= '';
	var $culture		= 'false';
	var $return_url		= '';
	var $market			= '';
	var $baseLogDetail  = '';
	var $baseLogParams	= array();

	function __construct() {
		$this->CI =& get_instance();
	}

    function init($market = null) {
        $this->session_id   = session_id();
        $this->mktime      = mktime();
        if ($market == null) {
			$this->order_except_msg('주문가능한 마켓 정보가 없습니다.',140);
        }
        $this->market       = $market;
        if ( $this->market == "talkbuy") {
            $this->marketname = "카카오톡구매";
        }else{
            $this->marketname = "네이버페이";
        }

		/*
		mallApiHost https://talkbuy-test.firstmall.kr
		orderSheetCreatePath /orders/sheets-create
		*/
    }

    /* 
    카카오톡구매
    주문하기
    */
    function getPartnerSettingInfo($service='', $goods=[], $marketing_admin='', $mode='goods'){
		// 네이버 체크아웃(기본통화가 원화 일때만)
		if( $this->CI->config_system['basic_currency'] != "KRW" || empty($service)) {
			return false;
		}
		
		if(($mode == 'goods' && $goods['goods_status'] == 'normal' &&  !$goods['string_price_use'] ) || ($mode == 'cart')) {

			if ($service === "talkbuy")  { 
				$market_field = 'talkbuy';
				$setConfig = getTalkbuyConfig();
			} else {
				$market_field = 'npay';
				$setConfig = getNaverpayConfig();
			}

            if(
                strtolower($setConfig['use']) == 'y'											// "사용모드"일때
                ||	(strtolower($setConfig['use']) == 'test' && $this->CI->managerInfo)					// "테스트모드"이고 관리자아이디일때
                ||	(strtolower($setConfig['use']) == 'test' && $marketing_admin=='nbp') // "테스트모드"이고 회원아이디 gabia일때
            ){
				$return = ['setConfig' => $setConfig, 'use_postpaid' => 0];
				// 비과세 상품 버튼 비노출
				// if($goods['tax'] == 'exempt') $expectGoodsChk = true;

				// 착불배송 사용여부
				$this->CI->load->model('Providershipping');
				$tmp_shipping = $this->CI->Providershipping->get_provider_shipping($goods['provider_seq']);
				$able_shipping_method = array_keys($tmp_shipping['shipping_method']);
				if(in_array('postpaid',$able_shipping_method)){
					$return['use_postpaid'] = 1;
				}

				# 네이버페이 구매 불가 체크
				if($mode == "goods") {
					$not_buy_chk		= $this->partner_not_buy_check($goods,'','','',$setConfig);
					$not_buy_pay		= $not_buy_chk['not_buy_pay'];
					$not_buy_msg		= $not_buy_chk['not_buy_msg'];
					$expectCategoryChk	= $not_buy_chk['expectCategoryChk'];
					$expectGoodsChk		= $not_buy_chk['expectGoodsChk'];
				}

				if(!($this->CI->fammerceMode || $this->CI->storefammerceMode) && $not_buy_pay == false ){

					$this->CI->template->template_dir = BASEPATH."../partner";
					$this->CI->template->compile_dir	= BASEPATH."../_compile/";

					//$this->CI->template->assign(array('navercheckout'=>$setConfig));

					// 네이버페이 구매가능한 배송그룹만 버튼노출되도록 수정 2018-05-23
					// 네이버페이 api 2.1 상품/주문연동
					// 카카오톡구매
					if($this->isShowPartnerButton($service, $setConfig)) {

						$return['use'] = 1;
						
						// mallApiHost debug 여부 치환
						if($setConfig['debug'] == 'y') {
							$setConfig["mallApiHost"] = $setConfig["mallApiHostDebug"];
						}
						
						if($this->CI->_is_mobile_agent){
							$setConfig_btn = $setConfig[$market_field.'_btn_mobile_goods'];
							$btnTmp = explode("-",$setConfig_btn);
							// 모바일 버튼에는 M 붙임 -- 카카오톡구매만
							if($service == "talkbuy") {
								$btnTmp[0] .= "M";
							}
							
						}else{
							$setConfig_btn = $setConfig[$market_field.'_btn_pc_goods'];
							$btnTmp = explode("-",$setConfig_btn);
						}
						
						$return['btn'] 			= $btnTmp;
						$return['not_buy_chk'] 	= $not_buy_chk;
						$return['setConfig'] 	= $setConfig;
						return $return;

					}else{
						// navercheckout 1.0 상품연동
						if(!$expectGoodsChk && !$expectCategoryChk){
							$this->CI->template->define(array('navercheckout'=>'navercheckout.html'));
							$navercheckout_tpl = $this->CI->template->fetch('navercheckout');
							$this->CI->template->assign(array('tmptpl'=>$tmptpl));
						}

						return false;
					}
				}
			}
		}
	}

	/**
	 * 네이버페이/카카오톡구매 버튼 노출 여부
	 */
	private function isShowPartnerButton($service='', $setConfig=[]) {
		// 카카오톡구매는 true 리턴
		if ($service == 'talkbuy') {
			return true;
		}

		// 네이버페이 API 2.1 주문형
		if ($service == 'navercheckout' && $setConfig['version'] == '2.1') {
			// 테스트 연동 시 true 리턴
			if (strtolower($setConfig['use']) == 'test') {
				return true;
			}
			// 네이버페이 정책에 따라 노출조건 체크
			if ($setConfig['btnView'] == 'all') {
				return true;
			}
			if ($setConfig['btnView'] == 'nonLogin' && !$this->CI->userInfo) {
				return true;
			}
		}

		return false;
	}

	/**
	 * 주문 불가 체크 goodsmodel에서 이동함
	 */
	public function partner_not_buy_check($goods,$options='',$suboptions='',$inputs='',$setConfig, $market='npay'){

		$not_buy_pay		= false;
		$not_buy_msg		= "";
		$expectCategoryChk	= false;
		$expectGoodsChk		= false;

		$market_name = "네이버페이";
		if($market == "talkbuy")  { 
			$market_name = "카카오톡구매";
		}

		// 전자결제 설정 체크 @author Sunha Ryu 2019-07-04
		if($setConfig['use'] == 'n'){
		    $not_buy_pay = true;
		    $not_buy_msg = "<span class=fx12><span class=red>전자결제 설정에서 ".$market_name."가 미사용 상태입니다.</span></span>";
		}

		// 예외카테고리 체크
		if($setConfig['except_category_code']) $navercategorys    = $this->CI->goodsmodel->get_goods_category($goods['goods_seq']); // 추가
		foreach($setConfig['except_category_code'] as $v1){
			foreach($navercategorys as $v2){
				if($v1['category_code']==$v2['category_code'] || preg_match("/^".$v1['category_code']."/",$v2['category_code'])){
					$expectCategoryChk = true;
					$not_buy_pay	= true;
					$not_buy_msg	= $market_name." <span class=red>주문 예외 카테고리 상품</span>입니다.";
				}
			}
		}

		// 예외상품 체크
		foreach($setConfig['except_goods'] as $v1){
			if($v1['goods_seq'] == $goods['goods_seq']){
				$expectGoodsChk = true;
				$not_buy_pay	= true;
				$not_buy_msg	= $market_name." <span class=red>주문 예외 상품</span>입니다.";
			}
		}

		# 상품 판매상태
		if($goods['goods_status'] && $goods['goods_status'] != "normal"){
			$not_buy_pay	= true;
			$not_buy_msg	= $market_name." <span class=red>주문 불가</span>합니다. 판매상태를 확인해 주세요.";
		}

		# 상품기본가 0원 체크
		if($goods['price'] == 0){
			$not_buy_pay	= true;
			$not_buy_msg	= "<span class=red>기본가가 \'0원\'인 상품</span>은 ".$market_name." 주문이 불가합니다.<br />(".$market_name." 마일리지 적립혜택 불가)";
		}

		# 티켓상품
		if($goods['goods_kind'] == "coupon"){
			$not_buy_pay	= true;
			$not_buy_msg	= "<span class=red>티켓상품</span>은 ".$market_name." 주문이 불가합니다.";
		}

		$string_msg = "<span class=red>제한된 특수문자가 포함</span>되어 ".$market_name." 주문이 불가합니다.<hr></hr><div style=\'width:90%;text-align:left;margin-top:5px;\'><strong>제한된 특수문자</strong> <span class=\'fx11\' style=\'font-family:돋움;\'>(네이버 페이는 띄어쓰기도 인식을 합니다.)</span><br />1. \' / \' (좌우공백포함슬래시)<br />2. \': \'(우측공백포함콜론)</div>";

		# 필수옵션 체크
		$option_titles = array();
		if($options){
			foreach($options as $opt){

				if($opt['title'] && !in_array(trim($opt['title']),$option_titles)){
					$option_titles[] = trim($opt['title']);
				}
				//필수옵션명 또는 옵션값에 '좌우 공백이 포함된 슬래시( / )' or '우측 공백이 포함된 콜론(: )'은 사용 불가
				if(strstr($opt['title']," / ") || strstr($opt['title'],": ") || strstr($opt['option']," / ") || strstr($opt['option'],": ")){
					$not_buy_pay	= true;
					$not_buy_msg	= "\'상품옵션명\' 또는 \'옵션값\'에 ".$string_msg;
				}
				//필수옵션명 길이 제한(20자리(byte아님))-옵션정보(title/value)는 추후 옵션 재고연동시 사용되므로 가공하여 전송불가
				if(mb_strlen($opt['title'],'utf-8') > 20){
					$not_buy_pay	= true;
					$not_buy_msg	= $market_name." 주문의 \'상품옵션명\' 길이는 최대 20자까지 입니다.";
				}
				//필수옵션값 길이 제한(50자리(byte아님))-옵션정보(title/value)는 추후 옵션 재고연동시 사용되므로 가공하여 전송불가
				if(mb_strlen($opt['option'],'utf-8') > 50){
					$not_buy_pay	= true;
					$not_buy_msg	= $market_name." 주문시 \'상품옵션값\' 길이는 최대 50자까지 입니다.";
				}
			}
		}

		# 추가옵션 체크
		if($suboptions){
			if(!$suboptions[0]['goods_seq']){ $suboptions = $suboptions[0]; }
			foreach($suboptions as $opt){
				//추가옵션값에 '좌우 공백이 포함된 슬래시( / )' or '콜론(:)'은 사용 불가
				//추가옵션 타이틀은 넘기지 않으므로 특수 문자 및 자릿수 체크 불필요
				if(strstr($opt['suboption']," / ") || strstr($opt['suboption'],": ")){
					$not_buy_pay	= true;
					$not_buy_msg	= "\'추가구성옵션값\'에 ".$string_msg;
				}
				//추가옵션명 길이 제한(20자리(byte아님))-옵션정보(title/value)는 추후 옵션 재고연동시 사용되므로 가공하여 전송불가
				if(mb_strlen($opt['suboption'],'utf-8') > 50){
					$not_buy_pay	= true;
					$not_buy_msg	= $market_name." 주문의 \'추가구성옵션값\' 길이는 최대 50자까지 입니다.";
				}
			}
		}

		# 입력옵션 체크
		if($inputs){
			foreach($inputs as $inp){
				// 첨부파일 업로드 형식은 사용 불가
				if($inp['input_form'] == "file" || $inp['type'] == "file"){
				    $not_buy_pay	= true;
				    $not_buy_msg	= "<span class=red>첨부파일 업로드가 필요한 상품</span>은 ".$market_name." 주문이 불가합니다.";
				}else{
					// 필수옵션명과 입력옵션명이 동일하면 네이버페이 주문 불가
					if($option_titles && ($inp['input_title'] || $inp['input_name'])){
						if(in_array(trim($inp['input_title']),$option_titles) || in_array(trim($inp['input_name']),$option_titles)){
							$not_buy_pay	= true;
							$not_buy_msg	= "<span class=red>\'상품옵션명\'과 \'입력옵션명\'이 동일한 상품</span>은 ".$market_name." 주문이 불가합니다.";
						}
					}
					//입력옵션명 또는 입력옵션값에 '좌우 공백이 포함된 슬래시( / )' or '우측 공백이 포함된 콜론(: )'은 사용 불가
					if(strstr($inp['input_title']," / ") || strstr($inp['input_title'],": ") || strstr($inp['input_value']," / ") || strstr($inp['input_value'],": ")){
						$not_buy_pay	= true;
						$not_buy_msg	= "\'입력옵션명\' 또는 \'입력값\'에 ".$string_msg;
					}
					//입력옵션명(타이틀) 길이 제한(20자리(byte아님))-옵션 title명은 추후 옵션 재고연동시 사용되므로 가공 불가
					if(mb_strlen($inp['input_title'],'utf-8') > 20){
						$not_buy_pay	= true;
						$not_buy_msg	= "".$market_name." 주문의 \'입력옵션명\' 길이는 최대 20자까지 입니다.";
					}
				}
			}
		}

		// 배송그룹의 선택한 배송방법이 네이버페이 주문 가능 여부 체크 @author Sunha Ryu 2019-07-04
		if(!empty($goods['shipping_set_seq'])) {
			$shipping_set = $this->CI->shippingmodel->get_shipping_set($goods['shipping_set_seq'], 'shipping_set_seq');
			if($shipping_set['npay_order_possible'] === 'N' && $market == "npay") {
				$not_buy_pay = true;
				$not_buy_msg = "<span class=fx12>";
				$not_buy_msg .= "<span class=red>선택한 배송방법은 ".$market_name." 주문이 불가합니다.";
				if($shipping_set['npay_order_impossible_msg']) $not_buy_msg .= "-".$shipping_set['npay_order_impossible_msg'];
				$not_buy_msg .= "</span></span>";
			}
			if($shipping_set['talkbuy_order_possible'] === 'N' && $market == "talkbuy") {
				$not_buy_pay = true;
				$not_buy_msg = "<span class=fx12>";
				$not_buy_msg .= "<span class=red>선택한 배송방법은 ".$market_name." 주문이 불가합니다.";
				if($shipping_set['npay_order_impossible_msg']) $not_buy_msg .= "-".$shipping_set['npay_order_impossible_msg'];
				$not_buy_msg .= "</span></span>";
			}
		}

		$return = array('not_buy_pay'			=> $not_buy_pay,
						'not_buy_msg'			=> $not_buy_msg,
						'expectCategoryChk'		=> $expectCategoryChk,
						'expectGoodsChk'		=> $expectGoodsChk,
						);
		return $return;

	}

	function order_except_msg($_except_msg,$h=""){

		$_except_msg_strlen = mb_strlen(strip_tags($_except_msg));

		if($h=="") {
			if($_except_msg_strlen > 200) $h = 235;
			elseif($_except_msg_strlen > 150) $h = 210;
			elseif($_except_msg_strlen > 110) $h = 190;
			elseif($_except_msg_strlen > 70) $h = 180;
			elseif($_except_msg_strlen > 30) $h = 160;
			else $h = 140;
		}

        if ($this->market == "talkbuy") {
            echo json_encode(['result' => 'error', 'message' => $_except_msg]);
        } else {
        	openDialogAlert($_except_msg, 450, $h, 'parent', '');
        }
		exit;

	}

    
    function getPartnerOrderCart($goodsSeq=null, $cart_option_seq=null) {
		$this->CI->load->model('cartmodel');
		# 선택한 상품만 골라내기
		$cart_list = array();
		if(!$goodsSeq){
		    $cart = $this->CI->cartmodel->catalog("", null, 'saleprice', $cart_option_seq);
		}else{
		    $cart = $this->CI->cartmodel->catalog("", null, 'saleprice');
		}

		if(count($cart['list']) < 1){
			//네이버 페이로 주문할 상품을 먼저 선택해 주세요.
			$this->order_except_msg(getAlert('os219', $this->marketname),140);
		}

        return $cart;
    }

	/**
	 * 네이버페이/카카오톡구매 주문하기 상품 제고체크
  	*/
	function partnerOrderStockCheck($cart){

		/**** 재고 체크 및 최대/최소 구매수량 체크 ****/
		foreach($cart['data_goods'] as $goods_seq => $data){
		    
		    $goods_name_strlen	= mb_strlen($data['goods_name']);
		    
		    if($goods_name_strlen > 15) $alert_h = 160;
		    elseif($goods_name_strlen > 50) $alert_h = 175;
		    elseif($goods_name_strlen > 100) $alert_h = 195;
		    else $alert_h = 140;
		    
		    // 구매수량 체크
		    if($data['option_ea']){
		        $opteEa = $data['option_ea'];
		    }else{
		        $opteEa = $data['ea'];
		    }
		    if($data['min_purchase_ea'] && $data['min_purchase_ea'] > $opteEa){
		        // $data['goods_name']은 $data['min_purchase_ea']개 이상 구매하셔야 합니다.
				$this->order_except_msg(addslashes(getAlert('oc022',array(addslashes($data['goods_name']),$data['min_purchase_ea']))),$alert_h);
		        exit;
		    }
		    if($data['max_purchase_ea'] && $data['max_purchase_ea'] < $opteEa){
		        // $data['goods_name']은 $data['max_purchase_ea']개 이상 구매하실 수 없습니다.
				$this->order_except_msg(addslashes(getAlert('oc023',array(addslashes($data['goods_name']),($data['max_purchase_ea']+1)))),$alert_h);
		        exit;
		    }
		    
		    // 단독이벤트만 판매시 이벤트기간이 아니면 판매중지
		    if( $data['event']['event_goodsStatus'] === true ){
		        // 재고품절 이유로 구매불가한 아래의 상품은 제외되었습니다.%b다시 확인해 주세요.%b -
		        $err_msg	= getAlert('oc014');
		        $err_msg	.= addslashes($data['goods_name']);
				$this->order_except_msg($err_msg, 140);
		        exit;
		    }
		    
		    if($data['ea_for_option']) {
		        foreach($data['ea_for_option'] as $option_key => $option_ea){
		            $option_r = explode(' ^^ ',$option_key);
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
		                
		                // 재고품절 이유로 구매불가한 아래의 상품은 제외되었습니다.%b다시 확인해 주세요.%b -
		                $err_msg	= getAlert('oc019');
		                $err_msg	.= addslashes($data['goods_name']);
		                $err_msg	.= addslashes($opttitle);
						$this->order_except_msg($err_msg, 140);
		                exit;
		            }
		        }
		    }
		    
		    if($data['ea_for_suboption']) {
		        foreach($data['ea_for_suboption'] as $option_key => $option_ea){
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
		            
		            if	(!$chk || $chk['stock'] < 0 ){
		                // 필수 추가구성옵션 $option_r[0]을(를) 구매할(재고부족) 수 없습니다.
						$this->order_except_msg(getAlert('oc020',$option_r[0]), 140);
		                exit;
		            }
		        }
		    }
		}
		/* **************************************************** */
	}


    function partnerOrderStatusCheck($cfg, $cart_list){

		// 도서공연비소득공제 설정 n 이면 false(기본)
		// 도서공연비소득공제 설정 all 이면 true
		if($cfg['culture'] == 'all') $this->culture = 'true';

		$cultureY = $cultureN = 0;
		$cultureGoods = array();
		foreach($cfg['culture_goods'] as $v1){
			$culture_goods[] = $v1['goods_seq'];
		}

		# ------------------------------------------------------------------------------------------------------------
		# 네이버페이/카카오톡구매 주문가능 상태 체크 시
		foreach ($cart_list as $row){

			$selected_goods = false;	//선택한 상품 여부
			$_except		= false;	//주문불가여부
			$_except_msg	= "";
			$alert_h		= 170;

			$return_cart_seq[]				= $row['cart_seq'];
			$goods_ea[$row['goods_seq']]	+= $row['ea'];

			//if($selected_goods) $select_goods_cnt++;	//총 선택상품 갯수

			# 네이버페이 주문 불가 상품 체크 시작
			//if($selected_goods){

			//선택한 옵션의 주문 불가 체크
			$options		= array();
			for($i=1; $i <= 5; $i++){
				if($row["option".$i]) $options[] = array("title" => $row["title".$i],"option" => $row["option".$i]);
			}

			$suboptions		= $row['cart_suboptions'];
			$inputs			= $row['cart_inputs'];
			$not_buy_chk	= $this->partner_not_buy_check($row, $options, $suboptions, $inputs, $cfg, $this->market);
			$_except		= $not_buy_chk['not_buy_pay'];
			$_except_msg	= $not_buy_chk['not_buy_msg'];

			if($_except == true){

				if(!$goodsSeq){
					$_except_msg = "<span class=fx12>[상품명: ".$row['goods_name']."]<br /><span class=red>".$_except_msg."</span></span>";
				}
				$this->order_except_msg($_except_msg);
			}
			//}
			# 네이버페이 주문 불가 상품 체크 종료.
			if($cfg['culture'] == 'choice') {
				if(in_array($row['goods_seq'], $culture_goods)) {
					$cultureY +=1;
					$cultureGoods[] = $row['goods_name'];
				} else {
					$cultureN +=1;
				}
			}
		}

		if($cfg['culture'] == 'choice') {
			if($cultureY>0 && $cultureN>0) {
				$_except_msg = "도서공연비 소득공제 대상 상품과 비대상 상품은<br/>함께 결제가 불가능합니다.<br/><br/>[도서공연비 소득공제 대상 상품]<br/>";
				foreach($cultureGoods as $val) {
					$_except_msg .= $val."<br/>";
				}
				$this->order_except_msg($_except_msg,200);
			} else if($cultureY>0) {
				$this->culture = 'true';
			}
		}
		# 네이버페이/카카오톡구매 주문가능 상태 체크 종료
		# ------------------------------------------------------------------------------------------------------------
    }

	// partner_order_detail 주문 update 체크
	function getPartnerOrder($params) {	
		if(empty($params)) {
			return false;
		}
		
		$this->CI->load->model('partnerordermodel');
		$result = $this->CI->partnerordermodel->getPartnerOrder($params);
		return $result;
	}

	// partner_order_detail 주문 update 
	function setPartnerOrder($partner_order_seq, $partner_order_pk) {	
		if(empty($partner_order_seq)) {
			return false;
		}
		
		$this->CI->load->model('partnerordermodel');
		$result = $this->CI->partnerordermodel->setPartnerOrderSeq($partner_order_seq, $partner_order_pk);
		return $result;
	}

	// fm_order 검색
	function getOrder($partner_order_seq, $partner_id) {
		if(!$this->CI->load->library('orderlibrary')) $this->CI->load->library('orderlibrary');
		$params = array();
		list($order_field, $order_product_field) = $this->getOrderField($partner_id);
		$params = array(
			$order_field => $partner_order_seq,
		);
		$result = $this->CI->orderlibrary->get_order($params);
		return $result;
	}

	function getLastOrderByOrinOrderSeq($top_orign_order_seq, $partner_order_seq, $partner_id) {
		if(!$this->CI->load->library('orderlibrary')) $this->CI->load->library('orderlibrary');
		$params = array();
		list($order_field, $order_product_field) = $this->getOrderField($partner_id);
		$params = array(
			"top_orign_order_seq" => $top_orign_order_seq,
			"step" => 75,
			$order_field => $partner_order_seq,
		);
		$result = $this->CI->orderlibrary->get_last_order_by_top_orign($params);
		return $result;
	}

	// fm_order_shipping 검색
	function getOrderShipping($in, $packagenumber, $partner_id) {
		if(!$this->CI->load->model('ordermodel')) $this->CI->load->model('ordermodel');

		list($order_field, $order_product_field, $package_field) = $this->getOrderField($partner_id);

		$params = array();
		$params = array(
			$package_field => $packagenumber
		);
		$result = $this->CI->ordermodel->get_order_shipping($in['order_seq'],null,null,$params);
		return $result;
	}

	// 주문 item 검색
	function getOrderItem($in, $partner_id) {
		if(!$this->CI->load->model('ordermodel')) $this->CI->load->model('ordermodel');
		list($order_field, $order_product_field) = $this->getOrderField($partner_id);
		$params = array(
			"order_seq" => $in['order_seq'],
			"shipping_seq" => $in['shipping_seq'],
			"goods_seq" => $in['goods_seq'],
			$order_field => $in['orderId'],
		);
		$result = $this->CI->ordermodel->get_data_item($params);
		return $result->row_array();
	}

	// 주문 item option 검색
	function getOrderItemOption($in, $partner_id) {
		if(!$this->CI->load->model('ordermodel')) $this->CI->load->model('ordermodel');
		list($order_field, $order_product_field, $order_packagenumber_field) = $this->getOrderField($partner_id);
		$params = array(
			"order_seq" => $in['order_seq'],
			"shipping_seq" => $in['shipping_seq'],
			"item_seq" => $in['item_seq'],
			$order_field => $in['orderId'],
			$order_product_field => $in['id'],
			$order_packagenumber_field => $in['packageNumber'],
		);
		$result = $this->CI->ordermodel->get_data_item_option($params);
		return $result->row_array();
	}

	// 주문 item option 검색
	function getLastOrderItemOptionByProductId($in, $partner_id) {
		if(!$this->CI->load->model('ordermodel')) $this->CI->load->model('ordermodel');
		list($order_field, $order_product_field, $order_packagenumber_field) = $this->getOrderField($partner_id);
		$params = array(
			$order_field => $in['orderId'],
			$order_product_field => $in['id'],
			$order_packagenumber_field => $in['packageNumber'],
		);
		$result = $this->CI->ordermodel->get_last_item_option_by_product($params, [$order_product_field, "DESC"]);
		return $result->row_array();
	}

	// 주문 item suboption 검색
	function getOrderItemSubOption($in, $partner_id) {
		if(!$this->CI->load->model('ordermodel')) $this->CI->load->model('ordermodel');
		list($order_field, $order_product_field, $order_packagenumber_field) = $this->getOrderField($partner_id);
		$params = array(
			"order_seq" => $in['order_seq'],
			"item_seq" => $in['item_seq'],
			"item_option_seq" => $in['option_seq'],
			$order_field => $in['orderId'],
			$order_product_field => $in['id'],
			$order_packagenumber_field => $in['packageNumber'],
		);

		$result = $this->CI->ordermodel->get_data_item_suboption($params);
		return $result->row_array();
	}

	// 주문 item suboption 검색
	function getLastOrderItemSubOptionByProductId($in, $partner_id) {
		if(!$this->CI->load->model('ordermodel')) $this->CI->load->model('ordermodel');
		list($order_field, $order_product_field, $order_packagenumber_field) = $this->getOrderField($partner_id);
		$params = array(
			$order_field => $in['orderId'],
			$order_product_field => $in['id'],
			$order_packagenumber_field => $in['packageNumber'],
		);

		$result = $this->CI->ordermodel->get_last_item_suboption_by_product($params, [$order_product_field, "DESC"]);
		return $result->row_array();
	}

	// 주문 item input 검색
	function getOrderItemInput($in, $partner_id) {
		if(!$this->CI->load->model('ordermodel')) $this->CI->load->model('ordermodel');
		list($order_field, $order_product_field, $order_packagenumber_field) = $this->getOrderField($partner_id);
		$params = array(
			"order_seq" 	=> $in['order_seq'],
			"item_seq" 		=> $in['item_seq'],
			"item_option_seq"	=> $in['option_seq'],
			"title"			=> $in['title'],
			"value" 		=> $in['value'],
		);
		$result = $this->CI->ordermodel->get_data_item_input($params);
		return $result->row_array();
	}

	// 주문 export item 검색
	function getExportItem($in, $partner_id) {
		if(!$this->CI->load->model('exportmodel')) $this->CI->load->model('exportmodel');
		list($order_field, $order_product_field, $order_packagenumber_field) = $this->getOrderField($partner_id);
		$params = array(
			"exp.order_seq" 				=> $in['order_seq'],
			"item.".$order_product_field 	=> $in['id'],
		);
		$result = $this->CI->exportmodel->get_data_export_item($params, $in["option_type"]);
		return $result->row_array();
	}
	
	// 주문 refund 검색
	function getRefund($in, $partner_id) {
		if(!$this->CI->load->model('refundmodel')) $this->CI->load->model('refundmodel');
		list($order_field, $order_product_field, $order_packagenumber_field) = $this->getOrderField($partner_id);
		$params = array(
			"refund_code" => $in['refund_code'],
			$order_field => $in['order_id'],
		);
		$result = $this->CI->refundmodel->get_data_refund($params);
		return $result->row_array();
	}

	// 주문 refund item 검색
	function getRefundItem($in, $partner_id) {
		if(!$this->CI->load->model('refundmodel')) $this->CI->load->model('refundmodel');
		list($order_field, $order_product_field, $order_packagenumber_field) = $this->getOrderField($partner_id);
		$params = array(
			"item_seq" => $in["item_seq"],
			$order_product_field => $in['id'],
		);
		$result = $this->CI->refundmodel->get_data_refund_item($params);
		return $result->row_array();
	}

	// 주문 return item 검색
	function getReturnItem($in, $partner_id) {
		if(!$this->CI->load->model('returnmodel')) $this->CI->load->model('returnmodel');
		list($order_field, $order_product_field, $order_packagenumber_field) = $this->getOrderField($partner_id);
		$params = array(
			"item_seq" => $in["item_seq"],
			$order_product_field => $in['id'],
		);
		$result = $this->CI->returnmodel->get_data_return_item($params);
		return $result->row_array();
	}
	
	// 주문 return 검색
	function getReturn($in, $partner_id) {
		if(!$this->CI->load->model('returnmodel')) $this->CI->load->model('returnmodel');
		list($order_field, $order_product_field, $order_packagenumber_field) = $this->getOrderField($partner_id);
		$params = array(
			"return_code" => $in['return_code'],
			"talkbuy_order_id" => $in['order_id'],
		);
		$result = $this->CI->returnmodel->get_data_return($params);
		return $result->row_array();
	}

	// 주문 관련 필드 리턴
	function getOrderField($partner_id){
		$result = array("npay_order_id","npay_product_order_id","npay_packgenumber");

		if($partner_id == "talkbuy") {
			$result = array("talkbuy_order_id","talkbuy_product_order_id","talkbuy_packagenumber");
		}
		return $result;
	}

	// 관리자 주문 로그 view
	function viewOrderLog($logs) {
		foreach($logs as &$log) {
			if($log["add_info"] == "npay") {
				$log["add_info"] = "[네이버페이]";
			} else if($log["add_info"] == "talkbuy") {
				$log["add_info"] = "[카카오페이 구매]";
			}
		}		
		return $logs;
	}

	/**
	 * 사용가능한 배송그룹 로드
	 */
	function possible_partner_shipping_group() {
		if(!$this->CI->load->model('shippingmodel')) $this->CI->load->model('shippingmodel');
		if(!$this->CI->load->model('providermodel')) $this->CI->load->model('providermodel');

		$shipping_group_list = $this->CI->shippingmodel->get_shipping_group_list(null,array('order_by'=>'shipping_provider_seq'));
		$npay_shipping_group = array();
		foreach($shipping_group_list as $group_data){

			$shipping_set_list	= $this->CI->shippingmodel->load_shipping_set_list($group_data['shipping_group_seq']);

			$shipping_group_npay_tmp = $shipping_group_talkbuy_tmp = array();
			$set_npay_tmp = $set_talkbuy_tmp = array();
			foreach($shipping_set_list as $shipping_set_seq => $set_data){

				// 네이버페이에서 사용가능한 배송정책인지 확인.
				if($set_data['npay_order_possible'] == "Y") $set_npay_tmp[]	=$set_data['shipping_set_name'];

				// 카카오페이 사용가능한 배송정책인지 확인.
				if($set_data['talkbuy_order_possible'] == "Y") $set_talkbuy_tmp[]	=$set_data['shipping_set_name'];

			}

			if(count($set_npay_tmp) > 0 || count($set_talkbuy_tmp) > 0) {

				$provider_data = $this->CI->providermodel->get_provider_one($group_data['shipping_provider_seq']);

				if(serviceLimit('H_AD')){
					if($group_data['shipping_provider_seq'] > 1){
						$provider_info = $provider_data['provider_name'];
					}else{
						$provider_info = "[본사]";
					}
				}

				// 연결상품 통계 구하기 :: 2017-02-16 lwh
				$goods_cnt['goods']		= $group_data['target_goods_cnt'];
				$goods_cnt['package']	= $group_data['target_package_cnt'];
				$shipping_group_tmp['rel_goods_cnt']		= $goods_cnt;
				$shipping_group_tmp['shipping_group_seq']	= $group_data['shipping_group_seq'];
				$shipping_group_tmp['provider_shipping_use']= $group_data['provider_shipping_use'];
				$shipping_group_tmp['shipping_provider_seq']= $group_data['shipping_provider_seq'];
				$shipping_group_tmp['provider_info']		= $provider_info;
				$shipping_group_tmp['shipping_group_name']	= $group_data['shipping_group_name'];
				if(count($set_npay_tmp) > 0) {
					$shipping_group_npay_tmp = $shipping_group_tmp;
					$shipping_group_npay_tmp['shipping_set']			= $set_npay_tmp;
				}
				if(count($set_talkbuy_tmp) > 0) {
					$shipping_group_talkbuy_tmp = $shipping_group_tmp;
					$shipping_group_talkbuy_tmp['shipping_set']			= $set_talkbuy_tmp;
				}
			}

			if($shipping_group_npay_tmp){
				$npay_shipping_group[$group_data['shipping_provider_seq']][] = $shipping_group_npay_tmp;
			}
			if($shipping_group_talkbuy_tmp){
				$talkbuy_shipping_group[$group_data['shipping_provider_seq']][] = $shipping_group_talkbuy_tmp;
			}
		}

		return array(
			'npay_shipping' 		=> $npay_shipping_group,
			'talkbuy_shipping' 		=> $talkbuy_shipping_group,
		);
	}

	/**
	 * 환불 삭제 시 출고 준비건 되돌리기
	 */
	function deleteExportReady($order_seq, $product_order_id, $partner_id) {
		list($order_field, $order_product_field, $order_packagenumber_field) = $this->getOrderField($partner_id);

		if(is_array($product_order_id) === false){
			$product_order_id = array($product_order_id);
		}

		$params = array(
			"exp.order_seq" 				=> $order_seq,
			"item.".$order_product_field 	=> $product_order_id,
			"exp.status"					=> "45"
		);
		$export = $this->CI->exportmodel->get_data_export_item($params, "opt");
		if( $export ) {
			// 출고삭제
			$this->CI->delete_export($export['export_code']);

			if($export['delivery_company_code'] && $export['delivery_number']){
				$delivery_info = "(".$export['delivery_company_code'] .":".$export['delivery_number'].")";
			}else{
				$delivery_info = "";
			}
			// 로그
			$log_title		= "출고준비삭제(".$export['export_code'].")";
			$log_message	= "[".implode(',',$product_order_id)."]".$this->baseLogDetail." 주문취소로 인한 출고준비(".$export['export_code'].") 삭제 되었습니다.".$delivery_info;
			$this->CI->ordermodel->set_log($order_seq,'process',$this->baseLogParams["actor"],$log_title,$log_message,'','',$this->baseLogParams["add_info"]);
		}

		// 출고데이터가 없으면 그냥 return
		if(!$export['export_code']) return;

		// 수량 변경 유무
		$isModified = false;

		/**
		 * 주문 상품별 수량 업데이트
		 */
		$params = array(
			"exp.order_seq" 				=> $order_seq,
			"item.".$order_product_field 	=> $product_order_id,
			"exp.status"					=> "45",
			"exp.export_code"				=> $export['export_code']
		);
		$option 	= $this->CI->exportmodel->get_data_export_item($params, "opt");
		$suboption 	= $this->CI->exportmodel->get_data_export_item($params, "sub");
		foreach(array('option', 'suboption') as $prefix) {
			foreach(${$prefix} as $itemRow) {
				// 출고준비 수량만큼 상품준비로 되돌린다.
				$ea = (int) $itemRow['step45'];
				if( $ea > 0 ) {
					$plus = $ea;
					$minus = -1*$ea;
					$this->CI->ordermodel->set_step_ea('45',$minus,$itemRow['option_seq'], $prefix);
					$this->CI->ordermodel->set_step_ea('35',$plus,$itemRow['option_seq'], $prefix);
					$this->CI->ordermodel->set_option_step($itemRow['option_seq'], $prefix);
					$isModified = true;
				}
			}
		}

		if($isModified === true) {
			// 상태 변경
			$this->CI->ordermodel->set_order_step($order_seq);
			$arr_step 	= config_load('step');
			// 로그
			$log_title = '되돌리기 ('.$arr_step['45'].' => '.$arr_step['35'].')';
			$this->CI->ordermodel->set_log($order_seq,'process',$this->baseLogParams["actor"],$log_title,'','','',$this->baseLogParams["add_info"]);
		}
	}

}
