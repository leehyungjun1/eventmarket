<?php
/*----------------------------------/
 * 입점마케팅 엔진페이지
 * @author lwh
 * @since version 1.0 - 2015-11-27
 * @since version 2.0 - 2016-02-25
/----------------------------------*/
class Partnermodel extends CI_Model {

	# EP 3.0 Format
	protected $format = array(
		'id' => array('type' => 'varchar', 'length' => 50, 'require' => true), # 상품ID (A12345)
		'title' => array('type' => 'varchar', 'length' => 100, 'require' => true), # 상품명 (라인 대형 코니 인형(35cm))
		'price_pc' => array('type' => 'int', 'length' => 10, 'require' => true), # 상품 가격 (20000 / Only 원화 기준)
		'price_mobile' => array('type' => 'int', 'length' => 10, 'require' => false), # 모바일 상품 가격 (20000 / Only 원화 기준)
		'normal_price' => array('type' => 'int', 'length' => 10, 'require' => false), # 정가 (20000 / Only 원화 기준 / 할인전 가격이 같거나 구분되지 않을 경우 표기안함)
		'link' => array('type' => 'varchar', 'length' => 255, 'require' => true), # 상품 URL (http://www.naver.com/php?pro=12345)
		'mobile_link' => array('type' => 'varchar', 'length' => 255, 'require' => false), # 상품모바일 URL (http://m.naver.com/php?pro=12345)
		'image_link' => array('type' => 'varchar', 'length' => 255, 'require' => true), # 이미지 URL (http://www.naver.com/image/12345.jpg)
		'add_image_link' => array('type' => 'varchar', 'length' => 2560, 'require' => false), # 추가 이미지 URL ({이미지1 URL}|{이미지2 URL} / 최대 10개)
		'category_name1' => array('type' => 'varchar', 'length' => 50, 'require' => true), # 제휴사 카테고리명(대분류) (디지털/가전)
		'category_name2' => array('type' => 'varchar', 'length' => 50, 'require' =>  false), # 제휴사 카테고리명(중분류) (휴대폰)
		'category_name3' => array('type' => 'varchar', 'length' => 50, 'require' => false), # 제휴사 카테고리명(소분류) (skt)
		'category_name4' => array('type' => 'varchar', 'length' => 50, 'require' => false), # 제휴사 카테고리명(세분류) (갤럭시)
		'naver_category' => array('type' => 'varchar', 'length' => 8, 'require' => false), # 네이버 카테고리 (50000805)
		'naver_product_id' => array('type' => 'varchar', 'length' => 50, 'require' => false), # 가격비교 페이지 ID (8535546055)
		'condition' => array('type' => 'varchar', 'length' => 10, 'require' => false), # 상품상태 (신상품 / 신상품, 중고, 리퍼, 전시, 반품, 스크래치)
		'import_flag' => array('type' => 'varchar', 'length' => 1, 'require' => false), # 해외구매대행 여부 (Y)
		'parallel_import' => array('type' => 'varchar', 'length' => 1, 'require' => false), # 병행수입 여부 (Y)
		'order_made' => array('type' => 'varchar', 'length' => 1, 'require' => false), # 주문제작상품 여부 (Y)
		'product_flag' => array('type' => 'varchar', 'length' => 10, 'require' => false), # 판매방식 구분 (도매 / 도매, 렌탈, 대여, 할부, 예약판매, 구매대행)
		'rental_info' => array('type' => 'varchar', 'length' => 13, 'require' => false), # 렌탈상품의 월렌탈료(최대 9자리) 및 렌탈계약기간(최대 3자리) (50000^12)
		'adult' => array('type' => 'varchar', 'length' => 1, 'require' => false), # 미성년자 구매 불가 상품 여부 (Y)
		'goods_type' => array('type' => 'varchar', 'length' => 10, 'require' => false), # 상품구분 (MA / MA: 마트, DF: 면세점, HS: 홈쇼핑, DP: 백화점 상품)
		'barcode' => array('type' => 'varchar', 'length' => 13, 'require' => false), # 바코드 (8801234560016)
		'manufacture_define_number' => array('type' => 'varchar', 'length' => 100, 'require' => false), # 제조사 제품코드 (BC4764007M)
		'model_number' => array('type' => 'varchar', 'length' => 60, 'require' => false), # 모델명 (SCH-M620)
		'brand' => array('type' => 'varchar', 'length' => 60, 'require' => false), # 브랜드 (갤럭시)
		'maker' => array('type' => 'varchar', 'length' => 60, 'require' => false), # 제조사 (삼성전자)
		'origin' => array('type' => 'varchar', 'length' => 30, 'require' => false), # 원산지 (중국)
		'card_event' => array('type' => 'varchar', 'length' => 100, 'require' => false), # 할인카드명/카드할인가 (신한카드^10000|KB국민카드^11000)
		'event_words' => array('type' => 'varchar', 'length' => 100, 'require' => false), # 이벤트 (10주년 10%할인 이벤트)
		'coupon' => array('type' => 'varchar', 'length' => 100, 'require' => false), # 일반/제휴쿠폰 (1000원^5)
		'partner_coupon_download' => array('type' => 'varchar', 'length' => 1, 'require' => false), # 쿠폰다운로드 필요 여부 (Y)
		'interest_free_event' => array('type' => 'varchar', 'length' => 100, 'require' => false), # 카드 무이자할부 정보 (삼성카드^2~3|신한카드^2~3)
		'point' => array('type' => 'varchar', 'length' => 50, 'require' => false), # 포인트 (쇼핑몰자체포인트^400|OK캐쉬백^300)
		'installation_costs' => array('type' => 'varchar', 'length' => 1, 'require' => false), # 별도 설치비 유무 (Y)
		'pre_match_code' => array('type' => 'varchar', 'length' => 100, 'require' => false), # 사전매칭 코드 (1234)
		'search_tag' => array('type' => 'varchar', 'length' => 100, 'require' => false), # 검색태그 (물방울패턴원피스|2016 S/S신상 원피스)
		'group_id' => array('type' => 'varchar', 'length' => 50, 'require' => false), # 그룹ID (A12345)
		'vendor_id' => array('type' => 'varchar', 'length' => 500, 'require' => false), # 제휴사 상품ID (네이버^A12345|네이버1^A123456)
		'coordi_id' => array('type' => 'varchar', 'length' => 500, 'require' => false), # 코디 상품ID (A12345|A12346|A12347)
		'minimum_purchase_quantity' => array('type' => 'varchar', 'length' => 10, 'require' => false), # 최소구매수량 (100)
		'review_count' => array('type' => 'varchar', 'length' => 10, 'require' => false), # 상품평 개수 (320)
		'shipping' => array('type' => 'varchar', 'length' => 100, 'require' => true), # 배송료 (2500)
		'delivery_grade' => array('type' => 'varchar', 'length' => 1, 'require' => false), # 차등배송비 여부 (Y)
		'delivery_detail' => array('type' => 'varchar', 'length' => 100, 'require' => false), # 차등배송비 내용 (서울 경기 무료배송/ 강원, 충청 2만원 추가)
		'attribute' => array('type' => 'varchar', 'length' => 500, 'require' => false), # 속성 (서울^1개^오션뷰^2명^주중^조식포함^무료주차^와이파이)
		'option_detail' => array('type' => 'varchar', 'length' => 1000, 'require' => false), # 구매옵션 (레이스원피스^23000|멜빵원피스^25000|…)
		'seller_id' => array('type' => 'varchar', 'length' => 50, 'require' => false), # 셀러ID (abcde123)
		'age_group' => array('type' => 'varchar', 'length' => 50, 'require' => false), # 나이 (성인 / 유아, 아동, 청소년, 성인)
		'gender' => array('type' => 'varchar', 'length' => 10, 'require' => false), # 성별 (남녀공용 / 남성, 여성, 남녀공용)
		//'class' => array('type' => 'varchar', 'length' => 1, 'require' => true), # 업데이트 구분 (U / I: 신규상품, U: 기존상품 중 업데이트 또는 품절뒤 판매재개한 상품, D: 품절상품)
		//'update_time' => array('type' => 'varchar', 'length' => 19, 'require' => true), # 업데이트 시간 (2016-11-16 00:00:00)		
	);
	
	// [판매지수 EP] 필드조건 추가 :: 2018-09-19 pjw
	protected $format_sale = array(
		'mall_id'		=> array('type' => 'varchar', 'length' => 50, 'require' => true ),
		'sale_count'	=> array('type' => 'int', 'length' => 6, 'require' => true ),
		'sale_price'	=> array('type' => 'int', 'length' => 10, 'require' => true ),
		'order_count'	=> array('type' => 'int', 'length' => 6, 'require' => true ),
		'dt'			=> array('type' => 'varchar', 'length' => 10, 'require' => true )
	);

	protected $facebook = array('id', 'title', 'description', 'link', 'image_link', 'availability', 'price', 'brand', 'condition', 'shipping', 'identifier_exists');
	
	protected $google = array('id', 'title', 'description', 'link', 'image_link', 'availability', 'price', 'brand', 'condition', 'adult', 'shipping', 'identifier_exists');
	
	public function __construct(){
		parent::__construct();

		$this->load->helper('order');
		$this->load->model('goodsmodel');
		$this->load->model('categorymodel');
		$this->load->model('membermodel');
		$this->load->model('configsalemodel');
		$this->load->model('shippingmodel');
		$this->load->library('sale');

		if( $this->config_system['pgCompany'] ){
			$payment_gateway = config_load($this->config_system['pgCompany']);
			$payment_gateway['rCardCompany'] = code_load($this->config_system['pgCompany'].'CardCompanyCode');

			foreach($payment_gateway['rCardCompany'] as $k => $v){
				$payment_gateway['arrCardCompany'][$v['codecd']]=$v['value'];
			}
		}

		if($payment_gateway['pcCardCompanyCode']) foreach($payment_gateway['pcCardCompanyCode'] as $k => $code)
		{
			$tmp = explode(',',$payment_gateway['pcCardCompanyTerms'][$k]);
			if( in_array($code,array('ALL')) ) $str_noint = $tmp[count($tmp)-1]."개월";

			if(count($tmp) > 1){
				$r_tmp_noint[] = $payment_gateway['arrCardCompany'][$code].$tmp[0].'~'.$tmp[count($tmp)-1];
			}else{
				$r_tmp_noint[] = $payment_gateway['arrCardCompany'][$code].$tmp[0];
			}
		}
		if(!$str_noint && $r_tmp_noint){
			$str_noint = implode('/',$r_tmp_noint);
		}
		$this->noint = $str_noint;
	}

	### 크론용 입점 마케팅 다음 파일 생성 :: 2015-11-30 lwh
	public function cron_daumFile($mode='all',$type='file'){

		if($this->config_basic['daum_use'] != 'Y'){
			return false;
		}

		// 크론에서 돌아가기 때문에 도메인 정보 추출
		$config_system = $this->config_system;
		$domain = ($config_system['domain']) ? $config_system['domain'] : $config_system['subDomain'];
		// 한글도메인 문제로 euc-kr로 변환
		$domain		= iconv('UTF-8', 'euc-kr',$domain);

		$last_update_date = '';

		if($mode == 'summary'){
			$tmp = config_load('partner','daum_update');
			if($tmp['daum_update']) $last_update_date = $tmp['daum_update'];
		}

		if($type == 'file'){
			$file_path	= ROOTPATH."/ep/daum_".$mode.".txt";

			$dir_name	= dirname($file_path);
			if( !is_dir($dir_name) )	@mkdir($dir_name);
			@chmod($dir_name,0777);
			@chmod($file_path,0777);

			$fp = fopen($file_path,"w+");
		}

		// EUC-KR 선언
		header("Content-Type: text/html; charset=EUC-KR");

		// 마케팅 전달 이미지
		$market_image	= config_load('marketing_image');
		if($market_image['daumImage']=='B' || !$market_image['daumImage']){
			$view_type	= "view";
		}else if($market_image['daumImage']=='C'){
			$view_type	= "large";
		}

		// 입점마케팅 상품 추가할인
		$marketing_sale = config_load('marketing_sale');
		$arr_marketing_sale = array();
		if (isset($marketing_sale)) {
			// 회원 등급별 할인 적용 [member_sale_type => 0 : 비회원 , 1 : 일반 등급 회원]
			if ($marketing_sale['member']=='Y' && $marketing_sale['member_sale_type']==1) $arr_marketing_sale['member'] = 'Y';

			// 할인 유입경로 적용
			if ($marketing_sale['referer']=='Y') 	{
				$arr_marketing_sale['referer'] = 'Y';
				$arr_marketing_sale['referer_url'] = 'shopping.daum.net';
			}

			// 할인 쿠폰 적용
			if ($marketing_sale['coupon']=='Y') $arr_marketing_sale['coupon'] = 'Y';
		}

		// 입점마케팅 전달 데이터 통합 설정 - 입점마케팅 상품명,카드무이자할부 leewh 2015-01-29
		$marketing_feed = config_load('marketing_feed');

		$query	= $this->goodsmodel->get_goods_all_partner($last_update_date,$view_type,true);

		$result	= mysqli_query($this->db->conn_id,$query);
		if($mode == 'all'){
			$data_goods_cnt	= mysqli_num_rows($result);
			if($data_goods_cnt > 0){
				$total_goods_cnt = "<<<tocnt>>>".$data_goods_cnt;
				if($type == 'file')		fwrite($fp,$total_goods_cnt."\r\n");
				else					echo $total_goods_cnt."\r\n";
			}
		}

		//----> sale library 적용
		$cfg_reserve			= ($this->reserves) ? $this->reserves : config_load('reserve');
		$param['cal_type']		= 'list';
		$param['reserve_cfg']	= $cfg_reserve;
		$this->sale->set_init($param);
		$this->sale->preload_set_config('list');
		//<---- sale library 적용

		while ($data_goods = mysqli_fetch_array($result)){ // 20130325
			if ($data_goods['feed_goods_use']=='Y' && !empty($data_goods['feed_goods_name'])
				|| $data_goods['feed_goods_use']=='N' && !empty($marketing_feed['goods_name'])) {

				// 상품 검색어 자동
				$data_goods['keyword'] = $data_goods['openmarket_keyword'];

				## 상품명 치환코드
				$replaceArr = array();
				$replaceArr['{product_name}'] = strip_tags($data_goods['goods_name']);
				$replaceArr['{product_category}'] = strip_tags($data_goods['category_title']);
				$replaceArr['{product_brand}'] = strip_tags($data_goods['brand_title']);
				$replaceArr['{product_tag}'] = strip_tags($data_goods['keyword']);

				if ($data_goods['feed_goods_use']=='Y') {
					$data_goods['goods_name'] = $this->get_replace_goods_name($data_goods['feed_goods_name'],$replaceArr);
				} else if($data_goods['feed_goods_use']=='N') {
					$data_goods['goods_name'] = $this->get_replace_goods_name($marketing_feed['goods_name'],$replaceArr);
				}
			} 
			$data_goods['goods_name']	= strip_tags(iconv('UTF-8', 'euc-kr',$data_goods['goods_name']));

			$data_goods['brand']		= iconv('UTF-8', 'euc-kr',$data_goods['brand']);
			if	($marketing_feed['brand_kind'] == 'brand')
				$data_goods['brand']	= iconv('UTF-8', 'euc-kr',$data_goods['brand_title']);
			$data_goods['manufacture']	= iconv('UTF-8', 'euc-kr',$data_goods['manufacture']);
			$data_goods['orgin']		= iconv('UTF-8', 'euc-kr',$data_goods['orgin']);
			$data_goods['feed_evt_text']= iconv('UTF-8', 'euc-kr',$data_goods['feed_evt_text']);

			$data_goods['goods_url']	= 'http://'.$domain."/goods/view?no=".$data_goods['goods_seq'].'&market=daum';

			if(preg_match('/http/', $data_goods['image'])){
				$data_goods['image_url'] = $data_goods['image'];
			}else{
				$data_goods['image_url'] = 'http://'.$domain.iconv('UTF-8', 'euc-kr',$data_goods['image']);
			}


			// 카테고리
			$category_arr ='';
			$arr_category_code = array();
			$arr_category_code = $this->categorymodel->split_category($data_goods['category_code']);
			for($i=0;$i<4;$i++) {
				$data_goods['arr_category_code'][$i] = "";
				$data_goods['arr_category'][$i] = "";
				if( $arr_category_code[$i] ){

					$data_goods['arr_category_code'][$i] = $arr_category_code[$i];
					$data_goods['arr_category'][$i] =  ($this->categorymodel->one_category_name($arr_category_code[$i]));

					if (count($arr_category_code) > ($i+1))
						$category_arr .= "<<<cate".($i+1).">>>".iconv('UTF-8', 'euc-kr',$data_goods['arr_category'][$i])."\r\n<<<caid".($i+1).">>>".$data_goods['arr_category_code'][$i]."\r\n";
					else {
						$category_arr .= "<<<cate".($i+1).">>>".iconv('UTF-8', 'euc-kr',$data_goods['arr_category'][$i])."\r\n<<<caid".($i+1).">>>".$data_goods['arr_category_code'][$i];
					}
				}
			}

			$data_goods['shop_name'] = $this->config_basic['shopName'];
			$data_goods['shop_name'] = iconv('UTF-8', 'euc-kr',$data_goods['shop_name']);

			// 2014-01-16 이벤트 문구 미노출 추가
			if($data_goods['feed_evt_sdate'] == '0000-00-00') $data_goods['feed_evt_sdate'] = '';
			if($data_goods['feed_evt_edate'] == '0000-00-00') $data_goods['feed_evt_edate'] = '';
			$data_goods['event']	= $data_goods['feed_evt_text'];
			if( time() < strtotime($data_goods['feed_evt_sdate'].' 00:00:00') || strtotime($data_goods['feed_evt_edate'].' 23:59:59') < time() ){
				$data_goods['event']	= '';
			}

			// 상품가격 추가할인 설정
			if ($arr_marketing_sale) $data_goods['marketing_sale'] = $arr_marketing_sale;

			// 할인가 적용
			$data_goods['price'] = $this->apply_sale($data_goods);

			// EP 배송비 추출 :: 2017-02-24 lwh
			if($data_goods['goods_kind'] == "coupon"){
				$data_goods['feed_info']['std_txt'] = "0";
			}else{
				$data_goods['category_code'] = $data_goods['arr_category_code'];

				unset($feed_data);
				$feed_data['feed_pay_type']		= $data_goods['feed_pay_type'];
				$feed_data['feed_std_fixed']	= $data_goods['feed_std_fixed'];
				$feed_data['feed_add_txt']		= $data_goods['feed_add_txt'];
				$data_goods['feed_info'] = $this->shippingmodel->get_ep_data($data_goods['feed_ship_type'], $data_goods['shipping_group_seq'], $feed_data, $data_goods);

				$data_goods['feed_info']['std_txt'] = get_currency_price($data_goods['feed_info']['std'],1);
			}

			// 무이자
			if (!empty($marketing_feed['cfg_card_free'])) {
				$cfg_card_free = preg_replace("/\//",",",$marketing_feed['cfg_card_free']);
				$noint = trim(iconv('UTF-8', 'euc-kr',$cfg_card_free));
			} else {
				$cfg_card_free = preg_replace("/\//",",",$this->noint);
				$noint = trim(iconv('UTF-8', 'euc-kr',$cfg_card_free));
			}

			// 모바일 할인가 계산
			$systemmobiles = $this->configsalemodel->get_mobile_sale_for_goods($data_goods['price']);
			$mobile_price = 0;
			if($systemmobiles['sale_price'] && $data_goods['price']){
				$mobile_sale = $systemmobiles['sale_price'] * $data_goods['price'] / 100;
				$mobile_price = $data_goods['price'] - $mobile_sale;
				$mobile_price = $this->sale->cut_sale_price($mobile_price);
			}

			// 요약정보 일때 어떤 정보인지 상태 체크
			if($mode == 'summary'){
				$type_class = 'U';
				if($data_goods['regist_date'] == $data_goods['update_date']){
					$type_class = 'I';
				}
			}

			unset($loop);
			$loop[] = "<<<begin>>>"; // 시작
			$loop[] = "<<<mapid>>>".$data_goods['goods_seq']; // 상품번호
			if($data_goods['consumer_price'] != '0.00' && $data_goods['consumer_price'] != $data_goods['price'])
				$loop[] = "<<<lprice>>>".(int)$data_goods['consumer_price']; // 원판매가
			$loop[] = "<<<price>>>".$data_goods['price']; // 할인적용가
			if($mobile_price) $loop[] = "<<<mpric>>>".$mobile_price; // 모바일할인적용가
			if($mode == 'summary'){
				$loop[] = "<<<class>>>".$type_class; // 요약정보 상태값
				$loop[] = "<<<utime>>>".date('YmdHis',strtotime($data_goods['update_date'])); // 업데이트 시간
			}
			$loop[] = "<<<pname>>>".$data_goods['goods_name']; // 상품명
			$loop[] = "<<<pgurl>>>".$data_goods['goods_url']; // 상품URL
			$loop[] = "<<<igurl>>>".$data_goods['image_url']; // 이미지URL
			if($img_chg) // 현재는 알 방법이 없음
				$loop[] = "<<<upimg>>>Y"; // 이미지 변경여부

			$loop[] = ${category_arr}; // 카테고리ID , 카테고리명
			if($data_goods['model']) // 모델명
				$loop[] = "<<<model>>>".iconv('UTF-8', 'euc-kr',$data_goods['model']);
			if($data_goods['brand']) // 브랜드명
				$loop[] = "<<<brand>>>".$data_goods['brand'];
			if($data_goods['manufacture']) // 제조사
				$loop[] = "<<<maker>>>".$data_goods['manufacture'];
			//$loop[] = "<<<coupon>>>".$data_goods['coupon'];
			if ($data_goods['coupon_won'])
					$loop[] = "<<<coupo>>>".$data_goods['coupon_won']; // 쿠폰/제휴쿠폰
			if($noint)$loop[] = "<<<pcard>>>".$noint; // 무이자 할부
			if($data_goods['reserve'] > 0){
				$reserve_unit = iconv('UTF-8', 'euc-kr',"원");
				$loop[] = "<<<point>>>".(int)$data_goods['reserve'].$reserve_unit; // 포인트/마일리지
			}

			// 배송비 재개선 :: 2017-02-24 lwh
			$loop[] = "<<<deliv>>>".$data_goods['feed_info']['std_txt']; // 배송비
			if($data_goods['feed_info']['add_txt']){ // 차등배송비내용
				$loop[] = "<<<dlvdt>>>".iconv('UTF-8', 'euc-kr',$data_goods['feed_info']['add_txt']);
			}
			if($data_goods['review_count'] > 0){
				$loop[] = "<<<revct>>>".$data_goods['review_count']; // 상품평 수
				$avg_score = $data_goods['review_sum'] / $data_goods['review_count'];
				$loop[] = "<<<rating>>>".number_format($avg_score,1)."/5"; // 상품평 평점/만점
			}

			if($data_goods['event']) // 이벤트 강조내용 문자10개
				$loop[] = "<<<event>>>".$data_goods['event'];
			if($data_goods['adult_goods'] == 'Y')
				$loop[] = "<<<adult>>>Y"; // 성인상품여부 (일반상품시 필드자체X)

			$loop[] = "<<<ftend>>>";

			if($type == 'file')
				fwrite($fp,implode("\r\n",$loop)."\r\n");
			else
				echo implode("\r\n",$loop)."\r\n";

			$this->db->queries = array();
			$this->db->query_times = array();
		}

		// 품절상품 추출 :: 2016-01-11
		if($mode == 'summary'){
			$run_sql = "
			SELECT goods_seq, update_date
			FROM fm_goods
			WHERE
				goods_type='goods'
				AND goods_status IN ('runout','purchasing','unsold')
				AND goods_view = 'look'
				AND string_price_use != '1'
				AND (feed_status = 'Y' or feed_status is NULL)
				AND update_date > '".$last_update_date."'
			";
			$run_res	= mysqli_query($this->db->conn_id,$run_sql);
			while ($data_run = mysqli_fetch_array($run_res)){
				$run_loop[] = "<<<begin>>>";
				$run_loop[] = "<<<mapid>>>".$data_run['goods_seq'];
				$run_loop[] = "<<<class>>>D";
				$run_loop[] = "<<<utime>>>".date('YmdHis',strtotime($data_run['update_date']));
				$run_loop[] = "<<<ftend>>>";
			}

			echo implode("\r\n",$run_loop)."\r\n";
		}

		$now = date('Y-m-d H:i:s');
		if($type == 'file') {
			fclose($fp);

			// 파일 생성시간 및 파일 사이즈 저장
			$filesize = filesize($file_path) / 1024;
			$filesize = number_format($filesize,1);
			config_save('partner',array('daum_file_time'=>$now, 'daum_file_size'=>$filesize));
		}

		// 읽어간 시간 저장
		$now = date('Y-m-d H:i:s');
		config_save('partner',array('daum_update'=>$now));
	}

	### 크론용 입점 마케팅 네이버 파일 생성 :: 2015-12-01 lwh
	public function cron_naverFile($mode='all',$type='file'){

		if($this->config_basic['naver_use'] != 'Y'){
			return false;
		}

		// 도메인 추출
		$domain		= $this->get_domain();

		$last_update_date = '';

		if($mode == 'summary'){
			$tmp = config_load('partner','naver_update');
			if($tmp['naver_update']) $last_update_date = $tmp['naver_update'];
		}

		if($type == 'file'){
			$file_path	= ROOTPATH."/ep/naver_".$mode.".txt";

			$dir_name	= dirname($file_path);
			if( !is_dir($dir_name) )	@mkdir($dir_name);
			@chmod($dir_name,0777);
			@chmod($file_path,0777);

			$fp = fopen($file_path,"w+");
		}

		// EUC-KR 선언
		header("Content-Type: text/html; charset=EUC-KR");

		// 마케팅 전달 이미지
		$market_image	= config_load('marketing_image');
		if($market_image['naverImage']=='B' || !$market_image['naverImage']){
			$view_type	= "view";
		}else if($market_image['naverImage']=='C'){
			$view_type	= "large";
		}

		$all_category = $this->categorymodel->get_all();
		foreach ($all_category as $row){			
			$cate[$row['category_code']] = $row['title'];
		}

		$arr_status['normal'] = "U";
		$arr_status['runout'] = "D";
		$arr_status['unsold'] = "D";

		// 입점마케팅 상품 추가할인
		$marketing_sale = config_load('marketing_sale');
		$arr_marketing_sale = array();
		if (isset($marketing_sale)) {
			// 회원 등급별 할인 적용 [member_sale_type => 0 : 비회원 , 1 : 일반 등급 회원]
			if ($marketing_sale['member']=='Y' && $marketing_sale['member_sale_type']==1) $arr_marketing_sale['member'] = 'Y';

			// 할인 유입경로 적용
			if ($marketing_sale['referer']=='Y') 	{
				$arr_marketing_sale['referer'] = 'Y';
				$arr_marketing_sale['referer_url'] = 'shopping.naver.com';
			}

			// 할인 쿠폰 적용
			if ($marketing_sale['coupon']=='Y') $arr_marketing_sale['coupon'] = 'Y';

			// 모바일 할인 적용 : 지식쇼핑 모바일가격 별도 계산해서 전달
			//if ($marketing_sale['mobile']=='Y') $arr_marketing_sale['mobile'] = 'Y';
		}

		// 입점마케팅 전달 데이터 통합 설정 - 입점마케팅 상품명,카드무이자할부 leewh 2015-01-29
		$marketing_feed = config_load('marketing_feed');

		$query	= $this->goodsmodel->get_goods_all_partner($last_update_date,$view_type,true);

		$result = mysqli_query($this->db->conn_id,$query);
		if($mode == 'all'){
			$data_goods_cnt	= mysqli_num_rows($result);
			if($data_goods_cnt > 0){
				$total_goods_cnt = "<<<tocnt>>>".$data_goods_cnt;
				if($type == 'file')		fwrite($fp,$total_goods_cnt."\r\n");
				else					echo $total_goods_cnt."\r\n";
			}
		}

		//----> sale library 적용
		$cfg_reserve			= ($this->reserves) ? $this->reserves : config_load('reserve');
		$param['cal_type']		= 'list';
		$param['reserve_cfg']	= $cfg_reserve;
		$this->sale->set_init($param);
		$this->sale->preload_set_config('list');
		//<---- sale library 적용

		while ($data_goods = mysqli_fetch_array($result)){ // 20130325
			if ($data_goods['feed_goods_use']=='Y' && !empty($data_goods['feed_goods_name'])
				|| $data_goods['feed_goods_use']=='N' && !empty($marketing_feed['goods_name'])) {

				// 상품 검색어 자동
				$data_goods['keyword'] = $data_goods['openmarket_keyword'];

				## 상품명 치환코드
				$replaceArr = array();
				$replaceArr['{product_name}'] = strip_tags(iconv('UTF-8', 'euc-kr',$data_goods['goods_name']));
				$replaceArr['{product_category}'] = strip_tags(iconv('UTF-8', 'euc-kr',$data_goods['category_title']));
				$replaceArr['{product_brand}'] = strip_tags(iconv('UTF-8', 'euc-kr',$data_goods['brand_title']));
				$replaceArr['{product_tag}'] = strip_tags(iconv('UTF-8', 'euc-kr',$data_goods['keyword']));

				if ($data_goods['feed_goods_use']=='Y') {
					$data_goods['goods_name'] = $this->get_replace_goods_name($data_goods['feed_goods_name'],$replaceArr);
				} else if($data_goods['feed_goods_use']=='N') {
					$data_goods['goods_name'] = $this->get_replace_goods_name($marketing_feed['goods_name'],$replaceArr);
				}
			}

			$data_goods['goods_name']	= strip_tags(iconv('UTF-8', 'euc-kr',$data_goods['goods_name']));

			$data_goods['brand']		= iconv('UTF-8', 'euc-kr',$data_goods['brand']);
			if	($marketing_feed['brand_kind'] == 'brand')
				$data_goods['brand']	= iconv('UTF-8', 'euc-kr',$data_goods['brand_title']);
			$data_goods['manufacture']	= iconv('UTF-8', 'euc-kr',$data_goods['manufacture']);
			$data_goods['orgin']		= iconv('UTF-8', 'euc-kr',$data_goods['orgin']);
			$data_goods['feed_evt_text']	= iconv('UTF-8', 'euc-kr',$data_goods['feed_evt_text']);
			$data_goods['feed_condition']	= iconv('UTF-8', 'euc-kr',$data_goods['feed_condition']);
			$data_goods['goods_url'] = 'http://'.$domain."/goods/view?no=".$data_goods['goods_seq'].'&market=naver';
			$mourl = str_replace('www.','',$data_goods['goods_url']);
			$data_goods['mourl'] = str_replace('http://','http://m.',$mourl);

			if(preg_match('/http/', $data_goods['image'])){
				$data_goods['image_url'] = $data_goods['image'];
			}else{
				$data_goods['image_url'] = 'http://'.$domain.iconv('UTF-8', 'euc-kr',$data_goods['image']);
			}

			// 카테고리
			$page_code ='';
			$page_name ='';
			$arr_category_code = array();
			$arr_category_code = $this->categorymodel->split_category($data_goods['category_code']);
			for($i=0;$i<4;$i++) {
				$data_goods['arr_category_code'][$i] = "";
				$data_goods['arr_category'][$i] = "";
				if( $arr_category_code[$i] ){

					$data_goods['arr_category_code'][$i] = $arr_category_code[$i];
					$data_goods['arr_category'][$i] =  ($this->categorymodel->one_category_name($arr_category_code[$i]));

					$page_code .="<<<caid".($i+1).">>>".$data_goods['arr_category_code'][$i]."\n";
					$page_name .="<<<cate".($i+1).">>>".iconv('UTF-8', 'euc-kr',$data_goods['arr_category'][$i])."\n";
				}else{
					$page_code .="<<<caid".($i+1).">>>\n";
					$page_name .="<<<cate".($i+1).">>>\n";
				}
			}			

			$data_goods['shop_name'] = $this->config_basic['shopName'];
			$data_goods['shop_name'] = iconv('UTF-8', 'euc-kr',$data_goods['shop_name']);

			// 2014-01-16 이벤트 문구 미노출 추가
			if($data_goods['feed_evt_sdate'] == '0000-00-00') $data_goods['feed_evt_sdate'] = '';
			if($data_goods['feed_evt_edate'] == '0000-00-00') $data_goods['feed_evt_edate'] = '';
			$data_goods['event']	= preg_replace('/[^가-힣0-9a-z!?@#$%^&*_=,.:;\~\'\/\-\+\(\)\[\]\{\}\s]+/iu', '', $data_goods['feed_evt_text']);
			if( time() < strtotime($data_goods['feed_evt_sdate'].' 00:00:00') || strtotime($data_goods['feed_evt_edate'].' 23:59:59') < time() ){
				$data_goods['event']	= '';
			}

			// 상품가격 추가할인 설정
			if ($arr_marketing_sale) $data_goods['marketing_sale'] = $arr_marketing_sale;

			// 할인가 적용
			$data_goods['price'] = $this->apply_sale($data_goods);

			// EP 배송비 추출 :: 2017-02-24 lwh
			if($data_goods['goods_kind'] == "coupon"){
				$data_goods['feed_info']['std_txt'] = "0";
			}else{
				$data_goods['category_code'] = $data_goods['arr_category_code'];

				unset($feed_data);
				$feed_data['feed_pay_type']		= $data_goods['feed_pay_type'];
				$feed_data['feed_std_fixed']	= $data_goods['feed_std_fixed'];
				$feed_data['feed_add_txt']		= $data_goods['feed_add_txt'];
				$data_goods['feed_info'] = $this->shippingmodel->get_ep_data($data_goods['feed_ship_type'], $data_goods['shipping_group_seq'], $feed_data, $data_goods);

				$data_goods['feed_info']['std_txt'] = get_currency_price($data_goods['feed_info']['std'],1);
			}

			// 무이자
			if (!empty($marketing_feed['cfg_card_free'])) {
				$noint = trim(iconv('UTF-8', 'euc-kr',$marketing_feed['cfg_card_free']));
			} else {
				$noint = trim(iconv('UTF-8', 'euc-kr',$this->noint));
			}

			// 모바일 할인가 계산
			$systemmobiles = $this->configsalemodel->get_mobile_sale_for_goods($data_goods['price']);
			$mobile_price = 0;
			if($systemmobiles['sale_price'] && $data_goods['price']){
				$mobile_sale = $systemmobiles['sale_price'] * $data_goods['price'] / 100;
				$mobile_price = $data_goods['price'] - $mobile_sale;
				$mobile_price = $this->sale->cut_sale_price($mobile_price);
			}

			// 요약정보 일때 어떤 정보인지 상태 체크
			if($mode == 'summary'){
				$type_class = 'U';
				if($data_goods['regist_date'] == $data_goods['update_date']){
					$type_class = 'I';
				}
			}

			unset($loop);
			$loop[] = "<<<begin>>>";
			$loop[] = "<<<mapid>>>".$data_goods['goods_seq']; // 상품번호
			$loop[] = "<<<pname>>>".$data_goods['goods_name']; // 상품명
			$loop[] = "<<<price>>>".$data_goods['price']; // 가격
			$loop[] = "<<<pgurl>>>".$data_goods['goods_url']; // 상품 URL
			$loop[] = "<<<igurl>>>".$data_goods['image_url']; // 이미지 URL
			$loop[] = ${page_code}.${page_name}."<<<model>>>".iconv('UTF-8', 'euc-kr',$data_goods['model']); // 카테고리ID , 카테고리명, 모델명
			$loop[] = "<<<brand>>>".$data_goods['brand']; // 브랜드명
			$loop[] = "<<<maker>>>".$data_goods['manufacture']; // 제조사
			$loop[] = "<<<origi>>>".$data_goods['orgin']; // 원산지
			$loop[] = "<<<event>>>".$data_goods['event']; // 이벤트
			if ($data_goods['coupon_won'])$loop[] = "<<<coupo>>>".$data_goods['coupon_won']; // 쿠폰 할인
			if($noint)$loop[] = "<<<pcard>>>".$noint; // 무이자 할부 정보
			$loop[] = "<<<point>>>".$data_goods['reserve']; // 포인트마일리지
			if( $mode == 'summary' ){
				$loop[] = "<<<class>>>".$type_class; // 요약정보 상태값
				$loop[] = "<<<utime>>>".$data_goods['update_date']; // 업데이트 시간
			}
			if($mobile_price) $loop[] = "<<<mpric>>>".$mobile_price; // 모바일할인적용가
			$loop[] = "<<<revct>>>".$data_goods['review_count']; // 상품평 수			

			// 배송비 재개선 :: 2017-02-24 lwh
			$loop[] = "<<<deliv>>>".$data_goods['feed_info']['std_txt']; // 배송비
			if($data_goods['feed_info']['add_txt']){ // 차등배송비내용
				$loop[] = "<<<dlvga>>>Y"; // 차등배송비여부
				$loop[] = "<<<dlvdt>>>".iconv('UTF-8', 'euc-kr',$data_goods['feed_info']['add_txt']);
			}
			$loop[] = "<<<condition>>>".$data_goods['feed_condition']; // 상품상태 (신상품,중고,리퍼,전시,반품,스크래치
			$loop[] = "<<<ftend>>>";

			if($type == 'file')
				fwrite($fp,implode("\r\n",$loop)."\r\n");
			else
				echo implode("\r\n",$loop)."\r\n";

			$this->db->queries = array();
			$this->db->query_times = array();
		}

		// 품절상품 추출 :: 2016-01-11
		if($mode == 'summary'){
			$run_sql = "
			SELECT goods_seq, update_date
			FROM fm_goods
			WHERE
				goods_type='goods'
				AND goods_status IN ('runout','purchasing','unsold')
				AND goods_view = 'look'
				AND string_price_use != '1'
				AND (feed_status = 'Y' or feed_status is NULL)
				AND update_date > '".$last_update_date."'
			";
			$run_res	= mysqli_query($this->db->conn_id,$run_sql);
			while ($data_run = mysqli_fetch_array($run_res)){
				$run_loop[] = "<<<begin>>>";
				$run_loop[] = "<<<mapid>>>".$data_run['goods_seq'];
				$run_loop[] = "<<<class>>>D";
				$run_loop[] = "<<<utime>>>".$data_run['update_date'];
				$run_loop[] = "<<<ftend>>>";
			}

			echo implode("\r\n",$run_loop)."\r\n";
		}

		$now = date('Y-m-d H:i:s');
		if($type == 'file') {
			fclose($fp);

			// 파일 생성시간 및 파일 사이즈 저장
			$filesize = filesize($file_path) / 1024;
			$filesize = number_format($filesize,1);
			config_save('partner',array('naver_file_time'=>$now, 'naver_file_size'=>$filesize));
		}

		// 읽어간 시간 저장
		config_save('partner',array('naver_update'=>$now));
	}

	# EP 3.0 / 크론용 입점 마케팅 네이버 파일 생성
	public function cron_naverThirdFile($mode='all', $type='file'){
		# reset
		$data		= '';
		$soldout	= array();

		$config_system = $this->config_system;
		$service_code = $this->config_system['service']['code'];
		
		// 도메인 추출
		$domain		= $this->get_domain();

		$tmp = config_load('partner','naver_third_update');

		if($type == 'file'){
			$file_path	= ROOTPATH."/ep/naver_third_".$mode.".tsv";
			$dir_name	= dirname($file_path);
			if( !is_dir($dir_name) )	@mkdir($dir_name);
			@chmod($dir_name, 0777);
			@chmod($file_path, 0777);
			$fp = fopen($file_path,"w+");
		}

		// UTF-8 선언
		header("Content-Type: text/html; charset=UTF-8");

		// 마케팅 전달 이미지
		$market_image	= config_load('marketing_image');
		if($market_image['naverImage']=='B' || !$market_image['naverImage']){
			$view_type	= "view";
		}else if($market_image['naverImage']=='C'){
			$view_type	= "large";
		}

		$all_category = $this->categorymodel->get_all();
		foreach ($all_category as $row){
			$cate[$row['category_code']] = $row['title'];
		}

		// 입점마케팅 상품 추가할인
		$marketing_sale = config_load('marketing_sale');
		$arr_marketing_sale = array();
		if (isset($marketing_sale)) {
			// 회원 등급별 할인 적용 [member_sale_type => 0 : 비회원 , 1 : 일반 등급 회원]
			if ($marketing_sale['member']=='Y' && $marketing_sale['member_sale_type']==1) $arr_marketing_sale['member'] = 'Y';

			// 할인 유입경로 적용
			if ($marketing_sale['referer']=='Y') 	{
				$arr_marketing_sale['referer'] = 'Y';
				$arr_marketing_sale['referer_url'] = 'shopping.naver.com';
			}

			// 할인 쿠폰 적용
			if ($marketing_sale['coupon']=='Y') $arr_marketing_sale['coupon'] = 'Y';
		}

		// 입점마케팅 전달 데이터 통합 설정 - 입점마케팅 상품명,카드무이자할부 leewh 2015-01-29
		$marketing_feed = config_load('marketing_feed');
		$sql	= $this->goodsmodel->get_goods_all_partner('', $view_type, true);
		$result	= mysqli_query($this->db->conn_id, $sql);

		// 추가배송비 추출
		$arrBasicPolicy	= config_load('shippingdelivery');
		$addBasicCost	= $arrBasicPolicy['addDeliveryCost'];
		rsort($addBasicCost);
		if($addBasicCost[0])
			$addCost = "도서산간 최대 ".$addBasicCost[0]."원 추가";

		# 헤더출력
		if($type == 'file') {
			fwrite($fp, implode("\t", array_keys($this->format)));
		} else {
			echo implode("\t", array_keys($this->format));
		}

		//----> sale library 적용
		$cfg_reserve			= ($this->reserves) ? $this->reserves : config_load('reserve');
		$param['cal_type']		= 'list';
		$param['reserve_cfg']	= $cfg_reserve;
		$this->sale->set_init($param);
		$this->sale->preload_set_config('list');
		//<---- sale library 적용

		while($data_goods = mysqli_fetch_array($result)) {
			# reset
			$category_names = $buff = array();

			if ($data_goods['feed_goods_use']=='Y' && !empty($data_goods['feed_goods_name'])
				|| $data_goods['feed_goods_use']=='N' && !empty($marketing_feed['goods_name'])) {

				// 상품 검색어 자동
				$data_goods['keyword'] = $data_goods['openmarket_keyword'];

				## 상품명 치환코드
				$replaceArr = array();
				$replaceArr['{product_name}'] = strip_tags($data_goods['goods_name']);
				$replaceArr['{product_category}'] = strip_tags($data_goods['category_title']);
				$replaceArr['{product_brand}'] = strip_tags($data_goods['brand_title']);
				$replaceArr['{product_tag}'] = strip_tags($data_goods['keyword']);

				if ($data_goods['feed_goods_use']=='Y') {
					$data_goods['goods_name'] = $this->get_replace_goods_name($data_goods['feed_goods_name'],$replaceArr);
				} else if($data_goods['feed_goods_use']=='N') {
					$data_goods['goods_name'] = $this->get_replace_goods_name($marketing_feed['goods_name'],$replaceArr);
				}
			} else {
				$data_goods['goods_name']	= strip_tags($data_goods['goods_name']);
			}

			if($marketing_feed['brand_kind'] == 'brand') {
				$data_goods['brand']	= $data_goods['brand_title'];
			}

			$data_goods['manufacture'] = $data_goods['manufacture'];
			$data_goods['orgin'] = $data_goods['orgin'];
			$data_goods['feed_evt_text'] = $data_goods['feed_evt_text'];

			$data_goods['goods_url'] = 'http://'.$domain."/goods/view?no=".$data_goods['goods_seq'].'&market=naver';
			$data_goods['goods_mobile_url'] = 'http://m.'.str_replace("www.","",$domain)."/goods/view?no=".$data_goods['goods_seq'].'&market=naver';
			$mourl = str_replace('www.','',$data_goods['goods_url']);
			$data_goods['mourl'] = str_replace('http://','http://m.',$mourl);

			if(preg_match('/http/', $data_goods['image'])){
				$data_goods['image_url'] = $data_goods['image'];
			}else{
				$data_goods['image_url'] = 'http://' . $domain . $data_goods['image'];
			}

			// 카테고리
			$arr_category_code = array();
			$arr_category_code = $this->categorymodel->split_category($data_goods['category_code']);
			for($i=0;$i<4;$i++) {
				$data_goods['arr_category_code'][$i] = "";
				$data_goods['arr_category'][$i] = "";
				if( $arr_category_code[$i] ){

					$data_goods['arr_category_code'][$i] = $arr_category_code[$i];
					$data_goods['arr_category'][$i] =  ($this->categorymodel->one_category_name($arr_category_code[$i]));

					$category_names['category_name'.($i+1)] = $data_goods['arr_category'][$i];
				}
			}

			// 2014-01-16 이벤트 문구 미노출 추가
			if($data_goods['feed_evt_sdate'] == '0000-00-00') $data_goods['feed_evt_sdate'] = '';
			if($data_goods['feed_evt_edate'] == '0000-00-00') $data_goods['feed_evt_edate'] = '';
			$data_goods['event']	= $data_goods['feed_evt_text'];
			if( time() < strtotime($data_goods['feed_evt_sdate'].' 00:00:00') || strtotime($data_goods['feed_evt_edate'].' 23:59:59') < time() ){
				$data_goods['event']	= '';
			}

			// 상품가격 추가할인 설정
			if ($arr_marketing_sale) $data_goods['marketing_sale'] = $arr_marketing_sale;

			// 할인가 적용
			$data_goods['price'] = $this->apply_sale($data_goods);

			// EP 배송비 추출 :: 2017-02-24 lwh
			if($data_goods['goods_kind'] == "coupon"){
				$data_goods['feed_info']['std_txt'] = "0";
			}else{
				$data_goods['category_code'] = $data_goods['arr_category_code'];

				unset($feed_data);
				$feed_data['feed_pay_type']		= $data_goods['feed_pay_type'];
				$feed_data['feed_std_fixed']	= $data_goods['feed_std_fixed'];
				$feed_data['feed_add_txt']		= $data_goods['feed_add_txt'];
				$data_goods['feed_info'] = $this->shippingmodel->get_ep_data($data_goods['feed_ship_type'], $data_goods['shipping_group_seq'], $feed_data, $data_goods);

				//EP 배송료는 무조건 정수(범위: -1 ~ 1000000) 처리
				$data_goods['feed_info']['std_txt'] = (int)str_replace(',','',$data_goods['feed_info']['std']);
			}

			// 무이자
			if (!empty($marketing_feed['cfg_card_free'])) {
				$noint = trim($marketing_feed['cfg_card_free']);
			} else {
				$noint = trim($this->noint);
			}

			// 렌탈상품
			if($data_goods['product_flag'] == '렌탈' && $data_goods['rental_period']) {
				$data_goods['rental_info'] = ($data_goods['price'] * $data_goods['rental_period']).'^'.$data_goods['rental_period'];
			}

			// 모바일 할인가 계산
			$systemmobiles = $this->configsalemodel->get_mobile_sale_for_goods($data_goods['price']);
			$mobile_price = 0;
			if($systemmobiles['sale_price'] && $data_goods['price']){
				$mobile_sale = $systemmobiles['sale_price'] * $data_goods['price'] / 100;
				$mobile_price = $data_goods['price'] - $mobile_sale;
				$mobile_price = $this->sale->cut_sale_price($mobile_price);
			}

			// 판매상태 체크(I = 신규상품, U = 업데이트, D = 품절)
			$type_class = 'U';

			if($data_goods['provider_status'] == '1'
				&& $data_goods['goods_status'] == 'runout'
				&& $data_goods['regist_date'] == $data_goods['update_date']) { # 추가
				$type_class = 'I';
			} else if(in_array($data_goods['goods_status'], array('runout', 'unsold', 'purchasing'))) { # 품절
				$type_class = 'D';
			}

			// 네이버페이 후기 제외
			if(!$this->goodsreview) $this->load->model('goodsreview');
			$sc['whereis'] = " and goods_seq = '".$data_goods['goods_seq']."' and ifnull(npay_reviewid,'')!='' ";
			$sc['select'] = " count(gid) as cnt ";
			$npay_review = $this->goodsreview->get_data($sc);
			$review_count = $data_goods['review_count'] - $npay_review['cnt'];

			$row = array(
				'id'				=> $data_goods['goods_seq'],
				'title'				=> $data_goods['goods_name'],
				'price_pc'			=> $data_goods['price'],
				'link'				=> $data_goods['goods_url'],
				'image_link'		=> $data_goods['image_url'],
				'brand'				=> $data_goods['brand'],
				'maker'				=> $data_goods['manufacture'],
				'origin'			=> $data_goods['orgin'],
				'event_words'		=> $data_goods['event'],
				'point'				=> $data_goods['reserve'],
				'review_count'		=> $review_count,
				'condition'			=> $data_goods['feed_condition'],
				'class'				=> $type_class,
				'update_time'		=> $data_goods['update_date']
			);

			$row = array_merge($row, $category_names);

			if($service_code!='P_FAMM' && $config_system['subDomain']) {
				$row['mobile_link'] = $data_goods['goods_mobile_url'];
			}			

			# EP 배송비 추가 :: 2017-02-24 lwh
			$row['shipping']			= $data_goods['feed_info']['std_txt'];
			if($data_goods['feed_info']['add_txt']){
				$row['delivery_grade']	= 'Y';
				$row['delivery_detail']	= $data_goods['feed_info']['add_txt'];
			}

			# naver EP 3.0 추가 필드 구성 :: 2018-08-07 lwh
			if($data_goods['compound_state'])			$row[$data_goods['compound_state']]	= 'Y';
			if($data_goods['product_flag'])				$row['product_flag']				= $data_goods['product_flag'];
			if($data_goods['rental_info'])				$row['rental_info']					= $data_goods['rental_info'];
			if($data_goods['installation_costs'])		$row['installation_costs']			= $data_goods['installation_costs'];

			if($mobile_price)	$row['price_mobile']			= $mobile_price;
			if($noint) 			$row['interest_free_event']		= $noint;

			# 쿠폰 할인가			
			if( $data_goods['coupon_won'] ){				
				$row['coupon']		= iconv('EUC-KR', 'UTF-8', $data_goods['coupon_won']);
			}

			# 모델명
			if($data_goods['model_number']) $row['model_number'] = $data_goods['model_number'];

			# 최소 구매수량
			if($data_goods['min_purchase_limit'] == 'limit') {
				$row['minimum_purchase_quantity'] = $data_goods['min_purchase_ea'];
			}

			// 해외구매대행여부 (일반상품시 필드자체X) @2017-04-17
			if($data_goods['option_international_shipping_status'] == 'y') {
				$row['import_flag']	= 'Y';
			}

			// 성인상품여부 (일반상품시 필드자체X) @2017-04-17
			if($data_goods['adult_goods'] == 'Y') {
				$row['adult']	= 'Y';
			}

			// 검색태그 @2017-04-24
			if($data_goods['openmarket_keyword']) {
				$row['search_tag']	= str_replace(",","|",$data_goods['openmarket_keyword']);
			}

			# tsv format
			$row = $this->naver_valid($row);			

			if(gettype($row) != 'string') {
				foreach($this->format as $fk=>$fv) {
					$buff[$fk] = array_key_exists($fk, $row) ? $row[$fk] : '';
				}
			}

			if(sizeof($buff)) {
				if($type == 'file') {
					fwrite($fp, "\r\n" . implode("\t", $buff));
				} else {
					echo "\r\n" . implode("\t", $buff);
				}
			}

			$this->db->queries = array();
			$this->db->query_times = array();
		}

		$now = date('Y-m-d H:i:s');
		if($type == 'file') {
			fclose($fp);

			// 파일 생성시간 및 파일 사이즈 저장
			$filesize = filesize($file_path) / 1024;
			$filesize = number_format($filesize,1);
			config_save('partner',array('naver_third_file_time'=>$now, 'naver_third_file_size'=>$filesize));
		}

		// 읽어간 시간 저장
		config_save('partner',array('naver_third_update'=>$now));
	}

	### 크론용 다음 상품평 파일 생성 :: 2015-12-02 lwh
	public function cron_reviewFile($mode='all',$type='file'){

		$last_update_date = '';

		if($mode == 'summary'){
			$tmp = config_load('partner','daum_review_update');
			if($tmp['daum_review_update']) $last_update_date = $tmp['daum_review_update'];
		}

		if($type == 'file'){
			$file_path	= ROOTPATH."/ep/review_".$mode.".txt";

			$dir_name	= dirname($file_path);
			if( !is_dir($dir_name) )	@mkdir($dir_name);
			@chmod($dir_name,0777);
			@chmod($file_path,0777);

			$fp = fopen($file_path,"w+");
		}

		// EUC-KR 선언
		header("Content-Type: text/html; charset=EUC-KR");

		// 상품평 정보 읽어오기
		$review_sql = "
		SELECT goods_seq, seq, display, subject, contents, name, r_date, reviewcategory
		FROM fm_goods_review ";
		if($last_update_date)
			$review_sql .= "WHERE r_date > '" . $last_update_date . "'";

		$result			= mysqli_query($this->db->conn_id,$review_sql);
		$review_cnt		= mysqli_num_rows($result);
		$total_cnt		= "<<<tocnt>>>".$review_cnt;
		if($type == 'file')		fwrite($fp,$total_cnt."\r\n");
		else					echo $total_cnt."\r\n";

		while ($data_rev = mysqli_fetch_array($result)){ // 2015-12-02

			// 상품ID 예외처리
			if(strpos($data_rev['goods_seq'],',')){
				$tmp = explode(',',$data_rev['goods_seq']);
				$data_rev['goods_seq'] = $tmp[0];
			}

			// 상태값 정의
			if($data_rev['display'])	$status = 'D';
			else						$status = 'S';

			// 태그 제거 및 인코딩
			$title		= htmlspecialchars_decode(strip_tags(iconv('UTF-8', 'euc-kr', str_replace('&nbsp;',' ',$data_rev['subject']))));
			$content	= htmlspecialchars_decode(strip_tags(iconv('UTF-8', 'euc-kr', str_replace('&nbsp;',' ',$data_rev['contents']))));
			$writer		= htmlspecialchars_decode(strip_tags(iconv('UTF-8', 'euc-kr', str_replace('&nbsp;',' ',$data_rev['name']))));
			if(preg_match("/[가-힣]/", $writer))	{
				if(strlen($writer)>2)	$writer = substr($writer,0,-4).'**';
				else					$writer = substr($writer,0,-1).'*';
			}else{
				if(strlen($writer)>2)	$writer = substr($writer,0,-2).'**';
				else					$writer = substr($writer,0,-1).'*';
			}

			unset($loop);

			$loop[] = "<<<begin>>>";
			$loop[] = "<<<mapid>>>".$data_rev['goods_seq'];					// 상품번호
			$loop[] = "<<<reviewid>>>".$data_rev['seq'];					// 리뷰아이디
			$loop[] = "<<<status>>>".$status;								// 상태
			$loop[] = "<<<title>>>".trim($title);							// 제목
			$loop[] = "<<<content>>>".trim($content);						// 내용
			$loop[] = "<<<writer>>>".$writer;								// 작성자
			$loop[] = "<<<cdate>>>".date('YmdHis',strtotime($data_rev['r_date']));		// 작성일
			$loop[] = "<<<ranking>>>".$data_rev['reviewcategory']."/5";		// 평점
			$loop[] = "<<<ftend>>>";

			if($type == 'file')
				fwrite($fp,implode("\r\n",$loop)."\r\n");
			else
				echo implode("\r\n",$loop)."\r\n";

			$this->db->queries = array();
			$this->db->query_times = array();
		}

		$now = date('Y-m-d H:i:s');
		if($type == 'file') {
			fclose($fp);

			// 파일 생성시간 및 파일 사이즈 저장
			$filesize = filesize($file_path) / 1024;
			$filesize = number_format($filesize,1);
			config_save('partner',array('daum_review_file_time'=>$now, 'daum_review_file_size'=>$filesize));
		}

		// 읽어간 시간 저장
		$now = date('Y-m-d H:i:s');
		config_save('partner',array('daum_review_update'=>$now));
	}

	/* ##### app/controllers/partner.php 에서 발췌 ##### */

	### 세일계산
	public function apply_sale(&$data_goods)
	{
		//----> sale library 적용 ( 정가기준 목록에서는 sale_price를 넘기지 않음 )
		unset($param, $sales);
		$param['option_type']		= 'option';
		$param['cal_type']			= 'list';
		$param['consumer_price']	= $data_goods['consumer_price'];
		$param['price']				= $data_goods['price'];
		$param['total_price']		= $data_goods['price'];
		$param['ea']				= 1;
		$param['goods_ea']			= 1;
		$param['category_code']		= $data_goods['r_category'];
		$param['goods_seq']			= $data_goods['goods_seq'];
		if ($data_goods['marketing_sale']) $param['marketing_sale']	 = $data_goods['marketing_sale'];
		$param['goods']				= $data_goods;
		$this->sale->set_init($param);
		$sales						= $this->sale->calculate_sale_price('list');
		$this->sale->reset_init();
		//----> sale library END

		if ($data_goods['marketing_sale'] && $sales['sale_list']['coupon'] > 0) {
			$data_goods['coupon_won'] = iconv("UTF-8","euc-kr",$sales['sale_list']['coupon'].'원');
		}		
		$data_goods['price']		= $sales['result_price'];

		return $data_goods['price'];
	}

	### 상품명 치환코드
	public function get_replace_goods_name($goods_name, $replaceArr) {
		$goods_name = strip_tags($goods_name);
		foreach ($replaceArr as $key => $val){
			$patterns[]		= "/".$key."/";
			$replacements[]	= $val;
		}
		$gname	= preg_replace($patterns, $replacements, $goods_name);
		return $gname;
	}

	# 네이버 EP 3.0 데이터 유효성 검사
	protected function naver_valid($shopData=array()) {

		if(!is_array($shopData)) {
			return 'E000';
		}

		# length 확인해서 mb_substr로 잘라내기
		# echo mb_strlen('', 'UTF-8');

		# 0원 상품 제외
		# 모든필드(설명+요약필드 제외) 탭+개행기호 제거
		# 스크립트릿 모두 제거
		foreach($this->format as $k=>$v) {
			if(gettype($shopData[$k]) == 'string') {
				# 스크립트릿 삭제
				$shopData[$k] = strip_tags($shopData[$k]);

				# 특수기호 삭제
				$shopData[$k] = trim(preg_replace("/[\r\n\t]/i", " ", $shopData[$k]));
			}

			//네이버쇼핑 이벤트 컬럼 정책에 따라 허용되지 않는 문자가 포함되어 있으면 무효 처리
			//힌글, 영문, 숫자, 허용되는 특수문자 ([] () , . - _ ! ? ' : ; ~ + * @ # $ % ^ & { } = /)를 제외한 문자 강제 치환
			if($k == 'event_words') {
				$event_pattern = '/[^가-힣0-9a-z!?@#$%^&*_=,.:;\~\'\/\-\+\(\)\[\]\{\}\s]+/iu';
				$shopData[$k] = preg_replace($event_pattern, '', $shopData[$k]);
			}
	
			if($v['require']) {
				$length = mb_strlen($shopData[$k], 'UTF-8');

				# 필수값 미입력시 제외
				if(!$length) {
					return 'E001 ('.$k.')';
				}

				# 0원 상품입력시 제외
				if(in_array($k, array('price_pc', 'price_mobile', 'normal_price'))) {
					if($shopData[$k] < 1) {
						return 'E002';
					}
				}

				if($length > $v['length']) {
					$shopData[$k] = mb_substr($shopData[$k], 0, $v['length']);
				}
			}
		}

		return $shopData;
	}

	# [판매지수 EP] 크론용 네이버 판매지수 파일 생성 :: 2018-09-19 pjw
	public function cron_naverSalesEP($type='file'){
		
		if($this->config_basic['naver_third_use'] != 'Y'){
			return false;
		}

		if($type == 'file'){
			$file_path	= ROOTPATH."/ep/naver_sales_ep.tsv";
			$dir_name	= dirname($file_path);
			if( !is_dir($dir_name) )	@mkdir($dir_name);
			@chmod($dir_name, 0777);
			@chmod($file_path, 0777);
			$fp = fopen($file_path,"w+");
		}

		// UTF-8 선언
		header("Content-Type: text/html; charset=UTF-8");

		
		// 헤더출력
		if($type == 'file') {
			fwrite($fp, implode("\t", array_keys($this->format_sale)));
		} else {
			echo implode("\t", array_keys($this->format_sale));
		}

		// 전일 주문건 통계데이터 조회
		if(!$this->statsmodel) $this->load->model('statsmodel');
		$stats_data_list = $this->statsmodel->get_ep_stats_yesterday();	
		$ep_data		 = array();
		

		// 통계 데이터를 EP 형식에 맞게끔 배열처리
		while ($stats_data = mysqli_fetch_assoc($stats_data_list)) {
			$goods_seq = $stats_data['goods_seq'];
			$ep_type = $stats_data['type'];
			$cnt = $stats_data['cnt'];
			$dt = $stats_data['stats_date'];

			if ($cnt == 0) {
				// 값이 0이면 제외함.
				continue;
			}

			$ep_data[$goods_seq][$ep_type] += $cnt;
			$ep_data[$goods_seq]['mall_id'] = $goods_seq;
			$ep_data[$goods_seq]['dt'] = $dt;
		}


		// tsv 파일 생성 및 출력
		foreach ($ep_data as $row) {
			$row = $this->naver_sales_valid($row);

			if (gettype($row) != 'string') {
				foreach ($this->format_sale as $fk => $fv) {
					$buff[$fk] = array_key_exists($fk, $row) ? $row[$fk] : '';
				}
			}

			if (sizeof($buff)) {
				if ($type == 'file') {
					fwrite($fp, "\r\n" . implode("\t", $buff));
				} else {
					echo "\r\n" . implode("\t", $buff);
				}
			}
		}

		$now = date('Y-m-d H:i:s');
		if($type == 'file') {
			fclose($fp);

			// 파일 생성시간 및 파일 사이즈 저장
			$filesize = filesize($file_path) / 1024;
			$filesize = number_format($filesize,1);
			config_save('partner',array('naver_sales_file_time'=>$now, 'naver_sales_file_size'=>$filesize));
		}

		// 읽어간 시간 저장
		config_save('partner',array('naver_sales_update'=>$now));
	}

	# [판매지수 EP] 데이터 유효성 검사 :: 2018-09-19 pjw
	protected function naver_sales_valid($shopData=array()) {

		if(!is_array($shopData)) {
			return 'E000';
		}

		# length 확인해서 mb_substr로 잘라내기
		# echo mb_strlen('', 'UTF-8');

		# 0원 상품 제외
		# 모든필드(설명+요약필드 제외) 탭+개행기호 제거
		# 스크립트릿 모두 제거
		foreach($this->format_sale as $k=>$v) {
			if(gettype($shopData[$k]) == 'string') {
				# 스크립트릿 삭제
				$shopData[$k] = strip_tags($shopData[$k]);

				# 특수기호 삭제
				$shopData[$k] = trim(preg_replace("/[\r\n\t]/i", " ", $shopData[$k]));
			}

			if($v['require']) {
				$length = mb_strlen($shopData[$k], 'UTF-8');

				# 필수값 미입력시 제외
				if(!$length) {
					return 'E001 ('.$k.')';
				}

				# 0원 상품입력시 제외
				if(in_array($k, array('price_pc', 'price_mobile', 'normal_price'))) {
					if($shopData[$k] < 1) {
						return 'E002';
					}
				}

				if($length > $v['length']) {
					$shopData[$k] = mb_substr($shopData[$k], 0, $v['length']);
				}
			}
		}

		return $shopData;
	}
	
	# 페이스북 피드 파일 생성 :: 2019.04.29 kmj
	public function cron_feedFiles($type='file', $format=NULL)
	{
		if (!$format) {
			return false;
		}
		
		if ($format == 'facebook' && $this->config_system['facebook_pixel_use'] != 'Y') {
			return false;
		}

		// 기존 google_verification_token 없고, 신규 google_feed_use Y 가 아니면 파일 미생성
		$partner_info = config_load('partner');
		if ($format == 'google' && ($this->config_system['google_feed_use'] != 'Y' && !$partner_info['google_verification_token'])) {
			return false;
		}

		// 도메인 추출
		$domain		= $this->get_domain();
		
		$selectSQL = "g.goods_seq,
					LEFT(TRIM(g.goods_name), 75) AS title,
					TRIM(g.summary) AS description,
					gi.image AS image_link,
					go.consumer_price,
					go.price,
					g.runout_policy,
					g.feed_goods_use,
					g.feed_goods_name,
					g.openmarket_keyword,
					g.shipping_group_seq,
					g.feed_pay_type,
					g.feed_std_fixed,
					g.feed_ship_type,
					g.feed_condition,
					go.option_seq,
					c.title as category_title,";
		
		switch($format){
			case "facebook":
				$exe = "tsv";
				$selectSQL .= "
					CASE WHEN g.goods_status = 'unsold' THEN 'Discontinued'
					WHEN g.goods_status = 'purchasing' THEN 'Preorder'
					WHEN g.goods_status = 'runout' THEN 'Out of stock'
					ELSE 'in stock' END AS availability";
				break;
				
			case "google":
				$exe = "txt";
				$selectSQL .= "
					CASE WHEN g.goods_status = 'unsold' THEN 'out of stock'
					WHEN g.goods_status = 'purchasing' THEN 'Preorder'
					WHEN g.goods_status = 'runout' THEN 'Out of stock'
					ELSE 'in stock' END AS availability";
				break;
		}
		
		//마지막
		if($type == 'file') {
			$file_path	= ROOTPATH."/ep/".$format.".".$exe;
			
			$dir_name	= dirname($file_path);
			if (!is_dir($dir_name)) {
				@mkdir($dir_name);
			}
			@chmod($dir_name, 0777);
			@chmod($file_path, 0777);
			
			$fp = fopen($file_path, "w+");
			fwrite($fp, implode("\t", $this->{$format}));
		}
		
		//$last_update_date = '';
		header("Content-Type: text/html; charset=EUC-KR"); // EUC-KR 선언
		
		$queryDB = mysqli_query($this->db->conn_id, "seLECT
				{$selectSQL}
			FROM
				fm_goods g
					INNER JOIN fm_goods_image gi ON gi.goods_seq = g.goods_seq AND gi.cut_number = '1' AND gi.image_type = 'large'
					INNER JOIN fm_category_link cl ON cl.goods_seq = g.goods_seq AND cl.link = '1'
					INNER JOIN fm_category c ON c.category_code = cl.category_code
						AND (c.catalog_allow = 'show'
						OR c.catalog_allow = 'period' AND c.catalog_allow_sdate >= CURDATE() AND c.catalog_allow_edate <= CURDATE())
					INNER JOIN fm_goods_option go ON go.goods_seq = g.goods_seq AND default_option = 'y'
			WHERE
				g.goods_kind = 'goods'
				AND g.goods_type = 'goods'
				AND g.goods_status = 'normal'
				AND g.adult_goods = 'N'
				AND g.feed_status != 'N'
				AND (g.string_price_use != '1' OR g.string_price_use != 1)
				AND (g.provider_status = '1' OR g.provider_status = 1)
				AND (g.goods_view = 'look' OR
					(g.display_terms = 'AUTO' AND g.display_terms_begin <= CURDATE() and g.display_terms_end >= CURDATE()))");
				
				//입점사 마케팅 통합 조건
				$marketing_feed = config_load('marketing_feed');
				
				while($goods = mysqli_fetch_assoc($queryDB)) {
					$data = array();
					$goods['id'] = $goods['goods_seq'];
					
					//페이스북의 경우 대문자만 입력은 오류 발생
					if(strtoupper($goods['title']) == $goods['title']){
						$goods['title'] = strtolower($goods['title']);
					}
					
					if(strtoupper($goods['description']) == $goods['description']){
						$goods['description'] = strtolower($goods['description']);
					}
					
					//브랜드
					$brandQuery = "seLECT b.title FROM fm_brand_link bl INNER JOIN fm_brand b ON b.category_code = bl.category_code WHERE bl.goods_seq = ? AND bl.link = 1";
					$brand = $this->db->query($brandQuery, $goods['goods_seq'])->result_array();
					
					if ($brand[0]) {
						$goods['brand'] = $brand[0]['title'];
						$goods['identifier_exists'] = 'yes';
					} else {
						$goods['brand'] = "";
						$goods['identifier_exists'] = 'no';
					}
					
					//상품명 치환코드 여부
					if ($goods['feed_goods_use'] == 'Y' && !empty($goods['feed_goods_name'])
						|| $goods['feed_goods_use']=='N' && !empty($marketing_feed['goods_name'])) {
							$replaceArr = array();
							$replaceArr['{product_name}']	   = strip_tags($goods['title']);
							$replaceArr['{product_category}']   = strip_tags($goods['category_title']);
							$replaceArr['{product_brand}']	  = strip_tags($goods['brand']);
							$replaceArr['{product_tag}']		= strip_tags($goods['openmarket_keyword']);
							
							if ($goods['feed_goods_use']=='Y') {
								$goods['title'] = $this->get_replace_goods_name($goods['feed_goods_name'], $replaceArr);
							} else if($goods['feed_goods_use']=='N') {
								$goods['title'] = $this->get_replace_goods_name($marketing_feed['goods_name'], $replaceArr);
							}
						} else {
							$goods['title']	= strip_tags($goods['title']);
						}
						
						//할인가 적용
						$goods['price'] = $this->apply_sale($goods);
						if($format == 'google'){
							$goods['price'] .= ' KRW';
						}
						
						//배송비
						$shipping_cost = "0";
						if ($goods['feed_ship_type'] != "E") { //그룹배송비 설정을 따를 경우
							$shipping_ep_data = $this->shippingmodel->get_ep_data($goods['feed_ship_type'], $goods['shipping_group_seq'], array(), $goods);
							
							if ($shipping_ep_data['std'] > 0) {
								$shipping_cost = $shipping_ep_data['std'];
							} else {
								if( $format != "facebook" && $shipping_ep_data['fixed_cost']) {
									$shipping_cost = ((int) $shipping_ep_data['fixed_cost'])."";
								}
							}
						} else { //개별 설정일 경우
							if ($goods['feed_pay_type'] == 'postpay' || $goods['feed_pay_type'] == 'fixed') { //착불
								$shipping_cost = $goods['feed_std_fixed'];
							}
						}
						// 빈값이 전달되지 않도록 수정
						if(empty($shipping_cost) || $shipping_cost == "0" || $shipping_cost == "0.0"){
							if($format == "facebook"){
								$shipping_cost = "0.0";
							} else {
								$shipping_cost = "0";
							}
						}
						$goods['shipping'] = "KR:::".$shipping_cost." KRW";
						
						//재고확인
						/*
						if ($goods['runout_policy'] != 'unlimited') {
							$stockQuery = "seLECT option_seq, stock FROM fm_goods_supply WHERE option_seq = ?";
							$stock = $this->db->query($stockQuery, $goods['option_seq'])->result_array();
							if ($stock[0]['stock'] <=0) {
								continue;
							}
						}
						*/
						
						if ($goods['feed_condition'] == '리퍼') {
							$goods['condition'] = 'refurbished';
						} else if($goods['feed_condition'] == '중고' || $goods['feed_condition'] == '전시' || $goods['feed_condition'] == '스크레치' || $goods['feed_condition'] == '반품') {
							$goods['condition'] = 'used';
						} else {
							$goods['condition'] = 'new'; //임시설정
						}
						
						if(!preg_match('/http/', $goods['image_link'])){
							$goods['image_link'] = "https://".$domain.iconv('UTF-8', 'euc-kr', $goods['image_link']);
						}
						
						$goods['link'] = "https://".$domain."/goods/view?no=".$goods['goods_seq'];
						
						//for google
						$goods['adult'] = 'no';
						
						foreach ($this->{$format} as $v) {
							$data[$v] = $goods[$v];
						}
						
						if($type == 'file') {
							fwrite($fp, "\r\n" . implode(" \t", $data));
						}
				}
				
				$now = date('Y-m-d H:i:s');
				if($type == 'file') {
					fclose($fp);
					
					// 파일 생성시간 및 파일 사이즈 저장
					$filesize = filesize($file_path) / 1024;
					$filesize = number_format($filesize, 1);
					config_save('partner',array($format.'_file_time' => $now, $format.'_file_size' => $filesize));
				}
				
				// 읽어간 시간 저장
				config_save('partner',array($format.'_update'=>$now));
	}
	
	// 구글 standarad access
	public function get_goods_name($aSeqs)
	{
		$this->db->select('goods_seq, goods_name');
		$this->db->from('fm_goods');
		$this->db->or_where_in('goods_seq', $aSeqs);
		return $this->db->get();
	}
	
	// 도메인 추출
	protected function get_domain(){
		
		// 크론에서 돌아가기 때문에 도메인 정보 추출
		$config_system = $this->config_system;
		if($config_system['ssl_domain']){
			$domain = $config_system['ssl_domain'];
		} else {
			$domain = ($config_system['domain']) ? $config_system['domain'] : $config_system['subDomain'];
		}
		
		//한글 도메인일경우 Punycode 변환
		if(preg_match('/[^\x00-\x7f]/',$domain)){
			$this->load->library('punycode');
			$domain	= $this->punycode->encodeHostName($domain);
		}
		$domain = iconv('UTF-8', 'euc-kr',$domain); // 한글도메인 문제로 euc-kr로 변환
		
		return $domain;
	}
}

/* End of file partnermodel.php */
/* Location: ./app/models/partnermodel.php */