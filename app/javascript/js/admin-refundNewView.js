$(document).ready(function() {
	
	/* 환불수단에 따른 안내 */
	var isInit = true;
	var isComplete = false;
	if( gl_refund_status == 'complete' ){
		isComplete = true;
		
		$("input,select,textarea",$("form[name='refundForm']")).each(function(){
			$(this).attr("readonly",true).attr("disabled",true);
		});
	
		$("input.couponsalebtn").attr("readonly",false).attr("disabled",false);
		$("input.promotioncodesalebtn").attr("readonly",false).attr("disabled",false);
	}
	
	// 별표 설정
	$("span.list-important").bind("click",function(){
		var param = "?no="+$(this).attr('id');
		if( $(this).hasClass('checked') ){
			$(this).removeClass('checked');
			param += "&val=0";
			$.get('important'+param,function(data) {});
		}else{
			$(this).addClass('checked');
			param += "&val=1";
			$.get('important'+param,function(data) {});
		}
	});	
	
	$("select[name='refund_method']").change(function(){		
		if($(this).val() == 'cash'){

			if(!isComplete){
				// 상품 환불 금액 제거 :: 2018-06-07 lwh
				$(".refund_goods_price_area").each(function(){
					var refund_item_seq = $(this).closest('tr').attr('refund_item_seq');

					// 예치금 환불일 경우 상품 금액과 사용 예치금 금액을 모두 가산 by hed
					var origin_refund_goods_price_obj	= $("input[name='origin_refund_goods_price["+refund_item_seq+"]']"); // 금액
					var origin_cash_price_obj			= $("input[name='refund_goods_cash_area_origin["+refund_item_seq+"]']"); // 예치금
					var origin_refund_goods_price		= $(origin_refund_goods_price_obj).length > 0 ? uncomma($(origin_refund_goods_price_obj).val()) : 0;
					var origin_cash_price				= $(origin_cash_price_obj).length > 0 ? uncomma($(origin_cash_price_obj).val()) : 0;
					
					$("input[name='refund_cash_tmp["+refund_item_seq+"]']").val(comma(origin_refund_goods_price + origin_cash_price)).removeClass('hide').prev('.right').addClass('hide');
					$("div[name='refund_cash_txt["+refund_item_seq+"]']").html(comma(origin_refund_goods_price + origin_cash_price)).removeClass('hide').prev('.right').addClass('hide');
				});
				// 배송비 환불 금액 제거 :: 2018-06-07 lwh
				$(".refund_delivery_price_area").each(function(){
					var return_shipping_seq = $(this).data('return_shipping_seq');

					// 예치금 환불일 경우 상품 금액과 사용 예치금 금액을 모두 가산 by hed
					var refund_delivery_price_origin_obj		= $("input[name='refund_delivery_price_origin["+return_shipping_seq+"]']"); // 금액
					var refund_delivery_cash_area_origin_obj	= $("input[name='refund_delivery_cash_area_origin["+return_shipping_seq+"]']"); // 예치금
					var refund_delivery_price_origin				= $(refund_delivery_price_origin_obj).length > 0 ? uncomma($(refund_delivery_price_origin_obj).val()) : 0;
					var refund_delivery_cash_area_origin		= $(refund_delivery_cash_area_origin_obj).length > 0 ? uncomma($(refund_delivery_cash_area_origin_obj).val()) : 0;

					$("input[name='refund_delivery_cash_tmp["+return_shipping_seq+"]']").val(comma(refund_delivery_price_origin + refund_delivery_cash_area_origin)).removeClass('hide').prev('.right').addClass('hide');
					$("div[name='refund_delivery_cash_txt["+return_shipping_seq+"]']").html(comma(refund_delivery_price_origin + refund_delivery_cash_area_origin)).removeClass('hide').prev('.right').addClass('hide');
				});
			}

			$(".refund_goods_price_area, .refund_delivery_price_area").hide();
			$(".refund_goods_price_area, .refund_delivery_price_area").val(0);
			$(".refund_goods_price_txt, .refund_delivery_price_txt").html('-');
		}else{
			if(!isInit && !isComplete){
				// 상품 환불 금액 제거 :: 2018-06-07 lwh
				$(".refund_goods_cash_area").each(function(){
					var refund_item_seq = $(this).closest('tr').attr('refund_item_seq');

					// 예치금 환불일 경우 상품 금액과 사용 예치금 금액을 모두 가산 by hed
					var origin_refund_goods_price_obj	= $("input[name='origin_refund_goods_price["+refund_item_seq+"]']"); // 금액
					var origin_cash_price_obj			= $("input[name='refund_goods_cash_area_origin["+refund_item_seq+"]']"); // 예치금
					var origin_refund_goods_price		= $(origin_refund_goods_price_obj).length > 0 ? uncomma($(origin_refund_goods_price_obj).val()) : 0;
					var origin_cash_price				= $(origin_cash_price_obj).length > 0 ? uncomma($(origin_cash_price_obj).val()) : 0;

					$("input[name='refund_goods_price["+refund_item_seq+"]']").val(comma(uncomma(origin_refund_goods_price)));
					$("div[name='refund_goods_price_txt["+refund_item_seq+"]']").html(comma(uncomma(origin_refund_goods_price)));
					$("input[name='refund_cash_tmp["+refund_item_seq+"]']").val(0);
					$("div[name='refund_cash_txt["+refund_item_seq+"]']").html('-');
					if(origin_cash_price > 0){
						$("input[name='refund_cash_tmp["+refund_item_seq+"]']").val(comma(origin_cash_price));
						$("div[name='refund_cash_txt["+refund_item_seq+"]']").html(comma(origin_cash_price));
					}

					$(this).val(comma(origin_cash_price));
					if($(this).prev('.right').length > 0){
						$(this).addClass('hide');
					}
				});
				// 배송비 환불 금액 제거 :: 2018-06-07 lwh
				$(".refund_delivery_cash_area").each(function(){
					var return_shipping_seq = $(this).data('return_shipping_seq');

					// 예치금 환불일 경우 상품 금액과 사용 예치금 금액을 모두 가산 by hed
					var refund_delivery_price_origin_obj		= $("input[name='refund_delivery_price_origin["+return_shipping_seq+"]']"); // 금액
					var refund_delivery_cash_area_origin_obj	= $("input[name='refund_delivery_cash_area_origin["+return_shipping_seq+"]']"); // 예치금
					var refund_delivery_price_origin				= $(refund_delivery_price_origin_obj).length > 0 ? uncomma($(refund_delivery_price_origin_obj).val()) : 0;
					var refund_delivery_cash_area_origin		= $(refund_delivery_cash_area_origin_obj).length > 0 ? uncomma($(refund_delivery_cash_area_origin_obj).val()) : 0;


					$("input[name='refund_delivery_price_tmp["+return_shipping_seq+"]']").val(comma(refund_delivery_price_origin));
					$("div[name='refund_delivery_price_txt["+return_shipping_seq+"]']").html(comma(refund_delivery_price_origin));
					$("input[name='refund_delivery_cash_tmp["+return_shipping_seq+"]']").val(0);
					$("div[name='refund_delivery_cash_txt["+return_shipping_seq+"]']").html('-');
					if(refund_delivery_cash_area_origin > 0){
						$("input[name='refund_delivery_cash_tmp["+return_shipping_seq+"]']").val(comma(refund_delivery_cash_area_origin));
						$("div[name='refund_delivery_cash_txt["+return_shipping_seq+"]']").html(comma(refund_delivery_cash_area_origin));
					}

					$(this).val(comma(refund_delivery_cash_area_origin));
					if($(this).prev('.right').length > 0){
						$(this).addClass('hide');
					}
				});
			}

			$(".refund_goods_price_area, .refund_delivery_price_area").show();
		}

		$("." + $(this).val() + "_desc").show();
		$("select[name='status']").change();
		isInit = false;
	}).change();

	/* 환불상태에 따른 안내 */
	$("select[name='status']").change(function(){
		$("div.status_change_msg").hide();
		if($(this).val() == gl_refund_status){
			$("div.status_change_msg[curStatus = gl_refund_status]").show();
			$("div.status_change_msg").css('border','3px solid #333');
		}else{
			if($(this).val()=='complete'){
				$('.status_complete_msg').show();
				if( gl_npay_use && gl_refund_npay_order_id ){
					$("div.status_change_msg[curStatus='request']").show();
				}else{
					var refund_method = $("select[name='refund_method']").val();
					if($("select[name='refund_method']").val() == "cash") refund_method = "bank";
					$("div.status_change_msg.complete." +refund_method).show();
					$("div.status_change_msg .status_change_msg_price").html(comma(num($("input[name='refund_price']").val())));
				}
			}else{
				$('.status_complete_msg').hide();
				if( gl_npay_use && gl_refund_npay_order_id ){
					$("div.status_change_msg[curStatus='ing']").show();
				}else{
					$("div.status_change_msg."+$(this).val()).show();
				}
			}
			$("div.status_change_msg").css('border','3px solid #333');
		}
	});

	$("select[name='status']").val(gl_refund_status).change();

	if( !gl_refund_userid ){
		// 비회원은 마일리지환불,예치금환불 불가
		// 각 행의 비활성화 추가 작업 :: 2018-06-01 lwh
		$("input[name='refund_emoney'], input[name='refund_cash'], select[name='refund_emoney_limit_type'], input[name='refund_emoney_limit_date']").attr('readonly',true);
		$("input[name='refund_emoney'], input[name='refund_cash'], select[name='refund_emoney_limit_type'], input[name='refund_emoney_limit_date']").attr('disabled',true);
		if(get_currency_price($("input[name='refund_emoney']").val(),1,'basic')==0 && get_currency_price($("input[name='refund_cash']").val(),1,'basic')==0) $(".emoney_refund_cell").hide();
	}

	/* 마일리지 환불 유효기간 제한 */
	$("select[name='refund_emoney_limit_type']").on("change",function(){
		if($(this).val()=='y'){
			$("#refund_emoney_date_div").show();
		}else{
			$("#refund_emoney_date_div").hide();
		}
	}).change();

	/* 숫자만 입력, 맨앞 0 지움 */
	$(".refund_adjust").on("keyup",function(){
		if($(this).val().length > 1 && $(this).val().substring(0,1) == "0"){
			$(this).val($(this).val().substring(1,$(this).val().length));
		}
	});

	/* 환불금액 입력시 콤마제거 */
	$(".refund_adjust").on("click",function(){

		var selector = $(this);
		if(selector.attr("name") != "refund_method"){

			selector.val(selector.val().replace(",",""));
			if(selector.val() == "0" || selector.val() == "0.00") selector.val("");
			if(eval(selector.val().replace("-","")) > 0){

				// IE
				if (this.createTextRange) {
					var range = this.createTextRange();
					range.move('character', this.value.length);    // input box 의 글자 수 만큼 커서를 뒤로 옮김
					range.select();
				}
				else if (this.selectionStart || this.selectionStart== '0')
					this.selectionStart = this.value.length;
			}
		}

	});
	
	if( gl_order_pg != 'npay' || gl_refund_npay_order_id ){
		/* 환불금액관리자조정 */
		$(".refund_adjust").on("change blur",function(){

			var refund_price		= 0.00;
			var refund_price_sum	= 0.00;

			var refund_cash			= 0.00; //uncomma_float($("input[name='refund_cash']").val());		// 예치금 환불
			var refund_emoney		= 0.00; //uncomma_float($("input[name='refund_emoney']").val());	// 마일리지 환불

			// 3차 환불 개선으로 전체 조정금액 변수 추가 :: 2018-11- lkh
			var refund_all_deductible_price = 0.00;
			var refund_penalty_deductible_price = get_currency_price(gl_refund_penalty_deductible_price,1,'basic'); // 환불위약금(쿠폰)
			var refund_deductible_price = uncomma_float($("input[name='refund_deductible_price']").val());
			var refund_delivery_deductible_price = uncomma_float($("input[name='refund_delivery_deductible_price']").val());
			refund_all_deductible_price = refund_penalty_deductible_price+refund_deductible_price+refund_delivery_deductible_price;

			var return_shipping_price	= get_currency_price(gl_return_shipping_price, 1, 'basic'); // 반품 배송비

			var selector = $(this);
			if(selector.val().trim() == "") selector.val(0);

			if(selector.attr("name") != "refund_method"){
				if(selector.val().trim() != 0 && get_currency_price(uncomma_float(selector.val()),1,'basic') == 0){
					openDialogAlert("숫자만 입력 가능합니다.",400,140,function(){
						selector.val(0);
					});
					return false;
				}
			}

			// 예치금 환불 금액 계산 :: 2018-06-01 lwh
			$(".refund_cash_input").each(function(i){
				var cash = 0;
				cash = $(this).val();
				if(cash == "") cash = 0; else cash = uncomma_float(cash);
				refund_cash = refund_cash + eval(cash);
			});
			// 마일리지 환불 금액 계산 :: 2018-06-01 lwh
			$(".refund_emoney_input").each(function(i){
				var emoney = 0;
				emoney = $(this).val();
				if(emoney == "") emoney = 0; else emoney = uncomma_float(emoney);
				refund_emoney = refund_emoney + eval(emoney);
			});
			$("input[name='refund_cash']").val(refund_cash);								// 예치금 환원 금액
			$("input[name='refund_emoney']").val(refund_emoney);							// 마일리지 환원 금액
			$("#refund_cash_txt").html(get_currency_price(refund_cash,'','basic'));			// 예치금 환원 표기
			$("#refund_emoney_txt").html(get_currency_price(refund_emoney,'','basic'));		// 마일리지 환원 표기

			//상품별 환불금액 + 반품배송비 총액
			$(".refund_adjust").each(function(w){
				var price = 0;
				if($(this).attr("itype") == "goods_price" || $(this).attr("itype") == "delivery_price" ){
					price = $(this).val();
					if(price == "") price = 0; else price = uncomma_float(price);
					refund_price = refund_price + eval(price);

					//기본통화와 결제통화가 다를 때 결제통화기준 금액 노출
					if( gl_order_pg_currency && gl_order_pg_currency != gl_basic_currency && price > 0 ){
						var refund_payment_price = 0;
						refund_payment_price = get_currency_exchange(price,'',pg_currency_exchange_rate);
						refund_payment_price = get_currency_price(refund_payment_price,1,pg_currency,'','','front');
						$(this).closest('td').find(".refund_pg_price").show();
						$(this).closest('td').find(".refund_pg_price span").html(refund_payment_price);
					}
				}
			});

			var refund_method = $("select[name='refund_method'] option:selected");	//환불방법

			var refund_method_text = "(무통장)";
			if(refund_method.val() == "card"){
				refund_method_text = "(카드결제취소)";
			}else if(refund_method.val() == "account" || refund_method.val() == "escrow_account"){
				refund_method_text = "(계좌이체취소)";
			}else if(refund_method.val() == "cellphone"){
				refund_method_text = "(핸드폰취소)";
			}else if(refund_method.val() == "cash"){
				$("#refund_cash_txt").html(get_currency_price(refund_cash,'','basic'));			//예치금로 환불
				refund_price = 0;
			}else if(refund_method.val() == "point"){
				refund_method_text = "(포인트취소)";
			}

			// 총 환불 금액
			// 3차 환불 개선으로 전체 총조정금액 변수 추가 :: 2018-11- lkh
			refund_price_sum = eval(refund_price) + eval(refund_emoney) + eval(refund_cash) - eval(refund_all_deductible_price);
			if( gl_refund_ship_duty == 'buyer' && gl_refund_ship_type =='M' ){
				refund_price_sum = refund_price_sum - eval(return_shipping_price);
			}

			$("#refund_all_deductible_price_txt").html(get_currency_price((eval(refund_all_deductible_price)+eval(return_shipping_price)),'','basic'));		//총조정금액(상품+배송+환불위약금+반품배송비)
			if(refund_method.val() != "manual") {
				$("#refund_method_txt").html(refund_method_text);				//환불방법
			}
			$("#refund_price_txt").html(get_currency_price(refund_price, '', 'basic'));			//상품+배송

			$("input[name='refund_price']").val(refund_price_sum);			//상품+배송
			$("#refund_price_sum").html(get_currency_price(refund_price_sum,'','basic'));		//총환불액(상품+배송+마일리지+예치금-총조정금액)

			//결제통화 기준 환불금액
			if(refund_price > 0){
				var refund_pg_price_sum = 0;
				refund_pg_price_sum = get_currency_exchange(refund_price, '', pg_currency_exchange_rate);
				refund_pg_price_sum = get_currency_price(refund_pg_price_sum, '', pg_currency, '', '', 'front');
				$("#refund_pg_price_sum").html(refund_pg_price_sum);
			}

			if(selector.attr("name") != "refund_method"){
				selector.val(get_currency_price(selector.val(),'','basic'));
			}
			
			var settle_price	= eval($("input[name='settle_price']").val())+eval(pay_emoney)+eval(pay_cash)-eval(return_shipping_price);
			var complete_price	= eval($("input[name='complete_price']").val());
			var refund_shipping_price	= eval($("input[name='refund_shipping_price']").val());			//반품배송비
			var remain_price	= settle_price - complete_price;				//환불가능 잔여액(결제금액-환불완료액)

			$(".status_change_msg_price").html(get_currency_price(refund_price_sum,'','basic'));

			var order_shipping_cost = 0;		//주문시 결제한 배송비
			var refund_delivery_price_area = 0;	//실제 환불배송비
			$(".refund_delivery_price_area").each(function(){
				refund_delivery_price_area = refund_delivery_price_area + eval($(this).val());
			});

			if(remain_price > 0 && remain_price < refund_price_sum){
				var msg = "[경고] 위 환불금액 "+get_currency_price(refund_price_sum,'','basic')+"은 환불 가능금액 "+get_currency_price(remain_price,'','basic')+"(="+get_currency_price(settle_price,'','basic')+"-"+get_currency_price(complete_price,'','basic')+")을 초과한 금액입니다.";
				$("#warning_msg").html(msg);
				$("#warning_msg").show();
			}else{
				$("#warning_msg").hide();
			}
		});
	}
	
	

	// 재발행된 쿠폰의 사용기간 자세히
	$(".btncouponinfo").on("click",function(){
		openDialog("재발행 A쿠폰(또는 코드)의 사용기간 예시", "couponinfo", {"width":500,"height":280});
	});

	// 사은품 지급 조건 상세
	$(".gift_log").bind('click', function(){
		gift_use_log($(this).attr('order_seq'),$(this).attr('item_seq'));
	});

	$(".coupon_seq").on("click",function(){
		var seq = $(this).val();
		$(".coupon_seq."+seq).attr("checked",$(this).attr("checked"));
	});

	$(".promotion_seq").on("click",function(){
		var seq = $(this).val();
		$(".promotion_seq."+seq).attr("checked",$(this).attr("checked"));
	});
	
	$.get('../member/sms_pop?order_seq='+gl_refund_order_seq+'&page=refund', function(data) {
		$('#sms_form').html(data);
	});
	
	load_order_info();	
	
	if( gl_refund_status != 'complete' ){
		$(".refund_adjust").eq(0).blur();
	}

	$(".refund_ordersheet").bind("click", function(){
		var obj_checked = $(this).attr("checked");
		if(typeof(obj_checked) === "undefined"){
			obj_checked = false;
		}
		var obj_val = $(this).val();
		$(".refund_ordersheet").each(function(){
			if($(this).val() == obj_val){
				$(this).attr("checked", obj_checked);
			}
		});
	});
});

// 사은품 지급 조건 상세 2015-05-14 pjm
function gift_use_log(order_seq,item_seq){
		$.ajax({
			type: "post",
			url: "../event/gift_use_log",
			data: "order_seq="+order_seq+"&item_seq="+item_seq,
			success: function(result){
				if	(result){
					$("#gift_use_lay").html(result);
					openDialog("사은품 이벤트 정보", "gift_use_lay", {"width":"500","height":"330"});
				}
			}
		});
}

function load_order_info(){
	$.get('../order/view?no='+gl_refund_order_seq+'&pagemode=refund_view&refund_code='+gl_refund_code, function(data) {
		$('#order_info').html(data);
	});
}

function refundSubmit(){	
	if( gl_npay_use && gl_refund_npay_order_id && gl_refund_type != "cancel_payment" ){
		openDialogAlert("이 환불건은 네이버페이 반품건이므로 직접 처리 불가합니다.",460,150,function(){});
		return false;
	}else{
		var refund_price = $("input[name='refund_price']").val();
	
		if(refund_price == "") refund_price = 0;
	
		if(eval(refund_price) < 0){
			openDialogAlert("환불 처리금액이 0보다 작아질 수 없습니다.",400,140,function(){});
			return false;
		}
	
		if($("select[name='status']").val() == "complete"){
			if(eval(refund_price) == 0){
				openDialogConfirm("환불 처리금액이 0"+gl_basic_currency+" 입니다. 계속 진행하시겠습니까?",400,140,function(){ refundSumitTrue('err',refund_price); });
				return false;
			}else{
				return refundSumitTrue('',refund_price);
			}
		}else{
			return refundSumitTrue('',refund_price);
		}
	}
}

function refundSumitTrue(mode, refund_price){
	var settle_price			= eval($("input[name='settle_price']").val())+eval(pay_emoney)+eval(pay_cash);
	var complete_price			= $("input[name='complete_price']").val();
	var refund_shipping_price	= eval($("input[name='refund_shipping_price']").val());			//반품배송비
	var remain_price			= eval(settle_price) - eval(complete_price) ;	//환불가능 잔여액(결제금액-환불완료액)

	if(remain_price < refund_price){
		openDialogAlert("환불 처리금액("+get_currency_price(refund_price, '', 'basic')+")은 환불 가능금액("+get_currency_price(remain_price, '', 'basic')+")을 초과한 금액입니다.", 500, 140,function(){
		});
		return false;
	}

	/* 올앳 결제취소시 파라미터 암호화 스크립트 처리 */
	if( gl_order_pg == 'allat' ){
		var refund_value = document.refundForm.refund_method.value;
		if(refund_value=='card' || refund_value=='escrow_account' || refund_value=='account' || refund_value=='cellphone'){
	
			var refund_emoney	= uncomma_float($("input[name='refund_emoney']").val());	//예치금환불
			var refund_cash		= uncomma_float($("input[name='refund_cash']").val());
	
			var allat_refund_price = refund_price - refund_emoney - refund_cash;
	
			document.refundForm.action			= "/common/allat_enc";
			document.refundForm.allat_amt.value = allat_refund_price;
			switch(document.refundForm.refund_method.value){
				case "card": 		document.refundForm.allat_pay_type.value = "CARD"; break;
				case "account": 	document.refundForm.allat_pay_type.value = "ABANK"; break;
				case "escrow_account": 	document.refundForm.allat_pay_type.value = "ABANK"; break;
				case "cellphone": 	document.refundForm.allat_pay_type.value = "HP"; break;
			}
	
		}else{
			document.refundForm.action = "/admin/refund_process/save";
		}
	}

	// 중복 실행 방지 :: 2018-01-19 lkh
	loadingStart();
	
	if(mode == "err"){
		document.refundForm.submit();
	}else{
		return true;
	}
}
// 할인내역 열기 닫기
function open_sale_contents(obj)
{
	var btnobj = $(obj);
	var trobj = $(obj).closest('tr').next();
	var tdobj = $(obj).closest('td');
	var divobj = trobj.find("td").eq(tdobj.index()).find("div");
	if(divobj.hasClass('hide')){
		divobj.removeClass('hide');
		btnobj.attr('src','../images/common/btn_close.gif');
	}else{
		divobj.addClass('hide');
		btnobj.attr('src','../images/common/btn_open.gif');
	}
}