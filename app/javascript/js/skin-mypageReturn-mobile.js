function refund_method_layer_view(){
	var chk_ea_sum = 0;

	$("#order_return_container select[name='chk_ea[]']").not(":disabled").each(function(){
		chk_ea_sum += num($(this).val());
	});

	if(
		(jQuery.inArray(gl_orders_payment, ["card", "kakaomoney", "cellphone"]) !== -1 || gl_orders_pg == "payco" || gl_orders_pg == "naverpayment")
		&& gl_order_total_ea == chk_ea_sum.toString()
	) {
		$("#refund_method_layer").hide();
	}else{
		$("#refund_method_layer").show();
	}
}

// 반품/교환 배송비 결제 방법
function refund_ship_type_chg(){
	var refund_ship_type = $("#refund_ship_type option:selected").val();
	$(".refund_ship_account").hide();
	$(".refund_ship_minus").hide();
	if (refund_ship_type == 'A'){
		$(".refund_ship_account").show();
	}else if (refund_ship_type == 'M'){
		$(".refund_ship_minus").show();
	}
}

$(function(){
	$(".sub_page_tab td").click(function(){
		$("input[type='radio'],input[type='checkbox']",this).attr('checked',true).change();
	});
	$(".sub_page_tab").each(function(){
		$("td:first-child",this).click();
	});

	$("#order_return_container select[name='reason[]']").change(function(){
		var row = $(this).closest(".goods-info-lay, .suboption-lay");
		var reason_desc = row.find("select[name='reason[]'] option:selected").text();
		row.find("input[name='reason_desc[]']").val(reason_desc);
	});

	$("#order_return_container input[name='chk_seq[]']").change(function(){
		// disabled 상태면 checked 속성을 제거하고 false를 반환한다.
		if($(this).attr('disabled') === 'disabled') {
			$(this).removeAttr('checked');
			return false;
		}
		var row						= $(this).closest(".goods-info-lay, .suboption-lay");
		var idx						= $("#order_return_container select[name='chk_ea[]']").index(this);
		var chk_item_seq			= row.find("input[name='chk_item_seq[]']").val();
		var chk_option_seq			= row.find("input[name='chk_option_seq[]']").val();
		var chk_shipping_seq		= row.find("input[name='chk_shipping_seq[]']").val();
		var chk_individual_return	= row.find("input[name='chk_individual_return[]']").val();
		var pay_shiping_cost_arr	= new Array();

		// 사유 선택 여부 결정
		if($("#order_return_container input[name='chk_seq[]']:checked").length > 0){
			$("select[name='reason']").removeAttr("disabled");
			$("select[name='reason']").css('background','');
			$(".reason_ship_duty_area").show();
			// 반품/교환 배송비 View 계산
			$("#order_return_container input[name='chk_seq[]']:checked").not(":disabled").each(function (idx){
				var shipObj = $(this).closest('.shipping_group').find("input[name='pay_shiping_cost[]']");
				shipObj.attr('disabled',false);
				pay_shiping_cost_arr[shipObj.attr('shipping_seq')] = parseFloat(shipObj.val());
			});
			// 배열에 값이 없어 reduce 함수 오류 수정 :: 2019-08-13 rsh
			if( pay_shiping_cost_arr.length > 0 ) {
				var refund_ship_cost = pay_shiping_cost_arr.reduce(function (previous, current){
					return previous + current
				});
			} else {
				var refund_ship_cost = 0;
			}

			$("#refund_ship_cost").html(get_currency_price(refund_ship_cost,2));
		}else{
			$("select[name='reason']").attr('disabled',true);
			$("select[name='reason']").css('background','#ccc');
			$(".reason_ship_duty_area").hide();
		}
		$("select[name='reason']").change();

		// 추가옵션 선택할때
		if(row.find("input[name='chk_suboption_seq[]']").val()!='' && $(this).is(":checked")){
			if(chk_individual_return!='1'){ // 개별취소 안되도록 설정했을때
				// 필수옵션이 선택되어있지 않으면 에러
				var result = true;
				$("#order_return_container input[name='chk_item_seq[]'][value='"+chk_item_seq+"'][item_option_seq='"+chk_option_seq+"'][shipping_seq='"+chk_shipping_seq+"']").each(function(){
					if($(this).closest(".goods-info-lay, .suboption-lay").find("input[name='chk_suboption_seq[]']").val()==''){
						if(!$(this).closest(".goods-info-lay, .suboption-lay").find("input[name='chk_seq[]']").is(":checked")){
							//이 상품의 추가옵션은 개별반품할 수 없습니다.
							openDialogAlert(getAlert('mo079'),400,140);
							result = false;
						}
					}
				});
				if(!result) return false;
			}
		}

		// 추가옵션 해제할때
		if(row.find("input[name='chk_suboption_seq[]']").val()!='' && !$(this).is(":checked")){
			if(chk_individual_return!='1'){
				var result = true;
				$("#order_return_container input[name='chk_item_seq[]'][value='"+chk_item_seq+"'][item_option_seq='"+chk_option_seq+"'][shipping_seq='"+chk_shipping_seq+"']").each(function(){
					if($(this).closest(".goods-info-lay, .suboption-lay").find("input[name='chk_suboption_seq[]']").val()==''){
						if($(this).closest(".goods-info-lay, .suboption-lay").find("select[name='chk_ea[]'] option").length>1 && $(this).closest(".goods-info-lay, .suboption-lay").find("select[name='chk_ea[]'] option:last-child").is(":selected")){
							openDialogAlert(getAlert('mo079'),400,140);
							result = false;
						}
					}
				});
				if(!result) return false;
			}
		}

		// 필수옵션 해제할때
		if(row.find("input[name='chk_suboption_seq[]']").val()=='' && !$(this).is(":checked")){
			if(chk_individual_return!='1'){ // 개별취소 안되도록 설정했을때
				// 추가옵션 해제
				$("#order_return_container input[name='chk_item_seq[]'][value='"+chk_item_seq+"'][item_option_seq='"+chk_option_seq+"'][shipping_seq='"+chk_shipping_seq+"']").each(function(){
					if($(this).closest(".goods-info-lay, .suboption-lay").find("input[name='chk_suboption_seq[]']").val()!=''){
						$(this).closest(".goods-info-lay, .suboption-lay").find("input[name='chk_seq[]']").removeAttr("checked").each(function(){
							$(this).closest(".goods-info-lay, .suboption-lay").find("input,select,textarea").not(this).attr("disabled",true);
						});
						$(this).closest(".goods-info-lay, .suboption-lay").find("select[name='chk_ea[]']").val('').attr("disabled",true);
					}
				});
			}
		}

		if($(this).is(":checked")){
			row.find("input,select,textarea").not(this).removeAttr("disabled");
			row.find("select[name='chk_ea[]'] option:last-child").attr("selected",true).parent().change();
			row.find("select[name='chk_ea[]']").not(this).css({'background':'#ff7f7f'});
		}
		else{
			row.find("select[name='chk_ea[]']").not(this).css({'background':'#ccc'});
			row.find("input,select,textarea").not(this).attr("disabled",true);
			row.find("select[name='chk_ea[]']").val('').change();
			if($(this).attr('cancel_type') ==  1 ){
				$(this).attr("disabled",true);
			}
		}

		refund_method_layer_view();
	});

	$("#order_return_container select[name='chk_ea[]']").change(function(){
		var row = $(this).closest(".goods-info-lay, .suboption-lay");
		var idx = $("#order_return_container select[name='chk_ea[]']").index(this);
		var chk_item_seq = row.find("input[name='chk_item_seq[]']").val();
		var chk_option_seq = row.find("input[name='chk_option_seq[]']").val();
		var chk_shipping_seq = row.find("input[name='chk_shipping_seq[]']").val();
		var chk_individual_return = row.find("input[name='chk_individual_return[]']").val();

		if($(this).val()=='0'){
			$(this).closest(".goods-info-lay, .suboption-lay").find("input[name='chk_seq[]']").removeAttr("checked").change();
		}

		// 필수옵션일때
		if(row.find("input[name='chk_suboption_seq[]']").val()==''){
			if(chk_individual_return!='1'){
				if(row.find("select[name='chk_ea[]'] option").length>1 && row.find("select[name='chk_ea[]'] option:last-child").is(":selected")){
					$("#order_return_container input[name='chk_item_seq[]'][value='"+chk_item_seq+"'][item_option_seq='"+chk_option_seq+"'][shipping_seq='"+chk_shipping_seq+"']").each(function(){
						if($(this).parent().find("input[name='chk_suboption_seq[]']").val()!=''){
							$(this).parent().find("input[name='chk_seq[]']").not(":disabled").attr("checked",true).change();
							$(this).closest(".goods-info-lay, .suboption-lay").find("select[name='chk_ea[]'] option").not(":last-child").attr("disabled",true);
						}
					});
				}else{
					$("#order_return_container input[name='chk_item_seq[]'][value='"+chk_item_seq+"'][item_option_seq='"+chk_option_seq+"'][shipping_seq='"+chk_shipping_seq+"']").each(function(){
						if($(this).parent().find("input[name='chk_suboption_seq[]']").val()!=''){
							$(this).closest(".goods-info-lay, .suboption-lay").find("select[name='chk_ea[]'] option").not(":last-child").removeAttr("disabled");
						}
					});
				}
			}
		}

		refund_method_layer_view();
	});

	$("input[name='submitButton']").bind('click',function(){
		var frm = this;
		//반품신청을 하기 위해 상품수령을 확인해주세요. 상품을 수령하셨습니까?
		openDialogConfirm(getAlert('mo080'),450,140,function(){
			$("form[name='refundForm']").submit();
		});
		return false;
	});

	// 우편번호 찾기
	$("#return_recipient_zipcode_button").live("click",function(){
		openDialogZipcode('return_recipient_');
    });
	
	$("input[name='chk_shipping_seq'][tot_rt_ea!='0']").eq(0).attr("checked",true);
	$("input[name='chk_shipping_seq']").change(function(){
		var shippingGroupObj = $(this).closest('div.shipping_group');
		if($(this).is(":checked")){
			$(".chk_seq_cls").attr("disabled",true);
			$(".chk_seq_cls").attr("checked",false);
			$(shippingGroupObj).find(".goods-info-lay").not("[disabledscript='1']").find("input[name='chk_seq[]']").attr("disabled",false);
			$(".return_shipping_group_address").html("(반송주소) <br/>" + $(this).attr("return_address"));
		}
		$("#order_return_container input[name='chk_seq[]']").change();
	}).change();

					
	// 수량 인풋박스 컨트롤
	$(".only_number_for_chk_ea").bind("blur",function(){
		var max = parseInt($(this).attr("max"));
		if( $(this).val() == "0" || $(this).val() == "" ){
			openDialogAlert("0개 이상을 입력해주세요.",400,140,function(){$el.val(max);$el.focus();});
			$(this).val(max);
		}
		$(this).trigger("change");
	});
	// 수량 인풋박스 컨트롤
	/* 숫자만 입력, 맨앞 0 지움 */
	$(".only_number_for_chk_ea").bind("keyup change",function(){
		var regexp = /[^0-9]/gi;
		var $el = $(this);
		// 수량 체크
		var max = parseInt($(this).attr("max"));
		var min = parseInt($(this).attr("min"));
		var val = 0;
		if( $(this).val() != "0" && $(this).val() != ""){
			val = parseInt($(this).val());
		}		
		if(regexp.test($(this).val())) {
			$(this).val($(this).val().replace(regexp,""));
			openDialogAlert("숫자만 입력 가능합니다.",400,140,function(){$el.val(max);$el.focus();});
			$(this).val(max);
		}
		if($(this).val().length > 1 && $(this).val().substring(0,1) == "0"){
			$(this).val($(this).val().substring(1,$(this).val().length));
		}
		if((val < min || val > max) && val != 0){
			openDialogAlert("수량은 "+min+"부터 "+max+"까지만 입력 가능합니다.",400,140,function(){$el.val(max);$el.focus();});
			$(this).val(max);
		}
		// select 박스에 값 전달
		$selectBoxEl = $(this).next();
		$selectBoxEl.children().remove();
		$selectBoxEl.append($("<option>").val($(this).val()));
		$selectBoxEl.val($selectBoxEl.first().val());
	});

	// 사유 선택 시
	$("select[name='reason']").on('change', function(){
		var reason_desc = $("select[name='reason'] option:selected").text();
		$("input[name='reason_desc']").val(reason_desc);
		$(".reason_ship_duty").hide();
		if($("select[name='reason'] option:selected").val() == '120'){
			$(".shipping_refund_area").show();
			$(".reason_buyer").show();
		}else{
			$(".shipping_refund_area").hide();
			$(".reason_seller").show();
		}
	});

	$("div[disabledScript=1]").find("input,select").attr("disabled",true);
});