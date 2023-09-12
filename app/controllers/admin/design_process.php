<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');
require_once(APPPATH ."controllers/base/admin_base".EXT);

use App\libraries\Design\Skin\SkinRename;
use App\libraries\Design\Skin\SkinNameValidationTrait;

class design_process extends admin_base {

	use SkinNameValidationTrait;

	public function __construct() {
		parent::__construct();
		$this->load->library('validation');
		$this->load->library('Upload');
		$this->load->helper('design');
		$this->load->helper('javascript');
		$this->load->model('layout');

		/* 루트폴더 정의 */
		$this->dataPath = 'data';
		define('__DESIGNIS__',true);
	}

	/* 업로드 경로 체크 */
	public function _datapath_check($path){
		$sUploadDir = ROOTPATH . $this->dataPath . '/';
		$sRealpath = realpath($path);
		if (strpos($sRealpath, $sUploadDir) === false) {
			return false;
		} else {
			return true;
		}
	}

	/* 레이아웃설정 저장 */
	public function layout()
	{

		if(!isset($_POST['mode']))					$_POST['mode']='';
		if(!isset($_POST['tpl_desc']))				$_POST['tpl_desc']='';
		if(!isset($_POST['width']))					$_POST['width']='';
		if(!isset($_POST['body_width']))			$_POST['body_width']='';
		if(!isset($_POST['width_sign']))			$_POST['width_sign']='';
		if(!isset($_POST['body_width_sign']))		$_POST['body_width_sign']='';
		if(!isset($_POST['align']))					$_POST['align']='';
		if(!isset($_POST['font']))					$_POST['font']='';
		if(!isset($_POST['scrollbarColor']))		$_POST['scrollbarColor']='';
		if(!isset($_POST['backgroundColor']))		$_POST['backgroundColor']='';
		if(!isset($_POST['backgroundImage']))		$_POST['backgroundImage']='';
		if(!isset($_POST['backgroundRepeat']))		$_POST['backgroundRepeat']='';
		if(!isset($_POST['backgroundPosition']))	$_POST['backgroundPosition']='';
		if(!isset($_POST['bodyBackgroundColor']))	$_POST['bodyBackgroundColor']='';
		if(!isset($_POST['bodyBackgroundImage']))	$_POST['bodyBackgroundImage']='';
		if(!isset($_POST['layoutHeaderChk']))		$_POST['layoutHeaderChk']='';
		if(!isset($_POST['layoutHeader']))			$_POST['layoutHeader']='';
		if(!isset($_POST['layoutTopBarChk']))		$_POST['layoutTopBarChk']='';
		if(!isset($_POST['layoutTopBar']))			$_POST['layoutTopBar']='';
		if(!isset($_POST['layoutMainTopBarChk']))	$_POST['layoutMainTopBarChk']='';
		if(!isset($_POST['layoutMainTopBar']))		$_POST['layoutMainTopBar']='';
		if(!isset($_POST['layoutSideChk']))			$_POST['layoutSideChk']='';
		if(!isset($_POST['layoutSide']))			$_POST['layoutSide']='';
		if(!isset($_POST['layoutSideLocation']))	$_POST['layoutSideLocation']='';
		if(!isset($_POST['layoutFooterChk']))		$_POST['layoutFooterChk']='';
		if(!isset($_POST['layoutFooter']))			$_POST['layoutFooter']='';
		if(!isset($_POST['layoutScrollLeftChk']))	$_POST['layoutScrollLeftChk']='';
		if(!isset($_POST['layoutScrollLeft']))		$_POST['layoutScrollLeft']='';
		if(!isset($_POST['layoutScrollRightChk']))	$_POST['layoutScrollRightChk']='';
		if(!isset($_POST['layoutScrollRight']))		$_POST['layoutScrollRight']='';
		if(!isset($_POST['apply_type']))			$_POST['apply_type']='';

		if($_POST['mode']=='create'){
			$this->validation->set_rules('tpl_folder', '디렉토리','trim|required|max_length[30]|xss_clean');
			$this->validation->set_rules('tpl_file_name', '파일명','trim|required|max_length[30]|xss_clean');
			$this->validation->set_rules('tpl_file_ext', '파일확장자','trim|required|max_length[30]|xss_clean');
			$this->validation->set_rules('tpl_file_name_chk', '파일명 중복확인','trim|required|max_length[1]|xss_clean');

		}

		$this->validation->set_rules('tpl_desc', '페이지 설명','trim|required|max_length[100]|xss_clean');

		if($this->validation->exec()===false){
			$err = $this->validation->error_array;
			$callback = "if(parent.document.getElementsByName('{$err['key']}')[0]) parent.document.getElementsByName('{$err['key']}')[0].focus();";
			openDialogAlert($err['value'],400,140,'parent',$callback);
			exit;
		}

		/* 배경이미지 업로드시 */
		if (is_uploaded_file($_FILES['backgroundImage']['tmp_name'])) {
			$upload_path = "data/skin/".$this->designWorkingSkin."/images/design";
			$config['upload_path']		= ROOTPATH.$upload_path;
			$file_ext = end(explode('.', $_FILES['backgroundImage']['name']));//확장자추출
			$config['allowed_types']	= 'jpg|gif|jpeg|png';
			$config['overwrite']			= TRUE;
			$config['file_name']			= "background_image".substr(microtime(), 2, 6).'.'.$file_ext;
			$this->upload->initialize($config);

			if ($this->upload->do_upload('backgroundImage')) {
				@chmod($config['upload_path'].$config['file_name'], 0777);
				$_POST['backgroundImage'] = $config['file_name'];
			}else{
				$callback = "";
				openDialogAlert("gif, jpg,jpeg, png 만 가능합니다.",400,140,'parent',$callback);
				exit;
			}
		}

		/* 본문 배경이미지 업로드시 */
		if (is_uploaded_file($_FILES['bodyBackgroundImage']['tmp_name'])) {
			$upload_path = "data/skin/".$this->designWorkingSkin."/images/design";
			$config['upload_path']		= ROOTPATH.$upload_path;
			$file_ext = end(explode('.', $_FILES['bodyBackgroundImage']['name']));//확장자추출
			$config['allowed_types']	= 'jpg|gif|jpeg|png';
			$config['overwrite']			= TRUE;
			$config['file_name']			= "body_background_image".substr(microtime(), 2, 6).'.'.$file_ext;
			$this->upload->initialize($config);

			if ($this->upload->do_upload('bodyBackgroundImage')) {
				@chmod($config['upload_path'].$config['file_name'], 0777);
				$_POST['bodyBackgroundImage'] = $config['file_name'];
			}else{
				$callback = "";
				openDialogAlert("gif, jpg,jpeg, png 만 가능합니다.",400,140,'parent',$callback);
				exit;
			}
		}

		$arrLayoutBasic = layout_config_load($this->designWorkingSkin,'basic');

		if($_POST['mode']=='create'){
			$tpl_path = $_POST['tpl_folder']."/".$_POST['tpl_file_name'].".".$_POST['tpl_file_ext'];
			$data['tpl_page']	= '1';
		}else{
			$tpl_path = $_POST['tpl_path'];
			$data = $tpl_path=='basic' ? $arrLayoutBasic['basic'] : array();
		}

		$data['tpl_desc'] 				= $_POST['tpl_desc'];
		$data['width'] 					= $_POST['width'];
		$data['body_width'] 			= $_POST['body_width'];
		$data['width_sign'] 			= $_POST['width_sign'];
		$data['body_width_sign'] 		= $_POST['body_width_sign'];
		$data['align']					= $_POST['align'];
		$data['font']					= $_POST['font'];
		$data['scrollbarColor']			= $_POST['scrollbarColor'];
		$data['backgroundColor']		= $_POST['backgroundChk']=='color' ? $_POST['backgroundColor'] : '';
		$data['bodyBackgroundColor']	= $_POST['bodyBackgroundChk']=='color' ? $_POST['bodyBackgroundColor'] : '';

		$data['layoutHeader']			= $_POST['layoutHeaderChk']=='hidden' ? 'hidden' : $_POST['layoutHeader'];
		$data['layoutTopBar']			= $_POST['layoutTopBarChk']=='hidden' ? 'hidden' : $_POST['layoutTopBar'];
		$data['layoutMainTopBar']		= $_POST['layoutMainTopBarChk']=='hidden' ? 'hidden' : $_POST['layoutMainTopBar'];
		$data['layoutSide']				= $_POST['layoutSideChk']=='hidden' ? 'hidden' : $_POST['layoutSide'];
		if( $_POST['layoutSideLocation']) $data['layoutSideLocation'] = $_POST['layoutSideLocation'];
		$data['layoutFooter']			= $_POST['layoutFooterChk']=='hidden' ? 'hidden' : $_POST['layoutFooter'];
		$data['layoutScrollLeft']		= $_POST['layoutScrollLeftChk']=='hidden' ? 'hidden' : $_POST['layoutScrollLeft'];
		$data['layoutScrollRight']		= $_POST['layoutScrollRightChk']=='hidden' ? 'hidden' : $_POST['layoutScrollRight'];

		if($_POST['backgroundChk']=='image'){
			$data['backgroundImage']		= $_POST['backgroundImage'] ? $_POST['backgroundImage'] : $_POST['oBackgroundImage'];
			$data['backgroundRepeat']		= $_POST['backgroundRepeat'];
			$data['backgroundPosition']		= $_POST['backgroundPosition'];
		}else{
			$data['backgroundImage']		= '';
			$data['backgroundRepeat']		= '';
			$data['backgroundPosition']		= '';
		}

		if($_POST['bodyBackgroundChk']=='image'){
			$data['bodyBackgroundImage']	= $_POST['bodyBackgroundImage'] ? $_POST['bodyBackgroundImage'] : $_POST['oBodyBackgroundImage'];
		}else{
			$data['bodyBackgroundImage']	= "";
		}

		$saveData = layout_config_load($this->designWorkingSkin,$tpl_path);
		$saveData = $saveData[$tpl_path];

		foreach($data as $key=>$value){

			if($key=='backgroundColor' && empty($_POST['backgroundSetChk'])) continue;
			if($key=='backgroundImage' && empty($_POST['backgroundSetChk'])) continue;
			if($key=='backgroundRepeat' && empty($_POST['backgroundSetChk'])) continue;
			if($key=='backgroundPosition' && empty($_POST['backgroundSetChk'])) continue;
			if($key=='bodyBackgroundColor' && empty($_POST['bodyBackgroundSetChk'])) continue;
			if($key=='bodyBackgroundImage' && empty($_POST['bodyBackgroundSetChk'])) continue;
			if($key=='layoutHeader' && empty($_POST['layoutHeaderSetChk'])) continue;
			if($key=='layoutTopBar' && empty($_POST['layoutTopBarSetChk'])) continue;
			if($key=='layoutMainTopBar' && empty($_POST['layoutMainTopBarSetChk'])) continue;
			if($key=='layoutSide' && empty($_POST['layoutSideSetChk'])) continue;
			if($key=='layoutSideLocation' && empty($_POST['layoutSideSetChk'])) continue;
			if($key=='layoutFooter' && empty($_POST['layoutFooterSetChk'])) continue;
			if($key=='layoutScrollLeft' && empty($_POST['layoutScrollLeftSetChk'])) continue;
			if($key=='layoutScrollRight' && empty($_POST['layoutScrollRightSetChk'])) continue;

			$saveData[$key] = $value;

			if($tpl_path!='basic'){
				if($saveData[$key] == $arrLayoutBasic['basic'][$key]) unset($saveData[$key]);
			}
		}

		layout_config_save($this->designWorkingSkin,$tpl_path,$saveData);

		/* 공통레이아웃설정에서 "마이페이지제외 전체적용" 할 경우 */
		if($tpl_path=='basic' && $_POST['apply_type']=='all'){

			//debug_Var($saveData);

			$query = "select * from fm_config_layout where skin=? and tpl_path!='{$tpl_path}'";
			$query = $this->db->query($query,$this->designWorkingSkin);
			foreach ($query->result_array() as $row){

				$rowConfigData = layout_config_load($this->designWorkingSkin,$row['tpl_path']);
				$rowSaveData = $rowConfigData[$row['tpl_path']];
				foreach($saveData as $key=>$row2){

					if(in_array($key,array('tpl_folder','tpl_path','tpl_desc','tpl_page','regist_date'))) continue;

					if($key=='backgroundColor' && empty($_POST['backgroundSetChk'])) continue;
					if($key=='backgroundImage' && empty($_POST['backgroundSetChk'])) continue;
					if($key=='backgroundRepeat' && empty($_POST['backgroundSetChk'])) continue;
					if($key=='backgroundPosition' && empty($_POST['backgroundSetChk'])) continue;
					if($key=='bodyBackgroundColor' && empty($_POST['bodyBackgroundSetChk'])) continue;
					if($key=='bodyBackgroundImage' && empty($_POST['bodyBackgroundSetChk'])) continue;
					if($key=='layoutHeader' && empty($_POST['layoutHeaderSetChk'])) continue;
					if($key=='layoutSide' && empty($_POST['layoutSideSetChk'])) continue;
					if($key=='layoutSideLocation' && empty($_POST['layoutSideSetChk'])) continue;
					if($key=='layoutFooter' && empty($_POST['layoutFooterSetChk'])) continue;
					if($key=='layoutScrollLeft' && empty($_POST['layoutScrollLeftSetChk'])) continue;
					if($key=='layoutScrollRight' && empty($_POST['layoutScrollRightSetChk'])) continue;

					if($row['tpl_folder']=='mypage'){
						if($key=='layoutSide') continue;
						if($key=='layoutSideLocation') continue;
					}

					$rowSaveData[$key] = $saveData[$key];

					if($rowSaveData[$key] == $arrLayoutBasic['basic'][$key]) unset($rowSaveData[$key]);

				}

				layout_config_save($this->designWorkingSkin,$row['tpl_path'],$rowSaveData);
			}

		}

		/*2015-04-23 jhr 상단바 기본값 지정*/
		if($data['layoutMainTopBar'] != 'hidden'){
			$query = $this->db->query('select tab_seq from fm_topbar_file');
			$pprs = $query->row_array();
			if(!isset($pprs["tab_seq"])){
				$insert_query = "INSERT INTO `fm_topbar_file` (`style_index`, `tab_seq`, `tab_title`, `tab_title_img`, `tab_title_img_on`, `tab_filename`) VALUES
				(1, 0, '홈', NULL, NULL, 'index.html'),
				(1, 1, '베스트', NULL, NULL, 'index.html'),
				(1, 2, '신상품', NULL, NULL, 'index.html'),
				(1, 3, '기획전', NULL, NULL, 'index.html');";
				$insert_query2 = "INSERT INTO `fm_topbar_style` (`tab_index`, `tab_type`, `tab_style`, `tab_cursor`, `tab_img_prev`, `tab_img_next`) VALUES
				(1, 'text', 'tabGrey2', 0, '', '');";
				$this->db->query($insert_query);
				$this->db->query($insert_query2);
			}
		}

		if($_POST['mode']=='create'){
			$callback = "parent.parent.DM_window_allpages('{$tpl_path}');";
			openDialogAlert("페이지가 생성 되었습니다.",400,140,'parent',$callback);
		}

		if($_POST['mode']=='edit'){
			$callback = "parent.parent.document.location.reload();document.location.href='about:blank';";
			openDialogAlert("설정이 저장 되었습니다.",400,140,'parent',$callback);
		}

	}

	/* 실제적용 스킨 저장 */
	public function apply_skin(){
		if($_POST['type']=='realSkin'){
			if		($_POST['skinPrefix']=='mobile'){
				config_save('system',array('mobileSkin'=>$_POST['skin']));
				//스킨 버젼을 저장한다 2016-01-19 jhr
				$skin_configuration = skin_configuration($_POST['skin']);
				config_save('system',array('mobileSkinVersion'=>$skin_configuration['mobile_version']));
			}elseif	($_POST['skinPrefix']=='responsive'){
				// 반응형일때 PC/Mobile을 동시 저장함 :: 2019-01-30 lwh
				config_save('system',array('skin'=>$_POST['skin']));
				config_save('system',array('mobileSkin'=>$_POST['skin']));
			}elseif ($_POST['skinPrefix']=='fammerce'){
				config_save('system',array('fammerceSkin'=>$_POST['skin']));
			}else{
				config_save('system',array('skin'=>$_POST['skin']));
			}
		}

		if($_POST['type']=='workingSkin'){
			if		($_POST['skinPrefix']=='mobile'){
				config_save('system',array('workingMobileSkin'=>$_POST['skin']));
				//스킨 버젼을 저장한다 2016-01-19 jhr
				$skin_configuration = skin_configuration($_POST['skin']);
				config_save('system',array('workingMobileSkinVersion'=>$skin_configuration['mobile_version']));
			}elseif	($_POST['skinPrefix']=='fammerce'){
				config_save('system',array('workingFammerceSkin'=>$_POST['skin']));
			}elseif	($_POST['skinPrefix']=='responsive'){
				// 반응형일때 PC/Mobile을 동시 저장함 :: 2019-01-30 lwh
				config_save('system',array('workingSkin'=>$_POST['skin']));
				config_save('system',array('workingMobileSkin'=>$_POST['skin']));
			}else{
				config_save('system',array('workingSkin'=>$_POST['skin']));
			}
		}

		/* 실제스킨,작업스킨 모두 적용 */
		if($_POST['type']=='skin'){
			if		($_POST['skinPrefix']=='mobile'){
				config_save('system',array('mobileSkin'=>$_POST['skin']));
				config_save('system',array('workingMobileSkin'=>$_POST['skin']));
				//스킨 버젼을 저장한다 2016-01-19 jhr
				$skin_configuration = skin_configuration($_POST['skin']);
				config_save('system',array('mobileSkinVersion'=>$skin_configuration['mobile_version']));
				config_save('system',array('workingMobileSkinVersion'=>$skin_configuration['mobile_version']));
			}else	if($_POST['skinPrefix']=='fammerce'){
				config_save('system',array('fammerceSkin'=>$_POST['skin']));
				config_save('system',array('workingFammerceSkin'=>$_POST['skin']));
			}elseif	($_POST['skinPrefix']=='responsive'){
				// 반응형일때 PC/Mobile을 동시 저장함 :: 2019-01-30 lwh
				config_save('system',array('skin'=>$_POST['skin']));
				config_save('system',array('workingSkin'=>$_POST['skin']));
				config_save('system',array('mobileSkin'=>$_POST['skin']));
				config_save('system',array('workingMobileSkin'=>$_POST['skin']));
			}else{
				config_save('system',array('skin'=>$_POST['skin']));
				config_save('system',array('workingSkin'=>$_POST['skin']));
			}
		}
	}

	/* 스킨 백업 */
	public function backup_skin(){
		$this->load->model('designmodel');
		$skin = $_GET['skin'];

		$backup_file_name = $skin."_".date('YmdHis').".zip";

		$backup_file_contents = $this->designmodel->export_skin($skin);

		/* zip 다운로드 */
		force_download($backup_file_name, $backup_file_contents);
	}

	/* 스킨 복사 */
	public function copy_skin(){

		$this->load->library('Zipfile');
		$this->load->helper('download');
		$this->load->helper('directory');
		$this->load->helper('file');

		$this->load->model('usedmodel');

		$res = $this->usedmodel->used_limit_check();
		if(!$res['type']){
			openDialogAlert($res['msg'],600,370,'parent');
			exit;
		}

		if($this->demo){
			openDialogAlert("체험 사이트에서는 해당 기능을 제공하지 않습니다.",300,140,'parent');
			exit;
		}

		$skin = $_GET['skin'];
		$skin_path = ROOTPATH."data/skin/".$skin;
		$skin_idx = 0;

		/* 새로운 스킨명 생성 */
		do {
			$skin_idx++;
			$new_skin = $skin.'_'.$skin_idx;
			$new_skin_path = ROOTPATH."data/skin/".$new_skin;
		}while(is_dir($new_skin_path));

		/* 스킨폴더 생성 */
		if(!is_dir($new_skin_path)) mkdir($new_skin_path);
		chmod($new_skin_path,0777);

		/* 스킨파일 복사 */
		$map = directory_map_list(directory_map($skin_path,false,false));
		foreach($map as $k=>$v) {
			if(is_dir($skin_path.$v)) {
				if(!is_dir($new_skin_path.$v)) mkdir($new_skin_path.$v);
			}
			else{
				copy($skin_path.$v,$new_skin_path.$v);
			}
			chmod($new_skin_path.$v,0777);
		}
		
		// 윈도우 서버 구분
		$isWindowServer = (isset($_SERVER['WINDIR']) === true) ? true : false;
		
		if ($isWindowServer === false) {
			// escapeshellcmd : 시스템에 영향 줄수있는 특수문자를 이스케이프 처리
			$new_skin_path = escapeshellcmd($new_skin_path);
			@exec("chmod 777 {$new_skin_path} -R");
		}

		/* layout.sql 생성 */
		$this->db->close();
		$this->db->initialize();
		$this->db->reconnect();
		$config_layout_queries = array();
		$query = "select * from fm_config_layout where skin=?";
		$query = $this->db->query($query,$skin);
		foreach ($query->result_array() as $row){
			$row['skin'] = $new_skin;
			$keys = "`".implode("`,`",array_keys($row))."`";
			$values = "'".implode("','",array_map("addslashes",$row))."'";
			$config_layout_queries[] = sprintf("INSERT INTO `fm_config_layout` (%s) values (%s);",$keys,$values);
		}

		/* layout config insert */
		$query = "delete from fm_config_layout where skin=?";
		$this->db->query($query,$new_skin);
		foreach($config_layout_queries as $config_layout_query){
			$this->db->query($config_layout_query);
		}

		/* newskin flash delete */
		$query = "select * from fm_design_flash where skin=?";
		$query = $this->db->query($query,$new_skin);
		foreach ($query->result_array() as $row){
			$query = "delete from fm_design_flash_file where flash_seq=?";
			$this->db->query($query,$row['flash_seq']);
		}
		$query = "delete from fm_design_flash where skin=?";
		$this->db->query($query,$new_skin);

		/* flash.sql 생성 */
		$query = "select * from fm_design_flash where skin=?";
		$query = $this->db->query($query,$skin);
		foreach ($query->result_array() as $row){
			$flash_seq = $row['flash_seq'];
			unset($row['flash_seq']);
			$row['skin'] = $new_skin;
			$keys = "`".implode("`,`",array_keys($row))."`";
			$values = "'".implode("','",array_map("addslashes",$row))."'";
			$insert_query = sprintf("INSERT INTO `fm_design_flash` (%s) values (%s);",$keys,$values);
			$this->db->query($insert_query);
			$new_flash_seq = $this->db->insert_id();

			$query2 = "select * from fm_design_flash_file where flash_seq=?";
			$query2 = $this->db->query($query2,$flash_seq);
			foreach ($query2->result_array() as $row2){
				unset($row2['flash_file_seq']);
				$row2['flash_seq'] = $new_flash_seq;
				$keys = "`".implode("`,`",array_keys($row2))."`";
				$values = "'".implode("','",array_map("addslashes",$row2))."'";
				$insert_query = sprintf("INSERT INTO `fm_design_flash_file` (%s) values (%s);",$keys,$values);
				$this->db->query($insert_query);
			}
		}

		/* newskin slide banner delete */
		$query = "select * from fm_design_banner where skin=?";
		$query = $this->db->query($query,$new_skin);
		foreach ($query->result_array() as $row){
			$query = "delete from fm_design_banner_item where skin=? and banner_seq=?";
			$this->db->query($query,array($new_skin,$row['banner_seq']));
		}
		$query = "delete from fm_design_banner where skin=?";
		$this->db->query($query,$new_skin);

		/* banner.sql 생성 */
		$query = "select * from fm_design_banner where skin=?";
		$query = $this->db->query($query,$skin);
		foreach ($query->result_array() as $row){
			$banner_seq = $row['banner_seq'];
			$row['skin'] = $new_skin;
			$keys = "`".implode("`,`",array_keys($row))."`";
			$values = "'".implode("','",array_map("addslashes",$row))."'";
			$insert_query = sprintf("INSERT INTO `fm_design_banner` (%s) values (%s);",$keys,$values);
			$this->db->query($insert_query);

			$query2 = "select * from fm_design_banner_item where skin=? and banner_seq=?";
			$query2 = $this->db->query($query2,array($skin,$banner_seq));
			foreach ($query2->result_array() as $row2){
				unset($row2['banner_item_seq']);
				$row2['skin'] = $new_skin;
				$keys = "`".implode("`,`",array_keys($row2))."`";
				$values = "'".implode("','",array_map("addslashes",$row2))."'";
				$insert_query = sprintf("INSERT INTO `fm_design_banner_item` (%s) values (%s);",$keys,$values);
				$this->db->query($insert_query);
			}
		}

		/* newskin topbar delete */
		$query = "select * from fm_topbar_style where skin=?";
		$query = $this->db->query($query,$new_skin);
		foreach ($query->result_array() as $row){
			$query = "delete from fm_topbar_file where style_index=?";
			$this->db->query($query,$row['tab_index']);
		}
		$query = "delete from fm_topbar_style where skin=?";
		$this->db->query($query,$new_skin);

		/* topbar.sql 생성 */
		$query = "select * from fm_topbar_style where skin=?";
		$query = $this->db->query($query,$skin);
		foreach ($query->result_array() as $row){
			$tab_index= $row['tab_index'];
			unset($row['tab_index']);
			$row['skin'] = $new_skin;
			$keys = "`".implode("`,`",array_keys($row))."`";
			$values = "'".implode("','",array_map("addslashes",$row))."'";
			$insert_query = sprintf("INSERT INTO `fm_topbar_style` (%s) values (%s);",$keys,$values);
			$this->db->query($insert_query);
			$new_tab_index = $this->db->insert_id();

			$query2 = "select * from fm_topbar_file where style_index=?";
			$query2 = $this->db->query($query2,$tab_index);
			foreach ($query2->result_array() as $row2){
				unset($row2['tab_idx']);
				$row2['style_index'] = $new_tab_index;
				$keys = "`".implode("`,`",array_keys($row2))."`";
				$values = "'".implode("','",array_map("addslashes",$row2))."'";
				$insert_query = sprintf("INSERT INTO `fm_topbar_file` (%s) values (%s);",$keys,$values);
				$this->db->query($insert_query);
			}
		}

		skin_configuration_save($new_skin,"skin",$new_skin);

		openDialogAlert("{$new_skin} 스킨 생성",400,140,'parent',"parent.load_skin_list();");

	}

	/* 스킨 이름변경 */
	public function rename_skin(){
		$requestPost = $this->input->post();

		$skin = $requestPost['skin'];
		$skinName = trim($requestPost['skinName']);
		$skinFolder = trim($requestPost['skinFolder']);
		$skinPrefix = $requestPost['skinPrefix'];
	
		$skinRename = new SkinRename();

		$skinParams = [
			// 이전 스킨 폴더명
			'prevSkinFolder' => $skin,
			// 변경될 스킨명
			'skinName' => $skinName,
			// 변경될 스킨폴더명
			'skinFolder' => $skinFolder,
			// 접두사가 필요한 스킨타입
			'skinPrefix' => $skinPrefix,
		];
		// 스킨명 변경
		$renameResult = $skinRename->change($skinParams);
		if ($renameResult['result'] === false) {
			openDialogAlert($renameResult['message'], 400, 140, 'parent');
			exit;
		}

		/* 스킨 스크린샷 변경 */
		if (is_uploaded_file($_FILES['skinScreenshot']['tmp_name'])) {
			$config = array();
			$upload_path = "data/skin/".$skinFolder."/configuration";
			$file_ext = end(explode('.', $_FILES['skinScreenshot']['name']));//확장자추출
			$config['upload_path']			= ROOTPATH.$upload_path;
			$config['allowed_types']		= 'jpg|gif|jpeg|png';
			$config['overwrite']			= TRUE;
			$config['file_name']			= "screenshot.".$file_ext;
			$this->upload->initialize($config);

			if ($this->upload->do_upload('skinScreenshot')) {
				@chmod($config['upload_path'].'/'.$config['file_name'], 0777);
				skin_configuration_save($skin,"screenshot",$config['file_name']);
			}else{
				$callback = "";
				openDialogAlert("스크린샷은 gif, jpg, jpeg, png 만 가능합니다.",400,140,'parent',$callback);
				exit;
			}
		}

		openDialogAlert("스킨 정보가 변경되었습니다.",400,140,'top.parent',"top.parent.document.location.reload();document.location.href='about:blank';");

	}

	/* 스킨 삭제 */
	public function delete_skin(){
		$this->load->helper('directory');

		$requestGet = $this->input->get();

		$skin = $requestGet['skin'];
		$skin_path = ROOTPATH."data/skin/".$skin;
		$skinPrefix = $requestGet['skinPrefix'];

		$folderCheckResult = $this->commonFolderNameValidation(['folderName' => $skin]);
		if ($folderCheckResult['result'] === false) {
			openDialogAlert($folderCheckResult['message'], 400, 140, 'parent.load_skin_list();');
			exit;
		}

		switch($skinPrefix){
			case 'mobile':
				if($this->config_system['mobileSkin']==$skin)				{openDialogAlert("현재 적용된 스킨은 삭제할 수 없습니다.",400,140,'parent');exit;}
				if($this->config_system['workingMobileSkin']==$skin)		{openDialogAlert("작업중인 스킨은 삭제할 수 없습니다.",400,140,'parent');exit;}
			break;
			default:
				if($this->config_system['fammerceSkin']==$skin && serviceLimit('H_NFR'))				{openDialogAlert("현재 적용된 스킨은 삭제할 수 없습니다.",400,140,'parent');exit;}
				if($this->config_system['workingFammerceSkin']==$skin && serviceLimit('H_NFR'))		{openDialogAlert("작업중인 스킨은 삭제할 수 없습니다.",400,140,'parent');exit;}
				if($this->config_system['skin']==$skin)						{openDialogAlert("현재 적용된 스킨은 삭제할 수 없습니다.",400,140,'parent');exit;}
				if($this->config_system['workingSkin']==$skin)				{openDialogAlert("작업중인 스킨은 삭제할 수 없습니다.",400,140,'parent');exit;}
			break;
		}

		/* layout config delete */
		$query = "delete from fm_config_layout where skin=?";
		$this->db->query($query,$skin);

		/* flash delete */
		$query = "select * from fm_design_flash where skin=?";
		$query = $this->db->query($query,$skin);
		foreach ($query->result_array() as $row){
			$query = "delete from fm_design_flash_file where flash_seq=?";
			$this->db->query($query,$row['flash_seq']);
		}
		$query = "delete from fm_design_flash where skin=?";
		$this->db->query($query,$skin);

		/* slide banner delete */
		$query = "select * from fm_design_banner where skin=?";
		$query = $this->db->query($query,$skin);
		foreach ($query->result_array() as $row){
			$query = "delete from fm_design_banner_item where skin=? and banner_seq=?";
			$this->db->query($query,array($skin,$row['banner_seq']));
		}
		$query = "delete from fm_design_banner where skin=?";
		$this->db->query($query,$skin);

		/* topbar delete */
		$query = "select * from fm_topbar_style where skin=?";
		$query = $this->db->query($query,$skin);
		foreach ($query->result_array() as $row){
			$query = "delete from fm_topbar_file where style_index=?";
			$this->db->query($query,$row['tab_index']);
		}
		$query = "delete from fm_topbar_style where skin=?";
		$this->db->query($query,$skin);

		/* 스킨폴더 내 파일 및 디렉토리 삭제 */
		$map = directory_map_list(directory_map($skin_path,false,true));
		rsort($map);
		foreach($map as $k=>$v) {
			chmod($skin_path.$v,0777);
			if(is_file($skin_path.$v)){
				unlink($skin_path.$v);
			}else{
				rmdir($skin_path.$v);
			}
		}

		if(rmdir($skin_path)){
			openDialogAlert("{$skin} 스킨을 삭제하였습니다.",400,150,'parent',"parent.load_skin_list();");
		}elseif(is_dir($skin_path)){
			openDialogAlert("{$skin} 스킨을 삭제 실패",400,150,'parent',"parent.load_skin_list();");
		}else{
			openDialogAlert("{$skin} 스킨은 존재하지 않습니다.",400,150,'parent',"parent.load_skin_list();");
		}
	}

	/* 스킨 다운로드&업로드 */
	public function download_skin(){
		$this->load->helper('file');
		$this->load->helper('readurl');
		$this->load->model('designmodel');
		$this->load->model('usedmodel');

		$res = $this->usedmodel->used_limit_check();
		if(!$res['type']){
			openDialogAlert($res['msg'],600,370,'parent');
			exit;
		}

		$url = urldecode($_POST['url']);
		$urlInfo = parse_url($url);
		// scheme이 없을 때 scheme을 http로 하고 url을 재구성한다. : rsh 2019-03-15
		if(empty($urlInfo['scheme'])) {
		    $url = "http:{$url}";
		}

		if(!preg_match("/\.zip$/",$url)){
			openDialogAlert("스킨 ZIP 파일이 아닙니다.",300,150,'parent');
			exit;
		}

		if(!$url){
			openDialogAlert("업로드할 스킨 URL이 없습니다.",300,150,'parent');
			exit;
		}

		$data = readurl($url);
		$zip_path = ROOTPATH."data/tmp/download_skin_tmp_".time().".zip";
		$res = write_file($zip_path, $data);

		if(!$res){
			openDialogAlert("스킨 ZIP파일 다운로드에 실패했습니다.",300,150,'parent');
			exit;
		}

		$success = $this->designmodel->zip_extract_skin($zip_path);

		if($success[0] == true){
			$platform = "PC";
			$url	= "../design/skin";
			if( $_POST['target'] == 'mobile' ){
				$url	.= "?prefix=mobile";
				$platform = "Mobile";
			}
			if	($success[2]) {
				openDialogConfirm("{$success[1]} 스킨이 추가되었습니다.<br />상품,카테고리 데이터가 초기화 됩니다 진행하시겠습니까?",400,180,'top.parent',"parent.set_default_data('{$success[1]}','".$url."');","top.parent.location.href='".$url."';");
			}else{
				openDialogConfirm("스킨이 추가되었습니다.<br/>".$platform." 보유 스킨 리스트로 이동하시겠습니까?",400,165,'top.parent',"top.parent.location.href='".$url."';");
			}
		}
	}

	/* 스킨 업로드 */
	public function upload_skin(){

		$this->load->helper('directory');
		$this->load->helper('file');
		$this->load->model('designmodel');
		$this->load->model('usedmodel');

		$res = $this->usedmodel->used_limit_check();
		if(!$res['type']){
			openDialogAlert($res['msg'],600,370,'parent');
			exit;
		}

		if(!$_FILES['skin_zipfile']['name']){
			openDialogAlert("업로드할 스킨 ZIP파일을 첨부해주세요.",300,150,'parent');
			exit;
		}

		$mines = array('application/x-zip', 'application/zip', 'application/x-zip-compressed', 'application/s-compressed', 'multipart/x-zip');
		if (!in_array($_FILES['skin_zipfile']['type'], $mines)) {
			openDialogAlert("ZIP파일이 아닙니다..",300,150,'parent');
			exit;
		}

		$success = $this->designmodel->zip_extract_skin($_FILES['skin_zipfile']['tmp_name']);

		if($success[0] == true){
			if	($success[2]) {
				openDialogConfirm("{$success[1]} 스킨이 추가되었습니다.<br />상품,카테고리 데이터가 초기화 됩니다 진행하시겠습니까?",400,180,'top.parent',"parent.set_default_data('{$success[1]}');","parent.document.location.reload();document.location.href='about:blank';");
			}else{
				openDialogAlert("{$success[1]} 스킨이 추가되었습니다.",400,150,'parent',"parent.document.location.reload();document.location.href='about:blank';");
			}
		}
	}

	/* 소스편집 저장 */
	public function sourceeditor_save(){
		$this->load->helper('directory');
		$this->load->helper('file');
		$this->load->helper('readurl');
		$this->load->model('usedmodel');

		// 데모몰에서는 차단
		if($this->demo){
			openDialogAlert("데모몰에서는 소스편집이 불가합니다.",400,150,'parent');
			exit;
		}

		$res = $this->usedmodel->used_limit_check();
		if(!$res['type']){
			openDialogAlert($res['msg'],600,370,'parent');
			exit;
		}

		if(!isset($_POST['skin']) || !$_POST['skin']){
			openDialogAlert("skin 값 누락",300,150,'parent');
			exit;
		}

		if(!isset($_POST['tpl_path']) || !$_POST['tpl_path']){
			openDialogAlert("tpl_path 값 누락",300,150,'parent');
			exit;
		}

		/* tpl_path assign */
		$skin = $_POST['skin'];
		$skinPath = ROOTPATH."data/skin";
		$tpl_path = $_POST['tpl_path'];
		$tpl_realpath = $skinPath."/".$skin."/".$tpl_path;
		$tpl_source = $_POST['tpl_source'];

		backup_skin_file($skin,$tpl_path);

		write_file($tpl_realpath, $tpl_source);

		echo js("parent.sourceeditor_edited_mark_off();");
		//openDialogAlert("{$tpl_path} 저장완료",400,140,'parent',"parent.document.getElementById('source_edited_mark').style.display='none';");

		if($_POST['reload']){
			openDialogAlert("{$tpl_path} 저장완료",400,150,'parent',"parent.parent.document.location.reload();document.location.href='about:blank';");
		}else{
			openDialogAlert("{$tpl_path} 저장완료",400,150,'parent',"");
		}
	}

	/* eye editor 소스편집 저장 */
	public function eye_editor_save(){
		$this->load->helper('directory');
		$this->load->helper('file');
		$this->load->helper('readurl');
		$this->load->model('usedmodel');

		// 데모몰에서는 차단
		if($this->demo){
			$result = array(
				'code'	=> 'failure',
				'msg'	=> "데모몰에서는 소스편집이 불가합니다.",
			);
			echo json_encode($result);
			exit;
		}

		$result = array(
			'code'	=> 'success',
			'msg'	=> '',
		);

		$res = $this->usedmodel->used_limit_check();
		if(!$res['type']){
			$result = array(
				'code'	=> 'failure',
				'msg'	=> $res['msg'],
			);
			echo json_encode($result);
			exit;
		}

		if(!isset($_POST['tplPath']) || !$_POST['tplPath']){
			$result = array(
				'code'	=> 'failure',
				'msg'	=> "tplPath 값 누락",
			);
			echo json_encode($result);
			exit;
		}

		/* tplPath assign */
		$tplPath = $_POST['tplPath'];
		$tpl_realpath = ROOTPATH.$tplPath;
		$tpl_source = $_POST['tplSource'];

		if(!backup_file($tplPath)){
			$result = array(
				'code'	=> 'failure',
				'msg'	=> "파일 백업 실패",
			);
			echo json_encode($result);
			exit;
		}

		if(!write_file($tpl_realpath, $tpl_source)){
			$result = array(
				'code'	=> 'failure',
				'msg'	=> "파일 저장 실패",
			);
			echo json_encode($result);
			exit;
		}

		@chmod($tpl_realpath,0777);

		$result = array(
			'code'		=> 'success',
			'filemtime'	=> filemtime($tpl_realpath),
			'msg'		=> "{$tplPath} 저장완료",
		);
		echo json_encode($result);
	}

	/* 새 파일 생성 */
	public function eye_editor_newpage(){

		$this->load->helper('file');

		$newFileDirectory = preg_replace("/^\//","",$_POST['newFileDirectory']);
		$newFileName = $_POST['newFileName'];

		$newFileDirectoryReal = ROOTPATH.$newFileDirectory;
		$newFilePath = $newFileDirectory.'/'.$newFileName;
		$newFilePathReal = ROOTPATH.$newFilePath;

		$newFileExtTmp = explode('.',$newFileName);
		$newFileExt = $newFileExtTmp[count($newFileExtTmp)-1];

		if(!in_array($newFileExt,array('html','htm','css','js','ini','txt','cvs'))){
			$result = array(
				'code'		=> 'failure',
				'msg'		=> "허용되지 않는 파일확장자입니다."
			);
			echo json_encode($result);
			exit;
		}

		if(preg_match("/\.\./",$newFilePath)){
			$result = array(
				'code'		=> 'failure',
				'msg'		=> "파일명에 '..'이 포함될 수 없습니다."
			);
			echo json_encode($result);
			exit;
		}

		if(file_exists($newFilePathReal)){
			$result = array(
				'code'		=> 'duplicate',
				'msg'		=> "동일한 이름의 파일이 존재합니다."
			);
			echo json_encode($result);
			exit;
		}

		$res = write_file($newFilePathReal, '');
		@chmod($newFilePathReal,0777);

		if(!$res){
			$result = array(
				'code'		=> 'failure',
				'msg'		=> "파일 생성 실패"
			);
			echo json_encode($result);
			exit;
		}

		if(preg_match("/^data\/skin\/([^\/]+)\/([^\/]+)\/(.*)/",$newFilePath,$matches)){
			$skin = $matches[1];
			$tpl_folder = $matches[2];
			$tpl_path = $tpl_folder.'/'.$matches[3];

			$saveData = array();
			$saveData['skin'] = $skin;
			$saveData['tpl_folder'] = $tpl_folder;
			$saveData['tpl_path'] = $tpl_path;
			$saveData['tpl_desc'] = '';
			$saveData['tpl_page'] = 1;
			$saveData['regist_date'] = date('Y-m-d H:i:s');

			layout_config_save($skin,$tpl_path,$saveData);

		}

		$result = array(
			'code'		=> 'success',
			'tplPath'	=> $newFilePath,
			'msg'		=> "{$newFilePath} 저장완료",
		);
		echo json_encode($result);
	}

	/* 파일 수정시간 반환 */
	public function eye_editor_filemtime(){
		$tplPath = $this->input->post('tplPath');
		$tpl_realpath = ROOTPATH.$tplPath;
		echo filemtime($tpl_realpath);
	}

	/* 파일명 중복확인 */
	public function tpl_file_name_chk(){
		$tpl_folder = $_GET['tpl_folder'];
		$tpl_file_name = $_GET['tpl_file_name'];
		$tpl_file_ext = $_GET['tpl_file_ext'];

		$skinPath = ROOTPATH."data/skin/".$this->designWorkingSkin."/".$tpl_folder."/".$tpl_file_name;

		if($tpl_file_ext) $skinPath .= ".".$tpl_file_ext;

		$newFileExtTmp = explode('.',$skinPath);
		$newFileExt = $newFileExtTmp[count($newFileExtTmp)-1];

		if(!preg_match("/^[a-zA-Z0-9_]*$/",$tpl_file_name)){
			echo '0';exit;
		}

		if(!in_array($newFileExt,array('html','htm','css','js','ini','txt','cvs'))){
			echo '0';exit;
		}

		if(preg_match("/\.\./",$tpl_folder) || preg_match("/\.\./",$tpl_file_name)){
			echo '0';exit;
		}

		if(file_exists($skinPath)){
			echo '1';exit;
		}

		echo '1111';
	}

	/* 새로만든페이지 삭제 */
	public function tpl_file_delete(){
		$tplPath = $_POST['tpl_path'];

		$layout_config = layout_config_load($this->designWorkingSkin,$tplPath);

		if(!$tplPath || preg_match("/\.\.\//",$tplPath)){
			openDialogAlert("삭제할 파일 경로가 올바르지 않습니다.",400,140,'parent');
			exit;
		}

		if(!$layout_config[$tplPath]['tpl_page']){
			openDialogAlert("삭제할 수 없는 파일입니다.",400,140,'parent');
			exit;
		}

		$filePath = ROOTPATH.'data/skin/'.$this->designWorkingSkin.'/'.$tplPath;

		@unlink($filePath);

		$this->db->query("delete from fm_config_layout where skin=? and tpl_path=?",array($this->designWorkingSkin,$tplPath));

		$callback= "parent.document.location.reload();";
		openDialogAlert("페이지를 삭제했습니다.", 400, 140, 'parent', $callback);
	}


	/* 중복확인 */
	public function file_name_chk(){
		$tpl_folder = $_GET['tpl_folder'];
		$tpl_file_name = $_GET['tpl_file_name'];

		$skinPath = ROOTPATH.$tpl_folder."/".$tpl_file_name;

		$newFileExtTmp = explode('.',$skinPath);
		$newFileExt = $newFileExtTmp[count($newFileExtTmp)-1];

		if(!preg_match("/^[a-zA-Z0-9_]*$/",$tpl_file_name)){
			echo '0';exit;
		}

		if(!in_array($newFileExt,array('html','htm','css','js','ini','txt','cvs'))){
			echo '0';exit;
		}

		if(preg_match("/\.\./",$tpl_folder) || preg_match("/\.\./",$tpl_file_name)){
			echo '0';exit;
		}

		if(file_exists($skinPath)){
			echo '0';exit;
		}

		echo '1';
	}

	/* 이미지 변경 처리 */
	public function image_edit(){
		$this->tempate_modules();
		$this->load->helper('file');
		$this->load->model('usedmodel');

		$res = $this->usedmodel->used_limit_check();
		if(!$res['type']){
			openDialogAlert($res['msg'],600,370,'parent');
			exit;
		}

		$designTplPath		= isset($_REQUEST['designTplPath']) ? $_REQUEST['designTplPath'] : null;
		$designImgSrc		= isset($_REQUEST['designImgSrc']) ? $_REQUEST['designImgSrc'] : null;
		$designImgSrcOri	= isset($_REQUEST['designImgSrcOri']) ? $_REQUEST['designImgSrcOri'] : null;
		$designImgPath		= isset($_REQUEST['designImgPath']) ? $_REQUEST['designImgPath'] : null;
		$newDesignImgPath	= isset($_REQUEST['newDesignImgPath']) ? $_REQUEST['newDesignImgPath'] : null;
		$link				= isset($_REQUEST['link']) ? trim($_REQUEST['link']) : null;
		$target				= isset($_REQUEST['target']) ? $_REQUEST['target'] : null;
		$imageLabel			= isset($_REQUEST['imageLabel']) ? $_REQUEST['imageLabel'] : null;
		$removeDesignImageArea	= isset($_REQUEST['removeDesignImageArea']) ? true : false;

		$tpl_path			= ROOTPATH."data/skin/".$designTplPath;

		/* data 폴더가 아니면 차단 */
		if($newDesignImgPath){
			if ( ! $this->_datapath_check(ROOTPATH . $newDesignImgPath)){
				openDialogAlert("권한이 없습니다.",400,140,'parent');
				exit;
			}
		}

		/* 파일이 있으면 */

		/* 파일이 있으면 */
		if($_POST['newDesignImgPath']){
			//새 이미지 파일 압축 18.06.18 kmj
			$tmpExe = explode(".", $_POST['newDesignImgPath']);
			$exe = end($tmpExe);

			if(extension_loaded('imagick') && ($exe == "jpg" || $exe == "jpeg")){
				$img = new Imagick();
				$img->readImage($_POST['newDesignImgPath']);
				$img->setImageCompression(Imagick::COMPRESSION_JPEG);
				$img->setImageCompressionQuality(90);
				$img->stripImage();
				$img->writeImage($_POST['designImgPath']);
				unset($img);
			} else {
				if(!copy($_POST['newDesignImgPath'],$_POST['designImgPath'])){
					openDialogAlert("파일 카피 실패",400,140,'parent');
					exit;
				}
			}
		}

		/* 소스 불러오기 */
		$source = read_file($tpl_path);

		/* src 보정 */
		$designImgSrcOriForReg = addslashes($designImgSrcOri);
		$designImgSrcOriForReg = str_replace(array("/",".","(",")","[","]"),array("\\/","\\.","\\(","\\)","\\[","\\]"),$designImgSrcOriForReg);

		/* 이미지 영역 제거 */
		if($removeDesignImageArea){

			// 수정 전에 백업 파일 생성 추가 :: 2019-08-13 pjw
			$this->load->helper('readurl');
			if(!backup_file("data/skin/".$designTplPath)){
				openDialogAlert("파일 백업 실패",400,140,'parent');
				exit;
			}

			$source = preg_replace_callback("/<a [^>]*>(<img[^>]*src=[\'|\"]".$designImgSrcOriForReg."[\'|\"][^>]*>)<\/a>/i",create_function('$matches','return "";'), $source);
			$source = preg_replace_callback("/<img[^>]*src=[\'|\"]".$designImgSrcOriForReg."[\'|\"][^>]*>/i",create_function('$matches','return "";'), $source);
		}
		/* 링크주소 보정 */
		else {
			/* link 보정*/
			$link = str_replace(array('\'','"',';','\\'),'',$link);

			/* a태그 link추가 */
			if($link){
				if(preg_match("/<a [^>]*>(<img[^>]*src=[\'|\"]".$designImgSrcOriForReg."[\'|\"][^>]*>)<\/a>/i",$source)){
					// a태그가 있으면 수정
					$source = preg_replace_callback("/(<a[^>]*href=[\'|\"])[^\'\">]*([\'|\"][^>]*><img[^>]*src=[\'|\"]".$designImgSrcOriForReg."[\'|\"][^>]*><\/a>)/i",create_function('$matches','return "{$matches[1]}'.$link.'{$matches[2]}";'), $source);

					if(preg_match("/<a [^>]*target=[\'|\"][^>]*[\'|\"][^>]*>(<img[^>]*src=[\'|\"]".$designImgSrcOriForReg."[\'|\"][^>]*>)<\/a>/i",$source)){
						// target 태그가 있으면 수정
						$source = preg_replace_callback("/(<a[^>]*target=[\'|\"])[^\'\">]*([\'|\"][^>]*><img[^>]*src=[\'|\"]".$designImgSrcOriForReg."[\'|\"][^>]*><\/a>)/i",create_function('$matches','return "{$matches[1]}'.$target.'{$matches[2]}";'), $source);

					}else{
						// target 태그가 없으면 추가
						$source = preg_replace_callback("/(<a [^>]*)(><img[^>]*src=[\'|\"]".$designImgSrcOriForReg."[\'|\"][^>]*><\/a>)/i",create_function('$matches','return "{$matches[1]} target=\''.$target.'\'{$matches[2]}";'), $source);
					}

					$source = preg_replace_callback("/(<a[^>]*target=[\'|\"])[^>]*([\'|\"][^>]*><img[^>]*src=[\'|\"]".$designImgSrcOriForReg."[\'|\"][^>]*><\/a>)/i",create_function('$matches','return "{$matches[1]}'.$target.'{$matches[2]}";'), $source);
				}else{
					// a태그가 없으면 추가
					$source = preg_replace_callback("/<img[^>]*src=[\'|\"]".$designImgSrcOriForReg."[\'|\"][^>]*>/i",create_function('$matches','return "<a href=\''.$link.'\' target=\''.$target.'\'>$matches[0]</a>";'), $source);
				}
			}else{
				$source = preg_replace_callback("/<a [^>]*>(<img[^>]*src=[\'|\"]".$designImgSrcOriForReg."[\'|\"][^>]*>)<\/a>/i",create_function('$matches','return "{$matches[1]}";'), $source);
			}

			/* img태그 alt추가 */
			if(preg_match("/<img[^>]*src=[\'|\"]".$designImgSrcOriForReg."[\'|\"][^>]*alt=[\'|\"][^>]*[\'|\"][^>]*>/i",$source)){
				// alt태그가 있으면 수정
				$source = preg_replace_callback("/(<img[^>]*src=[\'|\"]".$designImgSrcOriForReg."[\'|\"][^>]*alt=[\'|\"])[^\'\">]*([\'|\"][^>]*>)/i",create_function('$matches','return "{$matches[1]}'.$imageLabel.'{$matches[2]}";'), $source);
			}else{
				// alt태그가 없으면 추가
				$source = preg_replace_callback("/(<img[^>]*src=[\'|\"]".$designImgSrcOriForReg."[\'|\"])([^>]*>)/i",create_function('$matches','return "{$matches[1]} alt=\\"'.$imageLabel.'\\"{$matches[2]}";'), $source);
			}

			/* img태그 title추가 */
			if(preg_match("/<img[^>]*src=[\'|\"]".$designImgSrcOriForReg."[\'|\"][^>]*title=[\'|\"][^>]*[\'|\"][^>]*>/i",$source)){
				// title태그가 있으면 수정
				$source = preg_replace_callback("/(<img[^>]*src=[\'|\"]".$designImgSrcOriForReg."[\'|\"][^>]*title=[\'|\"])[^\'\">]*([\'|\"][^>]*>)/i",create_function('$matches','return "{$matches[1]}'.$imageLabel.'{$matches[2]}";'), $source);
			}else{
				// title태그가 없으면 추가
				$source = preg_replace_callback("/(<img[^>]*src=[\'|\"]".$designImgSrcOriForReg."[\'|\"])([^>]*>)/i",create_function('$matches','return "{$matches[1]} title=\\"'.$imageLabel.'\\"{$matches[2]}";'), $source);
			}
		}

		if(!write_file($tpl_path,$source)){
			openDialogAlert("소스 변경 실패",400,140,'parent');
			exit;
		}

		openDialogAlert("적용되었습니다.",400,140,'parent','parent.parent.document.location.reload()');
	}

	/* 이미지 넣기 처리 */
	public function image_insert(){
		$this->tempate_modules();
		$this->load->helper('file');
		$this->load->model('usedmodel');

		$res = $this->usedmodel->used_limit_check();
		if(!$res['type']){
			openDialogAlert($res['msg'],600,370,'parent');
			exit;
		}

		$tplPath			= isset($_REQUEST['tplPath']) ? $_REQUEST['tplPath'] : null;
		$newDesignImgPath	= isset($_REQUEST['newDesignImgPath']) ? $_REQUEST['newDesignImgPath'] : null;
		$uploadPath			= isset($_REQUEST['uploadPath']) ? $_REQUEST['uploadPath'] : null;
		$originalFileName	= isset($_REQUEST['originalFileName']) ? $_REQUEST['originalFileName'] : null;

		$link				= isset($_REQUEST['link']) ? trim($_REQUEST['link']) : null;
		$target				= isset($_REQUEST['target']) ? $_REQUEST['target'] : null;
		$imageLabel			= isset($_REQUEST['imageLabel']) ? $_REQUEST['imageLabel'] : null;
		$location			= isset($_REQUEST['location']) ? $_REQUEST['location'] : null;


		$tpl_path			= ROOTPATH."data/skin/".$this->designWorkingSkin."/".$tplPath;

		$this->validation->set_rules('newDesignImgPath', '삽입 이미지','trim|required|xss_clean');

		if($this->validation->exec()===false){
			$err = $this->validation->error_array;
			$callback = "if(parent.document.getElementsByName('{$err['key']}')[0]) parent.document.getElementsByName('{$err['key']}')[0].focus();";
			openDialogAlert($err['value'],400,140,'parent',$callback);
			exit;
		}
		// 파일 확장자
		$tmpExe = explode(".", $newDesignImgPath);
		$exe = end($tmpExe);
		// 기본 이미지 확장자 목록
		$defaultImageTypes = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'ico'];
		// 이미지 여부 체크
		if (!in_array($exe, $defaultImageTypes)) {
			openDialogAlert(getAlert('et001'), 400, 140, 'parent');
			exit;
		}

		if(preg_match("/^\/?data\/tmp/",$newDesignImgPath) && !$originalFileName) $originalFileName = basename($newDesignImgPath);
		$uploadFilePath = $uploadPath.'/'.$originalFileName;

		/* data 폴더가 아니면 차단 */
		if($newDesignImgPath){
			if( ! $this->_datapath_check($newDesignImgPath)){
				openDialogAlert("권한이 없습니다.",400,140,'parent');
				exit;
			}
		}

		/* 파일이 있으면 */
		if($newDesignImgPath && $newDesignImgPath!=$uploadFilePath){
			if(extension_loaded('imagick') && ($exe == "jpg" || $exe == "jpeg")){
				$img = new Imagick();
				$img->readImage($newDesignImgPath);
				$img->setImageCompression(Imagick::COMPRESSION_JPEG);
				$img->setImageCompressionQuality(90);
				$img->stripImage();
				$img->writeImage($uploadFilePath);
				unset($img);
			} else {
				if(!copy($newDesignImgPath, $uploadFilePath)){
					openDialogAlert("적용에 실패했습니다.",400,140,'parent');
					exit;
				}
			}

			@chmod($uploadFilePath,0777);
		}

		/* src 보정 */
		$relPath = str_repeat('../',count(explode('/',dirname($_REQUEST['tplPath']))));
		$uploadFilePathForReg = addslashes(str_replace("data/skin/".$this->designWorkingSkin."/",$relPath,$uploadFilePath));

		/* 추가소스 */
		$addSource = "<img src=\"{$uploadFilePathForReg}\" alt=\"{$imageLabel}\" title=\"{$imageLabel}\">";

		/* link 보정*/
		$link = str_replace(array('\'','"',';','\\'),'',$link);

		if($link){
			$addSource = "<a href=\"{$link}\" target=\"".$target."\">{$addSource}</a>";
		}

		/* 소스 불러오기 */
		$source = read_file($tpl_path);

		if($location == 'top'){
			$source = $addSource . "\n" . $source;
		}else{
			$source = $source . "\n" . $addSource;
		}

		if(!write_file($tpl_path,$source)){
			openDialogAlert("적용에 실패했습니다.",400,140,'parent');
			exit;
		}

		if(preg_match("/^layout_/",$_REQUEST['tplPath']) || preg_match("/^board/",$_REQUEST['tplPath']) || preg_match("/^goods/",$_REQUEST['tplPath']) || preg_match("/^joincheck/",$_REQUEST['tplPath'])){
			openDialogAlert("적용되었습니다.",400,140,'parent',"parent.parent.document.location.reload();document.location.href='about:blank';");
		}else{
			$locationUrl = $this->layout->get_tpl_path_url($this->designWorkingSkin,$_REQUEST['tplPath']);
			openDialogAlert("적용되었습니다.",400,140,'parent',"parent.parent.document.location.href='{$locationUrl}';");
		}

	}

	/* 팝업 넣기 */
	public function popup_insert(){

		$this->load->helper('file');
		$this->load->model('usedmodel');

		$res = $this->usedmodel->used_limit_check();
		if(!$res['type']){
			openDialogAlert($res['msg'],600,370,'parent');
			exit;
		}

		$params = $_POST;

		if(!$params['popup_seq']){
			openDialogAlert("적용할 팝업을 선택해주세요.",400,140,'parent');
			exit;
		}

		$template_path			= ROOTPATH."data/skin/".$this->designWorkingSkin."/".$params['template_path'];

		if	($this->config_system['operation_type'] == 'light'){
			$addSource = "{=showDesignLightPopup({$params['popup_seq']})}";
		}else{
			$addSource = "{=showDesignPopup({$params['popup_seq']})}";
		}

		/* 소스 불러오기 */
		$source = read_file($template_path);

		/* 소스 체크 */
		if(preg_match("/".str_replace(array("{","}","(",")"),array("\\{","\\}","\\(","\\)"),$addSource)."/",$source)){
			openDialogAlert("이미 팝업이 띄워져있습니다.",400,140,'parent');
			exit;
		}

		/* 소스 추가 */
		$source = $addSource . "\n" . $source;

		if(!write_file($template_path,$source)){
			openDialogAlert("적용에 실패했습니다.",400,140,'parent');
			exit;
		}

		if(preg_match("/^layout_/",$params['template_path']) || preg_match("/^board/",$params['template_path']) || preg_match("/^goods/",$params['template_path'])){
			openDialogAlert("적용되었습니다.",400,140,'parent',"parent.parent.document.location.reload();document.location.href='about:blank';");
		}else{
			$locationUrl = $this->layout->get_tpl_path_url($this->designWorkingSkin,$params['template_path']);
			openDialogAlert("적용되었습니다.",400,140,'parent',"parent.parent.document.location.href='{$locationUrl}';");
		}
	}

	/* 팝업 생성,변경 처리 */
	public function popup_edit(){
		$params = $this->input->post();
		// 에디터 내용은 xss 필터링에서 제외
		$params['contents'] = $this->input->post('contents', false);

		// 팝업 노출 조건 추가 2015-10-01 jhr
		if(!isset($params['view'])) $params['view'] = 'all';
		if(!isset($params['category'])) $params['category'] = 'all';
		if(!isset($params['brand'])) $params['brand'] = 'all';
		if(!isset($params['location'])) $params['location'] = 'all';

		$condition_arr = array();
		$condition_arr['view'] = $params['view'];
		$condition_arr['category'] = $params['category'];
		$condition_arr['brand'] = $params['brand'];
		$condition_arr['location'] = $params['location'];

		switch($params['view']){
			case "category":
				$condition_arr['issueCategoryViewCode'] = $params['issueCategoryViewCode'];
				break;
			case "brand":
				$condition_arr['issueBrandViewCode'] = $params['issueBrandViewCode'];
				break;
			case "goods":
				$condition_arr['issueGoods'] = $params['issueGoods'];
				break;
		}

		if(isset($params['category']) && $params['category'] != 'all') $condition_arr['issueCategoryCode'] = $params['issueCategoryCode'];
		if(isset($params['brand']) && $params['brand'] != 'all') $condition_arr['issueBrandCode'] = $params['issueBrandCode'];
		if(isset($params['location']) && $params['location'] != 'all')  $condition_arr['issueLocationCode'] = $params['issueLocationCode'];

		$params['popup_condition'] = serialize($condition_arr);

		// 띠배너,모바일일 경우 contents_type 은 image 로 고정 2015-10-07 jhr
		if($params['style'] == 'band' || $params['style'] == 'mobile_band'){
			$params['contents_type'] = 'image';
		}

		if(is_numeric($params['popup_seq']) === true){
			$query = $this->db->get_where('fm_design_popup', array('popup_seq'=>$params['popup_seq']));
			$popupData = $query->row_array();
		}else{
			$popupData = array();
		}

		/* 영역 제거 */
		if(isset($params['removeDesignPopupArea']) && $params['removeDesignPopupArea']=='Y'){
			$this->load->model('usedmodel');

			$res = $this->usedmodel->used_limit_check();
			if(!$res['type']){
				openDialogAlert($res['msg'],600,370,'parent');
				exit;
			}

			$this->load->helper('file');
			$tpl_path = ROOTPATH.'data/skin/'.$this->designWorkingSkin.'/'.$params['template_path'];

			// 수정 전에 백업 파일 생성 추가 :: 2019-08-13 pjw
			$this->load->helper('readurl');
			if(!backup_file('data/skin/'.$this->designWorkingSkin.'/'.$params['template_path'])){
				$this->load->helper('readurl');
				openDialogAlert("파일 백업 실패",400,140,'parent');
				exit;
			}

			/* 소스 불러오기 */
			$source = read_file($tpl_path);

			$removeSource = "{=showDesignPopup({$params['popup_seq']})}";

			$source = str_replace($removeSource,"",$source);

			if(!write_file($tpl_path,$source)){
				openDialogAlert("소스 변경 실패",400,140,'parent');
				exit;
			}
		}

		if(trim($params['admin_comment'])==''){
			openDialogAlert("관리용 코멘트는 필수입력 사항입니다.",400,140,'parent');
			exit;
		}

		if($params['contents_type']=='image' && !$popupData['image'] && !$params['newImgPath']){
			openDialogAlert("팝업 이미지를 첨부해주세요",400,140,'parent');
			exit;
		}

		/* 본문 배경이미지 업로드시 */
		if (is_uploaded_file($_FILES['band_background_image']['tmp_name'])) {
			$upload_path = "data/skin/".$this->designWorkingSkin."/images/design";
			$config['upload_path']		= ROOTPATH.$upload_path;
			$file_ext = end(explode('.', $_FILES['band_background_image']['name']));//확장자추출
			$config['allowed_types']	= 'jpg|gif|jpeg|png';
			$config['overwrite']			= TRUE;
			$config['file_name']			= "body_background_image".substr(microtime(), 2, 6).'.'.$file_ext;
			$this->upload->initialize($config);

			if ($this->upload->do_upload('band_background_image')) {
				@chmod($config['upload_path'].'/'.$config['file_name'], 0777);
				$_POST['band_background_image'] = $config['file_name'];
			}else{
				$callback = "";
				openDialogAlert("gif, jpg,jpeg, png 만 가능합니다.",400,140,'parent',$callback);
				exit;
			}
		}

		if($params['band_background_type']=='image'){

			if($params['new_band_background_image']){
				$params['band_background_image'] = basename(str_replace('temp_','popup_bandbg_',$params['new_band_background_image']));
				@copy($_POST['new_band_background_image'],ROOTPATH.'data/popup/'.$params['band_background_image']);
				@chmod(ROOTPATH.'data/popup/'.$params['band_background_image'],0777);

				if($popupData['band_background_image']){
					@unlink(ROOTPATH.'data/popup/'.$popupData['band_background_image']);
				}
			}

			$band_background_image['band_background_image']		= $params['band_background_image'] ? $params['band_background_image'] : $params['o_band_background_image'];
			$params['band_background_color']='';
		}else{
			if($popupData['band_background_image']){
				@unlink(ROOTPATH.'data/popup/'.$popupData['band_background_image']);
			}
			$params['band_background_image']='';
			$params['band_background_image_repeat']='';
			$params['band_background_image_position']='';
		}

		if($params['newImgPath']){
			$params['image'] = basename(str_replace('temp_','popup_',$params['newImgPath']));

			//새 이미지 파일 압축 18.06.18 kmj
			$tmpExe = explode(".", $params['newImgPath']);
			$exe = end($tmpExe);

			if(extension_loaded('imagick') && ($exe == "jpg" || $exe == "jpeg")){
				$img = new Imagick();
				$img->readImage($params['newImgPath']);
				$img->setImageCompression(Imagick::COMPRESSION_JPEG);
				$img->setImageCompressionQuality(90);
				$img->stripImage();
				$img->writeImage(ROOTPATH.'data/popup/'.$params['image']);
				unset($img);
			} else {
				@copy($params['newImgPath'], ROOTPATH.'data/popup/'.$params['image']);
			}

			@chmod(ROOTPATH.'data/popup/'.$params['image'],0777);
		}

		if($params['contents']){
			adjustEditorImages($params['contents']);
		}

		if(!$params['open']){
			$params['open'] = 0;
		}

		if($params['popup_seq']){
			$data = filter_keys($params, $this->db->list_fields('fm_design_popup'));
			$this->db->update('fm_design_popup', $data, "popup_seq = {$params['popup_seq']}");
		}else{
			$params['regdate'] = date('Y-m-d H:i:s');
			$data = filter_keys($params, $this->db->list_fields('fm_design_popup'));
			$this->db->insert('fm_design_popup', $data);
		}

		if($params['popup_seq']){
			$resultMsg = "팝업이 변경되었습니다.";
			if($params['direct']){
				$resultCallback = "top.document.location.reload();document.location.href='about:blank';";
			}else{
				$resultCallback = "top.DM_window_popup_insert('{$params['template_path']}');";
			}
		}else{
			$resultMsg = "팝업이 저장되었습니다.";
			$resultCallback = "top.DM_window_popup_insert('{$params['template_path']}');";
		}

		openDialogAlert($resultMsg,400,140,'parent',$resultCallback);

		//openDialogAlert("팝업이 저장되었습니다.",400,140,'parent',"parent.parent.DM_window_popup_insert('{$params['template_path']}');");
		//openDialogAlert("팝업이 저장되었습니다.",400,140,'parent','parent.parent.document.location.reload()');

	}

	/* 팝업 생성,변경 처리 */
	public function popup_edit_light(){
		$params						= $_POST;
		$params['operation_type']	= $this->config_system['operation_type'];

		// parameter 정리
		if($params['contents_type'] == 'text'){
			unset($params['image']);
			unset($params['s_image']);
			unset($params['s_link']);
			unset($params['s_target']);
		}else{
			unset($params['contents']);
		}

		// 슬라이드 배너 저장 :: 2018-12-18 lwh
		if	($this->config_system['operation_type'] == 'light' && $params['contents_type'] == 'slider'){
			$banner_seq = $params['banner_seq'];
			if(!$banner_seq){
				$this->load->model('designmodel');
				$banner_seq = $this->designmodel->get_new_popup_banner_seq();
			}

			$this->popup_banner_directory_check($banner_seq);

			$this->validation->set_rules('s_image[]', '이미지','trim|required|max_length[255]|xss_clean');

			if($this->validation->exec()===false){
				$err = $this->validation->error_array;
				$callback = "if(parent.document.getElementsByName('{$err['key']}')[0]) parent.document.getElementsByName('{$err['key']}')[0].focus();";
				openDialogAlert($err['value'],400,140,'parent',$callback);
				exit;
			}

			if($params['banner_seq']){
				$saveParams = $params;
				$saveParams['modtime'] = time();
				$data = filter_keys($saveParams, $this->db->list_fields('fm_design_popup_banner'));
				$this->db->update('fm_design_popup_banner', $data, array("banner_seq"=>$banner_seq));
			}else{
				$saveParams = $params;
				$saveParams['banner_seq'] = $banner_seq;
				$saveParams['regdate'] = date('Y-m-d H:i:s');
				$saveParams['modtime'] = time();
				$data = filter_keys($saveParams, $this->db->list_fields('fm_design_popup_banner'));

				$this->db->insert('fm_design_popup_banner', $data);
			}

			$this->popup_banner_directory_check($banner_seq);

			$this->db->delete('fm_design_popup_banner_item', array("banner_seq"=>$banner_seq));

			// 순서에 맞게 이미지명 저장하기 위하여 기존에 있던 이미지들은 임시파일로 저장
			foreach($params['s_image'] as $k=>$image){
				if(!(preg_match("/^(\/data\/tmp)/i",$image))) {
					$ext = explode(".",$image);
					$ext = $ext[count($ext)-1];
					$new_path = "data/tmp/temp_".time().rand(0,9999).".{$ext}";
					chmod(ROOTPATH.$image, 0777);
					copy(ROOTPATH.$image,ROOTPATH.$new_path);
					chmod(ROOTPATH.$new_path,0777);

					// 임시 파일 삭제
					if(!(preg_match("/^(\/admin\/)/i",$image)))	unlink(ROOTPATH.$image);
				}
				$params['s_image'][$k] = preg_match("/^(\/data\/tmp)/i",$image) ? $image : $new_path;
			}

			// 이미지 저장 :: 2019-01-09 lwh
			foreach($params['s_image'] as $k=>$image){
				$data = array();
				$data['banner_seq']	= $banner_seq;
				$data['link']		= $params['s_link'][$k];
				$data['target']		= $params['s_target'][$k];
				$data['image']		= $params['s_image'][$k];
				$data['tab_title']	= $params['tab_title'][$k];

				// if(preg_match("/^(\/data\/tmp)/i",$data['image']) || preg_match("/^(\/admin\/)/i",$data['image'])){ -> 순번이 변경되었을수
				$ext = explode(".",$data['image']);
				$ext = $ext[count($ext)-1];
				$save_path = "popup_banner/{$banner_seq}/images_".($k+1).".{$ext}";
				$new_path = "data/popup/{$save_path}";
				chmod(ROOTPATH.$data['image'],0777);
				copy(ROOTPATH.$data['image'],ROOTPATH.$new_path);
				chmod(ROOTPATH.$new_path,0777);
				$data['image'] = '/'.$new_path;

				$this->db->insert('fm_design_popup_banner_item', $data);
			}
			$params['popup_banner_seq'] = $banner_seq;
		}

		// 팝업 노출 조건 추가 2015-10-01 jhr
		if(!isset($params['view'])) $params['view'] = 'all';
		if(!isset($params['category'])) $params['category'] = 'all';
		if(!isset($params['brand'])) $params['brand'] = 'all';
		if(!isset($params['location'])) $params['location'] = 'all';

		$condition_arr = array();
		$condition_arr['view'] = $params['view'];
		$condition_arr['category'] = $params['category'];
		$condition_arr['brand'] = $params['brand'];
		$condition_arr['location'] = $params['location'];

		switch($params['view']){
			case "category":
				$condition_arr['issueCategoryViewCode'] = $params['issueCategoryViewCode'];
				break;
			case "brand":
				$condition_arr['issueBrandViewCode'] = $params['issueBrandViewCode'];
				break;
			case "goods":
				$condition_arr['issueGoods'] = $params['issueGoods'];
				break;
		}

		if(isset($params['category']) && $params['category'] != 'all') $condition_arr['issueCategoryCode'] = $params['issueCategoryCode'];
		if(isset($params['brand']) && $params['brand'] != 'all') $condition_arr['issueBrandCode'] = $params['issueBrandCode'];
		if(isset($params['location']) && $params['location'] != 'all')  $condition_arr['issueLocationCode'] = $params['issueLocationCode'];

		$params['popup_condition'] = serialize($condition_arr);

		// 띠배너,모바일일 경우 contents_type 은 image 로 고정 2015-10-07 jhr
		if($params['style'] == 'band' || $params['style'] == 'mobile_band'){
			$params['contents_type'] = 'image';
		}

		if($params['popup_seq']){
			$query = $this->db->get_where('fm_design_popup', array('popup_seq'=>$params['popup_seq']));
			$popupData = $query->row_array();
		}else{
			$popupData = array();
		}

		/* 영역 제거 */
		if(isset($params['removeDesignPopupArea']) && $params['removeDesignPopupArea']=='Y'){
			$this->load->model('usedmodel');

			$res = $this->usedmodel->used_limit_check();
			if(!$res['type']){
				openDialogAlert($res['msg'],600,370,'parent');
				exit;
			}

			$this->load->helper('file');
			$tpl_path = ROOTPATH.'data/skin/'.$this->designWorkingSkin.'/'.$params['template_path'];

			// 수정 전에 백업 파일 생성 추가 :: 2019-08-13 pjw
			$this->load->helper('readurl');
			if(!backup_file('data/skin/'.$this->designWorkingSkin.'/'.$params['template_path'])){
				$this->load->helper('readurl');
				openDialogAlert("파일 백업 실패",400,140,'parent');
				exit;
			}

			/* 소스 불러오기 */
			$source = read_file($tpl_path);

			$removeSource = "{=showDesignPopup({$params['popup_seq']})}";

			$source = str_replace($removeSource,"",$source);

			if(!write_file($tpl_path,$source)){
				openDialogAlert("소스 변경 실패",400,140,'parent');
				exit;
			}
		}

		if(trim($params['admin_comment'])==''){
			openDialogAlert("관리용 코멘트는 필수입력 사항입니다.",400,140,'parent');
			exit;
		}

		if($params['contents_type']=='image' && !$popupData['image'] && !$params['newImgPath']){
			openDialogAlert("팝업 이미지를 첨부해주세요",400,140,'parent');
			exit;
		}

		/* 본문 배경이미지 업로드시 */
		if (is_uploaded_file($_FILES['band_background_image']['tmp_name'])) {
			$upload_path = "data/skin/".$this->designWorkingSkin."/images/design";
			$config['upload_path']		= ROOTPATH.$upload_path;
			$file_ext = end(explode('.', $_FILES['band_background_image']['name']));//확장자추출
			$config['allowed_types']	= 'jpg|gif|jpeg|png';
			$config['overwrite']			= TRUE;
			$config['file_name']			= "body_background_image".substr(microtime(), 2, 6).'.'.$file_ext;
			$this->upload->initialize($config);

			if ($this->upload->do_upload('band_background_image')) {
				@chmod($config['upload_path'].'/'.$config['file_name'], 0777);
				$_POST['band_background_image'] = $config['file_name'];
			}else{
				$callback = "";
				openDialogAlert("gif, jpg,jpeg, png 만 가능합니다.",400,140,'parent',$callback);
				exit;
			}
		}

		if($params['band_background_type']=='image'){

			if($params['new_band_background_image']){
				$params['band_background_image'] = basename(str_replace('temp_','popup_bandbg_',$params['new_band_background_image']));
				@copy($_POST['new_band_background_image'],ROOTPATH.'data/popup/'.$params['band_background_image']);
				@chmod(ROOTPATH.'data/popup/'.$params['band_background_image'],0777);

				if($popupData['band_background_image']){
					@unlink(ROOTPATH.'data/popup/'.$popupData['band_background_image']);
				}
			}

			$band_background_image['band_background_image']		= $params['band_background_image'] ? $params['band_background_image'] : $params['o_band_background_image'];
			$params['band_background_color']='';
		}else{
			if($popupData['band_background_image']){
				@unlink(ROOTPATH.'data/popup/'.$popupData['band_background_image']);
			}
			$params['band_background_image']='';
			$params['band_background_image_repeat']='';
			$params['band_background_image_position']='';
		}

		if($params['newImgPath'] && !preg_match('/http[s]*:\/\//',$params['newImgPath'])) {
			$params['image'] = basename(str_replace('temp_','popup_',$params['newImgPath']));

			//새 이미지 파일 압축 18.06.18 kmj
			$tmpExe = explode(".", $params['newImgPath']);
			$exe = end($tmpExe);

			if(extension_loaded('imagick') && ($exe == "jpg" || $exe == "jpeg")){
				$img = new Imagick();
				$img->readImage(ROOTPATH.$params['newImgPath']);
				$img->setImageCompression(Imagick::COMPRESSION_JPEG);
				$img->setImageCompressionQuality(90);
				$img->stripImage();
				$img->writeImage(ROOTPATH.'data/popup/'.$params['image']);
				unset($img);
			} else {
				@copy(ROOTPATH.$params['newImgPath'], ROOTPATH.'data/popup/'.$params['image']);
			}

			@chmod(ROOTPATH.'data/popup/'.$params['image'],0777);
		}

		if($params['contents']){
			adjustEditorImages($params['contents']);
		}

		if(!$params['open']){
			$params['open'] = 0;
		}

		if($params['popup_seq']){
			$data = filter_keys($params, $this->db->list_fields('fm_design_popup'));
			$this->db->update('fm_design_popup', $data, "popup_seq = {$params['popup_seq']}");
		}else{
			$params['regdate'] = date('Y-m-d H:i:s');
			$data = filter_keys($params, $this->db->list_fields('fm_design_popup'));
			$this->db->insert('fm_design_popup', $data);
		}

		if($params['popup_seq']){
			$resultMsg = "팝업이 변경되었습니다.";
			if($params['direct']){
				$resultCallback = "top.document.location.reload();document.location.href='about:blank';";
			}else{
				$resultCallback = "top.DM_window_popup_insert('{$params['template_path']}');";
			}
		}else{
			$resultMsg = "팝업이 저장되었습니다.";
			$resultCallback = "top.DM_window_popup_insert('{$params['template_path']}');";
		}

		openDialogAlert($resultMsg,400,140,'parent',$resultCallback);
	}

	/* 팝업 복사 */
	public function copy_popup(){
		$popup_seq = $_GET['popup_seq'];

		$query = $this->db->query("select * from fm_design_popup where popup_seq = ?",$popup_seq);
		$data = $query->row_array();

		/*슬라이드 배너 추가 2015-10-07 jhr*/
		if(strpos($data['contents_type'],'pc_style') !== FALSE && $data['popup_banner_seq']){
			$this->load->model('designmodel');
			$banner_seq = $this->designmodel->get_new_popup_banner_seq();

			$this->popup_banner_directory_check($banner_seq);

			$before_path = "data/popup/popup_banner/".$data['popup_banner_seq'];
			$new_path	= "data/popup/popup_banner/".$banner_seq;

			//이미지 전체 복사
			folder_copy(ROOTPATH.$before_path,ROOTPATH.$new_path);

			$query = $this->db->query("select * from fm_design_popup_banner where banner_seq = ?",$data['popup_banner_seq']);
			$banner_data = $query->row_array();

			$banner_data['banner_seq'] = $banner_seq;
			$banner_data['regdate'] = date('Y-m-d H:i:s');
			$banner_data['modtime'] = time();

			//이미지 경로 수정
			$banner_data['background_image'] = str_replace('/'.$data['popup_banner_seq'].'/','/'.$banner_seq.'/',$banner_data['background_image']);

			$this->db->insert('fm_design_popup_banner', $banner_data);

			$query = $this->db->query("select * from fm_design_popup_banner_item where banner_seq = ?",$data['popup_banner_seq']);
			$banner_item_data = $query->result_array();

			foreach($banner_item_data as $param){
				unset($param['banner_item_seq']);
				$param['banner_seq'] = $banner_seq;
				$param['image'] = str_replace('/'.$data['popup_banner_seq'].'/','/'.$banner_seq.'/',$param['image']);
				$this->db->insert('fm_design_popup_banner_item', $param);
			}

			$data['popup_banner_seq'] = $banner_seq;
		}

		if($data['image']){
			$imagePathOri = ROOTPATH.'data/popup/'.$data['image'];
			preg_match("/\.[a-zA-Z]{3,4}$/",$data['image'],$matches);
			$data['image'] = "popup_".time().sprintf("%04d",rand(0,9999)).$matches[0];
			@copy($imagePathOri,ROOTPATH.'data/popup/'.$data['image']);
			@chmod(ROOTPATH.'data/popup/'.$data['image'],0777);
		}

		unset($data['popup_seq']);
		$data['regdate'] = date('Y-m-d H:i:s');

		$query = $this->db->insert_string('fm_design_popup', $data);
		$this->db->query($query);

		openDialogAlert("팝업이 복사되었습니다.",400,140,'parent',"parent.load_popup_list();");

	}

	/* 팝업 삭제 */
	public function delete_popup(){
		$popup_seqs = $_GET['popup_seqs'];
		$popup_seqs = explode(',',$popup_seqs);
		foreach($popup_seqs as $popup_seq){
			$query = $this->db->query("select * from fm_design_popup where popup_seq = ?",$popup_seq);
			$data = $query->row_array();

			/*슬라이드 배너 추가 2015-10-07 jhr*/
			if(strpos($data['contents_type'],'pc_style') !== FALSE && $data['popup_banner_seq']){
				$this->load->helper('file');

				$banner_image_path = ROOTPATH."data/popup/popup_banner/{$data['popup_banner_seq']}";
				if(is_dir($banner_image_path)){
					if(delete_files($banner_image_path,true)){
						@rmdir($banner_image_path);
					}
				}

				$this->db->query("delete from fm_design_popup_banner where banner_seq = ?",$data['popup_banner_seq']);
				$this->db->query("delete from fm_design_popup_banner_item where banner_seq = ?",$data['popup_banner_seq']);
			}

			if($data['image']){
				@unlink(ROOTPATH.'data/popup/'.$data['image']);
			}

			$this->db->query("delete from fm_design_popup where popup_seq = ?",$popup_seq);
		}

		openDialogAlert("팝업이 삭제되었습니다.",400,140,'parent',"parent.load_popup_list();");
	}

	/* 플래시 넣기 */
	public function flash_insert(){

		$this->load->helper('file');
		$this->load->model('usedmodel');

		$res = $this->usedmodel->used_limit_check();
		if(!$res['type']){
			openDialogAlert($res['msg'],600,370,'parent');
			exit;
		}

		$params = $_POST;

		if(!$params['flash_seq']){
			openDialogAlert("적용할 플래시를 선택해주세요.",400,140,'parent');
			exit;
		}

		$template_path			= ROOTPATH."data/skin/".$this->designWorkingSkin."/".$params['template_path'];

		$addSource = "{=showDesignFlash({$params['flash_seq']})}";

		/* 소스 불러오기 */
		$source = read_file($template_path);

		if($params['location'] == 'top'){
			$source = $addSource . "\n" . $source;
		}else{
			$source = $source . "\n" . $addSource;
		}

		if(!write_file($template_path,$source)){
			openDialogAlert("적용에 실패했습니다.",400,140,'parent');
			exit;
		}

		if(preg_match("/^layout_/",$params['template_path']) || preg_match("/^board/",$params['template_path']) || preg_match("/^goods/",$params['template_path'])){
			openDialogAlert("적용되었습니다.",400,140,'parent',"parent.parent.document.location.reload();document.location.href='about:blank';");
		}else{
			$locationUrl = $this->layout->get_tpl_path_url($this->designWorkingSkin,$params['template_path']);
			openDialogAlert("적용되었습니다.",400,140,'parent',"parent.parent.document.location.href='{$locationUrl}';");
		}

	}

		/* 플래시 변경 처리 */
	public function flash_edit()
	{
		$this->load->library('SofeeXmlParser');
		$this->load->library('upload');
		$this->load->model('usedmodel');
		$this->load->model('designflashmodel');
		$this->load->helper('file');
		$this->load->helper('readurl');
		$this->load->model('Cachemodel');

		$res = $this->usedmodel->used_limit_check();
		if ( ! $res['type']) {
			openDialogAlert($res['msg'],600,370,'parent');
			exit;
		}

		$params = $this->input->post();

		$flashW = (int) $params['flashW'];
		$flashH = (int) $params['flashH'];
		$seq = (int) $params['flash_seq'];

		$config['allowed_types'] = implode('|', array('jpg','jpeg','png','gif','bmp','tif','pic'));
		$config['max_size']	= $this->config_system['uploadLimit'];

		/* 영역 제거 */
		if (isset($params['removeDesignFlashArea']) && $params['removeDesignFlashArea']=='Y') {
			$tpl_path = ROOTPATH . 'data/skin/' . $this->designWorkingSkin . '/' . $params['template_path'];
			// 수정 전에 백업 파일 생성 추가 :: 2019-08-13 pjw
			if ( ! backup_file('data/skin/' . $this->designWorkingSkin . '/' . $params['template_path'])){
				openDialogAlert("파일 백업 실패",400,140,'parent');
				exit;
			}

			/* 소스 불러오기 */
			$source = read_file($tpl_path);
			$removeSource = "{=showDesignFlash(" . $seq . ")}";
			$source = str_replace($removeSource, "", $source);

			if ( ! write_file($tpl_path, $source)){
				openDialogAlert("소스 변경 실패",400,140,'parent');
				exit;
			}
		}

		$data = $this->designflashmodel->get_flash($seq)->row_array();

		$xmlParser = new SofeeXmlParser();
		$xml_url = get_connet_protocol() . $_SERVER['HTTP_HOST'] . $data['xmlurl'];

		$xmlParser->parseFile($xml_url);
		$tree = $xmlParser->getTree();

		$options = $tree['data']['option'] ? $tree['data']['option'] : $tree['data'];
		$options['flashW']['value'] = $flashW;
		$options['flashH']['value'] = $flashH;
		$items = $tree['data']['item'];

		if ( ! is_numeric(key($tree['data']['item']))) {
			$items[0] = $items;
		}

		if ($items[0]['visual']['value']) {
			$tmp = explode('/', $items[0]['visual']['value']);
			$tmp = array_slice($tmp, 0, 5);
			$dir = implode('/', $tmp);
		} else {
			$tmp = explode('/', $options['title']['value']);
			$tmp = array_slice($tmp, 0, 7);
			$dir = implode('/', $tmp);
		}
		$config['upload_path'] = ROOTPATH . $dir;

		if ($flashW == 0) {
			openDialogAlert("플래시 넓이를 입력해주세요.", 400, 140, 'parent');
			exit;
		} else if($flashH == 0) {
			openDialogAlert("플래시 높이를 입력해주세요.", 400, 140, 'parent');
			exit;
		} else if (isset($_FILES['title']['tmp_name']) && ! $options['title']['value'] && ! $_FILES['title']['tmp_name']) {
			openDialogAlert("타이틀 이미지를 입력하세요.", 400, 140, 'parent');
			exit;
		}

		if (isset($_FILES['visual'])) foreach ($_FILES['visual']['tmp_name'] as $k => $img) {
			if ( ! $img && !$params['ovisual'][$k]) {
				openDialogAlert("플래시 이미지를 입력하세요.", 400, 140, 'parent');
				exit;
			}
		}

		if (isset($_FILES['menuOff'])) foreach ($_FILES['menuOff']['tmp_name'] as $k => $img) {
			if ( ! $img && ! $params['omenuOff'][$k]) {
				openDialogAlert("버튼 이미지를 입력하세요.", 400, 140, 'parent');
				exit;
			}
		}

		if (isset($_FILES['menuOn'])) foreach ($_FILES['menuOn']['tmp_name'] as $k => $img) {
			if ( ! $img && ! $params['omenuOn'][$k]){
				openDialogAlert("롤오버 이미지를 입력하세요.", 400, 140,'parent');
				exit;
			}
		}

		// 타이틀 이미지 업로드
		if ($_FILES['title']['name']) {
			$file_name = "title_" . time();
			$config['file_name'] = $file_name;
			$this->upload->initialize($config, true);
			if ($this->upload->do_upload('title')) {
				if ($options['title']['value']) {
					unlink(ROOTPATH . $options['title']['value']);
				}
				$options['title']['value'] = $dir . '/' . $this->upload->file_name;
			}
		}

		// 좌측 메뉴 이미지 업로드
		if ($_FILES['prevBtn']['name']) {
			$file_name = "left_" . time();
			$config['file_name'] = $file_name;
			$this->upload->initialize($config, true);
			if ($this->upload->do_upload('prevBtn')) {
				if ($options['prevBtn']['value']) {
					unlink(ROOTPATH . $options['prevBtn']['value']);
				}
				$options['prevBtn']['value'] = $dir . '/' . $this->upload->file_name;
			}
		}

		// 우측 메뉴 이미지 업로드
		if ($_FILES['nextBtn']['name']) {
			$file_name = "right_" . time();
			$config['file_name'] = $file_name;
			$this->upload->initialize($config, true);
			if ($this->upload->do_upload('nextBtn')) {
				if ($options['nextBtn']['value']) {
					unlink(ROOTPATH . $options['nextBtn']['value']);
				}
				$options['nextBtn']['value'] = $dir . '/' . $this->upload->file_name;
			}
		}

		// 플래시 파일 업로드
		if ($_FILES['detail']['name'][0]) {
			$file_name = "flash_" . time();
			$config['file_name'] = $file_name;
			$this->upload->initialize($config, true);
			if ($this->upload->do_upload('detail', 0)) {
				if ($options['detail']['value']) {
					unlink(ROOTPATH . $options['detail']['value']);
				}
				$options['detail']['value'] = $dir . '/' . $this->upload->file_name;
			}
		} else if ($params['odetail'][0]) { //원본이 있고 원본과 이름이 다를 경우와 새로등록시
			$options['detail']['value'] = $params['odetail'][0];
		}

		$num = 0;
		foreach ($params['items'] as $k => $val) {
			$num++;//pixpeen add

			if (isset($params['target'][$k])) $new_items[$k]['target']['value'] =  $params['target'][$k];
			if (isset($params['url'][$k])) $new_items[$k]['url']['value'] =  $params['url'][$k];

			// 비주얼-비주얼 파일 업로드
			if ($_FILES['thumb']['name'][$k]) {
				$file_name = "flash_" . time() . $k;
				$config['file_name'] = $file_name;
				$this->upload->initialize($config, true);
				if ($this->upload->do_upload('thumb', $k)) {
					$new_items[$k]['thumb']['value'] = $dir . '/' . $this->upload->file_name;
				}
			} else if(isset($params['othumb'][$k])) { //원본이 있고 원본과 이름이 다를 경우와 새로등록시
				$new_items[$k]['thumb']['value'] = $params['othumb'][$k];
			}

			// 비주얼-플래시 파일 업로드
			if ($_FILES['visual']['name'][$k]) {
				$file_name = "flash_" . time() . $k;
				$config['file_name'] = $file_name;
				$this->upload->initialize($config, true);
				if ($this->upload->do_upload('visual', $k)) {
					$new_items[$k]['visual']['value'] = $dir . '/' . $this->upload->file_name;
				}
			} else {
				//원본이 있고 원본과 이름이 다를 경우와 새로등록시
				if ($params['visual'. $num . 'type'] == 'pix' && (($params['visual' . $num] != $params['old_visual' . $num] && $params['old_visual' . $num]) || ($params['visual' . $num] && ! $params['old_visual' . $num]))) {
					$new_items[$k]['visual']['value'] = $dir . '/' . $params["visual" . $num];
				} else {
					if ($params['old_visual' . $num] && $params['visual' . $num . 'type'] == 'pix') {
						$new_items[$k]['visual']['value'] = $dir . '/' . $params['old_visual' . $num];
					} else {
						$new_items[$k]['visual']['value'] = $params['ovisual'][$k];
					}
				}
			}

			// 롤오버 파일 업로드
			if ($_FILES['menuOn']['tmp_name'][$k]) {
				$file_name = "btn_over_" . time() . $k;
				$config['file_name'] = $file_name;
				$this->upload->initialize($config, true);
				if ($this->upload->do_upload('menuOn', $k)) {
					$new_items[$k]['menuOn']['value'] = $dir . '/' . $this->upload->file_name;
				}
			} else {
				if ($params['menuOn' . $num . 'type'] == 'pix' && (($params['menuOn' . $num] != $params['old_menuOn' . $num] && $params['old_menuOn' . $num]) || ($params['menuOn' . $num] && !$params['old_menuOn' . $num]))) { //원본이 있고 원본과 이름이 다를 경우와 새로등록시
					$new_items[$k]['menuOn']['value'] = $dir . '/' . $params["menuOn" . $num];
				} else if($params['old_menuOn'.$num] || isset($params['omenuOn'][$k])) { // 메뉴 버튼이 없는 스타일이 있어서 수정
					$new_items[$k]['menuOn']['value'] = ($params['old_menuOn' . $num] && $params['menuOn' . $num . 'type'] == 'pix') ? $dir . '/' . $params['old_menuOn' . $num] : $params['omenuOn'][$k];
				}
			}

			// 버튼 업로드
			if ($_FILES['menuOff']['tmp_name'][$k]) {
				$file_name = "btn_" . time() . $k;
				$config['file_name'] = $file_name;
				$this->upload->initialize($config, true);
				if ($this->upload->do_upload('menuOff', $k)) {
					$new_items[$k]['menuOff']['value'] = $dir . '/' . $this->upload->file_name;
				}
			} else {
				if ($params['menuOff' . $num . 'type'] == 'pix' && (($params['menuOff' . $num] != $params['old_menuOff' . $num] && $params['old_menuOff' . $num]) || ($params['menuOff' . $num] && ! $params['old_menuOff' . $num]))) { //원본이 있고 원본과 이름이 다를 경우와 새로등록시
					$new_items[$k]['menuOff']['value'] = $dir . '/' . $params["menuOff" . $num];
				} else if($params['old_menuOff' . $num] || isset($params['omenuOff'][$k])) { // 메뉴 버튼이 없는 스타일이 있어서 수정
					$new_items[$k]['menuOff']['value'] = ($params['old_menuOff' . $num] && $params['menuOff' . $num . 'type'] == 'pix') ? $dir . '/' . $params['old_menuOff' . $num] : $params['omenuOff'][$k];
				}
			}
		} //endfor

		$handle = fopen(ROOTPATH . $data['xmlurl'], "w");
		fwrite($handle,'<?xml version="1.0" encoding="utf-8" ?>'.chr(10));
		fwrite($handle,'<data>'.chr(10));
		if(!isset($options['detail'])){
			fwrite($handle,'<option>'.chr(10));
		}
		foreach($options as $key => $data){
			$item = $data['value'];
			fwrite($handle,'<'.$key.'>'.$item.'</'.$key.'>'.chr(10));
		}
		if(!isset($options['detail'])){
			fwrite($handle,'</option>'.chr(10));
		}
		foreach($new_items as $k => $data){
			fwrite($handle,'<item>'.chr(10));
			foreach($data as $key => $data1){
				$item = $data1['value'];
				fwrite($handle, '<'.$key.'>'.$item.'</'.$key.'>'.chr(10));
			}
			fwrite($handle,'</item>'.chr(10));
		}
		fwrite($handle,'</data>'.chr(10));
		fclose($handle);

		$this->designflashmodel->update_flash(
			array(
				'name' => $params['name'],
				'flashW' => $options['flashW']['value'],
				'flashH' => $options['flashH']['value']
			),
			$seq
		);

		$this->designflashmodel->delete_flash_file($seq);

		foreach ($new_items as $k => $data) {
			foreach ($data as $key => $data1) {
				$item = $data1['value'];
				if ($item && ! in_array($key, array('url', 'target'))) {
					if (preg_match("/\.swf/", $item)) {
						$type = "swf";
					} else if(preg_match("/\.xml/", $item)) {
						$type = "xml";
					} else if(preg_match("/\.js/", $item)) {
						$type = "js";
					} else {
						$type = "img";
					}
					$this->designflashmodel->insert_flash_file(
						array(
							'type' => $type,
							'flash_seq' => $seq,
							'url' => $item
						)
					);
				}
			}
		}

		if($options['title']['value']){
			$this->designflashmodel->insert_flash_file(
				array(
					'type' => 'xml',
					'flash_seq' => $seq,
					'url' => $options['title']['value']
				)
			);
		}

		$this->Cachemodel->cache_delete('flash_banner', 'flash_banner_' . $seq);

		if ($seq) {
			$resultMsg = "플래시가 변경되었습니다.";
			if ($params['direct']) {
				$resultCallback = "top.document.location.reload();document.location.href='about:blank';";
			} else {
				$resultCallback = "top.DM_window_flash_insert('" . $params['template_path'] . "');";
			}
		} else {
			$resultMsg = "플래시가 저장되었습니다.";
			$resultCallback = "top.DM_window_flash_edit('" . $params['template_path'] . "');";
		}

		openDialogAlert($resultMsg, 400, 140, 'parent', $resultCallback);
	}

	/* 플래시 삭제 */
	public function delete_flash(){
		$flash_seqs = $_GET['flash_seqs'];

		$query = $this->db->query("select * from fm_design_flash_file where find_in_set(flash_seq,'{$flash_seqs}')");

		foreach ($query->result_array() as $row)
		{
			if($row['url']){
				$imgsrc	= ROOTPATH.$row['url'];
				if (is_file($imgsrc) === true && preg_match('/\/xml\//',$imgsrc))
				{
					unlink($imgsrc);
					$dirsrc = dirname($imgsrc);
				}
			}
		}

		if(is_dir($dirsrc)===true && preg_match('/\/xml/',$dirsrc) ){
			@rmdir($dirsrc);
		}

		$this->db->query("delete from fm_design_flash where find_in_set(flash_seq,'{$flash_seqs}')");
		$this->db->query("delete from fm_design_flash_file where find_in_set(flash_seq,'{$flash_seqs}')");

		openDialogAlert("플래시가 삭제되었습니다.",400,140,'parent',"parent.document.location.reload();document.location.href='about:blank';");
	}

	/* 플래시 추가 ajax */
	public function flash_add(){
		$result = '<div class="flashmagictr" style="margin-bottom:5px;padding:10px;border: 1px solid #e3e3e3;">
		<input type="hidden" name="items[]" value="'.$_POST['nextKey'].'" />
		<table width="100%">
		<tr>
			<td>
				<table width="100%">
				<colgroup>
				<col align="center" width="80" /><col width="300" /><col align="left" />
				</colgroup>';
		if($_POST['use_thumb'] == 'Y'){
			$result .= '<tr><td>비주얼 :</td><td>';
			$result .= '<input type="file" name="thumb[]" class="line" />';
			$result .= '</td><td></td></tr>';
		}
		if($_POST['use_detail'] == 'Y'){
			$result .= '<tr><td>비주얼大 :</td><td>';
			$result .= '<input type="file" name="detail[]" class="line" />';
			$result .= '</td><td></td></tr>';
		}
		if($_POST['use_visual'] == 'Y'){
			$result .= '<tr><td>플래시 :</td><td>';
			$result .= '<input type="file" name="visual[]" class="line" />';
			$result .= '</td><td></td></tr>';
		}
		if($_POST['use_menuOff'] == 'Y'){
			$result .= '<tr><td>버튼 :</td><td>';
			$result .= '<input type="file" name="menuOff[]" class="line" />';
			$result .= '</td><td></td></tr>';
		}
		if($_POST['use_menuOn'] == 'Y'){
			$result .= '<tr><td>롤오버 :</td><td>';
			$result .= '<input type="file" name="menuOn[]" class="line" />';
			$result .= '</td><td></td></tr>';
		}
		if($_POST['use_link'] == 'Y'){
			$result .= '<tr><td>링크 :</td><td>';
			$result .= '<select name="target[]">';
			$result .= '<option value="_parent">현재창</option>';
			$result .= '<option value="_blank">새창</option>';
			$result .= '</select>';
			$result .= '<input type="text" name="url[]" size="33" title="링크주소를 입력해주세요" class="line" />';
			$result .= '</td><td>';
			$result .= '</td></tr>';
		}
		$result .= '</table></td>';
		$result .= '<td><span class="btn small"><button type="button" id="image_del">삭제하기</button></span></td></tr></table></div>';

		echo $result;
	}

	/* 동영상 변경 처리 */
	public function video_edit(){
		$this->tempate_modules();
		$this->load->helper('file');
		$this->load->model('videofiles');
		$this->load->model('usedmodel');

		$res = $this->usedmodel->used_limit_check();
		if(!$res['type']){
			openDialogAlert($res['msg'],600,370,'parent');
			exit;
		}

		$params = $_POST;
		if($parmas['setMode'] != 'pc'){
			$params['realwidth'] = $params['mobile_width'];
			$params['realheight'] = $params['mobile_height'];
		}else{
			$params['realwidth'] = $params['pc_width'];
			$params['realheight'] = $params['pc_height'];
		}

		/* 영역 제거 */
		if(isset($params['removeDesignVideoArea']) && $params['removeDesignVideoArea']=='Y'){

			$this->load->helper('file');
			$tpl_path = ROOTPATH.'data/skin/'.$this->designWorkingSkin.'/'.$params['template_path'];

			// 수정 전에 백업 파일 생성 추가 :: 2019-08-13 pjw
			$this->load->helper('readurl');
			if(!backup_file('data/skin/'.$this->designWorkingSkin.'/'.$params['template_path'])){
				$this->load->helper('readurl');
				openDialogAlert("파일 백업 실패",400,140,'parent');
				exit;
			}

			/* 소스 불러오기 */
			$source = read_file($tpl_path);

			$removeSource = "{=showDesignVideo({$params['seq']},'{$params['old_realwidth']}X{$params['old_realheight']}')}";

			$source = str_replace($removeSource,"",$source);

			if(!write_file($tpl_path,$source)){
				openDialogAlert("소스 변경 실패",400,140,'parent');
				exit;
			}
		}else{//소스변경
			$removeSource = "{=showDesignVideo({$params['seq']},'{$params['old_realwidth']}X{$params['old_realheight']}')}";
			$addSource = "{=showDesignVideo({$params['seq']},'{$params['realwidth']}X{$params['realheight']}')}";
			$source = str_replace($removeSource,$addSource,$source);
		}

		if($parmas['setMode'] != 'pc'){
			$videofiles['mobile_width']		= $params['realwidth'];//
			$videofiles['mobile_height']		= $params['realheight'];//
		}else{
			$videofiles['pc_width']				= $params['realwidth'];//
			$videofiles['pc_height']			= $params['realheight'];//
		}
		$videofiles['memo']					= $params['memo'];//
		$videofiles['seq']						= $params['seq'];//
		$this->videofiles->videofiles_modify($videofiles);

		openDialogAlert("적용되었습니다.",400,140,'parent','parent.parent.document.location.reload()');
	}

	/* 동영상 넣기 */
	public function video_insert(){

		$this->load->helper('file');
		$this->load->model('videofiles');

		$params = $_POST;

		if(!$params['video_seq']){
			openDialogAlert("적용할 동영상을 선택해주세요.",400,140,'parent');
			exit;
		}

		$template_path			= ROOTPATH."data/skin/".$this->designWorkingSkin."/".$params['template_path'];
		$size = ($params['mobile_width'])?$params['mobile_width'].'X'.$params['mobile_height']:$params['pc_width'].'X'.$params['pc_height'];
		$addSource = "{=showDesignVideo({$params['video_seq']},'{$size}')}";

		/* 소스 불러오기 */
		$source = read_file($template_path);

		if($params['location'] == 'top'){
			$source = $addSource . "\n" . $source;
		}else{
			$source = $source . "\n" . $addSource;
		}

		if(!write_file($template_path,$source)){
			openDialogAlert("적용에 실패했습니다.",400,140,'parent');
			exit;
		}

		if(preg_match("/^layout_/",$params['template_path']) || preg_match("/^board/",$params['template_path']) || preg_match("/^goods/",$params['template_path'])){
			openDialogAlert("적용되었습니다.",400,140,'parent',"parent.parent.document.location.reload();document.location.href='about:blank';");
		}else{
			$locationUrl = $this->layout->get_tpl_path_url($this->designWorkingSkin,$params['template_path']);
			openDialogAlert("적용되었습니다.",400,140,'parent',"parent.parent.document.location.href='{$locationUrl}';");
		}

	}

	/* 동영상연결해제 */
	public function delete_video(){
		$video_seqs = $_GET['video_seqs'];
		$this->db->query("delete from fm_videofiles where find_in_set(seq,'{$video_seqs}')");
		openDialogAlert("동영상이 연결해제되었습니다.",400,140,'parent',"parent.load_video_list();");
	}

	/* 라이브 방송 생성,변경 처리 */
	public function broadcast_edit(){
		$params				= $this->input->post();
		$this->load->model('broadcastdisplay');

		$display_seq		= $params['display_seq'];

		if(trim($params['title'])==''){
			openDialogAlert("타이틀은 필수입력 사항입니다.",400,140,'parent');
			exit;
		}

		if($params['sort']=='direct' && count($params['issueBroadcast'])==0){
			openDialogAlert("방송을 선택해주세요.",400,140,'parent');
			exit;
		}

		/* 영역 제거 */
		if(isset($params['removeDesignDisplayArea']) && $params['removeDesignDisplayArea']=='Y' && isset($display_seq)){

			$this->load->model('usedmodel');

			$res = $this->usedmodel->used_limit_check();
			if(!$res['type']){
				openDialogAlert($res['msg'],600,370,'parent');
				exit;
			}

			$this->load->helper('file');
			$tpl_path = ROOTPATH.'data/skin/'.$this->designWorkingSkin.'/'.$params['template_path'];

			// 수정 전에 백업 파일 생성 추가 :: 2019-08-13 pjw
			$this->load->helper('readurl');
			if(!backup_file('data/skin/'.$this->designWorkingSkin.'/'.$params['template_path'])){
				$this->load->helper('readurl');
				openDialogAlert("파일 백업 실패",400,140,'parent');
				exit;
			}

			/* 소스 불러오기 */
			$source = read_file($tpl_path);

			$removeSource = "/\{(\s*)=(\s*)showDesignBroadcast(\s*)\((\s*){$display_seq}(\s*)\)(\s*)\}/";
			$source = preg_replace($removeSource,"",$source);

			if(!write_file($tpl_path,$source)){
				openDialogAlert("소스 변경 실패",400,140,'parent');
				exit;
			}
		}

		$data = array();
		$data['platform'] = isset($params['platform']) ? $params['platform'] : 'pc';
		$data['title'] = $params['title'];
		$data['status'] = isset($params['status']) ? $params['status'] : 'vod';
		$data['sort'] = isset($params['sort']) ? $params['sort'] : 'visitors';
		$data['style'] = isset($params['style']) ? $params['style'] : 'full';
		$data['count_f'] = isset($params['count_f']) ? $params['count_f'] : '3';
		$data['count_s'] = isset($params['count_s']) ? $params['count_s'] : '5';
		$data['count_w'] = isset($params['count_w']) ? $params['count_w'] : '2';
		$data['count_h'] = isset($params['count_h']) ? $params['count_h'] : '3';
		$data['count_r'] = isset($params['count_r']) ? $params['count_r'] : '1';
		$data['update_date'] = date("Y-m-d H:i:s");

		// 트랜잭션 시작
		$this->db->trans_begin();

		if(!empty($display_seq)) {
			$result = $this->broadcastdisplay->updateBroadcast($data,$display_seq);
		} else {
			$display_seq = $this->broadcastdisplay->insertBroadcast($data);
		}

		if($data['sort'] == 'direct') {
			$this->broadcastdisplay->deleteBroadcastItem($display_seq);
			foreach($params['issueBroadcast'] as $broadcast_seq) {
				$result = $this->broadcastdisplay->insertBroadcastItem($broadcast_seq, $display_seq);
				if(empty($result)) {
					$this->db->trans_rollback();
				}
			}
		}

		// 트랜잭션 종료
		if ($this->db->trans_status() === false)
		{
			$this->db->trans_rollback();
			$resultMsg = "라이브 방송 등록(수정)이 실패되었습니다.";
		} else {
			$this->db->trans_commit();

			if($params['display_seq']){
				$resultMsg = "라이브 방송이 변경되었습니다.";
				$resultCallback = "top.DM_window_broadcast_insert('{$params['template_path']}');";
			}else{
				$resultMsg = "라이브 방송이 저장되었습니다.";
				$resultCallback = "top.DM_window_broadcast_insert('{$params['template_path']}');";
			}
		}
		openDialogAlert($resultMsg,400,140,'parent',$resultCallback);
	}

	/* 라이브방송 삭제 */
	public function delete_broadcast(){
		$this->load->model('broadcastdisplay');

		$display_seqs = $this->input->get('display_seqs');
		$display_seqs = explode(',',$display_seqs);

		$this->broadcastdisplay->deleteBroadcastItem($display_seqs);
		$this->broadcastdisplay->deleteBroadcast($display_seqs);

		openDialogAlert("라이브방송이 삭제되었습니다.",400,140,'parent',"parent.load_display_list();");
	}

	/* 라이브방송 복사 */
	public function copy_broadcast(){
		$display_seq = $this->input->get('display_seq');

		$this->load->model('broadcastdisplay');

		$data = $this->broadcastdisplay->getDisplay(array('display_seq'=>$display_seq));
		$data = $data['0'];

		$resultMsg = "라이브 방송 복사가 실패되었습니다.";

		if($data){
			$this->db->trans_begin();

			unset($data['display_seq']);
			$data['regist_date'] = date('Y-m-d H:i:s');

			$new_display_seq = $this->broadcastdisplay->insertBroadcast($data);
			// 방송목록 가져와서 똑같이 insert
			/* 상품목록 */
			$bc_items = $this->broadcastdisplay->getBroadcastItem($data['display_seq']);

			foreach($bc_items as $item){
				unset($data['bc_item_seq']);
				$result = $this->broadcastdisplay->insertBroadcastItem($item['bs_seq'], $new_display_seq);
			}

			// 트랜잭션 종료
			if ($this->db->trans_status() === false)
			{
				$this->db->trans_rollback();

			} else {
				$this->db->trans_commit();
				$resultMsg = "라이브방송이 복사되었습니다.";
			}
		}

		openDialogAlert($resultMsg,400,140,'parent',"parent.load_display_list();");

	}

	/*  라이브 방송 넣기 */
	public function broadcast_insert(){

		$this->load->helper('file');
		$this->load->model('broadcastdisplay');
		$this->load->model('usedmodel');

		$res = $this->usedmodel->used_limit_check();
		if(!$res['type']){
			openDialogAlert($res['msg'],600,370,'parent');
			exit;
		}

		$params = $this->input->post();
		$location = isset($params['location']) ? $params['location'] : null;

		if(!$params['display_seq']){
			openDialogAlert("적용할 상품디스플레이를 선택해주세요.",400,140,'parent');
			exit;
		}

		$template_path			= ROOTPATH."data/skin/".$this->designWorkingSkin."/".$params['template_path'];

		/* 소스 불러오기 */
		$source = read_file($template_path);

		$addSource = "{=showDesignBroadcast({$params['display_seq']})}";

		if($location == 'top'){
			$source = $addSource . "\n" . $source;
		}else{
			$source = $source . "\n" . $addSource;
		}

		if(!write_file($template_path,$source)){
			openDialogAlert("적용에 실패했습니다.",400,140,'parent');
			exit;
		}

		if(preg_match("/^layout_/",$params['template_path']) || preg_match("/^board/",$params['template_path']) || preg_match("/^goods/",$params['template_path'])){
			openDialogAlert("적용되었습니다.",400,140,'parent',"parent.parent.document.location.reload();document.location.href='about:blank';");
		}else{
			$locationUrl = $this->layout->get_tpl_path_url($this->designWorkingSkin,$params['template_path']);
			openDialogAlert("적용되었습니다.",400,140,'parent',"parent.parent.document.location.href='{$locationUrl}';");
		}
	}

	/*  인스타그램 피드 넣기 */
	public function instagramFeed_insert()
	{
		$this->load->helper('file');

		$params = $this->input->post();
		$location = isset($params['location']) ? $params['location'] : null;

		$template_path = ROOTPATH . 'data/skin/' . $this->designWorkingSkin . '/' . $params['template_path'];

		/* 소스 불러오기 */
		$source = file_get_contents($template_path);

		/* 추가소스 */
		$addContentsArr = [];
		$addContentsArr[] = '<div class="instagramFeedTitleWrap">';
		$addContentsArr[] = ' <h3><span designElement="text">' . $params['title'] . '</span></h3>';
		$addContentsArr[] = '</div>';
		$addContentsArr[] = '{=showInstagramFeed()}';

		$addSource = '';
		foreach ($addContentsArr as $addContent) {
			$addSource .= $addContent . "\n";
		}

		if ($location == 'top') {
			$source = $addSource . "\n" . $source;
		} else {
			$source = $source . "\n" . $addSource;
		}

		if (!write_file($template_path, $source)) {
			openDialogAlert('적용에 실패했습니다.', 400, 140, 'parent');
			exit;
		}

		if (preg_match('/^layout_/', $params['template_path']) || preg_match('/^board/', $params['template_path']) || preg_match('/^goods/', $params['template_path'])) {
			openDialogAlert('적용되었습니다.', 400, 140, 'parent', "parent.parent.document.location.reload();document.location.href='about:blank';");
		} else {
			$locationUrl = $this->layout->get_tpl_path_url($this->designWorkingSkin, $params['template_path']);
			openDialogAlert('적용되었습니다.', 400, 140, 'parent', "parent.parent.document.location.href='{$locationUrl}';");
		}
	}

	/* 상품디스플레이 넣기 */
	public function display_insert(){

		$this->load->helper('file');
		$this->load->model('goodsdisplay');
		$this->load->model('usedmodel');

		$res = $this->usedmodel->used_limit_check();
		if(!$res['type']){
			openDialogAlert($res['msg'],600,370,'parent');
			exit;
		}

		$params = $_POST;
		$location = isset($params['location']) ? $params['location'] : null;

		if(!$params['display_seq']){
			openDialogAlert("적용할 상품디스플레이를 선택해주세요.",400,140,'parent');
			exit;
		}

		$display_data = $this->goodsdisplay->get_display($params['display_seq']);
		$display_data_tab = $this->goodsdisplay->get_display_tab($params['display_seq']);

		if($display_data['platform']=='mobile' && (!$this->mobileMode && !$this->storemobileMode)){
			openDialogAlert("모바일용 상품 디스플레이는 모바일 화면에만 적용 가능합니다.",400,140,'parent');
			exit;
		}

		$template_path			= ROOTPATH."data/skin/".$this->designWorkingSkin."/".$params['template_path'];

		/* 소스 불러오기 */
		$source = read_file($template_path);

		if($params['paging']){
			if	(count($display_data_tab) > 1) {
				openDialogAlert("탭으로 구성된 상품디스플레이는<br>페이징이 되는 상품디스플레이를 만들수 없습니다",400,140,'parent');
				exit;
			}

			if	(preg_match('/goods\//',$params['template_path'])) {
				openDialogAlert("현재 페이지에 페이징이 되는 상품디스플레이는 넣을 수 없습니다.",400,140,'parent');
				exit;
			}

			if	(!in_array($display_data['style'] , array('lattice_a','lattice_b','list','newmatrix','responsible'))) {
				openDialogAlert("현재 상품디스플레이 스타일은 페이징이 되는<br>상품디스플레이는 넣을 수 없습니다.",400,140,'parent');
				exit;
			}

			switch($params['paging']){
				case "1":
					$paging_style = 'style1';
					$perpage = $params['perpage1'];
					break;
				case "2":
					$paging_style = 'style2';
					$perpage = $params['perpage2'];
					break;
				case "3":
					$paging_style = 'style3';
					$perpage = $params['perpage3'];
					break;
			}

			$addSource = "{=showDesignDisplayPaging({$params['display_seq']},$perpage,'$paging_style')}";
			$checkSource = "/\{(\s*)=(\s)*showDesignDisplayPaging(\s*)\((\s*)([0-9])+(\s*),(\s*)[0-9]+(\s*)/";

			if(preg_match($checkSource,$source)){
				openDialogAlert("한 페이지에 페이징이 되는 상품디스플레이는 한개만 넣을 수 있습니다.",400,140,'parent');
				exit;
			}
		}else{
			$addSource = "{=showDesignDisplay({$params['display_seq']})}";
		}

		if($location == 'top'){
			$source = $addSource . "\n" . $source;
		}else{
			$source = $source . "\n" . $addSource;
		}

		if(!write_file($template_path,$source)){
			openDialogAlert("적용에 실패했습니다.",400,140,'parent');
			exit;
		}

		if(preg_match("/^layout_/",$params['template_path']) || preg_match("/^board/",$params['template_path']) || preg_match("/^goods/",$params['template_path'])){
			openDialogAlert("적용되었습니다.",400,140,'parent',"parent.parent.document.location.reload();document.location.href='about:blank';");
		}else{
			$locationUrl = $this->layout->get_tpl_path_url($this->designWorkingSkin,$params['template_path']);
			openDialogAlert("적용되었습니다.",400,140,'parent',"parent.parent.document.location.href='{$locationUrl}';");
		}
	}

	/* 상품디스플레이 생성,변경 처리 */
	public function display_edit(){
		if(!isset($_POST['m_list_use'])) $_POST['m_list_use'] = 'n';
		$this->load->model('designmodel');

		$params				= $_POST;
		$m_params			= $_POST;

		$display_seq		= $params['display_seq'];
		$m_display_seq		= $params['m_display_seq'];

		//개발서버에선 display_seq를 마음대로 조정 할 수 있다
		if	($_SERVER["REMOTE_ADDR"] == "106.246.242.226")
			$display_seq_update = $params['display_seq_update'];


		$image_decorations	= isset($params['image_decoration']) ? $params['image_decoration'] : null;
		$info_setting		= isset($params['info_setting']) ? $params['info_setting'] : null;

		$m_image_decorations = isset($params['m_image_decoration']) ? $params['m_image_decoration'] : null;
		$m_info_setting		= isset($params['m_info_setting']) ? $params['m_info_setting'] : null;

		if	(!isset($params['img_opt_lattice_a']))
			$params['img_opt_lattice_a'] = 0;
		if	(!isset($params['img_opt_rolling_h']))
			$params['img_opt_rolling_h'] = 0;

		if	(!isset($params['img_padding_lattice_a']))
			$params['img_padding_lattice_a'] = 0;
		if	(!isset($params['img_padding_rolling_h']))
			$params['img_padding_rolling_h'] = 0;

		if(empty($params['popup']) && trim($params['admin_comment'])==''){
			openDialogAlert("관리용 코멘트는 필수입력 사항입니다.",400,140,'parent');
			exit;
		}

		/* 영역 제거 */
		if(isset($params['removeDesignDisplayArea']) && $params['removeDesignDisplayArea']=='Y'){

			$this->load->model('usedmodel');

			$res = $this->usedmodel->used_limit_check();
			if(!$res['type']){
				openDialogAlert($res['msg'],600,370,'parent');
				exit;
			}

			$this->load->helper('file');
			$tpl_path = ROOTPATH.'data/skin/'.$this->designWorkingSkin.'/'.$params['template_path'];

			// 수정 전에 백업 파일 생성 추가 :: 2019-08-13 pjw
			$this->load->helper('readurl');
			if(!backup_file('data/skin/'.$this->designWorkingSkin.'/'.$params['template_path'])){
				$this->load->helper('readurl');
				openDialogAlert("파일 백업 실패",400,140,'parent');
				exit;
			}

			/* 소스 불러오기 */
			$source = read_file($tpl_path);

			if($params['perpage']){
				$removeSource = "/\{(\s*)=(\s)*showDesignDisplayPaging(\s*)\((\s*){$display_seq}(\s*),(\s*){$params['perpage']}(\s*)\)(\s*)\}/";
				$source = preg_replace($removeSource,"",$source);
				for($i=1;$i<=3;$i++){
					$removeSource = "/\{(\s*)=(\s)*showDesignDisplayPaging(\s*)\((\s*){$display_seq}(\s*),(\s*){$params['perpage']}(\s*),(\s*)'(\s*)style".$i."(\s*)'(\s*)\)(\s*)\}/";
					$source = preg_replace($removeSource,"",$source);
				}
			}else{
				$removeSource = "/\{(\s*)=(\s*)showDesignDisplay(\s*)\((\s*){$display_seq}(\s*)\)(\s*)\}/";
				$source = preg_replace($removeSource,"",$source);
			}

			if(!write_file($tpl_path,$source)){
				openDialogAlert("소스 변경 실패",400,140,'parent');
				exit;
			}
		}

		if	($params['recommend_flag']) {
			$params['kind']				= preg_replace('/_recommend/','',$params['kind']);
			$m_params['kind']			= preg_replace('/_recommend/','',$m_params['kind']);
		}

		if(isset($image_decorations)){
			$params['image_decorations'] = $image_decorations;
		}

		if(isset($info_setting)){
			foreach($info_setting as $key=>$data){
				$info_setting[$key] = str_replace("\"{","{",str_replace("}\"","}",$data));
			}
			$params['info_settings'] = "[".implode(",",$info_setting)."]";
		}

		if(isset($m_info_setting)){
			foreach($m_info_setting as $key=>$data){
				$m_info_setting[$key] = str_replace("\"{","{",str_replace("}\"","}",$data));
			}
			$m_params['info_settings'] = "[".implode(",",$m_info_setting)."]";
		}

		if	($params['image_decoration_type'] == 'favorite') {
			$ret = $this->designmodel->get_favorite_decorations('image_decoration', 'pc', $params['image_decoration_favorite_key']);
			$params['image_decoration_favorite'] = $ret[0]['decoration'];

			$ret = $this->designmodel->get_favorite_decorations('image_decoration', 'mobile', $params['m_image_decoration_favorite_key']);
			$params['m_image_decoration_favorite'] = $ret[0]['decoration'];
		}

		// 상품정보 라이트형 추가 :: 2018-11-30 lwh
		if	($this->config_system['operation_type'] == 'light'){
			// 기본 값 추가
			if(empty($params['goods_info_style'])){
				$params['goods_info_style'] = 'goods_info_style_1';
			}
			$dir		= 'data/design/';
			$file_info	= file_get_contents($dir.$params['goods_info_style'].'.html');
			$params['goods_decoration_favorite_key']	= $params['goods_info_style'];
			$params['goods_decoration_favorite']		= $file_info;
		}else{
			if	($params['goods_decoration_type'] == 'favorite') {
				$ret = $this->designmodel->get_favorite_decorations('goods_decoration', 'pc', $params['goods_decoration_favorite_key']);
				$params['goods_decoration_favorite'] = $ret[0]['decoration'];

				$ret = $this->designmodel->get_favorite_decorations('goods_decoration', 'mobile', $params['m_goods_decoration_favorite_key']);
				$params['m_goods_decoration_favorite'] = $ret[0]['decoration'];
			}
		}

		if	($params['category_flag']) {
			$category_params['list_style']						= $params['style'];
			$category_params['list_count_w']					= $params['count_w'];
			$category_params['list_count_h']					= $params['count_h'];
			$category_params['list_count_w_lattice_b']			= $params['count_w_lattice_b'];
			$category_params['list_count_h_lattice_b']			= $params['count_h_lattice_b'];
			$category_params['list_count_h_list']				= $params['count_h_list'];
			$category_params['list_image_size']					= $params['image_size'];
			$category_params['list_image_size_lattice_b']		= $params['image_size_lattice_b'];
			$category_params['list_image_size_list']			= $params['image_size_list'];
			$category_params['list_info_settings']				= $params['info_settings'];
			$category_params['list_image_decorations']			= $params['image_decorations'];
			$category_params['image_decoration_type']			= $params['image_decoration_type'];
			$category_params['goods_decoration_type']			= $params['goods_decoration_type'];
			$category_params['image_decoration_favorite_key']	= $params['image_decoration_favorite_key'];
			$category_params['image_decoration_favorite']		= $params['image_decoration_favorite'];
			$category_params['goods_decoration_favorite_key']	= $params['goods_decoration_favorite_key'];
			$category_params['goods_decoration_favorite']		= $params['goods_decoration_favorite'];
			$category_params['list_text_align']					= $params['text_align'];
			$category_params['list_default_sort']				= $params['list_default_sort'];
			$category_params['list_paging_use']					= $params['list_paging_use'];
			$category_params['list_goods_status']				= implode("|",$params['list_goods_status']);
			$category_params['img_opt_lattice_a']				= $params['img_opt_lattice_a'];
			$category_params['img_padding_lattice_a']			= $params['img_padding_lattice_a'];
			$category_params['m_list_use']						= $params['m_list_use'];
			$category_params['m_list_style']					= $params['m_style'];
			$category_params['m_list_count_w']					= $params['m_count_w'];
			$category_params['m_list_count_h']					= $params['m_count_h'];
			$category_params['m_list_count_h_lattice_b']		= $params['m_count_h_lattice_b'];
			$category_params['m_list_count_h_list']				= $params['m_count_h_list'];
			$category_params['m_list_count_r']					= $params['m_count_r'];
			$category_params['m_list_image_size']				= $params['m_image_size'];
			$category_params['m_list_image_size_lattice_b']		= $params['m_image_size_lattice_b'];
			$category_params['m_list_image_size_list']			= $params['m_image_size_list'];
			$category_params['m_list_mobile_h']					= $params['mobile_h'];
			$category_params['m_list_info_settings']			= $m_params['info_settings'];
			$category_params['m_list_image_decorations']		= $params['m_image_decoration'];
			$category_params['m_image_decoration_type']			= $params['m_image_decoration_type'];
			$category_params['m_goods_decoration_type']			= $params['m_goods_decoration_type'];
			$category_params['m_image_decoration_favorite_key']	= $params['m_image_decoration_favorite_key'];
			$category_params['m_image_decoration_favorite']		= $params['m_image_decoration_favorite'];
			$category_params['m_goods_decoration_favorite_key']	= $params['m_goods_decoration_favorite_key'];
			$category_params['m_goods_decoration_favorite']		= $params['m_goods_decoration_favorite'];
			$category_params['m_list_text_align']				= $params['m_text_align'];
			$category_params['m_list_default_sort']				= $params['m_list_default_sort'];
			$category_params['m_list_paging_use']				= $params['m_list_paging_use'];
			$category_params['m_list_goods_status']				= implode("|",$params['m_list_goods_status']);
			$category_params['update_date'] = date('Y-m-d H:i:s');

			if(!isset($data['m_list_use'])) $data['m_list_use'] = 'n';

			if	(!$params['sub_kind']) {
				switch($params['kind']){
					case 'category':
						$data = filter_keys($category_params, $this->db->list_fields('fm_category'));
						$this->db->update('fm_category', $data, "category_code = ".$params['category_code']);
						break;
					case 'brand':
						$data = filter_keys($category_params, $this->db->list_fields('fm_brand'));
						$this->db->update('fm_brand', $data, "category_code = ".$params['category_code']);
						break;
					case 'location':
						$data = filter_keys($category_params, $this->db->list_fields('fm_location'));
						$this->db->update('fm_location', $data, "location_code = ".$params['category_code']);
				}

				$callback = "this.close();";
				openDialogAlert("디스플레이 설정이 저장 되었습니다.",400,140,'parent.parent',$callback);
			}else{
				switch($params['kind']){
					case 'category':
						$this->load->model('categorymodel');
						$data = filter_keys($category_params, $this->db->list_fields('fm_category'));

						$this->db->where("level >",0);
						$this->db->where("ifnull(category_code,'')","");
						$this->db->update('fm_category', $data);

						/* 하위카테고리에 동일하게 적용 */
						$this->categorymodel->childset_category('category','');
						$msg = '세팅된 디스플레이 설정이 전체카테고리에 적용되었습니다.';
						break;
					case 'brand':
						$this->load->model('brandmodel');
						$data = filter_keys($category_params, $this->db->list_fields('fm_brand'));

						$this->db->where("level >",0);
						$this->db->where("ifnull(category_code,'')","");
						$this->db->update('fm_brand', $data);

						/* 하위카테고리에 동일하게 적용 */
						$this->brandmodel->childset_brand('category','');
						$msg = '세팅된 디스플레이 설정이 전체브랜드에 적용되었습니다.';
						break;
					case 'location':
						$this->load->model('locationmodel');
						$data = filter_keys($category_params, $this->db->list_fields('fm_location'));

						$this->db->where("level >",0);
						$this->db->where("ifnull(location_code,'')","");
						$this->db->update('fm_location', $data);

						/* 하위카테고리에 동일하게 적용 */
						$this->locationmodel->childset_location('location','');
						$msg = '세팅된 디스플레이 설정이 전체지역에 적용되었습니다.';

				}

				$callback = "";
				openDialogAlert($msg,500,140,'parent',"");
				die();
			}
		}else{
			if	($display_seq_update) $params['display_seq'] = $display_seq_update;

			// pc 환경인데 mobile_h 값이 0이면 기본값 null 로 저장되도록 unset 처리 @2017-12-14
			if ( $params['platform'] == 'pc' && $params['mobile_h'] == '0') unset($params['mobile_h']);

			if($display_seq){
				$data = filter_keys($params, $this->db->list_fields('fm_design_display'));
				unset($data['auto_use']);

				$this->db->update('fm_design_display', $data, "display_seq = {$display_seq}");

				$this->db->query("delete from fm_design_display_item where display_seq=?",$display_seq);

				//검색 디스플레이 노출 설정 2018-02-14
				if($params['kind'] == 'search'){
					config_save('search',array($params['platform'].'_list_goods_status'=>implode("|",$params['list_goods_status'])));
					config_save('search',array($params['platform'].'_list_default_sort'=>$params['list_default_sort']));
				}

				if($m_display_seq){
					if($m_display_seq){
						if(isset($m_image_decorations)){
							$m_params['image_decorations'] = $m_image_decorations;
						}

						$m_params['style'] = $m_params['m_style'];
						$m_params['image_size'] = $m_params['m_image_size'];
						$m_params['text_align'] = $m_params['m_text_align'];
						$m_params['tab_design_type'] = $m_params['m_tab_design_type'];
						$m_params['platform'] = 'mobile';

						$m_params['image_decoration_type'] = $m_params['m_image_decoration_type'];
						$m_params['image_decoration_favorite_key'] = $m_params['m_image_decoration_favorite_key'];

						if	($m_params['m_image_decoration_type'] == 'favorite') {
							$ret = $this->designmodel->get_favorite_decorations('image_decoration', 'mobile', $m_params['m_image_decoration_favorite_key']);
							$m_params['image_decoration_favorite'] = $ret[0]['decoration'];
						}

						$m_params['goods_decoration_type'] = $m_params['m_goods_decoration_type'];
						$m_params['goods_decoration_favorite_key'] = $m_params['m_goods_decoration_favorite_key'];

						if	($m_params['m_goods_decoration_type'] == 'favorite') {
							$ret = $this->designmodel->get_favorite_decorations('goods_decoration', 'mobile', $m_params['m_goods_decoration_favorite_key']);
							$m_params['goods_decoration_favorite'] = $ret[0]['decoration'];
						}
					}
					$m_data = filter_keys($m_params, $this->db->list_fields('fm_design_display'));
					unset($m_data['auto_use']);
					unset($m_data['display_seq']);
					unset($m_data['m_display_seq']);
					unset($m_data['kind']);
					$this->db->update('fm_design_display', $m_data, "display_seq = {$m_display_seq}");
					$this->db->query("delete from fm_design_display_item where display_seq=?",$m_display_seq);
				}
			}else{
				//카테고리, 브랜드, 지역 한꺼번에 꾸미기
				if($params['sub_kind'] == 'batch' && $params['recommend_flag']){
					$params['regdate'] = date('Y-m-d H:i:s');
					$data = filter_keys($params, $this->db->list_fields('fm_design_display'));
					unset($data['auto_use']);
					$this->db->insert('fm_design_display', $data);

					$display_seq = $this->db->insert_id();

					if	($params['m_list_use'] == 'y') {
						unset($m_data['auto_use']);
						unset($m_data['display_seq']);
						unset($m_data['m_display_seq']);
						$m_params['kind'] .= '_mobile';
						$m_params['platform'] = 'mobile';
						$m_params['regdate'] = date('Y-m-d H:i:s');

						if(isset($m_image_decorations)){
							$m_params['image_decorations'] = $m_image_decorations;
						}

						$m_params['style'] = $m_params['m_style'];
						$m_params['image_size'] = $m_params['m_image_size'];
						$m_params['text_align'] = $m_params['m_text_align'];
						$m_params['tab_design_type'] = $m_params['m_tab_design_type'];
						$m_params['platform'] = 'mobile';

						$m_params['image_decoration_type'] = $m_params['m_image_decoration_type'];
						$m_params['image_decoration_favorite_key'] = $m_params['m_image_decoration_favorite_key'];

						if	($m_params['m_image_decoration_type'] == 'favorite') {
							$ret = $this->designmodel->get_favorite_decorations('image_decoration', 'mobile', $m_params['m_image_decoration_favorite_key']);
							$m_params['image_decoration_favorite'] = $ret[0]['decoration'];
						}

						$m_params['goods_decoration_type'] = $m_params['m_goods_decoration_type'];
						$m_params['goods_decoration_favorite_key'] = $m_params['m_goods_decoration_favorite_key'];

						if	($m_params['m_goods_decoration_type'] == 'favorite') {
							$ret = $this->designmodel->get_favorite_decorations('goods_decoration', 'mobile', $m_params['m_goods_decoration_favorite_key']);
							$m_params['goods_decoration_favorite'] = $ret[0]['decoration'];
						}

						$data = filter_keys($m_params, $this->db->list_fields('fm_design_display'));
						unset($data['auto_use']);
						$this->db->insert('fm_design_display', $data);

						$m_display_seq = $this->db->insert_id();
					}
				}else{
					$params['kind'] = ($params['displaykind'] == 'designvideo' )?'designvideo':'design';
					$params['regdate'] = date('Y-m-d H:i:s');
					$data = filter_keys($params, $this->db->list_fields('fm_design_display'));
					unset($data['auto_use']);
					$this->db->insert('fm_design_display', $data);

					$display_seq = $this->db->insert_id();
				}
			}

			// 이미지업로드
			if($params['popup_tab_design_kind']=='image'){
				$config = array();
				$config['upload_path'] = ROOTPATH."data/icon/goodsdisplay/tabs";
				$config['max_size']	= $this->config_system['uploadLimit'];
				$config['overwrite'] = true;
				$config['allowed_types'] = 'jpg|gif|jpeg|png';

				if(!is_dir($config['upload_path'])) {
					@mkdir($config['upload_path']);
					@chmod($config['upload_path'],0777);
				}

			}

			$this->load->model('goodsdisplay');

			$this->db->query("delete from fm_design_display_tab where display_seq=?",$display_seq);
			$this->db->query("delete from fm_design_display_tab_item where display_seq=?",$display_seq);

			if	($display_seq_update && ($display_seq != $display_seq_update)) $display_seq = $display_seq_update;

			if(!$params['tab_title']) $params['tab_title'] = array('');
			if(count($params['tab_title'])>1){
				foreach($params['tab_title'] as $tab_index => $tab_title){
					$tab_data = array();
					$tab_data['display_seq'] = $display_seq;
					$tab_data['display_tab_index'] = $tab_index;

					$tab_data['tab_title'] = count($params['tab_title']) > 1 ? $tab_title : '';

					// 이미지업로드
					if($params['popup_tab_design_kind']=='image'){
						if($_FILES['new_tab_title_img']['tmp_name'][$tab_index]){
							$config['file_name'] = "tab_{$display_seq}_{$tab_index}";
							$this->upload->initialize($config);
							$this->upload->do_upload('new_tab_title_img',$tab_index);
							$res = $this->upload->data();
							$tab_data['tab_title_img'] = $res['file_name']?$res['file_name']:$params['tab_title_img'][$tab_index];
						}else{
							$tab_data['tab_title_img'] = $params['tab_title_img'][$tab_index];
						}

						if($_FILES['new_tab_title_img_on']['tmp_name'][$tab_index]){
							$config['file_name'] = "tab_{$display_seq}_{$tab_index}_on";
							$this->upload->initialize($config);
							$this->upload->do_upload('new_tab_title_img_on',$tab_index);
							$res = $this->upload->data();
							$tab_data['tab_title_img_on'] = $res['file_name']?$res['file_name']:$params['tab_title_img_on'][$tab_index];
						}else{
							$tab_data['tab_title_img_on'] = $params['tab_title_img_on'][$tab_index];
						}
					}

					$tab_data['contents_type'] = $params['contents_type'][$tab_index];
					$tab_data['auto_use'] = $params['contents_type'][$tab_index] == 'auto' || $params['contents_type'][$tab_index] == 'auto_sub' ? 'y' : 'n';
					$tab_data['auto_condition_use'] = $params['auto_condition_use'][$tab_index];
					$tab_data['auto_criteria'] = $this->goodsdisplay->check_criteria($params['auto_criteria'][$tab_index]);
					$tab_data['tab_contents'] = adjustEditorImages($params['tab_contents'][$tab_index]);
					$tab_data['tab_contents_mobile'] = adjustEditorImages($params['tab_contents_mobile'][$tab_index]);
					$this->db->insert('fm_design_display_tab', $tab_data);
				}
			}elseif(isset($params['contents_type'])){
				$tab_data = array();
				$tab_data['display_seq'] = $display_seq;
				$tab_data['display_tab_index'] = 0;
				$tab_data['tab_title'] = '';
				$tab_data['tab_title_img'] = '';
				$tab_data['tab_title_img_on'] = '';
				$tab_data['contents_type'] = $params['contents_type'][0];
				$tab_data['auto_use'] = $params['contents_type'][0] == 'auto' || $params['contents_type'][0] == 'auto_sub' ? 'y' : 'n';
				$tab_data['auto_condition_use'] = $params['auto_condition_use'][0];
				$tab_data['auto_criteria'] = $tab_data['auto_use'] == 'y' ? $this->goodsdisplay->check_criteria($params['auto_criteria'][0]) : '';
				$tab_data['tab_contents'] = adjustEditorImages($params['tab_contents'][0]);
				$tab_data['tab_contents_mobile'] = adjustEditorImages($params['tab_contents_mobile'][0]);
				$this->db->insert('fm_design_display_tab', $tab_data);
			}

			if(isset($params['auto_goods_seqs'])){
				foreach($params['auto_goods_seqs'] as $tab_index=>$auto_goods_seqs){
					$arr_goods_seqs = explode(",",$auto_goods_seqs);
					foreach($arr_goods_seqs as $goods_seq){
						if($goods_seq){
							$data = array(
								"display_seq" => $display_seq,
								"display_tab_index" => $tab_index,
								"goods_seq" => $goods_seq
							);
							$this->db->insert('fm_design_display_tab_item', $data);
						}
					}
				}
			}

			//모바일 영역 2015-12-28 jhr
			if($m_display_seq){
				$this->db->query("delete from fm_design_display_tab where display_seq=?",$m_display_seq);
				if(!$m_params['m_tab_title']) $m_params['m_tab_title'] = array('');
				if(count($m_params['m_tab_title'])>1){
					foreach($m_params['m_tab_title'] as $tab_index => $tab_title){
						$tab_data = array();
						$tab_data['display_seq'] = $m_display_seq;
						$tab_data['display_tab_index'] = $tab_index;

						$tab_data['tab_title'] = count($m_params['m_tab_title']) > 1 ? $tab_title : '';

						// 이미지업로드
						if($m_params['m_popup_tab_design_kind']=='image'){
							if($_FILES['m_new_tab_title_img']['tmp_name'][$tab_index]){
								$config['file_name'] = "tab_{$m_display_seq}_{$tab_index}";
								$this->upload->initialize($config);
								$this->upload->do_upload('m_new_tab_title_img',$tab_index);
								$res = $this->upload->data();
								$tab_data['tab_title_img'] = $res['file_name']?$res['file_name']:$m_params['m_tab_title_img'][$tab_index];
							}else{
								$tab_data['tab_title_img'] = $m_params['m_tab_title_img'][$tab_index];
							}

							if($_FILES['m_new_tab_title_img_on']['tmp_name'][$tab_index]){
								$config['file_name'] = "tab_{$m_display_seq}_{$tab_index}_on";
								$this->upload->initialize($config);
								$this->upload->do_upload('m_new_tab_title_img_on',$tab_index);
								$res = $this->upload->data();
								$tab_data['tab_title_img_on'] = $res['file_name']?$res['file_name']:$m_params['m_tab_title_img_on'][$tab_index];
							}else{
								$tab_data['tab_title_img_on'] = $m_params['m_tab_title_img_on'][$tab_index];
							}
						}

						$tab_data['contents_type'] = $m_params['m_contents_type'][$tab_index];
						$tab_data['auto_use'] = $m_params['m_contents_type'][$tab_index] == 'auto' || $m_params['m_contents_type'][$tab_index] == 'auto_sub' ? 'y' : 'n';
						$tab_data['auto_condition_use'] = $m_params['m_auto_condition_use'][$tab_index];
						$tab_data['auto_criteria']			= ($tab_data['auto_use'] == 'y') ? $this->goodsdisplay->check_criteria($m_params['m_auto_criteria'][$tab_index]):'';
						$tab_data['tab_contents_mobile'] = adjustEditorImages($m_params['m_tab_contents_mobile'][$tab_index]);
						$this->db->insert('fm_design_display_tab', $tab_data);
					}
				}elseif(isset($m_params['m_contents_type'])){
					$tab_data = array();
					$tab_data['display_seq'] = $m_display_seq;
					$tab_data['display_tab_index'] = 0;
					$tab_data['tab_title'] = '';
					$tab_data['tab_title_img'] = '';
					$tab_data['tab_title_img_on'] = '';
					$tab_data['contents_type'] = $m_params['m_contents_type'][0];
					$tab_data['auto_use'] = $m_params['m_contents_type'][0] == 'auto' || $m_params['m_contents_type'][0] == 'auto_sub' ? 'y' : 'n';
					$tab_data['auto_condition_use'] = $m_params['m_auto_condition_use'][0];
					$tab_data['auto_criteria']			= ($tab_data['auto_use'] == 'y') ? $this->goodsdisplay->check_criteria($m_params['m_auto_criteria'][0]) : '';
					$tab_data['tab_contents_mobile'] = adjustEditorImages($m_params['m_tab_contents_mobile'][0]);
					$this->db->insert('fm_design_display_tab', $tab_data);
				}

				$this->db->query("delete from fm_design_display_tab_item where display_seq=?",$m_display_seq);
				if(isset($m_params['m_auto_goods_seqs'])){
					foreach($m_params['m_auto_goods_seqs'] as $tab_index=>$auto_goods_seqs){
						$arr_goods_seqs = explode(",",$auto_goods_seqs);
						foreach($arr_goods_seqs as $goods_seq){
							if($goods_seq){
								$data = array(
									"display_seq" => $m_display_seq,
									"display_tab_index" => $tab_index,
									"goods_seq" => $goods_seq
								);
								$this->db->insert('fm_design_display_tab_item', $data);
							}
						}
					}
				}
			}

			//카테고리 상품 디스플레이 일괄 저장
			if	($params['sub_kind'] == 'batch') {

				// batch 변수 공통으로 묶음
				// light 일 경우 recommend_display_light_seq 에 저장하도록 수정 :: 2018-11-30 pjw
				$operation_type						= $this->config_system['operation_type'];
				$batch								= array();
				$batch['update_date']				= date('Y-m-d H:i:s');

				if($operation_type == 'light'){
					$batch['recommend_display_light_seq']	= $display_seq;
				}else{
					$batch['recommend_display_seq']			= $display_seq;
					$batch['m_recommend_display_seq']		= $m_display_seq;
				}

				switch($params['kind']){
					case 'category':
						$this->load->model('categorymodel');
						$batch								= filter_keys($batch, $this->db->list_fields('fm_category'));
						$this->db->where("level >",0);
						$this->db->where("ifnull(category_code,'')","");
						$this->db->update('fm_category', $batch);

						/* 하위카테고리에 동일하게 적용 */
						$this->categorymodel->childset_category('recommend','');
						break;
					case 'brand':
						$this->load->model('brandmodel');
						$batch								= filter_keys($batch, $this->db->list_fields('fm_brand'));
						$this->db->where("level >",0);
						$this->db->where("ifnull(category_code,'')","");
						$this->db->update('fm_brand', $batch);

						/* 하위카테고리에 동일하게 적용 */
						$this->brandmodel->childset_brand('recommend','');
						break;
					case 'location':
						$this->load->model('locationmodel');
						$batch								= filter_keys($batch, $this->db->list_fields('fm_location'));

						$this->db->where("level >",0);
						$this->db->where("ifnull(location_code,'')","");
						$this->db->update('fm_location', $batch);

						/* 하위카테고리에 동일하게 적용 */
						$this->locationmodel->childset_location('recommend','');
				}
			}
		}

		if($params['display_seq']){
			$resultMsg = "상품디스플레이가 변경되었습니다.";
			if($params['direct']){
				$resultCallback = "top.document.location.reload();document.location.href='about:blank';";
			}else{
				$resultCallback = "top.DM_window_display_insert('{$params['template_path']}','{$params['displaykind']}','{$params['platform']}');";
			}
		}else{
			$resultMsg = "상품디스플레이가 저장되었습니다.";
			$resultCallback = "top.DM_window_display_insert('{$params['template_path']}','{$params['displaykind']}','{$params['platform']}');";
		}

		if(!empty($params['popup'])){
			$resultCallback = "parent.window.close();";
		}

		// 다중 디자인 디스플레이 저장 :: 2018-11-16 lwh
		if($params['target_codes']){
			if(count($params['target_codes']) > 1){
				$modelName = $params['kind'].'model';

				foreach($params['target_codes'] as $idx => $target_code){
					if($idx > 0){
						// 운영방식 추가 :: 2018-11-30 pjw
						$operation_type	= $this->config_system['operation_type'];

						$this->load->model($modelName);
						$display_seq_arr	= $this->$modelName->{'get_'.$params['kind'].'_recommend_display_seq'}($target_code);

						if($operation_type == 'light'){
							//기존 recommend_display_seq 를 light 용으로 사용
							$now_r_display_seq	= $display_seq_arr['recommend_display_light_seq'];

							$this->db->where_in('display_seq', array($now_r_display_seq));
							$this->db->delete('fm_design_display_tab');
							$this->db->where_in('display_seq', array($now_r_display_seq));
							$this->db->delete('fm_design_display_tab_item');

							// 디스플레이 복사 Responsive
							$query = $this->db->select('*')->where('display_seq', $display_seq)->get('fm_design_display');
							foreach ($query->result_array() as $row){
								unset($row['display_seq']);
								$this->db->where('display_seq', $now_r_display_seq);
								$this->db->update('fm_design_display', $row);
							}

							// 디스플레이 탭 복사 Responsive
							$query = $this->db->select('*')->where('display_seq', $display_seq)->get('fm_design_display_tab');
							foreach ($query->result_array() as $row){
								$row['display_seq'] = $now_r_display_seq;
								$this->db->set($row);
								$this->db->insert('fm_design_display_tab');
							}
							// 디스플레이 탭 아이템 복사 Responsive
							$query = $this->db->select('*')->where('display_seq', $display_seq)->get('fm_design_display_tab_item');
							foreach ($query->result_array() as $row){
								unset($row['display_tab_item_seq']);
								$row['display_seq'] = $now_r_display_seq;
								$this->db->set($row);
								$this->db->insert('fm_design_display_tab_item');
							}

						}else{
							$now_display_seq	= $display_seq_arr['recommend_display_seq'];
							$now_m_display_seq	= $display_seq_arr['m_recommend_display_seq'];

							$this->db->where_in('display_seq', array($now_display_seq,$now_m_display_seq));
							$this->db->delete('fm_design_display_tab');
							$this->db->where_in('display_seq', array($now_display_seq,$now_m_display_seq));
							$this->db->delete('fm_design_display_tab_item');

							// 디스플레이 복사 PC
							$query = $this->db->select('*')->where('display_seq', $display_seq)->get('fm_design_display');
							foreach ($query->result_array() as $row){
								unset($row['display_seq']);
								$this->db->where('display_seq', $now_display_seq);
								$this->db->update('fm_design_display', $row);
							}
							// 디스플레이 복사 Mobile
							$query = $this->db->select('*')->where('display_seq', $m_display_seq)->get('fm_design_display');
							foreach ($query->result_array() as $row){
								unset($row['display_seq']);
								$this->db->where('display_seq', $now_m_display_seq);
								$this->db->update('fm_design_display', $row);
							}
							// 디스플레이 탭 복사 PC
							$query = $this->db->select('*')->where('display_seq', $display_seq)->get('fm_design_display_tab');
							foreach ($query->result_array() as $row){
								$row['display_seq'] = $now_display_seq;
								$this->db->set($row);
								$this->db->insert('fm_design_display_tab');
							}
							// 디스플레이 탭 복사 Mobile
							$query = $this->db->select('*')->where('display_seq', $m_display_seq)->get('fm_design_display_tab');
							foreach ($query->result_array() as $row){
								$row['display_seq'] = $now_m_display_seq;
								$this->db->set($row);
								$this->db->insert('fm_design_display_tab');
							}
							// 디스플레이 탭 아이템 복사 PC
							$query = $this->db->select('*')->where('display_seq', $display_seq)->get('fm_design_display_tab_item');
							foreach ($query->result_array() as $row){
								unset($row['display_tab_item_seq']);
								$row['display_seq'] = $now_display_seq;
								$this->db->set($row);
								$this->db->insert('fm_design_display_tab_item');
							}
							// 디스플레이 탭 아이템 복사 Mobile
							$query = $this->db->select('*')->where('display_seq', $m_display_seq)->get('fm_design_display_tab_item');
							foreach ($query->result_array() as $row){
								unset($row['display_tab_item_seq']);
								$row['display_seq'] = $now_m_display_seq;
								$this->db->set($row);
								$this->db->insert('fm_design_display_tab_item');
							}
						}
					}
				}
			}
			$resultCallback = 'parent.opener.submit_target_update("obj", "modify");'.$resultCallback;
		}

		openDialogAlert($resultMsg,400,140,'parent',$resultCallback);
	}

	public function display_icon_upload(){

		$config['upload_path'] = ROOTPATH."data/icon/goodsdisplay";
		if($_POST['subPath']) $config['upload_path'] .= "/".$_POST['subPath'];

		if(!is_dir($config['upload_path'])) {
			@mkdir($config['upload_path']);
			@chmod($config['upload_path'],0777);
		}

		$config['allowed_types'] = 'jpg|gif|jpeg|png';
		$config['max_size']	= $this->config_system['uploadLimit'];
		//$config['file_name'] = date('YmdHis').rand(0,9);

		$this->upload->initialize($config);
		if ( ! $this->upload->do_upload('displayImageIconImg') ) {
			$err = $this->upload->display_errors();

		}else{
			$fileInfo = $this->upload->data();
			if($_POST['subPath']){
				$callback = "parent.set_display_image_{$_POST['subPath']}();";
			}else{
				$callback = "parent.set_display_image_icon();";
			}
			openDialogAlert("아이콘이 저장 되었습니다.",400,140,'parent',$callback);
		}
	}

	public function display_quick_icon_upload(){

		$config['upload_path'] = ROOTPATH."data/icon/goodsdisplay/quick_shopping";

		if(!is_dir($config['upload_path'])) {
			@mkdir($config['upload_path']);
			@chmod($config['upload_path'],0777);
		}

		$config['allowed_types'] = 'jpg|gif|jpeg|png';
		$config['max_size']	= $this->config_system['uploadLimit'];
		$config['overwrite'] = true;
		//$config['file_name'] = date('YmdHis').rand(0,9);

		foreach($_FILES as $key=>$row){
			if($row['name']){
				$config['file_name'] = str_replace("quick_shopping_icon_","thumb_",$key).".gif";
				$this->upload->initialize($config);
				$this->upload->do_upload($key);
			}
		}

		$quick_shopping = array();
		foreach($_POST['quick_shopping_icon'] as $value){
			$quick_shopping[] = $value;
		}

		if(!$quick_shopping){
			openDialogAlert("이미지를 선택해주세요.",400,140,'parent',$callback);
		}else{
			$quick_shopping_value = "['".implode("','",$quick_shopping)."']";
			$callback = "parent.set_display_quick_icon(\"{$quick_shopping_value}\");";
			echo js($callback);
		}
	}

	/* 상품디스플레이 삭제 */
	public function delete_display(){
		$display_seqs = $this->input->get('display_seqs');
        $this->load->model('goodsdisplay');
        $this->goodsdisplay->delete_display($this->realMobileSkinVersion, $display_seqs);

		openDialogAlert("상품디스플레이가 삭제되었습니다.",400,140,'parent',"parent.load_display_list();");
	}

	/* 상품디스플레이 복사 */
	public function copy_display(){
		$display_seq = $_GET['display_seq'];

		$this->load->model('goodsdisplay');

		$this->goodsdisplay->copy_display($display_seq);

		openDialogAlert("상품디스플레이가 복사되었습니다.",400,140,'parent',"parent.load_display_list();");

	}

	/* 상단바 디자인 설정 저장 JHR */
	public function topBar_design(){
		$allCategory	= $_POST['allCategory']=='on' ? '1' : '0';
		$category		= $_POST['categoryChk']=='on' ? $_POST['category'] : '0';
		$brand			= $_POST['brand']=='on' ? '1' : '0';
		$location		= $_POST['location']=='on' ? '1' : '0';
		$data = "topBar|".$allCategory."|".$category."|".$brand."|".$location;

		skin_configuration_save($this->designWorkingSkin,"topbar",$data);

		$callback = "parent.parent.location.reload();document.location.href='about:blank';";
		openDialogAlert("상단바 디자인 설정이 저장 되었습니다.",400,140,'parent',$callback);
	}

	/* 모바일 메인상단바 탭 만들기 JHR */
	public function mainTopBar_edit(){
		$params = $_POST;
		$tplFilePath = ROOTPATH."data/skin/".$this->designWorkingSkin."/main";
		$tplTabPath = ROOTPATH."data/skin/".$this->designWorkingSkin."/images/topbar/tabs";
		$seq = $params["tab_index"];

		unset($params['tabStyleName']);
		unset($params['tab_cursor_text']);
		unset($params['tab_cursor_img']);
		unset($params['tab_title']);
		unset($params['tab_title_img']);
		unset($params['tab_title_img_on']);
		unset($params['tab_idx']);
		unset($params['tab_img_idx']);
		unset($params['tab_delete']);
		unset($params['tab_filename']);
		unset($params['tab_img_filename']);

		//스킨별로 관리한다 2016-01-14 jhr
		if(!$params["skin"]) $params["skin"] = $this->designWorkingSkin;

		if($params['tab_type'] == "image"){
			$config = array();
			$config['upload_path'] = $tplTabPath;
			$config['max_size']	= $this->config_system['uploadLimit'];
			$config['overwrite'] = true;
			$config['allowed_types'] = 'jpg|gif|jpeg|png';
			if(!is_dir($config['upload_path'])) {
				@mkdir($config['upload_path']);
				@chmod($config['upload_path'],0777);
			}
			if($_FILES['new_tab_img_prev']['tmp_name']){
				$config['file_name'] = "img_arw_prev";
				$this->upload->initialize($config);
				$this->upload->do_upload('new_tab_img_prev');
				$res = $this->upload->data();
				$params['tab_img_prev'] = $res['file_name']?$res['file_name']:$params['tab_img_prev'];
			}
			if($_FILES['new_tab_img_next']['tmp_name']){
				$config['file_name'] = "img_arw_next";
				$this->upload->initialize($config);
				$this->upload->do_upload('new_tab_img_next');
				$res = $this->upload->data();
				$params['tab_img_next'] = $res['file_name']?$res['file_name']:$params['tab_img_next'];
			}
		}

		if(empty($seq)){
			$this->db->insert("fm_topbar_style",$params);
			$seq = $this->db->insert_id();
		}else{
			$this->db->where(array("tab_index"=>$seq));
			$this->db->update("fm_topbar_style",$params);
		}

		$params = $_POST;
		$setPrams = array();
		$i = 0;
		//텍스트와 이미지의 인덱스가 서로 다르다.
		if($params['tab_type'] == "image") $params['tab_idx'] = $params['tab_img_idx'];

		if(!empty($params['tab_title'])){
			foreach($params['tab_title'] as $tab){
				$setPrams["style_index"] = $seq;
				$setPrams["tab_seq"] = $i;
				if($params['tab_type'] == "text"){
					$setPrams["tab_title"] = $tab;
				}else if($params['tab_type'] == "image"){
					$temp = time();
					if($_FILES['new_tab_title_img_on']['tmp_name'][$i]){
						$config['file_name'] = "tab_".$temp.$i."_on";
						$this->upload->initialize($config);
						$this->upload->do_upload('new_tab_title_img_on',$i);
						$res = $this->upload->data();
						$setPrams['tab_title_img_on'] = $res['file_name']?$res['file_name']:$params['tab_title_img'][$i];
					}else{
						$setPrams['tab_title_img_on'] = $params['tab_title_img_on'][$i];
					}
					if($_FILES['new_tab_title_img']['tmp_name'][$i]){
						$config['file_name'] = "tab_".$temp.$i."_off";
						$this->upload->initialize($config);
						$this->upload->do_upload('new_tab_title_img',$i);
						$res = $this->upload->data();
						$setPrams['tab_title_img'] = $res['file_name']?$res['file_name']:$params['tab_title_img'][$i];
					}else{
						$setPrams['tab_title_img'] = $params['tab_title_img'][$i];
					}
				}

				//값 존재 여부
				$query = $this->db->query("select tab_seq from fm_topbar_file where tab_idx=?",$params['tab_idx'][$i]);
				$pprs = $query->row_array();
				$tab_filename = $params['tab_type'] == "text" ? $params["tab_filename"][$i] : $params["tab_img_filename"][$i];

				if(!isset($pprs["tab_seq"])){
					if($tab_filename != "new"){
						$setPrams["tab_filename"] = $tab_filename;
						$this->db->insert("fm_topbar_file",$setPrams);
					}else{
						$temp = time();
						$fileName = "tab_".$temp.$i.".html";
						$setPrams["tab_filename"] = $fileName;
						$this->createTabFile($tplFilePath,$fileName);
						$this->db->insert("fm_topbar_file",$setPrams);
					}
				}else{
					if($pprs["tab_seq"] <= $i){
						$sql = "update fm_topbar_file set tab_seq = tab_seq+1 where tab_seq < '".$pprs['tab_seq']."' and tab_seq >= $i";
					}else{
						$sql = "update fm_topbar_file set tab_seq = tab_seq-1 where tab_seq > '".$pprs['tab_seq']."' and tab_seq <= $i";
					}
					$this->db->query($sql);
					$where["tab_idx"] = $params["tab_idx"][$i];
					$this->db->where($where);

					if($tab_filename != "new"){
						$setPrams["tab_filename"] = $tab_filename;
					}else{
						//파일 생성하여 다시 넣기
						$temp = time();
						$fileName = "tab_".$temp.$i.".html";
						$setPrams["tab_filename"] = $fileName;
						$this->createTabFile($tplFilePath,$fileName);
					}

					$result = $this->db->update("fm_topbar_file",$setPrams);
				}
				$i++;
			}
		}

		//파일삭제도 같이 해준다.
		if(!empty($params["tab_delete"])){
			$query = "select tab_title_img,tab_title_img_on from fm_topbar_file where tab_idx in (".$params["tab_delete"].")";
			$query = $this->db->query($query);
			$pprs = $query->result_array();
			foreach($pprs as $rs){
				foreach($rs as $val){
					$this->deleteFiles($tplTabPath,$val);
				}
			}
			$query = "delete from fm_topbar_file where tab_idx in (".$params["tab_delete"].")";
			$this->db->query($query);
		}

		$callback = "parent.parent.location.reload();";
		openDialogAlert("상단바 디자인 설정이 저장 되었습니다.",400,140,'parent',$callback);
	}

	/* 상단바 파일 삭제 부분 2014-07-23 JHR */
	public function deleteFiles($path,$filename){
		$path = $path."/".$filename;
		if(@is_file($path)) @unlink($path);
	}
	/* 상단바 파일 생성 부분 2014-07-23 JHR */
	public function createTabFile($path,$fName){
		$tabFilePath = $path."/".$fName;
		if(!is_dir($path)){
			@mkdir($path);
			@chmod($path,0777);
		}
		if(!file_exists($tabFilePath)){
			$this->load->helper('file');
			write_file($tabFilePath, '');
			@chmod($tabFilePath,0777);
		}
	}

	/* 카테고리 네비게이션 디자인 설정 저장 */
	public function category_navigation_design(){

		skin_configuration_save($this->designWorkingSkin,"category_type",$_POST['category_type']);

		$callback = "parent.parent.location.reload();document.location.href='about:blank';";
		openDialogAlert("카테고리 네비게이션 디자인 설정이 저장 되었습니다.",400,140,'parent',$callback);
	}

	/* 브랜드 네비게이션 디자인 설정 저장 */
	public function brand_navigation_design(){

		skin_configuration_save($this->workingSkin,"brand_type",$_POST['brand_type']);

		$callback = "parent.parent.location.reload();document.location.href='about:blank';";
		openDialogAlert("브랜드 네비게이션 디자인 설정이 저장 되었습니다.",400,140,'parent',$callback);
	}

	/* 지역 네비게이션 디자인 설정 저장 */
	public function location_navigation_design(){

		skin_configuration_save($this->workingSkin,"location_type",$_POST['location_type']);

		$callback = "parent.parent.location.reload();document.location.href='about:blank';";
		openDialogAlert("지역 네비게이션 디자인 설정이 저장 되었습니다.",400,140,'parent',$callback);
	}

	/* 게시판 넣기 */
	public function lastest_insert(){

		$this->load->helper('file');
		$this->load->model('usedmodel');

		$res = $this->usedmodel->used_limit_check();
		if(!$res['type']){
			openDialogAlert($res['msg'],600,370,'parent');
			exit;
		}

		$params = $_POST;
		$location = isset($params['location']) ? $params['location'] : null;

		if(!$params['lastest_code']){
			openDialogAlert("게시판 넣기 실패",400,140,'parent');
			exit;
		}

		$template_path			= ROOTPATH."data/skin/".$this->designWorkingSkin."/".$params['template_path'];

		$addSource = $params['lastest_code'];

		/* 소스 불러오기 */
		$source = read_file($template_path);

		if($location == 'top'){
			$source = $addSource . "\n" . $source;
		}else{
			$source = $source . "\n" . $addSource;
		}

		if(!write_file($template_path,$source)){
			openDialogAlert("적용에 실패했습니다.",400,140,'parent');
			exit;
		}

		if(preg_match("/^layout_/",$params['template_path']) || preg_match("/^board/",$params['template_path']) || preg_match("/^goods/",$params['template_path'])){
			openDialogAlert("적용되었습니다.",400,140,'parent',"parent.parent.document.location.reload();document.location.href='about:blank';");
		}else{
			$locationUrl = $this->layout->get_tpl_path_url($this->designWorkingSkin,$params['template_path']);
			openDialogAlert("적용되었습니다.",400,140,'parent',"parent.parent.document.location.href='{$locationUrl}';");
		}

	}


	/* 게시판 넣기 추가기능 */
	public function lastest_insert_new(){

		$this->load->helper('file');
		$this->load->model('usedmodel');

		$this->load->model('Boardmanager');
		$this->load->model('membermodel');
		$this->load->model('boardadmin');
		
		$boardid = $this->input->post('boardId');

		if( $boardid == 'goods_qna' ) {
			$this->load->model('Goodsqna','wigetGoodsqna');
		}elseif( $boardid == 'goods_review' ) {
			$this->load->model('Goodsreview','wigetGoodsreview');
		}else{
			$this->load->model('Boardmodel','wigetboardmodel');
		}

		$sc['whereis']	= ' and id = ? ';
		$sc['select']		= ' * ';
		$sc['binds'] = [$boardid];
		$this->manager = $this->Boardmanager->managerdataidck($sc);//게시판정보
		if(!$this->manager['seq']){
			openDialogAlert("잘못된 게시판입니다.",400,140,'parent');
			exit;
		}

		$res = $this->usedmodel->used_limit_check();
		if(!$res['type']){
			openDialogAlert($res['msg'],600,370,'parent');
			exit;
		}

		$params = $_POST;

		$params['title'] = adjustEditorImages($params['title']);// /data/tmp 임시폴더변경 /data/editor
		$params['boardtitle'] = $this->manager['name'];
		$image_decorations = isset($params['image_decoration']) ? $params['image_decoration'] : array();

		if(isset($image_decorations)){
			$params['image_decorations'] = $image_decorations;
		}

		if( $params['showRecommend'] && $this->manager['auth_recommend_use'] != 'Y' ){
			openDialogAlert("게시글 평가항목을 사용하지 않는 게시판입니다.",400,140,'parent','parent.showrecommendck();');
			exit;
		}

		$params['count_w']	= isset($params['count_w']) ? $params['count_w'] : 4;
		$params['count_h']	= isset($params['count_h']) ? $params['count_h'] : 1;
		$params['lineCnt']	= ($params['count_w']*$params['count_h']);

		$latestopt = '';
		$today = date("Y-m-d");

		if($params['boardId'] == 'goods_review'){

			if($params['auto_use'] == 'y' ){//자동
				if($params['auto_review_detail']) $latestopt['auto_review_detail']	= 'auto_review_detail='.$params['auto_review_detail'];
 				//if($params['auto_hit_desc']) $latestopt['auto_hit_desc']	= 'orderby=hit desc';
				//if($params['auto_gid_desc']) $latestopt['auto_gid_desc']	= 'orderby=gid asc';

				if($params['auto_desc'] == 'hit' ) {
					$latestopt['auto_hit_desc']	= 'orderby=hit desc';
					$latestopt['auto_gid_desc']	= '';
				}else{
					$latestopt['auto_gid_desc']	= 'orderby=gid asc';
					$latestopt['auto_hit_desc']	= '';
				}

				if($params['auto_order_seq']) $latestopt['isorder_seq']	= 'isorder_seq='.$params['auto_order_seq'];
				if($params['auto_upload']) $latestopt['isimage']		= 'isimage='.$params['auto_upload'];
				if($params['auto_best']) $latestopt['best']				= 'best='.$params['auto_best'];
				if($params['auto_term'] && $params['auto_term_type'] == 'relative' ){
					$latestopt['rdate_s'] = 'rdate_s='.date("Y-m-d",strtotime("-".$params['auto_term']." day ".$today));
					$latestopt['rdate_f']  = 'rdate_f='.$today;
					$latestopt['auto_term']  = 'auto_term='.$params['auto_term'];
				}else{
					$latestopt['rdate_s'] = 'rdate_s='.$params['auto_start_date'];
					$latestopt['rdate_f']  = 'rdate_f='.$params['auto_end_date'];
				}

			}elseif($params['displayBoard']) {
				$latestopt['isseq'] = ($params['displayBoard'])?'displayBoard='.implode(",",$params['displayBoard']):'displayBoard=';
			}
		}else{
			if($params['none_auto_desc'] == 'hit' ) {
				$latestopt['none_auto_hit_desc']	= 'orderby=hit desc';
				$latestopt['none_auto_gid_desc']	= '';
			}else{
				$latestopt['none_auto_gid_desc']	= 'orderby=gid asc';
				$latestopt['none_auto_hit_desc']	= '';
			}

			if($params['auto_upload']) $latestopt['isimage']		= 'isimage='.$params['auto_upload'];
			if($params['none_auto_term'] && $params['none_auto_term_type'] == 'relative' ){
				$latestopt['rdate_s'] = 'rdate_s='.date("Y-m-d",strtotime("-".$params['none_auto_term']." day ".$today));
				$latestopt['rdate_f']  = 'rdate_f='.$today;
				$latestopt['none_auto_term']  = 'none_auto_term='.$params['none_auto_term'];
			}else{
				$latestopt['rdate_s'] = 'rdate_s='.$params['none_auto_start_date'];
				$latestopt['rdate_f']  = 'rdate_f='.$params['none_auto_end_date'];
			}
		}

		$latestopt['image_w'] = 'image_w='.$params['image_w'];
		$latestopt['image_h']  = 'image_h='.$params['image_h'];

		$location = isset($params['location']) ? $params['location'] : 'top';
		$style		= isset($params['style']) ? $params['style'] : 'display_lattice_a';
		$params['width'] = round(100/$params['count_w']).'%';

		if($style == 'display_lattice_a'){
			$params['toptr'] = '<!--{? (.index_+1) == 1}--><tr class="normal_bbslist"><!--{ / }-->';
			$params['bottomtr'] = '<!--{? (.index_+1) != 0 && ((.index_+1)%'.$params['count_w'].')==0 }--></tr><tr  class="normal_bbslist"><!--{ / }-->';
		}elseif($style == 'display_lattice_b'){
			$params['toptr'] = '<!--{ ? .index_ && .index_ % 2 == 0 }--></tr><tr ><td height="10"></td></tr><tr  class="normal_bbslist"><!--{ / }-->';
			$params['bottomtr'] = '<!--{? (.index_+1) != 0 && ((.index_+1)%'.$params['count_w'].')==0 }--></tr><tr><!--{ / }-->';
		}else{//display_list
			$params['toptr'] = '<!--{ ? .index_ }--><tr><td height="10"></td></tr><!--{ / }-->';
		}

		$params['getBoardData'] = '<!--{@ getBoardData(\''.$params['boardId'].'\',\''.$params['lineCnt'].'\',null,null,\''.$params['strcut'].'\',\''.$params['contentcut'].'\',\''. $params['write_show'].'\',\''. $params['show_name_type'].'\',\''. $params['show_grade_type'].'\',array(\''.implode('\',\'',$latestopt).'\')'.') }-->';

		if($params['showNumber'])$params['Number'] = '{=number_format((.index_+1))}';

		if($params['showImg']) {
			$params['imagelay'] = '{? .filelist }<span class="BoardgoodsDisplayImageWrap"  decoration="'.$params['image_decorations'].'" ><a href="{.wigetboardurl_view}" ><img src="{.filelist}" width="'.$params['image_w'].'" height="'.$params['image_h'].'" onerror="this.src=\'/data/skin/{skin}/images/common/noimage.gif\'" /></a></span>{/}';
		}

		$params['subject'] = '{.subject} {.iconnew} {.iconhot} {.iconfile} {.iconhidden}';
		$params['goodsInfo'] = '<!--{? .goodsInfo }--><a href="{.wigetboardurl_view}" target="'.$params['target'].'">{.goodsInfo.goods_name} </a><br/><!--{/}-->';

		if($params['showView'])$params['hitlay'] = '{=number_format(.hit)}';//조회수

		if($params['showRecommend'] && $this->manager['auth_recommend_use'] == 'Y'  ){//게시글 평가수
			$params['recommendlay'] = '{.recommendlay}';
		}

		if($params['showBuyer'])$params['buyertitle'] = '{.buyertitle}';//구매여부
		if($params['showScore'])$params['scorelay'] = '{.scorelay}';//평점
		if($params['showName'])$params['name'] = '{.name}';//작성자
		if($params['showDate'])$params['r_date'] = '{=substr(.r_date,0,10)}';//날짜
		if($params['showContents'])$params['contents'] = '{.contents}';//내용

		$params['end'] = '<!--{/}-->';

		$params['info_settings'] = isset($params['info_setting']) ? $params['info_setting'] : array();


		$template_path			= ROOTPATH."data/skin/".$this->designWorkingSkin."/".$params['template_path'];

		$html = '';
		$addSource = '';
		$lastest_key = "designLastestNew".uniqid();
		$this->template->assign(array('recent'=>$params,'lastest_key'=>$lastest_key,'template_path'=>base64_encode($params['template_path'])));
		$thisfile = $this->designWorkingSkin."/_modules/lastest/".$style.".html";
		$this->template->template_dir = BASEPATH."../data/skin";
		$this->template->compile_dir	= BASEPATH."../_compile/data";
		$this->template->define(array('lastest'=>$thisfile));
		$addSource = $this->template->fetch("lastest");

		/* 소스 불러오기 */
		$source = read_file($template_path);

		if($location == 'top'){
			$source = "\n".$addSource . "\n" . $source;
		}else{
			$source = $source . "\n" . $addSource."\n";
		}

		if(!write_file($template_path,$source)){
			openDialogAlert("적용에 실패했습니다.",400,140,'parent');
			exit;
		}

		if(preg_match("/^layout_/",$params['template_path']) || preg_match("/^board/",$params['template_path']) || preg_match("/^goods/",$params['template_path'])){
			openDialogAlert("적용되었습니다.",400,140,'parent',"parent.parent.document.location.reload();document.location.href='about:blank';");
		}else{
			$locationUrl = $this->layout->get_tpl_path_url($this->designWorkingSkin,$params['template_path']);
			openDialogAlert("적용되었습니다.",400,140,'parent',"parent.parent.document.location.href='{$locationUrl}';");
		}

	}

	public function mobile_quick_design(){
		$this->load->model('designmodel');

		$skin = $_POST['skin'];
		$kind = $_POST['kind'];
		$mode = $_POST['mode'];

		$skin_configuration = skin_configuration($skin);

		$original_css = $skin_configuration['mobile_version']=='3' ? 'quick_design_ver3_original.css' : 'quick_design_original.css';

		if($skin != $this->designWorkingSkin){
			openDialogAlert("적용하려는 스킨이 디자인작업용스킨으로 설정되어있지 않습니다.",400,140,'parent');
			exit;
		}

		if($kind=='theme'){
			$theme = $_POST['theme'];
			$themecolor = preg_replace("/[0-9]/","",$theme); //숫자삭제
			skin_configuration_save($skin,"themecolor",$themecolor);
			$iconcolor = (str_replace($themecolor,'',$_POST['theme']) == 1)?'2':'3';
			skin_configuration_save($skin,"iconcolor",$iconcolor);//기본검은색
			skin_configuration_save($skin,"skintheme",$theme);//테마저장

			if($skin_configuration['mobile_version']=='3'){
				$oriCssPath = ROOTPATH.'admin/skin/default/css/mobile_ver3_theme/'.$theme.'.css';
			}else{
				$oriCssPath = ROOTPATH.'admin/skin/default/css/mobile_theme/'.$theme.'.css';
			}
			if($mode=='save') $cssPath = $this->designmodel->get_mobile_buttons_css_path();
			else exit;

			$contents = file_get_contents($oriCssPath);
			$contents = "/* \r\n	이 CSS파일은 아이디자인에 의해 강제로 변형되므로 임의수정시 주의하시기 바랍니다. \r\n	원본소스 : /admin/skin/default/css/{$original_css}\r\n*/\r\n\r\n".$contents;
		}else{
			$oriCssPath = ROOTPATH.'admin/skin/default/css/'.$original_css;
			if($mode=='save') $cssPath = $this->designmodel->get_mobile_buttons_css_path();
			else if($mode=='view') $cssPath = '/data/tmp/quick_design_'.time().'.css';
			else exit;

			$contents = file_get_contents($oriCssPath);
			$contents = "/* \r\n	이 CSS파일은 아이디자인에 의해 강제로 변형되므로 임의수정시 주의하시기 바랍니다. \r\n	원본소스 : /admin/skin/default/css/{$original_css}\r\n*/\r\n\r\n".$contents;

			//치환
			$contents = str_replace('{color_header_t1}',$_POST['color_header_t1'],$contents);
			$contents = str_replace('{color_header_b1}',$_POST['color_header_b1'],$contents);
			$contents = str_replace('{color_header_b2}',$_POST['color_header_b2'],$contents);

			$contents = str_replace('{color_side_t1}',$_POST['color_side_t1'],$contents);
			$contents = str_replace('{color_side_b1}',$_POST['color_side_b1'],$contents);
			$contents = str_replace('{color_side_b2}',$_POST['color_side_b2'],$contents);

			$contents = str_replace('{color_subtitle_t1}',$_POST['color_subtitle_t1'],$contents);
			$contents = str_replace('{color_subtitle_b1}',$_POST['color_subtitle_b1'],$contents);
			$contents = str_replace('{color_subtitle_b2}',$_POST['color_subtitle_b2'],$contents);

			$contents = str_replace('{color_tab_t1}',$_POST['color_tab_t1'],$contents);
			$contents = str_replace('{color_tab_b1}',$_POST['color_tab_b1'],$contents);
			$contents = str_replace('{color_tab_b2}',$_POST['color_tab_b2'],$contents);

			$contents = str_replace('{color_btnimportant_t1}',$_POST['color_btnimportant_t1'],$contents);
			$contents = str_replace('{color_btnimportant_b1}',$_POST['color_btnimportant_b1'],$contents);
			$contents = str_replace('{color_btnimportant_b2}',$_POST['color_btnimportant_b2'],$contents);

			$contents = str_replace('{color_btnnormal_t1}',$_POST['color_btnnormal_t1'],$contents);
			$contents = str_replace('{color_btnnormal_b1}',$_POST['color_btnnormal_b1'],$contents);
			$contents = str_replace('{color_btnnormal_b2}',$_POST['color_btnnormal_b2'],$contents);

			$contents = str_replace('{color_btncancel_t1}',$_POST['color_btncancel_t1'],$contents);
			$contents = str_replace('{color_btncancel_b1}',$_POST['color_btncancel_b1'],$contents);
			$contents = str_replace('{color_btncancel_b2}',$_POST['color_btncancel_b2'],$contents);

			$contents = str_replace('{color_btnblack_t1}',$_POST['color_btnblack_t1'],$contents);
			$contents = str_replace('{color_btnblack_b1}',$_POST['color_btnblack_b1'],$contents);
			$contents = str_replace('{color_btnblack_b2}',$_POST['color_btnblack_b2'],$contents);

			$contents = str_replace('{color_btn_t1}',$_POST['color_btn_t1'],$contents);
			$contents = str_replace('{color_btn_b1}',$_POST['color_btn_b1'],$contents);
			$contents = str_replace('{color_btn_b2}',$_POST['color_btn_b2'],$contents);

			$contents = str_replace('{color_selectbox_b1}',$_POST['color_selectbox_b1'],$contents);
			$contents = str_replace('{color_selectbox_b2}',$_POST['color_selectbox_b2'],$contents);

			$contents = str_replace('{color_price_t1}',$_POST['color_price_t1'],$contents);
			$contents = str_replace('{color_price_b1}',$_POST['color_price_b1'],$contents);
			$contents = str_replace('{color_price_b2}',$_POST['color_price_b2'],$contents);

			$contents = str_replace('{color_important_t1}',$_POST['color_important_t1'],$contents);

			$contents = str_replace('{color_footer_t1}',$_POST['color_footer_t1'],$contents);
			$contents = str_replace('{color_footer_t2}',$_POST['color_footer_t2'],$contents);
			$contents = str_replace('{color_footer_b1}',$_POST['color_footer_b1'],$contents);
			$contents = str_replace('{color_footer_b2}',$_POST['color_footer_b2'],$contents);

			if( $_POST['icon_ver'] ) {
				$iconcolor = ( $_POST['icon_ver'] )?$_POST['icon_ver']:'3';
				skin_configuration_save($skin,"iconcolor",$iconcolor);//기본검은색
				$contents = str_replace('{icon_ver}','ver'.$iconcolor,$contents);
			}else{
				$contents = str_replace('{icon_ver}','ver3',$contents);
			}

			if( $skin_configuration['themecolor'] ) $contents = str_replace('{themecolor}','_'.$skin_configuration['themecolor'],$contents);
		}

		@file_put_contents(ROOTPATH.$cssPath,$contents);
		@chmod(ROOTPATH.$cssPath,0777);

		if($mode=='save'){
			openDialogAlert("적용되었습니다.",400,140,'parent',"parent.document.location.href('/admin/design/mobile_quick_design?tpl_path=".urlencode($_GET['tpl_path'])."&kind=direct');document.location.href='about:blank';");
		}
		if($mode=='view'){
			echo "
			<script>
			parent.$('#quick_design_css').load('{$cssPath}')
			</script>
			";
		}

	}

	public function pc_quick_design_image_upload(){

		$buttonFileName = explode('?',basename($_POST['src']));
		$buttonFileName = $buttonFileName[0];

		if($_POST['key']=='icon'){
			$upload_path = "data/icon/goods_status";
		}else{
			$upload_path = "data/skin/".$this->designWorkingSkin."/images/buttons";
		}

		$config['upload_path']	= ROOTPATH.$upload_path;
		$config['allowed_types'] = 'gif|png|jpg';
		$config['overwrite'] = true;
		$config['max_size']	= $this->config_system['uploadLimit'];
		$config['file_name'] = $buttonFileName;
		$this->load->library('Upload');
		$this->upload->initialize($config);

		$filePath = $config['upload_path'].'/'.$config['file_name'];
		if ( ! $this->_datapath_check($filePath)) {
			openDialogAlert("권한이 없습니다.", 400, 140, 'parent');
			exit;
		}
		if ($this->upload->do_upload('image')) {
			@chmod($filePath, 0777);
			$callback = "parent.closeDialog('popImageChoice');parent.$(\"img.imageBtn[code='{$_POST['code']}']\").attr('src','".dirname($_POST['src']).'/'.$buttonFileName.'?'.time()."');";
			openDialogAlert("이미지가 변경 되었습니다.",400,140,'parent',$callback);
		}else{
			$err = $this->upload->display_errors();
			if($err){
				openDialogAlert(is_array($err)?implode('<br />',$err):$err,400,170,'parent','');
			}
		}
	}

	/* 반응형 퀵디자인 저장 :: 2019-05-29 pjw */
	public function responsive_quick_design(){
		$this->load->model('designmodel');

		// 퀵디자인 설정에서 받아온 값 정의
		$css_path		= 'data/skin/'.$this->config_system['workingMobileSkin'].'/css/quick_design.css';
		$param			= $this->input->post();
		$theme_key		= $param['quick_theme_select'];
		$custom_color	= $param['quick_theme_color'];
		$theme_data		= array(
			'custom_color'	=> $custom_color,
			'select_colors'	=> $param,
		);

		// 해당 값으로 css에 assign할 색상배열 가져옴
		$colors = $this->designmodel->get_responsive_select_theme($theme_key, $theme_data);
		$this->template->assign('colors',$colors);
		$this->template->template_dir = ROOTPATH.'admin/skin/default/design';
		$this->template->compile_dir = ROOTPATH.'_compile/';
		$this->template->define(array('tpl'=>'quick_design_css.html'));
		ob_start();
		$this->template->print_('tpl');
		$css_source = ob_get_clean();

		/* minify generated css */
		$css_source = preg_replace('/\/\*[\s\S]*?\*\//', '', $css_source);
		$css_source = preg_replace('/[\t\s]{2,}/', ' ', $css_source);
		$css_source = preg_replace('/(?<=^|\{|\:|\,)\s+|;?\s+(?=$|\}|\:|\,)/', '', $css_source);

		/* add autogenerated disclaimer */
		$css_source = '/* 이 파일은 퀵디자인으로 자동 생성된 파일입니다. 변경사항은 저장이 되지 않습니다. This file is generated by Quick Design. */'.PHP_EOL.$css_source;

		// css 내용을 해당 스킨내 퀵디자인 css 파일에 덮음
		@file_put_contents(ROOTPATH.$css_path, $css_source);
		@chmod(ROOTPATH.$css_path,0777);

		// 선택 테마를 저장
		if($this->designmodel->set_responsive_theme($theme_key, $colors)){
			pageReload('', 'parent');
		}else{
			pageReload('일시적인 오류 입니다 잠시 후 다시 시도해 주세요.', 'parent');
		}
	}

	/* 우측 추천상품 생성,수정 화면 */
	public function recomm_goods_edit(){
		$params = $_POST;

		$this->db->query("delete from fm_design_recommend_item");
		if(isset($params['auto_goods_seqs'])){
			foreach($params['auto_goods_seqs'] as $key=>$auto_goods_seqs){
				if ($auto_goods_seqs) {
					$arr_goods_seqs = explode(",",$auto_goods_seqs);
					$temp_data = array();
					foreach($arr_goods_seqs as $goods_seq){
						$data = array(
							"goods_seq" => $goods_seq
						);
						$temp_data[] = $data;
					}
					$this->db->insert_batch('fm_design_recommend_item', $temp_data);
				}
			}
		}

		$resultMsg = "추천상품이 저장되었습니다.";
		$resultCallback = "parent.parent.document.location.reload();document.location.href='about:blank';";

		if(!empty($params['popup'])){
			$resultCallback = "parent.window.close();";
		}

		openDialogAlert($resultMsg,400,140,'parent',$resultCallback);
	}

	/* 배너 넣기 */
	public function banner_insert(){

		$this->load->helper('file');
		$this->load->model('usedmodel');

		$res = $this->usedmodel->used_limit_check();
		if(!$res['type']){
			openDialogAlert($res['msg'],600,370,'parent');
			exit;
		}

		$params = $_POST;

		if(!$params['banner_seq']){
			openDialogAlert("적용할 배너를 선택해주세요.",400,140,'parent');
			exit;
		}

		$template_path			= ROOTPATH."data/skin/".$this->designWorkingSkin."/".$params['template_path'];

		// 반응형 구분 :: 2018-12-11 lwh
		if	($this->config_system['operation_type'] == 'light'){
			if($params['style_id'] == 'light_style_1')		$cssSlider = 'sliderA';
			else											$cssSlider = 'sliderB';

			$addSource = "\r\n
<!-- 슬라이드 배너 영역 (".$params['style_id']."_".$params['banner_seq'].") :: START -->\n\r
<div class=\"custom_slider ".$cssSlider." center\">\n\r
	{=showDesignBanner(".$params['banner_seq'].")}\n\r
</div>\n\r
<script type=\"text/javascript\">\n\r
$(function() {\n\r
	$('.".$params['style_id']."_".$params['banner_seq']."').not('.slick-initialized').slick({ // $('.light_style_타입num_배너num')에서 '배너num'는 showDesignBanner(배너num)과 반드시 일치해야 합니다\n\r
		dots: true, // 도트 페이징 사용( true 혹은 false )\n\r
		autoplay: true, // 슬라이드 자동( true 혹은 false )\n\r
		speed: 1000, // 슬라이딩 모션 속도 ms( 밀리세컨드, ex. 600 == 0.6초 )\n\r
		fade: true, // 슬라이딩 fade 모션 사용( true 혹은 fasle )\n\r
		autoplaySpeed: 3000 // autoplay 사용시 슬라이드간 시간 ms( 밀리세컨드, ex. 3000 == 3초 )\n\r
		// 이 외 slick 슬라이더의 자세한 옵션사항은 http://kenwheeler.github.io/slick/ 참고\n\r
	});\n\r
});\n\r
</script>\n\r
<!-- 슬라이드 배너 영역 (".$params['style_id']."_".$params['banner_seq'].") :: END -->\n\r
";
		}else{
			$addSource = "{=showDesignBanner({$params['banner_seq']})}";
		}

		/* 소스 불러오기 */
		$source = read_file($template_path);

		if($params['location'] == 'top'){
			$source = $addSource . "\n" . $source;
		}else{
			$source = $source . "\n" . $addSource;
		}

		if(!write_file($template_path,$source)){
			openDialogAlert("적용에 실패했습니다.",400,140,'parent');
			exit;
		}

		if(preg_match("/^layout_/",$params['template_path']) || preg_match("/^board/",$params['template_path']) || preg_match("/^goods/",$params['template_path']) || preg_match("/^promotion/",$params['template_path'])){
			openDialogAlert("적용되었습니다.",400,140,'parent',"parent.parent.document.location.reload();document.location.href='about:blank';");
		}else{
			$locationUrl = $this->layout->get_tpl_path_url($this->designWorkingSkin,$params['template_path']);
			openDialogAlert("적용되었습니다.",400,140,'parent',"parent.parent.document.location.href='{$locationUrl}';");
		}

	}

	/* 배너 디렉토리 체크 */
	public function banner_directory_check($banner_seq){

		// 디렉토리 생성
		if(!is_dir(ROOTPATH."data/skin/{$this->designWorkingSkin}/images/banner")){
			if(!mkdir(ROOTPATH."data/skin/{$this->designWorkingSkin}/images/banner")){
				openDialogAlert("/data/skin/{$this->designWorkingSkin}/images/banner 디렉토리 생성 실패",400,140,'parent');
				exit;
			}
			@chmod(ROOTPATH."data/skin/{$this->designWorkingSkin}/images/banner",0777);
		}

		if($banner_seq){
			if(!is_dir(ROOTPATH."data/skin/{$this->designWorkingSkin}/images/banner/{$banner_seq}")){
				if(!mkdir(ROOTPATH."data/skin/{$this->designWorkingSkin}/images/banner/{$banner_seq}")){
					openDialogAlert("/data/skin/{$this->designWorkingSkin}/images/banner/{$banner_seq} 디렉토리 생성 실패",400,140,'parent');
					exit;
				}
				@chmod(ROOTPATH."data/skin/{$this->designWorkingSkin}/images/banner/{$banner_seq}",0777);
			}
		}
	}

	/* 배너 생성,변경 처리 */
	public function banner_edit(){
		$this->load->model('designmodel');

		$params = $this->input->post();
		$banner_seq = $params['banner_seq'];
		// [반응형스킨] 페이지타입 추가 :: 2018-11-08 pjw
		$page_type	= $params['page_type'];
		if(!$banner_seq) $banner_seq = $this->designmodel->get_new_banner_seq($this->designWorkingSkin);

		if($params['skin']!=$this->designWorkingSkin){
			openDialogAlert("작업중이던 스킨과 현재 디자인작업용으로 설정된 스킨이 다릅니다.",400,140,'parent');
			exit;
		}

		$this->banner_directory_check($banner_seq);

		/* 영역 제거 */
		if(isset($params['removeDesignBannerArea']) && $params['removeDesignBannerArea']=='Y'){

			$this->load->model('usedmodel');

			$res = $this->usedmodel->used_limit_check();
			if(!$res['type']){
				openDialogAlert($res['msg'],600,370,'parent');
				exit;
			}

			$this->load->helper('file');
			$tpl_path = ROOTPATH.'data/skin/'.$this->designWorkingSkin.'/'.$params['template_path'];

			// 수정 전에 백업 파일 생성 추가 :: 2019-08-13 pjw
			$this->load->helper('readurl');
			if(!backup_file('data/skin/'.$this->designWorkingSkin.'/'.$params['template_path'])){
				$this->load->helper('readurl');
				openDialogAlert("파일 백업 실패",400,140,'parent');
				exit;
			}

			/* 소스 불러오기 */
			$source = read_file($tpl_path);

			$removeSource = "/\{(\s*)=(\s*)showDesignBanner(\s*)\((\s*){$banner_seq}(\s*)\)(\s*)\}/";

			$source = preg_replace($removeSource,"",$source);

			if(!write_file($tpl_path,$source)){
				openDialogAlert("소스 변경 실패",400,140,'parent');
				exit;
			}
		}

		$this->validation->set_rules('name', '배너명','trim|required|max_length[20]|xss_clean');
		$this->validation->set_rules('image[]', '이미지','trim|required|max_length[255]|xss_clean');

		if($this->validation->exec()===false){
			$err = $this->validation->error_array;
			$callback = "if(parent.document.getElementsByName('{$err['key']}')[0]) parent.document.getElementsByName('{$err['key']}')[0].focus();";
			openDialogAlert($err['value'],400,140,'parent',$callback);
			exit;
		}

		// 배경이미지 저장
		if(preg_match("/^data\/tmp/i",$params['background_image']) || preg_match("/\/admin\//i",$params['background_image'])){
			$ext = explode(".",$params['background_image']);
			$ext = $ext[count($ext)-1];
			$save_path = "images/banner/{$banner_seq}/background_image.{$ext}";
			$new_path = "data/skin/{$this->designWorkingSkin}/{$save_path}";
			copy(ROOTPATH.$params['background_image'],ROOTPATH.$new_path);
			chmod(ROOTPATH.$new_path,0777);
			$params['background_image'] = $save_path;
		}

		$saveParams = $params;
		$saveParams['page_type'] = $page_type;

		/*
		 * 관리자 위치 : 판매상품 > 브랜드 > 브랜드설정 > 메인 > 슬라이드배너 > 이미지 저장
		 * page_type 값이 brand_main 로 넘어가야 select에 검색 된다.
		 */
		if (
			$page_type === 'brand'
			&& (isset($params['tab']) && $params['tab'] === 'main')
		) {
			$saveParams['page_type'] = $page_type . '_' . $params['tab'];
		}

		if($params['banner_seq']){
			$saveParams['modtime'] = time();

			$data = filter_keys($saveParams, $this->db->list_fields('fm_design_banner'));
			$this->db->update('fm_design_banner', $data, array("skin"=>$this->designWorkingSkin,"banner_seq"=>$banner_seq));
		}else{
			$saveParams['banner_seq'] = $banner_seq;
			$saveParams['skin'] = $this->designWorkingSkin;
			$saveParams['regdate'] = date('Y-m-d H:i:s');
			$saveParams['modtime'] = time();

			$data = filter_keys($saveParams, $this->db->list_fields('fm_design_banner'));
			$this->db->insert('fm_design_banner', $data);
		}

		$this->banner_directory_check($banner_seq);

		$this->db->delete('fm_design_banner_item', array("skin"=>$this->designWorkingSkin,"banner_seq"=>$banner_seq));

		// 순서에 맞게 이미지명 저장하기 위하여 기존에 있던 이미지들은 임시파일로 저장
		foreach($params['image'] as $k=>$image){
			$image = preg_replace('/^\//','',$image);
			if(
			    !(preg_match("/^data\/tmp/i",$image) || preg_match("/^\/admin\//i",$image) )
			    &&
			    strstr($image, "data/skin/{$this->designWorkingSkin}/") === false
		    ) {
		        $image = "data/skin/{$this->designWorkingSkin}/".$image;
		    }
			if(!(preg_match("/^data\/tmp/i",$image))) {
				$ext = explode(".",$image);
				$ext = $ext[count($ext)-1];
				$new_path = "data/tmp/temp_".time().rand(0,9999).".{$ext}";
				copy(ROOTPATH.$image,ROOTPATH.$new_path);
				chmod(ROOTPATH.$new_path,0777);
			}
			$params['image'][$k] = preg_match("/^data\/tmp/i",$image) ? $image : $new_path;

			if($params['navigation_paging_style']=='custom'){
				$active = $params['navigation_image_active'][$k];
				if(!(preg_match("/^data\/tmp/i",$active))) {
					$ext = explode(".",$active);
					$ext = $ext[count($ext)-1];
					$new_path = "data/tmp/temp_".time().rand(0,9999).".{$ext}";
					copy(ROOTPATH.$active,ROOTPATH.$new_path);
					chmod(ROOTPATH.$new_path,0777);
				}
				$params['navigation_image_active'][$k] = preg_match("/^data\/tmp/i",$active) ? $active : $new_path;

				$inactive = $params['navigation_image_inactive'][$k];
				if(!(preg_match("/^data\/tmp/i",$inactive))) {
					$ext = explode(".",$inactive);
					$ext = $ext[count($ext)-1];
					$new_path = "data/tmp/temp_".time().rand(0,9999).".{$ext}";
					copy(ROOTPATH.$inactive,ROOTPATH.$new_path);
					chmod(ROOTPATH.$new_path,0777);
				}
				$params['navigation_image_inactive'][$k] = preg_match("/^data\/tmp/i",$inactive) ? $inactive : $new_path;
			}
		}

		foreach($params['image'] as $k=>$image){
			$data = array();
			$data['banner_seq']	= $banner_seq;
			$data['skin']		= $this->designWorkingSkin;
			$data['link']		= $params['link'][$k];
			$data['target']		= $params['target'][$k];
			$data['tag_ctrl']	= $params['tag_ctrl'][$k];
			$data['image']		= $params['image'][$k];
			$data['tab_image_inactive']		= basename($params['navigation_image_inactive'][$k]);
			$data['tab_image_active']		= basename($params['navigation_image_active'][$k]);

			if(preg_match("/^data\/tmp/i",$data['image']) || preg_match("/^\/admin\//i",$data['image'])){
				$ext = explode(".",$data['image']);
				$ext = $ext[count($ext)-1];
				$save_path = "images/banner/{$banner_seq}/images_".($k+1).".{$ext}";
				$new_path = "data/skin/{$this->designWorkingSkin}/{$save_path}";

				//새 이미지 파일 압축 18.06.18 kmj
				$isTmp = explode(".", $data['image']);
				$isTmp = explode("temp_", $isTmp[0]);
				if(extension_loaded('imagick') && $isTmp[1] < 10000000000 && ($ext == "jpg" || $ext == "jpeg")){
					$img = new Imagick();
					$img->readImage(ROOTPATH.$data['image']);
					$img->setImageCompression(Imagick::COMPRESSION_JPEG);
					$img->setImageCompressionQuality(90);
					$img->stripImage();
					$img->writeImage(ROOTPATH.$new_path);
					unset($img);
				} else {
					if(file_exists(ROOTPATH.$new_path) === true) {
				        unlink(ROOTPATH.$new_path);
				    }
					copy(ROOTPATH.$data['image'],ROOTPATH.$new_path);
				}

				chmod(ROOTPATH.$new_path,0777);
				$data['image'] = $save_path;
			}

			// 페이징이 커스텀일때 이미지업로드
			if($params['navigation_paging_style']=='custom'){
				if(preg_match("/^data\/tmp/i",$params['navigation_image_inactive'][$k]) || preg_match("/^\/admin\//i",$params['navigation_image_inactive'][$k])){
					$ext = explode(".",$params['navigation_image_inactive'][$k]);
					$ext = $ext[count($ext)-1];
					$save_path = "images/banner/{$banner_seq}/tab".($k+1).".{$ext}";
					$new_path = "data/skin/{$this->designWorkingSkin}/{$save_path}";
					copy(ROOTPATH.$params['navigation_image_inactive'][$k],ROOTPATH.$new_path);
					chmod(ROOTPATH.$new_path,0777);
					$data['tab_image_inactive'] = basename($save_path);
				}
				if(preg_match("/^data\/tmp/i",$params['navigation_image_active'][$k]) || preg_match("/^\/admin\//i",$params['navigation_image_active'][$k])){
					$ext = explode(".",$params['navigation_image_active'][$k]);
					$ext = $ext[count($ext)-1];
					$save_path = "images/banner/{$banner_seq}/tab".($k+1)."_on.{$ext}";
					$new_path = "data/skin/{$this->designWorkingSkin}/{$save_path}";
					copy(ROOTPATH.$params['navigation_image_active'][$k],ROOTPATH.$new_path);
					chmod(ROOTPATH.$new_path,0777);
					$data['tab_image_active'] = basename($save_path);
				}
			}

			$this->db->insert('fm_design_banner_item', $data);
		}

		if($params['banner_seq']){
			$resultMsg = "배너가 변경되었습니다.";
			if($params['direct']){
				$resultCallback = "top.document.location.reload();document.location.href='about:blank';";
			}else{
				$resultCallback = "top.DM_window_banner_insert('{$params['template_path']}');";
				$resultCallback = "parent.document.location.reload();document.location.href='about:blank';";
			}
		}else{
			// [반응형스킨] direct 새로고침 추가 :: 2018-11-08 pjw
			$resultMsg = "배너가 저장되었습니다.";
			if($params['direct']){
				$resultCallback = "top.document.location.reload();document.location.href='about:blank';";
			}else{
				$resultCallback = "top.DM_window_banner_insert('{$params['template_path']}');";
			}
		}

		if(!empty($params['popup'])){
			$resultCallback = "parent.window.close();";
		}

		openDialogAlert($resultMsg,400,140,'parent',$resultCallback);
		
		// design cache clean
		cache_clean('design');
	}

	/* 배너 삭제 */
	public function delete_banner(){
		$this->load->helper('file');

		$banner_seqs = explode(",",$_GET['banner_seqs']);

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



			$tpl_path = ROOTPATH.'data/skin/'.$this->designWorkingSkin.'/'.$_GET['template_path'];

			/* 소스 불러오기 */
			$source = read_file($tpl_path);

			// 치환코드 삭제 :: 2019-02-28 lwh
			$removeSource = "/\{(\s*)=(\s*)showDesignBanner(\s*)\((\s*){$banner_seq}(\s*)\)(\s*)\}/";
			$source = preg_replace($removeSource,"",$source);

			// 클래스 삭제 :: 2019-02-28 lwh
			$removeSource = "/\.light\_style\_(1|2)\_{$banner_seq}/";
			$source = preg_replace($removeSource,"",$source);

			if(!write_file($tpl_path,$source)){
				openDialogAlert("소스 변경 실패",400,140,'parent');
				exit;
			}


			$this->db->query("delete from fm_design_banner where skin=? and banner_seq=?",array($this->designWorkingSkin,$banner_seq));
			$this->db->query("delete from fm_design_banner_item where skin=? and banner_seq=?",array($this->designWorkingSkin,$banner_seq));
		}


		openDialogAlert("배너가 삭제되었습니다.",400,140,'parent',"parent.document.location.reload();document.location.href='about:blank';");
	}

	/* 팝업 배너 디렉토리 체크 */
	public function popup_banner_directory_check($banner_seq){
		// 디렉토리 생성
		if(!is_dir(ROOTPATH."data/popup/popup_banner")){
			if(!mkdir(ROOTPATH."data/popup/popup_banner")){
				$result['insert'] = 0;
				$result['msg'] = "/data/popup/popup_banner 디렉토리 생성 실패";
				echo json_encode($result);
				exit;
			}
			@chmod(ROOTPATH."data/popup/popup_banner",0777);
		}

		if($banner_seq){
			if(!is_dir(ROOTPATH."data/popup/popup_banner/{$banner_seq}")){
				if(!mkdir(ROOTPATH."data/popup/popup_banner/{$banner_seq}")){
					$result['insert'] = 0;
					$result['msg'] = "/data/popup/popup_banner/{$banner_seq} 디렉토리 생성 실패";
					echo json_encode($result);
					exit;
				}
				@chmod(ROOTPATH."data/popup/popup_banner/{$banner_seq}",0777);
			}
		}
	}

	/* 팝업 배너 생성,변경 처리 */
	public function popup_banner_edit(){
		$this->load->model('designmodel');

		$params = $_POST;
		$banner_seq = $params['banner_seq'];
		if(!$banner_seq) $banner_seq = $this->designmodel->get_new_popup_banner_seq();

		$this->popup_banner_directory_check($banner_seq);

		$this->validation->set_rules('image[]', '이미지','trim|required|max_length[255]|xss_clean');

		if($this->validation->exec()===false){
			$err = $this->validation->error_array;
			$callback = "if(parent.document.getElementsByName('{$err['key']}')[0]) parent.document.getElementsByName('{$err['key']}')[0].focus();";
			$result['insert'] = 0;
			$result['msg'] = $err['value'];
			$result['callback'] = $callback;
			echo json_encode($result);
			exit;
		}

		// 배경이미지 저장
		if(preg_match("/^data\/tmp/i",$params['background_image']) || preg_match("/\/admin\//i",$params['background_image'])){
			$ext = explode(".",$params['background_image']);
			$ext = $ext[count($ext)-1];
			$save_path = "popup_banner/{$banner_seq}/background_image.{$ext}";
			$new_path = "data/popup/{$save_path}";
			copy(ROOTPATH.$params['background_image'],ROOTPATH.$new_path);
			chmod(ROOTPATH.$new_path,0777);
			$params['background_image'] = $save_path;
		}

		if($params['banner_seq']){
			$saveParams = $params;
			$saveParams['modtime'] = time();
			$data = filter_keys($saveParams, $this->db->list_fields('fm_design_popup_banner'));
			$this->db->update('fm_design_popup_banner', $data, array("banner_seq"=>$banner_seq));
		}else{
			$saveParams = $params;
			$saveParams['banner_seq'] = $banner_seq;
			$saveParams['regdate'] = date('Y-m-d H:i:s');
			$saveParams['modtime'] = time();
			$data = filter_keys($saveParams, $this->db->list_fields('fm_design_popup_banner'));
			$this->db->insert('fm_design_popup_banner', $data);
		}

		$this->popup_banner_directory_check($banner_seq);

		$this->db->delete('fm_design_popup_banner_item', array("banner_seq"=>$banner_seq));

		// 순서에 맞게 이미지명 저장하기 위하여 기존에 있던 이미지들은 임시파일로 저장
		foreach($params['image'] as $k=>$image){
			if(!(preg_match("/^data\/tmp/i",$image) || preg_match("/^\/admin\//i",$image) )) $image = "data/popup/".$image;
			if(!(preg_match("/^data\/tmp/i",$image))) {
				$ext = explode(".",$image);
				$ext = $ext[count($ext)-1];
				$new_path = "data/tmp/temp_".time().rand(0,9999).".{$ext}";
				copy(ROOTPATH.$image,ROOTPATH.$new_path);
				chmod(ROOTPATH.$new_path,0777);
			}
			$params['image'][$k] = preg_match("/^data\/tmp/i",$image) ? $image : $new_path;

			if($params['navigation_paging_style']=='custom'){
				$active = $params['navigation_image_active'][$k];
				if(!(preg_match("/^data\/tmp/i",$active))) {
					$ext = explode(".",$active);
					$ext = $ext[count($ext)-1];
					$new_path = "data/tmp/temp_".time().rand(0,9999).".{$ext}";
					copy(ROOTPATH.$active,ROOTPATH.$new_path);
					chmod(ROOTPATH.$new_path,0777);
				}
				$params['navigation_image_active'][$k] = preg_match("/^data\/tmp/i",$active) ? $active : $new_path;

				$inactive = $params['navigation_image_inactive'][$k];
				if(!(preg_match("/^data\/tmp/i",$inactive))) {
					$ext = explode(".",$inactive);
					$ext = $ext[count($ext)-1];
					$new_path = "data/tmp/temp_".time().rand(0,9999).".{$ext}";
					copy(ROOTPATH.$inactive,ROOTPATH.$new_path);
					chmod(ROOTPATH.$new_path,0777);
				}
				$params['navigation_image_inactive'][$k] = preg_match("/^data\/tmp/i",$inactive) ? $inactive : $new_path;
			}
		}

		foreach($params['image'] as $k=>$image){
			$data = array();
			$data['banner_seq']	= $banner_seq;
			$data['link']		= $params['link'][$k];
			$data['target']		= $params['target'][$k];
			$data['image']		= $params['image'][$k];
			$data['tab_image_inactive']		= basename($params['navigation_image_inactive'][$k]);
			$data['tab_image_active']		= basename($params['navigation_image_active'][$k]);

			if(preg_match("/^data\/tmp/i",$data['image']) || preg_match("/^\/admin\//i",$data['image'])){
				$ext = explode(".",$data['image']);
				$ext = $ext[count($ext)-1];
				$save_path = "popup_banner/{$banner_seq}/images_".($k+1).".{$ext}";
				$new_path = "data/popup/{$save_path}";
				copy(ROOTPATH.$data['image'],ROOTPATH.$new_path);
				chmod(ROOTPATH.$new_path,0777);
				$data['image'] = $save_path;
			}

			// 페이징이 커스텀일때 이미지업로드
			if($params['navigation_paging_style']=='custom'){
				if(preg_match("/^data\/tmp/i",$params['navigation_image_inactive'][$k]) || preg_match("/^\/admin\//i",$params['navigation_image_inactive'][$k])){
					$ext = explode(".",$params['navigation_image_inactive'][$k]);
					$ext = $ext[count($ext)-1];
					$save_path = "popup_banner/{$banner_seq}/tab".($k+1).".{$ext}";
					$new_path = "data/popup/{$save_path}";
					copy(ROOTPATH.$params['navigation_image_inactive'][$k],ROOTPATH.$new_path);
					chmod(ROOTPATH.$new_path,0777);
					$data['tab_image_inactive'] = basename($save_path);
				}
				if(preg_match("/^data\/tmp/i",$params['navigation_image_active'][$k]) || preg_match("/^\/admin\//i",$params['navigation_image_active'][$k])){
					$ext = explode(".",$params['navigation_image_active'][$k]);
					$ext = $ext[count($ext)-1];
					$save_path = "popup_banner/{$banner_seq}/tab".($k+1)."_on.{$ext}";
					$new_path = "data/popup/{$save_path}";
					copy(ROOTPATH.$params['navigation_image_active'][$k],ROOTPATH.$new_path);
					chmod(ROOTPATH.$new_path,0777);
					$data['tab_image_active'] = basename($save_path);
				}
			}

			$this->db->insert('fm_design_popup_banner_item', $data);
		}

		$result['insert'] = 1;
		$result['banner_seq'] = $banner_seq;
		echo json_encode($result);
	}

	public function set_upgrade_select()
	{
		$display_seq = $this->input->get('display_seq');
		$relation_goods_seq = $this->input->get('relation_goods_seq');

		if ($display_seq) {
			$data = [
				'auto_criteria' => 'admin∀type=select_auto,provider=all,month=1,act=recently,min_ea=1'
			];
			
			$this->db->where('display_seq', $display_seq);
			$this->db->update('fm_design_display_tab', $data);
		}

		if ($relation_goods_seq) {
			$data = [
				'relation_criteria' => 'admin∀type=select_auto,provider=all,month=1,act=recently,min_ea=1',
				'auto_condition_use' => '1'
			];
			
			$this->db->where('goods_seq', $relation_goods_seq);
			$this->db->update('fm_goods', $data);
		}
	}

	public function favorite_decorations_save(){
		$this->load->model('designmodel');

		$params['favorite_type']		= $_POST['favorite_type'];
		$params['platform']				= $_POST['platform'];
		$params['favorite_act']			= $_POST['favorite_act'];
		$params['decoration']			= $_POST['decoration'];
		$params['text_align']			= $_POST['text_align'];
		$params['key']					= 'decoration_'.time();
		$params['key_fix']				= $_POST['favorite_key'];
		$params['favorite_title']		= $_POST['favorite_title'];
		$params['favorite_desc']		= $_POST['favorite_desc'];
		$params['favorite_regist']		= date('Y-m-d H:i:s');
		$params['favorite_update']		= date('Y-m-d H:i:s');

		$result = $this->designmodel->favorite_decorations_save($params);

		if	($params['favorite_type'] == 'goods_decoration') {
			foreach($result as $key => $val){
				$val['decoration'] = str_replace("\\\"","\"",$val['decoration']);
				$val['decoration'] = str_replace("\"{","{",$val['decoration']);
				$val['decoration'] = str_replace("}\"","}",$val['decoration']);
				$result[$key] = $val;
			}
		}

		echo base64_encode(json_encode($result));
	}

	public function set_default_value(){
		$this->load->model('designmodel');
		$new_skin = $_POST['skin'];
		$new_skin_path = ROOTPATH."data/skin/".$new_skin;
		$ret = $this->designmodel->set_default_goods(false,$new_skin_path);
		echo $ret ? 'pass' : 'fail';
	}

	// [반응형스킨] 기본 스킨 타입 설정 :: 2018-10-31 pjw
	function set_default_skintype(){
		$skin_type   = $_POST['skin_type'];

		if(!empty($skin_type)){
			$this->load->model('designmodel');
			$this->designmodel->set_default_skintype($skin_type);
			openDialogAlert("적용되었습니다.",400,140,'parent',"parent.parent.document.location.reload();");
		}else{
			openDialogAlert("스킨타입을 선택해주세요.",400,140,'parent',"parent.parent.document.location.reload();");
		}

		exit;
	}

	// 텍스트 수정기능 신규 추가 :: 2019-01-25 pjw
	function text_edit(){
		// html dom 파서
		$this->load->helper('html_dom_helper');
		$this->load->helper('file');

		// 기본 파라미터 가져온
		$template_path	= $this->input->post('template_path');
		$txt			= $this->input->post('txt');
		$txt_index		= $this->input->post('txt_index');
		$tag_name		= $this->input->post('tag_name');
		$link			= $this->input->post('link');
		$link			= base64_decode($link);
		$target			= $this->input->post('target');

		// xssfilter 및 유효성 검사
		$this->validation->set_rules('txt', '내용','trim|xss_clean');
		$this->validation->set_rules('link', '링크','trim|xss_clean');
		$this->validation->set_rules('target', '타겟','trim|xss_clean');

		if($this->validation->exec()===false){
			$err = $this->validation->error_array;
			$callback = "if(parent.document.getElementsByName('{$err['key']}')[0]) parent.document.getElementsByName('{$err['key']}')[0].focus();";
			openDialogAlert($err['value'],400,140,'parent',$callback);
			exit;
		}

		if(preg_match("/<script(.*?)<\/script>/is",htmlspecialchars_decode($txt))) {
			openDialogAlert("스크립트 태그는 사용할 수 없습니다.",400,140,'parent');
			exit;
		}

		// 해당 템플릿 파일 로드
		$file_path = ROOTPATH."data/skin/".$this->designWorkingSkin.'/'.$template_path;
		if(!file_exists($file_path)){
			openDialogAlert("파일경로를 찾을 수 없습니다.",400,140,'parent',"parent.parent.document.location.reload();");
			exit;
		}

		$origin_text	= ''; // 원본 소스

		// 템플릿 소스 읽음
		$fp = fopen($file_path, "r");

		// 파일이 정상적으로 오픈 되었을 때 실행
		if($fp){
			while(!feof($fp)) {
		        $origin_text .= fgets($fp,2048);
		    }
		}
		fclose($fp);

		// 파일 내용을 html 파싱
		$html = str_get_html($origin_text,false,false,"utf-8", false);

		// 수정할 텍스트객체 index로 검색 후 치환
		// txt 빈값일때 <br> 제거
		$txt = $txt == '<br>' ? '' : $txt;
		$txt = preg_replace("/designelement/", 'designElement', $txt);
		$html->find('[designElement="text"]', $txt_index)->innertext = $txt;

		// 수정할 링크 index로 검색 후 치환
		if(!empty($link)){
			$html->find('[designElement="text"]', $txt_index)->href = $link;
		}

		// 수정할 링크 타겟 index로 검색 후 치환
		if(!empty($target)){
			$html->find('[designElement="text"]', $txt_index)->target = $target;
		}

		// 수정된 내용으로 파일 저장
		if(!write_file($file_path, $html->__toString(), 'w')){
			openDialogAlert("파일 저장 실패.",400,140,'parent',"parent.parent.document.location.reload();");
		}else{
			openDialogAlert("텍스트가 수정되었습니다.",400,140,'parent',"parent.parent.document.location.reload();");
		}

		@chmod($file_path,0777);

		$html->clear();
		unset($html);
	}
}



/* End of file design_process.php */
/* Location: ./app/controllers/admin/design_process.php */