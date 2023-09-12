<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');
require_once(APPPATH ."controllers/base/admin_base".EXT);

class referer extends admin_base {

	public function __construct(){
		parent::__construct();

		$this->load->model('providermodel');
		$this->load->model('referermodel');
		$this->load->model('categorymodel');
		$this->load->model('goodsmodel');

		$this->admin_menu();
		$this->tempate_modules();
		$this->file_path	= $this->template_path();

		$this->template->define(array('tpl'=>$this->file_path));
	}

	public function index(){
		redirect("/admin/referer/catalog");
	}

	// 유입경로할인 목록
	public function catalog(){

		serviceLimit('H_FR','process');

		/* 관리자 권한 체크 : 시작 */
		$auth = $this->authmodel->manager_limit_act('referer_view');
		if(!$auth){
			$callback = "history.go(-1);";
			$this->template->assign(array('auth_msg'=>$this->auth_msg,'callback'=>$callback));
			$this->template->define(array('denined'=>$this->skin.'/common/denined.html'));
			$this->template->print_("denined");
			exit;
		}
		/* 관리자 권한 체크 : 끝 */

		### SEARCH
		$sc						= $this->input->get();
		$sc['page']				= (isset($sc['page']) && $sc['page'] > 1) ? intval($sc['page']):'0';
		$sc['perpage']			= (isset($sc['perpage'])) ?	intval($sc['perpage']):'10';
		if(isset($_GET['search_text'])) $sc['search_mode']	= "search";

		$referer			= $this->referermodel->get_referersale_list($sc);
		if	($referer['record']){

			foreach($referer['record'] as $k => $data){
	
				$data['date']		= date('Y-m-d H:i', strtotime($data['regist_date']));
				$data['validdate']	= $data['issue_startdate'] . '~' . $data['issue_enddate'];

				//기획정의.
				if(in_array($this->config_system['basic_currency'],array("KRW","JPY"))){
					$data['max_percent_goods_sale'] = (int)$data['max_percent_goods_sale'];
					$data['won_goods_sale']			= (int)$data['won_goods_sale'];
				}
				$data['salepricetitle']	= ($data['sale_type'] == 'percent' ) ? $data['percent_goods_sale'].'% 할인, 최대 '.get_currency_price($data['max_percent_goods_sale'],2,'basic'): get_currency_price($data['won_goods_sale'],2,'basic')." 할인";

				$list[]	= $data;
			}
		}

		if(!$sc['search_field']) $sc['search_field'] = "all";
		$sc['selectbox']['search_field'][$sc['search_field']] = "selected";

		$this->template->assign('sc',$sc);
		$this->template->assign('scObj',json_encode($sc));
		$this->template->assign(array('list'=>$list));
		$this->template->assign(array('page'=>$referer['page']));
		$this->template->print_("tpl");
	}

	// 유입경로할인 상세
	public function referersale(){

		$no						= $this->input->get('no');
		$mode					= $this->input->get('mode');
		$provider				= $this->providermodel->provider_goods_list();
		
		if	($no){
			$referer			= $this->referermodel->get_referersale_info($no);
			$issuegoods 		= $this->referermodel->get_referersale_issuegoods($no);
			$issuecategorys		= $this->referermodel->get_referersale_issuecategory($no);			

			//기획정의.금액 입력 시, 화폐별 소수점 입력은 아래의 내용을 따른다
			//포인트의 경우 소수점 이하 입력 불가, 마일리지는 기본 화폐별로 2번의 정책에 따름
			if(in_array($this->config_system['basic_currency'],array("KRW","JPY"))){
				$referer['max_percent_goods_sale']	= (int)$referer['max_percent_goods_sale'];
				$referer['won_goods_sale']			= (int)$referer['won_goods_sale'];
				$referer['limit_goods_price']		= (int)$referer['limit_goods_price'];
			}

			if($referer['provider_list']){
				$referer['provider_name_list'] = $this->providermodel->get_provider_select_list($referer['provider_list']);
			}

			if(($issuegoods)){
				$issuegoods = $this->goodsmodel->get_select_goods_list($issuegoods);
				$this->template->assign(array('issuegoods'=>$issuegoods));
			}

			if($issuecategorys){
				foreach($issuecategorys as $key =>$data) $issuecategorys[$key]['category'] = $this->categorymodel -> get_category_name($data['category_code']);
				$this->template->assign(array('issuecategorys'=>$issuecategorys));
			}

			$this->template->assign(array('referer'=>$referer));
		}

		$this->template->assign(array('provider'=>$provider,'mode'=>$mode));

		$this->template->print_("tpl");
	}

	// 유입경로 URL 중복 확인
	public function chkRefererUrl(){
		$referer_url	= trim($_GET['referer_url']);
		$url_type		= trim($_GET['url_type']);
		$sdate			= trim($_GET['sdate']);
		$edate			= trim($_GET['edate']);
		$provider_list	= trim($_GET['provider_list']);
		// http나 https 제거
		if	(preg_match('/^http/', $referer_url))
			$referer_url	= preg_replace('/^https*\:\/\//', '', $referer_url);

		// 필수값 체크
		if	(!$referer_url || !$sdate || !$edate){
			echo 'fail';
			exit;
		}

		// 유효기간 확인
		if	(strtotime($sdate) > strtotime($edate)){
			echo 'error_date';
			exit;
		}

		$referer	= $this->referermodel->chk_referersale_duple($referer_url, $url_type, $sdate, $edate, $provider_list);
		if	(!$referer['referersale_seq'])	echo 'ok';
		else								echo 'no';
	}

	/*
	[공용] 유입경로할인 검색 - 관리자 UX/UI변경 @2020.02.24 pjm
	*/
	public function gl_referer_select(){

		$this->load->model('referermodel');

		$sc				= $this->input->get();
		$sc['page']		= (isset($sc['page']) && $sc['page'] > 1) ? intval($sc['page']):'0';
		$sc['perpage']	= (!empty($sc['perpage'])) ? intval($sc['perpage']):10;
		
		if($sc['select_lists']){
			$select_lists		= explode("|",$sc['select_lists']);
			$sc['select_lists'] = $select_lists;
		} 
		$this->template->assign('sc',$sc);

		$result			= $this->referermodel->get_referersale_list($sc);  
		$this->template->assign($result);

		$file_path = str_replace("gl_referer_select.html","_gl_referer_select.html",$this->template_path());
		$this->template->define(array('tpl'=>$file_path));
		$this->template->print_("tpl");
	}

}

/* End of file referer.php */
/* Location: ./app/controllers/admin/referer.php */