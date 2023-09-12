<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');
require_once(APPPATH ."controllers/base/admin_base".EXT);

class page_manager_process extends admin_base {

	public function __construct() {
		parent::__construct();
		$this->load->model('pagemanagermodel');
	}

	// 서브페이지 통합 저장
	public function save_subpage(){
	    // 저장할 타입값 가져온 후 unset
	    $params			= $this->input->post();

	    $page_type		= $params['page_type'];
	    unset($params['page_type']);

		// 최소 한개 선택
	    if($page_type == 'event' && ($params['search_filter'][0] == 'event' || !$params['search_filter'])){
	        openDialogAlert('최소 한개의 필터를 선택하세요.',400,140,'parent',$callback);
	        exit;
	    }

	    // 상품정보 개선 스킨버전이 최신이 아닌경우 경고 :: 2019-05-13 pjw
	    $this->load->helper('design');
	    check_skin_version('version_20190510');

		// 미체크 필터,상태 삭제
	    if( preg_match('/tab=page_goods|tab=main|cmd=search_result|cmd=newproduct|cmd=bestproduct|cmd=bigdata_criteria|cmd=event/', $_SERVER['HTTP_REFERER']) ){
	        $aRemoveChkParams = array('search_filter','status');
	        foreach($aRemoveChkParams as $sKey){
	            if(!$params[$sKey]){
	                config_save($page_type, array($sKey => ''));
	            }
	        }
	    }	

		// 저장할 데이터 키 배열정의
	    $allow_column = array('link_url', 'banner', 'orderby', 'rank', 'search_filter', 'status', 'condition', 'goods_info_style', 'goods_info_image');
	    foreach($params as $key => $val){
	        // 넘어온 값의 키가 저장할 데이터 키목록에 없으면 건너뜀
	        if(!in_array($key, $allow_column)) continue;
	        $config_data = $this->pagemanagermodel->page_data_check($page_type, $key, $val);
	        config_save($page_type, array($key => $config_data));
		}
	
		
		if(preg_match("/^\/?data\/tmp/i",$params['bigdata_banner'])){
			if(!is_dir(ROOTPATH.'data/bigdata')){
				@mkdir(ROOTPATH.'data/bigdata');
				@chmod(ROOTPATH.'data/bigdata',0777);
			}
			$ext = explode(".",$params['bigdata_banner']);
			$ext = $ext[count($ext)-1];
			$bigdata_banner = "banner."."{$ext}";
			$new_path = "data/bigdata/{$bigdata_banner}";

			copy(ROOTPATH.$params['bigdata_banner'],ROOTPATH.$new_path);
			chmod(ROOTPATH.$new_path,0777);
			config_save($page_type, array('banner' => $bigdata_banner));
		}

		$callback = "parent.location.reload();";
	    openDialogAlert('저장되었습니다.',400,140,'parent',$callback);
	    exit;
	}

	// 배너 이미지 업로드
	public function save_banner_image(){
		$this->load->library('Upload');

		$params		= $this->input->post();
		$skinFolder = $params['folder'];

		/* 스킨 스크린샷 변경 */
		if (is_uploaded_file($_FILES['banner_image']['tmp_name'])) {
			$config = array();
			$upload_path = "data/".$skinFolder;

			// 폴더 없으면 추가
			if(!file_exists(ROOTPATH.$upload_path)){
				@mkdir(ROOTPATH.$upload_path);
			}

			@chmod(ROOTPATH.$upload_path, 0777);

			$file_ext = end(explode('.', $_FILES['banner_image']['name']));//확장자추출
			$config['upload_path']			= ROOTPATH.$upload_path;
			$config['allowed_types']		= 'jpg|gif|jpeg|png';
			$config['overwrite']			= TRUE;
			$config['file_name']			= "banner.".$file_ext;
			$this->upload->initialize($config);

			if ($this->upload->do_upload('banner_image')) {
				@chmod($config['upload_path'].'/'.$config['file_name'], 0777);

				$result = array(
					'result'	 => true,
					'image_path' => $config['file_name']
				);
			}else{
				$result = array(
					'result'	=> false,
					'msg'		=> "이미지는 gif, jpg, jpeg, png 만 업로드가 가능합니다."
				);
			}

			echo json_encode($result);
		}
	}

	// 슬라이드 배너 삭제
	public function delete_design_banner(){
		$mode		= $this->input->post('mode');
		$banner_seq	= $this->input->post('banner_seq');

		if($banner_seq){
			$this->load->helper('file');

			$banner_seqs = explode(",",$banner_seq);

			foreach($banner_seqs as $banner_seq){

				if(!$banner_seq) continue;

				$query = $this->db->query("select * from fm_design_banner where skin=? and banner_seq=?",array($this->designWorkingSkin,$banner_seq));
				$banner_data = $query->row_array();
				if(!$banner_data) continue;

				if($banner_data['skin']){
					$banner_image_path = ROOTPATH."data/skin/{$banner_data['skin']}/images/banner/{$banner_seq}";

					if(is_dir($banner_image_path)){
						if(delete_files($banner_image_path,true)){
							@rmdir($banner_image_path);
						}
					}
				}

				$this->db->query("delete from fm_design_banner where skin=? and banner_seq=?",array($this->designWorkingSkin,$banner_seq));
				$this->db->query("delete from fm_design_banner_item where skin=? and banner_seq=?",array($this->designWorkingSkin,$banner_seq));
			}

			$result = array('result' => true, 'msg' => '배너가 삭제되었습니다.');
		}else{
			$result = array('result' => false, 'msg' => '정상적인 파라미터가 아닙니다.');
		}

		if($mode == 'ajax'){
			echo json_encode($result);
		}else{
			openDialogAlert($result['msg'],400,140,'parent',"parent.document.location.reload();");
		}
	}

	// 접속제한 리스트 수정
	public function modify_access_limit(){
		$this->load->library('validation');

		$this->validation->set_data($this->input->get());
		$this->validation->set_rules('page_type', '페이지 타입', 'trim|required|alpha|xss_clean'); 
		if ($this->validation->exec() === false) {
			echo $this->validation->error_array['value'];
			exit;
		}

		$mode			= $this->input->get('mode');
		$page_type		= $this->input->get('page_type');
		$target_code	= $this->input->get('chk_code');
		if($page_type && count($target_code) > 0){

			if($mode == 'delete'){
				$updateData = array(
					'catalog_allow'			=> 'show',
					'catalog_allow_sdate'	=> null,
					'catalog_allow_edate'	=> null
				);
				$memberGroup	= null;
				$userType		= null;
			}else{
				$updateData = array(
					'catalog_allow'			=> $this->input->get('catalog_allow'),
					'catalog_allow_sdate'	=> $this->input->get('catalog_allow_sdate'),
					'catalog_allow_edate'	=> $this->input->get('catalog_allow_edate')
				);
				$memberGroup	= $this->input->get('memberGroup');
				$userType		= $this->input->get('userType');
			}

			if($page_type != 'location')	$column_nm = 'category';
			else							$column_nm = $page_type;

			$this->db->where_in($column_nm.'_code', $target_code);
			$this->db->update('fm_'.$page_type, $updateData);

			$this->db->where_in($column_nm.'_code', $target_code);
			$this->db->delete('fm_'.$page_type.'_group');

			if(count($memberGroup) > 0 || count($userType) > 0) foreach($target_code as $code){
				if(isset($memberGroup)) foreach( $memberGroup as $group ){
					$query = "insert into `fm_".$page_type."_group` (`".$column_nm."_code`,`group_seq`, `user_type`,`regist_date`) values (?,?,null,now())";
					$this->db->query( $query, array($code,$group));
				}

				if(isset($userType)) foreach($userType as $txt){
					$query = "insert into `fm_".$page_type."_group` (`".$column_nm."_code`,`group_seq`, `user_type`,`regist_date`) values (?,null,?,now())";
					$this->db->query( $query, array($code,$txt));
				}
			}
		}else{
			// 저장할 내용 없음.
		}
	}

	// 배너관리 리스트 수정
	public function modify_banner(){
		$mode			= $this->input->post('mode');
		$page_type		= $this->input->post('page_type');
		$target_code	= $this->input->post('chk_code');

		if($page_type != 'location')	$column_nm = 'category';
		else							$column_nm = $page_type;

		// CI post로 받아올때 xss 필터에 걸려 모든 스타일을 지워버려서 $_POST로 수정
		$top_html	= $_POST['top_html'];
		$top_html	= (in_array(strtolower($top_html),array("<p>&nbsp;</p>","<p><br></p>"))) ? '' : $top_html;

		/* 이미지호스팅 기본 값 세팅 */
		$this->load->library('imagehostinglibrary');
		$imagestore_type = [];
		$imagestore_type['imagestore_division'] = "goods_page_view";
		$imagestore_type['imagestore_item'] = "category_banner";

		if($page_type && count($target_code) > 0){
			if($mode == 'delete'){
				$top_html	= '';
			}else{
				adjustEditorImages($top_html);

				/* 이미지호스팅 사용 여부 체크 및 업로드 */
				$isImagehosting = $this->imagehostinglibrary->isImagehostingUse($imagestore_type);
				if($isImagehosting == true){
					$result_imagehosting = $this->imagehostinglibrary->uploadEditorImage(['category_banner' => $top_html], $imagestore_type);
					$top_html = $result_imagehosting['category_banner']['contents'];
					unset($result_imagehosting['category_banner']['contents']);
				}
			}			
			foreach($target_code as $code){
				$this->db->where($column_nm.'_code', $code);
				$this->db->update('fm_'.$page_type, ['top_html'=>$top_html,'update_date'=>date('Y-m-d H:i:s')]);
			}

		}else{
			// 저장할 내용 없음.
		}
		
		// 이미지호스팅 업로드 결과
		if($result_imagehosting){
			$resultMessage = $this->imagehostinglibrary->message($result_imagehosting);
			echo json_encode($resultMessage);
		}else{
			echo true;
		}		
	}

	// 추천상품관리 리스트 삭제
	public function modify_recommend(){
		$mode			= $this->input->post('mode');
		$page_type		= $this->input->post('page_type');
		$target_code	= $this->input->post('chk_code');

		if($page_type != 'location')	$column_nm = 'category';
		else							$column_nm = $page_type;

		if($page_type && count($target_code) > 0 && $mode == 'delete'){
			$modelName = $page_type.'model';
			$this->load->model($modelName);
			foreach($target_code as $code){
				$target_info	= $this->$modelName->{'get_'.$page_type.'_data'}($code);
				$now_display_seq	= $target_info['recommend_display_seq'];
				$now_m_display_seq	= $target_info['m_recommend_display_seq'];

				$update_params['update_date']	= date('Y-m-d H:i:s');
				if($this->operation_type == 'light'){
					$update_params['recommend_display_light_seq']	= '';
				}else{
					$update_params['recommend_display_seq']			= '';
					$update_params['m_recommend_display_seq']		= '';
				}

				$this->db->where($column_nm.'_code', $code);
				$this->db->update('fm_'.$page_type, $update_params);
				$this->db->where_in('display_seq', array($now_display_seq,$now_m_display_seq));
				$this->db->delete('fm_design_display');
				$this->db->where_in('display_seq', array($now_display_seq,$now_m_display_seq));
				$this->db->delete('fm_design_display_tab');
				$this->db->where_in('display_seq', array($now_display_seq,$now_m_display_seq));
				$this->db->delete('fm_design_display_tab_item');
			}
		}else{
			$callback = "";
			openDialogAlert("삭제에 실패하였습니다.",400,140,'parent',$callback);
			exit;
		}
	}

	// 전체 네비게이션 수정
	public function modify_navigation(){
		
		$this->load->library('validation');

		$params			= $this->input->post();
		$page_type		= $params['page_type'];  // 페이지 타입
		$target_code	= $params['chk_code'];   // 수정 될 코드 목록
		$style_type		= $params['style_type']; // 이미지, 텍스트 선택값
		$mode			= $params['mode'];		 // 수정 모드

		if($page_type != 'location')	$column_nm = 'category';
		else							$column_nm = $page_type;

		if($page_type && count($target_code) > 0){
			$root_path	= substr(ROOTPATH,0,-1);

			// 업데이트 할 정보 세팅
			$data = array(
				'node_type'			=> $style_type,
				'update_date'			=> date('Y-m-d H:i:s')
			);

			// 삭제
			if($mode == "delete") {
				$modelName = $page_type.'model';
				$this->load->model($modelName);

				$data = array(
					'node_type'			=> '',
					'node_image_normal' => '',
					'node_image_over'	=> '',
					'node_text_normal'	=> '',
					'node_text_over'	=> '',
					'update_date'			=> date('Y-m-d H:i:s')
				);

				$fields = $column_nm.'_code, node_image_normal, node_image_over';
				$codesInfo = $this->$modelName->{'getCodeInfo'}($target_code,$fields);

				foreach($codesInfo as $code) {
					if( $code['node_image_normal'] ) {
						$img_path	= $code['node_image_normal'];
						@unlink($root_path.$img_path);
					}
					if( $code['node_image_over'] ) {
						$img_path	= $code['node_image_over'];
						@unlink($root_path.$img_path);
					}
				}

				// 데이터 저장
				$this->db->where_in($column_nm.'_code', $target_code);
				$this->db->update('fm_'.$page_type, $data);
			} else {

				if($style_type == 'image') {

					// 이미지 타입, 경로 세팅
					$image_types 	= array('normal','over');
					$upload_path 	= '/data/'.$page_type;

					foreach($image_types as $image_type) {
						$this->validation->set_rules('image_type_'.$image_type, '파일 선택','trim|required|xss_clean');
					}

					if($this->validation->exec()===false ){
						$err = $this->validation->error_array;
						echo json_encode(array('msg'=>$err['value']));
						exit;
					}

					// 폴더 없으면 추가
					if(!file_exists(ROOTPATH.$upload_path)){
						@mkdir(ROOTPATH.$upload_path);
					}

					@chmod(ROOTPATH.$upload_path, 0777);

					foreach($target_code as $code) {
						foreach($image_types as $image_type) {
							$tmp_path 		= $params['image_type_'.$image_type];
							$tmpExe 		= explode(".", $tmp_path);

							// 이미지 저장
							$file_name	= $code.'_'.$image_type.'.'.end($tmpExe);
							$img_path	= $upload_path.'/'.$file_name;
							unlink($root_path.$img_path);
							copy($root_path.$tmp_path, $root_path.$img_path);
							chmod($root_path.$img_path, 0777);

							$data['node_image_'.$image_type] = $img_path;
						}
						// 데이터 저장
						$this->db->where($column_nm.'_code', $code);
						$this->db->update('fm_'.$page_type, $data);
					}
					foreach($image_types as $image_type) {
						$tmp_path 		= $params['image_type_'.$image_type];
						unlink($root_path.$tmp_path);
					}
				} else {

					// 텍스트 기본, 마우스오버 세팅
					$text_normal					= $params['text_normal'];
					$text_over						= $params['text_over'];
					$data['node_text_normal']	= $text_normal;
					$data['node_text_over']		= $text_over;


					// 데이터 저장
					$this->db->where_in($column_nm.'_code', $target_code);
					$this->db->update('fm_'.$page_type, $data);
				}
			}
		}else{
			$callback = "";
			openDialogAlert("잘못 된 접근입니다.",400,140,'parent',$callback);
			exit;
		}

	}

	// 네비게이션 배너 설정
	public function extra_navigation(){
		$mode			= $this->input->post('mode');
		$page_type		= $this->input->post('page_type');
		$code			= $this->input->post('code');
		$chk_code		= $this->input->post('chk_code');

		if($page_type != 'location')	$column_nm = 'category';
		else							$column_nm = $page_type;

		if($page_type){
			if($mode == 'delete'){
				$node_banner	= '';
			}else{
				// CI post로 받아올때 xss 필터에 걸려 모든 스타일을 지워버려서 $_POST로 수정 :: 2019-03-13 pjw
				$node_banner	= $_POST['node_banner'];
				$node_banner	= (in_array(strtolower($node_banner),array("<p>&nbsp;</p>","<p><br></p>"))) ? '' : $node_banner;
				adjustEditorImages($node_banner);

				/* 이미지호스팅 기본 값 세팅 */
				$this->load->library('imagehostinglibrary');
				$imagestore_type = [];
				$imagestore_type['imagestore_division'] = "goods_page_view";
				$imagestore_type['imagestore_item'] = "category_navigation";

				/* 이미지호스팅 사용 여부 체크 및 업로드 */
				$isImagehosting = $this->imagehostinglibrary->isImagehostingUse($imagestore_type);
				if($isImagehosting == true){
					$result_imagehosting = $this->imagehostinglibrary->uploadEditorImage(['category_navigation' => $node_banner], $imagestore_type);
					$node_banner = $result_imagehosting['category_navigation']['contents'];
					unset($result_imagehosting['category_navigation']['contents']);
				}
			}

			if(!empty($code))		$this->db->where($column_nm.'_code', $code);
			if(!empty($chk_code))	$this->db->where_in($column_nm.'_code', $chk_code);
			$this->db->where('level', '2');
			$this->db->update('fm_'.$page_type, ['node_banner'=>$node_banner,'update_date'=>date('Y-m-d H:i:s')]);

			// 이미지호스팅 업로드 결과
			if($result_imagehosting){
				$resultMessage = $this->imagehostinglibrary->message($result_imagehosting);
				echo json_encode($resultMessage);
			}

		}else{
			$callback = "";
			openDialogAlert("잘못 된 접근입니다.",400,140,'parent',$callback);
			exit;
		}
	}

	// 브랜드 이미지 업로드 :: 2018-12-24 lwh
	public function upload_brand_img(){
		$this->load->library('Upload');

		// 파일 저장
		if($_FILES){
			$config['upload_path']			= ROOTPATH.'/data/tmp';
			$config['allowed_types']		= 'jpg|gif|jpeg|png';
			$config['overwrite']			= TRUE;

			foreach($_FILES as $file_nm => $fobj){
				$tmp_file_name					= $file_nm . '_' . date('YmdHis');
				$config['file_name']			= $tmp_file_name;
				$this->upload->initialize($config);
				$category_code = str_replace('brand_image_', '', $file_nm);
				if ($this->upload->do_upload($file_nm)) {
					$ext	= explode('.', $fobj['name']);
					$len	= count($ext);
					@chmod($config['upload_path'] . '/' . $tmp_file_name . '.' . $ext[$len-1], 0777);

					$return['cate_code']	= $category_code;
					$return['file_url']		= '/data/tmp/' . $tmp_file_name . '.' . $ext[$len-1];

					echo json_encode($return);
				}else{
					$return['cate_code']	= 'err';

					echo json_encode($return);
				}
			}
		}
	}

	// 브랜드 이미지 저장 :: 2018-12-24 lwh
	public function modify_brand_image(){
		$this->load->model('brandmodel');
		$return['cnt'] = 0;

		$brand_image = $this->input->post('brand_image');

		if($brand_image && count($brand_image) > 0) foreach($brand_image as $category_code => $tmp_file){
			$root_path	= substr(ROOTPATH,0,-1);
			$tmp_ext	= explode('.',$tmp_file);
			$tmp_cnt	= count($tmp_ext);

			// 새로 저장된 파일만 저장
			if(is_file($root_path.$tmp_file) && substr_count($tmp_file,'/data/tmp/') > 0){
				// 이미지 저장
				$file_name	= 'brand_image_' . $category_code;
				$img_path	= '/data/brand/'.$file_name.'.'.$tmp_ext[$tmp_cnt-1];
				unlink($root_path.$img_path);
				rename($root_path.$tmp_file, $root_path.$img_path);
				chmod($root_path.$img_path, 0777);
				unlink($root_path.$tmp_file);

				// DB 저장
				$params['brand_image'] = $img_path;
				$this->brandmodel->set_brand_info($category_code, $params);

				$return['cnt']++;
			}else if($tmp_file == 'delete'){
				$this->brandmodel->del_brand_info($category_code);

				$return['cnt']++;
			}
		}

		echo json_encode($return);
	}

	// 베스트 브랜드 설정
	public function modify_best_brand(){
		$params			= $this->input->post();
		$best_yn		= ($params['best_yn'] == 'Y') ? 'N' : 'Y';
		$target_code	= $params['target_code'];  // 업데이트 대상 코드

		if($best_yn && $target_code){
			$this->db->where("category_code = '".$target_code."'");
			$this->db->update('fm_brand', array('best'=>$best_yn, 'update_date'=>date('Y-m-d H:i:s')));

			$result = array(
				'state' => true,
				'msg'	=> '업데이트 완료되었습니다.'
			);
		}else{
			$result = array(
				'state' => false,
				'msg'	=> '잘못된 접근입니다.'
			);
		}

		echo json_encode($result);
		exit;
	}

	// 베스트 브랜드 아이콘 저장
	public function modify_best_icon(){
		$tmp_file	= $this->input->post('tmp_file');
		$tmp_ext	= explode('.',$tmp_file);

		// 이미지 저장
		$file_name	= 'brand_main_' . date('YmdHis');
		$img_path	= '/data/brand/'.$file_name.'.'.$tmp_ext[1];
		$root_path	= substr(ROOTPATH,0,-1);
		rename($root_path.$tmp_file, $root_path.$img_path);
		config_save('brand_main', array('best_icon'=>$img_path));

		$result = array('img_path' => $img_path);

		echo json_encode($result);
	}

	// 네비게이션 노출 설정
	public function modify_hide_navigation(){
		$params			= $this->input->post();
		$page_type		= $params['page_type'];	   // 페이지 타입
		$target_code	= $params['target_code'];  // 업데이트 대상 코드
		$next			= !empty($params['next']) ? $params['next'] : '0';		   // 노출값

		if($page_type != 'location')	$column_nm = 'category';
		else							$column_nm = $page_type;

		if($page_type && $target_code){
			$this->db->where($column_nm."_code like '".$target_code."%'");
			$this->db->update('fm_'.$page_type, array('hide'=>$next,'hide_in_navigation'=>$next,'update_date'=>date('Y-m-d H:i:s')));

			$result = array(
				'state' => true,
				'msg'	=> '업데이트 완료되었습니다.'
			);
		}else{
			$result = array(
				'state' => false,
				'msg'	=> '잘못된 접근입니다.'
			);
		}

		echo json_encode($result);
		exit;
	}

	// 전체 네비게이션 수정
	public function modify_all_navigation(){

		$this->load->library('validation');

	    $params			= $this->input->post();
	    $page_type		= $params['page_type'];  // 페이지 타입
	    $target_code	= $params['chk_code'];   // 수정 될 코드 목록
	    $style_type		= $params['style_type']; // 이미지, 텍스트 선택값
	    $mode			= $params['mode'];		 // 수정 모드

	    if($page_type != 'location')	$column_nm = 'category';
	    else							$column_nm = $page_type;

	    if($page_type && count($target_code) > 0){
	        $root_path	= substr(ROOTPATH,0,-1);

	        // 업데이트 할 정보 세팅
	        $data = array(
	            'node_gnb_type'			=> $style_type,
	            'update_date'			=> date('Y-m-d H:i:s')
	        );

	        // 삭제
	        if($mode == "delete") {
	            $modelName = $page_type.'model';
	            $this->load->model($modelName);

	            $data = array(
	                'node_gnb_type'			=> '',
	                'node_gnb_image_normal' => '',
	                'node_gnb_image_over'	=> '',
	                'node_gnb_text_normal'	=> '',
	                'node_gnb_text_over'	=> '',
	                'update_date'			=> date('Y-m-d H:i:s')
	            );

	            $fields = $column_nm.'_code, node_gnb_image_normal, node_gnb_image_over';
	            $codesInfo = $this->$modelName->{'getCodeInfo'}($target_code,$fields);

	            foreach($codesInfo as $code) {
	                if( $code['node_gnb_image_normal'] ) {
	                    $img_path	= $code['node_gnb_image_normal'];
	                    @unlink($root_path.$img_path);
	                }
	                if( $code['node_gnb_image_over'] ) {
	                    $img_path	= $code['node_gnb_image_over'];
	                    @unlink($root_path.$img_path);
	                }
	            }

	            // 데이터 저장
	            $this->db->where_in($column_nm.'_code', $target_code);
	            $this->db->update('fm_'.$page_type, $data);
	        } else {

	            if($style_type == 'image') {

					// 이미지 타입, 경로 세팅
					$image_types 	= array('normal','over');
					$upload_path 	= '/data/'.$page_type;

					foreach($image_types as $image_type) {
						$this->validation->set_rules('image_type_'.$image_type, '파일 선택','trim|required|xss_clean');
					}

					if($this->validation->exec()===false ){
						$err = $this->validation->error_array;
						echo json_encode(array('msg'=>$err['value']));
						exit;
					}

	                // 폴더 없으면 추가
	                if(!file_exists(ROOTPATH.$upload_path)){
	                    @mkdir(ROOTPATH.$upload_path);
	                }

	                @chmod(ROOTPATH.$upload_path, 0777);
					
					// 이미지호스팅 업로드 세팅 - 전체 네비게이션
					$this->load->library('imagehostinglibrary');
					$imagestore_type = array();
					$imagestore_type['imagestore_division'] = "goods_page_view";
					$imagestore_type['imagestore_item'] = "all_navigation";


	                foreach($target_code as $code) {
						foreach($image_types as $image_type) {
							$tmp_path 		= $params['image_type_'.$image_type];
							$tmpExe 		= explode(".", $tmp_path);

							 // 이미지 저장
							 $file_name	= $code.'_gnb_'.$image_type.'.'.end($tmpExe);
							 $img_path	= $upload_path.'/'.$file_name;
							 unlink($root_path.$img_path);
							 copy($root_path.$tmp_path, $root_path.$img_path);
							 chmod($root_path.$img_path, 0777);
	 
							 $data['node_gnb_image_'.$image_type] = $img_path;
						}

	                    // 데이터 저장
	                    $this->db->where($column_nm.'_code', $code);
	                    $this->db->update('fm_'.$page_type, $data);

						/* 이미지호스팅 이미지 업로드 */
						if($data['node_gnb_image_normal'] || $data['node_gnb_image_over']){
							$params_imagehosting = [];
							$params_imagehosting['imagestore_type'] = $imagestore_type;
							$params_imagehosting['upload_images'] = [$data['node_gnb_image_normal'], $data['node_gnb_image_over']];
							$params_imagehosting['table_where'] = $column_nm . "_code = '{$code}' ";
							$params_imagehosting['page_type'] = $page_type;
							if($this->imagehostinglibrary->uploadImage($params_imagehosting)){
								$result_imagehosting[$imagestore_type['imagestore_item']][] = $this->imagehostinglibrary->uploadImage($params_imagehosting);
							}
						}
					}
					foreach($image_types as $image_type) {
						$tmp_path 		= $params['image_type_'.$image_type];
						unlink($root_path.$tmp_path);
					}
	            } else {

	                // 텍스트 기본, 마우스오버 세팅
	                $text_normal					= $params['text_normal'];
	                $text_over						= $params['text_over'];
	                $data['node_gnb_text_normal']	= $text_normal;
	                $data['node_gnb_text_over']		= $text_over;


	                // 데이터 저장
	                $this->db->where_in($column_nm.'_code', $target_code);
	                $this->db->update('fm_'.$page_type, $data);
	            }
	        }

			// 이미지호스팅 업로드 결과
			if($result_imagehosting){
				$resultMessage = $this->imagehostinglibrary->message($result_imagehosting);
				echo json_encode($resultMessage);
			}
			
	    }else{
	        $callback = "";
	        openDialogAlert("잘못 된 접근입니다.",400,140,'parent',$callback);
	        exit;
	    }

	}

	// 전체 네비게이션 배너 설정
	public function extra_all_navigation(){
		$this->load->library('imagehostinglibrary');
		$mode			= $this->input->post('mode');
		$page_type		= $this->input->post('page_type');

		if($page_type != 'location')	$column_nm = 'category';
		else							$column_nm = $page_type;

		if($page_type){
			if($mode == 'delete'){
				$node_gnb_banner	= '';
			}else{
				// CI post로 받아올때 xss 필터에 걸려 모든 스타일을 지워버려서 $_POST로 수정 :: 2019-03-13 pjw
				$node_gnb_banner	= $_POST['node_gnb_banner'];
				$node_gnb_banner	= (in_array(strtolower($node_gnb_banner),array("<p>&nbsp;</p>","<p><br></p>"))) ? '' : $node_gnb_banner;
				adjustEditorImages($node_gnb_banner);

				/* 이미지호스팅 기본 값 세팅 */
				$imagestore_type = [];
				$imagestore_type['imagestore_division'] = "goods_page_view";
				$imagestore_type['imagestore_item'] = "all_navigation";

				/* 이미지호스팅 사용 여부 체크 및 업로드 */
				$isImagehosting = $this->imagehostinglibrary->isImagehostingUse($imagestore_type);
				if($isImagehosting == true){
					$result_imagehosting = $this->imagehostinglibrary->uploadEditorImage(['all_navigation' => $node_gnb_banner], $imagestore_type);
					$node_gnb_banner = $result_imagehosting['all_navigation']['contents'];
					unset($result_imagehosting['all_navigation']['contents']);
				}
			}

			$this->db->where('level', '2');
			$this->db->update('fm_'.$page_type, array('node_gnb_banner'=>$node_gnb_banner,'update_date'=>date('Y-m-d H:i:s')));

			// 이미지호스팅 업로드 결과
			if($result_imagehosting){
				$resultMessage = $this->imagehostinglibrary->message($result_imagehosting);
				echo json_encode($resultMessage);
			}
			
		}else{
			$callback = "";
			openDialogAlert("잘못 된 접근입니다.",400,140,'parent',$callback);
			exit;
		}
	}

	// 전체 네비게이션 노출 설정
	public function modify_hide_all_navigation(){
		$params			= $this->input->post();
		$page_type		= $params['page_type'];	   // 페이지 타입
		$target_code	= $params['target_code'];  // 업데이트 할 코드
		$next			= !empty($params['next']) ? $params['next'] : '0';		   // 노출값

		if($page_type != 'location')	$column_nm = 'category';
		else							$column_nm = $page_type;

		if($page_type && $target_code){
			$this->db->where($column_nm."_code like '".$target_code."%'");
			$this->db->update('fm_'.$page_type, array('hide_in_gnb'=>$next,'update_date'=>date('Y-m-d H:i:s')));

			$result = array(
				'state' => true,
				'msg'	=> '업데이트 완료되었습니다.'
			);
		}else{
			$result = array(
				'state' => false,
				'msg'	=> '잘못된 접근입니다.'
			);
		}

		echo json_encode($result);
		exit;
	}

	// 페이지 상품리스트 검색 필터 설정
	public function extra_page_goods_setting(){
		$this->load->helper('design');

		$page_type					= $this->input->post('page_type');
		$search_use					= $this->input->post('search_use');
		$navigation_depth			= $this->input->post('navigation_depth');
		$navigation_count_w			= $this->input->post('navigation_'.$navigation_depth.'_w');
		$naviation_sub_w			= $this->input->post('naviation_sub_w');

		if($search_use == 'Y'){
			if($page_type == 'category')	$colname = 'brand';
			else							$colname = 'category';

			skin_configuration_save($this->designWorkingSkin,$page_type."_navigation_use",$search_use);
			skin_configuration_save($this->designWorkingSkin,$page_type."_navigation_type",$navigation_depth);
			skin_configuration_save($this->designWorkingSkin,$page_type."_navigation_count_w",implode("|",$navigation_count_w));
			skin_configuration_save($this->designWorkingSkin,$page_type."_navigation_count_".$navigation_depth,implode("|",$navigation_count_w));
			skin_configuration_save($this->designWorkingSkin,$page_type."_navigation_".$colname."_count_w",$naviation_sub_w);
			$resmsg = '설정이 저장되었습니다.';

		}else{
			skin_configuration_save($this->designWorkingSkin,$page_type."_navigation_use",'N');
			$resmsg = '사용여부가 저장되었습니다.';
		}

		// 카테고리 데이터에 검색여부 업데이트 추가 (기존에는 카테고리테이블에서 읽어와서 스킨수정이 불가능하므로 업데이트를 해줌) :: 2019-03-11 pjw
		$this->db->update('fm_'.$page_type, array('search_use'=>strtolower($search_use), 'update_date'=>date('Y-m-d H:i:s')));

		$resultCallback = "parent.ajax_main_body_layer();parent.closeDialog('setCtrlLayer');";
		openDialogAlert($resmsg,400,140,'parent',$resultCallback);
		exit;
	}
}

/* End of file page_manager_process.php */
/* Location: ./app/controllers/admin/page_manager_process.php */