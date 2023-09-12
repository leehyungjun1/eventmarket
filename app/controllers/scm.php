<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');
require_once(APPPATH ."controllers/base/common_base".EXT);
class scm extends common_base {

	public function __construct() {
		parent::__construct();

		$aPostParams = $this->input->post();
	}

	// 업체에 재고 반영
	public function send_store_stock_data(){
		$this->load->helper('readurl');
		$this->load->helper('javascript');
		$this->load->model('scmmodel');

		$chVal				= unserialize(base64_decode(trim($aPostParams['chVal'])), ['allowed_classes' => false]);
		$whSeq				= unserialize(base64_decode(trim($aPostParams['whSeq'])), ['allowed_classes' => false]);
		$store_per			= trim($aPostParams['store_per']);
		$store_seq			= trim($aPostParams['store_seq']);
		$url				= trim($aPostParams['storeUrl']);
		$storeName			= trim($aPostParams['storeName']);
		$page				= (trim($aPostParams['page']) > 0) ? trim($aPostParams['page']) : '1';
		$perpage			= 300;
		$nper				= $store_per;

		if	($store_seq > 0){
			// 판매처 사용 창고
			unset($sc);
			$sc['store_seq']	= $store_seq;
			$storeWh			= $this->scmmodel->get_store_warehouse($sc);
			unset($stwh);
			if	($storeWh) foreach($storeWh as $w => $wh){
				$stwh[]					= $wh['wh_seq'];
				$usedWH[$wh['wh_seq']]	= $wh['wh_name'];
			}

			// 창고가 있을 경우 창고 체크
			$chg_wh		= true;
			if	( $whSeq && count($whSeq) > 0 ){
				$chg_wh	= false;
				foreach($whSeq as $k => $seq){
					if	(in_array($seq, $stwh)){	$chg_wh	= true; break;	}
				}
			}
			// 전체 창고이거나 변경 창고가 포함된 경우
			if	($chg_wh){
				unset($sendParam);
				$sendParam['page']			= $page;
				$sendParam['perpage']		= $perpage;
				$sendParam['concat_goods']	= $chVal;
				$sendParam['src_wh_seq']	= $stwh;
				$data				= $this->scmmodel->get_location_stock($sendParam);
				if	($data['record']) foreach($data['record'] as $k => $g){
					$sendGoods[]	= $g;
				}

				// 다음 페이지가 있는 경우
				$first_nper	= $nper;
				if	($data['page']['totalpage'] > $page){
					$nper		= ROUND($store_per / $data['page']['totalpage']);
					$first_nper	= $nper + ($store_per - ($nper * $data['page']['totalpage']));
				}

				// 변경 전송
				if	(count($sendGoods) > 0 || !(count($chVal) > 0)){
					if	(count($chVal) > 0)	$type	= 'select';
					else					$type	= 'all';
					$params		= array('send_type' => $type, 'use_wh' => $usedWH, 'goods' => $sendGoods);
					$result		= readurl(get_connet_protocol() . $url . '/scm/receive_stock_data', $params);
					if	($result){

						// 전송 완료 처리
						$js		= 'parent.addProgPercent(\'' . $first_nper . '\');';
						// 전송 실패
						if	(trim($result) == 'F'){
							$js	.= 'parent.addDetailLog(\'[' . $storeName . '] 판매처 데이터 전송 실패\');';
						}else{
							$js	.= 'parent.addDetailLog(\'[' . $storeName . ']' . $result . '\');';
						}
						echo js($js);


						// 다음 페이지가 있는 경우
						if	($data['page']['totalpage'] > $page){
							$sendParam['page']++;
							$sendParam['nper']		= $nper;
							$sendParam['goods']		= base64_encode(serialize($chVal));
							$sendParam['wh']		= base64_encode(serialize($stwh));
							$sendParam['storeUrl']	= $url;
							$sendParam['storeName']	= $storeName;
							arrayToFormSubmit('tmpForm', '/scm/send_stock_data', $sendParam);
						}
					}
				}
			}else{
				// 전송 완료 처리
				$js		= 'parent.addProgPercent(\'' . $nper . '\');';
				$js		.= 'parent.addDetailLog(\'[' . $storeName . '] 해당 창고 미사용\');';
				echo js($js);
			}
		}
	}

	// 재고 반영 send 함수
	public function send_stock_data(){
		$this->load->helper('readurl');
		$this->load->helper('javascript');
		$this->load->model('scmmodel');

		$goods				= unserialize(base64_decode(trim($aPostParams['goods'])), ['allowed_classes' => false]);
		$wh					= unserialize(base64_decode(trim($aPostParams['wh'])), ['allowed_classes' => false]);
		$url				= trim($aPostParams['storeUrl']);
		$storeName			= trim($aPostParams['storeName']);
		$page				= trim($aPostParams['page']);
		$perpage			= trim($aPostParams['perpage']);
		$nper				= trim($aPostParams['nper']);

		unset($sendParam);
		$sendParam['page']			= $page;
		$sendParam['perpage']		= $perpage;
		$sendParam['concat_goods']	= $goods;
		$sendParam['src_wh_seq']	= $wh;
		$data				= $this->scmmodel->get_location_stock($sendParam);
		if	($data['record']) foreach($data['record'] as $k => $g){
			$sendGoods[]	= $g;
		}

		// 변경 전송
		if	(count($sendGoods) > 0){
			$type		= 'select';
			$params		= array('send_type' => $type, 'goods' => $sendGoods);
			$result		= readurl(get_connet_protocol() . $url . '/scm/receive_stock_data', $params);

			// 전송 완료 처리
			$js		= 'parent.addProgPercent(\'' . $nper . '\');';
			// 전송 실패
			if	(trim($result) == 'F'){
				$js	.= 'parent.addDetailLog(\'[' . $storeName . '] 판매처 데이터 전송 실패\');';
			}else{
				$js	.= 'parent.addDetailLog(\'[' . $storeName . ']' . $result . '\');';
			}
			echo js($js);

			// 다음 페이지가 있는 경우
			if	($data['page']['totalpage'] > $page){
				$sendParam['page']++;
				$sendParam['nper']		= $nper;
				$sendParam['goods']		= base64_encode(serialize($goods));
				$sendParam['wh']		= base64_encode(serialize($wh));
				$sendParam['storeUrl']	= $url;
				$sendParam['storeName']	= $storeName;
				arrayToFormSubmit('tmpForm', '/scm/send_stock_data', $sendParam);
			}
		}
	}

	// 재고 반영 receive 함수
	public function receive_stock_data(){

		$this->load->model('scmmodel');

		$type			= $_POST['send_type'];
		$goods			= $_POST['goods'];
		$use_wh			= $_POST['use_wh'];

		// 사용창고 정보 update
		if	($use_wh && is_array($use_wh) && count($use_wh) > 0){
			$this->load->helper('basic');
			$use_warehouse	= (count($use_wh) > 0) ? serialize($use_wh) : '';
			config_save('scm', array('use_warehouse' => $use_warehouse));
		}

		// 재고 및 매입가 초기화
		if	($type == 'all'){
			$sql	= "update fm_goods_supply a, fm_goods b set "
					. "a.stock = 0, a.badstock = 0, a.supply_price = 0 "
					. "where a.goods_seq = b.goods_seq and b.master_goods_seq > 0 ";
			if	(!$this->scmmodel->scm_use_suboption_mode)	$sql	.= " and a.option_seq > 0 ";
			echo '<br/>상품 재고정보 초기화';
			$this->db->query($sql);
		}

		if	(count($goods) > 0){
			foreach($goods as $k => $g){
				$goods_seq		= $g['goods_seq'];
				$option_type	= $g['option_type'];
				$option_seq		= $g['option_seq'];
				$stock			= $g['ea'];
				$badstock		= $g['bad_ea'];
				$supply_price	= $g['supply_price'];

				if	($goods_seq > 0 && $option_seq > 0){
					unset($whParams, $upParams);
					$whParams['master_goods_seq']		= $goods_seq;
					if	($option_type == 'suboption')	$whParams['suboption_seq']		= $option_seq;
					else								$whParams['option_seq']			= $option_seq;
					$upParams['stock']			= $stock;
					$upParams['badstock']		= $badstock;
					$upParams['supply_price']	= $supply_price;
					$this->scmmodel->update_supply_master($upParams, $whParams);
					echo '<br/>상품정보 : ' . $goods_seq . ' / ';
					if	($option_type == 'suboption')	echo '추가구성옵션 ';
					else								echo '필수옵션 ';
					echo $option_seq . '를 ';
					echo '재고 ' . $stock . ', 평균매입가 ' . number_format($supply_price, 2) . '로 수정 성공';
				}else{
					echo '<br/>상품정보 : ' . $goods_seq . ' / ';
					if	($option_type == 'suboption')	echo '추가구성옵션 ';
					else								echo '필수옵션 ';
					echo $option_seq . '를 정보부족으로 수정 실패';
				}
			}
		}else{
			echo '<br/>전달할 상품정보가 없습니다.';
		}
	}

	// 하위에서 재고 요청
	public function request_getstock(){
		$wh_seq			= trim($_POST['whSeq']);
		$getType		= trim($_POST['getType']);
		$optioninfo		= $_POST['optioninfo'];

		if	($wh_seq > 0){
			$this->load->model('scmmodel');
			$this->scmmodel->get_warehouse_stock($wh_seq, $getType, 'json', $optioninfo);
		}
	}

	// 재고 요청에 대한 재고 정보 제공
	public function response_getstock(){
		$wh_seq			= trim($aPostParams['whSeq']);
		$getType		= trim($aPostParams['getType']);
		$optioninfo		= unserialize(base64_decode($aPostParams['optioninfo']), ['allowed_classes' => false]);

		if	($wh_seq > 0){
			$this->load->model('scmmodel');
			$this->scmmodel->get_warehouse_stock($wh_seq, $getType, 'json', $optioninfo);
		}
	}

	// 해당 상품에 대한 전체 창고 재고 요청
	public function request_getstock_all(){
		$this->load->model('scmmodel');
		$goods_seq		= trim($_POST['goods_seq']);
		$option_type	= trim($_POST['option_type']);
		$option_seq		= trim($_POST['option_seq']);

		$result			= $this->scmmodel->get_stock_warehouse_list($goods_seq, $option_type, $option_seq);
		if	(count($result) > 0)	echo json_encode($result);
		else						echo json_encode(array());
	}

	// 매장정보 변경
	public function chg_export_wh(){
		$this->load->helper('basic');

		$store_seq		= trim($_POST['store_seq']);
		$store_name		= trim($_POST['store_name']);
		$use_warehouse	= trim($_POST['use_warehouse']);
		$export_wh		= trim($_POST['export_wh']);
		$return_wh		= trim($_POST['return_wh']);
		config_save('scm', array('store_seq'		=> $store_seq,
								'store_name'		=> $store_name,
								'use_warehouse'		=> $use_warehouse,
								'export_wh'			=> $export_wh,
								'return_wh'			=> $return_wh));
	}

	// 출고 요청 받는 팝업 페이지
	public function response_export(){
		// 관리자 스킨 호출
		$this->template->template_dir	= BASEPATH . '../admin/skin';
		$this->template->compile_dir	= BASEPATH . '../_compile/admin';
		$skin_path						= 'default/scm/response_export.html';

		$wh_seq		= trim($_POST['wh_seq']);
		$store_seq	= trim($_POST['store_seq']);
		$goodsData	= unserialize(base64_decode($aPostParams['goodsData']), ['allowed_classes' => false]);
		$this->template->assign(array(
			'wh_seq'	=> $wh_seq,
			'store_seq'	=> $store_seq,
			'goodsData'	=> $goodsData,
		));
		$this->template->define(array(
			'common_html_header'	=> 'default/_modules/common/html_header.html',
			'common_html_footer'	=> 'default/_modules/common/html_footer.html',
			'tpl'					=> $skin_path
		));
		$this->template->print_("tpl");
	}

	// 출고 요청에 대한 처리
	public function scm_export_process(){
		$this->load->model('scmmodel');

		$store_seq	= trim($_POST['store_seq']);
		$wh_seq		= trim($_POST['wh_seq']);
		$goodsData	= $_POST['goodsData'];
		if	($wh_seq > 0 && $store_seq > 0 && count($goodsData) > 0){
			$this->scmmodel->apply_export_wh($wh_seq, $goodsData);
			$sendResult	= $this->scmmodel->change_store_stock($this->scmmodel->tmp_scm['goods'], array($this->scmmodel->tmp_scm['wh_seq']), '', '', 'close');
		}else{
			echo '<script>parent.close();</script>';
		}
	}

	// 반품 요청 받는 팝업 페이지
	public function response_return(){
		// 관리자 스킨 호출
		$this->template->template_dir	= BASEPATH . '../admin/skin';
		$this->template->compile_dir	= BASEPATH . '../_compile/admin';
		$skin_path						= 'default/scm/response_return.html';

		$wh_seq		= trim($aPostParams['wh_seq']);
		$store_seq	= trim($aPostParams['store_seq']);
		$goodsData	= unserialize(base64_decode($aPostParams['goodsData']), ['allowed_classes' => false]);
		$this->template->assign(array(
			'wh_seq'	=> $wh_seq,
			'store_seq'	=> $store_seq,
			'goodsData'	=> $goodsData,
		));
		$this->template->define(array(
			'common_html_header'	=> 'default/_modules/common/html_header.html',
			'common_html_footer'	=> 'default/_modules/common/html_footer.html',
			'tpl'					=> $skin_path
		));
		$this->template->print_("tpl");
	}

	// 반품 요청에 대한 처리
	public function scm_return_process(){
		$this->load->model('scmmodel');

		$store_seq	= trim($_POST['store_seq']);
		$wh_seq		= trim($_POST['wh_seq']);
		$goodsData	= $_POST['goodsData'];
		if	($wh_seq > 0 && $store_seq > 0 && count($goodsData) > 0){
			$this->scmmodel->apply_return_wh($wh_seq, $goodsData);
			$sendResult	= $this->scmmodel->change_store_stock($this->scmmodel->tmp_scm['goods'], array($this->scmmodel->tmp_scm['wh_seq']), '', '', 'close');
		}else{
			echo '<script>parent.close();</script>';
		}
	}

	// 창고의 로케이션 정보 추출
	public function get_location_info(){
		$this->load->model('scmmodel');
		$wh_seq			= trim($_POST['wh_seq']);
		if	(!$this->scm_cfg)	$this->scm_cfg	= config_load('scm');
		if	($wh_seq > 0){
			if	($this->scm_cfg['scm_type'] == 'local'){
				$sc['wh_seq']		= $wh_seq;
				$sc['grid_type']	= true;
				$location			= $this->scmmodel->get_location($sc);
				if	(count($location) > 0)	echo json_encode($location);
				else						echo json_encode(array());
			}else{
				$this->load->helper('readurl');
				$url					= $this->scm_cfg['scm_location'];
				$params['wh_seq']		= $wh_seq;
				echo readurl(get_connet_protocol() . $url . '/scm/get_location_info', $params);
			}
		}
	}

	public function request_getstock_total(){

		$this->load->model('scmmodel');

		if	(!$this->cfg_scm)		$this->cfg_scm		= config_load('scm');
		if	(!$this->cfg_order)		$this->cfg_order		= config_load('order');

		$goods_seq		= trim($_POST['goods_seq']);
		$option_seq		= trim($_POST['option_seq']);

		if(!$goods_seq){
			$goods_seq		= trim($_GET['goods_seq']);
			$option_seq		= trim($_GET['option_seq']);
		}

		// 창고목록
		if($this->cfg_scm['use']){
			$warehouse			= $this->scmmodel->get_warehouse(array());
			if	($warehouse) foreach($warehouse as $w => $data){
				$reWh[$data['wh_seq']]	= $data;
			}
		}

		unset($warehouse);
		$warehouse	= $reWh;

		// 창고별 재고
		unset($sc);
		$sc['get_type']		= 'wh';
		$sc['goods_seq']	= $goods_seq;
		$whTotal			= $this->scmmodel->get_location_stock($sc);
		if	($whTotal) foreach($whTotal as $w => $data){
			$warehouse[$data['wh_seq']]['stock']		= $data['ea'];
			$warehouse[$data['wh_seq']]['bad_stock']	= $data['bad_ea'];
			$warehouse[$data['wh_seq']]['supply_price']	= $data['supply_price'];
			$warehouse[$data['wh_seq']]['total_price']	= $data['total_supply_price'];
			//$warehouse[$data['wh_seq']]['wh_name']		= $data['wh_name'];
			$totalStock									+= $data['ea'];
		}

		// 필수옵션 정보
		unset($sc);
		$sc['goods_seq']	= $goods_seq;
		$sc['option_seq']	= $option_seq;
		$tmp_options		= $this->scmmodel->get_location_goods_for_option($sc, 'option','request_total');

		$arr_title = explode(',',$tmp_options[0]['option_title']);
		$option_count = count($arr_title);

		foreach ($tmp_options as $k => $opt){

			// 창고별 재고 초기화
			if	($warehouse) foreach($warehouse as $w => $data){
				if	(!$options[$opt['option_seq']]['wh'][$data['wh_seq']]['stock']){
					$options[$opt['option_seq']]['wh'][$data['wh_seq']]['location_position']	= '';
					$options[$opt['option_seq']]['wh'][$data['wh_seq']]['location_code']		= '';
					$options[$opt['option_seq']]['wh'][$data['wh_seq']]['stock']				= '0';
					$options[$opt['option_seq']]['wh'][$data['wh_seq']]['bad_stock']			= '0';
					$options[$opt['option_seq']]['wh'][$data['wh_seq']]['supply_price']			= '0';
					$options[$opt['option_seq']]['wh'][$data['wh_seq']]['total_price']			= '0';
					$options[$opt['option_seq']]['wh'][$data['wh_seq']]['wh_name'] = $data['wh_name'];
					$options[$opt['option_seq']]['wh'][$data['wh_seq']]['use'] = 'n';
					if( $this->cfg_scm['use_warehouse'][$data['wh_seq']] ){
						$options[$opt['option_seq']]['wh'][$data['wh_seq']]['use'] = 'y';
					}
				}
			}

			// 창고별 재고
			if	($opt['wh_seq'] > 0 ){
				$options[$opt['option_seq']]['wh'][$opt['wh_seq']]['location_position']	= $opt['location_position'];
				$options[$opt['option_seq']]['wh'][$opt['wh_seq']]['location_code']		= $opt['location_code'];
				$options[$opt['option_seq']]['wh'][$opt['wh_seq']]['stock']					= $opt['ea'];
				$options[$opt['option_seq']]['wh'][$opt['wh_seq']]['bad_stock']				= $opt['bad_ea'];
				$options[$opt['option_seq']]['wh'][$opt['wh_seq']]['supply_price']			= $opt['wh_supply_price'];
				$options[$opt['option_seq']]['wh'][$opt['wh_seq']]['total_price']			= $opt['wh_supply_price'] * $opt['ea'];
				$options[$opt['option_seq']]['total_stock']	+= $opt['ea'];
				$options[$opt['option_seq']]['total_badstock']	+= $opt['bad_ea'];
				$total[$opt['wh_seq']]['stock']			+= $opt['ea'];
				$total[$opt['wh_seq']]['bad_stock']		+= $opt['bad_ea'];
				$total[$opt['wh_seq']]['supply_price']	+= $opt['wh_supply_price'];
				$total[$opt['wh_seq']]['total_price']	+= $opt['wh_supply_price'] * $opt['ea'];
			}

			// 기 저장된 매입정보 추출
			if	(!$options[$opt['option_seq']]['defaultinfo']){
				unset($sc);
				$sc['goods_seq']		= $opt['goods_seq'];
				$sc['option_seq']		= $opt['option_seq'];
				$sc['exists_trader']	= 'Y';
				$defaultinfo			= $this->scmmodel->get_order_defaultinfo($sc);
				$options[$opt['option_seq']]['defaultinfo']		= $defaultinfo;
			}

			$options[$opt['option_seq']]['store_name'] = $this->config_system['admin_env_name'];
			$options[$opt['option_seq']]['admin_env_name'] = $this->config_system['admin_env_name'];
			$options[$opt['option_seq']]['option_count']	= $option_count;
			for($oi=1;$oi<6;$oi++){
				$options[$opt['option_seq']]['title'.$oi]	= $arr_title[$oi-1];
				$options[$opt['option_seq']]['option'.$oi]	= $opt['option'.$oi];
			}

			$options[$opt['option_seq']]['stock']			= $opt['stock'];
			$options[$opt['option_seq']]['badstock']		= $opt['badstock'];
			$reservation_field = 'reservation'.$this->cfg_order['ableStockStep'];
			$options[$opt['option_seq']]['ablestock']		= $opt['stock'] - $opt['badstock'] - $opt[$reservation_field];
			$options[$opt['option_seq']]['supply_price']	= $opt['supply_price'];

			// 재배열
			$options[$opt['option_seq']]['package_yn']		= $opt['package_yn'];
			$options[$opt['option_seq']]['goods_seq']		= $opt['goods_seq'];
			$options[$opt['option_seq']]['goods_code']		= $opt['goods_code'];
			$options[$opt['option_seq']]['goods_name']		= $opt['goods_name'];
			$options[$opt['option_seq']]['option_seq']		= $opt['option_seq'];
			$options[$opt['option_seq']]['option_type']		= $opt['option_type'];
			$options[$opt['option_seq']]['weight']			= $opt['weight'];
			$options[$opt['option_seq']]['option_view']		= $opt['option_view'];
			$options[$opt['option_seq']]['option_name']		= implode('',
																array($opt['option1'], $opt['option2'],
																	$opt['option3'], $opt['option4'],
																	$opt['option5']));
		}

		echo json_encode($options);
	}


	//발주서 화면 출력
	public function get_sorder_draft_view(){
		$this->load->model('membermodel');
		$this->load->model('scmmodel');

		$sorder_draft_info_arr	= $this->scmmodel->get_sorder_draft_info($_GET['sono']);
		$sorder_draft_info		= $sorder_draft_info_arr[0];
		$replace_codes			= $this->membermodel->get_replacetext_other('sorder_draft');

		$file_path	= "../../data/email/".get_lang(true)."/sorder_draft.html";
		$this->template->assign($sorder_draft_info);
		$this->template->compile_dir	= ROOTPATH."data/email/".get_lang(true)."/";
		$this->template->define(array('tpl'=>$file_path));
		$this->template->print_("tpl");
	}

	// 해당 상품의 매장 판매정보 ( 판가, 정가, 안전재고 등 )
	public function get_store_goods_info(){
		$goods_seq			= trim($_GET['goods_seq']);
		$option_type		= trim($_GET['option_type']);
		$option_seq			= trim($_GET['option_seq']);
		$option_info_arr	= $goods_seq . $option_type . $option_seq;

		if	($option_type && $option_seq)	$sc['option_info_arr']	= $option_info_arr;
		else								$sc['goods_seq']		= $goods_seq;
		$goods	= $this->scmmodel->get_location_goods_for_option($sc);

		echo json_encode($goods);
	}
}

/* End of file scm.php */
/* Location: ./app/controllers/scm.php */