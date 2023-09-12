<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');
require_once(APPPATH.'controllers/base/admin_base'.EXT);

class batch_payments extends admin_base {
	
	protected $pid;
	protected $queueID;
	protected $limittotalnum;
	protected $totalCount;
	protected $params;
	//protected $nameDomain;
	public $aCategory = array(
				11 => "마일리지",
				12 => "포인트",
				13 => "쿠폰");

	function __construct(){
        parent::__construct();

		//파라미터 셋팅
		$this->pid				= getmypid();
		$this->queueID			= $_POST['queueID'];

		/*
		$arrSystem			= ($this->config_system) ? $this->config_system : config_load('system');
		$arr_sub_domain		= explode(".",$arrSystem['subDomain']);
		$this->nameDomain	= sprintf("%s","{$arr_sub_domain['0']}"); //기본 도메인 셋팅
		*/

		//state 0:대기, 1:작업중, 2:완료
		//업데이트 하고 엑셀 생성
		$query	 = "SELECT category, context, count, limit_count FROM fm_queue WHERE id = ? AND state = ?";
		$queryDB = $this->db->query($query, array($this->queueID, 0));
		$res	 = $queryDB->result_array();
		if(!$res){
			echo "No Datas!";
			exit;
		}
		
		$this->limittotalnum = $res[0]['limit_count'] === NULL ? 10000 : $res[0]['limit_count'];	
		$this->totalCount = $res[0]['count'];	

		$this->params = array();
		if($res[0]['category'] == 13){ //쿠폰일 경우
			parse_str($res[0]['context'], $this->params);
		} else { //쿠폰 이외는 조건절이 좀 복잡하여 아래처럼...
			$tempArr = explode("&",urldecode($res[0]['context']));
			foreach($tempArr as $k){
				$tmp = explode("=",$k);
				$this->params[$tmp[0]] = $tmp[1];				
			}
		}

		$this->db->where('id', $this->queueID);
		$this->db->update('fm_queue', array('state' => 1, 'pid' => $this->pid)); //상태 진행중으로 업데이트
		//$this->db->update('fm_queue', array('state' => 99, 'pid' => $this->pid)); //테스트 업데이트

		ini_set("memory_limit", -1);
		set_time_limit(0);

		echo "Start a Job No.".$this->queueID."!\n";
    }

	//쿠폰 대량 적립 18.04.04 kmj
	public function set_coupon(){
		$this->load->helper('file');
		$this->load->model('membermodel');
		$this->load->model('couponmodel');

		$params		= $this->params;
		$dataBatch	= array();
		$regDate	= date("Y-m-d");
		$regTime	= date("H:i:s");

		$coupons = $this->couponmodel->get_coupon($params['no']);
		$columns = $this->db->list_fields('fm_download');
		
		$dataArr = array();
		foreach($columns as $cNames){
			$dataArr[$cNames] = $coupons[$cNames];

			if($cNames == "issue_type" && empty($coupons['issue_type'])){
				$dataArr['issue_type'] = "all";
			}

			if($cNames == "salescost_admin" && empty($coupons['salescost_admin'])){
				$dataArr['salescost_admin'] = 100;
			}
			
			if($cNames == "salescost_provider" && empty($coupons['salescost_provider'])){
				$dataArr['salescost_provider'] = 0;
			}

			if($coupons['issue_priod_type'] == 'day'){
				$dataArr['issue_startdate']	= $regDate;
				$dataArr['issue_enddate']	= date("Y-m-d", strtotime("+ ".$coupons['after_issue_day']." days"));
			}else{
				$dataArr['issue_startdate']	= $coupons['issue_startdate'];
				$dataArr['issue_enddate']	= $coupons['issue_enddate'];
			}
		}
		$dataArr['use_status']		= 'unused';
		$dataArr['regist_date']		= $regDate." ".$regTime;

		$couponCategory	= $this->couponmodel->get_coupon_issuecategory($coupons['coupon_seq']);
		$couponGoods	= $this->couponmodel->get_coupon_issuegoods($coupons['coupon_seq']);
		
		$loopCount	= ceil($this->totalCount/$this->limittotalnum);
		for($i=0; $i<$loopCount; $i++){
			$params['page']		= $i * $this->limittotalnum;
			$params['perpage']	= $this->limittotalnum;
			$params['is_member'] = true;

			$memberArr = $this->membermodel->coupon_member_list($params);

			unset($this->db->queries);
			unset($this->db->query_times);
			$totalCnt = 0;
			foreach($memberArr as $member_seq){
				// 쿠폰 정보 확인
				$memberCoupon = $this->couponmodel->get_admin_download($member_seq, $params['no']);
				if($memberCoupon) continue; //이미 다운받은 쿠폰이 있음.

				$dataArr['member_seq']  = $member_seq;
				$dataBatch[]			= $dataArr;

				$totalCnt++;
			}

			$this->db->insert_batch("fm_download", $dataBatch, NULL, $this->limittotalnum);
			$firstId = $this->db->insert_id();
			$lastId  = $firstId + $totalCnt;
		
			unset($this->db->queries);
			unset($this->db->query_times);
			unset($memberArr, $dataBatch);

			//카테고리 쿠폰 입력
			if($couponCategory){
				foreach($couponCategory	as $v){
					$dataBatchCategory = array();
					for($seq=$firstId; $seq<$lastId; $seq++){
						$dataCategory					= array();
						$dataCategory['category_code']	= $v['category_code'];
						$dataCategory['type']			= $v['type'];
						$dataCategory['download_seq']	= $seq;
						$dataBatchCategory[]			= $dataCategory;
					}
					$this->db->insert_batch("fm_download_issuecategory", $dataBatchCategory);

					unset($this->db->queries);
					unset($this->db->query_times);
				}
			}
			unset($dataBatchCategory);

			//상품 쿠폰 입력
			if($couponGoods){	
				foreach($couponGoods as $v){
					$dataBatchGoods = array();
					for($seq=$firstId; $seq<$lastId; $seq++){
						$dataGoods					= array();
						$dataGoods['goods_seq']		= $v['goods_seq'];
						$dataGoods['type']			= $v['type'];
						$dataGoods['download_seq']	= $seq;
						$dataBatchGoods[]		= $dataGoods;
					}
					$this->db->insert_batch("fm_download_issuegoods", $dataBatchGoods);
					
					unset($this->db->queries);
					unset($this->db->query_times);
				}
			}
			unset($dataBatchGoods);
		}
		unset($couponCategory, $couponGoods);

		echo "Complete ".$totalCnt."명에게 지급 되었습니다.\n";

		$this->db->reconnect();

		$this->db->where('id', $this->queueID);
		$this->db->update('fm_queue', array(
				'state'		=> 2, 
				'com_date'	=> date('Y-m-d H:i:s')
			)
		);
		exit;
	}

	//마일리지 대량 적립 18.03.08 kmj
	public function set_payments(){

		$this->load->helper('file');
		$this->load->model('membermodel');
		
		$aPostParams 				= $this->input->post();
		$params						= $this->params;
		$params['excel_spout']		= true;
		$params['callPage']			= $aPostParams['callPage'];
		$params[$aPostParams['callPage']]	= $aPostParams['amount'];
		$params['gb']				= $aPostParams['gb'];
		$params['memo']				= $aPostParams['memo'];
		$params['limit_date']		= $aPostParams['limit_date'];
		$params['batchProcess']		= 'y';
		$category					= $aPostParams['callPage'];
		$amount						= $aPostParams['amount'];

		$loopCount	= ceil($this->totalCount/$this->limittotalnum);

		$data = array();
		$data = filter_keys($params, $this->db->list_fields("fm_{$category}"));
		$data['regist_date'] = date("Y-m-d H:i:s");

		write_file(ROOTPATH."/data/tmp/{$category}_set_query_batch_".date('YmdHis', strtotime($data['regist_date'])).".txt", serialize($params), 'a');

		$totalCnt = 0;

		unset($this->db->queries);
		unset($this->db->query_times);
		for($i=0; $i<$loopCount; $i++){
			$params['page']		= $i * $this->limittotalnum;
			$params['perpage']	= $this->limittotalnum;
			$params['is_member'] = true;

			//$memberArr = $this->membermodel->admin_member_list($params);
			$memberArr = $this->membermodel->admin_member_list_spout($params);
			
			$dataBatch	 = array();
			$dataBatchUp = array();
			foreach($memberArr as $k){
				$dataArr				= $data;
				$dataArr['member_seq']	= $k;
				$dataArr[$category]		= $amount;
				$dataArr['remain']		= $amount;
				$dataBatch[] = $dataArr;

				$dataArrUp = array();
				$dataArrUp['member_seq'] = $k;
				$dataArrUp[$category]	 = "`{$category}`+".$amount;
				$dataBatchUp[] = $dataArrUp;

				$totalCnt++;
			}

			$this->db->insert_batch("fm_{$category}", $dataBatch);
			$this->db->update_batch("fm_member", $dataBatchUp, 'member_seq', false); //escape 여부

			write_file(ROOTPATH."/data/tmp/{$category}_set_query_batch_".date('YmdHis', strtotime($data['regist_date'])).".txt", serialize($this->db->queries), 'a');
		
			unset($this->db->queries);
			unset($this->db->query_times);
		}

		$this->db->reconnect();

		echo "Complete ".$totalCnt."명에게 지급 되었습니다.\n";

		$this->db->where('id', $this->queueID);
		$this->db->update('fm_queue', array(
				'state'		=> 2, 
				'com_date'	=> date('Y-m-d H:i:s')
			)
		);
		exit;
	}

}
