<?php
class Authmodel extends CI_Model {
	public function __construct()
	{
		$this->table = 'fm_manager_auth';
	}

	public function manager_limit_view($path){
		if($this->managerInfo['manager_yn']=='Y') return true;
		if($this->providerInfo['manager_yn']=='Y' ) return true;

		if($path == "default/board/counsel_catalog.html"){
			$chk_view = "counsel_view";
		//2017/11/09 오픈마켓 관리자 권환 추가
		}else if(stripos($path, "market_connector") !== false){
			if($path == "default/market_connector/market_linkage.html" || $path == "default/market_connector/market_setting.html"){
				$chk_view = "openmarket_linkage_setting";
			}else{
				$chk_view = "openmarket_order_goods";
			}
		}else if(stripos($path, "accountall") !== false){
			$chk_view = "account_view";
		}else{
			$chk_view = $this->manager_path_return($path);
		}
		//echo $path." : ".$chk_view."<br>";
		//2015-05-19 jhr 프로모션에 따로 view 권한이 없음 쿠폰 권한에 따름
		if($chk_view == 'promotion_view') $chk_view = 'coupon_view';
		//2016-05-04 pjw 바코드에 따로 view 권한이 없음 상품 권한에 따름
		if($chk_view == 'barcode_view') $chk_view = 'goods_view';

		$cnt = 0;
		$auth_list = $this->managerInfo['manager_auth'];
		if	(defined('__SELLERADMIN__') === true) $auth_list = $this->providerInfo['manager_auth'];

		$auth_arr = explode("||",$auth_list);
		$exception = array('setting_manager_view');

		foreach($auth_arr as $k){
			$tmp_arr = explode("=",$k);
			//$auth[$tmp_arr[0]] = $tmp_arr[1];
			//echo $tmp_arr[0]." : ".$chk_view." : ".$tmp_arr[1]."<br>";
			if(in_array($tmp_arr[0],$exception)) $tmp_arr[1] = 'N';
			if($tmp_arr[0] == $chk_view && $tmp_arr[1]=='N') $cnt++;
		}

		if($cnt>0){
			if( ($chk_view=='setting_shipping_view' && $this->input->get("provider_seq") && strpos($_SERVER['HTTP_REFERER'],'provider_reg')) ||
				($chk_view=='openmarket_order_goods' && $this->input->get("mode") == "goodsModify")
			){
				return true;
			}
			return false;
		}else{
			return true;
		}
	}

	public function manager_limit_act($action){
		if($this->managerInfo && $this->managerInfo['manager_yn']=='Y') {
			if($action=='private_masking') {
				return false;
			} else {
				return true;
			}
		} else if($this->providerInfo && $this->providerInfo['manager_yn']=='Y') {
			if($action=='private_masking') {
				return false;
			} else {
				return true;
			}
		}

		$auth_list = $this->managerInfo['manager_auth'];
		if (defined('__SELLERADMIN__') === true) $auth_list = $this->providerInfo['manager_auth'];

		$auth_arr = explode("||", $auth_list);
		$exception = array('setting_pg_act', 'setting_manager_act', 'setting_bank_act');

		foreach($auth_arr as $k){
			$tmp_arr = explode("=", $k);
			if (in_array($tmp_arr[0],$exception)) $auth[$tmp_arr[0]] = 'N';
			else $auth[$tmp_arr[0]] = $tmp_arr[1];
		}

		$act_auth = $auth[$action];

		// 기본설정 값 N 인 항목 추가 : 회원정보다운로드, 마스킹, 게시글신고관련, 휴면/탈퇴 회원 주문 조회
		if (in_array($action, array('member_download', 'private_masking', 'report_view', 'report_act', 'personal_info_view')) && !$act_auth) {
			$act_auth='N';
		}

		if ($act_auth=='N') return false;
		else return true;
	}

	public function manager_path_return($path){
		$path_arr = explode("/",$path);
		if(strpos($path,'setting')){
			$path2 = explode(".",$path_arr[2]);
			$chk_view = $path_arr[1]."_".$path2[0]."_view";
		}elseif	(strpos($path,'scm')){
			$path2		= preg_replace('/_[^\_]*$/', '', preg_replace('/\.[^\.]*$/', '', $path_arr[2]));
			$chk_view	= 'scm'.$path2.'_view';
		}else{
			$chk_view = $path_arr[1]."_view";
		}
		return $chk_view;
	}

	public function manager_auth_arr(){
		$cnt = 0;
		$auth_arr = explode("||",$this->managerInfo['manager_auth']);
		foreach($auth_arr as $k){
			$tmp_arr = explode("=",$k);
			$auth[$tmp_arr[0]] = $tmp_arr[1];
		}
		return $auth;
	}

	public function make_auth_list(){
		$auth_groupcd = array('auth_manager','auth_order','auth_goods','auth_member','auth_promotion','auth_marketplace',
				'auth_statistic','auth_provider','auth_account','auth_design','auth_setting','auth_board',
				'auth_counsel','auth_scmstore','auth_scmgoods','auth_scmautoorder','auth_openmarket','auth_mobileapp','auth_o2osetting',
				'auth_ifdo_marketing','auth_broadcast','private_order','auth_report');
		foreach($auth_groupcd as $groupcd){
			$ault_list = code_load($groupcd);
			foreach($ault_list as $k=>$v){
				$auth_arr[] = $v['codecd'];
			}
		}
		$auth_text = "";
		foreach($auth_arr as $k){
			if($k=='setting_manager_view'){
				$value = 'Y';
			}else{
				$value = if_empty($_POST, $k, 'N');
			}
			$result[$k] = $value;
		}
		return $result;
	}

	## 관리자 권한
	public function select($field='',$where='',$orderby=''){
		if(!$field) $field = "*";
		$this->db->select($field);
		$this->db->from($this->table);

		if(is_array($where)){
			foreach($where as $k=>$v) {
				if(is_array($v)) {
					$this->db->where_in($k, $v);
				} else {
					$this->db->where($k, $v);
				}
			}
		}
		if($orderby){
			foreach($orderby as $key=>$value){
				$this->db->order_by($key, $value);
			}
		}
		return $this->db->get();
	}

	public function del($params,$where_ins='',$where_not_ins=''){
		if($where_ins){
			foreach($where_ins as $field => $values){
				$this->db->where_in($field, $values);
			}
		}
		if($where_not_ins){
			foreach($where_not_ins as $field => $values){
				$this->db->where_not_in($field, $values);
			}
		}
		$this->db->where($params);
		$this->db->delete($this->table);
	}



	public function insert($params){
		$this->db->set($params);
		$this->db->insert($this->table);
	}


}
?>