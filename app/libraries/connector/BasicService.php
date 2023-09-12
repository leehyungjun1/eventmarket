<?php

Class BasicService extends ServiceBase
{

	protected $_ServiceName	= 'basic';

	public function __construct($params = array())
	{
		parent::__construct($params);
	}

	public function getServiceName(){ return $this->_ServiceName; }
	
	/* 상품고시 정보 불러오기 */
	public function getNotificationInfo($fmNotificationCode, $notificationDescJson, $market = '') {
		
		$this->_CI->load->helper('notification');
		
		$notificationDetail	= notificationDetail('forSearch');
		$notificationCode	= notificationCodeMatch($fmNotificationCode);
		$notificationInfo	= json_decode($notificationDescJson);
		
		$notifictionList	= array();
		
		foreach ((array)$notificationInfo as $key => $val) {
			$searchKey		= makeTextForSearch($key);
			$notiCode		= array_search($searchKey, $notificationDetail);
			preg_match('/^(D[0-9]+)/', $notiCode, $keyTemp);
			$notiKey		= $keyTemp[1];
			$notifictionList[$notiKey]	= $val;
		}

		if ($market != '') {
			
			if(stripos($market,"API") !== false){
				$rawNotiInfo		= $this->callOtherConnector("Notification/getNotificationInfo/{$notificationCode}/shoplinker");
			}else{
				$rawNotiInfo		= $this->callOtherConnector("Notification/getNotificationInfo/{$notificationCode}/{$market}");
			}
			
			$marketNotiInfo		= $rawNotiInfo['resultData'];
			$notificationCode	= $marketNotiInfo['notificationType']['key'];
			$marketNotiList		= array();
			
			foreach ((array)$marketNotiInfo['requiredList'] as $key => $val) {
				$keyExp	= explode('_', $key);
				$setKey	= $keyExp[0];
				$marketNotiList[$val]	= (isset($notifictionList[$setKey])) ? $notifictionList[$setKey] : '상세설명참조';
			}

			$notifictionList	= $marketNotiList;
		}

		$return['notificationCode']	= $notificationCode;
		$return['notifictionList']	= $notifictionList;
		
		return $return;
	}

	/* 오픈마켓 매칭을 위한 배송방법을 반환 */
	public function getDeliveryMethod($shipping_method) {

		switch ($shipping_method) {
			case 'direct':	//배송없음(직접수령)
				$deliveryMethod = 'M05';
				break;

			case 'quick':	//퀵서비스
				$deliveryMethod = 'M04';
				break;

			default:	//택배
				$deliveryMethod = 'M01';
		}

		return $deliveryMethod;
	}

	/* 오픈마켓 매칭을 위한 택배사 코드를 반환 */
	public function getDeliveryCompanyCode($delivery_company_code) {
		
		switch($delivery_company_code) {
			case 'code4' :	//대한통운
			case 'code0' :	//CJ대한통운
			case 'code30' :	//대운통운택배
				$companyCode	= 'D034';
				break;
			
			case 'code14' :	//동부익스프레스
			case 'code34' :	//KG로지스
			case 'code5' :	//kg로지스(구 동부택배)
				$companyCode	= 'D001';
				break;
			
			case 'code2' :	//KGB택배
				$companyCode	= 'D014';
				break;
			
			case 'code25' :	//합동택배
				$companyCode	= 'D035';
				break;

			case 'code3' :	//경동택배
				$companyCode	= 'D026';
				break;

			case 'code6' :	//로젠택배
				$companyCode	= 'D002';
				break;

			case 'code7' :	//우체국택배
				$companyCode	= 'D007';
				break;

			case 'code44' :	//우체국등기
				$companyCode	= 'D044';
				break;

			case 'code9' :	//한진택배
				$companyCode	= 'D011';
				break;

			case 'code10' :	//현대택배 - (구)롯데택배
				$companyCode	= 'D012';
				break;

			case 'code15' :	//천일택배
				$companyCode	= 'D027';
				break;
			
			case 'code17' :	//일양택배
				$companyCode	= 'D022';
				break;
			
			case 'code20' :	//건영택배
				$companyCode	= 'D037';
				break;
						
			case 'code31' :	//FEDEX
				$companyCode	= 'D054';
				break;

			case 'code33' :	//GTX로지스
			case 'code18' :	//이노지스
				$companyCode	= 'D033';
				break;
			
			case 'code1' :	//DHL코리아
				$companyCode	= 'D052';
				break;

			case 'code27' :	//DHL코리아
				$companyCode	= 'D051';
				break;
			
			case 'code12' :	//대신택배
				$companyCode	= 'D021';
				break;

			case 'code19' :	//편의점택배(CVS택배)
				$companyCode	= 'D041';
				break;
			case 'code51' : // 부릉
				$companyCode 	= 'D091';
				break;
			case 'code32' :	//고려택배
			case 'code35' :	//판토logistics
			case 'code36' : //한의사랑택배
			case 'code8' :	//하나로택배
			case 'code11' :	//동원택배
			case 'code13' :	//세덱스
			case 'code16' :	//사가와택배
			case 'code21' :	//옐로우캡
			case 'code26' :	//호남택배
			case 'code28' :	//HPL(한의사랑)
			case 'code29' :	//용마로지스
			default :
				$companyCode	= 'D099';
		}
		
		// 택배 자동화 시스템 이용시 택배사코드 그대로 쓰기 예외처리 @2020.02.03
		if(stripos($delivery_company_code,"auto") !== false){
			$companyCode = $delivery_company_code;
		}

		return $companyCode;
	}

	public function getAllMarkets($justMarkets = false, $onlyUsetAccount = true) {

		$supportMarkets	= $this->getSupportMarkets();
		$marketList		= array();
		$this->_CI->load->model('connectormodel');

		/*2017-12-06 샵링커 마켓 설정 추가*/
		$find			= $this->_CI->db->query("SELECT * FROM fm_market_account WHERE market LIKE 'API%' and delete_yn = 'N' and account_use = 'Y' group by market");
		$findRow		= $find->result();
		
		$this->_CI->load->model('connectormodel');
		$connectorModel		=& $this->_CI->connectormodel;
		foreach ($findRow as $val){
			$getParam['searchMarket'] = $val->market;
			$rtn = $connectorModel->getLinkageMarket($getParam);
			$supportMarkets[$val->market]['name'] = $rtn[0]['marketName'];
			$supportMarkets[$val->market]['productLink'] = '';
		}
		
		/*2017-12-06 샵링커 마켓 설정 추가*/
		
		foreach ($supportMarkets as $market => $val) {
			$marketList[$market]	= $val;

			if ($justMarkets !== true) {
				$marketList[$market]['sellerList']	= $this->getMarketSellers($market, $onlyUsetAccount);
				if (count($marketList[$market]['sellerList']) < 1)
					unset($marketList[$market]);
			}
		}
		return $marketList;

	}

	public function getMarketSellers($market, $onlyUsetAccount = true, $seller_id='') {

		$whereArr[]			= 'market = ?';
		$whereData[]		= $market;

		$whereArr[]			= 'delete_yn = ?';
		$whereData[]		= 'N';

		if ($onlyUsetAccount === true) {
			$whereArr[]		= 'account_use = ?';
			$whereData[]	= 'Y';
		}

		if ($seller_id) {
			$whereArr[]		= 'seller_id = ?';
			$whereData[]	= $seller_id;
		}
		
		$where			= implode(' AND ', $whereArr);

		$find			= $this->_CI->db->query("SELECT seller_id, market_other_info FROM fm_market_account WHERE {$where}", $whereData);
		$findRow		= $find->result();

		$sellerList		= [];
		foreach ($findRow as $val)
			$sellerList[]	= $val->seller_id;
		return $sellerList;
	}
	
	public function getShoplinkerMarkets($market) {
		$this->_CI->load->model('connectormodel');
		$connectorModel		=& $this->_CI->connectormodel;
		
		$whereArr[]			= "market LIKE 'API%'";
		
		$whereArr[]			= 'delete_yn = ?';
		$whereData[]		= 'N';
		
		$whereArr[]			= 'account_use = ?';
		$whereData[]		= 'Y';
		
		
		$where			= implode(' AND ', $whereArr);
		
		$find			= $this->_CI->db->query("SELECT market_auth_info FROM fm_market_account WHERE {$where}", $whereData);
		$findRow		= $find->result();
		
		$marketsList		= [];
		foreach ($findRow as $val){
			$authInfo = json_decode($val->market_auth_info,true);
			$params = array('mallCode'=>$authInfo['mallId']);
			$marketInfo = $connectorModel->getLinkageCompany($params);
			$marketsList[]	= $marketInfo[0]['mall_name'];
		}
		
		return $marketsList;
	}
	


	public function isConnectorUse()
	{
		if (strlen($this->_FmAccessKey) == 36)
			return true;
		else
			return false;
	}

}