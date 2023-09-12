<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');
require_once(APPPATH ."controllers/base/front_base".EXT);

use App\Libraries\FileSystem\Upload;
use App\Libraries\FileSystem\FileTrait;

class common extends front_base  {

	use FileTrait;

	public function test()
	{
		$this->load->model('visitorlog');
		$referer_sitecd = $this->visitorlog->get_referer_sitecd("");
	}

	//한글도메인체크를 위해추가됨@2012-10-30
	public function domainjson()
	{
		$return = array('subdomain'=>$this->config_system['subDomain'], 'domain'=>$this->config_system['domain']);
		echo json_encode($return);
	}

	public function code2json()
	{
		$arrCode = code_load($_GET['groupcd']);
		echo json_encode($arrCode);
	}


	public function category2json(){
		$result = array();
		$this->load->model('categorymodel');
		$code = $_GET['categoryCode'];
		$result = $this->categorymodel->get_list($code,array("hide='0'"));
		echo json_encode($result);
	}

	//상품후기 >> 주문검색추가
	public function orderlistjson(){
		$this->arr_step = config_load('step');

		$sc['whereis']	= ' and id= "goods_review" ';
		$sc['select']		= ' * ';
		$this->load->model('Boardmanager');
		$this->load->model('membermodel');
		$this->load->model('boardadmin');
		$manager = $this->Boardmanager->managerdataidck($sc);//게시판정보
		if( strstr($manager['auth_write'],'[onlybuyer]') ) {//구매자만 가능한경우
			$auth_write = "onlybuyer";
		}elseif( strstr($manager['auth_write'],'[member]') ){
			$auth_write = "member";
		}else{
			$auth_write = "all";
		}
		if( $this->userInfo['member_seq'] ) {
			$result = array('auth_write'=>$auth_write, 'data'=>array());
		}else{
			$result = array('auth_write'=>$auth_write, 'nonorder'=>true,'data'=>array());
		}
		//$where[] = " (step = '70' OR step = '75') ";//부분배송완료, 배송완료
		if($this->userInfo['member_seq']) {//회원전용
			$where[] = " mem.member_seq = '".$this->userInfo['member_seq']."' ";
			$where[] = " ord.order_seq IN ( SELECT order_seq FROM fm_order_item WHERE goods_seq = ?)";
		}else{
			//$where[] = " member_seq is null ";//회원주문 검색불가
			$where[] = " ord.order_seq IN ( SELECT order_seq FROM fm_order_item WHERE goods_seq = ?)";
			$where[] = " ord.order_seq = '".$this->session->userdata('sess_order')."' ";
		}

		$query = "SELECT order_seq , step FROM (
			SELECT
			export.* ,
			export.status as step,
			ord.member_seq
			FROM
			fm_goods_export export
			LEFT JOIN fm_order ord ON ord.order_seq=export.order_seq
			LEFT JOIN fm_member mem ON mem.member_seq=ord.member_seq
			WHERE export.status = '75' AND" . implode(' AND ',$where) . " group by order_seq
		) t ORDER BY order_seq ASC, regist_date DESC";

		$bind = [
			$this->input->post('goods_seq')
		];
		$query = $this->db->query($query,$bind);
		foreach ($query->result_array() as $row){
			$row['mstep'] = $this->arr_step[$row['step']];
			$result['data'][] = $row;
		}
		echo json_encode($result);
		/**
		if(!$result && !$this->userInfo['member_seq'] ){
			echo json_encode($result);
		}else{
			echo json_encode($result);
		}
		**/
		$this->session->unset_userdata('sess_order');//비회원주문번호세션제거
	}

	//1:1문의 >> 주문검색추가
	public function myqanorderlistjson(){
		$this->arr_step = config_load('step');

		$sc['whereis']	= ' and id= "goods_review" ';
		$sc['select']		= ' * ';
		$this->load->model('Boardmanager');
		$this->load->model('membermodel');
		$this->load->model('boardadmin');
		$manager = $this->Boardmanager->managerdataidck($sc);//게시판정보
		if( strstr($manager['auth_write'],'[onlybuyer]') ) {//구매자만 가능한경우
			$auth_write = "onlybuyer";
		}elseif( strstr($manager['auth_write'],'[member]') ){
			$auth_write = "member";
		}else{
			$auth_write = "all";
		}

		$result = array('auth_write'=>$auth_write, 'data'=>array());
		$where[] = " member_seq = '".$this->userInfo['member_seq']."' ";
		$query = "SELECT order_seq , step, goods_name, item_cnt FROM (
			SELECT
			ord.*,
			(
				SELECT group_name FROM fm_member m,fm_member_group g WHERE m.group_seq=g.group_seq and m.member_seq=ord.member_seq
			) group_name,
			(
				SELECT goods_name FROM fm_order_item WHERE order_seq=ord.order_seq ORDER BY item_seq LIMIT 1
			) goods_name,
			(
				SELECT count(item_seq) FROM fm_order_item WHERE order_seq=ord.order_seq
			) item_cnt,
			mem.rute as mbinfo_rute,
			mem.user_name as mbinfo_user_name,
			bus.business_seq as mbinfo_business_seq,
			bus.bname as mbinfo_bname
			FROM
			fm_order ord
			LEFT JOIN fm_member mem ON mem.member_seq=ord.member_seq
			LEFT JOIN fm_member_business bus ON bus.member_seq=mem.member_seq
			WHERE ord.step!=0
		) t WHERE " . implode(' AND ',$where) . " ORDER BY step ASC, regist_date DESC";
		$query = $this->db->query($query,$bind);
		foreach ($query->result_array() as $row){
			$row['mstep'] = $this->arr_step[$row['step']];
			$result['data'][] = $row;
		}
		echo json_encode($result);
	}

	public function download(){
		$this->load->helper('common');
		$getParams = $this->input->get();

		$downfile = ROOTPATH.$getParams['downfile'];

		$sDownloadDir = ROOTPATH.'data/';
		$aFileExtensions = getAllowDownloadFileExtList();
		$sPattern = '/'.implode('|', $aFileExtensions).'/i';

		$realPath = realpath($downfile);

		// 파일 확장자 검증, 경로 검증 추가
		if (!preg_match($sPattern, $downfile) || strpos($realPath, $sDownloadDir) === false) {
			//올바른 파일이 아닙니다.
			openDialogAlert(getAlert('et001'),400,140,'parent','');
			exit;
		}

		$real_filename = end(explode("/", $downfile));

		$arr = explode('/',$downfile);
        $filename = $arr[count($arr)-1];

        //관리자 로그 남기기
        $type = array();
        if($getParams['type']){
            $type = array($getParams['type'] => $getParams['menu']);
        }

        if($type){
            $this->load->library('managerlog');
            $logInfo = array(
                'params' 	=> array('excelcount' => $getParams['excelcount']),
                'type'		=> $type
            );
            $this->managerlog->insertData($logInfo);
        }

		if ( file_exists($downfile) )
		{
			header("Content-Type: application/octet-stream");
			Header("Content-Disposition: attachment; filename=$filename");
			header("Content-Transfer-Encoding: binary");
			Header("Content-Length: ".(string)(filesize($downfile)));
			Header("Cache-Control: cache, must-reval!idate");
			header("Pragma: no-cache");
			header("Expires: 0");

			$fp = fopen($downfile, "rb"); //rb 읽기전용 바이러니 타입

			while ( !feof($fp) )
			{
				echo fread($fp, 100*1024); //echo는 전송을 뜻함.
			}

			fclose ($fp);

			flush(); //출력 버퍼비우기 함수..
		}
		else
		{
			//존재하지 않는 파일입니다.
			openDialogAlert(getAlert('et002'),400,140,'parent','');
			exit;
		}

	}


	/* 에디터 첨부이미지 임시업로드(uplodify처리) */
	public function editor_image_upload_temp(){

		$this->load->model('usedmodel');

		$res = $this->usedmodel->used_limit_check();
		if(!$res['type']){
			//파일 저장 공간이 부족하여 업로드가 불가능합니다.
			//업로드 실패
			$result = array(
				'status' => 0,
				'msg' => getAlert('et003'),
				'desc' => getAlert('et004')
			);
			echo "[".json_encode($result)."]";
			exit;
		}

		/* 이미지파일 확장자 */
		$this->arrImageExtensions = array('jpg','jpeg','png','gif','bmp','tif','pic','ai','psd','eps','dwg');
		$this->arrImageExtensions = array_merge($this->arrImageExtensions,array_map('strtoupper',$this->arrImageExtensions));
		//업로드 실패하였습니다.
		//업로드 실패
		$result = array(
			'status' => 0,
			'msg' => getAlert('et005'),
			'desc' => getAlert('et004')
		);

		$path = '/data/tmp';
		$targetPath = ROOTPATH.$path;

		if (!empty($_FILES)) {

			$fileName = "temp_".time().sprintf("%04d",rand(0,9999));

			$size = getimagesize($_FILES['Filedata']['tmp_name']);
			$_FILES['Filedata']['type'] = $size['mime'];
			$config['upload_path'] = $targetPath;
			$config['allowed_types'] = implode('|',$this->arrImageExtensions);
			$config['max_size']	= $this->config_system['uploadLimit'];
			$config['file_name'] = $fileName;
			$this->load->library('upload', $config);
			if ( ! $this->upload->do_upload('Filedata'))
			{
				//업로드 실패
				$result = array(
					'status' => 0,
					'msg' => $this->upload->display_errors(),
					'desc' => getAlert('et004')
				);
			}else{
				$fileInfo = $this->upload->data();
				$filePath = $path.'/'.$fileInfo['file_name'];
				ImgLotate($config['upload_path'].'/'.$fileInfo['file_name']);//@2017-04-25

				if( $this->session->userdata('tmpcode') ) {
					$boardidar	= @explode('^^', $this->session->userdata('tmpcode'));
					$sql['whereis']	= ' and id= "'.$boardidar[0].'" ';
					$sql['select']		= ' * ';
					$this->load->model('Boardmanager');
					$this->load->model('membermodel');
					$this->load->model('boardadmin');
					$this->manager = $this->Boardmanager->managerdataidck($sql);//게시판정보
					$this->manager['gallery_list_w'] = ($this->manager['gallery_list_w'])?$this->manager['gallery_list_w']:250;
					if($fileInfo['is_image'] == true && $fileInfo['image_width'] > $this->manager['gallery_list_w']) {//이미지인경우
						$this->load->helper('board');
						$source = $fileInfo['full_path'];
						$target = str_replace($fileInfo['file_name'], '_thumb_'.$fileInfo['file_name'],$fileInfo['full_path']);
						board_image_thumb($source,$target,$this->manager['gallery_list_w'],$this->manager['gallery_list_h']);
					}
				}else{
					if($fileInfo['is_image'] == true && $fileInfo['image_width'] > 250) {//이미지인경우
						$this->load->helper('board');
						$source = $fileInfo['full_path'];
						$target = str_replace($fileInfo['file_name'], '_thumb_'.$fileInfo['file_name'],$fileInfo['full_path']);
						board_image_thumb($source,$target,'250','250');
					}
				}

				$result = array('status' => 1,'filePath' => $filePath,'fileInfo'=>$fileInfo);
			}

		}

		echo "[".json_encode($result)."]";
	}

	// 상품 파일 이미지 업로드
	public function goods_image_temporary_fileupload()
	{
		$requestFiles = $_FILES ?? null;

		// 파일 존재여부
		if (isset($requestFiles) === false) {
			$emptyFiles = json_encode([
				// 상태값 false
				'status' => 0,
				// javascript alert 출력에 사용되는 메세지
				'msg' => getAlert('et005'),
				// 화면에 출력되는 메세지
				'desc' => getAlert('et004'),
			]);
			die($emptyFiles);
		}

		$upload = new Upload();

		// 파일업로드 설정
		$config = [];
		$config['webPath'] = '/data/tmp';
		$config['upload_path'] = ROOTPATH . $config['webPath'];
		$config['allowed_types'] = implode('|', $this->getFileExtension('image'));

		// 업로드 크기  (개별 파일 업로드 사이즈 체크 해야한다)
		$config['max_size'] = $this->config_system['uploadLimit'];

		// 이미지 이름과 리사이징된 이미지 이름이 동일해야 저장시 오류없음!!
		$imageName = $this->randomResizeFileName();

		// 상품 이미지일 경우 임시파일명에 view 붙이기 안붙이면 버그남
		$config['file_name'] = $imageName . 'view';

		// 파일업로드
		$uploadResult = $upload->put([
			'config' => $config,
			// $_FILES 의 키 이름을 넘겨야 합니다.
			'uploadFileName' => 'Filedata',
		]);

		// 업로드 성공
		if ($uploadResult['status'] === 1) {
			$fileInfo = $uploadResult['fileInfo'];

			// 돌아간 이미지 수정
			ImgLotate($config['upload_path'] . '/' . $fileInfo['file_name']); //@2017-04-25

			// 상품 이미지일 경우 사이즈별로 리사이징
			$this->load->model('goodsmodel');
			$arrDiv = config_load('goodsImageSize');

			$resizeImages = [];

			foreach ($arrDiv as $tmp => $size) {
				$target = $fileInfo['file_path'] . $imageName . $tmp . $fileInfo['file_ext'];
				$resizeImage = $this->goodsmodel->goods_temp_image_resize($fileInfo['full_path'], $target, $arrDiv[$tmp]['width'], $arrDiv[$tmp]['height']);
				$resizeImages[] = $resizeImage;
			}
			$uploadResult['imagesize'] = $resizeImages;
		}

		// 상품등록은 파일경로만 제공해야 한다
		$uploadResult['filePath'] = $config['webPath'];

		// 응답 출력
		$this->output->set_content_type('text/json');
		$this->output->set_output(json_encode([$uploadResult]));
	}

	// 임시 파일 이미지 업로드
	public function image_temporary_fileupload()
	{
		$requestFiles = $_FILES ?? null;

		// 파일 존재여부
		if (isset($requestFiles) === false) {
			$emptyFiles = json_encode([
				// 상태값 false
				'status' => 0,
				// javascript alert 출력에 사용되는 메세지
				'msg' => getAlert('et005'),
				// 화면에 출력되는 메세지
				'desc' => getAlert('et004'),
			]);
			die($emptyFiles);
		}

		$upload = new Upload();

		// 파일업로드 설정
		$config = [];
		$config['webPath'] = '/data/tmp';
		$config['upload_path'] = ROOTPATH . $config['webPath'];
		$config['allowed_types'] = implode('|', $this->getFileExtension('image'));

		// 업로드 크기  (개별 파일 업로드 사이즈 체크 해야한다)
		$config['max_size'] = $this->config_system['uploadLimit'];

		// 파일명칭
		$config['file_name'] = $this->randomFileName($_FILES['Filedata']['name']);

		// 파일업로드
		$uploadResult = $upload->put([
			'config' => $config,
			// $_FILES 의 키 이름을 넘겨야 합니다.
			'uploadFileName' => 'Filedata',
		]);

		// 업로드 성공
		if ($uploadResult['status'] === 1) {
			$fileInfo = $uploadResult['fileInfo'];

			// 돌아간 이미지 수정
			ImgLotate($config['upload_path'] . '/' . $fileInfo['file_name']); //@2017-04-25

			if ($fileInfo['image_width'] > 250) {
				$this->load->helper('board');
				$source = $fileInfo['full_path'];
				$target = str_replace($fileInfo['file_name'], '_thumb_' . $fileInfo['file_name'], $fileInfo['full_path']);
				board_image_thumb($source, $target, '250', '250');
			}
		}

		// 응답 출력
		$this->output->set_content_type('text/json');
		$this->output->set_output(json_encode([$uploadResult]));
	}

	// 임시 파일 게시판 업로드
	public function board_temporary_fileupload()
	{
		$requestFiles = $_FILES ?? null;

		// 파일 존재여부
		if (isset($requestFiles) === false) {
			$emptyFiles = json_encode([
				// 상태값 false
				'status' => 0,
				// javascript alert 출력에 사용되는 메세지
				'msg' => getAlert('et005'),
				// 화면에 출력되는 메세지
				'desc' => getAlert('et004'),
			]);
			die($emptyFiles);
		}

		/**
		 * 게시판 아이디를 session 에서 받아올수 있다.
		 * - 게시판 write 시 session 생성된다.
		 * ex) promotion^^469441^^24
		 */
		$boardWriteSession = $this->session->userdata('tmpcode');

		// 게시판 업로드 세션이 없는경우
		if (is_null($boardWriteSession) === true) {
			$emptyFiles = json_encode([[
				// 상태값 false
				'status' => 0,
				// javascript alert 출력에 사용되는 메세지
				'msg' => getAlert('et005'),
				// 화면에 출력되는 메세지
				'desc' => getAlert('et004'),
			]]);
			die($emptyFiles);
		}

		$boardidar = @explode('^^', $boardWriteSession);
		$boardId = $boardidar[0];


		$upload = new Upload();

		// 파일업로드 설정
		$config = [];
		// 게시판폴더
		$config['webPath'] = '/data/board/' . $boardId;
		$config['upload_path'] = ROOTPATH . $config['webPath'];

		// 파일 업로드 제한
		$config['allowed_types'] = implode('|', $this->getFileExtension('all'));

		// 업로드 크기  (개별 파일 업로드 사이즈 체크 해야한다)
		$config['max_size'] = $this->config_system['uploadLimit'];

		// 파일명칭
		$config['file_name'] = $this->randomFileName($_FILES['Filedata']['name']);

		//게시판으로 업로드
		$this->load->model('Boardmanager');
		//게시판정보
		$this->manager = $this->Boardmanager->managerdataidck([
			'select' => ' * ',
			'whereis' => ' and id= "' . $boardId . '" ',
		]);
		$this->manager['gallery_list_w'] = ($this->manager['gallery_list_w']) ? $this->manager['gallery_list_w'] : 250;

		// 업로드 파일이 이미지인지 확인
		if ($this->isImage($requestFiles['Filedata']['type']) === true) {

			$imgInfo = getimagesize($requestFiles['Filedata']['tmp_name']);

			$imgWidth = $imgInfo[0];
		
			if ($imgWidth > $this->manager['gallery_list_w']) {
				// 돌아간 이미지 회전
				rotateImage($requestFiles['Filedata']['tmp_name']); 
			}
		}

		// 파일 업로드
		$uploadResult = $upload->put([
			'config' => $config,
			// $_FILES 의 키 이름을 넘겨야 합니다.
			'uploadFileName' => 'Filedata',
		]);

		if ($uploadResult['status'] === 1) {
			// 업로드 성공
			$fileInfo = $uploadResult['fileInfo'];

			//이미지인경우
			if ($fileInfo['is_image'] == true && $fileInfo['image_width'] > $this->manager['gallery_list_w']) {
				$this->load->helper('board');
				$source = $fileInfo['full_path'];
				$target = str_replace($fileInfo['file_name'], '_thumb_' . $fileInfo['file_name'], $fileInfo['full_path']);
				board_image_thumb($source, $target, $this->manager['gallery_list_w'], $this->manager['gallery_list_h']);
			}
		}

		// 응답 출력
		$this->output->set_content_type('text/json');
		$this->output->set_output(json_encode([$uploadResult]));
	}

	/* 에디터 첨부이미지 임시업로드(uplodify처리) */
	public function editor_upload_temp(){

		$this->load->model('usedmodel');
		/* 파일확장자 */

		$uptype = ($this->input->post('uptype'))? $this->input->post('uptype') : '';

		// 이미지파일만 업로드 제한을 위해
		$arrFileExtensions_arr = array(
									'img' => array('jpg','jpeg','png','gif','pic','tif','tiff','jfif','bmp'),
									'doc' => array('txt','hwp','docx','docm','doc','ppt','pptx','pptm','pps','ppsx','xls','xlsx','xlsm','xlam','xla'),
									'etc' => array('ai','psd','eps','pdf','ods','ogg','mp4','avi','wmv','zip','rar','tar','7z','tbz','tgz','lzh','gz','dwg')
								);

		if($uptype) {
			$arrFileExtensions = $arrFileExtensions_arr[$uptype];
		}else{
			$arrFileExtensions = array();
			foreach($arrFileExtensions_arr as $k => $_extenstions){
				$arrFileExtensions = array_merge($arrFileExtensions, $_extenstions);
			}
		}
		//$arrFileExtensions = array('jpg','jpeg','png','gif','pic','tif','tiff','jfif','bmp','txt','hwp','docx','docm','doc','ppt','pptx','pptm','pps','ppsx','xls','xlsx','xlsm','xlam','xla','ai','psd','eps','pdf','ods','ogg','mp4','avi','wmv','zip','rar','tar','7z','tbz','tgz','lzh','gz','dwg');

		$arrFileExtensions = array_merge($arrFileExtensions,array_map('strtoupper',$arrFileExtensions));

		$res = $this->usedmodel->used_limit_check();
		if(!$res['type']){
			//파일 저장 공간이 부족하여 업로드가 불가능합니다.
			//업로드 실패
			$result = array(
				'status' => 0,
				'msg' => getAlert('et003'),
				'desc' => getAlert('et004')
			);
			echo "[".json_encode($result)."]";
			exit;
		}

		//업로드 실패하였습니다.
		$result = array(
			'status' => 0,
			'msg' => getAlert('et005'),
			'desc' => getAlert('et004')
		);

		if (!empty($_FILES)) {
			$size			= @getimagesize($_FILES['Filedata']['tmp_name']);

			if($this->session->userdata('tmpcode') && !$size && $this->session->userdata('tmpcode')) {//이미지가 아닌경우 게시판으로 업로드
				$boardidar	= @explode('^^', $this->session->userdata('tmpcode'));
				$path = '/data/board/'.$boardidar[0];//게시판폴더로 이동마//
			}else{
				$path = '/data/tmp';
			}
			$targetPath = ROOTPATH.$path;
			$file_ext		= @end(explode('.', $_FILES['Filedata']['name']));//확장자추출
			if(!$size['mime']){
				$_FILES['Filedata']['type'] = $file_ext;//확장자추출
			}else{
				$_FILES['Filedata']['type'] = $size['mime'];
			}

			$config['upload_path'] = $targetPath;
			$config['allowed_types'] = @implode('|',$arrFileExtensions);
			$config['max_size']	= $this->config_system['uploadLimit'];
			$config['file_name']	= md5($_FILES['Filedata']['name']).substr(date('YmdHisw'),8,14).'.'.$file_ext;//새로운이름으로
			$this->load->library('upload', $config);
			if ( ! $this->upload->do_upload('Filedata'))
			{
				//업로드 실패
				$result = array(
					'status' => 0,
					'msg' => $this->upload->display_errors(),
					'desc' => getAlert('et004')
				);
			}else{
				$fileInfo = $this->upload->data();
				$filePath = $path.'/'.$fileInfo['file_name'];
				ImgLotate($config['upload_path'].'/'.$fileInfo['file_name']);//@2017-04-25

				if($this->session->userdata('tmpcode')) {//이미지가 아닌경우 게시판으로 업로드
					$boardidar	= @explode('^^', $this->session->userdata('tmpcode'));
					$sql['whereis']	= ' and id= "'.$boardidar[0].'" ';
					$sql['select']		= ' * ';
					$this->load->model('Boardmanager');
					$this->load->model('membermodel');
					$this->load->model('boardadmin');
					$this->manager = $this->Boardmanager->managerdataidck($sql);//게시판정보
					$this->manager['gallery_list_w'] = ($this->manager['gallery_list_w'])?$this->manager['gallery_list_w']:250;
					if($fileInfo['is_image'] == true && $fileInfo['image_width'] > $this->manager['gallery_list_w']) {//이미지인경우
						$this->load->helper('board');
						$source = $fileInfo['full_path'];
						$target = str_replace($fileInfo['file_name'], '_thumb_'.$fileInfo['file_name'],$fileInfo['full_path']);
						board_image_thumb($source,$target,$this->manager['gallery_list_w'],$this->manager['gallery_list_h']);
					}
				}else{
					if($fileInfo['is_image'] == true && $fileInfo['image_width'] > 250) {//이미지인경우
						$this->load->helper('board');
						$source = $fileInfo['full_path'];
						$target = str_replace($fileInfo['file_name'], '_thumb_'.$fileInfo['file_name'],$fileInfo['full_path']);
						board_image_thumb($source,$target,'250','250');
					}
				}

				$result = array('status' => 1,'filePath' => $filePath,'fileInfo'=>$fileInfo,'filetype'=>$_FILES['Filedata']['type']);
			}

		}

		echo "[".json_encode($result)."]";
	}

	/* 카테고리 네비게이션 디자인 영역 HTML 보기 */
	public function category_navigation_html(){
		$this->load->model('categorymodel');

		$getParams = $this->input->get();
		$tpl = isset($getParams['tpl_path']) ? $getParams['tpl_path'] : '';
		$layoutPath = check_display_skin_file($this->designWorkingSkin, $tpl);
		if ($layoutPath === false) {
			show_error(getAlert('et001'));
		}

		$category = $this->categorymodel->get_category_view();

		$categoryNavigationKey = "categoryNavigation".uniqid();
		$this->template->assign(array('category'=>$category,'categoryNavigationKey'=>$categoryNavigationKey));
		$this->template->define(array('category'=>$layoutPath));

		$tpl_source = $this->template->fetch("category");
		$tpl_source = "<div id='{$categoryNavigationKey}'>\n{$tpl_source}\n</div>";

		echo $tpl_source;
	}

	/* 브랜드 네비게이션 디자인 영역 HTML 보기 */
	public function brand_navigation_html(){
		$this->load->model('brandmodel');

		$getParams = $this->input->get();
		$tpl = isset($getParams['tpl_path']) ? $getParams['tpl_path'] : '';
		$layoutPath = check_display_skin_file($this->designWorkingSkin, $tpl);
		if ($layoutPath === false) {
			show_error(getAlert('et001'));
		}

		$category = $this->brandmodel->get_brand_view();

		$categoryNavigationKey = "categoryNavigation".uniqid();
		$this->template->assign(array('brand'=>$category,'categoryNavigationKey'=>$categoryNavigationKey));
		$this->template->define(array('brand'=>$layoutPath));

		$tpl_source = $this->template->fetch("brand");
		$tpl_source = "<div id='{$categoryNavigationKey}'>\n{$tpl_source}\n</div>";

		echo $tpl_source;
	}

	/* 지역 네비게이션 디자인 영역 HTML 보기 */
	public function location_navigation_html(){
		$this->load->model('locationmodel');

		$getParams = $this->input->get();
		$tpl = isset($getParams['tpl_path']) ? $getParams['tpl_path'] : '';
		$layoutPath = check_display_skin_file($this->designWorkingSkin, $tpl);
		if ($layoutPath === false) {
			show_error(getAlert('et001'));
		}

		$category = $this->locationmodel->get_location_view();

		$categoryNavigationKey = "categoryNavigation".uniqid();
		$this->template->assign(array('location'=>$category,'categoryNavigationKey'=>$categoryNavigationKey));
		$this->template->define(array('location'=>$layoutPath));

		$tpl_source = $this->template->fetch("location");
		$tpl_source = "<div id='{$categoryNavigationKey}'>\n{$tpl_source}\n</div>";

		echo $tpl_source;
	}

	//* IP차단 페이지 */
	public function denined_ip(){
		echo "접근이 차단되었습니다.";
		exit;
	}

	/* ALLAT 결제취소시 데이터 암호화*/
	public function allat_enc(){
		$param = $this->input->post();
		//comma 제거
		foreach ($param as $k => $v) {
			if (is_array($v)) {
				foreach ($v as $kk => $vv) {
					$param[$k][$kk] = str_replace(',', '', $vv);
				}
			} else {
				$param[$k] = str_replace(',', '', $v);
			}
		}
		$actionUrl = $param['actionUrl'];

		if ($param['refund_code']) {
			try {
				$this->load->library('allatmultiamt');
				$this->allatmultiamt->setRefundData($param['refund_code']);
				$this->allatmultiamt->setFreeTax();
				$this->allatmultiamt->setTaxPrice($param['refund_delivery_price_tmp'], $param['refund_goods_price']);
				$this->allatmultiamt->setAllOrderSeq($param['top_orign_order_seq']);
				$this->allatmultiamt->setCommMny();
				$data_refund = $this->allatmultiamt->getDataRefund();
				if ($data_refund) {
					$param['free_price'] = (int) $data_refund['free_price'];
					$param['comm_tax_mny'] = (int) $data_refund['comm_tax_mny'];
					$param['comm_vat_mny'] = (int) $data_refund['comm_vat_mny'];
					$param['free_tax'] = $data_refund['free_tax'];
				}
			} catch (Exception $e) {
				openDialogAlert($e->getMessage(), 450, 150, 'parent');
				exit;
			}
		}

		if ($param['free_tax'] === 'y') {
			$param['allat_multi_amt'] = implode('|', [$param['comm_tax_mny'], $param['comm_vat_mny'], $param['free_price']]);
		} else {
			$param['allat_vat_amt'] = $param['comm_vat_mny'];
		}
		/* non-Active X */

		// 올앳 데이터
		$allatData = [];
		// 환불 데이터
		$refundData = [];

		$allatData['allat_shop_id'] = $pg['mallCode'];
		// 올앳 상점 정보를 가져온다.
		$pg = config_load('allat');

		// 올앳 enc data receive url
		// @see common/allat_enc_receive
		$allatData['shop_receive_url'] = get_connet_protocol() . $this->input->server('HTTP_HOST') . '/common/allat_enc_receive';

		$allatFields = ['allat_shop_id', 'allat_order_no', 'allat_amt', 'allat_pay_type', 'shop_receive_url', 'allat_opt_pin', 'allat_opt_mod', 'allat_seq_no', 'allat_vat_amt', 'allat_multi_amt'];
		unset($param['actionUrl'], $param['allat_enc_data']);

		foreach ($param as $key => $value) {
			// allat 결제취소 필드는 allatData
			if (in_array($key, $allatFields) === true) {
				$allatData[$key] = $value;
			} else { // 그 외의 data는 cancelData
				$cancelData[$key] = $value;
			}
		}

		$this->template->assign('allatData', $allatData);
		$this->template->assign('cancelData', $cancelData);
		$this->template->assign('actionUrl', $actionUrl);
		$this->template->template_dir = BASEPATH . '../pg/allat/';
		$this->template->compile_dir = BASEPATH . '../_compile/';
		$this->template->define(['tpl' => 'cancel_nax.html']);
		$this->template->print_('tpl');
	}

	/* ALLAT 결제취소시 데이터 암호화 응답*/
	public function allat_enc_receive()
	{
	    $allat_result_cd = $this->input->post('allat_result_cd');
	    $allat_result_msg = $this->input->post('allat_result_msg');
	    $allat_enc_data = $this->input->post('allat_enc_data');

	    // 페이지에서 실행할 스크립트
	    $script = "";

	    if($allat_result_cd !== "0000") {

	        if(!empty($allat_result_msg)) {
	            if(mb_detect_encoding($allat_result_msg, array("EUC-KR")) !== false) {
	                $allat_result_msg = iconv("EUC-KR", "UTF-8", $allat_result_msg);
	            }
	        } else {
	            $allat_result_msg = "PG 정보 암호화 실패";
	        }

	        $script  = "alert('{$allat_result_cd} : {$allat_result_msg}');";
	    } else {
	        $script = "parent.allat_result_submit('{$allat_enc_data}');";
	    }

	    $this->template->template_dir = BASEPATH."../pg/allat/";
	    $this->template->compile_dir = BASEPATH."../_compile/";
	    $this->template->define(array('tpl'=>'allat_enc_receive.html'));
	    $this->template->assign('script', $script);
	    $this->template->print_('tpl');
	}

	/* 즐겨찾기(북마크) */
	public function bookmark(){
		//즐겨찾기 해 주셔서 감사합니다.
		$result = array('result'=>false,'msg'=>getAlert('et006'));
		$reserves = ($this->reserves)?$this->reserves:config_load('reserve');

		$default_reserve_bookmark = $reserves['default_reserve_bookmark'];
		$default_point_bookmark = $reserves['default_point_bookmark'];

		$bookmarkuser = $_COOKIE['bookmark'];
		$bookmarkpointuser = $_COOKIE['bookmarkpoint'];

		if( ( $default_reserve_bookmark > 0 && !strstr($bookmarkuser,'['.$this->userInfo['member_seq'].']') &&  !$bookmarkuser ) || ( $default_point_bookmark > 0  && !strstr($bookmarkpointuser,'['.$this->userInfo['member_seq'].']')  && !$bookmarkpointuser) ) {

			if ( !defined('__ISUSER__') ) {//비회원인 경우
					$msg = '';

					if( $default_reserve_bookmark ) {
						//' 마일리지 '.number_format($default_reserve_bookmark).'원'
						$msg .= getAlert('et007',get_currency_price($default_reserve_bookmark));
					}

					if ($default_point_bookmark) {
						//포인트
						$msg .= ($msg)?', '.getAlert('et008',number_format($default_point_bookmark)) : getAlert('et008',number_format($default_point_bookmark));
					}
					//'즐겨찾기를 하시면 '.$msg.'이 지급됩니다.<br>로그인 하시겠습니까?'
					$result = array('result'=>false, 'type'=>'login', 'msg'=>getAlert('et009',$msg));
			}else{
				$this->load->model('membermodel');
				$msg = '';

				if($default_reserve_bookmark > 0 ) {
					$this->load->model('emoneymodel');
					$sc['select']		= 'emoney_seq';
					$sc['whereis']	= ' and type="bookmark" and member_seq = '.$this->userInfo['member_seq'];
					$bookmarkck = $this->emoneymodel->get_data($sc);
					if(!$bookmarkck){//회원중복체크
						setcookie('bookmark', '['.$this->userInfo['member_seq'].']', 0, '/');

						$emoney['gb']		= 'plus';
						$emoney['type']		= 'bookmark';
						$emoney['emoney']	= $default_reserve_bookmark;
						$emoney['memo']		= '즐겨찾기 적립';
						$emoney['memo_lang']	= $this->membermodel->make_json_for_getAlert("mp274");    // 즐겨찾기 적립
						$emoney['limit_date']	= get_emoney_limitdate('bookmark');
						$this->membermodel->emoney_insert($emoney, $this->userInfo['member_seq']);
					}

					//' 마일리지 '.number_format($default_reserve_bookmark).'원';
					$msg .= ' '.getAlert('et007',get_currency_price($default_reserve_bookmark));
				}

				if($default_point_bookmark > 0 ) {
					$this->load->model('pointmodel');
					$sc['select']		= 'point_seq';
					$sc['whereis']	= ' and type="bookmark" and member_seq = '.$this->userInfo['member_seq'];
					$bookmarkck = $this->pointmodel->get_data($sc);
					if(!$bookmarkck){//회원중복체크
						setcookie('bookmarkpoint', '['.$this->userInfo['member_seq'].']', 0, '/');

						### POINT
						$iparam['gb']			= "plus";
						$iparam['type']			= 'bookmark';
						$iparam['point']		= $default_point_bookmark;
						$iparam['memo']			= '즐겨찾기 적립';
						$iparam['memo_lang']            = $this->membermodel->make_json_for_getAlert("mp274");    // 즐겨찾기 적립
						$iparam['limit_date']           = get_point_limitdate('bookmark');
						$this->membermodel->point_insert($iparam, $this->userInfo['member_seq']);
					}

					//포인트
					$msg .= ($msg)?', '.getAlert('et008',number_format($default_point_bookmark)):'  '.getAlert('et008',number_format($default_point_bookmark));
				}

				//'즐겨찾기를 하시면 '.$msg.'이 지급됩니다.'
				$result = array('result'=>true, 'type'=>'login', 'msg'=>getAlert('et010',$msg));
			}
		}
		echo json_encode($result);
	}

	/* 모바일모드에서 PC모드로 전환 */
	public function mobile_mode_off(){

		//관리자>디자인>스킨설정:미리보기스킨 초기화
		if($_COOKIE['previewSkin']){
			$this->load->helper("cookie");
			delete_cookie('previewSkin');
		}
		$referer = parse_url($_SERVER['HTTP_REFERER']);
		if(!$referer['host']) $referer['host'] = $_SERVER['HTTP_HOST'];

		$host = preg_replace("/^m\./","",$referer['host']);
		$path = $referer['path'];
		$protocol = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']=='on' ? 'https://' : 'http://';
		$query = !empty($referer['query']) ? "?" . $referer['query'] : "";
		$query = $query ? $query."&setMode=pc" : "?setMode=pc";

		// 모바일 상품상세 시 예외처리 2014-01-15 lwh
		if($path == "/goods/view_contents")		$path = "/goods/view";

		// 모바일 출고상세 예외처리 2015-01-14 ocw
		if($path == "/mypage/export_view")		$path = "/mypage/order_view";

		// 카테고리리스트 예외처리 2015-01-16 ocw
		if($path == "/goods/category_list")		$path = "/main/index";

		$url = $protocol.$host.$path.$query;

		pageRedirect($url);
	}


	/* facebook모드에서 PC모드로 전환 */
	public function facebook_mode_off(){
		setcookie('fammercemode', '', 0, '/');
		$referer = parse_url($_SERVER['HTTP_REFERER']);
		$host = preg_replace("/^m\./","",$referer['host']);
		$path = $referer['path'];
		$protocol = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']=='on' ? 'https://' : 'http://';
		$query = !empty($referer['query']) ? "?" . $referer['query'] : "";

		$url = $protocol.$host.$path.$query;

		pageRedirect($url);
	}

	public function mybag_data(){

		$this->load->model('cartmodel');
		$cart = $this->cartmodel->cart_list();
		$cart['list'] = is_array($cart['list'])?$cart['list']:array();

		$cart_ea_sum = 0;
		foreach($cart['list'] as $row){
			if($row['cart_options']){
				foreach($row['cart_options'] as $option) {
					$cart_ea_sum+=$option['ea'];
				}
			}
			if($row['cart_suboptions']){
				foreach($row['cart_suboptions'] as $suboption) {
					$cart_ea_sum+=$suboption['ea'];
				}
			}
		}

		$this->template->assign(array(
			'cart_ea_sum'	=> $cart_ea_sum,
		));

		$result = array(
			'cart_ea_sum' => $cart_ea_sum,
			'total_price' => $cart['total_price']
		);

		echo json_encode($result);

	}

	public function mybag_contents(){

		$this->load->model('cartmodel');
		$cart = $this->cartmodel->cart_list();
		$cart['list'] = is_array($cart['list'])?$cart['list']:array();

		$cart_ea_sum = 0;
		$cart_item_list = array();
		foreach($cart['list'] as $row){
			if($row['cart_options']){
				foreach($row['cart_options'] as $option) {
					$cart_item_list[] = array_merge($row,$option);
					$cart_ea_sum+=$option['ea'];
				}
			}
			if($row['cart_suboptions']){
				foreach($row['cart_suboptions'] as $suboption) {
					$cart_item_list[] = array_merge($row,$suboption);
					$cart_ea_sum+=$suboption['ea'];
				}
			}
		}

		$size = config_load('goodsImageSize','thumbScroll');

		$this->template->assign(array(
			'size'				=> $size['thumbScroll'],
			'cart'				=> $cart,
			'cart_item_list'	=> $cart_item_list,
			'cart_ea_sum'	=> $cart_ea_sum,
		));

		$cart_promotioncode = $this->session->userdata('cart_promotioncode_'.session_id());
		$this->template->assign('cart_promotioncode' , $cart_promotioncode);
		$this->template->assign('ispromotioncode' , $this->isplusfreenot['ispromotioncode']);

		$this->template->define(array('tpl'=>$this->skin.'/_modules/mybag/mybag_contents.html'));
		$this->template->print_("tpl");
	}

	public function mybag_goods_cart_del()
	{
		$this->load->model('cartmodel');
		$this->cartmodel->delete_option($_POST['cart_option_seq'],$_POST['cart_suboption_seq']);
	}

	public function mybag_goods_today_del(){

		$goods_seq = $_POST['goods_seq'];

		// 오늘본 상품 쿠키
		$today_num = 0;
		$today_view = $_COOKIE['today_view'];
		if( $today_view ) $today_view = unserialize($today_view, ['allowed_classes' => false]);
		if( $today_view ) foreach($today_view as $v){
			if($v!=$goods_seq){
				$data_today_view[] = $v;
			}
		}
		if( $data_today_view ) $data_today_view = serialize($data_today_view);
		setcookie('today_view',$data_today_view,time()+86400,'/');

	}

	public function ajax_get_search_option(){
		$this->load->model('SearchoptionModel');

		$searchOption['category']	= $this->SearchoptionModel->get_results("category");
		$searchOption['brand']		= $this->SearchoptionModel->get_results("brand");
		$searchOption['option1']	= $this->SearchoptionModel->get_results("option1");
		$searchOption['option2']	= $this->SearchoptionModel->get_results("option2");
		$searchOption['rate']		= $this->SearchoptionModel->get_results("rate");

		echo json_encode($searchOption);

	}


	//전국매장안내 네이버지도추가
	public function get_map(){
		$this->load->library('SofeeXmlParser');
		$xmlParser = new SofeeXmlParser();
		$addr= urlencode($_GET['addr']);
		$key=($_GET['key']);
		$url= get_connet_protocol()."openapi.map.naver.com/api/geocode.php?key=".$key."&encoding=utf-8&coord=latlng&query=".$addr;
		$xmlParser->parseFile($url);
		$tree = $xmlParser->getTree();
		if($tree['geocode']['item'][0]['point']['x']['value']){
			$returnpoint = array('y'=>$tree['geocode']['item'][0]['point']['x']['value'], 'x'=>$tree['geocode']['item'][0]['point']['y']['value']);
		}else{
			$returnpoint = array('y'=>$tree['geocode']['item']['point']['x']['value'], 'x'=>$tree['geocode']['item']['point']['y']['value']);
		}
		echo json_encode($returnpoint);
		exit;
	}

	// 반응형 파일업로드 팝업
	public function responsive_file_upload()
	{
		$requestGet = $this->input->get();

		// 유효성 검사
		$this->load->library('validation');
		$this->validation->set_data($requestGet);
		$this->validation->set_rules('board_id', 'board_id', 'trim|required|xss_clean');
		$this->validation->set_rules('insert_image', 'insert_image', 'trim|xss_clean|alpha');
		if ($this->validation->exec() === false) {
			show_error($this->validation->error_array['value']);
			exit;
		}

		$assigns = [
			'board_id' => $requestGet['board_id'],
			'insert_image' => $requestGet['insert_image'],
		];
		$this->template->assign($assigns);

		$this->template->template_dir = ROOTPATH;
		$this->template->define(['tpl' => "app/javascript/plugin/editor/pages/trex/file_mobile.html"]);
		$this->template->print_('tpl');
	}

	// 다음에디터
	public function editor()
	{
		$params = $this->input->get();

		// 보안 이슈 처리 by hed 2018-01-12
		// 에디터를 구성하기 위한 기본 파라미터들에 XSS 공격이 가능함에 따라 GET 메소드에서 태그를 제거함
		// html파일 직접 호출방식 제거를 위해 contoller 로 재구성 by pjm 2022/03/28
		foreach($params as $k => $v) {
			$params[$k] = strip_tags($v);
		}

		$this->template->assign('params', $params);
		$this->template->template_dir = ROOTPATH;
		$this->template->define(['tpl' => 'app/javascript/plugin/editor/_editor.html']);
		$this->template->print_("tpl");
	}


	// 다음에디터 파일 첨부 팝업
	public function editor_file()
	{
		$this->template->template_dir = ROOTPATH;
		$this->template->define(['tpl' => 'app/javascript/plugin/editor/pages/trex/file.html']);
		$this->template->print_("tpl");
	}

	// 다음에디터 사진 첨부 팝업
	public function editor_image() {

		$redomain = $this->input->get("redomain");
		if($redomain) {//한글도메인
			$this->load->helper("krdomain");
			$redomaindecode = urldecode($redomain);
			$redomainar = explode(".",$redomaindecode);
			$redomain = krencode($redomainar[0]).str_replace($redomainar[0],"",$redomaindecode);
		}

		### SERVICE CHECK
		$this->load->model('usedmodel');
		$result = $this->usedmodel->used_service_check('watermark');
		if(!$result['type']){
			$this->template->assign('service_limit','Y');
		}

		$config_watermark = config_load('watermark');
		if($config_watermark['watermark_position']!=''){
			$config_watermark['watermark_position'] = explode('|',$config_watermark['watermark_position']);
		}

		if($config_watermark['watermark_image'] && $config_watermark['watermark_type']){
			$config_watermark['watermark_setting_status'] = 'y';
		}

		$file_path	= "app/javascript/plugin/editor/pages/trex/image.html";

		$this->template->template_dir = ROOTPATH;
		$this->template->assign(array('sc'				 => $this->input->get()));
		$this->template->assign(array('config_watermark' => $config_watermark));
		$this->template->assign(array('managerInfo'		 => $this->managerInfo));
		$this->template->define(array('tpl'				 => $file_path));
		$this->template->print_("tpl");
	}

	function editor_image_watermark()
	{

		### SERVICE CHECK
		$this->load->model('usedmodel');
		$result = $this->usedmodel->used_service_check('watermark');
		if(!$result['type']) return;
		if(!$this->managerInfo) return;
		if(!$_POST['target_image']) return;

		$target_imgs = explode('|',urldecode($_POST['target_image']));
		$this->load->model('watermarkmodel');

		$result = "FALSE";
		if($target_imgs[0]){
			foreach($target_imgs as $target_image){
				if($target_image){
					$this->watermarkmodel->target_image = str_replace('//','/',ROOTPATH.$target_image);
					$this->watermarkmodel->source_image = $this->watermarkmodel->target_image;
					$this->watermarkmodel->watermark();
					$result = "OK";
				}
			}

		}

		echo $result;
	}

	public function category_all_navigation(){
		$this->load->helper('design');
		$this->load->model('categorymodel');

		$_GET['categoryNavigationKey']	= chk_parameter_xss_clean($_GET['categoryNavigationKey']);

		$categoryNavigationKey = $_GET['categoryNavigationKey'];
		$categoryData = $this->categorymodel->get_category_view(null,3,'gnb');

		$tpl_path = substr($_GET['template_path'],strpos($_GET['template_path'],'/')+1);
		$layout_config = layout_config_autoload($this->skin,$tpl_path);

		// 반응형 인 경우 전체네비게이션 배너 무조건 노출
		if($this->config_system['operation_type'] == 'light'){

			// 1번째 카테고리 데이터에서 배너를 가져옴
			$cate_node_gnb_banner = '';
			foreach($categoryData as $category){
				$cate_node_gnb_banner = showdesignEditor($category['node_gnb_banner']);
				break;
			}

			$this->template->assign(array('category_gnb_banner' => $cate_node_gnb_banner));


		}else if(preg_match("/^\/goods\/catalog\?(.*)/",$_GET['requesturi'],$matches)){
			$params = array();
			$tmp = explode("&",$matches[1]);
			foreach($tmp as $strings){
				list($k,$v) = explode("=",$strings);
				$params[$k]=$v;
			}
			if($params['code']){
				$currentCategoryData = $this->categorymodel->get_category_data($params['code']);

				// editor 빈 값 태그일 경우 미노출 처리 2015-04-13
				if (strtolower($currentCategoryData['node_gnb_banner']) == "<p>&nbsp;</p>"
					|| strtolower($currentCategoryData['node_gnb_banner']) == "<p><br></p>") {
					$currentCategoryData['node_gnb_banner']='';
				}else{
					# 치환코드 변환
					$currentCategoryData['node_gnb_banner'] = showdesignEditor($currentCategoryData['node_gnb_banner']);
				}

				$this->template->assign(array('category_gnb_banner' => $currentCategoryData['node_gnb_banner']));
			}
		}

		$this->template->assign(array('categoryNavigationKey'=>$categoryNavigationKey));
		$this->template->assign(array('categoryData'=>$categoryData));
		$this->template->assign(array('layout_config'=>$layout_config[$tpl_path]));
		$this->template->define(array('tpl'=>$this->skin.'/_modules/category/all_navigation.html'));
		$this->template->print_("tpl");
	}

	public function brand_all_navigation(){
		$this->load->helper('design');
		$this->load->model('brandmodel');

		$categoryNavigationKey = $_GET['categoryNavigationKey'];
		$categoryData = $this->brandmodel->get_brand_view(null,3,'gnb');

		$tpl_path = substr($_GET['template_path'],strpos($_GET['template_path'],'/')+1);
		$layout_config = layout_config_autoload($this->skin,$tpl_path);

		// 반응형 인 경우 전체네비게이션 배너 무조건 노출
		if($this->config_system['operation_type'] == 'light'){

			// 1번째 카테고리 데이터에서 배너를 가져옴
			$cate_node_gnb_banner = '';
			foreach($categoryData as $category){
				$cate_node_gnb_banner = showdesignEditor($category['node_gnb_banner']);
				break;
			}

			$this->template->assign(array('category_gnb_banner' => $cate_node_gnb_banner));


		}else if(preg_match("/^\/goods\/brand\?(.*)/",$_GET['requesturi'],$matches)){
			$params = array();
			$tmp = explode("&",$matches[1]);
			foreach($tmp as $strings){
				list($k,$v) = explode("=",$strings);
				$params[$k]=$v;
			}
			if($params['code']){
			/*
				$bestBrandData = $this->brandmodel->get_all(array("category_code='{$params['code']}'","best='Y'"));
				$bestBrandData = $this->brandmodel->design_set($bestBrandData,'gnb');
				$this->template->assign(array('bestBrandData' => $bestBrandData));
			*/
				$currentCategoryData = $this->brandmodel->get_brand_data($params['code']);
				$this->template->assign(array('category_gnb_banner' => $currentCategoryData['node_gnb_banner']));
			}
		}

		if(!$bestBrandData){
			$bestBrandData = $this->brandmodel->get_all(array("best='Y'"));
			$bestBrandData = $this->brandmodel->design_set($bestBrandData,'gnb');
			$this->template->assign(array('bestBrandData' => $bestBrandData));
		}

		$this->template->assign(array('categoryNavigationKey'=>$categoryNavigationKey));
		$this->template->assign(array('categoryData'=>$categoryData));
		$this->template->assign(array('layout_config'=>$layout_config[$tpl_path]));
		$this->template->define(array('tpl'=>$this->skin.'/_modules/brand/all_navigation.html'));
		$this->template->print_("tpl");
	}

	public function location_all_navigation(){
		$this->load->helper('design');
		$this->load->model('locationmodel');

		$locationNavigationKey = $_GET['locationNavigationKey'];
		$locationData = $this->locationmodel->get_location_view(null,3,'gnb');

		$tpl_path = substr($_GET['template_path'],strpos($_GET['template_path'],'/')+1);
		$layout_config = layout_config_autoload($this->skin,$tpl_path);

		// 반응형 인 경우 전체네비게이션 배너 무조건 노출
		if($this->config_system['operation_type'] == 'light'){

			// 1번째 카테고리 데이터에서 배너를 가져옴
			$location_gnb_banner = '';
			foreach($locationData as $location){
				$location_gnb_banner = showdesignEditor($location['node_gnb_banner']);
				break;
			}

			$this->template->assign(array('location_gnb_banner' => $location_gnb_banner));

		}else if(preg_match("/^\/goods\/location\?(.*)/",$_GET['requesturi'],$matches)){
			$params = array();
			$tmp = explode("&",$matches[1]);
			foreach($tmp as $strings){
				list($k,$v) = explode("=",$strings);
				$params[$k]=$v;
			}
			if($params['code']){
				$currentLocationData = $this->locationmodel->get_location_data($params['code']);
				$this->template->assign(array('location_gnb_banner' => $currentLocationData['node_gnb_banner']));
			}
		}

		$this->template->assign(array('locationNavigationKey'=>$locationNavigationKey));
		$this->template->assign(array('locationData'=>$locationData));
		$this->template->assign(array('layout_config'=>$layout_config[$tpl_path]));
		$this->template->define(array('tpl'=>$this->skin.'/_modules/location/all_navigation.html'));
		$this->template->print_("tpl");
	}

	//검색어 자동완성 기능 불러오기
	public function autocomplete(){
		$key = $_POST["key"];
		$key = str_replace(' ', '',addslashes($key));

		$this->load->model('goodsmodel');
		$this->load->model('categorymodel');
		$this->load->library('sale');
		$cfg_reserve	= ($this->reserves) ? $this->reserves : config_load('reserve');
		$cfg_tmp		= config_load("search");
		$cfg_search['popular_search'] = $cfg_tmp['popular_search']?$cfg_tmp['popular_search']:'n';
		$cfg_search['popular_search_limit_day'] = $cfg_tmp['popular_search_limit_day']?$cfg_tmp['popular_search_limit_day']:30;
		$cfg_search['popular_search_recomm_limit_day'] = $cfg_tmp['popular_search_recomm_limit_day']?$cfg_tmp['popular_search_recomm_limit_day']:30;

		$cfg_search['auto_search'] = $cfg_tmp['auto_search']?$cfg_tmp['auto_search']:'n';
		$cfg_search['auto_search_limit_day'] = $cfg_tmp['auto_search_limit_day']?$cfg_tmp['auto_search_limit_day']:30;
		$cfg_search['auto_search_recomm_limit_day'] = $cfg_tmp['auto_search_recomm_limit_day']?$cfg_tmp['auto_search_recomm_limit_day']:30;

		//----> sale library 적용
		$applypage						= 'list';
		$param['cal_type']				= 'list';
		$param['reserve_cfg']			= $cfg_reserve;
		$param['member_seq']			= $this->userInfo['member_seq'];
		$param['group_seq']				= $this->userInfo['group_seq'];
		$this->sale->set_init($param);
		$this->sale->preload_set_config($applypage);
		//<---- sale library 적용

		$now_date						= date('Y-m-d');

		// 자동검색 slow query 개선 작업 @2016-08-25 ysm
		if($key){
			$timestamp = strtotime('-'.$cfg_search['auto_search_limit_day'].'day');
			$enddate = date('Y-m-d',$timestamp);

			$query = "select SQL_NO_CACHE keyword,sum(cnt) cnt from fm_search_list where `keyword` like '%".$key."%' group by `keyword` limit 10";
			$query = $this->db->query($query);
			foreach ($query->result_array() as $row){
				$row['key'] = $row["keyword"];
				$row['keyword'] = str_replace($key, "<font color='#f6620b'><b>".$key."</b></font>", htmlspecialchars($row["keyword"]));
				$result[] = $row;
			}

			$timestamp = strtotime('-'.$cfg_search['auto_search_recomm_limit_day'].'day');
			$enddate = date('Y-m-d',$timestamp);
/*
			$query = "select SQL_NO_CACHE * from (
						select g.goods_seq, g.goods_name, g.sale_seq, g.string_price_use, g.string_price, g.member_string_price_use, g.member_string_price, g.allmember_string_price_use, g.allmember_string_price,g.display_terms,g.display_terms_text,g.display_terms_color
						from fm_order_item oi,fm_goods_export_item ei,fm_goods_export ex,fm_goods g
						where oi.item_seq=ei.item_seq
						and ei.export_code=ex.export_code
						and oi.goods_seq=g.goods_seq
						and oi.goods_name like '%".$key."%'
						and ex.`status`='75'
						and ex.shipping_date >= '".$enddate."'
						and (g.goods_view = 'look' or ( g.display_terms = 'AUTO' and g.display_terms_begin <= '".$now_date."' and g.display_terms_end >= '".$now_date."'))
						and g.goods_type = 'goods'
						group by oi.goods_seq
						limit 20
					) t order by rand() desc limit 1";
*/
			// 추천상품 검색 쿼리문 개선 2017-12-04
			$query = "select SQL_NO_CACHE * from (
					SELECT * FROM (
					SELECT g.goods_seq, g.goods_name, g.sale_seq, SUM( ei.ea ) AS total_ea, g.string_price_use, g.string_price, g.member_string_price_use, g.member_string_price, g.allmember_string_price_use, g.allmember_string_price,
					( SELECT price FROM fm_goods_option WHERE goods_seq = g.goods_seq AND default_option = 'y' LIMIT 1 ) AS price,
					(( SELECT price FROM fm_goods_option WHERE goods_seq = g.goods_seq AND default_option = 'y' LIMIT 1 ) * SUM( ei.ea )) as total_price
					FROM fm_order_item oi, fm_goods_export_item ei, fm_goods_export ex, fm_goods g
					WHERE oi.item_seq = ei.item_seq
					AND ei.export_code = ex.export_code
					AND oi.goods_seq = g.goods_seq
					AND oi.goods_name like '%".$key."%'
					AND ex.`status` = '75'
					AND ex.shipping_date >= '".$enddate."'
					AND (g.goods_view = 'look' or ( g.display_terms = 'AUTO' and g.display_terms_begin <= '".$now_date."' and g.display_terms_end >= '".$now_date."'))
					AND g.goods_type = 'goods' GROUP BY oi.goods_seq ) AS t ORDER BY t.total_ea DESC, t.total_price DESC LIMIT 20 )
					as c order by rand() limit 1";

			$query = $this->db->query($query);
			foreach ($query->result_array() as $row){

				// 카테고리정보
				$tmparr2	= array();
				$categorys	= $this->goodsmodel->get_goods_category($row['goods_seq']);
				foreach($categorys as $val){
					$tmparr = $this->categorymodel->split_category($val['category_code']);
					foreach($tmparr as $cate) $tmparr2[] = $cate;
				}
				if($tmparr2){
					$tmparr2 = array_values(array_unique($tmparr2));
					$row['r_category']	= $tmparr2;
				}

				$o_sql = "select price,consumer_price from fm_goods_option where goods_seq = '".$row['goods_seq']."' and default_option='y'  limit 1";
				$o_query = $this->db->query($o_sql);
				$o_row = $o_query->row_array();
				$row['consumer_price']			= $o_row['consumer_price'];
				$row['price']						= $o_row['price'];

				$i_sql = "select image from fm_goods_image where goods_seq = '".$row['goods_seq']."' and cut_number ='1' and (image_type = 'list1' or image_type = 'list2') limit 1";
				$i_result = $this->db->query($i_sql);
				$i_result = $i_result->row_array();
				$row['goods_img']					= $i_result['image'];

				//----> sale library 적용
				unset($param, $sales);
				$param['option_type']				= 'option';
				$param['consumer_price']			= $row['consumer_price'];
				$param['price']						= $row['price'];
				$param['total_price']				= $row['price'];
				$param['ea']						= 1;
				$param['category_code']				= $row['r_category'];
				$param['goods_seq']					= $row['goods_seq'];
				$param['goods']						= $row;
				$this->sale->set_init($param);
				$sales								= $this->sale->calculate_sale_price($applypage);
				$row['price']						= $sales['result_price'];
				$this->sale->reset_init();
				//<---- sale library 적용

				// 회원 등급별 가격대체문구 출력
				$row['string_price'] = get_string_price($row);
				$row['string_price_use']	= 0;
				if	($row['string_price'] != '')	$row['string_price_use']	= 1;

				$temp_price = "";
				if ($row['string_price_use']==1) {
					$temp_price = $row['string_price'];
				} else {
					$temp_price = get_currency_price($sales['sale_price'],2);
				}
				$row['replace_price'] = $temp_price;

				//예약 상품의 경우 문구를 넣어준다 2016-11-07
				$row['goods_name']	=  get_goods_pre_name($row);

				$result_recomm[] = $row;
			}
		}
		// if(!$result) unset($key);
		if(!$key && $cfg_search['popular_search']=='y'){
			unset($result, $result_recomm);
			$timestamp = strtotime('-'.$cfg_search['popular_search_limit_day'].'day');
			$enddate = date('Y-m-d',$timestamp);

			$query = "select SQL_NO_CACHE * from (select keyword, sum(cnt) cnt from fm_search_list where regist_date >= '".$enddate."' group by `keyword` order by cnt desc limit 10) t order by cnt desc";
			$query = $this->db->query($query);
			foreach ($query->result_array() as $row){
				$row['key'] = $row["keyword"];
				$result[] = $row;
			}

			$timestamp = strtotime('-'.$cfg_search['popular_search_recomm_limit_day'].'day');
			$enddate = date('Y-m-d',$timestamp);
/*
			$query = "select SQL_NO_CACHE * from (
						select g.goods_seq, g.goods_name, g.sale_seq,g.string_price_use, g.string_price, g.member_string_price_use, g.member_string_price, g.allmember_string_price_use, g.allmember_string_price,g.display_terms,g.display_terms_text,g.display_terms_color
						from fm_order_item oi,fm_goods_export_item ei,fm_goods_export ex,fm_goods g
						where oi.item_seq=ei.item_seq
						and ei.export_code=ex.export_code
						and oi.goods_seq=g.goods_seq
						and ex.`status`='75'
						and ex.shipping_date >= '".$enddate."'
						and (g.goods_view = 'look' or ( g.display_terms = 'AUTO' and g.display_terms_begin <= '".$now_date."' and g.display_terms_end >= '".$now_date."'))
						and g.goods_type = 'goods'
						group by oi.goods_seq
						limit 20
					) t order by rand() desc limit 1";
*/
			// 추천상품 검색 쿼리문 개선 2017-12-04
			$query = "select SQL_NO_CACHE * from (
					SELECT * FROM (
					SELECT g.goods_seq, g.goods_name, g.sale_seq, SUM( ei.ea ) AS total_ea, g.string_price_use, g.string_price, g.member_string_price_use, g.member_string_price, g.allmember_string_price_use, g.allmember_string_price,
					( SELECT price FROM fm_goods_option WHERE goods_seq = g.goods_seq AND default_option = 'y' LIMIT 1 ) AS price,
					(( SELECT price FROM fm_goods_option WHERE goods_seq = g.goods_seq AND default_option = 'y' LIMIT 1 ) * SUM( ei.ea )) as total_price
					FROM fm_order_item oi, fm_goods_export_item ei, fm_goods_export ex, fm_goods g
					WHERE oi.item_seq = ei.item_seq
					AND ei.export_code = ex.export_code
					AND oi.goods_seq = g.goods_seq
					AND ex.`status` = '75'
					AND ex.shipping_date >= '".$enddate."'
					AND (g.goods_view = 'look' or ( g.display_terms = 'AUTO' and g.display_terms_begin <= '".$now_date."' and g.display_terms_end >= '".$now_date."'))
					AND g.goods_type = 'goods' GROUP BY oi.goods_seq ) AS t ORDER BY t.total_ea DESC, t.total_price DESC LIMIT 20 )
					as c order by rand() limit 1";

			$query = $this->db->query($query);
			foreach ($query->result_array() as $row){

				// 카테고리정보
				$tmparr2	= array();
				$categorys	= $this->goodsmodel->get_goods_category($row['goods_seq']);
				foreach($categorys as $val){
					$tmparr = $this->categorymodel->split_category($val['category_code']);
					foreach($tmparr as $cate) $tmparr2[] = $cate;
				}
				if($tmparr2){
					$tmparr2 = array_values(array_unique($tmparr2));
					$row['r_category']	= $tmparr2;
				}

				$o_sql = "select price,consumer_price from fm_goods_option where goods_seq = '".$row['goods_seq']."' and default_option='y'  limit 1";
				$o_query = $this->db->query($o_sql);
				$o_row = $o_query->row_array();
				$row['consumer_price']			= $o_row['consumer_price'];
				$row['price']						= $o_row['price'];

				$i_sql = "select image from fm_goods_image where goods_seq = '".$row['goods_seq']."' and cut_number ='1' and (image_type = 'list1' or image_type = 'list2') limit 1";
				$i_result = $this->db->query($i_sql);
				$i_result = $i_result->row_array();
				$row['goods_img']					= $i_result['image'];

				//----> sale library 적용
				unset($param, $sales);
				$param['option_type']				= 'option';
				$param['consumer_price']			= $row['consumer_price'];
				$param['price']						= $row['price'];
				$param['total_price']				= $row['price'];
				$param['ea']						= 1;
				$param['category_code']				= $row['r_category'];
				$param['goods_seq']					= $row['goods_seq'];
				$param['goods']						= $row;
				$this->sale->set_init($param);
				$sales								= $this->sale->calculate_sale_price($applypage);
				$row['price']						= $sales['result_price'];
				$this->sale->reset_init();
				//<---- sale library 적용

				// 회원 등급별 가격대체문구 출력
				$row['string_price'] = get_string_price($row);
				$row['string_price_use']	= 0;
				if	($row['string_price'] != '')	$row['string_price_use']	= 1;

				$temp_price = "";
				if ($row['string_price_use']==1) {
					$temp_price = $row['string_price'];
				} else {
					$temp_price = get_currency_price($sales['sale_price'],2);
				}
				$row['replace_price'] = $temp_price;

				//예약 상품의 경우 문구를 넣어준다 2016-11-07
				$row['goods_name']	=  get_goods_pre_name($row);

				$result_recomm[] = $row;
			}
		}

		// 추천상품이 없을 경우
		if(!$result_recomm){
			$query = "
			select SQL_NO_CACHE * from (
			select
			*
			from fm_goods g
			where (g.goods_view = 'look' or ( g.display_terms = 'AUTO' and g.display_terms_begin <= '".$now_date."' and g.display_terms_end >= '".$now_date."'))
			and g.goods_type = 'goods'
			order by
			IFNULL(g.like_count, 0)
			+ IFNULL(g.review_count, 0)
			+ IFNULL(g.purchase_ea, 0)
			+ IFNULL(g.cart_count, 0)
			+ IFNULL(g.wish_count, 0) desc limit 20
			) t
			order by rand()	limit 1";

			$query = $this->db->query($query);
			foreach ($query->result_array() as $row){

				// 카테고리정보
				$tmparr2	= array();
				$categorys	= $this->goodsmodel->get_goods_category($row['goods_seq']);
				foreach($categorys as $val){
					$tmparr = $this->categorymodel->split_category($val['category_code']);
					foreach($tmparr as $cate) $tmparr2[] = $cate;
				}
				if($tmparr2){
					$tmparr2 = array_values(array_unique($tmparr2));
					$row['r_category']	= $tmparr2;
				}

				$o_sql = "select price,consumer_price from fm_goods_option where goods_seq = '".$row['goods_seq']."' and default_option='y'  limit 1";
				$o_query = $this->db->query($o_sql);
				$o_row = $o_query->row_array();
				$row['consumer_price']			= $o_row['consumer_price'];
				$row['price']						= $o_row['price'];

				$i_sql = "select image from fm_goods_image where goods_seq = '".$row['goods_seq']."' and cut_number ='1' and (image_type = 'list1' or image_type = 'list2') limit 1";
				$i_result = $this->db->query($i_sql);
				$i_result = $i_result->row_array();
				$row['goods_img']					= $i_result['image'];

				//----> sale library 적용
				unset($param, $sales);
				$param['option_type']				= 'option';
				$param['consumer_price']			= $row['consumer_price'];
				$param['price']						= $row['price'];
				$param['total_price']				= $row['price'];
				$param['ea']						= 1;
				$param['category_code']				= $row['r_category'];
				$param['goods_seq']					= $row['goods_seq'];
				$param['goods']						= $row;
				$this->sale->set_init($param);
				$sales								= $this->sale->calculate_sale_price($applypage);
				$row['price']						= $sales['result_price'];
				$this->sale->reset_init();
				//<---- sale library 적용

				// 회원 등급별 가격대체문구 출력
				$row['string_price'] = get_string_price($row);
				$row['string_price_use']	= 0;
				if	($row['string_price'] != '')	$row['string_price_use']	= 1;

				$temp_price = "";
				if ($row['string_price_use']==1) {
					$temp_price = $row['string_price'];
				} else {
					$temp_price = get_currency_price($sales['sale_price'],2);
				}
				$row['replace_price'] = $temp_price;

				//예약 상품의 경우 문구를 넣어준다 2016-11-07
				$row['goods_name']	=  get_goods_pre_name($row);

				$result_recomm[] = $row;
			}
		}

		$this->template->assign(array('popular_search_use'=>$cfg_search['popular_search']));
		$this->template->assign(array('auto_search_use'=>$cfg_search['auto_search']));
		$this->template->assign(array('key'=>$key));
		$this->template->assign(array('skin'=>$this->skin));
		$this->template->assign(array('result'=>$result));
		$this->template->assign(array('result_recomm'=>$result_recomm));
		$this->template->define(array('tpl'=>$this->skin.'/goods/autocomplete.html'));
		$this->template->print_("tpl");
		//echo '</tr><tr><td align="right" valign="bottom"><a href="javascript:autocomplete_nouse();">기능끄기</a></td></tr></table>';
	}

	public function snslinkurl_tag(){
		$this->template->include_('snslinkurl');
		snslinkurl('goods', $_GET['goods_name']);
	}

	public function arrLayoutBasic(){
		$arrLayoutBasic = layout_config_load($this->skin,'basic');

		// width 기본값 없는 경우 넣어줌 :: 2019-01-21 pjw
		if(empty($arrLayoutBasic['basic']['width']))
			$arrLayoutBasic['basic']['width'] = 1000;

		echo json_encode($arrLayoutBasic);
	}

	//SNSlink 짧은주소
	public function get_shortURL(){
		if($_GET['url'] && $this->arrSns['shorturl_use'] == 'Y' && ($this->arrSns['shorturl_app_id'] && $this->arrSns['shorturl_app_key']) ||  $this->arrSns['shorturl_app_token']){
			list($sns_url_fa, $result) = get_shortURL($_GET['url']);
			// 변환 되지 않았다면, 기존 URL 사용 함
			if($result === false) $sns_url_fa = $_GET['url'];
		}
		if($_GET['jsoncallback']) {
			echo $_GET["jsoncallback"] ."(".json_encode($sns_url_fa).");";
		}else{
			echo json_encode($sns_url_fa);
		}
	}

	/* 우측 퀵메뉴 리스트 생성 (ajax 호출) */
	public function get_right_display(){
		$type = $this->input->get('type');
		$page = $this->input->get('page');
		$limit = $this->input->get('limit');
		$sc = $this->input->get();
		$result = array();
		$fname="recent";

		if ($type=="right_item_recent") {
			$today_view = $this->input->cookie('today_view',TRUE);
			$this->load->library('memberlibrary');
			$today_view_member = $this->memberlibrary->today_view_member($this->userInfo['member_seq'], [
				'today_view' => $today_view,
				'page' => $page,
				'limit' => $limit,
			]);
			$result = $today_view_member['result'];
		} else if ($type=="right_item_recomm") {
			$this->load->model('goodsmodel');
			$data = $this->goodsmodel->get_recommend_goods_list($page,$limit);
			$result = $this->goodsmodel->get_recommend_item($data);
			$fname="recommend";
		} else if ($type=="right_item_cart") {
			$this->load->model('cartmodel');
			$result = $this->cartmodel->get_right_cart_list($page,$limit);
			$fname="cart";
		} else if ($type=="right_item_wish") {
			if ($this->userInfo['member_seq']) {
				$member_seq = $this->userInfo['member_seq'];
				$this->load->model('wishmodel');
				$result = $this->wishmodel->get_right_wish_list($member_seq,$page,$limit);
			}
			$fname="wish";
		}

		if ($result) {
			$this->load->library('goodsList');
			foreach ($result as $key => $value) {

				// 19mark 이미지
				$markingAdultImg = $this->goodslist->checkingMarkingAdultImg($value);
				if($markingAdultImg) {
					$result[$key]['image'] = $this->goodslist->adultImg;
				}

				if	($result[$key]['sale_price'])	$result[$key]['price']	= $result[$key]['sale_price'];

				$lenGood = strlen(strip_tags($result[$key]['goods_name']));
				if ($lenGood > 15) {
					$result[$key]['goods_name'] = getstrcut(strip_tags($result[$key]['goods_name']),15);
				}

				// 회원 등급별 가격대체문구 출력
				$result[$key]['string_price'] = get_string_price($result[$key]);
				$result[$key]['string_price_use']	= 0;
				if	($result[$key]['string_price'] != '')	$result[$key]['string_price_use']	= 1;

				$temp_price = "";
				if ($result[$key]['string_price_use']==1) {
					$temp_price = $result[$key]['string_price'];
				} else {
					$temp_price = get_currency_price($result[$key]['sale_price'],2);
				}
				$result[$key]['replace_price'] = $temp_price;
			}
		}

		$this->template->assign(array('dataRightQuicklist'=>$result,'sc'=>$sc));
		$this->template->define(array('tpl'=>$this->skin.'/_modules/display/right_'.$fname.'_display.html'));
		$this->template->print_("tpl");
	}

	/* 우측 퀵메뉴 총개수 (ajax 호출) */
	public function get_right_total(){
		$type = $_GET["type"];

		if ($type=="right_item_cart") {
			$this->load->model('cartmodel');
			$total= number_format($this->cartmodel->get_cart_count());
		} else if ($type=="right_item_wish") {
			$total = 0;

			if ($this->userInfo['member_seq']) {
				$this->load->model('wishmodel');
				$total = $this->wishmodel->get_wish_count($this->userInfo['member_seq']);
			}
		} else if ($type=="right_item_recent") {
			$this->load->library('memberlibrary');
			$today_view = $this->input->cookie('today_view', true);
			$today_view_member = $this->memberlibrary->today_view_member($this->userInfo['member_seq'], [
				'today_view' => $today_view
			]);
			$total = $today_view_member['total'];
		}
		echo $total;
	}

	/* 배송조회 URL 추출 */
	public function get_delivery_url(){
		$sql		= "select * from fm_config where groupcd = 'delivery_url'";
		$query		= $this->db->query($sql);
		$delivery	= $query->result_array();
		if	($delivery)foreach($delivery as $k => $data){
			$info	= unserialize(stripslashes($data['value']));
			if	($data['codecd'] && $info['url']){
				$result[$data['codecd']]	= $info['url'];
			}
		}

		// 자동화 배송 url 호출 :: 2015-10-06 lwh
		$this->load->helper('shipping');
		$gf_delivery = get_invoice_company($this->input->get('provider_seq'));
		foreach ($gf_delivery as $k => $gf) {
			$result[$k] = $gf['url'];
		}

		echo json_encode($result);
	}

	/* data경로 체크 */
	public function _datapath_check($path){
		/* data폴더 하위가 맞으면 true 아니면 false */
		return preg_match("/^{$this->dataPath}/",$path) ? true : false;
	}

	/* 파일 업로드*/
	public function upload_file(){

		/* 이미지파일 확장자 */
		$this->arrImageExtensions = array('jpg','jpeg','png','gif','bmp','tif','pic');
		$this->arrImageExtensions = array_merge($this->arrImageExtensions,array_map('strtoupper',$this->arrImageExtensions));

		// 데모몰에서는 차단
		if($this->demo){
			//데모몰에서는 업로드가 불가합니다.
			//'업로드 불가'
			$result = array(
				'status' => 0,
				'msg' => getAlert('et011'),
				'desc' => getAlert('et012')
			);
			echo "[".json_encode($result)."]";
			exit;
		}
		//업로드 실패하였습니다.
		//업로드 실패
		$result = array(
			'status' => 0,
			'msg' => getAlert('et005'),
			'desc' => getAlert('et004')
		);

		$path = 'data/tmp';
		$targetPath = ROOTPATH.$path;

		if (!empty($_FILES)) {

			// 파일명 변경안되게 수정 2015-04-22 leewh
			//$fileName = $_POST['randomFilename'] ? "temp_".time().sprintf("%04d",rand(0,9999)) : $_FILES['Filedata']['name'];
			$fileName = $_FILES['Filedata']['name'];
			$file = $path.'/'.$fileName;

			$t=0;
			while(file_exists($file)){
				$file = $path.'/'.$fileName;
				$file = substr($file,0,strpos($file,"."))."_$t".strstr($file,".");
				$t++;
			}
			if ($t>0) $fileName = str_replace($path.'/',"",$file);

			if(preg_match("/[\xA1-\xFE\xA1-\xFE]/",$_FILES['Filedata']['name']) && !$_POST['allowKorean']){
				//'파일명에 한글 또는 특수문자가 포함되어있습니다.<br />영문 파일명으로 변경 후 업로드해주세요.'
				//한글/특수문자 파일명 업로드 불가
				$result = array(
					'status' => 0,
					'msg' => getAlert('et013'),
					'desc' => getAlert('et014')
				);
			}else{
				$size = getimagesize($_FILES['Filedata']['tmp_name']);
				$_FILES['Filedata']['type'] = $size['mime'];
				$config['upload_path'] = $targetPath;
				$config['allowed_types'] = implode('|',$this->arrImageExtensions);
				$config['max_size']	= 2048; // 사용자 업로드는 2MB로 제한
				$config['file_name'] = $fileName;
				$config['overwrite'] = true;
				$this->load->library('upload', $config);
				if ( ! $this->upload->do_upload('Filedata'))
				{
					//업로드 실패
					$result = array(
						'status' => 0,
						'msg' => $this->upload->display_errors(),
						'desc' => getAlert('et004')
					);
				}else{
					$fileInfo = $this->upload->data();
					$filePath = $path.'/'.$fileInfo['file_name'];
					@chmod($targetPath,0777);
					$result = array('status' => 1,'filePath' => $filePath,'fileInfo'=>$fileInfo);
				}
			}
		}

		echo "[".json_encode($result)."]";

	}

	// 모바일 - 좌측 네비게이션 로딩
	public function ajax_mobile_layout_side(){

		$this->load->model('categorymodel');
		$this->load->model('brandmodel');
		$this->load->model('locationmodel');

		if($this->userInfo['member_seq']){
			$favorite_category	= $this->categorymodel->get_all(array("hide != '1'","b.member_category_seq is not null","ctype = 'category'"));
			$favorite_brand		= $this->brandmodel->get_all(array("hide != '1'","b.member_category_seq is not null","ctype = 'brand'"));
		}

		$cache_item_id = 'category_mobile_layout_side';
		$category = cache_load($cache_item_id);
		if ($category === false || $this->managerInfo || $this->userInfo['member_seq']) {
			$category	= $this->categorymodel->get_category_view(null, 2);

			//
			cache_save($cache_item_id, $category);
		}

		//
		$cache_item_id = 'brand_mobile_layout_side';
		$brand = cache_load($cache_item_id);
		if ($brand === false || $this->managerInfo) {
			$brand		= $this->brandmodel->get_brand_view(null,2);

			//
			cache_save($cache_item_id, $brand);
		}

		//
		$cache_item_id = 'location_mobile_layout_side';
		$location = cache_load($cache_item_id);
		if ($location === false || $this->managerInfo) {
			$location	= $this->locationmodel->get_location_view(null,2);

			//
			cache_save($cache_item_id, $location);
		}

		//
		$cache_item_id = 'brand_best_mobile_layout_side';
		$best_brand = cache_load($cache_item_id);
		if ($best_brand === false || $this->managerInfo) {
			$best_brand		= $this->brandmodel->get_all(array("hide != '1'","best='Y'"));

			//
			cache_save($cache_item_id, $best_brand);
		}

		$this->template->assign(array(
			'category'=>$category,
			'brand'=>$brand,
			'location'=>$location,
			'favorite_category'=>$favorite_category,
			'favorite_brand'=>$favorite_brand,
			'best_brand'=>$best_brand,
		));

		// 공통 - 모바일 사이드 회원 바코드
		$this->load->library('o2o/o2oinitlibrary');
		$this->o2oinitlibrary->init_front_mobile_side_barcode();

		$skin = $this->skin;
		$this->template_path = "_modules/common/layout_side.html";
		$this->template->define(array('LAYOUT_SIDE'=>$this->skin."/".$this->template_path));
		$this->template->print_('LAYOUT_SIDE');
	}

	// 모바일 - 즐겨찾기 추가,삭제
	public function ajax_category_favorite(){
		$ctype = preg_replace("/[^a-z]/i","",$_GET['ctype']);
		$ccode = preg_replace("/[^0-9]/i","",$_GET['ccode']);

		if($this->userInfo['member_seq']){
			$query = $this->db->query("select * from fm_member_category where ctype=? and ccode=? and member_seq=?",array($ctype,$ccode,$this->userInfo['member_seq']));
			if($query->row_array()){
				$this->db->query("delete from fm_member_category where ctype=? and ccode=? and member_seq=?",array($ctype,$ccode,$this->userInfo['member_seq']));
				echo 'off';
			}else{
				$this->db->query("insert into fm_member_category set ctype=?, ccode=?, member_seq=?",array($ctype,$ccode,$this->userInfo['member_seq']));
				echo 'on';
			}
		}
	}

	// 임시폴더로 파일 업로드
	public function fmupload(){

		$this->load->model('usedmodel');
		$res = $this->usedmodel->used_limit_check();
		if(!$res['type']){
			$result[0]['status']	= false;
			//파일 저장 공간이 부족하여 업로드가 불가능합니다.
			//업로드 실패
			$result[0]['msg']		= getAlert('et003');
			$result[0]['desc']		= getAlert('et004');
			echo "[".json_encode($result)."]";
			exit;
		}
		$result[0]['status']		= false;
		//업로드 실패하였습니다.
		$result[0]['msg']			= getAlert('et005');
		$result[0]['desc']			= getAlert('et004');

		/* 이미지파일 확장자 */
		$this->arrImageExtensions = array('jpg','jpeg','png','gif','bmp','tif','pic','ai','psd','eps','dwg');
		$this->arrImageExtensions = array_merge($this->arrImageExtensions,array_map('strtoupper',$this->arrImageExtensions));

		$path		= '/data/tmp';
		$targetPath	= ROOTPATH . $path;

		if (!empty($_FILES)) {
			$idx	= 0;
			foreach($_FILES as $fileKey => $filedata){
				$fileName					= "temp_".time().sprintf("%04d",rand(0,9999));
				$size						= getimagesize($filedata['tmp_name']);
				$filedata['type']			= $size['mime'];
				$config['upload_path']		= $targetPath;
				$config['allowed_types']	= implode('|',$this->arrImageExtensions);
				$config['max_size']			= $this->config_system['uploadLimit'];
				$config['file_name']		= $fileName;
				$this->load->library('upload', $config);
				if	( !$this->upload->do_upload($fileKey) ){
					$result[$idx]['status']	= false;
					$result[$idx]['msg']	= $this->upload->display_errors();
					$result[$idx]['desc']	= getAlert('et004'); //업로드 실패
				}else{
					$fileInfo					= $this->upload->data();
					$filePath					= $path . '/' . $filedata['file_name'];
					ImgLotate($config['upload_path'].'/'.$fileInfo['file_name']);//@2017-04-25
					$result[$idx]['status']		= true;
					$result[$idx]['msg']		= getAlert('et015'); //파일이 정상적으로 업로드 되었습니다.
					$result[$idx]['desc']		= getAlert('et016'); //업로드 성공
					$result[$idx]['filePath']	= $filePath;
					$result[$idx]['fileInfo']	= $fileInfo;
				}

				$idx++;
			}
		}

		echo json_encode($result);
	}

	//바코드 이미지 생성 슈퍼관리자와 입점관리자 공통함수 @2016-08-02 ysm
	public function barcode_image(){
		$this->load->model('barcodemodel');

		$code_type	= $_GET['code_type'] ? $_GET['code_type'] : 'code128';
		$code_value = $_GET['code_value'] ? $_GET['code_value'] : '123456789';
		$code_size	= $_GET['code_size'] ? $_GET['code_size'] : '40';

		$chk_subtype = explode('_', $code_type);
		if(count($chk_subtype) > 1){
			$code_type		= $chk_subtype[0];
			$code_subtype	= 'Start '. strtoupper($chk_subtype[1]);
		}else{
			$code_subtype = null;
		}

		$this->barcodemodel->create_barcode($code_type, $code_subtype, $code_value, $code_size);
	}

	# front용 cutting price 구하기.(common-function.js 호출용)
	public function get_front_cutting_price(){

		$res = get_cutting_price($_GET['price'],$_GET['currency']);

		echo $res;
	}

	public function ssl_action(){
		$action		= $_GET['action'];
		$boardid	= $_GET['boardid'];

		$this->load->helper('url');
		$this->load->model('ssl');

		//모바일인경우 글쓰기 첨부파일 사용시 첨부파일 전달 오류로 보안예외 모바일인경우 첨부파일용때문에 보안제외
		if( $this->manager['file_use'] == 'Y' && uri_string() == "board/write" && $boardid && ($this->_is_mobile_agent || $this->_is_mobile_domain)){
			echo $action;
			exit;
		}

		if(!$this->ssl->ssl_use){
			echo $action;
			exit;
		}
		if(!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']=='on'){
			echo $action;
			exit;
		}
		if(!preg_match("/^http:\/\//",$action)){
			$protocol = 'http://';
			$domain = $_SERVER['HTTP_HOST'];
			$port = $_SERVER['SERVER_PORT']==80 ? '' : ':'.$_SERVER['SERVER_PORT'];

			if(preg_match("/^\//",$action)){
				$action = $protocol.$domain.$port.$action;
			}else{
				$action = $protocol.$domain.$port.'/'.dirname(uri_string()).'/'.$action;
			}
		}

		echo $this->ssl->get_ssl_action($action);
	}

	// 공통 ajax 파일 업로드 :: 2018-11-20 pjw
	public function ajax_file_upload(){

		$params = $this->input->post();

		// 저장 공간 체크
		$this->load->model('usedmodel');
		$res = $this->usedmodel->used_limit_check();
		if(!$res['type']){
			//파일 저장 공간이 부족하여 업로드가 불가능합니다.
			//업로드 실패
			$result = array(
				'status'	=> false,
				'msg'		=> getAlert('et003'),
				'desc'		=> getAlert('et004')
			);
			echo json_encode($result);
			exit;
		}

		// 기본 이미지 확장자 목록
		$this->defaultImageTypes = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'ico'];

		// 파일 확장자 목록 정의 :: default = 이미지파일확장자
		$this->fileExtList = !empty($params['allow_types']) ? explode('|', $params['allow_types']) : $this->defaultImageTypes;
		$this->fileExtList = array_merge($this->fileExtList,array_map('strtoupper',$this->fileExtList));

		/**
		 * 기본적으로 허용되는 업로드 확장자가 있지만, 예외적으로 업로드되는 허용되는 확장자가 있다.
		 */
		$isExceptionUpload = (isset($params['allow_types']) === true) ? true : false;
		if ($isExceptionUpload === true) {
			/**
			 * 예외적으로 허용 되어야 하는 타입 ("allow_types", "allowTypes" 로 검색해서 추가함..)
			 *
			 * 프론트를 조작 해서 "php" 가 넘어 온다면 문제가 된다.
			 */
			$exceptionFiles = [
				'p8',
				'xls',
				'docx',
				'pptx',
				'xlsx',
				'hwp',
				'jpg',
				'png',
				'gif',
			];
			// 업로드 가능한 모든 확장자
			$checkFiles = array_merge($this->defaultImageTypes, $exceptionFiles);

			// 업로드 가능여부 검사
			$isExceptionFiles = true;
			for ($i = 0; $i < count($this->fileExtList); $i++) {
				$ext = strtolower($this->fileExtList[$i]);
				if (array_search($ext, $checkFiles) === false) {
					$isExceptionFiles = false;
					break;
				}
			}

			 /**
			  * 업로드 불가 메세지 반환
			  * "올바른 파일이 아닙니다"
			  */
			if ($isExceptionFiles === false) {
				$result = [
					'status' => false,
					'msg' => getAlert('et001'),
					'desc' => getAlert('et004')
				];
				echo json_encode($result);
				exit;
			}
		}

		// 업로드 결과 변수 선언 :: default = 실패
		$result = array(
			'status'	=> false,
			'msg'		=> getAlert('et005') ."12",
			'desc'		=> getAlert('et004')
		);

		// 업로드 할 파일이 있을 경우만 실행
		if (!empty($_FILES)){
			$aFilesParams = $_FILES;
			if(is_array($_FILES['filedata']['name'])){
				$filenames = $params['filename'];
				foreach($aFilesParams['filedata']['name'] as $key => $name){
					$files['filedata'] = array(
								'name'		=> $name,
								'type'		=> $aFilesParams['filedata']['type'][$key],
								'tmp_name'	=> $aFilesParams['filedata']['tmp_name'][$key],
								'error'		=> $aFilesParams['filedata']['error'][$key],
								'size'		=> $aFilesParams['filedata']['size'][$key],
					);
					//$params['filename'] = $name;
					$return[] = $this->ajax_file_upload_processing($params,$files,$key);
				}
				$result = $return;
			}else{
				$files 	= $_FILES;
				$result = $this->ajax_file_upload_processing($params,$files);
			}

		}

		echo json_encode($result);
	}

	/**
	 * params : post data
	 * files : file data
	 */
	public function ajax_file_upload_processing($params, $files, $idx=''){

		$_FILES = $files;

		$tmp_filename_default = 'temp_'.time().sprintf("%04d",rand(0,9999));

		// 상품 이미지일 경우 임시파일명에 view 붙이기(view 가 없으면 리사이징 처리 안됨)
		if($params['filemode'] == "goods"){
			$params['filename']	= $tmp_filename_default."view";
		}else{
			// post 에서 filename 지정 안함
			if(empty($params['filename'])) {
				if($params['filepath'] == "/data/tmp") {
					// 임시폴더는 임시파일명으로 업로드
					$params['filename']	= $tmp_filename_default;
				} else if(!empty($_FILES['filedata']['name'])) {
					// 그 외는 아이디자인or직접파일업로드 -- 업로드한 파일명으로 업로드
					$params['filename'] = $_FILES['filedata']['name'];
				}
			}
		}

		$fileExt = explode('.', $_FILES['filedata']['name']);
		$fileExt = $fileExt[count($fileExt) - 1];

		// 파일 확장자가 이미지타입인 경우만 이미지정보함수 호출하여 mime 타입 변경
		if(in_array($fileExt, $this->defaultImageTypes)){
			$size						= getimagesize($_FILES['filedata']['tmp_name']);
			$_FILES['filedata']['type'] = $size['mime'];
		}
		$rand = rand(0,9999)."".$params['filemode'];
		$config['upload_path']          = ROOTPATH.$params['filepath'];
		$config['allowed_types']        = implode('|',$this->fileExtList);
		$config['max_size']             = $this->config_system['uploadLimit'];
		$config['file_name']			= $params['filename'];


		if($idx == 0 || $idx === ''){
			$this->load->library('upload', $config);
		}else{
			$this->upload->initialize($config);
		}
		if($params['overwrite']) $this->upload->overwrite = true;
		if ( !$this->upload->do_upload('filedata') ){
			//업로드 실패
			$result = array(
				'status'	=> false,
				'msg'		=> $this->upload->display_errors(),
				'error'		=> '',
				'desc'		=> getAlert('et004')
			);
		}else{
			$fileInfo = $this->upload->data();

			$filePath = $params['filepath'].'/';
			ImgLotate($config['upload_path'].'/'.$fileInfo['file_name']);//@2017-04-25

			//debug($fileInfo);
			// 상품 이미지일 경우 사이즈별로 리사이징
			if($params['filemode'] == "goods"){
				$this->load->model('goodsmodel');
				$arrDiv 		= config_load('goodsImageSize');
				//debug($arrDiv);
				$source 		= $fileInfo['full_path'];
				//debug("source : ".$source);
				$resizeResult 	= array();
				foreach($arrDiv as $tmp => $size){
					//if($tmp != "view"){
						$target 		= $fileInfo['file_path'].$tmp_filename_default.$tmp.$fileInfo['file_ext'];
						//debug($tmp." : " .$arrDiv[$tmp]['width']." : " .$arrDiv[$tmp]['height']. " : ".$target);
						$resizeResult[]	= $this->goodsmodel->goods_temp_image_resize($source,$target,$arrDiv[$tmp]['width'],$arrDiv[$tmp]['height']);
					//}
				}

				//debug($resizeResult);
			}
			// 불필요한 정보 제거
			unset($fileInfo['file_path']);
			unset($fileInfo['full_path']);

			$result = array('status' => true,'filePath' => $filePath,'fileInfo'=>$fileInfo,'imagesize'=>$resizeResult);

			@chmod(ROOTPATH.$filePath.$fileInfo['file_name'], 0777);
		}

		return $result;
	}


	public function check_password_validation(){
		$this->load->model('membermodel');

		$params = array();
		$params['birthday'] = array();
		$params['phone'] = array();
		$params['cellphone'] = array();
		$result_password_check_msg = array('code' => '00', 'alert_code' => '');
		$password = $this->input->post('password');
		$data['seq'] =  $this->input->post('seq');

		// 생년월일
		if($this->input->post('birthday')){
			$params['birthday'][] =  $this->input->post('birthday');
		}
		// 기념일
		if($this->input->post('anniversary')){
			$params['birthday'][] =  $this->input->post('anniversary');
		}
		// 연락처
		if($this->input->post('phone')){
			if(is_array($this->input->post('phone'))){
				$params['phone'][] = implode("-", $this->input->post('phone'));
			}else{
				$params['phone'][] = $this->input->post('phone');
			}
		}
		// 기업회원연락처
		if($this->input->post('bphone')){
			if(is_array($this->input->post('bphone'))){
				$params['phone'][] = implode("-", $this->input->post('bphone'));
			}else{
				$params['phone'][] = $this->input->post('bphone');
			}
		}
		// 핸드폰
		if($this->input->post('cellphone')){
			if(is_array($this->input->post('cellphone'))){
				$params['cellphone'][] = implode("-", $this->input->post('cellphone'));
			}else{
				$params['cellphone'][] = $this->input->post('cellphone');
			}
		}
		// 기업회원핸드폰
		if($this->input->post('bcellphone')){
			if(is_array($this->input->post('bcellphone'))){
				$params['cellphone'][] = implode("-", $this->input->post('bcellphone'));
			}else{
				$params['cellphone'][] = $this->input->post('bcellphone');
			}
		}

		// 핸드폰/연락처/생년월일에 대해 먼저 검증
		$this->load->library('memberlibrary');
		$result_password_check_msg = $this->memberlibrary->check_password_validation($password, $params);

		// 회원 수정인경우
		if($data['seq']){

			// 일반/비지니스 회원의 기존 정보 체크
			if($result_password_check_msg['code'] == '00'){
				$mdata = $this->membermodel->get_member_data($data['seq']);//회원정보
				if($mdata){
					if($mdata['upper_password']){
						$pre_enc_password = $mdata['upper_password'];
						$enc_password = hash('sha256',md5(strtoupper($password)));
					}else {
						$pre_enc_password =  $mdata['password'];
						$enc_password = hash('sha256',md5($password));
					}
					$params['pre_enc_password'] =  $pre_enc_password;
					$params['enc_password'] =  $enc_password;

					$this->load->library('memberlibrary');
					$result_password_check_msg = $this->memberlibrary->check_password_validation($password, $params);

					unset($params['pre_enc_password']);
					unset($params['enc_password']);
					unset($mdata);
				}
			}

			// 관리자 회원의 기존 정보 체크
			if($result_password_check_msg['code'] == '00'){
				$query = "select * from fm_manager where manager_seq=?";
				$query = $this->db->query($query, $data['seq']);
				$mdata = $query->row_array();

				if($mdata){
					if($mdata['upper_mpasswd']){
						$pre_enc_password = $mdata['upper_mpasswd'];
						$enc_password = hash('sha256',md5(strtoupper($password)));
					}else {
						$pre_enc_password = $mdata['mpasswd'];
						$enc_password = hash('sha256',md5($password));
					}
					$params['pre_enc_password'] =  $pre_enc_password;
					$params['enc_password'] =  $enc_password;

					$this->load->library('memberlibrary');
					$result_password_check_msg = $this->memberlibrary->check_password_validation($password, $params);

					unset($params['pre_enc_password']);
					unset($params['enc_password']);
					unset($mdata);
				}
			}

			// 입점사 회원의 기존 정보 체크
			if($result_password_check_msg['code'] == '00'){
				$query = "select * from fm_provider where provider_seq=?";
				$query = $this->db->query($query, $data['seq']);
				$mdata = $query->row_array();
				if($mdata){
					if($mdata['provider_upper_passwd']){
						$pre_enc_password = $mdata['provider_upper_passwd'];
						$enc_password = md5(strtoupper($password));
					}else {
						$pre_enc_password = $mdata['provider_passwd'];
						$enc_password = md5($password);
					}
					$params['pre_enc_password'] =  $pre_enc_password;
					$params['enc_password'] =  $enc_password;

					$this->load->library('memberlibrary');
					$result_password_check_msg = $this->memberlibrary->check_password_validation($password, $params);

					unset($params['pre_enc_password']);
					unset($params['enc_password']);
					unset($mdata);
				}
			}
		}

		echo json_encode($result_password_check_msg);
	}

	/**
	 * 상품 상세에서 배송방법 정보 조회 시 사용함
	 */
	function get_shipping_info() {
		$shipping_set_seq = $this->input->get("shipping_set_seq");

		$this->load->model('shippingmodel');
		$shipping_set = $this->shippingmodel->get_shipping_set($shipping_set_seq, 'shipping_set_seq');

		//상품 상세에서 네이버페이/카카오페이구매 사용여부 체크
		$this->load->helper('order');

		$shipping_set['npay'] 		= npay_useck();
		$shipping_set['talkbuy'] 	= talkbuy_useck();
		
		echo json_encode($shipping_set);
		exit;
	}
}

// END
/* End of file common.php */
/* Location: ./app/controllers/common.php */