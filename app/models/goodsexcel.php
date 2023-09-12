<?php
require_once(APPPATH.'/libraries/Spout/Autoloader/autoload.php'); //excel library

use Box\Spout\Reader\ReaderFactory;
use Box\Spout\Writer\WriterFactory;
use Box\Spout\Common\Type;
use Box\Spout\Writer\Style\StyleBuilder;
use Box\Spout\Writer\Style\Color;
use Box\Spout\Writer\Style\Border;
use Box\Spout\Writer\Style\BorderBuilder;

class goodsExcel extends CI_Model {

	// 전역변수 네이밍 method의 m_ + 변수타입(배열:a,문자열:s,숫자:n,객체:o,boolm:b) + 변수명(PascalCasing)
	var $PHPExcel					= '';
	var $IOFactory					= '';
	var $m_sUploadFileName			= '';
	var $m_sUploadAllowedType		= '';
	var $m_sGoodsAddQuery			= '';
	var $m_sExcelDownloadFilePath	= '';
	var $m_sExcelUploadFilePath		= '';
	var $m_sLogFilePath				= '';
	var $m_oSuccessLogFile			= '';
	var $m_oFailedLogFile			= '';
	var $m_sUploader				= '';
	var $m_sProviderChoice			= '';
	var $m_sFileName				= '';
	var $m_sReservePolicy			= '';
	var $m_sOptionReservePolicy		= '';
	var $m_sAfterTreatmentFunc		= '';
	var $m_sAfterTreatmentFuncEnd	= '';
	var $m_sServiceType				= 'N';
	var $m_sProcType				= 'D';
	var $m_sGoodsKind				= 'G';
	var $m_sAdminType				= 'A';
	var $m_sGoodsSaveType			= 'insert';
	var $m_sDownloadLineString		= "&#10;";
	var $m_nCodeLen					= 7;
	var $m_nMaxRow					= 3000;
	var $m_nTotalCount				= 0;
	var $m_nTotalPage				= 0;
	var $m_sUploadLineCharString	= 10;
	var $m_nProviderSeq				= 1;
	var $m_nProviderCommission		= 0;
	var $m_nCurrentOptionCount		= 0;
	var $m_bZipDown					= false;
	var $m_bIsScm					= false;
	var $m_aCellList				= array();
	var $m_aTableInfo				= array();
	var $m_aFieldInfo				= array();
	var $m_aOptionMultiRowCell		= array();
	var $m_aSuboptionMultiRowCell	= array();
	var $m_aInputMultiRowCell		= array();
	var $m_aOptionRowCountCell		= array();
	var $m_aSuboptionRowCountCell	= array();
	var $m_aInputRowCountCell		= array();
	var $m_aUpdateProviderIgnore	= array();
	var $m_aNeedZeroVal				= array();
	var $m_aImageName				= array();
	var $m_aProviderList			= array();
	var $m_aProviderAsGoods			= array();
	var $m_aAddOptionInfoList		= array();
	var $m_aCurrentGoodsInfo		= array();
	var $m_aChgGoodsTarget			= array();
	var $m_aLocationList			= array();
	var $m_aGoodsView				= array();

	public function __construct(){
		$this->set_service_type();
		$this->m_sExcelDownloadFilePath	= ROOTPATH . 'data/excel_tmp';
		$this->m_sExcelUploadFilePath	= ROOTPATH . 'data/tmp';
		$this->m_sLogFilePath			= ROOTPATH . 'data/excel_tmp';

		$this->crt_folder($this->m_sExcelDownloadFilePath);
		$this->crt_folder($this->m_sExcelUploadFilePath);
		$this->crt_folder($this->m_sLogFilePath);

		// 물류관리
		$this->load->model('scmmodel');
		if	($this->scmmodel->chkScmConfig(true) && $this->m_sAdminType != 'S')	$this->m_bIsScm		= true;

		$this->load->model('providermodel');
		$provider_tmp	= $this->providermodel->provider_goods_list();

		foreach((array)$provider_tmp as $val){
			$provider_list[$val['provider_seq']]	= $val;
		}

		$this->m_aProviderList	= $provider_list;
	}

	########## ↓↓↓↓↓ 양식 저장 관련 ↓↓↓↓↓ ##########

	// 기존 다운로드 양식 정보 추출
	public function get_excel_form_data($sc){

		if	($sc['seq']){
			$sAddWhere	.= " and form_seq = ? ";
			$aAddBind[]	= $sc['seq'];
		}

		if	($sc['gb']){
			$sAddWhere	.= " and form_type = ? ";
			$aAddBind[]	= $sc['gb'];
		}

		if		($sc['provider_seq']){
			$sAddWhere	.= " and provider_seq = ? ";
			$aAddBind[]	= $sc['provider_seq'];
		}elseif	($this->m_sAdminType == 'S' && $this->m_nProviderSeq > 0){
			$sAddWhere	.= " and provider_seq = ? ";
			$aAddBind[]	= $this->m_nProviderSeq;
		}else{
			$sAddWhere	.= " and provider_seq = '1' ";
		}

		$oQuery	= $this->db->query("select * from fm_excel_download_form where form_seq > 0 " . $sAddWhere, $aAddBind);
		$aData	= $oQuery->result_array();
		if	($aData)foreach($aData as $k => $data){
			$data['item_arr']	= explode('|', $data['form_item']);
			$data['sort_arr']	= json_decode($data['sort_item']);
			$return[]			= $data;
		}

		return $return;
	}

	// 다운로드 양식 저장
	public function save_excel_form($params){

		
		// check되어 넘어온 cell 데이터
		if	($params['chk_cell'])foreach($params['chk_cell'] as $k => $code){
			$item	.= '|' . implode('|', $this->division_code_string($code));
		}
		$item			= substr($item, 1);
		$provider_seq	= 1;
		if	($params['form_id'])	$form_id	= $params['form_id'];
		else						$form_id	= 'admin_goods_'.date('YmdHis');
		if	($params['form_name'])	$form_name	= $params['form_name'];
		else						$form_name	= '관리자 상품엑셀';
		if	($params['form_type'])	$form_type	= $params['form_type'];
		else						$form_type	= 'GOODS';

		if	($this->m_sAdminType == 'S' && $this->m_nProviderSeq > 0){
			$provider_seq	= $this->m_nProviderSeq;
			if	(!$params['form_name'])	$form_name	= '입점사 상품엑셀';
		}

		if	($params['seq'] > 0){
			$upParam['form_name']	= $form_name;
			$upParam['form_item']		= $item;
			$upParam['sort_item']		= json_encode($params['sort_cell']);
			$upParam['update_date']	= date('Y-m-d H:i:s');

			$this->db->where(array('form_seq' => $params['seq']));
			$this->db->update('fm_excel_download_form', $upParam);
		}else{
			$inParam['form_id']			= $form_id;
			$inParam['provider_seq']	= $provider_seq;
			$inParam['form_name']		= $form_name;
			$inParam['form_type']		= $form_type;
			$inParam['form_item']		= $item;
			$upParam['sort_item']		= json_encode($params['sort_cell']);
			$inParam['regist_date']		= date('Y-m-d H:i:s');
			$inParam['update_date']	= date('Y-m-d H:i:s');
			$this->db->insert('fm_excel_download_form', $inParam);
		}
	}

	// 문자열로 합쳐진 코드 배열로 분리
	public function division_code_string($str_code){
		$nLen	= strlen($str_code) / $this->m_nCodeLen;
		if	($nLen > 1){
			for ( $i = 0; $i < $nLen; $i++){
				$return[]	= substr($str_code, ($i*$this->m_nCodeLen), $this->m_nCodeLen);
			}
		}else{
			$return[]	.= $str_code;
		}

		return $return;
	}

	// 전체 컬럼을 양식용 배열로 return ( 양식 저장에서 구역 분리 처리용 - 변경될 수 있음 )
	public function get_cell_list_for_form($oldArr,$mode){
		$m_aCellList	= $org_aCellList = $this->m_aCellList;
		if	(is_array($oldArr) && count($oldArr) > 0){
			$m_aCellList	= $oldArr;
		}

		if	($m_aCellList) foreach($m_aCellList as $code => $title){
			/**
			 * newform(UI/UX 이후) group_num = 0 초기화
			 * 실제 다운로드 시 가공안된 DB에 저장한 순서대로 다운로드됨
			 */
			$group_num	= ($mode == 'newform') ? 0 : substr($code, -4, 1);
			$except		= substr($code, 4, 1);
			if		(in_array($except, array('O', 'S', 'I', 'W'))){
				if	(!isset(${'except_' . $except})){
					${'except_' . $except}		= count($return[$group_num]['list']);
					$except_num					= ${'except_' . $except};
					$return[$group_num]['list'][$except_num]['code']	= $code;
					$return[$group_num]['list'][$except_num]['title']	= $title;
					if($oldArr) {
						$return[$group_num]['list'][$except_num]['title'] = $org_aCellList[$code];
					}
				}else{
					$return[$group_num]['list'][$except_num]['code']	.= $code;
					//$return[$group_num]['list'][$except_num]['title']	.= '<br/>' . $title;
				}
			}else{
				$aData							= array('code' => $code, 'title' => $title);
				if($oldArr) {
					$aData['title'] = $org_aCellList[$code];
				}
				$return[$group_num]['list'][]	= $aData;
			}
		}

		return $return;
	}

	########## ↑↑↑↑↑ 양식 저장 관련 ↑↑↑↑↑ ##########


	########## ↓↓↓↓↓ 엑셀 다운로드 ↓↓↓↓↓ ##########
	public function download_excel_spout($params){
		// 선택형일 경우 선택된 값이 있는지 체크
		if	(($params['excel_type'] == 'select' || $params['excel_type'] == 'select_barcode' ) && !$params['goods_seq']) {
			echo '선택된 상품이 없습니다.';
			exit;
		}

		// 다운로드 양식 데이터 추출 ( 추후 양식을 여러개 제공 시 seq 검색을 추가해야 한다. )
		$sc = array();
		$sc['gb']			= 'GOODS';
		$sc['provider_seq']	= $params['provider_seq'];
		if	($this->m_sGoodsKind == 'C')	$sc['gb']			= 'COUPON';
		if	($this->m_nProviderSeq > 1)		$sc['provider_seq']	= $this->m_nProviderSeq;
		$forms		= $this->get_excel_form_data($sc);
		$excelForm	= $forms[0];
		if	(!$excelForm['form_seq'] || count($excelForm['item_arr']) < 1){
			echo '다운로드 양식 정보가 없습니다.<br/>다운로드 항목설정에서 양식을 생성해 주세요.';
			exit;
		}
		$this->create_download_excel_spout($excelForm, $params);
	}

	public function create_download_excel_spout($excelForm, $params=null){
		$reg_date = date('Y-m-d H:i:s');
		$fileExe = 'xlsx';
		$writer = WriterFactory::create(Type::XLSX); // for XLSX files
		
		$border = (new BorderBuilder())
			->setBorderRight(Color::BLACK, Border::WIDTH_THIN, Border::STYLE_SOLID)
			->setBorderBottom(Color::BLACK, Border::WIDTH_THIN, Border::STYLE_SOLID)
			->build();

		$style_title = (new StyleBuilder())
		   ->setBorder($border)
		   ->setFontBold()
		   ->setFontSize(11)
		   ->setFontColor(Color::BLACK)
		   ->setShouldWrapText(false)
		   ->setBackgroundColor(Color::rgb(221, 221, 221))
		   ->build();

		$style_contents = (new StyleBuilder())
		   ->setBorder($border)
		   ->setFontSize(11)
		   ->setFontColor(Color::BLACK)
		   ->setShouldWrapText()
		   ->build();

		// 파일명 생성
		$arrSystem	= ($this->config_system)?$this->config_system:config_load('system');
		$arr_sub_domain = explode(".",$arrSystem['subDomain']);
		$name_sub_domain = sprintf("%s","{$arr_sub_domain['0']}");
		$filename = $name_sub_domain."_goods_list_".date('YmdHis').".".$fileExe;

		$echoPath	= "goods/" . date("Ymd") . "/";
		$downPath	= ROOTPATH . "excel_download/" . $echoPath;
		if(!is_dir($downPath)){
			mkdir($downPath);
			chmod($downPath,0777);
		}
		$filePath = $downPath . $filename;

		$writer->openToFile($filePath);

		$columnNames = array();
		$cellNames	 = array();
		foreach( $excelForm['item_arr'] as $cellCode ){
			if( isset($this->m_aCellList[$cellCode]) ){
				$columnNames[]	= $this->m_aCellList[$cellCode];	
			}
			if($this->m_aTableInfo[$cellCode] == 'fm_goods'){
				$cellNames[] = "C.".$this->m_aFieldInfo[$cellCode];
			}
		}
		
		// o2o 바코드 실물 다운로드 규격 추가
		$this->load->library('o2o/o2oinitlibrary');
		$this->o2oinitlibrary->init_admin_goods_barcode_download_form($columnNames, $cellNames, $params);
		
		$writer->addRowWithStyle($columnNames, $style_title); 

		/*
		if($params['orderby'] == 'desc_goods_seq') $params['orderby']	= 'goods_seq';
		$orderbyTmp = explode("_",$params['orderby']);
		if(in_array($orderbyTmp[0],array("asc","desc"))){
			foreach($orderbyTmp as $orderK=>$orderV) if($orderK > 0) $orderbyTmp2[] = $orderV;
			$params['orderby']	= implode("_",$orderbyTmp2);
			$params['sort']		= $orderbyTmp[0];
		}else{
			$params['orderby'];
		}
		*/

		for ( $c = 1; $c <= 4; $c++){
			if($params['category'.$c]){
				$params['category']	= $params['category'.$c];
			}
		}
		
		$params['excel_spout']	= true;
		$params['goods_type']	= 'goods';
		$params['abs_goods_seq']= $params['goods_seq'];

		//파일 생성 시작
		//$loopCount	= ceil($params['searchcount'] / $params['perpage']);
		//for($i=0; $i<$loopCount; $i++) {
			//$params['page']		= $i * $params['perpage'];
			$sql = $this->goodsmodel->admin_goods_list($params);

			if( !array_search('C.goods_seq', $cellNames) ){
				$cellNames[] = 'C.goods_seq';
			}

			if( array_search('C.suboption_layout_group_num', $cellNames) ){
				$cellNames[] = 'C.option_suboption_use';
			}

			if( array_search('C.relation_image_size', $cellNames) ){
				$cellNames[] = 'C.relation_count_w';
				$cellNames[] = 'C.relation_count_h';
			}

			$queryDB = mysqli_query($this->db->conn_id, "SELECT ".implode(",", $cellNames).",
							CASE WHEN C.goods_status = 'unsold' THEN '판매중지'
							WHEN C.goods_status = 'purchasing' THEN '재고확보중'
							WHEN C.goods_status = 'runout' THEN '품절'
							ELSE '정상' END AS goods_status_text
						,D.consumer_price,D.price
				FROM (SELECT C.* ".$sql.") as C
				left join fm_goods_option as D on C.goods_seq=D.goods_seq and D.default_option='y'");

			// o2o 바코드 실물 다운로드 쿼리 추가
			$this->load->library('o2o/o2oinitlibrary');
			$this->o2oinitlibrary->init_admin_goods_barcode_download_sql($queryDB, $this->db->conn_id, $params);

			while($goods = mysqli_fetch_array($queryDB)){
				unset($this->db->queries);
				unset($this->db->query_times);

				$goodsRow = array();
				if($this->m_sServiceType == 'A'){
					$goods['provider_seq'] = $goods['provider_seq'];
				}
				$this->m_sReservePolicy = $goods['reserve_policy'];
				if($goods)foreach($goods as $fld => $val){
					if	($fld != 'provider_status'){
						$this->get_download_goods_change_val($fld, $goods);
					}
				}

				// 승인여부 처리는 마지막에 한다.
				if($goods['provider_status']){
					$this->get_download_goods_change_val('provider_status', $goods);
				}
				
				$addGoodsData = $this->get_excel_add_goods($goods['goods_seq'], $excelForm['item_arr']);
				if($addGoodsData) {	
					$goods	= array_merge($goods, $addGoodsData);
					unset($addGoodsData);
				}

				$tmpGoodsRow = array();
				foreach($excelForm['item_arr'] as $x => $cellCode){
					if	( !isset($this->m_aFieldInfo[$cellCode]) ) {
						continue;
					}

					$tmpGoodsRow[] = html_entity_decode($goods[$this->m_aFieldInfo[$cellCode]], ENT_QUOTES, 'utf-8');
				}
				$goodsRow = $tmpGoodsRow;
				
				// o2o 바코드 실물 다운로드 행 추가
				$this->load->library('o2o/o2oinitlibrary');
				$this->o2oinitlibrary->init_admin_goods_barcode_download_row($goodsRow, $goods, $params);
				
				$writer->addRowWithStyle($goodsRow, $style_contents);

				unset($goods, $result, $goodsRow, $tmpGoodsRow);
			}
		//}
		//foreach end
		$writer->close();

		$com_date		= date('Y-m-d H:i:s');
		$expired_date	= date('Y-m-d H:i:s', strtotime('+7 days', strtotime($com_date)));
		$setData = array(
			'id'			=> '',
			'provider_seq'	=> $params['provider_seq'],
			'manager_id'	=> $this->managerInfo['manager_id'],
			'category'		=> 1, //1:goods, 2:order, 3:member
			'excel_type'	=> $params['excel_type'], 
			'context'		=> serialize($params),
			'count'			=> $params['searchcount'],
			'state'			=> 2,
			'file_name'		=> str_replace("goods/", "", $echoPath.$filename),
			'limit_count'	=> $params['perpage'],
			'reg_date'		=> $reg_date,
			'com_date'		=> $com_date,
			'expired_date'	=> $expired_date
		);
		$this->db->insert('fm_queue', $setData);

		echo $echoPath.$filename;
		exit;
	}

	// 엑셀 다운로드 함수
	public function download_excel($params){
		$result	= array('status' => true);

		// 엑셀 파일들 압축 후 다운로드
		if	($params['excel_zip_file']){
			$this->download_excel_zip_file($params);
		}

		// 선택형일 경우 선택된 값이 있는지 체크
		if	($params['excel_type'] == 'select' && !$params['goods_seq']) {
			$result['status']	= false;
			$result['err_msg']	= '선택된 상품이 없습니다.';
			return $result;
		}

		// 파일명 생성
		$excel_file_name	= 'download_goods_excel_' . date('YmdHis') . rand(0,9999);
		if	($params['excel_file_name'])	$excel_file_name	= $params['excel_file_name'];

		// 상품 기본 정보 추출
		$goodsList	= $this->get_excel_goods($params);
		if	(!is_array($goodsList) || count($goodsList) < 1){
			$result['status']	= false;
			$result['err_msg']	= '다운로드할 상품이 없습니다.';
			return $result;
		}

		// 다운로드 양식 데이터 추출 ( 추후 양식을 여러개 제공 시 seq 검색을 추가해야 한다. )
		$sc['gb']			= 'GOODS';
		$sc['provider_seq']	= 1;
		if	($this->m_sGoodsKind == 'C')	$sc['gb']			= 'COUPON';
		if	($this->m_nProviderSeq > 1)		$sc['provider_seq']	= $this->m_nProviderSeq;
		$forms		= $this->get_excel_form_data($sc);
		$excelForm	= $forms[0];
		if	(!$excelForm['form_seq'] || count($excelForm['item_arr']) < 1){
			$result['status']	= false;
			$result['err_msg']	= '다운로드 양식 정보가 없습니다.<br/>다운로드 항목설정에서 양식을 생성해 주세요.';
			return $result;
		}

		// 압축 다운일 때
		if	($this->m_bZipDown){

			$this->create_download_excel('file', $excel_file_name, $goodsList, $excelForm);

			// 마지막 페이지일 경우 zip파일 다운로드
			if		($this->m_nExcelPage == $this->m_nTotalPage){
					$params['excel_zip_file']	= $excel_file_name;
			// 첫 페이지일 경우 parameter에 추가 다운로드
			}elseif	($this->m_nExcelPage == 1){
					$params['excel_page']		= 2;
					$params['excel_zipdown']	= true;
					$params['excel_totalCount']	= $this->m_nTotalCount;
					$params['excel_totalPage']	= $this->m_nTotalPage;
					$params['excel_file_name']	= $excel_file_name;
			}else{
				$params['excel_page']++;
			}
			// 페이지 이동 replace 한다. ( history에 남지 않음 )
			$url			= $_SERVER['REDIRECT_URL'] . '?' . http_build_query($params);
			header("Location:".$url, true);
		}else{
			$this->create_download_excel('down', $excel_file_name, $goodsList, $excelForm);
		}

		return $result;
	}

	// 엑셀 파일 생성
	public function create_download_excel($outputType, $filename, $goodsList, $excelForm){
		if	($outputType == 'file'){
			// directory 생성
			if(!is_dir($this->m_sExcelDownloadFilePath)){
				@mkdir($this->m_sExcelDownloadFilePath);
				@chmod($this->m_sExcelDownloadFilePath, 0777);
			}

			// 임시 폴더에 파일로 저장 ( 전일 남은 dummy file은 cron에서 일괄 삭제 함 )
			$filename		= $filename.'_'.$this->m_nExcelPage.'.xls';
			$filepath		= $this->m_sExcelDownloadFilePath . '/'. $filename;

			$fObj			= fopen($filepath, 'w+');
			fwrite($fObj, $this->get_default_excel_header($excelForm));
			foreach($goodsList as $g => $goods){
				$this->m_sReservePolicy		= $goods['reserve_policy'];
				if	($goods)foreach($goods as $fld => $val){
					if	($fld != 'provider_status'){
						$this->get_download_goods_change_val($fld, $goods);
					}
				}
				// 승인여부 처리는 마지막에 한다.
				if	($goods['provider_status']){
					$this->get_download_goods_change_val('provider_status', $goods);
				}
				$addGoodsData				= $this->get_excel_add_goods($goods['goods_seq'], $excelForm['item_arr']);
				if	($addGoodsData)	$goods	= array_merge($goods, $addGoodsData);

				fwrite($fObj, '<Row ss:Index="'.($g+2).'" ss:Height="33">');
				foreach($excelForm['item_arr'] as $x => $cellCode){
					if	(!isset($this->m_aFieldInfo[$cellCode])) continue;
					$msg	= $goods[$this->m_aFieldInfo[$cellCode]];
					if	(preg_match('/\</', $msg)){
						fwrite($fObj, '<Cell ss:StyleID="s62"><Data ss:Type="String"><![CDATA['.$msg.']]></Data></Cell>');
					}else{
						fwrite($fObj, '<Cell ss:StyleID="s62"><Data ss:Type="String">'.$msg.'</Data></Cell>');
					}
				}
				fwrite($fObj, '</Row>');
			}
			fwrite($fObj, $this->get_default_excel_footer());

			fclose($fObj);
		}else{
			// 즉시 다운로드
			header('Content-Type: application/vnd.ms-excel');
			header("Content-Disposition: attachment;filename=".$filename.".xls");
			header('Cache-Control: max-age=0');

			echo $this->get_default_excel_header($excelForm);
			foreach($goodsList as $g => $goods){
				if	($this->m_sServiceType == 'A'){
					### PROVIDER
					$goods['provider_seq'] = $goods['provider_seq'];
				}
				$this->m_sReservePolicy		= $goods['reserve_policy'];
				if	($goods)foreach($goods as $fld => $val){
					if	($fld != 'provider_status'){
						$this->get_download_goods_change_val($fld, $goods);
					}
				}
				// 승인여부 처리는 마지막에 한다.
				if	($goods['provider_status']){
					$this->get_download_goods_change_val('provider_status', $goods);
				}
				$addGoodsData				= $this->get_excel_add_goods($goods['goods_seq'], $excelForm['item_arr']);
				if	($addGoodsData)	$goods	= array_merge($goods, $addGoodsData);

				echo '<Row ss:Index="'.($g+2).'" ss:Height="33">';
				foreach($excelForm['item_arr'] as $x => $cellCode){
					if	(!isset($this->m_aFieldInfo[$cellCode])) continue;
					$msg	= $goods[$this->m_aFieldInfo[$cellCode]];
					if	(preg_match('/\</', $msg)){
						echo '<Cell ss:StyleID="s62"><Data ss:Type="String"><![CDATA['.$msg.']]></Data></Cell>';
					} else if( empty($msg) && $msg != "0" ) {			// 빈값의 경우공백이 아닌 null 처리, 값이 0일때는 제외
						echo '<Cell ss:StyleID="s62"></Cell>';
					}else{
						echo '<Cell ss:StyleID="s62"><Data ss:Type="String">'.$msg.'</Data></Cell>';
					}
				}
				echo '</Row>';
			}
			echo $this->get_default_excel_footer();

		}
	}

	// xml 형태 엑셀의 기본 header
	public function get_default_excel_header($excelForm){
		$excelXmlHeader	= '<?xml version="1.0"?>					<?mso-application progid="Excel.Sheet"?>
					<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"
						xmlns:o="urn:schemas-microsoft-com:office:office"
						xmlns:x="urn:schemas-microsoft-com:office:excel"
						xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"
						xmlns:html="http://www.w3.org/TR/REC-html40">
					<Styles>
						<Style ss:ID="Default" ss:Name="Normal">
							<Alignment ss:Vertical="Top"/>
							<Borders/>
							<Font ss:FontName="맑은 고딕" x:CharSet="129" x:Family="Modern" ss:Size="11" ss:Color="#000000"/>
							<Interior/>
							<NumberFormat/>
							<Protection/>
						</Style>
						<Style ss:ID="s62">
							<Alignment ss:Vertical="Top" ss:WrapText="1"/>
						</Style>
						<Style ss:ID="s63">
							<Alignment ss:Horizontal="Center" ss:Vertical="Top" />
							<Interior ss:Color="#dfeaff" ss:Pattern="Solid"/>
							<Font ss:Size="11" ss:Color="#000000" ss:Bold="1" />
						</Style>
						<Style ss:ID="s64">
							<Alignment ss:Horizontal="Center" ss:Vertical="Top" />
							<Interior ss:Color="#dfeaff" ss:Pattern="Solid"/>
							<Font ss:Size="11" ss:Color="#FF0000" ss:Bold="1" />
						</Style>
					</Styles>
					<Worksheet ss:Name="Sheet1">
						<Table>';

		$excelXmlHeader	.= '<Row ss:Index="1" ss:Height="33">';
		foreach( $excelForm['item_arr'] as $c => $cellCode){
			if	(!isset($this->m_aCellList[$cellCode])) continue;
			$StyleID		= 's63';
			if	(substr($cellCode, 2, 1) == 'R')	$StyleID	= 's64';
			$excelXmlHeader	.= '<Cell ss:StyleID="'.$StyleID.'"><Data ss:Type="String">'.$this->m_aCellList[$cellCode].'</Data></Cell>';
		}
		$excelXmlHeader	.= '</Row>';

		return $excelXmlHeader;
	}

	// xml 형태 엑셀의 기본 footer
	public function get_default_excel_footer(){
		return '</Table></Worksheet></Workbook>';
	}

	// 엑셀 파일들 압축하여 다운로드
	public function download_excel_zip_file($params){

		$this->load->helper('download');

		$zipfile	= $params['excel_zip_file'] . '.zip';
		$zippath	= $this->m_sExcelDownloadFilePath . '/' . $zipfile;
		$this->load->library('pclzip',array('p_zipname' => $zippath));


		// 압축할 파일명 배열 생성
		for	($p = 1; $p <= $params['excel_totalPage']; $p++){
			$filename	= $params['excel_zip_file'].'_'.$p.'.xls';
			$filepath	= $this->m_sExcelDownloadFilePath . '/'. $filename;
			if	(file_exists($filepath) && is_file($filepath)){
				$excel_files[]	= $filepath;
			}
		}

		// 파일 압축
		$zipFile	= $this->pclzip->create($excel_files,
											PCLZIP_OPT_REMOVE_PATH, $this->m_sExcelDownloadFilePath);

		// 파일 삭제
		for	($p = 1; $p <= $params['excel_totalPage']; $p++){
			$filename	= $params['excel_zip_file'].'_'.$p.'.xls';
			$filepath	= $this->m_sExcelDownloadFilePath . '/'. $filename;
			if	(file_exists($filepath) && is_file($filepath)){
				@unlink($filepath);
			}
		}

		if	($zipFile === 0){
			openDialogAlert('파일 압축에 실패하였습니다.', 400, 150, '');
			exit;
		}else{
			$url	= str_replace(ROOTPATH, '/', $zippath);
			header("Location:".$url, true);
			exit;
		}
	}

	// 상품 정보 추출 ( 최대 row수를 넘어서면 페이징으로 전환 )
	public function get_excel_goods($params, $page = 0){

		$this->load->model('goodsmodel');

		if	($params['orderby'] == 'desc_goods_seq')	$params['orderby']	= 'goods_seq';
		//정렬관련 추가 (정가, 할인가, 재고 오름/내림 차순 정렬)
		$orderbyTmp = explode("_",$params['orderby']);
		if(in_array($orderbyTmp[0],array("asc","desc"))){
			foreach($orderbyTmp as $orderK=>$orderV) if($orderK > 0) $orderbyTmp2[] = $orderV;
			$params['orderby']	= implode("_",$orderbyTmp2);
			$params['sort']		= $orderbyTmp[0];
		}else{
			$params['orderby'];
		}
		if	($params['excel_type'] == 'select'){
			$sc['abs_goods_seq']	= $params['goods_seq'];
		}else{
			$sc	= $params;
			for ( $c = 1; $c <= 4; $c++){
				if	($sc['category'.$c])	$sc['category']	= $sc['category'.$c];
			}
		}

		$sc['goods_kind']	= $sc['goodsKind']	= 'goods';
		if	($this->m_sGoodsKind == 'C')	{
			$sc['goods_kind']	= $sc['goodsKind']	= 'coupon';
		}

		if	($this->m_nProviderSeq > 1)	$sc['provider_seq']	= $this->m_nProviderSeq;
		$this->goodsmodel->get_from_mode	= true;
		$sql		= $this->goodsmodel->admin_goods_list($sc);

		if	($params['excel_page']){
			$this->m_nExcelPage		= $params['excel_page'];
			$this->m_bZipDown		= true;
			$this->m_nTotalCount	= $params['excel_totalCount'];
			$this->m_nTotalPage		= $params['excel_totalPage'];
			$sLimit					= ($params['excel_page'] - 1) * $this->m_nMaxRow;
			$query					= $this->db->query("select C.* " . $sql . " LIMIT " . $sLimit . ", " . $this->m_nMaxRow);
			$result					= $query->result_array();
		}else{
			$countSql				= "select count(*) cnt " . $sql;
			$countQuery				= $this->db->query($countSql);
			$countData				= $countQuery->row_array();
			$totalCnt				= $countData['cnt'];

			// 최대값 초과 시 파일을 분할하기 위해 페이징 처리함.
			if	($totalCnt > $this->m_nMaxRow){
				$this->m_nExcelPage		= 1;
				$this->m_bZipDown		= true;
				$this->m_nTotalCount	= $totalCnt;
				$this->m_nTotalPage		= ceil( $totalCnt / $this->m_nMaxRow );
				$query	= $this->db->query("select C.* " . $sql . " LIMIT 0, " . $this->m_nMaxRow);
				$result	= $query->result_array();
			}else{
				$query	= $this->db->query("select C.* " . $sql);
				$result	= $query->result_array();
			}
		}

		return $result;
	}
	
	public function get_excel_options($goods_seq)
	{
		$returnData = array();
		$query = "seLECT
					option_title,
					GROUP_CONCAT(option_seq SEPARATOR '\n') as option_seq,
					GROUP_CONCAT(option1 SEPARATOR '\n') as option1,
					GROUP_CONCAT(option2 SEPARATOR '\n') as option2,
					GROUP_CONCAT(option3 SEPARATOR '\n') as option3,
					GROUP_CONCAT(option4 SEPARATOR '\n') as option4,
					GROUP_CONCAT(option5 SEPARATOR '\n') as option5,
					GROUP_CONCAT(optioncode1 SEPARATOR '\n') as optioncode1,
					GROUP_CONCAT(optioncode2 SEPARATOR '\n') as optioncode2,
					GROUP_CONCAT(optioncode3 SEPARATOR '\n') as optioncode3,
					GROUP_CONCAT(optioncode4 SEPARATOR '\n') as optioncode4,
					GROUP_CONCAT(optioncode5 SEPARATOR '\n') as optioncode5,
					GROUP_CONCAT(consumer_price SEPARATOR '\n') as consumer_price,
					GROUP_CONCAT(price SEPARATOR '\n') as price
				fROM fm_goods_option
				wHERE goods_seq = ?
				GROUP BY goods_seq";
		$result = $this->db->query($query, $goods_seq)->result_array();
		$returnData = $result[0];
		
		$option_titles = explode(',', $result[0]['option_title']);
		
		$key = 1;
		foreach ($option_titles as $option_title) {
			$returnData['option_title'.$key] = $option_title;
			$key++;
		}
		unset($returnData['option_title']);
		
		return $returnData;
	}

	// 양식에 따른 추가 정보 추출
	public function get_excel_add_goods($goods_seq, $cellArr){

		$return				= array();
		if	(is_array($cellArr)) foreach( $cellArr as $k => $cellCode){
			// 예외처리 컬럼일 경우
			$exept	= $this->get_except_cell_list($cellCode);
			if	($exept){
				$exceptArr[$exept]	= 1;
			}else{
				if	($this->m_aTableInfo[$cellCode] && $this->m_aFieldInfo[$cellCode] && $this->m_aTableInfo[$cellCode] != 'fm_goods')
					$dbArr[$this->m_aTableInfo[$cellCode]][]	= $this->m_aFieldInfo[$cellCode];
			}
		}

		// 기본 추가정보 추출 쿼리 ( 1:1 구조인 것들 현재는 없음... )
		if	($dbArr) foreach( $dbArr as $tb => $fld){
			$sql			= "select " . implode(", ", $fld) . " from " . $tb . " where goods_seq = '" . $goods_seq . "' ";
			$query			= $this->db->query($sql);
			$data			= $query->result_array();

			$return[$tb]	= $data;
		}

		// 예외처리 데이터 추출 ( 1:N 구조로 줄바꿈 처리가 필요한 데이터 )
		if	($exceptArr) foreach( $exceptArr as $type => $s){
			$except		= '_download_exception_' . $type;
			$data		= $this->$except($goods_seq);
			if	(is_array($data))	$return			= array_merge($return, $data);
			else					$return[$type]	= $data;
		}

		return $return;	// 결과데이터 return;
	}

	// 필수옵션 데이터 추출
	public function _download_exception_option($goods_seq){

		// 옵션 정보 추출
		$sql	= "select * from
					fm_goods_option	as opt, fm_goods_supply as sup
					where opt.option_seq = sup.option_seq
					and opt.goods_seq = '".$goods_seq."' and sup.goods_seq = '".$goods_seq."'";
		$query	= $this->db->query($sql);
		$result	= $query->result_array();
		if($result)foreach($result as $k => $opt){

			if($opt['commission_type'] == 'SACO' || $opt['commission_type'] == ''){
				$opt['commission_price']		= '';
			}else{
				$opt['commission_price']		= $opt['commission_rate'];
				$opt['commission_rate']			= '';
			}

			if	($k == 0){
				if	($opt['newtype'])
					$newType		= explode(',', $opt['newtype']);
				if	($opt['option_title']){
					$option_title	= explode(',', $opt['option_title']);
					$return['option_title1']	= $this->download_special_option_name($newType[0]).$option_title[0];
					$return['option_title2']	= $this->download_special_option_name($newType[1]).$option_title[1];
					$return['option_title3']	= $this->download_special_option_name($newType[2]).$option_title[2];
					$return['option_title4']	= $this->download_special_option_name($newType[3]).$option_title[3];
					$return['option_title5']	= $this->download_special_option_name($newType[4]).$option_title[4];
				}
			}

			// 옵션 동작구분 자동 추가
			if	($opt['option_seq'] > 0)	$opt['action_option_kind']	= '수정';
			else							$opt['action_option_kind']	= '추가';

			foreach($this->m_aOptionMultiRowCell as $c => $fld){
				switch($fld){
					case	'reserve_rate' :
						if ($this->m_sReservePolicy == 'shop') {
							// 통합마일리지 정책
							$val = $this->reserves['default_reserve_percent'] . '%';
						} else {
							// 개별마일리지 정책
							$reserve_unit = '';
							if ($opt['reserve_unit'] == 'percent') {
								$reserve_unit = '%';
							}
							$val = $opt[$fld] . $reserve_unit;
						}
						break;
					case	'commission_rate' :
						if($opt[$fld])	$val	= $opt[$fld] . '%';
						else			$val	= '';
						break;
					case	'commission_price' :
						if($opt[$fld])	$val	= ($opt['commission_type'] == 'SUPR') ? $opt[$fld].'원' : $opt[$fld].'%';
						else			$val	= '';
						break;
					case	'supply_price' :
						$val	= ($opt['supply_price'] > 0) ? $opt['supply_price'] : 0;
						break;
					case	'option_view' :
						$val	= ($opt['option_view'] == 'Y') ? "노출" : "미노출";
						break;
					default :
						$val	= $opt[$fld];
						break;
				}

				if	(preg_match('/^option[0-5]$/', $fld)){
					$optNum	= substr($fld, - 1) - 1;
					if	($newType[$optNum])
						$val	.= $this->get_string_special_option($newType[$optNum], $opt);
				}

				if	(in_array($fld, $this->m_aNeedZeroVal) && !$val)	$val	= '0';

				if	(isset($return[$fld])){
					$return[$fld]	.= $this->m_sDownloadLineString . $val;
				}else{
					$return[$fld]	= $val;
				}

			}
		}

		return $return;
	}

	// 추가옵션 데이터 추출
	public function _download_exception_suboption($goods_seq){

		// 티켓상품은 추가옵션이 없다.
		if	($this->m_sGoodsKind == 'C')	return array();

		// 옵션 정보 추출
		$sql	= "select * from
					fm_goods_suboption	as sub, fm_goods_supply as sup
					where sub.suboption_seq = sup.suboption_seq
					and sub.goods_seq = '".$goods_seq."' ";
		$query	= $this->db->query($sql);
		$result	= $query->result_array();
		if($result)foreach($result as $k => $sub){
			if	($sub['sub_required'] == 'y')	$required[$sub['suboption_title']]	= 1;
			if	($sub['sub_sale'] == 'y')		$sale[$sub['suboption_title']]		= 1;

			if($sub['commission_type'] == 'SACO' || $sub['commission_type'] == ''){
				$sub['sub_commission_rate']		= $sub['commission_rate'];
				$sub['sub_commission_price']	= '';
			}else{
				$sub['sub_commission_price']	= $sub['commission_rate'];
				$sub['sub_commission_rate']		= '';
			}

			// 옵션 동작구분 자동 추가
			if	($sub['suboption_seq'] > 0)	$sub['action_suboption_kind']	= '수정';
			else							$sub['action_suboption_kind']	= '추가';

			foreach($this->m_aSuboptionMultiRowCell as $c => $fld){
				switch($fld){
					case	'sub_reserve_rate' :
						if ($this->m_sOptionReservePolicy == 'shop') {
							// 통합 마일리지 정책
							$val = $this->reserves['default_reserve_percent'] . '%';
						} else {
							// 개별 마일리지 정책
							$reserve_unit = '';
							if ($sub['reserve_unit'] == 'percent') {
								$reserve_unit = '%';
							}
							$val = $sub['reserve_rate'] . $reserve_unit;
						}
					break;
					case	'sub_commission_rate' :
						if($sub[$fld])	$val	= $sub[$fld] . '%';
						else			$val	= '';
					break;
					case	'sub_commission_price' :
						if($sub[$fld])	$val	= ($sub['commission_type'] == 'SUPR') ? $sub[$fld].'원' : $sub[$fld].'%';
						else			$val	= '';
					break;
					case	'sub_stock' :
						$val	= $sub['stock'];
					break;
					case	'sub_badstock' :
						$val	= $sub['badstock'];
					break;
					case	'sub_safe_stock' :
						$val	= $sub['safe_stock'];
					break;
					case	'sub_weight' :
						$val	= $sub['weight'];
					break;
					case	'sub_option_view' :
						$val	= ($sub['option_view'] == 'Y')? '노출' : '미노출';
					break;
					case	'sub_supply_price' :
						$val	= $sub['supply_price'];
					break;
					case	'sub_consumer_price' :
						$val	= $sub['consumer_price'];
					break;
					case	'sub_price' :
						$val	= $sub['price'];
					break;
					default :
						$val	= $sub[$fld];
					break;
				}

				if		($fld == 'suboption_title')
					$val	= $this->download_special_option_name($sub['newtype']).$val;
				elseif	($fld == 'suboption')
					$val	.= $this->get_string_special_option($sub['newtype'], $sub);

				if	(in_array($fld, $this->m_aNeedZeroVal) && !$val)	$val	= '0';

				if	(isset($return[$fld]))	$return[$fld]	.= $this->m_sDownloadLineString . $val;
				else						$return[$fld]	= $val;
			}
		}

		// 필수여부
		if	($required)	$return['sub_required']	= implode('^', array_keys($required));
		// 추가혜택
		if	($sale)		$return['sub_sale']		= implode('^', array_keys($sale));

		return $return;
	}

	// 입력옵션 데이터 추출
	public function _download_exception_input($goods_seq){

		// 입력옵션 종류 한글명
		$formArr		= array('text'=>'텍스트', 'edit'=>'에디터','file'=>'이미지');

		// 옵션 정보 추출
		$sql	= "select * from fm_goods_input where goods_seq = '".$goods_seq."' ";
		$query	= $this->db->query($sql);
		$result	= $query->result_array();
		if($result)foreach($result as $k => $input){
			if	($input['input_require'])	$required[$input['input_name']]	= 1;

			foreach($this->m_aInputMultiRowCell as $c => $fld){
				if	($fld == 'input_form')
					$val	= $formArr[$input[$fld]] . (($input['input_limit'] > 0) ? '^' . $input['input_limit'] : '');
				else
					$val	= $input[$fld];

				if	($return[$fld])	$return[$fld]	.= $this->m_sDownloadLineString . $val;
				else				$return[$fld]	= $val;
			}
		}

		// 필수여부
		if	($required)	$return['input_require']	= implode('^', array_keys($required));

		return $return;
	}

	// 카테고리 데이터 추출
	public function _download_exception_category($goods_seq){
		$sql		= "select * from fm_category_link where goods_seq = '".$goods_seq."' order by link";
		$query		= $this->db->query($sql);
		$categorys	= $query->result_array();
		if	($categorys)foreach($categorys as $c => $category){
			if	($c > 0)	$return	.= $this->m_sDownloadLineString . $category['category_code'];
			else			$return	= $category['category_code'];
		}

		return $return;
	}

	// 카테고리 데이터 추출 주머니
	public function _download_exception_category_title($goods_seq){
		$sql	= "select cate.category_code, cate.title from fm_category_link as link inner join fm_category as cate on link.category_code=cate.category_code where goods_seq = ? order by link";
		$query	= $this->db->query($sql, $goods_seq);
		$categorys	= $query->result_array();
		if ($categorys)foreach($categorys as $c => $category){
			if(strlen($category['category_code']) <= 4){
				$return	.= "\n" . $category['title'];
			} else {
				$return	.= "/" . $category['title'];
			}
		}
		
		return $return;
	}
	
	// 브랜드 데이터 추출
	public function _download_exception_brand($goods_seq){
		$sql		= "select * from fm_brand_link where goods_seq = '".$goods_seq."' order by link";
		$query		= $this->db->query($sql);
		$brands		= $query->result_array();
		if	($brands)foreach($brands as $b => $brand){
			if	($b > 0)	$return	.= $this->m_sDownloadLineString . $brand['category_code'];
			else			$return	= $brand['category_code'];
		}

		return $return;
	}

	public function _download_exception_brand_title($goods_seq){
		$sql		= "select brand.category_code, brand.title from fm_brand_link as link inner join fm_brand as brand on link.category_code=brand.category_code where goods_seq = ? order by link";
		$query		= $this->db->query($sql, $goods_seq);
		$brands	= $query->result_array();
		if ($brands)foreach($brands as $c => $brand){
			if(strlen($brand['category_code']) <= 4){
				$return	.= "\n" . $brand['title'];
			} else {
				$return	.= "/" . $brand['title'];
			}
		}
		
		return $return;
	}
	
	// 지역 데이터 추출
	public function _download_exception_location($goods_seq){
		$sql		= "select * from fm_location_link where goods_seq = '".$goods_seq."' order by link";
		$query		= $this->db->query($sql);
		$locations	= $query->result_array();
		if	($locations)foreach($locations as $l => $location){
			if	($l > 0)	$return	.= $this->m_sDownloadLineString . $location['location_code'];
			else			$return	= $location['location_code'];
		}

		return $return;
	}

	// 추가정보 데이터 추출
	public function _download_exception_addition($goods_seq){
		// 미리 정의된 추가정보명
		$typeArr	= array('model'			=> '모델명',
							'brand'			=> '브랜드',
							'manufacture'	=> '제조사',
							'orgin'			=> '원산지');

		$sql		= "select * from fm_goods_addition where goods_seq = '".$goods_seq."' ";
		$query		= $this->db->query($sql);
		$additions	= $query->result_array();
		if	($additions)foreach($additions as $l => $addition){
			if ( $typeArr[$addition['type']] ) {
				$addMsg	= '[' . $typeArr[$addition['type']] . ']' . '=' . $addition['contents'];
			} elseif( strstr($addition['type'],"goodsaddinfo_") ) {//설정>상품코드 이용시
				$addMsg	= '[상품코드]'.$addition['code_seq'].'=' .$addition['title'] . '=' . $addition['contents'] . '=' . $addition['contents_title'];
			} elseif(isset($addition['title']) && isset($addition['contents'])) {
				$addMsg	= $addition['title'] . '=' . $addition['contents'];
			} else {
				$addMsg = '';
			}

			if	($l > 0)	$return	.= '^' . $addMsg;
			else			$return	= $addMsg;
		}

		return $return;
	}

	// 아이콘 데이터 추출
	public function _download_exception_icon($goods_seq){
		$sql		= "select * from fm_goods_icon where goods_seq = '".$goods_seq."' ";
		$query		= $this->db->query($sql);
		$icons		= $query->result_array();
		if	($icons)foreach($icons as $i => $icon){
			if	(!$icon['start_date'])	$icon['start_date']	= '0000-00-00';
			if	(!$icon['end_date'])	$icon['end_date']	= '0000-00-00';
			$iconMsg	= $icon['codecd'] . '=' . $icon['start_date'] . '~' . $icon['end_date'];

			if	($i > 0)	$return	.= $this->m_sDownloadLineString . $iconMsg;
			else			$return	= $iconMsg;
		}

		return $return;
	}

	// 이미지 데이터 추출
	public function _download_exception_image($goods_seq){
		$sql		= "select * from fm_goods_image where goods_seq = '".$goods_seq."'
						order by cut_number asc ";
		$query		= $this->db->query($sql);
		$images		= $query->result_array();
		if	($images)foreach($images as $i => $image){
			if	($return[$image['image_type']])
				$return[$image['image_type']]	.= $this->m_sDownloadLineString . $image['image'];
			else
				$return[$image['image_type']]	= $image['image'];
		}

		return $return;
	}

	// 관련상품 데이터 추출
	public function _download_exception_relation($goods_seq){
		$sql		= "select * from fm_goods_relation where goods_seq = '".$goods_seq."' ";
		$query		= $this->db->query($sql);
		$relations	= $query->result_array();
		if	($relations)foreach($relations as $r => $relation){
			if	($r > 0)	$return	.= "^" . $relation['relation_goods_seq'];
			else			$return	= $relation['relation_goods_seq'];
		}

		return $return;
	}

	// 판매자 관련상품 데이터 추출
	public function _download_exception_relation_seller($goods_seq){
		$sql		= "select * from fm_goods_relation_seller where goods_seq = '".$goods_seq."' ";
		$query		= $this->db->query($sql);
		$relations	= $query->result_array();
		if	($relations)foreach($relations as $r => $relation){
			if	($r > 0)	$return	.= "^" . $relation['relation_goods_seq'];
			else			$return	= $relation['relation_goods_seq'];
		}

		return $return;
	}

	// 기초재고 관련 부분은 download 시는 데이터가 없다.
	public function _download_exception_warehouse($goods_seq){

		$return['default_date']	= '재고기초>절사/환율,기초일자 메뉴에서 기초재고 기초일자 설정 필요';
		if	($this->scm_cfg['set_default_date'])
			$return['default_date']	= $this->scm_cfg['set_default_date'];

		return $return;
	}

	// 특수옵션 문자열 생성
	public function get_string_special_option($type, $opt){
		switch($type){
			case 'color':
				$return		= '=' . $opt['color'];
			break;
			case 'address':
				if	($opt['address_type'] == 'street')	$return		= '=도로명|';
				else									$return		= '=지번|';
				$return		.= $opt['zipcode'] . '|';
				$return		.= $opt['address'] . '|';
				if	($opt['address_type'] == 'street')	$return		.= $opt['address_street'];
				else									$return		.= $opt['addressdetail'];
				$return		.= '=' . $opt['address_commission'] . '%';
			break;
			case 'date':
				$return		= '=' . $opt['codedate'];
			break;
			case 'dayinput':
				$return		= '=' . $opt['sdayinput'] . '~' . $opt['fdayinput'];
			break;
			case 'dayauto':
				if		($opt['dayauto_type'] == 'month'){
					// A : 결제확인 후 해당월 15일부터 30일동안
					// B : 결제확인 후 해당월 15일부터 30일이 되는 월말
					if		($opt['dayauto_day'] == 'day')	$autoType	= 'A';
					else									$autoType	= 'B';
				}elseif	($opt['dayauto_type'] == 'day'){
					// C : 결제확인 후 해당일 15일부터 30일동안
					// D : 결제확인 후 해당일 15일부터 30일이 되는 월말
					if		($opt['dayauto_day'] == 'day')	$autoType	= 'C';
					else									$autoType	= 'D';
				}elseif	($opt['dayauto_type'] == 'next'){
					// E : 결제확인 후 익월 15일부터 30일동안
					// F : 결제확인 후 익월 15일부터 30일이 되는 월말
					if		($opt['dayauto_day'] == 'day')	$autoType	= 'E';
					else									$autoType	= 'F';
				}

				$return		= '=' . $opt['sdayauto'] . '+' . $opt['fdayauto'] . '=' . $autoType;
			break;
		}

		return $return;
	}

	// 상품 테이블 다운로드 예외처리
	public function get_download_goods_change_val($fld, &$goods){

		switch($fld){
			case 'provider_status':
				if	($goods['provider_status'] == '1'){
					$goods['provider_status']	= '승인';
				}else{
					$goods['goods_status']		= '판매중지';
					$goods['goods_view']		= '미노출';
					$goods['provider_status']	= '미승인';
				}
			break;
			case 'goods_status':
				if		($goods['goods_status'] == 'normal')	$goods['goods_status']	= '정상';
				elseif	($goods['goods_status'] == 'runout')	$goods['goods_status']	= '품절';
				elseif	($goods['goods_status'] == 'purchasing')	$goods['goods_status']	= '재입고';
				else											$goods['goods_status']	= '판매중지';
			break;
			case 'goods_view':
				if	($goods['display_terms'] == 'AUTO'){
					$goods['goods_view']		= '노출^';
					if	($goods['display_terms_before'] == 'CONCEAL'){
						$goods['goods_view']	= '미노출^';
					}
					$goods['goods_view']		.= date('Ymd', strtotime($goods['display_terms_begin'])) . '-'
												. date('Ymd', strtotime($goods['display_terms_end'])) . '=';
					if	($goods['display_terms_type'] == 'LAYAWAY')
						$goods['goods_view']	.= 'Y=';
					else
						$goods['goods_view']	.= 'N=';
					$goods['goods_view']		.= $goods['display_terms_text'] . '=';
					$goods['goods_view']		.= date('Ymd', strtotime($goods['possible_shipping_date'])) . '=';
					$goods['goods_view']		.= $goods['possible_shipping_text'] . '^';
					if	($goods['display_terms_after'] == 'CONCEAL'){
						$goods['goods_view']	.= '미노출';
					}else{
						$goods['goods_view']	.= '노출';
					}
				}else{
					if	($goods['goods_view'] == 'notLook')		$goods['goods_view']		= '미노출';
					else										$goods['goods_view']		= '노출';
				}
			break;
			case 'runout_policy':
				if	($goods['runout_policy']){
					switch($goods['runout_policy']){
						case 'stock':
							$goods['runout_policy']	= '개별정책=재고연동';
						break;
						case 'ableStock':
							$goods['runout_policy']	= '개별정책=가용재고^' . $goods['able_stock_limit'];
						break;
						case 'unlimited':
							$goods['runout_policy']	= '개별정책=재고무관';
						break;
						default:
							$goods['runout_policy']	= '개별정책';
						break;
					}
				}else{
					$goods['runout_policy']	= '통합정책';
				}
			break;
			case 'cancel_type':
				if	($goods['cancel_type'] == '1')			$goods['cancel_type']		= '예';
				else										$goods['cancel_type']		= '아니요';
			break;
			case 'reserve_policy':
				if	($goods['reserve_policy'] == 'goods')	$goods['reserve_policy']	= '개별';
				else										$goods['reserve_policy']	= '통합';
			break;
			case 'tax':
				if	($goods['tax'] == 'exempt')				$goods['tax']				= '비과세';
				else										$goods['tax']				= '과세';
			break;
			case 'adult_goods':
				if	($goods['adult_goods'] == 'Y')			$goods['adult_goods']		= '예';
				else										$goods['adult_goods']		= '아니요';
			break;
			case 'option_view_type':
				if	($goods['option_view_type'] == 'join')	$goods['option_view_type']	= '합체형';
				else										$goods['option_view_type']	= '분리형';
			break;
			case 'feed_status':
				if	($goods['feed_status'] == 'Y')			$goods['feed_status']		= '예';
				else										$goods['feed_status']		= '아니요';
			break;
			case 'keyword':		// 검색어 그대로 저장 2018-08-07 #19650
				$goods['keyword']	= str_replace(',', '^', $goods['keyword']);
			break;
			case 'openmarket_keyword':
				$goods['openmarket_keyword']	= str_replace(',', '^', $goods['openmarket_keyword']);
			break;
			case 'sub_info_desc':
				if	(isset($goods['goods_sub_info']) && $goods['sub_info_desc']){
					$goods['goods_sub_info']	= $goods['goods_sub_info'];
					$tmp1		= json_decode($goods['sub_info_desc']);
					if	($tmp1)foreach($tmp1 as $k => $v){
						if	($x > 0)	$sub_info_desc	.= '^';
						$sub_info_desc		.= $k.'='.$v;
						$x++;
					}
					$goods['sub_info_desc']		= $sub_info_desc;
				}
			break;
			case 'multi_discount_policy':
				if	($goods['multi_discount_policy']){
					$md_policy		= json_decode($goods['multi_discount_policy'], true);
					if ($md_policy['discountUnit'] == 'PER') {
						$md_unit	= '%';
					} else {
						$md_unit	= '원';
					}
					
					if ($md_policy['policyList']){
						foreach($md_policy['policyList'] as $k => $tmp){
							$mdStr	.= $tmp['discountOverQty'] . '^' . $tmp['discountAmount'] . $md_unit . $this->m_sDownloadLineString;
						}
					}
					
					if($md_policy['discountMaxOverQty'] > 0) {
						$mdStr		.= $md_policy['discountMaxOverQty'] . '^' . $md_policy['discountMaxAmount'] . $md_unit;
					}
					
					$goods['multi_discount_policy'] = $mdStr;
				}else{
					$goods['multi_discount_policy'] = '';
				}
			break;
			case 'min_purchase_ea':
				if	($goods['min_purchase_limit'] == 'limit'){
					$goods['min_purchase_ea']	= $goods['min_purchase_ea'];
				}
			break;
			case 'max_purchase_ea':
				if	($goods['max_purchase_limit'] == 'limit'){
					$goods['max_purchase_ea']	= $goods['max_purchase_ea'];
				}
			break;
			case 'string_price':
				if	($goods['string_price_use'] == '1'){
					$goods['string_price']	= $goods['string_price'];
					if		($goods['string_price_link'] == 'login')
						$goods['string_price']	.= '^로그인';
					elseif	($goods['string_price_link'] == '1:1')
						$goods['string_price']	.= '^1:1문의';
					elseif	($goods['string_price_link'] == 'direct')
						$goods['string_price']	.= '^직접입력^'.$goods['string_price_link_url'];
				}
			break;
			case 'string_button':
				if	($goods['string_button_use'] == '1'){
					$goods['string_button']	= $goods['string_button'];
					if		($goods['string_button_link'] == 'login')
						$goods['string_button']	.= '^로그인';
					elseif	($goods['string_button_link'] == '1:1')
						$goods['string_button']	.= '^1:1문의';
					elseif	($goods['string_button_link'] == 'direct')
						$goods['string_button']	.= '^직접입력^'.$goods['string_button_link_url'];
				}
			break;
			case 'member_string_price':
				if	($goods['member_string_price_use'] == '1'){
					$goods['member_string_price']	= $goods['member_string_price'];
					if		($goods['member_string_price_link'] == 'login')
						$goods['member_string_price']	.= '^로그인';
					elseif	($goods['member_string_price_link'] == '1:1')
						$goods['member_string_price']	.= '^1:1문의';
					elseif	($goods['member_string_price_link'] == 'direct')
						$goods['member_string_price']	.= '^직접입력^'.$goods['member_string_price_link_url'];
				}
			break;
			case 'member_string_button':
				if	($goods['member_string_button_use'] == '1'){
					$goods['member_string_button']	= $goods['member_string_button'];
					if		($goods['member_string_button_link'] == 'login')
						$goods['member_string_button']	.= '^로그인';
					elseif	($goods['member_string_button_link'] == '1:1')
						$goods['member_string_button']	.= '^1:1문의';
					elseif	($goods['member_string_button_link'] == 'direct')
						$goods['member_string_button']	.= '^직접입력^'.$goods['member_string_button_link_url'];
				}
			break;
			case 'allmember_string_price':
				if	($goods['allmember_string_price_use'] == '1'){
					$goods['allmember_string_price']	= $goods['allmember_string_price'];
					if		($goods['allmember_string_price_link'] == 'login')
						$goods['allmember_string_price']	.= '^로그인';
					elseif	($goods['allmember_string_price_link'] == '1:1')
						$goods['allmember_string_price']	.= '^1:1문의';
					elseif	($goods['allmember_string_price_link'] == 'direct')
						$goods['allmember_string_price']	.= '^직접입력^'.$goods['allmember_string_price_link_url'];
				}
			break;
			case 'allmember_string_button':
				if	($goods['allmember_string_button_use'] == '1'){
					$goods['allmember_string_button']	= $goods['allmember_string_button'];
					if		($goods['allmember_string_button_link'] == 'login')
						$goods['allmember_string_button']	.= '^로그인';
					elseif	($goods['allmember_string_button_link'] == '1:1')
						$goods['allmember_string_button']	.= '^1:1문의';
					elseif	($goods['allmember_string_button_link'] == 'direct')
						$goods['allmember_string_button']	.= '^직접입력^'.$goods['allmember_string_button_link_url'];
				}
			break;
			case 'relation_image_size':
				$pat1			= array_keys($this->m_aImageName);
				$pat2			= array_values($this->m_aImageName);
				$image_size		= str_replace($pat1, $pat2, $goods['relation_image_size']);

				$goods['relation_image_size']	= $goods['relation_count_w'] . 'x'
												. $goods['relation_count_h'] . '=' . $image_size;
			break;
			case 'feed_evt_text':
				if	($goods['feed_evt_text'] && $goods['feed_evt_sdate'] && $goods['feed_evt_edate']){
					$goods['feed_evt_text']	.= '='
											. date('Ymd', strtotime($goods['feed_evt_sdate']))
											. '-'
											. date('Ymd', strtotime($goods['feed_evt_edate']));
				}
 			break;
			case 'option_international_shipping_status':
				if	($goods['option_international_shipping_status'] == 'y')	$goods['option_international_shipping_status']		= '예';
				else														$goods['option_international_shipping_status']		= '아니요';
			break;
			case 'suboption_layout_group':
				if($goods['option_suboption_use'] == '1'){
					$goods['suboption_layout_group']	= ($goods['suboption_layout_group'] == 'first') ? '첫번째필수옵션' : '필수옵션과쌍';
				}else{
					$goods['suboption_layout_group']	= '';
				}
			break;
			case 'inputoption_layout_position':
				if($goods['member_input_use'] == '1'){
					$goods['inputoption_layout_position']	= ($goods['inputoption_layout_position'] == 'down') ? '선택된옵션영역' : '옵션선택영역';
				}else{
					$goods['inputoption_layout_position']	= '';
				}
			break;
			case 'purchase_goods_name':
				if	($this->m_bIsScm){
					$scmDefaultInfo			= $this->scmmodel->get_default_supply_goods_info($goods['goods_seq']);
					if	($scmDefaultInfo['trader_name'] && $scmDefaultInfo['supply_goods_name']){
						$goods['purchase_goods_name']	= $scmDefaultInfo['supply_goods_name'];
					}
				}
			break;
			case 'feed_ship_type':
				if		($goods['feed_ship_type'] == 'E'){
					$goods['feed_ship_type']		= '착불';
					if		($goods['feed_pay_type'] == 'fixed'){
						$goods['feed_ship_type']	= $goods['feed_std_fixed'] . '^'
													. $goods['feed_add_txt'];
					}elseif	($goods['feed_pay_type'] == 'free'){
						$goods['feed_ship_type']	= '무료^' . $goods['feed_add_txt'];
					}
				}elseif	($goods['feed_ship_type'] == 'S'){
					$goods['feed_ship_type']		= '통합설정';
				}else{
					$goods['feed_ship_type']		= '설정된배송그룹';
				}
			break;
			case 'present_use':		// 선물하기
				if	($goods['present_use'] == '1')			$goods['present_use']		= 'Y';
				else										$goods['present_use']		= 'N';
			break;			
		}
	}

	// 특수옵션 명칭 정의
	public function download_special_option_name($type){
		switch($type){
			case 'color':		return '[색상]';		break;
			case 'address':		return '[지역]';		break;
			case 'date':		return '[날짜]';		break;
			case 'dayinput':	return '[수동기간]';	break;
			case 'dayauto':		return '[자동기간]';	break;
		}

		return '';
	}

	// 임시 다운로드 엑셀파일 삭제
	public function delete_download_excel_file(){

		$today		= strtotime(date('Y-m-d') . ' 00:00:00');
		$dir		= opendir($this->m_sExcelDownloadFilePath);
		while($file = readdir($dir)){
			if	(preg_match('/^download\_goods\_excel\_/', $file)){
				$datetime	= strtotime(substr(str_replace('download_goods_excel_', '', $file), 0, 14));

				if	($datetime < $today){
					unlink($this->m_sExcelDownloadFilePath . '/' . $file);
				}
			}
		}
	}

	########## ↑↑↑↑↑ 엑셀 다운로드 ↑↑↑↑↑ ##########


	########## ↓↓↓↓↓ 엑셀 업로드 ↓↓↓↓↓ ##########

	// 엑셀 업로드 ( 상품 등록/수정 )
	public function excel_upload($filename, $filedata){

		// 파일 업로드
		$upload_result	= $this->excel_file_upload($filename, $filedata);
		if	(!$upload_result['status']){
			return $upload_result;
		}

		try {
			set_time_limit(0);
			ini_set('memory_limit', '3500M');

			$this->load->model('goodsmodel');
			$this->load->library('PHPExcel');
			$this->load->library('PHPExcel/IOFactory');
			$this->PHPExcel				= new PHPExcel();
			$this->IOFactory			= new IOFactory();

			// 업로드 된 엑셀 형식에 맞는 Reader객체를 만든다.
			$objReader	= $this->IOFactory->createReaderForFile($upload_result['file']);
			// 읽기전용으로 설정
			$objReader->setReadDataOnly(true);
			// 엑셀파일을 읽는다
			$objExcel = $objReader->load($upload_result['file']);
			// 첫번째 시트를 선택
			$objExcel->setActiveSheetIndex(0);

			$this->workSheet	= $objExcel->getActiveSheet();
			$rowIterator		= $this->workSheet->getRowIterator();
			foreach ($rowIterator as $row) { // 모든 행에 대해서
				$cellIterator = $row->getCellIterator();
				$cellIterator->setIterateOnlyExistingCells(false);
			}
			$maxRow			= $this->workSheet->getHighestRow();
			if	($this->m_nMaxRow < ($maxRow - 1)){
				return array('status' => false, 'msg' => '최대 ' . $this->m_nMaxRow . '개까지 등록 가능합니다.');
			}

			// 필수열 체크 및 loop용 배열 가공
			$chkArr			= $this->get_cell_array_to_check_upload();
			$goods			= $chkArr['goods'];
			$tableArr		= $chkArr['tableArr'];
			$exceptArr		= $chkArr['exceptArr'];

			// fm_goods는 goods_seq 필수열이 포함되어 있기에 반드시 있어야 함.
			if	(!$goods){
				return array('status' => false, 'msg' => '필수 열이 없습니다. 필수 열을 확인해 주십시오.');
			}
			$goods_seq_cell		= array_search('goods_seq', $goods);

			// 노출여부 변경시 카테고리별 상품수 업데이트를 위한 변수 정의
			if	(in_array('goods_view', $goods)) {
				$today				= date("Y-m-d H:i:s");
				$goods_view_cell	= array_search('goods_view', $goods);
				for	( $r = 2; $r <= $maxRow; $r++){
					$goods_seq	= $this->workSheet->getCell($goods_seq_cell.$r)->getValue();
					$goods_view	= $this->workSheet->getCell($goods_view_cell.$r)->getValue();
					if( $goods_view && $goods_seq > 0 ){
						$goods_view_code	= 'notLook';
						if( $goods_view == '노출')	$goods_view_code	= 'look';
						$goodsOld	= $this->goodsmodel->get_view($goods_seq)->row_array();
						if($goodsOld['goods_view']!='look' && $goodsOld['display_terms']=='AUTO' && $goodsOld['display_terms_begin']<=$today && $goodsOld['display_terms_end']>=$today )	$goodsOld['goods_view']	= 'look';
						if($goodsOld['goods_view'] && $goods_view_code!=$goodsOld['goods_view'])	$this->m_aGoodsView[$goods_seq]	= $goods_view_code;
					}
				}
			}

			// 데이터 등록/수정
			for	( $r = 2; $r <= $maxRow; $r++){
				// 상품 등록 여부를 체크할 사전 체크 함수
				$chkResult	= $this->get_row_data_upload_check($r, $goods, $exceptArr);
				if	($chkResult['status'] === true){
					// 상품 기본정보 등록/수정 ( 필수열이 포함되어 없을 수 없음 )
					unset($goods_seq);
					if	($goods){
						$goods_seq		= $this->save_goods_data($r, $this->workSheet->getCell($goods_seq_cell.$r)->getValue(), $goods);
					}

					if	($goods_seq > 0){
						$goodsArr[]	= $goods_seq;
						$this->save_table_data($r, $goods_seq, $tableArr);
						$this->save_except_data($r, $goods_seq, $exceptArr);
		
						/**
						* - 상품 기본값 업데이트
						* - @2017-07-07
						**/
						$this->goodsmodel->default_price($goods_seq);

						// 결과 사용자 정의 함수 호출
						if	(function_exists($this->m_sAfterTreatmentFunc))
							call_user_func($this->m_sAfterTreatmentFunc, $goods_seq);
					}
				}else{
					$this->save_upload_log('failed', $chkResult['msg'] . "\r\n");
				}
			}
			
			$this->close_upload_log();
			
			// 결과 사용자 정의 함수 호출
			if	(function_exists($this->m_sAfterTreatmentFuncEnd))
				call_user_func($this->m_sAfterTreatmentFuncEnd, $goodsArr);

			// 엑셀 업로드 시 오픈마켓 연동 추가
			// 수정일때만 $goodsSeq 가 생성됨
			if(count($goodsArr)>0){
				$this->load->library('Connector');
				$goodsService	= $this->connector::getInstance('goods');
				foreach($goodsArr as $goodsSeq){
					$goodsService->doMarketGoodsUpdate($goodsSeq);	//Queue 로 처리
				}
				
				// 오픈마켓 상품 여부 확인
				$this->load->model('connectormodel');
				$marketParams						= array();
				$marketParams['fmGoodsSeqArr']			= $goodsArr;
				$marketParams['manualMatched']		= 'N';
				$marketProductList	= $this->connectormodel->getMarketProductList($marketParams);
				if(count($marketProductList)>0){
					$alertText = "<br/><br/>※ 오픈마켓 수정 결과는 <a href=\"/admin/market_connector/market_product_list\" target=\"_blank\">[오픈마켓>상품관리]</a>에서 확인하시기 바랍니다.";
				}
			}
			
			return array('status' => true, 'msg' => '처리완료 되었습니다.<br/>처리 로그를 확인해 주십시오.'.$alertText);

		}catch (exception $e) {
			return array('status' => false, 'msg' => '엑셀파일을 읽는도중 오류가 발생하였습니다.<br/><span style="font-size: 9pt; color:red;">필독! 엑셀파일 저장시 확장자가 XLS 인 엑셀 97~2003 양식으로 저장해 주세요.</span>');
		}
	}

	// 파일 업로드
	public function excel_file_upload($filename, $filedata, $fileexe = 'xls'){
		$this->load->library('upload');
		$fileinfo	= $filedata[$filename];
		if	(is_uploaded_file($fileinfo['tmp_name'])){
			$fileExt				= end(explode('.', $fileinfo['name']));
			$fileName				= 'upload_goods_excel_' . date('YmdHis') . rand(0,9999);
			$cfg['allowed_types']	= $fileexe;
			$cfg['file_name']		= $fileName;
			$cfg['upload_path']		= $this->m_sExcelUploadFilePath . '/';
			$cfg['overwrite']		= TRUE;
			$this->upload->initialize($cfg);
			if ($this->upload->do_upload($filename)) {
				$file_nm					= $cfg['upload_path'] . $cfg['file_name'] . '.' . $cfg['allowed_types'];
				@chmod($file_nm, 0777);
				$this->m_sUploadFileName	= $fileinfo['name'];
				$return						= array('status' => true, 'file' => $file_nm);
			}else{
				$err_msg			= $fileexe.' 파일만 가능합니다.'.$cfg['upload_path'];
				$return				= array('status' => false, 'msg' => $err_msg);
			}
		}else{
			$err_msg			= '파일을 등록해 주세요.';
			$return				= array('status' => false, 'msg' => $err_msg);
		}

		return $return;
	}

	// 업로드 시 필수값 체크 및 가공 배열 생성
	public function get_cell_array_to_check_upload(){

		$maxCol			= $this->workSheet->getHighestColumn();
		$maxColCnt		= $this->calculate_alphar_to_count($maxCol);
		$alpharArr		= $this->get_excel_cell_alphar($maxColCnt);

		// 타이틀 영역 추출해서 cellcode 배열 생성
		foreach($alpharArr as $k => $cell){
			$title		= $this->workSheet->getCell($cell.'1')->getValue();
			$cellCode	= array_search(str_replace("*","",$title), $this->m_aCellList);
			if	($cellCode){
				$cellCodeArr[$cell]	= $cellCode;

				// 테이블 별로 배열 분할
				$except								= $this->get_except_cell_list($cellCode);
				if	(!$except){
					if	($this->m_aTableInfo[$cellCode] == 'fm_goods')	$goods[$cell]										= $this->m_aFieldInfo[$cellCode];
					else												$tableArr[$this->m_aTableInfo[$cellCode]][$cell]	= $this->m_aFieldInfo[$cellCode];
				}else{
					$exceptArr[$except][$cell]		= $cellCode;
				}
			}
		}

		// 타이틀에 필수 cell이 있는지 체크
		foreach($this->m_aCellList as $code => $title){
			if	(substr($code, 2, 1) == 'R'){
				if	(!in_array($code, $cellCodeArr)){
					return array('status' => false, 'msg' => '필수열 '. $title . '이(가) 누락되었습니다.');
				}
			}

			// exception 배열
			$except		= $this->get_except_cell_list($code);
			if	($except)	$exceptAllArr[$except][]	= $code;
		}

		// 예외 처리여부 체크 ( image는 1개만 있어도 처리, 나머지는 1개라도 없으면 미처리 )
		if	($exceptAllArr)foreach($exceptAllArr as $except => $codeArr){
			if	(is_array($codeArr) && count($codeArr) > 0){
				$except_status	= 'y';
				if	($except == 'image')	$except_status	= 'n';
				foreach($codeArr as $k => $code){
					if	($except == 'image'){
						if	(in_array($code, $cellCodeArr)){
							$except_status	= 'y';
							break;
						}
					}else{
						//필수옵션동작구분/추가옵션동작구분 -> 프로그램단에서 자동처리되어 체크예외처리함 @2016-07-07
						$byPassExcept	= array('BBNNO1034','BGNNS2030');
						if	( !in_array($code,$byPassExcept) && !in_array($code, $cellCodeArr) ){
							$except_status	= 'n';
							$fail_code		= $code;
							break;
						}
					}
				}
				if	($except_status == 'n'){

					// 있는데 제거할 때 실패log 저장
					if	($exceptArr[$except]){
						$name	= $this->get_except_code_to_name($except);
						if	($except == 'image'){
							$this->save_upload_log('failed', '[' . $name . '/' . $except . '] 저장항목이 없어서 저장하지 않습니다.' . "\r\n");
						}else{
							$this->save_upload_log('failed', '[' . $name . '/' . $except . '] 필요항목(' . $this->m_aCellList[$fail_code] . ')이 부족하여 저장하지 않습니다.' . "\r\n");
						}
					}

					unset($exceptArr[$except]);
				}
			}
		}

		return array('goods' => $goods, 'tableArr' => $tableArr, 'exceptArr' => $exceptArr);
	}

	// 상품 등록 여부를 체크할 사전 체크 함수 ( 아래 체크 함수에서 fail일 경우 상품 등록/수정 자체를 안함 )
	// 현재 기초재고만 체크 ( 필요시 추가해서 사용 )
	public function get_row_data_upload_check($rowNum, $excelGoods, $exceptArr){

		$result		= array('status' => true);

		// 데이터 배열로 변환
		if	($excelGoods) foreach($excelGoods as $cell => $fld){
			$val				= $this->workSheet->getCell($cell.$rowNum)->getValue();
			$goodsParams[$fld]	= $val;
		}

		// 예외처리 데이터 체크
		if	($exceptArr)foreach($exceptArr as $type => $fldArr){
			if	($fldArr)foreach($fldArr as $cell => $code){
				$fldName	= $this->m_aFieldInfo[$code];
				$value		= $this->workSheet->getCell($cell.$rowNum)->getValue();
				if	($type == 'option' && in_array($fldName, $this->m_aOptionMultiRowCell)){
					$value	= explode(chr($this->m_sUploadLineCharString), $value);
				}
				$params[$type][$fldName]	= $value;
			}
		}
		if	($exceptArr)foreach($exceptArr as $type => $fldArr){
			//올인원이면서 입점상품일때 재고관리 예외처리 @2017-04-04
			if	($goodsParams['provider_id'] && $goodsParams['provider_id'] != 'base' && $type == 'warehouse' ) continue;
			$except		= 'chk_upload_exception_' . $type;
			if	(method_exists($this, $except)){
				$result		= $this->$except($goodsParams, $params['option'], $params[$type]);
				if	($result['status'] === false){
					$result['msg']	= $goodsParams['goods_name'] . ' ' . $result['msg'];
					return $result;
				}
			}
		}

		return $result;
	}

	// 기초재고 입력
	public function chk_upload_exception_warehouse($goodsData, $optionData, $data){
		$scm_use_revision		= explode(chr($this->m_sUploadLineCharString), $data['scm_use_revision']);
		$location_stock			= explode(chr($this->m_sUploadLineCharString), $data['location_stock']);
		$location_supply_price	= explode(chr($this->m_sUploadLineCharString), $data['location_supply_price']);
		$locationData			= '';
		$revisionSeq			= '';

		if	($scm_use_revision) foreach($scm_use_revision as $idx => $useVal){
			if	($useVal == 'Y'){
				if	(!$this->m_bIsScm){
					return array('status' => false, 'msg' => '등록 실패 : 기초재고는 올인원버전에서만 가용가능합니다.');
				}
				if	($optionData['action_option_kind'][$idx] != '추가' || $optionData['option_seq'][$idx] > 0){
					return array('status' => false, 'msg' => '등록 실패 : 기초재고는 옵션이 추가일 경우에만 가능합니다.');
				}
				if	(!$this->scm_cfg['set_default_date']){
					return array('status' => false, 'msg' => '등록 실패 : 재고기초>절사/환율,기초일자 메뉴에서 기초재고 기초일자 설정 필요.');
				}
				$tmp			= str_replace(' ', '', trim($location_stock[$idx]));
				$tmpList		= explode(',', $tmp);
				if	($tmpList) foreach($tmpList as $k => $linestr){
					$tmpData	= explode('=', $linestr);
					$wh_seq		= trim($tmpData[0]);
					$locArr		= explode('-', $tmpData[1]);
					$ea			= trim($tmpData[2]);
					$bad_ea		= trim($tmpData[3]);
					if	($ea > 0){
						if	($bad_ea > $ea){
							return array('status' => false, 'msg' => '등록 실패 : 기초재고로 등록할 불량재고가 재고수량 보다 적아야 합니다.');
						}
						if	($wh_seq > 0){
							if	(!$locationData[$wh_seq]){
								unset($sc);
								$sc['wh_seq']	= $wh_seq;
								$locationData[$wh_seq]	= $this->scmmodel->get_location($sc);
							}
							if	(!$locationData[$wh_seq]){
								return array('status' => false, 'msg' => '등록 실패 : 기초재고를 등록할 창고가 존재하지 않습니다.');
							}
						}else{
							return array('status' => false, 'msg' => '등록 실패 : 기초재고를 등록할 창고번호가 있어야 합니다.');
						}
					}else{
						return array('status' => false, 'msg' => '등록 실패 : 기초재고로 등록할 재고수량은 1이상이어야 합니다.');
					}
				}
			}
		}
		$this->m_aLocationList	= $locationData;

		return array('status' => true);
	}

	// fm_goods 상품 기본정보 저장
	public function save_goods_data($rowNum, $goods_seq, $excelGoods){
		// 데이터 배열로 변환

		if	($excelGoods) foreach($excelGoods as $cell => $fld){
			if	($fld != 'goods_seq'){
				$val				= $this->workSheet->getCell($cell.$rowNum)->getValue();

				# 모바일 상세설명 없을때 PC용상세설명으로 동일하게 입력 @2016-06-20 pjm
				if($fld == "mobile_contents" && trim($val) == ''){
					$val = $goodsParams['contents'];
				}

				$goodsParams[$fld]	= $val;
			}
		}
		// 데이터 치환처리
		if	($goodsParams) foreach($goodsParams as $fld => $val){
			if	($fld != 'provider_status'){
				$this->get_upload_goods_change_val($fld, $goodsParams);
			}
		}
		// 승인여부에 대한 처리는 마지막에 한다.
		if	($goodsParams['provider_status']){
			$this->get_upload_goods_change_val('provider_status', $goodsParams);
		}

		$goods_name		= $goodsParams['goods_name'];
		$provider_id	= $goodsParams['provider_id'];

		if($goods_seq > 0){
			$goodsParams['update_date']		= date('Y-m-d H:i:s');

			$whereParam['goods_seq']		= $goods_seq;
			$whereParam['goods_kind']		= 'goods';
			if	($this->m_nProviderSeq > 1)	$whereParam['provider_seq']		= $this->m_nProviderSeq;
			if	($this->m_sGoodsKind == 'C')$whereParam['goods_kind']		= 'coupon';

			// 입점사일 경우 승인처리 여부에 따른 필드 조정
			if	(count($this->m_aUpdateProviderIgnore['default']) > 0){
				foreach($this->m_aUpdateProviderIgnore['default'] as $k => $fld){
					unset($goodsParams[$fld]);
				}
			}
			if	(count($this->m_aUpdateProviderIgnore['goods']) > 0){
				foreach($this->m_aUpdateProviderIgnore['goods'] as $k => $fld){
					unset($goodsParams[$fld]);
				}
			}elseif	($this->m_sAdminType == 'S'){
				// 입점사 일경우 미승인 처리
				$goodsParams['goods_status']					= 'unsold';
				$goodsParams['goods_view']						= 'notLook';
				$goodsParams['provider_status']					= '0';
				$goodsParams['provider_status_reason_type']		= '5';
				$goodsParams['provider_status_reason']			= '[자동] 엑셀 업로드 시 미승인 처리됨';
			}

			$this->chk_require_goods_update_param($goodsParams);
			$this->db->where($whereParam);
			$this->db->update('fm_goods', $goodsParams);
			$this->m_sGoodsSaveType		= 'update';

			if	($this->m_nProviderSeq > 1)	$goodsParams['provider_seq']	= $this->m_nProviderSeq;
			$this->m_aProviderAsGoods[$goods_seq]	= $goodsParams['provider_seq'];

			if	($this->db->affected_rows() > 0){
				// 처리 로그 추가
				if	($this->m_sAdminType == 'S')	$manager	= '입점사';
				else								$manager	= '관리자';
				$log	= '<div>' . date('Y-m-d H:i:s') . ' ' . $manager . '가 엑셀일괄업데이트를 통해 상품정보를 수정하였습니다. (' . $_SERVER['REMOTE_ADDR'] . ')</div>';
				$sql	= "update fm_goods set admin_log=concat(?, IFNULL(admin_log, '')) where goods_seq = ?";
				$this->db->query($sql, array($log, $goods_seq));

				$this->save_upload_log('success', '[' . $goods_name . '/' . $goods_seq . ']의 상품 기본정보가 수정되었습니다.'."\r\n");
			}else{
				$this->save_upload_log('failed', '[' . $goods_name . '/' . $goods_seq . ']의 상품 기본정보수정되지 않았습니다.' . "\r\n");
				$goods_seq		= '';
			}
		}else{
			// 처리 로그 추가
			if	($this->m_sAdminType == 'S')	$manager	= '입점사';
			else								$manager	= '관리자';
			$log	= '<div>' . date('Y-m-d H:i:s') . ' ' . $manager . '가 엑셀일괄업데이트를 통해 상품정보를 등록하였습니다. (' . $_SERVER['REMOTE_ADDR'] . ')</div>';

			$goodsParams['admin_log']	= $log;
			$goodsParams['regist_date']	= date('Y-m-d H:i:s');
			if	($this->m_nProviderSeq > 1)	$goodsParams['provider_seq']	= $this->m_nProviderSeq;
			unset($goodsParams['provider_id']);
			$this->chk_require_goods_insert_param($goodsParams);
			$this->db->insert('fm_goods', $goodsParams);
			$goods_seq					= $this->db->insert_id();
			$this->m_sGoodsSaveType		= 'insert';

			if	($goods_seq){
				/**
				 * 엑셀 상품 등록시 배송그룹 실물상품 개수 재계산
				 * 2019-06-20
				 * @author Sunha Ryu
				 */
				if(!empty($goodsParams['shipping_group_seq'])) {
					$this->load->model('shippingmodel');
					$this->shippingmodel->group_cnt_adjust(array($goodsParams['shipping_group_seq']));
				}
				$this->save_upload_log('success', '[' . $goods_name . '/' . $goods_seq . ']의 상품 기본정보가 등록되었습니다.'."\r\n");
				$this->m_aProviderAsGoods[$goods_seq]	= $goodsParams['provider_seq'];
			}else{
				$this->save_upload_log('failed', '[' . $goods_name . '/' . $goods_seq . ']의 상품 기본정보가 등록되지 않았습니다.' . "\r\n");
				$goods_seq		= '';
			}
		}
		$this->m_aCurrentGoodsInfo	= $goodsParams;
		if($provider_id && !$this->m_aCurrentGoodsInfo['provider_id']) $this->m_aCurrentGoodsInfo['provider_id']	= $provider_id;


		if($goods_seq){
			$this->load->model('goodssummarymodel');
			$this->goodssummarymodel->set_event_price(array('goods'=>array($goods_seq)));
		}

		return $goods_seq;
	}

	// 단순 insert ( delete -> insert )
	public function save_table_data($rowNum, $goods_seq, $tableArr){
		// 단순 insert ( delete -> insert )
		if	($tableArr)foreach($tableArr as $tbName => $fldArr){
			$this->db->delete($tbName, array('goods_seq' => $goods_seq));
			if	($fldArr)foreach($fldArr as $cell => $fld){
				$insertParams[$fld]	= $this->workSheet->getCell($cell.$rowNum)->getValue();
			}
			$this->db->insert($tbName, $insertParams);
		}
	}

	// 예외처리가 필요한 데이터 insert ( delete -> insert )
	public function save_except_data($rowNum, $goods_seq, $exceptArr){

		$goodsUpdateParams	= array();
		if	($exceptArr)foreach($exceptArr as $type => $fldArr){
			// 입점사 관리자 수정일 경우
			if	($this->m_sGoodsSaveType == 'update' && in_array($type, $this->m_aUpdateProviderIgnore['except']))	continue;

			unset($params,$result);
			if	($fldArr)foreach($fldArr as $cell => $code){
				$params[$this->m_aFieldInfo[$code]]	= $this->workSheet->getCell($cell.$rowNum)->getValue();
			}
			//올인원이면서 입점상품일때 재고관리 예외처리 @2017-04-04
			if	($this->m_aProviderAsGoods[$goods_seq] && $this->m_aProviderAsGoods[$goods_seq] > 1  && $type == 'warehouse' ) continue;

			$except		= '_upload_exception_' . $type;
			$result		= $this->$except($goods_seq, $params);
			if	($type == 'option')	$set_option	= $result['status'];

			// 상품정보 Update 항목
			if	($result['status'] && count($result['goodsUpdateParam']) > 0){
				$goodsUpdateParams	= array_merge($goodsUpdateParams, $result['goodsUpdateParam']);
			}

			if	(!$result['status']){
				$this->save_upload_log('failed', '[' . $goods_seq . '] ' . $result['msg'] . "\r\n");
			}
		}

		// 필수옵션이 없는 경우 ( 기본 필수옵션으로 넣을때는 판매중지, 미노출로 처리 )
		if	($this->m_sGoodsSaveType == 'insert'){
			if	(!$exceptArr || !in_array('option', array_keys($exceptArr)) || !$set_option){
				$this->insert_default_option($goods_seq);
				$goodsUpdateParams['reserve_policy']	= 'shop';
				$goodsUpdateParams['goods_status']		= 'unsold';
				$goodsUpdateParams['goods_view']		= 'notLook';
			}
		}

		// 예외처리에 따른 상품 기본정보 Update
		if	($goods_seq > 0 && count($goodsUpdateParams) > 0){
			$this->db->where(array('goods_seq' => $goods_seq));
			$this->db->update('fm_goods', $goodsUpdateParams);
		}
	}

	// 필수옵션 기본 형태로 추가
	public function insert_default_option($goods_seq){

		$params					 = array();
		$params['goods_seq']		= $goods_seq;
		$params['consumer_price']	= 0;
		$params['price']			= 0;

		// 수수료
		if	(in_array('commission_rate', $this->m_aFieldInfo)){
			$params['commission_rate']	= 0;
		}
		$params['reserve_rate']		= ($this->reserves['default_reserve_percent'])?$this->reserves['default_reserve_percent']:0;
		$params['reserve_unit']		= 'percent';
		$params['reserve']			= 0;
		$params['fix_option_seq']	= 0;
		if ($this->m_sGoodsKind == "C" && !$params['default_option']) {
			$params['default_option']	= 'y';
		}
		
		$supplyparam					= array();
		$supplyparam['stock']			= 0;
		$supplyparam['supply_price']	= 0;
		
		$this->db->insert('fm_goods_option', $params);
		$optSeq	= $this->db->insert_id();
		$supplyparam['option_seq']			= $optSeq;
		$supplyparam['goods_seq']			= $goods_seq;
		$this->db->insert('fm_goods_supply', $supplyparam);

		// 고정 seq값 추가
		if	(!$option_seq){
			$this->db->where(array('option_seq' => $optSeq));
			$this->db->update('fm_goods_option', array('fix_option_seq' => $optSeq));
		}

		// 정상 상태라면 품절로 update함
		if	($this->config_order['runout'] != 'unlimited'){
			$sql	= "update fm_goods set goods_status = 'runout'
						where goods_seq = ? and goods_status = ? ";
			$this->db->query($sql, array($goods_seq, 'normal'));
		}
	}

	// 옵션 데이터 저장
	public function _upload_exception_option($goods_seq, $data){

		$goodsParams					= array();
		$tot_stock						= 0;
		$this->m_sOptionReservePolicy	= '';

		$provider_info					= $this->m_aProviderList[$this->m_aProviderAsGoods[$goods_seq]];

		// 줄바꿈 구분자 cell 데이터들을 배열화
		$nCnt = 0;
		$bCnt = 0;
		$oCnt = 0;

		foreach($this->m_aOptionMultiRowCell as $c => $fld){
			$data[$fld] = trim($data[$fld]);
			if(strlen($data[$fld]) <= 0){
				unset($data[$fld]);
			}
			$$fld		= explode(chr($this->m_sUploadLineCharString), $data[$fld]);

			// 행수체크를 pass @2017-04-11
			if	($fld == 'action_option_kind' && !trim($data[$fld]))	continue;//필수옵션동작구분은 프로그램단에서 처리됨
			if	($fld == 'reserve_rate' && !isset($data[$fld]))			continue;//적립금 ( 통합정책으로 전환 )
			if	($fld == 'commission_rate' && !isset($data[$fld]))		continue;//수수료 0원도 포함
			if	($fld == 'commission_price' && !isset($data[$fld]))		continue;//공급가 0원도 포함
			if	($fld == 'supply_price' && !isset($data[$fld]))			continue;//매입가 0원도 포함
			if	($fld == 'consumer_price' && !isset($data[$fld]))		continue;//정가 0원도 포함
			if	($fld == 'price' && !isset($data[$fld]))				continue;//할인가(판매가) 0원도 포함

			//필수옵션 등록 불가 오류 수정 18.12.12 kmj
			if(in_array($fld, $this->m_aOptionRowCountCell)){
				if(preg_match('/^option[0-9]{1}$/', $fld)){
					if($data['option_title'.substr($fld, -1)]){
						$nCnt = count($$fld);

						if($oCnt <= 0){
							$oCnt = $nCnt;
						}

						if($nCnt > 0 && $oCnt > 0 && $nCnt != $oCnt){
							return array('status' => false, 'msg' => '필수옵션 저장 실패 : 필수옵션 행수 불일치');
						}
					}
				}else{
					$nCnt = count($$fld);
					if($bCnt <= 0){
						$bCnt = $nCnt;
					}

					if($nCnt > 0 && $bCnt > 0 && $nCnt != $bCnt){
						return array('status' => false, 'msg' => '필수옵션 저장 실패 : 필수옵션항목 행수 불일치');
					}
				}
			}
		}

		if($nCnt != $nCnt){
			return array('status' => false, 'msg' => '필수옵션 저장 실패 : 필수옵션 및 각 항목 행수 불일치');
		}

		// 옵션명 특수옵션 체크
		for ($t = 1; $t <= 5; $t++){
			if	($data['option_title'.$t]){
				$tmp			= $this->upload_special_option_name($data['option_title'.$t]);
				$optTitle[$t]	= $tmp['title'];
				$newtype[$t]	= 'none';//특수옵션외 기본값
				// 특수옵션 중복 세팅 방지 ( 날짜옵션은 1개만 유효하고 그 외에는 일반옵션 처리 )
				if	($tmp['newtype']){
					if	(!in_array($tmp['newtype'], $newtype)){
						if	(in_array($tmp['newtype'], array('date', 'dayinput', 'dayauto'))){
							if	(in_array('date', $newtype) || in_array('dayinput', $newtype) || in_array('dayauto', $newtype)){
								return array('status' => false, 'msg' => '필수옵션 저장 실패 : 중복될 수 없는 날짜, 수동기간, 자동기간 특수옵션 중 2가지가 혼재합니다.');
							}else{
								$newtype[$t]	= $tmp['newtype'];
							}
						}else{
							$newtype[$t]	= $tmp['newtype'];
						}
					}else{
						return array('status' => false, 'msg' => '필수옵션 저장 실패 : 중복된 특수옵션 '.$this->download_special_option_name($tmp['newtype']).'이(가) 존재합니다.');
					}
				}
			}
		}
		$option_title	= implode(',', $optTitle);
		$newTypeStr		= implode(',', $newtype);

		// 티켓 상품일 경우 특수옵션에 날짜 관련 옵션이 없으면 판매중지, 미노출로 처리함
		if	($this->m_sGoodsKind == 'C' && !in_array('date', $newtype) && !in_array('dayinput', $newtype) && !in_array('dayauto', $newtype)) {
			return array('status' => false, 'msg' => '필수옵션 저장 실패 : 티켓 상품은 날짜, 수동기간, 자동기간 중 1개의 특수옵션이 존재해야 합니다.');
		}

		// 상품이 insert일 경우 기존 option_seq는 무시함.
		if	($this->m_sGoodsSaveType == 'insert'){
			$option_seq	= array();

			// 새로 등록
			$this->db->delete('fm_goods_option', array('goods_seq'=>$goods_seq));
			$this->delete_goods_supply($goods_seq, 'option');
		}

		// 필수옵션 사용
		$this->m_aAddOptionInfoList	= array();
		$default_option				= 'y';
		if	($option_title && is_array($option1) && count($option1) > 0){
			if	(!$this->m_bIsScm || $this->m_sGoodsSaveType == 'insert'){
				$goodsParams['option_use']		= '1';
			}
			foreach($option1 as $o => $opt1){
				$optionCodeStr				= '';
				$optionNameStr				= '';
				unset($params);
				$params['option_title']		= $option_title;
				$params['newtype']			= $newTypeStr;
				$params['default_option']	= $default_option;
				// 삭제인 경우에는 계속 default_option : y 로 .... 2018-04-18
				if	($default_option == 'y' && $action_option_kind[$o] != '삭제')	$default_option		= 'n';
				for($k = 1; $k <= 5; $k++){
					$tmp						= $this->get_divide_special_option($newtype[$k], ${'option'.$k}[$o]);
					$params['option'.$k]		= (${'option'.$k}[$o]) ? ${'option'.$k}[$o] : '';
					if	($tmp['option']) foreach($tmp as $f => $v){
						if	($f == 'option')	$params['option'.$k]		= $v;
						else					$params[$f]					= $v;
					}
					$params['optioncode'.$k]	= ${'optioncode'.$k}[$o];
					$optionCodeStr				.= trim(${'optioncode'.$k}[$o]);
					$optionNameStr				.= trim($params['option'.$k]);
				}
				$params['infomation']		= $infomation[$o];
				$params['consumer_price']	= (int) $consumer_price[$o];
				$params['price']			= (int) $price[$o];
				$params['weight']			= $weight[$o];
				$params['option_view']		= ($option_view[$o] == "미노출") ? 'N' : 'Y';

				if(!$params['weight'])
						$params['weight']	= 0;

				if($provider_info['commission_type'] == 'SACO' || $provider_info['commission_type'] == ''){
					// 수수료 방식
					if	(isset($commission_rate[$o])){//0포함 체크
						$commission_rate[$o]		= str_replace('%', '', $commission_rate[$o]);
						$commission_rate[$o]		= ($commission_rate[$o] < 1) ? $commission_rate[$o] * 100 : $commission_rate[$o];
						$params['commission_rate']	= floor($commission_rate[$o]*100)/100;
						$params['commission_type']	= 'SACO';
					}
				}else{
					//공급가 방식
					if	(isset($commission_price[$o])){//0포함 체크
						$now_unit			= '';
						$commission_type	= '';
						$commission_rate	= '';
						$commission_rate	= preg_replace('/원|%/','',$commission_price[$o]);
						preg_match('/(원|%)$/', $commission_price[$o], $now_unit);

						if(!$now_unit[1])	$now_unit[1]	= ((int)$commission_rate > 100) ? '원' : '%';

						switch($now_unit[1]){
							case	'원' :
								$params['commission_rate']	= (int)$commission_rate;
								$params['commission_type']	= 'SUPR';
								break;
							default :
								$commission_rate				= ($commission_rate < 1) ? $commission_rate * 100 : $commission_rate;
								$params['commission_rate']	= floor($commission_rate*100)/100;
								$params['commission_type']	= 'SUCO';
								break;
						}
					}
				}
				// 마일리지 ( 한개라도 통합정책이면 전부 통합 정책임 )
				if	($reserve_rate[$o] && $this->m_sOptionReservePolicy != "shop"){
					$this->m_sOptionReservePolicy	= "goods";
					$tmp_reserve_arr				= $this->divide_price_and_unit($reserve_rate[$o]);
					$tmp_reserve_rate				= $tmp_reserve_arr['price'];
					$tmp_reserve_unit				= $tmp_reserve_arr['unit'];
					if	($tmp_reserve_unit == 'percent'){
						$tmp_reserve				= $price[$o] * ($tmp_reserve_rate / 100);
					}else{
						$tmp_reserve				= $tmp_reserve_rate;
					}
				}else{
					$this->m_sOptionReservePolicy	= "shop";
					$tmp_reserve_rate				= ($this->reserves['default_reserve_percent'])?$this->reserves['default_reserve_percent']:0;
					$tmp_reserve_unit				= 'percent';
					$tmp_reserve					= $price[$o] * ($tmp_reserve_rate / 100);
				}
				
				$params['reserve_rate']		= $tmp_reserve_rate;
				$params['reserve_unit']		= $tmp_reserve_unit;
				$params['reserve']			= (int) $tmp_reserve;

				// 상품이 수정이고 option_seq가 있을 경우 update, 없으면 insert
				if	($this->m_sGoodsSaveType == 'update' && $option_seq[$o] > 0){
					// 입점사는 마일리지 update 제거
					if	($this->m_sAdminType == 'S'){
						unset($params['reserve_rate'], $params['reserve_unit'], $params['reserve']);
					}

					$this->db->where(array('option_seq'=>$option_seq[$o],'goods_seq'=> $goods_seq));
					$this->db->update('fm_goods_option', $params);
					$optSeq						= $option_seq[$o];
					$option_save_type			= 'update';
				}else{
					$params['goods_seq']		= $goods_seq;
					
					//입점사의 경우 기본수수료 적용
					if($this->m_sAdminType == 'S'){
						$params['commission_type']		= $provider_info['commission_type'];
						$params['commission_rate']		= $provider_info['charge'];
					}

					$this->db->insert('fm_goods_option', $params);
					$optSeq						= $this->db->insert_id();
					$option_save_type			= 'insert';

					// 고정 seq값 추가
					$this->db->where(array('option_seq' => $optSeq));
					$this->db->update('fm_goods_option', array('fix_option_seq' => $optSeq));
				}
				
				//입점사의 경우 매입가 0으로 처리
				if($this->m_sServiceType == 'A' && ($this->m_sAdminType == 'S' || $provider_info['provider_seq'] > 1)){
					$supply_price[$o] = '0';
				}

				// supply 정보 update 및 insert
				unset($supplyparam);
				if	($option_save_type == 'update' && $optSeq > 0 && $goods_seq > 0){
					//올인원아니거나 올인원이면서 입점상품일때 재고추가 @2017-04-04
					if	(!$this->m_bIsScm || ($this->m_bIsScm && $this->m_aProviderAsGoods[$goods_seq] && $this->m_aProviderAsGoods[$goods_seq] > 1) ){
						$supplyparam['stock']			= (int) $stock[$o];
						$supplyparam['badstock']		= (int) $badstock[$o];
						$supplyparam['safe_stock']		= (int) $safe_stock[$o];
						$supplyparam['supply_price']	= (int) $supply_price[$o];
						$this->db->where(array('option_seq'=>$optSeq,'goods_seq'=> $goods_seq));
						$this->db->update('fm_goods_supply', $supplyparam);
					}
				}else{
					//올인원이면서 본사상품만 재고 0 처리 @2017-04-04
					if	($this->m_bIsScm && $this->m_aProviderAsGoods[$goods_seq] == 1 ) {
						$stock[$o]			= '0';
						$badstock[$o]		= '0';
						$supply_price[$o]	= '0';
					}
					$supplyparam['stock']				= (int) $stock[$o];
					$supplyparam['badstock']			= (int) $badstock[$o];
					$supplyparam['safe_stock']			= (int) $safe_stock[$o];
					$supplyparam['supply_price']		= (int) $supply_price[$o];
					$supplyparam['goods_seq']			= (int) $goods_seq;
					$supplyparam['option_seq']			= $optSeq;
					$this->db->insert('fm_goods_supply', $supplyparam);
				}
				if	($optSeq > 0){
					$saved_option[]		= $optSeq;
					if	($action_option_kind[$o] == '삭제')	$delete_option[]		= $optSeq;
					if	($action_option_kind[$o] == '추가' && $option_save_type == 'insert'){
						$this->m_aAddOptionInfoList[$o]['option_seq']		= $optSeq;
						$this->m_aAddOptionInfoList[$o]['option_code']		= $optionCodeStr;
						$this->m_aAddOptionInfoList[$o]['option_name']		= $optionNameStr;
					}
				}
				if	($stock[$o] > 0)	$tot_stock			+= (int) $stock[$o];
			}
		// 필수옵션 미사용
		}else{

			// 배열로 넘어온 경우 0번째 값으로 변경함.
			foreach($this->m_aOptionMultiRowCell as $c => $fld){
				if	(is_array($$fld))	$$fld	= ${$fld}[0];
			}

			$params['default_option']	= 'y';
			$params['goods_seq']		= $goods_seq;
			$params['consumer_price']	= (int) $consumer_price;
			$params['price']			= (int) $price;
			$params['weight']			= $weight;
			$params['option_view']		= ($option_view == "미노출") ? 'N' : 'Y';

			if(!$params['weight'])
					$params['weight']	= 0;

			if($provider_info['commission_type'] == 'SACO' || $provider_info['commission_type'] == ''){
				// 수수료 방식
				if(isset($commission_rate)){//0포함 체크
					if( strstr($commission_rate,"%") ) {//excel type : text @2015-11-16
						$commission_rate		= str_replace('%', '', $commission_rate);
					}else{
						$commission_rate = ($commission_rate*100);//excel type : % @2015-11-16
					}
					$commission_rate			= ($commission_rate < 1) ? $commission_rate * 100 : $commission_rate;
					$params['commission_rate']	= floor($commission_rate*100)/100;
				}
			}else{
				//공급가 방식
				if(isset($commission_price)){//0포함 체크
					$now_unit			= '';
					$commission_type	= '';
					$commission_rate	= '';
					$commission_rate	= preg_replace('/원|%/','',$commission_price);
					preg_match('/(원|%)$/', $commission_price, $now_unit);
					if(!$now_unit[1])	$now_unit[1]	= ((int)$commission_rate > 100) ? '원' : '%';

					switch($now_unit[1]){
						case	'원' :
							$params['commission_rate']	= (int)$commission_rate;
							$params['commission_type']	= 'SUPR';
							break;
						default :
							$commission_rate			= ($commission_rate < 1) ? $commission_rate * 100 : $commission_rate;
							$params['commission_rate']	= floor($commission_rate*100)/100;
							$params['commission_type']	= 'SUCO';
							break;
					}

				}
			}

			// 마일리지
			if	($reserve_rate && $this->m_sOptionReservePolicy != "shop"){
				$this->m_sOptionReservePolicy	= "goods";
				$tmp_reserve_arr				= $this->divide_price_and_unit($reserve_rate);
				$tmp_reserve_rate				= $tmp_reserve_arr['price'];
				$tmp_reserve_unit				= $tmp_reserve_arr['unit'];
				if	($tmp_reserve_unit == 'percent'){
					$tmp_reserve				= $price * ($tmp_reserve_rate / 100);
				}else{
					$tmp_reserve				= $tmp_reserve_rate;
				}
			}else{
				$this->m_sOptionReservePolicy	= "shop";
				$tmp_reserve_rate				= ($this->reserves['default_reserve_percent'])?$this->reserves['default_reserve_percent']:0;
				$tmp_reserve_unit				= 'percent';
				$tmp_reserve					= $price * ($tmp_reserve_rate / 100);
			}
			$params['reserve_rate']		= (int) $tmp_reserve_rate;
			$params['reserve_unit']		= $tmp_reserve_unit;
			$params['reserve']			= (int) $tmp_reserve;

			// 상품이 수정이고 option_seq가 있을 경우 update, 없으면 insert
			if	($this->m_sGoodsSaveType == 'update' && $option_seq > 0){
				// 입점사는 마일리지 update 제거
				if	($this->m_sAdminType == 'S'){
					unset($params['reserve_rate'], $params['reserve_unit'], $params['reserve']);
				}

				$this->db->where(array('option_seq'=>$option_seq,'goods_seq'=> $goods_seq));
				$this->db->update('fm_goods_option', $params);
				$option_save_type			= 'update';
				$optSeq						= $option_seq;
			}else{
				$params['goods_seq']		= $goods_seq;

				//입점사의 경우 기본수수료 적용
				if($this->m_sAdminType == 'S'){
					$params['commission_type']		= $provider_info['commission_type'];
					$params['commission_rate']		= $provider_info['charge'];
				}
				$this->db->insert('fm_goods_option', $params);
				
				$optSeq						= $this->db->insert_id();
				$option_save_type			= 'insert';

				// 고정 seq값 추가
				$this->db->where(array('option_seq' => $optSeq));
				$this->db->update('fm_goods_option', array('fix_option_seq' => $optSeq));
			}

			// supply 정보 update 및 insert
			unset($supplyparam);
			if	($option_save_type == 'update' && $optSeq > 0 && $goods_seq > 0){
				//올인원아니거나 올인원이면서 입점상품일때 재고추가 @2017-04-04
				if	(!$this->m_bIsScm || ($this->m_bIsScm && $this->m_aProviderAsGoods[$goods_seq] && $this->m_aProviderAsGoods[$goods_seq] > 1) ){
					$supplyparam['stock']				= (int) $stock;
					$supplyparam['badstock']			= (int) $badstock;
					$supplyparam['supply_price']		= (int) $supply_price;
				}
				$supplyparam['safe_stock']				= (int) $safe_stock;
				$this->db->where(array('option_seq'=>$optSeq,'goods_seq'=> $goods_seq));
				$this->db->update('fm_goods_supply', $supplyparam);
			}else{
				//올인원이면서 본사상품만 재고 0 처리 @2017-04-04
				if	($this->m_bIsScm && ($this->m_aProviderAsGoods[$goods_seq] == 1 || $this->m_sServiceType	== 'N')) {
					$stock			= '0';
					$badstock		= '0';
					$supply_price	= '0';
				}
				$supplyparam['stock']				= (int) $stock;
				$supplyparam['badstock']			= (int) $badstock;
				$supplyparam['safe_stock']			= (int) $safe_stock;
				$supplyparam['supply_price']		= (int) $supply_price;
				$supplyparam['goods_seq']			= (int) $goods_seq;
				$supplyparam['option_seq']			= $optSeq;
				$this->db->insert('fm_goods_supply', $supplyparam);
			}

			if	($optSeq > 0){
				$saved_option[]		= $optSeq;
				if	($action_option_kind == '삭제')	$delete_option[]		= $optSeq;
				if	($action_option_kind == '추가' && $option_save_type == 'insert'){
					$this->m_aAddOptionInfoList[0]['option_seq']		= $optSeq;
					$this->m_aAddOptionInfoList[0]['option_code']		= '';
					$this->m_aAddOptionInfoList[0]['option_name']		= '';
				}
			}
			if	($stock > 0)		$tot_stock			+= (int) $stock;
		}
		if	($tot_stock > 0)	$goodsParams['tot_stock']	= $tot_stock;

		// 위에서 save된 option외의 option들 삭제
		if	(count($delete_option) > 0){
			$this->delete_target_options($goods_seq, 'option', $delete_option);
		}

		//옵션고유번호 없을때 기존옵션삭제하지 않고 생성되는 문제로 수정 @2016-07-13 ysm
		if	(!$this->m_bIsScm && count($saved_option) > 0){
			$this->delete_remain_options($goods_seq, 'option', $saved_option);
		}

		// 입점사는 마일리지 update 제거
		if	($this->m_sAdminType != 'S' || $this->m_sGoodsSaveType != 'update'){
			$goodsParams['reserve_policy']	= $this->m_sOptionReservePolicy;
		}

		// 입점사 일경우 미승인 처리
		if	($this->m_sAdminType == 'S'){
			$goodsParams['goods_status']					= 'unsold';
			$goodsParams['goods_view']						= 'notLook';
			$goodsParams['provider_status']					= '0';
			$goodsParams['provider_status_reason_type']		= '5';
			$goodsParams['provider_status_reason']			= '[자동] 엑셀 업로드 시 미승인 처리됨';
		}
		return array('status' => true, 'goodsUpdateParam' => $goodsParams);
	}

	// 옵션 데이터 저장
	public function _upload_exception_option_batch($option){
		$optionParams	 = array();
		$supplyParams	 = array();
		$returnData	   = array();
		$goodsSeqs		= array();
		$optionsSeqs	  = array();
		$keys			 = array('0' => 'goods_seq');
		
		foreach($option as $goods_seq => $data) {
			if(!array_filter($data)){ //빈 데이터 일 경우 패스
				continue;
			}
			$returnData[$goods_seq]['status']		= NULL;
			$returnData[$goods_seq]['goods_name']	= $data['goods_name'];
			$returnData[$goods_seq]['provider_seq']	= $data['provider_seq'];

			$tot_stock						= 0;
			$this->m_sOptionReservePolicy	= "";		

			$provider_info					= $this->m_aProviderList[$data['provider_seq']];

			// 줄바꿈 구분자 cell 데이터들을 배열화
			$nCnt = 0;
			$bCnt = 0;
			$oCnt = 0;

			foreach( $this->m_aOptionMultiRowCell as $fld ) {
				$data[$fld] = trim($data[$fld]);
				if(strlen($data[$fld]) <= 0){
					unset($data[$fld]);
				}
				$$fld = explode(chr($this->m_sUploadLineCharString), $data[$fld]);
				$$fld = array_filter($$fld);
				
				// 행수체크를 pass @2017-04-11
				if	($fld == 'action_option_kind' && !trim($data[$fld]))	continue;//필수옵션동작구분은 프로그램단에서 처리됨
				if	($fld == 'reserve_rate' && !isset($data[$fld]))			continue;//적립금 ( 통합정책으로 전환 )
				if	($fld == 'commission_rate' && !isset($data[$fld]))		continue;//수수료 0원도 포함
				if	($fld == 'commission_price' && !isset($data[$fld]))		continue;//공급가 0원도 포함
				if	($fld == 'supply_price' && !isset($data[$fld]))			continue;//매입가 0원도 포함
				if	($fld == 'consumer_price' && !isset($data[$fld]))		continue;//정가 0원도 포함
				if	($fld == 'price' && !isset($data[$fld]))				continue;//할인가(판매가) 0원도 포함

				//필수옵션 등록 불가 오류 수정 18.12.12 kmj
				if(in_array($fld, $this->m_aOptionRowCountCell)){
					if(preg_match('/^option[0-9]{1}$/', $fld)){
						if($data['option_title'.substr($fld, -1)]){
							$nCnt = count($$fld);

							if($oCnt <= 0){
								$oCnt = $nCnt;
							}

							if($nCnt > 0 && $oCnt > 0 && $nCnt != $oCnt){
								$returnData[$goods_seq]['status']	= false;
								$returnData[$goods_seq]['msg']		= '필수옵션 저장 실패 : 필수옵션 행수 불일치';
								continue;
							}
						}
					}else{
						$nCnt = count($$fld);
						if($bCnt <= 0){
							$bCnt = $nCnt;
						}

						if($nCnt > 0 && $bCnt > 0 && $nCnt != $bCnt){
							$returnData[$goods_seq]['status']	= false;
							$returnData[$goods_seq]['msg']		= '필수옵션 저장 실패 : 필수옵션항목 행수 불일치';
							continue;
						}
					}
				}
			}

			if($nCnt != $nCnt){
				$returnData[$goods_seq]['status']	= false;
				$returnData[$goods_seq]['msg']		= '필수옵션 저장 실패 : 필수옵션 및 각 항목 행수 불일치';
				continue;
			}

			// 옵션명 특수옵션 체크
			$optTitle	= array();
			$newtype	 = array();
			
			for ($t = 1; $t <= 5; $t++){
				if ($data['option_title'.$t]) {
					$tmp			= $this->upload_special_option_name($data['option_title'.$t]);
					$optTitle[$t]	= $tmp['title'];
					$newtype[$t]	= 'none';//특수옵션외 기본값
					// 특수옵션 중복 세팅 방지 ( 날짜옵션은 1개만 유효하고 그 외에는 일반옵션 처리 )
					if	($tmp['newtype']){
						if	(!in_array($tmp['newtype'], $newtype)){
							if	(in_array($tmp['newtype'], array('date', 'dayinput', 'dayauto'))){
								if	(in_array('date', $newtype) || in_array('dayinput', $newtype) || in_array('dayauto', $newtype)){
									$returnData[$goods_seq]['status']	= false;
									$returnData[$goods_seq]['msg']		= '필수옵션 저장 실패 : 중복될 수 없는 날짜, 수동기간, 자동기간 특수옵션 중 2가지가 혼재합니다.';
									continue;
								}else{
									$newtype[$t]	= $tmp['newtype'];
								}
							}else{
								$newtype[$t]	= $tmp['newtype'];
							}
						}else{
							$returnData[$goods_seq]['status']	= false;
							$returnData[$goods_seq]['msg']		= '필수옵션 저장 실패 : 중복된 특수옵션 '.$this->download_special_option_name($tmp['newtype']).'이(가) 존재합니다.';
							continue;
						}
					}
				}
			}

			$option_title	= implode(',', $optTitle);
			$newTypeStr		= implode(',', $newtype);

			// 티켓 상품일 경우 특수옵션에 날짜 관련 옵션이 없으면 판매중지, 미노출로 처리함
			if ($this->m_sGoodsKind == 'C' && !in_array('date', $newtype) && !in_array('dayinput', $newtype) && !in_array('dayauto', $newtype)) {
				$returnData[$goods_seq]['status']	= false;
				$returnData[$goods_seq]['msg']		= '필수옵션 저장 실패 : 티켓 상품은 날짜, 수동기간, 자동기간 중 1개의 특수옵션이 존재해야 합니다.';
				continue;
			}

			$this->m_aAddOptionInfoList	= array();
			$default_option				= 'y';
			$delOptions				  = array();

			// 필수옵션 사용
			if ($option_title && is_array($option1) && count($option1) > 0) {
				if (!$this->m_bIsScm || $this->m_sGoodsSaveType == 'insert') {
					$returnData[$goods_seq]['update']['option_use'] = '1';
				}

				foreach ($option1 as $o => $opt1) {
					$params = array();
					
					if ($option_seq[$o] && $action_option_kind[$o] == '수정') {
						$params['option_seq']   = $option_seq[$o];
					} 
					
					$params['option_title']		= $option_title;
					$params['newtype']			= $newTypeStr;
					$params['default_option']	= $default_option;

					// 삭제인 경우에는 계속 default_option : y 로 .... 2018-04-18
					if ($default_option == 'y' && $action_option_kind[$o] != '삭제') {
						$default_option = 'n';
					}
					
					for ($k = 1; $k <= 5; $k++) {
						$tmp						= $this->get_divide_special_option($newtype[$k], ${'option'.$k}[$o]); //특수 옵션값 분리
						$params['option'.$k]		= (${'option'.$k}[$o]) ? trim(${'option'.$k}[$o]) : '';
						if($tmp['option']) foreach($tmp as $f => $v){
							if	($f == 'option')	$params['option'.$k]		= trim($v);
							else					$params[$f]					= $v;
						}
						$params['optioncode'.$k]	= ${'optioncode'.$k}[$o];
					}
					$params['infomation']		= $infomation[$o];
					$params['consumer_price']	= (int) $consumer_price[$o];
					$params['price']			= (int) $price[$o];
					$params['weight']			= $weight[$o];
					$params['option_view']		= ($option_view[$o] == "미노출") ? 'N' : 'Y';
					//$params['commission_type']  = '';
					$params['commission_rate']  = '';

					if (!$params['weight']) {
						$params['weight'] = 0;
					}
					
					if ($provider_info['commission_type'] == 'SACO' || $provider_info['commission_type'] == '') {
						// 수수료 방식
						if (isset($commission_rate[$o])) {//0포함 체크
							$commission_rate[$o]		= str_replace('%', '', $commission_rate[$o]);
							$commission_rate[$o]		= ($commission_rate[$o] < 1) ? $commission_rate[$o] * 100 : $commission_rate[$o];
							
							$params['commission_rate']  = round($commission_rate[$o] - (0.5 / pow(10, 2)), 2);
							$params['commission_type']	= 'SACO';
						}
					} else {
						//공급가 방식
						if (isset($commission_price[$o])) {//0포함 체크
							$now_unit			= '';
							$commission_type	= '';
							$commission_rate	= '';
							$commission_rate	= preg_replace('/원|%/','',$commission_price[$o]);
							preg_match('/(원|%)$/', $commission_price[$o], $now_unit);

							if(!$now_unit[1])	$now_unit[1]	= ((int)$commission_rate > 100) ? '원' : '%';

							switch($now_unit[1]){
								case '원' :
									$params['commission_rate']	= (int)$commission_rate;
									$params['commission_type']	= 'SUPR';
									break;
								default :
									$commission_rate				= ($commission_rate < 1) ? $commission_rate * 100 : $commission_rate;
									$params['commission_rate']	= floor($commission_rate*100)/100;
									$params['commission_type']	= 'SUCO';
									break;
							}
						}
					}

					// 마일리지 ( 한개라도 통합정책이면 전부 통합 정책임 )
					if($reserve_rate[$o] && $this->m_sOptionReservePolicy != "shop"){
						$this->m_sOptionReservePolicy	= "goods";
						$tmp_reserve_arr				= $this->divide_price_and_unit($reserve_rate[$o]);
						$tmp_reserve_rate				= $tmp_reserve_arr['price'];
						$tmp_reserve_unit				= $tmp_reserve_arr['unit'];
						if( $tmp_reserve_unit == 'percent' ) {
							$tmp_reserve				= $price[$o] * ($tmp_reserve_rate / 100);
						}else{
							$tmp_reserve				= $tmp_reserve_rate;
						}
					}else{
						$this->m_sOptionReservePolicy	= "shop";
						$tmp_reserve_rate				= ($this->reserves['default_reserve_percent'])?$this->reserves['default_reserve_percent']:0;
						$tmp_reserve_unit				= 'percent';
						$tmp_reserve					= $price[$o] * ($tmp_reserve_rate / 100);
					}

					$params['reserve_rate'] = $tmp_reserve_rate;
					$params['reserve_unit'] = $tmp_reserve_unit;
					$params['reserve']		= (int) $tmp_reserve;
					$params['goods_seq']	= $goods_seq;

					//입점사의 경우 기본수수료 적용
					if($this->m_sAdminType == 'S'){
						$params['commission_type']		= $provider_info['commission_type'];
						$params['commission_rate']		= $provider_info['charge'];
					}
					
					//입점사의 경우 매입가 0으로 처리
					if($this->m_sServiceType == 'A' && ($this->m_sAdminType == 'S' || $provider_info['provider_seq'] > 1)){
						$supply_price[$o] = '0';
					}
					
					if ($action_option_kind[$o] == '삭제') {
						$delOptions[] = $option_seq[$o];
					}

					if ($params && $action_option_kind[$o] != '삭제') {
						if($option_seq[$o] > 0){
							$params['fix_option_seq'] = $option_seq[$o];
						}
						
						//insert의 경우 모든 데이터의 key 값이 동일 해야 함.
						$paramsKeys	= array_keys($params);
						$keysDiff	  = array_diff($paramsKeys, $keys);
						if(count($keysDiff) > 0){
							$keys	  = array_merge($keys, $keysDiff);
						}
						
						$optionParams[]	= $params;
						$goodsSeqs[]	  = $goods_seq;
						$optionsSeqs[]	= $params['option_seq'];
					}

					// supply 정보 update 및 insert
					$supplyparam   = array();
					$supplyKey	 = count($optionParams) - 1;
					if ($action_option_kind[$o] == '수정' && $option_seq[$o] > 0 && $this->m_sGoodsSaveType == 'update'){
						//올인원아니거나 올인원이면서 입점상품일때 재고추가 @2017-04-04
						if (!$this->m_bIsScm || 
							   ($this->m_bIsScm && $this->m_aProviderAsGoods[$goods_seq] 
								   && $this->m_aProviderAsGoods[$goods_seq] > 1) ){
							$supplyparam['stock']			= (int) $stock[$o];
							$supplyparam['badstock']		= (int) $badstock[$o];
							$supplyparam['safe_stock']		= (int) $safe_stock[$o];
							$supplyparam['supply_price']	= (int) $supply_price[$o];
						}
					} else {
						//올인원이면서 본사상품만 재고 0 처리 @2017-04-04
						if ($this->m_bIsScm && $this->m_aProviderAsGoods[$goods_seq] == 1 ) {
							$stock[$o]			= '0';
							$badstock[$o]		= '0';
							$supply_price[$o]	= '0';
						}

						$supplyparam['stock']			= (int) $stock[$o];
						$supplyparam['badstock']		= (int) $badstock[$o];
						$supplyparam['safe_stock']		= (int) $safe_stock[$o];
						$supplyparam['supply_price']	= (int) $supply_price[$o];
						$supplyparam['goods_seq']		= (int) $goods_seq;
					}


					if ($action_option_kind[$o] != '삭제' && count($supplyparam) > 0) {
						$supplyParams[$supplyKey] = $supplyparam;
					}

					if( $stock[$o] > 0 ){
						$tot_stock += (int) $stock[$o];
					}
				}
			// 필수옵션 미사용
			} else {
				// 배열로 넘어온 경우 0번째 값으로 변경함.
				foreach( $this->m_aOptionMultiRowCell as $fld ) {
					if( is_array($$fld) ) {
						$$fld	= ${$fld}[0];
					}
				}

				$params						= array();
				if ($option_seq && $action_option_kind == '수정') {
					$params['option_seq']   = $option_seq;
					
					// 입점사는 마일리지 update 제거
					if ($this->m_sAdminType == 'S') {
						unset($params['reserve_rate'], $params['reserve_unit'], $params['reserve']);
					}
				} 

				$params['default_option']	= 'y';
				$params['goods_seq']		= $goods_seq;
				$params['consumer_price']	= (int) $consumer_price;
				$params['price']			= (int) $price;
				$params['weight']			= $weight;
				$params['option_view']		= ($option_view == "미노출") ? 'N' : 'Y';

				if(!$params['weight']) {
					$params['weight'] = 0;
				}
				if($provider_info['commission_type'] == 'SACO' || $provider_info['commission_type'] == '') {
					// 수수료 방식
					if (isset($commission_rate)) {//0포함 체크
						if( strstr($commission_rate,"%") ) {//excel type : text @2015-11-16
							$commission_rate = str_replace('%', '', $commission_rate);
						}
						$commission_rate			= ($commission_rate < 1) ? $commission_rate * 100 : $commission_rate;
						$params['commission_rate']  = round($commission_rate - (0.5 / pow(10, 2)), 2);
						$params['commission_type']	= 'SACO';
					}
				} else {
					//공급가 방식
					if (isset($commission_price)) {//0포함 체크
						$now_unit			= '';
						$commission_type	= '';
						$commission_rate	= '';
						$commission_rate	= preg_replace('/원|%/','',$commission_price);
						preg_match('/(원|%)$/', $commission_price, $now_unit);
						if(!$now_unit[1])	$now_unit[1]	= ((int)$commission_rate > 100) ? '원' : '%';

						switch($now_unit[1]){
							case '원' :
								$params['commission_rate']	= (int)$commission_rate;
								$params['commission_type']	= 'SUPR';
							break;
							default :
								$commission_rate			= ($commission_rate < 1) ? $commission_rate * 100 : $commission_rate;
								$params['commission_rate']	= floor($commission_rate*100)/100;
								$params['commission_type']	= 'SUCO';
							break;
						}
					}
				}
				
				// 마일리지
				if ($reserve_rate && $this->m_sOptionReservePolicy != "shop") {
					$this->m_sOptionReservePolicy	= "goods";
					$tmp_reserve_arr				= $this->divide_price_and_unit($reserve_rate);
					$tmp_reserve_rate				= $tmp_reserve_arr['price'];
					$tmp_reserve_unit				= $tmp_reserve_arr['unit'];
					if ($tmp_reserve_unit == 'percent') {
						$tmp_reserve				= $price * ($tmp_reserve_rate / 100);
					} else {
						$tmp_reserve				= $tmp_reserve_rate;
					}
				} else {
					$this->m_sOptionReservePolicy	= "shop";
					$tmp_reserve_rate				= ($this->reserves['default_reserve_percent'])?$this->reserves['default_reserve_percent']:0;
					$tmp_reserve_unit				= 'percent';
					$tmp_reserve					= $price * ($tmp_reserve_rate / 100);
				}
				
				$params['reserve_rate']	= (int) $tmp_reserve_rate;
				$params['reserve_unit']	= $tmp_reserve_unit;
				$params['reserve']		= (int) $tmp_reserve;
				$params['goods_seq']	= $goods_seq;

				//입점사의 경우 기본수수료 적용
				if ($this->m_sAdminType == 'S') {
					$params['commission_type'] = $provider_info['commission_type'];
					$params['commission_rate'] = $provider_info['charge'];
				}
				
				if ($action_option_kind == '삭제' ) {
					$delOptions[] = $option_seq;
				}

				if ($params && $action_option_kind != '삭제') {
					if($option_seq > 0){
						$params['fix_option_seq'] = $option_seq;
					}
					
					//insert의 경우 모든 데이터의 key 값이 동일 해야 함.
					$paramsKeys	= array_keys($params);
					$keysDiff	  = array_diff($paramsKeys, $keys);
					if(count($keysDiff) > 0){
						$keys	  = array_merge($keys, $keysDiff);
					}
				
					$optionParams[] = $params;
					$goodsSeqs[]   = $goods_seq;
					$optionsSeqs[] = $params['option_seq'];
				}

				// supply 정보 update 및 insert
				$supplyparam = array();
				$supplyKey = count($optionParams) - 1;
				
				//업데이트 상품에 옵션 번호가 있고 수정 데이터 일때 재고 업데이트
				if ($action_option_kind  == '수정' && $option_seq > 0 && $this->m_sGoodsSaveType == 'update'){
					//올인원아니거나 올인원이면서 입점상품일때 재고추가 @2017-04-04
					if (!$this->m_bIsScm 
							|| ($this->m_bIsScm && $this->m_aProviderAsGoods[$goods_seq] 
								&& $this->m_aProviderAsGoods[$goods_seq] > 1) ){
						$supplyparam['stock']		= (int) $stock;
						$supplyparam['badstock']	= (int) $badstock;
						$supplyparam['supply_price']= (int) $supply_price;
					}
					$supplyparam['safe_stock']		= (int) $safe_stock;
				}else{
					//올인원이면서 본사상품만 재고 0 처리 @2017-04-04
					if ($this->m_bIsScm && ($this->m_aProviderAsGoods[$goods_seq] == 1 || $this->m_sServiceType	== 'N')) {
						$stock			= '0';
						$badstock		= '0';
						$supply_price	= '0';
					}
					$supplyparam['stock']			= (int) $stock;
					$supplyparam['badstock']		= (int) $badstock;
					$supplyparam['safe_stock']		= (int) $safe_stock;
					$supplyparam['supply_price']	= (int) $supply_price;
					$supplyparam['goods_seq']	   = (int) $goods_seq;
				}
				
				if ($action_option_kind != '삭제' && count($supplyparam) > 0) {
					$supplyParams[$supplyKey] = $supplyparam;
				}

				if ($stock > 0) {
					$tot_stock += (int) $stock;
				}
			}

			if ($tot_stock > 0) {
				$returnData[$goods_seq]['update']['tot_stock'] = $tot_stock;
			}

			// 입점사는 마일리지 update 제거
			if ($this->m_sAdminType != 'S' || $this->m_sGoodsSaveType != 'update') {
				$returnData[$goods_seq]['update']['reserve_policy'] = $this->m_sOptionReservePolicy;
			}

			// 입점사 일경우 미승인 처리
			if( $this->m_sAdminType == 'S' ) {
				$returnData[$goods_seq]['update']['goods_status']				= 'unsold';
				$returnData[$goods_seq]['update']['goods_view']					= 'notLook';
				$returnData[$goods_seq]['update']['provider_status']			= '0';
				$returnData[$goods_seq]['update']['provider_status_reason_type']= '5';
				$returnData[$goods_seq]['update']['provider_status_reason']		= '[자동] 엑셀 업로드 시 미승인 처리됨';
			}
			
			//삭제 요청 옵션/재고 삭제
			if (count($delOptions) > 0) {
				$delRes[$goods_seq] = $this->delete_target_options($goods_seq, 'option', $delOptions);
			}
		}

		$goodsSeqs	= array_unique($goodsSeqs);
		$optionsSeqs  = array_unique($optionsSeqs);
		$optionsSeqs  = array_filter($optionsSeqs);
		
		if (count($optionParams) > 0) {
			if (count($optionsSeqs) > 0) {
				//기존 데이터 삭제
				$this->db->where_in('option_seq', $optionsSeqs);
				$this->db->delete('fm_goods_option');
			}
			
			//bulk insert의 경우 모든 데이터의 key 갯수와 순서가 동일해야 함
			foreach($optionParams as $k => &$options){
				$array_modified = false;
				foreach($keys as $key){
					if(!isset($options[$key])) {
						$options[$key] = '';
						$array_modified = true;
					}
				}
				if($array_modified) {
					$tmp_array = [];
					foreach($keys as $key) {
						$tmp_array[$key] = $options[$key];
					}
					$options = $tmp_array;
					unset($tmp_array);
				}
			}
			
			$this->db->insert_batch('fm_goods_option', $optionParams, true, count($optionParams)); //옵션데이터 insert
			$firstOptionId	= $this->db->insert_id();
			
			$supplyInsert   = array();
			$supplyUpdate   = array();	
			$oldSeq		 = 0;
			$fixOptionSeqs  = array();
			
			if ($firstOptionId > 0) {
				foreach($optionParams as $k => $v){
					if($k > 0 && $v['goods_seq'] == $oldSeq){
						$keyNO++;
					} else {
						$keyNO = 0;
					}
					
					if ($v['option_seq']) { //기존 데이터
						$fixOptionSeqs[$k]['option_seq']		= $v['option_seq'];
						$fixOptionSeqs[$k]['fix_option_seq']	= $v['option_seq'];
						
						if($supplyParams[$k]){
							$supplyUpdate[$k]			   = $supplyParams[$k];
							$supplyUpdate[$k]['option_seq'] = $v['option_seq'];
						}
						
						//기초재고 등록 변수
						$this->m_aAddOptionInfoList[$v['goods_seq']][$keyNO]['option_seq'] = $v['option_seq'];
					} else {
						$fixOptionSeqs[$k]['option_seq']		= $firstOptionId;
						$fixOptionSeqs[$k]['fix_option_seq']	= $firstOptionId;
						
						if($supplyParams[$k]){
							$supplyInsert[$k]			   = $supplyParams[$k];
							$supplyInsert[$k]['option_seq'] = $firstOptionId;
						}
						//기초재고 등록 변수
						$this->m_aAddOptionInfoList[$v['goods_seq']][$keyNO]['option_seq'] = $firstOptionId;
						
						$firstOptionId++;
					}
					
					$oldSeq = $v['goods_seq'];

					$codeName = "";
					$optionName = "";
					for($i=1; $i<=5; $i++){
						$codeName .= $v['optioncode'.$i];
						$optionName .= $v['option'.$i];
					}
					$this->m_aAddOptionInfoList[$v['goods_seq']][$keyNO]['option_code'] = $codeName;
					$this->m_aAddOptionInfoList[$v['goods_seq']][$keyNO]['option_name'] = $optionName;	 

					if($v['default_option'] == 'y'){
						$returnData[$v['goods_seq']]['update']['default_consumer_price'] = $v['consumer_price'];
						$returnData[$v['goods_seq']]['update']['default_price'] = $v['price'];

						if ($v['consumer_price'] > $v['price']) {
							$returnData[$v['goods_seq']]['update']['default_discount'] = $v['consumer_price'] - $v['price'];
						} else {
							$returnData[$v['goods_seq']]['update']['default_discount'] = 0;
						}
					}
				}
			}
			
			if (count($fixOptionSeqs) > 0) {
				$this->db->update_batch('fm_goods_option', $fixOptionSeqs, 'option_seq', true, count($fixOptionSeqs));
			}

			if (count($supplyInsert) > 0) { 
				$this->db->insert_batch('fm_goods_supply', $supplyInsert, true, count($supplyInsert));
			}
			
			if (count($supplyUpdate) > 0) { 
				$this->db->update_batch('fm_goods_supply', $supplyUpdate, 'option_seq', true, count($supplyUpdate));
			}
			
			foreach($goodsSeqs as $goodsSeq){
				$returnData[$goodsSeq]['status'] = true;
			}
		}

		unset($goodsSeqs, $optionParams, $supplyParams, $supplyInsert, $supplyUpdate);
		return $returnData;
	}

	// 추가옵션 데이터 저장
	public function _upload_exception_suboption($goods_seq, $data){

		// 물류관리 사용 시 예외처리.
		if	($this->m_bIsScm && !$this->scmmodel->scm_use_suboption_mode){
			return array('status' => true, 'goodsUpdateParam' => array());
		}else{

			$goodsParams		= array();
			$provider_info		= $this->m_aProviderList[$this->m_aProviderAsGoods[$goods_seq]];

			// 티켓상품은 추가옵션이 없다.
			if	($this->m_sGoodsKind == 'C'){
				return array('status' => false, 'msg' => '추가옵션 저장 실패 : 티켓상품은 추가옵션을 지원하지 않습니다.');
			}

			// 줄바꿈 구분자 cell 데이터들을 배열화
			foreach($this->m_aSuboptionMultiRowCell as $c => $fld){

				// 마일리지가 전혀 없으면 행수체크를 pass한다. ( 통합정책으로 전환 )
				if	($fld == 'sub_reserve_rate' && !trim($data[$fld]))	continue;
				///추가옵션동작구분 : 프로그램단에서 자동처리되어 체크예외처리함 @2016-07-12 ysm
				if	($fld == 'action_suboption_kind' && !trim($data[$fld]))	continue;

				$nCnt	= 0;
				$$fld	= explode(chr($this->m_sUploadLineCharString), $data[$fld]);
				if	(in_array($fld, $this->m_aSuboptionRowCountCell)){
					$nCnt	= count($$fld);
				}

				if	($nCnt > 0 && $bCnt > 0 && $nCnt != $bCnt){
					return array('status' => false, 'msg' => '추가옵션 저장 실패 : 추가옵션 행수 불일치');
				}
				if	($nCnt > 0)	$bCnt	= $nCnt;
			}

			// 상품이 insert일 경우 기존 option_seq는 무시함.
			if	($this->m_sGoodsSaveType == 'insert'){
				$option_seq	= array();

				// 새로 등록
				$this->db->delete('fm_goods_suboption', array('goods_seq'=>$goods_seq));
				$this->delete_goods_supply($goods_seq, 'suboption');
			}

			if	($suboption){

				// 필수여부
				if	($data['sub_required'])	$sub_required	= explode('^', $data['sub_required']);
				if	($data['sub_sale'])		$sub_sale		= explode('^', $data['sub_sale']);

				foreach($suboption as $s => $sub){

					unset($subParams);
					$tmp			= $this->upload_special_option_name($suboption_title[$s]);
					$title			= $tmp['title'];
					$newType		= $tmp['newtype'];
					if	($newType){
						$tmp	= $this->get_divide_special_option($newType, $sub);
						if	($tmp) foreach($tmp as $f => $v){
							if	($f == 'option' && $v)	$sub			= $v;
							else						$subParams[$f]	= $v;
						}
					}

					if	($sub && $title){

						$goodsParams['option_suboption_use']	= '1';

						$subParams['suboption_title']		= $title;
						$subParams['suboption']				= $sub;
						$subParams['consumer_price']		= $sub_consumer_price[$s];
						$subParams['suboption_code']		= $suboption_code[$s];
						$subParams['price']					= $sub_price[$s];
						$subParams['sub_required']			= 'N';
						$subParams['sub_sale']				= 'N';
						$subParams['weight']				= $sub_weight[$s];
						$subParams['option_view']			= ($sub_option_view[$s] == '미노출') ? 'N' : 'Y';
						$subParams['newtype']				= $newType;
						if	(is_array($sub_required)){
							if	(in_array($title, $sub_required))	$subParams['sub_required']	= 'Y';
						}
						if	(is_array($sub_sale)){
							if	(in_array($title, $sub_sale))		$subParams['sub_sale']		= 'Y';
						}

						// 수수료
						if	($sub_commission_rate[$s]){
							$sub_commission_rate[$s]		= str_replace('%', '', $sub_commission_rate[$s]);
							$sub_commission_rate[$s]		= ($sub_commission_rate[$s] < 1) ? $sub_commission_rate[$s] * 100 : $sub_commission_rate[$s];
							$subParams['commission_rate']	= floor($sub_commission_rate[$s]*100)/100;
						}


						if($provider_info['commission_type'] == 'SACO' || $provider_info['commission_type'] == ''){
							// 수수료 방식
							if	($sub_commission_rate[$s]){
								$sub_commission_rate[$s]		= str_replace('%', '', $sub_commission_rate[$s]);
								$sub_commission_rate[$s]		= ($sub_commission_rate[$s] < 1) ? $sub_commission_rate[$s] * 100 : $sub_commission_rate[$s];
								$subParams['commission_rate']	= floor($sub_commission_rate[$s]*100)/100;
								$subParams['commission_type']	= 'SACO';
							}
						}else{
							//공급가 방식
							if	($sub_commission_price[$s]){
								$now_unit			= '';
								$commission_type	= '';
								$commission_rate	= '';
								$commission_rate	= preg_replace('/원|%/','',$sub_commission_price[$s]);
								preg_match('/(원|%)$/', $sub_commission_price[$s], $now_unit);

								if(!$now_unit[1])	$now_unit[1]	= ((int)$commission_rate > 100) ? '원' : '%';

								switch($now_unit[1]){
									case	'원' :
										$subParams['commission_rate']	= (int)$commission_rate;
										$subParams['commission_type']	= 'SUPR';
										break;
									default :
										$commission_rate				= ($commission_rate < 1) ? $commission_rate * 100 : $commission_rate;
										$subParams['commission_rate']	= floor($commission_rate*100)/100;
										$subParams['commission_type']	= 'SUCO';
										break;
								}
							}
						}

						// 마일리지
						if	($sub_reserve_rate[$s] && $this->m_sOptionReservePolicy != "shop"){
							$tmp_reserve_arr	= $this->divide_price_and_unit($sub_reserve_rate[$s]);
							$tmp_reserve_rate	= $tmp_reserve_arr['price'];
							$tmp_reserve_unit	= $tmp_reserve_arr['unit'];
							if	($tmp_reserve_unit == 'percent'){
								$tmp_reserve	= $sub_price[$s] * ($tmp_reserve_rate / 100);
							}else{
								$tmp_reserve	= $tmp_reserve_rate;
							}
						}else{
							$tmp_reserve_rate	= ($this->reserves['default_reserve_percent'])?$this->reserves['default_reserve_percent']:0;
							$tmp_reserve_unit	= 'percent';
							$tmp_reserve		= $sub_price[$s] * ($tmp_reserve_rate / 100);
						}
						$subParams['reserve_rate']		= (int) $tmp_reserve_rate;
						$subParams['reserve_unit']		= $tmp_reserve_unit;
						$subParams['reserve']			= (int) $tmp_reserve;


						// 상품이 수정이고 option_seq가 있을 경우 update, 없으면 insert
						if	($this->m_sGoodsSaveType == 'update' && $suboption_seq[$s] > 0){
							// 입점사는 마일리지 update 제거
							if	($this->m_sAdminType == 'S'){
								unset($subParams['reserve_rate'], $subParams['reserve_unit'], $subParams['reserve']);
							}

							$this->db->where(array('suboption_seq'=>$suboption_seq[$s],'goods_seq'=> $goods_seq));
							$this->db->update('fm_goods_suboption', $subParams);
							$option_save_type			= 'update';
							$subSeq						= $suboption_seq[$s];
						}else{
							$subParams['goods_seq']		= $goods_seq;

							//입점사의 경우 기본수수료 적용
							if($this->m_sAdminType == 'S'){
								$subParams['commission_type']		= $provider_info['commission_type'];
								$subParams['commission_rate']		= $provider_info['charge'];
							}

							$this->db->insert('fm_goods_suboption', $subParams);
							$subSeq						= $this->db->insert_id();
							$option_save_type			= 'insert';
						}

						// supply 정보 update 및 insert
						unset($supplyparam);
						if	($option_save_type == 'update' && $subSeq > 0 && $goods_seq > 0){
							if	(!$this->m_bIsScm){
								$supplyparam['stock']				= (int) $sub_stock[$s];
								$supplyparam['badstock']			= (int) $sub_badstock[$s];
								$supplyparam['supply_price']		= (int) $sub_supply_price[$s];
							}
							$supplyparam['safe_stock']				= (int) $sub_safe_stock[$s];
							$this->db->where(array('suboption_seq'=>$subSeq,'goods_seq'=> $goods_seq));
							$this->db->update('fm_goods_supply', $supplyparam);
						}else{
							if	($this->m_bIsScm){
								$sub_stock[$s]			= '0';
								$sub_badstock[$s]		= '0';
								$sub_supply_price[$s]	= '0';
							}
							$supplyparam['stock']				= (int) $sub_stock[$s];
							$supplyparam['badstock']			= (int) $sub_badstock[$s];
							$supplyparam['safe_stock']			= (int) $sub_safe_stock[$s];
							$supplyparam['supply_price']		= (int) $sub_supply_price[$s];
							$supplyparam['goods_seq']			= (int) $goods_seq;
							$supplyparam['suboption_seq']		= $subSeq;
							$this->db->insert('fm_goods_supply', $supplyparam);
						}

						if	($subSeq > 0){
							$saved_option[]		= $subSeq;
							if	($action_suboption_kind[$s] == '삭제')	$delete_option[]	= $subSeq;
						}
					}
				}
			}

			// 위에서 save된 suboption외의 suboption들 삭제
			if	(count($delete_option) > 0){
				$this->delete_target_options($goods_seq, 'suboption', $delete_option);
			}

			//옵션고유번호 없을때 기존옵션삭제하지 않고 생성되는 문제로 수정 @2016-07-13 ysm
			if	(!$this->m_bIsScm && count($saved_option) > 0){
				$this->delete_remain_options($goods_seq, 'suboption', $saved_option);
			}

			// 추가옵션을 사용안하는 경우에는 자주쓰는추가옵션도 삭제되도록 수정 2018-09-07
			if( count($suboption) > 0 && count($suboption) == count($delete_option) ) {
				$goodsParams['option_suboption_use']	= '0';
				$goodsParams['frequentlysub']			= '0';
			}

			// 입점사 일경우 미승인 처리
			if	($this->m_sAdminType == 'S'){
				$goodsParams['goods_status']					= 'unsold';
				$goodsParams['goods_view']						= 'notLook';
				$goodsParams['provider_status']					= '0';
				$goodsParams['provider_status_reason_type']		= '5';
				$goodsParams['provider_status_reason']			= '[자동] 엑셀 업로드 시 미승인 처리됨';
			}

			return array('status' => true, 'goodsUpdateParam' => $goodsParams);
		}
	}

	public function _upload_exception_suboption_batch($suboptions) {
		$optionParams	 = array();
		$supplyParams	 = array();
		$returnData	   = array();
		$goodsSeqs		  = array();
		$optionsSeqs	  = array();
		$delOptions		  = array();
		$keys			 = array('0' => 'goods_seq');

		foreach ($suboptions as $goods_seq => $data) {
			if(!array_filter($data)){ //빈 데이터 일 경우 패스
				continue;
			}
			
			$returnData[$goods_seq]['status'] = NULL;
			
			// 신규등록시 : 물류관리 사용 시 예외처리.
			// 수정 시 : 물류관리 버전이라도 수정 가능
			if ( $this->m_bIsScm && !$this->scmmodel->scm_use_suboption_mode && $this->m_sGoodsSaveType == 'insert') {
				$returnData[$goods_seq]['status'] = true;
			} else {
				$provider_info = $this->m_aProviderList[$this->m_aProviderAsGoods[$goods_seq]];

				// 티켓상품은 추가옵션이 없다.
				if ($this->m_sGoodsKind == 'C') {
					$returnData[$goods_seq]['status']	= false;
					$returnData[$goods_seq]['msg']		= '추가옵션 저장 실패 : 티켓상품은 추가옵션을 지원하지 않습니다.';
					continue;
				}

				// 줄바꿈 구분자 cell 데이터들을 배열화
				foreach ($this->m_aSuboptionMultiRowCell as $fld) {
					// 마일리지가 전혀 없으면 행수체크를 pass한다. ( 통합정책으로 전환 )
					if ($fld == 'sub_reserve_rate' && !trim($data[$fld])) {
						continue;
					}
					///추가옵션동작구분 : 프로그램단에서 자동처리되어 체크예외처리함 @2016-07-12 ysm
					if ($fld == 'action_suboption_kind' && !trim($data[$fld]))	continue;

					$nCnt = 0;
					$$fld = explode(chr($this->m_sUploadLineCharString), $data[$fld]);
					if (in_array($fld, $this->m_aSuboptionRowCountCell)) {
						$nCnt	= count($$fld);
					}

					if ($nCnt > 0 && $bCnt > 0 && $nCnt != $bCnt) {
						$returnData[$goods_seq]['status']	= false;
						$returnData[$goods_seq]['msg']		= '추가옵션 저장 실패 : 추가옵션 행수 불일치';
						continue;
					}

					if ($nCnt > 0) {
						$bCnt = $nCnt;
					}
				}

				if ($suboption) {
					// 필수여부
					if ($data['sub_required']) {
						$sub_required	= explode('^', $data['sub_required']);
					}

					if ($data['sub_sale'])	{
						$sub_sale		= explode('^', $data['sub_sale']);
					}

					foreach ($suboption as $s => $sub) {
						$subParams	= array();
						$tmp		= $this->upload_special_option_name($suboption_title[$s]);
						$title		= $tmp['title'];
						$newType	= $tmp['newtype'];
						if ($newType) {
							$tmp	= $this->get_divide_special_option($newType, $sub);
							if ($tmp) {
								foreach ($tmp as $f => $v) {
									if ($f == 'option' && $v) {
										$sub = $v;
									} else {
										$subParams[$f]	= $v;
									}
								}
							}
						}

						if ($sub && $title) {
							//$returnData[$goods_seq]['update']['option_use'] = '1';

							if($suboption_seq[$s] && $action_suboption_kind[$s] == '수정'){
								$subParams['suboption_seq']	= $suboption_seq[$s];
							}
							
							$subParams['suboption_title']	= $title;
							$subParams['suboption']			= $sub;
							$subParams['consumer_price']	= $sub_consumer_price[$s];
							$subParams['suboption_code']	= $suboption_code[$s];
							$subParams['price']				= $sub_price[$s];
							$subParams['sub_required']		= 'N';
							$subParams['sub_sale']			= 'N';
							$subParams['weight']			= $sub_weight[$s];
							$subParams['option_view']		= ($sub_option_view[$s] == '미노출') ? 'N' : 'Y';
							$subParams['newtype']			= $newType;
							$subParams['commission_rate']	= '';
							$subParams['commission_type']	= '';

							if (is_array($sub_required)) {
								if (in_array($title, $sub_required)) {
									$subParams['sub_required'] = 'Y';
								}
							}

							if (is_array($sub_sale)) {
								if (in_array($title, $sub_sale)) {
									$subParams['sub_sale'] = 'Y';
								}
							}

							// 수수료
							if ($sub_commission_rate[$s]){
								$sub_commission_rate[$s]		= str_replace('%', '', $sub_commission_rate[$s]);
								$sub_commission_rate[$s]		= ($sub_commission_rate[$s] < 1) ? $sub_commission_rate[$s] * 100 : $sub_commission_rate[$s];
								$subParams['commission_rate']	= floor($sub_commission_rate[$s]*100)/100;
							}

							if ($provider_info['commission_type'] == 'SACO' || $provider_info['commission_type'] == '') {
								// 수수료 방식
								if	($sub_commission_rate[$s]){
									$sub_commission_rate[$s]		= str_replace('%', '', $sub_commission_rate[$s]);
									$sub_commission_rate[$s]		= ($sub_commission_rate[$s] < 1) ? $sub_commission_rate[$s] * 100 : $sub_commission_rate[$s];
									$subParams['commission_rate']	= floor($sub_commission_rate[$s]*100)/100;
									$subParams['commission_type']	= 'SACO';
								}
							} else {
								//공급가 방식
								if ($sub_commission_price[$s]) {
									$now_unit			= '';
									$commission_type	= '';
									$commission_rate	= '';
									$commission_rate	= preg_replace('/원|%/','',$sub_commission_price[$s]);
									preg_match('/(원|%)$/', $sub_commission_price[$s], $now_unit);

									if (!$now_unit[1]) {
										$now_unit[1]	= ((int)$commission_rate > 100) ? '원' : '%';
									}

									switch ($now_unit[1]) {
										case '원' :
											$subParams['commission_rate']	= (int)$commission_rate;
											$subParams['commission_type']	= 'SUPR';
											break;
										default :
											$commission_rate				= ($commission_rate < 1) ? $commission_rate * 100 : $commission_rate;
											$subParams['commission_rate']	= floor($commission_rate*100)/100;
											$subParams['commission_type']	= 'SUCO';
											break;
									}
								}
							}

							// 마일리지
							if ($sub_reserve_rate[$s] && $this->m_sOptionReservePolicy != "shop") {
								$tmp_reserve_arr	= $this->divide_price_and_unit($sub_reserve_rate[$s]);
								$tmp_reserve_rate	= $tmp_reserve_arr['price'];
								$tmp_reserve_unit	= $tmp_reserve_arr['unit'];
								if ($tmp_reserve_unit == 'percent') {
									$tmp_reserve	= $sub_price[$s] * ($tmp_reserve_rate / 100);
								} else {
									$tmp_reserve	= $tmp_reserve_rate;
								}
							} else {
								$tmp_reserve_rate		= ($this->reserves['default_reserve_percent'])?$this->reserves['default_reserve_percent']:0;
								$tmp_reserve_unit		= 'percent';
								$tmp_reserve			= $sub_price[$s] * ($tmp_reserve_rate / 100);
							}

							$subParams['reserve_rate']	= (int) $tmp_reserve_rate;
							$subParams['reserve_unit']	= $tmp_reserve_unit;
							$subParams['reserve']		= (int) $tmp_reserve;
							$subParams['goods_seq']		= $goods_seq;

							//입점사의 경우 기본수수료 적용
							if ($this->m_sAdminType == 'S') {
								$subParams['commission_type'] = $provider_info['commission_type'];
								$subParams['commission_rate'] = $provider_info['charge'];
							}

							if($action_suboption_kind[$s] == '삭제'){
								$delOptions[] = $suboption_seq[$s];
							}
							
							//insert의 경우 모든 데이터의 key 값이 동일 해야 함.
							$paramsKeys	= array_keys($subParams);
							$keysDiff	  = array_diff($paramsKeys, $keys);
							if(count($keysDiff) > 0){
								$keys	  = array_merge($keys, $keysDiff);
							}
							
							if ($subParams && $action_suboption_kind[$s] && $action_suboption_kind[$s]  != '삭제') {
								$optionParams[]  = $subParams;
								$goodsSeqs[]	 = $goods_seq;
								$optionsSeqs[]   = $subParams['suboption_seq'];
							}

							
							// supply 정보 update 및 insert
							$supplyparam = array();
							$supplyKey = count($optionParams) - 1;
							$supplyparam['goods_seq'] = (int) $goods_seq;
							
							if	($this->m_sGoodsSaveType == 'update' 
									 && $suboption_seq[$s] > 0 && $action_suboption_kind[$s] == '수정'){
								if	(!$this->m_bIsScm){
									$supplyparam['stock']				= (int) $sub_stock[$s];
									$supplyparam['badstock']			= (int) $sub_badstock[$s];
									$supplyparam['supply_price']		= (int) $sub_supply_price[$s];
								}
								$supplyparam['safe_stock']				= (int) $sub_safe_stock[$s];
							}else{
								if ($this->m_bIsScm) {
									$sub_stock[$s]			= '0';
									$sub_badstock[$s]		= '0';
									$sub_supply_price[$s]	= '0';
								}
								$supplyparam['stock']				= (int) $sub_stock[$s];
								$supplyparam['badstock']			= (int) $sub_badstock[$s];
								$supplyparam['safe_stock']			= (int) $sub_safe_stock[$s];
								$supplyparam['supply_price']		= (int) $sub_supply_price[$s];
							}
							
							if ($action_suboption_kind[$s]  != '삭제' && count($supplyparam) > 0) {
								$supplyParams[$supplyKey] = $supplyparam;
							}
						}
					}
				}
			}
			
			// 입점사 일경우 미승인 처리
			if ($this->m_sAdminType == 'S') {
				$returnData[$goods_seq]['update']['goods_status']				= 'unsold';
				$returnData[$goods_seq]['update']['goods_view']					= 'notLook';
				$returnData[$goods_seq]['update']['provider_status']			= '0';
				$returnData[$goods_seq]['update']['provider_status_reason_type']= '5';
				$returnData[$goods_seq]['update']['provider_status_reason']		= '[자동] 엑셀 업로드 시 미승인 처리됨';
			}
			
			//삭제 요청 옵션/재고 삭제
			if (count($delOptions) > 0) {
				$this->delete_target_options($goods_seq, 'suboption', $delOptions);
			}
		}
		
		$goodsSeqs	= array_unique($goodsSeqs);
		$optionsSeqs  = array_unique($optionsSeqs);
		$optionsSeqs  = array_filter($optionsSeqs);
		
		if (count($optionParams) > 0) {
			//기존 옵션 데이터 삭제
			if (count($optionsSeqs) > 0) {
				$this->db->where_in('suboption_seq', $optionsSeqs);
				$this->db->delete('fm_goods_suboption');
			}

			//bulk insert의 경우 모든 데이터의 key 갯수와 순서가 동일해야 함
			foreach($optionParams as $k => &$options){
				$array_modified = false;
				foreach($keys as $key){
					if(!isset($options[$key])) {
						$options[$key] = '';
						$array_modified = true;
					}
				}
				if($array_modified) {
					$tmp_array = [];
					foreach($keys as $key) {
						$tmp_array[$key] = $options[$key];
					}
					$options = $tmp_array;
					unset($tmp_array);
				}
			}
			
			$this->db->insert_batch('fm_goods_suboption', $optionParams, true, count($optionParams)); //추가 건 insert
			$firstOptionId	= $this->db->insert_id();
			
			$supplyInsert   = array();
			$supplyUpdate   = array();	
			
			if ($firstOptionId > 0) {
				foreach($optionParams as $k => $v){
	 				if ($v['suboption_seq']) { //기존 데이터
						if($supplyParams[$k]){
							$supplyUpdate[$k]				  = $supplyParams[$k];
							$supplyUpdate[$k]['suboption_seq'] = $v['suboption_seq'];
						}
					} else {
						if($supplyParams[$k]){
							$supplyInsert[$k]				  = $supplyParams[$k];
							$supplyInsert[$k]['suboption_seq'] = $firstOptionId;
						}
						$firstOptionId++;
					}
				}

				if (count($supplyInsert) > 0) {
					$this->db->insert_batch('fm_goods_supply', $supplyInsert, true, count($supplyInsert));
				}
				
				if (count($supplyUpdate) > 0) {
					$this->db->update_batch('fm_goods_supply', $supplyUpdate, 'suboption_seq', true, count($supplyUpdate));
				}
			}
			
			foreach($goodsSeqs as $goodsSeq){
				$returnData[$goodsSeq]['status'] = true;
			}
			
			$returnData[$goodsSeq]['update']['option_suboption_use'] = true;
		}

		unset($goodsSeqs, $optionsSeqs, $optionParams, $supplyParams, $supplyInsert, $supplyUpdate);

		return $returnData;
	}

	// 입력옵션 데이터 저장
	public function _upload_exception_input($goods_seq, $data){

		// 줄바꿈 구분자 cell 데이터들을 배열화
		foreach($this->m_aInputMultiRowCell as $c => $fld){
			$nCnt	= 0;
			$$fld	= explode(chr($this->m_sUploadLineCharString), $data[$fld]);
			$nCnt	= count($$fld);

			if	($nCnt > 0 && $bCnt > 0 && $nCnt != $bCnt){
				return array('status' => false, 'msg' => '입력옵션 저장 실패 : 입력옵션 행수 불일치');
			}
			if	($nCnt > 0)	$bCnt	= $nCnt;
		}

		$this->db->delete('fm_goods_input', array('goods_seq'=>$goods_seq));

		if	($input_name){

			// 새로 등록
			if	($data['input_require'])	$input_require	= explode('^', $data['input_require']);

			foreach($input_name as $i => $name){
				if	($name){
					unset($inputParam);
					$inputParam['goods_seq']	= $goods_seq;
					$inputParam['input_name']	= $name;

					$tmp	= explode('^', $input_form[$i]);
					if		($tmp[0] == '이미지')	$form	= 'file';
					elseif	($tmp[0] == '에디터')	$form	= 'edit';
					else							$form	= 'text';
					$inputParam['input_form']		= $form;
					$inputParam['input_limit']		= $tmp[1];
					$inputParam['input_require']	= '0';
					if	($input_require){
						if	(in_array(trim($name), $input_require))	$inputParam['input_require']	= '1';
					}

					$input_insert_row++;
					$this->db->insert('fm_goods_input', $inputParam);
				}
			}
		}

		if	($input_insert_row > 0){
			$goodsParams['member_input_use']	= '1';
		}

		return array('status' => true, 'goodsUpdateParam' => $goodsParams);
	}

	// 입력옵션 데이터 저장
	public function _upload_exception_input_batch($input){
		$optionParams	 = array();
		$returnData	   = array();
		$goodsSeqs		  = array();
		$keys			 = array('0' => 'goods_seq');

		foreach($input as $goods_seq => $data){
			// 줄바꿈 구분자 cell 데이터들을 배열화
			foreach($this->m_aInputMultiRowCell as $c => $fld){
				
				$nCnt = 0;
				$$fld = explode(chr($this->m_sUploadLineCharString), $data[$fld]);
				$$fld = array_filter($$fld);
				$nCnt = count($$fld);

				if ($nCnt > 0 && $bCnt > 0 && $nCnt != $bCnt) {
					$returnData[$goods_seq]['status']	= false;
					$returnData[$goods_seq]['msg']		= '입력옵션 저장 실패 : 입력옵션 행수 불일치';
					continue;
				}

				if ($nCnt > 0) {
					$bCnt = $nCnt;
				}
			}

			if ($input_name) {
				// 새로 등록
				if ($data['input_require']) {
					$input_require = explode('^', $data['input_require']);
				}

				foreach ($input_name as $i => $name) {
					if ($name) {
						$inputParam = array();
						$inputParam['goods_seq']	= $goods_seq;
						$inputParam['input_name']	= $name;

						$tmp = explode('^', $input_form[$i]);
						if ($tmp[0] == '이미지') {
							$form	= 'file';
						} else if ($tmp[0] == '에디터') {
							$form	= 'edit';
						} else {
							$form	= 'text';
						}
						$inputParam['input_form']		= $form;
						$inputParam['input_limit']		= $tmp[1];
						$inputParam['input_require']	= '0';
						if ($input_require) {
							if (in_array(trim($name), $input_require)) {
								$inputParam['input_require'] = '1';
							}
						}

						$optionParams[] = $inputParam;
						$goodsSeqs[]	= $goods_seq;
						
						//bulk insert의 경우 key 값이 모두 동일 해야 함
						$paramsKeys	= array_keys($inputParam);
						$keysDiff	  = array_diff($paramsKeys, $keys);
						
						if(count($keysDiff) > 0){
							$keys	  = array_merge($keys, $keysDiff);
						}
					}
				}
			}
		}

		$goodsSeqs = array_unique($goodsSeqs);

		if( count($optionParams) > 0 ){
			if(count($goodsSeqs) > 0){
				//기존 옵션 데이터 삭제
				$this->db->where_in('goods_seq', $goodsSeqs);
				$this->db->delete('fm_goods_input');
			}

			//bulk insert의 경우 모든 데이터의 key 갯수와 순서가 동일해야 함
			foreach($optionParams as $k => &$options){
				$array_modified = false;
				foreach($keys as $key){
					if(!isset($options[$key])) {
						$options[$key] = '';
						$array_modified = true;
					}
				}
				if($array_modified) {
					$tmp_array = [];
					foreach($keys as $key) {
						$tmp_array[$key] = $options[$key];
					}
					$options = $tmp_array;
					unset($tmp_array);
				}
			}
			
			//데이터 bulk 입력 option
			$this->db->insert_batch('fm_goods_input', $optionParams, true, count($optionParams));
			if ($this->db->insert_id() > 0) {
				foreach($goodsSeqs as $goodsSeq){
					$returnData[$goodsSeq]['status']						= true;
					$returnData[$goodsSeq]['update']['member_input_use']	= true;
				}
			}
		}
		unset($goodsSeqs, $optionParams);

		return $returnData;
	}

	// 카테고리 데이터 저장
	public function _upload_exception_category($goods_seq, $data){

		$this->load->model("Excelgoodsmodel");
		$oldcategory = $this->Excelgoodsmodel->get_goods_category_sort($goods_seq);

		$category = explode(chr($this->m_sUploadLineCharString), $data['category']);
		$lastCategory = end($category);

		//상위 카테고리 검색을 위해 정렬 18.11.16 kmj
		usort($category, function($a, $b) {
			return str_pad($a, 16, 0) - str_pad($b, 16, 0) ?: strcmp($a, $b);
		});

		// 카테고리 유효성 검사 (1depth, 2depth, 3depth......)
		if	($category) foreach($category as $c => $code){
			if($code){
				$codeLen = strlen($code); // 코드 자리수
				$preCodeLen = strlen($preCode); // 이전 코드 자리수
				if($codeLen%4 !== 0){
					return array('status' => false, 'msg' => '카테고리 코드('.$code.') 길이가 올바르지 않습니다.');
				}

				if($c==0 && $codeLen > 4){ // 첫번째 입력값을 4자리 이상의 코드를 입력했을 경우
					return array('status' => false, 'msg' => '[0] 카테고리 코드('.$code.') 입력형식이 올바르지 않습니다.');
				}else{
					if($codeLen > 4){
						if($codeLen == $preCodeLen){
							if(substr($code,0,$codeLen-4) != substr($preCode,0,$preCodeLen-4)){ // 이전배열의 카테고리코드와 부모 코드가 다를 경우
								return array('status' => false, 'msg' => '[1] 카테고리 코드('.$code.') 입력형식이 올바르지 않습니다.');
							}
						}elseif($codeLen < $preCodeLen){ // 1뎁스,2뎁스,3뎁스 순서로 등록되지 않았으며 부모 카테고리가 아닐 경우
							if(!preg_match("/^".substr($code,0,$codeLen-4)."/",substr($preCode,0,$preCodeLen-4))){
								return array('status' => false, 'msg' => '[2] 카테고리 코드('.$code.') 입력형식이 올바르지 않습니다.');
							}
						}else{
							if($preCode != substr($code,0,$codeLen-4)){ // 이전 배열의 카테고리 코드가 부모가 아닌 경우
								return array('status' => false, 'msg' => '[3] 카테고리 코드('.$code.') 입력형식이 올바르지 않습니다.');
							}
						}
					}
				}
				$preCode = $code;
			}
		}

		$this->load->model('categorymodel');
		$this->db->delete('fm_category_link', array('goods_seq'=>$goods_seq));
		if	($category) foreach($category as $c => $code){
			if	($code){
				if	($lastCategory == $code)	$link	= '1';
				else							$link	= '0';

				// 카테고리 중복 제거 - 중복카테고리 Pass :: 2017-03-30 lwh
				if(in_array($code,$duple_catecode[$goods_seq]))
						continue;
				else	$duple_catecode[$goods_seq][] = $code;

				unset($cateParam);
				$cateParam['goods_seq']			= $goods_seq;
				$cateParam['category_code']		= $code;
				$cateParam['link']				= $link;
				$cateParam['regist_date']		= date('Y-m-d H:i:s');
				$cateParam['sort']				= ($oldcategory[$code]['sort'])?$oldcategory[$code]['sort']:0;
				$cateParam['mobile_sort']		= ($oldcategory[$code]['mobile_sort'])?$oldcategory[$code]['mobile_sort']:0;
				$result							= $this->db->insert('fm_category_link', $cateParam);

				if($result && empty($oldcategory[$code])) {//신규만
					$link_seq = $this->db->insert_id();

					$minsort			= $this->categorymodel->getSortValue($code, 'min');
					$mobile_minsort		= $this->categorymodel->getSortValue($code, 'mobile_min');
					$sort				= $minsort - 1;
					$mobile_sort		= $mobile_minsort - 1;
					
					$this->db->where('category_link_seq', $link_seq);
					$this->db->update('fm_category_link',array('sort'=>$sort,'mobile_sort'=>$mobile_sort));
				}
			}
		}

		return array('status' => true);
	}

	// 카테고리 데이터 저장
	public function _upload_exception_category_batch($categories){
		$this->load->model("Excelgoodsmodel");
		$this->load->model('categorymodel');

		$optionParams	 = array();
		$goodsSeqs		= array();
		$returnData	   = array();
		$keys			 = array('0' => 'goods_seq');

		foreach ($categories as $goods_seq => $data) {
			$returnData[$goods_seq]['status'] = NULL;

			$oldcategory = $this->Excelgoodsmodel->get_goods_category_sort($goods_seq);
			
			$category = explode(chr($this->m_sUploadLineCharString), $data['category']);
			$lastCategory = end($category);

			//상위 카테고리 검색을 위해 정렬 18.11.16 kmj
			usort($category, function($a, $b) {
				return str_pad($a, 16, 0) - str_pad($b, 16, 0) ?: strcmp($a, $b);
			});
		
			// 카테고리 유효성 검사 (1depth, 2depth, 3depth......)
			if ($category) {
				$category = array_unique($category);
				foreach ($category as $c => $code) {
					if ($code) {
						$codeLen	= strlen($code); // 코드 자리수
						$preCodeLen = strlen($preCode); // 이전 코드 자리수
						if($codeLen%4 !== 0){
							$returnData[$goods_seq]['status']	= false;
							$returnData[$goods_seq]['msg']		= '카테고리 코드('.$code.') 길이가 올바르지 않습니다.';
							continue;
						}

						if($c==0 && $codeLen > 4){ // 첫번째 입력값을 4자리 이상의 코드를 입력했을 경우
							$returnData[$goods_seq]['status']	= false;
							$returnData[$goods_seq]['msg']		= '[0] 카테고리 코드('.$code.') 입력형식이 올바르지 않습니다.';
							continue;
						}else{
							if($codeLen > 4){
								if($codeLen == $preCodeLen){
									if(substr($code,0,$codeLen-4) != substr($preCode,0,$preCodeLen-4)){ // 이전배열의 카테고리코드와 부모 코드가 다를 경우
										$returnData[$goods_seq]['status']	= false;
										$returnData[$goods_seq]['msg']		= '[1] 카테고리 코드('.$code.') 입력형식이 올바르지 않습니다.';
										continue;
									}
								}elseif($codeLen < $preCodeLen){ // 1뎁스,2뎁스,3뎁스 순서로 등록되지 않았으며 부모 카테고리가 아닐 경우
									if(!preg_match("/^".substr($code,0,$codeLen-4)."/",substr($preCode,0,$preCodeLen-4))){
										$returnData[$goods_seq]['status']	= false;
										$returnData[$goods_seq]['msg']		= '[2] 카테고리 코드('.$code.') 입력형식이 올바르지 않습니다.';
										continue;
									}
								}else{
									if($preCode != substr($code,0,$codeLen-4)){ // 이전 배열의 카테고리 코드가 부모가 아닌 경우
										$returnData[$goods_seq]['status']	= false;
										$returnData[$goods_seq]['msg']		= '[3] 카테고리 코드('.$code.') 입력형식이 올바르지 않습니다.';
										continue;
									}
								}
							}
						}
						$preCode = $code;

						if ($lastCategory == $code) {
							$link = '1';
						} else {
							$link = '0';
						}

						if($oldcategory[$code]['sort']) {
							$sort = $oldcategory[$code]['sort'];
						}else{
							$minsort			= $this->categorymodel->getSortValue($code, 'min');
							$sort				= $minsort - 1;
						}
						if($oldcategory[$code]['mobile_sort']) {
							$mobile_sort = $oldcategory[$code]['mobile_sort'];
						}else{
							$mobile_minsort		= $this->categorymodel->getSortValue($code, 'mobile_min');
							$mobile_sort		= $mobile_minsort - 1;
						}
						$cateParam = array();
						$cateParam['goods_seq']			= $goods_seq;
						$cateParam['category_code']		= $code;
						$cateParam['link']				= $link;
						$cateParam['regist_date']		= date('Y-m-d H:i:s');
						$cateParam['sort']				= $sort;
						$cateParam['mobile_sort']		= $mobile_sort;

						$optionParams[] = $cateParam;
						$goodsSeqs[]	= $goods_seq;
						
						$paramsKeys	= array_keys($cateParam);
						$keysDiff	  = array_diff($paramsKeys, $keys);
						if(count($keysDiff) > 0){
							$keys	  = array_merge($keys, $keysDiff);
						}
					}
				}
			}
		}
		
		$goodsSeqs = array_unique($goodsSeqs);

		if (count($optionParams) > 0) {
			if(count($goodsSeqs) > 0) {
				//기존 옵션 데이터 삭제
				$this->db->where_in('goods_seq', $goodsSeqs);
				$this->db->delete('fm_category_link');
			}

			//bulk insert의 경우 모든 데이터의 key 갯수와 순서가 동일해야 함
			foreach($optionParams as $k => &$options){
				$array_modified = false;
				foreach($keys as $key){
					if(!isset($options[$key])) {
						$options[$key] = '';
						$array_modified = true;
					}
				}
				if($array_modified) {
					$tmp_array = [];
					foreach($keys as $key) {
						$tmp_array[$key] = $options[$key];
					}
					$options = $tmp_array;
					unset($tmp_array);
				}
			}

			//데이터 bulk 입력 option
			$this->db->insert_batch('fm_category_link', $optionParams, true, count($optionParams));
			if ($this->db->insert_id() > 0) {
				foreach($goodsSeqs as $goodsSeq){
					$returnData[$goodsSeq]['status'] = true;
				}
			}
		}

		unset($goodsSeqs, $optionParams);
		return $returnData;
	}

	// 브랜드 데이터 저장
	public function _upload_exception_brand($goods_seq, $data){
		$brand		= explode(chr($this->m_sUploadLineCharString), $data['brand']);

		// 브랜드 유효성 검사 (1depth, 2depth, 3depth......)
		if	($brand) foreach($brand as $c => $code){
			if($code){
				$codeLen = strlen($code); // 코드 자리수
				$preCodeLen = strlen($preCode); // 이전 코드 자리수
				if($codeLen%4 !== 0){
					return array('status' => false, 'msg' => '브랜드 코드('.$code.') 길이가 올바르지 않습니다.');
				}

				if($c==0 && $codeLen > 4){ // 첫번째 입력값을 4자리 이상의 코드를 입력했을 경우
					return array('status' => false, 'msg' => '[0] 브랜드 코드('.$code.') 입력형식이 올바르지 않습니다.');
				}else{
					if($codeLen > 4){
						if($codeLen == $preCodeLen){
							if(substr($code,0,$codeLen-4) != substr($preCode,0,$preCodeLen-4)){ // 이전배열의 카테고리코드와 부모 코드가 다를 경우
								return array('status' => false, 'msg' => '[1] 브랜드 코드('.$code.') 입력형식이 올바르지 않습니다.');
							}
						}elseif($codeLen < $preCodeLen){ // 1뎁스,2뎁스,3뎁스 순서로 등록되지 않았으며 부모 카테고리가 아닐 경우
							if(!preg_match("/^".substr($code,0,$codeLen-4)."/",substr($preCode,0,$preCodeLen-4))){
								return array('status' => false, 'msg' => '[2] 브랜드 코드('.$code.') 입력형식이 올바르지 않습니다.');
							}
						}else{
							if($preCode != substr($code,0,$codeLen-4)){ // 이전 배열의 카테고리 코드가 부모가 아닌 경우
								return array('status' => false, 'msg' => '[3] 브랜드 코드('.$code.') 입력형식이 올바르지 않습니다.');
							}
						}
					}
				}
				$preCode = $code;
			}
		}

		$lastBrand	= end($brand);

		$this->db->delete('fm_brand_link', array('goods_seq'=>$goods_seq));
		if	($brand) foreach($brand as $c => $code){
			if	($code){
				if	($lastBrand == $code)	$link	= '1';
				else						$link	= '0';

				// 브랜드 중복 제거 - 중복브랜드 Pass :: 2017-03-30 lwh
				if(in_array($code,$duple_brandcode[$goods_seq]))
						continue;
				else	$duple_brandcode[$goods_seq][] = $code;

				unset($brandParam);
				$brandParam['goods_seq']		= $goods_seq;
				$brandParam['category_code']	= $code;
				$brandParam['link']				= $link;
				$brandParam['regist_date']		= date('Y-m-d H:i:s');
				$brandParam['sort']				= 0;
				$brandParam['mobile_sort']		= 0;
				$this->db->insert('fm_brand_link', $brandParam);
			}
		}

		return array('status' => true);
	}

	// 브랜드 데이터 저장
	public function _upload_exception_brand_batch($brands){
		$optionParams = array();
		$goodsSeqs	= array();
		$returnData   = array();
		$keys		 = array('0' => 'goods_seq');

		foreach ($brands as $goods_seq => $data) {
			$returnData[$goods_seq]['status'] = NULL;

			$brand		= explode(chr($this->m_sUploadLineCharString), $data['brand']);
			$lastBrand	= end($brand);

			//상위 카테고리 검색을 위해 정렬 18.11.16 kmj
			usort($brand, function($a, $b) {
				return str_pad($a, 16, 0) - str_pad($b, 16, 0) ?: strcmp($a, $b);
			});

			// 브랜드 유효성 검사 (1depth, 2depth, 3depth......)
			if ($brand) {
				$brand = array_unique($brand);
				foreach ($brand as $c => $code) {
					if ($code) {
						$codeLen = strlen($code); // 코드 자리수
						$preCodeLen = strlen($preCode); // 이전 코드 자리수
						if ($codeLen%4 !== 0) {
							$returnData[$goods_seq]['status']	= false;
							$returnData[$goods_seq]['msg']		= '브랜드 코드('.$code.') 길이가 올바르지 않습니다.';
							continue;
						}

						if ($c==0 && $codeLen > 4) { // 첫번째 입력값을 4자리 이상의 코드를 입력했을 경우
							$returnData[$goods_seq]['status']	= false;
							$returnData[$goods_seq]['msg']		= '[0] 브랜드 코드('.$code.') 입력형식이 올바르지 않습니다.';
							continue;
						} else {
							if($codeLen > 4){
								if($codeLen == $preCodeLen){
									if(substr($code,0,$codeLen-4) != substr($preCode,0,$preCodeLen-4)){ // 이전배열의 카테고리코드와 부모 코드가 다를 경우
										$returnData[$goods_seq]['status']	= false;
										$returnData[$goods_seq]['msg']		= '[1] 브랜드 코드('.$code.') 입력형식이 올바르지 않습니다.';
										continue;
									}
								}elseif($codeLen < $preCodeLen){ // 1뎁스,2뎁스,3뎁스 순서로 등록되지 않았으며 부모 카테고리가 아닐 경우
									if(!preg_match("/^".substr($code,0,$codeLen-4)."/",substr($preCode,0,$preCodeLen-4))){
										$returnData[$goods_seq]['status']	= false;
										$returnData[$goods_seq]['msg']		= '[2] 브랜드 코드('.$code.') 입력형식이 올바르지 않습니다.';
										continue;
									}
								}else{
									if($preCode != substr($code,0,$codeLen-4)){ // 이전 배열의 카테고리 코드가 부모가 아닌 경우
										$returnData[$goods_seq]['status']	= false;
										$returnData[$goods_seq]['msg']		= '[3] 브랜드 코드('.$code.') 입력형식이 올바르지 않습니다.';
										continue;
									}
								}
							}
						}
						$preCode = $code;

						if ($lastBrand == $code) {
							$link = '1';
						} else {
							$link = '0';
						}

						$brandParam						= array();
						$brandParam['goods_seq']		= $goods_seq;
						$brandParam['category_code']	= $code;
						$brandParam['link']				= $link;
						$brandParam['regist_date']		= date('Y-m-d H:i:s');
						$brandParam['sort']				= 0;
						$brandParam['mobile_sort']		= 0;
						
						$optionParams[] = $brandParam;
						$goodsSeqs[]	= $goods_seq;
						
						$paramsKeys	= array_keys($brandParam);
						$keysDiff	  = array_diff($paramsKeys, $keys);
						if(count($keysDiff) > 0){
							$keys	  = array_merge($keys, $keysDiff);
						}
					}
				}
			}
		}

		$goodsSeqs = array_unique($goodsSeqs);

		if( count($goodsSeqs) > 0 ){
			//기존 옵션 데이터 삭제
			$this->db->where_in('goods_seq', $goodsSeqs);
			$this->db->delete('fm_brand_link');
			
			//bulk insert의 경우 모든 데이터의 key 갯수와 순서가 동일해야 함
			foreach($optionParams as $k => &$options){
				$array_modified = false;
				foreach($keys as $key){
					if(!isset($options[$key])) {
						$options[$key] = '';
						$array_modified = true;
					}
				}
				if($array_modified) {
					$tmp_array = [];
					foreach($keys as $key) {
						$tmp_array[$key] = $options[$key];
					}
					$options = $tmp_array;
					unset($tmp_array);
				}
			}

			//데이터 bulk 입력 option
			$this->db->insert_batch('fm_brand_link', $optionParams, true, count($optionParams));
			if ($this->db->insert_id() > 0) {
				foreach($goodsSeqs as $goodsSeq){
					$returnData[$goodsSeq]['status'] = true;
				}
			}
		}

		unset($goodsSeqs, $optionParams);
		return $returnData;
	}

	// 지역 데이터 저장
	public function _upload_exception_location($goods_seq, $data){
		$location		= explode(chr($this->m_sUploadLineCharString), $data['location']);

		// 지역 유효성 검사 (1depth, 2depth, 3depth......)
		if	($location) foreach($location as $c => $code){
			if($code){
				$codeLen = strlen($code); // 코드 자리수
				$preCodeLen = strlen($preCode); // 이전 코드 자리수
				if($codeLen%4 !== 0){
					return array('status' => false, 'msg' => '지역 코드('.$code.') 길이가 올바르지 않습니다.');
				}

				if($c==0 && $codeLen > 4){ // 첫번째 입력값을 4자리 이상의 코드를 입력했을 경우
					return array('status' => false, 'msg' => '[0] 지역 코드('.$code.') 입력형식이 올바르지 않습니다.');
				}else{
					if($codeLen > 4){
						if($codeLen == $preCodeLen){
							if(substr($code,0,$codeLen-4) != substr($preCode,0,$preCodeLen-4)){ // 이전배열의 카테고리코드와 부모 코드가 다를 경우
								return array('status' => false, 'msg' => '[1] 지역 코드('.$code.') 입력형식이 올바르지 않습니다.');
							}
						}elseif($codeLen < $preCodeLen){ // 1뎁스,2뎁스,3뎁스 순서로 등록되지 않았으며 부모 카테고리가 아닐 경우
							if(!preg_match("/^".substr($code,0,$codeLen-4)."/",substr($preCode,0,$preCodeLen-4))){
								return array('status' => false, 'msg' => '[2] 지역 코드('.$code.') 입력형식이 올바르지 않습니다.');
							}
						}else{
							if($preCode != substr($code,0,$codeLen-4)){ // 이전 배열의 카테고리 코드가 부모가 아닌 경우
								return array('status' => false, 'msg' => '[3] 지역 코드('.$code.') 입력형식이 올바르지 않습니다.');
							}
						}
					}
				}
				$preCode = $code;
			}
		}

		$lastLocation	= end($location);

		$this->db->delete('fm_location_link', array('goods_seq'=>$goods_seq));
		if	($location) foreach($location as $c => $code){
			if	($code){
				if	($lastLocation == $code)	$link	= '1';
				else							$link	= '0';

				// 지역 중복 제거 - 중복지역 Pass :: 2017-03-30 lwh
				if(in_array($code,$duple_location[$goods_seq]))
						continue;
				else	$duple_location[$goods_seq][] = $code;

				unset($locParam);
				$locParam['goods_seq']			= $goods_seq;
				$locParam['location_code']		= $code;
				$locParam['link']				= $link;
				$locParam['regist_date']		= date('Y-m-d H:i:s');
				$locParam['sort']				= 0;
				$locParam['mobile_sort']		= 0;
				$this->db->insert('fm_location_link', $locParam);
			}
		}

		return array('status' => true);
	}

	// 지역 데이터 저장
	public function _upload_exception_location_batch($locations){
		$optionParams = array();
		$goodsSeqs	= array();
		$returnData   = array();
		$keys		 = array('0' => 'goods_seq');

		foreach ($locations as $goods_seq => $data) {
			$location		= explode(chr($this->m_sUploadLineCharString), $data['location']);
			$lastLocation	= end($location);

			//상위 카테고리 검색을 위해 정렬 18.11.16 kmj
			usort($location, function($a, $b) {
				return str_pad($a, 16, 0) - str_pad($b, 16, 0) ?: strcmp($a, $b);
			});

			// 지역 유효성 검사 (1depth, 2depth, 3depth......)
			if ($location) {
				$location = array_unique($location);
				foreach ($location as $c => $code) {
					if ($code) {
						$codeLen	= strlen($code); // 코드 자리수
						$preCodeLen = strlen($preCode); // 이전 코드 자리수
						if ($codeLen%4 !== 0) {
							$returnData[$goods_seq]['status']	= false;
							$returnData[$goods_seq]['msg']		= '지역 코드('.$code.') 길이가 올바르지 않습니다.';
							continue;
						}

						if($c==0 && $codeLen > 4) { // 첫번째 입력값을 4자리 이상의 코드를 입력했을 경우
							return array('status' => false, 'msg' => '[0] 지역 코드('.$code.') 입력형식이 올바르지 않습니다.');
						} else {
							if ($codeLen > 4) {
								if ($codeLen == $preCodeLen) {
									if(substr($code,0,$codeLen-4) != substr($preCode,0,$preCodeLen-4)){ // 이전배열의 카테고리코드와 부모 코드가 다를 경우
										$returnData[$goods_seq]['status']	= false;
										$returnData[$goods_seq]['msg']		= '[1] 지역 코드('.$code.') 입력형식이 올바르지 않습니다.';
										continue;
									}
								} elseif ($codeLen < $preCodeLen) { // 1뎁스,2뎁스,3뎁스 순서로 등록되지 않았으며 부모 카테고리가 아닐 경우
									if(!preg_match("/^".substr($code,0,$codeLen-4)."/",substr($preCode,0,$preCodeLen-4))){
										$returnData[$goods_seq]['status']	= false;
										$returnData[$goods_seq]['msg']		= '[2] 지역 코드('.$code.') 입력형식이 올바르지 않습니다.';
										continue;
									}
								} else {
									if ($preCode != substr($code,0,$codeLen-4)) { // 이전 배열의 카테고리 코드가 부모가 아닌 경우
										$returnData[$goods_seq]['status']	= false;
										$returnData[$goods_seq]['msg']		= '[3] 지역 코드('.$code.') 입력형식이 올바르지 않습니다.';
										continue;
									}
								}
							}
						}
						$preCode = $code;

						if ($lastLocation == $code) {
							$link = '1';
						} else {
							$link = '0';
						}

						$locParam						= array();
						$locParam['goods_seq']			= $goods_seq;
						$locParam['location_code']		= $code;
						$locParam['link']				= $link;
						$locParam['regist_date']		= date('Y-m-d H:i:s');
						$locParam['sort']				= 0;
						$locParam['mobile_sort']		= 0;

						$optionParams[] = $locParam;
						$goodsSeqs[]	= $goods_seq;
						
						$paramsKeys	= array_keys($locParam);
						$keysDiff	  = array_diff($paramsKeys, $keys);
						if(count($keysDiff) > 0){
							$keys	  = array_merge($keys, $keysDiff);
						}
					}
				}
			}
		}
		
		$goodsSeqs = array_unique($goodsSeqs);

		if( count($goodsSeqs) > 0 ){
			//기존 옵션 데이터 삭제
			$this->db->where_in('goods_seq', $goodsSeqs);
			$this->db->delete('fm_location_link');
			
			//bulk insert의 경우 모든 데이터의 key 갯수와 순서가 동일해야 함
			foreach($optionParams as $k => &$options){
				$array_modified = false;
				foreach($keys as $key){
					if(!isset($options[$key])) {
						$options[$key] = '';
						$array_modified = true;
					}
				}
				if($array_modified) {
					$tmp_array = [];
					foreach($keys as $key) {
						$tmp_array[$key] = $options[$key];
					}
					$options = $tmp_array;
					unset($tmp_array);
				}
			}

			//데이터 bulk 입력 option
			$this->db->insert_batch('fm_location_link', $optionParams, true, count($optionParams));
			if ($this->db->insert_id() > 0) {
				foreach($goodsSeqs as $goodsSeq){
					$returnData[$goodsSeq]['status'] = true;
				}
			}
		}

		unset($goodsSeqs, $optionParams);
		return $returnData;
	}

	// 추가정보 데이터 저장
	public function _upload_exception_addition($goods_seq, $data){
		// 미리 정의된 추가정보명
		$typeArr	= array('[모델명]'		=> 'model',
							'[브랜드]'		=> 'brand',
							'[제조사]'		=> 'manufacture',
							'[원산지]'		=> 'orgin');

		$this->db->delete('fm_goods_addition', array('goods_seq' => $goods_seq));
		$tmp1		= explode('^', trim($data['addition']));
		if	($tmp1) foreach($tmp1 as $a => $addStr){
			if	($addStr){
				$type		= 'direct';
				$code_seq = '0';
				$contents_title = '';
				$tmp2		= explode('=', $addStr);
				if( strstr($tmp2[0],"[상품코드]")) {//설정>상품코드 이용시
					$title			= $tmp2[1];
					$contents		= $tmp2[2];
					$contents_title	= $tmp2[3];
					$code_seq	= str_replace('[상품코드]',"", $tmp2[0]);
					$type			= "goodsaddinfo_".$code_seq;
				}else{
					$title		= $tmp2[0];
					$contents	= $tmp2[1];
					if ($typeArr[$title]) {
						$type	= 	$typeArr[$title];
						$title	= '';
					}
				}

				unset($addParam);
				$addParam['goods_seq']	= $goods_seq;
				$addParam['type']			= $type;
				$addParam['title']			= $title;
				$addParam['contents']		= $contents;
				$addParam['code_seq']	= $code_seq;
				$addParam['contents_title']	= $contents_title;
				$this->db->insert('fm_goods_addition', $addParam);
			}
		}

		return array('status' => true);
	}

	public function _upload_exception_addition_batch($addition){
		// 미리 정의된 추가정보명
		$typeArr	= array('[모델명]'		=> 'model',
							'[브랜드]'		=> 'brand',
							'[제조사]'		=> 'manufacture',
							'[원산지]'		=> 'orgin');

		$optionParams = array();
		$goodsSeqs	= array();
		$keys		 = array('0' => 'goods_seq');

		foreach($addition as $goods_seq => $data){
			$tmp1 = explode('^', trim($data['addition']));

			// 추가 정보가 없는 경우 goods_seq 삭제처리
			if (!array_filter($tmp1)) {
				$goodsSeqs[]	= $goods_seq;
			} else {
				foreach ($tmp1 as $a => $addStr) {
					if ($addStr) {
						$type				= 'direct';
						$code_seq			= '0';
						$contents_title		= '';
						$tmp2				= explode('=', $addStr);
						if ( strstr($tmp2[0],"[상품코드]")) {//설정>상품코드 이용시
							$title			= $tmp2[1];
							$contents		= $tmp2[2];
							$contents_title	= $tmp2[3];
							$code_seq		= str_replace('[상품코드]',"", $tmp2[0]);
							$type			= "goodsaddinfo_".$code_seq;
						} else {
							$title			= $tmp2[0];
							$contents		= $tmp2[1];
							if ($typeArr[$title]) {
								$type		= 	$typeArr[$title];
								$title		= '';
							}
						}

						$addParam					= array();
						$addParam['goods_seq']		= $goods_seq;
						$addParam['type']			= $type;
						$addParam['title']			= $title;
						$addParam['contents']		= $contents;
						$addParam['code_seq']		= $code_seq;
						$addParam['contents_title']	= $contents_title;

						$optionParams[] = $addParam;
						$goodsSeqs[]	= $goods_seq;
						
						$paramsKeys	= array_keys($addParam);
						$keysDiff	  = array_diff($paramsKeys, $keys);
						if(count($keysDiff) > 0){
							$keys	  = array_merge($keys, $keysDiff);
						}
					}
				}
			}
		}

		$returnData = array();
		$goodsSeqs = array_unique($goodsSeqs);

		if (count($goodsSeqs) > 0) {
			//기존 옵션 데이터 삭제
			$this->db->where_in('goods_seq', $goodsSeqs);
			$this->db->delete('fm_goods_addition');
		}

		if (count($optionParams) > 0 ) {
			
			//bulk insert의 경우 모든 데이터의 key 갯수와 순서가 동일해야 함
			foreach($optionParams as $k => &$options){
				$array_modified = false;
				foreach($keys as $key){
					if(!isset($options[$key])) {
						$options[$key] = '';
						$array_modified = true;
					}
				}
				if($array_modified) {
					$tmp_array = [];
					foreach($keys as $key) {
						$tmp_array[$key] = $options[$key];
					}
					$options = $tmp_array;
					unset($tmp_array);
				}
			}

			//데이터 bulk 입력 option
			$this->db->insert_batch('fm_goods_addition', $optionParams, true, count($optionParams));
			if ($this->db->insert_id() > 0) {
				foreach($goodsSeqs as $goodsSeq){
					$returnData[$goodsSeq]['status'] = true;
				}
			}
		}

		unset($goodsSeqs, $optionParams);
		return $returnData;
	}

	// 아이콘 데이터 저장
	public function _upload_exception_icon($goods_seq, $data){
		$icon	= explode(chr($this->m_sUploadLineCharString), $data['icon']);
		$this->db->delete('fm_goods_icon', array('goods_seq' => $goods_seq));

		if	($icon) foreach($icon as $i => $iconStr){
			if	(trim($iconStr)){
				$tmp1	= explode('=', $iconStr);
				$tmp2	= explode('~', $tmp1[1]);

				unset($iconParam);
				$iconParam['goods_seq']		= $goods_seq;
				$iconParam['codecd']		= $tmp1[0];
				$iconParam['start_date']	= $tmp2[0];
				$iconParam['end_date']		= $tmp2[1];
				$this->db->insert('fm_goods_icon', $iconParam);
			}
		}

		return array('status' => true);
	}

	// 아이콘 데이터 저장
	public function _upload_exception_icon_batch($icons){
		$optionParams = array();
		$goodsSeqs	= array();
		$returnData   = array();
		$keys		 = array('0' => 'goods_seq');

		foreach($icons as $goods_seq => $data){
			$icon = explode(chr($this->m_sUploadLineCharString), $data['icon']);
			// 아이콘이 없는 경우 goods_seq 삭제처리
			if (!array_filter($icon)) {
				$goodsSeqs[]	= $goods_seq;
			} else {
				foreach ($icon as $iconStr) {
					if (trim($iconStr)) {
						$tmp1	= explode('=', $iconStr);
						$tmp2	= explode('~', $tmp1[1]);

						$iconParam					= array();
						$iconParam['goods_seq']		= $goods_seq;
						$iconParam['codecd']		= $tmp1[0];
						$iconParam['start_date']	= $tmp2[0];
						$iconParam['end_date']		= $tmp2[1];

						$optionParams[] = $iconParam;
						$goodsSeqs[]	= $goods_seq;
						
						$paramsKeys	= array_keys($iconParam);
						$keysDiff	  = array_diff($paramsKeys, $keys);
						if(count($keysDiff) > 0){
							$keys	  = array_merge($keys, $keysDiff);
						}
					}
				}
			}
		}

		$goodsSeqs = array_unique($goodsSeqs);

		if (count($goodsSeqs) > 0) {
			//기존 옵션 데이터 삭제
			$this->db->where_in('goods_seq', $goodsSeqs);
			$this->db->delete('fm_goods_icon');
		}
	
		if (count($optionParams) > 0) {
			
			//bulk insert의 경우 모든 데이터의 key 갯수와 순서가 동일해야 함
			foreach($optionParams as $k => &$options){
				$array_modified = false;
				foreach($keys as $key){
					if(!isset($options[$key])) {
						$options[$key] = '';
						$array_modified = true;
					}
				}
				if($array_modified) {
					$tmp_array = [];
					foreach($keys as $key) {
						$tmp_array[$key] = $options[$key];
					}
					$options = $tmp_array;
					unset($tmp_array);
				}
			}

			//데이터 bulk 입력 option
			$this->db->insert_batch('fm_goods_icon', $optionParams, true, count($optionParams));
			if ($this->db->insert_id() > 0) {
				foreach($goodsSeqs as $goodsSeq){
					$returnData[$goodsSeq]['status'] = true;
				}
			}
		}

		unset($goodsSeqs, $optionParams);
		return $returnData;
	}

	// 이미지 데이터 저장
	public function _upload_exception_image($goods_seq, $data){

		$typeArr		= array_keys($this->m_aImageName);
		$imgrealnum	= count($typeArr);//이미지 기본갯수
		$dataArr			= array_keys($data);
		if	($typeArr) foreach($typeArr as $k => $type){
			if(!in_array($type, $dataArr)) $imgcellnum++;//엑셀 이미지항목과 기본항목체크
			$$type			= explode(chr($this->m_sUploadLineCharString), $data[$type]);
		}

		if(!$imgcellnum){//이미지 기본전체이면 초기화 @2016-09-29
			$this->db->delete('fm_goods_image', array('goods_seq' => $goods_seq));
		}

		if	($list1) foreach($typeArr as $k => $type){
			if	($$type) {
				if( $imgcellnum && in_array($type, $dataArr))
					$this->db->delete('fm_goods_image', array('goods_seq' => $goods_seq,'image_type' => $type));

				foreach($$type as $i => $img){
					if	($img){

						$idx	= $i + 1;
						$imgParam['goods_seq']	= $goods_seq;
						$imgParam['cut_number']	= $idx;
						$imgParam['image_type']	= $type;
						$imgParam['image']		= $img;
						$this->db->insert('fm_goods_image', $imgParam);
					}
				}
			}
		}

		return array('status' => true);
	}

	public function _upload_exception_image_batch($images)
	{
		$optionParams = array();
		$goodsSeqs	= array();
		$returnData   = array();
		$keys		 = array('0' => 'goods_seq');

		foreach ($images as $goods_seq => $data) {
			$typeArr = array_keys($this->m_aImageName);

			if ($typeArr) {
				foreach($typeArr as $type){
					$$type = explode(chr($this->m_sUploadLineCharString), $data[$type]);
				}
			}

			if ($list1) {
				$goodsSeqs[] = $goods_seq;
				foreach($typeArr as $type) {
					if ($$type) {
						foreach ($$type as $i => $img) {
							if ($img) {
								$idx	= $i + 1;
								$imgParam			   = array();
								$imgParam['goods_seq']	= $goods_seq;
								$imgParam['cut_number']	= $idx;
								$imgParam['image_type']	= $type;
								$imgParam['image']		= $img;

								$optionParams[] = $imgParam;
								
								$paramsKeys	= array_keys($imgParam);
								$keysDiff	  = array_diff($paramsKeys, $keys);
								if(count($keysDiff) > 0){
									$keys	  = array_merge($keys, $keysDiff);
								}
							}
						}
					}
				}
			}
		}

		$goodsSeqs = array_unique($goodsSeqs);
		$delSeqs   = array_unique($delSeqs);

		if (count($optionParams) > 0){
			if (count($goodsSeqs) > 0) {
				//기존 데이터 삭제
				$this->db->where_in('goods_seq', $goodsSeqs);
				$this->db->delete('fm_goods_image');
			}
			
			//bulk insert의 경우 모든 데이터의 key 갯수와 순서가 동일해야 함
			foreach($optionParams as $k => &$options){
				$array_modified = false;
				foreach($keys as $key){
					if(!isset($options[$key])) {
						$options[$key] = '';
						$array_modified = true;
					}
				}
				if($array_modified) {
					$tmp_array = [];
					foreach($keys as $key) {
						$tmp_array[$key] = $options[$key];
					}
					$options = $tmp_array;
					unset($tmp_array);
				}
			}
			
			//데이터 bulk 입력 option
			$this->db->insert_batch('fm_goods_image', $optionParams, true, count($optionParams));
			if ($this->db->insert_id() > 0) {
				foreach($goodsSeqs as $goodsSeq){
					$returnData[$goodsSeq]['status'] = true;
				}
			}
		}

		unset($goodsSeqs, $optionParams);
		return $returnData;
	}

	// 관련상품 데이터 저장
	public function _upload_exception_relation($goods_seq, $data)
	{

		$relation	= explode('^', $data['relation']);
		$this->db->delete('fm_goods_relation', array('goods_seq' => $goods_seq));
		if	(is_array($relation) && count($relation) > 0){
			foreach($relation as $r => $seq){
				if	(is_numeric($seq) && trim($seq) > 0){
					$save_relation_goods_status		= true;
					unset($relParam);
					$relParam['goods_seq']			= $goods_seq;
					$relParam['relation_goods_seq']	= $seq;
					$this->db->insert('fm_goods_relation', $relParam);
				}
			}
		}

		$goodsParams['relation_type']			= 'AUTO';
		if	($save_relation_goods_status)
			$goodsParams['relation_type']		= 'MANUAL';

		return array('status' => true, 'goodsUpdateParam' => $goodsParams);
	}
	
	public function _upload_exception_relation_batch($datas)
	{
		$optionParams  = array();
		$goodsSeqs	 = array();
		$delSeqs	   = array();
		$returnData	= array();   
		$keys		  = array('0' => 'goods_seq');
		
		foreach ($datas as $goods_seq => $data) {
			$goodsSeqs[] = $goods_seq;
			$relation  = explode('^', $data['relation']);
			if (is_array($relation) && count($relation) > 0){
				foreach($relation as $seq){
					if	(is_numeric($seq) && trim($seq) > 0){ 
						$params						= array();
						$params['goods_seq']		   = $goods_seq;
						$params['relation_goods_seq']  = $seq;
						
						$optionParams[] = $params;
						
						$paramsKeys	= array_keys($params);
						$keysDiff	  = array_diff($paramsKeys, $keys);
						if(count($keysDiff) > 0){
							$keys	  = array_merge($keys, $keysDiff);
						}
					}
				}
			} 
		}
		
		$goodsSeqs = array_unique($goodsSeqs);
		$delSeqs   = array_unique($delSeqs);
		
		if (count($optionParams) > 0) {
			if (count($goodsSeqs) > 0) {
				//기존 옵션 데이터 삭제
				$this->db->where_in('goods_seq', $goodsSeqs);
				$this->db->delete('fm_goods_relation');
			}
			
			//bulk insert의 경우 모든 데이터의 key 갯수와 순서가 동일해야 함
			foreach($optionParams as $k => &$options){
				$array_modified = false;
				foreach($keys as $key){
					if(!isset($options[$key])) {
						$options[$key] = '';
						$array_modified = true;
					}
				}
				if($array_modified) {
					$tmp_array = [];
					foreach($keys as $key) {
						$tmp_array[$key] = $options[$key];
					}
					$options = $tmp_array;
					unset($tmp_array);
				}
			}
			
			//데이터 bulk 입력 option
			$this->db->insert_batch('fm_goods_relation', $optionParams, true, count($optionParams));
			if ($this->db->insert_id() > 0) {
				foreach($goodsSeqs as $goodsSeq){
					$returnData[$goodsSeq]['status'] = true;
				}
			}
		}
		
		unset($goodsSeqs, $optionParams);
		return $returnData; 
	}

	// 관련상품 데이터 저장
	public function _upload_exception_relation_seller($goods_seq, $data){

		$relation	= explode('^', $data['relation']);
		$this->db->delete('fm_goods_relation_seller', array('goods_seq' => $goods_seq));
		if	(is_array($relation) && count($relation) > 0){
			foreach($relation as $r => $seq){
				if	(is_numeric($seq) && trim($seq) > 0){
					$save_relation_goods_status		= true;
					unset($relParam);
					$relParam['goods_seq']			= $goods_seq;
					$relParam['relation_goods_seq']	= $seq;
					$this->db->insert('fm_goods_relation_seller', $relParam);
				}
			}
		}

		$goodsParams['relation_seller_type']			= 'AUTO';
		if	($save_relation_goods_status)
			$goodsParams['relation_seller_type']		= 'MANUAL';

		return array('status' => true, 'goodsUpdateParam' => $goodsParams);
	}

	// 관련상품 데이터 저장
	public function _upload_exception_relation_seller_batch($relations){
		$optionParams  = array();
		$goodsSeqs	 = array();
		$returnData	= array(); 
		$keys		  = array('0' => 'goods_seq');

		foreach ($relations as $goods_seq => $data) {
			$goods_seq[] = $goods_seq;
			$relation  = explode('^', $data['relation']);
			
			if (is_array($relation) && count($relation) > 0) {
				foreach($relation as $seq){
					if (is_numeric($seq) && trim($seq) > 0){
						$params						= array();
						$params['goods_seq']		   = $goods_seq;
						$params['relation_goods_seq']  = $seq;
						
						$optionParams[] = $params;
						
						$paramsKeys	= array_keys($params);
						$keysDiff	  = array_diff($paramsKeys, $keys);
						if(count($keysDiff) > 0){
							$keys	  = array_merge($keys, $keysDiff);
						}
					}
				}
			}
		}
		
		$goodsSeqs = array_unique($goodsSeqs);
		$delSeqs   = array_unique($delSeqs);
		
		if( count($optionParams) > 0 ){
			if( count($goods_seq) > 0 ){
				//기존 옵션 데이터 삭제
				$this->db->where_in('goods_seq', $goods_seq);
				$this->db->delete('fm_goods_relation_seller');
			}
			
			//bulk insert의 경우 모든 데이터의 key 갯수와 순서가 동일해야 함
			foreach($optionParams as $k => &$options){
				$array_modified = false;
				foreach($keys as $key){
					if(!isset($options[$key])) {
						$options[$key] = '';
						$array_modified = true;
					}
				}
				if($array_modified) {
					$tmp_array = [];
					foreach($keys as $key) {
						$tmp_array[$key] = $options[$key];
					}
					$options = $tmp_array;
					unset($tmp_array);
				}
			}
			
			//데이터 bulk 입력 option
			$this->db->insert_batch('fm_goods_relation_seller', $optionParams, true, count($optionParams));
			if ($this->db->insert_id() > 0) {
				foreach($goodsSeqs as $goodsSeq){
					$returnData[$goodsSeq]['status'] = true;
				}
			}
		}

		unset($goodsSeqs, $optionParams);
		return $returnData;	   
	}
	
	// 기초재고 입력
	public function _upload_exception_warehouse($goods_seq, $data){
		if	($this->m_bIsScm){
			$goodsData				= $this->m_aCurrentGoodsInfo;
			$scm_use_revision		= explode(chr($this->m_sUploadLineCharString), $data['scm_use_revision']);
			$location_stock			= explode(chr($this->m_sUploadLineCharString), $data['location_stock']);
			$location_supply_price	= explode(chr($this->m_sUploadLineCharString), $data['location_supply_price']);
			$locationData			= $this->m_aLocationList;
			$revisionSeq			= '';
			if	($this->m_aAddOptionInfoList)foreach($this->m_aAddOptionInfoList as $idx => $optioninfo){
				if	($scm_use_revision[$idx] == 'Y'){
					$option_seq		= $optioninfo['option_seq'];
					$option_code	= $optioninfo['option_code'];
					$option_name	= $optioninfo['option_name'];
					$supply_price	= $location_supply_price[$idx];
					$tmp			= str_replace(' ', '', trim($location_stock[$idx]));
					$tmpList		= explode(',', $tmp);
					if	($tmpList) foreach($tmpList as $k => $linestr){
						$tmpData	= explode('=', $linestr);
						$wh_seq		= trim($tmpData[0]);
						$locArr		= explode('-', $tmpData[1]);
						$ea			= trim($tmpData[2]);
						$bad_ea		= trim($tmpData[3]);
						if	($ea > 0){
							if	(!$locationData[$wh_seq]){
								unset($sc);
								$sc['wh_seq']	= $wh_seq;
								$locationData[$wh_seq]	= $this->scmmodel->get_location($sc);
							}
							if	($locationData[$wh_seq]){
								$location		= $locationData[$wh_seq];
								$location_info	= $location[$locArr[0]][$locArr[1]][$locArr[2]];

								if	($location_info){
									$location_position	= $location_info['location_position'];
									$location_code		= $location_info['location_code'];
								}else{
									$location_info		= $location[1][1][1];
									$location_position	= $location_info['location_position'];
									$location_code		= $location_info['location_code'];
								}

								$r_seq	= $revisionSeq[$wh_seq];
								unset($params);
								$params['supply_price_type']	= 'KRW';
								$params['ea']					= $ea;
								$params['supply_price']			= $supply_price;
								$params['tax']					= $goodsData['tax'];
								$params['goods_seq']			= $goods_seq;
								$params['goods_code']			= $goodsData['goods_code'] . $option_code;
								$params['goods_name']			= $goodsData['goods_name'];
								$params['option_type']			= 'option';
								$params['option_seq']			= $option_seq;
								$params['option_name']			= $option_name;
								$params['location_position']	= $location_position;
								$params['location_code']		= $location_code;
								$rData	= $this->scmmodel->save_default_revision($wh_seq, $params, $r_seq);
								$cDate	= date('Y-m-d', strtotime($rData['complete_date']));
								$revisionSeq[$wh_seq]			= $rData['revision_seq'];
								$targetGoods					= array('goods_seq'		=> $goods_seq,
																		'option_type'	=> 'option',
																		'option_seq'	=> $option_seq);
								$this->m_aChgGoodsTarget[$wh_seq]['complete_date']	= $cDate;
								$this->m_aChgGoodsTarget[$wh_seq]['goods'][]		= $targetGoods;
							}else{
								$this->save_upload_log('failed', '[' . $goodsData['goods_name'] . '/' . $goods_seq . ' > ' . $option_name . ']의 창고번호가 잘못되어 기초재고가 등록되지 않았습니다.' ."\r\n");
							}
						}
					}
				}
			}
		}
		return array('status' => true);
	}

	// 기초재고 입력
	public function _upload_exception_warehouse_batch($datas)
	{
		$locationData  = $this->m_aLocationList;
		
		foreach ($datas as $goods_seq => $data) {
			$scm_use_revision		= explode(chr($this->m_sUploadLineCharString), $data['scm_use_revision']);
			$location_stock			= explode(chr($this->m_sUploadLineCharString), $data['location_stock']);
			$location_supply_price	= explode(chr($this->m_sUploadLineCharString), $data['location_supply_price']);
			$revisionSeq			= '';

			if ($this->m_aAddOptionInfoList[$goods_seq]) {
				foreach ($this->m_aAddOptionInfoList[$goods_seq] as $idx => $optioninfo) {
					if ($scm_use_revision[$idx] == 'Y') {
						$option_seq		= $optioninfo['option_seq'];
						$option_code	= $optioninfo['option_code'];
						$option_name	= $optioninfo['option_name'];
						$supply_price	= $location_supply_price[$idx];
						$tmp			= str_replace(' ', '', trim($location_stock[$idx]));
						$tmpList		= explode(',', $tmp);
						
						if ($tmpList) {
							foreach ($tmpList as $linestr) {
								$tmpData	= explode('=', $linestr);
								$wh_seq		= trim($tmpData[0]);
								$locArr		= explode('-', $tmpData[1]);
								$ea			= trim($tmpData[2]);
								$bad_ea		= trim($tmpData[3]);
								
								if ($ea > 0) {
									if (!$locationData[$wh_seq]) {
										$sc					= array();
										$sc['wh_seq']		  = $wh_seq;
										$locationData[$wh_seq] = $this->scmmodel->get_location($sc);
									}
									
									if ($locationData[$wh_seq]) {
										$location		= $locationData[$wh_seq];
										$location_info	= $location[$locArr[0]][$locArr[1]][$locArr[2]];
										
										if ($location_info) {
											$location_position	= $location_info['location_position'];
											$location_code		= $location_info['location_code'];
										} else {
											$location_info		= $location[1][1][1];
											$location_position	= $location_info['location_position'];
											$location_code		= $location_info['location_code'];
										}
										
										$r_seq	= $revisionSeq[$wh_seq];
										
										$params						= array();
										$params['supply_price_type']   = 'KRW';
										$params['ea']				  = $ea;
										$params['supply_price']		= $supply_price;
										$params['tax']				 = $datas[$goods_seq]['tax'];
										$params['goods_seq']		   = $goods_seq;
										$params['goods_code']		  = $datas[$goods_seq]['goods_code'] . $option_code;
										$params['goods_name']		  = $datas[$goods_seq]['goods_name'];
										$params['option_type']		 = 'option';
										$params['option_seq']		  = $option_seq;
										$params['option_name']		 = $option_name;
										$params['location_position']   = $location_position;
										$params['location_code']	   = $location_code;

										$rData				 = $this->scmmodel->save_default_revision($wh_seq, $params, $r_seq);
										$cDate				 = date('Y-m-d', strtotime($rData['complete_date']));
										$revisionSeq[$wh_seq]  = $rData['revision_seq'];
										$targetGoods		   = array(
											'goods_seq'		=> $goods_seq,
											'option_type'	=> 'option',
											'option_seq'	=> $option_seq

										);
										
										$this->m_aChgGoodsTarget[$wh_seq]['complete_date']	= $cDate;
										$this->m_aChgGoodsTarget[$wh_seq]['goods'][]		= $targetGoods;
									} else {
										$this->save_upload_log('failed', '[' . $goodsData['goods_name'] . '/' . $goods_seq . ' > ' . $option_name . ']의 창고번호가 잘못되어 기초재고가 등록되지 않았습니다.' ."\r\n");
									}
								}
							}
						}
					}
				}
			}
		}

		return array('status' => true);
	}
	
	// 선택된 옵션 외의 옵션들은 삭제 ( No Index Type Slow Query )
	public function delete_remain_options($goods_seq, $type, $saved_option){
		if	($type == 'option'){
			$tbName	= 'fm_goods_option';
			$seqFld	= 'option_seq';
		}elseif	($type == 'suboption'){
			$tbName	= 'fm_goods_suboption';
			$seqFld	= 'suboption_seq';
		}

		if	($goods_seq > 0 && count($saved_option) > 0){
			$sql	= "delete from " . $tbName . " where goods_seq = ? "
					. "and " . $seqFld . " not in ('" . implode("', '", $saved_option) . "')";
			$this->db->query($sql, array($goods_seq));

			$sql	= "delete from fm_goods_supply where goods_seq = ? and " . $seqFld . " > 0 "
					. "and " . $seqFld . " not in ('" . implode("', '", $saved_option) . "')";
			$this->db->query($sql, array($goods_seq));
		}
	}

	// 선택된 옵션만 삭제
	public function delete_target_options($goods_seq, $type, $delete_option){

		// 물류관리용 지워도 되는 옵션 목록을 재생성.
		if	($this->m_bIsScm){
			if	(count($delete_option) > 0) foreach($delete_option as $k => $option_seq){
				unset($sc);
				$sc['goods_seq']	= $goods_seq;
				$sc['option_type']	= $type;
				$sc['option_seq']	= $option_seq;
				$data		= $this->scmmodel->get_location_stock($sc);
				$totalStock	= $data[0]['ea'];
				if	( !($totalStock > 0) )	$tmp_delete_option[]	= $option_seq;
			}
			$delete_option	= '';	unset($delete_option);
			$delete_option	= $tmp_delete_option;
		}

		if	($type == 'option'){
			$tbName	= 'fm_goods_option';
			$seqFld	= 'option_seq';
		}elseif	($type == 'suboption'){
			$tbName	= 'fm_goods_suboption';
			$seqFld	= 'suboption_seq';
		}

		if	($goods_seq > 0 && count($delete_option) > 0){
			$sql	= "delete from " . $tbName . " where goods_seq = ? "
					. "and " . $seqFld . " in ('" . implode("', '", $delete_option) . "')";
			$this->db->query($sql, array($goods_seq));

			$sql	= "delete from fm_goods_supply where goods_seq = ? and " . $seqFld . " > 0 "
					. "and " . $seqFld . " in ('" . implode("', '", $delete_option) . "')";
			$this->db->query($sql, array($goods_seq));
		}
	}

	// 상품 테이블 업로드 예외처리
	public function get_upload_goods_change_val($fld, &$goods){

		switch($fld){
			case 'provider_status':
				if	($goods['provider_status'] == '승인')	{
					$goods['provider_status']					= '1';
				}else{
					$goods['goods_status']						= 'unsold';
					$goods['goods_view']						= 'notLook';
					$goods['provider_status']					= '0';
					$goods['provider_status_reason_type']		= '5';
					$goods['provider_status_reason']			= '[수동] 관리자 엑셀 업로드 시 미승인 처리됨';
				}
			break;
			case 'goods_status':
				if		($goods['goods_status'] == '정상')	$goods['goods_status']		= 'normal';
				elseif	($goods['goods_status'] == '품절')	$goods['goods_status']		= 'runout';
				elseif	($goods['goods_status'] == '재입고')	$goods['goods_status']		= 'purchasing';
				else										$goods['goods_status']		= 'unsold';
			break;
			case 'goods_view':
				if		($goods['goods_view'] == '노출')	{
					$goods['display_terms']				= 'MENUAL';
					$goods['goods_view']				= 'look';
				}elseif	($goods['goods_view'] == '미노출')	{
					$goods['display_terms']				= 'MENUAL';
					$goods['goods_view']				= 'notLook';
				}else{
					$tmp1								= explode('^', $goods['goods_view']);
					$tmp2								= explode('=', $tmp1[1]);
					$tmp3								= explode('-', $tmp2[0]);

					$goods['display_terms']				= 'AUTO';
					if	($tmp1[0] == '노출')				$goods['display_terms_before']	= 'DISPLAY';
					else								$goods['display_terms_before']	= 'CONCEAL';
					if	($tmp1[2] == '노출')				$goods['display_terms_after']	= 'DISPLAY';
					else								$goods['display_terms_after']	= 'CONCEAL';
					$goods['display_terms_begin']		= date('Y-m-d', strtotime($tmp3[0]));
					$goods['display_terms_end']			= date('Y-m-d', strtotime($tmp3[1]));
					if	($tmp2[1] == 'Y')				$goods['display_terms_type']	= 'LAYAWAY';
					else								$goods['display_terms_type']	= 'SELLING';
					$goods['display_terms_text']		= $tmp2[2];
					$goods['possible_shipping_date']	= date('Y-m-d', strtotime($tmp2[3]));
					$goods['possible_shipping_text']	= $tmp2[4];
				}
			break;
			case 'runout_policy':
				if	($goods['runout_policy'] == '통합정책'){
					$goods['runout_policy']				= '';
					$goods['able_stock_limit']			= '';
				}else{
					if		($goods['runout_policy'] == '개별정책=재고연동'){
						$goods['runout_policy']			= 'stock';
						$goods['able_stock_limit']		= '';
					}elseif	($goods['runout_policy'] == '개별정책=재고무관'){
						$goods['runout_policy']			= 'unlimited';
						$goods['able_stock_limit']		= '';
					}else{
						$tmp_policy						= explode('^', $goods['runout_policy']);
						$tmp_limit						= end($tmp_policy);
						$goods['runout_policy']			= 'ableStock';
						$goods['able_stock_limit']		= $tmp_limit;
					}
				}
			break;
			case 'cancel_type':
				if	($goods['cancel_type'] == '예')			$goods['cancel_type']		= '1';
				else										$goods['cancel_type']		= '0';
			break;
			case 'reserve_policy':
				if	($goods['reserve_policy'] == '개별')		$goods['reserve_policy']	= 'goods';
				else										$goods['reserve_policy']	= 'shop';
			break;
			case 'tax':
				if	($goods['tax'] == '비과세')				$goods['tax']				= 'exempt';
				else										$goods['tax']				= 'tax';
			break;
			case 'adult_goods':
				if	($goods['adult_goods'] == '예')			$goods['adult_goods']		= 'Y';
				else										$goods['adult_goods']		= 'N';
			break;
			case 'option_view_type':
				if	($goods['option_view_type'] == '합체형')	$goods['option_view_type']	= 'join';
				else										$goods['option_view_type']	= 'divide';
			break;
			case 'feed_status':
				if	($goods['feed_status'] == '아니요')		$goods['feed_status']		= 'N';
				else										$goods['feed_status']		= 'Y';
			break;
			case 'openmarket_keyword':
				$goods['openmarket_keyword']	= str_replace('^', ',', $goods['openmarket_keyword']);
			break;
			case 'keyword':		// 검색어 그대로 저장 2018-08-07
				$goods['keyword']	= str_replace('^', ',', $goods['keyword']);
			break;
			case 'sub_info_desc':
				if	(isset($goods['goods_sub_info']) && $goods['sub_info_desc']){
					$tmp1		= explode('^', $goods['sub_info_desc']);
					if	($tmp1)foreach($tmp1 as $k => $tmpstr){
						$tmp2						= explode('=', $tmpstr);
						$sub_info_desc[$tmp2[0]]	= $tmp2[1];
					}
					if	($sub_info_desc){
						$goods['sub_info_desc']		= json_encode($sub_info_desc);
					}
				}
			break;
			case 'multi_discount_policy':
				if	($goods['multi_discount_policy']){
					$tmp1		= explode(chr($this->m_sUploadLineCharString), $goods['multi_discount_policy']);
					$tmpCnt		= count($tmp1);
					for	($t = 0; $t < $tmpCnt; $t++){
						$tmp2							= explode('^', $tmp1[$t]);
						if		(preg_match('/%/', $tmp2[1])){
							$tmp2[1]					= str_replace('%', '', $tmp2[1]);
							if	(!$tmpUnit)	$tmpUnit	= 'PER';
						}elseif	(preg_match('/원/', $tmp2[1])){
							$tmp2[1]					= str_replace('원', '', $tmp2[1]);
							if	(!$tmpUnit)	$tmpUnit	= 'PRI';
						}
						if	(!$tmpUnit)	$tmpUnit		= 'PER';
						$tmp2[1]						= preg_replace('/[^0-9\.]*/', '', $tmp2[1]);
						if	($tmpUnit == 'PER' && ($tmp2[1] > 100 || $tmp2[1] < 0)){
							$tmp2[1]					= 0;
						}

						if	($t > 0)	$tmpArr[($t-1)]['discountUnderQty']	= $tmp2[0];
						if	(($t + 1) < $tmpCnt){
							$tmpArr[$t]['discountOverQty']	= $tmp2[0];
							$tmpArr[$t]['discountAmount']	= $tmp2[1];
						}else{
							$tmpLstQty						= $tmp2[0];
							$tmpLstAmt						= $tmp2[1];
						}
					}
					$tmpResult['policyList']				= $tmpArr;
					$tmpResult['discountMaxOverQty']		= $tmpLstQty;
					$tmpResult['discountMaxAmount']			= $tmpLstAmt;
					$tmpResult['discountUnit']				= $tmpUnit;
					$goods['multi_discount_policy']			= json_encode($tmpResult);
				}else{
					$goods['multi_discount_policy']			= '';
				}
			break;
			case 'min_purchase_ea':
				$goods['min_purchase_limit']		= 'unlimit';
				if	($goods['min_purchase_ea'] > 1){
					$goods['min_purchase_limit']	= 'limit';
				}
			break;
			case 'max_purchase_ea':
				$goods['max_purchase_limit']		= 'unlimit';
				if	($goods['max_purchase_ea'] > 0){
					$goods['max_purchase_limit']	= 'limit';
				}
			break;
			case 'string_price':
				if	($goods['string_price']){
					$tmp	= explode('^', $goods['string_price']);
					$goods['string_price_use']		= '1';
					$goods['string_price']			= $tmp[0];
					if		($tmp[1] == '로그인')	$goods['string_price_link']		= 'login';
					elseif	($tmp[1] == '1:1문의')	$goods['string_price_link']		= '1:1';
					elseif	($tmp[1] == '직접입력')	$goods['string_price_link']		= 'direct';
					else							$goods['string_price_link']		= '';
					$goods['string_price_link_url']	= $tmp[2];
				}else{
					$goods['string_price_use']		= '0';
					$goods['string_price']			= '';
					$goods['string_price_link']		= '';
					$goods['string_price_link_url']	= '';
				}
			break;
			case 'string_button':
				if	($goods['string_button']){
					$tmp	= explode('^', $goods['string_button']);
					$goods['string_button_use']			= '1';
					$goods['string_button']				= $tmp[0];
					if		($tmp[1] == '로그인')	$goods['string_button_link']		= 'login';
					elseif	($tmp[1] == '1:1문의')	$goods['string_button_link']		= '1:1';
					elseif	($tmp[1] == '직접입력')	$goods['string_button_link']		= 'direct';
					else							$goods['string_button_link']		= '';
					$goods['string_button_link_url']	= $tmp[2];
				}else{
					$goods['string_button_use']			= '0';
					$goods['string_button']				= '';
					$goods['string_button_link']		= '';
					$goods['string_button_link_url']	= '';
				}
			break;
			case 'member_string_price':
				if	($goods['member_string_price']){
					$tmp	= explode('^', $goods['member_string_price']);
					$goods['member_string_price_use']		= '1';
					$goods['member_string_price']			= $tmp[0];
					if		($tmp[1] == '로그인')	$goods['member_string_price_link']		= 'login';
					elseif	($tmp[1] == '1:1문의')	$goods['member_string_price_link']		= '1:1';
					elseif	($tmp[1] == '직접입력')	$goods['member_string_price_link']		= 'direct';
					else							$goods['member_string_price_link']		= '';
					$goods['member_string_price_link_url']	= $tmp[2];
				}else{
					$goods['member_string_price_use']		= '0';
					$goods['member_string_price']			= '';
					$goods['member_string_price_link']		= '';
					$goods['member_string_price_link_url']	= '';
				}
			break;
			case 'member_string_button':
				if	($goods['member_string_button']){
					$tmp	= explode('^', $goods['member_string_button']);
					$goods['member_string_button_use']		= '1';
					$goods['member_string_button']			= $tmp[0];
					if		($tmp[1] == '로그인')	$goods['member_string_button_link']		= 'login';
					elseif	($tmp[1] == '1:1문의')	$goods['member_string_button_link']		= '1:1';
					elseif	($tmp[1] == '직접입력')	$goods['member_string_button_link']		= 'direct';
					else							$goods['member_string_button_link']		= '';
					$goods['member_string_button_link_url']	= $tmp[2];
				}else{
					$goods['member_string_button_use']		= '0';
					$goods['member_string_button']			= '';
					$goods['member_string_button_link']		= '';
					$goods['member_string_button_link_url']	= '';
				}
			break;
			case 'allmember_string_price':
				if	($goods['allmember_string_price']){
					$tmp	= explode('^', $goods['allmember_string_price']);
					$goods['allmember_string_price_use']		= '1';
					$goods['allmember_string_price']			= $tmp[0];
					if		($tmp[1] == '로그인')	$goods['allmember_string_price_link']		= 'login';
					elseif	($tmp[1] == '1:1문의')	$goods['allmember_string_price_link']		= '1:1';
					elseif	($tmp[1] == '직접입력')	$goods['allmember_string_price_link']		= 'direct';
					else							$goods['allmember_string_price_link']		= '';
					$goods['allmember_string_price_link_url']	= $tmp[2];
				}else{
					$goods['allmember_string_price_use']		= '0';
					$goods['allmember_string_price']			= '';
					$goods['allmember_string_price_link']		= '';
					$goods['allmember_string_price_link_url']	= '';
				}
			break;
			case 'allmember_string_button':
				if	($goods['allmember_string_button']){
					$tmp	= explode('^', $goods['allmember_string_button']);
					$goods['allmember_string_button_use']		= '1';
					$goods['allmember_string_button']			= $tmp[0];
					if		($tmp[1] == '로그인')	$goods['allmember_string_button_link']		= 'login';
					elseif	($tmp[1] == '1:1문의')	$goods['allmember_string_button_link']		= '1:1';
					elseif	($tmp[1] == '직접입력')	$goods['allmember_string_button_link']		= 'direct';
					else							$goods['allmember_string_button_link']		= '';
					$goods['allmember_string_button_link_url']	= $tmp[2];
				}else{
					$goods['allmember_string_button_use']		= '0';
					$goods['allmember_string_button']			= '';
					$goods['allmember_string_button_link']		= '';
					$goods['allmember_string_button_link_url']	= '';
				}
			break;
			case 'relation_image_size':
				if	($goods['relation_image_size']){
					$tmp1							= explode('=', $goods['relation_image_size']);
					$tmp2							= explode('x', $tmp1[0]);
					$pat1							= array_values($this->m_aImageName);
					$pat2							= array_keys($this->m_aImageName);
					$goods['relation_image_size']	= str_replace($pat1, $pat2, $tmp1[1]);
					$goods['relation_count_w']		= $tmp2[0];
					$goods['relation_count_h']		= $tmp2[1];
				}
			break;
			case 'feed_evt_text':
				if	($goods['feed_evt_text']){
					$tmp1		= explode('=', $goods['feed_evt_text']);
					$tmp2		= explode('-', $tmp1[1]);
					$goods['feed_evt_text']		= $tmp1[0];
					$goods['feed_evt_sdate']	= $tmp2[0] ? date('Y-m-d', strtotime($tmp2[0])) : '0000-00-00';
					$goods['feed_evt_edate']	= $tmp2[1] ? date('Y-m-d', strtotime($tmp2[1])) : '0000-00-00';
				}
 			break;
			case 'shipping_price':
				if		((trim($goods['shipping_price']) == '0' || trim($goods['shipping_price']) > 0) && isset($goods['shipping_price'])){
					$goods['shipping_policy']		= 'goods';
					if($goods['limit_shipping_ea'] > 0 || !empty($goods['limit_shipping_price'])){
						$goods['limit_shipping_price']		= $goods['shipping_price'];
						$goods['unlimit_shipping_price']	= '0';
					}else{
						$goods['limit_shipping_price']		= '0';
						$goods['unlimit_shipping_price']	= $goods['shipping_price'];
					}
				}else{
					$goods['shipping_policy']			= 'shop';
					$goods['limit_shipping_price']		= '0';
					$goods['unlimit_shipping_price']	= '0';
				}
				unset($goods['shipping_price']);
			break;
			case 'limit_shipping_ea':
				if		($goods['shipping_policy'] == 'goods' || (trim($goods['shipping_price']) == '0' || trim($goods['shipping_price']) > 0)){
					if($goods['limit_shipping_ea'] > 0 || !empty($goods['limit_shipping_price'])){
						$goods['goods_shipping_policy']		= 'limit';
						$goods['limit_shipping_ea']			= $goods['limit_shipping_ea'];
						$goods['limit_shipping_subprice']	= $goods['limit_shipping_subprice'];
					}else{
						$goods['goods_shipping_policy']		= 'unlimit';
						$goods['limit_shipping_ea']			= '0';
						$goods['limit_shipping_subprice']	= '0';
					}
				}
			break;
			case 'limit_shipping_subprice':
				if		($goods['shipping_policy'] == 'goods' || (trim($goods['shipping_price']) == '0' || trim($goods['shipping_price']) > 0)){
					if($goods['limit_shipping_ea'] > 0 || !empty($goods['limit_shipping_price'])){
						$goods['goods_shipping_policy']		= 'limit';
						$goods['limit_shipping_ea']			= $goods['limit_shipping_ea'];
						$goods['limit_shipping_subprice']	= $goods['limit_shipping_subprice'];
					}else{
						$goods['goods_shipping_policy']		= 'unlimit';
						$goods['limit_shipping_ea']			= '0';
						$goods['limit_shipping_subprice']	= '0';
					}
				}
			break;
			case 'option_international_shipping_status':
				if	($goods['option_international_shipping_status'] == '예'){
					$goods['option_international_shipping_status']		= 'y';
				}else{
					$goods['option_international_shipping_status']		= 'n';
				}
			break;
			case 'inputoption_layout_position':
				if	($goods['inputoption_layout_position'] == '선택된옵션영역'){
					$goods['inputoption_layout_position']		= 'down';
				}else{
					$goods['inputoption_layout_position']		= 'up';
				}
			break;
			case 'suboption_layout_group':
				if	($goods['suboption_layout_group'] == '첫번째필수옵션'){
					$goods['suboption_layout_group']		= 'first';
				}else{
					$goods['suboption_layout_group']		= 'group';
				}
			break;
			case 'purchase_goods_name':
				if	($this->m_bIsScm){
					$goods['suboption_layout_group']		= '';
				}
			break;
			case 'feed_ship_type':
				if		($goods['feed_ship_type'] == '설정된배송그룹'){
					$goods['feed_ship_type']			= 'G';
				}elseif	($goods['feed_ship_type'] == '통합설정'){
					$goods['feed_ship_type']			= 'S';
				}else{
					$tmp1								= explode('^', $goods['feed_ship_type']);
					if		(preg_match('/^무료\^/', $goods['feed_ship_type'])){
						$goods['feed_pay_type']			= 'free';
						$goods['feed_add_txt']			= $tmp1[1];
					}elseif	(preg_match('/^착불\^/', $goods['feed_ship_type'])){
						$goods['feed_pay_type']			= 'postpay';
						$goods['feed_add_txt']			= $tmp1[1];
					}else{
						$goods['feed_pay_type']			= 'fixed';
						$goods['feed_std_fixed']			= preg_replace('/[^0-9\.]/', '', $tmp1[0]);
						$goods['feed_add_txt']			= $tmp1[1];
					}
					$goods['feed_ship_type']			= 'E';
				}
			break;
			case 'present_use' :		// 선물하기
				if	($goods['present_use'] === 'Y'){
					$goods['present_use']	= '1';
				} else {
					$goods['present_use']		= '0';
				}
			break;
		}
	}

	// 옵션 타이틀 특수옵션과 타이틀 분리
	public function upload_special_option_name($title){
		// 특수옵션이 있는 옵션명인지 여부 체크
		$reTitle	= $title;
		if	(preg_match('/^\[[^\]]*\]/', $title)){
			$typeArr	= array('/^\[색상\]/', '/^\[지역\]/', '/^\[날짜\]/',
								'/^\[수동기간\]/', '/^\[자동기간\]/');
			preg_match('/^\[[^\]]+\]/', $title, $matches, PREG_OFFSET_CAPTURE);
			$spc		= $matches[0][0];
			$reTitle	= preg_replace($typeArr, '', $title);
			switch($spc){
				case '[색상]':		$newtype	= 'color';		break;
				case '[지역]':		$newtype	= 'address';	break;
				case '[날짜]':		$newtype	= 'date';		break;
				case '[수동기간]':	$newtype	= 'dayinput';	break;
				case '[자동기간]':	$newtype	= 'dayauto';	break;
			}
		}

		return array('newtype' => $newtype, 'title' => $reTitle);
	}

	// 옵션값에서 특수옵션값과 옵션값 분리
	public function get_divide_special_option($type, $str){

		switch($type){
			case 'color':
				$tmp							= explode('=', $str);
				$return['option']				= $tmp[0];
				$return['color']				= $tmp[1];
			break;
			case 'address':
				$tmp1							= explode('=', $str);
				$tmp2							= explode('|', $tmp1[1]);
				$return['option']				= $tmp1[0];
				$return['address_type']			= ($tmp2[0] == '도로명') ? 'street' : 'zibun';
				$return['zipcode']				= $tmp2[1];
				$return['address']				= $tmp2[2];
				if	($return['address_type'] == 'street')	$return['address_street']	= $tmp2[3];
				else										$return['addressdetail']	= $tmp2[3];
				$return['address_commission']	= (int) preg_replace('/[^0-9]*/', '', $tmp1[2]);
			break;
			case 'date':
				$tmp							= explode('=', $str);
				$return['option']				= $tmp[0];
				$return['codedate']				= $tmp[1];
			break;
			case 'dayinput':
				$tmp1							= explode('=', $str);
				$tmp2							= explode('~', $tmp1[1]);
				$return['option']				= $tmp1[0];
				$return['sdayinput']			= $tmp2[0];
				$return['fdayinput']			= $tmp2[1];
			break;
			case 'dayauto':
				$tmp1							= explode('=', $str);
				$tmp2							= explode('+', $tmp1[1]);
				$return['option']				= $tmp1[0];
				$return['sdayauto']				= $tmp2[0];
				$return['fdayauto']				= $tmp2[1];
				switch($tmp1[2]){
					case 'A':
						$return['dayauto_type']	= 'month';
						$return['dayauto_day']	= 'day';
					break;
					case 'B':
						$return['dayauto_type']	= 'month';
						$return['dayauto_day']	= 'end';
					break;
					case 'C':
						$return['dayauto_type']	= 'day';
						$return['dayauto_day']	= 'day';
					break;
					case 'D':
						$return['dayauto_type']	= 'day';
						$return['dayauto_day']	= 'end';
					break;
					case 'E':
						$return['dayauto_type']	= 'next';
						$return['dayauto_day']	= 'day';
					break;
					case 'F':
						$return['dayauto_type']	= 'next';
						$return['dayauto_day']	= 'end';
					break;
				}
			break;
		}

		return $return;
	}

	// 상품 정보 insert 시 not null 컬럼 체크 후 기본값 추가
	public function chk_require_goods_insert_param(&$params){

		if	(isset($params['provider_seq']) && !$params['provider_seq'])	$params['provider_seq']	= 1;
		if	($this->m_sServiceType == 'A' && !isset($params['provider_status']))
			$params['provider_status']	= '0';
		if	($this->m_sServiceType == 'N')				$params['provider_status']	= '1';
		if	(!$params['goods_name'])					$params['goods_name']					= '상품명입니다.';
		if	(!$params['goods_status'])					$params['goods_status']					= 'unsold';
		if	(!$params['view_layout'])					$params['view_layout']					= 'basic';
		if	(!$params['goods_view'])					$params['goods_view']					= 'notLook';
		if	(!$params['favorite_chk'])					$params['favorite_chk']					= 'none';
		if	(!$params['info_seq'])						$params['info_seq']						= '0';
		if	(!$params['string_price_use'])				$params['string_price_use']				= '0';
		if	(!$params['member_string_price_use'])		$params['member_string_price_use']		= '0';
		if	(!$params['allmember_string_price_use'])	$params['allmember_string_price_use']	= '0';
		if	(!$params['tax'])							$params['tax']							= 'tax';
		if	(!$params['multi_discount_use'])			$params['multi_discount_use']			= '0';
		if	(!$params['multi_discount'])				$params['multi_discount']				= '0';
		if	(!$params['min_purchase_limit'])			$params['min_purchase_limit']			= 'unlimit';
		if	(!$params['max_purchase_limit'])			$params['max_purchase_limit']			= 'unlimit';
		if	(!$params['reserve_policy'])				$params['reserve_policy']				= 'shop';
		if	(!$params['option_use'])					$params['option_use']					= '0';
		if	(!$params['option_view_type'])				$params['option_view_type']				= 'divide';
		if	(!$params['option_suboption_use'])			$params['option_suboption_use']			= '0';
		if	(!$params['member_input_use'])				$params['member_input_use']				= '0';
		if	(!$params['shipping_policy'])				$params['shipping_policy']				= 'shop';
		if	(!$params['goods_shipping_policy'])			$params['goods_shipping_policy']		= 'unlimit';
		if	(!$params['shipping_weight_policy'])		$params['shipping_weight_policy']		= 'shop';
		if	(!$params['relation_type'])					$params['relation_type']				= 'AUTO';
		if	(!$params['relation_count_w'])				$params['relation_count_w']				= '4';
		if	(!$params['relation_count_h'])				$params['relation_count_h']				= '1';
		if	(!$params['purchase_ea'])					$params['purchase_ea']					= '0';
		if	(!$params['page_view'])						$params['page_view']					= '0';
		if	(!$params['review_count'])					$params['review_count']					= '0';
		if	(!$params['restock_notify_use'])			$params['restock_notify_use']			= '0';
		if	(!$params['regist_date'])					$params['regist_date']					= date('Y-m-d H:i:s');
		if	(!$params['update_date'])					$params['update_date']					= date('Y-m-d H:i:s');
		if	(!$params['video_type'])					$params['video_type']					= '';
		if	(!$params['video_size'])					$params['video_size']					= '';
		if	(!$params['video_size_mobile'])				$params['video_size_mobile']			= '';
		if	(!$params['videotmpcode'])					$params['videotmpcode']					= '';
		if	(!$params['possible_pay_type'])				$params['possible_pay_type']			= '';
		if	(!$params['goods_kind']){
			if	($this->m_sGoodsKind == 'C')			$params['goods_kind']					= 'coupon';
			else										$params['goods_kind']					= 'goods';
		}
		if	(!$params['tot_stock'])						$params['tot_stock']					= 0;
		if	(!$params['sale_seq'])						$params['sale_seq']						= '1';

		if ($this->m_sGoodsKind == 'C'){
			if	(!$params['socialcp_cancel_type'])		$params['socialcp_cancel_type']			= 'option';
			if	(!$params['socialcp_cancel_use_refund'])$params['socialcp_cancel_use_refund']	= '0';
			if	(!$params['socialcp_use_return'])		$params['socialcp_use_return']			= '0';
		}

		if	(!$params['relation_criteria'])				$params['relation_criteria']			= 'admin∀type=select_auto,provider=all,month=3,age=all,sex=all,agent=all,act=view,min_ea=1';

		if	($this->m_sAdminType == 'S'){
			if	(!$params['relation_seller_criteria'])		$params['relation_seller_criteria']		= 'admin∀type=select_auto,provider=all,month=3,age=all,sex=all,agent=all,act=view,min_ea=1';
		}

		if	(!$params['bigdata_criteria'])				$params['bigdata_criteria']				= '';
		//#22996 2019-01-03 ycg 엑셀 일괄 등록 개선(추가/오픈마켓 검색어 대체 문구)
		if(!$params['goods_name_linkage'])				$params['goods_name_linkage']			= '오픈마켓 상품명입니다.';

		if	(!$params['feed_pay_type'])					$params['feed_pay_type']				= '';
		if	(!$params['feed_std_fixed'])				$params['feed_std_fixed']				= '';
		if	(!$params['feed_add_txt'])					$params['feed_add_txt']					= '';
		if	(!$params['feed_ship_type'])				$params['feed_ship_type']				= 'G';
	}

	// 상품 정보 insert 시 not null 컬럼 체크 후 기본값 추가
	public function chk_require_goods_update_param(&$params){

		if	(isset($params['provider_seq']) && !$params['provider_seq'])								$params['provider_seq']					= 1;
		if	(isset($params['goods_name']) && !$params['goods_name'])									$params['goods_name']					= '상품명입니다.';
		if	(isset($params['goods_status']) && !$params['goods_status'])								$params['goods_status']					= 'unsold';
		if	(isset($params['view_layout']) && !$params['view_layout'])									$params['view_layout']					= 'basic';
		if	(isset($params['goods_view']) && !$params['goods_view'])									$params['goods_view']					= 'notLook';
		if	(isset($params['info_seq']) && !$params['info_seq'])										$params['info_seq']						= '0';
		if	(isset($params['string_price_use']) && !$params['string_price_use'])						$params['string_price_use']				= '0';
		if	(isset($params['member_string_price_use']) && !$params['member_string_price_use'])			$params['member_string_price_use']		= '0';
		if	(isset($params['allmember_string_price_use']) && !$params['allmember_string_price_use'])	$params['allmember_string_price_use']	= '0';
		if	(isset($params['tax']) && !$params['tax'])													$params['tax']							= 'tax';
		$params['multi_discount_use']			= '0';	// 미사용 not null
		$params['multi_discount']				= '0';	// 미사용 not null
		if	(isset($params['min_purchase_limit']) && !$params['min_purchase_limit'])					$params['min_purchase_limit']			= 'unlimit';
		if	(isset($params['max_purchase_limit']) && !$params['max_purchase_limit'])					$params['max_purchase_limit']			= 'unlimit';
		if	(isset($params['reserve_policy']) && !$params['reserve_policy'])							$params['reserve_policy']				= 'shop';
		if	(isset($params['option_use']) && !$params['option_use'])									$params['option_use']					= '0';
		if	(isset($params['option_view_type']) && !$params['option_view_type'])						$params['option_view_type']				= 'divide';
		if	(isset($params['option_suboption_use']) && !$params['option_suboption_use'])				$params['option_suboption_use']			= '0';
		if	(isset($params['member_input_use']) && !$params['member_input_use'])						$params['member_input_use']				= '0';
		if	(isset($params['shipping_policy']) && !$params['shipping_policy'])							$params['shipping_policy']				= 'shop';
		if	(isset($params['goods_shipping_policy']) && !$params['goods_shipping_policy'])				$params['goods_shipping_policy']		= 'unlimit';
		if	(isset($params['shipping_weight_policy']) && !$params['shipping_weight_policy'])			$params['shipping_weight_policy']		= 'shop';
		if	(isset($params['relation_type']) && !$params['relation_type'])								$params['relation_type']				= 'AUTO';
		if	(isset($params['relation_count_w']) && !$params['relation_count_w'])						$params['relation_count_w']				= '4';
		if	(isset($params['relation_count_h']) && !$params['relation_count_h'])						$params['relation_count_h']				= '1';
		if	(isset($params['purchase_ea']) && !$params['purchase_ea'])									$params['purchase_ea']					= '0';
		if	(isset($params['page_view']) && !$params['page_view'])										$params['page_view']					= '0';
		if	(isset($params['review_count']) && !$params['review_count'])								$params['review_count']					= '0';
		if	(isset($params['restock_notify_use']) && !$params['restock_notify_use'])					$params['restock_notify_use']			= '0';
		if	(isset($params['regist_date']) && !$params['regist_date'])									$params['regist_date']					= date('Y-m-d H:i:s');
		if	(isset($params['update_date']) && !$params['update_date'])									$params['update_date']					= date('Y-m-d H:i:s');
		if	(isset($params['video_type']) && !$params['video_type'])									$params['video_type']					= '';
		if	(isset($params['video_size']) && !$params['video_size'])									$params['video_size']					= '';
		if	(isset($params['video_size_mobile']) && !$params['video_size_mobile'])						$params['video_size_mobile']			= '';
		if	(isset($params['videotmpcode']) && !$params['videotmpcode'])								$params['videotmpcode']					= '';
		if	(isset($params['possible_pay_type']) && !$params['possible_pay_type'])						$params['possible_pay_type']			= '';
		if	(isset($params['option_international_shipping_status']) && !$params['option_international_shipping_status'])	$params['option_international_shipping_status']			= 'n';
		//#22996 2019-01-03 ycg 엑셀 일괄 등록 개선(수정/오픈마켓 검색어 대체 문구)
		if(!$params['goods_name_linkage'])				$params['goods_name_linkage']			= '오픈마켓 상품명입니다.';
		if	(!$params['feed_pay_type'])					$params['feed_pay_type']				= '';
		if	(!$params['feed_std_fixed'])				$params['feed_std_fixed']				= '';
		if	(!$params['feed_add_txt'])					$params['feed_add_txt']					= '';
	}

	// 임시 업로드 엑셀파일 삭제
	public function delete_upload_excel_file(){

		$today		= strtotime(date('Y-m-d') . ' 00:00:00');
		$dir		= opendir($this->m_sExcelUploadFilePath);
		while($file = readdir($dir)){
			if	(preg_match('/^upload\_goods\_excel\_/', $file)){
				$datetime	= strtotime(substr(str_replace('upload_goods_excel_', '', $file), 0, 14));

				if	($datetime < $today){
					unlink($this->m_sExcelUploadFilePath . '/' . $file);
				}
			}
		}
	}

	// 업로드 로그 삭제
	public function delete_upload_log(){
		// 삭제 기준
		$logDelType		= 'date';	// date : 날짜기준, count : 건수 기준
		$logDelData		= 5;		// date : 최대 몇일, count : 최대건수

		// 삭제 대상 데이터 추출
		if	($logDelType == 'date'){
			$date	= date('Y-m-d', strtotime('-'.$logDelData.' day')) . ' 00:00:00';
			$sql	= "select * from fm_excel_upload_log where upload_date < '".$date."' ";
			$query	= $this->db->query($sql);
			$delLog	= $query->result_array();
		}elseif	($logDelType == 'count'){
			$sql	= "select * from (
						select @rownum:=@rownum+1 as rownum, log.*
						from (select * from fm_excel_upload_log order by upload_date desc) as log,
							(select @rownum:=0) as r ) as tmp
						where rownum > ".$logDelData." ";
			$query	= $this->db->query($sql);
			$delLog	= $query->result_array();
		}

		// 파일 및 데이터 삭제
		if	($delLog) foreach($delLog as $d => $log){
			$sFile	= $this->m_sLogFilePath . '/' . $log['result_success'];
			$fFile	= $this->m_sLogFilePath . '/' . $log['result_failed'];
			if	(file_exists($sFile))	unlink($sFile);
			if	(file_exists($fFile))	unlink($fFile);

			$sql		= "delete from fm_excel_upload_log where upload_seq = ? ";
			$this->db->query($sql, array($log['upload_seq']));
		}
	}

	// 업로드 시 성공 실패 로그 저장
	public function save_upload_log($type = 'failed', $msg = ''){
		if	(!$this->m_oSuccessLogFile){
			$log_file_name				= 'log_goods_excel_' . date('YmdHis') . rand(0,9999) . '.txt';
			$this->m_oSuccessLogFile	= fopen($this->m_sLogFilePath . '/success_' . $log_file_name, 'a+');
			$this->m_oFailedLogFile		= fopen($this->m_sLogFilePath . '/failed_' . $log_file_name, 'a+');

			$logParam['upload_type']			= 'goods';
			$logParam['provider_seq']			= '1';
			$logParam['seller_upload_type']		= '';
			if	($this->m_sGoodsKind == 'C')	$logParam['upload_type']	= 'coupon';
			if	($this->m_sAdminType == 'S'){
				if	($this->m_sProviderChoice == 'N')	$logParam['seller_upload_type']	= 'Type1';
				else									$logParam['seller_upload_type']	= 'Type2';
				$logParam['provider_seq']		= $this->m_nProviderSeq;
			}
			$logParam['upload_date']			= date('Y-m-d H:i:s');
			$logParam['uploader_ip']			= $_SERVER['REMOTE_ADDR'];
			$logParam['upload_filename']		= $this->m_sUploadFileName.".".$this->m_sUploadAllowedType;
			$logParam['uploader']				= $this->m_sUploader;
			$logParam['result_success']			= 'success_' . $log_file_name;
			$logParam['result_failed']			= 'failed_' . $log_file_name;
			$this->db->insert('fm_excel_upload_log', $logParam);
		}

		if	($msg){
			if	($type == 'success'){
				fwrite($this->m_oSuccessLogFile, $msg);
			}else{
				fwrite($this->m_oFailedLogFile, $msg);
			}
		}
	}

	// 로그파일 닫기
	public function close_upload_log(){
		fclose($this->m_oSuccessLogFile);
		fclose($this->m_oFailedLogFile);
	}

	// fm_goods_supply 삭제
	public function delete_goods_supply($goods_seq, $type = 'option'){
		if	($type == 'option')		$addWhere	= " and option_seq > 0 ";
		if	($type == 'suboption')	$addWhere	= " and suboption_seq > 0 ";

		$sql	= "delete from fm_goods_supply where goods_seq = ? " . $addWhere;
		$query	= $this->db->query($sql, array($goods_seq));
	}

	########## ↑↑↑↑↑ 엑셀 업로드 ↑↑↑↑↑ ##########


	########## ↓↓↓↓↓ 기타 함수 ↓↓↓↓↓ ##########

	// 폴더가 없을 경우 생성
	public function crt_folder($folderPath){
		if(!is_dir($folderPath)){
			@mkdir($folderPath);
			@chmod($folderPath,0777);
		}
	}

	// 금액 문자열에서 금액과 단위를 분리
	public function divide_price_and_unit($priceStr){
		$price	= preg_replace('/[^0-9+\.]*/', '', $priceStr);
		$unit	= str_replace($price, '', $priceStr);

		// 단일 옵션인 경우 엑셀에서 3% 입력시 => 0.03 으로 자동으로 백분율로 변환하여 인식됨
		// unit 없고, price가 1보다 작으면 price = price*100 , unit=percent 처리 2020-05-07  
		if ( $unit == '' && $price < 1) {
			$price = $price * 100;
			$unit = '%';
		}

		if	($unit == '%')	$unit	= 'percent';
		else				$unit	= $this->config_system['basic_currency'];

		return array('price' => $price, 'unit' => $unit);
	}

	// 전체 컬럼 목록 배열 return
	public function get_cell_list(){
		return $this->m_aCellList;
	}

	// 실물상품 / 쿠폰상품 종류 세팅 ( default : 실물 )
	public function set_init($params){

		if	(strtoupper($params['process']) == 'UPDATE'){
			$this->m_sProcType	= 'U';	// UPDATE ( 업로드 )

			// 기본 정책 정보
			if	(!$this->reserves)		$this->reserves		= config_load('reserve');
			if	(!$this->config_order)	$this->config_order	= config_load('order');
		}else{
			$this->m_sProcType	= 'D';	// DOWNLOAD ( 다운로드 )
		}

		if	(strtoupper($params['goods_kind']) == 'COUPON'){
			$this->m_sGoodsKind	= 'C';	// COUPON ( 티켓 )
		}else{
			$this->m_sGoodsKind	= 'G';	// GOODS ( 실물 )
		}

		// 입점사 관리자
		if	($params['provider_seq'] > 1){
			$this->load->model('providermodel');
			$provider_info	= $this->providermodel->provider_charge_list($params['provider_seq']);

			$this->m_sAdminType			= 'S';	// SELLERADMIN ( 입점사관리자 )
			$this->m_nProviderSeq		= $params['provider_seq'];
			$this->m_nProviderCommission= $provider_info[0]['charge'];
			$this->m_sUploader			= $params['provider_id'];
			$this->m_sProviderChoice	= ($params['provider_choice']) ? $params['provider_choice'] : 'N';
		}else{
			$this->m_sUploader			= $params['manager_id'];
			$this->m_sProviderChoice	= '';
		}
		$this->set_provider_ignore();

		// 엑셀 업로드 시 호출할 사용자 정의 함수
		if	($this->m_sProcType == 'U' && $params['user_func'])
			$this->m_sAfterTreatmentFunc	= $params['user_func'];		// 상품 하나당 호출
		if	($this->m_sProcType == 'U' && $params['end_user_func'])
			$this->m_sAfterTreatmentFuncEnd	= $params['end_user_func'];	// 마지막에 한번 호출

		// 물류관리 관련 추가
		if	(!$this->scm_cfg){
			if	($this->scmmodel->scm_cfg)	$this->scm_cfg		= $this->scmmodel->scm_cfg;
			else							$this->scm_cfg		= config_load('scm');
		}

		$this->set_cell_list();
		$this->set_multiRow_cell();
	}

	// 현재 쇼핑몰의 서비스 정보 세팅
	public function set_service_type(){
		if	(!$this->config_system)		$this->config_system	= config_load('system');

		// 서비스 구분
		if	( serviceLimit('H_AD') ){
			$this->m_sServiceType	= 'A';	// ADVANCED ( 입점몰 )
		}else{
			$this->m_sServiceType	= 'N';	// NORMAL ( 일반몰 )
		}

		// 관리자 페이지 구분
		if	(preg_match('/^\/selleradmin/', $_SERVER['REQUEST_URI'])){
			$this->m_sAdminType		= 'S';	// SELLERADMIN ( 입점사관리자 )
		}else{
			$this->m_sAdminType		= 'A';	// ADMIN ( 관리자 )
		}
		$this->set_provider_ignore();
	}

	// 영문명 cell 수를 수치로 계산
	public function calculate_alphar_to_count($alphar){

		$chr	= strtoupper($alphar);
		$len	= strlen($chr);
		$square	= 0;
		for	( $c = $len; $c > 0; $c--){

			// 자릿수
			$add	= 1;
			if	($square > 0)for ( $s = 0; $s < $square; $s++){
				$add	= $add * 26;
			}

			$str	= substr($chr, ($s-1), 1);
			$num	= ( ord($str) - 64 ) * $add;
			$return	= $return + $num;

			$square++;
		}

		return $return;
	}

	// excel cell 영문값 추출 함수
	public function get_excel_cell_alphar($cellCount){

		$char		= 26;
		$cellArr	= array('A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M',
							'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z');
		for( $i = 0; $i < $cellCount; $i++) {
			if	( $i < $char)	$return[]	= $cellArr[$i];
			else {
				$idx1		= (int)( $i - $char ) / $char;
				$idx2		= ($i-$char)%$char;
				$return[]	= $cellArr[$idx1].$cellArr[$idx2];
			}
		}

		return $return;
	}

	// 코드 길이 값 반환
	public function get_code_length(){
		return $this->m_nCodeLen;
	}

	// 엑셀 업로드 로그
	public function get_excel_upload_log($sc){

		// 업로드 구분 검색
		if		($this->m_sGoodsKind == 'C')	$addWhere	.= " and upload_type = 'coupon' ";
		else									$addWhere	.= " and upload_type = 'goods' ";

		// 입점사 검색
		if		($sc['provider_seq'] > 0){
			$addWhere	.= " and provider_seq = '".$sc['provider_seq']."' ";
		}elseif	($this->m_sAdminType == 'S' && $this->m_nProviderSeq > 1){
			$addWhere	.= " and provider_seq = '".$this->m_nProviderSeq."' ";
		}else{
			$addWhere	.= " and provider_seq = '1' ";
		}

		// 추출 수량
		if		(isset($sc['elimit'])){
			if	(!$sc['slimit'])	$sc['slimit']	= 0;
			$addLimit	= " LIMIT " . $sc['slimit'] . ", " . $sc['elimit'] . " ";
		}

		// 정렬
		$orderby	= " order by upload_date desc ";
		if	($sc['orderby'])	$orderby	= " order by " . $sc['orderby'] . " ";

		$sql		= "select * from fm_excel_upload_log where upload_seq > 0 "
					. $addWhere . $orderby . $addLimit;
		$query		= $this->db->query($sql);
		$result		= $query->result_array();

		return $result;
	}

	// log파일 다운로드
	public function download_log_file($filename){
		if	(!$filename){
			return array('status' => false, 'err_msg' => '선택된 log파일이 없습니다.');
		}

		$filepath	= $this->m_sLogFilePath . '/' . $filename;
		if	(!file_exists($filepath)){
			return array('status' => false, 'err_msg' => '해당 log파일을 찾을 수 없습니다.');
		}

		$fobj	= fopen($filepath, 'r');
		header('Content-Type: text/html');
		header("Content-Disposition: attachment;filename=".$filename);
		header('Cache-Control: max-age=0');
		while (!feof($fobj)){
			echo fgets($fobj, 4096);
		}
		fclose($fobj);

		return array('status' => true);
	}

	########## ↑↑↑↑↑ 기타 관련 ↑↑↑↑↑ ##########

	########## ↓↓↓↓↓ 기본 설정 배열 함수 ↓↓↓↓↓ ##########


	// 전체 컬럼 목록 배열 정의
	// 컬럼 코드 조합규칙
	//	몰구분(B:모두,A:입점몰,N:일반몰),
	//	상품구분(B:모두,G:실물,C:쿠폰),
	//	업로드필수구분(R:필수,N:일반),
	//	NULL값0처리여부(Y:0처리함,N:처리안함),
	//	예외처리종류(0:없음,O:필수옵션,S:추가옵션,I:입력옵션,A:추가정보,N:아이콘,M:이미지,R:관련상품),
	//	그룹번호 1자리,
	//	순차번호 3자리 ( 그룹번호 + 순차번호 = UNIQUE )

	public function set_cell_list(){

		// cell code 길이
		$this->m_nCodeLen				= 9;

		// 양식 배열 정의 ( 1그룹 )
		$cellList['ABRY00001']	= array('입점사 고유값', 'fm_goods',	'provider_seq', 15);
		$cellList['BBRY00002']	= array('상품번호', 'fm_goods',	'goods_seq', 15);
		$cellList['ABNN00003']	= array('승인여부', 'fm_goods',	'provider_status', 15);
		$cellList['BBNNC0004']	= array('카테고리', 'fm_category_link',	'category', 15);
		$cellList['BBNNB0005']	= array('브랜드', 'fm_brand_link',	'brand', 15);
		$cellList['BBNNL0006']	= array('지역', 'fm_location_link',	'location', 15);
		$cellList['BBRN00007']	= array('상태', 'fm_goods',	'goods_status', 15);
		$cellList['BBNN00030']	= array('재고에따른판매여부', 'fm_goods',	'runout_policy', 15);
		$cellList['BBNN00008']	= array('노출', 'fm_goods',	'goods_view', 15);
		$cellList['BCNN00009']	= array('티켓그룹', 'fm_goods',	'social_goods_group', 15);
		$cellList['BBNN00010']	= array('상품기본코드', 'fm_goods',	'goods_code', 15);
		$cellList['BBRN00011']	= array('상품명', 'fm_goods',	'goods_name', 50);
		$cellList['BBRN00025']	= array('오픈마켓 상품명', 'fm_goods',	'goods_name_linkage', 50);	//#22996 2018-10-10 ycg 엑셀 일괄 등록 개선(오픈마켓 상품명)
		$cellList['BBNN00014']	= array('간략설명', 'fm_goods',	'summary', 50);
		$cellList['BBNN00015']	= array('청약철회불가', 'fm_goods',	'cancel_type', 15);
		$cellList['BBNN00016']	= array('과세비과세', 'fm_goods',	'tax', 15);
		$cellList['BBNN00017']	= array('성인상품', 'fm_goods',	'adult_goods', 15);
		$cellList['BBNN00018']	= array('검색어', 'fm_goods',	'keyword', 50);						// 검색어 그대로 저장 2018-08-07
		$cellList['BBNN00026']	= array('오픈마켓 검색어', 'fm_goods',	'keyword_linkage', 50);		//#22996 2018-10-10 ycg 엑셀 일괄 등록 개선(오픈마켓 검색어)
		$cellList['BBNNA0019']	= array('추가정보', 'fm_goods_addition',	'addition', 50);
		$cellList['BBNNN0020']	= array('아이콘', 'fm_goods_icon',	'icon', 15);
		$cellList['BBNN00021']	= array('상품정보고시품목', 'fm_goods',	'goods_sub_info', 15);
		$cellList['BBNN00022']	= array('상품정보고시', 'fm_goods',	'sub_info_desc', 30);
		$cellList['BBNN00023']	= array('해외구매대행', 'fm_goods',	'option_international_shipping_status', 15);
		$cellList['BBNN00024']	= array('HSCODE', 'fm_goods',	'hscode', 15);

		// 양식 배열 정의 ( 1그룹 > 추가입력 )
		$cellList['BBNNI0025']	= array('추가입력옵션', 'fm_goods_input',	'input_name', 15);
		$cellList['BBNNI0026']	= array('추가입력옵션형식', 'fm_goods_input',	'input_form', 15);
		$cellList['BBNNI0027']	= array('추가입력옵션필수', 'fm_goods_input',	'input_require', 15);
		$cellList['BBNNI0029']	= array('노출위치', 'fm_goods',	'inputoption_layout_position', 15);



		// 양식 배열 정의 ( 2그룹 > 추가입력 )
		$cellList['BBNN01001']	= array('필수옵션타입', 'fm_goods',	'option_view_type', 15);
		$cellList['BBNNO1002']	= array('필수옵션', 'fm_goods_option',	'option_seq', 15);
		$cellList['BBNNO1034']	= array('필수옵션동작구분', 'fm_goods_option',	'action_option_kind', 15);
		$cellList['BBNNO1003']	= array('필수옵션명1', 'fm_goods_option',	'option_title1', 15);
		$cellList['BBNNO1004']	= array('필수옵션값1', 'fm_goods_option',	'option1', 15);
		$cellList['BBNNO1005']	= array('필수옵션값1코드', 'fm_goods_option',	'optioncode1', 15);
		$cellList['BBNNO1006']	= array('필수옵션명2', 'fm_goods_option',	'option_title2', 15);
		$cellList['BBNNO1007']	= array('필수옵션값2', 'fm_goods_option',	'option2', 15);
		$cellList['BBNNO1008']	= array('필수옵션값2코드', 'fm_goods_option',	'optioncode2', 15);
		$cellList['BBNNO1009']	= array('필수옵션명3', 'fm_goods_option',	'option_title3', 15);
		$cellList['BBNNO1010']	= array('필수옵션값3', 'fm_goods_option',	'option3', 15);
		$cellList['BBNNO1011']	= array('필수옵션값3코드', 'fm_goods_option',	'optioncode3', 15);
		$cellList['BBNNO1012']	= array('필수옵션명4', 'fm_goods_option',	'option_title4', 15);
		$cellList['BBNNO1013']	= array('필수옵션값4', 'fm_goods_option',	'option4', 15);
		$cellList['BBNNO1014']	= array('필수옵션값4코드', 'fm_goods_option',	'optioncode4', 15);
		$cellList['BBNNO1015']	= array('필수옵션명5', 'fm_goods_option',	'option_title5', 15);
		$cellList['BBNNO1016']	= array('필수옵션값5', 'fm_goods_option',	'option5', 15);
		$cellList['BBNNO1017']	= array('필수옵션값5코드', 'fm_goods_option',	'optioncode5', 15);
		$cellList['BBNNO1018']	= array('옵션설명', 'fm_goods_option',	'infomation', 20);
		$cellList['BBNYO1019']	= array('필수옵션재고', 'fm_goods_supply',	'stock', 15);
		$cellList['BBNYO1042']	= array('필수옵션불량재고', 'fm_goods_supply',	'badstock', 15);
		$cellList['BBNYO1043']	= array('필수옵션안전재고', 'fm_goods_supply',	'safe_stock', 15);
		$cellList['BBNYO1044']	= array('필수옵션무게', 'fm_goods_option',	'weight', 15);
		$cellList['BBNNO1037']	= array('필수옵션노출', 'fm_goods_option',	'option_view', 15);
		$cellList['ABNYO1020']	= array('수수료', 'fm_goods_option',	'commission_rate', 15);
		$cellList['ABNYO1021']	= array('공급가', 'fm_goods_option',	'commission_price', 15);
		$cellList['BBNYO1023']	= array('매입가', 'fm_goods_supply',	'supply_price', 15);
		$cellList['BBNYO1024']	= array('정가', 'fm_goods_option',	'consumer_price', 15);
		$cellList['BBNYO1025']	= array('할인가(판매가)', 'fm_goods_option',	'price', 15);
		$cellList['BBNYO1026']	= array('마일리지', 'fm_goods_option',	'reserve_rate', 15);
		$cellList['BBNNO1038']	= array('필수옵션바코드', 'fm_goods_option',	'full_barcode', 20);

		// 양식 배열 정의 ( 2그룹 )
		$cellList['BBNNW1041']	= array('기초재고', 'fm_scm_revision',	'scm_use_revision', 15);
		$cellList['BBNNW1035']	= array('기초창고=로케이션=재고=불량', 'fm_scm_revision',	'location_stock', 15);
		$cellList['BBNNW1036']	= array('기초매입가', 'fm_scm_revision',	'location_supply_price', 15);
		$cellList['BBNNW1045']	= array('기초일자', 'fm_scm_revision',	'default_date', 15);
		$cellList['BBNN01027']	= array('수량별할인(대량구매)', 'fm_goods',	'multi_discount_policy', 15);
		$cellList['BBNY01028']	= array('최소구매수량', 'fm_goods',	'min_purchase_ea', 15);
		$cellList['BBNY01029']	= array('최대구매수량', 'fm_goods',	'max_purchase_ea', 15);
		$cellList['BBNN01030']	= array('비회원가격대체', 'fm_goods',	'string_price', 15);
		$cellList['BBNN01038']	= array('비회원버튼대체', 'fm_goods',	'string_button', 15);
		$cellList['BBNN01031']	= array('일반등급가격대체', 'fm_goods',	'member_string_price', 15);
		$cellList['BBNN01039']	= array('일반등급버튼대체', 'fm_goods',	'member_string_button', 15);
		$cellList['BBNN01032']	= array('상위등급가격대체', 'fm_goods',	'allmember_string_price', 15);
		$cellList['BBNN01040']	= array('상위등급버튼대체', 'fm_goods',	'allmember_string_button', 15);
		$cellList['BBNN01033']	= array('회원등급혜택번호', 'fm_goods',	'sale_seq', 15);


		// 양식 배열 정의 ( 3그룹 > 추가옵션 )
		$cellList['BGNNS2012']	= array('추가옵션', 'fm_goods_suboption',	'suboption_seq', 15);
		$cellList['BGNNS2039']	= array('추가옵션동작구분', 'fm_goods_suboption',	'action_suboption_kind', 20);
		$cellList['BGNNS2001']	= array('추가옵션명', 'fm_goods_suboption',	'suboption_title', 15);
		$cellList['BGNNS2002']	= array('추가옵션필수', 'fm_goods_suboption',	'sub_required', 15);
		$cellList['BGNNS2003']	= array('추가옵션추가혜택', 'fm_goods_suboption',	'sub_sale', 20);
		$cellList['BGNNS2004']	= array('추가옵션값', 'fm_goods_suboption',	'suboption', 15);
		$cellList['BGNNS2005']	= array('추가옵션값코드', 'fm_goods_suboption',	'suboption_code', 15);
		$cellList['BGNYS2006']	= array('추가옵션재고', 'fm_goods_supply',	'sub_stock', 15);
		$cellList['BGNYS2040']	= array('추가옵션불량재고', 'fm_goods_supply',	'sub_badstock', 15);
		$cellList['BGNYS2041']	= array('추가옵션안전재고', 'fm_goods_supply',	'sub_safe_stock', 15);
		$cellList['BGNYS2042']	= array('추가옵션무게', 'fm_goods_suboption',	'sub_weight', 15);
		$cellList['BGNNS2043']	= array('추가옵션노출', 'fm_goods_suboption',	'sub_option_view', 15);
		$cellList['AGNYS2007']	= array('추가옵션수수료', 'fm_goods_suboption',	'sub_commission_rate', 15);
		$cellList['AGNYS2008']	= array('추가옵션공급가', 'fm_goods_suboption',	'sub_commission_price', 15);
		$cellList['BGNYS2037']	= array('추가옵션매입가', 'fm_goods_supply',	'sub_supply_price', 15);
		$cellList['BGNYS2009']	= array('추가옵션정가', 'fm_goods_suboption',	'sub_consumer_price', 15);
		$cellList['BGNYS2010']	= array('추가옵션할인가(판매가)', 'fm_goods_suboption',	'sub_price', 20);
		$cellList['BGNYS2011']	= array('추가옵션마일리지', 'fm_goods_suboption',	'sub_reserve_rate', 20);
		$cellList['BGNNS2013']	= array('묶임방법', 'fm_goods',	'suboption_layout_group', 15);
		$cellList['BGNNS2038']	= array('추가옵션바코드', 'fm_goods_suboption',	'sub_full_barcode', 20);

		// 양식 배열 정의 ( 3그룹 )
		$cellList['BBNN02034']	= array('배송그룹번호', 'fm_goods',	'shipping_group_seq', 15);
		$cellList['BBNN02019']	= array('상품설명(PC/태블릿)', 'fm_goods',	'contents', 100);
		$cellList['BBNN02020']	= array('상품설명(모바일)', 'fm_goods',	'mobile_contents', 100);
		$cellList['BBNN02021']	= array('상품공통정보고유번호', 'fm_goods',	'info_seq', 15);
		$cellList['BBNNM2022']	= array('상품상세확대이미지', 'fm_goods_image',	'large', 30);
		$cellList['BBNNM2023']	= array('상품상세이미지', 'fm_goods_image',	'view', 30);
		$cellList['BBNNM2024']	= array('리스트이미지1', 'fm_goods_image',	'list1', 30);
		$cellList['BBNNM2025']	= array('리스트이미지2', 'fm_goods_image',	'list2', 30);
		$cellList['BBNNM2026']	= array('상품상세썸네일', 'fm_goods_image',	'thumbView', 30);
		$cellList['BBNNM2027']	= array('장바구니썸네일', 'fm_goods_image',	'thumbCart', 30);
		$cellList['BBNNM2028']	= array('스크롤썸네일', 'fm_goods_image',	'thumbScroll', 30);
		$cellList['BBNNR2038']	= array('관련상품직접선정', 'fm_goods_relation',	'relation', 20);
		$cellList['BBNN02030']	= array('관련상품노출', 'fm_goods',	'relation_image_size', 15);
		$cellList['BBNN02031']	= array('입점마케팅전달', 'fm_goods',	'feed_status', 15);
		$cellList['BBNN02032']	= array('입점마케팅전달이벤트', 'fm_goods',	'feed_evt_text', 15);
		$cellList['BBNN02035']	= array('입점마케팅검색어', 'fm_goods',	'openmarket_keyword', 20);
		$cellList['BBNN02036']	= array('입점마케팅배송비', 'fm_goods',	'feed_ship_type', 20);
		$cellList['BBNN02033']	= array('관리메모', 'fm_goods',	'admin_memo', 15);
		$cellList['BBNN02037']	= array('선물하기', 'fm_goods',	'present_use', 15);
		$cellList['ABNNP2029']	= array('판매자관련상품직접선정', 'fm_goods_relation_seller',	'relation_seller', 15);

		// 오픈마켓 상품명 필수 체크 s
		$this->load->model('connectormodel');
		$MarketConnectorClause	= config_load('MarketConnectorClause');
		$MarketLinkage			= config_load('MarketLinkage');
		$params					= array();
		$params['account_use']	= 'Y';

		if($MarketLinkage['shopCode'] == "firstmall"){
			$useMarketList	= $this->connectormodel->getUseMarketList($params);
		}else{
			$useMarketList	= $this->connectormodel->getUseShoplinkerMarketList($params);
		}

		$useMarket = !empty($useMarketList) ? true : false;

		// MarketConnectorClause 아직 사용안함이거나, useMarket 하나도 없는 경우
		if( $MarketConnectorClause == 'NOT_YET' || $useMarket == false ) {
			// 오픈마켓 상품명 KEY BBRN00025 에서 BBNN00025 로 변경
			$cellList['BBNN00025']	= $cellList['BBRN00025'];
			unset($cellList['BBRN00025']);
		}
		// 오픈마켓 상품명 필수 체크 e
		// 사용여부 체크에 따른 재배열
		foreach($cellList as $code => $data){
			$service_type		= substr($code, 0, 1);
			$goods_type			= substr($code, 1, 1);
			$upload_required	= substr($code, 2, 1);
			$zero_except		= substr($code, 3, 1);
			if		($service_type == 'A' && $this->m_sServiceType != 'A')			continue;
			elseif	($service_type == 'N' && $this->m_sServiceType != 'N')			continue;
			elseif	($goods_type == 'C' && $this->m_sGoodsKind != 'C')				continue;
			elseif	($goods_type == 'G' && $this->m_sGoodsKind != 'G')				continue;
			elseif	(!$this->except_selleradmin_cell($code, $data))					continue;

			// 창고재고관련 필드는 SCM 버전에서만 사용
			if($data[1] == "fm_scm_revision"){
				if($this->scmmodel->chkScmConfig(true) != true) continue;
			}

			$aCellList[$code]		= $data[0];
			$aTableInfo[$code]		= $data[1];
			$aFieldInfo[$code]		= $data[2];
			$aWidthInfo[$code]		= $data[3];
			if	($zero_except == 'Y'){
				$aNeedZeroVal[$code]	= $data[2];
			}
		}
		$this->m_aCellList		= $aCellList;
		$this->m_aTableInfo		= $aTableInfo;
		$this->m_aFieldInfo		= $aFieldInfo;
		$this->m_aWidthInfo		= $aWidthInfo	;
		$this->m_aNeedZeroVal	= $aNeedZeroVal;

		// 이미지 영문명
		$this->m_aImageName		= array('list1'			=> '리스트1',
										'list2'			=> '리스트2',
										'view'			=> '상품상세',
										'large'			=> '상품확대',
										'thumbView'		=> '상세썸네일',
										'thumbScroll'	=> '스크롤썸네일',
										'thumbCart'		=> '장바구니썸네일');
	}

	// 입점사 사용가능한 컬럼
	public function except_selleradmin_cell($code, $data){
		$return		= true;
		if	($this->m_sAdminType == 'S'){
			if		($data[2] == 'provider_seq')			$return	= false;
			elseif	($data[2] == 'sub_sale')				$return	= false;
			elseif	($data[2] == 'scm_use_revision')		$return	= false;
			elseif	($data[2] == 'location_stock')			$return	= false;
			elseif	($data[2] == 'location_supply_price')	$return	= false;
			elseif	($data[2] == 'default_date')			$return	= false;
			elseif	($this->m_sProcType == 'U'){
				if		($data[2] == 'provider_status')		$return	= false;
				elseif	($data[2] == 'commission_rate')		$return	= false;
				elseif	($data[2] == 'commission_price')	$return	= false;
				elseif	($data[2] == 'sub_commission_rate')	$return	= false;
				elseif	($data[2] == 'sub_commission_price')$return	= false;
				elseif	($data[2] == 'reserve_rate')		$return	= false;
				elseif	($data[2] == 'sub_reserve_rate')	$return	= false;
				elseif	($data[2] == 'sale_seq')	$return	= false;
			}
		}

		return $return;
	}

	// 입점사 excel upload 시 설정에 따라 무시할 상품 기본컬럼 ( exception은 exception에서 예외처리 )
	public function set_provider_ignore(){

		$this->m_aUpdateProviderIgnore['default']			= array();
		$this->m_aUpdateProviderIgnore['goods']				= array();
		$this->m_aUpdateProviderIgnore['except']			= array();

		if($this->m_sAdminType == 'S') {
			$this->m_aUpdateProviderIgnore['default'][]			= 'provider_seq';
		}

		if	($this->m_sAdminType == 'S'){
			$this->m_aUpdateProviderIgnore['default'][]		= 'provider_status';
			if	($this->m_sProviderChoice == 'N'){
				$this->m_aUpdateProviderIgnore['goods'][]	= 'goods_name';
				$this->m_aUpdateProviderIgnore['goods'][]	= 'goods_name_linkage';
			//	$this->m_aUpdateProviderIgnore['goods'][]	= 'goods_status';
				$this->m_aUpdateProviderIgnore['goods'][]	= 'string_price';
				$this->m_aUpdateProviderIgnore['goods'][]	= 'member_string_price';
				$this->m_aUpdateProviderIgnore['goods'][]	= 'allmember_string_price';
				$this->m_aUpdateProviderIgnore['except'][]	= 'option';
				$this->m_aUpdateProviderIgnore['except'][]	= 'suboption';
			}
		}
	}

	// 복수열이 한Cell로 저장되는 컬럼에 대한 정의
	public function set_multiRow_cell(){

		// 복수열이 한Cell로 저장되는 컬럼들 ( option, suboption, input )
		$this->m_aOptionMultiRowCell	= array('option_seq', 'option1', 'option2', 'option3',
										'option4', 'option5', 'optioncode1', 'optioncode2',
										'optioncode3', 'optioncode4', 'optioncode5', 'infomation',
										'stock', 'badstock', 'safe_stock', 'weight', 'option_view',
										'supply_price', 'consumer_price', 'price', 'action_option_kind', 'full_barcode');
		$this->m_aOptionMultiRowCellKR	= array('옵션번호', '필수옵션값1', '필수옵션값2', '필수옵션값3',
										'필수옵션값4', '필수옵션값5', '필수옵션값1코드', '필수옵션값2코드',
										'필수옵션값3코드', '필수옵션값4코드', '필수옵션값5코드', '옵션설명',
										'필수옵션재고', '필수옵션불량재고', '필수옵션안전재고', '필수옵션무게', '필수옵션노출',
										'매입가', '정가', '할인가(판매가)', '필수옵션동작구분', '필수옵션바코드');

		if	(in_array('commission_rate', $this->m_aFieldInfo))		$this->m_aOptionMultiRowCell[]		= 'commission_rate';
		if	(in_array('reserve_rate', $this->m_aFieldInfo))		$this->m_aOptionMultiRowCell[]		= 'reserve_rate';
		if	(in_array('commission_price', $this->m_aFieldInfo))		$this->m_aOptionMultiRowCell[]		= 'commission_price';
		$this->m_aSuboptionMultiRowCell	= array('suboption_seq', 'suboption_title', 'suboption',
												'suboption_code', 'sub_stock', 'sub_badstock',
												'sub_safe_stock', 'sub_weight', 'sub_option_view',
												'sub_supply_price', 'sub_consumer_price', 'sub_price',
												'action_suboption_kind', 'sub_full_barcode');
		if	(in_array('sub_commission_rate', $this->m_aFieldInfo))	$this->m_aSuboptionMultiRowCell[]	= 'sub_commission_rate';
		if	(in_array('sub_reserve_rate', $this->m_aFieldInfo))	$this->m_aSuboptionMultiRowCell[]	= 'sub_reserve_rate';
		if	(in_array('sub_commission_price', $this->m_aFieldInfo))	$this->m_aSuboptionMultiRowCell[]	= 'sub_commission_price';
		$this->m_aInputMultiRowCell		= array('input_name', 'input_form');

		// 필수로 row 수가 맞아야 되는 복수열 Cell 컬럼들 ( option, suboption, input )
		$this->m_aOptionRowCountCell	= array('option1', 'option2', 'option3', 'option4',
												'option5', 'stock', 'supply_price', 'consumer_price', 'price');

		if	(in_array('commission_rate', $this->m_aFieldInfo))		$this->m_aOptionRowCountCell[]		= 'commission_rate';
		if	(in_array('reserve_rate', $this->m_aFieldInfo))		$this->m_aOptionRowCountCell[]		= 'reserve_rate';
		$this->m_aSuboptionRowCountCell	= array('suboption_title', 'suboption',
												'sub_stock', 'sub_badstock', 'sub_safe_stock',
												'sub_weight', 'sub_supply_price',
												'sub_consumer_price', 'sub_price',
												'action_suboption_kind');
		if	(in_array('sub_commission_rate', $this->m_aFieldInfo))	$this->m_aSuboptionRowCountCell[]	= 'sub_commission_rate';
		if	(in_array('sub_reserve_rate', $this->m_aFieldInfo))	$this->m_aSuboptionRowCountCell[]	= 'sub_reserve_rate';
		$this->m_aInputRowCountCell		= $this->m_aInputMultiRowCell;
	}

	// 예외 처리가 필요한 Cell의 예외별 종류 추출
	public function get_except_cell_list($code){
		$except_type	= substr($code, 4, 1);

		//byPassExcept code는 예외처리 하지 않는다.(그룹핑만 하기위함)
		$byPassExcept	= array('BBNNI0029','BGNNS2013');
		if(array_search($code, $byPassExcept) !== false)	$except_type	= '0';

		switch($except_type){
			case 'O':		return 'option';			break;
			case 'S':		return 'suboption';			break;
			case 'I':		return 'input';				break;
			case 'C':		return 'category';			break;
			case 'B':		return 'brand';				break;
			case 'L':		return 'location';			break;
			case 'A':		return 'addition';			break;
			case 'N':		return 'icon';				break;
			case 'M':		return 'image';				break;
			case 'R':		return 'relation';			break;
			case 'P':		return 'relation_seller';	break;
			case 'W':		return 'warehouse';			break;
		}

		return '';
	}

	// 예외 처리명 추출
	public function get_except_code_to_name($except){
		switch($except){
			case 'option':			return '필수옵션';			break;
			case 'suboption':		return '추가옵션';			break;
			case 'input':			return '입력옵션';			break;
			case 'category':		return '카테고리';			break;
			case 'brand':			return '브랜드';			break;
			case 'location':		return '지역';				break;
			case 'addition':		return '추가정보';			break;
			case 'icon':			return '아이콘';			break;
			case 'image':			return '이미지';			break;
			case 'relation':		return '관련상품';			break;
			case 'relation_seller':	return '판매자 관련상품';	break;
		}

		return '';
	}

	// 회원할인세트 엑셀 목록 컬럼 :: 2019-09-19 pjw
	public function get_membersale_cell_list($type){
		switch($type){
			// 회원등급할인 양식 추가 (본사) :: 2019-09-18 pjw
			case 'default':
				
				// 메인 열 명칭 정의
				$cell['provider_seq']['name']						= '판매자번호';
				$cell['provider_name']['name']						= '판매자명';
				$cell['brand_id']['name']							= '브랜드번호';
				$cell['brand_name']['name']							= '브랜드명';
				$cell['type']['name']								= '타입';
				$cell['goods_seq']['name']							= '번호';				
				$cell['goods_name']['name']							= '상품명';
				$cell['consumer_price']['name']						= '정가';
				$cell['price']['name']								= '판매가';
				$cell['act_cal']['name']							= '정산대상금액';
				$cell['act_cal']['subcell']['target_price']			= '';
				$cell['act_cal']['subcell']['settle_price']			= '결제금액(A)';
				$cell['act_cal']['subcell']['sale_price_head']		= '본사할인';
				$cell['sale_rate']['name']							= '할인율';
				$cell['supply_price']['name']						= '매입금액';
				$cell['margin_price']['name']						= '마진';
				$cell['act_price']['name']							= '정산금액';
				$cell['benefit']['name']							= '이익';
				$cell['benefit']['subcell']['benefit_price']		= '(C)=(A)+(B)';
				$cell['benefit']['subcell']['benefit_rate']			= '이익율';
				$cell['sale_seq']['name']							= '회원등급별할인세트번호';

				// 각 셀 width 값 정의
				// 따로 키값은 필요 없고 총 셀 개수만큼만 만든다 (19개)
				$cell_width[]	=  7;
				$cell_width[]	=  15;
				$cell_width[]	=  7;
				$cell_width[]	=  15;
				$cell_width[]	=  10;
				$cell_width[]	=  7;
				$cell_width[]	=  20;
				$cell_width[]	=  15;
				$cell_width[]	=  15;
				$cell_width[]	=  15;
				$cell_width[]	=  15;
				$cell_width[]	=  15;
				$cell_width[]	=  10;
				$cell_width[]	=  10;
				$cell_width[]	=  15;
				$cell_width[]	=  15;
				$cell_width[]	=  15;
				$cell_width[]	=  10;
				$cell_width[]	=  25;

			break;

			// 회원등급할인 양식 추가 (입점사 수수료율방식) :: 2019-09-18 pjw
			case 'saco':

				// 메인 열 명칭 정의
				$cell['provider_seq']['name']						= '판매자번호';
				$cell['provider_name']['name']						= '판매자명';
				$cell['brand_id']['name']							= '브랜드번호';
				$cell['brand_name']['name']							= '브랜드명';
				$cell['type']['name']								= '타입';
				$cell['goods_seq']['name']							= '번호';				
				$cell['goods_name']['name']							= '상품명';
				$cell['consumer_price']['name']						= '정가';
				$cell['price']['name']								= '판매가';
				$cell['act_cal']['name']							= '정산대상금액';
				$cell['act_cal']['subcell']['target_price']			= '';
				$cell['act_cal']['subcell']['settle_price']			= '결제금액(A)';
				$cell['act_cal']['subcell']['sale_price_head']		= '본사할인';
				$cell['sale_rate']['name']							= '할인율';
				$cell['commission_rate']['name']					= '수수료율';
				$cell['margin_price']['name']						= '마진';
				$cell['act_price']['name']							= '정산금액';
				$cell['benefit']['name']							= '이익';
				$cell['benefit']['subcell']['benefit_price']		= '(C)=(A)+(B)';
				$cell['benefit']['subcell']['benefit_rate']			= '이익율';
				$cell['sale_seq']['name']							= '회원등급별할인세트번호';

				// 각 셀 width 값 정의
				// 따로 키값은 필요 없고 총 셀 개수만큼만 만든다 (19개)
				$cell_width[]	=  7;
				$cell_width[]	=  15;
				$cell_width[]	=  7;
				$cell_width[]	=  15;
				$cell_width[]	=  10;
				$cell_width[]	=  7;
				$cell_width[]	=  20;
				$cell_width[]	=  15;
				$cell_width[]	=  15;
				$cell_width[]	=  15;
				$cell_width[]	=  15;
				$cell_width[]	=  15;
				$cell_width[]	=  10;
				$cell_width[]	=  10;
				$cell_width[]	=  15;
				$cell_width[]	=  15;
				$cell_width[]	=  15;
				$cell_width[]	=  10;
				$cell_width[]	=  25;
				
			break;

			// 회원등급할인 양식 추가 (입점사 공급가방식) :: 2019-09-18 pjw
			case 'suco':
			case 'supr':

				// 메인 열 명칭 정의
				$cell['provider_seq']['name']						= '판매자번호';
				$cell['provider_name']['name']						= '판매자명';
				$cell['brand_id']['name']							= '브랜드번호';
				$cell['brand_name']['name']							= '브랜드명';
				$cell['type']['name']								= '타입';
				$cell['goods_seq']['name']							= '번호';				
				$cell['goods_name']['name']							= '상품명';
				$cell['consumer_price']['name']						= '정가';
				$cell['price']['name']								= '판매가';
				$cell['act_cal']['name']							= '정산대상금액';
				$cell['act_cal']['subcell']['target_price']			= '';
				$cell['act_cal']['subcell']['settle_price']			= '결제금액(A)';
				$cell['act_cal']['subcell']['sale_price_head']		= '본사할인';
				$cell['sale_rate']['name']							= '할인율';				
				$cell['supply_price']['name']						= '공급금액';
				$cell['supply_rate']['name']						= '공급율';
				$cell['margin_price']['name']						= '마진';
				$cell['act_price']['name']							= '정산금액';
				$cell['benefit']['name']							= '이익';
				$cell['benefit']['subcell']['benefit_price']		= '(C)=(A)+(B)';
				$cell['benefit']['subcell']['benefit_rate']			= '이익율';
				$cell['sale_seq']['name']							= '회원등급별할인세트번호';

				// 각 셀 width 값 정의
				// 따로 키값은 필요 없고 총 셀 개수만큼만 만든다 (20개)
				$cell_width[]	=  7;
				$cell_width[]	=  15;
				$cell_width[]	=  7;
				$cell_width[]	=  15;
				$cell_width[]	=  10;
				$cell_width[]	=  7;
				$cell_width[]	=  20;
				$cell_width[]	=  15;
				$cell_width[]	=  15;
				$cell_width[]	=  15;
				$cell_width[]	=  15;
				$cell_width[]	=  15;
				$cell_width[]	=  10;
				$cell_width[]	=  15;
				$cell_width[]	=  10;
				$cell_width[]	=  15;
				$cell_width[]	=  15;
				$cell_width[]	=  15;
				$cell_width[]	=  10;
				$cell_width[]	=  25;
			break;
		}

		// 수정가능 요소 정의 (modify는 실제 데이터까지 적용됨, check는 엑셀 내에서만 실시간 확인용으로 수정가능)
		// $cell 변수의 1차 요소에만 적용
		$meta_cell			= array('provider_seq', 'provider_name', 'brand_id', 'brand_name', 'type');
		$able_modify_cell	= array('consumer_price', 'price', 'commission_rate', 'supply_price', 'supply_rate', 'sale_seq');
		$able_check_cell	= array('sale_rate');

		return array(
			'cell'				=> $cell,
			'able_modify_cell'	=> $able_modify_cell,
			'able_check_cell'	=> $able_check_cell,
			'cell_width'		=> $cell_width,
			'meta_cell'			=> $meta_cell,
		);
	}

	// 회원등급할인세트 연결 상품 목록 가져오기 :: 2019-09-19 pjw
	public function get_membersale_goods($params, $type='list'){

		############ 브랜드 및 입점사 정보 가져오기 ############
		
		// 브랜드 정보
		if($params['category_code']){
			$sql			= "SELECT id, title FROM fm_brand WHERE category_code = ?";
			$query			= $this->db->query($sql, array($params['category_code']));
			$brand_info		= $query->row_array();
		}else{
			$brand_info		= array(
				'id'	=> '',
				'title'	=> '미연결',
			);
		}
		
		// 입점사 정보
		// 본사인 경우 수동으로 만들어서 리턴
		if($params['provider_seq'] > 1){
			
			$sql			= "SELECT fp.provider_seq, fp.provider_name, fpc.commission_type FROM fm_provider as fp LEFT JOIN fm_provider_charge as fpc ON fp.provider_seq = fpc.provider_seq WHERE fp.provider_seq = ?";
			$query			= $this->db->query($sql, array($params['provider_seq']));
			$provider_info	= $query->row_array();

		}else{
			
			$provider_info	= array(
				'provider_seq'		=> 1,
				'provider_name'		=> '본사',
				'commission_type'	=> 'default',
			);

		}
		
		############ 특정 브랜드에 연결 된 특정 입점사 상품의 목록 ############

		// type 값에 따라 count 혹은 목록 조회 여부 설정
		$select_type = $type == 'cnt' ? 'count(*) as cnt' : "fg.goods_seq, fg.provider_seq, fg.goods_name, fg.sale_seq, fg.package_yn, IFNULL(fbl.category_code, '') as category_code, IF(fmgi.sale_seq > 0, 'Y', 'N') AS is_sale_except";

		// 상품목록 쿼리 조회
		$sqlWhereBind	= array($params['provider_seq']);
		
		// 브랜드 미연결인 경우
		if($params['category_code']){
			$join_brand			= " INNER JOIN ";
			$where_brand		= " fbl.category_code = ? ";
			$sqlWhereBind[]		= $params['category_code'];
		}else{
			$join_brand			= " RIGHT JOIN ";
			$where_brand		= " fbl.category_code is null ";
		}
		$sql					= "SELECT ".$select_type."
									FROM   fm_brand_link AS fbl 
										   " . $join_brand . " fm_goods AS fg 
												   ON fg.goods_seq = fbl.goods_seq 
										   LEFT JOIN fm_member_group_issuegoods AS fmgi 
												  ON fg.sale_seq = fmgi.sale_seq 
													 AND fg.goods_seq = fmgi.goods_seq 
									WHERE  fg.provider_seq = ?
										   AND " . $where_brand . "
									ORDER BY fg.goods_seq ";
		
		// 목록으로 가져오는 경우에만 limit 제한
		if($type == 'list'){
			$sql			.= " LIMIT ?, ? ";
			$sqlWhereBind[]  = $params['limit_s'];
			$sqlWhereBind[]  = $params['limit_e'];
		}

		// cnt 조회인 경우 바로 리턴
		$query = $this->db->query($sql, $sqlWhereBind);

		if($type == 'cnt'){
			return array(
				'goods_info'	=> $query->row_array(),
				'brand_info'	=> $brand_info,
				'provider_info'	=> $provider_info,
			);
		}else{
			$goods_list	= $query->result_array();
		}

		############ 등급할인세트 별 필수옵션 할인금액 최대값인 행만 추출 ############
		
		// 쿼리는 필수, 추가 옵션 동일하게 사용
		$sql = "SELECT	mgsd.sale_seq, 
						mgsd.{price_key}, 
						mgsd.{price_key}_type 
				FROM   fm_member_group_sale_detail AS mgsd, 
				   (SELECT sale_seq, 
					   Max({price_key}) AS 
					   max_sale_price 
					FROM fm_member_group_sale_detail 
					GROUP  BY sale_seq) AS mgsd2 
				WHERE mgsd.{price_key} = mgsd2.max_sale_price AND mgsd.sale_seq = mgsd2.sale_seq
				GROUP BY sale_seq ";
		
		// 필수옵션 최댓값 행 가져옴
		$query					= $this->db->query(str_replace('{price_key}', 'sale_price', $sql));
		$sale_price				= $query->result_array();
		$search_sale_price		= array_flip(array_column($sale_price, 'sale_seq'));


		// 추가옵션 최댓값 행 가져옴
		$query					= $this->db->query(str_replace('{price_key}', 'sale_option_price', $sql));
		$sale_opt_price			= $query->result_array();
		$search_sale_opt_price  = array_flip(array_column($sale_opt_price, 'sale_seq'));


		############ 상품별 정보 세팅 ############ 

		$goods_seq_arr		= array();	// 상품번호 배열 선언
		$result_goods_list	= array();	// 상품목록 결과값
	
		foreach($goods_list as $key => $goods){
			
			// 상품별 연결된 등급할인세트 값 세팅
			$goods['sale_price']					= $sale_price[$search_sale_price[$goods['sale_seq']]]['sale_price'];
			$goods['sale_price_type']				= $sale_price[$search_sale_price[$goods['sale_seq']]]['sale_price_type'];
			$goods['sale_option_price']				= $sale_opt_price[$search_sale_opt_price[$goods['sale_seq']]]['sale_option_price'];
			$goods['sale_option_price_type']		= $sale_opt_price[$search_sale_opt_price[$goods['sale_seq']]]['sale_option_price_type'];
			
			// 결과값 세팅
			$goods_seq_arr[]						= $goods['goods_seq'];
			$result_goods_list[$goods['goods_seq']] = $goods;
		}

		// 상품 갯수 누적 추가 (rowspan 용도)
		$total_row = count($result_goods_list);


		############ 상품번호 배열로 상품 필수, 추가옵션 정보 및 매입가 조회 ############
		
		// 필수옵션 쿼리
		$sql		= "SELECT fgo.goods_seq, 
							   fgo.option_seq,
							   fgo.option_title, 
							   fgo.option1, 
							   fgo.option2, 
							   fgo.option3, 
							   fgo.option4, 
							   fgo.option5, 
							   fgo.consumer_price,
							   fgo.price,
							   fgo.commission_type, 
							   fgo.commission_rate, 
							   fgs.supply_price
						FROM   fm_goods_option AS fgo 
							   LEFT JOIN fm_goods_supply AS fgs 
									  ON fgo.option_seq = fgs.option_seq 
						WHERE  fgo.goods_seq IN ( '".implode("','", $goods_seq_arr)."' ) 
						ORDER BY fgo.option_seq";
		
		// 필수옵션 처리
		$query		= $this->db->query($sql);
		$opt_list	= $query->result_array();


		// 옵션에 맞는 상품에 옵션값 추가 
		foreach($opt_list as $key => $opt){

			// 상품 정보
			$goods_info		= $result_goods_list[$opt['goods_seq']];
			
			// 옵션명 배열
			if(!empty($opt['option1'])) $option_name[] = $opt['option1'];
			if(!empty($opt['option2'])) $option_name[] = $opt['option2'];
			if(!empty($opt['option3'])) $option_name[] = $opt['option3'];
			if(!empty($opt['option4'])) $option_name[] = $opt['option4'];
			if(!empty($opt['option5'])) $option_name[] = $opt['option5'];

			// 해당 상품 필수옵션 할인율
			$sale_type		= $goods_info['sale_price_type'];
			
			// 본사, 입점사 방식에 따라 타입값 구분 ( NONE=본사, SACO-수수료방식, SUCO-공급가퍼센트, SUPR-공급가가격 )
			$comission_type		= $provider_info['provider_seq'] == 1 ? 'NONE' : $opt['commission_type'];
			

			// 할인율이 금액인지 퍼센트인지에 따라 구분
			// 같은 수식은 미리 정의
			$sale_rate							= $goods_info['sale_price'];													// 할인율
			$sale_rate_text						= $sale_type == 'PER' ? $sale_rate.'%' : get_currency_price($sale_rate, 1);		// 할인율 (텍스트 노출용)
			$sale_rate_head						= $sale_type == 'PER' ? '=I{rownum} * M{rownum}' : '=M{rownum}';				// 본사할인
			$commission_rate					= $opt['commission_rate'];														// 수수료율
			$commission_rate_text				= $commission_rate.'%';															// 수수료율 (텍스트 노출용)
			$supply_price						= $commission_rate;
			$supply_rate						= $commission_rate.'%';
			
			

			// 엑셀 실제 삽입 데이터 정의
			$new_opt_cells				= array();
			$new_opt_cells[]			= '';
			$new_opt_cells[]			= '';
			$new_opt_cells[]			= '';
			$new_opt_cells[]			= '';
			$new_opt_cells[]			= 'option';
			$new_opt_cells[]			= $opt['option_seq'];
			$new_opt_cells[]			= implode(' | ', $option_name);			
			$new_opt_cells[]			= number_format($opt['consumer_price']);
			$new_opt_cells[]			= number_format($opt['price']);

			// 본사, 입점사 방식에 따라 구분하여 엑셀 수식을 만듬
			switch($comission_type){

				case 'NONE':

					$new_opt_cells[]			= '=K{rownum}';								// 정산대상금액
					$new_opt_cells[]			= '=I{rownum} - L{rownum}';					// 결제금액   
					$new_opt_cells[]			= $sale_rate_head;							// 본사할인   
					$new_opt_cells[]			= $sale_rate_text;							// 할인율    
					$new_opt_cells[]			= number_format($opt['supply_price']);		// 매입금액   
					$new_opt_cells[]			= '=J{rownum} - P{rownum}';					// 마진
					$new_opt_cells[]			= '=N{rownum}';								// 정산금액

				break;

				case 'SACO':

					$new_opt_cells[]			= '=K{rownum} + L{rownum}';	  // 정산대상금액   
					$new_opt_cells[]			= '=I{rownum} - L{rownum}';	  // 결제금액      
					$new_opt_cells[]			= $sale_rate_head;			  // 본사할인      
					$new_opt_cells[]			= $sale_rate_text;			  // 할인율       
					$new_opt_cells[]			= $commission_rate_text;	  // 수수료율      
					$new_opt_cells[]			= '=J{rownum} * N{rownum}';	  // 마진         
					$new_opt_cells[]			= '=J{rownum} - O{rownum}';	  // 정산금액      
					

				break;

				case 'SUCO':
				case 'SUPR':
					
					$new_opt_cells[]			= '=K{rownum}';												// 정산대상금액   
					$new_opt_cells[]			= '=I{rownum} - L{rownum}';									// 결제금액      
					$new_opt_cells[]			= $sale_rate_head;											// 본사할인      
					$new_opt_cells[]			= $sale_rate_text;											// 할인율       
					$new_opt_cells[]			= number_format($supply_price);								// 공급금액      
					$new_opt_cells[]			= $supply_rate;												// 공급율      
					$new_opt_cells[]			= '=J{rownum} - Q{rownum}';									// 마진         
					$new_opt_cells[]			= '=IF(N{rownum} = "",H{rownum} * O{rownum},N{rownum})';	// 정산금액      

				break;
			}
			
			$new_opt_cells[]			= '=K{rownum} - P{rownum}';							 // 이익금액
			$new_opt_cells[]			= '=CONCATENATE(Q{rownum} / K{rownum} * 100, "%")';	 // 이익율
			$new_opt_cells[]			= '';											 // 회원등급세트번호 (옵션에서는 빈값)

			
			$result_goods_list[$opt['goods_seq']]['option_cells'][] = $new_opt_cells;

			// 초기화할 변수
			unset($new_opt_cells);
			unset($option_name);
			unset($supply_rate);
			unset($supply_price);
		}

		// 상품 필수옵션 갯수 누적 추가 (rowspan 용도)
		$total_row += count($opt_list);

		// 추가옵션 쿼리
		$sql		= "SELECT  fgso.goods_seq, 
							   fgso.suboption_seq, 
							   fgso.suboption_title, 
							   fgso.suboption, 
							   fgso.consumer_price, 
							   fgso.price,
							   fgso.commission_type,
							   fgso.commission_rate,
							   fgs.supply_price
						FROM   fm_goods_suboption AS fgso 
							   LEFT JOIN fm_goods_supply AS fgs 
									  ON fgso.suboption_seq = fgs.suboption_seq 
						WHERE  fgso.goods_seq IN ( '".implode("','", $goods_seq_arr)."' ) 
						ORDER BY fgso.suboption_seq";

		
		// 추가옵션 처리
		$query		 = $this->db->query($sql);
		$subopt_list = $query->result_array();

		// 옵션에 맞는 상품에 옵션값 추가 
		foreach($subopt_list as $key => $subopt){

			// 상품 정보
			$goods_info		= $result_goods_list[$subopt['goods_seq']];
			
			// 해당 상품 필수옵션 할인율
			$sale_type		= $goods_info['sale_option_price_type'];
			
			// 본사, 입점사 방식에 따라 타입값 구분 ( NONE=본사, SACO-수수료방식, SUCO-공급가퍼센트, SUPR-공급가가격 )
			$comission_type			= $provider_info['provider_seq'] == 1 ? 'NONE' : $subopt['commission_type'];

			// 할인율이 금액인지 퍼센트인지에 따라 구분
			// 같은 수식은 미리 정의
			$sale_rate							= $goods_info['sale_option_price'];												// 할인율
			$sale_rate_text						= $sale_type == 'PER' ? $sale_rate.'%' : get_currency_price($sale_rate, 1);		// 할인율 (텍스트 노출용)
			$sale_rate_head						= $sale_type == 'PER' ? '=I{rownum} * M{rownum}' : '=M{rownum}';				// 본사할인
			$commission_rate					= $subopt['commission_rate'];													// 수수료율		
			$commission_rate_text				= $commission_rate.'%';															// 수수료율 (텍스트 노출용)
			$supply_price						= $commission_rate;
			$supply_rate						= $commission_rate.'%';


			// 엑셀 실제 삽입 데이터 정의
			$new_subopt_cells				= array();
			$new_subopt_cells[]			= '';
			$new_subopt_cells[]			= '';
			$new_subopt_cells[]			= '';
			$new_subopt_cells[]			= '';
			$new_subopt_cells[]			= 'suboption';
			$new_subopt_cells[]			= $subopt['suboption_seq'];
			$new_subopt_cells[]			= 'ㄴ'.$subopt['suboption'];
			$new_subopt_cells[]			= number_format($subopt['consumer_price']);
			$new_subopt_cells[]			= number_format($subopt['price']);

			// 본사, 입점사 방식에 따라 구분하여 엑셀 수식을 만듬
			switch($comission_type){

				case 'NONE':

					$new_subopt_cells[]			= '=K{rownum}';								// 정산대상금액
					$new_subopt_cells[]			= '=I{rownum} - L{rownum}';					// 결제금액   
					$new_subopt_cells[]			= $sale_rate_head;							// 본사할인   
					$new_subopt_cells[]			= $sale_rate_text;							// 할인율   M   
					$new_subopt_cells[]			= number_format($subopt['supply_price']);	// 매입금액   
					$new_subopt_cells[]			= '=J{rownum} - P{rownum}';					// 마진
					$new_subopt_cells[]			= '=N{rownum}';								// 정산금액

				break;

				case 'SACO':

					$new_subopt_cells[]			= '=K{rownum} + J{rownum}';	  // 정산대상금액   
					$new_subopt_cells[]			= '=I{rownum} - L{rownum}';	  // 결제금액      
					$new_subopt_cells[]			= $sale_rate_head;			  // 본사할인      
					$new_subopt_cells[]			= $sale_rate_text;			  // 할인율       
					$new_subopt_cells[]			= $commission_rate_text;	  // 수수료율      
					$new_subopt_cells[]			= '=J{rownum} * N{rownum}';	  // 마진         
					$new_subopt_cells[]			= '=J{rownum} - O{rownum}';	  // 정산금액      
					

				break;

				case 'SUCO':
				case 'SUPR':
					
					$new_subopt_cells[]			= '=K{rownum}';												// 정산대상금액   
					$new_subopt_cells[]			= '=I{rownum} - L{rownum}';									// 결제금액      
					$new_subopt_cells[]			= $sale_rate_head;											// 본사할인      
					$new_subopt_cells[]			= $sale_rate_text;											// 할인율       
					$new_subopt_cells[]			= number_format($supply_price);								// 공급금액      
					$new_subopt_cells[]			= $supply_rate;												// 공급율      
					$new_subopt_cells[]			= '=J{rownum} * Q{rownum}';									// 마진         
					$new_subopt_cells[]			= '=IF(N{rownum} = "",H{rownum} * O{rownum},N{rownum})';	// 정산금액      

				break;
			}
			
			$new_subopt_cells[]			= '=K{rownum} - P{rownum}';							 // 이익금액
			$new_subopt_cells[]			= '=CONCATENATE(Q{rownum} / K{rownum} * 100, "%")';	 // 이익율
			$new_subopt_cells[]			= '';												 // 회원등급세트번호 (옵션에서는 빈값)

			
			$result_goods_list[$subopt['goods_seq']]['suboption_cells'][] = $new_subopt_cells;

			// 초기화할 변수
			unset($new_subopt_cells);
			unset($supply_rate);
			unset($supply_price);
			
		}

		// 상품 필수옵션 갯수 누적 추가 (rowspan 용도)
		$total_row += count($subopt_list);

		// 결과값 리턴
		return array(
			'brand_info'			=> $brand_info,
			'provider_info'			=> $provider_info,
			'result_goods_list'		=> $result_goods_list,
			'total_row'				=> $total_row,
		);
	}

	########## ↑↑↑↑↑ 기본 설정 배열 함수 ↑↑↑↑↑ ##########

}

/* End of file goodsexcel.php */
/* Location: ./app/models/goodsexcel */

