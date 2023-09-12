<?php
if ( ! defined('BASEPATH')) exit('No direct script access allowed');
class invoiceapimodel extends CI_Model {
	var $config_invoice;
	var $invoice_vendor_cfg = array(
		'hlc' => array(
			'company'=>'롯데택배',
			'url'=>'http://www.hydex.net/ehydex/jsp/home/distribution/tracking/tracingView.jsp?InvNo='
		)
	);

	function __construct() {
		parent::__construct();

		$this->load->library('invoiceapi');
		
		if(!$this->load_vendor_cfg){ // 설정에서 롯데택배 자동화에 대한 환경정보 load
			$this->load_vendor_cfg = config_load('delivery_url','code97');
		}
		
		if( date('YmdH', time()) >= '2019071408' && $this->load_vendor_cfg['code97']['url'] ){ // 14일 8시 이후 변경 url 적용
			$this->invoice_vendor_cfg['hlc']['url']	= $this->load_vendor_cfg['code97']['url'];
		}

		if (defined('__SELLERADMIN__') === true) {
			$provider_seq	= $this->providerInfo['provider_seq'];
		}else{
			$provider_seq	= 1;
		}

		$this->config_invoice = $this->get_invoice_setting($provider_seq);
	}

	# 사용가능한 택배사 업체 정보 반환
	function get_usable_invoice_vendor($provider_seq){

		$config_invoice = $this->get_invoice_setting($provider_seq);

		$result = array();
		foreach($config_invoice as $k=>$row){
			if($row['use']){
				$row['company'] = $this->invoice_vendor_cfg[$k]['company'];
				$result[$k] = $row;
			}
		}
		return $result;
	}

	# 출고데이터 송신
	function export($arr_export_code=array(), $forced=false, $new_export=false){

		$this->load->model('epostmodel');
		$this->load->model('shippingmodel');

		if(!is_array($arr_export_code)) $arr_export_code = array($arr_export_code);

		//합포장 처리용
		$bundleExportList	= array();
		$exportList			= array();
		foreach($arr_export_code as $nowExportCode) {
			if (preg_match('/^B/', $nowExportCode))
				$bundleExportList[]		= $nowExportCode;
			else
				$exportList[]			= $nowExportCode;
		}


		$exportCodeWhereArr		= array();

		if (count($exportList) > 0) {
			$targetExport			= '';
			$targetExport			= implode("','", $exportList);
			$exportCodeWhereArr[]	= "a.export_code IN ('{$targetExport}')";
		}

		if (count($bundleExportList) > 0) {
			$targetExport			= '';
			$targetExport			= implode("','", $bundleExportList);
			$exportCodeWhereArr[]	= "a.bundle_export_code IN ('{$targetExport}')";
		}

		$exportCodeWhere			= '('.implode(' OR ',$exportCodeWhereArr).')';

		$sqlWhereAdd = $forced ? "" : " and a.invoice_send_yn != 'y' ";

		$sql = "
			select
			a.order_seq,
			if(a.bundle_export_code REGEXP '^B', a.bundle_export_code, a.export_code) as export_code,
			a.delivery_company_code,
			a.shipping_provider_seq,
			a.domestic_shipping_method,
			a.delivery_number,
			o.payment,
			o.order_user_name,
			o.order_phone,
			o.order_cellphone,
			'' as order_zipcode,
			'' as order_address,
			'' as order_address_detail,
			o.shipping_method,
			b.shipping_group,
			b.shipping_cost,
			o.recipient_user_name,
			o.recipient_phone,
			o.recipient_cellphone,
			o.recipient_zipcode,
			o.recipient_address_type,
			o.recipient_address,
			o.recipient_address_street,
			o.recipient_address_detail,
			o.memo,
			c.ea,
			d.goods_name,
			g.goods_code,
			if(c.option_seq is not null,title1,title) as title1,
			if(c.option_seq is not null,title2,'') as title2,
			if(c.option_seq is not null,title3,'') as title3,
			if(c.option_seq is not null,title4,'') as title4,
			if(c.option_seq is not null,title5,'') as title5,
			if(c.option_seq is not null,option1,suboption) as option1,
			if(c.option_seq is not null,option2,'') as option2,
			if(c.option_seq is not null,option3,'') as option3,
			if(c.option_seq is not null,option4,'') as option4,
			if(c.option_seq is not null,option5,'') as option5,
			sum(d.goods_shipping_cost) as goods_shipping_cost
			from fm_goods_export a
			inner join fm_order o on o.order_seq=a.order_seq
			inner join fm_goods_export_item c on c.export_code=a.export_code
			inner join fm_order_item as d on d.item_seq=c.item_seq
			inner join fm_order_shipping b on b.shipping_seq=d.shipping_seq
			left join fm_order_item_option as e on e.item_option_seq=c.option_seq
			left join fm_order_item_suboption as f on f.item_suboption_seq=c.suboption_seq
			left join fm_goods as g on g.goods_seq=d.goods_seq
			where 
			{$exportCodeWhere}
			{$sqlWhereAdd}
			and a.delivery_company_code like 'auto_%'
			and a.domestic_shipping_method = 'delivery'
			group by export_code
			order by a.export_code asc
		";
		$query = $this->db->query($sql);
		$data = $query->result_array();

		$arrData = array();
		foreach($data as $k=>$row){
			if(preg_match("/^auto_/",$row['delivery_company_code'])){
				$vendor = str_replace("auto_","",$row['delivery_company_code']);
				if($vendor=='hlc'){
					if(!$this->config_invoice['hlc']['use']) continue;
					// 상품옵션 취합
					$row['goods_options'] = array();
					for($i=1;$i<=5;$i++) if(!empty($row['option'.$i])) {
						$row['goods_options'][] = $row['title'.$i].":".$row['option'.$i];
					}
					$row['goods_options'] = implode(" / ",$row['goods_options']);

					// 결제수단
					$row['mpayment'] = $this->arr_payment[$row['payment']];

					// 매출액
					$row['salesprice'] = $row['price'] * $row['ea'];

					// 배송비
					$row['sum_shipping_cost'] = $row['shipping_cost']+$row['goods_shipping_cost'];

					// 출고지
					$grp_arr = explode('_',$row['shipping_group']);
					$grp_info = $this->shippingmodel->get_shipping_group($grp_arr[0]);
					$add_info = $this->shippingmodel->get_shipping_address($grp_info['refund_address_seq'], $grp_info['refund_scm_type']);

					// 주소 있는경우 기존 정보를 교체함.
					if($add_info['address_type'] && $add_info['address_zipcode']){
						$shipping_info['zipcode']		= $add_info['address_zipcode'];
						$shipping_info['address']		= ($add_info['address_type']=='street') ? $add_info['address_street'] : $add_info['address'];
						$shipping_info['addressDetail']	= $add_info['address_detail'];
						
						$shipping_info['address_name']	= $add_info['address_name'];
						$shipping_info['shipping_phone']	= $add_info['shipping_phone'];

						$row['shipping_info']			= $shipping_info;
					}

					$arrData[$vendor][] = $row;
					unset($data[$k]);
				}else if($vendor=='epostnet' && !$row['delivery_number']){

					// 업체구분코드 추출
					$epost_info = $this->epostmodel->get_epost_requestkey($row['shipping_provider_seq']);

					// 우체국 택배분 추출 :: 2016-04-05 lwh
					unset($param);
					$param['export_code']	= $row['export_code'];
					$param['requestkey']	= $epost_info['requestkey'];
					$param['domestic_shipping_method'] = $row['domestic_shipping_method'];
					$arrData[$vendor][] = $param;
				}
			}
		}

		$resultData = array();
		$resultDeliveryNumber = array();
		foreach($arrData as $vendor=>$data){
			if($vendor == 'hlc'){
				$data['provider_seq']	= $data[0]['shipping_provider_seq'];
				if($new_export){
					$result = $this->invoiceapi->send($vendor.".new_export",$data);
				}else{
				$result = $this->invoiceapi->send($vendor.".export",$data);
				}

				if($result['code']!='success'){
					return array(
						'code' => $result['code'],
						'msg' => $result['msg']
					);
				}
			}else if($vendor == 'epostnet'){
				// 우체국 택배분 우회 :: 2016-04-05 lwh
				$deliverynumber = $this->epostmodel->get_delivery_number($data);
				$resultDeliveryNumber[] = $deliverynumber[0];
			}

			foreach((array)$result['data'] as $row){
				if($row['export_code']){
					$export_field	= (preg_match('/^B/', $row['export_code'])) ? 'bundle_export_code' : 'export_code';
					$sql = "update fm_goods_export set invoice_send_yn='y', delivery_number=? where {$export_field}=?";
					$this->db->query($sql,array($row['delivery_number'],$row['export_code']));
					$resultDeliveryNumber[] = $row['delivery_number'];
				}
			}

			$resultData = array_merge($resultData,(array)$result['data']);
		}

		return array(
			'code' => 'success',
			'resultDeliveryNumber' => $resultDeliveryNumber,
			'msg' => number_format(count($resultData))."건 처리 완료"
		);

	}

	# 출고결과데이터 수신
	function result($vendor, $arr_export_code=array()){

		$this->load->model('exportmodel');
		$this->load->model('ordermodel');
		$this->load->model('goodsmodel');
		$this->load->helper('order');

		$cfg_order = config_load('order');

		if(!is_array($arr_export_code)) $arr_export_code = array($arr_export_code);

		// 각 입점사 마다 출고데이터 전송하도록 수정 2018-04-09
		$providers = array();
		$provider_export_code = array();

		$query = $this->exportmodel->get_exports($arr_export_code);
		foreach( $query->result_array() as $data_export ){
			// provider_seq 별로 출고번호 array 생성 
			$provider_export_code[$data_export['shipping_provider_seq']][] = ($data_export['is_bundle_export'] == 'Y') ? $data_export['bundle_export_code'] : $data_export['export_code'];

			// provider_seq 배열 생성하여 provider 별로 전송
			if( !in_array($data_export['shipping_provider_seq'], $providers)) {
				$providers[] = $data_export['shipping_provider_seq'];
			}
		}
		
		$success_export_code = array();

		$this->managerInfo['mname'] = '택배업무자동화서비스';

		foreach( $providers as $provider ) {
			// 각 provider 별로 config_invoice 세팅
			$this->config_invoice = $this->get_invoice_setting($provider);
			if(!$this->config_invoice['hlc']['use']) continue;

			// provider_seq, 출고번호 전송
			$result = $this->invoiceapi->send($vendor.".result",array('provider_seq'=>$provider,'arr_export_code'=>$provider_export_code[$provider]));

			if($result['code']!='success'){
				$return = array(
					'code' => $result['code'],
					'msg' => $result['msg']
				);
			}
			
			if($result['code'] == 'success' ) {
				foreach($result['data'] as $row){
					
					$export_field	= (preg_match('/^B/', $row['export_code'])) ? 'bundle_export_code' : 'export_code';

					$this->db->query("update fm_goods_export set delivery_number=? where {$export_field}=?",array($row['delivery_number'],$row['export_code']));

					if($row['status']=='export'){
						$this->db->query("update fm_goods_export set delivery_number=? where {$export_field}=?",array($row['delivery_number'],$row['export_code']));

						$this->exportmodel->exec_complete_export($row['export_code'],$cfg_order,false,'system');

						$success_export_code[] = $row['export_code'];
					}

					if($row['status']=='delivery_complete'){
						$this->db->query("update fm_goods_export set delivery_number=? where {$export_field}=?",array($row['delivery_number'],$row['export_code']));

						$result = $this->exportmodel->get_export($row['export_code']);
						if($result['status'] >= 40 || $result['status'] <= 55){
							$this->exportmodel->exec_complete_export($row['export_code'],$cfg_order,false,'system');
						}
						
						$this->exportmodel->exec_complete_delivery($row['export_code'],'system');

						$success_export_code[] = $row['export_code'];
					}
				}
			}
		}

		if ( count($success_export_code) > 0 ) {
			$return = array(
				'code' => 'success',
				'msg' => number_format(count($success_export_code))."건 처리 완료",
				'success_export_code' => $success_export_code
			);
		}

		return $return;
	}

	# 롯데택배 인증
	# 사업자번호와 입력한 신용코드 전송하여 성공여부 반환
	function hlc_auth($auth_code){
		$result = $this->invoiceapi->send("hlc.auth",array(
			'provider_seq'	=> $this->providerInfo['provider_seq'],
			'company_no'	=> $this->config_basic['businessLicense'],
			'auth_code'		=> $auth_code,
		));
		return $result;
	}

	# 롯데택배 운송장 프린트
	function hlc_invoice_print($exports){

		$exports_chunked = array_chunk($exports,5);

		$resultData = array();
		
		foreach($exports_chunked as $exports){
			$result = $this->invoiceapi->send("hlc.invoice_print",array(
				'config_basic'=>$this->config_basic,
				'exports'=>$exports,
				'provider_seq'=>$exports[0]['data_export']['shipping_provider_seq'],
				'sub_division_yn'=>'n' // 상품수가 많으면 송장을 분할할지 여부 - 분할 안함 18.01.24 kmj
			));
			if($result['code'] == 'fail') {
				return $result;
			}
			if(!$resultData)$resultData = (array)$result['data'];
			$resultData['list'] = array_merge($resultData['list'],(array)$result['data']['list']);
		}

		return $resultData;
	}

	# 출고데이터 송신
	function new_export($arr_export_code=array(),$forced=false){
		//export()에서 처리합니다. @2016-11-01
		$this->export($arr_export_code,$forced,true);
	}

	# 출고데이터 송신
	function new_get_invoice($data){

		if(!$this->config_invoice['hlc']['use']) return false;
		$result = $this->invoiceapi->send("hlc.new_get_invoice",$data);

		if($result['code']!='success'){
			return array(
				'code' => $result['code'],
				'msg' => $result['msg']
			);
		}

		return array(
			'code' => 'success',
			'resultDeliveryNumber' => $result['data'],
			'msg' => number_format(count($result['data']))."건 처리 완료"
		);

	}

	# 택배 업무 자동화 서비스 삽입 :: 2015-07-10 lwh
	function set_invoice_setting($provider_seq,$vendor,$param){
		if($provider_seq && $vendor){
			$sql	= "SELECT * FROM fm_invoice WHERE provider_seq = ? AND invoice_vendor = ? ";
			$query	= $this->db->query($sql,array($provider_seq,$vendor));
			$result	= $query->row_array();

			if($result['provider_seq']){
				$this->db->where('provider_seq',$result['provider_seq']);
				$this->db->update('fm_invoice',$param);
			}else{
				$param['provider_seq']		= $provider_seq;
				$param['invoice_vendor']	= $vendor;
				$this->db->insert('fm_invoice',$param);
			}
		}
	}

	# 택배 업무 자동화 서비스 추출 :: 2015-07-10 lwh
	function get_invoice_setting($provider_seq){
		$sql	= "SELECT * FROM fm_invoice WHERE provider_seq = ?";
		$query	= $this->db->query($sql,$provider_seq);
		$data	= $query->result_array();

		$setting = array();
		foreach($data as $k => $invoice){
			unset($vendor);
			$vendor = $invoice['invoice_vendor'];
			if($invoice['invoice_use']){
				$setting[$vendor]['use']			= $invoice['invoice_use'];
				$setting[$vendor]['auth_code']		= $invoice['auth_code'];
				$setting[$vendor]['branch_name']	= $invoice['branch_name'];
				$setting[$vendor]['print_type']		= $invoice['print_type'];
			}
		}

		return $setting;
	}

	# 택배 업무 자동화 서비스 삭제 :: 2015-07-10 lwh
	function del_invoice_setting($provider_seq,$vendor){
		if($provider_seq && $vendor){
			$query = "delete from fm_invoice where provider_seq=? AND invoice_vendor = ?";
			$this->db->query($query,array($provider_seq,$vendor));
		}
	}

	/**
	 * 택배 업무 자동화 vendor를 반환한다.
	 * @return array
	 */
	public function get_invoice_vendors()
	{
	    $query = $this->db->select('DISTINCT invoice_vendor as invoice_vendor', false)
	    ->from("fm_invoice")
	    ->where("invoice_vendor <> ''", null, false)
	    ->where("invoice_vendor IS NOT NULL", null, false)
	    ->get();
	    $result = $query->result_array();
	    $vendors = array();
	    if(count($result)>0){
	        foreach($result as $row) {
	            $vendors[] = $row['invoice_vendor'];
	        }
	    }
	    return $vendors;
	}
}
?>