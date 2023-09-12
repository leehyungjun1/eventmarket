<?php
class barcodeexcel extends CI_Model {
	var $PHPExcel				= null;
	var $IOFactory				= null;
	var $MODE					= '';
	var $GOODS_SEQ_LIST			= array();
	var $EXCEL_UPLOAD_PATH		= '';
	var $EXCEL_DOWNLOAD_PATH	= '';
	var $EXCEL_LOG_PATH			= '';
	var $PROCESS_TYPE			= '';
	var $MAX_ROWS				= 3000;
	var $PAGE					= 1;
	var $TOTAL_ROWS				= 0;
	var $TOTAL_PAGE				= 0;
	var $EXCEL_COLUMN			= array();
	var $EXCEL_UPLOAD_COLUMN	= array();
	var $UPLOAD_FILE_NAME		= array();
	var $UPLOAD_USER			= '';
	var $SUCCESS_LOG_FILE		= null;
	var $FAIL_LOG_FILE			= null;

	public function __construct(){

		$this->EXCEL_DOWNLOAD_PATH	= ROOTPATH . 'data/excel_tmp';
		$this->EXCEL_UPLOAD_PATH	= ROOTPATH . 'data/tmp';
		$this->EXCEL_LOG_PATH		= ROOTPATH . 'data/excel_tmp';
		$this->EXCEL_COLUMN			= array(
			array(
				'바코드'	=> 'ss:MergeAcross="1"',
				'상품번호'	=> 'ss:MergeDown="1"',
				'옵션번호'	=> 'ss:MergeDown="1"',
				'상품명'	=> 'ss:MergeDown="1"',
				'옵션명'	=> 'ss:MergeDown="1"',
				'재고'		=> 'ss:MergeDown="1"',
				'불량재고'	=> 'ss:MergeDown="1"'
			),
			array(
				'기본코드'	=> '',
				'옵션코드'	=> ''
			)
		);

		$this->EXCEL_UPLOAD_COLUMN	= array( '기본코드', '옵션코드', '상품번호','옵션번호' );

		$this->crt_folder($this->EXCEL_DOWNLOAD_PATH);
		$this->crt_folder($this->EXCEL_UPLOAD_PATH);
		$this->crt_folder($this->EXCEL_LOG_PATH);
	}

	########## ↓↓↓↓↓ 엑셀 업로드 ↓↓↓↓↓ ##########

	//엑셀 바코드등록 함수
	public function upload_excel($params){
		$this->load->model('barcodemodel');

		// 파일 업로드
		$filename		= 'barcode_excel_file';
		$upload_result	= $this->excel_file_upload($filename, $params);
		
		if	(!$upload_result['status']){
			return $upload_result;
		}
		
		try {
			set_time_limit(0);
			ini_set('memory_limit', '10000M');

			$this->load->library('PHPExcel');
			$this->load->library('PHPExcel/IOFactory');
			$this->PHPExcel				= new PHPExcel();
			$this->IOFactory			= new IOFactory();
			
			foreach($upload_result['file'] as $val){				
				// 업로드 된 엑셀 형식에 맞는 Reader객체를 만든다.
				$objReader	= $this->IOFactory->createReaderForFile($val);
				// 읽기전용으로 설정
				$objReader->setReadDataOnly(true);
				// 엑셀파일을 읽는다
				$objExcel = $objReader->load($val);
				// 첫번째 시트를 선택
				$objExcel->setActiveSheetIndex(0);
				$this->workSheet	= $objExcel->getActiveSheet();
				$rowIterator		= $this->workSheet->getRowIterator();				
				foreach ($rowIterator as $row) { // 모든 행에 대해서
					$cellIterator = $row->getCellIterator();
					$cellIterator->setIterateOnlyExistingCells(false);
				}


				//등록 가능한 최대 행을 넘는지 검사
				$maxRow			= $this->workSheet->getHighestRow();
				if	($this->MAX_ROWS < ($maxRow + 3)){
					return array('status' => false, 'msg' => '최대 ' . ($this->MAX_ROWS). '개까지 등록 가능합니다.');
				}
				
				//필수 열이 들어있는지 검사
				$maxCol			= $this->workSheet->getHighestColumn();
				$maxColCnt		= $this->calculate_alphar_to_count($maxCol);
				$alpharArr		= $this->get_excel_cell_alphar($maxColCnt);
				
				$tmp_list	= $this->EXCEL_UPLOAD_COLUMN;
				foreach($alpharArr as $cell){
					$title		= $this->workSheet->getCell($cell.'2')->getValue() != '' 
								  ? $this->workSheet->getCell($cell.'2')->getValue()
								  : $this->workSheet->getCell($cell.'1')->getValue();
					foreach($tmp_list as $key=>$val){
						if($title == $val){
							unset($tmp_list[$key]);
						}
					}
				}

				if(count($tmp_list) > 0) return array('status' => false, 'msg' => '필수 열이 없습니다. 필수 열을 확인해 주십시오.');
				//데이터 업데이트				
				
				for	( $r = 3; $r <= $maxRow; $r++){
					$goods_code		= $this->workSheet->getCell('A'.$r)->getValue();  //기본코드
					$option_code	= $this->workSheet->getCell('B'.$r)->getValue();  //옵션코드
					$goods_seq		= $this->workSheet->getCell('C'.$r)->getValue();  //상품번호
					$option_seq		= $this->workSheet->getCell('D'.$r)->getValue();  //옵션번호
					$goods_name		= $this->workSheet->getCell('E'.$r)->getValue();  //상품명
					$option_name	= $this->workSheet->getCell('F'.$r)->getValue();  //옵션명

					if($goods_seq == '')	return array('status' => false, 'msg' => '상품번호 값이 없습니다. 상품번호를 확인해 주십시오.');
					if($option_seq == '')	return array('status' => false, 'msg' => '옵션번호 값이 없습니다. 옵션번호를 확인해 주십시오.');
					
					$code_param = array(
						'goods_code'	=> $goods_code,						
						'goods_seq'		=> $goods_seq,
						'option_seq'	=> $option_seq,
						'option_code'	=> explode(',', $option_code),
						'goods_name'	=> $goods_name,			
						'option_name'	=> explode(',', $option_name)
					);
					$result = $this->barcodemodel->set_barcode_data($code_param);

					//로그저장
					if	($result['code'] == 200){
						$this->save_upload_log('success', '[' . $goods_name . '/' . $goods_seq . ']의 바코드정보가 등록되었습니다.'."\r\n");
					}else{
						$this->save_upload_log('failed', '[' . $goods_name . '/' . $goods_seq . ']의 바코드정보가 등록되지 않았습니다.' . "\r\n");
					}
				}
					
			}
			$this->close_upload_log();	

			return array('status' => true, 'msg' => '처리완료 되었습니다.<br/>처리 로그를 확인해 주십시오.');

		}catch (exception $e) {
			echo $e;
			return array('status' => false, 'msg' => '엑셀파일을 읽는도중 오류가 발생하였습니다.');
		}
	}

	// 파일 업로드
	public function excel_file_upload($filename, $filedata){
		$fileinfo	= $filedata[$filename];

		//CI 다중업로드처리가 안되므로 FILES 객체에 해당 파일정보를 세팅한다.
		$_FILES[$filename]['name']		= $fileinfo['name'];
		$_FILES[$filename]['type']		= $fileinfo['type'];
		$_FILES[$filename]['tmp_name']	= $fileinfo['tmp_name'];
		$_FILES[$filename]['error']		= $fileinfo['error'];
		$_FILES[$filename]['size']		= $fileinfo['size'];    

		if	(is_uploaded_file($fileinfo['tmp_name'])){
			$fileExt				= end(explode('.', $fileinfo['name']));
			$fileName				= 'upload_barcode_excel_' . date('YmdHis') . rand(0,9999) . '_' . $i;
			$cfg['allowed_types']	= 'xls';
			$cfg['file_name']		= $fileName;
			$cfg['upload_path']		= $this->EXCEL_UPLOAD_PATH . '/';
			$cfg['overwrite']		= TRUE;
			
			$this->load->library('upload');
			$this->upload->initialize($cfg);

			if ($this->upload->do_upload($filename)) {
				$file_nm[]					= $cfg['upload_path'] . $cfg['file_name'] . '.' . $cfg['allowed_types'];
				@chmod($cfg['upload_path'] . $cfg['file_name'] . '.' . $cfg['allowed_types'], 0777);
				$this->UPLOAD_FILE_NAME[]	= str_replace(',', '', $fileinfo['name']);
				$return			= array( 'status' => true, 'file' => $file_nm );
			}else{
				$err_msg		= 'xls 파일만 가능합니다.'.$cfg['upload_path'];
				$return			= array('status' => false, 'msg' => $err_msg);
			}
		}else{
			$err_msg			= '파일을 등록해 주세요.';
			$return				= array('status' => false, 'msg' => $err_msg);
		}

		return $return;
	}


	########## ↓↓↓↓↓ 엑셀 다운로드 ↓↓↓↓↓ ##########

	//다운로드 할 데이터를 가져오는 함수
	public function getdata($params){
		$this->load->model('barcodemodel');

		//선택형 일 경우 seq 값이 있는지 검사
		if($params['mode'] == 'select' && count($params['goods_seq']) == 0){
			openDialogAlert("다운로드 할 바코드 데이터를 선택해 주세요.",400,175,'parent');
			exit;
		}
		
		//seq 값들을 나눔 (goods_seq|option_seq 형태로 넘어옴)
		foreach($params['goods_seq'] as $row){
			$tmp_token			= explode('|', $row);
			$tmp_goods_seqs[]	= $tmp_token[0];
			$tmp_option_seqs[]	= $tmp_token[1];
		}

		//데이터를 로딩하기 위한 인자값 세팅
		$params['page'] = $this->PAGE;
		$params['perpage'] = $this->MAX_ROWS;
		
		if($params['mode']=='select'){
			$params['goods_seq_list']	= $tmp_goods_seqs;
			$params['option_seq_list']	= $tmp_option_seqs;
		}
		
		//상품 데이터를 가져옴
		$listdata			= $this->barcodemodel->get_goods_list($params);
		//창고 데이터를 가져옴
		$storedata = $this->barcodemodel->getstorelist($listdata['goods_seq_list'], $listdata['option_seq_list']);
		
		//상품코드 중복 등록 방지
		$dupl_list	= array();
		$refer_key  = '';
		$refer_seq  = '';
		$code_count = 0;
		foreach($listdata['record'] as $key=>$val){
			$row = $listdata['record'][$key];
			
			foreach($storedata as $skey=>$sval){
				$tmp_row = $storedata[$row['goods_seq']][$row['option_seq']];
				$row['total_ea']				   = $tmp_row['ea'] ? $tmp_row['ea'] : 0;						//상품의 총 재고 수					
				$row['total_bad_ea']			   = $tmp_row['bad_ea'] ? $tmp_row['bad_ea'] : 0;				//상품의 총 불량재고 수					
			}			
			$listdata['record'][$key] = $row;
			
			//상품코드 중복 등록 방지
			$seq = $val['goods_seq'];			
			if($refer_seq == ''){		
				$refer_key = $key;
				$refer_seq = $seq;
				continue;
			}

			$last_key = '';
			if($refer_seq != $seq){
				$listdata['record'][$refer_key]['use_cell'] = 'Y';
				$listdata['record'][$refer_key]['rowspan'] = $code_count;
				$refer_key = $key;
				$refer_seq = $seq;
				$code_count = 0;
				$last_key = count($listdata['record'])-1 == $key ? $key : '';
			}else{						
				$code_count++;				
				$last_key = count($listdata['record'])-1 == $key ? $refer_key : '';
			}
			if($last_key !== ''){
				$listdata['record'][$last_key]['use_cell'] = 'Y';
				$listdata['record'][$last_key]['rowspan'] = $code_count;
			}
		}
		
		$this->TOTAL_ROWS	= $listdata['page']['searchcount'];
		$this->TOTAL_PAGE	= ceil($listdata['page']['totalcount']/ $listdata['perpage']);
			
		return $listdata;
	}

	// 엑셀 다운로드 함수
	public function download_excel($params){
		$this->MODE				= $params['mode'];		//선택, 검색 다운로드 여부
		$this->GOODS_SEQ_LIST	= $params['goods_seq'];	//선택 다운로드 일 경우 seq 파라미터
		$this->PAGE				= $params['excel_page'] ? $params['excel_page'] : $this->PAGE;

		$result	= array('status' => true);

		// 파일명 생성
		$excel_file_name	= 'download_barcode_excel_' . date('YmdHis') . rand(0,9999);
		if	($params['excel_file_name'])	$excel_file_name = $params['excel_file_name'];

		// 바코드 상품 기본 정보 추출
		$data = $this->getdata($params);
		if	(!is_array($data) || count($data) < 1){
			$result['status']	= false;
			$result['err_msg']	= '다운로드할 바코드 데이터를 선택해 주세요.';
			return $result;
		}

		// 압축 다운일 때
		if	($this->TOTAL_ROWS > $this->MAX_ROWS){
			
			for($i=$this->PAGE; $i<=$this->TOTAL_PAGE; $i++){
				$data = $this->getdata($params);
				$this->create_download_excel('file', $excel_file_name, $data['record']);
				$this->PAGE++;
			}

			$params['excel_page']		= $this->PAGE;
			$params['excel_zipdown']	= true;
			$params['excel_totalCount']	= $this->TOTAL_ROWS;
			$params['excel_totalPage']	= $this->TOTAL_PAGE;
			$params['excel_file_name']	= $excel_file_name;
			$params['excel_zip_file']	= $excel_file_name;
			$this->download_excel_zip_file($params);
			
		}else{
			//debug($data);
			$this->create_download_excel('down', $excel_file_name, $data['record']);
		}

		return $result;
	}

	// 엑셀 파일 생성
	public function create_download_excel($outputType, $filename, $data){
		if	($outputType == 'file'){
			// directory 생성
			if(!is_dir($this->EXCEL_DOWNLOAD_PATH)){
				@mkdir($this->EXCEL_DOWNLOAD_PATH);
				@chmod($this->EXCEL_DOWNLOAD_PATH, 0777);
			}

			// 임시 폴더에 파일로 저장 ( 전일 남은 dummy file은 cron에서 일괄 삭제 함 )
			$filename		= $filename.'_'.$this->PAGE.'.xls';
			$filepath		= $this->EXCEL_DOWNLOAD_PATH . '/'. $filename;

			$fObj			= fopen($filepath, 'w+');
			fwrite($fObj, $this->get_default_excel_header());
			
			foreach($data as $idx => $barcode){				
				fwrite($fObj, '<Row ss:Index="'.($idx+3).'" ss:Height="18">');	
				if($barcode['use_cell'] && $barcode['use_cell'] == 'Y'){
					$rowspan_attr = $barcode['rowspan'] > 0 ? 'ss:MergeDown="'.$barcode['rowspan'].'"': '';
					fwrite($fObj, '<Cell ss:StyleID="Default" '.$rowspan_attr.'><Data ss:Type="String">'.$barcode['goods_code'].'</Data></Cell>');
				}
				fwrite($fObj, '<Cell ss:StyleID="Default" ss:Index="2"><Data ss:Type="String">'.$barcode['option_code_cell']	.'</Data></Cell>');
				fwrite($fObj, '<Cell ss:StyleID="Default" ss:Index="3"><Data ss:Type="String">'.$barcode['goods_seq']		.'</Data></Cell>');
				fwrite($fObj, '<Cell ss:StyleID="Default" ss:Index="4"><Data ss:Type="String">'.$barcode['option_seq']	.'</Data></Cell>');
				fwrite($fObj, '<Cell ss:StyleID="Default" ss:Index="5"><Data ss:Type="String">'.$barcode['goods_name']	.'</Data></Cell>');
				fwrite($fObj, '<Cell ss:StyleID="Default" ss:Index="6"><Data ss:Type="String">'.$barcode['option_title']	.'</Data></Cell>');
				fwrite($fObj, '<Cell ss:StyleID="Default" ss:Index="7"><Data ss:Type="String">'.$barcode['total_ea']	.'</Data></Cell>');
				fwrite($fObj, '<Cell ss:StyleID="Default" ss:Index="8"><Data ss:Type="String">'.$barcode['total_bad_ea']	.'</Data></Cell>');
				fwrite($fObj, '</Row>');
			}
			fwrite($fObj, $this->get_default_excel_footer());

			fclose($fObj);
		}else{
			// 즉시 다운로드
			header('Content-Type: application/vnd.ms-excel');
			header("Content-Disposition: attachment;filename=".$filename.".xls");
			header('Cache-Control: max-age=0');

			echo $this->get_default_excel_header();
			foreach($data as $idx => $barcode){
				echo '<Row ss:Index="'.($idx+3).'" ss:Height="18">';
				if($barcode['use_cell'] && $barcode['use_cell'] == 'Y'){
					$rowspan_attr = $barcode['rowspan'] > 0 ? 'ss:MergeDown="'.$barcode['rowspan'].'"': '';
					echo '	<Cell ss:StyleID="Default" '.$rowspan_attr.'><Data ss:Type="String">'.$barcode['goods_code'].'</Data></Cell>';
				}
				echo '	<Cell ss:StyleID="Default" ss:Index="2"><Data ss:Type="String">'.$barcode['option_code_cell']	.'</Data></Cell>';
				echo '	<Cell ss:StyleID="Default" ss:Index="3"><Data ss:Type="String">'.$barcode['goods_seq']		.'</Data></Cell>';
				echo '	<Cell ss:StyleID="Default" ss:Index="4"><Data ss:Type="String">'.$barcode['option_seq']	.'</Data></Cell>';
				echo '	<Cell ss:StyleID="Default" ss:Index="5"><Data ss:Type="String">'.$barcode['goods_name']	.'</Data></Cell>';
				echo '	<Cell ss:StyleID="Default" ss:Index="6"><Data ss:Type="String">'.$barcode['option_title']	.'</Data></Cell>';
				echo '	<Cell ss:StyleID="Default" ss:Index="7"><Data ss:Type="String">'.$barcode['total_ea']	.'</Data></Cell>';
				echo '	<Cell ss:StyleID="Default" ss:Index="8"><Data ss:Type="String">'.$barcode['total_bad_ea']	.'</Data></Cell>';
				echo '</Row>';
			}
			echo $this->get_default_excel_footer();
		}
	}

	// xml 형태 엑셀의 기본 header
	public function get_default_excel_header(){
		$excelXmlHeader	= '<?xml version="1.0"?>
					<?mso-application progid="Excel.Sheet"?>
					<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"
						xmlns:o="urn:schemas-microsoft-com:office:office"
						xmlns:x="urn:schemas-microsoft-com:office:excel"
						xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"
						xmlns:html="http://www.w3.org/TR/REC-html40">
					<Styles>
						<Style ss:ID="Default" ss:Name="Normal">
							<Alignment ss:Horizontal="Center" ss:Vertical="Center" />
							<Font ss:Size="9" ss:Color="#000000" ss:Bold="1" />
						</Style>
						<Style ss:ID="Cols">
							<Alignment ss:Horizontal="Center" ss:Vertical="Center" />
							<Font ss:Size="9" ss:Color="#000000" ss:Bold="1" />
							<Interior ss:Color="#dadada" ss:Pattern="Solid"/>
						</Style>
						
					</Styles>
					<Worksheet ss:Name="Sheet1">
						<Table>
							<Column ss:Index="5" ss:Width="200"/>
							<Column ss:Index="6" ss:Width="200"/>';

		foreach($this->EXCEL_COLUMN as $key=>$row){
			$excelXmlHeader	.= '<Row ss:Index="'.($key+1).'" ss:Height="18">';
			
			foreach( $row as $cell => $style){
				$StyleID		= 'Cols';
				$excelXmlHeader	.= '<Cell ss:StyleID="'.$StyleID.'" '.$style.'>
										<Data ss:Type="String">'.$cell.'</Data>
									</Cell>';
			}

			$excelXmlHeader	.= '</Row>';
		}		

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
		$zippath	= $this->EXCEL_DOWNLOAD_PATH . '/' . $zipfile;
		$this->load->library('pclzip',array('p_zipname' => $zippath));


		// 압축할 파일명 배열 생성
		for	($p = 1; $p <= $params['excel_totalPage']; $p++){
			$filename	= $params['excel_zip_file'].'_'.$p.'.xls';
			$filepath	= $this->EXCEL_DOWNLOAD_PATH . '/'. $filename;
			if	(file_exists($filepath) && is_file($filepath)){
				$excel_files[]	= $filepath;
			}
		}

		// 파일 압축
		$zipFile	= $this->pclzip->create($excel_files,
											PCLZIP_OPT_REMOVE_PATH, $this->EXCEL_DOWNLOAD_PATH);

		// 파일 삭제
		for	($p = 1; $p <= $params['excel_totalPage']; $p++){
			$filename	= $params['excel_zip_file'].'_'.$p.'.xls';
			$filepath	= $this->EXCEL_DOWNLOAD_PATH . '/'. $filename;
			if	(file_exists($filepath) && is_file($filepath)){
				@unlink($filepath);
			}
		}

		if	($zipFile === 0){
			openDialogAlert('파일 압축에 실패하였습니다.', 400, 150, 'parent');
			exit;
		}else{
			$url	= str_replace(ROOTPATH, '/', $zippath);
			header("Location:".$url, true);
			exit;
		}
	}

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
		if	(preg_match('/\./', $priceStr)){
			$price	= $priceStr * 100;
			$unit	= 'percent';
		}else{
			$price	= (int) preg_replace('/[^0-9]*/', '', $priceStr);
			$unit	= str_replace($price, '', $priceStr);
			if	($unit == '원')	$unit	= $this->config_system['basic_currency'];
			else				$unit	= 'percent';
		}

		return array('price' => $price, 'unit' => $unit);
	}

	// 전체 컬럼 목록 배열 return
	public function get_cell_list(){
		return $this->m_aCellList;
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

	// 업로드 시 성공 실패 로그 저장
	public function save_upload_log($type = 'failed', $msg = ''){
		if	(!$this->SUCCESS_LOG_FILE){
			$log_file_name				= 'log_barcode_excel_' . date('YmdHis') . rand(0,9999) . '.txt';
			$this->SUCCESS_LOG_FILE		= fopen($this->EXCEL_LOG_PATH . '/success_' . $log_file_name, 'a+');
			$this->FAIL_LOG_FILE		= fopen($this->EXCEL_LOG_PATH . '/failed_' . $log_file_name, 'a+');

			$logParam['upload_type']			= 'barcode';
			$logParam['upload_date']			= date('Y-m-d H:i:s');
			$logParam['uploader_ip']			= $_SERVER['REMOTE_ADDR'];
			$logParam['upload_filename']		= implode(',', $this->UPLOAD_FILE_NAME);
			$logParam['result_success']			= 'success_' . $log_file_name;
			$logParam['result_failed']			= 'failed_' . $log_file_name;
			$this->db->insert('fm_excel_upload_log', $logParam);
		}

		if	($msg){
			if	($type == 'success'){
				fwrite($this->SUCCESS_LOG_FILE, $msg);
			}else{
				fwrite($this->FAIL_LOG_FILE, $msg);
			}
		}
	}

	// 로그파일 닫기
	public function close_upload_log(){
		fclose($this->SUCCESS_LOG_FILE);
		fclose($this->FAIL_LOG_FILE);
	}

	// 엑셀 업로드 로그
	public function get_excel_upload_log($sc){

		// 업로드 구분 
		$addWhere	.= " and upload_type = 'barcode' ";
		$addOrder	.= " ORDER BY upload_date desc ";
		
		// 추출 수량
		if		(isset($sc['elimit'])){
			if	(!$sc['slimit'])	$sc['slimit']	= 0;
			$addLimit	= " LIMIT " . $sc['slimit'] . ", " . $sc['elimit'] . " ";
		}

		$sql		= "select * from fm_excel_upload_log where upload_seq > 0 "
					. $addWhere . $addOrder . $addLimit;
		$query		= $this->db->query($sql);
		$result		= $query->result_array();
		
		foreach($result as $key=>$val){
			$tmp_logname = $val;
			$tmp_logname['upload_filename'] = explode(',', $tmp_logname['upload_filename']);
			$tmp_logname['upload_filename'] = implode('<br/>', $tmp_logname['upload_filename']);
			$val = $tmp_logname;
			$result[$key] = $val;
		}		

		return $result;
	}

	// log파일 다운로드
	public function download_log_file($filename){
		if	(!$filename){
			return array('status' => false, 'err_msg' => '선택된 log파일이 없습니다.');
		}

		$filepath	= $this->EXCEL_LOG_PATH . '/' . $filename;
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
}
?>
