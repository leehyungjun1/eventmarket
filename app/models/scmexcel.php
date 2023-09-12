<?php
require_once(APPPATH.'/libraries/Spout/Autoloader/autoload.php'); //excel library

use Box\Spout\Reader\ReaderFactory;
use Box\Spout\Writer\WriterFactory;
use Box\Spout\Common\Type;
use Box\Spout\Writer\Style\StyleBuilder;
use Box\Spout\Writer\Style\Color;
use Box\Spout\Writer\Style\Border;
use Box\Spout\Writer\Style\BorderBuilder;

class scmexcel extends CI_Model {

	var $PHPExcel					= '';
	var $IOFactory					= '';
	var $m_sForType					= '';
	var $m_sExcelDownloadFilePath	= '';
	var $m_sExcelUploadFilePath		= '';
	var $m_sLogFilePath				= '';
	var $m_sProcType				= 'D';

	var $m_nExcelPage				= 0;
	var $m_nTotalPage				= 0;
	var $m_nTotalCount				= 0;
	var $m_nTotalStock				= 0;
	var $m_nMaxRow					= 2000;

	var $m_bZipDown					= false;

	var $m_aCellList				= array();
	var $m_aWarehouse				= array();
	var $m_aCellCode				= array();
	var $m_aOptionInfo				= array();
	
	var $m_nExcelTotal				= 0;


	public function __construct(){
		$this->load->model('scmmodel');

		$this->m_sExcelDownloadFilePath	= ROOTPATH . 'data/excel_tmp';
		$this->m_sExcelUploadFilePath	= ROOTPATH . 'data/tmp';
		$this->m_sLogFilePath			= ROOTPATH . 'data/excel_tmp';

		$this->crt_folder($this->m_sExcelDownloadFilePath);
		$this->crt_folder($this->m_sExcelUploadFilePath);
		$this->crt_folder($this->m_sLogFilePath);
	}


	########## ↓↓↓↓↓ 엑셀 다운로드 ↓↓↓↓↓ ##########

	// 엑셀 다운로드 함수
	public function download_excel($params){
		set_time_limit(0);
		ini_set("memory_limit",-1);
		
		$result	= array('status' => true);

		// 엑셀 파일들 압축 후 다운로드
		if	($params['excel_zip_file']){
			$this->download_excel_zip_file($params);
		}

		// 파일명 생성
		$excel_file_name = 'download_' . $this->m_sForType . '_excel_' . date('YmdHis') . rand(0,9999);
		if	($params['excel_file_name'])	$excel_file_name	= $params['excel_file_name'];

		$params['is_excel']	= true;
		
		// 상품 기본 정보 추출
		$dataList	= $this->get_excel_data($params);
		
		if	(!is_array($dataList) || count($dataList) < 1){
			$result['status']	= false;
			$result['err_msg']	= '다운로드할 목록이 없습니다.';
			return $result;
		}
		
		// 압축 다운일 때
		if	($this->m_nExcelTotal > $this->m_nMaxRow){
			$this->m_nExcelPage = 1;
			
			$zipfile = $excel_file_name . ".zip";
			$zippath = $this->m_sExcelDownloadFilePath . '/';
			
			$files = array();
			
			if ($this->m_sForType == 'default_stock'){
				$fileName = $this->create_download_excel_new('file', $excel_file_name, $dataList);
			} else {
				$fileName = $this->create_download_excel('file', $excel_file_name, $dataList);
			}
			$files[]			= $zippath.$fileName;
			$loopCnt			= ceil($this->m_nExcelTotal/$this->m_nMaxRow);
			$params['total']	= $this->m_nExcelTotal;
			
			for($i=2; $i<=$loopCnt; $i++){
				$params['excel_page'] = ($i-1) * $this->m_nMaxRow;
				
				$dataList	= $this->get_excel_data($params);
				
				$this->m_nExcelPage = $i;
				
				if ($this->m_sForType == 'default_stock'){
					$fileName = $this->create_download_excel_new('file', $excel_file_name, $dataList);
				} else {
					$fileName = $this->create_download_excel('file', $excel_file_name, $dataList);
				}
				$files[] = $zippath.$fileName;
			}

			
			$this->load->helper('download');
			$this->load->library('pclzip', array('p_zipname' => $zippath . $zipfile));
			$pclZip = $this->pclzip->create($files, PCLZIP_OPT_REMOVE_PATH, $zippath);
			
			if($pclZip !== 0){
				$url = $zippath . $zipfile;
				
				header('Content-Type: application/x-octetstream');
				header('Content-Length: '.filesize($url));
				header('Content-Disposition: attachment; filename='.$zipfile);
				header('Content-Transfer-Encoding: binary');
				
				$fp = fopen($url, "r");
				fpassthru($fp);
				fclose($fp);
			}
		}else{
			$this->create_download_excel('down', $excel_file_name, $dataList);
		}

		return $result;
	}
	
	public function create_download_excel_new($outputType, $filename, $dataList)
	{
		$filename		= $filename.'_'.$this->m_nExcelPage.'.xlsx';
		$filepath		= $this->m_sExcelDownloadFilePath . '/'. $filename;
		
		$alNum = array(
			'0' => 'A',
			'1' => 'B',
			'2' => 'C',
			'3' => 'D',
			'4' => 'E',
			'5' => 'F',
			'6' => 'G',
			'7' => 'H',
			'8' => 'I',
			'9' => 'J',
			'10' => 'K',
			'11' => 'L',
			'12' => 'M',
			'13' => 'N',
			'14' => 'O',
			'15' => 'P',
			'16' => 'Q',
			'17' => 'R',
			'18' => 'S',
			'19' => 'T',
			'20' => 'U',
			'21' => 'V',
			'22' => 'W',
			'23' => 'X',
			'24' => 'Y',
			'25' => 'Z',
		);

		$columnTitles = array('A1' => '마스터 상품정보');
		$mergeCells['A1'] = 'A1:G1';
		
		$coulumnContents = array();
		$columnTitles2 = array();
		foreach($this->m_aCellList['master'] as $k => $v){
			$coulumnContents[] = $k;
			$columnTitles2[] = $v;
		}
		
		$num = '7';
		foreach($this->m_aWarehouse as $k => $v){
			$sAl = $alNum[$num%26];
			$nAl = '';
			$j = 0;
			for($i = $num; $i <= $num+2; $i++){
				$nAl = $alNum[$i%26];
				
				if($i > 25 && $j == 2){
					$numNew = floor($i/26) - 1;
					if($i-2 > 25){
						$sAl = $alNum[$numNew].$sAl;
					}
					$nAl = $alNum[$numNew].$nAl;
				}
				$j++;
			}
			
			$mergeCells[$sAl.'1'] = $sAl.'1:'.$nAl.'1';
			$num = $i++;
			
			$columnTitles[$sAl.'1'] = $v['wh_name'];
			
			foreach(array('stock' => '수량', 'supply_price' => '단가', 'total_price' => '금액') as $kk => $vv){
				$columnTitles2[] = $vv;
			}
			
			if($num > 670){ //too many wh
				openDialogAlert('창고가 너무 많아 엑셀 생성에 실패 했습니다. 관리자에게 문의하세요', 400, 150, '');
				exit;
			}
		}
		
		$mergeCellsContent = array();
		$num = 3;
		
		$excelContents = array();
		foreach($dataList as $k => $v){
			$contents = array();
			foreach($coulumnContents as $kk => $vv){
				$contents[] = $v[$vv];
				$mergeCellsContent[$alNum[$kk].$num] = $alNum[$kk].$num.':'.$alNum[$kk].($num+1);
			}
			
			foreach($v['wh'] as $kk => $vv){
				$contents[] = $vv['stock'];
				$contents[] = $vv['supply_price'];
				$contents[] = $vv['total_price'];
			}
			$excelContents[] = $contents;
			$num++;
			
			$contents = array();
			foreach($coulumnContents as $kk => $vv){
				$contents[] = '';
			}
			
			foreach($v['wh'] as $kk => $vv){
				
				$contents[] = $vv['location_code'];
				$contents[] = '';
				$contents[] = '';
				
				foreach(array_slice($mergeCells, 1) as $mk => $mv){
					$alChar = substr($mk, 0, -1);
					$mergeCellsContent[$alChar.$num] = str_replace(1, $num, $mv);
				}
			}
			
			$excelContents[] = $contents;
			$num++;
		}
		
		$mergeCells = array_merge($mergeCells, $mergeCellsContent);
		
		$writer = WriterFactory::create(Type::XLSX); // for XLSX files
		//$writer->colWidth = $columnWidths;
		$writer->mergeCells = $mergeCells;
		
		$this->set_style();
		$writer->openToFile($filepath);
		$writer->addRowWithStyle($columnTitles, $this->style_title);
		$writer->addRowWithStyle($columnTitles2, $this->style_title);
		
		foreach($excelContents as $v){
			$writer->addRowWithStyle($v, $this->style_contents);
		}
		
		$writer->close();
		return $filename;
	}

	function set_style(){
		$this->border = (new BorderBuilder())
		->setBorderLeft(Color::BLACK, Border::WIDTH_THIN, Border::STYLE_SOLID)
		->setBorderRight(Color::BLACK, Border::WIDTH_THIN, Border::STYLE_SOLID)
		->setBorderBottom(Color::BLACK, Border::WIDTH_THIN, Border::STYLE_SOLID)
		->setBorderTop(Color::BLACK, Border::WIDTH_THIN, Border::STYLE_SOLID)
		->build();
		
		$this->style_title = (new StyleBuilder())
		->setBorder($this->border)
		->setFontBold()
		->setFontSize(11)
		->setFontColor(Color::BLACK)
		->setShouldWrapText(false)
		->setBackgroundColor(Color::rgb(221, 221, 221))
		->build();
		
		$this->style_contents = (new StyleBuilder())
		->setBorder($this->border)
		->setFontSize(11)
		->setFontColor(Color::BLACK)
		->setShouldWrapText()
		->build();
		
		$this->style_contents_yellow = (new StyleBuilder())
		->setBorder($this->border)
		->setFontSize(11)
		->setFontColor(Color::BLACK)
		->setShouldWrapText()
		->setBackgroundColor(Color::rgb(255, 255, 204))
		->build();
	}
	
	// 엑셀 파일 생성
	public function create_download_excel($outputType, $filename, $dataList)
	{
		if	($outputType == 'file'){
			// 임시 폴더에 파일로 저장 ( 전일 남은 dummy file은 cron에서 일괄 삭제 함 )
			$filename		= $filename.'_'.$this->m_nExcelPage.'.xls';
			$filepath		= $this->m_sExcelDownloadFilePath . '/'. $filename;

			$fObj			= fopen($filepath, 'w+');
			fwrite($fObj, $this->get_default_excel_header());
			foreach($dataList as $g => $data){
				if	($this->m_sForType == 'trader'){
					$managerData		= $this->get_manager_data($data);
					if	($managerData)	$data	= array_merge($data, $managerData);
				}
				fwrite($fObj, '<Row ss:Index="'.($g+3).'" ss:Height="33">');
				$cellIdx	= 1;
				foreach($this->m_aCellList as $fld	=> $title){
					if	($this->m_sForType == 'default_stock'){
						if	($fld == 'warehouse'){
							$cellPosition	= $cellIdx;
							$subList		= $title;
							
							$tmpIdx = 'ss:Index="'.$cellIdx.'"';
							foreach($this->m_aWarehouse as $wh_seq => $wh){
								foreach($title as $subFld => $subTitle){
									$tmpArr	= $this->get_cell_style($subFld);
									$styleID	= $tmpArr['style'];
									$dataType	= $tmpArr['type'];
									$msg		= $this->download_except_replace($subFld, $data['wh'][$wh_seq]);
									
									if	(preg_match('/\</', $msg)){
										fwrite($fObj, '<Cell '.$tmpIdx.'  ss:StyleID="s62"><Data ss:Type="String"><![CDATA['.$msg.']]></Data></Cell>');
									}else{
										fwrite($fObj, '<Cell '.$tmpIdx.' ss:StyleID="' . $styleID . '"><Data ss:Type="' . $dataType . '">'.$msg.'</Data></Cell>');
									}
									$tmpIdx = '';
								}
							}
						}else if ($fld == 'master'){
							foreach($title as $subFld => $subTitle){
								$tmpArr	= $this->get_cell_style($subFld);
								$msg	= $this->download_except_replace($subFld, $data);
								
								$styleID	= $tmpArr['style'];
								$dataType	= $tmpArr['type'];
								if	(preg_match('/\</', $msg)){
									fwrite($fObj, '<Cell ss:Index="'.$cellIdx.'" ss:MergeDown="1" ss:StyleID="s62"><Data ss:Type="String"><![CDATA['.$msg.']]></Data></Cell>');
								}else{
									fwrite($fObj, '<Cell ss:Index="'.$cellIdx.'" ss:MergeDown="1" ss:StyleID="'. $styleID .'"><Data ss:Type="'. $dataType .'">'.$msg.'</Data></Cell>');
								}
								$cellIdx++;
							}
						}else{
							$msg = $this->download_except_replace($fld, $data);
							
							if	(preg_match('/\</', $msg)){
								fwrite($fObj, '<Cell ss:MergeDown="1" ss:StyleID="s62"><Data ss:Type="String"><![CDATA['.$msg.']]></Data></Cell>');
							}else{
								fwrite($fObj, '<Cell ss:MergeDown="1" ss:StyleID="' . $styleID . '"><Data ss:Type="' . $dataType . '">'.$msg.'</Data></Cell>');
							}
						}
					}else if ($this->m_sForType == 'default_supply'){
						if	($fld == 'master' || $fld == 'info'){
							foreach($title as $subFld => $subTitle){
								$tmpArr	= $this->get_cell_style($subFld);
								$msg	= $this->download_except_replace($subFld, $data);
								
								$styleID	= $tmpArr['style'];
								$dataType	= $tmpArr['type'];
								if	(preg_match('/\</', $msg)){
									fwrite($fObj, '<Cell ss:Index="'.$cellIdx.'" ss:StyleID="s62"><Data ss:Type="String"><![CDATA['.$msg.']]></Data></Cell>');
								}else{
									fwrite($fObj, '<Cell ss:Index="'.$cellIdx.'" ss:StyleID="'. $styleID .'"><Data ss:Type="'. $dataType .'">'.$msg.'</Data></Cell>');
								}
								$cellIdx++;
							}
						}else{
							$msg	= $this->download_except_replace($fld, $data);
							if	(preg_match('/\</', $msg)){
								fwrite($fObj, '<Cell ss:MergeDown="1" ss:StyleID="s62"><Data ss:Type="String"><![CDATA['.$msg.']]></Data></Cell>');
							}else{
								fwrite($fObj, '<Cell ss:MergeDown="1" ss:StyleID="' . $styleID . '"><Data ss:Type="' . $dataType . '">'.$msg.'</Data></Cell>');
							}
						}
					}else if ($this->m_sForType == 'default_shop'){
						if	($fld == 'master'){
							foreach($title as $subFld => $subTitle){
								$tmpArr	= $this->get_cell_style($subFld);
								$msg	= $this->download_except_replace($subFld, $data);
								
								$styleID	= $tmpArr['style'];
								$dataType	= $tmpArr['type'];
								if	(preg_match('/\</', $msg)){
									fwrite($fObj, '<Cell ss:Index="'.$cellIdx.'" ss:StyleID="s62"><Data ss:Type="String"><![CDATA['.$msg.']]></Data></Cell>');
								}else{
									fwrite($fObj, '<Cell ss:Index="'.$cellIdx.'" ss:StyleID="'. $styleID .'"><Data ss:Type="'. $dataType .'">'.$msg.'</Data></Cell>');
								}
								$cellIdx++;
							}
						}else if	($fld == 'store'){
							$cellPosition	= $cellIdx;
							$subList		= $title;
							
							$tmpIdx = 'ss:Index="'.$cellIdx.'"';
							foreach($this->m_aStore as $wh_seq => $wh){
								foreach($title as $subFld => $subTitle){									
									$tmpArr	= $this->get_cell_style($subFld);
									$styleID	= $tmpArr['style'];
									$dataType	= $tmpArr['type'];
									$msg		= $this->download_except_replace($subFld, $data);
									if	(preg_match('/\</', $msg)){
										fwrite($fObj, '<Cell '.$tmpIdx.' ss:StyleID="s62"><Data ss:Type="String"><![CDATA['.$msg.']]></Data></Cell>');
									}else{
										fwrite($fObj, '<Cell '.$tmpIdx.' ss:StyleID="' . $styleID . '"><Data ss:Type="' . $dataType . '">'.$msg.'</Data></Cell>');
									}
									$tmpIdx = '';
								}
							}
						}else{
							$msg	= $this->download_except_replace($fld, $data);
							if	(preg_match('/\</', $msg)){
								fwrite($fObj, '<Cell ss:MergeDown="1" ss:StyleID="s62"><Data ss:Type="String"><![CDATA['.$msg.']]></Data></Cell>');
							}else{
								fwrite($fObj, '<Cell ss:MergeDown="1" ss:StyleID="' . $styleID . '"><Data ss:Type="' . $dataType . '">'.$msg.'</Data></Cell>');
							}
						}
					}else{
						$msg	= $this->download_except_replace($fld, $data[$fld]);
						if	(preg_match('/\</', $msg)){
							fwrite($fObj, '<Cell ss:StyleID="s62"><Data ss:Type="String"><![CDATA['.$msg.']]></Data></Cell>');
						}else{
							fwrite($fObj, '<Cell ss:StyleID="s62"><Data ss:Type="String">'.$msg.'</Data></Cell>');
						}
					}
				}
				fwrite($fObj, '</Row>');
				
				// 로케이션 정보 노출 추가
				if	($this->m_sForType == 'default_stock'){
					$ssIndex = ' ss:Index="' . $cellPosition . '"';
					fwrite($fObj, '<Row ss:Height="17.25">');
					foreach($this->m_aWarehouse as $wh_seq => $wh){
						$tmpArr	= $this->get_cell_style('');
						$styleID	= $tmpArr['style'];
						$dataType	= $tmpArr['type'];
						$msg		= $this->download_except_replace('location_code', $data['wh'][$wh_seq]);
						if	(preg_match('/\</', $msg)){
							fwrite($fObj, '<Cell' . $ssIndex . ' ss:MergeAcross="2" ss:StyleID="s62"><Data ss:Type="String"><![CDATA['.$msg.']]></Data></Cell>');
						}else{
							fwrite($fObj, '<Cell' . $ssIndex . ' ss:MergeAcross="2" ss:StyleID="' . $styleID . '"><Data ss:Type="' . $dataType . '">'.$msg.'</Data></Cell>');
						}
						$ssIndex	= '';
					}
					
					fwrite($fObj, '</Row>');
				}
			}
			fwrite($fObj, $this->get_default_excel_footer());

			fclose($fObj);
			
			return $filename;
		}else{
			// 즉시 다운로드
			header('Content-Type: application/vnd.ms-excel');
			header("Content-Disposition: attachment;filename=".$filename.".xls");
			header('Cache-Control: max-age=0');

			echo $this->get_default_excel_header();			
			foreach($dataList as $g => $data){
				if	($this->m_sForType == 'trader'){
					$managerData		= $this->get_manager_data($data);
					if	($managerData)	$data	= array_merge($data, $managerData);
				}

				echo '<Row ss:Height="17.25">';
				$cellIdx	= 1;
				foreach($this->m_aCellList as $fld	=> $title){
					$tmpArr	= $this->get_cell_style($fld);
					$styleID	= $tmpArr['style'];
					$dataType	= $tmpArr['type'];

					if	($this->m_sForType == 'default_stock'){
						if	($fld == 'warehouse'){
							$cellPosition	= $cellIdx;
							$subList		= $title;
							
							$tmpIdx = 'ss:Index="'.$cellIdx.'"';
							foreach($this->m_aWarehouse as $wh_seq => $wh){
								foreach($title as $subFld => $subTitle){									
									$tmpArr	= $this->get_cell_style($subFld);
									$styleID	= $tmpArr['style'];
									$dataType	= $tmpArr['type'];
									$msg		= $this->download_except_replace($subFld, $data['wh'][$wh_seq]);
									if	(preg_match('/\</', $msg)){
										echo '<Cell '.$tmpIdx.' ss:StyleID="s62"><Data ss:Type="String"><![CDATA['.$msg.']]></Data></Cell>';
									}else{
										echo '<Cell '.$tmpIdx.' ss:StyleID="' . $styleID . '"><Data ss:Type="' . $dataType . '">'.$msg.'</Data></Cell>';
									}
									$tmpIdx = '';
								}
							}
						}else if	($fld == 'master'){
							foreach($title as $subFld => $subTitle){
								$tmpArr	= $this->get_cell_style($subFld);
								$msg	= $this->download_except_replace($subFld, $data);
								
								$dataType	= $tmpArr['type'];
								if	(preg_match('/\</', $msg)){
									echo '<Cell ss:Index="'.$cellIdx.'" ss:MergeDown="1" ss:StyleID="s62"><Data ss:Type="String"><![CDATA['.$msg.']]></Data></Cell>';
								}else{
									echo '<Cell ss:Index="'.$cellIdx.'" ss:MergeDown="1" ss:StyleID="s61"><Data ss:Type="'. $dataType .'">'.$msg.'</Data></Cell>';
								}
								$cellIdx++;
							}
						}else{
							$msg	= $this->download_except_replace($fld, $data);
							if	(preg_match('/\</', $msg)){
								echo '<Cell ss:MergeDown="1" ss:StyleID="s62"><Data ss:Type="String"><![CDATA['.$msg.']]></Data></Cell>';
							}else{
								echo '<Cell ss:MergeDown="1" ss:StyleID="' . $styleID . '"><Data ss:Type="' . $dataType . '">'.$msg.'</Data></Cell>';
							}
						}
					}else if ($this->m_sForType == 'default_supply'){
						if	( $fld == 'info'){
							foreach($title as $subFld => $subTitle){
								
								$tmpArr	= $this->get_cell_style($subFld);
								$msg	= $this->download_except_replace($subFld, $data);
								
								$styleID	= $tmpArr['style'];
								$dataType	= $tmpArr['type'];
								if	(preg_match('/\</', $msg)){
									echo '<Cell ss:Index="'.$cellIdx.'" ss:StyleID="s62"><Data ss:Type="String"><![CDATA['.$msg.']]></Data></Cell>';
								}else{
									echo '<Cell ss:Index="'.$cellIdx.'" ss:StyleID="'. $styleID .'"><Data ss:Type="'. $dataType .'">'.$msg.'</Data></Cell>';
								}
								$cellIdx++;
							}
						}else if($fld == 'master'){
							foreach($title as $subFld => $subTitle){
								$tmpArr	= $this->get_cell_style($subFld);
								$msg	= $this->download_except_replace($subFld, $data);
								
								$dataType	= $tmpArr['type'];
								if	(preg_match('/\</', $msg)){
									echo '<Cell ss:Index="'.$cellIdx.'" ss:StyleID="s62"><Data ss:Type="String"><![CDATA['.$msg.']]></Data></Cell>';	
								}else{
									echo '<Cell ss:Index="'.$cellIdx.'" ss:StyleID="s61"><Data ss:Type="'. $dataType .'">'.$msg.'</Data></Cell>';
								}
								$cellIdx++;
							}
						}else{
							$msg	= $this->download_except_replace($fld, $data);
							if	(preg_match('/\</', $msg)){
								echo '<Cell ss:MergeDown="1" ss:StyleID="s62"><Data ss:Type="String"><![CDATA['.$msg.']]></Data></Cell>';
							}else{
								echo '<Cell ss:MergeDown="1" ss:StyleID="' . $styleID . '"><Data ss:Type="' . $dataType . '">'.$msg.'</Data></Cell>';
							}
						}
					}else if ($this->m_sForType == 'default_shop'){
						if	($fld == 'master'){
							foreach($title as $subFld => $subTitle){
								$tmpArr	= $this->get_cell_style($subFld);
								$msg	= $this->download_except_replace($subFld, $data);
								
								$dataType	= $tmpArr['type'];
								if	(preg_match('/\</', $msg)){
									echo '<Cell ss:Index="'.$cellIdx.'" ss:StyleID="s62"><Data ss:Type="String"><![CDATA['.$msg.']]></Data></Cell>';
								}else{
									echo '<Cell ss:Index="'.$cellIdx.'" ss:StyleID="s61"><Data ss:Type="'. $dataType .'">'.$msg.'</Data></Cell>';
								}
								$cellIdx++;
							}
						}else if	($fld == 'store'){
							$cellPosition	= $cellIdx;
							$subList		= $title;
							
							$tmpIdx = 'ss:Index="'.$cellIdx.'"';
							foreach($this->m_aStore as $wh_seq => $wh){
								foreach($title as $subFld => $subTitle){									
									$tmpArr	= $this->get_cell_style($subFld);
									$styleID	= $tmpArr['style'];
									$dataType	= $tmpArr['type'];
									$msg		= $this->download_except_replace($subFld, $data);
									if	(preg_match('/\</', $msg)){
										echo '<Cell '.$tmpIdx.' ss:StyleID="s62"><Data ss:Type="String"><![CDATA['.$msg.']]></Data></Cell>';
									}else{
										echo '<Cell '.$tmpIdx.' ss:StyleID="' . $styleID . '"><Data ss:Type="' . $dataType . '">'.$msg.'</Data></Cell>';
									}
									$tmpIdx = '';
								}
							}
						}else{
							$msg	= $this->download_except_replace($fld, $data);
							if	(preg_match('/\</', $msg)){
								echo '<Cell ss:MergeDown="1" ss:StyleID="s62"><Data ss:Type="String"><![CDATA['.$msg.']]></Data></Cell>';
							}else{
								echo '<Cell ss:MergeDown="1" ss:StyleID="' . $styleID . '"><Data ss:Type="' . $dataType . '">'.$msg.'</Data></Cell>';
							}
						}
					}else{
						$msg	= $this->download_except_replace($fld, $data);
						if	(preg_match('/\</', $msg)){
							echo '<Cell ss:StyleID="s62"><Data ss:Type="String"><![CDATA['.$msg.']]></Data></Cell>';
						}else{
							echo '<Cell ss:StyleID="' . $styleID . '"><Data ss:Type="' . $dataType . '">'.$msg.'</Data></Cell>';
						}
					}
				}
				echo '</Row>';
				// 로케이션 정보 노출 추가
				if	($this->m_sForType == 'default_stock'){
					$ssIndex	= ' ss:Index="' . $cellPosition . '"';
					echo '<Row ss:Height="17.25">';
					foreach($this->m_aWarehouse as $wh_seq => $wh){
						$tmpArr	= $this->get_cell_style('');
						$styleID	= $tmpArr['style'];
						$dataType	= $tmpArr['type'];
						$msg		= $this->download_except_replace('location_code', $data['wh'][$wh_seq]);
						if	(preg_match('/\</', $msg)){
							echo '<Cell' . $ssIndex . ' ss:MergeAcross="2" ss:StyleID="s62"><Data ss:Type="String"><![CDATA['.$msg.']]></Data></Cell>';
						}else{
							echo '<Cell' . $ssIndex . ' ss:MergeAcross="2" ss:StyleID="' . $styleID . '"><Data ss:Type="' . $dataType . '">'.$msg.'</Data></Cell>';
						}
						$ssIndex	= '';
					}
					echo '</Row>';
				}

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
							<Alignment ss:Vertical="Center"/>
							<Borders/>
							<Font ss:FontName="맑은 고딕" x:CharSet="129" x:Family="Modern" ss:Size="11" ss:Color="#000000"/>
							<Interior/>
							<NumberFormat/>
							<Protection/>
						</Style>
						<Style ss:ID="s60">
							<Alignment ss:Horizontal="Center" ss:Vertical="Center" ss:WrapText="1"/>
						   <Borders>
							<Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#808080"/>
							<Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#808080"/>
							<Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#808080"/>
							<Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#808080"/>
						   </Borders>
						</Style>
						<Style ss:ID="s61">
							<Alignment ss:Horizontal="Left" ss:Vertical="Center" ss:WrapText="1"/>
						   <Borders>
							<Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#808080"/>
							<Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#808080"/>
							<Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#808080"/>
							<Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#808080"/>
						   </Borders>
						</Style>
						<Style ss:ID="s62">
							<Alignment ss:Horizontal="Right" ss:Vertical="Center" ss:WrapText="1"/>
						   <Borders>
							<Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#808080"/>
							<Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#808080"/>
							<Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#808080"/>
							<Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#808080"/>
						   </Borders>
						</Style>
						<Style ss:ID="s63">
							<Alignment ss:Horizontal="Right" ss:Vertical="Center" ss:WrapText="1"/>
						   <Borders>
							<Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#808080"/>
							<Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#808080"/>
							<Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#808080"/>
							<Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#808080"/>
						   </Borders>
						</Style>
						<Style ss:ID="s64">
							<Alignment ss:Horizontal="Center" ss:Vertical="Center" />
							<Interior ss:Color="#dfeaff" ss:Pattern="Solid"/>
							<Font ss:Size="11" ss:Color="#000000" ss:Bold="1" />
						   <Borders>
							<Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#808080"/>
							<Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#808080"/>
							<Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#808080"/>
							<Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#808080"/>
						   </Borders>
						</Style>
					</Styles>
					<Worksheet ss:Name="Sheet1">
						<Table>';

		$excelXmlHeader	.= '<Row ss:Height="17.25">';
		foreach( $this->m_aCellList as $fld => $title){
			if	($this->m_sForType == 'default_stock'){
				if	($fld == 'warehouse'){
					$subTitle	= $title;
					foreach($this->m_aWarehouse as $wh_seq => $data){
						$excelXmlHeader	.= '<Cell ss:MergeAcross="' . (count($title) - 1) . '" ss:StyleID="s64"><Data ss:Type="String">' . $data['wh_name'] . '</Data></Cell>';
					}
				}else if	($fld == 'master'){
					$subTitle	= $title;
					$excelXmlHeader	.= '<Cell ss:MergeAcross="' . (count($title) - 1) . '" ss:StyleID="s64"><Data ss:Type="String">마스터 상품정보</Data></Cell>';
				}else{
					$excelXmlHeader	.= '<Cell ss:MergeDown="1" ss:StyleID="s64"><Data ss:Type="String">' . $title . '</Data></Cell>';
				}
			}else if($this->m_sForType == 'default_supply'){
				if	($fld == 'master'){
					$subTitle	= $title;
					$excelXmlHeader	.= '<Cell ss:MergeAcross="' . (count($title) - 1) . '" ss:StyleID="s64"><Data ss:Type="String">마스터 상품정보</Data></Cell>';
				}else if ($fld == 'info'){
					$subTitle	= $title;
					$excelXmlHeader	.= '<Cell ss:MergeAcross="' . (count($title) - 1) . '" ss:StyleID="s64"><Data ss:Type="String">자동발주 정보</Data></Cell>';
				}else{
					$excelXmlHeader	.= '<Cell ss:MergeDown="1" ss:StyleID="s64"><Data ss:Type="String">' . $title . '</Data></Cell>';
				}
			}else if($this->m_sForType == 'default_shop'){
				if	($fld == 'master'){
					$subTitle	= $title;
					$excelXmlHeader	.= '<Cell ss:MergeAcross="' . (count($title) - 1) . '" ss:StyleID="s64"><Data ss:Type="String">마스터 상품정보</Data></Cell>';
				}else if ($fld == 'store'){
					$subTitle	= $title;
					foreach($this->m_aStore as $st_seq => $data){
						$excelXmlHeader	.= '<Cell ss:MergeAcross="' . (count($title) - 1) . '" ss:StyleID="s64"><Data ss:Type="String">' . $data['admin_env_name'] . '='.$data['admin_env_seq'].'</Data></Cell>';
					}
				}else{
					$excelXmlHeader	.= '<Cell ss:MergeDown="1" ss:StyleID="s64"><Data ss:Type="String">' . $title . '</Data></Cell>';
				}
			}else{
				$excelXmlHeader	.= '<Cell ss:StyleID="s64"><Data ss:Type="String">' . $title . '</Data></Cell>';
			}
		}
		$excelXmlHeader	.= '</Row>';

		// 상품재고 정보 추가 타이틀 처리
		if	($this->m_sForType == 'default_stock'){
			$excelXmlHeader	.= '<Row ss:Height="17.25">';
			$idx_cnt		= 1;
			foreach($this->m_aCellList['master'] as $m_key => $m_data){
				$excelXmlHeader	.= '<Cell ss:Index="'.$idx_cnt.'" ss:StyleID="s64"><Data ss:Type="String">' . $m_data . '</Data></Cell>';
				$idx_cnt++;	
			}
			$firstIndex		= ' ss:Index="'.$idx_cnt.'"';
			foreach($this->m_aWarehouse as $wh_seq => $data){
				foreach($subTitle as $fld => $title){
					$excelXmlHeader	.= '<Cell ' . $firstIndex . ' ss:StyleID="s64"><Data ss:Type="String">' . $title . '</Data></Cell>';
					$firstIndex		= '';
				}
			}
			$excelXmlHeader	.= '</Row>';
		}else if	($this->m_sForType == 'default_supply'){
			$excelXmlHeader	.= '<Row ss:Height="17.25">';
			$idx_cnt		= 1;
			foreach($this->m_aCellList['master'] as $m_key => $m_data){
				$excelXmlHeader	.= '<Cell ss:Index="'.$idx_cnt.'" ss:StyleID="s64"><Data ss:Type="String">' . $m_data . '</Data></Cell>';
				$idx_cnt++;	
			}
			foreach($this->m_aCellList['info'] as $i_key => $i_data){
				$excelXmlHeader	.= '<Cell ss:Index="'.$idx_cnt.'" ss:StyleID="s64"><Data ss:Type="String">' . $i_data . '</Data></Cell>';
				$idx_cnt++;	
			}
			$excelXmlHeader	.= '</Row>';
		}else if	($this->m_sForType == 'default_shop'){
			$excelXmlHeader	.= '<Row ss:Height="17.25">';
			$idx_cnt		= 1;
			foreach($this->m_aCellList['master'] as $m_key => $m_data){
				$excelXmlHeader	.= '<Cell ss:Index="'.$idx_cnt.'" ss:StyleID="s64"><Data ss:Type="String">' . $m_data . '</Data></Cell>';
				$idx_cnt++;	
			}
			foreach($this->m_aStore as $st_seq => $data){
				foreach($subTitle as $fld => $title){
					$excelXmlHeader	.= '<Cell ss:Index="'.$idx_cnt.'" ss:StyleID="s64"><Data ss:Type="String">' . $title . '</Data></Cell>';
					$idx_cnt++;	
				}
				
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

	// 정보 추출 ( 최대 row수를 넘어서면 페이징으로 전환 )
	public function get_excel_data($params, $page = 0){
		$page				= ($params['excel_page'] > 0) ? $params['excel_page'] : '1';
		$params['page']		= $page;
		$params['perpage']	= $this->m_nMaxRow;
		$params['wh_seq']	= $params['sc_stock_warehouse'] ? $params['sc_stock_warehouse'] : null;

		switch($this->m_sForType){
			case 'trader':
				$cfg_scm					= $this->scmmodel->scm_cfg;
				$data						= $this->scmmodel->get_trader($params);
				if	($data['record']) foreach($data['record'] as $k => $opt){
					$opt['default_date']	= $cfg_scm['set_account_date'];
					$data['record'][$k]		= $opt;
				}
			break;
			case 'default_stock':
				if	(true){
					$data = $this->scmmodel->get_location_goods_for_option($params);
					
					if	($data['record']) foreach($data['record'] as $k => $opt){
						// 창고별 재고 초기화
						if	($this->m_aWarehouse) foreach($this->m_aWarehouse as $wh_seq => $wh){
							if	(!$options[$opt['option_type'].$opt['option_seq']]['wh'][$wh['wh_seq']]['stock']){
								$options[$opt['option_type'].$opt['option_seq']]['wh'][$wh['wh_seq']]['location_position']	= '';
								$options[$opt['option_type'].$opt['option_seq']]['wh'][$wh['wh_seq']]['location_code']		= '';
								$options[$opt['option_type'].$opt['option_seq']]['wh'][$wh['wh_seq']]['stock']				= '0';
								$options[$opt['option_type'].$opt['option_seq']]['wh'][$wh['wh_seq']]['bad_stock']			= '0';
								$options[$opt['option_type'].$opt['option_seq']]['wh'][$wh['wh_seq']]['supply_price']		= '0';
								$options[$opt['option_type'].$opt['option_seq']]['wh'][$wh['wh_seq']]['total_price']		= '0';
							}
						}
						
						if($opt['wh_seq'] > 0){
							// 창고별 재고
							$options[$opt['option_type'].$opt['option_seq']]['wh'][$opt['wh_seq']]['location_position']	= $opt['location_position'];
							$options[$opt['option_type'].$opt['option_seq']]['wh'][$opt['wh_seq']]['location_code']		= $opt['location_code'];
							$options[$opt['option_type'] . $opt['option_seq']]['wh'][$opt['wh_seq']]['stock'] = $opt['ea'];
							$options[$opt['option_type'] . $opt['option_seq']]['wh'][$opt['wh_seq']]['bad_stock'] = $opt['bad_ea'];
							$options[$opt['option_type'] . $opt['option_seq']]['wh'][$opt['wh_seq']]['supply_price'] = number_format($opt['wh_supply_price'], 2);
							$options[$opt['option_type'] . $opt['option_seq']]['wh'][$opt['wh_seq']]['total_price'] = number_format($opt['wh_supply_price'] * $opt['ea'], 2);

							$options[$opt['option_type'] . $opt['option_seq']]['total_stock'] += $opt['ea'];
						}
						
						// 재배열
						$options[$opt['option_type'].$opt['option_seq']]['goods_seq']		= $opt['goods_seq'];
						$options[$opt['option_type'].$opt['option_seq']]['goods_code']		= $opt['goods_code'];
						$options[$opt['option_type'].$opt['option_seq']]['goods_name']		= $opt['goods_name'];
						$options[$opt['option_type'].$opt['option_seq']]['option_seq']		= $opt['option_seq'];
						$options[$opt['option_type'].$opt['option_seq']]['option_type']		= $opt['option_type'];
						$options[$opt['option_type'].$opt['option_seq']]['option_code']		= $opt['option_code'];
						$options[$opt['option_type'].$opt['option_seq']]['option_name']		= $opt['option_name'];
						
						//발주가액
						if($opt['auto_type'] == 'Y'){
							$supply_price = $opt['supply_price'].'%';
						}else{
							$supply_price = $opt['supply_price'];
						}
						
						//정가, 판매가액 (부가세를 뺀 가격)
						$consumer_price	= $opt['consumer_price'] - round($opt['consumer_price'] * 0.1);
						$price			= $opt['price'] - round($opt['price'] * 0.1);
						
						$options[$opt['option_type'].$opt['option_seq']]['use_status']			= $opt['use_status'];
						$options[$opt['option_type'].$opt['option_seq']]['main_trade_type']		= $opt['main_trade_type'];
						$options[$opt['option_type'].$opt['option_seq']]['supply_goods_name']	= $opt['supply_goods_name'];
						$options[$opt['option_type'].$opt['option_seq']]['trader_seq']			= $opt['trader_seq'];
						$options[$opt['option_type'].$opt['option_seq']]['scm_category']		= $opt['scm_category'];
						$options[$opt['option_type'].$opt['option_seq']]['reserve']				= $opt['reserve'];
						$options[$opt['option_type'].$opt['option_seq']]['consumer_price']		= $opt['consumer_price'];
						$options[$opt['option_type'].$opt['option_seq']]['price']				= $opt['price'];
						$options[$opt['option_type'].$opt['option_seq']]['safe_stock']			= $opt['safe_stock'];
						$options[$opt['option_type'].$opt['option_seq']]['trader_id']			= $opt['trader_id'];
						$options[$opt['option_type'].$opt['option_seq']]['trader_name']			= $opt['trader_name'];
						$options[$opt['option_type'].$opt['option_seq']]['supply_price_type']	= $opt['supply_price_type'];
						$options[$opt['option_type'].$opt['option_seq']]['supply_price']		= $supply_price;
						$options[$opt['option_type'].$opt['option_seq']]['auto_type']			= $opt['auto_type'];
						$options[$opt['option_type'].$opt['option_seq']]['use_supply_tax']		= $opt['use_supply_tax'];
						$options[$opt['option_type'].$opt['option_seq']]['option_code']			= $opt['option_code'];
					}
					
					$data['record']		= $options;
				}
			break;

			case 'default_shop':
				// 매장정보
				$shopno	= $this->config_system['shopSno'];
				$store	= $this->scmmodel->get_store(array());
				if	($store) foreach ( $store as $k => $data){
					if	($shopno == $data['shopSno']) $data['store_default'] = 1;
					if	($data['store_default'] == 1){
						$goods					= $this->scmmodel->get_location_goods_for_option($params);
					}elseif	($data['store_url']){
						//multi 매장 미개발
						//$url					= 'http://' . $data['store_url'] . '/scm/get_store_goods_info';
						//$jsonGoods				= readurl($url, $params);
						//$goods					= json_decode($jsonGoods);
					}

					// 상품정보와 매장정보 merge
					if	($goods['record']) foreach($goods['record'] as $k => $g){
						$optKey					= $g['goods_seq'] . $g['option_type'] . $g['option_seq'];
						if	($data['store_default'] == 1){
							$goods_name				= $g['goods_name'];
							$option_use				= $g['option_use'];
							$division_option_title	= explode(',', $g['option_title']);
							$option_title_count		= count($division_option_title);
							if	($option_use == 1 && $option_title_count > 0){
								$g['division_option_title']	= $division_option_title;
								for	($o = 1; $o <= 5; $o++){
									if	($g['option' . $o])	$g['opt'][]		= $g['option' . $o];
								}
							}else{
								$g['opt'][]				= '기본';
							}

							$reGoods[$optKey]['option_type']			= $g['option_type'];
							$reGoods[$optKey]['goods_seq']				= $g['goods_seq'];
							$reGoods[$optKey]['goods_name']				= $g['goods_name'];
							$reGoods[$optKey]['option_use']				= $g['option_use'];
							//$reGoods[$optKey]['goods_code']			= $g['goods_code'];
							$reGoods[$optKey]['goods_code']				= $g['goods_option_code'];							
							$reGoods[$optKey]['package_yn']				= $g['package_yn'];
							$reGoods[$optKey]['option_seq']				= $g['option_seq'];
							$reGoods[$optKey]['option_title']			= $g['option_title'];
							$reGoods[$optKey]['division_option_title']	= $g['division_option_title'];
							$reGoods[$optKey]['option1']				= $g['option1'];
							$reGoods[$optKey]['option2']				= $g['option2'];
							$reGoods[$optKey]['option3']				= $g['option3'];
							$reGoods[$optKey]['option4']				= $g['option4'];
							$reGoods[$optKey]['option5']				= $g['option5'];							
							$reGoods[$optKey]['opt']					= $g['opt'];
							$reGoods[$optKey]['total_supply_price']		= $g['total_supply_price'];
							$reGoods[$optKey]['total_stock']			= $g['total_stock'];
							$reGoods[$optKey]['total_badstock']			= $g['total_badstock'];
							
							$tmp_opt_names = array();
							for($i = 1; $i <= 5; $i++){
								if($g['option'.$i] && $g['option'.$i] != '') $tmp_opt_names[] = $g['option'.$i];
							}
							$reGoods[$optKey]['option_name']		= implode(',', $tmp_opt_names);
							$reGoods[$optKey]['safe_stock']			= number_format($g['safe_stock']);
							$reGoods[$optKey]['consumer_price']		= number_format($g['consumer_price'], 2, '.', '');
							$reGoods[$optKey]['price']				= number_format($g['price'], 2, '.', '');
							$reGoods[$optKey]['reserve']			= number_format($g['reserve'], 2, '.', '');
						}

						// 정가, 판매가 부가세 제외 금액
						//$consumer_price	= $g['consumer_price'] - round($g['consumer_price'] * 0.1);
						//$price			= $g['price'] - round($g['price'] * 0.1);

						$reGoods[$optKey]['store'][$data['admin_env_seq']]['admin_env_seq']	= $data['admin_env_seq'];
						$reGoods[$optKey]['store'][$data['admin_env_seq']]['store_default']	= $data['store_default'];
						$reGoods[$optKey]['store'][$data['admin_env_seq']]['store_type']	= $data['store_type'];
						$reGoods[$optKey]['store'][$data['admin_env_seq']]['admin_env_name']= $data['admin_env_name'];
						$reGoods[$optKey]['store'][$data['admin_env_seq']]['store_location']= $data['store_location'];
						$reGoods[$optKey]['store'][$data['admin_env_seq']]['consumer_price']= number_format($g['consumer_price'], 2, '.', '');
						$reGoods[$optKey]['store'][$data['admin_env_seq']]['price']			= number_format($g['price'], 2, '.', '');
						$reGoods[$optKey]['store'][$data['admin_env_seq']]['org_consumer']	= number_format($g['consumer_price'], 2, '.', '');
						$reGoods[$optKey]['store'][$data['admin_env_seq']]['org_price']		= number_format($g['price'], 2, '.', '');
						$reGoods[$optKey]['store'][$data['admin_env_seq']]['supply_price']	= $g['supply_price'];
						$reGoods[$optKey]['store'][$data['admin_env_seq']]['stock']			= $g['stock'];
						$reGoods[$optKey]['store'][$data['admin_env_seq']]['badstock']		= $g['badstock'];
						$reGoods[$optKey]['store'][$data['admin_env_seq']]['safe_stock']	= number_format($g['safe_stock']);
						$reGoods[$optKey]['store'][$data['admin_env_seq']]['reservation15']	= $g['reservation15'];
						$reGoods[$optKey]['store'][$data['admin_env_seq']]['reservation25']	= $g['reservation25'];
					}
				}
				$this->m_aStore = $store;
				$data['record']	= $reGoods;
			break;

			case 'default_supply':
				$params['get_goods_info']	= true;
				// 환율정보 추출
				$exchanges			= $this->scmmodel->get_exchange_config();
				$currency_list		= array_keys($exchanges);

				unset($sc);
				// 상품 목록 추출		
				$sc							= $_POST;
				// 상품목록
				unset($sc);
				$sc['page']					= ($_POST['page'])		? intval($_POST['page'])	: '1';
				$sc['perpage']				= $this->m_nMaxRow;
				$sc['sc_wh_seq']			= $_POST['sc_wh_seq'];
				$sc['sc_location']			= $_POST['sc_location'];
				
				//상품구분
				if($_POST['sc_goods_kind']){
					$tmpcnt = 0;
					foreach($_POST['sc_goods_kind'] as $key=>$val){
						if($tmpcnt != 0) $sc['sc_goods_kind'] .= ',';
						$sc['sc_goods_kind'] .= "'".$val."'";
						$tmpcnt++;
					}
				}

				//주거래처
				$sc['sc_traderGroup']		= $_POST['sc_trader_group'] ? $_POST['sc_trader_group'] : null;
				$sc['sc_trader']			= $_POST['sc_trader'] ? $_POST['sc_trader'] : null;
				
				//주거래처 없음
				$sc['sc_exists_info']		= ($_POST['sc_exists_info']) ? '1' : '0';
				
				//재고 검색
				$sc['wh_seq']				= $_POST['sc_stock_warehouse'] ? $_POST['sc_stock_warehouse'] : null;
				$sc['wh_stock_seq']			= $this->input->post('sc_stock_warehouse_seq') ?? null;
				$sc['total_sStock']			= $_POST['sStock'] ? $_POST['sStock'] : null;
				$sc['total_eStock']			= $_POST['eStock'] ? $_POST['eStock'] : null;

				// 로케이션 검색 여부
				$sc['allowLocation']	=	$params['allowLocation'];

				//카테고리 검색
				$sc['scm_category']			= $_POST['sc_scm_category'] ? $_POST['sc_scm_category'] : null;
				if($sc['scm_category']) foreach($sc['scm_category'] as $val){
					if($val != '') $category[] = $val;
				}

				//선택 값
				if($_POST['excel_type'] == 'select' && $_POST['option_info_arr']){
					$sc['option_info_arr'] = $_POST['option_info_arr'];
				}
				
				//검색어
				if		($sType)			$sc[$sType]		= $_POST['keyword'];
				elseif	($_POST['keyword'])	$sc['keyword']	= $_POST['keyword'];

				list($loop,$page)		= $this->scmmodel->get_goods_default_order_data($sc);
				if	($loop) foreach($loop as $k => $val){

					// 기본 가공 정보
					$sc['option_info_arr']						= $val['goods_seq'] . $val['option_type'] . $val['option_seq'];
					$main_info									= $this->scmmodel->get_order_defaultinfo($sc);
					$val['trader_name']							= $val['trader_name'] . '(' . $val['currency_unit'] . ')';
					if	($val['currency_unit'] != 'KRW')	$val['use_supply_tax']	= 'N';

					if($main_info['record']){
						foreach($main_info['record'] as $row){
							$tmp_val					= $row;
							$tmp_val['goods_code']		= $val['goods_code'] . $val['option_code'];
							$tmp_val['goods_name']		= $val['goods_name'];
							$tmp_val['option_name']		= $val['option_name'];
							$tmp_val['trader_name']		= $row['trader_name'] . '(' . $row['currency_unit'] . ')';
							if	($row['currency_unit'] != 'KRW')	$tmp_val['use_supply_tax']	= 'N';

							$data['record'][] = $tmp_val;
						}
					}else{
						$data['record'][] = $val;
					}	
				}

			break;
			case 'traderaccount':
				if	(!$_POST['date_selected'])	$_POST['date_selected']	= 'today';
				if	(!$_POST['sc_sdate'])		$_POST['sc_sdate']		= date('Y-m-d');
				if	(!$_POST['sc_edate'])		$_POST['sc_edate']		= date('Y-m-d');
				if	(!$_POST['orderby'])		$_POST['orderby']		= 'trader_name';

				unset($sc);
				$sc['sc_sdate']			= $_POST['sc_sdate'];
				$sc['sc_edate']			= $_POST['sc_edate'];
				$sc['sc_trader_seq']	= $_POST['sc_trader_seq'];
				$sc['groupby']			= 'sta.trader_seq';
				$sc['orderby']			= $_POST['orderby'];
				$data['record']			= $this->scmmodel->get_traderaccount_list($sc);
			break;
		}
		if	($data['page']['totalpage'] > 1){
			$this->m_nExcelPage		= $page;
			$this->m_bZipDown		= true;
			$this->m_nTotalCount	= $data['page']['totalcount'];
			$this->m_nTotalPage		= $data['page']['totalpage'];
		}
		
		$this->m_nExcelTotal	= $data['total'];

		return $data['record'];
	}

	// 담당자 정보 추출
	public function get_manager_data($data){
		$sc['parent_table']	= $this->m_sForType;
		switch($this->m_sForType){
			case 'trader':
				$sc['parent_seq']	= $data['trader_seq'];
			break;
		}
		$result		= $this->scmmodel->get_manager($sc);
		$manager	= $result[0];
		$manager['manager_phone_number']	= $manager['phone_number'];
		unset($manager['phone_number']);

		return $manager;
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

	// 데이터 추가 변환
	public function download_except_replace($fld, $data){
		$val		= $data[$fld];
		switch($fld){
			case 'trader_pass' : 
				$val	= '';
			break;
			case 'trader_use' :
				if	($data[$fld] == 'N')				$val	= '거래종료';
				else									$val	= '거래중';
			break;
			case 'trader_type' :
				if	($data[$fld] == 'sales')			$val	= '매출';
				else									$val	= '매입';
			break;
			case 'trader_location' :
				if	($data[$fld] == 'Y')				$val	= '해외';
				else									$val	= '국내';
			break;
			case 'bank_name' :
				$bankData	= code_load('bankCode', $data[$fld]);
				$val		= $bankData[0]['value'];
			break;
			case 'favorite_chk' :
				if	($data[$fld] == '1')				$val	= '★';
				else									$val	= '☆';
			break;
			case 'option_type' :
				if	(preg_match('/^sub/', $data[$fld]))	$val	= '추가';
				else									$val	= '필수';
			break;
			case 'use_status' :
				if	($data[$fld] == 'Y')				$val	= '○';
				else									$val	= 'X';
			break;
			case 'main_trade_type' :
				if	($data[$fld] == 'Y')				$val	= '○';
				else									$val	= 'X';
			break;
			case 'use_supply_tax' :	
				if	($data[$fld] == 'Y')				$val	= '○';
				else									$val	= 'X';
			break;
			case 'auto_type' :
				if	($data[$fld] == 'Y')				$val	= '자동';
				else									$val	= '수동';
			break;
			case 'supply_price' :
				if	($data['auto_type']){
					if	($data['auto_type'] == 'Y')		$val	= floor($data[$fld]) . '%';
					else								$val	= $data[$fld];
				}
			break;
		}

		return $val;
	}

	// 창고정보 전역변수로 설정
	public function set_warehouse($addTotal = 'n'){
		// 창고목록
		$warehouse			= $this->scmmodel->get_warehouse(array());
		if	($warehouse) foreach($warehouse as $w => $data){
			$reWh[$data['wh_seq']]	= $data;
		}
		unset($warehouse);
		$warehouse	= $reWh;

		// 창고별 재고
		if	($addTotal == 'y'){
			unset($sc);
			$sc['get_type']		= 'wh';
			$whTotal			= $this->scmmodel->get_location_stock($sc);
			if	($whTotal) foreach($whTotal as $w => $data){
				$warehouse[$data['wh_seq']]['stock']		= $data['ea'];
				$warehouse[$data['wh_seq']]['bad_stock']	= $data['bad_ea'];
				$warehouse[$data['wh_seq']]['supply_price']	= $data['tot_supply_price'];
				$warehouse[$data['wh_seq']]['total_price']	= $data['total_supply_price'];
				$totalStock									+= $data['ea'];
			}
			$this->m_nTotalStock	= $totalStock;
		}
		$this->m_aWarehouse		= $warehouse;
	}

	########## ↑↑↑↑↑ 엑셀 다운로드 ↑↑↑↑↑ ##########




	########## ↓↓↓↓↓ 엑셀 업로드 ↓↓↓↓↓ ##########

	// 파일 업로드
	public function excel_file_upload($filename, $filedata){
		$this->load->library('upload');
	
		$fileinfo	= $filedata[$filename];
		if($fileinfo['tmp_name'] && count($fileinfo['tmp_name']) > 0){
			for($i=0; $i<count($fileinfo['tmp_name']); $i++){
				
				//CI 다중업로드처리가 안되므로 FILES 객체에 해당 파일정보를 세팅한다.
				$_FILES[$filename]['name']		= $fileinfo['name'][$i];
				$_FILES[$filename]['type']		= $fileinfo['type'][$i];
				$_FILES[$filename]['tmp_name']	= $fileinfo['tmp_name'][$i];
				$_FILES[$filename]['error']		= $fileinfo['error'][$i];
				$_FILES[$filename]['size']		= $fileinfo['size'][$i];

				if	(is_uploaded_file($fileinfo['tmp_name'][$i])){
					$fileExt				= end(explode('.', $fileinfo['name'][$i]));
					$fileName				= 'upload_goods_excel_' . date('YmdHis') . rand(0,9999);
					$cfg['allowed_types']	= 'xls';
					$cfg['file_name']		= $fileName;
					$cfg['upload_path']		= $this->m_sExcelUploadFilePath . '/';
					$cfg['overwrite']		= TRUE;
					$this->upload->initialize($cfg);
					if ($this->upload->do_upload($filename)) {
						$file_nm[]	= $cfg['upload_path'] . $cfg['file_name'] . '.' . $cfg['allowed_types'];
						@chmod($file_nm, 0777);

						$status		= true;
					}else{
						$err_msg			= 'xls 파일만 가능합니다.';
						$status		= false;
					}
				}else{
					$err_msg			= '파일을 등록해 주세요.';
					$status		= false;
					break;
				}
			}			
		}else{
			$err_msg	= '파일을 등록해 주세요.';
			$status		= false;
		}

		$return	= array('status' => $status, 'file' => $file_nm, 'msg' => $err_msg);
		
		return $return;
	}

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
				$maxRow			= $this->workSheet->getHighestRow();
				if	($this->m_nMaxRow < ($maxRow - 1)){
					return array('status' => false, 'msg' => '최대 ' . $this->m_nMaxRow . '개까지 등록 가능합니다.');
				}

				// 양식에 따른 재배열
				$headRow	= $this->field_match_to_excel();
				$startRow	= $headRow + 1;
				$saveFunc	= 'save_' . $this->m_sForType;
				$this->m_aMainTrader = array();
				$this->m_aGoodsCodeIdx = array();
				for	( $r = $startRow; $r <= $maxRow; $r++){
					$result		= $this->$saveFunc($r);
					
					if ($result['status']) {
						$success++;
					} else {
						if ($this->m_sForType != 'default_stock') {
							$fail++;
						} else {
							// 창고별 재고는 기본 1Row가 엑셀 2Row라서 홀수 일때만 실행
							if($number % 2 != 0){
								$fail++;
							}
						}
					}
				}
				
				// 매입정보에서 주거래처에 대한 추가 처리
				if	($this->m_sForType == 'default_supply'){
					$this->set_once_main_trader();
				}
				
				// 창고별 재고는 기본 1Row가 엑셀 2Row라서 결과 수치를 반으로 줄임.
				if	($this->m_sForType == 'default_stock'){
					$maxRow		= FLOOR($maxRow / 2);
					$headRow	= FLOOR($headRow / 2);
				}

				// 결과 message 생성
				if		($success > 0 && $fail > 0){
					$msg	= ($maxRow - $headRow) . '개 중 ' . $success . '개 성공, ' . $fail . '개 실패하였습니다.';
				}elseif	($success > 0){
					$msg	= '정상적으로 처리되었습니다.';
				}elseif	($fail > 0){
					$msg	= '처리에 실패하였습니다.';
				}else{
					$msg	= '비정상 동작으로 정상처리되지 않았습니다.';
				}
			}
			
			if	($this->m_sForType == 'default_supply')	$this->scmmodel->chg_taxuse_to_currency();

			return array('status' => true, 'msg' => $msg);
		}catch (exception $e) {
			return array('status' => false, 'msg' => '엑셀파일을 읽는도중 오류가 발생하였습니다. [' . $e->getMessage() . ']');
		}
	}

	// excel의 cellCode와 fieldName 매칭 배열 생성
	public function field_match_to_excel(){
		$maxCol			= $this->workSheet->getHighestColumn();
		$maxColCnt		= $this->calculate_alphar_to_count($maxCol);
		$alpharArr		= $this->get_excel_cell_alphar($maxColCnt);
		$headRow		= $this->m_sForType == 'trader' ? '1' : '2';
		
		// 타이틀 영역 추출해서 cellcode 배열 생성
		foreach($alpharArr as $k => $cell){
			$title		= $this->workSheet->getCell($cell.$headRow)->getValue();
			foreach($this->m_aCellList as $key=>$val){

				if($this->m_sForType == 'trader' || $this->m_sForType == 'traderaccount'){
					if	(str_replace("*","",$title) == $val){
						$cellCodeArr[$cell]	= $key;
						break;
					}
				}else{
					foreach($val as $key2=>$val2){
						if	(str_replace("*","",$title) == $val2){
							$cellCodeArr[$cell]	= $key2;
							break;
						}
					}
				}
				
			}		
		}
		$this->m_aCellCode	= $cellCodeArr;

		return $headRow;
	}

	// 거래처 정보 저장
	public function save_trader($rowNum){

		$scm_cfg		= $this->scmmodel->scm_cfg;

		// 데이터 배열로 변환
		if	($this->m_aCellCode) foreach($this->m_aCellCode as $cell => $fld){
			$val		= $this->workSheet->getCell($cell.$rowNum)->getValue();
			$val		= $this->upload_except_replace($fld, $val);
			if	($fld && trim($val)){
				if		($fld == 'trader_seq'){
					$trader_seq		= trim($val);
				}elseif	(in_array($fld, array('manager_name', 'manager_partname', 'manager_charge', 'manager_phone_number', 'extension_number', 'cellphone_number', 'email'))){
					$manager[$fld]	= trim($val);
				}elseif	(in_array($fld, array('default_date', 'remain_account'))){
					$account[$fld]	= trim($val);
				}else{
					$trader[$fld]	= trim($val);
				}
			}
		}

		// db 저장
		if	($trader_seq > 0){
			// update 미지원
			unset($trader['trader_id'], $trader['trader_type'], $trader['currency_unit']);

			if	($trader['trader_pass']){
				$chkVal	= $this->scmmodel->chk_password($trader['trader_pass']);
				if	($chkVal['result'])		$trader['trader_pass']	= hash('sha256', $trader['trader_pass']);
				else						unset($trader['trader_pass']);
			}

			$trader['chg_log']			= '<div>' . date('Y-m-d H:i:s') . ' '
										. $this->managerInfo['mname']
										. '(' . $this->managerInfo['manager_id'] . ')가 '
										. '거래처의 정보를 엑셀일괄수정하였습니다. '
										. '(' . $_SERVER['REMOTE_ADDR'] . ')</div>';
			$this->db->where(array('trader_seq' => $trader_seq));
			$this->db->update('fm_scm_trader', $trader);

			// 담당자 정보 수정
			$this->db->delete('fm_scm_manager', array('parent_table' => 'trader', 'parent_seq' => $trader_seq));
			$manager['parent_table']	= 'trader';
			$manager['parent_seq']		= $trader_seq;
			$manager['phone_number']	= $manager['manager_phone_number'];
			unset($manager['manager_phone_number']);
			$this->db->insert('fm_scm_manager', $manager);
		}else{
			$trader['trader_pass']		= 'firstmall1234';

			// 필수값 체크 (  )
			if	($trader['trader_id'] && $trader['trader_pass'] && $trader['trader_name'] && $trader['trader_group']){
				// 비밀번호 체크
				$chkVal	= $this->scmmodel->chk_password($trader['trader_pass']);
				if	($chkVal['result']){
					$trader['trader_pass']	= hash('sha256', $trader['trader_pass']);
					$trader					= $this->add_insert_default_data($trader);
					$this->db->insert('fm_scm_trader', $trader);
					$trader_seq	= $this->db->insert_id();

					// 담당자 정보 추가
					if	($trader_seq && $manager){
						$manager['parent_table']	= 'trader';
						$manager['parent_seq']		= $trader_seq;
						$manager['phone_number']	= $manager['manager_phone_number'];
						unset($manager['manager_phone_number']);
						$this->db->insert('fm_scm_manager', $manager);
					}

					// 거래처 정산 추가
					if	($trader_seq && $account['default_date'] && ($account['remain_account'] && $account['remain_account'] != 0)){
						$scm_cfg	= $this->scmmodel->scm_cfg;						
						if	($scm_cfg['set_account_date']){
							unset($params);
							$params['act_type']			= 'def';
							$params['trader_seq']		= $trader_seq;
							$params['currency']			= $trader['currency_unit'];
							$params['act_price']		= $account['remain_account'];
							$params['act_carriedover']	= '0';
							$params['act_memo']			= '기초정산 추가';
							$params['act_date']			= $scm_cfg['set_account_date'];
							$this->scmmodel->save_traderaccount($params);
						}
					}
				}else{
					return array('status' => false, 'msg' => $rowNum . '행 유효하지 않은 비밀번호로 거래처 추가에 실패했습니다.');
				}
			}else{
				return array('status' => false, 'msg' => $rowNum . '행 아이디, 비밀번호, 거래처명, 분류명 누락으로 거래처 추가에 실패했습니다.');
			}
		}

		return array('status' => true);
	}

	// 자동발주정보 저장
	public function save_default_supply($rowNum){
		// 데이터 배열로 변환
		if	($this->m_aCellCode) foreach($this->m_aCellCode as $cell => $fld){
			$val		= $this->workSheet->getCell($cell.$rowNum)->getValue();
			$val		= $this->upload_except_replace($fld, $val);
			if	($fld && trim($val)){
				if		(in_array($fld, array('supply_goods_name', 'use_status', 'main_trade_type', 'trader_seq', 'supply_price', 'use_supply_tax'))){
					$supply[$fld]	= trim($val);
				}else{
					$$fld			= trim($val);
				}
			}
		}
		if	(!$this->scmmodel->scm_use_suboption_mode)	$option_type	= 'option';

		// db 저장
		if	($goods_seq > 0 && $option_type && $option_seq > 0){
			$supply		= $this->add_insert_default_data($supply);
			$optionStr	= $goods_seq . $option_type . $option_seq;

			// 해당 옵션의 자동발주정보 삭제 ( 최초 한번만 삭제함 )
			if	(!in_array($optionStr, $this->m_aOptionInfo)){
				$this->db->delete('fm_scm_order_defaultinfo', array('goods_seq' => $goods_seq, 'option_type' => $option_type, 'option_seq' => $option_seq));
				$this->m_aOptionInfo[$optionStr]	= $optionStr;
			}

			// 주거래처 처리
			if	($supply['main_trade_type'] == 'Y' && !$this->m_aMainTrader[$optionStr]){
				$this->m_aMainTrader[$optionStr]	= 'Y';
				$supply['use_status']				= 'Y';
			}else{
				$supply['main_trade_type']			= 'N';
			}
			$supply['goods_seq']					= $goods_seq;
			$supply['option_type']					= $option_type;
			$supply['option_seq']					= $option_seq;
			$this->db->insert('fm_scm_order_defaultinfo', $supply);

			// 로그 저장 ( performence를 위해 select -> insert/update 대신 duplicate key update를 사용 )
			$chg_log	= '<div>[' . date('Y-m-d H:i:s') . '] '
						. $this->managerInfo['mname'] . '('
						. $this->managerInfo['manager_id'] . ')가 엑셀 일괄 등록/수정으로 ';
			if	($option_type == 'suboption')	$chg_log	.= '추가옵션';
			else								$chg_log	.= '필수옵션';
			$chg_log	.= '(' . $option_seq . ')의 '
						. '자동발주정보를 수정하였습니다. (' . $_SERVER['REMOTE_ADDR'] . ')</div>';
			$chg_log	= addslashes($chg_log);
			$sql		= "insert into fm_scm_order_defaultinfo_log (goods_seq, admin_memo, chg_log) "
						. "values('" . $goods_seq . "', '', '" . $chg_log . "') "
						. "on duplicate key update chg_log = concat(chg_log, '" . $chg_log . "')";
			$this->db->query($sql);
			return array('status' => true);
		}else{
			return array('status' => false, 'msg' => $rowNum . '행 상품번호, 옵션구분, 옵션번호 누락으로 자동발주정보 등록/수정에 실패했습니다.');
		}

		return array('status' => true);
	}

	// 쇼핑몰별안전재고 저장
	public function save_default_shop($rowNum){
		// 마켓 번호 가져오기
		for($i = 3; $i < count($this->m_aCellCode); $i = $i+6){
			$cell_idx = $this->get_cell_alpha($i);

			$val			= $this->workSheet->getCell($cell_idx.'1')->getValue();
			$val			= explode('=', $val);
			$market_id[]	= $val[1];
		}

		// 데이터 배열로 변환
		if	($this->m_aCellCode) {
			$supIdx = 0;
			foreach($this->m_aCellCode as $cell => $fld){
				$val		= $this->workSheet->getCell($cell.$rowNum)->getValue();
				$val		= $this->upload_except_replace($fld, $val);
				if	($fld && trim($val)){
					if		(in_array($fld, array('goods_seq', 'option_seq', 'goods_code'))){
						$$fld			= trim($val);
					}else{
						if(!$supply[ $market_id[$supIdx] ][$fld]) {
							$supply[ $market_id[$supIdx] ][$fld]	= trim($val);
						} else {
							$supply[ $market_id[$supIdx+1] ][$fld]	= trim($val);
							$supIdx++;
						}
					}
				}
			}
		}

		//db저장
		if	($goods_seq > 0 && $option_seq > 0){			
			foreach($supply as $key=>$row){		
				$store = $this->scmmodel->get_store(array('admin_env_seq' => $key));
				$data  = $store[0];
				if	($data) {
					if	($data['store_default'] == 1){
						//메인상품명
						$goods_set = array('goods_name'			=> $row['goods_name']);

						//상품옵션
						$tmp_opt = explode(',', $row['option_name']);						
						if(count($tmp_opt) > 0){
							for($i = 1; $i <= count($tmp_opt); $i++){
								$option_set['option'.$i] = $tmp_opt[$i-1];
							}
						}
						$option_set['price']			= $row['price'];
						$option_set['consumer_price']	= $row['consumer_price'];
						$option_set['reserve']			= $row['reserve'];

						//상품재고
						$supply_set = array('safe_stock'		=> $row['safe_stock']);

						if($goods_set)	$this->db->update('fm_goods', $goods_set , array( 'goods_seq' => $goods_seq ));
						if($option_set)	$this->db->update('fm_goods_option', $option_set , array( 'goods_seq' => $goods_seq, 'option_seq' => $option_seq ));
						if($supply_set)	$this->db->update('fm_goods_supply', $supply_set , array( 'goods_seq' => $goods_seq, 'option_seq' => $option_seq  ));
						
						// 로그 저장 ( performence를 위해 select -> insert/update 대신 duplicate key update를 사용 )
						$chg_log	= '<div>[' . date('Y-m-d H:i:s') . '] '
									. $this->managerInfo['mname'] . '('
									. $this->managerInfo['manager_id'] . ')가 엑셀 일괄 등록/수정으로 ';
						if	($option_type == 'suboption')	$chg_log	.= '추가옵션';
						else								$chg_log	.= '필수옵션';
						$chg_log	.= '(' . $option_seq . ')의 '
									. '쇼핑몰별안전재고를 수정하였습니다. (' . $_SERVER['REMOTE_ADDR'] . ')</div>';
						$chg_log	= addslashes($chg_log);
						$sql		= "insert into fm_scm_order_defaultinfo_log (goods_seq, admin_memo, chg_log) "
									. "values('" . $goods_seq . "', '', '" . $chg_log . "') "
									. "on duplicate key update chg_log = concat(chg_log, '" . $chg_log . "')";
						$this->db->query($sql);
						return array('status' => true);
					}elseif	($data['store_url']){
						//multi 매장 미개발
						//$url					= 'http://' . $data['store_url'] . '/scm/get_store_goods_info';
						//$jsonGoods				= readurl($url, $params);
						//$goods					= json_decode($jsonGoods);
					}
				}
			}
		}else{
			return array('status' => false, 'msg' => $rowNum . '행 상품번호, 옵션번호 누락으로 쇼핑몰별안전재고 등록/수정에 실패했습니다.');
		}
	}

	// 창고별재고 저장
	public function save_default_stock($rowNum){
		// 데이터 배열로 변환
		if	($this->m_aCellCode) foreach($this->m_aCellCode as $cell => $fld){
			$val		= $this->workSheet->getCell($cell.$rowNum)->getValue();
			$val		= $this->upload_except_replace($fld, $val);
			if	($fld && trim($val)){
				if		(in_array($fld, array('goods_seq', 'option_seq', 'goods_name', 'option_name', 'stock', 'supply_price', 'total_price'))){
					$$fld			= trim($val);
				}else{
					$supply[$fld]	= trim($val);
				}
			}
		}

		// db 저장
		if	($goods_seq > 0 && $option_seq > 0){
			$supply		= $this->add_insert_default_data($supply);
			
			//goods 용 update 배열 변수
			unset($goods_set);
			if(!$this->m_aGoodsCodeIdx[$goods_seq]){				
				$goods_set['goods_code']	= $supply['goods_code'];			
				$goods_set['scm_category']	= $supply['scm_category'];
				$this->m_aGoodsCodeIdx[$goods_seq] = $goods_seq;
			}

			//옵션코드 나누기
			$opt_arr = explode(',', $supply['option_code']);
			
			unset($option_set);
			for($i = 1; $i <= count($opt_arr); $i++){
				$option_set['optioncode'.$i] = $opt_arr[$i-1];
			}

			if($goods_set)	$this->db->update('fm_goods', $goods_set , array( 'goods_seq' => $goods_seq ));
			if($option_set) $this->db->update('fm_goods_option', $option_set , array( 'goods_seq' => $goods_seq, 'option_seq' => $option_seq ));
			
			// 로그 저장 ( performence를 위해 select -> insert/update 대신 duplicate key update를 사용 )
			$chg_log	= '<div>[' . date('Y-m-d H:i:s') . '] '
						. $this->managerInfo['mname'] . '('
						. $this->managerInfo['manager_id'] . ')가 엑셀 일괄 등록/수정으로 ';
			if	($option_type == 'suboption')	$chg_log	.= '추가옵션';
			else								$chg_log	.= '필수옵션';
			$chg_log	.= '(' . $option_seq . ')의 '
						. '창고별재고를 수정하였습니다. (' . $_SERVER['REMOTE_ADDR'] . ')</div>';
			$chg_log	= addslashes($chg_log);
			$sql		= "insert into fm_scm_order_defaultinfo_log (goods_seq, admin_memo, chg_log) "
						. "values('" . $goods_seq . "', '', '" . $chg_log . "') "
						. "on duplicate key update chg_log = concat(chg_log, '" . $chg_log . "')";
			$this->db->query($sql);
			return array('status' => true);
		}else{
			return array('status' => false, 'msg' => $rowNum . '행 상품번호, 옵션번호 누락으로 창고별재고 등록/수정에 실패했습니다.');
		}
	}

	// 주거래처가 없는 경우 첫번째 매입정보를 주거래처로 update
	public function set_once_main_trader(){
		if	($this->m_aOptionInfo) foreach($this->m_aOptionInfo as $key => $optionStr){
			// 주거래처가 없는 옵션
			if	(!in_array($optionStr, $this->m_aMainTrader)){
				if	(preg_match('/option/', $optionStr)){
					$tmp			= explode('option', $optionStr);
					$goods_seq		= $tmp[0];
					$option_type	= 'option';
					$option_seq		= $tmp[1];
				}else{
					$tmp			= explode('suboption', $optionStr);
					$goods_seq		= $tmp[0];
					$option_type	= 'suboption';
					$option_seq		= $tmp[1];
				}

				if	($goods_seq > 0 && $option_type && $option_seq > 0){
					$sql	= "update fm_scm_order_defaultinfo a, "
							. "(select default_seq from fm_scm_order_defaultinfo "
							. "where goods_seq = '" . $goods_seq . "' "
							. "and option_type = '" . $option_type . "' "
							. "and option_seq = '" . $option_seq . "' " 
							. "order by default_seq asc limit 1) b "
							. "set a.main_trade_type = 'Y', a.use_status = 'Y' "
							. "where a.default_seq = b.default_seq";
					$this->db->query($sql);
				}
			}
		}
	}

	// 필수값이 없을 시 대체값 추가
	public function add_insert_default_data($data){
		switch ($this->m_sForType){
			// 거래처
			case 'trader':
//				if	(!$data['trader_type'])			$data['trader_type']		= 'supply';
				$data['trader_type']		= 'supply';
				if	(!$data['trader_location'])		$data['trader_location']	= 'N';
				if	(!$data['trader_use'])			$data['trader_use']			= 'Y';
				if	(!$data['favorite_chk'])		$data['favorite_chk']		= '0';
				if	(!$data['currency_unit'])		$data['currency_unit']		= 'KRW';

				$data['regist_date']		= date('Y-m-d H:i:s');
				$data['modify_date']		= date('Y-m-d H:i:s');
				$data['chg_log']			= '<div>' . date('Y-m-d H:i:s') . ' '
											. $this->managerInfo['mname']
											. '(' . $this->managerInfo['manager_id'] . ')가 '
											. '거래처의 정보를 엑셀일괄등록하였습니다. '
											. '(' . $_SERVER['REMOTE_ADDR'] . ')</div>';
			break;

			// 자동발주정보
			case 'default_supply':
				if	(!$data['use_status'])			$data['use_status']			= 'Y';
				if	(!$data['main_trade_type'])		$data['main_trade_type']	= 'N';
				$data['regist_date']		= date('Y-m-d H:i:s');
			break;

		}

		return $data;
	}

	// 데이터 추가 변환
	public function upload_except_replace($fld, $val){
		switch($fld){
			case 'trader_use' :
				if	($val == '거래종료')			$val	= 'N';
				else							$val	= 'Y';
			break;
			case 'trader_type' :
/*				if	($val == '매출')				$val	= 'sales';
				else							*/$val	= 'supply';
			break;
			case 'currency_unit' :
				if (!$val)						$val	= 'KRW';
			break;
			case 'trader_location' :
				if	($val == '해외')				$val	= 'Y';
				else							$val	= 'N';
			break;
			case 'bank_name' :
				$bankData	= code_load('bankCode');
				$bankName	= $val;
				$val		= '';
				if	($bankData) foreach($bankData as $k => $bank){
					if	($bank['value'] == $bankName){
						$val	= $bank['codecd'];
						break;
					}
				}
			break;
			case 'favorite_chk' :
				if	($val == '★')				$val	= '1';
				else							$val	= '0';
			break;
			case 'option_type' :
				if	($val == '추가')			$val	= 'suboption';
				else							$val	= 'option';
			break;
			case 'use_status' :
				if	($val == '○' || strtoupper($val) == 'O')	$val	= 'Y';
				else											$val	= 'N';
			break;
			case 'main_trade_type' :
				if	($val == '○' || strtoupper($val) == 'O')	$val	= 'Y';
				else											$val	= 'N';
			break;
			case 'use_supply_tax' :	
				if	($val == '○' || strtoupper($val) == 'O')	$val	= 'Y';
				else											$val	= 'N';
			break;
			case 'auto_type' :
				if	($val == '자동')			$val	= 'Y';
				else							$val	= 'N';
			break;
			case 'supply_price' :
				if	(preg_match('/%/', $val)){
					$val	= floor(preg_replace('/[^0-9\.]/', '', $val));
					if	($val > 100)	$val	= '100';
				}else{
					$val	= preg_replace('/[^0-9\.]/', '', $val);
				}
			break;
		}

		return $val;
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

	// 설정값 정의
	public function set_init($params){
		$this->m_sForType	= $params['type'];
		if	(strtoupper($params['process']) == 'UPDATE'){
			$this->m_sProcType	= 'U';	// UPDATE ( 업로드 )
		}else{
			$this->m_sProcType	= 'D';	// DOWNLOAD ( 다운로드 )
		}

		$this->set_cell_list();
		$this->set_multiRow_cell();
	}

	// field에 따라 cell style 정의
	public function get_cell_style($fld){
		if	(preg_match('/reserve/', $fld) || (preg_match('/price/', $fld) && !preg_match('/price_type/', $fld))){
			$styleID	= 's62';
			$dataType	= 'String';
		}elseif	(preg_match('/(ea|stock)/', $fld)){
			$styleID	= 's63';
			$dataType	= 'String';		
		}elseif	(preg_match('/(name|info|code)/', $fld)){
			$styleID	= 's61';
			$dataType	= 'String';
		}else{
			$styleID	= 's60';
			$dataType	= 'String';
		}

		return array('style' => $styleID, 'type' => $dataType);
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

	public function get_cell_alpha($cellIdx){

		$char		= 26;
		$cellArr	= array('A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M',
							'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z');
		if($cellIdx > $char){
			$return = $cellArr[floor($cellIdx / $char)];
			$return .= $cellArr[$cellIdx % $char];
		}else{
			$return = $cellArr[$cellIdx];
		}

		return $return;
	}

	########## ↑↑↑↑↑ 기타 관련 ↑↑↑↑↑ ##########






	########## ↓↓↓↓↓ 기본 설정 배열 함수 ↓↓↓↓↓ ##########

	// 전체 컬럼 목록 배열 정의
	public function set_cell_list(){
		switch ($this->m_sForType){

			// 거래처
			case 'trader':
				if	(true){	// 시작
				$cell['trader_seq']					= '코드';
				$cell['trader_id']					= '아이디';
//				$cell['trader_pass']				= '비밀번호';
				$cell['trader_name']				= '거래처명';
				$cell['trader_group']				= '분류';
				$cell['trader_type']				= '구분';
				$cell['currency_unit']				= '통화';
//				$cell['trader_location']			= '국내/해외';
				$cell['trader_use']					= '상태';
				$cell['company_owner']				= '대표자명';
				$cell['company_url']				= '홈페이지';
				$cell['regist_number']				= '사업자등록번호';
				$cell['business_type']				= '업태';
				$cell['business_category']			= '업종';
				$cell['phone_number']				= '전화번호';
				$cell['fax_number']					= '팩스번호';
				$cell['zipcode']					= '우편번호';
				$cell['address']					= '지번 주소';
				$cell['address_street']				= '도로명 주소';
				$cell['address_detail']				= '상세주소';
				$cell['bank_name']					= '은행명';
				$cell['bank_owner']					= '예금주';
				$cell['bank_number']				= '계좌번호';
				$cell['favorite_chk']				= '★표시 여부';
				$cell['manager_name']				= '담당자명';
				$cell['manager_partname']			= '부서명';
				$cell['manager_charge']				= '담당업무';
				$cell['manager_phone_number']		= '담당자전화번호';
				$cell['extension_number']			= '담당자내선번호';
				$cell['cellphone_number']			= '담당자휴대폰';
				$cell['email']						= '담당자이메일';
				$cell['default_date']				= '기준일자';
				$cell['remain_account']				= '미지급잔액';
				}	// 끝
			break;
			//2016.04.25 엑셀 양식 변경 pjw
			// 자동발주정보
			case 'default_supply':
				if	(true){	// 시작
				$cell['master']['goods_seq']		= '상품번호';
				$cell['master']['option_seq']		= '옵션번호';
				$cell['master']['goods_code']		= '상품코드';
				$cell['master']['goods_name']		= '상품명';
				$cell['master']['option_name']		= '옵션명';
				$cell['info']['supply_goods_name']	= '매입용상품명';
				$cell['info']['use_status']			= '사용여부';
				$cell['info']['main_trade_type']	= '주거래처';
				$cell['info']['trader_seq']			= '거래처코드';
				$cell['info']['trader_name']		= '거래처(통화)';				
				$cell['info']['supply_price']		= '발주가액';
				$cell['info']['use_supply_tax']		= '부가세';
				}	// 끝
			break;

			//2016.04.25 엑셀 양식 추가 pjw
			// 쇼핑몰별안전재고
			case 'default_shop':
				if	(true){	// 시작
				$cell['master']['goods_seq']		= '상품번호';
				$cell['master']['option_seq']		= '옵션번호';
				$cell['master']['goods_code']		= '상품코드';
				$cell['store']['goods_name']		= '상품명';
				$cell['store']['option_name']		= '옵션';
				$cell['store']['safe_stock']		= '안전재고';
				$cell['store']['consumer_price']	= '정가(KRW)';
				$cell['store']['price']				= '판매가액(KRW)';				
				$cell['store']['reserve']			= '적립금';
				}	// 끝
			break;
			
			//2016.04.25 엑셀 양식 추가 pjw
			// 창고별재고
			case 'default_stock':
				if	(true){	// 시작
				$this->set_warehouse('y');
				$cell['master']['goods_seq']		= '상품번호';
				$cell['master']['option_seq']		= '옵션번호';
				$cell['master']['goods_code']		= '상품코드';
				$cell['master']['option_code']		= '옵션코드';
				$cell['master']['scm_category']		= '분류코드';
				$cell['master']['goods_name']		= '상품명';
				$cell['master']['option_name']		= '옵션';
				$cell['warehouse']['stock']			= '수량';
				$cell['warehouse']['supply_price']	= '단가';
				$cell['warehouse']['total_price']	= '금액';
				}	// 끝
			break;

			// 거래처별 정산
			case 'traderaccount':
				if	(true){	// 시작
				$cell['trader_group']				= '그룹';
				$cell['trader_seq']					= '거래처코드';
				$cell['trader_name']				= '거래처명';
				$cell['carriedover']				= '기초정산';
				$cell['act_in_price']				= '재고매입';
				$cell['act_out_price']				= '지급';
				$cell['balance']					= '미지급 잔액';
				}	// 끝
			break;
		}

		$this->m_aCellList		= $cell;
	}

	// 복수열이 한Cell로 저장되는 컬럼에 대한 정의
	public function set_multiRow_cell(){
	}

	########## ↑↑↑↑↑ 기본 설정 배열 함수 ↑↑↑↑↑ ##########

	######### ↓↓↓↓↓ 업로드 후 로그 관련 함수 ↓↓↓↓↓ ##########
	// 업로드 시 성공 실패 로그 저장
	public function save_upload_log($upload_type = 'trader', $type = 'failed', $msg = ''){
		if	(!$this->SUCCESS_LOG_FILE){
			$log_file_name				= 'log_'.$upload_type.'_excel_' . date('YmdHis') . rand(0,9999) . '.txt';
			$this->SUCCESS_LOG_FILE		= fopen($this->EXCEL_LOG_PATH . '/success_' . $log_file_name, 'a+');
			$this->FAIL_LOG_FILE		= fopen($this->EXCEL_LOG_PATH . '/failed_' . $log_file_name, 'a+');

			$logParam['upload_type']			= $upload_type;
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
		$addWhere	.= " and upload_type = '".$sc['upload_type']."' ";
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
	######### ↑↑↑↑↑ 업로드 후 로그 관련 함수 ↑↑↑↑↑ ##########
}

/* End of file goodsexcel.php */
/* Location: ./app/models/goodsexcel */