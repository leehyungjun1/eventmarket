<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');
require_once(APPPATH ."controllers/base/front_base".EXT);

class goods_process extends front_base {

	public function __construct() {
		parent::__construct();

		if( $this->db->es_use === true ){
			$this->load->library('elasticsearch');
		}
	}

	public function restock_notify_apply(){
		$this->load->model('ssl');
		$this->load->model("goodsmodel");
		$this->ssl->decode();

		$this->load->library('validation');
		$requestParam = $this->input->post();

		$this->validation->set_data($requestParam);
		//휴대폰번호
		$this->validation->set_rules('cellphone[]', getAlert('gv008'),'trim|required|max_length[4]|numeric|xss_clean');
		$this->validation->set_rules('goods_seq', '상품고유번호', 'trim|xss_clean|numeric');

		if($this->validation->exec()===false){
			$err = $this->validation->error_array;
			$callback = "if(parent.document.getElementsByName('{$err['key']}')[0]) parent.document.getElementsByName('{$err['key']}')[0].focus();";
			openDialogAlert($err['value'],400,140,'parent',$callback);
			exit;
		}

		$goods_seq = $requestParam['goods_seq'];
		$isMobile = $requestParam['isMobile'];
		$member_seq = !empty($this->userInfo['member_seq']) ? $this->userInfo['member_seq'] : null;
		$cellphone = implode("-",$requestParam['cellphone']);
		$optionType = $requestParam['optionType'];
		$key = get_shop_key();
		$opFlag = false;

		if($this->operation_type == 'light'){
			$callback = "parent.location.reload();";
		}else{
			if(empty($isMobile)){
				$callback = "parent.closeDialog('restockNotifyApply');";
			}else{
				$callback = "parent.closeDialogAll('restock');";
			}
		}

		if($requestParam['viewOptionsReStock'][0]){
			$opFlag = true;
			if($optionType == "divide"){
				$option = $this->setOptionFunc($requestParam['title'],$requestParam['viewOptionsReStock']);
			}else if($optionType == "join"){
				$title = explode(",",$requestParam['title'][0]);
				$viewOptionsReStock = explode("/",$requestParam['viewOptionsReStock'][0]);
				$option = $this->setOptionFunc($title,$viewOptionsReStock);
			}
		}

		$option1 = ($option['option1'] ? $option['option1']: '');
        $option2 = ($option['option2'] ? $option['option2']: '');
        $option3 = ($option['option3'] ? $option['option3']: '');
        $option4 = ($option['option4'] ? $option['option4']: '');
        $option5 = ($option['option5'] ? $option['option5']: '');

		/**
        $op_supply = $this->goodsmodel->get_goods_option_stock($goods_seq,$option1,$option2,$option3,$option4,$option5);
        if($op_supply > 0){
			//해당 상품은 현재 재고가 존재합니다.
            openDialogAlert(getAlert('gv006'),400,140,'parent',$callback);
            exit;
        }
		**/

		$this->db->select('*');
		$this->db->from('fm_goods_restock_notify AS a');
		$this->db->join("fm_goods_restock_option AS b", 'a.restock_notify_seq = b.restock_notify_seq', 'LEFT');
		$this->db->where('notify_status','none');
		$this->db->where('goods_seq', $goods_seq);
		$cellphoneQuery = "AES_DECRYPT(UNHEX(cellphone), '{$key}')='$cellphone'";
		$this->db->where($cellphoneQuery);

		if($option){
			foreach($option as $key => $val){
				$this->db->where($key, $val);
			}
		}else{
			$this->db->where('restock_option_seq is null');
		}
		$query = $this->db->get();
		$result = $query->row_array();

		if($result){
			//이미 동일한 신청내역이 있습니다.
			openDialogAlert(getAlert('gv007'),400,140,'parent',$callback);
			exit;
		}

		$this->db->insert("fm_goods_restock_notify",array(
			'goods_seq' => $goods_seq,
			'member_seq' => $member_seq,
			'cellphone' => $cellphone,
			'regist_date' => date('Y-m-d H:i:s'),
			'agent' => $_SERVER['HTTP_USER_AGENT'],
			'ip' => $_SERVER["REMOTE_ADDR"]
		));
		$restock_notify_seq = $this->db->insert_id();

		if($this->db->es_use === true){
			$cid = $this->elasticsearch->index_check('stats_goods');
			if($cid !== false){
				//카테고리 코드
				$query = "sELECT category_code fROM fm_category_link wHERE goods_seq=?";
				$query = $this->db->query($query, array($goods_seq))->result_array();
				if($query){
					foreach($query as $val){
						$goodsCategoryCode[] = str_split($val['category_code'], 4);
					}

					$goodsCategoryCode = array_reduce($goodsCategoryCode, 'array_merge', array());
					$goodsCategoryCode = array_unique($goodsCategoryCode);
					asort($goodsCategoryCode);
					$goodsCategoryCode = join(", ", $goodsCategoryCode);
				} else {
					$goodsCategoryCode = "none";
				}

				//브랜드코드
				$query = "sELECT category_code fROM fm_brand_link wHERE goods_seq=?";
				$query = $this->db->query($query, array($goods_seq))->result_array();
				if($query){
					foreach($query as $val){
						$goodsBrandCode[] = $val['category_code'];
					}
					$goodsBrandCode = join(", ", $goodsBrandCode);
				} else {
					$goodsBrandCode = "none";
				}

				$params = array(
					'goods_seq'		=> intval($goods_seq),
					'category_code' => $goodsCategoryCode,
					'brand_code'	=> $goodsBrandCode
				);

				$paramsES = $this->elasticsearch->get_stats_params('stats_goods', 'restock', $params, $this->userInfo);
				$this->elasticsearch->esClientM->index($paramsES);
				unset($response, $query, $params, $paramsES);
			}
		}

		### Private Encrypt
		$cellphone = get_encrypt_qry('cellphone');
		$sql = "update fm_goods_restock_notify set {$cellphone} where restock_notify_seq = {$restock_notify_seq}";
		$this->db->query($sql);

		### Option Add
		$option["restock_notify_seq"] = $restock_notify_seq;
		$this->db->insert("fm_goods_restock_option",$option);

		//해당 상품의 재입고 시\\n휴대폰 알림 신청이 완료었습니다.
		openDialogAlert(getAlert('gv005'),400,150,'parent',$callback);

	}

	public function setOptionFunc($arr,$arr2){
		$op = array();
		$len = sizeOf($arr);
		for($i=0; $i < $len; $i++){
			$op["title".($i+1)] = $arr[$i];
			$op["option".($i+1)] = $arr2[$i];
		}
		return $op;
	}

	// 브랜드 전체 검색부분 추가 혹시나 다른곳에서도 동종 이슈가 잇을수 있어서 AJAX 로 처리
	public function brand_search()
	{
		$classificationList = $this->input->post('classification');
		$keyword = $this->input->post('keyword');

		// 유효성 검증
		if (!$keyword && !$classificationList) {
			$result['result'] = false;
			echo json_encode($result);
			exit;
		}

		$this->db->select('*');
		$this->db->from('fm_brand');

		// 브랜드 그룹 조회
		if (!empty($classificationList) && is_array($classificationList)) {
			$classificationSql = [];
			foreach ($classificationList as $classification) {
				if (!$classification) {
					break;
				}
				$classificationSql[] = "classification LIKE '%\"seq\"%\"{$classification}\"%'";
			}

			if(count($classificationSql) == 0){				
				$classificationSql = "( (1) = '1' )";
			} else {
				$classificationSql = implode(" OR ", $classificationSql);
			}
			
			if ($classificationSql) {
				$this->db->group_start();
				$this->db->where($classificationSql);
				$this->db->group_end();
			}
		}

		// 브랜드 이름 조회
		if (!empty($keyword)) {
			$this->db->group_start();
			$this->db->like('title', $keyword , 'after');
			$this->db->or_like('title', $keyword, 'after');
			$this->db->group_end();
		}

		$result = $this->db->get()->result_array();
		echo json_encode($result);
		return true;
		exit;
	}

	// 모바일에서 추가입력옵션의 파일 업로드처리하는 함수
	public function upload_goods_inputs(){
		$this->load->library('upload');

		/* 이미지파일 확장자 */
		$this->arrImageExtensions = array('jpg','jpeg','png','gif','bmp','tif','pic');
		$this->arrImageExtensions = array_merge($this->arrImageExtensions,array_map('strtoupper',$this->arrImageExtensions));

		// 데모몰에서는 차단
		if($this->demo){
			$result = array(
				'status' => 0,
				'msg' => '데모몰에서는 업로드가 불가합니다.',
				'desc' => '업로드 불가'
			);
			echo "[".json_encode($result)."]";
			exit;
		}

		if(!$_FILES){
			$result = array('status' => 1);
			echo json_encode($result);
			exit;
		}

		$result = array(
			'status' => 0,
			'msg' => '업로드 실패하였습니다.',
			'desc' => '업로드 실패'
		);

		$path = 'data/tmp';
		$target_path = ROOTPATH.$path;
		$config['upload_path'] = $target_path;
		$config['allowed_types'] = implode('|', $this->arrImageExtensions);
		$config['max_size']	= $this->config_system['uploadLimit'];

		foreach ($_FILES['viewInputsUploader']['name'] as $k => $v) {
			if ($v) {
				if (preg_match("/[\xA1-\xFE\xA1-\xFE]/",$_FILES['viewInputsUploader']['name'][$k]) && !$_POST['allowKorean']) {
					$result = array(
						'status' => 0,
						'msg' => '파일명에 한글이 포함되어있습니다.<br />영문 파일명으로 변경 후 업로드해주세요.',
						'desc' => '한글파일명 업로드 불가'
					);
					break;
				} else {
					$file_name = "temp_" . $k . "_" . time(). sprintf("%04d", rand(0,9999));
					$config['file_name'] = $file_name;
					$this->upload->initialize($config, true);
					if ( ! $this->upload->do_upload('viewInputsUploader', $k)) {
						//업로드 실패
						$result = array(
							'status' => 0,
							'msg' => $this->upload->display_errors(),
							'desc' => getAlert('et004')
						);
					} else {
						@chmod($this->upload->upload_path.'/'.$this->upload->file_name, 0777);
						unset($result['msg']);
						unset($result['desc']);
						$result['status'] = 1;
						$result['fileList'][] = array(
							'filePath' => $path . '/' . $this->upload->file_name,
							'fileInfo' => array(
								'client_name' => $this->upload->client_name,
								'image_width' => $this->upload->image_width,
								'image_height' => $this->upload->image_height
							)
						);
					}
				}
			}else{
				$result['status'] = 1;
				$result['fileList'][] = null;
			}
		}

		echo json_encode($result);
	}

}

/* End of file goods_process.php */
/* Location: ./app/controllers/goods_process.php */