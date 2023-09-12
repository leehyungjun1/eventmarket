<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');
require_once(APPPATH ."controllers/base/admin_base".EXT);

class member extends admin_base {

	public function __construct() {
		parent::__construct();
		$this->load->helper('member');
		$this->load->library('validation');
		$this->template->assign('mname',$this->managerInfo['mname']);
		// 보안키 입력창
		$member_download_info = $this->skin.'/member/member_download_info.html';
		$this->template->define(array("member_download_info"=>$member_download_info));
	}

	public function index()
	{
		redirect("/admin/member/catalog");
	}

	### 회원리스트
	public function catalog()
	{
		$auth = $this->authmodel->manager_limit_act('member_view');
		if(!$auth){
			pageBack("관리자 권한이 없습니다.");
			exit;
		}
		$this->load->model('snsmember');
		$this->load->model('membermodel');
		$this->load->model('providermodel');
		$this->admin_menu();
		$this->tempate_modules();
		$file_path	= $this->template_path();

		$provider	= $this->providermodel->provider_goods_list();
		$this->template->assign('provider',$provider);

		// 개인 정보 조회 로그
		// $type,$manager_seq,$type_seq
		$this->load->model('logPersonalInformation');
		$this->logPersonalInformation->insert('memberlist',$this->managerInfo['manager_seq'],'');

		for ($m=1;$m<=12;$m++){	$m_arr[] = str_pad($m, 2, '0', STR_PAD_LEFT); }
		for ($d=1;$d<=31;$d++){	$d_arr[] = str_pad($d, 2, '0', STR_PAD_LEFT); }
		$this->template->assign('m_arr',$m_arr);
		$this->template->assign('d_arr',$d_arr);

		#### AUTH
		$auth_arr		= array();
		$auth_act		= $this->authmodel->manager_limit_act('member_act');
		if(isset($auth_act)) $auth_arr['auth_act'] = $auth_act;
		$auth_promotion = $this->authmodel->manager_limit_act('member_promotion');
		if(isset($auth_promotion)) $auth_arr['auth_promotion'] = $auth_promotion;
		$auth_send	= $this->authmodel->manager_limit_act('member_send');
		if(isset($auth_send)) $auth_arr['auth_send'] = $auth_send;

		// 회원정보다운로드 체크
		$auth_member_down	= $this->authmodel->manager_limit_act('member_download');
		if( !$this->isplusfreenot ){ //무료몰인경우 다운권한 없음
			$auth_member_down = false;
		}
		if(isset($auth_member_down)) $auth_arr['auth_member_down'] = $auth_member_down;
		$this->template->assign('auth_arr',json_encode($auth_arr));

		### GROUP
		$group_arr = $this->membermodel->find_group_list();
		$search_type_arr = array(
			'user_name' => '이름',
			'userid' => '아이디',
			'email' => '이메일',
			'phone' => '전화번호(네자리)',
			'cellphone' => '핸드폰(네자리)',
			'address' => '주소',
			'nickname' => '닉네임'
		);

		$this->load->library('searchsetting');
		$_default 						= array('orderby'=>'member_seq','sort'=>'desc','page'=>0,'perpage'=>10);
		$scRes 							= $this->searchsetting->pagesearchforminfo("member_catalog",$_default);
		$sc_form 						= $scRes['form'];
		$sc_datePreset					= $scRes['date_preset'];
		$this->template->assign('sc_form',$sc_form);

		unset($scRes['form']);

		$sc					= $scRes;
		$sc['selected']['search_type'][$sc['search_type']]	= "selected";
		$sc['selected']['sc_day_type'][$sc['sc_day_type']]	= "selected";
		$sc['selected']['lastlogin_search_type'][$sc['lastlogin_search_type']]	= "selected";
		$sc['selected']['grade'][$sc['grade']]	= "selected";

		$sc['checkbox']['business_seq'][$sc['business_seq']]	= "checked";
		$sc['checkbox']['status'][$sc['status']]	= "checked";
		$sc['checkbox']['sitetype'][$sc['sitetype']]	= "checked";
		$sc['checkbox']['snsrute'][$sc['snsrute']]	= "checked";

		if($sc_datePreset[$scRes['select_date_regist']] && empty($sc['regist_sdate']) && empty($sc['regist_edate'])) {
			$sc['regist_sdate'] = $sc_datePreset[$scRes['select_date_regist']][0];
			$sc['regist_edate'] = $sc_datePreset[$scRes['select_date_regist']][1];
		}
		if($sc_datePreset[$scRes['select_date_birthday']] && empty($sc['birthday_sdate']) && empty($sc['birthday_edate'])) {
			$sc['birthday_sdate'] = $sc_datePreset[$scRes['select_date_birthday']][0];
			$sc['birthday_edate'] = $sc_datePreset[$scRes['select_date_birthday']][1];
		}
		if($sc_datePreset[$scRes['select_date_anniversary']] && empty($sc['anniversary_sdate'][0]) && empty($sc['anniversary_edate'][0])) {
			$sc['anniversary_sdate'][0] = substr($sc_datePreset[$scRes['select_date_anniversary']][0],5,2);
			$sc['anniversary_sdate'][1] = substr($sc_datePreset[$scRes['select_date_anniversary']][0],8,2);
			$sc['anniversary_edate'][0] = substr($sc_datePreset[$scRes['select_date_anniversary']][1],5,2);
			$sc['anniversary_edate'][1] = substr($sc_datePreset[$scRes['select_date_anniversary']][1],8,2);
		}
		### 관리자 상단검색 keyword보다 우선됨
		if($this->input->get('header_search_keyword')) {
			$sc['keyword'] = $this->input->get('header_search_keyword');
		}
		### MEMBER
		$data = $this->membermodel->admin_member_list_spout($sc); //프로세스 변경 kmj

		### PAGE & DATA
		$sc['searchcount']	 = $data['count'];
		$sc['total_page']	 = ceil($sc['searchcount']	 / $sc['perpage']);
		$cntquery = $this->db->query("select count(*) as cnt from fm_member where status in ('done','hold','dormancy')");
		$cntrow = $cntquery->result_array();
		$sc['totalcount'] = $cntrow[0]['cnt'];

		$idx = 0;
		$this->load->model('Goodsreview','Boardmodel');//리뷰건
		foreach($data['result'] as $datarow){
			$idx++;
			$datarow['number']	= $sc['searchcount']	 - ( ($sc['page'] -1 ) * 1 + $idx + 1) + 1;

			//기업회원 정보 매칭 kmj
			if($datarow['mtype'] == 'business'){
				$datarow['type']	= '기업';

				$bus_info = $this->db->query("seLECT
						business_seq, bname, bcellphone, bphone
					fROM
						fm_member_business
					wHERE
						member_seq = ? limit 0, 1", $datarow['member_seq'])->result_array();

				if($bus_info[0]){
					$datarow['business_seq']	= $bus_info[0]['business_seq'];
					$datarow['user_name']		= $bus_info[0]['bname'];
					$datarow['cellphone']		= $bus_info[0]['bcellphone'];
					$datarow['phone']			= $bus_info[0]['bphone'];
				} else {
					$datarow['business_seq']	= '';
					$datarow['user_name']		= '';
					$datarow['cellphone']		= '';
					$datarow['phone']			= '';
				}
			} else {
				$datarow['type']	= '개인';
			}

			//그룹 정보 매칭 kmj
			$group_info = $this->db->query("seLECT
						group_name
					fROM
						fm_member_group
					wHERE
						group_seq = ? limit 0, 1", $datarow['group_seq'])->result_array();
			if($group_info[0]){
				$datarow['group_name'] = $group_info[0]['group_name'];
			} else {
				$datarow['group_name'] = '';
			}

			//유입 정보 매칭 kmj
			if(!$datarow['referer_domain']){
				$datarow['referer_name'] = '직접입력';
			} else {
				$referer_info = $this->db->query("seLECT
							referer_group_name
						fROM
							fm_referer_group
						wHERE
							referer_group_url = ? limit 0, 1", $datarow['referer_domain'])->result_array();
				if($referer_info[0]){
					$datarow['referer_name'] = $referer_info[0]['referer_group_name'];
				} else {
					$datarow['referer_name'] = '기타';
				}
			}

			//리뷰건
			$sc['whereis'] = ' and mseq <> \'\' and mseq='.$datarow['member_seq'];
			$sc['select'] = ' count(gid) as cnt ';
			$gdreviewquery = $this->Boardmodel->get_data($sc);
			$datarow['gdreview_sum'] = $gdreviewquery['cnt'];

			if($datarow['rute'] != "none" ) {
				$snsmbsc['select'] = ' * ';
				$snsmbsc['whereis'] = ' and member_seq = \''.$datarow['member_seq'].'\' ';
				$snslist = $this->snsmember->snsmb_list($snsmbsc);
				if($snslist['result'][0]) $datarow['snslist'] = $snslist['result'];
			}

			$datarow['regist_date'] 	= date("Y-m-d H:i", strtotime($datarow['regist_date']));
			$datarow['lastlogin_date'] 	= date("Y-m-d H:i", strtotime($datarow['lastlogin_date']));

			/****/

			$dataloop[] = $datarow;
		}

		## 유입경로 그룹
		$this->load->model('statsmodel');
		$referer_list	= $this->statsmodel->get_referer_grouplist();
		$this->template->assign('referer_list',$referer_list);

		###
		if(isset($data)) $this->template->assign('loop',$dataloop);
		$paginlay = pagingtag($sc['searchcount'],$sc['perpage'],$this->membermodel->admin_member_url($file_path).'?', getLinkFilter('',array_keys($sc)) );

		if(empty($paginlay))$paginlay = '<p><a class="on red">1</a><p>';

		//가입환경
		$sitetypeloop = sitetype($_GET['sitetype'], 'image', 'array');
		$this->template->assign('sitetypeloop',$sitetypeloop);

		//가입양식
		$ruteloop = memberrute($_GET['rute'], 'image', 'array' , 'search');
		$this->template->assign('ruteloop',$ruteloop);

		$this->template->assign('pagin',$paginlay);
		$this->template->assign('group_arr',$group_arr);
		$this->template->assign('search_type_arr',$search_type_arr);
		$this->template->assign('perpage',$sc['perpage']);
		$this->template->assign('sc',$sc);


		$this->template->assign('query_string',get_query_string());

		$reserve = config_load('reserve');
		$this->template->assign('reserveinfo',$reserve);

		$point_use_button = "pointNotForm";
		if($reserve['point_use']=="Y") $point_use_button = "batchForm";
		$this->template->assign('point_use_button',$point_use_button);

		$this->template->assign(array('sc'=>$sc,'scObj'=>json_encode($sc)));

		$this->template->define('member_list',$this->skin.'/member/member_list.html');
		$this->template->define('member_search',$this->skin.'/member/member_search.html');
		$this->template->define(array('tpl'=>$file_path));
		$this->template->print_("tpl");
	}

	### 회원리스트
	public function dormancy_catalog()
	{
		$auth = $this->authmodel->manager_limit_act('dormancy_view');
		if(!$auth){
			pageBack("권한이 없습니다.");
			exit;
		}

		$this->load->model('membermodel');
		$this->admin_menu();
		$this->tempate_modules();
		$file_path	= $this->template_path();


		### SEARCH
		$sc = $_GET;
		$sc['orderby']			= (isset($_GET['orderby'])) ?	$_GET['orderby']:'d.log_seq';
		$sc['sort']				= (isset($_GET['sort'])) ?		$_GET['sort']:'desc';
		$sc['page']				= (isset($_GET['page'])) ?		intval($_GET['page']):0;
		$sc['perpage']			= (isset($_GET['perpage'])) ?	intval($_GET['perpage']):10;
		$sc['status']			= ($_GET['status'] == "all") ?	"":$_GET['status'];

		### MEMBER
		$data = $this->membermodel->admin_member_dr_list($sc);

		### PAGE & DATA
		$sc['searchcount']	 = $data['count'];
		$sc['total_page']	 = ceil($sc['searchcount']	 / $sc['perpage']);

		$cntquery = $this->db->query("select count(*) as cnt from fm_dormancy_log");
		$cntrow = $cntquery->result_array();
		$sc['totalcount'] = $cntrow[0]['cnt'];

		foreach($data['result'] as $datarow){
			$dataloop[] = $datarow;
		}

		###
		if(isset($data)) $this->template->assign('loop',$dataloop);
		$paginlay = pagingtag($sc['searchcount'],$sc['perpage'],$this->membermodel->admin_member_url($file_path).'?', getLinkFilter('',array_keys($sc)) );

		if(empty($paginlay))$paginlay = '<p><a class="on red">1</a><p>';

		$this->template->assign('pagin',$paginlay);
		$this->template->assign('perpage',$sc['perpage']);
		$this->template->assign('sc',$sc);

		$this->template->assign('query_string',get_query_string());

		$auth_send	= $this->authmodel->manager_limit_act('member_send');
		if(isset($auth_send)) $this->template->assign('auth_send',$auth_send);

		$this->template->define('dormancy_list',$this->skin.'/member/dormancy_list.html');
		$this->template->define('dormancy_search',$this->skin.'/member/dormancy_search.html');
		$this->template->define(array('tpl'=>$file_path));
		$this->template->print_("tpl");
	}

	public function set_search_default(){
		foreach($_POST as $key => $data){
			if( is_array($data) ){
				foreach($data as $key2 => $data2){
					if($data2) $cookie_arr[] = $key."[".$key2."]"."=".$data2;
				}
			}else if($data){
				$cookie_arr[] = $key."=".$data;
			}
		}
		if($cookie_arr){
			$cookie_str = implode('&',$cookie_arr);
			if($_POST['gb']=='withdrawal'){
				$_COOKIE['withdrawal_search'] = $cookie_str;
				setcookie('withdrawal_search',$cookie_str,time()+86400*30);
			}else{
				$_COOKIE['member_list_search'] = $cookie_str;
				setcookie('member_list_search',$cookie_str,time()+86400*30);
			}
		}
		$callback = "parent.closeDialog('search_detail_dialog');parent.location.reload();";
		openDialogAlert("설정이 저장 되었습니다.",400,140,'parent',$callback);
	}

	public function get_search_default(){
		$arr = explode('&',$_COOKIE['member_list_search']);
		foreach($arr as $data){
			$arr2 = explode("=",$data);
			$result[] = $arr2;

		}
		echo json_encode($result);
	}

	public function get_search_withdrawal(){
		$arr = explode('&',$_COOKIE['withdrawal_search']);
		foreach($arr as $data){
			$arr2 = explode("=",$data);
			$result[] = $arr2;

		}
		echo json_encode($result);
	}

	public function set_search_dormancy(){

		$this->load->model('searchdefaultconfigmodel');

		$param_order = $_POST;
		$param_order['search_page'] = 'admin/member/dormancy';

		$this->searchdefaultconfigmodel->set_search_default($param_order);

		$callback = "parent.closeDialog('search_detail_dialog');";
		openDialogAlert("설정이 저장 되었습니다.",400,140,'parent',$callback);
	}

	public function get_search_dormancy(){

		$this->load->model('searchdefaultconfigmodel');
		$data_search_default_str = $this->searchdefaultconfigmodel->get_search_default_config('admin/member/dormancy');
		parse_str($data_search_default_str['search_info'], $data_search_default);
		echo json_encode($data_search_default);
	}

	### 회원상세
	public function detail()
	{
		$this->admin_menu();
		$this->tempate_modules();
		$file_path	= $this->template_path();

		#### AUTH
		$auth_act		= $this->authmodel->manager_limit_act('member_act');
		if(isset($auth_act)) $this->template->assign('auth_act',$auth_act);
		$auth_promotion = $this->authmodel->manager_limit_act('member_promotion');
		if(isset($auth_promotion)) $this->template->assign('auth_promotion',$auth_promotion);
		$auth_send	= $this->authmodel->manager_limit_act('member_send');
		if(isset($auth_send)) $this->template->assign('auth_send',$auth_send);

		if(!isset($_GET['member_seq'])){
			$callback = "parent.history.back();";
			openDialogAlert("잘못된 접근입니다.",400,140,'parent',$callback);
			die();
		}

		redirect("/admincrm/main/user_detail?member_seq=".$_GET['member_seq']);//@2016-06-01 crm page

		// 개인정보 조회 로그
		//'member', 'memberlist', 'order', 'export', 'return', 'refund', 'orderexcel', 'exportexcel'
		$this->load->model('logPersonalInformation');
		$this->logPersonalInformation->insert('member',$this->managerInfo['manager_seq'],$_GET['member_seq']);

		###
		$this->load->model('membermodel');
		$data		= $this->membermodel->get_member_data($_GET['member_seq']);
		if($data['auth_type']=='auth'){
			$data['auth_type'] = "실명인증";
		}else if($data['auth_type']=='ipin'){
			$data['auth_type'] = "아이핀";
		}else{
			$data['auth_type'] = "없음";
		}

		$data['zip_arr'] = explode("-",$data['zipcode']);
		$data['bzip_arr'] = explode("-",$data['bzipcode']);

		/*
		 * 코드별 출력 내용(KR ORI)
		 * mp292: 배송 주문 불만족
		 * mp293: 사이트 이용 불편
		 * mp294: 상품품질 불만족
		 * mp295: 서비스 불만족
		 * mp291: 기타 / 기타 항목 마지막에 표시
		 */
		$withdrawal = array(getAlert('mp292'),getAlert('mp293'),getAlert('mp294'),getAlert('mp295'),getAlert('mp291'));
		$this->template->assign('withdrawal_arr',$withdrawal);

		$this->load->model('myminishopmodel');
		$minisohp	= $this->myminishopmodel->get_minishop_list($_GET['member_seq']);
		$this->template->assign('minisohp',$minisohp);

		$joinform = ($this->joinform)?$this->joinform:config_load('joinform');
		if($joinform) $this->template->assign('joinform',$joinform);
		$this->template->assign('memberIcondata',memberIconConf());

		//가입 추가 정보 리스트
		//$mdata = $this->mdata;
		$qry = "select * from fm_joinform where used='Y' order by sort_seq";
		$query = $this->db->query($qry);
		$form_arr = $query -> result_array();
		foreach ($form_arr as $k => $subdata){
		$msubdata=$this->membermodel->get_subinfo($data['member_seq'],$subdata['joinform_seq']);
		$subdata['label_view'] = $this -> membermodel-> get_labelitem_type($subdata,$msubdata);
		$sub_form[] = $subdata;
		}
		$this->template->assign('form_sub',$sub_form);

		###
		$grade_list = $this->membermodel->find_group_list();
		$grade_list = array_reverse($grade_list);
		$this->template->assign('grade_list',$grade_list);
		//print_r($grade_list);

		//1:1문의건
		$this->load->model('Boardmodel');
		$sc['whereis'] = " and boardid = 'mbqna'   and mseq='".$data['member_seq']."'";
		$sc['select'] = " count(gid) as cnt ";
		$mbqnaquery = $this->Boardmodel->get_data($sc);
		$data['mbqna_sum'] = $mbqnaquery['cnt'];

		$sc['whereis'] = " and re_contents!='' and boardid = 'mbqna'  and mseq='".$data['member_seq']."'";
		$sc['select'] = " count(gid) as cnt ";
		$mbqnareplyquery = $this->Boardmodel->get_data($sc);
		$data['mbqna_reply'] = $mbqnareplyquery['cnt'];//답변완료수 / 전체질문수

		//리뷰건
		$this->load->model('goodsreview');
		$sc['whereis'] = " and mseq='".$data['member_seq']."'";
		$sc['select'] = " count(gid) as cnt ";
		$gdreviewquery = $this->goodsreview->get_data($sc);
		$data['gdreview_sum'] = $gdreviewquery['cnt'];

		//상품문의건
		$this->load->model('goodsqna');
		$sc['whereis'] = "and mseq= ?";
		$bindData[] = $data['member_seq'];
		$sc['select'] = " count(gid) as cnt ";
		$gdqnaquery = $this->goodsqna->get_data($sc, $bindData);
		$data['gdqna_sum'] = $gdqnaquery['cnt'];

		//쿠폰보유건 test
		$this->load->model('couponmodel');
		$this->load->helper('coupon');
		down_coupon_list('admin', $sc , $dataloop);

		$svcount = $this->couponmodel->get_download_have_total_count($sc,$data);
		$this->template->assign($svcount);

		$this->load->model('snsmember');


		$snsmbsc['select'] = " * ";
		$snsmbsc['whereis'] = " and member_seq ='".$data['member_seq']."' ";
		$snslist = $this->snsmember->snsmb_list($snsmbsc);
		if($snslist['result'][0]) $data['snslist'] = $snslist['result'];

		if($data['sns_f']) {//facebook 전용인경우
			foreach($snslist['result'] as $snslist){
				if( $snslist['sns_f'] == $data['sns_f'] ) {
					$snsmb['sns'] = $snslist;
					break;
				}
			}
			if($snsmb) $this->template->assign($snsmb);

			$data['totalinviteck'] = $data['member_invite_cnt'];// 추천회원수

			if( $data['totalinviteck'] ) {
				$fquery = $this->db->query("select count(member_seq) as total from fm_member WHERE fb_invite = '".$data['member_seq']."' and status != 'withdrawal' ");
				$snsftotal = $fquery->row_array();
				$data['totalinvitejoin'] = $snsftotal['total'];//초대후 회원가입된 회원수
			}
		}

		//추천회원정보
		if($data['fb_invite']){
			$fb_invitequery = $this->db->query("select userid from fm_member WHERE member_seq = '".$data['fb_invite']."' and status != 'withdrawal' ");
			$fb_invite = $fb_invitequery->row_array();
			$data['fb_invite_id'] = $fb_invite['userid'];
		}


		$data['totalrecommend'] = $data['member_recommend_cnt'];// 추천회원수

		// 성인인증정보 :: 2015-03-18 lwh
		$adult_query		= $this->db->query("select * from fm_adult_log WHERE member_seq = '".$data['member_seq']."' order by regist_date limit 10");
		$adult_info['res']	= $adult_query->result_array();
		$adult_info['cnt']	= count($adult_info['res']);
		$adult_info['lst']	= $adult_info['res'][$adult_info['cnt']-1]['regist_date'];
		$data['adult_info']	= $adult_info;

		// 회원 처리주문 요약 :: 2015-03-24 lwh
		$orderSummary	= array();
		$step_arr		= array('15'=>'주문접수', '25'=>'결제확인', '35'=>'상품준비', '40'=>'부분출고준비', '45'=>'출고준비', '50'=>'부분출고완료', '55'=>'출고완료', '60'=>'부분배송중', '65'=>'배송중', '70'=>'부분배송완료');

		/*$date_range	= " and b.regist_date between '"
					. date('Y-m-d', strtotime('-100 day'))." 00:00:00' "
					. " and '".date('Y-m-d')." 23:59:59' ";*/
		$sql	= "
				SELECT count(*) as cnt , step
				FROM fm_order as b
				WHERE hidden = 'N'
				and member_seq = '".$data['member_seq']."'
				".$date_range."
				GROUP BY step
				";
		$query	= $this->db->query($sql);
		foreach ($query->result_array() as $row){
			$result[$row['step']]	= $row['cnt'];
		}

		foreach ($step_arr as $key => $val){
			$orderSummary[$key] = array(
			'count'			=> ($result[$key]) ? $result[$key] : 0,
			'name'			=> $val,
			'link'			=> "../order/catalog?chk_step[".$key."]=1&keyword=".$data['userid']
			);

			if($key == '45' || $key == '55' || $key == '65'){
				$orderSummary[$key]['link_export'] = "../export/catalog?export_status[".$key."]=1&keyword=".$data['userid'];
			}
		}

		/* 반품 접수 */
		$query = $this->db->query("select count(*) as cnt from fm_order_return as b left join fm_order as o on b.order_seq = o.order_seq where b.status = 'request' and o.member_seq = '".$data['member_seq']."' ".$date_range);
		$result = $query->row_array();
		$orderSummary['101'] = array(
			'count'		=> ($result['cnt']) ? $result['cnt'] : 0,
			'name'		=> '반품접수',
			'link'		=> '../returns/catalog?return_status[]=request&keyword='.$data['userid']
		);

		/* 환불 접수 */
		$query = $this->db->query("select count(*) as cnt from fm_order_refund as b left join fm_order as o on b.order_seq = o.order_seq where b.status = 'request' and o.member_seq = '".$data['member_seq']."' ".$date_range);
		$result = $query->row_array();
		$orderSummary['102'] = array(
			'count'		=> ($result['cnt']) ? $result['cnt'] : 0,
			'name'		=> '환불접수',
			'link'		=> '../refund/catalog?refund_status[]=request&keyword='.$data['userid']
		);
		// 회원 처리주문 요약 :: END

		for ($m=1;$m<=12;$m++){	$m_arr[] = str_pad($m, 2, '0', STR_PAD_LEFT); }
		for ($d=1;$d<=31;$d++){	$d_arr[] = str_pad($d, 2, '0', STR_PAD_LEFT); }
		$this->template->assign('m_arr',$m_arr);
		$this->template->assign('d_arr',$d_arr);
		$this->template->assign('query_string',$_GET['query_string']);
		$this->template->assign($data);
		$this->template->assign(array('orderSummary'=>$orderSummary));
		$this->template->define(array('tpl'=>$file_path));
		$this->template->print_("tpl");
	}

	public function emoney_detail()
	{
		$this->admin_menu();
		$this->tempate_modules();
		$file_path	= $this->template_path();

		$member_seq = $_GET['member_seq'];
		###
		$this->load->model('membermodel');
		$data = $this->membermodel->get_member_data($member_seq);
		$this->template->assign($data);

		$send_phone = getSmsSendInfo();
		if(isset($send_phone)) $this->template->assign('send_phone',$send_phone);
		###
		include_once $_SERVER['DOCUMENT_ROOT']."/app/libraries/sms.class.php";
		$auth			= config_load('master');
		$sms_id			= $this->config_system['service']['sms_id'];
		$sms_api_key	= $auth['sms_auth'];
		$gabiaSmsApi	= new gabiaSmsApi($sms_id,$sms_api_key);
		$params			= "sms_id=" . $sms_id . '&sms_pw=' . md5($sms_id);
		$params			= makeEncriptParam($params);
		$limit			= $gabiaSmsApi->getSmsCount();
		$this->template->assign('count',$limit);

		$this->template->define(array('tpl'=>$file_path));
		$this->template->print_("tpl");
	}

	public function point_detail()
	{
		$this->admin_menu();
		$this->tempate_modules();
		$file_path	= $this->template_path();

		$member_seq = $_GET['member_seq'];
		###
		$this->load->model('membermodel');
		$data = $this->membermodel->get_member_data($member_seq);
		$this->template->assign($data);

		$send_phone = getSmsSendInfo();
		if(isset($send_phone)) $this->template->assign('send_phone',$send_phone);
		###
		include_once $_SERVER['DOCUMENT_ROOT']."/app/libraries/sms.class.php";
		$auth			= config_load('master');
		$sms_id			= $this->config_system['service']['sms_id'];
		$sms_api_key	= $auth['sms_auth'];
		$gabiaSmsApi	= new gabiaSmsApi($sms_id,$sms_api_key);
		$params			= "sms_id=" . $sms_id . '&sms_pw=' . md5($sms_id);
		$params			= makeEncriptParam($params);
		$limit			= $gabiaSmsApi->getSmsCount();
		$this->template->assign('count',$limit);

		$reserve = config_load('reserve');
		$this->template->assign('reserveinfo',$reserve);

		$this->template->define(array('tpl'=>$file_path));
		$this->template->print_("tpl");
	}

	public function cash_detail()
	{
		$this->admin_menu();
		$this->tempate_modules();
		$file_path	= $this->template_path();

		$member_seq = $_GET['member_seq'];
		###
		$this->load->model('membermodel');
		$data = $this->membermodel->get_member_data($member_seq);
		$this->template->assign($data);

		###
		require_once $_SERVER['DOCUMENT_ROOT']."/app/libraries/SMS_send.class.php";
		$sms	= new SMS_SEND();
		$params	= "sms_id=" . $sms->sms_account . '&sms_pw=' . $sms->sms_password;
		$params = makeEncriptParam($params);
		$limit	= ($sms->limit != -1) ? number_format($sms->limit) : 0;
		$this->template->assign('count',$limit);

		$this->template->define(array('tpl'=>$file_path));
		$this->template->print_("tpl");
	}

	public function sms_pop()
	{
		$aParams	= $this->input->get();

		$this->admin_menu();
		$this->tempate_modules();
		$file_path	= $this->template_path();
		$aParams	= $this->input->get();
		if($aParams['member_seq']){
			$member_seq = $aParams['member_seq'];
			###
			$this->load->model('membermodel');
			$data = $this->membermodel->get_member_data($member_seq);
			$this->template->assign($data);
		}
		/*
			입점사 주문 미처리 현황(/admin/provider/remind_export)에서 문자 발송 시
			받는 사람 정보가 있으면 하이폰(-)이 포함되고 text로 노출되어야 하기 때문에
			hcellphone: 000-0000-0000, 000-0000-0000 (view용)
			cellphone: 하이폰(-)을 제거한 전화번호
		*/
		if($aParams['hcellphone']) { 
			$aParams['cellphone'] = str_replace("-","",$aParams['hcellphone']);
			$aParams['hcellphone'] = str_replace(",",", ",$aParams['hcellphone']);
			$this->template->assign('hcellphone',$aParams['hcellphone']);
		}

		if($aParams['cellphone']) {
			$this->template->assign('cellphone',$aParams['cellphone']);
		}

		
		if ($aParams['page']) {
			$this->template->assign('page', $aParams['page']);
		}

		if ($aParams['order_seq']) {
			$type = $aParams['type'] ?? 'order_cellphone';

			$this->load->model('ordermodel');
			$data = $this->ordermodel->get_order_info($aParams['order_seq']);

			$this->load->library('privatemasking');
			$data = $this->privatemasking->masking($data, 'order');

			$this->template->assign('cellphone', str_replace('-', '', $data[$type]));
			$this->template->assign('order_seq', $aParams['order_seq']);
			$this->template->assign($data);
		}

		//티켓상품의 확인코드 SMS보내기
		if ($aParams['certify_code']) {
			$default_massage = $this->config_basic['shopName'] . '쇼핑몰에서 판매된 티켓 상품에 대하여 구매자가 귀사 매장 방문 시 티켓 사용 확인코드는 ' . $aParams['certify_code'] . '입니다.';
		}
		// 입점사 물류담당자 SMS 보내기
		if ($aParams['type'] == 'provider_person') {
			$default_massage = $this->config_basic['shopName'] . " [{$aParams['provider_name']}] 미발송된 상품이 있습니다. 주문을 확인하시어 빠른 발송 처리 바랍니다.";
			$type = $aParams['type'];
		}

		if ($type) {
			$this->template->assign('type', $type);
		}
		if ($default_massage) {
			$this->template->assign('default_massage', $default_massage);
		}

		if (preg_match('/admin\/member/', $this->input->server('HTTP_REFERER'))) {
			$this->template->assign('css', 'common-ui');
		}


		###
		include_once $_SERVER['DOCUMENT_ROOT']."/app/libraries/sms.class.php";
		$auth			= config_load('master');
		$sms_id			= $this->config_system['service']['sms_id'];
		$sms_api_key	= $auth['sms_auth'];
		$gabiaSmsApi	= new gabiaSmsApi($sms_id,$sms_api_key);
		$params			= "sms_id=" . $sms_id . '&sms_pw=' . md5($sms_id);
		$params			= makeEncriptParam($params);
		$limit			= $gabiaSmsApi->getSmsCount();
		$this->template->assign('count',$limit);

		$this->template->define(array('tpl'=>$file_path));
		$this->template->print_("tpl");
	}


	public function email_pop()
	{
		$this->admin_menu();
		$this->tempate_modules();
		$file_path	= $this->template_path();
		
		$aParams = $this->input->get();
		$member_seq = $aParams['member_seq'];
		if($member_seq){
			$this->load->model('membermodel');
			$data = $this->membermodel->get_member_data($member_seq);
			$this->template->assign($data);
		}else if($aParams['email']){
			$data['email'] = $aParams['email'];
			$this->template->assign($data);
		}
		if($aParams['order_seq']){
			$type = $aParams['type'] ?? 'order_email';

			$this->load->model('ordermodel');
			$data = $this->ordermodel->get_order_info($aParams['order_seq']);

			$this->load->library('privatemasking');
			$data = $this->privatemasking->masking($data, 'order');

			$data['email'] = $data[$type];

			$this->template->assign('email', $data['email']);
			$this->template->assign('order_seq', $aParams['order_seq']);
			$this->template->assign('type', $type);
			$this->template->assign($data);
		}

		$basic = ($this->config_basic)?$this->config_basic:config_load('basic');

		if(preg_match('/admin\/member/',$this->input->server('HTTP_REFERER'))) {
			$this->template->assign('css','common-ui');
		}
		###
		$query = $this->db->query("select * from fm_log_email order by seq desc limit 10");
		$emailData = $query->result_array();

		###
		$this->load->model('usedmodel');
		$email_chk = $this->usedmodel->hosting_check();
		$this->template->assign('email_chk',$email_chk);

		###
		$this->template->assign(array('mail_count'=>master_mail_count(),'email'=>$data['email']));
		$this->template->assign('loop',$emailData);

		$this->template->define(array('tpl'=>$file_path));
		$this->template->print_("tpl");
	}

	public function cash_pop()
	{
		$this->admin_menu();
		$this->tempate_modules();
		$file_path	= $this->template_path();

		$member_seq = $_GET['member_seq'];
		if($member_seq){
			$this->load->model('membermodel');
			$data = $this->membermodel->get_member_data($member_seq);
			$this->template->assign($data);
		}else if($_GET['email']){
			$data['email'] = $_GET['email'];
			$this->template->assign($data);
		}

		$basic = ($this->config_basic)?$this->config_basic:config_load('basic');

		###
		$query = $this->db->query("select * from fm_log_email order by seq desc limit 10");
		$emailData = $query->result_array();

		###
		$this->load->model('usedmodel');
		$email_chk = $this->usedmodel->hosting_check();
		$this->template->assign('email_chk',$email_chk);

		###
		$this->template->assign(array('mail_count'=>master_mail_count(),'email'=>$data['email']));
		$this->template->assign('loop',$emailData);

		$this->template->define(array('tpl'=>$file_path));
		$this->template->print_("tpl");
	}

	public function emoney_list()
	{
		$this->admin_menu();
		$this->tempate_modules();
		$file_path	= $this->template_path();

		$member_seq = $_GET['member_seq'];

		###
		$sc = $_GET;
		$sc['page']				= (isset($_GET['page'])) ?		intval($_GET['page']):'0';
		$sc['member_seq']		= $member_seq;
		$sc['perpage']			= '5';

		$this->load->model('membermodel');

		$data = $this->membermodel->emoney_list($sc);

		$sc['searchcount']	 = $data['count'];
		$sc['total_page']	 = ceil($sc['searchcount']	 / $sc['perpage']);
		$sc['totalcount']	 = get_rows('fm_emoney',array('member_seq'=>$member_seq));
		$idx = 0;
		foreach($data['result'] as $datarow){
			$idx++;
			$datarow['number']	= $sc['searchcount']	 - ( ($sc['page'] -1 ) * 1 + $idx + 1) + 1;
			/**
			$datarow['contents'] = "";
			if($datarow['type']=='order'){
				$datarow['contents'] = "주문번호 ".$datarow['ordno'];
			}else if($datarow['type']=='join'){
				$datarow['contents'] = "회원가입";
			}else if($datarow['type']=='bookmark'){
				$datarow['contents'] = "즐겨찾기";
			}else if($datarow['type']=='refund'){
				$datarow['contents'] = "환불 ".$datarow['ordno'];
			}
			**/
			$datarow['mname'] = get_manager_name($datarow['manager_seq']);
			$dataloop[] = $datarow;
		}

		###
		if(isset($data)) $this->template->assign('loop',$dataloop);
		$paginlay = pagingtag($sc['searchcount'],$sc['perpage'],$this->membermodel->admin_member_url($file_path).'?', getLinkFilter('',array_keys($sc)) );
		if(empty($paginlay))$paginlay = '<p><a class="on red">1</a><p>';
		$this->template->assign('pagin',$paginlay);

		$this->template->assign('perpage',$sc['perpage']);
		$this->template->assign('sc',$sc);
		$this->template->define(array('tpl'=>$file_path));
		$this->template->print_("tpl");
	}

	public function used_history(){
		//$this->admin_menu();
		//$this->tempate_modules();
		$file_path	= $this->template_path();

		$type	= $_GET['type'];
		$seq	= $_GET['seq'];
		$table	= "fm_".$type;
		$seq_nm	= $type."_seq";

		$sql = "select A.*,B.ordno AS bordno, B.ordno AS bordno, B.".$type." AS bemoney, B.remain AS bremain, B.regist_date AS bregist_date, B.memo as bmemo from fm_used_log A left join {$table} B ON A.used_seq = B.{$seq_nm} where A.parent_seq = '{$seq}' order by A.seq asc";
		$query = $this->db->query($sql);
		$bemoney = 0;
		foreach($query->result_array() as $v){
			$loop[] = $v;
			$bemoney_total += $v['bemoney'];
			$used_amt_total += $v['used_amt'];
			$last_remain = $v['remain'];
		}
		$this->template->assign('bemoney_total',($bemoney_total));
		$this->template->assign('used_amt_total',($used_amt_total));
		$this->template->assign('last_remain',($last_remain));
		$this->template->assign('order_memo',$loop[0][memo]);
		$this->template->assign('bregist_date',$loop[0][bregist_date]);

		$this->template->assign('loop',$loop);

		$this->template->define(array('tpl'=>$file_path));
		$this->template->print_("tpl");
	}

	public function point_list()
	{
		$this->admin_menu();
		$this->tempate_modules();
		$file_path	= $this->template_path();

		$member_seq = $_GET['member_seq'];

		###
		$sc = $_GET;
		$sc['page']				= (isset($_GET['page'])) ?		intval($_GET['page']):'0';
		$sc['member_seq']		= $member_seq;
		$sc['perpage']			= '5';

		$this->load->model('membermodel');

		$data = $this->membermodel->point_list($sc);

		$sc['searchcount']	 = $data['count'];
		$sc['total_page']	 = ceil($sc['searchcount']	 / $sc['perpage']);
		$sc['totalcount']	 = get_rows('fm_point',array('member_seq'=>$member_seq));
		$idx = 0;
		foreach($data['result'] as $datarow){
			$idx++;
			$datarow['number']	= $sc['searchcount']	 - ( ($sc['page'] -1 ) * 1 + $idx + 1) + 1;
			/**
			$datarow['contents'] = "";
			if($datarow['type']=='order'){
				$datarow['contents'] = "주문번호 ".$datarow['ordno'];
			}else if($datarow['type']=='join'){
				$datarow['contents'] = "회원가입";
			}else if($datarow['type']=='bookmark'){
				$datarow['contents'] = "즐겨찾기";
			}else if($datarow['type']=='refund'){
				$datarow['contents'] = "환불 ".$datarow['ordno'];
			}
			**/
			$datarow['mname'] = get_manager_name($datarow['manager_seq']);
			$dataloop[] = $datarow;
		}

		###
		if(isset($data)) $this->template->assign('loop',$dataloop);
		$paginlay = pagingtag($sc['searchcount'],$sc['perpage'],$this->membermodel->admin_member_url($file_path).'?', getLinkFilter('',array_keys($sc)) );
		if(empty($paginlay))$paginlay = '<p><a class="on red">1</a><p>';
		$this->template->assign('pagin',$paginlay);

		$this->template->assign('perpage',$sc['perpage']);
		$this->template->assign('sc',$sc);
		$this->template->define(array('tpl'=>$file_path));
		$this->template->print_("tpl");
	}

	public function cash_list()
	{
		$this->admin_menu();
		$this->tempate_modules();
		$file_path	= $this->template_path();

		$member_seq = $_GET['member_seq'];

		###
		$sc = $_GET;
		$sc['page']				= (isset($_GET['page'])) ?		intval($_GET['page']):'0';
		$sc['member_seq']		= $member_seq;
		$sc['perpage']			= '5';

		$this->load->model('membermodel');

		$data = $this->membermodel->cash_list($sc);

		$sc['searchcount']	 = $data['count'];
		$sc['total_page']	 = ceil($sc['searchcount']	 / $sc['perpage']);
		$sc['totalcount']	 = get_rows('fm_point',array('member_seq'=>$member_seq));
		$idx = 0;
		foreach($data['result'] as $datarow){
			$idx++;
			$datarow['number']	= $sc['searchcount']	 - ( ($sc['page'] -1 ) * 1 + $idx + 1) + 1;
			/**
			$datarow['contents'] = "";
			if($datarow['type']=='order'){
				$datarow['contents'] = "주문번호 ".$datarow['ordno'];
			}else if($datarow['type']=='join'){
				$datarow['contents'] = "회원가입";
			}else if($datarow['type']=='bookmark'){
				$datarow['contents'] = "즐겨찾기";
			}else if($datarow['type']=='refund'){
				$datarow['contents'] = "환불 ".$datarow['ordno'];
			}
			**/
			$datarow['mname'] = get_manager_name($datarow['manager_seq']);
			$dataloop[] = $datarow;
		}

		###
		if(isset($data)) $this->template->assign('loop',$dataloop);
		$paginlay = pagingtag($sc['searchcount'],$sc['perpage'],$this->membermodel->admin_member_url($file_path).'?', getLinkFilter('',array_keys($sc)) );
		if(empty($paginlay))$paginlay = '<p><a class="on red">1</a><p>';
		$this->template->assign('pagin',$paginlay);

		$this->template->assign('perpage',$sc['perpage']);
		$this->template->assign('sc',$sc);
		$this->template->define(array('tpl'=>$file_path));
		$this->template->print_("tpl");
	}

	//초대하기 내역입니다.
	public function invite_list()
	{
		$this->admin_menu();
		$this->tempate_modules();
		$file_path	= $this->template_path();
		###

		$this->load->model('membermodel');
		$this->load->model('snsfbinvite');
		unset($sc);
		### SEARCH
		$sc = $_GET;
		$sc['orderby']		= (!empty($_GET['orderby'])) ?	$_GET['orderby']:'seq';
		$sc['sort']				= (!empty($_GET['sort'])) ?			$_GET['sort']:'desc';
		$sc['page']			= (!empty($_GET['page'])) ?		intval($_GET['page']):0;
		$sc['perpage']		= (!empty($_GET['perpage'])) ?	intval($_GET['perpage']):10;
		$sc['member_seq']			= $_GET['member_seq'];
		$data = $this->snsfbinvite->snsinvite_list_search($sc);

		/**
		 * count setting
		**/
		$sc['searchcount']	 = $data['count'];
		$sc['total_page']	 = @ceil($sc['searchcount']	 / $sc['perpage']);
		$sc['totalcount']	 = $this->snsfbinvite->get_item_total_count($sc);

		$idx = 0;
		foreach($data['result'] as $datarow){
			$idx++;
			$datarow['number']	= $sc['searchcount']	 - ( ($sc['page'] -1 ) * 1 + $idx + 1) + 1;
			$datarow['date']			= substr($datarow['r_date'],2,14);//초대일

			$datarow['joinck'] = ($datarow['joinck'] == 1)? "Y":"N";
			$dataloop[] = $datarow;
		}

		if(isset($data)) $this->template->assign('loop',$dataloop);
		$paginlay = pagingtagfront($sc['searchcount'],$sc['perpage'],'?member_seq='.$_GET['member_seq'], getLinkFilter('',array_keys($sc)) );
		if(empty($paginlay))$paginlay = '<p><a class="on red">1</a><p>';
		$this->template->assign('pagin',$paginlay);
		$this->template->assign('perpage',$sc['perpage']);
		$this->template->assign('sc',$sc);

		$this->template->define(array('tpl'=>$file_path));
		$this->template->print_("tpl");
	}


	//추천하기 내역입니다.
	public function recommend_list()
	{
		$this->admin_menu();
		$this->tempate_modules();
		$file_path	= $this->template_path();
		###

		$this->load->model('membermodel');
		$data = $this->membermodel->get_member_data($_GET['member_seq']);
		unset($sc);
		### SEARCH
		$sc = $_GET;
		$sc['orderby']		= (!empty($_GET['orderby'])) ?	$_GET['orderby']:'seq';
		$sc['sort']				= (!empty($_GET['sort'])) ?			$_GET['sort']:'desc';
		$sc['page']			= (!empty($_GET['page'])) ?		intval($_GET['page']):0;
		$sc['perpage']		= (!empty($_GET['perpage'])) ?	intval($_GET['perpage']):10;
		$sc['recommend']			= $data['userid'];
		$data = $this->membermodel->recommend_list($sc);

		/**
		 * count setting
		**/
		$sc['searchcount']	 = $data['count'];
		$sc['total_page']	 = @ceil($sc['searchcount']	 / $sc['perpage']);
		$sc['totalcount']	 = $this->membermodel->recommend_total_count($sc);

		$idx = 0;
		foreach($data['result'] as $datarow){
			$idx++;
			$datarow['number']	= $sc['searchcount']	 - ( ($sc['page'] -1 ) * 1 + $idx + 1) + 1;
			$datarow['date']			= substr($datarow['regist_date'],2,14);//추천(가입)일
			$dataloop[] = $datarow;
		}
		if(isset($data)) $this->template->assign('loop',$dataloop);
		$paginlay = pagingtagfront($sc['searchcount'],$sc['perpage'],'?member_seq='.$_GET['member_seq'], getLinkFilter('',array_keys($sc)) );
		if(empty($paginlay))$paginlay = '<p><a class="on red">1</a><p>';
		$this->template->assign('pagin',$paginlay);
		$this->template->assign('perpage',$sc['perpage']);
		$this->template->assign('sc',$sc);

		$this->template->define(array('tpl'=>$file_path));
		$this->template->print_("tpl");
	}

	###
	public function withdrawal()
	{
		$aGetParams = $this->input->get();

		// validation
		if ($aGetParams) {
			$this->validation->set_data($aGetParams);
			$this->validation->set_rules('member_seq', '회원번호', 'trim|numeric|xss_clean');
			$this->validation->set_rules('orderby', '정렬선택', 'trim|string|xss_clean');
			$this->validation->set_rules('sort', '정렬방법', 'trim|string|xss_clean');
			$this->validation->set_rules('searchcount', '검색수', 'trim|numeric|xss_clean');
			$this->validation->set_rules('type', '종류', 'trim|string|xss_clean');
			$this->validation->set_rules('keyword', '검색어', 'trim|string|xss_clean');
			$this->validation->set_rules('sdate', '시작일', 'trim|string|xss_clean');
			$this->validation->set_rules('edate', '종료일', 'trim|string|xss_clean');
			$this->validation->set_rules('perpage', '페이지갯수', 'trim|numeric|xss_clean');
			$this->validation->set_rules('page', '페이지', 'trim|numeric|xss_clean');
			if ($this->validation->exec() === false) {
				show_error($this->validation->error_array['value']);
			}
		}

		$auth = $this->authmodel->manager_limit_act('withdrawal_view');
		if(!$auth){
			pageBack("권한이 없습니다.");
			exit;
		}

		$this->admin_menu();
		$this->tempate_modules();
		$file_path	= $this->template_path();

		###
		$sc = $this->input->get();
		$sc['orderby'] = (isset($sc['orderby'])) ? $sc['orderby']:'withdrawal_seq';
		$sc['sort'] = (isset($sc['sort'])) ? $sc['sort']:'desc';
		$sc['page'] = (isset($sc['page'])) ? intval($sc['page']):'0';
		$sc['perpage'] = (isset($sc['perpage'])) ? intval($sc['perpage']):'10';

		###
		$this->load->model('membermodel');
		$data = $this->membermodel->admin_withdrawal_list($sc);
		$sc['searchcount']	 = $data['searchcount'];
		$sc['total_page']	 = ceil($sc['searchcount']	 / $sc['perpage']);
		$sc['totalcount']	 = $data['totalcount'];
		$idx = 0;
		foreach($data['result'] as $datarow){
			$idx++;
			$datarow['number']	= $sc['searchcount']	 - ( ($sc['page'] -1 ) * 1 + $idx + 1) + 1;
			$dataloop[] = $datarow;
		}

		###
		if(isset($data)) $this->template->assign('loop',$dataloop);
		$paginlay = pagingtag($sc['searchcount'],$sc['perpage'],$this->membermodel->admin_member_url($file_path).'?', getLinkFilter('',array_keys($sc)) );
		if(empty($paginlay))$paginlay = '<p><a class="on red">1</a><p>';
		$this->template->assign('pagin',$paginlay);

		$this->template->assign('perpage',$sc['perpage']);
		$this->template->assign('sc',$sc);

		$this->template->define(array('tpl'=>$file_path));
		$this->template->print_("tpl");
	}

	public function sms_group(){
	}

	### SMS발송관리
	public function sms(){
		$auth = $this->authmodel->manager_limit_act('member_send');
		if(!$auth){
			pageBack("권한이 없습니다.");
			exit;
		}

		$this->admin_menu();
		$this->tempate_modules();
		$file_path	= $this->template_path();
		$this->load->model('membermodel');
		$this->load->model('kakaotalkmodel');

		// 설정정보 호출
		$kakaotalk_config	= $this->kakaotalkmodel->get_service();
		if($kakaotalk_config['status'] == 'A' && $kakaotalk_config['use_service'] == 'Y'){
			$kakaotalk_use = true;
			$this->template->assign('kakaouse','Y');

			// 카카오 메세지 정보 호출 :: 2018-03-15 lwh
			$msg_list = $this->kakaotalkmodel->get_msg_code($scParams);
			$this->template->assign('msg_code',$msg_list);
		}

		$auth_promotion = $this->authmodel->manager_limit_act('member_promotion');
		if(isset($auth_promotion)) $this->template->assign('auth_promotion',$auth_promotion);
		$auth_send	= $this->authmodel->manager_limit_act('member_send');
		if(isset($auth_send)) $this->template->assign('auth_send',$auth_send);

		$send_phone = getSmsSendInfo();
		if(isset($send_phone)) $this->template->assign('send_phone',$send_phone);

		##
		$sms_info = config_load('sms_info');
		if($sms_info['send_num']) $send_num = explode("-",$sms_info['send_num']);
		if($sms_info['admis_cnt']>0){
			for($i=0;$i<$sms_info['admis_cnt'];$i++){
				$id = "admins_num_".$i;
				$v['number'] = explode("-",$sms_info[$id]);
				$admins_arr[] = $v;
			}
		}

		###
		$sms		= config_load('sms');
		$sms_rest	= config_load('sms_restriction');
		if(!$this->scm_cfg)	$this->scm_cfg	= config_load('scm');
		$scm_use_chk	= ($this->scm_cfg['use'] == 'Y' && $this->scm_cfg['scm_type'] == 'local') ? 'Y' : 'N';

		$this->config->load('smsGroup');
		$msg_group = $this->config->item('msg_group');

		## 발송제한 설정 시간 및 예약발송시간
		if($sms_rest['config_time_s'] && $sms_rest['config_time_e'] && $sms_rest['reserve_time']){
			if($sms_rest['reserve_time'] > 60){
				$sms_rest['reserve_time'] = ($sms_rest['reserve_time']/60)."시간";
			}else{
				$sms_rest['reserve_time'] .= "분";
			}
			$restriction_msg = "<span style='color:#d90000;font-size:11px;line-height:14px;'>발송제한시간 : ";
			$restriction_msg.= $sms_rest['config_time_s']."시~".$sms_rest['config_time_e']."시 ";
			$restriction_msg.= " ▶ 08시 +".$sms_rest['reserve_time']."</span>";
		}else{
			$restriction_msg = "";
		}

		if(!$sms['deposit_user']) $sms['deposit_user'] = "[{shopName}] {ord_item}의 주문({ordno})
입금안내 드립니다.
{bank_account}  {settleprice}";
		if(!$sms['deposit_admin']) $sms['deposit_admin'] = "[{shopName}] {ord_item}의 주문({ordno})
입금안내 드립니다.
{bank_account}  {settleprice}";

		if(!$sms['dormancy_user']) $sms['dormancy_user'] = "{userid}님 {shopName}에 1년동안 로그인하지 않아 {dormancy_du_date}에 휴면처리될 예정입니다.";


		$this->template->assign('deposit_user',$sms['deposit_user']);
		$this->template->assign('deposit_admin',$sms['deposit_admin']);
		$this->template->assign('deposit_user_yn',$sms['deposit_user_yn']);

		$this->template->assign('deposit_send_day',$sms['deposit_send_day']);
		$this->template->assign('deposit_send_time',$sms['deposit_send_time']);

		$this->template->assign('dormancy_send_time',$sms['dormancy_send_time']);
		$this->template->assign('dormancy_user',$sms['dormancy_user']);
		$this->template->assign('dormancy_user_yn',$sms['dormancy_user_yn']);
		$this->template->assign('scm_use_chk',$scm_use_chk);

		for($i=0;$i<$sms_info['admis_cnt'];$i++){
			$deposit_admins_chk[$i] = $sms['deposit_admins_yn_'.$i];
		}
		$this->template->assign('deposit_admins_chk',$deposit_admins_chk);


		/* 기본 메시지가 빈 값이 있는 경우가 있어서 추가 leewh 2014-10-20 */
		$msg_arr			= parse_ini_file(APPPATH."config/_default_sms_msg.ini", true);
		foreach ($msg_group as $k => $data){
			$sms_arr		= $data['name'];
			$sms_text		= $data['title'];
			$sms_dis		= $data['disable'];
			$sms_user_dis	= $data['user_disable'];
			$user_req		= $data['user_req'];
			$sms_provider	= $data['provider'];
			$sms_cnt		= count($sms_arr);

			for($i = 0; $i < $sms_cnt; $i++){
				###
				$name		= $sms_arr[$i];
				if( preg_match('/coupon_/',$name) && !$this->isplusfreenot ){ //무료몰은 티켓 패스
					continue;
				}

				// 알림톡 사용여부
				if ($kakaotalk_use){
					if ($msg_list[$name.'_user']){
						$v['kkotalk_use'] = $msg_list[$name.'_user']['msg_yn'];
					} else {
						$v['kkotalk_use'] = false;
					}
				}

				###
				$v['name']			= $name;
				$v['text']			= $sms_text[$i];
				$v['provider_use']	= $sms_provider[$i];
				$v['user']			= (trim($sms[$name.'_user'])) ? trim($sms[$name.'_user']) : $msg_arr[$name.'_user'];
				$v['admin']			= (trim($sms[$name.'_admin'])) ? trim($sms[$name.'_admin']) : $msg_arr[$name.'_admin'];
				$v['disabled']		= $sms_dis[$i];
				$v['user_disabled']		= $sms_user_dis[$i];
				$v['user_req']		= $user_req[$i];
				$v['arr']			= $admins_arr;
				$v['user_chk']		= $sms[$name."_user_yn"];
				$v['provider_chk']	= $sms[$name."_provider_yn"];
				if($sms_rest[$name] == "checked" && $restriction_msg) $v['rest_msg'] = $restriction_msg;
				for($j = 0; $j < $sms_info['admis_cnt']; $j++)
					$v['admins_chk'][] = $sms[$name."_admins_yn_".$j];


				$loop[$k][]		= $v;
				unset($v);
			}
		}

		## 치환코드 리스트
			$replace_item	= array();
			$replace_item[] = array("cd" => "shopName"			,"nm" => "쇼핑몰명(설정 &gt; 상점정보)");
			$replace_item[] = array("cd" => "shopDomain"		,"nm" => "쇼핑몰 도메인");
			$replace_item[] = array("cd" => "userid"			,"nm" => "회원아이디");
			$replace_item[] = array("cd" => "username"			,"nm" => "회원명(회원명 없을시 제외)");
			$replace_item[] = array("cd" => "password"			,"nm" => "회원비밀번호");
			$replace_item[] = array("cd" => "order_user"		,"nm" => "주문자명");
			$replace_item[] = array("cd" => "recipient_user"	,"nm" => "받는분");
			$replace_item[] = array("cd" => "ordno"				,"nm" => "주문번호");
			$replace_item[] = array("cd" => "orduserName"		,"nm" => "주문자명");
			$replace_item[] = array("cd" => "go_item"			,"nm" => "출고완료/배송완료 상품","etc"=>"여러 개의 상품인 경우 외 몇 건으로 표시됨<br />상품명은 치환코드 길이 설정을 따릅니다.");
			$replace_item[] = array("cd" => "ord_item"			,"nm" => "주문상품","etc"=>"여러 개의 상품인 경우 외 몇 건으로 표시됨<br />상품명은 치환코드 길이 설정을 따릅니다.");
			$replace_item[] = array("cd" => "bank_account"		,"nm" => "입금은행 계좌번호 예금주");
			$replace_item[] = array("cd" => "settleprice"		,"nm" => "입금(결제)금액");
			$replace_item[] = array("cd" => "settle_kind"		,"nm" => "결제수단 수단별확인메시지","etc"=>"<div style='color:#999999;'>
																	신용카드 예시) 카드결제 완료<br>
																	계좌이체 예시) 계좌이체 완료<br>
																	가상계좌 예시) 가상계좌 완료<br>
																	무통장 예시) OO은행 입금확인<br>
																	핸드폰 예시) 핸드폰 결제완료
																	</div>");
			$replace_item[] = array("cd" => "delivery_company"	,"nm" => "택배사명");
			$replace_item[] = array("cd" => "delivery_number"	,"nm" => "운송장번호");
			$replace_item[] = array("cd" => "coupon_serial"		,"nm" => "티켓인증코드");
			$replace_item[] = array("cd" => "couponNum"			,"nm" => "티켓발송회차");
			$replace_item[] = array("cd" => "coupon_value"		,"nm" => "티켓값어치");
			$replace_item[] = array("cd" => "options"			,"nm" => "필수옵션");
			$replace_item[] = array("cd" => "used_time"			,"nm" => "티켓사용일시");
			$replace_item[] = array("cd" => "coupon_used"		,"nm" => "티켓사용 값어치");
			$replace_item[] = array("cd" => "coupon_remain"		,"nm" => "티켓잔여 값어치");
			$replace_item[] = array("cd" => "used_location"		,"nm" => "티켓 사용처");
			$replace_item[] = array("cd" => "confirm_person"	,"nm" => "티켓사용 확인자");
			$replace_item[] = array("cd" => "goods_name"		,"nm" => "티켓상품","etc"=>"여러 개의 상품인 경우 외 몇 건으로 표시됨<br />상품명은 치환코드 길이 설정을 따릅니다.");
			$replace_item[] = array("cd" => "repay_item"		,"nm" => "취소/반품->환불완료 상품","etc"=>"여러 개의 상품인 경우 외 몇 건으로 표시됨<br />상품명은 치환코드 길이 설정을 따릅니다.");
			$replace_item[] = array("cd" => "remainSms"				,"nm" => "잔여문자");
			$replace_item[] = array("cd" => "remainAutodeposit"		,"nm" => "자동입금만료일");
			$replace_item[] = array("cd" => "remainGoodsflow"		,"nm" => "잔여택배자동");
			$replace_item[] = array("cd" => "dormancy_du_date"		,"nm" => "휴면예정일");

			$replace_item[] = array("cd" => "trader_name"				,"nm" => "거래처명");
			$replace_item[] = array("cd" => "sorder_code"				,"nm" => "발주번호");
			$replace_item[] = array("cd" => "sorder_time"				,"nm" => "발주일시");
			$replace_item[] = array("cd" => "cancel_date"				,"nm" => "취소일시");
			$replace_item[] = array("cd" => "sorder_item_cnt"				,"nm" => "발주종수");
			$replace_item[] = array("cd" => "total_ea"				,"nm" => "발주수량");
			$replace_item[] = array("cd" => "sorder_url"			,"nm" => "발주서상세URL");
			$replace_item[] = array("cd" => "deadline"			,"nm" => "배송지 등록 마감일");
			$replace_item[] = array("cd" => "inputUrl"			,"nm" => "배송지등록URL");

		
		// 치환코드
		$use_replace_code = [
			'join' => ['shopName', 'shopDomain', 'userid', 'username'], // 회원가입 시
			'withdrawal' =>  ['shopName', 'shopDomain', 'userid', 'username'], // 회원탈퇴 시
			'findid' =>  ['shopName', 'shopDomain', 'userid', 'username'], // 아이디 찾기
			'findpwd' =>  ['shopName', 'shopDomain', 'password'], // 비밀번호 찾기
			'order' =>  ['shopName', 'shopDomain', 'ordno', 'ord_item', 'bank_account', 'settleprice', 'userid', 'order_user', 'username'], // 주문접수 시
			'settle' => ['shopName', 'shopDomain', 'ordno', 'ord_item', 'settleprice', 'settle_kind', 'userid', 'order_user', 'username'], // 결제확인 시
			'deposit' =>  ['shopName', 'shopDomain', 'ordno', 'ord_item', 'bank_account', 'settleprice', 'userid', 'order_user'], // 미입금 시
			'sms_charge' =>  ['remainSms', 'shopDomain', 'shopName'], // 문자 충전 안내
			'autodeposit_charge' =>  ['remainAutodeposit', 'shopDomain', 'shopName'], // 무통장자동입금 연장 안내
			'goodsflow_charge' =>  ['remainGoodsflow', 'shopDomain', 'shopName'], // 택배자동 연장 안내
			'released' =>  ['shopName', 'shopDomain', 'ordno', 'go_item', 'delivery_company', 'delivery_number', 'userid', 'order_user', 'recipient_user', 'username'], // 출고완료 시
			'delivery' =>  ['shopName', 'shopDomain', 'ordno', 'go_item', 'userid', 'order_user', 'recipient_user', 'username'], // 배송완료 시
			'cancel' =>  ['shopName', 'shopDomain', 'repay_item', 'userid', 'order_user', 'username', 'ordno'], //결제취소→환불완료 시
			'refund' => ['shopName', 'shopDomain', 'repay_item', 'userid', 'order_user', 'username', 'ordno'], // 반품→환불완료 시
			'coupon_released' => ['shopName', 'shopDomain', 'coupon_serial', 'couponNum', 'coupon_value', 'options', 'goods_name', 'userid', 'order_user', 'recipient_user', 'username'], // 출고완료 시(티켓발송)
			'coupon_cancel' =>  ['shopName', 'shopDomain', 'coupon_serial', 'couponNum', 'goods_name', 'userid', 'order_user', 'recipient_user', 'username'], // 결제취소→환불완료 시
			'coupon_delivery' => ['shopName', 'shopDomain', 'coupon_serial', 'couponNum', 'coupon_value', 'options', 'used_time', 'coupon_used', 'coupon_remain', 'used_location', 'confirm_person', 'goods_name', 'userid', 'order_user', 'username'], //배송완료 시(티켓사용)
			'coupon_refund' => ['shopName', 'shopDomain', 'coupon_serial', 'couponNum', 'goods_name', 'userid', 'order_user', 'username'], // 반품→환불완료 시
			'dormancy' => ['userid', 'shopName', 'dormancy_du_date', 'shopDomain', 'shopName', 'username'], // 휴면회원
			'sorder_draft' => ['shopName', 'shopDomain', 'trader_name', 'sorder_code', 'sorder_time', 'sorder_item_cnt', 'total_ea', 'sorder_url'], //발주완료시
			'sorder_cancel_draft' => ['shopName', 'shopDomain', 'trader_name', 'sorder_code', 'sorder_time', 'cancel_date', 'sorder_item_cnt', 'total_ea', 'sorder_url'], //발주취소시
			'sorder_modify_draft' => ['shopName', 'shopDomain', 'trader_name', 'sorder_code', 'sorder_time', 'sorder_item_cnt', 'total_ea', 'sorder_url'], //수정발주시
			'present_receive' => ['shopName', 'order_user','recipient_user', 'deadline', 'inputUrl'], //선물수신(받는분)
			'present_cancel_order' => ['shopName', 'ordno', 'recipient_user'], //선물취소(주문자)
			'present_cancel_receive' => ['shopName', 'order_user'], //선물취소(받는분)
		];

		$this->template->assign('loop',$loop);
		$this->template->assign('add_group',$add_group);

		$this->template->assign('replace_code_loop',$replace_item);
		$this->template->assign('use_replace_code',json_encode($use_replace_code,true));

		###
		require_once $_SERVER['DOCUMENT_ROOT']."/app/libraries/SMS_send.class.php";
		$sms		= new SMS_SEND();
		$sms_chk	= $sms->sms_account;
		$auth		= config_load('master');

		## 상품명 치환코드 길이 제한
		$sms_goods_limit			= config_load('sms_goods_limit');
		$sms_goods_limit_default	= array("goods_item_use"=>"n","go_item_use"=>"n","ord_item_use"=>"n","repay_item_use"=>"n");
		if(!$sms_goods_limit){
			$sms_goods_limit = $sms_goods_limit_default;
		}
		$this->template->assign($sms_goods_limit);

		if(!$_GET['no']) $_GET['no'] = 1;

		$this->template->assign('tab1','-on');
		$this->template->assign(array('send_num'=>$send_num,'admins_arr'=>$admins_arr,'chk'=>$sms_chk,'sms_auth'=>$auth['sms_auth']));
		//$this->template->assign(array('sms_arr'=>$sms_arr,'sms_text'=>$sms_text));
		$this->template->define('top_menu',$this->skin.'/member/top_menu.html');

		// 외부판매마켓 발송제한안내
		$this->template->define('linkage_sms_mail_info',$this->skin.'/member/_linkage_sms_mail_info.html');

		$this->template->define(array('tpl'=>$file_path));
		$this->template->print_("tpl");
	}

	public function sms_restriction(){

		$this->admin_menu();
		$this->tempate_modules();
		$file_path	= $this->template_path();
		$this->load->model('membermodel');

		$restriction = $this->membermodel->get_sms_restriction();

		$this->template->assign('restriction_title',$restriction[0]);
		$this->template->assign('restriction_item',$restriction[1]);

		## 설정/예약 시간대
		$loop_reserve_time = array('10'=>'10분','20'=>'20분','30'=>'30분','60'=>'1시간','120'=>'2시간','180'=>'3시간','240'=>'4시간','300'=>'5시간');

		$loop_config_time = array();
		for($i=1; $i<=24;$i++) $loop_config_time[] = ((int)$i<10) ? "0".(int)$i:$i;

		$sms_rest = array();
		$sms_restriction = config_load('sms_restriction');
		if($_GET['mode'] == "board"){
			## 게시판 SMS 발송시간 제한 설정
			$sms_rest = $sms_restriction;
			if($_GET['first'] && !$sms_rest){
				$selected['config_time_s']['21']	= "selected";
				$selected['config_time_e']['08']	= "selected";
				$selected['reserve_time']['10']		= "selected";
			}else{
				$selected['config_time_s'][$sms_rest['board_time_s']]		= "selected";
				$selected['config_time_e'][$sms_rest['board_time_e']]		= "selected";
				$selected['reserve_time'][$sms_rest['board_reserve_time']]	= "selected";
			}
			$config_field = array("board_time_s","board_time_e","board_reserve_time");
		}else{
			## 일반 SMS 발송시간 제한 설정
			if($_GET['first'] && !$sms_restriction){
				$selected['config_time_s']['21']	= "selected";
				$selected['config_time_e']['08']	= "selected";
				$selected['reserve_time']['10']		= "selected";
			}else{
				foreach($sms_restriction as $k=>$v){
					$tmp = explode("__",$k);
					if($v == "on"){ $v = "checked"; }
					if(count($tmp) > 1){
						$tmp[0] = str_replace("admin_","",$tmp[0]);
						$sms_rest[$tmp[0]][$tmp[1]] = $v;
					}else{
						$sms_rest[$k] = $v;
					}
				}
				$selected['config_time_s'][$sms_rest['config_time_s']]	= "selected";
				$selected['config_time_e'][$sms_rest['config_time_e']]	= "selected";
				$selected['reserve_time'][$sms_rest['reserve_time']]	= "selected";
			}
			$config_field = array("config_time_s","config_time_e","reserve_time");
		}
		$this->template->assign(array('loop_config_time'=>$loop_config_time,'loop_reserve_time'=>$loop_reserve_time,'selected'=>$selected));
		$this->template->assign(array('sms_rest'=>$sms_rest,'config_field'=>$config_field));
		$this->template->define(array('tpl'=>$file_path));
		$this->template->print_("tpl");
	}

	public function default_sms_msg(){
		$msg_arr			= parse_ini_file(APPPATH."config/_default_sms_msg.ini", true);
		$result['user']		= $msg_arr[$_GET['type'].'_user'] ? $msg_arr[$_GET['type'].'_user'] : '';
		$result['admin']	= $msg_arr[$_GET['type'].'_admin'] ? $msg_arr[$_GET['type'].'_admin'] : '';

		echo json_encode($result);
	}

	public function sms_charge()
	{
		$auth = $this->authmodel->manager_limit_act('member_send');
		if(!$auth){
			pageBack("권한이 없습니다.");
			exit;
		}

		$this->admin_menu();
		$this->tempate_modules();
		$file_path	= $this->template_path();

		include_once $_SERVER['DOCUMENT_ROOT']."/app/libraries/sms.class.php";
		$auth = config_load('master');
		$sms_id = $this->config_system['service']['sms_id'];
		$sms_api_key = $auth['sms_auth'];

		//$sms_send	= new SMS_SEND();
		$gabiaSmsApi = new gabiaSmsApi($sms_id,$sms_api_key);

		$params	= "sms_id=" . $sms_id . '&sms_pw=' . md5($sms_id);
		$params = makeEncriptParam($params);
		$limit	= $gabiaSmsApi->getSmsCount();
		$sms_chk = $sms_id;

		$auth_send	= $this->authmodel->manager_limit_act('member_send');
		if(isset($auth_send)) $this->template->assign('auth_send',$auth_send);

		if($_GET['sc_gb'] == "PERSONAL"){
			$this->template->assign('tab5','-on');
		}else{
			$this->template->assign('tab2','-on');
		}
		$this->template->assign(array('count'=>$limit,'param'=>$params,'chk'=>$sms_chk));
		$this->template->define('top_menu',$this->skin.'/member/top_menu.html');
		$this->template->define(array('tpl'=>$file_path));
		$this->template->print_("tpl");
	}

	public function sms_payment(){
		$this->admin_menu();
		$this->tempate_modules();
		$file_path	= $this->template_path();
		$mall_id	= isset($mall_id) ? $mall_id : $this->config_system['service']['cid'];
		$domain		= isset($this->config_system['subDomain']) ? $this->config_system['subDomain'] : $_SERVER['SERVER_NAME'];
		$arr_domain	= explode(".", $domain);
		if ($arr_domain[0]=="www") {
			$domain = substr($domain, 4);
		}
		$sms_call = "req_domain=".$domain."&req_mallid=".$mall_id."&req_type=SMS&req_url=/myhg/mylist/spec/firstmall/sms/index.php";
		$sms_call = makeEncriptParam($sms_call);

		$this->template->assign('param',$sms_call);
		$this->template->define(array('tpl'=>$file_path));
		$this->template->print_("tpl");
	}

	public function sms_history()
	{
		$auth = $this->authmodel->manager_limit_act('member_send');
		if(!$auth){
			pageBack("권한이 없습니다.");
			exit;
		}

		$this->admin_menu();
		$this->tempate_modules();
		$file_path	= $this->template_path();

		$aParams = $this->input->get();

		if($aParams['order_seq']) {
			$this->load->model('ordermodel');
			$data = $this->ordermodel->get_order_info($aParams['order_seq']);

			$aParams['tran_phone'] = str_replace('-','',$data['order_cellphone']);
		}

		$config_system = $this->config_system['service'];

		$sms_id=$this->config_system['service']['sms_id'];

		$sms_api_key=$this->config_system['service']['sms_api_key'];

		if($sms_api_key){
			$sms_chk = "true";
		}else{
			$sms_chk = "false";
		}

		$auth_send	= $this->authmodel->manager_limit_act('member_send');
		if(isset($auth_send)) $this->template->assign('auth_send',$auth_send);
		###
		$this->template->assign('tran_phone',$aParams['tran_phone']);
		$this->template->assign('tab3','-on');
		$this->template->assign(array('sms_id'=>$sms_id,'chk'=>$sms_chk,'sms_auth'=>$auth['sms_auth']));
		$this->template->define('top_menu',$this->skin.'/member/top_menu.html');
		$this->template->define(array('tpl'=>$file_path));
		$this->template->print_("tpl");
	}

	public function sms_history_list()
	{
		$auth = $this->authmodel->manager_limit_act('member_send');
		if(!$auth){
			pageBack("권한이 없습니다.");
			exit;
		}
		$this->tempate_modules();
		$file_path	= $this->template_path();

		require_once ROOTPATH."/app/libraries/sms.class.php";
		$auth 			= config_load('master');

		$sms_id 		= $this->config_system['service']['sms_id'];
		$sms_api_key 	= $auth['sms_auth'];
		$gabiaSmsApi 	= new gabiaSmsApi($sms_id, $sms_api_key);
		$aGetParams 	= $this->input->get();

        $this->config->load("searchFormSet");
        $get_search_info	= $this->config->item("sms_history");
		$scDefault 			= $get_search_info['searchValue'];			// 검색기본설정값 가져오기
		$aGetParams 		= array_merge($scDefault, $aGetParams);

		$date_preset 		= $this->config->item('date_preset');		// 조회기간

		switch ($aGetParams['search_type']) {
			case 'tran_msg' :
				$tran_msg = $aGetParams['keyword'];
				break;
			case 'tran_phone' :
				$tran_phone = $aGetParams['keyword'];
				break;
			case 'tran_callback' :
				$tran_callback = $aGetParams['keyword'];
				break;
		}

		if($aGetParams['select_date_regist']) {
			$aGetParams['sdate'] 		= $date_preset[$aGetParams['select_date_regist']][0];
			$aGetParams['edate'] 		= $date_preset[$aGetParams['select_date_regist']][1];
		}

		$aGetParams['defaultSdate'] = $date_preset[$scDefault['select_date_regist']][0];
		$aGetParams['defaultEdate'] = $date_preset[$scDefault['select_date_regist']][1];

		
		if($aGetParams['sdate'] < date('Y-m-d', strtotime($aGetParams['edate'] . ' -3 month'))){
			pageBack("최대 검색기간은 3개월입니다.");
			exit;
		}
		if($aGetParams['sdate'] < date('Y-m-d', strtotime('-1 year'))){
			pageBack("최근 1년 이내만 검색가능합니다.");
			exit;
		}

		$per_page 	= ($aGetParams['perpage'])? $aGetParams['perpage']: 20;

		if ($aGetParams['page'] > 1) {
			$page = ceil($aGetParams['page'] / $per_page) + 1;
		}else{
			$page = 1;
		}
		$params = array(
			'page' 			=> $page,
			'per_page' 		=> $per_page,
			's_date' 		=> $aGetParams['sdate'],
			'e_date' 		=> $aGetParams['edate'],
			'tran_phone' 	=> $tran_phone,
			'tran_callback' => $tran_callback,
			'tran_kind' 	=> $aGetParams['tran_kind'],
			'tran_rslt' 	=> $aGetParams['tran_rslt'],
			'tran_msg' 		=> $tran_msg
		);
		$result 						= $gabiaSmsApi->history($params);
		$result['sdate'] 				= $aGetParams['sdate'];
		$result['edate'] 				= $aGetParams['edate'];
		$result['defaultSearchType']	= $scDefault['search_type'];
		$result['defaultSelectDate']	= $scDefault['select_date_regist'];
		$result['defaultSdate'] 		= $aGetParams['defaultSdate'];
		$result['defaultEdate'] 		= $aGetParams['defaultEdate'];
		$result['select_date_regist'] 	= $aGetParams['select_date_regist'];
		$result['tran_kind'] 			= $aGetParams['tran_kind'];
		$result['tran_rslt'] 			= $aGetParams['tran_rslt'];
		$result['search_type'] 			= $aGetParams['search_type'];
		$result['keyword'] 				= $aGetParams['keyword'];
		$result['page'] 				= $aGetParams['page'];
		$result['paginlay'] 			= pagingtag($result['total'], $per_page, '?');

		$this->template->assign($result);
		$this->template->define(array('tpl'=>$file_path));
		$this->template->print_("tpl");
	}

	public function sms_auth()
	{
		$this->admin_menu();
		$this->tempate_modules();
		$file_path	= $this->template_path();

		$auth = $this->authmodel->manager_limit_act('member_send');
		if(!$auth){
			pageBack("권한이 없습니다.");
			exit;
		}

		require_once $_SERVER['DOCUMENT_ROOT']."/app/libraries/sms.class.php";

		$auth = config_load('master');
		$sms_id = $this->config_system['service']['sms_id'];
		$sms_api_key = $auth['sms_auth'];

		//$sms_send	= new SMS_SEND();
		$gabiaSmsApi = new gabiaSmsApi($sms_id,$sms_api_key);

		if($_GET['sc_gb'] == "PERSONAL"){
			$this->template->assign('tab6','-on');
		}else{
			$this->template->assign('tab4','-on');
		}

		$auth_send	= $this->authmodel->manager_limit_act('member_send');
		if(isset($auth_send)) $this->template->assign('auth_send',$auth_send);

		$sms_info = config_load('sms_info');
		if($sms_info['send_num']) $send_num = explode("-",$sms_info['send_num']);
		if($sms_info['admis_cnt']>0){
			for($i=0;$i<$sms_info['admis_cnt'];$i++){
				$id = "admins_num_".$i;
				$v['number'] = explode("-",$sms_info[$id]);
				$admins_arr[] = $v;
			}
		}

		$send_phone = getSmsSendInfo();
		if(isset($send_phone)) $this->template->assign('send_phone',$send_phone);

		$this->template->assign('tab1','-on');
		$this->template->assign(array('send_num'=>$send_num,'admins_arr'=>$admins_arr,'chk'=>$sms_chk,'sms_auth'=>$auth['sms_auth']));

		$this->template->assign(array('sms_id'=>$sms_id,'sms_auth'=>$auth['sms_auth'],'auth_date'=>$auth['auth_date']));
		$this->template->define('top_menu',$this->skin.'/member/top_menu.html');
		$this->template->define(array('tpl'=>$file_path));
		$this->template->print_("tpl");
	}

	### 이메일발송관리
	public function email()
	{
		$auth = $this->authmodel->manager_limit_act('member_send');
		if(!$auth){
			pageBack("권한이 없습니다.");
			exit;
		}

		$this->admin_menu();
		$this->tempate_modules();
		$file_path	= $this->template_path();

		if(!$this->scm_cfg)	$this->scm_cfg	= config_load('scm');

		$auth_promotion = $this->authmodel->manager_limit_act('member_promotion');
		if(isset($auth_promotion)) $this->template->assign('auth_promotion',$auth_promotion);
		$auth_send	= $this->authmodel->manager_limit_act('member_send');
		if(isset($auth_send)) $this->template->assign('auth_send',$auth_send);

		$this->config->load('emailGroup');
		$group_name = $this->config->item('group_name');
		$email_group = $this->config->item('email_group');

		$email = config_load('email');
		foreach ($email_group as $key => $group){
			foreach($group as $data) {
				###
				if( preg_match('/coupon_/',$data['name']) && !$this->isplusfreenot ){ //무료몰인 티켓상품 메일은 패스
					continue;
				}
				if( $this->scm_cfg['use'] != 'Y' && $key == "scm" ){ //무료몰인 티켓상품 메일은 패스
					continue;
				}
				$v = array();
				$v['name']		= $data['name'];
				$v['text']		= $data['title'];

				// 사용유무
				$v['user_use']	= true;
				$v['admin_use']	= true;
				$v['order_use']	= true;

				$user_chk	= 'N';
				$admin_chk	= 'N';
				###

				if(isset($email[$v['name']."_user_yn"]))
					$user_chk	= $email[$v['name']."_user_yn"];
				if(isset($email[$v['name']."_admin_yn"]))
					$admin_chk	= $email[$v['name']."_admin_yn"];

				# 거래처 여부에 따라 고객/거래처 발송
				if($key=="scm") {
					$v['user_use'] = false;
				} else {
					$v['order_use'] = false;
				}

				# 티켓발송상품 고객에게 무조건 발송
				if(in_array($v['name'], array("coupon_released","coupon_delivery"))) {
					$v['user_chk'] = "Y";
				}

				if($user_chk == "Y") $v['user_chk']="checked='checked'";
				if($admin_chk == "Y") $v['admin_chk']="checked='checked'";

				# 특정 이메일 관리자 미발송
				if(in_array($v['name'], array("findid","findpwd","marketing_agree","marketing_agree_status"))) {
					$v['admin_use'] = false;
				}

				$loop[$key]['list'][]		= $v;
			}
		}
		$this->template->assign('scm_cfg', $this->scm_cfg);
		$this->template->assign('group_name',$group_name);
		$this->template->assign('perline',$perline);

		$this->template->assign('loop',$loop);
		$this->template->define(array('tpl'=>$file_path));

		// 외부판매마켓 발송제한안내
		$this->template->define('linkage_sms_mail_info',$this->skin.'/member/_linkage_sms_mail_info.html');

		$this->template->print_("tpl");
	}

	public function email_history()
	{
		ini_set("memory_limit",-1);
		set_time_limit(0);

		$auth = $this->authmodel->manager_limit_act('member_send');
		if(!$auth){
			pageBack("권한이 없습니다.");
			exit;
		}

		$this->admin_menu();
		$this->tempate_modules();
		$file_path	= $this->template_path();

		###
		$sc = $_GET;
		$sc['orderby']			= (isset($_GET['orderby'])) ?	$_GET['orderby']:'seq';
		$sc['sort']				= (isset($_GET['sort'])) ?		$_GET['sort']:'desc';
		$sc['page']				= (isset($_GET['page'])) ?		intval($_GET['page']):'0';
		$sc['perpage']			= (isset($_GET['perpage'])) ?	intval($_GET['perpage']):'10';
		$sc['sc_gb']			= strtoupper($_GET['sc_gb']);

		###
		$this->load->model('membermodel');
		$data = $this->membermodel->email_history_list($sc);

		$sc['searchcount']	 = $data['count'];
		$sc['total_page']	 = ceil($sc['searchcount']	 / $sc['perpage']);
		$sc['totalcount']	 = $data['totalcount'];
		$idx = 0;
		foreach($data['result'] as $datarow){
			$idx++;
			$datarow['number']	= $sc['searchcount']	 - ( ($sc['page'] -1 ) * 1 + $idx + 1) + 1;
			$dataloop[] = $datarow;
		}

		###
		if(isset($data)) $this->template->assign('loop',$dataloop);
		$paginlay = pagingtag($sc['searchcount'],$sc['perpage'],$this->membermodel->admin_member_url($file_path).'?', getLinkFilter('',array_keys($sc)) );
		$this->template->assign('sc_gb',$sc['sc_gb']);
		$this->template->assign('pagin',$paginlay);
        $this->template->assign('searchcount',$sc['searchcount']);

		$this->template->assign('perpage',$sc['perpage']);
		$this->template->assign('sc',$sc);

		$this->template->define(array('tpl'=>$file_path));
		$this->template->print_("tpl");
	}

	public function email_contents_modify_pop()
	{
		$auth = $this->authmodel->manager_limit_act('member_send');
		if(!$auth){
			pageBack("권한이 없습니다.");
			exit;
		}

		$this->admin_menu();
		$this->tempate_modules();
		$file_path	= $this->template_path();

		$mode			= $_GET['mode'];
		$email			= config_load('email');
		$admin_email	= $email[$mode."_admin_email"];

		$basic = ($this->config_basic)?$this->config_basic:config_load('basic');
		$this->template->assign('email',$basic['companyEmail']);
		if(!$admin_email) $admin_email = $basic['companyEmail'];

		$this->template->assign('mode', $mode);
		$this->template->assign('admin_email', $admin_email);
		$this->template->define(array('tpl'=>$file_path));
		$this->template->print_("tpl");
	}

	### 이메일대량발송
	public function amail()
	{
		$auth = $this->authmodel->manager_limit_act('member_send');
		if(!$auth){
			pageBack("권한이 없습니다.");
			exit;
		}

		$this->admin_menu();
		$this->tempate_modules();
		$file_path	= $this->template_path();

		###
		$email_mass = config_load('email_mass');
		$email_mass['phoneArr']		= explode("-",$email_mass['phone']);
		$email_mass['mobileArr']	= explode("-",$email_mass['cellphone']);

		$this->template->assign($email_mass);
		$this->template->define(array('tpl'=>$file_path));
		$this->template->print_("tpl");
	}

	public function amail_send()
	{
		$auth = $this->authmodel->manager_limit_act('member_send');
		if(!$auth){
			pageBack("관리자 권한이 없습니다.");
			exit;
		}
		$this->load->model('snsmember');
		$this->load->model('membermodel');
		$this->load->model('providermodel');
		$this->admin_menu();
		$this->tempate_modules();
		$file_path	= $this->template_path();

		$provider	= $this->providermodel->provider_goods_list();
		$this->template->assign('provider',$provider);

		// 개인 정보 조회 로그
		// $type,$manager_seq,$type_seq
		$this->load->model('logPersonalInformation');
		$this->logPersonalInformation->insert('memberlist',$this->managerInfo['manager_seq'],'');

		for ($m=1;$m<=12;$m++){	$m_arr[] = str_pad($m, 2, '0', STR_PAD_LEFT); }
		for ($d=1;$d<=31;$d++){	$d_arr[] = str_pad($d, 2, '0', STR_PAD_LEFT); }
		$this->template->assign('m_arr',$m_arr);
		$this->template->assign('d_arr',$d_arr);

		#### AUTH
		$auth_arr		= array();
		$auth_act		= $this->authmodel->manager_limit_act('member_act');
		if(isset($auth_act)) $auth_arr['auth_act'] = $auth_act;
		$auth_promotion = $this->authmodel->manager_limit_act('member_promotion');
		if(isset($auth_promotion)) $auth_arr['auth_promotion'] = $auth_promotion;
		$auth_send	= $this->authmodel->manager_limit_act('member_send');
		if(isset($auth_send)) $auth_arr['auth_send'] = $auth_send;

		// 회원정보다운로드 체크
		$auth_member_down	= $this->authmodel->manager_limit_act('member_download');
		if( !$this->isplusfreenot ){ //무료몰인경우 다운권한 없음
			$auth_member_down = false;
		}
		if(isset($auth_member_down)) $auth_arr['auth_member_down'] = $auth_member_down;
		$this->template->assign('auth_arr',json_encode($auth_arr));

		###
		$cid = preg_replace("/gabia-/","", $this->config_system['service']["cid"]);
		$email_mass = config_load('email_mass');
		$email_mass['cid']			= $cid;
		$email_mass['name']			= urlencode($email_mass['name']);
		$email_mass['phoneArr']		= explode("-",$email_mass['phone']);
		$email_mass['mobileArr']	= explode("-",$email_mass['cellphone']);
		$email_mass['server_name']	= $_SERVER["SERVER_NAME"];
		$email_mass['vertify_cd']	= $this->config_system['shopSno'];
		$this->template->assign('mass',$email_mass);

		###
		$basic = ($this->config_basic)?$this->config_basic:config_load('basic');
		$cntquery = $this->db->query("select count(*) as cnt from fm_member where status in ('done','hold','dormancy')");
		$cntrow = $cntquery->result_array();
		$mInfo['total'] = $cntrow[0]['cnt'];
		$this->template->assign('mInfo',$mInfo);

		### GROUP
		$group_arr = $this->membermodel->find_group_list();
		$search_type_arr = array(
			'user_name' => '이름',
			'userid' => '아이디',
			'email' => '이메일',
			'phone' => '전화번호(네자리)',
			'cellphone' => '핸드폰(네자리)',
			'address' => '주소',
			'nickname' => '닉네임'
		);

		$this->load->library('searchsetting');
		$_default 						= array('orderby'=>'member_seq','sort'=>'desc','page'=>0,'perpage'=>10);
		$scRes 							= $this->searchsetting->pagesearchforminfo("member_catalog",$_default);
		$sc_form 						= $scRes['form'];
		$sc_datePreset					= $scRes['date_preset'];
		$this->template->assign('sc_form',$sc_form);

		unset($scRes['form']);

		$sc					= $scRes;
		$sc['selected']['search_type'][$sc['search_type']]	= "selected";
		$sc['selected']['sc_day_type'][$sc['sc_day_type']]	= "selected";
		$sc['selected']['lastlogin_search_type'][$sc['lastlogin_search_type']]	= "selected";
		$sc['selected']['grade'][$sc['grade']]	= "selected";

		$sc['checkbox']['business_seq'][$sc['business_seq']]	= "checked";
		$sc['checkbox']['status'][$sc['status']]	= "checked";
		$sc['checkbox']['sitetype'][$sc['sitetype']]	= "checked";
		$sc['checkbox']['snsrute'][$sc['snsrute']]	= "checked";

		if($sc_datePreset[$scRes['select_date_regist']] && empty($sc['regist_sdate']) && empty($sc['regist_edate'])) {
			$sc['regist_sdate'] = $sc_datePreset[$scRes['select_date_regist']][0];
			$sc['regist_edate'] = $sc_datePreset[$scRes['select_date_regist']][1];
		}
		if($sc_datePreset[$scRes['select_date_birthday']] && empty($sc['birthday_sdate']) && empty($sc['birthday_edate'])) {
			$sc['birthday_sdate'] = $sc_datePreset[$scRes['select_date_birthday']][0];
			$sc['birthday_edate'] = $sc_datePreset[$scRes['select_date_birthday']][1];
		}
		if($sc_datePreset[$scRes['select_date_anniversary']] && empty($sc['anniversary_sdate'][0]) && empty($sc['anniversary_edate'][0])) {
			$sc['anniversary_sdate'][0] = substr($sc_datePreset[$scRes['select_date_anniversary']][0],5,2);
			$sc['anniversary_sdate'][1] = substr($sc_datePreset[$scRes['select_date_anniversary']][0],8,2);
			$sc['anniversary_edate'][0] = substr($sc_datePreset[$scRes['select_date_anniversary']][1],5,2);
			$sc['anniversary_edate'][1] = substr($sc_datePreset[$scRes['select_date_anniversary']][1],8,2);
		}
		if(!$sc['searchflag']) $sc['mailing'] = 'y';

		### MEMBER
		$data = $this->membermodel->admin_member_list_spout($sc); //프로세스 변경 kmj

		### PAGE & DATA
		$sc['searchcount']	 = $data['count'];
		$sc['total_page']	 = ceil($sc['searchcount']	 / $sc['perpage']);
		$cntquery = $this->db->query("select count(*) as cnt from fm_member where status in ('done','hold','dormancy')");
		$cntrow = $cntquery->result_array();
		$sc['totalcount'] = $cntrow[0]['cnt'];

		$idx = 0;
		$this->load->model('Goodsreview','Boardmodel');//리뷰건
		foreach($data['result'] as $datarow){
			$idx++;
			$datarow['number']	= $sc['searchcount']	 - ( ($sc['page'] -1 ) * 1 + $idx + 1) + 1;

			//기업회원 정보 매칭 kmj
			if($datarow['mtype'] == 'business'){
				$datarow['type']	= '기업';

				$bus_info = $this->db->query("seLECT
						business_seq, bname, bcellphone, bphone
					fROM
						fm_member_business
					wHERE
						member_seq = ? limit 0, 1", $datarow['member_seq'])->result_array();

				if($bus_info[0]){
					$datarow['business_seq']	= $bus_info[0]['business_seq'];
					$datarow['user_name']		= $bus_info[0]['bname'];
					$datarow['cellphone']		= $bus_info[0]['bcellphone'];
					$datarow['phone']			= $bus_info[0]['bphone'];
				} else {
					$datarow['business_seq']	= '';
					$datarow['user_name']		= '';
					$datarow['cellphone']		= '';
					$datarow['phone']			= '';
				}
			} else {
				$datarow['type']	= '개인';
			}

			//그룹 정보 매칭 kmj
			$group_info = $this->db->query("seLECT
						group_name
					fROM
						fm_member_group
					wHERE
						group_seq = ? limit 0, 1", $datarow['group_seq'])->result_array();
			if($group_info[0]){
				$datarow['group_name'] = $group_info[0]['group_name'];
			} else {
				$datarow['group_name'] = '';
			}

			//유입 정보 매칭 kmj
			if(!$datarow['referer_domain']){
				$datarow['referer_name'] = '직접입력';
			} else {
				$referer_info = $this->db->query("seLECT
							referer_group_name
						fROM
							fm_referer_group
						wHERE
							referer_group_url = ? limit 0, 1", $datarow['referer_domain'])->result_array();
				if($referer_info[0]){
					$datarow['referer_name'] = $referer_info[0]['referer_group_name'];
				} else {
					$datarow['referer_name'] = '기타';
				}
			}

			//리뷰건
			$sc['whereis'] = ' and mseq <> \'\' and mseq='.$datarow['member_seq'];
			$sc['select'] = ' count(gid) as cnt ';
			$gdreviewquery = $this->Boardmodel->get_data($sc);
			$datarow['gdreview_sum'] = $gdreviewquery['cnt'];

			if($datarow['rute'] != "none" ) {
				$snsmbsc['select'] = ' * ';
				$snsmbsc['whereis'] = ' and member_seq = \''.$datarow['member_seq'].'\' ';
				$snslist = $this->snsmember->snsmb_list($snsmbsc);
				if($snslist['result'][0]) $datarow['snslist'] = $snslist['result'];
			}

			$datarow['regist_date'] 	= date("Y-m-d H:i", strtotime($datarow['regist_date']));
			$datarow['lastlogin_date'] 	= date("Y-m-d H:i", strtotime($datarow['lastlogin_date']));

			/****/

			$dataloop[] = $datarow;
		}

		## 유입경로 그룹
		$this->load->model('statsmodel');
		$referer_list	= $this->statsmodel->get_referer_grouplist();
		$this->template->assign('referer_list',$referer_list);

		###
		if(isset($data)) $this->template->assign('loop',$dataloop);
		$paginlay = pagingtag($sc['searchcount'],$sc['perpage'],$this->membermodel->admin_member_url($file_path).'?', getLinkFilter('',array_keys($sc)) );
		if(empty($paginlay))$paginlay = '<p><a class="on red">1</a><p>';

		//가입환경
		$sitetypeloop = sitetype($_GET['sitetype'], 'image', 'array');
		$this->template->assign('sitetypeloop',$sitetypeloop);

		//가입양식
		$ruteloop = memberrute($_GET['rute'], 'image', 'array' , 'search');
		$this->template->assign('ruteloop',$ruteloop);

		$this->template->assign('pagin',$paginlay);
		$this->template->assign('group_arr',$group_arr);
		$this->template->assign('search_type_arr',$search_type_arr);
		$this->template->assign('perpage',$sc['perpage']);
		$this->template->assign(array('sc'=>$sc,'scObj'=>json_encode($sc)));
		$this->template->assign('amail','Y');

		$this->template->assign('query_string',get_query_string());

		$reserve = config_load('reserve');
		$this->template->assign('reserveinfo',$reserve);

		//$this->template->assign('pageType','search');

		$point_use_button = "pointNotForm";
		if($reserve['point_use']=="Y") $point_use_button = "batchForm";
		$this->template->assign('point_use_button',$point_use_button);

		$this->template->define('member_list',$this->skin.'/member/member_list.html');
		$this->template->define('member_search',$this->skin.'/member/member_search.html');
		$this->template->define(array('tpl'=>$file_path));
		$this->template->print_("tpl");

		###
		if(!$email_mass['name'] && !$email_mass['email']){
			$callback = "<script>openDialog('이메일 대량 발송 설정','amail_chk',{'width':'300','height':'120'});</script>";
			echo $callback;
			exit;
		}
	}

	public function sms_form()
	{
		$this->admin_menu();
		$this->tempate_modules();
		$file_path	= $this->template_path();

		$table = !empty($_GET['table']) ? $_GET['table'] : 'fm_member';
		$this->template->assign('table',$table);

		###
		if($table=='fm_goods_restock_notify'){
			$mInfo['total'] = get_rows('fm_goods_restock_notify',array('notify_status'=>'none'));
			$action = "../goods_process/restock_notify_send_sms";
			$this->template->assign('action',$action);

			$this->template->assign('send_message',"[{$this->config_basic['shopName']}] 고객님께서 알림요청하신 상품({상품고유값},{상품명},{옵션})이 재입고되었습니다.");
		}else{
			$mInfo['total'] = get_rows('fm_member',array('status !='=>'withdrawal'));
			$action = "../member_process/send_sms";
			$this->template->assign('action',$action);
		}
		$specialArr	= array('＃', '＆', '＊', '＠', '§', '※', '☆', '★', '○', '●', '◎', '◇', '◆', '□', '■', '△', '▲', '▽', '▼', '→', '←', '↑', '↓', '↔', '〓', '◁', '◀', '▷', '▶', '♤', '♠', '♡', '♥', '♧', '♣', '⊙', '◈', '▣', '◐', '◑', '▒', '▤', '▥', '▨', '▧', '▦', '▩', '♨', '☏', '☎', '☜', '☞', '¶', '†', '‡', '↕', '↗', '↙', '↖', '↘', '♭', '♩', '♪', '♬', '㉿', '㈜', '№', '㏇', '™', '㏂', '㏘', '℡', '?', 'ª', 'º');
		$basic = ($this->config_basic)?$this->config_basic:config_load('basic');

		$sms_info = config_load('sms_info','send_num');
		if($sms_info['send_num']) $send_num = $sms_info['send_num'];

		###
		$sql = "select count(seq) as total, category from fm_sms_album group by category";
		$query = $this->db->query($sql);
		$sms_data = $query->result_array();
		$sms_total = get_rows('fm_sms_album');
		array_push($sms_data,array('total'=>$sms_total,'category'=>'전체보기'));
		rsort($sms_data);
		###
		require_once $_SERVER['DOCUMENT_ROOT']."/app/libraries/SMS_send.class.php";
		$sms	= new SMS_SEND();
		$params	= "sms_id=" . $sms->sms_account . '&sms_pw=' . $sms->sms_password;
		$params = makeEncriptParam($params);
		$limit	= ($sms->limit != -1) ? number_format($sms->limit) : 0;
		$sms_chk = $sms->sms_account;

		$this->template->assign('count',$limit);
		$this->template->assign(array('mInfo'=>$mInfo,'number'=>($send_num)? $send_num : $basic['companyPhone']));
		$this->template->assign(array('sms_loop'=>$sms_data,'sms_total'=>$sms_total,'sms_cont'=>$specialArr,'chk'=>$sms_chk));
		$this->template->define(array('tpl'=>$file_path));
		$this->template->print_("tpl");
	}

	public function emoney_form()
	{
		$this->admin_menu();
		$this->tempate_modules();
		$file_path	= $this->template_path();

		###
		$mInfo['total'] = get_rows('fm_member',array('status !='=>'withdrawal'));
		$this->template->assign('mInfo',$mInfo);

		$this->template->define(array('tpl'=>$file_path));
		$this->template->print_("tpl");
	}

	public function point_form()
	{
		$this->admin_menu();
		$this->tempate_modules();
		$file_path	= $this->template_path();

		###
		$mInfo['total'] = get_rows('fm_member',array('status !='=>'withdrawal'));
		$this->template->assign('mInfo',$mInfo);

		$this->template->define(array('tpl'=>$file_path));
		$this->template->print_("tpl");
	}

	public function email_form()
	{
		$this->admin_menu();
		$this->tempate_modules();
		$file_path	= $this->template_path();

		$basic = ($this->config_basic)?$this->config_basic:config_load('basic');

		###
		$query = $this->db->query("select * from fm_log_email order by seq desc limit 10");
		$emailData = $query->result_array();

		###
		$this->load->model('usedmodel');
		$email_chk = $this->usedmodel->hosting_check();
		$this->template->assign('email_chk',$email_chk);

		###
		$mInfo['total'] = get_rows('fm_member',array('status !='=>'withdrawal'));

		$this->template->assign(array('mail_count'=>master_mail_count(),'email'=>$basic['companyEmail']));
		$this->template->assign('mInfo',$mInfo);
		$this->template->assign('loop',$emailData);
		$this->template->define(array('tpl'=>$file_path));
		$this->template->print_("tpl");
	}
	public function delivery(){
		$file_path	= $this->template_path();
		$this->template->assign('member_seq',$_GET['member_seq']);
		$this->template->define(array('tpl'=>$file_path));
		$this->template->print_("tpl");
	}

	public function delivery_address(){
		//login_check();
		$this->admin_menu();
		$this->tempate_modules();
		$this->load->helper('shipping');
		$file_path	= $this->template_path();

		$sc=array();
		$sc['page']				= (!empty($_GET['page'])) ?		intval($_GET['page']):'1';
		$sc['perpage']			= $perpage ? $perpage : 10;
		$list_order=$_GET['order'];

		switch($list_order){
			case 'desc_up' :
				$orderby='address_description asc';
				break;
			case 'desc_dn' :
				$orderby='address_description desc';
				break;
			case 'name_up' :
				$orderby='recipient_user_name asc';
				break;
			case 'name_dn' :
				$orderby='recipient_user_name desc';
				break;
			case 'name_dn' :
				$orderby='address_seq desc';
				break;
			default :
				$orderby='address_seq desc';
				break;
		}

		$shipping = use_shipping_method();
		if( $shipping ){
			foreach($shipping as $key => $data){
				if($data) $shipping_cnt[$key] = count($data);
			}
		}
		$shipping_policy['policy'] 	= $shipping;
		$shipping_policy['count'] 	= $shipping_cnt;

		$deli_cnt = count($shipping_policy['policy']);

		$member_seq = $_GET['member_seq'];

		$tab=$_GET['tab'];
		$key = get_shop_key();

		$popup=$_GET['popup'];

		$sql="select *,
				AES_DECRYPT(UNHEX(recipient_phone), '{$key}') as recipient_phone,
				AES_DECRYPT(UNHEX(recipient_cellphone), '{$key}') as recipient_cellphone
			from fm_delivery_address ";

		if($popup == '1'){
			if($tab=='2'){
				if($deli_cnt < 2){
					$sql .= " where member_seq=".$member_seq." and lately='Y' and international ='domestic' order by ".$orderby." limit 30";
				}else{
					$sql .= " where member_seq=".$member_seq." and lately='Y' order by ".$orderby." limit 30";
				}
			}else{
				if($deli_cnt < 2){
					$sql .= " where member_seq=".$member_seq." and often='Y' and international ='domestic' order by ".$orderby." limit 30";
				}else{
					$sql .= "  where member_seq=".$member_seq." and often='Y'  order by ".$orderby." limit 30";
				}
			}
			$query = $this->db->query($sql);
			$result['record'] = $query -> result_array();
		}else{
			if($tab=='2'){
				if($deli_cnt < 2){
					$sql .= " where member_seq=".$member_seq." and lately='Y' and international ='domestic' order by ".$orderby;
				}else{
					$sql .= " where member_seq=".$member_seq." and lately='Y' order by ".$orderby;
				}
			}else{
				if($deli_cnt < 2){
					$sql .= " where member_seq=".$member_seq." and often='Y' and international ='domestic' order by ".$orderby;
				}else{

					$sql .= " where member_seq=".$member_seq." and often='Y'  order by ".$orderby;
				}
			}
			$result = select_page($sc['perpage'],$sc['page'],10,$sql,array());

		}

		foreach($result['record'] as $data){
			if($data['international'] == 'domestic'){
				$international_show = '국내';
			}elseif($data['international'] == 'international'){
				$international_show = '해외';
			}
			$data['international_show'] = $international_show;
			$loop[] = $data;
		}


		$this->template->assign('shop_shipping_policy',$cart['shop_shipping_policy']);
		$this->template->assign('shipping_policy',$shipping_policy);
		$this->template->assign('loop',$loop);
		$this->template->assign('member_seq',$member_seq);
		$this->template->assign($result);
		$this->template->define(array('tpl'=>$file_path));
		$this->template->print_("tpl");
	}

	## 사용중인 SNS 정보보기(2014-07-01)
	public function sns_detail(){

		$this->admin_menu();
		$this->tempate_modules();
		$this->load->model('snsmember');
		$file_path	= $this->template_path();
		$this->template->assign('member_seq',$_GET['member_seq']);
		$this->template->assign('snscd',$_GET['snscd']);
		$this->template->assign('no',$_GET['no']);

		$sql = "select rute,user_name,email
						,(case when sex='famale' then '여자' when sex='male' then '남자' else '' end) sex
						,(case when ifnull(birthday,'0000-00-00')!='0000-00-00' then birthday else '' end ) birthdayV
					from
						fm_membersns
					where
						member_seq ='".$_GET['member_seq']."' and rute='".$_GET['snscd'] ."'";
		$query	= $this->db->query($sql);
		$result = $query -> result_array();

		if(!$result[0]) $result[0]['message'] = "연동 해제된 계정입니다.";

		if($result[0]['rute'] == "facebook"){
			$sql	= "select sns_f from fm_member where member_seq='".$_GET['member_seq']."'";
			$query2	= $this->db->query($sql);
			$result2 = $query2->result_array();
			$result[0]['sns_f'] = $result2[0]['sns_f'];
		}

		$result[0]['rute_nm'] = $this->snsmember->snstype_name($_GET['snscd']);

		$this->template->assign('data',$result[0]);

		$this->template->define(array('tpl'=>$file_path));
		$this->template->print_("tpl");
	}

	public function replace_pop()
	{
		$this->admin_menu();
		$this->tempate_modules();
		$file_path	= $this->template_path();

		$this->load->model('membermodel');

		switch($_GET['mode']){
			case	"curation" :
				$title			= "SMS/메일";
				$replaceText	= $this->membermodel->get_replacetext('curation');
				break;

			case	"sorder_draft" :
			case	"sorder_edraft" :
			case	"sorder_cancel_draft" :
			case	"sorder_cancel_edraft" :
			case	"sorder_modify_draft" :
			case	"sorder_modify_edraft" :
				$title			= "메일";
				$replaceText	= $this->membermodel->get_replacetext_other(($_GET['mode']));
				break;

			default :
				$title			= "메일";
				$replaceText	= $this->membermodel->get_replacetext();
		}

		$this->template->assign('title', $title);
		$this->template->assign('replaceText', $replaceText);
		$this->template->define(array('tpl'=>$file_path));
		$this->template->print_("tpl");
	}

	/* 등급별 할인율 설정 */
	public function member_sale(){

		$this->load->model('membermodel');
		$list = $this->membermodel->member_sale_group_list();

		$sc = $_GET;
		$sc['orderby']			= (isset($_GET['orderby'])) ?	$_GET['orderby']:'sale_title';
		$sc['sort']				= (isset($_GET['sort'])) ?		$_GET['sort']:'asc';
		$sc['page']				= (isset($_GET['page'])) ?		intval($_GET['page']):'1';
		$sc['perpage']			= '100';


		//할인율 MASTER 정보
		$qry = "select * from fm_member_group_sale";
		$qry .=" order by {$sc['orderby']} {$sc['sort']}";
		$sale_list = select_page($sc['perpage'],$sc['page'],10,$qry,'');

		$this->template->assign('page',$sale_list['page']);

		foreach ($sale_list["record"] as $datarow){

			foreach($list as $group){

				$qry = "select * from fm_member_group_sale_detail where sale_seq = '".$datarow["sale_seq"]."' and group_seq = '".$group["group_seq"]."'";
				$query = $this->db->query($qry);
				$detail_list = $query -> result_array();

				foreach($detail_list as $subdatarow){
					if($subdatarow["sale_use"] == "Y"){
						$subdata[$group["group_seq"]]["sale_use"]				= $subdatarow["sale_limit_price"]."원 이상 구매";
					}else{
						$subdata[$group["group_seq"]]["sale_use"]				= "조건없음";
					}

					$subdata[$group["group_seq"]]["sale_price"]				= $subdatarow["sale_price"];

					if($subdatarow["sale_price_type"] == "WON"){
						$subdata[$group["group_seq"]]["sale_price_type"]		= "원 할인";
					}else{
						$subdata[$group["group_seq"]]["sale_price_type"]		= "% 할인";
					}

					$subdata[$group["group_seq"]]["sale_option_price"] 		= $subdatarow["sale_option_price"];

					if($subdatarow["sale_option_price_type"] == "WON"){
						$subdata[$group["group_seq"]]["sale_option_price_type"]		= "원 할인";
					}else{
						$subdata[$group["group_seq"]]["sale_option_price_type"]		= "% 할인";
					}

					$subdata[$group["group_seq"]]["point_use"]				= $subdatarow["point_use"];

					if($subdatarow["point_use"] == "Y"){
						$subdata[$group["group_seq"]]["point_use"]				= $subdatarow["point_limit_price"]."원 이상 구매";
					}else{
						$subdata[$group["group_seq"]]["point_use"]				= "조건없음";
					}

					$subdata[$group["group_seq"]]["point_price"]			= $subdatarow["point_price"];

					if($subdatarow["point_price_type"] == "WON"){
						$subdata[$group["group_seq"]]["point_price_type"]		= "원 적립";
					}else{
						$subdata[$group["group_seq"]]["point_price_type"]		= "% 적립";
					}


					$subdata[$group["group_seq"]]["reserve_price"]			= $subdatarow["reserve_price"];

					if($subdatarow["reserve_price_type"] == "WON"){
						$subdata[$group["group_seq"]]["reserve_price_type"]		= "원 적립";
					}else{
						$subdata[$group["group_seq"]]["reserve_price_type"]		= "% 적립";
					}

					$subdata[$group["group_seq"]]["reserve_select"]			= $subdatarow["reserve_select"];
					$subdata[$group["group_seq"]]["reserve_year"]			= $subdatarow["reserve_year"];
					$subdata[$group["group_seq"]]["reserve_direct"]			= $subdatarow["reserve_direct"];
					$subdata[$group["group_seq"]]["point_select"]			= $subdatarow["point_select"];
					$subdata[$group["group_seq"]]["point_year"]				= $subdatarow["point_year"];
					$subdata[$group["group_seq"]]["point_direct"]			= $subdatarow["point_direct"];
				}


			}

			$data[$datarow["sale_seq"]] = $subdata;
			$data[$datarow["sale_seq"]]["sale_seq"] = $datarow["sale_seq"];
			$data[$datarow["sale_seq"]]["sale_title"] = $datarow["sale_title"];
			$data[$datarow["sale_seq"]]["loop"] = $list;
			$data[$datarow["sale_seq"]]["gcount"] = count($list);
			unset($limit_goods);
			unset($limit_cate);
			###
			$sql = "SELECT
							distinct A.*, B.*
						FROM
							fm_member_group_issuegoods A
							LEFT JOIN
							(SELECT
								g.goods_seq, g.goods_name, o.price
							FROM
								fm_goods g LEFT JOIN fm_goods_option o ON g.goods_seq = o.goods_seq AND o.default_option = 'y') B ON A.goods_seq = B.goods_seq
						WHERE
							A.sale_seq = '{$datarow["sale_seq"]}'";
			$query = $this->db->query($sql);
			foreach ($query->result_array() as $row){
				$limit_goods[] = $row;
			}

			$data[$datarow["sale_seq"]]["issuegoods"] = $limit_goods;


			###
			$this->load->model('categorymodel');
			$this->db->where('sale_seq', $datarow["sale_seq"]);
			$query = $this->db->get('fm_member_group_issuecategory');
			foreach ($query->result_array() as $row){
				$row['title'] =  $this->categorymodel->get_category_name($row['category_code']);
				$limit_cate[] = $row;
			}

			$data[$datarow["sale_seq"]]["issuecategorys"] = $limit_cate;


		}



		$this->template->assign(array('data'=>$data));
		$this->template->assign(array('loop'=>$list,'gcount'=>count($list)));

		$filePath	= $this->template_path();
		$this->template->define(array('tpl'=>$filePath));
		$this->template->print_("tpl");
	}


	public function member_sale_write(){

		$this->load->model('membermodel');
		$list = $this->membermodel->member_sale_group_list();


		if($_GET["sale_seq"]){
			//일반가입 정보
			$qry = "select * from fm_member_group_sale where sale_seq = '".$_GET["sale_seq"]."'";
			$query = $this->db->query($qry);
			$sale_list = $query -> result_array();
			$this->template->assign(array('sale_title'=>$sale_list[0]["sale_title"]));
			$this->template->assign(array('defualt_yn'=>$sale_list[0]["defualt_yn"]));

			foreach ($sale_list as $datarow){

				foreach($list as $group){

					$qry = "select * from fm_member_group_sale_detail where sale_seq = '".$datarow["sale_seq"]."' and group_seq = '".$group["group_seq"]."'";
					$query = $this->db->query($qry);
					$detail_list = $query -> result_array();

					foreach($detail_list as $subdatarow){

						$subdata[$group["group_seq"]]["sale_use"]				= $subdatarow["sale_use"];
						$subdata[$group["group_seq"]]["sale_limit_price"]		= $subdatarow["sale_limit_price"];
						$subdata[$group["group_seq"]]["sale_price"]				= $subdatarow["sale_price"];

						$subdata[$group["group_seq"]]["sale_price_type"]		= $subdatarow["sale_price_type"];
						$subdata[$group["group_seq"]]["sale_option_price"] 		= $subdatarow["sale_option_price"];

						$subdata[$group["group_seq"]]["sale_option_price_type"]	= $subdatarow["sale_option_price_type"];
						$subdata[$group["group_seq"]]["point_use"]				= $subdatarow["point_use"];

						$subdata[$group["group_seq"]]["point_use"]				= $subdatarow["point_use"];
						$subdata[$group["group_seq"]]["point_limit_price"]		= $subdatarow["point_limit_price"];
						$subdata[$group["group_seq"]]["point_price"]			= $subdatarow["point_price"];

						$subdata[$group["group_seq"]]["point_price_type"]		= $subdatarow["point_price_type"];

						$subdata[$group["group_seq"]]["reserve_price"]			= $subdatarow["reserve_price"];

						$subdata[$group["group_seq"]]["reserve_price_type"]		= $subdatarow["reserve_price_type"];
						$subdata[$group["group_seq"]]["reserve_select"]			= $subdatarow["reserve_select"];
						$subdata[$group["group_seq"]]["reserve_year"]			= $subdatarow["reserve_year"];
						$subdata[$group["group_seq"]]["reserve_direct"]			= $subdatarow["reserve_direct"];
						$subdata[$group["group_seq"]]["point_select"]			= $subdatarow["point_select"];
						$subdata[$group["group_seq"]]["point_year"]				= $subdatarow["point_year"];
						$subdata[$group["group_seq"]]["point_direct"]			= $subdatarow["point_direct"];
					}


				}

				$data[$datarow["sale_seq"]] = $subdata;
				$data[$datarow["sale_seq"]]["sale_seq"] = $datarow["sale_seq"];
				$data[$datarow["sale_seq"]]["sale_title"] = $datarow["sale_title"];
				$data[$datarow["sale_seq"]]["loop"] = $list;
				$data[$datarow["sale_seq"]]["gcount"] = count($list);
				unset($limit_goods);
				unset($limit_cate);
				###
				$sql = "SELECT
								distinct A.*, B.*
							FROM
								fm_member_group_issuegoods A
								LEFT JOIN
								(SELECT
									g.goods_seq, g.goods_name, o.price
								FROM
									fm_goods g LEFT JOIN fm_goods_option o ON g.goods_seq = o.goods_seq AND o.default_option = 'y') B ON A.goods_seq = B.goods_seq
							WHERE
								A.sale_seq = '{$datarow["sale_seq"]}'";
				$query = $this->db->query($sql);
				foreach ($query->result_array() as $row){
					$limit_goods[] = $row;
				}

				$data[$datarow["sale_seq"]]["issuegoods"] = $limit_goods;


				###
				$this->load->model('categorymodel');
				$this->db->where('sale_seq', $datarow["sale_seq"]);
				$query = $this->db->get('fm_member_group_issuecategory');
				foreach ($query->result_array() as $row){
					$row['title'] =  $this->categorymodel->get_category_name($row['category_code']);
					$limit_cate[] = $row;
				}


				$data[$datarow["sale_seq"]]["issuecategorys"] = $limit_cate;


			}

		}
		$this->template->assign(array('data'=>$data));
		$this->template->assign(array('loop'=>$list,'gcount'=>count($list)));





		$filePath	= $this->template_path();
		$this->template->define(array('tpl'=>$filePath));
		$this->template->print_("tpl");
	}


	function member_sale_delete(){

		$this->tempate_modules();
		$filePath	= $this->template_path();

		$this->load->model('membermodel');

		$where_str = "sale_seq <> '".$_GET["sale_seq"]."'";
		$sale_list = $this->membermodel->get_member_sale($where_str);

		$where_str = "sale_seq = '".$_GET["sale_seq"]."'";
		$sale_title = $this->membermodel->get_member_sale($where_str, "sale_title");

		$this->template->assign(array('list'=>$sale_list));
		$this->template->assign(array('sale_title'=>$sale_title[0]["sale_title"]));

		$this->template->define(array('tpl'=>$filePath));
		$this->template->print_("tpl");

	}

	/* 엑셀 다운로드 항목설정 */
	public function download_write(){

		$this->load->model('excelmembermodel');
		$itemList 	= $this->excelmembermodel->itemList;

		$this->template->assign('itemList',$itemList);
		$requireds 	= $this->excelmembermodel->requireds;
		$this->template->assign('requireds',$requireds);

		$data = get_data("fm_exceldownload",array("gb"=>'MEMBER'));
		$item = $data ? explode("|",$data[0]['item']) : array();
		$this->template->assign('items',$item);

		$this->template->assign($data[0]);

		$file_path	= $this->template_path();
		$this->template->define(array('tpl'=>$file_path));
		$this->template->print_("tpl");
	}

	## 고객리마인드서비스 설정
	public function curation(){

		$this->admin_menu();
		$this->tempate_modules();
		$file_path	= $this->template_path();
		$this->load->helper('reservation');

		### 큐레이션 발송 구분
		$this->curation_raserevation();

		$_GET['sc_gb'] = "PERSONAL";
		## 전월 총 일수
		$month_t = date("t",strtotime(date("Y-m-d H:i:s")." -1 month"));
		$loop_day = array();
		for($i=1; $i<$month_t;$i++) $loop_day[] = ((int)$i<10) ? "0".(int)$i:$i;

		## 예약 시간대
		$loop_time = array();
		for($i=1; $i<=24;$i++) $loop_time[] = ((int)$i<10) ? "0".(int)$i:$i;

		$goodsname_length= config_load('personal_goods_limit');
		$go_item_limit	= $goodsname_length['go_item_limit'];
		$go_item_use	= $goodsname_length['go_item_use'];

		/* 짧은 url 설정에 따른 안내 문구 추가 leewh 2014-12-04 */
		$set_url = true;
		$set_string = "";
		if (empty($this->arrSns['shorturl_app_id']) && empty($this->arrSns['shorturl_app_key']) && empty($this->arrSns['shorturl_app_token'])) {
			$set_url = false;
			$set_string = "설정이 필요";
		}

		$shorturl_test	= get_connet_protocol().$this->config_system['domain'].'/personal_referer/access?inflow=shorturl&mid=1';
		list($shorturl, $shorturl_result) = get_shortURL($shorturl_test);

		if(parse_url($shorturl, PHP_URL_SCHEME)!='https' || $shorturl_result === false) {
			$shorturl = "https://bit.ly/xxxxxxxx";
			if ($set_url) {
				$set_string = "제대로 설정되지 않았습니다. ‘설정’ 을 확인해 주세요";
			}
		}

		$goodsname_length_limit = 20;
		$this->template->assign('tab1','-on');
		$this->template->assign('go_item_limit',$go_item_limit);
		$this->template->assign('go_item_use',$go_item_use);
		$this->template->assign(array('sns'=>$this->arrSns));
		$this->template->assign('shorturl_test',$shorturl_test);
		$this->template->assign('shorturl',$shorturl);
		$this->template->assign('loop_day',$loop_day);
		$this->template->assign('set_string',$set_string);
		$this->template->define(array('tpl'=>$file_path,'top_menu'=>$this->skin.'/member/top_menu.html','shorturl_setting'=>$this->skin."/setting/snsconf_shorturl_setting.html"));
		$this->template->print_("tpl");
	}

	## 고객 리마인드 서비스 세팅값 불러오기
	public function curation_raserevation(){

		$this->load->helper('reservation');
		$this->load->model('kakaotalkmodel');

		### 큐레이션 발송 구분
		$loop	= curation_menu();

		$id				= $_GET['id'];
		$personal_use	= config_load('personal_use');
		$email_config	= config_load('email_personal');
		$sms_config		= config_load('sms_personal');

		$user_yn		= $selected = array();

		## 전월 총 일수
		$month_t	= date("t",strtotime(date("Y-m-d H:i:s")." -1 month"));
		$loop_day	= array();
		for($i=0; $i<$month_t;$i++) {
			$d = $i+1;
			$loop_day[] = ((int)$d<10) ? "0".(int)$d:$d;
		}

		## 예약 시간대
		$loop_time = array();
		for($i=8; $i<=22;$i++) $loop_time[] = ((int)$i<10) ? "0".(int)$i:$i;

		$loop_delivconfirm_day	= array('1','2','3','4','5','6','7','8','9','10','12','15','20');

		foreach($loop as &$data) {
			$name = $data['name'];
			$loop_curation_day = $loop_day;
			$loop_curation_time = $loop_time;

			$data['personal_use'][$personal_use[$name."_use"]] 		= "selected='selected'";
			if(strtoupper($email_config[$name."_user_yn"]) == 'Y') {
				$data['personal_email'] 		= "checked='checked'";
			}
			$data['reserve_day']			= trim($sms_config[$name."_day"]);
			$data['reserve_time']			= trim($sms_config[$name."_time"]);
			$data['reserve_email']			= $email_config[$name."_user_yn"];

			if($name=='personal_coupon') {
				$data['not_use_day'] = 'hide';
			} else if($name=='personal_timesale'){
				$loop_curation_day		= array('lastday'=>'단독 상품 이벤트 마지막날','before'=>'단독 상품 이벤트 종료 하루 전');
			} else if($name=='personal_membership'){
				$loop_curation_day		= array('1','3','5','7','10','15','20','30');
			} else if($name=='personal_cart'){
				$loop_curation_day		= array_splice($loop_curation_day, 0, 14);
			} else if($name=='personal_review'){
				$loop_curation_day		= array('1','2','3','4','5','6','7','8','9','10','12','15','20');
			} else if($name=='personal_birthday' || $name=='personal_anniversary'){
				## 생일/기념일 안내일
				$loop_curation_day	= array();
				for($i=1; $i<31;$i++) $loop_curation_day[] = ((int)$i<10) ? "0".(int)$i:$i;
			}

			$data['loop_time'] 				= $loop_curation_time;
			$data['loop_day'] 				= $loop_curation_day;

			$data['curation_time_text']		= $curation_time[$name];

			$scParams['msg_code'] = $data['name'];
			$msg_list = $this->kakaotalkmodel->get_msg_code($scParams);
			if($msg_list) {
				$data['personal_talk'] = '발송안함';
				if ($msg_list[$data['name']]['msg_yn'] == 'Y')	$data['personal_talk'] = '발송';
			}
		}

		foreach($sms_config as $key => $val) {
			if(preg_match('/_day/', $key)) {
				$name = str_replace('_day','',$key);
				$selected_day[$name][$val] = "selected='selected'";
			}
			if(preg_match('/_time/', $key)) {
				$name = $key=="personal_timesale_time"? "personal_timesale" : str_replace('_time','',$key);
				$selected_time[$name][$val] = "selected='selected'";
			}
		}

		$this->template->assign('loop',$loop);
		$this->template->assign('selected_day',$selected_day);
		$this->template->assign('selected_time',$selected_time);
	}

	function curation_contents_modify_pop(){
		$this->admin_menu();
		$this->tempate_modules();
		$filePath	= $this->template_path();

		$this->template->assign('mode',$this->input->get('mode'));

		$this->template->define(array('tpl'=>$filePath));
		$this->template->print_("tpl");
	}
	## 리마인드 SMS발송내역
	public function  curation_history_sms(){

		$this->admin_menu();
		$this->tempate_modules();
		$file_path	= $this->template_path();
		$this->load->helper('reservation_helper');
		$this->load->model('membermodel');

		$curation_menu = curation_menu();
		foreach($curation_menu as $v){
			$loop = array();
			$tmp	= explode("_",$v['name']);
			$loop['name'] = $tmp[1];
			$loop['title'] = $v['title'];

			$curationmn[] = $loop;
		}

		$_GET['sc_gb'] = "PERSONAL";

		$sc = $_GET;
		$sc['orderby']			= (isset($_GET['orderby'])) ?	$_GET['orderby']:'seq';
		$sc['sort']				= (isset($_GET['sort'])) ?		$_GET['sort']:'desc';
		$sc['page']				= (isset($_GET['page'])) ?		intval($_GET['page']):'0';
		$sc['perpage']			= (isset($_GET['perpage'])) ?	intval($_GET['perpage']):'10';

		###
		$data = $this->membermodel->curtion_history_sms($sc);

		$sc['searchcount']	 = $data['count'];
		$sc['totalcount']	 = $data['totalcount'];
		$sc['total_page']	 = ceil($sc['searchcount']	 / $sc['perpage']);
		$idx = 0;
		foreach($data['result'] as $datarow){
			$idx++;
			$datarow['number']	= $sc['searchcount']	 - ( ($sc['page'] -1 ) * 1 + $idx + 1) + 1;

			$datarow['kind_name'] = "";
			foreach($curationmn as $v){
				if($v['name']==$datarow['kind']){
					$datarow['kind_name'] = $v['title'];
				}
			}
			$dataloop[] = $datarow;
		}

		###
		if(isset($data)) $this->template->assign('loop',$dataloop);
		$paginlay = pagingtag($sc['searchcount'],$sc['perpage'],$this->membermodel->admin_member_url($file_path).'?', getLinkFilter('',array_keys($sc)) );
		$this->template->assign('pagin',$paginlay);

		$this->template->assign('curationmn',$curationmn);
        $this->template->assign('perpage',$sc['perpage']);
        $this->template->assign('searchcount',$sc['searchcount']);
		$this->template->assign('sc',$sc);

		$this->template->assign('tab2','-on');
		if(isset($data)) $this->template->assign('loop',$dataloop);
		$this->template->define(array('tpl'=>$file_path,'top_menu'=>$this->skin.'/member/top_menu.html'));
		$this->template->print_("tpl");
	}

	## 리마인드 Email 발송내역
	public function  curation_history_email(){

		$this->admin_menu();
		$this->tempate_modules();
		$file_path	= $this->template_path();
		$this->load->helper('reservation_helper');
		$this->load->model('membermodel');

		$curation_menu = curation_menu();
		foreach($curation_menu as $v){
			$loop = array();
			$tmp	= explode("_",$v['name']);
			$loop['name'] = $tmp[1];
			$loop['title'] = $v['title'];

			$curationmn[] = $loop;
		}

		$_GET['sc_gb'] = "PERSONAL";

		$sc = $_GET;
		$sc['orderby']			= (isset($_GET['orderby'])) ?	$_GET['orderby']:'seq';
		$sc['sort']				= (isset($_GET['sort'])) ?		$_GET['sort']:'desc';
		$sc['page']				= (isset($_GET['page'])) ?		intval($_GET['page']):'0';
		$sc['perpage']			= (isset($_GET['perpage'])) ?	intval($_GET['perpage']):'10';

		###
		$data = $this->membermodel->curtion_history_email($sc);

		$sc['searchcount']	 = $data['count'];
		$sc['totalcount']	 = $data['totalcount'];
		$sc['total_page']	 = ceil($sc['searchcount']	 / $sc['perpage']);
		$idx = 0;
		foreach($data['result'] as $datarow){
			$idx++;
			$datarow['number']	= $sc['searchcount']	 - ( ($sc['page'] -1 ) * 1 + $idx + 1) + 1;

			$datarow['kind_name'] = "";
			foreach($curationmn as $v){
				if($v['name']==$datarow['kind']){
					$datarow['kind_name'] = $v['title'];
				}
			}
			$dataloop[] = $datarow;
		}

		###
		if(isset($data)) $this->template->assign('loop',$dataloop);
		$paginlay = pagingtag($sc['searchcount'],$sc['perpage'],$this->membermodel->admin_member_url($file_path).'?', getLinkFilter('',array_keys($sc)) );
		$this->template->assign('pagin',$paginlay);

		$this->template->assign('curationmn',$curationmn);
        $this->template->assign('perpage',$sc['perpage']);
        $this->template->assign('searchcount',$sc['searchcount']);
		$this->template->assign('sc',$sc);

		$this->template->assign('tab3','-on');
		if(isset($data)) $this->template->assign('loop',$dataloop);
		$this->template->define(array('tpl'=>$file_path,'top_menu'=>$this->skin.'/member/top_menu.html'));
		$this->template->print_("tpl");
	}

	## 리마인드 유입통계 상세
	public function curation_stat_detail(){

		$this->admin_menu();
		$this->tempate_modules();
		$file_path	= $this->template_path();

		$this->load->helper('reservation_helper');
		$this->load->model('membermodel');

		$curation_menu = curation_menu();
		foreach($curation_menu as $v){
			$loop = array();
			$tmp	= explode("_",$v['name']);
			$loop['name'] = $tmp[1];
			$loop['title'] = $v['title'];

			$curationmn[] = $loop;
		}

		if($_GET['sc_type'] == "all") $_GET['sc_type'] = "";
		$sc = $_GET;
		$sc['orderby']			= (isset($_GET['orderby'])) ?	$_GET['orderby']:'c.member_seq';
		$sc['sort']				= (isset($_GET['sort'])) ?		$_GET['sort']:'desc';
		$sc['page']				= (isset($_GET['page'])) ?		intval($_GET['page']):0;
		$sc['perpage']			= (isset($_GET['perpage'])) ?	intval($_GET['perpage']):10;

		if($_GET['first']){
			if(!$sc['start_date2']){
				if($_GET['start_date']){
					$sc['start_date2'] = $_GET['start_date'];
				}else{
					$mktime				= strtotime(date("Y-m-d H:i:s")." -7 days");
					$sc['start_date2']	= date("Y-m-d",$mktime);
				}
			}
			if(!$sc['end_date2']){
				if($_GET['end_date']){
					$sc['end_date2'] = $_GET['end_date'];
				}else{
					$sc['end_date2']	= date("Y-m-d",mktime());
				}
			}
		}

		$data = $this->membermodel->curation_stat_detail($sc);
		$sc['searchcount']	 = $data['count'];
		$idx = 0;
		foreach($data['result'] as $datarow){
			$idx++;
			$datarow['number']	= $sc['searchcount']	 - ( ($sc['page'] -1 ) * 1 + $idx + 1) + 1;
			$dataloop[] = $datarow;
		}

		foreach($curationmn as $v){
			if($v['name'] == $sc['sc_kind']) $kind_name = $v['title'];
		}

		if(!$sc['sc_type']){
			$sc_type = "SMS/EMAIL";
		}else{
			$sc_type = $sc['sc_type'];
		}
		if(!$sc['sc_kind']) $kind_name = "전체";
		$searchcount = number_format($sc['searchcount']);

		$paginlay = pagingtag($sc['searchcount'],$sc['perpage'],$this->membermodel->admin_member_url($file_path).'?', getLinkFilter('',array_keys($sc)) );

		if(empty($paginlay))$paginlay = '<p><a class="on red">1</a><p>';

		$this->template->assign('searchcount',$searchcount);
		$this->template->assign('curationmn',$curationmn);
		$this->template->assign('pagin',$paginlay);
		$this->template->assign('perpage',$sc['perpage']);
		$this->template->assign('sc',$sc);

		if(isset($data)) $this->template->assign('loop',$dataloop);
		$this->template->define(array('tpl'=>$file_path));
		$this->template->print_("tpl");
	}

	## 리마인드 유입통계
	public function curation_stat(){

		$this->admin_menu();
		$this->tempate_modules();
		$file_path	= $this->template_path();
		$this->load->model('membermodel');
		$this->load->helper('reservation');

		### 큐레이션 발송 구분
		$curationmn	= curation_menu();

		$sc = $_GET;
		if($_GET['first']){
			if(!$sc['start_date']){
				$mktime				= strtotime(date("Y-m-d H:i:s")." -7 days");
				$sc['start_date']	= date("Y-m-d",$mktime);
			}
			if(!$sc['end_date']){
				$sc['end_date']	= date("Y-m-d",mktime());
			}
		}
		$data = $this->membermodel->curation_stat($sc);

		## 차트 기초 데이터 생성
		$inflow_kind		= array();
		foreach($data as $item){ $inflow_kind[] = $item['inflow_kind']; }
		$inflowDefault = array();
		$inflowDefault['inflow_sms_total']		= 0;
		$inflowDefault['inflow_email_total']	= 0;
		$inflowDefault['send_sms_total']		= 0;
		$inflowDefault['send_email_total']		= 0;
		$inflowDefault['login_cnt']				= 0;
		$inflowDefault['goodsview_cnt']			= 0;
		$inflowDefault['cart_cnt']				= 0;
		$inflowDefault['wish_cnt']				= 0;
		$inflowDefault['order_cnt']				= 0;
		if(!in_array("coupon",$inflow_kind)){ $inflowDefault['inflow_kind'] = "coupon"; $data[] = $inflowDefault; }
		if(!in_array("emoney",$inflow_kind)){ $inflowDefault['inflow_kind'] = "emoney"; $data[] = $inflowDefault; }
		if(!in_array("membership",$inflow_kind)){ $inflowDefault['inflow_kind'] = "membership"; $data[] = $inflowDefault; }
		if(!in_array("cart",$inflow_kind)){ $inflowDefault['inflow_kind'] = "cart"; $data[] = $inflowDefault; }
		if(!in_array("timesale",$inflow_kind)){ $inflowDefault['inflow_kind'] = "timesale"; $data[] = $inflowDefault; }
		if(!in_array("review",$inflow_kind)){ $inflowDefault['inflow_kind'] = "review"; $data[] = $inflowDefault; }
		if(!in_array("birthday",$inflow_kind)){ $inflowDefault['inflow_kind'] = "birthday"; $data[] = $inflowDefault; }
		if(!in_array("anniversary",$inflow_kind)){ $inflowDefault['inflow_kind'] = "anniversary"; $data[] = $inflowDefault; }

		/* 데이터 가공 */
		$maxValue = 0;
		$maxMonth = 12;

		$dataInflowChart	= array();
		$dataLoginChart		= array();
		$dataloop			= array();
		foreach($data as $item){

			if($item['send_sms_total']>0 && $item['inflow_sms_total']>0){
				$item['sms_stat_per'] = floor($item['inflow_sms_total']/$item['send_sms_total']*100);
			}else{
				$item['sms_stat_per'] = 0;
			}
			if($item['send_email_total']>0 && $item['inflow_email_total']>0){
				$item['email_stat_per'] = floor($item['inflow_email_total']/$item['send_email_total']*100);
			}else{
				$item['email_stat_per'] = 0;
			}
			if(!$item['login_cnt'])		$item['login_cnt']		= '0';
			if(!$item['goodsview_cnt']) $item['goodsview_cnt']	= '0';

			foreach($curationmn as $v){
				if(strstr($v['name'],$item['inflow_kind'])){
					$item['kind_name'] = $v['title'];
				}
			}
			## 접속, 로그인,상품뷰,위시리스트,장바구니,구매 : 순서 지킬 것.
			$KindLoop = array();
			$KindLoop[0]		= ($item['inflow_sms_total']+$item['inflow_email_total']);
			$KindLoop[1]		= $item['login_cnt'];
			$KindLoop[2]		= $item['goodsview_cnt'];
			$KindLoop[3]		= $item['cart_cnt'];
			$KindLoop[4]		= $item['wish_cnt'];
			$KindLoop[5]		= $item['order_cnt'];

			$inflowLoop			= array($item['kind_name'],($item['inflow_sms_total']+$item['inflow_email_total']));
			$dataInflowChart[]	= $inflowLoop;

			$LoginLoop			= array($item['kind_name'],$item['login_cnt']);
			$dataLoginChart[]	= $LoginLoop;

			$OrderLoop			= array($item['kind_name'],$item['order_cnt']);
			$dataOrderChart[]	= $OrderLoop;

			$dataKind[$item['inflow_kind']]['data']	= $KindLoop;
			$dataKind[$item['inflow_kind']]['max']	= $item['send_sms_total'] + $item['send_email_total'];
			$dataKind[$item['inflow_kind']]['lable']= $item['kind_name'];

			$dataloop[]			= $item;


		}
		$this->seriesColors1 = array("#75c8b4", "#c3b8f3", "#f383c9", "#c4b5e6","#d8f27b", "#a5aef1");
		$this->seriesColors2 = array("#445ebc", "#d33c34","#4bb2c5", "#c5b47f", "#EAA228", "#579575");

		$this->template->assign(array('seriesColors1'=>$this->seriesColors1,'seriesColors2'=>$this->seriesColors2));
		$_GET['sc_gb'] = "PERSONAL";
		###
		$this->template->assign('tab4','-on');
		$this->template->assign('sc',$sc);

		$this->template->assign(array(
			'dataKind'	=> $dataKind,
			'maxValue'		=> $maxValue
		));
		$this->template->assign(array('dataInflowChart'=>$dataInflowChart,'dataLoginChart'=>$dataLoginChart,'dataOrderChart'=>$dataOrderChart));
		if(isset($dataloop)) $this->template->assign('loop',$dataloop);
		$this->template->define(array('tpl'=>$file_path,'top_menu'=>$this->skin.'/member/top_menu.html'));
		$this->template->print_("tpl");
	}

	//휴면회원 수동 SMS
	public function dormancy_sms(){
		$this->admin_menu();
		$this->tempate_modules();
		$file_path	= $this->template_path();

		$auth_send	= $this->authmodel->manager_limit_act('member_send');
		if(!$auth_send){
			echo "<script>alert('권한이 없습니다.'); self.close();</script>";
			exit;
		}

		$table = !empty($_GET['table']) ? $_GET['table'] : 'fm_member';
		$this->template->assign('table',$table);

		// 회원정보다운로드 체크
		if ($this->managerInfo['manager_yn']=='Y') {
			$auth_member_down = true;
		} else {
			$auth_member_down	= $this->authmodel->manager_limit_act('member_download');
		}
		if(isset($auth_member_down)) $this->template->assign('auth_member_down',$auth_member_down);

		###
		if($table=='fm_goods_restock_notify'){
			$mInfo['total'] = get_rows('fm_goods_restock_notify',array('notify_status'=>'none'));
			$action = "../goods_process/restock_notify_send_sms";
			$this->template->assign('action',$action);

			$this->template->assign('send_message',"[{$this->config_basic['shopName']}] 고객님께서 알림요청하신 상품({상품고유값},{상품명},{옵션})이 재입고되었습니다.");
		}else{
			//$mInfo['total'] = get_rows('fm_member',array('status !='=>'withdrawal'));
			$action = "../member_process/send_sms";
			$this->template->assign('action',$action);
		}
		$specialArr	= array('＃', '＆', '＊', '＠', '§', '※', '☆', '★', '○', '●', '◎', '◇', '◆', '□', '■', '△', '▲', '▽', '▼', '→', '←', '↑', '↓', '↔', '〓', '◁', '◀', '▷', '▶', '♤', '♠', '♡', '♥', '♧', '♣', '⊙', '◈', '▣', '◐', '◑', '▒', '▤', '▥', '▨', '▧', '▦', '▩', '♨', '☏', '☎', '☜', '☞', '¶', '†', '‡', '↕', '↗', '↙', '↖', '↘', '♭', '♩', '♪', '♬', '㉿', '㈜', '№', '㏇', '™', '㏂', '㏘', '℡', '?', 'ª', 'º');
		$basic = ($this->config_basic)?$this->config_basic:config_load('basic');

		$sms_info = config_load('sms_info','send_num');
		if($sms_info['send_num']) $send_num = $sms_info['send_num'];

		###
		$sql = "select count(seq) as total, category from fm_sms_album group by category";
		$query = $this->db->query($sql);
		$sms_data = $query->result_array();
		$sms_total = get_rows('fm_sms_album');
		array_push($sms_data,array('total'=>$sms_total,'category'=>'전체보기'));
		rsort($sms_data);

		$sms_id = $this->config_system['service']['sms_id'];
		$limit	= commonCountSMS();
		$sms_chk = $sms_id;

		$this->template->assign('count',$limit);
		$this->template->assign(array('mInfo'=>$mInfo,'number'=>($send_num)? $send_num : $basic['companyPhone']));
		$this->template->assign(array('sms_loop'=>$sms_data,'sms_total'=>$sms_total,'sms_cont'=>$specialArr,'chk'=>$sms_chk));
		$this->template->define(array('tpl'=>$file_path));
		$this->template->print_("tpl");
	}

	public function sms_form_dormancy()
	{
		$this->admin_menu();
		$this->tempate_modules();
		$file_path	= $this->template_path();

		$auth_send	= $this->authmodel->manager_limit_act('member_send');
		if(!$auth_send){
			echo "<script>alert('권한이 없습니다.'); self.close();</script>";
			exit;
		}

		$table = !empty($_GET['table']) ? $_GET['table'] : 'fm_member';
		$this->template->assign('table',$table);

		$send_phone = getSmsSendInfo();
		if(isset($send_phone)) $this->template->assign('send_phone',$send_phone);

		// 회원정보다운로드 체크
		if ($this->managerInfo['manager_yn']=='Y') {
			$auth_member_down = true;
		} else {
			$auth_member_down	= $this->authmodel->manager_limit_act('member_download');
		}
		if(isset($auth_member_down)) $this->template->assign('auth_member_down',$auth_member_down);

		###
		if($table=='fm_goods_restock_notify'){
			$mInfo['total'] = get_rows('fm_goods_restock_notify',array('notify_status'=>'none'));
			$action = "../goods_process/restock_notify_send_sms";
			$this->template->assign('action',$action);

			$this->template->assign('send_message',"[{$this->config_basic['shopName']}] 고객님께서 알림요청하신 상품({상품고유값},{상품명},{옵션})이 재입고되었습니다.");
		}else{
			//$mInfo['total'] = get_rows('fm_member',array('status !='=>'withdrawal'));
			$action = "../member_process/send_sms";
			$this->template->assign('action',$action);
		}
		$specialArr	= array('＃', '＆', '＊', '＠', '§', '※', '☆', '★', '○', '●', '◎', '◇', '◆', '□', '■', '△', '▲', '▽', '▼', '→', '←', '↑', '↓', '↔', '〓', '◁', '◀', '▷', '▶', '♤', '♠', '♡', '♥', '♧', '♣', '⊙', '◈', '▣', '◐', '◑', '▒', '▤', '▥', '▨', '▧', '▦', '▩', '♨', '☏', '☎', '☜', '☞', '¶', '†', '‡', '↕', '↗', '↙', '↖', '↘', '♭', '♩', '♪', '♬', '㉿', '㈜', '№', '㏇', '™', '㏂', '㏘', '℡', '?', 'ª', 'º');
		$basic = ($this->config_basic)?$this->config_basic:config_load('basic');

		$sms_info = config_load('sms_info','send_num');
		if($sms_info['send_num']) $send_num = $sms_info['send_num'];

		$sms_id = $this->config_system['service']['sms_id'];
		$limit	= commonCountSMS();
		$sms_chk = $sms_id;

		$this->template->assign('count',$limit);
		$this->template->assign(array('mInfo'=>$mInfo, 'sms_cont'=>$specialArr,'number'=>($send_num)? $send_num : $basic['companyPhone']));
		$this->template->define(array('tpl'=>$file_path));
		$this->template->print_("tpl");
	}

	public function sms_form_list_pop()
	{
		$this->admin_menu();
		$this->tempate_modules();
		$file_path	= $this->template_path();

		$auth_send	= $this->authmodel->manager_limit_act('member_send');
		if(!$auth_send){
			echo "<script>alert('권한이 없습니다.'); self.close();</script>";
			exit;
		}

		$table = !empty($_GET['table']) ? $_GET['table'] : 'fm_member';
		$this->template->assign('table',$table);

		###
		$sms_data = array();
		$sql = "select count(seq) as total, category from fm_sms_album group by category";
		$query = $this->db->query($sql);
		$sms_data = $query->result_array();
		$sms_total = get_rows('fm_sms_album');
		array_unshift($sms_data,array('total'=>$sms_total,'category'=>'전체보기'));

		$sms_id = $this->config_system['service']['sms_id'];
		$limit	= commonCountSMS();
		$sms_chk = $sms_id;

		$this->template->assign(array('sms_loop'=>$sms_data,'sms_total'=>$sms_total,'sms_cont'=>$specialArr,'chk'=>$sms_chk));
		$this->template->define(array('tpl'=>$file_path));
		$this->template->print_("tpl");
	}

	//이메일 수동 발송
	public function email_form_dormancy()
	{
		$this->admin_menu();
		$this->tempate_modules();
		$file_path	= $this->template_path();

		$auth_send	= $this->authmodel->manager_limit_act('member_send');

		if(!$auth_send){
			echo "<script>alert('권한이 없습니다.'); self.close();</script>";
			exit;
		}


		// 회원정보다운로드 체크
		if ($this->managerInfo['manager_yn']=='Y') {
			$auth_member_down = true;
		} else {
			$auth_member_down	= $this->authmodel->manager_limit_act('member_download');
		}
		if(isset($auth_member_down)) $this->template->assign('auth_member_down',$auth_member_down);


		$basic = ($this->config_basic)?$this->config_basic:config_load('basic');

		###
		$query = $this->db->query("select * from fm_log_email order by seq desc limit 10");
		$emailData = $query->result_array();

		###
		$this->load->model('usedmodel');
		$email_chk = $this->usedmodel->hosting_check();
		$this->template->assign('email_chk',$email_chk);
		$this->template->assign('verify',$this->config_system['shopSno']);

		/**
		 * SSL Redirect를 사용 중이라면 프로토콜을 무조건 https로 한다.
		 * 2019-06-17
		 * @copyright gabiacns
		 * @author Sunha Ryu
		 */
		$protocol = function_exists('get_connet_protocol') ? get_connet_protocol() : 'http://';
		$domain = $this->input->server('HTTP_HOST');
		$this->load->library('ssllib');
		$sslEnv = $this->ssllib->getSslEnvironment(array(), 1);
		if(!empty($sslEnv['data']) && count($sslEnv['data'])>0 && !empty($sslEnv['data'][0])) {
		    // 접속한 도메인이 ssl 대상 도메인에 포함되어 있고, 리다이렉트 설정이 Y 면 무조건 https로 한다.
		    if(in_array($domain, $sslEnv['data'][0]['domains'])) {
		        $certRedirect = $sslEnv['data'][0][$this->ssllib->sslConfigColumn['certRedirect']];
		        if($certRedirect === 'Y') {
		            $protocol = 'https://';
		        }
		    }
		}

		###
		//$mInfo['total'] = get_rows('fm_member',array('status !='=>'withdrawal'));

		$this->template->assign('protocol', $protocol);
		$this->template->assign('domain', $domain);
		$this->template->assign('agreeManager', $this->managerInfo['mname'].'('.$this->managerInfo['manager_id'].')');
		$this->template->assign(array('mail_count'=>master_mail_count(),'email'=>$basic['companyEmail']));
		$this->template->assign('loop',$emailData);
		$this->template->define(array('tpl'=>$file_path));
		$this->template->print_("tpl");
	}

	public function email_log_list_pop()
	{
		$this->admin_menu();
		$this->tempate_modules();
		$file_path	= $this->template_path();

		$auth_send	= $this->authmodel->manager_limit_act('member_send');

		if(!$auth_send){
			echo "<script>alert('권한이 없습니다.'); self.close();</script>";
			exit;
		}

		###
		$query = $this->db->query("select * from fm_log_email order by seq desc limit 10");
		$emailData = $query->result_array();

		$this->template->assign('loop',$emailData);
		$this->template->define(array('tpl'=>$file_path));
		$this->template->print_("tpl");
	}

	### 회원상세
	public function member_crm_detail()
	{
		$this->admin_menu();
		$this->tempate_modules();
		$file_path	= $this->template_path();

		#### AUTH
		$auth_act		= $this->authmodel->manager_limit_act('member_act');
		if(isset($auth_act)) $this->template->assign('auth_act',$auth_act);
		$auth_promotion = $this->authmodel->manager_limit_act('member_promotion');
		if(isset($auth_promotion)) $this->template->assign('auth_promotion',$auth_promotion);
		$auth_send	= $this->authmodel->manager_limit_act('member_send');
		if(isset($auth_send)) $this->template->assign('auth_send',$auth_send);

		if(!isset($_GET['member_seq'])){
			$callback = "parent.history.back();";
			openDialogAlert("잘못된 접근입니다.",400,140,'parent',$callback);
			die();
		}

		// 개인정보 조회 로그
		//'member', 'memberlist', 'order', 'export', 'return', 'refund', 'orderexcel', 'exportexcel'
		$this->load->model('logPersonalInformation');
		$this->logPersonalInformation->insert('member',$this->managerInfo['manager_seq'],$_GET['member_seq']);

		###
		$this->load->model('membermodel');
		$data = $this->membermodel->get_member_data($_GET['member_seq']);
		if($data['auth_type']=='auth'){
			$data['auth_type'] = "실명인증";
		}else if($data['auth_type']=='ipin'){
			$data['auth_type'] = "아이핀";
		}else{
			$data['auth_type'] = "없음";
		}

		$withdrawal = code_load('withdrawal');
		if($withdrawal) $this->template->assign('withdrawal_arr',$withdrawal);


		###
		$grade_list = $this->membermodel->find_group_list();
		$grade_list = array_reverse($grade_list);
		$this->template->assign('grade_list',$grade_list);
		//print_r($grade_list);

		//1:1문의건
		$this->load->model('Boardmodel');
		$sc['whereis'] = " and boardid = 'mbqna' and mseq='".$data['member_seq']."' and (re_contents = '' or re_contents is null)";
		$sc['select'] = " count(gid) as cnt ";
		$mbqnaquery = $this->Boardmodel->get_data($sc);

		if($mbqnaquery['cnt'] > 9) $mbqnaquery['cnt'] = "9+";
		$data['mbqna_sum'] = $mbqnaquery['cnt'];

		//리뷰건
		$this->load->model('goodsreview');
		$sc['whereis'] = " and mseq='".$data['member_seq']."'";
		$sc['select'] = " count(gid) as cnt ";
		$gdreviewquery = $this->goodsreview->get_data($sc);

		if($gdreviewquery['cnt'] > 9) $gdreviewquery['cnt'] = "9+";
		$data['gdreview_sum'] = $gdreviewquery['cnt'];

		//상품문의건
		$this->load->model('goodsqna');
		$sc['whereis'] = "and mseq= ? and (re_contents = '' or re_contents is null)";
		$bindData = [$data['member_seq']];
		$sc['select'] = " count(gid) as cnt ";
		$gdqnaquery = $this->goodsqna->get_data($sc, $bindData);
		if($gdqnaquery['cnt'] > 9) $gdqnaquery['cnt'] = "9+";
		$data['gdqna_sum'] = $gdqnaquery['cnt'];

		//상담미처리건
		$sql = "select count(*) as cnt from fm_counsel where counsel_status = 'request' and member_seq = '".$data['member_seq']."'";
		$query = $this->db->query($sql);
		$result = $query->row_array();

		if($result['cnt'] > 9) $result['cnt'] = "9+";
		$data['counsel_sum'] = $result['cnt'];


		//쿠폰보유건 test
		$this->load->model('couponmodel');
		$this->load->helper('coupon');
		down_coupon_list('admin', $sc , $dataloop);

		$svcount = $this->couponmodel->get_download_have_total_count($sc,$data);
		$this->template->assign($svcount);

		$this->load->model('snsmember');

		$snsmbsc['select'] = " * ";
		$snsmbsc['whereis'] = " and member_seq ='".$data['member_seq']."' ";
		$snslist = $this->snsmember->snsmb_list($snsmbsc);
		if($snslist['result'][0]) $data['snslist'] = $snslist['result'];


		$sql	= "
				SELECT count(*) as cnt
				FROM fm_order as b
				WHERE hidden = 'N'
				and member_seq = '".$data['member_seq']."'
				and step = '15'
				";
		$query	= $this->db->query($sql);
		$orderResult = $query->row_array();


		$sql	= "
				SELECT count(*) as cnt
				FROM fm_order a
				WHERE a.hidden = 'N'
				and a.member_seq = '".$data['member_seq']."'
				and a.step in ('25','35','40','45','50','60','70')
				";
		$query	= $this->db->query($sql);
		$settleResult = $query->row_array();

		/* 반품 접수 */
		$query = $this->db->query("select count(*) as cnt from fm_order_return as b left join fm_order as o on b.order_seq = o.order_seq where b.status = 'request' and o.member_seq = '".$data['member_seq']."' ".$date_range);
		$result = $query->row_array();

		if($result['cnt'] > 9) $result['cnt'] = "9+";
		$orderSummary['101'] = array(
			'count'		=> ($result['cnt']) ? $result['cnt'] : 0,
			'name'		=> '반품접수',
			'link'		=> '../returns/catalog?return_status[]=request&keyword='.$data['userid']
		);

		/* 환불 접수 */
		$query = $this->db->query("select count(*) as cnt from fm_order_refund as b left join fm_order as o on b.order_seq = o.order_seq where b.status = 'request' and o.member_seq = '".$data['member_seq']."' ".$date_range);
		$result = $query->row_array();

		if($result['cnt'] > 9) $result['cnt'] = "9+";
		$orderSummary['102'] = array(
			'count'		=> ($result['cnt']) ? $result['cnt'] : 0,
			'name'		=> '환불접수',
			'link'		=> '../refund/catalog?refund_status[]=request&keyword='.$data['userid']
		);

		if($orderResult['cnt'] > 9) $orderResult['cnt'] = "9+";
		if($settleResult['cnt'] > 9) $settleResult['cnt'] = "9+";

		$orderData['order'] = $orderResult['cnt'];
		$orderData['settle'] = $settleResult['cnt'];

		//프로모션 코드 수
		$sql = "select count(*) as cnt from fm_download_promotion where use_status = 'unused' and issue_enddate >= '".date("Y-m-d")."' and member_seq = '".$data['member_seq']."'";
		$query = $this->db->query($sql);
		$result = $query->row_array();
		$this->template->assign('promotionCount',$result['cnt']);
		$this->template->assign('query_string',$_GET['query_string']);


		//권한
		$auth_arr = explode("||",$this->managerInfo['manager_auth']);
		foreach($auth_arr as $k){
			$tmp_arr = explode("=",$k);
			$auth[$tmp_arr[0]] = $tmp_arr[1];
		}

		$this->template->assign(array('auth'=>$auth));

		$boardAuthSql = "select * from fm_boardadmin where boardid in ('goods_qna', 'mbqna') and manager_seq = '".$this->managerInfo['manager_seq']."'";
		$boardAuthQuery = $this->db->query($boardAuthSql);
		$boardAuthResult = $boardAuthQuery->result_array();

		foreach($boardAuthResult as $boardAuthData){
			if($boardAuthData['board_view'] > 0){
				$boardAuth[$boardAuthData['boardid']] = "Y";
			}
		}

		$this->template->assign(array('boardAuth'=>$boardAuth));

		$this->template->assign($data);
		$this->template->assign(array('orderData'=>$orderData));
		$this->template->assign(array('orderSummary'=>$orderSummary));
		$this->template->define(array('tpl'=>$file_path));
		$this->template->print_("tpl");
	}

	### 비회원상세
	public function nomember_crm_detail()
	{
		$this->admin_menu();
		$this->tempate_modules();
		$file_path	= $this->template_path();

		#### AUTH
		$auth_act		= $this->authmodel->manager_limit_act('member_act');
		if(isset($auth_act)) $this->template->assign('auth_act',$auth_act);
		$auth_promotion = $this->authmodel->manager_limit_act('member_promotion');
		if(isset($auth_promotion)) $this->template->assign('auth_promotion',$auth_promotion);
		$auth_send	= $this->authmodel->manager_limit_act('member_send');
		if(isset($auth_send)) $this->template->assign('auth_send',$auth_send);

		if(!isset($_GET['order_seq'])){
			$callback = "parent.history.back();";
			openDialogAlert("잘못된 접근입니다.",400,140,'parent',$callback);
			die();
		}


		//리뷰건
		$this->load->model('goodsreview');
		$sc['whereis'] = " and order_seq='".$_GET['order_seq']."'";
		$sc['select'] = " count(gid) as cnt ";
		$gdreviewquery = $this->goodsreview->get_data($sc);
		if($gdreviewquery['cnt'] > 9) $gdreviewquery['cnt'] = "9+";
		$data['gdreview_sum'] = $gdreviewquery['cnt'];

		//상담미처리건
		$sql = "select count(*) as cnt from fm_counsel where counsel_status = 'request' and order_seq = '".$_GET['order_seq']."'";
		$query = $this->db->query($sql);
		$result = $query->row_array();

		if($result['cnt'] > 9) $result['cnt'] = "9+";
		$data['counsel_sum'] = $result['cnt'];

		$sql	= "
				SELECT count(*) as cnt
				FROM fm_order as b
				WHERE hidden = 'N'
				and order_seq = '".$_GET['order_seq']."'
				and step = '15'
				";
		$query	= $this->db->query($sql);
		$orderResult = $query->row_array();

		$sql	= "
				SELECT count(*) as cnt
				FROM fm_order a
				WHERE a.hidden = 'N'
				and a.order_seq = '".$_GET['order_seq']."'
				and a.step in ('25','35','45','50','60','70')
				";
		$query	= $this->db->query($sql);
		$settleResult = $query->row_array();

		/* 반품 접수 */
		$query = $this->db->query("select count(*) as cnt from fm_order_return as b left join fm_order as o on b.order_seq = o.order_seq where b.status = 'request' and o.order_seq = '".$_GET['order_seq']."' ".$date_range);
		$result = $query->row_array();

		if($result['cnt'] > 9) $result['cnt'] = "9+";
		$orderSummary['101'] = array(
			'count'		=> ($result['cnt']) ? $result['cnt'] : 0,
			'name'		=> '반품접수',
			'link'		=> '../returns/catalog?return_status[]=request&keyword='.$data['userid']
		);

		/* 환불 접수 */
		$query = $this->db->query("select count(*) as cnt from fm_order_refund as b left join fm_order as o on b.order_seq = o.order_seq where b.status = 'request' and o.order_seq = '".$_GET['order_seq']."' ".$date_range);
		$result = $query->row_array();

		if($result['cnt'] > 9) $result['cnt'] = "9+";
		$orderSummary['102'] = array(
			'count'		=> ($result['cnt']) ? $result['cnt'] : 0,
			'name'		=> '환불접수',
			'link'		=> '../refund/catalog?refund_status[]=request&keyword='.$data['userid']
		);

		$orderData['order'] = $orderResult['cnt'];
		$orderData['settle'] = $settleResult['cnt'];

		//권한
		$this->db->where('manager_seq', $this->managerInfo['manager_seq']);
		$query = $this->db->get('fm_manager');
		$authdata = $query->result_array();

		$auth_arr = explode("||",$authdata[0]['manager_auth']);
		foreach($auth_arr as $k){
			$tmp_arr = explode("=",$k);
			$auth[$tmp_arr[0]] = $tmp_arr[1];
		}

		$this->template->assign(array('auth'=>$auth));

		$boardAuthSql = "select * from fm_boardadmin where boardid in ('goods_qna', 'mbqna') and manager_seq = '".$this->managerInfo['manager_seq']."'";
		$boardAuthQuery = $this->db->query($boardAuthSql);
		$boardAuthResult = $boardAuthQuery->result_array();

		foreach($boardAuthResult as $boardAuthData){
			if($boardAuthData['board_view'] > 0){
				$boardAuth[$boardAuthData['boardid']] = "Y";
			}
		}

		$this->template->assign(array('boardAuth'=>$boardAuth));

		$this->template->assign($data);
		$this->template->assign(array('orderData'=>$orderData));
		$this->template->assign(array('orderSummary'=>$orderSummary));
		$this->template->define(array('tpl'=>$file_path));
		$this->template->print_("tpl");
	}

	// 회원의 최근 배송지 5개 추출
	public function get_lastly_shipping_address(){

		$member_seq		= (trim($_GET['member_seq'])) ? trim($_GET['member_seq']) : 0;
		$return_type	= (trim($_GET['return_type'])) ? trim($_GET['return_type']) : 'json';
		if	($member_seq > 0){
			$this->load->model('membermodel');
			// 최근배송지 5개 로딩
			$lately_delivery_address = $this->membermodel->get_delivery_address($member_seq,'lately',0,5);
		}

		if		($return_type == 'json'){
			echo json_encode($lately_delivery_address);
		}else{
			return $lately_delivery_address;
		}
	}

	// 관리자 주문 시 [배송주소록] 선택하기 2018-04-10
	public function delivery_address_ajax(){
		$key = get_shop_key();
		$query = $this->db->query("select *,
				AES_DECRYPT(UNHEX(recipient_phone), '{$key}') as recipient_phone,
				AES_DECRYPT(UNHEX(recipient_cellphone), '{$key}') as recipient_cellphone
			from fm_delivery_address where address_seq=?",$_GET['address_seq']);
		$result = $query->row_array();
		foreach($result as $k=>$v){
			if(is_null($v)) $result[$k] = '';
			if($k == 'default' ) $result['defaults'] = $v;
		}
		$result['recipient_new_zipcode'] = str_replace("-", "", $result['recipient_zipcode']);
		$result['result'] = true;

		echo json_encode($result);
	}

	# KAKAO TALK FNC ADD - START - :: 2018-02-27 lwh
	public function kakaotalk_index(){
		// 설정정보 호출
		$this->load->model('kakaotalkmodel');
		$kakaotalk_config	= $this->kakaotalkmodel->get_service();

		if ($kakaotalk_config['use_service'] == 'Y'){
			redirect("/admin/member/kakaotalk_msg");
		}else{
			redirect("/admin/member/kakaotalk");
		}
	}

	// 카카오 알림톡
	public function kakaotalk(){

		$auth = $this->authmodel->manager_limit_act('kakaotalk_view');
		if(!$auth){
			pageBack("권한이 없습니다.");
			exit;
		}

		$this->admin_menu();
		$this->tempate_modules();
		$file_path	= $this->template_path();
		$this->load->model('kakaotalkmodel');

		// 설정정보 호출
		$kakaotalk_config	= $this->kakaotalkmodel->get_service();

		if	($kakaotalk_config['authKey']){
			$this->template->assign('kakaotalk_config',$kakaotalk_config);
		}else{
			// 업종 카테고리 조회
			$category_json	= $this->kakaotalkmodel->apiSender('categoryAll');
			$this->template->assign(array('category_json'=>$category_json,'categoryReset'=>$this->input->get('categoryReset')));

			// 사업자 번호 추출
			$config				= config_load('basic','businessLicense');
			$businessLicense	= str_replace('-','',$config['businessLicense']);
			if(strlen($businessLicense) == 10){
				$this->template->assign('businessLicense',$businessLicense);
			}

			// 서비스 이용약관
			$agreement1 = nl2br(strip_tags(file_get_contents($_SERVER['DOCUMENT_ROOT'].'/admin/skin/default/member/kakao_agreement1.html')));
			$agreement2 = nl2br(strip_tags(file_get_contents($_SERVER['DOCUMENT_ROOT'].'/admin/skin/default/member/kakao_agreement2.html')));
			$agreement3 = file_get_contents($_SERVER['DOCUMENT_ROOT'].'/admin/skin/default/member/kakao_agreement3.html');
			$this->template->assign('agreement1',$agreement1);
			$this->template->assign('agreement2',$agreement2);
			$this->template->assign('agreement3',$agreement3);
		}

		// 재고관리 버전 체크
		if(!$this->scm_cfg)	$this->scm_cfg	= config_load('scm');
		$scm_use_chk	= ($this->scm_cfg['use'] == 'Y' && $this->scm_cfg['scm_type'] == 'local') ? 'Y' : 'N';

		$this->template->assign('scm_use_chk',$scm_use_chk);
		$this->template->assign('tab1','-on');
		$this->template->define('top_menu',$this->skin.'/member/kakaotalk_top_menu.html');
		$this->template->define(array('tpl'=>$file_path));
		$this->template->print_("tpl");
	}

	// 카카오톡 인증토큰요청
	public function kakaotalk_auth(){
		$this->load->model('kakaotalkmodel');

		parse_str($_POST['formdata']);

		$data['shopno']		= $this->config_system['shopSno'];
		$data['domain']		= $this->config_system['subDomain'];
		$data['sendTelNo']	= '15443270';
		$data['grpName']	= $this->config_system['shopSno'];
		$data['yellowId']	= $yellowId;
		$data['phoneNumber']= $phoneNumber;

		$res = $this->kakaotalkmodel->apiSender('getToken', $data);

		echo $res;
	}

	// 카카오톡 메세지 관리
	public function kakaotalk_msg(){
		$auth = $this->authmodel->manager_limit_act('kakaotalk_view');
		if(!$auth){
			pageBack("권한이 없습니다.");
			exit;
		}

		$this->admin_menu();
		$this->tempate_modules();
		$file_path	= $this->template_path();
		$this->load->model('kakaotalkmodel');

		// 메세지 종류 추출
		$scParams	= array();
		$msg_type	= array('common','goods','ticket','remind','present');
		$msg_list	= $this->kakaotalkmodel->get_template($scParams);
		if ($msg_list){ // SMS 설정 추출
			$sms		= config_load('sms');
			$remindUse	= config_load('personal_use');
			foreach ($msg_type as $k => $msg_code){
				foreach ($msg_list[$msg_code] as $idx => $msgInfo){
					if ($msg_code == 'remind'){
						$msg_list[$msg_code][$idx]['sms_use'] = strtoupper($remindUse[$msgInfo['msg_code'].'_use']);
					}else{
						$msg_list[$msg_code][$idx]['sms_use'] = $sms[$msgInfo['msg_code'].'_yn'];
					}
				}
			}
		}else{ // 메세지 초기화 셋팅 :: 2018-02-27 lwh
			$this->kakaotalkmodel->set_template_default_code();
			$cnt = $this->kakaotalkmodel->set_template_sync('set');
			$msg_list = $this->kakaotalkmodel->get_template($scParams);
		}

		$this->template->assign('msg_type',$msg_type);
		$this->template->assign('msg_list',$msg_list);
		$this->template->assign('tab2','-on');
		$this->template->define('top_menu',$this->skin.'/member/kakaotalk_top_menu.html');
		$this->template->define(array('tpl'=>$file_path));
		$this->template->print_("tpl");
	}

	// 카카오 페이 메세지 수정페이지
	public function kakaotalk_template_modify(){
		$this->admin_menu();
		$this->tempate_modules();
		$file_path	= $this->template_path();
		$this->load->model('kakaotalkmodel');

		# 치환코드 정의 ##############################################
		$rpl_code_arr['join']					= array(
			"shopName","shopDomain","userid","userName"
		);// 회원가입 시
		$rpl_code_arr['withdrawal']				= array(
			"shopName","shopDomain","userid"
		);// 회원탈퇴 시
		$rpl_code_arr['findid']					= array(
			"shopName","shopDomain","userid","userName"
		);// 아이디 찾기
		$rpl_code_arr['findpwd']				= array(
			"shopName","shopDomain","password"
		);// 비밀번호 찾기
		$rpl_code_arr['order']					= array(
			"shopName","shopDomain","ordno","ord_item","bank_account","settleprice","userid","order_user"
		);// 주문접수 시
		$rpl_code_arr['settle']					= array(
			"shopName","shopDomain","ordno","ord_item","settleprice","settle_kind","userid","order_user"
		);// 결제확인 시
		$rpl_code_arr['deposit']				= array(
			"shopName","shopDomain","ordno","ord_item","bank_account","settleprice","userid","order_user"
		);// 미입금 시
		$rpl_code_arr['sms_charge']				= array(
			"remainSms"
		);// 문자 충전 안내
		$rpl_code_arr['autodeposit_charge']		= array(
			"remainAutodeposit"
		);// 무통장자동입금 연장 안내
		$rpl_code_arr['goodsflow_charge']		= array(
			"remainGoodsflow"
		);// 택배자동 연장 안내
		$rpl_code_arr['released']				= array(
			"shopName","shopDomain","ordno","go_item","delivery_company","delivery_number","userid","order_user","recipient_user","userName"
		);// 출고완료 시
		$rpl_code_arr['released2']				= array(
			"shopName","shopDomain","ordno","go_item","delivery_company","delivery_number","userid","order_user","recipient_user"
		);// 출고완료 시 받는분 (≠주문자)
		$rpl_code_arr['delivery']				= array(
			"shopName","shopDomain","ordno","go_item","userid","order_user","recipient_user","userName"
		);// 배송완료 시
		$rpl_code_arr['delivery2']				= array(
			"shopName","shopDomain","ordno","go_item","userid","order_user","recipient_user"
		);// 배송완료 시 받는분 (≠주문자)
		$rpl_code_arr['cancel']					= array(
			"shopName","shopDomain","repay_item","userid","order_user","userName","ordno"
		);//결제취소→환불완료 시
		$rpl_code_arr['refund']					= array(
			"shopName","shopDomain","repay_item","userid","order_user","userName","ordno"
		);// 반품→환불완료 시
		$rpl_code_arr['coupon_released']		= array(
			"shopName","shopDomain","coupon_serial","couponNum","coupon_value","options","goods_name","userid","order_user","recipient_user"
		);// 출고완료 시(티켓발송)
		$rpl_code_arr['coupon_released2']		= array(
			"shopName","shopDomain","coupon_serial","couponNum","coupon_value","options","goods_name","userid","order_user","recipient_user","userName"
		);// 출고완료 시(티켓발송) 주문자(≠받는분)
		$rpl_code_arr['coupon_cancel']			= array(
			"shopName","shopDomain","coupon_serial","couponNum","goods_name","userid","order_user","recipient_user","userName"
		);// 결제취소→환불완료 시
		$rpl_code_arr['coupon_delivery']		= array(
			"shopName","shopDomain","coupon_serial","couponNum","coupon_value","options","used_time","coupon_used","coupon_remain","used_location","confirm_person","goods_name","userid","order_user"
		);//배송완료 시(티켓사용)
		$rpl_code_arr['coupon_delivery2']		= array(
			"shopName","shopDomain","coupon_serial","couponNum","coupon_value","options","used_time","coupon_used","coupon_remain","used_location","confirm_person","goods_name","userid","order_user","userName"
		);//티켓사용 (주문자)
		$rpl_code_arr['coupon_refund']			= array(
			"shopName","shopDomain","coupon_serial","couponNum","goods_name","userid","order_user","userName"
		);// 반품→환불완료 시
		$rpl_code_arr['dormancy']				= array(
			"userid","shopName","dormancy_du_date","shopDomain","userName"
		);// 휴면회원
		$rpl_code_arr['goods_qna_reply']		= array(
			"shopName","boardName","userid","userName","shopDomain"
		);//상품문의 답변
		$rpl_code_arr['mbqna_reply']			= array(
			"shopName","boardName","userid","userName","shopDomain"
		);//1:1문의 답변
		$rpl_code_arr['goods_review_reply']		= array(
			"shopName","boardName","userid","userName","shopDomain"
		);//상품후기
		$rpl_code_arr['personal_coupon']		= array(
			"shopName","userid","userName","usernickname","userlevel","usermileage","userpoint","useremoney","coupon_count","mypage_short_url"
		);//이번주 만료될 할인 쿠폰
		$rpl_code_arr['personal_membership']		= array(
			"shopName","userid","userName","usernickname","userlevel","usermileage","userpoint","useremoney","mypage_short_url","shopDomain"
		);//회원 멤버쉽 등급 변경
		$rpl_code_arr['personal_emoney']		= array(
			"shopName","userid","userName","usernickname","userlevel","usermileage","userpoint","useremoney","mileage_rest","mypage_short_url"
		);//다음 달 소멸 예정 마일리지
		$rpl_code_arr['personal_review']		= array(
			"shopName","userid","userName","usernickname","userlevel","userday","userbirthday","usermileage","userpoint","useremoney","anniversary","coupon_count","go_item","mileage_rest","mypage_short_url","shopDomain"
		);//상품 리뷰 작성 유도

		## 치환코드 리스트
		$replace_item['shopName']			= array('name' => '쇼핑몰명(설정 &gt; 상점정보)');
		$replace_item['shopDomain']			= array('name' => '쇼핑몰 도메인');
		$replace_item['userid']				= array('name' => '회원아이디');
		$replace_item['userName']			= array('name' => '회원명(회원명 없을시 제외)');
		$replace_item['password']			= array('name' => '회원비밀번호');
		$replace_item['order_user']			= array('name' => '주문자명');
		$replace_item['recipient_user']		= array('name' => '받는분');
		$replace_item['ordno']				= array('name' => '주문번호');
		$replace_item['orduserName']		= array('name' => '주문자명');
		$replace_item['boardName']			= array('name' => '게시판이름');
		$replace_item['go_item']			= array(
			'name'	=> '출고완료/배송완료 상품',
			'etc'	=> '여러 개의 상품인 경우 외 몇 건으로 표시됨<br />상품명은 치환코드 길이 설정을 따릅니다.'
		);
		$replace_item['ord_item']			= array(
			'name'	=> '주문상품',
			'etc'	=> '여러 개의 상품인 경우 외 몇 건으로 표시됨<br />상품명은 치환코드 길이 설정을 따릅니다.'
		);
		$replace_item['bank_account']		= array('name' => '입금은행 계좌번호 예금주');
		$replace_item['settleprice']		= array('name' => '입금(결제)금액');
		$replace_item['settle_kind']		= array(
			'name'	=> '결제수단 수단별확인메시지',
			'etc'	=> '<div style="color:#999999;">신용카드 예시) 카드결제 완료<br/>계좌이체 예시) 계좌이체 완료<br/>가상계좌 예시) 가상계좌 완료<br/>무통장 예시) OO은행 입금확인<br/>핸드폰 예시) 핸드폰 결제완료</div>'
		);
		$replace_item['delivery_company']	= array('name' => '택배사명');
		$replace_item['delivery_number']	= array('name' => '운송장번호');
		$replace_item['coupon_serial']		= array('name' => '티켓인증코드');
		$replace_item['couponNum']			= array('name' => '티켓발송회차');
		$replace_item['coupon_value']		= array('name' => '티켓값어치');
		$replace_item['options']			= array('name' => '필수옵션');
		$replace_item['used_time']			= array('name' => '티켓사용일시');
		$replace_item['coupon_used']		= array('name' => '티켓사용 값어치');
		$replace_item['coupon_remain']		= array('name' => '티켓잔여 값어치');
		$replace_item['used_location']		= array('name' => '티켓 사용처');
		$replace_item['confirm_person']		= array('name' => '티켓사용 확인자');
		$replace_item['goods_name']			= array(
			'name'	=> '티켓상품',
			'etc'	=> '여러 개의 상품인 경우 외 몇 건으로 표시됨<br />상품명은 치환코드 길이 설정을 따릅니다.'
		);

		$replace_item['repay_item']			= array(
			'name'	=> '취소/반품->환불완료 상품',
			'etc'	=> '여러 개의 상품인 경우 외 몇 건으로 표시됨<br />상품명은 치환코드 길이 설정을 따릅니다.'
		);
		$replace_item['remainSms']			= array('name' => '잔여문자');
		$replace_item['remainAutodeposit']	= array('name' => '자동입금만료일');
		$replace_item['remainGoodsflow']	= array('name' => '잔여택배자동');
		$replace_item['dormancy_du_date']	= array('name' => '휴면예정일');

		$replace_item['usernickname']		= array('name' => '회원 닉네임 (개인회원)');
		$replace_item['userlevel']			= array('name' => '회원 등급 (발송일 기준)');
		$replace_item['userday']			= array('name' => '회원 기념일 (개인회원)');
		$replace_item['userbirthday']		= array('name' => '회원 생일 (개인회원)');
		$replace_item['usermileage']		= array('name' => '회원 보유 적립금 (발송일 기준)');
		$replace_item['userpoint']			= array('name' => '회원 보유 포인트 (발송일 기준)');
		$replace_item['useremoney']			= array('name' => '회원 보유 이머니 (발송일 기준)');
		$replace_item['anniversary']		= array('name' => '회원 기념일');
		$replace_item['coupon_count']		= array('name' => '만기 할인쿠폰 갯수');
		$replace_item['mileage_rest']		= array('name' => '다음달 소멸 적립금');
		$replace_item['mypage_short_url']	= array('name' => '마이페이지 바로가기<br/>(메일은 본문에만 사용가능)');

		# 치환코드 정의 END ##############################################

		foreach ($rpl_code_arr as $msg_code => $rpl_arr){
			foreach ($rpl_arr as $k => $rpl_code){
				$use_replace_code[$msg_code][$rpl_code]['name'] = $replace_item[$rpl_code]['name'];
				if ($replace_item[$rpl_code]['etc']){
					$use_replace_code[$msg_code][$rpl_code]['etc'] = $replace_item[$rpl_code]['etc'];
				}
			}
		}

		$msg_title = '신규 메세지';
		if ($_POST['msg_code']){
			$scParams['msg_code']	= $_POST['msg_code'];
			$template_info	= $this->kakaotalkmodel->get_template($scParams, false);
			//템플릿 수정 후 재검수 요청 시에는 치환코드 사용 불가(최초 등록 시에만 사용 가능)
			$replace_text = array('#{shopDomain}','#{mypage_short_url}');
			if(in_array($template_info[0]['kkoLinkPc_arr'][0], $replace_text)){
				unset($template_info[0]['kkoLinkPc_arr'][0]);
			}
			$template		= $template_info[0];
			$msg_title		= $template['msg_txt'];
		}

		// 사용자 정보 추출
		$kakaotalk_config = $this->kakaotalkmodel->get_service();

		$this->template->assign('kakaotalk_config',$kakaotalk_config);
		$this->template->assign('use_replace_code',$use_replace_code);
		$this->template->assign('msg_title',$msg_title);
		$this->template->assign('template',$template);
		$this->template->define(array('tpl'=>$file_path));
		$this->template->print_("tpl");
	}

	// 카카오톡 충전
	public function kakaotalk_charge(){
		$auth = $this->authmodel->manager_limit_act('kakaotalk_view');
		if(!$auth){
			pageBack("권한이 없습니다.");
			exit;
		}

		$this->admin_menu();
		$this->tempate_modules();
		$file_path	= $this->template_path();
		$this->load->model('kakaotalkmodel');

		// SMS 설정 정보 호출
		include_once $_SERVER['DOCUMENT_ROOT']."/app/libraries/sms.class.php";
		$auth = config_load('master');
		$sms_id = $this->config_system['service']['sms_id'];
		$sms_api_key = $auth['sms_auth'];
		$gabiaSmsApi = new gabiaSmsApi($sms_id,$sms_api_key);
		$limit	= $gabiaSmsApi->getSmsCount();

		// 카카오 설정정보 호출
		$data['getType']	= 'C';
		$data['year']		= ($_GET['src_year']) ? $_GET['src_year'] : date('Y');
		$kakaotalk_info		= $this->kakaotalkmodel->get_charge_log($data);
		$kko_log_list		= $kakaotalk_info['chargeList'];
		$kakaotalk_config	= $kakaotalk_info['serviceInfo'];

		$this->template->assign('sms_count',$limit);
		$this->template->assign('kakaotalk_count',$kakaotalk_config['kt_quantity']);
		$this->template->assign('kakaotalk_config',$kakaotalk_config);
		$this->template->assign('log_list',$kko_log_list);
		$this->template->assign('tab3','-on');
		$this->template->define('top_menu',$this->skin.'/member/kakaotalk_top_menu.html');
		$this->template->define(array('tpl'=>$file_path));
		$this->template->print_("tpl");
	}

	// 카카오톡 발송 내역
	public function kakaotalk_log(){
		$auth = $this->authmodel->manager_limit_act('kakaotalk_view');
		if(!$auth){
			pageBack("권한이 없습니다.");
			exit;
		}

		$this->admin_menu();
		$this->tempate_modules();
		$file_path	= $this->template_path();
		$this->load->model('kakaotalkmodel');

		// 템플릿 검색용 템플릿 번호 추출
		unset($scParams);
		$template_list = $this->kakaotalkmodel->get_template($scParams,false);
		// 설정된 템플릿이 아닌 사용중인 템플릿 코드 리스트 반환(승인 거절된 템플릿 제외)
		$template_list = $this->kakaotalkmodel->availableTemplateList($template_list);
	
		$msg_type_arr = [];
		if (is_array($template_list) === true) {
			foreach ($template_list as $k => $template) {
				$msg_type_arr[$template['msg_code']] = $template['msg_txt'];
			}
		}

		// 카카오 발송내역 호출
		$sc	= $_GET;
		$sc['getType']	= 'D';
		$sc['s_date']	= ($sc['s_date']) ? $sc['s_date'] : date('Y-m-d', strtotime('-7 day'));
		$sc['e_date']	= ($sc['e_date']) ? $sc['e_date'] : date('Y-m-d');
		$sc['page']		= (isset($sc['page']))		? intval($sc['page'])		: '0';
		$sc['perpage']	= (isset($sc['perpage']))	? intval($sc['perpage'])	: '20';
		if(isset($sc['mobile']))	$sc['mobile']	= str_replace('-','',$sc['mobile']);
		if($sc['s_date'] > $sc['e_date']){
			pageBack("검색기간이 잘못 지정되었습니다.");
			exit;
		}
		if($sc['s_date'] < date('Y-m-d', strtotime($sc['e_date'] . ' -3 month'))){
			pageBack("최대 검색기간은 3개월입니다.");
			exit;
		}
		if($sc['s_date'] < date('Y-m-d', strtotime('-1 year'))){
			pageBack("최근 1년 이내만 검색가능합니다.");
			exit;
		}
		$send_log		= $this->kakaotalkmodel->get_send_log($sc);

		if($send_log['sendList']['logUnit']['seq']){
			$send_log_list[] = $send_log['sendList']['logUnit'];
		}else if($send_log['sendList']['logUnit'][0]){
			$send_log_list = $send_log['sendList']['logUnit'];
		}else{
			unset($send_log_list);
		}

		$no = $send_log['total'] - ( $sc['page'] / $sc['perpage'] * $sc['perpage'] );
		foreach($send_log_list as &$data) {
			$data['no'] = $no;
			$no--;
		}

		// 페이징
		$paginlay = pagingtag($send_log['total'],$sc['perpage'],getPageUrl($this->file_path).'?', getLinkFilter('',array_keys($sc)) );
		if(empty($paginlay))$paginlay = '<p><a class="on red">1</a><p>';
		$this->template->assign('templateList', $template_list);
		$this->template->assign('msg_type_arr', $msg_type_arr);
		$this->template->assign('sendList', $send_log_list);
		$this->template->assign('total', $send_log['total']);
		$this->template->assign('pagin', $paginlay);
		$this->template->assign('sc', $sc);
        //관리자 로그 수집 데이터
        if($send_log['total'] > 0){
            $searchcount = $send_log['total'];
        } else {
            $searchcount = 0;
        }
        $this->template->assign('searchcount', $searchcount);

		$this->template->assign('tab4','-on');
		$this->template->define('top_menu',$this->skin.'/member/kakaotalk_top_menu.html');
		$this->template->define(array('tpl'=>$file_path));
		$this->template->print_("tpl");
	}

		// 카카오알림톡 발송 로그 상세
	public function kakaotalk_log_detail(){

		$auth = $this->authmodel->manager_limit_act('kakaotalk_view');
		if(!$auth){
			pageBack("권한이 없습니다.");
			exit;
		}

		$this->admin_menu();
		$this->tempate_modules();
		$file_path	= $this->template_path();
		$this->load->model('kakaotalkmodel');

		$params['uid']		= $_POST['uid'];
		$params['date']		= $_POST['date'];
		$params['getType']	= 'L';

		$json_data			= $this->kakaotalkmodel->get_send_log_detail($params);
		$log_res			= json_decode($json_data, true);
		$log_detail			= $log_res['detail'];

		// 발송상황 매칭
		if($log_detail['msg_code']){
			$params['msg_code']		= $log_detail['msg_code'];
		}else{
			$params['kkoBizCode']	= $log_detail['template'];
		}
		$template_info				= $this->kakaotalkmodel->get_template($params, false);
		$log_detail['msg_txt']		= $template_info[0]['msg_txt'];

		// 버튼 arr
		$log_detail['buttons_arr']	= json_decode($log_detail['buttons'],true);

		$this->template->assign('log_detail',$log_detail);
		$this->template->define(array('tpl'=>$file_path));
		$this->template->print_("tpl");
	}

	// 카카오알림톡 결제
	public function kakaotalk_payment(){
		$this->admin_menu();
		$this->tempate_modules();
		$file_path	= $this->template_path();
		$mall_id	= isset($mall_id) ? $mall_id : $this->config_system['service']['cid'];
		$domain		= isset($this->config_system['subDomain']) ? $this->config_system['subDomain'] : $_SERVER['SERVER_NAME'];
		$arr_domain	= explode(".", $domain);
		if ($arr_domain[0]=="www") {
			$domain = substr($domain, 4);
		}
		$kakaotalk_call = "req_domain=".$domain."&req_mallid=".$mall_id."&req_type=KAKAOTALK&req_url=/myhg/mylist/spec/firstmall/kakaotalk/index.php";
		$kakaotalk_call = makeEncriptParam($kakaotalk_call);

		$this->template->assign('param',$kakaotalk_call);
		$this->template->define(array('tpl'=>$file_path));
		$this->template->print_("tpl");
	}

	// 카카오알림톡 메세지 추출 AJAX
	public function kakaotalk_template_ajax(){
		if ($_POST['kkoBizCode']){
			$this->load->model('kakaotalkmodel');
			$scParams['kkoBizCode']	= $_POST['kkoBizCode'];
			$template_info = $this->kakaotalkmodel->get_template($scParams, false);

			if ($template_info[0]){
				foreach($template_info[0]['comments_arr'] as $k => $v){
					$template_info[0]['comments_arr'][$k]['content'] = nl2br($v['content']);
				}
				echo json_encode($template_info[0]);
			}
		}else{
			// done no msg
		}
	}

	# KAKAO TALK FNC ADD - END -

	public function excel_download(){
		redirect('/admin/excel_spout/excel_download?category=3&searchflag=1');
	}

	public function withdrawal_pop()
	{
		$this->admin_menu();
		$this->tempate_modules();
		$file_path	= $this->template_path();

		$member_seq = $_GET['member_seq'];
		if($member_seq){
			$this->load->model('membermodel');
			$data = $this->membermodel->get_member_data($member_seq);
			$this->template->assign($data);
		}

		$this->template->define(array('tpl'=>$file_path));
		$this->template->print_("tpl");
	}

	public function marketing_agree_log()
	{
	    $member_seq = $_GET['member_seq'];

	    if ($member_seq<0) {
	        return false;
	    }

	    $this->load->model('membermodel');
	    $res = $this->membermodel->get_member_marketing_send_log($member_seq);

	    $totalCount = count($res);
	    foreach($res as $k => $v){
	        $res[$k]['no'] = $totalCount--;
	    }

	    $file_path	= $this->template_path();

	    $this->template->assign('res', $res);
	    $this->template->define(array('tpl'=>$file_path));
	    $this->template->print_("tpl");
	}


	/*
	  [공용] openDialog 선택형 회원 등급 리스트
	  @2020.02.18 pjm
	*/
	public function gl_select_member_grade(){

		$this->load->model('membermodel');

		$sc				= array();
		$sc['orderby']	= ($_GET['orderby']) ? $_GET['orderby']:'order_sum_price';
		$sc['sort']		= ($_GET['sort']) ? $_GET['sort']:'DESC';
		$sc['page']		= (!empty($_GET['page'])) ?	intval($_GET['page']):0;
		$sc['perpage']	= ($_GET['perpage']) ? intval($_GET['perpage']):'10';
		if($_GET['issued_seq']) $sc['issued_seq']	= $_GET['issued_seq'];

		if($_GET['select_lists']){
			$sc['select_lists'] = explode("|",$_GET['select_lists']);
		}
		$member_grade 	= $this->membermodel->get_member_group_list($sc);

		$file_path = str_replace("gl_select_member_grade.html","_gl_select_member_grade.html",$this->template_path());
		$this->template->define(array('tpl'=>$file_path));
		$this->template->assign($member_grade);
		$this->template->assign('sc',$sc);
		$this->template->print_("tpl");
	}

	/*
	  [공용] openDialog 선택형 회원  리스트
	  @2020.02.27 pjm
	*/
	public function gl_select_member()
	{
		/* 관리자 권한 체크 : 시작 */
		$auth = $this->authmodel->manager_limit_act('coupon_act');
		if(!$auth){
			$callback = "";
			openDialogAlert($this->auth_msg,400,140,'parent',$callback);
			exit;
		}
		/* 관리자 권한 체크 : 끝 */

		$this->load->model('membermodel');
		if($_POST['issued_type'] == "promotion"){
			$this->load->model('promotionmodel');
		}else{
			$this->load->model('couponmodel');
		}

		$searchfield = array("all"			=>"전체"
							,"A.userid"		=>"아이디"
							,"A.user_name"	=>"이름"
							,"A.email"		=>"이메일"
							,"A.phone"		=>"전화번호"
							,"A.cellphone"	=>"핸드폰"
							,"A.address"	=>"주소"
							);

		$sc = $_POST;
		$this->template->assign(array('searchfield'=>$searchfield,'sc'=>$sc));

		$group_arr = $this->membermodel->find_group_list();
		$this->template->assign('group_arr',$group_arr);

		$file_path = str_replace("gl_select_member.html","_gl_select_member.html",$this->template_path());
		$this->template->define(array('tpl'=>$file_path));
		$this->template->print_("tpl");
	}


}

/* End of file member.php */
/* Location: ./app/controllers/admin/member.php */