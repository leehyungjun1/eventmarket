<?php
class naverpaymodel extends CI_Model {

	var $referer_domain = '';
	var $arr_step		= '';
	var $cfg_order		= '';

	public function __construct(){

		$this->load->model("ordermodel");
		$this->load->model("providermodel");
		$this->load->model("goodsmodel");
		$this->load->model("categorymodel");
		$this->load->library('naverpaylib');

		$this->arr_step		= config_load('step');
		$this->cfg_order	= config_load('order');

		//$this->referer_domain = "order.pay.naver.com";
	}

	public function cfg_naverpay(){
		return config_load("navercheckout");
	}

	function getLocaltimeToGMT($time=''){

		if(!$time){ $time = date("Y-m-d H:i:s"); }
	   $timestamp	= date("Y-m-d\TH:i:s",strtotime("-9hour",strtotime($time)));
	   $microtime	= substr(microtime(),2,3);

	   return $timestamp.".".$microtime."Z";

	}

	function getGMTToLocaltime($time){
		//$timestamp	= '2015-06-17T01:34:54+0000';
		$tmp1		= substr(str_replace("T"," ",$time),0,19);
		$tmp2		= explode(" ",$tmp1);
		$time		= date("Y-m-d H:i:s",strtotime("+9 hour",strtotime($tmp1)));
		return $time;
	}

	# 상품주문 : Npay 상품주문 정보
	public function partner_order_detail($opt_type='option', $options, $arr, $market='npay'){

		$goods_seq			= $arr['goods_seq'];
		$provider_seq		= $arr['provider_seq'];
		$mode				= $arr['mode'];
		$session_id			= $arr['session_id'];
		$mktime				= $arr['mktime'];
		$shipping_method	= $arr['shipping_method'];
		$shipping_packageId	= $arr['shipping_packageId'];
		$shipping_set		= $arr['shipping_set'];
		$tax				= $arr['tax']; // 과세여부 추가 :: 2018-03-14 lkh

		if($shipping_set){
			$shipping_set = serialize($shipping_set);
		}

		if($opt_type == "option"){
			//$this->db->query("delete from fm_partner_order_detail where session_tmp like '".$session_id."%' and session_tmp != ? and npay_confirm_cnt = 0",array($session_id."_".$mktime));
		}

		// 필수옵션 할인 : 기본할인, 이벤트할인, 복수구매할인, 멤버할인, 유입경로할인, like할인, 모바일할인
		// 추가옵션 할인 : 기본할인, 멤버할인
		foreach($options as $option){

			if($opt_type == "option"){
				$option_seq = $option['option_seq'];
				if ($option['event_sale_target'] == "consumer_price" && $option['consumer_price'] > 0){
					$price		= $option['consumer_price'];
				}else{
					$price		= $option['org_price'];
				}
			}else{
				$option_seq = $option['suboption_seq'];
				$price		= $option['price'];
			}

			if($option['type'] == "single"){
				$option_single = "y";
			}else{
				$option_single = "n";
			}

			$input_option = array();
			if($option['inputs']){
				foreach($option['inputs'] as $inp){
					$input_data = array();
					$input_data['type']		= $inp['type'];
					$input_data['title']	= $inp['input_title'];
					$input_data['value']	= $inp['input_value'];
					$input_option[] = $input_data;
				}
			}
			$sale_price		= $option['event_sale'] + $option['multi_sale']
									+ $option['member_sale']
									+ ($option['like_sale']/$option['ea'])
									+ ($option['referer_sale']/$option['ea'])
									+ ($option['mobile_sale']/$option['ea']);
			$discount_price = $price - $sale_price;

			$insert_params = array();
			$insert_params['partner_id']		= $market;
			$insert_params['member_gubun']		= ($this->userInfo['member_seq'])? 'member':'nonmember';
			$insert_params['session_tmp']		= $session_id."_".$mktime;
			$insert_params['cart_seq']			= $option['cart_seq'];
			$insert_params['cart_option_seq']	= $option['cart_option_seq'];
			$insert_params['provider_seq']		= $provider_seq;
			$insert_params['goods_seq']			= $goods_seq;
			$insert_params['option_seq']		= $option_seq;
			$insert_params['option_type']		= $opt_type;
			$insert_params['option_single']		= $option_single;
			$insert_params['goods_code']		= $option['goods_code'];
			$insert_params['optioncode1']		= ($option['optioncode1'])? $option['optioncode1']:$option['optioncode'];
			$insert_params['optioncode2']		= $option['optioncode2'];
			$insert_params['optioncode3']		= $option['optioncode3'];
			$insert_params['optioncode4']		= $option['optioncode4'];
			$insert_params['optioncode5']		= $option['optioncode5'];
			$insert_params['ea']				= $option['ea'];
			$insert_params['distribution']		= $mode;
			$insert_params['price']				= $price;
			$insert_params['discount_price']	= $discount_price;	//할인적용액(개당)
			$insert_params['consumer_price']	= $option['consumer_price'];
			$insert_params['supply_price']		= $option['supply_price'];
			$insert_params['goods_price']		= $option['goods_price'];	//할인미적용 판매가(ori_price / ori_price 변질)
			$insert_params['original_price']	= $option['original_price'];//할인미적용 정가
			$insert_params['sale_price']		= $option['sale_price'];	//기본할인(개당)
			$insert_params['basic_sale']		= $option['basic_sale'];
			$insert_params['member_sale']		= $option['member_sale'];
			$insert_params['member_seq']		= $this->userInfo['member_seq'];
			if($opt_type == "option"){
				$insert_params['event_sale']		= $option['event_sale'];
				$insert_params['multi_sale']		= $option['multi_sale'];
				$insert_params['like_sale']			= $option['like_sale'];
				$insert_params['referer_sale']		= $option['referer_sale'];
				$insert_params['mobile_sale']		= $option['mobile_sale'];
				$insert_params['referer_sale_unit']	= $option['referer_sale_unit'];
				$insert_params['mobile_sale_unit']	= $option['mobile_sale_unit'];
				$insert_params['event_sale_target']	= $option['event_sale_target'];
				$insert_params['event_seq']			= $option['event']['event_seq'];
				$insert_params['input_option']		= serialize($input_option);

				// [퍼스트몰 라이브] broadcast_seq 추가 :: 2020-11-12 hyem
				// bs_type live 일때는 방송 중인지 체크 vod는 상관없음
				if($option['bs_type'] && $option['bs_seq']) {
					if($option['bs_type'] == 'live') {
						$this->load->model("broadcastmodel");
						$sch = $this->broadcastmodel->getSchEach($option['bs_seq']);
						if($sch['status'] != 'live') {
							unset($option['bs_seq'],$option['bs_type']);
						}
					}
					if($option['bs_type'] && $option['bs_seq']) {
						$insert_params['bs_seq'] = $option['bs_seq'];
						$insert_params['bs_type'] = $option['bs_type'];
					}
				}
			}

			//정산수수료(할인가(이벤트할인,복수구매할인적용)  * 수수료)
			$price_basic_sale = $price - ($option['event_sale']+$option['multi_sale']);

			$insert_params['commission_type']	= $option['commission_type']; // 수수료방식 :: 2018-07-16 lkh
			$insert_params['commission_rate']	= $option['commission_rate'];
			$insert_params['commission_price']	= $option['commission_price'];
			$insert_params['shipping_method']	= $shipping_method;
			$insert_params['shipping_group']	= $shipping_packageId;
			$insert_params['shipping_store_seq']= $option['shipping_store_seq'];
			$insert_params['reserve_date']		= $option['reserve_date'];
			$insert_params['referer_domain']	= $option['referer_domain'];
			$insert_params['referer']			= $option['referer'];
			$insert_params['shipping_cfg']		= $shipping_set;
			$insert_params['tax']				= $tax;  // 과세여부 추가 :: 2018-03-14 lkh
			$insert_params['regist_date']		= date("Y-m-d H:i:s",mktime());
			$insert_params['shipping_charge']	= ($option['shipping_charge']) ? $option['shipping_charge'] : 0; // 배송비수수료 :: 2018-07-16 lkh
			$insert_params['return_shipping_charge'] = ($option['return_shipping_charge']) ? $option['return_shipping_charge'] : 0; // 반품배송비수수료 :: 2018-07-16 lkh

			$insert_params['salescost_provider']			= $option['salescost_provider'];
			$insert_params['salescost_provider_coupon']		= $option['salescost_provider_coupon'];
			$insert_params['salescost_provider_referer']	= $option['salescost_provider_referer'];

			$insert_params['marketplace']		= $option['marketplace'];		// 입점마케팅EP데이터 :: 2020-06-17 hyem

			$this->db->insert("fm_partner_order_detail",$insert_params);

		}

	}


	# 상품주문 : 선택된 옵션의 코드정보
	public function select_option_code($bind){

		if($where) $wheres = " and ".implode(" and ",$where);

		$sql = "select optioncode1,optioncode2,optioncode3,optioncode4,optioncode5 from fm_goods_option where goods_seq=? and option_seq=?";
		$que = $this->db->query($sql,$bind);
		$res = $que->result_array();

		return $res[0];

	}

	# 상품주문 : 선택된 서브옵션의 코드정보
	public function select_suboption_code($bind){

		if($where) $wheres = " and ".implode(" and ",$where);

		$sql = "select suboption_code from fm_goods_suboption where goods_seq=? and suboption_seq=?";
		$que = $this->db->query($sql,$bind);
		$res = $que->result_array();

		return $res[0];

	}

	# 상품주문 : 옵션 기본가
	public function option_default_price($goods_seq){

		$sql = "select price from fm_goods_option where goods_seq='".$goods_seq."'  and default_option='y'";
		$que = $this->db->query($sql);
		$res = $que->result_array();

		return $res[0]['price'];

	}

	# 문의글 답변등록
	public function set_qnswer_customer_inquiry(){

		if($_POST['npay_answer_id']){
			$actiontype = "UPDATE";
		}else{
			$actiontype = "INSERT";
		}

		$re_contents = strip_tags($_POST['re_contents'] );
		$re_contents = str_replace("&nbsp;", " ", $re_contents);
		$params		= array(
						"InquiryID" => $_POST['npay_inquiry_id'],
						"AnswerContent" => $re_contents,
						"AnswerContentID" => '',
						"ActionType" => $actiontype,
					);
		$result = $this->naverpaylib->qnswer_customer_inquiry($params);

		if($result['ResponseType'] == "SUCCESS"){
			$sql = "update fm_boarddata set npay_answer_send='Y' where boardid='naverpay_qna' and npay_inquiry_id='".$_POST['npay_inquiry_id']."'";
			$this->db->query($sql);
			$return = true;
		}else{
			$return = false;
		}

		return $return;
	}

	# 문의글 등록
	public function set_customer_inquiry($loop){

		$this->load->model('Boardmodel');
		$this->load->model('Boardindex');
		$this->load->helper('board');
		$this->load->model('Boardmanager');

		$boardid = "naverpay_qna";
		$sc['whereis']	= ' and id= "'.$boardid.'" ';
		$sc['select']		= ' * ';
		$manager = $this->Boardmanager->managerdataidck($sc);//게시판정보

		$arr_inquiryid = array();
		foreach($loop as $list){

			$sql	= "select count(seq) cnt from fm_boarddata where npay_inquiry_id=?";
			$query	= $this->db->query($sql,array($list['InquiryID']));
			$res	= $query->row_array();

			if(!$res['cnt']){

				//$userid = $scl->decrypt($secret,$list['CustomerID']);
				$userid = $list['CustomerID'];

				$params = array();
				$params['boardid']			=  $boardid;
				$params['notice']			=  '0';//공지
				$params['onlynotice']		=	0;//공지영역만 노출여부

				if( $manager['secret_use'] == "A" ) {//무조건비밀글
					$params['hidden']		= 1;//비밀글
				}else{
					$params['hidden']		= '0';//비밀글
				}

				$params['subject']		=  $list['Title'];
				$params['editor']		=  0;//모바일
				$params['name']			=  $list['CustomerName']."(naverpay)";
				$params['category']		=  $list['Category'];
				$params['contents']		=  $list['InquiryContent'];

				if($list['IsAnswered'] == "true"){
					$params['npay_answer_id']	=  $list['AnswerContentID'];
					$params['re_subject']		=  substr($list['AnswerContent'],10,30);
					$params['re_contents']		=  $list['AnswerContent'];
					$params['re_date']			=  "";
				}

				$params['pw']			= '';
				$params['email']		= '';
				$params['tel1']			= '';
				$params['tel2']			= '';

				$params['rsms']			= 'N';//수신여부
				$params['remail']		= 'N';//수신여부

				//상품문의/후기
				$goods = $this->goodsmodel->get_goods($list['ProductID']);
				$params['goods_seq']	= $list['ProductID'];
				$params['provider_seq'] = $goods['provider_seq'];

				$params['goods_cont']	= '';

				$params['mseq']			= '';
				$params['mid']			= $userid;

				$mindata	= $this->Boardmodel->get_data(array('whereis' => ' ','select' => ' min(gid) as mingid '));
				$parentgid	= $mindata['mingid'] ? $mindata['mingid']-1 : 100000000.00;
				$params['parent']		= 0;
				$params['gid']			= $parentgid;
				$params['depth']		= 0;

				$params['r_date']		= $this->getGMTToLocaltime($list['InquiryDateTime']);
				$params['m_date']		= date("Y-m-d H:i:s");
				$params["ip"]			= $this->input->ip_address();
				$params["agent"]		= $_SERVER['HTTP_USER_AGENT'];
				$params["npay_inquiry_id"]		= $list['InquiryID'];
				$params["npay_product_order_id"]= $list['ProductOrderID'];

				$result = $this->Boardmodel->data_write($params);
				if($result){
					$arr_inquiryid[$list['InquiryID']]['result']	= "success";
					$arr_inquiryid[$list['InquiryID']]['message']	= "성공";

					$idxparams = array();
					$idxparams['onlynotice']			=	0;//공지영역만 노출여부
					$idxparams['onlynotice_sdate']		= '';//공지노출 시작일
					$idxparams['onlynotice_edate']		= '';//공지노출 완료일

					$idxparams['gid']		= $params['gid'];//고유번호
					$idxparams['boardid']	= $params['boardid'];//id
					$this->Boardindex->idx_write($idxparams);

					$upmanagerparams = array('totalnum' => $manager['totalnum']+1 );
					$this->Boardmanager->manager_item_save($upmanagerparams,$boardid);

				}else{
					$arr_inquiryid[$list['InquiryID']]['result']	= "fail";
					$arr_inquiryid[$list['InquiryID']]['message']	= $this->db->last_query();
				}
				$arr_inquiryid[$list['InquiryID']]['message'] = $message;

			}else{
				$arr_inquiryid[$list['InquiryID']]['result']	= "success";
				$arr_inquiryid[$list['InquiryID']]['message']	= "성공";
			}
		}

		return $arr_inquiryid;

	}

	# 주문수집 : 상품리뷰 등록
	public function set_goods_review($data,$timestamp){

		$this->load->model('Goodsreview','Boardmodel');
		$this->load->model('Boardindex');
		$this->load->helper('board');
		$this->load->model('Boardmanager');

		$boardid = "goods_review";
		$sc['whereis']	= ' and id= "'.$boardid.'" ';
		$sc['select']		= ' * ';
		$manager = $this->Boardmanager->managerdataidck($sc);//게시판정보

		$return = array();
		foreach($data as $type => $data_loop){
			$type = strtolower($type);
			//$secret = $this->naverpaylib->generateKey($timestamp[$type]);
			if($type != "general" && $type != "premium") continue;

			foreach($data_loop as $list){

				$sql	= "select count(seq) cnt from fm_goods_review where npay_reviewid=? and npay_reviewtype=?";
				$query	= $this->db->query($sql,array($list['PurchaseReviewId'],$type));
				$res	= $query->row_array();

				if(!$res['cnt']){

					if($list['timestamp'] != $old_timestamp){
						$secret			= $this->naverpaylib->generateKey($list['timestamp']);
						$old_timestamp	= $list['timestamp'];
					}

					$userid = $this->naverpaylib->scl->decrypt($secret,$list['WriterId']);

					$params = array();
					$params['boardid']			=  $boardid;
					$params['notice']			=  '0';//공지
					$params['onlynotice']		=	0;//공지영역만 노출여부

					if( $manager['secret_use'] == "A" ) {//무조건비밀글
						$params['hidden']		= 1;//비밀글
					}else{
						$params['hidden']		= '0';//비밀글
					}

					$params['subject']		=  $list['Title'];
					$params['editor']		=  0;//모바일
					$params['name']			=  'naverpay';
					$params['category']		=  '';
					$params['contents']		=  ($type == "premium")? stripslashes($list['Content']):$list['Title'];

					$params['pw']			= '';
					$params['email']		= '';
					$params['tel1']			= '';
					$params['tel2']			= '';

					$params['rsms']			= 'N';//수신여부
					$params['remail']		= 'N';//수신여부

					//평가점수
					if($list['PurchaseReviewScore_new']) {
						$score		= $list['PurchaseReviewScore_new'];
						$score_avg	= (int)$score * 20;
						$params['reviewcategory'] = $params['score'] = $score;
						$params['score_avg']	=  $score_avg;
					}else{
						if($list['PurchaseReviewScore']) {
							if($type == "premium"){
								switch($list['PurchaseReviewScore']){
									case "13": $score = 5; $score_avg = 100; break;		//적극추천
									case "12": $score = 4; $score_avg = 70; break;		//추천
									case "11": $score = 3; $score_avg = 50; break;		//보통
									case "10": $score = 1; $score_avg = 0; break;		//추천안함
								}
							}else{
								switch($list['PurchaseReviewScore']){
									case "2": $score = 5; $score_avg = 100; break;		//만족
									case "1": $score = 3; $score_avg = 50; break;		//보통
									case "0": $score = 1; $score_avg = 0; break;		//불만족
								}
							}
							$params['reviewcategory'] = $params['score']		= $score;
							$params['score_avg']	=  $score_avg;
						}
					}

					//상품문의/후기
					$goods = $this->goodsmodel->get_goods($list['ProductID']);
					$params['goods_seq']	= $list['ProductID'];
					$params['provider_seq'] = $goods['provider_seq'];

					$params['goods_cont']	= '';

					$params['mseq']			= '';
					$params['mid']			= $userid;

					$mindata	= $this->Boardmodel->get_data(array('whereis' => ' ','select' => ' min(gid) as mingid '));
					$parentgid	= $mindata['mingid'] ? $mindata['mingid']-1 : 100000000.00;
					$params['parent']		= 0;
					$params['gid']			= $parentgid;
					$params['depth']		= 0;

					$params['r_date']		= date("Y-m-d H:i:s");
					$params['m_date']		= date("Y-m-d H:i:s");
					$params["ip"]			= $this->input->ip_address();
					$params["agent"]		= $_SERVER['HTTP_USER_AGENT'];
					$params["npay_reviewid"]= $list['PurchaseReviewId'];
					$params["npay_reviewtype"]= $type;
					$params["npay_product_order_id"]= $list['ProductOrderID'];

					$result = $this->Boardmodel->data_write($params);
					if($result){
						goods_review_count(array("goods_seq"=>$list['ProductID']), $result, 'minus');
						$return[$type][] = $list['PurchaseReviewId'];

						$idxparams = array();
						$idxparams['onlynotice']			=	0;//공지영역만 노출여부
						$idxparams['onlynotice_sdate']		= '';//공지노출 시작일
						$idxparams['onlynotice_edate']		= '';//공지노출 완료일

						$idxparams['gid']		= $params['gid'];//고유번호
						$idxparams['boardid']	= $params['boardid'];//id
						$this->Boardindex->idx_write($idxparams);

						$upmanagerparams = array('totalnum' => $manager['totalnum']+1 );
						$this->Boardmanager->manager_item_save($upmanagerparams,$boardid);
					}

				}else{
					$return[$type][] = $list['PurchaseReviewId'];
				}
			}
		}

		return $return;

	}

	# 주문수집 : Npay 주문건 최종 수정일 업데이트
	public function last_date_update($npay_product_order_id,$opt_type,$timestamp){

		if($npay_product_order_id && $timestamp){
			if(strtoupper($opt_type) == "OPT"){
				$tb = "fm_order_item_option";
			}else{
				$tb = "fm_order_item_suboption";
			}
			$this->db->query("update ".$tb." set npay_last_change_date=? where npay_product_order_id=?",array($timestamp,$npay_product_order_id));
		}
	}

	# 주문수집 : npay 주문상태 => shop 기준으로 변환
	# A.1.3 상품 주문 상태 코드
	public function get_order_option_step($ProductOrderStatus,$claimArr=array()){

		$npay_step			= '';
		$npay_return_status	= "";
		$npay_return_flag	= "";
		$npay_refund_status	= "";
		$npay_refund_flag	= "";
		$buyConfirm			= "n";
		$deposit_yn			= "n";
		$npay_return_type	= "";
		$npay_refund_type	= "";

		$claimtype			= $claimArr['claimtype'];
		$claimstatus		= $claimArr['claimstatus'];
		$holdback_status	= $claimArr['holdback_status'];
		$holdback_reason	= $claimArr['holdback_reason'];

		//ADMIN_CANCEL ,  ADMIN_CANCEL_DONE
		switch($ProductOrderStatus){
			case 'payment_waiting':			$npay_step = 15; $deposit_yn = "n";	break;	//입금대기
			case 'payed':					$npay_step = 25; $deposit_yn = "y";	break;	//결제완료
			case 'delivering':				$npay_step = 65; $deposit_yn = "y";	break;	//출고완료
			case 'delivered':				$npay_step = 75; $deposit_yn = "y";	break;	//배송완료
			case 'purchase_decided':		$npay_step = 75; $deposit_yn = "y"; $buyConfirm = 'y'; break;	//구매확정
			case 'exchange':				$npay_step = 55; $deposit_yn = "y"; break;	//교환
			case 'exchanged':				$npay_step = 75; $deposit_yn = "y"; break;	//교환
			case 'cancele':					$npay_step = 85; $deposit_yn = "y"; break;	//취소완료
			case 'canceled':				$npay_step = 85; $deposit_yn = "y"; break;	//취소완료
			case 'return':					$npay_step = 55; $deposit_yn = "y"; break;	//반품완료
			case 'returned':				$npay_step = 55; $deposit_yn = "y"; break;	//반품완료
			case 'canceled_by_nopayment':	$npay_step = 95; $deposit_yn = "n"; break;	//미입금취소
		}
		if($npay_step == 95){

			$npay_refund_status = "";	//미입금 취소=>주문무효

		}else{

			switch($claimtype){
				case "return":
					$npay_return_type	= "return";
					$npay_refund_type	= "return";
					$npay_return_flag	= $claimstatus;
					$npay_refund_flag	= $claimstatus;
					switch($claimstatus){
						case 'return_request':	//반품접수	-> 반품접수/환불접수
							$npay_return_status = "request";
							$npay_refund_status = "request";
							$npay_return_flag	= '';
							$npay_refund_flag	= '';
						break;
						case 'collecting':		//수거처리중-> 반품접수/환불접수
							$npay_return_status = "request";
							$npay_refund_status = "request";
						break;
						case 'collect_done':	//수거완료	-> 반품완료/환불접수
							$npay_return_status = "request";
							$npay_refund_status = "request";
						break;
						case 'return_done':		//반품완료	-> 반품완료/환불완료
							$npay_return_status = "complete";
							$npay_refund_status = "complete";
						break;
						case 'return_reject':	//반품철회 -> 삭제
							$npay_return_status = "reject";
						break;
					}
					## 보류중인 경우 보류사유코드를 flag 값으로 가진다.
					if($holdback_status == "holdback"){
						$npay_return_flag = $holdback_reason;
						$npay_refund_flag = $holdback_reason;
					}
				break;

				case "exchange":

					$npay_return_type	= "exchange";
					$npay_return_flag	= $claimstatus;
					$npay_refund_type	= "";
					switch($claimstatus){
						case 'exchange_request':			//교환요청		-> 반품접수
							$npay_return_status = "request";
							$npay_return_flag	= '';
						break;
						case 'collecting':					//수거처리중	-> 반품접수
							$npay_return_status = "request";
						break;
						case 'collect_done':				//수거완료	-> 반품완료(재주문 생성을 위한 반품완료.실제상태는 반품접수처리)
							$npay_return_status = "complete";
						break;
						case 'exchange_redelivering':		//교환재배송중	-> 반품완료/재주문건 출고완료
							$npay_return_status = "complete";
						break;
						case 'exchange_done':				//교환완료		-> 반품완료/재주문건 재배송완료
							$npay_return_status = "complete";
						break;
					}
					## 보류중인 경우 보류사유코드를 flag 값으로 가진다.
					if($holdback_status == "holdback"){
						$npay_return_flag = $holdback_reason;
					}
				break;

				case "cancel":

					// 자동 무효처리가 아닌 고객이 무효처리했을 경우 주문접수건 미입금 주문 무효 처리
					if($npay_step == 15) {

						$npay_step			= 95;
						$npay_refund_status = "";	//미입금 취소=>주문무효

					} else {

						switch($claimstatus){
							case 'cancel_request':	//취소요청		-> 환불접수(승인필요)
								$npay_refund_status = "request";
								$npay_refund_flag	= 'refund_request';
								$npay_step			= 85;
								$npay_return_type	= "refund";
								$npay_refund_type	= "cancel_payment";
							break;
							case 'canceling':		//취소처리중	-> 환불처리중(승인불필요)
								$npay_refund_status = "request";
								$npay_step			= 85;
								$npay_return_type	= "refund";
								$npay_refund_type	= "cancel_payment";
							break;
							case 'cancel_done':		//취소처리완료	-> 환불완료
								$npay_refund_status = "complete";
								$npay_refund_flag	= $claimstatus;
								$npay_step			= 85;
								$npay_return_type	= "refund";
								$npay_refund_type	= "cancel_payment";
							break;
							case 'cancel_reject':	//취소철회		-> 환불삭제
								$npay_refund_status = "";
								$npay_return_type	= "";
							break;
						}
						## 보류중인 경우 보류사유코드를 flag 값으로 가진다.
						if($holdback_status == "holdback"){
							$npay_refund_flag = $holdback_reason;
						}

					}

				break;

				case "admin_cancel":

					$npay_step = 85;
					$npay_return_type	= "refund";
					$npay_refund_type	= "cancel_payment";
					switch($claimstatus){
						case 'admin_canceling':			//취소요청		-> 환불접수(승인필요)
							$npay_refund_status = "ing";
							$npay_refund_flag	= 'refund_ing';
						break;
						case 'admin_cancel_done':		//취소처리중	-> 환불처리중(승인불필요)
							$npay_refund_status = "complete";
						break;
					}

				break;
			}
		}

		return array('step'				=>$npay_step,
					'deposit_yn'		=>$deposit_yn,
					'buyConfirm'		=>$buyConfirm,
					'npay_claim_status'	=>$claimstatus,
					'return_type'		=>$npay_return_type,
					'refund_type'		=>$npay_refund_type,
					'return_status'		=>$npay_return_status,
					'refund_status'		=>$npay_refund_status,
					'return_flag'		=>$npay_return_flag,
					'refund_flag'		=>$npay_refund_flag
					);
	}

	# 주문수집 : 출고처리(중계서버로 전송-사용안함)
	public function request_send_export($export_data){

		$this->load->model("ordermodel");
		$npay_use	= npay_useck();
		$npayconfig = config_load("navercheckout");

		if($npay_use){

			if(!$export_data['items']['npay_product_order_id']){
				return array("result"=>"INSERT_ERROR","message"=>"네이버페이 상품주문번호가 없습니다.");
			}

			$tmp_export_code		= 'TMP'.date('ymdHis').rand(1,100000);
			$npay_delivery_company	= config_load("npay_delivery_company");

			if($export_data['delivery_company_code']){
				$delivery_company_code = $npay_delivery_company[$export_data['delivery_company_code']];
			}else{
				$delivery_company_code = '';
			}

			if(!$export_data['delivery_number']) $delivery_company_code = "";

			if(preg_match( '/delivery/',$export_data['domestic_shipping_method'])){
				$shipping_method = "DELIVERY";
			}elseif(preg_match( '/postpaid/',$export_data['domestic_shipping_method'])){
				$shipping_method = "DELIVERY";
			}elseif(preg_match( '/quick/',$export_data['domestic_shipping_method'])){
				$shipping_method = "QUICK_SVC";
			}elseif(preg_match( '/direct/',$export_data['domestic_shipping_method'])){
				$shipping_method = "VISIT_RECEIPT";
			}else{
				$shipping_method = "DELIVERY";
			}

			if($export_data['delivery_mode'] == "redelivery"){
				$delivery_mode = "ReDeliveryExchange";
			}else{
				$delivery_mode = "ShipProductOrder";
			}

			$npay_product_order_id = array();
			foreach($export_data['items']['ea'] as $k => $ea){
				if($ea > 0) $npay_product_order_id[] = $export_data['items']['npay_product_order_id'][$k];
			}

			$npay_data = array(
				"mode"					=>"export_insert",
				"MallID"				=>$npayconfig['shop_id'],
				"ProductOrderID"		=>implode(",",$npay_product_order_id),
				"delivery_mode"			=>$delivery_mode,
				"npay_flag_release"		=>$export_data['npay_flag_release'],
				"DeliveryMethod"		=>$shipping_method,
				"DeliveryCompany"		=>$delivery_company_code,
				"TrackingNumber"		=>$export_data['delivery_number'],
				"order_seq"				=>$export_data['order_seq'],
				"tmp_export_code"		=>$tmp_export_code,
			);
			$res = $this->naverpaylib->send($npay_data,'export');
			$res = unserialize($res);

			if($res['result'] == "SUCCESS"){

				# 이미 npay 통신 성공 시
				$export_result = array("order_seq"		=> $export_data['order_seq'],
										"npay_product_order_id" => $export_data['npay_product_order_id'],
										"message"		=> $res['message'],
										"result"		=> $res['result'],
					);
				$query = $this->set_export_result($export_result);

			}elseif($res['result'] == "INSERT_SUCCESS"){

				$logTitle = '출고전송성공(API)';

			}elseif($res['result'] == "INSERT_ERROR"){

				$logTitle = '출고전송실패(API)';
			}

			return $res;

		}else{

			return "";

		}
	}

	# 주문수집 : 중계서버로부터 받은 출고처리결과 저장
	public function set_export_result($export){

		if(!$this->exportmodel)			$this->load->model("exportmodel");
		if(!$this->goodsmodel)			$this->load->model("goodsmodel");
		if(!$this->ordermodel)			$this->load->model("ordermodel");
		if(!$this->accountall)			$this->load->helper('accountall');
		if(!$this->accountallmodel)		$this->load->model('accountallmodel');
		if(!$this->providermodel)		$this->load->model('providermodel');

		foreach($export as $data){

			$sql = "select
						exp.export_code,exp.status,exp_item.*
					from
						fm_goods_export as exp
						left join fm_goods_export_item as exp_item on exp.export_code=exp_item.export_code
					where
						exp.order_seq=?
						and exp_item.npay_product_order_id=?";
			$query  = $this->db->query($sql,array($data['order_seq'],$data['npay_product_order_id']));
			$res	= $query->row_array();

			$export_data[$res['export_code']][] = array_merge($data,$res);
		}

		$r_package_goods_seq		= array();
		$r_reservation_goods_seq	= array();

		foreach($export_data as $export_code => $export_items){

			$export_result = false;
			foreach($export_items as $item){

				$order_seq		= $item['order_seq'];
				$npay_product_order_id		= $item['npay_product_order_id'];
				$message		= $item['message'];
				$export_status	= $item['status'];
				$export_date	= $item['export_date'];

				if($item['result'] == "SUCCESS" && (int) $export_status < 55){

					$export_result = true;

					if($item['suboption_seq']){
						$mode		= "suboption";
						$option_seq = $item['suboption_seq'];
					}else{
						$mode		= "option";
						$option_seq = $item['option_seq'];
					}

					// 옵션 step 수량 및 상태 변경
					$minus_ea = $item['ea'] * -1;
					$this->ordermodel->set_step_ea($export_status,$minus_ea,$option_seq,$mode);
					$this->ordermodel->set_step_ea(55,$item['ea'],$option_seq,$mode);
					$this->ordermodel->set_option_step($option_seq,$mode);

					if($mode == 'option'){
						$this->goodsmodel->stock_option('-',
														$item['ea'],
														$item['goods_seq'],
														$item['option1'],
														$item['option2'],
														$item['option3'],
														$item['option4'],
														$item['option5']);

					}else{
					$this->goodsmodel->stock_suboption('-',$item['ea'],
													$item['goods_seq'],
													$item['title1'],
													$item['option1']);

					}

					// 패키지 상품 재고 변경
					if($item['package_yn'] == 'y'){
						$export_target = "option";
						if($item['opt_type'] == 'sub') $export_target = "suboption";
						$result_option_package = $this->orderpackagemodel->{'get_'.$export_target}($item['option_seq']);
						foreach($result_option_package as $data_option_package){

							// 품절체크를 위한 변수정의
							if(!in_array($data_option_package['goods_seq'],$r_package_goods_seq)){
								$r_package_goods_seq[] = $data_option_package['goods_seq'];
							}
						}
					}

					// 출고량 업데이트를 위한 변수정의
					if(!in_array($item['goods_seq'],$r_reservation_goods_seq)){
						$r_reservation_goods_seq[] = $item['goods_seq'];
					}
				}
			}

			if($export_result){
				$sql = "update fm_goods_export set
								status='55',
								complete_date='".date("Y-m-d")."',
								status_date='".date("Y-m-d")."',
							npay_export_date='".$export_date."'
						where export_code=?";
				$this->db->query($sql,$export_code);

				//주문상태 변경
				$this->ordermodel->set_order_step($order_seq);

				// 출고예약량 업데이트
				foreach($r_reservation_goods_seq as $goods_seq){
					$this->goodsmodel->modify_reservation_real($goods_seq);
				}

				// 패키지 품절 처리
				foreach($r_package_goods_seq as $goods_seq){
					$this->goodsmodel->runout_check($goods_seq);
				}

			}

			if($export_status < 55 && $export_result){

				// 출고완료 후 작업
				$this->load->model('batchmodel');
				$arr_params = serialize(array('order_seq'=>$order_seq,'export_code'=>$export_code));
				$this->batchmodel->insert('export_complete',$arr_params,'none');;

				$logTitle	= '출고완료(API:'.$export_code.')';
				$logDetail	= "[".$npay_product_order_id."]Npay로 부터 ".$logTitle." 처리 되었습니다.";
				$this->ordermodel->set_log($order_seq,'process',"Npay",$logTitle,$logDetail,'',$export_code,'npay');
			}
		}

		return 'true';
	}

	# 주문 : 출고처리(Shop -> Npay)
	public function order_export($export){

		$actor = $this->managerInfo['mname'];
		if( defined('__SELLERADMIN__') === true ){
			$actor = $this->providerInfo['provider_name'];
		}
		$npay_delivery_company = config_load("npay_delivery_company");

		if($export['delivery_company_code']){
			$delivery_company_code = $npay_delivery_company[$export['delivery_company_code']];
		}else{
			$delivery_company_code = '';
		}

		if($export['delivery_number']) $export['delivery_number'] = str_replace("-","",trim($export['delivery_number']));
		if(!$export['delivery_number']) $delivery_company_code = "";

		if (in_array($export['domestic_shipping_method'], ['freight', 'direct_delivery'])) {
			$shipping_method = 'DIRECT_DELIVERY';
		} elseif (preg_match('/delivery/', $export['domestic_shipping_method'])) {
			$shipping_method = 'DELIVERY';
		} elseif (preg_match('/postpaid/', $export['domestic_shipping_method'])) {
			$shipping_method = 'DELIVERY';
		} elseif (preg_match('/quick/', $export['domestic_shipping_method'])) {
			$shipping_method = 'QUICK_SVC';
		} elseif (preg_match('/direct/', $export['domestic_shipping_method'])) {
			$shipping_method = 'VISIT_RECEIPT';
		} else {
			$shipping_method = 'DELIVERY';
		}

		$export_result	= array();
		$success_cnt	= 0;
		foreach($export['items']['npay_product_order_id'] as $item_k=>$npay_product_order_id){

			if(!$export['items']['ea'][$item_k]) continue;

			if($shipping_method == "DELIVERY" && !$delivery_company_code){

				$return = array();
				$return['result']		= 'ERROR';
				$return['message']		= "해당 택배사는 Npay 출고 불가합니다.";

			}else{

				//교환재배송일때
				if($export['npay_flag_release'] == "redelivery"){
					$operation = "ReDeliveryExchange";
					$tmp = array(
							'ProductOrderID'			=> $npay_product_order_id,
							'ReDeliveryCompanyCode'		=> $delivery_company_code,
							'ReDeliveryTrackingNumber'	=> $export['delivery_number'],
							'ReDeliveryMethodCode'		=> $shipping_method,
						);
				}else{
					$operation = "ShipProductOrder";
					$tmp = array(
							'ProductOrderID'			=> $npay_product_order_id,
							'DeliveryCompanyCode'		=> $delivery_company_code,
							'TrackingNumber'			=> $export['delivery_number'],
							'DeliveryMethodCode'		=> $shipping_method,
							'DispatchDate'				=> $this->getLocaltimeToGMT(),
						);
				}
				$result = $this->naverpaylib->ship_product_order($tmp,$operation);

				$return = array();
				$return['result']		= $result['ResponseType'];
				$return['timestamp']	= substr(str_replace("T"," ",$result['Timestamp']),0,19);
				$return['message']		= $result['Error']['Message'];

				if($return['result'] == "SUCCESS"){
					$success_cnt++;
					$logTitle	= "발송처리성공(API)";
					$logDetail	= "[".$npay_product_order_id."] 관리자가 ".$logTitle." 하였습니다.";
				}else{
					$logTitle	= "발송처리실패(API)";
					$logDetail	= "[".$npay_product_order_id."]".$return['message'];
				}
				$this->ordermodel->set_log($export['order_seq'],'process',$actor,$logTitle,$logDetail,'','','npay');
			}

			$export_result['success_cnt']							= $success_cnt;
			$export_result['export_items'][$npay_product_order_id]	= $return;
		}

		return $export_result;
	}

	# 출고 업데이트(Npay -> Shop)
	public function set_export_update($export){

		$this->load->model("exportmodel");
		$this->load->model("goodsmodel");
		$this->load->model("ordermodel");

		$export_code		= $export['export_code'];
		$export_status		= $export['status'];
		$export_npay_status = $export['npay_status'];

		# 출고완료
		if($export_status < $export_npay_status && $export_npay_status == "55"){
			$this->exportmodel->exec_complete_export($export_code,$this->cfg_order,'order_api');
		}
		# 배송중
		if($export_status < $export_npay_status && $export_npay_status == "65"){
			$this->exportmodel->exec_going_delivery($export_code,'Npay');
		}
		# 배송완료
		if($export_status < $export_npay_status && $export_npay_status == "75"){
			$this->exportmodel->exec_complete_delivery($export_code,'Npay');
		}

		// 상태변경일시 업데이트 추가 2018-08-07
		$query = "UPDATE fm_goods_export SET status_date=? where export_code = ?";
		$this->db->query($query,array(date("Y-m-d H:i:s"), $export_code));

		return 'true';
	}

	# 주문 : npay 상태 flag 저장
	public function npay_flag_update($type,$flag,$code){

		$table = "fm_order_".$type;
		$where = $type."_code";

		$this->db->query("update ".$table." set npay_flag=? where ".$where."=?",array($flag,$code));

	}

	# 주문 : 주문취소 (Shop -> Npay)
	public function order_cancel($npay_data){

		$return = array();

		$tmp = array(
					'ProductOrderID' => $npay_data['npay_product_order_id'],
					'CancelReasonCode'	=> $npay_data['cancel_reason'],
				);
		$result = $this->naverpaylib->cancel_sale($tmp);

		$return['result']		= $result['ResponseType'];
		$return['timestamp']	= substr(str_replace("T"," ",$result['Timestamp']),0,19);
		$return['message']		= $result['Error']['Message'];

		$actor = $this->managerInfo['mname'];

		if($return['result'] == "SUCCESS"){
			$logTitle	= "주문판매취소성공(API)";
		}else{
			$logTitle	= "주문판매취소실패(API)";
		}
		$logDetail	= "[".$npay_data['npay_product_order_id']."] ".$return['message'];
		$logParams	= array("refund_code"=>$npay_data['refund_code']);
		$this->ordermodel->set_log($npay_data['order_seq'],'process',$actor,$logTitle,$logDetail,$logParams,'','npay');

		return $return;
	}

	# 주문 : 주문취소 승인 (Shop -> Npay)
	public function approve_cancel($npay_data){

		$return = array();

		$tmp = array(
					'ProductOrderID' => $npay_data['npay_product_order_id'],
					'EtcFeeDemandAmount' => '0',
					'Memo' => '',
				);
		$result = $this->naverpaylib->approve_cancel_application($tmp);

		$return['result']		= $result['ResponseType'];
		$return['timestamp']	= substr(str_replace("T"," ",$result['Timestamp']),0,19);
		$return['message']		= $result['Error']['Message'];

		$actor = $this->managerInfo['mname'];

		if($return['result'] == "SUCCESS"){
			$logTitle	= "주문취소승인성공(API)(".$npay_data['refund_code'].")";
			$this->npay_flag_update('refund','ApproveCancelApplication',$npay_data['refund_code']);
		}else{
			$logTitle	= "주문취소승인실패(API)(".$npay_data['refund_code'].")";
		}
		$logDetail	= "[".$npay_data['npay_product_order_id']."] ".$return['message'];
		$logParams	= array("refund_code"=>$npay_data['refund_code']);
		$this->ordermodel->set_log($npay_data['order_seq'],'process',$actor,$logTitle,$logDetail,$logParams,'','npay');

		return $return;

	}

	# 주문 : 반품접수 (Shop -> Npay)
	public function order_return($npay_data){

		$return = array();

		//npay 반품배송 방법
		if($npay_data['return_method'] == "user"){
			$npay_return_method = "RETURN_INDIVIDUAL";	//직접반송
		}else{
			$npay_return_method = "RETURN_DELIVERY";	//RETURN_DELIVERY:일반반품택배, RETURN_DESIGNATED:지정반품택배
		}

		$npay_return_method = "RETURN_INDIVIDUAL";	//npay 요청으로 반품방법은 '직접반송'으로 고정.

		$tmp = array(
					'ProductOrderID'			=> $npay_data['npay_product_order_id'],
					'ReturnReasonCode'			=> $npay_data['reason'],
					'CollectDeliveryMethodCode' => $npay_return_method,
				);

		$result = $this->naverpaylib->request_return($tmp);

		$return['result']		= $result['ResponseType'];
		$return['timestamp']	= substr(str_replace("T"," ",$result['Timestamp']),0,19);
		$return['message']		= $result['Error']['Message'];

		$actor = $this->managerInfo['mname'];

		if($return['result'] == "SUCCESS"){
			$logTitle	= "반품접수성공(API)";
		}else{
			$logTitle	= "반품접수실패(API)";
		}
		$logDetail	= "[".$npay_data['npay_product_order_id']."] ".$return['message'];
		$this->ordermodel->set_log($npay_data['order_seq'],'process',$actor,$logTitle,$logDetail,'','','npay');

		return $return;
	}


	# 주문수집 : 반품요청 승인 (Shop -> Npay)
	public function approve_return($npay_data){

		$return = array();

		$tmp = array(
					'ProductOrderID' => $npay_data['npay_product_order_id'],
					'EtcFeeDemandAmount' => '0',
					'Memo' => '',
				);
		$result = $this->naverpaylib->approve_return_application($tmp);

		$return['result']		= $result['ResponseType'];
		$return['timestamp']	= substr(str_replace("T"," ",$result['Timestamp']),0,19);
		$return['message']		= $result['Error']['Message'];

		$actor = $this->managerInfo['mname'];
		if( defined('__SELLERADMIN__') === true ){
			$actor = $this->providerInfo['provider_name'];
		}

		if($return['result'] == "SUCCESS"){
			$logTitle	= "반품요청승인성공(API)(".$npay_data['return_code'].")";
			$this->npay_flag_update('return','ApproveReturnApplication',$npay_data['return_code']);
		}else{
			$logTitle	= "반품요청승인실패(API)(".$npay_data['return_code'].")";
		}

		$logDetail	= "[".$npay_data['npay_product_order_id']."] ".$return['message'];
		$logParams = array("return_code"=>$npay_data['return_code']);
		$this->ordermodel->set_log($npay_data['order_seq'],'process',$actor,$logTitle,$logDetail,$logParams,'','npay');

		return $return;

	}

	# 주문수집 : 반품/교환보류 해제 (Shop -> Npay)
	public function approve_return_hold($npay_data){

		$result = $this->naverpaylib->release_return_hold($npay_data['return_type'],$npay_data['npay_product_order_id']);

		if($npay_data['return_type'] == "exchange"){
			$title = "교환";
		}else{
			$title = "반품";
		}

		$return = array();
		$return['result']		= $result['ResponseType'];
		$return['timestamp']	= substr(str_replace("T"," ",$result['Timestamp']),0,19);
		$return['message']		= $result['Error']['Message'];

		$actor = $this->managerInfo['mname'];
		if( defined('__SELLERADMIN__') === true ){
			$actor = $this->providerInfo['provider_name'];
		}

		if($return['result'] == "SUCCESS"){
			$logTitle	= $title."보류해제성공(API)";
			$this->npay_flag_update('return','ReleaseReturnHold',$npay_data['return_code']);
		}else{
			$logTitle	= $title."보류해제실패(API)";
		}

		$logDetail	= "[".$npay_data['npay_product_order_id']."] ".$return['message'];
		$params = array("return_code"=>$npay_data['return_code']);
		$this->ordermodel->set_log($npay_data['order_seq'],'process',$actor,$logTitle,$logDetail,$params,'','npay');

		return $return;

	}

	# 주문수집 : 교환 수거 완료 (Shop -> Npay)
	public function approve_exchange($npay_data){

		$result = $this->naverpaylib->approve_collected_exchange($npay_data['npay_product_order_id']);

		$return['result']		= $result['ResponseType'];
		$return['timestamp']	= substr(str_replace("T"," ",$result['Timestamp']),0,19);
		$return['message']		= $result['Error']['Message'];

		$actor = $this->managerInfo['mname'];
		if( defined('__SELLERADMIN__') === true ){
			$actor = $this->providerInfo['provider_name'];
		}

		if($return['result'] == "SUCCESS"){
			$logTitle	= "교환수거완료성공(API)(".$npay_data['return_code'].")";
			$this->npay_flag_update('return','ApproveCollectedExchange',$npay_data['return_code']);
		}else{
			$logTitle	= "교환수거완료실패(API)(".$npay_data['return_code'].")";
		}

		$logDetail	= "[".$npay_data['npay_product_order_id']."] ".$return['message'];
		$params = array("return_code"=>$npay_data['return_code']);
		$this->ordermodel->set_log($npay_data['order_seq'],'process',$actor,$logTitle,$logDetail,$params,'','npay');

		return $return;

	}

	# 주문수집 : 발주처리 (Shop -> Npay)
	public function order_ready($npay_product_order_id,$order_seq){

		$result = $this->naverpaylib->place_product_order($npay_product_order_id);

		$return = array();
		$return['result']		= $result['ResponseType'];
		$return['timestamp']	= substr(str_replace("T"," ",$result['Timestamp']),0,19);
		$return['message']		= $result['Error']['Message'];

		if($return['result'] == "SUCCESS"){
			$logTitle	= "발주처리성공(API)";
			if(!$return['message']) $return['message'] = "발주처리 성공하였습니다.";
		}else{
			$logTitle	= "발주처리실패(API)";
		}
		if($return['message'] != "이미 발주확인 된 주문입니다."){
			$logDetail	= "[".$npay_product_order_id."] ".$return['message'];
			$this->ordermodel->set_log($order_seq,'process','Npay',$logTitle,$logDetail,'','','npay');
		}

		return $return;
	}

	# 주문수집 : 기존 반품건이 있는지 조회.
	public function get_return_code($npay_order_id,$npay_request_date,$return_type='return'){

		$query = $this->db->query("select
							return_code
						from
							fm_order_return
						where
							npay_order_id=?
							and npay_return_request_date=?
							and return_type=?
						",array($npay_order_id,$npay_request_date,$return_type));
		$res = $query->row_array();
		return $res;

	}

	# 주문수집 : 배송정보확인
	public function get_export_code($order_seq,$option_type,$npay_product_order_id,$order_type="order"){

		if($option_type == "opt") $option_type = "option";

		//---------------------------------------------------------------------------
		//배송 정보가 있는지 확인
		$exp_where	= $exp_bind = array();
		$exp_where[]= "exp_item.npay_product_order_id=?";
		$exp_bind[]	= $npay_product_order_id;
		$exp_field[] = "exp.order_seq";
		$exp_field[] = "exp.export_seq";
		$exp_field[] = "exp.export_code";
		$exp_field[] = "exp.status";
		$exp_field[] = "exp_item.*";

		if($order_type == "reorder"){
			# 맞교환 재주문의 배송정보가 있는지 확인
			if($option_type == "option"){
				$exp_field[] = "ord.orign_order_seq";
				$exp_field[] = "opt.top_item_seq";
				$exp_field[] = "opt.top_item_option_seq";
			}else{
				$exp_field[] = "opt.top_item_suboption_seq";
			}
			$exp_where[]= "ord.orign_order_seq=?";
			$exp_bind[]	= $order_seq;
		}else{
			# 일반(또는 원주문)의 배송정보가 있는지 확인.
			$exp_where[]= "ord.order_seq=?";
			$exp_bind[]	= $order_seq;
		}
		$sql		= "select
						".implode(",",$exp_field)."
					from
						fm_goods_export as exp
						left join fm_goods_export_item as exp_item on exp.export_code=exp_item.export_code
					";
		if($option_type == "option"){
			$sql .= "			left join fm_order_item_option as opt on opt.item_option_seq=exp_item.option_seq
								left join fm_order as ord on opt.order_seq=ord.order_seq ";
		}else{
			$sql .= "			left join fm_order_item_suboption as opt on opt.item_suboption_seq=exp_item.suboption_seq
								left join fm_order as ord on opt.order_seq=ord.order_seq ";
		}
		# 맞교환 재주문건 배송조회 일때
		if($order_type == "reorder"){
			if($option_type == "option"){
				$exp_where[] = "(ifnull(opt.top_item_option_seq,0) > 0)";
			}else{
				$exp_where[] = "(ifnull(opt.top_item_suboption_seq,0) > 0)";
			}
		# 일반 배송 조회일때
		}else{
			if($option_type == "option"){
				$exp_where[] = "(opt.top_item_option_seq is null or opt.top_item_option_seq='')";
			}else{
				$exp_where[] = "(opt.top_item_suboption_seq is null or opt.top_item_suboption_seq='')";
			}
		}
		$sql			.="	where
							".implode(" and ",$exp_where);
		$query			= $this->db->query($sql,$exp_bind);
		$exp_data		= $query->row_array();

		return $exp_data;

	}

	# Shop 주문/반품/취소/교환 상태 정보
	public function get_shop_order_info($ProductOrderID,$return_type=''){

		$opt_bind	= $sub_bind		= array();
		$opt_bind[] = $sub_bind[]	= $ProductOrderID;

		$where		= array();
		# 교환재주문건 검색 : 교환재주문건에 대한 반품처리시 사용.
		# (교환완료 후 구매자가 직접 반품처리는 불가하나 교환완료건 구매확정 후 판매자센터에서 직권취소 가능)
		if($return_type == "reorder_new"){
			$opt_where1 = " and ord.orign_order_seq !='' and opt.ea > 0";
			$opt_where2 = " and ord.orign_order_seq !=''";
			$sub_where  = " and sub.ea > 0";
		# 일반주문건 검색
		}else{
			$opt_where1 = " and (ord.orign_order_seq is null or ord.orign_order_seq='')";
			$opt_where2 = "";
			$sub_where  = "";
		}
		$sql = "(select
				'opt' as opt_type
				,ord.order_seq
				,ord.orign_order_seq
				,opt.step,opt.item_option_seq,'' as item_suboption_seq,opt.item_seq
			from
				fm_order as ord
				left join fm_order_item_option as opt on ord.order_seq=opt.order_seq ".$opt_where1."
			where
				opt.npay_product_order_id=?
		) union(
			select
				'sub' as opt_type
				,ord.order_seq
				,ord.orign_order_seq
				,sub.step,opt.item_option_seq,sub.item_suboption_seq,sub.item_seq
			from
				fm_order as ord
				left join fm_order_item_option as opt on ord.order_seq=opt.order_seq ".$opt_where2."
				left join fm_order_item_suboption as sub on opt.order_seq=sub.order_seq and opt.item_option_seq=sub.item_option_seq ".$sub_where."
			where
				sub.npay_product_order_id=?
		);
		";
		$que = $this->db->query($sql,array_merge($opt_bind,$sub_bind));
		$shop_step = $que->row_array();

		return $shop_step;
	}

	## 2018-10-16 동일환불그룹내 마지막 환불상품 여부. pjm
	public function get_last_claim_item_yn($type='refund',$order_info,$claim_info,$npay_collec=array()){

		if($type == "refund"){
			$sql = "
			select
					ref_item.refund_item_seq as claim_item_seq
					,ref_item.ea
				from
					fm_order_refund_item as ref_item
					left join fm_order_refund as ref on ref.refund_code=ref_item.refund_code
				where
					ref.refund_code=?
					and ref.order_seq=?
				order by ref_item.npay_product_order_id desc
			";
		}else{
			$sql = "
			select
					ret_item.return_item_seq as claim_item_seq
					,ret_item.ea
				from
					fm_order_return_item as ret_item
					left join fm_order_return as ret on ret.return_code=ret_item.return_code
				where
					ret.return_code=?
					and ret.order_seq=?
				order by ret_item.npay_product_order_id desc
			";
		}

		$que					= $this->db->query($sql,array($claim_info['claim_code'],$order_info['order_seq']));
		$_arr_shop_claim		= $que->result_array();

		// 쇼핑몰에 저장된 동일 환불/반품(item)건수와 수집 처리중인 건수가 동일 할 경우
		// 수집 처리중인 item 순서 기준으로 마지막 item 여부를 판단 한다.
		if($npay_collec && count($_arr_shop_claim) == $npay_collec['refund_cnt']){
			if($npay_collec['last_product_order_id'] == $order_info['npay_product_order_id']){
				$last_claim_item_yn = true;
			}else{
				$last_claim_item_yn = false;
			}
		}else{
			$claim_group_total_ea	= 0;
			foreach($_arr_shop_claim as $_shop_refund){
				$claim_group_total_ea += $_shop_refund['ea'];
			}
			$last_claim_item_seq = $_arr_shop_claim[count($_arr_shop_claim)-1]['claim_item_seq'];

			if($claim_info['claim_item_seq'] && $last_claim_item_seq == $claim_info['claim_item_seq']){
				$last_claim_item_yn = true;
			}else{
				$last_claim_item_yn = false;
			}
		}

		return array($last_claim_item_yn,$claim_group_total_ea);

	}

	# Shop에 저장된 주문/클레임 상태값 불러오기
	public function get_product_update_query($ProductOrderID,$return_type=''){

		# 정상주문건
		$shop_step = $this->get_shop_order_info($ProductOrderID);

		# 재주문건 확인(재주문건이 있을 경우 재주문건에서 반품 처리)
		if($return_type == "reorder_new"){
			$shop_reorder_step = $this->get_shop_order_info($ProductOrderID,'reorder_new');
			# 재주문건에 대한 환불/반품건 조회
			if($shop_reorder_step) $shop_step = $shop_reorder_step;
		}

		# 환불건
		if($shop_step){
			$sql = "
			select
					ref.refund_code
					,concat(ref.refund_type,'_',ref.`status`) as shop_claim_status
					,ref.refund_type
					,ref.`status` as refund_status
					,ref.npay_flag as refund_flag
					,ref_item.refund_item_seq
				from
					fm_order_refund_item as ref_item
					left join fm_order_refund as ref on ref.refund_code=ref_item.refund_code
				where
					ref_item.npay_product_order_id=?
					and ref.order_seq=?
			";
			$que = $this->db->query($sql,array($ProductOrderID,$shop_step['order_seq']));
			$shop_refund	= $que->row_array();
			if($shop_refund) $shop_step		= array_merge($shop_step,$shop_refund);
		}
		# 반품건
		if($shop_step){
			$sql = "
			select
					ret.return_code
					,concat(ret.return_type,'_',ret.`status`) as shop_claim_status
					,ret.return_type
					,ret.`status` as return_status
					,ret.npay_flag as return_flag
					,ret_item.return_item_seq
				from
					fm_order_return_item as ret_item
					left join fm_order_return as ret on ret.return_code=ret_item.return_code
				where
					ret_item.npay_product_order_id=?
					and ret.order_seq=?
			";
			$que = $this->db->query($sql,array($ProductOrderID,$shop_step['order_seq']));
			$shop_return	= $que->row_array();
			if($shop_return) $shop_step		= array_merge($shop_step,$shop_return);
		}

		return $shop_step;
	}

	# 수집한 주문건의 신규등록/수정/아무처리안함 여부 결정
	public function get_product_update_status($order_update,$claim_type,$claim_status,$deliv_status='N'){

		$shop_step	= $order_update['shop_detail'];
		$npay_step	= $order_update['npay_detail'];

		if(!$npay_step['return_flag']) $npay_step['return_flag'] = $shop_step['return_flag'];
		if(!$npay_step['refund_flag']) $npay_step['refund_flag'] = $shop_step['refund_flag'];

		# 직권취소의 경우 배송전/배송후 구분 없이 claim_type이 모두 'admin_cancel' 로 넘어옴.
		# 출고 후 관리자 직권 취소일 경우(교환건 포함)
		if($claim_type == "admin_cancel"){
			if($deliv_status == 'delivery'){
				$claim_type						= "admin_return";
				$return_type					= "return";
				$npay_step['return_type']		= "return";
				$npay_step['return_status']		= $npay_step['refund_status'];
				$npay_step['npay_claim_status']	= $claim_type;
				$npay_step['step']				= "75";
			# 재주문건이 출고가 되어 있고 직권취소시 재주문 반품 처리.
			//}elseif($order_update['reorder_cancel'] == 'reorder_new' && $deliv_status == 'redelivery'){
			//	$claim_type = "admin_return";
			}else{
				$npay_step['npay_claim_status']	= $claim_type;
				$return_type					= "refund";
				$npay_step['step']				= $shop_step['step'];
			}
		}else{
			$return_type					= $claim_type;
		}

		# 재주문에 대한 직권 취소인지 확인.
		//if($shop_step['return_type'] == "exchange" && $claim_type == "admin_cancel" && $order_update['reorder_cancel'] == ''){
			//$order_update['reorder_cancel'] = "reorder_new";
		//}

		# 주문 상태 체크
		if(!in_array($npay_step['step'],array("none","85")) && ($npay_step['step'] > $shop_step['step']) ){
			if($npay_step['step'] < 45) $order_update['order'] = "upt";
			else $order_update['delivery'] = "upt";
		}

		# 주문취소 상태 체크
		if($npay_step['step'] != 95 && in_array($claim_type,array("cancel","admin_cancel"))){

			if( trim($shop_step['refund_code']) && $shop_step['refund_type'] == "cancel_payment"){
				if($shop_step['refund_status'] != $npay_step['refund_status'] && $npay_step['refund_type'] == "cancel_payment" ) $order_update['refund'] = "upt";
				if($shop_step['refund_flag'] != $npay_step['refund_flag'] ) $order_update['refund']	= "upt";
			}else{
				$order_update['refund'] = "new";
			}
			$order_update['return'] = "non";

			# 클레임 요청일/완료일/승인일
			$request_date		= $cancel['ClaimRequestDate'];
			$complete_date		= $cancel['CancelCompletedDate'];
			$approval_date		= $cancel['CancelApprovalDate'];
		}
		# 반품 상태 체크(관리자 직권취소 포함)
		elseif(in_array($claim_type,array("return","admin_return"))){

			if($claim_status == "return_reject"){
				if($shop_step['return_code'])  $order_update['return'] = "upt";
			}else{

				$exchange_return = $order_update['return'];
				if($shop_step['return_code'] && ($shop_step['return_type'] == $return_type || $exchange_return == "reorder_new")){

					# 재주문건의 반품완료건 일 때.
					if($exchange_return == "reorder_new" && $shop_step['return_status'] == "complete"){
						$order_update['return']	= "non";
						$order_update['refund']	= "non";
					}else{
						# 반품 상태 업데이트 조건.
						if($shop_step['return_status'] != $npay_step['return_status'] ) $order_update['return'] = "upt";
						if($shop_step['return_flag'] != $npay_step['return_flag'] ) $order_update['return']	= "upt";

						$request_date	= $return['ClaimRequestDate'];
						$complete_date	= $return['ReturnCompletedDate'];
						$approval_date	= $return['CollectCompletedDate'];		//수거완료일
					}
				# 재주문건의 반품신청
				}elseif($shop_step['return_code'] && $shop_step['return_type'] == "exchange" && $claim_type == 'admin_return'){
					$order_update['return'] = "reorder_new";
					$order_update['refund'] = "none";
				# 신규 등록
				}else{
					$order_update['return'] = "new";
				}
				# refund 상태가 다를 때(반품일때만 체크)
				if($exchange_return != "reorder_new"){
					if($shop_step['refund_code'] && $shop_step['refund_type'] && $shop_step['refund_type'] == $return_type){
					if($shop_step['refund_status'] != $npay_step['refund_status'] ) $order_update['refund'] = "upt";
				# 신규 등록
				}else{
					$order_update['refund'] = "new";
				}
			}
		}
		}
		# 교환 상태 체크
		elseif($claim_type == "exchange"){

			if($shop_step['return_code'] && $shop_step['return_type'] == $return_type){

				# 반품 상태 업데이트 조건.
				if($shop_step['return_status'] != $npay_step['return_status'] ) $order_update['exchange'] = "upt";
				if(strtolower($shop_step['return_flag']) != $npay_step['return_flag'] ) $order_update['exchange']	= "upt";

				$request_date	= $exchange['ClaimRequestDate'];
				$complete_date	= $exchange['CollectCompletedDate'];	//수거완료시 원주문 반품완료
				$redelivery_date= $exchange['ReDeliveryOperationDate'];	//재배송 처리일

				if( $order_update['shop_detail']['opt_type'] == "sub"){
					$option_type = "suboption";
				}else{
					$option_type = "option";
				}
				# 재주문건에 대한 배송건이 있는지 체크
				$exp_data = $this->get_export_code($shop_step['order_seq'],$option_type,$order_update['npay_product_order_id'],'reorder');
				if(!$exp_data) $order_update['exchange_export']	= "upt";

			# 신규 등록
			}else{
				$order_update['exchange'] = "new";
			}

		}

		$order_update['shop_detail']	= $shop_step;
		$order_update['npay_detail']	= $npay_step;

		return array($order_update);

	}

	# 주문수집 : 주문 상태 확인 - 업데이트 여부 결정 (배송상태, 반품/교환 상태)
	public function get_product_update_use($data,$claim=array(),$delivery=array()){

		//해당 주문 정보 update
		$order_update = array('npay_product_order_id'=>$data['ProductOrderID'],
							  'order'			=> 'non',
							  'delivery'		=> 'non',
							  'cancel'			=> 'non',
							  'return'			=> 'non',
							  'refund'			=> 'non',
							  'exchange'		=> 'non',
							  'exchange_export'	=> 'non',
							  'msg'				=> 'non',
						);

		$order_status		= strtolower($data['ProductOrderStatus']);
		$claim_type			= strtolower($data['ClaimType']);
		$claim_status		= strtolower($data['ClaimStatus']);

		if($request_date)	$npay_step['request_date']	= substr(str_replace("T"," ",$request_date),0,19);
		if($complete_date)	$npay_step['complete_date']	= substr(str_replace("T"," ",$complete_date),0,19);
		if($approval_date)	$npay_step['approval_date']	= substr(str_replace("T"," ",$approval__date),0,19);
		if($redelivery_date)$npay_step['redelivery_date']= substr(str_replace("T"," ",$redelivery_date),0,19);

		$claim_info = $claim[$claim_type];
		if($claim_type == "exchange")	$claim_redelivery = $claimdh['redelivery'];

		$holdback_status	= strtolower($claim_info['HoldbackStatus']);
		$holdback_reason	= strtolower($claim_info['HoldbackReason']);

		# 다음의 경우 맞교환 재주문건이 있는지 검색
		#  : 교환재배송중 exchange_redelivering, 교환완료 exchange_done, 관리자 직권취소
		$return_type		= "";
		/*
		if(($claim['exchange'] && in_array($claim_status,array("exchange_redelivering","exchange_done","exchange_complete")))
			|| in_array($claim_status,array("admin_cancel","admin_cancel_done"))){
			$return_type				= "reorder_new";
		}*/
		if(in_array($claim_status,array("admin_cancel","admin_cancel_done"))){
			$return_type				= "reorder_new";
		}
		# Shop 주문/클레임 상태값 불러오기
		$shop_step						= $this->get_product_update_query($data['ProductOrderID'],$return_type);
		$order_update['shop_detail']	= $shop_step;

		# Npay 주문/클레임 상태값 매핑
		$claimArr						= array();
		$claimArr['claimtype']			= $claim_type;
		$claimArr['claimstatus']		= $claim_status;
		$claimArr['holdback_status']	= $holdback_status;
		$claimArr['holdback_reason']	= $holdback_reason;
		$npay_step						= $this->get_order_option_step($order_status,$claimArr);
		$order_update['npay_detail']	= $npay_step;

		# 수집된 주문정보에 배송정보가 있는지 확인.
		if($delivery['SendDate']) $deliv_status = "delivery";
		elseif($claim['redelivery']['ReDeliveryMethod']) $deliv_status = "redelivery";
		else $deliv_status = "N";

		# Shop주문 상태와 Npay주문상태를 다시 한번 확인해서 최종 주문상태를 결정.
		list($order_update) = $this->get_product_update_status($order_update,$claim_type,$claim_status,$deliv_status);

		return $order_update;
	}

	## 배송지 정보 복호화
	public function get_shipping_address($scl,$secret,$ShippingAddress){

		$shipping_address = array();

		//국내/국외
		if($ShippingAddress['AddressType'] == "DOMESTIC"){
			$shipping_address['international'] = "domestic";
		}elseif($ShippingAddress['AddressType'] == "FOREIGN"){
			$shipping_address['international'] = "international";
		}else{
			$shipping_address['international'] = "";
		}
		//도로명 주소
		if($ShippingAddress['IsRoadNameAddress'] == 'false'){
			$shipping_address['recipient_address_type'] = 'zibun';
		}else{
			$shipping_address['recipient_address_type'] = 'street';
		}

		if($shipping_address['recipient_address_type'] == "street"){
			$shipping_address['recipient_address_street']	= $scl->decrypt($secret,$ShippingAddress['BaseAddress']);
			$shipping_address['recipient_address']			= "";
		}else{
			$shipping_address['recipient_address']			= $scl->decrypt($secret,$ShippingAddress['BaseAddress']);
			$shipping_address['recipient_address_street']	= "";
		}
		if($ShippingAddress['DetailedAddress']){
			$shipping_address['recipient_address_detail']	= $scl->decrypt($secret,$ShippingAddress['DetailedAddress']);
		}else{
			$shipping_address['recipient_address_detail']	= '';
		}
		if($ShippingAddress['Name']){
			$shipping_address['recipient_user_name']		= $scl->decrypt($secret,$ShippingAddress['Name']);
		}else{
			$shipping_address['recipient_user_name']		= "";
		}
		if($ShippingAddress['Tel1']){
			$shipping_address['recipient_phone']			= $scl->decrypt($secret,$ShippingAddress['Tel1']);
		}else{
			$shipping_address['recipient_phone']			= "";
		}
		if($ShippingAddress['Tel2']){
			$shipping_address['recipient_cellphone']		= $scl->decrypt($secret,$ShippingAddress['Tel2']);
		}else{
			$shipping_address['recipient_cellphone']		= "";
		}

		$shipping_address['recipient_zipcode'] = $ShippingAddress['ZipCode'];

		return $shipping_address;

	}

	# Npay주문번호로 Shop주문번호 확인.
	public function get_order_seq($npay_order_id){

		$query = $this->db->query("select order_seq,memo,international,payment from fm_order where IFNULL(npay_order_id,'')!='' and  npay_order_id=?",$npay_order_id);
		$order_data = $query->row_array();
		if($order_data['order_seq']){
			$order_use		= true;
			$order_seq		= $order_data['order_seq'];
			$memo			= $order_data['memo'];
			$international	= $order_data['international'];
			$payment		= $order_data['payment'];
		}else{
			$order_use		= false;
			$order_seq		= '';
			$memo			= '';
			$international	= '';

			# 주문번호 생성
			$query		= $this->db->query("select order_seq from fm_order_item where IFNULL(npay_order_id,'')!='' and npay_order_id=?",$npay_order_id);
			$item_data	= $query->row_array();
			if($item_data['order_seq']){
				$order_seq	= $item_data['order_seq'];
			}else{
				$order_seq	= $this->ordermodel->get_order_seq();
			}

		}

		return array($order_use,$order_seq,$international,$memo,$payment);
	}

	# 주문시 적용된 할인 정책 불러오기
	public function get_order_sales($sql_where,$sql_params,$mode='',$_params){

		$sql = "select * from fm_partner_order_detail where (1) and ". implode(" and ",$sql_where);
		$query				= $this->db->query($sql,$sql_params);
		$sale_detail		= $query->result_array();
	
		if($sale_detail){
	
			$return_sale		= array();
			$item_goods_code	= array();
			foreach($sale_detail as $data){
				$item_goods_code[$data['goods_seq']] = $data['goods_code'];
				if($mode == ''){
					if($data['option_type'] == "option"){
						$return_sale['OPT'][$data['option_seq']] = $data;
					}elseif($data['option_type'] == "suboption"){
						$return_sale['SUB'][$data['option_seq']] = $data;
					}
				}else{
					$return_sale = $data;
				}
			}
		}

		// 이미 등록된 주문건이 있는지 확인
		$return_order_data		= array();
		$order_seq				= $_params['order_seq'];
		$goods_seq				= $_params['goods_seq'];
		$npay_order_id			= $_params['npay_order_id'];
		$npay_product_order_id	= $_params['npay_product_order_id'];
		$npay_opt_type			= $_params['npay_opt_type'];
		$npay_packgenumber		= $_params['npay_packgenumber'];
		// -----------------------------------------------------------------------------------
		# 등록된 order_shipping이 있는지 확인.
		$query = $this->db->query("select * from fm_order_shipping where order_seq=? and  npay_packgenumber=?",array($order_seq,$npay_packgenumber));
		$chk_shipping = $query->row_array();
		$return_order_data['shipping'] = $chk_shipping;

		# 등록된 order_item이 있는지 확인
		if( $return_order_data['shipping'] ) {
			$_query		= "select item_seq,provider_seq from fm_order_item where order_seq=? and goods_seq=? and npay_order_id=? and shipping_seq=?";
			$query		= $this->db->query($_query, array($order_seq,$goods_seq,$npay_order_id,$chk_shipping['shipping_seq']));
			$order_item = $query->row_array();
			$return_order_data['item'] = $order_item;
		}

		# 등록된 order_item_option, order_item_suboption 이 있는지 확인
		if($npay_opt_type != "추가구성상품" ){
			$option_query = "select item_option_seq from fm_order_item_option where order_seq=? and npay_product_order_id=?";
		}else{
			$option_query = "select item_option_seq,item_suboption_seq from fm_order_item_suboption where order_seq=? and npay_product_order_id=?";
		}
		$query = $this->db->query($option_query, array($order_seq,$npay_product_order_id));
		$order_item = $query->row_array();
		$return_order_data['option'] = $order_item;

		if($mode == ''){
			return array($item_goods_code,$return_sale,$return_order_data);
		}else{
			return array($return_sale,$return_order_data);
		}
	}

	# 배송방법 매핑
	public function get_shipping_method($method,$feetype=''){

		if($method == "DELIVERY"){					//택배
			$shipping_method = "delivery";
		}elseif($method == "VISIT_RECEIPT"){		//방문수령
			$shipping_method = "direct";
		}elseif($method == "QUICK_SVC"){			//퀵
			$shipping_method = "quick";
		}elseif($method == "DIRECT_DELIVERY"){		//직접배송
			$shipping_method = "direct_delivery";
		}else{
			$shipping_method = "delivery";
		}

		$shipping_paytype = "delivery";		//착불
		if($shipping_method == "delivery" && ($feetype == "후결제" || $feetype == "착불")){
			$shipping_method	= "postpaid";
			$shipping_paytype	= "postpaid";
		}
		return $shipping_method;
	}

	## 주문상세 내역 저장
	public function set_order($timestamp='',$orderdata,$order_desc=array()){

		//error_reporting(E_ALL);
		$this->load->model("eventmodel");
		$this->load->model("exportmodel");
		$this->load->model('buyconfirmmodel');
		$this->load->model('shippingmodel');

		//암호 복호화를 위한 시크릿값 생성
		if($timestamp){
			$secret = $this->naverpaylib->generateKey($timestamp);
			$scl	= $this->naverpaylib->scl;
		}

		if($orderdata['ProductOrderInfoList']['Order']){
			$ProductOrderInfoList[] = $orderdata['ProductOrderInfoList'];
		}else{
			$ProductOrderInfoList =  $orderdata['ProductOrderInfoList'];
		}
		# 변수선언
			$result_log 				= array();		// 주문 수집 결과
			$arr_referers 				= array();		// 통계용 유입경로 배열 
			$arr_referer_domains		= array();		// 통계용 유입경로 도메인 배열 
			$r_reservation_goods_seq	= array();		// 출고량 업데이트를 위한 변수선언
			$option_product				= array();
			$arr_stockable				= array();
			$export_list				= array();		// 출고 리스트
			$export_list55				= array();		// 출고완료리스트
			$export_list75				= array();		// 배송완료리스트
			$refund_list				= array();		// 환불리스트
			$return_list				= array();		// 반품,교환리스트
			$exchange_list				= array();		// 교환 리스트
			$refund_reject				= array();		// 환불 철회 리스트
			$return_reject				= array();		// 반품 철회 리스트
			$exchange_reject			= array();		// 교환 철회 리스트
			$last_change_date			= array();		// 상품주문번호별 npay 최종 수정일자
			$buy_confirm_list			= array();		// 구매확정 리스트
			$order_shipping				= array();		// 주문 기본 배송비
			$arr_packagenumber			= array();		// 묶음배송번호
			$opt_manager_code = array();
			$old_request_timestamp = '';
			$npay_sale_npay				= array();
			$receiver_changed_list		= array();		//배송지변경 리스트
			$receiver_changed_order_seq	= array();		//배송지변경 주문번호 리스트
		
			$shippingmemo_product_order_id	= array();		//배송 메모 변경 주문상품 리스트
			$refund_group				= array();
			$process_list				= array();
			
			/**
			* 정산개선 배열초기화
			* @ accountallmodel
			**/
			$account_ins_shipping		= array();
			$account_ins_opt			= array();
			$account_ins_subopt			= array();

			$cnt_i						= 0;

		# 주문생성(주문접수, 결제확인)
		foreach($ProductOrderInfoList as $idx_i => $product_data){

			// -----------------------------------------------------------------------------------
			// Response Data
				$Order					= $product_data['Order'];					//주문
				$ProductOrder			= $product_data['ProductOrder'];			//주문상품

				$Delivery = $CancelInfo = $ReturnInfo = $ExchangeInfo = $DecisionHoldbackInfo = "";
				if($product_data['Delivery'])		$Delivery		= $product_data['Delivery'];		//배송 현황 정보
				if($product_data['CancelInfo'])		$CancelInfo		= $product_data['CancelInfo'];		//취소 정보
				if($product_data['ReturnInfo'])		$ReturnInfo		= $product_data['ReturnInfo'];		//반품 정보
				if($product_data['ExchangeInfo'])	$ExchangeInfo	= $product_data['ExchangeInfo'];	//교환 정보
				if($product_data['DecisionHoldbackInfo']) $DecisionHoldbackInfo= $product_data['DecisionHoldbackInfo'];	//구매 확정 보류 정보

				$npay_order_id				= trim($Order['OrderID']);
				$npay_product_order_id		= trim($ProductOrder['ProductOrderID']);
				$npay_product_status		= $ProductOrder['ProductOrderStatus'];
				$npay_product_claim			= ($ProductOrder['ClaimStatus'])? $ProductOrder['ClaimStatus']:"NONE";

				# 주문상품정보 공통
				/*
					[DeliveryDiscountAmount] => 0			//배송비 최종할인액
					[DeliveryFeeAmount] => 0				//배송비합계(변동될수도 있음)
					[DeliveryPolicyType] => 무료			//배송비 정책
					[ExpectedDeliveryMethod] => DELIVERY	//구매자 선택 배송방법(A.1.9 참고)
					[FirstSellerBurdenDiscountAmount] => 0	//판매자 부담 할인액(상품별 할인액 중 판매자 비용 부담 금액(최초))
					[MallID] => gabiashop
					[OptionManageCode] => opt113031_opt213031	//주문등록시 사용한 옵션 관리코드
					[OptionPrice] => 0						//옵션금액
					[PackageNumber] => 2016012110398358		//묶음 배송 번호
					[PlaceOrderStatus] => NOT_YET			//발주상태코드(A.1.2)
					[ProductClass] => 조합형옵션상품		//상품종류(단독/옵션상품/추가상품)
					[ProductDiscountAmount] => 0			//상품별 할인액(즉시할인+상품할인쿠폰+복수구매할인)
					[ProductID] => 2854						//가맹점 상품번호
					[ProductInfoApiVersion] => API_VERSION_20	//상품정보API버전(A.1.14)
					[ProductOrderID] => 2016012111485190		//반품 배송비 묶음 청구 상품 주문번호(여러개 쉼표 구분)
					[ProductOrderStatus] => PAYED				//상품주문상태(string)
					[Quantity] => 1							//수량
					[SectionDeliveryFee] => 0				//지역별 추가 배송비
					[SellerBurdenDiscountAmount] => 0		//4 부담 할인액
					[SellerProductCode] => 1_direct			//판매자 상품코드(판매자가 임의 지정)
					[ShippingDueDate] => 2016-01-26T14:59:59.00Z		//발송기한
					[ShippingFeeType] => 무료						//배송비 형태(선불/착불/무료)
					[TotalPaymentAmount] => 12000					//총 결제금액(할인적용 후 금액)
					[TotalProductAmount] => 12000					//상품 주문 금액(할인적용 전)
					[UnitPrice] => 12000							//상품가격
					[CommissionRatingType] => 결제수수료			//수수료 과금 구분(결제수수료)
					[InflowPath] => 네이버쇼핑 외					//유입경로(검색광고(SA)/지식쇼핑/지식쇼핑외
					[MerchantProductId] => 1_direct				//가맹점 상품번호(SellerProductCode와 동일)
				*/
				$goods_seq			= $ProductOrder['ProductID'];
				$goods_name			= $ProductOrder['ProductName'];		//상품명
				$order_ea			= $ProductOrder['Quantity'];		//주문수량
				$npay_opt_type		= $ProductOrder['ProductClass'];	//상품구분(단일옵션,조합형옵션,추가구성상품)
				$npay_packgenumber	= $ProductOrder['PackageNumber'];	//묶음배송번호
				$npay_product_dc	= (int)$ProductOrder['ProductDiscountAmount'];	//상품별 할인액(즉시할인+상품할인쿠폰+복수구매할인)
				$npay_seller_dc		= (int)$ProductOrder['FirstSellerBurdenDiscountAmount'];	//판매자 부담 할인액
																					//(상품별 할인액 중 판매자 비용 부담 금액(최초))
				$npay_delivery_dc	= (int)$ProductOrder['DeliveryDiscountAmount'];	//배송비 최종할인액


				if(!in_array($goods_seq,$r_reservation_goods_seq)) $r_reservation_goods_seq[]	= $goods_seq;

				# 중계서버로부터 넘어온 주분번호별 timestamp(복호화 시 필요)
				if($order_desc){

					$order_desc_info = $order_desc[$npay_product_order_id][$npay_product_status][$npay_product_claim];

					if(trim($npay_product_order_id) == trim($old_npay_product_order_id) && (int)$order_desc_info['order_cnt'] > (int)$cnt_i){
					}else{
						$cnt_i = 0;
					}

					# 결제확인 상태에서 배송지 변경건으로 인해 동일 상품주문번호로 중복 수집 되는 경우가 있음.
					# 배송지 변경 유무, 요청 timestamp를 각각의 수집건에 맞게 매칭 해줌.
					$request_timestamp   = $order_desc_info['request_timestamp2'][$cnt_i];
					$is_receiver_changed = $order_desc_info['is_receiver_changed2'][$cnt_i];

					$cnt_i++;

					$old_npay_product_order_id = $npay_product_order_id;

					if($is_receiver_changed == "true"){
						$is_receiver_changed = "true";
					}else{
						$is_receiver_changed = "false";
					}

					//npay 주문 최종 수정 일시
					$npay_last_change_date = $order_desc_info['last_change_date'];

					//배송지 정보 변경 유무
					if(trim($is_receiver_changed) == "true"){
						$npay_is_receiver_changed	= true;
					}else{
						$npay_is_receiver_changed	= false;
					}

					if(!$timestamp && $old_request_timestamp != $request_timestamp){
						$secret = $this->naverpaylib->generateKey($request_timestamp);
						$scl	= $this->naverpaylib->scl;
						$old_request_timestamp = $request_timestamp;
					}
					// Npay Real Server or Test Server
					$npay_server_info		= $order_desc_info['npay_server_info'];
				}else{
					$request_timestamp		= $timestamp;
					$npay_server_info		= '';
					$is_receiver_changed	= '';
				}

				// -----------------------------------------------------------------------------------
				// 단일옵션의 option_seq
				$MerchantProductId = explode("@",base64_decode($ProductOrder['MerchantProductId']));
				if(!$ProductOrder['OptionManageCode'] && $MerchantProductId[1]){
					$opt_manager_code[$goods_seq] = str_replace("opt1","",$MerchantProductId[1]);
					$option_seq = $opt_manager_code[$goods_seq];
				}
				//주문시 단일옵션의 옵션번호를 MerchantProductId를 통해 가지고 오는게 정상.
				//넘어온 옵션번호가 없다면 아래 실행
				if(!$option_seq && $npay_opt_type == "단일상품"){
					$query = $this->db->query("select * from fm_goods_option where goods_seq=? and  default_option='y'",array($goods_seq));
					$row = $query->row_array();
					$option_seq = $row['option_seq'];
				}
				// -----------------------------------------------------------------------------------
				//주문시 적용된 할인 정책
				$_sales_where	= array();
				$_sales_params	= array();

				$_sales_where[]			= "session_tmp=?";
				$_sales_params[]		= $MerchantProductId[0];

				if($npay_product_order_id){
					$_sales_where[]		= "(ifnull(partner_order_pk,'') = '' or partner_order_pk=?)";
					$_sales_params[]	= $npay_product_order_id;
				}else{
					$_sales_where[]		= "ifnull(partner_order_pk,'') = ''";
				}

				if($order_ea){
					$_sales_where[] = " ea = ? ";
					$_sales_params[] = $order_ea;
				}

			// -----------------------------------------------------------------------------------
			// step1-1. 주문번호 확인 및 생성(신규주문여부,주문번호, 해외배송여부)
				list($order_use,$order_seq,$international,$memo,$payment) = $this->get_order_seq($npay_order_id);
				$arr_order_seq[] = $order_seq;		// 주문번호 수집

				$_params = array("order_seq"			=>$order_seq,
								"npay_order_id"			=>$npay_order_id,
								"npay_product_order_id"	=>$npay_product_order_id,
								"goods_seq"				=>$goods_seq,
								"npay_opt_type"			=>$npay_opt_type,
								"npay_packgenumber"			=>$npay_packgenumber);
				list($item_goods_code,$return_sale,$return_order) = $this->get_order_sales($_sales_where,$_sales_params,'',$_params);

				# 이미 저장된 주문정보가 있다면 sequence 정리
				$order_item_data		= $return_order['item'];
				$order_item_option_data = $return_order['option'];
				$order_shipping_data	= $return_order['shipping'];
				
				$item_seq				= $order_item_data['item_seq'];
				$provider_seq			= $order_item_data['provider_seq'];
				$item_option_seq		= $order_item_option_data['item_option_seq'];		//option
				$item_suboption_seq		= ($order_item_option_data['item_suboption_seq'])? $order_item_option_data['item_suboption_seq']: '';	//suboption
				$shipping_seq			= $order_shipping_data['shipping_seq'];
				$shipping_method		= $order_shipping_data['shipping_method'];
				$shipping_group			= $order_shipping_data['shipping_group'];
			// -----------------------------------------------------------------------------------
				#--------------------------------------------------------------------------------------------
				// fm_partner_order_detail 없고, 기존에 저장된 주문건도 없을 경우에는 건너뛰기. 다음 주문건 처리
				if( empty($return_sale) && (!$item_seq || !$item_option_seq || !$shipping_seq) ) continue;

				// -----------------------------------------------------------------------------------
				// 주문시 생성한 옵션 코드로 할인 정책 불러오기
				/*
				if(!$ProductOrder['OptionManageCode']){
					$option_manager_code = $ProductOrder['MerchantCustomCode1'];
				}else{
					$option_manager_code = $ProductOrder['OptionManageCode'];
				}
				*/
				// -----------------------------------------------------------------------------------
				// 옵션구분(필수옵션, 추가옵션)에 따른 옵션번호 및 주문시 적용된 할인 정책
				if($npay_opt_type == "추가구성상품"){
					$OptionManageCode	= explode("_",$ProductOrder['OptionManageCode']);
					$option_seq			= $OptionManageCode[0];
					$suboption_seq		= $OptionManageCode[1];
					$opt_sales			= $return_sale['SUB'][$suboption_seq];
					$option_type		= "suboption";
				}else{
					if($ProductOrder['OptionManageCode']){
						$OptionManageCode	= explode("_",$ProductOrder['OptionManageCode']);
						$option_seq			= substr($OptionManageCode[0],4);
					}
					$suboption_seq		= "";
					$opt_sales			= $return_sale['OPT'][$option_seq];
					$option_type		= "option";
				}
				// 사용된 파트너 주문 고유키 테이블 저장
				unset($partner_order);
				if($return_sale){
					$partner_order = array();
					$partner_order['partner_order_pk'] = $npay_product_order_id;
					$this->db->where('partner_order_seq',$opt_sales['partner_order_seq']);
					$res = $this->db->update('fm_partner_order_detail',$partner_order);
				}
				// -----------------------------------------------------------------------------------
				//주문시 선택한 배송방법
				$user_shipping_method = $this->get_shipping_method($ProductOrder['ExpectedDeliveryMethod'],$ProductOrder['ShippingFeeType']);

				$shipping_method		= (!$shipping_method)? $opt_sales['shipping_method']:$shipping_method;
				$shipping_group			= (!$shipping_group)? $opt_sales['shipping_group']:$shipping_group;
				$provider_seq			= (!$provider_seq)? $opt_sales['provider_seq'] : $provider_seq;

				if(!$shipping_method) $shipping_method	= "delivery";
				if(!$shipping_group) $shipping_group	= $shipping_method;
				if(!$distribution) $distribution		= "cart";
				// -----------------------------------------------------------------------------------
				if(!$distribution)		$distribution		= $opt_sales['distribution'];
				if(!$referer_domain)	$referer_domain		= $opt_sales['referer_domain'];
				if(!$referer)			$referer			= $opt_sales['referer'];
				if(!$marketplace)		$marketplace		= $opt_sales['marketplace'];
			// -----------------------------------------------------------------------------------
				## 패키지 상품 확인
				$package_yn = "n";
				$goods_info = $this->goodsmodel->get_goods($goods_seq);
				if($goods_info['package_yn'] == "y") $package_yn = "y";
				if($goods_info['package_yn_suboption']) $package_yn = "y";
				$goods_tax = $goods_info['tax'];
			// -----------------------------------------------------------------------------------
			// step1-3. log create
				$order_log							= array();
				$order_log['result']				= 'true';
				$order_log['order_insert']			= false;
				$order_log['order_seq']				= $order_seq;
				$order_log['npay_order_id']			= $npay_order_id;
				$order_log['npay_product_order_id'] = $npay_product_order_id;
				$order_log['goods_seq']				= $goods_seq;
				$order_log['option_type']			= $option_type;
				$order_log['package_yn']			= $package_yn;
				$order_log['payment']				= $Order['PaymentMeans'];
				$order_log['is_receiver_changed']	= $is_receiver_changed;
				$order_log['is_payed_shipping']		= 'n';
				$order_log['message'][]				= "주문 조회 시작";
			// -----------------------------------------------------------------------------------
			// step1-4. 주문상태 및 기타 정보 정리
				## 주문, 취소, 반품, 교환 상태 정보 가져오기
				$ClaimArr = array('cancel'		=> $CancelInfo,
								  'return'		=> $ReturnInfo,
								  'exchange'	=> $ExchangeInfo,
								  'redelivery'	=> $DecisionHoldbackInfo
								);
				$product_update					= $this->get_product_update_use($ProductOrder,$ClaimArr,$Delivery);
				$shop_status					= $product_update['shop_detail'];	//쇼핑몰에 저장된 주문상태
				$npay_status					= $product_update['npay_detail'];	//네이버페이로부터 전달받은 주문상태
				$order_log['old_step']			= $shop_status['step'];
				$order_log['step']				= $npay_status['step'];
				$order_log['npay_status']		= $ProductOrder['ProductOrderStatus'];
				$order_log['npay_claimtype']	= $ProductOrder['ClaimType'];
				$order_log['npay_claimstatus']	= $ProductOrder['ClaimStatus'];
				$order_log['request_timestamp']	= $request_timestamp;

				$step			= $npay_status['step'];
				$deposit_yn		= $npay_status['deposit_yn'];
				$buyConfirm		= $npay_status['buyConfirm'];

				if($shop_status['refund_code']){
					$refund_group[$shop_status['refund_code']]['refund_cnt']++;									// 동일 환불의 item 갯 수
					$refund_group[$shop_status['refund_code']]['last_product_order_id'] = $npay_product_order_id;	// 수집된 마지막 상품주문번호(동일 환불 건 중)
				}

				# 상품별 판매액
				# 동일 주문건이 2건 이상 수집될 경우 결제금액 오류 발생됨에 따라
				# 상품주문번호 기준 최초 주문건에 대한 판매액만 +
				$order_shipping[$order_seq][$npay_product_order_id]['cnt']++;
				if($order_shipping[$order_seq][$npay_product_order_id]['cnt'] == 1){
					$order_shipping[$order_seq]['settleprice']		+= $ProductOrder['TotalPaymentAmount'];
				}

				//네이버페이 주문 상태가 상품준비 이하이거나 주문취소 이상(주문무효포함)인데 송장정보가 있는경우 출고생성 안함.
				//네이버페이 goodsflow 사용시 발생하는 현상.
				if(($step <= 35 || $step >= 85) && $Delivery){
					$is_pay_delivery				= 'y';
					$order_log['is_payed_shipping'] = 'y';
				}else{
					$is_pay_delivery = 'n';
				}
			// -----------------------------------------------------------------------------------
			// step1-5. 배송비, 배송지정보
				if(!$provider_seq) $provider_seq = 1;

				$ShippingAddress	= $ProductOrder['ShippingAddress'];
				if(!$order_use || $npay_is_receiver_changed){

					$shipping_address	= $this->get_shipping_address($scl,$secret,$ShippingAddress);
					extract($shipping_address);

					if(!$receiver_changed_order_seq || !in_array($order_seq,$receiver_changed_order_seq)){
						if($ShippingAddress['BaseAddress'] && is_object($recipient_address)){
							$order_log['result'] = 'false'; $order_log['message'][] = "배송지 복호화 실패";
						}
						if($ShippingAddress['DetailedAddress'] && is_object($recipient_address_detail)){
							$order_log['result'] = 'false'; $order_log['message'][] = "배송지 상세 복호화 실패";
						}
						if($ShippingAddress['Name'] && is_object($recipient_user_name)){
							$order_log['result'] = 'false'; $order_log['message'][] = "배송지명 복호화 실패";
						}
						if($ShippingAddress['Tel1'] && is_object($recipient_phone)){
							$order_log['result'] = 'false'; $order_log['message'][] = "배송 연락처1 복호화 실패";
						}
						if($ShippingAddress['Tel2'] && is_object($recipient_cellphone)){
							$order_log['result'] = 'false'; $order_log['message'][] = "배송 연락처2 복호화 실패";
						}
				}
				}
				$shipping_fee_type	= $ProductOrder['DeliveryPolicyType'];		//배송유형(무료,유료,조건부무료)
				$shipping_std_cost	= $ProductOrder['DeliveryFeeAmount'];		//기본배송비
				$shipping_add_cost	= $ProductOrder['SectionDeliveryFee'];		//추가배송비

				if(in_array($ProductOrder['ShippingFeeType'], array("선불", "선결제"))){
					$shipping_paytype	= "prepay";			//배송비결제방법
				}elseif($ProductOrder['ShippingFeeType'] == "착불"){
					$postpaid			= $shipping_std_cost;	//착불배송비
					$shipping_paytype	= "postpaid";				//배송비결제방법
				}else{
					$postpaid			= '0';
					$shipping_paytype	= "free";			//배송비결제방법
				}
				if($ProductOrder['DeliveryPolicyType'] == "유료"){
				}
				//총 배송비(기본배송비 + 도서산간 추가배송비)
				if($user_shipping_method != "postpaid"){
					$shipping_cost = $shipping_std_cost + $shipping_add_cost;
				}else{
					$shipping_cost = 0;
				}
				//배송 메모
				$delivery_memo = $ProductOrder['ShippingMemo']? $ProductOrder['ShippingMemo'] : "";

				//결제 수단(string)
				if(preg_match( '/신용카드/',$Order['PaymentMeans'])){
					$PaymentMeans	= 'card';
				}elseif(preg_match( '/계좌이체/',$Order['PaymentMeans'])){
					$PaymentMeans	= 'account';
				}elseif(preg_match( '/무통장/',$Order['PaymentMeans'])){
					$PaymentMeans	= 'virtual';		//가상계좌로
				}elseif(preg_match( '/휴대폰/',$Order['PaymentMeans'])){
					$PaymentMeans	= 'cellphone';
				}elseif(preg_match( '/계좌/',$Order['PaymentMeans'])){
					$PaymentMeans	= 'account';
				}elseif(preg_match( '/포인트/',$Order['PaymentMeans'])){
					$PaymentMeans	= 'point';
				}elseif(preg_match( '/나중에결제/',$Order['PaymentMeans'])){ // 나중에결제는 bank 로 insert/update
					$PaymentMeans	= 'bank';
				}elseif(preg_match( '/후불결제/',$Order['PaymentMeans'])){
					$PaymentMeans	= 'pay_later';
				}else{
					$PaymentMeans	= 'bank';
				}

				//결제금액
				$settleprice = $Order['ChargeAmountPaymentAmount']
								+$Order['CheckoutAccumulationPaymentAmount']
								+$Order['GeneralPaymentAmount']
								+$Order['NaverMileagePaymentAmount'];
				$order_shipping[$order_seq]['npayAmount'] = $settleprice;		//npay총 결제금액

				if(!in_array($npay_packgenumber,$arr_packagenumber[$order_seq])){
					if($shipping_paytype != "postpaid") {		//착불이 아닌 경우에 settleprice에 배송비 추가 2018-08-07
						// settleprice 배송비 더한 금액으로 계산
						$order_shipping[$order_seq]['settleprice'] += $ProductOrder['DeliveryFeeAmount'];
						$order_shipping[$order_seq]['settleprice'] += $ProductOrder['SectionDeliveryFee'];
					}
				} 
				$arr_packagenumber[$order_seq][] = $npay_packgenumber;
			// -----------------------------------------------------------------------------------
			// step2. 주문서 작성
			if(!$order_use && $order_log['result'] == 'true'){
			// -----------------------------------------------------------------------------------
			// step2-1. 주문내용 정리

				//판매환경
				if($Order['PayLocationType'] == "MOBILE"){
					$sitetype = "M";
				}else{
					$sitetype = "P";
				}
				//복호화대상 - 주문자 아이디,주문자 이름,주문자 연락처 1, 개인통관부호
				$npay_orderid	= $scl->decrypt($secret,$Order['OrdererID']);
				$npay_username	= $scl->decrypt($secret,$Order['OrdererName']);
				if($Order['OrdererTel1']) {
					$npay_ordertel	= $scl->decrypt($secret,$Order['OrdererTel1']);
				}else{
					$npay_ordertel	= '';
				}
				if($ProductOrder['IndividualCustomUniqueCode']) $npay_uniquecode = $scl->decrypt($secret,$ProductOrder['IndividualCustomUniqueCode']);

				if(is_object($npay_orderid)){
					$npay_orderid = ""; $order_log['result'] = 'false'; $order_log['message'][] = "사용자 ID 복호화 실패";
				}
				if(is_object($npay_username)){
					$npay_username = ""; $order_log['result'] = 'false'; $order_log['message'][] = "사용자 이름 복호화 실패";
				}
				if(is_object($npay_ordertel)){
					$npay_ordertel = ""; $order_log['result'] = 'false'; $order_log['message'][] = "사용자 연락처 복호화 실패";
				}
				if(is_object($npay_uniquecode)){
					$npay_uniquecode = ""; $order_log['result'] = 'false'; $order_log['message'][] = "개인통관부호 복호화 실패";
				}

				//주문일시
				$npay_orderdt		= $this->getGMTToLocaltime($Order['OrderDate']);
				//npay 결제일시
				if($Order['PaymentDate']) $npay_paydt = $this->getGMTToLocaltime($Order['PaymentDate']);
				//결제 일시-통계를 위해 수집일자넣기
				$deposit_date		= date("Y-m-d H:i:s",mktime());
				//PG 승인 번호
				$pa_number		= $Order['PaymentNumber']? $Order['PaymentNumber']: "";
				//유입경로(검색광고(SA)/지식쇼핑/지식쇼핑외
				if($ProductOrder['InflowPath'] == "네이버쇼핑 외"){
					$inflow = "naver";
				}
				//네이버페이 포인트 최종 결제 금액(사용한 네이버페이 포인트)
				if((int)$Order['NaverMileagePaymentAmount'] > 0){
					$npay_point_payment = $Order['NaverMileagePaymentAmount'];
				}else{
					$npay_point_payment = '0';
				}
				$npay_coupon_payment = '0';
				$npay_order_dc			= $Order['OrderDiscountAmount'];	//주문 할인액(주문을 부분 취소하면 이 값은 변경될 수 있다.)
				$npay_order_type		= $Order['OrderType'];				//주문 유형 구분(네이버페이/통합장바구니)
				$payment_type			= $Order['PaymentCoreType'];			//결제 구분(네이버결제/PG결제)
				if($Order['IsDeliveryMemoParticularInput'] == "true"){
					$delivery_memo = $npay_product_order_id."(Npay상품주문번호): ".$delivery_memo;
				}
			// -----------------------------------------------------------------------------------
			// step2-2. 주문서 fm_order insert
				$order_params = array();
				$order_params['order_seq']			= $order_seq;
				$order_params['mode']				= $distribution;
				$order_params['npay_order_id']		= $npay_order_id;
				$order_params['npay_orderer_id']	= $npay_orderid;
				$order_params['order_user_name']	= $npay_username;
				$order_params['order_phone']		= $npay_ordertel;
				$order_params['npay_order_date']	= $npay_orderdt;
				if($npay_paydt) $order_params['npay_order_pay_date']	= $npay_paydt;
				$order_params['npay_point']			= $npay_point_payment;
				$order_params['npay_server_info']	= $npay_server_info;
				$order_params['step']				= $step;						//주문상태
				$order_params['deposit_yn']			= $deposit_yn;
				$order_params['deposit_date']			= $deposit_date;
				$order_params['settleprice']		= $settleprice;				//일반 결제 수단 최종 결제 금액
				$order_params['payment_price']		= $settleprice;				//결제통화
				$order_params['pg_transaction_number']	= $pa_number;
				$order_params['pg']					= 'npay';
				$order_params['payment_type']		= $payment_type;
				$order_params['payment']			= $PaymentMeans;			//결제수단
				$order_params['shipping_method']	= $user_shipping_method;
				$order_params['shipping_cost']		= $shipping_cost;
				$order_params['sitetype']			= $sitetype;				//판매환경
				$order_params['memo']				= $delivery_memo;
				$order_params['international']		= $international;
				$order_params['recipient_user_name']= $recipient_user_name;
				$order_params['recipient_phone']	= $recipient_phone;
				$order_params['recipient_cellphone']= $recipient_cellphone;
				$order_params['recipient_zipcode']	= $recipient_zipcode;
				$order_params['recipient_address']	= $recipient_address;
				$order_params['recipient_address_street']= $recipient_address_street;
				$order_params['recipient_address_detail']= $recipient_address_detail;
				$order_params['recipient_address_type'] = $recipient_address_type;
				$order_params['international']		= $international;
				$order_params['postpaid']			= $postpaid;
				$order_params['referer']			= $referer;					//유입경로 full url
				$order_params['referer_domain']		= $referer_domain;			//유입경로 domain
				$order_params['marketplace']		= $marketplace;				//입점마케팅EP 2020-06-17 
				$order_params['regist_date']		= date("Y-m-d H:i:s");		//주문일시
				$order_params['clearance_unique_personal_code']= $npay_uniquecode;		//개인통관부호

				$res = $this->db->insert('fm_order', $order_params);
				
				// 네이버페이 API 로 수집될 때 referer이 유지되는 현상을 해결하기 위해 변수 초기화 및 통계를 위한 배열화 by hed #24744
				$arr_referers[$order_seq]			= $referer;		// 통계용 유입경로 배열 
				$arr_referer_domains[$order_seq]	= $referer_domain;		// 통계용 유입경로 배열 
				unset($referer);
				unset($referer_domain);

				if($res){
					$order_log['order_insert']			= true;
					$order_log['message'][] = "fm_order insert success";

					// 나중에결제인 경우 메모에 추가
					if($Order['PaymentMeans'] == "나중에결제") {
						$logParams	= array();
						$logTitle	= "결제수단-나중에결제(API)";
						$logDetail	= "네이버페이 결제수단은 나중에결제입니다.";

						$this->ordermodel->set_log($order_seq,'pay','Npay',$logTitle,$logDetail,$logParams,'','npay');
					}
				}else{
					$order_log['message'][] = "fm_order insert fail(".$this->db->last_query().")";
				}
			}else{

				$order_log['message'][] = "fm_order 주문서 존재함";
				// step2-2-1. 수정된 배송지 정보 업데이트 fm_order, fm_order_shipping
				if($npay_is_receiver_changed && !in_array($order_seq,$receiver_changed_order_seq)){
					$order_params = array();
					$order_params['international']				= $international;
					$order_params['recipient_user_name']		= $recipient_user_name;
					$order_params['recipient_phone']			= $recipient_phone;
					$order_params['recipient_cellphone']		= $recipient_cellphone;
					$order_params['recipient_zipcode']			= $recipient_zipcode;
					$order_params['recipient_address']			= $recipient_address;
					$order_params['recipient_address_street']	= $recipient_address_street;
					$order_params['recipient_address_detail']	= $recipient_address_detail;
					$order_params['recipient_address_type']		= $recipient_address_type;

					$receiver_changed_list[$order_seq]			= $order_params;
					$order_log['message'][]						= "fm_order 배송지 변경";
					$receiver_changed_order_seq[]				= $order_seq;
				}
				if($npay_is_receiver_changed) {
					if(trim($ProductOrder['ShippingMemo'])){
						$order_params = array();
						if($memo){ $memo .= "\n"; }
						if($Order['IsDeliveryMemoParticularInput'] == "true"){
							$order_params['memo']	= $memo."[수정]".$npay_product_order_id."(Npay상품주문번호): ".$ProductOrder['ShippingMemo'];
							$shippingmemo_product_order_id[] = $npay_product_order_id;
						}else{
							$order_params['memo']	= "[수정]".$npay_product_order_id.":".$ProductOrder['ShippingMemo'];
						}

						$this->db->where('order_seq',$order_seq);
						$res = $this->db->update('fm_order',$order_params);
						
						$order_log['message'][]					= "fm_order 배송 메모 변경(".$memo.")";
					}
				}

				// 기존 결제수단(payment) 가 bank 이고 현재결제수단(PaymentMeans) 다를 때에는 fm_order update 및 log 추가 2019-12-12
				if($payment=="bank" && $payment != $PaymentMeans) {
					$order_params = array();

					$order_params['payment']	= $PaymentMeans;
					$this->db->where('order_seq',$order_seq);
					$res = $this->db->update('fm_order',$order_params);

					$logTitle	= "결제수단 변경(API)";
					$logDetail	= "나중에결제 결제수단이 변경되었습니다.";

					$this->ordermodel->set_log($order_seq,'pay','Npay',$logTitle,$logDetail,$logParams,'','npay');
				}
			}
			// -----------------------------------------------------------------------------------
			// step2-3. 배송정책 저장
			if($order_log['result'] == 'true'){

				// -----------------------------------------------------------------------------------
				if(!$opt_sales && !$shipping_group){
					//주문시 적용된 할인 정책
					$_sales_where = array();
					$_sales_where[] = "session_tmp=?";
					$_sales_where[] = "partner_order_pk=?";
					
					$_sales_params = array();
					$_sales_params[] = $MerchantProductId[0];
					$_sales_params[] = $npay_product_order_id;

					list($return_sale) = $this->get_order_sales($_sales_where,$_sales_params,'get_order_sales');

					$opt_sales['shipping_cfg'] = $return_sale['shipping_cfg'];

					$shipping_group = $return_sale['shipping_group'];
				}
				# ----------------------------------------------------------------------------------------
				# 개선된 배송정책 로그 저장
				if($opt_sales['shipping_cfg']){
					$shipping_cfg = unserialize($opt_sales['shipping_cfg']);
				}

				# 실제 배송 업체 seq 가져오기 2018/05/28 pjm
				$_shipping_group		= explode("_",$shipping_group);
				$shipping_group_seq		= $_shipping_group[0];
				$shipping_provider_seq	= $this->get_shipping_provider_seq($shipping_group_seq);
				#--------------------------------------------------------------------------------------------

				//주문 배송정보 데이터가 없을 떄.
				if($shipping_seq){
					$order_log['message'][] = "fm_order_shipping 존재함 (".$shipping_seq.")";
				}else{

					$cnt_shipping++;

					$insert_params = array();

					$insert_params['delivery_cost']		= $shipping_std_cost;	//기본배송비
					$insert_params['add_delivery_cost'] = $shipping_add_cost;	//추가배송비
					$insert_params['hop_delivery_cost'] = 0;					//희망배송비
					$insert_params['postpaid']			= $postpaid;			//착불배송비

					# 매장수령
					if($shipping_cfg['baserule']['shipping_set_code'] == 'direct_store'){
						$insert_params['store_scm_type']		= $shipping_cfg['store_info']['store_scm_type'];
						$insert_params['shipping_address_seq']	= $shipping_cfg['store_info']['shipping_address_seq'];
					}
					// 예약상품배송일
					if($opt_sales['reserve_date']) {
						$insert_params['reserve_sdate']			= $opt_sales['reserve_date'];
						$reservation_ship						= 'y';		//예약발송여부
					}

					if	($insert_params['add_delivery_cost'])
					{
						$address_tmp = ($recipient_address) ? $recipient_address : $recipient_address_street;
						$add_arr_tmp = explode(' ',$address_tmp);
						$insert_params['add_delivery_area'] = $add_arr_tmp[0] . ' ' . $add_arr_tmp[1] . ' ' . $add_arr_tmp[2];
					}

					// 주문당시 배송 설정명 저장 :: 2016-09-23 lwh
					$insert_params['shipping_set_name']	= $shipping_cfg['baserule']['shipping_set_name'];

					$shipping_group_seq = $shipping_cfg['baserule']['shipping_group_seq'];

					// shipping 로그 등록
					$query = $this->db->query("select * from fm_order_shipping_log where order_seq=? and shipping_group_seq=?",array($order_seq,$shipping_group_seq));
					$chk_shipping_log = $query->row_array();
					if(!$chk_shipping_log){
						$log_seq = $this->shippingmodel->set_shipping_log($order_seq,array("cfg"=>$shipping_cfg));
					}
					# ----------------------------------------------------------------------------------------

					$data = use_shipping_method($shipping_provider_seq);
					$data = $data[0][0];

					$insert_params['order_seq'] 			= $order_seq;
					$insert_params['provider_seq']			= $shipping_provider_seq; // 업체배송이면 입점사의 코드, 본사배송이면 본사의 코드 1

					//조건부무료
					if($shipping_fee_type == "조건부무료"){
						$insert_params['delivery_if']		= $shipping_cfg['std'][1]['section_st'];	//무료배송 조건금액
					}

					//개별배송비
					if(strstr($shipping_method,'each')){
						$goods_shipping_cost	= $shipping_std_cost;
						$shipping_policy		= 'goods';
						$basic_shipping_cost	= '0';
						$add_shipping_cost		= '0';
						$shipping_cost			= $shipping_add_cost;
						$insert_params['delivery_if']= '';
					}else{
						$goods_shipping_cost	= '0';
						$shipping_policy		= 'shop';
						$basic_shipping_cost	= '0';
						$add_shipping_cost		= $shipping_add_cost;
						//총 배송비(기본배송비 + 도서산간 추가배송비)
						if($user_shipping_method != "postpaid"){
						    $shipping_cost			= $shipping_std_cost + $shipping_add_cost;
						}else{
						    $shipping_cost = 0;
						}
					}
					$insert_params['shipping_cost']		= $shipping_cost;	//배송비합계(변동될수도 있음)
					$insert_params['shipping_method']	= $shipping_cfg['baserule']['shipping_set_code'];
					$insert_params['shipping_group']	= $shipping_group;
					$insert_params['npay_packgenumber']	= $npay_packgenumber;
					$insert_params['shipping_type']		= $shipping_paytype;		//배송비결제방법

					$res = $this->db->insert('fm_order_shipping', $insert_params);
					if($res){
						$shipping_seq = $this->db->insert_id();
						$order_log['message'][] = "fm_order_shipping insert success (".$shipping_seq.")";
						/**
						* 정산개선 - 배송처리 : 순서변경주의 시작
						* data : 주문정보
						* insert_params : 배송정보
						* @ accountallmodel
						**/
						$insert_params['order_form_seq']			= $shipping_seq;
						$insert_params['shipping_seq']				= $shipping_seq;//배송비할인쿠폰
						$insert_params['shipping_charge']			= $return_sale['OPT'][$option_seq]['shipping_charge'];
						$insert_params['return_shipping_charge']	= $return_sale['OPT'][$option_seq]['return_shipping_charge'];
						$insert_params['accountallmodeltest']		= "accountallmodeltest_ship";
						$account_ins_shipping[$shipping_seq] = array_merge($insert_params);
						/**
						* 정산개선 - 배송처리 : 순서변경주의 끝
						* data : 주문정보
						* insert_params : 배송정보
						* @
						**/
					}else{
						$order_log['message'][] = "fm_order_shipping insert fail (".$this->db->last_query().")";
					}
				}
			}
			// -----------------------------------------------------------------------------------
			// step2-4. 주문 상품 저장
			if($order_log['result'] == 'true'){

				if($item_seq){
					$order_log['message'][] = "fm_order_item 주문상품 존재함 (".$item_seq.")";

				}else{

					$query = $this->db->query("select image from fm_goods_image where goods_seq=? and image_type='thumbView' order by cut_number limit 1",$goods_seq);
					$goods_image = $query->row_array();

					$item_params = array();
					$item_params['order_seq']		= $order_seq;
					$item_params['goods_seq']		= $goods_seq;
					$item_params['image']			= $goods_image['image'];
					$item_params['goods_code']		= $item_goods_code[$goods_seq];
					$item_params['goods_name']		= $goods_name;
					$item_params['provider_seq']	= $provider_seq;
					$item_params['shipping_seq']	= $shipping_seq;
					$item_params['goods_shipping_cost']	= ($goods_shipping_cost)? $goods_shipping_cost : '0' ;
					$item_params['shipping_policy']		= ($shipping_policy)? $shipping_policy : '0';
					$item_params['basic_shipping_cost']	= ($basic_shipping_cost)? $basic_shipping_cost: '0';
					$item_params['add_shipping_cost']	= ($add_shipping_cost)? $add_shipping_cost: '0';
					$item_params['npay_order_id']	= $npay_order_id;
					$item_params['event_seq']		= $opt_sales['event_seq'];		// event_seq 추가 2018-01-10
					$item_params['tax']				= (!$opt_sales['tax'])? $goods_tax:$opt_sales['tax'];	// 과세여부 추가 :: 2018-03-14 lkh
					$item_params['referer_domain']	= $arr_referer_domains[$order_seq];		// 입점마케팅EP 2020-06-17
					$item_params['bs_seq'] 			= $opt_sales['bs_seq'];
					$item_params['bs_type'] 		= $opt_sales['bs_type'];
					$item_params['hscode'] 			= $goods_info['hscode'];

					$res = $this->db->insert('fm_order_item', $item_params);
					if($res){
						$item_seq = $this->db->insert_id();
						$order_log['message'][] = "fm_order_item insert success (".$item_seq.")";
					}else{
						$order_log['message'][] = "fm_order_item insert fail (".$this->db->last_query().")";
					}

				}
			}
			// -----------------------------------------------------------------------------------
			// step2-5. 주문 상품 옵션
			if($order_log['result'] == 'true'){

				if($npay_opt_type != "추가구성상품" ){

					if(!$item_option_seq){

						// 판매가 - 옵션별 할인금액(shop) - npay할인금액
						// 정산, 매출 통계 : 판매사 할인가만 적용할 것.
						// OptionPrice							: 옵션 금액
						// TotalProductAmount					: 상품 주문 금액(할인 적용 전 금액) - 수량 * 된 금액
						// TotalPaymentAmount					: 총 결제금액(할인 적용 후 금액)
						// npay 할인 >>
						// ProductProductDiscountAmount			: 상품별 상품 할인 쿠폰 금액
						// FirstSellerBurdenDiscountAmount		: 판매자 부담 할인액(주문당시 판매자부담할인액)
						// SellerBurdenDiscountAmount			: 판매자 부담 할인액
								//(클레임 처리가 완료된 후 재계산된 판매자부담 할인액 (클레임이 없었다면 최초 금액과 동일))
						// SellerBurdenProductDiscountAmount	: 판매자 부담 상품 할인 쿠폰 금액

						// ProductImmediateDiscountAmount		: 상품별 즉시 할인금액(npay 제공안함)
						// SellerBurdenImmediateDiscountAmount	: 판매자 부담 즉시 할인 금액(npay 제공안함)
						// 주문시 넘긴 옵션별 판매가.
						//$price		= $ProductOrder['UnitPrice'] - abs($ProductOrder['OptionPrice']);

						# 옵션 패키지 상품인지 확인
						$option_package_yn = "n";
						if($goods_info['package_yn'] == 'y') $option_package_yn = "y";
						
						// 이벤트, 복수구매할인이 미리 적용되어있으므로 제거 :: 2018-07-18 pjw
						$opt_sales_sum = 0; //$opt_sales['event_sale']+$opt_sales['multi_sale'];

						$goods_code = $return_sale['OPT'][$option_seq]['goods_code'];
						if($return_sale['OPT'][$option_seq]['optioncode1']) $goods_code .= $return_sale['OPT'][$option_seq]['optioncode1'];
						if($return_sale['OPT'][$option_seq]['optioncode2']) $goods_code .= $return_sale['OPT'][$option_seq]['optioncode2'];
						if($return_sale['OPT'][$option_seq]['optioncode3']) $goods_code .= $return_sale['OPT'][$option_seq]['optioncode3'];
						if($return_sale['OPT'][$option_seq]['optioncode4']) $goods_code .= $return_sale['OPT'][$option_seq]['optioncode4'];
						if($return_sale['OPT'][$option_seq]['optioncode5']) $goods_code .= $return_sale['OPT'][$option_seq]['optioncode5'];

						if(!$goods_code) $goods_code = "";

						$opt_params = $inp_list = array();
						$opt_params['order_seq'] 			= $order_seq;
						$opt_params['item_seq'] 			= $item_seq;
						$opt_params['step'] 				= $step;
						$opt_params['goods_code']			= $goods_code;
						$opt_params['optioncode1']			= $return_sale['OPT'][$option_seq]['optioncode1'];
						$opt_params['optioncode2']			= $return_sale['OPT'][$option_seq]['optioncode2'];
						$opt_params['optioncode3']			= $return_sale['OPT'][$option_seq]['optioncode3'];
						$opt_params['optioncode4']			= $return_sale['OPT'][$option_seq]['optioncode4'];
						$opt_params['optioncode5']			= $return_sale['OPT'][$option_seq]['optioncode5'];

						$opt_params['price'] 				= ($opt_sales['price']-$opt_sales_sum);
						$opt_params['ori_price'] 			= ($opt_sales['price']-$opt_sales_sum);
						$opt_params['sale_price'] 			= ($opt_sales['discount_price']);		//할인적용된 개당 금액
						$opt_params['ea'] 					= $order_ea;
						$opt_params['provider_seq']			= $provider_seq;
						$opt_params['shipping_seq']			= $shipping_seq;
						$opt_params['package_yn']			= $option_package_yn;
						$opt_params['npay_order_id']		= $npay_order_id;
						$opt_params['npay_product_order_id']= $npay_product_order_id;
						$opt_params['npay_packgenumber']	= $npay_packgenumber;
						//결제상태인데 배송정보 넘어온경우(goodsflow)
						$opt_params['npay_pay_delivery']	= $is_pay_delivery;
						# Npay측 쿠폰할인액
						if($npay_order_dc && !$npay_sale_npay[$order_seq]){
							$opt_params['npay_sale_npay']	= $npay_order_dc;
							$npay_sale_npay[$order_seq] = true;
						}

						$option_product[$npay_order_id][$option_seq] = $npay_product_order_id;
						// -----------------------------------------------------------------------------------
						// shop 할인 리스트
						$opt_params['basic_sale']	= ($opt_sales['basic_sale'])? $opt_sales['basic_sale']:'0';
						$opt_params['multi_sale']	= ($opt_sales['multi_sale'])? $opt_sales['multi_sale'] :'0';
						$opt_params['event_sale']	= ($opt_sales['event_sale'])? $opt_sales['event_sale'] :'0';
						$opt_params['fblike_sale']	= ($opt_sales['like_sale'])? $opt_sales['like_sale']:'0';
						$opt_params['mobile_sale']	= ($opt_sales['mobile_sale'])? $opt_sales['mobile_sale']:'0';
						$opt_params['member_sale']	= ($opt_sales['member_sale'])? $opt_sales['member_sale']:'0';
						$opt_params['referer_sale']	= ($opt_sales['referer_sale'])? $opt_sales['referer_sale']:'0';
						$opt_params['mobile_sale_unit']	= ($opt_sales['mobile_sale_unit'])? $opt_sales['mobile_sale_unit']:'0';
						$opt_params['referer_sale_unit'] = ($opt_sales['referer_sale_unit'])? $opt_sales['referer_sale_unit']:'0';

						# npay 쿠폰할인(네이버페이 부담=상품별 할인액-판매자 부담 할인액)
						$npay_sale_npay		= $npay_product_dc - $npay_seller_dc;

						# npay 할인(배송비 할인 + 상품별 할인 - 네이버페이 부담 상품할인액)
						$npay_sale_seller	= $npay_delivery_dc+$npay_product_dc-$npay_sale_npay;

						$opt_params['npay_sale_npay']	= $npay_sale_npay;
						$opt_params['npay_sale_seller']	= $npay_sale_seller;


						//판매가에 주문시 넘긴 할인 포함 적용.
						/*$opt_params['price'] = $price + (($opt_sales['fblike_sale']/$ProductOrder['Quantity'])
											+($opt_sales['mobile_sale']/$ProductOrder['Quantity'])
											+($opt_sales['referer_sale']/$ProductOrder['Quantity']))
											+$opt_sales['member_sale'];
						*/
						$opt_params['consumer_price']	= ($opt_sales['consumer_price'])? $opt_sales['consumer_price']:'0';
						$opt_params['supply_price']		= ($opt_sales['supply_price'])? $opt_sales['supply_price']:'0';
						$opt_params['commission_price']	= $opt_sales['commission_price'];

						if($ProductOrder['ProductOption']){

							//옵션명, 선택옵션 정리.
							$optionData = null;
							$query = $this->db->query("select a.*, b.supply_price from fm_goods_option a left join fm_goods_supply b on (a.goods_seq=b.goods_seq and a.option_seq=b.option_seq) where a.goods_seq=? and ifnull(a.fix_option_seq,a.option_seq)=?",array($goods_seq,$option_seq));
							$optionData = $query->row_array();

							// 옵션과 옵션구분 ( / ), 옵션타이틀과 옵션값 구분 (: )
							$titles = explode(",",$optionData['option_title']);

							$ProductOption = explode(" / ",$ProductOrder['ProductOption']);
							$j = 1;

							$input_option = unserialize($opt_sales['input_option']);

							foreach($ProductOption as $kk=>$opt){

								//입력옵션과 필수옵션이 명확히 구분 안됨. (옵션타이틀명: 옵션값)
								$opt_tmp = explode(": ",$opt);

								$opt_type = "";
								$inp_type = "";
								foreach($input_option as $inp_data){
									if(trim($inp_data['title']) == trim($opt_tmp[0])){
										$opt_type = "input";
										$inp_type = $inp_data['type'];
									}
								}
								if($opt_type == "input"){
									$inp_params['type']		= trim($inp_type);
									$inp_params['title']	= trim($opt_tmp[0]);
									$inp_params['value']	= trim($opt_tmp[1]);
									$inp_list[]				= $inp_params;
								}else{
									$opt_params['title'.$j]	= trim($opt_tmp[0]);
									$opt_params['option'.$j]= trim($opt_tmp[1]);
									$j++;
								}

							}

						}else{
							$opt_params['title1'] 		= '';
							$opt_params['option1'] 		= '';
						}

						for($i=1;$i<=5;$i++){
							if(is_null($opt_params['title'.$i])) $opt_params['title'.$i]	= '';
							if(is_null($opt_params['option'.$i])) $opt_params['option'.$i]	= '';
						}

						$res = $this->db->insert('fm_order_item_option', $opt_params);
						if($res){
							$item_option_seq = $this->db->insert_id(); //초기화 하면 안됨.
							$order_log['message'][] = "fm_order_item_option insert success (".$item_option_seq.")";
							/**
							* 정산개선 - 옵션처리 시작
							* data : 주문정보
							* insert_params : 필수옵션정보
							* @ accountallmodel
							**/
							$opt_params['commission_rate']			= $return_sale['OPT'][$option_seq]['commission_rate'];
							$opt_params['commission_type']			= $return_sale['OPT'][$option_seq]['commission_type'];
							$opt_params['item_option_seq']			= $item_option_seq;
							$opt_params['order_form_seq']			= $item_option_seq;
							$opt_params['shipping_seq']				= $shipping_seq;
							$opt_params['multi_sale_provider']		= ($provider_seq != 1)?100:0;//해당상품이 입점사상품이면 입점사부담율 100%/본사라면 0
							$opt_params['event_sale_provider']		= $return_sale['OPT'][$option_seq]['salescost_provider'];			// 입점사 이벤트 할인 부담"율"
							$opt_params['coupon_sale_provider']		= $return_sale['OPT'][$option_seq]['salescost_provider_coupon'];	// 입점사 쿠폰 할인 부담"액"
							$opt_params['referer_sale_provider']	= $return_sale['OPT'][$option_seq]['salescost_provider_referer'];	// 입점사 유입경로 할인 부담"액"

							$opt_params['multi_sale_unit']			= $opt_sales['multi_sale'] / $order_ea;		// 정산 대량구매할인 개당 할인금액 2020-01-09 

							$opt_params['accountallmodeltest']		= "accountallmodeltest_opt";
							$account_ins_opt[$item_option_seq] = array_merge($opt_params,$return_sale['OPT'][$option_seq]);
							/**
							* 정산개선 - 옵션처리 끝
							* data : 주문정보
							* insert_params : 필수옵션정보
							* @
							**/
						}else{
							$order_log['message'][] = "fm_order_item_option insert fail (".$this->db->last_query().")";
						}

						//입력옵션
						if(count($inp_list) > 0){
							foreach($inp_list as $inp_data){

								$inp_params						= array();
								$inp_params['item_option_seq']	= $item_option_seq;
								$inp_params['order_seq']		= $order_seq;
								$inp_params['item_seq']			= $item_seq;
								$inp_params['type']				= $inp_data['type'];
								$inp_params['title']			= $inp_data['title'];
								$inp_params['value']			= $inp_data['value'];
								$res = $this->db->insert('fm_order_item_input', $inp_params);
								if($res){
									$item_inp_seq = $this->db->insert_id(); //초기화 하면 안됨.
									$order_log['message'][] = "fm_order_item_input insert success (".$item_inp_seq.")";
								}else{
									$order_log['message'][] = "fm_order_item_input insert fail (".$this->db->last_query().")";
								}
							}
						}


						# 배송메모 저장.
						if($Order['IsDeliveryMemoParticularInput'] == "true" && !in_array($npay_product_order_id,$shippingmemo_product_order_id)){

							if(trim($ProductOrder['ShippingMemo'])){
								if($memo){ $memo .= "\n"; }
								$order_params = array("memo" => $memo.$npay_product_order_id."(Npay상품주문번호): ".$ProductOrder['ShippingMemo']);
								
								$this->db->where('order_seq',$order_seq);
								$res = $this->db->update('fm_order',$order_params);
							}
						}

					}else{

						if($is_pay_delivery == 'y'){
							$query = "update fm_order_item_option set npay_pay_delivery='".$is_pay_delivery."' where item_option_seq=?";
							$this->db->query($query,array($item_option_seq));
						}

						$order_log['message'][] = "fm_order_item_option 주문상품 옵션 존재함 (".$item_option_seq.")";
					}
				}
			}
			// -----------------------------------------------------------------------------------
			// step2-6. 주문 상품 추가옵션
			if($order_log['result'] == 'true'){

				if($npay_opt_type == "추가구성상품"){

					$order_log['option_type'] = "suboption";

					if($item_suboption_seq){

						if($is_pay_delivery == 'y'){
							$query = "update fm_order_item_suboption set npay_pay_delivery='".$is_pay_delivery."' where item_suboption_seq=?";
							$this->db->query($query,array($item_suboption_seq));
						}
						$order_log['message'][] = "fm_order_item_suboption 상품 추가옵션 존재함 (".$item_suboption_seq.")";
					}else{

						# 추가옵션이 연결상품인지 확인.
						$suboption_package_yn = "n";
						if($goods_info['package_yn_suboption'] == 'y') $suboption_package_yn = "y";

						# 추가옵션의 부모옵션정보 찾기
						$where = '';
						if($option_product){
							$where = " and npay_product_order_id='".$option_product[$npay_order_id][$option_seq]."'";
						}

						if(!$item_option_seq){
							$query = $this->db->query("select item_option_seq from fm_order_item_option where order_seq=? ".$where." limit 1",array($order_seq));
							$item_opt = $query->row_array();
							$item_option_seq = $item_opt['item_option_seq'];
						}

						$query = $this->db->query("select * from fm_goods_suboption where suboption_seq=?",array($suboption_seq));
						$data_suboptions = $query->row_array();

						$insert_params = array();
						$insert_params['order_seq']			= $order_seq;
						$insert_params['item_seq'] 			= $item_seq;
						$insert_params['item_option_seq'] 	= ($item_option_seq)? $item_option_seq: "";
						$insert_params['goods_code']		= $return_sale['SUB'][$suboption_seq]['goods_code'].$return_sale['SUB'][$suboption_seq]['optioncode1'];
						$insert_params['suboption_code']	= $return_sale['SUB'][$suboption_seq]['optioncode1'];
						$insert_params['step'] 				= $step;
						$insert_params['price'] 			= (int) $ProductOrder['UnitPrice'] + (int)$opt_sales['member_sale'];
						$insert_params['member_sale'] 		= "0";
						$insert_params['point']				= "0";
						$insert_params['reserve']			= "0";
						$insert_params['member_sale']		= $member_sale;
						$insert_params['consumer_price']	= ($opt_sales['consumer_price'])? $opt_sales['consumer_price']:'0';
						$insert_params['supply_price']		= ($opt_sales['supply_price'])? $opt_sales['supply_price']:'0';
						$insert_params['commission_price']	= $opt_sales['commission_price'];
						$insert_params['ea'] 				= $order_ea;
						# 추가옵션은 주문시 옵션 title명을 넘기지 않아 주문 당싱의 title명을 없음.
						$insert_params['title'] 			= ($data_suboptions['suboption_title'])?$data_suboptions['suboption_title']:'';
						$insert_params['suboption']			= $ProductOrder['ProductOption'];
						$insert_params['package_yn']		= $suboption_package_yn;
						$insert_params['npay_order_id']		= $npay_order_id;
						$insert_params['npay_product_order_id']	= $npay_product_order_id;
						$insert_params['npay_packgenumber']		= $npay_packgenumber;
						//결제상태인데 배송정보 넘어온경우(goodsflow)
						$insert_params['npay_pay_delivery']	= $is_pay_delivery;

						if($opt_sales){
							$insert_params['basic_sale']	= $opt_sales['basic_sale'];
							$insert_params['member_sale']	= $opt_sales['member_sale'];
						}

						# npay 할인(배송비 할인 + 상품별 할인) - 추가상품은 npay 할인없음.

						$res = $this->db->insert('fm_order_item_suboption', $insert_params);
						if($res){
							$item_suboption_seq = $this->db->insert_id(); //초기화 하면 안됨.
							$order_log['message'][] = "fm_order_item_suboption insert success (".$item_suboption_seq.")";
							/**
							* 정산개선 - 추가옵션처리 시작
							* data : 주문정보
							* insert_params : 추가옵션정보
							* @ accountallmodel
							**/
							$insert_params['item_suboption_seq']		= $item_suboption_seq;
							$insert_params['order_form_seq']			= $item_suboption_seq;
							$insert_params['provider_seq'] 				= $provider_seq;
							$insert_params['shipping_seq']				= $shipping_seq;
							$insert_params['accountallmodeltest']		= "accountallmodeltest_sub";
							$account_ins_subopt[$item_suboption_seq] = array_merge($insert_params,$return_sale['SUB'][$suboption_seq]);
							/**
							* 정산개선 - 추가옵션처리 끝
							* data : 주문정보
							* insert_params : 추가옵션정보
							* @accountallmodel
							**/
						}else{
							$order_log['message'][] = "fm_order_item_suboption insert fail (".$this->db->last_query().")";
						}
						if($item_suboption_seq){
							$cart_suboption_real_seq[$data_suboptions['cart_suboption_seq']] = array(
								'order_item_seq' => $item_seq,
								'order_item_suboption_seq' => $item_suboption_seq
							);
						}

						# 개별 배송메모 저장.
						if($Order['IsDeliveryMemoParticularInput'] == "true" && !in_array($npay_product_order_id,$shippingmemo_product_order_id)){

							if(trim($ProductOrder['ShippingMemo'])){
								if($memo){ $memo .= "\n"; }
								$order_params = array("memo" => $memo.$npay_product_order_id."(Npay상품주문번호): ".$ProductOrder['ShippingMemo']);
								
								$this->db->where('order_seq',$order_seq);
								$res = $this->db->update('fm_order',$order_params);
							}
						}
					}
				}
			}
			// 주문접수 -> 결제완료 Log
			if($order_log['result'] == 'true'){

				if($product_update['order'] == "upt"){

					if($old_step && $step == '25'){
							$order_log['step_update'] = "update(".$shop_status['step']." => 25)";
					}
				}
			}
			$result_log[] = $order_log;

			# 상품주문상태가 주문접수, 결제확인 상태가 아닐경우 별도 처리
			# 주문취소, 취소철회, 출고처리, 반품처리, 반품철회, 교환처리, 교환철회, 구매확정 처리(접수된 순서대로 일괄 처리)
			if(!in_array($order_log['npay_status'],array("PAYMENT_WAITING","PAYED")) || $order_log['npay_claimtype'] != ''){

				$order_log['item_seq']				= $item_seq;
					$order_log['item_option_seq']		= $item_option_seq;
				if($npay_opt_type == "추가구성상품"){
					$order_log['item_suboption_seq']	= $item_suboption_seq;
				}
				$order_log['order_ea']				= $order_ea;
				$order_log['provider_seq']			= $provider_seq;
				$order_log['shipping_provider_seq']	= $shipping_provider_seq;
				$order_log['shipping_seq']			= $shipping_seq;
				$order_log['shipping_group_seq']	= $shipping_group_seq;
				$order_log['international']			= $international;
				$order_log['shipping_method']		= $shipping_method;
				$order_log['secret']				= $secret;
				$order_log['product_data']			= $product_data;
				$process_log[]						= $order_log;

			}


		} //foreach

		##------------------------------------------------------------------------
		## 주문상태 변경, 출고처리, 반품/교환처리 등
		$result_log['order_shipping_cost'] = $order_shipping;

		##------------------------------------------------------------------------
		## 주문 수집 결과
		$this->naverpaylib->result_message_log("npay_send_order_result",$result_log);

		$this->load->model("statsmodel");
		$this->load->model('orderpackagemodel');
		$this->load->model("order2exportmodel");

		if(!$this->accountall)			$this->load->helper('accountall');
		if(!$this->accountallmodel)		$this->load->model('accountallmodel');
		if(!$this->providermodel)		$this->load->model('providermodel');
		##------------------------------------------------------------------------
		## 주문접수 로그
			$arr_npay_order_id			= array();
			$is_receiver_changed_saved	= false;	//배송 변경 저장 여부

			foreach($result_log as $idx => $data){

				$order_seq	= $data['order_seq'];

				//주문상태 변경(무통장 입금 수집 후 미입금 취소 or 결제확인 처리)
				if((int)$data['step'] > 15 && (int)$data['step'] != (int)$data['old_step']){

					$step_update_25 = false;

					if((int)$data['step'] == 95 && !$data['order_insert']){
						$this->ordermodel->set_step($order_seq,95);
						$logTitle	= "미입금취소(API)";
						$logDetail	= "Npay가 ".$logTitle." 신청을 하였습니다.";
						$this->ordermodel->set_log($order_seq,'pay','Npay',$logTitle,$logDetail,$logParams,'','npay');
					}elseif((int)$data['step'] == 25 && (int)$data['old_step'] < 25 && !$data['order_insert']){
						$this->ordermodel->set_step($order_seq,25);
						$logTitle	= "결제확인(API)([N]".$data['payment'].")";
						$logDetail	= "Npay가 ".$logTitle." 하였습니다.";
						$this->ordermodel->set_log($order_seq,'pay','Npay',$logTitle,$logDetail,$logParams,'','npay');

						//티켓상품 자동 출고처리구문 순차진행을 위해 분리함 @2017-08-16
						ticket_payexport_ck($order_seq);
											
						$step_update_25 = true;

					}elseif((int)$data['step'] == 85 && (int)$data['old_step'] <= 15){
						$step_update_25 = true;
						$data['step']	= 25;
						$this->ordermodel->set_step($order_seq,$data['step']);
						if(!$data['order_insert']){
							$logTitle	= "결제확인(API)([N]".$data['payment'].")";
							$logDetail	= "Npay가 ".$logTitle." 하였습니다.";
							$this->ordermodel->set_log($order_seq,'pay','Npay',$logTitle,$logDetail,$logParams,'','npay');
						}
					}

					if($step_update_25){
					
						/**
						* 2-1 결제확인시 임시매출데이타를 이용한 미정산매출데이타 시작
						* 정산개선 - 미정산매출데이타 처리
						* @ 
						**/
						$this->accountallmodel->insert_calculate_sales_order_deposit($order_seq);
						//debug_var($this->db->queries);
						//debug_var($this->db->query_times);
						/**
						* 2-1 결제확인시 임시매출데이타를 이용한 미정산매출데이타 끝
						* 정산개선 - 미정산매출데이타 처리
						* @ 
						**/
					}

					if((int)$data['step'] != 95 ){
						$this->ordermodel->set_order_step($order_seq);
					}
				}

				//결제금액 체크 @2017-03-21
				unset($shipping);
				if(!in_array($data['npay_order_id'],$arr_npay_order_id) && $data['order_insert']){

					$shipping	= $order_shipping[$order_seq];

					// 연결상품이 1개라도 있으면 패키지 상품 연결 생성
					if($data['package_yn'] == 'y'){
						$this->orderpackagemodel->package_order($order_seq);
					}

					if((int)$data['step'] == 15){
						$logTitle	= "주문접수(API)";
					}elseif((int)$data['step'] == 95){
						$logTitle	= "미입금취소(API)";
					}elseif((int)$data['step'] > 15 && (int)$data['step'] < 85){
						$logTitle	= "결제확인(API)([N]".$data['payment'].")";
					}else{
						$logTitle	= "주문접수(API)";
					}
					$logDetail	= "Npay가 ".$logTitle." 하였습니다.";
					$this->ordermodel->set_log($order_seq,'pay','Npay',$logTitle,$logDetail,$logParams,'','npay');

					#  주문 통계 저장 - 주문당시의 referer 값 전달되도록 수정 2018-08-02
					$refererArr = array();
					$refererArr['referer_domain']	= !($arr_referer_domains[$order_seq]) ? '' : $arr_referer_domains[$order_seq];
					$refererArr['referer']			= !($arr_referers[$order_seq]) ? '' : $arr_referers[$order_seq];
					$this->statsmodel->insert_order_stats($order_seq,$refererArr);

					// [판매지수 EP] 쿠키로 ep 등록 처리된 주문건인지 확인 후 EP 수집 :: 2020-06-17 hyem
					$this->statsmodel->set_order_sale_ep($order_seq);

					/**
					* 2-1 결제확인시 임시매출데이타를 이용한 미정산매출데이타 시작
					* 정산개선 - 미정산매출데이타 처리
					* @ 
					**/
					if((int)$data['step'] > 15 && (int)$data['step'] < 85){ 
						//step1 주문금액별 정의/비율/단가계산 후 정렬
						$set_order_price_ratio = $this->accountallmodel->set_order_price_ratio($order_seq);

						if( $data['npay_point']>0 ) {//step2 npay_point update
							$this->accountallmodel->update_ratio_emoney_cash_enuri_npoint($order_seq, $set_order_price_ratio, 'npay_point');
						}
						//step3 임시 매출/정산 저장
						$this->accountallmodel->insert_calculate_sales_order_tmp($order_seq, $set_order_price_ratio, $account_ins_opt, $account_ins_subopt, $account_ins_shipping);
						//debug_var($this->db->queries);
						//debug_var($this->db->query_times);

						$this->accountallmodel->insert_calculate_sales_order_deposit($order_seq);
						//debug_var($this->db->queries);
						//debug_var($this->db->query_times);
					}elseif((int)$data['step'] != 95){
						//step1 주문금액별 정의/비율/단가계산 후 정렬
						$set_order_price_ratio = $this->accountallmodel->set_order_price_ratio($order_seq);

						if( $data['npay_point']>0 ) {//step2 npay_point update
							$this->accountallmodel->update_ratio_emoney_cash_enuri_npoint($order_seq, $set_order_price_ratio, 'npay_point');
						}
						//step3 임시 매출/정산 저장
						$this->accountallmodel->insert_calculate_sales_order_tmp($order_seq, $set_order_price_ratio, $account_ins_opt, $account_ins_subopt, $account_ins_shipping);
						//debug_var($this->db->queries);
						//debug_var($this->db->query_times);
					}
					/**
					* 2-1 결제확인시 임시매출데이타를 이용한 미정산매출데이타 끝
					* 정산개선 - 미정산매출데이타 처리
					* @ 
					**/
				}

				# npay 총 결제액이 상품별 결제액(배송비포함) 합계가 다를 경우 총 결제금액 업데이트
				# 주문 수집 전에 결제 취소 완료일때 총 결제금액에서 취소완료 금액 빠짐으로 인한 오류 발생)
				# 결제확인 상태에서 부분취소와 함께 수집시 결제금액이 변경되도록 처리 2018-03-06
				if($data['step'] >= 25 && $shipping && $shipping['npayAmount'] != $shipping['settleprice']){
					$sql = "update fm_order set settleprice=? where order_seq=?";
					$this->db->query($sql,array($shipping['settleprice'],$order_seq));
				}
				$arr_npay_order_id[] = $data['npay_order_id'];

				##------------------------------------------------------------------------
				## 배송지 변경
				if($receiver_changed_list[$order_seq] && !$is_receiver_changed_saved && $data['old_step'] < 55){
					$this->db->where('order_seq',$order_seq);
					$res = $this->db->update('fm_order',$receiver_changed_list[$order_seq]);

					if($res){
						$logTitle	= "배송지변경";
						$logDetail	= "NPay가 배송지변경 하였습니다.";
						$this->ordermodel->set_log($order_seq,'process','Npay',$logTitle,$logDetail,'','','npay');
					}
					$is_receiver_changed_saved =  true;
				}

				# 결제건 발주처리
				# 즉시 발주처리 하지 않을때 발생되는 문제점.
				#  => 고객이 주문 취소신청 후 취소건이 수집되기 전에 관리자가 출고처리를 하게되면
				#     취소 신청한 건이 무시(자동 취소철회)된 채로 출고처리 된다.
				#     단, 고객이 주문 취소시 취소차액을 모두 결제하여 취소완료가 된 경우는 제외.
				//if((int)$data['old_step'] < 25 && (int)$data['step'] == 25 ){
				if((int)$data['step'] == 25 && $data['npay_claimtype'] != "CANCEL" ){
					$this->order_ready($data['npay_product_order_id'],$data['order_seq']);
				}

				# Npay상품주문상태는 결제완료이나 송장정보가 있을 때 상품준비 처리.(Npay goodsflow 사용시)
				if($data['step'] == 25 && $data['is_payed_shipping'] == 'y'){
					$this->ordermodel->set_step35_ea($order_seq);
					$this->ordermodel->set_step($order_seq,35);
					$logTitle	= "상품준비(API)";
					$logDetail	= "Npay가 상품준비(네이버페이 발송대기 상태) 처리 하였습니다.";
					$this->ordermodel->set_log($order_seq,'pay','Npay',$logTitle,$logDetail,'','','npay');
				}

				// 주문 총주문수량 / 총상품종류 업데이트 leewh 2014-08-01
				$this->ordermodel->update_order_total_info($data['order_seq']);

			}
		$reserve_save		= !$this->cfg_order['buy_confirm_use'] ? true : false;
		$last_change_update = array();
		$buyconfirm_list	= array();

		$process_list		= array();
		$except_field		= array("shipping_seq","international","shipping_method","secret","product_data");
		##------------------------------------------------------------------------
		## 주문취소, 취소철회, 출고처리, 반품처리, 반품철회, 교환처리, 교환철회, 구매확정 처리
		foreach($process_log as $process_data){

			$order_seq					= trim($process_data['order_seq']);
			$npay_order_id				= trim($process_data['npay_order_id']);
			$npay_product_order_id		= trim($process_data['npay_product_order_id']);

			$goods_seq					= $process_data['goods_seq'];
			$npay_product_status		= $process_data['npay_status'];
			$npay_product_claim			= $process_data['npay_claimstatus'];

			$item_seq					= $process_data['item_seq'];
			$item_option_seq			= $process_data['item_option_seq'];
			$item_suboption_seq			= $process_data['item_suboption_seq'];
			$order_ea					= $process_data['order_ea'];
			$option_type				= $process_data['option_type'];
			$provider_seq				= $process_data['provider_seq'];
			$shipping_provider_seq		= $process_data['shipping_provider_seq'];	//실제 배송 업체

			$shipping_seq				= $process_data['shipping_seq'];
			$shipping_group_seq			= $process_data['shipping_group_seq'];
			$international				= $process_data['international'];
			$shipping_method			= $process_data['shipping_method'];		//주문시 선택한 배송방법
			$secret						= $process_data['secret'];

			$product_data				= $process_data['product_data'];

			if(!$provider_seq) $provider_seq = 1;

			// -----------------------------------------------------------------------------------
			// 주문번호 수집
			$arr_order_seq[]			= $order_seq;
			// -----------------------------------------------------------------------------------
			// step1-1. log create
				foreach($except_field as $field){
					unset($process_data[$field]);
				}
				$order_log				= array();
				$order_log				= $process_data;
			// -----------------------------------------------------------------------------------
			// Response Data
				$Order					= $product_data['Order'];					//주문
				$ProductOrder			= $product_data['ProductOrder'];			//주문상품

				$Delivery = $CancelInfo = $ReturnInfo = $ExchangeInfo = $DecisionHoldbackInfo = "";
				if($product_data['Delivery'])		$Delivery		= $product_data['Delivery'];		//배송 현황 정보

				if($product_data['CancelInfo'])		{
					$CancelInfo		= $product_data['CancelInfo'];		//취소 정보
					$CancelInfo['CancelDetailedReason'] = str_replace(array("\r\n","\r","\n"),' ',$CancelInfo['CancelDetailedReason']);//줄바꿈처리
				}
				if($product_data['ReturnInfo'])		{
					$ReturnInfo		= $product_data['ReturnInfo'];		//반품 정보
					$ReturnInfo['ReturnDetailedReason'] = str_replace(array("\r\n","\r","\n"),'',$ReturnInfo['ReturnDetailedReason']);//줄바꿈처리
				}

				if($product_data['ExchangeInfo'])	{
					$ExchangeInfo	= $product_data['ExchangeInfo'];	//교환 정보
					$ExchangeInfo['ExchangeDetailedReason'] = str_replace(array("\r\n","\r","\n"),'',$ExchangeInfo['ExchangeDetailedReason']);//줄바꿈처리
				}

				if($product_data['DecisionHoldbackInfo']) $DecisionHoldbackInfo= $product_data['DecisionHoldbackInfo'];	//구매 확정 보류 정보

				$product_claim_status = $ProductOrder['ClaimStatus'];

				if(!$shipping_method) $shipping_method	= "delivery";
				if(!$shipping_group) $shipping_group	= $shipping_method;
			// -----------------------------------------------------------------------------------
			// step1-2. 주문상태 및 기타 정보 정리
				## 주문, 취소, 반품, 교환 상태 정보 가져오기
				$ClaimArr = array('cancel'		=> $CancelInfo,
								  'return'		=> $ReturnInfo,
								  'exchange'	=> $ExchangeInfo,
								  'redelivery'	=> $DecisionHoldbackInfo
								);
				$product_update					= $this->get_product_update_use($ProductOrder,$ClaimArr,$Delivery);
				$shop_status					= $product_update['shop_detail'];
				$npay_status					= $product_update['npay_detail'];
				$order_log['old_step']			= $shop_status['step'];
				$order_log['step']				= $npay_status['step'];
				$order_log['npay_status']		= $ProductOrder['ProductOrderStatus'];
				$order_log['npay_claimtype']	= $ProductOrder['ClaimType'];
				$order_log['npay_claimstatus']	= $product_claim_status;
				$order_log['request_timestamp']	= $request_timestamp;

				$step							= $npay_status['step'];
				$deposit_yn						= $npay_status['deposit_yn'];
				$buyConfirm						= $npay_status['buyConfirm'];

				if($CancelInfo || $ReturnInfo){
					$_order_info = array('order_seq'=>$order_seq,'npay_product_order_id'=>$npay_product_order_id);
					$_claim_info = array('claim_code'=>$shop_status['refund_code'],'claim_seq'=>$shop_status['refund_item_seq']);
					list($shop_status['last_refund_item_yn'],$shop_status['refund_group_total_ea']) = $this->get_last_claim_item_yn('refund',$_order_info,$_claim_info,$refund_group[$shop_status['refund_code']]);

					if($ReturnInfo){
						$_claim_info = array('claim_code'=>$shop_status['return_code'],'claim_seq'=>$shop_status['return_item_seq']);
						list($shop_status['last_return_item_yn'],$shop_status['return_group_total_ea']) = $this->get_last_claim_item_yn('return',$_order_info,$_claim_info,$refund_group[$shop_status['return_code']]);
					}
				}

				//네이버페이 주문 상태가 상품준비 이하이거나 주문취소 이상(주문무효포함)인데 송장정보가 있는경우 출고생성 안함.
				//네이버페이 goodsflow 사용시 발생하는 현상.
				if(($step <= 35 || $step >= 85) && $Delivery){
					$is_pay_delivery				= 'y';
					$order_log['is_payed_shipping'] = 'y';
				}else{
					$is_pay_delivery = 'n';
				}

			// -----------------------------------------------------------------------------------
			// stpe2-1. 주문취소/취소철회
			if($order_log['result'] == 'true'){

				# 주문취소
				if((int)$step == 85){

					//-----------------------------------------------------------------------------------------
					//같이 신청한 취소건 환불코드 불러오기.
					if(!$CancelInfo['RefundRequestDate']){
						$npay_request_date	= $this->getGMTToLocaltime($CancelInfo['ClaimRequestDate']);
					}else{
						$npay_request_date	= $this->getGMTToLocaltime($CancelInfo['RefundRequestDate']);
					}
					if($CancelInfo['CancelCompletedDate']){
						$npay_complete_date	= $this->getGMTToLocaltime($CancelInfo['CancelCompletedDate']);
					}else{
						$npay_complete_date = '';
					}
					//-----------------------------------------------------------------------------------------
					//결제취소 업데이트 상태
					if($product_update['refund'] == "new"){

						# 같은 상품주문번호에 대해 환불신청/환불완료 처리가 동시에 이루어 질 때
						# 두번째 처리되는 건에 대해서는 new 가 아니라 update 처리 함.
						$refund_mode = "new";
						if($refund_list){
							foreach($refund_list as $refund){
								if($refund['product_info']['ProductOrderID'] == $npay_product_order_id){
									if($refund['refund_update'] == "new"){
										$refund_mode = "upt";
									}
								}
							}
						}

						$refund_item = array();
						$refund_item['refund_update']		= $refund_mode;
						$refund_item['item_seq']			= $item_seq;
						$refund_item['item_option_seq']		= $item_option_seq;
						$refund_item['item_suboption_seq']	= ($option_type == 'suboption')? $item_suboption_seq:'';
						$refund_item['refund_ea']			= $order_ea;
						$refund_item['npay_product_order_id']= $npay_product_order_id;

						$refund_data = array(
								'process_mode'		=> 'refund',
								'order_seq'			=> $order_seq,
								'npay_order_id'		=> $npay_order_id,
								'option_type'		=> $option_type,
								'npay_request_date'	=> $npay_request_date,
								'npay_complete_date'=> $npay_complete_date,
								'refund_type'		=> 'cancel_payment',
								'refund_status'		=> $npay_status['refund_status'],
								'reason'			=> $CancelInfo['CancelReason'],
								'reason_detail'		=> $CancelInfo['CancelDetailedReason'],
								'request_channel'	=> $CancelInfo['RequestChannel'],
								'shipping_seq'		=> $shipping_seq,
								'order_info'		=> $Order,
								'product_info'		=> $ProductOrder,
								'refund_update'		=> $refund_mode,
								'items'				=> array($refund_item),
								'last_refund_item_yn'=> $shop_status['last_refund_item_yn'],
							);

						$order_log['order_cancel'] = "insert(".$npay_status['refund_status'].")";
						$res = $this->set_order_refund(array_merge($refund_data,$npay_status));

					}elseif($product_update['refund'] == "upt"){

						$refund_item = array();
						$refund_item['refund_update']		= "upt";
						$refund_item['item_seq']			= $item_seq;
						$refund_item['item_option_seq']		= $item_option_seq;
						$refund_item['item_suboption_seq']	= ($option_type == 'suboption')? $item_suboption_seq:'';
						$refund_item['refund_ea']			= $order_ea;
						$refund_item['npay_product_order_id']= $npay_product_order_id;

						$refund_data = array(
								'process_mode'		=> 'refund',
								'order_seq'			=> $order_seq,
								'refund_type'		=> 'cancel_payment',
								'refund_update'		=> $product_update['refund'],
								'refund_code'		=> $shop_status['refund_code'],
								'items'				=> array($refund_item),
								'shipping_seq'		=> $shipping_seq,
								'order_info'		=> $Order,
								'product_info'		=> $ProductOrder,
								'last_refund_item_yn'=> $shop_status['last_refund_item_yn'],
								'refund_group_total_ea'=> $shop_status['refund_group_total_ea'],
							);
						$res = $this->set_order_return_update(array_merge($refund_data,$npay_status));
						$order_log['order_cancel'] = "update(". $shop_status['refund_status']." => ".$npay_status['refund_status'].", ".$npay_status['refund_flag'].")";
					}
				}

				# 취소철회가 수집 되지 않았을 때.
				# 취소철회 후 같은 주문건에 대해서 취소철회가 아닌 다른 claim type으로 수집되었을때. 취소신청건은 철회처리.
				# 예) 취소신청 -> 취소철회(중계서버 수집 안됨) -> 배송처리 or 반품처리 등.
				if($product_claim_status != "CANCEL_REJECT"
						&& ($shop_status['return_type'] == "refund" || $shop_status['refund_type'] == "cancel_payment")
						&& $shop_status['refund_status'] == "request" && $shop_status['refund_code']
						&& $shop_status['refund_type'] != $npay_status['refund_type']){
					$product_claim_status		= "CANCEL_REJECT";
					$refund_update				= "upt";
				}else{
					$refund_update				= $product_update['refund'];
				}

				# 취소 철회
				if($product_claim_status == "CANCEL_REJECT"){

					if($refund_update == "new"){
						$product_update					= $this->get_product_update_use($ProductOrder,$ClaimArr,$Delivery);
						$shop_status					= $product_update['shop_detail'];
						$npay_status					= $product_update['npay_detail'];
					}

					# 취소건 삭제
					if($shop_status['refund_code']){
						$order_log['refund']	= "delete(".$shop_status['refund_code'].")";
						$refund_res = $this->set_reverse_refund($shop_status['refund_code'],array($npay_product_order_id));
					}

				}
			}
			// -----------------------------------------------------------------------------------
			// stpe3-1. 배송
			if($order_log['result'] == 'true'){

				if($Delivery && $is_pay_delivery == 'n'){

					if(!$npay_delivery_company) $npay_delivery_company = config_load("npay_delivery_company");

					//---------------------------------------------------------------------------
					// npay 실제 출고일
					if($Delivery['SendDate']){
						$npay_export_date	= $this->getGMTToLocaltime($Delivery['SendDate']);
					}else{
						$npay_export_date = '';
					}

					//실제 배송방법
					$shipping_method = $this->get_shipping_method($Delivery['DeliveryMethod']);

					//---------------------------------------------------------------------------
					//배송 정보가 존재 하는지 확인
					$exp_data = $this->get_export_code($order_seq,$option_type,$npay_product_order_id,'order');
					if(!$exp_data['export_item_seq']){

						// npay 배송완료일
						if($Delivery['DeliveredDate']){
							$npay_shipping_date	= $this->getGMTToLocaltime($Delivery['DeliveredDate']);
						}else{
							$npay_shipping_date = '';
						}
						$delivery_company_code		= "";
						$delivery_tracking_number	= ($Delivery['TrackingNumber'])? $Delivery['TrackingNumber']:"";

						// 입점몰 재고처리 방법
						$arr_stockable[$shipping_seq] = $data_provider['default_export_stock_check'];

						if($Delivery['DeliveryCompany']){
							foreach($npay_delivery_company as $code => $deli_com){
								if($deli_com == $Delivery['DeliveryCompany']){
									$delivery_company_code = $code;
									break;//auto_ 택배자동화 쇼핑몰용으로 첫번째 코드로 적용  @2017-02-14
								}
							}
						}

						//배송완료일
						if($option_type == 'suboption'){
							$export_item_seq = "SUB-".$goods_seq."-".$item_suboption_seq;
						}else{
							$export_item_seq = "OPT-".$goods_seq."-".$item_option_seq;
						}

						$export_item							= array();
						$export_item['item_seq'][]				= $item_seq;
						$export_item['shipping_seq'][]			= $shipping_seq;
						$export_item['goods_name'][]			= $goods_name;
						$export_item['option_seq'][]			= $item_option_seq;
						$export_item['suboption_seq'][]			= ($option_type == 'suboption')? $item_suboption_seq:'';;
						$export_item['ea'][]					= $order_ea;
						$export_item['export_item_seq'][]		= $export_item_seq;
						$export_item['npay_product_order_id'][]	= $npay_product_order_id;
						$export_item['npay_status'][]			= 'y';	// npay API 전송결과(npay에서 처리된 건이라 'y'

						$export_data = array(
										'process_mode'				=> 'export',
										'goods_kind'				=> 'goods',
										'status'					=> '55',
										'next_status'				=> $step,
										'export_update'				=> 'new',
										'shipping_seq'				=> $shipping_seq,
										'shipping_provider_seq'		=> $shipping_provider_seq,
										'order_seq'					=> $order_seq,
										'international'				=> $international,
										'domestic_shipping_method'	=> $shipping_method,
										'delivery_company_code'		=> $delivery_company_code,
										'delivery_number'			=> $delivery_tracking_number,
										'export_date'				=> date("Y-m-d"),
										'regist_date'				=> date("Y-m-d H:i:s"),
										'complete_date'				=> date("Y-m-d H:i:s"),
										'npay_order_id'				=> $npay_order_id,
										'npay_export_date'			=> $npay_export_date,
										'npay_shipping_date'		=> $npay_shipping_date,
										'items'						=> $export_item
						);

						$process_list[] = $export_data;
						$order_log['export_update'] = "insert(".$step.")";

						$cfg = array();
						$cfg['stockable'] 			= $arr_stockable[$export_data['shipping_seq']];
						$cfg['step'] 				= '55';
						$cfg['export_date'] 		= date("Y-m-d");

						$next_status = $export_data['next_status'];
						unset($export_data['process_mode'],$export_data['next_status'],$export_data['export_update']);
						$exp_res = $this->order2exportmodel->export_for_goods(array($export_data),$cfg,'order_api');
						if($exp_res){
							foreach($exp_res['55'] as $expcd => $exp_data_tmp){
								$export_code = $exp_data_tmp['export_code'];
								if((strstr($expcd,"OPT") || strstr($expcd,"SUB")) && $next_status == 75){
									$this->exportmodel->exec_complete_delivery($export_code,'Npay');
								}
							}

							$exp_data['status']			= $export_data['status'];
							$exp_data['export_code']	= $export_code;
						}
					}

					# 출고 업데이트(출고완료/배송중/배송완료)
					if($exp_data['status'] < $step && ($step > 35 && $step < 85)){

						$export_data = array(
									'process_mode'			=> 'export',
									'order_seq'				=> $order_seq,
									'npay_order_id'			=> $npay_order_id,
									'export_code'			=> $exp_data['export_code'],
									'status'				=> $exp_data['status'],
									'npay_status'			=> $step,
									'export_update'			=> 'upt',
									'export_date'			=> $npay_export_date,
									'message'				=> ""
									);

						$process_list[] = $export_data;
						$order_log['export_update'] = "update(".$step.")";

						# 배송중/배송완료 처리
						if(!in_array($export_data['export_code'],$arr_export_code)){
							$res = $this->set_export_update($export_data);
						}
						$arr_export_code[] = $export_data['export_code'];

					}
				}
			}
			// -----------------------------------------------------------------------------------
			// stpe4-1. 반품
			if($order_log['result'] == 'true'){

				# 배송후 관리자 직권 취소 일때 반품 정보 안넘어옴.
				# 반품정보 생성
				if($npay_status['npay_claim_status'] == "admin_return"){

					if($npay_status['return_status'] == "ing"){
						$claimstatus = "COLLECTING";
					}elseif($npay_status['return_status'] == "complete"){
						$claimstatus = "RETURN_DONE";
					}
					$ReturnInfo = array(
									"ClaimStatus"			=> $claimstatus,
									"ClaimRequestDate"		=> $CancelInfo['ClaimRequestDate'],
									"ReturnCompletedDate"	=> $CancelInfo['CancelCompletedDate'],
									"ReturnReason"			=> $CancelInfo['CancelReason'],
									"ReturnDetailedReason"	=> $CancelInfo['CancelDetailedReason']."(관리자 직권취소)",
								);
				}

				//반품접수
				if($ReturnInfo['ClaimStatus']){

					# 원주문에 교환건이 있고, 재배송되지 않은 상태에서 반품접수(완료)되었다면
					# 교환(수거완료)->반품전환 처리된 건 => 반품(교환) -> 반품처리, 환불생성, 재주문 삭제(반품전환 후 처리)
					if($shop_status['return_type'] == "exchange" && !$DecisionHoldbackInfo){
						$shop_status['return_type']		= "return";
						$return_for_exchange			= true;
					}else{
						$return_for_exchange			= false;
					}

					//-----------------------------------------------------------------------------------------
					//반품신청일/수거완료일/반품완료일
					$npay_request_date	= $this->getGMTToLocaltime($ReturnInfo['ClaimRequestDate']);
					if($ReturnInfo['CollectCompletedDate']) $npay_collect_date	= $this->getGMTToLocaltime($ReturnInfo['CollectCompletedDate']);

					if($ReturnInfo['ReturnCompletedDate']){
						$npay_complete_date	= $this->getGMTToLocaltime($ReturnInfo['ReturnCompletedDate']);
					}else{
						$npay_complete_date = '';
					}

					$return_mode = "";

					//shop에 저장된 교환 step 확인.
					if($product_update['return'] == "new"){

						# 교환철회 : "교환접수 => 수집완료 => 교환철회 => 미수집 => 반품요청=> 수집"의 상황에서 발생
						if($shop_status['return_type'] == "exchange" && $shop_status['return_status'] == "request" && $npay_status['npay_claim_status'] == "return_request"){
							$return_reject_data = array(
												'process_mode'			=> 'return_reject',
												'order_seq'				=> $order_seq,
												'return_code'			=> $shop_status['return_code'],
												'npay_product_order_id'	=> $npay_product_order_id
							);
							$process_list[]			= $return_reject_data;
							$this->set_reverse_return($shop_status['return_code'],array($npay_product_order_id));
						}

						# 같은 상품주문번호에 대해 반품신청/반품완료 처리가 동시에 이루어 질 때
						# 두번째 처리되는 건에 대해서는 new 가 아니라 update 처리 함.
						$return_mode = "new";
						if($return_list){
							foreach($return_list as $return){
								if($return['product_info']['ProductOrderID'] == $npay_product_order_id){
									if($return['return_update'] == "new"){
										$return_mode = "upt";
									}
								}
							}
						}

						$return_item = array();
						$return_item['return_update']		= $return_mode;
						$return_item['item_seq']			= $shop_status['item_seq'];
						$return_item['item_option_seq']		= $shop_status['item_option_seq'];
						$return_item['item_suboption_seq']	= ($shop_status['opt_type'] == 'sub')? $shop_status['item_suboption_seq']:'';
						$return_item['return_ea']			= $order_ea;
						$return_item['npay_product_order_id']= $npay_product_order_id;
						$return_item['reason']				= $ReturnInfo['ReturnReason'];

						# 반품 주문번호와 오리지널 주문번호가 다르면 맞교환 재주문건에 대한 반품 처리.
						if($shop_status['order_seq'] != $order_seq){
							$exchange_return = true;
						}else{
							$exchange_return = false;
						}
						$return_data = array(
								'process_mode'					=> 'return',
								'return_type'					=> 'return',
								'order_seq'						=> $shop_status['order_seq'],
								'orign_order_seq'				=> $shop_status['orign_order_seq'],
								'option_type'					=> $option_type,
								'exchange_return'				=> $exchange_return,
								'npay_order_id'					=> $npay_order_id,
								'npay_request_date'				=> $npay_request_date,
								'npay_complete_date'			=> $npay_complete_date,
								'return_for_exchange'			=> $return_for_exchange,
								'return_code'					=> $shop_status['return_code'],
								'return_status'					=> $npay_status['return_status'],
								'shipping_seq'					=> $shipping_seq,
								'shipping_provider_seq'			=> $shipping_provider_seq,
								'return_update'					=> $return_mode,
								'refund_code'					=> $shop_status['refund_code'],
								'refund_update'					=> $product_update['refund'],
								'secret'						=> $secret,
								'return_info'					=> $ReturnInfo,
								'product_info'					=> $ProductOrder,
								'order_info'					=> $Order,
								'items'							=> array($return_item)
							);

						$process_list[]			= array_merge($return_data,$npay_status);
						$order_log['return']	= "update(".$npay_status['return_status'].", ".$npay_status['return_flag'].")";
						$order_log['refund']	= "update(".$npay_status['refund_status'].", ".$npay_status['refund_status'].")";

					}elseif($product_update['return'] == "upt" || $product_update['refund'] == "upt"){
						$return_mode = "upt";
						$return_data = array(
								'process_mode'					=> 'return',
								'order_seq'						=> $order_seq,
								'option_type'					=> $option_type,
								'return_type'					=> 'return',
								'return_update'					=> $return_mode,
								'return_for_exchange'			=> $return_for_exchange,
								'return_code'					=> $shop_status['return_code'],
								'return_status'					=> $npay_status['return_status'],
								'shop_return_status'			=> $shop_status['return_status'],
								'refund_update'					=> $product_update['refund'],
								'refund_code'					=> $shop_status['refund_code'],
								'refund_status'					=> $npay_status['refund_status'],
								'last_refund_item_yn'			=> $shop_status['last_refund_item_yn'],
								'shipping_seq'					=> $shipping_seq,
								'return_info'					=> $ReturnInfo,
								'product_info'					=> $ProductOrder,
								'order_info'					=> $Order,
							);
						$process_list[]			= array_merge($return_data,$npay_status);
						$order_log['return']	= "update(".$shop_status['return_status']." => ".$npay_status['return_status'].", ".$npay_status['return_flag'].")";
						$order_log['refund']	= "update(".$shop_status['refund_status']." => ".$npay_status['refund_status'].", ".$npay_status['refund_flag'].")";
					}

					if($return_mode == "new"){
						$this->set_order_return($return_data,$reserve_save,$arr_stockable);
					}elseif($return_mode == "upt") {
						$this->set_order_return_update($return_data,$reserve_save,$arr_stockable);
					}

					# 재주문 삭제(교환->반품으로 변경 건)
					if($return_for_exchange){
						$this->set_reorder_delete($order_seq,$npay_product_order_id);
					}
				}

				# 반품철회가 수집 되지 않았을 때.
				# 반품신청 후 같은 주문건에 대해서 반품철회가 아닌 다른 claim type으로 수집되었을때. 반품신청건은 철회처리.
				# 예) 반품신청 -> 반품철회(중계서버 수집 안됨) -> 교환신청
				if($product_claim_status != "RETURN_REJECT" && $shop_status['return_type'] == "return"
						&& $shop_status['return_status'] == "request" && $shop_status['return_code']
						&& $shop_status['return_type'] != $npay_status['return_type']){
					$product_claim_status		= "RETURN_REJECT";
					$return_update	= "upt";
				}else{
					$return_update				= $product_update['return'];
				}

				//반품 철회
				if($product_claim_status == "RETURN_REJECT"){

					if($return_update == "new"){
						$product_update					= $this->get_product_update_use($ProductOrder,$ClaimArr,$Delivery);
						$shop_status					= $product_update['shop_detail'];
						$npay_status					= $product_update['npay_detail'];
					}

					# 반품접수된 건이 있으면 반품 삭제
					if($shop_status['return_code']){
						$order_log['return']	= "delete(".$shop_status['return_code'].")";
						$this->set_reverse_return($shop_status['return_code'],array($npay_product_order_id));
					}
				}
			}
			// -----------------------------------------------------------------------------------
			// step4-2. 교환
			if($order_log['result'] == 'true'){

				// 교환 처리
				if($ExchangeInfo){

					# -----------------------------------------------------------------------------------------
					# 원주문 처리 : 교환신청~교환완료.(재주문 생성까지만)
					# -----------------------------------------------------------------------------------------
					# 교환신청일/재배송일/완료일
					$npay_request_date		= $this->getGMTToLocaltime($ExchangeInfo['ClaimRequestDate']);
					$npay_redelivery_date	= $this->getGMTToLocaltime($ExchangeInfo['ReDeliveryOperationDate']);
					if($ExchangeInfo['CollectCompletedDate']){
						$npay_complete_date	= $this->getGMTToLocaltime($ExchangeInfo['CollectCompletedDate']);
					}else{
						$npay_complete_date = '';
					}

					$reorder_export = array();

					// 재주문건의 배송 정보가 존재 하는지 확인
					$exp_data = $this->get_export_code($order_seq,$option_type,$npay_product_order_id,'reorder');
					# -----------------------------------------------------------------------------------------
					# 재주문건 출고생성 (Npay 교환재배송중, 교환완료, 수거완료 중 일때)
					if(in_array($npay_status['npay_claim_status'],array("exchange_redelivering","exchange_done","collect_done")) && $ExchangeInfo['ReDeliveryMethod']){

						if(!$exp_data){

							if($npay_status['return_flag'] == "exchange_done"){
								$re_status = 75;
							}else{
								$re_status = 55;
							}
							$reorder_export['goods_seq']				= $goods_seq;
							$reorder_export['goods_name']				= $goods_name;
							$reorder_export['order_seq']				= $order_seq;
							$reorder_export['item_seq']					= $item_seq;
							$reorder_export['item_option_seq']			= $item_option_seq;
							$reorder_export['item_suboption_seq']		= $item_suboption_seq;
							$reorder_export['option_type']				= $option_type;
							$reorder_export['return_ea']				= $order_ea;
							$reorder_export['npay_product_order_id']	= $npay_product_order_id;
							$reorder_export['provider_seq']				= $provider_seq;
							$reorder_export['shipping_seq']				= $shipping_seq;
							$reorder_export['shipping_provider_seq']	= $shipping_provider_seq;
							$reorder_export['exchange_info']			= $ExchangeInfo;
							$reorder_export['product_update']			= $product_update;
							$reorder_export['international']			= $international;
							$reorder_export['shipping_method']			= $shipping_method;
							$reorder_export['exp_data']					= $exp_data;
							$reorder_export['re_status']				= $re_status;

							//$process_list[]			= $reorder_export;
							//$this->set_exchange_export($reorder_export);
							//$order_log['exchange'] = "reorder export(".$re_status.", ".$npay_status['return_flag'].")";
						}else{
							$reorder_export = $exp_data;
						}
					}

					if($product_update['exchange'] == "new"){

						$return_item = array();
						$return_item['return_update']			= "new";
						$return_item['item_seq']				= $item_seq;
						$return_item['item_option_seq']			= $item_option_seq;
						$return_item['item_suboption_seq']		= ($option_type == 'suboption')? $item_suboption_seq:'';
						$return_item['return_ea']				= $order_ea;
						$return_item['npay_product_order_id']	= $npay_product_order_id;
						$return_item['reason']					= $ExchangeInfo['ExchangeReason'];

						$return_data = array(
								'process_mode'					=> 'exchange',
								'return_type'					=> 'exchange',
								'order_seq'						=> $order_seq,
								'npay_order_id'					=> $npay_order_id,
								'npay_request_date'				=> $npay_request_date,
								'npay_complete_date'			=> $npay_complete_date,
								'npay_redelivery_date'			=> $npay_redelivery_date,
								'return_status'					=> $npay_status['return_status'],
								'shipping_seq'					=> $shipping_seq,
								'shipping_provider_seq'			=> $shipping_provider_seq,
								'return_update'					=> 'new',
								'secret'						=> $secret,
								'order_info'					=> $Order,
								'product_info'					=> $ProductOrder,
								'return_info'					=> $ExchangeInfo,
								'npay_redelivery'				=> $DecisionHoldbackInfo,
								'return_reason'					=> $ExchangeInfo['ExchangeDetailedReason'],
								'items'							=> array($return_item),
								'reorder_export'				=> $reorder_export,
								'npay_return_deliveryfee_ids'	=> $ExchangeInfo['ClaimDeliveryFeeProductOrderIds'],
								'return_flag'					=> $npay_status['return_flag'],
							);

						$order_log['exchange']	= "insert(".$npay_status['return_status'].", ".$npay_status['return_flag'].")";
						$this->set_order_return($return_data,$reserve_save,$arr_stockable);

					}elseif($product_update['exchange'] == "upt"){

						# -----------------------------------------------------------------------------------------
						# 재주문건 출고생성 및 배송완료 처리(Npay 교환재배송중, 교환완료일때)
						$return_item = array();
						$return_item['return_update']			= "upt";
						$return_item['item_seq']				= $item_seq;
						$return_item['option_type']				= $option_type;
						$return_item['item_option_seq']			= $item_option_seq;
						$return_item['item_suboption_seq']		= ($option_type == 'suboption')? $item_suboption_seq:'';
						$return_item['return_ea']				= $order_ea;
						$return_item['npay_product_order_id']	= $npay_product_order_id;
						$return_item['reason']					= $ExchangeInfo['ExchangeReason'];

						$return_data = array(
								'process_mode'					=> 'exchange',
								'order_seq'						=> $order_seq,
								'reorder_export'				=> $reorder_export,
								'return_type'					=> 'exchange',
								'return_status'					=> $npay_status['return_status'],
								'return_update'					=> $product_update['exchange'],
								'return_code'					=> $shop_status['return_code'],
								'npay_complete_date'			=> $npay_complete_date,
								'order_info'					=> $Order,
								'product_info'					=> $ProductOrder,
								'return_info'					=> $ExchangeInfo,
								'shipping_seq'					=> $shipping_seq,
								'items'							=> $return_item,
								'return_flag'					=> $npay_status['return_flag'],
							);
						$exchange_data		= array_merge($return_data,$npay_status);
						$this->set_order_return_update($exchange_data,$reserve_save,$arr_stockable);
						$order_log['exchange']	= "update(".$shop_status['return_status']." => ".$npay_status['return_status'].", ".$npay_status['return_flag'].")";

					}

				}

				# 교환철회가 수집 되지 않았을 때.
				# 교환 신청 후 같은 주문건에 대해서 교환철회가 아닌 다른 claim type으로 수집되었을때. 교환신청건은 철회처리.
				# 예) 교환신청 -> 교환철회(중계서버 수집 안됨) -> 반품신청
				if($product_claim_status != "EXCHANGE_REJECT" && $shop_status['return_type'] == "exchange"
						&& $shop_status['return_status'] == "request" && $shop_status['return_code']
						&& $shop_status['return_type'] != $npay_status['return_type']){
					$product_claim_status		= "EXCHANGE_REJECT";
					$exchange_update			= "upt";
				}else{
					$exchange_update			= $product_update['exchange'];
				}

				//교환 철회
				if($product_claim_status == "EXCHANGE_REJECT"){

					if($exchange_update == "new"){
						$product_update					= $this->get_product_update_use($ProductOrder,$ClaimArr,$Delivery);
						$shop_status					= $product_update['shop_detail'];
						$npay_status					= $product_update['npay_detail'];
					}

					# 반품(맞교환)건 삭제
					if($shop_status['return_code']){
						$this->set_reverse_return($shop_status['return_code'],array($npay_product_order_id));
						$order_log['exchange']	= "delete(".$shop_status['return_code'].")";
					}
				}

			}
			// -----------------------------------------------------------------------------------
			// step3-2. 구매확정
			if($order_log['result'] == 'true'){
				if($buyConfirm == 'y'){
					$buyconfirm_data = array(
										'process_mode'			=> 'buyconfirm',
										'order_seq'				=> $order_seq,
										'option_type'			=> $option_type,
										'npay_product_order_id'	=> $npay_product_order_id
					);
					$process_list[]				= $buyconfirm_data;
					$order_log['buyconfirm']	= "update(buyconfirm)";
					$export_data				= $this->get_export_code($order_seq,$option_type,$npay_product_order_id,'order');
					$this->set_buy_confirm($export_data);
				}
			}

			$result_log[] = $order_log;


		} //foreach



		##------------------------------------------------------------------------
		// 출고예약량 업데이트
		foreach($r_reservation_goods_seq as $goods_seq){
			$this->goodsmodel->modify_reservation_real($goods_seq);
		}

		return $result_log;

	}


	# 교환재배송처리
	public function set_exchange_export($exchange){

		$goods_seq				= $exchange['goods_seq'];
		$goods_name				= $exchange['goods_name'];
		$order_seq				= $exchange['order_seq'];
		$option_type			= $exchange['option_type'];
		$npay_product_order_id	= $exchange['npay_product_order_id'];
		$item_seq				= $exchange['item_seq'];
		$item_option_seq		= $exchange['item_option_seq'];
		$item_suboption_seq		= $exchange['item_suboption_seq'];
		$return_ea				= $exchange['return_ea'];
		$provider_seq			= $exchange['provider_seq'];
		$shipping_provider_seq	= $exchange['shipping_provider_seq'];	//실제배송업체
		$shipping_seq			= $exchange['shipping_seq'];
		$exchange_info			= $exchange['exchange_info'];
		$international			= $exchange['international'];
		$shipping_method		= $exchange['shipping_method'];
		$exp_data				= $exchange['exp_data'];
		$re_status				= $exchange['re_status'];

		$exchange_re_export = array();

		//---------------------------------------------------------------------------
		if(!$exp_data['export_item_seq']){

			if(!$npay_delivery_company) $npay_delivery_company = config_load("npay_delivery_company");
			//---------------------------------------------------------------------------
			// npay 재배송 처리일
			if($exchange_info['ReDeliveryOperationDate']){
				$npay_export_date	= $this->getGMTToLocaltime($exchange_info['ReDeliveryOperationDate']);
			}else{
				$npay_export_date = '';
			}
			if($exchange_info['ReDeliveryCompany']){
				foreach($npay_delivery_company as $code => $deli_com){
					if($deli_com == $exchange_info['ReDeliveryCompany']){
						$delivery_company_code = $code;
						break;//auto_ 택배자동화 구조는 쇼핑몰내에서산 적용
					}
				}
			}else{
				$delivery_company_code = "";
			}

			# 재주문건의 order_seq, item_seq, option/suboption seq , shippgin_seq
			if($option_type == 'suboption'){
				$que = "select
					o.order_seq
					,item.item_seq
					,item.shipping_seq
					,opt.item_suboption_seq
				from
					fm_order as o
					left join fm_order_item as item on o.order_seq=item.order_seq
					left join fm_order_item_suboption as opt on o.order_seq=opt.order_seq and item.item_seq=opt.item_seq
				where
					o.orign_order_seq=?
					and opt.npay_product_order_id=?
					and opt.ea > 0
					";
			}else{
				$que = "select
					o.order_seq
					,item.item_seq
					,item.shipping_seq
					,opt.item_option_seq
					,'' as item_suboption_seq
				from
					fm_order as o
					left join fm_order_item as item on o.order_seq=item.order_seq
					left join fm_order_item_option as opt on o.order_seq=opt.order_seq and item.item_seq=opt.item_seq
				where
					o.orign_order_seq=?
					and opt.npay_product_order_id=?
					and opt.ea > 0
					";
			}

			$query			= $this->db->query($que,array($order_seq,$npay_product_order_id));
			//print_r($this->db->last_query());
			$re_order		= $query->row_array();
			$re_order_seq	= $re_order['order_seq'];
			$re_item_seq	= $re_order['item_seq'];
			$re_shipping	= $re_order['re_shipping'];
			$re_item_option_seq		= $re_order['item_option_seq'];
			$re_item_suboption_seq	= $re_order['item_suboption_seq'];

			if($option_type == 'suboption'){
				$export_item_seq		= "SUB-".$goods_seq."-".$re_item_suboption_seq;
			}else{
				$export_item_seq		= "OPT-".$goods_seq."-".$re_item_option_seq;
			}
			// 입점몰 재고처리 방법
			$arr_stockable[$re_shipping['shipping_seq']] = $data_provider['default_export_stock_check'];

			$export_item = array();
			$export_item['item_seq'][]				= $re_item_seq;
			$export_item['goods_name'][]			= $goods_name;
			$export_item['option_seq'][]			= $re_item_option_seq;
			$export_item['suboption_seq'][]			= $re_item_suboption_seq;
			$export_item['ea'][]					= $return_ea;
			$export_item['export_item_seq'][]		= $export_item_seq;
			$export_item['npay_product_order_id'][]	= $npay_product_order_id;
			$export_item['npay_status'][]			= 'y';	// npay API 전송결과(npay에서 처리된 건이라 'y'

			$export_data = array(
							'goods_kind'				=> 'goods',
							'export_update'				=> 'new',
							'status'					=> '55',
							'next_status'				=> $re_status,
							'shipping_seq'				=> $re_shipping['shipping_seq'],
							'order_seq'					=> $re_order_seq,
							'international'				=> $international,
							'domestic_shipping_method'	=> $shipping_method,
							'delivery_company_code'		=> $delivery_company_code,
							'delivery_number'			=> $exchange_info['ReDeliveryTrackingNumber'],
							'export_date'				=> date("Y-m-d"),
							'regist_date'				=> date("Y-m-d H:i:s"),
							'complete_date'				=> date("Y-m-d H:i:s"),
							'shipping_provider_seq'		=> $shipping_provider_seq,
							'npay_export_date'			=> $npay_export_date,
							'items'						=> $export_item,
			);
			//$export_list[] = $export_data;

		}else{

			$export_data = array(
							'goods_kind'				=> 'goods',
							'export_update'				=> 'upt',
							'export_code'				=> $exp_data['export_code'],
							'export_item'				=> $exp_data,
							'status'					=> $re_status,
							'order_seq'					=> $re_order_seq,
							'npay_shipping_date'		=> $npay_shipping_date,
			);
			//$export_list[] = $export_data;
		}

		if($export_data['export_update'] == "new"){
			$cfg = array();
			$cfg['stockable'] 			= $arr_stockable[$export_data['shipping_seq']];
			$cfg['step'] 				= '55';
			$cfg['export_date'] 		= date("Y-m-d");

			unset($export_data['export_update'],$export_data['next_status']);
			$exp_res = $this->order2exportmodel->export_for_goods(array($export_data),$cfg,'order_api');
			if($exp_res){
				$query = $this->db->query("select export_code from fm_goods_export where order_seq=?",$export_data['order_seq']);
				$exportdata = $query->row_array();
				//$this->exportmodel->exec_complete_delivery($exportdata['export_code'],false,'Npay');
			}

		}else{
			# 배송완료 처리
			$this->exportmodel->exec_complete_delivery($export_data['export_code'],false,'Npay');
		}
	}

	# 취소철회(환불삭제)
	public function set_reverse_refund($refund_code,$npay_data=array()){

		$this->load->model("refundmodel");
		$this->load->model("returnmodel");

		if(!$this->arr_step) $this->arr_step  = config_load('step');

		$data_refund 		= $this->refundmodel->get_refund($refund_code);
		$data_refund_item 	= $this->refundmodel->get_refund_item($refund_code);
		$data_order			= $this->ordermodel->get_order($data_refund['order_seq']);

		# npay 환불 삭제 불가
		$npay_use = npay_useck();

		//$this->db->trans_begin();
		$rollback = false;

		// 출고량 업데이트를 위한 변수선언
		$r_reservation_goods_seq = array();
		$reject_item				= array();

		foreach($data_refund_item as $refund_item){

			$reject_use = true;
			if($npay_use){
				if(in_array($refund_item['npay_product_order_id'],$npay_data)){
					$reject_item[] = $refund_item['refund_item_seq'];
				}else{
					$reject_use = false;
				}
			}

			if($reject_use){

				if($refund_item['opt_type']=='opt'){
					$option_seq = $refund_item['option_seq'];

					$query = "select * from fm_order_item_option where item_option_seq=?";
					$query = $this->db->query($query,array($option_seq));
					$optionData = $query->row_array();

					if($optionData['step']==85){
					//	$this->db->set('step','25');
					}

					$this->db->set('refund_ea','refund_ea-'.$refund_item['ea'],false);
					$this->db->where('item_option_seq',$option_seq);
					$this->db->update('fm_order_item_option');

					$opt_type = 'option';
					$this->ordermodel->set_step_ea(85,-$refund_item['ea'],$option_seq,$opt_type);

					// 주문 option 상태 변경
					$this->ordermodel->set_option_step($option_seq,'option');


					if($data_refund['refund_type'] != 'return'){
						// 출고량 업데이트를 위한 변수정의
						if(!in_array($optionData['goods_seq'],$r_reservation_goods_seq)){
							$r_reservation_goods_seq[] = $optionData['goods_seq'];
						}
					}

					## 마일리지&포인트 적립내역이 없으면. fm_goods_export_item 마일리지 지급예정 수량 업데이트 2015-03-30 pjm
					if($refund_item['reserve'] == 0 && $refund_item['point'] == 0){
						$this->db->set('reserve_return_ea','reserve_return_ea-'.$refund_item['ea'],false);
						$this->db->set('reserve_ea','reserve_ea+'.$refund_item['ea'],false);
						$this->db->where('item_seq',$refund_item['item_seq']);
						$this->db->where('option_seq',$refund_item['option_seq']);
						$this->db->update('fm_goods_export_item');
					}

				}else if($refund_item['opt_type']=='sub'){
					$option_seq = $refund_item['option_seq'];

					$query = "select * from fm_order_item_suboption where item_suboption_seq=?";
					$query = $this->db->query($query,array($option_seq));
					$optionData = $query->row_array();

					if($optionData['step']==85){
					//	$this->db->set('step','25');
					}

					$this->db->set('refund_ea','refund_ea-'.$refund_item['ea'],false);
					$this->db->where('item_suboption_seq',$option_seq);
					$this->db->update('fm_order_item_suboption');

					$opt_type = 'suboption';
					$this->ordermodel->set_step_ea(85,-$refund_item['ea'],$option_seq,$opt_type);

					// 주문 option 상태 변경
					$this->ordermodel->set_option_step($option_seq,'suboption');


					if($data_refund['refund_type'] != 'return'){
						// 출고량 업데이트를 위한 변수정의
						if(!in_array($optionData['goods_seq'],$r_reservation_goods_seq)){
							$r_reservation_goods_seq[] = $optionData['goods_seq'];
						}
					}

					## 마일리지&포인트 적립내역이 없으면. fm_goods_export_item 마일리지 지급예정 수량 업데이트 2015-03-30 pjm
					if($refund_item['reserve'] == 0 && $refund_item['point'] == 0){
						$this->db->set('reserve_return_ea','reserve_return_ea-'.$refund_item['ea'],false);
						$this->db->set('reserve_ea','reserve_ea+'.$refund_item['ea'],false);
						$this->db->where('item_seq',$refund_item['item_seq']);
						$this->db->where('suboption_seq',$refund_item['suboption_seq']);
						$this->db->update('fm_goods_export_item');
					}
				}
			}

			// 출고예약량 업데이트
			$this->goodsmodel->modify_reservation_real($refund_item['goods_seq']);
		}

		$actor = "Npay";

		$logTitle	= "취소철회(API:{$refund_code})";
		$logDetail	= "[".$reject_product_id."]Npay로부터 취소철회 되었습니다.";
		$this->ordermodel->set_log($data_order['order_seq'],'process',"Npay",$logTitle,$logDetail,'','','npay');

		// 환불 삭제시 주문상태값 되돌리기 추가 : 출고완료(55)=>50, 배송완료(75)=>70 leewh 2015-03-24
		//출고준비(45)=>40  @2015-12-24 pjm 추가
		if (in_array($data_order['step'], array('45','55','65','75','85'))) {
			$prev_step = $data_order['step'];
			$this->ordermodel->set_order_step($data_order['order_seq']);
			$data_order	= $this->ordermodel->get_order($data_order['order_seq']);
			$target_step = $data_order['step'];

			if($prev_step != $target_step){
				$this->ordermodel->set_log($data_order['order_seq'],'process',$actor,'되돌리기 ('.$this->arr_step[$prev_step].' => '.$this->arr_step[$target_step].')','-','','',$mode);
			}
		}

		$sql = "delete from fm_order_refund_item where refund_item_seq in(".implode(",",$reject_item).")";
		$this->db->query($sql);

		// fm_order_refund_item 테이블에서 옵션 데이터가 모두 삭제된 경우 환불 데이터 삭제
		$query = $this->db->get_where('fm_order_refund_item', array('refund_code' => $refund_code));
		if($query->num_rows() < 1){
			$res = $this->db->delete('fm_order_refund', array('refund_code'=>$refund_code));
		}

		if($npay_data) {
			# 출고준비건이 있으면 삭제
			$this->load->model('exportmodel');
			$this->exportmodel->delete_export_ready($data_order['order_seq'],$npay_data);
		}

		$logTitle	= "환불삭제(API:".$refund_code.")";
		$logDetail	= "{$refund_code} 환불건을 삭제처리했습니다.";
		$this->ordermodel->set_log($data_order['order_seq'],'process',$actor,$logTitle,$logDetail,'','',$mode);

		// 출고예약량 업데이트
		foreach($r_reservation_goods_seq as $goods_seq){
			$this->goodsmodel->modify_reservation_real($goods_seq);
		}

		//if ($this->db->trans_status() === FALSE || $rollback == true)
		//{
		//    $this->db->trans_rollback();
		//    echo "환불삭제 처리중 오류가 발생했습니다.";
		//}
		//else
		//{
		//    $this->db->trans_commit();
		//}

		return "{$refund_code} - 삭제완료";
	}


	# 반품철회(반품/환불 삭제)
	public function set_reverse_return($return_code,$npay_data=array()){

		$this->load->model("refundmodel");
		$this->load->model("returnmodel");
		if(!$this->accountall)			$this->load->helper('accountall');
		if(!$this->accountallmodel)		$this->load->model('accountallmodel');
		if(!$this->providermodel)		$this->load->model('providermodel');

		$data_return 		= $this->returnmodel->get_return($return_code);
		$data_return_item 	= $this->returnmodel->get_return_item($return_code);
		$data_order			= $this->ordermodel->get_order($data_return['order_seq']);

		if($data_return['return_type']=='return'){
			$title = "반품";
		}else{
			$title = "교환";
		}

		# npay 반품건 삭제 불가
		$npay_use = npay_useck();

		if($data_return['return_type']=='return' && $data_return['refund_code']){

			$data_refund 		= $this->refundmodel->get_refund($data_return['refund_code']);
			$data_refund_item 	= $this->refundmodel->get_refund_item($data_return['refund_code']);

			if($npay_use){
				$refund_items = array();
				foreach($data_refund_item as $refund){
					$refund_items[$refund['npay_product_order_id']] = $refund['refund_item_seq'];
				}
			}
		}


		if(!$this->cfg_order) $this->cfg_order = config_load('order');

		//$this->db->trans_begin();
		$rollback = false;

		$export_items_reserve	= array();
		$exports				= array();
		$export_items			= array();
		$reject_item			= array();

		foreach($data_return_item as $return_item){

			$reject_use = true;
			$npay_product_order_id	= $return_item['npay_product_order_id'];

			if($npay_use){
				if(in_array($return_item['npay_product_order_id'],$npay_data)){
					$reject_item['return'][] = $return_item['return_item_seq'];
					foreach($refund_items as $npay_product_order_id=>$refund_item_seq){
						if($npay_product_order_id == $return_item['npay_product_order_id']){
							$reject_item['refund'][] = $refund_item_seq;
						}
					}
				}else{
					$reject_use = false;
				}
			}

			if($reject_use){

				$reject_product[] = $return_item['return_item_seq'];

				if($return_item['opt_type']=='opt'){
					$option_seq = $return_item['option_seq'];

					$query = "select * from fm_order_item_option where item_option_seq=?";
					$query = $this->db->query($query,array($option_seq));
					$optionData = $query->row_array();

					if($data_return['return_type']=='return' && $optionData['refund_ea']>=$return_item['ea']){
						$this->db->set('refund_ea','refund_ea-'.$return_item['ea'],false);
						$this->db->where('item_option_seq',$option_seq);
						$this->db->update('fm_order_item_option');
					}
				}else if($return_item['opt_type']=='sub'){
					$option_seq = $return_item['option_seq'];

					$query = "select * from fm_order_item_suboption where item_suboption_seq=?";
					$query = $this->db->query($query,array($option_seq));
					$optionData = $query->row_array();

					if($data_return['return_type']=='return' && $optionData['refund_ea']>=$return_item['ea']){
						$this->db->set('refund_ea','refund_ea-'.$return_item['ea'],false);
						$this->db->where('item_suboption_seq',$option_seq);
						$this->db->update('fm_order_item_suboption');
					}
				}

				##----------------------------------------------------------------------------------
				// 자동구매확정처리를 위한 데이터 정리
				$export_code		= $return_item['export_code'];

				if(!in_array($export_code,$export_code_loop)){
					$exports[$export_code]	= $this->exportmodel->get_export($export_code);
					$export_code_loop[]		= $export_code;
				}


				$chk					= array();
				$chk['export_code']		= $export_code;
				$chk['item_seq']		= $return_item['item_seq'];
				if($return_item['opt_type']=='opt'){
					$chk['option_seq'] 		= $option_seq;
				}else if($return_item['opt_type']=='sub'){
					$chk['suboption_seq']	= $option_seq;
				}
				$items_tmp				= $this->exportmodel->get_export_item_by_item_seq('',$chk);		//1건만

				$items_tmp['opt_type']			= $return_item['opt_type'];
				$items_tmp['return_item_ea']	= $return_item['ea'];				//반품취소수량
				$items_tmp['give_reserve_ea']	= $return_item['give_reserve_ea'];	//회수마일리지(지급된)수량

				$exports[$export_code]['items'][]	= $items_tmp;
				##----------------------------------------------------------------------------------

				if($npay_use){
					$logTitle	= $title."철회(API:".$return_code.")";
					$logDetail	= "[".$npay_product_order_id."]Npay로 부터 ".$logTitle." 되었습니다.";
					$this->ordermodel->set_log($data_order['order_seq'],'process',"Npay",$logTitle,$logDetail,'','','npay');
				}
			}
			##----------------------------------------------------------------------------------
		}

		if(count($reject_item['return']) == count($data_return_item)){

			$actor = "Npay";

			if($data_return['return_type'] == "return"){
				$logTitle	= "반품삭제({$return_code})";
			}else{
				$logTitle	= "반품(맞교환)삭제({$return_code})";
			}
			$logDetail	= "{$return_code} 반품건을 삭제처리했습니다.";
			$this->ordermodel->set_log($data_order['order_seq'],'process',$actor,$logTitle,$logDetail,'','',$mode);
		}

		/**
		 * 네이버페이 구매확정 수집 될 때 구매확정 처리 됨
		 * 구매확정은 무조건 사용 && 자동구매확정은 처리되면 안됨
		 */
		foreach ($exports as $export_code => $data_export) {
			$export_items_reserve = [];
			foreach ($data_export['items'] as $export_items) {
				$tmp = [];
				$tmp['export_item_seq'] = $export_items['export_item_seq'];
				$tmp['export_code'] = $export_items['export_code'];

				$reserve_update = false;

				//# 마일리지 지급관련 반품취소 수량 ★
				$return_ea = $export_items['return_item_ea'] - $export_items['give_reserve_ea'];

				//# 일반 반품취소
				if ($export_items['reserve_return_ea'] > 0) {
					$reserve_update = true;
					//지급예정수량 = 지급예정수량 + 반품수량(회수마일리지(지급된)수량 제외)
					$tmp['reserve_ea'] = $export_items['reserve_ea'] + $return_ea;
					//지급예정반품수량 = 지급예정반품수량 - 반품수량(회수마일리지(지급된)수량 제외)
					$tmp['reserve_return_ea'] = $export_items['reserve_return_ea'] - $return_ea;
				}
				if ($reserve_update) {
					$export_items_reserve[] = $tmp;
				}
			}

			// 마일리지지급예정수량,반품수량 조절
			if ($export_items_reserve) {
				$this->exportmodel->exec_export_reserve_ea($export_items_reserve, 'return_cancel');
			}
		}
		##----------------------------------------------------------------------------------

		if($reject_item['return']){
			if($reject_item['return']){
				$sql = "delete from fm_order_return_item where return_item_seq in(".implode(",",$reject_item['return']).")";
				$this->db->query($sql);
			}
			// fm_order_return_item 테이블에서 옵션 데이터가 모두 삭제된 경우 반품 데이터 삭제
			$query = $this->db->get_where('fm_order_return_item', array('return_code' => $return_code));
			if($query->num_rows() < 1){
				$sql = "delete from fm_order_return where return_code=?";
				$this->db->query($sql, $return_code);
			}

		}

		if($data_return['return_type']=='return' && $data_return['refund_code']){

			if($reject_item['refund']){
				$sql = "delete from fm_order_refund_item where refund_item_seq in(".implode(",",$reject_item['refund']).")";
				$this->db->query($sql);
			}
			// fm_order_refund_item 테이블에서 옵션 데이터가 모두 삭제된 경우 환불 데이터 삭제
			$query = $this->db->get_where('fm_order_refund_item', array('refund_code' => $data_return['refund_code']));
			if($query->num_rows() < 1){
				$sql = "delete from fm_order_refund where refund_code=?";
				$this->db->query($sql, $data_return['refund_code']);
			}
		}elseif($data_return['return_type']=='exchange'){

			# Npay 교환 삭제일 경우 생성된 재주문건 삭제(원주문번호 넘기기)
			$this->set_reorder_delete($data_order['order_seq'],$npay_product_order_id);

		}
		return "{$return_code} - 삭제완료";

	}

	# 재주문번호  확인.
	public function get_reorder_seq($order_seq,$npay_product_order_id){
		$sql = "select * from (
				select
						o.order_seq
				from
					fm_order_item_option as opt,fm_order as o
				where
					opt.order_seq=o.order_seq
					and opt.npay_product_order_id=?
					and o.orign_order_seq=?
			union all
				select
						o.order_seq
				from
					fm_order_item_suboption as opt,fm_order as o
				where
					opt.order_seq=o.order_seq
					and opt.npay_product_order_id=?
					and o.orign_order_seq=?
			) as k";
		$query		= $this->db->query($sql,array($npay_product_order_id,$order_seq,$npay_product_order_id,$order_seq));
		$reorder	= $query->row_array();

		return $reorder['order_seq'];
	}

	# 재주문 삭제처리
	public function set_reorder_delete($order_seq,$npay_product_order_id){

		$reorder_seq = $this->get_reorder_seq($order_seq,$npay_product_order_id);

		if($reorder_seq){
			$sql = "delete from fm_order where order_seq = ?";
			$this->db->query($sql,$reorder_seq);
			$sql = "delete from fm_order_item where order_seq = ?";
			$this->db->query($sql,$reorder_seq);
			$sql = "delete from fm_order_item_option where order_seq = ?";
			$this->db->query($sql,$reorder_seq);
			$sql = "delete from fm_order_item_suboption where order_seq = ?";
			$this->db->query($sql,$reorder_seq);
			$sql = "delete from fm_order_package_option where order_seq = ?";
			$this->db->query($sql,$reorder_seq);
			$sql = "delete from fm_order_package_suboption where order_seq = ?";
			$this->db->query($sql,$reorder_seq);
			$sql = "delete from fm_order_shipping where order_seq = ?";
			$this->db->query($sql,$reorder_seq);

			$logTitle	= "재주문삭제(API:".$reorder_seq.")";
			$logDetail	= "[".$npay_product_order_id."] Npay로 부터 ".$title." 되었습니다.";
			$this->ordermodel->set_log($order_seq,'process',"Npay",$logTitle,$logDetail,'','','npay');

		}
	}

	## 구매확정 처리
	public function set_buy_confirm($export_data){


		if(!$this->accountall)			$this->load->helper('accountall');
		if(!$this->accountallmodel)		$this->load->model('accountallmodel');
		if(!$this->providermodel)		$this->load->model('providermodel');

		$chg_reserve	= array();
		$tot_reserve_ea = 0;
		$export_code	= $export_data['export_code'];
		$tot_reserve_ea = $export_data['reserve_ea'];

		## 구매확정 사용, 마일리지 지급예정수량이 있을때
		if($this->cfg_order['buy_confirm_use'] && $export_data['reserve_ea'] > 0){

			$reserve_buyconfirm_ea = $export_data['reserve_ea']+$export_data['reserve_buyconfirm_ea'];

			#지급예정수량 : 0, 지급완료수량 : 지급예정수량
			$tmp = array();
			$tmp['export_item_seq']			= $export_data['export_item_seq'];
			$tmp['reserve_ea']				= 0;
			$tmp['reserve_buyconfirm_ea']	= $reserve_buyconfirm_ea;
			$chg_reserve[]					= $tmp;

		}

		# 배송완료 처리
		if($export_data['status'] < 75){
			$this->exportmodel->exec_complete_delivery($export_code,'Npay');
		}

		$export_seq = $export_data['export_seq'];
		$order_seq	= $export_data['order_seq'];

		## 출고아이템에 마일리지 지급예정수량, 지급완료 수량 업데이트 2015-03-31 pjm
		if($chg_reserve){
			$this->exportmodel->exec_export_reserve_ea($chg_reserve,'buyconfirm');
		}

		# 마일리지 지급 예정 수량에 따른 구매확정 처리
		if($tot_reserve_ea > 0){

			$data_buy_confirm = array();
			$data_buy_confirm['order_seq']		= $order_seq;
			$data_buy_confirm['export_seq']		= $export_seq;
			$data_buy_confirm['manager_seq']	= '0';
			$data_buy_confirm['ea']				= $tot_reserve_ea;
			$data_buy_confirm['emoney_status']	= 'pay';
			$data_buy_confirm['actor_id']		= 'npay';
			$data_buy_confirm['doer']			= 'Npay';

			$this->buyconfirmmodel->buy_confirm('system',$export_code);
			$this->buyconfirmmodel->log_buy_confirm($data_buy_confirm);

			// 주문로그
			$log_title	= '구매확정(API:'.$export_code.':'.$tot_reserve_ea.")";
			$log_detail	= "[".$export_data['npay_product_order_id']."]Npay로부터 ".$log_title." 처리 되었습니다.";
			$this->ordermodel->set_log($order_seq,'buyconfirm','Npay',$log_title,$log_detail,'',$export_code,'npay');
			
			/**
			* 2-1 임시매출데이타를 이용한 미정산데이타 또는 통합정산테이블 시작
			* 정산개선 - 미정산데이타 또는 통합정산데이타 생성
			* @ 
			**/
			//정산대상 수량업데이트
			$this->accountallmodel->update_calculate_sales_ac_ea($order_seq,$export_code);
			//정산확정 처리
			$this->accountallmodel->insert_calculate_sales_buyconfirm($order_seq,$export_code, $tot_reserve_ea);
			//debug_var($this->db->queries);
			//debug_var($this->db->query_times);
			/**			* 2-1 임시매출데이타를 이용한 미정산데이타 또는 통합정산테이블 시작
			* 정산개선 - 미정산데이타 또는 통합정산데이타 생성

			* @
			**/
		}

		# 맞교환 주문건 추가옵션만 구매확정된 경우 추가 처리
		# 동일 주문건에 step!=75, ea=0 인 데이터는 구매확정 시 75로 변경
		# 관리자 주문 리스트에 option=25 로 되어있어서 결제확인으로 보여 수정함
		$this->db->select('item_option_seq');
		$this->db->from("fm_order_item_option");
		$this->db->where("order_seq", $order_seq);
		$this->db->where("step!=", '75');
		$this->db->where("ea", '0');
		$this->db->where('top_item_option_seq is NOT NULL', NULL, FALSE);
		$query = $this->db->get();
		$order_items = $query->result_array();
		foreach($order_items as $order_item) {
			$this->db->where('item_option_seq',$order_item['item_option_seq']);
			$this->db->where('order_seq',$order_seq);
			$this->db->update('fm_order_item_option',array('step'=>'75'));
		}
	}

	## refund insert 주문수집시 반품접수로 인한 환불접수
	public function set_order_refund($arr){

		$this->load->model("refundmodel");
		$this->load->model("returnmodel");

		$order_seq				= $arr['order_seq'];
		$exchange_return		= $arr['exchange_return'];	//맞교환 재주문건에 대한 반품 처리 여부
		$npay_order_id			= $arr['npay_order_id'];
		$reason					= $arr['reason'];
		$reason_detail			= $arr['reason_detail'];
		$refund_status			= $arr['refund_status'];
		$refund_type			= $arr['refund_type'];
		$shipping_seq			= $arr['shipping_seq'];
		$refund_flag			= $arr['refund_flag'];
		$return_info			= $arr['return_info'];				//반품정보
		$order_info				= $arr['order_info'];				//주문정보
		$product_info			= $arr['product_info'];				//상품정보
		$return_delivery		= $arr['return_delivery'];			//추가된 반품배송비
		$npay_product_order_id		= $product_info['ProductOrderID'];
		$npay_claim_deliveryfee_ids	= $return_info['ClaimDeliveryFeeProductOrderIds'];
		$step					= $arr['step'];
		//$DeliveryFeeAmount		= $product_info['DeliveryFeeAmount'];		//배송비
		//$SectionDeliveryFee		= $product_info['SectionDeliveryFee'];		//추가배송비

		//$refund_shipping_price	= $arr['refund_shipping_price'];	//취소로 인한 추가 배송비
		$npay_request_date	= $arr['npay_request_date']? $arr['npay_request_date'] : $npay_request_date = "";
		$npay_complete_date	= $arr['npay_complete_date']? $arr['npay_complete_date'] : $npay_complete_date = "";
		$admin_memo			= $arr['admin_memo']? $arr['admin_memo'] : $admin_memo = "";

		$data_order			= $this->ordermodel->get_order($order_seq);

		// 반품시 최상위 주문번호 저장 :: 2014-11-27 lwh
		// @pjm 설명 덧 붙임 : 교환으로 인한 재주문건은 주문금액 없음. 환불은 최상위 원주문에만 생성함.
		if($data_order['top_orign_order_seq'] && !$exchange_return)
			$orgin_order_seq = $data_order['top_orign_order_seq'];
		else
			$orgin_order_seq = $order_seq;

		$settleprice		= $data_order['settleprice'];	//최초 결제금액
		if($refund_type == "return"){

			$title = "반품신청";
			// 반품시 최상위 주문번호 저장 :: 2014-11-27 lwh
			if($top_orign_order_seq)
				$orgin_order_seq = $top_orign_order_seq;
			else
				$orgin_order_seq = $order_seq;


		}else{

			$title				= "주문취소";
			$orgin_order_seq	= $order_seq;

			//사유
			if($refund_type != "return"){
				$reason_msg		= $this->naverpaylib->get_npay_code('claim_cancel',trim($reason));
				if(!$reason_msg) $reason_msg = $this->naverpaylib->get_npay_code('claim_return',trim($reason));
				if($reason_detail)  $reason_detail = $reason_msg."-".$reason_detail;
			}

		}

		# 전체 취소/부분취소 구분
		$order_total_ea = $this->ordermodel->get_order_total_ea($orgin_order_seq);
		if($order_total_ea == $cancel_total_ea){
			$cancel_type = 'full';
		}else{
			$cancel_type = 'partial';
		}

		$items = array();
		//$cancel_total_ea	= 0;
		foreach($arr['items'] as $k=>$item){

			if($item['refund_update'] == 'new'){

				$items[$k]['item_seq']				= $item['item_seq'];
				$items[$k]['option_seq']			= ($item['item_option_seq'])? $item['item_option_seq']:"";
				$items[$k]['suboption_seq']			= ($item['item_suboption_seq'])? $item['item_suboption_seq']:"";
				$items[$k]['ea']					= $item['refund_ea'];
				$items[$k]['npay_product_order_id']	= $item['npay_product_order_id'];
				$items[$k]['refund_delivery_price'] = 0;
				$items[$k]['partner_return']		= true;

				if($refund_type == "return"){

					$items[$k]['give_reserve_ea']	= $item['give_reserve_ea'];
					$items[$k]['give_reserve']		= $item['give_reserve'];
					$items[$k]['give_point']		= $item['give_point'];

					$arr['request_channel'] = $return_info['RequestChannel'];

				}

				//$cancel_total_ea += $items[$k]['ea'];

				if($items[$k]['option_seq'] && !$items[$k]['suboption_seq']){

					$arr	= array('item_option_seq'=>$items[$k]['option_seq'],'item_seq'=>$items[$k]['item_seq']);
					$query	= $this->db->get_where('fm_order_item_option',$arr);
					$result = $query->row_array();

					if($refund_type == "return"){
						// 반품으로 인한 원주문 추출 및 교체 :: 2014-11-27 lwh
						if($result['top_item_option_seq']){
							$items[$k]['option_seq']	= $result['top_item_option_seq'];
							$items[$k]['item_seq']		= $result['top_item_seq'];
						}
					}
					// 취소수량 업데이트
					$this->db->query("update fm_order_item_option set step45=0 where item_option_seq=?",$items[$k]['option_seq']);
					$this->ordermodel->set_step_ea($step,$items[$k]['ea'],$items[$k]['option_seq'],'option');

					$this->db->set('refund_ea','refund_ea+'.$items[$k]['ea'],false);
					$this->db->where('item_option_seq',$items[$k]['option_seq']);
					$this->db->update('fm_order_item_option');

					// 주문 option 상태 변경
					$this->ordermodel->set_option_step($items[$k]['option_seq'],'option');

					//최종환불액(상품+배송)
					$refund_price		= (($result['price']-$result['member_sale'])*$result['ea'])
											-$result['fblike_sale']
											-$result['mobile_sale']
											-$result['referer_sale'];

					$items[$k]['refund_goods_price']	= $refund_price;

				}else if($items[$k]['suboption_seq']){
					$arr	= array('item_suboption_seq'=>$items[$k]['suboption_seq']);
					$query	= $this->db->get_where('fm_order_item_suboption',$arr);
					$result = $query->row_array();
					if($refund_type == "return"){
						// 반품으로 인한 원주문 추출 및 교체 :: 2014-11-27 lwh
						if($result['top_item_suboption_seq']){
							$items[$k]['suboption_seq'] = $result['top_item_suboption_seq'];
						}
					}

					$this->db->query("update fm_order_item_suboption set step45=0 where item_suboption_seq=?",$items[$k]['suboption_seq']);
					$this->ordermodel->set_step_ea($step,$items[$k]['ea'],$items[$k]['suboption_seq'],'suboption');

					$this->db->set('refund_ea','refund_ea+'.$items[$k]['ea'],false);
					$this->db->where('item_suboption_seq',$items[$k]['suboption_seq']);
					$this->db->update('fm_order_item_suboption');

					// 주문 option 상태 변경
					$this->ordermodel->set_option_step($items[$k]['suboption_seq'],'suboption');

					//최종환불액(상품+배송)
					$refund_price		= (($result['price']-$result['member_sale'])*$result['ea']);

					$items[$k]['refund_goods_price']	= $refund_price;
				}
			}

		}

		if(!$reason_detail) $reason_detail = "";

		## 환불신청
		$data = array(
			'order_seq'					=> $orgin_order_seq,
			'bank_name'					=> '',
			'bank_depositor'			=> '',
			'bank_account'				=> '',
			'refund_reason'				=> $reason_detail,
			'refund_type'				=> $refund_type,
			'cancel_type'				=> $cancel_type,
			'status'					=> $refund_status,
			'regist_date'				=> date('Y-m-d H:i:s'),
			'npay_order_id'				=> $npay_order_id,
			'npay_flag'					=> $refund_flag,
			'npay_refund_request_date'	=> $npay_request_date,
			'npay_refund_complete_date'	=> $npay_request_date,
			'npay_claim_deliveryfee_ids'=> $npay_claim_deliveryfee_ids,
			'manager_seq'				=> '0',
			'admin_memo'				=> $admin_memo
		);
		if($refund_status == "complete") $data['refund_date'] = $refund_date = date("Y-m-d H:i:s");

		$refund_code = $this->refundmodel->insert_refund($data,$items);

		## ----------------------------------------------------------------------------------
		## 동일 배송그룹내 반품으로 인한 반품배송비 환불/차감 처리
		if($refund_type == "return"){
			$params = array("order_seq"=>$orgin_order_seq,
							"order_info"=>$order_info,
							"product_info"=>$product_info,
							"return_info"=>$return_info,
							"refund_code"=>$refund_code,
							"shipping_seq"=>$shipping_seq
						);
			$return_delivery = $this->get_return_delivery($params,"refund");
		}else{
			## 동일 배송그룹내 결제취소로 인한 추가배송비 구하기
			if($refund_status == "complete"){
				$params = array("order_seq"				=> $orgin_order_seq,
								"refund_code"			=> $refund_code,
								"shipping_seq"			=> $shipping_seq,
								"order_info"			=> $order_info,
								"product_info"			=> $product_info,
							);
				$this->get_refund_delivery($params,"complete");
			}
		}

		## ----------------------------------------------------------------------------------
		if($refund_type == "cancel_payment"){
			# order step change
			$this->ordermodel->set_option_step($order_seq);
		}

		$this->ordermodel->set_order_step($order_seq);
		## ----------------------------------------------------------------------------------

		## 총 환불금액 저장.
		//$this->set_order_refund_price($refund_code);
		## ----------------------------------------------------------------------------------

		if($refund_status == "complete"){
			$logTitle	= "환불완료(".$refund_code.")";
			$logDetail	= "[".$npay_product_order_id."]NPay ".$title."에 의해 환불완료 되었습니다.";

			# 출고준비건이 있으면 삭제
			$this->load->model('exportmodel');
			$this->exportmodel->delete_export_ready($order_seq,$npay_product_order_id);

			$data_return		= $this->returnmodel->get_return_refund_code($refund_code);
			
			/**
			* 4-2 환불데이타를 이용한 통합정산테이블 생성 시작
			* @
			**/
			// 정산개선 미정산 추가 
			$this->load->helper('accountall');
			if(!$this->accountallmodel)	$this->load->model('accountallmodel');
			if(!$this->providermodel)	$this->load->model('providermodel');
			if(!$this->refundmodel)		$this->load->model('refundmodel');
			if(!$this->returnmodel)		$this->load->model('returnmodel');
			//정산대상 수량업데이트
			$this->accountallmodel->update_calculate_sales_ac_ea($order_seq,$refund_code, 'refund', '', $data_order);
			//정산확정 처리
			$this->accountallmodel->insert_calculate_sales_order_refund($order_seq, $refund_code, $cancel_type, $data_order);//월별매출
			/* 저장된 정보 로드 $data_order, $data_refund, $data_refund_item */
			// 3차 환불 개선으로 함수 처리 추가 :: 2018-11- lkh
			$this->accountallmodel->insert_calculate_sales_order_deductible($order_seq,$refund_code, $cancel_type, $data_order);
			//debug($this->db->queries);
			//debug_var($this->db->query_times);
			if($data_return && $data_return['refund_ship_duty'] == "buyer" && in_array($data_return['refund_ship_type'],array("M","A","D")) && $data_return['return_shipping_gubun'] == 'company' && $data_return['return_shipping_price']) {
				//step2 통합정산 생성(미정산매출 환불건수 업데이트)
				$this->accountallmodel->update_calculate_sales_order_returnshipping($data_return['order_seq'],$data_return['return_code'],$data['refund_date']);
				//debug_var($this->db->queries);
				//debug_var($this->db->query_times);
			}
			//debug_var($this->db->queries);
			//debug_var($this->db->query_times);
			/**
			* 3-2 환불데이타를 이용한 통합정산테이블 생성 끝
			* @
			**/

		}else{

			//출고준비건이 있으면 삭제
			$this->load->model('exportmodel');
			$this->exportmodel->delete_export_ready($order_seq,$npay_product_order_id);

			$logTitle	= "환불신청(".$refund_code.")";
			$logDetail	= "[".$npay_product_order_id."]NPay(".$arr['request_channel'].") ".$title."에 의한 환불신청이 접수되었습니다.";
		}
		$logParams	= array('refund_code' => $refund_code);
		$this->ordermodel->set_log($orgin_order_seq,'process','Npay',$logTitle,$logDetail,$logParams,'','npay');

		return $refund_code;

	}

	# 반품완료 처리(재고복구/상태/완료일 업데이트)
	public function set_order_return_complete($return_code,$arr){

		// 물류관리 관련 설정정보 추출
		if	(!$this->scm_cfg)		$this->scm_cfg			= config_load('scm');

		$data_return		= $this->returnmodel->get_return($return_code);
		$data_return_item	= $this->returnmodel->get_return_item($return_code);
		$retuns_coupon_ea	= 0;					//쿠폰상품 갯수
		$return_ea_arr		= array();				//재고차감 처리 수량

		# 반품완료일이 이미 등록되어 있다면, 기존 반품완료일 그대로 유지.
		if(trim($data_return['return_date']) && $data_return['return_date'] != "0000-00-00 00:00:00" && $data_return['return_date'] != "0000-00-00"){
			$return_date = $data_return['return_date'];
		}else{
			$return_date		= date("Y-m-d H:i:s");	//반품완료일
		}
		foreach($data_return_item as $key=> $item){

			$return_item_seq					= $item['return_item_seq'];
			$return_ea_arr[$return_item_seq]	= $item['ea'];

			# 반품 재고 복구
			# 패키지 상품
			if($item['package_yn'] == "y"){

				$package_stock_return_ea			= $item['package_stock_return_ea'];
				$package_return_badea				= $item['package_return_badea'];

				if($package_stock_return_ea){
					$package_stock_return_ea = unserialize($package_stock_return_ea);
				}
				if($package_return_badea){
					$package_return_badea = unserialize($package_return_badea);
				}

				if($item['opt_type'] == 'opt'){
					$packages = $this->orderpackagemodel->get_option($item['option_seq']);
					foreach($packages as $key_package=>$data_package){

						$package_option_code = 'option'.$data_package['package_option_seq'];

						if($data_return['status'] != "complete"){

							if($package_stock_return_ea){
								$return_ea = $package_stock_return_ea[$package_option_code];
							}else{
								$return_ea = $item['ea'];
							}
							$_POST['stock_return_ea'][$return_item_seq][$package_option_code]	= $return_ea;
							if($this->scm_cfg['use'] == "Y"){
								if($package_return_badea){
									$badea = $package_return_badea[$package_option_code];
								}else{
									$badea = 0;
								}
								$_POST['return_badea'][$return_item_seq][$package_option_code]	= $badea;
							}
						}

					}
				}elseif($item['opt_type'] == 'sub'){
					$packages = $this->orderpackagemodel->get_suboption($item['option_seq']);
					foreach($packages as $key_package=>$data_package){

						$package_option_code = 'suboption'.$data_package['package_suboption_seq'];

						if($data_return['status'] != "complete"){
							if($package_stock_return_ea){
								$return_ea = $package_stock_return_ea[$package_option_code];
							}else{
								$return_ea = $item['ea'];
							}
							$_POST['stock_return_ea'][$return_item_seq][$package_option_code]	= $return_ea;
							if($this->scm_cfg['use'] == "Y"){
								if($package_return_badea){
									$badea = $package_return_badea[$package_option_code];
								}else{
									$badea = 0;
								}
								$_POST['return_badea'][$return_item_seq][$package_option_code]		= $badea;
							}
						}

					}
				}

			# 일반 상품
			}else{

				if($data_return['status'] != "complete"){
					$_POST['stock_return_ea'][$return_item_seq]	= $item['stock_return_ea'];
					$_POST['return_badea'][$return_item_seq]	= $item['return_badea'];
				}
			}

			$stock_return_ea	= $_POST['stock_return_ea'][$return_item_seq];

			// 반품으로 인한 재고증가
			$goodsData = $this->returnmodel->return_stock_ea($stock_return_ea, $return_item_seq, $item, $goodsData);

		}

		foreach($_POST['stock_return_ea'] as $return_item_seq=>$stock_return_ea)
		{
			unset($update_param);

			$update_param['reason_code'] = $reason_code;
			if (!empty($_POST['reason_desc'][$return_item_seq])) {
				$update_param['reason_desc']	= $_POST['reason_desc'][$return_item_seq];
			}
			$stock_return_ea	= $_POST['stock_return_ea'][$return_item_seq];
			$return_badea		= $_POST['return_badea'][$return_item_seq];

			if( !is_array($stock_return_ea) ){
				$update_param['stock_return_ea']	= $_POST['stock_return_ea'][$return_item_seq];
				$update_param['return_badea']		= $_POST['return_badea'][$return_item_seq];
			}else{
				$update_param['package_stock_return_ea']= serialize($_POST['stock_return_ea'][$return_item_seq]);
				$update_param['package_return_badea']	= serialize($_POST['return_badea'][$return_item_seq]);
			}
			if($this->scm_cfg['use'] == "Y"){
				if(is_array($_POST['location_position'][$return_item_seq])){
					$location_position	= serialize($_POST['location_position'][$return_item_seq]);
					$location_code		= serialize($_POST['location_code'][$return_item_seq]);
				}else{
					$location_position	= $_POST['location_position'][$return_item_seq];
					$location_code		= $_POST['location_code'][$return_item_seq];
				}

				$update_param['location_position']	= $location_position;
				$update_param['location_code']		= $location_code;
			}

			$this->db->where('return_item_seq',$return_item_seq);
			$this->db->update('fm_order_return_item',$update_param);
		}


		# 반품 재고조정 히스토리 저장
		$this->returnmodel->return_stock_history($return_code,$retuns_coupon_ea,$data_return_item,$return_ea_arr,$return_date);

		# Npay 반품완료일 입력
		if(!$arr['complete_date']) $complete_date = date("Y-m-d H:i:s"); else $complete_date=$arr['complete_date'];

		// 현재 complete 아닐때에만 실행 2019-11-26 by hyem
		if($data_return['status'] != "complete"){
			// 물류관리 재고 적용 및 매장 재고 전송
			if	($this->scm_cfg['use'] == 'Y'){
				$this->load->model('scmmodel');
				$this->scmmodel->apply_return_wh($this->scm_cfg['return_wh'], $return_code, $goodsData);
				if	($this->scmmodel->tmp_scm['wh_seq'] > 0){
					$this->scmmodel->change_store_stock($this->scmmodel->tmp_scm['goods'], array($this->scmmodel->tmp_scm['wh_seq']), '', '반품처리가 완료 되었습니다.', 'reload');
				}
			}
		}

		$this->db->query("update fm_order_return set status=?,return_date=?,npay_return_complete_date=? where return_code=?",array('complete',$return_date,$complete_date,$return_code));
		// 네이버 페이 반품 건의 반품 사유 코드 코드 기반으로 귀책 부담 설정
		if ($arr['return_info']['ReturnReason']) {
			$npay_ship_duty = $this->returnmodel->get_npay_ship_duty($arr['return_info']['ReturnReason']);
			
			$update_param = array();
			$update_param['refund_ship_duty'] = $npay_ship_duty;
			$this->db->where('return_code', $return_code);
			$this->db->update('fm_order_return', $update_param);
		}
	}

	# 교완수거완료시 재주문 생성/출고/구매확정 처리
	public function export_reorder($npay_product_order_id,$order_seq,$return_code,$return_flag,$reorder_export){

		# 재주문건 검색
		$reorder_data = $this->get_shop_order_info($npay_product_order_id,'reorder_new');
		# 재주문 생성
		if(!$reorder_data){
			$reorder_seq = $this->ordermodel->reorder($order_seq,$return_code);
			$logTitle	= "재주문 생성(API:".$return_code.")";
			$logDetail	= "[".$npay_product_order_id."]Npay 교환으로 인한 ".$logTitle." 되었습니다.";
			$logParams	= array('return_code' => $return_code);
			$this->ordermodel->set_log($reorder_seq,'process','Npay',$logTitle,$logDetail,$logParams,'','npay');
		}

		# 교환수거완료시 반품완료 처리, 재고조정, 재주문 생성
		if($return_flag == "collect_done"){
			$title			= "교환수거완료 ";
			$return_status	= "complete";
			$exchange_status= "request";
		}elseif($return_flag == "exchange_redelivering"){
			$title			= "교환완료 ";		# 재주문 발송처리
			$return_status	= "complete";
			$exchange_status	= "reorder_deliviery";
		}elseif($return_flag == "exchange_done"){
			$title			= "교환완료 ";		# 재주문 배송완료(구매확정)
			$return_status	= "complete";
			$exchange_status= "reorder_delivery_complete";
		}

		# 교환완료시(재주문 배송완료처리, 구매확정처리)
		if($exchange_status == "reorder_deliviery" || $exchange_status == "reorder_delivery_complete"){

			# 재주문 출고(교환 재배송) 등록
			if(!$reorder_export['export_code']){
				$exp_res		= $this->set_exchange_export($reorder_export);
				$reorder_export	= $exp_res;
			}

			# 재주문 출고건 배송완료 처리
			if($reorder_export['export_code'] && $exchange_status == "reorder_delivery_complete"){

				if($reorder_export['status'] < 75 ){
					$this->exportmodel->exec_complete_delivery($reorder_export['export_code'],false,'Npay');
				}

				# 구매확정
				$this->set_buy_confirm($reorder_export);
			}

		}

		return $return_status;

	}

	## return/refund update 주문 수집시 반품,교환 상태 update
	public function set_order_return_update($arr,$reserve_save='',$arr_stockable=''){

		$this->load->model("order2exportmodel");
		$this->load->model("returnmodel");
		$this->load->model("refundmodel");
		$this->load->model("orderpackagemodel");

		$order_seq				= $arr['order_seq'];
		$shipping_seq			= $arr['shipping_seq'];
		$return_code			= $arr['return_code'];
		$refund_code			= $arr['refund_code'];
		$return_type			= $arr['return_type'];
		$order_info				= $arr['order_info'];
		$product_info			= $arr['product_info'];
		$return_info			= $arr['return_info'];
		$npay_claim_status		= $arr['npay_claim_status'];
		$return_flag			= $arr['return_flag'];
		$reorder_export			= $arr['reorder_export'];		//재주문 출고정보
		$npay_product_order_id	= $arr['product_info']['ProductOrderID'];
		$return_flag			= $arr['return_flag'];

		$refund_update			= $arr['refund_update'];
		$refund_status			= $arr['refund_status'];
		$refund_flag			= $arr['refund_flag'];
		$last_refund_item_yn	= $arr['last_refund_item_yn'];	//동일환불그룹에서 마지막 환불 상품인지 여부

		# 반품배송비 청구액 관련 필드
		$ClaimDeliveryFeeProductOrderIds= $return_info['ClaimDeliveryFeeProductOrderIds'];
		$ClaimDeliveryFeeDemandAmount	= $return_info['ClaimDeliveryFeeDemandAmount'];
		$ClaimDeliveryFeePayMethod		= $return_info['ClaimDeliveryFeePayMethod'];

		# 동일한 상품주문번호에 대해 환불신청/환불완료건이 동시 처리 될 때
		# 두번째 처리 되는 건은 강제 upt 처리이기 때문에 refund_code 가 없음.
		# 해서 첫번째 처리 된 refund_code를 가져옴. (반품도 동일)
		if(!$refund_code){
			$sql	= "select refund_code from fm_order_refund_item where npay_product_order_id='".$npay_product_order_id."'";
			$query	= $this->db->query($sql);
			$res	= $query->row_array();
			$refund_code = $res['refund_code'];
		}
		if(!$return_code){
			$sql	= "select return_code from fm_order_return_item where npay_product_order_id='".$npay_product_order_id."'";
			$query	= $this->db->query($sql);
			$res	= $query->row_array();
			$return_code = $res['return_code'];
		}

		## 환불완료일때
		if($refund_update == "upt" && $refund_status == "complete"){

			$data_refund		= $this->refundmodel->get_refund($refund_code);

			if($data_refund['status'] != "complete"){
				$refund_goods_price = $arr['product_info']['TotalPaymentAmount'];
				$que = "update fm_order_refund_item set refund_goods_price=? where refund_code=? and npay_product_order_id=?";
				$this->db->query($que,array($refund_goods_price,$refund_code,$npay_product_order_id));

				# 동일 환불그룹 내 마지막 환불 상품일때 처리
				if($last_refund_item_yn){

					if($arr['refund_type'] == "cancel_payment"){
						$refund_delivery = $this->get_refund_delivery($arr,'complete');
						# 환불 배송비가 추가 발생이면 환불금에서 차감 한다.
					}else{
						$params = array("order_seq"=>$order_seq,
										"refund_code"			=> $refund_code,
										"order_info"=>$order_info,
										"product_info"=>$product_info,
										"return_info"=>$return_info,
										"shipping_seq"=>$shipping_seq
							);
						$this->get_return_delivery($arr,'refund');
					}


					# 출고준비건이 있으면 삭제
					$this->load->model('exportmodel');
					$this->exportmodel->delete_export_ready($order_seq,$npay_product_order_id);

					# 네이버페이 판매자센터 직권취소 시
					if($npay_claim_status == "admin_cancel_done"){
						$logTitle	= "직권취소(".$refund_code.")";
						$logDetail	= "[".$npay_product_order_id."]Npay로부터 ".$logTitle." 되었습니다..";
						$logParams	= array('refund_code' => $refund_code);
						$this->ordermodel->set_log($order_seq,'process','Npay',$logTitle,$logDetail,$logParams,'','npay');
					}

					$logTitle	= "환불완료(".$refund_code.")";
					$logDetail	= "[".$npay_product_order_id."]Npay로부터 ".$logTitle." 되었습니다..";
					$logParams	= array('refund_code' => $refund_code);
					$this->ordermodel->set_log($order_seq,'process','Npay',$logTitle,$logDetail,$logParams,'','npay');

					$saveData['refund_date'] = date('Y-m-d H:i:s');
					
					$this->load->model('accountmodel');
					$this->accountmodel->set_refund($refund_code,$saveData['refund_date']);
					
				}
			}

		}

		# 반품/교환 완료일때
		## (Shop에서 반품신청/반품요청승인 처리 -> Npay에서 환불까지 처리된 반품완료)
		if($arr['return_update'] == "upt" && $arr['return_status'] == "complete" && $arr['shop_return_status'] != "complete"){

			if($return_type == "return"){
				$title			= "반품완료";
				$return_status	= "complete";

				$this->set_order_return_complete($return_code,$arr);	//반품완료처리

				$logTitle	= $title."(API:".$return_code.")";
				$logDetail	= "[".$npay_product_order_id."]Npay로부터 ".$title." 되었습니다.";
				$logParams	= array('return_code' => $return_code);
				$this->ordermodel->set_log($order_seq,'process','Npay',$logTitle,$logDetail,$logParams,'','npay');

			# 교환수거완료시 재주문 생성/출고/구매확정 처리
			}elseif($return_type == "exchange"){
				$return_status = $this->export_reorder($npay_product_order_id,$order_seq,$return_code,$return_flag,$reorder_export);
			}

			$params = array("order_seq"				=> $order_seq,
							"refund_code"			=> $refund_code,
							"order_info"			=> $order_info,
							"product_info"			=> $product_info,
							"return_info"			=> $return_info,
							"shipping_seq"			=> $shipping_seq
				);
			$this->get_return_delivery($arr,'return');

		}else{
			$return_status = $arr['return_status'];
		}

		# 동일 환불그룹 내 마지막 환불 상품일때 처리
		if($arr['refund_update'] == "upt" && $last_refund_item_yn){

			$update_field = $update_bind = array();

			$update_field[] = "status=?";
			$update_field[] = "npay_flag=?";
			$update_bind[] = $refund_status;
			$update_bind[] = $refund_flag;
			if($refund_status == "complete"){
				# 환불완료일이 이미 등록되어 있다면, 기존 환불완료일 그대로 유지.
				if(trim($data_refund['refund_date']) && $data_refund['refund_date'] != "0000-00-00 00:00:00" && $data_refund['refund_date'] != "0000-00-00"){
					$refund_date = $data_refund['refund_date'];
				}else{
					$refund_date		= date("Y-m-d H:i:s");	//반품완료일
				}

				# Npay 환불완료일 입력
				$update_field[] = "refund_date=?";
				$update_bind[]	= $refund_date;
				if($arr['complete_date']){
					$update_field[] = "npay_refund_complete_date=?";
					$update_bind[]	= $arr['complete_date'];
				}
			}

			$update_bind[]	= $refund_code;
			$bind			= array();
			$sql			= "update fm_order_refund set ".implode(",",$update_field)." where refund_code=?";
			$this->db->query($sql,$update_bind);

		}

		if($arr['return_update'] == "upt"){
			$field = $bind = array();
			$field[]	= "status=?";
			$field[]	= "npay_flag=?";
			$bind[]		= $return_status;
			$bind[] = $arr['return_flag'];

			# Npay 반품완료일 입력
			if($return_status == "complete"){
				if(!$arr['complete_date']) $complete_date = date("Y-m-d H:i:s"); else $complete_date=$arr['npay_complete_date'];
				$field[]	= "return_date=?";
				$field[]	= "npay_return_complete_date=?";
				$bind[]		= date("Y-m-d H:i:s");
				$bind[]		= $complete_date;
			}
			$bind[] = $arr['return_code'];
			$sql = "update fm_order_return set ".implode(",",$field)." where return_code=?";
			$this->db->query($sql,$bind);
		}


		if($arr['return_update'] == "upt" && $last_refund_item_yn && $return_status == "complete"){
			// 네이버 페이 반품 건의 반품 사유 코드 코드 기반으로 귀책 부담 설정
			if ($return_info['ReturnReason']) {
				$npay_ship_duty = $this->returnmodel->get_npay_ship_duty($return_info['ReturnReason']);
				
				$update_param = array();
				$update_param['refund_ship_duty'] = $npay_ship_duty;
				$this->db->where('return_code', $return_code);
				$this->db->update('fm_order_return', $update_param);
			}
			
			
			# 반품배송비 수령대상(환불금에서 차감일때는 통신판매중계자(본사)가 대신 받아 입점사에 정산 됨.
			if($ClaimDeliveryFeePayMethod == "환불금에서 차감"){
				$return_shipping_gubun = "company";
			}else{
				$return_shipping_gubun = "provider";
			}
			$return_shipping_price = $ClaimDeliveryFeeDemandAmount;

			# 배송비 환불 방법 저장 22.03.03 
			if (isset($return_code)) {
				$refund_ship_arr = array(
					'ClaimDeliveryFeePayMethod' => $ClaimDeliveryFeePayMethod,
				);
				$this->set_return_refund_ship($refund_ship_arr,$return_code);
			}

			/**
			* 2-2 반품배송비 관련 미정산데이타 또는 통합정산데이타 시작
			* @
			**/
			  if($return_shipping_gubun == 'company' && $return_shipping_price) {
				$this->load->helper('accountall');
				if(!$this->accountallmodel)	$this->load->model('accountallmodel');
				if(!$this->providermodel)	$this->load->model('providermodel');
				if(!$this->refundmodel)		$this->load->model('refundmodel');
				if(!$this->returnmodel)		$this->load->model('returnmodel');
				$this->accountallmodel->insert_calculate_sales_order_returnshipping($order_seq,$return_code);
				//debug_var($this->db->queries);
				//debug_var($this->db->query_times);
			  }
			/**
			* 2-2 반품배송비 관련 미정산데이타 또는 통합정산데이타 시작
			* @
			**/
		}
					
		if($refund_update == "upt" && $refund_status == "complete"){
			# 동일 환불그룹 내 마지막 환불 상품일때 처리
			if($last_refund_item_yn){
				$data_return		= $this->returnmodel->get_return_refund_code($refund_code);

				/**
				* 4-2 환불데이타를 이용한 통합정산테이블 생성 시작
				* @
				**/
				// 정산개선 미정산 추가 
				$this->load->helper('accountall');
				if(!$this->accountallmodel)	$this->load->model('accountallmodel');
				if(!$this->providermodel)	$this->load->model('providermodel');
				if(!$this->refundmodel)		$this->load->model('refundmodel');
				if(!$this->returnmodel)		$this->load->model('returnmodel');
				//정산대상 수량업데이트
				$this->accountallmodel->update_calculate_sales_ac_ea($order_seq,$refund_code, 'refund');
				//정산확정 처리

				$data_order			= $this->ordermodel->get_order($order_seq);
				$this->accountallmodel->insert_calculate_sales_order_refund($order_seq, $refund_code, $data_refund['cancel_type'], $data_order);//월별매출
				// 3차 환불 개선으로 함수 처리 추가 :: 2018-11- lkh
				$this->accountallmodel->insert_calculate_sales_order_deductible($order_seq,$refund_code, $data_refund['cancel_type'], $data_order);

				if($data_return && $data_return['refund_ship_duty'] == "buyer" && in_array($data_return['refund_ship_type'],array("M","A","D")) && $data_return['return_shipping_gubun'] == 'company' && $data_return['return_shipping_price']) {
					//step2 통합정산 생성(미정산매출 환불건수 업데이트)
					$this->accountallmodel->update_calculate_sales_order_returnshipping($data_return['order_seq'],$data_return['return_code'],$saveData['refund_date']);
					//debug_var($this->db->queries);
					//debug_var($this->db->query_times);
				}
				//debug_var($this->db->queries);
				//debug_var($this->db->query_times);
				/**
				* 3-2 환불데이타를 이용한 통합정산테이블 생성 끝
				* @
				**/
			}
		}
		
		
	}

	## return insert 주문 수집시 반품,교환 insert
	public function set_order_return($arr,$reserve_save='',$arr_stockable=''){

		$this->load->model("order2exportmodel");
		$this->load->model("returnmodel");

		if(!$this->cfg_order) $this->cfg_order = config_load('order');
		$scl							= $this->naverpaylib->scl;

		$order_seq						= $arr['order_seq'];
		$orign_order_seq				= $arr['orign_order_seq'];
		$exchange_return				= $arr['exchange_return'];	//맞교환 재주문건에 대한 반품처리 여부
		$npay_order_id					= $arr['npay_order_id'];
		$npay_request_date				= $arr['npay_request_date'];
		$npay_complete_date				= $arr['npay_complete_date'];
		$return_status					= $arr['return_status'];
		$return_type					= $arr['return_type'];
		$shipping_seq					= $arr['shipping_seq'];
		$shipping_provider_seq			= $arr['shipping_provider_seq'];
		//$return_reason					= $arr['return_reason'];
		$info							= $arr['return_info'];
		$product_info					= $arr['product_info'];
		$order_info						= $arr['order_info'];
		$secret							= $arr['secret'];
		$refund_update					= $arr['refund_update'];
		$refund_code					= $arr['refund_code'];
		$return_reason					= $info['ReturnDetailedReason'];
		$return_flag					= $arr['return_flag'];
		$reorder_export					= $arr['reorder_export'];		//재주문 출고정보
		$return_code					= $arr['return_code'];
		$return_for_exchange			= $arr['return_for_exchange'];	//교환중인건 반품전환
		$npay_product_order_id			= $product_info['ProductOrderID'];

		# 반품배송비 청구액 관련 필드
		$ClaimDeliveryFeeProductOrderIds= $info['ClaimDeliveryFeeProductOrderIds'];
		$ClaimDeliveryFeeDemandAmount	= $info['ClaimDeliveryFeeDemandAmount'];
		$ClaimDeliveryFeePayMethod		= $info['ClaimDeliveryFeePayMethod'];

		# 직권취소로 인한 반품 로그
		if($info['ClaimStatus'] == "RETURN_DONE"){
			$logTitle	= "직권취소(API)";
			$logDetail	= "[".$product_info['ProductOrderID']."]Npay로부터 ".$logTitle." 되었습니다.";
			$this->ordermodel->set_log($order_seq,'process','Npay',$logTitle,$logDetail,'','','npay');
		}

		//--------------------------------------------------------------------------------------
		//동일 반품건이 있는지 체크
		if($return_type == "exchange"){

			$title				= "교환";
			$refund_code		= '0';
			$address_type		= $info['AddressType'];
			//교환사유
			$reason				= $info['ExchangeReason'];
			$reason_desc		= $info['ExchangeDetailedReason'];

		}elseif($return_type == "return"){

			//$reason				= $info['ReturnReason'];
			$return_reason		= $info['ReturnDetailedReason'];

			$title				= "반품";
			//반품사유
			/*
			//수거완료일
			$CollectCompletedDate			= '';
			//접수채널(string)
			$ReturnDetailedReason			= "";
			//반품배송비 청구액
			$ClaimDeliveryFeeDemandAmount	= '';
			//반품배송비 결제방법
			$ClaimDeliveryFeePayMethod		= '';
			//반품배송비 결제수단
			$ClaimDeliveryFeePayMeans		= '';
			//기타비용 청구액
			$EtcFeeDemandAmount				= '';
			//기타 비용 결제 방법
			$EtcFeePayMethod				= '';
			//기타 비용 결제 수단
			$EtcFeePayMeans					= '';
			//환불예정일
			$RefundExpectedDate				= '';
			//환불요청일
			$RefundRequestDate				= '';
			//반품완료일
			$ReturnCompletedDate			= '';
			//반품배송비 할인액
			$ClaimDeliveryFeeDiscountAmount	= '';
			//기타 반품 비용 할인액
			$EtcFeeDiscountAmount			= '';
			*/
		}
		//--------------------------------------------------------------------------------------
		//반품/교환 수거지
			if($info['CollectAddress']['IsRoadNameAddress'] == "true"){
				$return_address_type	= "street";
				$return_address_street	= $scl->decrypt($secret,$info['CollectAddress']['BaseAddress']);
				$return_address			= "";
			}else{
				$return_address_type	= "zibun";
				$return_address_street	= "";
				$return_address			= $scl->decrypt($secret,$info['CollectAddress']['BaseAddress']);
			}
			$return_address_detail= $scl->decrypt($secret,$info['CollectAddress']['DetailedAddress']);

			if(is_object($return_address_street))	$return_address_street = '';
			if(is_object($return_address))			$return_address = '';
			if(is_object($return_address_detail))	$return_address_detail = '';

			$return_zipcode		= $info['CollectAddress']['ZipCode'];
			if($info['CollectAddress']['Tel1']){
				$Tel1			= $scl->decrypt($secret,$info['CollectAddress']['Tel1']);
				if(is_object($Tel1)) $return_tel1 = ''; else $return_tel1 = $Tel1;
			}else{
				$return_tel1	= "";
			}
			if($info['CollectAddress']['Tel2']){
				$Tel2			= $scl->decrypt($secret,$info['CollectAddress']['Tel2']);
				if(is_object($Tel2)) $return_tel2 = ''; else $return_tel2 = $Tel2;
			}else{
				$return_tel2	= "";
			}
			$admin_memo			= $scl->decrypt($secret,$info['RequestChannel']);
			if(is_object($admin_memo)) $admin_memo = ''; else $admin_memo = "반품요청자:".$admin_memo;
			//수거방법
			if($info['CollectDeliveryMethod'] == "RETURN_INDIVIDUAL"){
				$return_method = "user";	//자가반품
			}else{
				$return_method = "shop";
			}
		$shipping_price_depositor		= '';	//배송비입금자명
		$shipping_price_bank_account	= '';	//배송비입금계좌
		//--------------------------------------------------------------------------------------
		# 맞교환으로 인한 재주문의 반품일 경우 => 원주문 환불처리.
		if($exchange_return){
			$refund_order_seq	= $orign_order_seq;
			$reason_detail		= "재주문[".$order_seq."]건의 반품으로 인한 환불";
		}else{
			$refund_order_seq	= $order_seq;
			$reason_detail		= "반품으로 인한 환불";
		}
		//--------------------------------------------------------------------------------------
		// 반품/교환 아이템 정리
		$reason = $reason_desc = array();
		$k		= 0;
		$return_items = $items	= array();
		foreach($arr['items'] as $j => $item){

			$npay_product_order_id = $item['npay_product_order_id'];

			if($item['return_update'] == "new"){

				$refund_item_seq			= $item['item_seq'];
				$refund_item_option_seq		= $item['item_option_seq'];
				$refund_item_suboption_seq	= $item['item_suboption_seq'];

				if(!$item['item_suboption_seq']){
					# 맞교환으로 인한 재주문의 반품일 경우 => 원주문 환불처리.
					if($exchange_return){
						$sql = "select item_seq,item_option_seq from fm_order_item_option where order_seq=? and npay_product_order_id=?";
						$query = $this->db->query($sql,array($refund_order_seq,$item['npay_product_order_id']));
						$orign_option = $query->row_array();
						$refund_item_seq			= $orign_option['item_seq'];
						$refund_item_option_seq		= $orign_option['item_option_seq'];
						$refund_item_suboption_seq	= "";
					}
					$where				= "exp_item.option_seq";
					$item_option_seq	= $item['item_option_seq'];
					$option_type		= "OPT";
				}else{
					if($exchange_return){
						$sql = "select item_seq,item_option_seq,item_suboption_seq from fm_order_item_suboption where order_seq=? and npay_product_order_id=?";
						$query = $this->db->query($sql,array($refund_order_seq,$item['npay_product_order_id']));
						$orign_option = $query->row_array();
						$refund_item_seq			= $orign_option['item_seq'];
						$refund_item_option_seq		= $orign_option['item_option_seq'];
						$refund_item_suboption_seq	= $orign_option['item_suboption_seq'];
					}
					$where				= "exp_item.suboption_seq";
					$item_option_seq	= $item['item_suboption_seq'];
					$option_type		= "SUB";
				}

				##---------------------------------------------------------------------------
				# 반품 Item
					$query = $this->db->query("select exp_item.export_code from
											fm_goods_export_item as exp_item
											left join fm_goods_export as exp on exp.export_code=exp_item.export_code
											where exp.order_seq=? and exp_item.item_seq=? and ".$where."=?",array($order_seq,$item['item_seq'],$item_option_seq));
					$export_data = $query->row_array();
					$item['export_code'] = $export_data['export_code'];

					$return_items['chk_seq'][$k]					= 1;
					$return_items['chk_item_seq'][$k]				= $item['item_seq'];
					$return_items['chk_option_seq'][$k]				= $item['item_option_seq'];
					$return_items['chk_suboption_seq'][$k]			= $item['item_suboption_seq'];
					$return_items['chk_ea'][$k]						= $item['return_ea'];
					$return_items['chk_export_code'][$k]			= $item['export_code'];
					$return_items['chk_npay_product_order_id'][$k]	= $item['npay_product_order_id'];
					$return_items['option_type'][$k]				= $option_type;
					$return_items['reason'][$k]						= '';
					$return_items['reason_desc'][$k]				= $item['reason'];
					$return_items['partner_return'][$k]				= true;
					$return_items['stock_return_ea'][$k]			= $item['return_ea'];
					$return_items['return_badea'][$k]				= 0;
					$return_items_reason 							= $item['reason'];

				##---------------------------------------------------------------------------
				# 환불 Item

					// 회수해올 마일리지 수량
					$give_reserve_ea = $return_give_ea[$export_code][$option_type][$item_option_seq];
					if($give_reserve_ea > 0){
						$reserve		= $this->ordermodel->get_option_reserve($item_option_seq);
						$point			= $this->ordermodel->get_option_reserve($item_option_seq,'point');
						$give_reserve	= $reserve * $give_reserve_ea;
						$give_point		= $point * $give_reserve_ea;
					}else{
						$give_reserve	= 0;
						$give_point		= 0;
					}

					$tmp = array();
					$tmp['refund_update']				= "new";
					$tmp['item_seq']					= $refund_item_seq;
					$tmp['item_option_seq']				= $refund_item_option_seq;
					$tmp['item_suboption_seq']			= $refund_item_suboption_seq;
					$tmp['refund_ea']					= $item['return_ea'];
					$tmp['npay_product_order_id']		= $item['npay_product_order_id'];
					$tmp['give_reserve']				= $give_reserve;
					$tmp['give_point']					= $give_point;
					$tmp['give_reserve_ea']				= $give_reserve_ea;

					$refund_items[] = $tmp;

				##---------------------------------------------------------------------------

				$k++;

			}
		}
		#--------------------------------------------------------------------------------------
		# 배송완료 처리, 회수마일리지수량, 마일리지/포인트 처리
		$return_give_ea = $this->returnmodel->order_return_delivery_confirm($this->cfg_order,$return_items,'Npay');
		#--------------------------------------------------------------------------------------
		# 반품으로 인한 환불등록
		if($return_type == "return" && $refund_update == "new" ){

			# 환불상태는 반품상태와 동일하게 처리.
			$refund_data = array(
							'order_seq'						=> $refund_order_seq,
							'refund_code'					=> $refund_code,
							'phone'							=> $return_tel2,
							'cellphone'						=> $return_tel1,
							'reason_detail'					=> $reason_detail,
							'admin_memo'					=> $admin_memo,
							'refund_type'					=> 'return',
							'exchange_return'				=> $exchange_return,
							'refund_status'					=> $return_status,
							'shipping_seq'					=> $shipping_seq,
							'npay_order_id'					=> $npay_order_id,
							'npay_request_date'				=> $npay_request_date,
							'npay_complete_date'			=> $npay_complete_date,
							'items'							=> $refund_items,
							'return_info'					=> $info,
							'product_info'					=> $product_info,
							'order_info'					=> $order_info,
							);
			$refund_code = $this->set_order_refund($refund_data);
		}

		//보류중일땐 보류사유코드를, 보류가 아닐땐 반품처리 상태코드
		if($info['HoldbackStatus'] == "HOLDBACK" && !in_array($ClaimDeliveryFeePayMethod,array("환불금에서 차감","지금 결제함-추가결제"))){
			$npay_flag = $info['HoldbackReason'];
		}else{
			$npay_flag = $info['ClaimStatus'];
		}

		# 반품배송비 수령대상(환불금에서 차감일때는 통신판매중계자(본사)가 대신 받아 입점사에 정산 됨.
		if($info['ClaimDeliveryFeePayMethod'] == "환불금에서 차감"){
			$return_shipping_gubun = "company";
		}else{
			$return_shipping_gubun = "provider";
		}

		if($return_for_exchange){

			# 교환중인건 반품으로 전환 시 (반품타입 변경 및 환불코드 입력)
			$que = "update fm_order_return set return_type = 'return',refund_code='".$refund_code."',npay_flag='".$npay_flag."' where return_code='".$return_code."'";
			$this->db->query($que);

			$logTitle	= "교환->반품으로 변경(".$return_code.")";
			$logDetail	= "[".$product_info['ProductOrderID']."]Npay로부터 ".$title." 되었습니다.";
			$logParams	= array('return_code' => $return_code);
			$this->ordermodel->set_log($order_seq,'process','Npay',$logTitle,$logDetail,$logParams,'','npay');

		}else{
			
			$return_shipping_price = $info['ClaimDeliveryFeeDemandAmount'];

			# 반품 등록(request 등록됨)
			$return_config	= array(
							'mode'								=> $return_type,
							'order_seq'							=> $order_seq,
							'phone'								=> $return_tel2,
							'cellphone'							=> $return_tel1,
							'return_method'						=> $return_method,
							'return_recipient_zipcode'			=> $return_zipcode,
							'return_recipient_address_type'		=> $return_address_type,
							'return_recipient_address'			=> $return_address,
							'return_recipient_address_street'	=> $return_address_street,
							'return_recipient_address_detail'	=> $return_address_detail,
							'shipping_price_depositor'			=> $shipping_price_depositor,
							'shipping_price_bank_account'		=> $shipping_price_bank_account,
							'admin_memo'						=> $admin_memo,
							'npay_order_id'						=> $npay_order_id,
							'npay_request_date'					=> $npay_request_date,
							'npay_complete_date'				=> $npay_complete_date,
							'npay_flag'							=> $npay_flag,
							'reason_detail'						=> $return_reason,
							'return_shipping_price'				=> $return_shipping_price,
							'give_reserve_ea'					=> $give_reserve_ea,
							'give_reserve'						=> $give_reserve,
							'give_point'						=> $give_point,
							'return_shipping_gubun'				=> $return_shipping_gubun,
							'shipping_provider_seq'				=> $shipping_provider_seq,
							'npay_claim_deliveryfee_ids'		=> $ClaimDeliveryFeeProductOrderIds,
						);
			$return_data	= array_merge($return_items,$return_config);
			$return_code	= $this->returnmodel->order_return_insert($return_data,$refund_code,$return_type);
			
			# 반품배송비부담 , 배송비 환불 방법 저장 22.03.03 
			if (isset($return_code)) {
				$refund_ship_arr = array(
					'reason_code' => $return_items_reason,
					'ClaimDeliveryFeePayMethod' => $ClaimDeliveryFeePayMethod,
				);
				$this->set_return_refund_ship($refund_ship_arr,$return_code);
			}

		}

		## 반품/교환 배송비 저장.
		if($ClaimDeliveryFeeProductOrderIds){

			# 같이 신청된 반품 배송비 업데이트
			$params = array("order_seq"			=> $order_seq,
							"refund_code"		=> $refund_code,
							"return_code"		=> $return_code,
							"shipping_seq"		=> $shipping_seq,
							"return_info"		=> $info,
							"order_info"		=> $order_info,
							"product_info"		=> $product_info,
						);
			$this->get_return_delivery($params,'return');
		}else{

			// 반품배송비 로그
			if($ClaimDeliveryFeeDemandAmount > 0){
				$admin_memo	= "반품배송비 : ".number_format($ClaimDeliveryFeeDemandAmount)."(".$ClaimDeliveryFeePayMethod.")";
				if($info['ClaimDeliveryFeePayMeans']) $admin_memo .= "-".$info['ClaimDeliveryFeePayMeans'];
			}
		}

		## 반품 완료 처리
		if($return_status == "complete"){

			//직권취소시 원주문의 반품완료처리 @2017-06-15
			if($arr['return_code'] && $info['ClaimStatus'] == "RETURN_DONE") {
				$this->set_order_return_complete($arr['return_code'],$arr);
			}

			$this->set_order_return_complete($return_code,$arr);	//반품완료처리

			/**
			* 2-2 반품배송비 관련 미정산데이타 또는 통합정산데이타 시작
			* @
			**/
			  if($return_shipping_gubun == 'company' && $return_shipping_price) {
				$this->load->helper('accountall');
				if(!$this->accountallmodel)	$this->load->model('accountallmodel');
				if(!$this->providermodel)	$this->load->model('providermodel');
				if(!$this->refundmodel)		$this->load->model('refundmodel');
				if(!$this->returnmodel)		$this->load->model('returnmodel');
				$this->accountallmodel->insert_calculate_sales_order_returnshipping($order_seq,$return_code);
				//debug_var($this->db->queries);
				//debug_var($this->db->query_times);
			  }
			/**
			* 2-2 반품배송비 관련 미정산데이타 또는 통합정산데이타 시작
			* @
			**/

			// 반품완료가 환불완료보다 먼저 처리되므로 반품완료 후 정산마감 처리
			if($return_type == "return" && $refund_update == "new" ){

				/**
				* 4-2 환불데이타를 이용한 통합정산테이블 생성 시작
				* @
				**/
				// 정산개선 미정산 추가 
				$this->load->helper('accountall');
				if(!$this->accountallmodel)	$this->load->model('accountallmodel');
				if(!$this->providermodel)	$this->load->model('providermodel');
				if(!$this->refundmodel)		$this->load->model('refundmodel');
				if(!$this->returnmodel)		$this->load->model('returnmodel');
				//정산대상 수량업데이트
				$this->accountallmodel->update_calculate_sales_ac_ea($order_seq,$refund_code, 'refund');
				//정산확정 처리

				$data_order			= $this->ordermodel->get_order($order_seq);
				$data_refund		= $this->refundmodel->get_refund($refund_code);

				// 정산 남은 수량 확인 및 정산확정 처리
				$this->accountallmodel->update_calculate_refund_sales_buyconfirm($order_seq, $refund_code, $data_order);

				//debug_var($this->db->queries);
				//debug_var($this->db->query_times);
				/**
				* 3-2 환불데이타를 이용한 통합정산테이블 생성 끝
				* @
				**/
			}

		}

		if(!$return_for_exchange){
			if($return_status == "request") $title	.= "신청";
			elseif($return_status == "complete") $title	.= "완료";

			$logTitle = $title."(".$return_code.")";
			$logDetail	= "[".$product_info['ProductOrderID']."]Npay로부터 ".$title." 되었습니다.";
			$logParams	= array('return_code' => $return_code);
			$this->ordermodel->set_log($order_seq,'process','Npay',$logTitle,$logDetail,$logParams,'','npay');
		}

		if($return_status == "complete"){
			# 교환완료시 재주문 생성
			if($return_type == "exchange"){
				$this->export_reorder($npay_product_order_id,$order_seq,$return_code,$return_flag,$reorder_export);
			}
		}

		return true;
	}

	public function set_return_admin_memo($return_info=array(),$delivery_group_product=array(),$admin_memo='',$return_code=''){

		$ClaimDeliveryFeeProductOrderIds= $return_info['ClaimDeliveryFeeProductOrderIds'];
		$ClaimDeliveryFeeDemandAmount	= $return_info['ClaimDeliveryFeeDemandAmount'];
		$ClaimDeliveryFeePayMethod		= $return_info['ClaimDeliveryFeePayMethod'];
		$ClaimDeliveryFeePayMeans		= $return_info['ClaimDeliveryFeePayMeans'];

		$admin_memo = "[".date("Y-m-d H:i:s")."]".$admin_memo;
		if($delivery_group_product && $admin_memo){
			# 같이 신청된 반품배송비 메모 업데이트.
			$admin_memo_update	= true;
			$sql = "select
					ret.return_code,
						ifnull(ret.admin_memo,'') as admin_memo
					from
						fm_order_return as ret,fm_order_return_item as ret_item
					where
						ret.return_code=ret_item.return_code
						and ret_item.npay_product_order_id in(".$delivery_group_product.")";
			$query		= $this->db->query($sql);
			$ret_row	= $query->result_array();
			foreach($ret_row as $ret){
				if($return_code == $ret['return_code']){
					if($ret['admin_memo'] && $ret['admin_memo'] === $admin_memo){
						$admin_memo_update = false;
					}
				}
			}
			if($admin_memo_update ){
				$this->db->query("update
							fm_order_return as ret,fm_order_return_item as ret_item
							set ret.admin_memo=concat(admin_memo,(case when admin_memo!='' then '\n=>' else '' end),'".$admin_memo."')
						where
							ret.return_code=ret_item.return_code
							and ret.return_code='".$return_code."'
							and ret_item.npay_product_order_id in(".$delivery_group_product.")");
			}
		}
	}

	# 주문시 결제한 배송비 가져오기(기본배송비 + 개별배송비 가져오기 + 추가배송비)
	public function get_delivery_existing_price($order_seq,$shipping_seq){

		$sql		= "select
							(case when item.shipping_policy = 'goods'  then
								ifnull(item.goods_shipping_cost,0) + ifnull(ship.add_delivery_cost,0) - ifnull(ship.enuri_sale_unit,0) - ifnull(ship.enuri_sale_rest,0)
							else
								ifnull(ship.shipping_cost,0) - ifnull(ship.enuri_sale_unit,0) - ifnull(ship.enuri_sale_rest,0)
							end) as shipping_cost
						from
							fm_order_shipping as ship,
							fm_order_item as item
						where
							item.shipping_seq=ship.shipping_seq
							and item.order_seq=?
							and ship.shipping_seq=?
						group by ship.shipping_seq";
		$query		= $this->db->query($sql,array($order_seq,$shipping_seq));
		$row		= $query->row_array();

		$existing_delivery_price	= $row['shipping_cost'] + $row['add_delivery_cost'];

		return $existing_delivery_price;
	}

	public function get_refund_delivery($arr,$mode=''){

		$refund_code			= $arr['refund_code'];
		$refund_info			= $arr['refund_info'];				//반품정보
		$order_info				= $arr['order_info'];				//반품정보
		$product_info			= $arr['product_info'];
		$order_seq				= $arr['order_seq'];
		$shipping_seq			= $arr['shipping_seq'];
		$packgenumber			= $product_info['PackageNumber'];
		$refund_ea				= $product_info['Quantity'];
		$npay_product_order_id	= $product_info['ProductOrderID'];
		$last_refund_item_yn	= $arr['last_refund_item_yn'];
		$refund_group_total_ea	= $arr['refund_group_total_ea'];

		$return_shipping_price_sum	= 0;
		$return_code_shipping		= array();

		# 동일 배송그룹 상품주문번호
		$list_product = array();
		$sql = "select npay_product_order_id from (
					select
						npay_product_order_id
					from
						fm_order_item_option
					where
						order_seq=? and npay_packgenumber=?
					union all
					select
						npay_product_order_id
					from
						fm_order_item_suboption
					where
						order_seq=? and npay_packgenumber=?
				) as k ";
		$query = $this->db->query($sql,array($order_seq,$packgenumber,$order_seq,$packgenumber));
		$arr_npay_product = $query->result_array();
		foreach($arr_npay_product as $prod){
			$list_product[] = $prod['npay_product_order_id'];
		}
		$delivery_group_product = implode(",",$list_product);


		# 배송비 추가 발생 예
		# 1. 주문 취소 : 부분 취소로 인해 발생한 배송비 (예:무료배송 or 조건부 무료배송 -> 부분 취소 => 배송비 발생)
		# 2. 주문 반품 : 부분 취소로 인해 발생한 배송비 (예:무료배송 or 조건부 무료배송 -> 부분 반품 => 배송비 발생)
		$delivery_total		= (int)$product_info['DeliveryFeeAmount'] + (int)$product_info['SectionDeliveryFee'];

		$refund_delivery		= 0;
		$refund_add_delivery	= 0;

		# 주문 잔여 수량(해당 환불건 제외한 환불 신청/완료 수량)
		$order_remain	= $this->ordermodel->get_order_remain_ea($order_seq,$packgenumber,'refund',$refund_code);
		## --------------------------------------------------------------------------------------------------------
		# 마지막 취소건 이면
		$order_total_ea			= $order_remain['order_total_ea'];			//총 주문 수량
		$cancel_request_ea		= $order_remain['cancel_request_ea'];		//총 취소요청 수량
		$cancel_complete_ea		= $order_remain['cancel_complete_ea'];		//총 취소완료 수량

		if($refund_group_total_ea > $refund_ea) $refund_ea = $refund_group_total_ea;

		// 초도배송비는 언제나 가장 마지막 환불에 처리되므로 취소 신청 수량을 더할 이유가 없어서 제외함. by hed
		$cancel_ea_total		= $cancel_complete_ea + $refund_ea;
		$cancel_remain_ea		= $order_total_ea - $cancel_ea_total;

		## 동일 배송그룹내 총 배송비(결제시 지불한 배송비)
		$existing_delivery_price = $this->get_delivery_existing_price($order_seq,$shipping_seq);

		## --------------------------------------------------------------------------------------------------------
		# 동일 배송그룹 전체 취소일 때 결제시 지불한 배송비 환불
		if($cancel_remain_ea <= 0 ){
			$refund_delivery			= $existing_delivery_price;

		}else{

			## 동일 배송그룹내 부분취소 시 : 추가배송비 구하기
			## 추가 배송비 발생 = 총 배송비 - 최초 배송비
			$refund_add_delivery = $delivery_total - $existing_delivery_price;

		}

		$delivery_log = $refund_code." ".$npay_product_order_id;
		$delivery_log .= "\n동일 배송그룹의 기 배송비 existing_delivery_price : ".$existing_delivery_price;
		$delivery_log .= "\n동일 배송그룹의 현재 배송비 delivery_total : ".$delivery_total;
		$delivery_log .= "\n환불 배송비 refund_delivery : ".$refund_delivery;
		$delivery_log .= "\n추가 배송비 refund_add_delivery : ".$refund_add_delivery;

		# 동일 배송그룹 내 추가된 배송비 가져오기
		$sql = "select
					ref.refund_code,
					ifnull(ref.npay_claim_add_delivery,0) as npay_claim_add_delivery
				from
					fm_order_refund as ref
					,fm_order_refund_item as ref_item
				where
					ref.refund_code=ref_item.refund_code
					and ref.refund_code!='".$refund_code."'
					and ref_item.npay_product_order_id in(".$delivery_group_product.")
				";
		$query				= $this->db->query($sql);
		$list_refund_code			= array();
		$npay_claim_add_delivery	= 0;
		$refund_data				= $query->result_array();
		foreach($refund_data as $data){
			$list_refund_code[]			= "'".$data['refund_code']."'";
			$npay_claim_add_delivery	+= $data['npay_claim_add_delivery'];
		}

		$delivery_log .= "\n기 추가 배송비 npay_claim_add_delivery : ".$npay_claim_add_delivery;

		## --------------------------------------------------------------------------------------------------------
		# 동일 배송그룹 전체 취소일 때 결제시 지불한 배송비 환불
		$this->db->query("update fm_order_refund_item set refund_delivery_price=0 where npay_product_order_id in(".$delivery_group_product.")");

		$this->db->query("update fm_order_refund_item set refund_delivery_price=? where npay_product_order_id=?",array($refund_delivery,$npay_product_order_id));

		# 동일배송 그룹 내 환불로 인한 추가배송비가 다르거나, 동일 배송 그룹 전체 취소일때
		if($npay_claim_add_delivery != $refund_add_delivery || $cancel_remain_ea <= 0){

			if(count($list_refund_code) > 0){
				$this->db->query("update fm_order_refund set npay_claim_price=0,npay_claim_add_delivery=0 where refund_code in(".implode(",",$list_refund_code).")",array($refund_delivery));
			}

			$this->db->query("update fm_order_refund set npay_claim_price=?,npay_claim_add_delivery=? where refund_code=?",array($refund_add_delivery,$refund_add_delivery,$refund_code));

		}

		# 코드별 환불 최종금액 업데이트
		$this->set_refund_price($delivery_group_product);
		
		return $refund_delivery;


	}

	# 반품배송비 처리.
	public function get_return_delivery($arr,$mode='return'){

		$return_code						= $arr['return_code'];
		$refund_code						= $arr['refund_code'];
		$return_info						= $arr['return_info'];
		$return_type						= $arr['return_type'];
		$order_seq							= $arr['order_seq'];
		$shipping_seq						= $arr['shipping_seq'];
		$order_info							= $arr['order_info'];
		$product_info						= $arr['product_info'];
		$npay_product_order_id				= $product_info['ProductOrderID'];
		$packgenumber						= $product_info['PackageNumber'];
		$return_ea							= $product_info['Quantity'];
		$ClaimDeliveryFeeProductOrderIds	= $return_info['ClaimDeliveryFeeProductOrderIds'];
		$ClaimDeliveryFeeDemandAmount		= (int)$return_info['ClaimDeliveryFeeDemandAmount'];
		$ClaimDeliveryFeePayMethod			= $return_info['ClaimDeliveryFeePayMethod'];
		$ClaimDeliveryFeePayMeans			= $return_info['ClaimDeliveryFeePayMeans'];

		$old_return_delivery	= 0;
		$old_refund_delivery	= 0;
		$return_delivery		= 0;
		$existing_delivery_price= 0;
		$return_code_shipping	= array();
		$refund_code_shipping	= array();

		$data_order				= $this->ordermodel->get_order($order_seq);

		if($return_code){
			$data_return = $this->returnmodel->get_return($return_code);
		}

		# 추가배송비 : 무료배송 => 반품/취소로 인해 발생한 (발송) 배송비 packgenumber  그룹별
		# 반품배송비 : 반품으로 인해 발생한 반품(수거) 배송비  ClaimDeliveryFeeProductOrderIds 그룹별
		#----------------------------------------------------------------------------------------------------
		# 동일 배송 그룹 내 주문/취소수량
		$order_remain	= $this->ordermodel->get_order_remain_ea($order_seq,$packgenumber,'return',$refund_code,$return_code);

		# 동일 배송그룹이 전체 반품완료 되었을 경우
		$delivery_group_full_return = false;
		$order_remain_ea			= $order_remain['order_total_ea'] - $order_remain['cancel_complete_ea'];
		if($return_info['ClaimStatus'] == "RETURN_DONE"){
			$order_remain_ea -= $return_ea;
		}
		if($order_remain_ea <= 0){
			$delivery_group_full_return = true;
		}
		# 환불금에서 차감 여부
		if(in_array($ClaimDeliveryFeePayMethod,array("환불금에서 차감","지금 결제함-추가결제"))){
			$refund_delivery_insert = true;
		}else{
			$refund_delivery_insert = false;
		}

		if($mode == "return"){
			$mode_tmp = $mode." : ".$return_code;
		}else{
			$mode_tmp = $mode." : ".$refund_code;
		}
		$delivery_log = $mode_tmp." ".$npay_product_order_id;
		$delivery_log .= "\n동일 배송그룹의 총 주문 수량 order_total_ea : ".$order_remain['order_total_ea'];
		$delivery_log .= "\n동일 배송그룹의 반품요청 수량 cancel_request_ea : ".$order_remain['cancel_request_ea'];
		$delivery_log .= "\n동일 배송그룹의 반품완료 수량 cancel_complete_ea : ".$order_remain['cancel_complete_ea'];
		$delivery_log .= "\n현재 상품의 반품 수량 return_ea : ".$return_ea;
		$delivery_log .= "\n동일 배송그룹의 남은 수량(반품신청포함) order_remain_ea : ".$order_remain_ea;
		$delivery_log .= "\n동일 배송그룹 전체 반품 여부 delivery_group_full_return : ".$delivery_group_full_return;
		$delivery_log .= "\n환불금에서 차감 or 지금 결제함 refund_delivery_insert : ".$refund_delivery_insert;

		# 동일 배송그룹 상품주문번호
		$list_product		= array();
		$sql				= "select npay_product_order_id from (
					select
						npay_product_order_id
					from
						fm_order_item_option
					where
						order_seq=? and npay_packgenumber=?
					union all
					select
						npay_product_order_id
					from
						fm_order_item_suboption
					where
						order_seq=? and npay_packgenumber=?
				) as k ";
		$query				= $this->db->query($sql,array($order_seq,$packgenumber,$order_seq,$packgenumber));
		$arr_npay_product	= $query->result_array();
		foreach($arr_npay_product as $prod){
			$list_product[] = $prod['npay_product_order_id'];
		}
		$delivery_group_product = implode(",",$list_product);
		#----------------------------------------------------------------------------------------------------
		# 환불(반품) 배송비 계산

			# 동일 배송그룹의 최초 배송비(기본배송비 or 개별배송비 가져오기 + 추가배송비)
			$existing_delivery_price	= $this->get_delivery_existing_price($order_seq,$shipping_seq);

			# 청구된 반품배송비
			$claim_delivery_price		= $ClaimDeliveryFeeDemandAmount;

			# 현재 배송비
			$now_delivery_price			= (int)$product_info['DeliveryFeeAmount'] + (int)$product_info['SectionDeliveryFee'];

			# 추가 배송비(반품으로 인한)(무료배송 or 조건부무료배송 이 깨질때)
			$claim_add_delivery_price	= $now_delivery_price - $existing_delivery_price;

			# 환불해줄 최종 배송비(현재 배송비 - 반품으로 인한 추가배송비)
			$refund_delivery_price		= $now_delivery_price - $claim_add_delivery_price;
		#----------------------------------------------------------------------------------------------------
		# 반품으로 인해 발생한 동일 배송그룹에 추가된 배송비.
		if($return_type != "exchange" && $refund_code){
			//$where = "and ref.refund_code != ?";
		}else{
			$where = "";
		}
		$sql = "select
					sum(ifnull(ref.npay_claim_add_delivery,0)) as npay_claim_add_delivery
				from
					fm_order_refund  as ref ,
					fm_order_refund_item as ref_item
				where
					ref.refund_code=ref_item.refund_code
					and ref.order_seq=?
					".$where."
					and ref_item.npay_product_order_id in(".$delivery_group_product.")";
		$query							= $this->db->query($sql,array($order_seq));
		$return_row						= $query->row_array();
		$old_claim_add_delivery_price	=  $return_row['npay_claim_add_delivery'];
		$old_claim_price				=  $return_row['npay_claim_price'];		//
		# 기 추가배송비와 현 추가배송비가 다를 경우 추가배송비 저장
		if($old_claim_add_delivery_price != $claim_add_delivery_price){
			$claim_add_delivery_update		= true;
		}else{
			$claim_add_delivery_update	= false;
			//$claim_add_delivery_price	= 0;	//기 추가 배송비가 있다면 현 추가배송비는 초기화
		}
		#----------------------------------------------------------------------------------------------------
		# 최종 반품 배송비 (청구된 반품배송비 + 현 추가배송비)
		$delivery_log .= "\n최종 반품 배송비에 현 추가 배송비 포함 여부 claim_add_delivery_update : ".$claim_add_delivery_update;
		$return_delivery_price		=  $claim_delivery_price;

		# 기 추가 배송비와 현 추가 배송비가 다를 경우 추가배송비 저장
		if($claim_add_delivery_update && $refund_code){

			$old_claim_add_delivery_price	= $claim_add_delivery_price;
			# 동일 배송 그룹 추가배송비 초기화
			$this->db->query("update
								fm_order_refund as ref,fm_order_refund_item as ref_item
							set
								ref.npay_claim_add_delivery=0
							where
								ref.refund_code=ref_item.refund_code
								and ref_item.npay_product_order_id in(".$delivery_group_product.")
							");
			$this->db->query("update fm_order_refund set npay_claim_add_delivery=? where refund_code=?",array($claim_add_delivery_price,$refund_code));
			# 반품배송비 관련 메모 남기기
			if($return_code){
				if($claim_add_delivery_price > 0){
					$admin_memo	= "반품으로 인한 추가 배송비 발생 : ".number_format($claim_add_delivery_price)."원";
				}
				$this->set_return_admin_memo($return_info,$delivery_group_product,$admin_memo,$return_code);
			}
		}
		#----------------------------------------------------------------------------------------------------
		# 함께 반품된건의 반품 배송비 ClaimDeliveryFeeProductOrderIds
		if($ClaimDeliveryFeeProductOrderIds){
			$sql		= "select sum(ifnull(ret.return_shipping_price,0)) return_shipping_price
							 from
								fm_order_return as ret,fm_order_return_item as ret_item
							where
								ret.return_code=ret_item.return_code
								and ret.refund_code != '".$refund_code."'
								and ret_item.npay_product_order_id in(".$delivery_group_product.")
								and ret_item.npay_product_order_id in(".$ClaimDeliveryFeeProductOrderIds.")
							";
			$query = $this->db->query($sql);
			$return_row	= $query->row_array();
			$old_return_shipping_price = $return_row['return_shipping_price'];
			# 함께 반품된 건에 이미 처리된 반품배송비가 있으면 중복 등록 안함.
			if($old_return_shipping_price >= $return_delivery_price){
				$return_delivery_update_use = false;
				$return_delivery_price		= 0;
			}else{
				$return_delivery_update_use = true;
			}
		}
		$delivery_log .= "\n------------------------------------------------------------------";
		$delivery_log .= "\n동일 배송그룹의 최초 배송비 existing_delivery_price : ".$existing_delivery_price;
		$delivery_log .= "\n청구된 반품 배송비 claim_delivery_price : ".$claim_delivery_price;
		$delivery_log .= "\n현재 배송비 now_delivery_price : ".$now_delivery_price;
		$delivery_log .= "\n추가 배송비 claim_add_delivery_price : ".$claim_add_delivery_price;
		$delivery_log .= "\n기 추가 배송비 old_claim_add_delivery_price : ".$old_claim_add_delivery_price;
		$delivery_log .= "\n환불해줄 최종 배송비 refund_delivery_price : ".$refund_delivery_price;
		$delivery_log .= "\n청구할 반품 배송비 return_delivery_price : ".$return_delivery_price;
		$delivery_log .= "\n------------------------------------------------------------------";
		$delivery_log .= "\n기 청구한 반품 배송비 return_delivery_price : ".$old_return_shipping_price;
		$delivery_log .= "\n청구할 최종 반품 배송비 return_delivery_price : ".$return_delivery_price;
		$delivery_log .= "\n반품 배송비 업데이트 여부 return_delivery_update_use : ".$return_delivery_update_use;
		$delivery_log .= "\n------------------------------------------------------------------";
		#----------------------------------------------------------------------------------------------------
		# 반품배송비 청구액 없는 경우
		#  - 판매자 귀책사유로 인해 무료 반품시
		#  - 구매자 귀책사유이나 반품완료가 되면 반품청구액은 넘어오지 않음.
		# 반품배송비 등록. (실제 환불금에서 차감된 반품배송비에 대해서만 저장)
		if($mode == "return" && $refund_delivery_insert && $return_delivery_update_use){

			#----------------------------------------------------------------------------------------------------
			# 동일 배송그룹의 반품배송비 초기화
			$this->db->query("update
								fm_order_return as ret,fm_order_return_item as ret_item
							set
								ret.return_shipping_price=0,
								ret.return_shipping_gubun='provider'
							where
								ret.return_code=ret_item.return_code
								and ret_item.npay_product_order_id in(".$delivery_group_product.")
								and ret_item.npay_product_order_id in(".$ClaimDeliveryFeeProductOrderIds.")
								");
			#----------------------------------------------------------------------------------------------------
			# 청구된 반품배송비 업데이트
			# 일반몰 : 추가발생된 반품배송비 입력해도 무방
			# 입점몰 : 추가발생된 반품배송비를 입점몰에 정산(환불금에서 차감 하거나 추가 결제시)해줄 경우에만 입력.
			$this->db->query("update fm_order_return set return_shipping_price=?,return_shipping_gubun='company' where return_code=?",array($return_delivery_price,$return_code));
			# 반품배송비 관련 메모 남기기
			$admin_memo	= "반품배송비 : ".number_format($ClaimDeliveryFeeDemandAmount)."원"."(".$ClaimDeliveryFeePayMethod.")";
				if($info['ClaimDeliveryFeePayMeans']) $admin_memo .= "-".$ClaimDeliveryFeePayMeans;
			if($data_return['return_shipping_price'] != $ClaimDeliveryFeeDemandAmount){
				$this->set_return_admin_memo($return_info,$ClaimDeliveryFeeProductOrderIds,$admin_memo,$return_code);
			}
		}

		#----------------------------------------------------------------------------------------------------
		# 최종 반품배송비는 반품 완료시 저장.
		if($mode == "refund"){

			#----------------------------------------------------------------------------------------------------
			# 동일 배송그룹 기환불 배송비
			$sql = "select
						sum(ifnull(ref_item.refund_delivery_price,0)) as refund_delivery_price
					from
						fm_order_refund  as ref ,
						fm_order_refund_item as ref_item
					where
						ref.refund_code=ref_item.refund_code
						and ref.order_seq=?
						and ref.refund_code!=?
						and ref_item.npay_product_order_id in(".$delivery_group_product.")";
			$query					= $this->db->query($sql,array($order_seq,$refund_code));
			$refund_row				= $query->row_array();
			$existing_refund_delivery_price	=  $refund_row['refund_delivery_price'];
			#----------------------------------------------------------------------------------------------------
			# 동일 배송그룹의 모든 상품이 반품 되었을 경우 초도배송비(추가배송비포함) 환불 해줌.
			# 동일 배송그룹의 최초 배송비와 환불해준 배송비가 다를 경우 초기화 후 업데이트
			if(!$refund_delivery_insert){
				# 환불금에서 차감이 아닐때 초도배송비 + 추가배송비 모두 환불
				$existing_delivery_price += $old_claim_add_delivery_price;

				# 반품배송비 관련 메모 남기기
				if($return_info){
					$admin_memo	= "전체 반품으로 인한 추가 배송비 환불 : ".number_format($old_claim_add_delivery_price)."원";
					$this->set_return_admin_memo($return_info,$delivery_group_product,$admin_memo,$return_code);
				}

			}
			if($delivery_group_full_return && $existing_refund_delivery_price != $existing_delivery_price){

				$this->db->query("update fm_order_refund_item set refund_delivery_price=0 where npay_product_order_id in(".$delivery_group_product.")");
				$this->db->query("update fm_order_refund_item set refund_delivery_price=? where npay_product_order_id=?",array($existing_delivery_price,$npay_product_order_id));
				$existing_delivery_refund_use	= true;

			}else{
				$existing_delivery_refund_use = false;
			}

			$delivery_log .= "\n기 배송비 환불여부 : ".$existing_delivery_refund_use;
			#----------------------------------------------------------------------------------------------------
			# 동일 주문의 기 반품중 해당 반품건과 함께 반품된 건의 차감 금액 가져오기.
			$sql = "select
						sum(ifnull(npay_claim_price,0)) as npay_claim_price
					from
						fm_order_refund
					where
						order_seq = ?
						and refund_code != ?
						and npay_claim_deliveryfee_ids like '%".$npay_product_order_id."%'";
			$query = $this->db->query($sql,array($order_seq,$refund_code));
			$refund_row = $query->row_array();
			$refund_group_claim_price = (int)$refund_row['npay_claim_price'];

			$delivery_log .= "\n동일 배송그룹의 기환불 배송비 existing_refund_delivery_price : ".$existing_refund_delivery_price;
			$delivery_log .= "\n동일 주문의 기 반품중 해당 반품건과 함께 반품된 건이 있는가? refund_group_use : ".$refund_group_use;
			$delivery_log .= "\n함께 처리하는 반품의 주문번호 ClaimDeliveryFeeProductOrderIds : ".$ClaimDeliveryFeeProductOrderIds;
			$delivery_log .= "\n함께 처리하는 반품 건수 npay_claim_products : ".count($npay_claim_products);
			$delivery_log .= "\n재계산된 기 추가 배송비 old_claim_add_delivery_price : ".$old_claim_add_delivery_price;
			#----------------------------------------------------------------------------------------------------

			# Npay 환불 차감 금액 : 반품배송비가 환불금에서 차감일 때에만 입력
			# 단, +추가배송비는 무조건 환불금에서 차감.
			if($refund_delivery_insert || $claim_add_delivery_price > 0){

				if($claim_add_delivery_price > 0){
					$npay_claim_price	=  $claim_delivery_price + $claim_add_delivery_price;
				}else{
					$npay_claim_price	=  $claim_delivery_price;
				}

				#----------------------------------------------------------------------------------------------------
				# 동일 배송그룹의 모든 상품이 반품 되었을 경우 배송비 환불 해줌.(최종 차감액 )
				if($delivery_group_full_return){
					//$npay_claim_price = $npay_claim_price;
					# 상품 전체 반품 시
					#   > 반품배송비는 무조건 왕복으로 청구됨.
					#   > 최초 배송비가 없으면 차감할 반품 배송비에서 추가된 반품 배송비(반품으로 인해 추가된 배송비)를 뺀다.
					if($old_claim_add_delivery_price > 0){
						$delivery_log .= "\n차감 금액 ".$npay_claim_price ;
						$delivery_log .= "\n동일 배송그룹 상품 전체 반품 & 최초 배송비 없을 때 : '차감금액'에서 기 추가 배송비 제외하기 ".$old_claim_add_delivery_price ;
						$npay_claim_price = $npay_claim_price - $old_claim_add_delivery_price;
					}
					//if($npay_claim_price < 0){
					//	$npay_claim_price = (abs)$npay_claim_price;
					//}
				}
			}else{
				$npay_claim_price = 0;
			}

			$delivery_log .= "\n------------------------------------------------------------------";
			$delivery_log .= "\n환불금에서 차감 여부 refund_delivery_insert : ".$refund_delivery_insert;
			$delivery_log .= "\n반품 최종 배송비 return_delivery_price : ".$return_delivery_price;
			$delivery_log .= "\n함께 반품된 기 차감 금액 refund_group_claim_price : ".$refund_group_claim_price;
			$delivery_log .= "\n최종 차감 금액 npay_claim_price : ".$npay_claim_price;
			#----------------------------------------------------------------------------------------------------
			# 동일 주문 기 반품건들의 취소/반품에 의한 차감 금액이 현재 차감액과 다르면 업데이트
			//if($npay_claim_price != $refund_group_claim_price){

				$delivery_log .= "\n최종 차감 처리 : 완료";

				if($ClaimDeliveryFeeProductOrderIds){
					$this->db->query("update
										fm_order_refund as ref,fm_order_refund_item as ref_item
									set
										ref.npay_claim_price=0
									where
										ref.refund_code=ref_item.refund_code
										and ref_item.npay_product_order_id in(".$delivery_group_product.")
										and ref_item.npay_product_order_id in(".$ClaimDeliveryFeeProductOrderIds.")
									");
				}

				# Npay 환불 차감 금액 업데이트
				$this->db->query("update fm_order_refund set npay_claim_price=? where refund_code=?",array($npay_claim_price,$refund_code));

			//}else{
			//	$delivery_log .= "\n최종 차감 처리 : 안함";
			//}

			//debug($delivery_log);

		}

		if($mode == "refund"){
			# 코드별 환불 최종금액 업데이트
			$this->set_refund_price($delivery_group_product);			
		}

	}


	# return_code는 무조건 필요한 값 
	# reason_code는 refund_ship_duty 지정시만 필요함으로 arr로 수정
	public function set_return_refund_ship($arr,$return_code){
		
		$this->load->model("returnmodel");

		$set = array();

		$this->db->reset_query(); // 쿼리 리셋

		# 반품 배송비 결제 방법
		if (isset($arr['ClaimDeliveryFeePayMethod'])) {
			if ($arr['ClaimDeliveryFeePayMethod'] === '판매자에게 직접 송금' || $arr['ClaimDeliveryFeePayMethod'] === '지금 결제함-추가결제') {
				$refund_ship_type = 'A';
			} else if ($arr['ClaimDeliveryFeePayMethod'] === '환불금에서 차감') {
				$refund_ship_type = 'M';
			} else if ($arr['ClaimDeliveryFeePayMethod'] === '상품에 동봉함') { # 상품에 동봉함
				$refund_ship_type = 'D';
			} 
			# 기타 · 지금 결제함-추가결제
			$set['refund_ship_type'] = $refund_ship_type;
		}

		# 반품 배송 주체 결정 insert때만 필요함
		if (isset($arr['reason_code'])) {
			$duty 		= $this->returnmodel->get_npay_ship_duty($arr['reason_code']);
			$set['refund_ship_duty'] = $duty;
		}
		if(!empty($set)) {
			$this->db->where('return_code',$return_code);
			$this->db->update('fm_order_return', $set);
		}
		
	}

	# 코드별 환불 최종금액 업데이트
	public function set_refund_price($delivery_group_product){

		if($delivery_group_product){
			$sql = "
				update
					fm_order_refund as ref,
					(select
						refund_code,
						sum(ifnull(refund_goods_price,0)) refund_goods_price,
						sum(ifnull(refund_delivery_price,0)) refund_delivery_price
					from
						fm_order_refund_item
					where
						npay_product_order_id in(".$delivery_group_product.")
					group by refund_code
					) as ref_item
				set
					ref.refund_price=(ref_item.refund_goods_price+ref_item.refund_delivery_price),
					ref.refund_delivery=ref_item.refund_delivery_price
				where
					ref.refund_code=ref_item.refund_code
				";
			$this->db->query($sql);
		}
	}

	public function get_shipping_provider_seq($_shipping_group){

		$query = $this->db->query("select shipping_provider_seq from fm_shipping_grouping where  shipping_group_seq=?",array($_shipping_group));
		$_shipping_provider_seq = $query->row_array();
		$shipping_provider_seq	= $_shipping_provider_seq['shipping_provider_seq'];
		return $shipping_provider_seq;
	}


	// 주문시 이벤트, 쿠폰, 유입경로 할인에 따른 정산금액 및 입점사 부담율,부담액 계산. @2019-03-07 pjm
	public function get_buy_sales($params){

		$params['event_sale']	= $params['one_sale_list_event'] * $params['ea'];		//이벤트할인 총액

		/*
		1. 할인이벤트로 인한 정산수수료 재계산 accountallmodel @20190516 pjm
		2. 옵션/추가옵션의 정산관련 데이터는 미노출로 변경됨에 따라 저장안함. (모든 정산데이터는 정산테이블에서만 관리)
		*/
		$_commission_info					= array();
		foreach(get_commission_info_field() as $_field) $_commission_info[$_field] = $params[$_field];
		$params['commission_rate']			= reset_commission_rate($_commission_info,$params['event']);

		// 할인이벤트: 입점사부담금 확인(개당)
		$provider_event_sales	= $this->eventmodel->get_salecost_provider($params);

		## 쿠폰할인 할인부담금 적용
		if(!$couponmodel) $this->load->model('couponmodel');
		$salescost_provider_coupon	= $this->couponmodel->get_salecost_provider($params);

		## 프로모션 할인부담금 적용 : 네이버페이는 프로모션 할인 적용 안함
		/*
		$this->load->model('promotionmodel');
		$salescost_provider_promotion	= $this->promotionmodel->get_salecost_provider($params);
		*/

		## 유입경로 할인부담금 적용
		if(!$referermodel) $this->load->model('referermodel');
		$salescost_provider_referer	= $this->referermodel->get_salecost_provider($params);
		
		$salescost_provider						= array();
		$salescost_provider['event']			= $provider_event_sales;
		$salescost_provider['coupon']			= $salescost_provider_coupon;
		$salescost_provider['referer']			= $salescost_provider_referer;

		$_commission_info['target_price']		= $params['price'] - array_sum($salescost_provider);
		$_commission_info['commission_rate']	= $params['commission_rate'];
		$_commission_info['salescost_provider']	= $salescost_provider;
		$_return_commission						= get_commission($_commission_info);
		$commission_price						= $_return_commission['old_commission_unit_price'];	//(구)정산 금액 저장. (신)정산금액은 수집시 계산.

		$return									= array();
		$return['commission_type']				= $params['commission_type'];
		$return['commission_price']				= $commission_price;
		$return['commission_rate']				= $params['commission_rate'];
		$return['salescost_provider']			= $params['event']['salescost_provider'];		// 입점사 이벤트 할인 부담"율"
		$return['salescost_provider_coupon']	= $salescost_provider_coupon;		// 입점사 쿠폰 할인 부담액
		$return['salescost_provider_referer']	= $salescost_provider_referer;		// 입점사 유입경로할인 부담액

		return $return;
	}

}