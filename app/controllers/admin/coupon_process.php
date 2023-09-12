<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');
require_once(APPPATH ."controllers/base/admin_base".EXT);

class coupon_process extends admin_base {

	public function __construct() {
		parent::__construct();
		$this->load->library(array('validation','pxl'));
		$this->load->model('couponmodel');
	}

	public function index()
	{
		redirect("/admin/coupon/catalog");
	}

	/*
	쿠폰 등록 @20200226
	*/
	public function regist()
	{
		/* 관리자 권한 체크 : 시작 */
		$auth = $this->authmodel->manager_limit_act('coupon_act');
		if(!$auth){
			$callback = "";
			openDialogAlert($this->auth_msg,400,140,'parent',$callback);
			exit;
		}

		$_params			= $this->input->post();
		$couponSeq			= (int) $_params['couponSeq'];
		$paramCoupon		= $this->couponmodel->check_param_download($_params);
		$excel_url_check 	= $paramCoupon['excel_url_check'];
		$offline_insert 	= false;
		
		unset($paramCoupon['excel_url_check']);
		
		if(strstr($_params['issued_method'],'offline') && !$couponSeq && $_params['coupon_type'] != "ordersheet"){
			$offline_insert = true;
		}

		if($offline_insert == true){
			$paramCoupon["offline_input_serialnumber"]	= ($paramCoupon['offline_input_serialnumber'])?$paramCoupon['offline_input_serialnumber']:strtoupper(substr(md5( time().uniqid('') ), 0, 16));
		}

		$this->db->trans_begin();
		$rollback	= false;

		// 수정
		if($couponSeq){

			$savemode = "modify";
			$this->db->where('coupon_seq', $couponSeq);
			$this->db->update('fm_coupon', $paramCoupon);

			$this->db->delete('fm_coupon_issuecategory', array('coupon_seq' => $couponSeq));
			$this->db->delete('fm_coupon_issuegoods', array('coupon_seq' => $couponSeq));
			$this->db->delete('fm_coupon_group', array('coupon_seq' => $couponSeq));

			$paramGroup				= array();
			$paramIssuecategory		= array();
			$issueGoodsSeq			= $_params['issueGoodsSeq'];
			$issueCategoryCodeSeq	= $_params['issueCategoryCodeSeq'];
			$memberGroupsSeqar		= $_params['member_grade_coupon_group'];

		// 신규 등록
		}else{

			$savemode = "regist";

			$this->db->insert('fm_coupon', $paramCoupon);
			$couponSeq = $this->db->insert_id();

		}

		// 인증번호 발급(자동,엑셀,직접입력)은 신규등록시에만 처리.
		if($offline_insert == true){
			$paramoffline = array();
			$paramoffline["offline_number"]		= mt_rand();//생성
			$paramoffline["coupon_seq"]			= $couponSeq;
			$paramoffline["regist_date"]		= date("Y-m-d H:i:s",mktime());
			if( $paramCoupon['offline_type'] == 'random') {//자동생성 > 인증번호 갯수
				if($paramCoupon["offline_random_num"] > 10000) {
					set_time_limit(0);
					ini_set('memory_limit','-1');
				}
				$paramoffline["use_count"] = 1;
				for($i=0;$i<$paramCoupon["offline_random_num"];$i++) {
					$paramoffline["offline_serialnumber"] = strtoupper(substr(md5( time().uniqid('') ), 0, 16));
					$this->db->insert('fm_offline_coupon', $paramoffline);
				}
			}elseif( $paramCoupon['offline_type'] == 'one') {//자동생성 > 동일번호
				if( $paramCoupon['offline_limit'] == 'limit') {//자동생성 > 동일번호 > 선착순
					$paramoffline["use_count"] = $paramCoupon['offline_limit_ea'];
				}else{
					//제한없음
				}
				$paramoffline["offline_serialnumber"] = $paramCoupon["offline_input_serialnumber"];
				$this->db->insert('fm_offline_coupon', $paramoffline);
			}elseif( $paramCoupon['offline_type'] == 'input') {//수동생성1 > 동일번호
				if( $paramCoupon['offline_limit'] == 'limit') {//수동생성 > 동일번호 > 선착순
					$paramoffline["use_count"] = $paramCoupon['offline_limit_ea'];
				}else{
					//제한없음
				}
				$paramoffline["offline_serialnumber"] = $paramCoupon["offline_input_serialnumber"];
				$this->db->insert('fm_offline_coupon', $paramoffline);
			}elseif( $paramCoupon['offline_type'] == 'file') {//수동생성2 > 파일
				//'offline_file', '수동생성 > 엑셀파일' $this->db->insert('fm_offline_coupon_input', $paramoffline);
			}
		}

		$memberGroupsar		= $_params['member_grade_list'];
		$issueGoods			= $_params['issueGoods'];
		$issueCategoryCode	= $_params['issueCategoryCode'];

		// 회원등급제한
		if( isset($memberGroupsar) ){
			$memberGroupsar = array_unique($memberGroupsar);
			foreach($memberGroupsar as $key => $groupSeq){
				if($savemode == "modify"){
					unset($paramGroup);
					if( isset($memberGroupsar[$groupSeq]) ){
						$paramGroup['coupon_group_seq'] = $memberGroupsSeqar[$groupSeq];
					}
				}
				$paramGroup['coupon_seq']		= $couponSeq;
				$paramGroup['group_seq']		= $groupSeq;
				$this->db->insert('fm_coupon_group', $paramGroup);
			}
		}

		// 선택한 상품/카테고리에만 쿠폰 적용 또는 선택한 상품/카테고리에만 쿠폰 제외
		if( isset($issueGoods) ){
			$issueGoods = array_unique($issueGoods);
			foreach($issueGoods as $key => $goodsSeq){
				if($savemode == "modify"){
					if( isset($issueGoodsSeq[$goodsSeq]) ){
						$paramIssuegoods['issuegoods_seq']	= $issueGoodsSeq[$goodsSeq];
					}else{
						$paramIssuegoods['issuegoods_seq'] = '';
					}
				}
				// 본사 또는 선택한 입점사의 상품만 저장.
				$query		= $this->db->select('provider_seq')->get_where('fm_goods',array('goods_seq'=>$goodsSeq));
				$goodsData	= $query->row_array();
				if(!serviceLimit('H_AD') || $_params['discount_seller_type'] == "all" || ($_params['discount_seller_type'] == 'admin' && $goodsData['provider_seq'] == 1) || 
						($_params['discount_seller_type'] == 'seller' && in_array($goodsData['provider_seq'],$_params['salescost_provider_list'])))
				{
				$paramIssuegoods['coupon_seq']		= $couponSeq;
				$paramIssuegoods['goods_seq']		= $goodsSeq;
				$paramIssuegoods['type']			= $paramCoupon['issue_type'];
				$this->db->insert('fm_coupon_issuegoods', $paramIssuegoods);
			}
		}
		}

		if( isset($issueCategoryCode) ){
			$issueCategoryCode = array_unique($issueCategoryCode);
			foreach($issueCategoryCode as $key => $categoryCode){
				if($savemode == "modify"){
					if( isset($issueCategoryCodeSeq[$categoryCode]) ){
						$paramIssuecategory['issuecategory_seq']	= $issueCategoryCodeSeq[$categoryCode];
					}else{
						$paramIssuecategory['issuecategory_seq'] = '';
					}
				}
				$paramIssuecategory['coupon_seq']			= $couponSeq;
				$paramIssuecategory['category_code']		= $categoryCode;
				$paramIssuecategory['type']					= $paramCoupon['issue_type'];
				$this->db->insert('fm_coupon_issuecategory', $paramIssuecategory);
			}
		}

		if ($this->db->trans_status() === FALSE || $rollback == true)
		{
			$this->db->trans_rollback();

			## 쿠폰등록 중 오류가 발생했습니다.
			openDialogAlert("쿠폰등록 중 오류가 발생했습니다.",400,140);
			exit;
		}
		else
		{
			$this->db->trans_commit();
		}

		//인증번호발급 > 수동 엑셀 등록
		if( $excel_url_check == 'ok') {
			$callback = "parent.offlineexcelsave('".$couponSeq."');";
			openDialogAlert("인증번호를 일괄등록시작합니다.<br><b><font color=red>창이 닫히지 않도록 주의해 주세요.</font></b>",400,180,'parent',$callback);
		}else{

			if($savemode == "modify"){
				$dsc['whereis']	= ' and coupon_seq='.$couponSeq;
				$downloadtotal	= $this->couponmodel->get_download_total_count($dsc);
				$msg			= "쿠폰 정보가 수정되었습니다.<br />이제부터는 본 쿠폰이 발급될 때 수정된 정보로 쿠폰이 발급되어집니다.";
				if($downloadtotal > 0)
					$msg .= "<br/>단, 수정 이전에 발급된 쿠폰 " . $downloadtotal . "건에는 영향이 없습니다.";
				$callback = "parent.document.location.reload();";
				openDialogAlert($msg,500,180,'parent',$callback);
			}else{
				$callback = "parent.document.location.replace('/admin/coupon/regist?no=".$couponSeq."&mode=new');";
				openDialogAlert("저장 되었습니다.",400,140,'parent',$callback);
			}
		}
			
	}


	public function issued_status_update()
	{
		/* 관리자 권한 체크 : 시작 */
		$auth = $this->authmodel->manager_limit_act('coupon_act');
		if(!$auth){
			$callback = "";
			openDialogAlert($this->auth_msg,400,140,'parent',$callback);
			exit;
		}

		$aPostParams				= $this->input->post();
		$paramCoupon 				= array();
		$paramCoupon['issue_stop'] 	= if_empty($aPostParams, 'issue_stop', '0');

		$this->db->where('coupon_seq', $aPostParams['couponSeq']);
		$this->db->update('fm_coupon', $paramCoupon);

		echo 'true';
		exit;
	}


	//온라인쿠폰 등록 ------ 삭제대상 20200519
	public function online()
	{
		/* 관리자 권한 체크 : 시작 */
		$auth = $this->authmodel->manager_limit_act('coupon_act');
		if(!$auth){
			$callback = "";
			openDialogAlert($this->auth_msg,400,140,'parent',$callback);
			exit;
		}

		$paramCoupon = $this->couponmodel->check_param_online_download();
		$paramCoupon['discount_seller_type'] = "admin";

		$this->db->insert('fm_coupon', $paramCoupon);
		$couponSeq = $this->db->insert_id();
		$memberGroupsar = $_POST['memberGroups_'.$paramCoupon['type']];
		if(isset($memberGroupsar)){
			foreach($memberGroupsar as $groupSeq){
				$paramGroup['coupon_seq']		= $couponSeq;
				$paramGroup['group_seq']		= $groupSeq;
				$this->db->insert('fm_coupon_group', $paramGroup);
			}
		}
		if($_POST['issue_type'] == 'issue' ){
			if(isset($_POST['issueGoods'])){
				foreach($_POST['issueGoods'] as $goodsSeq){
					$paramIssuegoods['coupon_seq']	= $couponSeq;
					$paramIssuegoods['goods_seq']	= $goodsSeq;
					$paramIssuegoods['type']		= 'issue';
					$this->db->insert('fm_coupon_issuegoods', $paramIssuegoods);
				}
			}
			if(isset($_POST['issueCategoryCode'])){
				foreach($_POST['issueCategoryCode'] as $categoryCode){
					$paramIssuecategory['coupon_seq']		= $couponSeq;
					$paramIssuecategory['category_code']	= $categoryCode;
					$paramIssuecategory['type']				= 'issue';
					$this->db->insert('fm_coupon_issuecategory', $paramIssuecategory);
				}
			}
		}elseif($_POST['issue_type'] == 'except' ){
			if(isset($_POST['exceptIssueGoods'])){
				foreach($_POST['exceptIssueGoods'] as $goodsSeq){
					$paramIssuegoods['coupon_seq']	= $couponSeq;
					$paramIssuegoods['goods_seq']	= $goodsSeq;
					$paramIssuegoods['type']		= 'except';
					$this->db->insert('fm_coupon_issuegoods', $paramIssuegoods);
				}
			}
			if(isset($_POST['exceptIssueCategoryCode'])){
				foreach($_POST['exceptIssueCategoryCode'] as $categoryCode){
					$paramIssuecategory['coupon_seq']		= $couponSeq;
					$paramIssuecategory['category_code']	= $categoryCode;
					$paramIssuecategory['type']				= 'except';
					$this->db->insert('fm_coupon_issuecategory', $paramIssuecategory);
				}
			}
		}


		$callback = "parent.document.location.href='/admin/coupon/online?no=".$couponSeq."';";
		openDialogAlert("저장 되었습니다.",400,140,'parent',$callback);
	}

	//온라인쿠폰수정 ------ 삭제대상 20200519
	public function online_modify()
	{

		/* 관리자 권한 체크 : 시작 */
		$auth = $this->authmodel->manager_limit_act('coupon_act');
		if(!$auth){
			$callback = "";
			openDialogAlert($this->auth_msg,400,140,'parent',$callback);
			exit;
		}
		/* 관리자 권한 체크 : 끝 */

		$couponSeq = (int) $_POST['couponSeq'];

		$paramCoupon = $this->couponmodel->check_param_online_download();

		$this->db->where('coupon_seq', $couponSeq);
		$this->db->update('fm_coupon', $paramCoupon);

		$this->db->delete('fm_coupon_issuecategory', array('coupon_seq' => $couponSeq));
		$this->db->delete('fm_coupon_issuegoods', array('coupon_seq' => $couponSeq));
		$this->db->delete('fm_coupon_group', array('coupon_seq' => $couponSeq));
		$paramGroup = array();
		$memberGroupsar = $_POST['memberGroups_'.$paramCoupon['type']];
		$memberGroupsSeqar = $_POST['memberGroupsSeq_'.$paramCoupon['type']];

		if( isset($memberGroupsar) ){
			foreach($memberGroupsar as $key => $groupSeq){
				unset($paramGroup);
				if( isset($memberGroupsar[$groupSeq]) ){
					$paramGroup['coupon_group_seq'] = $memberGroupsSeqar[$groupSeq];
				}
				$paramGroup['coupon_seq']		= $couponSeq;
				$paramGroup['group_seq']		= $groupSeq;
				$this->db->insert('fm_coupon_group', $paramGroup);
			}
		}

		$paramIssuecategory = array();
		if($_POST['issue_type'] == 'issue' ){
			if( isset($_POST['issueCategoryCode']) ){
				foreach($_POST['issueCategoryCode'] as $key => $categoryCode){
					if( isset($_POST['issueCategoryCodeSeq'][$categoryCode]) ){
						$paramIssuecategory['issuecategory_seq']	= $_POST['issueCategoryCodeSeq'][$categoryCode];
					}else{
						$paramIssuecategory['issuecategory_seq'] = '';
					}

					$paramIssuecategory['coupon_seq']			= $couponSeq;
					$paramIssuecategory['category_code']		= $categoryCode;
					$paramIssuecategory['type']						= 'issue';
					$this->db->insert('fm_coupon_issuecategory', $paramIssuecategory);
				}
			}
			if( isset($_POST['issueGoods']) ){
				foreach($_POST['issueGoods'] as $key => $goodsSeq){
					if( isset($_POST['issueGoodsSeq'][$goodsSeq]) ){
						$paramIssuegoods['issuegoods_seq']	= $_POST['issueGoodsSeq'][$goodsSeq];
					}else{
						$paramIssuegoods['issuegoods_seq'] = '';
					}
					$paramIssuegoods['coupon_seq']		= $couponSeq;
					$paramIssuegoods['goods_seq']		= $goodsSeq;
					$paramIssuegoods['type']			= 'issue';
					$this->db->insert('fm_coupon_issuegoods', $paramIssuegoods);
				}
			}

		}elseif($_POST['issue_type'] == 'except' ){
			if( isset($_POST['exceptIssueCategoryCode']) ){
				foreach($_POST['exceptIssueCategoryCode'] as $key => $categoryCode){
					if( isset($_POST['exceptIssueCategoryCodeSeq'][$categoryCode]) ){
						$paramIssuecategory['issuecategory_seq']	= $_POST['exceptIssueCategoryCodeSeq'][$categoryCode];
					}else{
						$paramIssuecategory['issuecategory_seq'] = '';
					}
					$paramIssuecategory['coupon_seq']			= $couponSeq;
					$paramIssuecategory['category_code']		= $categoryCode;
					$paramIssuecategory['type']						= 'except';
					$this->db->insert('fm_coupon_issuecategory', $paramIssuecategory);
				}
			}
			if( isset($_POST['exceptIssueGoods']) ){
				foreach($_POST['exceptIssueGoods'] as $key => $goodsSeq){
					if( isset($_POST['exceptIssueGoodsSeq'][$goodsSeq]) ){
						$paramIssuegoods['issuegoods_seq']	= $_POST['exceptIssueGoodsSeq'][$goodsSeq];
					}else{
						$paramIssuegoods['issuegoods_seq'] = '';
					}
					$paramIssuegoods['coupon_seq']		= $couponSeq;
					$paramIssuegoods['goods_seq']		= $goodsSeq;
					$paramIssuegoods['type']					= 'except';
					$this->db->insert('fm_coupon_issuegoods', $paramIssuegoods);
				}
			}
		}

		$dsc['whereis'] = ' and coupon_seq='.$couponSeq;
		$downloadtotal = $this->couponmodel->get_download_total_count($dsc);
		$msg = "쿠폰의 정보가 수정되었습니다.<br/>이제부터는 본 쿠폰이 발급될 때 수정된 정보로 쿠폰이 발급되어집니다.";
		if($downloadtotal > 0)
			$msg .= "<br/>단, 수정 이전에 발급된 쿠폰 " . $downloadtotal . "건에는 영향이 없습니다.";
		$callback = "parent.document.location.reload();";
		openDialogAlert($msg,500,180,'parent',$callback);
	}

	//온라인쿠폰삭제
	public function online_delete()
	{
		/* 관리자 권한 체크 : 시작 */
		$auth = $this->authmodel->manager_limit_act('coupon_act');
		if(!$auth){
			if($_GET['ajaxcall']){
				$return = array('result' => 'auth', 'msg' => '관리자 권한이 없습니다.');
				echo json_encode($return);
			}else{
				openDialogAlert($this->auth_msg,400,140,'parent','');
			}
			exit;
		}
		/* 관리자 권한 체크 : 끝 */

		$couponSeq = (int) $_POST['couponSeq'];
		$coupons 	= $this->couponmodel->get_coupon($couponSeq);
		if($coupons) {
			$dsc = array();
			$dsc['whereis']				= ' and coupon_seq='.$couponSeq;
			$downloadtotal				= $this->couponmodel->get_download_total_count($dsc);
			if($downloadtotal > 0) {
				$return = array('result' => 'false', 'msg' => '이미 쿠폰을 다운로드 받은 회원이 있습니다.');
				echo json_encode($return);
				exit;			
			}
			$this->db->delete('fm_coupon_issuecategory', array('coupon_seq' => $couponSeq));
			$this->db->delete('fm_coupon_issuegoods', array('coupon_seq' => $couponSeq));
			$this->db->delete('fm_coupon_group', array('coupon_seq' => $couponSeq));
			$result = $this->db->delete('fm_coupon', array('coupon_seq' => $couponSeq));
			if($result) {
				if(@is_file($this->couponmodel->copuonupload_dir.$coupons['coupon_image4'])) {
					@unlink($this->couponmodel->copuonupload_dir.$coupons['coupon_image4']);
				}

				$return = array('result' => 'true', 'msg' => '삭제 되었습니다.');
				echo json_encode($return);
			}else{
				$return = array('result' => 'false', 'msg' => '쿠폰삭제가 실패되었습니다.');
				echo json_encode($return);
			}
		}else{
			$return = array('result' => 'false', 'msg' => '잘못된 접근입니다.');
			echo json_encode($return);
		}
		exit;
	}

	//offlinecoupon insert ------ 삭제대상 20200519
	public function offline()
	{
		/* 관리자 권한 체크 : 시작 */
		$auth = $this->authmodel->manager_limit_act('coupon_act');
		if(!$auth){
			$callback = "";
			openDialogAlert($this->auth_msg,400,140,'parent',$callback);
			exit;
		}

		$paramCoupon = $this->couponmodel->check_param_offline_download();
		$paramCoupon["offline_input_serialnumber"]		= ($paramCoupon['offline_input_serialnumber'])?$paramCoupon['offline_input_serialnumber']:strtoupper(substr(md5( time().uniqid('') ), 0, 16));

		$this->db->insert('fm_coupon', $paramCoupon);
		$couponSeq = $this->db->insert_id();

		$paramoffline["offline_number"]		= mt_rand();//생성
		$paramoffline["coupon_seq"]			= $couponSeq;
		$paramoffline["regist_date"]			= $paramCoupon["regist_date"];
		if( $paramCoupon['offline_type'] == 'random') {//자동생성 > 인증번호 갯수
			if($paramCoupon["offline_random_num"] > 10000) {
				set_time_limit(0);
				ini_set('memory_limit','-1');
			}
			$paramoffline["use_count"] = 1;
			for($i=0;$i<$paramCoupon["offline_random_num"];$i++) {
				$paramoffline["offline_serialnumber"] = strtoupper(substr(md5( time().uniqid('') ), 0, 16));
				$this->db->insert('fm_offline_coupon', $paramoffline);
			}
		}elseif( $paramCoupon['offline_type'] == 'one') {//자동생성 > 동일번호
			if( $paramCoupon['offline_limit'] == 'limit') {//자동생성 > 동일번호 > 선착순
				$paramoffline["use_count"] = $paramCoupon['offline_limit_ea'];
			}else{
				//제한없음
			}
			$paramoffline["offline_serialnumber"] = $paramCoupon["offline_input_serialnumber"];
			$this->db->insert('fm_offline_coupon', $paramoffline);
		}elseif( $paramCoupon['offline_type'] == 'input') {//수동생성1 > 동일번호
			if( $paramCoupon['offline_limit'] == 'limit') {//수동생성 > 동일번호 > 선착순
				$paramoffline["use_count"] = $paramCoupon['offline_limit_ea'];
			}else{
				//제한없음
			}
			$paramoffline["offline_serialnumber"] = $paramCoupon["offline_input_serialnumber"];
			$this->db->insert('fm_offline_coupon', $paramoffline);
		}elseif( $paramCoupon['offline_type'] == 'file') {//수동생성2 > 파일
			//'offline_file', '수동생성 > 엑셀파일' $this->db->insert('fm_offline_coupon_input', $paramoffline);
		}

		$memberGroupsar = $_POST['memberGroups_'.$_POST['couponType']];
		if(isset($memberGroupsar)){
			foreach($memberGroupsar as $groupSeq){
				$paramGroup['coupon_seq']		= $couponSeq;
				$paramGroup['group_seq']			= $groupSeq;
				$this->db->insert('fm_coupon_group', $paramGroup);
			}
		}
		if($_POST['issue_type'] == 'issue' ){
			if(isset($_POST['issueGoods'])){
				foreach($_POST['issueGoods'] as $goodsSeq){
					$paramIssuegoods['coupon_seq']	= $couponSeq;
					$paramIssuegoods['goods_seq']	= $goodsSeq;
					$paramIssuegoods['type']				= 'issue';
					$this->db->insert('fm_coupon_issuegoods', $paramIssuegoods);
				}
			}
			if(isset($_POST['issueCategoryCode'])){
				foreach($_POST['issueCategoryCode'] as $categoryCode){
					$paramIssuecategory['coupon_seq']		= $couponSeq;
					$paramIssuecategory['category_code']	= $categoryCode;
					$paramIssuecategory['type']					= 'issue';
					$this->db->insert('fm_coupon_issuecategory', $paramIssuecategory);
				}
			}
		}elseif($_POST['issue_type'] == 'except' ){
			if(isset($_POST['exceptIssueGoods'])){
				foreach($_POST['exceptIssueGoods'] as $goodsSeq){
					$paramIssuegoods['coupon_seq']	= $couponSeq;
					$paramIssuegoods['goods_seq']	= $goodsSeq;
					$paramIssuegoods['type']				= 'except';
					$this->db->insert('fm_coupon_issuegoods', $paramIssuegoods);
				}
			}
			if(isset($_POST['exceptIssueCategoryCode'])){
				foreach($_POST['exceptIssueCategoryCode'] as $categoryCode){
					$paramIssuecategory['coupon_seq']		= $couponSeq;
					$paramIssuecategory['category_code']	= $categoryCode;
					$paramIssuecategory['type']					= 'except';
					$this->db->insert('fm_coupon_issuecategory', $paramIssuecategory);
				}
			}
		}
		if( $paramCoupon['offline_type'] == 'file') {//수동생성2 > 파일
			$callback = "parent.offlineexcelsave('".$couponSeq."');";
			openDialogAlert("인증번호를 일괄등록시작합니다.<br><b><font color=red>창이 닫히지 않도록 주의해 주세요.</font></b>",400,150,'parent',$callback);
		}else{
			$callback = "parent.document.location.href='/admin/coupon/offline?no=".$couponSeq."';";
			openDialogAlert("저장 되었습니다.",400,140,'parent',$callback);
		}
	}

	//offline coupon modify ------ 삭제대상 20200519
	public function offline_modify()
	{
		/* 관리자 권한 체크 : 시작 */
		$auth = $this->authmodel->manager_limit_act('coupon_act');
		if(!$auth){
			$callback = "";
			openDialogAlert($this->auth_msg,400,140,'parent',$callback);
			exit;
		}
		/* 관리자 권한 체크 : 끝 */

		$couponSeq = (int) $_POST['couponSeq'];
		$paramCoupon = $this->couponmodel->check_param_offline_download();

		$this->db->where('coupon_seq', $couponSeq);
		$this->db->update('fm_coupon', $paramCoupon);

		$this->db->delete('fm_coupon_issuecategory', array('coupon_seq' => $couponSeq));
		$this->db->delete('fm_coupon_issuegoods', array('coupon_seq' => $couponSeq));
		$this->db->delete('fm_coupon_group', array('coupon_seq' => $couponSeq));

		$paramGroup = array();
		$memberGroupsar = $_POST['memberGroups_'.$_POST['couponType']];
		$memberGroupsSeqar = $_POST['memberGroupsSeq_'.$_POST['couponType']];

		if( isset($memberGroupsar) ){
			foreach($memberGroupsar as $key => $groupSeq){
				if( isset($memberGroupsar[$groupSeq]) ){
					$paramGroup['coupon_group_seq'] = $memberGroupsSeqar[$groupSeq];
				}
				$paramGroup['coupon_seq']		= $couponSeq;
				$paramGroup['group_seq']		= $groupSeq;
				$this->db->insert('fm_coupon_group', $paramGroup);
			}
		}

		$paramIssuecategory = array();
		if($_POST['issue_type'] == 'issue' ){
			if( isset($_POST['issueCategoryCode']) ){
				foreach($_POST['issueCategoryCode'] as $key => $categoryCode){
					if( isset($_POST['issueCategoryCodeSeq'][$categoryCode]) ){
						$paramIssuecategory['issuecategory_seq']	= $_POST['issueCategoryCodeSeq'][$categoryCode];
					}else{
						$paramIssuecategory['issuecategory_seq']	= '';
					}

					$paramIssuecategory['coupon_seq']			= $couponSeq;
					$paramIssuecategory['category_code']		= $categoryCode;
					$paramIssuecategory['type']					= 'issue';
					$this->db->insert('fm_coupon_issuecategory', $paramIssuecategory);
				}
			}
			if( isset($_POST['issueGoods']) ){
				foreach($_POST['issueGoods'] as $key => $goodsSeq){
					if( isset($_POST['issueGoodsSeq'][$goodsSeq]) ){
						$paramIssuegoods['issuegoods_seq']	= $_POST['issueGoodsSeq'][$goodsSeq];
					}else{
						$paramIssuegoods['issuegoods_seq']	= '';
					}
					$paramIssuegoods['coupon_seq']		= $couponSeq;
					$paramIssuegoods['goods_seq']		= $goodsSeq;
					$paramIssuegoods['type']			= 'issue';
					$this->db->insert('fm_coupon_issuegoods', $paramIssuegoods);
				}
			}

		}elseif($_POST['issue_type'] == 'except' ){
			if( isset($_POST['exceptIssueCategoryCode']) ){
				foreach($_POST['exceptIssueCategoryCode'] as $key => $categoryCode){
					if( isset($_POST['exceptIssueCategoryCodeSeq'][$categoryCode]) ){
						$paramIssuecategory['issuecategory_seq']	= $_POST['exceptIssueCategoryCodeSeq'][$categoryCode];
					}else{
						$paramIssuecategory['issuecategory_seq']	= '';
					}
					$paramIssuecategory['coupon_seq']			= $couponSeq;
					$paramIssuecategory['category_code']		= $categoryCode;
					$paramIssuecategory['type']					= 'except';
					$this->db->insert('fm_coupon_issuecategory', $paramIssuecategory);
				}
			}
			if( isset($_POST['exceptIssueGoods']) ){
				foreach($_POST['exceptIssueGoods'] as $key => $goodsSeq){
					if( isset($_POST['exceptIssueGoodsSeq'][$goodsSeq]) ){
						$paramIssuegoods['issuegoods_seq']	= $_POST['exceptIssueGoodsSeq'][$goodsSeq];
					}else{
						$paramIssuegoods['issuegoods_seq']	= '';
					}
					$paramIssuegoods['coupon_seq']		= $couponSeq;
					$paramIssuegoods['goods_seq']		= $goodsSeq;
					$paramIssuegoods['type']			= 'except';
					$this->db->insert('fm_coupon_issuegoods', $paramIssuegoods);
				}
			}
		}

		$dsc['whereis'] = ' and coupon_seq='.$couponSeq;
		$downloadtotal = $this->couponmodel->get_download_total_count($dsc);
		$msg = "쿠폰의 정보가 수정되었습니다.<br/>이제부터는 본 쿠폰이 발급될 때 수정된 정보로 쿠폰이 발급되어집니다.";
		if($downloadtotal > 0)
			$msg .= "<br/>단, 수정 이전에 발급된 쿠폰 " . $downloadtotal . "건에는 영향이 없습니다.";
		$callback = "parent.document.location.reload();";
		openDialogAlert($msg,500,180,'parent',$callback);
	}

	//offline coupon delete
	public function offline_delete()
	{

		/* 관리자 권한 체크 : 시작 */
		$auth = $this->authmodel->manager_limit_act('coupon_act');
		if(!$auth){
			if($_GET['ajaxcall']){
				$return = array('result' => 'auth', 'msg' => '관리자 권한이 없습니다.');
				echo json_encode($return);
			}else{
				openDialogAlert($this->auth_msg,400,140,'parent','');
			}
			exit;
		}
		/* 관리자 권한 체크 : 끝 */

		$couponSeq = (int) $_POST['couponSeq'];
		$coupons 	= $this->couponmodel->get_coupon($couponSeq);
		if($coupons) {

			$this->db->delete('fm_coupon_issuecategory', array('coupon_seq' => $couponSeq));
			$this->db->delete('fm_coupon_issuegoods', array('coupon_seq' => $couponSeq));
			$this->db->delete('fm_coupon_group', array('coupon_seq' => $couponSeq));

			$this->db->delete('fm_offline_coupon', array('coupon_seq' => $couponSeq));
			$this->db->delete('fm_offline_coupon_input', array('coupon_seq' => $couponSeq));

			$result = $this->db->delete('fm_coupon', array('coupon_seq' => $couponSeq));
			if($result) {
				if(@is_file($this->couponmodel->copuonupload_dir.$coupons['coupon_image4'])) {
					@unlink($this->couponmodel->copuonupload_dir.$coupons['coupon_image4']);
				}

				$return = array('result' => 'true', 'msg' => '삭제 되었습니다.');
				echo json_encode($return);
			}else{
				$return = array('result' => 'false', 'msg' => '쿠폰삭제가 실패되었습니다.');
				echo json_encode($return);
			}
		}else{
			$return = array('result' => 'false', 'msg' => '잘못된 접근입니다.');
			echo json_encode($return);
		}
		exit;
	}

	//오프라인쿠폰 > 인증번호 보기
	public function offline_coupon_list()
	{
		$no = (int) $this->input->post('no', true);
		$coupons = $this->couponmodel->get_coupon($no);
		$this->template->assign(array('coupons'=>$coupons));

		### SEARCH
		$sc = $this->input->post();
		$sc['perpage'] = (!empty($sc['perpage'])) ? (int)$sc['perpage'] :20;
		$sc['page'] = (!empty($sc['page'])) ? (int)(($sc['page'] - 1) * $sc['perpage']) :0;
		
		if($coupons['offline_type'] == 'file'){//랜덤등록과 엑셀등록인 경우
			$data = $this->couponmodel->offlinecoupon_input_list($sc);
		}else{
			$data = $this->couponmodel->offlinecoupon_list($sc);
		}
		// 페이징 처리 위한 변수 셋팅
		$page =  get_current_page($sc);
		$pagecount =  get_page_count($sc, $data['count']);

		### PAGE & DATA
		$sc['searchcount']	 = $data['count'];
		$sc['total_page']		= @ceil($sc['searchcount']/ $sc['perpage']);
		if($coupons['offline_type'] == 'file'){//엑셀등록인 경우
			$sc['totalcount'] = $this->couponmodel->get_offlinecoupon_input_item_total_count($no);
		}else{
			$sc['totalcount'] = $this->couponmodel->get_offlinecoupon_item_total_count($no);
		}

		$html = '';
		$i = 0;
		foreach($data['result'] as $datarow){
			//$usecolor = ($datarow['use_count'] == 0)? ' red bold ':'   ';
			$usc['whereis'] = ' and coupon_seq='.$datarow['coupon_seq'].'  and offline_input_serialnumber="'.$datarow['offline_serialnumber'].'" and use_status = "used" ';
			$usetotal = $this->couponmodel->get_download_total_count($usc);  
			$usecolor = ( $usetotal )? ' red bold ':' ';
			$datarow['number'] = $data['count'] - ( ( $page -1 ) * $sc['perpage'] + $i + 1 ) + 1;
			$html .= '<tr>';
			$html .= '	<td>'.$datarow['number'].'</td>';
			$html .= '	<td class="'.$usecolor.'" >'.$datarow['offline_serialnumber'].'</td>';
			$html .= '</tr>';
			$i++;
		}//foreach end

		if($i==0){
			if($sc['search_text']){
				$html .= '<tr ><td colspan="2" >"'.$sc['search_text'].'"로(으로) 검색된 인증번호가 없습니다.</td></tr>';
			}else{
				$html .= '<tr ><td colspan="2" >인증번호가 없습니다.</td></tr>';
			}
		}
		if(!empty($html)) {
			$result = array( 'content'=>$html, 'searchcount'=>$sc['searchcount'], 'totalcount'=>$sc['totalcount'], 'nowpage'=>(($sc['page']/$sc['perpage'])+1), 'total_page'=>$sc['total_page'], 'page'=>$page, 'pagecount'=>(int)$pagecount);
		}else{
			$result = array( 'content'=>"", 'searchcount'=>$sc['searchcount'], 'totalcount'=>$sc['totalcount'], 'nowpage'=>(($sc['page']/$sc['perpage'])+1), 'total_page'=>$sc['total_page'], 'page'=>$page, 'pagecount'=>(int)$pagecount);
		}
		echo json_encode($result);
		exit;
	}

	//쿠폰이미지등록하기
	public function upload_file()
	{
		$this->load->helper('board');//

		$folder = "data/tmp/";

		foreach($_FILES as $key => $value)
		{
			$tmpname	= $value['tmp_name'];
			$file_ext		= end(explode('.', $value['name']));//확장자추출
			$file_name	= 'coupon_app_'.$_GET['type'].'_'.str_replace(" ", "", (substr(microtime(), 2, 6))).'.'.$file_ext;
			$file_name	= str_replace("\'", "", $file_name); 	// ' " 제거
			$file_name	= str_replace("\"", "", $file_name); 	// ' " 제거
			$saveFile		= $folder.$file_name;
			$config['allowed_types'] = 'jpg|gif|png';
			$tmp = @getimagesize($value['tmp_name']);
			if(!$tmp['mime']){
				$_FILES['Filedata']['type'] = $file_ext;//확장자추출
			}else{
				$_FILES['Filedata']['type'] = $tmp['mime'];
			}

			$fileresult = board_upload($key, $file_name, $folder, $config, $saveFile, 0, 'coupon');//status  error, fileInfo
			if(!$fileresult['status']){
				$error = array('status' => 0,'msg' => $fileresult['error'],'desc' => '업로드 실패');
				echo "[".json_encode($error)."]";
				exit;
			}
		}
		$result = array('status' => 1,'saveFile' => "/".$saveFile,'file_name' => $file_name);
		echo "[".json_encode($result)."]";
		exit;
	}

	//오프라인쿠폰 엑셀등록하기
	public function upload_excelfile()
	{
		$this->load->helper('board');//

		$folder = "data/tmp/";

		foreach($_FILES as $key => $value)
		{
			$tmpname	= $value['tmp_name'];
			$file_ext		= end(explode('.', $value['name']));//확장자추출
			$file_name	= 'offline_coupon_excel_'.str_replace(" ", "", (substr(microtime(), 2, 6))).'.'.$file_ext;
			$file_name	= str_replace("\'", "", $file_name); 	// ' " 제거
			$file_name	= str_replace("\"", "", $file_name); 	// ' " 제거
			$saveFile		= $folder.$file_name;
			$config['allowed_types'] = 'xls|xml';
			$tmp = @getimagesize($value['tmp_name']);
			if(!$tmp['mime']){
				$_FILES['Filedata']['type'] = $file_ext;//확장자추출
			}else{
				$_FILES['Filedata']['type'] = $tmp['mime'];
			}

			$fileresult = board_upload($key, $file_name, $folder, $config, $saveFile, 0, 'coupon');//status  error, fileInfo
			if(!$fileresult['status']){
				$error = array('status' => 0,'msg' => $fileresult['error'],'desc' => '업로드 실패');
				echo "[".json_encode($error)."]";
				exit;
			}
		}
		$readdata['filename']= $file_name;
		$readdata['savedir']= ROOTPATH.'/'.$folder;
		$realfilename = $readdata['savedir'].$readdata['filename'];
		$data = $this->_excelreader_coupon($realfilename);
		if($data['result']){
			$result = array('status' => 1,'saveFile' => "/".$saveFile,'file_name' => $file_name);
		}else{
			$msg = ($data['msg'])?$data['msg']:"잘못된 엑셀입니다.";
			$result = array('status' => 0,'msg'=>$msg,'desc' => '잘못된 엑셀양식');
		}
		echo "[".json_encode($result)."]";
		exit;
	}

	//오프라인쿠폰 > 엑셀등록하기 > query
	function offline_excel_save()
	{
		$no = (int) $_POST['no'];
		$coupons 		= $this->couponmodel->get_coupon($no);
		$this->template->assign(array('coupons'=>$coupons));

		$sc['perpage']			= 100;//
		$sc['page']				= (!empty($_POST['page'])) ?		intval($_POST['page']):2;

		$realfilename			= ROOTPATH.'/data/tmp/'.$_POST['file_name'];
		$firstnum					= ($_POST['page'] != 2) ? (($sc['page']-2)*$sc['perpage'])+2:2;
		$nextnum					= intval(($sc['page'] - 1) * $sc['perpage'])+1;
		$data = $this->_excelreader_coupon($realfilename, $firstnum, $nextnum);//, $firstnum=2, $nextnum= 1002

		// 페이징 처리 위한 변수 셋팅
		$page =  get_current_page($sc);
		$pagecount =  get_page_count($sc, $data['count']);
		### PAGE & DATA
		$sc['total_page']		= @ceil($data['count']/ $sc['perpage']);
		$sc['totalcount']		=  $data['count'];
		$nextpage = ( $nextnum > $sc['totalcount'] ) ? 0:($sc['page']+1);
		$nowpage = ($sc['page']-1);

		$i = $failcount = $succescount = 0;
		$html = '';
		foreach($data['loop'] as $offline_serialnumber){
			// offline쿠폰 정보
			$params['offline_serialnumber'] = $offline_serialnumber;
			$offline_coupons = $this->couponmodel->get_offlinecoupon_input_total_count($params);

			if($offline_coupons){
				$failcount++;			
				$success	= '중복번호';
			}else{
				unset($paramofflineinput);$succescount++;
				$paramofflineinput["offline_number"]			= mt_rand();//생성
				$paramofflineinput["coupon_seq"]				= $no;
				$paramofflineinput["regist_date"]				= date('Y-m-d H:i:s',time());
				$paramofflineinput["use_count"]					= 1;
				$paramofflineinput["offline_serialnumber"] = $offline_serialnumber;
				$this->db->insert('fm_offline_coupon_input', $paramofflineinput);
				$class = " ";
				$success	= '정상등록';
			}

			$number = $data['count'] - ( ( $sc['page'] - 2 ) * $sc['perpage'] + $i + 1 ) + 1;

			$html .= '<tr  '.$class.' >';
			$html .= '	<td>'.$number.'</td>';
			$html .= '	<td>'.$offline_serialnumber.'</td>';
			$html .= '	<td>'.$success.'</td>';
			$html .= '</tr>';
			$i++;
		}//foreach end

		if($i==0){
			$html .= '<tr ><td colspan="7" >등록자료가 없습니다.</td></tr>';
		}

		$result = array( 'content'=>$html, 'totalcount'=>$sc['totalcount'], 'nowpage'=>$nowpage, 'total_page'=>$sc['total_page'], 'page'=>$sc['page'], 'nextpage'=>$nextpage, 'pagecount'=>(int)$pagecount,'firstnum'=>$firstnum,  'nextnum'=>$nextnum);
		echo json_encode($result);
		exit;
	}

	/**
	* 엑셀 파일읽어오기
	* firstnum
	* nextnum
	**/
	function _excelreader_coupon($realfilename, $firstnum=2, $nextnum = NULL)
	{
		$this->load->library('pxl');
		$this->objPHPExcel = new PHPExcel();
		if(is_file($realfilename)){
		 try {
				// 업로드 된 엑셀 형식에 맞는 Reader객체를 만든다.
				$objReader = IOFactory::createReaderForFile($realfilename);
				// 읽기전용으로 설정
				$objReader->setReadDataOnly(true);
				// 엑셀파일을 읽는다
				$objExcel = $objReader->load($realfilename);

				// 첫번째 시트를 선택
				$objExcel->setActiveSheetIndex(0);
				$objWorksheet = $objExcel->getActiveSheet();

				$maxRow = $objWorksheet->getHighestRow();
				if($nextnum && $nextnum <= $maxRow ){
					$maxRow = $nextnum;
				}

				$maxCol = $objWorksheet->getHighestColumn();
				unset($loop);
				$totalcnt = 0;
				for ($i = $firstnum ; $i <= $maxRow ; $i++) { // 기본 두번째 행부터 읽는다 첫번째 타이틀임
					$coupon = $objWorksheet->getCell('A' . $i)->getValue(); // 첫번째 열 getCell('A' . $i) 두번째:getCell('B' . $i) 세번째:getCell('C' . $i)
					if($coupon){
						$totalcnt++;
						$loop[] = $coupon;
					}
				}//endfor
				if($totalcnt) {
					$data['result']		= true;
					$data['count']		= ($objWorksheet->getHighestRow()-1);
					$data['loop']		= $loop;
				}else{
					$data['result']		= false;
					$data['count']		= ($objWorksheet->getHighestRow()-1);
					$data['msg']			= '엑셀파일의 데이타가 없습니다.';
				}
			} catch (exception $e) {
				$data['result']		= false;
				$data['count']	= 0;
				$data['msg']			= '엑셀파일을 읽는도중 오류가 발생하였습니다.';
			}
		}else{
			$data['result']		= false;
			$data['count']	= 0;
			$data['msg']			= '엑셀파일이 없습니다.';
		}
		return $data;
	}

	//오프라인쿠폰 > 인증번호 엑셀다운
	public function offline_coupon_exceldown()
	{
		ini_set('memory_limit', '-1');
		set_time_limit(0);
		$no = (int) $_GET['no'];
		$coupons 	= $this->couponmodel->get_coupon($no);
		### SEARCH
		$sc = $_GET;
		if($coupons['offline_type'] == 'file'){//랜덤등록과 엑셀등록인 경우
			$writedata = $this->couponmodel->offlinecoupon_input_list($sc,'all');
		}else{
			$writedata = $this->couponmodel->offlinecoupon_list($sc,'all');
		}

		$writedata['filename']= $coupons['coupon_name'].'offlinecopon_down_'.str_replace(" ", "", (substr(microtime(), 2, 6))).'.xls';//
		$writedata['savedir']= ROOTPATH.'/data/tmp/';
		$writedata['saveurl']= '/data/tmp/';
		$writedata['exceltype']= 'Excel5';//'Excel2007';
		$this->_exceldownload($writedata);
	}

	//엑셀로 다운로드받기
	function _exceldownload($writedata)
	{
		ini_set('memory_limit', '5120M');
		set_time_limit(0);
		$this->load->library('pxl');
		$filename = $writedata['savedir'].$writedata['filename'];
		$this->objPHPExcel = new PHPExcel();
		// Assign cell values
		//Set active sheet index to the first sheet, so Excel opens this as the first sheet
		$this->objPHPExcel->setActiveSheetIndex(0);
		$this->objPHPExcel->getActiveSheet()->setCellValue("A1", "쿠폰번호");
		$i=2;
		foreach ($writedata["result"] as $k=>$v)
		{
		    $this->objPHPExcel->getActiveSheet()->setCellValueExplicit("A".$i, $v['offline_serialnumber'], PHPExcel_Cell_DataType::TYPE_STRING);
			$i++;
		}
		$this->objPHPExcel->getActiveSheet()->setTitle("오프라인쿠폰");
		$objWriter = IOFactory::createWriter($this->objPHPExcel, $writedata['exceltype']);
		if( $writedata['exceltype'] == 'Excel2007' ) {
			header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
			header("Cache-Control: no-store, no-cache, must-revalidate");
			header("Cache-Control: post-check=0, pre-check=0", false);
			header("Pragma: no-cache");
			header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet; charset=utf-8');
			header('Content-Disposition: attachment;filename="'.urlencode($writedata['filename']).'"');//한글명이 있는경우를 위해 urlencode($writedata['filename'])
			header('Cache-Control: max-age=0');
		}else{
			header("Pragma: public");
			header("Expires: 0");
			header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
			header("Content-Type: application/force-download");
			header("Content-Type: application/octet-stream");
			header("Content-Type: application/download; charset=utf-8");
			header('Content-Disposition: attachment;filename="'.urlencode($writedata['filename']).'"');
			header("Content-Transfer-Encoding: binary ");
		}
		$objWriter->save('php://output');
		/**
		$objWriter->save($filename);
		$fp = fopen($filename, 'rb');
		if (!fpassthru($fp)) fclose($fp);
		unlink($filename);
		**/
	}

	//엑셀로 웹상폴더에 저장하기
	function _excelsave($writedata)
	{
		$this->load->library('pxl');
		$this->objPHPExcel = new PHPExcel();
		$filename			= $writedata['filename'];
		$savedir			= $writedata['savedir'];
		$exceltype		= $writedata['exceltype'];
		// Assign cell values
		$this->objPHPExcel->setActiveSheetIndex(0);
		$this->objPHPExcel->getActiveSheet()->setCellValue("A1", "쿠폰인증번호");
		$i=2;
		foreach ($writedata["result"] as $k=>$v)
		{
		    $this->objPHPExcel->getActiveSheet()->setCellValueExplicit("A".$i, $v['offline_serialnumber'], PHPExcel_Cell_DataType::TYPE_STRING);
			$i++;
		}
		$objWriter = IOFactory::createWriter($this->objPHPExcel, $exceltype);
		$objWriter->save($savedir.$filename);
	}

	//오프라인 > 수동생성 > 인증코드입력시
	public function offlinecoupon_ck()
	{
		$offline_input_num = $this->input->post('offline_input_num');
		if(empty($offline_input_num)) {
			echo '';
			exit;
		}

		// offline쿠폰 인증번호 체크
		$sc['offline_serialnumber'] = $offline_input_num;
		$offlienresult = $this->couponmodel->get_offlinecoupon_total_count($sc);
		if(!$offlienresult){
			$offlienresult = $this->couponmodel->get_offlinecoupon_input_total_count($sc);
		}
		echo !$offlienresult ? 'true' : 'false';
		exit;
	}


	//관리자 > 쿠폰발급하기
	public function download_write()
	{

		/* 관리자 권한 체크 : 시작 */
		$auth = $this->authmodel->manager_limit_act('coupon_act');
		if(!$auth){
			$callback = "";
			openDialogAlert($this->auth_msg,400,140,'parent',$callback);
			exit;
		}
		/* 관리자 권한 체크 : 끝 */

		$couponSeq = (int) $_POST['no'];

		unset($memberArr);
		if( $_POST['target_type'] == 'all' ) {
			$key = get_shop_key();
			$query = $this->db->query("select member_seq from fm_member where status != 'withdrawal'");
			$memberArr = $query->result_array();
		}

		elseif( $_POST['target_type'] == 'group' ) {
			$memberGrouparr = implode("','",$_POST['memberGroups']);
			$key = get_shop_key();
			$whereis = " and group_seq in ('".$memberGrouparr."') ";//group_seq
			$query = $this->db->query("select member_seq from fm_member where status != 'withdrawal'".$whereis);
			$memberArr = $query->result_array();
		}

		elseif($_POST['target_type'] == 'member') {
			$target_member_ar = explode("],[",$_POST['target_member']);
			foreach($target_member_ar as $target_memberseq) {
				if(empty($target_memberseq))continue;
				$target_memberseq = str_replace("],","",str_replace("[","",$target_memberseq));
				$memberArr[]['member_seq'] = $target_memberseq;
			}
		}

		$coupons 	= $this->couponmodel->get_coupon($couponSeq);

		$downloadcnt = 0;
		if(is_array($memberArr) ) {
			//최종 관리자 > 쿠폰직접발급하기
			foreach($memberArr as $k){
				if(empty($k['member_seq']))continue;
				// 발급쿠폰 정보 확인
				$downcoupons = $this->couponmodel->get_admin_download($k['member_seq'], $couponSeq);
				if(!$downcoupons){
					if( $this->couponmodel->_admin_downlod( $couponSeq, $k['member_seq']) ) {
						$downloadcnt++;
					}
				}
			}
		}else{
			openDialogAlert("발급대상을 선택해 주세요.",400,140,'parent','');
			exit;
		}
		if($downloadcnt > 0) {
			$callback = "parent.document.location.reload();";
			openDialogAlert($downloadcnt."건의 쿠폰이 발급되었습니다.",400,150,'parent',$callback);
		}else{
			openDialogAlert("쿠폰발급 실패하였습니다.",400,140,'parent',$callback);
		}
		exit;
	}

	//관리자 > 쿠폰발급하기 kmj
	public function download_write_new()
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

		$params  = $_POST;

		$coupons = $this->couponmodel->get_coupon($params['no']);

		if(!$coupons){
			openDialogAlert("쿠폰이 올바르지 않습니다.",400,140,'parent','');
			exit;
		}

		if($params['target_type'] == "all"){
			$params['search_type'] = "search";
			$params['serialize'] = "no=".$params['no'];

			$sc = array();
			$sc['is_count'] = true;

			$params['member_total_count'] = $this->membermodel->coupon_member_list($sc);
		} else if(in_array($params['target_type'],array("group","member_grade"))){
			if(!$params['memberGroups']){
				openDialogAlert("회원 그룹을 선택 해 주세요.",400,140,'parent',$callback);
				exit;
			}
			
			$params['search_type'] = "search";
			$params['serialize'] = "no=".$params['no'];

			foreach($params['memberGroups'] as $groupNum){
				$params['serialize'] .= "&grade[]=".$groupNum;
			}

			$sc = array();
			$sc['is_count'] = true;
			$sc['grade'] = $params['memberGroups'];

			$params['member_total_count'] = $this->membermodel->coupon_member_list($sc);
		} else if(in_array($params['target_type'],array("member","member_select"))){
			$params['serialize'] = "no=".$params['no'];
			$memberArr = array_filter(explode("|", $params['target_member']));
			foreach($memberArr as $member){
				$params['serialize'] .= "&member_seq[]=".$member;
			}
			$params['member_total_count'] = count($memberArr);

			if($params['member_total_count'] <= 0 ){
				openDialogAlert("적용 할 회원이 없습니다.",400,140,'parent',$callback);
				exit;
			}
		}
		
		$couponCategory	= $this->couponmodel->get_coupon_issuecategory($coupons['coupon_seq']);
		if(!$couponCategory){
			$categoryCount = 0;
		} else {
			$categoryCount = count($couponCategory);
		}

		$couponGoods	= $this->couponmodel->get_coupon_issuegoods($coupons['coupon_seq']);
		if(!$couponGoods){
			$goodsCount = 0;
		} else {
			$goodsCount = count($couponGoods);
		}

		//전체 인서트 카운트로 비동기 처리 할지 결정을 하자...kmj
		$totalQueryCount = $params['member_total_count'] + ($categoryCount * $params['member_total_count']) + ($goodsCount * $params['member_total_count']);

		if($totalQueryCount <= 0){
			openDialogAlert("적용 할 회원이 없습니다.",400,140,'parent',$callback);
			exit;
		}

		$limitCount = 10000;
		if($totalQueryCount <= $limitCount){
			$sc = array();
			parse_str($params['serialize'], $sc);
			$sc['is_member'] = true;
			$sc['nolimit']	 = 'y';

			$memberArr = $this->membermodel->coupon_member_list($sc);
			$this->set_coupon_new($memberArr, $coupons, $couponCategory, $couponGoods, $limit = $limitCount);
		} else {
			$params['limitCount'] = $limitCount;
			$this->set_coupon_batch($params, $coupons);
		}
		exit;
	}

	//쿠폰 대량 발송 kmj
	public function set_coupon_batch($params, $coupons){
		//비동기 처리 시작
		//state 0:대기, 1:작업중, 2:완료
		//couponn category : 13
		$query	 = 'SELECT id FROM fm_queue WHERE category = ? AND state = ? LIMIT 1';
		$queryDB = $this->db->query($query, array(13, 1));
		$res	 = $queryDB->result_array();
		if( !empty($res) ){
			//작업중인게 있으면 안받음
			echo "Wait for a job No.".$res[0]['id']."!\n";
			exit;
		}

		$regDate = date('Y-m-d H:i:s');
		$salePrice	= ($coupons['sale_type'] == 'percent' ) ? $coupons['percent_goods_sale']."% 할인 (최대 ".number_format($coupons['max_percent_goods_sale']).")": "판매가격에서 ".number_format($coupons['won_goods_sale'])."원";

		$setData = array(
			'id'			=> '',
			'provider_seq'	=> 1,
			'manager_id'	=> $this->managerInfo['manager_id'],
			'category'		=> 13,
			'excel_type'	=> $params['search_type']."_".$params['target_type'], 
			'context'		=> $params['serialize'],
			'count'			=> $params['member_total_count'],
			'file_name'		=> $params['coupon_name']."|".$salePrice,
			'state'			=> 0,
			'limit_count'	=> $params['limitCount'],
			'reg_date'		=> $regDate
		);

		$this->db->insert('fm_queue', $setData);
		$queueID = $this->db->insert_id();
		if( $queueID > 0 ){
			$shopdomainArr = parse_url(base_url(uri_string()));
			$shopdomain	= $shopdomainArr['host'];

			$postParams = array(
				'queueID'	 => $queueID,
				'limitCount' => $params['limitCount']
			);
			$post_string = http_build_query($postParams);

			if(!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'){
				$shopport = 'https://';	
			} else {
				$shopport = 'http://';	
			}
			
			$parts = parse_url($shopport.$shopdomain.'/cli/batch_payments/set_coupon');
			if ($parts['scheme'] == 'http'){  
				$fp = fsockopen($parts['host'], isset($parts['port'])?$parts['port']:80, $errno, $errstr, 30);  
			}else if ($parts['scheme'] == 'https'){  
				$fp = fsockopen("ssl://" . $parts['host'], isset($parts['port'])?$parts['port']:443, $errno, $errstr, 30);  
			}  

			if (!$fp) {
				echo "$errstr ($errno), open sock erro.<br/>\n";
				exit;
			}
		  
			fwrite($fp, "POST ".$parts['path']." HTTP/1.1\r\n");
			fwrite($fp, "Host: ".$parts['host']."\r\n");
			fwrite($fp, "Content-Type: application/x-www-form-urlencoded\r\n");
			fwrite($fp, "Content-Length: ".strlen($post_string)."\r\n");
			fwrite($fp, "Connection: close\r\n");
			fwrite($fp, "\r\n");

			fwrite($fp, $post_string);
			//response 확인 할때 만
			
			while (!feof($fp)) {
				echo fgets($fp, 128);
			}
			
			//response 확인 할때 만
			fclose($fp);

			//$callback = "parent.location.reload();";
			openDialogAlert("지급 요청 완료 (회원 > 마일리지/포인트/쿠폰 지급내역에서 확인 가능)", 400, 140, 'parent', $callback);
			exit;
		} else {
			echo "Job Insert Errors";
			exit;
		}
	
	}

	//쿠폰 대량 발송 kmj
	public function set_coupon_new($memberArr, $coupons, $couponCategory, $couponGoods, $limit=10000){
		ini_set("memory_limit", -1);
		set_time_limit(0);

		unset($this->db->queries);
		unset($this->db->query_times);

		$totalCnt	= 0;
		$dataBatch	= array();
		$regDate	= date("Y-m-d");
		$regTime	= date("H:i:s");

		$columns = $this->db->list_fields('fm_download');
		
		$dataArr = array();
		foreach($columns as $cNames){
			$dataArr[$cNames] = $coupons[$cNames];

			if($cNames == "issue_type" && empty($coupons['issue_type'])){
				$dataArr['issue_type'] = "all";
			}

			if($cNames == "salescost_admin" && empty($coupons['salescost_admin'])){
				$dataArr['salescost_admin'] = 100;
			}
			
			if($cNames == "salescost_provider" && empty($coupons['salescost_provider'])){
				$dataArr['salescost_provider'] = 0;
			}

			if($coupons['issue_priod_type'] == 'day'){
				$dataArr['issue_startdate']	= $regDate;
				$dataArr['issue_enddate']	= date("Y-m-d", strtotime("+ ".$coupons['after_issue_day']." days"));
			}else{
				$dataArr['issue_startdate']	= $coupons['issue_startdate'];
				$dataArr['issue_enddate']	= $coupons['issue_enddate'];
			}
		}
		$dataArr['use_status']		= 'unused';
		$dataArr['regist_date']		= $regDate." ".$regTime;
		$dataArr['use_date']		= '0000-00-00 00:00:00';

		foreach($memberArr as $member_seq){
			// 쿠폰 정보 확인
			$memberCoupon = $this->couponmodel->get_admin_download($member_seq, $coupons['coupon_seq']);
			if($memberCoupon) continue; //이미 다운받은 쿠폰이 있음.

			$dataArr['member_seq']  = $member_seq;
			$dataBatch[]			= $dataArr;

			$totalCnt++;
		}
		
		if(!$dataBatch){

			$callback_msg = "쿠폰을 지급한 대상이 없습니다.";

		}else{

		$this->db->insert_batch("fm_download", $dataBatch, NULL, $limit);
		$firstId = $this->db->insert_id();
		$lastId  = $firstId + $totalCnt;

		unset($this->db->queries);
		unset($this->db->query_times);
		unset($memberArr, $dataBatch);

		//카테고리 쿠폰 입력
		if($couponCategory){
			foreach($couponCategory	as $v){
				$dataBatchCategory = array();
				for($i=$firstId; $i<$lastId; $i++){
					$dataCategory					= array();
					$dataCategory['category_code']	= $v['category_code'];
					$dataCategory['type']			= $v['type'];
					$dataCategory['download_seq']	= $i;
					$dataBatchCategory[]			= $dataCategory;
				}
				$this->db->insert_batch("fm_download_issuecategory", $dataBatchCategory);
				unset($this->db->queries);
				unset($this->db->query_times);
			}
		}
		unset($couponCategory, $dataBatchCategory);

		//상품 쿠폰 입력
		if($couponGoods){	
			foreach($couponGoods as $v){
				$dataBatchGoods = array();
				for($i=$firstId; $i<$lastId; $i++){
					$dataGoods					= array();
					$dataGoods['goods_seq']		= $v['goods_seq'];
					$dataGoods['type']			= $v['type'];
					$dataGoods['download_seq']	= $i;
					$dataBatchGoods[]		= $dataGoods;
				}
				$this->db->insert_batch("fm_download_issuegoods", $dataBatchGoods);
				unset($this->db->queries);
				unset($this->db->query_times);
			}
		}
		unset($couponGoods, $dataBatchGoods);

			$callback_msg = $totalCnt."명에게 쿠폰이 지급 되었습니다.";

		}

		$callback = "parent.location.reload();";
		openDialogAlert($callback_msg, 400, 140, 'parent', $callback);
		exit;
	}

	//쿠폰복사
	public function coupon_copy()
	{

		/* 관리자 권한 체크 : 시작 */
		$auth = $this->authmodel->manager_limit_act('coupon_act');
		if(!$auth){
			$callback = "";
			openDialogAlert($this->auth_msg,400,140,'parent',$callback);
			exit;
		}
		/* 관리자 권한 체크 : 끝 */

		// 쿠폰 validation 체크 추가 
		$this->load->library('validation');

		$aPostParams = $this->input->post();

		$this->validation->set_rules('coupon_name','쿠폰명','trim|required|xss_clean');
		
		if ($this->validation->exec() === false) {
			$err = $this->validation->error_array;
			$callback = "if(parent.document.getElementsByName('{$err['key']}')[0]) parent.document.getElementsByName('{$err['key']}')[0].focus();";
			openDialogAlert($err['value'], 400, 140, 'parent', $callback);
			exit;
		}

		$coupons 	= $this->couponmodel->get_coupon($aPostParams['copy_coupon_seq']);
		if($coupons) {
			$nokey = array('coupon_seq', 'coupon_name', 'coupon_desc', 'coupon_image4', 'regist_date','update_date');
			foreach($coupons as $key=>$val) {
				if(in_array($key,$nokey)) continue;
				$paramCoupon[$key] = $val;
			}

			$paramCoupon['coupon_name']	= $aPostParams['coupon_name'];
			$paramCoupon['coupon_desc']	= $aPostParams['coupon_desc'];
			$paramCoupon['regist_date']		= date("Y-m-d H:i:s");
			$paramCoupon['update_date']	= $paramCoupon['regist_date'];
			$result =$this->db->insert('fm_coupon', $paramCoupon);
			$new_coupon_seq = $this->db->insert_id();

			if($result){
				if(@is_file($this->couponmodel->copuonupload_dir.$coupons['coupon_image4'])) {
					@copy($this->couponmodel->copuonupload_dir.$coupons['coupon_image4'],$this->couponmodel->copuonupload_dir.$new_coupon_seq.'_'.$coupons['coupon_image4']);//파일복사
					$coupon_image4 = $new_coupon_seq.'_'.$coupons['coupon_image4'];
					$this->db->where('coupon_seq', $new_coupon_seq);
					$this->db->update('fm_coupon', array('coupon_image4' => $coupon_image4));
				}

				$couponGroups 	= $this->couponmodel->get_coupon_group($aPostParams['copy_coupon_seq']);
				$couponcategorys 	= $this->couponmodel->get_coupon_issuecategory($aPostParams['copy_coupon_seq']);
				$couponGoods 	= $this->couponmodel->get_coupon_issuegoods($aPostParams['copy_coupon_seq']);

				$paramGroup = array();
				if($couponGroups){
					foreach($couponGroups as $key => $group){
						$paramGroup['coupon_seq']		= $new_coupon_seq;
						$paramGroup['group_seq']		= $group['group_seq'];
						$this->db->insert('fm_coupon_group', $paramGroup);
					}
				}

				$paramGoods = array();
				if($couponGoods){
					foreach($couponGoods as $key => $Goods){
						$paramGoods['coupon_seq']	= $new_coupon_seq;
						$paramGoods['goods_seq']		= $Goods['goods_seq'];
						$paramGoods['type']				= $Goods['type'];
						$this->db->insert('fm_coupon_issuegoods', $paramGoods);
					}
				}

				$paramCategory = array();
				if($couponcategorys){
					foreach($couponcategorys as $key => $Categorys){
						$paramCategory['coupon_seq']		= $new_coupon_seq;
						$paramCategory['category_code']	= $Categorys['category_code'];
						$paramCategory['type']					= $Categorys['type'];
						$this->db->insert('fm_coupon_issuecategory', $paramCategory);
					}
				}

				$callback = "parent.document.location.reload();";
				openDialogAlert("쿠폰을 복사하였습니다.",400,140,'parent',$callback);
			}else{
				openDialogAlert("쿠폰복사가 실패하였습니다.",400,140,'parent','');
			}
		}else{
			openDialogAlert("잘못된 접근입니다.",400,140,'parent','');
		}
	}


	//쿠폰발급 > sql 회원검색 전체인경우
	public function download_member_search_all()
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
		### SEARCH
		$sc = $_POST;
		$sc['search_text']		= ($sc['search_text'] == '이름, 아이디, 이메일, 전화번호, 핸드폰(뒷자리4), 주소') ? '':$sc['search_text'];
		$sc['keyword']			= $sc['search_text']; 
		$sc['orderby']		= 'A.member_seq';
		$sc['nolimit']		= 'y';
		
		### MEMBER
		$i=0;
		$data = $this->membermodel->coupon_member_list($sc);		// 간략데이터
		//$data = $this->membermodel->admin_member_list($sc);//popup_member_list($sc);
		foreach($data['result'] as $datarow){
			$download_coupons = $this->couponmodel->get_admin_download($datarow['member_seq'], $_POST['no']);
			if(!$download_coupons) {
				$searchallmember[$i]['user_name'] = $datarow['user_name'];
				$searchallmember[$i]['userid']			 = $datarow['userid'];
				$searchallmember[$i]['member_seq']			 = $datarow['member_seq'];
				$i++;
			}
		}

		$result = array('searchallmember'=>$searchallmember,'totalcnt'=>$i);
		echo json_encode($result);
		exit;
	}

	//쿠폰발급하기 > 발급대상찾기시 다운로드 권한 보여주기
	public function download_coupon_info()
	{
		$coupons 			= $this->couponmodel->get_coupon($_POST['couponSeq']);
		$couponGroups 		= $this->couponmodel->get_coupon_group($_POST['couponSeq']);
		$download_limitlay 	= $download_groupsMsglay = $download_datelay = '';
		$typearraydate 		= array('download','shipping','admin');

		if($coupons) {
			/* 회원 그룹 개발시 변경*/
			$groups = "";
			$query = $this->db->query("select group_seq,group_name from fm_member_group");
			foreach($query->result_array() as $row){
				$groups[] = $row;
			}
			/******************/
			$dsc['whereis'] = ' and coupon_seq='.$coupons['coupon_seq'];
			$downloadtotal 	= $this->couponmodel->get_download_total_count($dsc);
			$downloadtotal	= number_format($downloadtotal);//발급수

			$download_limitlay = ($coupons['download_limit'] == 'limit')? '현재 '.$downloadtotal.'건 / 누적 '.$coupons['download_limit_ea'].'<input type="hidden" name="downloadtotal" id="downloadtotal" value="'.$downloadtotal.'"><input type="hidden" name="download_limit_ea" id="download_limit_ea" value="'.$coupons['download_limit_ea'].'">':'제한없음';
			$download_limitlay .= '<input type="hidden" name="download_limit" id="download_limit" value="'.$coupons['download_limit'].'">';
			if($couponGroups){
				$download_groupsMsglay = '다운로드 권한은 ';
				$i=0;
				foreach($couponGroups as $key => $group){$coupongroupsar[] = $group['group_seq'];
					foreach($groups as $tmp){
						if($tmp['group_seq'] == $group['group_seq']){
							$download_groupsMsglay .= '<strong>'.$tmp['group_name'].'</strong><input type="hidden" name="download_memberGroups[]" value="'.$tmp['group_seq'].'">';
						}
					}$i++;
				}
				$download_groupsMsglay .= '회원 ';
				$this->load->model('membermodel');
				$sc['groupsar'] = $coupongroupsar;
				$downloadmbtotalcountlay = '전체회원 '.number_format($this->membermodel->get_member_total_count($sc)).'명 <input type="hidden" name="member_total_count" id="member_total_count" value="'.$this->membermodel->get_item_total_count($sc).'">';
			}else{
				$this->load->model('membermodel');
				$downloadmbtotalcountlay = '전체회원 '.number_format($this->membermodel->get_member_total_count()).'명 <input type="hidden" name="member_total_count" id="member_total_count" value="'.$this->membermodel->get_item_total_count().'">';
				$download_groupsMsglay = '다운로드 권한 제한은 없습니다.';
			}

			$coupons['date']			= ($coupons['update_date'])?substr($coupons['update_date'],2,14):substr($coupons['regist_date'],2,14);//등록일
			$coupons['limit_goods_price'] = number_format($coupons['limit_goods_price']);

			if($coupons['type'] == 'download' || $coupons['type'] == 'shipping' || $coupons['type'] == 'offline_coupon' || $coupons['type'] == 'offline_emoney' ){//다운로드/배송비
				$coupons['downloaddate']	= ($coupons['download_startdate'] && $coupons['download_enddate'])?substr($coupons['download_startdate'],2,8).' ~ '.substr($coupons['download_enddate'],2,8):'기간제한없음';
			}elseif($coupons['type'] == 'birthday'){//생일자
				$coupons['downloaddate']	= '생일 '.$coupons['before_birthday'].'일전 ~ '.$coupons['after_birthday'].'일이후까지';
			}elseif($coupons['type'] == 'anniversary'){//기념일
				$coupons['downloaddate']	= '기념일 '.$coupons['before_anniversary'].'일전 ~ '.$coupons['after_anniversary'].'일이후까지';
			}elseif($coupons['type'] == 'memberGroup'){//회원등급
				$coupons['downloaddate']	= '등급조정일로부터 '. ($coupons['after_upgrade']).'일';
			}else{
				$coupons['downloaddate']	= '-';
			}

			if($coupons['type'] == 'birthday' || $coupons['type'] == 'anniversary' || $coupons['type'] == 'memberGroup' || $coupons['type'] == 'member' ){//직접발급시
				$coupons['issuedate']	= '발급일로부터 '.number_format($coupons['after_issue_day']).'일';
			}else{
				if( $coupons['issue_priod_type'] == 'date' ) {
					$coupons['issuedate']	= substr($coupons['issue_startdate'],2,10).' ~ '.substr($coupons['issue_enddate'],2,10);
				}else{
					$coupons['issuedate']	= '발급일로부터 '.number_format($coupons['after_issue_day']).'일';
				}
			}

			if( $coupons['type'] == 'shipping' || strstr($coupons['type'],'_shipping') ){//배송비
				$coupons['salepricetitle']	= ($coupons['shipping_type'] == 'free' ) ? '무료, 최대 '.get_currency_price($coupons['max_percent_shipping_sale'],3): '배송비 '.get_currency_price($coupons['won_shipping_sale'],3);//
			}elseif($coupons['type'] == 'offline_emoney' ){//오프라인 마일리지쿠폰
				$coupons['salepricetitle']	='마일리지 '.get_currency_price($coupons['offline_emoney'],3).' 지급';
			}else{
				$coupons['salepricetitle']	= ($coupons['sale_type'] == 'percent' ) ? $coupons['percent_goods_sale'].'% 할인, 최대 '.get_currency_price($coupons['max_percent_goods_sale'],3): '판매가격의 '.get_currency_price($coupons['won_goods_sale'],3);
			}

			$dsc['whereis'] = ' and coupon_seq='.$coupons['coupon_seq'];
			$downloadtotal = $this->couponmodel->get_download_total_count($dsc);
			$coupons['downloadtotal']	= number_format($downloadtotal);//발급수

			$usc['whereis'] = ' and coupon_seq='.$coupons['coupon_seq'].' and use_status = \'used\' ';
			$usetotal = $this->couponmodel->get_download_total_count($usc);

			$coupons['usetotal']			= number_format($usetotal);//사용건수

			$coupons['issueimg'] = (strstr($coupons['type'],'offline'))?'offline':'online';
			if($coupons['type'] == 'admin' ){//직접발급시
				$coupons['issuebtn']	= (( $coupons['issue_priod_type'] == 'date' && str_replace("-","", substr($coupons['issue_enddate'],0,10)) < date("Ymd"))) ? '':'<span class="btn small cyanblue"><button type="button" class="downloa_write_btn" coupon_seq="'.$coupons['coupon_seq'].'" download_limit="'.$coupons['download_limit'].'" coupon_name="'.$coupons['coupon_name'].'" >발급하기</button></span>';
			}else{
				$coupons['issuebtn']	= $this->couponmodel->couponTypeTitle[$coupons['type']];
			}

			$result = array('downloadmbtotalcountlay'=>$downloadmbtotalcountlay,'download_limitlay'=>$download_limitlay,'download_groupsMsglay'=>$download_groupsMsglay,'download_datelay'=>$download_datelay,'coupon'=>$coupons);
			echo json_encode($result);
			exit;
		}
	}

	//발급(인증)받은쿠폰삭제하기
	public function download_delete()
	{
		$delseqar = @explode(",",$_POST['delseqar']);
		$delnum = 0;
		for($i=0;$i<sizeof($delseqar);$i++){ if(empty($delseqar[$i]))continue;
			$download_seq = $delseqar[$i];
			$download_coupons 	= $this->couponmodel->get_download_coupon($download_seq);
			if($download_coupons['use_status'] != 'used') {//미사용만 삭제함
				$this->db->delete('fm_download_issuecategory', array('download_seq' => $download_seq));
				$this->db->delete('fm_download_issuegoods', array('download_seq' => $download_seq));
				$result = $this->db->delete('fm_download', array('download_seq' => $download_seq));
				if($result) {
					$downloadcnt++;
				}
			}
		}

		if($downloadcnt > 0) {
			$result = array( 'result'=>true, 'downloadcnt'=>$downloadcnt,'msg'=>"선택된 ".$downloadcnt."건의 회원들에게 지급된 해당 쿠폰을<br/>정상적으로 삭제하였습니다.");
		}else{
			$result = array( 'result'=>false,'msg'=>"발급(인증)쿠폰삭제를 실패하였습니다.");
		}

		echo json_encode($result);
		exit;
	}

	// 발급 된 미사용 쿠폰 전체삭제 :: 2019-09-02 pjw
	public function download_all_delete()
	{
		/* 관리자 권한 체크 : 시작 */
		$auth = $this->authmodel->manager_limit_act('coupon_act');
		if(!$auth){
			$callback = "";
			openDialogAlert($this->auth_msg,400,140,'parent',$callback);
			exit;
		}
		/* 관리자 권한 체크 : 끝 */
		
		// 발급 쿠폰 전체 삭제
		$coupon_seq			= $this->input->post('coupon_seq');
		$delete_result		= $this->couponmodel->delete_unused_download($coupon_seq);

		echo json_encode($delete_result);
		exit;
	}

	public function couponpopupuse(){
		config_save("couponpopupuse" ,array($_POST['type'].'_popup_use'=>$_POST['couponpopupuse'])); 
		$result = array("result"=>true,"msg"=>"설정이 저장 되었습니다");
		echo json_encode($result);
		exit;
	}

}

/* End of file coupon_process.php */
/* Location: ./app/controllers/admin/category.php */
