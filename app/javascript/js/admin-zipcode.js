function choice_zipcode(obj_tr, idx)
{
	obj_tr = $(obj_tr);

	var zip = obj_tr.find(".zipcode").html();
	zip = zip.replace("-", "");

	if(zipcodeFlag == 'order_multi') {
		choice_order_multi(obj_tr, zip, idx);
	} else if(zipcodeFlag == 'windowLabel' || zipcodeFlag.substr(0,11) == 'windowLabel') {
		choice_winowLabel(obj_tr, zip, idx);
	} else if(zipcodeFlag.match('_')) {
		choice_params_address(obj_tr, zip);
	} else if(zipcodeFlag == 'zone' || zipcodeFlag == 'goodsflow') {
		choice_zone(obj_tr, zip);
	} else {
		choice_params_Address(obj_tr, zip, idx);
	}

	if(zipcodeFlag == 'recipient_') {
		try{opener.order_price_calculate();}catch(e){};
		try{order_price_calculate();}catch(e){};
	}

	closeDialog(zipcodeFlag + 'Id');
}

function choice_order_multi(obj_tr, zip, idx)
{
	$(":input[name='recipient_address_type'][idx='" + idx + "']").val(zipcodeType);
	$(":input[name='recipient_address'][idx='" + idx + "']").val( obj_tr.find(".address").text());
	$(":input[name='recipient_address_street'][idx='" + idx + "']").val( obj_tr.find(".address_street").text());
	$(":input[name='recipient_zipcode[]'][idx='" + idx + "']").eq(0).val(zip);
}

function choice_winowLabel(obj_tr, zip, idx)
{
	$(".windowLabelAddress_type" + idx).val( zipcodeType );
	$(".windowLabelAddress" + idx).val( obj_tr.find(".address").text() );
	$(".windowLabelAddress_street" + idx).val( obj_tr.find(".address_street").text() );
	$(".windowLabelZipcode1" + idx).val(zip);
}

function choice_params_address(obj_tr, zip, idx)
{
	$("input[name='" + zipcodeFlag + "address_type']").val( zipcodeType );
	$("input[name='" + zipcodeFlag + "address']").val( obj_tr.find(".address").text() );
	$("input[name='" + zipcodeFlag + "address_street']").val( obj_tr.find(".address_street").text() );
	$("input[name='" + zipcodeFlag + "zipcode'],:input[name='" + zipcodeFlag + "zipcode[]']").val(zip);
}

function choice_zone(obj_tr, zip)
{
	$(":input[name='" + zipcodeFlag + "Address_type']").val( zipcodeType );
	$(":input[name='" + zipcodeFlag + "Address']").val( obj_tr.find(".address").text() );
	$(":input[name='" + zipcodeFlag + "Address_street']").val( obj_tr.find(".address_street").text() );
	$(":input[name='" + zipcodeFlag + "Zipcode[]']").eq(0).val(zip);
		
	if(zipcodeType == 'street') {
		$(":input[name='" + zipcodeFlag + "Address_street']").show();
		$(":input[name='" + zipcodeFlag + "Address']").hide();
	} else {
		$(":input[name='" + zipcodeFlag + "Address_street']").hide();
		$(":input[name='" + zipcodeFlag + "Address']").show();
	}
}

function choice_params_Address(obj_tr, zip)
{
	$(":input[name='" + zipcodeFlag + "Address_type']").val( zipcodeType );

	$(":input[name='" + zipcodeFlag + "Address']").val( obj_tr.find(".address").text() );
	$(":input[name='" + zipcodeFlag + "Address_street']").val( obj_tr.find(".address_street").text() );

	$(":input[name='" + zipcodeFlag + "Zipcode[]']").eq(0).val(zip);
}