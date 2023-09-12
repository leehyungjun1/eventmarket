<?php
if ( ! defined('BASEPATH')) exit('No direct script access allowed');
/**
 * 출고와 관련된 소스들이 컨트롤러와 모델에 산재되어 있어 향후 병합을 위한 라이브러리 구조 
 * 2018-08-06
 * by hed 
 */
class goodsLibrary
{
	function __construct() {
		$this->CI =& get_instance();
	}

    function _select_goods_data_sc($sc){

        $sc['orderby']	= ($sc['orderby']) ? $sc['orderby']:'goods_seq';
        $sc['sort']		= ($sc['sort']) ? $sc['sort']:'desc_goods_seq';
        //$sc['page']		= (!empty($sc['page'])) ?	intval(($sc['page'] - 1) * $sc['perpage']):0;
        $sc['perpage']	= ($sc['perpage']) ? intval($sc['perpage']):'10';

        $sc['page']			= $sc['page'] > 0 ? $sc['page'] : 0;
        $sc['goods_type']	= 'goods';

        // 패키지 상품 연결 일 경우 일반 상품만 연결 가능.
        if($sc['package']){
            $sc['selectGoodsKind'] = "goods";
        }
        if(!$sc['selectGoodsKind']) $sc['selectGoodsKind'] = 'all';

        if($sc['selectSearchField'])$sc['search_type']	= $sc['selectSearchField'];
        if($sc['selectKeyword'])	$sc['keyword']		= $sc['selectKeyword'];
        if($sc['selectdateGb'])		$sc['date_gb']		= $sc['selectdateGb'];
        if($sc['selectSdate'])		$sc['sdate']		= $sc['selectSdate'];
        if($sc['selectEdate'])		$sc['edate']		= $sc['selectEdate'];

        if($sc['selectCategory1'])	$sc['category1']	= $sc['selectCategory1'];
        if($sc['selectCategory2'])	$sc['category2']	= $sc['selectCategory2'];
        if($sc['selectCategory3'])	$sc['category3']	= $sc['selectCategory3'];
        if($sc['selectCategory4'])	$sc['category4']	= $sc['selectCategory4'];

        if($sc['selectBrand1'])		$sc['brands1']		= $sc['selectBrand1'];
        if($sc['selectBrand2'])		$sc['brands2']		= $sc['selectBrand2'];
        if($sc['selectBrand3'])		$sc['brands3']		= $sc['selectBrand3'];
        if($sc['selectBrand4'])		$sc['brands4']		= $sc['selectBrand4'];

        if($sc['selectLocation1'])	$sc['location1']	= $sc['selectLocation1'];
        if($sc['selectLocation2'])	$sc['location2']	= $sc['selectLocation2'];
        if($sc['selectLocation3'])	$sc['location3']	= $sc['selectLocation3'];
        if($sc['selectLocation4'])	$sc['location4']	= $sc['selectLocation4'];

        if($sc['selectGoodsStatus'])$sc['goodsStatus']	= $sc['selectGoodsStatus'];
        if($sc['selectGoodsView'])	$sc['goodsView'][0]	= $sc['selectGoodsView'];
        if($sc['selectGoodsKind'])	$sc['goodsKind']	= $sc['selectGoodsKind'];
        if($sc['selectEvent'])		$sc['event_seq']	= $sc['selectEvent'];
        if($sc['selectGift'])		$sc['gift_seq']		= $sc['selectGift'];
        if($sc['selectStartPrice'])	$sc['sprice']		= $sc['selectStartPrice'];
        if($sc['selectEndPrice'])	$sc['eprice']		= $sc['selectEndPrice'];

        /*
        if($sc['goodsKind'] == 'all') define("PACKAGEUSE",'all');
        elseif($sc['goodsKind'] == "package") define("PACKAGEUSE",true);
        elseif($sc['goodsKind'] == "coupon") define("SOCIALCPUSE",true);
        */
        
        $sc['price_gb'] = "price";

        if	(is_array($sc['provider_status_reason_type']) && count($sc['provider_status_reason_type']) > 0){
            $sc['provider_status_reason_type'] = $sc['provider_status_reason_type'];
        }

        $bak_goods_addinfo_title			= $sc['goods_addinfo_title'];
        if(preg_match('/^goodsaddinfo_([0-9]+)/',$sc['goods_addinfo'], $temp_info_no)){
            $sc['goods_addinfo_title']	= $sc['goodsaddinfo'][$temp_info_no[1]][0];
            $sc['goods_addinfo_title']		= $sc['goodsaddinfo'][$temp_info_no[1]][0];
        }

        // 배송그룹별 검색
        if($sc['ship_grp_seq']){
            $sc['shipping_group_seq'] = $sc['ship_grp_seq'];
        }

        // 오픈마켓검색
        if($sc['market']){
            $sc['market'] = $sc['market'];
        }
        if($sc['sellerId']){
            $sc['sellerId'] = $sc['sellerId'];
        }

		// 입점사 검색
		if ($sc['provider_seq_selector']) {
			$sc['provider_seq'] = $sc['provider_seq_selector'];
		}

        return $sc;
    }

    function get_bigdata_title($displayKind='bigdata'){
    
		$bigdata_title_arr = array(
			'view' 			=> '해당 상품을 본 다른 고객',
			'cart'			=> '해당 상품을 장바구니에 담은 다른 고객',
			'wish'			=> '해당 상품을 위시리스트에 찜 한 다른 고객',
			'order'			=> '해당 상품을 구매한 다른 고객',
			'review'		=> '해당 상품을 상품 후기 작성한 다른 고객',
		);
		if(!in_array($displayKind,array('bigdata','bigdata_catalog'))){
			$bigdata_title_arr['view'] 		= "해당 고객이 최근 본 상품";
			$bigdata_title_arr['cart'] 		= "해당 고객이 최근 장바구니에 담은 상품";
			$bigdata_title_arr['wish'] 		= "해당 고객이 최근 위시리스트에 찜 한 상품";
			$bigdata_title_arr['restock'] 	= "해당 고객이 최근 재입고 알림 요청한 상품";
			$bigdata_title_arr['search'] 	= "해당 고객이 최근 검색한 결과의 최상위 상품";
			$bigdata_title_arr['order'] 	= "해당 고객이 최근 구매한 상품";
			$bigdata_title_arr['admin'] 	= "관리자 지정 상품";
			unset($bigdata_title_arr['review']);
        }

        return $bigdata_title_arr;
	}
	
	function get_batchmodify($admin='admin') {
		$batch = array();

		$batch['direct']['goods']			= "기본 정보/회원 등급별 할인/과세";
		$batch['direct']['status']			= "판매정보";
		$batch['direct']['goodsetc']		= "코드/무게/재고";
		$batch['direct']['price']			= "가격/마일리지/수수료/옵션노출";
		$batch['direct']['watermark']		= "워터마크";
		$batch['direct']['relation']		= "추천상품";
		$batch['direct']['ep_shipping']		= "입점 마케팅";
		
		$batch['if']['ifgoods']				= "기본 정보/회원 등급별 할인/과세";
		$batch['if']['ifstatus']			= "판매정보";
		$batch['if']['ifgoodsetc']			= "코드/무게/재고";
		$batch['if']['shipping']			= "배송비";
		$batch['if']['ifprice']				= "가격/마일리지/수수료/옵션노출";
		$batch['if']['ifaddinfo']			= "추가정보/상품정보고시";
		$batch['if']['icon']				= "아이콘";
		$batch['if']['hscode']				= "HS CODE";
		$batch['if']['commoninfo']			= "상품 공통 정보";
		$batch['if']['multidiscount']		= "구매수량 할인/최대,최소 구매 수량";
		$batch['if']['ifpay']				= "결제수단, 구매 대상 제한";
		$batch['if']['ifrelation']			= "추천상품";
		$batch['if']['category']			= "카테고리/브랜드/지역";
		$batch['if']['ifep_shipping']		= "입점 마케팅";
        
		// 입점사에 사용가능여부 인 경우에만 노출 :: 2019-09-16 pjw
		if(serviceLimit('H_AD') && $this->CI->config_system['use_membersale_update'] == 'Y'){
            $batch['direct']['membersale']		= "정가/판매가/회원 등급별 할인에 따른 예상 마진";
		}

		// 입점사 항목 제거
		if($admin == 'selleradmin') {
			unset($batch['direct']['watermark']);
			unset($batch['direct']['relation']);
			unset($batch['direct']['ep_shipping']);

			unset($batch['if']['ifaddinfo']);
			unset($batch['if']['ifpay']);
			unset($batch['if']['ifrelation']);
			unset($batch['if']['ifep_shipping']);
			unset($batch['if']['imagehosting']);
		}

		return $batch;
    }
    
    function get_goods_status($goods_status=''){
        
        switch($goods_status){
            case "runout":
                $goods_status_text = "품절";
            break;
            case "unsold":
                $goods_status_text = "판매중지";
            break;
            case "purchasing":
                $goods_status_text = "재고확보중";
            break;
            default:
                $goods_status_text = "정상";
            break;
        }
        return $goods_status_text;
    }

    // 상품 환경 정보
    function get_goods_config($auth,$isAdmin=false,$isSellerAdmin=false,$goodsKind='goods'){
        
		if	(!$this->CI->scm_cfg)	$this->CI->scm_cfg	= config_load('scm');
        $currency_config 		= code_load('currency', $this->CI->config_system['basic_currency']);
        
        $package_yn         = 'n';
        $socialcpuse_flag   = false;
        $gift               = false;
        if($goodsKind == 'package') $package_yn = 'y';
        if($goodsKind == 'coupon') $socialcpuse_flag = true;
        if($goodsKind == 'gift') $gift = true;

		$arr_gl_gooda_config 	= array(
            'auth'					=> $auth,
            'first_goods_date'		=> $this->CI->config_system['first_goods_date'],
            'basic_currency_hangul'	=> $currency_config[0]['value']['hangul'],
            'basic_currency_nation'	=> $currency_config[0]['value']['nation'],
            'scm_cfg_use'			=> $this->CI->scm_cfg['use'],
            'excel_sample_url'		=> get_interface_sample_path('20220621/goodsexcel.sample.xlsx'),
			'isAdmin'				=> $isAdmin,
			'isSellerAdmin'			=> $isSellerAdmin,
			'package_yn'			=> $package_yn,
			'socialcpuse_flag'		=> $socialcpuse_flag,
			'gift'			        => $gift,
        );

        if(serviceLimit('H_AD')){
            $arr_gl_gooda_config['excel_sample_url'] = get_interface_sample_path('20220621/goodsexcel.admin.sample.xlsx');
            $arr_gl_gooda_config['excel_sample_url_seller'] = get_interface_sample_path('20220621/goodsexcel.seller.sample.xlsx');
        }
        return $arr_gl_gooda_config;
    }

	// 필수옵션 가용재고 계산 20210121
	// 상품상세 > 판매상태 > '정상' 항목 클릭 시 호출되어 실제 판매가능한 가용재고 계산함.
	public function check_option_stock($params){

		$this->CI->load->model('goodsmodel');

		$goods_seq 			= $params['goods_seq'];
		$package_yn 		= $params['package_yn'];
		$tmp_seq 			= $params['tmp_seq'];		// 필수옵션 사용함 : 임시 옵션 시퀀스
		$optionUse			= $params['optionUse'];		// 옵션 사용여부
		$inputStock			= $params['inputStock'];			// 일반상품 필수옵션 사용안함 일 때 입력받은 재고 수량
		$inputBadstock		= $params['inputBadstock'];		// 일반상품 필수옵션 사용안함 일 때 입력받은 불량재고 수량
		$runout_type		= $params['runout_type'];			// 선택된 재고에 따른 판매 방법(통합설정/개별설정)

		$options 			= array();
		$goods_type 		= 'normal_multi';
		$totstock 			= 0; 		// 총재고
		$totunUsableStock 	= 0;		// 불량재고 + 출고예약량(주문)
		$totablestock 		= 0;		// 총가용재고(판매가능재고)
		
		if($package_yn == 'y') $package_title = "패키지";

		// 옵션 사용안함 : 연결된 상품옵션번호로 조회
		if(!$optionUse){

			//패키지 상품 
			if($package_yn == 'y'){

				$goods_type				= 'package_single';
				$package_option_seq 	= $params['package_option_seq'];		
				$package_unit_ea		= $params['package_unit_ea'];
				$_options				= array('package_count' => count($package_option_seq));

				foreach($package_option_seq as $k=>$option_seq){
					$option_tmp = $this->CI->goodsmodel->get_package_by_option_seq($option_seq);
					unset($option_tmp['weight'],$option_tmp['package_count']);
					$option_tmp['package_unit_ea'] = $package_unit_ea[0];		//get으로 전달받은 주문당 발송수량 정의
					foreach($option_tmp as $k2 => $v2){
						$option_tmp[$k2.($k+1)] = $v2;
						unset($option_tmp[$k2]);
					}
					$_options = array_merge($_options, $option_tmp);
				}
				$options = array($_options);
			// 일반/티켓 상품
			}else{

				// 일반상품 필수옵션 사용안함 신규등록 시
				// 밑에서 입력받은 재고 수량으로 총재고/총가용재고 계산
				$goods_type				= 'normal_single';

				if($goods_seq) {
					$optionsTmp	= $this->CI->goodsmodel->get_goods_option($goods_seq);
				}else{
					$optionsTmp[] = array('reservation15' => 0, 'reservation25' => 0);
				}
				// 입력받은 재고, 불량재고로 가용재고 구하기
				$options[] =  [
							'stock' 		=> $inputStock,
							'badstock' 		=> $inputBadstock,
							'reservation15' => $optionsTmp[0]['reservation15'],
							'reservation25' => $optionsTmp[0]['reservation25'],
				];

			}

		// 옵션 사용
		}else{
			
			if($package_yn == 'y'){
				$goods_type				= 'package_multi';
			}
			// 옵션 사용 : 임시 옵션 정보
			if($tmp_seq){
				$options	= $this->CI->goodsmodel->get_option_tmp_list($tmp_seq);
				$goods_type .= "_tmp";
			}else{
			// 옵션 사용 : 기존 옵션 정보
				$options	= $this->CI->goodsmodel->get_goods_option($goods_seq);
			}
		}

		$config_goods	= config_load('order');
		$ableStockStep 	= $config_goods['ableStockStep'];		//출고예약량 기준(15 주문접수부터, 25 결제확인부터)

		// 개별설정일 때
		if($runout_type == "goods") {
			$runout 		= $params['select_runout'];				//재고에 따른 상품 판매(stock, ableStock, unlimited)
			$ableStockLimit = $params['ableStockLimit'];			//가용재고 n개 이하일 때 상품 품절 또는 재고확보중 처리 설정 값
		}else{
		// 통합설정일 때
			$runout 		= $config_goods['runout'];				//재고에 따른 상품 판매(stock, ableStock, unlimited)
			$ableStockLimit = $config_goods['ableStockLimit'];		//가용재고 n개 이하일 때 상품 품절 또는 재고확보중 처리 설정 값
		}

		if($runout != "ableStock"){
			$ableStockLimit = 0;
		}

		//-------------------------------------------------------------------------------------
		// 총재고, 총 가용재고 계산
		// 패키지 상품 일때
		if($package_yn == 'y'){
			
			$k = 1;
			// 총재고, 출고예약량
			foreach($options as $key_option =>  $data_option){

				$stock = 0;
				$ablestock = 0;
				$aa = array();
				if($data_option['package_count'] > 0){

					$package_stock = $package_ablestock = null;
					$cntStock	= $cntAbleStock =  array();
					for($i=1; $i <= $data_option['package_count']; $i++){
						
						// 판매가능 재고: 패키지 상품의 경우 goodsmodel에서 재고/가용재고 기준에 따라 실제판매가능한 재고로 계산되어서 넘어오고 있음)
						$stock 		= $data_option['package_stock'.$i];
						$ablestock 	= $data_option['package_ablestock'.$i];	
						$badstock 	= $data_option['package_badstock'.$i];	
						$unit_ea 	= $data_option["package_unit_ea".$i];		// 주문당 출고 수량
						if(!$unit_ea) $unit_ea = 1;

						// 주문당 발송 수량 적용한 재고 = floor($stock / $unit_ea)
						if($package_stock == null || floor($stock / $unit_ea) < $package_stock){
							$package_stock  = floor($stock / $unit_ea);
							if($package_stock < 0) $package_stock = 0;
						}

						// 주문당 발송 수량 적용한 가용 재고 = floor($ablestock / $unit_ea)
						if($package_ablestock == null || floor($ablestock / $unit_ea) < $package_ablestock){
							$package_ablestock  = floor($ablestock / $unit_ea);
							if($package_ablestock < 0) $package_ablestock = 0;
						}

						$cntStock[]		= $package_stock;
						$cntAbleStock[] = $package_ablestock;
					}

					// 패키지 상품에 연결된 상품의 재고/가용재고가 모두 있어야 실제 출고가능한 재고/가용재고로 계산 가능.
					// 예) 상품1 재고 5, 상품 2 재고 0 일때 => 해당 옵션의 출고가능 재고 0개
					// 예) 상품1 재고 5, 상품 2 재고 1 일때 => 해당 옵션의 출고가능 재고 1개
					if(count($cntStock) == $data_option['package_count']){
						$totstock 		+= min($cntStock);
					}

					if(count($cntAbleStock) == $data_option['package_count']){
						$totablestock 	+= min($cntAbleStock);
					}
				}
				$k++;

			}

		// 일반 상품 일 때
		}else{

			if ($options) {
				// 총재고, 출고예약량
				foreach ($options as $key_option => $data_option) {
					$totstock += $data_option['stock'] - $data_option['badstock'];
					$reservation15 += $data_option['reservation15'];
					$reservation25 += $data_option['reservation25'];

					if ($ableStockStep == 15) {
						$totunUsableStock += $data_option['reservation15'];
					}
					if ($ableStockStep == 25) {
						$totunUsableStock += $data_option['reservation25'];
					}
				}
			}

		}

		switch($runout){
			case "stock":		// 재고가 있으면 판매
				$totablestock = $totstock;
			break;
			case "ableStock":	// 가용재고가 있으면 판매
				if($package_yn != 'y'){
					$totablestock = $totstock - $totunUsableStock;
				}
				// 상품판매 여부가 가용재고 일때, 가용재고 {n}개 이하면 '정상' 상태 변경 불가.
				if($totablestock <= $ableStockLimit) $totablestock = 0;
			break;
			default: 			// 재고 상관없이 판매
				$totablestock = 9999;
			break;
		}

		// 판매가능 재고(가용재고) 수량 리턴
		return [$goods_type, $totablestock];
	}

	// 원본보기
	public function getGuideOriginalImage($image, $status = false)
	{
		preg_match_all("/<IMG([^>]*)src[\s]*=[\s]*([\"']?)([^>\"']+)[\"']?([^>]*)>/i", $image, $matches);

		if (count($matches[0]) > 0) {
			$status = true;
			$image = [];
			foreach ($matches[0] as $imgSrc) {
				$image[] = $imgSrc;
			}
		}

		return ['status' => $status,'image' => $image,];
	}

	/** 
	 * 상품상세 or 공통정보 image에 대해 lazyload
	 * @contents data-original로 적용하고자하는 컨텐츠
	 */
	public function lazyload($contents) 
	{
		preg_match_all("/<img[^>]*src=[\"']?([^>\"']+)[\"']?[^>]*>/i",$contents, $matches);

		if(count($matches[0]) > 0){
			foreach($matches[0] as $idx => $src_url){
				if (strpos($contents, $src_url) !== false) {
					$data_original_url = preg_replace("/([\s]*)src([\s]*=)/i"," data-original=",$src_url);
					$contents = str_replace($src_url, $data_original_url,$contents);
				}
			}
		}
		
		return $contents;
	}

	/**
	 * 선물하기 노출
	 * allat 사용중이면 false
	 * 설정 미사용 중이면 false
	 * 상품 선물 사용안함이면 false
	 * 개인통관부호 수집 중이면 false
	 * 입점사 선물하기 미사용중일 때 입점사 상품은 false
	 * 해외배송여부 사용이면 false 
	 */
	public function present_usable($goods) {
		$cfg_order 	= ($CI->cfg_order) ? $CI->cfg_order : config_load('order');
		$cfg_system	= ($CI->config_system) ? $CI->config_system : config_load('system');

		if($cfg_system['pgCompany'] === 'allat') {
			return false;
		}
		if($cfg_order['present_use'] !== 'y' ) {
			return false;
		}
		if ($goods['present_use'] === '0') {
			return false;
		}
		if ($cfg_order['present_seller_use'] !== 'y' && $goods['provider_seq'] > 1 ) {
			return false;
		}
		if($goods['option_international_shipping_status'] === 'y') {
			return false;
		}
		return true;
	}
}
?>