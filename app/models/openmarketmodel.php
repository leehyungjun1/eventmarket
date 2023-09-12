<?php
class openmarketmodel extends CI_Model {
	public function __construct(){
		$this->load->model('usedmodel');

		// controller 마지막에 linkage 종료용 ended_linkage_service 함수를 반드시 선언해 주셔야 합니다.
	}

	## 서비스 체크 ( usemodel interface )
	public function chk_linkage_service(){
		$return	= false;
		$result	= $this->usedmodel->chk_linkage_service();

		// 원산지 정보 복사 ( 속도 문제로 몰에서 복사해서 가지고 있는 방식으로 변경 )
		if	($result == 'save'){
			$cfg_linkage	= $this->get_linkage_config();
			if	($cfg_linkage['linkage_id']){
				$origin	= $this->usedmodel->get_linkage_origin($cfg_linkage['linkage_id']);
				$sql	= "truncate table fm_linkage_origin";
				$this->db->query($sql);
				if	($origin)foreach($origin as $k => $data){
					unset($insParam);
					$insParam['origin_code']	= $data['origin_code'];
					$insParam['origin_name']	= $data['origin_name'];
					$this->db->insert('fm_linkage_origin', $insParam);
				}
			}

			$return	= true;
		}elseif	($result == 'ok')$return	= true;

		return $return;
	}

	## 서비스 정보 ( usemodel interface )
	public function get_linkage_service(){
		return $this->usedmodel->get_linkage_service();
	}

	## 연동 업체 정보 목록 ( usemodel interface )
	public function get_linkage_company($sc){
		return $this->usedmodel->get_linkage_company($sc);
	}

	## 연동 업체 지원 마켓 정보 목록 ( usemodel interface )
	public function get_linkage_support_mall($linkage_id, $sc){
		return $this->usedmodel->get_linkage_support_mall($linkage_id, $sc);
	}

	## 연동 업체 카테고리 정보 ( usemodel interface )
	public function get_linkage_category($linkage_id, $sc = array()){
		return $this->usedmodel->get_linkage_category($linkage_id, $sc);
	}

	## 연동 업체 데이터 추출 종료 ( usemodel interface )
	public function ended_linkage_service(){
		$this->usedmodel->ended_linkage_service();
	}

	## 오픈마켓용 원산지 목록 ( usemodel interface )
	public function get_linkage_origin($linkage_id){
		$sql	= "select * from fm_linkage_origin ";
		$query	= $this->db->query($sql);
		$result	= $query->result_array();
		return $result;
	}

	## 연동 업체 설정
	public function get_linkage_config(){
		$sql	= "select * from fm_linkage_config ";
		$query	= $this->db->query($sql);
		
		return $query->row_array();
	}

	## 연동 마켓 설정
	public function get_linkage_mall($group = ''){
		$sql	= "select * from fm_linkage_mall ";
		$query	= $this->db->query($sql);
		$result	= $query->result_array();

		// mall_code를 기준으로 묶은 배열
		if	($group == 'code' && $result){
			// 금액 조정 set 재정의
			foreach($result as $k => $data){
				if	($data['default_yn'] == 'Y'){
					$mall[$data['mall_code']]['linkage_seq']	= $data['linkage_seq'];
					$mall[$data['mall_code']]['mall_code']		= $data['mall_code'];
					$mall[$data['mall_code']]['mall_name']		= $data['mall_name'];
					$mall[$data['mall_code']]['mall_key']		= $data['mall_key'];
					$mall[$data['mall_code']]['default_yn']		= $data['default_yn'];
					$mall[$data['mall_code']]['revision_val']	= $data['revision_val'];
					$mall[$data['mall_code']]['revision_unit']	= $data['revision_unit'];
					$mall[$data['mall_code']]['revision_type']	= $data['revision_type'];
				}

				$data['revision_str']	= '';
				if	($data['default_yn'] == 'Y')		$data['revision_str']	.= '[기본] ';
				$data['revision_str']	.= $data['revision_val'];
				if	($data['revision_unit'] == 'won')	$data['revision_str']	.= '원 ';
				else									$data['revision_str']	.= '% ';
				if	($data['revision_type'] == 'M')		$data['revision_str']	.= '-조정';
				else									$data['revision_str']	.= '+조정';

				$mall[$data['mall_code']]['revision'][]		= $data;
			}

			unset($result);
			$result	= $mall;
		}

		return $result;
	}

	// 주요 오픈마켓 우선순위로 정렬
	public function sort_linkage_mall($linkage_malldata=array()){
		$sort_key = array_reverse(array('APISHOP_0010','APISHOP_0003','APISHOP_0025','APISHOP_0012','APISHOP_0148','APISHOP_0037','APISHOP_0021','APISHOP_0022','APISHOP_0170','APISHOP_0002'));

		foreach($sort_key as $k1){
			foreach($linkage_malldata as $k2=>$row){
				if($k1==$row['mall_code']){
					$tmp = $linkage_malldata[$k2];
					unset($linkage_malldata[$k2]);
					array_unshift($linkage_malldata,$tmp);
					break;
				}
			}
		}
		return $linkage_malldata;
	}

	## 설정데이터 체크
	public function chk_param_save(){
		$linker_name		= $_POST['linker_name'];
		$linker_company		= $_POST['linker_linkage'];
		$linker_key			= $_POST['linker_key'];
		$cut_price_use		= $_POST['cut_price_use'];
		$cut_price_unit		= $_POST['cut_price_unit'];
		$cut_price_type		= $_POST['cut_price_type'];
		$linker_keysub		= $_POST['linker_keysub'];
		$master_key			= $_POST['master_key'];

		// 연동업체 선택여부
		if	(!$linker_company || !$linker_name){
			openDialogAlert('연동업체를 선택해 주세요.',400,140,'parent',$callback);
			exit;
		}

		// 연동업체 ID 입력여부
		if	(!$linker_key){
			$callback = "if(parent.document.getElementsByName('linker_key[]')[0]) parent.document.getElementsByName('linker_key[]')[0].focus();";
			openDialogAlert($linker_name.' 아이디(연동키)를 입력해 주세요.',400,140,'parent',$callback);
			exit;
		}

		if	(!$_POST['linkage_type'][$linker_company]){
			openDialogAlert('지원하는 마켓이 없습니다.',400,140,'parent',$callback);
			exit;
		}else{
			$mall_name			= $_POST['mall_name'][$linker_company];
			$mall_code			= $_POST['mall_code'][$linker_company];
			$mall_key			= $_POST['linkage_key'][$linker_company];
			$mall_type			= $_POST['mall_type'][$linker_company];
			$revision_default	= $_POST['default_revision'][$linker_company];
			$revision_value		= $_POST['revision_val'][$linker_company];
			$revision_unit		= $_POST['revision_unit'][$linker_company];
			$revision_type		= $_POST['revision_type'][$linker_company];
			foreach($_POST['linkage_type'][$linker_company] as $k => $use){
				if	($use == 'Y'){
					$mall[$mall_code[$k]]['key']					= $mall_key[$k];
					$mall[$mall_code[$k]]['mall_name']				= $mall_name[$k];
					$mall[$mall_code[$k]]['mall_type']				= $mall_type[$k];
					$mall[$mall_code[$k]]['revision']['value']		= $revision_value[$k];
					$mall[$mall_code[$k]]['revision']['unit']		= $revision_unit[$k];
					$mall[$mall_code[$k]]['revision']['type']		= $revision_type[$k];
					$mall[$mall_code[$k]]['revision']['default']	= $revision_default[$k];

					if	(!$mall_code[$k]){
						openDialogAlert($mall_name[$k].' 판매자 아이디를 입력해 주세요.',400,140,'parent',$callback);
						exit;
					}
				}
			}

			if	(count($mall) < 1){
				openDialogAlert('연동 선택된 마켓이 없습니다.',400,140,'parent',$callback);
				exit;
			}
		}

		$params['linkage']['seq']		= $linker_company;
		$params['linkage']['name']		= $linker_name;
		$params['linkage']['code']		= $linker_key;
		$params['linkage']['codesub']	= $linker_keysub;
		$params['cut']['use']			= $cut_price_use;
		$params['cut']['unit']			= $cut_price_unit;
		$params['cut']['type']			= $cut_price_type;
		$params['mall']					= $mall;
		$params['master_key']			= $master_key;

		return $params;
	}

	## 설정데이터 저장
	public function save_config($params){
		$linkage	= $params['linkage'];
		$cut		= $params['cut'];
		$mall		= $params['mall'];
		$master_key	= $params['master_key'];

		if	($linkage['seq'] > 0){
			$code		= $linkage['code'];
			$code_sub	= $linkage['codesub'];
			$tmp		= $this->get_linkage_company(array('seq'=>$linkage['seq']));
			$linkage	= $tmp[0];

			$linkageParam['linkage_seq']		= $linkage['seq'];
			$linkageParam['linkage_name']		= $linkage['linkage_name'];
			$linkageParam['linkage_id']			= $linkage['linkage_id'];
			$linkageParam['linkage_code']		= $code;
			$linkageParam['linkage_codesub']	= $code_sub;
			$linkageParam['cut_price_use']	= $cut['use'];
			$linkageParam['cut_price_unit']	= $cut['unit'];
			$linkageParam['cut_price_type']	= $cut['type'];

			$this->db->query("TRUNCATE TABLE fm_linkage_config");
			$this->db->insert('fm_linkage_config', $linkageParam);
		}

		$this->db->query("TRUNCATE TABLE fm_linkage_mall");
		if	($mall)foreach($mall as $mall_code => $data){
			$mallParam['linkage_seq']	= $linkage['seq'];
			$mallParam['mall_code']		= $mall_code;
			$mallParam['mall_name']		= $data['mall_name'];
			$mallParam['mall_key']		= $data['key'];
			$mallParam['master_key']	= '';
			if	($data['mall_type'] == '오픈마켓'){
				$mallParam['master_key']		= $master_key;
				$mall[$mall_code]['master_key']	= $master_key;
			}

			if	($data['revision']['value']){
				foreach($data['revision']['value'] as $k => $val){
					$mallParam['revision_val']	= $val;
					$mallParam['revision_unit']	= $data['revision']['unit'][$k];
					$mallParam['revision_type']	= $data['revision']['type'][$k];
					$mallParam['default_yn']	= 'N';
					if	($data['revision']['default'] == $k)
						$mallParam['default_yn']	= 'Y';
 
					$this->db->insert('fm_linkage_mall', $mallParam);
				}
			}else{
				$this->db->insert('fm_linkage_mall', $mallParam);
			}
		}

		$this->load->model('usedmodel');
		return $this->usedmodel->set_linkage_company($mall);
	}

	## 쇼핑몰 카테고리 및 매칭된 카테고리 정보
	public function get_shop_category($sc){

		// 단계 검색
		if(!empty($sc['depth'])){
			$addWhere		.= " and c.level = ? ";
			$addSql[]		= $sc['depth'] + 1;
		}
		// 부모코드 검색
		if(!empty($sc['pCode'])){
			$codeLen		= strlen($sc['pCode']);
			$this->load->model('categorymodel');
			$categoryData = $this->categorymodel->get_category_data($sc['pCode']);
			$addWhere		.= " and c.category_code like '".$sc['pCode']."%' ";
			$addWhere		.= " and LENGTH(c.category_code) > ".$codeLen." ";
			$addWhere		.= " and c.parent_id = ".$categoryData["id"]." ";
		}
		// 코드 이후 검색
		if(!empty($sc['sCode'])){
			$addWhere		.= " and c.category_code like '".$sc['sCode']."%' ";
		}
		// 코드 검색
		if(!empty($sc['code'])){
			$addWhere		.= " and c.category_code = ? "; 
			$addSql[]		= $sc['code'];
		}

		$sql	= "select c.*, 
						lc.category_linkage_seq, 
						lc.linkage_id, 
						lc.linkage_category_code1, 
						lc.linkage_category_name1, 
						lc.linkage_category_code2, 
						lc.linkage_category_name2, 
						lc.linkage_category_code3, 
						lc.linkage_category_name3, 
						lc.linkage_category_code4, 
						lc.linkage_category_name4 
					from fm_category				as c 
					left join fm_linkage_category	as lc 
						on c.category_code = lc.category_code 
					where c.category_code > 0 ".$addWhere."
					order by c.category_code ";
		$query	= $this->db->query($sql, $addSql);
		$result	= $query->result_array();

		return $result;
	}

	## 카테고리 매칭 체크
	public function chk_link_category(){
		$mall_cate			= $_POST['mall_cate'];
		$chk_linkage		= $_POST['chk_linkage'];
		$linkage_cate		= $_POST['linkage_cate'];
		$linkage_cate_name	= $_POST['linkage_cate_name'];
		$linkage			= $this->get_linkage_config();

		if	(!$chk_linkage || count($chk_linkage) < 1){
			$callback	= 'parent.save_result(\'fail\');';
			openDialogAlert('연결할 쇼핑몰 카테고리를 선택해 주세요',400,140,'parent',$callback);
			exit;
		}

		if	(!$linkage_cate[0] || !$linkage_cate[1]){
			$callback	= 'parent.save_result(\'fail\');';
			openDialogAlert('연결할 '.$linkage_name['linkage_name'].' 카테고리를 선택해 주세요',400,140,'parent',$callback);
			exit;
		}

		// insert할 배열 생성.
		$param['linkage_id']				= $linkage['linkage_id'];
		$param['linkage_category_code1']	= $linkage_cate[0];
		$param['linkage_category_name1']	= $linkage_cate_name[0];
		$param['linkage_category_code2']	= $linkage_cate[1];
		$param['linkage_category_name2']	= $linkage_cate_name[1];
		$param['linkage_category_code3']	= $linkage_cate[2];
		$param['linkage_category_name3']	= $linkage_cate_name[2];
		$param['linkage_category_code4']	= $linkage_cate[3];
		$param['linkage_category_name4']	= $linkage_cate_name[3];
		$param['regist_date']				= date('Y-m-d H:i:s');
		if	(count($chk_linkage) > 0)foreach($chk_linkage as $k => $cate){
			if	($cate){
				$param['category_code']		= $cate;
				$insertParam[$cate]			= $param;
			}
		}
/*
		if	($mall_cate){
			foreach($mall_cate as $k => $cate){
				if	(trim($cate))	$addCate	= trim($cate);
			}
			if	($addCate){
				$param['category_code']		= $addCate;
				$insertParam[$addCate]		= $param;
			}
		}
*/
		return $insertParam;
	}

	## 카테고리 매핑 저장
	public function save_link_category($param){
		if	($param)foreach($param as $category_code => $insertParam){
			if	($category_code){
				$this->db->where(array('category_code'=>$category_code));
				$this->db->delete('fm_linkage_category');

				$this->db->insert('fm_linkage_category', $insertParam);
			}
		}
	}

	## 카테고리 매핑 삭제
	public function save_unlink_category($unSeq){
		if	($unSeq){
			$this->db->where(array('category_linkage_seq'=>$unSeq));
			$this->db->delete('fm_linkage_category');
		}
	}

	## 상품별 연동 몰 정보 데이터 추출
	public function get_linkage_goods_mall($goods_seq){
		$addSql[]	= $goods_seq;
		$sql	= "select * from fm_linkage_goods_mall where goods_seq = ? ";
		$query	= $this->db->query($sql, $addSql);
		$result	= $query->result_array();

		return $result;
	}

	## 마켓 금액 조정 설정 데이터 추출
	public function get_linkage_goods_config($goods_seq, $option_type = '', $mall_code = ''){
		$addSql[]	= $goods_seq;
		if	(!empty($option_type)){
			$addWhere	= " and option_type = ? ";
			$addSql[]	= $option_type;
		}
		if	(!empty($mall_code)){
			$addWhere	.= " and mall_code = ? ";
			$addSql[]	= $mall_code;
		}
		$sql	= "select * from fm_linkage_goods_config where goods_seq = ? ".$addWhere;
		$query	= $this->db->query($sql, $addSql);
		$result	= $query->result_array();

		return $result;
	}

	## 마켓 금액 조정 설정 데이터 추출
	public function get_linkage_goods_config_tmp($tmp_seq, $option_type = ''){
		$addSql[]	= $tmp_seq;
		if	(!empty($option_type)){
			$addWhere	= " and option_type = ? ";
			$addSql[]	= $option_type;
		}
		$sql	= "select * from fm_linkage_goods_config_tmp where tmp_seq = ? ".$addWhere;
		$query	= $this->db->query($sql, $addSql);
		$result	= $query->result_array();

		return $result;
	}

	## 마켓 금액 조정 데이터 추출
	public function get_linkage_option_price($goods_seq){
		$sql	= "select * from fm_linkage_goods_price where option_seq > 0 and goods_seq = ? 
					order by option_seq ";
		$query	= $this->db->query($sql, array($goods_seq));
		$result	= $query->result_array();

		return $result;
	}

	## 마켓 금액 조정 임시 데이터 추출
	public function get_linkage_option_price_tmp($tmp_seq){
		$sql	= "select * from fm_linkage_goods_price_tmp where option_seq > 0 and tmp_seq = ? 
					order by option_seq ";
		$query	= $this->db->query($sql, array($tmp_seq));
		$result	= $query->result_array();

		return $result;
	}

	## 오픈마켓 할인가 조정 체크
	public function chk_goods_price(){

		$tmp_seq				= trim($_POST['tmp_seq']);
		$linkage_seq			= trim($_POST['linkage_seq']);
		$goods_seq				= trim($_POST['goods_seq']);
		$option_title			= trim($_POST['option_title']);
		$option_tmp_seq			= trim($_POST['option_tmp_seq']);
		$cal_type				= trim($_POST['cal_type']);
		$mall_name				= $_POST['mall_name'];
		$option1				= $_POST['option1'];
		$option2				= $_POST['option2'];
		$option3				= $_POST['option3'];
		$option4				= $_POST['option4'];
		$option5				= $_POST['option5'];
		$option_seq				= $_POST['option_seq'];
		$shop_consumer_price	= $_POST['shop_consumer_price'];
		$shop_supply_price		= $_POST['shop_supply_price'];
		$shop_sale_price		= $_POST['shop_sale_price'];
		$market_price			= $_POST['market_price'];
		$market_consumer_price	= $_POST['market_consumer_price'];
		$market_supply_price	= $_POST['market_supply_price'];
		$revision_val			= $_POST['revision_val'];
		$revision_unit			= $_POST['revision_unit'];
		$revision_type			= $_POST['revision_type'];

		// 임시 번호 생성
		if	(!$tmp_seq)
			$tmp_seq	= date('YmdHis').'market'.$this->managerInfo['manager_id'];

		// 수동입력방식
		if	($cal_type == 'manual'){
			$result_data['tmp_seq']				= $tmp_seq;
			$result_data['goods_seq']			= $goods_seq;
			$result_data['option_tmp_seq']		= $option_tmp_seq;
			$result_data['suboption_tmp_seq']	= '';
			$result_data['suboption_seq']		= '0';
			$result_data['option_title']		= $option_title;
			$result_data['regist_date']			= date('Y-m-d H:i:s');
			if	($market_price)foreach($market_price as $optSeq => $market){
				$result_data['option_seq']			= $optSeq;
				$result_data['option1']				= $option1[$optSeq];
				$result_data['option2']				= $option2[$optSeq];
				$result_data['option3']				= $option3[$optSeq];
				$result_data['option4']				= $option4[$optSeq];
				$result_data['option5']				= $option5[$optSeq];
				$result_data['shop_consumer_price']	= $shop_consumer_price[$optSeq];
				$result_data['shop_supply_price']	= $shop_supply_price[$optSeq];
				$result_data['shop_sale_price']		= $shop_sale_price[$optSeq];
				$result_data['shop_margin']			= $shop_sale_price[$optSeq] - $shop_supply_price[$optSeq];
				if	($market)foreach($market as $mall_code => $price){
					$result_data['mall_code']		= $mall_code;
					$result_data['mall_name']		= $mall_name[$mall_code];
					$result_data['consumer_price']	= $market_consumer_price[$optSeq][$mall_code];
					$result_data['supply_price']	= $market_supply_price[$optSeq][$mall_code];
					$result_data['sale_price']		= $price;
					$result_data['margin']			= $price - $market_supply_price[$optSeq][$mall_code];

					$result[]	= $result_data;
				}
			}
		}else{
			$result_data['tmp_seq']			= $tmp_seq;
			$result_data['goods_seq']		= $goods_seq;
			$result_data['option_type']		= 'opt';
			$result_data['cal_type']		= 'auto';
			if	($revision_val)foreach($revision_val as $mall_code => $val){
				$result_data['mall_code']		= $mall_code;
				$result_data['mall_name']		= $mall_name[$mall_code];
				$result_data['revision_val']	= $val;
				$result_data['revision_unit']	= $revision_unit[$mall_code];
				$result_data['revision_type']	= $revision_type[$mall_code];

				$result[]	= $result_data;
			}
		}

		return $result;
	}

	## 오픈마켓 할인가 조정 임시 저장
	public function save_goods_price_tmp($params){
		$tmp_seq	= $params[0]['tmp_seq'];

		// 기존 데이터 삭제.
		$sql		= "delete from fm_linkage_goods_config_tmp 
						where tmp_seq = '".$tmp_seq."' and option_type = 'opt' ";
		$this->db->query($sql);
		$sql		= "delete from fm_linkage_goods_price_tmp 
						where tmp_seq = '".$tmp_seq."' and option_seq > 0";
		$this->db->query($sql);
 
		if	($params[0]['cal_type'] == 'auto'){
			if	($params)foreach($params as $p => $param){
				$this->db->insert("fm_linkage_goods_config_tmp", $param);
			}
		}else{

			$cparam['tmp_seq']		= $tmp_seq;
			$cparam['goods_seq']	= $params[0]['goods_seq'];
			$cparam['mall_code']	= $params[0]['mall_code'];
			$cparam['option_type']	= 'opt';
			$cparam['cal_type']		= 'manual';
			$this->db->insert("fm_linkage_goods_config_tmp", $cparam);

			if	($params)foreach($params as $p => $param){
				$this->db->insert("fm_linkage_goods_price_tmp", $param);
			}
		}

		return $tmp_seq;
	}

	## 오픈마켓 할인가 조정 임시에서 원본으로 이동
	public function save_openmarket_option($goods_seq, $tmp_seq = ''){

		// 절사 설정
		$cfg		= $this->get_linkage_config();
		if	($cfg['cut_price_use'] == 'y')
			$cutArr	= array('unit' => $cfg['cut_price_unit'], 'type' => $cfg['cut_price_type']);

		// 기본으로 설정된 판매마켓 정보
		$malldata	= $this->get_linkage_mall();
		foreach($malldata as $k => $data){
			if	($data['default_yn'] == 'Y'){
				$mall[$data['mall_code']]['linkage_seq']	= $data['linkage_seq'];
				$mall[$data['mall_code']]['mall_code']		= $data['mall_code'];
				$mall[$data['mall_code']]['mall_name']		= $data['mall_name'];
				$mall[$data['mall_code']]['mall_key']		= $data['mall_key'];
				$mall[$data['mall_code']]['default_yn']		= $data['default_yn'];
				$mall[$data['mall_code']]['revision_val']	= $data['revision_val'];
				$mall[$data['mall_code']]['revision_unit']	= $data['revision_unit'];
				$mall[$data['mall_code']]['revision_type']	= $data['revision_type'];
			}
		}

		$this->db->where(array('goods_seq' => $goods_seq));
		$this->db->delete('fm_linkage_goods_config');
		$this->db->where(array('goods_seq' => $goods_seq));
		$this->db->delete('fm_linkage_goods_price');

		// 현재 옵션을 불러온다.
		if	($tmp_seq){
			$mgconfig	= $this->get_linkage_goods_config_tmp($tmp_seq, 'opt');
			if	($mgconfig[0]['cal_type'] == 'manual'){
				unset($insParam);
				$insParam['goods_seq']			= $goods_seq;
				$insParam['option_type']		= 'opt';
				$insParam['cal_type']			= 'manual';
				$insParam['mall_code']			= '';
				$insParam['mall_name']			= '';
				$insParam['revision_val']		= '';
				$insParam['revision_unit']		= '';
				$insParam['revision_type']		= '';
				$this->db->insert('fm_linkage_goods_config', $insParam);

				// 현재 옵션에 따른 가격 정보 저장 ( 일치하는 옵션만 저장 )
				$sql		= "select opt.option_seq, 
									lgp.option_seq as tmp_option_seq, 
									lgp.mall_code, 
									lgp.mall_name, 
									opt.goods_seq, 
									opt.option_seq, 
									opt.option_title, 
									opt.option1, 
									opt.option2, 
									opt.option3, 
									opt.option4, 
									opt.option5, 
									opt.consumer_price, 
									spy.supply_price, 
									opt.price, 
									lgp.consumer_price as market_consumer_price, 
									lgp.supply_price as market_supply_price, 
									lgp.sale_price as market_sale_price, 
									lgp.margin as market_margin 
								from fm_goods_option as opt
									inner join fm_goods_supply as spy 
										on ( opt.goods_seq = spy.goods_seq and 
											opt.option_seq = spy.option_seq )
									left join fm_linkage_goods_price_tmp as lgp 
										on ( tmp_seq = ? and ifnull(opt.option_title, '') = ifnull(lgp.option_title, '') and 
											ifnull(opt.option1, '') = ifnull(lgp.option1, '') and 
											ifnull(opt.option2, '') = ifnull(lgp.option2, '') and 
											ifnull(opt.option3, '') = ifnull(lgp.option3, '') and 
											ifnull(opt.option4, '') = ifnull(lgp.option4, '') and 
											ifnull(opt.option5, '') = ifnull(lgp.option5, '') )
								where opt.goods_seq = ? ";
				$query		= $this->db->query($sql, array($tmp_seq, $goods_seq));
				$options	= $query->result_array();
				if	($options)foreach($options as $o => $opt){
					if	($opt['mall_code']){
						unset($insParam);
						$insParam['mall_code']				= $opt['mall_code'];
						$insParam['mall_name']				= $opt['mall_name'];
						$insParam['goods_seq']				= $opt['goods_seq'];
						$insParam['option_seq']				= $opt['option_seq'];
						$insParam['suboption_seq']			= '';
						$insParam['option_title']			= $opt['option_title'];
						$insParam['option1']				= $opt['option1'];
						$insParam['option2']				= $opt['option2'];
						$insParam['option3']				= $opt['option3'];
						$insParam['option4']				= $opt['option4'];
						$insParam['option5']				= $opt['option5'];
						$insParam['shop_consumer_price']	= $opt['consumer_price'];
						$insParam['shop_supply_price']		= $opt['supply_price'];
						$insParam['shop_sale_price']		= $opt['price'];
						$insParam['shop_margin']			= $opt['price'] - $opt['supply_price'];
						$insParam['consumer_price']			= $opt['market_consumer_price'];
						$insParam['supply_price']			= $opt['market_supply_price'];
						$insParam['sale_price']				= $opt['market_sale_price'];
						$insParam['margin']					= $opt['market_margin'];
						$insParam['regist_date']			= date('Y-m-d H:i:s');
 						$this->db->insert('fm_linkage_goods_price', $insParam);
					}
				}
			}else{
				// 설정값에 따라 자동 저장.
				foreach($mgconfig as $k => $data){
					unset($insParam);
					$insParam['goods_seq']			= $goods_seq;
					$insParam['option_type']		= 'opt';
					$insParam['cal_type']			= $data['cal_type'];
					$insParam['mall_code']			= $data['mall_code'];
					$insParam['mall_name']			= $data['mall_name'];
					$insParam['revision_val']		= $data['revision_val'];
					$insParam['revision_unit']		= $data['revision_unit'];
					$insParam['revision_type']		= $data['revision_type'];
					$this->db->insert('fm_linkage_goods_config', $insParam);

					$rVal	= $data['revision_val'];
					$rUnit	= $data['revision_unit'];
					$rType	= $data['revision_type'];

					// 현재 옵션에 따른 가격 정보 저장
					$sql		= "select opt.*, spy.supply_price
									from fm_goods_option as opt
										inner join fm_goods_supply as spy 
											on ( opt.goods_seq = spy.goods_seq and 
												opt.option_seq = spy.option_seq )
									where opt.goods_seq = ? ";
					$query		= $this->db->query($sql, array($goods_seq));
					$options	= $query->result_array();
					if	($options)foreach($options as $o => $opt){
						if	($opt['option_seq']){
							unset($insParam);

							$consumer_price	= 0;
							$supply_price	= 0;
							$sale_price		= 0;
							if	($opt['consumer_price'])
								$consumer_price	= $this->calRevision($opt['consumer_price'], $rVal, $rType, $rUnit, $cutArr);
							if	($opt['supply_price'])
								$supply_price	= $this->calRevision($opt['supply_price'], $rVal, $rType, $rUnit, $cutArr);
							if	($opt['price'])
								$sale_price	= $this->calRevision($opt['price'], $rVal, $rType, $rUnit, $cutArr);
							$margin			= $sale_price - $supply_price;

							$insParam['mall_code']				= $data['mall_code'];
							$insParam['mall_name']				= $data['mall_name'];
							$insParam['goods_seq']				= $opt['goods_seq'];
							$insParam['option_seq']				= $opt['option_seq'];
							$insParam['suboption_seq']			= '';
							$insParam['option_title']			= $opt['option_title'];
							$insParam['option1']				= $opt['option1'];
							$insParam['option2']				= $opt['option2'];
							$insParam['option3']				= $opt['option3'];
							$insParam['option4']				= $opt['option4'];
							$insParam['option5']				= $opt['option5'];
							$insParam['shop_consumer_price']	= $opt['consumer_price'];
							$insParam['shop_supply_price']		= $opt['supply_price'];
							$insParam['shop_sale_price']		= $opt['price'];
							$insParam['shop_margin']			= $opt['price'] - $opt['supply_price'];
							$insParam['consumer_price']			= $consumer_price;
							$insParam['supply_price']			= $supply_price;
							$insParam['sale_price']				= $sale_price;
							$insParam['margin']					= $margin;
							$insParam['regist_date']			= date('Y-m-d H:i:s');
							$this->db->insert('fm_linkage_goods_price', $insParam);
						}
					}
				}
			}
		}else{
			$mgconfig	= $this->openmarketmodel->get_linkage_goods_config($goods_seq, 'opt');
			if		(!$mgconfig){
				// 설정값만 저장.
				foreach($mall as $m => $data){
					unset($insParam);
					$insParam['goods_seq']			= $goods_seq;
					$insParam['option_type']		= 'opt';
					$insParam['cal_type']			= 'auto';
					$insParam['mall_code']			= $data['mall_code'];
					$insParam['mall_name']			= $data['mall_name'];
					$insParam['revision_val']		= $data['revision_val'];
					$insParam['revision_unit']		= $data['revision_unit'];
					$insParam['revision_type']		= $data['revision_type'];
					$this->db->insert('fm_linkage_goods_config', $insParam);

					$rVal	= $data['revision_val'];
					$rUnit	= $data['revision_unit'];
					$rType	= $data['revision_type'];

					// 현재 옵션에 따른 가격 정보 저장
					$sql		= "select opt.*, spy.supply_price
									from fm_goods_option as opt
										inner join fm_goods_supply as spy 
											on ( opt.goods_seq = spy.goods_seq and 
												opt.option_seq = spy.option_seq )
									where opt.goods_seq = ? ";
					$query		= $this->db->query($sql, array($goods_seq));
					$options	= $query->result_array();
					if	($options)foreach($options as $o => $opt){
						if	($opt['option_seq']){
							unset($insParam);

							$consumer_price	= 0;
							$supply_price	= 0;
							$sale_price		= 0;
							if	($opt['consumer_price'])
								$consumer_price	= $this->calRevision($opt['consumer_price'], $rVal, $rType, $rUnit, $cutArr);
							if	($opt['supply_price'])
								$supply_price	= $this->calRevision($opt['supply_price'], $rVal, $rType, $rUnit, $cutArr);
							if	($opt['price'])
								$sale_price	= $this->calRevision($opt['price'], $rVal, $rType, $rUnit, $cutArr);
							$margin			= $sale_price - $supply_price;

							$insParam['mall_code']				= $data['mall_code'];
							$insParam['mall_name']				= $data['mall_name'];
							$insParam['goods_seq']				= $opt['goods_seq'];
							$insParam['option_seq']				= $opt['option_seq'];
							$insParam['suboption_seq']			= '';
							$insParam['option_title']			= $opt['option_title'];
							$insParam['option1']				= $opt['option1'];
							$insParam['option2']				= $opt['option2'];
							$insParam['option3']				= $opt['option3'];
							$insParam['option4']				= $opt['option4'];
							$insParam['option5']				= $opt['option5'];
							$insParam['shop_consumer_price']	= $opt['consumer_price'];
							$insParam['shop_supply_price']		= $opt['supply_price'];
							$insParam['shop_sale_price']		= $opt['price'];
							$insParam['shop_margin']			= $opt['price'] - $opt['supply_price'];
							$insParam['consumer_price']			= $consumer_price;
							$insParam['supply_price']			= $supply_price;
							$insParam['sale_price']				= $sale_price;
							$insParam['margin']					= $margin;
							$insParam['regist_date']			= date('Y-m-d H:i:s');
							$this->db->insert('fm_linkage_goods_price', $insParam);
						}
					}
				}
			}
		}
	}

	## 상품별 전송 대상 오픈마켓 저장
	public function save_goods_send_mall($goods_seq, $send_mall = array()){
		if	($goods_seq){
			// 초기화
			$this->db->where(array('goods_seq' => $goods_seq));
			$this->db->delete('fm_linkage_goods_mall');

			if	(is_array($send_mall) && count($send_mall) > 0){
				$malldata	= $this->get_linkage_mall();
				if	($malldata)foreach($malldata as $k => $data){
					$mall[$data['mall_code']]	= $data;
				}

				// 데이터 저장
				$insParam['goods_seq']	= $goods_seq;
				foreach($send_mall as $k => $mall_code){
					$data	= $mall[$mall_code];
					if	($data['mall_key']){
						$insParam['mall_code']	= $data['mall_code'];
						$insParam['mall_name']	= $data['mall_name'];
						$insParam['mall_key']	= $data['mall_key'];
						$this->db->insert('fm_linkage_goods_mall', $insParam);
					}
				}
			}
		}
	}

	## 오픈마켓 상품 전송 요청
	## 샵DB 테이블 변경에 영향을 받지 않기 위해 컬럼을 모두 정의함.
	## 보사대상 테이블 : fm_goods, fm_goods_option, fm_goods_suboption, fm_goods_supply, fm_linkage_goods_config, fm_linkage_goods_mall, fm_linkage_goods_price
	public function request_send_goods($goods_seq){

		$this->load->model('usedmodel');
		$shopSno	= $this->config_system['shopSno'];
		$linkage	= $this->get_linkage_config();
		$domain		= ($this->config_system['domain'])?$this->config_system['domain']:$this->config_system['subDomain'];

		if	($shopSno && $goods_seq && $linkage){
			$this->usedmodel->request_send_goods($shopSno, $goods_seq, $linkage, $domain);
		}
	}

	## 오픈마켓 주문 수집
	public function exec_order_receive($mall_code=''){
		if(!$this->chk_linkage_service()) return;
		$linkage = $this->get_linkage_config();
		$malldata		= $this->get_linkage_mall();

		$arr_order_seq = array();

		return $arr_order_seq;
	}

	## 설정에 따른 가격 계산
	public function calRevision($orgVal, $rVal, $rType, $rUnit, $cut = array()){

		// 가감 금액 계산
		if	($rUnit == 'percent'){
			$addVal	= floor($orgVal * ($rVal / 100 ));
		}else{
			$addVal	= $rVal;
		}

		// 가감 처리
		if	($rType == 'P'){
			$retVal	= $orgVal + $addVal;
		}else{
			$retVal	= $orgVal - $addVal;
		}

		// 절사 처리
		if	($cut['unit'] > 0){
			if		($cut['type'] == 'ceil')
				$retVal	= ceil($retVal / $cut['unit'] ) * $cut['unit'];
			elseif	($cut['type'] == 'round')
				$retVal	= round($retVal / $cut['unit'] ) * $cut['unit'];
			else
				$retVal	= floor($retVal / $cut['unit'] ) * $cut['unit'];
		}

		return $retVal;
	}

	## 상품금액 변경 시 몰별 금액 재계산
	public function chg_price_to_option($goods_seq){
		$this->load->model('goodsmodel');
		$linkage		= $this->get_linkage_config();
		$config			= $this->get_linkage_goods_config($goods_seq);

		if	($config[0]['cal_type'] == 'manual' ) {//수동입력방식
			
			// 기본으로 설정된 판매마켓 정보
			$malldata	= $this->get_linkage_mall();
			foreach($malldata as $k => $data){
				if	($data['default_yn'] == 'Y'){
					$mall[$data['mall_code']]['linkage_seq']	= $data['linkage_seq'];
					$mall[$data['mall_code']]['mall_code']		= $data['mall_code'];
					$mall[$data['mall_code']]['mall_name']		= $data['mall_name'];
					$mall[$data['mall_code']]['mall_key']		= $data['mall_key'];
					$mall[$data['mall_code']]['default_yn']		= $data['default_yn'];
					$mall[$data['mall_code']]['revision_val']	= $data['revision_val'];
					$mall[$data['mall_code']]['revision_unit']	= $data['revision_unit'];
					$mall[$data['mall_code']]['revision_type']	= $data['revision_type'];
				}
			}

			$linkage_seq	= $linkage['linkage_seq'];
			$options	= $this->goodsmodel->get_goods_option($goods_seq);
			if	($options) {
				$org_mallprice	= $this->get_linkage_option_price($goods_seq);
				$org_mallprice_new = array(); 
				foreach($org_mallprice as $org_o => $org_opt) {
					$org_mallprice_new[$org_opt['mall_code']][$org_opt['option_seq']] = $org_opt;
				}
				$this->db->where(array('goods_seq' => $goods_seq));
				$this->db->delete('fm_linkage_goods_price');
				foreach($mall as $k => $conf){
					$revision_val	= $conf['revision_val'];
					$revision_unit	= $conf['revision_unit'];
					$revision_type	= $conf['revision_type'];
					$mall_code		= $conf['mall_code'];
					$mall_name		= $conf['mall_name'];
					foreach($options as $o => $opt){
						unset($insParam);

						if( $org_mallprice_new[$mall_code][$opt['option_seq']] ) {//기존옵션 수정시
							$consumer_price	= $org_mallprice_new[$mall_code][$opt['option_seq']]['consumer_price'];
							$supply_price		= $org_mallprice_new[$mall_code][$opt['option_seq']]['supply_price'];
							$sale_price			= $org_mallprice_new[$mall_code][$opt['option_seq']]['sale_price'];
							$margin				= $org_mallprice_new[$mall_code][$opt['option_seq']]['margin'];
						}else{//추가된 옵션이면 자동 계산 방식						
							$consumer_price	= $this->calRevision($opt['consumer_price'], $revision_val, $revision_type, $revision_unit, $cut_arr);
							$supply_price	= $this->calRevision($opt['supply_price'], $revision_val, $revision_type, $revision_unit, $cut_arr);
							$sale_price		= $this->calRevision($opt['price'], $revision_val, $revision_type, $revision_unit, $cut_arr);
							$margin			= $sale_price - $supply_price;
						}

						$insParam['mall_code']					= $mall_code;
						$insParam['mall_name']					= $mall_name;
						$insParam['goods_seq']					= $goods_seq;
						$insParam['option_seq']					= $opt['option_seq'];
						$insParam['linkage_seq']					= $linkage_seq;
						$insParam['suboption_seq']			= '0';
						$insParam['option_title']					= $opt['option_title'];
						$insParam['option1']						= $opt['option1'];
						$insParam['option2']						= $opt['option2'];
						$insParam['option3']						= $opt['option3'];
						$insParam['option4']						= $opt['option4'];
						$insParam['option5']						= $opt['option5'];
						$insParam['shop_consumer_price']	= $opt['consumer_price'];
						$insParam['shop_supply_price']		= $opt['supply_price'];
						$insParam['shop_sale_price']			= (int) $opt['price'];//not null
						$insParam['shop_margin']				= $opt['price'] - $opt['supply_price'];
						$insParam['consumer_price']			= $consumer_price;
						$insParam['supply_price']				= $supply_price;
						$insParam['sale_price']					= (int) $sale_price;//not null
						$insParam['margin']							= $margin;
						$insParam['regist_date']					= date('Y-m-d H:i:s');
						$this->db->insert('fm_linkage_goods_price', $insParam);
					}//end foreach
				}
			}//endif
		}else{// 자동 계산 방식 재계산한다.
			
			//중간에 추가된 판매마켓 체크@2017-05-23
			$malldata	= $this->get_linkage_mall();
			unset($data);
			if	($malldata)foreach($malldata as $k => $data){
				if	($data['default_yn'] == 'Y'){
					$mall[$data['mall_code']]['linkage_seq']	= $data['linkage_seq'];
					$mall[$data['mall_code']]['mall_code']		= $data['mall_code'];
					$mall[$data['mall_code']]['mall_name']		= $data['mall_name'];
					$mall[$data['mall_code']]['mall_key']		= $data['mall_key'];
					$mall[$data['mall_code']]['default_yn']		= $data['default_yn'];
					$mall[$data['mall_code']]['revision_val']	= $data['revision_val'];
					$mall[$data['mall_code']]['revision_unit']	= $data['revision_unit'];
					$mall[$data['mall_code']]['revision_type']	= $data['revision_type'];
				}
			}
			$newmallck = 0;
			if	($mall)foreach($mall as $m => $data){
				$configtmp	= $this->get_linkage_goods_config($goods_seq, 'opt',$data['mall_code']);
				if(!$configtmp) {//없으면 설정값만 저장.
					$newmallck++;
					unset($insParam);
					$insParam['goods_seq']			= $goods_seq;
					$insParam['option_type']		= 'opt';
					$insParam['cal_type']			= 'auto';
					$insParam['mall_code']			= $data['mall_code'];
					$insParam['mall_name']			= $data['mall_name'];
					$insParam['revision_val']		= $data['revision_val'];
					$insParam['revision_unit']		= $data['revision_unit'];
					$insParam['revision_type']		= $data['revision_type'];
					$this->db->insert('fm_linkage_goods_config', $insParam);
				}
			}
			//추가된 판매마켓이 있으면 재호출
			if($newmallck) $config = $this->get_linkage_goods_config($goods_seq);

			$linkage_seq	= $linkage['linkage_seq'];
			if	($linkage['cut_price_use'] == 'y')
				$cut_arr		= array('unit'=>$linkage['cut_price_unit'], 
										'type'=>$linkage['cut_price_type']);

			$options	= $this->goodsmodel->get_goods_option($goods_seq);
			if	($options){
				$this->db->where(array('goods_seq' => $goods_seq));
				$this->db->delete('fm_linkage_goods_price');
				foreach($config as $k => $conf){
					$revision_val	= $conf['revision_val'];
					$revision_unit	= $conf['revision_unit'];
					$revision_type	= $conf['revision_type'];
					$mall_code		= $conf['mall_code'];
					$mall_name		= $conf['mall_name'];
					foreach($options as $o => $opt){
						unset($insParam);

						$consumer_price	= $this->calRevision($opt['consumer_price'], $revision_val, $revision_type, $revision_unit, $cut_arr);
						$supply_price	= $this->calRevision($opt['supply_price'], $revision_val, $revision_type, $revision_unit, $cut_arr);
						$sale_price		= $this->calRevision($opt['price'], $revision_val, $revision_type, $revision_unit, $cut_arr);
						$margin			= $sale_price - $supply_price;

						$insParam['linkage_seq']			= $linkage_seq;
						$insParam['mall_code']				= $mall_code;
						$insParam['mall_name']				= $mall_name;
						$insParam['goods_seq']				= $goods_seq;
						$insParam['option_seq']				= $opt['option_seq'];
						$insParam['suboption_seq']			= '0';
						$insParam['option_title']			= $opt['option_title'];
						$insParam['option1']				= $opt['option1'];
						$insParam['option2']				= $opt['option2'];
						$insParam['option3']				= $opt['option3'];
						$insParam['option4']				= $opt['option4'];
						$insParam['option5']				= $opt['option5'];
						$insParam['shop_consumer_price']	= $opt['consumer_price'];
						$insParam['shop_supply_price']		= $opt['supply_price'];
						$insParam['shop_sale_price']		= $opt['price'];
						$insParam['shop_margin']			= $opt['price'] - $opt['supply_price'];
						$insParam['consumer_price']			= $consumer_price;
						$insParam['supply_price']			= $supply_price;
						$insParam['sale_price']				= $sale_price;
						$insParam['margin']					= $margin;
						$insParam['regist_date']			= date('Y-m-d H:i:s');

						$this->db->insert('fm_linkage_goods_price', $insParam);
					}
				}
			}
		}
	}

	## 오픈마켓 송장 전송
	public function request_send_export($export_code){

		$this->load->model('usedmodel');

		$export_field	= (preg_match('/^B/', $export_code)) ? 'bundle_export_code' : 'export_code';

		$shopSno	= $this->config_system['shopSno'];
		$linkage	= $this->get_linkage_config();

		$query = $this->db->query("select a.order_seq,a.delivery_company_code,a.delivery_number,c.linkage_order_id
		from fm_goods_export a 
		left join fm_goods_export_item b on a.{$export_field}=b.{$export_field}
		left join fm_order_item c on b.item_seq=c.item_seq
		where a.{$export_field}=?",$export_code);
		$export_items = $query->result_array();

		foreach($export_items as $export_data){
			$query = $this->db->query("select linkage_id,linkage_order_id,linkage_mall_code from fm_order where order_seq=?",$export_data['order_seq']);
			$order_data = $query->row_array();
			if(!$order_data['linkage_id'] || !$order_data['linkage_order_id']) return false;

			$linkage_order_id	= $export_data['linkage_order_id'] ? $export_data['linkage_order_id']: $order_data['linkage_order_id'];
			$mall_code			= $order_data['linkage_mall_code'];
			$order_seq			= $export_data['order_seq'];
			$delivery_company_code	 = $export_data['delivery_company_code'];
			$delivery_number		 = $export_data['delivery_number'];

			if( !($delivery_company_code && $delivery_number) ) continue;
			$this->usedmodel->request_send_export($shopSno, $linkage, $linkage_order_id,$mall_code,$order_seq,$export_code,$delivery_company_code,$delivery_number);
		}
	}
}

/* End of file openmarketmodel.php */
/* Location: ./app/models/openmarketmodel.php */
