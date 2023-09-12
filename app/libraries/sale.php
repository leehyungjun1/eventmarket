<?php
if (!defined('BASEPATH')) exit('No direct script access allowed');

class sale
{
	## 전달받을 기본 정보
	var $cal_type				= 'each';	// 계산 방식 ( each : 상품당 계산, list : 할인목록->할인계산 )
	var $option_type			= 'option';	// 할인 적용할 옵션 ( option : 필수옵션, suboption : 추가옵션 )
	var $sub_sale				= 'n';		// 추가옵션 혜택 적용 여부
	var $total_price			= 0;		// 현재 장바구니의 혜택적용할인가 합계금액
	var $consumer_price			= 0;		// 현재 상품의 정가
	var $sale_price				= 0;		// 혜택적용 할인가
	var $sale_option_price		= 0;		// 혜택적용 필수옵션 할인가
	var $price					= 0;		// 현재 상품의 판매가
	var $ea						= 0;		// 현재 상품의 구매수량
	var $goods_ea				= 0;		// 현재 상품의 총 구매수량
	var $option_ea				= 0;		// 현재 상품의 옵션 구매수량
	var $member_seq				= 0;		// 로그인한 회원번호
	var $group_seq				= 0;		// 현재 회원의 등급
	var $category_code			= array();	// 현재 상품의 카테고리 전체 목록
	var $brand_code				= array();	// 현재 상품의 브랜드 전체 목록
	var $goods_seq				= 0;		// 현재 상품의 상품 번호
	var $coupon_download_seq	= 0;		// 다운로드 쿠폰 고유번호
	var $ordersheet_coupon_seq	= 0;		// 주문서쿠폰 고유번호
	var $sum_option_total_price	= 0;		// 주문서쿠폰 계산용 필수옵션 총액
	var $ordersheet_obj			= array();	// 주문서쿠폰 할인 정보 : 초기 설정 후 변경 금지 : 리셋으로 초기화 가능
	var $ordersheet_sale_cal	= 0;		// 주문서쿠폰 할인금액 차감 계산용
	var $total_price_cal		= 0;		// 주문서쿠폰 총 금액 가감 계산용
	var $goods					= array();	// 현재 상품 정보
	var $reserve_cfg			= array();	// 마일리지 설정 정보
	var $tot_use_emoney			= 0;		// 사용 마일리지
	var $marketing_sale			= array();		// 입점마케팅 추가할인 적용 ( 예) array('member'=>'Y','referer'=>'Y','coupon'=>'Y','mobile'=>'Y') )
	var $npay					= false;	//npay 할인 사용여부
	var $npay_sale				= array('basic','event','multi','member','like','referer','mobile');//npay 할인 정책
	var $npay_member_group_seq	= '0';	//무조건 npay 비회원 할인 정책 적용
	var $referer_url			= '';	//유입경로

	## 계산된 결과에서 추가로 필요한 정보
	var $code_seq				= '';		// 할인 적용된 코드할인 고유번호
	var $referer_seq			= '';		// 할인 적용된 유입경로할인 고유번호
	var $coupon_list			= array();	// 사용가능 쿠폰 목록
	var $coupon_use_array		= array();	// 사용된 쿠폰 목록
	var $coupon_same_time_y		= array();	// 사용된 쿠폰 중 단독쿠폰이 아닌 쿠폰 목록
	var $coupon_same_time_n		= array();	// 사용된 쿠폰 중 단독쿠폰 쿠폰인 목록
	var $coupon_duplication_n	= array();	// 단독쿠폰이며, 중복할인 비허용 쿠폰인 목록
	var $coupon_sale_payment_b	= array();	// 사용된 쿠폰 중 무통장일때만 사용 가능한 쿠폰 목록
	var $coupon_sale_agent_m	= array();	// 사용된 쿠폰 중 모바일에서만 사용 가능한 쿠폰 목록

	## 계산에 필요한 설정 정보
	var $suboption_sale_target	= array('basic', 'member');
	var $except_reset_vars		= array('except_reset_vars', 'suboption_sale_target', 'ci',
										'cfg_cutting_sale', 'sale_group', 'basic_sale_array',
										'cal_type', 'cancel_ea_array', 'eventSales', 'groupSales',
										'mobileSales', 'likeSales', 'couponSales', 'codeSales',
										'refererSales', 'member_seq', 'group_seq','total_price',
										'reserve_cfg', 'tot_use_emoney', 'coupon_same_time_y',
										'coupon_same_time_n', 'coupon_sale_payment_b',
										'coupon_duplication_n', 'coupon_sale_agent_m',
										'except_providerchk_coupon'
										, 'ordersheet_coupon_seq', 'ordersheet_obj', 'ordersheet_sale_cal'
										, 'total_price_cal'
									);	// 초기화 시 초기화 되지 않는 값들
	var $sale_group				= array('basic', 'event', 'multi', 'referer', 'mobile', 
										'member', 'like', 'code', 'coupon', 'ordersheet');	// 할인 계산 순서 배열
	var $basic_sale_array		= array('basic');	// 할인의 기준이될 할인가에 반영될 할인들
	var $cancel_ea_array		= array('coupon', 'code', 'referer','ordersheet');	// ea가 적용된 할인들
	var $cfg_cutting_sale		= array();	// 절사 설정
	var $pass_basic_sale		= false;	// 기본할인 패스 여부
	var $cfgs					= array();	// 할인이 적용된 각 할인의 설정값들
	var $eventSales				= array();	// 적용될 수 있는 이벤트 할인 설정들
	var $groupSales				= array();	// 적용될 수 있는 등급 할인 설정들
	var $mobileSales			= array();	// 적용될 수 있는 모바일 할인 설정들
	var $likeSales				= array();	// 적용될 수 있는 좋아요 할인 설정들
	var $couponSales			= array();	// 적용될 수 있는 쿠폰 할인 설정들
	var $codeSales				= array();	// 적용될 수 있는 코드 할인 설정들
	var $refererSales			= array();	// 적용될 수 있는 유입경로 할인 설정들

	// 입점몰 전용
	var $except_providerchk_coupon	= array('admin', 'admin_shipping', 'point', 'offline_emoney',
											'birthday', 'anniversary', 'memberGroup', 'member',
											'memberlogin', 'membermonths', 'order', 'ordersheet');


	public function __construct() {
		$this->ci =& get_instance();

		if(!$this->ci->goodsmodel) $this->ci->load->model('goodsmodel');
		if(!$this->ci->categorymodel) $this->ci->load->model('categorymodel');
		if(!$this->ci->brandmodel) $this->ci->load->model('brandmodel');
		if(!$this->ci->goodsfblike) $this->ci->load->model('goodsfblike');
		if(!$this->ci->membermodel) $this->ci->load->model('membermodel');
		if(!$this->ci->couponmodel) $this->ci->load->model('couponmodel');
		if(!$this->ci->configsalemodel) $this->ci->load->model('configsalemodel');
		if(!$this->ci->referermodel) $this->ci->load->model('referermodel');
		if(!$this->ci->promotionmodel) $this->ci->load->model('promotionmodel');
		if(!$this->ci->eventmodel) $this->ci->load->model('eventmodel');
		if(!$this->ci->common) $this->ci->load->helper('common');
		if(!$this->ci->coupon) $this->ci->load->helper('coupon');

		$this->ci->load->helper('order');

		$this->set_cutting_sale();
	}

	## 절사설정
	public function set_cutting_sale(){
		// 절사 설정 저장
		if	($this->ci->config_system)	$cfg	= $this->ci->config_system;
		else							$cfg	= config_load('system');

		if	($cfg['cutting_sale_use'] != 'none'){
			$this->cfg_cutting_sale	= array('action'	=> $cfg['cutting_sale_action'],
											'price'		=> $cfg['cutting_sale_price']);
		}
	}

	## 전역변수에 정의된 this->ci를 제외한 값들을 모두 초기화
	public function reset_init(){
		$class_vars			= get_class_vars(get_class($this));
		if	($class_vars)foreach($class_vars as $var_name => $var_value){
			if	(!in_array($var_name, $this->except_reset_vars)){
				if		(is_array($this->$var_name))	$this->$var_name	= array();
				elseif	(is_numeric($this->$var_name))	$this->$var_name	= 0;
				else									$this->$var_name	= '';
			}
		}
	}

	## 카테고리 목록 추출
	public function set_category_array(){
		// 카테고리정보
		$tmparr2	= array();
		$categorys	= $this->ci->goodsmodel->get_goods_category($this->goods_seq);
		if	($categorys)foreach($categorys as $key => $val){
			$tmparr	= $this->ci->categorymodel->split_category($val['category_code']);
			foreach($tmparr as $cate) $tmparr2[]	= $cate;
		}
		if	($tmparr2)	$this->category_code		= array_values(array_unique($tmparr2));
	}

	## 브랜드 목록 추출
	public function set_brand_array(){
		$brands		= $this->ci->goodsmodel->get_goods_brand($this->goods_seq);
		if($brands) foreach($brands as $bkey => $branddata){
			if( $branddata['link'] == 1 ){
				$brand_codear = $this->ci->brandmodel->split_brand($branddata['category_code']);
				$brand_code[] = $brand_codear[0];
			}
		}

		$this->brand_code	= $brand_code;
	}

	## 마일리지 설정 정보 추출
	public function set_reserve_config(){
		if	(!$this->reserve_cfg)	$this->reserve_cfg		= config_load('reserve');
	}

	## 기존 페이지를 위한 추가함수 ( 좋아요 혜택 전체목록 )
	public function get_fblikesale_config_list(){
		$sc['type']	= 'fblike';
		$fblike		= $this->ci->configsalemodel->lists($sc);
		if	($fblike['result'])foreach($fblike['result'] as $k => $data){
			if	($data['price1']<= $this->sale_price && $data['price2'] >= $this->sale_price){
				$fblike['sale_price']		= $data['sale_price'];
				$fblike['sale_emoney']	= $data['sale_emoney'];
				$fblike['sale_point']		= $data['sale_point'];
				break;
			}
		}
		return $fblike;
	}

	## 기존 페이지를 위한 추가함수 ( 등급 혜택 전체목록 )
	public function get_groupsale_config(){
		$group_list	= $this->ci->membermodel->get_goods_group_benifits($this->goods['sale_seq']);

		// 예외상품 및 예외카테고리 체크
		$sale_status	= true;
		if	($this->ci->membermodel->get_group_except_goods_seq($this->group_seq, $this->goods['sale_seq'], $this->goods_seq, 'sale') > 0)							$sale_status		= false;
		if	($this->ci->membermodel->get_group_except_category($this->group_seq, $this->goods['sale_seq'], $this->category_code, 'sale') > 0)	$sale_status		= false;
		$reserve_status	= true;
		if	($this->ci->membermodel->get_group_except_goods_seq($this->group_seq, $this->goods['sale_seq'], $this->goods_seq, 'emoney') > 0)						$reserve_status	= false;
		if	($this->ci->membermodel->get_group_except_category($this->group_seq, $this->goods['sale_seq'], $this->category_code, 'emoney') > 0)	$reserve_status	= false;

		if	($group_list)foreach($group_list as $k => $data){
			if	(in_array($data['use_type'], array('AUTO', 'AUTOPART'))){
				// 기존 스킨용

				if	($data['sale_price_type'] == 'WON')		$data['sale']			= $data['sale_price'];
				else										$data['sale_rate']		= $data['sale_price'];
				if	(!$sale_status)	$data['sale_price'] = 0;

				if	($data['reserve_price_type'] == 'WON')	$data['reserve']		= $data['reserve_price'];
				else										$data['reserve_rate']	= $data['reserve_price'];
				if	($data['point_price_type'] == 'WON')	$data['point']			= $data['point_price'];
				else										$data['point_rate']		= $data['point_price'];
				if	(!$reserve_status) $data['reserve_price'] = $data['point_price'] = 0;

				$group_benifit_list[$k]	= $data;
			}
		}

		return $group_benifit_list;
	}

	## 상품, 카테고리, 브랜드 Issue 설정 체크
	public function chk_issues($issue_type, $category, $goods, $brands = false){
		switch($issue_type){
			case 'issue':
				$result		= false;
				if		(!$result && $category)foreach($category as $cate){
					if		(in_array($cate, $this->category_code)){
						$result		= true;
						break;
					}
				}
				if		(!$result && $brands)foreach($brands as $brand){
					if		(in_array($brand, $this->brand_code)){
						$result		= true;
						break;
					}
				}
				if		(!$result && in_array($this->goods_seq, $goods)){
					$result			= true;
				}
			break;
			case 'except':
				$result		= true;
				if		($result && $category)foreach($category as $cate){
					if		(in_array($cate, $this->category_code)){
						$result	= false;
						break;
					}
				}
				if		($result && $brands)foreach($brands as $brand){
					if		(in_array($brand, $this->brand_code)){
						$result		= false;
						break;
					}
				}
				if		($result && in_array($this->goods_seq, $goods)){
					$result		= false;
				}
			break;
			case 'all':
			default:
				$result		= true;
			break;
		}

		return $result;
	}

	## 전달받을 기본 정보 설정
	public function set_init($param = array()){
		if	(isset($param['cal_type']))				$this->cal_type				= $param['cal_type'];
		if	(isset($param['option_type']))			$this->option_type			= $param['option_type'];
		if	(isset($param['sub_sale']))				$this->sub_sale				= $param['sub_sale'];
		if	(isset($param['reserve_cfg']))			$this->reserve_cfg			= $param['reserve_cfg'];
		if	(isset($param['tot_use_emoney']))		$this->tot_use_emoney		= $param['tot_use_emoney'];
		if	(isset($param['total_price']))			$this->total_price			= $param['total_price'];
		if	(isset($param['consumer_price']))		$this->consumer_price		= $param['consumer_price'];
		if	(isset($param['sale_price']))			$this->sale_price			= $param['sale_price'];
		if	(isset($param['price']))				$this->price				= $param['price'];
		if	(isset($param['ea']))					$this->ea					= $param['ea'];
		if	(isset($param['goods_ea']))				$this->goods_ea				= $param['goods_ea'];
		if	(isset($param['option_ea']))			$this->option_ea			= $param['option_ea'];
		if	(isset($param['member_seq']))			$this->member_seq			= $param['member_seq'];
		if	(isset($param['group_seq']))			$this->group_seq			= $param['group_seq'];
		if	(isset($param['category_code']))		$this->category_code		= $param['category_code'];
		if	(isset($param['brand_code']))			$this->brand_code			= $param['brand_code'];
		if	(isset($param['goods_seq']))			$this->goods_seq			= $param['goods_seq'];
		if	(isset($param['coupon_download_seq']))	$this->coupon_download_seq	= $param['coupon_download_seq'];
		if	(isset($param['marketing_sale']))		$this->marketing_sale		= $param['marketing_sale'];
		if	(isset($param['npay']))					$this->npay					= $param['npay'];
		if	(isset($param['npay_sale']))			$this->npay_sale			= $param['npay_sale'];
		if	(isset($param['npay_member_group_seq']))$this->npay_member_group_seq= $param['npay_member_group_seq'];
		
		// 주문서 쿠폰 계산
		if	(isset($param['ordersheet_coupon_seq'])) $this->ordersheet_coupon_seq	= $param['ordersheet_coupon_seq'];
		if	(isset($param['sum_option_total_price'])) $this->sum_option_total_price	= $param['sum_option_total_price'];

		if	(isset($param['goods'])){
			$this->goods				= $param['goods'];
			if	(!$param['goods_seq'] && $param['goods']['goods_seq'])
				$this->goods_seq		= $param['goods']['goods_seq'];
			if	(!$param['category_code'] && $param['goods']['category_code'])
				$this->category_code	= $param['goods']['category_code'];
			if	($this->option_type != 'suboption'){
				if	(!$param['price'] && $param['goods']['price'])
					$this->price			= $param['goods']['price'];
				if	(!$param['consumer_price'] && $param['goods']['consumer_price'])
					$this->consumer_price	= $param['goods']['consumer_price'];
				if	(!$param['consumer_price'] && $this->price)
					$this->consumer_price	= $this->price;
				if	(!$param['ea'] && $param['goods']['ea'])
					$this->ea				= $param['goods']['ea'];
			}
		}

		if	($this->sale_price && $this->ea) {
			$this->sale_option_price = $this->sale_price * $this->ea;
		}
		if	(!$this->goods_ea && $this->ea)	$this->goods_ea		= $this->ea;
		if	($this->goods_seq && (!is_array($this->category_code) || count($this->category_code) < 1))
			$this->set_category_array();
		if	($this->goods_seq && (!is_array($this->brand_code) || count($this->brand_code) < 1))
			$this->set_brand_array();
	}

	## 페이지별 적용 할인 확인 ( all : 금액계산 + 할인내용, textonly : 할인내용만, none : 적용안함 )
	public function apply_sale_per_page($apply_page, $sale_type, $pass_basic_sale = false){

		$saleStatue		= 'none';
		switch($apply_page){
			case 'list':
			case 'wish':
			case 'search':
			case 'search_auto':
			case 'lately':
			case 'lately_scroll':
			case 'relation':
			case 'option':
				if		($sale_type == 'basic')		$saleStatue		= 'all';
				elseif	($sale_type == 'event')		$saleStatue		= 'all';
				elseif	($sale_type == 'mobile')	$saleStatue		= 'all';
				elseif	($sale_type == 'member')	$saleStatue		= 'all';
			break;

			case 'view':
				if		($sale_type == 'basic')		$saleStatue		= 'all';
				elseif	($sale_type == 'event')		$saleStatue		= 'all';
				elseif	($sale_type == 'member')	$saleStatue		= 'all';
				elseif	($sale_type == 'mobile')	$saleStatue		= 'all';
				else								$saleStatue		= 'textonly';
			break;

			case 'cart':
				if		($sale_type == 'coupon' || $sale_type == 'code')	$saleStatue		= 'none';
				else														$saleStatue		= 'all';
			break;

			case 'saleprice':
				if	(in_array($sale_type, $this->basic_sale_array))			$saleStatue		= 'all';
			break;

			case 'order':
				$saleStatue		= 'all';
			break;
		}

		// 기본할인가가 넘어온 경우 기본 할인은 할인에서 제외
		if	($pass_basic_sale && in_array($sale_type, $this->basic_sale_array))
			$saleStatue		= 'none';
		// 추가구성옵션이고 추가구성옵션 할인 대상이 아닌 경우 할인 제외
		if	($this->option_type == 'suboption' && !in_array($sale_type, $this->suboption_sale_target))
			$saleStatue		= 'none';
		// 추가구성옵션이고 추가구성옵션 할인 대상이나, 추가할인 적용여부가 y가 아닌 경우 할인 제외
		if	($this->option_type == 'suboption' && $this->sub_sale != 'y' && !in_array($sale_type, $this->basic_sale_array)){
			$saleStatue		= 'none';
		}


		// 목록형에서는 설명전용에 대한 부분은 pass!!
		if	($this->cal_type == 'list' && $saleStatue == 'textonly')	$saleStatue		= 'none';
		// 모바일할인은 모바일일때만 적용됨
		if	($sale_type == 'mobile' && !$this->ci->_is_mobile_agent)	$saleStatue		= 'none';
		// 프로모션 코드 사용여부가 Y가 아닌 경우 적용안함
		if	($sale_type == 'code' && $this->ci->reserves['promotioncode_use']!='Y') $saleStatue = 'none';
		// 좋아요 사용여부가 NO 아닌경우 적용
		if	($sale_type == 'like' && $this->ci->arrSns['fb_like_box_type']=='NO') $saleStatue = 'none';
		// 입점마케팅 전달 데이터 통합 설정시 추가할인 적용 leewh 2015-02-02
		if ($sale_type == 'mobile' && $this->marketing_sale['mobile']=='Y') $saleStatue = 'all';
		if ($sale_type == 'coupon' && $this->marketing_sale['coupon']=='Y') $saleStatue = 'all';
		if ($sale_type == 'referer' && $this->marketing_sale['referer']=='Y') $saleStatue = 'all';

		return $saleStatue;
	}

	## 최종할인 금액 절사 처리
	public function cut_sale_price($price){
		$action		= $this->cfg_cutting_sale['action'];
		$unit		= $this->cfg_cutting_sale['price'];

		if	($action && $unit > 0){
			switch($action){
				case 'dscending':
					$price = floor($price / $unit) * $unit;
				break;
				case 'rounding':
					$price = round($price / $unit) * $unit;
				break;
				case 'ascending':
					$price = ceil($price / $unit) * $unit;
				break;
			}
		}

		return $price;
	}

	## 할인 내역을 미리 읽어옴
	public function preload_set_config($apply_page = 'order'){
		foreach($this->sale_group as $sale_type){
			$saleStatue		= $this->apply_sale_per_page($apply_page, $sale_type);
			if	($saleStatue != 'none'){
				$funcName					= 'set_'.$sale_type.'_sale';
				$this->$funcName();
			}
		}
	}

	## 할인 계산
	public function calculate_sale_price($apply_page = 'order'){
		// 주문서쿠폰 분할 계산을 위해 총 상품 금액 재계산 추가
		if	($this->option_type == 'option'){
			$this->total_price_cal += $this->sale_price * $this->ea;
		}

		if	($this->sale_price){
			$pass_basic_sale				= true;
			$result_price					= $this->sale_price * $this->ea;
			$one_result_price				= $this->sale_price;
		}else{
			$pass_basic_sale				= false;
			$result_price					= $this->consumer_price * $this->ea;
			$one_result_price				= $this->consumer_price;
			if	($this->option_type == 'suboption')	$this->sale_price	= $this->price;
		}

		$after_price['original']		= $result_price;
		$one_after_price['original']	= $one_result_price;

		foreach($this->sale_group as $sale_type){

			//이벤트 쿠폰/코드 사용제한 @2015-08-14
			if ($sale_type == 'coupon' && $this->eventSales &&
				( ( $this->goods['event']['event_seq'] && $this->goods['event']['use_coupon']=="n" ) || ( $this->cfgs['event']['event_seq'] && $this->cfgs['event']['use_coupon']=="n" ) ) ) continue;
			if ($sale_type == 'code' && $this->eventSales &&
				( ( $this->goods['event']['event_seq'] && $this->goods['event']['use_code']=="n" ) || ( $this->cfgs['event']['event_seq'] && $this->cfgs['event']['use_code']=="n") ) ) continue;

			//npay 주문 할인일때 지정된 할인 정책만 이용 @2016-01-29 pjm
			if($this->npay){
				if(!in_array($sale_type,$this->npay_sale)){
					continue;
				}else{
				}
			}

			$saleStatue		= $this->apply_sale_per_page($apply_page, $sale_type, $pass_basic_sale);

			//if($apply_page == "order") debug($sale_type ." :: ".$saleStatue." :: ".$pass_basic_sale);

			if	($saleStatue != 'none'){
				if ($sale_type=='coupon' && $this->marketing_sale['coupon']=='Y') $funcName	= $sale_type.'_marketing_sale';
				else if (($sale_type=='referer' && $this->marketing_sale['referer']=='Y') || ($sale_type=='member' && $this->marketing_sale['member']=='Y')) $funcName	= $sale_type.'_sale';
				else if	($this->cal_type == 'list')	$funcName	= 'list_'.$sale_type.'_sale';
				else							$funcName	= $sale_type.'_sale';

				$sale_result				= $this->$funcName();
				$sale_price					= get_cutting_price($sale_result['sale_price']);

				$nvr_cut = array('basic', 'ordersheet');
				if	(!in_array($sale_type, $nvr_cut))	$sale_price	= $this->cut_sale_price($sale_price);

				$target_sale				= 0;
				if	($sale_type == 'event')	{
					$eventEnd		= $sale_result['eventEnd'];
					$event_order_ea	= $sale_result['event_order_ea'];
					$event_order_cnt = $sale_result['event_order_cnt'];
					$event_target_sale	= $sale_result['target_sale'];
					$event_event_sale	= $sale_result['event_sale'];
				} else if ($sale_type == 'member') {
					$member_sale_price = $sale_result['sale_unit'];
					$member_sale_type = $sale_result['sale_type'];
				}

				// NEW 스킨에 추가될 변수들 정의 :: 2016-11-10 lwh
				$alertEnd[$sale_type]	= $sale_result['alertEnd'];
				$evtPeriod[$sale_type]	= $sale_result['evtperiod'];
				$descPopup[$sale_type]	= $sale_result['goods_desc_popup'];

				if	($sale_result['target_sale'] == 1)	$target_sale	= 1;
				if	($saleStatue != 'textonly'){
					// 할인 기준 할인가 계산.
					if	(in_array($sale_type, $this->basic_sale_array)){
						if	($target_sale == 1){

							unset($sale_list,$one_sale_list,$text_list,$total_sale_price,$one_total_sale_price,$after_price,$one_after_price);

							// 기본할인 시 정가가 있으나, 정가가 판매가 보다 작을 경우 판매가에서 할인
							if	($sale_type == 'basic' && $this->consumer_price > 0 &&
								$this->consumer_price < $this->price){
								$this->sale_price				= $this->price - $sale_price;
								$after_price['original']		= $this->price * $this->ea;
								$one_after_price['original']	= $this->price;
							// 정가 기준 시 정가가 있으면 정가에서 할인
							}elseif	($this->consumer_price > 0){
								$this->sale_price				= $this->consumer_price - $sale_price;
								$after_price['original']		= $this->consumer_price * $this->ea;
								$one_after_price['original']	= $this->consumer_price;
							}else{
								$this->sale_price				= $this->price - $sale_price;
								$after_price['original']		= $this->price * $this->ea;
								$one_after_price['original']	= $this->price;
							}
						}else{
							$this->sale_price	-= $sale_price;
						}

						$sale_list[$sale_type]			= $sale_price * $this->ea;
						$one_sale_list[$sale_type]		= $sale_price;
						$result_price					= $this->sale_price * $this->ea;
						$one_result_price				= $this->sale_price;
						$total_sale_price				+= $sale_price * $this->ea;
						$one_total_sale_price			+= $sale_price;
						$this->sale_option_price 		= $this->sale_price * $this->ea;

					// 혜택적용할인가로 계산되는 할인들
					}else{
						// ea가 적용된 할인과 아닌 할인 분리
						if	(in_array($sale_type, $this->cancel_ea_array)){
							$sale_list[$sale_type]			= $sale_price;
							$one_sale_list[$sale_type]		= $sale_price / $this->ea;
							$result_price					-= $sale_price;
							$one_result_price				-= $sale_price / $this->ea;
							$total_sale_price				+= $sale_price;
							$one_total_sale_price			+= $sale_price / $this->ea;
						}else{
							$sale_list[$sale_type]			= $sale_price * $this->ea;
							$one_sale_list[$sale_type]		= $sale_price;
							$result_price					-= $sale_price * $this->ea;
							$one_result_price				-= $sale_price;
							$total_sale_price				+= $sale_price * $this->ea;
							$one_total_sale_price			+= $sale_price;
						}
					}

					// 최종 금액은 0원 미만이 될 수 없다..
					if	($result_price < 0){
						$sale_list[$sale_type] = $sale_list[$sale_type] - abs($result_price);
						$total_sale_price	= $total_sale_price - abs($result_price);
						$result_price		= 0;

						// 최종할인 금액이 재계산될 때 개당 할인 금액도 재계산
						if	(in_array($sale_type, $this->cancel_ea_array)){
							$one_sale_list[$sale_type]		= $sale_list[$sale_type] / $this->ea;
						}else{
							$one_sale_list[$sale_type]		= $sale_list[$sale_type];
						}
						
					}
					if	($one_result_price < 0){
						$one_sale_list[$sale_type] = $one_sale_list[$sale_type] - abs($one_result_price);
						$one_result_price	= 0;
					}

					$after_price[$sale_type]		= $result_price;
					$one_after_price[$sale_type]	= $one_result_price;
					$addSale[]						= $sale_type;
					$target_list[$sale_type]		= $target_sale;
				}
				$title_list[$sale_type]		= $sale_result['sale_title'];
				$text_list[$sale_type]		= $sale_result['sale_txt'];
				$seq_list[$sale_type]		= $sale_result['sale_seq'];
				$subject_list[$sale_type]	= $sale_result['sale_subject'];

				if	($sale_result['sale_mtxt'])	$mtext_list[$sale_type]	= $sale_result['sale_mtxt'];
				else							$mtext_list[$sale_type]	= $sale_result['sale_txt'];
			}
		}//endfor

		// 할인이 모두 반영된 할인가로 마일리지 포인트를 계산
		if	($result_price > 0){
			if	(!$this->reserve_cfg)	$this->set_reserve_config();

			$result_one_price			= $result_price / $this->ea;
			if	(in_array('event', $addSale)){
				$reserve					= $this->event_sale_reserve($result_one_price);
				$one_tot_reserve			+= $reserve;
				$tot_reserve				+= $reserve * $this->ea;
				$reserve_list['event']		= $reserve * $this->ea;
				$one_reserve_list['event']	= $reserve;
				$point						= $this->event_sale_point($result_one_price);
				$one_tot_point				+= $point;
				$tot_point					+= $point * $this->ea;
				$point_list['event']		= $point * $this->ea;
				$one_point_list['event']	= $point;
			}
			if	(in_array('member', $addSale)){
				$reserve					= $this->member_sale_reserve($result_one_price,$result_price);
				$one_tot_reserve			+= $reserve;
				$tot_reserve				+= $reserve * $this->ea;
				$reserve_list['member']		= $reserve * $this->ea;
				$one_reserve_list['member']	= $reserve;
				$point						= $this->member_sale_point($result_one_price,$result_price);
				$one_tot_point				+= $point;
				$tot_point					+= $point * $this->ea;
				$point_list['member']		= $point * $this->ea;
				$one_point_list['member']	= $point;
			}
			if	(in_array('mobile', $addSale)){
				$reserve					= $this->mobile_sale_reserve($result_one_price);
				$one_tot_reserve			+= $reserve;
				$tot_reserve				+= $reserve * $this->ea;
				$reserve_list['mobile']		= $reserve * $this->ea;
				$one_reserve_list['mobile']	= $reserve;
				$point						= $this->mobile_sale_point($result_one_price);
				$one_tot_point				+= $point;
				$tot_point					+= $point * $this->ea;
				$point_list['mobile']		= $point * $this->ea;
				$one_point_list['mobile']	= $point;
			}
			if	(in_array('like', $addSale)){
				$reserve					= $this->like_sale_reserve($result_one_price);
				$one_tot_reserve			+= $reserve;
				$tot_reserve				+= $reserve * $this->ea;
				$reserve_list['like']		= $reserve * $this->ea;
				$one_reserve_list['like']	= $reserve;
				$point						= $this->like_sale_point($result_one_price);
				$one_tot_point				+= $point;
				$tot_point					+= $point * $this->ea;
				$point_list['like']			= $point * $this->ea;
				$one_point_list['like']		= $point;
			}

			// 최종 할인율 역계산
			if	($this->consumer_price > 0)
				$sale_per	= 100 - floor(( $result_price / ($this->consumer_price * $this->ea) ) * 100);
			else
				$sale_per	= 100 - floor(( $result_price / ($this->price * $this->ea) ) * 100);
		}else if($result_price <= 0 && $this->cunsumer_price <= 0){
			$sale_per     = 0;
		}	
		else {
			$sale_per		= 100;
		}

		$return	= array('apply_page'			=> $apply_page,
						'goods_seq'				=> $this->goods_seq,
						'consumer_price'		=> $this->consumer_price,
						'price'					=> $this->price,
						'ea'					=> $this->ea,
						'sale_per'				=> ($sale_per < 0) ? 0 : $sale_per,
						'sale_price'			=> $this->sale_price * $this->ea,
						'one_sale_price'		=> $this->sale_price,
						'result_price'			=> $result_price,
						'one_result_price'		=> $one_result_price,
						'total_sale_price'		=> $total_sale_price,
						'one_total_sale_price'	=> $one_total_sale_price,
						'one_tot_reserve'		=> $one_tot_reserve,
						'tot_reserve'			=> $tot_reserve,
						'one_tot_point'			=> $one_tot_point,
						'tot_point'				=> $tot_point,
						'text_list'				=> $text_list,
						'mtext_list'			=> $mtext_list,
						'title_list'			=> $title_list,
						'subject_list'			=> $subject_list,
						'seq_list'				=> $seq_list,
						'sale_list'				=> $sale_list,
						'one_sale_list'			=> $one_sale_list,
						'target_list'			=> $target_list,
						'reserve_list'			=> $reserve_list,
						'one_reserve_list'		=> $one_reserve_list,
						'point_list'			=> $point_list,
						'one_point_list'		=> $one_point_list,
						'after_price'			=> $after_price,
						'one_after_price'		=> $one_after_price,
						'event_order_ea'		=> $event_order_ea,
		                'event_order_cnt'       => $event_order_cnt,
						'eventEnd'				=> $eventEnd,
						'alertEnd'				=> $alertEnd,
						'event_target_sale'		=> $event_target_sale,
						'event_event_sale'		=> $event_event_sale,
						'member_sale_price'		=> $member_sale_price,
						'member_sale_type'		=> $member_sale_type,
						'evtPeriod'				=> $evtPeriod,
						'descPopup'				=> $descPopup);

		return $return;
	}


	##↓↓↓↓↓↓↓↓↓↓	1:1 할인 계산 함수들		↓↓↓↓↓↓↓↓↓↓##


	## 기본할인
	public function basic_sale(){
		$return['sale_price']	= 0;
		$return['sale_seq']		= 0;
		$return['target_sale']	= 1;
		$return['sale_subject']	= '';
		$return['sale_title']	= getAlert('gv086'); //기본할인
		if	($this->consumer_price > $this->price){
			$return['target_sale']	= 1;
			$return['sale_price']	= $this->consumer_price - $this->price;
			$sale_per				= round(($return['sale_price'] / $this->consumer_price) * 100);
			//할인
			$return['sale_txt']		= $sale_per.'% '.getAlert('gv069');
		}else{
			$return['target_sale']	= 1;
			$return['sale_price']	= 0;
			$sale_per				= 0;
			$return['sale_txt']		= '0% '.getAlert('gv069');
		}

		return $return;
	}

	## 이벤트 할인
	public function event_sale(){
		$return['sale_price']	= 0;
		$return['sale_subject']	= '';
		$return['sale_title']	= getAlert('gv087'); //이벤트
		$event					= get_event_price($this->price, $this->goods_seq, $this->category_code, $this->consumer_price, $this->goods);
		
		// 입점사 할인 분담금에 따른 이벤트 제한 :: 2018-05-09 lwh
		if	($this->goods['provider_seq'] && $event['provider_list']){
			$gProvider	= explode('|', $event['provider_list']);
			if	(!in_array($this->goods['provider_seq'], $gProvider)){
				unset($event);
			}
		}
		
		if($event['target_sale'] == '2' && $event['event_sale'] > $this->price){ // 판매가만큼 지정한 경우에도 할인 적용되게 수정 22.02.25
			unset($event);
		}

		$this->cfgs['event']	= $event;

		if($event['event_seq']) {
			$return['sale_subject']	= $event['title'];
			$return['sale_seq']		= $event['event_seq'];
			$return['target_sale']	= $event['target_sale'];
			$return['event_sale']	= $event['event_sale'];

			
			if	($event['target_sale'] == 1)
				$return['sale_price']	= ($this->consumer_price >= $event['event_sale_unit']) ? $event['event_sale_unit'] : 0; // 정가 100% 할인 이더라도 할인 적용  22.02.25
			else 
				$return['sale_price']	= ($this->price >= $event['event_sale_unit']) ? $event['event_sale_unit'] : 0; // 판매가 100% 할인 이더라도 할인 적용  22.02.25

			
			// 할인 설명 문구
			$sale_txts = array();
			$target_txt = $event['target_sale'] == '1' ? getAlert('gv069') : getAlert('gv074');
			//추가할인
			if	($event['event_sale'] > 0)		$sale_txts['event_sale'] =($event['target_sale'] == 2) ? get_currency_price($event['event_sale'],2).' '.$target_txt.' ' : floor($event['event_sale']).'%'.$target_txt.' ';
			//추가적립
			if	($event['event_reserve'] > 0)	$sale_txts['event_reserve'] = floor($event['event_reserve']).'%'.getAlert('gv075').' ';
			//추가포인트
			if	($event['event_point'] > 0)		$sale_txts['event_point'] = floor($event['event_point']).'%'.getAlert('gv076').' ';

			$sale_mtxt	= $sale_txts[array_max_key($event,array('event_sale','event_reserve','event_point'))];
			$sale_txt = implode(", ",$sale_txts);

			if	($event['app_week_title']){
				//까지
				$return['sale_txt']		= $sale_txt . '<br/>' . $event['app_week_title'] . ' '
										. substr($event['app_start_time_title'],0,2) . ':00~'
										. substr($event['app_end_time_title'],0,2) . ':00 '
										. '(~' . getAlert('gv078', date('Y-m-d', strtotime($event['end_date']))).')';
				$return['sale_mtxt']	= $sale_mtxt . '(~' . getAlert('gv078', date('Y-m-d', strtotime($event['end_date']))).')';
			}else{
				$return['sale_txt']		= $sale_txt.'(~'.getAlert('gv078', date('Y-m-d', strtotime($event['end_date']))).')';
				$return['sale_mtxt']	= $sale_mtxt.'(~'.getAlert('gv078', date('Y-m-d', strtotime($event['end_date']))).')';
			}

			if	($event['event_type'] == 'solo'){
				if	($event['app_end_time']){
					$eventEnd['year']	= date("Y");
					$eventEnd['month']	= date("m");
					$eventEnd['day']	= date("d");
					$eventEnd['hour']	= substr($event['app_end_time'], 0, 2);
					$eventEnd['min']	= substr($event['app_end_time'], -2);
					$eventEnd['second']	= '00';
				}else{
					$eventEndDateTime	= explode(" ", $event['end_date']);
					$eventEndDate		= explode("-", $eventEndDateTime[0]);
					$eventEnd['year']	= $eventEndDate[0];
					$eventEnd['month']	= $eventEndDate[1];
					$eventEnd['day']	= $eventEndDate[2];
					$eventEndTime		= explode(":", $eventEndDateTime[1]);
					$eventEnd['hour']	= $eventEndTime[0];
					$eventEnd['min']	= $eventEndTime[1];
					$eventEnd['second']	= $eventEndTime[2];
				}
				$return['eventEnd']		= $eventEnd;
			}

			// NEW 스킨 종료일 안내 추가 :: 2016-11-10 lwh
			$evtEndObj	= new DateTime(date('Y-m-d',strtotime($event['end_date'])));
			$todayObj	= new DateTime(date('Y-m-d'));
			$gap		= date_diff($todayObj,$evtEndObj);
			unset($alertTxt);
			if($gap->days <= 5){
				if($gap->days > 0){
					//'종료 '.$gap->days.'일전';
					$alertTxt = getAlert('gv084',$gap->days);
				}else{
					//마지막날
					$alertTxt = getAlert('gv085');
				}
			}
			$return['alertEnd'] = ($gap->days <= 5) ? $alertTxt : false;

			// NEW 스킨 기간 및 소개 팝업 추가 :: 2016-11-10 lwh
			$return['evtperiod'] = date('Y-m-d',strtotime($event['start_date'])) . ' ~ ' . date('Y-m-d',strtotime($event['end_date']));
			$return['goods_desc_popup'] = $event['goods_desc_popup'];
		}

		return $return;
	}

	## 대량구매 할인
	public function multi_sale(){
		$return['sale_price']	= 0;
		$return['sale_seq']		= 0;
		$return['sale_subject']	= '';
		$return['sale_title']	= getAlert('gv088'); //대량구매
		$sale_option_price = $this->sale_option_price;
		if	($this->goods['multi_discount_policy']){
			$sDiscountMaxOverQty	= $this->goods['multi_discount_policy']['discountMaxOverQty'];
			$sDiscountMaxAmount		= $this->goods['multi_discount_policy']['discountMaxAmount'];
			$sDiscountUnit			= $this->goods['multi_discount_policy']['discountUnit'];
			// 슬라이딩 조건
			foreach($this->goods['multi_discount_policy']['policyList'] as $policy){
				if	($policy['discountOverQty'] && $policy['discountUnderQty'] && $policy['discountAmount']){
					if	($policy['discountOverQty'] <= $this->option_ea && $this->option_ea < $policy['discountUnderQty']){ // 최대 구매 조건이 있을 경우
						if	( $this->goods['multi_discount_policy']['discountUnit'] == 'PER' &&
							$policy['discountAmount'] <= 100 ){
							$return['sale_price']	= ( $this->sale_price * $policy['discountAmount'] / 100 );
						}elseif ($this->sale_price >= $policy['discountAmount'] ) {
							$return['sale_price']	= $policy['discountAmount'];

							//상품의 할인적용금액보다 대량구매 할인금액이 큰경우 대량구매 할인금액 재계산
							if ( $return['sale_price'] > $sale_option_price / $this->ea ) {
								$return['sale_price'] = $sale_option_price / $this->ea;
							}
						}
					}
				}else if($policy['discountOverQty'] && $policy['discountAmount'] && count($this->goods['multi_discount_policy']['policyList']) == 1 ){ //슬라이드 방식 할인 조건이 있을 경우
					if	($policy['discountOverQty'] <= $this->option_ea ){
						if	( $this->goods['multi_discount_policy']['discountUnit'] == 'PER' && $policy['discountAmount'] <= 100 ){
							$return['sale_price']	= ( $this->sale_price * $policy['discountAmount'] / 100 );
						} elseif ($this->sale_price >= $policy['discountAmount'] ) {
							$return['sale_price']	= $policy['discountAmount'];

							//상품의 할인적용금액보다 대량구매 할인금액이 큰경우 대량구매 할인금액 재계산
							if ( $return['sale_price'] > $sale_option_price / $this->ea ) {
								$return['sale_price'] = $sale_option_price / $this->ea;
							}
						}
					}
				}
			}
			// 마지막 슬라이딩 조건
			if	($sDiscountMaxOverQty && $sDiscountMaxAmount && $sDiscountUnit ){
				if	($sDiscountMaxOverQty <= $this->option_ea ){
					if	( $sDiscountUnit == 'PER' && $sDiscountMaxAmount <= 100 ){
						$return['sale_price']	=  $this->sale_price * $sDiscountMaxAmount / 100;
					}else if($this->sale_price >= $sDiscountMaxAmount ) {
						$return['sale_price']	= $sDiscountMaxAmount;

						//상품의 할인적용금액보다 대량구매 할인금액이 큰경우 대량구매 할인금액 재계산
						if ($return['sale_price'] > $sale_option_price / $this->ea ) {
							$return['sale_price'] = $sale_option_price / $this->ea;
						}
					}
				}
			}
			$this->sale_option_price -= $return['sale_price'] * $this->ea;

			if	($this->goods['multi_discount_policy']['policyList'][0]['discountOverQty'] &&
				$this->goods['multi_discount_policy']['policyList'][0]['discountUnderQty'] &&
				$this->goods['multi_discount_policy']['policyList'][0]['discountAmount']){
				$return['sale_txt']			=
					getAlert('gv079',array($this->goods['multi_discount_policy']['policyList'][0]['discountOverQty'],$this->goods['multi_discount_policy']['policyList'][0]['discountUnderQty']));
				$return['sale_mtxt']		= getAlert('gv080').' ';
				if	($this->goods['multi_discount_policy']['discountUnit'] == 'PER'){
					$return['sale_txt']		.= $this->goods['multi_discount_policy']['policyList'][0]['discountAmount'].'% '.getAlert('gv069');
					$return['sale_mtxt']	.= $this->goods['multi_discount_policy']['policyList'][0]['discountAmount'].'% '.getAlert('gv069');
				}else{
					$return['sale_txt']		.= get_currency_price($this->goods['multi_discount_policy']['policyList'][0]['discountAmount'],2).' '.getAlert('gv069');
					$return['sale_mtxt']	.= get_currency_price($this->goods['multi_discount_policy']['policyList'][0]['discountAmount'],2).' '.getAlert('gv069');
				}
				$return['sale_mtxt']	.= ' (' .getAlert('gv081',array($this->goods['multi_discount_policy']['policyList'][0]['discountOverQty'],$this->goods['multi_discount_policy']['policyList'][0]['discountUnderQty'])).')';
			}else if	($this->goods['multi_discount_policy']['discountMaxOverQty'] &&
				$this->goods['multi_discount_policy']['discountMaxAmount'] && $this->goods['multi_discount_policy']['discountUnit']){
				$return['sale_txt']			= getAlert('gv082', $this->goods['multi_discount_policy']['discountMaxOverQty']).' ';
				$return['sale_mtxt']		= getAlert('gv080').' ';
				if	($this->goods['multi_discount_policy']['discountUnit'] == 'PER'){
					$return['sale_txt']		.= $this->goods['multi_discount_policy']['discountMaxAmount'].'% '.getAlert('gv069');
					$return['sale_mtxt']	.= $this->goods['multi_discount_policy']['discountMaxAmount'].'% '.getAlert('gv069');
				}else{
					$return['sale_txt']		.= get_currency_price($this->goods['multi_discount_policy']['discountMaxAmount'],2).' '.getAlert('gv069');
					$return['sale_mtxt']	.= get_currency_price($this->goods['multi_discount_policy']['discountMaxAmount'],2).' '.getAlert('gv069');
				}
				$return['sale_mtxt']	.= ' (' .getAlert('gv083', $this->goods['multi_discount_policy']['discountMaxOverQty']).')';

			}else if ($this->goods['multi_discount_policy']['policyList'][0]['discountOverQty'] &&
				!$this->goods['multi_discount_policy']['policyList'][0]['discountUnderQty'] &&
				$this->goods['multi_discount_policy']['policyList'][0]['discountAmount']){
				$return['sale_txt']			= getAlert('gv082', $this->goods['multi_discount_policy']['policyList'][0]['discountOverQty']).' ';
				$return['sale_mtxt']		= getAlert('gv080').' ';
				if	($this->goods['multi_discount_policy']['discountUnit'] == 'PER'){
					$return['sale_txt']		.= $this->goods['multi_discount_policy']['policyList'][0]['discountAmount'].'% '.getAlert('gv069');
					$return['sale_mtxt']	.= $this->goods['multi_discount_policy']['policyList'][0]['discountAmount'].'% '.getAlert('gv069');
				}else{
					$return['sale_txt']		.= get_currency_price($this->goods['multi_discount_policy']['policyList'][0]['discountAmount'],2).' '.getAlert('gv069');
					$return['sale_mtxt']	.= get_currency_price($this->goods['multi_discount_policy']['policyList'][0]['discountAmount'],2).' '.getAlert('gv069');
				}
				$return['sale_mtxt']	.= ' (' .getAlert('gv083', $this->goods['multi_discount_policy']['policyList'][0]['discountOverQty']).')';
			}
		}
		return $return;
	}

	## 등급 할인
	public function member_sale(){
		$return['sale_price']	= 0;
		$return['sale_subject']	= '';
		$return['sale_title']	= getAlert('gv089'); //등급할인
		$group_seq				= 0;
		if	($this->group_seq)	$group_seq	= $this->group_seq;

		$total_price			= $this->total_price;
		if	(!$total_price)		$total_price	= $this->sale_price * $this->ea;

		// 입점마케팅 전달 데이터 회원등급별 할인 적용(1 : 일반 등급 회원) leewh 2015-02-03
		if ($this->marketing_sale['member']=='Y') {
			$this->group_seq = 1;
		}
		//npay 등급할인 정책 적용
		if($this->npay) $this->group_seq = $this->npay_member_group_seq;

		$return['sale_price']	= get_cutting_price($this->ci->membermodel->get_member_group($this->group_seq, $this->goods_seq, $this->category_code, $this->sale_price, $total_price, $this->goods['sale_seq'], $this->option_type));
		$benefit				= $this->ci->membermodel->group_benifit;

		// 예외상품 및 예외카테고리 체크
		$sale_status	= true;
		if	($this->ci->membermodel->get_group_except_goods_seq($this->group_seq, $this->goods['sale_seq'], $this->goods_seq, 'sale') > 0)							$sale_status		= false;
		if	($this->ci->membermodel->get_group_except_category($this->group_seq, $this->goods['sale_seq'], $this->category_code, 'sale') > 0)	$sale_status		= false;
		$reserve_status	= true;
		if	($this->ci->membermodel->get_group_except_goods_seq($this->group_seq, $this->goods['sale_seq'], $this->goods_seq, 'emoney') > 0)						$reserve_status	= false;
		if	($this->ci->membermodel->get_group_except_category($this->group_seq, $this->goods['sale_seq'], $this->category_code, 'emoney') > 0)	$reserve_status	= false;

		if	($sale_status || $reserve_status){
		if	(!$this->group_seq){

			$this->cfgs['no_member']	= $benefit; // 비회원용 혜택
			unset($benefit);

			// 비회원의 경우 가장 높은 등급의 혜택을 보여줌.
			$benefitList	= $this->ci->membermodel->get_goods_group_benifits($this->goods['sale_seq']);
			$bfGroupSeq	= 0;
			if	($benefitList)foreach($benefitList as $data){
				if	(in_array($data['use_type'], array('AUTO', 'AUTOPART')) || $data['group_seq'] == 0){
						if	($sale_status){
							// 예상 할인가
							if	($data['sale_price_type'] == 'PER'){
								$data['sale_rate']	= $data['sale_price'];	// 기존 스킨용
								$tmp_expect_price	= get_cutting_price($this->sale_price * ($data['sale_price'] / 100));
							}else{
								$data['sale']		= $data['sale_price'];	// 기존 스킨용
								$tmp_expect_price	= $data['sale_price'];
							}
						}

						if	($reserve_status){
							// 기존 스킨용
							if	($data['reserve_price_type'] == 'PER'){
								$data['reserve_rate']	= $data['reserve_price'];
								$tmp_expect_reserve		= get_cutting_price($this->sale_price * ($data['reserve_price'] / 100));
							}else{
								$data['reserve']		= $data['reserve_price'];
								$tmp_expect_reserve		= $data['reserve_price'];
							}
							if	($data['point_price_type'] == 'PER'){
								$data['point_rate']	= $data['point_price'];
								if	(!$data['reserve_price'])
									$tmp_expect_reserve		= get_cutting_price($this->sale_price * ($data['point_price'] / 100));
							}else{
								$data['point']		= $data['point_price'];
								if	(!$data['reserve_price'])
									$tmp_expect_reserve		= $data['point_price'];
							}

							if	($data['point_price_type'] == 'WON')	$data['point']			= $data['point_price'];
							else										$data['point_rate']		= $data['point_price'];
						}

						if	($sale_status){
							// 1개 구매 시 기준으로 가장 높은 할인을 안내함
							if	($expect_price < $tmp_expect_price){
								$expect_price	= $tmp_expect_price;
								$benefit		= $data;
							}

							// 가격할인이 없고 1개 구매 시 기준으로 가장 높은 적립을 안내함 @2016-10-07 ysm
							if	( $reserve_status && $expect_price == 0 && $tmp_expect_reserve > 0){
								if	($expect_reserve < $tmp_expect_reserve){
									$expect_reserve	= $tmp_expect_reserve;
									$benefit		= $data;
								}
							}
						}else{
							// 1개 구매 시 기준으로 가장 높은 적립을 안내함
							if	($expect_reserve < $tmp_expect_reserve){
								$expect_reserve	= $tmp_expect_reserve;
								$benefit		= $data;
							}
						}
						// 비회원 할인조건이 있는 경우에는 비회원 금액 적용되도록 수정 2019-06-17 by hyem
						if	($data['group_seq'] == 0 && $tmp_expect_price > 0 ) {
							break;
						}
					}
				}
			}else{
				// 기존 스킨용
				if	($benefit['sale_price_type'] == 'WON')		$benefit['sale']		= $benefit['sale_price'];
				else											$benefit['sale_rate']	= $benefit['sale_price'];
				if	($benefit['reserve_price_type'] == 'WON')	$benefit['reserve']		= $benefit['reserve_price'];
				else											$benefit['reserve_rate']	= $benefit['reserve_price'];
				if	($benefit['point_price_type'] == 'WON')		$benefit['point']		= $benefit['point_price'];
				else											$benefit['point_rate']	= $benefit['point_price'];

				// 할인 금액 없을시 sals_txt 삭제 :: 2015-04-09 lwh
				//if($return['sale_price'] <= 0)	$benefit = '';
				if($return['sale_price']+$benefit['reserve_price']+$benefit['point_price'] <= 0) $benefit = '';

				$this->cfgs['member']	= $benefit;
				$return['sale_txt']		= $benefit['group_name'] . ' ';
			}
		}else{
			unset($benefit);
		}

		if	($benefit){
			$return['sale_seq']		= $benefit['sale_seq'];
			$return['sale_type']	= $benefit['sale_price_type'];
			$return['sale_unit']	= ($benefit['sale_price_type'] == 'PER') ? $benefit['sale_rate'] : $benefit['sale_price'];

			// 할인 정보
			if($sale_status && $benefit['sale_price'] > 0){
				//최대
				if	($benefit['sale_limit_price'] > 0)	$addMaxStr1	= getAlert('gv073').' ';
				$return['sale_mtxt']		= $return['sale_txt'];
				if	($benefit['sale_price_type'] == 'PER'){
					//추가할인
					$return['sale_txt']		.= $addMaxStr1 . floor($benefit['sale_price']).'%'.getAlert('gv074').' ';
					$return['sale_mtxt']	.= $addMaxStr1 . floor($benefit['sale_price']).'%'.getAlert('gv074').' ';
				}else{
					$return['sale_txt']		.= $addMaxStr1 . get_currency_price($benefit['sale_price'],2).getAlert('gv074').' ';
					$return['sale_mtxt']	.= $addMaxStr1 . get_currency_price($benefit['sale_price'],2).getAlert('gv074').' ';
				}
			}

			// 적립 정보
			if($reserve_status && $benefit['reserve_price'] > 0){
				if	($benefit['point_limit_price'] > 0)	$addMaxStr2	= getAlert('gv073').' ';
				//추가적립
				if	($benefit['reserve_price_type'] == 'WON')
					$return['sale_txt']		.= $addMaxStr2 . get_currency_price($benefit['reserve_price'],2).getAlert('gv075').' ';
				else
					$return['sale_txt']		.= $addMaxStr2 . floor($benefit['reserve_price']).'%'.getAlert('gv075').' ';
			}

			// 포인트 정보
			if($reserve_status && $benefit['point_price'] > 0){
				//최대
				if	($benefit['point_limit_price'] > 0)	$addMaxStr2	= getAlert('gv073').' ';
				//추가포인트
				if	($benefit['point_price_type'] == 'PER')
					$return['sale_txt']		.= $addMaxStr2 . floor($benefit['point_price']).'%'.getAlert('gv076').' ';
				else
					$return['sale_txt']		.= $addMaxStr2 . get_currency_price($benefit['point_price'],2).getAlert('gv076').' ';
			}
		}else{
			$return['sale_txt']			= '';
		}

		return $return;
	}

	## 모바일 할인
	public function mobile_sale(){
		$return['sale_price']	= 0;
		$return['sale_subject']	= '';
		$return['sale_title']	= getAlert('gv090'); //모바일

		// 모바일 할인 설정
		$sc['type']			= 'mobile';
		if	($this->mobileSales)	$mobile_cfg_list	= $this->mobileSales;
		else						$mobile_cfg_list	= $this->ci->configsalemodel->lists($sc);

		$tmp_sum_sale_price	= $this->sale_price;
		if	($this->ea > 1)	$tmp_sum_sale_price	= $this->sale_price * $this->ea;
		foreach($mobile_cfg_list['result'] as $k => $cfg) {
			$m++;
			$basic_cfg	= $cfg;
			if	($cfg['price1'] <= $tmp_sum_sale_price && $tmp_sum_sale_price <= $cfg['price2']){
				// 가장 높은 할인을 반영
				if	($cfg['sale_price'] > $current_cfg['sale_price']){
					$current_cfg	= $cfg;
				}
			}

			if	($m > 1 && $best_cfg['sale_price'] < $cfg['sale_price']){
				$best_cfg	= $cfg;
			}
		}
		if	($current_cfg)	$this->cfgs['mobile']		= $current_cfg;

		// 해당 할인에 대한 정보로 할인 및 노출
		$return['sale_txt']		= '';
		if		($current_cfg){
			$return['sale_price']	= $this->sale_price * ($current_cfg['sale_price'] / 100);
		}
		// 가장 높은 할인에 대한 정보로 노출
		if		($best_cfg){
			$current_cfg				= $best_cfg;
			//최대
			$return['sale_txt']		= getAlert('gv073').' ';
		// 기본 할인 정보로 노출
		}else{
			$current_cfg				= $basic_cfg;
		}

		// 할인혜택 정보
		if		($current_cfg){
			$sale_txts = array();
			$return['sale_seq']		= $current_cfg['seq'];
			//추가할인
			if($current_cfg['sale_price'] > 0)
				$sale_txts['sale_price']		= $current_cfg['sale_price'].'% '.getAlert('gv074');
			//추가적립
			if	($current_cfg['sale_emoney'] > 0)
				$sale_txts['sale_emoney']		= $current_cfg['sale_emoney'].'% '.getAlert('gv075');
			//추가포인트
			if	($current_cfg['sale_point'] > 0)
				$sale_txts['sale_point']		= $current_cfg['sale_point'].'% '.getAlert('gv076');

			$return['sale_txt']		= implode(", ",$sale_txts);
			$return['sale_mtxt']	= $sale_txts[array_max_key($current_cfg,array('sale_price','sale_emoney','sale_point'))];

		}

		return $return;
	}

	## 좋아요 할인
	public function like_sale(){
		$return['sale_price']	= 0;
		$return['sale_subject']	= '';
		$return['sale_title']	= getAlert('gv091'); //좋아요

		// 좋아요 여부 체크
		if	(!$this->member_seq){
			if( $this->ci->session->userdata('fbuser') ) {
				$sns_id	= $this->ci->session->userdata('fbuser');
			}elseif(get_cookie('fbuser')){
				$sns_id	= get_cookie('fbuser');
			}
		}
		if	($this->member_seq > 0 || $sns_id){
			$sc['whereis']	= " and goods_seq = '".$this->goods_seq."' ";
			if	($this->member_seq)	$sc['whereis']	.= " and member_seq = '".$this->member_seq."' ";
			else					$sc['whereis']	.= " and sns_id = '".$sns_id."' ";
			$fbstatus		= $this->ci->goodsfblike->get_data($sc);
		}

		// 할인 여부 및 할인금액 계산
		$sc['type']	= 'fblike';
		$fblike_cfg_list	= $this->ci->configsalemodel->lists($sc);
		if	($fblike_cfg_list){
			if	( $fblike_cfg_list['result'] ){
				$tmp_sum_sale_price	= $this->sale_price;
				if	($this->ea > 1)	$tmp_sum_sale_price	= $this->sale_price * $this->ea;
				foreach($fblike_cfg_list['result'] as $fblike => $cfg) {
					$f++;
					$basic_cfg	= $cfg;
					if	($fbstatus['like_seq'] > 0 &&
						$cfg['price1'] <= $tmp_sum_sale_price && $cfg['price2'] >= $tmp_sum_sale_price){
						// 가장 높은 할인을 반영
						if	($cfg['sale_price'] > $current_cfg['sale_price']){
							$current_cfg	= $cfg;
						}
					}

					if	($f > 1 && $best_cfg['sale_price'] < $cfg['sale_price']){
						$best_cfg	= $cfg;
					}
				}
			}
			if	($current_cfg)	$this->cfgs['like']		= $current_cfg;

			// 해당 할인에 대한 정보로 할인 및 노출
			$return['sale_txt']		= '';
			if		($current_cfg){
				$return['sale_price']	= $this->sale_price * ($current_cfg['sale_price'] / 100);
			}
			// 가장 높은 할인에 대한 정보로 노출
			if		($best_cfg){
				$current_cfg				= $best_cfg;
				//최대
				$return['sale_txt']		= getAlert('gv073').' ';
			// 기본 할인 정보로 노출
			}else{
				$current_cfg				= $basic_cfg;
			}

			// 할인혜택 정보
			if		($current_cfg){
				$return['sale_seq']		= $current_cfg['seq'];
				//추가할인
				$return['sale_txt']		.= $current_cfg['sale_price'].'% '.getAlert('gv074').' ';
				$return['sale_mtxt']	= $return['sale_txt'];
				//추가적립
				if	($current_cfg['sale_emoney'] > 0)
					$return['sale_txt']		.= $current_cfg['sale_emoney'].'% '.getAlert('gv075').' ';
				//추가포인트
				if	($current_cfg['sale_point'] > 0)
					$return['sale_txt']		.= $current_cfg['sale_point'].'% '.getAlert('gv076').' ';
			}
		}

		return $return;
	}

	## 쿠폰 할인 ( 회원전용 또는 입점마케팅  )
	public function coupon_sale($marketing=null){
		$return['sale_price']	= 0;
		$return['sale_subject']	= '';
		$return['sale_title']	= getAlert('gv092'); //쿠폰할인
		$total_price			= $this->total_price;
		if	(!$total_price)		$total_price	= $this->sale_price * $this->ea;
		if	($this->member_seq > 0 || $marketing ){
			if($marketing) {
				$today = date('Y-m-d',time());
				$coupons[]	= goods_coupon_max($this->goods_seq, false, $this->sale_price);		// 입점마케팅 DB 할인가 기준으로 쿠폰 적용되도록 수정 2018-04-02
			}else{
				$coupons	= $this->ci->couponmodel->get_able_use_list($this->member_seq, $this->goods_seq, $this->category_code, $total_price, $this->sale_price, $this->goods_ea);
			}
			if	($coupons){
				foreach($coupons as $downloads){
					// 적용대상 입점사 체크 ( 할인분담금 )
					if	( !in_array($downloads['type'],  $this->except_providerchk_coupon ) ){
						if	($downloads['provider_list']){
							if	(!strstr($downloads['provider_list'], '|'.$this->goods['provider_seq'].'|') && !$marketing )
								continue;
						}else{
							if	($this->goods['provider_seq'] != 1)
								continue;
						}
					}

					if	($this->coupon_download_seq == $downloads['download_seq']){
						$this->coupon_use_array[]	= $this->coupon_download_seq;

						if	($downloads['duplication_use'] == 1){
							$return['sale_price'] = get_cutting_price($downloads['goods_sale'] * $this->ea);
						}else{
							$return['sale_price'] = get_cutting_price($downloads['goods_sale']);
						}

						$this->coupon_salescost['admin']	= $downloads['salescost_admin'];
						$this->coupon_salescost['provider']	= $downloads['salescost_provider'];
						$this->coupon_salescost['list']		= $downloads['provider_list'];

						// 단독쿠폰체크
						if	($downloads['coupon_same_time'] == 'N' ) {
							if	( !in_array($this->coupon_download_seq, $this->coupon_same_time_n) ) {
								$this->coupon_same_time_n[]		= $this->coupon_download_seq;
							}
							if	( $downloads['duplication_use'] != 1) {
								$this->coupon_duplication_n[$this->coupon_download_seq]++;
							}
						}else{
							if	( !in_array($this->coupon_download_seq, $this->coupon_same_time_y) ) {
								$this->coupon_same_time_y[]		= $this->coupon_download_seq;
							}
						}

						//무통장만 사용가능
						if	($downloads['sale_payment'] == 'b' ) {
							if	( !in_array($this->coupon_download_seq, $this->coupon_sale_payment_b) )
								$this->coupon_sale_payment_b[]	= $this->coupon_download_seq;
						}

						//모바일만 사용가능> 모바일기기체크
						if	($downloads['sale_agent'] == 'm') {// && !$this->_is_mobile_agent
							if	( !in_array($this->coupon_download_seq, $this->coupon_sale_agent_m) )
								$this->coupon_sale_agent_m[]	= $this->coupon_download_seq;
						}

						$return['sale_seq']			= $downloads['coupon_seq'];
						$return['sale_subject']		= $downloads['coupon_name'];
						$return['sale_txt']			= $downloads['coupon_name'] . ' (';
						if	($downloads['limit_goods_price'] > 0){
							//이상 구매 시
							$return['sale_txt']		.= get_currency_price($downloads['limit_goods_price'],2).' '.getAlert('gv063').' ';
						}
						if	($downloads['sale_type'] == 'percent'){
							if	($downloads['duplication_use'] == '1'){
//								$return['sale_txt']	.= '상품 1개당 '.$downloads['percent_goods_sale'].'% 추가할인';
								$return['sale_txt']	.= getAlert('gv064',$downloads['percent_goods_sale']);
							}else{
//								$return['sale_txt']	.= '상품 1개 '.$downloads['percent_goods_sale'].'% 추가할인';
								$return['sale_txt']	.= getAlert('gv065',$downloads['percent_goods_sale']);
							}
							if	($downloads['max_percent_goods_sale'] > 0){
//								$return['sale_txt']	.= ' 단, 최대 '.get_currency_price($downloads['max_percent_goods_sale'],2).' 할인';
								$return['sale_txt']	.= getAlert('gv066',get_currency_price($downloads['max_percent_goods_sale'],2));
							}
						}else{
							if	($downloads['duplication_use'] == '1'){
//								$return['sale_txt']	.= '상품 1개당 '.get_currency_price($downloads['won_goods_sale'],2).' 추가할인';
								$return['sale_txt']	.= getAlert('gv067',get_currency_price($downloads['won_goods_sale'],2));
							}else{
//								$return['sale_txt']	.= '상품 1개 '.get_currency_price($downloads['won_goods_sale'],2).' 추가할인';
								$return['sale_txt']	.= getAlert('gv068',get_currency_price($downloads['won_goods_sale'],2));
							}
						}

						$return['sale_txt']	.= ')';
					}

					$this->coupon_list[$downloads['download_seq']]	= $downloads;
				}
			}
		}
		if	(!$return['sale_txt']){
			$coupon	= goods_coupon_max($this->goods_seq);
			if	($coupon){
				$return['sale_subject']		= $coupon['coupon_name'];
				$return['sale_seq']			= $coupon['coupon_seq'];
				if($coupon['sale_type'] == 'percent') {
					//할인
					$return['sale_txt']		= number_format($coupon['percent_goods_sale']).'% '.getAlert('gv069');
					if($coupon['max_percent_goods_sale']){
//						$return['sale_txt'] .= " (최대할인금액 ".get_currency_price($coupon['max_percent_goods_sale'],2).")";
						$return['sale_txt'] .= getAlert('gv070',get_currency_price($coupon['max_percent_goods_sale'],2));
					}
				}else{
					$return['sale_txt']		= get_currency_price($coupon['won_goods_sale'],2).' '.getAlert('gv069');
				}
			}
		}
		return $return;
	}
	
	// 주문서 할인 
	public function ordersheet_sale(){}

	## 입점 마케팅(지식쇼핑/쇼핑하우) 쿠폰 할인
	public function coupon_marketing_sale(){
		return $this->coupon_sale('coupon_marketing_sale');//쿠폰할인문구 동일하게 처리하기 위해 @2017-03-08
	}

	## 코드 할인
	public function code_sale(){
		$return['sale_price']	= 0;
		$return['sale_subject']	= '';
		$return['sale_title']	= getAlert('gv093'); //할인코드
		$sessid					= session_id();
		$promotion_seq			= $this->ci->session->userdata('cart_promotioncodeseq_'.$sessid);
		$promotion_code			= $this->ci->session->userdata('cart_promotioncode_'.$sessid);
		$total_price			= $this->total_price;
		if	(!$total_price)		$total_price	= $this->sale_price * $this->ea;
		if	($promotion_seq && $promotion_code){
			$code	= $this->ci->promotionmodel->get_able_download_saleprice($promotion_seq,$promotion_code, $total_price, $this->sale_price,$this->ea);
			// 할인부담금 관련 적용 대상 입점사 여부 체크
			if	( ($code['provider_list'] && strstr($code['provider_list'], '|'.$this->goods['provider_seq'].'|')) || (!$code['provider_list'] && $this->goods['provider_seq'] == 1)){

				$this->code_seq	= $code['promotion_seq'];
				if($code['promotion_seq'] && ($code['sale_type'] == 'percent' || in_array($code['sale_type'],array('won','KRW','USD','CNY','JPY','EUR')))){
					$return['sale_title']				= $promotion_seq;
					$return['sale_price']				= get_cutting_price($code['promotioncode_sale']);
					$this->code_salescost['admin']		= get_cutting_price($code['salescost_admin']);
					$this->code_salescost['provider']	= get_cutting_price($code['salescost_provider']);
					$this->code_salescost['list']		= $code['provider_list'];
					if	($code['sale_type'] == 'per'){
						//할인
						$return['sale_txt']		= '['.$promotion_code.'] '.number_format($code['percent_goods_sale']).'% '.getAlert('gv069');
						if	($code['max_percent_goods_sale'] > 0){
							//최대
							$return['sale_txt']	.= '('.getAlert('gv073').' '.get_currency_price($code['max_percent_goods_sale'],2).')';
						}
					}else{
						$return['sale_txt']		= '['.$promotion_code.'] '.get_currency_price($code['won_goods_sale'],2).' '.getAlert('gv069');
					}
				}
			}
		}else{
			$code	= $this->ci->promotionmodel->get_able_promotion_max($this->goods_seq, $this->category_code, $this->brand_code);
			if	($code)foreach($code as $cfg){

				// 할인부담금 관련 적용 대상 입점사 여부 체크
				if	( ($cfg['provider_list'] && strstr($cfg['provider_list'], '|'.$this->goods['provider_seq'].'|')) || (!$cfg['provider_list'] && $this->goods['provider_seq'] == 1)){

					if	($cfg['type'] == 'promotion'){
						if	($cfg['sale_type'] == 'percent'){
							$tmp_expect_price	= floor($this->sale_price * ($cfg['percent_goods_sale'] / 100));
						}else{
							$tmp_expect_price	= $cfg['won_goods_sale'];
						}

						if	($expect_price < $tmp_expect_price){
							$expect_price	= $tmp_expect_price;
							$current_cfg	= $cfg;
						}
					}
				}
			}

			if	($current_cfg){
				$return['sale_subject']	= $current_cfg['promotion_name'];
				$return['sale_seq']		= $current_cfg['promotion_seq'];
				$return['sale_txt']		= '['.$current_cfg['promotion_input_serialnumber'].'] ';
				if	($current_cfg['sale_type'] == 'percent'){
					//할인
					$return['sale_txt']	.= $current_cfg['percent_goods_sale'] . '% '.getAlert('gv069');
					if	($current_cfg['max_percent_goods_sale'] > 0){
						//최대
						$return['sale_txt']	.= '('.getAlert('gv073').' '.get_currency_price($current_cfg['max_percent_goods_sale'],2).')';
					}
				}else{
					//할인
					$return['sale_txt']	.= get_currency_price($current_cfg['won_goods_sale'],2) . ' '.getAlert('gv069');
				}
			}
		}

		return $return;
	}

	## 유입경로 할인
	public function referer_sale(){
		$return['sale_price']	= 0;
		$return['sale_title']	= getAlert('gv094'); //유입경로

		$shop_referer = get_cookie('shopReferer');

		// 입점마케팅 전달 데이터 할인 유입경로 적용 leewh 2015-02-03
		if ($this->marketing_sale['referer']=='Y' && $this->marketing_sale['referer_url']) {
			$shop_referer = $this->marketing_sale['referer_url'];
		}

		if	($shop_referer){

			$referer	= $this->ci->referermodel->sales_referersale($shop_referer, $this->goods_seq, $this->sale_price, $this->ea);

			$this->refererSales		= $referer;
			$this->referer_seq		= $referer['referersale_seq'];
			$return['sale_seq']		= $referer['referersale_seq'];
			if	($referer){
				$this->referer_salecode['admin']	= $referer['salescost_admin'];
				$this->referer_salecode['provider']	= $referer['salescost_provider'];
				$this->referer_salecode['list']		= $referer['provider_list'];
				$return['sale_subject']				= $referer['referersale_name'];
				$return['sale_price']				= $referer['sales_price'];
				$return['sale_txt']					= (substr($referer['referersale_url'],-1) == '/') ? '['.substr($referer['referersale_url'],0,strlen($referer['referersale_url'])-1).'] ' : '['.$referer['referersale_url'].'] ';
				if	($referer['sale_type'] == 'percent'){
					//추가할인
					$return['sale_txt']	.= ' '.$referer['percent_goods_sale'].'% '.getAlert('gv074');
					if	($referer['max_percent_goods_sale'] > 0){
						//최대
						$return['sale_txt']	.= ' ('.getAlert('gv073').' '.get_currency_price($referer['max_percent_goods_sale'],2).')';
					}
				}else{
					$return['sale_txt']	.= ' '.get_currency_price($referer['won_goods_sale'],2).' '.getAlert('gv074');
				}
			}
		}else{
			$referer	= $this->ci->referermodel->get_goods_referersale($this->goods_seq, $this->category_code);
			if	($referer)foreach($referer as $cfg){
				if	($cfg['sale_type'] == 'percent'){
					$tmp_expect_price	= get_cutting_price($this->sale_price * ($cfg['percent_goods_sale'] / 100));
				}else{
					$tmp_expect_price	= $cfg['won_goods_sale'];
				}

				if	($expect_price < $tmp_expect_price){
					$expect_price	= $tmp_expect_price;
					$current_cfg	= $cfg;
				}
			}

			if	($current_cfg){
				$return['sale_subject']	= $current_cfg['referersale_name'];
				$return['sale_seq']		= $current_cfg['referersale_seq'];
				$return['sale_txt']		= (substr($current_cfg['referersale_url'],-1) == '/')?'['.substr($current_cfg['referersale_url'],0,strlen($current_cfg['referersale_url'])-1).'] ':'['.$current_cfg['referersale_url'].'] ';//'['.$current_cfg['referersale_url'].'] ';
				if	($current_cfg['sale_type'] == 'percent'){
					//추가할인
					$return['sale_txt']	.= $current_cfg['percent_goods_sale'].'% '.getAlert('gv074');
					if	($current_cfg['max_percent_goods_sale'] > 0){
						//최대
						$return['sale_txt']	.= ' ('.getAlert('gv073').' '.get_currency_price($current_cfg['max_percent_goods_sale'],2).')';
					}
				}else{
					$return['sale_txt']		.= get_currency_price($current_cfg['won_goods_sale'],2).' '.getAlert('gv074');
				}
			}
		}

		return $return;
	}

	##↑↑↑↑↑↑↑↑↑↑	1:1 할인 계산 함수들		↑↑↑↑↑↑↑↑↑↑##
	##↓↓↓↓↓↓↓↓↓↓	1:N 할인 계산 함수들 ( Query는 최대한 날리지 않는다. )	↓↓↓↓↓↓↓↓↓↓##

	## 기본할인
	public function list_basic_sale(){
		return $this->basic_sale();
	}

	## 이벤트 할인
	public function list_event_sale(){

		$return['sale_price']	= 0;
		$return['sale_title']	= getAlert('gv087'); //이벤트
		$price					= $this->price;
		$consumer_price			= $this->consumer_price;
		if	(!$consumer_price)	$consumer_price	= $price;

		// 이벤트 조회
		$eventList				= $this->eventSales;
		if	($eventList){
			foreach($eventList as $k => $evt){

				//할인금액 이벤트 시 할인될 금액이 판매금액보다 크면 이벤트 대상이 아님.
				if($evt['target_sale'] == 2 && $evt['event_sale'] >= $price) continue;

				//카테고리,상품 초기화
				$gSeqArr = $gCateArr = $geSeqArr = $geCateArr = array();

				if	($evt['goods'])					$gSeqArr	= explode(',', $evt['goods']);
				if	($evt['category'])				$gCateArr	= explode(',', $evt['category']);
				if	($evt['exception_goods'])		$geSeqArr	= explode(',', $evt['exception_goods']);
				if	($evt['exception_category'])	$geCateArr	= explode(',', $evt['exception_category']);
				if	($evt['provider_list'])			$gProvider	= explode('|', $evt['provider_list']);

				// 카테고리 선정
				if	($evt['goods_rule'] == 'category'){
					$set_sale	= false;
					if	($this->category_code)foreach($this->category_code as $c => $code){
						if	($gCateArr && in_array($code, $gCateArr)){
							$set_sale	= true;
						}
						if	($geCateArr && in_array($code, $geCateArr)){
							$set_sale	= false;
							break;
						}
					}
					if	(in_array($this->goods_seq, $geSeqArr))	$set_sale	= false;

				// 상품으로 선정일때
				}elseif	($evt['goods_rule'] == 'goods_view'){
					$set_sale	= false;
					if	(in_array($this->goods_seq, $gSeqArr))	$set_sale	= true;

				// 전체 상품일 때
				}else{
					$set_sale	= true;
					if	($evt['goods_kind'] == 'coupon' && $goods_kind == 'goods')	$set_sale	= false;
					if	($evt['goods_kind'] == 'goods' && $goods_kind == 'coupon')	$set_sale	= false;
					if	($set_sale){
						if	($this->category_code)foreach($this->category_code as $c => $code){
							if	($geCateArr && in_array($code, $geCateArr)){
								$set_sale	= false;
								break;
							}
						}
					}
					if	($set_sale){
						if	(in_array($this->goods_seq, $geSeqArr))	$set_sale	= false;
					}
				}

				// 입점사 할인 분담금 체크 :: 2018-05-09 lwh 추가
				if	($set_sale){
					$set_sale	= false;
					if ($evt['provider_list']) {
						if	($this->goods['provider_seq']){
							if	($gProvider && in_array($this->goods['provider_seq'], $gProvider)){
								$set_sale	= true;
							}
						}
					} else {// 할인이벤트 등록시 > 본사부담으로 체크시 provider_list가 빈값으로 저장되기 때문에 추가 2022-02-23
						if	($this->goods['provider_seq']){
							if	($this->goods['provider_seq'] == 1){
								$set_sale	= true;
							}
						}
					}

				}

				if	($set_sale){
					$nTime	= date('H');
					if	($evt['sTime'] <= $nTime && $evt['eTime'] >= $nTime && !$solos[$nTime]){
						// 단독이벤트 우선 처리
						if	($evt['event_type'] == 'solo')	$solos[$nTime]	= true;

						$cprice		= $price;
						if	($evt['target_sale'] == 1)	$cprice		= $consumer_price;
						//$nprice		= $cprice * ($evt['event_sale'] / 100);


						if($evt['target_sale'] == 2){
							$nprice		= get_cutting_price($evt['event_sale']);
						}else{
							$nprice		= $cprice * ($evt['event_sale'] / 100);
						}

						if	($nprice > $sale_price || !$sale_price){
							$event			= $evt;
							$sale_price		= $nprice;
							// 단독이벤트 시간 표시
							if	($evt['event_type'] == 'solo'){
								$solo_start		= $evt['solo_start'];
								$solo_end		= $evt['solo_end'];
							}
						}
					}
				}
			}
		}

		if	($event){
			$this->cfgs['event']	= $event;
			$return['sale_seq']		= $event['event_seq'];
			$return['sale_subject']	= $event['title'];
			$return['target_sale']	= $event['target_sale'];
			$return['sale_price']	= $sale_price;
			$return['event_sale']	= $event['event_sale'];

			// 할인 설명 문구
			//추가할인
			$target_txt = $event['target_sale'] == '1' ? getAlert('gv069') : getAlert('gv074');
			if	($event['event_sale'] > 0)		$sale_txt	= ($event['target_sale'] == 2) ? get_currency_price($event['event_sale'],2).' '.$target_txt.' ' : $event['event_sale'].'%'.$target_txt.' ';
												$sale_mtxt	= $sale_txt;
			if	($event['event_reserve'] > 0)	$sale_txt	.= $event['event_reserve'].'%'.getAlert('gv075').' '; //추가적립
			if	($event['event_point'] > 0)		$sale_txt	.= $event['event_point'].'%'.getAlert('gv076').' '; //추가포인트
			if	($event['app_week_title']){
				$return['sale_txt']		= $sale_txt . $event['app_week_title'] . ' '
										. $event['sTime'] . getAlert('gv077').'~ '
										. $event['eTime'] . getAlert('gv077').' '
										. '(~' . getAlert('gv078',date('Y-m-d', strtotime($event['end_date']))).')'; //까지
				$return['sale_mtxt']	= $sale_mtxt.'(~' .getAlert('gv078', date('Y-m-d', strtotime($event['end_date']))).')';
			}else{
				$return['sale_txt']		= $sale_txt.'(~'.getAlert('gv078', date('Y-m-d', strtotime($event['end_date']))).')';
				$return['sale_mtxt']	= $sale_mtxt.'(~'.getAlert('gv078', date('Y-m-d', strtotime($event['end_date']))).')';
			}

			$this->sale_option_price -= $return['sale_price'] * $this->ea;

			// 이벤트 판매수량 추가
			$return['event_order_ea'] = $event['event_order_ea'];

			if	($event['event_type'] == 'solo'){
				if	($event['app_end_time']){
					$eventEnd['year']	= date("Y");
					$eventEnd['month']	= date("m");
					$eventEnd['day']	= date("d");
					$eventEnd['hour']	= substr($event['app_end_time'], 0, 2);
					$eventEnd['min']	= substr($event['app_end_time'], -2);
					$eventEnd['second']	= '00';
				}else{
					$eventEndDateTime	= explode(" ", $event['end_date']);
					$eventEndDate		= explode("-", $eventEndDateTime[0]);
					$eventEnd['year']	= $eventEndDate[0];
					$eventEnd['month']	= $eventEndDate[1];
					$eventEnd['day']	= $eventEndDate[2];
					$eventEndTime		= explode(":", $eventEndDateTime[1]);
					$eventEnd['hour']	= $eventEndTime[0];
					$eventEnd['min']	= $eventEndTime[1];
					$eventEnd['second']	= $eventEndTime[2];
				}
				$return['eventEnd']		= $eventEnd;
			}

			// NEW 스킨 종료일 안내 추가 :: 2016-11-10 lwh
			$evtEndObj	= new DateTime(date('Y-m-d',strtotime($event['end_date'])));
			$todayObj	= new DateTime(date('Y-m-d'));
			$gap		= date_diff($todayObj,$evtEndObj);
			$return['alertEnd'] = ($gap->days <= 5) ? $gap->days : false;

			// NEW 스킨 기간 및 소개 팝업 추가 :: 2016-11-10 lwh
			$return['evtperiod'] = date('Y-m-d',strtotime($event['start_date'])) . ' ~ ' . date('Y-m-d',strtotime($event['end_date']));
			$return['goods_desc_popup'] = $event['goods_desc_popup'];
		}

		return $return;
	}

	## 복수구매 할인
	public function list_multi_sale(){
		return $this->multi_sale();
	}

	## 등급 할인
	public function list_member_sale(){
		$return['sale_price']	= 0;
		$return['sale_subject']	= '';
		$return['sale_title']	= getAlert('gv089'); //등급할인

		$total_price			= $this->total_price;
		if	(!$total_price)		$total_price	= $this->sale_price * $this->ea;
		$sale_option_price 		= $this->sale_option_price;
		$group_seq				= 0;
		if($this->npay) $this->group_seq = $this->npay_member_group_seq; //npay 등급할인 정책 적용
		if	($this->group_seq)	$group_seq	= $this->group_seq;
		$groupSales				= $this->groupSales[$this->goods['sale_seq']];
		$benefit				= $groupSales['benefit'][$group_seq];
		$this->cfgs['member']	= $benefit;
		$issuecategory			= $groupSales['category']['sale'];
		$issuegoods				= $groupSales['goods']['sale'];
		if	(!$issuecategory)	$issuecategory	= array();
		if	(!$issuegoods)		$issuegoods		= array();

		// 제외 카테고리 체크
		$sale_possible		= true;
		if	( $this->category_code && is_array($groupSales['category']['sale']) ){
			foreach($this->category_code as $category_code){
				if	(in_array($category_code, $issuecategory)){
					$sale_possible		= false;
					break;
				}
			}
		}
		// 제외 상품 체크
		if	($sale_possible && in_array($this->goods_seq, $issuegoods))	$sale_possible	= false;

		if	($sale_possible){
			$type_fld	= 'sale_price_type';
			$price_fld	= 'sale_price';
			if($this->option_type == 'suboption'){
				$type_fld	= 'sale_option_price_type';
				$price_fld	= 'sale_option_price';
			}

			if( $benefit[$type_fld] == 'PER' && $benefit[$price_fld] && $this->sale_price ){
				$return['sale_price']	= get_cutting_price($this->sale_price * ($benefit[$price_fld]/100));
			}else if( $benefit[$type_fld] != 'PER' && $benefit[$price_fld] && $this->sale_price ){
				$return['sale_price']	= get_cutting_price($benefit[$price_fld]);

				//상품의 할인적용금액보다 등급할인금액이 큰경우 등급할인금액 재계산
				//추가옵션은 등급 할인만 적용되어 재계산 필요 X
				if( $this->option_type == 'option' && $return['sale_price'] > $sale_option_price / $this->ea ) {
					$return['sale_price'] = $sale_option_price / $this->ea;
				}
			}

			if($benefit['sale_use'] == 'Y' && $total_price){
				if( $benefit['sale_limit_price'] > $total_price ){
					$return['sale_price'] = 0;
				}
			}

			if($this->option_type == 'option') {
				$this->sale_option_price -= $return['sale_price'] * $this->ea;
			}
		}

		if	(!$group_seq){
			// 비회원의 경우 가장 높은 등급의 혜택을 보여줌.
			$benefitList	= $groupSales['benefit'];
			if	($benefitList)foreach($benefitList as $data){
				if	($bfGroupSeq < $data['group_seq']){
					$bfGroupSeq	= $data['group_seq'];
					$benefit	= $data;
				}
			}
			//최대
			$return['sale_txt']	= getAlert('gv073').' ';
		}else{
			$return['sale_txt']	= $benefit['group_name'] . ' ';
		}

		if	($benefit){
			$return['sale_seq']		= $benefit['sale_seq'];
			$return['sale_type']	= $benefit['sale_price_type'];
			$return['sale_unit']    = ($benefit['sale_price_type'] == 'PER') ? $benefit['sale_rate'] : $benefit['sale_price'];

			// 할인 정보
			$return['sale_mtxt']		= $return['sale_txt'];
			if	($benefit['sale_price_type'] == 'PER'){
				$return['sale_txt']		.= floor($benefit['sale_price']).'%'.getAlert('gv074').' '; //추가할인
				$return['sale_mtxt']	.= floor($benefit['sale_price']).'%'.getAlert('gv074').' ';
			}else{
				$return['sale_txt']		.= get_currency_price($benefit['sale_price'],2).getAlert('gv074').' ';
				$return['sale_mtxt']	.= get_currency_price($benefit['sale_price'],2).getAlert('gv074').' ';
			}

			// 적립 정보
			if	($benefit['reserve_price_type'] == 'PER')
				$return['sale_txt']		.= floor($benefit['reserve_price']).'%'.getAlert('gv075').' ';//추가적립
			else
				$return['sale_txt']		.= get_currency_price($benefit['reserve_price'],2).getAlert('gv075').' ';

			// 포인트 정보
			if	($benefit['point_price_type'] == 'PER')
				$return['sale_txt']		.= floor($benefit['point_price']).'%'.getAlert('gv076').' '; //추가포인트
			else
				$return['sale_txt']		.= get_currency_price($benefit['point_price'],2).getAlert('gv076').' '; //추가포인트
		}

		return $return;
	}

	## 모바일 할인
	public function list_mobile_sale(){
		return $this->mobile_sale();
	}

	## 좋아요 할인
	public function list_like_sale(){
		$return['sale_price']	= 0;
		$return['sale_subject']	= '';
		$return['sale_title']	= getAlert('gv091'); //좋아요

		$likeSales			= $this->likeSales;
		$likeGoods			= $likeSales['goods'];
		if	(!$likeGoods)	$likeGoods	= array();
		$fblike_cfg_list	= $likeSales['config'];
		$saleStatus			= false;
		if	(in_array($this->goods_seq, $likeGoods))	$saleStatus	= true;

		// 할인 여부 및 할인금액 계산
		if	($fblike_cfg_list){
			$tmp_sum_sale_price	= $this->sale_price;
			if	($this->ea > 1)	$tmp_sum_sale_price	= $this->sale_price * $this->ea;
			foreach($fblike_cfg_list as $fblike => $cfg) {
				$f++;
				$basic_cfg	= $cfg;
				if	($saleStatus && $cfg['price1'] <= $tmp_sum_sale_price && $cfg['price2'] >= $tmp_sum_sale_price){
					// 가장 높은 할인을 반영
					if	($cfg['sale_price'] > $current_cfg['sale_price']){
						$current_cfg	= $cfg;
					}
				}

				if	($f > 1 && $best_cfg['sale_price'] < $cfg['sale_price']){
					$best_cfg	= $cfg;
				}
			}
			if	($current_cfg)	$this->cfgs['like']		= $current_cfg;

			// 해당 할인에 대한 정보로 할인 및 노출
			$return['sale_txt']		= '';
			if		($current_cfg){
				$return['sale_price']	= $this->sale_price * ($current_cfg['sale_price'] / 100);
			}
			// 가장 높은 할인에 대한 정보로 노출
			if		($best_cfg){
				$current_cfg				= $best_cfg;
				//최대
				$return['sale_txt']		= getAlert('gv073').' ';
			// 기본 할인 정보로 노출
			}else{
				$current_cfg				= $basic_cfg;
			}

			// 할인혜택 정보
			if		($current_cfg){
				$return['sale_seq']		= $current_cfg['seq'];
				$return['sale_txt']		.= $current_cfg['sale_price'].'% '.getAlert('gv074').' '; //추가할인
				if	($current_cfg['sale_emoney'] > 0)
					$return['sale_txt']		.= $current_cfg['sale_emoney'].'% '.getAlert('gv075').' '; //추가적립
				if	($current_cfg['sale_point'] > 0)
					$return['sale_txt']		.= $current_cfg['sale_point'].'% '.getAlert('gv076').' '; //추가포인트
			}
		}

		return $return;
	}

	## 쿠폰 할인 ( 회원전용임 )
	public function list_coupon_sale(){
		return $this->list_ordersheet_coupon_sale($this->coupon_download_seq, $this->sale_price, $this->ea);
	}

	## 주문서 쿠폰 할인
	public function list_ordersheet_sale(){
		$return = $this->ordersheet_obj;
		$return['sale_price']	= 0;
		$return['sale_title']	= '주문서쿠폰'; //쿠폰할인
		$sale_option_price = $this->sale_option_price;

		// 주문서 할인 금액 총액을 각 상품별로 분할
		$tmp_ordersheet_price = $this->ordersheet_obj['sale_price'] * $this->sale_price * $this->ea / $this->sum_option_total_price;
		$tmp_ordersheet_price = get_cutting_price($tmp_ordersheet_price,"KRW",'admin');// 나머지 버림 처리를 위해 한화로 계산

		$this->ordersheet_sale_cal -= $tmp_ordersheet_price;
		
		$return['sale_price']	= $tmp_ordersheet_price;

		//상품의 할인적용금액보다 주문서 쿠폰 할인금액이 큰경우 주문서 쿠폰 할인금액 재계산
		if( $sale_option_price && $return['sale_price'] > $sale_option_price ) {
			$return['sale_price'] = $sale_option_price;
		}

		if($this->total_price_cal == $this->sum_option_total_price){
			$return['sale_price'] += $this->ordersheet_sale_cal;
		}

		if( $sale_option_price ) {
			$this->sale_option_price -= $return['sale_price'];
		}

		return $return;
	}
	
	## 주문서 쿠폰 할인 ( 회원전용임 )
	public function list_ordersheet_coupon_sale($coupon_download_seq, $sale_price, $ea){

		$return['sale_price']	= 0;
		$return['sale_title']	= getAlert('gv092'); //쿠폰할인
		$total_price			= $this->sum_option_total_price;
		$sale_option_price 		= $this->sale_option_price;

		if	(!$total_price)		$total_price	= $sale_price * $ea;

		if	($this->member_seq > 0 && $coupon_download_seq){
			$downloads	= $this->couponSales[$coupon_download_seq];

			if	($downloads){
				$sale_status	= true;

				// 사용제한 확인
				$sale_status	= $this->chk_issues($downloads['issue_type'], $downloads['category'], $downloads['goods']);

				// 적용대상 입점사 체크 ( 할인분담금 )
				if	( !in_array($downloads['type'],  $this->except_providerchk_coupon ) ){
					if	($downloads['provider_list']){
						if	(!strstr($downloads['provider_list'], '|'.$this->goods['provider_seq'].'|') && $data['provider_list'] != $goods_info['provider_seq'])
							$sale_status	= false;
					}else{
						if	($this->goods['provider_seq'] != 1)
							$sale_status	= false;
					}
				}

				if(get_cookie('shopReferer')){
					$shopReferer = get_cookie('shopReferer');
				}else{
					$shopReferer = $this->referer_url;
				}

				if	( $sale_status && $shopReferer ){
					if	( couponordercheck($downloads, $this->goods_seq, $sale_price, $this->goods_ea) !== true ) {
						$sale_status	= false;
					}
				}

				if	($sale_status){
					if	( $downloads['limit_goods_price'] <= $total_price ) {//사용제한 원이상인경우만
						if	( $downloads['sale_type'] == 'percent' && $downloads['percent_goods_sale'] && $sale_price ){
							$return['sale_price']	= $downloads['percent_goods_sale'] * $sale_price / 100;

							if	($downloads['max_percent_goods_sale'] < $return['sale_price']){
								$return['sale_price']	= $downloads['max_percent_goods_sale'];
							}
						}elseif	( $downloads['sale_type'] != 'percent' && $downloads['won_goods_sale'] && $sale_price ){
							// 상품금액($sale_price) 보다 쿠폰할인금액이 큰 경우 상품금액으로 할인되도록 수정
							$return['sale_price']	= ($downloads['won_goods_sale'] >= $sale_price) ? $sale_price : $downloads['won_goods_sale'] ;

							if ( $downloads['duplication_use'] == 1 ) {
								$sale_option_price = $sale_option_price / $this->ea;
							}

							//상품의 할인적용금액보다 쿠폰 할인금액이 큰경우 쿠폰 할인금액 재계산
							if ( $downloads['type'] != 'ordersheet' && $return['sale_price'] > $sale_option_price ) {
								$return['sale_price'] = $sale_option_price;
							}
						}

						$return['sale_price']		= $this->cut_sale_price($return['sale_price']);
						if	($downloads['duplication_use'] == 1){
							$return['sale_price']	= $this->cut_sale_price($return['sale_price'] * $ea);
						}else{
							$return['sale_price']	= $this->cut_sale_price($return['sale_price']);
						}

						if ( $downloads['type'] != 'ordersheet' ) {
							$this->sale_option_price -= $return['sale_price'];
						}

						$this->coupon_salescost['admin']	= $downloads['salescost_admin'];
						$this->coupon_salescost['provider']	= $downloads['salescost_provider'];
						$this->coupon_salescost['list']		= $downloads['provider_list'];
					}
					$this->coupon_use_array[]	= $coupon_download_seq;

					// 단독쿠폰체크
					if	($downloads['coupon_same_time'] == 'N' ) {
						if( !in_array($coupon_download_seq, $this->coupon_same_time_n) ) {
							$this->coupon_same_time_n[]		= $coupon_download_seq;
						}
						if	( $downloads['duplication_use'] != 1) {
							$this->coupon_duplication_n[$coupon_download_seq]++;
						}
					}else{
						if( !in_array($coupon_download_seq, $this->coupon_same_time_y) ) {
							$this->coupon_same_time_y[]		= $coupon_download_seq;
						}
					}

					//무통장만 사용가능
					if	($downloads['sale_payment'] == 'b' ) {
						if( !in_array($coupon_download_seq, $this->coupon_sale_payment_b) )
							$this->coupon_sale_payment_b[]	= $coupon_download_seq;
					}

					//모바일만 사용가능> 모바일기기체크
					if	($downloads['sale_agent'] == 'm') {// && !$this->_is_mobile_agent
						if( !in_array($coupon_download_seq, $this->coupon_sale_agent_m) )
							$this->coupon_sale_agent_m[]	= $coupon_download_seq;
					}

					$return['sale_seq']			= $downloads['coupon_seq'];
					$return['sale_subject']		= $downloads['coupon_name'];
					$return['sale_txt']			= $downloads['coupon_name'] . ' (';
					if	($downloads['limit_goods_price'] > 0){
						//이상 구매 시
						$return['sale_txt']		.= get_currency_price($downloads['limit_goods_price'],2).' '.getAlert('gv063').' ';
					}
					if	($downloads['sale_type'] == 'percent'){
						if	($downloads['duplication_use'] == '1'){
//							$return['sale_txt']	.= '상품 1개당 '.$downloads['percent_goods_sale'].'% 추가할인';
							$return['sale_txt']	.= getAlert('gv064',$downloads['percent_goods_sale']);
						}else{
//							$return['sale_txt']	.= '상품 1개 '.$downloads['percent_goods_sale'].'% 추가할인';
							$return['sale_txt']	.= getAlert('gv065',$downloads['percent_goods_sale']);
						}
						if	($downloads['max_percent_goods_sale'] > 0){
//							$return['sale_txt']	.= ' 단, 최대 '.get_currency_price($downloads['max_percent_goods_sale'],2).' 할인';
							$return['sale_txt']	.= getAlert('gv066',get_currency_price($downloads['max_percent_goods_sale'],2));
						}
					}else{
						if	($downloads['duplication_use'] == '1'){
//							$return['sale_txt']	.= '상품 1개당 '.get_currency_price($downloads['won_goods_sale'],2).' 추가할인';
							$return['sale_txt']	.= getAlert('gv067',get_currency_price($downloads['won_goods_sale'],2));
						}else{
//							$return['sale_txt']	.= '상품 1개 '.get_currency_price($downloads['won_goods_sale'],2).' 추가할인';
							$return['sale_txt']	.= getAlert('gv068',get_currency_price($downloads['won_goods_sale'],2));
						}
					}

					$return['sale_txt']		.= ')';
				}
			}
		}

		return $return;
	}

	## 코드 할인
	public function list_code_sale(){

		$return['sale_price']	= 0;
		$return['sale_title']	= getAlert('gv093'); //할인코드
		$sessid					= session_id();
		$promotion_seq			= $this->ci->session->userdata('cart_promotioncodeseq_'.$sessid);
		$promotion_code			= $this->ci->session->userdata('cart_promotioncode_'.$sessid);
		$total_price			= $this->total_price;
		$sale_option_price		= $this->sale_option_price;
		if	(!$total_price)		$total_price	= $this->sale_price * $this->ea;
		if	($promotion_seq && $promotion_code){
			$code			= $this->codeSales;
			$sale_status	= true;

			// 사용제한 확인
			$sale_status	= $this->chk_issues($code['issue_type'], $code['category'], $code['goods'], $code['brand']);

			// 할인부담금 관련 적용 대상 입점사 여부 체크
			if	( $sale_status && (($code['provider_list'] && strstr($code['provider_list'], '|'.$this->goods['provider_seq'].'|')) || (!$code['provider_list'] && $this->goods['provider_seq'] == 1)) )	$sale_status	= true;
			else								$sale_status	= false;

			if	($sale_status){
				if( $code['limit_goods_price'] <= $total_price ) {
					if( $code['sale_type'] == 'percent' && $code['percent_goods_sale'] && $this->sale_price ){
						$return['sale_price']		= $code['percent_goods_sale'] * $this->sale_price / 100;
						if($code['max_percent_goods_sale'] < $return['sale_price']){
							$return['sale_price']	= $code['max_percent_goods_sale'];
						}
					}elseif( $code['sale_type'] != 'percent' && $code['won_goods_sale'] && $this->sale_price ){
						$return['sale_price']		= $code['won_goods_sale'];

						if ( $code['duplication_use'] == 1 ) {
							$sale_option_price = $sale_option_price / $this->ea;
						}

						//상품의 할인적용금액보다 코드할인금액이 큰경우 코드할인금액 재계산
						if ( $return['sale_price'] > $sale_option_price ) {
							$return['sale_price'] = $sale_option_price;
						}
					}

					$this->code_salescost['admin']		= get_cutting_price($code['salescost_admin']);
					$this->code_salescost['provider']	= get_cutting_price($code['salescost_provider']);
					$this->code_salescost['list']		= $code['provider_list'];
				}

				$return['sale_price']				= get_cutting_price($return['sale_price']);
				if	( strstr($code['type'], 'promotion') && in_array($code['sale_type'], array('percent', 'won')) && $code['duplication_use'] == 1) {
					$return['sale_price']			= $return['sale_price'] * $this->ea;
				}
				$this->sale_option_price -= $return['sale_price'];

				$this->code_seq	= $code['promotion_seq'];
				if($code['promotion_seq'] && ($code['sale_type'] == 'percent' || $code['sale_type'] == 'won' )){
					$return['sale_seq']			= $code['promotion_seq'];
					$return['sale_subject']		= $code['promotion_name'];

					if	($code['sale_type'] == 'won'){
						//할인
						$return['sale_txt']		= '['.$promotion_code.'] '.get_currency_price($code['won_goods_sale'],2).' '.getAlert('gv069');
					}else{
						$return['sale_txt']		= '['.$promotion_code.'] '.number_format($code['percent_goods_sale']).'% '.getAlert('gv069');
						if	($code['max_percent_goods_sale'] > 0){
							//최대
							$return['sale_txt']	.= '('.getAlert('gv073').' '.get_currency_price($code['max_percent_goods_sale'],2).')';
						}
					}
				}
			}
		}

		return $return;
	}

	## 유입경로 할인
	public function list_referer_sale(){

		$return['sale_price']	= 0;
		$return['sale_title']	= getAlert('gv094'); //유입경로

		if(get_cookie('shopReferer')){

			$total_price			= $this->total_price;
			if	(!$total_price)		$total_price	= $this->sale_price * $this->ea;
			$sale_option_price		= $this->sale_option_price;

			$refererlist	= $this->refererSales;

			if	($refererlist)foreach($refererlist as $ref){
				$sale_status	= false;

				// 사용제한 확인
				$sale_status	= $this->chk_issues($ref['issue_type'], $ref['category'], $ref['goods']);

				// 할인부담금 관련 적용 대상 입점사 여부 체크
				// 2015-05-04 jhr 특정 상품 할인 적용 수정
				if	($sale_status && (($ref['provider_list'] && strstr($ref['provider_list'], '|'.$this->goods['provider_seq'].'|')) || (!$ref['provider_list'] && $this->goods['provider_seq'] == 1)))	$sale_status	= true;
				else								$sale_status	= false;

				// 총 상품금액 제한 확인
				if	($sale_status){
					if	($ref['limit_goods_price'] > 0 && $ref['limit_goods_price'] > $total_price){
						$sale_status	= false;
					}
				}

				if	($sale_status){
					// 개당 할인 금액 계산 ( 고객에게 유리한 할인을 적용하기 위한 할인금액 비교 )
					if		($ref['sale_type'] == 'percent' && $ref['percent_goods_sale']){
						$sale_price			= $this->sale_price * ($ref['percent_goods_sale'] / 100);
					}elseif	($ref['sale_type'] != 'percent' && $ref['won_goods_sale']){
						$sale_price			= $ref['won_goods_sale'];
						// 상품의 할인적용금액보다 유입경로 할인금액이 큰경우 유입경로 할인금액 재계산
						if( $sale_price > $sale_option_price / $this->ea ) {
							$sale_price = $sale_option_price / $this->ea;
						}
					}

					if	($return['sale_price'] < $sale_price){
						$current_cfg			= $ref;
						$return['sale_price']	= get_cutting_price($sale_price);
					}
				}
			}

			if	($current_cfg){
					$this->referer_salecode['admin']	= $current_cfg['salescost_admin'];
					$this->referer_salecode['provider']	= $current_cfg['salescost_provider'];
					$this->referer_salecode['list']		= $current_cfg['provider_list'];
					$return['sale_seq']					= $current_cfg['referersale_seq'];
					$this->referer_seq					= $current_cfg['referersale_seq'];
					$return['sale_price']				= $return['sale_price'] * $this->ea;
					$return['sale_subject']				= $current_cfg['referersale_name'];
					$return['sale_txt']					= $current_cfg['referersale_url'];
					$this->sale_option_price 			-= $return['sale_price'];
				if	($current_cfg['sale_type'] == 'percent'){
					$return['sale_txt']	.= ' '.$current_cfg['percent_goods_sale'].'% '.getAlert('gv074'); //추가할인
					if	($current_cfg['max_percent_goods_sale'] > 0){
						//최대
						$return['sale_txt']	.= ' ('.getAlert('gv073').' '.get_currency_price($current_cfg['max_percent_goods_sale'],2).')';
					}
				}else{
					//추가할인
					$return['sale_txt']	.= ' '.get_currency_price($current_cfg['won_goods_sale'],2).' '.getAlert('gv074');
				}
			}
		}

		return $return;
	}


	##↑↑↑↑↑↑↑↑↑↑	1:N 할인 계산 함수들		↑↑↑↑↑↑↑↑↑↑##
	##↓↓↓↓↓↓↓↓↓↓	현재 목록에서 적용될 수 있는 할인들을 배열화		↓↓↓↓↓↓↓↓↓↓##

	## 기본 할인
	public function set_basic_sale(){
		//-----> interface adjust function -----//
		//----- nothing done -----//
		//----- but never delete this function <-----//
	}

	## 이벤트 할인
	public function set_event_sale_old(){
		//월요일|화요일|수요일|목요일|금요일|토요일|일요일
		$day_name_arr		= getAlert('gv071');
		$day_name_arr		= explode('|',$day_name_arr);
		$app_week_arr		= array("1"	=> $day_name_arr[0], "2"	=> $day_name_arr[1], "3"	=> $day_name_arr[2],
									"4"	=> $day_name_arr[3], "5"	=> $day_name_arr[4], "6"	=> $day_name_arr[5], "7"	=> $day_name_arr[6]);
		$eventSales			= array();
		$result				= $this->ci->eventmodel->get_today_event();
		if	($result)foreach($result as $k => $event){

			unset($data);
			$data			= $event;

			// 2. 이벤트 시간
			$start_time		= strtotime($event['start_date']);
			$end_time		= strtotime($event['end_date']);
			if	($event['app_start_time']){
				if	(date('Ymd', $start_time) == date('Ymd')){
					if	(substr($event['app_start_time'], 0, 2) >= date('H', $start_time)){
						$data['sTime']		= substr($event['app_start_time'], 0, 2);
					}else{
						$data['sTime']		= date('H', $start_time);
					}
				}else{
						$data['sTime']		= substr($event['app_start_time'], 0, 2);
				}
			}else{
				if	(date('Ymd', $start_time) == date('Ymd'))
					$data['sTime']			= date('H', $start_time);
				else
					$data['sTime']			= '00';
			}
			if	($event['app_end_time']){
				if	(date('Ymd', $end_time) == date('Ymd')){
					if	(substr($event['app_end_time'], 0, 2) <= date('H', $end_time))
						$data['eTime']		= substr($event['app_end_time'], 0, 2);
					else
						$data['eTime']		= date('H', $end_time);
				}else{
						$data['eTime']		= substr($event['app_end_time'], 0, 2);
				}
			}else{
				if	(date('Ymd', $end_time) == date('Ymd'))
					$data['eTime']			= date('H', $end_time);
				else
					$data['eTime']			= '23';
			}

			if	($event['event_type'] == 'solo'){
				$data['solo_start']			= date('Y-m-d', $start_time).' '.$data['sTime'];
				$data['solo_end']			= date('Y-m-d', $end_time).' '.$data['eTime'];
			}

			if($event['daily_event'] && $event['app_week']){
				for($i = 0; $i < strlen($event['app_week']); $i++){
					$app_week	= substr($event['app_week'],$i,1);
					if($app_week_arr[$app_week])	$app_week_title[]	= $app_week_arr[$app_week];
				}
				$data['app_week_title'] = implode(', ',$app_week_title);
			}

			// 3. 상품 선택 기준
			$data['goods_kind']				= $event['apply_goods_kind'];
			$data['goods_rule']				= $event['goods_rule'];

			// 혜택정보
			$benefit		= $this->ci->eventmodel->get_event_benefit($event['event_seq']);
			if	($benefit)foreach($benefit as $b => $bnf){
				$goods						= '';
				$category					= '';
				$except_goods				= '';
				$except_category			= '';

				// 상품/카테고리
				$choice		= $this->ci->eventmodel->get_event_choice($event['event_seq'], $bnf['event_benefits_seq']);
				if	($choice)foreach($choice as $i => $chc){
					if			($chc['choice_type'] == 'goods'){
						if		($goods)			$goods				.= ','.$chc['goods_seq'];
						else						$goods				.= $chc['goods_seq'];
					}elseif		($chc['choice_type'] == 'category'){
						if		($category)			$category			.= ','.$chc['category_code'];
						else						$category			.= $chc['category_code'];
					}elseif		($chc['choice_type'] == 'except_goods'){
						if		($except_goods)		$except_goods		.= ','.$chc['goods_seq'];
						else						$except_goods		.= $chc['goods_seq'];
					}elseif	($chc['choice_type'] == 'except_category'){
						if		($except_category)	$except_category	.= ','.$chc['category_code'];
						else						$except_category	.= $chc['category_code'];
					}
				}

				$data['target_sale']		= $bnf['target_sale'];
				$data['event_sale']			= $bnf['event_sale'];
				$data['event_reserve']		= $bnf['event_reserve'];
				$data['event_point']		= $bnf['event_point'];
				$data['saller_rate_type']	= $bnf['saller_rate_type'];
				$data['saller_rate']		= $bnf['saller_rate'];

				// 할인 분담금 관련 추가 :: 2018-05-04 lwh
				$data['salescost_admin']	= $bnf['salescost_admin'];
				$data['salescost_provider']	= $bnf['salescost_provider'];
				$data['provider_list']		= $bnf['provider_list'];

				$data['goods']				= $goods;
				$data['category']			= $category;
				$data['exception_goods']	= $except_goods;
				$data['exception_category']	= $except_category;

				$eventSales[]				= $data;
			}
		}

		$this->eventSales	= $eventSales;
	}
	
	## 이벤트 할인
	public function set_event_sale(){
		if( $this->eventSales ) return $this->eventSales;
		
		//월요일|화요일|수요일|목요일|금요일|토요일|일요일
		$day_name_arr		= getAlert('gv071');
		$day_name_arr		= explode('|',$day_name_arr);
		$app_week_arr		= array("1"	=> $day_name_arr[0], "2"	=> $day_name_arr[1], "3"	=> $day_name_arr[2],
						"4"	=> $day_name_arr[3], "5"	=> $day_name_arr[4], "6"	=> $day_name_arr[5], "7"	=> $day_name_arr[6]);
		$eventSales			= array();
		$result				= $this->ci->eventmodel->get_today_event();
		
		if	($result) foreach($result as $k => $aEvent){
			$aEventSeq[]	= $aEvent['event_seq'];
		}
		
		if( $aEventSeq ){
			$aEventChoice		= $this->ci->eventmodel->get_event_choices($aEventSeq);
			$aEventBenefit		= $this->ci->eventmodel->get_event_benefits($aEventSeq);
		}
		
		if	($result) foreach($result as $k => $event){
			
			unset($data);
			$data			= $event;
			
			// 이벤트 페이지 접속불가인 경우 리스트에서 이벤트가 적용 안되도록 수정 2019-03-22
			if($data['display'] != 'y') continue;

			// 2. 이벤트 시간
			$start_time		= strtotime($event['start_date']);
			$end_time		= strtotime($event['end_date']);
			if	($event['app_start_time']){
				if	(date('Ymd', $start_time) == date('Ymd')){
					if	(substr($event['app_start_time'], 0, 2) >= date('H', $start_time)){
						$data['sTime']		= substr($event['app_start_time'], 0, 2);
					}else{
						$data['sTime']		= date('H', $start_time);
					}
				}else{
					$data['sTime']		= substr($event['app_start_time'], 0, 2);
				}
			}else{
				if	(date('Ymd', $start_time) == date('Ymd'))
					$data['sTime']			= date('H', $start_time);
					else
						$data['sTime']			= '00';
			}
			if	($event['app_end_time']){
				if	(date('Ymd', $end_time) == date('Ymd')){
					if	(substr($event['app_end_time'], 0, 2) <= date('H', $end_time))
						$data['eTime']		= substr($event['app_end_time'], 0, 2);
						else
							$data['eTime']		= date('H', $end_time);
				}else{
					$data['eTime']		= substr($event['app_end_time'], 0, 2);
				}
			}else{
				if	(date('Ymd', $end_time) == date('Ymd'))
					$data['eTime']			= date('H', $end_time);
					else
						$data['eTime']			= '23';
			}
			
			if	($event['event_type'] == 'solo'){
				$data['solo_start']			= date('Y-m-d', $start_time).' '.$data['sTime'];
				$data['solo_end']			= date('Y-m-d', $end_time).' '.$data['eTime'];
			}
			
			if($event['daily_event'] && $event['app_week']){
				for($i = 0; $i < strlen($event['app_week']); $i++){
					$app_week	= substr($event['app_week'],$i,1);
					if($app_week_arr[$app_week])	$app_week_title[]	= $app_week_arr[$app_week];
				}
				$data['app_week_title'] = implode(', ',$app_week_title);
			}
			
			// 3. 상품 선택 기준
			$data['goods_kind']				= $event['apply_goods_kind'];
			$data['goods_rule']				= $event['goods_rule'];
			
			// 혜택정보
			if	($aEventBenefit[$event['event_seq']]) foreach($aEventBenefit[$event['event_seq']] as $b => $bnf){
				$goods						= '';
				$category					= '';
				$except_goods				= '';
				$except_category			= '';
				
				// 상품/카테고리
				if	($aEventChoice[$event['event_seq']]) foreach($aEventChoice[$event['event_seq']] as $i => $chc){
					if( $bnf['event_benefits_seq'] != $chc['event_benefits_seq'] ) continue;
					if			($chc['choice_type'] == 'goods'){
						if		($goods)			$goods				.= ','.$chc['goods_seq'];
						else						$goods				.= $chc['goods_seq'];
					}elseif		($chc['choice_type'] == 'category'){
						if		($category)			$category			.= ','.$chc['category_code'];
						else						$category			.= $chc['category_code'];
					}elseif		($chc['choice_type'] == 'except_goods'){
						if		($except_goods)		$except_goods		.= ','.$chc['goods_seq'];
						else						$except_goods		.= $chc['goods_seq'];
					}elseif	($chc['choice_type'] == 'except_category'){
						if		($except_category)	$except_category	.= ','.$chc['category_code'];
						else						$except_category	.= $chc['category_code'];
					}
				}
				
				$data['target_sale']		= $bnf['target_sale'];
				$data['event_sale']			= $bnf['event_sale'];
				$data['event_reserve']		= $bnf['event_reserve'];
				$data['event_point']		= $bnf['event_point'];
				$data['saller_rate_type']	= $bnf['saller_rate_type'];
				$data['saller_rate']		= $bnf['saller_rate'];
				
				//2017-12-22 이벤트 추가 할인으로 인한 정산 금액 전달
				$data['rate_type_saco']		= $bnf['rate_type_saco'];
				$data['saco_value']			= $bnf['saco_value'];
				$data['rate_type_suco']		= $bnf['rate_type_suco'];		//정산:공급율 조정
				$data['suco_value']			= $bnf['suco_value'];
				$data['rate_type_supr']		= $bnf['rate_type_supr'];		//정산:공급가 조정
				$data['supr_value']			= $bnf['supr_value'];

				// 할인 분담금 관련 추가 :: 2018-05-04 lwh
				$data['salescost_admin']	= $bnf['salescost_admin'];
				$data['salescost_provider']	= $bnf['salescost_provider'];
				$data['provider_list']		= $bnf['provider_list'];
				
				$data['goods']				= $goods;
				$data['category']			= $category;
				$data['exception_goods']	= $except_goods;
				$data['exception_category']	= $except_category;
				
				$eventSales[]				= $data;
			}
		}
		
		$this->eventSales	= $eventSales;
	}

	## 복수구매할인 할인
	public function set_multi_sale(){
		//-----> interface adjust function -----//
		//----- nothing done -----//
		//----- but never delete this function <-----//
	}

	## 등급 할인	
	public function set_member_sale(){
		if( !$this->ci->GlGroupSales ){
			$groupsale		= $this->ci->membermodel->get_group_sale_list();
			if	($groupsale)foreach($groupsale as $g => $group){
				$sale_seqs[] = $group['sale_seq'];
			}
			if( $sale_seqs ){
				$groupdetailQuery		= $this->ci->membermodel->get_group_sale_detail_sales($sale_seqs);
				foreach($groupdetailQuery->result_array() as $detail){
					$benefit[$detail['sale_seq']][$detail['group_seq']]	= $detail;
				}
	
				$groupcategoryQuery		= $this->ci->membermodel->get_group_sale_issuecategory_sales($sale_seqs);
				if	($groupcategoryQuery)foreach($groupcategoryQuery->result_array() as $cate){
					if	($cate['type'] == 'emoney')	$category[$cate['sale_seq']]['emoney'][]	= $cate['category_code'];
					else							$category[$cate['sale_seq']]['sale'][]	= $cate['category_code'];
				}
	
				$groupgoodsQuery			= $this->ci->membermodel->get_group_sale_issuegoods_sales($sale_seqs);
				if	($groupgoodsQuery)foreach($groupgoodsQuery->result_array() as $goodsinfo){
					if	($goodsinfo['type'] == 'emoney')$goods[$goodsinfo['sale_seq']]['emoney'][]	= $goodsinfo['goods_seq'];
					else								$goods[$goodsinfo['sale_seq']]['sale'][]		= $goodsinfo['goods_seq'];
				}
			}
			if	($groupsale) foreach($groupsale as $g => $group){
				$data['benefit']	= $benefit[$group['sale_seq']];
				$data['category']	= $category[$group['sale_seq']];
				$data['goods']		= $goods[$group['sale_seq']];
				$groupSales[$group['sale_seq']]	= $data;
			}
		}

		if( !$this->ci->GlGroupSales && $groupSales ) $this->ci->GlGroupSales	= $groupSales;
		if( $this->ci->GlGroupSales ) $this->groupSales = $this->ci->GlGroupSales;
	}

	## 모바일 할인
	public function set_mobile_sale(){
		$sc['type']			= 'mobile';
		$mobileSales		= $this->ci->configsalemodel->lists($sc);
		$this->mobileSales	= $mobileSales;
	}

	## 좋아요 할인
	public function set_like_sale(){
		// 좋아요 여부 체크
		if( $this->ci->session->userdata('fbuser') ) {
			$sns_id	= $this->ci->session->userdata('fbuser');
		}elseif(get_cookie('fbuser')){
			$sns_id	= get_cookie('fbuser');
		}
		if	($this->member_seq > 0 || $sns_id){
			if($this->member_seq && $sns_id){
				$sc['whereis']	= " and ( member_seq = '".$this->member_seq."' or sns_id = '".$sns_id."' ) ";
			}elseif($this->member_seq && !$sns_id){
				$sc['whereis']	= " and member_seq = '".$this->member_seq."'";
			}elseif(!$this->member_seq && $sns_id){
				$sc['whereis']	= " and sns_id = '".$sns_id."' ";
			}
			$fbgoodslist	= $this->ci->goodsfblike->fblike_list_search($sc);
			if	($fbgoodslist['result'])foreach($fbgoodslist['result'] as $fb){
				if	(!in_array($fb['goods_seq'], $fbgoods)) $fbgoods[]	= $fb['goods_seq'];
			}
		}else{//회원또는 페이스북정보가 없을때
			$sc['whereis']	= " and session_id = '".session_id()."' ";
			$fbgoodslist	= $this->ci->goodsfblike->fblike_list_search($sc);
			if	($fbgoodslist['result'])foreach($fbgoodslist['result'] as $fb){
				if	(!in_array($fb['goods_seq'], $fbgoods)) $fbgoods[]	= $fb['goods_seq'];
			}
		}

		if	(count($fbgoods) > 0){
			$sc['type']	= 'fblike';
			$fblike_cfg_list			= $this->ci->configsalemodel->lists($sc);
			$this->likeSales['goods']	= $fbgoods;
			$this->likeSales['config']	= $fblike_cfg_list['result'];
		}
	}

	## 쿠폰 할인 ( 회원전용임 )
	public function set_coupon_sale(){
		if	($this->member_seq > 0){
			$sc['only_cart_goods']	= 'y';
			$sc['member_seq']		= $this->member_seq;
			$sc['use_status']		= 'unused';
			$sc['couponDate']		= array('available');
			$mycoupons				= $this->ci->couponmodel->my_download_list($sc, true);
			if	($mycoupons['result'])foreach($mycoupons['result'] as $k => $coupon){
				unset($data);
				$category = $goods = array();

				$data				= $coupon;
				$issuecategory		= $this->ci->couponmodel->get_coupon_download_issuecategory($coupon['download_seq']);
				if	($issuecategory)foreach($issuecategory as $cate){
					$category[]		= $cate['category_code'];
				}
				$issuegoods			= $this->ci->couponmodel->get_coupon_download_issuegoods($coupon['download_seq']);
				if	($issuegoods)foreach($issuegoods as $goodsinfo){
					$goods[]		= $goodsinfo['goods_seq'];
				}
				$data['category']	= $category;
				$data['goods']		= $goods;


				$couponSales[$coupon['download_seq']]		= $data;
			}

			$this->couponSales		= $couponSales;
		}
	}
	
	## 주문서 쿠폰할인
	public function set_ordersheet_sale(){
		if($this->ordersheet_coupon_seq && $this->sum_option_total_price){
			$ordersheet_sale = $this->list_ordersheet_coupon_sale($this->ordersheet_coupon_seq, $this->sum_option_total_price, 1);
			if($ordersheet_sale['sale_price']){
				$this->ordersheet_obj		= $ordersheet_sale;
				$this->ordersheet_sale_cal	= $ordersheet_sale['sale_price'];
			}
		}
	}

	## 코드 할인
	public function set_code_sale(){
		$sessid			= session_id();
		$promotion_seq	= $this->ci->session->userdata('cart_promotioncodeseq_'.$sessid);
		$promotion_code	= $this->ci->session->userdata('cart_promotioncode_'.$sessid);
		if	($promotion_seq && $promotion_code){
			$promotion				= $this->ci->promotionmodel->get_promotion($promotion_seq);
			$issuegoods				= $this->ci->promotionmodel->get_promotion_issuegoods($promotion_seq);
			if	($issuegoods)foreach($issuegoods as $goodsinfo){
				$goods[]			= $goodsinfo['goods_seq'];
			}
			$issuecategory			= $this->ci->promotionmodel->get_promotion_issuecategory($promotion_seq);
			if	($issuecategory)foreach($issuecategory as $categoryinfo){
				$category[]			= $categoryinfo['category_code'];
			}
			$issuebrand				= $this->ci->promotionmodel->get_promotion_issuebrand($promotion_seq);
			if	($issuebrand)foreach($issuebrand as $brandinfo){
				$brand[]			= $brandinfo['brand_code'];
			}
			$promotion['goods']		= $goods;
			$promotion['category']	= $category;
			$promotion['brand']		= $brand;

			$this->codeSales	= $promotion;
		}
	}

	## 유입경로 할인
	public function set_referer_sale(){

		if(get_cookie('shopReferer')) {
			$nowreferer	= $this->ci->referermodel->get_referersale_target_list(get_cookie('shopReferer'));
			if	($nowreferer)foreach($nowreferer as $referer){
				if	($referer['issue_type'] != 'all'){
					$issuegoods		= $this->ci->referermodel->get_referersale_issuegoods($referer['referersale_seq']);
					if	($issuegoods)foreach($issuegoods as $goodsinfo){
						$goods[]	= $goodsinfo['goods_seq'];
					}
					$issuecategory	= $this->ci->referermodel->get_referersale_issuecategory($referer['referersale_seq']);
					if	($issuecategory)foreach($issuecategory as $categoryinfo){
						$category[]	= $categoryinfo['category_code'];
					}
				}

				## 허용인데 허용 대상 상품, 카테고리가 없는 경우 ( 전체 사용불가와 같음 )
				if	($referer['issue_type'] == 'issue' && !$issuegoods && !$issuecategory) continue;

				$referer['goods']		= $goods;
				$referer['category']	= $category;
				$refererSales[]			= $referer;
			}
			$this->refererSales			= $refererSales;
		}
	}

	##↑↑↑↑↑↑↑↑↑↑	현재 목록에서 적용될 수 있는 할인들을 배열화		↑↑↑↑↑↑↑↑↑↑##
	##↓↓↓↓↓↓↓↓↓↓	마일리지 계산				↓↓↓↓↓↓↓↓↓↓##

	## 마일리지 계산의 기준이 되는 할인가 추가 계산
	public function price_for_reserve($type, $price, $reserve_unit){
		$total_price			= $this->total_price;
		if	(!$total_price)		$total_price	= $this->sale_price * $this->ea;
		if	($this->reserve_cfg['default_reserve_limit'] == 3 && $this->tot_use_emoney > 0){
			$this_use_emoney	= $this->ci->goodsmodel->get_reserve_standard_pay($this->sale_price, $this->ea, $total_price, $this->tot_use_emoney);
			$price				= $price - $this_use_emoney;
			if ($price < 0) $price = 0;
		}

		if	($type == 'PER'){
			$reserve		= get_cutting_price($reserve_unit * $price / 100);
		}else{
			if	($this->reserve_cfg['default_reserve_limit'] == 3 && $this->tot_use_emoney > 0){
				$reserve	= get_cutting_price(($reserve_unit/$this->sale_price) * $price);
			}else{
				$reserve	= get_cutting_price($reserve_unit);
			}
		}

		return $reserve;
	}

	## 이벤트 마일리지
	public function event_sale_reserve($result_price){
		$cfg		= $this->cfgs['event'];
		$reserve	= 0;
		if	($cfg){
			$reserve	= $this->price_for_reserve('PER', $result_price, $cfg['event_reserve']);
		}

		return $reserve;
	}

	## 회원 마일리지
	public function member_sale_reserve($result_one_price,$result_price){

		if	($this->total_price > 0)	$result_price	= $this->total_price;
		$groupSales				= $this->groupSales[$this->goods['sale_seq']];
		$issuecategory			= $groupSales['category']['emoney'];
		$issuegoods				= $groupSales['goods']['emoney'];

		// 제외 카테고리 체크
		$sale_possible		= true;
		if	( $this->category_code && is_array($issuecategory) ){
			foreach($this->category_code as $category_code){
				if	(in_array($category_code, $issuecategory)){
					$sale_possible		= false;
					break;
				}
			}
		}
		// 제외 상품 체크
		if	($sale_possible && in_array($this->goods_seq, $issuegoods))	$sale_possible	= false;

		$cfg		= $this->cfgs['member'];
		$reserve	= 0;
		if	($cfg && $sale_possible){
			if	($cfg['point_use'] == "N" || $cfg['point_limit_price'] <= $result_price){
				$reserve	= $this->price_for_reserve($cfg['reserve_price_type'], $result_one_price, $cfg['reserve_price']);
			}
		}

		return $reserve;
	}

	## 모바일 마일리지
	public function mobile_sale_reserve($result_price){
		$cfg		= $this->cfgs['mobile'];
		$reserve	= 0;
		if	($cfg){
			$reserve	= $this->price_for_reserve('PER', $result_price, $cfg['sale_emoney']);
		}

		return $reserve;
	}

	## 좋아요 마일리지
	public function like_sale_reserve($result_price){
		$cfg		= $this->cfgs['like'];
		$reserve	= 0;
		if	($cfg){
			$reserve	= $this->price_for_reserve('PER', $result_price, $cfg['sale_emoney']);
		}

		return $reserve;
	}

	##↑↑↑↑↑↑↑↑↑↑	마일리지 계산				↑↑↑↑↑↑↑↑↑↑##
	##↓↓↓↓↓↓↓↓↓↓	포인트 계산				↓↓↓↓↓↓↓↓↓↓##

	## 이벤트 마일리지
	public function event_sale_point($result_price){
		$cfg		= $this->cfgs['event'];
		$point	= 0;
		if	($cfg){
			$point = get_cutting_price($cfg['event_point'] * $result_price / 100);
		}

		return $point;
	}

	## 회원 포인트
	public function member_sale_point($result_one_price,$result_price){

		if	($this->total_price > 0)	$result_price	= $this->total_price;
		$groupSales				= $this->groupSales[$this->goods['sale_seq']];
		$issuecategory			= $groupSales['category']['emoney'];
		$issuegoods				= $groupSales['goods']['emoney'];

		// 제외 카테고리 체크
		$sale_possible		= true;
		if	( $this->category_code && is_array($issuecategory) ){
			foreach($this->category_code as $category_code){
				if	(in_array($category_code, $issuecategory)){
					$sale_possible		= false;
					break;
				}
			}
		}
		// 제외 상품 체크
		if	($sale_possible && in_array($this->goods_seq, $issuegoods))	$sale_possible	= false;

		$cfg		= $this->cfgs['member'];
		$point	= 0;
		if	($cfg && $sale_possible){
			if	($cfg['point_use'] == "N" || $cfg['point_limit_price'] <= $result_price){
				if($cfg['point_price_type'] == 'PER'){
					$point	= get_cutting_price($cfg['point_price'] * $result_one_price / 100);
				}else{
					$point	= get_cutting_price($cfg['point_price']);
				}
			}
		}

		return $point;
	}

	## 모바일 포인트
	public function mobile_sale_point($result_price){
		$cfg		= $this->cfgs['mobile'];
		$point	= 0;
		if	($cfg){
			$point = get_cutting_price($cfg['sale_point'] * $result_price / 100);
		}

		return $point;
	}

	## 좋아요 포인트
	public function like_sale_point($result_price){
		$cfg		= $this->cfgs['like'];
		$point	= 0;
		if	($cfg){
			$point	= get_cutting_price($cfg['sale_point'] * $result_price / 100);
		}

		return $point;
	}

	##↑↑↑↑↑↑↑↑↑↑	포인트 계산				↑↑↑↑↑↑↑↑↑↑##
}
