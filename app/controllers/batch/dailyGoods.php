<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');
require_once(APPPATH ."controllers/batch/dailyOrder".EXT);
class dailyGoods extends dailyOrder {
    public function __construct() {
        parent::__construct();
    }    
    /**
	* - 티켓상품의 모든옵션의 유효기간이 만기시 판매중지상태 @2014-03-26
	**/
	public function social_goods_validate() {
		list($aFunc, $aNextFunc)	= $this->batchlib->_getNextFunc(__FUNCTION__);
		try{
            $today = date("Y-m-d");
            $query = "select goods_seq, admin_log from fm_goods where goods_kind='coupon' and goods_status ='normal' ";
            $query = $this->db->query($query);
            foreach($query -> result_array() as $datagd){
                $optquery = "select newtype, codedate, sdayinput, fdayinput from fm_goods_option where (newtype like '%date%' or newtype like '%dayinput%') and  goods_seq=?";
                //( (newtype like '%date%' and date_format(codedate,'%Y-%m-%d') < '{$today}') or (newtype like '%dayinput%' and date_format(fdayinput,'%Y-%m-%d') < '{$today}') )
                $optquery = $this->db->query($optquery,array($datagd['goods_seq']));
                $cnt = 0;
                $couponexpire = 0;
                foreach($optquery->result_array() as $data){$cnt++;
                    $types = explode(",",$data['newtype']);
                    if( in_array('date', $types) ) {
                        $social_code_date = $data['codedate'];
                        $social_code_datear = @explode("-",$social_code_date);
                        if( checkdate($social_code_datear[1],$social_code_datear[2],$social_code_datear[0]) != true || ( $social_code_date < $today) ) $couponexpire++;
                    }elseif( in_array('dayinput', $types) ) {
                        $social_start_date = $data['sdayinput'];
                        $social_end_date = $data['fdayinput'];
                        $social_code_datear = @explode("-",$social_end_date);
                        if( checkdate($social_code_datear[1],$social_code_datear[2],$social_code_datear[0]) != true || ( $social_end_date < $today ) ) $couponexpire++;
                    }else{//dayauto not
                        break;
                    }
                }//endforeach

                if( $couponexpire > 0 && $cnt == $couponexpire ){//모든 유효기간이 만기시 goods_status unsold
                    $admin_log = "<div>".date("Y-m-d H:i:s")." 티켓상품의 유효기간 자동만기처리</div>".$datagd['admin_log'];
                    $upquery = "UPDATE `fm_goods` SET `goods_status`='unsold', update_date ='".date("Y-m-d H:i:s",time())."', admin_log ='".$admin_log."' WHERE goods_kind='coupon'  AND goods_seq=? ";
                    $this->db->query($upquery,array($datagd['goods_seq']));
                }
            }//endforeach
		} catch (Exception $e) {			
			if( $aFunc )		$this->batchlib->_cronFileLog($aFunc['sLogFile'], $aFunc['sMsg'] . '(' . $aFunc['sFunctionName'] . ') - ' . $e->getMessage());
		}
        if( $aNextFunc )	$this->{$aNextFunc['sFunctionName']}();
        if( $aFunc )		$this->batchlib->_cronFileLog($aFunc['sLogFile'], $aFunc['sMsg'] . '(' . $aFunc['sFunctionName'] . ')');
	}
    public function delete_tmp_option_data(){		
		list($aFunc, $aNextFunc)	= $this->batchlib->_getNextFunc(__FUNCTION__);
		try{
			$query	= "delete from fm_goods_option_tmp where tmp_date < '".date('Ymd')."' ";
            $this->db->query($query);
            $query	= "delete from fm_goods_suboption_tmp where tmp_date < '".date('Ymd')."' ";
            $this->db->query($query);
            $query	= "delete from fm_goods_supply_tmp where tmp_date < '".date('Ymd')."' ";
            $this->db->query($query);
		} catch (Exception $e) {			
			if( $aFunc )		$this->batchlib->_cronFileLog($aFunc['sLogFile'], $aFunc['sMsg'] . '(' . $aFunc['sFunctionName'] . ') - ' . $e->getMessage());
		}
        if( $aNextFunc )	$this->{$aNextFunc['sFunctionName']}();
        if( $aFunc )		$this->batchlib->_cronFileLog($aFunc['sLogFile'], $aFunc['sMsg'] . '(' . $aFunc['sFunctionName'] . ')');
	}
    // 빠른상품등록 임시 데이터 삭제
	public function truncate_tmp_goods_data(){		
		list($aFunc, $aNextFunc)	= $this->batchlib->_getNextFunc(__FUNCTION__);
		try{
			$this->load->model('goodsmodel');
            $this->goodsmodel->truncate_tmp_goods_data();            
		} catch (Exception $e) {			
			if( $aFunc )		$this->batchlib->_cronFileLog($aFunc['sLogFile'], $aFunc['sMsg'] . '(' . $aFunc['sFunctionName'] . ') - ' . $e->getMessage());
		}
        if( $aNextFunc )	$this->{$aNextFunc['sFunctionName']}();
        if( $aFunc )		$this->batchlib->_cronFileLog($aFunc['sLogFile'], $aFunc['sMsg'] . '(' . $aFunc['sFunctionName'] . ')');
	}
	public function set_goods_event_price(){		
		list($aFunc, $aNextFunc)	= $this->batchlib->_getNextFunc(__FUNCTION__);
		try{
			$this->load->model('goodssummarymodel');
            $this->goodssummarymodel->set_event_price();		
		} catch (Exception $e) {			
			if( $aFunc )		$this->batchlib->_cronFileLog($aFunc['sLogFile'], $aFunc['sMsg'] . '(' . $aFunc['sFunctionName'] . ') - ' . $e->getMessage());
		}
        if( $aNextFunc )	$this->{$aNextFunc['sFunctionName']}();
        if( $aFunc )		$this->batchlib->_cronFileLog($aFunc['sLogFile'], $aFunc['sMsg'] . '(' . $aFunc['sFunctionName'] . ')');
	}
    ## 자동노출 종료상품 수동 전환
	public function endOfAutoDisplay() {
        list($aFunc, $aNextFunc)	= $this->batchlib->_getNextFunc(__FUNCTION__);
		try{
            $today		= date('Y-m-d');
            // 자동 노출 이전
            $rowUpdate      = "UPDATE fm_goods SET goods_view = 'look' WHERE display_terms = 'AUTO' AND	display_terms_begin > '".$today."' AND ((display_terms_before = 'DISPLAY' AND goods_view = 'notLook') OR (display_terms_before = 'CONCEAL' AND goods_view = 'look')) AND display_terms_before = 'DISPLAY'";
            mysqli_query($this->db->conn_id, $rowUpdate);
            $rowUpdate      = "UPDATE fm_goods SET goods_view = 'notLook' WHERE display_terms = 'AUTO' AND	display_terms_begin > '".$today."' AND ((display_terms_before = 'DISPLAY' AND goods_view = 'notLook') OR (display_terms_before = 'CONCEAL' AND goods_view = 'look')) AND display_terms_before = 'CONCEAL'";
            mysqli_query($this->db->conn_id, $rowUpdate);   

            // 자동 노출 진행 중
            $rowUpdate	= "UPDATE fm_goods SET goods_view = 'look' WHERE display_terms = 'AUTO' AND display_terms_begin <= '".$today."' AND display_terms_end > '".$today."' AND goods_view = 'notLook'";
            mysqli_query($this->db->conn_id, $rowUpdate);

            // 자동 노출 종료
            $rowUpdate	= "UPDATE fm_goods SET display_terms = 'MENUAL', display_terms_begin = NULL, display_terms_end = NULL, display_terms_type = 'SELLING', goods_view = 'look' WHERE display_terms = 'AUTO' AND display_terms_end < '".$today."' AND display_terms_after = 'DISPLAY'";
            mysqli_query($this->db->conn_id, $rowUpdate);
            $rowUpdate	= "UPDATE fm_goods SET display_terms = 'MENUAL', display_terms_begin = NULL, display_terms_end = NULL, display_terms_type = 'SELLING', goods_view = 'notLook' WHERE display_terms = 'AUTO' AND display_terms_end < '".$today."' AND display_terms_after = 'CONCEAL'";
            mysqli_query($this->db->conn_id, $rowUpdate);
        } catch (Exception $e) {			
			if( $aFunc )		$this->batchlib->_cronFileLog($aFunc['sLogFile'], $aFunc['sMsg'] . '(' . $aFunc['sFunctionName'] . ') - ' . $e->getMessage());
		}
        if( $aNextFunc )	$this->{$aNextFunc['sFunctionName']}();
        if( $aFunc )		$this->batchlib->_cronFileLog($aFunc['sLogFile'], $aFunc['sMsg'] . '(' . $aFunc['sFunctionName'] . ')');
	}
	// 수불부 월별 집계 ( cron으로는 한달치 한번만 집계한다. )
	public function save_scm_ledger_month(){
		list($aFunc, $aNextFunc)	= $this->batchlib->_getNextFunc(__FUNCTION__);

		try {
			$this->load->model('scmmodel');
			// scm 아니면 다음 cron 으로 
			if ($this->scmmodel->chkScmConfig(true) === false) {
				throw new Exception('Not Scm');
			}
			$cronstatus = $this->scmmodel->get_ledger_month_cronstatus();
			if (in_array($cronstatus['cron_status'], [1, 2]) === false) {	// 크론을 통해 월 데이터 저장이 완료된지 여부
				$this->scmmodel->save_ledger_month_cronstatus(2);	// cron을 진행중상태로
				$this->scmmodel->delete_ledger_month();	// 기존 월 집계 데이터를 지운다.
				$this->scmmodel->save_ledger_month();	// 월 집계를 저장한다.
				$this->scmmodel->save_ledger_month_cronstatus(1);	// cron을 완료 상태로 만든다.
			}

		}catch (Exception $e) {
			if( $aFunc )		$this->batchlib->_cronFileLog($aFunc['sLogFile'], $aFunc['sMsg'] . '(' . $aFunc['sFunctionName'] . ') - ' . $e->getMessage());
		}
		if( $aNextFunc )	$this->{$aNextFunc['sFunctionName']}();
		if( $aFunc )		$this->batchlib->_cronFileLog($aFunc['sLogFile'], $aFunc['sMsg'] . '(' . $aFunc['sFunctionName'] . ')');
	}
	public function all_category_count()
	{		
		list($aFunc, $aNextFunc)	= $this->batchlib->_getNextFunc(__FUNCTION__);
		try{
			$this->load->model('countmodel');
            $query = mysqli_query($this->db->conn_id, "select * from fm_category");
            while($data = mysqli_fetch_array($query)){
                if($data['category_code']){
                    $this->countmodel->category($data['category_code']);
                }
            }            
		} catch (Exception $e) {			
			if( $aFunc )		$this->batchlib->_cronFileLog($aFunc['sLogFile'], $aFunc['sMsg'] . '(' . $aFunc['sFunctionName'] . ') - ' . $e->getMessage());
		}
        if( $aNextFunc )	$this->{$aNextFunc['sFunctionName']}();
        if( $aFunc )		$this->batchlib->_cronFileLog($aFunc['sLogFile'], $aFunc['sMsg'] . '(' . $aFunc['sFunctionName'] . ')');
	}	
	public function all_brand_count()
	{		
		list($aFunc, $aNextFunc)	= $this->batchlib->_getNextFunc(__FUNCTION__);
		try{
			$this->load->model('countmodel');
            $query = mysqli_query($this->db->conn_id, "select * from fm_brand");
            while($data = mysqli_fetch_array($query)){
                if($data['category_code']){
                    $this->countmodel->brand($data['category_code']);
                }
            }
		} catch (Exception $e) {			
			if( $aFunc )		$this->batchlib->_cronFileLog($aFunc['sLogFile'], $aFunc['sMsg'] . '(' . $aFunc['sFunctionName'] . ') - ' . $e->getMessage());
		}
        if( $aNextFunc )	$this->{$aNextFunc['sFunctionName']}();
        if( $aFunc )		$this->batchlib->_cronFileLog($aFunc['sLogFile'], $aFunc['sMsg'] . '(' . $aFunc['sFunctionName'] . ')');
	}
	
	//카테고리 상품 정렬 맞춤
	public function sort_category_goods(){
		list($aFunc, $aNextFunc)	= $this->batchlib->_getNextFunc(__FUNCTION__);
		try{
			$cfg_goods = config_load('goods');

			if( $cfg_goods['category_sort'] == "Y") {
				throw new Exception('Already Category Sort');
			}

			$_table = "fm_category_link";

			$sql	= "select category_code from ".$_table." group by category_code";
			$query	= $this->db->query($sql);
			$rows	= $query->result_array();

			foreach($rows as $k => $_categorys){
				$category_code = $_categorys['category_code'];

				//PC용 전체 정렬
				$sql	= "select * from ".$_table." where category_code='".$category_code."' order by sort asc,category_link_seq desc";
				$query	= $this->db->query($sql);
				$list	= $query->result_array();

				foreach($list as $kk => $data){
					$sql = "update ".$_table." set sort=".($kk + 1)." where category_link_seq='".$data['category_link_seq']."'";
					$this->db->query($sql);
				}

				//Mobile용 전체 정렬
				$sql	= "select * from ".$_table." where category_code='".$category_code."' order by mobile_sort asc,category_link_seq desc";
				$query	= $this->db->query($sql);
				$list	= $query->result_array();

				foreach($list as $kk => $data){
					$sql = "update ".$_table." set mobile_sort=".($kk + 1)." where category_link_seq='".$data['category_link_seq']."'";
					$this->db->query($sql);
				}
			}
			config_save('goods',array('category_sort'=> 'Y'));
		} catch (Exception $e) {			
			if( $aFunc )		$this->batchlib->_cronFileLog($aFunc['sLogFile'], $aFunc['sMsg'] . '(' . $aFunc['sFunctionName'] . ') - ' . $e->getMessage());
		}
        if( $aNextFunc )	$this->{$aNextFunc['sFunctionName']}();
        if( $aFunc )		$this->batchlib->_cronFileLog($aFunc['sLogFile'], $aFunc['sMsg'] . '(' . $aFunc['sFunctionName'] . ')');
	}
	//브랜드 상품 정렬 맞춤
	public function sort_brand_goods(){
		list($aFunc, $aNextFunc)	= $this->batchlib->_getNextFunc(__FUNCTION__);
		try{
			$cfg_goods = config_load('goods');

			if( $cfg_goods['brand_sort'] == "Y") {
				throw new Exception('Already Brand Sort');
			}

			$_table = "fm_brand_link";

			$sql	= "select category_code from ".$_table." group by category_code";
			$query	= $this->db->query($sql);
			$rows	= $query->result_array();

			foreach($rows as $k => $_categorys){
				$category_code = $_categorys['category_code'];

				//PC용 전체 정렬
				$sql	= "select * from ".$_table." where category_code='".$category_code."' order by sort asc,category_link_seq desc";
				$query	= $this->db->query($sql);
				$list	= $query->result_array();

				foreach($list as $kk => $data){
					$sql = "update ".$_table." set sort=".($kk + 1)." where category_link_seq='".$data['category_link_seq']."'";
					$this->db->query($sql);
				}

				//Mobile용 전체 정렬
				$sql	= "select * from ".$_table." where category_code='".$category_code."' order by mobile_sort asc,category_link_seq desc";
				$query	= $this->db->query($sql);
				$list	= $query->result_array();

				foreach($list as $kk => $data){
					$sql = "update ".$_table." set mobile_sort=".($kk + 1)." where category_link_seq='".$data['category_link_seq']."'";
					$this->db->query($sql);
				}
			}
			config_save('goods',array('brand_sort'=> 'Y'));
		} catch (Exception $e) {			
			if( $aFunc )		$this->batchlib->_cronFileLog($aFunc['sLogFile'], $aFunc['sMsg'] . '(' . $aFunc['sFunctionName'] . ') - ' . $e->getMessage());
		}
        if( $aNextFunc )	$this->{$aNextFunc['sFunctionName']}();
        if( $aFunc )		$this->batchlib->_cronFileLog($aFunc['sLogFile'], $aFunc['sMsg'] . '(' . $aFunc['sFunctionName'] . ')');
	}
	//지역 상품 정렬 맞춤
	public function sort_location_goods(){
		list($aFunc, $aNextFunc)	= $this->batchlib->_getNextFunc(__FUNCTION__);
		try{
			$cfg_goods = config_load('goods');

			if( $cfg_goods['location_sort'] == "Y") {
				throw new Exception('Already Location Sort');
			}

			$_table = "fm_location_link";

			$sql	= "select location_code from ".$_table." group by location_code";
			$query	= $this->db->query($sql);
			$rows	= $query->result_array();

			foreach($rows as $k => $_categorys){

				$location_code = $_categorys['location_code'];

				//PC용 전체 정렬
				$sql	= "select * from ".$_table." where location_code='".$location_code."' order by sort asc,location_link_seq desc";
				$query	= $this->db->query($sql);
				$list	= $query->result_array();

				foreach($list as $kk => $data){
					$sql = "update ".$_table." set sort=".($kk + 1)." where location_link_seq='".$data['location_link_seq']."'";
					$this->db->query($sql);
				}

				//Mobile용 전체 정렬
				$sql	= "select * from ".$_table." where location_code='".$location_code."' order by mobile_sort asc,location_link_seq desc";
				$query	= $this->db->query($sql);
				$list	= $query->result_array();

				foreach($list as $kk => $data){
					$sql = "update ".$_table." set mobile_sort=".($kk + 1)." where location_link_seq='".$data['location_link_seq']."'";
					$this->db->query($sql);
				}
			}
			config_save('goods',array('location_sort'=> 'Y'));
		} catch (Exception $e) {			
			if( $aFunc )		$this->batchlib->_cronFileLog($aFunc['sLogFile'], $aFunc['sMsg'] . '(' . $aFunc['sFunctionName'] . ') - ' . $e->getMessage());
		}
        if( $aNextFunc )	$this->{$aNextFunc['sFunctionName']}();
        if( $aFunc )		$this->batchlib->_cronFileLog($aFunc['sLogFile'], $aFunc['sMsg'] . '(' . $aFunc['sFunctionName'] . ')');
	}
   
}