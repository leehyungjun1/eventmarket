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

class excel_down extends admin_base {


	protected $pid;
	protected $queueID;
	protected $limittotalnum;
	protected $params;
	protected $fileExe;
	protected $writer;
	protected $nameDomain;
	protected $downPath;
	protected $regDate;
	protected $logPath;
	protected $logFile;
	public $aCategory = array(
		1 => "goods",
		2 => "order",
		3 => "member",
		4 => "export",
		5 => "scmgoods",
		6 => "membersale"
	);

	public $alimitCount = array(
		1 => 2000, //default 2000
		2 => 2000, //default 2000
		3 => 5000, //default 5000
		4 => 2000, //default 2000
		5 => 2000, //default 2000
		6 => 500, //default 500
	);

	public $aCategoryKR = array(
		1 => "판매상품",
		2 => "주문",
		3 => "회원",
		4 => "출고",
		5 => "재고상품",
		6 => "회원등급할인세트"
	);

	function __construct(){
		parent::__construct();
		$this->db->db_debug = false;

		$this->regDate		= date("Ymd");

		$arrSystem			= ($this->config_system) ? $this->config_system : config_load('system');
		$arr_sub_domain		= explode(".",$arrSystem['subDomain']);
		
		if($arrSystem['ssl_use'] == 1){
			$this->subDomain	= "https://".$arrSystem['subDomain'];
		} else {
			$this->subDomain	= "http://".$arrSystem['subDomain'];
		}
		
		$this->nameDomain	= sprintf("%s","{$arr_sub_domain['0']}"); //기본 도메인 셋팅
		$this->category		= end(explode("_", $this->uri->segment(3)));
		$this->categoryKey	= array_search($this->category, $this->aCategory);
		$this->categoryKR	= $this->aCategoryKR[$this->categoryKey];
		$this->limitCount	= $this->alimitCount[$this->categoryKey];
		$this->downPath		= ROOTPATH . "excel_download/" . $this->category . "/" . $this->regDate . "/";
		$this->isPrivate	= 'N'; // 개인정보 필드 유무

		//로그 남기기용 헬퍼
		$this->load->helper('file');
		$this->logPath = ROOTPATH . 'excel_download/log/' . $this->regDate . '/';
		$this->logFile = 'log_' . $this->nameDomain . '_' . $this->category . '.txt';

		if (!is_dir($this->logPath)) {
			mkdir($this->logPath);
			chmod($this->logPath, 0777);
			chown($this->logPath, 'nobody');
			chgrp($this->logPath, 'nobody');
		}

		if($this->categoryKey <= 0){
			echo "카테고리 인덱스를 찾을 수 없습니다.";
			exit;
		}
		if(in_array( $_POST['excel_type'] , array('select','select_barcode','search','search_barcode','all'))){ //직접 다운로드 프로세스
			//입점사 체크
			if(!$_POST['excel_provider_seq']){
				if( $this->is_excel_admin == "Y" ) {
					$_POST['excel_provider_seq'] = 1;
				} else {
					if($this->providerInfo){
						$_POST['excel_provider_seq'] = $this->providerInfo['provider_seq'];
					} else {
						echo "요청사 정보를 확인 할 수 없습니다.";
						exit;
					}
				}
			}

			if( $_POST['excel_provider_seq'] > 1 && !$_POST['provider_seq'] ){ //입점사는 무조건 입점사 상품만 
				$_POST['provider_seq'] = $_POST['excel_provider_seq'];
			} 

			if( $_POST['provider_seq'] <= 1 && $_POST['excel_provider_seq'] > 1 ){ //입점사가 본사 데이터 받으려고 할 경우 에러
				echo "다운로드 권한 없음";
				exit;
			}
			
		} else {
			//cronjob 프로세스
			$this->pid				= getmypid();
			
			//진행중 있으면 안함
			$query	 = "SELECT id FROM fm_queue WHERE category = ? AND state = 1";
			$queryDB = $this->db->query($query, $this->categoryKey);
			$res	 = $queryDB->result_array();
			if(!empty($res)){
				echo "대기중입니다.";
				write_file($this->logPath . $this->logFile, "[0][".date("Y-m-d H:i:s")."] ".$res[0]['id']." is Processing!\n", 'a');
				exit;
			}
			
			//대기큐 찾기
			$query	 = "SELECT id, context, limit_count FROM fm_queue WHERE category = ? AND state = 0 LIMIT 1";
			$queryDB = $this->db->query($query, $this->categoryKey);
			$res	 = $queryDB->result_array();
			if(empty($res)){
				echo "관련 데이터를 찾을 수 없습니다.";
				write_file($this->logPath . $this->logFile, "[0][".date("Y-m-d H:i:s")."] No Datas!\n", 'a');
				exit;
			}

			$this->queueID			= $res[0]['id'];
			$this->limitCount		= $res[0]['limit_count'] <= 0 ? $this->limitCount : $res[0]['limit_count'];
			$this->params			= unserialize($res[0]['context']);
			$this->params['is_zip'] = 'Y';
			$this->params['is_cron'] = 'Y';

			$this->db->where('id', $this->queueID);
			$this->db->update('fm_queue', array('state' => 1, 'pid' => $this->pid)); //상태 진행중으로 업데이트
			//$this->db->update('fm_queue', array('state' => 0, 'pid' => $this->pid)); //상태 진행중으로 업데이트 테스트
			
			write_file($this->logPath . $this->logFile, "[".$this->queueID."][".date("Y-m-d H:i:s")."] ----------Start!\n", 'a');
			
			ini_set("memory_limit",-1);
			set_time_limit(0);
			
			$funcName = "write_".$this->category;
			$this->{$funcName}();
		}
	}
	
	function doZipFile($fileList, $is_cron){
		$zipfile = $this->nameDomain . "_" . $this->category . "_list_" . date('YmdHis') . ".zip";
		$zippath = $this->downPath . $zipfile;

		$this->load->helper('download');
		$this->load->library('pclzip',array('p_zipname' => $zippath));

		//파일 체크
		foreach($fileList as $v){
			$filepath	= $this->downPath . $v;
			if	(file_exists($filepath) && is_file($filepath)){
				$downFileList[]	= $filepath;
			}
		}

		$pclZip = $this->pclzip->create($downFileList,
							PCLZIP_OPT_REMOVE_PATH, 
							$this->downPath);

		// 파일 삭제
		foreach($fileList as $v){
			$filepath	= $this->downPath . $v;
			if	(file_exists($filepath) && is_file($filepath)){
				@unlink($filepath);
			}
		}
		write_file($this->logPath . $this->logFile, '[' . $this->queueID . '][' . date('Y-m-d H:i:s') . '] is_cron : ' . $is_cron . "\n", 'a');
		write_file($this->logPath . $this->logFile, '[' . $this->queueID . '][' . date('Y-m-d H:i:s') . '] pclZip : ' . $pclZip . "\n", 'a');
		
		if ($is_cron == "N") {
			if($pclZip === 0){
				return false;
			} else {
				return $zipfile;
			}
		}
		
		if($pclZip === 0){
			write_file($this->logPath . $this->logFile, "[".$this->queueID."][".date("Y-m-d H:i:s")."] Fail Zipping Files!\n", 'a');
			exit;
		}

		write_file($this->logPath . $this->logFile, "[".$this->queueID."][".date("Y-m-d H:i:s")."] Success Zipping Files!\n", 'a');

		$this->db->close();
		$this->db->initialize();

		$this->db->where('id', $this->queueID);
		$com_date		= date('Y-m-d H:i:s');
		$expired_date	= date('Y-m-d H:i:s', strtotime('+7 days', strtotime($com_date)));
		$this->db->update('fm_queue', array(
				'state'			=> 2, 
				'file_name'		=> $this->regDate."/".$zipfile, 
				'com_date'		=> $com_date,
				'expired_date'	=> $expired_date
			)
		);

		write_file($this->logPath . $this->logFile, "[".$this->queueID."][".date("Y-m-d H:i:s")."] ----------Done!\n", 'a');
		exit;
	}

	function set_style(){
		$this->border = (new BorderBuilder())
			->setBorderRight(Color::BLACK, Border::WIDTH_THIN, Border::STYLE_SOLID)
			->setBorderBottom(Color::BLACK, Border::WIDTH_THIN, Border::STYLE_SOLID)
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
			
			$this->style_title_yellow = (new StyleBuilder())
			->setBorder($this->border)
			->setFontSize(10)
			->setFontColor(Color::BLACK)
			->setFontBold()
			->setShouldWrapText()
			->setBackgroundColor(Color::rgb(255, 255, 204))
			->build();
			
			$this->style_title_yellow_center = (new StyleBuilder())
			->setBorder($this->border)
			->setFontSize(10)
			->setFontColor(Color::BLACK)
			->setFontBold()
			->setShouldWrapText()
			->setTextAlign('center')
			->setBackgroundColor(Color::rgb(255, 255, 204))
			->build();
			
			$this->style_contents2 = (new StyleBuilder())
			->setBorder($this->border)
			->setFontSize(9)
			->setFontColor(Color::BLACK)
			->setShouldWrapText()
			->build();

		$this->style_red_text = (new StyleBuilder())
			->setBorder($this->border)
			->setFontSize(10)
			->setFontColor(Color::RED)
			->setShouldWrapText(false)
			->setBackgroundColor(Color::rgb(255, 255, 255))
			->build();

		$this->style_title_membersale = (new StyleBuilder())
			->setBorder($this->border)
			->setFontSize(10)
			->setFontColor(Color::WHITE)
			->setShouldWrapText(false)
			->setBackgroundColor(Color::rgb(128, 128, 128))
			->build();
	}

	function create_dirs(){
		if(!is_dir($this->downPath)){
			mkdir($this->downPath);
			chmod($this->downPath, 0777);
			chown($this->downPath, "nobody");
			chgrp($this->downPath, "nobody");
		}
	}

	function end_write($params, $filename, $fileList){
		$params['is_private'] = $this->isPrivate; //개인정보 필드 유무
		if($params['is_zip'] == "Y"){
			$filename = $this->doZipFile($fileList, $params['is_cron']);
		}
		
		if ($filename === false) {
			echo "파일 압축 에러. 에러가 지속 될 경우 관리자에게 문의 하세요.";
			exit;
		}
		
		$echoPath		= $this->category . "/" . $this->regDate . "/";
		$com_date		= date('Y-m-d H:i:s');
        $expired_date	= date('Y-m-d H:i:s', strtotime('+7 days', strtotime($com_date)));
        if($params['excel_provider_seq'] > 1){ //입점사 다운로드의 경우
			$manager_id = $this->providerInfo['provider_id'];
		} else {
			$manager_id = $this->managerInfo['manager_id'];
		}
		$setData = array(
			'id'			=> '',
			'provider_seq'	=> $params['excel_provider_seq'],
			'manager_id'	=> $manager_id,
			'category'		=> $this->categoryKey, //1:goods, 2:order, 3:member
			'excel_type'	=> $params['excel_type'],
			'context'		=> serialize($params),
			'count'			=> $params['searchcount'],
			'state'			=> 2,
			'file_name'		=> str_replace($this->category."/", "", $echoPath.$filename),
			'limit_count'	=> $this->limitCount,
			'reg_date'		=> $params['reg_date'],
			'com_date'		=> $com_date,
			'expired_date'	=> $expired_date
		);
		$this->db->insert('fm_queue', $setData);

		
        //관리자 로그 남기기
        $this->load->library('managerlog');
        $logInfo = array(
            'params' => array('excelcount' => $params['searchcount'], 'type' => $this->category, 'menu' => 'excel_download', 'callPage' => $this->input->post('callPage')),	
        );
        $this->managerlog->insertData($logInfo);
		
		echo $echoPath.$filename;
		exit;
	}

	function create_goods(){

		try
		{
			$this->params = $this->input->post();
	
			if(in_array($this->params['excel_type'],array('select', 'select_barcode')) ){
				$this->params['abs_goods_seq']	= $this->params['goods_seq'];
				$this->params['searchcount'] 	= count($this->params['goods_seq']);
			}
	
			if($this->params['searchcount'] <= 0){
				throw new Exception("다운로드 가능 한 ".$this->categoryKR."이 없습니다.");
			}
			
			if($this->goodsexcel->m_sAdminType == "S"){
				$this->params['excel_provider_seq'] = $this->providerInfo['provider_seq'];
			}
	
			if($this->params['excel_use'] == 'zoomoney'){
				$excel_type = $this->params['excel_type'] . '_' .$this->params['excel_use'] ;
			}else{
				$excel_type = $this->params['excel_type'];
			}
			$this->params['reg_date']	= date('Y-m-d H:i:s');
		
			if($this->params['excel_provider_seq'] > 1){ //입점사 다운로드의 경우
				$this->params['manager_id'] = $this->providerInfo['provider_id'];
			} else {
				$this->params['manager_id'] = $this->managerInfo['manager_id'];
			}

			if($this->params['searchcount'] <= $this->limitCount){
				$this->params['is_zip']		= 'N';
				$this->write_goods();
			} else {
				$setData = array(
					'id'			=> '',
					'provider_seq'	=> $this->params['excel_provider_seq'],
					'manager_id'	=> $this->params['manager_id'],
					'category'		=> $this->categoryKey, //1:goods, 2:order, 3:member
					'excel_type'	=> $excel_type, 
					'context'		=> serialize($this->params),
					'count'			=> $this->params['searchcount'],
					'state'			=> 0, //state 0:대기, 1:작업중, 2:완료
					'limit_count'	=> $this->limitCount,
					'reg_date'		=> $this->params['reg_date']
				);
				$this->db->insert('fm_queue', $setData);
				$queueID = $this->db->insert_id();
				$affect  = $this->db->affected_rows();
				if( $queueID > 0 || $affect > 0 ){
					$expectTime = ((ceil($this->params['searchcount']/$this->limitCount)) * 10) + 1200; 
					echo "엑셀 파일 생성 중 (예상 소요시간 : ".gmdate("H시 i분 s초", $expectTime).")\n파일 생성 후 ".$this->categoryKR." > 엑셀 다운로드 메뉴에서 다운로드 가능 합니다.";
				} else {
					throw new Exception("에러 발생.\n문제가 지속 될 경우 관리자에게 문의 바랍니다.");
				}
			}
		}
		catch (Exception $e) {
			$_message = "ERROR:: ";
			if(preg_match('/maximum number of characters allowed/', $e->getMessage())){
				$_message .= "셀(excel)에 허용되는 최대 문자수를 초과하였습니다.\r\n상품 상세설명/공통설명 등에 입력된 글자 수를 조정해주세요.\r\n";
			}
			$_message .= $e->getMessage();
			echo $_message;
		}
		
	}

	function write_goods(){
		$this->load->model('goodsmodel');
		$this->load->model('goodsexcel');

		$params = $this->params;
		// 다운로드 양식 데이터 추출 ( 추후 양식을 여러개 제공 시 seq 검색을 추가해야 한다. )
		$sc					= array();
		$sc['gb']			= 'GOODS';
		if($params['goodsKind'] == 'coupon') {
			$this->goodsexcel->m_sGoodsKind = 'C';
			$sc['gb'] = 'COUPON';
		}
		$sc['provider_seq']	= $params['excel_provider_seq'];

		$this->goodsexcel->set_cell_list(); //cellinfo 셋팅
		$this->goodsexcel->set_multiRow_cell();

		$this->create_dirs();

		if($params['excel_use'] == 'zoomoney'){
			$this->write_goods_zoomoney();
			exit;
		}
		
		$forms		= $this->goodsexcel->get_excel_form_data($sc);
		$excelForm	= $forms[0];
		if	(!$excelForm['form_seq'] || count($excelForm['item_arr']) < 1){
			echo "다운로드 양식 정보가 없습니다.\n다운로드 항목설정에서 양식을 생성해 주세요.";
			exit;
		}

		$cellNames	 = array();
		$columnNames = array();
		$columnWidths = array();
		foreach($excelForm['item_arr'] as $v){

			if	( isset($this->goodsexcel->m_aCellList[$v]) ) {
				$columnNames[]	= $this->goodsexcel->m_aCellList[$v];	
				$columnWidths[]	= $this->goodsexcel->m_aWidthInfo[$v];	
			}
			if($this->goodsexcel->m_aTableInfo[$v] == 'fm_goods'){
				$cellNames[]	= "C.".$this->goodsexcel->m_aFieldInfo[$v];
			}
		}

		
		// o2o 바코드 실물 다운로드 규격 추가
		$this->load->library('o2o/o2oinitlibrary');
		$this->o2oinitlibrary->init_admin_goods_barcode_download_form($columnNames, $cellNames, $params);

		$totalCount = $params['searchcount'];
		$loopCount	= ceil($totalCount / $this->limitCount);
		$fileList	= array();

		// 상품 기본 정보 추출
		$params['excel_spout']	= true;
		$params['goods_type']	= 'goods';

		for( $c = 1; $c <= 4; $c++){
			if($params['category'.$c]){
				$params['category']	= $params['category'.$c];
			}
		}

		$order_by = "oRDER BY C.goods_seq DESC";

		if ($params['orderby']) {
						
			// sql injection 체크
			$this->load->helper('sqlinjection');

			if (param_injection_checking($params['orderby']) === true) {
				echo '유효하지 않은 문자가 체크되었습니다.';
				exit;
			}

			$orderbyArr = explode("_", $params['orderby']);

			if( in_array($orderbyArr[0], array("asc", "desc")) ){
				$order_sort = array_shift($orderbyArr);
				$order_join = join("_", $orderbyArr);
				if($order_join == 'consumer_price' || $order_join == 'price'){
					$order_join = "default_".$order_join;
				}

				$order_by = "oRDER BY C.".$order_join." ".strtoupper($order_sort);
			}
		}

		for($i=0; $i<$loopCount; $i++) {
			$this->fileExe = 'xlsx';
			$this->writer = WriterFactory::create(Type::XLSX); // for XLSX files
			$this->writer->colWidth = $columnWidths;

			$this->set_style();

			$filename	= $this->nameDomain."_".$this->category."_list_".date('YmdHis')."_".str_pad( $i, 4, "0", STR_PAD_LEFT ).".".$this->fileExe;
			$filepath	= $this->downPath . $filename;
			$fileList[] = $filename;

			$this->writer->openToFile($filepath);
			$this->writer->addRowWithStyle($columnNames, $this->style_title);

			if( !in_array('C.goods_seq', $cellNames) ){
				$cellNames[] = 'C.goods_seq';
			}

			if( in_array('C.suboption_layout_group_num', $cellNames) 
				|| in_array('C.suboption_layout_group', $cellNames)){
				$cellNames[] = 'C.option_suboption_use';
			}

			if( in_array('C.relation_image_size', $cellNames) ){
				$cellNames[] = 'C.relation_count_w';
				$cellNames[] = 'C.relation_count_h';
			}

			// 필수옵션 있으면 옵션마일리지 항목 다운로드
			if(in_array("BBNNO1002", $excelForm['item_arr'])) {
				$cellNames[] = 'C.reserve_policy';
			}

			// 추가옵션 있으면 추가옵션마일리지 항목 다운로드
			if(in_array("BGNNS2012", $excelForm['item_arr'])) {
				$cellNames[] = 'C.sub_reserve_policy';
			}

			$sql = $this->goodsmodel->admin_goods_list_new($params);

			$offset	= $i * $this->limitCount;
			$sql .= " GROUP BY C.goods_seq " . $order_by . " LIMIT ".$offset.", ".$this->limitCount;

			$queryDB = mysqli_query($this->db->conn_id, "seLECT ".implode(",", $cellNames).",
				CASE WHEN C.goods_status = 'unsold' THEN '판매중지'
				WHEN C.goods_status = 'purchasing' THEN '재고확보중'
				WHEN C.goods_status = 'runout' THEN '품절'
				ELSE '정상' END AS goods_status_text, 
				OP.consumer_price, 
				OP.price" . $sql);

			// o2o 바코드 실물 다운로드 쿼리 추가
			$this->load->library('o2o/o2oinitlibrary');
			$this->o2oinitlibrary->init_admin_goods_barcode_download_sql($queryDB, $this->db->conn_id, $params);

			while($goods = mysqli_fetch_array($queryDB)) {
				unset($this->db->queries);
				unset($this->db->query_times);

				$goodsRow = array();
				if($this->goodsexcel->m_sServiceType == 'A'){
					$goods['provider_seq'] = $goods['provider_seq'];
				}
				// 상품 필수옵션 마일리지 정책
				$this->goodsexcel->m_sReservePolicy = $goods['reserve_policy'];
				// 상품 추가옵션 마일리지 정책
				$this->goodsexcel->m_sOptionReservePolicy = $goods['sub_reserve_policy'];
				if($goods){
					foreach($goods as $fld => $val){
						if($fld != 'provider_status'){
							$this->goodsexcel->get_download_goods_change_val($fld, $goods);
						}
					}
				}

				// 승인여부 처리는 마지막에 한다.
				if($goods['provider_status']){
					$this->goodsexcel->get_download_goods_change_val('provider_status', $goods);
				}
				
				$addGoodsData = $this->goodsexcel->get_excel_add_goods($goods['goods_seq'], $excelForm['item_arr']);
				if($addGoodsData) {	
					$goods = array_merge($goods, $addGoodsData);
					unset($addGoodsData);
				}

				$tmpGoodsRow = array();
				foreach($excelForm['item_arr'] as $x => $cellCode){
					if	( !isset($this->goodsexcel->m_aFieldInfo[$cellCode]) ) {
						continue;
					}

					$tmpGoodsRow[] = html_entity_decode($goods[$this->goodsexcel->m_aFieldInfo[$cellCode]], ENT_QUOTES, 'utf-8');
				}
				$goodsRow = $tmpGoodsRow;
				
				// o2o 바코드 실물 다운로드 행 추가
				$this->load->library('o2o/o2oinitlibrary');
				$this->o2oinitlibrary->init_admin_goods_barcode_download_row($goodsRow, $goods, $params);

				$this->writer->addRowWithStyle($goodsRow, $this->style_contents);
				unset($goods, $result, $goodsRow, $tmpGoodsRow);
			}

			$this->writer->close();
		} //파일 쓰기 종료

		$this->end_write($params, $filename, $fileList);
	}
	
	function write_goods_zoomoney()
	{
		$params = $this->params;
		
		$totalCount = $params['searchcount'];
		$loopCount	= ceil($totalCount / $this->limitCount);
		$fileList	= array();
		
		// 상품 기본 정보 추출
		$params['excel_spout']	= true;
		$params['goods_type']	= 'goods';
		
		for( $c = 1; $c <= 4; $c++){
			if($params['category'.$c]){
				$params['category']	= $params['category'.$c];
			}
		}
		
		$order_by = "oRDER BY C.goods_seq DESC";
				
		if	($params['orderby'])	{
					
			// sql injection 체크
			$this->load->helper('sqlinjection');
			
			if (param_injection_checking($params['orderby']) === true) {
				echo '유효하지 않은 문자가 체크되었습니다.';
				exit;
			}

			$orderbyArr = explode("_", $params['orderby']);
			
			if( in_array($orderbyArr[0], array("asc", "desc")) ){
				$order_sort = array_shift($orderbyArr);
				$order_join = join("_", $orderbyArr);
				if($order_join == 'consumer_price' || $order_join == 'price'){
					$order_join = "default_".$order_join;
				}
				
				$order_by = "oRDER BY C.".$order_join." ".strtoupper($order_sort);
			}
		}
		
		
		$columnCodes= array("goods_seq", "goods_code", "category_path", "category", "goods_name", "price", "tot_stock", "max_purchase", "min_purchase",
			"origin", "origin_gmarket_auction", "manufacturer", "importer", "view", "image1", "image2", "image3", "image4",
			"image5", "contents", "ESM 추가구성 상세설명", "ESM 광고홍보 상세설명", "zoomoney_type", "option_title", "option_detail",
			"option_stock", "inputoption", "suboption_title", "suboption_detail", "brand", "model_name", "tax", "adult_goods", "제조일",
			"feed_condition", "유효일자", "홍보문구", "원가", "소비자가", "도서공연비 소득공제 여부", "goods_sub_info", "goods_sub_info_ref",
			"값1", "값2", "값3", "값4", "값5", "값6", "값7", "값8", "값9", "값10", "값11", "값12", "값13", "값14", "값15", "값16",
			"값17", "값18", "값19", "값20", "값21", "값22", "값23", "값24", "값25", "값26", "값27", "값28", "값29", "기본정보 오류메시지" );
		
		$columnNames = array("원본번호*", "판매자 관리코드", "폴더명", "카테고리 번호*", "상품명*", "판매가*", "수량*", "최대구매수량", "최소구매수량",
			"원산지*", "G마켓,옥션 원산지 유형", "제조사", "수입사", "목록 이미지*", "이미지1(대표/기본이미지)*", "이미지2", "이미지3", "이미지4",
			"이미지5", "상세설명*", "ESM 추가구성 상세설명", "ESM 광고홍보 상세설명", "선택사항 타입", "선택사항 옵션명", "선택사항 상세정보",
			"선택사항 재고 사용여부", "작성형 선택사항", "추가구성 옵션명", "추가구성 상세정보", "브랜드", "모델명", "과세여부", "나이제한", "제조일",
			"상품상태", "유효일자", "홍보문구", "원가", "소비자가", "도서공연비 소득공제 여부", "요약정보 상품군 코드*", "요약정보 전항목 상세설명 참조",
			"값1", "값2", "값3", "값4", "값5", "값6", "값7", "값8", "값9", "값10", "값11", "값12", "값13", "값14", "값15", "값16",
			"값17", "값18", "값19", "값20", "값21", "값22", "값23", "값24", "값25", "값26", "값27", "값28", "값29", "기본정보 오류메시지" );
		
		for($i=0; $i<$loopCount; $i++) {
			// 상품 기본 정보 추출
			$params['excel_spout']	= true;
			$params['goods_type']	= 'goods';
			
			for( $c = 1; $c <= 4; $c++){
				if($params['category'.$c]){
					$params['category']	= $params['category'.$c];
				}
			}
			
			$order_by = "oRDER BY C.goods_seq DESC";
			
			if	($params['orderby'])	{
			
				// sql injection 체크
				$this->load->helper('sqlinjection');
				
				if (param_injection_checking($params['orderby']) === true) {
					echo '유효하지 않은 문자가 체크되었습니다.';
					exit;
				}

				$orderbyArr = explode("_", $params['orderby']);				

				if( in_array($orderbyArr[0], array("asc", "desc")) ){
					$order_sort = array_shift($orderbyArr);
					$order_join = join("_", $orderbyArr);
					if($order_join == 'consumer_price' || $order_join == 'price'){
						$order_join = "default_".$order_join;
					}
					
					$order_by = "oRDER BY C.".$order_join." ".strtoupper($order_sort);
				}
			}
			
			$sql = $this->goodsmodel->admin_goods_list_new($params);
			
			$offset	= $i * $this->limitCount;
			$sql .= " GROUP BY C.goods_seq " . $order_by . " LIMIT ".$offset.", ".$this->limitCount;
			
			$queryDB = mysqli_query($this->db->conn_id, "seLECT C.*,
				CASE WHEN C.goods_status = 'unsold' THEN '판매중지'
				WHEN C.goods_status = 'purchasing' THEN '재고확보중'
				WHEN C.goods_status = 'runout' THEN '품절'
				ELSE '정상' END AS goods_status_text,
				OP.consumer_price,
				OP.price" . $sql);
			
			$filename	= $this->nameDomain."_".$this->category."_list_".date('YmdHis')."_".str_pad( $i, 4, "0", STR_PAD_LEFT ).".xlsx";
			$filepath	= $this->downPath . $filename;
			$fileList[] = $filename;
			
			$this->writer = WriterFactory::create(Type::XLSX); // for XLSX files
			$this->set_style();
			
			//set column width
			$this->writer->colWidth[] = array(21, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30,
				30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30,
				30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30);
			$this->writer->colWidth[] = array(25, 32, 32, 32, 32, 32, 32, 32, 32, 32, 32, 32, 32, 32, 32, 32, 32, 32, 32, 32,
				32, 32, 32, 32, 32, 32, 32, 32, 32, 32, 32, 32, 32, 32, 32, 36, 32, 32, 32, 32, 32, 32, 32, 32, 32, 32, 32, 32);
			$this->writer->colWidth[] = array(20, 30, 30, 30, 30);
			$this->writer->colWidth[] = array(10, 32, 30, 22, 22, 22, 22, 22, 22, 22, 22, 22, 22, 22, 22, 22, 22, 22, 22, 22,
				22, 22, 22, 22, 22, 22, 22, 22, 22, 22, 22, 22);
			$this->writer->colWidth[] = array(10, 32, 12, 12, 12, 12, 12, 12, 12, 12, 12, 12);
			
			$this->writer->openToFile($filepath);
			
			$SheetCategory = $this->writer->getCurrentSheet();
			$SheetCategory->setName('기본정보');
			$this->writer->addRowWithStyle($columnNames, $this->style_title_yellow_center);
			$this->writer->addRowWithStyle($this->zoomoney_infos('basic'), $this->style_contents2);
			
			$goods_seqs = array();
			$goods_keywords = array();
			$seqKey = 0;
			while($goods = mysqli_fetch_array($queryDB)) {
				unset($this->db->queries);
				unset($this->db->query_times);
				
				$goods_seqs[$seqKey] = $goods['goods_seq'];
				
				$goodsRow = array();
				$this->goodsexcel->m_sReservePolicy = $goods['reserve_policy'];
				if($goods){
					foreach($goods as $fld => $val){
						if($fld != 'provider_status'){
							$this->goodsexcel->get_download_goods_change_val($fld, $goods);
						}
					}
				}
				
				$category_path = $this->goodsexcel->_download_exception_category_title($goods['goods_seq']);
				$category_path = explode("\n", $category_path);
				$goods['category_path'] = array_pop($category_path);
				$goods['category'] = '999999999';
				
				//이미지 1은 무조건 필수 이므로 없을 경우 에러 발생
				$images = $this->goodsexcel->_download_exception_image($goods['goods_seq']);
				if(count($images) > 0){
					$img_view = explode("&#10;", $images['view']);
					
					if(substr( $images['view'], 0, 12 ) === "/data/goods/"){
						$goods['view']	= $this->subDomain.$img_view[0];
					} else {
						$goods['view']	= $img_view[0];
					}
					$goods['image1'] = $goods['view'];
					
					if($img_view[1] && substr( $img_view[1], 0, 12 ) === "/data/goods/"){
						$goods['image2'] = $this->subDomain.$img_view[1];
					} else {
						$goods['image2'] = $img_view[1];
					}
					
					if($img_view[2] && substr( $img_view[2], 0, 12 ) === "/data/goods/"){
						$goods['image3'] = $this->subDomain.$img_view[2];
					} else {
						$goods['image3'] = $img_view[2];
					}
					
					if($img_view[3] && substr( $img_view[3], 0, 12 ) === "/data/goods/"){
						$goods['image4'] = $this->subDomain.$img_view[3];
					} else {
						$goods['image4'] = $img_view[3];
					}
					
					if($img_view[4] && substr( $img_view[4], 0, 12 ) === "/data/goods/"){
						$goods['image5'] = $this->subDomain.$img_view[4];
					} else {
						$goods['image5'] = $img_view[4];
					}
				} else {
					echo "상품 목록 중 사진이 등록되지 않은 상품이 있습니다.\n최소 1개 이상의 상품 사진을 등록 해주세요.";
					exit;
				}
				
				if($goods['max_purchase_limit'] == 'limit'){
					$goods['max_purchase'] = "Y*A*".$goods['max_purchase_ea'];
				} else {
					$goods['max_purchase'] = "";
				}
				
				if($goods['min_purchase_limit'] == 'limit'){
					$goods['min_purchase'] = "Y*".$goods['min_purchase_ea'];
				} else {
					$goods['min_purchase'] = "";
				}
				
				$goods['origin'] = '기타/상세설명참조';
				
				$addinfos = $this->goodsexcel->_download_exception_addition($goods['goods_seq']);
				$addinfo = explode("^", $addinfos);
				$addinfo = array_filter($addinfo);
				
				if(count($addinfo) > 0){
					foreach($addinfo as $v){
						$info_arr = explode("=", $v);
						if (strpos($info_arr[0], '모델명') !== false) {
							$goods['model_name'] = $info_arr[1];
						}
						
						if (strpos($info_arr[0], '제조사') !== false) {
							$goods['manufacturer'] = $info_arr[1];
						}
						
						if (strpos($info_arr[0], '수입사') !== false) {
							$goods['importer'] = $info_arr[1];
						}
					}
				}
				
				if($goods['goods_sub_info'] <= 0){
					$goods['goods_sub_info'] = '35';
				}
				$goods['goods_sub_info_ref'] = 'Y';
				
				//신선,가공일 경우
				if( $goods['goods_sub_info'] == 19 || $goods['goods_sub_info'] == 20 ){
					$goods['origin_gmarket_auction'] = $goods['origin'];
				} else {
					$goods['origin_gmarket_auction'] = "";
				}
				
				$option_details = array();
				$option_codes = array();
				
				$option_titles1 = array();
				$option_titles2 = array();
				$option_titles3 = array();
				$option_titles4 = array();
				$option_titles5 = array();
				$option_code1 = array();
				$option_code2 = array();
				$option_code3 = array();
				$option_code4 = array();
				$option_code5 = array();
				
				$options = $this->goodsexcel->_download_exception_option($goods['goods_seq']);
				if($options['option_title1']){
					$goods['option_title'] = $options['option_title1'];
					$option_titles1 = explode('&#10;', $options['option1']);
					$option_code1 = explode('&#10;', $options['optioncode1']);
					
					foreach($option_titles1 as $k => $v){
						$v = explode('=', $v);
						$option_details[$k] = $v[0]."*";
					}
					
					foreach($option_code1 as $k => $v){
						$option_codes[$k] = $v;
					}
					
					if($options['option_title2']){
						$goods['option_title'] .= "\n".$options['option_title2'];
						$option_titles2 = explode('&#10;', $options['option2']);
						$option_code2 = explode('&#10;', $options['optioncode2']);
						
						foreach($option_titles2 as $k => $v){
							$v = explode('=', $v);
							$option_details[$k] .= $v[0]."*";
						}
						
						foreach($option_code2 as $k => $v){
							$option_codes[$k] .= $v;
						}
					}
					
					if($options['option_title3']){
						$goods['option_title'] .= "\n".$options['option_title3'];
						$option_titles3 = explode('&#10;', $options['option3']);
						
						foreach($option_titles3 as $k => $v){
							$v = explode('=', $v);
							$option_details[$k] .= $v[0]."*";
						}
						
						foreach($option_code3 as $k => $v){
							$option_codes[$k] .= $v;
						}
					}
					
					if($options['option_title4']){
						$goods['option_title'] .= "\n".$options['option_title4'];
						$option_titles4 = explode('&#10;', $options['option4']);
						
						foreach($option_titles4 as $k => $v){
							$v = explode('=', $v);
							$option_details[$k] .= $v[0]."*";
						}
						
						foreach($option_code4 as $k => $v){
							$option_codes[$k] .= $v;
						}
					}
					
					if($options['option_title5']){
						$goods['option_title'] .= "\n".$options['option_title5'];
						$option_titles5 = explode('&#10;', $options['option5']);
						
						foreach($option_titles5 as $k => $v){
							$v = explode('=', $v);
							$option_details[$k] .= $v[0]."*";
						}
						
						foreach($option_code5 as $k => $v){
							$option_codes[$k] .= $v;
						}
					}
				}
				
				if(count($option_details) > 0){
					$goods['zoomoney_type'] = '조합형';
					
					$prices = explode('&#10;', $options['price']);
					$stocks = explode('&#10;', $options['stock']);
					$views = explode('&#10;', $options['option_view']);
					
					foreach($option_details as $k => $v){
						if($stocks[$k] <= 0){
							$stocks[$k] = '999';
						}
						
						$option_details[$k] = $v."*".intval($prices[$k]-$goods['price'])."*".$stocks[$k]."*";
						
						if($views[$k] == '노출'){
							$option_details[$k] .= "Y*N";
						} else {
							$option_details[$k] .= "N*N";
						}
						
						if ($goods['goods_code'] && $option_codes[$k]){
							$option_details[$k] .= "*".$goods['goods_code'].$option_codes[$k];
						} else if ($goods['goods_code'] && !$option_codes[$k]){
							$option_details[$k] .= "*".$goods['goods_code'];
						} else {
							$option_details[$k] .= "*".$option_codes[$k];
						}
					}
					
					$goods['option_detail'] = implode("\n", $option_details);
				}
				
				$runout_policy = explode("=", $goods['runout_policy']);
				if($runout_policy[0] == '통합정책'){
					$cfg_order = config_load('order');
					if($cfg_order['runout'] == 'unlimited'){
						$goods['option_stock'] = 'N';
					} else {
						$goods['option_stock'] = 'Y';
					}
				} else {
					if($runout_policy[1] == '재고무관'){
						$goods['option_stock'] = 'N';
					} else {
						$goods['option_stock'] = 'Y';
					}
				}
				
				$inputoptions = $this->goodsexcel->_download_exception_input($goods['goods_seq']);
				if($inputoptions){
					$goods['inputoption'] = $inputoptions['input_name'];
				} else {
					$goods['inputoption'] = '';
				}
				
				$suboptions = $this->goodsexcel->_download_exception_suboption($goods['goods_seq']);
				if($suboptions){
					$suboption_titles = explode("&#10;", $suboptions['suboption_title']);
					$suboption_value = explode("&#10;", $suboptions['suboption']);
					$suboption_price = explode("&#10;", $suboptions['sub_price']);
					$suboption_stock = explode("&#10;", $suboptions['sub_stock']);
					$suboption_view = explode("&#10;", $suboptions['sub_option_view']);
					
					$before = "";
					$suboption_title = "";
					$i = 0;
					foreach($suboption_titles as $k => $v){
						$v = str_replace("=", "", $v);
						
						if($before != $v){
							$suboption_title[$i] = $v;
							$i++;
						}
						
						if($suboption_stock[$k] <= 0){
							$suboption_stock[$k] = '999';
						}
						$suboption_value[$k] = str_replace("=", "", $suboption_value[$k]);
						
						$suboption_detail[$k] = $v."*".$suboption_value[$k]."**".$suboption_price[$k]."*".$suboption_stock[$k]."*";
						$before = $v;
						
						if($suboption_view[$k] == '노출'){
							$suboption_detail[$k] .= "Y";
						} else {
							$suboption_detail[$k] .= "N";
						}
					}
					
					$goods['suboption_title'] = join("\n", $suboption_title);
					$goods['suboption_detail'] = join("\n", $suboption_detail);
				} else {
					$goods['suboption_title'] = "";
					$goods['suboption_detail'] = "";
				}
				
				$goods['brand'] = $this->goodsexcel->_download_exception_brand_title($goods['goods_seq']);
				$goods['brand'] = explode("\n", $goods['brand']);
				$goods['brand'] = array_pop($goods['brand']);
				
				if($goods['tax'] == '비과세'){
					$goods['tax'] = '면세';
				} else {
					$goods['tax'] = '과세';
				}
				
				if($goods['adult_goods'] == '아니요'){
					$goods['adult_goods'] = '전체이용가';
				} else {
					$goods['adult_goods'] = '18세이용가';
				}
				
				if($goods['tot_stock'] <= 0){
					$goods['tot_stock'] = '99999';
				}
				
				$tmpGoodsRow = array();
				foreach($columnCodes as $cellCode){
					if(!is_null($goods[$cellCode])){
						$tmpGoodsRow[] = html_entity_decode($goods[$cellCode], ENT_QUOTES, 'utf-8');
					} else {
						$tmpGoodsRow[] = "";
					}
				}
				
				$goodsRow = $tmpGoodsRow;
				
				$keywords = explode("^", $goods['keyword']);
				$keywords = array_filter($keywords);
				$keywords = array_unique($keywords);
				if(count($keywords) > 40){
					$keywords = array_slice($keywords, 0, 40);
				}
				
				$goods_keywords[$seqKey] = $keywords;
				
				$this->writer->addRowWithStyle($goodsRow, $this->style_contents2);
				$seqKey++;
				unset($goods, $result, $goodsRow, $tmpGoodsRow);
			}
			
			//확장정보
			$SheetExtra = $this->writer->addNewSheetAndMakeItCurrent();
			$SheetExtra = $this->writer->getCurrentSheet();
			$SheetExtra->setName('확장정보');
			
			$this->writer->addRowWithStyle(array('원본번호*', '옥션 카테고리번호', 'G마켓 카테고리번호', '11번가 카테고리번호', '인터파크 카테고리번호', '스마트스토어 카테고리번호', 'ESM2.0 카테고리번호', '쿠팡 카테고리번호', '티몬 카테고리번호', '위메프2.0 카테고리번호', '롯데ON 카테고리번호', '위메프2.0 담당MD', '옥션 사은품', 'G마켓 사은품', 'ESM2.0 사은품', '도서 ISBN 코드명', '인터파크 도서 정가', '스마트스토어 태그', '스마트스토어 전용 상품명 사용 여부', '스마트스토어 전용 상품명', '쿠팡 검색어', '병행수입여부 및 수입신고필증', '롯데ON 수입형태', '인보이스', '기타 구비서류', '쿠팡 옵션 할인율기준가', '쿠팡 옵션 대표이미지', '쿠팡 옵션 상세설명', '쿠팡 옵션 바코드', '쿠팡 옵션 인증정보', '쿠팡 옵션 모델번호', '쿠팡 옵션 검색옵션명', '쿠팡 옵션 검색옵션값', '11번가 상품속성', '위메프2.0 상품속성 라벨', '인터파크, 티몬, 위메프2.0, 롯데ON 검색키워드', '위메프2.0 제휴채널 검색키워드', '티몬 법적허가 및 신고대상 상품', '옥션 인증정보', 'G마켓 인증정보', '11번가 인증정보', '인터파크 인증정보', '스마트스토어 인증정보', 'ESM2.0 인증정보', '티몬 인증정보', '위메프2.0 인증정보', '롯데ON 인증정보', '확장정보 오류메시지'), $this->style_title_yellow_center);
			$this->writer->addRowWithStyle($this->zoomoney_infos('extended'), $this->style_contents2);
			foreach($goods_seqs as $k => $seq){
				$this->writer->addRowWithStyle(array($seq, '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', implode(",", array_slice($goods_keywords[$k], 0, 10)), '', '', implode(",", array_slice($goods_keywords[$k], 0, 40)), '', '', '', '', '', '', '', '', '', '', '', '', '', '', implode(",", array_slice($goods_keywords[$k], 0, 10))), $this->style_contents2);
			}
			unset($goods_seqs);
			
			$this->write_goods_zoomoney_add_sheets();
			$this->writer->close();
		}
		
		$this->end_write($params, $filename, $fileList);
	}
	
	function zoomoney_infos($type)
	{
		switch ($type){
			case 'basic' :
				$infos = array(
				"[입력방식]\n숫자(0은 제외)\n\n▶0을 제외한 숫자를 임의로 입력하면 원본상품 등록 시 자동으로 규칙에 맞는 번호로 변경되어 등록\n\n▶중복값 입력 불가\n\n▶기본정보 시트에 있는 상품의 원본번호와 확장정보 시트에 있는 동일 상품의 원본번호는 같아야 함.\n\n▶상품정보는 3행부터 입력(2행의 예시는 삭제하지 마세요.)",
				"[입력방식]\n한글,영문,숫자",
				"[입력방식]\n한글,숫자,입력\n\n▶공란\n미반영\n\n▶원본상품 엑셀수정 시 폴더명 입력 및 수정 미지원\n\n▶다중 등록\n폴더명을 쉼표(,)로 구분하여 입력",
				"[입력방식]\n이셀러스 표준카테고리 탭에서 확인 및 입력\n\n▶셀서식을 텍스트로 설정 후 입력\n(설정방법 : 해당 열 전체선택 > 마우스우클릭 > 셀 서식 > 텍스트 > 확인)",
				"•옥션,G마켓 \n한글 25자, 영문/숫자 50자 이내\n\n•11번가\n한글 25자, 영문/숫자 50자 이내\n\n•인터파크\n한글 25자, 영문/숫자 50자 이내\n\n•스마트스토어\n한글50자,영문/숫자 100자 이내 \n\n•NH마켓\n한글 25자, 영문/숫자 50자 이내\n\n•쿠팡\n한글,영문,숫자 150자 이내\n\n•티몬\n한글60자, 영문/숫자120자 이내 \n\n•위메프2.0\n한글,영문,숫자 70자 이내\n\n•롯데ON\n한글 33자, 영문/숫자 100자 이내",
				"[입력방식]\n숫자\n\n•11번가\n10원 이상, 40억 미만\n\n•옥션,지마켓,쿠팡\n10원 이상\n\n•위메프2.0, 롯데ON\n0이상 10억 미만\n\n•위메프2.0\n0이상 10억 미만\n기본은 위메프가로 등록 (온라인판매가는 '세트-부가설정'에서 입력)",
				"[입력방식]\n숫자\n\n•옥션,G마켓,인터파크,위메프2.0,롯데ON\n최대 99999개\n\n•11번가\n1억개 미만\n\n▶선택사항 재고수량 총합으로 반영",
				"[입력방식]\n제한유무*제한유형*제한개수*제한기간\n\n▶제한유무\nY : 제한있음\nN 또는 공란 : 제한없음\n\n▶제한유형\nA : 최대구매제한\nB : 기간제한\nC : 1회제한\n\n•11번가, 위메프2.0\n최대구매제한 설정 시 1회제한으로 반영\n\n•스마트스토어\n최대구매제한, 기간제한 설정 시 1회 구매시 최대로 반영\n\n\n\n예시1)1명이 1회 구매 시 최대 3개 \nY*C*3\n\n예시2)1명이 1일 동안 최대 3개 \nY*B*3*1",
				"[입력방식]\n제한유무*제한개수",
				"[입력방식]\n국산/수입/기타\n\n▶국산\n국산/시,도명/구,시,군명\n▶수입\n수입/대륙명/국가명\n▶기타\n기타\n\n•롯데ON\n국산 : 한국으로 반영\n수입 : 국가만 반영\n\n\n\n\n\n\n예시) 국산인 경우 \n국산/서울시/금천구\n\n예시) 수입인 경우\n수입/유럽/프랑스\n\n예시) 기타인 경우\n기타",
				"[입력방식]\n농산물/수산물/가공품/상세설명참조\n\n▶신선식품/가공품일 경우 필수 입력\n\n▶공란\n의무표시 대상 아님으로 반영",
				"[입력방식]\n직접입력\n\n•쿠팡\n필수 입력",
				"[입력방식]\n직접입력\n\n•스마트스토어,롯데ON\n원산지 수입일 경우 필수 입력",
				"[입력방식]\n웹이미지 URL/로컬이미지 경로\n\n▶로컬경로 이미지는 이셀러스 이미지 서버에 저장 후 URL로 노출\n\n•옥션,지마켓,인터파크,스마트스토어,위메프2.0,롯데ON\n미반영\n\n•11번가\n목록이미지 반영\n\n•쿠팡, 위메프2.0\n대표이미지 반영\n\n•티몬\n딜이미지 반영\n\n\n\n\n예시) 웹이미지 url을 입력할 경우 \nhttp://z4img1.esellers.co.kr/esellers/이미지1.jpg\n\n예시) 로컬이미지 경로 입력할 경우\nC:\Users\이셀러스\상품이미지\이미지1.jpg",
				"[입력방식]\n웹이미지 URL/로컬이미지 경로\n\n▶로컬경로 이미지는 이셀러스 이미지 서버에 저장 후 URL로 노출\n\n•옥션,지마켓,\n기본이미지 반영\n\n•11번가,인터파크,스마트스토어\n대표이미지 반영\n\n•쿠팡, 티몬\n추가이미지1 반영\n\n•위메프2.0\n리스팅 이미지 반영\n\n•롯데ON\n대표이미지 반영",
				"[입력방식]\n웹이미지 URL/로컬이미지 경로\n\n▶로컬경로 이미지는 이셀러스 이미지 서버에 저장 후 URL로 노출\n\n•옥션,지마켓,11번가,인터파크,스마트스토어,위메프2.0,롯데ON\n추가이미지1 반영\n\n•쿠팡,티몬\n추가이미지2 반영",
				"[입력방식]\n웹이미지 URL/로컬이미지 경로\n\n▶로컬경로 이미지는 이셀러스 이미지 서버에 저장 후 URL로 노출\n\n•옥션,지마켓,11번가,인터파크,스마트스토어,위메프2.0\n추가이미지2 반영\n\n•쿠팡,티몬\n추가이미지3 반영",
				"[입력방식]\n웹이미지 URL/로컬이미지 경로\n\n▶로컬경로 이미지는 이셀러스 이미지 서버에 저장 후 URL로 노출\n\n•옥션,지마켓,11번가,인터파크,스마트스토어\n추가이미지3 반영\n\n•쿠팡, 티몬\n추가이미지4 반영\n\n•위메프2.0\n미반영",
				"[입력방식]\n웹이미지 URL/로컬이미지 경로\n\n▶로컬경로 이미지는 이셀러스 이미지 서버에 저장 후 URL로 노출\n\n•옥션,지마켓,스마트스토어\n추가이미지4 반영\n\n•11번가,인터파크,위메프2.0\n미반영\n\n•쿠팡,티몬\n추가이미지5 반영\n\n*롯데ON\n추가이미지4 반영\n(신상품을 제외한 상품 상태일 경우 상품 상태 이미지로 반영)",
				"[입력방식]\nHTML\n\n▶외부링크 사이트 정책으로 입력 제한\n\n•옥션, G마켓\nscript, object 태그 사용 불가\n\n•11번가\n자바스크립트 등록 불가",
				"[입력방식]\nHTML",
				"[입력방식]\nHTML",
				"[입력방법]\n조합형/독립형\n\n▶독립형, 조합형 동시 사용 불가\n\n▶독립형은 옥션,지마켓,11번가,스마트스토어만 지원 가능\n\n•11번가, 스마트스토어
독립형 사용 시 사이트 정책으로 인해 추가금액 미지원",
"[입력방법]\n옵션명 직접입력\n\n▶옵션명이 여러 개일 경우 줄바꿈(Alt+Enter)으로 구분\n\n옵션명1\n옵션명2\n\n•옥션, G마켓\n조합형 2단계 입력 필수\n옵션명은 최대 5개까지 지원\n\n•위메프2.0, 인터파크\n최대 2단계까지 등록 가능 ",
"[입력방법]\n독립형)옵션명*옵션값**추가금액*수량*노출여부(Y/N)*이미지URL*판매자관리코드\n조합형)옵션명1의 옵션값*옵션명2의 옵션값**추가금액*수량*노출여부(Y/N)*이미지URL*판매자관리코드*용량\n\n▶선택사항 이미지는 웹이미지만 등록 가능\n▶이미지URL, 판매자관리코드, 용량은 선택입력 사항으로 모든 선택입력 사항이 없을 경우 노출여부 까지만 입력\n▶선택입력 사항 중 일부값만 미입력 시 해당 위치에 'N'으로 대체 입력\n▶판매자관리코드는 20자, 용량은 9자까지 입력 가능\n▶용량은 롯데ON만 반영\n▶옵션정보 입력 시 줄바꿈(Alt+Enter)으로 입력\n\n예시)독립형_옵션명(옵션값)이 상의사이즈(M,L) 하의사이즈(M,L) 등록 할 경우\n상의사이즈*M**0*999*Y*http://esellers.esellersimg.co.kr/선택사항1.JPG\n상의사이즈*L**1000*999*Y*http://esellers.esellersimg.co.kr/선택사항1.JPG\n하의사이즈*M**0*999*Y*http://esellers.esellersimg.co.kr/선택사항1.JPG\n하의사이즈*L**1000*999*Y*http://esellers.esellersimg.co.kr/선택사항1.JPG\n\n예시)조합형_옵션명(옵션값)이 색상(검정,노랑) 사이즈(90,100) 등록 할 경우
검정*90**1000*999*Y*http://esellers.esellersimg.co.kr/선택사항1.JPG\n검정*100**2000*999*Y*http://esellers.esellersimg.co.kr/선택사항1.JPG\n노랑*90**1000*999*Y*http://esellers.esellersimg.co.kr/선택사항2.JPG\n노랑*100**2000*999*Y*http://esellers.esellersimg.co.kr/선택사항2.JPG\n\n예시)조합형_용량 등록 할 경우\n검정*90**1000*999*Y*http://esellers.esellersimg.co.kr/선택사항1.JPG*N*100\n검정*100**2000*999*Y*http://esellers.esellersimg.co.kr/선택사항1.JPG*1234*0\n노랑*90**1000*999*Y*http://esellers.esellersimg.co.kr/선택사항2.JPG*N*50\n노랑*100**2000*999*Y*http://esellers.esellersimg.co.kr/선택사항2.JPG*N*0",
"[입력방식]\nY/N\n\n▶Y\n재고 사용함 \n\n▶N\n재고 사용안함\n\n▶공란은 재고 사용으로 반영",
"[입력방식]\n직접입력\n\n▶선택사항이 여러 개일 경우 줄바꿈(Alt+Enter)으로 구분\n\n•옥션,G마켓,11번가,스마트스토어,롯데ON\n최대 5개\n\n•인터파크는 25자 내외로 1칸에 동시 등록\n\n•위메프2.0\n최대 2개\n\n\n\n\n\n\n\n\n\n예시)\n반지 이니셜 입력\n사은품 번호 입력",
"[입력방식]\n직접입력\n\n▶옵션명이 여러 개일 경우 줄바꿈(Alt+Enter)으로 구분\n\n옵션명1\n옵션명2",
"[입력방식]\n추가품목명*품목옵션값**추가금액*수량*노출여부(Y/N)\n\n▶추가구성 정보 입력 시 줄바꿈(Alt+Enter)으로 입력\n\n•옥션,G마켓 \n판매가 대비 50%까지 입력 가능",
"[입력방식]\n직접입력\n\n•쿠팡,티몬\n필수 등록",
"[입력방식]\n직접입력\n\n•스마트스토어\n모델명에 따라 카테고리/제조사/브랜드/속성 정보 변경",
"[입력방법]\n과세/면세/영세\n\n▶공란\n과세로 반영",
"[입력방식]\n전체이용가/12세이용가/15세이용가/18세이용가\n\n▶공란\n전체이용가로 반영",
"[입력방식]\nYYYY-MM-DD (년-월-일)\n\n•옥션, G마켓\n농수축산물/식품/화장품/분유/이유식 카테고리일 경우, 제조일 또는 유효일 중 하나 필수 입력\n\n•11번가\n중고판매 시 필수입력",
"[입력방식]\n신상품/중고상품/재고상품/\n전시상품/반품상품/반품(박스훼손)상품/리퍼상품/직접제작상품\n희귀소장품/스크래치상품/수집전/\n주문제작상품\n\n▶공란\n신상품으로 등록",
"[입력방식]\nYYYY-MM-DD (년-월-일)\n\n▶남은 일수로 변경 후 반영",
"[입력방식]\n한글/영문/숫자\n\n▶특수문자 입력 불가\n\n•티몬\n20자, 필수입력",
"[입력방식]\n숫자\n\n▶사이트 미반영, 주머니 내 관리용으로 입력",
"[입력방식]\n숫자\n\n▶사이트 미반영, 주머니 내 관리용으로 입력",
"[입력방식]\nY/N\n\n▶Y\n소득공제 대상\n\n▶N 또는 공란\n소득공제 대상아님",
"[입력방식]\n숫자\n\n▶요약정보설명 탭에서 숫자 확인 후 입력",
"[입력방식]\nY/N\n\n▶Y\n전항목 상세설명 참조로 반영\n\n▶N\n직접입력\n\n▶상품군에 따라 전항목 상세설명 참조 등록 불가 요약정보가 존재",
"[입력방식]\n직접입력\n\n▶요약정보설명 탭 내 값1 항목 입력\n\n▶[요약정보 전항목 상세설명 참조 열]이 Y일 경우 공란",
"[입력방식]\n직접입력\n\n▶요약정보설명 탭 내 값2 항목 입력\n\n▶[요약정보 전항목 상세설명 참조 열]이 Y일 경우 공란",
"[입력방식]\n직접입력\n\n▶요약정보설명 탭 내 값3 항목 입력\n\n▶[요약정보 전항목 상세설명 참조 열]이 Y일 경우 공란",
"[입력방식]\n직접입력\n\n▶요약정보설명 탭 내 값4 항목 입력\n\n▶[요약정보 전항목 상세설명 참조 열]이 Y일 경우 공란",
"[입력방식]\n직접입력\n\n▶요약정보설명 탭 내 값5 항목 입력\n\n▶[요약정보 전항목 상세설명 참조 열]이 Y일 경우 공란",
"[입력방식]\n직접입력\n\n▶요약정보설명 탭 내 값6 항목 입력\n\n▶[요약정보 전항목 상세설명 참조 열]이 Y일 경우 공란",
"[입력방식]\n직접입력\n\n▶요약정보설명 탭 내 값7 항목 입력\n\n▶[요약정보 전항목 상세설명 참조 열]이 Y일 경우 공란",
"[입력방식]\n직접입력\n\n▶요약정보설명 탭 내 값8 항목 입력\n\n▶[요약정보 전항목 상세설명 참조 열]이 Y일 경우 공란",
"[입력방식]\n직접입력\n\n▶요약정보설명 탭 내 값9 항목 입력\n\n▶[요약정보 전항목 상세설명 참조 열]이 Y일 경우 공란",
"[입력방식]\n직접입력\n\n▶요약정보설명 탭 내 값10 항목 입력\n\n▶[요약정보 전항목 상세설명 참조 열]이 Y일 경우 공란",
"[입력방식]\n직접입력\n\n▶요약정보설명 탭 내 값11 항목 입력\n\n▶[요약정보 전항목 상세설명 참조 열]이 Y일 경우 공란",
"[입력방식]\n직접입력\n\n▶요약정보설명 탭 내 값12 항목 입력\n\n▶[요약정보 전항목 상세설명 참조 열]이 Y일 경우 공란",
"[입력방식]\n직접입력\n\n▶요약정보설명 탭 내 값13 항목 입력\n\n▶[요약정보 전항목 상세설명 참조 열]이 Y일 경우 공란",
"[입력방식]\n직접입력\n\n▶요약정보설명 탭 내 값14 항목 입력\n\n▶[요약정보 전항목 상세설명 참조 열]이 Y일 경우 공란",
"[입력방식]\n직접입력\n\n▶요약정보설명 탭 내 값15 항목 입력\n\n▶[요약정보 전항목 상세설명 참조 열]이 Y일 경우 공란",
"[입력방식]\n직접입력\n\n▶요약정보설명 탭 내 값16 항목 입력\n\n▶[요약정보 전항목 상세설명 참조 열]이 Y일 경우 공란",
"[입력방식]\n직접입력\n\n▶요약정보설명 탭 내 값17 항목 입력\n\n▶[요약정보 전항목 상세설명 참조 열]이 Y일 경우 공란",
"[입력방식]\n직접입력\n\n▶요약정보설명 탭 내 값18 항목 입력\n\n▶[요약정보 전항목 상세설명 참조 열]이 Y일 경우 공란",
"[입력방식]\n직접입력\n\n▶요약정보설명 탭 내 값19 항목 입력\n\n▶[요약정보 전항목 상세설명 참조 열]이 Y일 경우 공란",
"[입력방식]\n직접입력\n\n▶요약정보설명 탭 내 값20 항목 입력\n\n▶[요약정보 전항목 상세설명 참조 열]이 Y일 경우 공란",
"[입력방식]\n직접입력\n\n▶요약정보설명 탭 내 값21 항목 입력\n\n▶[요약정보 전항목 상세설명 참조 열]이 Y일 경우 공란",
"[입력방식]\n직접입력\n\n▶요약정보설명 탭 내 값22 항목 입력\n\n▶[요약정보 전항목 상세설명 참조 열]이 Y일 경우 공란",
"[입력방식]\n직접입력\n\n▶요약정보설명 탭 내 값23 항목 입력\n\n▶[요약정보 전항목 상세설명 참조 열]이 Y일 경우 공란",
"[입력방식]\n직접입력\n\n▶요약정보설명 탭 내 값24 항목 입력\n\n▶[요약정보 전항목 상세설명 참조 열]이 Y일 경우 공란",
"[입력방식]\n직접입력\n\n▶요약정보설명 탭 내 값25 항목 입력\n\n▶[요약정보 전항목 상세설명 참조 열]이 Y일 경우 공란",
"[입력방식]\n직접입력\n\n▶요약정보설명 탭 내 값26 항목 입력\n\n▶[요약정보 전항목 상세설명 참조 열]이 Y일 경우 공란",
"[입력방식]\n직접입력\n\n▶요약정보설명 탭 내 값27 항목 입력\n\n▶[요약정보 전항목 상세설명 참조 열]이 Y일 경우 공란",
"[입력방식]\n직접입력\n\n▶요약정보설명 탭 내 값28 항목 입력\n\n▶[요약정보 전항목 상세설명 참조 열]이 Y일 경우 공란",
"[입력방식]\n직접입력\n\n▶요약정보설명 탭 내 값29 항목 입력\n\n▶[요약정보 전항목 상세설명 참조 열]이 Y일 경우 공란",
"오류메시지 출력 필드\n\n▶등록 실패 시 오류메시지 확인 필드\n\n▶공란으로 남겨주시기 바랍니다."
	);
				break;
			case 'extended' :
				$infos = array(
				"[입력방식]\n숫자 (0은 제외)\n\n▶0을 제외한 숫자를 임의로 입력하면 원본상품 등록 시 자동으로 규칙에 맞는 번호로 변경되어 등록\n\n▶중복값 입력 불가\n\n▶기본정보 시트에 있는 상품의 원본번호와 확장정보 시트에 있는 동일 상품의 원본번호는 같아야 함.\n\n▶상품정보는 3행부터 입력(2행의 예시는 삭제하지 마세요.)",
				"[입력방식]\n옥션 카테고리 번호\n(선택입력사항)\n\n▶카테고리 번호 확인경로\n원본상품 > 원본상품 엑셀추가 > [마켓카테고리 엑셀다운] > [옥션] 시트\n\n▶셀서식을 텍스트로 설정 후 입력\n(설정방법 : 해당 열 전체선택 > 마우스우클릭 > 셀 서식 > 텍스트 > 확인)\n\n▶확장카테고리 입력 시 세트 내 [확장 카테고리로 등록] 선택",
				"[입력방식]\nG마켓 카테고리 번호\n(선택입력사항)\n\n▶카테고리 번호 확인경로\n원본상품 > 원본상품 엑셀추가 > [마켓카테고리 엑셀다운] > [G마켓] 시트\n\n▶셀서식을 텍스트로 설정 후 입력\n(설정방법 : 해당 열 전체선택 > 마우스우클릭 > 셀 서식 > 텍스트 > 확인)\n\n▶확장카테고리 입력 시 세트 내 [확장 카테고리로 등록] 선택",
				"[입력방식]\n11번가 카테고리 번호\n(선택입력사항)\n\n▶카테고리 번호 확인경로\n원본상품 > 원본상품 엑셀추가 > [마켓카테고리 엑셀다운] > [11번가] 시트\n\n▶셀서식을 텍스트로 설정 후 입력\n(설정방법 : 해당 열 전체선택 > 마우스우클릭 > 셀 서식 > 텍스트 > 확인)\n\n▶확장카테고리 입력 시 세트 내 [확장 카테고리로 등록] 선택",
				"[입력방식]\n인터파크 카테고리 번호\n(선택입력사항)\n\n▶카테고리 번호 확인경로\n원본상품 > 원본상품 엑셀추가 > [마켓카테고리 엑셀다운] > [인터파크] 시트\n\n▶셀서식을 텍스트로 설정 후 입력\n(설정방법 : 해당 열 전체선택 > 마우스우클릭 > 셀 서식 > 텍스트 > 확인)\n\n▶확장카테고리 입력 시 세트 내 [확장 카테고리로 등록] 선택",
				"[입력방식]\n스마트스토어 카테고리 번호\n(선택입력사항)\n\n▶카테고리 번호 확인경로\n원본상품 > 원본상품 엑셀추가 > [마켓카테고리 엑셀다운] > [스마트스토어] 시트\n\n▶셀서식을 텍스트로 설정 후 입력\n(설정방법 : 해당 열 전체선택 > 마우스우클릭 > 셀 서식 > 텍스트 > 확인)\n\n▶확장카테고리 입력 시 세트 내 [확장 카테고리로 등록] 선택",
				"[입력방식]\nESM2.0*옥션*G마켓\n(ESM2.0 상품등록 시 필수입력사항)\n\n▶카테고리 번호 확인경로\n원본상품 > 원본상품 엑셀추가 > [마켓카테고리 엑셀다운] > [ESM2.0] 시트\n\n▶옥션, G마켓 모두 등록할 경우\nESM2.0*옥션*G마켓\n\n▶옥션만 등록할 경우\nESM2.0*옥션\n\n▶G마켓만 등록할 경우\nESM2.0*N*G마켓\n\n▶셀서식을 텍스트로 설정 후 입력\n(설정방법 : 해당 열 전체선택 > 마우스우클릭 > 셀 서식 > 텍스트 > 확인)\n\n▶확장카테고리 등록만 가능",
				"[입력방식]\n쿠팡 카테고리 번호\n(쿠팡 상품등록 시 필수입력사항)\n\n▶카테고리 번호 확인경로\n원본상품 > 원본상품 엑셀추가 > [마켓카테고리 엑셀다운] > [쿠팡] 시트\n\n▶셀서식을 텍스트로 설정 후 입력\n(설정방법 : 해당 열 전체선택 > 마우스우클릭 > 셀 서식 > 텍스트 > 확인)\n\n▶확장카테고리 등록만 가능",
				"[입력방식]\n티몬 카테고리 번호\n(티몬 상품등록 시 필수입력사항)\n\n▶카테고리 번호 확인경로\n원본상품 > 원본상품 엑셀추가 > [마켓카테고리 엑셀다운] > [티몬] 시트\n\n▶셀서식을 텍스트로 설정 후 입력\n(설정방법 : 해당 열 전체선택 > 마우스우클릭 > 셀 서식 > 텍스트 > 확인)\n\n▶확장카테고리 등록만 가능",
				"[입력방식]\n위메프2.0 카테고리 번호\n(위메프2.0 상품등록 시 필수입력사항)\n\n▶카테고리 번호 확인경로\n원본상품 > 원본상품 엑셀추가 > [마켓카테고리 엑셀다운] > [위메프2.0] 시트\n\n▶셀서식을 텍스트로 설정 후 입력\n(설정방법 : 해당 열 전체선택 > 마우스우클릭 > 셀 서식 > 텍스트 > 확인)\n\n▶확장카테고리 등록만 가능",
				"[입력방식]\n롯데ON 카테고리 번호\n(롯데ON 상품등록 시 필수입력사항)\n\n▶카테고리 번호 확인경로\n원본상품 > 원본상품 엑셀추가 > [마켓카테고리 엑셀다운] > [롯데ON] 시트\n\n▶셀서식을 텍스트로 설정 후 입력\n(설정방법 : 해당 열 전체선택 > 마우스우클릭 > 셀 서식 > 텍스트 > 확인)\n\n▶확장카테고리 등록만 가능",
				"[입력방식]\n담당MD 이름 입력\n\n▶공란\n담당MD가 없는 경우",
				"[입력방식]\n한글 17자/영,숫자 35자 \n\n▶제공할 사은품이 있을 경우 입력",
				"[입력방식]\n한글 17자/영,숫자 35자 \n\n▶제공할 사은품이 있을 경우 입력",
				"[입력방식]\n한글 17자/영,숫자 35자 \n\n▶제공할 사은품이 있을 경우 입력",
				"[입력방식]\n직접입력\n\n▶옥션,G마켓,인터파크 지원\n\n▶원본상품 상세정보>확장정보에서 각 마켓의 도서 ISBN 코드를 수정한 경우, 엑셀 수정 시 입력된 코드로 일괄 수정",
				"[입력방식]\n숫자\n\n▶정가\n출판사에서 책을 출간할 당시 생성된 가격이며, 고객에게 실제 판매하는 가격이 아닌 참고용으로 표시하는 가격\n\n•그 외 마켓\nISBN 코드에 해당되는 정보로 자동 등록",
				"[입력방식]\n콤마(,)로 구분하여 최대 10개까지 직접입력",
				"[입력방식]\nY 또는 공란 : 사용함\nN : 사용안함",
				"[입력방식]\n직접입력\n\n▶스마트스토어 전용 상품명을 사용하는 경우 전용 상품명을 입력\n\n▶공란\n기본 상품명으로 등록 ",
				"[입력방식]\n콤마(,)로 구분하여 최대 40개까지 입력\n\n▶키워드\n한글 20자, 영문 25자",
				"[입력방식]\n병행수입여부*수입신고필증 웹이미지 URL\n\n▶병행수입여부\nY : 병행수입상품O\nN 또는 공란 : 병행수입상품X",
				"[입력방식]\n아래 수입코드 중 입력\n(원산지가 수입의 경우 필수 입력사항)\n\n▶수입코드 입력\nA : 병행수입\nB : 공식수입\n공란 : 해당없음 (원산지가 국산의 경우)",
				"[입력방식]\n웹 이미지 URL로 입력(인보이스 서류 등록)\n\n▶복수 등록 시 (*)로 구분하여 입력",
				"[입력방식]\n웹 이미지 URL로 입력\n\n▶복수 등록 시 (*)로 구분하여 입력",
				"[입력방식]\n옵션명1의 옵션값*옵션명2의 옵션값**할인율기준가\n\n▶할인율기준가\n판매가격 대비 할인율 표기를 위한 할인전금액을 의미\n\n▶옵션값 조합이 여러 개일 경우 줄바꿈(Alt+Enter)으로 구분\n\n\n\n\n\n\n\n\n예시)옵션명(옵션값)이 색상(화이트), 사이즈(S,M)인 경우\n화이트*S**30000\n화이트*M**30000",
				"[입력방식]\n옵션명1의 옵션값*옵션명2의 옵션값**대표이미지*기타이미지1*기타이미지2*기타이미지3*기타이미지4*기타이미지5\n\n▶이미지\n웹 이미지 URL로 입력\n\n▶대표이미지 1개, 기타이미지 최대 5개, 최소 500 X 500px의 정사각형 이미지만 등록 가능\n\n▶옵션값 조합이 여러 개일 경우 줄바꿈(Alt+Enter)으로 구분\n\n\n\n\n예시)옵션명(옵션값)이 색상(화이트), 사이즈(S,M)인 경우\n화이트*S**http://z4img1.esellers.co.kr/admin/1_0.jpg\n화이트*M**http://z4img1.esellers.co.kr/admin/2_0.jpg",
				"[입력방식]\n옵션명1의 옵션값*옵션명2의 옵션값**상세설명HTML\n\n▶옵션값 조합이 여러 개일 경우 줄바꿈(Alt+Enter)으로 구분\n\n\n\n\n\n\n\n\n\n\n예시)옵션명(옵션값)이 색상(화이트), 사이즈(S,M)인 경우\n화이트*S**<img src=\"http://esellers.esellersimg.co.kr/주머니4.5.JPG\" border=0>\n화이트*M**<img src=\"http://esellers.esellersimg.co.kr/주머니4.5.JPG\" border=0>",
				"[입력방식]\n공란 또는 아래 형식으로 입력\n\n▶공란\n바코드 없음 / 사유 직접입력으로 등록\n\n▶바코드 있음\n옵션명1의 옵션값*옵션명2의 옵션값**Y*바코드번호\n\n▶바코드 없음\n옵션명1의 옵션값*옵션명2의 옵션값**N*사유코드\n\n▶바코드 없음 사유코드\nA : 온라인 판매를 위한 소규모 제작 상품임\nB : 주문 제작으로 유통하는 상품임\nC : 색상이 다르지만 바코드가 동일한 상품임\nD : 국내외 표준 바코드가 아닌 상품임\nE : 제조사에서 바코드를 제공 받지 못함 \nF : 사유 직접 입력(최대 30자)\n\n\n예시)옵션명(옵션값)이 색상(화이트), 사이즈(S,M)인 경우, 바코드 있음 \n화이트*S**Y*8801234560016\n화이트*M**Y*8801234560016",
				"[입력방식]\n옵션명1의 옵션값*옵션명2의 옵션값**인증여부*인증코드*인증번호\n\n▶인증여부\nA : 인증대상\nB : 인증대상아님\nC : 상세설명참조\n\n▶동일한 옵션값 조합에 인증정보 복수 등록 시 줄바꿈(Alt+Enter)으로 구분 / 동일 인증코드는 등록불가\n\n\n\n예시)옵션명(옵션값)이 색상(화이트), 사이즈(S,M), 인증대상\n화이트*S**A*C011*CB141R002-5001\n화이트*M**A*C011*CB141R002-5001\n\n예시)옵션명(옵션값)이 색상(화이트), 사이즈(S,M), 인증대상아님\n화이트*S**B\n화이트*M**B\n\n예시)옵션명(옵션값)이 색상(화이트), 사이즈(S,M), 상세설명참조",
				"[입력방식]\n옵션명1의 옵션값*옵션명2의 옵션값**모델번호 \n\n▶제품의 품번 또는 모델명 입력\n\n▶옵션값 조합이 여러 개일 경우 줄바꿈(Alt+Enter)으로 구분\n\n\n\n\n\n\n\n\n예시)옵션명(옵션값)이 색상(화이트), 사이즈(S,M)인 경우\n화이트*S**ABC125\n화이트*M**ABC125",
				"[입력방식]\n쿠팡 카테고리에 맞는 검색옵션명 입력\n\n▶검색옵션 확인경로\n원본상품 > 원본상품 엑셀추가 > [마켓카테고리 엑셀다운] > [쿠팡구매옵션] 시트\n\n▶검색옵션명이 여러 개일 경우 줄바꿈(Alt+Enter)으로 구분\n\n\n\n\n\n예시)\n상의사이즈계열\n패션의류/잡화색상계열",
				"[입력방식]\n옵션명1의 옵션값*옵션명2의 옵션값**검색옵션명1의 검색옵션값*검색옵션명2의 검색옵션값\n\n▶옵션값 조합이 여러 개일 경우 줄바꿈(Alt+Enter)으로 구분\n\n\n\n\n\n\n\n\n예시)옵션명(옵션값)이 색상(화이트), 사이즈(S,M)인 경우\n화이트*S**여성S*화이트계열*플라워*브이넥\n화이트*M**여성M*화이트계열*플라워*브이넥 ",
				"[입력방식]\n상품속성명1*상품속성값1\n상품속성명2*상품속성값2\n\n▶상품속성이 여러 개일 경우 줄바꿈(Alt+Enter) 으로 구분\n\n▶상품속성 확인경로\n원본상품 > 원본상품 엑셀추가 > [마켓카테고리 엑셀다운] > [11번가] 시트\n\n▶상품속성\n특정 카테고리에 따라 필수입력 사항이며, 등록할 카테고리의 상품속성명을 확인 후 동일하게 입력\n\n\n\n예시)상품속성 : 센서분류, 광학줌, 유효화소수\n센서분류*풀프레임\n광학줌*15배줌\n유효화소수*2001만 화소",
				"[입력방식]\n상품속성 라벨 분류코드 입력\n중복입력시 쉼표(,)로 구분\n\n▶상품속성 라벨 분류 코드\nA : 해외구매대행\nB : 남성용\nC : 여성용\nD : 남녀공용\n\n\n예시) 해외구매대행 상품의 남녀공용 상품\nA,D",
				"[입력방식]\n검색어(키워드) 입력\n\n•인터파크\n중복 키워드 제거, 키워드당 최대 10자, 키워드 최대 5개 까지 등록\n\n•티몬,롯데ON\n입력순대로 5개 까지 등록 \n\n•위메프2.0\n입력순대로 10개 까지 등록",
				"[입력방식]\n검색어(키워드) 입력\n(위메프2.0 제휴채널 노출용)\n\n▶입력순대로 10개 까지 등록\n\n▶ 공란
\"제휴채널 신청안함\"으로 처리\n\n▶키워드 입력 시\n\"제휴채널 신청\"으로 처리\n\n▶제휴채널\n위메프와 제휴계약을 체결한 모든 웹사이트 및 어플리케이션을 의미. \"제휴채널\"을 통해 고객이 상품을 구매할 경우 결제 금액의 2%가 \"제휴채널 노출 수수료\"로 부과",
"[입력방식]\n인증상품분류코드*광고심의필증 웹이미지주소URL\n\n▶아래 카테고리에 한해서만 등록 가능\n가전.컴퓨터 / 뷰티 / 생활.주방 / 식품.건강 / 출산.유아동\n\n▶공란\n법적허가 및 신고대상 상품이 아닌경우\n\n▶인증상품분류코드\nDOC1 : 건강기능식품\nDOC2 : 의료기기\nDOC3 : 기능성화장품\nDOC4 : 특수용도식품\nDOC5 : 의약식품\n\n\n\n\n예시) 기능성화장품 상품에 대한 법적허가 및 신고대상의 경우 광고심의필증 등록.\nDOC3*http://z4img1.esellers.co.kr/esellers/esellers.jpg",
"[입력방식]\n인증여부*인증코드*인증번호(신고번호)*인증기관(신고기관)*허가번호*심의번호\n\n▶인증여부\nA : 인증대상\nB 또는 공란 : 인증대상아님\nC : 상세설명참조\n\n▶인증코드\n인증품목코드 시트에서 확인\n\n▶인증정보가 여러 개일 경우 줄바꿈(Alt+Enter)으로 구분\n\n\n•인증대상의 경우 : 인증번호까지 필수 입력, 이하 정보는 필요 시 입력\n 예시) 어린이제품-인증대상으로 등록할 경우\n A*C011*CB141R002-5001\n\n•인증대상아님의 경우\n- 모든 인증항목이 인증대상아님 : 공란 또는 B\n- 일부 인증항목만 인증대상아님 : B*인증코드 입력\n 예시) B*C011\n\n•상세설명참조의 경우 : C*인증코드 입력\n 예시) 어린이제품-상세설명참조로 등록할 경우",
"[입력방식]\n인증여부*인증코드*인증번호*인증기관\n\n▶인증여부\nA : 인증대상\nB 또는 공란 : 인증대상아님\nC : 상세설명참조\n\n▶인증코드\n인증품목코드 시트에서 확인\n\n▶인증정보가 여러 개일 경우 줄바꿈(Alt+Enter)으로 구분\n\n\n\n•인증대상의 경우 : 인증번호까지 필수 입력, 이하 정보는 필요 시 입력
 예시) 어린이제품-인증대상으로 등록할 경우\n A*C011*CB141R002-5001\n\n•인증대상아님의 경우\n- 모든 인증항목이 인증대상아님 : 공란 또는 B\n- 일부 인증항목만 인증대상아님 : B*인증코드 입력\n 예시) B*C011\n\n•상세설명참조의 경우 : C*인증코드 입력\n 예시) 어린이제품-상세설명참조로 등록할 경우\n C*C011",
 "[입력방식]\n인증여부*인증코드*인증번호\n\n▶인증여부\nA : 인증대상\nB 또는 공란 : 인증대상아님\nC : 상세설명참조\n\n▶인증코드\n인증품목코드 시트에서 확인\n\n▶인증정보가 여러 개일 경우 줄바꿈(Alt+Enter)으로 구분 / 최대 100개까지 입력 가능 \n\n\n•인증대상의 경우 : 인증번호까지 필수 입력\n 예시) 생활용품안전인증-인증대상으로 등록할 경우\n A*C001*CB141R002-5001\n\n•인증대상아님의 경우 : 공란 또는 B\n 예시) B\n\n•상세설명참조의 경우 : 인증여부만 입력\n 예시) C",
 "[입력방식]\n인증여부*인증코드*인증번호\n\n▶인증여부\nA : 인증대상\nB 또는 공란 : 인증대상아님\nC : 상세설명참조\n\n▶인증코드\n인증품목코드 시트에서 확인\n\n▶인증정보가 여러 개일 경우 줄바꿈(Alt+Enter)으로 구분 / 최대 20까지 입력 가능\n\n▶인증대상아님 또는 상세설명참조로 입력할 경우, 다른 인증정보 추가 입력 불가\n\n\n•인증대상의 경우 : 인증번호까지 필수 입력\n 예시) 생활용품안전인증-인증대상으로 등록할 경우\n A*C001*CB141R002-5001\n\n•인증대상아님의 경우 : 공란 또는 B\n 예시) B\n\n•상세설명참조의 경우 : 인증여부만 입력\n 예시) C",
 "[입력방식]\n인증여부*인증코드*인증번호*인증기관*인증상호*KC마크사용여부*인증일자\n\n▶인증여부\nA : 인증대상\nB 또는 공란 : 인증대상아님\n\n▶인증코드\n인증품목코드 시트에서 확인\n\n▶KC마크사용여부\nY : 인증대상\nN 또는 공란: 사용안함\n\n▶인증일자\nYYYY-MM-DD 형식으로 입력\n\n▶인증정보가 여러 개일 경우 줄바꿈(Alt+Enter)으로 구분 / 최대 5까지 입력 가능\n\n▶어린이제품/생활용품/전기용품 공급자적합성확인 인증유형의 경우 인증여부와 인증코드만 입력\n\n\n\n•인증대상 : 인증번호까지 필수 입력",
 "[입력방식]\n인증여부*인증코드*인증번호(신고번호)*인증기관(신고기관)*허가번호*심의번호\n\n▶인증여부\nA : 인증대상\nB 또는 공란 : 인증대상아님
C : 상세설명참조\n\n▶인증코드\n인증품목코드 시트에서 확인\n\n▶인증정보가 여러 개일 경우 줄바꿈(Alt+Enter)으로 구분\n\n\n•인증대상일 경우 : 인증번호까지 필수 입력, 이하 정보는 필요 시 입력\n 예시) 어린이제품-인증대상으로 등록할 경우\n A*C011*CB141R002-5001\n\n•인증대상아님일 경우\n- 모든 인증항목이 인증대상아님 : 공란 또는 B\n- 일부 인증항목만 인증대상아님 : B*인증코드 입력\n  예시) B*C011\n\n•상세설명참조 : C*인증코드 입력\n 예시) 어린이제품-상세설명참조로 등록할 경우",
"[입력방식]\n인증여부*인증코드*인증번호\n\n▶인증여부\nA : 인증대상\nB 또는 공란 : 인증대상아님\n\n▶인증코드\n인증품목코드 시트에서 확인\n
▶인증정보가 여러 개일 경우 줄바꿈(Alt+Enter)으로 구분\n\n\n•인증대상일 경우 : 인증번호까지 필수 입력\n 예시) 생활용품-안전인증으로 등록할 경우\n A*C001*CB141R002-5001 ",
"[입력방식]\n인증여부*인증코드*인증번호(신고번호)\n\n▶인증여부\nA : 인증대상\nB 또는 공란 : 인증대상아님\nC : 상세설명참조\n\n▶인증코드
인증품목코드 시트에서 확인\n\n▶인증정보가 여러 개일 경우 줄바꿈(Alt+Enter)으로 구분\n\n\n•인증대상일 경우 : 인증번호까지 필수 입력\n 예시) 생활용품-안전인증으로 등록할 경우
 A*C001*CB141R002-5001\n\n•인증대상아님일 경우\n- 모든 인증항목이 인증대상아님 : 공란 또는 B\n- 일부 인증항목만 인증대상아님 : B*인증코드 입력\n  예시) B*C011\n
•상세설명참조일 경우\n- 모든 인증항목이 상세설명참조 : C\n- 일부 인증항목만 상세설명참조 : C*인증코드 입력\n  예시) 어린이제품-인증대상, 생활용품-상세설명참조, 전기",
"[입력방식]\n인증여부*인증코드*인증번호*인증기관\n\n▶인증여부\nA : 인증대상\nB 또는 공란 : 인증대상아님\n\n▶인증코드\n인증품목코드 시트에서 확인\n\n\n•인증대상일 경우 : 인증번호까지 필수 입력\n 예시) 어린이제품-인증대상으로 등록할 경우\n A*C011*CB141R002-5001",
"오류메시지 출력 필드\n\n▶등록 실패 시 오류메시지 확인 필드\n\n▶공란으로 남겨주시기 바랍니다."
	);
				break;
		}
		
		return $infos;
	}
	
	function write_goods_zoomoney_add_sheets(){
		$xmlPath = ROOTPATH.'data/esellers/';
		if(!is_dir($xmlPath)){
			mkdir($xmlPath);
			chmod($xmlPath, 0777);
			chown($xmlPath, "nobody");
			chgrp($xmlPath, "nobody");
		}
		
		$categoriesURL	= $xmlPath."zoomoney_categories.xml";
		$certsURL		= $xmlPath."zoomoney_certs.xml";
		$infosURL		= $xmlPath."zoomoney_infos.xml";
		
		if (!file_exists($categoriesURL)) {
			$dom = new DOMDocument();
			$dom->load('https://interface.firstmall.kr/esellers/zoomoney_categories.xml');
			$dom->save($categoriesURL);
		}
		
		if (!file_exists($certsURL)) {
			$dom = new DOMDocument();
			$dom->load('https://interface.firstmall.kr/esellers/zoomoney_certs.xml');
			$dom->save($certsURL);
		}
		
		if (!file_exists($infosURL)) {
			$dom = new DOMDocument();
			$dom->load('https://interface.firstmall.kr/esellers/zoomoney_infos.xml');
			$dom->save($infosURL);
		}
		
		//category
		$SheetCategory = $this->writer->addNewSheetAndMakeItCurrent();
		$SheetCategory = $this->writer->getCurrentSheet();
		$SheetCategory->setName('이셀러스표준카테고리');
		
		$this->writer->addRowWithStyle(array('카테고리번호', '대카테고리명', '중카테고리명', '소카테고리명', '세카테고리명'), $this->style_title_yellow_center);
		
		$categoriesXML = simplexml_load_file($categoriesURL);
		$categories = json_decode( json_encode( $categoriesXML ), 1 );
		foreach($categories['category'] as $k => $v){
			$rows = array($v['id'], $v['large_category'], $v['middle_category'], $v['small_category'], $v['detail_category']);
			$this->writer->addRowWithStyle($rows, $this->style_contents2);
		}
		
		//info
		$SheetInfos = $this->writer->addNewSheetAndMakeItCurrent();
		$SheetInfos = $this->writer->getCurrentSheet();
		$SheetInfos->setName('요약정보설명');
		
		$this->writer->addRowWithStyle( array( "상품군코드", "상품군설명", "전상품 상세설명참조여부\n(스마트스토어기준)",
			"값1", "값2", "값3", "값4", "값5", "값6", "값7", "값8", "값9", "값10", "값11", "값12", "값13", "값14", "값15", "값16",
			"값17", "값18", "값19", "값20", "값21", "값22", "값23", "값24", "값25", "값26", "값27", "값28", "값29"),
			$this->style_title_yellow_center);
		
		
		$infosXML = simplexml_load_file($infosURL);
		$infos = json_decode( json_encode( $infosXML ), 1 );
		
		foreach($infos['info'] as $k => $v){
			$rows = array( $v['code'], $v['desc'], $v['is_refer'],
				$v['value1'], $v['value2'], $v['value3'], $v['value4'], $v['value5'], $v['value6'], $v['value7'], $v['value8'],
				$v['value9'], $v['value10'], $v['value11'], $v['value12'], $v['value13'], $v['value14'], $v['value15'], $v['value16'],
				$v['value17'], $v['value18'], $v['value19'], $v['value20'], $v['value21'], $v['value23'], $v['value23'], $v['value24'],
				$v['value25'], $v['value26'], $v['value27'], $v['value28'], $v['value29']);
			$this->writer->addRowWithStyle($rows, $this->style_contents2);
		}
		
		//cert
		$SheetCerts = $this->writer->addNewSheetAndMakeItCurrent();
		$SheetCerts = $this->writer->getCurrentSheet();
		$SheetCerts->setName('인증품목코드');
		
		$this->writer->addRowWithStyle( array( "인증품목", "인증품목명", "옥션", "G마켓", "11번가", "스마트스토어", "인터파크", "ESM 2.0", "쿠팡", "티몬", "위메프 2.0", "롯데ON"), $this->style_title_yellow_center);
		
		$certsXML = simplexml_load_file($certsURL);
		$certs = json_decode( json_encode( $certsXML ), 1 );
		
		foreach($certs['cert'] as $k => $v){
			$rows = array( $v['code'], $v['name'], $v['auction'], $v['gmarket'], $v['st11'], $v['smartstore'],
				$v['interpark'], $v['esm2_0'], $v['coupang'], $v['tmon'], $v['wemakeprice2_0'], $v['lotteon']);
			$this->writer->addRowWithStyle($rows, $this->style_contents2);
		}
		
		unset($categories, $infos, $certs, $rows);
	}
	
	function create_order(){
		$this->load->model('ordermodel');
		$this->load->model('providershipping');
		$this->load->model('order2exportmodel');
		$this->load->model('excelmodel');
		$this->load->model('authmodel');

		$aParams = $this->input->post();

		$form_seq		= $aParams['seq'];
		$str_order_seq	= $aParams['order_seq'];
		$excel_step		= $aParams['excel_step'];
		$chk_step		= $aParams['chk_step'];
		$excel_type		= $aParams['excel_type']; 
		parse_str($aParams['params'], $this->params);
		$this->params	= array_filter($this->params);

		$this->params['provider_seq'] 		= $aParams['provider_seq'];
		$this->params['excel_provider_seq'] = $aParams['excel_provider_seq'];
		$this->params['reg_date'] 			= date('Y-m-d H:i:s');
		$this->params['is_cron'] 			= 'N';
		$this->queueID 						= '0';

		if( $this->params['provider_seq'] > 1 && $this->params['excel_provider_seq'] > 1 ) {
			define('__SELLERADMIN__', true);
		}

		//개인정보 마스킹 표시 권한 체크
		$private_masking = $this->authmodel->manager_limit_act('private_masking');
		$chk_masking = array('주문자명', '주문자연락처', '주문자휴대폰', '주문자이메일', '받는정보', '수령인', '수령인연락처', '수령인휴대폰', '전체주소(지번)', '전체주소(도로명)');

		if($excel_step > 0){
			unset($this->params['chk_step']);
			$this->params['chk_step'][$excel_step] = 1;
		} else {
			$this->params['chk_step'] = $chk_step;
		}

		$searchCount		= 0;
		if($str_order_seq == 'search'){
			$arr_order_seq				= 'search';
			$this->params['query_type']	= 'total_record';

			$res		 = $this->ordermodel->get_order_catalog_query_spout($this->params);
			$searchCount = $res[0]['cnt'];
		} else {
			$arr_order_seq	= explode('|',$str_order_seq); // 주문번호 추출
			$arr_order_seq	= array_filter($arr_order_seq);
			$searchCount	= count($arr_order_seq);
		}

		if($searchCount <= 0){
			echo "다운로드 가능 한 ".$this->categoryKR."이 없습니다."; 
			exit;
		}

		$this->excelmodel->get_exceldownload($form_seq);
		$columnInfo = $this->excelmodel->data_exceldownload;
		$this->excelmodel->setting_type	= $columnInfo['criteria']; 
		$this->excelmodel->set_cell();
		$excel_type .= "_".$this->excelmodel->data_exceldownload['criteria'];
		$excel_type = strtolower($excel_type);

		foreach($columnInfo['item'] as $item){
			$title = $item;
			foreach($this->excelmodel->all_cells as $code => $data){
				if($item == $data[1]){
					$title			= $data[0];
				}
			}

			if ( in_array($title, $chk_masking) ) {
				$this->isPrivate = 'Y';
				if ( $private_masking ) {
					$msg = "마스킹(*) 처리된 개인정보 항목이 포함되어 있어 엑셀 다운로드를 할 수 없습니다.";
					$msg .= "<br/ >대표운영자에게 관리자 권한 수정을 요청하거나 해당 항목을 제외하면 다운로드 가능합니다.";
					openDialogAlert($msg, 600, 180, 'parent', '');
					exit;
				}
			}
		}

		if($this->params['excel_provider_seq'] > 1){ //입점사 다운로드의 경우
			$this->params['manager_id'] = $this->providerInfo['provider_id'];
		} else {
			$this->params['manager_id'] = $this->managerInfo['manager_id'];
		}

		if($searchCount <= $this->limitCount){
			$this->params['list']			= $arr_order_seq;
			$this->params['form_seq']		= $form_seq;
			$this->params['excel_type']		= $excel_type;
			$this->params['limit_count']	= $this->limitCount;
			$this->params['searchcount']	= $searchCount;
			$this->params['excel_spout']	= true;
			$this->params['is_zip']			= 'N';

			$this->write_order();
		} else {
			$params					= array();
			$params['list']			= $arr_order_seq;
			$params['searchcount']	= $searchCount;
			$params['form_seq']		= $form_seq;
			$params['excel_step']	= $excel_step;
			$params					= array_merge($params, $this->params);
			$params['provider_seq']	= $this->params['provider_seq'];
			$params['is_private']	= $this->isPrivate;
			
			$setData = array(
				'id'			=> '',
				'provider_seq'	=> $this->params['excel_provider_seq'],
				'manager_id'	=> $this->params['manager_id'],
				'category'		=> $this->categoryKey, //type >> 1:goods, 2:order, 3:member
				'excel_type'	=> $excel_type, 
				'context'		=> serialize($params),
				'count'			=> $params['searchcount'],
				'state'			=> 0,
				'limit_count'	=> $this->limitCount,
				'reg_date'		=> $this->params['reg_date']
			);
			$this->db->insert('fm_queue', $setData);
			$queueID = $this->db->insert_id();
			$affect  = $this->db->affected_rows();
			if( $queueID > 0 || $affect > 0 ){
				$expectTime = ((ceil($params['searchcount']/$this->limitCount)) * 10) + 1200; 
				echo "엑셀 파일 생성 중 (예상 소요시간 : ".gmdate("H시 i분 s초", $expectTime).")\n파일 생성 후 ".$this->categoryKR." > 엑셀 다운로드 메뉴에서 다운로드 가능 합니다.";
			} else {
				echo "에러 발생.\n문제가 지속 될 경우 관리자에게 문의 바랍니다.";
			}
			exit;
		}
	}

	function write_order(){
		$this->load->model('ordermodel');
		$this->load->model('openmarketmodel');
		$this->load->model('excelmodel');
		$this->load->model('order2exportmodel');
		$this->load->model('shippingmodel');
		$this->load->model('authmodel');
		$this->load->library('itemexcelfilter');
		$this->load->library('orderexcelfilter');

		$this->create_dirs();
		$params = $this->params;

		$this->order2exportmodel->courier_for_provider[1]	= $this->providershipping->get_provider_courier(1);
		$this->orderexcelfilter->data_shipping_group_name	= $this->shippingmodel->get_shipping_group_name_list();	//배송그룹명리스트
		$this->itemexcelfilter->data_shipping_group_name	= $this->orderexcelfilter->data_shipping_group_name;	//배송그룹명리스트

		$provider_data	  = $this->order2exportmodel->provider_data;
		$linkage_malldata = $this->openmarketmodel->get_linkage_support_mall('shoplinker');

		if($linkage_malldata){
			foreach($linkage_malldata as $key => $malldata){
				$linkage_mallnames[$malldata['mall_code']] = $malldata['mall_name'];
			}
		}

		//cell info
		$this->excelmodel->get_exceldownload($params['form_seq']); 
		$this->orderexcelfilter->only_real = $this->excelmodel->only_real;
		$columnInfo = $this->excelmodel->data_exceldownload;
		$this->excelmodel->setting_type	= $columnInfo['criteria']; 
		$this->excelmodel->set_cell();

		$libraryName = strtolower($columnInfo['criteria']).'excelfilter';
		$this->load->library($libraryName);
		$this->{$libraryName}->data_linkage		= $linkage_mallnames;
		$this->{$libraryName}->data_paymethod	= code_load('orderexcel_pay_method');
		$this->{$libraryName}->data_tax			= code_load('orderexcel_tax');
		$this->{$libraryName}->data_step		= config_load('step');
		$this->{$libraryName}->data_provider	= $provider_data; 

		$columnNames	= array();
		$fields			= array();
		$columnWidths	= array();
		foreach($columnInfo['item'] as $item){
			$title = $item;
			$field = array();
			$columnWidth = 16;
			foreach($this->excelmodel->all_cells as $code => $data){
				if($item == $data[1]){
					$title			= $data[0];
					$field			= $data;
					$columnWidth	= ceil($data[3]/5);
				}
			}

			if($item == 'option'){
				foreach($field[4] as $k => $v){
					$columnNames[]	= $v[0];
					$fields[]		= $v;
					$columnWidths[]	= ceil($v[3]/5);
				}		
			} else {
				$columnNames[]	= $title;
				$fields[]		= $field;
				$columnWidths[]	= $columnWidth;
			}
		}
		$funcName = "write_order_".strtolower($columnInfo['criteria']);
		$this->{$funcName}($params, $columnNames, $fields, $columnWidths);
	}

	function write_order_order($params, $columnNames, $fields, $columnWidths=null){
		$totalCount = $params['searchcount'];
		$loopCount	= ceil($totalCount / $this->limitCount);
		$fileList	= array();

		unset($params['query_type']);
		$params['excel_spout'] = true;

//$loopCount = 1;
		for($i=0; $i<$loopCount; $i++) {
			$this->fileExe = 'xlsx';
			$this->writer = WriterFactory::create(Type::XLSX); // for XLSX files
			if($columnWidths){
				$this->writer->colWidth = $columnWidths;
			}

			$filename	= $this->nameDomain."_".$this->category."_list_".date('YmdHis')."_".str_pad( $i, 4, "0", STR_PAD_LEFT ).".".$this->fileExe;
			$filepath	= $this->downPath . $filename;
			$fileList[]	= $filename;
			
			$this->set_style();

			$this->writer->openToFile($filepath);
			$this->writer->addRowWithStyle($columnNames, $this->style_title);

			$orders	= array();
			if($params['list'] == 'search'){
				$res = array();
				unset($params['order_seq']);
				$params['limit_e']	= $this->limitCount;
				$params['limit_s']	= $i * $this->limitCount;

				unset($this->db->queries);
				unset($this->db->query_times);

				$res = $this->ordermodel->get_order_catalog_query_spout($params);
				foreach($res as $v){
					$orders[] = $v['order_seq'];
				}
			} else {
				$orders = array_slice($params['list'], ($i * $this->limitCount), $this->limitCount);
			}
			
			foreach($orders as $order_seq){
				$res = array();
				$params['order_seq'] = $order_seq; 
				$res = $this->order2exportmodel->get_excel($params);
				$this->orderexcelfilter->data_order = $res;
				$this->orderexcelfilter->shippinggroup_cnt	= 0;

				$outputs = array();
				foreach($fields as $j => $data_field){
					if( !$data_field[2] ){
						if($data_field[1]){
							if(in_array($data_field[1],$this->_set_currency)){
								$res['order'][$data_field[1]] = get_krw_currency($res['order'][$data_field[1]]);
							}
							$outputs[$data_field[1]] = $res['order'][$data_field[1]];
						}else{
							$outputs[$j] = "";
						}
					}else{
						$this->orderexcelfilter->shippinggroup_cnt = $res['ordershipping_cnt'];
						$data_filter = $this->orderexcelfilter->{$data_field[1]}();
						if( is_array($data_filter) ){
							$outputs[$data_field[1]] = strip_tags(implode("&#10;", $data_filter));
						} else { 
							$outputs[$data_field[1]] = $data_filter;
						}

						$outputs[$data_field[1]] = html_entity_decode($outputs[$data_field[1]], ENT_QUOTES, 'utf-8');
					}
				}
				$this->writer->addRowWithStyle($outputs, $this->style_contents);
				unset($res, $outputs);
			}

			$this->writer->close();
		}
//$this->writer->close();
		unset($orders);

		$this->end_write($params, $filename, $fileList);
	}

	function write_order_item($params, $columnNames, $fields, $columnWidths=null){
		$totalCount = $params['searchcount'];
		$loopCount	= ceil($totalCount / $this->limitCount);
		$fileList	= array();
		$fileCount	= 0;

		unset($params['query_type']);
		$params['excel_spout'] = true;

//$fileCount = 1;
		$order_count = 0;
		for($i=0; $i<$loopCount; $i++) {
			$orderData	= array();
			$orders		= array();
			if($params['list'] == 'search'){
				$res = array();
				unset($params['order_seq']);
				$params['limit_e']	= $this->limitCount;
				$params['limit_s']	= $i * $this->limitCount;

				unset($this->db->queries);
				unset($this->db->query_times);

				$res = $this->ordermodel->get_order_catalog_query_spout($params);
				foreach($res as $v){
					$orders[] = $v['order_seq'];
				}
			} else {
				$orders = array_slice($params['list'], ($i * $this->limitCount), $this->limitCount);
			}

			//start order
			$j = 0;
			$styleType = "style_contents_yellow";

			foreach($orders as $order_seq) {
				$res = array();
				$params['order_seq']	= $order_seq;
				$data_order				= $this->order2exportmodel->get_excel($params); //데이터 필터

				$params_order	= array();
				$params_order['data_order']		= $data_order['order'];
				$params_order['data_member']	= $data_order['member'];
				$old_order_seq					= "";

				$outputs = array();
				$thisStyle = $this->style_contents;

				foreach($data_order['ordershipping'] as $data_shipping) {
					$params_order['data_shipping'] = $data_shipping;
					foreach($fields as $data_field) {
						$item_count	= $order_count;
						$params_order['data_shipping']['old_shipping_seq']	= "";

						foreach($data_shipping['options'] as $data_option) {
							unset($params_order['data_package']);
							$params_order['data_option'] = $data_option;

							if ($this->orderexcelfilter->only_real != 'REAL' || !$data_option['packages']) {
								if( !$data_field[2] ){
									if($data_field[1]){
										if(in_array($data_field[1], $this->_set_currency)){
											$data_order['order'][$data_field[1]] = get_krw_currency($data_order['order'][$data_field[1]]);
										}

										if($data_field[1] == "settleprice" && $order_seq == $old_order_seq){
											$data_order['order'][$data_field[1]] = "(상동)";
										}

										if($data_field[1] == "settleprice"){
											$old_order_seq = $order_seq;
										}

										$outputs[$item_count][$data_field[1]]	= $data_order['order'][$data_field[1]];
									}else{
										$outputs[$item_count][$j] = "";
										$j++;
									}
								}else{
									if($data_field[1] == 'shipping_provider'){ //이미 데이터가 있기 때문에 필터 필요 없음 18.09.10 kmj
										$outputs[$item_count][$data_field[1]] = $data_shipping['shipping_provider'];
									} else if( preg_match('/^option/', $data_field[1]) ) {
										$keyName = substr($data_field[1], 6);
										$outputs[$item_count][$data_field[1]] = $data_option[$keyName];
									} else if( preg_match('/^addoption/', $data_field[1]) ) {
										$outputs[$item_count][$data_field[1]] = '';
									} else {
										$outputs[$item_count][$data_field[1]] = $this->itemexcelfilter->{$data_field[1]}($params_order);
									}
								}
								$outputs[$item_count]['order_seq_tmp'] = $order_seq;
								$item_count++;
							}
							# (묶음배송)표기를 위한 구분값
							$old_shipping_seq = $params_order['data_shipping']['shipping_seq'];
							$params_order['data_shipping']['old_shipping_seq'] = $old_shipping_seq;

							foreach($data_option['packages'] as $data_package){ //패키지 상품
								$params_order['data_package'] = $data_package;
								if( !$data_field[2] ){
									if($data_field[1]){
										if(in_array($data_field[1],$this->_set_currency)){
											$data_order['order'][$data_field[1]] = get_krw_currency($data_order['order'][$data_field[1]]);
										}

										if($data_field[1] == "settleprice" && $order_seq == $old_order_seq){
											$data_order['order'][$data_field[1]] = "(상동)";
										}

										$outputs[$item_count][$data_field[1]]	= $data_order['order'][$data_field[1]];
									}else{
										$outputs[$item_count][$j] = "";
										$j++;
									}
								}else{
									//$outputs[$item_count][$data_field[1]]	= $this->itemexcelfilter->{$data_field[1]}($params_order);
									if($data_field[1] == 'shipping_provider'){ //이미 데이터가 있기 때문에 필터 필요 없음 18.09.10 kmj
										$outputs[$item_count][$data_field[1]]	= $data_shipping['shipping_provider'];
									} else if( preg_match('/^option/', $data_field[1]) ) {
										$keyName = substr($data_field[1], 6);
										$outputs[$item_count][$data_field[1]]	= $data_package[$keyName];
									} else if( preg_match('/^addoption/', $data_field[1]) ) {
										$outputs[$item_count][$data_field[1]] = '';
									} else {
										$outputs[$item_count][$data_field[1]] = $this->itemexcelfilter->{$data_field[1]}($params_order);
									}
								}
								$outputs[$item_count]['order_seq_tmp'] = $order_seq;
								$item_count++;
							}

							foreach($data_option['suboptions'] as $data_suboption){ //서브옵션
								unset($params_order['data_package']);
								$params_order['data_option'] = $data_suboption;
								if ($this->orderexcelfilter->only_real != 'REAL' || $data_suboption['package_yn'] == 'n') {
									if( !$data_field[2] ){
										if($data_field[1]){
											if(in_array($data_field[1],$this->_set_currency)){
												$data_order['order'][$data_field[1]] = get_krw_currency($data_order['order'][$data_field[1]]);
											}
											if($data_field[1] == "settleprice" && $order_seq == $old_order_seq){
												$data_order['order'][$data_field[1]] = "(상동)";
											}
											$outputs[$item_count][$data_field[1]] = $data_order['order'][$data_field[1]];
										}else{
											$outputs[$item_count][$j] = "";
											$j++;
										}
									}else{
										if($data_field[1] == 'shipping_provider'){ //이미 데이터가 있기 때문에 필터 필요 없음 18.09.10 kmj
											$outputs[$item_count][$data_field[1]] = $data_shipping['shipping_provider'];
										} else if( $data_field[1] == 'addoptiontitle' ) {
											$outputs[$item_count][$data_field[1]]	= $data_suboption['title'];
										} else if( $data_field[1] == 'addoptionoption' ) {
											$outputs[$item_count][$data_field[1]]	= $data_suboption['suboption'];
										} else if( preg_match('/^option/', $data_field[1]) ) {
											$outputs[$item_count][$data_field[1]]	= '';
										} else {
											$outputs[$item_count][$data_field[1]]	= $this->itemexcelfilter->{$data_field[1]}($params_order);
										}
									}
									$outputs[$item_count]['order_seq_tmp'] = $order_seq;
									$item_count++;
								}

								foreach($data_suboption['packages'] as $data_package){
									$params_order['data_package'] = $data_package;
									if( !$data_field[2] ){
										if($data_field[1]){
											if(in_array($data_field[1],$this->_set_currency)){
												$data_order['order'][$data_field[1]] = get_krw_currency($data_order['order'][$data_field[1]]);
											}
											if($data_field[1] == "settleprice" && $order_seq == $old_order_seq){
												$data_order['order'][$data_field[1]] = "(상동)";
											}
											$outputs[$item_count][$data_field[1]]	= $data_order['order'][$data_field[1]];
										}else{
											$outputs[$item_count][$j] = "";
											$j++;
										}
									}else{
										if($data_field[1] == 'shipping_provider'){ //이미 데이터가 있기 때문에 필터 필요 없음 18.09.10 kmj
											$outputs[$item_count][$data_field[1]] = $data_shipping['shipping_provider'];
										} else if( preg_match('/^option/', $data_field[1]) || preg_match('/^addoption/', $data_field[1]) ) {
											$outputs[$item_count][$data_field[1]] = '';
										} else {
											$outputs[$item_count][$data_field[1]] = $this->itemexcelfilter->{$data_field[1]}($params_order);
										}
									}
									$outputs[$item_count]['order_seq_tmp'] = $order_seq;
									$item_count++;
								}
							} // end suboption
						} // end option
					} // end fields

					for($k=$order_count; $k<$item_count; $k++){
						if($k%$this->limitCount == 0){
							if($k > 0){
								$this->writer->close();
							}

							$this->fileExe = 'xlsx';
							$filename	= $this->nameDomain."_".$this->category."_list_".date('YmdHis')."_".str_pad( $fileCount, 4, "0", STR_PAD_LEFT ).".".$this->fileExe;
							$filepath	= $this->downPath . $filename;
							$fileList[]	= $filename;

							$this->writer  = WriterFactory::create(Type::XLSX); // for XLSX files
							if($columnWidths){
								$this->writer->colWidth = $columnWidths;
							}
							$this->set_style();
							$this->writer->openToFile($filepath);
							$this->writer->addRowWithStyle($columnNames, $this->style_title);

							$fileCount++;
						}

						if ($outputs[$k]['order_seq_tmp'] == $old_order_seq2 && array_search('관리자메모', $columnNames) !== false) {
							$outputs[$k]['admin_memo'] = '';
						}

						if ($old_order_seq2 != $outputs[$k]['order_seq']) {
							if ($styleType === "style_contents") {
								$styleType = "style_contents_yellow";
							} else {
								$styleType = "style_contents";
							}
						}

						$old_order_seq2 = $outputs[$k]['order_seq_tmp'];
						unset($outputs[$k]['order_seq_tmp']);
						foreach($outputs[$k] as $key => $val){
							$outputs[$k][$key] = html_entity_decode($val, ENT_QUOTES, 'utf-8');
						}
						$this->writer->addRowWithStyle($outputs[$k], $this->{$styleType});
					}
					$order_count = $item_count;
				} // end shipping
			} // end order
		}
		//exit;
		$this->writer->close();
		unset($orders);

		if (count($fileList) > 1) {
			$params['is_zip'] = 'Y';
		}

		$this->end_write($params, $filename, $fileList);
	}
	
	function create_member(){
		$this->load->model('excelmembermodel');

		$this->params = $this->input->post();

		if($this->params['excel_type'] == 'all'){
			$this->params['searchcount'] = $this->params['totalcount'];
		}


		if($this->params['searchcount'] <= 0){
			echo "다운로드 가능 한 ".$this->categoryKR."이 없습니다."; 
			exit;
		}
		
		//회원 정보 다운로드 비밀번호 검증
		if ($this->session->userdata['member_excel_download'] != "y") {
			if($this->params['pageid'] == "member_catalog"){
				echo "잘못된 접근입니다.";
			}else{
				$callback = "parent.openDialog('회원정보 다운로드','admin_member_download', {'width':550,'height':420});parent.$('input[name=member_download_passwd]').val('');parent.$('input[name=member_download_passwd]').focus();";
				openDialogAlert('잘못된 접근입니다.', 400, 140, 'parent', $callback);
			}
			exit;
		}

        $this->params['reg_date']	= date('Y-m-d H:i:s');

		if($this->params['searchcount'] <= $this->limitCount){
			$this->params['is_zip'] = 'N';
			$this->write_member();
		} else {
			$setData = array(
				'id'			=> '',
				'provider_seq'	=> $this->params['excel_provider_seq'],
				'manager_id'	=> $this->managerInfo['manager_id'],
				'category'		=> $this->categoryKey, //1:goods, 2:order, 3:member
				'excel_type'	=> $this->params['excel_type'], 
				'context'		=> serialize($this->params),
				'count'			=> $this->params['searchcount'],
				'state'			=> 0, //state 0:대기, 1:작업중, 2:완료
				'limit_count'	=> $this->limitCount,
				'reg_date'		=> $this->params['reg_date']
			);

			$this->db->insert('fm_queue', $setData);
			$queueID = $this->db->insert_id();
			$affect  = $this->db->affected_rows();
			if( $queueID > 0 || $affect > 0 ){
				$expectTime = ((ceil($this->params['searchcount']/$this->limitCount)) * 10) + 1200; 
				echo "엑셀 파일 생성 중 (예상 소요시간 : ".gmdate("H시 i분 s초", $expectTime).")\n파일 생성 후 ".$this->categoryKR." > 엑셀 다운로드 메뉴에서 다운로드 가능 합니다.";
			} else {
				echo "에러 발생.\n문제가 지속 될 경우 관리자에게 문의 바랍니다.";
			}
			exit;
		}
	}

	function write_member(){
		$this->load->model('snsmember');
		$this->load->model('membermodel');
		$this->load->model('excelmembermodel');
		$this->load->model('couponmodel');
		$this->load->model('Goodsreview','Boardmodel');//리뷰건

		$params = $this->params;

		$this->create_dirs();

		$title_items = array();
		$datasDB = $this->db->query('SELECT item FROM  `fm_exceldownload` WHERE  `gb` =  "MEMBER"');
		$datas	 = $datasDB->result_array();
		if (!$datas) {
			echo '항목 설정을 해 주세요.';
			exit;
		}
		$title_items = explode("|",$datas[0]['item']);
		
		//항목 쓰기
		$columnNames = array();
		foreach($title_items as $name){
			if( is_null($this->excelmembermodel->itemList[$name]) ){
				$columnNames[] = $name;
			} else {
				$columnNames[] = $this->excelmembermodel->itemList[$name];
			}
		}

		if($params['header_search_keyword']) $params['keyword'] = $params['header_search_keyword'];

		if ($params['keyword']=="이름, 아이디, 이메일, 전화번호, 핸드폰(뒷자리4), 주소, 닉네임") {
			unset($params['keyword']);
		}

		$sc = $params;

		// 판매환경
		if( $params['sitetype'] ){
			$sc['sitetype'] = implode('\',\'',$params['sitetype']);
		}

		// 가입방법
		if( $params['snsrute'] ) {
			foreach($params['snsrute'] as $key=>$val){$sc[$val] = 1;}
		}

		//등급 & 방문경로 매핑
		$groupDB	= $this->db->query("SELECT group_seq, group_name FROM fm_member_group");
		$groupArr	= $groupDB->result_array();
		$groupInfo	= array();
		foreach($groupArr as $k => $v){
			$groupInfo[$v['group_seq']] = $v['group_name'];
		}

		$refereDB	= $this->db->query("SELECT referer_group_name, referer_group_url FROM fm_referer_group");
		$refereArr	= $refereDB->result_array();
		$refereInfo = array();
		foreach($refereArr as $k => $v){
			$refereInfo[$v['referer_group_url']] = $v['referer_group_name'];
		}

		//기업회원 정보
		$busDB = $this->db->query("SELECT * FROM fm_member_business");
		foreach($busDB->result_array() as $v){
			$busInfo[$v['member_seq']] = $v;
		}
		
		$totalCount = $params['searchcount'];
		//$loopCount	= ceil($totalCount / $this->limitCount);

		$fileList	= array();
		$this->fileExe = 'xlsx';
		$filename	= $this->nameDomain."_".$this->category."_list_".date('YmdHis').".".$this->fileExe;
		$filepath	= $this->downPath . $filename;
		$fileList[] = $filename;

		$this->writer  = WriterFactory::create(Type::XLSX); // for XLSX files
		$this->set_style();

		$this->writer->openToFile($filepath);
		$this->writer->addRowWithStyle($columnNames, $this->style_title);

//$loopCount = 1;
		$k = 1;
		//for($i=0; $i<$loopCount; $i++) {
			//$sc['page']		= $i * $this->limitCount;
			//$sc['perpage']	= $this->limitCount;
			$sc['nolimit']			 = 'y';
			$sc['excel_spout_query'] = true;

			$query = $this->membermodel->admin_member_list_spout($sc);
			$queryDB = mysqli_query($this->db->conn_id, $query);

			while($v = mysqli_fetch_array($queryDB)){
				unset($this->db->queries);
				unset($this->db->query_times);

				if( !$v['member_seq'] ) continue;
				
				//등급 & 방문경로 매핑 kmj
				$v['group_name']	= $groupInfo[$v['group_seq']];
				$v['referer_name']	= $refereInfo[$v['referer_domain']];
				
				if($v['referer_name'] === NULL){
					if($v['referer_domain'] === NULL){
						$v['referer_name'] = "직접입력";
					} else {
						$v['referer_name'] = "기타";
					}
				}

				//기업회원 매핑
				if($busInfo[$v['member_seq']]){
					$v = array_merge($v, $busInfo[$v['member_seq']]);
				}

				$writeData = array();
				$thisNum = $k++;
				$writeData = $this->excelmembermodel->excel_write_spout($title_items, $v, $thisNum);

				$this->writer->addRowWithStyle($writeData, $this->style_contents);
				unset($writeData);
			}
		//} //파일 쓰기 종료

		$this->writer->close();

		$this->end_write($params, $filename, $fileList);
	}

	function create_export(){
		$this->load->model('excelexportmodel');
		$this->load->model('authmodel');
		
		$this->params				= $_POST;
		$this->params['reg_date']	= date('Y-m-d H:i:s');

		if( $this->params['excel_provider_seq'] > 1 ) {
			define('__SELLERADMIN__', true);
		}

		//개인정보 마스킹 표시 권한 체크
		$private_masking = $this->authmodel->manager_limit_act('private_masking');
		$chk_masking = array('주문자명', '주문자연락처', '주문자휴대폰', '주문자이메일', '수령인', '수령인연락처', '수령인휴대폰', '전체주소(지번)', '전체주소(도로명)', '상세주소');

		//배송책임 관련 파라미터명 수정
		if($this->params['provider_seq_selector'] > 0){
			$this->params['shipping_provider_seq'] = $this->params['provider_seq_selector'];
			unset($this->params['provider_seq'], $this->params['provider_name'], $this->params['provider_seq_selector']);
		}

		//입점사 체크
		if($this->params['excel_provider_seq'] > 1){
			$this->params['provider_seq'] = $this->params['excel_provider_seq'];
			$this->params['shipping_provider_seq'] = $this->params['excel_provider_seq'];
			$this->params['manager_id'] = $this->providerInfo['provider_id'];
		} else {
			$this->params['manager_id'] = $this->managerInfo['manager_id'];
		}

		$searchCount = 0;
		if($this->params['excel_type'] == 'search'){
			$this->params['query_type']	= 'total_record';
			$arr_export_seq				= 'search';

			$res			= $this->excelexportmodel->get_export_query_spout($this->params);
			$searchCount	= $res[0]['cnt'];
		} else {
			$arr_export_seq	= explode('|', $this->params['export_code']); 
			$arr_export_seq	= array_filter($arr_export_seq);
			$searchCount	= count($arr_export_seq);
		}

		if($searchCount <= 0){
			echo "다운로드 가능 한 ".$this->categoryKR."이 없습니다."; 
			exit;
		}

		$this->params['excel_type']	.= "_".$this->params['criteria'];

		$this->db->select('item');
		$this->db->where(array('gb' => strtoupper($this->category), 'provider_seq' => $this->params['excel_provider_seq'] ? $this->params['excel_provider_seq'] : 1));
		$query = $this->db->get('fm_exceldownload');
		$form_info = $query->result_array();
		if(!$form_info){
			echo '항목 설정을 해 주세요.';
			exit;
		}

		$title_items = explode("|",$form_info[0]['item']);
		$item_arr = $this->excelexportmodel->itemList;
		foreach($title_items as $k){
			if ( in_array($item_arr[$k], $chk_masking) ) {
				$this->isPrivate = 'Y';
				if ( $private_masking ) {
					$msg = "마스킹(*) 처리된 개인정보 항목이 포함되어 있어 엑셀 다운로드를 할 수 없습니다.";
					$msg .= "<br/ >대표운영자에게 관리자 권한 수정을 요청하거나 해당 항목을 제외하면 다운로드 가능합니다.";
					openDialogAlert($msg, 600, 180, 'parent', '');
					exit;
				}
			}
		}

		if($searchCount <= $this->limitCount){
			$this->params['list']				= $arr_export_seq;
			$this->params['limit_count']		= $this->limitCount;
			$this->params['searchcount']		= $searchCount;
			$this->params['excel_spout']		= true;
			$this->params['is_zip']				= 'N';

			$this->write_export();
		} else {
			$params					= array();
			$params['list']			= $arr_export_seq;
			$params['searchcount']	= $searchCount;
			$params					= array_merge($params, $this->params);
			$params['is_private']   = $this->isPrivate;
			
			$setData = array(
				'id'			=> '',
				'provider_seq'	=> $this->params['excel_provider_seq'],
				'manager_id'	=> $this->params['manager_id'],
				'category'		=> $this->categoryKey, 
				'excel_type'	=> $this->params['excel_type'], 
				'context'		=> serialize($params),
				'count'			=> $params['searchcount'],
				'state'			=> 0,
				'limit_count'	=> $this->limitCount,
				'reg_date'		=> $this->params['reg_date']
			);
			$this->db->insert('fm_queue', $setData);
			$queueID = $this->db->insert_id();
			$affect  = $this->db->affected_rows();
			if( $queueID > 0 || $affect > 0 ){
				$expectTime = ((ceil($params['searchcount']/$this->limitCount)) * 10) + 1200; 
				echo "엑셀 파일 생성 중 (예상 소요시간 : ".gmdate("H시 i분 s초", $expectTime).")\n파일 생성 후 주문 > 엑셀 다운로드 메뉴에서 다운로드 가능 합니다.";
			} else {
				echo "에러 발생.\n문제가 지속 될 경우 관리자에게 문의 바랍니다.";
			}
			exit;
		}
	}

	function write_export(){
		$this->scm_cfg	= config_load('scm');
		$this->arr_payment = config_load('payment');

		$this->load->model('goodsmodel');
		$this->load->model('exportmodel');
		$this->load->model('ordershippingmodel');
		$this->load->model('scmmodel');
		$this->load->model('logPersonalInformation');
		$this->load->model('excelexportmodel');

		unset($this->params['query_type']);
		$params = $this->params;
		$this->create_dirs();

		$provider_seq = $params['provider_seq'];
		if(!$provider_seq && $params['excel_provider_seq'] == 1){
			$provider_seq = 1;
		}

		if(!$provider_seq){
			echo "공급사 인덱스를 찾을 수 없습니다.";
			exit;
		}

		$title_items = array();
		$form_info = $this->db->query('SELECT item FROM `fm_exceldownload` WHERE `gb` = ? AND provider_seq = ?', 
			array(strtoupper($this->category), $provider_seq))->result_array();
		if(!$form_info){
			echo '항목 설정을 해 주세요.';
			exit;
		}

		$title_items = explode("|",$form_info[0]['item']);
		$this->excelexportmodel->requiredsck($title_items);//필수항목체크

		$item_arr		= $this->excelexportmodel->itemList;
		$columns		= array();
		$columnNames	= array();
		$columnWidths	= array();
		foreach($title_items as $k){
			if($k == 'option' || !$item_arr[$k] 
				|| ( $params['criteria'] == 'export' && (in_array($k, array('supply_price', 'consumer_price', 'price', 'ea_price'))) ) ){
				continue;
			}

			$columns[]		= $k;
			$columnNames[]	= $item_arr[$k];
			$columnWidths[] = $this->excelexportmodel->excelWidth[$k];
		}

		if( $params['criteria'] == 'item' ){
			$columns		= array_merge($columns, array_keys($this->excelexportmodel->temp));
			$columnNames	= array_merge($columnNames, array_values($this->excelexportmodel->temp));

			foreach($this->excelexportmodel->temp as $k => $v){
				$columnWidths[] = $this->excelexportmodel->excelWidth[$k];
			}
		}

		//배송사 정보
		$this->shippingInfo = array();
		$providers = $this->db->query("seLECT provider_seq, provider_name FROM fm_provider")->result_array();
		foreach($providers as $provider){
			$this->shippingInfo[$provider['provider_seq']]['provider_name'] = $provider['provider_name'];
		}

		$totalCount = $params['searchcount'];
		$loopCount	= ceil($totalCount / $this->limitCount);
		$fileList	= array();

		$funcName = "write_export_".$params['criteria'];

		$styleType = "style_contents_yellow";
		for($i=0; $i<$loopCount; $i++) {
			$this->fileExe = 'xlsx';
			$filename	= $this->nameDomain."_".$this->category."_list_".date('YmdHis')."_".str_pad( $i, 4, "0", STR_PAD_LEFT ).".".$this->fileExe;
			$filepath	= $this->downPath . $filename;
			$fileList[] = $filename;

			$this->writer = WriterFactory::create(Type::XLSX); 
			$this->writer->colWidth = $columnWidths;
			$this->set_style();

			$this->writer->openToFile($filepath);
			$this->writer->addRowWithStyle($columnNames, $this->style_title);

			$arr_export = array();
			if($params['list'] == 'search'){
				$this->params['limit_e'] = $this->limitCount;
				$this->params['limit_s'] = $i * $this->limitCount;

				$res = $this->excelexportmodel->get_export_query_spout($this->params);
				foreach($res as $k => $v){
					$arr_export[] = $v['export_code'];
				}
			} else {
				$arr_export = array_slice($params['list'], ($i * $this->limitCount), $this->limitCount);
			}

			$oldExCode = "";
			foreach($arr_export as $export_code){
				$writeData = array();
				$writeData = $this->{$funcName}($export_code, $columns);
				
				if ($oldExCode != $export_code) {
				    if ($styleType === "style_contents") {
				        $styleType = "style_contents_yellow";
				    } else {
				        $styleType = "style_contents";
				    }
				}
				
				if(is_array($writeData[0])){
				    $this->writer->addRowsWithStyle($writeData, $this->{$styleType});
				} else {
				    $this->writer->addRowWithStyle($writeData, $this->{$styleType});
				}

				if($oldExCode != $export_code){
				    $oldExCode = $export_code;
				}
			}

			$this->writer->close();
		}
		$this->end_write($params, $filename, $fileList);
	}

	function write_export_export($export_code, $columns){
		if ( substr($export_code, 0, 1) == "B" ){
			$queryWhere = "A.bundle_export_code = '{$export_code}'";
		} else {
			$queryWhere = "A.export_code = '{$export_code}'";
		}
		
		$sql = "SELECT
			A.*,
			B.member_seq,
			B.order_user_name,
			B.order_phone,
			B.order_cellphone,
			B.recipient_user_name,
			B.recipient_phone,
			B.recipient_cellphone,
			B.recipient_zipcode,
			B.recipient_address,
			B.recipient_address_street,
			B.recipient_address_detail,
			B.memo,
			B.international_country,
			B.international_town_city,
			B.international_county,
			B.international_address,
			B.regist_date as order_regist_date,
			B.admin_memo,
			B.settleprice,
			B.deposit_date,
			B.order_email,
			B.payment,
			B.pg,
			B.clearance_unique_personal_code,
			if(bundle_export_code REGEXP '^B', bundle_export_code, export_code) AS group_export_code,
			if(bundle_export_code REGEXP '^B', 'bundle', 'export') AS export_type
			FROM
				fm_goods_export A
				left join fm_order B on A.order_seq = B.order_seq
			WHERE
				".$queryWhere."
			GROUP BY group_export_code";
		$res = $this->db->query($sql)->result_array();
		$res = $res[0];

		// 배송사명
		$res['shipping_provider'] = $this->shippingInfo[$res['shipping_provider_seq']]['provider_name'];

		//출고상태
		$res['status'] = $this->exportmodel->arr_status[$res['status']];

		// 배송비
		$params = array(
			'order_seq'			=> $res['order_seq'],
			'shipping_group'	=> $res['shipping_group']
		);
		$data_order_shipping = $this->ordershippingmodel->get_shipping_only($params)->row_array();
		$res['shipping_cost'] = (float) $data_order_shipping['shipping_cost'];
		unset($params);

		//묶음배송은 묶음배송 번호로 처리
		$res['export_code']	= ($res['export_type'] == 'bundle') ? $res['bundle_export_code'] : $res['export_code'];

		// 개인정보 조회 로그
		//'member', 'memberlist', 'order', 'export', 'return', 'refund', 'orderprint' 'orderexcel', 'exportexcel'
		$this->logPersonalInformation->insert('exportexcel', $this->managerInfo['manager_seq'], $res['export_seq']);

		if($res['member_seq']){
			$member = get_data("fm_member", array("member_seq"=>$res['member_seq']));
			if($member) $res['userid'] = $member[0]['userid'];
		}

		// 카카오페이 표기 수정 :: 2015-03-05 lwh
		if($res['pg']=='kakaopay')
				$res['payment']	= '카카오페이';
		else	$res['payment']	= $this->arr_payment[$res['payment']];

		$items = ($res['export_type'] == 'bundle') ? $this->excelexportmodel->get_item($res['export_code'], 'bundle') : $this->excelexportmodel->get_item($res['export_code'], $res['order_seq']);

		//여러개인경우 콤마구분 (명칭, 명칭, )
		$res['goods_name']			= $items['goods_name'] == ',' ? '' : substr($items['goods_name'],0,-2);
		$res['tax']					= $items['tax'] == ',' ? '' : substr($items['tax'],0,-2);// 과세/비과세
		$res['purchase_goods_name']	= $items['purchase_goods_name'] == ',' ? '' : substr($items['purchase_goods_name'],0,-2);
		$res['goods_code']			= $items['goods_code'] == ',' ? '' : substr($items['goods_code'],0,-2);
		$res['goods_seq']			= $items['goods_seq'] == ',' ? '' : substr($items['goods_seq'],0,-2);
		$res['ea']					= $items['ea'];
		$res['goods_code']			= $items['goods_code'] == ',' ? '' : substr($items['goods_code'],0,-2);//상품코드
		$res['hscode']				= $items['hscode'] == ',' ? '' : substr($items['hscode'],0,-2);//수출입상품코드
		$res['location_position']	= $items['location_position'] == ',' ? '' : substr($items['location_position'],0,-2);
		$res['stock']				= $items['stock'];
		$res['export_ea']			= $this->excelexportmodel->get_export_ea($res['export_code'], $res['export_type']);
		$res['supply_price']		= $items['supply_price']; //매입가
		$res['price']				= $items['price']; //판매가
		$res['consumer_price']		= $items['consumer_price']; //정가
		$res['ea_price']			= $items['ea']*$items['price']; //판매가x출고수량

		if( $res['international'] == 'international' ){
			$res['shipping_method']				= $res['international_shipping_method'];
			$res['delivery_number']				= $res['international_delivery_no'];
			$res['delivery_company_code']		= '';
			$res['recipient_address']			= $res['international_country'].' '.$res['international_town_city'].' '.$res['international_county'];
			$res['recipient_address_detail']	= $res['international_address'];
			$res['recipient_address_all']		= $res['recipient_address']." ".$res['recipient_address_detail']; //전체주소
		}else{
			$res['shipping_method']					= $res['domestic_shipping_method'];
			$res['recipient_address_all']			= ($res['recipient_address'])?$res['recipient_address']." ".$res['recipient_address_detail']:'';//전체주소(지번)
			$res['recipient_address_street_all']	= ($res['recipient_address_street'])?$res['recipient_address_street']." ".$res['recipient_address_detail']:'';//전체주소(도로명)
		}

		if($res['shipping_method']=='direct' || $res['shipping_method']=='quick'){
			$res['delivery_number']			= '';
			$res['delivery_company_code']	= '';
		}

		$res['complete_date'] = $res['complete_date'] == '0000-00-00' ? '' : $res['complete_date'];
		$res['shipping_date'] = $res['shipping_date'] == '0000-00-00' ? '' : $res['shipping_date'];
	
		$datas = array();
		foreach ($columns as $column){
			$datas[] = $res[$column];
		}

		return $datas;
	}

	function write_export_item($export_code, $columns){
		if ( substr($export_code, 0, 1) == "B" ){
			$queryWhere = "A.bundle_export_code = '{$export_code}'";
		} else {
			$queryWhere = "A.export_code = '{$export_code}'";
		}

		$sql = "SELECT
				A.*,
				B.member_seq,
				B.order_user_name,
				B.order_phone,
				B.order_cellphone,
				B.recipient_user_name,
				B.recipient_phone,
				B.recipient_cellphone,
				B.recipient_zipcode,
				B.recipient_address,
				B.recipient_address_street,
				B.recipient_address_detail,
				B.memo,
				B.international_country,
				B.international_town_city,
				B.international_county,
				B.international_address,
				B.regist_date as order_regist_date,
				B.admin_memo,
				B.settleprice,
				B.deposit_date,
				B.payment,
				B.pg,
				B.order_email,
				B.clearance_unique_personal_code,
				C.item_seq,
				C.option_seq,
				C.suboption_seq,
				C.ea as export_ea,
				(select purchase_goods_name from fm_goods where goods_seq = D.goods_seq) as purchase_goods_name,
				D.goods_seq,
				D.goods_code,
				D.tax,
				D.goods_name,
				D.hscode,
				if(A.bundle_export_code REGEXP '^B', 'bundle', 'export') AS export_type
				FROM
					fm_goods_export as A
					INNER JOIN fm_order as B ON A.order_seq = B.order_seq
					INNER JOIN fm_goods_export_item as C ON A.export_code = C.export_code
					INNER JOIN fm_order_item as D ON C.item_seq = D.item_seq
				WHERE ".$queryWhere;
		$res = $this->db->query($sql)->result_array();
		$tax_title = array("tax"=>"과세", "exempt"=>"비과세");		// 과세/비과세
		
		$datas = array();
		foreach($res as $key => $val){
			// 배송사명
			$res[$key]['shipping_provider'] = $this->shippingInfo[$res[$key]['shipping_provider_seq']]['provider_name'];

			// 배송비
			$params = array(
				'order_seq'			=> $res[$key]['order_seq'],
				'shipping_group'	=> $res[$key]['shipping_group']
			);
			$data_order_shipping		= $this->ordershippingmodel->get_shipping_only($params)->row_array();
			$res[$key]['shipping_cost'] = (float) $data_order_shipping['shipping_cost'];
			unset($params);

			//묶음배송은 묶음배송 번호로 처리
			$res[$key]['export_code']	= ($res[$key]['export_type'] == 'bundle') ? $res[$key]['bundle_export_code'] : $res[$key]['export_code'];

			// 개인정보 조회 로그
			//'member', 'memberlist', 'order', 'export', 'return', 'refund', 'orderprint' 'orderexcel', 'exportexcel'
			$this->logPersonalInformation->insert('exportexcel', $this->managerInfo['manager_seq'], $res[$key]['export_seq']);

			if($res[$key]['member_seq']){
				$member = get_data("fm_member", array("member_seq"=>$res[$key]['member_seq']));
				if($member) $res[$key]['userid'] = $member[0]['userid'];
			}

			$items       = array();
			$optiontitle = "";
			
			if($res[$key]['option_seq']) {
				$items = $this->excelexportmodel->get_item_option($res[$key]['option_seq']);
				/* 매입가 추가 단가로 표시하려면 주석처리 하면됨. leewh 2014-09-24 */
				$items['supply_price']		= get_cutting_price($items['supply_price']*$items['ea']);
				$items['consumer_price']	= get_cutting_price($items['consumer_price']*$items['ea']);

				## 상품가 -> 할인가 로직 추가 2016-11-15
				$items['out_price'] = ($items['price']*$items['ea']);
				//promotion sale
				$sale_data = array();
				$sale_data['out_member_sale']			= ($items['member_sale']*$items['ea']);
				$sale_data['out_coupon_sale']			= ($items['download_seq'])?$items['coupon_sale']:0;
				$sale_data['out_fblike_sale']			= $items['fblike_sale'];
				$sale_data['out_mobile_sale']			= $items['mobile_sale'];
				$sale_data['out_promotion_code_sale']	= $items['promotion_code_sale'];
				$sale_data['out_referer_sale']			= $items['referer_sale'];
				// 할인 합계
				$sale_data['out_tot_sale'] = $sale_data['out_member_sale'];
				$sale_data['out_tot_sale'] += $sale_data['out_coupon_sale'];
				$sale_data['out_tot_sale'] += $sale_data['out_fblike_sale'];
				$sale_data['out_tot_sale'] += $sale_data['out_mobile_sale'];
				$sale_data['out_tot_sale'] += $sale_data['out_promotion_code_sale'];
				$sale_data['out_tot_sale'] += $sale_data['out_referer_sale'];

				$items['out_sale_price']	= $items['out_price'] - $sale_data['out_tot_sale'];
				$items['price']				= ($items['out_sale_price']/$items['ea']);
				## 상품가 -> 할인가 로직 추가 2016-11-15
				$items['price']				= get_cutting_price($items['price']);
				$items['ea_price']			= get_cutting_price($res[$key]['export_ea'] * $items['price']);

				// 재고 가져오기
				if($this->scm_cfg['use'] == 'Y' && $res[$key]['shipping_provider_seq'] == '1'){
					unset($sc);
					if	($items['option_seq'] > 0){
						$sc['option_seq']			= $items['option_seq'];
						$sc['goods_seq']			= $items['goods_seq'];
						$sc['option_type']			= 'option';
						$sc['get_type']				= 'wh';
						$sc['wh_seq']				= $res[$key]['wh_seq'];
						list($wh_data)				= $this->scmmodel->get_location_stock($sc);
						$items['location_position']	= $wh_data['location_position'];
						$stock						= $wh_data['ea'];
						$items['stock']				= $stock;
					}
				}else{
					$stock = $this->goodsmodel->get_goods_option_stock($items['goods_seq'],$items['option1'],$items['option2'],$items['option3'],$items['option4'],$items['option5']);
					$items['stock'] = $stock;
				}

				if ($items['title1']) {
				    if($totitem['option']){
				        $optiontitle .= ', '.$items['title1'].':'.$items['option1'];
				    } else {
				        $optiontitle .= $items['title1'].':'.$items['option1'];
				    }
				}
				
				if($items['title2']) $optiontitle .= ', '.$items['title2'].':'.$items['option2'];
				if($items['title3']) $optiontitle .= ', '.$items['title3'].':'.$items['option3'];
				if($items['title4']) $optiontitle .= ', '.$items['title4'].':'.$items['option4'];
				if($items['title5']) $optiontitle .= ', '.$items['title5'].':'.$items['option5'];

				if($optiontitle){
				    $res[$key]['goods_name'] .= " (".$optiontitle.")";
				}

				//추가입력옵션
				$sql = "SELECT
					*
					FROM
						fm_order_item_input C
						LEFT JOIN fm_order_item B ON C.item_seq = B.item_seq
						LEFT JOIN fm_order A ON B.order_seq = A.order_seq
					WHERE
						A.order_seq = ?
						AND C.item_seq = ? 
						AND C.item_option_seq = ?
					ORDER BY C.item_input_seq";
				$query = $this->db->query($sql, array($res[$key]['order_seq'], $res[$key]['item_seq'], $items['item_option_seq']));
				
				$inputoption = "";
				foreach ($query->result_array() as $rowinput) {
					$inputoption .= $rowinput['title'].':'.$rowinput['value'];
				}//endforeach

				if( $inputoption) {
				    if($optiontitle){
				        $res[$key]['goods_name'] = str_replace(')', ' + ', $res[$key]['goods_name']);
				        $res[$key]['goods_name'] .= $inputoption.")";
				    } else {
				        $res[$key]['goods_name'] .= " (".$inputoption.")";
				    } 
				}
			} else if($res[$key]['suboption_seq']) {
				$items = $this->excelexportmodel->get_sub_option($res[$key]['suboption_seq']);
				/* 매입가 추가 단가로 표시하려면 주석처리 하면됨. leewh 2014-09-24 */
				$items['supply_price']	= get_cutting_price($items['supply_price']);
				$items['consumer_price']= get_cutting_price($items['consumer_price']);

				## 상품가 -> 할인가 로직 추가 2016-11-15
				$items['out_price'] = ($items['price']*$items['ea']);

				//promotion sale
				$sale_data = array();
				$sale_data['out_member_sale'] = ($items['member_sale']*$items['ea']);

				// 할인 합계
				$sale_data['out_tot_sale']	= $sale_data['out_member_sale'];
				$items['out_sale_price']	= $items['out_price'] - $sale_data['out_tot_sale'];
				$items['price']				= ($items['out_sale_price']/$items['ea']);

				## 상품가 -> 할인가 로직 추가 2016-11-15
				$items['price']		= get_cutting_price($items['price']);
				$items['ea_price']	= get_cutting_price($res[$key]['export_ea'] * $items['price']);
				if ($items['title']) {
					$res[$key]['goods_name'] .= " (".$items['title'].':'.$items['suboption'].")";
				}

				$stock = $this->goodsmodel->get_goods_suboption_stock($items['goods_seq'],$items['title'],$items['suboption']);
				$items['ea_price'] = $stock;
			}

			$res[$key]['price_export_ea'] = get_cutting_price($res[$key]['export_ea'] * $items['price']);
			if ( empty($items['tax']) ) unset($items['tax']); // fm_order_item_option의 tax 없으면 unset
			$res[$key] = array_merge($res[$key], $items);

			if( $res[$key]['international'] == 'international' ){
				$res[$key]['shipping_method']			= $res[$key]['international_shipping_method'];
				$res[$key]['delivery_number'] 			= $res[$key]['international_delivery_no'];
				$res[$key]['delivery_company_code']		= '';

				$res[$key]['recipient_address']			= $res[$key]['international_country'].' '.$res[$key]['international_town_city'].' '.$res[$key]['international_county'];
				$res[$key]['recipient_address_detail']	= $res[$key]['international_address'];
				$res[$key]['recipient_address_all']	= $res[$key]['recipient_address']." ".$res[$key]['recipient_address_detail']; //전체주소
			}else{
				$res[$key]['shipping_method']			= $res[$key]['domestic_shipping_method'];
				$res[$key]['delivery_number']			= $res[$key]['delivery_number'];
				$res[$key]['delivery_company_code']	= $res[$key]['delivery_company_code'];

				$res[$key]['recipient_address_all']		= ($res[$key]['recipient_address'])?$res[$key]['recipient_address']." ".$res[$key]['recipient_address_detail']:'';//전체주소(지번)
				$res[$key]['recipient_address_street_all']= ($res[$key]['recipient_address_street'])?$res[$key]['recipient_address_street']." ".$res[$key]['recipient_address_detail']:'';//전체주소(도로명)
			}

			if($res[$key]['shipping_method']=='direct' || $res[$key]['shipping_method']=='quick'){
				$res[$key]['delivery_number']			= '';
				$res[$key]['delivery_company_code']		= '';
			}

			$res[$key]['status']	= $this->exportmodel->arr_status[$res[$key]['status']];
			$res[$key]['tax']		= $tax_title[$res[$key]['tax']];			// 과세/비과세

			// 카카오페이 표기 수정 :: 2015-03-05 lwh
			if($res[$key]['pg']=='kakaopay'){
				$res[$key]['payment'] = '카카오페이';
			}else{
				$res[$key]['payment'] = $this->arr_payment[$res[$key]['payment']];
			}

			$res[$key]['complete_date'] = $res[$key]['complete_date'] == '0000-00-00' ? '' : $res[$key]['complete_date'];
			$res[$key]['shipping_date'] = $res[$key]['shipping_date'] == '0000-00-00' ? '' : $res[$key]['shipping_date'];
			
			foreach ($columns as $column){
				$datas[$key][] = $res[$key][$column];
			}
		}

		return $datas;
	}

	function create_scmgoods(){
		$this->load->model('scmexcel');
		
		$this->params				= $_POST;
		$this->params['reg_date']	= date('Y-m-d H:i:s');

		//입점사 체크
		if($this->params['excel_provider_seq'] > 1){
			echo "다운로드 권한 없음";
			exit;
		}

		$searchCount = 0;
		if($this->params['excel_type'] == 'search'){
			$this->params['query_type']	= 'total_record';
			$arr_scm_seq				= 'search';

			$res			= $this->scmexcel->get_scm_query_spout($this->params);
			$searchCount	= $res[0]['cnt'];
			unset($this->params['query_type']);
		} else {
			foreach($this->params['scm_code'] as $k => $v){
				$tmpArr = explode('option', $v); 
				$arr_scm_seq[$k]['goods_seq']	= $tmpArr[0];
				$arr_scm_seq[$k]['option_seq']	= $tmpArr[1];
			}
			$searchCount	= count($arr_scm_seq);
		}

		if($searchCount <= 0){
			echo "다운로드 가능 한 ".$this->categoryKR."이 없습니다."; 
			exit;
		}

		if($searchCount <= $this->limitCount){
			$this->params['list']				= $arr_scm_seq;
			$this->params['limit_count']		= $this->limitCount;
			$this->params['searchcount']		= $searchCount;
			$this->params['excel_spout']		= true;
			$this->params['is_zip']				= 'N';

			$this->write_scmgoods();
		} else {
			$params					= array();
			$params['list']			= $arr_scm_seq;
			$params['searchcount']	= $searchCount;
			$params					= array_merge($params, $this->params);
			
			$setData = array(
				'id'			=> '',
				'provider_seq'	=> $this->params['excel_provider_seq'],
				'manager_id'	=> $this->managerInfo['manager_id'],
				'category'		=> $this->categoryKey, 
				'excel_type'	=> $this->params['excel_type'].'_scmgoods', 
				'context'		=> serialize($params),
				'count'			=> $params['searchcount'],
				'state'			=> 0,
				'limit_count'	=> $this->limitCount,
				'reg_date'		=> $this->params['reg_date']
			);
			$this->db->insert('fm_queue', $setData);
			$queueID = $this->db->insert_id();
			$affect  = $this->db->affected_rows();
			if( $queueID > 0 || $affect > 0 ){
				$expectTime = ((ceil($params['searchcount']/$this->limitCount)) * 10) + 1200; 
				echo "엑셀 파일 생성 중 (예상 소요시간 : ".gmdate("H시 i분 s초", $expectTime).")\n파일 생성 후 재고관리 > 엑셀 다운로드 메뉴에서 다운로드 가능 합니다.";
			} else {
				echo "에러 발생.\n문제가 지속 될 경우 관리자에게 문의 바랍니다.";
			}
			exit;
		}
	}

	function write_scmgoods(){
		$this->load->model('scmexcel');

		$params = $this->params;
		$this->create_dirs();

		$this->scmexcel->m_sForType = 'default_'.$params['excel_form'];
		$this->scmexcel->set_cell_list();

		$columns		= array();
		$columnNames	= array();
		$columnWidths	= array();
		foreach($this->scmexcel->m_aCellList['master'] as $k => $v){
			$columns[]		= $k;
			$columnNames[]	= $v;
			//$columnWidths[] = $this->excelexportmodel->excelWidth[$k];
		}
		foreach($this->scmexcel->m_aCellList['info'] as $k => $v){
			$columns[]		= $k;
			$columnNames[]	= $v;
			//$columnWidths[] = $this->excelexportmodel->excelWidth[$k];
		}

		if(!$columns){
			echo '데이터 형식을 찾을 수 없습니다.';
			exit;
		}

		$totalCount = $params['searchcount'];
		$loopCount	= ceil($totalCount / $this->limitCount);
		$fileList	= array();

		for($i=0; $i<$loopCount; $i++) {
			$arr_scm = array();
			if($params['list'] == 'search'){
				$this->params['query_type'] = 'list';
				$this->params['limit_e']	= $this->limitCount;
				$this->params['limit_s']	= $i * $this->limitCount;

				$res = $this->scmexcel->get_scm_query_spout($this->params);
				unset($this->params['query_type']);

				foreach($res as $k => $v){
					$arr_scm[$k]['goods_seq']	= $v['goods_seq'];
					$arr_scm[$k]['option_seq']	= $v['option_seq'];
				}
			} else {
				$arr_scm = $this->params['list'];
			}

			$this->fileExe = 'xlsx';
			$filename	= $this->nameDomain."_".$this->category."_list_".date('YmdHis')."_".str_pad( $i, 4, "0", STR_PAD_LEFT ).".".$this->fileExe;
			$filepath	= $this->downPath . $filename;
			$fileList[] = $filename;

			$this->writer = WriterFactory::create(Type::XLSX); 
			if($columnWidths){
				$this->writer->colWidth = $columnWidths;
			}
			$this->set_style();

			$this->writer->openToFile($filepath);
			$this->writer->addRowWithStyle($columnNames, $this->style_title);

			foreach($arr_scm as $scm_data){
				$dataParams					= array();
				$dataParams['query_type']	= 'data';
				$dataParams['goods_seq']	= $scm_data['goods_seq'];
				$dataParams['option_seq']	= $scm_data['option_seq'];
				$dataParams['orderby']		= $this->params['orderby'];

				$writeData = array();
				$writeData = $this->scmexcel->get_scm_query_spout($dataParams, $columns);
				if(is_array($writeData[0])){
					$this->writer->addRowsWithStyle($writeData, $this->style_contents);
				} else {
					$this->writer->addRowWithStyle($writeData, $this->style_contents);
				}
			}
			unset($writeData);

			$this->writer->close();
		}

		$params['excel_type'] .= "_scmgoods"; 
		$this->end_write($params, $filename, $fileList);
	}
	
	// 회원등급할인세트 엑셀 다운로드 추가 :: 2019-09-17 pjw
	function create_membersale(){
	
		$this->load->model('goodsexcel');
		
		// 기본 파라미터 정의
		$this->params				 = $_POST;
		$this->params['reg_date']	 = date('Y-m-d H:i:s');
		$this->params['limit_count'] = $this->limitCount;

		//입점사 체크
		if($this->params['excel_provider_seq'] > 1){
			echo "다운로드 권한 없음";
			exit;
		}
		
		// 총 상품 갯수 조회
		$res			= $this->goodsexcel->get_membersale_goods($this->params, 'cnt');
		$goods_info		= $res['goods_info'];
		$brand_info		= $res['brand_info'];
		$provider_info	= $res['provider_info'];
		$searchCount	= !empty($goods_info['cnt']) ? $goods_info['cnt'] : 0;

		if($searchCount <= 0){
			echo "다운로드 가능 한 ".$this->categoryKR."이 없습니다."; 
			exit;
		}

		if($searchCount <= $this->limitCount){
			$this->params['list']				= $arr_scm_seq;
			$this->params['limit_count']		= $this->limitCount;
			$this->params['searchcount']		= $searchCount;
			$this->params['brand_info']			= $brand_info;
			$this->params['provider_info']		= $provider_info;
			$this->params['excel_spout']		= true;
			$this->params['is_zip']				= 'N';

			$this->write_membersale();
		} else {
			$params					= array();
			$params['list']			= $arr_scm_seq;
			$params['searchcount']	= $searchCount;
			$params					= array_merge($params, $this->params);
			
			$setData = array(
				'id'			=> '',
				'provider_seq'	=> $this->params['excel_provider_seq'],
				'manager_id'	=> $this->managerInfo['manager_id'],
				'category'		=> $this->categoryKey, 
				'excel_type'	=> $this->params['excel_type'].'_membersale', 
				'context'		=> serialize($params),
				'count'			=> $params['searchcount'],
				'state'			=> 0,
				'limit_count'	=> $this->limitCount,
				'reg_date'		=> $this->params['reg_date']
			);
			$this->db->insert('fm_queue', $setData);
			$queueID = $this->db->insert_id();
			$affect  = $this->db->affected_rows();
			if( $queueID > 0 || $affect > 0 ){
				$expectTime = ((ceil($params['searchcount']/$this->limitCount)) * 10) + 1200; 
				echo "엑셀 파일 생성 중 (예상 소요시간 : ".gmdate("H시 i분 s초", $expectTime).")\n파일 생성 후 재고관리 > 엑셀 다운로드 메뉴에서 다운로드 가능 합니다.";
			} else {
				echo "에러 발생.\n문제가 지속 될 경우 관리자에게 문의 바랍니다.";
			}
			exit;
		}
	}
	
	// 회원등급할인세트 엑셀 생성 추가 :: 2019-09-17 pjw
	function write_membersale(){

		// 초기 파라미터 및 폴더 생성
		$params = $this->params;
		$this->create_dirs();
		
		// 모델로드
		$this->load->model('goodsexcel');
		
		$totalCount = $params['searchcount'];
		$loopCount	= ceil($totalCount / $this->limitCount);
		$fileList	= array();

		// 엑셀 데이터 가져옴
		$provider_info	= $this->params['provider_info'];
		$brand_info		= $this->params['brand_info'];
		
		// 엑셀 컬럼 정보 가져옴
		$column_info = $this->goodsexcel->get_membersale_cell_list(strtolower($provider_info['commission_type']));	

		if(!$column_info){
			echo '데이터 형식을 찾을 수 없습니다.';
			exit;
		}

		// 상단 열 정보 작성
		$firstColumns	= array();
		$secondColumns	= array();
		$thirdColumns	= array();

		// 시작열 선언 (셀병합을 위해 사용)
		
		$colKey			= 0;
		$merge_cells	= array();

		foreach($column_info['cell'] as $key => $columns){

			// 수정, 업데이트 여부 나타내는 컬럼
			if( in_array($key, $column_info['able_modify_cell']) )		$firstColumns[] = '수정 + 업데이트';
			else if( in_array($key, $column_info['able_check_cell']) )	$firstColumns[] = '수정가능';
			else														$firstColumns[] = '';

			// 메인 열 뿌림
			$secondColumns[] = $columns['name'];

			// 메인 열에서 서브로 붙은 컬럼 삽입
			if(!empty($columns['subcell'])){
				// 셀 병합 처리
				$nextColKey			= $colKey + (count($columns['subcell']) - 1);
				$merge_cells[]		= $this->get_column_key($colKey).'2:'.$this->get_column_key($nextColKey).'2';
				$colKey				= $nextColKey;

				// 3번째 줄 컬럼 넣기
				$cellflag = 0;
				foreach($columns['subcell'] as $subcell){
					
					// 위에서 이미 추가가 되어있으므로 첫번째만 무시후 서브 셀만큼 빈값 추가
					if($cellflag > 0){
						$firstColumns[]		= '';
						$secondColumns[]	= '';
					}
					$thirdColumns[]		= $subcell;	
					$cellflag++;
				}
				
			}else{

				// 서브가 없으면 rospan 2줄 처리
				$merge_cells[]		= $this->get_column_key($colKey).'2:'.$this->get_column_key($colKey).'3';
				$thirdColumns[]		= '';
			}

			$colKey++;
		}


		// 쪼갠 데이터 별로 엑셀파일 생성
		for($i=0; $i<$loopCount; $i++) {
			$this->params['limit_e']	= $this->limitCount;
			$this->params['limit_s']	= $i * $this->limitCount;
			                           
			// 데이터 가져오기
			$tmpgoods_list	= $this->goodsexcel->get_membersale_goods($this->params);
			$goods_list		= $tmpgoods_list['result_goods_list'];


			// 추가로 병합할 열 처리
			$merge_cells[] = 'A4:A'.($tmpgoods_list['total_row'] + 3);
			$merge_cells[] = 'B4:B'.($tmpgoods_list['total_row'] + 3);
			$merge_cells[] = 'C4:C'.($tmpgoods_list['total_row'] + 3);
			$merge_cells[] = 'D4:D'.($tmpgoods_list['total_row'] + 3);

			
			// 생성할 엑셀정보 설정
			$file_ext	= 'xlsx';
			$filename	= $provider_info['provider_name']."_".$brand_info['title']."_list_".date('YmdHis')."_".str_pad( $i, 4, "0", STR_PAD_LEFT ).".".$file_ext;
			$filepath	= $this->downPath . $filename;
			$fileList[] = $filename;

			$this->writer				= WriterFactory::create(Type::XLSX); 
			$this->writer->mergeCells	= $merge_cells;
			
			// 셀 width 크기 지정
			if($column_info['cell_width']){
				$this->writer->colWidth = $column_info['cell_width'];
			}
			$this->set_style();

			$this->writer->openToFile($filepath);
			$this->writer->addRowWithStyle($firstColumns, $this->style_red_text);
			$this->writer->addRowWithStyle($secondColumns, $this->style_title_membersale);
			$this->writer->addRowWithStyle($thirdColumns, $this->style_title_membersale);
			
			
			if(count($goods_list) > 0){

				$first_flag = true;	// 첫번째 데이터 행 여부
				$rownum		= 4;	// 엑셀 row 번호 (메타정보가 3행 이르모 4부터 시작)

				foreach($goods_list as $key => $goods){

					// 첫 행인 경우에만 앞에 브랜드, 입점사 정보 삽입
					if($first_flag){
						$provider_seq		= $provider_info['provider_seq'];
						$provider_name		= $provider_info['provider_name'];
						$brand_seq			= $brand_info['id'];
						$brand_name			= $brand_info['title'];
						$first_flag			= false;
					}else{
						$provider_seq		= '';
						$provider_name		= '';
						$brand_seq			= '';
						$brand_name			= '';
					}
					
					// 엑셀에 노출할 정보 재가공
					$goods_excel	= array();
					$goods_excel[]	= $provider_seq;
					$goods_excel[]	= $provider_name;
					$goods_excel[]	= $brand_seq;
					$goods_excel[]	= $brand_name;
					$goods_excel[]	= 'goods';
					$goods_excel[]	= $goods['goods_seq'];
					$goods_excel[]	= $goods['goods_name'];

					// 각 공급방식 별로 상품 셀을 맞춰준다
					for($i = 0; $i <  count($firstColumns) - 8; $i++)		$goods_excel[] = '';

					// 회원등급세트 번호
					$goods_excel[]	= $goods['sale_seq'];

					
					$this->writer->addRowWithStyle($goods_excel, $this->style_contents);
					++$rownum;

					// 상품 필수옵션 뿌리기
					if(!empty($goods['option_cells'])){
						
						foreach($goods['option_cells'] as $options){

							// 엑셀에 노출할 옵션 재가공
							$option_excel = array();
							foreach($options as $option){
								$option_excel[] = str_replace('{rownum}', $rownum, $option);
							}

							$this->writer->addRowWithStyle($option_excel, $this->style_contents);
							unset($option_excel);
							++$rownum;
						
						}
					}

					// 상품 추가옵션 뿌리기
					if(!empty($goods['suboption_cells'])){
						
						foreach($goods['suboption_cells'] as $suboptions){

							// 엑셀에 노출할 옵션 재가공
							$suboption_excel = array();
							foreach($suboptions as $suboption){
								$suboption_excel[] = str_replace('{rownum}', $rownum, $suboption);
							}

							$this->writer->addRowWithStyle($suboption_excel, $this->style_contents);
							unset($suboption_excel);
							++$rownum;
						
						}
					}

				}
			}


			unset($goods_list);

			$this->writer->close();
		}

		$params['excel_type'] .= "_membersale"; 
		$this->end_write($params, $filename, $fileList);

	}

	
	// 엑셀 열의 알파벳 리턴
	function get_column_key($keynum){
		$alpha_list = array('A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z');

		if($keynum < count($alpha_list)){
			return $alpha_list[$keynum];
		}else{
			$front	= $keynum / (count($alpha_list) - 1);
			$end	= $keynum % (count($alpha_list) - 1);
			
			return $alpha_list[$front].$alpha_list[$end];
		}
	}

}