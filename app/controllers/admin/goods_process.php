<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');
require_once(APPPATH ."controllers/base/admin_base".EXT);

class goods_process extends admin_base {

	public function __construct() {
		parent::__construct();

		$this->load->helper('goods');
		$this->load->library('validation');
		$this->load->model('goodsmodel');

		$this->load->model('errorpackage');
	}

	public function category_connect(){

		$this->load->model('categorymodel');
		$this->load->helper('readurl');

		$this->validation->set_rules('categoryInputMethod', '카테고리입력방법','trim|required|max_length[10]|xss_clean');
		if($_POST['categoryInputMethod'] == "select"){
			$this->validation->set_rules('category1', '카테고리','trim|required|max_length[4]|xss_clean');
			$this->validation->set_rules('category2', '카테고리','trim|max_length[8]|xss_clean');
			$this->validation->set_rules('category3', '카테고리','trim|max_length[12]|xss_clean');
			$this->validation->set_rules('category4', '카테고리','trim|max_length[16]|xss_clean');
		}else if($_POST['categoryInputMethod'] == "lastSelect"){
			$this->validation->set_rules('categoryLastRegist[]', '카테고리','trim|required|max_length[16]|xss_clean');
		}else if($_POST['categoryInputMethod'] == "input"){
			$this->validation->set_rules('category_input[]', '카테고리','trim|xss_clean');
			$max_key = 0;
			foreach($_POST['category_input'] as $k => $data) if($data) $max_key = $k;
			for($i=0;$i<=$max_key;$i++){
				if(!$_POST['category_input'][$i]){
					$callback = "if(top.window.parent.document.getElementsByName('category_input[]')[".$i."]) top.window.parent.document.getElementsByName('category_input[]')[".$i."].focus();";
					openDialogAlert('카테고리를 입력해주세요!',400,160,'parent',$callback);
					exit;
				}
			}
		}

		if($this->validation->exec()===false){
			$err = $this->validation->error_array;
			$callback = "if(top.window.parent.document.getElementsByName('{$err['key']}')[0]) top.window.parent.document.getElementsByName('{$err['key']}')[0].focus();";
			openDialogAlert($err['value'],400,160,'parent',$callback);
			exit;
		}

		$params = $this->input->post();
		if($params['categoryInputMethod'] == "select"){
			for($i=1;$i<=4;$i++){
				if( !isset($params['category'.$i]) ) continue;
				$catecode = $params['category'.$i];
				if( $catecode ){
					$catename = $this->categorymodel->get_category_name($catecode);
					echo("<script type='text/javascript'>top.window.parent.add_category('".$catecode."','".addslashes($catename)."');</script>");
				}
			}
		}else if($params['categoryInputMethod'] == "lastSelect"){
			if(!is_array($params['categoryLastRegist'])) $params['categoryLastRegist'] = array($params['categoryLastRegist']);
			if( is_array($params['categoryLastRegist'])){

				$return 		= array();
				foreach($params['categoryLastRegist'] as $catecode){
					$r_category = array();
					$fullCode 	= '';
					$catename 	= $this->categorymodel->get_category_name($catecode);
					$tmpname 	= explode(" > ",$catename);
					foreach($tmpname as $k => $_catename){
						$t_catecode 								= substr($catecode,0,($k+1)*4);
						$r_category['select_category_txt'.($k+1)] 	= addslashes($_catename);
						$r_category['select_category_val'.($k+1)] 	= $t_catecode;
						$return[] = $r_category;
					}
				}

				echo("<script type='text/javascript'>top.window.parent.gCategorySelect.callbackCategoryList(".json_encode($return).");</script>");
			}
		}else if($params['categoryInputMethod'] == "input"){
			/*
			$parent_id = 2;
			$position = $this->categorymodel->get_next_positon($parent_id);
			$category = $this->categorymodel->get_next_category();
			$requestUrl = "http://".$_SERVER['HTTP_HOST']."/admin/category/tree";

			for($i=0;$i<=$max_key;$i++){
				// 카테고리 등록
				$data = array (
				  'operation' => 'create_node',
				  'id' => $parent_id,
				  'position' => $position,
				  'title' => $params['category_input'][$i],
				  'type' => 'folder',
				);
				$out = json_decode(readurl($requestUrl, $data));
				$parent_id = $out->id;
				$position = 0;
				$catecode = $this->categorymodel->get_category_code($parent_id);
				$catename = $this->categorymodel->get_category_name($catecode);
				echo("<script type='text/javascript'>parent.add_category('".$catecode."','".$catename."');</script>");
			}
			*/
			$parent_id	= 2;
			$position	= $this->categorymodel->get_next_positon($parent_id);
			$position = ($position)?$position:0;
			$category	= $this->categorymodel->get_next_category();
			$left		= $this->categorymodel->get_next_left();
			$depth_chk	= $max_key+1;
			$right		= $left + ($depth_chk + ($depth_chk-1));

			$this->db->where('level','1');
			$this->db->limit(1);
			$query = $this->db->get('fm_category');
			$defaultCategoryData = $query->row_array();

			for($i=0;$i<=$max_key;$i++){
				$level = (strlen($category)/4) + 1;
				$data = array (
					'parent_id'					=> $parent_id,
					'position'					=> $position,
					'title'						=> $params['category_input'][$i],
					'type'						=> 'folder',
					'left'						=> $left,
					'right'						=> $right,
					'level'						=> $level,
					'category_code'				=> $category,
					'list_default_sort'			=> $defaultCategoryData['list_default_sort'],
					'list_style'				=> $defaultCategoryData['list_style'],
					'list_count_w'				=> $defaultCategoryData['list_count_w'],
					'list_count_h'				=> $defaultCategoryData['list_count_h'],
					'list_paging_use'			=> $defaultCategoryData['list_paging_use'],
					'list_image_size '			=> $defaultCategoryData['list_image_size'],
					'list_text_align'			=> $defaultCategoryData['list_text_align'],
					'list_image_decorations'	=> $defaultCategoryData['list_image_decorations'],
					'list_info_settings'		=> $defaultCategoryData['list_info_settings'],
					'list_goods_status'			=> $defaultCategoryData['list_goods_status'],
					'search_use'				=> $defaultCategoryData['search_use'],
					'regist_date'				=> date("Y-m-d H:i:s")
				);
				$result		= $this->db->insert('fm_category', $data);
				$parent_id	= $this->db->insert_id();
				$catecode = $this->categorymodel->get_category_code($parent_id);
				$catename = $this->categorymodel->get_category_name($catecode);
				###
				$category .= "0001";
				$position = 0;
				$left++;
				$right--;
				echo("<script type='text/javascript'>top.window.parent.add_category('".$catecode."','".addslashes($catename)."');</script>");
			}
			echo ("<script type='text/javascript'>top.window.parent.$('.pcate_input').val('');</script>");
		}
		//echo "<script>parent.closeDialog('categoryPopup');</script>";
	}

	//브랜드 연결
	public function brand_connect(){

		$this->load->model('brandmodel');
		$this->load->helper('readurl');
		$params = $this->input->post();

		$this->validation->set_rules('brandInputMethod', '브랜드입력방법','trim|required|max_length[20]|xss_clean');
		if($params['brandInputMethod'] == "select"){

			$this->validation->set_rules('brands1', '브랜드','trim|required|max_length[4]|xss_clean');
			$this->validation->set_rules('brands2', '브랜드','trim|max_length[8]|xss_clean');
			$this->validation->set_rules('brands3', '브랜드','trim|max_length[12]|xss_clean');
			$this->validation->set_rules('brands4', '브랜드','trim|max_length[16]|xss_clean');
		}
		else if($params['brandInputMethod'] == "lastSelect"){
			$this->validation->set_rules('brandLastRegist[]', '브랜드','trim|required|max_length[16]|xss_clean');
		}
		else if($params['brandInputMethod'] == "input"){
			$this->validation->set_rules('brand_input[]', '브랜드','trim|xss_clean');
			$max_key = 0;
			foreach($params['brand_input'] as $k => $data) if($data) $max_key = $k;
			for($i=0;$i<=$max_key;$i++){
				if(!$params['brand_input'][$i]){
					$callback = "if(top.window.parent.document.getElementsByName('brand_input[]')[".$i."]) top.window.parent.document.getElementsByName('brand_input[]')[".$i."].focus();";
					openDialogAlert('브랜드를 입력해주세요!',400,160,'parent',$callback);
					exit;
				}
			}
		}

		if($this->validation->exec()===false){
			$err = $this->validation->error_array;
			$callback = "if(top.window.parent.document.getElementsByName('{$err['key']}')[0]) top.window.parent.document.getElementsByName('{$err['key']}')[0].focus();";
			openDialogAlert($err['value'],400,160,'parent',$callback);
			exit;
		}

		if($params['brandInputMethod'] == "select"){
			for($i=1;$i<=4;$i++){
				if( !isset($params['brands'.$i]) ) continue;
				$catecode = $params['brands'.$i];
				if( $catecode ){
					$catename = $this->brandmodel->get_brand_name($catecode);
					echo("<script type='text/javascript'>top.window.parent.add_brand('".$catecode."','".addslashes($catename)."');</script>");
				}
			}
		}else if($params['brandInputMethod'] == "lastSelect"){
			
			if(!is_array($params['brandLastRegist'])) $params['brandLastRegist'] = array($params['brandLastRegist']);
			if( is_array($params['brandLastRegist'])){
				
				$return 		= array();
				foreach($params['brandLastRegist'] as $catecode){
					$r_category = array();
					$fullCode 	= '';
					$catename 	= $this->brandmodel->get_brand_name($catecode);
					$tmpname 	= explode(" > ",$catename);
					foreach($tmpname as $k => $_catename){
						$t_catecode 								= substr($catecode,0,($k+1)*4);
						$r_category['select_category_txt'.($k+1)] 	= addslashes($_catename);
						$r_category['select_category_val'.($k+1)] 	= $t_catecode;
						$return[] = $r_category;
					}
				}

				echo("<script type='text/javascript'>top.window.parent.gCategorySelect.callbackCategoryList(".json_encode($return).");</script>");
			}
		}else if($params['brandInputMethod'] == "input"){
			/*
			$parent_id = 2;
			$position = $this->categorymodel->get_next_positon($parent_id);
			$category = $this->categorymodel->get_next_category();
			$requestUrl = "http://".$_SERVER['HTTP_HOST']."/admin/category/tree";

			for($i=0;$i<=$max_key;$i++){
				// 카테고리 등록
				$data = array (
				  'operation' => 'create_node',
				  'id' => $parent_id,
				  'position' => $position,
				  'title' => $_POST['category_input'][$i],
				  'type' => 'folder',
				);
				$out = json_decode(readurl($requestUrl, $data));
				$parent_id = $out->id;
				$position = 0;
				$catecode = $this->categorymodel->get_category_code($parent_id);
				$catename = $this->categorymodel->get_category_name($catecode);
				echo("<script type='text/javascript'>parent.add_category('".$catecode."','".$catename."');</script>");
			}
			*/
			$parent_id	= 2;
			$position	= $this->brandmodel->get_next_positon($parent_id);
			$position	= ($position)?$position:0;
			$category	= $this->brandmodel->get_next_brand();
			$left		= $this->brandmodel->get_next_left();
			$depth_chk	= $max_key+1;
			$right		= $left + ($depth_chk + ($depth_chk-1));

			$this->db->where('level','1');
			$this->db->limit(1);
			$query = $this->db->get('fm_brand');
			$defaultCategoryData = $query->row_array();

			for($i=0;$i<=$max_key;$i++){
				$level = (strlen($category)/4) + 1;
				$data = array (
					'parent_id'		=> $parent_id,
					'position'		=> $position,
					'title'			=> $_POST['brand_input'][$i],
					'type'			=> 'folder',
					'left'			=> $left,
					'right'			=> $right,
					'level'			=> $level,
					'category_code' => $category,
					'list_default_sort'			=> $defaultCategoryData['list_default_sort'],
					'list_style'				=> $defaultCategoryData['list_style'],
					'list_count_w'				=> $defaultCategoryData['list_count_w'],
					'list_count_h'				=> $defaultCategoryData['list_count_h'],
					'list_paging_use'			=> $defaultCategoryData['list_paging_use'],
					'list_image_size '			=> $defaultCategoryData['list_image_size'],
					'list_text_align'			=> $defaultCategoryData['list_text_align'],
					'list_image_decorations'	=> $defaultCategoryData['list_image_decorations'],
					'list_info_settings'		=> $defaultCategoryData['list_info_settings'],
					'list_goods_status'			=> $defaultCategoryData['list_goods_status'],
					'search_use'				=> $defaultCategoryData['search_use'],
					'regist_date'	=> date("Y-m-d H:i:s")
				);
				$result		= $this->db->insert('fm_brand', $data);
				$parent_id	= $this->db->insert_id();
				$catecode = $this->brandmodel->get_brand_code($parent_id);
				$catename = $this->brandmodel->get_brand_name($catecode);
				###
				$category .= "0001";
				$position = 0;
				$left++;
				$right--;
				echo("<script type='text/javascript'>top.window.parent.add_brand('".$catecode."','".addslashes($catename)."');</script>");
			}
			echo ("<script type='text/javascript'>top.window.parent.$('.pcate_input').val('');</script>");
		}else if($params['brandInputMethod'] == "providerBrand"){

			if( $params['providerBrandCode'] ){

				$catecode = $params['providerBrandCode'];

				$query = $this->db->query("
					select charge from fm_provider as p
					left join fm_provider_charge as c on p.provider_seq = c.provider_seq
					where p.provider_seq=? and c.category_code=?
				",array($params['provider_seq'],$catecode));
				$res = $query->row_array();
				$charge = $res['charge'];

				for($i=0;$i<(strlen($catecode)/4);$i++){
					$t_catecode = substr($catecode,0,($i+1)*4);
					$catename 	= $this->brandmodel->get_brand_name($t_catecode);
					echo("<script type='text/javascript'>top.window.parent.add_brand('".$t_catecode."','".addslashes($catename)."','".$charge."');</script>");
				}
				echo ("<script type='text/javascript'>top.window.parent.$('.pcate_input').val('');</script>");
			}
		}
		//echo "<script>parent.closeDialog('brandPopup');</script>";
	}

	//지역 연결
	public function location_connect(){

		$this->load->model('locationmodel');
		$this->load->helper('readurl');

		$params = $this->input->post();

		$this->validation->set_rules('locationInputMethod', '지역입력방법','trim|required|max_length[20]|xss_clean');
		if($params['locationInputMethod'] == "select"){

			$this->validation->set_rules('location1', '지역','trim|required|max_length[4]|xss_clean');
			$this->validation->set_rules('location2', '지역','trim|max_length[8]|xss_clean');
			$this->validation->set_rules('location3', '지역','trim|max_length[12]|xss_clean');
			$this->validation->set_rules('location4', '지역','trim|max_length[16]|xss_clean');
		}
		else if($params['locationInputMethod'] == "lastSelect"){
			$this->validation->set_rules('locationLastRegist[]', '지역','trim|required|max_length[16]|xss_clean');
		}
		else if($params['locationInputMethod'] == "input"){
			$this->validation->set_rules('location_input[]', '지역','trim|xss_clean');
			$max_key = 0;
			foreach($params['location_input'] as $k => $data) if($data) $max_key = $k;
			for($i=0;$i<=$max_key;$i++){
				if(!$params['location_input'][$i]){
					$callback = "if(top.window.parent.document.getElementsByName('location_input[]')[".$i."]) top.window.parent.document.getElementsByName('location_input[]')[".$i."].focus();";
					openDialogAlert('지역를 입력해주세요!',400,160,'parent',$callback);
					exit;
				}
			}
		}

		if($this->validation->exec()===false){
			$err = $this->validation->error_array;
			$callback = "if(top.window.parent.document.getElementsByName('{$err['key']}')[0]) top.window.parent.document.getElementsByName('{$err['key']}')[0].focus();";
			openDialogAlert($err['value'],400,160,'parent',$callback);
			exit;
		}

		if($params['locationInputMethod'] == "select"){
			for($i=1;$i<=4;$i++){
				if( !isset($params['location'.$i]) ) continue;
				$catecode = $params['location'.$i];
				if( $catecode ){
					$catename = $this->locationmodel->get_location_name($catecode);
					echo("<script type='text/javascript'>top.window.parent.add_location('".$catecode."','".addslashes($catename)."');</script>");
				}
			}
		}else if($params['locationInputMethod'] == "lastSelect"){

			if(!is_array($params['locationLastRegist'])) $params['locationLastRegist'] = array($params['locationLastRegist']);
			if( is_array($params['locationLastRegist'])){
				
				$return 		= array();
				foreach($params['locationLastRegist'] as $catecode){
					$r_category = array();
					$fullCode 	= '';
					$catename 	= $this->locationmodel->get_location_name($catecode);
					$tmpname 	= explode(" > ",$catename);
					foreach($tmpname as $k => $_catename){
						$t_catecode 								= substr($catecode,0,($k+1)*4);
						$r_category['select_category_txt'.($k+1)] 	= addslashes($_catename);
						$r_category['select_category_val'.($k+1)] 	= $t_catecode;
						$return[] = $r_category;
					}
				}
				
				echo("<script type='text/javascript'>top.window.parent.gCategorySelect.callbackCategoryList(".json_encode($return).");</script>");
			}

		}else if($params['locationInputMethod'] == "input"){
			$parent_id	= 2;
			$position	= $this->locationmodel->get_next_positon($parent_id);
			$position = ($position)?$position:0;
			$category	= $this->locationmodel->get_next_location();
			$left		= $this->locationmodel->get_next_left();
			$depth_chk	= $max_key+1;
			$right		= $left + ($depth_chk + ($depth_chk-1));

			$this->db->where('level','1');
			$this->db->limit(1);
			$query = $this->db->get('fm_location');
			$defaultCategoryData = $query->row_array();

			for($i=0;$i<=$max_key;$i++){
				$level = (strlen($category)/4) + 1;
				$data = array (
					'parent_id'		=> $parent_id,
					'position'		=> $position,
					'title'			=> $params['location_input'][$i],
					'type'			=> 'folder',
					'left'			=> $left,
					'right'			=> $right,
					'level'			=> $level,
					'location_code' => $category,
					'list_default_sort'			=> $defaultCategoryData['list_default_sort'],
					'list_style'				=> $defaultCategoryData['list_style'],
					'list_count_w'				=> $defaultCategoryData['list_count_w'],
					'list_count_h'				=> $defaultCategoryData['list_count_h'],
					'list_paging_use'			=> $defaultCategoryData['list_paging_use'],
					'list_image_size '			=> $defaultCategoryData['list_image_size'],
					'list_text_align'			=> $defaultCategoryData['list_text_align'],
					'list_image_decorations'	=> $defaultCategoryData['list_image_decorations'],
					'list_info_settings'		=> $defaultCategoryData['list_info_settings'],
					'list_goods_status'			=> $defaultCategoryData['list_goods_status'],
					'search_use'				=> $defaultCategoryData['search_use'],
					'regist_date'	=> date("Y-m-d H:i:s")
				);
				$result		= $this->db->insert('fm_location', $data);
				$parent_id	= $this->db->insert_id();
				$catecode = $this->locationmodel->get_location_code($parent_id);
				$catename = $this->locationmodel->get_location_name($catecode);
				###
				$category .= "0001";
				$position = 0;
				$left++;
				$right--;
				echo("<script type='text/javascript'>top.window.parent.add_location('".$catecode."','".addslashes($catename)."');</script>");
			}
			echo ("<script type='text/javascript'>top.window.parent.$('.pcate_input').val('');</script>");

		}else if($params['locationInputMethod'] == "providerLocation"){

			if( $params['providerLocationCode'] ){

				$catecode = $params['providerLocationCode'];

				$query = $this->db->query("
					select charge from fm_provider as p
					left join fm_provider_charge as c on p.provider_seq = c.provider_seq
					where p.provider_seq=? and c.location_code=?
				",array($params['provider_seq'],$catecode));
				$res = $query->row_array();
				$charge = $res['charge'];

				for($i=0;$i<(strlen($catecode)/4);$i++){
					$t_catecode = substr($catecode,0,($i+1)*4);
					$catename = $this->locationmodel->get_location_name($t_catecode);
					echo("<script type='text/javascript'>top.window.parent.add_location('".$t_catecode."','".addslashes($catename)."','".$charge."');</script>");
				}
				echo ("<script type='text/javascript'>top.window.parent.$('.pcate_input').val('');</script>");
			}
		}
		//echo "<script>parent.closeDialog('locationPopup');</script>";

	}

	public function upload_file(){
		$error = array('status' => 0,'msg' => '업로드 실패하였습니다.','desc' => '업로드 실패');
		$folder = "data/tmp/";
		$idx = $_POST['idx'];
		$div = $division = $_POST['division'];
		$arrDiv = config_load('goodsImageSize');
		$unique_id = md5($_SERVER['UNIQUE_ID']);
		$newFile = "tmp_".$unique_id.sprintf("%04d",rand(0,9999));
		if( in_array($div,array_keys($arrDiv)) ){
			$filename = $newFile.$div;
			$result = $this->goodsmodel->goods_temp_image_upload($filename,$folder);
			if(!$result['status']){
				if($result['error']) $error['msg'] = $result['error'];
				echo "[".json_encode($error)."]";
				exit;
			}
			$source = $result['fileInfo']['full_path'];
			$target = $result['fileInfo']['full_path'];
			/*
			$resizeResult = $this->goodsmodel->goods_temp_image_resize($source,$target,$arrDiv[$div]['width'],$arrDiv[$div]['height']);
			if(!$resizeResult['status']){
				echo "[".json_encode($error)."]";
				exit;
			}
			*/
		}else if($div == 'all'){
			$div = "large";
			$filename = $newFile.$div;
			$result = $this->goodsmodel->goods_temp_image_upload($filename,$folder);
			if(!$result['status']){
				if($result['error']) $error['msg'] = $result['error'];
				echo "[".json_encode($error)."]";
				exit;
			}
			$source = $result['fileInfo']['full_path'];

			foreach($arrDiv as $tmp => $size){
				// if( $idx > 1 && in_array($tmp,array('list1','list2')) ) continue;
				$target = $result['fileInfo']['file_path'].$newFile.$tmp.$result['fileInfo']['file_ext'];
				$resizeResult = $this->goodsmodel->goods_temp_image_resize($source,$target,$arrDiv[$tmp]['width'],$arrDiv[$tmp]['height']);
				if(!$resizeResult['status']){
					if($result['error']) $error['msg'] = $result['error'];
					echo "[".json_encode($error)."]";
					exit;
				}
			}
		}
		$result = array('status' => 1,'newFile' => "/".$folder.$newFile,'idx' => $idx,'division'=>$division,'ext' => $result['fileInfo']['file_ext']);
		echo "[".json_encode($result)."]";
	}

	//여러컷 일괄등록시@2015-02-10
	public function upload_file_multi() {
		$error 		= array('status' => 0,'msg' => '업로드 실패하였습니다.','desc' => '업로드 실패');
		$folder 	= "data/tmp/";
		$div 		= $division = "all";
		$arrDiv 	= config_load('goodsImageSize');
		$unique_id 	= md5($_SERVER['UNIQUE_ID']);
		$newFile 	= "tmp_".$unique_id.sprintf("%04d",rand(0,9999));
		$div 		= "large";
		$filename 	= $newFile.$div;
		$result 	= $this->goodsmodel->goods_temp_image_upload($filename,$folder);
		if(!$result['status']){
			if($result['error']) $error['msg'] = $result['error'];
			echo "[".json_encode($error)."]";
			exit;
		}
		$source 	= $result['fileInfo']['full_path'];
		$num 		= str_replace($filename,'',$result['fileInfo']['raw_name']);
		if (is_numeric($num)) $newFile .= $num;
		foreach($arrDiv as $tmp => $size){
			$target = $result['fileInfo']['file_path'].$newFile.$tmp.$result['fileInfo']['file_ext'];
			$resizeResult = $this->goodsmodel->goods_temp_image_resize($source,$target,$arrDiv[$tmp]['width'],$arrDiv[$tmp]['height']);
		}
		$result = array('status' => 1,'newFile' => "/".$folder.$newFile,'ext' => $result['fileInfo']['file_ext']);
		echo "[".json_encode($result)."]";
	}

	public function doGoodsHandle($goodsSeq)
	{
		$goodsSeq		= (int)$goodsSeq;
		$this->load->model('goodsHandlermodel');
		$this->load->model('searchdefaultconfigmodel');
		$this->load->model('usedmodel');

		/*
		 확인된 변수값에 대해서만 사용 할 것
		 무작정 _POST -> aPostParams 변경 시 오류 발생.
		*/
		#### AUTH
		$auth = $this->authmodel->manager_limit_act('goods_act');
		if(!$auth){
			$callback = "";
			openDialogAlert($this->auth_msg,400,160,'parent',$callback);
			exit;
		}

		if(!preg_match("/^F_SH_/",$this->config_system['service']['hosting_code'])){
			$use_per	= $this->usedmodel->get_used_space_percent();
			if($use_per > 100){
				$callback					= "";
				$popOptions['btn_title']	= '용량추가';
				$popOptions['btn_class']	= 'btn large cyanblue';
				$popOptions['btn_action']	= "window.open('https://firstmall.kr/myshop','_blank')";

				openDialogAlert("용량이 초과되어 상품을 등록 또는 수정할 수 없습니다.<br/>용량 추가를 하시길 바랍니다.",400,175,'parent',$callback,$popOptions);
				exit;
			}
		}

		/* 공통정보 입점사와, 본사 각각 최대 20개씩 제한 */
		$r_info = $this->input->post('r_info_tmp');
		$provider_seq = $this->input->post('provider_seq');
		$maxCountCommonInfo = $this->goodsmodel->get_max_count_common_info($provider_seq);
		
		if ($r_info == 'create_info' && $maxCountCommonInfo > 20) {
			openDialogAlert("공통정보는 최대 20개까지 저장가능합니다", 400, 170, 'parent', '');
			exit;
		}

		/* 다량옵션오류방지를 위하여 인코딩된 옵션폼값을 디코딩 */
		decodeFormValue($_POST['encodedFormValue'],'POST');

		$aPostParams = $this->input->post();	// 인코딩된 옵션 폼 값 디코딩 후 선언 필수

		/* 열기 고정 저장 2020.08.12 */
		$goodsType = 'bxOpenFixingGoods';
		if($aPostParams['goods_kind'] == "coupon") $goodsType = 'bxOpenFixingSocial';
		if($aPostParams['package_yn'] == 'y') $goodsType = 'bxOpenFixingPackage';
		if($aPostParams['goods_type'] == 'gift') $goodsType = 'bxOpenFixingGift';
		if(count($aPostParams['bxOpenFixing']) > 0 ) $bxOpenFixingJson = json_encode($aPostParams['bxOpenFixing']);
			else $bxOpenFixingJson = null;
		$bxOpenFixing['goods'] 			= $bxOpenFixingJson;
		$bxOpenFixing['search_page'] 	= $goodsType;
		$this->searchdefaultconfigmodel->set_search_default_new($bxOpenFixing);

		/*20180626 필수옵션만들기에서 수정시 상품코드가 없으면 undefined로 갖고옴. 빈값으로 변경*/
		if($aPostParams['goodsCode'] == 'undefined') {
			$_POST['goodsCode'] = '';
		}

		if ($aPostParams['optionUse'] == '1' && $aPostParams['optionAddPopup'] != 'y' && !$aPostParams['tmp_option_seq']) {
			openDialogAlert("필수옵션을 추가해 주세요",400,160,'parent',$callback);
			exit;
		}

		// ### 서비스별 제한 고정값 선택 :: 2016-12-20 lwh
		$fixArr = serviceLimit('F2','return');
		if(count($fixArr) > 0) foreach($fixArr as $key => $val){ $_POST[$key] = $val; }

		//티켓상품 값어치/유효기간 체크
		if( $_POST['goods_kind'] == 'coupon' ) {

			//유효기간 시작 전
			if( $_POST['socialcp_cancel_type'] == 'payoption' ) {
				$socialcp_cancel_result = true;
				foreach( $_POST['socialcp_cancel_day'] as $key => $socialcp_cancel) {
					if (!($_POST['socialcp_cancel_day'][$key]>='0')) {
						$socialcp_cancel_result	= false;
						$socialcp_cancel_key	= $key;
						break;
					}
				}

				if ($socialcp_cancel_result === false) {
					$callback		= "parent.document.getElementsByName('socialcp_cancel_day[]')[".($socialcp_cancel_key+1)."].focus();";
					$msg			= "유효기간 시작 전 취소(환불) 가능날짜를 정확히 입력해 주세요.";
					openDialogAlert($msg,520,160,'parent',$callback);
					exit;
				}

				if ($_POST['socialcp_cancel_payoption']) {
					$socialcp_cancel_result		= ( ($_POST['socialcp_cancel_payoption_percent']>='0') )?true:false;
					if( $socialcp_cancel_result === false ) {
						$callback	= "parent.document.getElementsByName('socialcp_cancel_payoption_percent[]')[".($key+1)."].focus();";
						$msg		= "유효기간 시작 전 유효기간의 취소(환불)율을 정확히 입력해 주세요.";
						openDialogAlert($msg,520,160,'parent',$callback);
						exit;
					}
				}
			} else if ($_POST['socialcp_cancel_type'] == 'pay') {
				$socialcp_cancel_result		= (($_POST['socialcp_cancel_day'][0]>='0') )?true:false;
				if ($socialcp_cancel_result === false) {
					$callback	= "parent.document.getElementsByName('socialcp_cancel_day[]')[0].focus();";
					$msg		= "유효기간 시작 전 결제확인 후 취소(환불) 가능날짜를 정확히 입력해 주세요.";
					openDialogAlert($msg,520,160,'parent',$callback);
					exit;
				}
			}

			//유효기간 종료 후 미사용 티켓상품
			if ($_POST['socialcp_use_return'] == '1') {
				$socialcp_cancel_result		= ($_POST['socialcp_use_emoney_day']>='0')?true:false;
				if ($socialcp_cancel_result === false) {
					$msg		= "유효기간 종료 후 미사용 티켓상품의 취소(환불) 가능날짜를 정확히 입력해 주세요.";
					$callback	= "parent.document.getElementsByName('socialcp_use_emoney_day')[0].focus();";
					openDialogAlert($msg,520,160,'parent',$callback);
					exit;
				}
			}

			if ($_POST['optionUse'] != '1') {
				openDialogAlert("[티켓상품]의 유효기간(또는 날짜) 필수옵션을 추가해 주세요",450,140,'parent',$callback);
				exit;
			}

			$today			= date("Y-m-d");
			foreach ($_POST as $k => $v) {
				if( $goodsSeq < 1 ) break;//수정시 체크@2017-02-06

				if ($k == 'coupon_input' &&  in_array(0,$v)) {
					$msg		= "[티켓상품]의 티켓1장의 값어치를 정확히 입력해 주세요.";
					openDialogAlert($msg,450,140,'parent',$callback);
					exit;
				}

				if ($k == 'optnewtype') {
					$couponexpire =  false;
					if (!( in_array('address',$v) || in_array('date',$v) || in_array('dayinput',$v) || in_array('dayauto',$v) )) {//coupon goods
						$msg	= "[티켓상품]의 유효기간(날짜, 자동기간, 수동기간)옵션을 추가해 주세요.";
						openDialogAlert($msg,470,150,'parent',$callback);
						exit;
					}
					if( $_POST['goodsView'] == 'look' ) {//노출시에는 유효기간 날짜 필수체크
						if (in_array('date', $v)) {
							foreach ($_POST['codedate'] as $key => $codedate) {
								if ($codedate >= $today) {
									$couponexpire		= true;
									break;
								} else {
									$social_start_date	= $codedate;
									$social_end_date	= $codedate;
								}
							}

							if ($couponexpire === false) {
								$msg		= "[티켓상품]의 유효기간을 정확히 입력해 주세요.";

								if (strstr($social_start_date,'0000-00-00') || strstr($social_end_date,'0000-00-00') || !$social_start_date  || !$social_end_date)
									$msg	.= "<br/>유효기간이 없습니다.";
								else
									$msg	.= "<br/>유효기간이 ".$codedate." 입니다.";

								openDialogAlert($msg,450,160,'parent',$callback);
								exit;
							}
						} else if (in_array('dayinput', $v)) {
							foreach ($_POST['fdayinput'] as $key => $fdayinput) {
								if ($fdayinput >= $today) {
									$couponexpire = true;
									break;
								} else {
									$social_start_date = $_POST['sdayinput'][$key];
									$social_end_date = $fdayinput;
								}
							}

							if ($couponexpire === false) {
								$msg		= "[티켓상품]의 유효기간을 정확히 입력해 주세요.";
								if (strstr($social_start_date,'0000-00-00') || strstr($social_end_date,'0000-00-00') || !$social_start_date  || !$social_end_date)
									$msg	.= "<br/>유효기간이 없습니다.";
								else
									$msg	.= "<br/>유효기간이 ".$social_start_date." ~ ".$social_end_date." 입니다.";

								openDialogAlert($msg,450,160,'parent',$callback);
								exit;
							}
						}
					}
				}//endif
			}//endfor
		}

		/* 아이콘 */
		foreach ($_POST['goodsIcon'] as $key => $goodsIcon) {
			if ($key == 0) {
				$start		= 0;
				$end		= 1;
			} else {
				$start		= $key * 2;
				$end		= $start + 1;
			}

			if ($_POST['iconDate'][$start] > $_POST['iconDate'][$end]) {
				$callback	= "parent.document.getElementsByName('iconDate[]')[".$start."].focus();";
				openDialogAlert('아이콘 노출 시작일이 종료일보다 클 수 없습니다.',500,160,'parent',$callback);
				exit;
			}
		}

		$_POST['mobile_contents']	= (in_array(strtolower($_POST['mobile_contents']),array("<p>&nbsp;</p>","<p><br></p>"))) ? '' : $_POST['mobile_contents'];
		$_POST['shippingPolicy']	= (!$_POST['shippingPolicy']) ? "shop" : $_POST['shippingPolicy'];

		if ($goodsSeq == 0) {

			// 상품 등록시 추가 처리
			// 공용정보 추가 :: 2016-05-09 lwh
			$_POST['info_name']		= $_POST['info_name_view'];
			$_POST['info_select']	= $_POST['info_select_view'];

			if ($_POST['largeGoodsImage'][0] ||
				$_POST['viewGoodsImage'][0] ||
				$_POST['list1GoodsImage'][0] ||
				$_POST['list2GoodsImage'][0] ||
				$_POST['thumbViewGoodsImage'][0] ||
				$_POST['thumbCartGoodsImage'][0] ||
				$_POST['thumbScrollGoodsImage'][0]) {

				/* 디스크 용량 체크 */
				$data_used		= $this->usedmodel->used_limit_check();
				if (!$data_used['type']){
					openDialogAlert($data_used['msg'],600,340,'parent','');
					exit;
				}
			}

		} else {

			// 상품 수정시 추가 처리
			$oldgoods		= $this->goodsmodel->get_goods($goodsSeq);
			if ($_POST['old_update_date'] != $oldgoods['update_date']) {
				$_POST['admin_log']	= $oldgoods['admin_log'];

				if( $_POST['goods_modify_ok'] != "ok" ) {
					$callback		= 'parent.$("input[name=goods_modify_ok]").val("");parent.loadingStop("body",true);';
					openDialogAlert("다른 페이지에서 상품정보가 수정되었습니다.<br/> 현재 입력된 내용을 무시하고 다른 페이지에서 수정된 정보를 확인합니다.<br/> (현재 입력된 정보를 초기화하고 새로고침)",540,190,'parent',$callback);
					exit;
				}

			}

		}

		//페이스북 피드 관련 착불 배송비 데이터를 받기 위해 아래 설정 추가
		//DB 테이블 추가를 피하기 위해 고정 배송비 필드에 착불 배송비 값을 입력 함
		//데이터 구분은 feed_pay_type를 이용하기 바람 19.05.30 kmj
		if ($_POST['feed_ship_type'] == "E" && $_POST['feed_pay_type'] == "postpay") {
		    $_POST['feed_std_fixed']	= $_POST['feed_std_postpay'];
		    unset($_POST['feed_std_postpay']);
		}
		
		$result_goodsHandle = $this->goodsHandlermodel->goodsHandle($goodsSeq);
		$result = $result_goodsHandle['goodsSeq'];

		$this->goodsmodel->default_price($result);

		if($result){
			$catalogPage = $_POST['catalogPage'] ? $_POST['catalogPage'] : "catalog";

			if ($goodsSeq > 0) {	// 상품수정
				$callback			= "top.window.parent.document.location.reload();";

				if($_POST['goods_type']=='gift')
					$catalogPage	= 'gift_'.$catalogPage;
				elseif ($_POST['goods_kind']=='coupon')
					$catalogPage	= "social_catalog";

				if ($_POST['package_yn']=='y')
					$catalogPage	= 'package_'.$catalogPage;

				if ($_POST['query_string'])
					$catalogPage	.= "?".$_POST['query_string'];

				if ($_POST['save_type']=='list')
					$callback		= "top.window.parent.document.location.replace('../goods/{$catalogPage}');";

				$modeAlertText		= '수정';


			} else {				//상품등록

				if($_POST['goods_kind'] == 'coupon') {
					$catalogPage	= 'social_'.$catalogPage;
				}

				if($_POST['package_yn']=='y') {
					$catalogPage	= 'package_'.$catalogPage;
				}

				switch($_POST['goods_type']) {
					case 'gift':
						if ($_POST['save_type']=='list') {
							$callback	= "parent.document.location.replace('../goods/gift_{$catalogPage}');";
						} else {
							$callback	= "parent.document.location.replace('../goods/gift_regist?no={$result}');";
						}
						break;
					default:
						if ($_POST['save_type']=='list') {
							$callback	= "top.window.parent.document.location.replace('../goods/{$catalogPage}');";
						} else {
							if($_POST['package_yn']=='y') {
								$callback	= "top.window.parent.document.location.replace('../goods/regist?no={$result}&package_yn=y');";
							}else{
								$callback	= "top.window.parent.document.location.replace('../goods/regist?no={$result}');";
							}
							
						}
				}



				$modeAlertText		= '저장';

			}

			if(!$this->config_system['first_goods_date']){ // 상품최초등록일
				$this->load->model('adminenvmodel');
				$update_params['first_goods_date']		= date('Y-m-d H:i:s',time());
				$where_params['shopSno']					= $this->config_system['shopSno'];
				$this->adminenvmodel->update($update_params, $where_params);
				$this->config_system['first_goods_date']= $update_params['first_goods_date'];
			}

			switch ($_POST['goods_type']) {
				case	'gift' :
					$alertText		= "사은품이 {$modeAlertText} 되었습니다.";
					break;

				case	'coupon' :
					$alertText		= "티켓상품이 {$modeAlertText} 되었습니다.";
					break;

				default :
					$alertText		= "상품이 {$modeAlertText} 되었습니다.";
			}

			$openDialogAlertWitdh = 400;
			$openDialogAlertHeight = 140;
			// 오픈마켓 상품 여부 확인
			if($goodsSeq){
				$this->load->model('connectormodel');
				$market = $this->connectormodel->getUseAllMarkets();

				$marketParams						= array();
				$marketParams['fmGoodsSeq']			= $goodsSeq;
				$marketParams['manualMatched']		= 'N';
				$marketParams['market']				= array_keys($market);

				$marketProductList	= $this->connectormodel->getMarketProductList($marketParams);
				if(count($marketProductList)>0){
					$alertText .= "<br/><br/>※ 오픈마켓 수정 결과는 <a href=\"/admin/market_connector/market_product_list\" target=\"_blank\">[오픈마켓>상품관리]</a>에서 확인하시기 바랍니다.";
					$openDialogAlertWitdh = 480;
					$openDialogAlertHeight = 190;
				}
			}

			//이미지호스팅 결과 메세지
			if($result_goodsHandle['imagehosting']) {
				$alertText	.= $result_goodsHandle['imagehosting']['message'];
				$openDialogAlertWitdh = $result_goodsHandle['imagehosting']['dialogwidth'];
				$openDialogAlertHeight = $result_goodsHandle['imagehosting']['dialogheight'];
			}
			openDialogAlert($alertText, $openDialogAlertWitdh, $openDialogAlertHeight, 'parent', $callback);
		}
	}

	public function regist(){
		$this->doGoodsHandle(0);
		return;
	}

	public function modify(){

		//$goodsSeq = $this->input->post('goodsSeq');
		$goodsSeq = (int) $_POST['goodsSeq'];
		if($goodsSeq < 1){
			$callback = "";
			$msg = "수정할 상품이 없습니다!";
			openDialogAlert($msg,400,160,'parent',$callback);
			exit;
		}

		$this->doGoodsHandle($goodsSeq);
		return;
	}

	// 실시간 이미지 업로드 :: 2016-04-29 lwh
	public function goods_img_upload(){
		$aPostParams = $this->input->post();
		$type		= ($aPostParams['type']) ? $aPostParams['type'] : 'each';
		$idx		= $aPostParams['idx'];
		$goodsSeq	= $aPostParams['goodsSeq'];
		$imglabel	= $aPostParams['ImageGoodsLabel'];
		$fileColor	= $aPostParams['fileColorradio'];
		$division	= $aPostParams['division'];
		$uploadImg	= (is_array($aPostParams['uploadImg'])) ? $aPostParams['uploadImg'] : array($aPostParams['uploadImg']);
		$provider_seq = $aPostParams['provider_seq'];

		/* 이미지호스팅 사용 여부 및 기본 값 세팅 */
		$this->load->library('imagehostinglibrary');
		$imagestore_type = [];
		$imagestore_type['imagestore_division'] = ($provider_seq == "1") ? "goods_headquaters" : "goods_provider";
		$imagestore_type['imagestore_item'] = "goods_image";
		$isImagehosting = $this->imagehostinglibrary->isImagehostingUse($imagestore_type);

		$params_imagehosting = [];
		$params_imagehosting['isImagehosting'] = $isImagehosting;
		if($isImagehosting == true){
			$params_imagehosting['imagestore_type'] = $imagestore_type;
		}
		
		/* 기존 저장된 이미지에서 변경한 이미지가 있는지 확인 및 삭제 */
		if($uploadImg[0] && $type == 'each'){
			$delImgseq = array();
			$delImages = array();
			$target_idx = $idx + 1;
			$oldImages = $this->goodsmodel->get_goods_image($goodsSeq);
			$goodsimg = array('large','view','list1','list2','thumbView','thumbCart','thumbScroll');

			// 삭제된 이미지 재정렬
			foreach($goodsimg as $k => $imgtype){
				$oldImageSort[$imgtype] = $oldImages[$target_idx][$imgtype];
			}

			foreach($oldImageSort as $imgtype=>$data){
				$oldImage = $data['image'];
				if(!$oldImage) continue;
				if($division == 'all'){
					$delImgseq[] = $data['image_seq'];
					$delImages[] = $oldImage;
				}else if($division == $data['image_type']){
					$delImgseq[] = $data['image_seq'];
					$delImages[] = $oldImage;
				}
			}

			/* 변경된 기존 이미지 및 DB 연결 삭제 */
			foreach($delImages as $k => $delImage){
				@unlink(ROOTPATH.$delImage);
				// 기존 이미지 연결 삭제
				$this->db->delete('fm_goods_image', array('image_seq' => $delImgseq[$k]));
			}
		}

		/* 이미지 수정 :: 2016-04-21 lwh */
		$imgType_arr = array('large','view','list1','list2','thumbView','thumbCart','thumbScroll');
		$r_img_type = array('large','view','list1','list2');

		/* 업로드 대상 이미지 추출 :: 2016-04-21 lwh */
		foreach( $imgType_arr as $imgType ){
			if($division == 'all' || $division == $imgType){
				foreach($uploadImg as $key => $img){
					$target_img = str_replace('view',$imgType,$img);
					$upImg[$imgType.'GoodsImage'][] = $target_img;
					$aPostParams[$imgType.'GoodsImage'][] = $target_img;
				}
			}
		}

		$i = 0;
		foreach( $imgType_arr as $k => $imgType ){
			if(!$upImg[$imgType.'GoodsImage']) continue;

			// 실시간 이미지 업로드
			$res = $this->goodsmodel->upload_goodsImage($upImg[$imgType.'GoodsImage'],$delImages[$i],$params_imagehosting);
			
			$res_imagehosting = $res['result_imagehosting'];
			unset($res['result_imagehosting']);
			if($isImagehosting === true){
				$newPath ='';
				foreach($res_imagehosting as $imagehosting_array){
					$result_imagehosting['goods_image'][] = $imagehosting_array;
					$newPath[] = $imagehosting_array['new_path'];
				}
			}else{
				$newPath = $res;
			}

			// 실시간 이미지 연결
			$this->goodsmodel->insert_goodsImage($imgType.'GoodsImage',$goodsSeq,$newPath,$idx);

			// 개별컷 변경으로 경로 워터마크 이미지 경로 보정
			$this->load->model('watermarkmodel');
			if(in_array($imgType, $r_img_type)){
				foreach($upImg[$imgType.'GoodsImage'] as $image)
				{
					if( substr_count($image,'/data/tmp/') > 0 )
					{
						$from = $image;
						$to = $this->goodsmodel->get_target_goodsImage($image);
						$from = str_replace('//','/',ROOTPATH.$from);
						$to = str_replace('//','/',ROOTPATH.$to);
						$this->watermarkmodel->move_target_image($from,$to);
					}
				}
			}
			$i++;
			$res_img[] = $newPath;
		}

		if($result_imagehosting){
			$imagehostingMassage = $this->imagehostinglibrary->message($result_imagehosting);
			$res_img['imagehosting'] = $imagehostingMassage;	
		}

		// 라벨 및 매칭컬러 업데이트 :: 2016-05-02 lwh
		if($division != 'all'){
			$aPostParams[$division.'GoodsLabel'][] = $imglabel;
			$this->goodsmodel->set_goodsImg_update($goodsSeq,$division,$idx,$imglabel,$fileColor);
			if($imglabel)	$res_img['label'] = true;
			if($fileColor)	$res_img['color'] = true;
		}
		echo json_encode($res_img);
	}

	// 이미지 순서 및 삭제 재지정 :: 2016-05-03 lwh
	public function goods_img_sort(){

		$goodsSeq	= $this->input->post('goodsSeq');
		$cut_number = $this->input->post('cut_number');
		$cut_first	= $this->input->post('cut_first');
		$del_cut	= $this->input->post('del_cut');

		if(!$goodsSeq){
			$res['result'] = false;
			echo json_encode($res);
			exit;
		}

		// 대표이미지가 선택되지 않고 넘어 왔을 떄 첫번째 사진을 대표이미지로 지정.
		if(!$cut_first){
			$cut_first = $cut_number[0];
		}

		$this->load->model('goodsmodel');
		$images = $this->goodsmodel->get_goods_image($goodsSeq);

		// 기존 이미지 삭제
		foreach($del_cut as $delImage){
			foreach($images[$delImage] as $old_img){
				@unlink(ROOTPATH.$old_img['image']);
			}
		}

		// 기존 이미지 연결 삭제
		$this->db->delete('fm_goods_image', array('goods_seq' => $goodsSeq));

		// 이미지 재 연결
		$idx 			= 1;
		$newCutNumber 	= array();
		foreach($cut_number as $cutnum => $old_cutnum){
			if($old_cutnum == $cut_first){
				$new_cutnum = 1;
			}else{
				$new_cutnum = $idx + 1;
				$idx++;
			}
			$newCutNumber[$new_cutnum-1] = $old_cutnum;	
			foreach($images[$old_cutnum] as $old_img){
				$imgInfo 				= $old_img;
				$imgInfo['cut_number'] 	= $new_cutnum;
				unset($imgInfo['image_seq'], $imgInfo['imageWidth'], $imgInfo['imageHeight']);
				$this->db->insert('fm_goods_image', $imgInfo);
			}
		}

		ksort($newCutNumber);

		$res['result']		= true;
		$res['cut_number']	= $newCutNumber;
		echo json_encode($res);
		exit;
	}

	// 에디터 실시간 저장 :: 2016-05-04 lwh
	public function goods_edit_upload(){
		$auth = $this->authmodel->manager_limit_act('goods_act');
		if(!$auth){
			openDialogAlert("권한이 없습니다.",400,140,'parent',"");
			exit;
		}

		$aPostParams	= $this->input->post();
		$goodsSeq		= $aPostParams['goodsSeq'];
		$contents_type	= $aPostParams['contents_type'];
		$regist_date	= $aPostParams['regist_date'];
		$provider_seq	= $aPostParams['provider_seq'];
		$info_seq		= $aPostParams['info_select_seq'];
		$mode			= $aPostParams['mode'];
		$r_info			= $aPostParams['r_info'];

		//신규 등록 시 기존 info_seq 초기화
		if($r_info == "create_info"){ 
			$info_seq = '';

			/* 공통정보 입점사와, 본사 각각 최대 20개씩 제한 */
			$maxCountCommonInfo = $this->goodsmodel->get_max_count_common_info($provider_seq);
			if ($maxCountCommonInfo >= 20) {
				openDialogAlert("공통정보는 최대 20개까지 저장가능합니다", 400, 170, 'parent', '');
				exit;
			}
		}

		if($goodsSeq || $mode == "info_only_update"){

			// 타입 재지정
			switch($contents_type){
				case "goodscontents":
					$upcolumn = 'contents';
					break;
				case "mobile_contents":
					$upcolumn = 'mobile_contents';
					break;
				case "commonContents":
					$upcolumn = 'common_contents';
					break;
			}

			// 에디터 이미지 경로 변경 업데이트 :: 2016-05-09 lwh
			$aPostParams[$upcolumn] = $_POST['view_textarea'];
			$imgRes	= $this->goodsmodel->set_goodImages($goodsSeq, $aPostParams);
			$editor['contents']			= $imgRes['contents'];
			$editor['mobile_contents']	= $imgRes['mobile_contents'];
			$editor['common_contents']	= $imgRes['common_contents'];
			if(!empty($imgRes['result_imagehosting'])) $result_imagehosting = $imgRes['result_imagehosting'];
			
			if($upcolumn == 'common_contents'){
				// 공용정보
				if($info_seq) $addset = ", info_seq = '".$info_seq."'";

				/* INFO */
				$_REQUEST['tx_attach_files'] = (!empty($aPostParams['tx_attach_files'])) ? $aPostParams['tx_attach_files']:'';

				$common_contents	= adjustEditorImages($editor['common_contents'], "/data/editor/");
				$params['info_value']	= $common_contents;

				$params['info_name']	= $aPostParams['info_name'];
				$params['regist_date']	= date("Y-m-d H:i:s");

				if($info_seq){	// UPDATE

					$info_name				= explode("  [고유번호", $params['info_name']);
					$params['info_name']	= $info_name[0];

					$data = filter_keys($params, $this->db->list_fields('fm_goods_info'));
					$this->db->where('info_seq', $info_seq);
					$result = $this->db->update('fm_goods_info', $data);

				}else{			// INSERT

					if($params['info_name'] && $params['info_value']){
						$params['info_provider_seq'] = $provider_seq;
						$result		= $this->db->insert('fm_goods_info', $params);
						$info_seq	= $this->db->insert_id();

						if($mode != "info_only_update"){
							$this->db->where('goods_seq', $goodsSeq);
							$result = $this->db->update('fm_goods', array('info_seq'=>$info_seq));
						}
					}
				}
			}else{
				// PC 또는 모바일 변경의 경우 설명등록값을 초기화 :: 2016-05-11 lwh
				$addset = ", mobile_contents_copy = 'N'";
			}

			// 반응형 일 경우 PC 설명이 없을때만 모바일 설명과 동일하게 저장 :: 2019-02-08 pjw
			if( $this->config_system['operation_type'] == 'light' && $contents_type !== 'commonContents' ){
				$editor['contents'] = $editor['mobile_contents'];

				// 기존 프로세스에 영향을 주지 않고 상품설명을 직접 저장
				$query = "update fm_goods set `contents` = ?, `mobile_contents` = ? ".$addset." where `goods_seq` = ? ";
				$this->db->query($query,array( $editor['contents'], $editor['mobile_contents'] , $goodsSeq ));
				$contents_type = 'mobile_contents';
				$upcolumn = 'mobile_contents';

			}else if($mode != "info_only_update"){

				// 기존 저장 프로세스
				$query = "update fm_goods set `".$upcolumn."` = ? ".$addset." where `goods_seq`=?";
				$this->db->query($query,array($editor[$upcolumn],$goodsSeq));

			}

			if($mode != "info_only_update"){
				echo "<script type='text/javascript'>";
				if($upcolumn == 'common_contents' && $info_seq){
					echo "parent.$('#info_select_seq').val('".$info_seq."');";
				}
				echo "parent.$('#".$contents_type."_view').html('".addslashes($editor[$upcolumn])."');";
				echo "parent.$('#".$contents_type."').text('".addslashes($editor[$upcolumn])."');";
				echo "</script>";

				$callback = "";
			}else{
				$callback	= "parent.location.reload();";
			}

			$dialogwidth = 400;
			$dialogheight = 160;
			$message = "";
			if($result_imagehosting){
				$imagehostingMassage = $this->imagehostinglibrary->message($result_imagehosting);
				$message = $imagehostingMassage['message'];
				$dialogwidth = $imagehostingMassage['dialogwidth'];
				$dialogheight = $imagehostingMassage['dialogheight'];
			}

			openDialogAlert("저장 되었습니다" . $message, $dialogwidth, $dialogheight,'parent',$callback);
			exit;

		}
	}

	//일괄업데이트 > 공용정보업데이트 > 공용정보 추가/수정
	public function goods_info_update(){

		$_POST['goodsSeq'] = "common";
		$this->goods_edit_upload();

	}

	// 모바일 상품설명 PC동일하게 등록 :: 2016-05-11 lwh
	public function edit_mobile_copy(){
		$goodsSeq	= $_POST['goodsSeq'];
		if($goodsSeq){
			$goods		= $this->goodsmodel->get_goods($goodsSeq);
			if($goods['contents']){
				$mobile_contents = $this->goodsmodel->set_mobile_contents($goods['contents'],$goods['goods_seq']);
				echo $mobile_contents;
			}else{
				// PC 상품 설명 없음.
				echo 'N';
			}
		}else{
			$mobile_contents = $this->goodsmodel->set_mobile_contents($_POST['contents'],'');
			return $mobile_contents;
		}
	}

	public function goods_copy(){
		#### AUTH
		$auth = $this->authmodel->manager_limit_act('goods_act');
		if(!$auth) exit;

		if(!preg_match("/^F_SH_/",$this->config_system['service']['hosting_code'])){
			$this->load->model('usedmodel');
			$use_per	= $this->usedmodel->get_used_space_percent();
			if($use_per > 100){
				echo "diskfull";
				exit;
			}
		}

		$oldSeq = $_GET['goods_seq'];

		### FM_GOODS
		$goodSeq = $this->goodsmodel->copy_goods($oldSeq);

		### GOODS_DEFAULT
		$result = $this->goodsmodel->copy_goods_default('fm_category_link', $oldSeq, $goodSeq, 'category_link_seq');
		$result = $this->goodsmodel->copy_goods_default('fm_brand_link', $oldSeq, $goodSeq, 'category_link_seq');
		$result = $this->goodsmodel->copy_goods_default('fm_goods_addition', $oldSeq, $goodSeq, 'addition_seq');
		$result = $this->goodsmodel->copy_goods_default('fm_goods_icon', $oldSeq, $goodSeq, 'icon_seq');
		$result = $this->goodsmodel->copy_goods_default('fm_goods_input', $oldSeq, $goodSeq, 'input_seq');
		$result = $this->goodsmodel->copy_goods_default('fm_goods_relation', $oldSeq, $goodSeq, 'relation_seq');
		$result = $this->goodsmodel->copy_goods_default('fm_location_link', $oldSeq, $goodSeq, 'location_link_seq');
		$result = $this->goodsmodel->copy_goods_default('fm_goods_socialcp_cancel', $oldSeq, $goodSeq, 'seq');

		### OPTION : fm_goods_option, fm_goods_suboption, fm_goods_supply
		$result = $this->goodsmodel->copy_goods_option($oldSeq, $goodSeq);
		/*
		$result = $this->goodsmodel->copy_goods_default('fm_goods_suboption', $oldSeq, $goodSeq, 'suboption_seq');
		$result = $this->goodsmodel->copy_goods_default('fm_goods_option', $oldSeq, $goodSeq, 'option_seq');
		$result = $this->goodsmodel->copy_goods_default('fm_goods_supply', $oldSeq, $goodSeq, 'supply_seq');
		*/

		/* 총재고 수량 입력 */
		$this->goodsmodel->total_stock($goodSeq);

		/* 상품가 업데이트 */
		$this->goodsmodel->default_price($goodSeq);

		### GOODS_IMAGE
		$result = $this->goodsmodel->copy_goods_image('fm_goods_image', $oldSeq, $goodSeq, 'image_seq');

		// 할인혜택 금액 저장
		$this->load->model('goodssummarymodel');
		$this->goodssummarymodel->set_event_price(array('goods'=>array($goodSeq)));

		if(!$this->config_system['first_goods_date']){ // 상품최초등록일
			$this->load->model('adminenvmodel');
			$update_params['first_goods_date']		= date('Y-m-d H:i:s',time());
			$where_params['shopSno']					= $this->config_system['shopSno'];
			$this->adminenvmodel->update($update_params, $where_params);
			$this->config_system['first_goods_date']= $update_params['first_goods_date'];
		}
		
		$ship_grp_seq_list = $this->goodsmodel->get_ship_grp_seq_by_goods_seq(array($goodSeq, $oldSeq));
		// 배송그룹의 실물상품수를 재 정렬한다.
		if(count($ship_grp_seq_list)>0) {
		    $this->load->model('shippingmodel');
		    $this->shippingmodel->group_cnt_adjust($ship_grp_seq_list);
		}

		//상품 복사 로그 저장
		$goods					= array();
		$actionAdminName		= $this->managerInfo['mname'];
		$goods['admin_log']		= "<div>".date("Y-m-d H:i:s")." 관리자(".$actionAdminName.")가 상품 [".$oldSeq."]으로부터 복사하였습니다. (".$_SERVER['REMOTE_ADDR'].")</div>";
		$this->db->where('goods_seq', $goodSeq);
		$result	= $this->db->update('fm_goods', $goods);

		###
		//$result = "test L ".$goodsSeq;
		echo $goodSeq;
	}

	public function _icon_cleanup()
	{
		$icon_dir = "./data/icon/goods/";
		// 아이콘이미지가 없을경우 설정값 제거
		$r_icon = code_load('goodsIcon');
		foreach($r_icon as $icon){
			$icon_filename = $icon['codecd'].".gif";
			if( !file_exists( $icon_dir.$icon_filename ) ){
				code_save('goodsIcon',array($icon['codecd']=>''));
			}
		}
	}

	public function icon(){

		// 아이콘 이미지 정리
		$this->_icon_cleanup();

		$this->validation->set_rules('goodsIconImg', '추가할 아이콘', 'trim|required|xss_clean');
		if($this->validation->exec()===false){
			$err = $this->validation->error_array;
			$callback = "if(parent.document.getElementsByName('{$err['key']}')[0]) parent.document.getElementsByName('{$err['key']}')[0].focus();";
			openDialogAlert($err['value'],400,160,'parent',$callback);
			exit;
		}

		$icon_name = date('YmdHis').rand(0,9);
		$new_path = "data/icon/goods/{$icon_name}".".gif";

		copy(ROOTPATH.$this->input->post('goodsIconImg'),ROOTPATH.$new_path);
		chmod(ROOTPATH.$new_path,0777);

		code_save('goodsIcon',array($icon_name=>'사용자'));
		$callback = "parent.set_goods_icon();";
		openDialogAlert("설정이 저장 되었습니다.",400,160,'parent',$callback);
	}

	// 아이콘 삭제
	public function set_icon_delete(){

		if(!$this->input->get('code')){
			echo "error";
		}else{
			@unlink(ROOTPATH.'/data/icon/goods/'.$this->input->get('code').'.gif');
			code_delete('goodsIcon',$this->input->get('code'));
			echo "success";
		}
	}

	public function del(){
		$no = (int) $_GET['no'];
		$arrDelTable = array(
			'fm_goods_option',
			'fm_goods_suboption',
			'fm_goods_supply',
			'fm_goods_input',
			'fm_goods_image',
			'fm_goods_icon',
			'fm_goods_addition',
			'fm_category_link',
			'fm_goods'
		);
		foreach($arrDelTable as $table){
			$query = "delete from `".$table."` where goods_seq=?";
			$this->db->query($query,array($no));
		}
		$params = array('goods_seq'=>$no);
		$this->errorpackage->del_error($params);
	}


	public function get_info(){
		$seq	= $this->input->get('seq');
		$query 	= $this->db->query("select * from fm_goods_info where info_seq = '{$seq}'");
		$data 	= $query->result_array();
		$contents = isset($data[0]['info_value']) ? $data[0]['info_value'] : " ";
		$result = array("contents"=>$contents);
		echo "[".json_encode($result)."]";
	}


	public function goods_delete(){

		$goods_arr = $this->input->get('goods_seq');
		// 연결된 배송그룹을 가져온다.
		$ship_grp_seq_list = $this->goodsmodel->get_ship_grp_seq_by_goods_seq($goods_arr);
		foreach($goods_arr as $k){
			$result	= $this->goodsmodel->delete_goods($k);
			if	($result['status'] === false){
				$failmsg[]	= $result['msg'];
				$fail++;
			}else{
				$params = array('goods_seq'=>$k);
				$this->errorpackage->del_error($params);
				$success++;
			}
		}

		if		(count($failmsg) > 0)	$msg	= array_unique($failmsg);
		if		(count($goods_arr) == $fail ){
			$msg[]			= '위 사유로 삭제실패하였습니다.';
			$result			= implode('<br/>', $msg);
			$display_delete	= false;
		}elseif	($fail > 0 && $success > 0){
			$msg[]			= '일부 상품이 삭제되었습니다.';
			$result			= implode('<br/>', $msg);
			$display_delete	= true;
		}else{
			$result			= '삭제되었습니다.';
			$display_delete	= true;
		}
		
		// 배송그룹의 실물상품수를 재 정렬한다.
		if(count($ship_grp_seq_list)>0) {
		    $this->load->model('shippingmodel');
		    $this->shippingmodel->group_cnt_adjust($ship_grp_seq_list);
		}

		echo $result;
	}

	/*
	 * 엑셀 다운로드 항목 설정
	 */
	public function download_write(){

		$aParamsPost 			= $this->input->post();
		/* 신 버전 상품 다운로드 항목 설정 */
		if($aParamsPost['mode'] == 'newform'){
			// 다운로드 항목 유효성 검증
			if(count($aParamsPost['chk_cell'])<1){
				$callback = "parent.document.getElementsByName('name')[0].focus();";
				openDialogAlert("다운로드 항목을 1개 이상 설정해 주세요.",400,160,'parent',$callback);
				exit;
			}

			$sItem = implode("|",$aParamsPost['chk_cell']);
			$aParams['name'] = $aParamsPost['form_name'];
			$aParams['criteria'] = 'ITEM';
			$aParams['item'] = $sItem;

		}else{
			/* 구 버전 상품 다운로드 항목 설정 */

			// 다운로드 항목 유효성 검증
			$this->validation->set_rules('name', '이름', 'trim|required|xss_clean');
			
			if($this->validation->exec()===false){
				$err = $this->validation->error_array;
				$callback = "if(parent.document.getElementsByName('{$err['key']}')[0]) parent.document.getElementsByName('{$err['key']}')[0].focus();";
				openDialogAlert($err['value'],400,160,'parent',$callback);
				exit;
			}

			if(count($aParamsPost['downloads_item_use']) < 1){
				$callback = "parent.document.getElementsByName('name')[0].focus();";
				openDialogAlert("다운로드 항목을 1개 이상 설정해 주세요.",400,160,'parent',$callback);
				exit;
			}
	
			$sItem = implode("|",$aParamsPost['downloads_item_use']);
			$aParams['name'] = $aParamsPost['name'];
			$aParams['criteria'] = 'ITEM';
			$aParams['item'] = $sItem;
		}

		$aData = get_data("fm_exceldownload",array("gb"=>'GOODS',"provider_seq"=>'1'));

		if(!$aData){
			$aParams['provider_seq'] = 1;
			$aParams['gb'] = 'GOODS';
			$aParams['regdate'] = date("Y-m-d H:i:s");
			$aParams['update_date'] = date("Y-m-d H:i:s");
			$bResult = $this->db->insert('fm_exceldownload', $aParams);
			$msg	= "등록 되었습니다.";
		}else{
			$aParams['update_date']		= date("Y-m-d H:i:s");
			$this->db->where(array("gb"=>'GOODS',"provider_seq"=>'1'));
			$bResult = $this->db->update('fm_exceldownload', $aParams);
			$msg	= "수정 되었습니다.";
		}
		$func	= "parent.location.reload();";

		openDialogAlert($msg,400,160,'parent',$func);
	}

	//excel file down
	public function file_down(){
		$this->load->helper('download');
		if(is_file($_GET['realfiledir'])){
			$data = @file_get_contents($_GET['realfiledir']);
			force_download($_GET['filenames'], $data);
			exit;
		}
	}

	public function excel_down(){
		$this->load->model('excelgoodsmodel');
		if(is_array($_POST)){
			$this->excelgoodsmodel->create_excel_list($_POST);
		}else{
			$this->excelgoodsmodel->create_excel_list($_GET);
		}
		exit;
	}

	public function excel_upload(){
		if(!preg_match("/^F_SH_/",$this->config_system['service']['hosting_code'])){
			$this->load->model('usedmodel');
			$use_per	= $this->usedmodel->get_used_space_percent();
			if($use_per > 100){
				$callback				= "";
				$popOptions['btn_title']	= '용량추가';
				$popOptions['btn_class']	= 'btn large cyanblue';
				$popOptions['btn_action']	= "window.open('http://firstmall.kr/myshop','_blank')";
				openDialogAlert("용량이 초과되어 상품을 등록 또는 수정할 수 없습니다.<br/>용량 추가를 하시길 바랍니다.",400,175,'parent',$callback,$popOptions);
				exit;
			}
		}


		###
		$config['upload_path']		= $path = ROOTPATH."/data/tmp/";
		$config['overwrite']			= TRUE;
		$this->load->library('Upload');
		if (is_uploaded_file($_FILES['excel_file']['tmp_name'])) {
			$file_ext = end(explode('.', $_FILES['excel_file']['name']));//확장자추출
			$config['allowed_types']	= 'xls';
			$config['file_name']			= 'goods_upload.'.$file_ext;
			$this->upload->initialize($config);
			if ($this->upload->do_upload('excel_file')) {
				$file_nm = $config['upload_path'].$config['file_name'];
				@chmod("{$file_nm}", 0777);
			}else{
				$callback = "";
				openDialogAlert("xls 파일만 가능합니다.",400,160,'parent',$callback);
				exit;
			}
		}else{
			$callback = "";
			openDialogAlert("파일을 등록해 주세요.",400,160,'parent',$callback);
			exit;
		}
		$this->load->model('excelgoodsmodel');
		$result = $this->excelgoodsmodel->excel_upload($file_nm);
		$callback = "parent.location.reload();";
		openDialogAlert($result['msg'],600,150,'parent',$callback);
		exit;
	}

	public function goods_status_image_upload(){
		$file_ext = end(explode('.', $_FILES['goodsStatusImage']['name']));//확장자추출

		$data = code_load('goodsStatusImage',$_POST['goodsStatusImageCode']);

		$config['upload_path'] = "data/icon/goods_status/";
		$config['allowed_types'] = 'gif|png|jpg';
		$config['overwrite'] = true;
		$config['max_size']	= $this->config_system['uploadLimit'];
		$config['file_name'] = $data[0]['value'] ? $data[0]['value'] : $_POST['goodsStatusImageCode'].".".$file_ext;

		$this->load->library('upload', $config);
		if ( ! $this->upload->do_upload('goodsStatusImage') ) {
			$err = $this->upload->display_errors();
		}else{
			$fileInfo = $this->upload->data();
			code_save('goodsStatusImage',array($_POST['goodsStatusImageCode']=>$config['file_name']));
			$callback = "parent.closeDialog('popGoodsStatusImageChoice');parent.$('#goodsStatusImage').click();";
			openDialogAlert("설정이 저장 되었습니다.",400,160,'parent',$callback);
		}
	}

	public function restock_notify_delete(){
		$seq_arr = $_GET['restock_notify_seq'];
		foreach($seq_arr as $k){
			$result	= $this->goodsmodel->delete_restock_notify($k);
		}
		echo $result;
	}

	public function restock_notify_send_sms(){
		### Validation
		if($_POST['send_num'] < 1){
			$callback = "";
			openDialogAlert('받는사람이 없습니다.',400,160,'parent',$callback);
			exit;
		}
		$this->validation->set_rules('send_message', '내용','trim|required|xss_clean');
		$this->validation->set_rules('send_sms', '보내는사람','trim|required|max_length[50]|xss_clean');
		if($this->validation->exec()===false){
			$err = $this->validation->error_array;
			$callback = "if(parent.document.getElementsByName('{$err['key']}')[0]) parent.document.getElementsByName('{$err['key']}')[0].focus();";
			openDialogAlert($err['value'],400,160,'parent',$callback);
			exit;
		}

		###
		$arrRestockNotifySeq = array();
		unset($phoneNo[0]);
		$key = get_shop_key();

		if(isset($_POST['add_num_chk'])!='Y'){
			switch($_POST['member']){
				case "all":
					$query = $this->db->query("select restock_notify_seq from fm_goods_restock_notify where notify_status = 'none' and cellphone<>''");
					$data = $query->result_array();
					if(count($data)>0){
						foreach($data as $k){
							array_push($arrRestockNotifySeq,$k['restock_notify_seq']);
						}
					}
					break;
				case "search":
					//echo urldecode($_POST["serialize"]);
					// serialize 로 넘어온 checkbox 값들 배열 처리 2015-06-15 jhr
					$tempArr = explode("&",urldecode($_POST["serialize"]));
					foreach($tempArr as $k){
						$tmp = explode("=",$k);
						if($tmp[1]){
							if( strpos($tmp[0],'[]') !== FALSE ){
								$sc[str_replace('[]','',$tmp[0])][] = $tmp[1];
							}else{
								$sc[$tmp[0]] = $tmp[1];
							}
						}
					}
					$sc['sms'] = 'y';
					$this->load->model('goodsmodel');
					$data = $this->goodsmodel->restock_notify_list($sc);

					if(count($data['record'])>0){
						foreach($data['record'] as $k){
							array_push($arrRestockNotifySeq,$k['restock_notify_seq']);
						}
					}
					break;
				case "select":
					$tempArr = explode(",", $_POST["serialize"]);
					unset($tempArr[0]);
					foreach($tempArr as $k){
						array_push($arrRestockNotifySeq,$k);
					}
					break;
				case "excel":
					break;
			}
		}

		###
		require_once $_SERVER['DOCUMENT_ROOT']."/app/libraries/sms.class.php";

		$auth = config_load('master');

		$sms_id = $this->config_system['service']['sms_id'];
		$sms_api_key = $auth['sms_auth'];

		$gabiaSmsApi = new gabiaSmsApi($sms_id,$sms_api_key);
		$from_sms	= ereg_replace("[^0-9]", "", $_POST['send_sms']);

		$targetList = array();
		$sms_send->from		= $from_sms;
		$query = $this->db->query("
			select
			a.restock_notify_seq,
			AES_DECRYPT(UNHEX(a.cellphone), '{$key}') as cellphone,
			b.goods_seq,
			b.goods_name,
			c.title1,
			c.option1,
			c.title2,
			c.option2,
			c.title3,
			c.option3,
			c.title4,
			c.option4,
			c.title5,
			c.option5
			from fm_goods_restock_notify as a
			inner join fm_goods as b on a.goods_seq = b.goods_seq
			left join fm_goods_restock_option as c on a.restock_notify_seq = c.restock_notify_seq
			where a.restock_notify_seq in ('".implode("','",$arrRestockNotifySeq)."') and a.notify_status='none'
		");

		foreach($query->result_array() as $v){
			$targetList[$v['goods_seq']]['phone'][] = $v['cellphone'];
			$targetList[$v['goods_seq']]['restock_notify_seq'][] = $v['restock_notify_seq'];
			$targetList[$v['goods_seq']]['goods_name'] = $v['goods_name'];
			$temp = "";
			if($v['option1'] && $v['title1']){
				$temp = $v['title1'].":".$v['option1']." ";
				if($v['option2'] && $v['title2']){
					$temp .= $v['title2'].":".$v['option2']." ";
				}
				if($v['option3'] && $v['title3']){
					$temp .= $v['title3'].":".$v['option3']." ";
				}
				if($v['option4'] && $v['title4']){
					$temp .= $v['title4'].":".$v['option4']." ";
				}
				if($v['option5'] && $v['title5']){
					$temp .= $v['title5'].":".$v['option5']." ";
				}
			}
			$targetList[$v['goods_seq']]['option'][] = $temp;
		}

		if($targetList){
			$smsCnt=0;
			foreach($targetList as $goods_seq=>$v){
				$x = 0;
				$dataTo		= array();
				foreach($v['phone'] as $cnt=>$cellphone){
					$dataTo = $cellphone;
					$send_message = $_POST["send_message"];
					$send_message = str_replace("{상품고유값}",$goods_seq,$send_message);
					$send_message = str_replace("{상품명}",strip_tags($v['goods_name']),$send_message);
					$send_message = str_replace("{옵션}",strip_tags($v['option'][$x++]),$send_message);

					###
					$str = trim($send_message);

					$phoneNo[] = $dataTo;
					$msg[] = $str;

					$this->db->query("
						update fm_goods_restock_notify set notify_status='complete', notify_date=now() where restock_notify_seq in ('".implode("','",$v['restock_notify_seq'])."')
					");

					$smsCnt++;
				}
			}

			$params['msg'] = $msg;
			$commonSmsData['restock']['phone'] = $phoneNo;;
			$commonSmsData['restock']['params'] = $params;

			$result = commonSendSMS($commonSmsData);

			$msg = $smsCnt."건 발송에 성공하였습니다.";
		}else{
				$msg	= "재입고알림을 통보할 고객이 없습니다.";
		}

		###
		//$msg	= $sms_send->msg;
		$callback = "parent.document.location.reload();";
		openDialogAlert($msg,400,160,'parent',$callback);
		exit;
	}

	// 실제 주문을 검색하여 출고예약량을 업데이트합니다.
	public function all_modify_reservation($goods_seq=null)
	{
		set_time_limit(0);
		$this->load->model('ordermodel');

		$query = "select count(*) cnt from fm_order where step >= 15";
		$query = $this->db->query($query);
		$data = $query->row_array();
		if( $data['cnt'] == 0 && !$goods_seq ){
			$query = "update fm_goods_supply set reservation15=0,reservation25=0";
			$this->db->query($query);
		}else{

			$query = "update fm_goods_supply set reservation15 = 0,reservation25 = 0";
			if($goods_seq){
				$query .= " where goods_seq='$goods_seq'";
			}
			$this->db->query($query);

			$query = "update fm_goods_supply s,fm_goods g,fm_goods_option o set  s.reservation15 = (
						select
							sum(io.ea) ea from fm_order_item i,fm_order_item_option io
						where
							i.goods_seq=g.goods_seq
							and i.item_seq=io.item_seq
							and io.step >= 15
							and io.step <= 45
							and io.option1=o.option1
							and io.option2=o.option2
							and io.option3=o.option3
							and io.option4=o.option4
							and io.option5=o.option5
							)
			where g.goods_seq = o.goods_seq and o.option_seq=s.option_seq";
			if($goods_seq){
				$query .= " and g.goods_seq='$goods_seq'";
			}
			$this->db->query($query);


			$query = "update  fm_goods_supply s,fm_goods g,fm_goods_option o set  s.reservation25 = (
						select
							sum(io.ea) ea from fm_order_item i,fm_order_item_option io
						where
							i.goods_seq=g.goods_seq
							and i.item_seq=io.item_seq
							and io.step >= 25
							and io.step <= 45
							and io.option1=o.option1
							and io.option2=o.option2
							and io.option3=o.option3
							and io.option4=o.option4
							and io.option5=o.option5
							)
			where g.goods_seq = o.goods_seq and o.option_seq=s.option_seq";
			if($goods_seq){
				$query .= " and g.goods_seq='$goods_seq'";
			}
			$this->db->query($query);

			$query = "update  fm_goods_supply s,fm_goods g,fm_goods_suboption o set  s.reservation15 = (
						select
							sum(io.ea) ea from fm_order_item i,fm_order_item_suboption io
						where
							i.goods_seq=g.goods_seq
							and i.item_seq=io.item_seq
							and io.step >= 15
							and io.step <= 45
							and io.title = o.suboption_title
							and io.suboption = o.suboption
							)
			where g.goods_seq = o.goods_seq and o.suboption_seq=s.suboption_seq";
			if($goods_seq){
				$query .= " and g.goods_seq='$goods_seq'";
			}
			$this->db->query($query);

			$query = "update  fm_goods_supply s,fm_goods g,fm_goods_suboption o set  s.reservation25 = (
						select
							sum(io.ea) ea from fm_order_item i,fm_order_item_suboption io
						where
							i.goods_seq=g.goods_seq
							and i.item_seq=io.item_seq
							and io.step >= 25
							and io.step <= 45
							and io.title = o.suboption_title
							and io.suboption = o.suboption
							)
			where g.goods_seq = o.goods_seq and o.suboption_seq=s.suboption_seq";
			if($goods_seq){
				$query .= " and g.goods_seq='$goods_seq'";
			}
			$this->db->query($query);

			$query = "update fm_goods_supply set reservation15 = 0 where reservation15 is null";
			$this->db->query($query);

			$query = "update fm_goods_supply set reservation25 = 0 where reservation25 is null";
			$this->db->query($query);
		}

		config_save('reservation',array('update_date'=>date('Y-m-d H:i:s')));
		echo "OK";
	}

	/* 재고조정하기 */
	public function stock_modify(){

		$this->load->model('stockmodel');

		$process_ea_sum = array_sum($_POST['stock_ea']);

		if(!$process_ea_sum){
			$callback = "";
			if($_POST['reason']=='input'){
				openDialogAlert("입고 수량을 입력해주세요.",400,160,'parent',$callback);
				exit;
			}
			elseif(!$_POST['supply_price_replace']){
				openDialogAlert("조정 수량을 입력해주세요.",400,160,'parent',$callback);
				exit;
			}
		}

		/* 옵션 매입가격, 재고수량 조정 */
		if($_POST['mode']=='optionStockEdit'){

			foreach($_POST['stock_ea'] as $k=>$v){
				$opts = array();
				foreach($_POST['stock_opt'] as $k2=>$v2) $opts[] = $v2[$k];

				/* 보정수량(+/-) */
				$adjust_ea = $_POST['reason']=='input' ? $_POST['stock_ea'][$k] : -$_POST['stock_ea'][$k];

				$this->stockmodel->option_modify(
					$_POST['goods_seq'],
					$opts,
					$_POST['stock_supply_price'][$k],
					$adjust_ea,
					$_POST['reason'],
					$_POST['supply_price_replace']
				);

			}
		}

		/* 서브 매입가격, 재고수량 조정 */
		if($_POST['mode']=='subOptionStockEdit'){

			foreach($_POST['stock_ea'] as $k=>$v){
				$opts = array();
				foreach($_POST['stock_opt'] as $k2=>$v2) $opts[] = $v2[$k];

				/* 보정수량(+/-) */
				$adjust_ea = $_POST['reason']=='input' ? $_POST['stock_ea'][$k] : -$_POST['stock_ea'][$k];

				$this->stockmodel->suboption_modify(
					$_POST['goods_seq'],
					$opts,
					$_POST['stock_supply_price'][$k],
					$adjust_ea,
					$_POST['reason'],
					$_POST['supply_price_replace']
				);

			}
		}

		/* 재고조정 히스토리 저장 */
		$data = array();
		$data['reason'] = $_POST['reason'];
		if($_POST['reason']=='input'){
			$data['supplier_seq'] = $_POST['supplier_seq'];
			$data['reason_detail'] = '';
			$data['stock_date'] = $_POST['stock_date'];
		}else{
			$data['supplier_seq'] = '';
			$data['reason_detail'] = $_POST['reason_detail'];
			$data['stock_date'] = date('Y-m-d');
		}
		$stock_code = $this->stockmodel->insert_stock_history($data);

		foreach($_POST['stock_ea'] as $k=>$v){

			if($v || ($_POST['reason']!='input' && $_POST['supply_price_replace'] && $_POST['stock_prev_supply_price'][$k]!=$_POST['stock_supply_price'][$k])){
				$data = array();
				$data['option_type'] = $_POST['mode']=='optionStockEdit' ? 'option' : 'suboption';
				$data['stock_code'] = $stock_code;
				$data['goods_seq'] = $_POST['goods_seq'];
				if($_POST['reason']!='input'){
					// 분실,오류,불량,기타
					$data['prev_supply_price'] = $_POST['stock_prev_supply_price'][$k];
				}
				$data['supply_price'] = $_POST['stock_supply_price'][$k];
				$data['ea'] = $_POST['stock_ea'][$k];

				foreach($_POST['stock_opt'] as $j=>$v2){
					if(!empty($_POST['stock_opt'][$j][$k])){
						$data['title'.($j+1)] = $_POST['stock_opt_title'][$j];
						$data['option'.($j+1)] = $_POST['stock_opt'][$j][$k];
					}
				}

				$this->stockmodel->insert_stock_history_item($data);
			}
		}

		$callback = "parent.document.location.reload();";
		openDialogAlert("재고 조정 완료",400,160,'parent',$callback);


	}

	/* 매입처 검색 */
	public function search_supplier(){
		$this->db->like("supplier_name",$_GET['keyword']);
		$this->db->limit(2);
		$query = $this->db->get("fm_supplier");
		$result = $query->result_array();

		echo json_encode($result);
	}

	/* 매입처 등록 */
	public function add_supplier(){

		$this->validation->set_rules('supplier_name', '매입처명','trim|required|max_length[16]|xss_clean');
		$this->validation->set_rules('supplier_bno', '사업자등록번호','trim|max_length[12]|xss_clean');
		if($this->validation->exec()===false){
			$err = $this->validation->error_array;
			$callback = "if(parent.document.getElementsByName('{$err['key']}')[0]) parent.document.getElementsByName('{$err['key']}')[0].focus();";
			openDialogAlert($err['value'],400,160,'parent',$callback);
			exit;
		}

		$data = array(
			'supplier_name' => $_POST['supplier_name'],
			'supplier_bno' => $_POST['supplier_bno'],
		);

		$query = $this->db->get_where("fm_supplier",$data);
		if($query->result_array()){
			$callback = "";
			openDialogAlert("동일한 매입처가 이미 등록되어있습니다.",400,160,'parent',$callback);
			exit;
		}

		$data['regist_date'] = date('Y-m-d H:i:s');
		$this->db->insert("fm_supplier",$data);

		$callback = "parent.closeDialog('registSupplierPopup');parent.$('#searchSupplierPopup form').submit();";
		openDialogAlert("매입처가 등록되었습니다.",400,160,'parent',$callback);

	}

	public function batch_goods_modify() {

		#### AUTH
		$auth = $this->authmodel->manager_limit_act('goods_act');
		if(!$auth){
			$callback = "";
			openDialogAlert($this->auth_msg,400,160,'parent',$callback);
			exit;
		}

		if(!preg_match("/^F_SH_/",$this->config_system['service']['hosting_code'])){
			$this->load->model('usedmodel');
			$use_per	= $this->usedmodel->get_used_space_percent();
			if($use_per > 100){
				$callback				= "";
				$popOptions['btn_title']	= '용량추가';
				$popOptions['btn_class']	= 'btn large cyanblue';
				$popOptions['btn_action']	= "window.open('http://firstmall.kr/myshop','_blank')";
				openDialogAlert("용량이 초과되어 상품을 등록 또는 수정할 수 없습니다.<br/>용량 추가를 하시길 바랍니다.",400,175,'parent',$callback,$popOptions);
				exit;
			}
		}
		$this->load->model('goodsHandlermodel');
		$result		= $this->goodsHandlermodel->doBatchUpdate($this->input->post());
		$mode		= $this->input->post('mode');

		if ($result['result'] === true) {
			if((in_array($mode, array("status", "ifstatus"))) && $result['message']){
				$this->goodsHandlermodel->_doModifyStatus($mode,$result['message']);
			}else{
				$msg		= "상품정보가 변경 되었습니다.";
				$callback	= "parent.location.reload();";
				openDialogAlert($msg,400,160,'parent',$callback);
			}
		} else {
			$callback	= "";
			$msg		= $result;
			openDialogAlert($msg,400,160,'parent',$callback);
		}

	}

	public function batch_modify()
	{
		#### AUTH
		$auth = $this->authmodel->manager_limit_act('goods_act');
		if(!$auth){
			$callback = "";
			openDialogAlert($this->auth_msg,400,160,'parent',$callback);
			exit;
		}

		if(!preg_match("/^F_SH_/",$this->config_system['service']['hosting_code'])){
			$this->load->model('usedmodel');
			$use_per	= $this->usedmodel->get_used_space_percent();
			if($use_per > 100){
				$callback				= "";
				$popOptions['btn_title']	= '용량추가';
				$popOptions['btn_class']	= 'btn large cyanblue';
				$popOptions['btn_action']	= "window.open('http://firstmall.kr/myshop','_blank')";
				openDialogAlert("용량이 초과되어 상품을 등록 또는 수정할 수 없습니다.<br/>용량 추가를 하시길 바랍니다.",400,175,'parent',$callback,$popOptions);
				exit;
			}
		}

		$_POST['goods_kind'] = array('goods','coupon');
		
		$mode  = $_POST['mode'];
		$this->{'_batch_modify_'.$mode}();
	}

	public function _batch_modify_price()
	{
		if(!$_POST['goods_seq']){
			$callback = "";
			$msg = "수정할 상품을 체크하세요!";
			openDialogAlert($msg,400,160,'parent',$callback);
			exit;
		}

		if	(!$this->scm_cfg['use'])	$this->scm_cfg	= config_load('scm');

		if(!$_POST['detail_commission_rate'])	$_POST['detail_commission_rate'] = array();
		if(!$_POST['detail_commission_type'])	$_POST['detail_commission_type'] = array();
		if(!$_POST['detail_supply_price'])		$_POST['detail_supply_price'] = array();
		if(!$_POST['detail_consumer_price'])	$_POST['detail_consumer_price'] = array();
		if(!$_POST['detail_price'])				$_POST['detail_price'] = array();
		if(!$_POST['detail_stock'])				$_POST['detail_stock'] = array();
		if(!$_POST['detail_reserve_rate'])		$_POST['detail_reserve_rate'] = array();
		if(!$_POST['detail_reserve'])			$_POST['detail_reserve'] = array();
		if(!$_POST['detail_reserve_unit'])		$_POST['detail_reserve_unit'] = array();
		if(!$_POST['detail_default_option'])	$_POST['detail_default_option'] = array();

		if(!$_POST['detail_shipping_policy'])	$_POST['detail_shipping_policy'] = array();
		if(!$_POST['detail_unlimit_shipping_price']) $_POST['detail_unlimit_shipping_price'] = array();
		if(!$_POST['detail_reserve_policy'])	$_POST['detail_reserve_policy'] = array();
		if(!$_POST['sale_seq'])					$_POST['sale_seq'] = array();

		$r_option['commission_rate']		= $_POST['commission_rate'];
		$r_option['commission_type']		= $_POST['commission_type'];
		$r_option['supply_price']			= $_POST['supply_price'];
		$r_option['consumer_price']			= $_POST['consumer_price'];
		$r_option['price']					= $_POST['price'];

		// 재고/재고연동/상태/노출/승인 업데이트 페이지에서 처리하므로 체크함.
		if (isset($_POST['stock'])) {
			$r_option['stock']				= $_POST['stock'];
		}

		$r_option['reserve_rate']			= $_POST['reserve_rate'];
		$r_option['reserve']				= $_POST['reserve'];
		$r_option['reserve_unit']			= $_POST['reserve_unit'];

		if($_POST['detail_commission_type']){
			if(!$_POST['supply_price']) $_POST['supply_price'] = array();
			$r_option['commission_rate']	= (array) $_POST['detail_commission_rate'] + $_POST['commission_rate'];
			$r_option['commission_type']	= (array) $_POST['detail_commission_type'] + $_POST['commission_type'];
			$r_option['supply_price']		= (array) $_POST['detail_supply_price'] + $_POST['supply_price'];
			$r_option['consumer_price']		= (array) $_POST['detail_consumer_price'] + $_POST['consumer_price'];
			$r_option['price']				= (array) $_POST['detail_price'] + $_POST['price'];

			if (isset($_POST['stock'])) {
				$r_option['stock']			= (array) $_POST['detail_stock'] + $_POST['stock'];
			}

			$r_option['reserve_rate']		= (array)  $_POST['detail_reserve_rate'] + $_POST['reserve_rate'];
			$r_option['reserve']			= (array) $_POST['detail_reserve'] + $_POST['reserve'] ;
			$r_option['reserve_unit']		= (array) $_POST['detail_reserve_unit'] + $_POST['reserve_unit'];
		}
		$r_goods['shipping_policy']			= (array) $_POST['detail_shipping_policy'] + $_POST['shipping_policy'];
		$r_goods['unlimit_shipping_price']	= (array) $_POST['detail_unlimit_shipping_price'] + $_POST['unlimit_shipping_price'];
		$r_goods['reserve_policy']			= (array) $_POST['detail_reserve_policy'] + $_POST['reserve_policy'];
		$r_goods['goods_status']			= $_POST['goods_status'];
		$r_goods['goods_view']				= $_POST['goods_view'];
		$r_goods['sale_seq']				= (array) $_POST['sale_seq'];
		$r_goods['provider_status']			= $_POST['provider_status'];
		$r_goods['provider_status_reason_type'] = '0';
		$r_goods['provider_status_reason']		= '';
		if	($_POST['provider_status'] != '1'){
			$r_goods['provider_status_reason_type'] = '2';
			$r_goods['provider_status_reason'] = '관리자 ' . $this->managerInfo['manager_id'] . '에 의해 일괄 미승인 처리되었습니다.';
		}
		$r_default_option							= $_POST['detail_default_option'];

		// 상품기본정보 일괄 수정
		foreach($r_goods['shipping_policy'] as $goods_seq => $shipping_policy){
			if(!in_array($goods_seq,$_POST['goods_seq'])) continue;

			$r_goods_update = array();
			$update_bind = array();
			$r_set_query = array();
			if($r_goods['unlimit_shipping_price'][$goods_seq]) {
				$r_goods_update['shipping_policy'] 			='goods';
				$r_goods_update['goods_shipping_policy'] 	='unlimit';
				$r_goods_update['unlimit_shipping_price'] 	= $r_goods['unlimit_shipping_price'][$goods_seq];
			}
			if($r_goods['reserve_policy'][$goods_seq])	$r_goods_update['reserve_policy'] = $r_goods['reserve_policy'][$goods_seq];
			if($r_goods['goods_view'][$goods_seq])		$r_goods_update['goods_view'] = $r_goods['goods_view'][$goods_seq];
			if($r_goods['goods_status'][$goods_seq])	$r_goods_update['goods_status'] = $r_goods['goods_status'][$goods_seq];
			if($r_goods['provider_status'][$goods_seq]!='') $r_goods_update['provider_status'] = $r_goods['provider_status'][$goods_seq];
			if($shipping_policy) $r_goods_update['shipping_policy'] = $shipping_policy;
			if($r_goods['sale_seq'][$goods_seq]) $r_goods_update['sale_seq'] = $r_goods['sale_seq'][$goods_seq];

			// 상품 업데이트일자 추가 leewh 2015-01-16
			$r_goods_update['update_date'] = date("Y-m-d H:i:s",time());

			foreach($r_goods_update as $update_field => $update_value){
				$r_set_query[] = "`".$update_field."`=?";
				$update_bind[] = $update_value;
			}
			$update_bind[] = $goods_seq;

			$query = "update fm_goods set ".implode(',',$r_set_query)." where goods_seq=?";
			$this->db->query($query,$update_bind);

		}

		foreach($r_default_option as $goods_seq => $option_seq){
			if(!in_array($goods_seq,$_POST['goods_seq'])) continue;
			$query = "update fm_goods_option set default_option='n' where goods_seq=?";
			$this->db->query($query,array($goods_seq));
			$query = "update fm_goods_option set default_option='y' where option_seq=?";
			$this->db->query($query,array($option_seq));
		}

		# 환율(KRW)
		$basic_amout	= code_load('currency_amout',"KRW");
		$exchange_rate	= $basic_amout[0]['value'];

		foreach($r_option['price'] as $option_seq => $price){

			$goods_seq = $_POST['option_seq'][$option_seq];
			if(!in_array($goods_seq,$_POST['goods_seq'])) continue;
			$price				= get_cutting_price($r_option['price'][$option_seq]);
			$supply_price		= $r_option['supply_price'][$option_seq];
			$consumer_price		= get_cutting_price($r_option['consumer_price'][$option_seq]);
			$reserve_rate		= get_cutting_price($r_option['reserve_rate'][$option_seq]);
			$reserve			= get_cutting_price($r_option['reserve'][$option_seq]);
			$reserve_unit		= $r_option['reserve_unit'][$option_seq];
			$commission_rate	= $r_option['commission_rate'][$option_seq];
			$commission_type	= $r_option['commission_type'][$option_seq];
			if($reserve_unit=='percent') {
				$reserve		= get_cutting_price($price * $reserve_rate / 100);
			}else{
				$reserve_rate	= $reserve;
			}

			if ($r_option['stock'][$option_seq]) $stock = $r_option['stock'][$option_seq];

			$query = "update fm_goods_option set commission_rate=?,commission_type=?,consumer_price=?,price=?,reserve_rate=?,reserve_unit=?,reserve=? where option_seq=?";
			$this->db->query($query,array($commission_rate,$commission_type,$consumer_price,$price,$reserve_rate,$reserve_unit,$reserve,$option_seq));

			if	($this->scm_cfg['use'] == 'Y'){
				if ($stock) {
					$query = "update fm_goods_supply a, fm_goods b "
							. "set a.supply_price=?, a.exchange_rate=?,a.stock=? "
							. "where a.option_seq=? and a.goods_seq = b.goods_seq and b.provider_seq > 1";
					$this->db->query($query,array($supply_price,$exchange_rate,$stock,$option_seq));
				} else {
					$query = "update fm_goods_supply a, fm_goods b "
							. "set a.supply_price=?, a.exchange_rate=?"
							. "where a.option_seq=? and a.goods_seq = b.goods_seq and b.provider_seq > 1";
					$this->db->query($query,array($supply_price,$exchange_rate,$option_seq));
				}
			}else{
				if ($stock) {
					$query = "update fm_goods_supply set supply_price=?,exchange_rate=?,stock=? where option_seq=?";
					$this->db->query($query,array($supply_price,$exchange_rate,$stock,$option_seq));
				} else {
					$query = "update fm_goods_supply set supply_price=?,exchange_rate=? where option_seq=?";
					$this->db->query($query,array($supply_price,$exchange_rate,$option_seq));
				}
			}
		}

		/* 총재고 수량 입력 */
		foreach($r_goods['shipping_policy'] as $goods_seq => $shipping_policy){
			$this->goodsmodel->total_stock($goods_seq);
			$this->goodsmodel->default_price($goods_seq);
		}

		// 할인혜택 금액 저장
		$this->load->model('goodssummarymodel');
		$this->goodssummarymodel->set_event_price(array('goods'=>$_POST['goods_seq']));

		// 다중판매처 관련 처리 추가
		$this->load->model('openmarketmodel');
		if	($_POST['goods_seq'])foreach($_POST['goods_seq'] as $g => $goods_seq){
			if	($goods_seq){
				$this->openmarketmodel->chg_price_to_option($goods_seq);
				$this->openmarketmodel->request_send_goods($goods_seq);
			}
		}

		$msg = "상품정보가 변경 되었습니다.";
		$callback = "parent.location.reload();";
		openDialogAlert($msg,400,160,'parent',$callback);
	}

	public function _batch_modify_ifprice()
	{
		if($_POST['modify_list'] == 'all'){
			$_GET = $_POST;
			$sc = $_GET;
			$this->goodsmodel->batch_mode = 1;
			$query = $this->goodsmodel->admin_goods_list($sc);
			$query = $this->db->query($query);
			foreach($query->result_array() as $data){
				$r_goods_seq[] = $data['goods_seq'];
			}
		}else{
			$r_goods_seq = $_POST['goods_seq'];
		}

		if(!$r_goods_seq){
			$callback = "";
			$msg = "수정할 상품이 없습니다!";
			openDialogAlert($msg,400,160,'parent',$callback);
			exit;
		}

		// 매입가 변경
		if( $_POST['batch_supply_price_yn'] == 1 ){

			$price	= get_cutting_price($_POST['batch_supply_price']);
			$unit	= $_POST['batch_supply_price_unit'];
			$mode	= $_POST['batch_supply_price_updown'];
			$field	= "supply_price";
			$table	= "fm_goods_supply";
			unset($cuttingsale);
			$cuttingsale['cutting_sale_yn']		= $_POST['batch_supply_price_cutting_sale_yn'];
			$cuttingsale['cutting_sale_price']	= $_POST['batch_supply_price_cutting_sale_price'];
			$cuttingsale['cutting_sale_action']	= $_POST['batch_supply_price_cutting_sale_action'];

			$r_query[$table][] = $this->_batch_modify_ifprice_set_query($price,$unit,$mode,$field, $cuttingsale);
			if($mode == 'down' ) {//인하인경우 인하가격이상인경우 조건추가
				$w_query[$table][$field]['price']		= $price;
				$w_query[$table][$field]['unit']		= $unit;
				$w_query[$table][$field]['mode']	= $mode;
				$w_query[$table][$field]['field']		= $field;
			}
		}

		// 소비자가 변경
		if( $_POST['consumer_price_yn'] == 1 ){

			$price	= get_cutting_price($_POST['batch_consumer_price']);
			$unit	= $_POST['batch_consumer_price_unit'];
			$mode	= $_POST['batch_consumer_price_updown'];
			$field	= "consumer_price";
			$table	= "fm_goods_option";
			unset($cuttingsale);
			$cuttingsale['cutting_sale_yn']		= $_POST['batch_consumer_price_cutting_sale_yn'];
			$cuttingsale['cutting_sale_price']	= $_POST['batch_consumer_price_cutting_sale_price'];
			$cuttingsale['cutting_sale_action']	= $_POST['batch_consumer_price_cutting_sale_action'];


			$r_query[$table][] = $this->_batch_modify_ifprice_set_query($price,$unit,$mode,$field, $cuttingsale);

			if($mode == 'down' ) {//인하인경우 인하가격이상인경우 조건추가
				$w_query[$table][$field]['price']		= $price;
				$w_query[$table][$field]['unit']		= $unit;
				$w_query[$table][$field]['mode']	= $mode;
				$w_query[$table][$field]['field']		= $field;
			}

		}

		// 판매가 변경
		if( $_POST['batch_price_yn'] == 1 ){

			$price	= get_cutting_price($_POST['batch_price']);
			$unit	= $_POST['batch_price_unit'];
			$mode	= $_POST['batch_price_updown'];
			$field	= "price";
			$table	= "fm_goods_option";

			unset($cuttingsale);
			$cuttingsale['cutting_sale_yn']		= $_POST['batch_price_cutting_sale_yn'];
			$cuttingsale['cutting_sale_price']	= $_POST['batch_price_cutting_sale_price'];
			$cuttingsale['cutting_sale_action']	= $_POST['batch_price_cutting_sale_action'];
			$r_query[$table][] = $this->_batch_modify_ifprice_set_query($price,$unit,$mode,$field, $cuttingsale);

			if($mode == 'down' ) {//인하인경우 인하가격이상인경우 조건추가
				$w_query[$table][$field]['price']		= $price;
				$w_query[$table][$field]['unit']		= $unit;
				$w_query[$table][$field]['mode']	= $mode;
				$w_query[$table][$field]['field']		= $field;
			}

		}

		// 재고변경
		if( $_POST['batch_stock_yn'] == 1 ){
			$price	= $_POST['batch_stock'];
			$unit	= 'won';
			$mode	= $_POST['batch_stock_updown'];
			$field	= "stock";
			$table	= "fm_goods_supply";
			$r_query[$table][] = $this->_batch_modify_ifprice_set_query($price,$unit,$mode,$field);

			if($mode == 'down' ) {//인하인경우 인하가격이상인경우 조건추가
				$w_query[$table][$field]['price']		= $price;
				$w_query[$table][$field]['unit']		= $unit;
				$w_query[$table][$field]['mode']	= $mode;
				$w_query[$table][$field]['field']		= $field;
			}
		}

		// 상품상태 변경
		if( $_POST['batch_goods_status_yn'] == 1 ){
			$field = "goods_status";
			$table = "fm_goods";
			$r_query[$table][] =  $field." = '".$_POST['batch_goods_status']."'";
		}

		// 적립금 변경
		if( $_POST['batch_reserve_yn'] == 1 ){
			$field = "reserve_policy";
			$table = "fm_goods";
			$r_query[$table][] =  $field." = '".$_POST['batch_reserve_policy']."'";

			if($_POST['batch_reserve_policy'] == 'goods'){
				$price	= get_cutting_price($_POST['batch_reserve']);
				$unit	= $_POST['batch_reserve_unit'];
				$table	= "fm_goods_option";
				if($unit == 'percent') $field = "reserve_rate";
				else $field = "reserve";
				$r_query[$table][] =  $field." = ".$price;
				$r_query[$table][] =  "reserve_unit = '".$unit."'";
			}
		}

		// 배송비 변경
		if( $_POST['batch_shipping_yn'] == 1 ){
			$field = "shipping_policy";
			$table = "fm_goods";
			$r_query[$table][] =  $field." = '".$_POST['batch_shipping_policy']."'";

			if($_POST['batch_shipping_policy'] == 'goods' && $_POST['batch_unlimit_shipping_price']!='' ){
				$r_query[$table][] =  "goods_shipping_policy = 'unlimit'";
				$r_query[$table][] =  "unlimit_shipping_price = '".$_POST['batch_unlimit_shipping_price']."'";
			}
		}

		// 상품노출 변경
		if( $_POST['batch_goods_view_yn'] == 1 ){
			$field = "goods_view";
			$table = "fm_goods";
			$r_query[$table][] =  $field." = '".$_POST['batch_goods_view']."'";
		}

		// 과세/비과세를 변경
		if( $_POST['batch_tax_yn'] == 1 ){
			$field = "tax";
			$table = "fm_goods";
			$r_query[$table][] =  $field." = '".$_POST['batch_tax']."'";
		}

		//등급혜택 변경
		if( $_POST['batch_price_grade_sale_yn'] == 1 ){
			$field = "sale_seq";
			$table = "fm_goods";
			$r_query[$table][] =  $field." = '".$_POST['batch_sale_seq']."'";
		}

		// 상품승인 변경
		if( $_POST['batch_provider_status_yn'] == 1 ){
			$r_query['fm_goods'][] =  "provider_status_reason_type = '0'";
			$r_query['fm_goods'][] =  "provider_status_reason = ''";
			if	($_POST['batch_provider_status'] != '1'){
				$provider_status_reason	= '관리자 ' . $this->managerInfo['manager_id'] . '에 의해 일괄 미승인 처리되었습니다.';
				$r_query['fm_goods'][]	=  "provider_status_reason_type = '2'";
				$r_query['fm_goods'][]	=  "provider_status_reason = '".$provider_status_reason."'";
			}
			$field = "provider_status";
			$table = "fm_goods";
			$r_query[$table][] =  $field." = '".$_POST['batch_provider_status']."'";
		}

		foreach($r_goods_seq as $goods_seq){
			foreach($r_query as $table => $set_str){
				unset($whereis);

				if($w_query[$table] && $mode == 'down'){
					foreach($w_query[$table] as $where_str) {
						unset($upgoods,$up_price);
						$this->db->limit(1,0);
						$this->db->where('goods_seq', $goods_seq);
						if($table == 'fm_goods_option' ){
							$this->db->where('default_option', 'y');//기본가추출
						}
						$upgoodsquery		= $this->db->get($table);
						$upgoods			= $upgoodsquery->result_array();
						$orgupgoodsprice	= $upgoods[0][$where_str['field']];
						if( $where_str['unit'] == 'percent' ) {
							$up_price = ($orgupgoodsprice*$where_str['field']/100);
						}else{
							$up_price = $where_str['price'];
						}
						$whereis .= " and ".$where_str['field']." >= ".$up_price;//0원 초기화위해 = 구문추가
					}
				}

				// 상품 업데이트일자 추가 leewh 2015-01-16
				if ($table=="fm_goods") array_push($set_str, sprintf("update_date = '%s'", date("Y-m-d H:i:s",time())));

				$query = "update ".$table." set ".implode(',',$set_str)." where goods_seq=?".$whereis;
				$this->db->query($query,array($goods_seq));
				//debug_var($this->db->last_query());exit;

				/* 총재고 수량 입력 */
				//$this->goodsmodel->total_stock($goods_seq);
			}
		}

		// 할인혜택 금액 저장
		$this->load->model('goodssummarymodel');
		$this->goodssummarymodel->set_event_price(array('goods'=>$r_goods_seq));

		// 다중판매처 관련 처리 추가
		$this->load->model('openmarketmodel');
		if	($r_goods_seq)foreach($r_goods_seq as $goods_seq){
			if	($goods_seq){
				$this->openmarketmodel->chg_price_to_option($goods_seq);
				$this->openmarketmodel->request_send_goods($goods_seq);
			}
		}

		$msg = "상품정보가 변경 되었습니다.";
		$callback = "parent.location.reload();";
		openDialogAlert($msg,400,160,'parent',$callback);
	}

	public function _batch_modify_ifprice_set_query($price,$unit,$mode,$field, $cuttingsale=null)
	{

		$price		= get_cutting_price($price);
		$value_str	= $field .( $mode=='up' ? "+" : "-" ) ;

		if($cuttingsale && $cuttingsale['cutting_sale_yn'] == 1 ){//절삭 사용시
			$cutting_sale_price			=  $cuttingsale['cutting_sale_price'];//0.01,0.1,1,10, 100, 1000
			if($cutting_sale_price < 1){
				$cutting_sale_price_len		= strlen(str_replace("0.","",$cutting_sale_price));
			}else{
				$cutting_sale_price_len		= (strlen($cutting_sale_price)-1) * -1;//10, 100, 1000
			}
			$cutting_sale_action		= $cuttingsale['cutting_sale_action'];//rounding(반올림), ascending(올림), dscending(내림)

			if($unit == 'percent') $value_str .= "(".$field." * ".$price." / 100)";
			else $value_str .= $price;

			//ROUND(숫자,자릿수)  반올림
			//CEILING(숫자) = 값보다 큰 정수 중 가장 작은 수  올림
			//TRUNCATE(숫자,자릿수)  내림, 버림
			if( $cutting_sale_action == 'dscending' ) {//내림
				$value_str = " TRUNCATE((".$value_str."),".$cutting_sale_price_len.")";
			}elseif( $cutting_sale_action == 'rounding' ){//반올림
				$value_str = " ROUND((".$value_str."),".$cutting_sale_price_len.")";
			}elseif( $cutting_sale_action == 'ascending' ){//올림
				$value_str = " CEILING( ((".$value_str.")/".$cutting_sale_price."))*".$cutting_sale_price."";
			}
		}else{
			if($unit == 'percent') $value_str .= "(".$field." * ".$price." / 100)";
			else $value_str .= $price;
		}
		return $field." = ".$value_str;

	}

	public function _batch_modify_stock()
	{
		if(!$_POST['goods_seq']){
			$callback = "";
			$msg = "수정할 상품을 체크하세요!";
			openDialogAlert($msg,400,160,'parent',$callback);
			exit;
		}

		if	(!$this->scm_cfg)	$this->scm_cfg	= config_load('scm');

		// 상점 기본 재고정책
		$cfg_order = config_load('order');

		if(!$_POST['detail_stock']) $_POST['detail_stock'] = array();
		if(!$_POST['detail_default_option']) $_POST['detail_default_option'] = array();

		$r_goods['goods_seq']			= $_POST['goods_seq'];
		$r_goods['runout_type']			= $_POST['runout_type'];
		$r_goods['runout_policy']		= $_POST['runout_policy'];
		$r_goods['able_stock_limit']	= $_POST['able_stock_limit'];
		$r_option['stock']					= $_POST['stock'];

		if($_POST['detail_stock']){
			$r_option['stock']					= (array) $_POST['detail_stock'] + $_POST['stock'];
		}

		$r_goods['goods_status'] = $_POST['goods_status'];
		$r_goods['goods_view'] = $_POST['goods_view'];
		$r_goods['provider_status'] = $_POST['provider_status'];
		$r_goods['provider_status_reason_type'] = '0';
		$r_goods['provider_status_reason']		= '';
		if	($_POST['provider_status'] != '1'){
			$r_goods['provider_status_reason_type'] = '2';
			$r_goods['provider_status_reason'] = '관리자 ' . $this->managerInfo['manager_id'] . '에 의해 일괄 미승인 처리되었습니다.';
		}
		$r_default_option							= $_POST['detail_default_option'];

		foreach($r_default_option as $goods_seq => $option_seq){
			if(!in_array($goods_seq,$_POST['goods_seq'])) continue;
			$query = "update fm_goods_option set default_option='n' where goods_seq=?";
			$this->db->query($query,array($goods_seq));
			$query = "update fm_goods_option set default_option='y' where option_seq=?";
			$this->db->query($query,array($option_seq));
		}

		foreach($r_option['stock'] as $option_seq => $stock){
			$goods_seq = $_POST['option_seq'][$option_seq];
			if(!in_array($goods_seq,$_POST['goods_seq'])) continue;
			$stock = $r_option['stock'][$option_seq];

			if	($this->scm_cfg['use'] == 'Y'){
				$query	= "update fm_goods_supply a, fm_goods b "
						. "set a.stock=? "
						. "where a.option_seq=? and a.goods_seq = b.goods_seq and b.provider_seq > 1 ";
			}else{
				$query = "update fm_goods_supply set stock=? where option_seq=?";
			}
			$this->db->query($query,array($stock,$option_seq));
		}

		// 상품기본정보 일괄 수정
		$return_info_arr = array();
		foreach($r_goods['runout_type'] as $goods_seq => $runout_type) {
			if(!in_array($goods_seq,$_POST['goods_seq'])) continue;

			$r_goods_update = array();
			$update_bind = array();
			$r_set_query = array();

			// 노출
			if($r_goods['goods_view'][$goods_seq]) $r_goods_update['goods_view'] = $r_goods['goods_view'][$goods_seq];
			// 승인
			if($r_goods['provider_status'][$goods_seq]!='') $r_goods_update['provider_status'] = $r_goods['provider_status'][$goods_seq];

			// 개별 정책
			if ($runout_type=='goods') {
				if ($r_goods['runout_policy'][$goods_seq]) {
					$runout = $r_goods['runout_policy'][$goods_seq];
					$r_goods_update['runout_policy'] = $r_goods['runout_policy'][$goods_seq];
				}

				$ableStockLimit = 0;
				if ($r_goods['runout_policy'][$goods_seq]=="ableStock") {
					$ableStockLimit = $r_goods['able_stock_limit'][$goods_seq]+1;
					$r_goods_update['able_stock_limit'] = $r_goods['able_stock_limit'][$goods_seq];
				}
			} else {
				$r_goods_update['runout_policy'] = '';
				$r_goods_update['able_stock_limit'] = 0;

				$runout = $cfg_order['runout'];
				$ableStockLimit = 0;

				if ($cfg_order['runout']=='ableStock') {
					$ableStockLimit = $cfg_order['ableStockLimit']+1;
				}
			}

			if($r_goods['goods_status'][$goods_seq]) {
				if($r_goods['goods_status'][$goods_seq] == "normal_runout") {
					// 정상 또는 품절 자동 계산
					$result_info_arr = $this->get_option_goods_status($goods_seq,$runout,$ableStockLimit,$cfg_order['ableStockStep']);

					// 정상에서 품절로 변경되는 상품명
					if ($result_info_arr['runout_gname']) {
						$return_info_arr['runout_gname'][] = $result_info_arr['runout_gname'];
					}

					// 품절에서 정상으로 변경되는 상품명
					if ($result_info_arr['normal_gname']) {
						$return_info_arr['normal_gname'][] = $result_info_arr['normal_gname'];
					}

					// 변경되는 상품 상태
					$r_goods_update['goods_status'] = $result_info_arr['goods_status'];

				} else {
					// 변경하는 재고와 상관없이 상태값 그대로 저장
					$r_goods_update['goods_status'] = $r_goods['goods_status'][$goods_seq];
				}
			}

			// 업데이트할 최종 재고 입력
			$get_tot_option = $this->goodsmodel->get_tot_option($goods_seq);
			$r_goods_update['tot_stock'] = $get_tot_option['stock'];
			$r_goods_update['update_date'] = date("Y-m-d H:i:s",time());

			foreach($r_goods_update as $update_field => $update_value){
				$r_set_query[] = "`".$update_field."`=?";
				$update_bind[] = $update_value;
			}
			$update_bind[] = $goods_seq;

			$query = "update fm_goods set ".implode(',',$r_set_query)." where goods_seq=?";
			$this->db->query($query,$update_bind);
		}

		// 할인혜택 금액 저장
		$this->load->model('goodssummarymodel');
		$this->goodssummarymodel->set_event_price(array('goods'=>$_POST['goods_seq']));

		if ($return_info_arr) {
			$tot_cnt = 0;
			$first_gname = "";
			$gname_table = "";
			$out_gname_table = "";

			$common_table = "<table class=\'info_stock_status_table\' align=\'center\'><thead><tr><th>고유값</th><th>상품명</th></tr></thead>%s</table>";

			// 품절 => 정상
			if (is_array($return_info_arr['normal_gname'])) {
				$tot_cnt += count($return_info_arr['normal_gname']);

				$cre_tr = "";
				foreach ($return_info_arr['normal_gname'] as $key =>$row) {
					if ($key==0) $first_gname = $row['goods_name'];
					$cre_tr .= sprintf("<tr><td>%s</td><td>%s</td></tr>",$row['goods_seq'],addslashes($row['goods_name']));
				}
				$gname_table = sprintf($common_table,$cre_tr);
			}

			// 정상 => 품절
			if (is_array($return_info_arr['runout_gname'])) {
				$tot_cnt += count($return_info_arr['runout_gname']);

				$cre_tr_runout = "";
				foreach ($return_info_arr['runout_gname'] as $key =>$row) {
					if ($key==0 && $first_gname=="") $first_gname = $row['goods_name'];
					$cre_tr_runout .= sprintf("<tr><td>%s</td><td>%s</td></tr>",$row['goods_seq'],addslashes($row['goods_name']));
				}
				$out_gname_table = sprintf($common_table,$cre_tr_runout);
			}

			$msg_cnt = ($tot_cnt>1) ? "외 ".($tot_cnt-1) : "1";
			$msg_str = "상품은 재고정책에 따라 ‘정상’ 에서 ‘품절’ 또는 ‘품절’ 에서 ‘정상’ 으로 변경이 되었습니다. 자세한 변경상품은 아래 버튼을 클릭하여 확인하실 수 있습니다.";
			$msg_show = sprintf("‘%s’ %s개의 %s",$first_gname,$msg_cnt,$msg_str);

			$result_json = array();
			$result_json['msg_show'] = $msg_show;
			$result_json['gname'] = ($gname_table) ? 1 : '';
			$result_json['out_gname'] = ($out_gname_table) ? 1 : '';
			$str_result_json = addslashes(json_encode($result_json));

			echo("<script>
				parent.popup_stock_modify_msg('".$str_result_json."');
				parent.set_table_dialog('dialog_normal_table', '".$gname_table."');
				parent.set_table_dialog('dialog_runout_table', '".$out_gname_table."');
			</script>");
			exit;

		} else {
			$msg = "상품정보가 변경 되었습니다.";
			$callback = "parent.location.reload();";
			openDialogAlert($msg,400,160,'parent',$callback);
		}

	}


	public function _batch_modify_goods()
	{

		if(!$_POST['goods_seq']){
			$callback = "";
			$msg = "수정할 상품을 체크하세요!";
			openDialogAlert($msg,400,160,'parent',$callback);
			exit;
		}

		$r_fieldname = array('goods_name','summary','info_select_seq');
		foreach($_POST['goods_name'] as $goods_seq => $goods_name){
			if(!in_array($goods_seq,$_POST['goods_seq'])) continue;

			// 검색어 추출을 위한 추가 검색단어 가져오기 @2016-04-27
			$gdquery = "select goods_seq, goods_code, goods_name, summary, keyword from fm_goods where goods_seq=? limit 1";
			$gdquery = $this->db->query($gdquery,array($goods_seq));
			$gdresult		= $gdquery->result_array();
			$old_goods		= $gdresult[0];
			$result_old_keyword = $this->goodsmodel->set_search_keyword($old_goods['goods_seq'],$old_goods['goods_code'],$old_goods['goods_name'],$old_goods['summary'],$old_goods['keyword']);

			$r_field = array();
			$r_value = array();
			foreach($r_fieldname as $fieldname){
				if( $fieldname == 'goods_name' || $fieldname == 'summary' ) {//상품명/간략설명
					$r_value[] = trim($_POST[$fieldname][$goods_seq]);
					$old_goods[$fieldname] = trim($_POST[$fieldname][$goods_seq]);
				}else{
					$r_value[] = str_replace('"',htmlspecialchars('"', ENT_QUOTES),$_POST[$fieldname][$goods_seq]);
				}

				if($fieldname == 'info_select') { // 공용정보
					$info_seq = $_POST[$fieldname][$goods_seq];
					if ($info_seq != "") {
						$info = get_data("fm_goods_info",array("info_seq"=>$info_seq));
						$common_contents = $info ? $info[0]['info_value'] : '';
					} else {
						$common_contents = "";
					}
					$r_field[] = "info_seq=?";

					// 공용번호에 따른 공용정보 업데이트 추가 2015-06-25 leewh
					$r_field[] = "common_contents=?";
					$r_value[] = $common_contents;
				} else {
					$r_field[] = $fieldname."=?";
				}
			}

			// 상품 업데이트일자 추가 leewh 2015-01-16
			$r_field[] = "update_date=?";
			$r_value[] = date("Y-m-d H:i:s",time());

			$r_value[] = $goods_seq;
			$query = "update fm_goods set ".implode(',',$r_field)." where goods_seq=?";
			$this->db->query($query,$r_value);

			// 검색어 추출
			$arr_tmp_keyword = array();
			$result_keyword = $this->goodsmodel->set_search_keyword($goods_seq,$old_goods['goods_code'],$old_goods['goods_name'],$old_goods['summary'],$result_old_keyword['keyword']);
			if( $result_keyword['keyword']){
				$arr_tmp_keyword[] = $result_keyword['keyword'];
			}
			if($result_keyword['auto_keyword']){
				$arr_tmp_keyword[] = $result_keyword['auto_keyword'];
			}
			$keyword = implode(',',$arr_tmp_keyword);

			// 상품테이블에 검색어 저장
			$this->goodsmodel->update_keyword($goods_seq,$keyword);

		}//endforeach

		$msg = "상품정보가 변경 되었습니다.";
		$callback = "parent.location.reload();";
		openDialogAlert($msg,400,160,'parent',$callback);
	}


	//상품명/간략설명/공용정보/관련정보 조건업데이트
	public function _batch_modify_ifgoods()
	{
		if($_POST['modify_list'] == 'all'){
			$_GET = $_POST;
			$sc = $_GET;
			$this->goodsmodel->batch_mode = 1;
			$query = $this->goodsmodel->admin_goods_list($sc);
			$query = $this->db->query($query);
			foreach($query->result_array() as $data){
				$r_goods_seq[] = $data['goods_seq'];
			}
		}else{
			$r_goods_seq = $_POST['goods_seq'];
		}

		if(!$r_goods_seq){
			$callback = "";
			$msg = "수정할 상품이 없습니다!";
			openDialogAlert($msg,400,160,'parent',$callback);
			exit;
		}

		$r_fieldname = array('goods_name','summary','info_select_seq');
		foreach($r_goods_seq as $goods_seq) {

			// 검색어 추출을 위한 추가 검색단어 가져오기 @2016-04-27
			$gdquery = "select goods_seq, goods_code, goods_name, summary, keyword from fm_goods where goods_seq=? limit 1";
			$gdquery = $this->db->query($gdquery,array($goods_seq));
			$gdresult		= $gdquery->result_array();
			$old_goods		= $gdresult[0];
			$result_old_keyword = $this->goodsmodel->set_search_keyword($old_goods['goods_seq'],$old_goods['goods_code'],$old_goods['goods_name'],$old_goods['summary'],$old_goods['keyword']);

			$r_field = array();
			$r_value = array();
			foreach($r_fieldname as $fieldname){
				if( $_POST['batch_'.$fieldname.'_yn'] == 1 || ($_POST['batch_relation_yn'] == 1 && strstr($fieldname,"relation")) ){
					if( $fieldname == 'goods_name' || $fieldname == 'summary' ) {//상품명/간략설명
						$r_value[] = trim($_POST['batch_'.$fieldname]);
						$old_goods[$fieldname] = trim($_POST['batch_'.$fieldname]);
					}else{
						$r_value[] = str_replace('"',htmlspecialchars('"', ENT_QUOTES),$_POST['batch_'.$fieldname]);
					}

					if($fieldname == 'info_select'){ // 공용정보
						$info_seq = $_POST['batch_'.$fieldname];
						if ($info_seq != "") {
							$info = get_data("fm_goods_info",array("info_seq"=>$info_seq));
							$common_contents = $info ? $info[0]['info_value'] : '';
						} else {
							$common_contents = "";
						}
						$r_field[] = "info_seq=?";

						// 공용번호에 따른 공용정보 업데이트 추가 2015-06-25 leewh
						$r_field[] = "common_contents=?";
						$r_value[] = $common_contents;
					} else {
						$r_field[] = $fieldname."=?";
					}
				}
			}

			// 상품 업데이트일자 추가 leewh 2015-01-16
			$r_field[] = "update_date=?";
			$r_value[] = date("Y-m-d H:i:s",time());

			$r_value[] = $goods_seq;
			$query = "update fm_goods set ".implode(',',$r_field)." where goods_seq=?";
			$this->db->query($query,$r_value);

			// 검색어 추출
			$arr_tmp_keyword = array();
			$result_keyword = $this->goodsmodel->set_search_keyword($goods_seq,$old_goods['goods_code'],$old_goods['goods_name'],$old_goods['summary'],$result_old_keyword['keyword']);
			if( $result_keyword['keyword']){
				$arr_tmp_keyword[] = $result_keyword['keyword'];
			}
			if($result_keyword['auto_keyword']){
				$arr_tmp_keyword[] = $result_keyword['auto_keyword'];
			}
			$keyword = implode(',',$arr_tmp_keyword);

			// 상품테이블에 검색어 저장
			$this->goodsmodel->update_keyword($goods_seq,$keyword);

		}//endforeach

		$msg = "상품정보가 변경 되었습니다.";
		$callback = "parent.location.reload();";
		openDialogAlert($msg,400,160,'parent',$callback);
	}

	//상품코드 자동생성/청약철회 제한상품 업데이트
	public function _batch_modify_ifgoodscode()
	{
		if($_POST['modify_list'] == 'all'){
			$_GET = $_POST;
			$sc = $_GET;
			$this->goodsmodel->batch_mode = 1;
			$query = $this->goodsmodel->admin_goods_list($sc);
			$query = $this->db->query($query);
			foreach($query->result_array() as $data){
				$r_goods_seq[] = $data['goods_seq'];
			}
		}else{
			$r_goods_seq = $_POST['goods_seq'];
		}

		if(!$r_goods_seq){
			$callback = "";
			$msg = "수정할 상품이 없습니다!";
			openDialogAlert($msg,400,160,'parent',$callback);
			exit;
		}


		if(!$_POST['batch_cancel_type_yn'] && !$_POST['batch_goods_code_yn']){
			$callback = "";
			$msg = "업데이트 조건이 없습니다!";
			openDialogAlert($msg,400,160,'parent',$callback);
			exit;
		}

		$gdcodformqry = "select * from fm_goods_code_form  where label_type ='goodsaddinfo'  and codesetting=1 order by sort_seq";
		$gdcodformquery = $this->db->query($gdcodformqry);
		$this->goods_code_form_arr = $gdcodformquery -> result_array();

		$r_fieldname = array('goods_code','cancel_type');
		foreach($r_goods_seq as $goods_seq) {
			$r_field = array();
			$r_value = array();
			foreach($r_fieldname as $fieldname){
				if( $_POST['batch_'.$fieldname.'_yn'] == 1 ){
					if($fieldname == 'goods_code'){// 상품테이블에 상품코드/검색어 저장
						$goodscodear = goodscodeautock($goods_seq,'batch');
						$r_value[] =$goodscodear['tmpreturncode'];
						$r_field[] = "goods_code=?";

						$r_value[] = $goodscodear['tmpkeyword'];
						$r_field[] = "keyword=?";
					}else{
						$r_value[] = str_replace('"',htmlspecialchars('"', ENT_QUOTES),$_POST['batch_'.$fieldname]);
						$r_field[] = $fieldname."=?";
					}
				}
			}

			// 상품 업데이트일자 추가 leewh 2015-01-16
			$r_field[] = "update_date=?";
			$r_value[] = date("Y-m-d H:i:s",time());

			$r_value[] = $goods_seq;
			$query = "update fm_goods set ".implode(',',$r_field)." where goods_seq=?";
			$this->db->query($query,$r_value);

			// 검색어 추출
			$goods = $this->goodsmodel->get_goods($goods_seq);

			$arr_tmp_keyword = array();
			$result_keyword = $this->goodsmodel->set_search_keyword($goods['goods_seq'],$goods['goods_code'],$goods['goods_name'],$goods['summary'],$goods['keyword']);
			if( $result_keyword['keyword']){
				$arr_tmp_keyword[] = $result_keyword['keyword'];
			}
			if($result_keyword['auto_keyword']){
				$arr_tmp_keyword[] = $result_keyword['auto_keyword'];
			}
			$keyword = implode(',',$arr_tmp_keyword);

			// 상품테이블에 검색어 저장
			$this->goodsmodel->update_keyword($goods_seq,$keyword);


		}//endforeach

		$msg = "상품정보가 변경 되었습니다.";
		$callback = "parent.location.reload();";
		openDialogAlert($msg,400,160,'parent',$callback);
	}

	public function _batch_modify_category()
	{
		$mode	= $_POST['target_modify'];
		$act	= $_POST['search_'.$mode.'_mode'];

		if(($mode=='category' && $_POST['modify_list_category'] == 'all') || ($mode=='brand' && $_POST['modify_list_brand'] == 'all') || ($mode=='location' && $_POST['modify_list_location'] == 'all')){
			$_GET	= $_POST;
			$sc		= $_GET;
			$this->goodsmodel->batch_mode = 1;
			$query = $this->goodsmodel->admin_goods_list($sc);
			$query = $this->db->query($query);
			foreach($query->result_array() as $data){
				$r_goods_seq[] = $data['goods_seq'];
			}
		}else{
			$r_goods_seq = $_POST['goods_seq'];
		}

		if(!$r_goods_seq){
			$callback = "";
			$msg = "수정할 상품이 없습니다!";
			openDialogAlert($msg,400,160,'parent',$callback);
			exit;
		}

		if($mode=='category'){
			$source_code = $_POST['category1'];
			if($_POST['category2']) $source_code = $_POST['category2'];
			if($_POST['category3']) $source_code = $_POST['category3'];
			if($_POST['category4']) $source_code = $_POST['category4'];
		}elseif($mode=='brand'){
			$source_code = $_POST['brands1'];
			if($_POST['brands2']) $source_code = $_POST['brands2'];
			if($_POST['brands3']) $source_code = $_POST['brands3'];
			if($_POST['brands4']) $source_code = $_POST['brands4'];
		}elseif($mode=='location'){
			$source_code = $_POST['location1'];
			if($_POST['location2']) $source_code = $_POST['location2'];
			if($_POST['location3']) $source_code = $_POST['location3'];
			if($_POST['location4']) $source_code = $_POST['location4'];
		}else{
			$callback = "";
			$msg = "error";
			openDialogAlert($msg,400,160,'parent',$callback);
			exit;
		}

		$code = $_POST[$act.'_'.$mode.'1'];
		if($_POST[$act.'_'.$mode.'2']) $code = $_POST[$act.'_'.$mode.'2'];
		if($_POST[$act.'_'.$mode.'3']) $code = $_POST[$act.'_'.$mode.'3'];
		if($_POST[$act.'_'.$mode.'4']) $code = $_POST[$act.'_'.$mode.'4'];


		if($act == 'del'){
			$this->{'_batch_modify_'.$act}($r_goods_seq,$code,$source_code,$mode,1,1);
		}else if($act){
			$this->{'_batch_modify_'.$act}($r_goods_seq,$code,$source_code,$mode);
		}

		// 할인혜택 금액 저장
		$this->load->model('goodssummarymodel');
		$this->goodssummarymodel->set_event_price(array('goods'=>$r_goods_seq));

		$msg = "상품정보가 변경 되었습니다.";
		$callback = "parent.location.reload();";
		openDialogAlert($msg,400,160,'parent',$callback);
	}

	//상품일괄업데이트 > 아이콘 업데이트
	public function _batch_modify_icon()
	{
		if($_POST['modify_list'] == 'all'){
			$_GET = $_POST;
			$sc = $_GET;
			$this->goodsmodel->batch_mode = 1;
			$query = $this->goodsmodel->admin_goods_list($sc);
			$query = $this->db->query($query);
			foreach($query->result_array() as $data){
				$r_goods_seq[] = $data['goods_seq'];
			}
		}else{
			$r_goods_seq = $_POST['goods_seq'];
		}

		if(!$r_goods_seq){
			$callback = "";
			$msg = "수정할 상품이 없습니다!";
			openDialogAlert($msg,400,160,'parent',$callback);
			exit;
		}

		//일괄변경아이콘
		//선택(전체)상품의 아이콘을 체크박스의 필요성이 없다하여 삭제 2014-08-08 jhr
//		if( $_POST['batch_goodsIconCode_yn'] == 1 ){
			$r_icon = $_POST['batch_goodsIconCode'];
			$start_date = $_POST['batch_iconstartDate'];
			$end_date = $_POST['batch_iconendDate'];
			foreach($r_goods_seq as $goods_seq) {
				$query = "delete from fm_goods_icon where goods_seq=?";
				$this->db->query($query,array($goods_seq));
				if($r_icon){
					foreach($r_icon as $codecd){
						$query = "insert into fm_goods_icon set goods_seq=?,codecd=?,start_date=?,end_date=?";
						$this->db->query($query,array($goods_seq,$codecd,$start_date,$end_date));
					}
				}
			}//endforeach

//		}else{
//			foreach($r_goods_seq as $goods_seq) {
//				$iconSeq	= $_POST['iconSeq'][$goods_seq];
//				if($iconSeq){
//					foreach($iconSeq as $seq){
//						$start_date = $_POST['iconstartDate'][$goods_seq][$seq];
//						$end_date	= $_POST['iconendDate'][$goods_seq][$seq];
//						$this->db->where('icon_seq', $seq);
//						$this->db->update('fm_goods_icon',array('start_date'=>$start_date,'end_date'=>$end_date));
//					}
//				}
//			}
//		}

		// 상품 수정일시 변경
		if	(is_array($r_goods_seq) && count($r_goods_seq) > 0){
			$sql	= "update fm_goods set update_date = '".date('Y-m-d H:i:s')."' where goods_seq in ('".implode("', '", $r_goods_seq)."') ";
			$this->db->query($sql);

			// 할인혜택 금액 저장
			$this->load->model('goodssummarymodel');
			$this->goodssummarymodel->set_event_price(array('goods'=>$r_goods_seq));
		}

		$msg = "상품의 아이콘이 변경 되었습니다.";
		$callback = "parent.location.reload();";
		openDialogAlert($msg,400,160,'parent',$callback);
	}

	public function _batch_modify_add($r_goods_seq,$code,$source_code,$mode)
	{
		if(!$code){
			$callback = "";
			if($mode == 'category') $msg = "연결할 카테고리를 선택하세요!";
			else  $msg = "연결할 브랜드를 선택하세요!";
			openDialogAlert($msg,400,160,'parent',$callback);
			exit;
		}
		$this->load->model('categorymodel');
		$this->load->model('brandmodel');
		$this->load->model('locationmodel');
		if	($mode == 'category'){
			$minsort	= $this->categorymodel->getSortValue($code, 'min');
			$mobile_minsort	= $this->categorymodel->getSortValue($code, 'mobile_min');
		}else if	($mode == 'brand'){
			$minsort	= $this->brandmodel->getSortValue($code, 'min');
			$mobile_minsort	= $this->brandmodel->getSortValue($code, 'mobile_min');
		}else if	($mode == 'location'){
			$minsort	= $this->locationmodel->getSortValue($code, 'min');
			$mobile_minsort	= $this->locationmodel->getSortValue($code, 'mobile_min');
		}else{
			$msg = "error";
			openDialogAlert($msg,400,160,'parent',$callback);
			exit;
		}

		$table = "fm_".$mode."_link";
		$r_code = $this->categorymodel->split_category($code);
		foreach($r_goods_seq as $goods_seq){
			$query = "select count(*) cnt from ".$table." where goods_seq=? and link";
			$query = $this->db->query($query,array($goods_seq));
			$data = $query->row_array();
			$link_cnt = $data['cnt'];

			if($mode=='location'){
				foreach($r_code as $k => $location_code){
					$last_k = count($r_code)-1;
					$query = "select count(*) cnt from ".$table." where goods_seq=? and location_code=?";
					$query = $this->db->query($query,array($goods_seq,$location_code));
					$data = $query->row_array();
					$r_insert['link'] = 0;
					if($link_cnt == 0 && $last_k == $k)$r_insert['link'] = 1;
					if($data['cnt'] > 0) {
						if($link_cnt>0) continue; //이미 대표카테고리라면 건너 띔
						//대표카테고리 업데이트 만 함
						$this->db->where(array('goods_seq' => $goods_seq, 'location_code' => $location_code));
						$this->db->update($table, array('link'=>$r_insert['link']) );
						continue;
					}
					$r_insert['location_code'] = $location_code;
					$r_insert['goods_seq'] = $goods_seq;
					$r_insert['regist_date'] = date('Y-m-d H:i:s',time());
					$result = $this->db->insert($table, $r_insert);
					if($mode == 'location'){
						$link_seq = $this->db->insert_id();
						$this->db->where('location_link_seq', $link_seq);
						$this->db->update($table,array('sort'=>$minsort-1));
					}
				}
			}else{
				foreach($r_code as $k => $category_code){
					$last_k = count($r_code)-1;
					$query = "select count(*) cnt from ".$table." where goods_seq=? and category_code=?";
					$query = $this->db->query($query,array($goods_seq,$category_code));
					$data = $query->row_array();
					$r_insert['link'] = 0;
					if($link_cnt == 0 && $last_k == $k)$r_insert['link'] = 1;
					if($data['cnt'] > 0) {
						if($link_cnt>0) continue; //이미 대표카테고리라면 건너 띔
						//대표카테고리 업데이트 만 함
						$this->db->where(array('goods_seq' => $goods_seq, 'category_code' => $category_code));
						$this->db->update($table, array('link'=>$r_insert['link']) );
						continue;
					}
					$r_insert['category_code'] = $category_code;
					$r_insert['goods_seq'] = $goods_seq;
					$r_insert['regist_date'] = date('Y-m-d H:i:s',time());
					$result = $this->db->insert($table, $r_insert);
					if($mode == 'category'){
						$link_seq = $this->db->insert_id();
						$this->db->where('category_link_seq', $link_seq);
						$this->db->update($table,array('sort'=>$minsort-1));
					}
				}
			}
		}
	}

	public function _batch_modify_move($r_goods_seq,$code,$source_code,$mode)
	{
		if(!$code){
			$callback = "";
			if($mode == 'category') $msg = "연결할 카테고리를 선택하세요!";
			else  $msg = "연결할 브랜드를 선택하세요!";
			openDialogAlert($msg,400,160,'parent',$callback);
			exit;
		}
		$this->_batch_modify_del($r_goods_seq,$code,$source_code,$mode,1);
		$this->_batch_modify_add($r_goods_seq,$code,$source_code,$mode);
	}

	public function _batch_modify_copy($r_goods_seq,$code,$source_code,$mode)
	{

		if(!$code){
			$callback = "";
			if($mode == 'category') $msg = "연결할 카테고리를 선택하세요!";
			else  $msg = "연결할 브랜드를 선택하세요!";
			openDialogAlert($msg,400,160,'parent',$callback);
			exit;
		}

		foreach($r_goods_seq as $goods_seq){
			$oldSeq = $goods_seq;

			### FM_GOODS
			$goodSeq = $this->goodsmodel->copy_goods($oldSeq);

			### GOODS_DEFAULT
			if($mode == 'brand') {
				$result = $this->goodsmodel->copy_goods_default('fm_category_link', $oldSeq, $goodSeq, 'category_link_seq');
				$result = $this->goodsmodel->copy_goods_default('fm_location_link', $oldSeq, $goodSeq, 'location_link_seq');
			}
			if($mode == 'category') {
				$result = $this->goodsmodel->copy_goods_default('fm_brand_link', $oldSeq, $goodSeq, 'category_link_seq');
				$result = $this->goodsmodel->copy_goods_default('fm_location_link', $oldSeq, $goodSeq, 'location_link_seq');
			}
			if($mode == 'location') {
				$result = $this->goodsmodel->copy_goods_default('fm_category_link', $oldSeq, $goodSeq, 'category_link_seq');
				$result = $this->goodsmodel->copy_goods_default('fm_brand_link', $oldSeq, $goodSeq, 'category_link_seq');
			}

			$result = $this->goodsmodel->copy_goods_default('fm_goods_addition', $oldSeq, $goodSeq, 'addition_seq');
			$result = $this->goodsmodel->copy_goods_default('fm_goods_icon', $oldSeq, $goodSeq, 'icon_seq');
			$result = $this->goodsmodel->copy_goods_default('fm_goods_input', $oldSeq, $goodSeq, 'input_seq');
			$result = $this->goodsmodel->copy_goods_default('fm_goods_relation', $oldSeq, $goodSeq, 'relation_seq');
			$result = $this->goodsmodel->copy_goods_default('fm_goods_socialcp_cancel', $oldSeq, $goodSeq, 'seq');

			### OPTION : fm_goods_option, fm_goods_suboption, fm_goods_supply
			$result = $this->goodsmodel->copy_goods_option($oldSeq, $goodSeq);

			### GOODS_IMAGE
			$result = $this->goodsmodel->copy_goods_image('fm_goods_image', $oldSeq, $goodSeq, 'image_seq');
			$r_new_goods_seq[] = $goodSeq;
		}

		$this->_batch_modify_add($r_new_goods_seq,$code,$source_code,$mode);
	}

	public function _batch_modify_del($r_goods_seq,$code,$source_code,$mode,$move_act=0,$except_link=0)
	{

		if(!$source_code){
			$callback = "";
			if($mode == 'category') $msg = "카테고리를 검색하세요!";
			else  $msg = "브랜드를 검색하세요!";
			openDialogAlert($msg,400,160,'parent',$callback);
			exit;
		}

		$table = "fm_".$mode."_link";
		foreach($r_goods_seq as $goods_seq){
			if($mode=='location'){
				if( $move_act == 1 ){
					$query = "delete from ".$table." where goods_seq=? and location_code like '".$source_code."%'";
				}else{
					$query = "delete from ".$table." where goods_seq=? and location_code not like '".substr($source_code,0,4)."%'";
				}
				$cnt = 0;
				if($except_link){
					$query_except = "select count(*) cnt from $table where link=1 and location_code like '".$source_code."%' and goods_seq=?";
					$query_except =  $this->db->query($query_except,array($goods_seq));
					$data = $query_except->row_array();
					$cnt = $data['cnt'];
				}
				if($cnt==0) $this->db->query($query,array($goods_seq));
			}else{
				if( $move_act == 1 ){
					$query = "delete from ".$table." where goods_seq=? and category_code like '".$source_code."%'";
				}else{
					$query = "delete from ".$table." where goods_seq=? and category_code not like '".substr($source_code,0,4)."%'";
				}
				$cnt = 0;
				if($except_link){
					$query_except = "select count(*) cnt from $table where link=1 and category_code like '".$source_code."%' and goods_seq=?";
					$query_except =  $this->db->query($query_except,array($goods_seq));
					$data = $query_except->row_array();
					$cnt = $data['cnt'];
				}
				if($cnt==0) $this->db->query($query,array($goods_seq));
			}
		}
	}

	public function _batch_modify_all_del($r_goods_seq,$code,$source_code,$mode)
	{
		$table = "fm_".$mode."_link";
		foreach($r_goods_seq as $goods_seq){
			$query = "delete from ".$table." where goods_seq=?";
			$this->db->query($query,array($goods_seq));
		}
	}

	// 상품코드를 일괄 업데이트하기
	public function batch_goodscode_all()
	{
		set_time_limit(0);
		$_GET['page']	 = ($_GET['page']) ? intval($_GET['page']):'1';
		$sc['limitnum'] = 500;//300

		### GOODS
		$this->load->model('goodsmodel');
		$this->load->model('categorymodel');
		$this->load->model('brandmodel');
		$this->load->model('locationmodel');

		$loop = $this->goodsmodel->goodscode_batch_goods_list($sc);

		### PAGE & DATA
		$query = "select count(*) cnt from fm_goods where goods_type = 'goods' ";
		$query = $this->db->query($query);
		$data = $query->row_array();
		$all_count = $data['cnt'];

		$gdcodformqry = "select * from fm_goods_code_form  where label_type ='goodsaddinfo'  and codesetting=1 order by sort_seq";
		$gdcodformquery = $this->db->query($gdcodformqry);
		$this->goods_code_form_arr = $gdcodformquery -> result_array();

		$idx = 0;
		foreach($loop['record'] as $k => $datarow) {
			goodscodeautock($datarow['goods_seq']);
			$idx++;
		}

		if( ($_GET['page']>1 && $all_count <= ( ($sc['limitnum']*$_GET['page'])) ) || ($_GET['page']==1 && $all_count == $idx ) ) {
			$cfg_goodscodebatch = config_load('goodscodebatch');
			config_save('goodscodebatch',array('update_date'=>$cfg_goodscodebatch['update_date'].'\n'.date('Y-m-d H:i:s')));
			$status = 'FINISH';
		}else{
			$status = 'NEXT';
		}

		$totalpage = @ceil($all_count/ $sc['limitnum']);

		$result = array('status' => $status,'totalcount'=>$all_count,'nextpage'=>($_GET['page']+1),'totalpage'=>$totalpage);
		echo json_encode($result);
		//exit;
	}

	//상품코드 자동생성
	public function tmpgoodscode()
	{
		//상품코드 자동등록처리 @2013-02-07
		$returncode = goodscodeautockview();
		echo $returncode;
	}

	function watermark_goods()
	{
		$target_imgs = explode('|',urldecode($this->input->post('target_image')));
		$this->load->model('watermarkmodel');

		$result = "FALSE";
		if($target_imgs[0]){
			foreach($target_imgs as $target_image){
				if($target_image){
					$this->watermarkmodel->goods_seq = $this->input->post('goods_seq');
					$this->watermarkmodel->target_image = str_replace('//','/',ROOTPATH.$target_image);
					$this->watermarkmodel->source_image_cp();

					$result = $this->watermarkmodel->watermark();
				}
			}

		}

		echo $result;
	}

	function watermark_recovery()
	{
		$target_imgs = explode('|',urldecode($_POST['target_image']));
		$this->load->model('watermarkmodel');

		$result = "ERR";
		if($target_imgs[0]){
			foreach($target_imgs as $target_image){
				if($target_image){
					$this->watermarkmodel->goods_seq = $_POST['goods_seq'];
					$this->watermarkmodel->target_image = str_replace('//','/',ROOTPATH.$target_image);
					$water_return = $this->watermarkmodel->recovery();
					$result = ($water_return == "OK" || $result == "OK") ? "OK" : "ERR";
				}
			}
		}
		echo $result;
	}

	public function _batch_modify_watermark()
	{
		if(!$_POST['goods_seq']){
			$msg = "상품을 선택하여 주세요.";
			openDialogAlert($msg,400,160,'parent',$callback);
			exit;
		}

		$this->load->model('watermarkmodel');

		if($_POST['remove_watermark'] != 1){
			$this->watermarkmodel->watermark_setting();
		}

		$r_target_type = array('large','view');
		foreach($_POST['goods_seq'] as $goods_seq){
			$r_images = $this->goodsmodel->get_goods_image($goods_seq);

			$this->watermarkmodel->goods_seq = $goods_seq;
			foreach($r_images as $r_image){
				for($i=0;$i<4;$i++){
					$field = $r_target_type[$i];
					$image = $r_image[$field]['image'];
					$image_src = str_replace('//','/',ROOTPATH.$image);

					if( $image && file_exists($image_src) )
					{
						$this->watermarkmodel->target_image = $image_src;
						if($_POST['remove_watermark']==1){
							$this->watermarkmodel->recovery();
						}else{
							$this->watermarkmodel->source_image_cp();
							$this->watermarkmodel->watermark();
						}
					}
				}
			}
		}

		$msg = "상품정보가 변경 되었습니다.";
		$callback = "parent.location.reload();";
		openDialogAlert($msg,400,160,'parent',$callback);

	}

	## 판매마켓 상태 및 금액 일괄조정
	public function _batch_modify_mprice(){

		$this->load->model('openmarketmodel');

		$target_type			= trim($_POST['target_type']);
		$set_status_openmarket	= trim($_POST['set_status_openmarket']);
		$openmarket_use_status	= trim($_POST['openmarket_use_status']);
		$openmarket_use_price	= trim($_POST['openmarket_use_price']);
		$openmarket_status		= trim($_POST['openmarket_status']);
		$set_price_openmarket	= trim($_POST['set_price_openmarket']);
		$top_sel_mall			= trim($_POST['top_sel_mall']);
		$revision_set_data		= trim($_POST['revision_set_data']);
		$set_revision_val		= trim($_POST['set_revision_val']);
		$set_revision_unit		= trim($_POST['set_revision_unit']);
		$set_revision_type		= trim($_POST['set_revision_type']);
		$revision_set			= trim($_POST['revision_set']);
		$goods_seq				= $_POST['goods_seq'];

		// 연동 설정
		$linkage	= $this->openmarketmodel->get_linkage_config();
		if	($linkage['cut_price_use'] == 'y'){
			$cut_arr	= array('unit'	=> $linkage['cut_price_unit'],
								'type'	=> $linkage['cut_price_type']);
		}

		// 설정 몰 목록
		$mall		= $this->openmarketmodel->get_linkage_mall('code');


		// 적용 대상 ( 전체 )
		if	($target_type == 'all'){
			unset($goods_seq);
			$_GET = $_POST;
			$sc = $_GET;
			$this->goodsmodel->batch_mode = 1;
			$query = $this->goodsmodel->admin_goods_list($sc);
			$query = $this->db->query($query);
			foreach($query->result_array() as $data){
				$goods_seq[] = $data['goods_seq'];
			}
		}

		if	(is_array($goods_seq) && count($goods_seq) > 0){
			$addWhere	= " and goods_seq in ('".implode("', '", $goods_seq)."') ";

			// 상태변경 처리
			if	($openmarket_use_status == 'y'){
				if	($set_status_openmarket != 'all'){
					$addWhere_status	= " and mall_code = '".$set_status_openmarket."' ";
				}
				$addWhere1	= $addWhere . $addWhere_status;

				// 기존 데이터 삭제
				$sql	= "delete from fm_linkage_goods_mall where goods_seq > 0 ".$addWhere1;
				$this->db->query($sql);
				if	($openmarket_status == 'send'){
					if	($set_status_openmarket == 'all'){
						// 마켓 데이터 추가
						$sql	= "select * from fm_linkage_mall group by mall_code ";
						$query	= $this->db->query($sql);
						$result	= $query->result_array();
						if	($result)foreach($result as $k => $set_mall){
							if	(!in_array($set_mall['mall_code'], $bf_mall_code)){
								foreach($goods_seq as $g => $gseq){
									unset($sInsParam);
									$sInsParam['goods_seq']	= $gseq;
									$sInsParam['mall_code']	= $set_mall['mall_code'];
									$sInsParam['mall_name']	= $set_mall['mall_name'];
									$sInsParam['mall_key']	= $set_mall['mall_key'];
									$this->db->insert('fm_linkage_goods_mall', $sInsParam);
								}
							}
							$bf_mall_code[]		= $set_mall['mall_code'];
						}
					}else{
						foreach($goods_seq as $g => $gseq){
							unset($sInsParam);
							$sInsParam['goods_seq']	= $gseq;
							$sInsParam['mall_code']	= $set_status_openmarket;
							$sInsParam['mall_name']	= $mall[$set_status_openmarket]['mall_name'];
							$sInsParam['mall_key']	= $mall[$set_status_openmarket]['mall_key'];
							$this->db->insert('fm_linkage_goods_mall', $sInsParam);
						}
					}
				}
			}

			// 금액변경 처리
			if	($openmarket_use_price == 'y'){
				$addWhere2	= $addWhere;
				if	($set_price_openmarket != 'all'){
					$addWhere2 .= " and mall_code = '".$set_price_openmarket."' ";
				}

				if	($top_sel_mall == 'direct'){
					$cal_type			= 'manual';
					$mall_code			= $set_price_openmarket;
					$mall_name			= $mall[$set_price_openmarket]['mall_name'];
				}else{
					$cal_type			= 'auto';
					$mall_code			= $set_price_openmarket;
					$mall_name			= $mall[$set_price_openmarket]['mall_name'];
					$revision_arr		= explode('|', $revision_set_data);
					$set_revision_val	= $revision_arr[0];
					$set_revision_unit	= $revision_arr[1];
					$set_revision_type	= $revision_arr[2];
				}

				// 기존 데이터 삭제
				$sql		= "delete from fm_linkage_goods_config where goods_seq > 0 ".$addWhere2;
				$this->db->query($sql);
 				$sql		= "delete from fm_linkage_goods_price where goods_seq > 0 ".$addWhere2;
				$this->db->query($sql);

				$sql		= "select * from fm_goods_option where option_seq > 0 ". $addWhere;
				$query		= $this->db->query($sql);
				$result		= $query->result_array();
				if	($result && $set_price_openmarket == 'all'){
					foreach($result as $k => $opt){
						$opt_supply_sql		= "select supply_seq,supply_price,stock from fm_goods_supply where goods_seq=? AND option_seq=?";
						$opt_supply_query	= $this->db->query($opt_supply_sql,array($opt['goods_seq'], $opt['option_seq']));
						$opt_supply_data	= $opt_supply_query->row_array();
						$opt = array_merge($opt, $opt_supply_data);

						foreach($mall as $m => $malldata){
							$key_code	= $opt['goods_seq'] . '-' . $malldata['mall_code'];
							// 설정 값 추가
							if	(!in_array($key_code, $end_key_code)){
								unset($insParam);
								$insParam['goods_seq']		= $opt['goods_seq'];
								$insParam['option_type']	= 'opt';
								$insParam['cal_type']		= $cal_type;
								$insParam['mall_code']		= $malldata['mall_code'];
								$insParam['mall_name']		= $malldata['mall_name'];
								$insParam['revision_val']	= $set_revision_val;
								$insParam['revision_unit']	= $set_revision_unit;
								$insParam['revision_type']	= $set_revision_type;
								$this->db->insert('fm_linkage_goods_config', $insParam);
							}
							$end_key_code[$key_code]	= $key_code;

							// 금액 데이터 추가
							unset($insParam);
							$consumer_price	= $this->openmarketmodel->calRevision($opt['consumer_price'], $set_revision_val, $set_revision_type, $set_revision_unit, $cut_arr);
							$supply_price	= $this->openmarketmodel->calRevision($opt['supply_price'], $set_revision_val, $set_revision_type, $set_revision_unit, $cut_arr);
							$sale_price		= $this->openmarketmodel->calRevision($opt['price'], $set_revision_val, $set_revision_type, $set_revision_unit, $cut_arr);
							$insParam['linkage_seq']			= $linkage['linkage_seq'];
							$insParam['mall_code']				= $malldata['mall_code'];
							$insParam['mall_name']				= $malldata['mall_name'];
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
							// $insParam['shop_margin']			= $opt['price'] - $opt['supply_price'];
							$insParam['consumer_price']			= $consumer_price;
							$insParam['supply_price']			= $supply_price;
							$insParam['sale_price']				= $sale_price;
							// $insParam['margin']					= $sale_price - $supply_price;
							$insParam['regist_date']			= date('Y-m-d H:i:s');
							$this->db->insert('fm_linkage_goods_price', $insParam);
						}
					}
				}elseif	($result){
					foreach($result as $k => $opt){
						$opt_supply_sql		= "select supply_seq,supply_price,stock from fm_goods_supply where goods_seq=? AND option_seq=?";
						$opt_supply_query	= $this->db->query($opt_supply_sql,array($opt['goods_seq'], $opt['option_seq']));
						$opt_supply_data	= $opt_supply_query->row_array();
						$opt = array_merge($opt, $opt_supply_data);

						// 설정 값 추가
						if	(!in_array($opt['goods_seq'], $end_goods_seq)){
							unset($insParam);
							$insParam['goods_seq']		= $opt['goods_seq'];
							$insParam['option_type']	= 'opt';
							$insParam['cal_type']		= $cal_type;
							$insParam['mall_code']		= $mall_code;
							$insParam['mall_name']		= $mall_name;
							$insParam['revision_val']	= $set_revision_val;
							$insParam['revision_unit']	= $set_revision_unit;
							$insParam['revision_type']	= $set_revision_type;
							$this->db->insert('fm_linkage_goods_config', $insParam);
						}
						$end_goods_seq[$opt['goods_seq']]	= $opt['goods_seq'];

						// 설정 값 추가
						unset($insParam);
						$consumer_price	= $this->openmarketmodel->calRevision($opt['consumer_price'], $set_revision_val, $set_revision_type, $set_revision_unit, $cut_arr);
						$supply_price	= $this->openmarketmodel->calRevision($opt['supply_price'], $set_revision_val, $set_revision_type, $set_revision_unit, $cut_arr);
						$sale_price		= $this->openmarketmodel->calRevision($opt['price'], $set_revision_val, $set_revision_type, $set_revision_unit, $cut_arr);
						$insParam['linkage_seq']			= $linkage['linkage_seq'];
						$insParam['mall_code']				= $mall_code;
						$insParam['mall_name']				= $mall_name;
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
						// $insParam['shop_margin']			= $opt['price'] - $opt['supply_price'];
						$insParam['consumer_price']			= $consumer_price;
						$insParam['supply_price']			= $supply_price;
						$insParam['sale_price']				= $sale_price;
						// $insParam['margin']					= $sale_price - $supply_price;
						$insParam['regist_date']			= date('Y-m-d H:i:s');
						$this->db->insert('fm_linkage_goods_price', $insParam);
					}
				}
			}

			// 판매마켓 전송 요청
			foreach($goods_seq as $g => $gseq){
				$this->openmarketmodel->request_send_goods($gseq);
			}
		}

		$msg = "상품정보가 변경 되었습니다.";
		$callback = "parent.location.reload();";
		openDialogAlert($msg,400,160,'parent',$callback);
	}

	//상품일괄업데이트 > 아이콘업데이트 : 개별 아이콘 삭제
	public function goods_icon_del(){
		$goodSeq = $_GET["goods_seq"];
		$icon_seq = $_GET["icon_seq"];
		if( $goodSeq && $icon_seq ){
			$result = $this->db->delete('fm_goods_icon', array('goods_seq' => $goodSeq,'icon_seq' => $icon_seq));
		}else{
			$result = false;
		}

		// 상품 수정일시 변경
		if	($result){
			$sql	= "update fm_goods set update_date = '".date('Y-m-d H:i:s')."' where goods_seq =".$goodSeq;
			$this->db->query($sql);

			// 할인혜택 금액 저장
			$this->load->model('goodssummarymodel');
			$this->goodssummarymodel->set_event_price(array('goods'=>$goodSeq));
		}

		echo json_encode($result);
		exit;
	}


	public function goods_info_del(){
		$info_seq = $_GET["seq"];
		$result = $this->db->delete('fm_goods_info', array('info_seq' => $info_seq));
		echo json_encode($result);
		exit;
	}

	/**
	* 필수옵션 임시옵션정보 생성 (opt 1단계)
	**/
	public function make_tmp_option(){
		$this->load->model('goodsmodel');
		$params	= $this->input->post();
		$this->goodsmodel->make_tmp_option($params);

		$msg 		= "옵션이 추가되었습니다.";
		$callback 	= "parent.location.reload();";
		openDialogAlert($msg,400,160,'parent',$callback);
	}

	/**
	* 필수옵션 임시옵션정보을 가지고 실제테이블에 생성 (opt 3단계)
	**/
	public function save_option_tmp(){
		$this->load->model('goodsmodel');
		$params	= $this->input->post();
		$this->goodsmodel->save_option_tmp($params);

		$msg = "적용되었습니다.";
		$callback = "parent.setTmpSeq();";
		openDialogAlert($msg,400,160,'parent',$callback);
	}


	/**
	* 추가구성옵션 임시옵션정보 생성 (subopt 1단계)
	**/
	public function make_suboption_tmp(){
		$this->load->model('goodsmodel');
		$params	= $this->input->post();
		$this->goodsmodel->make_suboption_tmp($params);

		$msg = "옵션이 추가되었습니다.";
		$callback = "parent.location.reload();";
		openDialogAlert($msg,400,160,'parent',$callback);
	}

	/**
	* 추가구성옵션 임시옵션정보을 가지고 실제테이블에 생성 (subopt 3단계)
	**/
	public function save_suboption_tmp(){
		$this->load->model('goodsmodel');
		$params		= $this->input->post();
		$msg		= '';
		$callback	= '';
		
		if( isset($params['tmp_package_option_seq1'][0] ) === true && !$params['tmp_package_option_seq1'][0]){
			$msg = "상품을 연결하셔야 합니다.";
		}
		if(!$msg){
			$this->goodsmodel->save_suboption_tmp($params);
			$msg 		= "옵션이 저장되었습니다.";
			$callback 	= "parent.setTmpSeq();";
		}

		openDialogAlert($msg,400,160,'parent',$callback);
	}

	public function insert_option_tmp()
	{
		$set_params	= $this->input->post();
		$this->load->model('goodsmodel');
		$this->goodsmodel->insert_option_tmp($set_params);
		echo json_encode(array('sql'=>end($this->db->queries)));
	}

	public function goods_option_frequently() {
		$this->load->model('goodsmodel');
		$result = false;
		switch($this->input->get('type')){
			case 'option':
				$type = 'opt';//frequentlyopt
				break;
			case 'suboption':
				$type = 'sub';//frequentlysub
				break;
			case 'inputoption':
				$type = 'inp';//frequentlyinp
				break;
		}
		$loop = $this->goodsmodel->frequentlygoods($type);

		if($loop) {
			$loophtml = '';
			foreach( $loop as $key => $data ){
				$loophtml .= "<option value='".$data['goods_name']."^^".$data['goods_seq']."' >".$data['goods_name']."</option>";
			}
			$result = true;
		}

		$result = array('result' => $result,'loophtml'=>$loophtml);
		echo json_encode($result);
		exit;
	}
	//상품 > 상품후기건수 업데이트
	public function all_update_goods_review_cont()
	{
		set_time_limit(0);
		$cfg_good_review_count = config_load('good_review_count');

		$_GET['page']	 = ($_GET['page']) ? intval($_GET['page']):'1';
		$sc['limitnum'] = 500;//500

		$gdsql = "select goods_seq from fm_goods  order by goods_seq desc ";
		$gdresult = select_page($sc['limitnum'],$_GET['page'],10,$gdsql,'');
		$gdresult['page']['querystring'] = get_args_list();

		### PAGE & DATA
		$query = "select count(*) cnt from fm_goods ";
		$query = $this->db->query($query);
		$data = $query->row_array();
		$all_count = $data['cnt'];

		$idx = 0;
		foreach($gdresult['record'] as $gdidx => $gdrow) {
			if( $gdrow['goods_seq'] ) {
				$gdupquery = "select count(*) as review_count from fm_goods_review where goods_seq='{$gdrow['goods_seq']}' ";
				$gdupquery = $this->db->query($gdupquery);
				$review_cnt = $gdupquery->row_array();
				if($review_cnt['review_count'] > 0 ) {
					$upsql  = "update fm_goods set review_count=".$review_cnt['review_count']."  where goods_seq='{$gdrow['goods_seq']}' ";
					$this->db->query($upsql);
				}
			}
			$idx++;
		}

		if( ($_GET['page']>1 && $all_count <= ( ($sc['limitnum']*$_GET['page'])) ) || ($_GET['page']==1 && $all_count == $idx ) ) {
			if( !$cfg_good_review_count['update_date'] ) {
				config_save('good_review_count',array('update_date'=>date('Y-m-d H:i:s')));
			}
			$status = 'FINISH';
		}else{
			$status = 'NEXT';
		}

		$totalpage = @ceil($all_count/ $sc['limitnum']);

		$result = array('status' => $status,'totalcount'=>$all_count,'nextpage'=>($_GET['page']+1),'totalpage'=>$totalpage);
		echo json_encode($result);
		exit;
	}

	public function coupon_serial_upload(){
		$path						= ROOTPATH."/data/tmp/";
		$config['upload_path']		= $path;
		$config['allowed_types']	= 'xls';
		$config['overwrite']		= TRUE;
		$file_ext					= end(explode('.', $_FILES['coupon_serial_file']['name']));
		$config['file_name']		= 'coupon_serial_upload.'.$file_ext;

		$this->load->library('Upload');
		if (is_uploaded_file($_FILES['coupon_serial_file']['tmp_name'])) {
			if	($file_ext == 'xls'){
				$this->upload->initialize($config);
				if ($this->upload->do_upload('coupon_serial_file')) {
					$file_nm	= $config['upload_path'].$config['file_name'];
					@chmod("{$file_nm}", 0777);

					$this->load->model('goodsmodel');
					$result		= $this->goodsmodel->coupon_serial_upload($file_nm);
					if	($result)foreach($result as $coupon_serial => $status){
						$t++;
						if	($t > 1)	$result_str	.= ',';
						$result_str	.= $coupon_serial.'|'.$status.'|';
						if	($status == 'y')	$s++;
						else					$f++;
					}

					echo '<script>parent.setCouponSerial(\''.$t.'\', \''.$result_str.'\');</script>';
					exit;
				}else{
					$callback = "";
					openDialogAlert("업로드에 실패하였습니다.",400,160,'parent',$callback);
					exit;
				}
			}else{
				$callback = "";
				openDialogAlert("xls 파일만 가능합니다.",400,160,'parent',$callback);
				exit;
			}
		}else{
			$callback = "";
			openDialogAlert("업로드할 파일이 없습니다.",400,160,'parent',$callback);
			exit;
		}
	}

	//티켓상품그룹등록
	public function social_goods_group_regist()
	{
		$this->load->model('socialgoodsgroupmodel');
		$social_goods_group_name =  trim($_POST['social_goods_group_name']);
		$provider_seq =  trim($_POST['provider_seq']);
		$social_goods_group_data = $this->socialgoodsgroupmodel->get_data_numrow(array("select"=>" group_seq ","whereis"=>" and name = '".$social_goods_group_name."'  and provider_seq = '".$provider_seq."' "));
		if( $social_goods_group_data ) {
			$msg = "이미 등록된 티켓상품그룹명입니다.";
		}else{
			$insertdata['provider_seq']		= $provider_seq;
			$insertdata['name']		= $social_goods_group_name;
			$insertdata['regist_date'] = date("Y-m-d H:i:s",time());
			$social_goods_group_idx = $this->socialgoodsgroupmodel->sggroup_write($insertdata);
		}

		if($social_goods_group_idx){
			$result = array('result' => true);
		}else{
			$result = array('result' => false, 'msg'=>$msg);
		}
		echo json_encode($result);
		exit;
	}

	// 특수옵션 정보 개별 저장.
	public function save_special_option(){

		$aPostParams = $this->input->post();
		$tmp_no		= trim($aPostParams['tmpSeq']);
		$option_seq	= trim($aPostParams['optionSeq']);
		$option_no	= trim($aPostParams['optionNo']);
		$newType	= trim($aPostParams['newType']);
		if	(!$aPostParams['direct_zipcode'])	$aPostParams['direct_zipcode']	= array();

		if	($tmp_no && $option_seq && $newType){
			// 특수옵션별 처리
			switch($newType){
				case 'color':
					$upParam['color']				= trim($aPostParams['direct_color']);
				break;
				case 'address':
					$upParam['zipcode']				= implode('-', $aPostParams['direct_zipcode']);
					$upParam['address_type']		= trim($aPostParams['direct_address_type']);
 					$upParam['address']				= trim($aPostParams['direct_address']);
					$upParam['address_street']		= trim($aPostParams['direct_address_street']);
					$upParam['addressdetail']		= trim($aPostParams['direct_addressdetail']);
					$upParam['biztel']				= trim($aPostParams['direct_biztel']);
					$upParam['address_commission']	= trim($aPostParams['direct_address_commission']);
				break;
				case 'date':
					$upParam['codedate']			= trim($aPostParams['direct_codedate']);
				break;
			}

			if	(is_array($upParam) && count($upParam) > 0){
				$whrParam['tmp_no']		= $tmp_no;
				$whrParam['option_seq']	= $option_seq;
				$this->goodsmodel->save_tmp_option($whrParam, $upParam);
				if	($aPostParams['same_spc_save_type'] == 'y')
					$this->goodsmodel->save_same_tmp_option($tmp_no, $option_seq, $option_no, $upParam);

				echo '<script type="text/javascript">';
				echo 'parent.loadingStop();';
				echo '</script>';
				exit;
			}
		}
	}

	// 임시 옵션 컬럼당 저장
	public function save_tmpoption_piece(){
		$tmpSeq		= trim($_POST['tmpSeq']);
		$optionSeq	= trim($_POST['optionSeq']);
		unset($_POST['tmpSeq']);unset($_POST['optionSeq']);
		$upParam	= $_POST;
		$saveFunc	= 'save_tmp_option';

		// 저장 대상 컬럼에 따른 예외처리
		if		(isset($_POST['supply_price']) || isset($_POST['stock']) || isset($_POST['badstock']) || isset($_POST['safe_stock']))
			$saveFunc	= 'save_tmp_supply';

		if	($tmpSeq && $optionSeq){
			// default_option 초기화
			if	(isset($_POST['default_option'])){
				$tmpWhrParam['tmp_no']			= $tmpSeq;
				$tmpUpParam['default_option']	= 'n';
				$this->goodsmodel->$saveFunc($tmpWhrParam, $tmpUpParam);
			}

			$whrParam['tmp_no']		= $tmpSeq;
			$whrParam['option_seq']	= $optionSeq;

			//매입가는 KRW 추가 저장
			if($upParam['supply_price']){
				$upParam['supply_price'] = $upParam['supply_price'];
			}

			$this->goodsmodel->$saveFunc($whrParam, $upParam);
		}
	}

	// 임시옵션 항목별 일괄적용
	public function save_tmpoption_cell(){

		$aGetparams 		= $this->input->get();
		$tmpSeq				= trim($aGetparams['tmpSeq']);
		$target				= str_replace('_all', '', trim($aGetparams['target']));
		$value				= trim($aGetparams['value']);
		$commission_type	= trim($aGetparams['commission_type']);
		$commission_type	= ($commission_type == 'SACO' || $commission_type == '') ? 'SACO' : $commission_type;

		if	($target == 'option_view')
			$value			= $value;
		elseif	($target != 'tmp_policy' && $target != 'infomation')
			$value		= preg_replace('/[^0-9|.]*/', '', $value);
		$reserve_unit	= trim($aGetparams['reserve_unit']);
		$reserve_policy	= trim($aGetparams['reserve_policy']);
		$saveFunc		= 'save_tmp_option';

		// 저장 대상 컬럼에 따른 예외처리
		if		(in_array($target, array('supply_price', 'stock', 'badstock', 'safe_stock')))
			$saveFunc	= 'save_tmp_supply';

		if	($tmpSeq && $target){
			$upParam[$target]				= $value;
			if($target == 'commission_rate'){
				$upParam['commission_type']	= $commission_type;
			}

			// 1) 적립금 지급 정책 변경 시 통합설정 값으로 일괄 변경
			// 2) 할인가(판매가) 일괄적용시 적립금이 통합정책시
			if	($target == 'tmp_policy' || ($target == 'price' && $reserve_policy == 'shop') ){
				$reserves		= ($this->reserves)?$this->reserves:config_load('reserve');
				$upParam['reserve_rate']	= $reserves['default_reserve_percent'];
				$upParam['reserve_unit']	= 'percent';
			}

			if	($reserve_unit)
				$upParam['reserve_unit']	= $reserve_unit;

			$whrParam['tmp_no']				= $tmpSeq;
			if	($saveFunc	== 'save_tmp_supply')	$whrParam['suboption_seq']	= NULL;
			$this->goodsmodel->$saveFunc($whrParam, $upParam, $target, $reserve_policy);

			echo '<script>parent.tmpSaveAll(\''.$target.'\', \''.$value.'\', \''.$commission_type.'\');</script>';
		}
	}

	public function save_tmpsuboption_cell(){
		$tmpSeq				= trim($_GET['tmpSeq']);
		$target				= str_replace('_all', '', trim($_GET['target']));
		$value				= trim($_GET['value']);
		$commission_type	= trim($_GET['commission_type']);
		$commission_type	= ($commission_type == 'SACO' || $commission_type == '') ? 'SACO' : $commission_type;

		if	($target != 'tmp_policy')
			$value		= preg_replace('/[^0-9|.]*/', '', $value);
		$reserve_unit	= trim($_GET['reserve_unit']);
		$reserve_policy	= trim($_GET['reserve_policy']);
		$saveFunc		= 'save_tmp_suboption';


		if	($tmpSeq && $target){
			$upParam[$target]				= $value;
			if($target == 'commission_rate'){
				$upParam['commission_type']	= $commission_type;
			}

			// 1) 적립금 지급 정책 변경 시 통합설정 값으로 일괄 변경
			// 2) 할인가(판매가) 일괄적용시 적립금이 통합정책시
			if	($target == 'tmp_policy' || ($target == 'price' && $reserve_policy == 'shop') ){
				$reserves		= ($this->reserves)?$this->reserves:config_load('reserve');
				$upParam['reserve_rate']	= $reserves['default_reserve_percent'];
				$upParam['reserve_unit']	= 'percent';
			}

			if	($reserve_unit)
				$upParam['reserve_unit']	= $reserve_unit;

			$whrParam['tmp_no']				= $tmpSeq;
			$this->goodsmodel->$saveFunc($whrParam, $upParam, $target, $reserve_policy);

			echo '<script>parent.tmpSaveAll(\''.$target.'\', \''.$value.'\', \''.$commission_type.'\');</script>';
		}
	}

	public function save_tmp_option_package(){
		$this->goodsmodel->save_tmp_option_package($this->input->post());
		$script_str = "<script>
		parent.opener.setOptionTmp('".$this->input->post('tmp_no')."','".$this->input->post('tmp_frequently')."','',{'optionViewType':'".$this->input->post('optionViewType')."'});
		parent.self.close();</script>";
		echo $script_str;
	}

	// (실물/티켓)상품관리 기본값 설정 저장
	public function set_option_view_count(){
		
		$params			= $this->input->post();
		$gkind 			= $params['goods_kind'];
		$skind 			= $params['sub_kind'];
		$provider_seq 	= 1;
		if (defined('__SELLERADMIN__') === true) {
			$provider_seq = $this->providerInfo['provider_seq'];
		}
		$manager_seq 	= (int)$this->managerInfo['manager_seq'];
		$result 		= $this->goodsmodel->get_goods_default_config($gkind);
		$data 			= array(
							'goods_kind' => $gkind,
							'manager_seq' => $manager_seq,
							'provider_seq' => $provider_seq,
							'editor_view' => $params['editor_view'],
						);

		if($skind == "option"){
			$limit_view_count		= 100;
			$option_view_count		= trim($params['option_view_count']);
			$suboption_view_count	= trim($params['suboption_view_count']);
	
			// validation
			$this->validation->set_rules('option_view_count', '필수 옵션 보기 갯수','trim|required|max_length[10]|xss_clean|greater_than[0]|less_than['.$limit_view_count.']');
			$this->validation->set_rules('suboption_view_count', '추가 구성 옵션 보기 갯수','trim|required|max_length[10]|xss_clean|greater_than[0]|less_than['.$limit_view_count.']');
			if($this->validation->exec()===false){
				$err = $this->validation->error_array;
				$callback = "if(parent.document.getElementsByName('{$err['key']}')[0]) parent.document.getElementsByName('{$err['key']}')[0].focus();";
				openDialogAlert($err['value'],400,170,'parent',$callback);
				exit;
			}
			/*
			if	(!is_numeric($option_view_count) || $gkind=='goods' && !is_numeric($suboption_view_count)){
				openDialogAlert('기본 개수를 입력하십시오!',400,160,'parent',$callback);
				exit;
			}
			if	($option_view_count < 1 || $gkind=='goods' && $suboption_view_count < 1){
				openDialogAlert('기본 개수를 입력하십시오',400,160,'parent',$callback);
				exit;
			}
			if	($option_view_count > $limit_view_count || $gkind=='goods' && ($suboption_view_count > $limit_view_count)){
				openDialogAlert('기본 개수는 '.$limit_view_count.' 이하로 입력하십시오.',400,160,'parent',$callback);
				exit;
			}
			*/
			$data = array(
				'option_view_count' => $option_view_count,
				'suboption_view_count' => (in_array($gkind, array('goods','package_goods','coupon'))) ? $suboption_view_count : $option_view_count,
			);
		}elseif($skind == "relation"){
			$data = array(
				'relation_criteria' => ($params['relation_criteria']) ? $params['relation_criteria'] : '',
			);
		}elseif($skind == "commonContents"){
			// 상품 공통 정보
			$data = array(
				'common_info_seq' => ($params['common_info_seq']) ? $params['common_info_seq'] : '',
			);
		}else{
			$data = array(
				'list_condition_category' => ($params['list_default_condition']['category']) ? 'y' : 'n',
				'list_condition_brand' => ($params['list_default_condition']['brand']) ? 'y' : 'n',
				'list_condition_stringprice' => ($params['list_default_condition']['stringprice']) ? 'y' : 'n',
			);
		}

		if($result) $result = array_merge($result,$data);

		$this->goodsmodel->set_goods_default_config($gkind,$data,$result);

		$callback	= 'parent.optionViewSave();';
		openDialogAlert('저장되었습니다.',400,160,'parent',$callback);
		exit;
	}

	## 옵션 추가/삭제
	public function save_option_one_row(){
		$saveType		= trim($this->input->get('saveType'));
		$tmpSeq			= trim($this->input->get('tmpSeq'));
		$optionSeq		= trim($this->input->get('optionSeq'));

		$optionSeqTmp	= explode("|", $optionSeq);
		if(count($optionSeqTmp) > 0){
			foreach($optionSeqTmp as $optionSeq){
				if	($saveType && $tmpSeq && $optionSeq) $opt_seq	= $this->goodsmodel->save_option_one_row($saveType, $tmpSeq, $optionSeq);
				if		($saveType == 'add'){
					echo '<script>parent.add_option_row(\''.$opt_seq.'\');</script>';
				}elseif	($saveType == 'del'){
					echo '<script>parent.del_option_row(\''.$opt_seq.'\');</script>';
				}
			}
		}else{
			if	($saveType && $tmpSeq && $optionSeq)
			$opt_seq	= $this->goodsmodel->save_option_one_row($saveType, $tmpSeq, $optionSeq);
			if		($saveType == 'add'){
				echo '<script>parent.add_option_row(\''.$opt_seq.'\');</script>';
			}elseif	($saveType == 'del'){
				echo '<script>parent.del_option_row(\''.$opt_seq.'\');</script>';
			}
		}


	}

	function get_option_goods_status($goods_seq,$runout,$ableStockLimit,$ableStockStep) {
		$return_info = array();

		// 재고 업데이트 전 상품 상태값 확인
		$get_goods = $this->goodsmodel->get_goods($goods_seq);
		$before_goods_status = $get_goods['goods_status'];
		$before_tot_stock = $get_goods['tot_stock'];

		// 변경될 재고 확인
		$get_tot_option = $this->goodsmodel->get_tot_option($goods_seq);
		$afterUnUsableStock = (int) $get_tot_option['stock'] - $get_tot_option['badstock'] - $get_tot_option['reservation'.$ableStockStep];

		// 변경될 상품 상태값
		$modify_status = '';
		if ($runout=="stock") { // 재고가 있으면 판매
			if ($get_tot_option['stock'] < 1) {
				$modify_status = "runout";
			} else {
				$modify_status = "normal";
			}
		} else if ($runout=="ableStock") { // 가용 재고가 있으면 판매
			if ($afterUnUsableStock < $ableStockLimit) {
				$modify_status = "runout";
			} else {
				$modify_status = "normal";
			}
		} else if ($runout=="unlimited") { // 재고와 무관 판매
			if ($get_goods['goods_kind'] == 'coupon' && $get_goods['coupon_serial_type'] == 'n') { // 외부제휴사티켓상품
				if ($get_tot_option['stock'] < 1) {
					$modify_status = "runout";
				} else {
					$modify_status = "normal";
				}
			} else {
				$modify_status = "normal";
			}
		}

		// 정상과 품절이었던 상품들 중 상태값이 변경되는 경우 계산
		if ($before_goods_status=="normal" && $modify_status=="runout") {
			$return_info['runout_gname']['goods_seq'] = $get_goods['goods_seq'];
			$return_info['runout_gname']['goods_name'] = $get_goods['goods_name'];
		} else if ($before_goods_status=="runout" && $modify_status=="normal") {
			$return_info['normal_gname']['goods_seq'] = $get_goods['goods_seq'];
			$return_info['normal_gname']['goods_name'] = $get_goods['goods_name'];
		}

		$return_info['goods_status'] = $modify_status;

		return $return_info;
	}

	/* 상품이미지 설정 저장 */
	public function save_image_config()
	{
		$aParamsPost = $this->input->post();

		/* 상품이미지 설정 체크 */
		$image_arr = array(
			'largeImage'=>'상품상세(확대)',
			'viewImage'=>'대표 사진',
			'list1Image'=>'리스트(1)',
			'list2Image'=>'리스트(2)',
			'thumbView'=>'상품상세(썸네일)',
			'thumbCart'=>'장바구니/주문',
			'thumbScroll'=>'스크롤'
		);
		foreach($image_arr as $image_type => $image_description){
			$width = $aParamsPost[$image_type.'Width'];
			if($width < 1 || !$width){
				$result = array('result' => false, 'msg'=>$image_description."이미지 설정은 \'0\'보다 커야 합니다.");
				echo json_encode($result);
				exit;
			}
		}

		$this->goodsmodel->set_goodsImageSize('large',$aParamsPost['largeImageWidth'],$aParamsPost['largeImageHeight']);
		$this->goodsmodel->set_goodsImageSize('view',$aParamsPost['viewImageWidth'],$aParamsPost['viewImageHeight']);
		$this->goodsmodel->set_goodsImageSize('list1',$aParamsPost['list1ImageWidth'],$aParamsPost['list1ImageHeight']);
		$this->goodsmodel->set_goodsImageSize('list2',$aParamsPost['list2ImageWidth'],$aParamsPost['list2ImageHeight']);
		$this->goodsmodel->set_goodsImageSize('thumbView',$aParamsPost['thumbViewWidth'],$aParamsPost['thumbViewHeight']);
		$this->goodsmodel->set_goodsImageSize('thumbCart',$aParamsPost['thumbCartWidth'],$aParamsPost['thumbCartHeight']);
		$this->goodsmodel->set_goodsImageSize('thumbScroll',$aParamsPost['thumbScrollWidth'],$aParamsPost['thumbScrollHeight']);

		$msg = "상품 사진의 사이즈 설정이 변경 되었습니다.";
		$result = array('result' => true, 
						'msg'=>$msg,
						'largeImageWidth'=>$aParamsPost['largeImageWidth'], 'largeImageHeight'=>$aParamsPost['largeImageHeight'],
						'viewImageWidth'=>$aParamsPost['viewImageWidth'], 'viewImageHeight'=>$aParamsPost['viewImageHeight'],
						'list1ImageWidth'=>$aParamsPost['list1ImageWidth'], 'list1ImageHeight'=>$aParamsPost['list1ImageHeight'],
						'list2ImageWidth'=>$aParamsPost['list2ImageWidth'], 'list2ImageHeight'=>$aParamsPost['list2ImageHeight'],
						'thumbViewWidth'=>$aParamsPost['thumbViewWidth'], 'thumbViewHeight'=>$aParamsPost['thumbViewHeight'],
						'thumbCartWidth'=>$aParamsPost['thumbCartWidth'], 'thumbCartHeight'=>$aParamsPost['thumbCartHeight'],
						'thumbScrollWidth'=>$aParamsPost['thumbScrollWidth'], 'thumbScrollHeight'=>$aParamsPost['thumbScrollHeight']
					);
		echo json_encode($result);
		exit;
	}

	// 양식 저장
	public function save_excel_form(){
		$this->load->model('goodsexcel');
		$params['process']		= 'DOWNLOAD';
		$params['goods_kind']	= 'GOODS';
		if	($_POST['goods_kind'] == 'COUPON' || $_GET['goods_kind'] == 'COUPON')
			$params['goods_kind']	= 'COUPON';
		$params['manager_id']	= $this->managerInfo['manager_id'];

		$this->goodsexcel->set_init($params);
		$this->goodsexcel->save_excel_form($_POST);

		openDialogAlert('저장되었습니다.',400,160,'parent','parent.location.reload()');
		exit;
	}

	// 엑셀 다운로드
	public function goods_excel_download(){
		$this->load->model('goodsexcel');

		$params['process']		= 'DOWNLOAD';
		$params['goods_kind']	= 'GOODS';
		if	($_POST['goods_kind'] == 'COUPON' || $_GET['goods_kind'] == 'COUPON')
			$params['goods_kind']	= 'COUPON';
		$params['manager_id']	= $this->managerInfo['manager_id'];
		$this->goodsexcel->set_init($params);

		/* 기존 엑셀 다운로드 주석 kmj
		if	($_GET['excel_page'] > 0)	$result	= $this->goodsexcel->download_excel($_GET);
		else							$result	= $this->goodsexcel->download_excel($_POST);
		*/

		//엑셀 다운로드 프로세스 추가 kmj
		if($_GET['excel_page'] > 0){	
			$params = $_GET;
		} else {
			$params = $_POST;
		}

		if( $params['goods_seq'] ){
			$params['searchcount'] = count($params['goods_seq']);
		}

		if( $params['searchcount'] <= 0 ){
			echo '다운로드 할 상품이 없습니다.';
			exit;
		}

		$limitCount = 2000;
		$params['perpage'] = $limitCount;
		if( $params['searchcount'] > $limitCount){ //압축 다운로드 시
			$regDate = date('Y-m-d H:i:s');
			$setData = array(
				'id'			=> '',
				'provider_seq'	=> 1,
				'manager_id'	=> $this->managerInfo['manager_id'],
				'category'		=> 1, //1:goods, 2:order, 3:member
				'excel_type'	=> $params['excel_type'], 
				'context'		=> serialize($params),
				'count'			=> $params['searchcount'],
				'state'			=> 0,
				'limit_count'	=> $limitCount,
				'reg_date'		=> $regDate
			);
			$this->db->insert('fm_queue', $setData);
			$queueID = $this->db->insert_id();
			 
			if( $queueID > 0 ){
				$expectTime = ((ceil($params['searchcount']/$limitCount)) * 10) + 1200; 
				echo $params['searchcount'].'건 엑셀 파일 생성 중 (예상 소요시간 : '.gmdate("H시 i분 s초", $expectTime).')';
			} else {
				echo "Job Insert Errors";
			}
		} else {
		    /**
		     * 입점몰이 아닌 솔루션의 티켓상품 리스트에서 엑셀 다운로드시 
		     * provider_seq가 fm_queue에 insert시 provider_seq NOT NULL 조건에 의해 오류가 발생함.
		     * (입점몰에서는 provider_seq라는 name을 가진 select box가 있기 때문에 오류가 발생 안함)
		     * 
		     * cli/excel_down, base/admin_base 의 생성자를 참고하여 provider_seq 값을 넣어주게 처리
		     * 2019-05-28
		     * @author Sunha Ryu 
		     */
		    if(!$params['provider_seq']) {
		        if(!empty($this->managerInfo['manager_seq'])) {
		            $params['provider_seq'] = 1;
		        } else if($this->providerInfo) {
		            $params['provider_seq'] = $this->providerInfo['provider_seq'];
		        }
		    }
		    
			$this->goodsexcel->download_excel_spout($params);
		}

		exit;
	}

	// 엑셀 업로드
	public function goods_excel_upload(){

		if(!preg_match("/^F_SH_/",$this->config_system['service']['hosting_code'])){
			$this->load->model('usedmodel');
			$use_per	= $this->usedmodel->get_used_space_percent();
			if($use_per > 100){
				$callback				= "";
				$popOptions['btn_title']	= '용량추가';
				$popOptions['btn_class']	= 'btn large cyanblue';
				$popOptions['btn_action']	= "window.open('http://firstmall.kr/myshop','_blank')";
				openDialogAlert("용량이 초과되어 상품을 등록 또는 수정할 수 없습니다.<br/>용량 추가를 하시길 바랍니다.",400,175,'parent',$callback,$popOptions);
				exit;
			}
		}
		
		//특정한 사유로 동일한 파일명으로 3초이내 업로드시 제한 @2017-06-01
		$date	= date('Y-m-d H:i:s', strtotime('-3 second'));
		$query	= $this->db->query("select * from fm_excel_upload_log where upload_date > '".$date."' limit 1");
		$secondckLog	= $query->result_array();
		if	($secondckLog[0]) {
			foreach($secondckLog as $secondckLogquery => $sklog){
				$fileinfo	= $_FILES['goods_excel_file'];//첨부파일과 로그파일명 비교
				if( $sklog['upload_filename'] == $fileinfo['name'] ) {
					openDialogAlert("과도한 접속으로 인한 제한합니다.<br/>5초 뒤 다시 접속해 주세요.", 400, 180, 'parent', $callback);
					exit;
				}
			}
		}

		$this->load->model('scmmodel');
		$this->load->model('goodsexcel');
		$params['process']		= 'UPDATE';
		$params['goods_kind']	= 'GOODS';
		if	($_POST['goods_kind'])	$params['goods_kind']	= $_POST['goods_kind'];
		$params['manager_id']	= $this->managerInfo['manager_id'];
		$params['end_user_func']	= 'excel_upload_after_summary_update';
		$this->goodsexcel->set_init($params);

		$result		= $this->goodsexcel->excel_upload('goods_excel_file', $_FILES);
		// 물류관리이며 기초조정이 들어간 경우 추가 처리
		if	($this->scmmodel->chkScmConfig(true) && count($this->goodsexcel->m_aChgGoodsTarget) > 0){
			$this->scmmodel->after_save_for_default_revision($this->goodsexcel->m_aChgGoodsTarget);
		}

		$callback	= "parent.location.reload();";
		openDialogAlert($result['msg'], 400, 180, 'parent', $callback);
		exit;
	}

	// 엑셀 log 파일 다운로드용
	public function download_excel_log(){

		$this->load->model('goodsexcel');

		$filename	= $_GET['f'];
		$result		= $this->goodsexcel->download_log_file($filename);
		if	(!$result['status']){
			openDialogAlert($result['err_msg'], 400, 180, 'parent', '');
			exit;
		}
	}

	public function _batch_modify_relation(){
		$r_goods_seq = $_POST['goods_seq'];

		if(!$r_goods_seq){
			$callback = "";
			$msg = "수정할 상품이 없습니다!";
			openDialogAlert($msg,400,160,'parent',$callback);
			exit;
		}

		$r_fieldname = array('relation','relation_seller');

		foreach($r_goods_seq as $goods_seq) {
			$r_field = array();
			$r_value = array();

			foreach($r_fieldname as $fieldname){
				if($_POST[$fieldname.'_type'][$goods_seq] == 'AUTO'){
					$r_field[] = $fieldname."_criteria=?";
					$r_value[] = trim($_POST[$fieldname.'_criteria'][$goods_seq]);
					if($fieldname == 'relation'){
						$r_field[] = "auto_condition_use=?";
						$r_value[] = trim($_POST['auto_condition_use'][$goods_seq]);
					}
				}else{
					$this->db->delete('fm_goods_'.$fieldname, array('goods_seq' => $goods_seq));
					foreach($_POST[$fieldname.'Goods_'.$goods_seq] as $r_goods){
						$result = $this->db->insert('fm_goods_'.$fieldname, array('goods_seq'=>$goods_seq,'relation_goods_seq'=>$r_goods));
					}
				}
				$r_field[] = $fieldname."_type=?";
				$r_value[] = $_POST[$fieldname.'_type'][$goods_seq];
			}

			$r_field[] = "update_date=?";
			$r_value[] = date("Y-m-d H:i:s",time());
			$r_value[] = $goods_seq;

			$query = "update fm_goods set ".implode(',',$r_field)." where goods_seq=?";

			$this->db->query($query,$r_value);
		}//endforeach

		$msg = "상품정보가 변경 되었습니다.";
		$callback = "parent.location.reload();";
		openDialogAlert($msg,400,160,'parent',$callback);
	}

	public function _batch_modify_ifrelation(){
		if($_POST['modify_list'] == 'all'){
			$_GET = $_POST;
			$sc = $_GET;
			$this->goodsmodel->batch_mode = 1;
			$query = $this->goodsmodel->admin_goods_list($sc);
			$query = $this->db->query($query);
			foreach($query->result_array() as $data){
				$r_goods_seq[] = $data['goods_seq'];
			}
		}else{
			$r_goods_seq = $_POST['goods_seq'];
		}

		if(!$r_goods_seq){
			$callback = "";
			$msg = "수정할 상품이 없습니다!";
			openDialogAlert($msg,400,160,'parent',$callback);
			exit;
		}

		$r_fieldname = array('relation','relation_seller');

		foreach($r_goods_seq as $goods_seq) {
			$r_field = array();
			$r_value = array();

			foreach($r_fieldname as $fieldname){
				if( $_POST['batch_'.$fieldname.'_yn'] == 1 ){
					if($_POST['batch_'.$fieldname.'_type'] == 'AUTO'){
						$r_field[] = $fieldname."_criteria=?";
						$r_value[] = trim($_POST['batch_'.$fieldname.'_criteria']);
						if($fieldname == 'relation'){
							$r_field[] = "auto_condition_use=?";
							$r_value[] = 1;
						}
					}else{
						$this->db->delete('fm_goods_'.$fieldname, array('goods_seq' => $goods_seq));
						foreach($_POST[$fieldname.'Goods'] as $r_goods){
							$result = $this->db->insert('fm_goods_'.$fieldname, array('goods_seq'=>$goods_seq,'relation_goods_seq'=>$r_goods));
						}
					}
					$r_field[] = $fieldname."_type=?";
					$r_value[] = $_POST['batch_'.$fieldname.'_type'];
				}
			}

			$r_field[] = "update_date=?";
			$r_value[] = date("Y-m-d H:i:s",time());
			$r_value[] = $goods_seq;

			$query = "update fm_goods set ".implode(',',$r_field)." where goods_seq=?";

			$this->db->query($query,$r_value);
		}//endforeach

		$msg = "상품정보가 변경 되었습니다.";
		$callback = "parent.location.reload();";
		openDialogAlert($msg,400,160,'parent',$callback);
	}

	public function _batch_modify_shipping(){
		if	($_POST['modify_list'] == 'all'){
		}
	}

	// EP 데이터 배송비 업데이트
	public function _batch_modify_ep_shipping(){
		// done
	}

	// 옵션 유효성 체크
	public function chk_tmpoption_require(){
		$aPostParams = $this->input->post();
		if	($aPostParams['socialcp_input_type']){
			if	($aPostParams['newtype']) foreach($aPostParams['newtype'] as $k => $type){
				if	(in_array($type, array('date', 'dayinput', 'dayauto', 'address'))){
					$newType	= $type;
					break;
				}
			}
			if	($newType){
				$today			= date("Y-m-d");
				$couponexpire	= false;
				/**
				 * 날짜옵션수정시 과거날짜가 있으면 수정이 안되는 문제로 다음과 같이 개선합니다.
				 * 1. 한개라도 비어있으면 수정불가능
				 * 2. 모든옵션 오늘자 이전일때 수정불가능
				 * 3. 한개라도 오늘자 이후일때 수정가능
				 * @2016-07-19 ysm
				**/
				$couponexpirecknum = 0;
				switch($newType){
					case 'date':
						foreach($aPostParams['codedate'] as $k => $codedate){
							//한개라도 비어있으면 수정불가능
							if( !$codedate || strstr($codedate,'0000-00-00') ) {
								$couponexpire = false;
								break;
							}else{
								//3. 날짜옵션중 한개라도 오늘이후가 있다면 수정가능
								if( $codedate >= $today ) {
									$couponexpire = true;
									break;
								}elseif( $codedate < $today ) {//2. 옵션의 과거날짜 건수 체크
										$couponexpirecknum++;
								}
							}
							$social_start_date	= $codedate;
							$social_end_date	= $codedate;
						}

						// 2. 모든옵션이 오늘이전이라면 수정불가능
						if( $couponexpire === false && $couponexpirecknum && $couponexpirecknum != count($aPostParams['codedate']) ) {
							$couponexpire = true;
						}

						if( $couponexpire === false ) {
							$msg = "[티켓상품]의 유효기간을 정확히 입력해 주세요.";
							if( strstr($social_start_date,'0000-00-00') || strstr($social_end_date,'0000-00-00') || !$social_start_date  || !$social_end_date){
								$msg .= "<br/>유효기간이 없습니다.";
							}else{
								$msg .= "<br/>유효기간이 ".$codedate." 입니다.";
							}
							openDialogAlert($msg,450,160,'parent',$callback);
							exit;
						}
					break;
					case 'dayinput':
						foreach($aPostParams['fdayinput'] as $k => $fdayinput){
							if	($fdayinput && $aPostParams['sdayinput'][$k] &&
								$fdayinput >= $today && $fdayinput >= $aPostParams['sdayinput'][$k]){
								$couponexpire = true;
							}else{
								$couponexpire		= false;
								$social_start_date	= $aPostParams['sdayinput'][$k];
								$social_end_date	= $fdayinput;
								break;
							}
						}

						if( $couponexpire === false ) {
							$msg = "[티켓상품]의 유효기간을 정확히 입력해 주세요.";
							if( strstr($social_start_date,'0000-00-00') || strstr($social_end_date,'0000-00-00')  || !$social_start_date  || !$social_end_date){
								$msg .= "<br/>유효기간이 없습니다.";
							}else{
								$msg .= "<br/>유효기간이 ".$social_start_date." ~ ".$social_end_date." 입니다.";
							}
							openDialogAlert($msg,450,160,'parent',$callback);
							exit;
						}
					break;
					case 'dayauto':
						foreach($aPostParams['sdayauto'] as $k => $sdayauto){
							if		(preg_match('/[^0-9]/', $sdayauto) && preg_match('/[^0-9]/', $aPostParams['fdayauto'][$k])){
								$couponexpire		= false;
								break;
							}elseif	(in_array($aPostParams['dayauto_type'][$k], array('month', 'next')) && !($sdayauto > 0 && $sdayauto <= 31)){
								$couponexpire		= false;
								break;
							}else{
								$couponexpire		= true;
							}
						}
						if( $couponexpire === false ) {
							$msg = "[티켓상품]의 유효기간을 정확히 입력해 주세요.";
							$msg .= "<br/>자동날짜 시작일은 1 ~ 31의 숫자만 입력가능 합니다.";
							openDialogAlert($msg,450,160,'parent',$callback);
							exit;
						}
					break;
				}
			}else{
				$msg = "[티켓상품]의 유효기간(지역, 날짜, 자동기간, 수동기간)을 추가해 주세요.";
				openDialogAlert($msg,470,150,'parent',$callback);
				exit;
			}
		}

		echo '<script>parent.set_option_to_opener();</script>';
	}

	// 임시옵션 생성
	public function create_option_batch_regist(){
		$this->load->model('scmmodel');
		$this->load->model('goodsmodel');

		$popup_id				= trim($this->input->post('popup_id'));
		$goods_seq				= trim($this->input->post('goods_seq'));
		$tmp_seq				= trim($this->input->post('tmp_seq'));
		if	( defined('__SELLERADMIN__') === true )	$sellermode			= true;
		$result					= $this->goodsmodel->create_tmp_option($tmp_seq, $goods_seq, $this->input->post());
		if	($result){
			$tmpData						= $result['tmpData'];
			$tmpData['goods'][0]			= $this->goodsmodel->get_tmp_goodsinfo($tmp_seq, $goods_seq);
			$tmpData['goods'][0]['options']	= $result['optionData'];

			// 물류관리일 경우 창고 및 로케이션 정보 추출
			if	($this->scmmodel->chkScmConfig(true)){
				if	(!$this->scm_cfg){
					if	($this->scmmodel->scm_cfg)	$this->scm_cfg		= $this->scmmodel->scm_cfg;
					else							$this->scm_cfg		= config_load('scm');
				}
				$warehouse				= $this->scmmodel->get_warehouse(array('orderby' => 'wh_name asc'));
				$wh_seq					= $warehouse[0]['wh_seq'];
				$whData['warehouse']	= $warehouse;
				if	($wh_seq > 0){
					$whData['location']	= $this->scmmodel->get_location(array('wh_seq' => $wh_seq));
				}
			}

			// goods controller로 그려야 하나 이미 데이터를 모두 가지고 있어서 process그냥 그림
			$filePath		= 'default/goods/_quick_regist_rowskin.html';
			$this->template->define(array('tpl'=>$filePath));
			$this->template->assign(array(
				'sellermode'		=> $sellermode,
				'tmp_seq'			=> $tmp_seq,
				'goods_seq'			=> $goods_seq,
				'scm_cfg'			=> $this->scm_cfg,
				'tmpData'			=> $tmpData,
				'whData'			=> $whData,
				'popup_id'			=> $popup_id,
				'procJS'			=> 'replace_option_list_row();',
			));
			$this->template->print_("tpl");
		}else{
			echo '<script>parent.loadingStop();parent.fail_load_tmp_data();</script>';
			exit;
		}
	}

	// 상품 row 단위 데이터 추가/복사/삭제
	public function save_tmp_goods_row(){
		$this->load->model('goodsmodel');
		$this->load->model('scmmodel');

		$act_type	= trim($this->input->post('act_type'));
		$tmp_seq	= trim($this->input->post('tmp_seq'));
		$goods_seq	= $this->input->post('goods_seq');
		if	( defined('__SELLERADMIN__') === true )	$sellermode			= true;

		$tmpData	= $this->goodsmodel->get_tmp_goods_data($tmp_seq);
		if	(!$tmp_seq || !$tmpData){
			echo '<script>parent.loadingStop();parent.fail_load_tmp_data();</script>';
			exit;
		}

		if		($act_type == 'reset'){
			$this->goodsmodel->tmp_save_cell_data('tmp', $tmp_seq, array('provider_seq' => $this->input->post('provider_seq')));	// 입점사 정보 저장

			$procJS				= 'reset_option_list_row();';
			$result				= $this->goodsmodel->reset_tmp_goods($tmp_seq);
		}elseif	($act_type == 'add'){
			$procJS				= 'add_option_list_row();';
			$result				= $this->goodsmodel->create_tmp_goods($tmp_seq);
		}elseif	($act_type == 'copy'){
			$procJS				= 'add_option_list_row();';
			$result				= $this->goodsmodel->copy_tmp_goods($tmp_seq, $goods_seq);
		}elseif	($act_type == 'remove'){
			if	(count($goods_seq) > 0){
				$procJS			= 'remove_option_list_row();';
				if	($goods_seq) foreach($goods_seq as $k => $seq){
					$removeGoods[]	= $seq;
					$this->goodsmodel->remove_tmp_goods($tmp_seq, $seq);
				}
			}else{
				openDialogAlert('선택된 상품이 없습니다.', 400, 170, 'parent', '');
				exit;
			}
		}
		$tmpData			= $result['tmpData'];
		$tmpData['goods']	= $result['goods'];

		// 물류관리일 경우 창고 및 로케이션 정보 추출
		if	($this->scmmodel->chkScmConfig(true)){
			if	(!$this->scm_cfg){
				if	($this->scmmodel->scm_cfg)	$this->scm_cfg		= $this->scmmodel->scm_cfg;
				else							$this->scm_cfg		= config_load('scm');
			}
			$warehouse				= $this->scmmodel->get_warehouse(array('orderby' => 'wh_name asc'));
			$wh_seq					= $warehouse[0]['wh_seq'];
			$whData['warehouse']	= $warehouse;
			if	($wh_seq > 0){
				$whData['location']	= $this->scmmodel->get_location(array('wh_seq' => $wh_seq));
			}
		}

		// goods controller로 그려야 하나 이미 데이터를 모두 가지고 있어서 process그냥 그림
		$filePath		= 'default/goods/_quick_regist_rowskin.html';
		$this->template->define(array('tpl'=>$filePath));
		$this->template->assign(array(
			'sellermode'		=> $sellermode,
			'tmp_seq'			=> $tmp_seq,
			'goods_seq'			=> $goods_seq,
			'removeGoods'		=> $removeGoods,
			'scm_cfg'			=> $this->scm_cfg,
			'tmpData'			=> $tmpData,
			'whData'			=> $whData,
			'popup_id'			=> $popup_id,
			'procJS'			=> $procJS,
		));
		$this->template->print_("tpl");
	}

	/// 변경 내용 즉시 저장
	public function tmp_save_cell_data(){
		$this->load->model('goodsmodel');
		$this->load->model('scmmodel');

		if	($this->input->post()) foreach($this->input->post() as $k => $val){
			if	(in_array($k, array('tmp_seq', 'goods_seq', 'option_seq', 'revision_seq'))){
				$$k		= $val;
			}else{
				$data[$k]	= $val;
			}
		}
		if		($revision_seq > 0){
			$this->scmmodel->tmp_save_cell_data($revision_seq, $data);
		}elseif	($option_seq > 0 && !isset($data['goods_name']) && !isset($data['goods_code'])){
			$this->goodsmodel->tmp_save_cell_data('option', $option_seq, $data);
		}elseif	($goods_seq > 0){
			$this->goodsmodel->tmp_save_cell_data('goods', $goods_seq, $data);
		}else{
			$this->goodsmodel->tmp_save_cell_data('tmp', $tmp_seq, $data);
		}
	}

	// 변경 내용 일괄 즉시 저장
	public function tmp_save_all_data(){
		$this->load->model('goodsmodel');
		$this->load->model('scmmodel');

		$aPostParams 		= $this->input->post();

		$type				= trim($aPostParams['type']);
		$goods_seq			= trim($aPostParams['goods_seq']);
		$stock_type			= trim($aPostParams['stock_type']);
		$scm_fld_array		= array('warehouse', 'locatgoods_img_sortion_w', 'location_l', 'location_h',
									'stock', 'badstock', 'supply_price');
		$call_model_name	= 'goodsmodel';
		$call_func_name		= 'tmp_save_all_option';
		if	($stock_type == 'scm'){
			if	($aPostParams) foreach($aPostParams as $k => $v){
				if	(in_array($k, $scm_fld_array)){
					$call_model_name	= 'scmmodel';
					$call_func_name		= 'tmp_save_all_revision';
					break;
				}
			}
		}
		unset($aPostParams['stock_type']);
		$this->$call_model_name->$call_func_name($aPostParams);
	}

	// 실제 상품으로 등록
	public function save_batch_regist(){
		$this->load->model('goodsmodel');

		$tmp_seq		= trim($this->input->post('tmp_seq'));
		$goods_seq		= $this->input->post('goods_seq');
		$procJS			= 'remove_option_list_row();';
		if	($tmp_seq > 0 && is_array($goods_seq) && count($goods_seq) > 0){
			$tmpData	= $this->goodsmodel->get_tmp_goods_data($tmp_seq);
			if	(!$tmp_seq || !$tmpData){
				echo '<script>parent.loadingStop();parent.fail_load_tmp_data();</script>';
				exit;
			}

			$this->goodsmodel->save_batch_regist($tmp_seq, $goods_seq);

			// goods controller로 그려야 하나 이미 데이터를 모두 가지고 있어서 process그냥 그림
			$filePath		= 'default/goods/_quick_regist_rowskin.html';
			$addResultMsg	= array('msg' => '상품이 등록되었습니다.', 'width' => 400, 'height' => 170);
			$this->template->define(array('tpl'=>$filePath));
			$this->template->assign(array(
				'tmp_seq'			=> $tmp_seq,
				'removeGoods'		=> $goods_seq,
				'procJS'			=> $procJS,
				'addResultMsg'		=> $addResultMsg,
			));
			$this->template->print_("tpl");
		}else{
			openDialogAlert('등록할 상품을 선택하세요.', 400, 170, 'parent', '');
			exit;
		}
	}

	# HS CODE 저장
	public function hscode_setting(){

		$this->load->model("multisupportmodel");

		// 기존 get parameter 유지용
		$get_hscode		= $this->input->post('get_hscode');
		$keyword		= $this->input->post('keyword');
		$search_type	= $this->input->post('search_type');

		$nation_info	= $this->multisupportmodel->getNationList();
		$nation_list	= array();
		foreach($nation_info as $key=>$data){
			if($data['nationKey'] == "KOR") $data['nationName'] = "대한민국";
			$nation_list[$data['nationKey']] = $data['nationName'];
		}

		$hscode_list = array();

		foreach(array_count_values($_POST['nation_key']) as $nation => $cnt){
			if($cnt > 1){
				$msg		= "동일한 수입국가(".$nation_list[$nation].")가 2개 이상 선택 되었습니다.";
				$callback	= "";
				openDialogAlert($msg,400,160,'parent',$callback);
				exit;
			}
		}
		foreach($_POST['nation_key'] as $key=>$nation){
			$row_idx = $_POST['hscode_row'][$key];
			foreach(array_count_values($_POST['export_nation_key'][$row_idx]) as $export_nation => $cnt2){
				if($nation == $export_nation){
					$msg		= "수입국가(".$nation_list[$nation].")와 동일한 수출국가(".$nation_list[$export_nation].")가 선택되었습니다.";
					$callback	= "";
					openDialogAlert($msg,400,160,'parent',$callback);
					exit;
				}
				if($cnt2 > 1){
					$msg		= "수입국가(".$nation_list[$nation].")에 대한 동일한 수출국가(".$nation_list[$export_nation].")가<br /> 2개 이상 선택 되었습니다.";
					$callback	= "";
					openDialogAlert($msg,450,160,'parent',$callback);
					exit;
				}
			}
		}
		foreach(array_count_values($_POST['hscode_nation']) as $code=>$cnt){
			if($cnt > 1){
				$msg		= "국가별 개별코드 [".$key."]가 중복 입력 되었습니다.";
				$callback	= "";
				openDialogAlert($msg,400,160,'parent',$callback);
				exit;
			}
		}

		$hscode_info = array();
		$hscode_info['hscode_seq']			= $_POST['hscode_seq'];
		$hscode_info['hscode_name']			= $_POST['hscode_name'];
		//$data['hscode_type_cont']			= $_POST['hscode_type_cont'];
		$hscode_info['hscode_common']		= $_POST['hscode_common'];

		foreach($_POST['hscode_row'] as $key => $row_idx){

			if($_POST['hscode_nation'][$key]){

				$tmp							= array();
				$tmp['nation_key']				= $_POST['nation_key'][$key];
				$tmp['hscode_nation']			= $_POST['hscode_nation'][$key];
				$tmp['export_nation_key']		= serialize($_POST['export_nation_key'][$row_idx]);
				$tmp['customs_tax']				= serialize($_POST['customs_tax'][$row_idx]);

				$hscode_info['hscode_items'][] = $tmp;
			}

		}
		$this->multisupportmodel->set_hscode($hscode_info);

		$msg		= "HS CODE가 등록 되었습니다.";
		$callback	= "parent.location.reload();";
		if	($get_hscode)	$callback	= "parent.location.replace('./hscode_setting?keyword={$keyword}&search_type={$search_type}');";
		openDialogAlert($msg,400,160,'parent',$callback);

	}

	# HS CODE 삭제
	public function hscode_delete(){

		# HS CODE 삭제 시 현재 연결된 상품에서 HSCODE 삭제.
		$hscode_seq = $this->input->post('hscode_seq');
		if($hscode_seq){
			$query = $this->db->where_in("hscode_seq",$hscode_seq)->get("fm_hscode_info");
			foreach ($query->result() as $row){
				$hscode_common[] = $row->hscode_common;
			}
			$this->db->where_in("hscode",$hscode_common)->update("fm_goods",array("hscode"=>""));
			$this->db->where_in('hscode_seq', $hscode_seq)->delete('fm_hscode_info');
			$this->db->where_in('hscode_seq', $hscode_seq)->delete('fm_hscode_info_item');

			$msg		= "삭제 되었습니다.";
			$callback	= "parent.location.reload();";
		}else{
			$msg		= "삭제할 HS CODE가 없습니다.";
			$callback	= "";
		}
		openDialogAlert($msg,400,160,'parent',$callback);

	}

	public function gete_hscode_common(){

		$this->load->model("multisupportmodel");
		
		$hscode_common = $this->input->get('hscode_common');
		if(trim($hscode_common) == ''){
			echo 'codenull';
		}else{
			echo $this->multisupportmodel->get_common_code($hscode_common);
		}

	}

	// 상품 노출 설정 정보 저장
	public function save_display_terms(){

		$whrParams['goods_seq']					= (int) $this->input->post('dst_goods_seq');
		$upParams['display_terms']				= $this->input->post('dst_set');
		$upParams['display_terms_begin']		= $this->input->post('display_terms_begin');
		$upParams['display_terms_end']			= $this->input->post('display_terms_end');
		$upParams['display_terms_type']			= $this->input->post('display_terms_type_tmp');
		$upParams['display_terms_text']			= $this->input->post('display_terms_text');
		$upParams['display_terms_color']		= $this->input->post('display_terms_color');
		$upParams['possible_shipping_date']		= $this->input->post('possible_shipping_date');
		$upParams['possible_shipping_text']		= $this->input->post('possible_shipping_text');
		$upParams['display_terms_before']		= $this->input->post('display_terms_before');
		$upParams['display_terms_after']		= $this->input->post('display_terms_after');

		// 필수값 체크
		if	(!$whrParams['goods_seq']){
			$callback	= "parent.closeGoodsDisplayTerms();";
			openDialogAlert('저장실패 : 잠시 후 다시 시도해 주세요.', 400, 170, 'parent', $callback);
			exit;
		}

		if	($upParams['display_terms'] == 'AUTO'){
			// validation
			$this->validation->set_rules('display_terms_begin', '자동노출 시작일','trim|required|max_length[10]|xss_clean');
			$this->validation->set_rules('display_terms_end', '자동노출 종료일','trim|required|max_length[10]|xss_clean');
			if	($upParams['display_terms_type'] == 'LAYAWAY'){
				$this->validation->set_rules('possible_shipping_date', '예약발송일','trim|required|max_length[10]|xss_clean');
			}
			if($this->validation->exec()===false){
				$err = $this->validation->error_array;
				$callback = "if(parent.document.getElementsByName('{$err['key']}')[0]) parent.document.getElementsByName('{$err['key']}')[0].focus();";
				openDialogAlert($err['value'],400,170,'parent',$callback);
				exit;
			}


			$display_terms_begin = strtotime($upParams['display_terms_begin']);
			$display_terms_end = strtotime($upParams['display_terms_end']);

			// 유효기간 시작일 전 노출여부에 따라 처리
			if ($display_terms_begin > time()) {
				$upParams['goods_view'] = ($upParams['display_terms_before'] == 'DISPLAY') ? 'look' : 'notLook' ;
			}

			// 유효기간 종료일 후 노출여부에 따라 처리
			if ($display_terms_end < time()) {
				$upParams['goods_view'] = ($upParams['display_terms_after'] == 'DISPLAY') ? 'look' : 'notLook' ;
			}
		}

		$this->goodsmodel->set_goods($upParams, $whrParams);

		$callback	= "parent.closeGoodsDisplayTerms('{$whrParams['goods_seq']}', '{$upParams['display_terms']}');";
		openDialogAlert('저장되었습니다.',400, 170, 'parent', $callback);
	}
	
	//자주쓰는 상품의 필수 옵션 삭제
	public function del_freq_option()
	{
		if(!$_POST['goods_seq']){
			return false;
		}
		
		if($_POST['type'] == 'sub'){
			$field = 'frequentlysub';
		} else if($_POST['type'] == 'opt'){
			$field = 'frequentlyopt';
		} else if($_POST['type'] == 'inp'){
			$field = 'frequentlyinp';
		}

		$this->db->where('goods_seq', $_POST['goods_seq']);
		$this->db->update('fm_goods', array($field => '0'));
		
		return true;
	}
	
	//자주쓰는 상품의 필수 옵션 갱신
	public function set_freq_option()
	{
		if ($this->input->get('package_yn')) {
			$package_yn = $this->input->get('package_yn');
		} else {
			$package_yn = 'n';
		}
		
		if ($this->input->get('type') == 'sub') {
			if (!$this->scm_cfg['use']) {
				$this->scm_cfg = config_load('scm');
			}
		
			if ($this->scm_cfg['use'] == 'Y') {
				$package_yn = 'y';
			} else {
				// 올인원 아니면 실제 상품 상관없이 추가옵션 가져오도록 수정
				$package_yn = '';
			}

			$res = $this->goodsmodel->frequentlygoods('sub', '', defined('SOCIALCPUSE'), $package_yn);
		} else {
			$res = $this->goodsmodel->frequentlygoods('opt', '', defined('SOCIALCPUSE'), $package_yn);
		}
		
		echo json_encode($res);
	}
	
	//자주쓰는 상품의 필수 옵션 갱신
	public function get_freq_paging()
	{
		$perpage = 10;
		
		$res = $this->goodsmodel->frequentlygoodsPaging($_POST['type'], '', defined('SOCIALCPUSE'), $_POST['packageyn'], $_POST['page'], $perpage);
		
		if($perpage > $res['total']){
			$_POST['page'] = 1;
			
			$res = $this->goodsmodel->frequentlygoodsPaging($_POST['type'], '', defined('SOCIALCPUSE'), $_POST['packageyn'], $_POST['page'], $perpage);
		} else {
			if(count($res['result']) <= 0){
				$_POST['page'] = $_POST['page'] - 1;
				
				$res = $this->goodsmodel->frequentlygoodsPaging($_POST['type'], '', defined('SOCIALCPUSE'), $_POST['packageyn'], $_POST['page'], $perpage);
			}
		}
		
		$res['paging'] = pagingtagjs($_POST['page'], $perpage, $res['total'], 'frequentlypaging([:PAGE:], \''.$_POST['type'].'\', \''.$_POST['packageyn'].'\', \''.$_POST['popupID'].'\')');
		
		if(empty($res['paging'])){
			$res['paging'] = '<p><a class="on red">1</a><p>';
		}
		
		echo json_encode($res);
	}
}

/* End of file category.php */
/* Location: ./app/controllers/admin/goods_process */
