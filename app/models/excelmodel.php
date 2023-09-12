<?php
require_once(APPPATH.'/libraries/Spout/Autoloader/autoload.php'); //excel library

use Box\Spout\Reader\ReaderFactory;
use Box\Spout\Writer\WriterFactory;
use Box\Spout\Common\Type;
use Box\Spout\Writer\Style\StyleBuilder;
use Box\Spout\Writer\Style\Color;
use Box\Spout\Writer\Style\Border;
use Box\Spout\Writer\Style\BorderBuilder;

class excelmodel extends CI_Model {

	var $setting_type					= 'ORDER'; // 주문별:ORDER 상품별:ITEM
	var $all_cells						= array();
	var $itemList						= array();
	var $require_cells					= array();
	var $data_exceldownload				= array();
	var $upload_fields					= array();
	var $_set_currency					= array();	//통화 절삭기준 적용 필드
	var $debug 							= false;

	public function set_cell(){
		$this->all_cells = $this->get_default_cell();
		$this->set_default_cell();
		$this->set_service_type();
		$this->service_filter();
	}


	## 그룹번호1자리
	## 순차번호 3자리
	## 주문엑셀(0,1)
	## 상품엑셀(0,1)
	## 주문엑셀 업로드필수구분(0,1),
	## 상품엑셀 업로드필수구분(0,1),
	## NULL값0처리여부(0,1),
	public function get_default_cell()
	{

		$this->_set_currency			= array("settleprice","emoney","cash","enuri");

		$cellList['101011000']	= array('배송책임',				'shipping_provider',					true,	80);
		$cellList['102011000']	= array('판매자',				'provider_name',						true,	80);
		$cellList['103011100']	= array('출고그룹',				'shipping_seq',							true,	80);
		$cellList['104011000']	= array('주문번호+출고그룹',	'order_seq_shipping_seq',				true,	150);
		$cellList['105011000']	= array('주문번호',				'order_seq',							false,	130);
		$cellList['106011000']	= array('Npay주문번호',			'npay_order_id',						false,	130);
		$cellList['107011000']	= array('주문일',				'regist_date',							false,	120);
		$cellList['108011000']	= array('주문자명',				'order_user_name',						false,	80);
		$cellList['109011000']	= array('주문자 회원등급 (주문일 기준)',	'group_name_order',					 false,	80);
		$cellList['109012000']	= array('주문자 회원등급 (현재 기준)',	'group_name',					 false,	80);
		$cellList['110011000']	= array('주문자아이디',			'userid',								true,	180);
		$cellList['111011000']	= array('주문자연락처',			'order_phone',							false,	80);
		$cellList['112011000']	= array('주문자휴대폰',			'order_cellphone',						false,	80);
		$cellList['113011000']	= array('주문자이메일',			'order_email',							false,	180);
		$cellList['114011000']	= array('개인통관고유부호',		'clearance_unique_personal_code',		false,	100);
		$cellList['115011000']	= array('사용자메모',			'memo',									true,	300);
		$cellList['116011000']	= array('관리자메모',			'admin_memo',							false,	300);
		$cellList['117011000']	= array('주문경로',				'referer_name',							false,	80);
		$cellList['118011000']	= array('판매마켓',				'linkage_mallname',						true,	80);
		$cellList['119011000']	= array('출고일',				'export_date',							true,	80);
		$cellList['120011000']	= array('받는방법',				'shipping_method',						true,	80);
		$cellList['121011000']	= array('택배사',				'delivery_company',						true,	80);
		$cellList['122011000']	= array('운송장번호',			'delivery_number',						true,	80);
		$cellList['123011000']	= array('받는정보',				'recipient_info',						true,	450);
		$cellList['124011000']	= array('수령인',				'recipient_user_name',					false,	80);
		$cellList['125011000']	= array('수령인연락처',			'recipient_phone',						false,	80);
		$cellList['126011000']	= array('수령인휴대폰',			'recipient_cellphone',					false,	80);
		$cellList['127011000']	= array('우편번호',				'recipient_zipcode',					true,	80);
		$cellList['128011000']	= array('전체주소(지번)',		'recipient_address_all',				true,	450);
		$cellList['129011000']	= array('전체주소(도로명)',		'recipient_address_street_all',			true,	450);
		$cellList['130011001']	= array('배송비',				'shipping_cost',						true,	80);
		$cellList['131011000']	= array('상품고유번호',			'goods_seq',							true,	80);
		$cellList['132011000']	= array('수출입상품코드',		'hscode',								true,	80);
		$cellList['133011000']	= array('바코드',				'goods_code',							true,	80);
		$cellList['134011000']	= array('Npay상품주문번호',		'npay_product_order_id',				true,	80);
		$cellList['138011000']	= array('톡체크아웃주문번호',		'talkbuy_order_id',				false,	80);
		$cellList['139011000']	= array('톡체크아웃상품주문번호', 'talkbuy_product_order_id',		true,	80);
		$cellList['135001010']	= array('출고상품번호',			'export_item_seq',						true,	120);
		$cellList['136011000']	= array('상품명',				'goods_name',							true,	350);

		if($this->setting_type == "ITEM"){
			//옵션 필드 추가
			$cellList['136012000']	= array('옵션',				'option',							true,	80,
				array(
					array('옵션명1',			'optiontitle1',		true,	80),
					array('옵션값1',			'optionoption1',	true,	80),
					array('옵션명2',			'optiontitle2',		true,	80),
					array('옵션값2',			'optionoption2',	true,	80),
					array('옵션명3',			'optiontitle3',		true,	80),
					array('옵션값3',			'optionoption3',	true,	80),
					array('옵션명4',			'optiontitle4',		true,	80),
					array('옵션값4',			'optionoption4',	true,	80),
					array('옵션명5',			'optiontitle5',		true,	80),
					array('옵션값5',			'optionoption5',	true,	80),
					array('추가옵션명',		'addoptiontitle',	true,	80),
					array('추가옵션값',		'addoptionoption',	true,	80)
				)	
			);
		}

		if(serviceLimit('H_SC')){
		$cellList['137011000']	= array('로케이션',				'location',								true,	80);
		}
		$cellList['138011001']	= array('현재고',				'stock',								true,	80);
		//$cellList['139011000']	= array('매입용상품명',		'purchase_goods_name',					true,	80);
		$cellList['140011001']	= array('매입가',				'supply_price',							true,	80);
		$cellList['141011001']	= array('정가',					'consumer_price',						true,	80);
		$cellList['142011001']	= array('판매가',				'price',								true,	80);
		$cellList['143011001']	= array('판매가x수량',			'ea_price',								true,	80);
		$cellList['144011000']	= array('과세여부',				'tax',									true,	80);
		$cellList['145011000']	= array('추가입력옵션',			'subinputoption',						true,	80);
		$cellList['146011001']	= array('주문수량',				'ea',									true,	80);
		$cellList['147011001']	= array('취소수량',				'refund_ea',							true,	80);
		$cellList['148011001']	= array('보낸수량',				'export_ea',							true,	80);
		$cellList['149011111']	= array('보낼수량',				'request_ea',							true,	80);
		$cellList['150011000']	= array('주문상품상태',			'step',									true,	80);
		$cellList['151011001']	= array('상품쿠폰할인',			'goods_coupon_sale',					true,	80);
		$cellList['152011001']	= array('상품코드할인',			'promotion_code_sale',					true,	80);
		$cellList['152111001']	= array('이벤트할인',			'event_sale',							true,	80);
		$cellList['152211001']	= array('복수구매할인',			'multi_sale',							true,	80);
		$cellList['153011001']	= array('회원등급할인',			'member_sale',							true,	80);
		$cellList['154011001']	= array('모바일할인',			'mobile_sale',							true,	80);
		//$cellList['155011001']	= array('좋아요할인',			'fblike_sale',							true,	80);
		$cellList['156011001']	= array('유입경로할인',			'referer_sale',							true,	80);
		$cellList['157011001']	= array('배송비쿠폰',			'shipping_coupon_sale',					true,	80);
		$cellList['158011001']	= array('배송비코드',			'shipping_promotion_code_sale',			true,	80);
		$cellList['159011001']	= array('마일리지사용',			'emoney',								true,	80);
		$cellList['160011001']	= array('예치금사용',			'cash',									true,	80);
		$cellList['161011001']	= array('에누리',				'enuri',								true,	80);
		$cellList['162011001']	= array('결제금액',				'settleprice',							false,	80);
		$cellList['163011000']	= array('결제일',				'deposit_date',							false,	80);
		$cellList['164011000']	= array('결제방법',				'payment',								true,	80);
		$cellList['165011001']	= array('지급마일리지',			'reserve',								true,	80);
		$cellList['166011001']	= array('지급포인트',			'point',								true,	80);
		$cellList['167011000']	= array('배송그룹번호',			'shipping_group',						true,	80);
		$cellList['168011000']	= array('배송비결제방식',		'shipping_pay_type',					true,	80);
		if($this->setting_type == "ITEM"){
		$cellList['169001001']	= array('마일리지사용(배송비)',			'delivery_emoney',				true,	80);
		$cellList['170001001']	= array('예치금사용(배송비)',			'delivery_cash',				true,	80);
		$cellList['17100100']	= array('에누리(배송비)',				'delivery_enuri',				true,	80);
		}
		$cellList['181011000']	= array('주문서쿠폰할인금액',		'ordersheet_sale',					true,	80);

		foreach($cellList as $code => $data){
			if(substr($code,4,1) == 0 &&  $this->setting_type == 'ORDER' ){
				unset($cellList[$code]);
			}
			if(substr($code,5,1) == 0 &&  $this->setting_type == 'ITEM' ){
				unset($cellList[$code]);
			}
		}
		return $cellList;
	}

	public function set_default_cell()
	{
		foreach($this->all_cells as $code => $data){
			if(substr($code,6,1) == 1){
				$this->require_cells['ORDER'][$code] =  $data;
			}
			if(substr($code,7,1) == 1){
				$this->require_cells['ITEM'][$code] =  $data;
			}
		}
	}

	## 현재 쇼핑몰의 서비스 정보 및 관리자 구분 세팅
	public function set_service_type(){
		// 서비스 구분
		if	(serviceLimit('H_AD')){
			$this->m_sServiceType	= 'A';	// ADVANCED ( 입점몰 )
		}else{
			$this->m_sServiceType	= 'N';	// NORMAL ( 일반몰 )
		}

		// 관리자 페이지 구분
		if	(preg_match('/^\/selleradmin/', $_SERVER['REQUEST_URI']) || preg_match('/provider_reg$/', $_SERVER['REQUEST_URI'])){
			$this->m_sAdminType		= 'S';	// SELLERADMIN ( 입점사관리자 )
		}else{
			$this->m_sAdminType		= 'A';		// ADMIN ( 관리자 )
		}
	}

	## 서비스별,관리자별 필드 조정
	public function service_filter()
	{
		foreach($this->all_cells as $code => $data){
			if($this->m_sServiceType == 'N' && in_array($code,array('101011000','102011000'))){ // 일반몰일 경우
				unset($this->all_cells[$code]);
			}
			if($this->m_sAdminType == 'S' && in_array($code,array('140011001','162011001','163011000','164011000'))){ // 입점사 관리자모드일 경우
				unset($this->all_cells[$code]);
			}
		}

		foreach($this->data_exceldownload['item'] as $k => $v){
			if	($this->m_sServiceType == 'N' && in_array($v, array('shipping_provider', 'provider_name')))
				unset($this->data_exceldownload['item'][$k]);
			if	($this->m_sServiceType == 'S' && in_array($v, array('settleprice', 'deposit_date', 'payment')))
				unset($this->data_exceldownload['item'][$k]);
		}
	}

	public function get_exceldownload($seq)
	{
		$bind[] = $seq;
		$query = "select * from fm_exceldownload where seq=?";
		$query	= $this->db->query($query,$bind);
		$data = $query->row_array();
		$data['item'] = explode('|',$data['item']);
		$realKey = array_search('REAL', $data['item']);
		if ($realKey > 0) {
			$this->only_real = 'REAL';
			unset($data['item'][$realKey]);
		} else {
			$this->only_real = '';
		}
		
		$this->data_exceldownload = $data;
	}

	/* exceldownload_spout 으로 대체. exceldownload()는 사용안함 2018-09-06 */
	public function exceldownload($result,$provider_data)
	{
		if($this->data_exceldownload['criteria'] == 'ORDER'){
			$this->exceldownload_for_order($result,$provider_data);
		}else if($this->data_exceldownload['criteria'] == 'ITEM'){
			$this->exceldownload_for_item($result,$provider_data);
		}
	}

	//spout download kmj
	public function exceldownload_spout($result,$provider_data,$params)
	{
		//$this->get_exceldownload($params['form_seq']);
		if($this->data_exceldownload['criteria'] == 'ORDER'){
			$this->exceldownload_for_order_spout($result,$provider_data,$params);
		}else if($this->data_exceldownload['criteria'] == 'ITEM'){
			$this->exceldownload_for_item_spout($result,$provider_data,$params);
		}
	}

	//spout download for order kmj
	public function exceldownload_for_order_spout($result,$provider_data,$params)
	{
		$this->load->library('orderexcelfilter');
		$this->load->model('openmarketmodel');
		$this->load->model('order2exportmodel');
		$this->load->model('shippingmodel');
		$this->setting_type	= $this->data_exceldownload['criteria'];
		$linkage_malldata	= $this->openmarketmodel->get_linkage_support_mall('shoplinker');
		if($linkage_malldata) foreach($linkage_malldata as $key=>$malldata) $linkage_mallnames[$malldata['mall_code']] = $malldata['mall_name'];
		$this->orderexcelfilter->data_linkage	= $linkage_mallnames;
		$this->orderexcelfilter->data_provider	= $provider_data;
		$this->orderexcelfilter->data_paymethod	= code_load('orderexcel_pay_method');
		$this->orderexcelfilter->data_tax		= code_load('orderexcel_tax');
		$this->orderexcelfilter->data_step		= config_load('step');
		$this->orderexcelfilter->data_shipping_group_name	= $this->shippingmodel->get_shipping_group_name_list();	//배송그룹명리스트
		$titles	= '';
		$this->set_cell();

		foreach($this->data_exceldownload['item'] as $item){
			$title		= $item;
			$field		= array();
			foreach($this->all_cells as $code => $data){
				if($item ==  $data[1]){
					$title	= $data[0];
					$field	= $data;
				}
			}
			$titles[]	= $title;
			$fields[]	= $field;
		}

		$arrSystem	= ($this->config_system)?$this->config_system:config_load('system');
		$arr_sub_domain = explode(".",$arrSystem['subDomain']);
		$name_sub_domain = sprintf("%s","{$arr_sub_domain['0']}");

		$fileExe	= 'xlsx';
		$writer		= WriterFactory::create(Type::XLSX); // for XLSX files
		$filename	= $name_sub_domain."_order_list_".date('YmdHis').".".$fileExe;
		
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

		$echoPath	= "order/" . date("Ymd") . "/";
		$downPath	= ROOTPATH . "excel_download/" . $echoPath;
		if(!is_dir($downPath)){
			mkdir($downPath);
			chmod($downPath,0777);
		}
		
		$reg_date		= date('Y-m-d H:i:s');
		$filepath		= $downPath . $filename;
		$searchcount	= count($result);
		$limitcount		= $params['limit_count'];
		$se_params		= serialize($params);
		$excel_type		= $params['excel_type'];

		$writer->openToFile($filepath);
		$writer->addRowWithStyle($titles, $style_title); 

		foreach($result as $order_seq){
			$res = array();
			$params['order_seq'] = $order_seq;
			$res = $this->order2exportmodel->get_excel($params); //데이터 필터

			$this->orderexcelfilter->data_order			= $res;
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
			$writer->addRowWithStyle($outputs, $style_contents);
			unset($res, $outputs);
		}
		$writer->close();
		unset($result);

		$com_date		= date('Y-m-d H:i:s');
		$expired_date	= date('Y-m-d H:i:s', strtotime('+7 days', strtotime($com_date)));
		$setData = array(
			'id'			=> '',
			'provider_seq'	=> $params['excel_request_seq'],
			'manager_id'	=> $this->managerInfo['manager_id'],
			'category'		=> 2, //1:goods, 2:order, 3:member
			'excel_type'	=> $excel_type, 
			'context'		=> $se_params,
			'count'			=> $searchcount,
			'state'			=> 2,
			'file_name'		=> str_replace("order/", "", $echoPath.$filename),
			'limit_count'	=> $limitcount,
			'reg_date'		=> $reg_date,
			'com_date'		=> $com_date,
			'expired_date'	=> $expired_date
		);
		$this->db->insert('fm_queue', $setData);
		$queueID = $this->db->insert_id();

		if($this->debug){
			echo("<table border='1'>");
			$this->excel_print_html(array('outputs'=>$titles));
			$this->excel_print_html(array('outputs'=>$outputs, 'yellows'=>$yellows, 'reds'=>$reds));
		}else{
			echo $echoPath.$filename;
			exit;
		}
	}

	//spout download for item kmj
	public function exceldownload_for_item_spout($result,$provider_data,$params)
	{
		$excel_request_seq = $params['excel_request_seq'];

		$this->load->library('itemexcelfilter');
		$this->load->model('openmarketmodel');
		$this->load->model('order2exportmodel');
		$this->load->model('shippingmodel');
		$this->setting_type	= $this->data_exceldownload['criteria'];
		$linkage_malldata	= $this->openmarketmodel->get_linkage_support_mall('shoplinker');
		if($linkage_malldata) foreach($linkage_malldata as $key=>$malldata) $linkage_mallnames[$malldata['mall_code']]	= $malldata['mall_name'];
		$this->itemexcelfilter->data_linkage	= $linkage_mallnames;
		$this->itemexcelfilter->data_provider	= $provider_data;
		$this->itemexcelfilter->data_paymethod	= code_load('orderexcel_pay_method');
		$this->itemexcelfilter->data_tax		= code_load('orderexcel_tax');
		$this->itemexcelfilter->data_step		= config_load('step');
		$this->itemexcelfilter->data_shipping_group_name	= $this->shippingmodel->get_shipping_group_name_list();	//배송그룹명리스트
		$titles	= '';
		$this->set_cell();

		foreach($this->data_exceldownload['item'] as $item){
			$title		= $item;
			$field		= array();
			foreach($this->all_cells as $code => $data){
				if($item ==  $data[1]){
					$title	= $data[0];
					$field	= $data;
				}
			}
			$titles[]	= $title;
			$fields[]	= $field;
		}

		$arrSystem	= ($this->config_system)?$this->config_system:config_load('system');
		$arr_sub_domain = explode(".",$arrSystem['subDomain']);
		$name_sub_domain = sprintf("%s","{$arr_sub_domain['0']}");

		$fileExe	= 'xlsx';
		$writer		= WriterFactory::create(Type::XLSX); // for XLSX files
		$filename	= $name_sub_domain."_order_list_".date('YmdHis').".".$fileExe;
		
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

		$echoPath	= "order/" . date("Ymd") . "/";
		$downPath	= ROOTPATH . "excel_download/" . $echoPath;
		if(!is_dir($downPath)){
			mkdir($downPath);
			chmod($downPath,0777);
		}

		$reg_date		= date('Y-m-d H:i:s');
		$filepath		= $downPath . $filename;
		$searchcount	= count($result);
		$limitcount		= $params['limit_count'];
		$se_params		= serialize($params);
		$excel_type		= $params['excel_type'];

		$writer->openToFile($filepath);
		$writer->addRowWithStyle($titles, $style_title); 

		$order_count = 0;
		$j = 0;
		$yellows	= array();
		$reds		= array();
		foreach($result as $order_seq){
			$res = array();
			$params['order_seq'] = $order_seq;
			$data_order = $this->order2exportmodel->get_excel($params); //데이터 필터

			$params	= array();
			$params['data_order']	= $data_order['order'];
			$params['data_member']	= $data_order['member'];
			$old_order_seq			= "";

			foreach($data_order['ordershipping'] as $data_shipping){

				$params['data_shipping']	= $data_shipping;

				foreach($fields as $data_field){

					$item_count										= $order_count;
					$params['data_shipping']['old_shipping_seq']	= "";

					foreach($data_shipping['options'] as $data_option){

						unset($params['data_package']);
						$params['data_option']		= $data_option;
						$yellows[$item_count]	= $this->itemexcelfilter->check_stock($params);
						$reds[$item_count]		= $this->itemexcelfilter->check_package($params);
						if( !$data_field[2] ){
							if($data_field[1]){
								if(in_array($data_field[1],$this->_set_currency)){
									$data_order['order'][$data_field[1]] = get_krw_currency($data_order['order'][$data_field[1]]);
								}
								$outputs[$item_count][$data_field[1]]	= $data_order['order'][$data_field[1]];
							}else{
								$outputs[$item_count][$j] = "";
								$j++;
							}
						}else{
							if($data_field[1] == 'shipping_provider'){ //이미 데이터가 있기 때문에 필터 필요 없음 18.09.10 kmj
								$outputs[$item_count][$data_field[1]]	= $data_shipping['shipping_provider'];
							} else {
								$outputs[$item_count][$data_field[1]]	= $this->itemexcelfilter->{$data_field[1]}($params);
							}
						}

						$item_count++;
						foreach($data_option['packages'] as $data_package){
							$params['data_package']		= $data_package;
							$yellows[$item_count]	= $this->itemexcelfilter->check_stock($params);
							$reds[$item_count]		= $this->itemexcelfilter->check_package($params);
							if( !$data_field[2] ){
								if($data_field[1]){
									if(in_array($data_field[1],$this->_set_currency)){
										$data_order['order'][$data_field[1]] = get_krw_currency($data_order['order'][$data_field[1]]);
									}
									$outputs[$item_count][$data_field[1]]	= $data_order['order'][$data_field[1]];
								}else{
									$outputs[$item_count][$j] = "";
									$j++;
								}
							}else{
								$outputs[$item_count][$data_field[1]]	= $this->itemexcelfilter->{$data_field[1]}($params);
							}
							$item_count++;
						}
						foreach($data_option['suboptions'] as $data_suboption){
							unset($params['data_package']);
							$params['data_option']		= $data_suboption;
							$yellows[$item_count]	= $this->itemexcelfilter->check_stock($params);
							$reds[$item_count]		= $this->itemexcelfilter->check_package($params);
							if( !$data_field[2] ){
								if($data_field[1]){
									if(in_array($data_field[1],$this->_set_currency)){
										$data_order['order'][$data_field[1]] = get_krw_currency($data_order['order'][$data_field[1]]);
									}
									$outputs[$item_count][$data_field[1]]	= $data_order['order'][$data_field[1]];
								}else{
									$outputs[$item_count][$j] = "";
									$j++;
								}
							}else{
								$outputs[$item_count][$data_field[1]]	= $this->itemexcelfilter->{$data_field[1]}($params);
							}
							$item_count++;
							foreach($data_suboption['packages'] as $data_package){
								$params['data_package']		= $data_package;
								$yellows[$item_count]	= $this->itemexcelfilter->check_stock($params);
								$reds[$item_count]		= $this->itemexcelfilter->check_package($params);
								if( !$data_field[2] ){
									if($data_field[1]){
										if(in_array($data_field[1],$this->_set_currency)){
											$data_order['order'][$data_field[1]] = get_krw_currency($data_order['order'][$data_field[1]]);
										}
										$outputs[$item_count][$data_field[1]]	= $data_order['order'][$data_field[1]];
									}else{
										$outputs[$item_count][$j] = "";
										$j++;
									}
								}else{
									$outputs[$item_count][$data_field[1]]	= $this->itemexcelfilter->{$data_field[1]}($params);
								}
								$item_count++;
							}
						} // end suboption

						# (묶음배송)표기를 위한 구분값
						$old_shipping_seq		= $params['data_shipping']['shipping_seq'];
						$params['data_shipping']['old_shipping_seq'] = $old_shipping_seq;

					} // end option
					
				} // end fields


				for($i=$order_count; $i<$item_count; $i++){
					if(isset($outputs[$i]['settleprice']) && $outputs[$i]['order_seq'] == $old_order_seq){
						$outputs[$i]['settleprice'] = "(묶음배송)";
					}

					//관리자 메모는 첫줄만 나옴 kmj
					if($outputs[$i]['order_seq'] == $outputs[$i-1]['order_seq']){
						$outputs[$i]['admin_memo'] = '';
					}
					$writer->addRowWithStyle($outputs[$i], $style_contents);
					$old_order_seq = $outputs[$i]['order_seq'];
				}
				$order_count = $item_count;
			} // end shipping
		} // end order
		$writer->close();

		$com_date		= date('Y-m-d H:i:s');
		$expired_date	= date('Y-m-d H:i:s', strtotime('+7 days', strtotime($com_date)));
		$setData = array(
			'id'			=> '',
			'provider_seq'	=> $excel_request_seq,
			'manager_id'	=> $this->managerInfo['manager_id'],
			'category'		=> 2, //1:goods, 2:order, 3:member
			'excel_type'	=> $excel_type, 
			'context'		=> $se_params,
			'count'			=> $searchcount,
			'state'			=> 2,
			'file_name'		=> str_replace("order/", "", $echoPath.$filename),
			'limit_count'	=> $limitcount,
			'reg_date'		=> $reg_date,
			'com_date'		=> $com_date,
			'expired_date'	=> $expired_date
		);
		$this->db->insert('fm_queue', $setData);
		$queueID = $this->db->insert_id();

		if($this->debug){
			echo("<table border='1'>");
			$this->excel_print_html(array('outputs'=>$titles));
			$this->excel_print_html(array('outputs'=>$outputs, 'yellows'=>$yellows, 'reds'=>$reds));
		}else{
			echo $echoPath.$filename;
			exit;
		}
	}

	public function exceldownload_header($file_type='order_')
	{
		header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
		header("Content-Disposition: attachment;filename=".$file_type.date("YmdHi").".xls");
		header('Cache-Control: max-age=0');
		echo "<?xml version=\"1.0\"?><?mso-application progid=\"Excel.Sheet\"?>
<Workbook xmlns=\"urn:schemas-microsoft-com:office:spreadsheet\"
		xmlns:o=\"urn:schemas-microsoft-com:office:office\"
		xmlns:x=\"urn:schemas-microsoft-com:office:excel\"
		xmlns:ss=\"urn:schemas-microsoft-com:office:spreadsheet\"
		xmlns:html=\"http://www.w3.org/TR/REC-html40\">
	<Styles>
		<Style ss:ID=\"title\">
			<Borders>
				<Border ss:Position=\"Left\" ss:LineStyle=\"Continuous\" ss:Weight=\"1\" />
				<Border ss:Position=\"Right\" ss:LineStyle=\"Continuous\" ss:Weight=\"1\" />
				<Border ss:Position=\"Top\" ss:LineStyle=\"Continuous\" ss:Weight=\"1\" />
				<Border ss:Position=\"Bottom\" ss:LineStyle=\"Continuous\" ss:Weight=\"1\" />
			</Borders>
			<Alignment ss:Horizontal=\"Left\" ss:Vertical=\"Top\" />
			<Interior ss:Color=\"#dddddd\" ss:Pattern=\"Solid\"/>
			<Font ss:Size=\"11\" ss:Color=\"#000000\" ss:Bold=\"1\" />
		</Style>
		<Style ss:ID=\"whiteBlack\">
			<Borders>
				<Border ss:Position=\"Left\" ss:LineStyle=\"Continuous\" ss:Weight=\"1\" />
				<Border ss:Position=\"Right\" ss:LineStyle=\"Continuous\" ss:Weight=\"1\" />
				<Border ss:Position=\"Top\" ss:LineStyle=\"Continuous\" ss:Weight=\"1\" />
				<Border ss:Position=\"Bottom\" ss:LineStyle=\"Continuous\" ss:Weight=\"1\" />
			</Borders>
			<Alignment ss:Vertical=\"Top\" ss:WrapText=\"1\" />
			<Interior ss:Color=\"#ffffff\" ss:Pattern=\"Solid\"/>
			<Font ss:Size=\"11\" ss:Color=\"#000000\" ss:Bold=\"0\" />
		</Style>
		<Style ss:ID=\"whiteRed\">
			<Borders>
				<Border ss:Position=\"Left\" ss:LineStyle=\"Continuous\" ss:Weight=\"1\" />
				<Border ss:Position=\"Right\" ss:LineStyle=\"Continuous\" ss:Weight=\"1\" />
				<Border ss:Position=\"Top\" ss:LineStyle=\"Continuous\" ss:Weight=\"1\" />
				<Border ss:Position=\"Bottom\" ss:LineStyle=\"Continuous\" ss:Weight=\"1\" />
			</Borders>
			<Alignment ss:Vertical=\"Top\" ss:WrapText=\"1\" />
			<Interior ss:Color=\"#ffffff\" ss:Pattern=\"Solid\"/>
			<Font ss:Size=\"11\" ss:Color=\"#ff0000\" ss:Bold=\"0\" />
		</Style>
		<Style ss:ID=\"yellowBlack\">
			<Borders>
				<Border ss:Position=\"Left\" ss:LineStyle=\"Continuous\" ss:Weight=\"1\" />
				<Border ss:Position=\"Right\" ss:LineStyle=\"Continuous\" ss:Weight=\"1\" />
				<Border ss:Position=\"Top\" ss:LineStyle=\"Continuous\" ss:Weight=\"1\" />
				<Border ss:Position=\"Bottom\" ss:LineStyle=\"Continuous\" ss:Weight=\"1\" />
			</Borders>
			<Alignment ss:Vertical=\"Top\" ss:WrapText=\"1\" />
			<Interior ss:Color=\"#ffff00\" ss:Pattern=\"Solid\"/>
			<Font ss:Size=\"11\" ss:Color=\"#000000\" ss:Bold=\"0\" />
		</Style>
		<Style ss:ID=\"yellowRed\">
			<Borders>
				<Border ss:Position=\"Left\" ss:LineStyle=\"Continuous\" ss:Weight=\"1\" />
				<Border ss:Position=\"Right\" ss:LineStyle=\"Continuous\" ss:Weight=\"1\" />
				<Border ss:Position=\"Top\" ss:LineStyle=\"Continuous\" ss:Weight=\"1\" />
				<Border ss:Position=\"Bottom\" ss:LineStyle=\"Continuous\" ss:Weight=\"1\" />
			</Borders>
			<Alignment ss:Vertical=\"Top\" ss:WrapText=\"1\" />
			<Interior ss:Color=\"#ffff00\" ss:Pattern=\"Solid\"/>
			<Font ss:Size=\"11\" ss:Color=\"#ff0000\" ss:Bold=\"0\" />
		</Style>
	</Styles>
	<Worksheet ss:Name=\"Sheet1\">
		<Table>";
	}
	public function exceldownload_footer()
	{
		echo "
		</Table>
	</Worksheet>
</Workbook>";
	}

	public function excel_col_xml($fields){
		foreach($fields as $data_field){
			$width = 80;
			if($data_field[3]) $width = $data_field[3];
			echo '<Column ss:AutoFitWidth="0" ss:Width="'.$width.'" />'.chr(10);
		}
	}

	public function excel_print_xml($params){
		$idx				= $params['idx'];
		$outputs		= $params['outputs'];
		$yellows			= $params['yellows'];
		$reds				= $params['reds'];

		$is_title = true;
		foreach($outputs as $tmp) if(is_array($tmp)) $is_title = false;
		if( $is_title ){
			echo '<Row ss:Index="'.$idx.'" ss:customHeight ="0">'.chr(10);
			foreach($outputs as $output){
				$styleId = 'title';
				echo '<Cell ss:StyleID="'.$styleId.'"><Data ss:Type="String">'.$output.'</Data></Cell>'.chr(10);
			}
			echo '</Row>'.chr(10);
		}else{
			$rIdx = 0;
			foreach($outputs as $tmp){
				$idx++;
				echo '<Row ss:Index="'.$idx.'" ss:customHeight ="0">'.chr(10);
				foreach($tmp as $output){
					if( $yellows[$rIdx] ) $styleId = 'yellow';
					else $styleId = 'white';
					if( $reds[$rIdx] ) $styleId .= 'Red';
					else $styleId .= 'Black';
					echo '<Cell ss:StyleID="'.$styleId.'"><Data ss:Type="String">'.$output.'</Data></Cell>'.chr(10);
				}
				echo '</Row>'.chr(10);
				$rIdx++;
			}
		}
	}

	public function excel_print_html($params){
		$outputs	= $params['outputs'];
		$yellows		= $params['yellows'];
		$reds			= $params['reds'];

		$is_title = true;
		foreach($outputs as $tmp) if(is_array($tmp)) $is_title = false;
		if( $is_title ){
			echo '<tr>'.chr(10);
			foreach($outputs as $output){
				echo '<th nowrap><div>'.$output.'</div></th>'.chr(10);
			}
			echo '</tr>'.chr(10);
		}else{
			$rIdx = 0;
			foreach($outputs as $tmp){
				$styles = array();
				if( $yellows[$rIdx] ) $styles[]	= 'background-color:#ffff00;';
				if( $reds[$rIdx] ) $styles[]		= 'color:#ff0000;';
				$str_style = implode('', $styles);
				echo '<tr>'.chr(10);
				foreach($tmp as $output){
					echo '<td nowrap valign="top" style="'.$str_style.'"><div>'.str_replace('&#10;','&nbsp;</div><div>',$output).'</div></td>'.chr(10);
				}
				echo '</tr>'.chr(10);
				$rIdx++;
			}
		}
	}

	public function exceldownload_for_order($result,$provider_data)
	{
		$this->load->library('orderexcelfilter');
		$this->load->model('openmarketmodel');
		$this->load->model('shippingmodel');
		$this->setting_type	= $this->data_exceldownload['criteria'];
		$linkage_malldata	= $this->openmarketmodel->get_linkage_support_mall('shoplinker');
		if($linkage_malldata) foreach($linkage_malldata as $key=>$malldata) $linkage_mallnames[$malldata['mall_code']] = $malldata['mall_name'];
		$this->orderexcelfilter->data_linkage				= $linkage_mallnames;
		$this->orderexcelfilter->data_provider				= $provider_data;
		$this->orderexcelfilter->data_paymethod				= code_load('orderexcel_pay_method');
		$this->orderexcelfilter->data_tax					= code_load('orderexcel_tax');
		$this->orderexcelfilter->data_step					= config_load('step');
		$this->orderexcelfilter->data_shipping_group_name	= $this->shippingmodel->get_shipping_group_name_list();	//배송그룹명리스트
		$titles	= '';
		$this->set_cell();

		foreach($this->data_exceldownload['item'] as $item){
			$title		= $item;
			$field		= array();
			foreach($this->all_cells as $code => $data){
				if($item ==  $data[1]){
					$title	= $data[0];
					$field	= $data;
				}
			}
			$titles[]	= $title;
			$fields[]	= $field;
		}
		$i=0;
		$j=0;
		foreach($result as $data_order){
			$this->orderexcelfilter->data_order = $data_order;
			$yellows[]	= $this->orderexcelfilter->check_stock_order();
			$reds[]		= $this->orderexcelfilter->check_package_option();
			foreach($fields as $data_field){
				if( !$data_field[2] ){
					if($data_field[1]){
						if(in_array($data_field[1],$this->_set_currency)){
							$data_order['order'][$data_field[1]] = get_krw_currency($data_order['order'][$data_field[1]]);
						}
						$outputs[$i][$data_field[1]]	= $data_order['order'][$data_field[1]];
					}else{
						$outputs[$i][$j] = "";
						$j++;
					}
				}else{
					$data_filter							= $this->orderexcelfilter->{$data_field[1]}();
					
					if( is_array($data_filter) ){
						$outputs[$i][$data_field[1]] = strip_tags(implode("&#10;", $data_filter));
					}else{
						$outputs[$i][$data_field[1]] = $data_filter;
					}
				}
			}
			$i++;
		}
		if($this->debug){
			echo("<table border='1'>");
			$this->excel_print_html(array('outputs'=>$titles));
			$this->excel_print_html(array('outputs'=>$outputs, 'yellows'=>$yellows, 'reds'=>$reds));
		}else{
			$idx++;
			$this->exceldownload_header('order_');
			$this->excel_col_xml($fields);
			$this->excel_print_xml(array('idx'=>$idx, 'outputs'=>$titles));
			$this->excel_print_xml(array('idx'=>$idx, 'outputs'=>$outputs, 'yellows'=>$yellows, 'reds'=>$reds));
		}

		$this->exceldownload_footer();
	}

	public function exceldownload_for_item($result,$provider_data)
	{
		$this->load->library('itemexcelfilter');
		$this->load->model('openmarketmodel');
		$this->load->model('shippingmodel');
		$this->setting_type	= $this->data_exceldownload['criteria'];
		$linkage_malldata	= $this->openmarketmodel->get_linkage_support_mall('shoplinker');
		if($linkage_malldata) foreach($linkage_malldata as $key=>$malldata) $linkage_mallnames[$malldata['mall_code']] = $malldata['mall_name'];
		$this->itemexcelfilter->data_linkage			= $linkage_mallnames;
		$this->itemexcelfilter->data_provider			= $provider_data;
		$this->itemexcelfilter->data_paymethod			= code_load('orderexcel_pay_method');
		$this->itemexcelfilter->data_tax				= code_load('orderexcel_tax');
		$this->itemexcelfilter->data_step				= config_load('step');
		$this->itemexcelfilter->data_shipping_group_name= $this->shippingmodel->get_shipping_group_name_list();	//배송그룹명리스트
		$titles	= '';
		$this->set_cell();

		foreach($this->data_exceldownload['item'] as $item){
			$title		= $item;
			$field		= array();
			foreach($this->all_cells as $code => $data){
				if($item ==  $data[1]){
					$title	= $data[0];
					$field	= $data;
				}
			}
			$titles[]	= $title;
			$fields[]	= $field;
		}

		$order_count = 0;
		$j = 0;
		$yellows	= array();
		$reds		= array();
		foreach($result as $data_order){
			$params	= array();
			$params['data_order']	= $data_order['order'];
			$params['data_member']	= $data_order['member'];
			foreach($data_order['ordershipping'] as $data_shipping){

				$params['data_shipping']	= $data_shipping;
				$old_order_seq	= "";

				foreach($fields as $data_field){

					$item_count										= $order_count;
					$params['data_shipping']['old_shipping_seq']	= "";

					foreach($data_shipping['options'] as $data_option){
						unset($params['data_package']);
						$params['data_option']		= $data_option;
						$yellows[$item_count]	= $this->itemexcelfilter->check_stock($params);
						$reds[$item_count]		= $this->itemexcelfilter->check_package($params);
						if( !$data_field[2] ){
							if($data_field[1]){
								if(in_array($data_field[1],$this->_set_currency)){
									$data_order['order'][$data_field[1]] = get_krw_currency($data_order['order'][$data_field[1]]);
								}
								$outputs[$item_count][$data_field[1]]	= $data_order['order'][$data_field[1]];
							}else{
								$outputs[$item_count][$j] = "";
								$j++;
							}
						}else{
							$outputs[$item_count][$data_field[1]]	= $this->itemexcelfilter->{$data_field[1]}($params);
						}
						
						$item_count++;
						foreach($data_option['packages'] as $data_package){
							$params['data_package']		= $data_package;
							$yellows[$item_count]	= $this->itemexcelfilter->check_stock($params);
							$reds[$item_count]		= $this->itemexcelfilter->check_package($params);
							if( !$data_field[2] ){
								if($data_field[1]){
									if(in_array($data_field[1],$this->_set_currency)){
										$data_order['order'][$data_field[1]] = get_krw_currency($data_order['order'][$data_field[1]]);
									}
									$outputs[$item_count][$data_field[1]]	= $data_order['order'][$data_field[1]];
								}else{
									$outputs[$item_count][$j] = "";
									$j++;
								}
							}else{
								$outputs[$item_count][$data_field[1]]	= $this->itemexcelfilter->{$data_field[1]}($params);
							}
							$item_count++;
						}
						foreach($data_option['suboptions'] as $data_suboption){
							unset($params['data_package']);
							$params['data_option']		= $data_suboption;
							$yellows[$item_count]	= $this->itemexcelfilter->check_stock($params);
							$reds[$item_count]		= $this->itemexcelfilter->check_package($params);
							if( !$data_field[2] ){
								if($data_field[1]){
									if(in_array($data_field[1],$this->_set_currency)){
										$data_order['order'][$data_field[1]] = get_krw_currency($data_order['order'][$data_field[1]]);
									}
									$outputs[$item_count][$data_field[1]]	= $data_order['order'][$data_field[1]];
								}else{
									$outputs[$item_count][$j] = "";
									$j++;
								}
							}else{
								$outputs[$item_count][$data_field[1]]	= $this->itemexcelfilter->{$data_field[1]}($params);
							}
							$item_count++;
							foreach($data_suboption['packages'] as $data_package){
								$params['data_package']		= $data_package;
								$yellows[$item_count]	= $this->itemexcelfilter->check_stock($params);
								$reds[$item_count]		= $this->itemexcelfilter->check_package($params);
								if( !$data_field[2] ){
									if($data_field[1]){
										if(in_array($data_field[1],$this->_set_currency)){
											$data_order['order'][$data_field[1]] = get_krw_currency($data_order['order'][$data_field[1]]);
										}
										$outputs[$item_count][$data_field[1]]	= $data_order['order'][$data_field[1]];
									}else{
										$outputs[$item_count][$j] = "";
										$j++;
									}
								}else{
									$outputs[$item_count][$data_field[1]]	= $this->itemexcelfilter->{$data_field[1]}($params);
								}
								$item_count++;
							}
						} // end suboption

						# (묶음배송)표기를 위한 구분값
						$old_shipping_seq		= $params['data_shipping']['shipping_seq'];
						$params['data_shipping']['old_shipping_seq'] = $old_shipping_seq;

					} // end option

					$old_order_seq = $params['order_seq'];
				} // end fields
				$order_count = $item_count;
			} // end shipping
		} // end order

		if($this->debug){
			echo("<table border='1'>");
			$this->excel_print_html(array('outputs'=>$titles));
			$this->excel_print_html(array('outputs'=>$outputs, 'yellows'=>$yellows, 'reds'=>$reds));
		}else{
			$idx++;
			$this->exceldownload_header('item_');
			$this->excel_col_xml($fields);
			$this->excel_print_xml(array('idx'=>$idx, 'outputs'=>$titles));
			$this->excel_print_xml(array('idx'=>$idx, 'outputs'=>$outputs, 'yellows'=>$yellows, 'reds'=>$reds));
		}

		$this->exceldownload_footer();
	}

	public function get_info_by_export_item_seq($export_item_seq){
		$arr = explode('-',$export_item_seq);
		return $arr;
	}

	public function excel_upload_goods($result,$check_mode='')
	{
		$this->load->model('order2exportmodel');
		$export = $this->excel_filter_goods($result);
		return $export;
	}

	function excel_filter_goods($result){
		$this->load->model('ordershippingmodel');
		$this->setting_type			= 'ITEM';
		$excel['stockable'] 			= $_POST['stockable'];
		$excel['export_step'] 		= $_POST['export_step'];
		$excel['ticket_stockable'] 	= $_POST['ticket_stockable'];
		$excel['ticket_step'] 			= $_POST['ticket_step'];
		$excel['export_date'] 		= $_POST['export_date'];
		$this->set_cell();

		foreach($this->all_cells as $data_cell) $this->itemList[ $data_cell[1] ] = $data_cell[0];

		foreach($result[0] as $key_export => $data_export){
			$title = str_replace('*','',$data_export);
			$title = trim($title);
			$tmp = array_keys($this->itemList,$title);
			$key_field = $tmp[0];
			$arr_key_all[] = $key_field;
		}

		// 출고시 필요한 데이터 뽑기
		foreach($result as $key => $data){
			if($key < 1) continue;
			$i = 0;
			foreach($data as $key_export => $data_export){
				if( $arr_key_all[$i]  == "export_item_seq")	$arr_export_item_seq[$key] = $data_export;
				if( $arr_key_all[$i]  == "request_ea") 			$delivery_request_ea[$key] = $data_export;
				if( $arr_key_all[$i]  == "delivery_company")	$delivery_company[$key] = $data_export;
				if( $arr_key_all[$i]  == "delivery_number")	$delivery_number[$key] = $data_export;
				$i++;
			}
		}

		foreach( $arr_export_item_seq as $key => $export_item_seq){
			$export_excel[$export_item_seq]['delivery_company'] 	= $delivery_company[$key];
			$export_excel[$export_item_seq]['delivery_number'] 	= $delivery_number[$key];
			$export_excel[$export_item_seq]['request_ea'] 			= $delivery_request_ea[$key];
		}

		foreach($export_excel as $export_item_seq => $data_export){
			list($opttype,$shipping_seq,$opt_seq) = $this->order2exportmodel->get_info_by_export_item_seq( $export_item_seq );
			if( !$param_shipping[$shipping_seq] ) $all_shipping = $this->order2exportmodel->get_data_for_batch_export_item(array('shipping_seq'=>$shipping_seq));
			else $all_shipping = $param_shipping[$shipping_seq];
			$param_shipping[$shipping_seq] = $all_shipping;
			foreach($all_shipping as $k=>$data_order_shipping){
				if($data_order_shipping['shipping_seq'] == $shipping_seq)
				{
					if($opttype == 'SUB'){
						$excel['request_ea'][$shipping_seq]['suboption'][$opt_seq]					= $data_export['request_ea'];
						$excel['shipping_goods_kind'][$shipping_seq]['suboption'][$opt_seq]	= $opttype;
						if	($data_order_shipping['options']) foreach($data_order_shipping['options'] as $item_option_seq => $optdata) 	if	($optdata['suboptions'][$opt_seq]){
							$suboptiondata	= $optdata['suboptions'][$opt_seq];
							break;
						}
						if	($data_order_shipping['items'][$suboptiondata['item_seq']]['provider_seq'] == '1'){
							$excel['optioninfo'][$shipping_seq]['suboption'][$opt_seq]		= $data_order_shipping['items'][$suboptiondata['item_seq']]['goods_seq'].'suboption'.$suboptiondata['suboption_seq'];
							$excel['whSupplyPrice'][$shipping_seq]['suboption'][$opt_seq]	= '0';
							$excel['goodscode'][$shipping_seq]['suboption'][$opt_seq]		= $data_order_shipping['items'][$suboptiondata['item_seq']]['goods_code'];
							$excel['stock'][$shipping_seq]['suboption'][$opt_seq]				= '0';
						}
					}else{
						$excel['request_ea'][$shipping_seq]['option'][$opt_seq] = $data_export['request_ea'];
						$excel['shipping_goods_kind'][$shipping_seq]['option'][$opt_seq] = $opttype;
						if	($data_order_shipping['items'][$data_order_shipping['options'][$opt_seq]['item_seq']]['provider_seq'] == '1'){
							$excel['optioninfo'][$shipping_seq]['option'][$opt_seq]		= $data_order_shipping['items'][$data_order_shipping['options'][$opt_seq]['item_seq']]['goods_seq'] . 'option' . $data_order_shipping['options'][$opt_seq]['option_seq'];
							$excel['whSupplyPrice'][$shipping_seq]['option'][$opt_seq]	= '0';
							$excel['goodscode'][$shipping_seq]['option'][$opt_seq]		= $data_order_shipping['items'][$data_order_shipping['options'][$opt_seq]['item_seq']]['goods_code'];
							$excel['stock'][$shipping_seq]['option'][$opt_seq]			= '0';
						}
					}
					$delivery_code = '';
					foreach($data_order_shipping['couriers'] as $code => $data_courier){
						$delivery_code_arr[] = $code;
						if( $data_courier['company'] == $data_export['delivery_company'] ) $delivery_code = $code;
					}
					if($delivery_code) 	$excel['delivery_company'][$shipping_seq] = $delivery_code;
					else $excel['delivery_company'][$shipping_seq] = $delivery_code_arr[0];

					$excel['order_seq'][$shipping_seq] = $data_order_shipping['order_seq'];
					if( $data_export['delivery_number'] ) $excel['delivery_number'][$shipping_seq] = $data_export['delivery_number'];
				}
			} // shipping
		} // excel

		return array($excel,$param_shipping);
	}

	function export_log($stockable,$step,$export_type,$goods_kind,$matchyn,$exportyn,$error)
	{
		$this->load->model('exportlogmodel');
		$this->exportlogmodel->export_log($stockable,$step,$export_type,$goods_kind,$matchyn,$exportyn,$error);
	}

	function excel_filter_order($result){
		$this->load->model('ordershippingmodel');
		$this->setting_type			= 'ORDER';
		$excel['stockable'] 		= $_POST['stockable'];
		$excel['export_step'] 		= $_POST['export_step'];
		$excel['ticket_stockable'] 	= $_POST['ticket_stockable'];
		$excel['ticket_step'] 		= $_POST['ticket_step'];
		$excel['export_date'] 		= $_POST['export_date'];
		$this->set_cell();
		foreach($this->all_cells as $data_cell) $this->itemList[ $data_cell[1] ] = $data_cell[0];

		foreach($result[0] as $key_export => $data_export){
			$title = str_replace('*','',$data_export);
			$title = trim($title);
			$tmp = array_keys($this->itemList,$title);
			$key_field = $tmp[0];
			$arr_key_all[] = $key_field;
		}

		foreach($arr_key_all as $key=>$value){
			if($value == 'shipping_seq')		$num_shipping_seq 			= $key;
			if($value == 'delivery_company')	$num_delivery_company		= $key;
			if($value == 'delivery_number')		$num_delivery_number		= $key;
		}

		// 출고시 필요한 데이터 뽑기
		foreach($result as $key => $data){
			if($key < 1) continue;
			$i = 0;

			$tmp_shipping_seq[$key]		= explode(chr(10),$data[$num_shipping_seq]);
			$tmp_delivery_company[$key]	= explode(chr(10),$data[$num_delivery_company]);
			$tmp_delivery_number[$key]	= explode(chr(10),$data[$num_delivery_number]);

			if(count($tmp_shipping_seq[$key]) > count($tmp_delivery_company[$key])){
				for($i=count($tmp_delivery_company[$key]); $i<count($tmp_shipping_seq[$key]); $i++) $tmp_delivery_company[$key][] = "";
			}

			/*
				동일 출고그룹의 출고상품이 2개 이상일 때 송장번호가 1줄만 입력 되어 있으면 출고처리는 되나 송장번호 입력은 안됨.
				동일 출고그룹 row수와 송장번호 row수 체크하여 row수 맞춰주기
			*/
			$_old_tmp_shipping_seq = "";
			foreach($tmp_shipping_seq[$key] as $key2=>$_tmp_shipping_seq){

				if(strstr($tmp_delivery_number[$key][$key2],"입력")) $tmp_delivery_number[$key][$key2] = "";

				if($_old_tmp_shipping_seq == $_tmp_shipping_seq 
					&& (strstr($tmp_delivery_number[$key][$key2],"상동") || 
					strstr($tmp_delivery_number[$key][$key2],"묶음배송") || 
					$tmp_delivery_number[$key][$key2] == "")
				) {
					$tmp_delivery_number[$key][$key2]	= $_delivery_num;
					$tmp_delivery_company[$key][$key2]	= $_delivery_company;
				}else{
					$old_delivery_number	= $tmp_delivery_number[$key][$key2];
					$old_delivery_company	= $tmp_delivery_company[$key][$key2];
				}

				$_old_tmp_shipping_seq = $_tmp_shipping_seq;
				$_delivery_num		= $tmp_delivery_number[$key][$key2];
				$_delivery_company	= $tmp_delivery_company[$key][$key2];
			}
		}

		foreach( $tmp_shipping_seq as $key => $shipping_arr){
			if	($shipping_arr) foreach($shipping_arr as $key2 => $shipping_seq){
				$shipping_seq	= trim($shipping_seq);
				$export_excel[$shipping_seq]['delivery_company']	= $tmp_delivery_company[$key][$key2];
				$export_excel[$shipping_seq]['delivery_number']		= $tmp_delivery_number[$key][$key2];
			}
		}

		foreach($export_excel as $shipping_seq => $data_export){
			if( !$param_shipping[$shipping_seq] ) $all_shipping = $this->order2exportmodel->get_data_for_batch_export_item(array('shipping_seq'=>$shipping_seq));
			else $all_shipping = $param_shipping[$shipping_seq];
			$param_shipping[$shipping_seq] = $all_shipping;
			foreach($all_shipping as $k=>$data_order_shipping){
				if($data_order_shipping['shipping_seq'] == $shipping_seq)
				{
					foreach($data_order_shipping['options'] as $data_option){
						$item_option_seq	= $data_option['item_option_seq'];
						$item_seq			= $data_option['item_seq'];
						$goods_seq 		= $data_order_shipping['items'][$data_option['item_seq']]['goods_seq'];
						$goods_code		= $data_order_shipping['items'][$data_order_shipping['options'][$item_option_seq]['item_seq']]['goods_code'];
						$option_seq		= $data_order_shipping['options'][$item_option_seq]['option_seq'];
						//보낼수량
						$excel['request_ea'][$shipping_seq]['option'][$item_option_seq]					= $data_option['ea'] - $data_option['step85'] - $data_option['step45']- $data_option['step55']- $data_option['step65']- $data_option['step75'];
						$excel['shipping_goods_kind'][$shipping_seq]['option'][$item_option_seq]		= "OPT";
						if($data['items'][$data_option['item_seq']]['goods_data']['goods_kind'] == 'coupon'){
							$excel['shipping_goods_kind'][$shipping_seq]['option'][$item_option_seq]	= "COU";
						}
						$excel['optioninfo'][$shipping_seq]['option'][$item_option_seq]		= $goods_seq . 'option' . $option_seq;
						$excel['goodscode'][$shipping_seq]['option'][$item_option_seq]		= $goods_code;
						$excel['whSupplyPrice'][$shipping_seq]['option'][$item_option_seq]	= '0';
						$excel['stock'][$shipping_seq]['option'][$item_option_seq]				= '0';

						foreach($data_option['suboptions'] as $data_suboption){
							$item_suboption_seq	= $data_suboption['item_suboption_seq'];
							$suboption_seq			= $data_suboption['suboption_seq'];
							$excel['request_ea'][$shipping_seq]['suboption'][$item_suboption_seq]					= $data_suboption['ea'] - $data_suboption['step85'] - $data_suboption['step45']- $data_suboption['step55']- $data_suboption['step65']- $data_suboption['step75'];
							$excel['shipping_goods_kind'][$shipping_seq]['suboption'][$item_suboption_seq]	= "SUB";
							$excel['optioninfo'][$shipping_seq]['suboption'][$item_suboption_seq]					= $goods_seq.'suboption'.$data_suboption['suboption_seq'];
							$excel['whSupplyPrice'][$shipping_seq]['suboption'][$item_suboption_seq]			= '0';
							$excel['goodscode'][$shipping_seq]['suboption'][$item_suboption_seq]					= $goods_code;
							$excel['stock'][$shipping_seq]['suboption'][$item_suboption_seq]							= '0';
						}
					}
					$delivery_code = '';
					foreach($data_order_shipping['couriers'] as $code => $data_courier){
						$delivery_code_arr[] = $code;
						if( $data_courier['company'] == $data_export['delivery_company'] ) $delivery_code = $code;
					}
					if($delivery_code) 	$excel['delivery_company'][$shipping_seq] = $delivery_code;
					else	$excel['delivery_company'][$shipping_seq] = $delivery_code_arr[0];
				}
			}
			$excel['order_seq'][$shipping_seq] = $data_order_shipping['order_seq'];
			if( $data_export['delivery_number'] ) $excel['delivery_number'][$shipping_seq] = $data_export['delivery_number'];
		}

		return array($excel,$param_shipping);
	}

	function excel_upload_order($result,$check_mode='')
	{
		$this->load->model('order2exportmodel');
		$export = $this->excel_filter_order($result);
		return $export;
	}

	function excel_upload($excel,$check_mode=''){
		$this->load->model('authmodel');
		$auth = $this->authmodel->manager_limit_act('order_goods_export');
		if(!$auth){
			pageBack("관리자 권한이 없습니다.");
			exit;
		}

		// 상품별/주문별 출고 여부 판단
		$mode = "order";
		foreach($excel[0] as $key_excel => $data_excel){
			$data_excel =  trim($data_excel);
			if( $data_excel == '*출고상품번호' || $data_excel == '출고상품번호' ){
				$mode = "goods";
			}
		}
		return $this-> {'excel_upload_'.$mode}($excel,$check_mode);
	}

	// 출고 결과 엑셀로 출력
	function create_excel_temp($excel_temp_seq)
	{
		$this->load->model("exceltempmodel");
		$data = $this->exceltempmodel->get_excel_temp($excel_temp_seq);
		$excel_data = unserialize($data['excel_str']);

		$this->exceldownload_header('excel_export_result_');
		foreach($excel_data as $row=>$row_data){
			echo("<tr>");
			foreach($row_data as $field=>$field_data){
				echo("<td class='number'>".$field_data."</td>");
			}
			echo("</tr>");
		}
		$this->exceldownload_footer();
	}
}