// 택배사 정보 변경시
function check_deliveryCompany(shipping_seq)
{
	var obj = $("select[name='delivery_company["+shipping_seq+"]']");
	var obj_option = obj.find("option:selected");
	var str = obj_option.val();
	
	var method = $("input[name='export_shipping_method["+shipping_seq+"]']").val();

	if(method != 'delivery' && method != 'postpaid'){
		obj.closest('table').find('.gf_btn_area').hide();
		return false;
	}

	if( str && str.substring(0,5) == 'auto_' ){
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

	// 굿스플로 연동시 버튼 제어 :: 2015-07-02 lwh
	if(str == gf_deliveryCode){
		obj.closest('table').find('.gf_btn_area').show();
	}else{
		obj.closest('table').find('.gf_btn_area').hide();
	}
}

function go_search_export()
{
	var provider_seq = $("input[name='provider_seq']").val();
	var provider_seq_consignment = $("select[name='provider_seq_consignment'] option:selected").val();
	var url = "../export/batch_status?";

	// 배송책임 검색
	if(provider_seq){
		url += "&provider_seq="+provider_seq;
	}

	// 배송방법 검색
	var shipping_method = '';
	var delivery_shipping = false;
	$("input[name='search_shipping_method[]']").each(function(){
		if($(this).is(':checked')){
			if($(this).val() == 'delivery')	delivery_shipping = true;
			url += "&shipping_method["+$(this).val()+"]="+$(this).val();
		}
	});

	if( delivery_shipping ) {
		// 택배사 검색 :: 2015-07-02 lwh
		var shipping_delivery = $("select[name='src_shipping_delivery'] option:selected").val();
		if( shipping_delivery ){
			url += "&src_shipping_delivery="+shipping_delivery;
		}
		
		// 운송장 번호 검색
		iobj = $("input[name='search_delivery_number']");
		if( iobj.val() && iobj.val() != '운송장번호'){
			url += "&search_delivery_number="+iobj.val();
		}
		
		iobj = $("input[name='none_search_delivery_number']");
		if( iobj.attr("checked") ){
			url += "&none_search_delivery_number=" + iobj.val();
		}
	}
	
	if ($('input[name="allselectMarkets"]').is(":checked") == true)
		url += "&selectAllMarkets=Y";

	
	if ($('input[name="selectMarkets[]"]:checked').length > 0) {
		$('input[name="selectMarkets[]"]:checked').each(function(){
			url += "&selectMarkets[]=" + this.value;
		});
	}

	// 마켓송장실패 검색
	var search_market_fail = $("input[name='search_market_fail']:checked").val();
	if( search_market_fail == 'y'){
		url += "&search_market_fail="+search_market_fail;
	}

	// 검색날짜타입 검색
	var date_field = $("select[name='date_field'] option:selected").val();
	if( date_field ){
		url += "&date_field="+date_field;
	}

	// 키워드 검색
	if( $("input[name='search_keyword']").val() ){
		url += "&search_keyword="+$("input[name='search_keyword']").val();
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
	var status = $("input[name='status']:checked").val();
	if( status ){
		url += "&status="+status;
	}

	// 검색어 설명 문구
	var search_type = $("input[name='search_type']").val();
	if( search_type ){
		url += "&search_type="+search_type;
	}

	// 네이버 페이 검색
	var search_npay_order = $("input[name='search_npay_order']:checked").val();
	if( search_npay_order == 'y'){
		url += "&search_npay_order="+search_npay_order;
	}

	// 톡체크아웃 검색
	var search_talkbuy_order = $("input[name='search_talkbuy_order']:checked").val();
	if( search_talkbuy_order == 'y'){
		url += "&search_talkbuy_order="+search_talkbuy_order;
	}

	// 키워드 검색
	var search_keyword = $("input[name='keyword']").val();
	if( search_keyword && search_keyword!='출고번호,주문번호,아이디,주문자,수령자,입금자,이메일,연락처,휴대폰,상품명,상품번호,상품코드' ){
		url += "&keyword="+search_keyword;
	}

	location.href=url;
}

function controll_search_delivery()
{
	var sobj = $("select[name='search_shipping_method'] option:selected");
	var cobj = $("input[name='none_search_delivery_number']");
	var iobj = $("input[name='search_delivery_number']");

	if( sobj.val() == '' || sobj.val() == 'delivery' ){
		$("input.search_delivery ,select.search_delivery").attr("disabled",false);
		$("input.search_delivery ,select.search_delivery").css("background","#ffffff;");
		if( cobj.attr("checked") ){
			iobj.attr("disabled",true);
			iobj.css("background","#efefef;");
		}
	}else{
		$("input.search_delivery ,select.search_delivery").attr("disabled",true);
		$("input.search_delivery ,select.search_delivery").css("background","#efefef;");
	}
}

// 현재 사용하지 않음 - 정리대상 :: 2016-10-07 lwh
function check_base_inclusion()
{
	var able_select = false;
	var able_select_company = false;

	$("input[name='base_inclusion[]']:checked").each(function(){
		if( $(this).val() == 2 ){
			able_select = true;
		}
		if( $(this).val() == 1 ){
			able_select_company = true;
		}
	});

	if(! able_select ){
		$("div#provider_select").attr("disabled",true);
	}else{
		$("div#provider_select").attr("disabled",false);
	}

	if(! able_select_company ){
		$("select[name='provider_seq_consignment']").attr("disabled",true);
	}else{
		$("select[name='provider_seq_consignment']").attr("disabled",false);
	}
}

function set_default_stock_check()
{
	var title = '출고처리 기본값 설정';
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

function check_barcode(obj){
	var event=window.event;
	if(event.keyCode=="13"){
		var barcode_obj = $(obj);
		var str_shipping_seq = barcode_obj.attr('name');
		str_shipping_seq = str_shipping_seq.replace("barcode[", "");
		var shipping_seq = str_shipping_seq.replace("]", "");

		var str_check_ea = 'barcode_ea['+shipping_seq+']['+barcode_obj.val()+']';
		var check_ea_objs = $("input[name='"+str_check_ea+"']");

		barcode_obj.val('');

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
		return false;
	}

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

	check_ea_obj.val(check_ea);
	barcode_obj.val('');

}

function reset_barcode_ea(obj)
{
	var export_ea_obj = $(obj);
	var check_ea_obj = export_ea_obj.closest("td").next().find("input");
	var export_ea = num(export_ea_obj.val());
	var check_ea = num(check_ea_obj.val());

	if( export_ea_obj.val() > 0 ){
	}

	check_ea_obj.val(0);
}

function export_sumit()
{
	if( ! get_checked_input()  ){
		alert(chk_export_msg);
		return false;
	}
	loadingStart();
	document.order_export.submit();
}

function export_sumit_for_status55(status)
{
	if( ! get_checked_input()  ){
		if( status=='75' ){
			alert(chk_save_msg);
		}else{
			alert(chk_export_msg);
		}
		return false;
	}

	loadingStart();
	$("input[name='status']").val( status );
	document.order_export.submit();
}

function export_popup()
{
	if( ! get_checked_input() ){
		alert(chk_export_msg);
		return false;
	}
	$("input[name='each_export_code']").val('');
	var top = ( $(window).scrollTop() + ($(window).height() - $("#export_layer").height()) / 2 );
	$("#export_layer").css("top",top);
	$("#export_layer").show();
	$("#export_layer_overlay").show();
}

function close_export_popup()
{
	$("#export_layer").hide();
	$("#export_layer_overlay").hide();
}

function export_each_popup(each_export_code){
	$("input[name='each_export_code']").val( each_export_code );
	$("#export_layer").show(); 
	$("#export_layer_overlay").show(); 	
}

function export_each_submit(each_export_code){
	$("input[name='each_export_code']").val( each_export_code );
	document.order_export.submit();
}

function export_each_submit_for_status55(each_export_code,status){
	$("input[name='status']").val( status );
	$("input[name='each_export_code']").val( each_export_code );
	document.order_export.submit();
}

function batch_status_popup(mode,str_export_code,ticket_export_cnt,result_obj)
{
	var data_str  = "codes="+str_export_code+"&mode="+mode;
	var title = '';
	if( result_obj.req_cnt ){
		data_str += "&req_cnt="+result_obj.req_cnt;
		data_str += "&err_cnt="+result_obj.err_cnt;
		data_str += "&export_result_msg="+result_obj.export_result_msg;
		data_str += "&err_export_code="+result_obj.err_export_code;
	}else{
		data_str += "&ticket="+ticket_export_cnt;
		data_str += "&cnt_export_request_45="+result_obj.cnt_export_request_45;
		data_str += "&cnt_export_result_goods_45="+result_obj.cnt_export_result_goods_45;
		data_str += "&cnt_export_error_45="+result_obj.cnt_export_error_45;
		data_str += "&cnt_export_request_55="+result_obj.cnt_export_request_55;
		data_str += "&cnt_export_result_coupon_55="+result_obj.cnt_export_result_coupon_55;
		data_str += "&cnt_export_result_goods_55="+result_obj.cnt_export_result_goods_55;
		data_str += "&cnt_export_error_55="+result_obj.cnt_export_error_55;
		data_str += "&export_result_msg="+result_obj.export_result_msg;
	}
	data_str	+= "&market_mode=" + result_obj.market_mode;

	$.ajax({
		type: "post",
		url: "../export/batch_status_popup",
		data: data_str,
		success: function(result){
			if	(result){
				$("#batch_status_popup_layer").empty().append(result);
				if( result_obj.market_mode == 'y') title = '일괄송장전송처리';
				else title = '일괄출고처리';
				openDialog(title, "batch_status_popup_layer", {"width":"930","height":"250","close":function(){document.location.reload();}});
			}
		}
	});
}

function view_market_export_fail(){
	openDialog("실패사유", "market_export_fail", {"width":"700","height":"150","show" : "fade","hide" : "fade"});
}

function view_market_export_code(){
	openDialog("일괄송장전송실패내역", "market_export_code", {"width":"700","height":"408","show" : "fade","hide" : "fade"});
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

function excoupon_use_btn(obj){
	var btnobj = $(obj);
	$.ajax({
		type: "post",
		url: "../export/coupon_use",
		data: "order_seq="+btnobj.attr('order_seq'),
		success: function(result){
			if	(result){
				$("#coupon_use_lay").html(result);
				openDialog("티켓사용 확인 / 티켓번호 재발송 <span class='desc'></span>", "coupon_use_lay", {"width":"1000","height":"700"});
			}
		}
	});
}

function on_delivery_layer(obj)
{
	var btnobj = $(obj);
	btnobj.find("div").removeClass("hide");
}

function out_delivery_layer(obj)
{
	var btnobj = $(obj);
	btnobj.find("div").addClass("hide");
}

function set_date_export(start,end){
	$("input[name='regist_date[]']").eq(0).val(start);
	$("input[name='regist_date[]']").eq(1).val(end);
}

// 굿스플로 송장번호 안내팝업 :: 2015-07-02 lwh
function gf_invoice_call(export_code){
	if(export_code == 'all'){
		$("input[name='gf_mode']").val(export_code);
		$("input[name='gf_export_code']").val('');
	}else{
		$("input[name='gf_mode']").val('each');
		$("input[name='gf_export_code']").val(export_code);
		var delivery_number = $("input[name='delivery_number["+export_code+"]']").val();
		if(delivery_number){
			alert('운송장 번호가 이미 존재합니다.\n재발급을 원하시면 초기화 후 진행하세요.');
			return;
		}
	}

	var userBrower = $.browser;
	var ieVersion = userBrower.version;
	/* #24847 2018-12-17 ycg 굿스플로 버전 업데이트에 따른 브라우저 버전 체크 변경 - 시작  */
	var userBrower = $.browser;
	var ieVersion = userBrower.version;
	if(userBrower.msie && ieVersion > 11.0 || userBrower.mozilla || userBrower.opera || userBrower.safari){
		openDialog("(굿)운송장받기/출력", "goodsflow_popup_layer", {"width":"500","height":"400","close":function(){gf_reset();}});
	}else{
		alert('Internet Explorer 11 미만의 버전에서는 사용이 불가하오니, 타 브라우저 또는 Internet Explorer 버전 업데이트 후 이용해주시기 바랍니다.');
		return false;
	}
	/* #24847 2018-12-17 ycg 굿스플로 버전 업데이트에 따른 브라우저 버전 체크 변경 - 종료  */
}
// 굿스플로 송장번호 받기 :: 2015-07-07 lwh
function gf_call(){
	var frm_obj	= $("form#goods_export");
	frm_obj.attr('action','./gf_export_call');
	frm_obj.attr('target','export_frame');
	frm_obj.submit();
}
// 굿스플로 form 리셋 :: 2015-07-07 lwh
function gf_reset(){
	setTimeout(function(){
		$("input[name='gf_mode']").val('');
		$("input[name='gf_export_code']").val('');
		$("form#goods_export").attr('action','../export_process/batch_status');
	},100);
}
// 굿스플로 송장번호 리셋 :: 2015-07-09 lwh
function reset_delnumber(export_code){
	$("#gf_chk_reset").show();
	$("input[name='delivery_number["+export_code+"]']").val('');
}

function check_all(obj)
{
	var bobj = $(obj);
	var chk = false;
	if( bobj.attr('checked') ){
		chk = true;
	}
	$(".check_export_code").each(function(){
		$(this).attr('checked',chk);
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
	$("input[name='export_code[]']").each(function(tidx){
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
	return $("input[name='export_code[]']:checked").length;
}

function invoice_link(export_code){
	var company_obj		= $("select[name='delivery_company["+export_code+"]'] option:selected");
	var number_obj	= $("input[name='delivery_number["+export_code+"]']");
	var url			= company_obj.attr('url');
	var company = company_obj.attr('value');
	var number		= number_obj.val();
    if(number != "") number = number.replace(/[^0-9a-zA-Z]/g, '');
	if( url && number ){
		url += number;
		window.open(url);
	}else{
		alert('운송장번호가 올바르지 않습니다.');
		number_obj.focus();
	}

}

function order_print()
{
	if( ! get_checked_input() ){
		alert('인쇄할 주문이 없습니다.');
		return false;
	}
	var url = "../order/order_prints?ordarr=";
	$("input.check_export_code:checked").each(function(){
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
	var url = "../export/export_prints?export=";
	$("input.check_export_code:checked").each(function(){
		url += $(this).val()+"|";
	});
	window.open(url, '', 'width=850px,height=800px,toolbar=no,location=no,resizable=yes,scrollbars=yes');
}

function reset_search_form()
{
	var obj = $('table.search-form-table, .table_search');
	obj.find('select, textarea, input[type=text]').val('');
	obj.find('input:checkbox').removeAttr('checked').removeAttr('selected');

	var chk_except = false;
	obj.find('input:text, input:hidden').each(function() {
		chk_except = false;
		if (this.name != '') {
			if (!chk_except) $(this).val('');
		}
	});
	$('.search_type_text').hide();
}

// 기본설정적용
function set_default_search_form()
{	
	var objTd = $("form[name='search_default_frm'] table.info-table-style tr td.its-td");	
	var export_default_date_field = objTd.find("select[name='export_default_date_field'] option:selected").val(); // 기본 기간 설정
	var export_default_period = objTd.find("input[name='export_default_period']:checked").val(); // 기간 설정
	if( export_default_period == '-1 day' ){
		$("input[name='regist_date[]']").eq(0).val(today);
		$("input[name='regist_date[]']").eq(1).val(today);
	}else if( export_default_period == '-1 week' ){
		$("input[name='regist_date[]']").eq(0).val(week);
		$("input[name='regist_date[]']").eq(1).val(today);
		
	}else if( export_default_period == '-1 mon' ){
		$("input[name='regist_date[]']").eq(0).val(mon);
		$("input[name='regist_date[]']").eq(1).val(today);
		
	}else if( export_default_period == '-3 mon' ){
		$("input[name='regist_date[]']").eq(0).val(mon3);
		$("input[name='regist_date[]']").eq(1).val(today);
	}else if( export_default_period == 'all' ){
		$("input[name='regist_date[]']").eq(0).val("");
		$("input[name='regist_date[]']").eq(1).val("");
	}
	$("select[name='date_field'] option[value='"+export_default_date_field+"']").attr("selected",true);
	
	objTd.find("input[name='export_default_status[]']:checked").each(function(){ // 상태
		$("input[name='status'][value='"+$(this).val()+"']").attr("checked",true);
	});

}

function close_search_form()
{
	$("div.search-form-container").hide();
	$("div#btn-close-search").hide();
	$("div#btn-open-search").show();

}

function open_search_form()
{
	$("div.search-form-container").show();
	$("div#btn-close-search").show();
	$("div#btn-open-search").hide();
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

function check_export_shipping_method(seq)
{
	var selectObj = $("select#export_shipping_method_"+seq);
	var val = selectObj.find("option:selected").val();
	if( val == 'quick' || val == 'direct' ){
		selectObj.next("div").addClass("hide");
	}else{
		selectObj.next("div").removeClass("hide");
	}
}