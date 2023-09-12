<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');
require_once(APPPATH ."controllers/base/admin_base".EXT);

class statistic_process extends admin_base {

	public function __construct() {
		parent::__construct();
	}
	
	public function month_lastday(){
		echo date('t',strtotime("{$_GET['year']}-{$_GET['month']}-01"));
	}

	/* 방문자 접속통계 설정 저장 */
	public function visitor_setting(){

		if(!isset($_POST['statisticExcludeIp'])) $_POST['statisticExcludeIp'] = array();

		$setSystemConfig = array();
		$setSystemConfig['statisticExcludeIp'] = implode("\n",$_POST['statisticExcludeIp']);

		config_save('system',$setSystemConfig);

		$callback = "parent.document.location.reload();";
		openDialogAlert("설정이 저장 되었습니다.",400,140,'parent',$callback);
	}
	
	/* 프로모션통계 - 광고매체 추가*/
	public function promotion_inflow_add(){
		$this->load->library('validation');
		$this->validation->set_rules('title', '광고매체명','trim|required|max_length[20]|xss_clean');
		$this->validation->set_rules('url', '연결URL','trim|required|max_length[255]|xss_clean');
	
		if($this->validation->exec()===false){
			$err = $this->validation->error_array;
			$callback = "if(parent.document.getElementsByName('{$err['key']}')[0]) parent.document.getElementsByName('{$err['key']}')[0].focus();";
			openDialogAlert($err['value'],400,140,'parent',$callback);
			exit;
		}
		
		$seq = !empty($_POST['seq']) ? $_POST['seq'] : null;
		
		$data = array();
		$data['title']	= str_replace(array('"',"'"),'',$_POST['title']);
		$data['url']	= str_replace(array('"',"'"),'',$_POST['url']);
		
		if(!$seq){
			$data['regist_date']	= date('Y-m-d H:i:s');
			$data['inflow_code']	= '';	
			
			$this->db->insert('fm_inflow', $data);
	
			$seq = $this->db->insert_id();
			$update_data = array();
			$update_data['inflow_code'] = 'I'.date('ymd').sprintf("%05d",$seq);
	
			$this->db->where('seq',$seq);
			$this->db->update('fm_inflow',$update_data);
			
		}else{
			$this->db->where('seq',$seq);
			$this->db->update('fm_inflow', $data);
		}
		
		openDialogAlert("광고 매체 정보가 저장 되었습니다.",400,140,'parent','parent.openAddInflowRootLayer();');
	
	}
	
	/* 프로모션통계 - 광고매체 삭제*/
	public function promotion_inflow_del(){
		$seq = $_GET['seq'];
		$this->db->query("delete from fm_inflow where seq=?",$seq);
	}

	/* GA 설정 저장 */
	public function ga_setting(){
		$param = $this->input->post();

        // GA4 설정 추가 2021.08.11
        $ga4_commerce = 'N';
        if (!isset($param['ga4_id'])) $param['ga4_id'] = array();
        if ($param['ga4_visit'] == 'Y' && $param['ga4_commerce'] == 'Y' && $param['ga4_id']) $ga4_commerce = 'Y';

        $ga4_setSystemConfig = array();
        $ga4_setSystemConfig['ga4_id'] = $param['ga4_id'];
        $ga4_setSystemConfig['ga4_visit'] = $param['ga4_visit'];
        $ga4_setSystemConfig['ga4_commerce'] = $ga4_commerce;

        config_save('GA4',$ga4_setSystemConfig);

		$callback = "parent.document.location.reload();";
		openDialogAlert("설정이 저장 되었습니다.",400,140,'parent',$callback);
	}
}

/* End of file setting_process.php */
/* Location: ./app/controllers/admin/setting_process.php */