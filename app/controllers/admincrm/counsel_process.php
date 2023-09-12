<?php
/**
 * 상담 관련 관리자 process
 * @author gabia
 * @since version 2.0 - 2012.06.29
 */
 if ( ! defined('BASEPATH')) exit('No direct script access allowed');
require_once(APPPATH ."controllers/base/crm_base".EXT);

class counsel_process extends crm_base {

	public function __construct() {
		parent::__construct();
		$this->load->library('validation');

	}

	public function counsel_write(){
		$aPostParams	= $this->input->post();

		// validation
		if ($aPostParams) {
			$this->validation->set_data($aPostParams);
			$this->validation->set_rules('manager_seq', '관리자 번호', 'trim|numeric|xss_clean');
			$this->validation->set_rules('manager_name', '관리자명', 'trim|string|xss_clean');
			$this->validation->set_rules('counsel_category', '카테고리', 'trim|string|xss_clean');
			$this->validation->set_rules('counsel_status', '상태', 'trim|string|xss_clean');
			$this->validation->set_rules('counsel_category', '카테고리', 'trim|string|xss_clean');
			$this->validation->set_rules('order_seq', '주문번호', 'trim|numeric|xss_clean');
			$this->validation->set_rules('export_code', '출고코드', 'trim|string|xss_clean');
			$this->validation->set_rules('return_code', '반품코드', 'trim|string|xss_clean');
			$this->validation->set_rules('refund_code', '환불코드', 'trim|string|xss_clean');
			$this->validation->set_rules('goods_qna_seq', '질문', 'trim|numeric|xss_clean');
			$this->validation->set_rules('goods_review_seq', '후기', 'trim|numeric|xss_clean');
			$this->validation->set_rules('parent_counsel_seq', '상담', 'trim|numeric|xss_clean');
			$this->validation->set_rules('counsel_contents', '상담내용', 'trim|string|xss_clean');
			if ($this->validation->exec() === false) {
				//show_error($this->validation->error_array['value']);
				alert($this->validation->error_array['value']);
				exit;
			}
		}

		$counsel_act_auth = $this->authmodel->manager_limit_act('counsel_act');

		if(!$counsel_act_auth){
			alert("권한이 없습니다.");
			exit;
		}

		$this->validation->set_rules('counsel_contents', '상담내용','trim|required|xss_clean');

		if($this->validation->exec()===false){
			$err = $this->validation->error_array;
			$callback = "if(parent.document.getElementsByName('{$err['key']}')[0]) parent.document.getElementsByName('{$err['key']}')[0].focus();".$callback_default;
			openDialogAlert($err['value'],400,140,'parent',$callback);
			exit;
		}
		$data['manager_seq'] = $_POST['manager_seq'];
		$data['manager_name'] = $_POST['manager_name'];
		$data['counsel_category'] = $_POST['counsel_category'];
		$data['order_seq'] = $_POST['order_seq'];
		$data['export_code'] = $_POST['export_code'];
		$data['return_code'] = $_POST['return_code'];
		$data['refund_code'] = $_POST['refund_code'];
		$data['goods_qna_seq'] = $_POST['goods_qna_seq'];
		$data['goods_review_seq'] = $_POST['goods_review_seq'];
		$data['parent_counsel_seq'] = $_POST['parent_counsel_seq'];
		$data['counsel_status'] = $_POST['counsel_status'];
		$data['counsel_contents'] = $_POST['counsel_contents'];
		$data['counsel_regdate'] = date("Y-m-d H:i:s");
		$data['member_seq'] = $this->mdata['member_seq'];

		if($_POST['counsel_status'] == "complete"){
			$data['counsel_complete_date'] = date("Y-m-d H:i:s");
		}

		$result = $this->db->insert('fm_counsel', $data);

		$msg = "상담이 등록되었습니다.";
		$callback = "parent.location.reload()";
		openDialogAlert($msg,400,140,'parent',$callback);

	}

	public function counsel_modify(){
		$aPostParams	= $this->input->post();

		// validation
		if ($aGetParams) {
			$this->validation->set_data($aGetParams);
			$this->validation->set_rules('counsel_status', '상태', 'trim|string|xss_clean');
			$this->validation->set_rules('order_seq', '주문번호', 'trim|numeric|xss_clean');
			$this->validation->set_rules('export_code', '출고코드', 'trim|string|xss_clean');
			$this->validation->set_rules('return_code', '반품코드', 'trim|string|xss_clean');
			$this->validation->set_rules('refund_code', '환불코드', 'trim|string|xss_clean');
			$this->validation->set_rules('goods_qna_seq', '질문', 'trim|numeric|xss_clean');
			$this->validation->set_rules('goods_review_seq', '후기', 'trim|numeric|xss_clean');
			$this->validation->set_rules('parent_counsel_seq', '상담', 'trim|numeric|xss_clean');
			$this->validation->set_rules('counsel_contents', '상담내용', 'trim|string|xss_clean');
			if ($this->validation->exec() === false) {
				show_error($this->validation->error_array['value']);
			}
		}

		$counsel_act_auth = $this->authmodel->manager_limit_act('counsel_act');

		if(!$counsel_act_auth){
			alert("권한이 없습니다.");
			exit;
		}

		$this->validation->set_rules('counsel_contents', '상담내용','trim|required|xss_clean');

		if($this->validation->exec()===false){
			$err = $this->validation->error_array;
			$callback = "if(parent.document.getElementsByName('{$err['key']}')[0]) parent.document.getElementsByName('{$err['key']}')[0].focus();".$callback_default;
			openDialogAlert($err['value'],400,140,'parent',$callback);
			exit;
		}
		$data['order_seq'] = $_POST['order_seq'];
		$data['export_code'] = $_POST['export_code'];
		$data['return_code'] = $_POST['return_code'];
		$data['refund_code'] = $_POST['refund_code'];
		$data['goods_qna_seq'] = $_POST['goods_qna_seq'];
		$data['goods_review_seq'] = $_POST['goods_review_seq'];
		$data['parent_counsel_seq'] = $_POST['parent_counsel_seq'];
		$data['counsel_status'] = $_POST['counsel_status'];
		$data['counsel_contents'] = $_POST['counsel_contents'];
		$data['counsel_regdate'] = date("Y-m-d H:i:s");

		if($_POST['counsel_status'] == "complete"){
			$data['counsel_complete_date'] = date("Y-m-d H:i:s");
		}else{
			$data['counsel_complete_date'] = Null;
		}

		$this->db->where('counsel_seq', $_POST['counsel_seq']);
		$result = $this->db->update('fm_counsel', $data);

		$msg = "상담이 수정되었습니다.";
		$callback = "parent.location.reload()";
		openDialogAlert($msg,400,140,'parent',$callback);

	}

	public function counsel_view()
	{
		$aGetParams = $this->input->get();
		// validation
		if ($aGetParams) {
			$this->validation->set_data($aGetParams);
			$this->validation->set_rules('seq', '일련번호', 'trim|numeric|xss_clean');
			if ($this->validation->exec() === false) {
				show_error($this->validation->error_array['value']);
			}
		}

		$sql = "select * from fm_counsel where counsel_seq = '".$_POST['seq']."'";
		$query = $this->db->query($sql);
		$result = $query->row_array();

		echo json_encode($result);
	}

	public function counsel_remove() {

		$counsel_act_auth = $this->authmodel->manager_limit_act('counsel_act');

		if(!$counsel_act_auth){
			return "auth";
			exit;
		}

		$seq = $this->input->post('seq');
		$this->db->where('counsel_seq',$seq);
		$result = $this->db->delete('fm_counsel');
		return $result;
	}

	public function blacklist_add(){
		$aPostParams	 = $this->input->post();

		// validation
		if ($aPostParams) {
			$this->validation->set_data($aPostParams);
			$this->validation->set_rules('blacklist_level', 'level', 'trim|string|xss_clean');
			$this->validation->set_rules('blacklist_contents', '내용', 'trim|string|xss_clean');
			$this->validation->set_rules('order_seq', '주문번호', 'trim|numeric|xss_clean');
			if ($this->validation->exec() === false) {
				show_error($this->validation->error_array['value']);
			}
		}

	    if(!$_POST['blacklist_level'] || $_POST['blacklist_level'] <= 0){
	        $_POST['blacklist_level'] = 1;
	    }

		$data['blacklist_level'] = $_POST['blacklist_level'];
		$data['blacklist_contents'] = $_POST['blacklist_contents'];
		$data['member_seq'] = $this->mdata['member_seq'];
		$data['order_seq'] = $_POST['order_seq'];
		$data['blacklist_regist_date'] = date("Y-m-d H:i:s");
		$data['blacklist_regist_manager_seq'] = $this->managerInfo['manager_seq'];
		$data['blacklist_regist_manager'] = $this->managerInfo['mname'];

		$result = $this->db->insert('fm_member_blacklist', $data);

		if($this->mdata['member_seq']){
			$sqlWhere = " where member_seq = '".$this->mdata['member_seq']."'
                            AND blacklist_regist_date > (SELECT blacklist_regist_date FROM
                                fm_member_blacklist WHERE blacklist_level=0 ORDER BY blacklist_seq DESC LIMIT 1)";
			$sql = "select count(*) as cnt, sum(blacklist_level) as lvSum from fm_member_blacklist".$sqlWhere;
			$query = $this->db->query($sql);
			$result = $query->row_array();

			$blackListLevel = $data['blacklist_level'];
			if($result['cnt'] > 0) $blackListLevel = $result['lvSum'] / $result['cnt'];
			$upData['blacklist'] = $blackListLevel;

			$this->db->where('member_seq', $this->mdata['member_seq']);
			$this->db->update('fm_member', $upData);
		}else if($_POST['order_seq']){
			$sqlWhere = " where order_seq = '".$_POST['order_seq']."'
                            AND blacklist_regist_date > (SELECT blacklist_regist_date FROM
                                fm_member_blacklist WHERE blacklist_level=0 ORDER BY blacklist_seq DESC LIMIT 1)";
			$sql = "select count(*) as cnt, sum(blacklist_level) as lvSum from fm_member_blacklist".$sqlWhere;
			$query = $this->db->query($sql);
			$result = $query->row_array();

			$blackListLevel = $data['blacklist_level'];
			if($result['cnt'] > 0) $blackListLevel = $result['lvSum'] / $result['cnt'];
			$upData['blacklist'] = $blackListLevel;

			$this->db->where('order_seq', $_POST['order_seq']);
			$this->db->update('fm_order', $upData);
		}



		$msg = "블랙커스터머 정보가 등록되었습니다.";
		$callback = "parent.location.reload()";
		openDialogAlert($msg,400,200,'parent',$callback);

	}

	public function blacklist_delete(){

		$blacklist_seq = $this->input->post('blacklist_seq');
		$this->db->where('blacklist_seq',$blacklist_seq);
		$this->db->delete('fm_member_blacklist');

	}

	public function blacklist_load(){
		$blacklist_seq = $this->input->post('blacklist_seq');
		$this->db->where('blacklist_seq',$blacklist_seq);
		$query = $this->db->get('fm_member_blacklist');
		foreach($query->result_array() as $row){
			$result = $row;
		}

		echo json_encode($result);
	}
	public function blacklist_modify(){
		$data['blacklist_seq'] = $_POST['blacklist_seq'];
		$data['member_seq'] = $this->mdata['member_seq'];
		$data['order_seq'] = $_POST['order_seq'];
		$data['blacklist_level'] = $_POST['blacklist_level'];
		$data['blacklist_contents'] = $_POST['blacklist_contents'];
		$data['blacklist_modify_date'] = date("Y-m-d H:i:s");
		if($_POST['blacklist_modify_manager_seq']!=true){
			$data['blacklist_modify_manager_seq'] = $this->managerInfo['manager_seq'];
		}
		$data['blacklist_modify_manager'] = $this->managerInfo['mname'];

		$this->db->where('blacklist_seq', $data['blacklist_seq']);
		$result = $this->db->update('fm_member_blacklist', $data);

		if($data['member_seq']){
			$sqlWhere = " where member_seq = '".$data['member_seq']."'
                            AND blacklist_regist_date > (SELECT blacklist_regist_date FROM
                                fm_member_blacklist WHERE blacklist_level=0 ORDER BY blacklist_seq DESC LIMIT 1)";
			$sql = "select count(*) as cnt, sum(blacklist_level) as lvSum from fm_member_blacklist".$sqlWhere;
			$query = $this->db->query($sql);
			$result = $query->row_array();

			$blackListLevel = $data['blacklist_level'];
			if($result['cnt'] > 0) $blackListLevel = $result['lvSum'] / $result['cnt'];
			$upData['blacklist'] = $blackListLevel;

			$this->db->where('member_seq', $data['member_seq']);
			$this->db->update('fm_member', $upData);
		}else if($data['order_seq']){
			$sqlWhere = " where order_seq = '".$data['order_seq']."'
                            AND blacklist_regist_date > (SELECT blacklist_regist_date FROM
                                fm_member_blacklist WHERE blacklist_level=0 ORDER BY blacklist_seq DESC LIMIT 1)";
			$sql = "select count(*) as cnt, sum(blacklist_level) as lvSum from fm_member_blacklist".$sqlWhere;
			$query = $this->db->query($sql);
			$result = $query->row_array();

			$blackListLevel = $data['blacklist_level'];
			if($result['cnt'] > 0) $blackListLevel = $result['lvSum'] / $result['cnt'];
			$upData['blacklist'] = $blackListLevel;

			$this->db->where('order_seq', $data['order_seq']);
			$this->db->update('fm_order', $upData);
		}
	}
}