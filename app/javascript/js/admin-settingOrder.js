/* 사유추가 */
function addReason(reasonCode, ctype){
	var obj="";
	if(reasonCode == "120"){
		obj = '<tr class="move"><td><img src="/admin/skin/default/images/common/icon_move.png"></td><td>변심</td><td class="left"><input type="hidden" name="codecd'+ctype+'[]" value="120"><input type="text" name="reason'+ctype+'[]" size="65" value=""></td><td><button type="button" class="btn_minus" onclick="delReason(this);"></button></td></tr>';
	}else if(reasonCode == "210"){
		obj = '<tr class="move"><td><img src="/admin/skin/default/images/common/icon_move.png"></td><td>하자</td><td class="left"><input type="hidden" name="codecd'+ctype+'[]" value="210"><input type="text" name="reason'+ctype+'[]" size="65" value=""></td><td><button type="button" class="btn_minus" onclick="delReason(this);"></button></td></tr>';
	}else if(reasonCode == "310"){
		obj = '<tr class="move"><td><img src="/admin/skin/default/images/common/icon_move.png"></td><td>오배송</td><td class="left"><input type="hidden" name="codecd'+ctype+'[]" value="310"><input type="text" name="reason'+ctype+'[]" size="65" value=""></td><td><button type="button" class="btn_minus" onclick="delReason(this);"></button></td></tr>';
	}

	$("#reasonTable"+ctype+" tbody").append(obj);
	$("#reasonTable"+ctype+" tbody").find(".mess").remove();

	$(".tablednd").tableDnD({onDragClass: "dragRow"});
}

function delReason(obj){	
	var len = $(obj).closest('tbody').find('tr').length;
	var id = $(obj).closest('table').attr("id");
	var tbody = $(obj).closest('tbody');

	if(len==1){
		
		if(id == 'reasonTablecoupon')
		{
			tbody.append("<tr class='mess'><td colspan='4' >등록된 취소 사유가 없습니다.</td></tr>");
		}else{
			tbody.append("<tr class='mess'><td colspan='4' >등록된 반품, 교환 사유가 없습니다.</td></tr>");
		}
	}

	$(obj).closest('tr').remove();

	

}

$(document).ready(function() {

	$(".tablednd").tableDnD({onDragClass: "dragRow"});

	if(gl_runout){
		$("input[name='runout'][value='"+gl_runout+"']").attr('checked',true);
		gl_runout == "ableStock" ? $('.ableStockDetail').show() : $('.ableStockDetail').hide();		
	}

	change_ableStock()

	if(gl_cartDuration){
		$("select[name='cartDuration'] option[value='"+gl_cartDuration+"']").attr('selected',true);
	}
	if(gl_cancelDuration){
		$("select[name='cancelDuration'] option[value='"+gl_cancelDuration+"']").attr('selected',true);
	}
	if( gl_ableStockStep ){
		$("input[name='ableStockStep'][value='"+gl_ableStockStep+"']").attr('checked',true);
	}
	if( gl_refundDuration ){
		$("select[name='refundDuration'] option[value='"+gl_refundDuration+"']").attr('selected',true);
	}

	$("input[name='ableStockStep']").change(function(){
		if($(this).is(":checked")){
			$(".ableStockStepImg").hide();
			$(".ableStockStep"+$(this).val()).show();
		}
	}).change();

	//일반 과세사업자 > 세금계산서설정
	$("#biztype_tax").click(function(){
		$("#taxuse").val('1');
		$(".taxuselay").show();
		$(".taxuselaynone").hide();
	});

	//간이/면세사업자 세금계산서불가
	$("#biztype_taxexe").click(function(){
		$("#taxuse").val('0');
		$(".taxuselay").hide();
		$(".taxuselaynone").show();

	});

	//현금영수증
	$("input[name='cashreceiptuse']").click(function(){
		if( $(this).val() == 2 ){//현금영수증만 사용시
			$("#cashreceiptonlylay").show();
		}else{
			$("#cashreceiptonlylay").hide();
		}
	});
	
	$("#hiworks_request").click(function(){
		$.get('hiworks_request', function(data) {
			$('#popup').html(data);
			openDialog("하이웍스 신청 <span class='desc'>&nbsp;</span>", "popup", {"width":"800","height":"630"});
		});
	});

	if( gl_cashreceiptuse ){
		$("input[name='cashreceiptuse'][value='"+gl_cashreceiptuse+"']").attr('checked',true);
	}
	
	if( gl_biztype ){
		$("input[name='biztype'][value='"+gl_biztype+"']").attr('checked',true);
	}

	if( gl_taxuse ){
		$("input[name='taxuse'][value='"+gl_taxuse+"']").attr('checked',true);
	}

	if( gl_hiworks_use ){
		$("input[name='hiworks_use'][value='"+gl_hiworks_use+"']").attr('checked',true);
	}
	
	if( gl_cashreceiptpg ){
		$("select[name='cashreceiptpg'] option[value='"+gl_cashreceiptpg+"']").attr('selected',true);
	}
	
	if( gl_cashreceipt_date ){
		$("select[name='cashreceipt_date'] option[value='"+gl_cashreceipt_date+"']").attr('selected',true);
	}

	if( gl_cancelDisabledStep35 ){
		$("select[name='cancelDisabledStep35'][value='"+gl_cancelDisabledStep35+"']").attr('selected',true);
	}

	if(	gl_provider_do_order_done == 'N' ){
		$("input[name='provider_do_order_done'][value='N']").attr('checked',true);
	}else{
		$("input[name='provider_do_order_done'][value='Y']").attr('checked',true);
	}

	if(	gl_not_match_goods_order == 'n' ){
		$("input[name='not_match_goods_order'][value='n']").attr('checked',true);
	}else{
		$("input[name='not_match_goods_order'][value='y']").attr('checked',true);
	}

	/* 저장시 action조정 */
	if( gl_pgCompany ){
		$("#now_operating").html(" : "+gl_pgCompany+" 사용");
		$("#now_operating2").html(" : "+gl_pgCompany+" 사용");
	}

	$("select[name='cartDuration']").bind("change",function(){
		change_setting_msg();
	});

	$("select[name='cancelDuration']").bind("change",function(){
		change_setting_msg();
	});

	$("select[name='refundDuration']").bind("change",function(){
		change_setting_msg();
	});

	if( gl_autocancel ){
		$("input[name='autocancel'][value='"+gl_autocancel+"']").attr('checked',true);		
	}

	change_autocancel();

	if( gl_export_err_handling ){
		$("input[name='export_err_handling'][value='"+gl_export_err_handling+"']").attr('checked',true);
	}

	change_setting_msg();

	/* 상품상태별 이미지 세팅*/
	$("#goodsStatusImage").live("click",function(e){
		$('#popGoodsStatusImage').empty();

		$.ajax({
			type: "get",
			url: "../goods/goods_status_images_setting",
			success: function(result){
				$("#popGoodsStatusImage").html(result);
			}
		});
		openDialog("상품 상태별 이미지 세팅", "popGoodsStatusImage", {"width":"900","height":"450","show" : "fade","hide" : "fade"});
		e.preventDefault();
		return false;
	});
	changeFileStyle();

	/* 선택된 상품상태별이미지 변경창 출력 */
	$(".goodsStatusImage").live("click",function(){
		var codecd = $(this).attr('codecd');
		$("input[name='goodsStatusImageCode']").val(codecd);
		$(".nowGoodsStatusImage").html("<img src='"+$(this).attr('src')+"' />");
		closeDialog("popGoodsStatusImageChoice");
		openDialog("이미지 변경", "popGoodsStatusImageChoice", {"width":"570","height":"250","show" : "fade","hide" : "fade"});
	});

	// 차감금액 계산 최종결제금액 계산 세팅여부		
	if( gl_cutting_sale_use ){
		$("input[name='cutting_sale_use']").eq(0).attr('checked',true);	
	}else{
		$("input[name='cutting_sale_use']").eq(1).attr('checked',true);
	}

	change_cutting_sale();
	
	if( gl_cutting_sale_price ){
		$("select[name='cutting_sale_price'] option[value='"+gl_cutting_sale_price+"']").attr('selected',true);
	}
	
	if( gl_cutting_sale_action ){
		$("select[name='cutting_sale_action'] option[value='"+gl_cutting_sale_action+"']").attr('selected',true);
	}
	
	if( gl_cutting_settle_use ){
		$("input[name='cutting_settle_use']").attr('checked',true);
	}
	
	if(gl_cutting_settle_price){
		$("select[name='cutting_settle_price'] option[value='"+gl_cutting_settle_price+"']").attr('selected',true);
	}
	
	if(gl_cutting_settle_action){
		$("select[name='cutting_settle_action'] option[value='"+gl_cutting_settle_action+"']").attr('selected',true);
	}

	/* 맞교환,반품/환불,마일리지포인트지급 단계 설정 */
	$("select[name='save_term'] option[value='"+gl_save_term+"']").attr("selected",true);
	if( gl_save_type ){
		$("select[name='save_type'] option[value='"+gl_save_type+"']").attr("selected",true);
	}	

	$("input[name='buy_confirm_use'][value='" + gl_buy_confirm_use + "']").attr("checked",true);		
	
	if( $("input[name='buy_confirm_use']:checked").val() == '1' ){
		$("#select_buy_confirm_use").addClass("hide");
	}else{
		$("#msg_buy_confirm_use").addClass("hide");
	}
	
	$("input[name='buy_confirm_use']").change(function(){
		var buy_confirm_use = $("input[name='buy_confirm_use']:checked").val();
		if( buy_confirm_use == '1' ){
			openDialogConfirm("구매확정 사용으로 변경 저장 시 다시 미사용으로 변경할 수 없습니다.",'400','160',function(){},function(){
				$("input[name='buy_confirm_use'][value='0']").attr("checked", true);
			},{'yesMsg':'확인','noMsg':'취소'});
		}
		check_buy_confirm();
	});

	check_buy_confirm();
	
	$("select[name='save_term']").change(function(){
		if(!$(this).is(":disabled")){
			$("span.save_term").html(comma(num($(this).val())));
			$("select[name='save_term']").val($(this).val());
			$("#f_layer").html($(this).val());
		}
	}).change();

	$("input.use_setting").bind("click",function(){
		check_use_setting();
	});
	check_use_setting();

	$("input[name='runout']").bind("change",function(){
		check_runout();

		if($(this).is(":checked")){
			if($(this).val()=='stock'){
				$(".a_layer").html('재고 기준');
			}else if($(this).val()=='ableStock'){
				$(".a_layer").html('가용재고 기준');
			}else if($(this).val()=='unlimited'){
				$(".a_layer").html('재고 무관');
			}
		}
	});
	$("input[name='ableStockLimit'").bind("blur",function(){
		check_runout();
	});

	$("button#runout_info_button").bind("click",function(){
		$.get('../popup/information?template_path=runout', function(data) {
			$('#popup').html(data);
		});
		openDialog("[안내] 재고 Q&A", "popup", {"width":"1024","height":"770"});
	});
	check_runout();

	$("select[name='cancelDisabledStep35']").change(function(){
		var cancelDisabledStep35 = $("select[name='cancelDisabledStep35'] option:selected").val();
		//if(cancelDisabledStep35){
			$(".cancelDisabledStep35Img").hide();
			$("#cancelDisabledStep35_"+cancelDisabledStep35).show();
		//}

		if(cancelDisabledStep35 == 1){
			$('.cancel-disabled-step35').hide();
		}else{
			$('.cancel-disabled-step35').show();
		}
	}).change();

	$("input[name='export_err_handling']").change(function(){
		if($(this).is(":checked")){
			if($(this).val()=='ignore'){
				$(".export_err_handling_ignore").show();
			}else{
				$(".export_err_handling_ignore").hide();
			}
		}
	}).change();

	$("input[name='runout']").change(function(){
		change_ableStock();
	});

	$("input[name='cutting_sale_use']").change(function(){		
		change_cutting_sale()
	})

	$("input[name='autocancel']").change(function(){
		change_autocancel()
	});

	if(gl_servicelimit_h_fr){
		$(".GoodsCouponTable").hide();
	}

	if (gl_present_use == 'y') {
		$("input[name='present_use']").prop('checked', 'checked');
	}

	if (gl_present_seller_use == 'y') {
		$("input[name='present_seller_use']").prop('checked', 'checked');
	}
	$("input[name='present_use']").on('click', function(){
		order_present();
	});
	order_present();

	if( gl_separating_personal_info == 'y' ){
		$("input[name='separating_personal_info'][value='y']").attr('checked',true);
	}else{
		$("input[name='separating_personal_info'][value='n']").attr('checked',true);
	}

	$("input[name='separating_personal_info'][value='y']").bind("change",function(){
		openDialogConfirm('사용함 설정 시 다시 사용안함으로 변경 불가합니다.<br/>휴면/탈퇴 회원의 개인정보 분리 설정을 사용하시겠습니까?<br/>(개인정보 분리는 설정 다음날 새벽 일괄 적용됩니다.)', 480, 260, function(){}, function() {
			$("input[name='separating_personal_info'][value='n']").attr("checked", true);
		});
	});
});

/**
 * 선물하기 체크 및 disabled 처리
 * 올앳 사용중 disabled
 * 입점사 선물하기는 본사 선물하기 동의하에 사용 가능함
 */
function order_present() {
	if (gl_pgCompany == 'allat') {
		$("input[name='present_use']").prop('checked', '');
		$("input[name='present_seller_use']").prop('checked', '');
		$("input[name='present_use']").prop('disabled', 'disabled');
		$("input[name='present_seller_use']").prop('disabled', 'disabled');	
		return;
	}
	if ($("input[name='present_use']").is(":checked") === false) {
		$("input[name='present_seller_use']").prop('disabled', 'disabled');
		$("input[name='present_seller_use']").prop('checked', '');
	} else {
		$("input[name='present_seller_use']").prop('disabled','');
	}
}
function change_ableStock(){
	if($("input[name='runout']:checked").val()=='ableStock')
	{
		$('.ableStockDetail').show()
	}else{
		$('.ableStockDetail').hide()
	}
}

function change_autocancel(){
	if($("input[name='autocancel']:checked").val() == 'y' ){
		$('.autocancelDetail').show()
	}else{
		$('.autocancelDetail').hide()
	}

}

function change_cutting_sale(){
	if( $("input[name='cutting_sale_use']:checked").val() == 'none' ){
		$(".saleDetail").hide();
	}else{
		$(".saleDetail").show();
	}
}

function change_setting_msg(){
	$(".b_layer").html($("select[name='cartDuration']").val());
	$(".c_layer").html($("select[name='cancelDuration']").val());
	$("#f_layer").html($("select[name='save_term']").val());
}

// 차감금액 계산 최종결제금액 계산 세팅여부
function check_use_setting(){
	$("input.use_setting").each(function(){
		if( $(this).attr('checked') ){
			$(this).parent().next().attr('disabled',false);
		}else{
			$(this).parent().next().attr('disabled',true);
		}
	});
}

function check_runout()
{
	//$("table.stock-qa-table tr").removeClass("red");
	//$("input[name='runout']:checked").parent().parent().parent().addClass("red");
	//$("input[name='runout']:checked").parent().parent().parent().next().addClass("red");
	var ableStockLimit = parseInt($("input[name='ableStockLimit'").val())+1;
	$("#ableStockLimitMsg").html(ableStockLimit);

}

function check_buy_confirm(){
	var buy_confirm_use = $("input[name='buy_confirm_use']:checked").val();

	$(".buyConfirmUseImg").hide();
	if( buy_confirm_use == '1' ){
		$(".saveDetail1").show();
		$(".saveDetail2").hide();
		$(".buy_confirm0").hide();
		$(".buy_confirm1").show();
		$(".buy_confirm_rowspan1").attr('rowspan',15);
		$(".buy_confirm_rowspan2").attr('rowspan',7);
		$(".buy_confirm_rowspan3").attr('rowspan',2);
		$(".buyConfirmUselay").show();		
		$(".buy_confirm_use_con").show();
		$(".buy_confirm_unused_con").hide();
	}else{
		$(".saveDetail2").show();
		$(".saveDetail1").hide();
		$(".buy_confirm1").hide();
		$(".buy_confirm0").show();
		$(".buy_confirm_rowspan1").attr('rowspan',14);
		$(".buy_confirm_rowspan2").attr('rowspan',6);
		$(".buy_confirm_rowspan3").attr('rowspan',1);
		$(".buyConfirmUselay").hide();
		$(".buyConfirmUse0").show();
		$(".buy_confirm_use_con").hide();
		$(".buy_confirm_unused_con").show();
	}
}
