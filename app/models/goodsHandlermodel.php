<?php
class GoodsHandlermodel extends CI_Model {

	protected $updateCount	= 100;
	protected $cfg_order		= array();
	public	$providerCoList	= array();

	public function __construct()
	{
		//$cfg_order = config_load('goods');
		//$this->load->helper('goods');
		$this->load->model('goodsmodel');

		// 상점 기본 재고정책
		$this->cfg_order		= config_load('order');
	}

	/*
		상품 핸들링
		$providerInfo === false : 본사
		$providerInfo !== false : 입점사 코드
	*/
	public function goodsHandle($inGoodsSeq, $providerInfo = false)
	{
		$this->load->model('goodsmodel');
		$this->load->model('goodssummarymodel');
		$this->load->model('countmodel');
		$this->load->model('categorymodel');
		$this->load->model('brandmodel');
		$this->load->model('locationmodel');
		$this->load->model('shippingmodel');
		$this->load->model('videofiles');
		$this->load->model('openmarketmodel');

		$goods			= $this->goodsmodel->check_param_regist();

		if ($_POST["possible_pay_type_hidden"] == 'goods') {
			$goods['possible_pay_type']		= "goods";
			$goods['possible_pay']			= $_POST["possible_pay_hidden"];
			$goods['possible_mobile_pay']	= $_POST["possible_pay_hidden"];	// 모바일만 설정은 없어짐
		} else {
			$goods['possible_pay_type']		= "shop";
			$goods['possible_pay']			= "";
			$goods['possible_mobile_pay']	= "";
		}

		if($inGoodsSeq > 0) {
			$goodsSeq	= $inGoodsSeq;
			$mode		= 'modify';
			
			// 수정시에는 이미지 관련 작업이 실시간 처리됨 :: 2016-05-09 lwh, 
			// 제디터일 경우 실시간 업데이트가 아니기떄문에 해당 처리 필요없음 에디터에 경우 실시간 처리임으로 필요
			if ($goods['mobile_deploymentId'] == '') {
				unset($goods['contents']);
				unset($goods['mobile_contents']);
				unset($goods['common_contents']);	
			}


			//unset($goods['info_seq']);	// contents는 unset 하지만, info_seq 는 저장해야함

			$goods['update_date']	= date("Y-m-d H:i:s",time());
			$actionAdminName		= ( $providerInfo === false ) ? $this->managerInfo['mname'] : $this->providerInfo['provider_name'];
			$goods['admin_log']		= "<div>".date("Y-m-d H:i:s")." 관리자(".$actionAdminName.")가 상품의 정보를 수정하였습니다. (".$_SERVER['REMOTE_ADDR'].")</div>".$_POST['admin_log'];

			$goods['goods_sub_info'] = $_POST['goodsSubInfo'];

			if ($_POST['subInfoTitle']) {
				for ($i=0; $i<count($_POST['subInfoTitle']); $i++)
					$subInfo[$_POST['subInfoTitle'][$i]] = $_POST['subInfoDesc'][$i];
			}

			$goods['sub_info_desc']				= json_encode($subInfo);

			if ($providerInfo === false) {
				$goods['sale_seq'] = $_POST["sale_seq"];
				if(!$goods['sale_seq'])
					$goods['sale_seq'] = 1;
			}

			// 변경전 상품 노출 여부
			$today		= date("Y-m-d H:i:s");
			$goodsOld	= $this->goodsmodel->get_view($goodsSeq)->row_array();
			if($goodsOld['goods_view']!='look' && $goodsOld['display_terms']=='AUTO' && $goodsOld['display_terms_begin']<=$today && $goodsOld['display_terms_end']>=$today ){
				$goodsOld['goods_view']	= 'look';
			}

			$this->db->where('goods_seq', $goodsSeq);
			$result	= $this->db->update('fm_goods', $goods);

			$this->_doModifyLink($goodsSeq, $providerInfo);	//수정 요청시 처리

			$this->db->delete('fm_goods_socialcp_cancel', array('goods_seq' => $goodsSeq));
			$this->db->delete('fm_goods_addition', array('goods_seq' => $goodsSeq));
			$this->db->delete('fm_goods_icon', array('goods_seq' => $goodsSeq));

		} else {

			// 반응형일때 모바일 상품 설명을 PC 상품설명이 빈값일때 복사해서 넣음 :: 2019-02-08 pjw
			if( $this->config_system['operation_type'] == 'light'){

				if( trim($goods['contents']) == '' || trim($goods['contents']) == '<p><br></p>' ){
					$goods['contents'] = $goods['mobile_contents'];
				}
			}

			$mode		= 'regist';
			$result	= $this->goodsRegist($goods, $providerInfo);
			$goodsSeq	= $result['goods_seq'];
			if($result['result_imagehosting']['contents']) $result_imagehosting['contents'] = $result['result_imagehosting']['contents']; // PC 상품설명
			if($result['result_imagehosting']['mobile_contents']) $result_imagehosting['mobile_contents'] = $result['result_imagehosting']['mobile_contents']; // 모바일 상품설명 (반응형)
			$this->_doRegistLink($goodsSeq, $providerInfo);	//등록 요청시 처리
		}

		// 배송그룹 적용상품 재조정 :: 2016-07-01 lwh
		$target_ship_grp_seq = array();
		if($_POST['shipping_group_seq']) {
		    $target_ship_grp_seq[] = $_POST['shipping_group_seq'];
		}
		if($_POST['old_group_seq']) {
		    $target_ship_grp_seq[] = $_POST['old_group_seq'];
		}
		if(count($target_ship_grp_seq)>0) {
		    $this->shippingmodel->group_cnt_adjust($target_ship_grp_seq);
		}

		// 판매마켓 전송 대상 몰 저장
		if ($providerInfo === false)
			$this->openmarketmodel->save_goods_send_mall($goodsSeq, $_POST['openmarket_send_mall_id']);
		// 상품 동영상
		if(count($_POST['videofiles']) > 0 ) {
		    /**
		     * $videotype= image : 상품상세페이지 상품이미지 영역에 노출
		     * $videotype= contents : 상품상세페이지 상품설명 영역에 노출
		     */
		    $sort = 0;
		    foreach($_POST['videofiles'] as $videotype => $videoList) {
		        if(count($videoList)>0) {
		            foreach($videoList as $videoseq) {
		                // 상품상세페이지는 순서 변경
		                if($videotype === 'contents') {
		                    $sort++;
		                }
		                if(!empty($videoseq)) {
		                    if($_POST['video_del'][$videotype][$videoseq] === "1") {
		                        // 연결해제
		                        $this->videofiles->videofiles_delete($videoseq);
		                    } else {

		                        // 상품 최초 등록시 이미지 저장 안되는 오류 수정
		                        if($videotype === 'image') {
		                            $videoImageData = $this->videofiles->get_data(array('seq'=>$videoseq));
		                            $this->db->where('goods_seq', $goodsSeq)->update('fm_goods', array(
		                                'file_key_w' => $videoImageData['file_key_w'],
		                                'file_key_i' => $videoImageData['file_key_i'],
		                                'videotmpcode' => $videoImageData['tmpcode'],
		                            ));
		                        }

		                        $videofiles = array(
		                            'parentseq' => $goodsSeq,
		                            'viewer_use' => ($_POST['viewer_use'][$videotype][$videoseq])?$_POST['viewer_use'][$videotype][$videoseq]:'N',
		                            'viewer_position' => ($_POST['viewer_position'][$videotype][$videoseq])?$_POST['viewer_position'][$videotype][$videoseq]:'first',
		                            'pc_width' => $_POST['pc_width'][$videotype][$videoseq],
		                            'pc_height' => $_POST['pc_height'][$videotype][$videoseq],
		                            'mobile_width' => $_POST['mobile_width'][$videotype][$videoseq],
		                            'mobile_height' => $_POST['mobile_height'][$videotype][$videoseq],
		                            'seq' => $videoseq,
		                        );

		                        // 상품상세페이지는 순서 변경
		                        if($videotype === 'contents') {
		                            $videofiles['sort'] = $sort;
								}
								$this->videofiles->videofiles_modify($videofiles);
		                    }
		                }
		            }
		        }
		    }
		}

		/* 추가 정보 */
		if	($_POST['selectEtcTitle'])foreach($_POST['selectEtcTitle'] as $key => $type){
			$cnt		= 0;
			if($type != 'direct'){
				$query	= "SELECT count(*) as cnt FROM `fm_goods_addition` WHERE `goods_seq`=? and `type`=?";
				$query	= $this->db->query($query,array($goodsSeq,$type));
				$row	= $query->row();
				$cnt	= $row->cnt;
			}

			if($cnt == 0){
				$additionSeq				= (int) $_POST['additionSeq'][$key];
				$additions['addition_seq']	= $additionSeq;
				$additions['goods_seq']		= $goodsSeq;
				$additions['code_seq']		= str_replace("goodsaddinfo_","",$type);
				$additions['type']			= $type;

				if( $_POST['etcTitle'][$key]) {
					$additions['title']		= (strstr($type,"goodsaddinfo_") && strstr($_POST['etcTitle'][$key]," [코드]")) ? str_replace(" [코드]","",$_POST['etcTitle'][$key]) : $_POST['etcTitle'][$key];
				}else{
					$query					= "SELECT label_title FROM `fm_goods_code_form` WHERE `codeform_seq`=?";
					$code_formquery			= $this->db->query($query,array($additions['code_seq']));
					$code_formdata			= $code_formquery->row();
					$additions['title']		= $code_formdata->label_title;
				}

				$additions['contents_title']= $_POST['etcContents_title'][$key];
				$additions['contents']		= $_POST['etcContents'][$key];
				$additions['linkage_val']	= $_POST['linkageOrigin'][$key];

				$result = $this->db->insert('fm_goods_addition', $additions);
			}
		}

		/* 아이콘 */
		foreach((array)$_POST['goodsIcon'] as $key => $goodsIcon){

			$icons['end_date']			= "";
			$icons['start_date']		= "";

			$icons['start_date']	= $_POST['iconStartDate'][$key];
			$icons['end_date']		= $_POST['iconEndDate'][$key];

			$icons['goods_seq']			= $goodsSeq;
			$icons['codecd']			= $goodsIcon;
			$result = $this->db->insert('fm_goods_icon', $icons);
		}

		//티켓상품이면
		if($_POST['goods_kind'] == 'coupon' ){
			unset($socialcpcancels);

			if( $_POST['socialcp_cancel_type'] == 'payoption' ) {
				foreach( $_POST['socialcp_cancel_day'] as $key => $socialcp_cancel) {
					$socialcpcancels['goods_seq']				= $goodsSeq;
					$socialcpcancels['socialcp_cancel_type']	= $_POST['socialcp_cancel_type'];
					$socialcpcancels['socialcp_cancel_day']		= $_POST['socialcp_cancel_day'][$key];
					$socialcpcancels['socialcp_cancel_percent']	= $_POST['socialcp_cancel_percent'][$key];
					$socialcpcancels['regist_date']				= date('Y-m-d H:i:s',time());

					$result = $this->db->insert('fm_goods_socialcp_cancel', $socialcpcancels);
				}
			}else{
				$socialcpcancels['goods_seq']					= $goodsSeq;
				$socialcpcancels['socialcp_cancel_type']		= $_POST['socialcp_cancel_type'];
				$socialcpcancels['socialcp_cancel_day']			= ($_POST['socialcp_cancel_day'][0])?$_POST['socialcp_cancel_day'][0]:0;
				$socialcpcancels['socialcp_cancel_day']			= ($_POST['socialcp_cancel_type'] == 'pay'  && $_POST['socialcp_cancel_day'][0])?$_POST['socialcp_cancel_day'][0]:0;
				$socialcpcancels['socialcp_cancel_percent']		= 100;//percent
				$socialcpcancels['regist_date']					= date('Y-m-d H:i:s',time());

				$result = $this->db->insert('fm_goods_socialcp_cancel', $socialcpcancels);
			}
		}


		if ($_POST['optionUse'] == '1') {

			//옵션이 있을경우
			if ($_POST['tmp_option_seq'])
				$this->goodsmodel->moveTmpToOption($goodsSeq, $_POST['tmp_option_seq']);

		} else {

			//옵션이 없을경우
			$defaultOptions	= explode(',',$_POST['defaultOption']);
			for ($i=0; $i < 5; $i++) {
				for ($j=0; $j < count($_POST['price']); $j++) {
					$tmp				= 'opt'.$i;
					${$tmp}[$j]			= "";
					$tmpcode			= 'optcode'.$i;
					${$tmpcode}[$j]		= "";

					if (isset($_POST['opt'][$i][$j])) {
						${$tmpcode}[$j]	= $_POST['optcode'][$i][$j];
						${$tmp}[$j]		= $_POST['opt'][$i][$j];
						$comp_opt[$j][]	= $_POST['opt'][$i][$j];
					}
				}
			}

			$tmpKeys		= array_keys($_POST['price']);
			$key			= $tmpKeys[0];
			$optionSeq		= (int) $_POST['optionSeq'][$key];
			$price			= $_POST['price'][$key];
			$defaultOption	= 'n';

			if ($_POST['defaultOption'] == implode(',',$comp_opt[$key]))
				$defaultOption = 'y';

			if (!$_POST['reserveUnit'][$key])
				$_POST['reserveUnit'][$key] = "percent";

			$options['goods_seq'] 			= $goodsSeq;
			$options['default_option']		= $defaultOption;
			$options['option_title']		= implode(',',$_POST['optionTitle']);

			$options['code_seq']			= ($_POST['optionType']) ? str_replace("goodsoption_","",implode(',',$_POST['optionType'])) : '';
			$options['option_type']			= ($_POST['optionType']) ? implode(',',$_POST['optionType']) : 'direct';


			$options['option1']				= $opt0[$key];
			$options['option2']				= $opt1[$key];
			$options['option3']				= $opt2[$key];
			$options['option4']				= $opt3[$key];
			$options['option5']				= $opt4[$key];

			$options['optioncode1']			= $optcode0[$key];
			$options['optioncode2']			= $optcode1[$key];
			$options['optioncode3']			= $optcode2[$key];
			$options['optioncode4']			= $optcode3[$key];
			$options['optioncode5']			= $optcode4[$key];

			$options['infomation'] 			= $_POST['infomation'][$key];

			$options['weight']				= $_POST['weight'][$key];
			$options['option_view']			= ($_POST['option_view'][$key] == 'N') ? 'N' : 'Y';

			$_POST['commissionType'][$key]	= (!empty($_POST['commissionType'][$key])) ? $_POST['commissionType'][$key] : '';

			$options['coupon_input'] 		= get_cutting_price($_POST['coupon_input'][$key]);
			$options['consumer_price']		= get_cutting_price($_POST['consumerPrice'][$key]);
			$options['price'] 				= get_cutting_price($_POST['price'][$key]);

			if (defined('__SELLERADMIN__') === true) {
				// 입점사 상품 수정 시 : param 아예 저장 안함
				// 입점사 상품 등록 시 : 기본 마일리지 정책
				if ($inGoodsSeq == 0) {
					$cfg_reserve = ($this->reserves) ? $this->reserves : config_load('reserve');
					$options['reserve_unit'] = 'percent';
					$options['reserve_rate'] = $cfg_reserve['default_reserve_percent'];
					$options['reserve'] = get_cutting_price($options['price'] * $options['reserve_rate'] / 100);
				}
			} else {
				// 본사는 post 넘어온대로 저장
				$options['reserve_unit'] = $_POST['reserveUnit'][$key];
				$options['reserve_rate'] = get_cutting_price($_POST['reserveRate'][$key]);
				$options['reserve'] = get_cutting_price($_POST['reserve'][$key]);
			}


			if ($providerInfo === false) {
				//본사 상품
				$options['commission_rate']	= ($_POST['commissionType'][$key] != 'SUPR') ? pfloor($_POST['commissionRate'][$key]*100)/100 : round($_POST['commissionRate'][$key]);
				$options['commission_type']	= ($_POST['provider_seq'] == 1 || $_POST['default_commission_type'] == 'SACO') ? 'SACO' : $_POST['commissionType'][$key];
			} else {
				//입점사 상품
				if($_POST['commissionRate'][$key] && $_POST['commissionRate'][$key] != '') {
					$options['commission_rate']	= ($_POST['commissionType'][$key] != 'SUPR') ? pfloor($_POST['commissionRate'][$key]*100)/100 : round($_POST['commissionRate'][$key]);
				} else {
					$options['commission_rate'] = ($providerInfo['commission_type'] != 'SUPR') ? pfloor($providerInfo['charge']*100)/100 : round($providerInfo['charge']);
				}
				$options['commission_type'] = $_POST['commissionType'][$key];
			}


			$options['package_count']		= (int) $_POST['reg_package_count'];

			for($package_num=1; $package_num < 6; $package_num++){
				$package_option_seq			= $_POST['reg_package_option_seq'.$package_num][$key];
				$package_unit_ea			= $_POST['package_unit_ea'.$package_num][$key];

				$options['package_goods_name'.$package_num] = '';
				$options['package_option_seq'.$package_num] = 0;
				$options['package_option'.$package_num]		= '';
				$options['package_unit_ea'.$package_num]	= 0;
				if($package_option_seq){
					$data_package			= $this->goodsmodel->get_option_package_info($package_option_seq);
					$options['package_goods_name'.$package_num]	= $data_package['goods_name'];
					$options['package_option_seq'.$package_num]	= $package_option_seq;
					$options['package_option'.$package_num]		= option_to_package_str(array($data_package['option1'],$data_package['option2'],$data_package['option3'],$data_package['option4'],$data_package['option5']));
					$options['package_unit_ea'.$package_num]	= $package_unit_ea;
				}
			}

			$optionSeq						= ($mode == 'modify') ? (int) $_POST['optionSeq'][$key] : 0;

			$supply_price		= get_cutting_price($_POST['supplyPrice'][$key]);
			if ($mode == 'modify' && $optionSeq > 0) {

				$this->db->where('option_seq', $optionSeq);
				$this->db->update( 'fm_goods_option', $options );

				$supplys 						= array();
				if	($this->scm_cfg['use'] != 'Y' || $_POST['provider_seq'] != 1 ){
					$supplys['supply_price'] 	= $supply_price;
					$supplys['stock'] 			= $_POST['stock'][$key];
					$supplys['badstock'] 		= $_POST['badstock'][$key];
				}
				$supplys['safe_stock']			= $_POST['safe_stock'][$key];
				$supplys['reservation15']		= $_POST['reservation15'][$key];
				$supplys['reservation25']		= $_POST['reservation25'][$key];
				$this->db->where(array('goods_seq' => $goodsSeq, 'option_seq' => $optionSeq));
				$this->db->update( 'fm_goods_supply', $supplys );

			} else {

				//수정일경우 기존 옵션 삭제
				if ($mode == 'modify')
					$this->goodsmodel->delete_option_info($goodsSeq);

				$result		= $this->db->insert( 'fm_goods_option', $options );

				$supplys 						= array();
				$supplys['goods_seq'] 			= $goodsSeq;
				$supplys['option_seq'] 			= $this->db->insert_id();

				if	($this->scm_cfg['use'] != 'Y' || $_POST['provider_seq'] != 1 ){
					$supplys['supply_price'] 		= $supply_price;
					$supplys['stock'] 				= (int) $_POST['stock'][$key];
					$supplys['badstock'] 			= (int) $_POST['badstock'][$key];
				}else{
					$supplys['supply_price']		= '0';
					$supplys['stock']				= '0';
					$supplys['badstock']			= '0';
				}
				$supplys['safe_stock'] 			= (int) $_POST['safe_stock'][$key];
				$supplys['reservation15']		= (int) $_POST['reservation15'][$key];
				$supplys['reservation25']		= (int) $_POST['reservation25'][$key];

				$this->db->insert( 'fm_goods_supply', $supplys );
			}

		}

		/* 총재고 수량 입력 */
		$this->goodsmodel->total_stock($goodsSeq);


		if ($mode == 'modify')
			$result		= $this->_doModifyOthers($goodsSeq, $goods, $providerInfo);	//수정 요청시 처리
		else
			$result		= $this->_doRegistOthers($goodsSeq, $goods, $providerInfo);	//등록 요청시 처리

		//이미지호스팅 업로드 결과
		if($result['result_imagehosting']['goods_image']) $result_imagehosting['goods_image'] = $result['result_imagehosting']['goods_image']; // 상품이미지
		if($result['result_imagehosting']['common_info']) $result_imagehosting['common_info'] = $result['result_imagehosting']['common_info']; // 공용정보
		if($result_imagehosting){
			$this->load->library('imagehostinglibrary');
			$imagehostingMassage = $this->imagehostinglibrary->message($result_imagehosting);
		}
		// 본사상품 처리
		if ($providerInfo === false) {

			// 판매마켓 전송 요청
			if ($_POST['goods_type'] != 'gift'){

				// 할인혜택 금액 저장
				$this->goodssummarymodel->set_event_price(array('goods'=>array($goodsSeq)));

				$this->openmarketmodel->request_send_goods($goodsSeq);
			}

		} else if ($_POST['goods_type']!='gift') {

			// 할인혜택 금액 저장
			$this->goodssummarymodel->set_event_price(array('goods'=>array($goodsSeq)));

		}

		// 외부 티켓상품 저장
		if ($goods['coupon_serial_type'] == 'n' && $_POST['coupon_serial_upload']) {
			$coupon_serial_list		= explode(',', $_POST['coupon_serial_upload']);
			foreach ($coupon_serial_list as $k => $list){
				$data	= explode('|', $list);
				if ($data[0] && $data[1] == 'y'){
					if (!$this->goodsmodel->chkDuple_coupon_serial($data[0])){
						$this->db->insert('fm_goods_coupon_serial', array('coupon_serial'=>$data[0],'goods_seq'=>$goodsSeq,'regist_date'=>date('Y-m-d H:i:s')));
					}
				}
			}
			// 디비 저장 후 임시 업로드 엑셀 파일 삭제
			@unlink(ROOTPATH . "data/tmp/coupon_serial_upload.xls");
		}

		// 상품 수정시 연동상품 수정 체크
		if ($mode == 'modify') {
			$this->load->library('Connector');
			$goodsService	= $this->connector::getInstance('goods');
			$goodsService->doMarketGoodsUpdate($goodsSeq);	//Queue 로 처리
		}

		return ["goodsSeq" => $goodsSeq, "imagehosting" => $imagehostingMassage ];
	}

	//기본 상품 등록
	public function goodsRegist($goods, $providerInfo){

		// 기본값 정의
		$goods['regist_date']			= date("Y-m-d H:i:s",time());
		$goods['update_date']			= $goods['regist_date'];

		// _POST값 처리
		if($_POST['goodsSubInfo']!=='')	$goods['goods_sub_info']	= $_POST['goodsSubInfo'];
		if($_POST['subInfoTitle']) {
			for($i=0; $i<count($_POST['subInfoTitle']); $i++)
				$subInfo[$_POST['subInfoTitle'][$i]]	= $_POST['subInfoDesc'][$i];
		}
		$goods['sub_info_desc']		= json_encode($subInfo);
		$goods['sale_seq']			= $_POST["sale_seq"];

		// 필수값 기본값으로 채움 처리 ( null exception error 방지 )
		$require_fld_list	= array(
			'goods_kind'							=> 'goods',
			'provider_seq'							=> '1',
			'view_layout'							=> 'basic',
			'goods_type'							=> 'goods',
			'goods_status'							=> 'unsold',
			'goods_view'							=> 'notLook',
			'favorite_chk'							=> 'none',
			'goods_name'							=> '',
			'info_seq'								=> '0',
			'string_price_use'						=> '0',
			'string_price_link_target'				=> 'NEW',
			'member_string_price_use'				=> '0',
			'member_string_price_link_target'		=> 'NEW',
			'allmember_string_price_use'			=> '0',
			'allmember_string_price_link_target'	=> 'NEW',
			'string_button_use'						=> '0',
			'member_string_button_use'				=> '0',
			'allmember_string_button_use'			=> '0',
			'allmember_string_button_link_target'	=> 'NEW',
			'member_string_button_link_target'		=> 'NEW',
			'string_button_link_target'				=> 'NEW',
			'tax'									=> 'tax',
			'multi_discount_use'					=> '0',
			'multi_discount'						=> '0',
			'min_purchase_limit'					=> 'unlimit',
			'max_purchase_limit'					=> 'unlimit',
			'reserve_policy'						=> 'shop',
			'sub_reserve_policy'					=> 'shop',
			'option_use'							=> '0',
			'option_view_type'						=> 'divide',
			'option_suboption_use'					=> '0',
			'member_input_use'						=> '0',
			'shipping_policy'						=> 'shop',
			'goods_shipping_policy'					=> 'unlimit',
			'shipping_weight_policy'				=> 'shop',
			'relation_type'							=> 'AUTO',
			'relation_count_w'						=> '4',
			'relation_count_h'						=> '1',
			'purchase_ea'							=> '0',
			'page_view'								=> '0',
			'review_count'							=> '0',
			'restock_notify_use'					=> '0',
			'regist_date'							=> date('Y-m-d H:i:s'),
			'update_date'							=> date('Y-m-d H:i:s'),
			'video_type'							=> '',
			'video_size'							=> '',
			'video_size_mobile'						=> '',
			'video_view_type'						=> '3',
			'videotmpcode'							=> '',
			'provider_status'						=> '0',
			'possible_pay_type'						=> 'shop',
			'package_yn'							=> 'n',
			'package_yn_suboption'					=> 'n',
			'tot_stock'								=> '0',
			'option_international_shipping_status'	=> 'n',
			'display_terms_type'					=> 'SELLING',
			'display_terms_before'					=> 'CONCEAL',
			'display_terms_after'					=> 'CONCEAL',
			'sale_seq'								=> '1'
		);
		foreach($require_fld_list as $fld => $default_val)
			if	(!$goods[$fld])	$goods[$fld]	= $default_val;

		$result		= $this->db->insert('fm_goods', $goods);
		$goodsSeq	= $this->db->insert_id();

		// 에디터 이미지 경로 변경 재 업데이트 :: 2016-04-22 lwh
		$imgRes		= $this->goodsmodel->set_goodImages($goodsSeq);

		// 모바일용 상품 복사 :: 2016-05-11 lwh
		if($_POST['mobile_contents_copy'] == 'Y')
			$this->goodsmodel->set_mobile_contents($imgRes['contents'],$goodsSeq);//모바일용 상품설명 저장처리

		if($imgRes){
			/* 이미지호스팅 결과 값*/
			$result_imagehosting= $imgRes['result_imagehosting'];
			unset($imgRes['result_imagehosting']);

			$this->db->where('goods_seq', $goodsSeq);
			$result = $this->db->update('fm_goods', $imgRes);			
		}

		if ($goodsSeq > 0 ) {

			// 입점사가 아닐경우 임시로 master_goods_seq 저장 추가
			/*if ($providerInfo === false) {
				unset($upParams);
				$upParams['master_goods_seq']	= $goodsSeq;
				$this->db->where(array('goods_seq' => $goodsSeq));
				$this->db->update('fm_goods', $upParams);
			}*/

			return [ "goods_seq" => $goodsSeq, "result_imagehosting" => $result_imagehosting];
		} else {
			return false;
		}
	}



	// 수정시
	protected function _doModifyLink($goodsSeq, $providerInfo)
	{

		$cateParams = $this->input->post();

		/* 카테고리 연결 */
		$query	= "select * from fm_category_link where goods_seq=?";
		$query	= $this->db->query($query,array($goodsSeq));
		$data	= $query->result_array();
		$del_seq = [];
		foreach($data as $key => $row){
			$del_seq[] = $row['category_link_seq'];
		}
		foreach($cateParams['categoryLinkSeq'] as $seq) {
			if (($key = array_search($seq, $del_seq)) !== false) {
				unset($del_seq[$key]);
			}
		}
		if(count($del_seq)>0){
			$this->db->where('goods_seq',$goodsSeq);
			$this->db->where_in('category_link_seq', $del_seq);
			$this->db->delete('fm_category_link');
			$this->db->last_query();
		}

		if	($cateParams['connectCategory']) {
			foreach($cateParams['connectCategory'] as $key => $code){
				$categorys = array();
				$categoryLinkSeq				= (int) $cateParams['categoryLinkSeq'][$key];
				// 새로운 카테고리만 insert
				if($categoryLinkSeq == 0) {
					$categorys['link']				= 0;
					$categorys['category_link_seq']	= 0;
					$categorys['category_code']		= $code;
					$categorys['goods_seq']			= $goodsSeq;
					$categorys['regist_date']		= date('Y-m-d H:i:s',time());
					$minsort						= $this->categorymodel->getSortValue($code, 'min');
					$mobile_minsort					= $this->categorymodel->getSortValue($code, 'mobile_min');

					$categorys['sort']				= $minsort - 1;
					$categorys['mobile_sort']		= $mobile_minsort - 1;
					$result							= $this->db->insert('fm_category_link', $categorys);
				} else {
					$categorys['link']				= 0;
					$categorys['regist_date']		= date('Y-m-d H:i:s',time());
					$this->db->where('category_link_seq', $categoryLinkSeq);
					$result							= $this->db->update('fm_category_link', $categorys);
				}
			}
			// 대표카테고리 update
			$this->db->where('goods_seq',$goodsSeq);
			$this->db->where('category_code',$cateParams['firstCategory']);
			$this->db->update('fm_category_link', array('link'=>'1'));
		}


		/* 브랜드 연결 */
		$query	= "select * from fm_brand_link where goods_seq=?";
		$query	= $this->db->query($query,array($goodsSeq));
		$data	= $query->result_array();
		foreach($data as $key => $row){
			$del_seq[] = $row['category_link_seq'];
		}
		foreach($cateParams['brandLinkSeq'] as $seq) {
			if (($key = array_search($seq, $del_seq)) !== false) {
				unset($del_seq[$key]);
			}
		}
		if(count($del_seq)>0){
			$this->db->where('goods_seq',$goodsSeq);
			$this->db->where_in('category_link_seq', $del_seq);
			$this->db->delete('fm_brand_link');
			$this->db->last_query();
		}

		if	($cateParams['connectBrand']) {
			foreach($cateParams['connectBrand'] as $key => $code){
				$categorys = array();
				$brandLinkSeq				= (int) $cateParams['brandLinkSeq'][$key];
				// 새로운 브랜드만 insert
				if($brandLinkSeq == 0) {
					$categorys['link']				= 0;
					$categorys['category_link_seq']	= 0;
					$categorys['category_code']		= $code;
					$categorys['goods_seq']			= $goodsSeq;
					$categorys['regist_date']		= date('Y-m-d H:i:s',time());
					$minsort						= $this->brandmodel->getSortValue($code, 'min');
					$mobile_minsort					= $this->brandmodel->getSortValue($code, 'mobile_min');

					$categorys['sort']				= $minsort - 1;
					$categorys['mobile_sort']		= $mobile_minsort - 1;
					$result							= $this->db->insert('fm_brand_link', $categorys);
				} else {
					$categorys['link']				= 0;
					$categorys['regist_date']		= date('Y-m-d H:i:s',time());
					$this->db->where('category_link_seq', $brandLinkSeq);
					$result							= $this->db->update('fm_brand_link', $categorys);
				}
			}
			// 대표브랜드 update
			$this->db->where('goods_seq',$goodsSeq);
			$this->db->where('category_code',$cateParams['firstBrand']);
			$this->db->update('fm_brand_link', array('link'=>'1'));
		}

		/* 지역 연결 */
		$location_sorts			= array();
		$location_mobile_sorts	= array();

		$query	= "select * from fm_location_link where goods_seq=?";
		$query	= $this->db->query($query,array($goodsSeq));
		$data	= $query->result_array();
		foreach($data as $row){
			$location_sorts[$row['location_code']]			= $row['sort'];
			$location_mobile_sorts[$row['location_code']]	= $row['mobile_sort'];
		}

		$this->db->delete('fm_location_link', array('goods_seq' => $goodsSeq));

		unset($categorys);
		if	($cateParams['connectLocation'])foreach($cateParams['connectLocation'] as $key => $code){
			$locationLinkSeq		= (int) $cateParams['locationLinkSeq'][$key];
			$categorys['link']		= 0;

			if($code == $cateParams['firstLocation'])
				$categorys['link']	= 1;

			$categorys['location_link_seq']	= $locationLinkSeq;
			$categorys['location_code']		= $code;
			$categorys['goods_seq']			= $goodsSeq;
			$categorys['regist_date']		= date('Y-m-d H:i:s',time());
			$minsort						= $this->locationmodel->getSortValue($code, 'min');
			$mobile_minsort					= $this->locationmodel->getSortValue($code, 'mobile_min');
			$categorys['sort']				= $location_sorts[$code] ? $location_sorts[$code] : $minsort - 1;
			$categorys['mobile_sort']		= $location_mobile_sorts[$code] ? $location_mobile_sorts[$code] : $mobile_minsort - 1;

			$result = $this->db->insert('fm_location_link', $categorys);
		}

	}

	// 등록시
	protected function _doRegistLink($goodsSeq, $providerInfo)
	{

		/* 카테고리 연결 */
		foreach($_POST['connectCategory'] as $code){
			$categorys['link']			= 0;

			if($code == $_POST['firstCategory'])
				$categorys['link']		= 1;

			$categorys['category_code']	= $code;
			$categorys['goods_seq']		= $goodsSeq;
			$categorys['regist_date']	= date('Y-m-d H:i:s',time());
			$result						= $this->db->insert('fm_category_link', $categorys);

			$minsort					= $this->categorymodel->getSortValue($code, 'min');
			$mobile_minsort				= $this->categorymodel->getSortValue($code, 'mobile_min');
			$category_link_seq			= $this->db->insert_id();

			$this->db->where('category_link_seq', $category_link_seq);
			$this->db->update('fm_category_link',array('sort'=>$minsort-1,'mobile_sort'=>$mobile_minsort-1));
		}

		/* 브랜드 연결 */
		foreach($_POST['connectBrand'] as $code){
			$brands['link']				= 0;

			if($code == $_POST['firstBrand'])
				$brands['link']			= 1;

			$brands['category_code']	= $code;
			$brands['goods_seq']		= $goodsSeq;
			$brands['regist_date']		= date('Y-m-d H:i:s',time());
			$result						= $this->db->insert('fm_brand_link', $brands);

			$minsort					= $this->brandmodel->getSortValue($code, 'min');
			$mobile_minsort				= $this->brandmodel->getSortValue($code, 'mobile_min');
			$category_link_seq			= $this->db->insert_id();

			$this->db->where('category_link_seq', $category_link_seq);
			$this->db->update('fm_brand_link',array('sort'=>$minsort-1,'mobile_sort'=>$mobile_minsort-1));
		}

		/* 지역 연결 */
		foreach($_POST['connectLocation'] as $code){
			$locations['link']			= 0;

			if($code == $_POST['firstLocation'])
				$locations['link']		= 1;

			$locations['location_code']	= $code;
			$locations['goods_seq']		= $goodsSeq;
			$locations['regist_date']	= date('Y-m-d H:i:s',time());
			$result						= $this->db->insert('fm_location_link', $locations);

			$minsort					= $this->locationmodel->getSortValue($code, 'min');
			$mobile_minsort				= $this->locationmodel->getSortValue($code, 'mobile_min');
			$location_link_seq			= $this->db->insert_id();

			$this->db->where('location_link_seq', $location_link_seq);
			$this->db->update('fm_location_link',array('sort'=>$minsort-1,'mobile_sort'=>$mobile_minsort-1));
		}

	}



	protected function _doModifyOthers($goodsSeq, $goods, $providerInfo)
	{

		$this->load->model('errorpackage');

		if($goods['package_yn'] == 'y')
			$this->goodsmodel->package_check($goodsSeq,'option');

		if($goods['package_yn_suboption'] == 'y')
			$this->goodsmodel->package_check($goodsSeq,'suboption');


		if ($providerInfo === false) {
			// 오픈마켓 옵션 가격 조정 정보 저장
			$org_mallprice			= $this->openmarketmodel->get_linkage_option_price($goodsSeq);

			if ($_POST['market_tmp_seq'])
				$this->openmarketmodel->save_openmarket_option($goodsSeq, $_POST['market_tmp_seq']);
			else if (count($org_mallprice) < 1)
				$this->openmarketmodel->save_openmarket_option($goodsSeq);
			else
				$this->openmarketmodel->chg_price_to_option($goodsSeq);
		}


		/* 추가옵션 */
		if ($_POST['subOptionUse']){
			if ($_POST['tmp_suboption_seq'])
				$this->goodsmodel->moveTmpToSubOption($goodsSeq, $_POST['tmp_suboption_seq']);
		} else {
			$this->goodsmodel->delete_sub_option_info($goodsSeq);
			$del_err		= array('goods_seq'=>$goodsSeq,'type'=>'suboption');

			$this->errorpackage->del_error($del_err);

			$set_params		= array('package_yn_suboption'=>'n');
			$where_params	= array('goods_seq'=>$goodsSeq);

			$this->goodsmodel->set_goods($set_params,$where_params);
		}

		/* 가용재고 재계산 */
		$this->goodsmodel->modify_reservation_real($goodsSeq,'manual');

		/* 구매자입력사항 */
		$this->db->delete('fm_goods_input', array('goods_seq' => $goodsSeq));

		if ($_POST['memberInputName']) {

			foreach ($_POST['memberInputName'] as $i => $input) {
				$inputs['goods_seq'] 		= $goodsSeq;
				$inputs['input_name'] 		= trim(preg_replace('/\t+/', '', $input));
				$inputs['input_form']		= $_POST['memberInputForm'][$i];
				$inputs['input_limit'] 		= $_POST['memberInputLimit'][$i];
				$inputs['input_require']	= ($_POST['memberInputRequire'][$i] == 'require') ? '1' : '0';

				$result = $this->db->insert('fm_goods_input', $inputs);
			}

		}


		/* RELATION */
		if ($providerInfo === false) {
			$this->db->delete('fm_goods_relation', array('goods_seq' => $goodsSeq));
			if ($_POST['relation_type']=='MANUAL') {

				for ($i=0;$i<count($_POST['relationGoods']);$i++)
					$result		= $this->db->insert('fm_goods_relation', array('goods_seq'=>$goodsSeq,'relation_goods_seq'=>$_POST['relationGoods'][$i]));
			}
		}

		$this->db->delete('fm_goods_relation_seller', array('goods_seq' => $goodsSeq));
		if($_POST['relation_seller_type']=='MANUAL'){

			for ($i=0;$i<count($_POST['relationSellerGoods']);$i++)
				$result		= $this->db->insert('fm_goods_relation_seller', array('goods_seq'=>$goodsSeq,'relation_goods_seq'=>$_POST['relationSellerGoods'][$i]));
		}

		### RE OPTION CHECK
		$this->goodsmodel->option_check($goodsSeq);

		### 연결상품 재고
		$this->goodsmodel->update_package_stock($goodsSeq);

		### 연결상품오류저장
		$error_options			= array();
		$error_suboptions		= array();
		$this_goods_error_sub	= false;
		$this_goods_error		= false;

		if ($_POST['error_option']) {
			$this->errorpackage->del_error(array('type'=>'option','goods_seq'=>$goodsSeq));
			foreach($_POST['error_option'] as $option_seq => $tmp){
				foreach($tmp as $num => $error_code){
					if(!in_array($error_code,array('0000','9999','8888','7777')) && !in_array($error_options,$option_seq) ){
						$error['type']			= 'option';
						$error['goods_seq']		= $goodsSeq;
						$error['parent_seq']	= $option_seq;
						$error['no']			= $num;
						$error['error_code']	= $error_code;

						## 상품테이블 필드업데이트용 파라미터
						$goods_where['goods_seq']	= $goodsSeq;
						$goods_set['package_err']	= 'y';

						$this->errorpackage->set_error($error);

						if(!$this_goods_error)
							$this->goodsmodel->set_goods($goods_set,$goods_where);

						$error_options[]		= $option_seq;
						$this_goods_error		= true;
					}
				}
			}
			if(!$this_goods_error){
				$goods_where['goods_seq']	= $goodsSeq;
				$goods_set['package_err']	= 'n';
				$this->goodsmodel->set_goods($goods_set,$goods_where);
			}
		}
		if($_POST['error_suboption']){
			$this->errorpackage->del_error(array('type'=>'suboption','goods_seq'=>$goodsSeq));
			foreach($_POST['error_suboption'] as $suboption_seq => $tmp){
				foreach($tmp as $num => $error_code){
					if(!in_array($error_code,array('0000','9999','8888','7777')) && !in_array($error_suboptions,$suboption_seq)){
						$error['type']			= 'suboption';
						$error['goods_seq']		= $goodsSeq;
						$error['parent_seq']	= $suboption_seq;
						$error['no']			= $num;
						$error['error_code']	= $error_code;

						## 상품테이블 필드업데이트용 파라미터
						$goods_where['goods_seq']			= $goodsSeq;
						$goods_set['package_err_suboption']	= 'y';

						$this->errorpackage->set_error($error);

						if(!$this_goods_error_sub)
							$this->goodsmodel->set_goods($goods_set,$goods_where);

						$error_suboptions[]		= $suboption_seq;
						$this_goods_error_sub	= true;
					}
				}
			}

			if (!$this_goods_error_sub) {
				$goods_where['goods_seq']			= $goodsSeq;
				$goods_set['package_err_suboption']	= 'n';
				$this->goodsmodel->set_goods($goods_set,$goods_where);
			}
		}

		return $result;
	}


	protected function _doRegistOthers($goodsSeq, $goods, $providerInfo)
	{

		$this->load->model('usedmodel');

		// 오픈마켓 옵션 가격 조정 정보 저장
		$this->openmarketmodel->save_openmarket_option($goodsSeq, $_POST['market_tmp_seq']);

		/* 추가옵션 */
		if	($_POST['subOptionUse'] && $_POST['tmp_suboption_seq'])
			$this->goodsmodel->moveTmpToSubOption($goodsSeq, $_POST['tmp_suboption_seq']);


		/* 가용재고 재계산 */
		$this->goodsmodel->modify_reservation_real($goodsSeq,'manual');

		/* 구매자입력사항 */
		foreach($_POST['memberInputName'] as $i => $input){
			$inputs['goods_seq'] 		= $goodsSeq;
			$inputs['input_name'] 		= trim(preg_replace('/\t+/', '', $input));
			$inputs['input_form']		= $_POST['memberInputForm'][$i];
			$inputs['input_limit'] 		= $_POST['memberInputLimit'][$i];
			$inputs['input_require']	= ($_POST['memberInputRequire'][$i] == 'require') ? '1' : '0';

			$result		= $this->db->insert('fm_goods_input', $inputs);
		}

		/* 상품 이미지 저장 :: 2016-04-21 lwh */
		$data_used		= $this->usedmodel->used_limit_check();

		/* [상품 사진] 이미지호스팅 사용 여부 및 기본 값 세팅 */
		$this->load->library('imagehostinglibrary');
		$imagestore_type = [];
		$imagestore_type['imagestore_division'] = ($goods['provider_seq'] == "1") ? "goods_headquaters" : "goods_provider";
		$imagestore_type['imagestore_item'] = "goods_image";
		$isImagehosting = $this->imagehostinglibrary->isImagehostingUse($imagestore_type);

		$params_imagehosting = [];
		$params_imagehosting['isImagehosting'] = $isImagehosting;
		if($isImagehosting == true){
			$params_imagehosting['imagestore_type'] = $imagestore_type;
		}

		if ($data_used['type']) {
			$imgType_arr		= array('large','view','list1','list2','thumbView','thumbCart','thumbScroll');
			$this->goodsSeq		= $goodsSeq;

			foreach( $imgType_arr as $imgType ){
				// 이미지 업로드
				$res = $this->goodsmodel->upload_goodsImage($_POST[$imgType.'GoodsImage'], '', $params_imagehosting);
				$imagehosting_newPath = '';
				foreach($res['result_imagehosting'] as $imagehosting_array){
					$result_imagehosting['goods_image'][] = $imagehosting_array;
					$imagehosting_newPath[] = $imagehosting_array['new_path'];
				}
				// 이미지 연결
				$this->goodsmodel->insert_goodsImage($imgType.'GoodsImage',$goodsSeq,$imagehosting_newPath);
			}

		}else{
			openDialogAlert($data_used['msg'],400,140,'parent','');
		}

		/* INFO */
		###
		$_REQUEST['tx_attach_files']	= (!empty($_POST['tx_attach_files'])) ? $_POST['tx_attach_files']:'';

		$common_contents				= adjustEditorImages($_POST['commonContents'], "/data/editor/");
		/* [공용 정보] 이미지호스팅 사용 여부 및 기본 값 세팅 */
		$imagestore_type['imagestore_item'] = "common_info";
		$isImagehosting = $this->imagehostinglibrary->isImagehostingUse($imagestore_type);
		$params_imagehosting = [];
		$params_imagehosting['isImagehosting'] = $isImagehosting;
		if($isImagehosting == true){
			$params_imagehosting['imagestore_type'] = $imagestore_type;
			$result_imagehosting = $this->imagehostinglibrary->uploadEditorImage(['common_info' => $common_contents], $imagestore_type);
			$common_contents = $result_imagehosting['common_info']['contents'];
			unset($result_imagehosting['common_info']['contents']);
		}

		$params = [];
		$params['info_value']			= $common_contents;

		$params['info_name']			= $_POST['info_name'];
		$params['regist_date']			= date("Y-m-d H:i:s");
		$params['info_provider_seq']	= ($_POST['provider_seq'] > 0) ? $_POST['provider_seq'] : '1';
		if ($_POST['info_select_seq']) {	// UPDATE
			$data		= filter_keys($params, $this->db->list_fields('fm_goods_info'));
			unset($data['info_name']);

			$this->db->where('info_seq', $_POST['info_select_seq']);
			$result		= $this->db->update('fm_goods_info', $data);
		}else{						// INSERT
			if($params['info_name'] && $params['info_value']){
				$result		= $this->db->insert('fm_goods_info', $params);
				$info_seq	= $this->db->insert_id();
				$this->db->where('goods_seq', $goodsSeq);
				$result		= $this->db->update('fm_goods', array('info_seq'=>$info_seq));
			}

		}

		/* RELATION */
		if ($_POST['relation_type'] == 'MANUAL' && $providerInfo === false) {

			for($i=0;$i<count($_POST['relationGoods']);$i++)
				$result	= $this->db->insert('fm_goods_relation', array('goods_seq'=>$goodsSeq,'relation_goods_seq'=>$_POST['relationGoods'][$i]));
		}

		if($_POST['relation_seller_type']=='MANUAL'){

			for($i=0;$i<count($_POST['relationSellerGoods']);$i++)
				$result = $this->db->insert('fm_goods_relation_seller', array('goods_seq'=>$goodsSeq,'relation_goods_seq'=>$_POST['relationSellerGoods'][$i]));
		}

		### RE OPTION CHECK
		$this->goodsmodel->option_check($goodsSeq);

		### INSERT KEYWORD DEFAULT 2018-11-06 by hyem
		$keyword = $goods['keyword'] == "" ? $goodsSeq : $goodsSeq.",".$goods['keyword'];
		$this->db->where('goods_seq', $goodsSeq);
		$result		= $this->db->update('fm_goods', array('keyword'=>$keyword));

		$return['result'] = $result;
		$return['result_imagehosting'] = $result_imagehosting;
		return $return;

	}

	public function getGoodsInfo($goodsSeqList, $searchParam = array()) {
		$goodsSeqListIn	= implode(',', $goodsSeqList);

		$sql	= "
			SELECT	  goods_seq
					, provider_seq
			FROM	fm_goods
			WHERE	goods_seq IN ({$goodsSeqListIn})";

		$query	= $this->db->query($sql);

		return $query->result_array();
	}

	public function getOptionsInfo($seqList, $getType = 'GoodsSeq', $searchParam = array()) {

		$inField	= ($getType == 'GoodsSeq') ? 'goods_seq' : 'option_seq';
		$seqListIn	= implode(',', $seqList);

		$sql	= "
			SELECT	  goods_seq
					, default_option
					, option_seq
					, consumer_price
					, price
					, weight
					, option_view
			FROM	fm_goods_option
			WHERE	{$inField} IN ({$seqListIn})";

		$query	= $this->db->query($sql);

		return $query->result_array();
	}


	//상품 그룹업데이트(조건 업데이트) 정책
	public function goodsGroupUpdatePolicy()
	{
		//${goods|option}['입력 파리미터명']
		$goodsGroup['batch_tax_yn']				= array('params' => 'batch_tax', 'targetField' => 'tax');
		$goodsGroup['batch_grade_sale_yn']		= array('params' => 'batch_grade_sale', 'targetField' => 'sale_seq');
		$goodsGroup['batch_goods_name_yn']		= array('params' => 'batch_goods_name', 'targetField' => 'goods_name');
		$goodsGroup['batch_info_select_yn']		= array('params' => 'batch_info_select', 'targetField' => 'info_select_seq');
		$goodsGroup['batch_color_pick_yn']		= array('params' => 'batch_color_pick', 'targetField' => 'color_pick');
		$goodsGroup['batch_keyword_yn']			= array('params' => 'batch_keyword', 'targetField' => 'keyword');
		$goodsGroup['batch_summary_yn']			= array('params' => 'batch_summary', 'targetField' => 'summary');
		$goodsGroup['batch_provider_status_yn']	= array(
													'params' => 'batch_provider_status',
													'targetField' => 'provider_status');
		$goodsGroup['batch_goods_view_yn']		= array('params' => 'batch_goods_view', 'targetField' => 'goods_view');
		$goodsGroup['batch_goods_status_yn']	= array('params' => 'batch_goods_status', 'targetField' => 'goods_status');
		$goodsGroup['batch_runout_type_yn']		= array('params' => 'batch_runout_type', 'targetField' => 'runout_type');
		$goodsGroup['batch_runout_policy_yn']	= array('params' => 'batch_runout_policy', 'targetField' => 'runout_policy');
		$goodsGroup['batch_able_stock_limit_yn']= array('params' => 'batch_able_stock_limit',
													'targetField' => 'able_stock_limit');
		$goodsGroup['batch_adult_goods_yn']		= array('params' => 'batch_adult_goods', 'targetField' => 'adult_goods');
		$goodsGroup['batch_option_international_shipping_status_yn'] = array(
													'params'		=> 'batch_option_international_shipping_status',
													'targetField'	=> 'option_international_shipping_status');
		$goodsGroup['batch_cancel_type_yn']		= array('params' => 'batch_cancel_type', 'targetField' => 'cancel_type');
		$goodsGroup['batch_present_use_yn']		= array('params' => 'batch_present_use', 'targetField' => 'present_use');
		$goodsGroup['shipping']					= array(
													'params' => 'shipping_group_seq',
													'targetField' => 'shipping_group_seq');
		$goodsGroup['batch_info_select']		= array('params' => 'batch_info_select', 'targetField' => 'info_seq');
		$goodsGroup['batch_hscode_yn']			= array('params' => 'batch_hscode_selector', 'targetField' => 'hscode');
		$goodsGroup['batch_relation_yn']		= array('params' => 'batch_relation_type', 'targetField' => 'relation_type');
		$goodsGroup['batch_relation_seller_yn']	= array('params' => 'batch_relation_seller_type', 'targetField' => 'relation_seller_type');
		$goodsGroup['batch_bigdata_yn']	= array('params' => 'batch_bigdata_criteria', 'targetField' => 'bigdata_criteria');

		// 연관된 추가 파라미터
		$goodsGroup['batch_relation_yn']['addParams']['batch_auto_condition_use']	= 'auto_condition_use';
		$goodsGroup['batch_relation_yn']['addParams']['batch_relation_criteria']	= 'relation_criteria';
		$goodsGroup['batch_relation_yn']['addParams']['batch_relation_criteria_light']	= 'relation_criteria_light';
		$goodsGroup['batch_relation_seller_yn']['addParams']['batch_relation_seller_criteria']	= 'relation_seller_criteria';
		$goodsGroup['batch_relation_seller_yn']['addParams']['batch_relation_seller_criteria_light']	= 'relation_seller_criteria_light';

		//$goodsGroup['batch_multidiscount_yn']		= array('params' => 'batch_multidiscount', 'targetField' => 'goods_name');
		$goodsGroup['batch_min_limit']= array('params' => 'minPurchaseEa', 'targetField' => 'min_purchase_ea');
		$goodsGroup['batch_min_limit']['addParams']['minPurchaseLimit']		= 'min_purchase_limit';
		$goodsGroup['batch_max_limit']= array('params' => 'maxPurchaseEa', 'targetField' => 'max_purchase_ea');
		$goodsGroup['batch_max_limit']['addParams']['maxPurchaseLimit']		= 'max_purchase_limit';

		// 가격대체 문구
		// 2018-07-10 [pjw] 미체크 시 데이터 처리 부분을 따로 빼지 않고 policy에서 아예 가져오지 않게끔 처리
		if($_POST['batch_possible_pay_type_yn'] == '1'){
			$goodsGroup['batch_possible_pay_type_yn']			= array('params' => 'possible_pay_type', 'targetField' => 'possible_pay_type');
			$goodsGroup['batch_possible_pay_type_yn']['addParams']['possible_pay_hidden'] = 'possible_pay';
		}

		if($_POST['batch_replacement_yn'] == '1'){
			$goodsGroup['string_price_use']						= array('params' => 'string_price_use', 'targetField' => 'string_price_use');
			$goodsGroup['string_price']							= array('params' => 'string_price', 'targetField' => 'string_price');
			$goodsGroup['string_price_color']					= array('params' => 'string_price_color', 'targetField' => 'string_price_color');
			$goodsGroup['string_price_link']					= array('params' => 'string_price_link', 'targetField' => 'string_price_link');
			$goodsGroup['string_price_link_url']				= array('params' => 'string_price_link_url', 'targetField' => 'string_price_link_url');
			$goodsGroup['string_price_link_target] =']			= array('params' => 'string_price_link_target', 'targetField' => 'string_price_link_target');
			$goodsGroup['member_string_price_use']				= array('params' => 'member_string_price_use', 'targetField' => 'member_string_price_use');
			$goodsGroup['member_string_price']					= array('params' => 'member_string_price', 'targetField' => 'member_string_price');
			$goodsGroup['member_string_price_color']			= array('params' => 'member_string_price_color', 'targetField' => 'member_string_price_color');
			$goodsGroup['member_string_price_link']				= array('params' => 'member_string_price_link', 'targetField' => 'member_string_price_link');
			$goodsGroup['member_string_price_link_url']			= array('params' => 'member_string_price_link_url', 'targetField' => 'member_string_price_link_url');
			$goodsGroup['member_string_price_link_target']		= array('params' => 'member_string_price_link_target', 'targetField' => 'member_string_price_link_target');
			$goodsGroup['allmember_string_price_use']			= array('params' => 'allmember_string_price_use', 'targetField' => 'allmember_string_price_use');
			$goodsGroup['allmember_string_price']				= array('params' => 'allmember_string_price', 'targetField' => 'allmember_string_price');
			$goodsGroup['allmember_string_price_color']			= array('params' => 'allmember_string_price_color', 'targetField' => 'allmember_string_price_color');
			$goodsGroup['allmember_string_price_link']			= array('params' => 'allmember_string_price_link', 'targetField' => 'allmember_string_price_link');
			$goodsGroup['allmember_string_price_link_url']		= array('params' => 'allmember_string_price_link_url', 'targetField' => 'allmember_string_price_link_url');
			$goodsGroup['allmember_string_price_link_target']	= array('params' => 'allmember_string_price_link_target', 'targetField' => 'allmember_string_price_link_target');
			$goodsGroup['string_button_use']					= array('params' => 'string_button_use', 'targetField' => 'string_button_use');
			$goodsGroup['string_button']						= array('params' => 'string_button', 'targetField' => 'string_button');
			$goodsGroup['string_button_color']					= array('params' => 'string_button_color', 'targetField' => 'string_button_color');
			$goodsGroup['string_button_link']					= array('params' => 'string_button_link', 'targetField' => 'string_button_link');
			$goodsGroup['string_button_link_url']				= array('params' => 'string_button_link_url', 'targetField' => 'string_button_link_url');
			$goodsGroup['string_button_link_target']			= array('params' => 'string_button_link_target', 'targetField' => 'string_button_link_target');
			$goodsGroup['member_string_button_use']				= array('params' => 'member_string_button_use', 'targetField' => 'member_string_button_use');
			$goodsGroup['member_string_button']					= array('params' => 'member_string_button', 'targetField' => 'member_string_button');
			$goodsGroup['member_string_button_color']			= array('params' => 'member_string_button_color', 'targetField' => 'member_string_button_color');
			$goodsGroup['member_string_button_link']			= array('params' => 'member_string_button_link', 'targetField' => 'member_string_button_link');
			$goodsGroup['member_string_button_link_url']		= array('params' => 'member_string_button_link_url', 'targetField' => 'member_string_button_link_url');
			$goodsGroup['member_string_button_link_target']		= array('params' => 'member_string_button_link_target', 'targetField' => 'member_string_button_link_target');
			$goodsGroup['allmember_string_button_use']			= array('params' => 'allmember_string_button_use', 'targetField' => 'allmember_string_button_use');
			$goodsGroup['allmember_string_button']				= array('params' => 'allmember_string_button', 'targetField' => 'allmember_string_button');
			$goodsGroup['allmember_string_button_color']		= array('params' => 'allmember_string_button_color', 'targetField' => 'allmember_string_button_color');
			$goodsGroup['allmember_string_button_link']			= array('params' => 'allmember_string_button_link', 'targetField' => 'allmember_string_button_link');
			$goodsGroup['allmember_string_button_link_url']		= array('params' => 'allmember_string_button_link_url', 'targetField' => 'allmember_string_button_link_url');
			$goodsGroup['allmember_string_button_link_target']	= array('params' => 'allmember_string_button_link_target', 'targetField' => 'allmember_string_button_link_target');
			$goodsGroup['grp_feed_status']						= array('params' => 'grp_feed_status', 'targetField' => 'feed_status');
			$goodsGroup['grp_openmarket_keyword']				= array('params' => 'grp_openmarket_keyword', 'targetField' => 'openmarket_keyword');
			$goodsGroup['grp_feed_evt_sdate']					= array('params' => 'grp_feed_evt_sdate', 'targetField' => 'feed_evt_sdate');
			$goodsGroup['grp_feed_evt_edate']					= array('params' => 'grp_feed_evt_edate', 'targetField' => 'feed_evt_edate');
			$goodsGroup['grp_feed_evt_text']					= array('params' => 'grp_feed_evt_text', 'targetField' => 'feed_evt_text');
			$goodsGroup['grp_feed_ship_type']					= array('params' => 'grp_feed_ship_type', 'targetField' => 'feed_ship_type');
			$goodsGroup['grp_feed_pay_type']					= array('params' => 'grp_feed_pay_type', 'targetField' => 'feed_pay_type');
			$goodsGroup['grp_feed_std_fixed']					= array('params' => 'grp_feed_std_fixed', 'targetField' => 'feed_std_fixed');
			$goodsGroup['grp_feed_add_txt']						= array('params' => 'grp_feed_add_txt', 'targetField' => 'feed_add_txt');
		}

		// 2018-07-10 [pjw] 네이버,다음쇼핑 전달정보 추가
		$goodsGroup['grp_feed_status_yn']						= array('params' => 'grp_feed_status', 'targetField' => 'feed_status');
		$goodsGroup['grp_openmarket_keyword_yn']				= array('params' => 'grp_openmarket_keyword', 'targetField' => 'openmarket_keyword');
		$goodsGroup['grp_feed_evt_sdate_yn']					= array('params' => 'grp_feed_evt_sdate_yn', 'targetField' => 'grp_feed_evt_sdate_yn');
		$goodsGroup['grp_feed_ship_type_yn']					= array('params' => 'grp_feed_ship_type', 'targetField' => 'feed_ship_type');

		// --------------------- option ---------------------------
		$optionGroup['batch_reserve_yn']		= array('params' => 'batch_reserve_policy', 'targetField' => 'reserve_policy');

		// 상품 코드 재고 관련
		$optionGroup['batch_weight_yn']			= array('params' => 'batch_weight_value', 'targetField' => 'weight');


		// 연관된 추가 파라미터
		$optionGroup['batch_reserve_yn']['addParams']['batch_reserve']		= 'reserve_rate';
		$optionGroup['batch_reserve_yn']['addParams']['batch_reserve_unit']	= 'reserve_unit';


		// 입점사 제외 필드
		if (defined('__SELLERADMIN__') === true) {

		}

		$return['optionGroupParams']	= $optionGroup;
		$return['goodsGroupParams']		= $goodsGroup;

		return $return;
	}

	//상품 개별 업데이트 정책
	public function goodsEachUpdatePolicy()
	{

		//${goods|option}Params['입력 파리미터명']			= '저장 필드명';
		$goodsParams['goods_name']						= 'goods_name';
		$goodsParams['batch_keyword']					= 'keyword';			//검색어
		$goodsParams['color_pick']						= 'color_pick';			//색상
		$goodsParams['tax']								= 'tax';				//과세/비과세
		$goodsParams['summary']							= 'summary';			//짧은설명
		$goodsParams['info_select']						= 'info_seq';			//공용정보
		$goodsParams['grade_sale']						= 'sale_seq';			//회원등급별할인혜택
		$goodsParams['goods_status']					= 'goods_status';		//상품 상태
		$goodsParams['goods_view']						= 'goods_view';			//노출
		$goodsParams['provider_status']					= 'provider_status';	//상품승인여부
		$goodsParams['runout_policy']					= 'runout_policy';		//재고정책
		$goodsParams['able_stock_limit']				= 'able_stock_limit';	//재고정책-재한수량
		$goodsParams['adult_goods']						= 'adult_goods';		//성인상품
		$goodsParams['cancel_type']						= 'cancel_type';		//청약철회
		$goodsParams['reserve_policy']					= 'reserve_policy';		//정립금정책
		$goodsParams['option_international_shipping_status']= 'option_international_shipping_status';//필수옵션 해외배송여부
		$goodsParams['relation_type']					= 'relation_type';			//관련상품 타입
		$goodsParams['bigdata_criteria']				= 'bigdata_criteria';		//관련상품 자동조건
		$goodsParams['relation_criteria']				= 'relation_criteria';		//관련상품 자동조건
		$goodsParams['relation_criteria_light']			= 'relation_criteria_light';		//관련상품 자동조건
		$goodsParams['auto_condition_use']				= 'auto_condition_use';		//관련상품 개선된 자동조건 사용여부
		$goodsParams['relation_seller_criteria']		= 'relation_seller_criteria';//판매자관련상품 자동조건
		$goodsParams['relation_seller_criteria_light']		= 'relation_seller_criteria_light';//판매자관련상품 자동조건
		$goodsParams['relation_seller_type']			= 'relation_seller_type';	//판매자관련상품 타입
		$goodsParams['present_use']						= 'present_use';	//선물하기

		// 상품코드/무게/재고 직접 업데이트
		$goodsParams['code']							= 'goods_code';			//상품코드

		// 정보고시
		$goodsParams['goods_sub_info']					= 'goodsSubInfo';
		$goodsParams['sub_info_desc']					= 'sub_info_desc';

		// EP 전송 데이터
		$goodsParams['feed_status']						= 'feed_status';
		$goodsParams['feed_evt_sdate']					= 'feed_evt_sdate';
		$goodsParams['feed_evt_edate']					= 'feed_evt_edate';
		$goodsParams['feed_evt_text']					= 'feed_evt_text';
		$goodsParams['feed_ship_type']					= 'feed_ship_type';
		$goodsParams['feed_pay_type']					= 'feed_pay_type';
		$goodsParams['feed_std_fixed']					= 'feed_std_fixed';
		$goodsParams['feed_add_txt']					= 'feed_add_txt';
		$goodsParams['openmarket_keyword']				= 'openmarket_keyword';


		$optionParams['detail_code1']					= 'optioncode1';
		$optionParams['detail_code2']					= 'optioncode2';
		$optionParams['detail_code3']					= 'optioncode3';
		$optionParams['detail_code4']					= 'optioncode4';
		$optionParams['detail_code5']					= 'optioncode5';
		$optionParams['detail_commission_rate']			= 'commission_rate';
		$optionParams['commission_rate']				= 'commission_rate';
		$optionParams['detail_commission_type']			= 'commission_type';
		$optionParams['commission_type'	]				= 'commission_type';
		$optionParams['detail_consumer_price']			= 'consumer_price';
		$optionParams['consumer_price']					= 'consumer_price';
		$optionParams['detail_price']					= 'price';
		$optionParams['price']							= 'price';
		$optionParams['detail_reserve_policy']			= 'reserve_policy';
		$optionParams['reserve_policy']					= 'reserve_policy';
		$optionParams['detail_reserve_rate']			= 'reserve_rate';
		$optionParams['reserve_rate']					= 'reserve_rate';
		$optionParams['detail_reserve']					= 'reserve';
		$optionParams['reserve']						= 'reserve';
		$optionParams['detail_reserve_unit']			= 'reserve_unit';
		$optionParams['reserve_unit']					= 'reserve_unit';
		$optionParams['detail_shipping_policy']			= 'shipping_policy';
		$optionParams['shipping_policy']				= 'shipping_policy';
		$optionParams['detail_unlimit_shipping_price']	= 'unlimit_shipping_price';
		$optionParams['detail_option_view']				= 'option_view';
		$optionParams['unlimit_shipping_price']			= 'unlimit_shipping_price';

		// 상품코드/무게/재고 직접 업데이트
		$optionParams['detail_weight']					= 'weight';				//무게
		$optionParams['weight']							= 'weight';

		// 입점사 제외 필드
		if (defined('__SELLERADMIN__') === true) {
			unset($optionParams['commission_rate']);
			unset($optionParams['detail_commission_type']);
			unset($optionParams['detail_commission_type']);
			unset($optionParams['commission_type']);
		}

		$return['optionParams']		= $optionParams;
		$return['goodsParams']		= $goodsParams;

		return $return;
	}

	//상품 일괄업데이트 처리
	public function doBatchUpdate($params)
	{
		// 2018-07-10 [pjw] modify_list 변수로 조건인지 직접업데이트인지 판별
		$isDirectUpdate	= $params['modify_list'] == '' ? true : false;
		$eachPolicy		= $this->goodsEachUpdatePolicy();
		$groupPolicy	= $this->goodsGroupUpdatePolicy();
		$goodsSeqList	= array();

		if ($params['modify_list'] == 'all') {
			$params['batch_mode'] = true;
			$query = $this->goodsmodel->admin_goods_list_new($params);
			$query		= $this->db->query($query);
			while($data = $query->unbuffered_row('array'))
				$goodsSeqList[]	= $data['goods_seq'];

			array_unique($goodsSeqList);
		} else {
			$goodsSeqList	= $params['goods_seq'];
		}

		$optionSeqList		= array();

		if (is_array($params['option_seq']) === true) {
			foreach ($params['option_seq'] as $optionSeq => $goodsSeq) {
				if(array_search($goodsSeq, $goodsSeqList) !== false)
					$optionSeqList[]		= $optionSeq;

			}
		}

		$defaultOptionSeqList				= array();

		if (is_array($params['default_option_seq']) === true) {
			foreach ($params['default_option_seq'] as $optionSeq => $goodsSeq) {
				if(array_search($goodsSeq, $goodsSeqList) !== false)
					$defaultOptionSeqList[]	= $optionSeq;

			}
		}

		$updateData['goods']		= array();	// 상품 개별처리
		$updateData['options']		= array();	// 옵션 개별처리
		$updateData['goodsGroup']	= array();	// 상품 일괄처리
		$updateData['optionGroup']	= array();	// 옵션 일괄처리
		$updateData['addinfoGroup']	= array();	// 추가정보 일괄처리

		if ( count($goodsSeqList) > 0 ) {

			// 2018-07-10 [pjw] 직접, 조건 업데이트 분기처리
			if($isDirectUpdate){
				// 상품 개별 일괄 업데이트
				foreach ($eachPolicy['goodsParams'] as $paramName => $field) {

					if (isset($params[$paramName]) === false)
						continue;

					foreach ($goodsSeqList as $goodsSeq) {

						if (isset($params[$paramName][$goodsSeq]) === false)
							continue;

						$updateData['goods'][$goodsSeq][$field]	= $params[$paramName][$goodsSeq];

						//페이스북 피드 관련 착불 배송비 데이터를 받기 위해 아래 설정 추가
						//DB 테이블 추가를 피하기 위해 고정 배송비 필드에 착불 배송비 값을 입력 함
						//데이터 구분은 feed_pay_type를 이용하기 바람 19.05.30 kmj
						if($paramName == "feed_ship_type" && $params[$paramName][$goodsSeq] == "E"){
						    if($params['feed_pay_type'][$goodsSeq] == "postpay"){
						        $updateData['goods'][$goodsSeq]['feed_std_fixed'] = ($params['feed_std_postpay'][$goodsSeq])? $params['feed_std_postpay'][$goodsSeq] : '';
						        $updateData['goods'][$goodsSeq]['feed_add_txt'] = '';
						    } else if($params['feed_pay_type'][$goodsSeq] == "fixed"){
						        $updateData['goods'][$goodsSeq]['feed_add_txt'] = '';
						    } else if($params['feed_pay_type'][$goodsSeq] == "free"){
						        $updateData['goods'][$goodsSeq]['feed_std_fixed'] = '';
						    }
						} else if ($paramName == "feed_ship_type" && $params[$paramName][$goodsSeq] != "E"){
						    unset($updateData['goods'][$goodsSeq]['feed_pay_type']);
						}
					}

					unset($params[$paramName]);
				}
			}else{

				// 상품 조건(그룹) 일괄 업데이트
				foreach ($groupPolicy['goodsGroupParams'] as $paramName => $fieldArr) {

					if (isset($params[$paramName]) === false)
						continue;

					$updateData['goodsGroup'][$fieldArr['targetField']]	= $params[$fieldArr['params']];

					// 추가 필드
					if (isset($fieldArr['addParams'])) {
						foreach ((array)$fieldArr['addParams'] as $addParamsName => $addField) {

							if (isset($params[$addParamsName]) === false)
								continue;

							$updateData['goodsGroup'][$addField]	= $params[$addParamsName];
						}
					}

					unset($params[$paramName]);
					unset($params[$fieldArr['params']]);

				}
				// 옵션 조건(그룹) 일괄 업데이트
				foreach ($groupPolicy['optionGroupParams'] as $paramName => $fieldArr) {

					if (isset($params[$paramName]) === false)
						continue;

					$updateData['optionGroup'][$fieldArr['targetField']]	= $params[$fieldArr['params']];

					// 추가 필드
					if (isset($fieldArr['addParams'])) {
						foreach ((array)$fieldArr['addParams'] as $addParamsName => $addField) {

							if (isset($params[$addParamsName]) === false)
								continue;

							$updateData['optionGroup'][$addField]	= $params[$addParamsName];
						}
					}

					unset($params[$paramName]);
					unset($params[$fieldArr['params']]);

				}
			}

		}

		if (count($optionSeqList) > 0 || count($defaultOptionSeqList) > 0) {
			// 옵션 개별 일괄 업데이트(옵션 확장)
			foreach ($eachPolicy['optionParams'] as $paramName => $field) {

				if (isset($params[$paramName]) === false)
					continue;

				if (preg_match('/^detail_/', $paramName) == 1) {
					//기본옵션 정보
					foreach ($optionSeqList as $optionSeq) {

						if (isset($params[$paramName][$optionSeq]) === false)
							continue;

						$updateData['options'][$optionSeq][$field]	= $params[$paramName][$optionSeq];

					}

				} else {
					//전체 옵션 정보
					foreach ($defaultOptionSeqList as $optionSeq) {
						if (isset($params[$paramName][$optionSeq]) === false)
							continue;

						$updateData['options'][$optionSeq][$field]	= $params[$paramName][$optionSeq];
					}

				}


				unset($params[$paramName]);
			}

		}

		// 추가정보 및 정보고시 예외처리
		if	($params['mode'] == 'ifaddinfo'){
			// 추가정보
			$exceptResult						= $this->exceptGoodsAddinfoGroup($params);
			$updateData['addinfoGroup']			= $exceptResult['addinfo'];
			if	($exceptResult['goods']['goods_sub_info'] != 'keep'){
				if	(is_array($updateData['goodsGroup']) && count($updateData['goodsGroup']) > 0){
					$updateData['goodsGroup']	= array_merge($updateData['goodsGroup'], $exceptResult['goods']);
				}else{
					$updateData['goodsGroup']	= $exceptResult['goods'];
				}
			}
		}

		$optionSeqList	= array_merge($optionSeqList, $defaultOptionSeqList);

		$this->db->trans_begin();

		/* 	추가 처리를 해야하는경우
			_{일괄수정타입}Arrange($params, $goodsSeqList, $optionSeqList, &$arrangeData)
			$params			=> 수정 요청시 전달반은 파라미터
			$goodsSeqList	=> 타겟 상품 리스트
			$goodsSeqList	=> 타겟 옵션 리스트
			$arrangeData	=> 이미 정의된 수정항목(참조호출)

			※ 추가 처리(추가)할 파라미터가 없을경우 메서드는 생략 될 수 있음.
		*/

		if(method_exists($this, "_{$params['batchmodify_selector']}Arrange") === true) {
			$checkResult	= $this->{"_{$params['batchmodify_selector']}Arrange"}($params, $goodsSeqList, $optionSeqList, $updateData);

			if ($checkResult !== true) {
				$this->db->trans_rollback();
				return $checkResult;
			}
		}

		// 실제 업데이트
		if (count($updateData['goods']) > 0) {
			$checkResult	= $this->_doGoodsUpdate($updateData['goods']);

			if ($checkResult !== true) {
				$this->db->trans_rollback();
				return $checkResult;
			}
		}

		if (count($updateData['options']) > 0) {
			$checkResult	= $this->_doOptionsUpdate($updateData['options']);

			if ($checkResult !== true) {
				$this->db->trans_rollback();
				return $checkResult;
			}
		}

		if (count($updateData['goodsGroup']) > 0) {
			$checkResult	= $this->_doGoodsGroupUpdate($updateData['goodsGroup'], $goodsSeqList);

			if ($checkResult !== true) {
				$this->db->trans_rollback();
				return $checkResult;
			}
		}

		if (count($updateData['optionGroup']) > 0) {
			$checkResult	= $this->_doOptionGroupUpdate($updateData['optionGroup'], $goodsSeqList);

			if ($checkResult !== true) {
				$this->db->trans_rollback();
				return $checkResult;
			}
		}

			$checkResult	= $this->_doAddinfoGroupUpdate($params['batch_addinfo_savetype'], $updateData['addinfoGroup'], $goodsSeqList);

			if ($checkResult !== true) {
				$this->db->trans_rollback();
				return $checkResult;
			}

		$this->db->trans_commit();

		// 일괄 업데이트 후 추가 작업 :: 2017-08-10 lwh
		if(method_exists($this, "_{$params['batchmodify_selector']}Addprocess") === true) {
			$this->{"_{$params['batchmodify_selector']}Addprocess"}();
		}

		/**
		 * 미노출->노출로 변경할 시 Arrange 함수에서 set_event_price를 실행하면
		 * 상태가 아직 바뀌기 전이므로 summary 테이블만 삭제하는 오류 수정
		 * 2019-08-21
		 * @author Sunha Ryu
		 */
		if(in_array($params['batchmodify_selector'], array('status', 'ifstatus')) === true) {
		    $this->load->model('goodssummarymodel');
		    $this->goodssummarymodel->set_event_price(array('goods'=>$goodsSeqList));
		}

		// 재고 및 안전재고 수정 시 안전재고 상태 업데이트
		if(in_array($params['batchmodify_selector'], array('goodsetc', 'ifgoodsetc')) === true) {
			foreach($goodsSeqList as $goods_seq) {
				$this->goodsmodel->total_stock($goods_seq);
			}

		}

		// 상품 마스터 데이터 정가, 판매가 갱신
		foreach($goodsSeqList as $goods_seq) {
			$this->goodsmodel->default_price($goods_seq);
		}

        ////////// 상품(옵션) 수정 후 마켓연동 상품 수정. //////////
        $this->load->library('Connector');
        $goodsService   = $this->connector::getInstance('goods');
        foreach ((array)$goodsSeqList as $goodsSeq)
            $goodsService->doMarketGoodsUpdate($goodsSeq);
        ////////////////////////////////////////////////////////////

		if($updateData['goodsStatusEtc']){
			$message = $updateData['goodsStatusEtc'];
		}

		return array("result"=>true,"message"=>$message);
	}

	# 재고정책에 대한 상품 상태 재확인.
	public function getOptionsGoodsStatus($goods_seq,$runout="",$ableStockLimit="",$ableStockStep="") {
		$return_info = array();

		// 재고 업데이트 전 상품 상태값 확인
		$get_goods				= $this->goodsmodel->get_goods($goods_seq);
		$before_goods_status	= $get_goods['goods_status'];
		$before_tot_stock		= $get_goods['tot_stock'];

		if ($runout == "" && $ableStockLimit == "" && $ableStockStep=="") {
			if ($get_goods['runout_policy']!="") {
				$runout = $get_goods["runout_policy"];
				$ableStockLimit = $get_goods['able_stock_limit']+1;
			} else {
				$runout = $this->cfg_order['runout'];
				$ableStockLimit = $this->cfg_order['ableStockLimit']+1;
			}

			$ableStockStep = $this->cfg_order['ableStockStep'];
		}

		// 변경될 재고 확인
		$get_tot_option = $this->goodsmodel->get_tot_option($goods_seq);
		$afterUnUsableStock = (int) $get_tot_option['stock'] - $get_tot_option['badstock'] - $get_tot_option['reservation'.$ableStockStep];

		// 변경될 상품 상태값
		$modify_status = '';

		if ($runout=="stock") { // 재고가 있으면 판매
			if ($get_tot_option['stock'] < 1) {
				$modify_status = "runout";
			} else {
				$modify_status = "normal";
			}
		} else if ($runout=="ableStock") { // 가용 재고가 있으면 판매
			$opt_runout = true;		// 기본이 품절
			$options = $this->goodsmodel->get_goods_option($goods_seq,array('option_view'=>'Y'));
			// 옵션 중 한개만 가용재고보다 많으면 정상
			foreach($options as $k => $opt){
				$unUsableStock = (int) $opt['stock'] - $opt['badstock'] - $opt['reservation'.$ableStockStep];
				// 실재고가 설정한 가용재고+1보다 크거나 같으면 정상 :: 2019-01-09 lkh
				if($unUsableStock >= $ableStockLimit) {
					$opt_runout = false;
					break;
				}
			}
			if ($opt_runout === true) {
				$modify_status = "runout";
			} else {
				$modify_status = "normal";
			}
		} else if ($runout=="unlimited") { // 재고와 무관 판매
			if ($get_goods['goods_kind'] == 'coupon' && $get_goods['coupon_serial_type'] == 'n') { // 외부제휴사티켓상품
				if ($get_tot_option['stock'] < 1) {
					$modify_status = "runout";
				} else {
					$modify_status = "normal";
				}
			} else {
				$modify_status = "normal";
			}
		}

		// 정상과 품절이었던 상품들 중 상태값이 변경되는 경우 계산
		if ($before_goods_status=="normal" && $modify_status=="runout") {
			$return_info['runout_gname']['goods_seq'] = $get_goods['goods_seq'];
			$return_info['runout_gname']['goods_name'] = $get_goods['goods_name'];
		} else if ($before_goods_status=="runout" && $modify_status=="normal") {
			$return_info['normal_gname']['goods_seq'] = $get_goods['goods_seq'];
			$return_info['normal_gname']['goods_name'] = $get_goods['goods_name'];
		}

		$return_info['goods_status'] = $modify_status;

		return $return_info;
	}

	public function _doModifyStatus($mode,$return_info_arr)
	{
		if ($return_info_arr) {

			$tot_cnt			= 0;
			$first_gname		= "";
			$gname_table		= "";
			$out_gname_table	= "";

			$common_table = "<table class=\'info_stock_status_table\' align=\'center\'><thead><tr><th>고유값</th><th>상품명</th></tr></thead>%s</table>";

			// 품절 => 정상
			if (is_array($return_info_arr['normal_gname'])) {
				$tot_cnt += count($return_info_arr['normal_gname']);

				$cre_tr = "";
				foreach ($return_info_arr['normal_gname'] as $key =>$row) {
					if ($key==0) $first_gname = $row['goods_name'];
					$cre_tr .= sprintf("<tr><td>%s</td><td>%s</td></tr>",$row['goods_seq'],addslashes($row['goods_name']));
				}
				$gname_table = sprintf($common_table,$cre_tr);
			}

			// 정상 => 품절
			if (is_array($return_info_arr['runout_gname'])) {
				$tot_cnt += count($return_info_arr['runout_gname']);

				$cre_tr_runout = "";
				foreach ($return_info_arr['runout_gname'] as $key =>$row) {
					if ($key==0 && $first_gname=="") $first_gname = $row['goods_name'];
					$cre_tr_runout .= sprintf("<tr><td>%s</td><td>%s</td></tr>",$row['goods_seq'],addslashes($row['goods_name']));
				}
				$out_gname_table = sprintf($common_table,$cre_tr_runout);
			}

			$msg_cnt = ($tot_cnt>1) ? "외 ".($tot_cnt-1) : "1";

			if($mode == "status"){
				$msg_str = "상품은 재고정책에 따라 <br />‘정상’=>‘품절’ 또는 ‘품절’=>‘정상’<br />으로 변경이 되었습니다.<br />자세한 변경상품은 아래 버튼을 클릭하여 확인하실 수 있습니다.";
			}else{
				$msg_str = "상품은 재고정책에 따라 <br />‘정상’=>‘품절’ 또는 ‘품절’=>‘정상’<br />으로 변경이 되었습니다.";
			}
			$msg_show						= sprintf("‘%s’ %s개의 %s",$first_gname,$msg_cnt,$msg_str);

			$result_json					= array();
			$result_json['msg_show']		= $msg_show;
			$result_json['mode']			= $mode;
			$result_json['normal_cnt']		= $return_info_arr['normal_cnt'];
			$result_json['runout_cnt']		= $return_info_arr['runout_cnt'];
			$result_json['gname']			= ($gname_table) ? 1 : '';
			$result_json['out_gname']		= ($out_gname_table) ? 1 : '';
			$result_json['tot_cnt'] 		= ($tot_cnt) ? $tot_cnt : 0;
			$str_result_json				= addslashes(json_encode($result_json));

			echo("<script>
				parent.popup_stock_modify_msg('".$str_result_json."');
				parent.set_table_dialog('dialog_normal_table', '".$gname_table."');
				parent.set_table_dialog('dialog_runout_table', '".$out_gname_table."');
			</script>");
			exit;

		} else {
			$msg = "상품정보가 변경 되었습니다.";
			$callback = "parent.location.reload();";
			openDialogAlert($msg,400,140,'parent',$callback);
		}

	}

	// 업데이트 항목별 추가 처리 메서드↓↓↓↓↓
	protected function _priceArrange($params, $goodsSeqList, $optionSeqList, &$arrangeData) {

		$optionSeqList		= array();

		if (is_array($params['option_seq']) === true && defined('__SELLERADMIN__') !== true) {
			foreach ($params['option_seq'] as $optionSeq => $goodsSeq) {
				if(array_search($goodsSeq, $goodsSeqList) !== false) {
					if (isset($params['detail_supply_price'][$optionSeq]) === false)
						continue;

					$supplyUpdate['supply_price']	= $params['detail_supply_price'][$optionSeq];

					$this->db->where('option_seq', $optionSeq);
					$this->db->update('fm_goods_supply', $supplyUpdate);

				}

			}
		}

		$defaultOptionSeqList				= array();

		if (is_array($params['default_option_seq']) === true && defined('__SELLERADMIN__') !== true) {
			foreach ($params['default_option_seq'] as $optionSeq => $goodsSeq) {
				if(array_search($goodsSeq, $goodsSeqList) !== false) {

					if (isset($params['supply_price'][$optionSeq]) === false)
						continue;

					$supplyUpdate['supply_price']	= $params['supply_price'][$optionSeq];

					$this->db->where('option_seq', $optionSeq);
					$this->db->update('fm_goods_supply', $supplyUpdate);

				}

			}
		}


		if (defined('__SELLERADMIN__') === true) {

			foreach ($arrangeData['options'] as $optionSeq => $optionInfo) {

				if (isset($optionInfo['consumer_price']) === true || isset($optionInfo['price']) === true) {

					$goodsSeq		= (isset($params['option_seq'][$optionSeq]) == true) ? (int)$params['option_seq'][$optionSeq] : (int)$params['default_option_seq'][$optionSeq];

					if ($goodsSeq < 1)
						continue;

					if (isset($arrangeData['goods'][$goodsSeq]['provider_status']) === false) {

						$statusReason		= "입점관리자 {$this->providerInfo['provider_id']}에 의해 일괄수정되었습니다.";

						$arrangeData['goods'][$goodsSeq]['provider_status']					= 0;
						$arrangeData['goods'][$goodsSeq]['goods_status']					= 'unsold';
						$arrangeData['goods'][$goodsSeq]['provider_status_reason_type']		= 3;
						$arrangeData['goods'][$goodsSeq]['provider_status_reason']			= $statusReason;

					}

				}
			}

		}


		return true;
	}

	protected function _ifpriceArrange($params, $goodsSeqList, $optionSeqList, &$arrangeData) {

		$arrayCount		= count($goodsSeqList);
		$maxPage		= ceil($arrayCount / $this->updateCount);
		$endPoint		= 0;
		$updateCnt		= $this->updateCount;
		$checkCount		= 0;
		$goodsPageList	= array();

		for ($i = 1; $i <= $maxPage; $i++) {
			$startPoint	= $endPoint;
			$endPoint	= $i * $updateCnt;
			$endPoint	= ($endPoint > $arrayCount) ? $arrayCount : $endPoint;
			$targetList	= array();

			for ($j = $startPoint; $j < $endPoint; $j++) {
				$nowGoodsSeq	= (int)$goodsSeqList[$j];
				if ($goodsSeqList[$j] < 1)
					continue;

				if ((int)$goodsSeqList[$j] > 0)
					$targetList[]	= (int)$goodsSeqList[$j];
			}

			if (count($targetList) < 1)
				continue;


			$goodsPageList[]	= $targetList;

		}


		// batch_su_commission_yn 공급가(공급율) 처리 일괄업데이트
		if (isset($params['batch_su_commission_yn'])  && defined('__SELLERADMIN__') !== true) {

			$commissionType		= $params['batch_su_commission_type'];

			$targetGoodsList	= $this->_commissionSet($goodsPageList, $commissionType);

			$suCommissionParams	= array();

			if(count($targetGoodsList) > 0) {
				$suCommissionParams['commission_rate']		= $params['batch_su_commission_rate'];
				$suCommissionParams['commission_type']		= $commissionType;

				$this->_doOptionGroupUpdate($suCommissionParams, $targetGoodsList);
			} else {
				return "공급가(공급율) 변경이 가능한 상품이 없습니다";
			}
		}


		// batch_commission_rate_yn 수수료 일괄 업데이트
		if (isset($params['batch_commission_rate_yn']) && defined('__SELLERADMIN__') !== true) {
			$commissionType			= 'SACO';
			$targetGoodsList		= $this->_commissionSet($goodsPageList, $commissionType);

			$commissionRateParams	= array();

			if(count($targetGoodsList) > 0) {
				$commissionRateParams['commission_rate']	= $params['batch_commission_rate'];
				$commissionRateParams['commission_type']	= $commissionType;

				$this->_doOptionGroupUpdate($commissionRateParams, $targetGoodsList);
			} else {
				return "수수료 변경이 가능한 상품이 없습니다";
			}
		}

		// batch_supply_price_yn 매입가 조정
		if (!$this->scm_cfg)
			$this->scm_cfg	= config_load('scm');

		if (isset($params['batch_supply_price_yn']) && $this->scm_cfg != 'Y' && defined('__SELLERADMIN__') !== true) {
			$message	= $this->_supplyPriceSet($params, $goodsPageList);

			if ($message !== true)
				return $message;
		}

		// 가격변경
		$consumer_price		= (int)$params['batch_consumer_price_yn'];
		$price				= (int)$params['batch_price_yn'];
		$priceCheck			= bindec("{$consumer_price}{$price}");

		if ($priceCheck > 0) {
			switch ($priceCheck) {
				case	3 :
					$priceType	= 'all';
					break;

				case	2 :
					$priceType	= 'consumerPrice';
					break;

				case	1 :
					$priceType	= 'salePrice';
					break;
			}

			$message	= $this->_priceSet($params, $goodsPageList, $priceType);

			if ($message !== true)
				return $message;
		}


		//batch_reserve_yn(적립금 정책)은 상품으로 이동
		if (isset($arrangeData['optionGroup']['reserve_policy']) == true) {
			$arrangeData['goodsGroup']['reserve_policy']		= $arrangeData['optionGroup']['reserve_policy'];
			unset($arrangeData['optionGroup']['reserve_policy']);
		}

		//batch_option_view_yn(옵션 노출 정보)
		if (isset($params['batch_option_view_yn']) == true) {

			if ($params['batch_option_view'] == 'Y') {
				$arrangeData['optionGroup']['option_view']	= 'Y';
			} else {

				$optionViewParams['option_view']	= 'N';

				foreach((array)$goodsPageList as $goodsSeqList) {
					$this->db->where_in('goods_seq', $goodsSeqList);
					$this->db->where('default_option', 'n');
					$result	= $this->db->update('fm_goods_option', $optionViewParams);
				}

			}

		}

		return true;
	}


	// 정산금액 대상상품 필터링
	protected function _commissionSet($goodsPageList, $commissionType) {

		$commissionKeyType	= ($commissionType == 'SACO') ? 'SACO' : 'SUPPLY';

		if (isset($this->providerCoList[$commissionType]) !== true) {

			$this->load->model('providermodel');

			$providerList		= $this->providermodel->provider_goods_list();

			foreach((array)$providerList as $row) {
				switch($row['commission_type']) {
					case	'SUCO' :
					case	'SUPR' :
						$commissionKey		= 'SUPPLY';
						break;

					default :
						$commissionKey		= 'SACO';
						break;
				}

				$this->providerCoList[$commissionKey][$row['provider_seq']]		= $row['commission_type'];
			}

		}

		$targetGoods	= array();
		foreach((array)$goodsPageList as $goodsSeqList) {
			$nowGoodsList	= $this->getGoodsInfo($goodsSeqList);
			foreach((array) $nowGoodsList as $row) {

				//업데이트 가능 리스트
				if (isset($this->providerCoList[$commissionKeyType][$row['provider_seq']]) !== false)
					$targetGoods[]	= $row['goods_seq'];
			}
		}

		return $targetGoods;
	}

	// 본사 공급가 조건업데이트
	protected function _supplyPriceSet($params, $goodsPageList) {

		$cuttingPrice		= 'Y';
		$cuttingPosition	= $params['batch_supply_price_cutting_price'];
		$cuttingMode		= $params['batch_supply_price_cutting_action'];

		if (preg_match('/[^0-9|^.]/', $params['batch_supply_price']) == 1)
			return '사용할 수 없는 매입가 입니다.';


		if ($params['batch_supply_price_unit'] == 'percent') {
			$adjustmentUnit			= '*';

			if ($params['batch_supply_price_updown'] == 'down')
				$adjustmentPrice	= (100 - $params['batch_supply_price']) / 100;
			else
				$adjustmentPrice	= (100 + $params['batch_supply_price']) / 100;


		} else {
			$adjustmentUnit			= ($params['batch_supply_price_updown'] == 'down') ? '-' : '+';
			$adjustmentPrice		= $params['batch_supply_price'];
		}


		$supplyUpdateList			= array();

		foreach((array)$goodsPageList as $goodsSeqList) {
			$goodsSeqIn	= implode(',', $goodsSeqList);
			$inString	= str_replace(' ', ',', trim(str_repeat("? ", count($goodsSeqList))));

			$suppSql	= "
				SELECT
					  s.goods_seq		AS goods_seq
					, s.option_seq		AS option_seq
					, s.supply_price	AS supply_price
				FROM
					fm_goods			AS g
				LEFT JOIN
					fm_goods_supply		AS s
					ON g.goods_seq = s.goods_seq
				WHERE
					g.provider_seq = 1 AND
					g.goods_seq IN ({$inString})
			";


			$query			= $this->db->query($suppSql, $goodsSeqList);
			$totalUpdateCnt	+= $query->result_id->num_rows;
			foreach ((array)$query->result_array() as $row) {

				$optionSeq		= (int)$row['option_seq'];

				if ($optionSeq < 1)
					continue;


				$newSupplyPrice		= 0;
				eval("\$newSupplyPrice = ".$row['supply_price'].$adjustmentUnit.$adjustmentPrice.";");

				$newSupplyPrice		= ($newSupplyPrice < 0) ? 0 : abs($newSupplyPrice);
				$newSupplyPrice		= cutting_number($newSupplyPrice, $cuttingPosition, $cuttingMode);

				$supplyUpdateParams['supply_price']		= $newSupplyPrice;

				$this->db->where('option_seq', $optionSeq);
				$this->db->update('fm_goods_supply', $supplyUpdateParams);

			}

		}


		if ($totalUpdateCnt < 1)
			return '매입가 변경이 가능한 상품이 없습니다.';

		return true;
	}

	protected function _priceSet($params, $goodsPageList, $priceType) {


		if (($priceType == 'all' || $priceType == 'consumerPrice') && preg_match('/[^0-9|^.]/', $params['batch_consumer_price']) == 1)
			return "사용할 수 없는 정가 입니다.";

		if (($priceType == 'all' || $priceType == 'salePrice') && preg_match('/[^0-9|^.]/', $params['batch_price']) == 1)
			return "사용할 수 없는 판매가 입니다.";


		// 정가 계산용
		if ($priceType == 'all' || $priceType == 'consumerPrice') {

			if ($params['batch_consumer_price_unit'] == 'percent') {
				$adjustmentConsumerUnit			= '*';

				if ($params['batch_consumer_price_updown'] == 'down')
					$adjustmentConsumerPrice	= (100 - $params['batch_consumer_price']) / 100;
				else
					$adjustmentConsumerPrice	= (100 + $params['batch_consumer_price']) / 100;


			} else {
				$adjustmentConsumerUnit			= ($params['batch_consumer_price_updown'] == 'down') ? '-' : '+';
				$adjustmentConsumerPrice		= $params['batch_consumer_price'];
			}

		}

		// 판매가 계산용
		if ($priceType == 'all' || $priceType == 'salePrice') {

			if ($params['batch_price_unit'] == 'percent') {
				$adjustmentSaleUnit			= '*';

				if ($params['batch_price_updown'] == 'down')
					$adjustmentSalePrice	= (100 - $params['batch_price']) / 100;
				else
					$adjustmentSalePrice	= (100 + $params['batch_price']) / 100;


			} else {
				$adjustmentSaleUnit			= ($params['batch_price_updown'] == 'down') ? '-' : '+';
				$adjustmentSalePrice		= $params['batch_price'];
			}

		}


		$optionUpdateList		= array();
		$goodsUpdateList		= array();

		foreach((array)$goodsPageList as $goodsSeqList) {
			$optionList			= $this->getOptionsInfo($goodsSeqList);

			foreach ((array)$optionList	 as $row) {

				$optionSeq		= (int)$row['option_seq'];

				if ($optionSeq < 1)
					continue;


				$nowOptionInfo	= array();

				// 정가 계산
				if ($priceType == 'all' || $priceType == 'consumerPrice') {
					$newConsumerPrice					= 0;
					eval("\$newConsumerPrice = ".$row['consumer_price'].$adjustmentConsumerUnit.$adjustmentConsumerPrice.";");

					$nowOptionInfo['consumer_price']	= get_currency_price($newConsumerPrice, 1);
					$goodsUpdateList[]					= $row['goods_seq'];
				}


				//판매가 계산
				if ($priceType == 'all' || $priceType == 'salePrice') {
					$newSalePrice				= 0;
					eval("\$newSalePrice = ".$row['price'].$adjustmentSaleUnit.$adjustmentSalePrice.";");

					$nowOptionInfo['price']		= get_currency_price($newSalePrice, 1);
					$goodsUpdateList[]					= $row['goods_seq'];
				}

				$optionUpdateList[$optionSeq]	= $nowOptionInfo;
			}

		}


		if (count($optionUpdateList) < 1)
			return '정가 또는 판매가 변경이 가능한 상품이 없습니다.';

		$this->_doOptionsUpdate($optionUpdateList);

		// 입점사 수정 상품 상품 상태 변경
		if(defined('__SELLERADMIN__') === true) {
			$goodsUpdateList	= array_values(array_unique($goodsUpdateList));
			$statusReason		= "입점관리자 {$this->providerInfo['provider_id']}에 의해 일괄수정되었습니다.";

			$goodsStatus['provider_status']				= 0;
			$goodsStatus['goods_status']				= 'unsold';
			$goodsStatus['provider_status_reason_type']	= 3;
			$goodsStatus['provider_status_reason']		= $statusReason;

			$this->_doGoodsGroupUpdate($goodsStatus, $goodsUpdateList);
		}

		return true;

	}

	// 상품명/간략설명/검색/회원혜택/과세 직접 업데이트 추가처리
	protected function _goodsArrange($params, $goodsSeqList, $optionSeqList, &$arrangeData) {

		foreach($arrangeData['goods'] as $goodsSeq => $goods){

			if($goods["color_pick"]){
				$arrangeData['goods'][$goodsSeq]['color_pick'] = implode(",",$goods["color_pick"]);
			}
		}
		return true;
	}

	// 상품명/간략설명/검색/회원혜택/과세 조건 업데이트 추가처리
	protected function _ifgoodsArrange($params, $goodsSeqList, $optionSeqList, &$arrangeData) {

		if(is_array($arrangeData['goodsGroup']["color_pick"])){
			$arrangeData['goodsGroup']["color_pick"] = implode(",",$arrangeData['goodsGroup']["color_pick"]);
		}

		return true;
	}

	// 승인/노출/상태/성인/재고판매/구매대행/청약철회 직접 업데이트 추가처리
	protected function _statusArrange($params, $goodsSeqList, $optionSeqList, &$arrangeData) {

		$goodsUpdate = $arrangeData['goods'];

		$goodsStatusEtc = array();

		foreach($goodsUpdate as $goodsSeq => $goods){

			// 개별 정책
			if ($params["runout_type"][$goodsSeq] == 'goods') {

				$ableStockLimit = 0;
				if ($goods['runout_policy'] == "ableStock") {
					$ableStockLimit = $goods['able_stock_limit']+1;
				}

			//통합 정책
			} else {

				$goods['able_stock_limit']			= 0;
				$goods["runout_policy"]				= $this->cfg_order['runout'];	//정상/품절 자동계산을 위해 통합정책 적용.
				$ableStockLimit						= 0;

				if ($goods["runout_policy"] == 'ableStock') {
					$ableStockLimit = $this->cfg_order['ableStockLimit']+1;
				}
			}

			//승인/미승인
			if(serviceLimit('H_AD') && $goods["provider_status"] != '1'){
				$goods['provider_status_reason_type']	= '2';
				$goods['provider_status_reason']		= '관리자 ' . $this->managerInfo['manager_id'] . '에 의해 일괄 미승인 처리되었습니다.';
				$goods['goods_status']					= "unsold";	//판매중지 처리
			}

			//상품 상태 : 정상 또는 품절 자동 계산
			if($goods['goods_status'] == "normal_runout") {

				$result_info_arr = $this->getOptionsGoodsStatus($goodsSeq,$goods['runout_policy'],$ableStockLimit,$this->cfg_order['ableStockStep']);

				//통합정책일시 runout_policy = ''
				if ($params["runout_type"][$goodsSeq] == 'shop'){ $goods['runout_policy'] = ''; }

				// 정상에서 품절로 변경되는 상품명
				if ($result_info_arr['runout_gname']) {
					$goodsStatusEtc['runout_gname'][] = $result_info_arr['runout_gname'];
					$goodsStatusEtc['runout_cnt']++;
				}

				// 품절에서 정상으로 변경되는 상품명
				if ($result_info_arr['normal_gname']) {
					$goodsStatusEtc['normal_gname'][] = $result_info_arr['normal_gname'];
					$goodsStatusEtc['normal_cnt']++;
				}

				// 변경되는 상품 상태
				$goods['goods_status'] = $result_info_arr['goods_status'];

			}

			if ($params["runout_type"][$goodsSeq] == 'shop')
				$goods["runout_policy"]					= '';

			$goodsUpdate[$goodsSeq] = $goods;
		}

		$arrangeData['goods']			= $goodsUpdate;
		$arrangeData['goodsStatusEtc']	= $goodsStatusEtc;

		return true;
	}

	// 승인/노출/상태/성인/재고판매/구매대행/청약철회 조건 업데이트 추가처리
	protected function _ifstatusArrange($params, &$goodsSeqList, $optionSeqList, &$arrangeData) {

		$goodsStatusEtc = array();
		$goodsUpdate	= array();

		// 개별 정책
		if ($arrangeData["goodsGroup"]["runout_type"] == 'goods') {

			$arrangeData['goodsGroup']['runout_policy']		= $params['batch_runout_policy'];
			$arrangeData['goodsGroup']['able_stock_limit']	= $params['batch_able_stock_limit'];

			$ableStockLimit = 0;
			if ($arrangeData['goodsGroup']['runout_policy'] == "ableStock") {
				$ableStockLimit		= $arrangeData["goodsGroup"]['able_stock_limit']+1;
			}

		//통합 정책
		}elseif ($arrangeData["goodsGroup"]["runout_type"] == 'shop') {

			$arrangeData['goodsGroup']['able_stock_limit']	= 0;
			//정상/품절 자동계산을 위해 통합정책 적용.
			$arrangeData['goodsGroup']["runout_policy"]		= $this->cfg_order['runout'];
			$ableStockLimit									= 0;

			if ($arrangeData['goodsGroup']["runout_policy"] == 'ableStock') {
				$ableStockLimit = $this->cfg_order['ableStockLimit']+1;
			}
		}

		//승인/미승인
		if(serviceLimit('H_AD') && isset($arrangeData["goodsGroup"]["provider_status"]) && $arrangeData["goodsGroup"]["provider_status"] != '1'){
			$arrangeData["goodsGroup"]['provider_status_reason_type']	= '2';
			$arrangeData["goodsGroup"]['provider_status_reason']		= '관리자 ' . $this->managerInfo['manager_id'] . '에 의해 일괄 미승인 처리되었습니다.';
			$arrangeData["goodsGroup"]['goods_status']					= "unsold";	//판매중지 처리
		}


		//상품 상태 : 정상 또는 품절 자동 계산
		if($arrangeData['goodsGroup']['goods_status'] == "normal_runout") {

			unset($arrangeData['goodsGroup']['goods_status']);

			foreach($goodsSeqList as $goodsSeq){

				// 재고에 따른 판매여부 업데이트를 하지 않을 시 상품의 기존 정책 가져오기
				if(!$arrangeData["goodsGroup"]["runout_type"] || !$arrangeData["goodsGroup"]["provider_status"]){
					$goodsData		= $this->goodsmodel->get_goods($goodsSeq);
				}
				if(serviceLimit('H_AD') && !$arrangeData["goodsGroup"]["provider_status"]){
					$provider_status	= $goodsData['provider_status'];
					//승인된 상품이 아니면
					if($provider_status != 1){
						$goodsStatusEtc['status_gname'][] = "미승인상태 : ".$goodsData['goods_name'];
						continue;
					}
				}
				if(!$arrangeData["goodsGroup"]["runout_type"]){
					$runout_policy	= $goodsData['runout_policy'];
					if(!$runout_policy) $runout_policy = $this->cfg_order['runout'];
				}else{
					$runout_policy = $arrangeData["goodsGroup"]['runout_policy'];
				}

				if(!$ableStockLimit) {
					$ableStockLimit = 0;
					// 개별 정책
					if ($goodsData["runout_policy"]) {
						if ($goodsData['runout_policy'] == "ableStock") {
							$ableStockLimit = $goodsData['able_stock_limit']+1;
						}

					//통합 정책
					}elseif (!$goodsData["runout_type"]) {
						if ($this->cfg_order['runout'] == 'ableStock') {
							$ableStockLimit = $this->cfg_order['ableStockLimit']+1;
						}
					}
				}

				$result_info_arr = $this->getOptionsGoodsStatus($goodsSeq,$runout_policy,$ableStockLimit,$this->cfg_order['ableStockStep']);

				// 정상에서 품절로 변경되는 상품명
				if ($result_info_arr['runout_gname']) {
					$goodsStatusEtc['runout_gname'][] = $result_info_arr['runout_gname'];
					$goodsStatusEtc['runout_cnt']++;
				}

				// 품절에서 정상으로 변경되는 상품명
				if ($result_info_arr['normal_gname']) {
					$goodsStatusEtc['normal_gname'][] = $result_info_arr['normal_gname'];
					$goodsStatusEtc['normal_cnt']++;
				}
				// 변경되는 상품 상태
				$goodsUpdate[$goodsSeq]['goods_status'] = $result_info_arr['goods_status'];
			}
		}

		//통합정책일시 runout_policy = ''
		if ($arrangeData["goodsGroup"]["runout_type"] == 'shop'){ $arrangeData['goodsGroup']['runout_policy'] = ''; }

		unset($arrangeData["goodsGroup"]["runout_type"]);

		$arrangeData['goods']			= $goodsUpdate;
		$arrangeData['goodsStatusEtc']	= $goodsStatusEtc;

		return true;
	}

	protected function _shippingArrange($params, $goodsSeqList, $optionSeqList, &$arrangeData) {
		if	(!$params['sel_shipping_group_seq']){
			$msg = "배송그룹을 선택하여 주세요.";
			return $msg;
		}

		if	($params['sel_provider_type'] == 'base')	$params['sel_provider_seq'] = 1;
		if	($params['sel_provider_seq'] > 1){ // 입점사 업데이트 시
			$whereAdd	= " AND provider_seq = '" . $params['sel_provider_seq'] . "'";
		}

		// 본사의 기본그룹 추출
		if	($params['sel_shipping_group_seq'] == 'trust_ship'){
			$default_sql	= "SELECT * FROM fm_shipping_grouping WHERE shipping_provider_seq = 1 AND hidden_grp = 'N' AND default_yn = 'Y'";
			$default_query	= $this->db->query($default_sql);
			$default_res	= $default_query->result_array();
			$default_ship	= $default_res[0];

			if($params['shipping_grp_sub'])
				$default_ship['shipping_group_seq'] = $params['shipping_grp_sub'];
		}

		// 변경할 데이터 추출
		$sql		= "
			SELECT * FROM fm_goods
			WHERE
				goods_seq IN ('" . implode("', '", $goodsSeqList) . "')
				" . $whereAdd;
		$query		= $this->db->query($sql);
		$targetList	= $query->result_array();

		foreach($targetList as $key => $goods_info){
			if	($params['sel_provider_seq'] == $goods_info['provider_seq'] && $params['sel_shipping_group_seq'] != 'trust_ship'){
				$goodsUpdate[$goods_info['goods_seq']]['trust_shipping'] = 'N';
				$shipping_group_seq = ($default_ship['shipping_group_seq']) ? $default_ship['shipping_group_seq'] : $params['sel_shipping_group_seq'];
			}else{
				$goodsUpdate[$goods_info['goods_seq']]['trust_shipping'] = 'Y';
				$shipping_group_seq = ($default_ship['shipping_group_seq']) ? $default_ship['shipping_group_seq'] : $params['sel_shipping_group_seq'];
			}
			$goodsUpdate[$goods_info['goods_seq']]['shipping_group_seq'] = $shipping_group_seq;
		}

		if	($goodsUpdate)	$arrangeData['goods'] = $goodsUpdate;

		return true;
	}

	// 상품 재고 일괄 업데이트 추가처리 :: 2016-11-28 lwh
	protected function _goodsetcArrange($params, $goodsSeqList, $optionSeqList, &$arrangeData) {

		$this->load->model('scmmodel');

		$optionSeqList		= array();
		if (is_array($params['option_seq']) === true) {
			foreach ($params['option_seq'] as $optionSeq => $goodsSeq) {
				unset($updateData);

				if(array_search($goodsSeq, $goodsSeqList) !== false){
					if	($this->scmmodel->chkScmConfig(true)){
						$sql	= "update fm_goods_supply s, fm_goods g set "
								. "s.stock = ?, s.badstock = ? "
								. "where s.option_seq = ? and "
								. "s.goods_seq = g.goods_seq and g.provider_seq > 1 ";
						$this->db->query($sql, array(	$params['detail_stock'][$optionSeq],
														$params['detail_badstock'][$optionSeq],
														$optionSeq));
					}else{
						$updateData['stock']		= $params['detail_stock'][$optionSeq];
						$updateData['badstock']		= $params['detail_badstock'][$optionSeq];
					}
					$updateData['safe_stock']	= $params['detail_safe_stock'][$optionSeq];
					$this->db->where('option_seq', $optionSeq);
					$result	= $this->db->update('fm_goods_supply', $updateData);
				}
			}
		}

		$defaultOptionSeqList				= array();
		if (is_array($params['default_option_seq']) === true) {
			foreach ($params['default_option_seq'] as $optionSeq => $goodsSeq) {
				unset($updateData);
				if(array_search($goodsSeq, $goodsSeqList) !== false){
					if	($this->scmmodel->chkScmConfig(true)){
						$sql	= "update fm_goods_supply s, fm_goods g set "
								. "s.stock = ?, s.badstock = ? "
								. "where s.goods_seq = ? and "
								. "s.goods_seq = g.goods_seq and g.provider_seq > 1 ";
						$this->db->query($sql, array(	$params['stock'][$optionSeq],
														$params['badstock'][$optionSeq],
														$goodsSeq));
					}else{
						$updateData['stock']		= $params['stock'][$optionSeq];
						$updateData['badstock']		= $params['badstock'][$optionSeq];
					}
					$updateData['safe_stock']	= $params['safe_stock'][$optionSeq];
					$this->db->where('goods_seq', $goodsSeq);
					$result	= $this->db->update('fm_goods_supply', $updateData);
				}
			}
		}

		foreach($goodsSeqList as $goodsSeq) {
			$result_info_arr = $this->getOptionsGoodsStatus($goodsSeq,"","","");

			// 정상에서 품절로 변경되는 상품명
			if ($result_info_arr['runout_gname']) {
				$goodsStatusEtc['runout_gname'][] = $result_info_arr['runout_gname'];
				$goodsStatusEtc['runout_cnt']++;
			}

			// 품절에서 정상으로 변경되는 상품명
			if ($result_info_arr['normal_gname']) {
				$goodsStatusEtc['normal_gname'][] = $result_info_arr['normal_gname'];
				$goodsStatusEtc['normal_cnt']++;
			}

			// 변경되는 상품 상태
			$goodsUpdate[$goodsSeq]['goods_status'] = $result_info_arr['goods_status'];

			// 상품 코드 변경 값 초기화 문제로 추가 :: 2019-01-30 lkh
			$goodsUpdate[$goodsSeq]['goods_code'] = $arrangeData['goods'][$goodsSeq]['goods_code'];

		}
		$arrangeData['goods']			= $goodsUpdate;
		$arrangeData['goodsStatusEtc']	= $goodsStatusEtc;


		return true;
	}

	// 상품 재고 조건 업데이트 추가처리 :: 2016-11-29 lwh
	protected function _ifgoodsetcArrange($params, $goodsSeqList, $optionSeqList, &$arrangeData) {
		if($params['batch_goodscode_yn']){
			$str_goods_seq = implode(',',$goodsSeqList);
			$rescode = goodscodemulti($str_goods_seq);

			foreach($rescode as $goods_seq => $goods_code){
				$this->db->where('goods_seq', $goods_seq);
				$result	= $this->db->update('fm_goods', array('goods_code'=>$goods_code));
			}
		}

		foreach($goodsSeqList as $k => $goods_seq){
			unset($updateData);
			if($params['batch_badstock_yn'])
				$updateData['badstock']		= 0;
			if($params['batch_stock_yn'] && isset($params['batch_stock_value']))
				$updateData['stock']		= $params['batch_stock_value'];
			if($params['batch_safe_stock_yn'])
				$updateData['safe_stock']	= $params['batch_safe_stock_value'];

			if($updateData){
				$this->db->where('goods_seq', $goods_seq);
				$result	= $this->db->update('fm_goods_supply', $updateData);
			}

			// 재고 변경 시에는 상품 상태도 수정해줘야함
			if($params['batch_stock_yn'] && isset($params['batch_stock_value'])) {

				$result_info_arr = $this->getOptionsGoodsStatus($goods_seq,"","","");

				// 정상에서 품절로 변경되는 상품명
				if ($result_info_arr['runout_gname']) {
					$goodsStatusEtc['runout_gname'][] = $result_info_arr['runout_gname'];
					$goodsStatusEtc['runout_cnt']++;
				}

				// 품절에서 정상으로 변경되는 상품명
				if ($result_info_arr['normal_gname']) {
					$goodsStatusEtc['normal_gname'][] = $result_info_arr['normal_gname'];
					$goodsStatusEtc['normal_cnt']++;
				}
				// 변경되는 상품 상태
				$goodsUpdate[$goods_seq]['goods_status'] = $result_info_arr['goods_status'];
			}
		}
		$arrangeData['goods']			= $goodsUpdate;
		$arrangeData['goodsStatusEtc']	= $goodsStatusEtc;

		return true;
	}

	// 워터마크 일괄업데이트 추가처리
	protected function _watermarkArrange($params, $goodsSeqList, $optionSeqList, &$arrangeData) {

		//200개 대용량 처리 시 중단되는것을 방지 2019-06-21
		set_time_limit(0);

		$this->load->model('watermarkmodel');

		if($params['remove_watermark'] != 1){
			$this->watermarkmodel->watermark_setting();
		}

		$r_target_type = array('large','view');
		foreach($goodsSeqList as $goodsSeq){
			$r_images = $this->goodsmodel->get_goods_image($goodsSeq);

			$this->watermarkmodel->goods_seq = $goodsSeq;
			foreach($r_images as $r_image){
				for($i=0;$i<4;$i++){
					$field = $r_target_type[$i];
					$image = $r_image[$field]['image'];
					$image_src = str_replace('//','/',ROOTPATH.$image);

					if( $image && file_exists($image_src) )
					{
						$this->watermarkmodel->target_image = $image_src;
						if($params['remove_watermark']==1){
							$this->watermarkmodel->recovery();
						}else{
							$this->watermarkmodel->source_image_cp();
							$this->watermarkmodel->watermark();
						}
					}
				}
			}
		}

		return true;
	}

	// 공용정보업데이트 추가처리
	protected function _commoninfoArrange($params, $goodsSeqList, $optionSeqList, &$arrangeData) {

		$info_seq	= $arrangeData['goodsGroup']['info_seq'];
		$query		= $this->db->query("select info_value from fm_goods_info where info_seq=?",array($info_seq));
		$info_data	= $query->result_array();
		$arrangeData['goodsGroup']['common_contents'] = $info_data[0]['info_value'];

		return true;
	}

	// 대량구매 혜택 업데이트 추가처리
	protected function _multidiscountArrange($params, $goodsSeqList, $optionSeqList, &$arrangeData) {

		//일괄 15 설정시 체크안한 값은 초기화
		if (isset($params['batch_multidiscount']) === false){
			return true;
		}

		// 사용안함으로 업데이트 시
		if($params['multiDiscountSet'] != "y"){
			$arrangeData['goodsGroup']['multi_discount_policy']	= '';
			return true;
		}

		if (count($params['discountUnderQty']) > 0) {

			$discountPolicy							= array();
			foreach ($params['discountUnderQty'] as $key => $val) {
				$nowPolicy							= array();
				$nowPolicy['discountOverQty']		= $params['discountOverQty'][$key];
				$nowPolicy['discountUnderQty']		= $val;
				$nowPolicy['discountAmount']		= $params['discountAmount'][$key];

				$discountPolicy['policyList'][]		= $nowPolicy;
			}

			$discountPolicy['discountMaxOverQty']	= $params['discountMaxOverQty'];
			$discountPolicy['discountMaxAmount']	= $params['discountMaxAmount'];
			$discountPolicy['discountUnit']			= ($params['discountUnit'] == 'PRI') ? 'PRI' : 'PER';

			$arrangeData['goodsGroup']['multi_discount_policy']	 = json_encode($discountPolicy);
		}else{

			//00개 이상이라는 단일 조건일때 상품상세에서 저장하는것과 구조가 달라 저장이 정상적으로 안되어 변경함.
			$discountPolicy							= array();
			$nowPolicy							= array();
			$nowPolicy['discountOverQty']		= $params['discountOverQty'];
			$nowPolicy['discountUnderQty']		= null;
			$nowPolicy['discountAmount']		= $params['discountMaxAmount'];
			$discountPolicy['policyList'][]		= $nowPolicy;
			$discountPolicy['discountMaxOverQty']	= null;
			$discountPolicy['discountMaxAmount']	= null;
			$discountPolicy['discountUnit']			= ($params['discountUnit'] == 'PRI') ? 'PRI' : 'PER';
			$arrangeData['goodsGroup']['multi_discount_policy']	 = json_encode($discountPolicy);

		}
		return true;
	}

	protected function _categoryArrange($params, $goodsSeqList, $optionSeqList, &$arrangeData) {
		$mode	= $params['target_modify'];
		$act	= $params['search_'.$mode.'_mode'];

		switch($mode){
			case 'category':
				$source_code = $params['category1'];
				if($params['category2']) $source_code = $params['category2'];
				if($params['category3']) $source_code = $params['category3'];
				if($params['category4']) $source_code = $params['category4'];
			break;
			case 'brand':
				$source_code = $params['brands1'];
				if($params['brands2']) $source_code = $params['brands2'];
				if($params['brands3']) $source_code = $params['brands3'];
				if($params['brands4']) $source_code = $params['brands4'];
			break;
			case 'location':
				$source_code = $params['location1'];
				if($params['location2']) $source_code = $params['location2'];
				if($params['location3']) $source_code = $params['location3'];
				if($params['location4']) $source_code = $params['location4'];
			break;
			default:
				$callback	= "";
				$msg		= "error";
				openDialogAlert($msg,400,140,'parent',$callback);
				exit;
			break;
		}

		$code = $params[$act.'_'.$mode.'1'];
		if($params[$act.'_'.$mode.'2']) $code = $params[$act.'_'.$mode.'2'];
		if($params[$act.'_'.$mode.'3']) $code = $params[$act.'_'.$mode.'3'];
		if($params[$act.'_'.$mode.'4']) $code = $params[$act.'_'.$mode.'4'];

		if($act == 'del'){
			$this->{'_categoryArrange_'.$act}($goodsSeqList,$code,$source_code,$mode,1,1);
		}else if($act){
			$this->{'_categoryArrange_'.$act}($goodsSeqList,$code,$source_code,$mode);
		}

		// 카테고리/브랜드/지역 변경에 따른 할인혜택 금액 저장
		$this->load->model('goodssummarymodel');
		$this->goodssummarymodel->set_event_price(array('goods'=>$goodsSeqList));

		return true;
	}

	/*
	* 일괄 업데이트시 카테고리/브랜드/지역 연결
	*/
	protected function _categoryArrange_add($r_goods_seq,$code,$source_code,$mode)
	{
		if(!$code){
			$callback = "";
			if($mode == 'category') $msg = "연결할 카테고리를 선택하세요!";
			elseif($mode == 'brand')  $msg = "연결할 브랜드를 선택하세요!";
			else  $msg = "연결할 지역을 선택하세요!";
			openDialogAlert($msg,400,140,'parent',$callback);
			exit;
		}
		$this->load->model('categorymodel');
		$this->load->model('brandmodel');
		$this->load->model('locationmodel');
		if	($mode == 'category'){
			$minsort		= $this->categorymodel->getSortValue($code, 'min');
			$mobile_minsort	= $this->categorymodel->getSortValue($code, 'mobile_min');
			$codeName		= 'category';
		}else if	($mode == 'brand'){
			$minsort		= $this->brandmodel->getSortValue($code, 'min');
			$mobile_minsort	= $this->brandmodel->getSortValue($code, 'mobile_min');
			$codeName			= 'category';
		}else if	($mode == 'location'){
			$minsort		= $this->locationmodel->getSortValue($code, 'min');
			$mobile_minsort	= $this->locationmodel->getSortValue($code, 'mobile_min');
			$codeName			= 'location';
		}else{
			$msg = "error";
			openDialogAlert($msg,400,140,'parent',$callback);
			exit;
		}

		$table 	= "fm_".$mode."_link";
		$r_code = $this->categorymodel->split_category($code);
		foreach($r_goods_seq as $goods_seq){

			$query 		= "select count(*) cnt from ".$table." where goods_seq=? and link";
			$query 		= $this->db->query($query,array($goods_seq));
			$data 		= $query->row_array();
			$link_cnt 	= $data['cnt'];

			foreach($r_code as $k => $category_code){
				$last_k = count($r_code)-1;
				$query 	= "select count(*) cnt from ".$table." where goods_seq=? and ".$codeName."_code=?";
				$query 	= $this->db->query($query,array($goods_seq,$category_code));
				$data 	= $query->row_array();
				if($data['cnt'] > 0) continue;

				$minsort 		-= 1;
				$mobile_minsort	-= 1;

				if($link_cnt == 0 && $last_k == $k) $link = 1; else $link = 0;

				$r_insert						= [];
				$r_insert['link'] 				= $link;
				$r_insert[$codeName.'_code'] 			= $category_code;
				$r_insert['goods_seq'] 			= $goods_seq;
				$r_insert['sort'] 				= $minsort;						//상품 PC 정렬 순서
				$r_insert['mobile_sort'] 		= $mobile_minsort;				//상품 Mobile 정렬 순서
				$r_insert['regist_date'] 		= date('Y-m-d H:i:s',time());
				$result = $this->db->insert($table, $r_insert);
			}
		}
	}

	protected function _categoryArrange_move($r_goods_seq,$code,$source_code,$mode)
	{
		if(!$code){
			$callback = "";
			if($mode == 'category') $msg = "연결할 카테고리를 선택하세요!";
			elseif($mode == 'brand')  $msg = "연결할 브랜드를 선택하세요!";
			else  $msg = "연결할 지역을 선택하세요!";
			openDialogAlert($msg,400,140,'parent',$callback);
			exit;
		}
		$this->_batch_modify_del($r_goods_seq,$code,$source_code,$mode,1);
		$this->_batch_modify_add($r_goods_seq,$code,$source_code,$mode);
	}

	public function _batch_modify_del($r_goods_seq,$code,$source_code,$mode,$move_act=0,$except_link=0)
	{
		if(!$source_code){
			$callback = "";
			if($mode == 'category') $msg = "카테고리를 검색하세요!";
			else  $msg = "브랜드를 검색하세요!";
			openDialogAlert($msg,400,140,'parent',$callback);
			exit;
		}

		$table = "fm_".$mode."_link";
		foreach($r_goods_seq as $goods_seq){
			if($mode=='location'){
				if( $move_act == 1 ){
					$query = "delete from ".$table." where goods_seq=? and location_code like '".$source_code."%'";
				}else{
					$query = "delete from ".$table." where goods_seq=? and location_code not like '".substr($source_code,0,4)."%'";
				}
				$cnt = 0;
				if($except_link){
					$query_except = "select count(*) cnt from $table where link=1 and location_code like '".$source_code."%' and goods_seq=?";
					$query_except =  $this->db->query($query_except,array($goods_seq));
					$data = $query_except->row_array();
					$cnt = $data['cnt'];
				}
				if($cnt==0) $this->db->query($query,array($goods_seq));
			}else{
				if( $move_act == 1 ){
					$query = "delete from ".$table." where goods_seq=? and category_code like '".$source_code."%'";
				}else{
					$query = "delete from ".$table." where goods_seq=? and category_code not like '".substr($source_code,0,4)."%'";
				}
				$cnt = 0;
				if($except_link){
					$query_except = "select count(*) cnt from $table where link=1 and category_code like '".$source_code."%' and goods_seq=?";
					$query_except =  $this->db->query($query_except,array($goods_seq));
					$data = $query_except->row_array();
					$cnt = $data['cnt'];
				}
				if($cnt==0) $this->db->query($query,array($goods_seq));
			}
		}
	}

	public function _batch_modify_add($r_goods_seq,$code,$source_code,$mode)
	{
		if(!$code){
			$callback = "";
			if($mode == 'category') $msg = "연결할 카테고리를 선택하세요!";
			else  $msg = "연결할 브랜드를 선택하세요!";
			openDialogAlert($msg,400,140,'parent',$callback);
			exit;
		}
		$this->load->model('categorymodel');
		$this->load->model('brandmodel');
		$this->load->model('locationmodel');
		if	($mode == 'category'){
			$minsort		= $this->categorymodel->getSortValue($code, 'min');
			$mobile_minsort	= $this->categorymodel->getSortValue($code, 'mobile_min');
			$codeName		= 'category';
		}else if	($mode == 'brand'){
			$minsort		= $this->brandmodel->getSortValue($code, 'min');
			$mobile_minsort	= $this->brandmodel->getSortValue($code, 'mobile_min');
			$codeName		= 'category';
		}else if	($mode == 'location'){
			$minsort		= $this->locationmodel->getSortValue($code, 'min');
			$mobile_minsort	= $this->locationmodel->getSortValue($code, 'mobile_min');
			$codeName		= 'location';
		}else{
			$msg = "error";
			openDialogAlert($msg,400,140,'parent',$callback);
			exit;
		}

		$table = "fm_".$mode."_link";
		$r_code = $this->categorymodel->split_category($code);
		foreach($r_goods_seq as $goods_seq){
			$query 		= "select count(*) cnt from ".$table." where goods_seq=? and link";
			$query 		= $this->db->query($query,array($goods_seq));
			$data 		= $query->row_array();
			$link_cnt 	= $data['cnt'];

			foreach($r_code as $k => $category_code){
				$last_k 	= count($r_code)-1;
				$query 		= "select count(*) cnt,".$codeName."_link_seq from ".$table." where goods_seq=? and ".$codeName."_code=?";
				$query 		= $this->db->query($query,array($goods_seq,$category_code));
				$data 		= $query->row_array();
				if($data['cnt'] > 0) {
					//link_cnt=0 이고 마지막 카테고리이면 대표카테고리로 update 2018-03-27
					if( $link_cnt == 0 && $last_k == $k ){
						$this->db->where($codeName.'_link_seq', $data[$codeName.'_link_seq']);
						$this->db->update($table, array('link'=>1));
					}
					continue;
				}

				$minsort	 	-= 1;
				$mobile_minsort -= 1;

				if($link_cnt == 0 && $last_k == $k) $link = 1; else $link = 0;

				$r_insert						= [];
				$r_insert['link'] 				= $link;
				$r_insert[$codeName.'_code'] 	= $category_code;
				$r_insert['goods_seq'] 			= $goods_seq;
				$r_insert['sort'] 				= $minsort;
				$r_insert['mobile_sort'] 		= $mobile_minsort;
				$r_insert['regist_date'] 		= date('Y-m-d H:i:s',time());
				$result = $this->db->insert($table, $r_insert);
			}
		}
	}

	public function _batch_modify_all_del($r_goods_seq,$code,$source_code,$mode)
	{
		$table = "fm_".$mode."_link";
		foreach($r_goods_seq as $goods_seq){
			$query = "delete from ".$table." where goods_seq=?";
			$this->db->query($query,array($goods_seq));
		}
	}

	protected function _categoryArrange_copy($r_goods_seq,$code,$source_code,$mode)
	{
		if(!$code){
			$callback = "";
			if($mode == 'category') $msg = "연결할 카테고리를 선택하세요!";
			elseif($mode == 'brand')  $msg = "연결할 브랜드를 선택하세요!";
			else  $msg = "연결할 지역을 선택하세요!";
			openDialogAlert($msg,400,140,'parent',$callback);
			exit;
		}

		foreach($r_goods_seq as $goods_seq){
			$oldSeq = $goods_seq;

			### FM_GOODS
			$goodSeq = $this->goodsmodel->copy_goods($oldSeq);

			### GOODS_DEFAULT
			if($mode == 'brand') {
				$result = $this->goodsmodel->copy_goods_default('fm_category_link', $oldSeq, $goodSeq, 'category_link_seq');
				$result = $this->goodsmodel->copy_goods_default('fm_location_link', $oldSeq, $goodSeq, 'location_link_seq');
			}
			if($mode == 'category') {
				$result = $this->goodsmodel->copy_goods_default('fm_brand_link', $oldSeq, $goodSeq, 'category_link_seq');
				$result = $this->goodsmodel->copy_goods_default('fm_location_link', $oldSeq, $goodSeq, 'location_link_seq');
			}
			if($mode == 'location') {
				$result = $this->goodsmodel->copy_goods_default('fm_category_link', $oldSeq, $goodSeq, 'category_link_seq');
				$result = $this->goodsmodel->copy_goods_default('fm_brand_link', $oldSeq, $goodSeq, 'category_link_seq');
			}

			$result = $this->goodsmodel->copy_goods_default('fm_goods_addition', $oldSeq, $goodSeq, 'addition_seq');
			$result = $this->goodsmodel->copy_goods_default('fm_goods_icon', $oldSeq, $goodSeq, 'icon_seq');
			$result = $this->goodsmodel->copy_goods_default('fm_goods_input', $oldSeq, $goodSeq, 'input_seq');
			$result = $this->goodsmodel->copy_goods_default('fm_goods_relation', $oldSeq, $goodSeq, 'relation_seq');
			$result = $this->goodsmodel->copy_goods_default('fm_goods_socialcp_cancel', $oldSeq, $goodSeq, 'seq');
			$result = $this->goodsmodel->copy_goods_default('fm_goods_list_summary', $oldSeq, $goodSeq, 'randomseq_nonexists_at_database');

			### OPTION : fm_goods_option, fm_goods_suboption, fm_goods_supply
			$result = $this->goodsmodel->copy_goods_option($oldSeq, $goodSeq);

			### GOODS_IMAGE
			$result = $this->goodsmodel->copy_goods_image('fm_goods_image', $oldSeq, $goodSeq, 'image_seq');
			$r_new_goods_seq[] = $goodSeq;
		}

		$this->_batch_modify_add($r_new_goods_seq,$code,$source_code,$mode);
	}

	protected function _categoryArrange_del($r_goods_seq,$code,$source_code,$mode,$move_act=0,$except_link=0)
	{

		if(!$source_code){
			$callback = "";
			if($mode == 'category') $msg = "해제할 카테고리를 먼저 검색하세요!";
			elseif($mode == 'brand')  $msg = "해제할 브랜드를 먼저 검색하세요!";
			else  $msg = "해제할 지역을 먼저 검색하세요!";
			openDialogAlert($msg,400,140,'parent',$callback);
			exit;
		}

		$table = "fm_".$mode."_link";
		foreach($r_goods_seq as $goods_seq){
			if($mode=='location'){
				if( $move_act == 1 ){
					$query = "delete from ".$table." where goods_seq=? and location_code like '".$source_code."%'";
				}else{
					$query = "delete from ".$table." where goods_seq=? and location_code not like '".substr($source_code,0,4)."%'";
				}
				$cnt = 0;
				if($except_link){
					$query_except = "select count(*) cnt from $table where link=1 and location_code like '".$source_code."%' and goods_seq=?";
					$query_except =  $this->db->query($query_except,array($goods_seq));
					$data = $query_except->row_array();
					$cnt = $data['cnt'];
				}
				if($cnt==0) $this->db->query($query,array($goods_seq));
			}else{
				if( $move_act == 1 ){
					$query = "delete from ".$table." where goods_seq=? and link!=1 and category_code like '".$source_code."%'";
				}else{
					$query = "delete from ".$table." where goods_seq=? and link!=1 and category_code not like '".substr($source_code,0,4)."%'";
				}

				$cnt = 0;
				/*
				if($except_link){
					$query_except = "select count(*) cnt from $table where link=1 and category_code like '".$source_code."%' and goods_seq=?";
					$query_except =  $this->db->query($query_except,array($goods_seq));
					$data = $query_except->row_array();
					$cnt = $data['cnt'];
					debug("cnt : ".$cnt);
				}
				*/
				if($cnt==0) $this->db->query($query,array($goods_seq));
			}
		}
	}

	protected function _categoryArrange_all_del($r_goods_seq,$code,$source_code,$mode)
	{
		$table = "fm_".$mode."_link";
		foreach($r_goods_seq as $goods_seq){
			$query = "delete from ".$table." where goods_seq=?";
			$this->db->query($query,array($goods_seq));
		}
	}

	protected function _relationArrange($params, $goodsSeqList, $optionSeqList, &$arrangeData) {

		foreach ((array)$arrangeData['goods'] as $goodsSeq => $goods) {

			if	($goods['relation_type'] == 'MANUAL') {

				$del_sql = "delete from fm_goods_relation where goods_seq = ?";
				$this->db->query($del_sql,$goodsSeq);

				foreach ($params['relationGoods_'.$goodsSeq] as $relation) {

					$in_sql = "insert into fm_goods_relation set goods_seq = '".$goodsSeq."', relation_goods_seq = '".$relation."'";
					$this->db->query($in_sql);
				}
			}else{
				// 반응형일때 추천조건은 light 컬럼에 저장하도록 처리 :: 2019-04-17 pjw
				if($this->config_system['operation_type'] == 'light'){
					$arrangeData['goods'][$goodsSeq]['relation_criteria_light'] = $goods['relation_criteria'];
					unset($arrangeData['goods'][$goodsSeq]['relation_criteria']);
				}
			}

			if	($goods['relation_seller_type'] == 'MANUAL') {

				$del_sql = "delete from fm_goods_relation_seller where goods_seq = ?";
				$this->db->query($del_sql,$goodsSeq);

				foreach ($params['relationSellerGoods_'.$goodsSeq] as $relation_seller) {

					$in_sql = "insert into fm_goods_relation_seller set goods_seq = '".$goodsSeq."', relation_goods_seq = '".$relation_seller."'";
					$this->db->query($in_sql);
				}
			}else{
				// 반응형일때 추천조건은 light 컬럼에 저장하도록 처리 :: 2019-04-17 pjw
				if($this->config_system['operation_type'] == 'light'){
					$arrangeData['goods'][$goodsSeq]['relation_seller_criteria_light'] = $goods['relation_seller_criteria'];
					unset($arrangeData['goods'][$goodsSeq]['relation_seller_criteria']);
				}
			}
		}

		return true;

	}

	protected function _ifrelationArrange($params, $goodsSeqList, $optionSeqList, &$arrangeData) {
	    /**
	     * 반복문 안에 해당 부분이 있으면 unset 이후부터는 null을 대입하므로 별도 처리
	     * 2019-08-20
	     * @author Sunha Ryu
	     */
	    // 반응형일때 추천조건은 light 컬럼에 저장하도록 처리 :: 2019-06-25 hyem
	    if($this->config_system['operation_type'] == 'light') {
	        if($arrangeData['goodsGroup']['relation_type'] === 'AUTO'){
	            $arrangeData['goodsGroup']['relation_criteria_light'] = $arrangeData['goodsGroup']['relation_criteria'];
	            unset($arrangeData['goodsGroup']['relation_criteria']);
	        }

	        if($arrangeData['goodsGroup']['relation_seller_type'] === 'AUTO') {
	            $arrangeData['goodsGroup']['relation_seller_criteria_light'] = $arrangeData['goodsGroup']['relation_seller_criteria'];
	            unset($arrangeData['goodsGroup']['relation_seller_criteria']);
	        }
	    }

		if	($params['modify_list'] == 'choice') {

			foreach ($params['goods_seq'] as $key => $goodsSeq) {

				if	($arrangeData['goodsGroup']['relation_type'] == 'MANUAL') {

					$del_sql = "delete from fm_goods_relation where goods_seq = ?";
					$this->db->query($del_sql,$goodsSeq);

					foreach ($params['relationGoods'] as $relation) {

						$in_sql = "insert into fm_goods_relation set goods_seq = '".$goodsSeq."', relation_goods_seq = '".$relation."'";
						$this->db->query($in_sql);
					}
				}

				if	($arrangeData['goodsGroup']['relation_seller_type'] == 'MANUAL') {

					$del_sql = "delete from fm_goods_relation_seller where goods_seq = ?";
					$this->db->query($del_sql,$goodsSeq);

					foreach ($params['relationSellerGoods'] as $relation_seller) {

						$in_sql = "insert into fm_goods_relation_seller set goods_seq = '".$goodsSeq."', relation_goods_seq = '".$relation_seller."'";
						$this->db->query($in_sql);
					}
				}

			}
		}else{

			$arrayCount		= count($goodsSeqList);
			$maxPage		= ceil($arrayCount / $this->updateCount);
			$endPoint		= 0;
			$updateCnt		= $this->updateCount;

			for ($i = 1; $i <= $maxPage; $i++) {
				$startPoint	= $endPoint;
				$endPoint	= $i * $updateCnt;
				$endPoint	= ($endPoint > $arrayCount) ? $arrayCount : $endPoint;
				$targetList	= array();

				for ($j = $startPoint; $j < $endPoint; $j++) {
					$nowGoodsSeq	= (int)$goodsSeqList[$j];
					if ($goodsSeqList[$j] < 1)
						continue;

					if ((int)$goodsSeqList[$j] > 0)
						$targetList[]	= (int)$goodsSeqList[$j];
				}

				if (count($targetList) < 1)
					continue;

				if	($arrangeData['goodsGroup']['relation_type'] == 'MANUAL') {
					$del_sql = "delete from fm_goods_relation where goods_seq in ('" . implode("', '", $targetList) . "')";
					$this->db->query($del_sql);

					foreach ($targetList as $goodsSeq) {

						foreach ($params['relationGoods'] as $relation) {

							$in_sql = "insert into fm_goods_relation set goods_seq = '".$goodsSeq."', relation_goods_seq = '".$relation."'";
							$this->db->query($in_sql);
						}
					}
				}

				if	($arrangeData['goodsGroup']['relation_seller_type'] == 'MANUAL') {

					$del_sql = "delete from fm_goods_relation_seller where goods_seq in ('" . implode("', '", $targetList) . "')";
					$this->db->query($del_sql);

					foreach ($targetList as $goodsSeq) {

						foreach ($params['relationSellerGoods'] as $relation_seller) {

							$in_sql = "insert into fm_goods_relation_seller set goods_seq = '".$goodsSeq."', relation_goods_seq = '".$relation_seller."'";
							$this->db->query($in_sql);
						}
					}
				}
			}
		}

		return true;

	}

	protected function _ifpayArrange($params, $goodsSeqList, $optionSeqList, &$arrangeData) {

		if($_POST['batch_possible_pay_type_yn'] == '1'){
			$arrangeData['goodsGroup']['possible_pay_type']		= $arrangeData['goodsGroup']['possible_pay_type'];
			$possible_pay = $arrangeData['goodsGroup']['possible_pay'];
			if	($arrangeData['goodsGroup']['possible_pay_type'] != 'goods')
				$possible_pay = '';
			$arrangeData['goodsGroup']['possible_pay']			= $possible_pay;
			$arrangeData['goodsGroup']['possible_mobile_pay']	= $possible_pay;
		}

		return true;
	}

	protected function _iconArrange($params, $goodsSeqList, $optionSeqList, &$arrangeData) {

		if	($params['modify_list'] == 'choice') {

			foreach($goodsSeqList as $goods_seq) {

				$r_icon = $params['goodsIcon'];
				$start_date = $params['iconStartDate'];
				$end_date = $params['iconEndDate'];
				if	($params['modify_means'] != 'add') {
					$query = "delete from fm_goods_icon where goods_seq=?";
					$this->db->query($query,array($goods_seq));
				}
				if	($r_icon) {
					foreach($r_icon as $key => $codecd){
						$query = "insert into fm_goods_icon set goods_seq=?,codecd=?,start_date=?,end_date=?";
						$this->db->query($query,array($goods_seq,$codecd,$start_date[$key],$end_date[$key]));
					}
				}
				$sql = "update fm_goods set update_date = '".date('Y-m-d H:i:s')."' where goods_seq = ?";
				$this->db->query($sql,array($goods_seq));
			}
		}else{
			$arrayCount		= count($goodsSeqList);
			$maxPage		= ceil($arrayCount / $this->updateCount);
			$endPoint		= 0;
			$updateCnt		= $this->updateCount;

			for ($i = 1; $i <= $maxPage; $i++) {
				$startPoint	= $endPoint;
				$endPoint	= $i * $updateCnt;
				$endPoint	= ($endPoint > $arrayCount) ? $arrayCount : $endPoint;
				$targetList	= array();

				for ($j = $startPoint; $j < $endPoint; $j++) {
					$nowGoodsSeq	= (int)$goodsSeqList[$j];
					if ($goodsSeqList[$j] < 1)
						continue;

					if ((int)$goodsSeqList[$j] > 0)
						$targetList[]	= (int)$goodsSeqList[$j];
				}

				if (count($targetList) < 1)
					continue;

				foreach($targetList as $goods_seq) {
					$r_icon = $params['goodsIcon'];
					$start_date = $params['iconStartDate'];
					$end_date = $params['iconEndDate'];

					if	($params['modify_means'] != 'add') {
						$query = "delete from fm_goods_icon where goods_seq=?";
						$this->db->query($query,array($goods_seq));
					}
					if	($r_icon) {
						foreach($r_icon as $key => $codecd){
							$query = "insert into fm_goods_icon set goods_seq=?,codecd=?,start_date=?,end_date=?";
							$this->db->query($query,array($goods_seq,$codecd,$start_date[$key],$end_date[$key]));
						}
					}
					$sql = "update fm_goods set update_date = '".date('Y-m-d H:i:s')."' where goods_seq = ?";
					$this->db->query($sql,array($goods_seq));
				}
			}
		}

		// 아이콘 변경에 따른 할인혜택 금액 저장
		$this->load->model('goodssummarymodel');
		$this->goodssummarymodel->set_event_price(array('goods'=>$goodsSeqList));

		return true;
	}

	// 네이버,다음 쇼핑전달정보 추가 처리 :: 2018-07-10 pjw
	protected function _ifep_shippingArrange($params, $goodsSeqList, $optionSeqList, &$arrangeData){
		// 이벤트 설정
		if($arrangeData['goodsGroup']['grp_feed_evt_sdate_yn'] == '1'){
			$arrangeData['goodsGroup']['feed_evt_sdate']	= $params['grp_feed_evt_sdate'];
			$arrangeData['goodsGroup']['feed_evt_edate']	= $params['grp_feed_evt_edate'];
			$arrangeData['goodsGroup']['feed_evt_text']	= $params['grp_feed_evt_text'];
		}
		unset($arrangeData['goodsGroup']['grp_feed_evt_sdate_yn']);

		// 개별설정인 경우 추가항목
		if($arrangeData['goodsGroup']['feed_ship_type'] == 'E'){
		    if($params['grp_feed_pay_type'] == "postpay"){
		        $params['grp_feed_std_fixed'] = $params['grp_feed_std_postpay'];
		    }
			$arrangeData['goodsGroup']['feed_pay_type']		= $params['grp_feed_pay_type'];
			$arrangeData['goodsGroup']['feed_std_fixed']	= $params['grp_feed_std_fixed'];
			$arrangeData['goodsGroup']['feed_add_txt']		= $params['grp_feed_add_txt'];
		}

		return true;
	}

	// 배송그룹 수정후 추가 처리 :: 2017-08-10 lwh
	protected function _shippingAddprocess() {
		$this->load->model('shippingmodel');
		// ### 배송그룹연결상품 조정
		$this->shippingmodel->group_cnt_adjust();
	}

	// 최종 업데이트↓↓↓↓↓
	//상품 개별 업데이트
	protected function _doGoodsUpdate($goodsParams) {
		$this->load->model('goodsmodel');
		$this->load->model('categorymodel');
		$this->load->model('countmodel');

		$today		= date("Y-m-d H:i:s");
		foreach ($goodsParams as $goodsSeq => $params) {
			// 변경전 상품 노출 여부
			if( $params['goods_view'] ){
				$goodsOld	= $this->goodsmodel->get_view($goodsSeq)->row_array();
				if($goodsOld['goods_view']!='look' && $goodsOld['display_terms']=='AUTO' && $goodsOld['display_terms_begin']<=$today && $goodsOld['display_terms_end']>=$today ){
					$goodsOld['goods_view']	= 'look';
				}
			}

			$this->db->where('goods_seq', $goodsSeq);
			$result	= $this->db->update('fm_goods', $params);
		}

		return true;
	}

	//옵션 개별 업데이트
	protected function _doOptionsUpdate($optionParams) {

		foreach ($optionParams as $optionSeq => $params) {
			$this->db->where('option_seq', $optionSeq);
			$result	= $this->db->update('fm_goods_option', $params);
		}

		return true;

	}

	//상품 조건(일괄) 업데이트
	protected function _doGoodsGroupUpdate($goodsGroupParams, $goodsSeqList) {

		$this->load->model('goodsmodel');
		$this->load->model('categorymodel');
		$this->load->model('countmodel');

		$arrayCount		= count($goodsSeqList);
		$maxPage		= ceil($arrayCount / $this->updateCount);
		$endPoint		= 0;
		$updateCnt		= $this->updateCount;
		$today			= date("Y-m-d H:i:s");
		$aGoodsViews	= array();

		$checkCount		= 0;
		for ($i = 1; $i <= $maxPage; $i++) {
			$startPoint	= $endPoint;
			$endPoint	= $i * $updateCnt;
			$endPoint	= ($endPoint > $arrayCount) ? $arrayCount : $endPoint;
			$targetList	= array();

			for ($j = $startPoint; $j < $endPoint; $j++) {
				if ($goodsSeqList[$j] < 1)
					continue;

				$targetList[]	= $goodsSeqList[$j];
			}

			if (count($targetList) < 1)
				continue;

			// 변경전 상품 노출 여부
			if( $goodsGroupParams['goods_view'] ){
				foreach($this->goodsmodel->get_view($targetList)->result_array() as $goodsOld){
					if($goodsOld['goods_view']!='look' && $goodsOld['display_terms']=='AUTO' && $goodsOld['display_terms_begin']<=$today && $goodsOld['display_terms_end']>=$today ){
						$goodsOld['goods_view']	= 'look';
					}
					if($goodsGroupParams['goods_view']!= $goodsOld['goods_view'])	$aGoodsViews[$goodsOld['goods_seq']]	= $goodsOld['goods_view'];
				}
			}

			$goodsGroupParams['update_date'] = date("Y-m-d H:i:s",mktime());		//상품 수정일 업데이트
			$this->db->where_in('goods_seq', $targetList);
			$result	= $this->db->update('fm_goods', $goodsGroupParams);
		}

		return true;

	}


	//옵션 조건(일괄) 업데이트(
	protected function _doOptionGroupUpdate($optionGroupParams, $seqList, $whereField = 'goods_seq') {

		$arrayCount		= count($seqList);
		$maxPage		= ceil($arrayCount / $this->updateCount);
		$endPoint		= 0;
		$updateCnt		= $this->updateCount;

		$checkCount		= 0;

		for ($i = 1; $i <= $maxPage; $i++) {
			$startPoint	= $endPoint;
			$endPoint	= $i * $updateCnt;
			$endPoint	= ($endPoint > $arrayCount) ? $arrayCount : $endPoint;
			$targetList	= array();

			for ($j = $startPoint; $j < $endPoint; $j++) {
				if ($seqList[$j] < 1)
					continue;

				$targetList[]	= $seqList[$j];
			}

			if (count($targetList) < 1)
				continue;

			$this->db->where_in($whereField, $targetList);
			$result	= $this->db->update('fm_goods_option', $optionGroupParams);
		}


		return true;

	}

	// 추가정보 및 정보고 예외처리 함수 ( 위 Update Policy와 다른 구조로 예외처리 함 )
	public function exceptGoodsAddinfoGroup($params){
		// 2018-07-10 [pjw] 선택한 항목만 업데이트하게끔 수정
		$isCheckAddinfo		= $params['batch_addinfo_savetype_yn'] == '1' ? true : false;
		$isCheckGoodsinfo	= $params['goodsSubInfo_yn'] == '1' ? true : false;

		// 추가정보
		if	($isCheckAddinfo && $params['etcContents']) foreach($params['etcContents'] as $k => $cont){
			unset($tmp);
			$tmp['code_seq']			= '0';
			$tmp['title']				= $params['etcTitle'][$k];
			$tmp['contents']			= $cont;
			$tmp['type']				= $params['selectEtcTitle'][$k];
			$tmp['contents_title']		= $params['etcContents_title'][$k];
			if	(preg_match('/goodsaddinfo\_/', $params['selectEtcTitle'][$k])){
				$tmp['code_seq']		= (int) str_replace('goodsaddinfo_', '', $params['selectEtcTitle'][$k]);
				if	($tmp['code_seq'] > 0){
					$sql					= "SELECT label_title FROM `fm_goods_code_form` WHERE `codeform_seq` = ?";
					$query					= $this->db->query($sql, array($tmp['code_seq']));
					$select					= $query->row();
					$tmp['title']			= $select->label_title;
				}
			}
			$addinfo[]					= $tmp;
		}

		// 정보고시
		if($isCheckGoodsinfo){
			$goods['goods_sub_info']		= $params['goodsSubInfo'];
			$goods['sub_info_desc']			= '';
			if		($params['goodsSubInfo'] == 'delete'){
				$goods['goods_sub_info']	= NULL;
				$goods['sub_info_desc']		= NULL;
			}elseif	($params['goodsSubInfo'] != 'keep'){
				$goods['sub_info_desc']		= json_encode(array_combine($params['subInfoTitle'], $params['subInfoDesc']));
			}
		}

		return array('addinfo' => $addinfo, 'goods' => $goods);
	}

	// 추가정보 일괄 업데이트
	protected function _doAddinfoGroupUpdate($type, $addInfoParams, $goodsSeqList) {

		$arrayCount		= count($goodsSeqList);
		$chkCount		= 0;
		if	($arrayCount > 0){
			for	($g = 0; $g < $arrayCount; $g++){
				if ($goodsSeqList[$g] < 1)	continue;
				$chkCount++;

				$goods_seq	= $goodsSeqList[$g];

				// 현재 추가정보 삭제 후 업데이트
				if	($type == 'change'){
					$this->db->where('goods_seq', $goods_seq);
					$this->db->delete('fm_goods_addition');
				}

				if	(is_array($addInfoParams) && count($addInfoParams) > 0){
					foreach($addInfoParams as $k => $infos){
						unset($select, $addition_seq, $insParams, $upsParams);
						$addition_seq		= 0;
						if	($type == 'add'){
							$sql			= "SELECT * FROM `fm_goods_addition` "
											. "WHERE `goods_seq` = ? AND `type` = ? AND `title` = ?";
							$query			= $this->db->query($sql, array($goods_seq, $infos['type'], $infos['title']));
							$select			= $query->row();
							$addition_seq	= $select->addition_seq;
						}

						if	($addition_seq > 0){
							$upsParams['title']				= $infos['title'];
							$upsParams['contents']			= $infos['contents'];
							$upsParams['contents_title']	= $infos['contents_title'];
							$this->db->where(array('goods_seq' => $goods_seq, 'addition_seq' => $addition_seq));
							$this->db->update('fm_goods_addition', $upsParams);
						}else{
							$insParams['goods_seq']			= $goods_seq;
							$insParams['code_seq']			= $infos['code_seq'];
							$insParams['type']				= $infos['type'];
							$insParams['title']				= $infos['title'];
							$insParams['contents']			= $infos['contents'];
							$insParams['contents_title']	= $infos['contents_title'];
							$this->db->insert('fm_goods_addition', $insParams);
						}
					}
				}
			}

			if	($chkCount > 0){
				return true;
			}else{
				return '업데이트가 실패되었습니다.';
			}
		}else{
			return '업데이트할 상품이 없습니다.';
		}

		return '추가정보 업데이트 실패';
	}
}


/* End of file goodsJandlermodel.php */
/* Location: ./app/models/goodsJandlermodel.php */
