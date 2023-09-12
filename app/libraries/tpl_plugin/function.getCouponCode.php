<?php
/* 쿠폰 다운로드 소스 */
function getCouponCode($type = null)
{
	$CI =& get_instance(); 
	if(!$CI->goodsmodel) $CI->load->model('goodsmodel');
	if(!$CI->membermodel) $CI->load->model('membermodel');
	if(!$CI->couponmodel) $CI->load->model('couponmodel');
	if(!$CI->categorymodel) $CI->load->model('categorymodel');
	$CI->load->helper('coupon');
	$CI->load->helper('member');

	$today = date('Y-m-d',time()); 
	if( number_format($type) > 0 ) {
		$couponseq = (int) $type;
		$getCouponCodecoupons 			= ($CI->couponinfo)?$CI->couponinfo:$CI->couponmodel->get_coupon($couponseq);
		$sc['coupon_seq'] = $getCouponCodecoupons['coupon_seq'];
		$type = $getCouponCodecoupons['type'];
	}

	if( !($CI->managerInfo && $_GET['previewlayer'] && ($type == 'birthday' || $type == 'anniversary' || $type =='memberGroup' ) ) && $CI->userInfo['member_seq']) {
		$sc['member_seq']	= $CI->userInfo['member_seq'];
	
		$CI->mdata = $CI->membermodel->get_member_data($CI->userInfo['member_seq']);//회원정보  
	
		if( !empty($CI->mdata['birthday']) && $CI->mdata['birthday'] != '0000-00-00' ) {
			$CI->mdata['thisyear_birthday'] = date("Y").substr($CI->mdata['birthday'],4,6);
			if(checkdate(substr($CI->mdata['thisyear_birthday'],5,2),substr($CI->mdata['thisyear_birthday'],8,2),substr($CI->mdata['thisyear_birthday'],0,4)) != true) {
				$CI->mdata['thisyear_birthday'] = date("Y-m-d",strtotime('-1 day', strtotime($CI->mdata['thisyear_birthday'])));
			}
			//한국나이
			$birthyear = date("Y", strtotime($CI->mdata['birthday'])); //생년
			$nowyear = date("Y"); //현재년도
			$CI->mdata['birthday_age'] = $nowyear-$birthyear+1; 
		}

		if ( !empty($CI->mdata['anniversary']) ) {
			$CI->mdata['thisyear_anniversary'] = date("Y").'-'.$CI->mdata['anniversary'];//기념일(mm-dd) 추가
			if(checkdate(substr($CI->mdata['thisyear_anniversary'],5,2),substr($CI->mdata['thisyear_anniversary'],8,2),substr($CI->mdata['thisyear_anniversary'],0,4)) != true) {
				$CI->mdata['thisyear_anniversary'] = date("Y-m-d",strtotime('-1 day', strtotime($CI->mdata['thisyear_anniversary'])));
			}
		}
		
		$mb_group_sort = mb_group_sort();//회원등급순서 재정렬
		//등급조정쿠폰의 등업된 경우에만 다운가능
		if ($CI->mdata['grade_update_date'] != '0000-00-00 00:00:00') {
			$fm_member_group_logsql = "select * from fm_member_group_log where member_seq = '".$CI->userInfo['member_seq']."' order by regist_date desc limit 0,1";
			$fm_member_group_logquery = $CI->db->query($fm_member_group_logsql);  
			$fm_member_group_log =  $fm_member_group_logquery->row_array(); 
			if( ($mb_group_sort[$fm_member_group_log['prev_group_seq']] >= $mb_group_sort[$fm_member_group_log['chg_group_seq']]) || ($CI->userInfo['group_seq'] == 1) ) {
				$CI->mdata['grade_update_date'] = '';
			}
		}else{
			$CI->mdata['grade_update_date'] = substr($CI->mdata['regist_date'],0,10);
		}

		if($CI->mdata['birthday'] == '0000-00-00') $CI->mdata['birthday'] ='';
		if($CI->mdata['anniversary'] == '00-00') $CI->mdata['anniversary'] ='';
		if(!$CI->mdata['user_name']) $CI->mdata['user_name'] =$CI->mdata['userid'];
		if($CI->mdata) $CI->template->assign($CI->mdata); 
	}else{
		if( $CI->managerInfo && $_GET['previewlayer'] && ($type == 'birthday' || $type == 'anniversary' || $type =='memberGroup' )  ) {  
				$CI->template->assign(array("user_name"=>"관리자테스트","birthday"=>"0000-00-00","anniversary"=>"00-00","birthday_age"=>"00","group_name"=>"관리자")); 
		}
	}

	### SEARCH 
	$sc['today']		= $today;
	$sc['coupon_type'][]		= $type;
	if( in_array($type, $CI->couponmodel->couponshipping) ) {//배송비포함된 쿠폰
		$sc['coupon_type'][]		= $type."_shipping";
	}
	$sc['year']			= date('Y',time());
	$sc['month']		= date('Y-m',time());
	
	if( !($CI->managerInfo && $_GET['previewlayer'] && ($type == 'birthday' || $type == 'anniversary' || $type =='memberGroup' ) ) && $CI->userInfo['member_seq'] &&  in_array($type,$CI->couponmodel->couponpagetype['mypage']) ) {//생일자/기념일/회원등급
		$datacouponcode = $CI->couponmodel->get_my_download($sc,$CI->mdata, 'couponall');
		$CI->eventgroups[] = array("group_seq"=>$CI->mdata['group_seq'],"group_name"=>$CI->mdata['group_name']); 
	}else{//그외 모든쿠폰적용
		if( !($CI->managerInfo && $_GET['previewlayer']) && $CI->userInfo['member_seq'] && ( $type == 'birthday' || $type == 'anniversary' || $type =='memberGroup') ) {
			$datacouponcode = $CI->couponmodel->get_promotion_coupon_my_download($sc,$CI->mdata, 'couponall'); 
		}else{
			$datacouponcode = $CI->couponmodel->get_promotion_coupon_download($sc,$CI->mdata, 'couponall');
		}
		$CI->eventgroups = "";
		 
		$grquery = $CI->db->query("select group_seq,group_name  from fm_member_group order by order_sum_price desc, order_sum_ea desc, order_sum_cnt desc, use_type asc");
		if($grquery->result_array()) {
			foreach($grquery->result_array() as $row){
				$CI->eventgroups[] = $row; 
			}
		} 
	}
	if($CI->mdata) $mycouponcode = $CI->couponmodel->get_my_download($sc,$CI->mdata, 'couponall'); 

	$couponcodeidx = 0; 
	foreach($datacouponcode['result'] as $datarow){$couponcodeidx++;
		if($today > $datarow['download_enddate'] && $datarow['download_enddate'] )continue;
		if( !($datarow['type'] == 'member' || $datarow['type'] == 'member_shipping') && $CI->userInfo['member_seq'] ) {
			$datarow['coupondowninfo'] = (!($CI->managerInfo && $_GET['previewlayer'] && ($type == 'birthday' || $type == 'anniversary' || $type =='memberGroup' ) ))?$CI->couponmodel->get_admin_download($CI->userInfo['member_seq'], $datarow['coupon_seq']):"";
			getcouponcodewonloadcheck($datarow,$mycouponcode,$CI->mdata);//다운받았을 경우이거나 조건대상이 아닌것   
		}
		$datarow = downloadable_tab2($today, $datarow);
		if( $type == 'membermonths' ||  $type == 'membermonths_shipping'  ) {
			getcoupongrouparray($datarow, $datagroup);
		}

		// 제한 금액 화폐 절사 기준에 맞춰 처리
		$datarow['limit_goods_price_txt'] = get_currency_price($datarow['limit_goods_price'],2);
		
		$dataloop[] = $datarow;
	} 
	$CI->template->assign('getCouponCodedatacnt',$couponcodeidx);

	//이달의 쿠폰 전체
	if( $type == 'membermonths' ||  $type == 'membermonths_shipping'  ) { 
		$totalcoupondowninfouse = (count($mycouponcode['result'])>0)?false:true;//이달의 쿠폰다운여부
		$CI->template->assign('totalcoupondowninfouse',$totalcoupondowninfouse);
		$CI->template->assign('getCouponCodedata',$datagroup);
		$CI->dataloopcouponcode = $datagroup; 
	}else{ 
		$CI->template->assign('getCouponCodedata',$dataloop);
		$CI->dataloopcouponcode = $dataloop; 
	}
	
}
?>