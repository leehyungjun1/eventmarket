<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');
require_once(APPPATH ."controllers/base/front_base".EXT);
class promotion extends front_base {

	public function __construct() {
		parent::__construct();
		$this->load->model('membermodel');
	}

	/** 프로모션 > 쿠폰
	** @ 방식1 : 페이지
	** @ 배송비 쿠폰/신규가입 쿠폰/신규가입 쿠폰 (배송비)/컴백회원 쿠폰/컴백회원 쿠폰 (배송비)/이달의 등급 쿠폰/이달의 등급 쿠폰 (배송비)/첫 구매 쿠폰
	** @ 방식2 : 새창
	* 생일자/기념일/회원 등급 조정 쿠폰/회원 등급 조정 쿠폰 (배송비)
	* /promotion/coupon?type=구분자
	**/
	public function coupon()
	{
		$this->_getcoupon();
	}

	//프로모션 > 쿠폰 : 기념일쿠폰 다운페이지1
	public function coupon_anniversary()
	{
		$_GET['type'] = 'anniversary';
		$this->_getcoupon();
	}
	//프로모션 > 쿠폰 : 생일쿠폰 다운페이지2
	public function coupon_birthday()
	{
		$_GET['type'] = 'birthday';
		$this->_getcoupon();
	}
	//프로모션 > 쿠폰 : 신규가입쿠폰 다운3
	public function coupon_member()
	{
		$_GET['type'] = 'member';
		$this->_getcoupon();
	}
	//프로모션 > 쿠폰 : 회원등급쿠폰 다운페이지4
	public function coupon_membergroup()
	{
		$_GET['type'] = 'membergroup';
		$this->_getcoupon();
	}
	//프로모션 > 쿠폰 : 컴백회원쿠폰 다운페이지5
	public function coupon_memberlogin()
	{
		$_GET['type'] = 'memberlogin';
		$this->_getcoupon();
	}
	//프로모션 > 쿠폰 : 이달의 등급쿠폰 다운페이지6
	public function coupon_membermonths()
	{
		$_GET['type'] = 'membermonths';
		$this->_getcoupon();
	}
	// 프로모션 > 쿠폰 : 첫구매쿠폰 다운페이지7
	public function coupon_order()
	{
		$_GET['type'] = 'order';
		$this->_getcoupon();
	}
	//배송비쿠폰 다운페이지8
	public function coupon_shipping()
	{
		$_GET['type'] = 'shipping';
		$this->_getcoupon();
	}

	/** 프로모션 > 쿠폰
	** @
	**/
	function _getcoupon()
	{
		$this->load->model('couponmodel');
		$this->load->model('goodsmodel');
		$this->load->model('categorymodel');
		$this->load->helper('coupon');

		$type = ($_GET['type'] == 'membergroup' ) ? "memberGroup":$_GET['type'];
		if( !in_array($type, $this->couponmodel->coupontotaltype) ) {//이벤트페이지에서 다운가능쿠폰(포인트/직접발급 등 불가)
			$msg = "잘못된 접근입니다.";
			alert($msg);
			$url = "/main/";
			if( $this->fammerceMode  || $this->storefammerceMode ) {
				pageRedirect($url,'','self');
			}else{
				pageRedirect($url,'','parent');
			}
			exit;
		}
		$tpl = 'promotion/coupon_'.$_GET['type'].'.html';

		if( $_GET['layer'] ) {
			if($this->designMode) {
				$this->template->compile_dir	= BASEPATH."../_compile/design";
				$this->template->prefilter		= "addImageAttributesBefore | ".$this->template->prefilter." | addImageAttributes";
			}
			$this->template->define(array('LAYOUT'=>$this->skin."/".$tpl));
			$this->template->print_('LAYOUT');
		}else{
			$this->template->assign(array("template_path"=>$tpl));
			$this->print_layout($this->skin.'/'.$tpl);
		}
	}

	/** 프로모션 > 코드
	** @ 회원 > 포인트교환 > 할인코드 신청하기
	**/
	public function download_member()
	{
		$this->load->model('promotionmodel');
		$this->load->helper('order');
		$this->load->helper('shipping');
		$promotionSeq = (int) $_POST['promotion_seq'];
		if(empty($_POST['promotion_seq'])){
			//잘못된 접근입니다.
			$result = array('result'=>false, 'msg'=>getAlert('mp075'));
			echo json_encode($result);
			exit;
		}

		if(empty($this->userInfo['member_seq'])){
			//잘못된 접근입니다.
			$result = array('result'=>false, 'msg'=>getAlert('mp075'));
			echo json_encode($result);
			exit;
		}

		$now_timestamp = time();
		$today = date('Y-m-d',$now_timestamp);
		$now = date('Y-m-d H:i:s',$now_timestamp);
		$memberSeq = $this->userInfo['member_seq'];
		$this->mdata = $this->membermodel->get_member_data($memberSeq);//회원정보

		// 로그인 체크
		if(!isset( $_GET['return_url'])) $_GET['return_url'] = "/main/index";
		$_SERVER["REQUEST_URI"] = $_GET['return_url'];
		login_check();

		// 할인코드 정보 확인
		$promotioncode = $this->promotionmodel->get_admin_download($memberSeq, $promotionSeq);
		if($promotioncode) {
			//$result = array('result'=>false, 'msg'=>"이미 신청한 할인코드 입니다.");
			//echo json_encode($result);
			//exit;
		}

		$promotionData 	= $this->promotionmodel->get_promotion($promotionSeq);
		//할인코드신청이 실패하였습니다.
		if(!$promotionData) $result = array('result'=>false, 'msg'=>getAlert('mp076'));
		if($promotionData['type'] == 'point' || $promotionData['type'] == 'point_shipping' ) {//point 전환조건체크
			$this->load->model('membermodel');
			if( $this->mdata['point']<1 || $this->mdata['point'] < $promotionData['promotion_point'] ) {//포인트가 작거나 없는 경우
				if( $this->mdata['point']<1 ) {//포인트가 작거나 없는 경우
					//보유포인트가 없습니다.
					$result = array('result'=>false, 'msg'=>getAlert('mp077'));
					echo json_encode($result);
					exit;
				}else{
					//전환포인트 금액이 보유포인트보다 작습니다.
					$result = array('result'=>false, 'msg'=>getAlert('mp078'));
					echo json_encode($result);
					exit;
				}
			}
		}

		if( $promotionData['promotion_type'] == 'random') {//자동생성 >  -> 발급시자동생성 4-4-4-4
			$paramoffline["code_serialnumber"]		= strtoupper(substr(md5( uniqid('') ), 0, 4)).'-'.strtoupper(substr(md5( uniqid('') ), 0, 4)).'-'.strtoupper(substr(md5( uniqid('') ), 0, 4)).'-'.strtoupper(substr(md5( uniqid('') ), 0, 4));//영문+숫자
		}elseif( $promotionData['promotion_type'] == 'file' ) {//수동생성2 > 파일
			$inputsc['whereis'] = ' and down_use = 0 ';
			$promotioninput = $this->promotionmodel->get_promotioncode_input_item($promotionSeq, $inputsc);
			if($promotioninput['code_serialnumber']){
				$paramoffline["code_serialnumber"] = $promotioninput['code_serialnumber'];
			}
		}
		if($paramoffline["code_serialnumber"]) {
			$return = $this->promotionmodel->_members_point_downlod($promotionSeq, $memberSeq, $paramoffline["code_serialnumber"]);
			if( $return ) {
				if( $promotionData['promotion_type'] == 'random') {//자동생성 > 발급시자동생성 4-4-4-4
					$paramoffline["use_count"]				= 1;
					$paramoffline["code_number"]		= mt_rand();//생성
					$paramoffline["promotion_seq"]		= $promotionSeq;
					$paramoffline["regist_date"]			= date("Y-m-d H:i:s");
					$this->db->insert('fm_promotion_code', $paramoffline);
				}elseif( $promotionData['promotion_type'] == 'file' ) {//수동생성2 > 파일
					$this->promotionmodel->set_promotioncode_down_use($paramoffline["code_serialnumber"]);
				}

				if( $this->mdata['email'] ) {
					/**
					$emailparams['email']= $this->mdata['email'];
					$emailparams['title']	= '할인코드가 발급되었습니다.';
					$data['contents']	= '할인코드가 발급되었습니다. <br/>할인코드 : '.$paramoffline["code_serialnumber"].'';
					getSendMail($data);
					**/
					if($promotionData["sale_type"] == 'shipping_free'){
						$promotionsale = "기본배송비 무료";
						if($promotionData["max_percent_shipping_sale"] > 0){
							$promotionsale .= "(최대 " .get_currency_price($promotionData["max_percent_shipping_sale"],2).")";
						}
					}else if($promotionData["sale_type"] =='shipping_won'){
						$promotionsale = get_currency_price($promotionData["won_shipping_sale"],2)." 할인";
					}else if($promotionData["sale_type"] =='won'){
						$promotionsale = get_currency_price($promotionData["won_goods_sale"],2)." 할인";
					}else{
						$promotionsale = number_format($promotionData["percent_goods_sale"])."% 할인";
					}
					if ($promotionData['issue_priod_type'] == 'day') {
						$promotionlimitdate = ($promotionData['after_issue_day']>0) ? '다운로드 후 '.$promotionData['after_issue_day'].'일 간 사용가능':'다운로드 후 오늘까지만 사용가능';
					}else{
						$promotionlimitdate = substr($promotionData['issue_enddate'], 5,2).'월 '. substr($promotionData['issue_enddate'],8,2).'일 까지 사용가능';
					}

					$emailparams['promotioncode']		= $paramoffline["code_serialnumber"];
					$emailparams['promotionsale']		= $promotionsale;
					$emailparams['promotionlimitdate']	= $promotionlimitdate;

					if($this->mdata['rute']!='none'){
						$this->load->helper('email');
						if (valid_email($this->mdata['email']))
						{
							sendMail($this->mdata['email'], 'promotion', $this->mdata['userid'] , $emailparams);
						}
						elseif (valid_email($this->mdata['userid']))
						{
							sendMail($this->mdata['userid'], 'promotion', $this->mdata['userid'] , $emailparams);
						}
					}else{
						sendMail($this->mdata['email'], 'promotion', $this->mdata['userid'] , $emailparams);
					}
				}
				//할인코드 신청되었습니다.<br/>포인트현황 또는 이메일로 할인코드를 확인하실 수 있습니다.
				$result = array('result'=>true, 'msg'=>getAlert('mp079'));
			}else{
				//할인코드 신청이 실패되었습니다.
				$result = array('result'=>false, 'msg'=>getAlert('mp080'));
			}
		}else{
			//할인코드 발급이 실패되었습니다.
			$result = array('result'=>false, 'msg'=>getAlert('mp081'));
		}
		echo json_encode($result);
		exit;
	}

	/** 프로모션 > 코드
	** @ 인증하기
	**/
	public function getPromotionJson()
	{
		$this->load->model('promotionmodel');
		$this->load->helper('order');
		$this->load->helper('shipping');
		$_GET['mode'] = ($_POST['mode'])?$_POST['mode']:$_GET['mode'];
		$session_id = session_id();
		$this->load->model('goodsmodel');
		$this->load->model('categorymodel');
		$this->load->model('cartmodel');
		$this->load->model('brandmodel');

		$adminOrder	= (trim($_POST['adminOrder'])) ? trim($_POST['adminOrder']) : '';
		$member_seq	= $this->userInfo['member_seq'];
		if( $adminOrder == 'admin' || $_GET['mode'] == 'admin' ) {
			$member_seq	= (trim($_POST['member_seq'])) ? trim($_POST['member_seq']) : '';
		}

		//초기화
		$unsetuserdata = array('cart_promotioncodeseq_'.session_id()=>'','cart_promotioncode_'.session_id()=>'');
		$this->session->unset_userdata($unsetuserdata);
		$_SESSION['cart_promotioncodeseq_'.session_id()] = "";
		$_SESSION['cart_promotioncode_'.session_id()] = "";


		$cartpromotioncode = $_POST['cartpromotioncode'];
		if(empty($_POST['cartpromotioncode'])){
			//잘못된 접근입니다.
			$result = array('result'=>false, 'msg'=>getAlert('os027'));
			echo json_encode($result);
			exit;
		}

		if( !empty($member_seq) ){
			$memberSeq = $member_seq;
			$this->mdata = $this->membermodel->get_member_data($memberSeq);//회원정보
		}

		$now_timestamp = time();
		$today = date('Y-m-d',$now_timestamp);
		$now = date('Y-m-d H:i:s',$now_timestamp);

		//$cart_promotioncode = $this->session->userdata('cart_promotioncode_'.session_id());
		if( $cart_promotioncode == $cartpromotioncode){
			//이미 인증된 코드입니다.
			$result = array('result'=>false, 'msg'=>getAlert('os028'));
			echo json_encode($result);
			exit;
		}

		$promotioncodeData 	= $this->promotionmodel->get_promotioncode_serialnumber($cartpromotioncode);
		if( empty($promotioncodeData) ) {
			$promotioncodeData 	= $this->promotionmodel->get_promotioncode_input_serialnumber($cartpromotioncode);
		}

		if($promotioncodeData) {//할인코드 인증1
			$sc['whereis'] = " and promotion_input_serialnumber ='".$cartpromotioncode."'";
			$promotioncode = $this->promotionmodel->get_data($sc);
			$promotioncode = $promotioncode[0];

			if( strstr($promotioncode['type'],'promotion') ){//일반코드인경우

				if ($promotioncode['issue_priod_type'] == 'day') {
					$promotioncode['issue_enddatetitle'] = ($promotioncode['after_issue_day']>0) ? getAlert("gv098", $promotioncode['after_issue_day']):getAlert("gv099");	//'다운로드 후 '.$promotioncode['after_issue_day'].'일 간 사용가능':'다운로드 후 오늘까지만 사용가능';
				}else{
					$promotioncode['issue_enddatetitle'] = getAlert("gv100",array(substr($promotioncode['issue_enddate'], 5,2),substr($promotioncode['issue_enddate'],8,2)));		// substr($promotioncode['issue_enddate'], 5,2).'월 '. substr($promotioncode['issue_enddate'],8,2).'일 까지 사용가능';
				}

				if($promotioncode['issue_type'] == 'all' ){
					$promotioncode['categoryhtml'] = getAlert("os194");	// '전체 사용 가능';
				}else{

					$issuegoods 		= $this->promotionmodel->get_promotion_issuegoods($promotioncode['promotion_seq']);
					$issuebrand 		= $this->promotionmodel->get_promotion_issuebrand($promotioncode['promotion_seq']);
					$issuecategorys	= $this->promotionmodel->get_promotion_issuecategory($promotioncode['promotion_seq']);

					if(($issuegoods)){
						foreach($issuegoods as $key => $tmp) $arrGoodsSeq[] =  $tmp['goods_seq'];
						$goods = $this->goodsmodel->get_goods_list($arrGoodsSeq,'thumbView');
						foreach($issuegoods as $key => $data) {
							$issuegoodsar[$key] = array_merge($issuegoods[$key],$goods[$data['goods_seq']]);
							$issuegoods[$key] = $issuegoodsar[$key]['goods_name'];
							$issuegoodscodes[$key] = $issuegoodsar[$key]['goods_seq'];
						}
						$promotioncode['goodshtml'] = implode(", ",$issuegoods);
						$promotioncode['goodshtmlcode'] = implode(",",$issuegoodscodes);
					}

					if(($issuebrand)) {
						foreach($issuebrand as $key =>$data) {
							$issuebrand[$key] = $this->brandmodel -> get_brand_name($data['brand_code']);
							$issuebrandcodes[$key] = $data['brand_code'];
						}
						$promotioncode['brandhtml'] = implode(", ",$issuebrand);
						$promotioncode['brandhtmlcode'] = implode(",",$issuebrandcodes);
					}

					if($issuecategorys){
						foreach($issuecategorys as $key =>$data) {
							$issuecategorys[$key] = $this->categorymodel -> get_category_name($data['category_code']);
							$issuecategorycodes[$key] = $data['category_code'];
						}
						$promotioncode['categoryhtml'] = implode(", ",$issuecategorys);
						$promotioncode['categoryhtmlcode'] = implode(",",$issuecategorycodes);
					}
				}

				//회원여부 (포인트전환은 회원전용) 인증3
				if( $promotioncode['downloadLimit_member'] == 1 &&  empty($member_seq) ) {
					//$result = array('result'=>false, 'msg'=>"해당 할인코드는 회원전용 할인코드입니다.\n로그인 후 이용해 주세요.");
					$promotioncode['result'] = false;
					//해당 할인코드는 회원전용 할인코드입니다.<br/>로그인 후 이용해 주세요.
					$promotioncode['msg'] = getAlert('os029');
					$result = $promotioncode;
					echo json_encode($result);
					exit;
				}

				//유효기간체크인증4
				if( !($promotioncode['issue_startdate']<=$today && $promotioncode['issue_enddate']>=$today) ) {
					//$result = array('result'=>false, 'msg'=>"해당 할인코드 유효기간이 아닙니다.");
					$promotioncode['result'] = false;
					//해당 할인코드 유효기간이 아닙니다.
					$promotioncode['msg'] = getAlert('os030');
					$result = $promotioncode;
					echo json_encode($result);
					exit;
				}

			}else{//개별코드인경우 -> 발급후 이용가능

				//발급 또는 구매 할인코드 인증2
				$promotioncode_down = $this->promotionmodel->get_download_serialnumber($promotioncodeData['promotion_seq'], $cartpromotioncode);
				if(!$promotioncode_down){
					//$result = array('result'=>false, 'msg'=>"해당 할인코드는 발급후 이용가능한 할인코드입니다.");
					$promotioncode_down['result'] = false;
					//해당 할인코드는 발급후 이용가능한 할인코드입니다.
					$promotioncode_down['msg'] = getAlert('os031');
					$result = $promotioncode_down;
					echo json_encode($result);
					exit;
				}
				$promotioncode = $promotioncode_down;


				if ($promotioncode['issue_priod_type'] == 'day') {
					$promotioncode['issue_enddatetitle'] = ($promotioncode['after_issue_day']>0) ? getAlert("gv098", $promotioncode['after_issue_day']):getAlert("gv099");	//'다운로드 후 '.$promotioncode['after_issue_day'].'일 간 사용가능':'다운로드 후 오늘까지만 사용가능';
				}else{
					$promotioncode['issue_enddatetitle'] = getAlert("gv100",array(substr($promotioncode['issue_enddate'], 5,2),substr($promotioncode['issue_enddate'],8,2)));		// substr($promotioncode['issue_enddate'], 5,2).'월 '. substr($promotioncode['issue_enddate'],8,2).'일 까지 사용가능';
				}

				if($promotioncode['issue_type'] == 'all' ){
					$promotioncode['categoryhtml'] = getAlert("os194");	// '전체 사용 가능';
				}else{

					$issuegoods 		= $this->promotionmodel->get_promotion_download_issuegoods($promotioncode['download_seq']);
					$issuebrand 		= $this->promotionmodel->get_promotion_download_issuebrand($promotioncode['download_seq']);
					$issuecategorys	= $this->promotionmodel->get_promotion_download_issuecategory($promotioncode['download_seq']);

					if(($issuegoods)){
						foreach($issuegoods as $key => $tmp) $arrGoodsSeq[] =  $tmp['goods_seq'];
						$goods = $this->goodsmodel->get_goods_list($arrGoodsSeq,'thumbView');
						foreach($issuegoods as $key => $data) {
							$issuegoodsar[$key] = array_merge($issuegoods[$key],$goods[$data['goods_seq']]);
							$issuegoods[$key] = $issuegoodsar[$key]['goods_name'];
							$issuegoodscodes[$key] = $issuegoodsar[$key]['goods_seq'];
						}
						$promotioncode['goodshtml'] = implode(", ",$issuegoods);
						$promotioncode['goodshtmlcode'] = implode(",",$issuegoodscodes);
					}

					if(($issuebrand)) {
						foreach($issuebrand as $key =>$data) {
							$issuebrand[$key] = $this->brandmodel -> get_brand_name($data['brand_code']);
							$issuebrandcodes[$key] = $data['brand_code'];
						}
						$promotioncode['brandhtml'] = implode(", ",$issuebrand);
						$promotioncode['brandhtmlcode'] = implode(",",$issuebrandcodes);
					}

					if($issuecategorys){
						foreach($issuecategorys as $key =>$data) {
							$issuecategorys[$key] = $this->categorymodel -> get_category_name($data['category_code']);
							$issuecategorycodes[$key] = $data['category_code'];
						}
						$promotioncode['categoryhtml'] = implode(", ",$issuecategorys);
						$promotioncode['categoryhtmlcode'] = implode(",",$issuecategorycodes);
					}
				}


				//1회성코드 인증7
				if($promotioncodeData['use_count'] == 0){
					//$result = array('result'=>false, 'msg'=>"해당 할인코드는 이미 사용한 코드입니다.");

					$promotioncode['result'] = false;
					//해당 할인코드는 이미 사용한 코드입니다.
					$promotioncode['msg'] = getAlert('os032');
					$result = $promotioncode;
					echo json_encode($result);
					exit;
				}

				//회원여부 (포인트전환은 회원전용) 인증3
				if( $promotioncode_down['downloadLimit_member'] == 1 &&  empty($member_seq) ) {
					//$result = array('result'=>false, 'msg'=>"해당 할인코드는 회원전용 할인코드입니다.\n로그인 후 이용해 주세요.");

					$promotioncode['result'] = false;
					//해당 할인코드는 회원전용 할인코드입니다.<br/>로그인 후 이용해 주세요.
					$promotioncode['msg'] = getAlert('os029');
					$result = $promotioncode;
					echo json_encode($result);
					exit;
				}

				//유효기간체크인증4
				if( !($promotioncode['issue_startdate']<=$today && $promotioncode['issue_enddate']>=$today) ) {
					//$result = array('result'=>false, 'msg'=>"해당 할인코드 유효기간이 아닙니다.");

					$promotioncode['result'] = false;
					//해당 할인코드 유효기간이 아닙니다.
					$promotioncode['msg'] = getAlert('os033');
					$result = $promotioncode;
					echo json_encode($result);
					exit;
				}
			}
			//사용제한-상품/카테고리/브랜드 인증5
			$chk_provider	= false;
			$cart			= $this->cartmodel->cart_list($adminOrder);
			foreach($cart['list'] as $key => $data){
				$category = array();
				$tmp = $this->goodsmodel->get_goods_category($data['goods_seq']);
				foreach($tmp as $row) $category[] = $row['category_code'];
				$cart_sale = (int) $data['tot_price'];
				$sum_goods_price += (int) $data['tot_price'];


				$cart_options = $data['cart_options'];
				$cart_suboptions = $data['cart_suboptions'];
				$cart_inputs = $data['cart_inputs'];

				$coupon_goods_sale = 0;
				$member_sale = 0;
				$reserve = 0;
				foreach($cart_options as $k => $cart_option){
					list($price,$cart_option['reserve']) = $this->goodsmodel->get_goods_option_price(
						$data['goods_seq'],
						$cart_option['option1'],
						$cart_option['option2'],
						$cart_option['option3'],
						$cart_option['option4'],
						$cart_option['option5']
					);

					// 재고 체크
					$chk = check_stock_option(
						$data['goods_seq'],
						$cart_option['option1'],
						$cart_option['option2'],
						$cart_option['option3'],
						$cart_option['option4'],
						$cart_option['option5'],
						$cart_option['ea'],
						$cfg['order']
					);
				}
				if($cart_suboptions){
					foreach($cart_suboptions as $k => $cart_suboption){
						// 재고 체크
						$chk = check_stock_suboption(
							$data['goods_seq'],
							$cart_suboption['suboption_title'],
							$cart_suboption['suboption'],
							$cart_suboption['ea'],
							$cfg['order']
						);

						if( $members ){
							/* 회원할인계산 */
							$cart_option['member_sale'] = $this->membermodel->get_member_group($members['group_seq'],$data['goods_seq'],$category,$cart_option['price'],$cart['total']);
							$member_sale += $cart_option['member_sale'] * $cart_option['ea'];
							$cart_options[$k] = $cart_option;
						}
					}
				}

				$cart_sale -= $member_sale;

				$cart['list'][$key]['options'] = $cart_options;
				$cart['list'][$key]['suboptions'] = $cart_suboptions;
				$cart['list'][$key]['inputs'] = $cart_inputs;

				if( $data['shipping_weight_policy'] == "shop" ){
					$goods_weight += $international_shipping['defaultGoodsWeight'];
				}else{
					$goods_weight += $data['goods_weight'];
				}

				if	( ($promotioncode['provider_list'] && strstr($promotioncode['provider_list'], '|'.$data['provider_seq'].'|')) || (!$promotioncode['provider_list'] && $data['provider_seq'] == 1) )	$chk_provider	= true;

				/* 상품 가격 합계  */
				$total_goods_price += (int) $cart_sale;
			}

			// 입점사 체크
			if(!$chk_provider){
				$promotioncode['result']	= false;
				//사용가능한 입점사의 상품이 없습니다.
				$promotioncode['msg']		= getAlert('os034');
				$result						= $promotioncode;
				echo json_encode($result);
				exit;
			}

			if( strstr($promotioncode['type'],'promotion') ){//일반코드인경우
				//사용제한금액1
				if( ($promotioncode['limit_goods_price']>$sum_goods_price) ) {
					//$result = array('result'=>false, 'msg'=>"총구매금액이 ".number_format($promotioncode['limit_goods_price'])."원이상만 사용가능합니다.");

					$promotioncode['result'] = false;
					//총구매금액이 ".number_format($promotioncode['limit_goods_price'])."원이상만 사용가능합니다.
					$promotioncode['msg'] = getAlert('os035',get_currency_price($promotioncode['limit_goods_price'],2));
					$result = $promotioncode;
					echo json_encode($result);
					exit;
				}

			}else{//개별코드
				//사용제한금액1
				if( ($promotioncode['limit_goods_price']>$sum_goods_price) ) {
					//$result = array('result'=>false, 'msg'=>"총구매금액이 ".number_format($promotioncode['limit_goods_price'])."원이상만 사용가능합니다.");

					$promotioncode['result'] = false;
					//총구매금액이 ".number_format($promotioncode['limit_goods_price'])."원이상만 사용가능합니다.
					$promotioncode['msg'] = getAlert('os035',get_currency_price($promotioncode['limit_goods_price'],2));

					$result = $promotioncode;
					echo json_encode($result);
					exit;
				}
			}
			$promotion_able = false;
			foreach($cart['list'] as $key => $data){
				$cart_seq = $data['cart_seq'];

				$category = array();
				$catetmp = $this->goodsmodel->get_goods_category($data['goods_seq']);
				foreach($catetmp as $caterow) {
					if( strlen($caterow['category_code']) > 4) {
						if(strlen($caterow['category_code']) == 16) {
							$category[] = substr($caterow['category_code'], 0, 16);
							$category[] = substr($caterow['category_code'], 0, 12);
							$category[] = substr($caterow['category_code'], 0, 8);
							$category[] = substr($caterow['category_code'], 0, 4);
						}elseif(strlen($caterow['category_code']) == 12) {
							$category[] = substr($caterow['category_code'], 0, 12);
							$category[] = substr($caterow['category_code'], 0, 8);
							$category[] = substr($caterow['category_code'], 0, 4);
						}elseif(strlen($caterow['category_code']) == 8) {
							$category[] = substr($caterow['category_code'], 0, 8);
							$category[] = substr($caterow['category_code'], 0, 4);
						}else{
							$category[] = substr($caterow['category_code'], 0, 4);
						}
					}else{
						$category[] = $caterow['category_code'];
					}
				}

				$brands = $this->goodsmodel->get_goods_brand($data['goods_seq']);
				unset($brand_code);
				if($brands) foreach($brands as $bkey => $branddata){
					if( $branddata['link'] == 1 ){
						$brand_codear= $this->brandmodel->split_brand($branddata['category_code']);
						$brand_code[] = $brand_codear[0];
					}
				}


				unset($promotions);


				$cart['list'][$key]['cart_options'] = array_reverse($cart['list'][$key]['cart_options']);
				$cartopttotal += count($cart['list'][$key]['cart_options']);
				foreach($cart['list'][$key]['cart_options'] as $k1 => $cart_option) {
					$cart_option_seq = $cart_option['cart_option_seq'];
					if( strstr($promotioncode['type'],'promotion') ){//일반코드인경우
						$promotions = $this->promotionmodel->get_able_promotion_list($data['goods_seq'], $category, $brand_code, $sum_goods_price, $cartpromotioncode, $cart_option['price'], $cart_option['ea'] );
					}else{//개별코드
						$promotions = $this->promotionmodel->get_able_download_list($data['goods_seq'], $category, $brand_code, $sum_goods_price, $cartpromotioncode, $cart_option['price'], $cart_option['ea'] );
					}

					if( $promotions) {

						if( !$promotions['provider_list'] && $data['provider_seq'] != 1 ) {//본사상품에만 가능
							$provider_shipping_cost_ck++;continue;
						}elseif($promotions['provider_list'] && !strstr($promotions['provider_list'], '|'.$data['provider_seq'].'|')) {//입점사상품만 가능
							$provider_shipping_cost_ck++;continue;
						}

						if($promotions['duplication_use'] == 1) {//중복할인은 무조건추가
							$this->db->where('cart_option_seq', $cart_option_seq);
							$this->db->update('fm_cart_option', array('promotion_code_seq'=>$promotions['promotion_seq'],'promotion_code_serialnumber'=>$promotions['promotion_input_serialnumber']));
						}else{//중복할인이 아니면서 상품 할인가(판매가)가 최대값인 상품으로 처리함
							//debug_var($cart_option_seq."==>".$max ." < ". $cart_option['price']);
							//if( ($max[$data['goods_seq']] && $max[$data['goods_seq']] < $cart_option['price']) || !$max[$data['goods_seq']]){
							if( ($max && $max < $cart_option['price']) || !$max){
								$ok_cart_option_seq = $cart_option_seq;
								//debug_var(" ok->".$cart_option_seq."==>".$max);
								$max = $cart_option['price'];
								$this->db->where('cart_option_seq', $cart_option_seq);
								$this->db->update('fm_cart_option', array('promotion_code_seq'=>$promotions['promotion_seq'],'promotion_code_serialnumber'=>$promotions['promotion_input_serialnumber']));

								$upsql = "update fm_cart_option set promotion_code_seq=null,promotion_code_serialnumber=null where cart_seq = {$cart_option['cart_seq']} and cart_option_seq!={$cart_option_seq}";
								$this->db->query($upsql);
							}
						}
						$promotion_able = true;
					}else{
						$this->db->where('cart_option_seq', $cart_option_seq);
						$this->db->update('fm_cart_option', array('promotion_code_seq'=>'','promotion_code_serialnumber'=>''));
					}
				}
			}

			if( $cartopttotal == $provider_shipping_cost_ck ){//할인부담금관련 처리
				//할인 가능 상품이 없습니다.
				$result = array('result'=>false, 'msg'=>getAlert('os036'));
			}else if ($promotion_able === false){
				//할인 가능 상품이 없습니다.
				$result = array('result'=>false, 'msg'=>getAlert('os036'));
			}else{
				if($ok_cart_option_seq) {
					//중복할인이 아니면서 할인코드가 최초적용된 주문옵션 제외한 주문상품 초기화
					$upsql = "update fm_cart_option set promotion_code_seq=null,promotion_code_serialnumber=null where cart_option_seq!={$ok_cart_option_seq}";//
					$this->db->query($upsql);
				}

				//사용제한금액
				$newdata = array(
									'cart_promotioncodeseq_'.$session_id  => $promotioncodeData['promotion_seq'],
									'cart_promotioncode_'.$session_id     => $cartpromotioncode
								);
				 $this->session->set_userdata($newdata);


				if( strstr($promotioncode['type'],'promotion') ){//일반코드인경우
					$promotioncode['result'] = true;
					//할인코드가 인증완료되었습니다.
					$promotioncode['msg'] = getAlert('os037');
					$result = $promotioncode;
				}else{//개별코드인경우 -> 발급후 이용가능
					$promotioncode['result'] = true;
					//할인코드가 인증완료되었습니다.
					$promotioncode['msg'] = getAlert('os037');
					$result = $promotioncode;
				}
			}

		}else{
			//할인코드가 인증되지 않았습니다.<br/>정확히 입력해 주세요.
			$result = array('result'=>false, 'msg'=>getAlert('os038'));
		}
		echo json_encode($result);
		exit;
	}

	/** 프로모션 > 코드
	** @  상품상세 최대할인코드
	**/
	public function goods_coupon_max()
	{
		$this->load->model('promotionmodel');
		$this->load->helper('order');
		$this->load->helper('shipping');
		$this->load->model('goodsmodel');
		$this->load->model('brandmodel');

		$goodsSeq = (int) $_GET['no'];
		$goodprice = $_GET['price'];

		$catetmp = $this->goodsmodel->get_goods_category($goodsSeq);
		foreach($catetmp as $caterow) {
			if( strlen($caterow['category_code']) > 4) {
				if(strlen($caterow['category_code']) == 16) {
					$category[] = substr($caterow['category_code'], 0, 16);
					$category[] = substr($caterow['category_code'], 0, 12);
					$category[] = substr($caterow['category_code'], 0, 8);
					$category[] = substr($caterow['category_code'], 0, 4);
				}elseif(strlen($caterow['category_code']) == 12) {
					$category[] = substr($caterow['category_code'], 0, 12);
					$category[] = substr($caterow['category_code'], 0, 8);
					$category[] = substr($caterow['category_code'], 0, 4);
				}elseif(strlen($caterow['category_code']) == 8) {
					$category[] = substr($caterow['category_code'], 0, 8);
					$category[] = substr($caterow['category_code'], 0, 4);
				}else{
					$category[] = substr($caterow['category_code'], 0, 4);
				}
			}else{
				$category[] = $caterow['category_code'];
			}
		}

		$brands = $this->goodsmodel->get_goods_brand($goodsSeq);
		if($brands) foreach($brands as $bkey => $branddata){
			if( $branddata['link'] == 1 ){
				$brand_codear = $this->brandmodel->split_brand($branddata['category_code']);
				$brand_code[] = $brand_codear[0];
			}
		}

		$max = 0;
		$result = $this->promotionmodel->get_able_promotion_max($goodsSeq, $category, $brand_code,0,'',0,1);
		foreach($result as $promotioncode){
				if(strstr($promotioncode['type'],'shipping')){
					if($promotioncode['sale_type']=='shipping_free') {
						$promotioncode['promotioncode_sale'] = $promotioncode['max_percent_shipping_sale'];
					}elseif($parampromotion['sale_type']=='shipping_won') {
						$promotioncode['promotioncode_sale'] = $promotioncode['won_shipping_sale'];
					}

				}elseif( $promotioncode['sale_type'] == 'percent' && $promotioncode['percent_goods_sale'] && $goodprice ){
				if( $this->config_system['cutting_price'] != 'none' ){
					$promotioncode['promotioncode_sale'] = $promotioncode['percent_goods_sale'] * $goodprice / ( $this->config_system['cutting_price'] * 100);
					$promotioncode['promotioncode_sale'] = floor($promotioncode['promotioncode_sale']);
					$promotioncode['promotioncode_sale'] = $promotioncode['promotioncode_sale'] * $this->config_system['cutting_price'];
				}else{
					$promotioncode['promotioncode_sale'] = $promotioncode['percent_goods_sale'] * $goodprice / 100;
					$promotioncode['promotioncode_sale'] = floor($promotioncode['promotioncode_sale']);
				}

				if($promotioncode['max_percent_goods_sale'] < $promotioncode['promotioncode_sale']){
					$promotioncode['promotioncode_sale'] = $promotioncode['max_percent_goods_sale'];
				}

			}else if( $promotioncode['sale_type'] == 'won' && $promotioncode['won_goods_sale'] && $goodprice ){
				$promotioncode['promotioncode_sale'] = $promotioncode['won_goods_sale'];
			}

			if($max < $promotioncode['promotioncode_sale']){
				$result_max = $promotioncode;
				$max = $promotioncode['promotioncode_sale'];
			}
		}
		if(strstr($result_max['type'],'shipping')){
			if($result_max['sale_type']=='shipping_free') {
				$result=array(
						'benifit'=>'기본배송비 무료(최대 '.get_currency_price($result_max['promotioncode_sale'],3).')',
						'codenumber'=>$result_max['promotion_input_serialnumber']
				);
			}elseif($result_max['sale_type']=='shipping_won') {
				$result=array(
						'benifit'=>'배송비 '.get_currency_price($result_max['promotioncode_sale'],3).' 할인',
						'codenumber'=>$result_max['promotion_input_serialnumber']
				);
			}
		}else{
			if($result_max['sale_type'] == 'percent'){
				$result=array(
						'benifit'=>number_format($result_max['percent_goods_sale']).'% 할인',
						'codenumber'=>$result_max['promotion_input_serialnumber']
				);
				if($result_max['max_percent_goods_sale']){
					$result['benifit'] .= "(최대 ".get_currency_price($result_max['max_percent_goods_sale'],3).")";
				}
			}else{
				$result=array(
						'benifit'=>get_currency_price($result_max['promotioncode_sale'],3).' 할인',
						'codenumber'=>$result_max['promotion_input_serialnumber']
				);
			}
		}

		echo json_encode($result);
	}


	/** 프로모션 > 이벤트
	** @ 화면구성정보
	**/
	public function event(){
		if($this->config_system['operation_type'] == 'light'){
			$this->event_light();
		}else{
			$this->event_heavy();
		}
	}

	public function event_heavy(){
		$this->load->model('eventmodel');

 		//$this->skin->file : body_width 체크 @2016-05-30
		$evt_tpl_path = substr($this->template_path(),strpos($this->template_path(),'/')+1);
		$this->layoutconf = layout_config_autoload($this->skin,$evt_tpl_path);
		$this->layoutconfevent = $this->layoutconf[$evt_tpl_path];

		$assign_list['display']	= $this->eventmodel->get_event_set();
		$sc['page']				= ($_GET['page'] > 0) ? (int)$_GET['page'] + 1 : 1;
		$sc['limit']			= $assign_list['display']['count_h'] * $assign_list['display']['count_w'];
		$sc['target']			= ($assign_list['display']['disp_target'] == 'ing' || !$_GET['target']) ? 'ing' : $_GET['target'];

		$data					= $this->eventmodel->get_all_type_event($sc);
		$sc['total_count']		= $data['count'];
		$sc['max_page']			= ceil($sc['total_count'] / $sc['limit']);
		$sc['next_page_count']	= (($sc['page'] + 1) * $sc['limit'] < $sc['total_count']) ? $sc['limit'] : $sc['total_count'] - $sc['page'] * $sc['limit'];
		$sc['next_page_count']	= ($sc['next_page_count'] > 0) ? $sc['next_page_count'] : 0;

		$paging		= array();
		$target		= "";
		for($i = 1; $i <= $sc['max_page']; $i++){
			$paging[$i]	= $i;
		}

		// 이미지호스팅 사용 유무에 따른 디렉토리 경로 재정의
		foreach($data['list'] as $idx => $event_data){
			$data['list'][$idx]['event_banner_src'] = $event_data['event_banner'];
			if($event_data['event_banner'] && substr($event_data['event_banner'],0,4) != 'http'){
				$data['list'][$idx]['event_banner_src'] = '/data/event/' . $event_data['event_banner'];
			}
		}

		$assign_list['record']	= $data['list'];
		$assign_list['sc']		= $sc;

		$paginlay = pagingtagfront($sc['max_page'],1,'/promotion/event?target='.$sc['target']);
		if(empty($paginlay))$paginlay = '<p><a class="on red">1</a><p>';
		$this->template->assign('pagin',$paginlay);

        //  GA4 연동
        if  ($this->ga4_auth_commerce)  { 
            $this->load->library('ga4library');
            $ga4_select = $this->ga4library->select_promotion();
        }

		$tpl		= 'promotion/event.html';
		$this->template->assign($assign_list);
		$this->print_layout($this->skin.'/'.$tpl);
	}

	// 반응형 - 전체 이벤트 리스트 페이지 :: 2018-12-11 lwh
	public function event_light(){

		$this->load->model('eventmodel');
		$this->load->model('pagemanagermodel');
		
		$getParams = $this->input->get();

		$page_config = $this->pagemanagermodel->get_page_config('event', 'responsive');

		$filter_col_tmp = $page_config['filter_col'];
		$page_config['filter_col'] 	= '';
		if ($filter_col_tmp) {
			$i = 0;
			foreach($filter_col_tmp as $k => $filter_col) {
				foreach($filter_col['item'] as $k2 => $v2) {
					$page_config['filter_col'][$i][$k2] = $v2;
				}
				$i++;
			}
		}

		$sc['page'] = ($getParams['page'] > 0) ? (int)$getParams['page'] + 1 : 1;
		$sc['limit'] = 12;
		$sc['target'] = ($getParams['target']) ? $getParams['target']		: null;
		$sc['sc_filter'] = ($getParams['sc_filter']) ? $getParams['sc_filter']	: 'sales';
		$sc['sc_status'] = ($getParams['sc_status']) ? $getParams['sc_status']	: 'ing';
		$sc['event_name'] = ($getParams['event_name']) ? $getParams['event_name']	: '';
		
		$data = $this->eventmodel->get_eventpage_list($sc);

		$sc['total_count'] = $data['count'];
		$sc['max_page'] = ceil($sc['total_count'] / $sc['limit']);
		$sc['next_page_count'] = (($sc['page'] + 1) * $sc['limit'] < $sc['total_count']) ? $sc['limit'] : $sc['total_count'] - $sc['page'] * $sc['limit'];
		$sc['next_page_count'] = ($sc['next_page_count'] > 0) ? $sc['next_page_count'] : 0;

		$paging = array();
		$target = "";
		for($i = 1; $i <= $sc['max_page']; $i++){
			$paging[$i]	= $i;
		}

		// 이미지호스팅 사용 유무에 따른 디렉토리 경로 재정의
		foreach($data['list'] as $idx => $event_data){
			$data['list'][$idx]['event_banner_src'] = $event_data['event_banner'];
			if($event_data['event_banner'] && substr($event_data['event_banner'],0,4) != 'http'){
				$data['list'][$idx]['event_banner_src'] = '/data/event/' . $event_data['event_banner'];
			}
		}

		$assign_list['record'] = $data['list'];
		$assign_list['sc'] = $sc;

		$paginlay = pagingtagfront($sc['max_page'], 1, '/promotion/event?', getLinkFilter('',array_keys($sc)));
		if (empty($paginlay)) {
			$paginlay = '<p><a class="on red">1</a><p>';
		}

		$this->template->assign('pagin',$paginlay);

		// GA4 연동
		if ($this->ga4_auth_commerce) {
						$this->load->library('ga4library');
						$ga4_select = $this->ga4library->select_promotion();
		}

		$tpl		= 'promotion/event_list.html';
		$this->template->assign('sc', $sc);
		$this->template->assign($assign_list);
		$this->template->assign('page_config', $page_config);
		$this->print_layout($this->skin.'/'.$tpl);
	}

	public function event_view()
	{
		$this->load->model('goodslistmodel');
		$this->load->model('eventmodel');
		$this->load->model('categorymodel');
		$this->load->model('brandmodel');
		$this->load->model('locationmodel');
		$this->load->model('myminishopmodel');
		$this->load->library('validation');

		$aGetParams = $this->input->get();

		// validation
		if ($aGetParams) {
			$this->validation->set_data($aGetParams);
			$this->validation->set_rules('event', '일련번호', 'trim|numeric|xss_clean');
			if ($this->validation->exec() === false) {
				show_error($this->validation->error_array['value']);
			}
		}

		$sErrorMessage	= false;
		$aDeliveryCodes	= code_load('searchDelivery');
		$aPerpageCodes	= code_load('searchPerpage');
		foreach($aPerpageCodes as $aData) $aDefaultPerpages[] = $aData['value'];

		$aParams	= $this->input->get();
		$sEvent	= $aParams['event'];
		$aParams['sRequestUri']   = $_SERVER['REQUEST_URI'];

		$eventData = $this->eventmodel->get_event($sEvent);

		$this->promotion_access($eventData);

		/* 이벤트 상품 뷰 카운트 2019_07_23 sms */
		$today_num = 0;
		$today_view = $_COOKIE['event_view'];
		if( $today_view ) $today_view = unserialize($today_view, ['allowed_classes' => false]);
		if( $today_view ) foreach($today_view as $v){
			$today_num++;
			if( count($today_view) > 50 && $today_num == 1 ) continue;
			$data_today_view[] = $v;
		}
		if( ! in_array($sEvent, $today_view) ) {
			$data_today_view[] = $sEvent;
			//페이지뷰 증가
			$this->eventmodel->update_event_view($sEvent);
		}
		if( $data_today_view ) $data_today_view = serialize($data_today_view);
		setcookie('event_view',$data_today_view,time()+86400,'/');

		# 단독 이벤트 리다이렉트 처리
		if($eventData['event_type'] == 'solo' && $eventData['goods_seq']){
			pageRedirect('../goods/view?no='.$eventData['goods_seq']);
			exit;
		}

		$this->goodslistmodel->getFilterConfig('event_view', $eventData);
		$aFilterConfig	= $this->goodslistmodel->aFilterConfig;

		if(!$aParams['page'])		$aParams['page']	= 1;
		if(!$aParams['sorting'])	$aParams['sorting']	= $aFilterConfig['orderby'];
		if(!in_array($aParams['per'], $aDefaultPerpages))	$aParams['per']	= 40;

		/* 카테고리 정보 */
		if( $aParams['category'] ){
			$aCategoryData	= $this->categorymodel->get_category_data(str_replace('c', '', $aParams['category']));
		}
		/* 브랜드 정보 */
		if( $aParams['brand'][0] ){
			$sBrand		= str_replace('b', '', $aParams['brand'][0]);
			foreach($aParams['brand'] as $sDataBrands){
				$sDataBrands				= str_replace('b', '', $sDataBrands);
				$aBrandInfo[$sDataBrands]	= $this->brandmodel->get_brand_data($sDataBrands);
			}
			if( $sBrand ){
				$aBrandData	= $aBrandInfo[$sBrand];
			}
		}
		/* 지역 정보 */
		if( $aParams['location'] ){
			$locationData	= $this->locationmodel->get_location_data(str_replace('l', '', $aParams['location']));
		}
		/* 판매자 정보 */
		if( $aParams['provider'] ){
			$aProvider	= $this->myminishopmodel->getProvider($aParams['provider']);
		}

		// url에 따른 code값 정의
		$aSearch['event']	= $sEvent;
		$aSearch['platform']	= 'P';
		if( $this->mobileMode || $this->_is_mobile_agent ){
			$aSearch['platform']	= 'M';
		}

		if( $sEvent ){ // 조건의 상품번호 추출
			$sGoodsQuery	= $this->goodslistmodel->queryBuild($aSearch);
		}
		$iTotcount	= $this->goodslistmodel->goodsListTotal($sGoodsQuery);

		// 상품에 해당 하는 필터 로딩
		if($aFilterConfig['category'] && !$aParams['category'] ){
			$aCategorys	= $this->goodslistmodel->categorysForFilter($sGoodsQuery);
		}
		if($aFilterConfig['brand']){
			$aBrands	= $this->goodslistmodel->brandsForFilter($sGoodsQuery);
		}
		if($aFilterConfig['seller']){
			$aProviders	= $this->goodslistmodel->providersFilter($sGoodsQuery);
		}
		if($aFilterConfig['color']){
			$aColors	= $this->goodslistmodel->colorsForFilter($sGoodsQuery);
		}

		$aDeliverys	= $this->goodslistmodel->deliverysForFilter($sGoodsQuery, $aDeliveryCodes, $aFilterConfig);

		if( $aFilterConfig['price'] ){
			$aMaxPrice	= $this->goodslistmodel->maxGoodsPriceFilter($sGoodsQuery);
		}

		//메타테그 치환용 정보
		$add_meta_info['event_title']		= $eventData['title'];
		$this->template->assign('add_meta_info',$add_meta_info);

		$this->template->assign('filterNaviCategoryList',	$aNaviCategorys);
		$this->template->assign('filterCategoryList',		$aCategorys);
		$this->template->assign('filterBrandList',			$aBrands);
		$this->template->assign('filterProviderList',		$aProviders);
		$this->template->assign('filterDelvieryCodes',		$aDeliverys);
		$this->template->assign('filterColors',				$aColors);
		$this->template->assign('filterMaxPrice',			$aMaxPrice);
		$this->template->assign('totcount',					$iTotcount);
		$this->template->assign('event',					$sEvent);
		$this->template->assign('params',					$aParams);
		$this->template->assign('eventData',				$eventData);
		$this->template->assign('categoryData',	$aCategoryData);
		$this->template->assign('brandData',	$aBrandData);
		$this->template->assign('aBrandInfo',	$aBrandInfo);
		$this->template->assign('locationData',	$locationData);
		$this->template->assign('aProvider',	$aProvider);
		$this->template->assign('aFilterConfig',		$aFilterConfig);
		$this->print_layout($this->template_path());
		echo("<script>var gl_searchFilterUse = '".$aFilterConfig['searchFilterUse']."';</script>");
    }

	public function gift_view()
	{
		$this->load->model('goodslistmodel');
		$this->load->model('giftmodel');
		$this->load->model('categorymodel');
		$this->load->model('brandmodel');
		$this->load->model('locationmodel');
		$this->load->model('myminishopmodel');
		$this->load->library('validation');

		$sErrorMessage	= false;
		$aDeliveryCodes	= code_load('searchDelivery');
		$aPerpageCodes	= code_load('searchPerpage');
		foreach($aPerpageCodes as $aData) $aDefaultPerpages[] = $aData['value'];

		$aParams	= $this->input->get();
		$aParams['sRequestUri']   = $_SERVER['REQUEST_URI'];
		$sGift		= $aParams['gift'];
		$giftData	= $this->giftmodel->get(array('gift_seq'=>$sGift))->row_array();

		$this->promotion_access($giftData);

		/* 필터 사용 */
		$this->goodslistmodel->getFilterConfig('gift_view', $giftData);
		$aFilterConfig	= $this->goodslistmodel->aFilterConfig;

		if(!$aParams['page'])		$aParams['page']	= 1;
		if(!$aParams['sorting'])	$aParams['sorting']	= $aFilterConfig['orderby'];
		if(!in_array($aParams['per'], $aDefaultPerpages))	$aParams['per']	= 40;

		/* 카테고리 정보 */
		if( $aParams['category'] ){
			$aCategoryData	= $this->categorymodel->get_category_data(str_replace('c', '', $aParams['category']));
		}
		/* 브랜드 정보 */
		if( $aParams['brand'][0] ){
			$sBrand		= str_replace('b', '', $aParams['brand'][0]);
			foreach($aParams['brand'] as $sDataBrands){
				$sDataBrands				= str_replace('b', '', $sDataBrands);
				$aBrandInfo[$sDataBrands]	= $this->brandmodel->get_brand_data($sDataBrands);
			}
			if( $sBrand ){
				$aBrandData	= $aBrandInfo[$sBrand];
			}
		}
		/* 지역 정보 */
		if( $aParams['location'] ){
			$locationData	= $this->locationmodel->get_location_data(str_replace('l', '', $aParams['location']));
		}
		/* 판매자 정보 */
		if( $aParams['provider'] ){
			$aProvider	= $this->myminishopmodel->getProvider($aParams['provider']);
		}

		// url에 따른 code값 정의
		$aSearch['gift']	= $sGift;
		$aSearch['platform']	= 'P';
		if( $this->mobileMode || $this->_is_mobile_agent ){
			$aSearch['platform']	= 'M';
		}

		if( $sGift ){ // 조건의 상품번호 추출
			$sGoodsQuery	= $this->goodslistmodel->queryBuild($aSearch);
		}
		$iTotcount	= $this->goodslistmodel->goodsListTotal($sGoodsQuery);

		// 상품에 해당 하는 필터 로딩
		if($aFilterConfig['category']){
			if($aParams['category']){
				$sCategory	= str_replace('c','',$aParams['category']);
				$aCategorys	= $this->categorymodel->split_category($sCategory);
				$aNaviCategorys = $this->goodslistmodel->categorysForFilter($sGoodsQuery, $aCategorys);
			}else{
				$aCategorys	= $this->goodslistmodel->categorysForFilter($sGoodsQuery);
			}
		}
		if($aFilterConfig['brand']){
			$aBrands	= $this->goodslistmodel->brandsForFilter($sGoodsQuery);
		}
		if($aFilterConfig['seller']){
			$aProviders	= $this->goodslistmodel->providersFilter($sGoodsQuery);
		}
		if($aFilterConfig['color']){
			$aColors	= $this->goodslistmodel->colorsForFilter($sGoodsQuery);
		}

		$aDeliverys	= $this->goodslistmodel->deliverysForFilter($sGoodsQuery, $aDeliveryCodes, $aFilterConfig);

		if( $aFilterConfig['price'] ){
			$aMaxPrice	= $this->goodslistmodel->maxGoodsPriceFilter($sGoodsQuery);
		}

		//메타테그 치환용 정보
		$add_meta_info['event_title'] = $giftData['title'];
		$this->template->assign('add_meta_info', $add_meta_info);

		$this->template->assign('filterNaviCategoryList',	$aNaviCategorys);
		$this->template->assign('filterCategoryList',		$aCategorys);
		$this->template->assign('filterBrandList',			$aBrands);
		$this->template->assign('filterProviderList',		$aProviders);
		$this->template->assign('filterDelvieryCodes',		$aDeliverys);
		$this->template->assign('filterColors',				$aColors);
		$this->template->assign('filterMaxPrice',			$aMaxPrice);
		$this->template->assign('totcount',					$iTotcount);
		$this->template->assign('gift',						$sGift);
		$this->template->assign('params',					$aParams);
		$this->template->assign('gfitData',					$giftData);
		$this->template->assign('categoryData',	$aCategoryData);
		$this->template->assign('brandData',	$aBrandData);
		$this->template->assign('aBrandInfo',	$aBrandInfo);
		$this->template->assign('locationData',	$locationData);
		$this->template->assign('aProvider',	$aProvider);
		$this->template->assign('aFilterConfig',		$aFilterConfig);
		$this->print_layout($this->template_path());
    }

	// 할인/사은품 이벤트 접근제한 공통 사용
	public function promotion_access($eventData) {
		$this->load->helper("javascript");
		$_redirect	= '/';
		if($eventData) {

			if($eventData['gift_seq']) {
				$eventData['start_date_unix'] = strtotime($eventData['start_date']." 00:00:00");
				$eventData['end_date_unix'] = strtotime($eventData['end_date']." 23:59:59");
			} else {
				$eventData['start_date_unix'] = strtotime($eventData['start_date']);
				$eventData['end_date_unix'] = strtotime($eventData['end_date']);
			}

			$currentDate = time();
			// 관리자가 아닌경우
			if(!defined('__ISADMIN__')) {
				if(preg_match('/promotion\/event/', $_SERVER['HTTP_REFERER']))
					$_redirect	= $_SERVER['HTTP_REFERER'];
				// 이벤트 노출 체크
				if($eventData['display']=='n'){
					//공개되지 않은 이벤트입니다.
					pageRedirect($_redirect,getAlert('et050'));
					exit;
				}
				if($eventData['start_date_unix'] > $currentDate) {
					pageRedirect($_redirect,getAlert('et051')); //이벤트 시작 전입니다.
					exit;
				}
				if($eventData['end_date_unix'] < $currentDate) {
					pageRedirect($_redirect,getAlert('et052')); //종료된  이벤트입니다.
					exit;
				}
			}
		}
		else {
			// 이벤트가 없음
			pageRedirect($_redirect,getAlert('et050'));
			exit;
		}
	}

	public function get_more_event_list(){
		header('Content-type:application/json;charset=utf-8');
		$this->load->model('eventmodel');

		//$this->skin->file : body_width 체크 @2016-05-30
		$evt_tpl_path = substr($this->template_path(),strpos($this->template_path(),'/')+1);
		$this->layoutconf = layout_config_autoload($this->skin,$evt_tpl_path);
		$this->layoutconfevent = $this->layoutconf[$evt_tpl_path];

		$event_set		= $this->eventmodel->get_event_set();
		$sc['page']		= ($_GET['page'] > 0) ? (int)$_GET['page'] : 1;
		$sc['limit']	= $event_set['count_h'] * $event_set['count_w'];
		$sc['target']	= ($event_set['disp_target'] == 'ing' || !$_GET['target']) ? 'ing' : $_GET['target'];

		$data			= $this->eventmodel->get_all_type_event($sc);


		$return['page']				= $sc['page'];
		$return['limit']			= $sc['limit'];
		$return['total_count']		= $data['count'];
		$return['max_page']			= ceil($return['total_count'] / $return['limit']);

		$return['next_page_count']	= (($return['page'] + 1) * $return['limit'] < $return['total_count']) ? $return['limit'] : $return['total_count'] - $return['page'] * $return['limit'];
		$return['next_page_count']	= ($return['next_page_count'] > 0) ? $return['next_page_count'] : 0;

		$return['event_config']		= $event_set;

		$event_list		= array();

		foreach((array)$data['list'] as $key => $val){
			// 이미지호스팅 사용 유무에 따른 디렉토리 경로 재정의
			$val['m_event_banner_src'] = $val['m_event_banner'];
			if($val['m_event_banner_src'] && substr($val['m_event_banner'],0,4) != 'http'){
				$val['m_event_banner_src'] = '/data/event/' . $val['m_event_banner'];
			}

			$event_list[]	= $val;
		}

		$return['event_list']	= $event_list;

		echo json_encode($return);

	}

	/** 프로모션 > 코드
	** @ 초기화하기
	**/
	public function getPromotionCartDel()
	{
		$this->load->model('promotionmodel');
		$this->load->helper('order');
		$this->load->helper('shipping');
		$this->load->model('cartmodel');
		$cart = $this->cartmodel->cart_list();
		foreach($cart['list'] as $key => $data){
			foreach($cart['list'][$key]['cart_options'] as $k1 => $cart_option){
				$cart_option_seq = $cart_option['cart_option_seq'];
				$this->db->where('cart_option_seq', $cart_option_seq);
				$this->db->update('fm_cart_option', array('promotion_code_seq'=>'','promotion_code_serialnumber'=>''));
			}
		}
		$unsetuserdata = array('cart_promotioncodeseq_'.session_id()=>'','cart_promotioncode_'.session_id()=>'');
		$this->session->unset_userdata($unsetuserdata);
		$_SESSION['cart_promotioncodeseq_'.session_id()] = "";
		$_SESSION['cart_promotioncode_'.session_id()] = "";
	}


	// 상품상세 > 할인코드 리스트 보기
	public function goods_code_list()
	{
		$this->load->model('promotionmodel');
		$this->load->model('goodsmodel');

		$goods_seq	= $_GET['no'];

		$goods_data		= $this->goodsmodel->get_goods($goods_seq);
		$goods_category	= $this->goodsmodel->get_goods_category($goods_seq);
		foreach($goods_category as  $cate) $category[] = $cate['category_code'];


		$list = $this->promotionmodel->get_able_promotion_max($goods_seq, $category, $brand_code,$goods_data['provider_seq']);
		//$data		= $this->promotionmodel->get_able_promotion_list($goods_seq, $category, $brand_code);//회원정보


		//debuG($list);
		$this->template->define(array('LAYOUT'=>$this->template_path()));
		$this->template->assign('list',$list);
		$this->template->print_('LAYOUT');

	}

	public function promotionpage_codeview(){

		$this->template->print_("tpl");
	}

    //  GA4 연동
    public function now_event()
	{
		$this->load->model('eventmodel');

		$this->load->library('validation');

		$param = $this->input->post();

		// 반응형 스킨인 경우 이벤트 고유 번호로 조회
		$event_seq = (int) $param['no'];

		// 전용 스킨인 경우 파일명으로 조회
		$tpl = $param['tpl_path'];  

		$event_type = $param['event_type'];

		$event = [];

		$this->validation->set_data($param);
		$this->validation->set_rules('no', '일련번호', 'trim|numeric|xss_clean');
		$this->validation->set_rules('tpl_path', '경로', 'trim|string|xss_clean');
		$this->validation->set_rules('event_type', '대량게시판', 'trim|in_list[event,gift]|xss_clean');

		if ($this->validation->exec() === false) {
			show_error($this->validation->error_array['value'], 422);
		}
		
		if ($tpl) {
			// 사은품 이벤트일 경우
			if ($this->eventmodel->is_gift_template_file($tpl)) { 
				$this->db->where('tpl_path', $tpl);
				$this->db->from("fm_".$event_type);
				$this->db->select("*, if(current_date() between start_date and end_date,'진행 중',if(end_date < current_date(),'종료','시작 전')) as status");
			} else { // 할인/기획 이벤트일 경우
				$this->db->where('tpl_path', $tpl);
				$this->db->from("fm_".$event_type);
				$this->db->select("*, if(CURRENT_TIMESTAMP() between start_date and end_date,'진행 중',if(end_date < CURRENT_TIMESTAMP(),'종료','시작 전')) as status");
			};
			
			$query = $this->db->get();
			$event_info = $query->result_array();
			$event_info = $event_info[0];
		}

		if ($event_seq) {
			if ($event_type == 'gift') {
				$event_info =   $this->eventmodel->get_gift($event_seq);
			} else {
				$event_info =   $this->eventmodel->get_event($event_seq);
			}
		}

		if ($event_type == 'event') {
			$event['event_type'] = $event_info['event_type']; // 단독이벤트 아닐때   
		}

		$event = [
			'event_seq' => $event_info[$event_type.'_seq'],
			'title' => $event_info['title'],
			'tpl_path' => $event_info['tpl_path'],
		];

		echo json_encode($event,JSON_UNESCAPED_UNICODE);
        
    }
}

/* End of file promotion.php */
/* Location: ./app/controllers/promotion.php */