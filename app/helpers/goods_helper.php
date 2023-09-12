<?php
/**
* 상품코드 자동등록처리
**/
function goodscodeautock($no, $mode=null){
	if(!$no) return;

	$CI =& get_instance();
	if( serviceLimit('H_FR') ) return;//무료몰불가
	
	$CI->load->model('categorymodel');
	$CI->load->model('brandmodel');
	$CI->load->model('goodsmodel');
	$additions = $CI->goodsmodel->get_goods_addition($no);
	$categoriesdefault = $CI->goodsmodel->get_goods_category_default($no);
	$categorycode = $CI->categorymodel->get_category_goods_code($categoriesdefault['category_code']);
	$brandsdefault = $CI->goodsmodel->get_goods_brand_default($no);
	$brandcode = $CI->brandmodel->get_brand_goods_code($brandsdefault['category_code']);

	if( !$CI->goods_code_form_arr ) {
		$qry = "select * from fm_goods_code_form  where label_type ='goodsaddinfo'  and codesetting=1 order by sort_seq";
		$query = $CI->db->query($qry);
		$CI->goods_code_form_arr = $query -> result_array();
	}

	foreach ($CI->goods_code_form_arr as $datarow){
		if( $datarow['label_code'] == 'goods_seq|' ){//상품고유번호
			$returncode[] = $no;
		}elseif( $datarow['label_code'] == 'category|' ){
			$returncode[] = str_replace(" > ","",$categorycode);
		}elseif( $datarow['label_code'] == 'brand|' ){
			$returncode[] =  str_replace(" > ","",$brandcode);
		}else{
			foreach($additions as $addition){
				if($addition['code_seq'] == $datarow['codeform_seq']) {
					$returncode[] = $addition['contents'];
				}
			}//endforeach
		}//endif
	}//endforeach

	if($returncode){
		$tmpreturncode = (is_array($returncode))?implode('',$returncode):'';

		// 검색어 추출
		$gdquery = "select goods_name, summary, keyword from fm_goods where goods_seq=? limit 1";
		$gdquery = $CI->db->query($gdquery,array($no));
		$result		= $gdquery->result_array();
		$goods		= $result[0];

		$arr_tmp_keyword = array();
		$result_keyword = $CI->goodsmodel->set_search_keyword($no,$tmpreturncode,strip_tags($goods['goods_name']),strip_tags($goods['summary']),$goods['keyword']);
		if( $result_keyword['keyword'] )			$arr_tmp_keyword[] = $result_keyword['keyword'];
		if( $result_keyword['auto_keyword'] )	$arr_tmp_keyword[] = $result_keyword['auto_keyword'];
		$tmpkeyword = (is_array($arr_tmp_keyword))?implode(',',$arr_tmp_keyword):'';

		if($mode == 'batch'){
			return array('tmpreturncode'=>$tmpreturncode,'tmpkeyword'=>$tmpkeyword);
		}else{
			// 상품테이블에 상품코드/검색어 저장
			$query = "update fm_goods set goods_code=?, keyword=? where goods_seq=?";
			$CI->db->query($query,array($tmpreturncode,$tmpkeyword,$no));
		}
	}
}

function goodscodeautockview(){
	//if( serviceLimit('H_FR') ) return;//무료몰불가

	$CI =& get_instance();
	$CI->load->model('goodsmodel');
	$CI->load->model('categorymodel');
	$CI->load->model('brandmodel');

	$no	= ($_POST['no'])? $_POST['no']:'';
	$category_goods_code	= ($_POST['category_goods_code'])? $_POST['category_goods_code']:'';
	$brand_goods_code		= ($_POST['brand_goods_code'])? $_POST['brand_goods_code']:'';
	$addtion_goods_seq		= ($_POST['addtion_goods_seq'])? $_POST['addtion_goods_seq']:'';
	$addtion_goods_code		= ($_POST['addtion_goods_code'])? $_POST['addtion_goods_code']:'';
	$addtion_goods_seq = explode(",",$addtion_goods_seq);
	$addtion_goods_code = explode(",",$addtion_goods_code);

	$categorycode = $CI->categorymodel->get_category_goods_code($category_goods_code);
	$brandcode = $CI->brandmodel->get_brand_goods_code($brand_goods_code);

	$qry = "select * from fm_goods_code_form  where label_type ='goodsaddinfo'  and codesetting=1 order by sort_seq";
	$query = $CI->db->query($qry);
	$user_arr = $query -> result_array();
	foreach ($user_arr as $datarow){

		if( $datarow['label_code'] == 'goods_seq|' ){//상품고유번호
			$returncode[] = $no;
		}elseif( $datarow['label_code'] == 'category|' ){
			$returncode[] = str_replace(" > ","",$categorycode);
		}elseif( $datarow['label_code'] == 'brand|' ){
			$returncode[] =  str_replace(" > ","",$brandcode);;
		}else{
			foreach($addtion_goods_seq as $key=>$addition){
				if($addition == $datarow['codeform_seq']) {
					$returncode[] = $addtion_goods_code[$key];
				}
			}
		}
	}

	return implode('',$returncode);
}

// 상품코드 자동생성 - 다중생성 :: 2016-11-23 lwh
function goodscodemulti($goods_seq){

	if( serviceLimit('H_FR') ) return;//무료몰불가

	$CI =& get_instance();
	$CI->load->model('goodsmodel');
	$CI->load->model('categorymodel');
	$CI->load->model('brandmodel');

	// goods_seq 배열화
	if(strpos($goods_seq, ',')){
		$seq_arr = explode(',', $goods_seq);
	}else{
		$seq_arr[] = $goods_seq;
	}

	foreach($seq_arr as $key => $no){
		// 상품 정보 추출
		$goods_sql = "
			select 
				g.goods_seq, 
				(select category_code from fm_category_link where link=1 and goods_seq=g.goods_seq limit 1) as category_code,
				(select category_code from fm_brand_link where link=1 and goods_seq=g.goods_seq limit 1) as brand_code
			from
				fm_goods as g 
			where 
				g.goods_seq = '" . $no . "'
		";
		$query		= $CI->db->query($goods_sql);
		$g_info		= $query->row_array();

		$category_goods_code	= ($g_info['category_code'])? $g_info['category_code']:null;
		$brand_goods_code		= ($g_info['brand_code'])? $g_info['brand_code']:null;

		$addtion_sql = "
			select
				ad.code_seq, ad.contents
			from
				fm_goods as g, fm_goods_addition as ad
			where
				g.goods_seq = ad.goods_seq and
				g.goods_seq = '" . $no . "' and
				ad.code_seq > 0
		";
		$query		= $CI->db->query($addtion_sql);
		$add_info	= $query->result_array();
		foreach($add_info as $k => $add){
			$addtion_goods_seq[$k]	= $add['code_seq'];
			$addtion_goods_code[$k] = $add['contents'];
		}
		// 추가정보 추출
		$addtion_goods_seq = explode(",",$addtion_goods_seq);
		$addtion_goods_code = explode(",",$addtion_goods_code);

		if($category_goods_code){
			$categorycode	= $CI->categorymodel->get_category_goods_code($category_goods_code);
		}
		if($brand_goods_code){
			$brandcode		= $CI->brandmodel->get_brand_goods_code($brand_goods_code);
		}

		$qry = "
			select * 
			from 
				fm_goods_code_form 
			where 
				label_type ='goodsaddinfo' and codesetting = 1 
			order by sort_seq
		";
		$query = $CI->db->query($qry);
		$user_arr = $query -> result_array();
		unset($returncode);
		foreach ($user_arr as $datarow){

			if( $datarow['label_code'] == 'goods_seq|' ){//상품고유번호
				$returncode[] = $no;
			}elseif( $datarow['label_code'] == 'category|' ){
				$returncode[] = str_replace(" > ","",$categorycode);
			}elseif( $datarow['label_code'] == 'brand|' ){
				$returncode[] =  str_replace(" > ","",$brandcode);;
			}else{
				foreach($addtion_goods_seq as $key=>$addition){
					if($addition == $datarow['codeform_seq']) {
						$returncode[] = $addtion_goods_code[$key];
					}
				}
			}
		}

		$rescode[$no] = implode('',$returncode);
	}

	return $rescode;
}

// 상품코드추가입력항목 추출
function getGoodsCodeForm($sc = array()){
	$CI			=& get_instance();
	$selectSql	= "SELECT * ";
	$fromSql	= "FROM fm_goods_code_form ";
	$whereSql	= "WHERE codeform_seq > 0 ";
	$groupSql	= "";
	$orderSql	= "ORDER BY sort_seq asc ";
	$limitSql	= "";

	// 코드 구분 검색
	if	($sc['label_type']){
		$whereSql	.= " AND label_type = ? ";
		$addBind[]	= $sc['label_type'];
	}

	$sql		= $selectSql . $fromSql . $whereSql . $groupSql . $orderSql . $limitSql;
	$query		= $CI->db->query($sql, $addBind);
	$result		= $query->result_array();

	return $result;
}

/**
* @입점사상품승인, 미승인
**/

function goodsstatustotalnum($status){
	$CI =& get_instance();

	if( preg_match("/goods\/catalog*/",uri_string()) || preg_match("/goods\/social_catalog*/",uri_string()) ) {
		if( defined('SOCIALCPUSE') === true ) {
			$gdkind = " and goods_kind != 'goods' ";
		}else{
			$gdkind = " and goods_kind = 'goods' ";
		}
	}

	if( defined('__SELLERADMIN__') === true ){
		$sql = "SELECT count(*) as cnt FROM fm_goods WHERE goods_type = 'goods' and provider_seq='".$CI->providerInfo['provider_seq']."' ";
		if( $status == '1' ){
			$sql .= " and provider_status='".$status."'";
		}else{
			$sql .= " and (provider_status='0' or provider_status is null or provider_status='') ";
		}

	}else{
		if( $status == '1' ){
			$sql = "SELECT count(*) as cnt FROM fm_goods WHERE goods_type = 'goods' and provider_status='".$status."'";
		}else{
			$sql = "SELECT count(*) as cnt FROM fm_goods WHERE goods_type = 'goods'  and (provider_status='0' or provider_status is null or provider_status='') ";
		}
	}
	$query	= $CI->db->query($sql.$gdkind);
	$row = $query->row();
	$cnt = $row->cnt;
	return $cnt;
}

/**
*
* @입점사 상태자동처리
* @상품명/정가/할인가격 변경시
**/
function goodsinfochange() {
	if(!$_POST['goodsSeq'])return;
	$CI =& get_instance();
	$CI->load->model('goodsmodel');
	$oldgoods 			= $CI->goodsmodel->get_goods($_POST['goodsSeq']);
	$oldoptions 		= $CI->goodsmodel->get_goods_option($_POST['goodsSeq']);
	$oldsuboptions 		= $CI->goodsmodel->get_goods_suboption($_POST['goodsSeq']);
	$goodsinfochangeuse = array('result'=>false,'msg'	=> '');
	//debug_var($_POST);debug_var($oldgoods);debug_var($oldoptions);debug_var($oldsuboptions);

	//상품명
	if( trim($oldgoods['goods_name']) != trim($_POST['goodsName']) ) {
		$msg = "상품명 ".addslashes($oldgoods['goods_name'])." → ".addslashes($_POST['goodsName'])."로 변경되었습니다.";
		$goodsinfochangeuse = array('result'=>true,'msg'=>$msg,'type'=>'상품명');
		return $goodsinfochangeuse;
	}

	/* 필수옵션 fm_goods_option */
	if($goodsinfochangeuse['result'] === false) {
		//debug_var("size -> " . count($oldoptions) . " == " .count($_POST['price']));
		if( count($oldoptions) != count($_POST['price']) ) {//필수옵션갯수체크
			$msg = "필수옵션 갯수정보가 변경되었습니다.";
			$goodsinfochangeuse = array('result'=>true,'msg'=>$msg,'type'=>'필수옵션');
			return $goodsinfochangeuse;
		}

		$i=0;
		foreach($_POST['price'] as $key => $price) {
			//debug_var($key."consumer price -> " . $_POST['consumerPrice'][$key] . " == " .$oldoptions[$key]['consumer_price']);
			if( $_POST['consumerPrice'][$key] != $oldoptions[$key]['consumer_price'] ) {//정가
				$msg = "필수옵션의 정가 ".number_format($oldoptions[$key]['consumer_price'])." → ".number_format($_POST['consumerPrice'][$key])."로 변경되었습니다.";
				$goodsinfochangeuse = array('result'=>true,'msg'=>$msg,'type'=>'필수옵션 정가');
				return $goodsinfochangeuse;
			}


			//debug_var($key."price -> " . $_POST['price'][$key] . " == " .$oldoptions[$key]['price']);
			if( $_POST['price'][$key] != $oldoptions[$key]['price'] ) {//할인가
				$msg = "필수옵션의 할인가 ".number_format($oldoptions[$key]['price'])." → ".number_format($_POST['price'][$key])."로 변경되었습니다.";
				$goodsinfochangeuse = array('result'=>true,'msg'=>$msg,'type'=>'필수옵션 할인가');
				return $goodsinfochangeuse;
			}
			$i++;
		}
	}

	/* 추가옵션 fm_goods_suboption */
	if($goodsinfochangeuse['result'] === false) {
		//debug_var("size -> " . count($oldsuboptions) . " == " .count($_POST['subopt']));
		if(!$oldsuboptions) $oldsuboptions = array();
		if(!$_POST['subopt']) $_POST['subopt']	= array();
		$suboptionCnt = 0;
		foreach($oldsuboptions as $k=>$v) $suboptionCnt += count($v);
		if(!$_POST['suboptionSeq']) $_POST['suboptionSeq'] = array();
		if( $suboptionCnt != count($_POST['suboptionSeq'])){//필수옵션갯수체크
			$msg = "추가옵션 갯수정보가 변경되었습니다. (".$suboptionCnt." => ".count($_POST['suboptionSeq']).")";
			$goodsinfochangeuse = array('result'=>true,'msg'=>$msg,'type'=>'추가구성옵션');
			return $goodsinfochangeuse;
		}

		$n = 0;
		foreach($_POST['subopt'] as $i => $tmp){
			//debug_var("sub size -> " . count($oldsuboptions[$n]) . " == " .count($tmp));
			if( count($oldsuboptions[$n]) != count($tmp)){//필수옵션갯수체크
				$msg = "추가옵션 갯수정보가 변경되었습니다.";
				$goodsinfochangeuse = array('result'=>true,'msg'=>$msg,'type'=>'추가구성옵션');
				return $goodsinfochangeuse;
			}
			foreach($tmp as $j => $subopt){
				//debug_var($i."==>".$j." sub consumer price -> " . $_POST['subConsumerPrice'][$j] . " == " .$oldsuboptions[$i][$j]['consumer_price']);
				if( trim($_POST['subConsumerPrice'][$j]) != trim($oldsuboptions[$i][$j]['consumer_price']) ) {//정가
					$msg = "추가옵션의 정가 ".number_format($oldsuboptions[$i][$j]['consumer_price'])." → ".number_format($_POST['subConsumerPrice'][$j])."로 변경되었습니다.";
					$goodsinfochangeuse = array('result'=>true,'msg'=>$msg,'type'=>'추가구성옵션 정가');
					return $goodsinfochangeuse;
				}

				//debug_var($i." sub price -> " . $_POST['subPrice'][$j] . " == " .$oldsuboptions[$i][$j]['price']);
				if( trim($_POST['subPrice'][$j]) != trim($oldsuboptions[$i][$j]['price']) ) {//할인가
					$msg = "추가옵션의 할인가 ".number_format($oldsuboptions[$i][$j]['price'])." → ".number_format($_POST['subPrice'][$j])."로 변경되었습니다.";
					$goodsinfochangeuse = array('result'=>true,'msg'=>$msg,'type'=>'추가구성옵션 할인가');
					return $goodsinfochangeuse;
				}
				$n++;
			}
		}
	}

	if( $oldgoods['goods_kind'] == 'coupon' ){//티켓상품이면
		if( trim($oldgoods['socialcp_cancel_type']) != trim($_POST['socialcp_cancel_type']) ) {
			$msg = "[유효기간 시작 전] 취소(환불) 정보가 변경되었습니다.";//1
			$goodsinfochangeuse = array('result'=>true,'msg'=>$msg,'type'=>'유효기간 전 후 취소(환불)');
			return $goodsinfochangeuse;
		}

		$socialcpcancelar = $CI->goodsmodel->get_goods_socialcpcancel($_POST['goodsSeq']);
		if( $oldgoods['socialcp_cancel_type'] !='payoption' ) {
			$oldgoods['socialcp_cancel_day0'] = $socialcpcancelar[0]['socialcp_cancel_day'];
		}

		if( $oldgoods['socialcp_cancel_type'] == 'pay' ) {//결제확인 후 몇일 이내에 취소(환불) 가능
			if( trim($oldgoods['socialcp_cancel_day0']) != trim($_POST['socialcp_cancel_day'][0]) ) {
				$msg = "[유효기간 시작 전]  취소(환불) 정보가 변경되었습니다.";//2
				$goodsinfochangeuse = array('result'=>true,'msg'=>$msg,'type'=>'유효기간 전 후 취소(환불)');
				return $goodsinfochangeuse;
			}
		}elseif( $oldgoods['socialcp_cancel_type'] == 'payoption' ) {//유효기간 설정
			if( count($socialcpcancelar) != count($_POST['socialcp_cancel_day'])){
				$msg = "[유효기간 시작 전] 취소(환불) 정보가 변경되었습니다.";//3
				$goodsinfochangeuse = array('result'=>true,'msg'=>$msg,'type'=>'유효기간 전 후 취소(환불)');
				return $goodsinfochangeuse;
			}
			foreach($socialcpcancelar as $key =>  $socialcpcancel) {
				if( ($socialcpcancel['socialcp_cancel_day'] != $_POST['socialcp_cancel_day'][$key]) || ( $socialcpcancel['socialcp_cancel_percent'] != $_POST['socialcp_cancel_percent'][$key] )) {
					$msg = "[유효기간 시작 전] 취소(환불) 정보가 변경되었습니다.";//4
					$goodsinfochangeuse = array('result'=>true,'msg'=>$msg,'type'=>'유효기간 전 후 취소(환불)');
					return $goodsinfochangeuse;
				}
			}
		}

		if(empty($_POST['socialcp_cancel_use_refund'])) $_POST['socialcp_cancel_use_refund'] = '0';
		if(empty($_POST['socialcp_use_return'])) $_POST['socialcp_use_return'] = '0';
		if(empty($_POST['socialcp_use_emoney_day'])) $_POST['socialcp_use_emoney_day'] = '0';
		if(empty($_POST['socialcp_use_emoney_percent'])) $_POST['socialcp_use_emoney_percent'] = '0';

		if( trim($oldgoods['socialcp_cancel_use_refund']) != trim($_POST['socialcp_cancel_use_refund']) ) {
			$msg = "[유효기간 시작 전] 취소(환불) 정보가 변경되었습니다.";//5
			$goodsinfochangeuse = array('result'=>true,'msg'=>$msg,'type'=>'유효기간 전 후 취소(환불)');
			return $goodsinfochangeuse;
		}
		if( trim($oldgoods['socialcp_use_return']) != trim($_POST['socialcp_use_return']) ) {
			$msg = "[유효기간 종료 후] 미사용 쿠폰환불 정보가 변경되었습니다.";//6
			$goodsinfochangeuse = array('result'=>true,'msg'=>$msg,'type'=>'유효기간 전 후 취소(환불)');
			return $goodsinfochangeuse;
		}

		if( trim($oldgoods['socialcp_use_emoney_day']) != trim($_POST['socialcp_use_emoney_day']) ) {
			$msg = "[유효기간 종료 후] 미사용 쿠폰환불 기간정보가 변경되었습니다.";//7
			$goodsinfochangeuse = array('result'=>true,'msg'=>$msg,'type'=>'유효기간 전 후 취소(환불)');
			return $goodsinfochangeuse;
		}

		if( trim($oldgoods['socialcp_use_emoney_percent']) != trim($_POST['socialcp_use_emoney_percent']) ) {
			$msg = "[유효기간 종료 후] 미사용 쿠폰환불 정보가 변경되었습니다.";//8
			$goodsinfochangeuse = array('result'=>true,'msg'=>$msg,'type'=>'유효기간 전 후 취소(환불)');
			return $goodsinfochangeuse;
		}
	}

	// 구매자별 판매가격 디스플레이
	if( trim($oldgoods['string_price_use']) != trim($_POST['string_price_use']) ||
		trim($oldgoods['string_price']) != trim($_POST['string_price']) ||
		trim($oldgoods['string_price_link']) != trim($_POST['string_price_link']) ||
		trim($oldgoods['string_price_link_url']) != trim($_POST['string_price_link_url']) ||
		trim($oldgoods['member_string_price_use']) != trim($_POST['member_string_price_use']) ||
		trim($oldgoods['member_string_price']) != trim($_POST['member_string_price']) ||
		trim($oldgoods['member_string_price_link']) != trim($_POST['member_string_price_link']) ||
		trim($oldgoods['member_string_price_link_url']) != trim($_POST['member_string_price_link_url']) ||
		trim($oldgoods['allmember_string_price_use']) != trim($_POST['allmember_string_price_use']) ||
		trim($oldgoods['allmember_string_price']) != trim($_POST['allmember_string_price']) ||
		trim($oldgoods['allmember_string_price_link']) != trim($_POST['allmember_string_price_link']) ||
		trim($oldgoods['allmember_string_price_link_url']) != trim($_POST['allmember_string_price_link_url'])
	){

		$msg = "구매 대상자 (가격노출 및 버튼노출 제어) 정보가 변경되었습니다.";//8
		$goodsinfochangeuse = array('result'=>true,'msg'=>$msg,'type'=>'구매자별 판매가격 디스플레이');
		return $goodsinfochangeuse;
	}

	return $goodsinfochangeuse;
}


/**
*
* @입점사 >> 일괄수정시  상태자동처리
* @정가/할인가격 변경시
**/
function goodsinfo_batch_modify_price( $optseq, $consumer_price, $price) {
	if(!$optseq)return;
	$CI =& get_instance();
	$CI->load->model('goodsmodel');
	$sql = "SELECT consumer_price, price FROM fm_goods_option WHERE option_seq='".$optseq."'";
	$query	= $CI->db->query($sql);
	$oldoptions = $query->row();
	$goodsinfochangeuse = false;

	/* 필수옵션 fm_goods_option */
	if( $consumer_price != $oldoptions->consumer_price ) {//정가
		$goodsinfochangeuse = true;
		return $goodsinfochangeuse;
	}

	if( $price != $oldoptions->price ) {//할인가
		$goodsinfochangeuse = true;
		return $goodsinfochangeuse;
	}

	return $goodsinfochangeuse;
}

//가입형식 추가 타입별 속성값 가져오기
function get_labelitem_type($data, $gddata,$showtype = null){
	$labelArray = @explode("|", $data['label_value']);
	$labelcodeArray = @explode("|", $data['label_code']);
	$labelCount = count($labelArray)-1;
	$labelindexBox = '';
	$label_value = ($gddata[0]) ? $gddata[0]['label_value'] : '';
	if($showtype == 'view'){
		$inputBox .= "<table class='selectLabelSet'><tr><td>";
		$inputBox .= $label_value ;
		$inputBox .= '</td></tr></table>';
	}elseif($showtype == 'setting'){
		$inputBox .= "";
		for ($j=0; $j<$labelCount; $j++)
		{
			if($data['codeform_seq'] == 1 && $labelcodeArray[$j] == 'category' ){
				$labelindexBox .=  '카테고리별 코드값은 상품 > <a href="/admin/category/catalog" target="_blank"><span class=" highlight-link hand">카테고리</span></a>에서 입력할 수 있습니다., ';
			}elseif($data['codeform_seq'] == 2 && $labelcodeArray[$j] == 'brand' ){
				$labelindexBox .=  '브랜드별 코드값은 상품 > <a href="/admin/brand/catalog" target="_blank"><span class=" highlight-link hand">브랜드</span></a>에서 입력할 수 있습니다., ';
			}elseif($data['base_type'] == '1' && $labelcodeArray[$j] == 'goods_seq' ){
				$labelindexBox .=  '자동 생성 상품코드의 중복을 방지할 수 있도록 상품의 고유값을 입력합니다., ';
			}else{
				$labelindexBox .=  $labelArray[$j] .'['.$labelcodeArray[$j].'], ';
			}
		}
		$labelindexBox = substr($labelindexBox,0,strlen($labelindexBox)-2);
		$inputBox .= $labelindexBox;
		$inputBox .= '';
	}else{
		if($data['label_type'] == 'goodsaddinfo' ) {//select box
			for ($j=0; $j<$labelCount; $j++)
			{
				$selected = ( $gddata['code_seq'] == $data['codeform_seq'] && $labelcodeArray[$j] == $gddata['contents']) ? "selected" : "";
				$labelindexBox .= '<option value="'. $labelcodeArray[$j] .'" '. $selected .'  >'. $labelArray[$j] .'</option>';
			}
			if($gddata){
				$labelsubBox = '<input type="hidden" name="subselect['.$gddata['code_seq'].'] id="subselect_'.$gddata['code_seq'].'" value="'.$gddata['contents_title'].'" code_seq="'.$gddata['code_seq'].'" class="hiddenLabelDepth">';
			}

			$inputBox .= '<select name="'.$data['label_type'].'['.$data['codeform_seq'].'][]" id="label_'.$data['codeform_seq'].'" codeform_seq="'.$data['codeform_seq'].'" class="resp_select '.$data['label_type'].'"  label_type="'.$data['label_type'].'"  label_id="'.$data['label_id'].'" >';
			$inputBox .= $labelindexBox;
			$inputBox .= '</select>';
			$inputBox .= $labelsubBox;
		}else{//checkbox

			if($gddata[0])$cmsdata=count($gddata);
			for ($k=0; $k<$cmsdata; $k++) {
				$ckdata[] = $gddata[$k]['label_value'];
			}

			for ($j=0; $j<$labelCount; $j++) {
				if (is_array($gddata)) {
					$checked = (@in_array($labelArray[$j], $ckdata )) ? "checked" : "";
				}
				if ($j > 0) $inputBox .= " ";
				$inputBox .= '<input type="checkbox" name="'.$data['label_type'].'['.$data['codeform_seq'].'][]" class="null labelCheckbox_'.$data['codeform_seq'].'"  codeform_seq="'.$data['codeform_seq'].'"  label_type="'.$data['label_type'].'"  label_id="'.$data['label_id'].'" value="'. $labelArray[$j] .'" '. $checked .'>'. $labelArray[$j] .'['.$labelcodeArray[$j].']';
			}
		}
	}
	return $inputBox;
}

// 회원 등급별 가격대체문구 출력
function get_string_price($data_goods, $memberInfo = array()){
	$CI =& get_instance();
	$userInfo	= $CI->userInfo;
	if	($memberInfo['member_seq'] > 0)	$userInfo	= $memberInfo;
	if( $userInfo['member_seq'] == ''){ // 비회원
		if($data_goods['string_price_use'] == 1 && $data_goods['string_price']){
			$string_price = get_string_price_link($data_goods['string_price_link'],$data_goods['string_price_link_url'],$data_goods['string_price'],$data_goods['string_price_link_target'],$data_goods['string_price_color']);
		}
	}else if( $userInfo['group_seq'] == '1'){ // 일반회원
		if($data_goods['member_string_price_use'] == 1 && $data_goods['member_string_price']){
			$string_price = get_string_price_link($data_goods['member_string_price_link'],$data_goods['member_string_price_link_url'],$data_goods['member_string_price'],$data_goods['member_string_price_link_target'],$data_goods['member_string_price_color']);
		}
	}else if( $userInfo['group_seq'] > '1'){ // 일반회원 제외한 모든 회원
		if($data_goods['allmember_string_price_use'] == 1 && $data_goods['allmember_string_price']){
			$string_price = get_string_price_link($data_goods['allmember_string_price_link'],$data_goods['allmember_string_price_link_url'],$data_goods['allmember_string_price'],$data_goods['allmember_string_price_link_target'],$data_goods['allmember_string_price_color']);
		}
	}

	return $string_price;
}

function get_string_price_link($link,$url,$string_price,$target,$color){
	$result = $string_price;
	if	($target == 'NEW') $target = "target='_blank'";
	if	($color)	$style = "color:".$color.";";
	if($link == 'login'){
		$result = "<a href='/member/login' ".$target." style='".$style."'>".$result."</a>";
	}
	if($link == '1:1'){
		$result = "<a href='/mypage/myqna_catalog' ".$target." style='".$style."'>".$result."</a>";
	}
	if( $link == 'direct' && $url ){
		$result = "<a href='".$url."' ".$target." style='".$style."'>".$result."</a>";
	} else if ( in_array($link, ['direct', 'none', ''])) {
		$style .= "text-decoration : none;";
		$result = "<a style='".$style."' >".$result."</a>";
	}
	return $result;
}

/**
* 특수정보 > 자동기간 미리보기
* $deposit_date : 결제일시(0000-00-00)
* $sdayauto : 시작되는 일 int
* $fdayauto : 끝나는 일 int
* $dayauto_type : 해당월(month), 해당일(day), 익월(next)
* $dayauto_day : 동안(day) 또는 말일(end)
**/
function goods_dayauto_setting_day( $deposit_date, $sdayauto, $fdayauto, $dayauto_type, $dayauto_day ) {
	$deposit_datear = explode("-",$deposit_date);

	$depositmonth = $deposit_datear[1];
	$depositday = $deposit_datear[1];
	$sday = $sdayauto;
	$fday = $fdayauto;
	if( $dayauto_type == 'month' ) {
		$social_start_date				= ($sday>0)?date("Y-m-d", strtotime($deposit_datear[0]."-".$depositmonth."-".$sday)):date("Y-m-d", strtotime($deposit_datear[0]."-".$depositmonth));
		$social_end_date_tmp		= ($fday>0)?date("Y-m-d",strtotime('+'.$fday.' day '.$social_start_date)):date("Y-m-d",strtotime($social_start_date));
	}elseif( $dayauto_type == 'day' ) {
		$social_start_date				= ($sday>0)?date("Y-m-d",strtotime('+'.$sday.' day '.$deposit_date)):date("Y-m-d",strtotime($deposit_date));
		$social_end_date_tmp		= ($fday>0)?date("Y-m-d",strtotime('+'.$fday.' day '.$social_start_date)):date("Y-m-d",strtotime($social_start_date));
	}elseif( $dayauto_type == 'next' ) {
		$social_start_date				= ($sday>0)?date("Y-m-d",strtotime(date("Y-m",strtotime('+1 month '.$deposit_date))."-".$sday)):date("Y-m-d",strtotime(date("Y-m",strtotime('+1 month '.$deposit_date))));
		$social_end_date_tmp		= ($fday>0)?date("Y-m-d",strtotime('+'.$fday.' day '.$social_start_date)):date("Y-m-d",strtotime($social_start_date));
	}

	if( $dayauto_day == 'end' ){//끝나는 날짜의 말일
		$social_end_date = date("Y-m-t", strtotime($social_end_date_tmp));
	}else{
		$social_end_date = date("Y-m-d", strtotime($social_end_date_tmp));
	}
	return array('social_start_date'=>$social_start_date,'social_end_date'=>$social_end_date);
}


// 필수옵션 option + option
function get_goods_options_print_array($param){

	// 특수옵션 처리
	if	($param['newtype']){
		$newtype	= explode(',', $param['newtype']);
		foreach($newtype as $k => $types){
			if(!$types)continue;
			$result[$types] = $k+1;
		}
	}

	return $result;
}


// 특수옵션 날짜/기간/주소 옵션 노출
function get_goods_special_option_print($param) {
	$CI =& get_instance();
	if(!$CI->goodsmodel) $CI->load->model('goodsmodel');
	$option_dayautotitle = '';

	// 특수옵션 처리
	if	($param['newtype']){
		$expire_arr				= array('dayauto', 'date', 'dayinput');
		$address_arr			= array('address');

		$dayauto_type = ($param['dayauto_type']  == 'day')?"이후":"";
		$newtype	= explode(',', $param['newtype']);
		foreach($newtype as $k => $types){
			if	(in_array($types, $expire_arr)){
				$key					= $k + 1;
				if( $types == 'date' ) {
					if($option_dayautotitle)$option_dayautotitle .= "<br/>";
					$option_dayautotitle .= $param['codedate'];
				}elseif( $types == 'dayauto' ) {
					if($option_dayautotitle)$option_dayautotitle .= "<br/>";
					$option_dayautotitle .= '\'결제확인\' 후 '.$CI->goodsmodel->dayautotype[$param['dayauto_type']].' '.$param['sdayauto'].'일 '.$dayauto_type.'부터 +'.$param['fdayauto'].'일 '.$CI->goodsmodel->dayautoday[$param['dayauto_day']];
				}else{
					if($option_dayautotitle)$option_dayautotitle .= "<br/>";
					$option_dayautotitle .= $param['sdayinput'] . ' ~ ' . $param['fdayinput'];
				}
			}
			if	($param['address'] && in_array($types, $address_arr)){
				if($option_dayautotitle)$option_dayautotitle .= "<br/>";
				if($param['address_type'] == 'street' ){
					$option_dayautotitle	.= ' [' . $param['zipcode'] . ']' .$param['address_street'].' '.$param['addressdetail'];
				}else{
					$option_dayautotitle	.= ' [' . $param['zipcode'] . ']' .$param['address'].' '.$param['addressdetail'];
				}
				$option_dayautotitle .= "<br/>";
				$option_dayautotitle	.= '업체연락처:' .$param['biztel'];
			}
		}
	}
	return $option_dayautotitle;
}

function get_goods_image($goods_seq){
	$CI =& get_instance();
	if(!$CI->goodsmodel) $CI->load->model('goodsmodel');
	$images			= $CI->goodsmodel->get_goods_image($goods_seq);
	return $images;
}

function get_goods_images($goods_seqs){
	$CI =& get_instance();
	if(!$CI->goodsmodel) $CI->load->model('goodsmodel');
	$images			= $CI->goodsmodel->get_goods_images($goods_seqs);
	return $images;
}

function excel_upload_after_summary_update($goodsArr){
	$CI =& get_instance();
	$CI->load->model('goodssummarymodel');
	$CI->goodssummarymodel->set_event_price(array('goods'=>$goodsArr));
}

function option_to_package_str($arr_option){
	foreach($arr_option as $data){
		if($data) $arr[] = $data;
	}
	if($arr) return implode(' / ',$arr);
	return '';
}

/**
* 패키지 검증
* 패키지옵션데이터, 실제연결옵션데이터
input
	mode				option or suboption
	goods_seq			패키지상품의 상품고유번호
	option_seq			패키지상품의 상품 옵션 고유번호
	package_option_seq	연결상품의 상품 옵션 고유번호
	package_option		연결상품의 상품 옵션
	no					연결상품의 순번
**/
function check_package_option($params,$data_con_option='',$err_save = false){
	$CI =& get_instance();
	$CI->load->model('goodsmodel');
	$CI->load->model('errorpackage');
	$mode				= $params['mode'];
	$goods_seq			= $params['goods_seq'];
	$option_seq			= $params['option_seq'];
	$con_option_seq		= $params['package_option_seq'];
	$con_package_option	= $params['package_option'];
	$no					= $params['no'];
	$return_mode		= $params['return_mode'];
	$del_mode			= $params['del_mode'];

	switch($mode){
		case 'option' :
			$code = '1'.$no;
			$field = 'package_err';
			$ofield = 'option_seq';
			break;
		case 'suboption' :
			$code = '2'.$no;
			$field = 'package_err_suboption';
			$ofield = 'suboption_seq';
			break;
	}
	if( $con_option_seq ){
		$goods_query = $CI->goodsmodel->get_option(array('option_seq'=>$con_option_seq));
		$data_con_option = $goods_query->row_array();
	}
	## 옵션에러테이블용 파라미터
	$error['type']				= $mode;
	$error['goods_seq']			= $goods_seq;
	$error['parent_seq']		= $option_seq;
	$error['no']				= $no;
	## 상품테이블 필드업데이트용 파라미터
	$goods_where['goods_seq']	= $goods_seq;
	$goods_set[$field]			= 'y';
	## 옵션테이블 필드업데이트용 파라미터
	$option_where[$ofield]		= $option_seq;
	$option_set['package_err']	= 'y';
	## 옵션없음
	if( !$data_con_option['option_seq'] ){
		$error['error_code'] = $code.'10';
		if($err_save){
			if($del_mode != 'n'){
				$CI->errorpackage->del_error($error);
			}
			$CI->errorpackage->set_error($error);
			$CI->goodsmodel->set_goods($goods_set,$goods_where);
		}
		if($return_mode == 'code'){
			return $error;
		}else{
			return false;
		}
	}
	## 옵션다름
	if( $con_package_option ){
		for($i=1;$i<=5;$i++){
			$arr_package_option = explode(' / ',$con_package_option);
			$error_opt = true;
			if( $data_con_option['option'.$i] ){
				foreach($arr_package_option as $con_options){
					if( trim($data_con_option['option'.$i]) == trim($con_options) ){
						$error_opt = false;
					}
				}
			}else{
				$error_opt = false;
			}
			if( $error_opt ){
				$error['error_code'] = $code.'20';
				if($err_save){
					if($del_mode != 'n'){
						$CI->errorpackage->del_error($error);
					}
					$CI->errorpackage->set_error($error);
					$CI->goodsmodel->set_goods($goods_set,$goods_where);
				}
				if($return_mode == 'code'){
					return $error;
				}else{
					return false;
				}
			}
		}
	}
	## 정상일 경우
	if($err_save){
		$del_err = $error;
		unset($del_err['error_code']);
		if($del_mode != 'n'){
			$CI->errorpackage->del_error($error);
		}
	}
	if($return_mode == 'code'){
		$error['error_code'] = '0000';
		return $error;
	}else{
		return true;
	}
}


function getSearchColorList($selected = array()) {

	if	($selected){
		$tmp			= $selected;
		if		(is_array($tmp))		$tmp		= implode(',', $tmp);
		$tmp			= preg_replace('/[^0-9a-zA-Z]/', ',', $tmp);	// 구분자 재정의
		$tmp			= preg_replace('/[\,]+/', ',', $tmp);			// 중복 구분자 제거
		$selected		= explode(',', $tmp);
	}else{
		$selected	= array();
	}

	// 상품 색상코드 목록
	unset($sc);
	$sc['label_type']	= 'goodscolor';
	$colorList			= getGoodsCodeForm($sc);
	if	($colorList) foreach($colorList as $k => $data){
		$colorPickList[$k]['name']			= $data['label_value'];
		$colorPickList[$k]['code']			= $data['label_color'];
		if	(in_array($data['label_color'], $selected)){
			$colorPickList[$k]['select']	= true;
		}else{
			$colorPickList[$k]['select']	= false;
		}
	}

	return $colorPickList;
}

// 회원 등급별 버튼대체문구
function get_string_button($data_goods, $memberInfo = array()){
	$CI =& get_instance();
	$userInfo	= $CI->userInfo;

	if	($memberInfo['member_seq'] > 0)	$userInfo	= $memberInfo;
	if( $userInfo['member_seq'] == ''){ // 비회원
		if($data_goods['string_button_use'] == 1 && $data_goods['string_button']){
			$string_button = get_string_button_link($data_goods['string_button_link'],$data_goods['string_button_link_url'],$data_goods['string_button'],$data_goods['string_button_link_target'],$data_goods['string_button_color']);
		}
	}else if( $userInfo['group_seq'] == '1'){ // 일반회원
		if($data_goods['member_string_button_use'] == 1 && $data_goods['member_string_button']){
			$string_button = get_string_button_link($data_goods['member_string_button_link'],$data_goods['member_string_button_link_url'],$data_goods['member_string_button'],$data_goods['member_string_button_link_target'],$data_goods['member_string_button_color']);
		}
	}else if( $userInfo['group_seq'] > '1'){ // 일반회원 제외한 모든 회원
		if($data_goods['allmember_string_button_use'] == 1 && $data_goods['allmember_string_button']){
			$string_button = get_string_button_link($data_goods['allmember_string_button_link'],$data_goods['allmember_string_button_link_url'],$data_goods['allmember_string_button'],$data_goods['allmember_string_button_link_target'],$data_goods['allmember_string_button_color']);
		}
	}

	return $string_button;
}

function get_string_button_link($link,$url,$string_button,$target,$color){
	$CI					= & get_instance();
	$isMobile			= $CI->mobileMode;

	$result = $string_button;
	if	($target == 'NEW') $target_val = "target='_blank'";
	if	($color) $color = "color:".$color." !important;";

	$href['login']		= '/member/login';
	$href['1:1']		= '/mypage/myqna_catalog';
	$href['direct']		= $url;
	
	$cursor = "";
	$disabled = "";
	if($link==none){
		$cursor	= 'cursor:default;';
		$disabled = 'disabled="disabled"';
	}	

	$result					= "<a href='".$href[$link]."' style='".$color."' ".$target_val." class='btn_move medium btn_resp size_b'>".$string_button."</a>";
	
	$browser = "Chrome";
	if(strpos($_SERVER['HTTP_USER_AGENT'],$browser) == false){
		$result				= "<a href='".$href[$link]."' style='width:100%;".$cursor.$color."' ".$target_val.$disabled." class='btn_important_large btn_resp size_c color4'>".$string_button."</a>";
	}else{
		if	($isMobile){
		$onclick		= "window.open('".$href[$link]."', 'string_url')";
		if	($target != 'NEW')
			$onclick	= "location.href='".$href[$link]."'";
		$result			= '<input type="button" style="width:100%;'.$cursor.$color.'" class="btn_important_large btn_resp size_c color4" value="'.$string_button.'" '.$disabled.' onClick="'.$onclick.'">';
		}
	}
	

	return $result;
}