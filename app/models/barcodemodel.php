<?php
class barcodemodel extends CI_Model {

	var $print_info = array(
		'form39'	=> array( 'id'=>'form39', 'cellcount'=>'3', 'rowcount'=>'9', 'form_name'=>'[폼텍3104] A4:3X9', 'margin_top'=>'10', 'margin_left'=>'7', 'margin_bottom'=>'14', 'margin_right'=>'7' ),
		'form410'	=> array( 'id'=>'form410', 'cellcount'=>'4', 'rowcount'=>'10', 'form_name'=>'[폼텍3102] A4:4X10', 'margin_top'=>'14', 'margin_left'=>'5', 'margin_bottom'=>'10', 'margin_right'=>'5' ),
		'form34'	=> array( 'id'=>'form34', 'cellcount'=>'3', 'rowcount'=>'4', 'form_name'=>'[폼텍3112] A4:3X4', 'margin_top'=>'9', 'margin_left'=>'8', 'margin_bottom'=>'8', 'margin_right'=>'8' ),
		'formroll'	=> array( 'id'=>'formroll', 'cellcount'=>'1', 'rowcount'=>'0', 'form_name'=>'롤지', 'margin_top'=>'14', 'margin_left'=>'5', 'margin_bottom'=>'12', 'margin_right'=>'5' )
	);
	
	var $barcode_info = array(
		'code39'	=> array( 'id'=>'code39',  'code_name'=>'Code 39', 'use_border'=>'Y', 'use_text'=>'Y', 'use_goods_name'=>'Y',	'use_option_name'=>'Y', 'use_goods_seq'=>'Y' ),
		'code128_a' => array( 'id'=>'code128_a', 'code_name'=>'Code 128-A', 'use_border'=>'Y', 'use_text'=>'Y', 'use_goods_name'=>'Y',	'use_option_name'=>'Y', 'use_goods_seq'=>'Y' ),
		'code128_b' => array( 'id'=>'code128_b',  'code_name'=>'Code 128-B', 'use_border'=>'Y', 'use_text'=>'Y', 'use_goods_name'=>'Y',	'use_option_name'=>'Y', 'use_goods_seq'=>'Y' ),
		'code128_c' => array( 'id'=>'code128_c', 'code_name'=>'Code 128-C', 'use_border'=>'Y', 'use_text'=>'Y', 'use_goods_name'=>'Y',	'use_option_name'=>'Y', 'use_goods_seq'=>'Y' ),
		'isbn'		=> array( 'id'=>'isbn', 'code_name'=>'ISBN', 'use_border'=>'Y', 'use_text'=>'Y', 'use_goods_name'=>'Y',	'use_option_name'=>'Y', 'use_goods_seq'=>'Y' )
	);

	public function __construct(){
		$this->load->library('validation');
		$barcode_config	= config_load('barcode');

		if(!$barcode_config){
			foreach($this->print_info as $key=>$val){
				config_save('barcode', array( $key	=> $val));
			}		
			foreach($this->barcode_info as $key=>$val){
				config_save('barcode', array( $key	=> $val));
			}

			config_save('barcode', array( 'use_form'		=> 'form39' ));
			config_save('barcode', array( 'use_code'		=> 'code39' ));
			config_save('barcode', array( 'use_code_order'	=> 'code39' ));
		}
	}

	//상품데이터리스트
	public function get_goods_list($params){

		//바코드 출력 변수
		$is_print			= $params['is_print'];
		$print_page_cnt		= $params['print_page_cnt'] ? $params['print_page_cnt'] : 1; 
		$print_start_num	= $params['print_start_num'] ? $params['print_start_num'] : 1;
		//바코드 출력수
		if ( $params['mode'] == "select" ) { 
			if ( !is_array( $params['goods_stock'] ) ) $params['goods_stock'] = explode(',',$params['goods_stock']);
			$rno = array_keys($params['goods_seq']);
			// goods_seq 가 없는 경우에는 goods_seq_list 를 이용해서 rno 구함 2020-03-18
			if(empty($params['goods_seq'])) {
				$rno = array_keys(explode(',',$params['goods_seq_list']));
			}
			foreach( $rno as $keys ) {
				$print_stock[] = $params['goods_stock'][$keys];
			}
		} else {
			$print_stock		= is_array($params['goods_stock']) 
								? $params['goods_stock'] 
								: explode(',',$params['goods_stock']);
		}
		
		//엑셀 다운로드 관련 변수
		$list_type		 = $params['mode'];									//엑셀 다운로드일 경우 (select : 선택, search : 검색)
		$goods_seq_list  = is_array($params['goods_seq_list']) 
							? $params['goods_seq_list'] 
							: explode(',',$params['goods_seq_list']);		//선택 한 상품의 상품번호 배열 (선택다운로드 일 경우만)
		$option_seq_list = is_array($params['option_seq_list'])
							? $params['option_seq_list'] 
							: explode(',',$params['option_seq_list']);		//선택 한 상품의 옵션번호 배열 (선택다운로드 일 경우만)
		
		//검색 폼 변수
		$params['page']		= $params['page'] > 1 ? $params['page'] : 0;			//페이지 번호
		$params['perpage']	= $params['perpage'] ? $params['perpage'] : 10;	//노출 개수
		$gtype			 = $params['gtype'];								//상품 타입 (실물배송, 티켓배송)
		$btype			 = $params['btype'];								//바코드 타입 (있음, 없음)
		$bsubtype1		 = $params['bsubtype1'];							//바코드 서브타입 (기본)
		$bsubtype2		 = $params['bsubtype2'];							//바코드 서브타입 (옵션)
		$search_type	 = $params['search_type'];							//검색타입
		$keyword		 = $params['keyword'];								//검색어		
		$sort			 = $params['sort'] ? $params['sort'] : 'desc_goods_seq';												//오름,내림 차순

		$wheres = ' WHERE 1=1 ';

		//선택한 seq 값으로 검색
		if($list_type == 'select' && $goods_seq_list && $option_seq_list){
			$wheres .= 'AND ( 1 != 1 ';
			for($i = 0; $i < count($goods_seq_list); $i++){
				if ( $goods_seq_list[$i]  && $option_seq_list[$i] ) {
					$wheres .= ' OR (fg.goods_seq = '.$goods_seq_list[$i];
					$wheres .= ' AND fgo.option_seq = '.$option_seq_list[$i].') ';
				}
			}
			$wheres .= ' ) ';
		}
		
		//상품 구분
		if($gtype){
			$wheres	.= " AND fg.goods_kind = '".$gtype."'";
		}

		//바코드 구분
		if($btype == 'Y'){
			if($bsubtype1 == 'type1'){
				$wheres	.= " AND fg.goods_code != '' ";
			} else if($bsubtype1 == 'type2') {
				$wheres	.= " AND fgo.optioncode1 != '' ";
			} else {
				$wheres	.= " AND fg.goods_code != '' AND fgo.optioncode1 != '' ";
			}
		}else if($btype == 'N'){
			if($bsubtype2 == 'type1'){
				$wheres	.= " AND fg.goods_code = '' ";
			} else if($bsubtype2 == 'type2') {
				$wheres	.= " AND fgo.optioncode1 = '' ";
			} else {
				$wheres	.= " AND fg.goods_code = '' AND fgo.optioncode1 = '' ";
			}
		}

		//출력 시 코드 정보 없는 상품 필터링
		if($is_print){
			$wheres	.= " AND (fg.goods_code != '' OR fgo.optioncode1 != '') ";
		}

		//검색어 구분
		if($keyword){
		    if($search_type == 'all' || $search_type == ""){
				$wheres .= " AND ( fg.goods_name like '%".$keyword."%' 
								OR fg.goods_seq = '".$keyword."' 
								OR CONCAT(IFNULL(fg.goods_code, ''), IFNULL(fgo.optioncode1, ''), IFNULL(fgo.optioncode2, ''), IFNULL(fgo.optioncode3, ''), IFNULL(fgo.optioncode4, ''), IFNULL(fgo.optioncode5, '')) like '%".$keyword."%' ) ";
			}else if($search_type == 'goods_name'){
				$wheres .= " AND fg.goods_name like '%".$keyword."%' ";
			}else if($search_type == 'goods_seq'){
				$wheres .= " AND fg.goods_seq = '".$keyword."' ";
			}else if($search_type == 'goods_code'){
				$wheres .= " AND CONCAT(IFNULL(fg.goods_code, ''), IFNULL(fgo.optioncode1, ''), IFNULL(fgo.optioncode2, ''), IFNULL(fgo.optioncode3, ''), IFNULL(fgo.optioncode4, ''), IFNULL(fgo.optioncode5, '')) like '%".$keyword."%' ";
			}
		}

		//정렬 변수 파싱
		$orderbyTmp = explode("_",$sort);
		if(in_array($orderbyTmp[0],array("asc","desc"))){
			foreach($orderbyTmp as $orderK=>$orderV) if($orderK > 0) $orderbyTmp2[] = $orderV;
			$orderby	= implode("_",$orderbyTmp2);
			$sort	= $orderbyTmp[0];
			if($orderby == 'barcode'){
				$orderStr = "fg.goods_code {$sort}, 
								fgo.option1 {$sort},
								fgo.option2 {$sort},
								fgo.option3 {$sort},
								fgo.option4 {$sort},
								fgo.option5 {$sort}";
			} else {
				$orderStr = "fg.{$orderby} {$sort}";
			}
		}

		$field = "fg.goods_seq,fg.goods_name,fg.goods_code,fgo.option_seq,
					fgo.option1,fgo.optioncode1,fgo.option2,fgo.optioncode2,fgo.option3,fgo.optioncode3,fgo.option4,fgo.optioncode4,fgo.option5,fgo.optioncode5";
		$tables = " fm_goods as fg	INNER JOIN fm_goods_option as fgo ON fg.goods_seq = fgo.goods_seq";
		$limitStr = " LIMIT {$params['page']}, {$params['perpage']}";

		$sql				= array();
		$sql['field']		= $field;
		$sql['table']		= $tables;
		$sql['wheres']		= $wheres;
		$sql['countWheres']	= $countWhere;
		$sql['groupby']		= $groupBy;
		$sql['orderby']		= $orderStr;
		$sql['limit']		= $limitStr;
		
		$datalist				= pagingNumbering($sql,$params);


		for($i=1; $i<$print_start_num; $i++){
			$data['record'][] = array('display' => 'none');
		}
		
		unset($goods_seq_list);
		unset($option_seq_list);

		foreach($datalist['record'] as $key=>$val){
			unset($option_names);
			unset($option_codes);
			for($i=1; $i<=5; $i++){
				if($val['option'.$i] != '')		$option_names[$i] = $val['option'.$i];
				if($val['optioncode'.$i] != '') $option_codes[$i] = $val['optioncode'.$i];
			}			
			
			$tmp_arr = array();
			$tmp_arr = $val;

			if($list_type == 'select' && $is_print && ($print_stock[$key] =='' || $print_stock[$key] == 0)) continue;				
			$tmp_arr['option_title']		= implode('/', $option_names);	//옵션 이름들을 묶어줌
			$tmp_arr['option_code']		= implode($option_codes);			//옵션코드를 묶어줌 
			$tmp_arr['option_code_cell']	= implode(',', $option_codes);	//옵션코드를 묶어줌 (엑셀 다운로드용)
			$data['record'][] = $tmp_arr;
			
			$goods_seq_list[]	= $datalist['record'][$key]['goods_seq'];
			$option_seq_list[]	= $datalist['record'][$key]['option_seq'];
			
			if($list_type == 'all' && $is_print){
				for($i=1; $i<$print_page_cnt; $i++){
					$data['record'][] = $tmp_arr;
				}
			}else if($list_type == 'select' && $is_print){
				for($i=1; $i<$print_stock[$key]; $i++){
					$data['record'][] = $tmp_arr;
				}
			}			
		}

		//바코드가 양식설정의 rowcount 보다 적게 선택 된 경우 셀이 100% 로 늘어나는 문제 해결
		$tmp_cellcnt = $params['print_config']['cellcount'];
		if($tmp_cellcnt > 0 && count($data['record']) % $tmp_cellcnt != 0){
			$tmp_divi = $tmp_cellcnt - count($data['record']) % $tmp_cellcnt;
			
			for($i=0; $i<$tmp_divi; $i++){
				$data['record'][] = array('display' => 'none');
			}
		}

		//페이지 검색, 정렬 정보를 넣는다.
		$data['gtype']				= $gtype;		
		$data['btype']				= $btype;		
		$data['bsubtype1']			= $bsubtype1;	
		$data['bsubtype2']			= $bsubtype2;	
		$data['keyword']			= $keyword;	
		$data['sorderby']			= $sort.'_'.$orderby;
		$data['orderby']			= $orderby;	
		$data['sort']				= $sort;
		$data['perpage']			= $params['perpage'];
		$data['goods_seq_list']		= $goods_seq_list;
		$data['option_seq_list']	= $option_seq_list;
		$data['goods_stock']		= $print_stock;
		$data['search_type']		= $search_type;	
		$data['page']				= $datalist['page'];
	
		return $data;
	}
	
	//창고 리스트
	public function getstorelist($goods_seq_list, $option_seq_list){
		
		$where_query .= 'AND ( 1 != 1 ';
		for($i = 0; $i < count($goods_seq_list); $i++){
			$where_query .= ' OR (goods_seq = '.$goods_seq_list[$i];
			$where_query .= ' AND option_seq = '.$option_seq_list[$i].') ';
		}
		$where_query .= ' ) ';

		//SCM 버전과 분기
		if( serviceLimit('H_SC') ){
			
			$query = 'SELECT * 
					FROM fm_scm_location_link
					WHERE 1=1 
					' . $where_query;
		}else{
			$query = 'SELECT goods_seq,
							option_seq,
							stock as ea,
							badstock as bad_ea
				FROM fm_goods_supply
				WHERE 1=1 ' . $where_query;
		}

		$result_set = $this->db->query($query);
		$datalist	= $result_set->result_array();
		$data		= array();
		
		foreach($datalist as $val){
			$goods_seq					= $val['goods_seq'];
			$option_seq					= $val['option_seq'];
			$ea							= $val['ea'];
			$bad_ea						= $val['bad_ea'];
			
			$data[$goods_seq][$option_seq]['ea']		+= $ea;
			$data[$goods_seq][$option_seq]['bad_ea']	+= $bad_ea;
			
			//SCM 버전일 경우 재고클릭 시 창고 목록 팝업 이벤트 추가
			if( serviceLimit('H_SC') ){
				$data[$goods_seq][$option_seq]['prefix']	= '<a class="underline" onclick="openGoodsWarehouseStock(\'goods_scm_warehouse_info\', \'\', \''.$goods_seq.'\', \'\', \'\');">';
				$data[$goods_seq][$option_seq]['suffix']	= '</a>';
			}
			
		}

		return $data;
	}
	
	//바코드 정보 업데이트 (단일 업데이트)
	public function set_barcode_data($param){
		
		$result = array();
		try{
			//상품코드를 UPDATE
			$query = $this->db->select('goods_code')
			->from('fm_goods')
			->where('goods_seq', $param['goods_seq']);

			$result_set = $query->get();
			$row		= $result_set->result_array();
			$refer_code = $row[0]['goods_code'];
			
			//이전에 등록 된 코드와 같으면 UPDATE 실행 안함
			if($param['goods_code'] && $refer_code != $param['goods_code']){
				$this->db->where('goods_seq', $param['goods_seq']);
				$this->db->update('fm_goods', ['goods_code' => $this->security->xss_clean($param['goods_code'])]);
			}

			//옵션코드를 UPDATE
			//옵션코드 배열을 바인딩 처리
			$bind = [];
			for($i=0; $i<5; $i++){
				$bind['optioncode'.($i+1)] = $this->security->xss_clean($param['option_code'][$i]);
			}

			$this->db->where('goods_seq', $param['goods_seq']);
			$this->db->where('option_seq', $param['option_seq']);
			$this->db->update('fm_goods_option', $bind);

			$result['code'] = 200;
			$result['msg'] = '바코드 정보 등록완료';
		}catch(exception $e){
			$result['code'] = 500;
			$result['msg'] = $e;
		}
		
		return $result;
	}
	
	//바코드 생성
	public function create_barcode($code_type, $code_subtype=null, $code_val, $code_size=20){
		$this->load->library('barcode');
		$this->barcode->codetype = $code_type;
		$this->barcode->text = $code_val;
		$this->barcode->size = $code_size;

		if($code_subtype != null){
			$this->barcode->codesubset = $code_subtype;
		}
		
		$this->barcode->draw();
	}
	
	//바코드 타입별 유효성 검사
	public function validate_barcode($code_type, $code_subtype=null, $code_val){
		$validate_arr = array(
			'N'		=> '/[0-9]+/',				//숫자
			'N9'	=> '/[0-9]{10}/',			//10자리 숫자 (ISBN 용)
			'N13'	=> '/978[0-9]{10}/',		//13자리 숫자 (ISBN 용)
			'U'		=> '/[A-Z]+/',				//대문자
			'L'		=> '/[a-z]+/',				//소문자
			'C'		=> '/[ %\$\-\/]+/',			//Control Character (연산자)
			'CA'	=> '/[ #\&\+\-%@=\/\\\:;,\.\'\"\^`~\_|\!\?\*$#<>()\[\]\{\}]/' //특수문자
		);

		//타입별 지원하는 정규식 설정
		if($code_type == 'code39'){

			$vali[] = 'N';
			$vali[] = 'U';
			$vali[] = 'C';

		}else if($code_type == 'code128'){
			
			if($code_subtype == 'Start A'){

				$vali[] = 'N';
				$vali[] = 'U';
				$vali[] = 'C';

			}else if($code_subtype == 'Start B'){

				$vali[] = 'N';
				$vali[] = 'U';
				$vali[] = 'L';
				$vali[] = 'C';

			}else if($code_subtype == 'Start C'){

				$vali[] = 'N';

			}else{

				$vali[] = 'N';
				$vali[] = 'U';
				$vali[] = 'L';
				$vali[] = 'C';

			}			

		}else if($code_type == 'isbn'){

			$vali[] = 'N13';
		
		}
		
		$result = $code_val;
		foreach($vali as $val){
			$result = preg_replace($validate_arr[$val], '', $result);
		}
	
		return $result == '' ? true : false;
	}
	
	//바코드 이미지 태그 자동 생성
	public function create_barcode_html($config, $code, $size=20){
		//바코드 설정 정보
		$barcode_info		= $this->get_barcode_info();
		$origin_code_type	= $barcode_info[$config];			
		$chk_subtype		= explode('_', $origin_code_type);
		if(count($chk_subtype) > 1){
			$code_type		= $chk_subtype[0];
			$code_subtype	= 'Start '. strtoupper($chk_subtype[1]);
		}else{
			$code_type		= $origin_code_type;
			$code_subtype	= null;
		}

		if($code != ''){
			if($this->validate_barcode('isbn', $code_subtype, $code)){
				$barcode = '<img src="/common/barcode_image?code_type=isbn&code_value='.$code.'&code_size='.$size.'" />';
			}else if($this->validate_barcode($code_type, $code_subtype, $code)){
				$barcode = '<img src="/common/barcode_image?code_type='.$origin_code_type.'&code_value='.$code.'&code_size='.$size.'" />';
			}else{
				$barcode = '<p style="margin: 5px; color: red">바코드 형식이 맞지 않습니다.</p>';
				$data_arr['barcode_fail_count']++;
			}
		}else{
			$barcode = '<p style="margin: 5px; color: red">코드 정보가 없습니다.</p>';
		}

		return $barcode;
	}

	public function get_barcode_info(){
		$barcode_config	= config_load('barcode');

		if(!$barcode_config){
			foreach($this->print_info as $key=>$val){
				config_save('barcode', array( $key	=> $val));
			}		
			foreach($this->barcode_info as $key=>$val){
				config_save('barcode', array( $key	=> $val));
			}

			config_save('barcode', array( 'use_form'		=> 'form39' ));
			config_save('barcode', array( 'use_code'		=> 'code39' ));
			config_save('barcode', array( 'use_code_order'	=> 'code39' ));
		}else{
			if(!$barcode_config['use_form'])			config_save('barcode', array( 'use_form'		=> 'form39' ));
			if(!$barcode_config['use_code'])			config_save('barcode', array( 'use_code'		=> 'code39' ));
			if(!$barcode_config['use_code_order'])	config_save('barcode', array( 'use_code_order'	=> 'code39' ));
		}

		return $barcode_config;
	}
}
?>
