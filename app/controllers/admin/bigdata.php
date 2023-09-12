<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');
require_once(APPPATH ."controllers/base/admin_base".EXT);

class bigdata extends admin_base {

	public function __construct() {
		parent::__construct();

		$this->admin_menu();
		$this->tempate_modules();
		$this->load->model('bigdatamodel');
		$this->load->model('goodsmodel');
		$this->load->model('usedmodel');
		if	(!$this->config_system)		$this->config_system	= config_load('system');

		$chks = $this->usedmodel->used_service_check('bigdata');
		$this->template->assign(array('chkBigdata'=>$chks['type']));

		$this->template->assign(array('kinds' => $this->bigdatamodel->get_kind_array()));
		$this->template->define(array('SEARCH_FORM' => $this->skin."/bigdata/search_form.html"));
	}

	public function index(){
		redirect("/admin/bigdata/catalog");
	}

	// 빅데이터 추천 설정 페이지
	public function catalog(){
		serviceLimit('H_FR','process');

		// 현재 저장된 설정 불러오기
		$cfg_bigdata = config_load('bigdata_criteria');
		$this->template->assign(array('cfg_bigdata'	=> $cfg_bigdata['condition']));

		$file_path	= $this->template_path();
		$this->template->define(array('tpl'=>$file_path));
		$this->template->print_("tpl");
	}

	// 상품정보 추출 페이지
	public function get_goods(){

		$goods_seq			= trim($this->input->post('goods_seq'));
		$condition			= trim($this->input->post('condition'));
		$displayCriteria	= trim($this->input->post('displayCriteria'));

		if(strpos($condition,'bigdata') > -1) $sc['bigdata'] = 1;

		$sc['bigdata_test'] 		= 1;
		$sc['goods_seq_exclude'] 	= $goods_seq;

		$this->load->model('goodsdisplay');
		$sc 	= $this->goodsdisplay->auto_select_condition($displayCriteria, $sc);
		$list 	= $this->goodsmodel->auto_condition_goods_list($sc);

		foreach($list['record'] as &$data) {
			$data['jsimage'] 		= viewImg($data['goods_seq'],'thumbView');
			$data['jsgoods_name'] 	= getstrcut(strip_tags($data['goods_name']),30);
			$data['jsprice'] 		= get_currency_price($data['price'],2);
		}
		echo json_encode($list);
	}

	// 상품 상세정보 HTML
	public function get_goods_view($goods_seq){

		$this->tempate_modules();
		$result		= $this->goodsmodel->get_goods_view($goods_seq, true, true);
		if	($result['status'] == 'error'){
			echo $result['msg'];
		}else{
			$goods			= $result['goods'];
			$category		= $result['category'];
			$alerts			= $result['alerts'];
			if	($result['assign'])foreach($result['assign'] as $key => $val){
				$this->template->assign(array($key	=> $val));
			}
			$this->template->assign(array('skin'	=> $this->config_system['skin']));

			$file_path	= $this->template_path();
			$this->template->define(array('tpl'=>$file_path));
			$html		= $this->template->fetch("tpl");

			return $html;
		}
	}

	// 상품 상세정보 HTML
	public function get_goods_list($goods_seq, $kind, $count_w = 5){

		$this->tempate_modules();
		$goods_seq[]	= 13;
		$goods_seq[]	= 14;
		$goods_seq[]	= 16;
		$goods_seq[]	= 17;
		$sc['src_seq']	= $goods_seq;
		$list			= $this->goodsmodel->goods_list($sc);

		$this->template->assign(array('kind'	=> $kind));
		$this->template->assign(array('count_w'	=> $count_w));
		$this->template->assign(array('goods'	=> $list['record']));
		$file_path	= $this->template_path();
		$this->template->define(array('tpl'=>$file_path));
		$html		= $this->template->fetch("tpl");

		return $html;
	}

}

/* End of file bigdata.php */
/* Location: ./app/controllers/admin/bigdata.php */