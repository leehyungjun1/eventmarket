<?php
class Excelexportmodel extends CI_Model {
	var $downloadType		= "Excel5";
	var $saveurl			= "/data/tmp";
	var $cell = array("A", "B", "C", "D", "E", "F", "G", "H", "I", "J", "K", "L", "M", "N", "O", "P", "Q", "R", "S", "T", "U", "V", "W", "X", "Y", "Z");

	var $itemList = array(
		"shipping_provider"				=> "배송책임",
		"export_code"						=> "*출고번호",
		"order_seq"							=> "주문번호",
		"order_regist_date"				=> "주문일",
		"order_user_name"				=> "주문자명",
		"userid"							=> "주문자아이디",
		"order_phone"						=> "주문자연락처",
		"order_cellphone"				=> "주문자휴대폰",
		"order_email"						=> "주문자이메일",
		"clearance_unique_personal_code"	=> "개인통관고유부호",
		"memo"								=> "사용자메모",
		"admin_memo"					=> "관리자메모",
		"complete_date"					=> "*출고완료일",
		"shipping_date"					=> "배송완료일",
		"export_date"						=> "출고일",
		"shipping_method"				=> "받는방법",
		"delivery_company_code"		=> "*택배사코드",
		"delivery_number"				=> "*운송장번호",
		"status"								=> "출고상태",
		"recipient_user_name"			=> "수령인",
		"recipient_phone"				=> "수령인연락처",
		"recipient_cellphone"			=> "수령인휴대폰",
		"recipient_zipcode"				=> "우편번호",
		"recipient_address_all"			=> "전체주소(지번)",
		"recipient_address_street_all"	=> "전체주소(도로명)",
		"recipient_address"				=> "주소(지번)",
		"recipient_address_street"		=> "주소(도로명)",
		"recipient_address_detail"		=> "상세주소",
		"shipping_cost"					=> "배송비",
		"goods_seq"						=> "상품고유번호",
		"hscode"							=> "수출입상품코드",
		"goods_code"						=> "바코드",
		"goods_name"						=> "상품명",
		"location_position"				=> "로케이션",
		"stock"								=> "현재고",
		"purchase_goods_name"		=> "매입용 상품명",
		"supply_price"						=> "매입가",
		"consumer_price"					=> "정가",
		"price"								=> "판매가",
		"ea_price"							=> "판매가x출고수량",
		"tax"									=> "과세여부",
		"export_ea"							=> "출고수량"
	);

	var $excelWidth = array(
		"shipping_provider"				=> 15,
		"export_code"					=> 15,
		"order_seq"						=> 20,
		"order_regist_date"				=> 25,
		"order_user_name"				=> 15,
		"userid"						=> 15,
		"order_phone"					=> 15,
		"order_cellphone"				=> 15,
		"order_email"					=> 25,
		"clearance_unique_personal_code"=> 15,
		"memo"							=> 15,
		"admin_memo"					=> 15,
		"complete_date"					=> 15,
		"shipping_date"					=> 15,
		"export_date"					=> 15,
		"shipping_method"				=> 15,
		"delivery_company_code"			=> 15,
		"delivery_number"				=> 15,
		"status"						=> 15,
		"recipient_user_name"			=> 15,
		"recipient_phone"				=> 15,
		"recipient_cellphone"			=> 15,
		"recipient_zipcode"				=> 15,
		"recipient_address_all"			=> 70,
		"recipient_address_street_all"	=> 70,
		"recipient_address"				=> 70,
		"recipient_address_street"		=> 70,
		"recipient_address_detail"		=> 20,
		"shipping_cost"					=> 15,
		"goods_seq"						=> 15,
		"hscode"						=> 15,
		"goods_code"					=> 15,
		"goods_name"					=> 70,
		"location_position"				=> 15,
		"stock"							=> 15,
		"purchase_goods_name"			=> 15,
		"supply_price"					=> 15,
		"consumer_price"				=> 15,
		"price"							=> 15,
		"ea_price"						=> 15,
		"tax"							=> 15,
		"export_ea"						=> 15,
		"optiontitle"					=> 15,
		"suboptiontitle"				=> 15,
		"subinputoption"				=> 15
	);

	var $requireds = array(
		"export_code",
		"complete_date",
		"delivery_company_code",
		"delivery_number"
	);

	var $temp = array(
		"optiontitle"		=> "필수옵션",
		"suboptiontitle"	=> "추가옵션",
		"subinputoption"	=> "추가입력옵션"
	);
	var $temp_arr = array(
		"optiontitle",
		"suboptiontitle",
		"subinputoption"
	);

	public function excel_cell($count){
		$cell =$count;
		$char = 26;
		for($i=0;$i<$cell;$i++) {
			if($i<$char) $alpha[] = $this->cell[$i];
			else {
				$idx1 = (int)($i-$char)/$char;
				$idx2 = ($i-$char)%$char;
				$alpha[] = $this->cell[$idx1].$this->cell[$idx2];
			}
		}
		return $alpha;
	}
	public function excel_num($column){
		$cell =100;
		$char = 26;
		for($i=0; $i<$cell; $i++) {
			if($i < $char){
				$alpha[] = $this->cell[$i];
				if($column==$this->cell[$i]) return $i;
			}else {
				$idx1 = (int)($i-$char)/$char;
				$idx2 = ($i-$char)%$char;
				$alpha[] = $this->cell[$idx1].$this->cell[$idx2];
				if($column==$this->cell[$idx1].$this->cell[$idx2]) return $i;
			}

		}
	}

	//필수항목 체크
	public function requiredsck($titleitems, $type='down'){
		for($i=0;$i<count($this->requireds);$i++) {
			if( in_array($this->requireds[$i], $titleitems ) )
				$requiredsnum++;//
		}
		if($requiredsnum != count($this->requireds)){
			if($type == "upload") {
				return false;
			}else{
				openDialogAlert('다운로드 양식의 필수항목이 빠져 있습니다.<br/>다운로드 양식을 다시한번 확인해 주세요.',600,140,'parent','');
				exit;
			}
		}
		return true;
	}

	public function get_item($export_code, $order_seq){
		$this->load->model('ordermodel');
		$this->load->model('goodsmodel');
		$tax_title = array("tax"=>"과세", "exempt"=>"비과세");		// 과세/비과세
		if	($order_seq == 'bundle'){
			$where		= " WHERE C.bundle_export_code = ? ";
			$binds[]	= $export_code;
		}else{
			$where		= " WHERE C.export_code = ? AND D.order_seq = ? ";
			$binds[]	= $export_code;
			$binds[]	= $order_seq;
		}
		$query = "
			SELECT
				D.*,
				(select purchase_goods_name from fm_goods where goods_seq = D.goods_seq) as purchase_goods_name,
				C.option_seq,
				C.suboption_seq
			FROM
				fm_order_item D
				LEFT JOIN fm_goods_export_item as C ON C.item_seq = D.item_seq
			" . $where;
		if( defined('__SELLERADMIN__') === true ){
			$sql .= " and D.provider_seq='{$this->providerInfo['provider_seq']}'  ";
		}
		$query = $this->db->query($query,$binds);
		foreach($query->result_array() as $data){
			$items[] = $data;
		}
		foreach($items as $key=>$item){
			unset($totitem);
			if($item['option_seq']) {
				$optwhere = array("item_option_seq='".$item['option_seq']."' ");
				unset($options,$subinputoptions);
				$options 	= $this->ordermodel->get_option_for_item($item['item_seq'],$optwhere);
				$subinputoptions = $this->ordermodel->get_input_for_item($item['item_seq']);

				$tot['hscode'] .= $item['hscode'].',';

				if($options) foreach($options as $k => $data) {
					$data['out_supply_price'] = $data['supply_price']*$data['ea'];
					$data['out_consumer_price'] = $data['consumer_price']*$data['ea'];
					$data['out_price'] = $data['price']*$data['ea'];

					//promotion sale
					$data['out_member_sale']				= $data['member_sale']*$data['ea'];
					$data['out_coupon_sale']				= ($data['download_seq'])?$data['coupon_sale']:0;
					$data['out_fblike_sale']					= $data['fblike_sale'];
					$data['out_mobile_sale']					= $data['mobile_sale'];
					$data['out_promotion_code_sale']	= $data['promotion_code_sale'];

					$data['out_reserve'] = $data['reserve']*$data['ea'];
					$data['out_point'] = $data['point']*$data['ea'];

					$data['step_complete'] = $data['step45']+$data['step55']+$data['step65']+$data['step75'];
					$options[$k] = $data;

					$tot['ea'] += $data['ea'];
					$tot['supply_price'] += $data['out_supply_price'];
					$tot['consumer_price'] += $data['out_consumer_price'];
					$tot['price'] += $data['out_price'];

					//promotion sale
					$tot['member_sale'] += $data['out_member_sale'];
					$tot['coupon_sale'] += $data['out_coupon_sale'];
					$tot['fblike_sale'] += $data['out_fblike_sale'];
					$tot['mobile_sale'] += $data['out_mobile_sale'];
					$tot['promotion_code_sale'] += $data['out_promotion_code_sale'];

					$tot['reserve'] += $data['out_reserve'];
					$tot['point'] += $data['out_point'];

					// 재고 가져오기
					if	($this->scm_cfg['use'] == 'Y' && $this->providerInfo['provider_seq'] == '1'){
						unset($sc);
						if	($data['option_seq'] > 0){
							$sc['option_seq']				= $data['option_seq'];
							$sc['goods_seq']				= $data['goods_seq'];
							$sc['option_type']				= 'option';
							$sc['get_type']					= 'wh';
							$sc['wh_seq']					= $warehouse;
							list($wh_data)					= $this->scmmodel->get_location_stock($sc);
							$data['location_position']	= $wh_data['location_position'];
							$stock							= $data['ea'];
							$data['stock']					= $stock;
						}
					}else{
						$stock = $this->goodsmodel->get_goods_option_stock($data['goods_seq'],$data['option1'],$data['option2'],$data['option3'],$data['option4'],$data['option5']);
						$data['stock'] = $stock;
					}

					$tot['real_stock'] += $real_stock;

					$tot['stock'] += $stock;
					$tot['location_position'] .= $data['location_position'].',';

					$tot['goods_code']	.= $data['goods_code'].', ';//@주문상품코드
					$tot['title1']			= $data['title1'];
					$tot['option1']		= $data['option1'];
					$tot['title2']			= $data['title2'];
					$tot['option2']		= $data['option2'];
					$tot['title3']			= $data['title3'];
					$tot['option3']		= $data['option3'];
					$tot['title4']			= $data['title4'];
					$tot['option4']		= $data['option4'];
					$tot['title5']			= $data['title5'];
					$tot['option5']		= $data['option5'];

					if($data['title1']) $totitem['option'] .= ($totitem['option'])?', '.$tot['title1'].':'.$tot['option1']:$tot['title1'].':'.$tot['option1'];
					if($data['title2']) $totitem['option'] .= ', '.$tot['title2'].':'.$tot['option2'];
					if($data['title3']) $totitem['option'] .= ', '.$tot['title3'].':'.$tot['option3'];
					if($data['title4']) $totitem['option'] .= ', '.$tot['title4'].':'.$tot['option4'];
					if($data['title5']) $totitem['option'] .= ', '.$tot['title5'].':'.$tot['option5'];
				}

				if($subinputoptions) foreach($subinputoptions as $data){
					if($data['title']) $totitem['inputoption'] .= ($totitem['inputoption'])?', '.$data['title'].':'.$data['value']:$data['title'].':'.$data['value'];
				}
			}

			if($item['suboption_seq']) {
				$suboptwhere = array("item_suboption_seq='".$item['suboption_seq']."' ");
				unset($suboptions);
				$suboptions = $this->ordermodel->get_suboption_for_item($item['item_seq'],$suboptwhere);

				if($suboptions) foreach($suboptions as $data){
					$data['out_supply_price'] = $data['supply_price']*$data['ea'];
					$data['out_consumer_price'] = $data['consumer_price']*$data['ea'];
					$data['out_price'] = $data['price']*$data['ea'];
					$data['out_reserve'] = $data['reserve']*$data['ea'];
					$data['out_point'] = $data['point']*$data['ea'];

					$suboptions[$k] = $data;
					$tot['ea'] += $data['ea'];
					$tot['supply_price'] 	+= $data['out_supply_price'];
					$tot['consumer_price'] 	+= $data['out_consumer_price'];
					$tot['price'] 			+= $data['out_price'];

					$tot['reserve'] 		+= $data['out_reserve'];
					$tot['point'] 			+= $data['out_point'];

					$stock = $this->goodsmodel->get_goods_suboption_stock($data['goods_seq'],$data['title'],$data['suboption']);
					
					$tot['stock'] 				+= $stock;
					$tot['goods_code']		.= $data['goods_code'].', ';//@주문상품코드
					$tot['subtitle']			= $data['title'];
					$tot['suboption']		= $data['suboption'];

					if($data['title']) {
						$totitem['suboption'] .= ($totitem['suboption'])?', '.$tot['subtitle'].':'.$tot['suboption']:$tot['subtitle'].':'.$tot['suboption'];
					}
				}
			}
			$tot['item_seq']			= $item['item_seq'];
			if($totitem) {
				$tot['goods_name']		.= $item['goods_name'].'(';
				if($totitem['option']){
					$tot['goods_name']		.= $totitem['option'];
				}

				if($totitem['suboption']){
					$tot['goods_name']		.= ($totitem['option'])?' + '.$totitem['suboption']:$totitem['suboption'];
				}

				if($totitem['inputoption']){
					$tot['goods_name']		.= ($totitem['option'] || $totitem['suboption'])?' + '.$totitem['inputoption']:$totitem['inputoption'];
				}

				$tot['goods_name']		.= '), ';
			}else{
				$tot['goods_name']		.= $item['goods_name'].', ';//@주문상품
			}

			$tot['purchase_goods_name'] .= $item['purchase_goods_name'].', ';//@주문상품 도매상품명
			$tot['tax'] .= $tax_title[$item['tax']].', ';// 과세/비과세

			$tot['goods_seq']		.= $item['goods_seq'].', ';//@주문상품
			$tot['count']				= count($items);

			$item['suboptions']	= $suboptions;
			$item['options']	= $options;
			$item['subinputoptions']	= $subinputoptions;
			$items[$key] 		= $item;
			$tot['goods_shipping_cost']	+= $item['goods_shipping_cost'];
		}
		return $tot;
	}



	public function get_export_ea($export_code){
		$code_fld	= 'export_code';
		if	($export_type == 'bundle')	$code_fld	= 'bundle_export_code';

		$query = "
			SELECT
				sum(B.ea) as export_ea
			FROM
				fm_goods_export A LEFT JOIN fm_goods_export_item B ON A.export_code = B.export_code
			WHERE
				A." . $code_fld . "=?";
		$query = $this->db->query($query,array($export_code));
		$res = $query->row_array();
		return $res['export_ea'];
	}

	public function get_refund_item($order_seq){
		$query = "
			SELECT
				*
			FROM
				fm_order_refund A LEFT JOIN fm_order_refund_item B ON A.refund_code = B.refund_code
			WHERE
				A.order_seq=?";
		$query = $this->db->query($query,array($order_seq));
		foreach($query->result_array() as $data){
			$items['refund_ea'] += $data['ea'];
		}
		return $items;
	}

	public function get_item_option($seq){
		$datas = get_data("fm_order_item_option ",array("item_option_seq"=>$seq));
		return $datas[0];
	}

	public function get_sub_option($seq){
		$datas = get_data("fm_order_item_suboption ",array("item_suboption_seq"=>$seq));
		return $datas[0];
	}

	public function get_refund_items($order_seq, $item_seq){
		$query = "
			SELECT
				*
			FROM
				fm_order_refund A LEFT JOIN fm_order_refund_item B ON A.refund_code = B.refund_code
			WHERE
				A.order_seq=? AND B.item_seq=?";
		$query = $this->db->query($query,array($order_seq,$item_seq));
		foreach($query->result_array() as $data){
			$items['refund_ea'] += $data['ea'];
		}
		return $items;
	}


	public function create_excel_list($criteria, $export_code){
		$criteria = strtoupper($criteria);

		$this->scm_cfg	= config_load('scm');
		$this->load->library('pxl');
		$this->load->model('providermodel');
		$this->load->model('goodsmodel');
		$this->load->model('ordershippingmodel');
		$this->load->model('scmmodel');
		$this->arr_payment = config_load('payment');
		if( defined('__SELLERADMIN__') === true ){
			$datas = get_data("fm_exceldownload",array("gb"=>"EXPORT","provider_seq"=>$this->providerInfo['provider_seq']));
		}else{
			$datas = get_data("fm_exceldownload",array("gb"=>"EXPORT","provider_seq"=>'1'));
		}
		$title_items = explode("|",$datas[0]['item']);
		$this->requiredsck($title_items);//필수항목체크

		$order_arr = explode("|",$export_code);

		// 개인정보 조회 로그 모델 로드
		$this->load->model('logPersonalInformation');

		for($i=0;$i<count($order_arr)-1;$i++){
			if(!$order_arr[$i]) continue;
			if($criteria=='EXPORT'){//출고번호별

				$export_field	= (preg_match('/^B/', $order_arr[$i])) ? 'bundle_export_code' : 'export_code';

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
					if(bundle_export_code REGEXP '^B', bundle_export_code, export_code) AS group_export_code
					FROM
						fm_goods_export A
						left join fm_order B on A.order_seq = B.order_seq
					WHERE
						A.{$export_field} = '{$order_arr[$i]}'
					GROUP BY group_export_code
					";
				$query = $this->db->query($sql);
				foreach ($query->result_array() as $row){

					$export_type	= 'export';
					if	(preg_match('/^B/', $order_arr[$i]))	$export_type	= 'bundle';

					// 배송사명
					if( !$arr_provider_name[$row['shipping_provider_seq']] ){
						$data_provider = $this->providermodel-> get_provider_one($row['shipping_provider_seq']);
						$arr_provider_name[$row['shipping_provider_seq']] = $data_provider['provider_name'];
					}
					$row['shipping_provider'] = $arr_provider_name[$row['shipping_provider_seq']];

					// 배송비
					$params = array(
						'order_seq'			=> $row['order_seq'],
						'shipping_group'	=> $row['shipping_group']
					);
					$data_order_shipping = $this->ordershippingmodel->get_shipping_only($params)->row_array();
					$row['shipping_cost'] = (float) $data_order_shipping['shipping_cost'];

					//묶음배송은 묶음배송 번호로 처리
					$row['export_code']		= ($export_type == 'bundle') ? $row['bundle_export_code'] : $row['export_code'];

					// 개인정보 조회 로그
					//'member', 'memberlist', 'order', 'export', 'return', 'refund', 'orderprint' 'orderexcel', 'exportexcel'
					$this->logPersonalInformation->insert('exportexcel',$this->managerInfo['manager_seq'],$row['export_seq']);

					if($row['member_seq']){
						$member = get_data("fm_member",array("member_seq"=>$row['member_seq']));
						if($member) $row['userid'] = $member[0]['userid'];
					}

					// 카카오페이 표기 수정 :: 2015-03-05 lwh
					if($row['pg']=='kakaopay')
							$row['payment']	= '카카오페이';
					else	$row['payment']	= $this->arr_payment[$row['payment']];

					$items		= ($export_type == 'bundle') ? $this->get_item($row['export_code'], 'bundle') : $this->get_item($row['export_code'], $row['order_seq']);

					//여러개인경우 콤마구분 (명칭, 명칭, )
					$row['goods_name']	= substr($items['goods_name'],0,-2);
					$row['tax']			= substr($items['tax'],0,-2);  // 과세/비과세
					$row['purchase_goods_name']	= substr($items['purchase_goods_name'],0,-2);
					$row['goods_code']		= substr($items['goods_code'],0,-2);
					$row['goods_seq']		= substr($items['goods_seq'],0,-2);
					$row['ea']					= $items['ea'];
					$row['goods_code']		= substr($items['goods_code'],0,-2);//상품코드
					$row['hscode']				= substr($items['hscode'],0,-2);//수출입상품코드
					$row['location_position']	= $items['location_position'];//수출입상품코드
					$row['stock']					= $items['stock'];//수출입상품코드


					$row['export_ea']			= $this->get_export_ea($row['export_code'], $export_type);

					if( $row['international'] == 'international' ){
						$row['shipping_method'] = $row['international_shipping_method'];
						$row['delivery_number'] = $row['international_delivery_no'];
						$row['delivery_company_code'] = '';
						$row['recipient_address'] = $row['international_country'].' '.$row['international_town_city'].' '.$row['international_county'];
						$row['recipient_address_detail'] = $row['international_address'];
						$row['recipient_address_all'] = $row['recipient_address']." ".$row['recipient_address_detail']; //전체주소
					}else{
						$row['shipping_method'] = $row['domestic_shipping_method'];
						$row['recipient_address_all'] = ($row['recipient_address'])?$row['recipient_address']." ".$row['recipient_address_detail']:'';//전체주소(지번)
						$row['recipient_address_street_all'] = ($row['recipient_address_street'])?$row['recipient_address_street']." ".$row['recipient_address_detail']:'';//전체주소(도로명)
					}

					if($row['shipping_method']=='direct' || $row['shipping_method']=='quick'){
						$row['delivery_number']			= '';
						$row['delivery_company_code']		= '';
					}

					$row['complete_date'] = $row['complete_date'] == '0000-00-00' ? '' : $row['complete_date'];
					$row['shipping_date'] = $row['shipping_date'] == '0000-00-00' ? '' : $row['shipping_date'];

					$row['status'] = $this->exportmodel->arr_status[$row['status']];
					$data[] = $row;
				}
			}else{//상품별

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
					D.hscode
					FROM
						fm_goods_export as A
						INNER JOIN fm_order as B ON A.order_seq = B.order_seq
						INNER JOIN fm_goods_export_item as C ON A.export_code = C.export_code
						INNER JOIN fm_order_item as D ON C.item_seq = D.item_seq
					WHERE
						A.export_code = '{$order_arr[$i]}'";

				if( defined('__SELLERADMIN__') === true ){
					$sql .= " and D.provider_seq='{$this->providerInfo['provider_seq']}'  ";
				}
				$query = $this->db->query($sql);
				$tax_title = array("tax"=>"과세", "exempt"=>"비과세");

				foreach ($query->result_array() as $row){
					if($row['member_seq']){
						$member = get_data("fm_member",array("member_seq"=>$row['member_seq']));
						if($member) $row['userid'] = $member[0]['userid'];
					}

					// 배송사명
					if( !$arr_provider_name[$row['shipping_provider_seq']] ){
						$data_provider = $this->providermodel-> get_provider_one($row['shipping_provider_seq']);
						$arr_provider_name[$row['shipping_provider_seq']] = $data_provider['provider_name'];
					}
					$row['shipping_provider'] = $arr_provider_name[$row['shipping_provider_seq']];

					// 배송비
					$params = array(
							'order_seq'			=> $row['order_seq'],
							'shipping_group'	=> $row['shipping_group']
					);
					$data_order_shipping = $this->ordershippingmodel->get_shipping_only($params)->row_array();
					$row['shipping_cost'] = (float) $data_order_shipping['shipping_cost'];

					$items = array();
					unset($optiontitle,$inputoption);
					if($row['option_seq']) {
						$items = $this->get_item_option($row['option_seq']);
						/* 매입가 추가 단가로 표시하려면 주석처리 하면됨. leewh 2014-09-24 */
						$items['supply_price']		= get_cutting_price($items['supply_price']*$items['ea']);
						$items['consumer_price']	= get_cutting_price($items['consumer_price']*$items['ea']);


						## 상품가 -> 할인가 로직 추가 2016-11-15
						$items['out_price'] = ($items['price']*$items['ea']);
						//promotion sale
						$sale_data = array();
						$sale_data['out_member_sale']				= ($items['member_sale']*$items['ea']);
						$sale_data['out_coupon_sale']				= ($items['download_seq'])?$items['coupon_sale']:0;
						$sale_data['out_fblike_sale']					= $items['fblike_sale'];
						$sale_data['out_mobile_sale']					= $items['mobile_sale'];
						$sale_data['out_promotion_code_sale']		= $items['promotion_code_sale'];
						$sale_data['out_referer_sale']					= $items['referer_sale'];
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
						$items['ea_price']			= get_cutting_price($row['export_ea'] * $items['price']);

						// 재고 가져오기
						if	($this->scm_cfg['use'] == 'Y' && $row['shipping_provider_seq'] == '1'){
							unset($sc);
							if	($items['option_seq'] > 0){
								$sc['option_seq']				= $items['option_seq'];
								$sc['goods_seq']				= $items['goods_seq'];
								$sc['option_type']				= 'option';
								$sc['get_type']					= 'wh';
								$sc['wh_seq']					= $row['wh_seq'];
								list($wh_data)					= $this->scmmodel->get_location_stock($sc);
								$items['location_position']	= $wh_data['location_position'];
								$stock							= $wh_data['ea'];
								$items['stock']					= $stock;
							}
						}else{
							$stock = $this->goodsmodel->get_goods_option_stock($items['goods_seq'],$items['option1'],$items['option2'],$items['option3'],$items['option4'],$items['option5']);
							$items['stock'] = $stock;
						}
						

						if($items['title1']) $optiontitle .= ($totitem['option'])?', '.$items['title1'].':'.$items['option1']:$items['title1'].':'.$items['option1'];
						if($items['title2']) $optiontitle .= ', '.$items['title2'].':'.$items['option2'];
						if($items['title3']) $optiontitle .= ', '.$items['title3'].':'.$items['option3'];
						if($items['title4']) $optiontitle .= ', '.$items['title4'].':'.$items['option4'];
						if($items['title5'])	$optiontitle .= ', '.$items['title5'].':'.$items['option5'];

						if($optiontitle) $row['optiontitle']		= $optiontitle;

						//추가입력옵션
						$sql = "SELECT
							*
							FROM
								fm_order_item_input C
								LEFT JOIN fm_order_item B ON C.item_seq = B.item_seq
								LEFT JOIN fm_order A ON B.order_seq = A.order_seq
							WHERE
								A.order_seq = '{$row[order_seq]}' AND C.item_seq = '{$row['item_seq']}' AND C.item_option_seq = '{$row['item_option_seq']}' ";
						$query = $this->db->query($sql.' order by C.item_input_seq ');
						foreach ($query->result_array() as $rowinput){
							$inputoption .= ($inputoption)?', '.$rowinput['title'].':'.$rowinput['value']:$rowinput['title'].':'.$rowinput['value'];
						}//endforeach
						if($inputoption) $row['subinputoption']		= $inputoption;
					}
					elseif($row['suboption_seq']) {
						$items = $this->get_sub_option($row['suboption_seq']);
						/* 매입가 추가 단가로 표시하려면 주석처리 하면됨. leewh 2014-09-24 */
						$items['supply_price']		= get_cutting_price($items['supply_price']);
						$items['consumer_price']	= get_cutting_price($items['consumer_price']);

						## 상품가 -> 할인가 로직 추가 2016-11-15
						$items['out_price'] = ($items['price']*$items['ea']);
						//promotion sale
						$sale_data = array();
						$sale_data['out_member_sale']				= ($items['member_sale']*$items['ea']);
						// 할인 합계
						$sale_data['out_tot_sale'] = $sale_data['out_member_sale'];
						$items['out_sale_price']	= $items['out_price'] - $sale_data['out_tot_sale'];
						$items['price']	= ($items['out_sale_price']/$items['ea']);
						## 상품가 -> 할인가 로직 추가 2016-11-15

						$items['price']				= get_cutting_price($items['price']);
						$items['ea_price']			= get_cutting_price($row['export_ea'] * $items['price']);
						if($items['title']) {
							$row['suboptiontitle'] = $items['title'].':'.$items['suboption'];
						}

						$stock = $this->goodsmodel->get_goods_suboption_stock($items['goods_seq'],$items['title'],$items['suboption']);
						$items['ea_price']			= $stock;
					}else{
						continue;
					}

					$row['price_export_ea'] = get_cutting_price($row['export_ea'] * $items['price']);
					if ( empty($items['tax']) ) unset($items['tax']); // fm_order_item_option의 tax 없으면 unset
					$row = array_merge($row,$items);

					if( $row['international'] == 'international' ){
						$row['shipping_method']		 	= $row['international_shipping_method'];
						$row['delivery_number'] 		= $row['international_delivery_no'];
						$row['delivery_company_code']	= '';

						$row['recipient_address'] = $row['international_country'].' '.$row['international_town_city'].' '.$row['international_county'];
						$row['recipient_address_detail'] = $row['international_address'];
						$row['recipient_address_all'] = $row['recipient_address']." ".$row['recipient_address_detail']; //전체주소
					}else{
						$row['shipping_method']			= $row['domestic_shipping_method'];
						$row['delivery_number']			= $row['delivery_number'];
						$row['delivery_company_code']	= $row['delivery_company_code'];

						$row['recipient_address_all']			= ($row['recipient_address'])?$row['recipient_address']." ".$row['recipient_address_detail']:'';//전체주소(지번)
						$row['recipient_address_street_all'] = ($row['recipient_address_street'])?$row['recipient_address_street']." ".$row['recipient_address_detail']:'';//전체주소(도로명)
					}

					if($row['shipping_method']=='direct' || $row['shipping_method']=='quick'){
						$row['delivery_number']			= '';
						$row['delivery_company_code']		= '';
					}

					$row['status'] = $this->exportmodel->arr_status[$row['status']];
					$row['tax'] = $tax_title[$row['tax']];			// 과세/비과세

					// 카카오페이 표기 수정 :: 2015-03-05 lwh
					if($row['pg']=='kakaopay')
							$row['payment']	= '카카오페이';
					else	$row['payment']	= $this->arr_payment[$row['payment']];

					$row['complete_date'] = $row['complete_date'] == '0000-00-00' ? '' : $row['complete_date'];
					$row['shipping_date'] = $row['shipping_date'] == '0000-00-00' ? '' : $row['shipping_date'];

					$data[] = $row;
				}

			}

		}

		$this->excel_write($data, $title_items, $criteria);
	}

	public function create_excel_count($criteria, $export_code){
	}

	//티켓상품 > 사용내역
	public function create_excel_coupon_use(){
		$this->load->library('pxl');
		$this->arr_payment = config_load('payment');
		$title_items = array();
		$add_title_items = array();
		$order_arr = explode("|",$export_code);

		$searchdate = ($_POST['searchdate'])?$_POST['searchdate']:$_GET['searchdate'];

		// 개인정보 조회 로그 모델 로드
		$this->load->model('socialgoodsgroupmodel');
		$couponuse_total = array();
		$sql = "SELECT
			L.*,
			L.regist_date as couponuse_regist_date,
			A.*,
			CASE WHEN L.coupon_value_type = 'price' THEN '".$this->config_sytem['basic_currency']."'
			ELSE '회' END AS coupon_value_unit,
			B.member_seq,
			B.order_user_name,
			B.order_phone,
			B.order_cellphone,
			B.recipient_user_name,
			B.recipient_phone,
			B.recipient_cellphone,
			B.regist_date as order_regist_date,
			B.admin_memo,
			B.settleprice,
			B.deposit_date,
			B.payment,
			B.order_email,
			C.item_seq,
			C.option_seq,
			C.suboption_seq,
			C.ea as export_ea,
			C.coupon_value,
			C.coupon_remain_value,
			C.coupon_serial,
			(select purchase_goods_name from fm_goods where goods_seq = D.goods_seq) as purchase_goods_name,
			D.goods_seq,
			D.goods_code,
			D.tax,
			D.goods_name,
			D.social_goods_group
			FROM
				fm_goods_coupon_use_log as L
				INNER JOIN fm_goods_export as A ON A.export_code = L.export_code
				INNER JOIN fm_order as B ON A.order_seq = B.order_seq
				INNER JOIN fm_goods_export_item as C ON A.export_code = C.export_code
				INNER JOIN fm_order_item as D ON C.item_seq = D.item_seq
			WHERE
				L.confirm_user_serial is not null ";

		if( defined('__SELLERADMIN__') === true ){
			$sql .= " and D.provider_seq='{$this->providerInfo['provider_seq']}'  ";
		}

		if( $searchdate ) {
			$searchdatear = explode(" ~ ",$searchdate);
			$sql .= " and L.regist_date between '{$searchdatear[0]} 00:00:00' and '{$searchdatear[1]} 23:59:59'";
		}

		$sql.= " order by D.social_goods_group, D.goods_seq ,  L.regist_date asc ";
		$query = $this->db->query($sql);//debug_var($this->db->last_query());
		foreach ($query->result_array() as $row){$i++;
			if($row['member_seq']){
				$member = get_data("fm_member",array("member_seq"=>$row['member_seq']));
				if($member) $row['userid'] = $member[0]['userid'];
			}
			if($row['coupon_value_type'] == 'price' ) {
				$row['coupon_use_value_price'] = $row['coupon_use_value'];
				$row['coupon_remain_value_price'] = $row['coupon_value']-$row['coupon_use_value'];//$row['coupon_remain_value'];
			}else{
				$row['coupon_use_value_pass'] = $row['coupon_use_value'];
				$row['coupon_remain_value_pass'] = $row['coupon_value']-$row['coupon_use_value'];//$row['coupon_remain_value'];
			}

			$goodsseq = $row['goods_seq'];
			$sgg = $row['social_goods_group'];
			$confirm_user_serial = $row['confirm_user_serial'];
			$coupon_use_area = trim($row['coupon_use_area']);

			$query = "select * from fm_order_item_option where item_option_seq=?";
			$query = $this->db->query($query,array($row['option_seq']));
			$optionData = $query->row_array();

			if ( $row['coupon_value_type'] == 'price' ) {//금액
				$row['coupon_use_pass_price']	= get_cutting_price($row['coupon_use_value_price']);
			}else{//횟수
				$row['coupon_use_pass_price']	= (int) ($optionData['coupon_input_one'] * $row['coupon_use_value_pass']);
			}
			if( $row['address_commission'] > 0 ) {
			$row['address_commission_price'] = $row['coupon_use_pass_price']*(100-$row['address_commission'])/100;
			$row['address_commission_account'] = $row['coupon_use_pass_price'] - $row['address_commission_price'];
			}else{
				$row['address_commission'] = 0;
			}

			//티켓상품그룹
			if($row['social_goods_group']){
				$social_goods_group_data = $this->socialgoodsgroupmodel->get_data(array('select'=>' * ','group_seq'=>$row['social_goods_group']));
				$row['social_goods_group_name'] = $social_goods_group_data['name'];
			}else{
				$row['social_goods_group_name'] ="";
			}
			$s = ($s)?$s+1:2;
			if( in_array($sgg,$couponuse_total[$sgg]) ) {
				$couponuse_total[$sgg]['count']			= $s;
				$couponuse_total[$sgg]['address_commission_price']			= $couponuse_total[$sgg]['address_commission_price'] + $row['address_commission_price'];
				$couponuse_total[$sgg]['address_commission_account']		= $couponuse_total[$sgg]['address_commission_account'] + $row['address_commission_account'];
			}else{
				$couponuse_total[$sgg]['count']			= $s;
				$couponuse_total[$sgg][$sgg]			= $sgg;
				$couponuse_total[$sgg]['social_goods_group_name']			= ($row['social_goods_group_name'])?$row['social_goods_group_name']:"티켓상품그룹없음";
				$couponuse_total[$sgg]['address_commission_price']			= $row['address_commission_price'];
				$couponuse_total[$sgg]['address_commission_account']		= $row['address_commission_account'];
			}

			$data[] = $row;
		}

		$this->load->library('pxl');
		$filenames = "ticketuse_down_".date("YmdHis").".xls";
		$fields = array("order_regist_date"=>"티켓 구매일","userid"=>"회원아이디","order_user_name"=>"회원이름",
			"order_cellphone"=>"핸드폰","order_email"=>"이메일","export_code"=>"출고번호","goods_seq"=>"상품고유값",
			"goods_name"=>"상품명","coupon_serial"=>"티켓코드","social_goods_group_name"=>"티켓 그룹명",
			"couponuse_regist_date"=>"티켓사용일","confirm_user"=>"확인매장명","confirm_user_serial"=>"확인코드",
			"coupon_use_area"=>"티켓사용장소","settle"=>"티켓구매가격","coupon_value"=>"티켓금액(횟수)","coupon_use_value_pass"=>"티켓사용횟수","coupon_remain_value_pass"=>"잔여티켓횟수","coupon_use_value_price"=>"티켓사용금액","coupon_remain_value_price"=>"잔여티켓금액","coupon_use_pass_price"=>"사용금액","address_commission"=>"매장수수료율","address_commission_price"=>"매장수수료","address_commission_account"=>"매장정산금액");

		$datas = array();
		$t=2;
		foreach ($data as $k)
		{
			$items = array();
			$i=0;
			foreach ($fields as $fieldskey=>$fieldsval)
			{
				$tmpvalue = $k[$fieldskey];
				if( $fieldskey == 'coupon_value' ) {
					$tmpvalue.=$k['coupon_value_unit'];
				}
				$items[$t][$i] = $tmpvalue;
				$i++;
			}
			@ksort($items[$t]);
			$datas[] = $items;
			$t++;
		}

		//debug_var($datas);exit;
		if(!$searchdate) $searchdate = '전체';
		$this->pxl->excel_download($datas, $fields, $filenames, '티켓상품사용내역', true, $searchdate, $couponuse_total);
	}

	public function excel_write($data, $title_items, $criteria) {
		$this->load->library('pxl');
		$filenames = ($criteria=='EXPORT')?"export_down_".date("YmdHis").".xls":"export_goods_down_".date("YmdHis").".xls";

		$item_arr = $this->itemList;
		$fields = array();
		$item = array();
		foreach($title_items as $k){
			if( $k == 'option' && $criteria!='EXPORT'){
				$item = array_merge($item, $this->temp_arr);
				$fields = array_merge($fields, $this->temp);
			}else{
				if($k == 'option' || !$item_arr[$k] || ( $criteria=='EXPORT' && ((in_array($k, array('supply_price', 'consumer_price', 'price', 'ea_price')))) ) ) continue;

				//입점사에서 다운로드시 매입가 항목 제거 2018-11-20
				if( (defined('__SELLERADMIN__') === true || $this->session->userdata['provider']) && $k == "supply_price") continue;

				$item[] = $k;
				$fields[$k] = $item_arr[$k];
			}
		}
		$cell_arr = $this->excel_cell(count($item));
		$cnt = count($fields);
		$t=2;
		foreach ($data as $k)
		{
			$items = array();
			for($i=0;$i<$cnt;$i++){
				$tmpname = $item[$i];
				$tmpvalue = $k[$tmpname];
				$items[$t][$i] = $tmpvalue;
			}
			@ksort($items[$t]);
			$datas[] = $items;
			$t++;
		}

		$this->pxl->excel_download($datas, $fields, $filenames,'출고엑셀일괄다운로드');
		//$this->pxl->pxl_excel_down($datas, $fields, $filenames,'출고엑셀일괄다운로드','export');
	}


	public function excel_upload($realfilename){
		$this->load->library('pxl');
		set_time_limit(0);
		ini_set('memory_limit', '3500M');
		$cacheMethod = PHPExcel_CachedObjectStorageFactory:: cache_to_phpTemp;
		$cacheSettings = array( ' memoryCacheSize ' => '5120MB');
		PHPExcel_Settings::setCacheStorageMethod($cacheMethod, $cacheSettings);

		$this->objPHPExcel = new PHPExcel();

		$resultExcelData = array();

		if(is_file($realfilename)){
			try {
				// 업로드 된 엑셀 형식에 맞는 Reader객체를 만든다.
				$objReader = IOFactory::createReaderForFile($realfilename);
				// 읽기전용으로 설정
				if( function_exists('$objReader->setReadDataOnly()') ) {
					$objReader->setReadDataOnly(true);
				}
				// 엑셀파일을 읽는다
				$objExcel = $objReader->load($realfilename);

				// 첫번째 시트를 선택
				$objExcel->setActiveSheetIndex(0);
				$objWorksheet = $objExcel->getActiveSheet();

				$maxRow = $objWorksheet->getHighestRow();
				$maxCol = $objWorksheet->getHighestColumn();
				if($nextnum && $nextnum <= $maxRow ){
					$maxRow = $nextnum;
				}
				$colCount = $this->excel_num($maxCol) + 1;
				$cell_arr = $this->excel_cell($colCount);

				for($i=0; $i<$colCount; $i++){
					$tmp = $objWorksheet->getCell($cell_arr[$i] . "1")->getValue();
					switch($tmp){
						case "*출고번호":			$cell_export_code				= $cell_arr[$i]; break;
						case "*출고완료일":		$cell_complete_date				= $cell_arr[$i]; break;
						case "*택배사코드":		$cell_delivery_company_code		= $cell_arr[$i]; break;
						case "*운송장번호":			$cell_delivery_number			= $cell_arr[$i]; break;
					}
				}

				###
				$row_key = 0;
				$chk_cnt = 0;
				for ($i = 2 ; $i <= $maxRow ; $i++) {
					$export_code		= $objWorksheet->getCell($cell_export_code.$i)->getValue();
					$complete_date		= $objWorksheet->getCell($cell_complete_date.$i)->getValue();
					//셀서식마다 값이 달라지는 이슈로 출고완료일은 날짜형태로 읽어오기
					$complete_date = PHPExcel_Style_NumberFormat::toFormattedString($complete_date, PHPExcel_Style_NumberFormat::FORMAT_DATE_YYYYMMDD2);
					$delivery_company_code = $objWorksheet->getCell($cell_delivery_company_code.$i)->getValue();
					$delivery_number = $objWorksheet->getCell($cell_delivery_number.$i)->getValue();

					$resultExcelData[$row_key][0] = $export_code;

					if($export_code){
						$result = $this->export_update($export_code, $complete_date, $delivery_company_code, $delivery_number);
						if($result) {
							$chk_cnt++;
							$resultExcelData[$row_key][1] = '성공';
						}
					}

					$row_key++;
				}

				if($chk_cnt>0){
					$data['result']	= true;
					$data['count']	= $chk_cnt;
					$data['msg']	= $chk_cnt.'건 수정 되었습니다.';
					$data['result_excel_url']	= $this->excel_upload_result($resultExcelData);

				}else{
					$data['result']	= false;
					$data['count']	= 0;
					$data['msg']	= '수정 가능한 데이터가 없습니다.';
				}
			} catch (exception $e) {
				$data['result']	= false;
				$data['count']	= 0;
				$data['msg']	= '엑셀파일을 읽는도중 오류가 발생하였습니다.<br/><span style="color:red;">필독! 엑셀파일 저장시 확장자가 XLS 인 엑셀 97~2003 양식으로 저장해 주세요.';
			}
		}else{
			$data['result']	= false;
			$data['count']	= 0;
			$data['msg']	= '엑셀파일이 없습니다.';
		}
		return $data;
	}


	public function export_update($export_code, $complete_date, $delivery_company_code, $delivery_number){
		$this->load->model('ordermodel');
		$this->load->model('exportmodel');

		$export_field	= (preg_match('/^B/', $export_code)) ? 'bundle_export_code' : 'export_code';

		$export_chk = get_data("fm_goods_export", array($export_field=>$export_code));

		if($export_chk[0]['international']=='domestic'){
			$data['delivery_number']			= $delivery_number;
			$data['delivery_company_code']		= $delivery_company_code;
			$data['international_delivery_no']		= '';

			$shipping_method = $export_chk[0]['domestic_shipping_method'];
		}else{
			$data['international_delivery_no']	= $delivery_number;
			$data['delivery_number']			= '';
			$data['delivery_company_code']		= '';

			$shipping_method = $export_chk[0]['international_shipping_method'];
		}

		if($shipping_method=='direct' || $shipping_method=='quick'){
			$data['delivery_number']			= '';
			$data['delivery_company_code']		= '';
			$data['international_delivery_no']		= '';
		}

		//$data['export_date']				= $complete_date;//출고일
		$data['complete_date']			= $complete_date;//출고완료일

		$this->db->where($export_field,$export_code);
		$this->db->update('fm_goods_export',$data);

		# 오픈마켓 송장등록 #
		$this->load->model('openmarketmodel');
		$this->openmarketmodel->request_send_export($export_code);

		return true;
	}

	public function excel_upload_result($resultExcelData){
		$this->load->library('pxl');
		$filenames = "export_excel_upload_result_".date("YmdHis").".xls";
		$t = 2;
		$datas = array();
		foreach($resultExcelData as $row){
			$items = array();
			$items[$t] = $row;
			$datas[] = $items;
		}

		$fields['export_code'] = "출고코드";
		$fields['result'] = "처리결과";

		$fileurl = '/data/tmp/'.$filenames;
		$filepath = ROOTPATH.'data/tmp/'.$filenames;
		$result = $this->pxl->excel_download($datas, $fields, $filenames,'엑셀 일괄 업로드 처리결과',false);
		file_put_contents($filepath,$result);
		return $fileurl;
	}


	public function get_export_query_spout( $_PARAM = array('list') ){
		//limit
		if(!is_null($_PARAM['limit_s']) && !is_null($_PARAM['limit_e'])){
			$addLimit = " LIMIT {$_PARAM['limit_s']}, {$_PARAM['limit_e']}";
		}

		//query where 시작
		$goodsviewjoin = ""; //join query
		$str_where_order = " WHERE exp.status != '' AND NOT (exp.status = '45' AND exp.status = '85')";

		//배송책임
		if( $_PARAM['shipping_provider_seq'] ){
			$str_where_order .= " AND exp.shipping_provider_seq = '{$_PARAM['shipping_provider_seq']}'";
		}

		//날짜
		if( $_PARAM['regist_date'][0] && $_PARAM['regist_date'][1] && $_PARAM['date'] ){
			if($_PARAM['date'] == 'order'){
				$str_where_order .= " AND ord.regist_date BETWEEN '{$_PARAM['regist_date'][0]} 00:00:00' AND '{$_PARAM['regist_date'][1]} 23:59:59'";
			}else if($_PARAM['date'] == 'export'){
				$str_where_order .= " AND exp.export_date BETWEEN '{$_PARAM['regist_date'][0]} 00:00:00' AND '{$_PARAM['regist_date'][1]} 23:59:59'";
			}else if($_PARAM['date'] == 'regist_date'){
				$str_where_order .= " AND exp.regist_date BETWEEN '{$_PARAM['regist_date'][0]} 00:00:00' AND '{$_PARAM['regist_date'][1]} 23:59:59'";
			}else if($_PARAM['date'] == 'shipping'){
				$str_where_order .= " AND exp.shipping_date BETWEEN '{$_PARAM['regist_date'][0]} 00:00:00' AND '{$_PARAM['regist_date'][1]} 23:59:59'";
			}else if($_PARAM['date'] == 'confirm_date'){
				$str_where_order .= " AND exp.confirm_date BETWEEN '{$_PARAM['regist_date'][0]} 00:00:00' AND '{$_PARAM['regist_date'][1]} 23:59:59'";
			}
		}

		//스텝별 검색 다운로드
		if($_PARAM['status']){
			$str_where_order .= " AND exp.status = '{$_PARAM['status']}'";
		}

		//출고상태
		if(!$_PARAM['status'] && $_PARAM['export_status']){
			$aSteps = array();
			foreach($_PARAM['export_status'] as $k => $v){
				if($v == true){
					$aSteps[] = $k;
				}
			}
			$str_where_order .= " AND exp.status IN ('" . join("', '", $aSteps) . "')";
		}

		//합포장 여부
		if($_PARAM['chk_bundle_yn']){
			$str_where_order .= " AND exp.bundle_export_code LIKE 'B%'";
		}

		//구매확정
		if($_PARAM['buy_confirm']){ 
			$goodsviewjoin		.= " INNER JOIN fm_goods_export_item item ON exp.export_code = item.export_code";
			if($_PARAM['buy_confirm']['ok'] && $_PARAM['buy_confirm']['standby']){
				$str_where_order	.= " AND (item.reserve_ea = 0 or item.reserve_ea > 0)";
			} else if($_PARAM['buy_confirm']['ok']){
				$str_where_order	.= " AND item.reserve_ea = 0";
			} else if($_PARAM['buy_confirm']['standby']){
				$str_where_order	.= " AND item.reserve_ea > 0";
			}
		}

		//출고방법
		if( $_PARAM['search_shipping_nation'] ){
			if( $_PARAM['search_shipping_nation']['kr'] && $_PARAM['search_shipping_nation']['gl'] ) {
				if($_PARAM['search_shipping_method_kr'] && $_PARAM['search_shipping_method_gl']){
					$str_where_order .= " AND ((exp.international = 'domestic' AND exp.shipping_method IN ('" . join("','", $_PARAM['search_shipping_method_kr']) . "')) OR (exp.international = 'international' AND exp.shipping_method IN ('" . join("','", $_PARAM['search_shipping_method_gl']) . "')))";
				} else {
					$str_where_order .= " AND exp.international IN ('domestic', 'international') ";
				}
			}else if( $_PARAM['search_shipping_nation']['kr'] ){
				if($_PARAM['search_shipping_method_kr'] ){
					$str_where_order .= " AND (exp.international = 'domestic' AND exp.shipping_method IN ('" . join("','", $_PARAM['search_shipping_method_kr'] ) . "'))";
				} else {
					$str_where_order .= " AND exp.international =  'domestic'";
				}
			}else if( $_PARAM['search_shipping_nation']['gl'] ){
				if($_PARAM['search_shipping_method_gl']){
					$str_where_order .= " AND (exp.international = 'international' AND exp.shipping_method IN ('" . join("','", $_PARAM['search_shipping_nation']['gl']) . "'))";
				} else {
					$str_where_order .= " AND exp.international =  'international'";
				}
			}
		}

		//티켓발송
		if( $_PARAM['search_shipping_method_coupon'] ){
			$str_where_order .= " AND (exp.domestic_shipping_method =  'coupon' OR exp.shipping_method =  'coupon')";
		}

		//택배정보
		if( $_PARAM['search_delivery_company_code'] ){
			if($_PARAM['null_delivery_number']){
				$str_where_order .= " AND ( exp.delivery_company_code = '{$_PARAM['search_delivery_company_code']}' 
					AND ((exp.international =  'domestic' AND exp.shipping_method IN ('delivery',  'postpaid') AND (exp.delivery_number IS NULL OR exp.delivery_number =  '')) OR ( exp.international = 'international' AND ( exp.international_delivery_no IS NULL OR exp.international_delivery_no =  '' ))))";
			} else if($_PARAM['search_delivery_number']){
				$str_where_order .= " AND ((exp.delivery_company_code = '{$_PARAM['search_delivery_company_code']}' AND exp.delivery_number LIKE '{$_PARAM['search_delivery_number']}%'))";
			} else {
				$str_where_order .= " AND exp.delivery_company_code = '{$_PARAM['search_delivery_company_code']}'";
			}
		}

		//검색 키워드
		if($_PARAM['keyword']){ 
			$_PARAM['keyword'] = trim($_PARAM['keyword']);
			if($_PARAM['search_type'] && $_PARAM['search_type'] != "all"){
				switch($_PARAM['search_type']){
					case "ord.order_seq":
						$str_where_order .= " AND CAST( ord.order_seq AS CHAR ) LIKE '%{$_PARAM['keyword']}%'";
						break;

					case "exp.export_code":
						$str_where_order .= " AND CAST( exp.export_code AS CHAR ) LIKE '%{$_PARAM['keyword']}%'";
						break;

					case "ord.order_user_name":
						$str_where_order .= " AND CAST( ord.order_user_name AS CHAR ) LIKE '%{$_PARAM['keyword']}%'";
						break;

					case "ord.depositor":
						$str_where_order .= " AND CAST( ord.depositor AS CHAR ) LIKE '%{$_PARAM['keyword']}%'";
						break;

					case "mem.userid":
						$goodsviewjoin .= " LEFT JOIN fm_member mem ON mem.member_seq = ord.member_seq";
						$str_where_order .= " AND CAST( mem.userid AS CHAR ) LIKE '%{$_PARAM['keyword']}%'";

					case "ord.order_cellphone":
						$str_where_order .= " AND INSTR( REPLACE( ord.order_cellphone, '-', '' ), '{$_PARAM['keyword']}' )";
						break;

					case "ord.order_email":
						$str_where_order .= " AND CAST( ord.order_email AS CHAR ) LIKE '%{$_PARAM['keyword']}%'";
						break;
					
					case "ord.recipient_user_name":
						$str_where_order .= " AND CAST( ord.recipient_user_name AS CHAR ) LIKE '%{$_PARAM['keyword']}%'";
						break;

					case "ord.recipient_cellphone":
						$str_where_order .= " AND INSTR( REPLACE( ord.recipient_cellphone, '-', '' ), '{$_PARAM['keyword']}' )";
						break;
					
					case "ord.recipient_phone":
						$str_where_order .= " AND INSTR( REPLACE( ord.recipient_phone,  '-',  '' ), '{$_PARAM['keyword']}' )";
						break;

					case "oitem.goods_name":
						if (strpos($goodsviewjoin, 'fm_goods_export_item') === false) {
							$goodsviewjoin .= " INNER JOIN fm_goods_export_item item ON exp.export_code = item.export_code";
						}
						$goodsviewjoin .= " INNER JOIN fm_order_item oitem ON oitem.item_seq = item.item_seq";
						$str_where_order .= " AND CAST( oitem.goods_name AS CHAR ) LIKE '%{$_PARAM['keyword']}%'";
						break;

					case "oitem.goods_seq":
						if (strpos($goodsviewjoin, 'fm_goods_export_item') === false) {
							$goodsviewjoin .= " INNER JOIN fm_goods_export_item item ON exp.export_code = item.export_code";
						}
						$goodsviewjoin .= " INNER JOIN fm_order_item oitem ON oitem.item_seq = item.item_seq";
						$str_where_order .= " AND CAST( oitem.goods_seq AS CHAR ) LIKE '%{$_PARAM['keyword']}%'";
						break;
				}
			} else {
				if (strpos($goodsviewjoin, 'fm_goods_export_item') === false) {
					$goodsviewjoin .= " INNER JOIN fm_goods_export_item item ON exp.export_code = item.export_code";
				}
				$goodsviewjoin .= " INNER JOIN fm_order_item oitem ON oitem.item_seq = item.item_seq
									LEFT JOIN fm_member mem ON mem.member_seq = ord.member_seq";

				$str_where_order .= " AND (
										INSTR( REPLACE( ord.order_cellphone,  '-',  '' ), '{$_PARAM['keyword']}' ) 
										OR INSTR( REPLACE( ord.recipient_phone,  '-',  '' ), '{$_PARAM['keyword']}' ) 
										OR INSTR( REPLACE( ord.recipient_cellphone,  '-',  '' ), '{$_PARAM['keyword']}' ) 
										OR CAST( ord.order_seq AS CHAR ) LIKE '%{$_PARAM['keyword']}%'
										OR CAST( exp.export_code AS CHAR ) LIKE '%{$_PARAM['keyword']}%'
										OR CAST( ord.order_user_name AS CHAR ) LIKE '%{$_PARAM['keyword']}%'
										OR CAST( ord.depositor AS CHAR ) LIKE '%{$_PARAM['keyword']}%'
										OR CAST( mem.userid AS CHAR ) LIKE '%{$_PARAM['keyword']}%'
										OR CAST( ord.order_email AS CHAR ) LIKE '%{$_PARAM['keyword']}%'
										OR CAST( ord.recipient_user_name AS CHAR ) LIKE '%{$_PARAM['keyword']}%'
										OR CAST( oitem.goods_name AS CHAR ) LIKE '%{$_PARAM['keyword']}%'
										OR CAST( oitem.goods_seq AS CHAR ) LIKE '%{$_PARAM['keyword']}%'
									)";
			}
		}

		//입점사 구분
		if( $_PARAM['provider_seq'] ){
			if (strpos($goodsviewjoin, 'fm_goods_export_item') === false) {
				$goodsviewjoin .= " INNER JOIN fm_goods_export_item item ON exp.export_code = item.export_code";
			}

			if (strpos($goodsviewjoin, 'fm_order_item') === false) {
				$goodsviewjoin .= " INNER JOIN fm_order_item oitem ON oitem.item_seq = item.item_seq";
			}

			$str_where_order .= " AND oitem.provider_seq = '{$_PARAM['provider_seq']}'";
		}

		## Query 정리
		if( $str_where_order ) {
			if($_PARAM['query_type'] == 'total_record'){
				$query = "SELECT
								count(exp.export_code) as cnt
							FROM
								fm_goods_export exp
								INNER JOIN fm_order ord ON ord.order_seq = exp.order_seq 
						".$goodsviewjoin." {$str_where_order}";
			}else{
				$query = "SELECT 
								exp.export_code,
								if(exp.bundle_export_code REGEXP '^B', exp.bundle_export_code, exp.export_code) AS group_export_code
							FROM
								fm_goods_export exp
								INNER JOIN fm_order ord ON ord.order_seq = exp.order_seq 
						".$goodsviewjoin." {$str_where_order} 
							GROUP BY group_export_code
							ORDER BY exp.status ASC, exp.export_seq DESC
							{$addLimit}";
			}
			
			$queryDB = $this->db->query($query,$bind);
			return $queryDB->result_array();
		}
	}

}
/* End of file excelexport.php */
/* Location: ./app/models/excelexport */