<?
class order2exportmodel extends CI_Model {
	var $provider_data = array();

	var $arr_shipping_method = array(
		'delivery'=>'택배',
		'postpaid'=>'택배착불',
		'each_delivery'=>'택배',
		'each_postpaid'=>'택배착불',
		'quick'=>'퀵서비스',
		'direct_delivery'=>'직접배송',
		'direct_store'=>'매장수령',
		'freight'=>'화물배송',
		'direct'=>'직접수령',
		'coupon'=>'티켓'
	);

	public function __construct(){
		$this->load->model('ordershippingmodel');
		$this->load->model('ordermodel');
		$this->load->model('providershipping');
		$this->load->model('providermodel');
		$this->load->model('goodsmodel');
		$this->load->model('membermodel');
		$this->load->model('exportmodel');
		$this->load->model('invoiceapimodel');
		$this->load->model('epostmodel');
		$this->load->model('openmarketmodel');
		$this->load->helper('shipping');
		$this->load->model('giftmodel');
		$this->load->model('orderpackagemodel');
		$this->load->helper('order');

		$this->courier_for_provider = array();
		$this->exist_invoice = 0;
	}

	public function get_excel($params)
	{
		$order_seq = $params['order_seq'];
		$data = $this->get_data_for_batch_export_item($params);

		if(!$data) return false;

		// 주문정보
		$result_order = $this->ordermodel->get_order($order_seq);
		if( $result_order['order_phone'] == '--' ) $result_order['order_phone'] = '';
		if( $result_order['order_cellphone'] == '--' ) $result_order['order_cellphone'] = '';
		if( $result_order['recipient_phone'] == '--' ) $result_order['recipient_phone'] = '';
		if( $result_order['recipient_cellphone'] == '--' ) $result_order['recipient_cellphone'] = '';

		//memo DB 분리되면서 수정 18.10.30 kmj
		// 쿼리 에러로 result_array 분할 :: 18-12-06 lkh
		$order_memos = array();
		$order_memos_sql = $this->db->query("SELECT 
                            CONCAT(a.regist_date, ' ', b.provider_name, ' ', a.mname, '(', a.manager_id, ') ', a.admin_memo) as admin_memo 
                        FROM 
                            fm_order_memo a 
                            INNER JOIN fm_provider b ON b.provider_seq = a.provider_seq 
                        WHERE 
                            a.order_seq = ?", $order_seq);
		
		if($order_memos_sql){
			$order_memos = $order_memos_sql->result_array();
		}
		foreach($order_memos as $memo){
			 $result_order['admin_memo'] .= $memo['admin_memo']."\n";
		}

		// 받는정보
		foreach($data as $key_shipping => $data_shipping){
			$arr_recipient_info = array();
			if( $result_order['international'] == 'international' ){
			    $arr_recipient_info[] = $result_order['international_country'];
			    $arr_recipient_info[] = $result_order['international_town_city'];
			    $arr_recipient_info[] = $result_order['international_county'];
			    $arr_recipient_info[] = $result_order['international_address'];
			} else {
			    if($result_order['recipient_address_type']=='street'){
			        $arr_recipient_info[] = $result_order['recipient_address_street'];
			        $arr_recipient_info[] = $result_order['recipient_address_detail'];
			        $arr_recipient_info[] = $result_order['recipient_user_name'];
			    }else{
			        $arr_recipient_info[] = $result_order['recipient_address'];
			        $arr_recipient_info[] = $result_order['recipient_address_detail'];
			        $arr_recipient_info[] = $result_order['recipient_user_name'];
			    }
			}
			

			if($data_shipping['shipping_method']=='coupon'){
				$arr_recipient_info = array();
				$arr_recipient_info[] = $result_order['recipient_cellphone'];
				$arr_recipient_info[] = $result_order['recipient_user_name'];
			}

			$data[$key_shipping]['recipient_info'] = implode(' ',$arr_recipient_info);
		}

		// 회원정보
		$result_member = $this->membermodel->get_member_data($result_order['member_seq'], $params['excel_spout']);

		$result['order']				= $result_order;
		$result['member']				= $result_member;
		$result['ordershipping']		= $data;

		if($params['excel_spout'] || $params['excel_upload']){
			$resArray = array();
			$filterData = array();

			//필요 없는 값 삭제
			if($params['excel_spout']){
				$this->excelmodel->get_exceldownload($params['form_seq']);
				$filter = $this->excelmodel->data_exceldownload;
				$filterData = $filter['item'];
				$filterData[] = 'shipping_seq';
				$filterData[] = 'shipping_type';
				$filterData[] = 'item_seq';
				$filterData[] = 'couriers';
				$filterData[] = 'suboptions';
				$filterData[] = 'ea';
				$filterData[] = 'packages';
				$filterData[] = 'recipient_address_type';
				$filterData[] = 'recipient_zipcode'; // 변수 누락됨으로 추가 :: 2018-09-05 lkh
				$filterData[] = 'recipient_address';
				$filterData[] = 'recipient_address_street';
				$filterData[] = 'recipient_address_detail';
				$filterData[] = 'each_msg_yn';

				$filterData[] = 'linkage_id';
				$filterData[] = 'linkage_order_id';
				$filterData[] = 'linkage_mall_order_id';
				$filterData[] = 'linkage_mall_code';
				$filterData[] = 'linkage_order_reg_date';

				$filterData[] = 'title1';
				$filterData[] = 'title2';
				$filterData[] = 'title3';
				$filterData[] = 'title4';
				$filterData[] = 'title5';
				$filterData[] = 'option1';
				$filterData[] = 'option2';
				$filterData[] = 'option3';
				$filterData[] = 'option4';
				$filterData[] = 'option5';
				$filterData[] = 'optioncode1';
				$filterData[] = 'optioncode2';
				$filterData[] = 'optioncode3';
				$filterData[] = 'optioncode4';
				$filterData[] = 'optioncode5';

				$filterData[] = 'shopping_method_msg';
				$filterData[] = 'location_position';
				$filterData[] = 'linkage_id';
				$filterData[] = 'linkage_mall_code';
				$filterData[] = 'emoney_sale_unit';
				$filterData[] = 'cash_sale_unit';
				$filterData[] = 'enuri_sale_unit';
				$filterData[] = 'emoney_sale_rest';
				$filterData[] = 'cash_sale_rest';
				$filterData[] = 'enuri_sale_rest';
				$filterData[] = 'ship_message';
				$filterData[] = 'shipping_set_name';
				
				$filterData[] = 'international';
				$filterData[] = 'international_postcode';
				$filterData[] = 'international_country';
				$filterData[] = 'international_town_city';
                $filterData[] = 'international_county';
				$filterData[] = 'international_address';
			} else if($params['excel_upload']){
				$filterData[] = 'order_seq';
				$filterData[] = 'provider_seq';
				$filterData[] = 'shipping_seq';
				$filterData[] = 'export_item_seq';
				$filterData[] = 'item_seq';
				$filterData[] = 'item_option_seq';
				$filterData[] = 'item_suboption_seq';
				$filterData[] = 'couriers';
				$filterData[] = 'goods_seq';
				$filterData[] = 'goods_name';
				$filterData[] = 'goods_kind';
				$filterData[] = 'goods_code';
				$filterData[] = 'option_seq';
				$filterData[] = 'suboptions';
				$filterData[] = 'ea';
				$filterData[] = 'request_ea';
				$filterData[] = 'refund_ea';
				$filterData[] = 'title1';
				$filterData[] = 'title2';
				$filterData[] = 'title3';
				$filterData[] = 'title4';
				$filterData[] = 'title5';
				$filterData[] = 'option1';
				$filterData[] = 'option2';
				$filterData[] = 'option3';
				$filterData[] = 'option4';
				$filterData[] = 'option5';
				$filterData[] = 'stock';
				$filterData[] = 'step85';
				$filterData[] = 'step45';
				$filterData[] = 'step55';
				$filterData[] = 'step65';
				$filterData[] = 'step75';
				$filterData[] = 'npay_product_order_id';
				$filterData[] = 'npay_pay_delivery';
				$filterData[] = 'package_yn';
				$filterData[] = 'shipping_method';
				$filterData[] = 'coupon_serial_type';
			}

			//회원 등급 변경 이력 (주문 당시 등급) 19.01.21 kmj
			$mLogQuery	= $this->db->query("seLECT gl.chg_group_seq, g.group_name
												FROM 
													fm_member_group_log gl
													INNER JOIN fm_member_group g ON gl.chg_group_seq = g.group_seq
												WHERE 
													gl.member_seq = ? 
													AND gl.regist_date <= ? 
												ORDER BY 
													gl.log_seq DESC LIMIT 1", 
							array($result_order['member_seq'], $result_order['regist_date']));
			$mLog		= $mLogQuery->result_array();

			$result['order']['group_name'] = $result_member['group_name'];
			if($mLog[0] && $mLog[0]['chg_group_seq'] != $result_member['group_seq']){
				$result['order']['group_name_order'] = $mLog[0]['group_name'];
			} else {
				$result['order']['group_name_order'] = $result_member['group_name'];
			}

			if( in_array('ea_price', $filterData) 
				&& (!in_array('ea', $filterData) || !in_array('price', $filterData)) ){
				
				if(!in_array('ea', $filterData)){
					$filterData[] = 'ea';
				}

				if(!in_array('price', $filterData)){
					$filterData[] = 'price';
				}
			}

			foreach($result['order'] as $k => $v){
				if(in_array($k, $filterData)){
					$resArray['order'][$k] = $v;
				}
			}

			foreach($result['member'] as $k => $v){
				if(in_array($k, $filterData)){
					$resArray['member'][$k] = $v;
				}
			}

			$_shipping_seq = array();
			foreach($result['ordershipping'] as $k => $v){
				foreach($v as $kk => $vv){
					if(in_array($kk, $filterData)){
						$resArray['ordershipping'][$k][$kk] = $vv;
					}

					//배송그룹 갯수 구하기
					if($kk == 'options'){
						foreach($result['ordershipping'][$k]['options'] as $_option){
							$_shipping_seq[] = $_option['shipping_seq'];
						}
					}

					if($kk == 'items' || $kk == 'options'){
						foreach($vv as $ik => $iv){
							foreach($iv as $ikk => $ivv){
								if(in_array($ikk, $filterData )){
									$resArray['ordershipping'][$k][$kk][$ik][$ikk] = $ivv;
								}

								if($ikk == 'goods_data' || $ikk == 'suboptions'){
									foreach($ivv as $ikkk => $ivvv){
										if(in_array($ikkk, $filterData )){
											$resArray['ordershipping'][$k][$kk][$ik][$ikk][$ikkk] = $ivvv;
										}

										if($ikkk == 'packages'){
											foreach($ivvv as $ikkkk => $ivvvv){
												if(in_array($ikkkk, $filterData )){
													$resArray['ordershipping'][$k][$kk][$ik][$ikk][$ikkk][$ikkkk] = $ivvvv;
												}
											}
										}
									}
								}
							}
						}
					}
				}
			}

			$_shipping_seq = array_unique($_shipping_seq);
			$resArray['ordershipping_cnt']	= count($_shipping_seq);	//배송그룹 갯수
			$result = $resArray;
		}

		// 다운로드 받는 내용 모두 htmlspecialchars 처리함 2017-10-10 
		array_walk_recursive($result, function(&$item) {
		  $item = htmlspecialchars($item);
		});

		return $result;
	}

	public function get_data_for_batch_export_order($params)
	{
		$data = $this->get_data_for_batch_export_item($params);
		return $data;
	}

	public function get_data_for_batch_export_item($params)
	{
		// $order_seq,$provider_seq,$arr_shipping_method,$arr_base_inclusion,$provider_seq_consignment,$shipping_seq
		$order_seq		= $params['order_seq'];
		$provider_seq	= $params['provider_seq'];
		$ship_set_code	= $params['ship_set_code'];
		$warehouse		= $params['warehouse'];
		$shipping_seq	= $params['shipping_seq'];

		if	(!$this->scm_cfg)	$this->scm_cfg	= config_load('scm');
		if	($this->scm_cfg['use'] == 'Y'){
			$this->load->model('scmmodel');
		}

		$this->load->model('giftmodel');

		$npay_use = npay_useck();	//npay2.0 사용여부

		// 배송 그룹 정보
		$result_ordershipping = $this->ordershippingmodel->get_ordershipping_for_order($params);

		foreach($result_ordershipping as $key_shipping => $data_shipping)
		{
			$rownum = 0;
			$result_ordershipping[$key_shipping]['tot_request_ea'] = 0;
			$result_ordershipping[$key_shipping]['shopping_method_msg'] = $this->arr_shipping_method[$data_shipping['shipping_method']];
			// 배송사 코드
			if(! $this->courier_for_provider[$data_shipping['provider_seq']] ){
				$this->courier_for_provider[$data_shipping['provider_seq']] = $this->providershipping->get_provider_courier($data_shipping['provider_seq']);
			}
			$result_ordershipping[$key_shipping]['couriers'] = $this->courier_for_provider[$data_shipping['provider_seq']];

			if(!$this->provider_data[$data_shipping['provider_seq']]){
				$this->provider_data[$data_shipping['provider_seq']] = $this->providermodel->get_provider_one($data_shipping['provider_seq']);
			}
			$result_ordershipping[$key_shipping]['shipping_provider']	= $this->provider_data[$data_shipping['provider_seq']]['provider_name'];

			// 첫번째 택배사명
			if( in_array($data_shipping['shipping_method'],array('delivery','postpaid','each_delivery','postpaid')) ){
				$courier_keys = array_keys($this->courier_for_provider[$data_shipping['provider_seq']]);
			}

			$where = '';
			if($data_shipping['coupon_option_seq']){
				$where = array("item_seq in (select item_seq from fm_order_item_option where item_option_seq = '".$data_shipping['coupon_option_seq']."')");
			}
			$result_item = $this->ordermodel->get_item_for_shipping($data_shipping['shipping_seq'],$where);

			// 상품정보
			$goods_view = true;
			foreach($result_item as $key_item => $data_item){
				## 상품명에 태그 사용 시 오류 발생 -> 태그 삭제 :: 2017-09-07 lwh
				$result_item[$key_item]['goods_name'] = strip_tags($data_item['goods_name']);

				## 사은품 2015-05-14 pjm
				$result_item[$key_item]['gift_title'] = "";
				if($data_item['goods_type'] == "gift"){
					$giftlog = $this->giftmodel->get_gift_title($order_seq,$data_item['item_seq']);
					$result_item[$key_item]['gift_title'] = $giftlog['gift_title'];
				}

				$result_goods = $this->goodsmodel->get_goods_simple($data_item['goods_seq']);

				/* 입점사 본사배송일 경우 위탁배송 표시 leewh 2015-03-04 */
				if ($data_shipping['provider_seq']==1 && $result_goods['provider_seq'] != 1) {
					$goods_provider_info = $this->providermodel->get_provider_one($result_goods['provider_seq']);
					$result_goods['provider_name'] = $goods_provider_info['provider_name'];
				}

				//shipping 테이블 상품코드 정보 누락으로 추가 18.11.21 kmj
				if(!$data_shipping['goods_code']){
					$result_item[$key_item]['goods_code'] = $result_goods['goods_code'];
				}

				$result_item[$key_item]['goods_data'] = $result_goods;
				if( ! $item_provider[$data_item['provider_seq']] ) $item_provider[$data_item['provider_seq']] = $this->providermodel->get_provider_one($data_item['provider_seq']);
				$result_item[$key_item]['provider_name'] = $item_provider[$data_item['provider_seq']]['provider_name'];

			}

			$where = '';
			if($data_shipping['coupon_option_seq'])
				$where = array("item_option_seq = '".$data_shipping['coupon_option_seq']."'");

			$result_option =  $this->ordermodel->get_option_for_shipping($data_shipping['shipping_seq'],$where);
			foreach($result_option as $k => $data_option)
			{
				$goods_seq = $result_item[$data_option['item_seq']]['goods_seq'];
				$result_option[$k]['goods_coupon_sale'] = $data_option['coupon_sale']; //DB명과 변수명이 달라서 엑셀노출불가 오류 해결 18.09.06 kmj
				if	($this->scm_cfg['use'] == 'Y' && $result_item[$data_option['item_seq']]['provider_seq'] == '1'){
					unset($sc);
					if	($data_option['option1'])	$sc['option1']	= $data_option['option1'];
					if	($data_option['option2'])	$sc['option2']	= $data_option['option2'];
					if	($data_option['option3'])	$sc['option3']	= $data_option['option3'];
					if	($data_option['option4'])	$sc['option4']	= $data_option['option4'];
					if	($data_option['option5'])	$sc['option5']	= $data_option['option5'];
					$optioninfo		= $this->goodsmodel->get_goods_option($goods_seq, $sc);
					if	($optioninfo[0]['option_seq'] > 0){
						$result_option[$k]['option_seq']	= $optioninfo[0]['option_seq'];
						$option_seq = $optioninfo[0]['option_seq'];
						$sc['option_seq'] = $option_seq;
						$sc['goods_seq'] = $goods_seq;
						list($data_defaultinfo) = $this->scmmodel->get_order_defaultinfo($sc);
						$result_option[$k]['supply_goods_name'] = $data_defaultinfo['supply_goods_name'];
					}
				}

				$data_option['bar_goods_code'] = $data_option['goods_code'];
				if ( ! preg_match("/^[a-z0-9:_\/-]+$/i", $data_option['goods_code']))
				{
					$data_option['bar_goods_code'] = "";
				}
				$result_option[$k]['bar_goods_code'] = $data_option['bar_goods_code'];

				if( in_array($goods_seq,$arr_except_goods_seq) ) {
					unset($result_option[$k]);
					continue;
				}

				// 출고상품번호
				$result_option[$k]['export_item_seq'] = 'OPT-'.$data_shipping['shipping_seq'].'-'.$data_option['item_option_seq'];
				if($data_shipping['coupon_option_seq']){
					$result_option[$k]['export_item_seq']  = 'COU-'.$data_shipping['shipping_seq'].'-'.$data_option['item_option_seq'];
				}

				// 보낸수량
				$result_option[$k]['export_ea'] = $data_option['step45']+$data_option['step55']+$data_option['step65']+$data_option['step75'];

				// 보낼수량
				$result_option[$k]['request_ea'] = $data_option['ea'] - $data_option['step85'] - $data_option['step45'] - $data_option['step55']- $data_option['step65']- $data_option['step75'];

				// 지급마일리지
				$result_option[$k]['reserve'] = $data_option['reserve'] * $data_option['ea'];

				//네이버페이 판매자센터에서 출고 진행중인 건은 보낼수량 0 으로 처리
				if($npay_use && $data_option['npay_pay_delivery'] == 'y'){
					$result_option[$k]['request_ea'] = 0;
				}

				// 재고 가져오기
				$stock = '';
				if	($this->scm_cfg['use'] == 'Y' && $result_item[$data_option['item_seq']]['provider_seq'] == '1'){
					unset($sc);
					if	($optioninfo[0]['option_seq'] > 0){
						$sc['option_seq']	= $option_seq;
						$sc['goods_seq']	= $goods_seq;
						$sc['option_type']	= 'option';
						$sc['get_type']		= 'wh';
						$sc['wh_seq']			= $warehouse;
						list($wh_data)	= $this->scmmodel->get_location_stock($sc);
						$result_option[$k]['location_position']	= $wh_data['location_position'];
						$stock						= ($wh_data['ea']) ? $wh_data['ea'] : 0;
						$result_option[$k]['stock']	= $stock;
					}
				}else{
					$stock = $this->goodsmodel->get_goods_option_stock($goods_seq,$data_option['option1'],$data_option['option2'],$data_option['option3'],$data_option['option4'],$data_option['option5']);
					$result_option[$k]['stock'] = $stock;
				}
				if(trim($stock) === '') $result_option[$k]['stock'] = "미매칭";

				// 그룹의 합계 및 기본 정보
				$result_ordershipping[$key_shipping]['tot_stock'] += (int) $stock;
				$result_ordershipping[$key_shipping]['tot_request_ea'] += $result_option[$k]['request_ea'];
				$result_ordershipping[$key_shipping]['tot_export_ea'] += $result_option[$k]['export_ea'];
				$result_ordershipping[$key_shipping]['tot_step85'] += $data_option['step85'];
				$result_ordershipping[$key_shipping]['tot_ea'] += $data_option['ea'];

				if( !$result_ordershipping[$key_shipping]['tot_goods_name'] ){
					$result_ordershipping[$key_shipping]['tot_image'] = $result_item[$data_option['item_seq']]['image'];
					$result_ordershipping[$key_shipping]['tot_goods_name'] = $result_item[$data_option['item_seq']]['goods_name'];
					$result_ordershipping[$key_shipping]['tot_goods_kind'] = $result_item[$data_option['item_seq']]['goods_kind'];
				}

				// 추가입력사항
				$result_input = $this->ordermodel->get_input_for_option($data_option['item_seq'], $data_option['item_option_seq']);

				$arr_input = array();
				if($result_input) foreach($result_input as $data_input){
					$arr_input[] = $data_input['value'];
				}
				$result_option[$k]['subinputoption'] = implode(',',$arr_input);

				$result_option[$k]['subinputs'] = $result_input;

				if($data_option['package_yn'] == 'y'){
					$result_option[$k]['packages'] = $this->orderpackagemodel->get_option($data_option['item_option_seq']);
					$result_option[$k]['package_msg'] = "<span style='color:red'>".count($result_option[$k]['packages'])."종 패키지</span>";
				}

				$arr_suboptions = $this->ordermodel->get_suboption_for_option($data_option['item_seq'], $data_option['item_option_seq']);
				$result_option[$k]['suboptions'] = $arr_suboptions;

				if($result_option[$k]['suboptions']) foreach($result_option[$k]['suboptions'] as $key_suboption => $data_suboption){
					if	($this->scm_cfg['use'] == 'Y' && $result_item[$data_option['item_seq']]['provider_seq'] == '1'){
						unset($sc);
						if	($data_suboption['title'])
							$sc['suboption_title']	= $data_suboption['title'];
						if	($data_suboption['suboption'])
							$sc['suboption']		= $data_suboption['suboption'];
						$suboptioninfo		= $this->goodsmodel->get_goods_suboption($goods_seq, $sc);
						if	($suboptioninfo[0][0]['suboption_seq'] > 0){
							$result_option[$k]['suboptions'][$key_suboption]['suboption_seq']	= $suboptioninfo[0][0]['suboption_seq'];
						}
					}

					$data_suboption['bar_goods_code'] = $data_suboption['goods_code'];
					if ( ! preg_match("/^[a-z0-9:_\/-]+$/i", $data_suboption['goods_code']))
					{
						$data_suboption['bar_goods_code'] = "";
					}
					$result_option[$k]['suboptions'][$key_suboption]['bar_goods_code'] = $data_suboption['bar_goods_code'];

					// 출고상품번호
					$result_option[$k]['suboptions'][$key_suboption]['export_item_seq'] = 'SUB-'.$data_shipping['shipping_seq'].'-'.$data_suboption['item_suboption_seq'];

					// 보낸수량
					$result_option[$k]['suboptions'][$key_suboption]['export_ea'] = $data_suboption['step45']+$data_suboption['step55']+$data_suboption['step65']+$data_suboption['step75'];

					// 보낼수량
					$result_option[$k]['suboptions'][$key_suboption]['request_ea'] = $data_suboption['ea'] - $data_suboption['step85'] - $data_suboption['step45'] - $data_suboption['step55']- $data_suboption['step65']- $data_suboption['step75'];

					// 지급마일리지
					$result_option[$k]['suboptions'][$key_suboption]['reserve'] = $data_suboption['reserve'] * $data_suboption['ea'];

					//네이버페이 판매자센터에서 출고 진행중인 건은 보낼수량 0 으로 처리
					if($npay_use && $data_suboption['npay_pay_delivery'] == 'y'){
						$result_option[$k]['suboptions'][$key_suboption]['request_ea'] = 0;
					}

					// 재고 가져오기
					$stock = $this->goodsmodel->get_goods_suboption_stock($goods_seq,$data_suboption['title'],$data_suboption['suboption']);
					$result_option[$k]['suboptions'][$key_suboption]['stock'] = $stock;
					if(trim($stock) === '') $result_option[$k]['suboptions'][$key_suboption]['stock'] = "미매칭";

					if($data_suboption['package_yn'] == 'y'){
						$result_option[$k]['suboptions'][$key_suboption]['packages'] = $this->orderpackagemodel->get_suboption($data_suboption['item_suboption_seq']);
						$result_option[$k]['suboptions'][$key_suboption]['package_msg'] = "<span style='color:red'>연결상품</span>";
					}

					// 그룹의 합계 및 기본 정보
					$result_ordershipping[$key_shipping]['tot_stock'] += (int) $stock;
					$result_ordershipping[$key_shipping]['tot_request_ea'] += $result_option[$k]['suboptions'][$key_suboption]['request_ea'];
					$result_ordershipping[$key_shipping]['tot_export_ea'] += $result_option[$k]['suboptions'][$key_suboption]['export_ea'];
					$result_ordershipping[$key_shipping]['tot_step85'] += $data_suboption['step85'];
					$result_ordershipping[$key_shipping]['tot_ea'] += $data_suboption['ea'];
				}

				$rownum += count($arr_suboptions) + 1; // 로우갯수
			}

			$result_ordershipping[$key_shipping]['rowspan'] = $rownum;
			$result_ordershipping[$key_shipping]['options'] = $result_option;
			$result_ordershipping[$key_shipping]['items'] = $result_item;
		}

		$result = array();
		foreach($result_ordershipping as $k=>$data_ordershipping){
			if( count($data_ordershipping['options']) > 0 ){
				$result[] = $data_ordershipping;
			}
		}
		return $result;
	}

	public function export_for_coupon($export,$cfg){
		$this->load->model('batchmodel'); // 후처리 작업
		foreach($export as $export_data){	
			$exportItemCount = count($export_data['items']['item_seq']);
			for($i=0;$i<$exportItemCount;$i++){
				$param_coupon['option_seq']		 = $export_data['items']['option_seq'][$i];
				$param_coupon['export_date']	 = $export_data['items']['export_date'][$i];
				if(!$param_coupon['export_date']) $param_coupon['export_date'] = $export_data['export_date'];
				$param_coupon['coupon_mail']	 = $export_data['coupon_mail'];
				$param_coupon['coupon_sms']		= $export_data['coupon_sms'];
				$param_coupon['export_item_seq'] 	= $export_data['items']['export_item_seq'][$i];
				$coupon_ea						 		= $export_data['items']['ea'][$i];

				$result = $this->exportmodel->coupon_export_for_option($param_coupon, $coupon_ea);
				if($result){
					$result_export_ea = $result[0];
					
					// $this->export_for_goods() 리턴 값 format과 동일 하게 맞춰줌, 출고결과 수량이 표시됨
					$result_export[$export_data['status']][$param_coupon['export_item_seq']]['export_code'] = $result[2];
				}

				// 주문상태별 수량 변경
				$this->ordermodel->set_step_ea(55, $result_export_ea, $param_coupon['option_seq'], 'option');

				// 주문 option 상태 변경
				$this->ordermodel->set_option_step($param_coupon['option_seq'], 'option');
			}

			// 주문상태 변경
			$this->ordermodel->set_order_step($export_data['order_seq']);

			// 2016.06.14 상태별 메세지 분기 처리
			if($export_data['status'] == '45')			$export_type = '출고준비';
			else if($export_data['status'] == '55')		$export_type = '출고완료';

			$actor = ($this->managerInfo['mname']) ? $this->managerInfo['mname'] : '시스템';
			if( defined('__SELLERADMIN__') === true ){
				$actor = $this->providerInfo['provider_name'];
			}
			$this->ordermodel->set_log($export_data['order_seq'],'process',$actor,$export_type,'관리자가 출고처리를 하였습니다.');
		}

		return $result_export;
	}

	// 실물 출고 처리
	public function export_for_goods($export,$cfg,$mode=''){

		$npay_use				= npay_useck();	//Npay2.1사용여부
		if($npay_use) $this->load->model("naverpaymodel");
		$talkbuy_use			= talkbuy_useck();
		if($talkbuy_use) $this->load->library('talkbuylibrary');
		$this->exist_invoice	= 0;
		$this->load->model('orderpackagemodel');
		$this->load->model('connectormodel');

		// 출고량 업데이트를 위한 변수선언
		$r_reservation_goods_seq = array();

		foreach($export as $key => $data_export){

			$tot_ea = 0;
			for($i=0;$i<count($data_export['items']['item_seq']);$i++){
				$tot_ea += $data_export['items']['ea'][$i];
			}
			$export[$key]['tot_ea'] = $tot_ea;

			if( $tot_ea > 0 ){
				// 롯데택배 출고자동화 송장조회
				if($data_export['delivery_company_code'] == 'auto_hlc'){

					//묶음배송의경우 1개반 받음
					if($cfg['bundle_mode'] != 'bundle' || count($result_invoice) < 1) {
						$hlc_shippings = array();
						$hlc_shippings[] = $data_export;
						$hlc_shippings['provider_seq'] = $data_export['shipping_provider_seq'];
						$result_invoice[] = $this->invoiceapimodel->new_get_invoice($hlc_shippings);
					}
				}
			}
		}

		// 롯데택배 출고자동화 송장조회
		if($result_invoice){
			$this->exist_invoice = 1;
		}

		$hlc_num			= 0;
		$scmIdx				= 0;
		$bundle_export_code	= $cfg['bundle_mode'];

		$actor = $this->managerInfo['mname'];
		if( defined('__SELLERADMIN__') === true ){
			$actor = $this->providerInfo['provider_name'];
		}

		$result			= array();
		$result_error	= array();

		foreach($export as $data_export){

			if( $data_export['tot_ea']== 0 ) continue;

			$params = $data_export;
			unset($params['items'],$params['goods_kind'],$params['shipping_seq'],$params['tot_ea']);

			// 롯데택배 출고자동화 송장저장
			if($data_export['delivery_company_code'] == 'auto_hlc'){
				$params['delivery_number'] = $result_invoice[$hlc_num]['resultDeliveryNumber'][0];

				if($cfg['bundle_mode'] != 'bundle')
					$hlc_num++;
			}

			# 출고처리 npay 송장정보 중계서버로 전송. 단, Npay에서 직접 처리된 출고건은 제외 @2016-03-08 pjm
			if($npay_use && $data_export['npay_order_id'] && in_array($data_export['status'],array("55")) && $mode != 'order_api'){
				if($data_export['orign_order_seq']){ //교환 재배송
					$data_export['npay_flag_release'] = "redelivery";
				}
				$npay_res	= $this->naverpaymodel->order_export($data_export);
				$export_cnt = $npay_res['success_cnt'];
			}else if($talkbuy_use && $data_export['talkbuy_order_id'] && in_array($data_export['status'],array("55")) && $mode != 'order_api'){
				$talkbuy_res	= $this->talkbuylibrary->order_export($data_export);
				$export_cnt = $talkbuy_res['success_cnt'];
			}else{
				$export_cnt = 1;
			}

			if($export_cnt > 0){

				unset($params['orign_order_seq'],$params['npay_flag_release']);

				// 실물 출고
				$params['wh_seq']	= $cfg['wh_seq'];
				$export_code_list	= $this->exportmodel->insert_export($params,$bundle_export_code);
				$export_code		= $export_code_list['export_code'];
				$bundle_export_code	= $export_code_list['bundle_export_code'];
				for($i=0;$i<count($data_export['items']['item_seq']);$i++){

					if( !$data_export['items']['ea'][$i] ) continue;

					## Npay 상품주문번호
					$npay_product_order_id		= $data_export['items']['npay_product_order_id'][$i];
					## Npay 출고처리 성공여부. (Npay 주문수집 출고 insert시에는 무조건 y)
					if($npay_product_order_id) {
						$npay_status = ($mode == 'order_api') ? "y" : "n";
					}

					## 카카오페이구매 상품주문번호
					$talkbuy_product_order_id	= $data_export['items']['talkbuy_product_order_id'][$i];
					if($talkbuy_product_order_id) {
						$talkbuy_status = ($mode == 'order_api') ? "y" : "n";
					}

					$export_item_exec		= true;
					$export_item_message	= '';
					# npay 처리결과
					if($npay_use && $npay_product_order_id && in_array($data_export['status'],array("55")) && $mode != 'order_api'){
						if($npay_res['export_items'][$npay_product_order_id]['result'] != "SUCCESS"){
							$export_item_exec		= false;
							$export_item_message	= $npay_res['export_items'][$npay_product_order_id]['message'];
						}else{
							$npay_status = 'y';		//Npay API 출고처리 성공여부
						}
					}
					# 카카오페이구매 처리결과
					if($talkbuy_use && $talkbuy_product_order_id && in_array($data_export['status'],array("55")) && $mode != 'order_api'){
						if($talkbuy_res['export_items'][$talkbuy_product_order_id]['result'] != "SUCCESS"){
							$export_item_exec		= false;
							$export_item_message	= $talkbuy_res['export_items'][$talkbuy_product_order_id]['message'];
						}else{
							$talkbuy_status = 'y';		// 카카오페이구매 API 출고처리 성공여부
						}
					}

					if($export_item_exec){

						$data_item['item_seq']				= $data_export['items']['item_seq'][$i];
						$data_item['option_seq']			= $data_export['items']['option_seq'][$i];
						$data_item['suboption_seq']			= $data_export['items']['suboption_seq'][$i];
						$data_item['ea']					= $data_export['items']['ea'][$i];
						$data_item['scm_supply_price']		= $data_export['items']['supplyprice'][$i];
						$data_item['reserve_ea']			= $data_item['ea'];	//마일리지&포인트 지급예정수량 2015-03-26 pjm
						$data_item['npay_product_order_id'] = $npay_product_order_id;
						$data_item['npay_status']			= $npay_status;
						$data_item['talkbuy_product_order_id'] = $talkbuy_product_order_id;
						$data_item['talkbuy_status']			= $talkbuy_status;
						$package_yn							= $data_export['items']['package_yn'][$i];

						// 물류관리 데이터 추가
						if( $package_yn != 'y'){
							$scmParams[$scmIdx]['export_code']	= $export_code;
							$scmParams[$scmIdx]['goodscode']	= $data_export['items']['goodscode'][$i];
							$scmParams[$scmIdx]['goodsname']	= $data_export['items']['goodsname'][$i];
							$scmParams[$scmIdx]['optionname']	= $data_export['items']['optionname'][$i];
							$scmParams[$scmIdx]['optioninfo']	= $data_export['items']['optioninfo'][$i];
							$scmParams[$scmIdx]['ea']			= $data_export['items']['ea'][$i];
							$scmParams[$scmIdx]['auto_wh']		= $data_export['items']['auto_wh'][$i];
							$scmParams[$scmIdx]['supplyprice']	= $data_export['items']['supplyprice'][$i];
							$scmIdx++;
						}else{
							if( $data_item['suboption_seq'] ){
								$opt_seq		= $data_item['suboption_seq'];
								$action_packges = 'get_suboption';
							}else{
								$opt_seq = $data_item['option_seq'];
								$action_packges = 'get_option';
							}
							$packages	= $this->orderpackagemodel->{$action_packges}($opt_seq);
							$stock		= '';
							foreach($packages as $key=>$data_package){
								// 물류관리 창고 정보 추출
								if	($this->scm_cfg['use'] == 'Y'){
									if	($data_package['option_seq'] > 0){
										$optionStr		= $data_package['goods_seq'] . 'option' . $data_package['option_seq'];
										if ($data_package['scm_auto_warehousing']){
											$whinfo['optioninfo']	= $optionStr;
											$whinfo['supply_price']	= 'X';
										}else{
											$whinfo			= $this->scmmodel->get_warehouse_stock($cfg['wh_seq'], 'optioninfo', '', array($optionStr));
											$whinfo = $whinfo[$optionStr];
										}
										$tmp_optionname = array();
										for($p_num=1;$p_num<=5;$p_num++){
											$optionname_str = 'option'.$p_num;
											if( $data_package[$optionname_str] ){
												$tmp_optionname[] = $data_package[$optionname_str];
											}
										}
										$optionname = implode('',$tmp_optionname);
										$scmParams[$scmIdx]['export_code']	= $export_code;
										$scmParams[$scmIdx]['goodscode']	= $data_package['goods_code'];
										$scmParams[$scmIdx]['goodsname']	= $data_package['goods_name'];
										$scmParams[$scmIdx]['optionname']	= $optionname;
										$scmParams[$scmIdx]['optioninfo']	= $whinfo['optioninfo'];
										$scmParams[$scmIdx]['ea']			= $data_export['items']['ea'][$i] * $data_package['unit_ea'];
										$scmParams[$scmIdx]['supplyprice']	= $whinfo['supply_price'];
										$scmParams[$scmIdx]['auto_wh']		= $data_package['scm_auto_warehousing'];

										$scmIdx++;
									}
								}
							}
						}

						## 마일리지&포인트 지급예정수량 2015-03-26 pjm
						$data_item['reserve_ea']	= $data_item['ea'];
						$this->exportmodel->insert_export_item($data_item,$export_code,$bundle_export_code);

						$export_item_seq = $data_export['items']['export_item_seq'][$i];

						if(!$data_item['suboption_seq']){
							// 주문상태별 수량 변경
							$this->ordermodel->set_step_ea($data_export['status'],$data_item['ea'],$data_item['option_seq'],'option');

							// 주문 option 상태 변경
							$this->ordermodel->set_option_step($data_item['option_seq'],'option');
						}else{
							// 주문상태별 수량 변경
							$this->ordermodel->set_step_ea($data_export['status'],$data_item['ea'],$data_item['suboption_seq'],'suboption');

							// 주문 option 상태 변경
							$this->ordermodel->set_option_step($data_item['suboption_seq'],'suboption');
						}
					}
					$result[$data_export['status']][$export_item_seq]['export_code'] =  ($cfg['bundle_mode'] == 'bundle') ? $bundle_export_code : $export_code;
					$result[$data_export['status']][$export_item_seq]['message']	 = $export_item_message;

				}

				// 주문상태 변경
				$this->ordermodel->set_order_step($data_export['order_seq']);

				// 묶음배송 처리
				if($cfg['bundle_mode'] == 'bundle')	$this->ordermodel->set_bundle_order('set', $data_export['order_seq']);

				$actor = $this->managerInfo['mname'];
				if( defined('__SELLERADMIN__') === true ){
					$actor = $this->providerInfo['provider_name'];
				}

				$logTitle = "출고준비";
				if($data_export['status'] == "55") $logTitle = '출고완료';
				$logTitle .= '(';
				if($mode == 'order_api') $logTitle .= "API:";
				$logTitle .= ($cfg['bundle_mode'] == 'bundle') ? $bundle_export_code : $export_code;
				$logTitle .= ')';


				// npay 주문수집 -> 출고처리 일때
				if($mode == 'order_api'){
					if($talkbuy_product_order_id) {
						$log_mode	= $this->talkbuylibrary->baseLogParams["add_info"];
						$actor		= $this->talkbuylibrary->baseLogParams["actor"];
						$logDetail	= "[".$talkbuy_product_order_id."]Kpay로부터 ".$logTitle." 되었습니다.";
					} else {
						$log_mode	= "npay";
						$actor		= "Npay";
						$logDetail	= "[".$npay_product_order_id."]Npay로부터 ".$logTitle." 되었습니다.";
					}
				}else{
					$log_mode	= "";
					$logDetail	= "관리자가 출고처리를 하였습니다.";
				}
				$this->ordermodel->set_log($data_export['order_seq'],'process',$actor,$logTitle,$logDetail,'',$export_code,$log_mode);

				if( $data_export['status'] == '55' ){
					$exportComplete	= true;
					$export_item = $this->exportmodel->get_export_item($export_code);
					foreach($export_item as $item){
						// 올인원&&미연결 창고 상태가 아닐 때만 재고 차감
						if	(!($this->scm_cfg['use'] == 'Y' && $cfg['not_connect_scm'] == 'Y')){
							if($item['opt_type'] == 'opt'){
								$providerList[$item['provider_seq']] = 1;
								$this->goodsmodel->stock_option('-',$item['ea'],$item['goods_seq'],$item['option1'],$item['option2'],$item['option3'],$item['option4'],$item['option5'],true);
							}else{
								$this->goodsmodel->stock_suboption('-',$item['ea'],$item['goods_seq'],$item['title1'],$item['option1'],true);
							}
						}

						// 출고예약량 업데이트를 위한 변수정의
						if(!in_array($item['goods_seq'],$r_reservation_goods_seq)){
							$r_reservation_goods_seq[] = $item['goods_seq'];
						}
					}

					// 우체국택배 출고자동화 목록 추출 :: 2016-04-04 lwh
					// _batch.php 에서 SMS발송 시 함께 처리 2018-09-13 pjm
					/*
					if($data_export['delivery_company_code'] == 'auto_epostnet'){

						//묶음배송의경우 1개반 받음
						if($cfg['bundle_mode'] != 'bundle' || count($epost_shippings) < 1) {
							$doExportCode	= ($cfg['bundle_mode'] == 'bundle') ? $bundle_export_code : $export_code;
							$epost_info = $this->epostmodel->get_epost_requestkey($data_export['shipping_provider_seq']);
							$epost_api['export_code']	= $doExportCode;
							$epost_api['requestkey']	= $epost_info['requestkey'];
							$export_tmp = $this->exportmodel->get_export($doExportCode);
							$epost_api['domestic_shipping_method'] = $export_tmp['domestic_shipping_method'];
							$epost_shippings[] = $epost_api;
						}
					}
					*/
				}
				

				// 오픈마켓 주문 문자 발송 중지
				$isMarketOrder		= $this->connectormodel->checkIsMarketOrder($data_export['order_seq']);

				if ($isMarketOrder == false) {
					$arr_export_code[]	= $export_code;
					$arr_order_seq[]	= $data_export['order_seq'];
				}

			}else{
				# Mpay 발송처리 모두 실패시
				foreach($data_export['items']['npay_product_order_id'] as $k=>$npay_product_order_id){

					if( !$data_export['items']['ea'][$k] ) continue;

					$export_item_seq = $data_export['items']['export_item_seq'][$k];

					$result[$data_export['status']][$export_item_seq]['export_code'] = 'ERROR';
					$result[$data_export['status']][$export_item_seq]['message']	 = $data_export['order_seq']." : (Npay:".$npay_product_order_id.")".$npay_res['export_items'][$npay_product_order_id]['message'];
				}
				# 카카오페이구매 발송처리 모두 실패시
				foreach($data_export['items']['talkbuy_product_order_id'] as $k=>$talkbuy_product_order_id){

					if( !$data_export['items']['ea'][$k] ) continue;

					$export_item_seq = $data_export['items']['export_item_seq'][$k];

					$result[$data_export['status']][$export_item_seq]['export_code'] = 'ERROR';
					$result[$data_export['status']][$export_item_seq]['message']	 = $data_export['order_seq']." : (kpay:".$talkbuy_product_order_id.")".$talkbuy_res['export_items'][$talkbuy_product_order_id]['message'];
				}				
			}
		}

		// 출고정보로 우체국택배 출고자동화 송장조회 :: 2016-04-04 lwh
		// _batch.php 에서 SMS발송 시 함께 처리 2018-09-13 pjm
		/*
		if($epost_shippings){
			// 해당 모델에서 송장번호 업데이트를 해줌.
			$this->epostmodel->get_delivery_number($epost_shippings);
		}
		*/

		// 출고예약량 / 품절 체크
		foreach($r_reservation_goods_seq as $goods_seq){
			$this->goodsmodel->runout_check($goods_seq);
			$this->goodsmodel->modify_reservation_real($goods_seq);
		}

		// 패키지 품절 체크
		foreach($r_package_goods_seq as $goods_seq){
			$this->goodsmodel->runout_check($goods_seq);
		}

		$order_count		= 0;
		$recipient_count	= 0;
		unset($params);

		$this->load->model('batchmodel');
		foreach($arr_export_code as $key_export_code => $export_code){

			$order_seq = $arr_order_seq[$key_export_code];
			$arr_params = serialize(array('order_seq'=>$order_seq,'export_code'=>$export_code));

			if( $data_export['status'] == '55' ){ // 출고완료 후 작업
				$this->batchmodel->insert('export_complete',$arr_params,'none');
			}else{ // 출고준비 후 작업
				$this->batchmodel->insert('export_ready',$arr_params,'none');
			}
		}

		// 물류관리 창고 재고 차감 ( 출고완료 시에만 )
		if	($exportComplete && $cfg['scm_use'] == 'Y' && $cfg['scm_wh'] > 0 && is_array($scmParams) && count($scmParams) > 0){
			$this->load->model('scmmodel');
			$this->scmmodel->apply_export_wh($cfg['scm_wh'], $scmParams);
		}

		unset($providerList);
		return $result;
	}

	// 출고처리
	public function goods_export($export,$cfg){

		foreach($export as $data_export){
			if( $data_export['goods_kind'] == 'goods' ){
				$export_for_goods[] = $data_export;
			}else if( $data_export['goods_kind'] == 'coupon' ) {
				$export_for_coupon[] = $data_export;
			}
		}

		if( $export_for_goods ){
			$result['goods'] = $this->export_for_goods($export_for_goods,$cfg);
		}

		if( $export_for_coupon ){
			$result['coupon'] = $this->export_for_coupon($export_for_coupon,$cfg);
		}

		return $result;
	}

	public function get_info_by_export_item_seq($export_item_seq){
		$arr = explode('-',$export_item_seq);
		return $arr;
	}

	// 출고 상품 검증
	public function check_error_export($export,$cfg,$mode='',$param_shipping_for_order='')
	{
		# Npay2.1 사용여부 확인
		$npay_use = npay_useck();
		# 카카오페이 구매 사용여부 확인
		$talkbuy_use = talkbuy_useck();

		if	($this->scm_cfg['use'] == 'Y'){
				$this->load->model('scmmodel');
		}
		$this->load->model('connectormodel');
		
		$origin_cfg_step = $cfg['step'];

		foreach($export as $export_key => $export_data){
			// 자동 출고 완료 주문의 경우 출고준비 단계를 거치지 않고 바로 출고완료로 처리된다 by hed
			if($origin_cfg_step=='45' && $export_data['check_direct_export_complete']=='1'){
				$cfg['step'] = '55';
			}else{
				$cfg['step'] = $origin_cfg_step;
			}

			$org_goods_stock_option			= $goods_stock_option;
			$export_data['export_result'] = "";

			// 주문정보가져오기
			if( $param_shipping_for_order[$export_data['order_seq']]){
				$order_data = $param_shipping_for_order[$export_data['order_seq']];
			}else{

				$order_data = $this->get_data_for_batch_export_item(array('order_seq'=>$export_data['order_seq']));
			}

			if( !$order_data[0]['order_seq'] ){
				$error_arr[] = array(
						'msg'=>'주문 미매칭',
						'order_seq'=>$export_data['order_seq']
				);
				$export_data['export_result'] = "주문 미매칭";
				continue;
			}

			// 선물하기 주문인 경우 배송지 미등록 시 출고 불가
			if( is_order_present($order_data[0]) === true && has_recipient_zipcode($order_data[0]) === false) {
				$error_arr[] = array(
					'msg'=>'배송지 미등록',
					'step' => $cfg['step'],
					'order_seq'=>$export_data['order_seq']
				);
				$export_data['export_result'] = "배송지 미등록";
				continue;
			}

			// 출고 상품 수량 체크
			$total_request_ea = 0;
			if( $export_data['OPT'] ) foreach( $export_data['OPT'] as $option_seq => $data_option ){
				foreach($order_data as $shipping_data){
					if($data_option['request_ea']=='ALL') $data_option['request_ea'] = $shipping_data['options'][$option_seq]['request_ea'];
				}


				if($data_option['request_ea']!='ERR') $total_request_ea += $data_option['request_ea'];
				if( $data_option['SUB'] ) foreach( $data_option['SUB'] as $suboption_seq => $data_suboption ){
					if($data_suboption['request_ea']!='ERR') $total_request_ea += $data_suboption['request_ea'];
				}
			}
			if( $export_data['COU'] ) foreach( $export_data['COU'] as $option_seq => $data_option ){
				foreach($order_data as $shipping_data){
					if($data_option['request_ea']=='ALL') $data_option['request_ea'] = $shipping_data['options'][$option_seq]['request_ea'];
				}
				if($data_option['request_ea']!='ERR') $total_request_ea += $data_option['request_ea'];
				if( $data_option['SUB'] ) foreach( $data_option['SUB'] as $suboption_seq => $data_suboption ){
					if($data_suboption['request_ea']!='ERR') $total_request_ea += $data_suboption['request_ea'];
				}
			}
			if( $total_request_ea <= 0 ){
				$error_arr[] = array(
					  'msg'=>'출고 안함, 출고 요청 수량이나 재고를 확인해주세요.',
					  'order_seq'=>$export_data['order_seq'],
					  'shipping_seq'=>$order_data[0]['shipping_seq'],
					  'step'=>$cfg['step']
				);
				$export_data['export_result'] = "출고 안함";
				continue;
			}
			if( $export_data['provider_data']['provider_seq'] && $export_data['check_delivery_code'] != 'Y' ){
				if($cfg['step'] > 45 && $export_data['export_shipping_method']!= "direct_delivery" && preg_match( '/delivery/',$export_data['export_shipping_method']) && (!$export_data['delivery_company'] || !$export_data['delivery_number'])){
					$error_arr[] = array(
							'msg' => '['.$export_data['order_seq']." ".$goods_name.'] - 송장번호 누락(택배사 및 송장번호 필수)',
							'order_seq' => $export_data['order_seq'],
							'shipping_seq'=>$shipping_seq,
							'export_item_seq'=>$data_option['export_item_seq'],
							'step'=>$cfg['step']
					);
					$export_data['export_result'] = "택배사 및 송장번호 입력 필수";
					continue;
				}
			}
			if( $export_data['OPT'] ) foreach( $export_data['OPT'] as $option_seq => $data_option ){
				$shipping_exists = false;
				foreach($order_data as $shipping_data){

					list($opttype,$shipping_seq,$opt_seq) = $this->get_info_by_export_item_seq($data_option['export_item_seq']);

					if( $shipping_seq != $shipping_data['shipping_seq'] ) continue;

					if( $shipping_data['tot_request_ea'] > 0 ) {
						$shipping_exists = true;
					}

					// 출고 수량이 0이라면 검증 제외
					if( $shipping_data['options'][$option_seq]['request_ea'] <= 0 ) continue;

					$arr_shipping_data[$export_key] = $shipping_data;

					if($data_option['request_ea']=='ALL') {
						$data_option['request_ea'] = $shipping_data['options'][$option_seq]['request_ea'];
						$export_data['OPT'][$option_seq]['request_ea'] = $shipping_data['options'][$option_seq]['request_ea'];
						$export_data['OPT'][$option_seq]['ea'] = $shipping_data['options'][$option_seq]['ea'];
					}

					if(!isset($data_option['title1'])){
						$export_data['title1'] = $shipping_data['options'][$option_seq]['title1'];
					}

					// 남은 출고 수량 체크
					if( $shipping_data['options'][$option_seq]['request_ea'] < $data_option['request_ea'] ){
						$error_arr[] = array(
								'msg'=>'출고할 수량 오류(출고할 수량 : '.$shipping_data['options'][$option_seq]['request_ea'].'개)',
								'order_seq'=>$export_data['order_seq'],
								'shipping_seq'=>$shipping_seq,
								'export_item_seq'=>$data_option['export_item_seq'],
								'step'=>$cfg['step']
						);
						$export_data['export_result'] = "출고할 수량 오류";
						continue;
					}

					// 실물 옵션의 재고 체크
					$item_seq 	= $shipping_data['options'][$option_seq]['item_seq'];
					$goods_seq 	= $shipping_data['items'][$item_seq]['goods_seq'];
					$goods_name = $shipping_data['items'][$item_seq]['goods_name'];
					$option1 	= $shipping_data['options'][$option_seq]['option1'];
					$option2 	= $shipping_data['options'][$option_seq]['option2'];
					$option3 	= $shipping_data['options'][$option_seq]['option3'];
					$option4 	= $shipping_data['options'][$option_seq]['option4'];
					$option5 	= $shipping_data['options'][$option_seq]['option5'];
					$option_key = $goods_seq."/".$option1."/".$option2."/".$option3."/".$option4."/".$option5;

					$goods_matching			= ($shipping_data['options'][$option_seq]['stock'] === '미매칭') ? false : true;
					$provider_seq			= $shipping_data['options'][$option_seq]['provider_seq'];
					$scm_auto_warehousing	= $shipping_data['items'][$item_seq]['goods_data']['scm_auto_warehousing'];	//자동입고상품(재고없이출고)
					$_stock_check_use		= true;																		//재고체크 사용유무

					if	( $cfg['scm_use'] == 'Y' && $provider_seq == 1 ){
						$goods_stock_option[$option_key] = ($goods_stock_option[$option_key]) ? $goods_stock_option[$option_key] : $data_option['whStock'];
						$goods_stock = $goods_stock_option[$option_key];
					}else{
						$goods_stock_option[$option_key] = ($goods_stock_option[$option_key]) ? $goods_stock_option[$option_key] : $shipping_data['options'][$option_seq]['stock'];
						$goods_stock = $goods_stock_option[$option_key];
					}
					if( $goods_matching ){
						$goods_stock_option[$option_key] = (int) ($goods_stock - $data_option['request_ea']);
					}

					/*
					@2019-02-15 pjm
					재고체크를 하지 않는 경우의 수(패키지상품은 별도 체크)
						1. SCM 버전이 아닐 때 - 출고팝업 재고에 따른 '출고완료'처리 설정이 재고와 상관없이 출고처리 일때.
					*/
					if($this->scm_cfg['use'] != 'Y' && $cfg['stockable'] != 'limit'){
						$_stock_check_use = false;
					}
					/*
						2. SCM 버전일 때 - 본사 상품이고, 
							2.1. 자동입고 상품이거나
							2.2. o2o 상품의 미매칭 상품의 주문 수집시 강제 매칭이 필요하지 않거나
							2.3. o2o 매장의 창고가 미연결창고이거나
					 */
					if(defined('__SELLERADMIN__') === true && $cfg['stockable'] != 'limit'){
						$_stock_check_use = false;
					}
					/*
						3. 입점사 관리자에서
							3.1. 재고에 따른 '출고완료'처리 설정 > 실물: 재고없어도 가능 일 때
					*/
					if(($this->scm_cfg['use'] == 'Y' && $provider_seq == 1 && ($scm_auto_warehousing || $data_option['not_match_goods_order'] == 'n' || $cfg['not_connect_scm'] == 'Y'))){
						$_stock_check_use = false;
					}
					/*
						4. '부분출고완료'이상에서만 재고체크
					*/
					if($cfg['step'] < 50){
						$_stock_check_use = false;
					}

					// 택배사 미설정 체크 @2019-10-07 sms
					if(!isset($export_data['delivery_company_code'])){
						$error_arr[] = array(
										  'msg'=>'택배사 미설정',
										  'order_seq'=>$export_data['order_seq'],
										  'shipping_seq'=>$shipping_seq,
										  'export_item_seq'=>$data_option['export_item_seq'],
										  'step'=>$cfg['step']
									);
									$export_data['export_result'] = "택배사 미설정";
									continue;
					}

					// 패키지 상품인 경우
					if($data_option['package_yn'] == 'y'){
						$packages = $this->orderpackagemodel->get_option($opt_seq);
						$chk_pkg_stock	= true;
						$org_pkg_stocks	= $pkg_stocks;
						foreach($packages as $key=>$data_package){
							$pkg_goods_seq	= $data_package['goods_seq'];
							$pkg_option_seq	= $data_package['option_seq'];
							$scm_auto_warehousing	= $data_package['scm_auto_warehousing'];
							if	($this->scm_cfg['use'] == 'Y' && $provider_seq == 1 && $scm_auto_warehousing){
								$packages[$key]['whinfo']['wh_seq']	= $cfg['wh_seq'];
								$data_package['stock']				= $data_option['request_ea'] * $data_package['unit_ea'];
								$pkg_stocks[$pkg_option_seq]		= $data_package['stock'];
							}
							if	(!isset($pkg_stocks[$pkg_option_seq])){
								// 물류관리 창고 정보 추출
								if	($this->scm_cfg['use'] == 'Y' && $provider_seq == 1){
									if (!$scm_auto_warehousing){
										if	($pkg_option_seq > 0){
											$optionStr		= $pkg_goods_seq . 'option' . $pkg_option_seq;
											$whinfo			= $this->scmmodel->get_warehouse_stock($cfg['wh_seq'], 'optioninfo', '', array($optionStr));
											$whinfo = $whinfo[$optionStr];
											$packages[$key]['whinfo']	= $whinfo;
										}
										$packages[$key]['whinfo']['wh_seq']	= $cfg['wh_seq'];
										$data_package['stock'] = (int) $whinfo['ea'];
									}
								}
								$pkg_stocks[$pkg_option_seq]	= $data_package['stock'];
							}
							$pkg_order_ea	= $data_option['request_ea'] * $data_package['unit_ea'];
							if	($pkg_order_ea > $pkg_stocks[$pkg_option_seq]){
								$chk_pkg_stock	= false;
								break;
							}else{
								$pkg_stocks[$pkg_option_seq]	-= $pkg_order_ea;
							}
						}

						if	(!$chk_pkg_stock && $cfg['stockable'] == 'limit' ){
							$pkg_stocks		= $org_pkg_stocks;	// 차감을 취소
							$error_arr[] = array(
									'msg'=>'재고 부족',
									'order_seq'=>$export_data['order_seq'],
									'shipping_seq'=>$shipping_seq,
									'export_item_seq'=>$data_option['export_item_seq'],
									'step'=>$cfg['step']
							);
							$export_data['export_result'] = "재고 부족";
							continue;
						}
					}else{

						//본사상품만 창고체크 @2017-04-24
						//자동입고상품(재고없이출고) 일 땐 제외 @2019-02-15
						if	($this->scm_cfg['use'] == 'Y' && $provider_seq == 1  && !$scm_auto_warehousing && $data_option['not_match_goods_order'] != 'n'){

							if (!$data_option['autoWh']){
								unset($scmsc);
								$scmsc['wh_seq']					= $cfg['wh_seq'];
								$scmsc['goods_seq']					= $goods_seq;
								$scmsc['option_type']				= 'option';
								$scmsc['option_seq']				= $shipping_data['options'][$option_seq]['option_seq'];
								$whData								= $this->scmmodel->get_location_goods($scmsc);
								if	( $data_option['request_ea'] > 0 && !$whData[0]['wh_seq']){ // 보내는수량 있는 상품만 체크 @2017-09-21
									$error_arr[] = array(
										  'msg'=>'출고창고 정보없음',
										  'order_seq'=>$export_data['order_seq'],
										  'shipping_seq'=>$shipping_seq,
										  'export_item_seq'=>$data_option['export_item_seq'],
										  'step'=>$cfg['step']
									);
									$export_data['export_result'] = "출고창고 정보없음";
									continue;
								}
							}
						}
						
						// 재고 채크 사용하고, 상품옵션 step이 85 미만(결제취소/주문무효/결제실패)인 경우
						if($_stock_check_use && $shipping_data['options'][$option_seq]['step'] < 85){
							// 매칭 상품이 없거나 출고 요청 재고가 상품 재고보다 많으면 재고 부족
							if (!$goods_matching || $goods_stock < $data_option['request_ea']) {
								$error_arr[] = [
									'msg' => '재고 부족',
									'order_seq' => $export_data['order_seq'],
									'shipping_seq' => $shipping_seq,
									'export_item_seq' => $data_option['export_item_seq'],
									'step' => $cfg['step']
								];
								$export_data['export_result'] = '재고 부족';

								continue;
							}
						}
					}

					# NPay 출고 검증 @2016-01-25 pjm
					if($npay_use && $data_option['npay_product_order_id']){

						$arr_shipping_data[$export_key]['npay_flag_release'] = $export_data['npay_flag_release'];

						$request_ea = $data_option['request_ea'];		//출고요청수량
						$npayres	= $this->npay_deliver_check($export_data['order_seq'],$option_seq);
						if($npayres['npay_order_id']){
							$arr_shipping_data[$export_key]['npay_order_id']			= $npayres['npay_order_id'];
							$export_data['OPT'][$option_seq]['npay_product_order_id']	= $npayres['npay_product_order_id'];

							# 수량 분할 출고 불가
							if($request_ea > 0 && $npayres['ea'] != $request_ea){
								$error_arr[] = array(
										'msg' => '['.$export_data['order_seq']." ".$goods_name.'] - 출고 수량 오류(NaverPay - 수량 분할 출고 불가)',
										'order_seq' => $export_data['order_seq'],
										'shipping_seq'=>$shipping_seq,
										'export_item_seq'=>$data_option['export_item_seq'],
										'step'=>$cfg['step']
								);
								$export_data['export_result'] = "NaverPay 수량 분할 출고 불가";
								continue;
							}
						}
						# 네이버페이 판매자센터 출고진행건 체크
						if($request_ea > 0 && $data_option['npay_pay_delivery'] == 'y'){
							$error_arr[] = array(
									'msg' => '['.$export_data['order_seq'].'] - 출고처리불가(네이버 페이 판매자센터에서 출고진행중인 주문입니다.)',
									'order_seq' => $export_data['order_seq'],
									'shipping_seq'=>$shipping_seq,
									'export_item_seq'=>$data_option['export_item_seq'],
									'step'=>$cfg['step']
							);
							$export_data['export_result'] = "NaverPay 출고처리불가";
							continue;
						}
						# 택배사 및 송장 정보 확인(실제 출고처리시 선택된 배송방법 : 택배일때만)
						if($cfg['step'] > 45 && $export_data['export_shipping_method']!= "direct_delivery" && preg_match( '/delivery/',$export_data['export_shipping_method']) && (!$export_data['delivery_company'] || !$export_data['delivery_number'])){
							$error_arr[] = array(
									'msg' => '['.$export_data['order_seq']." ".$goods_name.'] - 송장번호 누락(NaverPay - 택배사 및 송장번호 필수)',
									'order_seq' => $export_data['order_seq'],
									'shipping_seq'=>$shipping_seq,
									'export_item_seq'=>$data_option['export_item_seq'],
									'step'=>$cfg['step']
							);
							$export_data['export_result'] = "NaverPay 택배사 및 송장번호 입력 필수";
							continue;
						}
					}

					# 카카오페이 구매 출고 검증
					if($talkbuy_use && $data_option['talkbuy_product_order_id']){
						/*
						$arr_shipping_data[$export_key]['npay_flag_release'] = $export_data['npay_flag_release'];
						*/
						$request_ea = $data_option['request_ea'];		//출고요청수량
						$talkbuyres	= $this->npay_deliver_check($export_data['order_seq'],$option_seq);			// 네이버페이와 동일하게 수량 부분 출고 안됨
						if($talkbuyres['talkbuy_order_id']){
							$arr_shipping_data[$export_key]['talkbuy_order_id']				= $talkbuyres['talkbuy_order_id'];
							$export_data['OPT'][$option_seq]['talkbuy_product_order_id']	= $talkbuyres['talkbuy_product_order_id'];

							# 수량 분할 출고 불가
							if($request_ea > 0 && $talkbuyres['ea'] != $request_ea){
								$error_arr[] = array(
										'msg' => '['.$export_data['order_seq']." ".$goods_name.'] - 출고 수량 오류(KakaoPay - 수량 분할 출고 불가)',
										'order_seq' => $export_data['order_seq'],
										'shipping_seq'=>$shipping_seq,
										'export_item_seq'=>$data_option['export_item_seq'],
										'step'=>$cfg['step']
								);
								$export_data['export_result'] = "KakaoPay 수량 분할 출고 불가";
								continue;
							}
						}
						# 택배사 및 송장 정보 확인(실제 출고처리시 선택된 배송방법 : 택배일때만)
						if($cfg['step'] > 45 && $export_data['export_shipping_method']!= "direct_delivery" && preg_match( '/delivery/',$export_data['export_shipping_method']) && (!$export_data['delivery_company'] || !$export_data['delivery_number'])){
							$error_arr[] = array(
									'msg' => '['.$export_data['order_seq']." ".$goods_name.'] - 송장번호 누락(KakaoPay - 택배사 및 송장번호 필수)',
									'order_seq' => $export_data['order_seq'],
									'shipping_seq'=>$shipping_seq,
									'export_item_seq'=>$data_option['export_item_seq'],
									'step'=>$cfg['step']
							);
							$export_data['export_result'] = "KakaoPay 택배사 및 송장번호 입력 필수";
							continue;
						}
					}
					if( $data_option['SUB'] ) foreach( $data_option['SUB'] as $suboption_seq => $data_suboption ){

						foreach($shipping_data['options'] as $option_data){

							if( array_key_exists($suboption_seq,$option_data['suboptions']) ){

								// 출고 수량이 0이라면 검증 제외
								if( $shipping_data['options'][$option_seq]['suboptions'][$suboption_seq]['request_ea'] <= 0 ) continue;

								if($data_suboption['request_ea']=='ALL') {
									$data_suboption['request_ea'] =  $shipping_data['options'][$option_seq]['suboptions'][$suboption_seq]['request_ea'];
									$export_data['OPT'][$option_seq]['SUB'][$suboption_seq]['request_ea'] = $shipping_data['options'][$option_seq]['request_ea'];
									$export_data['OPT'][$option_seq]['SUB'][$suboption_seq]['ea'] = $shipping_data['options'][$option_seq]['ea'];
								}

								// 실물 추가옵션의 재고체크
								$suboption 	= $shipping_data['options'][$option_seq]['suboptions'][$suboption_seq]['suboption'];
								$option_key = $goods_seq."/".$suboption;

								$goods_matching = ($shipping_data['options'][$option_seq]['suboptions'][$suboption_seq]['stock'] === '미매칭') ? false : true;
								// 재고관리는 본사 상품일때에만
								if	($cfg['scm_use'] == 'Y' && $provider_seq == 1){
									$goods_stock_suboption[$option_key] = ($goods_stock_suboption[$option_key]) ? $goods_stock_suboption[$option_key] : $data_suboption['whStock'];
									$goods_stock = $goods_stock_suboption[$option_key];
								}else{
									$goods_stock_suboption[$option_key] = ($goods_stock_suboption[$option_key]) ? $goods_stock_suboption[$option_key] : $shipping_data['options'][$option_seq]['stock'];
									$goods_stock = $goods_stock_suboption[$option_key];
								}
								if( $goods_matching ){
									$goods_stock_suboption[$option_key] = (int) ($goods_stock - $data_suboption['request_ea']);
								}

								// 남은 출고 수량 체크
								if($shipping_data['options'][$option_seq]['suboptions'][$suboption_seq]['request_ea'] < $data_suboption['request_ea'] && $data_suboption['request_ea'] > 0){
									$error_arr[] = array(
											'msg'=>' 출고할 수량 오류(출고할 수량: '.$shipping_data['options'][$option_seq]['suboptions'][$suboption_seq]['request_ea'].')',
											'order_seq'=>$export_data['order_seq'],
											'shipping_seq'=>$shipping_seq,
											'export_item_seq'=>$data_suboption['export_item_seq'],
											'step'=>$cfg['step']
									);
									$export_data['export_result'] = "출고할 수량 오류";
									continue;
								}
								# 네이버페이 판매자센터 출고진행건 체크
								if($data_suboption['request_ea'] > 0 && $data_suboption['npay_pay_delivery'] == 'y'){
									$error_arr[] = array(
											'msg' => '['.$export_data['order_seq'].'] - 출고처리불가(네이버 페이 판매자센터에서 출고진행중인 주문입니다.)',
											'order_seq' => $export_data['order_seq'],
											'shipping_seq'=>$shipping_seq,
											'export_item_seq'=>$data_suboption['export_item_seq'],
											'step'=>$cfg['step']
									);
									$export_data['export_result'] = "NaverPay 출고처리불가";
									continue;
								}

								// 패키지 상품인 경우
								if($data_suboption['package_yn'] == 'y'){
									$packages = $this->orderpackagemodel->get_suboption($suboption_seq);
									$chk_pkg_stock	= true;
									$org_pkg_stocks	= $pkg_stocks;
									foreach($packages as $key=>$data_package){
										$pkg_goods_seq	= $data_package['goods_seq'];
										$pkg_option_seq	= $data_package['option_seq'];
										$scm_auto_warehousing	= $data_package['scm_auto_warehousing'];
										if	($this->scm_cfg['use'] == 'Y' && $provider_seq == 1 && $scm_auto_warehousing){
										    $packages[$key]['whinfo']['wh_seq']	= $cfg['wh_seq'];
										    $data_package['stock']				= $data_suboption['request_ea'] * $data_package['unit_ea'];
										    $pkg_stocks[$pkg_option_seq]		= $data_package['stock'];
										}
										
										if	(!isset($pkg_stocks[$pkg_option_seq])){
										// 물류관리 창고 정보 추출
										if	($this->scm_cfg['use'] == 'Y' && $provider_seq == 1 ){
										    if (!$scm_auto_warehousing){
												if	($pkg_option_seq > 0){
													$optionStr		= $pkg_goods_seq . 'option' . $pkg_option_seq;
												$whinfo			= $this->scmmodel->get_warehouse_stock($cfg['wh_seq'], 'optioninfo', '', array($optionStr));
												$whinfo = $whinfo[$optionStr];
												$packages[$key]['whinfo']	= $whinfo;
											}
											$packages[$key]['whinfo']['wh_seq']	= $cfg['wh_seq'];
											$data_package['stock'] = (int) $whinfo['ea'];
										    }
										}
											$pkg_stocks[$pkg_option_seq]	= $data_package['stock'];
										}
										$pkg_order_ea	= $data_suboption['request_ea'] * $data_package['unit_ea'];
										if	($pkg_order_ea > $pkg_stocks[$pkg_option_seq]){
											$chk_pkg_stock	= false;
											break;
										}else{
											$pkg_stocks[$pkg_option_seq]	-= $pkg_order_ea;
									}
								}

									if	(!$chk_pkg_stock && $cfg['stockable'] == 'limit' ){
										$pkg_stocks		= $org_pkg_stocks;	// 차감을 취소
										$error_arr[] = array(
												'msg'=>'재고 부족',
												'order_seq'=>$export_data['order_seq'],
												'shipping_seq'=>$export_data['shipping_seq'],
												'export_item_seq'=>$data_suboption['export_item_seq'],
												'step'=>$cfg['step']
										);
										$export_data['export_result'] = "재고 부족";
										continue;
									}
								}else{
									// 재고 채크 사용하고, 상품 추가 옵션 step이 85 미만(결제취소/주문무효/결제실패)인 경우
									if($_stock_check_use && $shipping_data['options'][$option_seq]['suboptions'][$suboption_seq]['step'] < 85){
										// 매칭 상품이 없거나 출고 요청 재고가 상품 재고보다 많으면 재고 부족
										if (!$goods_matching || ($goods_stock && $goods_stock < $data_suboption['request_ea'])) {
											$error_arr[] = [
												'msg' => '재고 부족',
												'order_seq' => $export_data['order_seq'],
												'shipping_seq' => $export_data['shipping_seq'],
												'export_item_seq' => $data_suboption['export_item_seq'],
												'step' => $cfg['step']
											];
											$export_data['export_result'] = '재고 부족';

											continue;
										}
									}
								}
							}
						}
					}

					// 오픈마켓 주문건은 롯데택배(자동) 제한 우편번호가 정확하지 않을 수 있으므로 제한 함 2019-05-30 by hyem 
					$isMarketOrder		= $this->connectormodel->checkIsMarketOrder($export_data['order_seq']);
					if ($isMarketOrder == true && in_array($export_data['export_shipping_method'], array('delivery', 'postpaid','each_delivery','each_postpaid')) && 
						in_array($export_data['delivery_company_code'], array('auto_hlc')) ) {
						$error_arr[] = array(
								'msg' => $export_data['delivery_company'].' 오픈마켓 롯데택배 미지원',
								'order_seq' => $export_data['order_seq'],
								'shipping_seq'=>$shipping_seq,
								'export_item_seq'=>$data_option['export_item_seq'],
								'step'=>$cfg['step']
						);
						$export_data['export_result'] = $export_data['delivery_company']." 오픈마켓 롯데택배 미지원";
						continue;
					}
					// 롯데택배(자동)인경우 우편번호 5자리 출고처리 제한
					// 롯데택배(자동) 우편번호 5자리 가능하도록 중계서버 개선되어 제한 해제 2018-08-20
					/*
					if($cfg['step'] > 45 && 
						in_array($export_data['export_shipping_method'], array('delivery', 'postpaid','each_delivery','each_postpaid')) && 
						in_array($export_data['delivery_company_code'], array('auto_hlc')) && 
						strlen($shipping_data['recipient_zipcode']) < 6) {
						$error_arr[] = array(
								'msg' => $export_data['delivery_company'].' 우편번호 5자리 미지원',
								'order_seq' => $export_data['order_seq'],
								'shipping_seq'=>$shipping_seq,
								'export_item_seq'=>$data_option['export_item_seq'],
								'step'=>$cfg['step']
						);
						$export_data['export_result'] = $export_data['delivery_company']." 우편번호 5자리 미지원";
						continue;
					}
					*/
				}
			}


			if( $export_data['COU'] ) foreach( $export_data['COU'] as $option_seq => $data_option ){

				$shipping_exists = false;
				$_stock_check_use = true; //재고체크 사용유무

				foreach($order_data as $shipping_data){

					list($opttype,$shipping_seq,$opt_seq) = $this->get_info_by_export_item_seq($data_option['export_item_seq']);

					if( $shipping_seq != $shipping_data['shipping_seq'] ) continue;
					if( !array_key_exists($option_seq,$shipping_data['options']) ) continue;

					if( $shipping_data['tot_request_ea'] > 0 ) {
						$shipping_exists = true;
					}

					// 출고 수량이 0이라면 검증 제외
					if( $shipping_data['options'][$option_seq]['request_ea'] <= 0 ) continue;

					$arr_shipping_data[$export_key] = $shipping_data;

					if(!isset($data_option['title1'])){
						$export_data['title1'] = $shipping_data['options'][$option_seq]['title1'];
					}

					if($data_option['request_ea']=='ALL') {
						$data_option['request_ea'] = $shipping_data['options'][$option_seq]['request_ea'];
						$export_data['COU'][$option_seq]['request_ea'] = $shipping_data['options'][$option_seq]['request_ea'];
						$export_data['COU'][$option_seq]['ea'] = $shipping_data['options'][$option_seq]['ea'];
					}

					// 남은 출고 수량 체크
					if($shipping_data['options'][$option_seq]['request_ea'] < $data_option['request_ea'] || $data_option['request_ea']==0 ){
						$error_arr[] = array(
								'msg'=>'출고할 수량 오류(출고할 수량:'.$shipping_data['options'][$option_seq]['request_ea'].')',
								'order_seq'=>$export_data['order_seq'],
								'shipping_seq'=>$shipping_seq,
								'export_item_seq'=>$data_option['export_item_seq'],
								'step'=>$cfg['ticket_step']
						);
						$export_data['export_result'] = "출고할 수량 오류";
						continue;
					}

					// 티켓 옵션의 재고 체크
					$item_seq 	= $shipping_data['options'][$option_seq]['item_seq'];
					$goods_seq 	= $shipping_data['items'][$item_seq]['goods_seq'];
					$option1 	= $shipping_data['options'][$option_seq]['option1'];
					$option2 	= $shipping_data['options'][$option_seq]['option2'];
					$option3 	= $shipping_data['options'][$option_seq]['option3'];
					$option4 	= $shipping_data['options'][$option_seq]['option4'];
					$option5 	= $shipping_data['options'][$option_seq]['option5'];
					$option_key = $goods_seq."/".$option1."/".$option2."/".$option3."/".$option4."/".$option5;

					$goods_matching = ($shipping_data['options'][$option_seq]['stock'] === '미매칭') ? false : true;

					if	( $cfg['scm_use'] == 'Y' && $provider_seq == 1 ){
						$goods_stock_option[$option_key] = ($goods_stock_option[$option_key]) ? $goods_stock_option[$option_key] : $data_option['whStock'];
						$goods_stock = $goods_stock_option[$option_key];
					}else{
						$goods_stock_option[$option_key] = ($goods_stock_option[$option_key]) ? $goods_stock_option[$option_key] : $shipping_data['options'][$option_seq]['stock'];
						$goods_stock = $goods_stock_option[$option_key];
					}
					if( $goods_matching ){
						$goods_stock_option[$option_key] = (int) ($goods_stock - $data_option['request_ea']);
					}
					
					/*
						@재고 체크 사용하지 않는 조건
						1. '부분출고완료' 상태 이전인 경우
					*/
					if($cfg['ticket_step'] < 50){
						$_stock_check_use = false;
					}

					// 2. 티켓 재고 제한 없는 경우
					if($cfg['ticket_stockable'] != 'limit'){
						$_stock_check_use = false;
					}
					
					// 티켓 재고 채크 사용하고, 티켓 옵션 step이 85 미만(결제취소/주문무효/결제실패)인 경우
					if($_stock_check_use && $shipping_data['options'][$option_seq]['step'] < 85){
						// 매칭 티켓 상품이 없거나 출고 요청 재고가 티켓 재고보다 많으면 재고 부족
						if (!$goods_matching || $goods_stock < $data_option['request_ea']) {
							$error_arr[] = [
								'msg' => '재고 부족',
								'order_seq' => $export_data['order_seq'],
								'shipping_seq' => $shipping_seq,
								'export_item_seq' => $data_option['export_item_seq'],
								'step' => $cfg['ticket_step']
							];
							$export_data['export_result'] = '재고 부족';

							continue;
						}
					}

					// 티켓번호 체크
					$item_seq = $shipping_data['options'][$option_seq]['item_seq'];
					$goods_data = $shipping_data['items'][$item_seq]['goods_data'];
					if($goods_data['coupon_serial_type']=='n'){
						$couopn_stock = $this->goodsmodel->get_count_coupon_serial($goods_data['goods_seq']);
						if( $couopn_stock < $data_option['request_ea'] && $data_option['package_yn']!='y' ){
							$error_arr[] = array(
									'msg'=>'티켓번호부족',
									'order_seq'=>$export_data['order_seq'],
									'shipping_seq'=>$shipping_seq,
									'export_item_seq'=>$data_option['export_item_seq'],
									'step'=>$cfg['ticket_step']
							);
							$export_data['export_result'] = "티켓번호부족";
							continue;
						}
					}
				}
			}

			if( !$shipping_exists ){
				$error_arr[] = array(
						'msg'				=> '['.$goods_name.'] 출고 미매칭',
						'order_seq'			=> $export_data['order_seq'],
						'shipping_seq'		=> $shipping_seq,
						'export_item_seq'	=> $data_option['export_item_seq']
				);
				$export_data['export_result'] = "출고 미매칭";
				continue;
			}

			$export[$export_key] = $export_data;

			if(!$export_data['export_result']){
				$export_params[] = $this->set_export_params($export_data, $arr_shipping_data[$export_key], $cfg);
			}else{
				// 출고대상에서 제외된 출고로 인해 차감한 재고를 원복함.
				$goods_stock_option			= $org_goods_stock_option;
			}

		}

		return array($export,$error_arr,$export_params);
	}

	// NPay 출고 수량 검증(수량 분할 출고 불가) @2016-01-25 pjm
	function npay_deliver_check($order_seq,$option_seq){

		$query	= "select item_option_seq,(ea-step85-step45-step55-step65-step75) ea,npay_order_id,npay_product_order_id from fm_order_item_option where order_seq='".$order_seq."' and item_option_seq =".$option_seq."";
		$query	= $this->db->query($query);
		$result	= $query->result_array();

		return $result[0];

	}

	function set_export_params($export_data,$shipping_data,$cfg)
	{
		# Npay 사용여부 확인
		$npay_use					= npay_useck();

		$data['goods_kind']			= 'goods';
		$item_field					= "OPT";
		$num_courier				= 0;

		$data['status']				= $cfg['step'];
		$data['shipping_seq']		= $shipping_data['shipping_seq'];
		$data['order_seq']			= $shipping_data['order_seq'];
		$data['orign_order_seq']	= $shipping_data['orign_order_seq'];
		$data['npay_flag_release']	= $shipping_data['npay_flag_release'];
		$data['npay_order_id']		= $shipping_data['npay_order_id'];	//npay 주문번호
		$data['talkbuy_order_id']	= $shipping_data['talkbuy_order_id'];	//카카오페이 구매 주문번호

		// 배송정보 추가 :: 2016-10-06 lwh
		$data['shipping_group']		= $export_data['export_shipping_group'] ? $export_data['export_shipping_group'] : $shipping_data['shipping_group'];
		$data['shipping_method']	= $export_data['export_shipping_method']? $export_data['export_shipping_method'] : $shipping_data['shipping_method'];
		$data['shipping_set_name']	= $export_data['export_shipping_set_name']? $export_data['export_shipping_set_name'] : $shipping_data['shipping_set_name'];
		$data['store_scm_type']		= $export_data['export_store_scm_type']? $export_data['export_store_scm_type'] : $shipping_data['store_scm_type'];
		$data['shipping_address_seq']= $export_data['export_shipping_address_seq']? $export_data['export_shipping_address_seq'] : $shipping_data['sending_address']['shipping_address_seq'];

		# Npay주문건은 무조건 출고준비 처리(중계서버에서 API전송결과에 따라 자동 출고완료 처리됨)
		if($npay_use && $data['npay_order_id']){
			//$data['status'] = "45";
		}


		foreach($shipping_data['couriers'] as $courier_code => $courier_data){
			$num_courier++;
			if( $export_data['delivery_company'] == $courier_data['company']){
				$delivery_company = $courier_code;
				break;
			}
			
			if(!$export_data['delivery_company'] && $num_courier==1 && $export_data['export_shipping_method '] == "delivery"){
				$delivery_company = $courier_code;
			}
		}

		$delivery_number = $export_data['delivery_number'];

		// 입점몰 해외배송 제공안함
		$data['international']				= ($shipping_data['international']) ? $shipping_data['international'] : 'domestic';
		$international_shipping_method = '';
		$international_number = '';

		if($data['international'] == 'domestic'){
			$data['domestic_shipping_method']	= $shipping_data['shipping_method'];
			if($export_data['export_shipping_method']){
				$data['domestic_shipping_method']	= $export_data['export_shipping_method'];
			}
			$data['delivery_company_code'] 		= $delivery_company;
			$data['delivery_number']			= $delivery_number;
		}else{
			$data['international_shipping_method']	= $international_shipping_method;
			$data['international_delivery_no']		= $international_number;
		}
		
		// 상태변경일시 업데이트 추가 @2017-02-09 nsg
		$data['status_date']				= date('Y-m-d H:i:s');
		$data['export_date']				= $cfg['export_date'];
		$data['regist_date']				= date('Y-m-d H:i:s');

		// 출고완료일
		if($data['status'] == 55) $data['complete_date']	= date('Y-m-d H:i:s');
		$data['shipping_provider_seq']		= $shipping_data['provider_seq'];

		// 티켓상품일 경우
		if($export_data['COU']){
			$item_field = "COU";
			$data['goods_kind']	= 'coupon';
			$data['status']			= 55;
			$data_order = $this->ordermodel->get_order($shipping_data['order_seq']);
			$data['coupon_mail'] = $data_order['recipient_email'];
			$data['coupon_sms'] = $data_order['recipient_cellphone'];
		}

		if($export_data[$item_field]) foreach($export_data[$item_field] as $option_seq => $data_option){
			$data_item['item_seq'][] 		= $shipping_data['options'][$option_seq]['item_seq'];
			$data_item['shipping_provider_seq'][] 		= $shipping_data['options'][$option_seq]['provider_seq'];
			$data_item['option_seq'][] 		= $option_seq;
			$data_item['suboption_seq'][] 	= '';
			$data_item['ea'][] 				= $data_option['request_ea'];
			$data_item['auto_wh'][] 		= $data_option['autoWh'];
			$data_item['supplyprice'][] 	= $data_option['supplyprice'];
			$data_item['export_item_seq'][] = $data_option['export_item_seq'];
			$data_item['goodscode'][] 		= $data_option['goodscode'];
			$data_item['goodsname'][] 		= $data_option['goods_name'];
			$data_item['optioninfo'][] 		= $data_option['optioninfo'];
			$data_item['optionname'][] 		= $data_option['option1'].$data_option['option2'].$data_option['option3'].$data_option['option4'].$data_option['option5'];
			$data_item['package_yn'][] 		= $data_option['package_yn'];
			if($data_option['npay_product_order_id']) $data_item['npay_product_order_id'][]= $data_option['npay_product_order_id'];
			if($data_option['talkbuy_product_order_id']) $data_item['talkbuy_product_order_id'][]= $data_option['talkbuy_product_order_id'];

			foreach($data_option['SUB'] as $suboption_seq => $data_suboption){
				$data_item['item_seq'][] 		= $shipping_data['options'][$option_seq]['item_seq'];
				$data_item['option_seq'][] 		= $option_seq;
				$data_item['suboption_seq'][] 	= $suboption_seq;
				$data_item['ea'][] 				= $data_suboption['request_ea'];
				$data_item['auto_wh'][] 		= $data_suboption['autoWh'];
				$data_item['supplyprice'][] 	= $data_suboption['supplyprice'];
				$data_item['export_item_seq'][] = $data_suboption['export_item_seq'];
				$data_item['goodscode'][] 		= $data_suboption['goodscode'];
				$data_item['goodsname'][] 		= $data_suboption['goods_name'];
				$data_item['optioninfo'][] 		= $data_suboption['optioninfo'];
				$data_item['optionname'][] 		= $data_suboption['suboption'];
				$data_item['package_yn'][] 		= $data_suboption['package_yn'];
				if($data_suboption['npay_product_order_id']) $data_item['npay_product_order_id'][]= $data_suboption['npay_product_order_id'];
				if($data_suboption['talkbuy_product_order_id']) $data_item['talkbuy_product_order_id'][]= $data_suboption['talkbuy_product_order_id'];
			}
		}

		$data['items'] = $data_item;

		return $data;
	}

	// 출고데이터 조합
	public function order_export($params)
	{
		$cfg							= $params['cfg'];
		$arr_order_seq					= $params['arr_order_seq'];
		$arr_request_ea					= $params['arr_request_ea'];
		$arr_shipping_goods_kind		= $params['arr_shipping_goods_kind'];
		$arr_delivery_company			= $params['arr_delivery_company'];
		$arr_delivery_number			= $params['arr_delivery_number'];
		$arr_export_data				= $params['arr_export_data'];
		$param_shipping					= $params['param_shipping'];
		$param_scmoptioninfo			= $params['arr_scmoptioninfo'];
		$arr_npay_flag_release			= $params['arr_npay_flag_release'];
		$arr_direct_export_complete		= $params['arr_direct_export_complete'];
		$arr_not_match_goods_order		= $params['arr_not_match_goods_order'];
		$provider_seq					= $params['provider_seq'];

		$coupon_payexport = false;
		if( $arr_order_seq && !$arr_request_ea ){
			// 티켓상품 자동출고 이면 true
			$coupon_payexport = true;
		}

		foreach($arr_order_seq as $shipping_seq=>$order_seq){
			/**
			 * 티켓상품 자동출고인 경우 무조건 data_shipping 추출
			 * 그외(팝업출고)는 출고수량 있는 정보만 data_shipping 추출
			 */
			if($coupon_payexport === false && !array_key_exists($shipping_seq,$arr_request_ea )) {
				continue;
			}
			if( $param_shipping[$shipping_seq] ){
				$data_shipping = $param_shipping[$shipping_seq];
			}else{
				$params_export_item['order_seq']			= $order_seq;
				$params_export_item['shipping_seq']	= $shipping_seq;
				$data_shipping = $this->get_data_for_batch_export_item($params_export_item);
			}
			$result_shipping[] = $data_shipping;

			foreach($data_shipping as $tmp_data_shipping){
				$tmp_ex = false;
				if(!$param_shipping_for_order[$order_seq]) $param_shipping_for_order[$order_seq] = array();
				foreach($param_shipping_for_order[$order_seq] as $tmp_param_shipping){

					//쿠폰상품시 배송그룹 제외@2016-02-15
					if( !preg_match('/coupon/',$tmp_param_shipping['shipping_method']) && $tmp_param_shipping['shipping_seq'] == $tmp_data_shipping['shipping_seq']){
						$tmp_ex = true;
					}
				}
				if(!$tmp_ex){
					$param_shipping_for_order[$order_seq][] = $tmp_data_shipping;
				}
			}
		}

		// 티켓상품 자동 출고
		if( $coupon_payexport === true ){
			$arr_order_seq = array();
			foreach($result_shipping as $tmp_shippping){
				foreach($tmp_shippping as $row_shipping){
					if(  preg_match('/coupon/',$row_shipping['shipping_method']) ){
						foreach($row_shipping['options'] as $row_options){
							$arr_request_ea[$row_shipping['shipping_seq']]['option'][$row_options['item_option_seq']] = $row_options['ea'];
							$arr_shipping_goods_kind[$row_shipping['shipping_seq']]['option'][$row_options['item_option_seq']] = 'COU';
							$arr_order_seq[$row_shipping['shipping_seq']] = $row_shipping['order_seq'];
						}
					}
				}
			}
		}

		// 출고데이터 조합
		foreach($arr_request_ea as $shipping_seq=>$data_request_ea){

			$tmp = '';
			foreach($data_request_ea['option'] as $item_option_seq => $request_ea){


				$data_shipping = '';
				foreach($result_shipping as $arr_shipping){
					foreach($arr_shipping as $tmp_shipping){
						if( $tmp_shipping['shipping_seq'] != $shipping_seq ) continue;
						$data_shipping = $tmp_shipping;
					}
				}

				if(!$data_shipping) continue;

				$shipping_goods_kind = $arr_shipping_goods_kind[$shipping_seq]['option'][$item_option_seq];
				$item_seq			= $data_shipping['options'][$item_option_seq]['item_seq'];
				$tmp[$shipping_goods_kind][$item_option_seq]['export_item_seq'] = $shipping_goods_kind.'-'.$shipping_seq.'-'.$item_option_seq;
				$tmp[$shipping_goods_kind][$item_option_seq]['request_ea'] = $request_ea;

				$tmp[$shipping_goods_kind][$item_option_seq]['goods_name'] = $data_shipping['items'][$item_seq]['goods_name'];
				$tmp[$shipping_goods_kind][$item_option_seq]['npay_product_order_id']= $data_shipping['options'][$item_option_seq]['npay_product_order_id'];
				$tmp[$shipping_goods_kind][$item_option_seq]['npay_pay_delivery']= $data_option['npay_pay_delivery'];
				$tmp[$shipping_goods_kind][$item_option_seq]['talkbuy_product_order_id']= $data_shipping['options'][$item_option_seq]['talkbuy_product_order_id'];
				$tmp[$shipping_goods_kind][$item_option_seq]['talkbuy_pay_delivery']= $data_option['talkbuy_pay_delivery'];
				$tmp[$shipping_goods_kind][$item_option_seq]['title1'] =  $data_shipping['options'][$item_option_seq]['title1'];
				$tmp[$shipping_goods_kind][$item_option_seq]['option1'] =  $data_shipping['options'][$item_option_seq]['option1'];
				$tmp[$shipping_goods_kind][$item_option_seq]['title2'] =  $data_shipping['options'][$item_option_seq]['title2'];
				$tmp[$shipping_goods_kind][$item_option_seq]['option2'] =  $data_shipping['options'][$item_option_seq]['option2'];
				$tmp[$shipping_goods_kind][$item_option_seq]['title3'] =  $data_shipping['options'][$item_option_seq]['title3'];
				$tmp[$shipping_goods_kind][$item_option_seq]['option3'] =  $data_shipping['options'][$item_option_seq]['option3'];
				$tmp[$shipping_goods_kind][$item_option_seq]['title4'] =  $data_shipping['options'][$item_option_seq]['title4'];
				$tmp[$shipping_goods_kind][$item_option_seq]['option4'] =  $data_shipping['options'][$item_option_seq]['option4'];
				$tmp[$shipping_goods_kind][$item_option_seq]['title5'] =  $data_shipping['options'][$item_option_seq]['title5'];
				$tmp[$shipping_goods_kind][$item_option_seq]['option5'] = $data_shipping['options'][$item_option_seq]['option5'];
				$tmp[$shipping_goods_kind][$item_option_seq]['stock'] = $data_shipping['options'][$item_option_seq]['stock'];
				$tmp[$shipping_goods_kind][$item_option_seq]['ea'] = $data_shipping['options'][$item_option_seq]['ea'];
				$tmp[$shipping_goods_kind][$item_option_seq]['refund_ea'] = $data_shipping['options'][$item_option_seq]['refund_ea'];
				$tmp[$shipping_goods_kind][$item_option_seq]['export_ea'] = $data_shipping['options'][$item_option_seq]['step45'] + $data_shipping['options'][$item_option_seq]['step55'] + $data_shipping['options'][$item_option_seq]['step65'] + $data_shipping['options'][$item_option_seq]['step75'];
				if($param_scmoptioninfo){
					$tmp[$shipping_goods_kind][$item_option_seq]['optioninfo'] = $param_scmoptioninfo[$shipping_seq]['option'][$item_option_seq]['info'];
					$tmp[$shipping_goods_kind][$item_option_seq]['whStock'] = $param_scmoptioninfo[$shipping_seq]['option'][$item_option_seq]['stock'];
					$tmp[$shipping_goods_kind][$item_option_seq]['autoWh'] = $param_scmoptioninfo[$shipping_seq]['option'][$item_option_seq]['autowh'];
					$tmp[$shipping_goods_kind][$item_option_seq]['goodscode'] = $param_scmoptioninfo[$shipping_seq]['option'][$item_option_seq]['code'];
					$tmp[$shipping_goods_kind][$item_option_seq]['supplyprice'] = $param_scmoptioninfo[$shipping_seq]['option'][$item_option_seq]['price'];
				}
				$tmp[$shipping_goods_kind][$item_option_seq]['package_yn'] =  $data_shipping['options'][$item_option_seq]['package_yn'];
				$tmp[$shipping_goods_kind][$item_option_seq]['not_match_goods_order'] = $arr_not_match_goods_order[$shipping_seq]['option'][$item_option_seq];

				if( $data_shipping['options'][$item_option_seq]['suboptions'] ) foreach($data_shipping['options'][$item_option_seq]['suboptions'] as $data_suboption_for_shipping){
					$item_suboption_seq = $data_suboption_for_shipping['item_suboption_seq'];
					if($arr_request_ea[$shipping_seq]['suboption'][$item_suboption_seq] ){

						$tmp[$shipping_goods_kind][$item_option_seq]['SUB'][$item_suboption_seq]['export_item_seq'] = 'SUB'.'-'.$shipping_seq.'-'.$item_suboption_seq;
						$request_ea = $arr_request_ea[$shipping_seq]['suboption'][$item_suboption_seq];
						$tmp[$shipping_goods_kind][$item_option_seq]['SUB'][$item_suboption_seq]['npay_product_order_id'] = $data_shipping['options'][$item_option_seq]['suboptions'][$item_suboption_seq]['npay_product_order_id'];
						$tmp[$shipping_goods_kind][$item_option_seq]['SUB'][$item_suboption_seq]['npay_pay_delivery'] = $data_option['suboptions'][$item_suboption_seq]['npay_pay_delivery'];
						$tmp[$shipping_goods_kind][$item_option_seq]['SUB'][$item_suboption_seq]['talkbuy_product_order_id'] = $data_shipping['options'][$item_option_seq]['suboptions'][$item_suboption_seq]['talkbuy_product_order_id'];
						$tmp[$shipping_goods_kind][$item_option_seq]['SUB'][$item_suboption_seq]['talkbuy_pay_delivery'] = $data_option['suboptions'][$item_suboption_seq]['talkbuy_pay_delivery'];
						$tmp[$shipping_goods_kind][$item_option_seq]['SUB'][$item_suboption_seq]['request_ea'] = $request_ea;
						$tmp[$shipping_goods_kind][$item_option_seq]['SUB'][$item_suboption_seq]['subtitle'] = $data_shipping['options'][$item_option_seq]['suboptions'][$item_suboption_seq]['title'];
						$tmp[$shipping_goods_kind][$item_option_seq]['SUB'][$item_suboption_seq]['suboption']= $data_shipping['options'][$item_option_seq]['suboptions'][$item_suboption_seq]['suboption'];

						$tmp[$shipping_goods_kind][$item_option_seq]['SUB'][$item_suboption_seq]['stock']= $data_shipping['options'][$item_option_seq]['suboptions'][$item_suboption_seq]['stock'];
						$tmp[$shipping_goods_kind][$item_option_seq]['SUB'][$item_suboption_seq]['ea']= $data_shipping['options'][$item_option_seq]['suboptions'][$item_suboption_seq]['ea'];
						$tmp[$shipping_goods_kind][$item_option_seq]['SUB'][$item_suboption_seq]['refund_ea']= $data_shipping['options'][$item_option_seq]['suboptions'][$item_suboption_seq]['refund_ea'];
						$tmp[$shipping_goods_kind][$item_option_seq]['SUB'][$item_suboption_seq]['export_ea']= $data_shipping['options'][$item_option_seq]['suboptions'][$item_suboption_seq]['step45']+$data_shipping['options'][$item_option_seq]['suboptions'][$item_suboption_seq]['step55']+$data_shipping['options'][$item_option_seq]['suboptions'][$item_suboption_seq]['step65']+$data_shipping['options'][$item_option_seq]['suboptions'][$item_suboption_seq]['step75'];

						if($param_scmoptioninfo){
							$tmp[$shipping_goods_kind][$item_option_seq]['SUB'][$item_suboption_seq]['optioninfo'] = $param_scmoptioninfo[$shipping_seq]['suboption'][$item_suboption_seq]['info'];
							$tmp[$shipping_goods_kind][$item_option_seq]['SUB'][$item_suboption_seq]['whStock'] = $param_scmoptioninfo[$shipping_seq]['suboption'][$item_suboption_seq]['stock'];
							$tmp[$shipping_goods_kind][$item_option_seq]['SUB'][$item_suboption_seq]['autoWh'] = $param_scmoptioninfo[$shipping_seq]['suboption'][$item_suboption_seq]['autowh'];
							$tmp[$shipping_goods_kind][$item_option_seq]['SUB'][$item_suboption_seq]['goodscode'] = $param_scmoptioninfo[$shipping_seq]['suboption'][$item_suboption_seq]['code'];
							$tmp[$shipping_goods_kind][$item_option_seq]['SUB'][$item_suboption_seq]['supplyprice'] = $param_scmoptioninfo[$shipping_seq]['suboption'][$item_suboption_seq]['price'];
						}
						$tmp[$shipping_goods_kind][$item_option_seq]['SUB'][$item_suboption_seq]['package_yn'] = $data_shipping['options'][$item_option_seq]['suboptions'][$item_suboption_seq]['package_yn'];
						$tmp[$shipping_goods_kind][$item_option_seq]['SUB'][$item_suboption_seq]['not_match_goods_order'] = $arr_not_match_goods_order[$shipping_seq]['suboption'][$item_suboption_seq];
					}
				}

			}

			$delivery_company_code			= $arr_delivery_company[$shipping_seq];

			# npay 재배송건에 대한 보류해제
			if($arr_npay_flag_release){
				$tmp['npay_flag_release']	= $arr_npay_flag_release[$shipping_seq];
			}

			$tmp['order_seq']				= $arr_order_seq[$shipping_seq];
			if( $data_shipping['couriers'] ){
				$tmp['delivery_company_code']	= $delivery_company_code;
				$tmp['delivery_company']			= $data_shipping['couriers'][$delivery_company_code]['company'];
			}
			$tmp['delivery_number']			= trim($arr_delivery_number[$shipping_seq]);
			$tmp['provider_data'] 					= $this->providermodel->get_provider($provider_seq);
			$tmp['check_delivery_code']		= $tmp['provider_data']['check_delivery_code'];

			// 출고 배송정보 추가 :: 2016-10-06 lwh
			if( $arr_export_data['group'][$shipping_seq] ){
				$tmp['export_shipping_group'] = $arr_export_data['group'][$shipping_seq];
			}
			if( $arr_export_data['method'][$shipping_seq] ){
				$tmp['export_shipping_method'] = $arr_export_data['method'][$shipping_seq];
			}
			if( $arr_export_data['set_name'][$shipping_seq] ){
				$tmp['export_shipping_set_name'] = $arr_export_data['set_name'][$shipping_seq];
			}
			if( $arr_export_data['scm_type'][$shipping_seq] ){
				$tmp['export_store_scm_type'] = $arr_export_data['scm_type'][$shipping_seq];
			}
			if( $arr_export_data['address_seq'][$shipping_seq] ){
				$tmp['export_shipping_address_seq'] = $arr_export_data['address_seq'][$shipping_seq];
			}

			$tmp['shipping_method'] = $data_shipping['shipping_method'];

			$tmp['direct_export_complete'] = $arr_direct_export_complete[$shipping_seq];
			
			$export[] = $tmp;

		}

		// 출고데이터할 데이터 검증
		$result_check = $this->check_error_export($export,$cfg,'',$param_shipping_for_order);

		return $result_check;
	}
}
?>