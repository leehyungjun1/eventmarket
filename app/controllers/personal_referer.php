<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');
require_once(APPPATH ."controllers/base/front_base".EXT);
require_once(APPPATH ."libraries/broadcast/BroadcastTrait".EXT);

class personal_referer extends front_base {
	use BroadcastTrait;

	public function broadcast() {
		$this->load->helper('broadcast');

		$bs_seq = $this->input->get('no');
		$goods_seq = $this->input->get('goods_seq');
		$sch = $this->view($bs_seq);
		$type = "live";
		$is_vod = $this->input->get('is_vod');
		if($is_vod) {
			$type = "vod";
		}

		// 상품상세 유입 통계 쌓기
		$param = array();
		$param['bs_seq'] = $bs_seq;
		$param['goods_seq'] = $goods_seq;
		$param['type'] = $type;
		$this->goodsStats($param);
		$cookie_name = "broadcast_".$goods_seq."_".$type;

		// 주문 데이터 쌓기
		if(($type == "live" && $sch['status'] != "end") || $type == "vod")  {
			// 플레이어 > 방송 종료만 아니라면 쿠키 굽기
			// vod > 무조건
			setcookie($cookie_name,$bs_seq,0,'/');
		}

		$redirect_url = "/goods/view?no=".$goods_seq;
		pageRedirect($redirect_url);
	}

	public function access()
	{
		$this->load->library('validation');

		$param	= str_replace(" ","+",$this->input->get('param'));
		$param	= unserialize(base64_decode($param), ['allowed_classes' => false]);

		$this->validation->set_data($param);
		$this->validation->set_rules('member_seq', '회원일련번호', 'trim|numeric|xss_clean');
		$this->validation->set_rules('inflow', '유입', 'trim|alpha_dash|xss_clean');
		$this->validation->set_rules('send_date', '기간', 'trim|string|xss_clean');
		if ($this->validation->exec() === false) {
			show_error($this->validation->error_array['value'],422);
		}

		## 정상적인 접근일때만
		if($param['inflow'] != "shorturl"){
			
			$nowdt		= date("Y-m-d H:i:s",mktime());
			$senddt		= $param['send_date'];

			$datetime1	= strtotime($nowdt);
			$datetime2	= strtotime($senddt);
			$interval	= $datetime1 - $datetime2;
	
			if($senddt && $interval > 60){

				$this->load->helper('reservation');

				## 접속환경
				$access_type = access_config();

				### 고객 리마인드 서비스 관련 테이블
				### fm_log_curation : 유입로그
				### fm_log_curation_info : 유입후 활동 로그
				### fm_log_curation_sms : sms 발송로그
				### fm_log_curation_email : email 발송로그
				### fm_log_curation_summary : 발송/유입통계데이터

				### 고객 리마인드 서비스 발송 구분
				/*
				$personal_gubun	= array('coupon'	=>'이번주 만료될 할인쿠폰',
										'emoney'	=>'마일리지',
										'membership'=>'멤버쉽서비스',
										'cart'		=>'장바구니/위시리스트',
										'timesale'	=>'판매종료',
										'review'	=>'상품리뷰',
										'wish'			=>'위시리스트',
										'birthday'		=>'생일축하쿠폰',
										'anniversary'	=>'기념일축하쿠폰'
									);
				*/
				if($param['inflow'] == "email_timesale_cart" || $param['inflow'] == "email_timesale_wish"){
					$inflow = "email_timesale";
				}else{
					$inflow		= $param['inflow'];
				}
				$inflow_tmp		= explode("_",$inflow);
				if(in_array($inflow_tmp[0],array("email","sms"))){

					$inflow_type	= strtoupper($inflow_tmp[0]);
					$inflow_kind	= $inflow_tmp[1];
					$personal_gubun = curation_menu();
					foreach($personal_gubun as $k=>$v){
						if($inflow_kind == "wish"){
							$inflow_kind = "cart";
						}
						if(strstr($v['name'],$inflow_kind)){
							$personal_title = $v['title'];
						}
					}
					$inflow_subject	= $personal_title;
					//$click_zone		= mypage_url($param['inflow'],$param['member_seq']);
					$senddtsearch	= date("Y-m-d",$datetime2);

					## 유입 로그.
					$this->db->where('member_seq', $param['member_seq']);
					$this->db->where('inflow_kind', $inflow_kind);
					$this->db->where('inflow_type', $inflow_type);
					$this->db->where('send_date', date("Y-m-d",mktime()));
					$this->db->from('fm_log_curation');
					$this->db->select('count(*) cnt');

					$query	= $this->db->get();
					$result = $query->result_array();
					$curation_cnt = $result[0]['cnt'];

					$this->db->where('member_seq', $param['member_seq']);
					$this->db->where('inflow_kind', $inflow_kind);
					$this->db->where('inflow_type', $inflow_type);
					$this->db->where('access_type', $access_type);
					$this->db->where("send_date BETWEEN '{$senddtsearch} 00:00:00' AND '{$senddtsearch} 23:59:59'");
					$this->db->from('fm_log_curation');
					$this->db->select('curation_seq');

					$query	= $this->db->get();
					$result = $query->result_array();
					$curation_seq = $result[0]['curation_seq'];

					## 문자발송건이 pc로 접속했을때 => 비정상접근으로 체크
					if($inflow_type=="SMS" && $access_type == "PC"){
						$except_use = "y";
					}
					## 유입로그 저장
					if(!$curation_seq && !$curation_cnt && $except_use != "y"){

						$this->db->where('member_seq', $param['member_seq']);
						$this->db->where('inflow_kind', $inflow_kind);
						$this->db->where('inflow_type', $inflow_type);
						$this->db->where("send_date BETWEEN '{$senddtsearch} 00:00:00' AND '{$senddtsearch} 23:59:59'");
						$this->db->from('fm_log_curation');
						$this->db->select('count(*) cnt');

						$query	= $this->db->get();
						$result = $query->result_array();
						$summary_cnt = $result[0]['cnt'];

						if(!$summary_cnt) $summary_cnt = 0;

						$this->db->where('member_seq', $param['member_seq']);
						$this->db->from('fm_member');
						$this->db->select('userid');

						$query = $this->db->get();
						$result = $query->result_array();
						$userid = $result[0]['userid'];

						$this->db->where('member_seq', $param['member_seq']);
						$this->db->where('kind', $inflow_kind);
						$this->db->where("regist_date BETWEEN '{$senddtsearch} 00:00:00' AND '{$senddtsearch} 23:59:59'");
						$this->db->from('fm_log_curation_sms');
						$this->db->select('to_mobile,sms_msg');
						
						$query = $this->db->get();
						$result = $query->result_array();
						$to_reception = $result[0]['to_mobile'];
						$to_msg	= $result[0]['sms_msg'];

						$params = array();
						$params['member_seq']	= $param['member_seq'];
						$params['userid']		= $userid;
						$params['to_reception'] = $to_reception;
						$params['to_msg']		= $to_msg;
						$params['send_date']	= $param['send_date'];
						$params['inflow_kind'] = $inflow_kind;
						$params['inflow_subject']= $inflow_subject;
						$params['inflow_type']	= $inflow_type;
						$params['access_type']	= $access_type;
						$params['click_zone']	= $click_zone;
						$params['referer']		= $_SERVER['HTTP_REFERER'];
						$params['regist_date']= date("Y-m-d H:i:s",mktime());

						$result			= $this->db->insert('fm_log_curation', $params);
						$curation_seq	= $this->db->insert_id();
						### 발송 통계
						if($summary_cnt == 0){

							$this->db->where('inflow_kind', $inflow_kind);
							$this->db->where('send_date', $senddtsearch);
							$this->db->from('fm_log_curation_summary');
							$this->db->select('count(*) as cnt');
							$query = $this->db->get();
							$result = $query->result_array();
							$to_reception = $result[0]['cnt'];

							if ($result[0]['cnt'] > 0) {
								$updateSet = [
									"inflow_".$inflow_tmp[0]."_total" => "inflow_".$inflow_tmp[0]."_total+1"
								];
								$this->db->where('inflow_kind', $inflow_kind);
								$this->db->where('send_date', $senddtsearch);
								$this->db->update('fm_log_curation_summary', $updateSet);
							} else {
								$insertSet = [
									"inflow_kind" => $inflow_kind,
									"inflow_".$inflow_tmp[0]."_total" => 1,
									'send_date' => $senddtsearch,
								];
								$this->db->insert('fm_log_curation_summary', $insertSet);
							}
						}
					}
					
					## curation session 생성
					$curation = array();
					$curation['member_seq']		= $param['member_seq'];
					$curation['inflow']			= $inflow;
					$curation['curation_seq']	= $curation_seq;

					## Cookie생성 : 접속일 + 2일 자정전까지 유지
					$mktime			= strtotime(date("Y-m-d H:i:s")." +2 days");
					$dt				= date("Y-m-d 23:59:59",$mktime);
					$curationtmp	= implode("^",$curation);
					setcookie("curation",$curationtmp,$mktime,'/');
					$_COOKIE["curation"] = $curationtmp;

					if($param['inflow'] == "email_timesale_cart" || $param['inflow'] == "email_timesale_wish"){
						$inflow_kind = $inflow_tmp[2];
					}

					## 이동 페이지 지정
					switch(strtolower($inflow_kind)){
						case "coupon":		$redirect_url = "/mypage/coupon"; break;
						case "emoney":		$redirect_url = "/mypage/emoney"; break;
						case "membership":	$redirect_url = "/mypage"; break;
						case "cart":		$redirect_url = "/order/cart"; break;
						case "wish":		$redirect_url = "/mypage/wish"; break;
						case "review":		$redirect_url = "/mypage/mygdreview_catalog"; break;
						case "birthday":	$redirect_url = "/mypage/coupon?tab=2"; break;
						case "anniversary":	$redirect_url = "/mypage/coupon?tab=2"; break;
						default: $redirect_url = "/index"; break;
					}

					## 로그인 후 페이지 이동
					if(!$this->session->userdata['user']){
						 $redirect_url = "/member/login?return_url=". urlencode($redirect_url);
					}

					pageRedirect($redirect_url,'','parent');

				}else{
					pageRedirect('/mypage','','parent');
				}
			}else{
				pageRedirect('/mypage','','parent');
			}

		}else{
			pageRedirect('/mypage','','parent');
		}
	}
}

