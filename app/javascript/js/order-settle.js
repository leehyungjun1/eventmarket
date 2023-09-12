/**
 통합 약관 관련 변수 선언
 
 **/
if(!gl_iscancellation) var gl_iscancellation = false;
if(!gl_isdelegation) var gl_isdelegation = false;

/**
 * 결제 방법 체크
 * @param obj
 * @author lwh 2017-04-03
 */
function change_payment_type(obj){
	// true 면 결제수단은 카카오 페이만 가능
	if (isPaymentOnlyKakaoPay() === true) {
		return false;
	}

	// IE8에서 결제 수단 변경 되도록 수정 2017-12-05
	$('input:radio[name=payment][value='+$(obj).val()+']').attr('checked', true);
	var mobileMode = gl_mobile;
	if(gl_mobile==true){
		/** 결제수단에 따른 활성화 처리 - 모바일/반응형 2018-12-17 by hed */
		$('input:radio[name=payment]').each(function(){
			$("#payment_type").siblings(".payment_detail_table").find("."+$(this).val()).hide();
			if(typeof($(this).attr("checked")) !== "undefined"){
				$("#payment_type").siblings(".payment_detail_table").find("."+$(this).val()).show();
			}
		});
	}else{
		/** 결제수단에 따른 활성화 처리 - pc 2018-12-17 by hed */
		$('input:radio[name=payment]').each(function(){
			$("#payment_type").siblings("."+$(this).val()).hide();
			if(typeof($(this).attr("checked")) !== "undefined"){
				$("#payment_type").siblings("."+$(this).val()).show();
			}
		});
	}

	// KICC 휴대폰 결제 복합 과세 미지원 처리
	var disableCheck = $(obj).attr("data-cellphonePayDisabled");
	if(disableCheck == undefined || disableCheck == '' || disableCheck == ' '){
		disableCheck = 'n';
	}
	if(disableCheck == 'y'){
		// os256 복합과세 상품을 지원하지 않는 결제 수단입니다.
		openDialogAlert(getAlert('os256'),400,150);
	}

	set_pay_button();
	reverse_pay_button();
}

// 카카오 페이 결제만 가능
function isPaymentOnlyKakaoPay() {
	var isFix = false;

	var querys = paymentUrlParseQuery();
	if (typeof querys.fix_payment !== 'undefined' && querys.fix_payment === 'kakaopay') {
		isFix = true;
	}

	return isFix;
}

// url parse
function paymentUrlParseQuery() {
	var queryString = window.location.search;
	var query = {};
	var pairs = (queryString[0] === '?' ? queryString.substr(1) : queryString).split('&');
	for (var i = 0; i < pairs.length; i++) {
		var pair = pairs[i].split('=');
		query[decodeURIComponent(pair[0])] = decodeURIComponent(pair[1] || '');
	}
	return query;
}

// 구버전 국내/해외 구분값 :: 2016-08-03 lwh
function check_shipping_method(){
	var idx = $("select[name='international'] option:selected").val();

	$("div.shipping_method_radio").each(function(){
		$(this).hide();
	});
	if(!idx)idx = 0;
	$("div.shipping_method_radio").eq(idx).show();

	if(idx == 0){
		$(".domestic").show();
		$(".international").hide();
	}else{
		$(".international").show();
		$(".domestic").hide();
	}
}

// 신버전 국내/해외 구분값 :: 2016-08-03 lwh
function check_shipping_nation(){
	var nation = $("#address_nation").val();

	if(!nation)	nation = 'KOREA';
	$("#nation_img").attr("src",""); // 국기 임시로 없애기

	if(nation == 'KOREA'){
		$(".international_nation").html('대한민국');
		$(".domestic").show();
		$(".international").hide();
	}else{
		$("input[name='international_country']").val(nation);
		$("input[name='international_country']").hide();
		$(".international_nation").html(nation);
		$(".international").show();
		$(".domestic").hide();
	}
}

// 상품별 입력
function ship_each_input(){
	if($("#each_msg").is(":checked")){
		$(".total_ship_msg").hide();
		$(".each_ship_msg").show();
	}else{
		$(".total_ship_msg").show();
		$(".each_ship_msg").hide();
	}
}

// 전체 재 계산 :: 2016-08-03 lwh
function order_price_calculate(){
	var f				= $("form#orderFrm");
	var adminOrder		= $("input[name='adminOrder']").val();
	var adminOrderType	= $("input[name='adminOrderType']").val();
	var action			= "/order/calculate?mode="+gl_mode+"&adminOrder="+adminOrder;

	if(adminOrderType != ""){
		action		= action + '&adminOrderType=person';
	}
	
	// ssl 적용
	$.ajax({
        async: false,
		'url'		: '/common/ssl_action',
		'data'		: {'action':action},
		'type'		: 'get',
		'dataType'	: 'html',
		'success'	: function(res) {
			action = res;
		}
	});
	
	f.attr("action",action);
	f.attr("target","actionFrame");
	// jCryption 재적용 스킨의 orderFrm 에 ssl 링크가 없기에 js 영역에서 재선언
	moduleJcryption.resetJcryptionSubmit(f[0]);
	f.submit();
}

function set_pay_button(){
	$.ajax({
		'url' : '../order/settle_order_images',
		'dataType': 'json',
		'cache': false,
		'success': function(data) {
			if($("#pay>img").attr("src")=='/data/skin/'+gl_skin+'/images/buttons/btn_pay.gif' || $("#pay>img").attr("src")=='/data/skin/'+gl_skin+'/images/buttons/btn_order.gif'){
				var btn_order_pay1 = '/data/skin/'+gl_skin+'/images/buttons/btn_pay.gif';
				var btn_order_pay2 = '/data/skin/'+gl_skin+'/images/buttons/btn_order.gif';
			}else{
				var btn_order_pay1 = '/data/skin/'+gl_skin+'/images/buttons/btn_order_pay.gif';
				var btn_order_pay2 = '/data/skin/'+gl_skin+'/images/buttons/btn_order.gif';
		
				if(data.btn_order_pay1) btn_order_pay1 = data.btn_order_pay1;
				if(data.btn_order_pay2) btn_order_pay2 = data.btn_order_pay2;
			}

			$("#pay").html("<img src='"+btn_order_pay1+"' />");
			$("input[name='payment']:checked").each(function(){
				if( $(this).val() == "bank" ){
					$("#pay").html("<img src='"+btn_order_pay2+"' />");
				}else{
					//쿠폰의 무통장쿠폰인 경우 점검 
					if( eval('$("#coupon_sale_payment_b")') ){  
						var coupon_sale_payment_b = $("#coupon_sale_payment_b").val();
						if(coupon_sale_payment_b>0){ 
							//현재 무통장 전용 쿠폰을 사용하셨습니다.<br />결제수단을 무통장으로 변경해 주세요!
							openDialogAlert(getAlert('os118'),400,150);
							return false;
						}
					}
				}
			});
		}
	});
}

// 반응형, PC, 모바일 모두 동작되도록 개선 2020-05-28
function typereceipt_layout_toggle(payment) {
	var payment = $(payment).val();

	if( gl_cashreceiptuse > 0 || gl_taxuse >0 ){
		$("#typereceiptcardlay").hide();
		$("#typereceipttablelay").show();

		if( jQuery.inArray(payment, ['card', 'cellphone', 'kakaopay', 'payco', 'naverpayment']) !== -1 ){
			$("#typereceiptcardlay").show();
			$("#typereceipttablelay").hide();
			$(".typereceiptlay").hide();
			$("#typereceiptlay").hide();
			$("#typereceiptchoice").hide();
		}else if( payment == "paypal" || payment == "eximbay"){
			$("#typereceiptcardlay").hide();
			$("#typereceipttablelay").hide();
			$(".typereceiptlay").hide();
			$("#typereceiptlay").hide();
			$("#typereceiptchoice").hide();
		}else{
			$("#typereceiptlay").show();
			check_typereceiptuse();
		}
	}
}

function reverse_pay_button(){
	$("div.pay_layer").eq(0).show();
	$("div.pay_layer").eq(1).hide();
}


/**
 * 매출증빙폼노출
 */
function check_typereceipt(){
	var obj =  $("input[name='typereceipt']:checked");
	if(obj.length == 0) obj = $("select[name='typereceipt']");

	if(obj.val() == 0) {
		$('#cash_container').hide();
		$('#tax_container').hide();
		taxRemoveClass();
		cashRemoveClass();
	}else if(obj.val() == 1) {
		// 세금계산서 신청일 경우
		$('#tax_container').show();
		$('#cash_container').hide();

		$('#co_name').addClass('required');
		$('#co_ceo').addClass('required');
		$('#busi_no').addClass('required').addClass('busiNo');
		$('#co_zipcode').addClass('required');
		$('#co_address').addClass('required');
		$('#co_status').addClass('required');
		$('#co_type').addClass('required');

		cashRemoveClass();
	}else if(obj.val() == 2) {
		// 현금영수증 신청일 경우
		$('#cash_container').show();
		$('#tax_container').hide();
		$('#creceipt_number').addClass('required').addClass('numberHyphen');

		taxRemoveClass();
	}
	if( $("input[name='payment']:checked").val() == 'bank'){
		$("#duplicate_message").hide();
	}else{
		$("#duplicate_message").show();
	}
}

/**
 * 증빙서류 선택폼 노출
 */
function check_typereceiptuse() {
	var obj =  $("input[name='typereceiptuse']:checked");

	// 발급안함 선택
	if(obj.val() == 0) {
		$('#typereceiptchoice').hide();
		$("#typereceipttablelay").hide();
		$("input[name='typereceipt']").attr("checked", false);
	}else if(obj.val() == 1) {
		// 발급 선택
		$('#typereceiptchoice').show();
		$("#typereceipttablelay").show();
		$("#typereceipt2").click();
	}

	check_typereceipt();
}

/**
 * 세금계산서 폼체크를 삭제한다.
 */
function taxRemoveClass() {
	$('#co_name').removeClass('required');
	$('#co_ceo').removeClass('required');
	$('#busi_no').removeClass('required');
	$('#co_zipcode').removeClass('required');
	$('#co_address').removeClass('required');
	$('#co_status').removeClass('required');
	$('#co_type').removeClass('required');
}

/**
 * 현금영수증 폼체크를 삭제한다.
 */
function cashRemoveClass() {
	$('#creceipt_number').removeClass('required');
}


//쿠폰적용하시기 단독쿠폰체크
function sametime_coupon_dialog(){
	getCouponAjaxList();
}


function getPromotionckloding(cartpromotioncode) {
	if( cartpromotioncode ) {
		$.ajax({
			'url' : '/promotion/getPromotionJson?mode='+gl_mode,
			'data' : {'cartpromotioncode':cartpromotioncode},
			'type' : 'post',
			'dataType': 'json',
			'cache': false,
			'success': function(data) {
				order_price_calculate();
			}
		});
	}
}

function getPromotionck(){
	var cartpromotioncode = $("#cartpromotioncode").val();
	if(!cartpromotioncode){
		//할인코드를 정확히 입력해 주세요.
		openDialogAlert(getAlert('os026'),'400','140');
		return false;
	}

	var mode		= $("input[name='mode']").val();
	var member_seq	= $("input[name='member_seq']").val();

	$.ajax({
		'url' : '/promotion/getPromotionJson?mode='+mode,
		'data' : {'cartpromotioncode':cartpromotioncode, 'member_seq':member_seq},
		'type' : 'post',
		'dataType': 'json',
		'cache': false,
		'success': function(data) {
			if(data.result == false ){
				openDialogAlert(data.msg,'400','140',function(){getPromotionCartDel();});
				return false;
			}

			var promotionDetailhelphtml = '<div class="promotionlay" align="left" ><ul >';

			if( data.result == false ) {
				//코드할인이 적용되지 않았습니다.
				promotionDetailhelphtml +=  "<li class='red'>"+getAlert('os186')+"</li>";
			}

			//코드내용
			promotionDetailhelphtml +=  "<li><b>"+getAlert('os187')+"</b> :  " + data.promotion_desc + "(" + data.promotion_name + ") </li>";
			//사용기간
			promotionDetailhelphtml +=  "<li><b>"+getAlert('os188')+"</b> :  " + data.issue_enddatetitle + " </li>";
			//할인내용
			promotionDetailhelphtml +=  "<li> <b>"+getAlert('os189')+"</b> </li>";
			if(data.sale_type == 'shipping_free'){
				//기본배송비 무료
				promotionDetailhelphtml +=  "<li>- <b>"+getAlert('os190')+"</b></li>";// (최대 " + get_currency_price(data.max_percent_shipping_sale) + "원)
				//이상 구매 시
				promotionDetailhelphtml +=  "<li>- "+ get_currency_price(data.limit_goods_price,2,'basic') +" "+getAlert('os191')+"</li>";
			}else if(data.sale_type == 'shipping_won'){//**원배송비 할인
				var realprice = get_currency_price(data.won_shipping_sale,'','basic');
				//기본배송비 "+ realprice +"원 할인 
				promotionDetailhelphtml +=  "<li>- <b>"+getAlert('os192',realprice)+" </b></li>";
				//이상 구매 시
				promotionDetailhelphtml +=  "<li>- "+ get_currency_price(data.limit_goods_price,2,'basic') +" "+getAlert('os191')+"</li>";
			}else if(data.sale_type == 'won'){//**원 주문상품할인
				var realprice = get_currency_price(data.won_goods_sale,'','basic');

				//할인
				promotionDetailhelphtml +=  "<li>- <b>"+ realprice +" "+getAlert('os193')+" </b></li>";
				//이상 구매 시
				promotionDetailhelphtml +=  "<li>- "+ get_currency_price(data.limit_goods_price,2,'basic') +" "+getAlert('os191')+"</li>";
				if(data.issue_type == 'all') {
					//전체 사용 가능
						promotionDetailhelphtml +=  "<li>- "+getAlert('os194')+"</li>";
				}else{
					if(data.goodshtml) {
						if(data.issue_type == 'except'){
							//상품 사용 불가
							promotionDetailhelphtml +=  "<li><b>"+getAlert('os195')+"</b></li>";
						}else if(data.issue_type == 'issue'){
							//상품 사용 가능
							promotionDetailhelphtml +=  "<li><b>"+getAlert('os196')+"</b></li>";
						}

						var sArr = data.goodshtml.split(',');
						var cArr = data.goodshtmlcode.split(',');
						promotionDetailhelphtml += '<li><div style="border-left:1px #ececec;background-color:#f2f2f2;border-top:2px #eaeaea;padding:5px; width:100%; height:50px; border:0px;overflow:auto" class="" readonly>';
						for(var ii = 0;ii<sArr.length;ii++){
							promotionDetailhelphtml += "- <a href='../goods/view?no="+cArr[ii]+"' target='_blank' >"+sArr[ii]+"</a><br />";
							//promotionDetailhelphtml += "-"+sArr[ii]+"<br />";
						}
						promotionDetailhelphtml += "</div></li>";
					}

					if(data.brandhtml) {
						if(data.issue_type == 'except'){
							//브랜드 사용 불가
							promotionDetailhelphtml +=  "<li><b>"+getAlert('os197')+"</b></li>";
						}else if(data.issue_type == 'issue'){
							//브랜드 사용 가능
							promotionDetailhelphtml +=  "<li><b>"+getAlert('os198')+"</b></li>";
						}
						var sArr = data.brandhtml.split(',');
						var cArr = data.brandhtmlcode.split(',');
						promotionDetailhelphtml += '<li ><div style="border-left:1px #ececec;background-color:#f2f2f2;border-top:2px #eaeaea;padding:5px; width:100%; height:50px; border:0px;overflow:auto" class="" readonly>';
						for(var ii = 0;ii<sArr.length;ii++){
							promotionDetailhelphtml += "- <a href='../goods/brand?code="+cArr[ii]+"' target='_blank' >"+sArr[ii]+"</a><br />";
							//promotionDetailhelphtml += "-  "+sArr[ii]+"<br />";
						}
						promotionDetailhelphtml += "</div></li>";
					}

					if(data.categoryhtml) {
						if(data.issue_type == 'except'){
							//카테고리 사용 불가
							promotionDetailhelphtml +=  "<li><strong>"+getAlert('os199')+"</strong> </li>";
						}else if(data.issue_type == 'issue'){
							//카테고리 사용 가능
							promotionDetailhelphtml +=  "<li><strong>"+getAlert('os200')+"</strong></li>";
						}
						var sArr = data.categoryhtml.split(',');
						var cArr = data.categoryhtmlcode.split(',');
						promotionDetailhelphtml += '<li><div style="border-left:1px #ececec;background-color:#f2f2f2;border-top:2px #eaeaea;padding:5px; width:100%; height:50px; border:0px;overflow:auto" class="" readonly>';
						for(var ii = 0;ii<sArr.length;ii++){
							promotionDetailhelphtml += "- <a href='../goods/catalog?code="+cArr[ii]+"' target='_blank' >"+sArr[ii]+"</a><br />";
						}
						promotionDetailhelphtml += "</div></li>";
					}
				}
			}else{//**%할인(최대할인금액제한)
				var realpercent = (data.percent_goods_sale);

				//할인
				promotionDetailhelphtml +=  "<li>- <b>" + realpercent + "% "+getAlert('os201')+"</b></li>";// (최대 " + get_currency_price(data.max_percent_goods_sale) + "원)
				//이상 구매 시
				promotionDetailhelphtml +=  "<li>- "+ get_currency_price(data.limit_goods_price,2,'basic') +" "+getAlert('os191')+"</li>";
				if(data.issue_type == 'all') {
					//전체 사용 가능
						promotionDetailhelphtml +=  "<li>- "+getAlert('os194')+"</li>";
				}else{
					if(data.goodshtml) {
						if(data.issue_type == 'except'){
							//상품 사용 불가
							promotionDetailhelphtml +=  "<li><strong>"+getAlert('os195')+"</strong></li>";
						}else if(data.issue_type == 'issue'){
							//상품 사용 가능
							promotionDetailhelphtml +=  "<li><strong>"+getAlert('os196')+"</strong></li>";
						}

						var sArr = data.goodshtml.split(',');
						var cArr = data.goodshtmlcode.split(',');
						promotionDetailhelphtml += '<li><div style="border-left:1px #ececec;background-color:#f2f2f2;border-top:2px #eaeaea;padding:5px; width:100%; height:50px; border:0px;overflow:auto" class="" readonly>';
						for(var ii = 0;ii<sArr.length;ii++){
							promotionDetailhelphtml += "- <a href='../goods/view?no="+cArr[ii]+"' target='_blank' >"+sArr[ii]+"</a><br />";
							//promotionDetailhelphtml += "-"+sArr[ii]+"<br />";
						}
						promotionDetailhelphtml += "</div></li>";
					}

					if(data.brandhtml) {
						if(data.issue_type == 'except'){
							//브랜드 사용 불가
							promotionDetailhelphtml +=  "<li><strong>"+getAlert('os197')+"</strong></li>";
						}else if(data.issue_type == 'issue'){
							//브랜드 사용 가능
							promotionDetailhelphtml +=  "<li><strong>"+getAlert('os198')+"</strong></li>";
						}

						var sArr = data.brandhtml.split(',');
						var cArr = data.brandhtmlcode.split(',');
						promotionDetailhelphtml += '<li ><div style="border-left:1px #ececec;background-color:#f2f2f2;border-top:2px #eaeaea;padding:5px; width:100%; height:50px; border:0px;overflow:auto" class="" readonly>';
						for(var ii = 0;ii<sArr.length;ii++){
							promotionDetailhelphtml += "- <a href='../goods/brand?code="+cArr[ii]+"' target='_blank' >"+sArr[ii]+"</a><br />";
							//promotionDetailhelphtml += "-  "+sArr[ii]+"<br />";
						}
						promotionDetailhelphtml += "</div></li>";

					}///goods/brand?code=

					if(data.categoryhtml) {
						if(data.issue_type == 'except'){
							//카테고리 사용 불가
							promotionDetailhelphtml +=  "<li><strong>"+getAlert('os199')+"</strong></li>";
						}else if(data.issue_type == 'issue'){
							//카테고리 사용 가능
							promotionDetailhelphtml +=  "<li><strong>"+getAlert('os200')+"</strong></li>";
						}

						var sArr = data.categoryhtml.split(',');
						var cArr = data.categoryhtmlcode.split(',');
						promotionDetailhelphtml += '<li><div style="border-left:1px #ececec;background-color:#f2f2f2;border-top:2px #eaeaea;padding:5px; width:100%; height:50px; border:0px;overflow:auto" class="" readonly>';
						for(var ii = 0;ii<sArr.length;ii++){
							promotionDetailhelphtml += "- <a href='../goods/catalog?code="+cArr[ii]+"' target='_blank' >"+sArr[ii]+"</a><br />";
						}
						promotionDetailhelphtml += "</div></li>";
					}
				}
			}

			promotionDetailhelphtml +=  "</ul></div>";
			var promotionwidth = ($("div#promotionalertDialog").width()>300)?$("div#promotionalertDialog").width()+100:400;
			var promotionheight = ($("div#promotionalertDialog").height()>100)?$("div#promotionalertDialog").height()+300:400;
			if(data.result){
				//코드할인<span class="desc" >코드할인이 적용되었습니다.</span>
				var title = getAlert('os202');
			}else{
				//코드할인<span class="desc" >코드할인이 적용되지 않았습니다.</span>
				var title = getAlert('os203');
			}
			if( data.result == false ) {
				openDialogAlerttitle(title,promotionDetailhelphtml,promotionwidth,promotionheight,function(){});
			}else{
				openDialogAlerttitle(title,promotionDetailhelphtml,promotionwidth,promotionheight,function(){$(".cartPromotionTh").show();$(".cartPromotionTd").show();$("#pricePromotionTd").show();$(".cartpromotioncodedellay").show();$(".cartpromotioncodeinputlay").hide();});
				$(".cartPromotionTh").show();$(".cartPromotionTd").show();$("#pricePromotionTd").show();$(".cartpromotioncodedellay").show();$(".cartpromotioncodeinputlay").hide();
			}
			order_price_calculate();
		}
	});
}



/* 프로모션코드 초기화하기 */
function getPromotionCartDel(){
	$.ajax({
		'url' : '/promotion/getPromotionCartDel',
		'cache': false,
		'success' : function(){
			$(".cartPromotionTh").hide();
			$(".cartPromotionTd").hide();
			$("#pricePromotionTd").hide();
			$(".cartpromotioncodedellay").hide();
			$(".cartpromotioncodeinputlay").show();
			order_price_calculate();
			//미적용 처리되었습니다.
			openDialogAlert(getAlert('os039'), 400, 150, function(){});
		}
	});
}

// 단독쿠폰 선택 여부
function chkCouponSameTimeUse(){
	var chkCouponSameTimeUse	= false;

	$(".coupon_select").each(function(){
		if( $(this).find("option:selected").attr('couponsametime') == 'N' )
			chkCouponSameTimeUse	= true;
	});
	$(".shipping_coupon_select").each(function(){
		if( $(this).find("option:selected").attr('couponsametime') == 'N' )
			chkCouponSameTimeUse	= true;
	});

	if	(chkCouponSameTimeUse)	$.cookie( "couponsametimeuse", true );
	else						$.cookie( "couponsametimeuse", null );

	return chkCouponSameTimeUse;
}


//상품쿠폰선택
function getCouponselectnew(e){
	var obj = $(e);

	if( obj.find("option:selected").attr("value") ) {
		var oldidx = obj.parent().next().find("span").attr("oldidx");
		var oldsale = obj.parent().next().find("span").attr("oldsale");

		if( obj.find("option:selected").attr('couponsametime') == 'N' ) {//단독쿠폰
			if( $.cookie( "couponsametimeuse") ) {
				//이전에 적용한 쿠폰은 단독으로만 사용가능한 쿠폰입니다.<br/>본 쿠폰을 사용하시면 이전에 적용된 쿠폰은 모두 해제 됩니다. 적용하시겠습니까?
				var msg = getAlert('os204');
			}else{
				//본 쿠폰은 다른 쿠폰과 함께 사용할 수 없는 단독사용쿠폰입니다.<br/>적용하시겠습니까?
				var msg = getAlert('os205');
			}
			openDialogConfirm(msg,400,150,function(){
				getCouponsametimeselect(obj,'goods');//선택해제
				getCouponselectreal(obj);//단독쿠폰 중복쿠폰여부
				$.cookie( "couponsametimeuse", true );
				obj.closest('div').find("button[name='couponInfoButton']").css("background-color","");
				obj.closest('div').find("button[name='couponInfoButton']").addClass('ordercoupongoodsreviewbtn');
			},function(){
				if(oldidx){
					obj.find("option").eq(oldidx).attr("selected",true);
					obj.closest('div').find("span.sale").html( get_currency_price( oldsale,2,'basic' ) );
				}else{
					obj.val("").prop("selected", true); //IE7
					obj.find("option:selected").attr("selected",false);
					obj.closest('div').find("span.sale").html( get_currency_price( 0,2,'basic' ) );
				}
				obj.closest('div').find("button[name='couponInfoButton']").css("background-color","#D9D9D9");
				obj.closest('div').find("button[name='couponInfoButton']").removeClass('ordercoupongoodsreviewbtn');
				return false;
			});
		}else{//단독쿠폰아닌경우
			if( $.cookie( "couponsametimeuse") ) {//이전에 단독쿠폰 선택된 경우
				//이전에 적용한 쿠폰은 단독으로만 사용가능한 쿠폰입니다.<br/>본 쿠폰을 사용하시면 이전에 적용된 쿠폰은 모두 해제 됩니다. 적용하시겠습니까?
				var msg = getAlert('os204');
				openDialogConfirm(msg,400,150,function() {
					getCouponsametimeselect(obj,'goods');//선택해제
					getCouponselectreal(obj);
					$.cookie( "couponsametimeuse", null );
					obj.closest('div').find("button[name='couponInfoButton']").css("background-color","");
					obj.closest('div').find("button[name='couponInfoButton']").addClass('ordercoupongoodsreviewbtn');
				},function(){
					if(oldidx){
						obj.find("option").eq(oldidx).attr("selected",true);
						obj.closest('div').find("span.sale").html( get_currency_price( oldsale,2,'basic' ) );
					}else{
						obj.val("").prop("selected", true); //IE7
						obj.find("option:selected").attr("selected",false);
						obj.closest('div').find("span.sale").html( get_currency_price( 0,2,'basic' ) );
					}
					obj.closest('div').find("button[name='couponInfoButton']").css("background-color","#D9D9D9");
					obj.closest('div').find("button[name='couponInfoButton']").removeClass('ordercoupongoodsreviewbtn');
					return false;
				});
			}else{
				getCouponselectreal(obj);
			}
		}
	}else{
		obj.val("").prop("selected", true); //IE7
		obj.find("option").attr("selected",false); //선택제외
		obj.closest('div').find("span.sale").html( get_currency_price( 0,2,'basic' ) );
		obj.closest('div').find("button.ordercoupongoodsreviewbtn").attr("download_seq",'');
		// 중복으로 선택된 다른 단독쿠폰이 있는지 체크
		var couponsame = 'Y';
		$(".coupon_select").each( function() {
			if ( $(this).find("option:selected").attr('couponsametime') == 'N') couponsame = 'N';
		});
		if( $.cookie( "couponsametimeuse") && couponsame == 'Y') {
			$.cookie( "couponsametimeuse", null );
		}
		obj.closest('div').find("button[name='couponInfoButton']").css("background-color","#D9D9D9");
		obj.closest('div').find("button[name='couponInfoButton']").removeClass('ordercoupongoodsreviewbtn');
	}
}

//배송비쿠폰선택
function getShippingCouponselectnew(e) {
	var obj = $(e); 
	if( obj.find("option:selected").attr("value") ) {
		var oldidx = obj.find("option").attr("oldidx");
		var oldsale = obj.find("option").attr("oldsale");
		if(!oldidx) {
			//obj.find("option").attr("oldidx", obj.find("option:selected").index() );
			//obj.find("option").attr("oldsale",obj.find("option:selected").attr("sale"));
		}

		if( obj.find("option:selected").attr('couponsametime') == 'N' ) {//단독쿠폰
			if( $.cookie( "couponsametimeuse") ) {
				//이전에 적용한 쿠폰은 단독으로만 사용가능한 쿠폰입니다.<br/>본 쿠폰을 사용하시면 이전에 적용된 쿠폰은 모두 해제 됩니다. 적용하시겠습니까?
				var msg = getAlert('os204');
			}else{
				//본 쿠폰은 다른 쿠폰과 함께 사용할 수 없는 단독사용쿠폰입니다.<br/>적용하시겠습니까?
				var msg = getAlert('os205');
			}
			openDialogConfirm(msg,400,150,function(){
				getCouponshsametimeselect(obj,'');//선택해제
				getCouponshselectreal(obj);//단독쿠폰 중복쿠폰여부
				$.cookie( "couponsametimeuse", true );
				return true;
			},function(){
				if(oldidx){
					obj.find("option").eq(oldidx).attr("selected",true);
					obj.closest('div').find("span.shipping_sale").html( get_currency_price( oldsale,'','basic' ) );
				}else{
					obj.val("").prop("selected", true); //IE7
					obj.find("option:selected").attr("selected",false);
					obj.closest('div').find("span.shipping_sale").html( get_currency_price( 0,'','basic' ) );
				}
				return false;});
		}else{//단독쿠폰아닌경우
			if( $.cookie( "couponsametimeuse") ) {//이전에 단독쿠폰 선택된 경우
				//이전에 적용한 쿠폰은 단독으로만 사용가능한 쿠폰입니다.<br/>본 쿠폰을 사용하시면 이전에 적용된 쿠폰은 모두 해제 됩니다. 적용하시겠습니까?
				var msg = getAlert('os204');
				openDialogConfirm(msg,400,150,function() {
					getCouponshsametimeselect(obj,'');//선택해제
					getCouponshselectreal(obj);
					$.cookie( "couponsametimeuse", null );
					return true;
				},function(){
					if(oldidx){
						obj.find("option").eq(oldidx).attr("selected",true);
						obj.closest('div').find("span.shipping_sale").html( get_currency_price( oldsale ,'','basic') );
					}else{
						obj.val("").prop("selected", true); //IE7
						obj.find("option:selected").attr("selected",false);
						obj.closest('div').find("span.shipping_sale").html( get_currency_price( 0 ,'','basic') );
					}
					return false;
				});
			}else{
				//getCouponshselectreal(obj);
			}
		}
	}else{
		obj.val("").prop("selected", true); //IE7
		obj.find("option").attr("selected",false); //선택제외
		obj.closest('div').find("span.shipping_sale").html( get_currency_price( 0,'','basic' ) );
		if( $.cookie( "couponsametimeuse") ) {
			$.cookie( "couponsametimeuse", null );
		}
	}
	//쿠폰정보정의
	var download_seq = obj.closest('div').find("select.shipping_coupon_select option:selected").val();
	if(download_seq) { 
		var shipping_sale = obj.closest('div').find("select.shipping_coupon_select option:selected").attr("sale");
		obj.closest('div').find(".shippingcoupongoodsreviewbtn").attr("download_seq",download_seq);
		obj.closest('div').find("button[name='couponInfoButton']").css("background-color","");
		obj.closest('div').find("button[name='couponInfoButton']").addClass('shippingcoupongoodsreviewbtn');
		obj.closest('div').find(".shipping_sale").html( get_currency_price( shipping_sale ,'','basic') );
	}else{ 
		obj.closest('div').find(".shippingcoupongoodsreviewbtn").attr("download_seq",'');
		obj.closest('div').find("button[name='couponInfoButton']").css("background-color","#D9D9D9");
		obj.closest('div').find("button[name='couponInfoButton']").removeClass('shippingcoupongoodsreviewbtn');
		obj.closest('div').find("span.shipping_sale").html( get_currency_price( 0,'','basic' ) );
	}
}

//단독쿠폰으로 중복이 아닌경우 선택된 정보이외에 모두 제외
function getCouponsametimeselect(obj, coupontype){
	if( coupontype == 'goods') {//상품쿠폰은 배송비쿠폰제외
		$("select.shipping_coupon_select").each(function(){
			//if( !$(this).find("option:selected").val() ) return true; //continue;
			$(this).val("").prop("selected", true); //IE7
			$(this).find("option").attr("selected",false); //선택제외
			$(".shippingcoupongoodsreviewbtn").attr("download_seq",'');
			$(".shipping_coupon_sale").html( get_currency_price( 0 ,'','basic') );
			$(this).closest('div').find("button[name='couponInfoButton']").css("background-color","#D9D9D9");
			$(this).closest('div').find("button[name='couponInfoButton']").removeClass('ordercoupongoodsreviewbtn');
		});
	}
	$("select.coupon_select").each(function(){
		if( !$(this).find("option:selected").val() ) return true; //continue;
		if( obj.attr('id') != $(this).attr("id") ) {
			$(this).val("").prop("selected", true); //IE7
			$(this).find("option").attr("selected",false); //선택제외
			$(this).closest('div').find("span.sale").html( get_currency_price( 0,'','basic' ) ); 
			$(this).closest('div').find("button.ordercoupongoodsreviewbtn").attr("download_seq",'');
			$(this).closest('div').find("button[name='couponInfoButton']").css("background-color","#D9D9D9");
			$(this).closest('div').find("button[name='couponInfoButton']").removeClass('ordercoupongoodsreviewbtn');
		}
	});
}


//단독쿠폰으로 중복이 아닌경우 선택된 정보이외에 모두 제외
function getCouponshsametimeselect(obj, coupontype){
		$("select.shipping_coupon_select").each(function(){
			if( !$(this).find("option:selected").val() ) return true; //continue;
			if( obj.attr('id') != $(this).attr("id") ) {
				$(this).val("").prop("selected", true); //IE7
				$(this).find("option").attr("selected",false); //선택제외
			}
		});

	$("select.coupon_select").each(function(){
		$(this).val("").prop("selected", true); //IE7
		$(this).find("option").attr("selected",false); //선택제외
		$(this).closest('div').find("span.sale").html( get_currency_price( 0 ,'','basic') );
	});
}



//쿠폰선택
function getCouponselectreal(obj) {
	$("select.coupon_select").each(function(idx){//
		if( obj.find("option:selected").attr('duplication') == 1 ) {//중복쿠폰
			if( obj.attr('id') != $(this).attr("id") && !$(this).find("option:selected").val() ) {//선택하지 않는 상품인경우
				$("select#"+$(this).attr("id")+" option[value='"+obj.find("option:selected").val()+"']").attr("selected",true);
			}
			if( obj.attr('id') == $(this).attr("id") )  {
				$(this).closest('div').find("span.sale").attr("oldidx",obj.find("option:selected").index());
				$(this).closest('div').find("span.sale").attr("oldsale", $(this).find("option:selected").attr("sale"));
			}
			$(this).closest('div').find("span.sale").html( get_currency_price( $(this).find("option:selected").attr("sale"),2,'basic' ) );
			$(this).closest('div').find("button.ordercoupongoodsreviewbtn").attr("download_seq",$(this).find("option:selected").val());
		}else{
			if( obj.attr('id') != $(this).attr("id") && obj.find("option:selected").attr("value") ){
				if(obj.find("option:selected").val() == $(this).find("option:selected").val()){
					$(this).find("option").eq(0).attr("selected",true);
					$(this).closest('div').find("button[name='couponInfoButton']").css("background-color","#D9D9D9");
					$(this).closest('div').find("button[name='couponInfoButton']").removeClass('ordercoupongoodsreviewbtn');
				}
			}
			if( obj.attr('id') == $(this).attr("id") ) {
				$(this).closest('div').find("span.sale").attr("oldidx",$(this).find("option:selected").index());
				$(this).closest('div').find("span.sale").attr("oldsale",$(this).find("option:selected").attr("sale"));
			}
			$(this).closest('div').find("span.sale").html( get_currency_price( $(this).find("option:selected").attr("sale"),2,'basic' ) );
			$(this).closest('div').find("button.ordercoupongoodsreviewbtn").attr("download_seq",$(this).find("option:selected").val());
		}
	});
	obj.closest('div').find("button[name='couponInfoButton']").css("background-color","");
	obj.closest('div').find("button[name='couponInfoButton']").addClass('ordercoupongoodsreviewbtn');
}

//배송비쿠폰선택
function getCouponshselectreal(obj) {
	$("select.shipping_coupon_select").each(function(idx){//
		if( obj.find("option:selected").attr('duplication') == 1 ) {//중복쿠폰
			if( obj.attr('id') != $(this).attr("id") && !$(this).find("option:selected").val() ) {//선택하지 않는 상품인경우
				$("select#"+$(this).attr("id")+" option[value='"+obj.find("option:selected").val()+"']").attr("selected",true);
			}

			if( obj.attr('id') == $(this).attr("id") )  {
				$(this).closest('div').find("span.shipping_sale").attr("oldidx",obj.find("option:selected").index());
				$(this).closest('div').find("span.shipping_sale").attr("oldsale", $(this).find("option:selected").attr("sale"));
			}
			$(this).closest('div').find("span.shipping_sale").html( get_currency_price( $(this).find("option:selected").attr("sale"),'','basic' ) );
			$(this).closest('div').find("button.ordercoupongoodsreviewbtn").attr("download_seq",$(this).find("option:selected").val());
		}else{

			if( obj.attr('id') != $(this).attr("id") && obj.find("option:selected").attr("value") ){
				if(obj.find("option:selected").val() == $(this).find("option:selected").val()){
					$(this).find("option").eq(0).attr("selected",true);
				}
			}

			if( obj.attr('id') == $(this).attr("id") ) {
				$(this).closest('div').find("span.shipping_sale").attr("oldidx",$(this).find("option:selected").index());
				$(this).closest('div').find("span.shipping_sale").attr("oldsale",$(this).find("option:selected").attr("sale"));
			}
			$(this).closest('div').find("span.shipping_sale").html( get_currency_price( $(this).find("option:selected").attr("sale"),'','basic' ) );
			$(this).closest('div').find("button.ordercoupongoodsreviewbtn").attr("download_seq",$(this).find("option:selected").val());

		}
	});
}




// 쿠폰 사용 취소
function cancelCouponSelect(obj){
	obj.val("").prop("selected", true); //IE7
	obj.find("option:selected").attr("selected",false);
	obj.closest('div').find("span.sale").html( get_currency_price( 0 ,'','basic') ); 
	obj.closest('div').find("button.ordercoupongoodsreviewbtn").attr("download_seq",''); 
	obj.blur();
}

// 배송 쿠폰 사용 취소
function cancelShippingCouponSelect(obj){
	obj.find("option").eq(0).attr("selected",true);
	obj.closest('div').find("span.shipping_sale").html( get_currency_price( 0 ,'','basic') );
}

// 쿠폰선택
function getCouponselect(e){
	var obj						= $(e);
	// 쿠폰 선택 시
	if( obj.find("option:selected").val() ) {
		// 단독 쿠폰의 경우
		if( obj.find("option:selected").attr('couponsametime') == 'N' ) {
			//본 쿠폰은 다른 쿠폰과 함께 사용할 수 없는 단독사용쿠폰입니다.<br/>적용하시겠습니까?
			var msg = getAlert('os205');
		}else if( chkCouponSameTimeUse() ){
			//이전에 적용한 쿠폰은 단독으로만 사용가능한 쿠폰입니다.<br/>본 쿠폰을 사용하시면 이전에 적용된 쿠폰은 모두 해제 됩니다. 적용하시겠습니까?
			var msg = getAlert('os204');
		}

		if	(msg)	openDialogConfirm(msg,400,150,function(){useCouponSelect(obj)},function(){cancelCouponSelect(obj)});
		else		useCouponSelect(obj);
	}else{
		obj.closest('div').find("span.sale").html( get_currency_price( 0 ) );
	}
}

/**
 * 쿠폰을 ajax로 검색한다.
 */
function getCouponAjaxList() {
	var f = $("form#orderFrm");
	var queryString = f.serialize();
	var mode	= $("input[name='mode']").val();
	$.ajax({
		type: 'post',
		url: '/order/settle_coupon?mode='+mode,
		data: queryString,
		dataType: 'json',
		cache: false,
		success: function(data) {
			if( data ){
				if( data.coupon_error ){
					$('#coupon_goods_lay').html('');
					$('#coupon_shipping_lay').html(''); 
					$('#coupon_ordersheet_lay').html('');
					closeDialog("coupon_apply_dialog");
					//적용 가능한 쿠폰이 없습니다
					openDialogAlert(getAlert('os004'),'400','140');
				}else{
					//쿠폰 적용 하기
					openDialog(getAlert('os005'), "coupon_apply_dialog", {"width":500,"height":500});
					if(data.coupongoods){
						$('#coupon_goods_lay').html(data.coupongoods);
					}
					if(data.couponshipping){
						$('#coupon_shipping_lay').html(data.couponshipping);
					}
					if(data.couponordersheet){
						$('#coupon_ordersheet_lay').html(data.couponordersheet);
					}
				}
			}
		}
	});
}

// 쿠폰사용
function getCouponuse(seqno){
	$(".couponlay_"+seqno).hide();
	$.getJSON('/order/coupon/goods_coupon_max?no='+seqno, function(data) {
		if(data){
			$(".couponlay_"+seqno).show();
			if(data.sale_type == 'won'){
				$(".couponlay_"+seqno+" .cb_percent").html('▶'+get_currency_price(data.won_goods_sale,2,'basic') + '<br/>쿠폰받기');
			}else{
				$(".couponlay_"+seqno+" .cb_percent").html('▶'+get_currency_price(data.percent_goods_sale,'','basic') + '%<br/>쿠폰받기');
			}
		}
	});
}

// 쿠폰재설정
function resetCouponSelect(e) {
	var obj = $(e); 
	if( obj.find("option:selected").val() == "" ) {
		$(obj).closest('div').find("button[name='couponInfoButton']").css("background-color","#D9D9D9");
		$(obj).closest('div').find("button[name='couponInfoButton']").removeClass('ordercoupongoodsreviewbtn');
		$(obj).closest('div').find("button[name='couponInfoButton']").removeClass('shippingcoupongoodsreviewbtn');
	} else if (obj.find("option:selected").attr('couponsametime') == 'N') {
		$.cookie( "couponsametimeuse", true );
	}

}

function getCouponDownlayerclose(){
	$('#couponDownloadDialog').dialog('close');
}

function inicis_mobile_popup(){
	var xpos = 100;
	var ypos = 100;
	var position = "top=" + ypos + ",left=" + xpos;
	var features = position + ", width=320, height=440";
	var wallet = window.open("", "BTPG_WALLET", features);
	wallet.focus();
}

function mobile_popup(){
	var xpos = 100;
	var ypos = 100;
	var position = "top=" + ypos + ",left=" + xpos;
	var features = position + ", width=320, height=440";
	var wallet = window.open("", "tar_opener", features);
	wallet.focus();
}
function use_cash(){
	if(parseFloat($("input[name='cash_view']").val()) <= 0 ){
		//예치금를 정확히 입력해 주세요.
		openDialogAlert(getAlert('os044'),'400','140');
		return false;
	}

	if(parseFloat($("input[name='cash_view']").val()) >  0 ){
		$("input[name='cash']").val( $("input[name='cash_view']").val() );
		$(".cash_input_button").hide();
		$(".cash_all_input_button").hide();
		$(".cash_cancel_button").show();
	}
	order_price_calculate();
}

function use_all_cash(){
	$("input[name='cash_all']").val('y');
	$("input[name='cash']").val(0);
	$(".cash_input_button").hide();
	$(".cash_all_input_button").hide();
	$(".cash_cancel_button").show();

	order_price_calculate();
}

function cancel_cash(){
	$("input[name='cash']").val(0);
	$("input[name='cash_view']").val(0);
	$(".cash_cancel_button").hide();
	$(".cash_input_button").show();
	$(".cash_all_input_button").show();
	$(".cach_voucherchk").show();
	$("select[name='typereceipt']").find("[value=2]").removeAttr("disabled");
	$("#priceCashTd").hide();
	order_price_calculate();
}

function use_emoney(){
	if($("input[name='emoney_view']").val().trim() == "" ){
		//마일리지을 정확히 입력해 주세요.
		openDialogAlert(getAlert('os040'),'400','140');
		return false;
	}
	if(parseFloat($("input[name='emoney_view']").val().trim()) > 0){
		$("input[name='emoney']").val( $("input[name='emoney_view']").val() );
		$(".emoney_input_button").hide();
		$(".emoney_all_input_button").hide();
		$(".emoney_cancel_button").show();
	}

	// 마일리지액 제한 조건 알림 추가 leewh 2014-07-01
	if ($("#default_reserve_limit").length) {
		if ($("#default_reserve_limit").val()==1) {
			//마일리지 사용으로 마일리지을 지급하지 않습니다.
			alert(getAlert('os041'));
		} else if ($("#default_reserve_limit").val()==2) {
			//기대마일리지에서 사용한 마일리지을 제외하고 마일리지을 지급합니다.
			alert(getAlert('os042'));
		} else if ($("#default_reserve_limit").val()==3) {
			//사용한 마일리지을 제외하고 결제금액을 기준으로 마일리지을 지급합니다.
			alert(getAlert('os043'));
		}
	}
	order_price_calculate();
}

function use_all_emoney(){
	$("input[name='emoney_all']").val('y');
	$("input[name='emoney']").val(0);
	$(".emoney_input_button").hide();
	$(".emoney_all_input_button").hide();
	$(".emoney_cancel_button").show();

	// 마일리지액 제한 조건 알림 추가 leewh 2014-07-01
	if ($("#default_reserve_limit").length) {
		if ($("#default_reserve_limit").val()==1) {
			//마일리지 사용으로 마일리지을 지급하지 않습니다.
			alert(getAlert('os041'));
		} else if ($("#default_reserve_limit").val()==2) {
			//기대마일리지에서 사용한 마일리지을 제외하고 마일리지을 지급합니다.
			alert(getAlert('os042'));
		} else if ($("#default_reserve_limit").val()==3) {
			//사용한 마일리지을 제외하고 결제금액을 기준으로 마일리지을 지급합니다.
			alert(getAlert('os043'));
		}
	}
	order_price_calculate();
}

function cancel_emoney(){
	$("input[name='emoney']").val(0);
	$("input[name='emoney_view']").val(0);
	$(".emoney_cancel_button").hide();
	$(".emoney_input_button").show();
	$(".emoney_all_input_button").show();
	$(".cach_voucherchk").show();
	$("select[name='typereceipt']").find("[value=2]").removeAttr("disabled");
	$("#priceEmoneyTd").hide();
	order_price_calculate();
}

function limit_chk(gift_seq, obj){

	var f = eval("document.orderFrm.gift_"+gift_seq)
	if(!f){
		f = $("input[name='gift_"+gift_seq+"[]']");
	}
	var cnt = 0;
	var f2 = eval("document.orderFrm.gift_"+gift_seq+"_limit");
	var limitCnt = f2.value;

	for(i=0; i<f.length; i++){
		if(f[i].checked == true){
			cnt++;
		}
	}

	if(cnt > limitCnt){
		//사은품 이벤트 타이틀
		var gift_tit = $(".gift_name_" + gift_seq).html();
		if(gift_tit) gift_tit = '[' + gift_tit + '] ';

		//사은품을 최대 "+limitCnt+"개까지 선택하실 수 있습니다.
		alert(gift_tit + getAlert('os122',limitCnt));
		obj.checked = false;
	}

}

var exception_sale	= 0;
function exception_saleprice(sale){
	if	(exception_sale != sale){
		exception_sale	= sale;
		//할인금액이 상품금액을 초과하여 일부할인이 제외되었습니다.
		openDialogAlert(getAlert('os120'), 500, 150);
	}
}

// 수동 국가변경 :: 2016-08-04 lwh
function chg_shipping_nation(nation,nation_key){
	$("#address_nation").val(nation).trigger("change"); // 국가 설정값 변경
	$("#address_nation_key").val(nation_key);
	check_shipping_nation(); // 국내/해외 체크
}

// 배송주소록 팝업 창 :: 2016-08-02 lwh
function popDeliveryaddress(page){
	var member_seq = $("input[name='member_seq']").val();
	$.ajax({
		'url'	: '/order/pop_delivery_address',
		'data'	: {'page':page,'member_seq':member_seq},
		'type'	: 'get',
		'dataType': 'text',
		'success': function(html) {
			if(html){
				$("div#delivery_address_dialog").html(html);
				if(page != 'reload'){
					//주소록
					openDialog(getAlert('os185'), "delivery_address_dialog", {"width":730,"height":480});
				}
			}else{
				//주소록을 로드하지 못했습니다.
				alert(getAlert('os182'));
				document.location.reload();
			}
		}
	});
}

// 쿠폰 초기화 :: 2017-07-11 lwh
function reset_coupon(){
	openDialogAlert(getAlert('os238'),400,150);
	//$(".coupon_download_input").val('');
	$(".shippingcoupon_download_input").val('');
	cancel_emoney();
	cancel_cash();
	order_price_calculate();
}

// 배송 view 결정 - 필수 배송호출 :: 2017-05-15 lwh
function set_shipping(type){

	// 각 타입별 배송지 view
	$(".goods_delivery_info").hide();
	$(".direct_store_info").hide();
	$(".coupon_delivery_info").hide();
	if(typeof(is_goods)!='undefined' && is_goods || typeof(is_direct_store)!='undefined' && is_direct_store)
		$(".goods_delivery_info").show();
	if(typeof(is_coupon)!='undefined' && is_coupon)
		$(".coupon_delivery_info").show();

	// 입력 또는 view 결정
	if(type == 'view'){
		$(".delivery_member").show();
		$(".delivery_input").hide();
	}else{
		$(".delivery_member").hide();
		$(".delivery_input").show();
	}

	// 국가별 결정
	var international = $("#address_nation").val();
	if(international == 'KOREA'){
		$(".international").hide();
	}else{
		$(".domestic").hide();
	}
}

// 엑심베이 팝업
function eximbapy_popup(){
	window.open("", "payment2", "scrollbars=yes,status=no,toolbar=no,resizable=yes,location=no,menu=no,width=400,height=420");
}

function check_input_value(obj){
	ret = false;
	$(obj).find('input').each(function(){
		if	($(this).val())
			ret = true;
	});
	return ret;
}

/**
 * 쿠폰 정보 버튼 활성화 처리
 * obj : selectbox 엘리먼트
 * mode : goods : 상품쿠폰, shipping : 배송비쿠폰, ordersheet : 주문서쿠폰
 * activeMode : true : 활성화, false : 비활성화
 * download_seq : 쿠폰 번호
 */
function proc_active_coupon_info_btn(obj, mode, activeMode, download_seq){
	if(typeof(mode) === 'undefined'){
		mode = 'goods';
	}
	if(typeof(activeMode) === 'undefined'){
		activeMode = false;
	}
	var arr_class = {
		'goods'			: 'ordercoupongoodsreviewbtn'
		, 'shipping'	: 'shippingcoupongoodsreviewbtn'
		, 'ordersheet'	: 'ordersheetcoupongoodsreviewbtn'
	};
	if(activeMode == true){
		obj.closest('div').find("button[name='couponInfoButton']").css("background-color","");
		obj.closest('div').find("button[name='couponInfoButton']").addClass(arr_class[mode]);
		obj.closest('div').find("."+arr_class[mode]).attr("download_seq",download_seq);
	}else if(activeMode == false){
		obj.closest('div').find("."+arr_class[mode]).attr("download_seq",'');
		obj.closest('div').find("button[name='couponInfoButton']").css("background-color","#D9D9D9");
		obj.closest('div').find("button[name='couponInfoButton']").removeClass(arr_class[mode]);
	}
}

/**
 * 쿠폰 단독 사용 선택 시 처리
 * obj : selectbox 엘리먼트
 * mode : goods : 상품쿠폰, shipping : 배송비쿠폰, ordersheet : 주문서쿠폰
 * confirmMode : succ : 성공, fail: 실패
 * couponsametimeuse : 단독 사용 처리
 */
function proc_couponsametime_selected(obj, mode, confirmMode, couponsametimeuse){
	if(typeof(mode) === 'undefined'){
		mode = 'goods';
	}
	if(typeof(confirmMode) === 'undefined'){
		confirmMode = 'succ';
	}
	var arr_class = {
		'goods'			: 'ordercoupongoodsreviewbtn'
		, 'shipping'	: 'shippingcoupongoodsreviewbtn'
		, 'ordersheet'	: 'ordersheetcoupongoodsreviewbtn'
	};
	var arr_sel_class = {
		'goods'			: 'coupon_select'
		, 'shipping'	: 'shipping_coupon_select'
		, 'ordersheet'	: 'ordersheet_coupon_select'
	};
	if(confirmMode=="succ"){
		for(var tmp_mode in arr_sel_class){
			$("select."+arr_sel_class[tmp_mode]).each(function(){
				if( !$(this).find("option:selected").val() ) return true; //continue;
				if( obj.attr('id') != $(this).attr("id") ) {
					$(this).val("").prop("selected", true); //IE7
					$(this).find("option").attr("selected",false); //선택제외
					
					proc_active_coupon_info_btn($(this), mode, false, '');
				}
			});
		}
		
		proc_duplication_selected(obj, mode); //중복쿠폰처리
		$.cookie( "couponsametimeuse", couponsametimeuse );
	}else if(confirmMode=="fail"){
		obj.val("");
		proc_active_coupon_info_btn(obj, mode, false, obj.find("option:selected").val());
	}
}

/**
 * 쿠폰 중복 사용 선택 시 처리
 * obj : selectbox 엘리먼트
 * mode : goods : 상품쿠폰, shipping : 배송비쿠폰, ordersheet : 주문서쿠폰
 */
function proc_duplication_selected(obj, mode){
	if(typeof(mode) === 'undefined'){
		mode = 'goods';
	}
	var arr_class = {
		'goods'			: 'ordercoupongoodsreviewbtn'
		, 'shipping'	: 'shippingcoupongoodsreviewbtn'
		, 'ordersheet'	: 'ordersheetcoupongoodsreviewbtn'
	};
	var arr_sel_class = {
		'goods'			: 'coupon_select'
		, 'shipping'	: 'shipping_coupon_select'
		, 'ordersheet'	: 'ordersheet_coupon_select'
	};
	
	$("select."+arr_sel_class[mode]).each(function(idx){//
		if( obj.find("option:selected").attr('duplication') == 1 ) {//중복쿠폰
			if( obj.attr('id') != $(this).attr("id") && !$(this).find("option:selected").val() ) {//선택하지 않는 상품인경우
				// 동일쿠폰을 선택할 수 있는지 확인
				var dub_coupon = $("select#"+$(this).attr("id")+" option[value='"+obj.find("option:selected").val()+"']");
				if(dub_coupon.length>0){
					dub_coupon.attr("selected",true);
					proc_active_coupon_info_btn($(this), mode, true, obj.find("option:selected").val());
				}
			}
		} else if( obj.attr('id') != $(this).attr("id") && obj.find("option:selected").attr("value") 
				&& obj.find("option:selected").val() == $(this).find("option:selected").val() ){
			$(this).find("option").eq(0).attr("selected",true);
		}
	});
}
/**
 * 쿠폰 selectbox 변경 통합 기능
 * e : selectbox 객체
 * mode : goods : 상품쿠폰, shipping : 배송비쿠폰, ordersheet : 주문서쿠폰
 */
function changeCouponSelectbox(e, mode) {
	if(typeof(mode) === 'undefined'){
		mode = 'goods';
	}
	var arr_class = {
		'goods'			: 'ordercoupongoodsreviewbtn'
		, 'shipping'	: 'shippingcoupongoodsreviewbtn'
		, 'ordersheet'	: 'ordersheetcoupongoodsreviewbtn'
	};
	var obj = $(e); 
	//쿠폰정보정의
	var download_seq = obj.find("option:selected").attr("value");
	
	if( download_seq ) {
		proc_active_coupon_info_btn(obj, mode, true, download_seq);
		
		var msg = "";
		var couponsametimeuse = null;
		
		if( obj.find("option:selected").attr('couponsametime') == 'N' ) {//단독쿠폰
			couponsametimeuse = true;
			//본 쿠폰은 다른 쿠폰과 함께 사용할 수 없는 단독사용쿠폰입니다.<br/>적용하시겠습니까?
			msg = getAlert('os205');
		}else{
		}

		if( $.cookie( "couponsametimeuse") ) {//이전에 단독쿠폰 선택된 경우
			//이전에 적용한 쿠폰은 단독으로만 사용가능한 쿠폰입니다.<br/>본 쿠폰을 사용하시면 이전에 적용된 쿠폰은 모두 해제 됩니다. 적용하시겠습니까?
			msg = getAlert('os204');
		}else{
		}

		if(msg!=""){
			openDialogConfirm(msg,400,150,function() {
				proc_couponsametime_selected(obj, mode, 'succ', couponsametimeuse);
				return true;
			},function(){
				proc_couponsametime_selected(obj, mode, 'fail', couponsametimeuse);
				return false;
			});
		}else{
			proc_duplication_selected(obj, mode);
		}
	}else{
		proc_active_coupon_info_btn(obj, mode, false, download_seq);
		
		// 쿠폰을 미선택으로 변경할 경우 
		// 모든 단독 쿠폰을 미선택으로 변경했다면 쿠키에 담아뒀던 단독 쿠폰 값을 제거한다.
		var couponsame = 'Y';
		$(".coupon_select, .shipping_coupon_select, .ordersheet_coupon_select").each( function() {
			if ( $(this).find("option:selected").attr('couponsametime') == 'N') couponsame = 'N';
		});
		if( $.cookie( "couponsametimeuse") && couponsame == 'Y') {
			$.cookie( "couponsametimeuse", null );
		}
	}
}

// 구버전 스킨 오류로 인해 임시로 작업 :: 2016-10-31 lwh
$(document).ready(function() {
	$("input[name='chkQuickAddress']").bind("change",function(){
		setTimeout(order_price_calculate, 1000);
	});
	$("select[name='chkQuickAddressLately']").bind("change",function(){
		setTimeout(order_price_calculate, 1000);
	});
});

/*결제사 외부 스크립트 호출 (절대 수정하지 마시오)*/
switch( gl_pg_company ) {
	case 'inicis' :
		$.getScript('https://stdpay.inicis.com/stdjs/INIStdPay.js');	
		break;
	case 'lg' :
		$.getScript('https://xpay.uplus.co.kr/xpay/js/xpay_crossplatform.js');	
		break;
	case 'kcp' :
		document.write( "<script type=\"text/javascript\" src=\"https://pay.kcp.co.kr/plugin/payplus_web.jsp\" charset=\"EUC-KR\" ></script>" );
		break;
	default :
		break;
}
/*결제사 외부 스크립트 호출 (절대 수정하지 마시오)*/