<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');
require_once(APPPATH ."controllers/base/admin_base".EXT);

class promotion extends admin_base {

	public function __construct() {
		parent::__construct();

		$this->admin_menu();
		$this->tempate_modules();
		$this->file_path	= $this->template_path();
		$this->load->model('promotionmodel');
		$this->load->model('membermodel');
		$this->load->library('validation');

		$ispointurl	= '/admin/setting/reserve';//포인트설정페이지
		$this->template->assign('ispoint',$this->isplusfreenot['ispoint']);
		$this->template->assign('ispointurl',$ispointurl);

		/* 회원 그룹 개발시 변경*/
		$groups = "";
		$query = $this->db->query("select group_seq,group_name from fm_member_group");
		foreach($query->result_array() as $row){
			$groups[] = $row;
		}
		/******************/
		$this->groups = $groups;
		$this->template->assign(array('groups'=>$groups));

		$this->template->define(array('tpl'=>$this->file_path));

	}

	public function index()
	{
		redirect("/admin/promotion/catalog");
	}

	//프로모션목록
	public function catalog()
	{
		$aGetParams = $this->input->get();

		// validation
		if ($aGetParams) {
			$this->validation->set_data($aGetParams);
			$this->validation->set_rules('id', 'ID', 'trim|string|xss_clean');
			$this->validation->set_rules('perpage', '페이지갯수', 'trim|numeric|xss_clean');
			$this->validation->set_rules('page', '페이지', 'trim|numeric|xss_clean');
			$this->validation->set_rules('orderby', '정렬', 'trim|string|xss_clean');
			$this->validation->set_rules('search_text', '검색어', 'trim|string|xss_clean');
			$this->validation->set_rules('sdate', '시작일', 'trim|string|xss_clean');
			$this->validation->set_rules('edate', '종료일', 'trim|string|xss_clean');
			$this->validation->set_rules('promotionType1', '일반 코드', 'trim|string|xss_clean');
			$this->validation->set_rules('promotionType2', '일반 배송비 코드', 'trim|string|xss_clean');
			$this->validation->set_rules('promotionType3', '개별 코드', 'trim|string|xss_clean');
			$this->validation->set_rules('promotionType4', '개별 배송비 코드', 'trim|string|xss_clean');
			if ($this->validation->exec() === false) {
				show_error($this->validation->error_array['value']);
			}
		}

		serviceLimit('H_FR','process');
		### SEARCH
		$sc		= $this->input->get();
		if ($sc['search_text'])
		{
			$sc['search_text'] = trim($sc['search_text']);
			$sc['search_text']= stripslashes(htmlspecialchars($sc['search_text']));
		}
		$sc['orderby']		= (!empty($sc['orderby'])) ?		$sc['orderby']:'promotion_seq';
		$sc['sort']			= (!empty($sc['sort'])) ?			$sc['sort']:'desc';
		$sc['page']			= (isset($sc['page']) && $sc['page'] > 1) ? intval($sc['page']):'0';
		$sc['perpage']		= (!empty($sc['perpage'])) ?	intval($sc['perpage']):10;

		$this->template->assign('checked',$checked);
		$result					= $this->promotionmodel->promotion_list($sc);

		### PAGE & DATA
		$sc['searchcount']		= $result['page']['searchcount'];
		$sc['total_page']		= @ceil($sc['searchcount']	 / $sc['perpage']);
		$sc['totalcount']		= $result['page']['totalcount'];

		$this->template->assign('sc',$sc);

		$idx = 0;
		foreach($result['record'] as $key=>$datarow){

			$datarow['date']			= substr($datarow['regist_date'],0,16);//등록일($datarow['update_date'])?substr($datarow['update_date'],2,14):

			//기획정의.
			if(in_array($this->config_system['basic_currency'],array("KRW","JPY"))){
				$datarow['max_percent_goods_sale']	= (int)$datarow['max_percent_goods_sale'];
				$datarow['won_goods_sale']			= (int)$datarow['won_goods_sale'];
			}

			$datarow['limit_goods_price'] = get_currency_price($datarow['limit_goods_price']);

			$datarow['downloaddate']	= '-';
			$datarow['pointtitle'] = ( $datarow['type'] == 'point' )?'포인트(P) '.get_currency_price($datarow['promotion_point'],2,'basic').' 이상':'-';

			if( $datarow['issue_priod_type'] == 'date' ) {
				$datarow['issuedate']	= substr($datarow['issue_startdate'],0,12).' <br> '.substr($datarow['issue_enddate'],0,12);
			}else{
				$datarow['issuedate']	= '발급일로부터 '.number_format($datarow['after_issue_day']).'일';
			}

			if( strstr($datarow['type'],'shipping')  ){//배송비
				$datarow['salepricetitle']	= ($datarow['sale_type'] == 'shipping_free' ) ? '무료, 최대 '.get_currency_price($datarow['max_percent_shipping_sale'],2,'basic'): '배송비 '.get_currency_price($datarow['won_shipping_sale'],2,'basic').' 할인';//
			}elseif($datarow['type'] == 'promotion_point' ){//개별코드 포인트
				$datarow['salepricetitle']	='포인트 '.get_currency_price($datarow['promotion_point'],2,'basic').' 지급';
			}else{
				$datarow['salepricetitle']	= ($datarow['sale_type'] == 'percent' ) ? $datarow['percent_goods_sale'].'% 할인, 최대 '.get_currency_price($datarow['max_percent_goods_sale'],2,'basic'): get_currency_price($datarow['won_goods_sale'],2,'basic')." 할인";
			}
			$datarow['issuetypetitle'] = ( strstr($datarow['type'],'shipping')  )?'배송비':'상품';

			$dsc['whereis'] = ' and promotion_seq='.$datarow['promotion_seq'];
			$downloadtotal = $this->promotionmodel->get_download_total_count($dsc);
			$datarow['downloadtotal']	= number_format($downloadtotal);//발급수

			$usc['whereis'] = ' and promotion_seq='.$datarow['promotion_seq'].' and use_status = \'used\' ';
			$usetotal = $this->promotionmodel->get_download_total_count($usc);

			$datarow['usetotal']			= number_format($usetotal);//사용건수

			$datarow['issueimg'] = ( strstr($datarow['type'],'promotion') )?'promotion':'promotionnone';
			$datarow['issueimgalt'] = ( strstr($datarow['type'],'promotion') )?'공용 코드':'1회용 코드';
			if( strstr($datarow['type'],'admin') ){//직접발급시
				$datarow['issuebtn']	= (( $datarow['issue_priod_type'] == 'date' && str_replace("-","", substr($datarow['issue_enddate'],0,10)) < date("Ymd"))) ? '':"<button type='button' class='resp_btn active' onClick=\"gCouponIssued.open({'issued_type':'promotion','issued_seq':'".$datarow['promotion_seq']."','download_limit':'".$datarow['download_limit']."','divSelectLay':'lay_promotion_issued'})\">발급</button>";
			}else{
				$datarow['issuebtn']	= $this->promotionmodel->promotionTypeTitle[$datarow['type']];
			}

			$result['record'][$key] = $datarow;
		}

		$this->load->model('providermodel');
		$provider = $this->providermodel->provider_goods_list();
		$this->template->assign('provider',$provider);

		###
		if(isset($result)) $this->template->assign($result);

		$this->template->assign('scObj',json_encode($sc));

		$this->template->print_("tpl");
	}

	public function regist()
	{
		$this->load->helper('file');

		$no 			= $this->input->get("no");

		$codeall		= str_replace("/regist.html","/codeall.html",$this->file_path);
		$codeone		= str_replace("/regist.html","/code.html",$this->file_path);
		$tpl_sourceall 	= read_file(ROOTPATH."admin/skin/".$codeall);
		$tpl_source 	= read_file(ROOTPATH."admin/skin/".$codeone);

		if(isset($no)) $tpl_source = str_replace("{프로모션고유번호}",$no,$tpl_source);
		$this->template->assign(array('promocodeallhtml'=>$tpl_sourceall,'promocodehtml'=>$tpl_source));

		if(isset($no)) {

			$this->load->model('goodsmodel');
			$this->load->model('categorymodel');
			$this->load->model('brandmodel');
			$this->load->model('providermodel');

			$promotions 		= $this->promotionmodel->get_promotion($no);
			$issuegoods 		= $this->promotionmodel->get_promotion_issuegoods($no);
			$issuebrand 		= $this->promotionmodel->get_promotion_issuebrand($no);
			$issuecategorys		= $this->promotionmodel->get_promotion_issuecategory($no);

			//기획정의.금액 입력 시, 화폐별 소수점 입력은 아래의 내용을 따른다
			//포인트의 경우 소수점 이하 입력 불가, 마일리지는 기본 화폐별로 2번의 정책에 따름
			if(in_array($this->config_system['basic_currency'],array("KRW","JPY"))){
				$promotions['max_percent_goods_sale']	= (int)$promotions['max_percent_goods_sale'];
				$promotions['won_goods_sale']			= (int)$promotions['won_goods_sale'];
				$promotions['limit_goods_price']		= (int)$promotions['limit_goods_price'];
			}

			if($promotions['provider_list']){
				$promotions['provider_name_list'] = $this->providermodel->get_provider_select_list($promotions['provider_list']);
			}

			if(($issuegoods)){
				$issuegoods = $this->goodsmodel->get_select_goods_list($issuegoods);
				$this->template->assign(array('issuegoods'=>$issuegoods));
			}

			if(($issuebrand)) {
				foreach($issuebrand as $key =>$data) $issuebrand[$key]['brand'] = $this->brandmodel -> get_brand_name($data['brand_code']);
				$this->template->assign(array('issuebrands'=>$issuebrand));
			}

			if($issuecategorys){
				foreach($issuecategorys as $key =>$data) $issuecategorys[$key]['category'] = $this->categorymodel -> get_category_name($data['category_code']);
				$this->template->assign(array('issuecategorys'=>$issuecategorys));
			}

			$dsc['whereis'] = ' and promotion_seq='.$promotions['promotion_seq'];
			$downloadtotal = $this->promotionmodel->get_download_total_count($dsc);
			$promotions['downloadtotal']	= 0;//발급수-> 수정가능하도록 수정@2012-06-08
			$promotions['downloadtotalbtn']	= number_format($downloadtotal);

			if($promotions['promotion_type'] == 'file' ){//직접발급시
				$filepromotiontotal = $this->promotionmodel->get_promotioncode_input_item_total_count($promotions['promotion_seq']);
				$promotions['filepromotiontotal']	= number_format($filepromotiontotal);//사용건수
			}elseif( $promotions['promotion_type'] == 'random' ) {
				$codesc['whereis'] = ' and promotion_seq = "'.$promotions['promotion_seq'].'" ';
				$promotioncodetotal = $this->promotionmodel->get_promotioncode_total_count($codesc);
				$promotions['filepromotiontotal']	= number_format($promotioncodetotal);//사용건수
			}

			$usc['whereis'] = ' and promotion_seq='.$promotions['promotion_seq'].' and use_status = \'used\' ';
			$usetotal = $this->promotionmodel->get_download_total_count($usc);
			$promotions['usetotalbtn']	= number_format($usetotal);//사용건수

			$promotions['node_text_title'] = str_replace("[프로모션코드설명]",$promotions['promotion_desc'],str_replace("[프로모션코드]",$promotions['promotion_input_serialnumber'],$promotions['node_text']));

			if( strstr($promotions['type'],'admin')){//직접발급시
				$adminissuebtn	= (( $promotions['issue_priod_type'] == 'date' && str_replace("-","", substr($promotions['issue_enddate'],0,10)) < date("Ymd"))) ? false:true;
				$this->template->assign(array('adminissuebtn'=>$adminissuebtn));
			}

			$node_text_normalar = @explode("^^",$promotions['node_text_normal']);
			$promotions['node_text_normal'] = $node_text_normalar[0];
			$promotions['node_text_normal_style'] = font_decoration_attr($promotions['node_text_normal'],'css','style');
			$node_text_normal_url_orgin = ($node_text_normalar[1]) ? json_decode($node_text_normalar[1]) : array();
			$promotions['node_text_normal_url'] = $node_text_normal_url_orgin->href;
			$promotions['node_text_normal_url_target'] = $node_text_normal_url_orgin->target;

			$this->template->assign(array('promotion'=>$promotions));

		}

		$promotionsJson = array();
		$jsonData		= array('promotion_seq','type','download_limit','download_limit_ea','downloadLimit_member','mainshow','issue_type','duplication_use','promotion_type');
		foreach($jsonData as $key){ $promotionsJson[$key] = addslashes($promotions[$key]); }
		$this->template->assign(array('promotionsJson'=>$promotionsJson));

		$this->template->assign("promotionNo",$no);
		$this->template->assign("limittitle","{상품 할인가(판매가) x 수량} + {좌동} + {좌동}...");
		$this->template->assign("promotion_code_form",get_interface_sample_path("20210510/promotion_form.xls"));
		$this->template->print_("tpl");
	}


	//엑셀등록하기
	public function promotion_excel()
	{
		$promotion_seq = (int) $_GET['no'];
		$promotions 		= $this->promotionmodel->get_promotion($promotion_seq);
		$this->template->assign(array('promotion'=>$promotions));
		$this->template->assign('saveinterval',3);//3초 대기
		$this->template->print_("tpl");
	}

	//인증번호 보기
	public function promotion_code()
	{
		$promotion_seq = (int) $_GET['no'];
		$promotions 		= $this->promotionmodel->get_promotion($promotion_seq);
		$this->template->assign(array('promotion'=>$promotions));
		$this->template->print_("tpl");
	}

	//발급내역
	public function download()
	{
		$no 			= $this->input->get('no');
		$promotions 	= $this->promotionmodel->get_promotion($no);
		$promotions['downloaddatetitle'] = (strstr($promotions['type'],'offline'))?'인증일':'발급일';

		$this->template->assign(array('promotion'=>$promotions));

		//list($result,$sc) = $this->downloadlist();

		$this->template->assign("sc",$sc);
		//$this->template->assign($result);
		$this->template->print_("tpl");
	}

	//프로모션발급 > 회원검색페이지
	public function download_member()
	{
		$no = (int) $_GET['no'];
		$promotions 		= $this->promotionmodel->get_promotion($no);
		$this->template->assign(array('promotion'=>$promotions));

		### GROUP
		$this->load->model('membermodel');
		$group_all = $this->membermodel->find_group_list();
		$this->template->assign('group_arr',$group_all);
		$this->template->print_("tpl");
	}


	//프로모션발급 > 회원검색리스트
	public function download_member_list()
	{
		$no = (int) $_POST['no'];
		$promotions 		= $this->promotionmodel->get_promotion($no);
		$this->template->assign(array('promotion'=>$promotions));
		$this->load->model('membermodel');

		### SEARCH
		$sc = $_POST;
		$sc['search_text']	= ($sc['search_text'] == '이름, 아이디, 이메일, 전화번호, 핸드폰(뒷자리4), 주소') ? '':$sc['search_text'];
		$sc['orderby']		= 'A.member_seq';
		$sc['sort']			= (isset($_POST['sort'])) ?		$_POST['sort']:'desc';
		$sc['perpage']		= (!empty($_POST['perpage'])) ?	intval($_POST['perpage']):10;
		$sc['page']			= (!empty($_POST['page'])) ?		intval(($_POST['page'] - 1) * $sc['perpage']):0;
		//$sc['groupsar']		= $promotiongroupsar;

		### MEMBER
		$data = $this->membermodel->popup_member_list($sc);
		// 페이징 처리 위한 변수 셋팅
		$page =  get_current_page($sc);
		$pagecount =  get_page_count($sc, $data['count']);

		### PAGE & DATA
		$sc['searchcount']	 = $data['count'];
		$sc['total_page']		= @ceil($sc['searchcount']/ $sc['perpage']);
		$sc['totalcount']		= $this->membermodel->get_item_total_count();

		$idx = 0;
		$html = $this->getdownload_member_html($data, $sc,  $page, $promotions);
		if(!empty($html)) {
			$result = array( 'content'=>$html, 'searchcount'=>$sc['searchcount'], 'totalcount'=>$sc['totalcount'], 'nowpage'=>(($sc['page']/$sc['perpage'])+1), 'total_page'=>$sc['total_page'], 'page'=>$page, 'pagecount'=>(int)$pagecount);
		}else{
			$result = array( 'content'=>"", 'searchcount'=>$sc['searchcount'], 'totalcount'=>$sc['totalcount'], 'nowpage'=>(($sc['page']/$sc['perpage'])+1), 'total_page'=>$sc['total_page'], 'page'=>$page, 'pagecount'=>(int)$pagecount);
		}
		echo json_encode($result);
		exit;
	}

	//회원검색 > 발급내역
	function getdownload_member_html($data, $sc, $page, $promotions)
	{
		$html = '';
		$i = 0;
		foreach($data['result'] as $datarow){
			// 프로모션 정보 확인
			$download_promotions = $this->promotionmodel->get_admin_download($datarow['member_seq'], $promotions['promotion_seq']);
			$class = ($download_promotions)?" class='bg-gray' ":"";

			$datarow['number'] = $data['count'] - ( ( $page -1 ) * $sc['perpage'] + $i + 1 ) + 1;
			$datarow['type']	= $datarow['business_seq'] ? '기업' : '개인';
			$html .= '<tr  '.$class.' >';
			if($download_promotions) {
				$html .= '	<td> </td>';
			}else{
				$html .= '	<td><span class="resp_checkbox"><input type="checkbox" onclick="chkmember(this);" name="member_chk[]" value="'.$datarow['member_seq'].'" cellphone="'.$datarow['cellphone'].'" email="'.$datarow['email'].'"  userid="'.$datarow['userid'].'"  user_name="'.$datarow['user_name'].'"  class="member_chk" '.$disabled.'/></span></td>';
			}
			$html .= '	<td>'.$datarow['number'].'</td>';
			$html .= '	<td>'.$datarow['type'].'</td>';
			$html .= '	<td onClick="open_crm_summary(this,\''.$datarow['member_seq'].'\',\'\',\'right\');"><div class="resp_btn_txt v2">'.$datarow['userid'].'</div></td>';
			$html .= '	<td>'.$datarow['user_name'].'</td>';
			$html .= '	<td>'.$datarow['email'].'</td>';
			$html .= '	<td>'.$datarow['cellphone'].'</td>';
			$html .= '	<td>'.$datarow['phone'].'</td>';
			$html .= '</tr>';
			$i++;
		}//foreach end

		if($i==0){
			if($sc['search_text']){
				$html .= '<tr ><td class="its-td-align center" colspan="7" >"'.$sc['search_text'].'"로(으로) 검색된 회원이 없습니다.</td></tr>';
			}else{
				$html .= '<tr ><td class="its-td-align center" colspan="7" >회원이 없습니다.</td></tr>';
			}
		}
		return $html;
	}

	//프로모션관리 > 발급내역 -> 검색 : 사용여부 , 사용일, 주문상품(주문번호) 또는 포인트 지급
	public function downloadlist()
	{

		### SEARCH
		$sc					= ($_GET)? $_GET: $_POST;
		$sc['search_text']	= ($sc['search_text'] == '아이디, 이름') ? '':$sc['search_text'];
		$sc['orderby']		= (!empty($sc['orderby'])) ?	$sc['orderby']:'download_seq';
		$sc['sort']			= (!empty($sc['sort'])) ?			$sc['sort']:'desc';
		$sc['page']			= (!empty($sc['page'])) ?	intval(($sc['page'] - 1) * $sc['perpage']):0;
		$sc['perpage']		= ($sc['perpage']) ? intval($sc['perpage']):'10';

		$promotions 		= $this->promotionmodel->get_promotion($sc['no']);
        $result				= $this->promotionmodel->download_list($sc);

		$sc['searchcount']		= $result['page']['searchcount'];
		$sc['total_page']		= @ceil($sc['searchcount']	 / $sc['perpage']);
		$sc['totalcount']		= $result['page']['totalcount'];

		// 페이징 처리 위한 변수 셋팅
		$nowpage	=  get_current_page($sc);	//현재 페이지
		$pagecount	=  get_page_count($sc, $result['page']['searchcount']);

		//발급내역 > 총 할인금액추출
		$promotion_order_saleprice = $this->promotionmodel->get_promotiontotal($sc, $promotions);
		$result['promotion_code_sale'] = $promotion_order_saleprice['promotion_code_sale'];

		### PAGE & DATA
		$result['content']		= $this->getdownloadhtml($result['record'], $sc);
		unset($result['record']);

		$result['nowpage']			= $nowpage;
		$result['pagecount']		= $pagecount;
		$result['totalsaleprcie']	= $promotion_order_saleprice['promotion_code_sale'];

        //관리자 로그 남기기
        $this->load->library('managerlog');
        $this->managerlog->insertData(array('params' => array('coupon_name' => $promotions['promotion_name'], 'searchcount' => $sc['searchcount'])));

		echo json_encode($result);
		exit;

	}

	//프로모션관리 > 발급내역
	function getdownloadhtml($data, $sc)
	{
		$this->load->model('membermodel');
		$this->load->model('ordermodel');
		$html = '';

		foreach($data as $datarow){

			$datarow['date']			= substr($datarow['regist_date'],2,14);//등록일
			$datarow['use_status_title'] = ($datarow['use_status'] == 'used') ? '<span class="blue" >사용함</span>':'<span class="red" >미사용</span>';
			if(str_replace("-","",$datarow['issue_enddate']) < date("Ymd") && $datarow['use_status'] != 'used') $datarow['use_status_title'] = '<span class="gray" >유효기간 만료</span>';//미사용중 기간지남
			$deletebtn = ($datarow['use_status'] == 'used')?' disabled="disabled" ':'';//

			$datarow['use_date']	= ($datarow['use_status'] == 'used') ? substr($datarow['use_date'],2,14):'';
			$class 					= '';

			if($datarow['use_status'] == 'used') {
				$class = 'class="bg-gray"';
				if ( strstr($datarow['type'],'shipping') ) {//배송비할인
					$order_promotion = $this->ordermodel->get_order_shipping_promotion($datarow['member_seq_buy'], $datarow['download_seq']);
					$items 			 = $this->ordermodel->get_item($order_promotion[0]['order_seq']);
					$goods_cnt 		= count($items)-1;
					$goodsinfo 		= ($goods_cnt > 0) ? '<span class="goods_name1">'.$items[0]['goods_name'].'</span> 외'.$goods_cnt.'건':'<span class="goods_name1 orderview blue"  onclick="orderinfo(\''.$order_promotion[0]['order_seq'].'\');"  goods_seq="'.$items[0]['goods_seq'].'" >'.$items[0]['goods_name'].'</span>';

					$datarow['goodsview'] = '<span class="goods_name1 hand orderview bold blue" onclick="orderinfo(\''.$order_promotion[0]['order_seq'].'\');"order_seq="'.$order_promotion[0]['order_seq'].'" >['.$order_promotion[0]['order_seq'].']</span><br/>'.$goodsinfo;
					$datarow['orderInfo'] = $goodsinfo;
					$datarow['promotion_order_saleprice'] = get_currency_price($order_promotion[0]['promotion_order_saleprice'],2,'basic').'&nbsp;';
				} else {
					$order_promotion 		= $this->ordermodel->get_option_promotioncode_item($datarow['member_seq_buy'], $datarow['download_seq']);
					$datarow['goodsview'] 	= ($order_promotion[0])?'<span class="goods_name1 hand orderview bold blue" onclick="orderinfo(\''.$order_promotion[0]['order_seq'].'\');" order_seq="'.$order_promotion[0]['order_seq'].'" >['.$order_promotion[0]['order_seq'].']</span><br/><img src="'.$order_promotion[0]['image'].'" style="width:100px;"/> <br /><span class="goods_name1 hand goodsview bold blue" onclick="goodsinfo(\''.$order_promotion[0]['goods_seq'].'\');"  goods_seq="'.$order_promotion[0]['goods_seq'].'" >'.$order_promotion[0]['goods_name'].'</span>':'';
					$datarow['orderInfo'] 	= ($order_promotion[0])?'<span class="goods_name1 hand goodsview blue" onclick="goodsinfo(\''.$order_promotion[0]['goods_seq'].'\');"  goods_seq="'.$order_promotion[0]['goods_seq'].'">'.$order_promotion[0]['goods_name'].'</span>':'';
					$datarow['promotion_order_saleprice'] = get_currency_price($order_promotion[0]['promotion_order_saleprice'],2,'basic').'&nbsp;';
				}
				if($datarow['order_seq']){
					$datarow['order_seq'] = '<span class="goods_name1 hand orderview blue" onclick="orderinfo(\''.$order_promotion[0]['order_seq'].'\');" order_seq="'.$order_promotion[0]['order_seq'].'" >'.$order_promotion[0]['order_seq'].'</span>';
				}else $datarow['order_seq'] = '-';
			}else $datarow['order_seq'] = '-';

			$datarow['datetitle'] = $datarow['date'];

			if ($datarow['type'] != 'promotion_point') {
				$datarow['limit_goods_price_title'] = get_currency_price($datarow['limit_goods_price'],2,'basic').' 이상 구매 시&nbsp;';//제한금액
			}
			$datarow['issuedate']	= substr($datarow['issue_startdate'],0,12).' ~ '.substr($datarow['issue_enddate'],0,12);//유효기간

			//혜택
			if( strstr($datarow['type'],'shipping') ){//배송비
				$datarow['salepricetitle']	= ($datarow['sale_type'] == 'shipping_free' ) ? '무료, 최대 '.get_currency_price($datarow['max_percent_shipping_sale'],2,'basic'): '배송비 '.get_currency_price($datarow['won_shipping_sale'],2,'basic');//
			}elseif($datarow['type'] == 'promotion_point' ){//개별코드 포인트전환
				$datarow['salepricetitle']	='포인트 '.get_currency_price($datarow['promotion_point'],2,'basic').' 지급';
			}else{
				$datarow['salepricetitle']	= ($datarow['sale_type'] == 'percent' ) ? $datarow['percent_goods_sale'].'% 할인, 최대 '.get_currency_price($datarow['max_percent_goods_sale'],2,'basic'): '판매가격의 '.get_currency_price($datarow['won_goods_sale'],2,'basic');
			}

			if($datarow['member_seq_buy']){
				$memberbuyer = $this->membermodel->get_member_data($datarow['member_seq_buy']);//구매회원정보
				if($memberbuyer){
					$datarow['userid_buy']			= $memberbuyer['userid'];
					$datarow['user_name_buy']	= $memberbuyer['user_name'];
				}else{
					$datarow['user_name_buy'] = '비회원';
				}
			}else{
				$datarow['user_name_buy'] = '';
			}

			if($datarow['userid']){
				$datarow['user_name'] = ($datarow['user_name'])?$datarow['user_name']:'';
			}else{
				$datarow['user_name'] = ($datarow['user_name'])?$datarow['user_name']:'비회원';
			}

			$html .= '<tr '.$class.'>';
			if($datarow['use_status'] != 'used'){
				$html .= '	<td><label class="resp_checkbox"><input type="checkbox" name="del[]" value="'.$datarow['download_seq'].'"  class="checkeds"  '.$deletebtn.'/></label></td>';
			}else{
				$html .= '	<td>&nbsp;</td>';
			}
			$html .= '	<td>'.$datarow['_no'].'</td>';
			if($datarow['member_seq']){
				$html .= '	<td><span class="hand blue" onClick="open_crm_summary(this,\''.$datarow['member_seq'].'\',\'\',\'right\');" >'.htmlentities($datarow['userid']).'</span></td>';
			}else{
				$html .= '<td>&nbsp;</td>';
			}
			$html .= '	<td>'.$datarow['user_name'].'</td>';
			$html .= '	<td>'.$datarow['issuedate'].'</td>';
			$html .= '	<td>'.$datarow['promotion_input_serialnumber'].'</td>';
			$html .= '	<td>'.$datarow['use_status_title'].'</td>';
			$html .= '	<td>'.$datarow['use_date'].'</td>';
			if($datarow['member_seq_buy']){
				$html .= '	<td><span class="hand blue" onClick="open_crm_summary(this,\''.$datarow['member_seq_buy'].'\',\'\',\'right\');" >'.htmlentities($datarow['userid_buy']).'<br/>'.$datarow['user_name_buy'].'</span></td>';
			}else{
				$html .= '	<td>'.$datarow['user_name_buy'].'</td>';
			}
			$html .= '	<td class="right">'.$datarow['promotion_order_saleprice'].'</td>';
			$html .= '	<td>'.$datarow['date'].'</td>';
			$html .= '	<td>'.$datarow['order_seq'].'</td>';
			$html .= '	<td>'.$datarow['orderInfo'].'</td>';
			$html .= '</tr>';

		}//foreach end

		if(!$data){
			if($sc['search_text']){
				$html .= '<tr ><td colspan="13" >"'.$sc['search_text'].'"로(으로) 검색된 프로모션내역이 없습니다.</td></tr>';
			}else{
				$html .= '<tr ><td colspan="13" >할인 코드 발급/사용 내역이 없습니다.</td></tr>';
			}
		}
		return $html;
	}

	//관리자 > 개별회원 프로모션보유내역 레이어
	public function member_promotion_list(){

		$this->load->model('ordermodel');

		if($_GET['tab'] ==1 || !$_GET['tab'] ){
			unset($sc);
			### SEARCH
			$sc['orderby']		= (!empty($_GET['orderby'])) ?	$_GET['orderby']:'download_seq';
			$sc['sort']				= (!empty($_GET['sort'])) ?			$_GET['sort']:'desc';
			$sc['page']			= (!empty($_GET['page'])) ?		intval($_GET['page']):0;
			$sc['perpage']		= (!empty($_GET['perpage'])) ?	intval($_GET['perpage']):10;
			$sc['member_seq']	= $_GET['member_seq'];

			$data = $this->promotionmodel->download_list($sc);

			/**
			 * count setting
			**/
			$sc['searchcount']	 = $data['count'];
			$sc['total_page']	 = @ceil($sc['searchcount']	 / $sc['perpage']);
			$sc['totalcount']	 = $this->promotionmodel->get_item_total_count($sc);
			$idx = 0;
			foreach($data['result'] as $datarow){
				$idx++;
				$datarow['number']	= $sc['searchcount']	 - ( ($sc['page'] -1 ) * 1 + $idx + 1) + 1;
				$datarow['date']			= substr($datarow['regist_date'],2,14);//발급일
				$datarow['use_status_title'] = ($datarow['use_status'] == 'used') ? '사용함':'미사용';

				if(str_replace("-","",$datarow['issue_enddate']) < date("Ymd") && $datarow['use_status'] != 'used') $datarow['use_status_title'] = '<span class="gray" >유효기간 만료</span>';//미사용중 기간지남

				$datarow['use_date']			  = ($datarow['use_status'] == 'used') ? $datarow['use_date']:'';

				if($datarow['use_status'] == 'used') {
					if ( strstr($datarow['type'],'shipping') ) {
						$order_promotion = $this->ordermodel->get_order_shipping_promotion($datarow['member_seq'], $datarow['download_seq']);
						$items 				 = $this->ordermodel->get_item($order_promotion['order_seq']);
						$goods_cnt = count($items)-1;
						$goodsinfo = ($goods_cnt > 0) ? '<img src="'.$items[0]['image'].'" /> <br /><span class="goods_name1">'.$items[0]['goods_name'].'</span> 외'.$goods_cnt.'건':'<img src="'.$items[0]['image'].'" /> <br /><span class="goods_name1"  goods_seq="'.$items[0]['goods_seq'].'" >'.$items[0]['goods_name'].'</span>';

						$datarow['goodsview'] = '<span class="goods_name1">['.$order_promotion['order_seq'].']</span><br/>'.$goodsinfo;
					} else {
						if ($datarow['type'] == 'promotion_point') {
							$datarow['goodsview'] = '포인트 '.get_currency_price($datarow['promotion_point'],2,'basic').' 지급';
						}else{
							$order_promotion = $this->ordermodel->get_option_promotioncode_item($datarow['member_seq'], $datarow['download_seq']);
							$datarow['goodsview'] = ($order_promotion[0])?'<span class="goods_name1">['.$order_promotion[0]['order_seq'].']</span><br/><img src="'.$order_promotion[0]['image'].'" /> <br /><span class="goods_name1" goods_seq="'.$order_promotion[0]['goods_seq'].'" >'.$order_promotion[0]['goods_name'].'</span>':'';
						}
					}
				}

				$datarow['limit_goods_price'] = get_currency_price($datarow['limit_goods_price']);
				$datarow['downloaddate']	.='<br>'.substr($datarow['issue_startdate'],2,10).' ~ '.substr($datarow['issue_enddate'],2,10);

				if( strstr($datarow['type'],'shipping')  ){//배송비
					$datarow['salepricetitle']	= ($datarow['sale_type'] == 'shipping_free' ) ? '무료, 최대 '.get_currency_price($datarow['max_percent_shipping_sale'],2,'basic'): '배송비 '.get_currency_price($datarow['won_shipping_sale'],2,'basic');//
				}else{
					$datarow['salepricetitle']	= ($datarow['sale_type'] == 'percent' ) ? $datarow['percent_goods_sale'].'%, 최대 '.get_currency_price($datarow['max_percent_goods_sale'],2,'basic'): '판매가격의 '.get_currency_price($datarow['won_goods_sale'],2,'basic');
				}
				$datarow['issuebtn']	= $this->promotionmodel->promotionTypeTitle[$datarow['type']];

				$dataloop[] = $datarow;
			}//
		}else{
			### SEARCH
			unset($sc);
			$sc['page']				= (!empty($_GET['page'])) ?		intval($_GET['page']):0;
			$sc['perpage']			= (!empty($_GET['perpage'])) ?	intval($_GET['perpage']):10;
			$sc['today']			= date('Y-m-d',time());

			$this->mdata = $this->membermodel->get_member_data($_GET['member_seq']);//회원정보
			$this->mdata['birthday'] = date("Y").substr($this->mdata['birthday'],4,6);
			$this->mdata['grade_update_date'] = ($this->mdata['grade_update_date'] != '0000-00-00 00:00:00')?substr($this->mdata['grade_update_date'],0,10):substr($this->mdata['regist_date'],0,10);//substr($this->mdata['regist_date'],0,10)
			$data = $this->promotionmodel->get_mypage_download($sc,$this->mdata);

			/**
			 * count setting
			**/
			$sc['searchcount'] = $data['count'];
			$sc['total_page']	 = @ceil($sc['searchcount']	 / $sc['perpage']);
			$sc['totalcount']	 = $this->promotionmodel->get_item_total_count($sc);

			$idx = 0;
			foreach($data['result'] as $datarow){
				$idx++;
				$datarow['number']	= $sc['searchcount']	 - ( ($sc['page'] -1 ) * 1 + $idx + 1) + 1;
				$datarow['date']			= substr($datarow['regist_date'],0,16);//발급일
				$datarow['use_status_title'] = ($datarow['use_status'] == 'used') ? '사용함':'미사용';
				if(str_replace("-","",$datarow['issue_enddate']) < date("Ymd") && $datarow['use_status'] != 'used') $datarow['use_status_title'] = '<span class="gray" >유효기간 만료</span>';//미사용중 기간지남

				$promotions = $this->promotionmodel->get_promotion($datarow['promotion_seq']);
				$datarow['limit_goods_price'] = get_currency_price($promotions['limit_goods_price']);

				if($promotions['type'] == 'birthday' || $promotions['type'] == 'memberGroup' || $promotions['type'] == 'member' ){//직접발급시
					$datarow['downloaddate']	= '발급일로부터 '.number_format($promotions['after_issue_day']).'일';
				}else{
					if( $promotions['issue_priod_type'] == 'date' ) {
						$datarow['downloaddate']	= substr($promotions['issue_startdate'],2,10).' ~ '.substr($promotions['issue_enddate'],2,10);
					}else{
						$datarow['downloaddate']	= '발급일로부터 '.number_format($promotions['after_issue_day']).'일';
					}
				}

				if( strstr($promotions['type'],'shipping') ){//배송비
					$datarow['salepricetitle']	= ($promotions['sale_type'] == 'shipping_free' ) ? '무료, 최대 '.get_currency_price($promotions['max_percent_shipping_sale'],2,'basic'): '배송비 '.get_currency_price($datarow['won_shipping_sale'],2,'basic');//
				}else{
					$datarow['salepricetitle']	= ($promotions['sale_type'] == 'percent' ) ? $promotions['percent_goods_sale'].'%, 최대 '.get_currency_price($promotions['max_percent_goods_sale'],2,'basic'): '판매가격의 '.get_currency_price($promotions['won_goods_sale'],2,'basic');
				}
				$datarow['issuebtn']	= $this->promotionmodel->promotionTypeTitle[$promotions['type']];
				if($promotions['promotion_img'] == '4' && @is_file($this->promotionmodel->copuonupload_dir.$promotions['promotion_image4'])){
					$datarow['downloadbtn']	= $this->promotionmodel->copuonupload_src.$promotions['promotion_image4'];
				}else{
					$datarow['downloadbtn']	= $this->promotionmodel->copuonupload_src.'promotion_0'.$promotions['promotion_img'].'.gif';
				}

				$dataloop[] = $datarow;
			}//
		}
		###
		if(isset($data)) $this->template->assign('loop',$dataloop);
		$paginlay = pagingtagfront($sc['searchcount'],$sc['perpage'],'?tab='.$_GET['tab'].'&member_seq='.$_GET['member_seq'], getLinkFilter('',array_keys($sc)) );
		if(empty($paginlay))$paginlay = '<p><a class="on red">1</a><p>';
		$this->template->assign('downloadtotal',$downloadtotal);//보유프로모션수량
		$this->template->assign('issuestotal',$issuestotal);//다운가능프로모션수량
		$this->template->assign('pagin',$paginlay);
		$this->template->assign('perpage',$sc['perpage']);

		$this->template->print_("tpl");
	}

	public function promotionpage_codeview(){

		$this->load->helper('file');

		if($_GET['mode'] == "all"){
			$codeall= str_replace("/promotionpage_codeview.html","/codeall.html",$this->file_path);
		}else{
			$codeall= str_replace("/promotionpage_codeview.html","/code.html",$this->file_path);
		}

		$tpl_source = read_file(ROOTPATH."admin/skin/".$codeall);
		if(isset($_GET['no'])) $tpl_source = str_replace("{프로모션고유번호}",$_GET['no'],$tpl_source);
		$tpl_source		= str_replace("<","&lt;",str_replace(">","&gt;",$tpl_source));
		$this->template->assign(array('promocodehtml'=>$tpl_source));

		$params = array('mode'=>$_GET['mode']);

		$this->template->assign($params);
		$this->template->print_("tpl");

	}

}

/* End of file promotion.php */
/* Location: ./app/controllers/admin/promotion.php */