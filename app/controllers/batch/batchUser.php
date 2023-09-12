<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');
require_once(APPPATH ."controllers/batch/dailyEtc".EXT);
class batchUser extends dailyEtc {
    public function __construct() {
        parent::__construct();
    }    
    ### 수동 실행 start ###
	public function update_gabia_member(){		
		### Private Encrypt
		$email = get_encrypt_qry('email');
		$cellphone = get_encrypt_qry('cellphone');
		$phone = get_encrypt_qry('phone');
		$sql = "update fm_member set {$email}, {$cellphone}, {$phone}, update_date = now() where userid = 'gabia'";
		$this->db->query($sql);
	}	

	//자동티켓상품>배송완료 @ 미사용티켓상품환불불가완료와 미사용
	public function batch_social_goods_migration()
	{	
		$cfg_reserve = ($this->reserves)?$this->reserves:config_load('reserve');
		$cfg_order = config_load('order');
		$this->load->model('exportmodel');
		$this->load->model('ordermodel');
		$this->load->model('returnmodel');
		$this->load->model('socialcpconfirmmodel');

		$edate = date('Ymd');

		$qry = "select exp.*
			 from
			  fm_goods_export as exp
			  LEFT JOIN fm_goods_export_item as exp_item ON exp.export_code = exp_item.export_code
			  LEFT JOIN fm_order_item as item ON exp_item.item_seq = item.item_seq
			 where item.goods_kind = 'coupon' and exp.status >= '50'
				 and (exp.socialcp_status is null or exp.socialcp_status ='1' or exp.socialcp_status ='2')
			group by exp.export_code
			order by exp.status,exp.export_code";
			//$qry .= " limit 0,10";//(exp.socialcp_refund_day is null or exp.socialcp_refund_day < '$edate' ) and
		$query = $this->db->query($qry);
		if($query->num_rows() < 1) {
			echo ' batch_social_goods_migration  no data';
		}else{

			foreach ($query->result_array() as $data_export){$num++;
				$export_code= $data_export['export_code'];
				$data_export_item = $this->exportmodel->get_export_item($export_code);
				if ($data_export_item[0]['goods_kind'] != 'coupon')continue;//티켓상품만

				//$data_export		= $this->exportmodel->get_export($export_code);
				$data_order		= $this->ordermodel->get_order($data_export['order_seq']);
				$data_returns_item	= $this->returnmodel->get_return_item_return_code($data_export_item[0]['item_seq'], $data_export_item[0]['option_seq'], $export_code);

				$tot_coupon_value = 0;
				$tot_coupon_remain_value = 0;
				foreach($data_export_item as $k => $item)
				{
					$tot_coupon_value += $item['coupon_value'];
					$tot_coupon_remain_value += $item['coupon_remain_value'];
				}

				$socialcp_remain_status = ($tot_coupon_remain_value == $tot_coupon_value )?true:false;//모두미사용 true, 일부사용 false
				$pointsave = false;
				if(!$data_export['socialcp_refund_day'] || $data_export['socialcp_refund_day'] < 1 ) {//환불기간이 없다면 계산
					$select_refund_day = "select if( ord_itm.socialcp_use_return ='1', DATE_ADD(ord_itm_opt.social_end_date, INTERVAL ord_itm.socialcp_use_emoney_day DAY), ord_itm_opt.social_end_date) FROM fm_order_item_option ord_itm_opt left join fm_order_item ord_itm ON ord_itm_opt.item_seq = ord_itm.item_seq left join fm_goods_export_item exp_itm ON ord_itm_opt.item_seq = exp_itm.item_seq WHERE exp_itm.export_code='".$export_code."' and exp_itm.coupon_serial is not null GROUP BY exp_itm.export_code";
					$res_refund_day  = mysqli_query($this->db->conn_id,$select_refund_day);
					$row_refund_day = mysqli_fetch_row($res_refund_day);
					$data_export['socialcp_refund_day'] = date("Ymd",strtotime($row_refund_day[0]));

					$refunddayupquery = "update fm_goods_export set socialcp_refund_day = '".$data_export['socialcp_refund_day']."' where export_code = ?";
					$this->db->query($refunddayupquery,array($export_code));

					if( $data_export['socialcp_refund_day'] < $edate ) {//가치종료
						if( $data_returns_item['ea'] > 0 ) {//취소(환불)
							$data_returns	= $this->returnmodel->get_return($data_returns_item['return_code']);
							if(  $data_export['socialcp_refund_day'] >= $data_returns['regist_date']  ) {//유효기간 시작 전 취소시
								$socialcp_status = ($socialcp_remain_status)?'6':'7';//모두미사용 6, 일부사용 7
							}else{//유효기간 종료 후 취소시
								$socialcp_status = ($socialcp_remain_status)?'8':'9';//모두미사용 8, 일부사용 9
							}
							$statustype = 'cancel';//환불
						}else{
							$socialcp_status = ($socialcp_remain_status)?'4':'5';//모두미사용 4, 일부사용 5
							$statustype = 'expired';//낙장
							$pointsave = true;
						}
					}else{//가치종료 전
						if($data_returns_item['ea'] > 0) {//취소(환불)
							$data_returns	= $this->returnmodel->get_return($data_returns_item['return_code']);
							if(  $data_export['socialcp_refund_day'] >= $data_returns['regist_date']  ) {//유효기간 시작 전 취소시
								$socialcp_status = ($socialcp_remain_status)?'6':'7';//모두미사용 6, 일부사용 7
							}else{//유효기간 종료 후 취소시
								$socialcp_status = ($socialcp_remain_status)?'8':'9';//모두미사용 8, 일부사용 9
							}
							$statustype = 'cancel';//환불
						}else{
							if( $tot_coupon_remain_value == 0 ) {
								$socialcp_status = '3';
								$statustype = 'expired';//낙장
								$pointsave = true;
							}else{
								if( $data_export['socialcp_refund_day'] < $edate ) {//가치종료
									$socialcp_status = ($socialcp_remain_status)?'4':'5';//모두미사용 4, 일부사용 5
									$statustype = 'expired';//낙장
									$pointsave = true;
								}else{
									$socialcp_status = ($socialcp_remain_status)?'1':'2';//모두미사용 1, 일부사용 2
									$statustype = 'migration';//마이그레이션
								}
							}
						}
					}
				}else{//환불기간이 있다면
					if(strstr($data_export['socialcp_refund_day'],'-')) {
						$data_export['socialcp_refund_day'] = date("Ymd",strtotime($data_export['socialcp_refund_day']));
						$refunddayupquery = "update fm_goods_export set socialcp_refund_day = '".$data_export['socialcp_refund_day']."' where export_code = ?";
						$this->db->query($refunddayupquery,array($export_code));
					}

					if( $data_export['socialcp_refund_day'] < $edate ) {//가치종료
						if( $data_returns_item['ea'] > 0 ) {//취소(환불)
							$data_returns	= $this->returnmodel->get_return($data_returns_item['return_code']);
							if(  $data_export['socialcp_refund_day'] >= $data_returns['regist_date']  ) {//유효기간 시작 전 취소시
								$socialcp_status = ($socialcp_remain_status)?'6':'7';//모두미사용 6, 일부사용 7
							}else{//유효기간 종료 후 취소시
								$socialcp_status = ($socialcp_remain_status)?'8':'9';//모두미사용 8, 일부사용 9
							}
							$statustype = 'cancel';//환불
						}else{
							$socialcp_status = ($socialcp_remain_status)?'4':'5';//모두미사용 4, 일부사용 5
							$statustype = 'expired';//낙장
							$pointsave = true;
						}
					}else{//가치종료 전
						if($data_returns_item['ea'] > 0) {//취소(환불)
							$data_returns	= $this->returnmodel->get_return($data_returns_item['return_code']);
							if(  $data_export['socialcp_refund_day'] >= $data_returns['regist_date']  ) {//유효기간 시작 전 취소시
								$socialcp_status = ($socialcp_remain_status)?'6':'7';//모두미사용 6, 일부사용 7
							}else{//유효기간 종료 후 취소시
								$socialcp_status = ($socialcp_remain_status)?'8':'9';//모두미사용 8, 일부사용 9
							}
							$statustype = 'cancel';//환불
						}else{
							if( $tot_coupon_remain_value == 0 ) {
								$socialcp_status = '3';
								$statustype = 'expired';//낙장
								$pointsave = true;
							}else{
								if( $data_export['socialcp_refund_day'] < $edate ) {//가치종료
									$socialcp_status = ($socialcp_remain_status)?'4':'5';//모두미사용 4, 일부사용 5
									$statustype = 'expired';//낙장
									$pointsave = true;
								}else{
									$socialcp_status = ($socialcp_remain_status)?'1':'2';//모두미사용 1, 일부사용 2
									$statustype = 'migration';//마이그레이션
								}
							}
						}
					}
				}
				if( $data_export['socialcp_status'] != $socialcp_status ) {
					$data_socialcp_confirm['order_seq']		= $data_export['order_seq'];
					$data_socialcp_confirm['export_seq']		= $data_export['export_seq'];
					$data_socialcp_confirm['doer']				= '자동';
					$this->socialcpconfirmmodel -> socialcp_confirm('system',$socialcp_status,$export_code);
					$this->socialcpconfirmmodel -> log_socialcp_confirm($data_socialcp_confirm);
					if( $statustype != 'migration' && $data_export['status'] != 75 ) {
						$this->exportmodel->socialcp_exec_complete_delivery($export_code,$pointsave,'','system',$statustype);//미사용티켓상품
					}
				}
			}//endwhile
			echo ' batch_social_goods_migration OK';
		}
	}	

	// 실제 주문을 검색하여 출고예약량을 업데이트합니다.
	public function all_modify_reservation()
	{
		if(ENVIRONMENT != 'development') return;
		$this->load->model('goodsmodel');
		$goods_arr = $this->goodsmodel->get_goods_only(['1'=>'1']);
		foreach($goods_arr->result_array() as $row) {
			$this->goodsmodel-> modify_reservation_real($row['goods_seq']);
		}
		config_save('reservation',array('update_date'=>date('Y-m-d H:i:s')));
		echo "OK";
	}
    
    public function daily_stats_order(){
		// 특정 IP에서만 호출
		if (developIpCheck()) {
			$this->load->model('dailystatsmodel');
			$this->load->model('goodsmodel');
			$custom_date = isset($_GET['date']) && $_GET['date'] ? $_GET['date'] : null;

			$this->dailystatsmodel->daily_order($custom_date);

			//CASE1 집계
			$this->dailystatsmodel->case1($custom_date,
				array(
					'order'				=> 'fm_daily_stats_order_vw',
				)
			);
			//CASE2 집계
			$this->dailystatsmodel->case2($custom_date,
				array(
					"view_table_m_1" => array(
						'order'				=> 'fm_daily_stats_order_m_1',
					),
					"view_table_m_3" => array(
						'order'				=> 'fm_daily_stats_order_m_3',
					),
					"view_table_m_6" => array(
						'order'				=> 'fm_daily_stats_order_m_6',
					)
				)	
			);

			//7개월 이전 뷰 삭제
			// $this->dailystatsmodel->insert_view_table($custom_date);
		}else{
			echo "허가되지 않은 IP입니다.";
		}
	}

	// 최초 사용 시 데이터 migration ( 사용 세팅 다음날 새벽 cron으로 돌면서 기본설정을 함 )
	public function scm_set_first_migration(){		
		$this->load->model('scmmodel');
		$cfg_basic	= config_load('scm');
		if	($cfg_basic['set_date']){
			$setTime	= strtotime($cfg_basic['set_date'] . ' 00:00:01');
			$chkTime	= strtotime(date('Y-m-d', strtotime('-1 day')) . ' 00:00:01');
//			if	($setTime == $chkTime){
				//초기화시 기본매장으로 처리 @2016-08-11 ysm
				$this->scmmodel->scm_migration = true;
				$result	= $this->scmmodel->scm_reset();
				if	($result)	echo 'scm_set_first_migration OK';
				else			echo 'scm_set_first_migration FAIL';
				return false;
//			}
		}
		echo 'scm_set_first_migration PASS';
		return false;			
	}	

	public function cron_stock_all_goods()
	{		
		$this->load->model('goodsmodel');
		$query	= "SELECT goods_seq FROM fm_goods";
		$query	= mysqli_query($this->db->conn_id, $query);
		while( $data = mysqli_fetch_array($query) )
		{
			$this->goodsmodel->total_stock( $data['goods_seq'] );
		}
		echo "update_goods_stock OK";
	}

	public function all_category_brand()
	{				
		$this->load->model('countmodel');
		$this->load->model('goodsmodel');

		$query = "select * from fm_goods";
		$query = mysqli_query($this->db->conn_id, $query);
		while($data = mysqli_fetch_array($query)){
			$this->countmodel->category_brand($data['goods_seq']);
			$this->goodsmodel->default_price($data['goods_seq']);
		}
		echo "count brand category, update default price!".chr(10);
	}

	// error 카테고리, 브랜드, 지역 삭제
	public function del_err_cbl()
	{		
		$aFields	= array('fm_category'=>'category_code', 'fm_brand'=>'category_code', 'fm_location'=>'location_code');
		foreach($aFields as $tableName => $fieldName){
			$chkCodes	= array();
			$qCateogry	= "select ".$fieldName." from ".$tableName." where length(".$fieldName.") > 4";
			$rCateogry	= mysql_query($qCateogry);
			while( $dCateogry = mysql_fetch_array($rCateogry) )
			{
				$chk			= true;
				$lCategoryCode	= strlen( $dCateogry[$fieldName] );
				for($i=4; $i<$lCategoryCode; $i+=4){
					if( $chk ){
						$category_code	= substr($dCateogry[$fieldName], 0, $i);
						if( $category_code && !in_array($category_code, $chkCodes) ){
							$qCategoryCode		= "select ".$fieldName." from ".$tableName." where ".$fieldName." = ? limit 1";
							$qCategoryCode		= $this->db->query($qCategoryCode, array($category_code));
							$dCategoryCode		= $qCategoryCode->row_array();
							if( !$dCategoryCode[$fieldName] )
							{
								$chk	= false;
								$this->db->query("delete from ".$tableName." where ".$fieldName." like ?", array($category_code.'%'));
								$this->db->query("delete from ".$tableName."_link where ".$fieldName." like ?", array($category_code.'%'));
							}
							$chkCodes[]	= $category_code;
						}
					}
				}
			}
		}
		echo "OK!";		
	}

	public function all_member_group()
	{		
		$this->load->model('countmodel');
		$query = "select * from fm_member_group";
		$query = mysqli_query($this->db->conn_id, $query);
		while($data = mysqli_fetch_array($query)){
			$this->countmodel->member_group($data['group_seq']);
		}
		echo "count member!".chr(10);			
	}	
	### 수동 실행 end ###
}

// END
/* End of file _batchUser.php */
/* Location: ./app/_batchUser.php */