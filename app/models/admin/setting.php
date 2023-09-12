<?php
class Setting extends CI_Model {

	/* pg사 선택정보 저장 */
	public function set_pg_company(){
		config_save('system',array('pgCompany'=>$_POST['pgCompany']));

		/* 주요행위 기록 */
		$this->load->model('managermodel');
		$this->managermodel->insert_action_history($this->managerInfo['manager_seq'],'pg_setting');
	}

	/* pg사 에스크로마크 업로드 */
	public function upload_escrow_mark(){
		$pgCompany = $_POST['pgCompany'];

		$escrowMarkPath = ROOTPATH."data/icon/escrow_mark/";

		if($_POST['newEscrowMark']){
			$filePath = ROOTPATH.$_POST['newEscrowMark'];

			if(file_exists($filePath)){
				$tmp = explode('.',$_POST['newEscrowMark']);
				$fileExt = $tmp[count($tmp)-1];
				$fileName = $pgCompany.".".$fileExt;
				if(copy($filePath,$escrowMarkPath.$fileName)){
					@chmod($escrowMarkPath.$fileName,0777);
					config_save($pgCompany,array('escrowMark'=>$fileName));
				}
			}
		}

		if($_POST['newEscrowMarkMobile']){
			$filePath = ROOTPATH.$_POST['newEscrowMarkMobile'];

			if(file_exists($filePath)){
				$tmp = explode('.',$_POST['newEscrowMarkMobile']);
				$fileExt = $tmp[count($tmp)-1];
				$fileName = $pgCompany."_mobile.".$fileExt;
				if(copy($filePath,$escrowMarkPath.$fileName)){
					@chmod($escrowMarkPath.$fileName,0777);
					config_save($pgCompany,array('escrowMarkMobile'=>$fileName));
				}
			}
		}

	}

	/* 파비콘 파일 저장 */
	public function upload_favicon()
	{
		if($_POST['faviconFile']=="")
		{
			$this->load->model('adminenvmodel');
			$where_params['shopSno']	= $this->config_system['shopSno'];
			$query	= $this->adminenvmodel->get($where_params, 0, 1);
			$row	= $query->row_array();
			if($row['favicon']){
				@unlink('./'.$row['favicon']);
			}
			$update_params['favicon']	= '';
			$where_params['shopSno']	= $this->config_system['shopSno'];
			$this->adminenvmodel->update($update_params,$where_params);
		}else{

			if(preg_match("/^\/?data\/tmp/i", $_POST['faviconFile'])){
				$this->load->model('usedmodel');
				$data_used = $this->usedmodel->used_limit_check();
				if( $data_used['type'] ){
					if($favicon){
						@unlink($_SERVER['DOCUMENT_ROOT'].$favicon);
						$favicon = "";
					}

					// 폴더가 없을 수도 있어 생성처리
					if(!is_dir(ROOTPATH.'data/icon/favicon')){
						@mkdir(ROOTPATH.'data/icon/favicon');
						@chmod(ROOTPATH.'data/icon/favicon',0777);
					}

					// 파일 이름 재정의
					$ext			= explode(".",$_POST['faviconFile']);
					$ext			= $ext[count($ext)-1];
					$filename		= 'faviconFile'.".{$ext}";
					$new_path		= "/data/icon/favicon/{$filename}";

					// 파일 이동 처리
					copy(ROOTPATH.$_POST['faviconFile'], ROOTPATH.$new_path);
					chmod(ROOTPATH.$new_path,0777);

					// 파일 이동 후 db 값에 갱신 하기 위해 재정의 한 파일명 넣음
					$favicon	= $new_path;

				}else{
					openDialogAlert($data_used['msg'],400,140,'parent','');
				}
			}
		}

		return $favicon;
	}

	/* 아이콘 저장 :: 2016-01-04 lwh */
	public function upload_book_icon($iconType='iphoneicon')
	{
		if($_POST[$iconType]=="")
		{
			$icon = config_load('system', $iconType);
			@unlink('./'.$icon[$iconType]);
			config_save('system',array($iconType=>''));
			$icon="";
		}else{

			/* 기존 정보 호출 */
			$this->load->model('usedmodel');
			$data_used = $this->usedmodel->used_limit_check();
			$data	= config_load('system', $iconType);
			$icon	= ($data[$iconType]) ? $data[$iconType] : '';

			if(preg_match("/^\/?data\/tmp/i", $_POST[$iconType])){

				if( $data_used['type'] ){
					if($icon){
						@unlink($_SERVER['DOCUMENT_ROOT'].$icon);
						$icon = "";
					}
					// 폴더가 없을 수도 있어 생성처리
					if(!is_dir(ROOTPATH.'data/icon/favicon')){
						@mkdir(ROOTPATH.'data/icon/favicon');
						@chmod(ROOTPATH.'data/icon/favicon',0777);
					}

					// 파일 이름 재정의
					$ext			= explode(".",$_POST[$iconType]);
					$ext			= $ext[count($ext)-1];
					$filename		= $iconType.".{$ext}";
					$new_path		= "/data/icon/favicon/{$filename}";

					// 파일 이동 처리
					copy(ROOTPATH.$_POST[$iconType], ROOTPATH.$new_path);
					chmod(ROOTPATH.$new_path,0777);

					// 파일 이동 후 db 값에 갱신 하기 위해 재정의 한 파일명 넣음
					$icon	= $new_path;
				}else{
					openDialogAlert($data_used['msg'],400,140,'parent','');

					exit;
				}
			}
		}

		return $icon;
	}

	/* snslogo 파일 저장 */
	public function upload_snslogo()
	{
		if($_POST['snslogo']=="")
		{
			$snslogo = config_load('system', 'snslogo');
			@unlink('./'.$snslogo['snslogo']);
			config_save('system',array('snslogo'=>''));
			$snslogo="";
		}else{

			/* 기존 설정정보 로드*/
			$data = config_load('system', 'snslogo');
			$snslogo = $data['snslogo'];

			if(preg_match("/^\/?data\/tmp/i", $_POST['snslogo'])){

				// 폴더가 없을 수도 있어 생성처리
				if(!is_dir(ROOTPATH.'data/icon/favicon')){
					@mkdir(ROOTPATH.'data/icon/favicon');
					@chmod(ROOTPATH.'data/icon/favicon',0777);
				}

				// 파일 이름 재정의
				$ext			= explode(".",$_POST['snslogo']);
				$ext			= $ext[count($ext)-1];
				$file_name		= "snslogo".".{$ext}";
				$new_path		= "/data/icon/favicon/{$file_name}";

				// 파일 이동 처리
				copy(ROOTPATH.$_POST['snslogo'], ROOTPATH.$new_path);
				chmod(ROOTPATH.$new_path,0777);

				// 파일 이동 후 db 값에 갱신 하기 위해 재정의 한 파일명 넣음
				$snslogo = $new_path;
			}
		}

		return $snslogo;
	}

	/* kcp 결제창 로고 체크 */
	public function chk_kcp_paylog(){
		if($_POST['kcp_logo_val_img']){
			$filePath	= ROOTPATH.$_POST['kcp_logo_val_img'];
			$size		= getImageSize($filePath);
			if		($size[0] > 150){
				return 'width_over';
			}elseif	($size[1] > 50){
				return 'height_over';
			}
		}

		return null;
	}

	/* kcp 결제창 로고 업로드 */
	public function upload_kcp_logo(){
		$pgCompany = $_POST['pgCompany'];

		$kcp_logo_path = "/data/icon/manager/";

		if($_POST['kcp_logo_val_img']){
			$filePath	= ROOTPATH.$_POST['kcp_logo_val_img'];
			if(file_exists($filePath)){
				$tmp = explode('.',$_POST['kcp_logo_val_img']);
				$fileExt = $tmp[count($tmp)-1];
				$fileName = $pgCompany."_paylogo_".date('YmdHis').".".$fileExt;
				if(copy($filePath,ROOTPATH.$kcp_logo_path.$fileName)){
					@chmod(ROOTPATH.$kcp_logo_path.$fileName,0777);
					config_save('kcp',array('kcp_logo_val_img'=>$kcp_logo_path.$fileName));
					config_save('kcp',array('kcp_logo_img_filename'=>$_POST['kcp_logo_img_filename']));
				}
			}
		}
	}

	/*기본설정 저장*/
	public function basic($icon){
		foreach($_POST as $k => $data){
			if( ! is_array($data) ){
				$_POST[$k] = str_replace(array("'","\""),array("&apos;","&quot;"),$data);;
			}
		}
		$_POST['shopBranch'] = serialize($_POST['shopBranch']);
		$_POST['businessLicense'] = implode('-',$_POST['businessLicense']);

		if( isset($_POST['providerNumber']))$_POST['providerNumber'] = implode('-',$_POST['providerNumber']) != '--' ? implode('-',$_POST['providerNumber']) : '';
		if( isset($_POST['companyPhone']))$_POST['companyPhone'] = implode('-',$_POST['companyPhone']) != '--' ? implode('-',$_POST['companyPhone']) : '';
		if($_POST['area_number']) {
			$_POST['companyPhone'] = $_POST['area_number'].'-'.$_POST['companyPhone'];
		}
		if( isset($_POST['companyFax'])) $_POST['companyFax'] = implode('-',$_POST['companyFax']) != '--' ? implode('-',$_POST['companyFax']) : '';

		$_POST['companyZipcode'] = implode('-',$_POST['companyZipcode']);
		if($icon['favicon'])	config_save('system',array('favicon'=>$icon['favicon']));
		if($icon['iphoneicon']) config_save('system',array('iphoneicon'=>$icon['iphoneicon']));
		if($icon['androidicon'])config_save('system',array('androidicon'=>$icon['androidicon']));
		if($icon['signatureicon'])config_save('system',array('signatureicon'=>$icon['signatureicon']));
		config_save('system',array('domain'=>$_POST['domain']));
		config_save('basic',array('shopName'=>$_POST['shopName']));
		config_save('basic',array('shopBranch'=> $_POST['shopBranch'] ));
		config_save('basic',array('shopTitleTag'=>$_POST['shopTitleTag']));
		config_save('basic',array('shopGoodsTitleTag'=>$_POST['shopGoodsTitleTag']));
		config_save('basic',array('shopCategoryTitleTag'=>$_POST['shopCategoryTitleTag']));
		config_save('basic',array('metaTagUse'=>$_POST['metaTagUse']));
		config_save('basic',array('companyName'=>$_POST['companyName']));
		config_save('basic',array('businessConditions'=>$_POST['businessConditions']));
		config_save('basic',array('businessLine'=>$_POST['businessLine']));
		config_save('basic',array('businessLicense'=>$_POST['businessLicense']));
		config_save('basic',array('mailsellingLicense'=>$_POST['mailsellingLicense']));
		config_save('basic',array('ceo'=>$_POST['ceo']));
		config_save('basic',array('providerNumber'=>$_POST['providerNumber']));
		config_save('basic',array('companyPhone'=>$_POST['companyPhone']));
		config_save('basic',array('companyFax'=>$_POST['companyFax']));
		config_save('basic',array('companyEmail'=>$_POST['companyEmail']));
		config_save('basic',array('partnershipEmail'=>$_POST['partnershipEmail']));
		config_save('basic',array('companyZipcode'=>$_POST['companyZipcode']));
		config_save('basic',array('companyAddress_type'=>$_POST['companyAddress_type']));
		config_save('basic',array('companyAddress'=>$_POST['companyAddress']));
		config_save('basic',array('companyAddress_street'=>$_POST['companyAddress_street']));
		config_save('basic',array('companyAddressDetail'=>$_POST['companyAddressDetail']));

		//개인정보 관련 문구추가 @2016-09-06 ysm
		config_save('basic',array('member_info_manager'=>$_POST['member_info_manager']));
		config_save('basic',array('member_info_part'=>$_POST['member_info_part']));
		config_save('basic',array('member_info_rank'=>$_POST['member_info_rank']));
		config_save('basic',array('member_info_tel'=>$_POST['member_info_tel']));
		config_save('basic',array('member_info_email'=>$_POST['member_info_email']));

		config_save('basic',array('useestimate'=>$_POST['useestimate']));
		config_save('basic',array('usetradeinfo'=>$_POST['usetradeinfo']));
		//2016-05-03 jhr 네이버맵키 API변경
		if($_POST['naverMapKey']) config_save('basic',array('naverMapKey'=>$_POST['naverMapKey']));
		config_save('basic',array('map_client_zoom'=>$_POST['map_client_zoom']));
		if	($_POST['naverMapKey'] == 'API')
			config_save('basic',array('mapKey'=>$_POST['mapKey']));
		else{
			config_save('basic',array('map_client_id'=>$_POST['map_client_id']));
			config_save('basic',array('map_client_secret'=>$_POST['map_client_secret']));
		}
	}

	/* SNS마케팅 저장*/
	public function snsconf($snslogo){
		config_save('system',array('snslogo'=>$snslogo));
	}

	/*
	 * PG.결제사 미사용 설정 저장
	 */
	public function notPg(){
		config_save('system',array('not_use_pg'=>'y'));
		config_save('order',array('cashreceiptuse'=>'0'));// 매출증빙 미사용 처리
	}

	/* kcp설정 저장 */
	public function kcp($data){
		if ($data['not_use_pg'] == 'y') {
			config_save('system', ['not_use_pg' => 'y']);
		} else {
			config_save('system', ['not_use_pg' => 'n']);
		}

		config_save('kcp',array('mallCode'=>$data['mallCode']));
		config_save('kcp',array('merchantKey'=>$data['merchantKey']));
		config_save('kcp',array('payment'=>$data['payment']));
		config_save('kcp',array('interestTerms'=>$data['interestTerms']));
		config_save('kcp',array('nonInterestTerms'=>$data['nonInterestTerms']));
		config_save('kcp',array('pcCardCompanyCode'=>$data['pcCardCompanyCode']));
		config_save('kcp',array('pcCardCompanyTerms'=>$data['pcCardCompanyTerms']));
		config_save('kcp',array('escrow'=>$data['escrow']));
		config_save('kcp',array('escrowAccountLimit'=>$data['escrowAccountLimit']));
		config_save('kcp',array('escrowVirtualLimit'=>$data['escrowVirtualLimit']));
		config_save('kcp',array('cashReceipts'=>$data['cashReceipts']));
		config_save('kcp',array('mobilePayment'=>$data['mobilePayment']));
		config_save('kcp',array('mobileInterestTerms'=>$data['mobileInterestTerms']));
		config_save('kcp',array('mobileNonInterestTerms'=>$data['mobileNonInterestTerms']));
		config_save('kcp',array('mobileCardCompanyCode'=>$data['mobileCardCompanyCode']));
		config_save('kcp',array('mobileCardCompanyTerms'=>$data['mobileCardCompanyTerms']));
		config_save('kcp',array('mobileEscrow'=>$data['mobileEscrow']));
		config_save('kcp',array('mobileEscrowAccountLimit'=>$data['mobileEscrowAccountLimit']));
		config_save('kcp',array('mobileEscrowVirtualLimit'=>$data['mobileEscrowVirtualLimit']));
		config_save('kcp',array('mobileCashReceipts'=>$data['mobileCashReceipts']));
		config_save('kcp',array('kcp_skin_color'=>$data['kcp_skin_color']));
		config_save('kcp',array('kcp_logo_type'=>$data['kcp_logo_type']));
		config_save('kcp',array('kcp_logo_val_text'=>$data['kcp_logo_val_text']));
		config_save('kcp',array('nonActiveXUse'=>$data['nonActiveXUse'])); //2017-05-24 jhs 크로스 브라우징 결제 모듈 추가
	}

	/* lg설정 저장 */
	public function lg(){
		if( $_POST['not_use_pg'] == 'y' ) config_save('system',array('not_use_pg'=>'y'));
		else config_save('system',array('not_use_pg'=>'n'));

		config_save('lg',array('mallCode'=>$_POST['mallCode']));
		config_save('lg',array('merchantKey'=>$_POST['merchantKey']));
		config_save('lg',array('payment'=>$_POST['payment']));
		config_save('lg',array('interestTerms'=>$_POST['interestTerms']));
		config_save('lg',array('nonInterestTerms'=>$_POST['nonInterestTerms']));
		config_save('lg',array('pcCardCompanyCode'=>$_POST['pcCardCompanyCode']));
		config_save('lg',array('pcCardCompanyTerms'=>$_POST['pcCardCompanyTerms']));
		config_save('lg',array('escrow'=>$_POST['escrow']));
		config_save('lg',array('escrowAccountLimit'=>$_POST['escrowAccountLimit']));
		config_save('lg',array('escrowVirtualLimit'=>$_POST['escrowVirtualLimit']));
		config_save('lg',array('cashReceipts'=>$_POST['cashReceipts']));
		config_save('lg',array('mobilePayment'=>$_POST['mobilePayment']));
		config_save('lg',array('mobileInterestTerms'=>$_POST['mobileInterestTerms']));
		config_save('lg',array('mobileNonInterestTerms'=>$_POST['mobileNonInterestTerms']));
		config_save('lg',array('mobileCardCompanyCode'=>$_POST['mobileCardCompanyCode']));
		config_save('lg',array('mobileCardCompanyTerms'=>$_POST['mobileCardCompanyTerms']));
		config_save('lg',array('mobileEscrow'=>$_POST['mobileEscrow']));
		config_save('lg',array('mobileEscrowAccountLimit'=>$_POST['mobileEscrowAccountLimit']));
		config_save('lg',array('mobileEscrowVirtualLimit'=>$_POST['mobileEscrowVirtualLimit']));
		config_save('lg',array('mobileCashReceipts'=>$_POST['mobileCashReceipts']));
		config_save('lg',array('nonActiveXUse'=>$_POST['nonActiveXUse'])); //2017-05-24 jhs 크로스 브라우징 결제 모듈 추가

		$this->load->helper('file');
		$mallConfPath = ROOTPATH."pg/lgdacom/conf/mall.conf";
		$mallConfContents = read_file($mallConfPath);
		$mallConfContents = preg_replace("/\n[\t\s]*[a-z0-9_\-]{2,}[\t\s]*=[\t\s]*[0-9a-z]{32}/i","",$mallConfContents);
		if($_POST['mallCode'] && $_POST['merchantKey']){
			$mallConfContents .= "\r\n{$_POST['mallCode']} = {$_POST['merchantKey']}";
		}
		write_file($mallConfPath,$mallConfContents);

	}

	/* kspay설정 저장 */
	public function kspay(){
		if( $_POST['not_use_pg'] == 'y' ) config_save('system',array('not_use_pg'=>'y'));
		else config_save('system',array('not_use_pg'=>'n'));

		config_save('kspay',array('mallId'=>$_POST['mallId']));
		config_save('kspay',array('mallPass'=>$_POST['mallPass']));
		config_save('kspay',array('payment'=>$_POST['payment']));
		config_save('kspay',array('interestTerms'=>$_POST['interestTerms']));
		config_save('kspay',array('nonInterestTerms'=>$_POST['nonInterestTerms']));
		config_save('kspay',array('pcCardCompanyCode'=>$_POST['pcCardCompanyCode']));
		config_save('kspay',array('pcCardCompanyTerms'=>$_POST['pcCardCompanyTerms']));
		config_save('kspay',array('escrow'=>$_POST['escrow']));
		config_save('kspay',array('escrowAccountLimit'=>$_POST['escrowAccountLimit']));
		config_save('kspay',array('escrowVirtualLimit'=>$_POST['escrowVirtualLimit']));
		config_save('kspay',array('cashReceipts'=>$_POST['cashReceipts']));
		config_save('kspay',array('mobilePayment'=>$_POST['mobilePayment']));
		config_save('kspay',array('mobileInterestTerms'=>$_POST['mobileInterestTerms']));
		config_save('kspay',array('mobileNonInterestTerms'=>$_POST['mobileNonInterestTerms']));
		config_save('kspay',array('mobileCardCompanyCode'=>$_POST['mobileCardCompanyCode']));
		config_save('kspay',array('mobileCardCompanyTerms'=>$_POST['mobileCardCompanyTerms']));
		config_save('kspay',array('mobileEscrow'=>$_POST['mobileEscrow']));
		config_save('kspay',array('mobileEscrowAccountLimit'=>$_POST['mobileEscrowAccountLimit']));
		config_save('kspay',array('mobileEscrowVirtualLimit'=>$_POST['mobileEscrowVirtualLimit']));
		config_save('kspay',array('mobileCashReceipts'=>$_POST['mobileCashReceipts']));
	}

	/* inicis 저장 */
	public function inicis(){
		$aPostData = $this->input->post();
		if ($aPostData['not_use_pg'] == 'y') {
			config_save('system', array('not_use_pg'=>'y'));
		} else {
			config_save('system', array('not_use_pg' => 'n'));
		}
		config_save('inicis', array('mallCode' => $aPostData['mallCode']));
		config_save('inicis', array('merchantKey' => $aPostData['merchantKey']));
		config_save('inicis', array('payment' => $aPostData['payment']));
		config_save('inicis', array('interestTerms' => $aPostData['interestTerms']));
		config_save('inicis', array('nonInterestTerms' => $aPostData['nonInterestTerms']));
		config_save('inicis', array('pcCardCompanyCode' => $aPostData['pcCardCompanyCode']));
		config_save('inicis', array('pcCardCompanyTerms' => $aPostData['pcCardCompanyTerms']));
		config_save('inicis', array('escrowMallCode' => $aPostData['escrowMallCode']));
		config_save('inicis', array('escrowMerchantKey' => $aPostData['escrowMerchantKey']));
		config_save('inicis', array('escrow' => $aPostData['escrow']));
		config_save('inicis', array('escrowAccountLimit' => $aPostData['escrowAccountLimit']));
		config_save('inicis', array('escrowVirtualLimit' => $aPostData['escrowVirtualLimit']));
		config_save('inicis', array('cashReceipts' => $aPostData['cashReceipts']));
		config_save('inicis', array('mobilePayment' => $aPostData['mobilePayment']));
		config_save('inicis', array('mobileInterestTerms' => $aPostData['mobileInterestTerms']));
		config_save('inicis', array('mobileNonInterestTerms' => $aPostData['mobileNonInterestTerms']));
		config_save('inicis', array('mobileCardCompanyCode' => $aPostData['mobileCardCompanyCode']));
		config_save('inicis', array('mobileCardCompanyTerms' => $aPostData['mobileCardCompanyTerms']));
		config_save('inicis', array('mobileEscrow' => $aPostData['mobileEscrow']));
		config_save('inicis', array('mobileEscrowAccountLimit' => $aPostData['mobileEscrowAccountLimit']));
		config_save('inicis', array('mobileEscrowVirtualLimit' => $aPostData['mobileEscrowVirtualLimit']));
		config_save('inicis', array('mobileCashReceipts' => $aPostData['mobileCashReceipts']));
		config_save('inicis', array('nonActiveXUse' => $aPostData['nonActiveXUse'])); //2017-05-24 jhs 크로스 브라우징 결제 모듈 추가
		config_save('inicis', array('signKey' => $aPostData['signKey'])); //2017-08-23 jhs 이니시스 상점키 이중화에 따른 추가 코드 생성
		config_save('inicis', array('escrowSignKey' => $aPostData['escrowSignKey'])); //2017-08-23 jhs 이니시스 상점키 이중화에 따른 추가 코드 생성
		config_save('inicis', array('iniapiKey' => $aPostData['iniapiKey']));
		config_save('inicis', array('iniapiIv' => $aPostData['iniapiIv']));
	}

	/* allat 저장 */
	public function allat(){
		if( $_POST['not_use_pg'] == 'y' ) config_save('system',array('not_use_pg'=>'y'));
		else config_save('system',array('not_use_pg'=>'n'));

		config_save('allat',array('mallCode'=>$_POST['mallCode']));
		config_save('allat',array('merchantKey'=>$_POST['merchantKey']));
		config_save('allat',array('payment'=>$_POST['payment']));
		config_save('allat',array('nonInterestYn'=>$_POST['nonInterestYn']));
		config_save('allat',array('interestTerms'=>$_POST['interestTerms']));
		config_save('allat',array('escrow'=>$_POST['escrow']));
		config_save('allat',array('escrowAccountLimit'=>$_POST['escrowAccountLimit']));
		config_save('allat',array('escrowVirtualLimit'=>$_POST['escrowVirtualLimit']));
		config_save('allat',array('cashReceipts'=>$_POST['cashReceipts']));
		config_save('allat',array('mobilePayment'=>$_POST['mobilePayment']));
		config_save('allat',array('mobileNonInterestYn'=>$_POST['mobileNonInterestYn']));
		config_save('allat',array('mobileInterestTerms'=>$_POST['mobileInterestTerms']));
		config_save('allat',array('mobileEscrow'=>$_POST['mobileEscrow']));
		config_save('allat',array('mobileEscrowAccountLimit'=>$_POST['mobileEscrowAccountLimit']));
		config_save('allat',array('mobileEscrowVirtualLimit'=>$_POST['mobileEscrowVirtualLimit']));
		config_save('allat',array('mobileCashReceipts'=>$_POST['mobileCashReceipts']));
		config_save('allat',array('nonActiveXUse'=>$_POST['nonActiveXUse'])); //2017-05-24 jhs 크로스 브라우징 결제 모듈 추가
	}

	/* 카카오페이 설정 저장 :: 2015-02-10 lwh */
	public function kakaopay(){

		if( $_POST['not_use_kakao'] == 'y' )
				config_save('system',array('not_use_kakao'=>'y'));
		else	config_save('system',array('not_use_kakao'=>'n'));

		config_save('payment',array('kakaopay'=>'카카오페이'));
		config_save('kakaopay',array('mid'=>$_POST['kakao_mid']));
		config_save('kakaopay',array('merchantEncKey'=>$_POST['kakao_merchantEncKey']));
		config_save('kakaopay',array('merchantHashKey'=>$_POST['kakao_merchantHashKey']));
		config_save('kakaopay',array('merchantKey'=>$_POST['kakao_merchantKey']));
		config_save('kakaopay',array('cancelPwd'=>$_POST['kakao_cancelPwd']));
		config_save('kakaopay',array('payment'=>$_POST['kakaopay_payment']));
		config_save('kakaopay',array('interestTerms'=>$_POST['kakaopay_interestTerms']));
		config_save('kakaopay',array('nonInterestTerms'=>$_POST['kakaopay_nonInterestTerms']));
		config_save('kakaopay',array('CardCompanyCode'=>$_POST['kakaoCardCompanyCode']));
		config_save('kakaopay',array('CardCompanyTerms'=>$_POST['kakaoCardCompanyTerms']));
	}

	/* 다음카카오페이 설정 저장 :: 2017-12-08 lwh */
	public function daumkakaopay(){

		if( $_POST['not_use_daumkakaopay'] == 'y' )
				config_save('system',array('not_use_daumkakaopay'=>'y'));
		else	config_save('system',array('not_use_daumkakaopay'=>'n'));

		config_save('payment',array('daumkakaopay'=>'카카오페이'));
		config_save('daumkakaopay',array('cid'=>$_POST['kakao_cid']));
		config_save('daumkakaopay',array('nonActiveXUse'=>'Y'));
		config_save('daumkakaopay',array('payment_opt'=>$_POST['payment_opt']));
		config_save('daumkakaopay',array('interestTerms'=>$_POST['interestTerms']));
	}

	/* 페이코(payco) 설정저장 2018-08-22 lwh */
	public function payco(){
		if( $_POST['not_use_payco'] == 'y' )
				config_save('system',array('not_use_payco'=>'y'));
		else	config_save('system',array('not_use_payco'=>'n'));

		if(!$_POST['use_set'])	$_POST['use_set'] = 'real';

		$params = array(
						'use_set'		=> $_POST['use_set'],
						'sellerKey'		=> $_POST['sellerKey'],
						'cpId'			=> $_POST['cpId'],
						'productId'		=> $_POST['productId'],
						'currency'		=> $_POST['payco_currency'],
						'method_code'	=> '||'.implode('||',$_POST['method_code']),
						'nonActiveXUse'	=> 'Y'
					);
		config_save('payment',array('payco'=>'페이코'));
		config_save('payco',$params);
	}

	/* 페이팔(paypal) 설정저장 2016-07-26 pjm */
	public function paypal(){
		if( $_POST['not_use_paypal'] == 'y' )
				config_save('system',array('not_use_paypal'=>'y'));
		else	config_save('system',array('not_use_paypal'=>'n'));

		$params = array(
						'paypal_currency'=>$_POST['paypal_currency'],
						'paypal_username'=>$_POST['paypal_username'],
						'paypal_userpasswd'=>$_POST['paypal_userpasswd'],
						'paypal_signature'=>$_POST['paypal_signature'],
					);
		config_save('payment',array('paypal'=>'페이팔'));
		config_save('paypal',$params);
	}

	/* 엑심베이(eximbay) 설정저장 2016-07-26 pjm */
	public function eximbay(){
		if( $_POST['not_use_eximbay'] == 'y' )
				config_save('system',array('not_use_eximbay'=>'y'));
		else	config_save('system',array('not_use_eximbay'=>'n'));

		$params = array(
						'eximbay_mid'						=>$_POST['eximbay_mid'],
						'eximbay_secretkey'					=>$_POST['eximbay_secretkey'],
						'eximbay_payment'					=>$_POST['eximbay_payment'],
						'eximbay_cur'						=>$_POST['eximbay_cur']
					);
		config_save('eximbay',array('eximbay'=>'엑심베이'));
		config_save('eximbay',$params);
	}

	/*
	 * 네이버 페이 설정 저장 2017-06-12 jhs
	 */
	public function naverpay(){
		### 네이버 체크아웃 설정 저장
		$config_param = array();
		$config_param['version'] 	= trim($_POST['navercheckout_ver']);
		$config_param['use'] 		= trim($_POST['navercheckout_use']);
		$config_param['shop_id'] 	= trim($_POST['navercheckout_shop_id']);
		$config_param['certi_key'] 	= trim($_POST['navercheckout_certi_key']);
		$config_param['button_key'] = trim($_POST['navercheckout_button_key']);
		$config_param['culture'] = trim($_POST['navercheckout_culture']);
		$config_param['except_category_code'] = array();
		foreach($_POST['issueCategoryCode'] as $value){
			$config_param['except_category_code'][] = array('category_code'=>$value);
		}
		$config_param['except_goods'] = array();
		foreach($_POST['except_goods'] as $value){
			$config_param['except_goods'][] = array('goods_seq'=>$value);
		}
		$config_param['culture_goods'] = array();
		if($config_param['culture']=='choice') {
			foreach($_POST['culture_goods'] as $value){
				$config_param['culture_goods'][] = array('goods_seq'=>$value);
			}
		}
		if( $config_param['use'] == 'y' || $config_param['use'] == 'test'){
			if( !$config_param['shop_id'] ){
				openDialogAlert("상점 ID 필수 입니다.",400,140,'parent',$callback);
				exit;
			}
			if( !$config_param['certi_key'] ){
				openDialogAlert("상점 인증키 필수 입니다.",400,140,'parent',$callback);
				exit;
			}
			if( !$config_param['button_key'] ){
				openDialogAlert("버튼키는 필수 입니다.",400,140,'parent',$callback);
				exit;
			}
		}

		# Npay 2.1 사용 설정(상품연동 2.1 / 주문연동 5.0)
		if($_POST['navercheckout_ver'] == "2.1"){

			# Npay 현재 사용 버전
			$old_navercheckout = config_load('navercheckout');
			if(!$old_navercheckout['version'] && in_array($old_navercheckout['use'],array("y","test"))){
				$old_navercheckout_version = "1.0";
			}else{
				$old_navercheckout_version = $old_navercheckout['version'];
			}

			if(in_array($_POST['navercheckout_use'],array("y","test"))){

				# Npay에서 사용될 함수 체크
				$func_chk = function_exists('hash_hmac') ;
				if(!$func_chk){
					return array("result"=>"ERROR","message"=>"<span style=\'color:red;\'>Npay 사용이 불가한 환경입니다.<br />퍼스트몰에 문의해 주세요.</span>");
					exit;
				}

				# 중계서버에 가맹점 세팅 정보 확인
				$npay_chk = $this->npay_use_chk('npay_shop_upgrade',$old_navercheckout_version);

				if($npay_chk['result'] == "ERROR"){
					openDialogAlert($npay_chk['message'],350,160,'parent');
					exit;
				}
				if(!$old_navercheckout['npay_btn_pc_goods']) $config_param['npay_btn_pc_goods']		= 'A-1-2-236×88';
				if(!$old_navercheckout['npay_btn_mobile_goods']) $config_param['npay_btn_mobile_goods']	= 'MA-1-2-290×85';

			}

			# Npay 2.1 사용여부 중계서버에 업데이트
			if($old_navercheckout_version == "2.1" && $_POST['navercheckout_use'] != $old_navercheckout['use']){
				$old_navercheckout['new_useyn'] = $_POST['navercheckout_use'];
				$this->npay_use_chk('npay_status_update',$old_navercheckout_version);
				# firstmall 관리 서버로 전송
				$this->naverpay_firstmall_apply("useyn",$old_navercheckout);
			}

		}

		config_save('navercheckout',$config_param);

		# Npay 2.1 사용 설정 시 네이버페이 전용 문의게시판 생성
		if($_POST['navercheckout_ver'] == "2.1" && $_POST['navercheckout_use'] == "y"){
			$this->load->model("Boardmanager");
			$params 	= array('board_id'=>'naverpay_qna','board_name'=>'네이버페이문의');
			$qna_res	= $this->Boardmanager->set_partner_board_create($params);
		}

		# npay 필드 네이버마일리지 설정에서 제외
		$config_param['use']								= "";
		$config_param['version']							= "";
		$config_param['npay_btn_pc_goods']		= "";
		$config_param['npay_btn_mobile_goods']	= "";

		$config_param['naver_mileage_yn']		= $_POST['naver_mileage_yn'];
		$config_param['naver_mileage_api_id'] = $_POST['naver_mileage_api_id'];
		$config_param['naver_mileage_secret']	= $_POST['naver_mileage_secret'];
		if( $_POST['naver_mileage_test']) $config_param['naver_mileage_test']	= $_POST['naver_mileage_test'];

		if( $config_param['naver_mileage_yn'] == 'y' ){
			if( !$_POST['naver_mileage_api_id'] || !$_POST['naver_mileage_secret'] ){
				openDialogAlert("외부인증아이디와 인증키는 필수 입니다.",400,140,'parent',$callback);
				exit;
			}
		}

		### 설정저장
		config_save('naver_mileage',$config_param);
	}

	# 중계서버에 설정된 가맹점 정보 체크 및 사용여부 업데이트
	public function npay_use_chk($mode,$old_navercheckout_version){

		$targetUrl	= "https://npayapi.firstmall.kr/npay/npay_status_check.php";

		$mallid		= ($_POST['naverpay_mall_id'])? $_POST['naverpay_mall_id'] : $_POST['navercheckout_shop_id'];

		$params		= array("mode"		=> $mode,
				"shopSno"		=> $this->config_system['shopSno'],
				"mallid"		=> $mallid,
				"npay_status"	=> $_POST['navercheckout_use']);

		if($mode == "npay_shop_upgrade"){

			# 현재 사용중인 버전이 1.0 이면 업그레이드 신청
			if($old_navercheckout_version == "1.0"){
				$gubun		= "upgrade";
				$status		= "apply";
				# 현재 사용중인 버전이 없다면. 신규 등록
			}elseif(!trim($old_navercheckout_version)){
				$gubun		= "new";
				$status		= "complete";
			}


			$config_basic	= config_load("basic");
			$params_sub		= array("mode"		=> $mode,
					"userid"		=> $this->config_system['service']['cid'],
					"domain"		=> $this->config_system['domain'],
					"subDomain"		=> $this->config_system['subDomain'],
					"companyname"	=> $config_basic['shopName'],
					"applyid"		=> $this->managerInfo['manager_id'],
					"applyname"		=> $this->managerInfo['mname'],
					"gubun"			=> $gubun,
					"status"		=> $status,
					"server_ip"		=> $_SERVER['SERVER_ADDR']
			);
			$params = array_merge($params,$params_sub);
		}

		$ci = curl_init();
		curl_setopt($ci, CURLOPT_SSL_VERIFYPEER, FALSE);
		curl_setopt($ci, CURLOPT_SSL_VERIFYHOST, FALSE);
		curl_setopt($ci, CURLOPT_RETURNTRANSFER, TRUE);
		curl_setopt($ci, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
		curl_setopt($ci, CURLOPT_URL, $targetUrl);
		curl_setopt($ci, CURLOPT_POST, TRUE);
		curl_setopt($ci, CURLOPT_TIMEOUT, 10);
		curl_setopt($ci, CURLOPT_POSTFIELDS, $params);

		// 주문 등록 후 결과값 확인
		$response = curl_exec($ci);

		if($response == false){
			$err = 'Curl error '. curl_error($ci);
			return $err;
			exit;
		}
		curl_close($ci);

		if($response == "E0001"){

			$result		= "ERROR";
			$message	= "Npay 사용 가능한 상태가 아닙니다.<br />퍼스트몰에 문의해 주세요.";

		}elseif($response == "E0002"){

			$result		= "ERROR";
			$message	= "동일한 가맹점 ID가 이미 존재 합니다.<br />퍼스트몰에 문의해 주세요.";

		}else{

			if($gubun == "new"){
				# firstmall 관리 서버로 전송
				$params['status']			= "9";
				$params['naverpay_mall_id'] = $params['mallid'];
				$this->naverpay_firstmall_apply('new',$params);
			}

			$result		= "SUCCESS";
			$message	= "";
		}

		return array("result"=>$result,"message"=>$message);

	}

	# 네이버페이 2.0 업그레이드 신청
	public function naverpay_upgrade(){

		# Npay에서 사용될 함수 체크
		$func_chk = function_exists('hash_hmac') ;
		if(!$func_chk){
			openDialogAlert("<span style=\'color:red;\'>Npay 사용이 불가한 환경입니다.<br />퍼스트몰에 문의 주세요.</span>",350,160,'parent');
			exit;
		}

		if(!trim($_POST['naverpay_mall_id'])){
			openDialogAlert("페이가맹점ID를 입력해 주세요.",350,150,'parent');
			exit;
		}
		if(!trim($_POST['naverpay_email'][0]) | !trim($_POST['naverpay_email'][1])){
			openDialogAlert("이메일주소를 입력해 주세요.",350,150,'parent');
			exit;
		}
		if(!trim($_POST['naverpay_user_phone'])){
			openDialogAlert("휴대폰번호를 입력해 주세요.",350,150,'parent');
			exit;
		}

		$config_basic							= config_load("basic");
		$navercheckout_tmp = $navercheckout = array();

		$navercheckout['naverpay_mall_id']		= $_POST['naverpay_mall_id'];
		$navercheckout['naverpay_user_email']	= implode("@",$_POST['naverpay_email']);
		$navercheckout['naverpay_user_phone']	= $_POST['naverpay_user_phone'];

		$navercheckout_tmp						= $navercheckout;
		config_save('navercheckout_tmp',$navercheckout_tmp);	//위치변경하지말것

		config_save('navercheckout',array("use"=>"test"));	//업그레이드 신청시 현재 사용여부는 "테스트"로

		$navercheckout['userid']				= $this->config_system['service']['cid'];
		$navercheckout['shopSno']				= $this->config_system['shopSno'];
		$navercheckout['domain']				= $this->config_system['domain'];
		$navercheckout['subDomain']				= $this->config_system['subDomain'];
		$navercheckout['gubun']					= "upgrade";
		$navercheckout['companyname']			= $config_basic['shopName'];
		$navercheckout['applyid']				= $this->managerInfo['manager_id'];
		$navercheckout['applyname']				= $this->managerInfo['manager_id'];
		$navercheckout['npay_status']			= "n";
		$navercheckout['returnUrl']				= $_SERVER['HTTP_HOST'];

		# firstmall 관리 서버로 전송
		$this->naverpay_firstmall_apply("upgrade",$navercheckout);

		# 중계서버로 전송
		$this->npay_use_chk("npay_shop_upgrade","1.0");

	}

	# firstmall 관리 서버로 전송
	public function naverpay_firstmall_apply($mode,$navercheckout){

		$target_url = "https://firstmall.kr/naverAPI/npay_apply.php";

		if($mode == "new"){
			echo "<iframe name='actionframe2' ></iframe>";
			echo "<form name='upgrade' method='post' target='actionframe2' action='".$target_url."'>";
		}else{
			echo "<form name='upgrade' method='post' action='".$target_url."'>";
		}
		echo "<input type='hidden' name='mode' value='".$mode."'>";

		foreach($navercheckout as $key=>$val){
			echo "<input type='hidden' name='".$key."' value='".$val."'>";
		}
		echo "</form>";
		echo "<script type='text/javascript'>";
		echo " upgrade.submit(); ";
		echo "</script>";
	}

	# 네이버페이 2.0 업그레이드 신청 결과(firstmall에서 신청처리 후 호출됨)
	public function naverpay_upgrade_result(){

		$callback = '';

		# 임시저장한 네이버페이 업그레이드 신청 정보 불러오기
		$navercheckout_tmp = config_load('navercheckout_tmp');
		foreach($navercheckout_tmp as $key=>$val){
			$navercheckout[$key]		= $val;
			$navercheckout_tmp[$key]	= "";
		}
		if($_GET['res_code']){

			if($_GET['res_code'] == "E0000"){

				# 네이버페이 업그레이드 신청 정보 저장
				$navercheckout['version']				= '2.1';
				$navercheckout['use']					= 'test';
				$navercheckout['npay_btn_pc_goods']		= 'A-1-2-236×88';
				$navercheckout['npay_btn_mobile_goods']	= 'MA-1-2-290×85';
				config_save('navercheckout',$navercheckout);

				$msg		= "네이버페이 업그레이드 신청 되었습니다.";
				$callback	= 'parent.location.reload()';

			}elseif($_GET['res_code'] == "E0001"){

				$navercheckout['version']				= '2.1';
				$navercheckout['use']					= 'test';
				$navercheckout['npay_btn_pc_goods']		= 'A-1-2-236×88';
				$navercheckout['npay_btn_mobile_goods']	= 'MA-1-2-290×85';
				# 네이버페이 업그레이드 신청 정보 저장
				config_save('navercheckout',$navercheckout);

				$msg		= "이미 네이버페이 업그레이드 신청되었습니다.";
				$callback	= 'parent.location.reload()';

			}elseif($_GET['res_code'] == "E0002"){

				$msg		= "네이버페이 업그레이드 신청서 저장 오류";
			}

		}else{

			$msg = "일시적 장애입니다. 신청버튼을 다시 눌러주세요";

		}

		# 임시저장한 네이버페이 업그레이드 신청 정보 삭제
		config_save('navercheckout_tmp',$navercheckout_tmp);

		openDialogAlert($msg,400,150,'parent',$callback);
	}

	/**
	 * 카카오톡 설정 저장
	 */
	function talkbuy() {
		$aPostParams = $this->input->post();
		/**
		 * 카카오페이 설정 저장하기 시작
		 */
		$this->load->library("talkbuylibrary");
		$this->talkbuylibrary->save_talkbuy_config($aPostParams);
		/**
		 * 카카오페이 설정 저장하기 종료
		 */
	}

	// 네이버페이 결제형 설정 저장
	function naverpayment() {
		$aPostParams = $this->input->post();

		if ( $aPostParams['naverpayment_use'] == 'n' ) {
			config_save('system', ['not_use_naverpayment'=>'y']);
		} else {
			config_save('system', ['not_use_naverpayment'=>'n']);
		}

		$params = [
			'client_id' => $aPostParams['client_id'],
			'client_secret' => $aPostParams['client_secret'],
			'partner_id' => $aPostParams['partner_id'],
			'use' => $aPostParams['naverpayment_use'],
			'nonActiveXUse'	=> 'Y'
		];
		config_save('payment', ['naverpayment'=>'네이버페이 결제형']);
		config_save('naverpayment',$params);
	}

	/* bank 저장 */
	public function bank(){
		/* 설정 초기화 */
		config_delete("bank");
		config_save('order',array('bank' => 'n'));

		/* 설정저장 */
		foreach($_POST['bank'] as $key => $bank){
			if(!$_POST['bankUser'][$key]||!$_POST['account'][$key]) continue;
			$account = $_POST['account'][$key];
			$tmp = array(
					'bank'=>$bank,
					'bankUser'=>$_POST['bankUser'][$key],
					'account'=>$account,
					'accountUse'=>$_POST['accountUse'][$key]
			);
			if($key == 0) config_save('order',array('bank' => 'y'));
			config_save('bank',array($key => $tmp));
		}

		/* 주요행위 기록 */
		$this->load->model('managermodel');
		$this->managermodel->insert_action_history($this->managerInfo['manager_seq'],'bank_setting');
	}

	/* bank2 저장 */
	public function bank2(){
		/* 설정 초기화 */
		config_delete("bank_return");

		/* 설정저장 */
		foreach($_POST['bankReturn'] as $key => $bank){
			if(!$_POST['bankUserReturn'][$key]||!$_POST['accountReturn'][$key]) continue;
			$account = $_POST['accountReturn'][$key];
			$tmp = array(
					'bankReturn'=>$bank,
					'bankUserReturn'=>$_POST['bankUserReturn'][$key],
					'accountReturn'=>$account,
					'accountUseReturn'=>$_POST['accountUseReturn'][$key]
			);
			config_save('bank_return',array($key => $tmp));
		}
	}

	/* 보안설정 저장 */
	public function protect(){
		$this->load->model('ssl');

		$setSystemConfig = array();

		switch($_POST['ssl']){
			case "pay":
				$setSystemConfig['ssl_use'] = 1;
				$setSystemConfig['ssl_pay'] = 1;
				$setSystemConfig['ssl_external'] = $_POST['ssl_external']?1:0;
				$setSystemConfig['ssl_ex_domain'] = trim($_POST['ssl_ex_domain']);
				$setSystemConfig['ssl_ex_port'] = trim($_POST['ssl_ex_port']);
				$setSystemConfig['ssl_page'] = $_POST['ssl_page']?1:0;
			break;
			case "free":
				$setSystemConfig['ssl_use'] = 1;
				$setSystemConfig['ssl_pay'] = 0;
			break;
			default:
				$setSystemConfig['ssl_use'] = 0;
				$setSystemConfig['ssl_pay'] = 0;
			break;
		}
		/*
		테스트용 설정
		$setSystemConfig['ssl_kind'] = "Thawte SSL 128bit";
		$setSystemConfig['ssl_status'] = 1;
		$setSystemConfig['ssl_period_start'] = "2012-05-23";
		$setSystemConfig['ssl_period_expire'] = "2012-12-24";
		$setSystemConfig['ssl_port'] = "80";
		$setSystemConfig['ssl_domain'] = "www.lks.firstmall.kr";
		*/
		$setSystemConfig['protectIp'] = implode("\n",$_POST['protectIp']);
		$setSystemConfig['protectMouseRight'] = $_POST['protectMouseRight'];
		$setSystemConfig['protectMouseDragcopy'] = $_POST['protectMouseDragcopy'];

		config_save('system',$setSystemConfig);

	}

	/* 다음쇼핑하우 로고1,2 업로드 */
	public function upload_daumshopping_logo(){

		$data = config_load('system');
		$daumshopping_logo1 = $data['daumshopping_logo1'];
		$daumshopping_logo2 = $data['daumshopping_logo2'];

		$this->load->model('usedmodel');
		$data_used = $this->usedmodel->used_limit_check();
		if( $data_used['type'] ){

			$upload_path = './data/icon/daumshopping';
			if(!is_dir($upload_path)){
				@mkdir($upload_path);
				@chmod($upload_path,0707);
			}

			if($_FILES['logoimg1']['tmp_name']){
				if($daumshopping_logo1){
					@unlink($_SERVER['DOCUMENT_ROOT'].$daumshopping_logo1);
					$daumshopping_logo1 = "";
				}
				$file_ext = end(explode('.', $_FILES['logoimg1']['name']));//확장자추출
				$config['upload_path'] = $upload_path;
				$config['max_size']	= $this->config_system['uploadLimit'];
				$config['file_name'] = 'daumshopping_logo1_'.time().".".$file_ext;
				$config['allowed_types'] = 'gif|jpg|png';
				$this->load->library('upload', $config);
				if ( ! $this->upload->do_upload('logoimg1'))
				{
					$error = $this->upload->display_errors();
					openDialogAlert($error,400,100,'parent');
					exit;
				}
				$uploadData = $this->upload->data();
				$daumshopping_logo1 = str_replace($_SERVER['DOCUMENT_ROOT'],'',$uploadData['file_path']).$uploadData['raw_name'].$uploadData['file_ext'];
			}

			if($_FILES['logoimg2']['tmp_name']){
				if($daumshopping_logo2){
					@unlink($_SERVER['DOCUMENT_ROOT'].$daumshopping_logo2);
					$daumshopping_logo2 = "";
				}
				$file_ext = end(explode('.', $_FILES['logoimg2']['name']));//확장자추출
				$config['upload_path'] = $upload_path;
				$config['max_size']	= $this->config_system['uploadLimit'];
				$config['file_name'] = 'daumshopping_logo2_'.time().".".$file_ext;
				$config['allowed_types'] = 'gif|jpg|png';
				$this->load->library('upload', $config);
				if ( ! $this->upload->do_upload('logoimg2'))
				{
					$error = $this->upload->display_errors();
					openDialogAlert($error,400,100,'parent');
					exit;
				}
				$uploadData = $this->upload->data();
				$daumshopping_logo2 = str_replace($_SERVER['DOCUMENT_ROOT'],'',$uploadData['file_path']).$uploadData['raw_name'].$uploadData['file_ext'];
			}

		}else{
			openDialogAlert($data_used['msg'],400,140,'parent','');
		}

		return array($daumshopping_logo1,$daumshopping_logo2);

	}
	## 기본정보저장
	public function multi_basic_info($icon,$isplusfreenot,$aPostParams=array()){

		if(!$aPostParams) $aPostParams = $this->input->post();

		$this->load->model('adminenvmodel');
		$this->load->model('currencymodel');
		$domain = str_replace('http://','',$aPostParams['domain']);
		$domain = str_replace('https://','',$domain);
		$domain = str_replace('www.','',$domain);
		$update_params = array(
			'admin_env_name'	=> trim($aPostParams['admin_env_name']),
			'domain'			=> trim($domain),
			'language'			=> $aPostParams['language'],
			'compare_currency'	=> $aPostParams['compare_currency']
		);

		if( $aPostParams['basic_currency'] ){
			$update_params['currency'] = $aPostParams['basic_currency'];
		}

		if($icon['favicon']){
			$update_params['favicon']	= $icon['favicon'];
		}
		$where_params	= array(
			'admin_env_seq'		=> $aPostParams['admin_env_seq']
		);
		$this->adminenvmodel->update($update_params,$where_params);
		list($arr_currency_amout) = code_load('currency_amout',$aPostParams['basic_currency']);
		$codes_currency_amout =  $arr_currency_amout['value'];

		foreach($aPostParams['currency_seq'] as $key => $currency_seq){
			$currency_kind	= $aPostParams['currency_kind'][$key];
			$currency_amout = 1 / $codes_currency_amout;
			$update_params = array(
				'currency'					=> $currency_kind,
				'currency_amout'			=> $currency_amout,
				'cutting_price'				=> $aPostParams['cutting_price'][$key],
				'cutting_action'			=> $aPostParams['cutting_action'][$key],
				'currency_symbol'			=> $aPostParams['currency_symbol'][$key],
				'currency_symbol_position'	=> $aPostParams['currency_symbol_position'][$key]
			);
			$where_params	= array(
				'currency_seq'		=> $currency_seq
			);
			$this->currencymodel->update($update_params,$where_params);
		}

		foreach($aPostParams['currency_exchange_seq'] as $key => $currency_seq){
			$update_params = array(
					'currency_exchange'				=> $aPostParams['currency_exchange'][$key]
			);
			$where_params	= array(
					'currency_seq'		=> $currency_seq
			);
			$this->currencymodel->update($update_params,$where_params);
		}

		// 카카오싱크 정보 변경
		if(isKakaoSyncUse()){
			$this->load->library('AdditionService/kakaosync/Client');
			$this->client->kakaosyncModify();
		}
	}
	## 추가정보저장
	public function multi_add_info($icon,$isplusfreenot,$aPostParams = array()){

		if(!$aPostParams) $aPostParams = $this->input->post();

		$this->load->model('configsalemodel');
		foreach($aPostParams as $k => $data){
			if( ! is_array($data) ){
				$aPostParams[$k] = str_replace(array("'","\""),array("&apos;","&quot;"),$data);;
			}
		}
		$aPostParams['shopBranch']		= serialize($aPostParams['shopBranch']);

		## 모바일 혜택 저장
		$this->db->delete('fm_config_sale', array('type' => 'mobile'));
		$mobilesize = count($aPostParams['mobile_price1']);
		if($mobilesize) {
			for($i=0;$i<$mobilesize;$i++) {
				$price1					= $aPostParams['mobile_price1'][$i];
				$price2					= $aPostParams['mobile_price2'][$i];
				$sale_price				= (int) $aPostParams['mobile_sale_price'][$i];
				$sale_emoney			= (int) $aPostParams['mobile_sale_emoney'][$i];
				$sale_point				=  (int)  $aPostParams['mobile_sale_point'][$i];
				$params['type']				= 'mobile';
				$params['price1']			= $price1;
				$params['price2']			= $price2;
				$params['sale_price']		= $sale_price;
				$params['sale_emoney']		= $sale_emoney;

				if($aPostParams['mobile_reserve_select'][$i]=='year'){
					$reserve_limit = date("Y-m-d", mktime(0,0,0,12, 31, date("Y")+$aPostParams['mobile_reserve_year'][$i]));//$_POST['mobile_reserve_year'][$i]."-12-31";
				}else if($aPostParams['mobile_reserve_select'][$i]=='direct'){
					$reserve_limit = date("Y-m-d", mktime(0,0,0,date("m")+$aPostParams['mobile_reserve_direct'][$i], date("d"), date("Y")));
				}else{
					$reserve_limit = "";
				}
				if($aPostParams['mobile_point_select'][$i]=='year'){
					$point_limit = date("Y-m-d", mktime(0,0,0,12, 31, date("Y")+$aPostParams['mobile_point_year'][$i]));//$_POST['mobile_point_year'][$i]."-12-31";
				}else if($aPostParams['mobile_point_select'][$i]=='direct'){
					$point_limit = date("Y-m-d", mktime(0,0,0,date("m")+$aPostParams['mobile_point_direct'][$i], date("d"), date("Y")));
				}else{
					$point_limit = "";
				}
				$params['reserve_limit']		= $reserve_limit;
				$params['reserve_select']		= $aPostParams['mobile_reserve_select'][$i];
				$params['reserve_year']			= $aPostParams['mobile_reserve_year'][$i];
				$params['reserve_direct']		= $aPostParams['mobile_reserve_direct'][$i];

				if( $isplusfreenot ) { //무료몰이아닌경우에만 적용
					if($aPostParams['mobile_point_select'][$i]=='year'){
						$point_limit = date("Y-m-d", mktime(0,0,0,12, 31, date("Y")+$aPostParams['mobile_point_year'][$i]));//$_POST['mobile_point_year'][$i]."-12-31";
					}else if($aPostParams['mobile_point_select'][$i]=='direct'){
						$point_limit = date("Y-m-d", mktime(0,0,0,date("m")+$aPostParams['mobile_point_direct'][$i], date("d"), date("Y")));
					}else{
						$point_limit = "";
					}
					$params['sale_point']		= $sale_point;
					$params['point_limit']		= $point_limit;
					$params['point_select']		= ($aPostParams['mobile_point_select'][$i])?$aPostParams['mobile_point_select'][$i]:'';
					$params['point_year']		= ($aPostParams['mobile_point_year'][$i])?$aPostParams['mobile_point_year'][$i]:'';
					$params['point_direct']		= ($aPostParams['mobile_point_direct'][$i])?$aPostParams['mobile_point_direct'][$i]:'';
				}else{
					$params['sale_point']		= 0;
					$params['point_limit']		= '';
					$params['point_select']		= '';
					$params['point_year']		= '';
					$params['point_direct']		= '';
				}

				$params['regist_date']	= date("Y-m-d H:i:s");
				$params['add']	= "";
				$this->configsalemodel->confsale_write($params);//
			}
		}

		if($icon['iphoneicon']){
			config_save('system',array('iphoneicon'=>$icon['iphoneicon']));
		}
		if($icon['androidicon']){
			config_save('system',array('androidicon'=>$icon['androidicon']));
		}

		config_save('basic',array('shopBranch'=> $aPostParams['shopBranch'] ));
		config_save('basic',array('metaTagUse'=>$aPostParams['metaTagUse']));
		## 즐겨찾기 혜택 저장
		config_save("reserve",array('default_reserve_bookmark'=>$aPostParams['default_reserve_bookmark']));
		config_save("reserve",array('book_reserve_select'=>$aPostParams['book_reserve_select']));
		config_save("reserve",array('book_reserve_year'=>$aPostParams['book_reserve_year']));
		config_save("reserve",array('book_reserve_direct'=>$aPostParams['book_reserve_direct']));
		if( $isplusfreenot ) { //무료몰이아닌경우에만 적용 @2013-01-14
			config_save("reserve",array('default_point_bookmark'=>$aPostParams['default_point_bookmark']));
			config_save("reserve",array('book_point_select'=>$aPostParams['book_point_select']));
			config_save("reserve",array('book_point_year'=>$aPostParams['book_point_year']));
			config_save("reserve",array('book_point_direct'=>$aPostParams['book_point_direct']));
		}
	}
	## 사업자정보저장
	public function multi_bussiness_info($icon,$isplusfreenot,$aPostParams = array()){

		if(!$aPostParams) $aPostParams = $this->input->post();

		foreach($aPostParams as $k => $data){
			if( ! is_array($data) ){
				$aPostParams[$k]	= str_replace(array("'","\""),array("&apos;","&quot;"),$data);;
			}
		}
		$aPostParams['businessLicense']	= implode('-',$aPostParams['businessLicense']);
		if( isset($aPostParams['providerNumber'])){
			$aPostParams['providerNumber'] = implode('-',$aPostParams['providerNumber']) != '--' ? implode('-',$aPostParams['providerNumber']) : '';
		}
		if( isset($aPostParams['companyPhone'])){
			$aPostParams['companyPhone']	= implode('-',$aPostParams['companyPhone']) != '--' ? implode('-',$aPostParams['companyPhone']) : '';
		}
		if($aPostParams['area_number']) {
			$aPostParams['companyPhone']	= $aPostParams['area_number'].'-'.$aPostParams['companyPhone'];
		}
		if( isset($aPostParams['companyFax'])){
			$aPostParams['companyFax']	= implode('-',$aPostParams['companyFax']) != '--' ? implode('-',$aPostParams['companyFax']) : '';
		}
		if( $aPostParams['companyZipcode'] ){
			$companyZipcode = implode('-',$aPostParams['companyZipcode']);
		}
		if( empty($aPostParams['companyEmail'][0]) || empty($aPostParams['companyEmail'][1])) {
			$aPostParams['companyEmail'] = '';
		}
		if( empty($aPostParams['partnershipEmail'][0]) || empty($aPostParams['partnershipEmail'][1])) {
			$aPostParams['partnershipEmail'] = '';
		}
		config_save('basic',array('companyName'=>$aPostParams['companyName']));
		config_save('basic',array('businessConditions'=>$aPostParams['businessConditions']));
		config_save('basic',array('businessLine'=>$aPostParams['businessLine']));
		config_save('basic',array('businessLicense'=>$aPostParams['businessLicense']));
		config_save('basic',array('mailsellingLicense'=>$aPostParams['mailsellingLicense']));
		config_save('basic',array('ceo'=>$aPostParams['ceo']));
		config_save('basic',array('providerNumber'=>$aPostParams['providerNumber']));
		config_save('basic',array('companyPhone'=>$aPostParams['companyPhone']));
		config_save('basic',array('companyFax'=>$aPostParams['companyFax']));
		config_save('basic',array('companyEmail'=>$aPostParams['companyEmail']));
		config_save('basic',array('partnershipEmail'=>$aPostParams['partnershipEmail']));
		config_save('basic',array('companyZipcode'=>$companyZipcode));
		config_save('basic',array('companyAddress_type'=>$aPostParams['companyAddress_type']));
		config_save('basic',array('companyAddress'=>$aPostParams['companyAddress']));
		config_save('basic',array('companyAddress_street'=>$aPostParams['companyAddress_street']));
		config_save('basic',array('companyAddressDetail'=>$aPostParams['companyAddressDetail']));

		//개인정보 관련 문구추가 @2016-09-06 ysm
		config_save('basic',array('member_info_manager'=>$aPostParams['member_info_manager']));
		config_save('basic',array('member_info_part'=>$aPostParams['member_info_part']));
		config_save('basic',array('member_info_rank'=>$aPostParams['member_info_rank']));
		config_save('basic',array('member_info_tel'=>$aPostParams['member_info_tel']));
		config_save('basic',array('member_info_email'=>$aPostParams['member_info_email']));

		config_save('basic',array('useestimate'=>$aPostParams['useestimate']));
		config_save('basic',array('usetradeinfo'=>$aPostParams['usetradeinfo']));
		if($icon['signatureicon']) {
			config_save('system',array('signatureicon'=>$icon['signatureicon']));
		}

		## 본사 미니샵이미지적용
		$this->load->model('providermodel');

		//모바일 바탕화면 아이콘(안드로이드)
		if(preg_match("/^\/?data\/tmp/i", $aPostParams['main_visual'])){

			// 폴더가 없을 수도 있어 생성처리
			if(!is_dir(ROOTPATH.'data/provider')){
				@mkdir(ROOTPATH.'data/provider');
				@chmod(ROOTPATH.'data/provider',0777);
			}

			// 파일 이름 재정의
			$ext			= explode(".",$aPostParams['main_visual']);
			$ext			= $ext[count($ext)-1];
			$filename		= "main_visual".".{$ext}";
			$new_path		= "/data/provider/{$filename}";

			// 파일 이동 처리
			copy(ROOTPATH.$aPostParams['main_visual'], ROOTPATH.$new_path);
			chmod(ROOTPATH.$new_path,0777);

			// 파일 이동 후 db 값에 갱신 하기 위해 재정의 한 파일명 넣음
			$aPostParams['main_visual']	= $new_path;
			$aPostParams['main_visual']	= $this->providermodel->upload_minishop_image('base', $aPostParams['main_visual'], $aPostParams['org_main_visual']);
		}else{
			if	($aPostParams['del_main_visual'] == 'y'){
				$this->providermodel->delete_minishop_image($aPostParams['org_main_visual']);
				$aPostParams['org_main_visual']	= '';
			}
			$aPostParams['main_visual']	= $aPostParams['org_main_visual'];
		}

		$providerinfodata['main_visual']	= $aPostParams['main_visual'];
		$this->db->update('fm_provider', $providerinfodata, array('provider_seq'=>1));

		## 네이버맵키 API변경
		if($aPostParams['naverMapKey']) config_save('basic',array('naverMapKey'=>$aPostParams['naverMapKey']));
		config_save('basic',array('map_client_zoom'=>$aPostParams['map_client_zoom']));
		if	($aPostParams['naverMapKey'] == 'API')
			config_save('basic',array('mapKey'=>$aPostParams['mapKey']));
		else{
			config_save('basic',array('map_client_id'=>$aPostParams['map_client_id']));
			config_save('basic',array('map_client_secret'=>$aPostParams['map_client_secret']));
		}
	}
	/* kicc설정 저장 */
	public function kicc(){
		if( $_POST['not_use_pg'] == 'y' ) config_save('system',array('not_use_pg'=>'y'));
		else config_save('system',array('not_use_pg'=>'n'));

		config_save('kicc',array('mallCode'=>$_POST['mallCode']));
		config_save('kicc',array('payment'=>$_POST['payment']));
		config_save('kicc',array('interestTerms'=>$_POST['interestTerms']));
		config_save('kicc',array('nonInterestTerms'=>$_POST['nonInterestTerms']));
		config_save('kicc',array('pcCardCompanyCode'=>$_POST['pcCardCompanyCode']));
		config_save('kicc',array('pcCardCompanyTerms'=>$_POST['pcCardCompanyTerms']));
		config_save('kicc',array('escrow'=>$_POST['escrow']));
		config_save('kicc',array('escrowAccountLimit'=>$_POST['escrowAccountLimit']));
		config_save('kicc',array('escrowVirtualLimit'=>$_POST['escrowVirtualLimit']));
		config_save('kicc',array('cashReceipts'=>$_POST['cashReceipts']));
		config_save('kicc',array('mobilePayment'=>$_POST['mobilePayment']));
		config_save('kicc',array('mobileInterestTerms'=>$_POST['mobileInterestTerms']));
		config_save('kicc',array('mobileNonInterestTerms'=>$_POST['mobileNonInterestTerms']));
		config_save('kicc',array('mobileCardCompanyCode'=>$_POST['mobileCardCompanyCode']));
		config_save('kicc',array('mobileCardCompanyTerms'=>$_POST['mobileCardCompanyTerms']));
		config_save('kicc',array('mobileEscrow'=>$_POST['mobileEscrow']));
		config_save('kicc',array('mobileEscrowAccountLimit'=>$_POST['mobileEscrowAccountLimit']));
		config_save('kicc',array('mobileEscrowVirtualLimit'=>$_POST['mobileEscrowVirtualLimit']));
		config_save('kicc',array('mobileCashReceipts'=>$_POST['mobileCashReceipts']));
		config_save('kicc',array('nonActiveXUse'=>$_POST['nonActiveXUse'])); //2017-05-24 jhs 크로스 브라우징 결제 모듈 추가
	}

	public function inicis_key_upload($sMallCodeName)
	{
		$this->load->library('upload');
		$this->load->helper('javascript');

		if ( ! in_array($sMallCodeName, array('escrowMallCode', 'mallCode'))){
			return false;
		}

		$key_dir = ROOTPATH . 'pg/inicis/key/' . $this->input->post($sMallCodeName);
		if ( ! is_dir($key_dir, 0707)) mkdir($key_dir);

		$arr = array(
			'keypass'=>'keypass.enc',
			'mcert'=>'mcert.pem',
			'mpriv'=>'mpriv.pem'
		);
		if ($sMallCodeName == 'escrowMallCode'){
			$arr = array(
				'escrowKeypass'=>'keypass.enc',
				'escrowMcert'=>'mcert.pem',
				'escrowMpriv'=>'mpriv.pem'
			);
		}
		$config['upload_path'] = $key_dir . '/';
		$config['allowed_types'] = implode('|', array('enc', 'pem'));
		$config['max_size']	= $this->config_system['uploadLimit'];
		$config['overwrite'] = true;	// 키파일은 덮어쓰기 필수

		foreach ($arr as $keyword => $keyfile) {
			if ($_FILES[$keyword]['tmp_name']) {
				$this->upload->initialize($config, true);
				if ($this->upload->do_upload($keyword)) {
					@chmod($this->upload->upload_path . $this->upload->file_name, 0707);
				}
			}
		}
	}
}