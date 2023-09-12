<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');
require_once(APPPATH.'controllers/base/admin_base'.EXT);
require_once(APPPATH.'/libraries/Spout/Autoloader/autoload.php'); //excel library

use Box\Spout\Reader\ReaderFactory;
use Box\Spout\Writer\WriterFactory;
use Box\Spout\Common\Type;
use Box\Spout\Writer\Style\StyleBuilder;
use Box\Spout\Writer\Style\Color;
use Box\Spout\Writer\Style\Border;
use Box\Spout\Writer\Style\BorderBuilder;

class excel_up extends admin_base {

	protected $pid;
	protected $queueID;
	protected $fileName;
	protected $filePath;
	protected $nameDomain;
	protected $downPath;
	protected $regDate;
	protected $logPath;
	protected $logFile;
	protected $provider;
	protected $fileAllowedType;

	public $aCategory = array(
		11 => "goods",
		12 => "export",
		13 => "membersale",
	);

	public $aCategoryKR = array(
		11 => "판매상품",
		12 => "출고",
		13 => "회원등급세트",
	);

	public $alimitCount = array(
		11 => 500,
		12 => 500,
		13 => 500,
	);

	function __construct(){
		parent::__construct();
		$this->db->db_debug = false;

		$this->regDate = date("Ymd");
		$this->batchLimit = 50;

		if(!preg_match("/^F_SH_/",$this->config_system['service']['hosting_code'])){
			$this->load->model('usedmodel');
			$use_per	= $this->usedmodel->get_used_space_percent();
			if($use_per > 100){
				$popOptions = array();
				$popOptions['btn_title']	= '용량추가';
				$popOptions['btn_class']	= 'btn large cyanblue';
				$popOptions['btn_action']	= "window.open('http://firstmall.kr/myshop','_blank')";
				openDialogAlert("용량이 초과되어 상품을 등록 또는 수정할 수 없습니다.<br/>용량 추가를 하시길 바랍니다.", 400,175, 'parent', "", $popOptions);
				exit;
			}
		}

		$arrSystem			= ($this->config_system) ? $this->config_system : config_load('system');
		$arr_sub_domain		= explode(".",$arrSystem['subDomain']);
		$this->nameDomain	= sprintf("%s","{$arr_sub_domain['0']}"); //기본 도메인 셋팅
		$this->category		= end(explode("_", $this->uri->segment(3)));
		$this->categoryKey	= array_search($this->category, $this->aCategory);
		$this->categoryKR	= $this->aCategoryKR[$this->categoryKey];
		$this->limitCount	= $this->alimitCount[$this->categoryKey];

		if($this->categoryKey <= 0){
			openDialogAlert("카테고리 인덱스를 찾을 수 없습니다.", 400, 180, 'parent', "");
			exit;
		}

		$this->load->model('goodsexcel');
		$this->load->model('shippingmodel');

		if($_FILES){ //즉시 업로드 프로세스
			//입점사 체크
			if( $this->is_excel_admin == "Y" ) {
				$this->provider = 1;
			} else {
				if($this->providerInfo){
					$this->provider 				= $this->providerInfo['provider_seq'];
					$this->goodsexcel->m_sAdminType = 'S';
					if($_POST['provider_choice']){
						$this->upload_type = $_POST['provider_choice']; //입점사 상품 등록 시 N : type1, Y : type2
					}
				} else {
					openDialogAlert("요청사 정보를 확인 할 수 없습니다.", 400, 180, 'parent', "");
					exit;
				}
			}
		}
	}

	function excel_file_check($filename, $filedata, $fileexe = 'xls'){
		$this->load->library('upload');
		$fileinfo = $filedata[$filename];
		if	(is_uploaded_file($fileinfo['tmp_name'])){
			$fileName				= "upload_" . $this->category . "_excel_" . date('YmdHis') . rand(0,9999);

			$cfg					 = array();
			$cfg['allowed_types']	= $fileexe;
			$cfg['file_name']		= $fileName;
			$cfg['upload_path']	  	= ROOTPATH . "data/tmp/";
			$cfg['overwrite']		= TRUE;

			$this->upload->initialize($cfg);
			if ($this->upload->do_upload($filename)) {
				$this->fileName 		= $fileName;
				$this->filePath 		= $cfg['upload_path'] . $cfg['file_name'] . '.' . $cfg['allowed_types'];
				$this->fileAllowedType 	= $fileexe;

				@chmod($this->filePath, 0777);
			}else{
				openDialogAlert($fileexe." 파일만 가능합니다.", 400, 180, 'parent', '');
				exit;
			}
		}else{
			openDialogAlert("파일을 등록해 주세요", 400, 180, 'parent', '');
			exit;
		}
	}

	public function create_goods(){

		// 입점사 세션 있을 경우 입점사 관리자로 지정
		if($this->providerInfo){
			define('__SELLERADMIN__', true);
		}

		// 상품등록/수정 권한 체크
		$this->load->model('authmodel');
		$auth = $this->authmodel->manager_limit_act('goods_act');
		if(!$auth){
			openDialogAlert("관리자 권한이 없습니다.", 400, 180, 'parent', '');
			exit;
		}

		//특정한 사유로 동일한 파일명으로 3초이내 업로드시 제한 @2017-06-01
		$date			= date('Y-m-d H:i:s', strtotime('-3 second'));
		$secondckLog	= $this->db->query("seLECT * FROM fm_excel_upload_log WHERE upload_date > '".$date."' limit 1")->result_array();
		if	($secondckLog[0]) {
			foreach($secondckLog as $secondckLogquery => $sklog){
				$fileinfo	= $_FILES['goods_excel_file'];//첨부파일과 로그파일명 비교
				if( $sklog['upload_filename'] == $fileinfo['name'] ) {
					openDialogAlert("과도한 접속으로 인한 제한합니다.<br/>5초 뒤 다시 접속해 주세요.", 400, 180, 'parent', "");
					exit;
				}
			}
		}

		// 파일 적합성 체크
		$this->excel_file_check('goods_excel_file', $_FILES, 'xlsx');

		if (!$this->scm_cfg) {
			$this->scm_cfg	= config_load('scm');
		}


		//배송 그룹 유효 체크
		$this->shippingList = array();
		$this->shippingList = $this->shippingmodel->get_shipping_group_list_all();

		$this->goodsexcel->m_sUploadFileName	= $this->fileName;
		$this->goodsexcel->m_sUploadAllowedType	= $this->fileAllowedType;

		if($this->provider > 1){
			$this->goodsexcel->m_sUploader		= $this->providerInfo['provider_id'];
		} else {
			$this->goodsexcel->m_sUploader		= $this->managerInfo['manager_id'];
		}

		if($_POST['goods_kind'] == 'COUPON'){
			$this->goodsexcel->m_sGoodsKind = 'C';
		} else {
			$this->goodsexcel->m_sGoodsKind = 'G';
		}

		//유형별 셀 리스트 생성
		$this->goodsexcel->set_cell_list();
		$this->goodsexcel->set_multiRow_cell();

		//필수 셀 리스트 - 목차 1행으로 비교하므로, 2행 이하 실제값의 유무와는 상관 없음
		$cellMust		= array();
		$cellExcept		= array();
		foreach($this->goodsexcel->m_aCellList as $code => $title){
			if	(substr($code, 2, 1) == 'R' && $code != 'BBRN00025') { //오픈마켓 상품명은 하단에서 예외 처리 하여 제외
				$cellMust[$code] = $title;
			}

			$except = $this->goodsexcel->get_except_cell_list($code);
			if($except){
				$cellExcept[$except][$this->goodsexcel->m_aFieldInfo[$code]] = $code;
			}
		}

		if($this->provider > 1){ //입점사의 경우 입점사 고유값 체크 제외, 업로드 타입과 입점사 코드 지정
			unset($cellMust['ABRY00001']);

			$this->goodsexcel->m_sProviderChoice	= $this->upload_type;
			$this->goodsexcel->m_nProviderSeq	   = $this->provider;
		}

		$excelArr	 = array(); //전체 셀 목록
		$goodsArr	 = array(); //DB fm_goods 테이블 목록
		$exceptArr	= array(); //fm_goods 이외 예외 테이블 목록
		$searchCount  = 0;

		$reader = ReaderFactory::create(Type::XLSX);
		$reader->open($this->filePath);
		foreach ($reader->getSheetIterator() as $sheet) {
			foreach ($sheet->getRowIterator() as $num => $row) {
				if($num == 1){
					foreach($row as $key => $name) {
						if($name == "검색어추가"){ //모델 변수와 엑셀 목차명이 틀림
							$name = "검색어";
						}

						$code = array_search(str_replace("*", "", $name), $this->goodsexcel->m_aCellList);
						if($code){
							$excelArr[$code] = $this->goodsexcel->m_aFieldInfo[$code];
						} else {
							$excelArr[$key] = "NONE";
						}

						// exception 배열
						$except = $this->goodsexcel->get_except_cell_list($code);
						if($except){
							$exceptArr[$except][$key] = $this->goodsexcel->m_aFieldInfo[$code];
						} else {
							$goodsArr[$key] = $this->goodsexcel->m_aFieldInfo[$code];
						}

						if($cellMust[$code]){ //필수값 체크
							unset($cellMust[$code]);
						}
					}

					if($cellMust){
						openDialogAlert("필수 값[".join(", ", array_values($cellMust))."] 누락", 400, 180, 'parent', '');
						exit;
					}

					// 예외 처리여부 체크
					if ($exceptArr) {
						unset($cellExcept['option']['action_option_kind'], $cellExcept['option']['action_suboption_kind']); //추가/필수 옵션 동작구분 제외
						foreach ($cellExcept as $type => $cells) {
							if (is_array($cells) && count($cells) > 0) {
								foreach ($cells as $name => $code) {
									if($type == 'option' || $type == 'suboption' || $type == 'input'){
										if (count($exceptArr[$type]) > 0 && !array_search($name, $exceptArr[$type])) {
											$this->goodsexcel->save_upload_log('failed', '[' . $this->goodsexcel->get_except_code_to_name($type) . '] 필요항목(' . $this->goodsexcel->m_aCellList[$code] . ')이 부족하여 저장하지 않습니다.' . "\r\n");
										}
									}
								}
							}
						}
					}
				} else {
					if (array_filter($row)) { //서식 설정만으로도 셀 내용 인식하여 추가
						$searchCount++;

						if($searchCount > $this->limitCount){
							openDialogAlert("업로드 제한 갯수(".$this->limitCount.")를 초과했습니다.<br/>제한 갯수 이하의 엑셀 파일을 업로드 해 주세요.", 400, 180, 'parent', '');
							exit;
						}
					}

					ini_set("memory_limit",-1);
					set_time_limit(0);
				}
			}
		}
		$reader->close();

		if($searchCount <= 0){
			openDialogAlert("업로드 가능 한 ".$this->categoryKR."이 없습니다.", 400, 180, 'parent', '');
			exit;
		} else {
			$this->write_goods($excelArr, $goodsArr, $exceptArr, $searchCount);
		}
	}

	function write_goods($excelArr, $goodsArr, $exceptArr, $searchCount){
		$this->load->model('scmmodel');
		$this->load->model('goodsmodel');
		$this->load->model('goodssummarymodel');

		$cellCodes = array_keys($excelArr);

		$reader = ReaderFactory::create(Type::XLSX);
		$reader->open($this->filePath);

		$goodsArrSucc = array();
		$goodsArrFail = array();

		$goodsBatchParams		= array();
		$goodsBatchExceptParams = array();

		$goodsBatchUpdateParams	   = array();
		$goodsBatchUpdateExceptParams = array();

		$goodsArrSuccMod = array();

		$keys	   = array('0' => 'goods_name');
		$failCount  = 0;

		foreach ($reader->getSheetIterator() as $sheet) {
			foreach ($sheet->getRowIterator() as $num => $row) {
				$goodsParams	= array();
				$exceptParams	= array();
				if ($num > 1 && array_filter($row)) {
					foreach ($row as $k => $v) {
						if ($goodsArr[$k]) {
							$goodsParams[$goodsArr[$k]] = $v;
						} else {
							$exceptType = $this->goodsexcel->get_except_cell_list($cellCodes[$k]);
							if($exceptType){
								if ($exceptType == 'option'
									&& in_array($exceptArr['option'][$k], $this->goodsexcel->m_aOptionMultiRowCell)) {
										$v	= explode(chr($this->goodsexcel->m_sUploadLineCharString), $v);
									}
									$exceptParams[$exceptType][$excelArr[$cellCodes[$k]]] = $v;
							}
						}
					}

					// 최초 등록 시 옵션 관련 데이터가 없을 경우 옵션 테이블 입력 누락으로 상품 노출 안됨
					// 때문에 최초 등록일 경우 옵션 관련 데이터는 반드시 입력 하도록 함
					if ($goodsParams['goods_seq'] <= 0 && !$exceptParams['option']) {
						$failCount++;
						$this->goodsexcel->save_upload_log('failed', "[" . $goodsParams['goods_name']. '] 신규 등록 상품의 경우 옵션 관련 항목을 입력 해주세요.' . "\r\n");
						continue;
					}
					if( $this->config_system['operation_type'] == 'light' && empty(trim($goodsParams['mobile_contents'])) != true) {
						$goodsParams['contents'] = $goodsParams['mobile_contents'];
					}elseif (trim($goodsParams['mobile_contents']) == '' && $goodsParams['contents']) {
						# 모바일 상세설명 없을때 PC용상세설명으로 동일하게 입력 @2016-06-20 pjm
						$goodsParams['mobile_contents'] = $goodsParams['contents'];
					}

					//오픈마켓 상품명 없을 경우 기존 상품명으로
					if (trim($goodsParams['goods_name_linkage']) == '') {
						$goodsParams['goods_name_linkage'] = $goodsParams['goods_name'];
					}

					//데이터 기본값 치환
					foreach($goodsParams as $fld => $val){
						if($fld != 'provider_status'){
							$this->goodsexcel->get_upload_goods_change_val($fld, $goodsParams);
						}
					}

					// 승인여부에 대한 처리는 마지막에 한다.
					if($goodsParams['provider_status']){
						$this->goodsexcel->get_upload_goods_change_val('provider_status', $goodsParams);
					}

					//옵션 재고 체크
					$failFlag = true;
					if($exceptParams){
						foreach($exceptParams as $type => $fldArr){
							//올인원이면서 입점상품일때 재고관리 예외처리 @2017-04-04
							if($goodsParams['provider_id'] && $goodsParams['provider_id'] != 'base' && $type == 'warehouse' ){
								continue;
							}

							$except = 'chk_upload_exception_' . $type;
							if(method_exists($this->goodsexcel, $except)){
								$result	= $this->goodsexcel->$except($goodsParams, $exceptParams['option'], $exceptParams[$type]);

								if($result['status'] === false){
									$result['msg']	= "[" . $goodsParams['goods_name'] . "] " . $result['msg'];
									$this->goodsexcel->save_upload_log('failed', $result['msg'] . "\r\n");
									$failFlag = false;
									continue;
								}
							}

							//옵션 특수문자 제거
							if($type == 'option'){

								foreach($fldArr as $k => $v){
									if($k == 'option1' || $k == 'option2' || $k == 'option3' || $k == 'option4' || $k == 'option5'){
										foreach($v as $kk => $vv){
											$exceptParams[$type][$k][$kk] = preg_replace("/[\"\'\\\\]/i", "", $vv);
										}
									}

									if( $k == 'option_title1' || $k == 'option_title2' || $k == 'option_title3' || $k == 'option_title4' || $k == 'option_title5'){
										$exceptParams[$type][$k] = preg_replace("/[\"\'\\\\]/i", "", $v);
									}
								}
							}
						}
					}

					if($failFlag == false){
						$failCount++;
						continue;
					}

					//수정 상품에 배송 그룹이 없으면 업데이트 무시
					if($goodsParams['goods_seq'] > 0 && $goodsParams['shipping_group_seq'] <= 0){
						unset($goodsParams['shipping_group_seq']);
					} else {
						//만약 존재하지 않는 배송 그룹이라면 해당  provider 의 가장 첫 배송 그룹으로 설정
						if($goodsParams['provider_seq'] > 1){ //입점사의 경우
							//본사위탁과 입점사 번호 모두 없는 경우
							if(!$this->shippingList[$goodsParams['provider_seq']][$goodsParams['shipping_group_seq']]
								&& !$this->shippingList[1][$goodsParams['shipping_group_seq']]){
									$goodsParams['shipping_group_seq'] = key($this->shippingList[$goodsParams['provider_seq']]);
							}

							//본사위탁 경우
							if($this->shippingList[1][$goodsParams['shipping_group_seq']]){
								$goodsParams['trust_shipping'] = 'Y';
							} else {
								$goodsParams['trust_shipping'] = 'N';
							}
						} else {
							if(!$this->shippingList[$this->provider][$goodsParams['shipping_group_seq']]){
								$goodsParams['shipping_group_seq'] = key($this->shippingList[$this->provider]);
							}

							$goodsParams['trust_shipping'] = 'N';
						}
					}

					$goods_seq	= $goodsParams['goods_seq']; //입점사의 경우 해당 변수가 unset 되므로 따로 저장
					unset($goodsParams['goods_seq']);

					if($this->provider > 1){
						$manager = '입점사';

						$this->goodsexcel->m_sAdminType			= "S";
						$this->goodsexcel->set_provider_ignore();

						foreach($this->goodsexcel->m_aUpdateProviderIgnore['default'] as $fld){
							unset($goodsParams[$fld]);
						}

						$goodsParams['sale_seq']			= 1;
						if ($goods_seq > 0) {
							unset($goodsParams['sale_seq']);
						}
						
						if($this->upload_type == 'N'){
							foreach($this->goodsexcel->m_aUpdateProviderIgnore['goods'] as $fld){
								if($goods_seq > 0){ //상품 수정의 경우 type1의 무시 목록은 업데이트 항목에서 제외
									unset($goodsParams[$fld]);
								}
							}

							foreach($this->goodsexcel->m_aUpdateProviderIgnore['except'] as $fld){
								if($goods_seq > 0){ //상품 수정의 경우 type1의 무시 목록은 업데이트 항목에서 제외
									unset($exceptParams[$fld]);
								}
							}
						} else {
							$goodsParams['goods_status']					= 'unsold';
							$goodsParams['goods_view']						= 'notLook';
							$goodsParams['provider_status']					= '0';
							$goodsParams['provider_status_reason_type']		= '5';
							$goodsParams['provider_status_reason']			= '[자동] 엑셀 업로드 시 미승인 처리됨';
						}
					} else {
						$manager = '관리자';
					}

					//입점사 번호가 없을 경우 본사 상품으로
					if(!$goodsParams['provider_seq']){
						$goodsParams['provider_seq'] = $this->provider;
					}

					$callback = "parent.location.reload();";
					if ($goods_seq > 0) { //기존 상품 수정
						$goodsData = $this->goodsmodel->get_goods($goods_seq);

						if($goodsData['goods_seq'] != $goods_seq){
							openDialogAlert("상품번호/옵션번호/추가옵션번호를 임의로 등록할 수 없습니다.<br />신규 상품의 경우 상품번호/옵션번호/추가옵션번호을 제거하고 등록하여 주시기 바랍니다.", 400, 180, 'parent', $callback);
							exit;
						}

						//provider_seq 변경은 불가
						if($goodsData['provider_seq'] != $goodsParams['provider_seq']){
							openDialogAlert("입점사 변경은 불가능 합니다. 입점사 고유값 확인 후 다시 시도 해 주세요.", 400, 180, 'parent', $callback);
							exit;
						}

						$goodsArrSuccMod[] = $goods_seq;

						$goodsParams['update_date']	= date('Y-m-d H:i:s');
						$goodsParams['goods_seq']	= $goods_seq;

						$exceptParams['goods_seq']	  = $goods_seq;
						$exceptParams['goods_name']	 = $goodsParams['goods_name'];
						$exceptParams['provider_seq']   = $goodsParams['provider_seq'];

						$this->goodsexcel->chk_require_goods_update_param($goodsParams);

						$goodsBatchUpdateParams[]	   = $goodsParams;
						$goodsBatchUpdateExceptParams[] = $exceptParams;
					} else { //새 상품 입력
						$goodsParams['regist_date']	= date('Y-m-d H:i:s');
						$goods_seq					= 0;
						$goodsParams['admin_log']	= '<div>' . date('Y-m-d H:i:s') . ' ' . $manager . '가 엑셀일괄업데이트를 통해 상품정보를 등록하였습니다. (' . $_SERVER['REMOTE_ADDR'] . ')</div>';

						if($this->provider > 1){
							$goodsParams['provider_seq']	= $this->provider;
						}

						// 현재 등록된 입점사 아닌 경우에는 리턴
						$this->load->model('providermodel');
						$provider 			= $this->providermodel->get_provider($goodsParams['provider_seq']);
						if(empty($provider)){
							openDialogAlert("현재 등록되지 않은 입점사 입니다. <br/>입점사 고유값 확인 후 다시 시도 해 주세요.", 400, 180, 'parent', $callback);
							exit;
						}

						$this->goodsexcel->chk_require_goods_insert_param($goodsParams);

						//insert의 경우 모든 데이터의 key 갯수가 동일 해야 함.
						$paramsKeys	= array_keys($goodsParams);
						$keysDiff	  = array_diff($paramsKeys, $keys);
						if(count($keysDiff) > 0){
							$keys	  = array_merge($keys, $keysDiff);
						}

						//물류관리 사용 시 추가 옵션은 예외처리
						if($exceptParams['suboption']['suboption_title']
							&& $this->goodsexcel->m_sGoodsKind != 'C'
							&& !$this->m_bIsScm
							&& $this->scmmodel->scm_use_suboption_mode){
								$goodsParams['option_suboption_use'] = '1';
						}

						$goodsBatchParams[]			= $goodsParams;
						$goodsBatchExceptParams[]	= $exceptParams;
					}

					if (($num-1)%$this->batchLimit == 0 || ($num-1) == $searchCount) {

						//keys 갯수 맞추기
						foreach($goodsBatchParams as $k => $datas){
							foreach($keys as $key){
								if(is_null($datas[$key]) === true){
									$goodsBatchParams[$k][$key] = '';
								}
							}
						}

						//트랜잭션 시작
						$this->db->trans_begin();
						$this->goodsexcel->m_aChgGoodsTarget = array(); //scm에서 기초재고 등록 변수 저장

						//새상품 입력
						if (count($goodsBatchParams) > 0) {

							$insert_ids		= 0;
							$insert_ids		= $this->db->insert_batch('fm_goods', $goodsBatchParams, true, $this->limitCount); //return 값으로 인서트 갯수 리턴
							$firstId	   	= $this->db->insert_id();

							if ($firstId > 0 && $insert_ids == count($goodsBatchParams)) {
								$this->goodsexcel->m_sGoodsSaveType = 'insert';
								foreach ($goodsBatchParams as $k => $v) {
									$goodsBatchExceptParams[$k]['goods_seq']	= $firstId;
									$goodsBatchExceptParams[$k]['provider_seq'] = $v['provider_seq'];
									$goodsBatchExceptParams[$k]['goods_name']	= $v['goods_name'];
									$goodsBatchExceptParams[$k]['goods_code']	= $v['goods_code'];
									$goodsBatchExceptParams[$k]['tax']			= $v['tax'];
									$goodsBatchExceptParams[$k]['keyword']		= $v['keyword'];

									$this->goodsexcel->save_upload_log('success', '[' . $v['goods_name']. '/' . $firstId . ']의 상품 기본정보가 등록되었습니다.'."\r\n");

									$goodsArrSucc[]				= $firstId;
									$firstId++;
								}
								$this->save_goods_except_batch($goodsBatchExceptParams, 'insert');
							} else {
								foreach ($goodsBatchParams as $k => $v) {
									$this->goodsexcel->save_upload_log('failed', '[' . $v['goods_name'] . ']의 상품 기본정보가 등록되지 않았습니다.' . "\r\n");
									$goodsArrFail[] = $v['goods_name'];
								}
							}
							unset($insert_ids, $firstId);
						}


						//기존상품 수정
						if (count($goodsBatchUpdateParams) > 0) {
							$updateCount = 0;
							$this->db->update_batch('fm_goods', $goodsBatchUpdateParams, 'goods_seq', true);
							$updateCount = $this->db->affected_rows();

							if ($updateCount == count($goodsBatchUpdateParams)) {
								$this->goodsexcel->m_sGoodsSaveType = 'update';
								$this->save_goods_except_batch($goodsBatchUpdateExceptParams, 'modify');

								foreach ($goodsBatchUpdateParams as $v) {
									$goodsArrSucc[] = $v['goods_seq'];
									$this->db->query("update fm_goods set admin_log=concat(?, IFNULL(admin_log, '')) where goods_seq = ?",
										array('<div>' . date('Y-m-d H:i:s') . ' ' . $manager . '가 엑셀일괄업데이트를 통해 상품정보를 수정하였습니다. (' . $_SERVER['REMOTE_ADDR'] . ')</div>', $v['goods_seq']));

									$this->goodsexcel->save_upload_log('success', '[' . $v['goods_name']. '/' . $v['goods_seq'] . ']의 상품 기본정보가 수정되었습니다.'."\r\n");
								}
							} else {
								foreach ($goodsBatchUpdateParams as $k => $v) {
									$this->goodsexcel->save_upload_log('failed', '[' . $v['goods_name'] . '/' . $v['goods_seq'] . ']의 상품 기본정보가 수정되지 않았습니다.' . "\r\n");
									$goodsArrFail[] = $v['goods_name'];
								}
							}
							unset($updateCount);
						}

						foreach($goodsArrSucc as $seq){
							$this->goodssummarymodel->set_event_price(array('goods'=>array($seq)));
						}

						// 물류관리이며 기초조정이 들어간 경우 추가 처리
						if($this->scmmodel->chkScmConfig(true) && count($this->goodsexcel->m_aChgGoodsTarget) > 0){
							$this->scmmodel->after_save_for_default_revision($this->goodsexcel->m_aChgGoodsTarget);
						}

						if ($this->db->trans_status() === FALSE || count($goodsArrFail) > 0) {
							$this->db->trans_rollback();
							openDialogAlert("트랜잭션 에러 발생. 에러가 지속 될 경우 관리자에게 문의 하세요.", 400, 180, 'parent', '');
							exit;
						} else {
							$this->db->trans_commit();
						}

						unset($goodsBatchParams, $goodsBatchExceptParams, $goodsBatchUpdateParams, $goodsBatchUpdateExceptParams);
					}
				}
			}
		}
		$reader->close();

		//오픈마켓
		//상품 수정일때만 수집
		if (count($goodsArrSuccMod)>0) {
			$this->load->library('Connector');
			$goodsService = $this->connector::getInstance('goods');
			foreach ($goodsArrSuccMod as $goodsSeq) {
				$goodsService->doMarketGoodsUpdate($goodsSeq);	//Queue 로 처리
			}

			// 오픈마켓 상품 여부 확인
			$this->load->model('connectormodel');
			$marketParams					= array();
			$marketParams['fmGoodsSeqArr']	= $goodsArrSuccMod;
			$marketParams['manualMatched']	= 'N';
			$marketProductList	= $this->connectormodel->getMarketProductList($marketParams);
			if(count($marketProductList)>0){
				$alertText = "<br/><br/>※ 오픈마켓 수정 결과는 <a href=\"/admin/market_connector/market_product_list\" target=\"_blank\">[오픈마켓>상품관리]</a>에서 확인하시기 바랍니다.";
			}
		}

		// total재고(안전재고상태업데이트)
		// foreach($goodsArrSucc as $seq){
		// 	$this->goodsmodel->total_stock($seq);
		// }
		$this->goodsmodel->total_stock_multi($goodsArrSucc);

		$this->goodsexcel->close_upload_log();

		$succCount = 0;
		if ($goodsArrSucc) {
			$succCount = count($goodsArrSucc);
		}

		openDialogAlert("처리완료(성공:".$succCount."/실패:".$failCount.") 되었습니다.<br/>처리 로그를 확인해 주십시오.".$alertText, 400, 180, 'parent', "parent.location.reload();");
		exit;
	}

	//fm_goods 외 데이터 인서트
	function save_goods_except($goods_seq, $exceptParams, $action){
		$this->goodsexcel->m_aProviderAsGoods[$goods_seq] = $this->provider;

		$goodsUpdateParams	= array();
		foreach($exceptParams as $type => $fldArr){
			$params = array();
			$result = '';

			// 입점사 관리자 수정일 경우 or 올인원이면서 입점상품일때 재고관리 예외처리 @2017-04-04
			if( ( $action == 'update' && in_array($type, $this->goodsexcel->m_aUpdateProviderIgnore['except']) )
				|| ( $this->provider > 1 && $type == 'warehouse') ){
					continue;
			}

			foreach($fldArr as $cell => $code){
				if(is_array($code)){
					$params[$cell] = join("\n", $code);
				} else {
					$params[$cell] = $code;
				}
			}

			$except = '_upload_exception_' . $type;
			$result = $this->goodsexcel->$except($goods_seq, $params);
			if($type == 'option'){
				$set_option	= $result['status'];
			}

			// 상품정보 Update 항목
			if($result['status'] && count($result['goodsUpdateParam']) > 0){
				$goodsUpdateParams = array_merge($goodsUpdateParams, $result['goodsUpdateParam']);
			}

			if	(!$result['status']){
				$this->goodsexcel->save_upload_log('failed', '[' . $goods_seq . '] ' . $result['msg'] . "\r\n");
			}
		}

		// 필수옵션이 없는 경우 ( 기본 필수옵션으로 넣을때는 판매중지, 미노출로 처리 )
		if($action == 'insert' && !$set_option){
			$this->goodsexcel->insert_default_option($goods_seq);
			$goodsUpdateParams['reserve_policy']	= 'shop';
			$goodsUpdateParams['goods_status']		= 'unsold';
			$goodsUpdateParams['goods_view']		= 'notLook';
		}

		// 예외처리에 따른 상품 기본정보 Update
		if(count($goodsUpdateParams) > 0){
			$this->db->where(array('goods_seq' => $goods_seq));
			$this->db->update('fm_goods', $goodsUpdateParams);
		}
	}

	function save_goods_except_batch($batchParams, $action) {
		$goodsUpdateParams	= array();
		$exceptionArr		= array_keys($batchParams[0]);

		foreach ($batchParams as $exceptParams) {
			$goods_seq									= $exceptParams['goods_seq'];
			$goodsUpdateParams[$goods_seq]['goods_seq'] = $goods_seq;
			$this->goodsexcel->m_aProviderAsGoods[$goods_seq] = $exceptParams['provider_seq'];

			foreach($exceptParams as $type => $fldArr){
				foreach($fldArr as $cell => $code){
					if(is_array($code)){
						$$type[$goods_seq][$cell] = join("\n", $code);
					} else {
						$$type[$goods_seq][$cell] = $code;
					}

					if($type == 'warehouse'){
						$$type[$goods_seq]['goods_name']	= $exceptParams['goods_name'];
						$$type[$goods_seq]['tax']		   = $exceptParams['tax'];
						$$type[$goods_seq]['goods_code']	= $exceptParams['goods_code'];
					}
				}

				if($type == 'option'){
					$$type[$goods_seq]['provider_seq']	= $exceptParams['provider_seq'];
					$$type[$goods_seq]['goods_name']	= $exceptParams['goods_name'];

					if (!$option[$goods_seq]) { //필수옵션 없는 경우 기본 옵션값
						$this->goodsexcel->insert_default_option($goods_seq);
						$goodsUpdateParams[$goods_seq]['reserve_policy']	= 'shop';
						$goodsUpdateParams[$goods_seq]['goods_status']		= 'unsold';
						$goodsUpdateParams[$goods_seq]['goods_view']		= 'notLook';
					}
				}

				if($type == 'keyword'){
					### INSERT KEYWORD DEFAULT 2020-01-14 by hyem
					$goodsUpdateParams[$goods_seq]['keyword'] = $fldArr == "" ? $exceptParams['goods_seq'] : $exceptParams['goods_seq'].",".$fldArr;
				}
			}
		}

		//유효 function 리스트
		/*
		 _upload_exception_category
		 _upload_exception_brand
		 _upload_exception_location
		 _upload_exception_addition
		 _upload_exception_icon
		 _upload_exception_input
		 _upload_exception_option
		 _upload_exception_image
		 _upload_exception_suboption
		 _upload_exception_relation
		 _upload_exception_relation_seller
		 _upload_exception_warehouse
		 */

		$funcList = array('category', 'brand', 'location', 'addition', 'icon', 'input', 'option', 'image', 'suboption', 'relation', 'relation_seller', 'warehouse');

		foreach ($funcList as $type) {
			if (in_array($type, $exceptionArr)) {
				if (($type == 'warehouse' && $this->goodsexcel->m_bIsScm) || $type != 'warehouse') {

					if ( $action == "insert"
						&& (($type == 'option' && $option[$goods_seq]['option_seq'] > 0)
							|| ($type == 'suboption' && $suboption[$goods_seq]['suboption_seq'] > 0)) ) {
								unset($option[$goods_seq]['option_seq'], $suboption[$goods_seq]['suboption_seq']);
							}
							$except = '_upload_exception_' . $type . '_batch';
							$result = $this->goodsexcel->$except($$type);
				}

				foreach ($result as $goods_seq => $res) {
					if ($res['status'] === true) {
						if ($res['update']) {
							$goodsUpdateParams[$goods_seq] = array_merge($goodsUpdateParams[$goods_seq], $res['update']);
						}
					}

					if ($res['status'] === false) {
						$this->goodsexcel->save_upload_log('failed', '[' . $goods_seq . '] ' . $res['msg'] . "\r\n");
					}
				}
			}
		}

		foreach ($goodsUpdateParams as $v) {
			if (count($v) > 2) {
				// 예외처리에 따른 상품 기본정보 Update
				if (count($goodsUpdateParams) > 0) {
					$this->db->update_batch('fm_goods', $goodsUpdateParams, 'goods_seq', true);
				}
			}
		}
	}

	public function create_export(){
		$this->load->model('authmodel');
		$auth = $this->authmodel->manager_limit_act('order_goods_export');
		if(!$auth){
			openDialogAlert("관리자 권한이 없습니다.", 400, 180, 'parent', '');
			exit;
		}

		// 파일 적합성 체크
		$this->excel_file_check('excel_file', $_FILES, 'xlsx');

		if (!$this->scm_cfg) {
			$this->scm_cfg	= config_load('scm');
		}

		$criteria		= "ORDER";
		$searchCount	= 0;
		$cellInfo		= array(); //주문관련 전체 셀 목록
		$cellArr		= array(); //엑셀 목록 리스트

		$reader = ReaderFactory::create(Type::XLSX);
		$reader->open($this->filePath);
		foreach ($reader->getSheetIterator() as $sheet) {
			foreach ($sheet->getRowIterator() as $num => $row) {
				if($num == 1){
					if(array_search("*출고상품번호", $row) !== false || array_search("출고상품번호", $row) !== false){
						$criteria = "ITEM";
					}

					$this->load->model('excelmodel');
					$this->excelmodel->setting_type = $criteria;
					$this->excelmodel->set_cell();

					foreach($this->excelmodel->all_cells as $cell){

						$cellInfo[$cell[1]] = $cell[0];

						if($cell[4]){ //옵션 관련 데이터
							foreach($cell[4] as $opCellK => $opCellV){
								$cellInfo[$opCellV[1]] = $opCellV[0];
							}
						}
					}

					foreach($row as $k => $v){
						$title = str_replace('*', '', $v);
						$title = trim($title);
						$cellArr[$k] = array_search($title, $cellInfo);
					}
				} else {
					$searchCount++;

					if($searchCount > $this->limitCount){
						openDialogAlert("업로드 제한 갯수(".$this->limitCount.")를 초과했습니다.<br/>제한 갯수 이하의 엑셀 파일을 업로드 해 주세요.", 400, 180, 'parent', '');
						exit;
					}
				}
			}
		}

		if($searchCount <= 0){
			openDialogAlert("업로드 가능 한 ".$this->categoryKR."이 없습니다.", 400, 180, 'parent', '');
			exit;
		} else {
			$this->write_export($cellArr);
		}
	}

	function write_export($cellArr){
		$this->load->model('order2exportmodel');
		$this->load->model('goodsmodel');
		$this->load->model('scmmodel');
		$this->load->model('orderpackagemodel');
		$this->load->model('exportlogmodel');

		$npay_use = npay_useck();	//Npay v2.1 사용여부

		$exportArr	= array();
		$funcName	= "get_excel_data_".strtolower($this->excelmodel->setting_type);
		$exportArr	= $this->{$funcName}($cellArr);

		// 예상 되는 error 체크
		if($_POST['isCheck'] === "Y"){
			$expectArr = array();
			$errorArr = array();

			foreach($exportArr as $shipping_seq => $data) {
				if($shipping_seq_old != $shipping_seq){
					$shipping = $this->order2exportmodel->get_excel(array('shipping_seq'=>$shipping_seq, 'excel_upload' => true));

					if($shipping['ordershipping'][0]['shipping_method'] == 'coupon'){
						$goods_kind = 'coupon';
						$resChk = $this->get_export_data_coupon($shipping_seq, $shipping, $data);
					} else {
						$goods_kind = 'goods';
						$resChk = $this->get_export_data($shipping_seq, $shipping, $data);
					}

					if($resChk['error']){ //에러 예상되는 주문
						$errorArr[] = $resChk['error'][0];

						if( $goods_kind == 'goods' ){
							$stockable	= $_POST['stockable'];
						}else{
							$stockable	= $_POST['ticket_stockable'];
						}
						$this->exportlogmodel->export_log($stockable, $resChk['error'][0]['step'], 'excel_goods', $goods_kind, $resChk['error'][0]);
					} else if($resChk['info']){ //성공 예상되는 주문
						$expectArr[] = $resChk['info'];
					} else { //알 수 없는 에러
						$errorArr[] = "알 수 없는 에러";
					}
				}

				$shipping_seq_old = $shipping_seq;
			}

			$export_success = array();
			$export_error = array();

			foreach($expectArr as $info){
				if( $info['total_ea'] > 0 ){
					$export_success[$info['step']][$info['shipping_seq']] = true;
				}
			}

			foreach($errorArr as $error){
				$export_error[$error['step']][$error['export_item_seq']] = $error['order_seq']." : ".$error['msg'];
				$export_error_msg[$error['step']][]						 = $error['msg'];
			}

			//출고 실패사유 노출
			if($export_error_msg[45]){
				$err_msg_45 = $export_error_msg[45][0];
				if(count($export_error_msg[45])>1){
					$err_msg_45 .= " 외 ".(count($export_error_msg[45])-1)."건";
				}
			}

			if($export_error_msg[55]){
				$err_msg_55 = $export_error_msg[55][0];
				if(count($export_error_msg[55])>1){
					$err_msg_55 .= " 외 ".(count($export_error_msg[55])-1)."건";
				}
			}

			//번들 배송인 경우 1건으로 처리
			if($_POST['bundle_mode'] == 'bundle'){
				$arrayKeys = array_keys($export_success['55']);
				if($arrayKeys > 1){
					$tmp_array	= $export_success['55'][$arrayKeys[0]];
					unset($export_success['55']);
					$export_success['55'][$arrayKeys[0]] = $tmp_array;
				}

				$arrayKeys = array_keys($export_success['45']);
				if($arrayKeys > 1){
					$tmp_array	= $export_success['45'][$arrayKeys[0]];
					unset($export_success['45']);
					$export_success['45'][$arrayKeys[0]] = $tmp_array;
				}

				$arrayKeys = array_keys($export_error['55']);
				if($arrayKeys > 1){
					$tmp_array	= $export_error['55'][$arrayKeys[0]];
					unset($export_error['55']);
					$export_error['55'][$arrayKeys[0]] = $tmp_array;
				}

				$arrayKeys = array_keys($export_error['45']);
				if($arrayKeys > 1){
					$tmp_array	= $export_error['45'][$arrayKeys[0]];
					unset($export_error['45']);
					$export_error['45'][$arrayKeys[0]] = $tmp_array;
				}
			}

			$msg = "<span class=\'fx12 left \'><div class=\'ml25\'><strong>예상 처리 결과는 아래와 같습니다.</strong></div>";
			$msg .= "<div class=\'left mt10 ml25\'>▶ 출고준비 ".number_format(count($export_success['45']) + count($export_error['45']))."건 요청 → 성공 ".number_format(count($export_success['45']))."건";
			$msg .= " , 실패".number_format(count($export_error['45']))."건 예상</div>";

			//출고 실패사유 노출
			if($export_error_msg[45]){
				$msg .= "<div class=\'left ml30\'><span class=\'red\'>┖ 실패사유 : ".$err_msg_45."</span></div>";
			}

			$msg .= "<div class=\'left ml25 mt5\'>▶ 출고완료  ".number_format(count($export_success['55'])+count($export_error['55']))."건 요청 → 성공 ".number_format(count($export_success['55']))."건";
			$msg .= " , 실패".number_format(count($export_error['55']))."건 예상</div>";

			if	($this->scm_cfg['use'] == 'Y'){
				$msg .= "※출고완료 시 {$this->scm_cfg['use_warehouse'][$export_param['scm_wh']]}의 재고가 차감됩니다.";
			}
			$msg .= "<br/>";

			//출고 실패사유 노출
			if($export_error_msg[55]){
				$msg .= "<div class=\'left ml30\'><span class=\'red\'>┖ 실패사유 : ".$err_msg_55."</span></div>";
			}
			$msg .= "</span><br/>";

			echo("<script>
					parent.loadingStop();
					var params = {'yesMsg':'[예] 출고처리 실행','noMsg':'[아니오] 출고처리 취소'}
					parent.openDialogConfirm('".nl2br($msg)."',500,200,function(){
						parent.upload_excel_new();
					},function(){},params);
				</script>");
			exit;
		} else { //실제 출고 프로세스
			$cfg						= array();
			$cfg['scm_use']				= $this->scm_cfg['use'];
			$cfg['scm_wh'] 				= $_POST['export_warehouse'];
			$cfg['wh_seq'] 				= $_POST['export_warehouse'];
			$cfg['stockable'] 			= $_POST['stockable'];
			$cfg['step'] 				= $_POST['export_step'];
			$cfg['ticket_stockable'] 	= $_POST['ticket_stockable'];
			$cfg['ticket_step'] 		= $_POST['ticket_step'];
			$cfg['export_date'] 		= $_POST['export_date'];
			$cfg['bundle_mode'] 		= ($_POST['bundle_mode'] == 'bundle') ? 'bundle' : '';

			$export_error = array(); //에러 관련 데이터
			foreach($exportArr as $shipping_seq => $data) {
				$resChk = array();
				$shipping = $this->order2exportmodel->get_excel(array('shipping_seq'=>$shipping_seq, 'excel_upload' => true));

				if($shipping['ordershipping'][0]['shipping_method'] == 'coupon'){
					$resChk = $this->get_export_data_coupon($shipping_seq, $shipping, $data);
				} else {
					$resChk = $this->get_export_data($shipping_seq, $shipping, $data);
				}

				if($resChk['info']){ //성공 데이터 실제 출고
					$export_params = $this->order2exportmodel->set_export_params($resChk, $shipping['ordershipping'][0], $cfg); //export 데이터 셋팅

					if($resChk['shipping_method'] == 'coupon'){
						$res['coupon'] = $this->order2exportmodel->export_for_coupon(array($export_params), $cfg);
					} else {
						$res['goods'] = $this->order2exportmodel->export_for_goods(array($export_params), $cfg);
					}

					foreach($res as $goods_kind => $result_export){
						foreach($result_export as $export_status => $result_export2){
							foreach($result_export2 as $export_item_seq => $result_export3){
								$result_export4 = explode('<br/>',$result_export3['export_code']);
								foreach( $result_export4 as $explode_code ){
									if($explode_code == "ERROR"){
										$export_error[$export_status][$export_item_seq]					= $result_export3['message'];
									} else {
										$arr_explode_code[$goods_kind][$export_status][ $explode_code ] = $explode_code;
										$arr_explode_code_all[$explode_code]							= $explode_code;
									}
								}
							}
						}
					}
				} else if($resChk['error']){ //에러 데이터 메세지 출력
					$export_error[$resChk['error'][0]['step']][$resChk['error'][0]['export_item_seq']]
					= $resChk['error'][0]['order_seq'] . " : " . $resChk['error'][0]['msg'];
				} else { //알 수 없는 에러
					$export_error[0][$ship['ordershipping'][0]['shipping_seq']]
					= $ship['ordershipping'][0]['order_seq'] . " : 알 수 없는 에러";
				}
			}

			$cnt_export_result_goods_45		= (int) count($arr_explode_code['goods']['45']);	 // 실물 출고준비 갯수
			$cnt_export_result_goods_55		= (int) count($arr_explode_code['goods']['55']);	 // 실물 출고완료 갯수
			$cnt_export_result_coupon_45	= (int) count($arr_explode_code['coupon']['45']);	 // 쿠폰 출고준비 갯수
			$cnt_export_result_coupon_55	= (int) count($arr_explode_code['coupon']['55']);	 // 쿠폰 출고완료 갯수

			//출고 준비
			$cnt_export_request_45			= (int) count($export_error['45'])
			+ $cnt_export_result_coupon_45
			+ $cnt_export_result_goods_45;
			$cnt_export_request_45_succ		= $cnt_export_result_coupon_45 + $cnt_export_result_goods_45;

			//출고 완료
			$cnt_export_request_55			= (int) count($export_error['55'])
			+ $cnt_export_result_coupon_55
			+ $cnt_export_result_goods_55;
			$cnt_export_request_55_succ		= $cnt_export_result_coupon_55 + $cnt_export_result_goods_55;

			//에러 갯수
			$cnt_export_error_45			= (int) count($export_error['45']);
			$cnt_export_error_55			= (int) count($export_error['55']);

			if(count($export_error['45']) > 0){
				$export_error_msg = implode("<br />",$export_error['45']);
			}
			if(count($export_error['55']) > 0){
				$export_error_msg = implode("<br />",$export_error['55']);
			}

			$msg = "처리 결과는 아래와 같습니다.";
			$msg .= "<br/>출고준비 ".number_format($cnt_export_request_45)."건 요청 → 성공 ".number_format($cnt_export_request_45_succ)."건";
			$msg .= " ,실패".number_format($cnt_export_error_45)."건";
			$msg .= "<br/>출고완료 ".number_format($cnt_export_request_55)."건 요청 → 성공 ".number_format($cnt_export_request_55_succ)."건";
			$msg .= " ,실패".number_format($cnt_export_error_55)."건";

			$result_obj = "";
			$result_obj = "{";
			$result_obj .= "'cnt_export_request_45':".$cnt_export_request_45;
			$result_obj .= ",'cnt_export_result_goods_45':".$cnt_export_result_goods_45;
			$result_obj .= ",'cnt_export_result_coupon_45':".$cnt_export_result_coupon_45;
			$result_obj .= ",'cnt_export_error_45':".$cnt_export_error_45;
			$result_obj .= ",'cnt_export_request_55':".$cnt_export_request_55;
			$result_obj .= ",'cnt_export_result_goods_55':".$cnt_export_result_goods_55;
			$result_obj .= ",'cnt_export_result_coupon_55':".$cnt_export_result_coupon_55;
			$result_obj .= ",'cnt_export_error_55':".$cnt_export_error_55;
			$result_obj .= ",'exist_invoice':".$this->order2exportmodel->exist_invoice;
			$result_obj .= ",'export_result_error_msg':'".urlencode($export_error_msg)."'";
			$result_obj .= "}";

			if($arr_explode_code_all){
				$str_goods_export_code = implode('|',$arr_explode_code_all); // 실물출고코드합치기
			}

			if($cnt_export_result_goods_45 >0){
				// 출고준비->출고완료 출고 상태 변경 창로드
				$callback = "parent.batch_status_popup(45,'".$str_goods_export_code."',".$cnt_export_result_coupon_55.",".$result_obj.",'".$cfg['bundle_mode']."');";
			}else{
				// 인쇄용창 로드
				$callback = "parent.batch_status_popup(55,'".$str_goods_export_code."',".$cnt_export_result_coupon_55.",".$result_obj.",'".$cfg['bundle_mode']."');";
			}

			// 물류관리 매장 재고 전송
			if($this->scm_cfg['use'] == 'Y'){
				if($this->scmmodel->tmp_scm['wh_seq'] > 0){
					$sendResult = $this->scmmodel->change_store_stock($this->scmmodel->tmp_scm['goods'], array($this->scmmodel->tmp_scm['wh_seq']), '');
				}
			}

			if	(!$sendResult['status']){
				if($cfg['bundle_mode'] == 'bundle'){
					echo "<script>".$callback."</script>";
				}else{
					echo "<script>".$callback."parent.window.opener.location.reload();</script>";
				}
			}
		}

		exit;
	}

	function get_excel_data_order($cellArr){
		$exportArr = array();

		$reader = ReaderFactory::create(Type::XLSX);
		$reader->open($this->filePath);
		foreach ($reader->getSheetIterator() as $sheet) {
			foreach ($sheet->getRowIterator() as $num => $row) {
				$shipping_seq		= array();
				$delivery_company	= array();
				$delivery_number	= array();
				$rowData			= array();

				if($num > 1){
					$rowData = array_combine($cellArr, $row);

					//줄바꿈 데이터 배열로 저장
					$shipping_seq		= array_unique(explode(chr(10), $rowData['shipping_seq']));
					$delivery_company	= explode(chr(10), $rowData['delivery_company']);
					foreach($delivery_company as $k => $v){
						if( preg_match('/상동/', $v) || preg_match('/묶음/', $v) ){
							unset($delivery_company[$k]);
						}
					}
					$delivery_number	= explode(chr(10), $rowData['delivery_number']);
					foreach($delivery_number as $k => $v){
						if( preg_match('/상동/', $v) || preg_match('/묶음/', $v) ){
							unset($delivery_number[$k]);
						}
					}

					if( count($shipping_seq) !== count($delivery_company) ) {
						openDialogAlert("배송업체 오류: 배송업체와 출고그룹 갯수를 확인 해 주세요.", 400, 180, 'parent', '');
						exit;
					}

					foreach($shipping_seq as $k => $seq){
						$exportArr[$seq]['delivery_company']	= $delivery_company[$k];
						$exportArr[$seq]['delivery_number']		= $delivery_number[$k];
					}
				}
			}
		}

		return $exportArr;
	}

	function get_excel_data_item($cellArr){
		$exportArr = array();

		$reader = ReaderFactory::create(Type::XLSX);
		$reader->open($this->filePath);
		foreach ($reader->getSheetIterator() as $sheet) {
			foreach ($sheet->getRowIterator() as $num => $row) {
				$rowData			= array();

				if($num > 1){
					$rowData = array_combine($cellArr, $row);

					if($rowData['request_ea'] <= 0){
						continue;
					}

					$export_seq = explode("-", $rowData['export_item_seq']);
					$export_seq	= $export_seq[1];

					$exportArr[$export_seq][$rowData['export_item_seq']]['delivery_company']	= $rowData['delivery_company'];
					$exportArr[$export_seq][$rowData['export_item_seq']]['delivery_number']		= $rowData['delivery_number'];
					$exportArr[$export_seq][$rowData['export_item_seq']]['request_ea']			= $rowData['request_ea'];

					$export_seq_old	= $export_seq;
				}
			}
		}

		return $exportArr;
	}

	function get_export_data($shipping_seq, $shipping, $data){
		$excelData	= array();
		$scmData	= array();

		$npay_use = npay_useck();	//Npay v2.1 사용여부

		foreach($shipping['ordershipping'] as $ship) {
			$total_ea = 0;

			$excelData['order_seq'] = $ship['order_seq'];

			if($shipping_seq == $ship['shipping_seq']) {
				$totalCnt = 0;

				foreach($ship['options'] as $opK => $opV) {
					$optionInfo = '';

					//주문상품정보
					if( $ship['items'][$opV['item_seq']] ) {
						$item = $ship['items'][$opV['item_seq']];

						$excelData['OPT'][$opK]['goods_name'] = $item['goods_name'];
						$excelData['OPT'][$opK]['goods_kind'] = $item['goods_kind'];
					} else {
						$excelData['error'][] = array(
							'msg'				=>'상품 정보 없음',
							'order_seq'			=> $ship['order_seq'],
							'shipping_seq'		=> $shipping_seq,
							'export_item_seq'	=> $subopV['export_item_seq'],
							'step'				=> $_POST['export_step']
						);
						return $excelData;
					}

					//보낼 수량
					if($this->excelmodel->setting_type == "ITEM"){
						$excelData['OPT'][$opK]['request_ea'] = $data[$opV['export_item_seq']]['request_ea'];
					} else { //상품별이면 엑셀 데이터로 수량 셋팅 - 부분출고를 위해
						$excelData['OPT'][$opK]['request_ea'] = $opV['request_ea'];
					}

					if($excelData['OPT'][$opK]['request_ea'] > $opV['request_ea']){
						$excelData['error'][] = array(
							'msg'				=> '출고할 수량 오류(출고할 수량 : '.$opV['request_ea'].'개)',
							'order_seq'			=> $ship['order_seq'],
							'shipping_seq'		=> $shipping_seq,
							'export_item_seq'	=> $opV['export_item_seq'],
							'step'				=> $_POST['export_step']
						);
						return $excelData;
					}

					//재고 데이터
					$optionInfo = $item['goods_seq'] . 'option'. $opV['option_seq'];
					if( $this->scm_cfg['use'] == 'Y' && $_POST['export_warehouse'] > 0 && $opV['stock'] !== "미매칭" ){
						if( !$scmData[$optionInfo] ){
							$scmData[$optionInfo] = $this->scmmodel->get_warehouse_stock($_POST['export_warehouse'], 'optioninfo', '', array($optionInfo));
							$scmData[$optionInfo] = $scmData[$optionInfo][$optionInfo];
						}

						$opV['whSeq']			= $scmData[$optionInfo]['wh_seq'];
						$opV['stock']			= $scmData[$optionInfo]['ea'];
						$opV['supply_price']	= $scmData[$optionInfo]['supply_price'];
						$opV['whAuto']			= $scmData[$optionInfo]['auto_warehousing']; //autoWh: 무재고 자동입고 처리
					}

					$excelData['OPT'][$opK]['optioninfo']				= $optionInfo;
					$excelData['OPT'][$opK]['export_item_seq']			= $opV['export_item_seq'];
					$excelData['OPT'][$opK]['title1']					= $opV['title1'];
					$excelData['OPT'][$opK]['option1']					= $opV['option1'];
					$excelData['OPT'][$opK]['title2']					= $opV['title2'];
					$excelData['OPT'][$opK]['option2']					= $opV['option2'];
					$excelData['OPT'][$opK]['title3']					= $opV['title3'];
					$excelData['OPT'][$opK]['option3']					= $opV['option3'];
					$excelData['OPT'][$opK]['title4']					= $opV['title4'];
					$excelData['OPT'][$opK]['option4']					= $opV['option4'];
					$excelData['OPT'][$opK]['title5']					= $opV['title5'];
					$excelData['OPT'][$opK]['option5']					= $opV['option5'];
					$excelData['OPT'][$opK]['npay_product_order_id']	= $opV['npay_product_order_id'];
					$excelData['OPT'][$opK]['npay_pay_delivery']		= $opV['npay_pay_delivery'];
					$excelData['OPT'][$opK]['ea']						= $opV['ea'];
					$excelData['OPT'][$opK]['stock']					= $opV['stock'];
					//$excelData['OPT'][$opK]['whStock']					= $opV['whStock']; // fm_scm_location_link
					$excelData['OPT'][$opK]['whSeq']					= $opV['whSeq'];
					$excelData['OPT'][$opK]['whAuto']					= $opV['whAuto'];
					$excelData['OPT'][$opK]['supplyprice']				= $opV['supply_price'];
					$excelData['OPT'][$opK]['refund_ea']				= $opV['refund_ea'];
					$excelData['OPT'][$opK]['package_yn']				= $opV['package_yn'];
					$excelData['OPT'][$opK]['order_seq']				= $opV['order_seq'];
					$excelData['OPT'][$opK]['goodscode']				= $opV['goods_code'];

					//패키지 상품일 경우
					if($opV['package_yn'] == 'y') {
						$chk_pkg_stock	= true;
						$pkg_order_ea	= 0;

						$packages = $this->orderpackagemodel->get_option($opV['item_option_seq']);

						foreach($packages as $key => $data_package) {
							$optionStr	= $data_package['goods_seq'] . 'option' . $data_package['option_seq'];

							//재고 체크
							if($this->scm_cfg['use'] == 'Y' && $opV['provider_seq'] == 1){
								if($data_package['scm_auto_warehousing']){ //무재고 자동 입고 : 요청 갯수만큼 재고가 있다고 본다
									$scmData[$optionStr]['stock'] = $excelData['OPT'][$opK]['request_ea'] * $data_package['unit_ea'];
								} else {
									if( !$scmData[$optionStr] ){
										$scmData[$optionStr] = $this->scmmodel->get_warehouse_stock($_POST['export_warehouse'], 'optioninfo', '', array($optionStr));
										$scmData[$optionStr] = $scmData[$optionStr][$optionStr];
									}

									$scmData[$optionStr]['stock'] = $scmData[$optionStr]['ea'];
								}
							} else {
								$scmData[$optionStr]['stock'] = $data_package['stock'];
							}

							$pkg_order_ea = $excelData['OPT'][$opK]['request_ea'] * $data_package['unit_ea'];  //요청 갯수

							if($pkg_order_ea > $scmData[$optionStr]['stock']){
								$chk_pkg_stock	= false;
								continue;
							}
						}

						if(!$chk_pkg_stock && $_POST['stockable'] == 'limit' ){
							$excelData['error'][] = array(
								'msg'				=> '재고 부족 (패키지)',
								'order_seq'			=> $ship['order_seq'],
								'shipping_seq'		=> $shipping_seq,
								'export_item_seq'	=> $opV['export_item_seq'],
								'step'				=> $_POST['export_step']
							);
							return $excelData;
						}
					} else {
						if($this->scm_cfg['use'] == 'Y' && $opV['provider_seq'] == 1 ){ //본사상품만 창고체크 @2017-04-24
							if( !$opV['whAuto'] ){
								if( $opV['request_ea'] > 0 && $opV['whSeq'] <= 0){ // 보내는수량 있는 상품만 체크 @2017-09-21
									$excelData['error'][] = array(
										'msg'				=> '출고창고 정보없음',
										'order_seq'			=> $ship['order_seq'],
										'shipping_seq'		=> $shipping_seq,
										'export_item_seq'	=> $opV['export_item_seq'],
										'step'				=> $_POST['export_step']
									);
									return $excelData;
								}
							}
						}

						//출고되는 실물상품은 '부분출고완료'이상에서만 재고체크 @2016-05-09
						if( $opV['stock'] !== '미매칭'
							&& $opV['stock'] < $excelData['OPT'][$opK]['request_ea']
							&& $_POST['export_step'] >= 50
							&& $_POST['stockable'] == 'limit' ){
								$excelData['error'][] = array(
									'msg'				=>'재고 부족',
									'order_seq'			=> $ship['order_seq'],
									'shipping_seq'		=> $shipping_seq,
									'export_item_seq'	=> $opV['export_item_seq'],
									'step'				=> $_POST['export_step']
								);
								return $excelData;
						}
					}

					//추가옵션 정보
					if( $opV['suboptions'] ) {
						foreach($opV['suboptions'] as $subopK => $subopV) {
							$suboptionInf = '';

							//보낼 수량
							if($this->excelmodel->setting_type == "ITEM"){
								$excelData['OPT'][$opK]['SUB'][$subopK]['request_ea'] = $data[$subopV['export_item_seq']]['request_ea'];
							} else { //상품별이면 엑셀 데이터로 출고
								$excelData['OPT'][$opK]['SUB'][$subopK]['request_ea'] = $subopV['request_ea'];
							}

							if($excelData['OPT'][$opK]['SUB'][$subopK]['request_ea'] > $subopV['request_ea']){
								$excelData['error'][] = array(
									'msg'				=> '출고할 수량 오류(추가옵션 출고할 수량 : '.$subopV['request_ea'].'개)',
									'order_seq'			=> $ship['order_seq'],
									'shipping_seq'		=> $shipping_seq,
									'export_item_seq'	=> $opV['export_item_seq'],
									'step'				=> $_POST['export_step']
								);
								return $excelData;
							}

							if( $excelData['OPT'][$opK]['SUB'][$subopK]['request_ea'] <= 0 ) continue;

							$suboptionInfo = $item['goods_seq'] . 'option'. $subopV['suboption_seq'];
							if( $this->scm_cfg['use'] == 'Y' && $_POST['export_warehouse'] > 0 && $subopV['stock'] !== "미매칭" ){
								if( !$scmData[$suboptionInfo] ){
									$scmData[$suboptionInfo] = $this->scmmodel->get_warehouse_stock($_POST['export_warehouse'], 'optioninfo', '', array($suboptionInfo));
									$scmData[$suboptionInfo] = $scmData[$suboptionInfo][$suboptionInfo];
								}

								$subopV['whSeq']			= $scmData[$suboptionInfo]['wh_seq'];
								$subopV['stock']			= $scmData[$suboptionInfo]['ea'];
								$subopV['supply_price']		= $scmData[$suboptionInfo]['supply_price'];
								$subopV['whAuto']			= $scmData[$suboptionInfo]['auto_warehousing']; //autoWh: 무재고 자동입고 처리
							}

							$excelData['OPT'][$opK]['SUB'][$subopK]['optioninfo']	= $suboptionInfo;
							$excelData['OPT'][$opK]['SUB'][$subopK]['export_item_seq']	= $subopV['export_item_seq'];
							$excelData['OPT'][$opK]['SUB'][$subopK]['title']		= $subopV['title'];
							$excelData['OPT'][$opK]['SUB'][$subopK]['suboption']	= $subopV['suboption'];
							$excelData['OPT'][$opK]['SUB'][$subopK]['ea']			= $subopV['ea'];
							$excelData['OPT'][$opK]['SUB'][$subopK]['stock']		= $subopV['stock']; // fm_goods_supply
							//$excelData['OPT'][$opK]['SUB'][$subopK]['whStock']		= $subopV['whStock']; // fm_scm_location_link
							$excelData['OPT'][$opK]['SUB'][$subopK]['whSeq']		= $subopV['whSeq'];
							$excelData['OPT'][$opK]['SUB'][$subopK]['whAuto']		= $subopV['whAuto'];
							$excelData['OPT'][$opK]['SUB'][$subopK]['supplyprice']	= $subopV['supply_price'];
							$excelData['OPT'][$opK]['SUB'][$subopK]['package_yn']	= $subopV['package_yn'];
							$excelData['OPT'][$opK]['SUB'][$subopK]['refund_ea']	= $subopV['refund_ea'];
							$excelData['OPT'][$opK]['SUB'][$subopK]['export_ea']	= $subopV['export_ea'];
							$excelData['OPT'][$opK]['SUB'][$subopK]['goodscode']	= $subopV['goods_code'];

							# 네이버페이 판매자센터 출고진행건 체크
							if($subopV['request_ea'] > 0 && $subopV['npay_pay_delivery'] == 'y'){
								$excelData['error'][] = array(
									'msg'				=> '['.$ship['order_seq'].'] - 출고처리불가(네이버 페이 판매자센터에서 출고진행중인 주문입니다.)',
									'order_seq'			=> $ship['order_seq'],
									'shipping_seq'		=> $shipping_seq,
									'export_item_seq'	=> $opV['export_item_seq'],
									'step'				=> $_POST['export_step']
								);
								return $excelData;
							}

							// 서브옵션 연결 상품
							if($subopV['package_yn'] == 'y'){
								$chk_pkg_stock	= true;
								$pkg_order_ea	= 0;

								foreach($subopV['packages'] as $key => $data_package) {
									$optionStr	= $data_package['goods_seq'] . 'option' . $data_package['option_seq'];

									//재고체크
									if($this->scm_cfg['use'] == 'Y' && $opV['provider_seq'] == 1 ) {
										if( !$scmData[$optionStr] ){
											$scmData[$optionStr] = $this->scmmodel->get_warehouse_stock($_POST['export_warehouse'], 'optioninfo', '', array($optionStr));
											$scmData[$optionStr] = $scmData[$optionStr][$optionStr];
										}

										$scmData[$optionStr]['stock'] = $scmData[$optionStr]['ea'];

										$excelData['OPT'][$opK]['SUB'][$subopK]['stock']		= $scmData[$optionStr]['stock'];
										$excelData['OPT'][$opK]['SUB'][$subopK]['whSeq']		= $scmData[$optionStr]['wh_seq'];
										$excelData['OPT'][$opK]['SUB'][$subopK]['whAuto']		= $scmData[$optionStr]['auto_warehousing'];
										$excelData['OPT'][$opK]['SUB'][$subopK]['supplyprice']	= $scmData[$optionStr]['supply_price'];
									} else {
										$scmData[$optionStr]['stock'] = $data_package['stock'];
									}

									$pkg_order_ea = $excelData['OPT'][$opK]['SUB'][$subopK]['request_ea'] * $data_package['unit_ea'];
									if($pkg_order_ea > $scmData[$optionStr]['stock']){
										$chk_pkg_stock	= false;
										continue;
									}
								}

								if(!$chk_pkg_stock && $_POST['stockable'] == 'limit' ){
									$excelData['error'][] = array(
										'msg'				=> '재고 부족 (추가옵션 연결상품)',
										'order_seq'			=> $ship['order_seq'],
										'shipping_seq'		=> $shipping_seq,
										'export_item_seq'	=> $subopV['export_item_seq'],
										'step'				=> $_POST['export_step']
									);
									return $excelData;
								}
							}else{
								//출고되는 실물상품은 '부분출고완료'이상에서만 재고체크 @2016-05-09
								if( $subopV['stock'] !== '미매칭'
									&& $subopV['stock'] < $excelData['OPT'][$opK]['SUB'][$subopK]['request_ea']
									&& $_POST['export_step'] >= 50
									&& $_POST['stockable'] == 'limit'  ){
										$excelData['error'][] = array(
											'msg'				=>'재고 부족 (추가옵션)',
											'order_seq'			=> $ship['order_seq'],
											'shipping_seq'		=> $shipping_seq,
											'export_item_seq'	=> $subopV['export_item_seq'],
											'step'				=> $_POST['export_step']
										);
										return $excelData;
								}
							}

							$totalCnt += $excelData['OPT'][$opK]['SUB'][$subopK]['request_ea'];
						}
					}

					# NPay 출고 검증 @2016-01-25 pjm
					if($npay_use && $opV['npay_product_order_id']){
						$npayres = $this->order2exportmodel->npay_deliver_check($opV['order_seq'], $opK);

						if($npayres['npay_order_id']){
							# 수량 분할 출고 불가
							if($opV['request_ea'] > 0 && $npayres['ea'] != $opV['request_ea']){
								$excelData['error'][] = array(
									'msg'				=> '['.$opV['order_seq']." ".$item['goods_name'].'] - 출고 수량 오류(NaverPay - 수량 분할 출고 불가)',
									'order_seq'			=> $ship['order_seq'],
									'shipping_seq'		=> $shipping_seq,
									'export_item_seq'	=> $opV['export_item_seq'],
									'step'				=> $_POST['export_step']
								);
								return $excelData;
							}
						}

						# 네이버페이 판매자센터 출고진행건 체크
						if($opV['request_ea'] > 0 && $opV['npay_pay_delivery'] == 'y'){
							$excelData['error'][] = array(
								'msg'				=> '['.$opV['order_seq'].'] - 출고처리불가(네이버 페이 판매자센터에서 출고진행중인 주문입니다.)',
								'order_seq'			=> $ship['order_seq'],
								'shipping_seq'		=> $shipping_seq,
								'export_item_seq'	=> $opV['export_item_seq'],
								'step'				=> $_POST['export_step']
							);
							return $excelData;
						}
					}

					if($this->excelmodel->setting_type == "ITEM"){
						$delivery_company	= $data[$opV['export_item_seq']]['delivery_company'];
						$delivery_number	= $data[$opV['export_item_seq']]['delivery_number'];
					} else {
						$delivery_company	= $data['delivery_company'];
						$delivery_number	= $data['delivery_number'];
					}

					//배달업체
					$delivery_code_arr = array();
					foreach($ship['couriers'] as $code => $courier){
						$delivery_code_arr[]	= $code;
						$delivery[]				= $courier['company'];

						if( $courier['company'] == $delivery_company ){
							$excelData['delivery_company_code']	= $code;
							$excelData['delivery_company']		= $courier['company'];
						} else {
							$excelData['delivery_company_code']	= $delivery_code_arr[0];
							$excelData['delivery_company']		= $delivery[0];
						}
					}

					//송장번호
					$excelData['delivery_number'] = $delivery_number;

					//배송방법
					$excelData['shipping_method'] = $ship['shipping_method'];

					$totalCnt += $excelData['OPT'][$opK]['request_ea'];
				}
			} else {
				//주문 미매칭 (주문번호와 배송번호 안맞음)
				$excelData['error'][] = array(
					'msg'				=> '주문 미매칭',
					'order_seq'			=> $ship['order_seq'],
					'shipping_seq'		=> $shipping_seq,
					'export_item_seq'	=> $opV['export_item_seq'],
					'step'				=> $_POST['export_step']
				);
				return $excelData;
			}

			//출고 갯수 0 이하
			if( $totalCnt <= 0 ){
				$excelData['error'][] = array(
					'msg'				=>'출고 안함',
					'order_seq'			=> $ship['order_seq'],
					'shipping_seq'		=> $shipping_seq,
					'export_item_seq'	=> $opV['export_item_seq'],
					'step'				=> $_POST['export_step']
				);
				return $excelData;
			}

			if( !$excelData['error'] ){
				$excelData['info']['shipping_seq']		= $shipping_seq;
				$excelData['info']['export_item_seq']	= $opV['export_item_seq'];
				$excelData['info']['step']				= $_POST['export_step'];
				$excelData['info']['total_ea']			= $totalCnt;
			}
		}

		return $excelData;
	}

	function get_export_data_coupon($shipping_seq, $shipping, $data){
		$excelData	= array();
		$scmData	= array();

		foreach($shipping['ordershipping'] as $ship) {
			$excelData['order_seq'] = $ship['order_seq'];

			if($shipping_seq == $ship['shipping_seq']) {
				$totalCnt = 0;

				foreach($ship['options'] as $opK => $opV) {
					$res = $this->order2exportmodel->get_info_by_export_item_seq($opV['export_item_seq']);

					if( $res[1] != $opV['shipping_seq'] || $res[2] != $opV['item_option_seq'] || !$ship['items'][$opV['item_seq']] ){
						$excelData['error'][] = array(
							'msg'				=>'출고 또는 상품 정보 없음',
							'order_seq'			=> $ship['order_seq'],
							'shipping_seq'		=> $shipping_seq,
							'export_item_seq'	=> $opV['export_item_seq'],
							'step'				=> $_POST['export_step']
						);
						return $excelData;
					}

					$item										= $ship['items'][$opV['item_seq']];
					$excelData['COU'][$opK]['goods_name']		= $item['goods_name'];
					$excelData['COU'][$opK]['goods_kind']		= $item['goods_kind'];
					$excelData['COU'][$opK]['optioninfo']		= $item['goods_seq'] . 'option'. $opV['option_seq'];

					//보낼 수량
					if($this->excelmodel->setting_type == "ITEM"){
						$excelData['COU'][$opK]['request_ea'] = $data[$opV['export_item_seq']]['request_ea'];
					} else { //상품별이면 엑셀 데이터로 수량 셋팅 - 부분출고를 위해
						$excelData['COU'][$opK]['request_ea'] = $opV['request_ea'];
					}

					// 출고 할 수량 체크
					if( $excelData['COU'][$opK]['request_ea'] > $opV['request_ea'] ){
						$excelData['error'][] = array(
							'msg'				=> '출고수량 오류(출고할 수량 : '.$opV['request_ea'].'개)',
							'order_seq'			=> $ship['order_seq'],
							'shipping_seq'		=> $shipping_seq,
							'export_item_seq'	=> $opV['export_item_seq'],
							'step'				=> $_POST['export_step']
						);
						return $excelData;
					}

					//재고 체크
					if($this->scm_cfg['use'] == 'Y'){
						$optionInfo = $excelData['COU'][$opK]['optioninfo'];

						if( !$scmData[$optionInfo] ){
							$scmData[$optionInfo] = $this->scmmodel->get_warehouse_stock($_POST['export_warehouse'], 'optioninfo', '', array($optionInfo));
							$scmData[$optionInfo] = $scmData[$optionInfo][$optionInfo];
						}

						$opV['whSeq']			= $scmData[$optionInfo]['wh_seq'];
						$opV['stock']			= $scmData[$optionInfo]['ea'];
						$opV['supply_price']	= $scmData[$optionInfo]['supply_price'];
						$opV['whAuto']			= $scmData[$optionInfo]['auto_warehousing']; //autoWh: 무재고 자동입고 처리
					}

					$excelData['COU'][$opK]['export_item_seq']	= $opV['export_item_seq'];
					$excelData['COU'][$opK]['title1']			= $opV['title1'];
					$excelData['COU'][$opK]['option1']			= $opV['option1'];
					$excelData['COU'][$opK]['title2']			= $opV['title2'];
					$excelData['COU'][$opK]['option2']			= $opV['option2'];
					$excelData['COU'][$opK]['title3']			= $opV['title3'];
					$excelData['COU'][$opK]['option3']			= $opV['option3'];
					$excelData['COU'][$opK]['title4']			= $opV['title4'];
					$excelData['COU'][$opK]['option4']			= $opV['option4'];
					$excelData['COU'][$opK]['title5']			= $opV['title5'];
					$excelData['COU'][$opK]['option5']			= $opV['option5'];
					$excelData['COU'][$opK]['ea']				= $opV['ea'];
					$excelData['COU'][$opK]['stock']			= $opV['stock'];
					$excelData['COU'][$opK]['whSeq']			= $opV['whSeq'];
					$excelData['COU'][$opK]['whAuto']			= $opV['whAuto'];
					$excelData['COU'][$opK]['whStock']			= $opV['whStock'];
					//$excelData['COU'][$opK]['request_ea']		= $opV['request_ea'];
					$excelData['COU'][$opK]['package_yn']		= $opV['package_yn'];
					$excelData['COU'][$opK]['supplyprice']		= $opV['supply_price'];

					// 티켓번호 체크
					if( $item['goods_data']['coupon_serial_type'] == 'n' ){
						$couopn_stock = $this->goodsmodel->get_count_coupon_serial($ship['items'][$opV['item_seq']]['goods_seq']);
						if( $couopn_stock < $excelData['COU'][$opK]['request_ea'] && $opV['package_yn'] != 'y' ){
							$excelData['error'][] = array(
								'msg'				=>'티켓번호부족',
								'order_seq'			=> $ship['order_seq'],
								'shipping_seq'		=> $shipping_seq,
								'export_item_seq'	=> $opV['export_item_seq'],
								'step'				=> $_POST['export_step']
							);
							return $excelData;
						}
					}

					// 재고 체크
					if( $opV['stock'] !== '미매칭'
						&& $opV['stock'] < $excelData['COU'][$opK]['request_ea']
						&& $_POST['ticket_step'] >= 50
						&& $_POST['ticket_stockable'] == 'limit' ){
							$excelData['error'][] = array(
								'msg'				=>'재고 부족',
								'order_seq'			=> $ship['order_seq'],
								'shipping_seq'		=> $shipping_seq,
								'export_item_seq'	=> $opV['export_item_seq'],
								'step'				=> $_POST['export_step']
							);
							return $excelData;
					}

					if($this->excelmodel->setting_type == "ITEM"){
						$delivery_company	= $data[$opV['export_item_seq']]['delivery_company'];
						$delivery_number	= $data[$opV['export_item_seq']]['delivery_number'];
					} else {
						$delivery_company	= $data['delivery_company'];
						$delivery_number	= $data['delivery_number'];
					}

					//배달업체
					$delivery_code_arr = array();
					foreach($ship['couriers'] as $code => $courier){
						$delivery_code_arr[]	= $code;
						$delivery[]				= $courier['company'];

						if( $courier['company'] == $delivery_company ){
							$excelData['delivery_company_code']	= $code;
							$excelData['delivery_company']		= $courier['company'];
						} else {
							$excelData['delivery_company_code']	= $delivery_code_arr[0];
							$excelData['delivery_company']		= $delivery[0];
						}
					}

					//송장번호
					$excelData['delivery_number'] = $delivery_number;

					//배송방법
					$excelData['shipping_method'] = $ship['shipping_method'];

					$totalCnt += $excelData['COU'][$opK]['request_ea'];
				}
			} else {
				//주문 미매칭 (주문번호와 배송번호 안맞음)
				$excelData['error'][] = array(
					'msg'				=> '주문 미매칭',
					'order_seq'			=> $ship['order_seq'],
					'shipping_seq'		=> $shipping_seq,
					'export_item_seq'	=> $opV['export_item_seq'],
					'step'				=> $_POST['export_step']
				);
				return $excelData;
			}

			//출고 갯수 0 이하
			if( $totalCnt <= 0 ){
				$excelData['error'][] = array(
					'msg'				=>'출고 안함',
					'order_seq'			=> $ship['order_seq'],
					'shipping_seq'		=> $shipping_seq,
					'export_item_seq'	=> $opV['export_item_seq'],
					'step'				=> $_POST['export_step']
				);
				return $excelData;
			}

			if( !$excelData['error'] ){
				$excelData['info']['shipping_seq']		= $shipping_seq;
				$excelData['info']['export_item_seq']	= $opV['export_item_seq'];
				$excelData['info']['step']				= $_POST['export_step'];
				$excelData['info']['total_ea']			= $totalCnt;
			}
		}

		return $excelData;
	}

	// 회원등급세트 업데이트 추가 :: 2019-09-25 pjw
	public function create_membersale(){
        //특정한 사유로 동일한 파일명으로 3초이내 업로드시 제한 @2017-06-01
        $date			= date('Y-m-d H:i:s', strtotime('-3 second'));
        $secondckLog	= $this->db->query("seLECT * FROM fm_excel_upload_log WHERE upload_date > '".$date."' limit 1")->result_array();
        if	($secondckLog[0]) {
            foreach($secondckLog as $secondckLogquery => $sklog){
                $fileinfo	= $_FILES['membersale_excel_file'];//첨부파일과 로그파일명 비교
                if( $sklog['upload_filename'] == $fileinfo['name'] ) {
                    openDialogAlert("과도한 접속으로 인한 제한합니다.<br/>5초 뒤 다시 접속해 주세요.", 400, 180, 'parent', "");
                    exit;
                }
            }
        }

        // 파일 적합성 체크
        $this->excel_file_check('membersale_excel_file', $_FILES, 'xlsx');

		$this->load->model('goodsexcel');
		$this->goodsexcel->m_sUploadFileName	= $this->fileName;
        $this->goodsexcel->m_sUploader			= $this->managerInfo['manager_id'];


        // 필수 구분자 열 정의
		$meta_row		= 4;
		$provider_cell	= 0;
		$brand_cell		= 2;
		$type_cell		= 4;
		$goods_seq		= 5;
		$goods_name		= 6;
        $searchCount	= 0;
		$update_cell	= array();
		$cell_meta		= array(
			'meta_row'		=> $meta_row,
			'provider_cell'	=> $provider_cell,
			'brand_cell'	=> $brand_cell,
			'type_cell'		=> $type_cell,
			'goods_seq'		=> $goods_seq,
			'goods_name'	=> $goods_name,
		);

		$provider_info	= array();
		$cell_info		= array();

        $reader = ReaderFactory::create(Type::XLSX);
        $reader->open($this->filePath);
        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $num => $row) {

				// 이전 컬럼들은 무시
				if($num < $meta_row) continue;

				// 메타 정보가 있는 로우에서 기본 정보 세팅 (한번만 실행함)
				if($meta_row == $num){
					// 기본 키 설정
					$provider_seq = $row[$provider_cell];
					$brand_seq	  = $row[$brand_cell];

					// 입점사 정보
					// 본사인 경우 수동으로 만들어서 리턴
					if($provider_seq > 1){
						$sql			= "SELECT fp.provider_seq, fp.provider_name, fpc.commission_type FROM fm_provider as fp LEFT JOIN fm_provider_charge as fpc ON fp.provider_seq = fpc.provider_seq WHERE fp.provider_seq = ?";
						$query			= $this->db->query($sql, array($provider_seq));
						$provider_info	= $query->row_array();

					}else{
						$provider_info	= array(
							'provider_seq'		=> 1,
							'provider_name'		=> '본사',
							'commission_type'	=> 'default',
						);
					}

					// 입점사 정보 없는경우
					if(empty($provider_info)) break;

					// 셀 정보 정의
					$cell_info	= $this->goodsexcel->get_membersale_cell_list(strtolower($provider_info['commission_type']));

					// 업데이트 되야할 셀 정보 정의
					$cell_key = 0;
					foreach($cell_info['cell'] as $key => $cell){


						if(!empty($cell['subcell'])){

							foreach($cell['subcell'] as $subkey => $subcell){
								$update_cell[$subkey] = $cell_key;

								$cell_key++;
							}

						}else{
							$update_cell[$key] = $cell_key;
							$cell_key++;
						}
					}
				}

				// 상품인 경우에만 카운트
				if($row[$type_cell] == 'goods') 	$searchCount++;
            }
        }

		$cell_meta['update_cell'] = $update_cell;
        $reader->close();

        if($searchCount <= 0){
            openDialogAlert("업로드 가능 한 ".$this->categoryKR."이 없습니다.", 400, 180, 'parent', '');
            exit;
        } else {
            $this->write_membersale($cell_info, $cell_meta, $provider_info, $searchCount);
        }
    }

	// 회원등급세트 업데이트 추가 :: 2019-09-25 pjw
    function write_membersale($cell_info, $cell_meta, $provider_info, $searchCount){
        $this->load->model('goodsmodel');
        $this->load->model('goodsexcel');

        // 엑셀 파일 열기
        $reader = ReaderFactory::create(Type::XLSX);
        $reader->open($this->filePath);

		// 성공 여부
		$goods_succ_cnt = 0;
		$goods_fail_cnt = 0;

		// 임시 goods_seq 목록
		$succ_goods_seq = array();

		// 엑셀 데이터 조회
        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $num => $row) {

				// 실제 데이터 행까지 넘어감
				if($cell_meta['meta_row'] > $num) continue;


				// 상품, 필수옵션, 추가옵션 구분값
				$type		= $row[$cell_meta['type_cell']];
				$goods_seq	= $row[$cell_meta['goods_seq']];
				$goods_name	= $row[$cell_meta['goods_name']];

				switch($type){
					case 'goods':

						$sale_seq		= $row[$cell_meta['update_cell']['sale_seq']];

						// 회원 등급세트 수정
						$sql			= "UPDATE fm_goods SET sale_seq = ? WHERE goods_seq = ? AND provider_seq = ?";
						$result			= $this->db->query($sql, array($sale_seq, $goods_seq, $provider_info['provider_seq']));

						if($result > 0){
							$this->goodsexcel->save_upload_log('success', '[' . $goods_name. '/' . $goods_seq . '] 상품의 회원등급세트가 수정되었습니다.'."\r\n");
							$succ_goods_seq[] = $goods_seq;
							$goods_succ_cnt++;
						}else{
							$this->goodsexcel->save_upload_log('failed', '[' . $goods_name. '/' . $goods_seq . '] 상품의 회원등급세트가 수정이 실패하였습니다.'."\r\n");
							$goods_fail_cnt++;
						}

					break;

					case 'option':
					case 'suboption':

						// 공통 정보
						$consumer_price		= str_replace(',', '', $row[$cell_meta['update_cell']['consumer_price']]);
						$price				= str_replace(',', '', $row[$cell_meta['update_cell']['price']]);
						$supply_price		= str_replace(',', '', $row[$cell_meta['update_cell']['supply_price']]);
						$update_set			= "";

						// 입점사 정보마다 업데이트 할 항목 다름
						if($provider_info['provider_seq'] > 1){

							// 입점사 방식 별로 넣어줄 데이터 상이
							if($provider_info['commission_type'] == 'SACO'){

								// %가 붙는 경우 소숫점으로 들어와서 변환처리
								$commission_rate	= $this->get_excel_rate($row[$cell_meta['update_cell']['commission_rate']]);
								$update_set			.= ", commission_rate = '" . $commission_rate ."' ";

							}else{

								$supply_price		= $row[$cell_meta['update_cell']['supply_price']];
								$supply_rate		= $this->get_excel_rate($row[$cell_meta['update_cell']['supply_rate']]);

								if(!empty($supply_price)){
									$update_set .= ", commission_rate = '" . str_replace(',', '', $supply_price) ."' ";
								}else if(!empty($supply_rate)){
									$update_set .= ", commission_rate = '" . $supply_rate ."' ";
								}
							}

						}

						$sql		= "UPDATE fm_goods_".$type." SET consumer_price = ? , price = ? ".$update_set." WHERE ".$type."_seq = ? AND goods_seq in ('" . implode("','", $succ_goods_seq) . "') ";
						$result		= $this->db->query($sql, array($consumer_price, $price, $goods_seq));

						if($result > 0){
							$this->goodsexcel->save_upload_log('success', '[' . $goods_name. '/' . $goods_seq . '] 옵션 정보가 수정되었습니다.'."\r\n");
							$goods_succ_cnt++;
						}else{
							$this->goodsexcel->save_upload_log('failed', '[' . $goods_name. '/' . $goods_seq . '] 옵션 정보 수정이 실패하였습니다.'."\r\n");
							$goods_fail_cnt++;
						}


						// 매입금액 수정
						$sql			= "UPDATE fm_goods_supply SET supply_price = ? WHERE ".$type."_seq = ? AND goods_seq in ('" . implode("','", $succ_goods_seq) . "')";
						$result			= $this->db->query($sql, array($supply_price, $goods_seq));

						if($result > 0){
							$this->goodsexcel->save_upload_log('success', '[' . $goods_name. '/' . $goods_seq . '] 옵션의 매입금액이 수정되었습니다.'."\r\n");
							$goods_succ_cnt++;
						}else{
							$this->goodsexcel->save_upload_log('failed', '[' . $goods_name. '/' . $goods_seq . '] 옵션의 매입금액이 수정이 실패하였습니다.'."\r\n");
							$goods_fail_cnt++;
						}

					break;
				}

            }
        }
        $reader->close();
        $this->goodsexcel->close_upload_log();

        openDialogAlert("처리완료 (성공:". $goods_succ_cnt ."/실패:". $goods_fail_cnt.") 되었습니다.<br/>처리 로그를 확인해 주십시오.", 400, 180, 'parent', "parent.location.reload();");
        exit;
    }


	// 엑셀에서 백분율 데이터가 넘어올때 변환처리
	// %가 붙어서 오는 경우도 있고, 소숫점으로 넘어오는 경우 있음 (수정 여부에 따라 다름)
	function get_excel_rate($str){

		// comma 제거
		$newstr = str_replace(',', '', $str);

		// %가 붙은 경우
		if(strpos($str, '%') !== false) {
			$newstr = str_replace('%', '', $newstr);
		}else{
			$newstr = $newstr * 100;
		}

		return $newstr;
	}

}

/* End of file excel_up.php */
/* Location: /controllers/cli/excel_up */
