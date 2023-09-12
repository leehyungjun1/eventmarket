<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');
require_once(APPPATH ."controllers/base/selleradmin_base".EXT);

class common extends selleradmin_base {

	public function __construct() {
		parent::__construct();
	}

	public function divExcelDownload(){
		$this->load->helper('download');

		$title = iconv("utf-8","euc-kr",$_POST['title']);
		$contents = $_POST['contents'];
		$contents = preg_replace("/\(([^\)]*)\)/","",$contents); // 값에 들어간 괄호를 제거하기 위한 코드
		$contents = strip_tags($contents,"<table><tr><th><td><style>");
		$contents = '<meta http-equiv="Content-Type" content="text/html; charset=utf-8">'.$contents;

		$fileName = "{$title}_".date('YmdHis').".xls";

		force_download($fileName, $contents);
	}

	/* 가비아 출력 패널 (배너,팝업) */
	public function getGabiaPannel(){
		$this->load->helper('readurl');

		$code = $_GET['code'];

		$data = array(
			'service_code'	=> SERVICE_CODE,
			'hosting_code'	=> $this->config_system['service']['hosting_code'],
			'subDomain'		=> $this->config_system['subDomain'],
			'domain'		=> $this->config_system['domain'],
			'hostDomain'	=> $_SERVER['HTTP_HOST'],
			'shopSno'		=> $this->config_system['shopSno'],
			'expire_date'	=> $this->config_system['service']['expire_date'],
		);

		$res = readurl(get_connet_protocol()."interface.firstmall.kr/firstmall_plus/request.php?cmd=getGabiaPannel&code={$code}",$data);

		echo $res;
	}

	/* 입점사관리페이지 */
	public function getSellerNoticePannel(){
		$this->load->helper('readurl');

		$data = array();

		$res = readurl($_GET['url'],$data);

		echo $res;
	}

	/* 상단메뉴별 카운트 반환 */
	public function getIssueCount(){
		$this->load->helper('noticount');
		if	(!$this->scm_cfg)	$this->scm_cfg	= config_load('scm');
		$this->load->model('ordermodel');
		$this->load->model('membermodel');
		$this->load->model('eventmodel');
		$this->load->model('boardmodel');
		$this->load->model('providercode');

		$issueCount = array();	
		if( $this->providerInfo['provider_seq'] ){
			$provider_seq				= $this->providerInfo['provider_seq'];
			$wheres['shopSno']			= $this->config_system['shopSno'];
			$wheres['provider_seq']		= $this->providerInfo['provider_seq'];
			$wheres['codecd like']		= '%_priod_%';;
			$orderbys['idx'] 			= 'asc';
			$query_auth	= $this->providercode->select('*',$wheres,$orderbys);
			foreach($query_auth->result_array() as $data){
				$codecd = str_replace('noti_count_priod_','',$data['codecd']);
				$cfg_priod[$codecd]	= $data['value'];
			}
		}
		if(!$cfg_priod['order']) $cfg_priod['order'] = "6개월";
		if(!$cfg_priod['board']) $cfg_priod['board'] = "6개월";
		if(!$cfg_priod['account']) $cfg_priod['account'] = "6개월";
		if(!$cfg_priod['warehousing']) $cfg_priod['warehousing'] = "6개월";

		## 처리해야할 주문수
		$start_date = str_to_priod_for_noti_count($cfg_priod['order']);
		$query = $this->ordermodel->get_issue_count_provider($start_date,$provider_seq);	
		$result = $query->result_array();
		foreach($result as $row){
			$issueCount['order']['title'] = "처리해야할 주문";
			$issueCount['order']['total'] += $row['cnt'];
			$issueCount['order'][$row['type']] = $row['cnt'];
		}

		## 미처리 1:1문의, 상품문의
		$union_query = array();
		$start_date = str_to_priod_for_noti_count($cfg_priod['board']);
		$query = $this->boardmodel->get_issue_count_provider($start_date,$provider_seq);
		$result = $query->result_array();
		foreach($result as $row){
			$issueCount['board']['title'] = "미처리 1:1문의, 상품문의";
			$issueCount['board']['total'] += $row['cnt'];
			$issueCount['board']['mbqna'] += $row['cnt'];
		}

		## 정산대기수
		if	(serviceLimit('H_AD')){ // 입점몰일 경우
			$this->load->model('accountmodel');
			$start_date = str_to_priod_for_noti_count($cfg_priod['account']);
			$total_account = 0;
			$arr_account_period = array(1,2,4);
			foreach($arr_account_period as $period){
				$query = $this->accountmodel->get_issue_count_provider($period,$start_date,$provider_seq);
				$data = $query->row_array();
				$issueCount['account']['period'.$period] = $data['cnt'];
				$total_account += (int) $data['cnt'];
			}
			$issueCount['account']['total'] = $total_account;
		}
		
		// 오픈마켓 - 주문수집/등록
		$this->load->model('connectormodel');
		$issueCount['market_connector']['title'] = "처리해야할 오픈 마켓 주문";
		
		// 오픈마켓 - 주문수집/등록
		unset($params);
		$params['withTotalCount']	= true;
		$params['hasFmOrderSeq']	= "N";
		$params['status']	= array("ORD10","ORD20");
		$response		= $this->connectormodel->getMarketOrderList($params, 'forViewList');
		$issueCount['market_connector']['regist'] = $response['totalCount'];

		// 오픈마켓 - 취소관리
		unset($params);
		$params['withTotalCount']	= true;
		$params['claimType']		= "CAN";
		$params['status']	= "CAN00";
		$response		= $this->connectormodel->getMarketClaimList($params, 'forViewList');
		$issueCount['market_connector']['cancel'] = $response['totalCount'];

		// 오픈마켓 - 반품관리
		unset($params);
		$params['withTotalCount']	= true;
		$params['claimType']		= "RTN";
		$params['is_fm_order']	= "Y";		// 등록된 주문
		$params['hasFmClaimCode']	= "N";	// 등록되지 않은 클레임
		$params['status']	= "RTN00";
		$response		= $this->connectormodel->getMarketClaimList($params, 'forViewList');
		$issueCount['market_connector']['return'] = $response['totalCount'];

		// 오픈마켓 - 교환관리
		unset($params);
		$params['withTotalCount']	= true;
		$params['claimType']		= "EXC";
		$params['is_fm_order']	= "Y";		// 등록된 주문
		$params['hasFmClaimCode']	= "N";	// 등록되지 않은 클레임
		$params['status']	= "EXC00";
		$response		= $this->connectormodel->getMarketClaimList($params, 'forViewList');
		$issueCount['market_connector']['exchange'] = $response['totalCount'];

		// 오픈마켓 - 총 갯수
		foreach($issueCount['market_connector'] as $k=>$v){
			$issueCount['market_connector']['total'] += $v;
		}


		echo json_encode($issueCount);
	}

	public function ajax_volume_check(){
		return $this->volume_check();
	}

	public function category2json(){
		$this->load->model('categorymodel');
		$result = array();
		$code 	= $this->input->get('categoryCode');
		$result = $this->categorymodel->get_admin_list($code);
		echo json_encode($result);
	}

	public function brand2json(){
		$this->load->model('brandmodel');
		$result = array();
		$code 	= $this->input->get('categoryCode');
		$result = $this->brandmodel->get_admin_list($code);
		echo json_encode($result);
	}

	public function location2json(){
		$this->load->model('locationmodel');
		$result = array();
		$code 	= $this->input->get('locationCode');
		$result = $this->locationmodel->get_admin_list($code);
		echo json_encode($result);
	}

	public function event2json(){
		$result 	= array();
		$event_seq 	= $this->input->get('event_seq');
		$query 		= $this->db->query("select * from fm_event_benefits where event_seq=? order by event_benefits_seq asc",$event_seq);
		$result 	= $query->result_array();
		foreach($result as $i=>$row){
			$result[$i]['title'] = "[경우".($i+1)."] 할인".number_format($row['event_sale'])."%,적립".number_format($row['event_reserve'])."%";
		}

		echo json_encode($result);
	}

	/* QR 코드 안내*/
	public function qrcode_guide(){
		$this->template->assign(array('key'=>$this->input->get('key')));
		$this->template->assign(array('value'=>$this->input->get('value')));
		$this->template->define(array('tpl'=>$this->template_path()));
		$this->template->print_("tpl");
	}

	public function total_menu(){
		$this->tempate_modules();
		$file_path	= $this->template_path();
		$this->load->model("admin_menu");
		
		$this->template->define(array('tpl'=>$file_path));
		$this->template->assign(array('adminMenu' => $this->admin_menu->arr_menu));
		$this->template->print_("tpl");
	}

	// LNB 버튼 설정 저장
	public function saveLnbConf()
	{
		$this->load->library('bookmarklibrary');
		$this->bookmarklibrary->setLnbConf($this->input->post());
	}

	// 즐겨찾기 추가/삭제
	public function bookmark()
	{
		$this->load->library('bookmarklibrary');
		$this->bookmarklibrary->setBookmark($this->input->post());
	}

	// 즐겨찾기 메뉴 리스트
	public function getBookmarkList()
	{
		$this->load->library('bookmarklibrary');
		$result = $this->bookmarklibrary->getBookmark();
		echo json_encode($result, JSON_UNESCAPED_UNICODE);
	}
}

/* End of file coupon.php */
/* Location: ./app/controllers/selleradmin/coupon.php */