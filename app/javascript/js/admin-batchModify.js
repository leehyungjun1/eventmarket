$(document).ready(function() {

	$("#ifchkAll").click(function(){
		if($(this).attr("checked")){
			$(".ifchk").attr("checked",true).change();
		}else{
			$(".ifchk").attr("checked",false).change();
		}
	});

	// 체크박스 색상
	$("input[type='checkbox'][name='goods_seq[]']").on('change',function(){
		var goods_seq	= this.value;
		var rowspan		= $(this).closest('td').attr('rowspan');
		var trIdx		= $(this).closest('tbody').find('tr').index($(this).closest('tr'));

		if(this.checked){
			$(this).closest('tr').children('td').addClass('checked-tr-background');
			if	(rowspan > 1){
				for (var i = 1; i < rowspan; i++){
					trIdx++;
					$(this).closest('tbody').find('tr').eq(trIdx).children('td').addClass('checked-tr-background');
				}
			}
			$('#option_' + goods_seq + ' tr').children('td').addClass('checked-tr-background');
		}else{
			$(this).closest('tr').children('td').removeClass('checked-tr-background');
			if	(rowspan > 1){
				for (var i = 1; i < rowspan; i++){
					trIdx++;
					$(this).closest('tbody').find('tr').eq(trIdx).children('td').removeClass('checked-tr-background');
				}
			}
			$('#option_' + goods_seq + ' tr').children('td').removeClass('checked-tr-background');
		}
	}).change();
	
	// 회원등급혜택 엑셀 일괄업데이트 추가 :: 2019-09-25 pjw
	$("#update_excel").bind("click",function(){
		openDialog("엑셀업로드", "membersale_excel_uplaod_dialog", {"width":420,"height":230});
	});

	$('#btn_excel_process').bind("click",function(){
		if($('#membersale_excel_file').val() == ''){
			openDialogAlert('업데이트할 엑셀파일을 선택하여 주시기 바랍니다.',400,150,function(){},'');
			return false;
		}

		$('#membersaleFrm').submit();
	});

	$("#update_goods").on("click",function(){
		batchmodify_submit(this);
	});
	
	//원본이미지 삭제여부
	$("#imagedelete").bind("click",function(){ 
		if( $("#imagedelete").is(':checked') ) {
			openDialogConfirm('원본이미지를 삭제하겠습니까?<br/>삭제된 이미지는 복구 되지 않습니다!',500,160,function(){
				$("#imagedelete").attr("checked",true);
			},function(){
				$("#imagedelete").removeAttr("checked");
			});
		}
	}); 

	$("select[name^='modify_list']").bind("change",function(){
		if( $(this).find("option:selected").val() != "all"){
			$("input.chk").attr('disabled',false);
			$("#chkAll").attr('disabled',false);
		}else{
			$("input.chk").attr('disabled',true);
			$("#chkAll").attr('disabled',true);
		}
	});

	// 바로열기
	$(".txt-direct-open").on("click", function(){optionViewOnOff(this);});

	// 모두 열기/닫기
	$(".btn_open_all").on("click", function(){optionViewAllOnOff(this);});

	// 일괄적용
	$(".applyAllBtn").on("click", function(){applyAll(this)});

	// 옵션일괄적용
	$(".applyOptionsBtn").on("click", function(){applyOptions(this)});

	// 조건/직접 선택
	$("#ifdirect").on("change", function(){applyMode()})

	$(".batchmodify_select").on("change", function(){applyModify()});

	$('select[name="batch_reserve_policy"]').on('change', function(){
		if (this.value == 'shop')
			$('.batch_reserve').attr('disabled', true);
		else
			$('.batch_reserve').attr('disabled', false);

	});	
	// 재고 정책 선택시
	$("select[name='batch_runout_type'], select.runout_type").on("change",function(){ chk_runout_type(); });

	// 재고연동판매 체크
	$("select[name='batch_runout_policy'], select.runout_policy").on("change",function(){ chk_runout_policy(); });


	//상품승인 -> 미승인시 판매중지자동
	$(".batch_provider_status, .provider_status").on("change",function(){
		if( $(this).val() != '1' ) {
			openDialogAlert("'미승인'처리되며<br />상품 상태는 '판매중지'가 됩니다.",400,150,function(){},'');
			$(this).parent().parent().find(".input_goods_status option[value='unsold']").attr("selected",true);
		}else{
		}
	});

	// 직접일때만 상품상태 -> 미승인 상품을 정상 처리시
	if(mode == 'status') {
		$(".goods_status").on("change",function(){
			if( $(this).closest("tr").find(".provider_status").val() == '0' && $(this).val() != 'unsold' ) {//미승인시

				openDialogAlert("'미승인' 상품이며, 먼저 '승인' 처리해 주세요.",400,150,function(){},'');
				$(this).find("option[value='unsold']").attr("selected",true);
			}
		});
	}

	$("select[name='batch_hscode_selector']").combobox()
	.on('change', function(){
		var selectedHSCode	= $(this).val();
		if( selectedHSCode != 0 ){
			$("input[name='hscode_common']").val(selectedHSCode);
		}else{
			$("input[name='hscode_common']").val('');
		}
	});


	chk_runout_type();
	chk_runout_policy();

	// 기본코드 자동생성 #goodsetc
	$(".btn_all_code").on("click", function(){
		var chk_flag = true;
		$("input[name='goods_seq[]']").each(function(){
			if($(this).is(":checked")){
				chk_flag		= false;
				var trObj		= $(this).closest(".list-row")
				var goods_seq	= trObj.attr("goods_seq");
				if($(this).val() == goods_seq){
					trObj.find(".real_code").val(trObj.find(".hidden_code").val());
				}
			}
		});
		if(chk_flag)	alert("일괄적용할 상품을 선택해 주세요.");
	});	

	$('.reserve_policy').on('change', function(){
		var goods_seq	= $(this).attr('goods_seq');
		reservePolicySet(goods_seq);
	});

	$('select[name="all_reserve_policy"]').on('change', function() {
		var ctrl			= (this.value == 'shop') ? true : false;
		$(this).siblings('input[name="all_reserve_rate"], select[name="all_reserve_unit"]').attr('disabled', ctrl);
	});

	$('.reserve_rate,.reserve_unit').on('change', function(){
		var $selector		= $(this).closest("td");
		var chkUse			= true;

		if($selector.find(".reserve_unit option:selected").val() != 'percent'){ chkUse = false; }
		
		if ($selector.find(".reserve_rate").val() > 100 && chkUse == true) {
			$selector.find(".reserve_rate").val(0);
			alert('마일리지는 100%를 넘을 수 없습니다.');
			$selector.find(".reserve_rate").focus();
		}
	});

	$('.commission_rate').on('blur', function(){

		if (this.value > 100) {
			this.value=0;
			alert('수수료값은 100%를 넘을 수 없습니다.');
			this.focus();
		}
	});

	$('.su_commission_rate').on('blur', function(){
		var commission_type	= $(this).siblings('.su_commission_type').val();
		if (commission_type == 'SUCO' && this.value > 100) {
			this.value=0;
			alert('공급율값은 100%를 넘을 수 없습니다.');
			this.focus();
		}
	});

	$('.su_commission_type').on('change', function(){
		var $commissionRate	= $(this).siblings('.su_commission_rate');
		if (this.value == 'SUCO' && $commissionRate.val() > 100) {
			$commissionRate.val(0)
			alert('공급율값은 100%를 넘을 수 없습니다.');
			$commissionRate.focus();
		}
	});	

	$("select[name='batch_info_select']").combobox().on('change',function(){
		if( $(this).val() != 0 ){
			$("input[name='common_info_seq']").val($(this).val());
		}else{
			$("input[name='common_info_seq']").val('');
		}
	});

	$("input[name='multidiscount'").on('change', function() {
		if($(this).val() == 'use') {
			$('#multiDiscountDialog').show();
		} else {
			$('#multiDiscountDialog').hide();
		}
	});

	$("select[name='possible_pay_type']").change(function(){
		if	($(this).val() == 'goods'){
			$('table.pay-table').find("input[name='possible_pay[]']").attr('disabled', false);
		}else{
			$('table.pay-table').find("input[name='possible_pay[]']").attr('disabled', true).attr('checked', false);
		}
	});

	// 위탁배송 본사그룹 호출
	$("#shipping_grp_sel").on('change',function(){
		if($(this).val() == 'trust_ship'){
			$("#shipping_grp_sub").show();
			get_ship_sub_grp();
		}else{
			$("#shipping_grp_sub").hide();
		}
	});

	reset_ship_grp();
	get_ship_grp($("input[name='sel_provider_seq']").val());

	$("input[name='remove_watermark']").on('click',function() {
		$("[class^=watermark_design_]").hide();
		$(".watermark_design_"+$(this).val()).show();
	});

	$("input[name='watermark_type']").on('click',function() {
		$(".watermark_type").hide();
		$(".watermark_type_"+$(this).val()).show();
	});

	/* 아이콘 개별삭제 */
	$(".iconViewTable button.iconDel").live("click",function(){
		if(!confirm("정말로 아이콘을 삭제하시겠습니까?")) return;
		var goods_seq = $(this).attr('goods_seq');
		var icon_seq = $(this).attr('icon_seq');
		$.ajax({
			type: "get",
			url: "../goods_process/goods_icon_del",
			data: "icon_seq="+icon_seq+"&goods_seq="+goods_seq,
			success: function(result){
				if(result){
					if( $("#iconViewTable_"+goods_seq+" tbody tr").length > 0) {
						$("#iconViewTable_"+goods_seq+"_"+icon_seq).remove();
					}
					alert('이 상품의 아이콘을 정상적으로 삭제하였습니다.');
				}else{
					alert('상품의 아이콘 삭제가 실패하였습니다.');
					return false;
				}
			}
		});
	});

	$(".txt-direct-open").on('changeOptionLay', function(){
		var goods_seq	= $(this).attr('goods_seq');

		if ($(this).hasClass('opened') === false) {
			//닫기
			$('#option_' + goods_seq).find("[defalult_option='y']").each(function(){
				var targetName	= this.name.replace(/^detail_/, '');
				$('[name="' + targetName + '"]').val(this.value);
			});
		}
	});

	/* relation start */
	$("#btn_all_bigdata").on("click",function(){
		var cnt = $("input:checkbox[name='goods_seq[]']:checked").length;
		if(cnt<1){
			alert("일괄적용할 상품을 선택해 주세요.");
			return false;
		}
		change_all_input_bigdata('batch_bigdata_criteria','bigdata_criteria');
		setCriteriaDescription_bigdata('goodsview', true);
	});

	//전체선택
	$("input[name='chkall']").on("click",function(){
		$("#"+$(this).val()+" table .chk").prop("checked",$(this).is(":checked"));
	});


	$("#btn_all_relation").on("click",function(){
		var cnt = $("input:checkbox[name='goods_seq[]']:checked").length;
		if(cnt<1){
			alert("일괄적용할 상품을 선택해 주세요.");
			return false;
		}
		change_all_select('batch_relation_type','all_relation');
		change_all_input_bigdata('batch_relation_criteria','relation_criteria');
		change_all_input_bigdata('batch_auto_condition_use','auto_condition_use');
		change_all_html('relationGoods','relationGoods','relation');
		setCriteriaDescription_upgrade('goodsview', true);
		setCriteriaDescription_bigdata('goodsview', true);
	});

	$("#btn_all_relation_seller").on("click",function(){
		var cnt = $("input:checkbox[name='goods_seq[]']:checked").length;
		if(cnt<1){
			alert("일괄적용할 상품을 선택해 주세요.");
			return false;
		}
		change_all_select('batch_relation_seller_type','all_relation_seller');
		change_all_input_bigdata('batch_relation_seller_criteria','relation_seller_criteria');
		change_all_html('relationSellerGoods','relationSellerGoods','relation_seller');
		setCriteriaDescription_upgrade('goodsview', true);
		setCriteriaDescription_bigdata('goodsview', true);
	});

	$('.relation_type').change(function(){
		type = $(this).val();
		that = $(this).closest('td');
		btn_that = $(this).closest('td');
		batch = $(this).attr('attr');
		if	(type == 'AUTO' || type == 'AUTO_SUB'){
			that.find('.relationContainer').show();
			that.find('.relationGoodsSelectContainer').hide();
			btn_that.find('.relation_auto_btn').show();
			btn_that.find('.relation_select_btn').hide();
			if	(batch == 'batch')
				$(this).closest('tr').find('.displayCriteriaType').attr('auto_type',type.toLowerCase());
			else
				$(this).closest('td').next('td').find('.displayCriteriaType').attr('auto_type',type.toLowerCase());
			setCriteriaDescription_upgrade('goodsview',true);
			setCriteriaDescription_bigdata('goodsview',true);
		}else{
			that.find('.relationContainer').hide();
			that.find('.relationGoodsSelectContainer').show();
			btn_that.find('.relation_auto_btn').hide();
			btn_that.find('.relation_select_btn').show();
		}
	}).change();
	/* relation end */
});
// document.ready end

function change_all_input_bigdata(input_name,class_name)
{
	var obj_val = $("input[name='"+input_name+"']").val();
	$("."+class_name).each(function(){
		var update_obj = $(this);
		if (update_obj.closest("tr").find("input[name='goods_seq[]']").attr("checked") == 'checked' ){
			$(this).val(obj_val);
		}
	});
	return true;
}

function change_all_select(input_name,class_name)
{
	var obj_val = $("input[name='"+input_name+"']:checked").val();
	$("."+class_name).each(function(){
		if( $(this).parent().parent().find("input[name='goods_seq[]']").attr("checked") == 'checked' ){
			$(this).find("option[value='"+obj_val+"']").attr('selected',true);
			$(this).trigger('change');
		}
	});
}

// 관심상품/인기상품 수동 선택 후 일괄 적용 처리 시 
// 수동선택한 상품의 필드명에 업데이트할 상품번호를 추가해 준다.
function change_all_html(input_id,class_name,type)
{
	var cloneObj = $("#"+input_id).clone();
	cloneObj.find("colgroup").remove();
	cloneObj.find("tr").each(function(){
		var goodsSeq 	= $(this).find("td input[name='"+input_id+"[]']");
		if(window.Firstmall.Config.Environment.serviceLimit.H_AD == true){
			// 역순으로 삭제
			$(this).find("td").eq(3).remove();	// 가격
			$(this).find("td").eq(1).remove();	// 입점사명
			$(this).find("td").eq(0).remove();	// checkbox
		}else{
			$(this).find("td").eq(2).remove();	// 가격
			$(this).find("td").eq(0).remove();	// checkbox
		}
		$(this).find("td").eq(0).append(goodsSeq);
	});
	objHtml = cloneObj.html();
	$(".relationTable input[name='goods_seq[]']").each(function(){
		if( $(this).attr("checked") == 'checked' ){
			var objHtmlTmp = objHtml.split(class_name+'[]').join(class_name+"_"+$(this).val()+"[]");
			$(this).closest("tr").find("."+class_name+" .goods_list").html(objHtmlTmp);
		}
	});
}


function reset_ship_grp(){
	$("#shipping_grp_sel > option").remove();
	$("#shipping_grp_sel").append('<option value="">선택</option>');
}

function get_ship_grp(provider_seq){
	$.ajax({
		type: 'get',
		url: '../popup/shipping_grp_ajax',
		data: 'provider_seq='+provider_seq,
		dataType: 'json',
		success: function(res) {
			$.each(res, function(idx, data){
				$("#shipping_grp_sel").append('<option value="' + res[idx]['shipping_group_seq'] + '">' + res[idx]['shipping_group_name'] + '</option>');
			});
			if	(provider_seq > 1){
				$("#shipping_grp_sel").append('<option value="trust_ship">본사위탁배송</option>');
			}
		}
	});
}

function get_ship_sub_grp(){
	$.ajax({
		type: 'get',
		url: '../popup/shipping_grp_ajax',
		data: 'provider_seq=1',
		dataType: 'json',
		success: function(res) {
			$.each(res, function(idx, data){
				$("#shipping_grp_sub").append('<option value="' + res[idx]['shipping_group_seq'] + '">' + res[idx]['shipping_group_name'] + '</option>');
			});
		}
	});
}

// 최소/최대 구매수량 설정 form 변경
function chgPurchaseType(obj){
	if ($(obj).val() == 'limit'){
		$(obj).closest('td').find('input:last').attr('disabled', false);
	}else{
		$(obj).closest('td').find('input:last').attr('disabled', true);
	}
}

// 최소/최대 구매수량 값체크 변경
function chkPurchaseEa(obj){
	var chkStatus	= $(obj).attr('name');
	var minEa		= parseInt($("input[name='minPurchaseEa']").val(), 10);
	var maxEa		= parseInt($("input[name='maxPurchaseEa']").val(), 10);
	if	(minEa < 2){
		if	(chkStatus == 'minPurchaseEa'){
			openDialogAlert('최소구매수량은 2이상 입력 가능합니다.', 400, 170, function(){});
		}
		minEa	= 2;
		$("input[name='minPurchaseEa']").val('2');
	}
	if	(minEa > maxEa){
		if	(chkStatus == 'maxPurchaseEa'){
			openDialogAlert('최대구매수량은 최소구매수량 보다 큰 수만 가능합니다.', 400, 170, function(){});
		}
		maxEa	= parseInt(minEa, 10) + 1;
		$("input[name='maxPurchaseEa']").val(maxEa);
	}
}

/* 일괄 검증 */
function reserve_policy_update($applyObj) {
	console.log("reserve_policy_update >>> ");
	var goods_seq	= (typeof $applyObj.attr('goods_seq') == 'undefined') ? 'all' : $applyObj.attr('goods_seq');

	if (goods_seq == 'all') {
		var reserve_policy	= $('select[name="all_reserve_policy"]').val();
		var reserve_unit	= $('select[name="all_reserve_unit"]').val();
		var reserve_rate	= $('input[name="all_reserve_rate"]').val();
		var $reserveValue	= $('input[name="all_reserve_rate"]');
	} else {
		var reserve_policy	= $('select[name="reserve_policy[' + goods_seq + ']"]').val();
		var reserve_unit	= $('#option_' + goods_seq + ' .reserve_unit').val();
		var reserve_rate	= $('#option_' + goods_seq + ' .reserve_rate').val();
		var $reserveValue	= $('#option_' + goods_seq + ' .reserve_rate');
	}

	if (reserve_policy == 'goods' && reserve_unit == 'percent' && reserve_rate > 100)
		return '마일리지는 100%을 넘을 수 없습니다.';

	if (goods_seq == 'all') {
		$("input:checkbox[name='goods_seq[]']:checked").each (function() {
			$('select[name="reserve_policy[' + this.value + ']').val(reserve_policy);
			reservePolicySet(this.value);
		});
	}

	return true;
}

function commission_rate_check($applyObj) {
	var goods_seq		= (typeof $applyObj.attr('goods_seq') == 'undefined') ? 'all' : $applyObj.attr('goods_seq');
	var apply_type		= $applyObj.attr('apply_type');

	switch (apply_type) {
		case	'all_su_commission' :
			var commission_type		= $('select[name="all_su_commission_type"]').val();
			var commission_rate		= $('input[name="all_su_commission_rate"]').val();
			var $commissionRate		= $('input[name="all_su_commission_rate"]');
			break;

		case	'all_commission_rate' :
			var commission_type		= 'SACO';
			var commission_rate		= $('input[name="all_commission_rate"]').val();
			var $commissionRate		= $('input[name="all_commission_rate"]');
			break;

		case	'su_commission_' + goods_seq :
			var commission_type		= $('.su_commission_' + goods_seq + '_value[apply_target="su_commission_type"]').val();
			var commission_rate		= $('.su_commission_' + goods_seq + '_value[apply_target="su_commission_rate"]').val();
			var $commissionRate		= $('.su_commission_' + goods_seq + '_value[apply_target="su_commission_rate"]');
			break;

		case	'commission_rate_' + goods_seq :
			var commission_type		= 'SACO';
			var commission_rate		= $('.commission_rate_' + goods_seq + '_value[apply_target="commission_rate"]').val();
			var $commissionRate		= $('.commission_rate_' + goods_seq + '_value[apply_target="commission_rate"]');
			break;
	}

	console.log(commission_type + " : " + commission_rate);
	if ((commission_type == 'SACO' || commission_type == 'SUCO') && commission_rate > 100) {
		$commissionRate.val(0);
		$commissionRate.focus();
		var typeText		= (commission_type == 'SACO') ? '수수료' : '공급율';
		return typeText + '값은 100%를 넘을 수 없습니다.';
	}

	return true;
}
// # status
function all_runout_type(){
	chk_runout_type();
	chk_runout_policy();
	return true;
}

// HSCODE 관리 페이지로 이동
function goHSCodepage(){
	if	($("input[name='hscode_common']").val() && $("input[name='hscode_name']").val()){
		window.open('../goods/hscode_setting?hscode=' + $("input[name='hscode_common']").val());
	}else{
		window.open('../goods/hscode_setting');
	}
}

function batchmodify_submit(el) {
	
	var $applyObj		= $(el);
	var checkFunction	= $applyObj.attr('check_function');
	var chk_val			= false;

	$(".chk").each(function(){
		if( $(this).attr("checked") == "checked" ){
			chk_val = true;
		}
	});

	if($('.batch_update_item').length > 0 && $('.batch_update_item:checked').length == 0){
		openDialogAlert('업데이트할 항목을 선택하여 주시기 바랍니다.',400,150,function(){},'');
		return false;
	}

	if($('.batch_icon').length > 0 && $('.batch_icon:checked').length == 0){
		openDialogAlert('업데이트할 아이콘을 선택하여 주시기 바랍니다.',400,150,function(){},'');
		return false;
	}

	if ($('select[name="modify_list"]').val() != 'all' && !chk_val) {
		openDialogAlert('일괄적용할 상품을 선택해 주세요.',400,150,function(){},'');
		return false;
	}

	if (typeof checkFunction == 'string' && checkFunction!="") {
		var callCheck	= new Function('$applyObj', 'return ' + checkFunction + '($applyObj);');
		var checkMsg	= callCheck($applyObj);

		if (checkMsg !== true) { return false; }
	}
	doAjaxPermit();
}

function doAjaxPermit() {
	// 역마진 확인 Ajax :: 2016-03-24 lwh
	$("#goods_permit_lay").html('');
	$.ajax({
		type: "post",
		url: "../goods/goods_batch_permit",
		data: $("form#goodsBatchUpdateForm").serialize(),
		success: function(html){
			$("#goods_permit_lay").append(html);
			openDialog("일괄업데이트 확인", "goods_permit_lay", {"width":"500","show" : "fade","hide" : "fade"});
		}
	});
}

function batchmodify_selector() {
//	alert(mode);
	$(".batchmodify_select option").each(function() {
		if($(this).val() == mode ) {
			$(this).attr('selected','selected');
			if($(this).parents('select').attr('id') == "batchmodify_selector_if") {
				$("#batchmodify_selector_if").show();
				$("#batchmodify_selector_direct").hide();
			} else {
				$("#batchmodify_selector_if").hide();
				$("#batchmodify_selector_direct").show();
			}
		}
	});
}

function applyMode() {
	if($("#ifdirect option:selected").val() == 'if') {
		$("#batchmodify_selector_if").show();
		$("#batchmodify_selector_direct").hide();

		$("#batchmodify_selector_if option:eq(0)").attr('selected','selected');
	} else {
		$("#batchmodify_selector_if").hide();
		$("#batchmodify_selector_direct").show();

		$("#batchmodify_selector_direct option:eq(0)").attr('selected','selected');
	}
	//applyModify();
}

function applyModify() {
	var modify_name = "";
	if($("#ifdirect option:selected").val() == 'if') {
		modify_name = $("#batchmodify_selector_if option:selected").val();
	} else {
		modify_name = $("#batchmodify_selector_direct option:selected").val();
	}	
	$("#batchmodify_selector").val(modify_name);
	go_link_tab();
}

// SEARCH FOLDER
function showSearch(){
	if($("#goods_search_form").css('display')=='none'){
		$("#goods_search_form").show();
		$.cookie("goods_list_folder", "folded");
	}else{
		$("#goods_search_form").hide();
		$.cookie("goods_list_folder", "unfolded");
	}
}

// 일괄업데이트 실행
function batch_goods_save_submit(){

	var f				= $("form#goodsBatchUpdateForm")[0];
	var actionMode		= $('[name="batchmodify_selector"]', f).val();
	var actionUrl		= "../goods_process/batch_goods_modify";
	var search_val		= "";

	Object.keys(scObj).forEach(function(k){
		if(k != 'cancel_type'){
			if(k == "goodsStatus"){
				$.each(scObj[k], function(k2,v2){
					search_val += "<input type='hidden' name='"+k+"["+k2+"]' value='"+v2+"'>";
				});
			}else{
				search_val += "<input type='hidden' name='"+k+"' value='"+scObj[k]+"'>";
			}
		}
	});

	$("form#goodsBatchUpdateForm").append(search_val);

	switch (actionMode) {
		case	'' :
			actionUrl	= "../goods_process/batch_modify";
			break;
	}
	f.method	= "post";
	f.target	= "actionFrame";
	f.action	= actionUrl;
	f.submit();
}


function goodsView(seq){
	$("input[name='no']").val(seq);
	var search = location.search;
	search = search.substring(1,search.length);
	$("input[name='query_string']").val(search);
	$("form[name='goodsForm']").attr('action','regist');
	$("form[name='goodsForm']").submit();
}

function go_link_tab()
{
	var now_mode = $("#goodsForm > input[name='mode']").val();
	var confirm_flag = false;
	jsbatchmodifyParse = JSON.parse(jsbatchmodify);
	$.each(jsbatchmodifyParse.direct, function(idx,val){
		if(now_mode == idx) {
			confirm_flag = true;
		}
	});
	var mode = $("#batchmodify_selector").val();
	var url = "?page=1&" + freezeObj.queryString + "&mode="+mode;//amp;
	url = url.replace("&mode={_GET.mode}","");
	if( confirm_flag === true ){
		openDialogConfirm('현재 화면에서 나가시겠습니까?<br/>우측 상단의 ‘업데이트’ 버튼으로 저장하지 않은 데이터는 저장되지 않습니다!',500,240,function(){
			document.location.href = url;
		},function(){});
	}else{
		document.location.href = url;
	}
}


function change_all_input(input_name,update_class,msg_yn)
{
	var cnt = $("input:checkbox[name='goods_seq[]']:checked").length;
	if(cnt<1){
		if( msg_yn ) alert("일괄적용할 상품을 선택해 주세요.");
		return;
	}else{

		var tagname				= $("."+input_name)[0].tagName;
		var obj_val				= "";
		var input_type			= "";
		var obj_val_checked		= new Array();
		var old_update_obj_name = "";

		if(tagname == "INPUT"){
			input_type	= $("."+input_name).attr("type").toLowerCase();
			if(input_type == "text"){
				obj_val		= $("input[name='"+input_name+"']").val();
			}else if(input_type == "checkbox"){
				obj_val_tmp	= $("input[name='"+input_name+"[]']");
				var k = 0;
				obj_val_tmp.each(function(){
					if($(this).is(":checked")){
						obj_val_checked[k] = $(this).val();
						k++;
					}
				});
			}
		}else{
			obj_val		= $("select[name='"+input_name+"'] option:selected").val();
		}

		// LIST 일괄 적용
		$("."+update_class).each(function(e){

			var update_obj = $(this);
			if( update_obj.closest("tr").find("input[name='goods_seq[]']").attr("checked") == 'checked' ){
				if(tagname == "INPUT"){
					//TEXT INPUT 처리
					if(input_type == "text"){
						update_obj.val(obj_val);
					//CHECKBOX INPUT 처리
					}else if(input_type == "checkbox"){
						
						var update_obj_name = update_obj.attr("name");
						if(update_obj_name != old_update_obj_name){
							$("input[name='"+update_obj_name+"']").prop("checked",false);
							if(update_class == "input_color_pick"){
								$("input[name='"+update_obj_name+"']").parent().attr("class","");
							}
						}

						old_update_obj_name = update_obj_name;

						for(var i=0; i < obj_val_checked.length; i++){

							if(obj_val_checked[i] == update_obj.val()){
								update_obj.prop("checked",true);
								if(update_class == "input_color_pick"){
									update_obj.parent().attr("class","active");
								}
							}
						}
					}
				}else{
					//SELECT 처리
					update_obj.find("option[value='"+obj_val+"']").attr('selected',true);
					
					if(input_name == "batch_runout_type"){

						change_all_input_in_span('batch_able_stock_limit','input_able_stock_limit');
						change_all_select_in_span('batch_runout_policy','input_runout_policy');

						chk_runout_type();
						chk_runout_policy();
					}
				}
			}
		});
	}
}

// -------------------------------------------------------------
// _batch_modify_status 사용 시작
//재고 정책 체크
function chk_runout_type()
{
	$("select.runout_type").each(function(e){
		if( $(this).find("option:selected").val() == 'goods' ){
			$(this).parent().find(".runout_span").removeClass("hide");
			$(this).parent().find(".runout_span2").addClass("hide");
		}else{
			$(this).parent().find(".runout_span").addClass("hide");
			$(this).parent().find(".runout_span2").removeClass("hide");
		}
	});
}

//재고연동판매 체크
function chk_runout_policy()
{
	$("select.runout_policy").each(function(){
		var stock_input = $(this).parents('div.runout_layout').find('input');
		var selectVal 	= $(this).find("option:selected").val();
		console.log("selectVal : " + selectVal);
		if( selectVal == 'ableStock' ){		// 가용재고 연동
			stock_input.removeClass("hide");
			stock_input.prop("disabled", false);
		}else {	// 재고무관,재고연동
			stock_input.val(0);
			stock_input.addClass("hide");
			stock_input.prop("disabled", true);
		}
	});
}

function change_all_input_in_span(input_name,class_name)
{
	var obj_val = $("input[name='"+input_name+"']").val();
	$("."+class_name).each(function(){
		if( $(this).parent().parent().parent().find("input[name='goods_seq[]']").attr("checked") == 'checked' ){
			$(this).val(obj_val);
		}
		var str_name = $(this).attr('name');
		if( str_name.match(/detail/) ){
			var obj_chk = $(this).parent().parent().parent().parent().parent().parent().parent().parent().prev();
			if( obj_chk.find("input[name='goods_seq[]']").attr("checked") == 'checked' ){
				$(this).val(obj_val);
			}
		}
	});
}
function change_all_select_in_span(input_name,class_name)
{
	var obj_val = $("select[name='"+input_name+"'] option:selected").val();
	$("."+class_name).each(function(){
		if( $(this).closest("tr").find("input[name='goods_seq[]']").attr("checked") == 'checked' ){
			$(this).find("option[value='"+obj_val+"']").attr('selected',true);
		}
		var str_name = $(this).attr('name');
		if( str_name.match(/detail/) ){
			var obj_chk = $(this).parent().parent().parent().parent().parent().parent().parent().parent().prev();
			if( obj_chk.find("input[name='goods_seq[]']").attr("checked") == 'checked' ){
				$(this).find("option[value='"+obj_val+"']").attr('selected',true);
			}
		}
	});
}

function set_table_dialog(id, content) {
	$("#"+id).append(content);
}

// 조건-판매정보 업데이트 시 :: 개인통관고유번호 & 선물하기 동시 사용 시 경고문구
function chk_ifstatus() {
	var check_option_international_shipping_status_yn = $("input[name='batch_option_international_shipping_status_yn']").is(':checked');
	var check_present_use_yn = $("input[name='batch_present_use_yn']").is(':checked');

	if (check_option_international_shipping_status_yn == false || check_present_use_yn == false) {
		return true;
	}

	if ($("select[name='batch_option_international_shipping_status'] option:selected").val() == "Y"
		&& $("select[name='batch_present_use'] option:selected").val() == "1") {
		openDialogAlert('선물하기 상품은 국내배송 및 택배수령만 가능합니다. <br/>해외 구매 대행 상품의 경우 쇼핑몰 페이지에서 선물하기 아이콘이 적용되지 않습니다.', 550, 250, function () { doAjaxPermit(); }, '');
		return false;
	}
	return true;
}

// 직접-판매정보 업데이트 시 :: 개인통관고유번호 & 선물하기 동시 사용 시 경고문구
function chk_status() {
	var goodsChecked = goods_seq = 0;

	$("input[name='goods_seq[]']:checked").each(function(){
		goods_seq = $(this).val();
		if ($("select[name='option_international_shipping_status[" + goods_seq + "]'] option:selected").val() == "Y"
			&& $("select[name='present_use[" + goods_seq + "]'] option:selected").val() == "1") {
			goodsChecked++;
		}
	});

	// 1개라도 조건에 부합하는 경우
	if (goodsChecked > 0) {
		openDialogAlert('선물하기 상품은 국내배송 및 택배수령만 가능합니다. <br/>해외 구매 대행 상품의 경우 쇼핑몰 페이지에서 선물하기 아이콘이 적용되지 않습니다.', 550, 250, function () { doAjaxPermit(); }, '');
		return false;
	}
	return true;
}

function chk_multidiscount(){

	var setting_chk			= false;
	var ck_multidiscount	= $("input[name='batch_multidiscount']").is(":checked");
	var ck_min_limit		= $("input[name='batch_min_limit']").is(":checked");
	var ck_max_limit		= $("input[name='batch_max_limit']").is(":checked");

	if(ck_multidiscount != true && ck_min_limit != true && ck_max_limit != true){
		openDialogAlert('업데이트 하실 내용을 먼저 선택하세요..',350,150,function(){},'');
		return false;
	}

	var discountMaxOverQty		= $("input[name='discountMaxOverQty']").length;
	var discountMaxAmount		= $("input[name='discountMaxAmount']").length;
	if(discountMaxOverQty > 0 && discountMaxAmount > 0){
		setting_chk = true;
	}

	var discountOverQty		= $("input[name='discountOverQty[]'").length;
	var discountUnderQty	= $("input[name='discountUnderQty[]'").length;
	var discountAmount		= $("input[name='discountAmount[]'").length;

	if((discountOverQty == 1 || (discountOverQty > 1 && discountUnderQty > 0)) && discountAmount > 0){
		setting_chk = true;
	}
	if(ck_multidiscount == true && setting_chk != true){
		openDialogAlert('대량구매 혜택을 먼저 설정하세요.',350,150,function(){},'');
		return false;
	}
	
	if(ck_min_limit == true && $("input[name='minPurchaseLimit']").val() == ""){
		openDialogAlert('최소구매수량을 먼저 설정하세요.',350,150,function(){},'');
		return false;
	}
	
	if(ck_max_limit == true && $("input[name='maxPurchaseLimit']").val() == ""){
		openDialogAlert('최대구매수량을 먼저 설정하세요.',350,150,function(){},'');
		return false;
	}


	return true;
}
function chk_commoninfo(){
	if($("select[name='batch_info_select'] option:selected").val() == "" || $("input[name='batch_commoninfo_yn']").is(":checked") == false){
		openDialogAlert('업데이트할 공통 정보를 먼저 선택해 주세요.',400,150,function(){},'');
		return false;
	}
	return true;
}
function chk_hscode(){
	if($("input[name='hscode_name']").val() == '' || $("input[name='batch_hscode_yn']").is(":checked") == false){
		openDialogAlert('업데이트할 HSCODE를 먼저 선택 해주세요.',400,150,function(){},'');
		return false;
	}
	return true;
}
function chk_epdata(){
	if($("#feed_ship_type").val() == 'E' && $("input[name='feed_pay_type']:checked").val() == 'fixed' && $("input[name='feed_std_fixed']").val() == ''){
		openDialogAlert('유료 타입은 배송비금액이 필수입니다.',400,150,function(){$("input[name='feed_std_fixed']").focus();},'');
		return false;
	}

	if($("#feed_ship_type").val() == 'E' && $("input[name='feed_pay_type']:checked").val() == 'postpay' && $("input[name='feed_std_postpay']").val() == ''){
		openDialogAlert('유료 타입은 배송비금액이 필수입니다.',400,150,function(){$("input[name='feed_std_postpay']").focus();},'');
		return false;
	}

	return true;
}

function chk_goods(){
	if($("input[name='mode']").val() == "goods"){
		var goodsNameNull = 0;
		$("input[name='goods_seq[]']:checked").each(function(){
			var goods_seq = $(this).val();
			if($("input[name='goods_name["+goods_seq+"]']").val().trim() == ""){
				goodsNameNull++;
			}
		});
		if(goodsNameNull > 0){
			openDialogAlert('업데이트할 상품명을 입력 해주세요.',400,150,function(){},'');
			return false;
		}
	}else{
		if($("input[name='batch_goods_name']").val() == '' && $("input[name='batch_goods_name_yn']").is(":checked") == true){
			openDialogAlert('업데이트할 상품명을 입력 해주세요.',400,150,function(){},'');
			return false;
		}
	}
	return true;
}
// 재고정책에 따라 상품상태가 변경된 경우 MSG 띄우기.
function popup_stock_modify_msg(data) {

	var rtn_json 	= $.parseJSON(data); // 데이터를 JSON으로 파싱
	var normal_cnt 	= 0;
	var runout_cnt 	= 0;
	if(rtn_json['msg_show']) {
		var msg_show		= rtn_json['msg_show'].replace(/\\n/g, '\n');
		var gname			= rtn_json['gname'];
		var out_gname		= rtn_json['out_gname'];
		var normal_cnt		= rtn_json['normal_cnt'];
		var runout_cnt		= rtn_json['runout_cnt'];
		var tot_cnt			= rtn_json['tot_cnt'];

		if(!normal_cnt)	normal_cnt = 0;
		if(!runout_cnt) runout_cnt = 0;

		if(tot_cnt == 0){
			openDialogAlert('상태 변경 가능한 상품이 없습니다.',400,180,'','');
			return false;
		}

		$("#dialog_confirm_msg").html(msg_show);
		openDialog("알림 <span class='desc'>알림 정보를 표시합니다.</span>", "dialog_confirm", {"width":480,"height":300});
	}

	$("#btn_normal_gname span").html("품절⇒정상<br/>변경 상품<br/>("+ normal_cnt+"건)");
	$("#btn_runout_gname span").html("정상⇒품절<br/>변경 상품<br/>("+ runout_cnt+"건)");

	$("#btn_normal_gname").bind("click",function(){
		if($(this).attr("mode") != "ifstatus" && $(this).attr("mode") != "ifgoodsetc"){
			if (gname) {
				openDialog("알림 <span class='desc'>알림 정보를 표시합니다.</span>", "dialog_confirm_normal", {"width":480,"height":"auto"});
			} else { 
				openDialogAlert('해당 건이 없습니다.',400,180,'','');
			}
		}
		return;
	});

	$("#btn_runout_gname").bind("click",function(){
		if($(this).attr("mode") != "ifstatus" && $(this).attr("mode") != "ifgoodsetc"){
			if (out_gname) {
				openDialog("알림 <span class='desc'>알림 정보를 표시합니다.</span>", "dialog_confirm_runout", {"width":480,"height":"auto"});
			} else { 
				openDialogAlert('해당 건이 없습니다.',400,180,'','');
			}
		}
		return;
	});

	var mode = $("#btn_normal_gname").attr('mode');
	if( mode == "ifstatus" || mode == "ifgoodsetc" ) {
		$("#btn_normal_gname").css("cursor","default");
		$("#btn_runout_gname").css("cursor","default");
	}

	$("#btn_pop_close").on("click",function(){
		$("#dialog_confirm").dialog( "close" );
		parent.location.reload();
	});

	$("#btn_pop_normal_close").on("click",function(){
		$("#dialog_confirm_normal").dialog( "close" );
	});

	$("#btn_pop_runout_close").on("click",function(){
		$("#dialog_confirm_runout").dialog( "close" );
	});

}
// _batch_modify_status 사용 종료
// -------------------------------------------------------------


function optionViewOnOff(thisObj) {
	
	var $btnObj		= $(thisObj);
	var ctrlType	= ($btnObj.hasClass('opened') === false) ? 'open' : 'close';
	var nowGoodsSeq	= $btnObj.attr('goods_seq');

	var $optionLay	= $('#option_' + nowGoodsSeq);

	// 열기
	if (ctrlType == 'open') {
		$optionLay.find('input,select').attr('disabled', false);
		$optionLay.show();
		$btnObj.addClass('opened');
		$btnObj.html('닫기');
		$('.default_option[goods_seq="' + nowGoodsSeq + '"]').attr('disabled', true);
		$('.applyOptionsBtn[goods_seq="' + nowGoodsSeq + '"]').parent().show();
		$('.openAddOptionSet[goods_seq="' + nowGoodsSeq + '"]').show();
		$('.closeAddOptionSet[goods_seq="' + nowGoodsSeq + '"]').hide();
		reservePolicySet(goods_seq);
	} else {
		$optionLay.find('input,select').attr('disabled', true);
		$optionLay.hide();
		$btnObj.removeClass('opened');
		$btnObj.html('열기');
		$('.default_option[goods_seq="' + nowGoodsSeq + '"]').attr('disabled', false);
		$('.applyOptionsBtn[goods_seq="' + nowGoodsSeq + '"]').parent().hide();
		$('.openAddOptionSet[goods_seq="' + nowGoodsSeq + '"]').hide();
		$('.closeAddOptionSet[goods_seq="' + nowGoodsSeq + '"]').show();
	}

	$('.txt-direct-open[goods_seq="' + nowGoodsSeq + '"]').trigger('changeOptionLay');
}

// #price
function reservePolicySet(goods_seq) {
	var	reserve_policy	= $('select[name="reserve_policy[' + goods_seq + ']').val();
	var ctrl			= (reserve_policy == 'shop') ? true : false;

	$('select[name="reserve_policy[' + goods_seq + ']"]').siblings('.reserve_rate, .reserve_unit').attr('disabled', ctrl);
	$('#option_' + goods_seq + '  .reserve_rate, #option_' + goods_seq + '  .reserve_unit').attr('disabled', ctrl);
}


function optionViewAllOnOff(thisObj) {
	var $btnObj		= $(thisObj);
	var ctrlType	= ($btnObj.hasClass('opened') === false) ? 'open' : 'close';
	var $optionLay	= $('.optionLay');
	
	if (ctrlType == 'open') {
		$btnObj.html('모두 닫기');
		$optionLay.find('input,select').attr('disabled', false);
		$optionLay.show();
		$(".txt-direct-open").addClass('opened');
		$(".txt-direct-open").html('닫기');
		$btnObj.addClass('opened');
		$('.default_option.option_use').attr('disabled', true);
		$('.applyOptionsBtn').parent().show();
		$('.openAddOptionSet').show();
		$('.closeAddOptionSet').hide();
	} else {
		$btnObj.html('모두 열기');
		$optionLay.find('input,select').attr('disabled', true);
		$optionLay.hide();
		$btnObj.removeClass('opened');
		$('.default_option.option_use').attr('disabled', false);
		$('.txt-direct-open').removeClass('opened');
		$(".txt-direct-open").html('열기');
		$('.applyOptionsBtn').parent().hide();
		$('.openAddOptionSet').hide();
		$('.closeAddOptionSet').show();
	}

	$('.txt-direct-open').trigger('changeOptionLay');

}


//전체상품적용
function applyAll(thisObj) {

	var $applyObj		= $(thisObj);
	var applyType		= $applyObj.attr('apply_type');
	var checkFunction	= $applyObj.attr('check_function');

	if ($("input:checkbox[name='goods_seq[]']:checked").length < 1) {
		alert("일괄적용할 상품을 선택해 주세요.");
		return false;
	}
	
	if (typeof checkFunction == 'string') {
		var callCheck	= new Function('$applyObj', 'return ' + checkFunction + '($applyObj);');
		var checkMsg	= callCheck($applyObj);

		if (checkMsg !== true) {
			alert(checkMsg);
			return false;
		}
	}

	$('.' + applyType + '_value:not(:disabled)').each(function(e) {
		target		= $(this).attr('apply_target');
		newValue	= this.value;

		// 선택된 상품의 '재고에 따른 판매' 방식 일괄 적용
		$("input:checkbox[name='goods_seq[]']:checked").each (function() {
			$applyTbodyObj	= $('tr[goods_seq="' + this.value + '"]')
			applyAction($applyTbodyObj, target, newValue);
		});
	});

	var doneFunction	= $applyObj.attr('done_function');

	if (typeof doneFunction == 'string') {
		var callDone	= new Function('$applyObj', 'return ' + doneFunction + '($applyObj);');
		var checkMsg	= callDone($applyObj);
	}


}

function all_color_pick($applyObj){

	var applyType			= $applyObj.attr('apply_type');
	var all_checked_obj		= new Array();
	var target				= "";
	var k					= 0;

	$('.' + applyType + '_value').each(function() {
		if($(this).is(":checked")){
			all_checked_obj[k] = $(this).val();
			k++;
		}
		target		= $(this).attr('apply_target');
	});

	$("input:checkbox[name='goods_seq[]']:checked").each (function(e) {

		var $applyTbodyObj	= $('tr[goods_seq="' + this.value + '"]');
		var obj				= $applyTbodyObj.find("input[name='" + target + "["+this.value+"][]']");

		//초기화
		obj.prop("checked",false);
		obj.parent().attr("class","");

		obj.each(function(){

			for(var i=0; i < all_checked_obj.length; i++){

				if(all_checked_obj[i] == this.value){

					$(this).prop("checked",true);
					$(this).parent().attr("class","active");
				}
			}
		});
	});

	return true;

}

//전체옵션적용
function applyOptions(thisObj) {
	var $applyObj		= $(thisObj);
	var goodsSeq		= $applyObj.attr('goods_seq');
	var applyType		= $applyObj.attr('apply_type');
	var checkFunction	= $applyObj.attr('check_function');
	
	if (typeof checkFunction == 'string') {
		var callCheck	= new Function('$applyObj', 'return ' + checkFunction + '($applyObj);');
		var checkMsg	= callCheck($applyObj);

		if (checkMsg !== true) {
			alert(checkMsg);
			return false;
		}
	}
	
	$('.' + applyType + '_value:not(:disabled)').each(function() {
		target			= $(this).attr('apply_target');
		newValue		= this.value;
		$applyTbodyObj	= $('#option_' + goodsSeq);
		applyAction($applyTbodyObj, target, newValue);
	});

	var doneFunction	= $applyObj.attr('done_function');
	
	if (typeof doneFunction == 'string') {
		var callDone	= new Function('$applyObj', 'return ' + doneFunction + '($applyObj);');
		var checkMsg	= callDone($applyObj);
	}
}

function applyAction ($applyTbodyObj, target, newValue) {
	$applyTbodyObj.find('.org_' + target + ':not(:disabled)').each(function(){
		this.value	= $(this).siblings('.' + target).val();
	});

	if($applyTbodyObj.find('.' + target ).attr("type") != "checkbox" && $applyTbodyObj.find('.' + target ).attr("disabled") != true){
		$applyTbodyObj.find('.' + target ).val(newValue);
	}
}

// EP 마케팅 배송구분 설정
function feed_ship_chk(obj,mode){
	var feed_ship_type	= $(obj).val();
	$(obj).closest('.feed-ship-lay').find('div.fst-lay').hide();
	$(obj).closest('.feed-ship-lay').find('div.feed_ship_type_' + feed_ship_type).show();
	if(mode == 'summary' && feed_ship_type!= 'E') {
		$(obj).closest('.feed-ship-lay').find('div.fst-lay').hide();
	}
}

// EP 마켓팅 배송데이터 설정 :: 2017-02-22 lwh
function ep_market_set(obj){
	var feed_pay_type = $(obj).val();

	if (feed_pay_type == 'postpay')	{ 
		$(obj).closest('.feed-ship-lay').find('.feed_add_txt').attr('disabled', true);
		$(obj).closest('.feed-ship-lay').find('.feed_add_txt').val('');
		$(obj).closest('.feed-ship-lay').find('.feed_add_txt_span').hide();
		$(obj).closest('.feed-ship-lay').find('.feed_std_fixed').attr('disabled', true);
		$(obj).closest('.feed-ship-lay').find('.feed_std_fixed').val('');
		$(obj).closest('.feed-ship-lay').find('.feed_std_fixed_span').hide();
		$(obj).closest('.feed-ship-lay').find('.feed_std_postpay').attr('disabled', false);
		$(obj).closest('.feed-ship-lay').find('.feed_std_postpay_span').show();
	} else if (feed_pay_type == 'fixed'){							
		$(obj).closest('.feed-ship-lay').find('.feed_add_txt').attr('disabled', false);
		$(obj).closest('.feed-ship-lay').find('.feed_add_txt').val('');
		$(obj).closest('.feed-ship-lay').find('.feed_add_txt_span').show();
		$(obj).closest('.feed-ship-lay').find('.feed_std_postpay').attr('disabled', true);
		$(obj).closest('.feed-ship-lay').find('.feed_std_postpay').val('');
		$(obj).closest('.feed-ship-lay').find('.feed_std_postpay_span').hide();
		$(obj).closest('.feed-ship-lay').find('.feed_std_fixed').attr('disabled', false);
		$(obj).closest('.feed-ship-lay').find('.feed_std_fixed_span').show();
	} else {							
		$(obj).closest('.feed-ship-lay').find('.feed_add_txt').attr('disabled', false);
		$(obj).closest('.feed-ship-lay').find('.feed_add_txt_span').show();
		$(obj).closest('.feed-ship-lay').find('.feed_std_postpay').attr('disabled', true);
		$(obj).closest('.feed-ship-lay').find('.feed_std_postpay').val('');
		$(obj).closest('.feed-ship-lay').find('.feed_std_postpay_span').hide();
		$(obj).closest('.feed-ship-lay').find('.feed_std_fixed').attr('disabled', true);
		$(obj).closest('.feed-ship-lay').find('.feed_std_fixed').val('');
		$(obj).closest('.feed-ship-lay').find('.feed_std_fixed_span').hide();
	}
}

// EP 마케팅 배송문구 일괄 적용 후 변경 처리
function chgFeedShipForm(obj){
	var feed_ship_wrap	= $(obj).closest('.feed-ship-lay');
	var feed_ship_type	= feed_ship_wrap.find('select.all_feed_ship_type_value').val();
	var feed_std_fixed	= feed_ship_wrap.find('input.feed_std_fixed').val();
	var feed_std_postpay= feed_ship_wrap.find('input.feed_std_postpay').val();
	var feed_add_txt	= feed_ship_wrap.find('input.feed_add_txt').val();
	var feed_pay_type	= 'free';
	if	(feed_ship_type == 'E'){
		feed_pay_type	= feed_ship_wrap.find("select.all_feed_pay_type").val();
	}

	$("input:checkbox[name='goods_seq[]']:checked").each (function() {
		$(this).closest('tr').find('select.feed_ship_type').change();

		if	(feed_ship_type == 'E'){
			$(this).closest('tr').find("select.feed_pay_type").val(feed_pay_type).trigger('change');

			// disable pass 처리로 인해 한번 더 처리
			$(this).closest('tr').find('input.feed_std_fixed:not(:disabled)').val(feed_std_fixed);
			$(this).closest('tr').find('input.feed_std_postpay:not(:disabled)').val(feed_std_postpay);
			$(this).closest('tr').find('input.feed_add_txt:not(:disabled)').val(feed_add_txt);
		} else {
			$(this).closest('tr').find("input.feed_pay_type[value='free']").attr("checked", true);
			
			$(this).closest('tr').find('input.feed_std_fixed').val('');
			$(this).closest('tr').find('input.feed_std_postpay').val('');
			$(this).closest('tr').find('input.feed_add_txt').val('');
		}
	});
}

// EP 사용여부 일괄 체크 처리
function chkFeedStatusChecked(obj){
	var chkStatus	= false;
	if	($('input.all_feed_status_value').attr('checked')){
		chkStatus	= true;
	}
	$("input:checkbox[name='goods_seq[]']:checked").each (function(){
		$(this).closest('tr').find('input.chk_feed_status').attr('checked', chkStatus);
		chgFeedStatus($(this).closest('tr').find('input.chk_feed_status'));
	});
}

// EP 사용여부 체크박스 체크에 따른 실제값 update
function chgFeedStatus(obj){
	if	($(obj).attr('checked'))	$(obj).closest('td').find('input.feed_status').val('Y');
	else							$(obj).closest('td').find('input.feed_status').val('N');
}