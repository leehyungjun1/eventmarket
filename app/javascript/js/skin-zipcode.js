function choice_zipcode(obj_tr)
{
	if(opener != null ) {
		var winobj = opener.document;
	}else{
		var winobj = parent.document;
	}

	obj_tr = $(obj_tr);
	var zip = obj_tr.find(".zipcode_number").text();
	var zipArr = zip.split('-');
	if(zip.length == 5){
		zipArr[0] = zip.substring(0,3);
		zipArr[1] = zip.substring(3,5);
	}
	
	zip = zip.replace("-", "");
	if(params.address){
		if(mtype=='order_multi' || zipcodeFlag=='order_multi'){
			choice_order_multi(obj_tr,zipArr,zip,winobj);
		}else if(mtype=='order_multi_view' || zipcodeFlag=='order_multi_view'){
			choice_order_multi_view(obj_tr,zipArr,zip,winobj);
		}else{
			choice_params_address(obj_tr,zipArr,zip,winobj);
		}
		try{parent.order_price_calculate();}catch(e){};
		try{opener.order_price_calculate();}catch(e){};
	}else{
		if(mtype=='order_info' || zipcodeFlag=='order_info'){
			choice_order_info(obj_tr,zipArr,zip,winobj);
		}else if(mtype=='order' || zipcodeFlag=='order'){
			choice_order(obj_tr,zipArr,zip,winobj);
		}else if(mtype=='morder' || zipcodeFlag=='morder'){
			choice_morder(obj_tr,zipArr,zip,winobj);
		}else if(mtype=='delivery' || zipcodeFlag=='delivery'){//3
			choice_delivery(obj_tr,zipArr,zip,winobj);
		}else if(mtype=='business' || zipcodeFlag=='business'){//4
			choice_business(obj_tr,zipArr,zip,winobj);
		}else if(mtype=='member' || zipcodeFlag=='member'){//5
			choice_member(obj_tr,zipArr,zip,winobj);
		}else if(mtype=='sender' || zipcodeFlag=='sender'){//6
			choice_sender(obj_tr,zipArr,zip,winobj);
		}else if(mtype=='co_' || zipcodeFlag=='co_'){//7
			choice_co(obj_tr,zipArr,zip,winobj);
		}else{//8
			choice_mtype(obj_tr,zipArr,zip,winobj);
		}
	}

	if(opener != null ) { // 새창으로 띄우는 경우
		if(opener.$('#' + zipcodeFlag + 'zipId').length) {
			opener.removeCenterLayer('#' + zipcodeFlag + 'zipId');
		} else if($("#" + zipcodeFlag + "BgId")) {
			opener.$("#" + zipcodeFlag + "BgId").remove();
			opener.$("#" + zipcodeFlag + "Id").remove();
		}

		self.close();
	}else{ // 레이어로 띄우는 경우
		if(parent.$('#' + zipcodeFlag + 'zipId').length) {
			parent.removeCenterLayer('#' + zipcodeFlag + 'zipId');
		} else if($("#" + zipcodeFlag + "BgId")) {
			parent.$("#" + zipcodeFlag + "BgId").remove();
			parent.$("#" + zipcodeFlag + "Id").remove();
		}
	}
	//self.close();
}

function choice_order_multi(obj_tr,zipArr,zip,winobj)
{
	$("input[name='multi_recipient_address_type[]']",$(".multiShippingItem[multiShippingItemNo=" + multiIdx + "]",winobj)).val( zipcodeType );
	if( eval("$('input[name=multi_recipient_address_type[]]',winobj).val()") ) {
		if(zipcodeType != 'oldzibun') {
			$("input[name='multi_recipient_address[]']",$(".multiShippingItem[multiShippingItemNo=" + multiIdx + "]"),winobj).val( obj_tr.find(".address").text() ).hide();
			$("input[name='multi_recipient_address_street[]']",$(".multiShippingItem[multiShippingItemNo=" + multiIdx + "]"),winobj).val( obj_tr.find(".address_street").text() ).show();
		} else {
			$("input[name='multi_recipient_address[]']",$(".multiShippingItem[multiShippingItemNo=" + multiIdx + "]"),winobj).val( obj_tr.find(".address").text() ).show();
			$("input[name='multi_recipient_address_street[]']",$(".multiShippingItem[multiShippingItemNo=" + multiIdx + "]"),winobj).val( obj_tr.find(".address_street").text() ).hide();
		}
	}else{
		$("input[name='multi_recipient_address[]']",$(".multiShippingItem[multiShippingItemNo=" + multiIdx + "]"),winobj).val( obj_tr.find(".address").text() );
	}
	if($("input[name='multi_recipient_zipcode[0][]']").length == 2){
		$("input[name='multi_recipient_zipcode[0][]']",$(".multiShippingItem[multiShippingItemNo=" + multiIdx + "]"),winobj).val(zipArr[0]);
		$("input[name='multi_recipient_zipcode[1][]']",$(".multiShippingItem[multiShippingItemNo=" + multiIdx + "]"),winobj).val(zipArr[1]);
	}else{
		$("input[name='multi_recipient_zipcode[0][]']",$(".multiShippingItem[multiShippingItemNo=" + multiIdx + "]"),winobj).val(zip);
	}
	$("input[name='multi_recipient_address_detail[]']",$(".multiShippingItem[multiShippingItemNo=" + multiIdx + "]"),winobj).focus();
}

function choice_order_multi_view(obj_tr,zipArr,zip,winobj){
	$("input[name='recipient_address_type']",$(".multiShippingItem").eq(),winobj).val( zipcodeType )

	if( eval("$('input[name=recipient_address_type]',winobj).val()") ) {
		if(zipcodeType != 'oldzibun') {
			$("input[name='recipient_address']",$(".multiShippingItem").eq(),winobj).val( obj_tr.find(".address").text() ).hide();
			$("input[name='recipient_address_street']",$(".multiShippingItem").eq(),winobj).val( obj_tr.find(".address_street").text() ).show();
		} else {
			$("input[name='recipient_address']",$(".multiShippingItem").eq(),winobj).val( obj_tr.find(".address").text() ).show();
			$("input[name='recipient_address_street']",$(".multiShippingItem").eq(),winobj).val( obj_tr.find(".address_street").text() ).hide();
		}
	}else{
		$("input[name='recipient_address']",$(".multiShippingItem").eq(),winobj).val( $(this).find(".address").text() );
	}
	if($("input[name='recipient_zipcode[]']",$(".multiShippingItem").eq(),winobj).length == 2){
		$("input[name='recipient_zipcode[]']",$(".multiShippingItem").eq(),winobj).eq(0).val(zipArr[0]);
		$("input[name='recipient_zipcode[]']",$(".multiShippingItem").eq(),winobj).eq(1).val(zipArr[1]);
	}else{
		$("input[name='recipient_zipcode[]']",$(".multiShippingItem").eq(),winobj).eq(0).val(zip);
	}
	$("input[name='recipient_address_detail']",$(".multiShippingItem").eq(),winobj).focus();
}

function choice_params_address(obj_tr,zipArr,zip,winobj)
{
	$("input[name='"+params.address+"_type']",winobj).val( zipcodeType );
	if( eval("$(\"input[name='"+params.address+"_type']\",winobj).val()") ) {
		if(zipcodeType != 'oldzibun') {
			$("input[name='"+params.address+"']",winobj).val( obj_tr.find(".address").text() ).hide();
			$("input[name='"+params.address_street+"']",winobj).val( obj_tr.find(".address_street").text() ).show();
		} else {
			$("input[name='"+params.address+"']",winobj).val( obj_tr.find(".address").text() ).show();
			$("input[name='"+params.address_street+"']",winobj).val( obj_tr.find(".address_street").text() ).hide();
		}
	}else{
		$("input[name='"+params.address+"']",winobj).val( obj_tr.find(".address").text() );
	}
	if($("input[name='"+params.zipcode+"']",winobj).length == 2){
		$("input[name='"+params.zipcode+"']",winobj).eq(0).val(zipArr[0]);
		$("input[name='"+params.zipcode+"']",winobj).eq(1).val(zipArr[1]);
	}else{
		$("input[name='"+params.new_zipcode+"']",winobj).eq(0).val(zip);
	}
	$("input[name='"+params.address_detail+"']",winobj).focus();
}

function choice_order_info(obj_tr,zipArr,zip,winobj)
{
	$("input[name='order_address_type']", winobj).val( zipcodeType );
	$("input[name='order_address']", winobj).val( obj_tr.find(".address").text() );
	$("input[name='order_address_street']", winobj).val( obj_tr.find(".address_street").text() );
	if($("input[name='order_zipcode[]']", winobj).length == 2){
		$("input[name='order_zipcode[]']", winobj).eq(0).val(zipArr[0]);
		$("input[name='order_zipcode[]']", winobj).eq(1).val(zipArr[1]);
	}else{
		$("input[name='order_new_zipcode']", winobj).eq(0).val(zip);
	}
	$("input[name='order_address_detail']", winobj).focus();
}

function choice_order(obj_tr,zipArr,zip,winobj)
{
	$("input[name='recipient_address_type']", winobj).val( zipcodeType );
	if( eval("$('input[name=recipient_address_type]', winobj).val()") ) {
		if(zipcodeType != 'oldzibun') {
			$("input[name='recipient_address']", winobj).val( obj_tr.find(".address").text() ).hide();
			$("input[name='recipient_address_street']", winobj).val( obj_tr.find(".address_street").text() ).show();
		} else {
			$("input[name='recipient_address']", winobj).val( obj_tr.find(".address").text() ).show();
			$("input[name='recipient_address_street']", winobj).val( obj_tr.find(".address_street").text() ).hide();
		}
	}else{
		$("input[name='recipient_address']", winobj).val( obj_tr.find(".address").text() );
	}
	if($("input[name='recipient_zipcode[]']", winobj).length == 2){
		$("input[name='recipient_zipcode[]']", winobj).eq(0).val(zipArr[0]);
		$("input[name='recipient_zipcode[]']", winobj).eq(1).val(zipArr[1]);
	}else{
		$("input[name='recipient_new_zipcode']", winobj).val(zip);
	}
	$("input[name='recipient_address_detail']", winobj).focus();
	try{parent.order_price_calculate();}catch(e){};
	try{opener.order_price_calculate();}catch(e){};
}
function choice_morder(obj_tr,zipArr,zip,winobj)
{
	$("input[name='recipient_input_address_type']", winobj).val( zipcodeType );
	if( eval("$('input[name=recipient_input_address_type]', winobj).val()") ) {
		if(zipcodeType != 'oldzibun') {
			$("input[name='recipient_input_address']", winobj).val( obj_tr.find(".address").text() ).hide();
			$("input[name='recipient_input_address_street']", winobj).val( obj_tr.find(".address_street").text() ).show();
		} else {
			$("input[name='recipient_input_address']", winobj).val( obj_tr.find(".address").text() ).show();
			$("input[name='recipient_input_address_street']", winobj).val( obj_tr.find(".address_street").text() ).hide();
		}
	}else{
		$("input[name='recipient_input_address']", winobj).val( obj_tr.find(".address").text() );
		if(typeof $("input[name='recipient_input_address_street']", winobj) != "undefind"){
			$("input[name='recipient_input_address_street']", winobj).val( obj_tr.find(".address_street").text());
		}
	}
	if($("input[name='recipient_input_zipcode[]']", winobj).length == 2){
		$("input[name='recipient_input_zipcode[]']", winobj).eq(0).val(zipArr[0]);
		$("input[name='recipient_input_zipcode[]']", winobj).eq(1).val(zipArr[1]);
	}else{
		$("input[name='recipient_input_new_zipcode']", winobj).eq(0).val(zip);
	}
	$("input[name='recipient_input_address_detail']", winobj).focus();
	try{parent.order_price_calculate();}catch(e){};
	try{opener.order_price_calculate();}catch(e){};
}
function choice_delivery(obj_tr,zipArr,zip,winobj)
{
	$("input[name='recipient_address_type']",winobj).val( zipcodeType );
	$("input[name='recipient_address']",winobj).val( obj_tr.find(".address").text() );
	$("input[name='recipient_address_street']",winobj).val( obj_tr.find(".address_street").text() );
	if($("input[name='recipient_zipcode[]']",winobj).length == 2){
		$("input[name='recipient_zipcode[]']",winobj).eq(0).val(zipArr[0]);
		$("input[name='recipient_zipcode[]']",winobj).eq(1).val(zipArr[1]);
	}else{
		$("input[name='recipient_new_zipcode']",winobj).val(zip);
	}
	$("input[name='recipient_address_detail']",winobj).focus();
}
function choice_business(obj_tr,zipArr,zip,winobj)
{
	$("input[name='baddress_type']",winobj).val( zipcodeType );
	if( eval("$('input[name=baddress_type]',winobj).val()") ) {
		if(zipcodeType != 'oldzibun') {
			$("input[name='baddress']",winobj).val( obj_tr.find(".address").text() ).hide();
			$("input[name='baddress_street']",winobj).val( obj_tr.find(".address_street").text() ).show();
		} else {
			$("input[name='baddress']",winobj).val( obj_tr.find(".address").text() ).show();
			$("input[name='baddress_street']",winobj).val( obj_tr.find(".address_street").text() ).hide();
		}
	}else{
		$("input[name='baddress']",winobj).val( obj_tr.find(".address").text() );
	}
	if($("input[name='bzipcode[]']",winobj).length == 2){
		$("input[name='bzipcode[]']",winobj).eq(0).val(zipArr[0]);
		$("input[name='bzipcode[]']",winobj).eq(1).val(zipArr[1]);
	}else{
		$("input[name='new_bzipcode']",winobj).eq(0).val(zip);
	}
	$("input[name='baddress_detail']", winobj).focus();
}
function choice_member(obj_tr,zipArr,zip,winobj)
{
	$("input[name='address_type']",winobj).val( zipcodeType );
	if( eval("$('input[name=address_type]',winobj).val()") ) {
		if(zipcodeType != 'oldzibun') {
			$("input[name='address']",winobj).val( obj_tr.find(".address").text() ).hide();
			$("input[name='address_street']",winobj).val( obj_tr.find(".address_street").text() ).show();
		} else {
			$("input[name='address']",winobj).val( obj_tr.find(".address").text() ).show();
			$("input[name='address_street']",winobj).val( obj_tr.find(".address_street").text() ).hide();
		}
	}else{
		$("input[name='address']",winobj).val( obj_tr.find(".address").text() );
	}
	if($("input[name='zipcode[]']",winobj).length == 2){
		$("input[name='zipcode[]']",winobj).eq(0).val(zipArr[0]);
		$("input[name='zipcode[]']",winobj).eq(1).val(zipArr[1]);
	}else{
		$("input[name='new_zipcode']",winobj).eq(0).val(zip);
	}

	$("input[name='address_detail']", winobj).focus();
}
function choice_sender(obj_tr,zipArr,zip,winobj)
{
	$("input[name='senderAddress_type']",winobj).val( zipcodeType );
	if( eval("$('input[name=senderAddress_type]',winobj).val()") ) {
		if(zipcodeType != 'oldzibun') {
			$("input[name='senderAddress']",winobj).val( obj_tr.find(".address").text() ).hide();
			$("input[name='senderAddress_street']",winobj).val( obj_tr.find(".address_street").text() ).show();
		} else {
			$("input[name='senderAddress']",winobj).val( obj_tr.find(".address").text() ).show();
			$("input[name='senderAddress_street']",winobj).val( obj_tr.find(".address_street").text() ).hide();
		}
	}else{
		$("input[name='senderAddress']",winobj).val( obj_tr.find(".address").text() );
	}
	if($("input[name='senderZipcode[]']",winobj).length == 2){
		$("input[name='senderZipcode[]']",winobj).eq(0).val(zipArr[0]);
		$("input[name='senderZipcode[]']",winobj).eq(1).val(zipArr[1]);
	}else{
		$("input[name='senderZipcode[]']",winobj).eq(0).val(zip);
	}

	$("input[name='senderAddressDetail']", winobj).focus();
}
function choice_co(obj_tr,zipArr,zip,winobj)
{
	$("input[name='co_address_type']",winobj).val( zipcodeType );
	if( eval("$('input[name=co_address_type]',winobj).val()") ) {
		if(zipcodeType != 'oldzibun') {
			$("input[name='co_address']",winobj).val( obj_tr.find(".address").text() ).hide();
			$("input[name='co_address_street']",winobj).val( obj_tr.find(".address_street").text() ).show();
		} else {
			$("input[name='co_address']",winobj).val( obj_tr.find(".address").text() ).show();
			$("input[name='co_address_street']",winobj).val( obj_tr.find(".address_street").text() ).hide();
		}
	}else{
		$("input[name='co_address']",winobj).val( obj_tr.find(".address").text() );
	}
	if($("input[name='co_zipcode[]']",winobj).length == 2){
		$("input[name='co_zipcode[]']",winobj).eq(0).val(zipArr[0]);
		$("input[name='co_zipcode[]']",winobj).eq(1).val(zipArr[1]);
	}else{
		$("input[name='co_new_zipcode']",winobj).eq(0).val(zip);
	}

	$("input[name='co_address_detail']", winobj).focus();
}
function choice_mtype(obj_tr,zipArr,zip,winobj)
{
	if(mtype)  {
		$("input[name='" + mtype + "address_type']",winobj).val( zipcodeType );
		if( eval("$(\"input[name='" + mtype + "address_type']\",winobj).val()") ) {
			if(zipcodeType != 'oldzibun') {
				$("input[name='" + mtype + "address']",winobj).val( obj_tr.find(".address").text() ).hide();
				$("input[name='" + mtype + "address_street']",winobj).val( obj_tr.find(".address_street").text() ).show();
			} else{
				$("input[name='" + mtype + "address']",winobj).val( obj_tr.find(".address").text() ).show();
				$("input[name='" + mtype + "address_street']",winobj).val( obj_tr.find(".address_street").text() ).hide();
			}
		}else{
			$("input[name='" + mtype + "address']",winobj).val( obj_tr.find(".address").text() );
		}
		if($("input[name='" + zipcodeFlag + "Flagzipcode[]']",winobj).length == 2){
			$("input[name='" + mtype + "zipcode[]']",winobj).eq(0).val(zipArr[0]);
			$("input[name='" + mtype + "zipcode[]']",winobj).eq(1).val(zipArr[1]);
		}else{
			$("input[name='" + mtype + "new_zipcode']",winobj).eq(1).val(zip);
		}
		$("input[name='" + mtype + "address_detail']", winobj).focus();
	} else {
		$("input[name='" + zipcodeFlag + "address_type']",winobj).val( zipcodeType );
		if( eval("$(\"input[name='" + zipcodeFlag + "address_type']\",winobj).val()") ) {
			if(zipcodeType != 'oldzibun') {
				$("input[name='" + zipcodeFlag + "address']",winobj).val( obj_tr.find(".address").text() ).hide();
				$("input[name='" + zipcodeFlag + "address_street']",winobj).val( obj_tr.find(".address_street").text() ).show();
			} else {
				$("input[name='" + zipcodeFlag + "address']",winobj).val( obj_tr.find(".address").text() ).show();
				$("input[name='" + zipcodeFlag + "address_street']",winobj).val( obj_tr.find(".address_street").text() ).hide();
			}
		}else{
			$("input[name='" + zipcodeFlag + "address']",winobj).val( obj_tr.find(".address").text() );
		}
		if($("input[name='" + zipcodeFlag + "zipcode[]']",winobj).length == 2){
			$("input[name='" + zipcodeFlag + "zipcode[]']",winobj).eq(0).val(zipArr[0]);
			$("input[name='" + zipcodeFlag + "zipcode[]']",winobj).eq(1).val(zipArr[1]);
		}else{
			$("input[name='" + zipcodeFlag + "new_zipcode']",winobj).eq(0).val(zip);
		}
		$("input[name='" + zipcodeFlag + "address_detail']", winobj).focus();
	}
}