<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');
require_once(APPPATH ."controllers/base/front_base".EXT);
class partner extends front_base {
	public function __construct(){
		parent::__construct();
		$this->load->helper('order');
		$this->load->model('goodsmodel');
		$this->load->model('categorymodel');
		$this->load->model('membermodel');
		$this->load->model('configsalemodel');
		$this->load->model('partnermodel');

		set_time_limit(0);
		ini_set("memory_limit",-1);

		if( $this->config_system['pgCompany'] ){
			$payment_gateway = config_load($this->config_system['pgCompany']);
			$payment_gateway['rCardCompany'] = code_load($this->config_system['pgCompany'].'CardCompanyCode');

			foreach($payment_gateway['rCardCompany'] as $k => $v){
				$payment_gateway['arrCardCompany'][$v['codecd']]=$v['value'];
			}
		}

		if($payment_gateway['pcCardCompanyCode']) foreach($payment_gateway['pcCardCompanyCode'] as $k => $code)
		{
			$tmp = explode(',',$payment_gateway['pcCardCompanyTerms'][$k]);
			if( in_array($code,array('ALL')) ) $str_noint = $tmp[count($tmp)-1]."개월";

			if(count($tmp) > 1){
				$r_tmp_noint[] = $payment_gateway['arrCardCompany'][$code].$tmp[0].'~'.$tmp[count($tmp)-1];
			}else{
				$r_tmp_noint[] = $payment_gateway['arrCardCompany'][$code].$tmp[0];
			}
		}
		if(!$str_noint && $r_tmp_noint){
			$str_noint = implode('/',$r_tmp_noint);
		}
		$this->noint = $str_noint;
	}

	//할인쿠폰 상품상세
	public function _goods_coupon_max($goods)
	{
		$max = 0;
		$memberSeq = "";
		$today = date('Y-m-d',time());
		$this->load->model('couponmodel');
		$tmp = $this->goodsmodel -> get_goods_category($goods['goods_seq']);
		foreach($tmp as $data) $category[] = $data['category_code'];
		$result = $this->couponmodel->get_able_download_list($today,$memberSeq,$goods['goods_seq'],$category,$goods['price']);
		foreach($result as $key => $data){
			if($max < $data['goods_sale']) {
				$max = $data['goods_sale'];
				$maxCoupon = $data;
			}
		}
		return $maxCoupon;
	}

	public function apply_sale(&$data_goods)
	{
		$this->load->library('sale');

		$applypage		= 'list';
		if	(!$this->reserves)	$this->reserves	= config_load('reserve');

		//----> sale library 적용 ( 정가기준 목록에서는 sale_price를 넘기지 않음 )
		unset($param, $sales);
		$this->sale->reset_init();
		$param['cal_type']			= 'each';
		$param['option_type']		= 'option';
		$param['reserve_cfg']		= $this->reserves;
		$param['member_seq']		= 0;
		$param['group_seq']			= 0;
		$param['consumer_price']	= $data_goods['consumer_price'];
		$param['price']				= $data_goods['price'];
		$param['total_price']		= $data_goods['price'];
		$param['ea']				= 1;
		$param['goods_ea']			= 1;
		$param['category_code']		= $data_goods['r_category'];
		$param['goods_seq']			= $data_goods['goods_seq'];
		if ($data_goods['marketing_sale']) $param['marketing_sale']	 = $data_goods['marketing_sale'];
		$param['goods']				= $data_goods;
		$this->sale->set_init($param);
		$sales						= $this->sale->calculate_sale_price($applypage);
		if ($data_goods['marketing_sale'] && $sales['sale_list']['coupon'] > 0) {
			$data_goods['coupon_won'] = iconv("UTF-8","euc-kr",get_currency_price($sales['sale_list']['coupon'],3));
		}
		$data_goods['price']		= $sales['result_price'];

		return $data_goods['price'];
	}

	public function danawa()
	{
		header("Content-Type: text/html; charset=EUC-KR");
		$marketset = config_load('marketing');

		$last_update_date = '';
		if($_GET['mode'] == 'summary'){
			$mode = 'summary';
		}else{
			$mode = 'all';
		}

		$all_category = $this->categorymodel->get_all();
		foreach ($all_category as $row){
			$data['category_code'];
			$cate[$row['category_code']] = $row['title'];
		}

		// 마케팅 전달 이미지 lwh 2014-02-28
		$market_image	= config_load('marketing_image');
		if($market_image['naverImage']=='B' || !$market_image['naverImage']){
			$view_type	= "view";
		}else if($market_image['naverImage']=='C'){
			$view_type	= "large";
		}

		$query = $this->goodsmodel->get_goods_all_partner($last_update_date,$view_type,true); // 20130325
		$result = mysqli_query($this->db->conn_id,$query);
		while ($data_goods = mysqli_fetch_array($result)){ // 20130325

			$data_goods['goods_name']	= strip_tags(iconv('UTF-8', 'euc-kr',$data_goods['goods_name']));
			$data_goods['feed_evt_text']	= iconv('UTF-8', 'euc-kr',$data_goods['feed_evt_text']);

			$data_goods['goods_url'] = get_connet_protocol().$_SERVER['HTTP_HOST']."/goods/view?no=".$data_goods['goods_seq'].'&market=danawa';
			$mourl = str_replace('www.','',$data_goods['goods_url']);
			$data_goods['mourl'] = str_replace(get_connet_protocol(),get_connet_protocol().'m.',$mourl);

			if(preg_match('/http/', $data_goods['image'])){
				$data_goods['image_url'] = $data_goods['image'];
			}else{
				$data_goods['image_url'] = get_connet_protocol().$_SERVER['HTTP_HOST'].iconv('UTF-8', 'euc-kr',$data_goods['image']);
			}

			// 카테고리
			$page_code ='';
			$page_name ='';
			$arr_category_code = array();
			$arr_category_code = $this->categorymodel->split_category($data_goods['category_code']);
			for($i=0;$i<4;$i++) {
				$data_goods['arr_category_code'][$i] = "";
				$data_goods['arr_category'][$i] = "";
				if( $arr_category_code[$i] ){

					$data_goods['arr_category_code'][$i] = $arr_category_code[$i];
					$data_goods['arr_category'][$i] =  ($this->categorymodel->one_category_name($arr_category_code[$i]));

					$page_name .=iconv('UTF-8', 'euc-kr',$data_goods['arr_category'][$i])."|";
				}else{

					$page_name .="";
				}
			}
			$page_name =substr($page_name,0,-1);
			// 배송비
			if($data_goods['goods_kind'] == "coupon"){
				$data_goods['delivery'] = "0";
			}else{
			$delivery = $this->goodsmodel->get_goods_delivery($data_goods);
			if( $delivery['type'] == 'delivery' ){
				if( $delivery['free'] && $delivery['price'] ){
					if($delivery['free'] > $data_goods['price']){
						$data_goods['delivery'] = $delivery['price'];
					}else{
						$data_goods['delivery'] = "0";
					}
				}else{
					$data_goods['delivery'] = $delivery['price'];
				}
			}else{
				$data_goods['delivery'] = "-1";
			}
			}

			// 쿠폰
			//$coupon = $this -> _goods_coupon_max($data_goods['goods_seq']);
			//$data_goods['coupon'] = (int) $coupon['goods_sale'];

			// 구분
			if( $data_goods['regist_date'] > $last_update_date ){
				$data_goods['class'] = "I";
			}
			if( $data_goods['update_date'] > $last_update_date ){
				$data_goods['class'] = $arr_status[$data_goods['goods_status']];
			}
			// 2014-01-16 이벤트 문구 미노출 추가
			if($data_goods['feed_evt_sdate'] == '0000-00-00') $data_goods['feed_evt_sdate'] = '';
			if($data_goods['feed_evt_edate'] == '0000-00-00') $data_goods['feed_evt_edate'] = '';
			$data_goods['event']	= $data_goods['feed_evt_text'];
			if( time() < strtotime($data_goods['feed_evt_sdate'].' 00:00:00') || strtotime($data_goods['feed_evt_edate'].' 23:59:59') < time() ){
				$data_goods['event']	= '';
			}

			// 할인가 적용
			$data_goods['price'] = $this->apply_sale($data_goods);

			// 무이자
			$noint = trim(iconv('UTF-8', 'euc-kr',$this->noint));

			// 모바일가
			$systemmobiles = $this->configsalemodel->get_mobile_sale_for_goods($data_goods['price']);
			$mobile_price = 0;
			if($systemmobiles['sale_price'] && $data_goods['price']){
				$mobile_sale = $systemmobiles['sale_price'] * $data_goods['price'] / 100;
				$mobile_price = $data_goods['price'] - $mobile_sale;
			}

			unset($loop);
			$loop[] = $data_goods['goods_seq']; //1.상품ID
			$loop[] = $page_name; // 2. 카테고리
			$loop[] = $data_goods['goods_name']; //3. 상품명
			$loop[] = $data_goods['manufacture']; //4. 제조사
			$loop[] = $data_goods['image_url']; //5. 이미지 url
			$loop[] = $data_goods['goods_url']; //6. 상품 url
			$loop[] = $data_goods['price']; //7. 가격
			$loop[] = $data_goods['reserve']; //8. 마일리지
			$loop[] = $data_goods['coupo']; // 9. 할인쿠폰
			$loop[] = $noint; //10. 무이자할부
			$loop[] = '';//11. 사은품
			$loop[] = $data_goods['model']; //12. 모델명
			$loop[] = '';//13. 추가정보
			$loop[] = '';//14. 출시일
			$loop[] = $data_goods['delivery'];// 15. 배송료 : 필수
			$loop[] = '';// 16. 카드프로모션명
			$loop[] = '';//17. 카드프로모션가
			$loop[] = 'null';//18. 쿠폰다운로드필요여부
			$loop[] = $mobile_price == 0 ? '' : $mobile_price;//19. 모바일상품가격
			$loop[] = '';//20. 차등배송비여부
			$loop[] = '';//21. 차등배송비내용
			$loop[] = '';//22. 별도설치비유무
			$loop[] = '';//23. 재고유무

			echo implode("^",$loop);
			echo "\r\n";

		}

	}

	public function navercheckout_copy($shipping_method)
	{
		// ALTER TABLE `fm_cart` CHANGE `distribution` `distribution` ENUM( 'cart', 'direct', 'choice', 'admin', 'navercheckout' ) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT 'cart'

		$this->load->model('cartmodel');
		$this->cartmodel->delete_mode('navercheckout');
		$cart = $this->cartmodel->catalog();

		foreach($cart['list'] as $cart_option)
		{
			$arr_cart_option_seq[$cart_option['cart_seq']][] = $cart_option['cart_option_seq'];
		}

		$arr_cart_seq = array_keys($arr_cart_option_seq);
		if($arr_cart_seq){
			foreach($arr_cart_seq as $cart_seq){

				$arr_field = $this->db->list_fields('fm_cart');
				$query = "select * from fm_cart where cart_seq=?";
				$query = $this->db->query($query,$cart_seq);
				list($row_cart) = $query->result_array();
				$params = filter_keys($row_cart, $arr_field);

				unset($params['cart_seq']);
				$params['regist_date'] = date("Y-m-d H:i:s");
				$params['update_date'] = date("Y-m-d H:i:s");
				$params['distribution'] = "navercheckout";

				$result = $this->db->insert('fm_cart', $params);
				$new_cart_seq = $this->db->insert_id();

				foreach($arr_cart_option_seq[$cart_seq] as $cart_option_seq){
					$arr_field = $this->db->list_fields('fm_cart_option');
					$query = "select * from fm_cart_option where cart_option_seq=?";
					$query = $this->db->query($query,$cart_option_seq);
					list($row_cart_option) = $query->result_array();
					$params = filter_keys($row_cart_option, $arr_field);
					unset($params['cart_option_seq']);
					$params['cart_seq'] = $new_cart_seq;
					$params['shipping_method'] = $shipping_method;
					$result = $this->db->insert('fm_cart_option', $params);
					$new_cart_option_seq = $this->db->insert_id();

					$arr_field = $this->db->list_fields('fm_cart_suboption');
					$query = "select * from fm_cart_suboption where cart_option_seq=?";
					$query = $this->db->query($query,$cart_option_seq);
					foreach($query->result_array() as $row_cart_suboption){
						$params = filter_keys($row_cart_suboption, $arr_field);
						unset($params['cart_suboption_seq']);
						$params['cart_seq'] = $new_cart_seq;
						$params['cart_option_seq'] = $new_cart_option_seq;
						$result = $this->db->insert('fm_cart_suboption', $params);
					}

					$arr_field = $this->db->list_fields('fm_cart_input');
					$query = "select * from fm_cart_input where cart_option_seq=?";
					$query = $this->db->query($query,$cart_option_seq);
					foreach($query->result_array() as $row_cart_input){
						$params = filter_keys($row_cart_input, $arr_field);
						unset($params['cart_input_seq']);
						$params['cart_seq'] = $new_cart_seq;
						$params['cart_option_seq'] = $new_cart_option_seq;
						$result = $this->db->insert('fm_cart_input', $params);
					}
				}
			}
		}

	}

	public function navercheckout_makeQueryString($shopId,$id,$name,$count,$option,$tprice,$uprice,$option_code='') {
			$ret .= 'ITEM_ID=' . urlencode($id);
			$ret .= '&EC_MALL_PID=' . urlencode($id);
			$ret .= '&ITEM_NAME=' . urlencode($name);
			$ret .= '&ITEM_COUNT=' . $count;
			$ret .= '&ITEM_OPTION=' . urlencode($option);
			if($option_code){
				$ret .= '&ITEM_OPTION_CODE=' . urlencode($option_code);
			}else{
				$ret .= '&ITEM_OPTION_CODE=';
			}
			$ret .= '&ITEM_TPRICE=' . $tprice;
			$ret .= '&ITEM_UPRICE=' . $uprice;
			return $ret;
	}

	public function navercheckout()
	{
		$this->load->model('cartmodel');
		$this->load->model('Providershipping');

		$shipping_method = "delivery";
		if($_GET['shippingType'] == 'ONDELIVERY') $shipping_method = "postpaid";

		$_POST['navercheckout'] = true; // 네이버페이 flag값 추가

		if($_GET['mode']=='direct'){
			// 옵션 선택 ver 0.1일 경우
			if	($_POST['gl_option_select_ver'] == '0.1'){
				$_POST['shipping_method']	= $shipping_method;
				$chk_result	= $this->cartmodel->chk_cart_ver_0_1();
				if	(!$chk_result['status']){
					openDialogAlert($chk_result['errorMsg'], 400, 140, 'parent', '');
					exit;
				}else{
					$this->cartmodel->add_cart_ver_0_1();
				}
			}
		}
		$cart = $this->cartmodel->catalog();

		$navercheckout = config_load('navercheckout');
		$shopId = $navercheckout['shop_id'];
		$certiKey = $navercheckout['certi_key'];
		$shippingPrice = array_sum($cart['shipping_price']);

		// 도서공연비 소득공제 정책 추가 2018-09-19
		$culture = 'false';
		if($navercheckout['culture'] == 'all') $culture = 'true';
		$cultureY = $cultureN = 0;
		$cultureGoods = array();
		foreach($navercheckout['culture_goods'] as $v1){
			$culture_goods[] = $v1['goods_seq'];
		}

		if ($shippingPrice > 0) {
			$shippingType = "PAYED";
			if($_GET['shippingType']) $shippingType = $_GET['shippingType'];
		} else {
			$shippingType = "FREE";
		}

		$backUrl = $_SERVER['HTTP_REFERER'];
		$queryString = 'SHOP_ID='.urlencode($shopId);
		$queryString .= '&CERTI_KEY='.urlencode($certiKey);
		$queryString .= '&SHIPPING_TYPE='.$shippingType;
		$queryString .= '&SHIPPING_PRICE='.$shippingPrice;
		$queryString .= '&RESERVE1=&RESERVE2=&RESERVE3=&RESERVE4=&RESERVE5=';
		$queryString .= '&BACK_URL='.$backUrl;
		$queryString .= '&SA_CLICK_ID='.$_COOKIE['NVADID']; //CTS 네이버검색광고 이용가맹점 중 전환데이터를 원할경우 SA URL파라미터중 NVADID를 입력
		$queryString .= '&CPA_INFLOW_CODE='.urlencode($_COOKIE["CPAValidator"]);// CPA 스크립트 가이드 설치업체는 해당 값 전달
		$queryString .= '&NAVER_INFLOW_CODE='.urlencode($_COOKIE["NA_CO"]); // 네이버 서비스 유입 경로 코드
		$queryString .= '&NMILEAGE_INFLOW_CODE='; // 네이버마일리지 유입 경로 코드
		$totalMoney = 0;

		// 모바일/like 할인/적립
		$this->load->model('configsalemodel');
		$sc['type'] = 'mobile';
		$systemmobiles = $this->configsalemodel->lists($sc);
		$sc['type'] = 'fblike';
		$systemfblike = $this->configsalemodel->lists($sc);

		foreach($cart['list'] as $data_option){

			$tmp = $this->Providershipping->get_provider_shipping($data_option['provider_seq']);
			$arr_shipping_method = array_keys($tmp['shipping_method']);
			$tmp_shipping_method = str_replace("each_","",$data_option['shipping_method']);
			if( !in_array( $tmp_shipping_method,$arr_shipping_method ) ){
				//상품의 배송방법이 택배 선불,착불이 제공되어야 합니다.
				alert(getAlert('os098'));
				exit;
			}

			$num++;
			$sub_price = 0;
			$sub_option = "";
			$arr_option = array();
			$item_total_ea = 0;

			$arr_option = array();
			for($i=1;$i<6;$i++){
				$title_field = 'title'.$i;
				$option_field = 'option'.$i;
				if($data_option[$title_field] && $data_option[$option_field]){
					$arr_option[] = $data_option[$title_field].":".$data_option[$option_field];
				}
			}
			$option_str = $arr_option[0];

			if( $data_option['price'] < 1 ) continue;

			foreach($data_option['cart_inputs'] as $k2=>$data_input){
				$inputValue = $data_input['input_value'];
				if($data_input['type']=='file'){
					$inputValue = get_connet_protocol().$_SERVER['HTTP_HOST']."/data/order/".$inputValue;
				}
				if(trim($data_input['input_value'])) $arr_option[] = $data_input['input_title'].":".strip_tags($inputValue);
			}

			/* 회원할인계산 */
			$member_sale = 0;
			$members['group_seq'] = 0;
			$data_option['member_sale_unit'] = $this->membermodel->get_member_group($members['group_seq'],$data_option['goods_seq'],$category,$data_option['price'],$cart['total'], $data_option["sale_seq"]);

			// 모바일 할인
			if($this->_is_mobile_agent) {//mobile 접속시  %할인, 추가적립 $this->mobileMode  ||
				$data_option['mobile_sale'] = 0;
				foreach($systemmobiles['result'] as $fblike => $systemmobiles_price) {
					if($systemmobiles_price['price1']<= $cart['total'] && $systemmobiles_price['price2'] >= $cart['total']){
						$opt_mobile_goods_sale = $systemmobiles_price['sale_price'] * $data_option['price'] / 100; // 모바일 할인
						$opt_mobile_goods_sale = get_price_point($opt_mobile_goods_sale,$this->config_system);
						$data_option['mobile_sale_unit'] = $opt_mobile_goods_sale;
						$data_option['mobile_sale'] = ($opt_mobile_goods_sale * $data_option['ea']);
						break;
					}//endif
				}//end foreach
			}

			// like 할인
			$data_option['fblike_sale'] = 0;
			if($data_option['fblike'] == 'Y'){//facebook like %할인, 추가적립
				foreach($systemfblike['result'] as $fblike => $systemfblike_price) {
					if($systemfblike_price['price1']<= $cart['total'] && $systemfblike_price['price2'] >= $cart['total']){
						$opt_fblike_goods_sale = $systemfblike_price['sale_price'] * $data_option['price'] / 100; // 좋아요 할인
						$opt_fblike_goods_sale = get_price_point($opt_fblike_goods_sale,$this->config_system);
						$data_option['fblike_sale_unit'] = $opt_fblike_goods_sale;
						$data_option['fblike_sale'] = ($opt_fblike_goods_sale * $data_option['ea']);
						break;
					}//endif
				}//end foreach
			}

			## 유입경로 할인
			if($_COOKIE['shopReferer']){
				$this->load->model('referermodel');
				$referersale	= $this->referermodel->sales_referersale($_COOKIE['shopReferer'], $data_option['goods_seq'], $data_option['price'], 1);
				$data_option['referersale_seq'] = $referersale['referersale_seq'];
				$data_option['referer_sale_unit'] = $referersale['sales_price'];
			}

			$id = $data_option['goods_seq'];
			$data_goods['goods_name']	= strip_tags($data_goods['goods_name']);
			$name = $data_option['goods_name'];
			$uprice = $data_option['price'] - (int) $data_option['member_sale_unit'] - (int) $data_option['mobile_sale_unit']  - (int) $data_option['fblike_sale_unit'] - (int) $data_option['referer_sale_unit'];
			$count = $data_option['ea'];
			$tprice = $uprice * $count;
			$item_total_ea += $count;

			$option = implode(' / ',$arr_option);

			if( strlen($option) > 4000 ){
				//옵션의 길이가 너무 깁니다.
				alert(getAlert('os099'));
				exit;
			}

			//옵션상품코드
			list($data_option['optioncode1'],$data_option['optioncode2'],$data_option['optioncode3'],$data_option['optioncode4'],$data_option['optioncode5'],$data_option['color'],$data_option['zipcode'],$data_option[0]['address_type'],$data_option['address'],$data_option[0]['address_street'],$data_option['addressdetail'],$data_option['biztel'],$data_option['coupon_input'],$data_option['codedate'],$data_option['sdayinput'],$data_option['fdayinput'],$data_option['dayauto_type'],$data_option['sdayauto'],$data_option['fdayauto'],$data_option['dayauto_day'],$data_option['newtype'],$data_option['address_commission']) = $this->goodsmodel->get_goods_option_code(
				$data_option['goods_seq'],
				$data_option['option1'],
				$data_option['option2'],
				$data_option['option3'],
				$data_option['option4'],
				$data_option['option5']
			);
			$opt_goods_code = $data_option['goods_code'].$data_option['optioncode1'].$data_option['optioncode2'].$data_option['optioncode3'].$data_option['optioncode4'].$data_option['optioncode5'];//조합된상품코드

			$totalMoney += $tprice;
			$queryString .= '&'.$this->navercheckout_makeQueryString($shopId,$id,$name,$count,$option,$tprice,$uprice,$opt_goods_code);

			foreach($data_option['cart_suboptions'] as $data_sub){
				$arr_option = array();
				if( $data_option['price'] ){
					$arr_option[] =  $data_sub['suboption_title'].":".$data_sub['suboption'];
					if($option_str){
						$option = $option_str ."의 추가옵션 - ". implode(' / ',$arr_option);
					}else{
						$option = "추가옵션 - ". implode(' / ',$arr_option);
					}
					$id = $data_sub['goods_seq'];
					$name = $name;
					$uprice = $data_sub['price'];
					$count = $data_sub['ea'];
					$tprice = $uprice * $count;
					$totalMoney += $tprice;

					$subopt_goods_code = $data_option['goods_code'].$data_sub['suboption_code'];//조합된상품코드
					$queryString .= '&'.$this->navercheckout_makeQueryString($shopId,$id,$name,$count,$option,$tprice,$uprice,$subopt_goods_code);
				}else{
					//가격이 없는 추가옵션은 체크아웃으로 구매하실 수 없습니다.
					alert(getAlert('os100'));
					exit;
				}
			}

			$goods = $this->goodsmodel->get_goods($data_option['goods_seq']);
			$goods['goods_name']	= strip_tags($goods['goods_name']);

			// 예외카테고리 체크
			$categorys = $this->goodsmodel->get_goods_category($goods['goods_seq']);
			foreach($navercheckout['except_category_code'] as $v1){
				foreach($categorys as $v2){
					if($v1['category_code']==$v2 || preg_match("/^".$v1['category_code']."/",$v2)){
						//상품은 네이버 체크아웃 예외카테고리에 속해있습니다.
						openDialogAlert("{$goods['goods_name']} ".getAlert('os103'),400,140,'parent',"");
					exit;
					}
				}
			}

			// 예외상품 체크
			foreach($navercheckout['except_goods'] as $v1){
				if($v1['goods_seq']==$goods['goods_seq']){
					//상품은 네이버 체크아웃 예외상품입니다.
					openDialogAlert("{$goods['goods_name']} ".getAlert('os104'),400,140,'parent',"");
					exit;
				}
			}

			// 도서공연비 소득공제 정책 체크
			if($navercheckout['culture'] == 'choice') {
				if(in_array($goods['goods_seq'], $culture_goods)) {
					$cultureY +=1;
					$cultureGoods[] = $goods['goods_name'];
				} else {
					$cultureN +=1;
				}
			}
		}

		if($navercheckout['culture'] == 'choice') {
			if($cultureY>0 && $cultureN>0) {
				$cultureGoods = array_unique($cultureGoods);
				$_except_msg = "도서공연비 소득공제 대상 상품과 비대상 상품은<br/>함께 결제가 불가능합니다.<br/><br/>[도서공연비 소득공제 대상 상품]<br/>";
				foreach($cultureGoods as $val) {
					$_except_msg .= $val."<br/>";
				}
				openDialogAlert($_except_msg,400,200,'parent',$pg_cancel_script);
				exit;
			} else if($cultureY>0) {
				$culture = 'true';
			}
		}


		/**** 재고 체크 및 최대/최소 구매수량 체크 ****/
		foreach($cart['data_goods'] as $goods_seq => $data){

			$goods_name_strlen	= mb_strlen($data['goods_name']);

			if($goods_name_strlen > 15) $alert_h = 160;
			elseif($goods_name_strlen > 50) $alert_h = 175;
			elseif($goods_name_strlen > 100) $alert_h = 195;
			else $alert_h = 140;

			// 구매수량 체크
			if($data['optea']){
				$opteEa = $data['optea'];
			}else{
				$opteEa = $data['ea'];
			}
			if($data['min_purchase_ea'] && $data['min_purchase_ea'] > $opteEa){
				//은 '.$data['min_purchase_ea'].'개 이상 구매하셔야 합니다.
				openDialogAlert(addslashes($data['goods_name']).getAlert('os105',$data['min_purchase_ea']),400,$alert_h,'parent',$pg_cancel_script);
				exit;
			}
			if($data['max_purchase_ea'] && $data['max_purchase_ea'] < $opteEa){
				//'은 '.$data['max_purchase_ea'].'개 이상 구매하실 수 없습니다.'
				openDialogAlert(addslashes($data['goods_name']).getAlert('os106',($data['max_purchase_ea']+1)),400,$alert_h,'parent',$pg_cancel_script);
				exit;
			}

			// 단독이벤트만 판매시 이벤트기간이 아니면 판매중지 @2013-11-29
			if( $data['event']['event_goodsStatus'] === true ){
				//"↓아래 상품은 단독이벤트 기간에만 구매가 가능합니다.\\n".addslashes($data['goods_name']);
				$err_msg = getAlert('os107',addslashes($data['goods_name']));
				openDialogAlert($err_msg,400,140,'parent',$pg_cancel_script);
				exit;
			}

			if($data['ea_for_option'])foreach($data['ea_for_option'] as $option_key => $option_ea){
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
//					$err_msg = "↓아래 상품의 재고는 ".$chk['sale_able_stock']."개 입니다.";
//					$err_msg .= "<br/>".addslashes($data['goods_name']);
					$err_msg = getAlert('os108',addslashes($data['goods_name']));
					if($opttitle) $err_msg .= "(".$opttitle.")";
					openDialogAlert($err_msg,400,140,'parent',$pg_cancel_script);
					exit;
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
					if($option_r[1]) $opttitle .= $option_r[1];
//					$err_msg = "↓아래 상품의 재고는 ".$chk['sale_able_stock']."개 입니다.";
//					$err_msg .= "<br/>".addslashes($data['goods_name']);
					$err_msg = getAlert('os108',addslashes($data['goods_name']));
					if($opttitle) $err_msg .= "(".$opttitle.")";
					openDialogAlert($err_msg,400,140,'parent',$pg_cancel_script);
					exit;
				}
			}
		}
		/* **************************************************** */
		if( !$totalMoney ){
			//주문금액이 0원입니다.
			openDialogAlert(getAlert('os109',get_currency_price(0,2)),400,140,'parent',"");
			exit;
		}

		if($_GET['shippingType']=='ONDELIVERY'){
			$totalPrice = (int)$totalMoney;	// 착불배송시
		}else{
			$totalPrice = (int)$totalMoney + (int)$shippingPrice; // 선불배송시
		}
		$queryString .= '&TOTAL_PRICE='.$totalPrice;
		$queryString .= '&MCST_CULTURE_BENEFIT_YN='.$culture;	// 도서공연비 소득공제 정책

		if($navercheckout['use']=='test'){
			$orderUrl = 'https://test-checkout.naver.com/customer/api/order.nhn';
		}else{
			$orderUrl = 'https://checkout.naver.com/customer/api/order.nhn';
		}

		$cu = curl_init();
		curl_setopt($cu, CURLOPT_URL,$orderUrl); // 데이터를 보낼 URL 설정
		curl_setopt($cu, CURLOPT_HEADER, FALSE);
		curl_setopt($cu, CURLOPT_FAILONERROR, TRUE);
		curl_setopt($cu, CURLOPT_HTTPHEADER, array('Content-type: application/x-www-form-urlencoded;charset=UTF-8'));
		curl_setopt($cu, CURLOPT_POST, 1); // 데이터를 get/post 로 보낼지 설정.
		curl_setopt($cu, CURLOPT_POSTFIELDS, $queryString); // 보낼 데이터를 설정. 형식은 GET 방식으로설정
		curl_setopt($cu, CURLOPT_RETURNTRANSFER, 1); // REQUEST 에 대한 결과값을 받을 것인지 체크.#Resource ID 형태로 넘어옴 :: 내장 함수 curl_errno 로 체크
		curl_setopt($cu, CURLOPT_TIMEOUT,60); // REQUEST 에 대한 결과값을 받는 시간 설정.
		curl_setopt($cu, CURLOPT_SSL_VERIFYPEER, 0); //
		curl_setopt($cu, CURLOPT_SSL_VERIFYHOST, 0); //

		$orderId = curl_exec($cu); // 실행

		if (curl_getinfo($cu, CURLINFO_HTTP_CODE) == 200) {
			$resultCode = 200;
			curl_close($cu);
		} else {
			//동시에 접속하는 이용자 수가 많거나 인터넷 네트워크 상태가 불안정하여 현재 체크아웃 서비스 접속이 불가합니다.이용에 불편을 드린 점 진심으로 사과드리며, 잠시 후 다시 접속해 주시기 바랍니다.
			alert(getAlert('os110'));
			curl_close($cu);
			exit(-1);
		}

		if( strlen(trim($orderId)) > 5000){
			//동시에 접속하는 이용자 수가 많거나 인터넷 네트워크 상태가 불안정하여 현재 체크아웃 서비스 접속이 불가합니다.이용에 불편을 드린 점 진심으로 사과드리며, 잠시 후 다시 접속해 주시기 바랍니다.
			alert(getAlert('os110'));
			exit;
		}

		if($navercheckout['use']=='test'){
			if($this->_is_mobile_agent)
				$orderUrl = 'https://test-m.checkout.naver.com/mobile/customer/order.nhn';
			else
				$orderUrl = 'https://test-checkout.naver.com/customer/order.nhn';
		}else{
			if($this->_is_mobile_agent)
				$orderUrl = 'https://m.checkout.naver.com/mobile/customer/order.nhn';
			else
				$orderUrl = 'https://checkout.naver.com/customer/order.nhn';
		}

		//여기서 받은 orderId로 주문서 page를 호출한다.
		echo ($orderId."\r\n");

		echo("<html>
		<body>
		<form name='frm' method='get' action='".$orderUrl."'>
		<input type='hidden' name='ORDER_ID' value='".$orderId."'>
		<input type='hidden' name='SHOP_ID' value='".$shopId."'>
		<input type='hidden' name='TOTAL_PRICE' value='".$totalPrice."'>
		</form>
		</body>
		<script>");
		if ($resultCode == 200) {
			echo("document.frm.target = '_top';
			document.frm.submit();");
		}
		echo("
		</script>
		</html>");

	}

	public function navercheckout_zzim_makeQueryString($id, $name, $uprice, $image, $thumb, $url) {
		$ret .= 'ITEM_ID=' . urlencode($id);
		$ret .= '&ITEM_NAME=' . urlencode($name);
		$ret .= '&ITEM_UPRICE=' . $uprice;
		$ret .= '&ITEM_IMAGE=' . urlencode($image);
		$ret .= '&ITEM_THUMB=' . urlencode($thumb);
		$ret .= '&ITEM_URL=' . urlencode($url);
		return $ret;
	}

	public function navercheckout_zzim()
	{
		$goods_seq = $_POST['goodsSeq'];
		$navercheckout = config_load('navercheckout');
		$shopId = $navercheckout['shop_id'];
		$certiKey = $navercheckout['certi_key'];
		$queryString = 'SHOP_ID='.urlencode($shopId);
		$queryString .= '&CERTI_KEY='.urlencode($certiKey);
		$queryString .= '&RESERVE1=&RESERVE2=&RESERVE3=&RESERVE4=&RESERVE5=';

		$data_goods = $this->goodsmodel->get_goods($goods_seq);
		$data_images = $this->goodsmodel->get_goods_image($goods_seq);
		$data_options = $this->goodsmodel->get_goods_option($goods_seq);
		if($data_options)foreach($data_options as $k => $opt){
			if($k == 0) $uprice = (int) $opt['price'];
			if($opt['default_option'] == 'y'){
				$data_goods['price'] = (int) $opt['price'];
			}
		}

		$id = $data_goods["goods_seq"];
		$data_goods['goods_name']	= strip_tags($data_goods['goods_name']);
		$name = $data_goods["goods_name"];
		if($data_goods['price']) $uprice = $data_goods['price'];

		$domain = preg_replace("/^m\./","",$_SERVER['HTTP_HOST']);

		$image = $data_images[1]['view']['image'];
		$thumb = $data_images[1]['list1']['image'];

		if(!preg_match("/http/",$data_images[1]['view']['image']))$image = get_connet_protocol().$domain.$data_images[1]['view']['image'];
		if(!preg_match("/http/",$data_images[1]['list1']['image']))$thumb = get_connet_protocol().$domain.$data_images[1]['list1']['image'];
		$url = get_connet_protocol().$domain."/goods/view?no=".$id;
		$queryString .= '&'.$this->navercheckout_zzim_makeQueryString($id,$name,$uprice,$image,$thumb,$url);

		if($navercheckout['use']=='test'){
			$zzimUrl = 'https://test-checkout.naver.com/customer/api/wishlist.nhn';
		}else{
			$zzimUrl = 'https://checkout.naver.com/customer/api/wishlist.nhn';
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
				$wishlistPopupUrl = "https://test-m.checkout.naver.com/mobile/customer/wishList.nhn";
			else
				$wishlistPopupUrl = "https://test-checkout.naver.com/customer/wishlistPopup.nhn";
		}else{
			if($this->_is_mobile_agent)
				$wishlistPopupUrl = "https://m.checkout.naver.com/mobile/customer/wishList.nhn";
			else
				$wishlistPopupUrl = "https://checkout.naver.com/customer/wishlistPopup.nhn";
		}

		echo("<html>
		<body>
		<form name='frm' method='get' action='".$wishlistPopupUrl."'>
		<input type='hidden' name='SHOP_ID' value='".$shopId."'>
		<input type='hidden' name='ITEM_ID' value='".$itemId."'>
		</form>
		</body>
		");
		if ($resultCode == 200) {
			echo("<script>document.frm.target = '_top'; document.frm.submit();</script>
			");
		}
		echo("</html>");

	}

	public function navercheckout_item()
	{

		$navercheckout	= config_load('navercheckout');

		$tpl	= "navercheckout_item.html";
		$query	= $_SERVER['QUERY_STRING'];
		$vars	= array();
		foreach(explode('&', $query) as $pair) {
			list($key, $value) = explode('=', $pair);
			$key			= urldecode($key);
			$value			= urldecode($value);
			$vars[$key][]	= $value;
		}

		if($navercheckout['version']=='2.1'){ // 네이버페이 api 2.1

			$result = $this->navercheckout_item2_1();

			$tpl = "naverpay2.1_item.html";

		}else{

			$cfg_order		= config_load('order');

			$itemIds = $vars['ITEM_ID'];

			foreach($itemIds as  $goods_seq){

				$data_goods		= $this->goodsmodel->get_goods($goods_seq);
				$categorys		= $this->goodsmodel->get_goods_category($goods_seq);
				$options		= $this->goodsmodel->get_goods_option($goods_seq);

				$data_goods['tot_stock'] = 0;

				if($data_goods['tax'] == "tax"){
					$taxtype = "TAX";			//과제
				}elseif($data_goods['tax'] == "exempt"){
					$taxtype = "TAX_FREE";		//면세
				}else{
					$taxtype = "ZERO_TAX";		//영세
				}

				if($options)foreach($options as $k => $opt){
					/* 대표가격 */
					if($opt['default_option'] == 'y'){
						$data_goods['price'] = $opt['price'];
					}

					if($cfg_order['runout'] == 'ableStock'){
						$reservation_field = 'reservation25';
						if($cfg_order['ableStockStep'] == 15) $reservation_field = 'reservation15';
						$data_goods['tot_stock'] += $opt['stock'] - $opt[$reservation_field];
					}else{
						$data_goods['tot_stock'] += $opt['stock'];
					}
				}

				if($cfg_order['runout'] == 'unlimited') $data_goods['tot_stock'] = 10000;

				if($categorys) foreach($categorys as $key => $data){
					if( $data['link'] == 1 ){
						list($data_goods['category_code']) = $this->categorymodel->split_category($data['category_code']);
					}
				}

				// 카테고리
				$arr_category_code = array();
				$arr_category_code = $this->categorymodel->split_category($data_goods['category_code']);
				for($i=0;$i<4;$i++) {
					if( $arr_category_code[$i] ){
						$data_goods['arr_category_code'][$i] = $arr_category_code[$i];
						$data_goods['arr_category'][$i] =  htmlspecialchars($this->categorymodel->one_category_name($arr_category_code[$i]));
					}
				}

				for($i=1;$i<6;$i++){
					$option_field = "option".$i;
					$query = "select option_title,".$option_field.",fix_option_seq from fm_goods_option where goods_seq=? and $option_field != '' group by ".$option_field;
					$query = $this->db->query($query,array($goods_seq));
					foreach($query->result_array() as $data){
						$titles = explode(',',$data['option_title']);
						if($navercheckout['version']=='2.1'){ // 네이버페이 api 2.1
							$data_option['id'] = $data['fix_option_seq'];
							$data_option['text'] = $data[$option_field];
							$data_goods['options'][$titles[$i-1]][] =  $data_option;
						}else{
							$data_goods['options'][$titles[$i-1]][] =  $data[$option_field];
						}
					}
				}

				//이미지 상세/ list1
				$data_goods['list1img'] = viewImg($goods_seq,'list1','N');
				if(!preg_match('/http/', $data_goods['list1img'])){
					$data_goods['list1img'] = get_connet_protocol().$_SERVER['HTTP_HOST'].iconv('UTF-8', 'euc-kr',$data_goods['list1img']);
				}

				$data_goods['viewimg'] = viewImg($goods_seq,'view','N');
				if(!preg_match('/http/', $data_goods['viewimg'])){
					$data_goods['viewimg'] = get_connet_protocol().$_SERVER['HTTP_HOST'].iconv('UTF-8', 'euc-kr',$data_goods['viewimg']);
				}

				$result[] = $data_goods;
			}
		}

		header("Content-Type: application/xml;charset=utf-8");
		echo ('<?xml version="1.0" encoding="utf-8"?>');
		$this->template->template_dir	= BASEPATH."../partner";
		$this->template->compile_dir	= BASEPATH."../_compile/";
		$this->template->assign('result',$result);
		$this->template->define(array('tpl'=>$tpl));

		if($navercheckout['version']=='2.1'){ // 네이버페이 api 2.1 개행문자 제거
			$cont = $this->template->fetch("tpl");
			echo str_replace("\r\n","",str_replace("\t","",$cont));
		}else{
			$this->template->print_("tpl");
		}
	}


	//네이버 NPay 2.1 상품정보조회 @2015-01-08 pjm
	public function navercheckout_item2_1(){

		$this->load->model("goodsmodel");
		$this->load->model("naverpaymodel");
		$this->load->model("categorymodel");
		$this->load->model("cartmodel");
		$this->load->model('shippingmodel');

		$this->load->library("sale");
		$this->load->library("naverpaylib");
		$this->load->library('shipping');

		$cfg_order		= config_load('order');

		// product 정보를 가져온다.
		$productList = $_GET['product'];
		if (count($productList) < 1) {
			exit('product정보는 필수입니다.');
		}

		$excpt_opt = array();
		$excpt_opt['shipping']		= "";
		$excpt_opt['event']			= "";

		$all_goods_view				= false;

		//주문시정보(할인 등) 조회용
		if($_GET['merchantCustomCode1']){
			$session_tmp			= base64_decode($_GET['merchantCustomCode1']);
			$session_tmp2			= explode("@",$session_tmp);
			$merchantCustomCode1	= explode("_",$session_tmp2[0]);
			$session_id				= $merchantCustomCode1[0];
			//$return_cart_seq			= explode(",",urldecode($_GET['merchantCustomCode1']));

			$this->db->query("update fm_partner_order_detail set npay_confirm_cnt=npay_confirm_cnt+1 where session_tmp=?",array($session_tmp));

			$query			= $this->db->query("select * from fm_partner_order_detail where partner_id='npay' and session_tmp=?",array($session_tmp));
			$sale_detail	= $query->result_array();

			$return_cart_seq = $return_sale = array();
			foreach($sale_detail as $data){
				$return_cart_seq[]			= $data['cart_seq'];
				$select_cart_option_seq[]	= $data['cart_option_seq'];
				if(!$distribution) $distribution = $data['distribution'];
				if($data['option_type'] == "option"){
					$return_sale['OPT'][$data['option_seq']] = $data;
				}elseif($data['option_type'] == "suboption"){
					$return_sale['SUB'][$data['option_seq']] = $data;
				}
			}

			//debug($return_sale);

			// single 옵션의 옵션 seq 가져오기
			$single_order = array();
				foreach($productList as $product){
				$merchantProductId = explode("@",base64_decode($product['merchantProductId']));
				if($merchantProductId[1]){
					$single_order[$product['id']] = $merchantProductId[1];
				}
			}

			$distribution	= $sale_detail[0]['distribution'];

			# 회원로그인 후 주문시 fm_cart에 session_id 저장 안되므로 회원정보로 조회해야함
			if($sale_detail[0]['member_gubun'] == "member"){
				$member_seq		= $sale_detail[0]['member_seq'];
				$tmp_arr		= array("distribution"=>$distribution,"npay"=>1);
				$cart			= $this->cartmodel->catalog($member_seq,$tmp_arr);
			}else{
				$tmp_arr		= array('session_id'=>$session_id,'distribution'=>$distribution,"npay"=>1);
				$cart			= $this->cartmodel->catalog(null,$tmp_arr);
			}

			$cart_list = array();
			$select_shipping_group_price = array();
			foreach($cart['list'] as $k=>$list){
				# 선택한 상품만 주문
				if(in_array($list['cart_option_seq'],$select_cart_option_seq)){
					$select_shipping_group_price[$list['shipping_method']] += '';
					$goods_ea[$list['goods_seq']]		+= $list['ea'];
					$cart_list[] = $list;
				}
			}

			# 옵션 코드 확인
			$optIds = $tmp2 = array();
			foreach($_GET['product'] as $data){
				if($data['optionManageCodes']){
					$opttmp = explode(",",$data['optionManageCodes']);
					foreach($opttmp as $tmp){
						$tmp2					= explode("_",$tmp);
						$optIds[$data['id']][]	= substr($tmp2[0],4,20);
					}
				}elseif(!$data['optionManageCodes'] && $data['supplementIds']){
					$opttmp = explode("_",$data['supplementIds']);
					$optIds[$data['id']][]	= $opttmp[0];
				}elseif(!$data['optionManageCodes'] && $single_order[$data['id']]){
					$optIds[$data['id']][]	= str_replace("opt1","",$single_order[$data['id']]);
				}
			}
		}else{

			$all_goods_view =  true;
		}

		$all_options	= array();
		$sel_options	= array();
		$opt_stock		= array();

		# 상품 전체 정보 보기 모드
		if($all_goods_view){
			foreach($_GET['product'] as $product){
				$cart_list[]['goods_seq'] = $product['id'];
			}
		}

		$ship_ini				= array('nation' => "KOREA");
		$ini_info				= $this->shipping->set_ini($ship_ini);
		$shipping_group_list	= $this->shipping->get_shipping_groupping($cart_list);

		# 선택한 상품의 배송그룹별 정책 재설정.(특정상품구입시 무료배송 포함)
		$r_shipping_group		= array();
		$cart_select_list		= array();
		$shipping_group_policy	= array();

		foreach($shipping_group_list as $shipping_group_id => $shipping_group){

			foreach($shipping_group['goods'] as $k=>$list){

				// 상품정보 전체 보기가 아니고 선택한 상품이 아니면 건너뛰기.
				if(!$all_goods_view && !in_array($list['cart_option_seq'],$select_cart_option_seq)){
					continue;
				}

				if($all_goods_view){
					if(!$shipping_group_policy){
						$goods_seq		= $list['goods_seq'];
						$goods			= $this->goodsmodel->get_goods($goods_seq);
						// 네이버페이 찜하기 통한 주문시
						$shipping_group_policy = $this->goodsmodel->get_goods_delivery($goods,1);
					}
				}else{

				}

				# 주문시 선택한 배송정책
				$shipping_set								= $shipping_group['cfg'];
				$shipping_set['shipping_group_id']			= $shipping_group_id;		//새로운 배송정책의 배송그룹
				# 배송그룹 무료배송[묶음배송] 정책 사용시 함께 주문하는 상품 모두 무료.
				$shipping_set['shipping_std_group_free']	= $shipping_group['shipping_std_group_free'];	//기본배송비 무료
				$shipping_set['shipping_add_group_free']	= $shipping_group['shipping_add_group_free'];	//추가배송비 무료

				$list['shipping_set']	= $shipping_set;
				$cart_select_list[]		= $list;
			}
		}

		// 선택한 상품의 배송비 및 정책 재설정
		if(!$all_goods_view){
			//debug($r_shipping_group);
			$row = array();
			foreach($r_shipping_group as $shipping_group => $row){
				$goods_seq		= $row['goods_seq'];
				$param			= $row;
				$param['price'] = $row['unit_price'];

				$shipping = $this->goodsmodel->get_goods_delivery($param,$row['ea'],$shipping_group);
				$shipping_group_policy[$shipping_group] = $shipping;
			}
		}

		// 선택한 상품의 상품정보 XML 생성
		foreach($cart_select_list as $k=>$list){

			//debug($list);

			$goods_seq		= $list['goods_seq'];
			$goods			= $this->goodsmodel->get_goods($goods_seq);
			$options		= $this->goodsmodel->get_goods_option($goods_seq);
			$suboptions		= $this->goodsmodel->get_goods_suboption($goods_seq);
			$inputs			= $this->goodsmodel->get_goods_input($goods_seq);
			$categorys		= $this->goodsmodel->get_goods_category($goods_seq);

			$goods['goods_name']	= strip_tags($goods['goods_name']);

			if($goods_seq != $old_goods_seq){
				$sel_options = array();
			}
			$old_goods_seq = $goods_seq;

			// 상품개별 재고 설정로드
			if($goods['runout_policy']){
				$cfg_runout['runout']			= $goods['runout_policy'];
				$cfg_runout['ableStockStep']	= $goods['ableStockStep'];
				$cfg_runout['ableStockLimit']	= $goods['able_stock_limit'];
			}else{
				$cfg_runout['runout']			= $cfg_order['runout'];
				$cfg_runout['ableStockStep']	= $cfg_order['ableStockStep'];
				$cfg_runout['ableStockLimit']	= $cfg_order['ableStockLimit'];
			}
			# 재고 체크 기준
			if($cfg_runout['runout'] == 'ableStock'){	//가용재고
				$reservation_field = 'reservation25';
				if($cfg_runout['ableStockStep'] == 15) $reservation_field = 'reservation15';
			}else{
				$reservation_field = '';
			}

			if(!$list['goods_name']) $list['goods_name'] = $goods['goods_name'];
			if(!$list['tax']) $list['tax'] = $goods['tax'];

			$inp_cnt = 0;
			if($list['cart_inputs']){
				foreach($list['cart_inputs'] as $inp_idx=>$inp){
					$inp_cnt++;
					if(!trim($inp['input_value'])){
						$list['cart_inputs'][$inp_idx]['input_value'] = "입력없음";
				}
			}
			}

			//option 정보 제공여부
			if((is_array($options) && count($options) > 0 && $options[0]['option1'] != '') || $inp_cnt > 0){
				$list['optionSupport']		= true;
			}else{
				$list['optionSupport']		= false;
			}
			if(is_array($suboptions) && count($suboptions) > 0)
			{
				$list['supplementSupport']	= true;
			}else{
				$list['supplementSupport']	= false;
			}

			// 카테고리정보
			$tmparr2			= array();
			foreach($categorys as $key => $data){
				$tmparr			= $this->categorymodel->split_category($data['category_code']);
				foreach($tmparr as $cate)	$tmparr2[]	= $cate;
			}
			if($tmparr2){
				$tmparr2			= array_values(array_unique($tmparr2));
				$list['r_category']	= $tmparr2;
			}

			//tax
			if($list['tax'] == "tax"){
				$list['taxtype'] = "TAX";			//과제
			}elseif($list['tax'] == "exempt"){
				$list['taxtype'] = "TAX_FREE";		//면세
			}else{
				$list['taxtype'] = "ZERO_TAX";		//영세
			}

			//이미지 상세
			$images = $this->goodsmodel->get_goods_image($goods_seq);
			if(strstr($images[1]['list1']['image'],"http")){
				$list['viewimg'] = $images[1]['list1']['image'];
			}else{
				$list['viewimg'] = get_connet_protocol().$_SERVER['HTTP_HOST'].$images[1]['list1']['image'];
			}

			//총 수량
			$list['tot_stock'] = 0;
			$opt_seq			= $list['option_seq'];
			if($options)foreach($options as $k => $opt){

				// 옵션 기본 판매가
				if($opt['default_option'] == 'y'){

					$opt_sales		= $return_sale['OPT'][$opt_seq];//장바구니에 담긴 가격으로 체크
					// 옵션 기본 판매가
					$list['base_price'] = floor($this->naverpaymodel->option_default_price($goods_seq));

					/*
					기존 : 할인 기준액에 이벤트 할인액 적용
					변경 : 할인 기준액은 판매가임.(이벤트 할인액 적용안함)
					if($opt_sales['event_seq']) {
						//기본 옵션가를 기준으로 price, consumer_price 계산함
						//이벤트 할인가는 기본할인으로 적용
						$default_option = $this->goodsmodel->get_default_option($goods_seq);
						$eventData = get_event_price($default_option['price'], $goods_seq, $list['r_category'], $default_option['consumer_price']);
						if($eventData['target_sale'] == 1 && $opt_sales['consumer_price'] > 0 ){//정가기준
							$list['base_price'] = $default_option['consumer_price'] - $eventData['event_sale_unit'];
						} else { // 정가기준 이외에는 판매가에 이벤트 할인가 뺌
							$list['base_price'] -= $eventData['event_sale_unit'];
						}
					}
					*/

					# 단일 옵션일때 기본 판매가에 할인액 적용(구매,멤버,좋아요,유입경로)
					if(!$list['optionSupport']){
						// 총 할인액이 아닌 개당 할인 금액으로 계산
						$sale_price			= ($opt_sales['multi_sale']/$opt_sales['ea'])
											 + ($opt_sales['event_sale']/$opt_sales['ea']) 
											 + $opt_sales['member_sale'] 
											 + ($opt_sales['like_sale']/$opt_sales['ea'])
											 + ($opt_sales['referer_sale']/$opt_sales['ea'])
											 + ($opt_sales['mobile_sale']/$opt_sales['ea']);

						$list['base_price'] -= $sale_price;
					}
				}

				if($cfg_runout['runout'] == 'unlimited'){
					$stock = 10000;
				}else{
					if($cfg_runout['runout'] == 'ableStock'){	//가용재고
						$stock				= $opt['stock'] - $opt[$reservation_field];
					}else{	//실재고
						$stock				= $opt['stock'];
					}
				}
				$list['tot_stock']				+= $stock;
				$opt_stock[$opt['option_seq']]	= $stock;

			}

			if($goods['goods_status'] == "normal" && $goods['goods_view'] == "look" && $goods['provider_status']){
				$list['status'] = "ON_SALE";
			}else{
				$list['status'] = "NOT_SALE";
			}

			if($list['tot_stock'] < 0) $list['status'] = "SOLD_OUT";

			$list['returnShippingFee']		= "";	//반품배송비(설정 없음. 네이버 자동 설정)
			$list['exchangeShippingFee']	= "";	//왕복 교환배송비(설정 없음. 네이버 자동 설정)

			//options 현재 옵션이 있고, 주문당시 단일옵션이 아니면
			if($list['optionSupport']){

				//선택한 옵션명, 재고, 금액 체크
				$tmps		= $opt = array();

				$opt_seq = $list['option_seq'];

				if(!$all_goods_view){
				$tmp			= array();
				$managecode		= "";
				for($i=1;$i<6;$i++){

					$title_field	= "title".$i;
					$option_field	= "option".$i;
					$tmp['name']	= $list[$title_field];
					if($list[$option_field]){
						if($managecode) $managecode .= "_";
						$managecode	.= "opt".$i.$opt_seq;
						$tmp['id']		= "opt".$i.$opt_seq;
						$tmp['value']	= $list[$option_field];
						$tmps[] = $tmp;
					}
				}
				}

				if($options){

					$titles = explode(",",$options[0]['option_title']);

					$inp_data_arr	= array();
					$opt_seq_arr	= array();

					//전체 옵션
					if(!$result[$goods_seq]['all_options']){

						foreach($titles as $k => $title){
							if($title){
								$opt_data = array();
								foreach($options as $opt){

									//if($all_goods_view || (!$all_goods_view && in_array($opt['option_seq'],$optIds[$goods_seq]))){

										$option_field = "option".($k+1);

										if($opt[$option_field]) $id = "opt".($k+1).$opt['option_seq']; else $id = '';

										if($opt_stock[$opt['option_seq']] < 1) $opt_status = "false"; else $opt_status = "true";

										$opt_tmp = array('type' =>'SELECT','id'=>$id,'text'=>$opt[$option_field],'status'=>$opt_status);

										$opt_data[]		=  $opt_tmp;
										$opt_seq_arr[]	= $opt['option_seq'];

									//}
								}

								$all_options['SELECT'][$title] = $opt_data;
							}
						}

						# 상품 전체 옵션 조회 시(네이버페이 장바구니쪽 나의 찜상품 '주문하기' 클릭시)
						if($all_goods_view){

							$optIds = array();
							foreach($options as $opt){

								$opt_title = explode(",",$opt['option_title']);

								if($opt_stock[$opt['option_seq']] < 1) $opt_status = "false"; else $opt_status = "true";

								$opt_tmp2 = array();
								// 옵션추가금액 = (옵션 판매가 - 할인액) - 상품 기본가
								$opt_tmp2['price']		= 0;
								$opt_tmp2['status']		= $opt_status;
								$opt_tmp2['stock']		= $opt_stock[$opt['option_seq']];

								$tmps = array();

								$managecodes = array();

								foreach($opt_title as $k=>$title){

									$option_field = "option".($k+1);
									if($opt[$option_field]) $id = "opt".($k+1).$opt['option_seq']; else $id = '';

									$optIds[$goods_seq][] = $id;
									$tmps[] = array('name'=>$title,'id' =>$id);
									$managecodes[] = $id;

								}

								$opt_tmp2['options']		= $tmps;

								$managecode					= implode("_",$managecodes);
								$sel_options[$managecode]	=  $opt_tmp2;
							}
						}
						if($inputs){

							foreach($inputs as $kk=>$inp){
								$input_data[$inp['input_name']] = $inp_data_arr;
								//$input_data[$inp['input_name']."^IN^".strtoupper($inp['input_form'])] = $inp_data_arr;
							}
							$all_options['INPUT'] = $input_data;
						}
					}
					if(!$all_goods_view){
						//옵션별 할인액(기본판매액(이벤트할인가적용) - 총 할인가)
						$opt_sales			= $return_sale['OPT'][$opt_seq];
						$sale_price			= ($opt_sales['multi_sale']/$opt_sales['ea'])
												+ ($opt_sales['event_sale']/$opt_sales['ea'])
												+ $opt_sales['member_sale']
												+ ($opt_sales['like_sale']/$opt_sales['ea'])
												+ ($opt_sales['referer_sale']/$opt_sales['ea'])
												+ ($opt_sales['mobile_sale']/$opt_sales['ea']);

						// 옵션추가금액 = (옵션 판매가(이벤트할인가적용) - 할인액) - 상품 기본가(이벤트할인가적용)
						$opt['price']		= ($list['price'] - $sale_price) - $list['base_price'];
						if($opt_sales['event_seq']) {
							$eventData = get_event_price($opt_sales['price'], $goods_seq, $list['r_category'], $opt_sales['consumer_price']);
							if($eventData['target_sale'] == 1 && $opt_sales['consumer_price'] > 0 ){//정가기준
								//옵션정가 - 이벤트할인가 - 그외 할인가 - 상품기본옵션(이벤트할인적용)
								$opt['price']		= $opt_sales['consumer_price'] - $eventData['event_sale_unit'] - $sale_price - $list['base_price'];
							}
						}
						$opt['status']		= "true";
						$opt['stock']		= $opt_stock[$opt_seq];
						$opt['options']		= $tmps;
					}

				}
				if($inputs){

					if(!$managecode) $managecode = "opt1".$opt_seq;
					$inp_data_arr = array();
					foreach($inputs as $kk=>$inp){
						$inp_data = array('type'	=> 'INPUT',
										'id'		=> 'inp'.$kk.$opt_seq,
										'name'		=> $inp['input_name'],
								);
						$inp_data_arr[] = $inp_data;
					}
					$opt['input']			= $inp_data_arr;

				}

				if(!$all_goods_view){ $sel_options[$managecode]	=  $opt; }

			}else{

				$sel_options = "";
				//$shipping_base_price = $list['base_price'] * $opt_sales['ea'];

			}

			if($list['supplementSupport']){
				$all_suboptions = array();
				$ea				= 1;
				foreach($optIds[$goods_seq] as $opt_code){
					foreach($suboptions as $sub){
						if(is_array($sub)){
							foreach($sub as $sub2){

								if($reservation_field){	//가용재고
									$stock				= $sub2['stock'] - $sub2[$reservation_field];
								}else{	//실재고
									$stock				= $sub2['stock'];
								}

								$all_suboptions[] = array("id"			=>$opt_code."_".$sub2['suboption_seq'],
														"suboption_seq"	=>$sub2['suboption_seq'],
														"name"			=>$sub2['suboption'],
														"title"			=>$sub2['suboption_title'],
														"price"			=>(int)$sub2['price'],
														"stock"			=>$stock,
														"reservation15" =>$sub2['reservation15'],
														"reservation25" =>$sub2['reservation25'],
													);
							}
						}else{

							if($reservation_field){	//가용재고
								$stock				= $sub['stock'] - $sub[$reservation_field];
							}else{	//실재고
								$stock				= $sub['stock'];
							}
							$all_suboptions[] = array("id"			=>$opt_code."_".$sub['suboption_seq'],
													"suboption_seq"	=>$sub['suboption_seq'],
													"name"			=>$sub['suboption'],
													"title"			=>$sub['suboption_title'],
													"price"			=>(int)$sub['price'],
													"stock"			=>$stock,
													"reservation15" =>$sub['reservation15'],
													"reservation25" =>$sub['reservation25'],
												);
						}
					}
				}
			}

			foreach($all_suboptions as $k=>$subopt){

				//멤버할인 적용
				if((int)$return_sale['SUB'][$subopt['suboption_seq']]['member_sale'] > 0){
					$subopt['price'] = $subopt['price'] - (int)$return_sale['SUB'][$subopt['suboption_seq']]['member_sale'];
				}

				// 이미 all_suboptions 만들 때 가용재고 계산됨 2020-06-18 hyem
				if($cfg_runout['runout'] == 'unlimited') {
					$subopt['stock'] = 10000;
				}

				if($subopt['stock'] < 1){
					$subopt['status'] = 'false';
				}else{
					$subopt['status'] = 'true';
				}
				$all_suboptions[$k] = $subopt;

				if(!$shipping_method) $shipping_method = $return_sale['SUB'][$subopt['suboption_seq']]['shipping_method'];
			}

			# 배송방법
			$shipping_set_seq		= $list['shipping_set_seq'];			//주문시 선택한 배송정책
			$shipping_paytype		= $list['shipping_prepay_info'];		//배송비 결제방법

			//debug("shipping_set_seq : ".$shipping_set_seq);
			//debug("shipping_paytype : ".$shipping_paytype);

			# 상품별 선택한 배송정책
			//$shipping_set = $this->shippingmodel->load_shipping_set_detail($shipping_set_seq);
			//debug($shipping_set);
			$ship_return = $this->naverpaylib->shipping_method_type($list['shipping_set'],$shipping_paytype);
			//debug($ship_return);
			//exit;

			# 반송지 정보
			$return_address						= $ship_return['return_address'];

			$shipping_data = array();
			$shipping_data['shipping_group']	= $opt_sales['shipping_group'];
			$shipping_data['shipping_type']		= $ship_return['fee_type'];
			$shipping_data['shipping_paytype']	= $ship_return['fee_paytype'];
			$shipping_data['shipping_price']	= $ship_return['fee_price'];
			$shipping_data['shipping_method']	= $ship_return['method'];
			$shipping_data['basic_price']		= $ship_return['basic_price'];
			$shipping_data['apiSupport']		= ($ship_return['add_shipping'])? 'true': '';	//지역별 배송비 조회 API 사용

			$shipping_data['return_sellername']	= $return_address['address_name'];
			$shipping_data['return_address1']	= ($return_address['address_type'] == "street")?$return_address['address_street']:$return_address['address'];
			$shipping_data['return_address2']	= $return_address['address_detail'];
			$shipping_data['return_contact1']	= $return_address['shipping_phone'];
			$shipping_data['return_contact2']	= $return_address['shipping_phone'];
			$shipping_data['return_zipcode']	= $return_address['address_zipcode'];

			//일정수량별 부과
			if($ship_return['fee_type'] == "CHARGE_BY_QUANTITY"){
				$shipping_data['chargebyquantity']['type']			= "REPEAT";
				$shipping_data['chargebyquantity']['repeatQuantity']= $list['shipping_set']['std'][1]['section_st'];
			}

			if($ship_return['fee_type'] == "CONDITIONAL_FREE"){
				$shipping_data['conditionalFree']		= true;
				$shipping_data['conditionalFreePrice']	= $shipping['ifpay_free_price'];

			}


			/*

				if(!$list['goods_shipping_policy']) $list['goods_shipping_policy'] = $goods['goods_shipping_policy'];
				if($all_goods_view){

					$shipping_provider = $shipping_group_policy;

					if($shipping_group_policy['policy'] == "goods"){
						$shipping_method		= "each_".$shipping_group_policy['type'];
						$list['shipping_group']	= $shipping_method.$goods_seq;
					}else{
						$shipping_method		= $shipping_group_policy['type'];
						$list['shipping_group']	= $shipping_method.$shipping_group_policy['shipping_provider']['provider_seq'];
					}
					//개별배송정책일 때
					if($goods['shipping_policy'] == "goods"){
						$shipping_group_policy['delivery_cost_policy'] = '';
						if($list['goods_shipping_policy'] == "limit"){
							$list['goods_shipping']				= $shipping_group_policy['price'];
							$list['limit_shipping_ea']			= $goods['limit_shipping_ea'];
							$list['limit_shipping_price']		= $goods['limit_shipping_price'];
							$list['limit_shipping_subprice']	= $goods['limit_shipping_subprice'];
						}else{
							$list['unlimit_shipping_price']		= $goods['unlimit_shipping_price'];
						}
					}
				}else{
					//shop에서 주문시
					if($opt_seq){
						$shipping_method	= $return_sale['OPT'][$opt_seq]['shipping_method'];
					}
					$shipping_provider = $shipping_group_policy[$list['shipping_group']];
				}

				$list['shipping_method']	= $shipping_method;
				//$shipping					= $list['shipping']['shipping_provider'];
				$shipping					= $shipping_provider['shipping_provider'];
				$group_cost_policy			= $shipping_provider['group_cost_policy'];	//특정상품 구입시 무료 여부(free)
				$shipping_data = array();

				//returnInfo 상품별 반송주소
				$list['return_zipcode']		= str_replace("-","",$shipping['return_zipcode']);
				if($shipping['return_address_type'] == "street"){
					$shipping_data['return_address1']	= $shipping['return_address_street'];
				}else{
					$shipping_data['return_address1']	= $shipping['return_address'];
				}
				$shipping_data['return_address2']		= $shipping['return_address_detail'];
				$shipping_data['return_sellername']		= $shipping['provider_name'];		//수취인이름

				$query = $this->db->query("select * from fm_provider_person where provider_seq = '".$list['provider_seq']."' and gb in('ds1','ds2')");
				$shipping_parson = $query->result_array();

				$shipping_phone1	= $shipping_parson[0]['phone'];
				$shipping_phone2	= $shipping_parson[1]['phone'];
				$shipping_mobile1	= $shipping_parson[0]['mobile'];
				$shipping_mobile2	= $shipping_parson[1]['mobile'];
				if($shipping_phone1) $shipping_phone1 = str_replace("-","",$shipping_phone1); else $shipping_phone1 = '';
				if($shipping_phone2) $shipping_phone2 = str_replace("-","",$shipping_phone2); else $shipping_phone2 = '';
				if($shipping_mobile1) $shipping_mobile1 = str_replace("-","",$shipping_mobile1); else $shipping_mobile1 = '';
				if($shipping_mobile2) $shipping_mobile2 = str_replace("-","",$shipping_mobile2); else $shipping_mobile2 = '';

				$shipping_data['return_contact1'] = ($shipping_phone1)? $shipping_phone1:$shipping_mobile1;	//연락처1
				$shipping_data['return_contact2'] = ($shipping_phone2)? $shipping_phone2:$shipping_mobile2;	//연락처1

				$shipping_data['shipping_group']	= $list['shipping_group'];		//배송그룹

				//주문시 선택한 배송방법
				list($method,$paytype) = $this->naverpaylib->shipping_method_type($list['shipping_method']);

				//feeType : 배송비 유형(무료 FREE, 유료:CHARGE, 조건부무료:CONDITIONAL_FREE, 수량별부과 : CHARGE_BY_QUANTITY)
					$feeType					= "FREE";
				//feePrice : 기본 배송비(무료또는 착불일떄만 0 입력 가능)
					$feePrice					= $shipping['ifpay_delivery_cost'];	//선불배송비
					$charge_by_quantity_type	= "";								//일정 수량별 반복 부과

					if($method == "DELIVERY"){
						//선불/착불 공통 정책
						if($paytype == "PREPAYED" || $paytype == "CASH_ON_DELIVERY"){
							//조건부 무료
							if($shipping['delivery_cost_policy'] == "ifpay" && $group_cost_policy != "free"){
								$feeType = "CONDITIONAL_FREE";
								$basePrice	= $shipping['ifpay_free_price'];		//basePrice : 조건부 무료배송 기준 금액
							//유료
							}else if($shipping['delivery_cost_policy'] == "pay" && $group_cost_policy != "free"){
								$feeType = "CHARGE";
								$feePrice	= $shipping['pay_delivery_cost'];
							//무료
							}else if($shipping['delivery_cost_policy'] == "free" || $group_cost_policy == "free"){
								$feeType = "FREE";
								$feePrice	= 0;
							}
						//무료
						}elseif($paytype == "FREE"){
							$feeType = "FREE";
							$feePrice	= 0;
						}
						if($paytype == "CASH_ON_DELIVERY"){	//착불배송비
							$feePrice = $shipping['postpaid_delivery_cost'];
						}
					}else if($method == "DELIVERY_EACH"){
						//개별배송
						// 무조건 유료 : goods_shipping_policy > unlimit
						// 수량별 부과 : goods_shipping_policy > limit
						if($list['goods_shipping_policy'] == "limit"){
							if((int)$list['limit_shipping_subprice'] == 0 && (int)$list['limit_shipping_subprice'] != (int)$list['limit_shipping_price']){

								# 유료배송
								$feeType	= "CHARGE";
								$feePrice	= ($list['goods_shipping'])? $list['goods_shipping'] : $list['limit_shipping_price'];

							}else{
								//일정수량별 부과
								$feeType					= "CHARGE_BY_QUANTITY";
								$charge_by_quantity_type	= "REPEAT";	//일정 수량별 반복 부과
								$feePrice					= $list['limit_shipping_price'];
							}
						}else{
							$feeType	= "CHARGE";
							$feePrice	= $list['unlimit_shipping_price'];
						}
						$method = "DELIVERY";
					}else if($method == "QUICK_SVC"){
						$feeType = "CHARGE";
					}else{
						$feeType = "FREE";
						$feePrice	= 0;
					}

					if(!$feePrice) $feePrice = "0";

					if((int)$feePrice == 0 && $method != "QUICK_SVC"){
						$feeType = "FREE";
						$paytype = "FREE";
					}

					$shipping_data['shipping_type']		= $feeType;	//배송방법
					$shipping_data['shipping_paytype']	= $paytype;	//배송비 결제방법

					//일정수량별 부과
					if($feeType == "CHARGE_BY_QUANTITY"){
						$shipping_data['chargebyquantity']['type']			= "REPEAT";
						$shipping_data['chargebyquantity']['repeatQuantity']= $list['limit_shipping_ea'];
					}

					$shipping_data['shipping_price']	= $feePrice;

					if($feeType == "CONDITIONAL_FREE"){
						$shipping_data['conditionalFree']		= true;
						$shipping_data['conditionalFreePrice']	= $shipping['ifpay_free_price'];

					}

				$shipping_data['shipping_method'] = $method;
				$shipping_data['basic_price'] = $shipping_base_price;

				//지역별 배송비 조회 API 사용
				if ($shipping_provider['addDeliveryCost']) {
					$shipping_data['apiSupport'] = 'true';
				}
				debug($shipping_data);
			*/

			$list								= array_diff_key($list,$excpt_opt);
			$cart_option_seq					= $list['cart_option_seq'];

			$list['shipping']					= $shipping_data;
			$result[$goods_seq]					= $list;
			if($all_options) $result[$goods_seq]['all_options']	= $all_options;
			$result[$goods_seq]['sel_options']	= $sel_options;
			$result[$goods_seq]['suboptions']	= $all_suboptions;

		}

		//exit;
		$this->naverpaylib->result_message_log("npay_goods_info",$_GET,$mode='');
		$this->naverpaylib->result_message_log("npay_goods_info",$result,$mode='');

		return $result;

	}

	// 네이버 NPay 2.1 도서산간비체크 @2015-01-08 pjm
	public function navercheckout_additionalFee(){

		$this->load->library("naverpaylib");
		$this->load->model('Providershipping');
		$this->load->model('shippingmodel');
		$this->load->library('shipping');

		$this->shipping->_call_mode = "npay";

		$query	= $_SERVER['QUERY_STRING'];
		$vars	= array();
		foreach(explode('&', $query) as $pair) {
			list($key, $value) = explode('=', $pair);
			$key			= urldecode($key);
			$value			= urldecode($value);
			$vars[$key][]	= $value;
		}

		$this->naverpaylib->result_message_log("npay_zipcode_price",$_GET,$mode='');

		$productList	= $_GET['productId'];
		$base64			= strtr($_GET['address1'], '-_', '+/');
		$address1		= base64_decode($base64);
		$zipcode		= $_GET['zipcode'];

		// 도서산간비 API는 상품 id, 우편번호, 기본주소정보를 모두 필수값으로 받는다.
		if (count($productList) < 1) {
			exit('product정보는 필수입니다.');
		}
		if ($_GET['zipcode'] < 1) {
			exit('우편번호는 필수입니다.');
		}
		if ($_GET['address1'] < 1) {
			exit('기본주소정보는 필수입니다.');
		}

		$_replace_region = array();
		$_replace_region["서울"] = "서울특별시";
		$_replace_region["경기"] = "경기도";
		$_replace_region["인천"] = "인천광역시";
		$_replace_region["부산"] = "부산광역시";
		$_replace_region["대전"] = "대전광역시";


		# 구주소 Replace
		$address1			= str_replace("제주 제주시","제주특별자치도 제주시",$address1);
		$address1			= str_replace("제주 서귀포시","제주특별자치도 서귀포시",$address1);

		foreach($_replace_region as $_k => $_v){
			$address1			= str_replace($_k." ",$_v." ",$address1);
		}

		$addDeliveryType	= config_load('adddelivery', 'addDeliveryType');
		$address_ext		= explode(" ",$address1);

		foreach($productList as $goods_seq){

			$data_goods		= $this->goodsmodel->get_goods($goods_seq);
			if(!$data_goods['provider_seq']){
				echo "입점사 정보가 없습니다.";
				exit;
			}
			if(!$data_goods['shipping_group_seq']){
				echo "배송그룹 정보가 없습니다.";
				exit;
			}

			# 상품이 속한 배송그룹내 기본배송정책을 가져온다.
			$shipping_params = array("delivery_nation"=>"korea","default_yn"=>"Y");
			$shipping_group_list = $this->shippingmodel->load_shipping_set_list($data_goods['shipping_group_seq'],$shipping_params);
			foreach($shipping_group_list as $key=>$val){
				if($val["default_yn"] == "Y"){
					$shipping_set_seq = $val['shipping_set_seq'];
					continue;
				}
			}

			if(!$shipping_set_seq){
				echo "기본배송정책 정보가 없습니다.";
				exit;
			}

			# 상품별 선택한 배송정책
			$shipping_set = $this->shippingmodel->load_shipping_set_detail($shipping_set_seq);

			$data_goods['shipping_set_seq']			= $shipping_set['shipping_set_seq'];
			$data_goods['shipping_set_code']		= $shipping_set['shipping_set_code'];
			$data_goods['goods_shipping_group_seq']	= $shipping_set['shipping_group_seq'];

			$ship_ini					= array();
			$ship_ini['nation']			= "KOREA";
			$ship_ini['street_address'] = $address1;
			$ship_ini['zibun_address']  = $address1;
			$ini_info				= $this->shipping->set_ini($ship_ini);
			$shipping_group_list	= $this->shipping->get_shipping_groupping(array($data_goods));

			// 지역별 추가 배송비 금액 추출
			$addDeliveryCost	= (!trim($shipping_group_list['shipping_cost_detail']["delivery"]['add']))? '0':trim($shipping_group_list['shipping_cost_detail']["delivery"]['add']);

			$result[] = array('id' => $goods_seq,'surprice' => $addDeliveryCost);
		}

		header("Content-Type: application/xml;charset=utf-8");
		echo ('<?xml version="1.0" encoding="utf-8"?>');
		$this->template->template_dir	= BASEPATH."../partner";
		$this->template->compile_dir	= BASEPATH."../_compile/";
		$this->template->assign('result',$result);
		$this->template->define(array('tpl'=>'navercheckout_additionalFee.html'));

		//if($navercheckout['version']=='2.1'){ // 네이버페이 api 2.1 개행문자 제거
		$cont = $this->template->fetch("tpl");
		echo str_replace("\r\n","",str_replace("\t","",$cont));
		//}else{
		//	$this->template->print_("tpl");
		//}

	}


	/* 입점마케팅 전체 행 갯수 */
	function file_rows($filemode){
		$last_update_date = '';
		if($mode == 'summary'){
			$tmp = config_load('partner',$filemode.'_update');
			if($tmp[$filemode.'_update']) $last_update_date = $tmp[$filemode.'_update'];
		}
		$query = $this->goodsmodel->get_goods_all_partner_count($last_update_date,'view',true);
		$result = mysqli_query($this->db->conn_id,$query);
		$data = mysqli_fetch_array($result);
		$rows = $data['cnt'];

		return $rows;
	}

	/* 입점 마케팅 다음 파일 생성 */
	function daumFile($file_path, $mode='all',$page,$pageline){
		header("Content-Type: text/html; charset=EUC-KR");

		$fileExt = '';
		$last_update_date = '';
		if($mode == 'summary'){
			$tmp = config_load('partner','daum_update');
			if($tmp['daum_update']) $last_update_date = $tmp['daum_update'];
		}

		$fp = fopen($file_path,"a+");

		// 마케팅 전달 이미지
		$market_image	= config_load('marketing_image');
		if($market_image['daumImage']=='B' || !$market_image['daumImage']){
			$view_type	= "view";
		}else if($market_image['daumImage']=='C'){
			$view_type	= "large";
		}

		// 입점마케팅 상품 추가할인
		$marketing_sale = config_load('marketing_sale');
		$arr_marketing_sale = array();
		if (isset($marketing_sale)) {
			// 회원 등급별 할인 적용 [member_sale_type => 0 : 비회원 , 1 : 일반 등급 회원]
			if ($marketing_sale['member']=='Y' && $marketing_sale['member_sale_type']==1) $arr_marketing_sale['member'] = 'Y';

			// 할인 유입경로 적용
			if ($marketing_sale['referer']=='Y') 	{
				$arr_marketing_sale['referer'] = 'Y';
				$arr_marketing_sale['referer_url'] = 'shopping.daum.net';
			}

			// 할인 쿠폰 적용
			if ($marketing_sale['coupon']=='Y') $arr_marketing_sale['coupon'] = 'Y';

			// 모바일 할인 적용 : 쇼핑하우 미적용(쇼핑하우 전달용 모바일 할인 필드 없음)
			//if ($marketing_sale['mobile']=='Y') $arr_marketing_sale['mobile'] = 'Y';
		}

		### 입점마케팅 전달 데이터 통합 설정 - 입점마케팅 상품명,카드무이자할부 leewh 2015-01-29
		$marketing_feed = config_load('marketing_feed');

		$query = $this->goodsmodel->get_goods_all_partner($last_update_date,$view_type,true);
		$slimit = (($page-1)*$pageline);
		$query .= " limit ".$slimit.",".$pageline;
		$result = mysqli_query($this->db->conn_id,$query);

		while ($data_goods = mysqli_fetch_array($result)){ // 20130325
			if ($data_goods['feed_goods_use']=='Y' && !empty($data_goods['feed_goods_name'])
				|| $data_goods['feed_goods_use']=='N' && !empty($marketing_feed['goods_name'])) {

				// 상품 검색어 자동
				$data_goods['keyword'] = $data_goods['openmarket_keyword'];

				## 상품명 치환코드
				$replaceArr = array();
				$replaceArr['{product_name}'] = strip_tags(iconv('UTF-8', 'euc-kr',$data_goods['goods_name']));
				$replaceArr['{product_category}'] = strip_tags(iconv('UTF-8', 'euc-kr',$data_goods['category_title']));
				$replaceArr['{product_brand}'] = strip_tags(iconv('UTF-8', 'euc-kr',$data_goods['brand_title']));
				$replaceArr['{product_tag}'] = strip_tags(iconv('UTF-8', 'euc-kr',$data_goods['keyword']));

				if ($data_goods['feed_goods_use']=='Y') {
					$data_goods['goods_name'] = $this->get_replace_goods_name($data_goods['feed_goods_name'],$replaceArr);
				} else if($data_goods['feed_goods_use']=='N') {
					$data_goods['goods_name'] = $this->get_replace_goods_name($marketing_feed['goods_name'],$replaceArr);
				}
			} else {
				$data_goods['goods_name']	= strip_tags(iconv('UTF-8', 'euc-kr',$data_goods['goods_name']));
			}
			$data_goods['brand']		= iconv('UTF-8', 'euc-kr',$data_goods['brand']);
			$data_goods['manufacture']	= iconv('UTF-8', 'euc-kr',$data_goods['manufacture']);
			$data_goods['orgin']		= iconv('UTF-8', 'euc-kr',$data_goods['orgin']);
			$data_goods['feed_evt_text']	= iconv('UTF-8', 'euc-kr',$data_goods['feed_evt_text']);

			$data_goods['goods_url'] = get_connet_protocol().$_SERVER['HTTP_HOST']."/goods/view?no=".$data_goods['goods_seq'].'&market=daum';

			if(preg_match('/http/', $data_goods['image'])){
				$data_goods['image_url'] = $data_goods['image'];
			}else{
				$data_goods['image_url'] = get_connet_protocol().$_SERVER['HTTP_HOST'].iconv('UTF-8', 'euc-kr',$data_goods['image']);
			}


			// 카테고리
			$page_code ='';
			$page_name ='';
			$arr_category_code = array();
			$arr_category_code = $this->categorymodel->split_category($data_goods['category_code']);
			for($i=0;$i<4;$i++) {
				$data_goods['arr_category_code'][$i] = "";
				$data_goods['arr_category'][$i] = "";
				if( $arr_category_code[$i] ){

					$data_goods['arr_category_code'][$i] = $arr_category_code[$i];
					$data_goods['arr_category'][$i] =  ($this->categorymodel->one_category_name($arr_category_code[$i]));

					$page_code .="<<<cate".($i+1).">>>".$data_goods['arr_category_code'][$i]."\n";
					$page_name .="<<<catename".($i+1).">>>".iconv('UTF-8', 'euc-kr',$data_goods['arr_category'][$i])."\n";
				}
			}


			// 배송비
			if($data_goods['goods_kind'] == "coupon"){
				$data_goods['deliv1'] = "0";
				$data_goods['deliv2'] = "";
				$data_goods['deliv2'] = iconv('UTF-8', 'euc-kr',"무료");
			}else{
			$data_goods['deliv1'] = "1";
			$data_goods['deliv2'] = "유료";
			$delivery = $this->goodsmodel->get_goods_delivery($data_goods);
			if( $delivery['type'] == 'delivery' ){
				if( $delivery['free'] && $delivery['price'] ){
					$data_goods['deliv1'] = "2";
					$data_goods['deliv2'] = get_currency_price($delivery['free'],3)." 이상무료 or ".get_currency_price($delivery['price'],3);
				}else if( ! $delivery['price'] ){
					$data_goods['deliv1'] = "0";
					$data_goods['deliv2'] = "";
				}else{
					$data_goods['deliv1'] = "1";
					$data_goods['deliv2'] = $delivery['price'];
				}
			}
			$data_goods['deliv2'] = iconv('UTF-8', 'euc-kr',$data_goods['deliv2']);
			}

			// 쿠폰
			//$coupon = $this -> _goods_coupon_max($data_goods['goods_seq']);
			//$data_goods['coupon'] = (int) $coupon['goods_sale'];
			$data_goods['shop_name'] = $this->config_basic['shopName'];
			$data_goods['shop_name'] = iconv('UTF-8', 'euc-kr',$data_goods['shop_name']);
			// 2014-01-16 이벤트 문구 미노출 추가
			if($data_goods['feed_evt_sdate'] == '0000-00-00') $data_goods['feed_evt_sdate'] = '';
			if($data_goods['feed_evt_edate'] == '0000-00-00') $data_goods['feed_evt_edate'] = '';
			$data_goods['event']	= $data_goods['feed_evt_text'];
			if( time() < strtotime($data_goods['feed_evt_sdate'].' 00:00:00') || strtotime($data_goods['feed_evt_edate'].' 23:59:59') < time() ){
				$data_goods['event']	= '';
			}

			// 상품가격 추가할인 설정
			if ($arr_marketing_sale) $data_goods['marketing_sale'] = $arr_marketing_sale;

			// 할인가 적용
			$data_goods['price'] = $this->apply_sale($data_goods);

			// 무이자
			if (!empty($marketing_feed['cfg_card_free'])) {
				$noint = trim(iconv('UTF-8', 'euc-kr',$marketing_feed['cfg_card_free']));
			} else {
				$noint = trim(iconv('UTF-8', 'euc-kr',$this->noint));
			}

			unset($loop);
			$loop[] = "<<<begin>>>";
			$loop[] = "<<<pid>>>".$data_goods['goods_seq'];
			$loop[] = "<<<price>>>".$data_goods['price'];
			$loop[] = "<<<pname>>>".$data_goods['goods_name'];
			$loop[] = "<<<pgurl>>>".$data_goods['goods_url'];
			$loop[] = "<<<igurl>>>".$data_goods['image_url'];
			$loop[] = ${page_code}.${page_name}."<<<model>>>".iconv('UTF-8', 'euc-kr',$data_goods['model']);
			$loop[] = "<<<brand>>>".$data_goods['brand'];
			$loop[] = "<<<maker>>>".$data_goods['manufacture'];
			$loop[] = "<<<pdate>>>";
			//$loop[] = "<<<coupon>>>".$data_goods['coupon'];
			if ($data_goods['coupon_won'])$loop[] = "<<<coupon>>>".$data_goods['coupon_won'];
			if($noint)$loop[] = "<<<pcard>>>".$noint;
			if($data_goods['reserve'] > 0)
				$loop[] = "<<<point>>>".$data_goods['reserve'];
			$loop[] = "<<<deliv>>>".$data_goods['deliv1'];
			$loop[] = "<<<deliv2>>>".$data_goods['deliv2'];
			$loop[] = "<<<sellername>>>".$data_goods['shop_name'];
			$loop[] = "<<<event>>>".$data_goods['event'];

			$loop[] = "<<<end>>>";

			fwrite($fp,implode("\r\n",$loop)."\r\n");
		}
		fclose($fp);

		return $page+1;
	}
	/* 입점 마케팅 어바웃 파일 생성 */
	function aboutFile($file_path, $mode='all',$page,$pageline)
	{
		header("Content-Type: text/html; charset=EUC-KR");
		$fileExt = '';
		$last_update_date = '';
		if($mode == 'summary'){
			$tmp = config_load('partner','about_update');
			if($tmp['about_update']) $last_update_date = $tmp['about_update'];
		}

		$arr_status['normal'] = "C";
		$arr_status['runout'] = "D";
		$arr_status['unsold'] = "D";

		$fp = fopen($file_path,"a+");

		$query = $this->goodsmodel->get_goods_all_partner($last_update_date,'view',true);
		$slimit = (($page-1)*$pageline);
		$query .= " limit ".$slimit.",".$pageline;
		$result = mysqli_query($this->db->conn_id,$query);

		while ($data_goods = mysqli_fetch_array($result)){
			$data_goods['goods_name']	= strip_tags(iconv('UTF-8', 'euc-kr',$data_goods['goods_name']));
			$data_goods['brand']		= iconv('UTF-8', 'euc-kr',$data_goods['brand']);
			$data_goods['manufacture']	= iconv('UTF-8', 'euc-kr',$data_goods['manufacture']);
			$data_goods['orgin']		= iconv('UTF-8', 'euc-kr',$data_goods['orgin']);
			$data_goods['model']		= iconv('UTF-8', 'euc-kr',$data_goods['model']);
			$data_goods['feed_evt_text']	= iconv('UTF-8', 'euc-kr',$data_goods['feed_evt_text']);
			$data_goods['goods_url'] = get_connet_protocol().$_SERVER['HTTP_HOST']."/goods/view?no=".$data_goods['goods_seq'].'&market=about';

			if(preg_match('/http/', $data_goods['image'])){
				$data_goods['image_url'] = $data_goods['image'];
			}else{
				$data_goods['image_url'] = get_connet_protocol().$_SERVER['HTTP_HOST'].iconv('UTF-8', 'euc-kr',$data_goods['image']);
			}

			// 카테고리
			$arr_category_code = array();
			$arr_category_code = $this->categorymodel->split_category($data_goods['category_code']);
			for($i=0;$i<4;$i++) {
				$data_goods['arr_category_code'][$i] = "";
				$data_goods['arr_category'][$i] = "";
				if( $arr_category_code[$i] ){
					$data_goods['arr_category_code'][$i] = $arr_category_code[$i];
					$data_goods['arr_category'][$i] =  $this->categorymodel->one_category_name($arr_category_code[$i]);
					$data_goods['arr_category'][$i]	= iconv('UTF-8', 'euc-kr',$data_goods['arr_category'][$i]);
				}
			}

			// 배송비
			if($data_goods['goods_kind'] == "coupon"){
					$data_goods['delivery'] = "0";
			}else{
			$delivery = $this->goodsmodel->get_goods_delivery($data_goods);
			if( $delivery['type'] == 'delivery' ){
				$data_goods['delivery'] = (int) $delivery['price'];
			}else{
				$data_goods['delivery'] = "-1";
			}
			}

			// 쿠폰
			//$coupon = $this -> _goods_coupon_max($data_goods['goods_seq']);
			//$data_goods['coupon'] = (int) $coupon['goods_sale'];

			// 수정일
			if( $data_goods['update_date'] == '0000-00-00 00:00:00' ){
				$data_goods['update_date'] = $data_goods['regist_date'];
			}

			// 구분
			if( $data_goods['regist_date'] > $last_update_date ){
				$data_goods['class'] = "C";
			}
			if( $data_goods['update_date'] > $last_update_date ){
				$data_goods['class'] = $arr_status[$data_goods['goods_status']];
			}
			// 2014-01-16 이벤트 문구 미노출 추가
			if($data_goods['feed_evt_sdate'] == '0000-00-00') $data_goods['feed_evt_sdate'] = '';
			if($data_goods['feed_evt_edate'] == '0000-00-00') $data_goods['feed_evt_edate'] = '';
			$data_goods['event']	= $data_goods['feed_evt_text'];
			if( time() < strtotime($data_goods['feed_evt_sdate'].' 00:00:00') || strtotime($data_goods['feed_evt_edate'].' 23:59:59') < time() ){
				$data_goods['event']	= '';
			}

			// 할인가 적용
			$data_goods['price'] = $this->apply_sale($data_goods);

			// 무이자
			$noint = trim(iconv('UTF-8', 'euc-kr',$this->noint_about));

			$r_item = array();
			$r_item[] = $data_goods['goods_seq'];
			$r_item[] = 'C';
			$r_item[] = $data_goods['goods_name'];
			$r_item[] = $data_goods['price'];
			$r_item[] = $data_goods['goods_url'];
			$r_item[] = $data_goods['image_url'];
			$r_item[] = $data_goods['arr_category_code'][0];
			$r_item[] = $data_goods['arr_category_code'][1];
			$r_item[] = $data_goods['arr_category_code'][2];
			$r_item[] = $data_goods['arr_category_code'][3];
			$r_item[] = $data_goods['arr_category'][0];
			$r_item[] = $data_goods['arr_category'][1];
			$r_item[] = $data_goods['arr_category'][2];
			$r_item[] = $data_goods['arr_category'][3];
			$r_item[] = $data_goods['model'];
			$r_item[] = $data_goods['brand'];
			$r_item[] = $data_goods['manufacture'];
			$r_item[] = $data_goods['orgin'];
			$r_item[] = '';
			$r_item[] = $data_goods['delivery'];
			$r_item[] = $data_goods['event'];
			$r_item[] = $data_goods['coupon'];
			$r_item[] = $noint;
			$r_item[] = $data_goods['reserve'];
			$r_item[] = '';
			$r_item[] = '';
			$r_item[] = '';
			$r_item[] = $data_goods['update_date'];

			fwrite($fp,implode('<!>',$r_item)."\r\n");
		}
		fclose($fp);

		config_save('partner',array('about_update'=>date('Y-m-d H:i:s')));
		return $page+1;
	}
	/* 입점 마케팅 네이버 파일 생성 */
	function naverFile($file_path, $mode='all',$page,$pageline)
	{
		header("Content-Type: text/html; charset=EUC-KR");

		$fileExt = '';
		$last_update_date = '';
		if($mode == 'summary'){
			$tmp = config_load('partner','naver_update');
			if($tmp['naver_update']) $last_update_date = $tmp['naver_update'];
		}

		$all_category = $this->categorymodel->get_all();
		foreach ($all_category as $row){
			$data['category_code'];
			$cate[$row['category_code']] = $row['title'];
		}


		$arr_status['normal'] = "U";
		$arr_status['runout'] = "D";
		$arr_status['unsold'] = "D";

		$fp = fopen($file_path,"a+");

		$market_image	= config_load('marketing_image');
		if($market_image['naverImage']=='B' || !$market_image['naverImage']){
			$view_type	= "view";
		}else if($market_image['naverImage']=='C'){
			$view_type	= "large";
		}

		// 입점마케팅 상품 추가할인
		$marketing_sale = config_load('marketing_sale');
		$arr_marketing_sale = array();
		if (isset($marketing_sale)) {
			// 회원 등급별 할인 적용 [member_sale_type => 0 : 비회원 , 1 : 일반 등급 회원]
			if ($marketing_sale['member']=='Y' && $marketing_sale['member_sale_type']==1) $arr_marketing_sale['member'] = 'Y';

			// 할인 유입경로 적용
			if ($marketing_sale['referer']=='Y') 	{
				$arr_marketing_sale['referer'] = 'Y';
				$arr_marketing_sale['referer_url'] = 'shopping.naver.com';
			}

			// 할인 쿠폰 적용
			if ($marketing_sale['coupon']=='Y') $arr_marketing_sale['coupon'] = 'Y';

			// 모바일 할인 적용 : 지식쇼핑 모바일가격 별도 계산해서 전달
			//if ($marketing_sale['mobile']=='Y') $arr_marketing_sale['mobile'] = 'Y';
		}

		### 입점마케팅 전달 데이터 통합 설정 - 입점마케팅 상품명,카드무이자할부 leewh 2015-01-29
		$marketing_feed = config_load('marketing_feed');

		$query	= $this->goodsmodel->get_goods_all_partner($last_update_date,$view_type,true); // 20130325
		$slimit = (($page-1)*$pageline);
		$query .= " limit ".$slimit.",".$pageline;
		$result = mysqli_query($this->db->conn_id,$query);

		while ($data_goods = mysqli_fetch_array($result)){ // 20130325
			if ($data_goods['feed_goods_use']=='Y' && !empty($data_goods['feed_goods_name'])
				|| $data_goods['feed_goods_use']=='N' && !empty($marketing_feed['goods_name'])) {

				// 상품 검색어 자동
				$data_goods['keyword'] = $data_goods['openmarket_keyword'];

				## 상품명 치환코드
				$replaceArr = array();
				$replaceArr['{product_name}'] = strip_tags(iconv('UTF-8', 'euc-kr',$data_goods['goods_name']));
				$replaceArr['{product_category}'] = strip_tags(iconv('UTF-8', 'euc-kr',$data_goods['category_title']));
				$replaceArr['{product_brand}'] = strip_tags(iconv('UTF-8', 'euc-kr',$data_goods['brand_title']));
				$replaceArr['{product_tag}'] = strip_tags(iconv('UTF-8', 'euc-kr',$data_goods['keyword']));

				if ($data_goods['feed_goods_use']=='Y') {
					$data_goods['goods_name'] = $this->get_replace_goods_name($data_goods['feed_goods_name'],$replaceArr);
				} else if($data_goods['feed_goods_use']=='N') {
					$data_goods['goods_name'] = $this->get_replace_goods_name($marketing_feed['goods_name'],$replaceArr);
				}
			} else {
				$data_goods['goods_name']	= strip_tags(iconv('UTF-8', 'euc-kr',$data_goods['goods_name']));
			}
			$data_goods['brand']		= iconv('UTF-8', 'euc-kr',$data_goods['brand']);
			$data_goods['manufacture']	= iconv('UTF-8', 'euc-kr',$data_goods['manufacture']);
			$data_goods['orgin']		= iconv('UTF-8', 'euc-kr',$data_goods['orgin']);
			$data_goods['feed_evt_text']	= iconv('UTF-8', 'euc-kr',$data_goods['feed_evt_text']);
			$data_goods['feed_condition']	= iconv('UTF-8', 'euc-kr',$data_goods['feed_condition']);

			$data_goods['goods_url'] = get_connet_protocol().$_SERVER['HTTP_HOST']."/goods/view?no=".$data_goods['goods_seq'].'&market=naver';
			$mourl = str_replace('www.','',$data_goods['goods_url']);
			$data_goods['mourl'] = str_replace(get_connet_protocol(), get_connet_protocol().'m.',$mourl);

			if(preg_match('/http/', $data_goods['image'])){
				$data_goods['image_url'] = $data_goods['image'];
			}else{
				$data_goods['image_url'] = get_connet_protocol().$_SERVER['HTTP_HOST'].iconv('UTF-8', 'euc-kr',$data_goods['image']);
			}

			// 카테고리
			$page_code ='';
			$page_name ='';
			$arr_category_code = array();
			$arr_category_code = $this->categorymodel->split_category($data_goods['category_code']);
			for($i=0;$i<4;$i++) {
				$data_goods['arr_category_code'][$i] = "";
				$data_goods['arr_category'][$i] = "";
				if( $arr_category_code[$i] ){

					$data_goods['arr_category_code'][$i] = $arr_category_code[$i];
					$data_goods['arr_category'][$i] =  ($this->categorymodel->one_category_name($arr_category_code[$i]));

					$page_code .="<<<caid".($i+1).">>>".$data_goods['arr_category_code'][$i]."\n";
					$page_name .="<<<cate".($i+1).">>>".iconv('UTF-8', 'euc-kr',$data_goods['arr_category'][$i])."\n";
				}else{
					$page_code .="<<<caid".($i+1).">>>\n";
					$page_name .="<<<cate".($i+1).">>>\n";
				}
			}

			// 배송비
			if($data_goods['goods_kind'] == "coupon"){
				$data_goods['delivery'] = "0";
			}else{
			$delivery = $this->goodsmodel->get_goods_delivery($data_goods);
			if( $delivery['type'] == 'delivery' ){
				if( $delivery['free'] && $delivery['price'] ){
					if($delivery['free'] > $data_goods['price']){
						$data_goods['delivery'] = $delivery['price'];
					}else{
						$data_goods['delivery'] = "0";
					}
				}else{
					$data_goods['delivery'] = $delivery['price'];
				}
			}else{
				$data_goods['delivery'] = "-1";
			}
			}

			// 쿠폰
			//$coupon = $this -> _goods_coupon_max($data_goods['goods_seq']);
			//$data_goods['coupon'] = (int) $coupon['goods_sale'];

			// 구분
			if( $data_goods['regist_date'] > $last_update_date ){
				$data_goods['class'] = "I";
			}
			if( $data_goods['update_date'] > $last_update_date ){
				$data_goods['class'] = $arr_status[$data_goods['goods_status']];
			}
			// 2014-01-16 이벤트 문구 미노출 추가
			if($data_goods['feed_evt_sdate'] == '0000-00-00') $data_goods['feed_evt_sdate'] = '';
			if($data_goods['feed_evt_edate'] == '0000-00-00') $data_goods['feed_evt_edate'] = '';
			$data_goods['event']	= $data_goods['feed_evt_text'];
			if( time() < strtotime($data_goods['feed_evt_sdate'].' 00:00:00') || strtotime($data_goods['feed_evt_edate'].' 23:59:59') < time() ){
				$data_goods['event']	= '';
			}


			// 상품가격 추가할인 설정
			if ($arr_marketing_sale) $data_goods['marketing_sale'] = $arr_marketing_sale;

			// 할인가 적용
			$data_goods['price'] = $this->apply_sale($data_goods);

			// 무이자
			if (!empty($marketing_feed['cfg_card_free'])) {
				$noint = trim(iconv('UTF-8', 'euc-kr',$marketing_feed['cfg_card_free']));
			} else {
				$noint = trim(iconv('UTF-8', 'euc-kr',$this->noint));
			}

			$systemmobiles = $this->configsalemodel->get_mobile_sale_for_goods($data_goods['price']);
			$mobile_price = 0;
			if($systemmobiles['sale_price'] && $data_goods['price']){
				$mobile_sale = $systemmobiles['sale_price'] * $data_goods['price'] / 100;
				$mobile_price = $data_goods['price'] - $mobile_sale;
				$mobile_price = $this->sale->cut_sale_price($mobile_price);
			}

			unset($loop);
			$loop[] = "<<<begin>>>";
			$loop[] = "<<<mapid>>>".$data_goods['goods_seq'];
			$loop[] = "<<<pname>>>".$data_goods['goods_name'];
			$loop[] = "<<<price>>>".$data_goods['price'];
			$loop[] = "<<<pgurl>>>".$data_goods['goods_url'];
			$loop[] = "<<<igurl>>>".$data_goods['image_url'];
			$loop[] = ${page_code}.${page_name}."<<<brand>>>".$data_goods['brand'];
			$loop[] = "<<<maker>>>".$data_goods['manufacture'];
			$loop[] = "<<<origi>>>".$data_goods['orgin'];
			$loop[] = "<<<deliv>>>".$data_goods['delivery'];
			//$loop[] = "<<<coupo>>>".$data_goods['coupo'];
			if ($data_goods['coupon_won'])$loop[] = "<<<coupo>>>".$data_goods['coupon_won'];
			if($noint)$loop[] = "<<<pcard>>>".$noint;
			$loop[] = "<<<point>>>".$data_goods['reserve'];
			if( $mode == 'summary' ){
				$loop[] = "<<<class>>>".$data_goods['class'];
				$loop[] = "<<<utime>>>".$data_goods['update_date'];
			}
			$loop[] = "<<<event>>>".$data_goods['event'];
			if($mobile_price) $loop[] = "<<<mpric>>>".$mobile_price;
			$loop[] = "<<<revct>>>".$data_goods['review_count'];
			$loop[] = "<<<mourl>>>".$data_goods['mourl'];
			$loop[] = "<<<condition>>>".$data_goods['feed_condition'];
			$loop[] = "<<<ftend>>>";

			fwrite($fp,implode("\r\n",$loop)."\r\n");
		}
		fclose($fp);

		return $page+1;
	}

	public function _enuri_category()
	{
		$all_category = $this->categorymodel->get_list();
		foreach ($all_category as $row){
			$cate[$row['category_code']]['title'] = $row['title'];
			$all_category2 = $this->categorymodel->get_list($row['category_code']);
			foreach ($all_category2 as $row2){
				$cate[$row['category_code']]['sub'][$row2['category_code']]['title'] = $row2['title'];
			}
		}

		echo "<table border=\"0\" cellspacing=\"1\" cellpadding=\"5\" bgcolor=\"black\" width=\"91%\" align='center'>";
		echo "<tr bgcolor=\"#ededed\">";
		echo "<th width=60 align=center>대분류</th>";
		echo "<th>중분류</th>";
		echo "</tr>";
		foreach($cate as $code1 => $step1){
			$url = get_connet_protocol() . $_SERVER['HTTP_HOST'] . "/partner/enuri/".$code1;
			echo "<tr bgcolor='white'>";
			echo "<td align=center><a href='".$url."'>".$step1['title']."</a></td>";
			echo "<td>";
			foreach($step1['sub'] as $code2 => $step2){
				$url = get_connet_protocol() . $_SERVER['HTTP_HOST'] . "/partner/enuri/".$code2;
				echo "<a href='".$url."'>".$step2['title']."</a> |";
			}
			echo "</td>";
			echo "</tr>";
		}
		echo "</table>";

	}

	public function _enuri_goods($categorycode)
	{
		$cfg_order = config_load('order');

		$page = $_GET['page'];
		if(!$page) $page = 1;
		$query	= $this->goodsmodel->get_goods_all_partner('','view',true); // 20130325
		$query .= " and l.category_code like '".$categorycode."%'";

		// paging (페이지당출력수,현재페이지넘버,페이지숫자링크갯수,쿼리,인자)
		$result = select_page(1000,$page,10,$query);
		echo "<center>상품수 : ".$result['page']['totalcount']." 개</center>";
		echo "<table border=\"0\" cellspacing=\"1\" cellpadding=\"1\" bgcolor=\"black\" width=\"600\" align='center'>";
		echo "<tr align=\"center\" bgcolor=\"EDEDED\">";
		echo "<td width=\"25\" height=\"24\" align=\"center\">번호</td>";
		echo "<td width=\"180\" height=\"24\" align=\"center\">제품명</td>";
		echo "<td width=\"40\" height=\"24\" align=\"center\">가격</td>";
		echo "<td width=\"35\" height=\"24\" align=\"center\">재고<br>유무</td>";
		echo "<td width=\"50\" height=\"24\" align=\"center\">배송</td>";
		echo "<td width=\"90\" height=\"24\" align=\"center\">웹상품이미지</td>";
		echo "<td width=\"30\" height=\"24\" align=\"center\">할인<br>쿠폰 <br></td>";
		echo "<td width=\"30\" height=\"24\" align=\"center\">계산서</td>";
		echo "<td width=\"50\" height=\"24\" align=\"center\">제조사</td>";
		echo "<td width=\"30\" height=\"24\" align=\"center\">상품코드</td>";
		echo "<td width=\"50\" height=\"24\" align=\"center\">무이자<br>할부</td>";
		echo "</tr>";
		foreach($result['record'] as $data){
			$data['goods_name']	= strip_tags($data['goods_name']);

			// 품절이면
			$stock_yn = "재고<br>있음";
			if($data['goods_status']!='normal') $stock_yn = "재고<br>없음";
			$tax = ($cfg_order['biztype']=='tax')? "Y" : "N";
			$coupon = $this -> _goods_coupon_max($data['goods_seq']);

			// 배송비
			if($data['goods_kind'] == "coupon"){
				$data['delivery'] = "무료배송";
			}else{
			$delivery = $this->goodsmodel->get_goods_delivery($data);
			if( $delivery['type'] == 'delivery' ){
				if( $delivery['free'] && $delivery['price'] ){
					if($delivery['free'] > $data_goods['price']){
						$data['delivery'] =  get_currency_price($delivery['free'],3)." 미만 ".get_currency_price($delivery['price'],3);
					}else{
						$data['delivery'] = 0;
					}
				}else{
					$data['delivery'] = $delivery['price'];
				}
			}
			if($data['delivery'] == 0) $data['delivery'] = "무료배송";

			}
			$url = get_connet_protocol() . $_SERVER['HTTP_HOST'] . "/goods/view?no=".$data['goods_seq'];
			if(preg_match('/http:\/\//', $data['image'])){
				$image_url = $data['image'];
			}else{
				$image_url = get_connet_protocol().$_SERVER['HTTP_HOST'].$data['image'];
			}

			// 할인가 적용
			$data['price'] = $this->apply_sale($data);

			echo "<tr align=\"center\" bgcolor=\"#FFFFFF\">";
			echo "<td height=\"24\">".$data['_no']."</td>";
			echo "<td height=\"24\" style=\"padding-top:3px;padding-bottom:3px\">";
			echo "<a href='".$url."' class=\"link_category1\">".$data['goods_name']."</a>";
			echo "</td>";
			echo "<td height=\"24\">".number_format($data['price'])."</td>";
			echo "<td height=\"24\">".$stock_yn."</td>";
			echo "<td height=\"24\">".$data['delivery']."</td>";
			echo "<td height=\"24\">".$image_url."</td>";
			echo "<td height=\"24\">".$coupon['percent_goods_sale']."</td>";
			echo "<td height=\"24\">".$tax."</td>";
			echo "<td height=\"24\">".$data['manufacture']."</td>";
			echo "<td height=\"24\">".$data['goods_code']."</td>";
			echo "<td height=\"24\">".$this->noint."</td>";
			echo "</tr>";
		}
		echo "</table>";

		echo "<table border=\"0\" cellspacing=\"1\" cellpadding=\"10\" bgcolor=\"white\" width=\"95%\" align='center'>";
		echo "<tr>";
		echo "<td align='center'>◀ ";
		foreach($result['page']['page'] as $data){
			echo "<a href='?page=".$data."'>".$data."</a>";
		}
		echo " ▶</td>";
		echo "</tr>";
		echo "</table>";

	}

	public function enuri()
	{

		if(!$this->uri->rsegments[3]) exit;
		if( $this->uri->rsegments[3] == 'category' ){
			$this->_enuri_category();
		}else{
			$this->_enuri_goods( $this->uri->rsegments[3] );
		}

	}

	## 상품명 치환코드
	public function get_replace_goods_name($goods_name, $replaceArr) {
		$goods_name = strip_tags(iconv('UTF-8', 'euc-kr',$goods_name));
		foreach ($replaceArr as $key => $val){
			$patterns[]		= "/".$key."/";
			$replacements[]	= $val;
		}
		$gname	= preg_replace($patterns, $replacements, $goods_name);
		return $gname;
	}

	### 입점 마케팅 다음 (구버전)
	public function daum(){
		header("Content-Type: text/html; charset=EUC-KR");
		$marketset = config_load('marketing');
		if($marketset['marketdaum']=='y'){
			$marketFiledaum	= ROOTPATH."/data/marketFile/daum.txt";
			if(file_exists($marketFiledaum)){
				$fp = fopen($marketFiledaum,"r");
				while(!feof($fp)) echo fgets($fp,2048);
				exit;
			}
		}

		$last_update_date = '';
		if($_GET['mode'] == 'summary'){
			$mode = 'summary';
			$tmp = config_load('partner','daum_update');
			if($tmp['naver_update']) $last_update_date = $tmp['daum_update'];
		}else{
			$mode = 'all';
		}

		// 마케팅 전달 이미지 lwh 2014-02-28
		$market_image	= config_load('marketing_image');
		if($market_image['daumImage']=='B' || !$market_image['daumImage']){
			$view_type	= "view";
		}else if($market_image['daumImage']=='C'){
			$view_type	= "large";
		}

		// 입점마케팅 상품 추가할인
		$marketing_sale = config_load('marketing_sale');
		$arr_marketing_sale = array();
		if (isset($marketing_sale)) {
			// 회원 등급별 할인 적용 [member_sale_type => 0 : 비회원 , 1 : 일반 등급 회원]
			if ($marketing_sale['member']=='Y' && $marketing_sale['member_sale_type']==1) $arr_marketing_sale['member'] = 'Y';

			// 할인 유입경로 적용
			if ($marketing_sale['referer']=='Y') 	{
				$arr_marketing_sale['referer'] = 'Y';
				$arr_marketing_sale['referer_url'] = 'shopping.daum.net';
			}

			// 할인 쿠폰 적용
			if ($marketing_sale['coupon']=='Y') $arr_marketing_sale['coupon'] = 'Y';

			// 모바일 할인 적용 : 쇼핑하우 미적용(쇼핑하우 전달용 모바일 할인 필드 없음)
			//if ($marketing_sale['mobile']=='Y') $arr_marketing_sale['mobile'] = 'Y';
		}

		### 입점마케팅 전달 데이터 통합 설정 - 입점마케팅 상품명,카드무이자할부 leewh 2015-01-29
		$marketing_feed = config_load('marketing_feed');

		$query = $this->goodsmodel->get_goods_all_partner($last_update_date,$view_type,true);
		$result = mysqli_query($this->db->conn_id,$query);
		while ($data_goods = mysqli_fetch_array($result)){ // 20130325
			if ($data_goods['feed_goods_use']=='Y' && !empty($data_goods['feed_goods_name'])
				|| $data_goods['feed_goods_use']=='N' && !empty($marketing_feed['goods_name'])) {

				// 상품 검색어 자동
				$data_goods['keyword'] = $data_goods['openmarket_keyword'];

				## 상품명 치환코드
				$replaceArr = array();
				$replaceArr['{product_name}'] = strip_tags(iconv('UTF-8', 'euc-kr',$data_goods['goods_name']));
				$replaceArr['{product_category}'] = strip_tags(iconv('UTF-8', 'euc-kr',$data_goods['category_title']));
				$replaceArr['{product_brand}'] = strip_tags(iconv('UTF-8', 'euc-kr',$data_goods['brand_title']));
				$replaceArr['{product_tag}'] = strip_tags(iconv('UTF-8', 'euc-kr',$data_goods['keyword']));

				if ($data_goods['feed_goods_use']=='Y') {
					$data_goods['goods_name'] = $this->get_replace_goods_name($data_goods['feed_goods_name'],$replaceArr);
				} else if($data_goods['feed_goods_use']=='N') {
					$data_goods['goods_name'] = $this->get_replace_goods_name($marketing_feed['goods_name'],$replaceArr);
				}
			} else {
				$data_goods['goods_name']	= strip_tags(iconv('UTF-8', 'euc-kr',$data_goods['goods_name']));
			}

			$data_goods['brand']		= iconv('UTF-8', 'euc-kr',$data_goods['brand']);
			if	($marketing_feed['brand_kind'] == 'brand')
				$data_goods['brand']		= iconv('UTF-8', 'euc-kr',$data_goods['brand_title']);

			$data_goods['manufacture']	= iconv('UTF-8', 'euc-kr',$data_goods['manufacture']);
			$data_goods['orgin']		= iconv('UTF-8', 'euc-kr',$data_goods['orgin']);
			$data_goods['feed_evt_text']	= iconv('UTF-8', 'euc-kr',$data_goods['feed_evt_text']);

			$data_goods['goods_url'] = get_connet_protocol().$_SERVER['HTTP_HOST']."/goods/view?no=".$data_goods['goods_seq'].'&market=daum';

			if(preg_match('/http/', $data_goods['image'])){
				$data_goods['image_url'] = $data_goods['image'];
			}else{
				$data_goods['image_url'] = get_connet_protocol().$_SERVER['HTTP_HOST'].iconv('UTF-8', 'euc-kr',$data_goods['image']);
			}


			// 카테고리
			$page_code ='';
			$page_name ='';
			$arr_category_code = array();
			$arr_category_code = $this->categorymodel->split_category($data_goods['category_code']);
			for($i=0;$i<4;$i++) {
				$data_goods['arr_category_code'][$i] = "";
				$data_goods['arr_category'][$i] = "";
				if( $arr_category_code[$i] ){

					$data_goods['arr_category_code'][$i] = $arr_category_code[$i];
					$data_goods['arr_category'][$i] =  ($this->categorymodel->one_category_name($arr_category_code[$i]));

					$page_code .="<<<cate".($i+1).">>>".$data_goods['arr_category_code'][$i]."\n";
					$page_name .="<<<catename".($i+1).">>>".iconv('UTF-8', 'euc-kr',$data_goods['arr_category'][$i])."\n";
				}
			}


			// 배송비
			if($data_goods['goods_kind'] == "coupon"){
				$data_goods['deliv1'] = "0";
				$data_goods['deliv2'] = "";
				$data_goods['deliv2'] = iconv('UTF-8', 'euc-kr',"무료");
			}else{
				$data_goods['deliv1'] = "1";
				$data_goods['deliv2'] = "유료";
				$delivery = $this->goodsmodel->get_goods_delivery($data_goods);
				if( $delivery['type'] == 'delivery' ){
					if( $delivery['free'] && $delivery['price'] ){
						$data_goods['deliv1'] = "2";
						$data_goods['deliv2'] = get_currency_price($delivery['free'],3)." 이상무료 or ".get_currency_price($delivery['price'],3);
					}else if( ! $delivery['price'] ){
						$data_goods['deliv1'] = "0";
						$data_goods['deliv2'] = "";
					}else{
						$data_goods['deliv1'] = "1";
						$data_goods['deliv2'] = $delivery['price'];
					}
				}
				$data_goods['deliv2'] = iconv('UTF-8', 'euc-kr',$data_goods['deliv2']);
			}

			// 쿠폰
			//$coupon = $this -> _goods_coupon_max($data_goods['goods_seq']);
			//$data_goods['coupon'] = (int) $coupon['goods_sale'];
			$data_goods['shop_name'] = $this->config_basic['shopName'];
			$data_goods['shop_name'] = iconv('UTF-8', 'euc-kr',$data_goods['shop_name']);
			$data_goods['event']		= $data_goods['feed_evt_text'];
			if( time() < strtotime($data_goods['feed_evt_sdate'].' 00:00:00') || strtotime($data_goods['feed_evt_edate'].' 23:59:59') < time() ){
				$data_goods['event']	= '';
			}

			// 상품가격 추가할인 설정
			if ($arr_marketing_sale) $data_goods['marketing_sale'] = $arr_marketing_sale;

			// 할인가 적용
			$data_goods['price'] = $this->apply_sale($data_goods);

			// 무이자
			if (!empty($marketing_feed['cfg_card_free'])) {
				$noint = trim(iconv('UTF-8', 'euc-kr',$marketing_feed['cfg_card_free']));
			} else {
				$noint = trim(iconv('UTF-8', 'euc-kr',$this->noint));
			}

			unset($loop);
			$loop[] = "<<<begin>>>";
			$loop[] = "<<<pid>>>".$data_goods['goods_seq'];
			$loop[] = "<<<price>>>".$data_goods['price'];
			$loop[] = "<<<pname>>>".$data_goods['goods_name'];
			$loop[] = "<<<pgurl>>>".$data_goods['goods_url'];
			$loop[] = "<<<igurl>>>".$data_goods['image_url'];
			$loop[] = ${page_code}.${page_name}."<<<model>>>".iconv('UTF-8', 'euc-kr',$data_goods['model']);
			$loop[] = "<<<brand>>>".$data_goods['brand'];
			$loop[] = "<<<maker>>>".$data_goods['manufacture'];
			$loop[] = "<<<pdate>>>";
			//$loop[] = "<<<coupon>>>".$data_goods['coupon'];
			if ($data_goods['coupon_won'])$loop[] = "<<<coupon>>>".$data_goods['coupon_won'];
			if($noint)$loop[] = "<<<pcard>>>".$noint;
			if($data_goods['reserve'] > 0)
				$loop[] = "<<<point>>>".$data_goods['reserve'];
			$loop[] = "<<<deliv>>>".$data_goods['deliv1'];
			$loop[] = "<<<deliv2>>>".$data_goods['deliv2'];
			$loop[] = "<<<sellername>>>".$data_goods['shop_name'];
			$loop[] = "<<<event>>>".$data_goods['event'];

			$loop[] = "<<<end>>>";

			echo implode("\r\n",$loop)."\r\n";

		}
	}

	### 입점 마케팅 다음 파일 로드 :: 2015-11-30 lwh
	function daum_engine(){
		if($_GET['mode'])	{ $mode = $_GET['mode']; }
		else				{ $mode = 'all'; }

		// 혹시 요약버전으로 들어왔을경우
		if($mode == 'summary'){
			$this->partnermodel->cron_daumFile('summary','echo');
			exit;
		}

		$file_path	= ROOTPATH."/ep/daum_".$mode.".txt";
		if(file_exists($file_path)){
			// EUC-KR 선언
			header("Content-Type: text/html; charset=EUC-KR");
			$fp = fopen($file_path,"r");
			while(!feof($fp)) echo fgets($fp,2048);
			fclose($fp);
		}else{
			$this->partnermodel->cron_daumFile('all','echo');
		}
	}

	### 네이버 EP 2.0 포멧 생성 / 입점 마케팅 네이버 파일 로드 :: 2015-12-01 lwh
	function naver(){
		if($_GET['mode'])	{ $mode = $_GET['mode']; }
		else				{ $mode = 'all'; }

		config_save('naver_cpc',array('use'=> 'Y'));
		config_save('naver_cpc',array('ip'=> $_SERVER["REMOTE_ADDR"]));

		// 혹시 요약버전으로 들어왔을경우
		if($mode == 'summary'){
			$this->partnermodel->cron_naverFile('summary','echo');
			exit;
		}

		$file_path	= ROOTPATH."/ep/naver_".$mode.".txt";
		if(file_exists($file_path)){
			// EUC-KR 선언
			header("Content-Type: text/html; charset=EUC-KR");
			$fp = fopen($file_path,"r");
			while(!feof($fp)) echo fgets($fp,2048);
			fclose($fp);
			exit;
		}else{
			$this->partnermodel->cron_naverFile('all','echo');
		}
	}

	# 네이버 EP 3.0 포멧 생성
	function naver_third() {
		# reset
		$mode = $_GET['mode'] ? $_GET['mode'] : 'all';

		config_save('naver_cpc',array('use'=> 'Y'));
		config_save('naver_cpc',array('ip'=> $_SERVER["REMOTE_ADDR"]));

		$file_path	= ROOTPATH."/ep/naver_third_".$mode.".tsv";
		if(file_exists($file_path)){
			// UTF-8 선언
			header("Content-Type: text/html; charset=UTF-8");
			$fp = fopen($file_path,"r");
			while(!feof($fp)) echo fgets($fp,2048);
			fclose($fp);
			exit;
		}else{
			$this->partnermodel->cron_naverThirdFile('all','echo');
		}
	}

	### 다음 상품평 파일 로드 :: 2015-12-01 lwh
	function daum_review(){
		if($_GET['mode'])	{ $mode = $_GET['mode']; }
		else				{ $mode = 'all'; }

		// 혹시 요약버전으로 들어왔을경우
		if($mode == 'summary'){
			$this->partnermodel->cron_reviewFile('summary','echo');
			exit;
		}

		$file_path	= ROOTPATH."/ep/review_".$mode.".txt";
		if(file_exists($file_path)){
			// EUC-KR 선언
			header("Content-Type: text/html; charset=EUC-KR");
			$fp = fopen($file_path,"r");
			while(!feof($fp)) echo fgets($fp,2048);
			fclose($fp);
			exit;
		}else{
			$this->partnermodel->cron_reviewFile('all','echo');
		}
	}

	# [판매지수 EP] 네이버 EP 판매지수 조회 :: 2018-09-19 pjw
	function naver_sales_ep() {
		config_save('naver_cpc',array('use'=> 'Y'));
		config_save('naver_cpc',array('ip'=> $_SERVER["REMOTE_ADDR"]));

		$file_path	= ROOTPATH."/ep/naver_sales_ep.tsv";
		if(file_exists($file_path)){
			// UTF-8 선언
			header("Content-Type: text/html; charset=UTF-8");
			$fp = fopen($file_path,"r");
			while(!feof($fp)) echo fgets($fp,2048);
			fclose($fp);
			exit;
		}else{
			$this->partnermodel->cron_naverSalesEP('all','echo');
		}
	}

	function facebook()
	{
		$file_path	= ROOTPATH."/ep/facebook.tsv";
		if(file_exists($file_path) && !$_GET['reload']){
			// UTF-8 선언
			header("Content-Type: text/html; charset=UTF-8");
			$fp = fopen($file_path,"r");
			while(!feof($fp)) echo fgets($fp,2048);
			fclose($fp);
			exit;
		}else{
			$this->partnermodel->cron_feedFiles('file','facebook');
			if($_GET['reload']){
				echo json_encode(config_load('partner'));
			}
		}
	}

	function google()
	{
		$this->partner_info	= config_load('partner');
		// 구글 머천트 센터에서 직접 발급 받은 경우 피드 URL 조건문
		if( $this->partner_info['google_merchant_use'] == 'Y' && $this->config_system['google_feed_use'] != 'Y' ) {
			return false;
		}

		$file_path	= ROOTPATH."/ep/google.txt";
		if(file_exists($file_path) && !$_GET['reload']){
			// UTF-8 선언
			header("Content-Type: text/html; charset=UTF-8");
			$fp = fopen($file_path,"r");
			while(!feof($fp)) echo fgets($fp,2048);
			fclose($fp);
			exit;
		}else{
			$this->partnermodel->cron_feedFiles('file','google');
			if($_GET['reload']){
				echo json_encode(config_load('partner'));
			}
		}
	}

	public function googleShipping()
	{
		$this->load->model('shippingmodel');
		$res = $this->shippingmodel->get_shipping_for_feed();

		$data									= array();
		$data['accountId']						= '';
		$data['services']						= array();
		$data['services'][0]['name']			= $res['info']['shipping_group_name'];
		$data['services'][0]['deliveryCountry']	= 'KR';
		$data['services'][0]['active']			= true;
		$data['services'][0]['currency']		= 'KRW';
		$data['services'][0]['deliveryTime']	= array('minTransitTimeInDays' => 9, 'maxTransitTimeInDays' => 18);
		$data['services'][0]['rateGroups']		= array();

		foreach($res['basicInfos'][0] as $kk => $basicInfo){
			if($basicInfo['shipping_opt_type'] != 'free' && $basicInfo['shipping_opt_type'] != 'fixed'){
				$optType = "";

				if($basicInfo['section_ed'] <= 0 || (substr($basicInfo['shipping_opt_type'], -3) == 'rep' && $kk == count($res['basicInfos'][0]) - 1)){
					$basicInfo['section_ed'] = "infinity";
				} else {
					$basicInfo['section_ed'] = $basicInfo['section_ed'];
				}

				switch($basicInfo['shipping_opt_type']){
					case "amount":
					case "amount_rep":
						$optType = "prices";
						$data['services'][0]['rateGroups'][0]['mainTable']['rowHeaders'][$optType][$kk] = array('currency' => 'KRW', 'value' => $basicInfo['section_ed']);
						break;

					case "weight":
					case "weight_rep":
						$optType = "weights";
						$data['services'][0]['rateGroups'][0]['mainTable']['rowHeaders'][$optType][$kk] = array('unit' => 'KG', 'value' =>$basicInfo['section_ed']);
						break;

					case "cnt":
					case "cnt_rep":
						$optType = "numberOfItems";
						$data['services'][0]['rateGroups'][0]['mainTable']['rowHeaders'][$optType][$kk] = $basicInfo['section_ed'];
						break;
				}

				$data['services'][0]['rateGroups'][0]['mainTable']['rows'][$kk]['cells'][]['flatRate'] = array(
					'currency'	=> 'KRW',
					'value'		=> $basicInfo['shipping_cost']
				);

			} else {
				$data['services'][0]['rateGroups'][0]['singleValue']['flatRate']['currency'] = 'KRW';
				$data['services'][0]['rateGroups'][0]['singleValue']['flatRate']['value']	= $res['basicInfos'][0][0]['shipping_cost'];
			}

		}

		echo json_encode($data);
	}
}

/* End of file partner.php */
/* Location: ./app/controllers/partner.php */
