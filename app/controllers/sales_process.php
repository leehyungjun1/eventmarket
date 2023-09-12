<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');
require_once(APPPATH ."controllers/base/front_base".EXT);

class sales_process extends front_base {

	public function __construct() {
		parent::__construct();
		//세금계산서/현금영수증
		$this->load->model('salesmodel');
		$this->load->model('ordermodel');
	}

	/* 현금영수증발급 */
	public function cashreceiptwrite()
	{
		$this->load->library('cashtax');
		$this->load->library('validation');

		$requestParam = $this->input->post();

		$this->validation->set_data($requestParam);

		$this->validation->set_rules('order_seq', '주문번호', 'trim|numeric|xss_clean');

		if ($this->validation->exec() === false) {
			show_error($this->validation->error_array['value'],	422);
		}

		$order 	= $this->ordermodel->get_order($requestParam['order_seq']);
		$order_tax_prices = $this->ordermodel->get_order_prices_for_tax($requestParam['order_seq'],$order,true);

		if($requestParam["cuse"] == "0"){
			//인증번호
			$this->validation->set_rules('creceipt_number[0]', getAlert('mo003'),'trim|numeric|xss_clean');
		}else{
			//사업자번호
			$this->validation->set_rules('creceipt_number[1]', getAlert('mo002'),'trim|numeric|xss_clean');
		}
		if(isset($requestParam["email"])){
			//이메일
			$this->validation->set_rules('email', getAlert('mo004'),'trim|required|valid_email|xss_clean');
		}

		if($this->validation->exec()===false){
			$err = $this->validation->error_array;
			$callback = "if(parent.document.getElementsByName('{$err['key']}')[0]) parent.document.getElementsByName('{$err['key']}')[0].focus();";
			openDialogAlert($err['value'],400,140,'parent',$callback);
			exit;
		}

		// 현금영수증 접수 가능여부 체크
		$this->check_tax_add('cash', $requestParam['order_seq']);

		$data_tax = $this->salesmodel->tax_calulate(
			$order_tax_prices["tax"],
			$order_tax_prices["exempt"],
			$order_tax_prices["shipping_cost"],
			$order_tax_prices["sale"],
			$order_tax_prices["tax_sale"],'SETTLE');
		$data_etc = $this->salesmodel->tax_calulate(
			$order_tax_prices["tax"],
			$order_tax_prices["exempt"],
			$order_tax_prices["shipping_cost"],
			$order_tax_prices["sale"],
			$order_tax_prices["etc_sale"],'SETTLE');

		//$items = $this->ordermodel->get_item($_POST['order_seq']);
		//$goods_name = strip_tags($items[0]['goods_name'])." 외 " . ( count($items) - 1) . "건";

		if($requestParam['creceipt_seq']){//수정

			$this->db->where('order_seq',$requestParam['order_seq']);
			$this->db->update('fm_order',array('typereceipt'=>'2'));

			$cashparams['typereceipt']			= 2;
			$cashparams['type'				]	= 0;
			$cashparams['seq'	]				= $requestParam['creceipt_seq'];
			$cashparams['order_seq']			= $requestParam['order_seq'];
			$cashparams['person']				= $requestParam['order_user_name'];
			$cashparams['cuse']					= $requestParam['cuse'];
			$cashparams['email']				= $requestParam['email'];
			$cashparams['phone']				= $requestParam['phone'];

			$cashparams['goodsname']		= $order_tax_prices["goods_name"];
			$creceipt_number					= str_replace("-", "", $requestParam['creceipt_number'][$requestParam['cuse']]);
			$cashparams['creceipt_number'	]	= $creceipt_number;
			$cashparams['order_date']			= $order['regist_date'];
			$cashparams['up_date']				= date('Y-m-d H:i:s');
			$cashparams['tstep']				= "1";
			/**$cashparams['price'		]		= $_POST['settleprice'];
			$cashparams['supply'		]		= $_POST['settleprice'] - $order_tax_prices['comm_vat_mny'];
			$cashparams['surtax'		]		= $order_tax_prices['comm_vat_mny'];**/


			$this->salesmodel->sales_modify($cashparams);
			$result_id							= $requestParam['creceipt_seq'];

		}else{
			$sc['whereis']    = ' and  order_seq="'.$this->db->escape_str($requestParam['order_seq']).'" ';
            $sc['select']        = '  *  ';
            $taxparams         = $this->salesmodel->get_data($sc);

			if($taxparams['seq']){ ## 세금계산서 취소 -> 현금영수증 신청 로직 추가 2017-01-17 @nsg
				$this->db->where('order_seq',$requestParam['order_seq']);
				$this->db->update('fm_order',array('typereceipt'=>'2'));
				
				$cashparams['typereceipt']			= 2;
				$cashparams['type']					= 0;//사용자 수동
				$cashparams['seq']					= $taxparams['seq'];
				$cashparams['order_seq']			= $requestParam['order_seq'];
				$cashparams['tstep']				= "1";
				$cashparams['person']				= $requestParam['order_user_name'];
				$cashparams['cuse']					= $requestParam['cuse'];
				$cashparams['email']				= $requestParam['email'];
				$cashparams['phone']				= $requestParam['phone'];
				$cashparams['goodsname'			]	= $order_tax_prices["goods_name"];
				$creceipt_number					= str_replace("-", "", $requestParam['creceipt_number'][$requestParam['cuse']]);
				$cashparams['creceipt_number'	]	= $creceipt_number;
				$cashparams['order_date']			= $order['regist_date'];
				$cashparams['regdate']				= date('Y-m-d H:i:s');
				
				$cashparams['price']		= (int) $data_etc['supply'] + (int) $data_etc['supply_free'] + (int) $data_etc['surtax'];
				$cashparams['supply']		= (int) $data_etc['supply'] + (int) $data_etc['supply_free'];
				$cashparams['surtax']		= (int) $data_etc['surtax'];

				$cashparams['tax_price']	= (int) $data_tax['supply'] + (int) $data_tax['supply_free'] + (int) $data_tax['surtax'];
				$cashparams['tax_supply']	= (int) $data_tax['supply'] + (int) $data_tax['supply_free'];
				$cashparams['tax_surtax']	= (int) $data_tax['surtax'];

				$this->salesmodel->sales_modify($cashparams);
				$result_id							= $cashparams['seq'];

			}else{ // 매출증빙 현금영수증 처음 등록
				$this->db->where('order_seq',$requestParam['order_seq']);
				$this->db->update('fm_order',array('typereceipt'=>'2'));

				$cashparams['typereceipt'	]		= 2;
				$cashparams['type'				]	= 0;//사용자 수동
				$cashparams['order_seq']			= $requestParam['order_seq'];
				$cashparams['member_seq']			= $this->userInfo['member_seq'];
				$cashparams['email']				= $requestParam['email'];
				$cashparams['person']				= $requestParam['order_user_name'];
				$cashparams['cuse']					= $requestParam['cuse'];
				$cashparams['goodsname'	]		= $order_tax_prices["goods_name"];
				$creceipt_number					= str_replace("-", "", $requestParam['creceipt_number'][$requestParam['cuse']]);
				$cashparams['creceipt_number'	]	= $creceipt_number;
				$cashparams['order_date']			= $order['regist_date'];
				$cashparams['regdate']				= date('Y-m-d H:i:s');

				$cashparams['price']					= (int) $data_etc['supply'] + (int) $data_etc['supply_free'] + (int) $data_etc['surtax'];
				$cashparams['supply']				= (int) $data_etc['supply'] + (int) $data_etc['supply_free'];
				$cashparams['surtax']				= (int) $data_etc['surtax'];
				/**$cashparams['price'			]		= $_POST['settleprice'];
				$cashparams['supply'		]			= $_POST['settleprice'] - $order_tax_prices['comm_vat_mny'];
				$cashparams['surtax'		]			= $order_tax_prices['comm_vat_mny'];**/

				$result_id = $this->salesmodel->sales_write($cashparams);
			}
		}

		if(25 <= $order['step'] && $order['step'] <= 75 ){
			$result = firstmall_typereceipt($requestParam['order_seq'],$result_id);
			$callback = "parent.document.location.reload();";
			//현금영수증이 발급 되었습니다.
			openDialogAlert(getAlert('mo005'),400,140,'parent',$callback);
			/**
			$cashparams['paydt']= $cashparams['regdate'];
			$taxResult = $this->cashtax->getCashTax('pay', $cashparams);

			if (is_array($taxResult) == true)
			{

				$upResult['seq'] = $result_id;
				$upResult['tstep'] = 2;//발급완료
				$upResult['order_seq'] = $cashparams['order_seq'];
				$this->salesmodel->sales_modify($upResult);

				$callback = "parent.document.location.reload();";
				openDialogAlert("현금영수증이 발급 되었습니다.",400,140,'parent',$callback);
			}
			else
			{
				$upResult['seq']			 = $result_id;
				$upResult['tstep'] = 4;//발급취소
				$upResult['order_seq'] = $cashparams['order_seq'];
				$this->salesmodel->sales_modify($upResult);

				$this->cashtax->getCashTax('mod', $cashparams);
				$callback = "parent.document.location.reload();";
				openDialogAlert($taxResult." 현금영수증이 발급취소 되었습니다.",400,140,'parent',$callback);
			}
			**/

		}else{
			$taxResult['tstep'] = 1;//발급신청접수
			$taxResult['order_seq'] = $cashparams['order_seq'];
			$this->salesmodel->sales_modify($taxResult);

			$callback = "parent.document.location.reload();";
			//현금영수증이 신청/수정 되었습니다.
			openDialogAlert(getAlert('mo006'),400,140,'parent',$callback);
		}


	}

	/* 세금계산서 신청 */
	public function taxwrite()
	{
		$this->load->model('salesmodel');
		$this->load->library('validation');

		$aPostParam = $this->input->post();

		//주문번호
		$this->validation->set_rules('order_seq', '주문번호','trim|required|numeric|xss_clean');
		//상호명
		$this->validation->set_rules('co_name', getAlert('mo126'),'trim|required|xss_clean');
		//사업자번호
		$this->validation->set_rules('busi_no', getAlert('mo127'),'trim|required|xss_clean');
		//대표자명
		$this->validation->set_rules('co_ceo', getAlert('mo128'),'trim|required|xss_clean');
		//업태
		$this->validation->set_rules('co_status', getAlert('mo129'),'trim|required|xss_clean');
		//업종
		$this->validation->set_rules('co_type', getAlert('mo130'),'trim|required|xss_clean');
		//주소
		$this->validation->set_rules('address', getAlert('mo131'),'trim|required|xss_clean');
		//상세주소
		$this->validation->set_rules('address_detail', getAlert('mo132'),'trim|required|xss_clean');
		//담당자이름
		$this->validation->set_rules('person', getAlert('mo133'),'trim|required|xss_clean');
		//담당자이메일
		$this->validation->set_rules('email', getAlert('mo134'),'trim|required|valid_email|xss_clean');
		//전화번호
		$this->validation->set_rules('phone', getAlert('mo135'),'trim|required|xss_clean');

		if($_POST['new_zipcode']){
			//우편번호
			$this->validation->set_rules('new_zipcode', getAlert('mo136'),'trim|numeric|xss_clean');
		}else{
			//우편번호
			$this->validation->set_rules('zipcode[]', getAlert('mo136'),'trim|numeric|xss_clean');
		}

		if($this->validation->exec()===false){
			$err = $this->validation->error_array;
			$callback = "if(parent.document.getElementsByName('{$err['key']}')[0]) parent.document.getElementsByName('{$err['key']}')[0].focus();";
			openDialogAlert($err['value'],400,140,'parent',$callback);
			exit;
		}

		$orderInfo = $this->ordermodel->get_order($aPostParam['order_seq']);

		$sc['whereis']	= ' and  order_seq="'.$aPostParam['order_seq'].'" ';
		$sc['select']		= '  *  ';
		$cashparams 		= $this->salesmodel->get_data($sc);

		if($aPostParam['person'] == ""){
			$taxparams['person']			= $aPostParam['order_user_name'];
		}else{
			$taxparams['person']			= $aPostParam['person'];
		}

		if($aPostParam['email'] != ""){
			$taxparams['email']			= $aPostParam['email'];
		}

		if($aPostParam['phone'] != ""){
			$taxparams['phone']			= str_replace("-","",$aPostParam['phone']);
		}

		if(isset($aPostParam['zipcode']) && !$aPostParam['new_zipcode']){
			$aPostParam['new_zipcode']	= implode('-', $aPostParam['zipcode']);
		}

		// 세금계산서 접수 가능여부 체크		
		$this->check_tax_add('tax', $aPostParam['order_seq']);

		if($aPostParam['tax_seq']){//수정

			$this->db->where('order_seq',$aPostParam['order_seq']);
			$this->db->update('fm_order',array('typereceipt'=>'1'));
			
			$taxparams['typereceipt']		= 1;
			$taxparams['type']				= 2;//사용자 수동
			$taxparams['seq']				= $aPostParam['tax_seq'];
			$taxparams['order_seq']			= $aPostParam['order_seq'];
			$taxparams['co_name']			= $aPostParam['co_name'];
			$taxparams['co_ceo']			= $aPostParam['co_ceo'];
			$taxparams['co_status']			= $aPostParam['co_status'];
			$taxparams['co_type']			= $aPostParam['co_type'];
			$taxparams['busi_no']			= $aPostParam['busi_no'];
			$taxparams['person']			= $aPostParam['person'];
			$taxparams['tstep']				= "1";


			$taxparams['zipcode']			=  $aPostParam['new_zipcode'];
			$taxparams['address_type']		= ($aPostParam['address_type'])?$aPostParam['address_type']:"zibun";
			$taxparams['address']			= $aPostParam['address'];
			$taxparams['address_street']		= $aPostParam['address_street'];
			$taxparams['address_detail']		= $aPostParam['address_detail'];
			$taxparams['up_date']			= date('Y-m-d H:i:s');
			$this->salesmodel->sales_modify($taxparams);

		}else{
			$order_tax_prices = $this->ordermodel->get_order_prices_for_tax($aPostParam['order_seq'],'',true);
			$data_tax = $this->salesmodel->tax_calulate(
								$order_tax_prices["tax"],
								$order_tax_prices["exempt"],
								$order_tax_prices["shipping_cost"],
								$order_tax_prices["sale"],
								$order_tax_prices["tax_sale"],'SETTLE');
			$data_etc = $this->salesmodel->tax_calulate(
								$order_tax_prices["tax"],
								$order_tax_prices["exempt"],
								$order_tax_prices["shipping_cost"],
								$order_tax_prices["sale"],
								$order_tax_prices["etc_sale"],'SETTLE');

			$sc['whereis']	= ' and  order_seq="'.$aPostParam['order_seq'].'" ';
			$sc['select']		= '  *  ';
			$cashparams 		= $this->salesmodel->get_data($sc);

			if($cashparams['seq']){ ## 현금영수증 취소 -> 세금계산서 신청
				$this->db->where('order_seq',$aPostParam['order_seq']);
				$this->db->update('fm_order',array('typereceipt'=>'1'));
				
				$taxparams['typereceipt']		= 1;
				$taxparams['type']				= 2;//사용자 수동
				$taxparams['seq'	]			= $cashparams['seq'];
				$taxparams['order_seq']			= $aPostParam['order_seq'];
				$taxparams['co_name']			= $aPostParam['co_name'];
				$taxparams['co_ceo']			= $aPostParam['co_ceo'];
				$taxparams['co_status']			= $aPostParam['co_status'];
				$taxparams['co_type']			= $aPostParam['co_type'];
				$taxparams['busi_no']			= $aPostParam['busi_no'];
				$taxparams['person']			= $aPostParam['person'] ? $aPostParam['person'] : $aPostParam['order_user_name'];
				$taxparams['order_name']		= $orderInfo['order_user_name'];
				$taxparams['tstep']				= "1";
				$taxparams['zipcode']			=  $aPostParam['new_zipcode'];
				$taxparams['address_type']		= ($aPostParam['address_type'])?$aPostParam['address_type']:"zibun";
				$taxparams['address']			= $aPostParam['address'];
				$taxparams['address_street']	= $aPostParam['address_street'];
				$taxparams['address_detail']	= $aPostParam['address_detail'];
				$taxparams['regdate']			= date('Y-m-d H:i:s');

				// 과세 매출증빙 저장
				$taxparams['price']			= (int) $data_etc['supply'] + (int) $data_etc['surtax'];
				$taxparams['supply']		= (int) $data_etc['supply'];
				$taxparams['surtax']		= (int) $data_etc['surtax'];
				$taxparams['tax_price']		= (int) $data_tax['supply'] + (int) $data_tax['surtax'];
				$taxparams['tax_supply']	= (int) $data_tax['supply'];
				$taxparams['tax_surtax']	= (int) $data_tax['surtax'];
				if( $data_etc['surtax'] > 0 ){
					$this->salesmodel->sales_modify($taxparams);
				}

				// 비과세 매출증빙 저장
				$taxparams['price']			= (int) $data_etc['supply_free'];
				$taxparams['supply']		= (int) $data_etc['supply_free'];
				$taxparams['surtax']		= 0;
				$taxparams['tax_price']		= (int) $data_tax['supply_free'];
				$taxparams['tax_supply']	= (int) $data_tax['supply_free'];
				$taxparams['tax_surtax']	= 0;
				if( $data_etc['supply_free'] > 0 ){
					$this->salesmodel->sales_modify($taxparams);
				}

			}else{ // 매출증빙 세금계산서 처음 등록

				$this->db->where('order_seq',$aPostParam['order_seq']);
				$this->db->update('fm_order',array('typereceipt'=>'1'));

				$order 	= $this->ordermodel->get_order($aPostParam['order_seq']);			

				$taxparams['typereceipt'	]	= 1;
				$taxparams['type']				= 2;//사용자 수동
				$taxparams['order_seq']			= $aPostParam['order_seq'];
				$taxparams['member_seq']		= $this->userInfo['member_seq'];
				$taxparams['price'		]		= $aPostParam['settleprice'];
				$taxparams['co_name']			= $aPostParam['co_name'];
				$taxparams['co_ceo']			= $aPostParam['co_ceo'];
				$taxparams['co_status']			= $aPostParam['co_status'];
				$taxparams['co_type']			= $aPostParam['co_type'];
				$taxparams['busi_no']			= $aPostParam['busi_no'];
				$taxparams['person']			= $aPostParam['person'] ? $aPostParam['person'] : $aPostParam['order_user_name'];
				$taxparams['order_name']		= $orderInfo['order_user_name'];
				$taxparams['zipcode']			= $aPostParam['new_zipcode'];
				$taxparams['address_type']		= ($aPostParam['address_type'])?$aPostParam['address_type']:"zibun";
				$taxparams['address']			= $aPostParam['address'];
				$taxparams['address_street']	= $aPostParam['address_street'];
				$taxparams['address_detail']	= $aPostParam['address_detail'];
				$taxparams['order_date']		= $order['regist_date'];
				$taxparams['regdate']			= date('Y-m-d H:i:s');
				$taxparams['goodsname']			= $order_tax_prices['goods_name'];

				// 과세 매출증빙 저장
				$taxparams['price']				= get_cutting_price($data_etc['supply'] + $data_etc['surtax']);
				$taxparams['supply']			= get_cutting_price($data_etc['supply']);
				$taxparams['surtax']			= get_cutting_price($data_etc['surtax']);
				$taxparams['tax_price']			= get_cutting_price($data_tax['supply'] + $data_tax['surtax']);
				$taxparams['tax_supply']		= get_cutting_price($data_tax['supply']);
				$taxparams['tax_surtax']		= get_cutting_price($data_tax['surtax']);
				if( $data_etc['surtax'] > 0 ){
					$this->salesmodel->sales_write($taxparams);
				}

				// 비과세 매출증빙 저장
				$taxparams['price']			= get_cutting_price($data_etc['supply_free']);
				$taxparams['supply']		= get_cutting_price($data_etc['supply_free']);
				$taxparams['surtax']		= 0;
				$taxparams['tax_price']		= get_cutting_price($data_tax['supply_free']);
				$taxparams['tax_supply']	= get_cutting_price($data_tax['supply_free']);
				$taxparams['tax_surtax']	= 0;
				if( $data_etc['supply_free'] > 0 ){
					$this->salesmodel->sales_write($taxparams);
				}
			}
		}

		firstmall_typereceipt($aPostParam['order_seq']);

		//세금계산서를 저장하였습니다.
		openDialogAlert(getAlert('mo137'),400,140,'parent',"parent.taxlayerclose();parent.location.reload();");
		exit;
	}

	public function taxdelete()
	{
		if(empty($_POST['tax_seq'])) {
			//잘못된 접근입니다.
			$return = array('result'=>false, 'msg'=>getAlert('mo123'));
			echo json_encode($return);
			exit;
		}

		$this->load->model('salesmodel');
		$result = $this->salesmodel->sales_delete($_POST['tax_seq']);

		if($result) {
			$this->db->where('order_seq',$_POST['order_seq']);
			$this->db->update('fm_order',array('typereceipt'=>'0'));

			//삭제되었습니다.
			$return = array('result'=>true, 'msg'=>getAlert('mo124'));
			echo json_encode($return);
			exit;
		}else{
			//세금계산서삭제가 실패 되었습니다.
			$return = array('result'=>false, 'msg'=>getAlert('mo125'));
			echo json_encode($return);
			exit;
		}
	}

	protected function check_tax_add($type='cash', $order_seq='') {
		$order 	= $this->ordermodel->get_order($order_seq);		

		switch($type) {
			case 'cash': // 현금영수증
				if(strtotime($order['deposit_date']."+10 days") <= time()) {
					openDialogAlert(sprintf(getAlert('mp219'), date('Y-m-d', strtotime($order['deposit_date']."+10 days"))),400,160,'parent');
					exit;
				}
				break;
			case 'tax': // 세금계산서
				$tax_limit_date = date('Y-m-05 23:59:59', strtotime($order['deposit_date']."+1 month"));
				if(strtotime($tax_limit_date) <= time()) {
					openDialogAlert(sprintf(getAlert('mp220'), date('Y-m-d', strtotime($tax_limit_date))),400,160,'parent');
					exit;
				}
				break;
		}
	}
}

/* End of file sales_process.php */
/* Location: ./app/controllers/admin/sales_process.php */