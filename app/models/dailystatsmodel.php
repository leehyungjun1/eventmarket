<?
class dailystatsmodel extends CI_Model {
	/*
		GET으로 ?debughr=1 을 넣어주면 넣을 데이터만 뿌려주고 멈춤

		각 행위 통계집계 함수에 수동으로 해당 날짜를 넣으면 그 날짜 집계하도록 함

		만약 수집하는 시간에 상품이 지워졌다면 그 상품에 대한 값을 수집 하지 않는다

		[보다]만 실시간으로 운영되기 때문에 로그테이블이 따로 존재함 해당 일 로그테이블은 통계 정리 후 30일 이전 데이터는 삭제함

		로그는 /data/cronlog/daily_stats_{date}.log 에 쌓임

		2016-03-11
		집계방식 변경
			공통
				전날 기준 데이터를 수집 하고 , CASE1 ,CASE2에서 사용할 데이터를 집계함
				집계테이블의 데이터 보존기간은 최대 7개월
				로우데이터는 1년 정도로 보관하는데 데이터를 백업 하는지 여부는 추후 정의 받기로 했음
			case 1
				일~토 7일 단위로 묶어서 데이터를 집계함
				unique key 상품
				날짜의 형식은
				ex) 20160311일 기준 2016030620160312 일주일 단위로 묶어놨음
			case 2
				1개월 3개월 6개월 테이블을 생성하여 집계함
				unique key 는 날짜 상품 회원
				날짜 형식은 기본 날짜 형식으로 하였음
	*/
	function __construct() {
		parent::__construct();
		$this->now_date						= date('Y-m-d', strtotime('-1day'));
		$this->log_delete_date				= date('Y-m-d', strtotime('-7month'));
		$this->now_year						= date('Y');
		$this->table_goods					= 'fm_goods';
		$this->table_goods_option			= 'fm_goods_option';
		$this->table_goods_suboption		= 'fm_goods_suboption';
		$this->table_member					= 'fm_member';
		$this->table_view_log				= 'fm_daily_stats_view_log';
		$this->table_view					= 'fm_daily_stats_view_vw';
		$this->table_view_row				= 'fm_daily_stats_view_row';
		$this->table_goods_review			= 'fm_goods_review';
		$this->table_review					= 'fm_daily_stats_review_vw';
		$this->table_review_row				= 'fm_daily_stats_review_row';
		$this->table_goods_fblike			= 'fm_goods_fblike';
		$this->table_fblike					= 'fm_daily_stats_fblike_vw';
		$this->table_fblike_row				= 'fm_daily_stats_fblike_row';
		$this->table_goods_restock_notify	= 'fm_goods_restock_notify';
		$this->table_goods_restock_option	= 'fm_goods_restock_option';
		$this->table_restock_notify			= 'fm_daily_stats_restock_notify_vw';
		$this->table_restock_notify_row		= 'fm_daily_stats_restock_notify_row';
		$this->table_goods_wish				= 'fm_goods_wish';
		$this->table_wish					= 'fm_daily_stats_wish_vw';
		$this->table_wish_row				= 'fm_daily_stats_wish_row';
		$this->table_goods_cart				= 'fm_cart';
		$this->table_cart_option			= 'fm_cart_option';
		$this->table_cart_suboption			= 'fm_cart_suboption';
		$this->table_cart					= 'fm_daily_stats_cart_vw';
		$this->table_cart_row				= 'fm_daily_stats_cart_row';
		$this->table_goods_order			= 'fm_order';
		$this->table_order_item				= 'fm_order_item';
		$this->table_order_option			= 'fm_order_item_option';
		$this->table_order_suboption		= 'fm_order_item_suboption';
		$this->table_order					= 'fm_daily_stats_order_vw';
		$this->table_order_row				= 'fm_daily_stats_order_row';
		$this->table_other_mall				= 'fm_daily_stats_other_mall';
		
		$this->table_sales_price			= 'fm_daily_stats_sales_price';	// 판매금액 통계 테이블
		

		##CASE1 테이블
		$this->view_table	= array(
			'fblike'			=> 'fm_daily_stats_fblike_vw',
			'review'			=> 'fm_daily_stats_review_vw',
			'restock_notify'	=> 'fm_daily_stats_restock_notify_vw',
			'wish'				=> 'fm_daily_stats_wish_vw',
			'cart'				=> 'fm_daily_stats_cart_vw',
			'order'				=> 'fm_daily_stats_order_vw',
			'view'				=> 'fm_daily_stats_view_vw',
			'recently'			=> 'fm_goods'
		);

		##CASE2 테이블
		$this->view_arr = array(
			"view_table_m_1" => array(
				'fblike'			=> 'fm_daily_stats_fblike_m_1',
				'review'			=> 'fm_daily_stats_review_m_1',
				'restock_notify'	=> 'fm_daily_stats_restock_notify_m_1',
				'wish'				=> 'fm_daily_stats_wish_m_1',
				'cart'				=> 'fm_daily_stats_cart_m_1',
				'order'				=> 'fm_daily_stats_order_m_1',
				'view'				=> 'fm_daily_stats_view_m_1'
			),
			"view_table_m_3" => array(
				'fblike'			=> 'fm_daily_stats_fblike_m_3',
				'review'			=> 'fm_daily_stats_review_m_3',
				'restock_notify'	=> 'fm_daily_stats_restock_notify_m_3',
				'wish'				=> 'fm_daily_stats_wish_m_3',
				'cart'				=> 'fm_daily_stats_cart_m_3',
				'order'				=> 'fm_daily_stats_order_m_3',
				'view'				=> 'fm_daily_stats_view_m_3'
			),
			"view_table_m_6" => array(
				'fblike'			=> 'fm_daily_stats_fblike_m_6',
				'review'			=> 'fm_daily_stats_review_m_6',
				'restock_notify'	=> 'fm_daily_stats_restock_notify_m_6',
				'wish'				=> 'fm_daily_stats_wish_m_6',
				'cart'				=> 'fm_daily_stats_cart_m_6',
				'order'				=> 'fm_daily_stats_order_m_6',
				'view'				=> 'fm_daily_stats_view_m_6'
			)
		);

		##빅데이터 로우 테이블
		$this->row_table	= array(
			'fblike'			=> 'fm_daily_stats_fblike_row',
			'review'			=> 'fm_daily_stats_review_row',
			'restock_notify'	=> 'fm_daily_stats_restock_notify_row',
			'wish'				=> 'fm_daily_stats_wish_row',
			'cart'				=> 'fm_daily_stats_cart_row',
			'order'				=> 'fm_daily_stats_order_row',
			'view'				=> 'fm_daily_stats_view_row'
		);

		##데이터 넣는 함수
		$this->funcList						= array(
			'daily_order',
			'daily_fblike',
			'daily_review',
			'daily_restock_notify',
			'daily_wish',
			'daily_cart',
			'daily_view',
			'daily_sales_price'
		);

	}

	public function view_log($param){
		$sess = $this->session->userdata('user');

		$view_log['regist_date']	= date('Y-m-d');
		$view_log['goods_seq']		= $param['goods_seq'];
		$view_log['goods_name']		= $param['goods_name'];
		$view_log['agent']			= $_SERVER['HTTP_USER_AGENT'];
		$view_log['provider_seq']	= $param['provider_seq'] ? $param['provider_seq'] : 1;
		$view_log['goods_kind']		= $param['goods_kind'];
		$view_log['mtype']			= $sess['member_type'] ? $sess['member_type'] : 'none';
		$view_log['sex']			= $sess['sex'] ? $sess['sex'] : 'none';
		$view_log['birthday']		= $sess['birthday'] ? $sess['birthday'] : '0000-00-00';
		$view_log['ip']				= $_SERVER["REMOTE_ADDR"];
		$this->db->insert($this->table_view_log,$view_log);
	}

	## 크론잡 파일로그
	function cron_file_log($filename, $content){
		$logDir = ROOTPATH."/data/cronlog/";
		if(!is_dir($logDir)){
			mkdir($logDir);
			@chmod($logDir,0777);
		}
		$fp = fopen($logDir.$filename,"a+");
		fwrite($fp,"[".date('Y-m-d H:i:s')."] - ");
		fwrite($fp,$content . "\r\n");

		fclose($fp);
	}

	public function exec_func($custom_date = null){
		foreach($this->funcList as $funcName){
			$this->cron_file_log('daily_stats_'.date('Ym').'.log', $this->now_date.' - '.$funcName.' start');
			$this->$funcName($custom_date);
			$this->cron_file_log('daily_stats_'.date('Ym').'.log', $this->now_date.' - '.$funcName.' success');
		}
		//CASE1 집계
		$this->cron_file_log('daily_stats_'.date('Ym').'.log', $this->now_date.' - case1 start');
		$this->case1($custom_date);
		$this->cron_file_log('daily_stats_'.date('Ym').'.log', $this->now_date.' - case1 success');
		//CASE2 집계
		$this->cron_file_log('daily_stats_'.date('Ym').'.log', $this->now_date.' - case2 start');
		$this->case2($custom_date);
		$this->cron_file_log('daily_stats_'.date('Ym').'.log', $this->now_date.' - case2 success');
		//7개월 이전 뷰 삭제
		$this->cron_file_log('daily_stats_'.date('Ym').'.log', $this->now_date.' - delete start');
		$this->insert_view_table($custom_date);
		$this->cron_file_log('daily_stats_'.date('Ym').'.log', $this->now_date.' - delete success');
	}

	## 결제수단
	public function get_payment($payment,$pg){
		$ret = '';
		$pay_arr = array(
			"bank"					=> 'daily_payment_1',
			"card"					=> 'daily_payment_2',
			"cellphone"				=> 'daily_payment_3',
			"account"				=> 'daily_payment_4',
			"virtual"				=> 'daily_payment_5',
			"escrow_account"		=> 'daily_payment_6',
			"escrow_virtual"		=> 'daily_payment_7',
			"pos_pay"				=> 'daily_payment_11'
		);
		$pay['card']['kakaopay']	= 'daily_payment_8';
		$pay['card']['naverpay']	= 'daily_payment_9';
		$pay['card']['payco']		= 'daily_payment_10';

		if($pg){
			$ret = $pay[$payment][$pg];
		}else{
			$ret = $pay_arr[$payment];
		}
		return $ret;
	}

	## 생년월일로 연령별 데이터 뽑기
	public function member_age_get($birth){
		$age = 'none';
		if($birth != '0000-00-00' && $birth && $birth != ''){
			$age = $this->now_year-substr($birth,0,4)+1;
			if($age < 10){
				$age = 10;
			}else if($age > 70){
				$age = 70;
			}else{
				$age = substr($age,0,1).'0';
			}
		}
		return $age;
	}

	public function getWeekStartEndDate($date = null){
		if(!$date) $date = $this->now_date;
		$dateArr	= explode('-',$date);
		$y			= $dateArr[0];
		$m			= $dateArr[1];
		$d			= $dateArr[2];
		$week		= floor($d/7) +1;
		$week_start	= $dateArr[2]%7;
		$no			= date('w', mktime(0, 0, 0, $m, $d, $y));
		$no++;
		if	($week_start>$no)
			$week++;
		sscanf(date('tw',mktime(0,0,0,$m,1,$y)),'%2d%d',$t,$w);
		$tweek = ceil(($t+$w)/7);

		$sun = (1-$w)+7*($week-1);

		$s_d = date('Y-m-d',mktime(0,0,0,$m,$sun,$y));
		$e_d = date('Y-m-d',mktime(0,0,0,$m,$sun+6,$y));

		return array('start_date'=>$s_d,'end_date'=>$e_d);
	}

	## CASE1 7일 단위로 묶어서 집계한다
	public function case1($date,$custom_view_table=null){
		$week_date	= $this->getWeekStartEndDate($date);
		$start_date	= $week_date['start_date'];
		$end_date	= $week_date['end_date'];
		$unique		= $start_date.$end_date;
		$unique		= str_replace('-','',$unique);
		$view_table = ($custom_view_table)?$custom_view_table:$this->view_table;
				
		foreach($view_table as $kind => $table){
		if	($table == 'fm_goods') continue;
		$sql		= "
						select
							daily_goods_seq,daily_goods_name,sum(daily_price) as daily_price,sum(daily_price_sale) as daily_price_sale,sum(daily_ea) as daily_ea,sum(daily_pc) as daily_pc,sum(daily_mobile) as daily_mobile,sum(daily_payment_1) as daily_payment_1,sum(daily_payment_2) as daily_payment_2,sum(daily_payment_3) as daily_payment_3,sum(daily_payment_4) as daily_payment_4,sum(daily_payment_5) as daily_payment_5,sum(daily_payment_6) as daily_payment_6,sum(daily_payment_7) as daily_payment_7,sum(daily_payment_8) as daily_payment_8,sum(daily_payment_9) as daily_payment_9,sum(daily_payment_10) as daily_payment_10,sum(daily_member_mem) as daily_member_mem,sum(daily_member_biz) as daily_member_biz,sum(daily_member_none) as daily_member_none,sum(daily_sex_male) as daily_sex_male,sum(daily_sex_female) as daily_sex_female,sum(daily_sex_none) as daily_sex_none,sum(daily_age_10) as daily_age_10,sum(daily_age_20) as daily_age_20,sum(daily_age_30) as daily_age_30,sum(daily_age_40) as daily_age_40,sum(daily_age_50) as daily_age_50,sum(daily_age_60) as daily_age_60,sum(daily_age_70) as daily_age_70,sum(daily_age_none) as daily_age_none,sum(daily_mall_own) as daily_mall_own,sum(daily_mall_other) as daily_mall_other,sum(daily_score_5) as daily_score_5,sum(daily_score_4) as daily_score_4,sum(daily_score_3) as daily_score_3,sum(daily_score_2) as daily_score_2,sum(daily_score_1) as daily_score_1,sum(daily_score_0) as daily_score_0,
							count(daily_seq) as daily_cnt
							from
								".$this->row_table[$kind]."
							where
								daily_date between '".$start_date."' and '".$end_date."'
							group by
								daily_goods_seq
					";
			$rs		= mysqli_query($this->db->conn_id,$sql);

			if(mysqli_num_rows($rs)){
				$del_sql = "delete from ".$table." where daily_date = '".$unique."'";
				mysqli_query($this->db->conn_id,$del_sql);
				while($pprs = mysqli_fetch_assoc($rs)){
					$set_arr = array();
					$set = "daily_date = '".$unique."'";
					foreach($pprs as $kk => $vv){
						$set_arr[] = $kk."='".$vv."'";
					}
					$set = $set.','.implode(',',$set_arr);
					$sql = "insert into ".$table." set ".$set;
					mysqli_query($this->db->conn_id,$sql);
				}
			}
		}
	}

	## CASE2 1,3,6개월 나눠서 수집 한다.
	public function case2($date = null,$custom_view_arr=null){
		$view_arr = ($custom_view_arr)?$custom_view_arr:$this->view_arr;

		$s_temp = array(180,149,119,89,59,29);
		$e_temp = array(150,120,90,60,30,1);
		//custom 날짜가 들어오면 오늘날짜 인 것 까지 다 넣는다
		if	($date) {
			$e_temp = array_slice($e_temp,0,5);
			$e_temp[5] = 0;
		}
		$m_table = array('view_table_m_6','view_table_m_3','view_table_m_1');
		foreach($m_table as $m_t){
			$start_i = 0;
			foreach($view_arr[$m_t] as $kind => $table)
				truncate_to_drop($table, $this->db->conn_id);				
			if($m_t == 'view_table_m_3') $start_i = 3;
			if($m_t == 'view_table_m_1') $start_i = 5;
			for($i=$start_i; $i<6; $i++){
				$start_date = date('Y-m-d',strtotime("-".$s_temp[$i]." day"));
				$end_date = date('Y-m-d',strtotime("-".$e_temp[$i]." day"));

				foreach($view_arr[$m_t] as $kind => $table){
					$sql	= "
								insert into  ".$table." (daily_date,daily_goods_seq,daily_member_seq,daily_goods_name,daily_price,daily_price_sale,daily_ea,daily_pc,daily_mobile,daily_payment_1,daily_payment_2,daily_payment_3,daily_payment_4,daily_payment_5,daily_payment_6,daily_payment_7,daily_payment_8,daily_payment_9,daily_payment_10,daily_payment_11,daily_member_mem,daily_member_biz,daily_member_none,daily_sex_male,daily_sex_female,daily_sex_none,daily_age_10,daily_age_20,daily_age_30,daily_age_40,daily_age_50,daily_age_60,daily_age_70,daily_age_none,daily_mall_own,daily_mall_other,daily_score_5,daily_score_4,daily_score_3,daily_score_2,daily_score_1,daily_score_0,daily_cnt)
								select
								daily_date,daily_goods_seq,ifnull(daily_member_seq,daily_ip) as daily_member_seq_new,daily_goods_name,sum(daily_price) as daily_price,sum(daily_price_sale) as daily_price_sale,sum(daily_ea) as daily_ea,sum(daily_pc) as daily_pc,sum(daily_mobile) as daily_mobile,sum(daily_payment_1) as daily_payment_1,sum(daily_payment_2) as daily_payment_2,sum(daily_payment_3) as daily_payment_3,sum(daily_payment_4) as daily_payment_4,sum(daily_payment_5) as daily_payment_5,sum(daily_payment_6) as daily_payment_6,sum(daily_payment_7) as daily_payment_7,sum(daily_payment_8) as daily_payment_8,sum(daily_payment_9) as daily_payment_9,sum(daily_payment_10) as daily_payment_10,sum(daily_payment_11) as daily_payment_11,sum(daily_member_mem) as daily_member_mem,sum(daily_member_biz) as daily_member_biz,sum(daily_member_none) as daily_member_none,sum(daily_sex_male) as daily_sex_male,sum(daily_sex_female) as daily_sex_female,sum(daily_sex_none) as daily_sex_none,sum(daily_age_10) as daily_age_10,sum(daily_age_20) as daily_age_20,sum(daily_age_30) as daily_age_30,sum(daily_age_40) as daily_age_40,sum(daily_age_50) as daily_age_50,sum(daily_age_60) as daily_age_60,sum(daily_age_70) as daily_age_70,sum(daily_age_none) as daily_age_none,sum(daily_mall_own) as daily_mall_own,sum(daily_mall_other) as daily_mall_other,sum(daily_score_5) as daily_score_5,sum(daily_score_4) as daily_score_4,sum(daily_score_3) as daily_score_3,sum(daily_score_2) as daily_score_2,sum(daily_score_1) as daily_score_1,sum(daily_score_0) as daily_score_0,
								count(daily_seq) as daily_cnt
								from
									".$this->row_table[$kind]."
								where
									daily_date between '".$start_date."' and '".$end_date."'
									group by daily_date,daily_goods_seq,daily_member_seq_new
							";
					$rs		= mysqli_query($this->db->conn_id,$sql);
				}
			}
		}
	}

	public function insert_data($query,$table,$table_row,$now_date,$member_totalprice = null){
		$daily_insert		= array();
		$daily_other_mall	= array();
		$flag				= false;
		$default_option		= true;
		$cfg_reserve		= ($this->reserves) ? $this->reserves : config_load('reserve');
		$applypage			= 'cart';

		$isCart				= false;
		$isOrder			= false;

		if($table == 'fm_daily_stats_cart_vw') $isCart = true;
		if($table == 'fm_daily_stats_order_vw') $isOrder = true;


		//debug
		$debug = false;
		if($_GET['debughr']){
			$debug = true;
		}

		while ($daily = mysqli_fetch_assoc($query)){
			if(!$daily['goods_seq']) continue;

			## 주문은 입금일 기준으로 가져오기 때문에
			if($isOrder){
				$daily['regist_date'] = $daily['deposit_date'];
			}

			$daily['regist_date'] = substr($daily['regist_date'],0,10);

			if($daily['opt_type'] == 'sub') $default_option = false;

			$arr_group = $daily['regist_date'].'-'.$daily['goods_seq'];

			## 카테고리/브랜드/지역
			$category = $this->goodsmodel->get_goods_category_default($daily['goods_seq']);
			$brand = $this->goodsmodel->get_goods_brand_default($daily['goods_seq']);
			$location = $this->goodsmodel->get_goods_location_default($daily['goods_seq']);

			## 환경
			$agent = 'pc';
			if($table == 'fm_daily_stats_order_vw'){
				if($daily['sitetype'] == 'M')
					$agent = 'mobile';
			}else{
				if(isMobilecheck($daily['agent']))
					$agent = 'mobile';
			}

			## 행위자
			$mem = 'mem';
			if($daily['mtype'] == 'business')
				$mem = 'biz';
			if($daily['mtype'] == 'none')
				$mem = 'none';

			## 연령
			$age = $this->member_age_get($daily['birthday']);

			## 리뷰 일 경우 평점을 넣어준다
			if($table == 'fm_daily_stats_review')
				if(!$daily['score']) $daily['score'] = 0;

			## 장바구니, 주문 일 경우 상품 갯수도 넣어준다
			if($isCart || $isOrder){
				$daily_ea = $daily['sub_ea'];
				if($default_option)
					$daily_ea = $daily['ea'];

				if($isCart){
					$member_session = ($daily['member_seq'] && $daily['member_seq'] > 0) ? $daily['member_seq'] : $daily['session_id'];
					$member_seq = ($daily['member_seq'] && $daily['member_seq'] > 0) ? $daily['member_seq'] : 0;
					$group_seq = ($daily['member_seq'] && $daily['group_seq'] > 0) ? $daily['group_seq'] : 0;

					//--> sale library 적용
					$param['cal_type']				= 'list';
					$param['total_price']			= $member_totalprice[$member_session];
					$param['reserve_cfg']			= $cfg_reserve;
					$param['member_seq']			= $member_seq;
					$param['group_seq']				= $group_seq;
					$this->sale->set_init($param);
					$this->sale->preload_set_config($applypage);
					//<-- sale library 적용

					// 해당 상품의 전체 카테고리
					$category_tmp	= array();
					$tmp			= $this->goodsmodel->get_goods_category($daily['goods_seq']);
					foreach($tmp as $row) $category_tmp[] = $row['category_code'];

					unset($param, $sales);

					if($default_option){
						$param['option_type']		= 'option';
						$param['consumer_price']	= $daily['consumer_price'];
						$param['price']				= $daily['price'];
						$param['sale_price']		= $daily['price'];
						$param['ea']				= $daily['ea'];//장바구니수량
						$param['goods_ea']			= $daily['total_ea'];//전체상품의 수량
						$param['category_code']		= $category_tmp;
						$param['goods_seq']			= $daily['goods_seq'];
						$param['goods']				= $daily;
					}else{
						$param['option_type']		= 'suboption';
						$param['sub_sale']			= $daily['sub_sale'];
						$param['consumer_price']	= $daily['sub_consumer_price'];
						$param['price']				= $daily['sub_price'];
						$param['sale_price']		= $daily['sub_price'];
						$param['ea']				= $daily['sub_ea'];
						$param['goods_ea']			= $daily['total_ea'];
						$param['category_code']		= $category_tmp;
						$param['goods_seq']			= $daily['goods_seq'];
						$param['goods']				= $daily;
					}

					$this->sale->set_init($param);
					$sales						= $this->sale->calculate_sale_price();

					$this->sale->reset_init();
					$daily_insert[$arr_group]['daily_price']				+= $sales['one_sale_price'];
					$daily_insert[$arr_group]['daily_price_sale']			+= $sales['one_result_price'];
				}else{
					if($default_option){
						$sale_sum = $daily['coupon_sale']+$daily['member_sale']+$daily['fblike_sale']+$daily['mobile_sale']+$daily['promotion_code_sale']+$daily['referer_sale'];
					}else{
						$sale_sum = $daily['member_sale'];
					}

					$sale_price = $daily['price'];
					if($sale_sum > 0)
						$sale_price -= $sale_sum;
					$pg = $this->get_payment($daily['payment'],$daily['pg']);
				}
			}

			## 자사/외부
			$mall = 'own';
			if(isset($daily['linkage_mall_code']) && $daily['linkage_mall_code']){
				$mall = 'other';
			}

			##row data params
			$insert_row = array();
			$member_seq = $daily['member_seq'];
			$member_ip	= $daily['ip'] ? $daily['ip'] : $this->get_rand_ip();
			if	(!$member_seq)
				$member_seq = $member_ip;
			$insert_row['daily_member_seq']									= $daily['member_seq'];
			$insert_row['daily_ip']											= $member_ip;
			$insert_row['daily_date']										= $daily['regist_date'];
			$insert_row['daily_goods_seq']									= $daily['goods_seq'];
			$insert_row['daily_opt_type']									= $daily['opt_type'];
			if($default_option){
				$insert_row['daily_title1']	= $daily['title1'] ? $daily['title1'] : '';
				$insert_row['daily_option1']	= $daily['option1'] ? $daily['option1'] : '';
			}else{
				$insert_row['daily_title1']	= $daily['suboption_title'] ? $daily['suboption_title'] : '';
				$insert_row['daily_option1']	= $daily['suboption'] ? $daily['suboption'] : '';
			}
			$insert_row['daily_title2']										= $daily['title2'] ? $daily['title2'] : '';
			$insert_row['daily_option2']									= $daily['option2'] ? $daily['option2'] : '';
			$insert_row['daily_title3']										= $daily['title3'] ? $daily['title3'] : '';
			$insert_row['daily_option3']									= $daily['option3'] ? $daily['option3'] : '';
			$insert_row['daily_title4']										= $daily['title4'] ? $daily['title4'] : '';
			$insert_row['daily_option4']									= $daily['option4'] ? $daily['option4'] : '';
			$insert_row['daily_title5']										= $daily['title5'] ? $daily['title5'] : '';
			$insert_row['daily_option5']									= $daily['option5'] ? $daily['option5'] : '';
			$insert_row['daily_goods_name']									= $daily['goods_name'];
			$insert_row['daily_goods_kind']									= $daily['goods_kind'];
			$insert_row['daily_provider']									= $daily['provider_seq'];
			$insert_row['daily_category']									= $category['category_code'];
			$insert_row['daily_brand']										= $brand['category_code'];
			$insert_row['daily_location']									= $location['location_code'];
			$insert_row['daily_price']										= $daily['price'];
			$insert_row['daily_price_sale']									= $sale_price;
			$insert_row['daily_ea']											= $daily_ea;
			$insert_row['daily_'.$agent]									= 1;
			$insert_row['daily_member_'.$mem]								= 1;
			$insert_row['daily_sex_'.$daily['sex']]							= 1;
			$insert_row['daily_age_'.$age]									= 1;
			$insert_row['daily_mall_'.$mall]								= 1;
			if($pg) $insert_row[$pg]										= 1;
			if($daily['score']) $insert_row['daily_score_'.$daily['score']]	= 1;
            
			if($mall == 'other'){
				$other = array();
				$other['daily_date']		= $insert_row['daily_date'];
				$other['daily_goods_seq']	= $insert_row['daily_goods_seq'];
				$other['daily_opt_type']	= $insert_row['daily_opt_type'];
				$other['daily_title1']		= $insert_row['daily_title1'];
				$other['daily_option1']		= $insert_row['daily_option1'];
				$other['daily_title2']		= $insert_row['daily_title2'];
				$other['daily_option2']		= $insert_row['daily_option2'];
				$other['daily_title3']		= $insert_row['daily_title3'];
				$other['daily_option3']		= $insert_row['daily_option3'];
				$other['daily_title4']		= $insert_row['daily_title4'];
				$other['daily_option4']		= $insert_row['daily_option4'];
				$other['daily_title5']		= $insert_row['daily_title5'];
				$other['daily_option5']		= $insert_row['daily_option5'];
				$other['linkage_mall_code'] = $daily['linkage_mall_code'];
				## 외부몰 정보는 따로 쌓는다
				$daily_other_mall[] = array(
					'linkage_mall_code' => $daily['linkage_mall_code'],
					'data' => $other,
				);
				unset($other);
			}
            $sInsertQuery = $this->db->insert_string($table_row, $insert_row);
            $sInsertQuery = str_replace('INSERT INTO', 'INSERT IGNORE INTO', $sInsertQuery);
			
			if( !$debug ){
				mysqli_query($this->db->conn_id, $sInsertQuery);
			}else{
				echo '★['.$table.']['.$now_date.']★';
				debug($sInsertQuery);		
			}
		}

		if( !$debug ){
            $opt_type = $default_option ? 'opt' : 'sub';            

			## 외부몰 구매,리뷰 정보를 쌓는다 (리뷰는 추후 추가되면)
			if($daily_other_mall){
				$this->db->where(array(
					'daily_date'		=> $now_date,
					'daily_opt_type'	=> $opt_type
				));

				$this->db->delete($this->table_other_mall);

				foreach($daily_other_mall as $other){
					$other_insert = array(
						'daily_type'		=> 'order',
						'daily_date'		=> $other['data']['daily_date'],
						'daily_goods_seq'	=> $other['data']['daily_goods_seq'],
						'daily_opt_type'	=> $other['data']['daily_opt_type'],
						'daily_title1'		=> $other['data']['daily_title1'],
						'daily_option1'		=> $other['data']['daily_option1'],
						'daily_title2'		=> $other['data']['daily_title2'],
						'daily_option2'		=> $other['data']['daily_option2'],
						'daily_title3'		=> $other['data']['daily_title3'],
						'daily_option3'		=> $other['data']['daily_option3'],
						'daily_title4'		=> $other['data']['daily_title4'],
						'daily_option4'		=> $other['data']['daily_option4'],
						'daily_title5'		=> $other['data']['daily_title5'],
						'daily_option5'		=> $other['data']['daily_option5'],
						'linkage_mall_code'	=> $other['linkage_mall_code']
					);
					$this->db->insert($this->table_other_mall,$other_insert);
				}
			}
			$flag = true;
		}

		return $flag;
	}

	public function daily_view($custom_date = null){
		$now_date = $this->now_date;
		if($custom_date) $now_date = $custom_date;

		$sql = "select 'opt' as opt_type,v.*
				from ".$this->table_view_log." v
				left join ".$this->table_goods." g on v.goods_seq = g.goods_seq
				where
				v.regist_date = '".$now_date."' and
				g.goods_seq is not null";

		$query = mysqli_query($this->db->conn_id,$sql);

		$insert_chk = $this->insert_data($query,$this->table_view,$this->table_view_row,$now_date);

		if($insert_chk && !$custom_date){
			## 해당 날짜 [보다]의 한달 이전 로그데이터는 지운다
			$delete_date = date("Y-m-d", mktime(0, 0, 0, date("m") - 1, date("d"), date("Y")));
			$del_sql = "delete from ".$this->table_view_log." where regist_date < '".$delete_date."'";
			$query = mysqli_query($this->db->conn_id,$del_sql);
		}
	}

	public function daily_review($custom_date = null){
		$now_date = $this->now_date;
		if($custom_date) $now_date = $custom_date;
		$startDate = $now_date.' 00:00:00';
		$endDate = $now_date.' 23:59:59';

		$col = '\'opt\' as opt_type,r.*,r.r_date as regist_date,g.goods_name,g.goods_kind,m.member_seq,ifnull(m.mtype,\'none\') as mtype,ifnull(m.sex,\'none\') as sex,ifnull(m.birthday,\'0000-00-00\') as birthday';

		$sql = "select ".$col."
				from ".$this->table_goods_review." r
				left join ".$this->table_goods." g on r.goods_seq = g.goods_seq
				left join ".$this->table_member." m on r.mseq = m.member_seq
				where
				(r.r_date between '".$startDate."' and '".$endDate."') and
				g.goods_seq is not null";

		$query = mysqli_query($this->db->conn_id,$sql);

		$insert_chk = $this->insert_data($query,$this->table_review,$this->table_review_row,$now_date);
	}

	public function daily_cart($custom_date = null){
		$now_date = $this->now_date;
		if($custom_date) $now_date = $custom_date;
		$startDate = $now_date.' 00:00:00';
		$endDate = $now_date.' 23:59:59';

		$col = "co.*,
				c.fblike,
				c.regist_date,
				c.goods_seq,
				c.agent,
				c.session_id,
				c.ip,
				goods.goods_name,goods.goods_code,goods.goods_type,goods.cancel_type,
				goods.goods_kind,goods.socialcp_event,
				goods.shipping_weight_policy,goods.goods_weight,
				goods.shipping_policy,goods.goods_shipping_policy,
				goods.unlimit_shipping_price,goods.limit_shipping_price,
				goods.limit_shipping_ea,goods.limit_shipping_subprice,
				goods.sale_seq,
				goods.min_purchase_ea,
				goods.max_purchase_ea,
				goods_opt.price,
				goods_opt.commission_rate,
				goods_opt.reserve_rate,
				goods_opt.reserve_unit as reserve_unit,
				goods_opt.reserve reserve,
				goods_opt.consumer_price,
				(select supply_price from fm_goods_supply where option_seq=goods_opt.option_seq) supply_price,
				goods.reserve_policy,
				goods.multi_discount_use,
				goods.multi_discount_ea,
				goods.multi_discount,
				goods.multi_discount_unit,
				goods.tax,
				goods.provider_seq,
				(select provider_name from fm_provider where provider_seq=goods.provider_seq) as provider_name,
				goods.social_goods_group,
				goods.socialcp_input_type,goods.socialcp_cancel_type,
				goods.socialcp_cancel_use_refund,goods.socialcp_cancel_payoption,goods.socialcp_cancel_payoption_percent,
				goods.socialcp_use_return,goods.socialcp_use_emoney_day,goods.socialcp_use_emoney_percent,
				goods.individual_refund,
				goods.individual_refund_inherit,
				goods.individual_export,
				goods.individual_return,
				goods.possible_pay,
				goods.possible_mobile_pay,
				goods.adult_goods,
				goods.hscode,
				m.member_seq,m.group_seq,ifnull(m.mtype,'none') as mtype,ifnull(m.sex,'none') as sex,ifnull(m.birthday,'0000-00-00') as birthday
				";

		$ea_col = "
				,(select sum(ea) from ".$this->table_cart_option." sub_co left join ".$this->table_goods_cart." sub_c on sub_co.cart_seq = sub_c.cart_seq ,".$this->table_goods_option." sub_goods_opt
				where
				sub_c.distribution = 'cart' and
				(sub_c.regist_date between '".$startDate."' and '".$endDate."') and
				sub_c.member_seq = c.member_seq and
				sub_c.goods_seq = c.goods_seq
				and sub_c.goods_seq = sub_goods_opt.goods_seq
				and ifnull(sub_co.option1,'') = ifnull(sub_goods_opt.option1,'')
				and ifnull(sub_co.option2,'') = ifnull(sub_goods_opt.option2,'')
				and ifnull(sub_co.option3,'') = ifnull(sub_goods_opt.option3,'')
				and ifnull(sub_co.option4,'') = ifnull(sub_goods_opt.option4,'')
				and ifnull(sub_co.option5,'') = ifnull(sub_goods_opt.option5,'')
				group by sub_c.member_seq,sub_c.goods_seq) total_ea";

		$sql = "select 'opt' as opt_type,".$col.$ea_col."
				from ".$this->table_cart_option." co
				left join ".$this->table_goods_cart." c on co.cart_seq = c.cart_seq
				left join ".$this->table_goods." goods on c.goods_seq = goods.goods_seq
				left join ".$this->table_member." m on c.member_seq = m.member_seq
				,".$this->table_goods_option." goods_opt
				where
				(c.regist_date between '".$startDate."' and '".$endDate."') and
				c.distribution = 'cart' and
				goods.goods_seq is not null
				and c.goods_seq = goods_opt.goods_seq
				and ifnull(co.option1,'') = ifnull(goods_opt.option1,'')
				and ifnull(co.option2,'') = ifnull(goods_opt.option2,'')
				and ifnull(co.option3,'') = ifnull(goods_opt.option3,'')
				and ifnull(co.option4,'') = ifnull(goods_opt.option4,'')
				and ifnull(co.option5,'') = ifnull(goods_opt.option5,'')";
		$query = mysqli_query($this->db->conn_id,$sql);

		$this->load->library('sale');

		$member_tmp = array();

		/*
			쿼리에서 GROUP BY 하지 않는 이유는 sale_library 에서 개별 가격을 구해 더해야 하기 때문이다
			처음 객체로 돌리고 난 뒤 그 객체의 포인트를 다시 0으로 초기화 해준다
		*/
		$applypage = 'saleprice';
		while ($daily = mysqli_fetch_assoc($query)){
			if(!$daily['goods_seq']) continue;

			$member_session = ($daily['member_seq'] && $daily['member_seq'] > 0) ? $daily['member_seq'] : $daily['session_id'];
			$member_seq = ($daily['member_seq'] && $daily['member_seq'] > 0) ? $daily['member_seq'] : 0;
			$group_seq = ($daily['member_seq'] && $daily['group_seq'] > 0) ? $daily['group_seq'] : 0;

			//--> sale library 적용
			$param['cal_type']				= 'list';
			$param['member_seq']			= $member_seq;
			$param['group_seq']				= $group_seq;
			$this->sale->set_init($param);
			$this->sale->preload_set_config($applypage);
			//<-- sale library 적용

			// 해당 상품의 전체 카테고리
			$category	= array();
			$tmp		= $this->goodsmodel->get_goods_category($daily['goods_seq']);
			foreach($tmp as $row) $category[] = $row['category_code'];

			unset($param, $sales);
			$param['consumer_price']	= $daily['consumer_price'];
			$param['price']				= $daily['price'];
			$param['ea']				= $daily['ea'];//장바구니수량
			$param['goods_ea']			= $daily['total_ea'];//전체상품의 수량
			$param['category_code']		= $category;
			$param['goods_seq']			= $daily['goods_seq'];
			$param['goods']				= $daily;
			$this->sale->set_init($param);
			$sales						= $this->sale->calculate_sale_price($applypage);

			$member_totalprice[$member_session] += $sales['one_result_price'];

			$this->sale->reset_init();
		}

		mysqli_data_seek($query,0);

		$insert_chk = $this->insert_data($query,$this->table_cart,$this->table_cart_row,$now_date,$member_totalprice);

		##서브 옵션도 따로 넣어준다
		$sql = "select 'sub' as opt_type,sub.suboption_title,sub.suboption,sub.ea as sub_ea,goods_subopt.price as sub_price,goods_subopt.consumer_price as sub_consumer_price,goods_subopt.sub_sale,".$col."
				from ".$this->table_cart_suboption." sub
				left join ".$this->table_goods_suboption." goods_subopt on sub.suboption = goods_subopt.suboption and sub.suboption_title=goods_subopt.suboption_title
				left join ".$this->table_cart_option." co on sub.cart_option_seq = co.cart_option_seq
				left join ".$this->table_goods_cart." c on co.cart_seq = c.cart_seq
				left join ".$this->table_goods." goods on c.goods_seq = goods.goods_seq
				left join ".$this->table_member." m on c.member_seq = m.member_seq
				,".$this->table_goods_option." goods_opt
				where
				(c.regist_date between '".$startDate."' and '".$endDate."')
				and c.distribution = 'cart'
				and goods_subopt.suboption_seq is not null
				and goods.goods_seq is not null
				and co.cart_option_seq is not null
				and c.goods_seq = goods_opt.goods_seq
				and ifnull(co.option1,'') = ifnull(goods_opt.option1,'')
				and ifnull(co.option2,'') = ifnull(goods_opt.option2,'')
				and ifnull(co.option3,'') = ifnull(goods_opt.option3,'')
				and ifnull(co.option4,'') = ifnull(goods_opt.option4,'')
				and ifnull(co.option5,'') = ifnull(goods_opt.option5,'')";
		$sub_query = mysqli_query($this->db->conn_id,$sql);

		$insert_chk = $this->insert_data($sub_query,$this->table_cart,$this->table_cart_row,$now_date);
	}

	public function daily_wish($custom_date = null){
		$now_date = $this->now_date;
		if($custom_date) $now_date = $custom_date;
		$startDate = $now_date.' 00:00:00';
		$endDate = $now_date.' 23:59:59';

		$col = '\'opt\' as opt_type,w.*,g.provider_seq,g.goods_name,g.goods_kind,m.member_seq,ifnull(m.mtype,\'none\') as mtype,ifnull(m.sex,\'none\') as sex,ifnull(m.birthday,\'0000-00-00\') as birthday';

		$sql = "select ".$col."
				from ".$this->table_goods_wish." w
				left join ".$this->table_goods." g on w.goods_seq = g.goods_seq
				left join ".$this->table_member." m on w.member_seq = m.member_seq
				where
				(w.regist_date between '".$startDate."' and '".$endDate."') and
				g.goods_seq is not null";

		$query = mysqli_query($this->db->conn_id,$sql);

		$insert_chk = $this->insert_data($query,$this->table_wish,$this->table_wish_row,$now_date);
	}

	public function daily_fblike($custom_date = null){
		$now_date = $this->now_date;
		if($custom_date) $now_date = $custom_date;
		$startDate = $now_date.' 00:00:00';
		$endDate = $now_date.' 23:59:59';

		$col = '\'opt\' as opt_type,f.*,f.date as regist_date,g.provider_seq,g.goods_name,g.goods_kind,m.member_seq,ifnull(m.mtype,\'none\') as mtype,ifnull(m.sex,\'none\') as sex,ifnull(m.birthday,\'0000-00-00\') as birthday';

		$sql = "select ".$col."
				from ".$this->table_goods_fblike." f
				left join ".$this->table_goods." g on f.goods_seq = g.goods_seq
				left join ".$this->table_member." m on f.member_seq = m.member_seq
				where
				(f.date between '".$startDate."' and '".$endDate."') and
				g.goods_seq is not null";

		$query = mysqli_query($this->db->conn_id,$sql);

		$insert_chk = $this->insert_data($query,$this->table_fblike,$this->table_fblike_row,$now_date);
	}

	public function daily_restock_notify($custom_date = null){
		$now_date = $this->now_date;
		if($custom_date) $now_date = $custom_date;
		$startDate = $now_date.' 00:00:00';
		$endDate = $now_date.' 23:59:59';

		$col = '\'opt\' as opt_type,r.*,ro.*,g.provider_seq,g.goods_name,g.goods_kind,m.member_seq,ifnull(m.mtype,\'none\') as mtype,ifnull(m.sex,\'none\') as sex,ifnull(m.birthday,\'0000-00-00\') as birthday';

		$sql = "select ".$col."
				from ".$this->table_goods_restock_notify." r
				inner join ".$this->table_goods_restock_option." ro on r.restock_notify_seq = ro.restock_notify_seq
				inner join ".$this->table_goods." g on r.goods_seq = g.goods_seq
				left join ".$this->table_member." m on r.member_seq = m.member_seq
				where
				(r.regist_date between '".$startDate."' and '".$endDate."') and
				g.goods_seq is not null";

		$query = mysqli_query($this->db->conn_id,$sql);

		$insert_chk = $this->insert_data($query,$this->table_restock_notify,$this->table_restock_notify_row,$now_date);
	}

	public function daily_order($custom_date = null){
		$now_date = $this->now_date;
		if($custom_date) $now_date = $custom_date;
		$startDate = $now_date.' 00:00:00';
		$endDate = $now_date.' 23:59:59';

		$col = "order_opt.*,ord.*,order_item.goods_seq,
				goods.goods_name,goods.goods_type,goods.goods_kind,
				m.member_seq,m.group_seq,ifnull(m.mtype,'none') as mtype,ifnull(m.sex,'none') as sex,ifnull(m.birthday,'0000-00-00') as birthday
				";

		$sql = "select 'opt' as opt_type,".$col."
				from ".$this->table_order_option." order_opt
				left join ".$this->table_order_item." order_item on order_opt.item_seq = order_item.item_seq
				left join ".$this->table_goods_order." ord on order_opt.order_seq = ord.order_seq
				left join ".$this->table_goods." goods on order_item.goods_seq = goods.goods_seq
				left join ".$this->table_member." m on ord.member_seq = m.member_seq
				where
				(ord.deposit_date between '".$startDate."' and '".$endDate."') and
				ord.deposit_yn='y' and
				ord.step between '15' and '85' and
				goods.goods_seq is not null";
		$query = mysqli_query($this->db->conn_id,$sql);

		$insert_chk = $this->insert_data($query,$this->table_order,$this->table_order_row,$now_date);

		$col .= ',order_opt.title as suboption_title,order_opt.ea as sub_ea';

		$sql = "select 'sub' as opt_type,".$col."
				from ".$this->table_order_suboption." order_opt
				left join ".$this->table_order_item." order_item on order_opt.item_seq = order_item.item_seq
				left join ".$this->table_goods_order." ord on order_opt.order_seq = ord.order_seq
				left join ".$this->table_goods." goods on order_item.goods_seq = goods.goods_seq
				left join ".$this->table_member." m on ord.member_seq = m.member_seq
				where
				(ord.deposit_date between '".$startDate."' and '".$endDate."') and
				ord.deposit_yn='y' and
				ord.step between '15' and '85' and
				goods.goods_seq is not null";
		$query = mysqli_query($this->db->conn_id,$sql);

		$insert_chk = $this->insert_data($query,$this->table_order,$this->table_order_row,$now_date);
	}

	public function insert_view_table($custom_date = null){
		## 7개월 이전 데이터는 지운다
		$del_date = $this->log_delete_date;
		$del_date = str_replace('-','',$del_date).'00000000';

		foreach($this->view_table as $data_tb => $view_tb){
			if	($view_tb == 'fm_goods') continue;
			$this->cron_file_log('daily_stats_'.date('Ym').'.log', $this->now_date.' - '.$view_tb.' start');
			## 6개월 이전, 오늘 데이터 모두 지운다
			$del = "delete from ".$view_tb." where daily_date < '".$del_date."'";
			$this->db->query($del);
			$this->cron_file_log('daily_stats_'.date('Ym').'.log', $this->now_date.' - '.$view_tb.' success');
		}
	}

	public function get_rand_ip(){
		$arr = array();
		for($i=0;$i<4;$i++){
			$arr[] = rand(0,255);
		}
		$ret = implode('.',$arr);
		return $ret;
	}
	
	// 정산데이터 기준 판매금액 저장
	public function daily_sales_price($custom_date = null){
		$now_date = $this->now_date;
		if($custom_date) $now_date = $custom_date;
		$startDate = $now_date.' 00:00:00';
		$endDate = $now_date.' 23:59:59';
		
		## 오늘 데이터 모두 지운다
		$this->db->where('daily_date', $now_date);
		$this->db->delete($this->table_sales_price);
		
		// 오늘 매출액은 정산 테이블에서 조회처리
		$this->load->model('accountallmodel');
		$main_count_bar = $this->accountallmodel->get_main_count_bar_total_price($now_date);
		$todayCount['total_price'] = $main_count_bar['total_price'];
		
		// 판매금액 데이터 생성
		$insert_data['daily_date']			= $now_date;
		$insert_data['daily_sales_price']	= $todayCount['total_price'];
		$this->db->insert($this->table_sales_price, $insert_data);
	}
}
