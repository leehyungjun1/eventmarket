<?php
/**
 * 회원 > SNSFacebook 초대하기 관련 모듈
 * @author gabia
 * @since version 1.0 - 2012-08-28
 */
class Snsfbinvite extends CI_Model {

	function __construct() {
		parent::__construct();
		$this->table_snsinvite = 'fm_memberinvite';
		if(!empty($_GET['seq'])) {//
			$this->seq = $_GET['seq'];
		}
	}

	/*
	 * SNSFacebook 초대하기 관리
	 * @param
	*/
	public function snsinvite_list($sc) {

		$sql = "select * from ".$this->table_snsinvite." where 1";
		if ( $sc['sns_f'] ) {
			$sql .= " and sns_f = '".$sc['sns_f']."' ";
		}
		$sql .=" order by seq desc ";
		$query = $this->db->query($sql);
		$data['result'] = $query->result_array();
		return $data;
	}


	/*
	 * SNSFacebook 초대하기 관리
	 * @param
	*/
	public function snsinvite_list_search($sc) {

		$sql = "select SQL_CALC_FOUND_ROWS * from ".$this->table_snsinvite." where 1";

		if ( $sc['sns_f'] ) $sql .= " and sns_f = '".$sc['sns_f']."' ";
		if ( $sc['member_seq'] ) $sql .= " and member_seq = '".$sc['member_seq']."' ";

		### DATE
		if( !empty($sc['sdate']) && !empty($sc['edate'])){
			$start_date = $sc['sdate'].' 00:00:00';
			$end_date = $sc['edate'].' 23:59:59';
			$sql.=" and r_date BETWEEN '{$start_date}' and '{$end_date}' ";
		}

		if(!empty($sc['search_text']))
		{
			$sql .= ' and ( sns_f  like "%'.$sc['search_text'].'%" or user_name  like "%'.$sc['search_text'].'%" ) ';
		}
		if(!empty($sc['joinck'])) {
			$joinar = @implode("','",$sc['joinck']);
			$sql .=  " and joinck  in ('".$joinar."') ";
		}

		$sql .=" order by seq desc ";
		$sql .=" limit {$sc['page']}, {$sc['perpage']} ";

		$query = $this->db->query($sql);
		$data['result'] = $query->result_array();

		//총건수
		$sql = "SELECT FOUND_ROWS() as COUNT";
		$query_count = $this->db->query($sql);
		$res_count= $query_count->result_array();
		$data['count'] = $res_count[0]['COUNT'];

		return $data;
	}

	// SNSFacebook 초대하기 총건수
	public function get_item_total_count($sc)
	{
		$sql = 'select seq from '.$this->table_snsinvite.' where 1';
		$query = $this->db->query($sql);
		return $query->num_rows();
	}

	/*
	 * SNSFacebook 초대하기 정보
	 * @param
	*/
	public function get_data($sc) {
		$sql = "select ".$sc['select']." from  ".$this->table_snsinvite."  where 1 ". $sc['whereis'];
		if ( $sc['sns_f'] ) $sql .= " and sns_f = '".$sc['sns_f']."' ";
		$sql .=" order by seq desc ";
		$query = $this->db->query($sql);
		$data = $query->row_array();
		return $data;
	}

	/*
	 * SNSFacebook 초대하기 정보
	 * @param
	*/
	public function get_data_numrow($sc) {
		$sql = 'select ' . $sc['select'] . ' from  ' . $this->table_snsinvite . '  where 1 ' . $sc['whereis'];
		if ($sc['sns_f']) {
			$sql .= ' and sns_f = ?  order by seq desc ';
			$query = $this->db->query($sql, [$sc['sns_f']]);
		} else {
			$sql .= ' order by seq desc ';
			$query = $this->db->query($sql);
		}
		return $query->num_rows();
	}

	/*
	 * SNSFacebook 초대하기 생성
	 * @param
	*/
	public function snsinvite_write($params) {
		$data = filter_keys($params, $this->db->list_fields($this->table_snsinvite));
		$result = $this->db->insert($this->table_snsinvite, $data);
		return $this->db->insert_id();
	}

	/*
	 * SNSFacebook 초대하기 정보
	 * @param
	*/
	public function get_data_optimize() {
		$sql = "optimize table ".$this->table_snsinvite;
		$this->db->query($sql);
	}

	/*
	 * SNSFacebook 초대하기 수정
	 * @param
	*/
	public function snsinvite_modify($params) {
		if(empty($params['sns_f']))return false;
		$data = filter_keys($params, $this->db->list_fields($this->table_snsinvite));
		$result = $this->db->update($this->table_snsinvite, $data,array('sns_f'=>$params['sns_f']));
		return $result;
	}


	/*
	 * SNSFacebook 초대하기 삭제
	 * @param
	*/
	public function snsinvite_delete($sns_f) {
		if(empty($sns_f))return false;
		$result = $this->db->delete($this->table_snsinvite, array('sns_f' => $sns_f));
		return $result;
	}

}
?>