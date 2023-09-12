<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');
require_once(APPPATH ."controllers/base/admin_base".EXT);

class webftp extends admin_base {

	var $dataPath;
	var $arrAbsoluteFolders;

	public function __construct() {
		parent::__construct();

		/* 루트폴더 정의 */
		$this->dataPath = 'data';

		/* 삭제 불가능 폴더 체크 */
		$this->arrAbsoluteFolders = array(
			'data',
			'data/favicon',
			'data/skin',
			'data/tmp',
		);

		/* 이미지파일 확장자 */
		$this->arrImageExtensions = array('jpg','jpeg','png','svg','gif','bmp','tif','pic');
		$this->arrImageExtensions = array_merge($this->arrImageExtensions,array_map('strtoupper',$this->arrImageExtensions));

		/* 소스파일 확장자 */
		$this->arrSourceExtensions = array('html','htm','txt','css','js');
		$this->arrSourceExtensions = array_merge($this->arrSourceExtensions,array_map('strtoupper',$this->arrSourceExtensions));

	}

	/* WEB FTP 메인화면 */
	public function catalog() {
		$this->admin_menu();
		$this->tempate_modules();

		$this->template->define(array('webftp'=>$this->skin.'/webftp/_webftp.html'));

		$this->template->define(array('tpl'=>$this->template_path()));
		$this->template->print_("tpl");
	}

	/* data경로 체크 */
	public function _datapath_check($path)
	{
		$result = false;
		$realpath	= realpath($path);

		/* data폴더 하위가 맞으면 true 아니면 false */
		if( preg_match("/\/".$this->dataPath."/", $realpath) ){
			$result = true;
		}

		return $result;
	}


	/* jstree ajax 처리 */
	public function process(){

		$this->load->helper('directory');

		switch($_GET['operation']){
			case 'get_folder_children':
				$result = $this->_get_folder_children();
			break;
			case 'create_folder':
				$result = $this->_create_folder();
			break;
			case 'remove_folder':
				$result = $this->_remove_folder();
			break;
			case 'rename_folder':
				$result = $this->_rename_folder();
			break;
			case 'get_image_file_list':
				$result = $this->_get_image_file_list();
			break;
			case 'get_source_file_list':
				$result = $this->_get_source_file_list();
			break;
			case 'remove_file':
				$result = $this->_remove_file();
			break;

		}

		header("HTTP/1.0 200 OK");
		header('Content-type: application/json; charset=utf-8');
		header("Cache-Control: no-cache, must-revalidate");
		header("Expires: Sun, 17 Jan 1988 05:00:00 GMT");
		header("Pragma: no-cache");
		echo json_encode($result);
		die();

		header("HTTP/1.0 404 Not Found");
	}

	/* jstree 하위폴더 가져오기 */
	public function _get_folder_children(){
		$path = $_GET['childPath'] ? $_GET['childPath'] : $this->dataPath;

		/* data 폴더가 아니면 차단 */
		if(!$this->_datapath_check($path)){
			return null;
		}

		$result = array();

		$map = directory_map(ROOTPATH.$path,true);

		foreach($map as $o){
			$oUTF8 = iconv('euc-kr','utf-8',$o);
			$childPath = $path.'/'.$o;
			$childPathUTF8 = $path.'/'.$oUTF8;

			if(is_dir($childPath)){

				$hasChildDirectory = false;
				$childMap = directory_map(ROOTPATH.$childPath,true);
				foreach($childMap as $childO){
					if(is_dir($childPath.'/'.$childO)){
						$hasChildDirectory = true;
					}
				}

				$node = array(
					'attr' => array('childPath'=>$childPathUTF8),
					'data' => $oUTF8
				);

				if($hasChildDirectory){
					$node['state'] = 'closed';
				}

				$result[] = $node;
			}
		}

		usort($result,"_folderChildrenSortCmp");

		if(!$_GET['childPath']){
			$result = array(
				'attr' => array(
					'childPath'=>$this->dataPath,
					'rel' => 'root',
				),
				'data' => $this->dataPath,
				'state' => 'open',
				'children' => $result
			);
		}

		return $result;
	}

	/* jstree 폴더생성 */
	public function _create_folder(){
		$name = iconv('utf-8','euc-kr',$_GET['name']);
		$parentPath = iconv('utf-8','euc-kr',$_GET['parentPath']);
		$childPath = $parentPath."/".$name;
		$childPathUTF8 = iconv('euc-kr','utf-8',$childPath);

		if(!$this->_datapath_check($parentPath)){
			$result = array(
				'status'	=> false,
				'msg'		=> '권한이 없습니다.'
			);
			return $result;
		}

		/* 스킨폴더 체크 */
		if(preg_match("/^data\/skin$/",$parentPath)){
			$result = array(
				'status'	=> false,
				'msg'		=> '스킨폴더에는 폴더를 생성할 수 없습니다.'
			);
			return $result;
		}

		if($parentPath && is_dir(ROOTPATH.$parentPath)){
			if(is_dir(ROOTPATH.$childPath)){
				$result = array(
					'status'	=> false,
					'msg'		=> '같은 이름의 폴더가 존재합니다.'
				);
				return $result;
			}else{
				mkdir(ROOTPATH.$childPath);

				$result = array(
					'status'	=> true,
					'childPath'	=>$childPathUTF8
				);
				return $result;
			}
		}

		return $result;
	}

	/* jstree 폴더삭제 */
	function _remove_folder(){
		$pathUTF8 = $_GET['childPath'];
		$path = iconv('utf-8','euc-kr',$_GET['childPath']);

		/* 존재여부 체크 */
		if(!is_dir(ROOTPATH.$path)){
			$result = array(
				'status'	=> false,
				'msg'		=> '폴더가 존재하지 않습니다.'
			);
			return $result;
		}

		/* data 폴더가 아니면 차단 */
		if(!$this->_datapath_check($pathUTF8)){
			$result = array(
				'status'	=> false,
				'msg'		=> '권한이 없습니다.'
			);
			return $result;
		}

		/* 스킨폴더 체크 */
		if(preg_match("/^data\/skin\/[a-zA-Z0-9_]+$/",$pathUTF8)){
			$result = array(
				'status'	=> false,
				'msg'		=> '스킨폴더는 삭제할 수 없습니다.'
			);
			return $result;
		}

		/* 삭제 불가능 폴더 체크 */
		foreach($this->arrAbsoluteFolders as $deny){
			if($deny==$pathUTF8){
				$result = array(
					'status'	=> false,
					'msg'		=> '삭제할 수 없는 폴더입니다.'
				);
				return $result;
			}
		}

		/* 폴더 삭제 */
		if(rmdir(ROOTPATH.$path)){
			$result = array(
				'status'	=> true,
			);
			return $result;
		}else{
			$result = array(
				'status'	=> false,
			);
			return $result;
		}

		return;
	}

	/* jstree 폴더 이름변경*/
	function _rename_folder(){

		$childPathUTF8 = $this->input->get('childPath');
		$childPath = iconv('utf-8', 'euc-kr', $childPathUTF8);
		$parentPath = dirname($childPath);
		$nameUTF8 = $this->input->get('name');

		if (preg_match('/^[a-z0-9_]+$/', $nameUTF8) === 0) {
			$result = [
				'status' => false,
				'msg' => '허용하지 않는 문자열이 포함되었습니다.'
			];

			return $result;
		} else {
			$name = iconv('utf-8', 'euc-kr', $nameUTF8);
		}

		/* data 폴더가 아니면 차단 */
		if(!$this->_datapath_check($parentPath)){
			$result = array(
				'status'	=> false,
				'msg'		=> '권한이 없습니다.'
			);
			return $result;
		}

		/* 스킨폴더 체크 */
		if(preg_match("/^data\/skin\/[a-zA-Z0-9_]+$/",$childPath)){
			$result = array(
				'status'	=> false,
				'msg'		=> '스킨폴더는 변경할 수 없습니다.'
			);
			return $result;
		}

		/* 삭제 불가능 폴더 체크 */
		foreach($this->arrAbsoluteFolders as $deny){
			if($deny==$childPath){
				$result = array(
					'status'	=> false,
					'msg'		=> '변경할 수 없는 폴더입니다.'
				);
				return $result;
			}
		}

		/* 폴더명 변경 */
		if(rename($childPath,ROOTPATH.$parentPath."/".$name)){
			$result = array(
				'status'	=> true,
				'childPath' => iconv('euc-kr','utf-8',$parentPath."/".$name)
			);
			return $result;
		}else{
			$result = array(
				'status'	=> false,
			);
			return $result;
		}

		return;
	}

	/* 파일목록 가져오기 */
	public function _get_image_file_list(){
		global $sortBy, $sortOrder;
		$path = iconv('utf-8','euc-kr',$_GET['path']);
		$keyword = trim(iconv('utf-8','euc-kr',$_GET['options']['keyword']));
		$sortBy = trim(iconv('utf-8','euc-kr',$_GET['options']['sortBy']));
		$sortOrder = trim(iconv('utf-8','euc-kr',$_GET['options']['sortOrder']));

		$map = directory_map(ROOTPATH.$path,true);

		$result = array();

		/* data 폴더가 아니면 차단 */
		if(!$this->_datapath_check($path)){
			return $result;
		}

		foreach($map as $o){

			$filePath = $path."/".$o;

			/* 검색어 처리 */
			if($keyword){
				if(!preg_match('/'.$keyword.'/',$o)) continue;
			}

			/* 이미지파일 체크 */
			$fileExtension = str_replace('.','',substr($o,strpos($o,".")));
			if(!in_array($fileExtension,$this->arrImageExtensions)) continue;

			/* 이미지가로세로 크기 */
			@list($imageWidth, $imageHeight) = @getimagesize($filePath);

			if(is_file(ROOTPATH.$filePath)){
				$result[] = array(
				    "name" =>  iconv( mb_detect_encoding( $o ), 'utf-8', $o),
				    "path" => iconv( mb_detect_encoding( $filePath ), 'utf-8', $filePath),
					"time" => date('Y-m-d H:i:s',filemtime($filePath)),
					"scale" => "{$imageWidth}x{$imageHeight}",
					"size" => filesize($filePath)
				);
			}
		}

		if($sortBy && $sortOrder){
			usort($result,"_fileSortCmp");
		}

		return $result;
	}

	/* 파일목록 가져오기 */
	public function _get_source_file_list(){
		global $sortBy, $sortOrder;
		$path = iconv('utf-8','euc-kr',$_GET['path']);
		$keyword = trim(iconv('utf-8','euc-kr',$_GET['options']['keyword']));
		$sortBy = trim(iconv('utf-8','euc-kr',$_GET['options']['sortBy']));
		$sortOrder = trim(iconv('utf-8','euc-kr',$_GET['options']['sortOrder']));
		$searchFileExtension = trim(iconv('utf-8','euc-kr',$_GET['options']['fileExtension']));
		if($searchFileExtension){
			$searchFileExtension = explode(',',$searchFileExtension);
		}

		if(!$sortBy) $sortBy = 'name';
		if(!$sortOrder) $sortOrder = 'asc';

		$map = directory_map(ROOTPATH.$path,true);

		$result = array();

		/* data 폴더가 아니면 차단 */
		if(!$this->_datapath_check($path)){
			return $result;
		}

		foreach($map as $o){

			$filePath = $path."/".$o;

			/* 검색어 처리 */
			if($keyword){
				if(!preg_match('/'.$keyword.'/',$o)) continue;
			}

			/* 확장자 검색 */
			if($searchFileExtension){
				$chk = false;
				foreach($searchFileExtension as $v){
					if(preg_match("/\.".$v."$/",$o)) {
						$chk = true;
						break;
					}
				}

				if(!$chk) continue;
			}

			/* 파일확장자 체크 */
			$fileExtension = str_replace('.','',substr($o,strpos($o,".")));
			//if(!in_array($fileExtension,$this->arrSourceExtensions)) continue;

			$scale = "";
			if(in_array($fileExtension,$this->arrImageExtensions)){
				/* 이미지가로세로 크기 */
				@list($imageWidth, $imageHeight) = @getimagesize($filePath);
				$scale = $imageWidth.'x'.$imageHeight;
			}

			if(is_file(ROOTPATH.$filePath)){
				$result[] = array(
					"name" => iconv('euc-kr','utf-8',$o),
					"path" => iconv('euc-kr','utf-8',$filePath),
					"time" => date('Y-m-d H:i:s',filemtime($filePath)),
					"size" => filesize($filePath),
					"scale" => $scale
				);
			}
		}

		if($sortBy && $sortOrder){
			usort($result,"_fileSortCmp");
		}

		return $result;
	}

	public function _remove_file(){
		$pathUTF8 = $_GET['path'];
		$path = iconv('utf-8','euc-kr',$_GET['path']);

		/* 존재여부 체크 */
		if(!is_file(ROOTPATH.$pathUTF8)){
			$result = array(
				'status'	=> false,
				'msg'		=> '파일이 존재하지 않습니다.'
			);
			return $result;
		}

		/* data 폴더가 아니면 차단 */
		if(!$this->_datapath_check($pathUTF8)){
			$result = array(
				'status'	=> false,
				'msg'		=> '권한이 없습니다.'
			);
			return $result;
		}

		/* 파일 삭제 */
		if(@unlink(ROOTPATH.$pathUTF8)){
			$result = array(
				'status'	=> true,
			);
			return $result;
		}else{
			$result = array(
				'status'	=> false,
			);
			return $result;
		}
	}

	/* 파일 다운로드 */
	public function download_file(){
		$pathUTF8 = $_GET['path'];
		$path = iconv('utf-8','euc-kr',$_GET['path']);

		/* 존재여부 체크 */
		if(!is_file(ROOTPATH.$path)){
			openDialogAlert("파일이 존재하지 않습니다.",400,140,'parent');
			exit;
		}

		$this->load->helper('download');
		$this->load->helper('file');

		force_download(basename($path), read_file($path));
	}

	/* 파일 업로드*/
	public function upload_file(){

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

		$result = array(
			'status' => 0,
			'msg' => '업로드 실패하였습니다.',
			'desc' => '업로드 실패'
		);

		$path = preg_replace("/^\//","",$_POST['folder']);
		$targetPath = ROOTPATH.$path;

		/* data 폴더가 아니면 차단 */
		if(!$this->_datapath_check($path)){
			$result = array(
				'status'	=> 0,
				'msg'	=> "'{$path}' 폴더에 권한이 없습니다.",
				'desc' => '오류'
			);
			echo "[".json_encode($result)."]";
			exit;
		}

		if (!empty($_FILES)) {

			$fileName = $_POST['randomFilename'] ? "temp_".time().sprintf("%04d",rand(0,9999)) : $_FILES['Filedata']['name'];

			if(preg_match("/[\xA1-\xFE\xA1-\xFE]/",$_FILES['Filedata']['name']) && !$_POST['allowKorean']){
				$result = array(
					'status' => 0,
					'msg' => '파일명에 한글이 포함되어있습니다.<br />영문 파일명으로 변경 후 업로드해주세요.',
					'desc' => '한글파일명 업로드 불가'
				);
			}else{
				$size = getimagesize($_FILES['Filedata']['tmp_name']);
				$_FILES['Filedata']['type'] = $size['mime'];
				$config['upload_path'] = $targetPath;
				$config['allowed_types'] = implode('|',$this->arrImageExtensions);
				$config['max_size']	= $this->config_system['uploadLimit'];
				$config['file_name'] = $fileName;
				$config['overwrite'] = true;
				$this->load->library('upload', $config);
				if ( ! $this->upload->do_upload('Filedata'))
				{
					$result = array(
						'status' => 0,
						'msg' => $this->upload->display_errors(),
						'desc' => '업로드 실패'
					);
				}else{
					$fileInfo = $this->upload->data();
					$filePath = $path.'/'.$fileInfo['file_name'];
					@chmod($filePath,0777);
					$result = array('status' => 1,'filePath' => $filePath,'fileInfo'=>$fileInfo);
				}
			}
		}

		echo "[".json_encode($result)."]";

	}

	/* 파일업로드 전 파일명 중복체크 */
	public function upload_check(){

		$path = preg_replace("/^\//","",$_POST['folder']);
		$targetPath = ROOTPATH.$path;

		$fileArray = array();
		foreach ($_POST as $key => $value) {
			if ($key != 'folder') {
				if (file_exists($targetPath . '/' . $value)) {
					$fileArray[$key] = $value;
				}
			}
		}
		echo json_encode($fileArray);
	}

}

function _fileSortCmp($a,$b){
	global $sortBy, $sortOrder;
	$sSortBy = $sortBy;
	$sSortOrder = $sortOrder;

	if ($a[$sSortBy] == $b[$sSortBy]) {
        return 0;
    }
    if($sSortOrder == 'asc') 		return ($a[$sSortBy] < $b[$sSortBy]) ? -1 : 1;
    if($sSortOrder == 'desc')    	return ($a[$sSortBy] > $b[$sSortBy]) ? -1 : 1;

}

function _folderChildrenSortCmp($a,$b){
	if ($a['attr']['childPath'] == $b['attr']['childPath']) {
        return 0;
    }
    return ($a['attr']['childPath'] < $b['attr']['childPath']) ? -1 : 1;
}

/* End of file webftp.php */
/* Location: ./app/controllers/admin/webftp.php */