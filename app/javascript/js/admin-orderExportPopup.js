var bundle_info	= {};

$('.check_shipping_group_seq').ready(function(){
	$('.check_shipping_group_seq[linkage_id="connector"]').click(function(){
		var fmOrderSeq	 = $(this).attr('order_seq');
		$('.check_shipping_group_seq[order_seq="' + fmOrderSeq + '"]').attr('checked', $(this).is(':checked'));
		check_bg();
	})
});


Number.prototype.number_format = function(){
	if(this == 0) return 0;
	var reg	= /(^[+-]?\d+)(\d{3})/;
	var n	= (this + '');
	while (reg.test(n)) n = n.replace(reg, '$1' + ',' + '$2');
	return n;
};
  

function Object_compare()
{
	this.base_data			= {};
	this.compare_key_list	= [];

	this.do_compare	= function(target){
		var result	= {
			equal	: [],
			unequal	: []
		};

		for(count = this.compare_key_list.length, i=0; i < count; i++){
			if(this.base_data[this.compare_key_list[i]] == target[this.compare_key_list[i]]){
				result.equal.push(this.compare_key_list[i]);
			}else{
				result.unequal.push(this.compare_key_list[i]);
			}
		}

		return result;
	}

	this.set_base_object		= function(base_data, compare_key_list){
		this.base_data			= base_data;
		this.compare_key_list	= compare_key_list;
	}
}

function check_stock_policy_step(mode){
	if(mode != 'bundle'){
	var obj = $("select#export_step");
	var sobj = $("select#export_stockable");
	}else{
		var obj = $("select#bundle_export_step");
		var sobj = $("select#bundle_export_stockable");
	}

	if( obj.val() == 45) {
		obj.parent().find("span").addClass("hide");
		sobj.find("option[value='unlimit']").attr("selected",true);
	}
	if( obj.val() == 55) {
		obj.parent().find("span").removeClass("hide");
	}
}

function matching_goods_option(goods_seq,item_option_seq,item_suboption_seq){
	if( goods_seq ){
		var url = "../order/goods_option_matching?goods_seq="+goods_seq+"&item_option_seq="+item_option_seq+"&item_suboption_seq="+item_suboption_seq;
		$.get(url, function(data) {
			$("div#goods_matching_dialog").html(data);
		});
		openDialog("미매칭 옵션 매칭", "goods_matching_dialog", {"width":300,"height":200});
	}
}

function matching_goods(goods_seq,item_seq){
	var url = "../order/goods_matching?goods_seq="+goods_seq+"&item_seq="+item_seq;
	$.get(url, function(data) {
		$("div#goods_matching_dialog").html(data);
	});
	openDialog("미매칭 상품 매칭", "goods_matching_dialog", {"width":300,"height":200});
}

function batch_export(mode)
{
	if(mode == 'bundle'){
		$("form#bundle_goods_export input[name='bundle_check_mode']").val('');
		$("form#bundle_goods_export").submit();
		loadingStart();
		$("form#bundle_goods_export input[name='bundle_check_mode']").val('check');		
	}else{
	$("form#goods_export input[name='check_mode']").val('');
	$("form#goods_export").submit();
	loadingStart();
	$("form#goods_export input[name='check_mode']").val('check');
}
}

function check_deliveryCompany(shipping_seq,mode)
{
	if(mode == 'bundle')	var obj = $("select[name='bundle_delivery_company["+shipping_seq+"]']");
	else					var obj = $("select[name='delivery_company["+shipping_seq+"]']");

	var obj = $("select[name='delivery_company["+shipping_seq+"]']");
	var obj_option = obj.find("option:selected");

	if( obj_option.val() && obj_option.val().substring(0,5) == 'auto_'){
		obj.next().attr("readonly",true);
		obj.next().css("background-color","#efefef;");
		obj.next().css("color","#999999");
		obj.css("background-color","yellow");
	}else{
		obj.next().attr("readonly",false);
		obj.next().css("background-color","#ffffff;");
		obj.next().css("color","#000000");
		obj.css("background-color","#ffffff");
	}
}

// 택배업무 자동화
function invoice_manual_button()
{
	// $("span#invoice_manual_button").live("click",function(){
	var title = '택배 업무 자동화 서비스 사용방법';
	openDialog(title, "invoice_manual_dialog", {"width":"700"});
}

// 굿스플로 발송시 설정 :: 2015-07-06 lwh
function goodsflow_set(flag){
	if(flag == true){
		$("select#export_step").val('45');
		$("#goodsflow_desc").show();
	}else{
		$("select#export_step").val('55');
		$("#goodsflow_desc").hide();
	}
	check_stock_policy_step();
}

function go_search_export(bundle_mode)
{
	var provider_seq = $("input[name='provider_seq']").val();
	var provider_seq_consignment = $("select[name='provider_seq_consignment'] option:selected").val();
	var url = "../order/order_export_popup?";

	if(bundle_mode == 'bundle'){
		url += "&seq="+$('input[name="seq"]').val();
		$('input[name="step[40]"]').attr("checked",true);
		$('input[name="step[50]"]').attr("checked",true);
	}

	// 배송책임 검색
	if(provider_seq){
		url += "&provider_seq="+provider_seq;
	}

	// 배송방법 검색
	var shipping_method = '';
	$("input[name='shipmethod[]']").each(function(){
		if($(this).is(':checked'))	url += "&shipping_method["+$(this).val()+"]="+$(this).val();
	});

	// 검색날짜타입 검색
	var date_field = $("select[name='date_field'] option:selected").val();
	if( date_field ){
		url += "&date_field="+date_field;
	}

	// 시작날짜 검색
	var start_search_date = $("input[name='regist_date[]']:eq(0)").val();
	if( start_search_date ){
		url += "&start_search_date="+start_search_date;
	}

	// 종료날짜 검색
	var end_search_date = $("input[name='regist_date[]']:eq(1)").val();
	if( start_search_date ){
		url += "&end_search_date="+end_search_date;
	}

	// 주문상태 검색
	$("input.step[type='checkbox']:checked").each(function(){
		url += "&step["+$(this).val()+"]="+$(this).val();
	});	

	// 검색어 설명 문구
	var search_type = $("input[name='search_type']").val();
	if( search_type ){
		url += "&search_type="+search_type;
	}

	// 네이버 페이 검색
	var search_npay_order = $("input[name='search_npay_order']:checked").val();
	if( search_npay_order == 'y' ){
		url += "&search_npay_order="+search_npay_order;
	}

	// 톡체크아웃 검색
	var search_talkbuy_order = $("input[name='search_talkbuy_order']:checked").val();
	if( search_talkbuy_order == 'y'){
		url += "&search_talkbuy_order="+search_talkbuy_order;
	}

	// 키워드 검색
	var search_keyword = $("input[name='keyword']").val();
	if( search_keyword ){
		url += "&keyword="+search_keyword;
	}

	location.href=url;
}

function set_default_stock_check()
{
	var title = '재고에 따른 \'출고완료\'처리 설정';
	openDialog(title, "default_stock_check_dialog", {"width":"900"});
}

function check_complete(shipping_seq)
{
	var str_class_name = 'barcode_ea_'+shipping_seq;

	var barcode_ea_obj = $("input."+str_class_name);
	var comp = true;
	barcode_ea_obj.each(function(){

		if($(this).prev().html()!='완료'){
			comp = false;
		}
	});

	return comp;
}

function package_check_barcode(shipping_seq,upperstr,barcode_obj){
	var package_str_check_ea = 'package_barcode_ea['+shipping_seq+']['+upperstr+']';
	var check_ea_objs = $("input[name='"+package_str_check_ea+"']");
	check_ea_objs.each(function(){
		var check_ea_obj = $(this);
		var export_ea_obj = check_ea_obj.closest("td").prev().find("span.package_ea");
		var check_ea = num(check_ea_obj.val());
		var export_ea = num(export_ea_obj.html());
		var ago_msg	 = check_ea_obj.prev().html();

		if( export_ea == 0){
			check_ea_obj.prev().html("-");
		}else if( check_ea_obj.val() < export_ea  ){

			check_ea = check_ea + 1;

			if( check_ea >= export_ea){
				check_ea_obj.prev().html("완료");
				check_ea = export_ea;
				check_ea_obj.removeClass('barcode_ready');
				check_ea_obj.addClass('barcode_complete');
			}else if(ago_msg!='대기'){
				check_ea_obj.prev().html("대기");
				check_ea_obj.removeClass('barcode_complete');
				check_ea_obj.addClass('barcode_ready');
			}

			// 체크완료
			if( check_complete(shipping_seq) ){
				var tabindex = barcode_obj.attr('tabindex') + 1;
				$('[tabindex=' + tabindex + ']').focus();
			}

			check_ea_obj.val(check_ea);

			return false;
		}
	});

	return true;
}

function check_barcode(obj,event,mode){
	var keyCode = event.which||event.keyCode;

	if(keyCode=="13"){
		var barcode_obj = $(obj);
		var upperstr = barcode_obj.val();
		var upperstr = upperstr.toUpperCase();

		if(mode == 'bundle'){
			var str_check_ea = 'barcode_ea["bundle"]['+upperstr+']';
		}else{
			var str_shipping_seq = barcode_obj.attr('name');
			str_shipping_seq = str_shipping_seq.replace("barcode[", "");
			var shipping_seq = str_shipping_seq.replace("]","");
			var str_check_ea = 'barcode_ea['+shipping_seq+']['+upperstr+']';
		}
		var check_ea_objs = $("input[name='"+str_check_ea+"']");

		barcode_obj.val('');

		if( !package_check_barcode(shipping_seq,upperstr, barcode_obj) ) return false;

		check_ea_objs.each(function(){
			var check_ea_obj = $(this);
			var export_ea_obj = check_ea_obj.closest("td").prev().find("input.export_ea");
			var check_ea = num(check_ea_obj.val());
			var export_ea = num(export_ea_obj.val());
			var ago_msg	 = check_ea_obj.prev().html();

			if( export_ea == 0){
				check_ea_obj.prev().html("-");
			}else if( check_ea_obj.val() < export_ea  ){

				check_ea = check_ea + 1;

				if( check_ea >= export_ea){
					check_ea_obj.prev().html("완료");
					check_ea = export_ea;
					check_ea_obj.removeClass('barcode_ready');
					check_ea_obj.addClass('barcode_complete');
				}else if(ago_msg!='대기'){
					check_ea_obj.prev().html("대기");
					check_ea_obj.removeClass('barcode_complete');
					check_ea_obj.addClass('barcode_ready');
				}

				// 체크완료
				if( check_complete(shipping_seq) ){
					var tabindex = barcode_obj.attr('tabindex') + 1;
					$('[tabindex=' + tabindex + ']').focus();
				}

				check_ea_obj.val(check_ea);

				return false;
			}



			// 체크완료
			if( check_complete(shipping_seq) ){
				var tabindex = barcode_obj.attr('tabindex') + 1;
				$('[tabindex=' + tabindex + ']').focus();
			}
		});

	}
}

function check_barcode_ea(obj){

		var check_ea_obj = $(obj);
		var export_ea_obj = check_ea_obj.closest("td").prev().find("input.export_ea");
		var check_ea = num(check_ea_obj.val());
		var export_ea = num(export_ea_obj.val());
		var ago_msg	 = check_ea_obj.prev().html();

		if( export_ea == 0){
			check_ea_obj.prev().html("-");
			check_ea_obj.hide();
			return false;
		}

		if( check_ea >= export_ea){
			check_ea_obj.show();
			check_ea_obj.prev().html("완료");
			check_ea = export_ea;
			check_ea_obj.removeClass('barcode_ready');
			check_ea_obj.addClass('barcode_complete');
		}else if(ago_msg!='대기'){
			check_ea_obj.show();
			check_ea_obj.prev().html("대기");
			check_ea_obj.removeClass('barcode_complete');
			check_ea_obj.addClass('barcode_ready');
		}

		check_ea_obj.val(check_ea);

}

function reset_barcode_ea(obj)
{
	var export_ea_obj = $(obj);
	var check_ea_obj = export_ea_obj.closest("td").next().find("input");
	var export_ea = num(export_ea_obj.val());
	var check_ea = num(check_ea_obj.val());

	check_ea_obj.val(0);
	check_barcode_ea(check_ea_obj);
}

function sync_reset_barcode_ea(obj)
{
	var export_ea_obj = $(obj);
	var check_ea_obj = export_ea_obj.closest("td").next().find("input");
	alert(check_ea_obj);
	var export_ea = num(export_ea_obj.val());
	var check_ea = num(check_ea_obj.val());
	check_barcode_ea(check_ea_obj);
}

function export_check(mode)
{
	if(mode != 'bundle'){
	loadingStart();
	$("input[name='check_mode']").val('check');
	document.order_export.submit();
	}else{
		var check_bundle	= export_bundle_delivery('provider_bundle');
		if(check_bundle == true){
			loadingStart();
			$("input[name='bundle_check_mode']").val('check');
			document.bundle_order_export.submit();
		}
	}
}

function export_submit(mode)
{
	if(mode != 'bundle'){
	loadingStart();
	$("input[name='check_mode']").val('');
	document.order_export.submit();
	}else{
		var check_bundle	= export_bundle_delivery('provider_bundle');
		if(check_bundle == true){
			loadingStart();
			$("input[name='bundle_check_mode']").val('');
			document.bundle_order_export.submit();
		}
	}
}

function export_each_popup(each_shipping_seq,each_item_option_seq,each_shipping_method)
{
	// 실시간 변경된 shipping_method로 변경 처리 :: 2016-10-06 lwh
	each_shipping_method = $("input[name='export_shipping_method["+each_shipping_seq+"]']").val();

	var sel_shipping = $(".shipping_group_"+each_shipping_seq).find("#delivery_company_"+each_shipping_seq+' option:selected');
	if(sel_shipping.val() == gf_deliveryCode && each_shipping_method == 'delivery'){
		goodsflow_set(true);
	}else{
		goodsflow_set(false);
	}

	$("input[name='each_shipping_seq']").val(each_shipping_seq);
	$("input[name='each_item_option_seq']").val(each_item_option_seq);
	$("input[name='each_shipping_method']").val(each_shipping_method);

	var top = ( $(window).scrollTop() + ($(window).height() - $("#export_layer").height()) / 2 );
	$("#export_layer").css("top",top);
	$("#export_layer").show();
	$(".ui-widget-overlay").show();
}

function export_popup()
{
	if( ! get_checked_input() ){
		alert(chk_export_msg);
		return false;
	}

	var flag = false;
	$("select.deliveryCompany").each(function(i){
		// 택배일경우만 굿스플로를 체크하도록 변경
		if($(this).val() == gf_deliveryCode && $(".export_shipping_method").eq(i).val() == 'delivery'){
			flag = true;
		}
	});
	goodsflow_set(flag);

	$("input[name='each_shipping_seq']").val('');
	$("input[name='each_item_option_seq']").val('');
	$("input[name='each_shipping_method']").val('');

	var top = ( $(window).scrollTop() + ($(window).height() - $("#export_layer").height()) / 2 );
	$("#export_layer").css("top",top);
	$("#export_layer").show();
	$("#export_layer_overlay").show();
}

function close_export_popup()
{
	$("#export_layer").hide();
	$(".ui-widget-overlay").hide();
}


function export_bundle_delivery(mode)
{

	var order_seq_list		= [];
	var sel_bundle_seq_list	= [];
	var hasConnecotrOrder	= false;
	var check_list			= (mode == 'provider_bundle') ? 'check_bundle_seq' : 'check_shipping_group_seq:checked=true';


	$('.' + check_list).each(function(){

		if ($(this).attr('linkage_id') == 'connector') {
			hasConnecotrOrder	= true;
			return;
		}

		sel_bundle_seq_list.push(this.value);
		order_seq		= $(this).attr('order_seq');
		if(order_seq_list.indexOf(order_seq) < 0)	order_seq_list.push(order_seq);
	});

	if(hasConnecotrOrder === true) {
		alert('오픈마켓 주문서가 포함되어 있습니다.');
		return;
	}


	if(mode != 'provider_bundle'){

		//reset bundle data
		bundle_info						= {};
		bundle_info.done_provider_list	= [];

		if(order_seq_list.length < 2){
			alert('합포장 출고처리는 다른 주문을 2개 이상 선택해야 가능합니다.');
			return;
		}

		var params			= {};
		params.step			= now_get_step;
		params.seq			= order_seq_list.join('|');
		params.view_mode	= 'get_bundle_tmp_list';

		$.get('../order/order_export_popup', params, function(response){
			bundle_info.for_bundle_delivery	= response;
			compared_list					= do_order_compare(bundle_info.for_bundle_delivery, sel_bundle_seq_list)
			if(compared_list != false){
				bundle_info.compared_list	= compared_list;
				make_bundle_list();
			}
		},'json');

	}else{
		return do_order_compare(bundle_info.for_bundle_delivery, sel_bundle_seq_list);
	}


	function do_order_compare(for_bundle_delivery, sel_bundle_seq_list){

		var flag			= false;
		var	base_order		= {};
		var base_result		= {};
		
		var order_compare	= new Object_compare();
		//var compare_keys	= ['member_seq','recipient_user_name','recipient_cellphone_chk','recipient_address_type','recipient_address','recipient_address_street','recipient_address_detail'];
		var compare_keys	= ['member_seq','recipient_user_name','recipient_cellphone_chk','recipient_address','recipient_address_street','recipient_address_detail'];
		var compared_list	= {};
		var unequal_list	= {};
		var base_order		= {};

		var order_liset		= [];
		
		for(bundle_seq in for_bundle_delivery){
			order_info		= for_bundle_delivery[bundle_seq];

			if(sel_bundle_seq_list.indexOf(bundle_seq) < 0) continue;

			if(!compared_list.hasOwnProperty(order_info.order_seq))		compared_list[order_info.order_seq]	= {};
			compared_list[order_info.order_seq][order_info.bundle_seq]	= {};
			compared_list[order_info.order_seq][order_info.bundle_seq]	= order_info;

			if(order_info.shipping_method.match(/coupon/g))	unequal_list['coupon']	= 'unequal';
			
			// 첫번째 row 기준값
			if(Object.keys(order_compare.base_data).length < 1){
				base_order		= for_bundle_delivery[bundle_seq];
				order_compare.set_base_object(order_info, compare_keys);
			}else{
				result	= order_compare.do_compare(order_info);

				if(result.unequal.length > 0){
					
					if(result.unequal.indexOf("member_seq") > -1)						unequal_list['member']				= 'unequal';
					if(result.unequal.indexOf("recipient_user_name") > -1)				unequal_list['recipient_name']		= 'unequal';
					if(result.unequal.indexOf("recipient_cellphone_chk") > -1)			unequal_list['recipient_cellphone']	= 'unequal';

					if(result.unequal.indexOf("recipient_address_detail") > -1)			unequal_list['address']				= 'unequal';
					else if(result.unequal.indexOf("recipient_address_type") > -1)		unequal_list['address']				= 'unequal';
					else if(order_info.recipient_address_type == 'street' 
							&& result.unequal.indexOf("recipient_address_street") > -1) unequal_list['address']				= 'unequal';
					else if(order_info.recipient_address_type != 'street' 
							&& result.unequal.indexOf("recipient_address") > -1)		unequal_list['address']				= 'unequal';
				}
			}
		}
		
		if(Object.keys(compared_list).length < 2){
			if(mode != 'provider_bundle')	alert('합포장 출고처리는 다른 주문을 2개 이상 선택해야 가능합니다.');
			else							alert('[출고수량이 있는 합포장 대상 주문]이 2개 이상이어야 합니다.');
			return false;
		}

		//주문별 출고 수량 확인
		if(mode == 'provider_bundle'){
			var possible_orders	= [];
			$('#bundle_goods_export').find('.export_ea').each(function(){
				var request_info	= this.name.match(/request_ea\[(\d+)\]/);
				var order_seq		= bundle_info.bundle_seq_list[request_info[1]];


				if(this.value > 0 && possible_orders.indexOf(order_seq) < 0){
					possible_orders.push(order_seq);
				}
			});

			if(possible_orders.length < 2){
				alert('[주문별 출고수량이 1이상인 주문]이 2개 이상이어야 합니다.');
				return false;
			}

			return true;
		}


		
		if(unequal_list['coupon'] == 'unequal'){
			alert('티켓상품이 포함되어 있습니다.');
			return false;
		}


		if(Object.keys(unequal_list).length > 0){
			$('#bundle_list > tbody > tr').remove();
			
			for(order_seq in compared_list){
				for(bundle_seq in compared_list[order_seq]){
					_data		= compared_list[order_seq][bundle_seq];
					result_td	= '';
					result_td	+= '';
					
					address		= (_data.recipient_address_type == 'street') ? _data.recipient_address_street : _data.recipient_address;
					address		+= _data.recipient_address_detail;

					create_row	= '<tr><td class="its-td">' + _data.order_seq + '</td>';
					create_row	+= '<td class="its-td">' + _data.order_user_name + '</td>';
					create_row	+= '<td class="its-td">' + address + '</td>';
					create_row	+= '<td class="its-td">' + _data.recipient_user_name + '</td>';
					create_row	+= '<td class="its-td">' + _data.recipient_cellphone + '</td></tr>';
					
					$('#bundle_list > tbody').append(create_row);
					break;
				}
			}


			var unequal		= '<span style="color:red;font-weight:bold;">상이</span>';
			var equal		= '<span>동일</span>';

			var member_result		= (unequal_list['member'] == 'unequal') ? unequal : equal;
			var address_result		= (unequal_list['address'] == 'unequal') ? unequal : equal;
			var recipient_name		= (unequal_list['recipient_name'] == 'unequal') ? unequal : equal;
			var recipient_cellphone	= (unequal_list['recipient_cellphone'] == 'unequal') ? unequal : equal;
			
			create_row	= '<tr><td class="its-td center" style="background-color:#f90;padding-left:0">조건결과</th></td>';
			create_row	+= '<td class="its-td center" style="background-color:#f90;padding-left:0">' + member_result + '</th>';
			create_row	+= '<td class="its-td center" style="background-color:#f90;padding-left:0">' + address_result + '</th>';
			create_row	+= '<td class="its-td center" style="background-color:#f90;padding-left:0">' + recipient_name + '</th>';
			create_row	+= '<td class="its-td center" style="background-color:#f90;padding-left:0">' + recipient_cellphone + '</th>';

			$('#bundle_list > tbody').prepend(create_row);

			openDialog("합포장 조건 결과", "not_to_be_bundle", {"width":"800","height":"350"});
			return false;
		}

	
		
		return compared_list;
	}

}


function make_bundle_list(){
	var params		= {};
	
	compared_list	= bundle_info.compared_list;
	params.step		= now_get_step;
	params.seq		= Object.keys(compared_list).join('|');
	params.view_mode= 'get_bundle_list';


	bundle_info.done_provider_list		= [];

	$.get('../order/order_export_popup', params, function(response){
		
		bundle_info.shipping_list		= response.shipping_list;
		bundle_info.provider_list		= response.provider_list;
		bundle_info.international		= response.international_shipping;
		bundle_info.bundle_seq_list		= {};

		var order_cnt		= 0;
		var npay_order_cnt	= 0;

		for(order_seq in response.shipping_list){
			for(bundle_seq in response.shipping_list[order_seq]){
				if(!bundle_info.bundle_seq_list.hasOwnProperty(bundle_seq)){
					bundle_info.bundle_seq_list[bundle_seq]		= order_seq;
				}

				order_cnt++;
				if(response.shipping_list[order_seq][bundle_seq].npay_order_id){
					npay_order_cnt++;
				}
			}
		}

		if(npay_order_cnt > 0){
			openDialogAlert("<span class='fx12'>Npay주문은 합포장 출고 처리 불가 합니다.</sapn>",400,150);
			return false;
		}

		base_order_info_tmp			= compared_list[Object.keys(compared_list)[0]];
		base_order_info				= base_order_info_tmp[Object.keys(base_order_info_tmp)[0]];
		base_order_info_tmp			= {};

		//묶음배송 배송지 / 수령인 정보
		if(base_order_info.member_seq){
			var member	= '<a href="../member/detail?member_seq=' + base_order_info.member_seq + '" target="_blank"> <span class="member">';
			member		+= base_order_info.order_user_name + '</span></a>';
		}else{
			var member	= '<span class="nomember"> ' + base_order_info.order_user_name + '</span>';
		}
		
		if(base_order_info.order_user_name != base_order_info.recipient_user_name){
			member		+= ' <img src="/admin/skin/default/images/common/order_arrow.png" / ><span class="nomember">' + base_order_info.recipient_user_name + '</span>';
		}


		var address		= '';
		if(base_order_info.shipping_method.match(/international/g)){
			address		+= base_order_info.international_address;
			address		+= (base_order_info.international_town_city) ? ' ' + base_order_info.international_town_city : '';
			address		+= (base_order_info.international_county) ? ' ' + base_order_info.international_county : '';
			address		+= (base_order_info.international_country) ? ' ' + base_order_info.international_country : '';
			address		+= (base_order_info.international_postcode) ? ' ' + base_order_info.international_postcode : '';
		}else{
			address		+= base_order_info.recipient_zipcode + ' ';
			address		+= (base_order_info.recipient_address_type == 'street') ? base_order_info.recipient_address_street : base_order_info.recipient_address;
			address		+= ' ' + base_order_info.recipient_address_detail;
		}
		
		
		if(base_order_info.recipient_phone){
			var phone		= '<span class="tel" style="font-size:11px;">' + base_order_info.recipient_phone + '</span>';
			phone			+='<span class="separator">|</span><span class="tel">' + base_order_info.recipient_cellphone + '</span>';
		}else{
			var phone		= '<span class="tel">' + base_order_info.recipient_cellphone + '</span>';
		}

		phone				+= (base_order_info.memo) ? '<span class="separator">|</span><span class="memo">' + base_order_info.memo + '</span>' : '';
		

		$('.bundle_member_info').html(member);
		$('.bundle_addr').html(address);
		$('.bundle_phone').html(phone);
		
		bundle_list_maker(base_order_info.provider_seq);
		
		openDialog("합포장 출고 처리", "to_be_bundle", {"width":"1200","height":"550","close" : close_bundle_lay});

		function close_bundle_lay(){
			
			
			if(bundle_info.done_provider_list.length > 0)	location.reload();

			//reset bundle data
			bundle_info		= {};
		}
	}, 'json')
}


function bundle_list_maker(provider_seq){

	bundle_info.selected	= provider_seq;

	var shipping_list		= bundle_info.shipping_list;
	var compared_list		= bundle_info.compared_list;
	var provider_list		= bundle_info.provider_list;
	var bundle_seq_list		= {};
	var has_order			= $('<tbody/>');
	var no_has_order		= '';
	
	$('.bundle_shipping_group > tbody').remove();
	$('#bundle_export_form').html('');
	
	var scm_wh	= $('select[name="scm_wh"]').val();
	if(scm_wh > 0) {
		has_order.append("<input type='hidden' name='scm_wh' value='" + scm_wh + "'>");
	}

	var provider_name	= provider_list[provider_seq]['provider_name'];

	var	template_tds	= '<tr><td class="orderSeq info center"></td>';
	template_tds		+= '<td class="optInfo info"></td>';
	template_tds		+= '<td class="stockCnt info" align="center"></td>';
	template_tds		+= '<td class="orderCnt info" align="center"></td>';
	template_tds		+= '<td class="cancleCnt info" align="center"></td>';
	template_tds		+= '<td class="sentCnt info" align="center"></td>';
	template_tds		+= '<td class="leftCnt info" align="center"></td>';
	template_tds		+= '<td class="null" align="center">→</td>';
	template_tds		+= '<td class="requestInfo info" align="center"></td>';
	template_tds		+= '<td class="itemCheckInfo info" align="center"></td></tr>';

	
	var bundle_cnt		= 0;
	var opt_cnt			= 0;
	var export_method	= {};
	var couriers_list	= {};
	var target_provider	= {};

	for(order_seq in compared_list){
		now_tds			= '';
		opt_cnt			= 0;
		for(bundle_seq in compared_list[order_seq]){
			_order_info		= compared_list[order_seq][bundle_seq];

			//판매처 리스트 생성
			if(bundle_info['done_provider_list'].indexOf(_order_info.provider_seq) == -1 && !target_provider.hasOwnProperty(_order_info.provider_seq)){
				target_provider[_order_info.provider_seq]	= provider_list[_order_info.provider_seq]['provider_name'];
			}

			//묶음배송 form list 생성
			target_options	= [];
			//선택한 판매처의 주문만 보여줌
			if(_order_info.provider_seq !=  provider_seq) continue;

			opt_cnt++;
			
			_shipping_info	= shipping_list[order_seq][bundle_seq];
			for(option_seq in _shipping_info['options']){
				_option_info	= shipping_list[order_seq][bundle_seq]['options'][option_seq];
				_item_info		= shipping_list[order_seq][bundle_seq]['items'][_option_info.item_seq];
				now_option		= {};

				
				//배송방법
				if(_shipping_info.arr_shipping_method.hasOwnProperty('shipping_method')){
					for(delv_code in _shipping_info.arr_shipping_method.shipping_method){
						if(!export_method.hasOwnProperty(delv_code)){
							export_method[delv_code]		= _shipping_info.arr_shipping_method.shipping_method[delv_code];
						}
					}
				}else{
					if(_shipping_info.arr_shipping_method[0]){
						for(del_i = 0, del_r_cnt = _shipping_info.arr_shipping_method[0].length; del_i < del_r_cnt; del_i++){
							delv_code	= _shipping_info.arr_shipping_method[0][del_i]['code'];
							if(!export_method.hasOwnProperty(delv_code)){
								export_method[delv_code]		= _shipping_info.arr_shipping_method[0][del_i]['method'];
							}
						}
					}
				}

				//배송사리스트
				for(cou_code in _shipping_info.couriers){
					if(!couriers_list.hasOwnProperty(cou_code)){
						couriers_list[cou_code]		= _shipping_info.couriers[cou_code]['company'];
					}
				}
				

				now_option.option_type			= 'option';
				now_option.item_option_seq		= option_seq;
				now_option.order_seq			= order_seq;
				now_option.npay_order_id		= _item_info.npay_order_id;
				now_option.goods_seq			= _item_info.goods_data.goods_seq;
				now_option.goods_type			= _item_info.goods_type;
				now_option.goods_name			= _item_info.goods_name;
				now_option.image				= _item_info.image;
				now_option.cancel_type			= _item_info.goods_data.cancel_type;
				now_option.gift_title			= _item_info.gift_title;
				now_option.individual_export	= _item_info.individual_export;
				now_option.item_seq				= _item_info.item_seq
				
				now_option.goods_code			= _option_info.goods_code;
				now_option.subinputs			= _option_info.subinputs;
				now_option.bar_goods_code		= _option_info.bar_goods_code;
				now_option.package_yn			= _option_info.package_yn;

				now_option.stock				= _option_info.stock;	//미매칭시 문자열
				now_option.ea					= parseInt(_option_info.ea,10);
				now_option.step85				= parseInt(_option_info.step85,10);
				now_option.export_ea			= parseInt(_option_info.export_ea,10);
				now_option.request_ea			= parseInt(_option_info.request_ea,10);
				
				if(now_option.package_yn != 'y') {
					now_option.stock_txt			= parseInt(now_option.stock,10).number_format();
					now_option.ea_txt				= now_option.ea.number_format();
					now_option.step85_txt			= now_option.step85.number_format();
					now_option.export_ea_txt		= now_option.export_ea.number_format();
					now_option.request_ea_txt		= now_option.request_ea.number_format();
				} else {
					now_option.stock_txt			= '실제상품▼';
					now_option.ea_txt				= '[' + now_option.ea.number_format() + ']';
					now_option.step85_txt			= '[' + now_option.step85.number_format() + ']';
					now_option.export_ea_txt		= '[' + now_option.export_ea.number_format() + ']';
					now_option.request_ea_txt		= '[' + now_option.request_ea.number_format() + ']';

				}


				now_option.title				='';
				if(_option_info.option1)
					now_option.option_title		= (_option_info.title1) ? _option_info.title1 + ":" + _option_info.option1 : _option_info.option1;

				if(_option_info.option2)
					now_option.option_title		= (_option_info.title2) ? _option_info.title2 + ":" + _option_info.option2 : _option_info.option2;

				if(_option_info.option3)
					now_option.option_title		= (_option_info.title3) ? _option_info.title3 + ":" + _option_info.option3 : _option_info.option3;

				if(_option_info.option4)
					now_option.option_title		= (_option_info.title4) ? _option_info.title4 + ":" + _option_info.option4 : _option_info.option4;

				if(_option_info.option5)
					now_option.option_title		= (_option_info.title5) ? _option_info.title5 + ":" + _option_info.option5 : _option_info.option5;

				target_options.push(now_option);

				for(package_seq in _option_info['packages']){
					now_package						= {};
					_package_info					= shipping_list[order_seq][bundle_seq]['options'][option_seq]['packages'][package_seq];
					now_package.option_type			= 'package_option';
					now_package.item_option_seq		= _package_info.item_option_seq;
					now_package.order_seq			= _package_info.order_seq;
					now_package.goods_seq			= _package_info.goods_seq;
					now_package.goods_name			= _package_info.goods_name;
					now_package.image				= _package_info.image;
					now_package.item_seq			= _package_info.item_seq
					now_package.goods_code			= _package_info.goods_code;
					now_package.package_option_seq	= _package_info.package_option_seq;
					now_package.return_badea		= _package_info.return_badea;
					
					now_package.stock				= parseInt(_package_info.stock);
					now_package.unit_ea				= parseInt(_package_info.unit_ea,10);
					now_package.reservation15		= parseInt(_package_info.reservation15,10);
					now_package.reservation25		= parseInt(_package_info.reservation25,10);
					
					now_package.bar_goods_code		= _option_info.bar_goods_code;
					now_package.ea					= parseInt(_option_info.ea,10);
					now_package.step85				= parseInt(_option_info.step85,10);
					now_package.export_ea			= parseInt(_option_info.export_ea,10);
					now_package.request_ea			= parseInt(_option_info.request_ea,10) * now_package.unit_ea;

					now_package.stock_txt			= '<span class="red stock">' + now_package.stock.number_format() + '</span>';

					if(now_package.ea > 0) {
						allEa						= now_package.ea * now_package.unit_ea;
						now_package.ea_txt			= '<span class="red stock">[' + now_package.ea.number_format() + ']x';
						now_package.ea_txt			+= now_package.unit_ea.number_format() + '=' + allEa.number_format() + '</span>';
					} else {
						now_package.ea_txt			= '<span class="red stock">0</span>';
					}

					if(now_package.step85 > 0) {
						allEa						= now_package.step85 * now_package.unit_ea;
						now_package.step85_txt		= '<span class="red stock">[' + now_package.step85.number_format() + ']x';
						now_package.step85_txt		+= now_package.unit_ea.number_format() + '=' + allEa.number_format() + '</span>';
					} else {
						now_package.step85_txt		= '<span class="red stock">0</span>';
					}
					

					if(now_package.export_ea > 0) {
						allEa						= now_package.export_ea * now_package.unit_ea;
						now_package.export_ea_txt	= '<span class="red stock">[' + now_package.export_ea.number_format() + ']x';
						now_package.export_ea_txt	+= now_package.unit_ea.number_format() + '=' + allEa.number_format() + '</span>';
					} else {
						now_package.export_ea_txt	= '<span class="red stock">0</span>';
					}


					if(now_package.request_ea > 0) {
						now_package.request_ea_txt	= '<span class="red stock">[' + now_package.request_ea.number_format() + ']x';
						now_package.request_ea_txt	+= now_package.unit_ea.number_format() + '=' + now_package.request_ea.number_format() + '</span>';

					} else {
						now_package.request_ea_txt	= '<span class="red stock">0</span>';
					}


					if(_package_info.option1)
						now_package.option_title	= (_package_info.title1) ? _package_info.title1 + ":" + _package_info.option1 : _package_info.option1;

					if(_package_info.option2)	
						now_package.option_title	= (_package_info.title2) ? _package_info.title2 + ":" + _package_info.option2 : _package_info.option2;
					
					if(_package_info.option3)
						now_package.option_title	= (_package_info.title3) ? _package_info.title3 + ":" + _package_info.option3 : _package_info.option3;

					if(_package_info.option4)
						now_package.option_title	= (_package_info.title4) ? _package_info.title4 + ":" + _package_info.option4 : _package_info.option4;
					
					if(_package_info.option5)
						now_package.option_title	= (_package_info.title5) ? _package_info.title5 + ":" + _package_info.option5 : _package_info.option5;

					target_options.push(now_package);
				}
				
				
				for(suboption_seq in _option_info['suboptions']){
					now_suboption					= {};
					_suboption_info					= shipping_list[order_seq][bundle_seq]['options'][option_seq]['suboptions'][suboption_seq];
					
					now_suboption.option_type		= 'suboption';
					now_suboption.goods_code		= _item_info.goods_data.goods_seq;
					now_suboption.goods_seq			= _item_info.goods_data.goods_seq;
					now_suboption.item_seq			= _item_info.item_seq
					now_suboption.npay_order_id		= _item_info.npay_order_id;
					now_suboption.individual_export	= _item_info.individual_export;

					now_suboption.item_option_seq	= _suboption_info.item_suboption_seq;	//suboption_seq 를 option_seq로 사용
					now_suboption.title				= _suboption_info.title;
					now_suboption.suboption			= _suboption_info.suboption;
					
					now_suboption.stock				= _suboption_info.stock;
					now_suboption.ea				= parseInt(_suboption_info.ea,10);
					now_suboption.step85			= parseInt(_suboption_info.step85,10);
					now_suboption.export_ea			= parseInt(_suboption_info.export_ea,10);
					now_suboption.request_ea		= parseInt(_suboption_info.request_ea,10);

					now_suboption.stock_txt			= parseInt(now_option.stock,10).number_format();
					now_suboption.ea_txt			= now_option.ea.number_format();
					now_suboption.step85_txt		= now_option.step85.number_format();
					now_suboption.export_ea_txt		= now_option.export_ea.number_format();
					now_suboption.request_ea_txt	= now_option.request_ea.number_format();

					now_suboption.package_yn		= _suboption_info.package_yn;

					if(now_suboption.package_yn != 'y') {
						now_suboption.stock_txt			= parseInt(now_suboption.stock,10).number_format();
						now_suboption.ea_txt			= now_suboption.ea.number_format();
						now_suboption.step85_txt		= now_suboption.step85.number_format();
						now_suboption.export_ea_txt		= now_suboption.export_ea.number_format();
						now_suboption.request_ea_txt	= now_suboption.request_ea.number_format();
					} else {
						now_suboption.stock_txt			= '실제상품▼';
						now_suboption.ea_txt			= '[' + now_suboption.ea.number_format() + ']';
						now_suboption.step85_txt		= '[' + now_suboption.step85.number_format() + ']';
						now_suboption.export_ea_txt		= '[' + now_suboption.export_ea.number_format() + ']';
						now_suboption.request_ea_txt	= '[' + now_suboption.request_ea.number_format() + ']';
					}

					target_options.push(now_suboption);

					for(sub_package_seq in _suboption_info['packages']){

						sub_package						= {};
						
						_sub_package_info				= shipping_list[order_seq][bundle_seq]['options'][option_seq]['suboptions'][suboption_seq]['packages'][sub_package_seq];

						sub_package.option_type			= 'package_suboption';
						sub_package.item_option_seq		= _sub_package_info.item_suboption_seq;
						sub_package.order_seq			= _sub_package_info.order_seq;
						sub_package.goods_seq			= _sub_package_info.goods_seq;
						sub_package.goods_name			= _sub_package_info.goods_name;
						sub_package.image				= _sub_package_info.image;
						sub_package.item_seq			= _sub_package_info.item_seq
						sub_package.goods_code			= _sub_package_info.goods_code;
						sub_package.package_option_seq	= _sub_package_info.package_suboption_seq;
						sub_package.return_badea		= _sub_package_info.return_badea;

						sub_package.stock				= parseInt(_sub_package_info.stock);
						sub_package.unit_ea				= parseInt(_sub_package_info.unit_ea,10);
						sub_package.reservation15		= parseInt(_sub_package_info.reservation15,10);
						sub_package.reservation25		= parseInt(_sub_package_info.reservation25,10);

						sub_package.bar_goods_code		= _option_info.bar_goods_code;
						sub_package.ea					= parseInt(now_suboption.ea,10);
						sub_package.step85				= parseInt(now_suboption.step85,10);
						sub_package.export_ea			= parseInt(now_suboption.export_ea,10);
						sub_package.request_ea			= parseInt(now_suboption.request_ea,10) * sub_package.unit_ea;

						sub_package.stock_txt			= '<span class="red stock">' + sub_package.stock.number_format() + '</span>';
						

						if(sub_package.ea > 0) {
							allEa						= sub_package.ea * sub_package.unit_ea;
							sub_package.ea_txt			= '<span class="red stock">[' + sub_package.ea.number_format() + ']x';
							sub_package.ea_txt			+= sub_package.unit_ea.number_format() + '=' + allEa.number_format() + '</span>';
						} else {
							sub_package.ea_txt			= '<span class="red stock">0</span>';
						}

						if(sub_package.step85 > 0) {
							allEa						= sub_package.step85 * sub_package.unit_ea;
							sub_package.step85_txt		= '<span class="red stock">[' + sub_package.step85.number_format() + ']x';
							sub_package.step85_txt		+= sub_package.unit_ea.number_format() + '=' + allEa.number_format() + '</span>';
						} else {
							sub_package.step85_txt		= '<span class="red stock">0</span>';
						}


						if(sub_package.export_ea > 0) {
							allEa						= sub_package.export_ea * sub_package.unit_ea;
							sub_package.export_ea_txt	= '<span class="red stock">[' + sub_package.export_ea.number_format() + ']x';
							sub_package.export_ea_txt	+= sub_package.unit_ea.number_format() + '=' + allEa.number_format() + '</span>';
						} else {
							sub_package.export_ea_txt	= '<span class="red stock">0</span>';
						}

						if(sub_package.request_ea > 0) {
							sub_package.request_ea_txt	= '<span class="red stock">[' + sub_package.request_ea.number_format() + ']x';
							sub_package.request_ea_txt	+= sub_package.unit_ea.number_format() + '=' + sub_package.request_ea.number_format() + '</span>';

						} else {
							sub_package.request_ea_txt	= '<span class="red stock">0</span>';
						}

						if(_sub_package_info.option1)
							sub_package.option_title	= (_sub_package_info.title1) ? _sub_package_info.title1 + ":" + _sub_package_info.option1 : _sub_package_info.option1;

						if(_sub_package_info.option2)	
							sub_package.option_title	= (_sub_package_info.title2) ? _sub_package_info.title2 + ":" + _sub_package_info.option2 : _sub_package_info.option2;
						
						if(_sub_package_info.option3)
							sub_package.option_title	= (_sub_package_info.title3) ? _sub_package_info.title3 + ":" + _sub_package_info.option3 : _sub_package_info.option3;

						if(_sub_package_info.option4)
							sub_package.option_title	= (_sub_package_info.title4) ? _sub_package_info.title4 + ":" + _sub_package_info.option4 : _sub_package_info.option4;
						
						if(_sub_package_info.option5)
							sub_package.option_title	= (_sub_package_info.title5) ? _sub_package_info.title5 + ":" + _sub_package_info.option5 : _sub_package_info.option5;

						target_options.push(sub_package);

					}
				}			
			}
			

			//묶음배송 form 생성
			for(r_cnt = target_options.length, i = 0; i < r_cnt; i++){
				
				bundle_seq_insert	= $('<input/>', {type : 'hidden', name : 'check_shipping_seq[' + bundle_seq + ']', value : bundle_seq}).addClass("check_bundle_seq");

				//해당 주문 리스트및 묶음배송번호(배송번호) 추가
				if($('#bundle_export_form input[name="order_seq[' + bundle_seq + ']').val() != order_seq){
					$('#bundle_export_form').append($('<input/>', {type : 'hidden', name : 'order_seq[' + bundle_seq + ']', value : order_seq}));
					$('#bundle_export_form').append(bundle_seq_insert);
				}else if($('#bundle_export_form input[name="check_shipping_seq[' + bundle_seq + ']').val() != bundle_seq){
					$('#bundle_export_form').append(bundle_seq_insert);
				}

				
				now_info		= target_options[i];
				option_info		= '';
				isPackage		= false;
				
				switch(now_info.option_type) {
					case	'option' :
						view_option_type	= 'option';

						//기본옵션 처리
						add_class			= 'export_ea_opt';
						option_info			+= '<input type="hidden" name="chk_individual_export[' + now_info.item_seq +']" value="' + now_info.individual_export + '" />';
						
						//주문상품
						option_info			+= '<table class="list-goods-info"><tr>';
						option_info			+= '<td width="50" style="border:0px">'


						option_info			+= '<span class="order-item-image"><img src="' + now_info.image + '" class="small_goods_image" onerror="admin_goods_image(this,\''+ now_info.image +'\',1,\'thumbCart\');" /></span>';

						option_info			+= '<td style="border:0px;"><div class="goods_name">';
						option_info			+= (now_info.goods_type == 'gift') ? '<img src="/admin/skin/default/images/common/icon_gift.gif" /> ' : '';
						option_info			+= (now_info.cancel_type == '1') ? '<span class="order-item-cancel-type " >[청약철회불가]</span><br/>' : '';
						option_info			+= now_info.goods_name + '</span>';
						
						warehoseFrom		= $('.warehouse_' + bundle_seq + '_' + view_option_type + '_' + now_info.item_option_seq);

						if(now_info.option_title){
							option_info		+= '<div class="goods_option"><img src="/admin/skin/default/images/common/icon_option.gif" /> ' + now_info.option_title + '</div>';

							if(warehoseFrom[0])
								option_info	+= '<div class="warehouse-info-lay">' + warehoseFrom.html() + '</div>';
							else if (now_info.goods_code)
								option_info	+= '<div class="goods_option fx11 goods_code_icon">[상품코드: ' + now_info.goods_code + ']</div>';
						}else{
							if(warehoseFrom[0])
								option_info	+= '<div class="warehouse-info-lay">' + warehoseFrom.html() + '</div>';
							else if(now_info.goods_code)
								option_info	+= '<div class="info"><div class="goods_option fx11 goods_code_icon">[상품코드: ' + now_info.goods_code + ']</div></div>';
						}

						
						if(now_info.subinputs instanceof Array){
							for(s = 0, s_cnt = now_info.subinputs.length; s < s_cnt; s++){
								option_info	+= '<div class="goods_input"><img src="/admin/skin/default/images/common/icon_input.gif" />';
								option_info	+= (now_info.subinputs[s].title) ? now_info.subinputs[s].title : '';
								option_info	+= (now_info.subinputs[s].type == 'file') ? '<a href="../order_process/filedown?file=' + now_info.subinputs[s].value + '" target="actionFrame"> : ' + now_info.subinputs[s].value + '</a>' : now_info.subinputs[s].value;
							}
						}

						option_info			+= '</tr></table>';

						break;

					
					case	'suboption' :
						view_option_type	= 'suboption';
						option_info			= '<img src="/admin/skin/default/images/common/icon_add_arrow.gif" /> <img src="/admin/skin/default/images/common/icon_add.gif" />';
						option_info			+= now_info.title + ':' + now_info.suboption;

						warehoseFrom		= $('.warehouse_' + bundle_seq + '_' + view_option_type + '_' + now_info.item_option_seq);
						if(warehoseFrom[0])
							option_info		+= '<div class="warehouse-info-lay">' + warehoseFrom.html() + '</div>';
						else if (now_info.goods_code)
							option_info		+= '<div class="goods_option fx11 goods_code_icon">[상품코드: ' + now_info.goods_code + ']</div>';

						break;

					case	'package_option' :
					case	'package_suboption' :
						view_option_type	= now_info.option_type.replace(/package_/, '');
						isPackage			= true;

						//패키지 상품 처리
						option_info			= '<table class="list-goods-info" width="100%"><tbody><tr>';
						option_info			+= '<td valign="top" style="border:none;"><img src="/admin/skin/default/images/common/icon/ico_package.gif" border="0" /></td>';
						option_info			+= '<td width="50" style="border:0px" class="center"><span class="order-item-image">';
						option_info			+= '<img src="' + now_info.image + '" class="small_goods_image" onerror="admin_goods_image(this,' + now_info.goods_seq + ',1,\'thumbCart\');" /></span></td>';
						option_info			+= '<td style="border:0px;"><div class="goods_name">';
						option_info			+= '<span class="red">[실제상품' + now_info.package_option_seq + '] ' + now_info.goods_name + '</span></div>';
						
						warehoseFrom		= $('.warehouse_' + bundle_seq + '_' + view_option_type + '_' + now_info.item_option_seq + '_' + now_info.package_option_seq);

						if(now_info.option_title){
							option_info		+= '<div class="goods_option"><img src="/admin/skin/default/images/common/icon_option.gif" /> ' + now_info.option_title + '</div>';
							if(warehoseFrom[0])
								option_info	+= '<div class="warehouse-info-lay">' + warehoseFrom.html() + '</div>';
							else if (now_info.goods_code)
								option_info	+= '<div class="goods_option fx11 goods_code_icon">[상품코드: ' + now_info.goods_code + ']</div>';
						}else{
							if(warehoseFrom[0])
								option_info	+= '<div class="warehouse-info-lay">' + warehoseFrom.html() + '</div>';
							else if(now_info.goods_code)
								option_info	+= '<div class="info"><div class="goods_option fx11 goods_code_icon">[상품코드: ' + now_info.goods_code + ']</div></div>';
						}

						option_info			+= '</td></tr></table>';
						break;
				}
				

				addArrForm			= '[' + bundle_seq + '][' + view_option_type + '][' + now_info.item_option_seq + ']';
				
				
				var stock_info	= '';

				if(now_info.stock == '미매칭'){
					stock_info	= '미매칭';
				} else if(now_info.package_yn == 'y') {
					stock_info	= now_info.stock_txt;
				} else {
					
					if (isPackage === true)
						stockName	= 'packageStock' + addArrForm + '[' + now_info.package_option_seq + ']';
					else
						stockName	= 'stock' + addArrForm ;
					
					stockQty		= parseInt($('input[name="' + stockName + '"]').val(), 10);
					stockQty		= (isNaN(stockQty)) ? 0 : stockQty;
					stock_info		= stockQty.number_format();
				}

				var request		= $('<DOCUMENT/>');
				var checker		= $('<DOCUMENT/>');
				barcode_no		= (typeof now_info.bar_goods_code == 'string') ? now_info.bar_goods_code : '';
				var requestEaTmp	= {};
				if ( isPackage !== true) {
					//일반상품
					requestEa			= $('input[name="request_ea' + addArrForm +'"]').clone();
					//request_ea_tmp가 있으면 npay주문 건
					if($('input[name="request_ea_tmp' + addArrForm +'"]').hasOwnProperty('length')) {
						requestEaTmp	= $('input[name="request_ea_tmp' + addArrForm +'"]').clone();
					}

					shippingGoodsKind	= $('input[name="shipping_goods_kind' + addArrForm +'"]').clone();
					request.append(shippingGoodsKind);

					barcodeName			= 'barcode_ea[bundle][' + barcode_no + ']';
					barcodeClass		= 'barcode_ea_bundle barcode_ready';
					barcodeOnblur		= 'check_barcode_ea(this);'
					
				} else {
					unitEa			= $('input[name="unit_ea[' + view_option_type + '][' + now_info.item_option_seq + '][' + now_info.package_option_seq + ']');
					request.append(unitEa);

					requestEa		= '<span class="package_ea" style="display:inline-block;width:38px;text-align:right;">';
					requestEa		+= now_info.request_ea.number_format() + '</span>';

					barcodeName			= 'package_barcode_ea[bundle][' + barcode_no + ']';
					barcodeClass		= 'barcode_ea_bundle barcode_ready package';
					barcodeOnblur		= 'package_check_barcode_ea(this);'
				}

				if(now_info.request_ea > 0 && now_info.package_yn != 'y'){
					checker.append('<span> 대기 </span>');
						
					chk_input				= {};
					chk_input['type']		= 'text';
					chk_input['name']		= barcodeName;
					chk_input['class']		= barcodeClass;
					chk_input['value']		= 0;
					chk_input['size']		= 2;
					chk_input['onblur']		= barcodeOnblur;
					checker.append($('<input/>',chk_input));
				}else{
					checker.append('<span> - </span>');
				}				
				
				request.append(requestEa);
				request.append(requestEaTmp);

				now_row	= $(template_tds);
				now_row.find('td.orderSeq').append(order_seq);
				now_row.find('td.optInfo').append(option_info);
				now_row.find('td.stockCnt').append(stock_info);
				now_row.find('td.orderCnt').append(now_info.ea_txt);
				now_row.find('td.cancleCnt').append(now_info.step85_txt);
				now_row.find('td.sentCnt').append(now_info.export_ea_txt);
				now_row.find('td.leftCnt').append(now_info.request_ea_txt);
				now_row.find('td.requestInfo').append(request.html());
				now_row.find('td.itemCheckInfo').append(checker.html());

				
				if(isPackage === true){
					var parkageCheckNames	= ['packageStock', 'packageOptioninfo', 'packageGoodscode', 'packageWhSupplyPrice'];
					for(ni = 0, niCnt = parkageCheckNames.length; ni < niCnt; ni++) {
						var nowForm	= $('input[name="' + parkageCheckNames[ni] + addArrForm + '[' + now_info.package_option_seq + ']' +'"]').clone();
						if(nowForm[0])
							now_row.find('td.optInfo').append(nowForm);
					}
				}else{
					var checkNames	= ['stock', 'optioninfo', 'whSupplyPrice', 'goodscode'];
					for(ni = 0, niCnt = checkNames.length; ni < niCnt; ni++) {
						var nowForm	= $('input[name="' + checkNames[ni] + addArrForm +'"]').clone();
						if(nowForm[0])
							now_row.find('td.optInfo').append(nowForm);
					}
				}
				
				td_style	= {};
				opt_style	= {};
				td_style['border']					= '1px solid rgb(131, 168, 131)';
				

				if(view_option_type == 'suboption'){
					td_style['background-color']	= 'rgb(190, 232, 190)';
					now_row.find('td.optInfo').css('padding','3px 0px 0px 45px');
				}else{
					td_style['background-color']	= 'rgb(215, 251, 215)';
				}

				now_row.find('td.info').addClass(now_info.option_type);
				now_row.find('td.info').css(td_style);				

				has_order.append(now_row);
				bundle_cnt++;
			}
		}
		
		if(opt_cnt < 1){
			//해당 주문에 판매처 상품이 없을경우
			no_has_order	+= '<tr class="open-tr"><td height="60" class="info option center">' + order_seq + '</td>';
			no_has_order	+= '<td class="info option" align="center" colspan="6">판매자(';
			no_has_order	+= provider_name + ')가 배송할 실물상품이 없습니다.</td>';
			no_has_order	+= '<td class="null" align="center">→</td><td class="info option" align="center">-</td>';
			no_has_order	+= '<td class="info option" align="center">-</td>';
			no_has_order	+= '<td class="info option delivery-info" align="center" rowspan="1">-</td></tr>';
		}
	}

	if(Object.keys(target_provider).length > 1){
		
		var bundle_provider	= $('<SELECT/>', {name:'bundle_provider',onChange:'bundle_list_maker(this.value)'});

		for(now_prod_seq in target_provider){
			bundle_provider.append($('<OPTION/>', {value : now_prod_seq, text : target_provider[now_prod_seq]}));
		}
		
		bundle_provider.val(provider_seq);
		
		//var bundle_provider		= target_provider[provider_seq];
	}else{
		var bundle_provider		= target_provider[provider_seq];
	}

	$('#bundle_provider_list').html(bundle_provider);


	var delv_numb	= '<input type="text" size="20" name="bundle_delivery_number" class="line delivery_number" />';
	var exp_attr	= {};
	
	if(_order_info.shipping_method.match(/international/i)){
		export_method	= {};
		for(del_i = 0, del_r_cnt = bundle_info.international.length; del_i < del_r_cnt; del_i++){
			delv_code	= bundle_info.international[del_i]['code'];
			if(!export_method.hasOwnProperty(delv_code)){
				export_method[delv_code]		= bundle_info.international[del_i]['method'];
			}
		}

		append_more	= delv_numb;
	}else{
		exp_attr['onchange']	= 'check_export_shipping_method(' + bundle_seq + ',"bundle");'
		var cou_attr			= {};
		cou_attr['name']		= 'bundle_delivery_company';
		cou_attr['id']			= 'bundle_delivery_company[' + bundle_seq + ']';
		cou_attr['class']		= 'deliveryCompany';
		cou_attr['onchange']	= 'check_deliveryCompany(' + bundle_seq + ', "bundle")';
		cou_attr['style']		= 'width:85px';

		var append_more	= $('<div/>');
		var couriers	= $('<select/>',cou_attr);
		

		//if(!_order_info.shipping_method.match(/delivery|postpaid/i))	append_more.addClass('hide');
		for(cou_code in couriers_list){
			opt_bg		= (cou_code.match(/auto_/i)) ? 'background-color:yellow' : 'background-color:#ffffff';
			couriers.append('<OPTION value="' + cou_code + '" style="' + opt_bg + '">' + couriers_list[cou_code] + '</OPTION>');
		}
		
		append_more.append(couriers);
		append_more.append(delv_numb);
	}

	// export_method 가 위에서 존재하지 않으면, bundle_export_shipping_method 가 존재하지 않아 송장이 초기화되는 이슈 수정(2021.05.20 : kjw)
	if(Object.keys(export_method).length > 0) {
		exp_attr['name']		= 'bundle_export_shipping_method';
		exp_attr['id']			= 'bundle_export_shipping_method_' + bundle_seq;
		exp_attr['class']		= 'export_shipping_method';
		exp_attr['style']		= 'width:85px;';
	
		var export_method_sel	= $('<select/>', exp_attr);

		for(exp_code in export_method){
			export_method_sel.append('<OPTION value="' + exp_code + '">' + export_method[exp_code] + '</OPTION>');
		}
	} else {
		var export_method_append_html = '<input type="hidden" name="bundle_export_shipping_method" id="bundle_export_shipping_method_'+bundle_seq+'" value="'+_order_info.shipping_method+'">';
		append_more.append(export_method_append_html);
	}

	var export_method		= $('<TABLE/>', {width : "100%"}).addClass("inner-table").append('<TR/>');
	export_method.find('tr').append('<TD class="info pdl5 left"/>');
	//export_method.find('td').eq(0).append(export_method_sel).append(append_more);
	export_method.find('td').eq(0).append(append_more);


	var export_method_td	= $('<TD/>', {rowspan : bundle_cnt, style : "background-color:rgb(215, 251, 215); border:1px solid rgb(131, 168, 131)"}).append(export_method).addClass("info pdl5 left");

	has_order.find('td.itemCheckInfo').eq(0).after(export_method_td);

	$('.bundle_shipping_group').append(has_order);
	$('.bundle_shipping_group').append(no_has_order);
}

function bundle_left_check(){
	
	if(typeof $('select[name="bundle_provider"]').val() == 'undefined'){
		go_search_export('bundle');
	}

	delete bundle_info['provider_list'][bundle_info.selected];
	bundle_info['done_provider_list'].push(bundle_info.selected);
	$('select[name="bundle_provider"] > option[value="' + bundle_info.selected + '"]').remove();
	var naxt_provider	= $('select[name="bundle_provider"] > option').eq(0).val();
	bundle_list_maker(naxt_provider);
}

function batch_status_popup(mode,str_export_code,ticket_export_cnt,result_obj,bundle_mode)
{
	var export_date = $("input[name='export_date'").val();
	var data_str  = "codes="+str_export_code+"&mode="+mode+"&export_date="+export_date+"&exist_invoice="+result_obj.exist_invoice;
	if( result_obj.req_cnt ){
		data_str += "&req_cnt="+result_obj.req_cnt;
		data_str += "&err_cnt="+result_obj.err_cnt;
	}else{
		data_str += "&ticket="+ticket_export_cnt;
		data_str += "&cnt_export_request_45="+result_obj.cnt_export_request_45;
		data_str += "&cnt_export_result_goods_45="+result_obj.cnt_export_result_goods_45;
		data_str += "&cnt_export_error_45="+result_obj.cnt_export_error_45;
		data_str += "&cnt_export_request_55="+result_obj.cnt_export_request_55;
		data_str += "&cnt_export_result_coupon_55="+result_obj.cnt_export_result_coupon_55;
		data_str += "&cnt_export_result_goods_55="+result_obj.cnt_export_result_goods_55;
		data_str += "&cnt_export_error_55="+result_obj.cnt_export_error_55;
		data_str += "&export_result_error_msg="+result_obj.export_result_error_msg;
	}
	data_str	+= "&bundle_mode=" + bundle_mode;

	$.ajax({
		type: "post",
		url: "../export/batch_status_popup",
		data: data_str,
		success: function(result){
			if	(result){
				$("#batch_status_popup_layer").html(result);
				if(bundle_mode != 'bundle')		var close_callback	= function(){document.location.reload();}
				else							var close_callback	= function(){bundle_left_check();}

				openDialog("일괄출고처리", "batch_status_popup_layer", {"width":"930","height":"300","close":close_callback});
			}
		}
	});
}

function set_date(start,end){
	$("input[name='regist_date[]']").eq(0).val(start);
	$("input[name='regist_date[]']").eq(1).val(end);
}

function searchLayerOpen(){
	var offset = $("#search_keyword").offset();
	if( offset) {
		$('.searchLayer').css({
			'position' : 'absolute',
			'z-index' : 999,
			'left' : -1,
			'top' : '100%',
			'width':$("#search_keyword").width()
		}).show();
	}
}

function check_all(obj)
{
	var bobj = $(obj);
	var chk = false;
	if( bobj.attr('checked') ){
		chk = true;
	}
	$(".check_shipping_group_seq").each(function(){	
		if($(this).attr('name') != 'disable_check') $(this).attr('checked',chk);
	});
}

function last_tr(tidx,mode){
 	if(mode=='on'){
 		var cssObj = {
			'border-bottom' : '2px solid #83a883'
		}
 	}else{
 		var cssObj = {
 		 	'border-bottom' : '1px solid #000000'
 		}
 	}

	$("table.table-export-info").eq(tidx).find("tr.open-tr:last-child td.option").each(function(){
		$(this).css(cssObj);
	});
 	$("table.table-export-info").eq(tidx).find("tr.open-tr:last-child td.suboption").each(function(){
		$(this).css(cssObj);
	});
 	$("table.table-export-info").eq(tidx).find("tr.open-tr td.delivery-info").each(function(){
 		$(this).css(cssObj);
	});
 	$("table.table-export-info").eq(tidx).find("tr.close-tr td.option").each(function(){
		$(this).css(cssObj);
	});
}

function check_bg(){
	var cssObj_option_on = {
      'background-color' : '#d7fbd7',
      'border' : '1px solid #83a883'
    }
	var cssObj_suboption_on = {
      'background-color' : '#bee8be',
      'border' : '1px solid #83a883'
    }
	var cssObj_option_off = {
      'background-color' : '#ffffff',
      'border' : '1px solid #dddddd'
    }
	var cssObj_suboption_off = {
      'background-color' : '#f6f6f6',
      'border' : '1px solid #dddddd'
    }
	$("input.check_shipping_group_seq").each(function(tidx){
		if( $(this).attr("checked") ){
			$(this).closest("div").next().find("td.option").each(function(){
				$(this).css(cssObj_option_on);
			});
			$(this).closest("div").next().find("td.suboption").each(function(){
				$(this).css(cssObj_suboption_on);
			});
			last_tr(tidx,'on');
		}else{
			$(this).closest("div").next().find("td.option").each(function(){
				$(this).css(cssObj_option_off);
			});
			$(this).closest("div").next().find("td.suboption").each(function(){
				$(this).css(cssObj_suboption_off);
			});
			last_tr(tidx,'off');
		}
	});
}

function get_checked_input()
{
	return $("input.check_shipping_group_seq:checked").length;
}

function order_print()
{
	if( ! get_checked_input() ){
		alert('인쇄할 주문이 없습니다.');
		return false;
	}
	var url = "../order/order_prints?ordarr=";
	$("input.check_shipping_group_seq:checked").each(function(){
		url += $(this).attr('order_seq')+"|";
	});
	window.open(url, '', 'width=850px,height=800px,toolbar=no,location=no,resizable=yes,scrollbars=yes');
}

function export_print()
{
	if( ! get_checked_input() ){
		alert('인쇄할 출고가 없습니다.');
		return false;
	}
	var url = "../export/export_prints?export=|&order=";
	$("input.check_shipping_group_seq:checked").each(function(){
		url += $(this).attr('order_seq')+"|";
	});
	window.open(url, '', 'width=850px,height=800px,toolbar=no,location=no,resizable=yes,scrollbars=yes');
}

function check_request_ea(inputobj,ea)
{
	var iobj = $(inputobj);
	if(iobj.val() > ea){
		alert('보낼수량이 남은수량보다 큽니다.');
		iobj.val(ea);
		iobj.focus();

	}
}

function open_search_form()
{
	$("div.search-form-container").show();
	$("div#btn-close-search").show();
	$("div#btn-open-search").hide();
}
function close_search_form()
{
	$("div.search-form-container").hide();
	$("div#btn-close-search").hide();
	$("div#btn-open-search").show();

}

function btn_export_toggle(btn){
	var btnObj = $(btn);
	var itemTableObj = btnObj.closest("table").closest("div").next("table");
	var pickingInputObj = btnObj.closest("td").next("td").find("input");
	var shipping_group_seq = btnObj.closest("tr").find("td input[type='checkbox']").val();
	var export_shipping_method = itemTableObj.find("select#export_shipping_method_"+shipping_group_seq+" option:selected").val();
	var delivery_company = itemTableObj.find("select#delivery_company_"+shipping_group_seq+" option:selected").val();
	var delivery_number = itemTableObj.find("td input[name='delivery_number["+shipping_group_seq+"]']").val();

	var tag = "";
	if( btnObj.hasClass("opened") ){
		btnObj.removeClass("opened");

		tag = itemTableObj.find("tr.open-tr td.delivery-info").html();
		itemTableObj.find("tr.close-tr td.delivery-info").html(tag);
		itemTableObj.find("tr.open-tr td.delivery-info").html('');

		itemTableObj.find("tr.open-tr").hide();
		itemTableObj.find("tr.close-tr").show();

		pickingInputObj.attr("disabled",true);
		itemTableObj.find("tr.open-tr td input[type='text']").each(function(){
			if( $(this).hasClass("export_ea") ){
				$(this).val($(this).attr('org'));
			}
		});
	}else{
		btnObj.addClass("opened");
		itemTableObj.find("tr.open-tr").show();
		itemTableObj.find("tr.close-tr").hide();
		pickingInputObj.attr("disabled",false);

		tag = itemTableObj.find("tr.close-tr td.delivery-info").html();
		itemTableObj.find("tr.open-tr td.delivery-info").html(tag);
		itemTableObj.find("tr.close-tr td.delivery-info").html('');
	}
	$("select#export_shipping_method_"+shipping_group_seq+" option[value='"+export_shipping_method+"']").attr("selected",true);
	$("select#delivery_company_"+shipping_group_seq+" option[value='"+delivery_company+"']").attr("selected",true);
	$("input[name='delivery_number["+shipping_group_seq+"]']").val(delivery_number);
	check_deliveryCompany(shipping_group_seq);
}

function reset_search_form()
{
	var obj = $('table.search-form-table, .table_search');
	obj.find('select, textarea, input[type=text]').val('');
	obj.find('input:radio, input:checkbox').removeAttr('checked').removeAttr('selected');

	var chk_except = false;
	obj.find('input:text, input:hidden').each(function() {
		chk_except = false;
		if (this.name != '') {
			if (!chk_except) $(this).val('');
		}
	});
	$('.search_type_text').hide();
}

function set_default_search_form()
{
	var objTd = $("form[name='search_default_frm'] table.info-table-style tr td.its-td");
	var order_default_date_field = objTd.find("select[name='order_default_date_field'] option:selected").val(); // 기본 기간 설정
	var order_default_period = objTd.find("input[name='order_default_period']:checked").val(); // 기간 설정
	if( order_default_period == '-1 day' ){
		$("input[name='regist_date[]']").eq(0).val(today);
		$("input[name='regist_date[]']").eq(1).val(today);
	}else if( order_default_period == '-1 week' ){
		$("input[name='regist_date[]']").eq(0).val(week);
		$("input[name='regist_date[]']").eq(1).val(today);

	}else if( order_default_period == '-1 mon' ){
		$("input[name='regist_date[]']").eq(0).val(mon);
		$("input[name='regist_date[]']").eq(1).val(today);

	}else if( order_default_period == '-3 mon' ){
		$("input[name='regist_date[]']").eq(0).val(mon3);
		$("input[name='regist_date[]']").eq(1).val(today);
	}else if( order_default_period == 'all' ){
		$("input[name='regist_date[]']").eq(0).val("");
		$("input[name='regist_date[]']").eq(1).val("");
	}
	$("select[name='date_field'] option[value='"+order_default_date_field+"']").attr("selected",true);

	objTd.find("input[name='order_default_step[]']:checked").each(function(){ // 상태
		$("input[name='step["+$(this).val()+"]']").attr("checked",true);
	});

}

function check_export_shipping_method(seq,mode)
{
	if(mode == 'bundle')	var selectObj = $("select#bundle_export_shipping_method_"+seq);
	else					var selectObj = $("select#export_shipping_method_"+seq);
	var selectObj = $("select#export_shipping_method_"+seq);
	var val = selectObj.find("option:selected").val();
	if( val == 'quick' || val == 'direct' ){
		selectObj.next("div").addClass("hide");
	}else{
		selectObj.next("div").removeClass("hide");
	}
}


function package_reset_barcode_ea(obj)
{
	var export_ea_obj = $(obj);
	var check_ea_obj = export_ea_obj.closest("td").next().find("input");
	var export_ea = num(export_ea_obj.find("option:selected").val());
	var check_ea = num(check_ea_obj.val());
	check_ea_obj.val(0);
	package_check_barcode_ea(check_ea_obj);
}

function package_check_request_ea(obj)
{
	var export_ea_obj = $(obj);
	var export_ea = num(export_ea_obj.find("option:selected").val());
	if( export_ea > export_ea_obj.attr('org') ){
		alert('보낼수량이 남은수량보다 큽니다.');
	}
	var check_obj = '';

	var selected_option_val = export_ea_obj.find("option:selected").val();	
	var item_option_seq = export_ea_obj.attr('option_group');
	if( item_option_seq ){
		$("select[option_group='"+item_option_seq+"']").each(function(){
			$(this).find("option[value='"+selected_option_val+"']").attr("selected",true);
			check_obj = $(this).closest("td").next().find("input");
			package_check_barcode_ea(check_obj);
		});
	}
	var item_suboption_seq = export_ea_obj.attr('suboption_group');
	if( item_suboption_seq ){
		$("select[suboption_group='"+item_suboption_seq+"']").each(function(){
			$(this).find("option[value='"+selected_option_val+"']").attr("selected",true);
			package_check_barcode_ea(check_obj);
		});
	}
	
}

function package_check_barcode_ea(obj){
	var check_ea_obj = $(obj);
	var export_ea_obj = check_ea_obj.closest("td").prev().find("span.package_ea");
	var check_ea = num(check_ea_obj.val());
	var export_ea = num(export_ea_obj.html());
	var ago_msg	 = check_ea_obj.prev().html();

	if( export_ea == 0){
		check_ea_obj.prev().html("-");
		check_ea_obj.hide();
		return false;
	}

	if( check_ea >= export_ea){
		check_ea_obj.show();
		check_ea_obj.prev().html("완료");
		check_ea = export_ea;
		check_ea_obj.removeClass('barcode_ready');
		check_ea_obj.addClass('barcode_complete');
	}else if(ago_msg!='대기'){
		check_ea_obj.show();
		check_ea_obj.prev().html("대기");
		check_ea_obj.removeClass('barcode_complete');
		check_ea_obj.addClass('barcode_ready');
	}

	check_ea_obj.val(check_ea);
}

function set_goods_list(member_seq,order_seq,item_seq,option_seq,cart_table){

	var param		= "";
	var displayId	= "export_goods_selected_";

	param			= "&order_seq="+order_seq+"&member_seq="+member_seq+"&displayId="+displayId+"&cart_table="+cart_table;
	param			= param +"&item_seq="+item_seq+"&option_seq="+option_seq;

	$.ajax({
		type: "get",
		url: "../goods/select_new",
		data: "page=1"+param,
		success: function(result){
			$("div#"+displayId).html(result);
		}
	});
	openDialog("상품 검색", displayId, {"width":"1000","height":"700","show" : "fade","hide" : "fade"});
}