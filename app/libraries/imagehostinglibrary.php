<?php

if (!defined('BASEPATH')) {
	exit('No direct script access allowed');
}

use App\Libraries\AdditionService\StringSecurity;
use phpseclib3\Net\SFTP;

/**
 * 이미지호스팅 라이브러리
 * @copyright	2022 Gabia C&S
 */
class Imagehostinglibrary
{
	protected $securityKey; //암호화 키

	public function __construct()
	{
		$this->CI = &get_instance();
		$this->CI->load->model('imagehosting');
		$this->CI->load->library('ftp');
		$this->CI->config->load('imageStoreSet');

		$this->securityKey = '2022.10.18_imagehosting';
	}

	/**
	 * 저장소 설정 저장
	 * @param array @params
	 * @return  message, code
	 */
	public function saveImagehosting($params)
	{		
		try {

			/*
				FTP 비밀번호 암호화
				솔루션 서비스가 변경되어도 데이터 그대로 사용할 수 있게 하기 위해 솔루션별 동일한 Key값으로 암호화
			*/
			$params['store_password'] = StringSecurity::SecurityEncode($this->securityKey . $params['store_password'], $this->securityKey);

			// 이미지호스팅 계정 정보 UPDATE
			if ($params['imagehosting_seq'] != '') {
				$updateRes = $this->CI->imagehosting->update_imagehosting($params);
				if (empty($updateRes)) {
					throw new Exception('저장에 실패하였습니다.', '200');
				}

			// 이미지호스팅 계정 정보 INSERT
			} else {
				$insertRes = $this->CI->imagehosting->insert_imagehosting($params);
				if (empty($insertRes)) {
					throw new Exception('저장에 실패하였습니다.', '100');
				}
			}

			$return = ['code' => '000', 'message' => '설정이 저장 되었습니다.'];

		} catch (Exception $e) {
			$return = ['code' => $e->getCode(), 'message' => $e->getMessage()];
		}

		return $return;
	}

	/**
	 * 이미지호스팅 계정 접속 성공 여부 체크
	 * $aPostParams array
	 * @return result, message
	 */
	public function checkImagehostingConnection($aPostParams)
	{
		// ftp or sftp 접속여부에 따라 분기 처리
		$connection_type = $aPostParams['connection_type'];
		$aPostParams['mode'] = 'test';

		if ($connection_type == 'ftp') {
			$result = $this->uploadImagehostingFtp($aPostParams);
		} elseif ($connection_type == 'sftp') {
			$result = $this->uploadImagehostingSftp($aPostParams);
		}

		// 안내 문구
		$message = '업로드 테스트에 성공하였습니다.</br>저장을 완료해주세요.';
		if ($result['code'] == '100') {
			$message = '업로드 테스트에 실패하였습니다.</br>FTP 접속 정보 확인 후 이용해 주세요.';
		}else if ($result['code'] == '200') {
			$message = '업로드 테스트에 실패하였습니다. </br>입력하신 저장소에 파일이 정상적으로 업로드 되지 않습니다. 확인 후 이용해주세요.';
		}

		return ['code' => $result['code'], 'message' => $message];
	}

	/**
	 * 이미지호스팅 계정 정보 삭제
	 * @param array @aPostParams
	 * @return  message, code
	 */
	public function deleteImagehosting($aPostParams)
	{
		try {
			//기능별 저장소 연결 여부 체크
			$check_isimagestore = $this->CI->imagehosting->count_matched_imagestore($aPostParams['imagehosting_seq']);

			if ($check_isimagestore) {
				throw new Exception('저장소 삭제가 불가능합니다.</br>기능별 저장소 설정에서 사용되는 저장소입니다.</br>삭제가 필요한 경우 설정을 먼저 변경해 주세요.</br>설정페이지로 이동하시겠습니까?', 100);
			}

			// 이미지호스팅 계정 정보 DELETE
			if ($aPostParams['imagehosting_seq']) {
				$deleteRes = $this->CI->imagehosting->delete_imagehosting($aPostParams['imagehosting_seq']);
				if (empty($deleteRes)) {
					throw new Exception('DB 저장 오류', 200);
				} else {
					$return = ['code' => '000', 'message' => '성공'];
				}
			} else {
				throw new Exception('이미지호스팅 고유번호 누락', 300);
			}
		} catch (Exception $e) {
			$return = ['code' => $e->getCode(), 'message' => $e->getMessage()];
		}

		return $return;
	}

	/**
	 * 저장소 설정 저장
	 * @param array @params
	 * @return array update_store_log 성공 update_store_error 오류
	 */
	public function saveImagestore($params)
	{
		$origin_object = $this->CI->imagehosting->get_imagestoreList();
		$imagehosting_object = $this->CI->imagehosting->get_imagehostingList();
		foreach ($imagehosting_object as $imagehosting) { // imagehosting_seq 를 idx로 처리
			$imagehosting_data[$imagehosting['imagehosting_seq']] = $imagehosting;
		}

		$count = 0;
		foreach ($origin_object as $origin_data) {
			$origin_imagehosting_seq = $origin_data['imagehosting_seq'];
			$new_imagehosting_seq = $params['imagehosting_each'][$origin_data['imagestore_seq']];
			$imagestore_seq = $origin_data['imagestore_seq'];

			// 기능별 저장소 데이터 가져오기
			$origin_imagehosting = $imagehosting_data[$origin_imagehosting_seq];
			$change_imagehosting = $imagehosting_data[$new_imagehosting_seq];

			if (!$origin_imagehosting || !$change_imagehosting) {
				continue;
			}

			// 수정된 데이터만 추출
			if ($origin_imagehosting_seq != $new_imagehosting_seq) {
				$update_params = [];
				$update_params['imagestore_seq'] = $imagestore_seq;
				$update_params['imagehosting_seq'] = $new_imagehosting_seq;
				$update_params['update_date'] = date('Y-m-d H:i:s');

				// 설정 UPDATE
				$result = $this->CI->imagehosting->update_imagestore($update_params);
				
				// 기능별 저장소 [구분] imagestore_division_title 값 가져오기
				foreach($params['imagestore_division_title'] as $idx => $title){
					debug_var($idx . ":" . $title);
					if($idx <= $imagestore_seq){
						$imagestore_division_title = $title;
					}
				}
				
				if ($result > 0) {
					// 로그 내역 저장
					// %구분%: %항목%(%구저장소명%>%신저장소명%)
					$return['update_store_log'][] = strip_tags($imagestore_division_title) . ' : ' . strip_tags($params['imagestore_item_title'][$imagestore_seq]) . ' ( ' . $origin_imagehosting['store_name'] . ' > ' . $change_imagehosting['store_name'] . ')';
				} else {
					// 저장 오류
					$return['update_store_error'][] = strip_tags($imagestore_division_title) . ' : ' . strip_tags($params['imagestore_item_title'][$imagestore_seq]) . ' ( ' . $origin_imagehosting['store_name'] . ' > ' . $change_imagehosting['store_name'] . ')';
				}
				$count++;
			}
		}
		$return['count'] = $count;

		return $return;
	}

	/**
	 * 각 기능별 이미지 호스팅 설정 여부 체크
	 * $params: arry(imagestore_division, imagestore_item)
	 * @return ture or false
	 */
	public function isImagehostingUse($params = [])
	{
		// 1. 기능별 저장소 연결된 이미지호스팅 확인
		$result_imagestore = $this->CI->imagehosting->get_imagestoreEach($params);
		// false: 기본 로컬경로인 경우
		if (!$result_imagestore['imagehosting_seq'] || $result_imagestore['imagehosting_seq'] == '1') {
			return false;
		}

		// 2. 이미지호스팅 설정 정보
		$result_imagehosting = $this->getImagehostingDecode($result_imagestore['imagehosting_seq']);
		// false: 이미지호스팅 정보가 없는 경우
		if (!$result_imagehosting['imagehosting_seq']) {
			return false;
		}

		return true;
	}

	/**
	 * 이미지호스팅 설정 정보(비밀번호 복호화)
	 * $seq: imagehosting_seq
	 */
	public function getImagehostingDecode($seq)
	{
		$result = $this->CI->imagehosting->get_imagehostingEach($seq);
		$result['store_password'] = str_replace($this->securityKey, '', StringSecurity::SecurityDecode($result['store_password'], $this->securityKey));

		return $result;
	}

	/**
	 * 이미지호스팅 에디터 이미지 업로드
	 * $contents
	 * $imagestore_type array(imagestore.imagestore_division, imagestore.imagestore_item)
	 * @return array [code, message]
	 */
	public function uploadEditorImage($contents, $imagestore_type)
	{
		$is_upload = false; // 에디터 내용에서 업로드한 파일 있는지 여부 체크
		foreach($contents as $type => $content){
			
			$result_content= $content;
			// 1.에디터에서 업로드 대상 뽑아내기 (로컬 경로)
			if(preg_match_all("/<img[^>]*src=[\"']?(\/data[^>\"']+)[\"']?[^>]*>/i",$content,$matches)){
				foreach($matches[1] as $matche){
					$is_upload = true;

					$params = [];
					$params['imagestore_type'] = $imagestore_type;
					$params['current_path_images'] = $matche;
					$params['new_path_images'] = $matche;
					
					// 2.이미지호스팅에 파일 업로드
					$result = $this->uploadImage($params);
					
					// 3. 에디터 데이터 치환
					$result_content = str_replace($matche,$result['new_path'],$result_content);
					$result_imagehosting[$type][] = $result;
				}
			}

			$result_imagehosting[$type]['contents'] = $result_content;			
		}
		$result_imagehosting['is_upload'] = $is_upload;

		return $result_imagehosting;
	}

	/**
	 * 이미지호스팅 이미지 파일 업로드
	 * $imagestore_type array(imagestore.imagestore_division, imagestore.imagestore_item)
	 * $current_path_images: array (local image tmp directory)
	 * $new_path_images: array (local image basic directory)
	 * @return array [code, message]
	 */
	public function uploadImage($params = [])
	{
		// 필수값
		$imagestore_type = $params['imagestore_type'];
		$current_images = $params['current_path_images'];
		$new_images = $params['new_path_images'];

		if (!$imagestore_type || !$current_images || !$new_images) { //필수값 없는 경우
			return false;
		}

		// 이미지호스팅 설정 정보
		$result_imagestore = $this->CI->imagehosting->get_imagestoreEach($imagestore_type);
		$result_imagehosting = $this->getImagehostingDecode($result_imagestore['imagehosting_seq']);

		try {
			$flag = 0; // editort 형태인 경우, db update를 1회만 하기 위함

			// 변환할 이미지경로가 배열이 아닌 경우, 배열 처리
			if (!is_array($current_images)) {
				$current_images = [$current_images];
			}
			if (!is_array($new_images)) {
				$new_images = [$new_images];
			}

			foreach ($current_images as $i => $current_image) {
				$new_image = $new_images[$i];

				// 1. 이미지호스팅 이미지 업로드
				if (substr($current_image, 0, 1) != '/') {
					$current_image = '/' . $current_image;
				}
				if (substr($new_image, 0, 1) != '/') {
					$new_image = '/' . $new_image;
				}
				$result_imagehosting['current_image'] = $current_image;
				$result_imagehosting['new_image'] = $new_image;

				// ftp or sftp 접속여부에 따라 분기 처리
				$connection_type = $result_imagehosting['connection_type'];
				$result_imagehosting['mode'] = 'upload';
				if ($connection_type == 'ftp') {
					$result_imageUpload = $this->uploadImagehostingFtp($result_imagehosting);
				} elseif ($connection_type == 'sftp') {
					$result_imageUpload = $this->uploadImagehostingSftp($result_imagehosting);
				}

				if ($result_imageUpload['code'] != '000') {
					throw new Exception($result_imageUpload['code']);
				}

				$return['new_path'] = $result_imagehosting['store_url'] . '/' . $result_imagehosting['store_dir'] . $result_imagehosting['new_image'];

				// 로컬 경로 이미지 삭제
				@unlink(ROOTPATH.$current_image);
			}
		} catch (Exception $e) {
			$return['code'] = '100';

			if (defined('__ADMIN__') === true) {
				$return['message'] = '</br></br>이미지호스팅에 이미지가 업로드되지 않았습니다.';
				$return['message'] .= '</br>이미지호스팅 접속 정보를 확인하신 후, 재업로드 부탁드립니다.';
				$return['message'] .= '</br><span class="blue"><a href="/admin/setting/imagehosting" class="link_blue_01" target="_blank">바로가기></a></span>';
			}
			if (defined('__SELLERADMIN__') === true) {
				$return['message'] = '</br></br>이미지호스팅에 이미지가 업로드되지 않았습니다.';
				$return['message'] .= '</br>본사 관리자에게 문의 바랍니다.';
			}

			return $return;
		}

		$return['code'] = '000';
		if (defined('__ADMIN__') === true) {
			$return['message'] = '</br></br><span class="blue">이미지호스팅 서버에 첨부된 이미지 업로드가 성공하였습니다.</span>';
		}

		return $return;
	}

	/**
	 * 이미지호스팅 이미지 파일 업로드 FTP
	 * $imagehosting [mode:test 접속테스트 / upload 실업로드]
	 * $upload_images 파일경로
	 * @return code, message
	 */
	public function uploadImagehostingFtp($aPostParams)
	{
		// setting
		$mode = ($aPostParams['mode']) ? $aPostParams['mode'] : 'upload'; // test 접속테스트 / upload 실업로드
		$imagehostingftp['hostname'] = $aPostParams['store_host'];
		$imagehostingftp['username'] = $aPostParams['store_username'];
		$imagehostingftp['password'] = $aPostParams['store_password'] ;
		$imagehostingftp['port'] = $aPostParams['store_port'];
		$imagehostingftp['passive'] = true; //패시브모드 항상 '사용'
		$imagehostingftp['debug'] = false;
		$imagehostingftp['timeout'] = 2;
		$upload_image = $aPostParams['new_image'];
		$current_image = $aPostParams['current_image'];

		try {
			// 1. 접속 여부 체크
			$connectResult = $this->CI->ftp->connect($imagehostingftp);
			if ($connectResult == false) {
				throw new Exception('connection error', '100');
			}

			// 2. 디렉토리 체크
			if ($mode == 'test') {
				$imagehostingdir = $aPostParams['store_dir'];
			} elseif ($mode == 'upload') {
				$local_directory_array = explode('/', $upload_image);
				array_pop($local_directory_array);
				$local_directory = implode('/', $local_directory_array); // 로컬 디렉토리
				$imagehostingdir = '/' . $aPostParams['store_dir'] . $local_directory; // 이미지호스팅 디렉토리(파일명 제외)
			}
			$changebasedir = $this->CI->ftp->changedir($imagehostingdir, true); //ftp_chdir

			if ($changebasedir == false) { // 기본디렉토리 없는 경우, 생성
				foreach (explode('/', $imagehostingdir) as $idx => $path) {
					if ($idx != 0) {
						$mkdirpath .= '/';
					}
					$mkdirpath .= $path;
					$mkdirbasedir = $this->CI->ftp->mkdir($mkdirpath); //ftp_mkdir;
				}

				if ($mkdirbasedir == false) {
					throw new Exception('connection error', '100');
				} else { //생성한 기본디렉토리 접속 체크
					$mkdirChangebasedir = $this->CI->ftp->changedir($imagehostingdir, true); //ftp_chdir
					if ($mkdirChangebasedir == false) {
						throw new Exception('connection error', '100');
					}
				}
			}

			// 3. 파일 업로드
			if ($mode == 'test') {
				$local_filepath = $_SERVER['DOCUMENT_ROOT'] . '/data/tmp/firstmall_imagehosting.txt';
				$fp = fopen($local_filepath, 'w');
				fclose($fp);
				chmod($local_filepath, 0777);
				$imagehosting_filepath = '/' .$imagehostingdir . '/firstmall_imagehosting.txt';
			} elseif ($mode == 'upload') {
				$local_filepath = $_SERVER['DOCUMENT_ROOT'] . $current_image;
				$imagehosting_filepath = '/' . $aPostParams['store_dir'] . $upload_image;
			}

			$imgserver = $this->CI->ftp->upload($local_filepath, $imagehosting_filepath, 'binary'); //ftp_put
			if ($imgserver == false) {
				throw new Exception('upload error', '200');
			}

			$this->CI->ftp->close();
			return ['code' => '000', 'message' => '성공'];

		} catch (Exception $e) {
			$this->CI->ftp->close();
			return ['code' => $e->getCode(), 'message' => $e->getMessage()];
		}
	}

	/**
	 * 이미지호스팅 이미지 파일 업로드 SFTP
	 * $imagehosting [mode:test 접속테스트 / upload 실업로드]
	 * $upload_images 파일경로
	 * @return code, message
	 */
	public function uploadImagehostingSftp($aPostParams)
	{
		// setting
		$mode = ($aPostParams['mode']) ? $aPostParams['mode'] : 'upload'; // test 접속테스트 / upload 실업로드
		$upload_image = $aPostParams['new_image'];
		$current_image = $aPostParams['current_image'];

		try {
			//define('NET_SSH2_LOGGING', 2); //getLog 를 위함
			// 1. 접속 여부 체크
			$sftp = new SFTP($aPostParams['store_host'], $aPostParams['store_port'], 2); // 2초 제한
			$connectResult = $sftp->login($aPostParams['store_username'], $aPostParams['store_password']);
			if ($connectResult == false) {
				throw new Exception('connection error', '100');
			}

			// 1-2. 디렉토리점검
			if ($mode == 'test') {
				$imagehostingdir = $sftp->pwd() . '/' . $aPostParams['store_dir'];
			} elseif ($mode == 'upload') {
				$full_imagehostingdir = $sftp->pwd() . '/' . $aPostParams['store_dir'] . $upload_image;
				$local_directory_array = explode('/', $upload_image);
				array_pop($local_directory_array);
				$local_directory = implode('/', $local_directory_array); // 로컬 디렉토리
				$imagehostingdir = $sftp->pwd() . '/' . $aPostParams['store_dir'] . $local_directory; // 이미지호스팅 디렉토리(파일명 제외)
			}
			$changebasedir = $sftp->chdir($imagehostingdir); //sftp_chdir

			if ($changebasedir == false) { // 기본디렉토리 없는 경우, 생성
				foreach (explode('/', $imagehostingdir) as $idx => $path) {
					if ($idx != 0) {
						$mkdirpath .= '/';
					}
					$mkdirpath .= $path;
					$mkdirbasedir = $sftp->mkdir($mkdirpath); //sftp_mkdir;
				}

				if ($mkdirbasedir == false) {
					throw new Exception('connection error', '100');
				} else { //생성한 기본디렉토리 접속 체크
					$mkdirChangebasedir = $sftp->chdir($imagehostingdir); //sftp_chdir
					if ($mkdirChangebasedir == false) {
						throw new Exception('connection error', '100');
					}
				}
			}

			// 2. 파일 업로드
			if ($mode == 'test') {
				$local_filepath = $_SERVER['DOCUMENT_ROOT'] . '/data/tmp/firstmall_imagehosting.txt';
				$fp = fopen($local_filepath, 'w');
				fclose($fp);
				chmod($local_filepath, 0777);
				$imagehosting_filepath = $imagehostingdir . '/firstmall_imagehosting.txt';
			} elseif ($mode == 'upload') {
				$local_filepath = $_SERVER['DOCUMENT_ROOT'] . $current_image;
				$imagehosting_filepath = $full_imagehostingdir;
			}
			$imgserver = $sftp->put($imagehosting_filepath, $local_filepath, SFTP::SOURCE_LOCAL_FILE); //sftp_put
			//echo $sftp->getLog();

			if ($imgserver == false) {
				throw new Exception('uploade error', 200);
			}

			return ['code' => '000', 'message' => '성공'];
		} catch (Exception $e) {
			debug_var($e->getMessage()); // error 발생 시 상세 내역 확인을 위함
			if ($e->getCode() === 0) { // sftp library에서 Exception 되는 경우 0으로 넘어오는 경우에 대한 처리
				return ['code' => '100', 'message' => $connect_error];
			} else {
				return ['code' => $e->getCode(), 'message' => $e->getMessage()];
			}
		}
	}

	/**
	 * 저장소별 이미지 경로 치환 값 세팅
	 * @param store_url_origin, store_url_change, imagestore_seq, table_where
	 * @return array change_url_log 성공 change_url_error 오류 []
	 */
	public function changeImageurl($params = [])
	{
		// 필수값
		$store_url_origin = $params['store_url_origin'] . "/";
		$store_url_change = $params['store_url_change'] . "/";
		$imagestore_seq = $params['imagestore_seq'];
		$table_where = $params['table_where'];
		// 선택값
		$page_type = $params['page_type']; // [상품페이지] 카테고리/브랜드/지역 구분자

		try {
			// 저장소 정보 가져오기
			$where_params = ['imagestore_seq' => $imagestore_seq];
			$result_imagestore = $this->CI->imagehosting->get_imagestoreEach($where_params);

			// 저장소명 가져오기
			$imagestore_division = $this->CI->config->item('imagestore_division');
			$imagestore_item = $this->CI->config->item('imagestore_item');
			$imagestore_division_title = $imagestore_division[$result_imagestore['imagestore_division']];
			$imagestore_item_title = $imagestore_item[$result_imagestore['imagestore_item']];

			// 반응형/전용 스킨에 따른 처리
			if (in_array($result_imagestore['imagestore_item'], ['all_navigation', 'slide_banner', 'event_banner'])) {
				$result_imagestore['imagestore_item'] = $result_imagestore['imagestore_item'] . '_' . $this->CI->config_system['operation_type'];
			}

			// 기능별 저장소 기본 DB 세팅
			$imagestore_db = $this->CI->config->item('imagestore_db');
			$update_params = $imagestore_db[$result_imagestore['imagestore_item']];

			// 예외 상황에 대한 $table_where 가공
			switch($result_imagestore['imagestore_item']) {
				case 'common_info': $table_where = ($table_where) ? $this->CI->imagehosting->changeImageurl_common_info($table_where) : $table_where;

					break; //공용정보

				case 'category_banner':
				case 'category_navigation':
				case 'all_navigation_light':
				case 'all_navigation_heavy': $update_params = ($page_type) ? $this->_changeImageurl_page_type($page_type, $update_params) : $update_params;

					break; //상품페이지조회

				default: break;
			}

			$flag = 0; // update_type=editor 인 경우, db update는 1번만 실행

			// 이미지 URL 변경 실행
			foreach ($update_params as $update_param) {
				// [조건] 상품 본사/입점사
				if ($result_imagestore['imagestore_division'] == 'goods_headquaters' || $result_imagestore['imagestore_division'] == 'goods_provider') {
					$update_param['where'] = $update_param['where'][$result_imagestore['imagestore_division']];
				}
				// [조건] 기능별 업데이트 조건
				if ($table_where) {
					$where_param = $table_where;
				}
				// [조건] 기능별 기본 조건
				if ($update_param['where']) {
					$where_param .= ($table_where) ? ' and ' . $update_param['where'] : $update_param['where'];
				}
				$update_param['where'] = $where_param;

				// 로컬저장소인 경우, /로 치환 (/data/로 시작하는지로 체크)
				$update_param['select']['select_string'] = (substr($store_url_origin,0,6) == "/data/") ? "/" : $store_url_origin;
				$update_param['select']['change_string'] = (substr($store_url_change,0,6) == "/data/") ? "/" : $store_url_change;

				$result = $this->CI->imagehosting->update_table_field($update_param);
				if (!$result) {
					throw new Exception('DB UPDATE ERROR');
				}
				if ($update_param['update_type'] == 'editor') {
					$flag = 1;
				}
				unset($update_param);
			}

			if ($result) {
				// 로그 내역 저장
				$return['change_url_log'] = $imagestore_division_title . ': ' . $imagestore_item_title . '(' . $store_url_origin . ' > ' . $store_url_change . ')';
				$return['flag'] = $flag;

				return $return;
			} else {
				// 저장 오류
				throw new Exception('DB UPDATE ERROR');
			}
		} catch (Exception $e) {
			$return['change_url_error'] = $e->getMessage();

			return $return;
		}

		return $return;
	}

	/**
	 * 저장소별 이미지 경로 치환 시 에외 상황 처리:: 상품페이지조회(카테고리/브랜드/지역)
	 * $page_type: 카테고리/브랜드/지역
	 * $update_params: 상품페이지조회 config 데이터
	 * @return array $update_params: 카테고리/브랜드/지역 중 필요한 데이터만 남음
	 */
	public function _changeImageurl_page_type($page_type, $update_params)
	{
		foreach ($update_params as $update_param) {
			if ($update_param['table'] == 'fm_' . $page_type) {
				$new_params[0] = $update_param;
			}
		}

		return $new_params;
	}

	/**
	 * 한 페이지에서 이미지호스팅 업로드 기능을 여러 번 사용하는 경우, 결과 메세지 가공
	 * $result: result_imagehosting array(code, massage)
	 * @return array message, dialogwidth, dialogheight
	 */
	public function message($result)
	{
		$dialogwidth = 450;
		$dialogheight = 270;

		$imagestore_item = $this->CI->config->item('imagestore_item');

		if($result['code']){ // 배열 안들어간 경우, 배열 처리
			$object[] = $result;
		}else{
			$object = $result;
		}

		unset($object['is_upload']);
		
		$is_error = [];
		$message = $error_massage = $success_massage = '';
		foreach ($object as $idx => $params) {
			if($idx=='mobile_contents') $idx = 'contents';
			if($idx=='common_contents') $idx = 'common_info';
			$imagestore_item_title = $imagestore_item[$idx];

			if ($params[0]) { // 배열인 경우
				foreach ($params as $param) {
					if ($param['code'] != '000') {
						$is_error[] = $imagestore_item_title;
						$error_massage = $param['message'];
					} else {
						$success_massage = $param['message'];
					}
				}
			} else {
				if ($params['code'] != '000') {
					$is_error[] = $imagestore_item_title;
					$error_massage = $params['message'];
				} else {
					$success_massage = $params['message'];
				}
			}
		}

		if (!$is_error) {
			$message .= $success_massage;
		} else {
			$message .= '</br>이미지호스팅 업로드 실패: ';
			$is_error = array_unique($is_error); // 중복 제거
			foreach ($is_error as $error_idx => $error) {
				if ($error_idx != 0) {
					$message .= ', ';
				}
				$message .= $error;
			}
			$message .= $error_massage;

			if (defined('__ADMIN__') === true) {
				$dialogheight = 340;
			}
			if (defined('__SELLERADMIN__') === true) {
				$dialogheight = 300;
			}
		}

		return ['message' => $message, 'dialogwidth' => $dialogwidth, 'dialogheight' => $dialogheight];
	}
}
