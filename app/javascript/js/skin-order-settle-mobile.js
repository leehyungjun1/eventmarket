if(is_file_facebook_tag == true){

		window.fbAsyncInit = function() {
			FB.init({
				appId      : plus_app_id, //App ID
				status     : true, // check login status
				cookie     : true, // enable cookies to allow the server to access the session
				xfbml      : true,  // parse XFBML,
				oauth      : true,
				version    : 'v'+APP_VER
			});
			if(fblike_ordertype && fblikesale == true){
				FB.getLoginStatus(function(response) {
					$("#fbloginlay").hide();
					if(fbuser){ $.ajax({'url' : '../sns_process/facebooklogincknone', 'type' : 'post'}); }
					if (response.status === 'connected') {
						var uid = response.authResponse.userID;
						var accessToken = response.authResponse.accessToken;
					}else if (response.status === 'not_authorized') {
						$("#fbloginlay").show();
					} else {
						$("#fbloginlay").show();
					}
				});
			}
			if(APP_USE == 'f'){
				// like 이벤트가 발생할때 호출된다.
				FB.Event.subscribe('edge.create', function(response) {
					//페이스북과 정보를 교환 중에 있습니다. 잠시만 기다려 주세요.
					$("#facebook_mgs").html(getAlert('os156'));
					if( HTTP_HOST == APP_DOMAIN ) {
						$.ajax({'url' : '../sns_process/facebooklikeck', 'type' : 'post', 'data' : {'mode':'like', 'product_url':response}, 'dataType': 'json','success': function(result){$("#facebook_mgs").html("");order_price_calculate();}});
					}else{
						var url = 'http://'+gl_sub_domain+'/sns_process/facebooklikeck?mode=like&firstmallcartid='+firstmallcartid+'&product_url='+response;
						$.getJSON(url + "&jsoncallback=?", function(res) {$("#facebook_mgs").html("");order_price_calculate();});
					}
				});

				// unlike 이벤트가 발생할때 호출된다.
				FB.Event.subscribe('edge.remove', function(response) {
					//페이스북과 정보를 교환 중에 있습니다. 잠시만 기다려 주세요.
					$("#facebook_mgs").html(getAlert('os156'));
					if( HTTP_HOST == APP_DOMAIN ) {
						$.ajax({'url' : '../sns_process/facebooklikeck', 'type' : 'post', 'data' : {'mode':'unlike', 'product_url':response}, 'dataType': 'json','success': function(result){$("#facebook_mgs").html("");order_price_calculate();}});
					}else{
						var url = 'http://'+gl_sub_domain+'/sns_process/facebooklikeck?mode=unlike&firstmallcartid='+firstmallcartid+'&product_url='+response;
						$.getJSON(url + "&jsoncallback=?", function(res) {$("#facebook_mgs").html("");order_price_calculate();});
					}
				});//
			}
		}
	}

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

	// 수동 국가변경 :: 2016-08-16 lwh
	function chg_shipping_nation(nation){
		$("#address_nation").val(nation); // 국가 설정값 변경
		$("#default_address_nation").val(nation); // 국가 설정값 변경
		check_shipping_nation(); // 국내/해외 체크
	}

	// 스킨 패치 관련 함수 :: 2018-03-19 lkh
	if(typeof(skin_order_settle_ver) == undefined){
		/* 스킨 패치 관련 함수 :: 2017-05-30 lwh */
		var skin_order_settle_ver = null;
	}

	/* required 체크 항목 재정의 시작 */
	var required_field		= new Array();
	var ticket_required_field		= new Array();

	required_field['KOREA_INPUT']		= [ 'recipient_input_user_name',
											'recipient_input_new_zipcode',
											'recipient_input_address',
											'recipient_input_address_detail',
											'recipient_input_cellphone[]'
											];
	required_field['INTERNATIONAL_INPUT'] = ['international_address_input',
											'international_town_city_input',
											'international_county_input',
											'international_postcode_input',
											'international_country_input',
											'international_recipient_cellphone_input[]'
											];
	required_field['KOREA']				= [ 'recipient_user_name',
											'recipient_new_zipcode',
											'recipient_address',
											'recipient_address_detail',
											'recipient_cellphone[]'
										];
	required_field['INTERNATIONAL']		= [ 'international_address',
											'international_town_city',
											'international_county',
											'international_postcode',
											'international_country',
											'international_recipient_cellphone[]'
										];
	required_field['ORDER']				= [ 'order_user_name',
											'order_email',
											'order_cellphone[]'
										];

	ticket_required_field['KOREA_INPUT']		= [ 'recipient_input_user_name',
											'recipient_input_email',
											'recipient_input_cellphone[]',
											];
	ticket_required_field['INTERNATIONAL_INPUT'] = [ 'international_recipient_cellphone_input[]',
											];
	ticket_required_field['KOREA']				= [ 'recipient_user_name',
											'recipient_email',
											'recipient_cellphone[]'
										];
	ticket_required_field['INTERNATIONAL']		= [ 'international_recipient_cellphone[]'
										];
	ticket_required_field['ORDER']				= [ 'order_user_name',
											'order_email',
											'order_cellphone[]'
										];
	function requiredChange(type,mode){

		var add_nation			= $("#address_nation").val();
		var address_type		= $("input[name='recipient_address_type']").val();
		var add_nation_tmp		= "";

		if(type == ''){
			if(is_goods)	type = 'delivery';
			else			type = 'ticket';
		}

		// 주소타입 예외처리
		if(address_type != $("input[name='recipient_input_address_type']").val()){
			address_type = $("input[name='recipient_input_address_type']").val();
			if(!address_type){
				if($("input[name='recipient_address_street']").hasClass('hide')){
					address_type == 'zibun';
				}else{
					address_type == 'street';
				}
			}
		}

		if(address_type == 'street' && is_goods == true){
			required_field['KOREA'][required_field['KOREA'].length] = 'recipient_address_street';
			required_field['KOREA_INPUT'][required_field['KOREA_INPUT'].length] = 'recipient_input_address_street';
		}

		if(add_nation != "KOREA"){ add_nation = "INTERNATIONAL"; }

		if(!is_members && is_goods){ // 실물상품 회원이 아닐때 예외상황 처리@2017-05-30
			mode = 'input';
			for (var i=0; required_field['KOREA'].length > i; i++ ){
				var fieldName = required_field['KOREA_INPUT'][i];
				if(fieldName){
					if(fieldName.indexOf('[]') > 0){
						$("input[name='"+fieldName+"']").each(function(idx, item){
							$("input[name='"+required_field['KOREA'][i]+"']").eq(idx).val($(item).val());
						});
					}else{
						$("input[name='"+required_field['KOREA'][i]+"']").val($("input[name='"+fieldName+"']").val());
					}
				}
			}
		}

		for(key in required_field){
			for(var i=0; i < required_field[key].length; i++){
				$("form[name='orderFrm'] input[name='"+required_field[key][i]+"']").removeAttr("required");
			}
		}

		if(is_goods == false) required_field = ticket_required_field;

		for(key in required_field){
			if	(typeof required_field[key] != 'object' && required_field[key].hasOwnProperty('length') == false)
				continue;
			for(var i=0; i < required_field[key].length; i++){
				$("input[name='"+required_field[key][i]+"']").removeAttr("required");
			}
			if(type != ""){
				if(type == "delivery"){
					if(mode == "input"){
						add_nation_tmp = add_nation + "_INPUT";
					}else{
						add_nation_tmp = add_nation;
					}
				}else{
					add_nation_tmp = "ORDER";
				}
				if(key == add_nation_tmp){
					for(var i=0; i < required_field[add_nation_tmp].length; i++){
						$("input[name='"+required_field[key][i]+"']").attr("required",true);
					}
				}
			}
		}
		if(type == ""){
			for(var i=0; i < required_field[add_nation].length; i++){
				$("input[name='"+required_field[add_nation][i]+"']").attr("required",true);
			}
		}

		for(var i=0; i < required_field['ORDER'].length; i++){
			$("input[name='"+required_field['ORDER'][i]+"']").attr("required",true);
		}
	}
	/* required 체크 항목 재정의 끝 */

	// 배송지/주문자 입력 창 수동 적용 :: 2016-08-16 lwh
	function succ_input(type){

		var cellphone	= new Array();
		var phone		= new Array();
		var check_type	= 'delivery_input';
		if(type != 'order'){ // 배송지 정보 수동적용
			requiredChange('delivery','input');	//배송지/주문자 입력창 필수 입력 재정의
		}else{
			requiredChange(type,'input');	//배송지/주문자 입력창 필수 입력 재정의
			check_type	= 'order_input';
		}
		if(validationCheck(check_type) != true) return false;
		if(type != 'order'){ // 배송지 정보 수동적용

			var add_nation					= $("#address_nation").val();
			var recipient_input_user_name	= $("input[name='recipient_input_user_name']").val();

			$(".delivery_often").show();
			$(".default_table_style.recipient").show();
			$(".delivery_info").show();
			$(".goods_delivery_info").show();

			$("input[name='recipient_user_name']").val(recipient_input_user_name);
			$(".recipient_user_name").html(recipient_input_user_name);

			if(add_nation == 'KOREA'){

				var address_type	= $("input[name='recipient_input_address_type']").val();
				var zipcode			= $("input[name='recipient_input_new_zipcode']").val();
				var address_street	= $("input[name='recipient_input_address_street']").val();
				var address			= $("input[name='recipient_input_address']").val();
				var address_detail	= $("input[name='recipient_input_address_detail']").val();
				var phone_obj		= $("input[name='recipient_input_phone[]']");
				var cellphone_obj	= $("input[name='recipient_input_cellphone[]']");

				cellphone_obj.each(function(idx){
					$("input[name='recipient_cellphone[]']").eq(idx).val(cellphone_obj.eq(idx).val() );
					cellphone[idx] = cellphone_obj.eq(idx).val();
				});
				phone_obj.each(function(idx){
					$("input[name='recipient_phone[]']").eq(idx).val(phone_obj.eq(idx).val() );
					phone[idx] = phone_obj.eq(idx).val();
				});
				$(".cellphone").html(cellphone.join("-"));
				$(".phone").html(phone.join("-"));

				if(type != 'ticket'){
					$("input[name='recipient_address_type']").val(address_type);
					$("input[name='recipient_new_zipcode']").val(zipcode);
					$("input[name='recipient_address_street']").val(address_street);
					$("input[name='recipient_address']").val(address);
					$("input[name='recipient_address_detail']").val(address_detail);
					// view 적용
					$(".recipient_zipcode").html($("input[name='recipient_input_new_zipcode']").val());
					if(address_type == 'street'){
						$(".recipient_address").html($("input[name='recipient_input_address_street']").val());
					}else{
						$(".recipient_address").html($("input[name='recipient_input_address']").val());
					}
					$(".recipient_address_detail").html(address_detail);
				}
			}else{
				var address		= $("input[name='international_address_input']").val();
				var town_city	= $("input[name='international_town_city_input']").val();
				var county		= $("input[name='international_county_input']").val();
				var postcode	= $("input[name='international_postcode_input']").val();
				var country		= $("input[name='international_country_input']").val();

				if(type != 'ticket'){
					$("input[name='international_address']").val(address);
					$("input[name='international_town_city']").val(town_city);
					$("input[name='international_county']").val(county);
					$("input[name='international_postcode']").val(postcode);
					$("input[name='international_country']").val(country);
					// view 적용
					$(".recipient_zipcode").html($("input[name='international_postcode_input']").val());
					$(".recipient_address").html(address + ', ' + town_city + ', ' + county + ', ' + country);
				}
				$("input[name='recipient_input_phone[]']").each(function(idx){
					$("input[name='international_recipient_phone[]']").eq(idx).val( $("input[name='recipient_input_phone[]']").eq(idx).val() );
					phone[idx] = $("input[name='recipient_input_phone[]']").eq(idx).val();
				});
				$("input[name='recipient_input_cellphone[]']").each(function(idx){
					$("input[name='international_recipient_cellphone[]']").eq(idx).val( $("input[name='recipient_input_cellphone[]']").eq(idx).val() );
					cellphone[idx] = $("input[name='recipient_input_cellphone[]']").eq(idx).val();
				});

				$(".cellphone").html(cellphone.join("-"));
				$(".phone").html(phone.join("-"));
			}
			if(is_members == true) $("input[name='recipient_email']").val( $("input[name='order_email']").val() );

			// 배송 주소록 저장여부 판단

			// 금액 재계산
			order_price_calculate();

			// 적용 후 닫기
			address_close('delivery');
		}else{ // 주문자 수동적용
			$(".order_user_name").html($("input[name='order_user_name']").val());
			$("input[name='order_cellphone[]']").each(function(idx){
				cellphone[idx] = $("input[name='order_cellphone[]']").eq(idx).val();
			});
			$("input[name='order_phone[]']").each(function(idx){
				phone[idx] = $("input[name='order_phone[]']").eq(idx).val();
			});
			$(".order_phone").html(cellphone.join("-") + ' / ' + phone.join("-"));
			$(".order_email").html($("input[name='order_email']").val());

			$(".order_input").hide();
			$(".order_member").show();
		}

		return true;
	}

	// 최종배송지 -> input으로 복사 :: 2017-06-01 lwh
	function delivery_apply_input(){

		if(skin_order_settle_ver < 1)	return false; // 이전버전에는 할 필요 없음.

		var add_nation					= $("#address_nation").val();
		var recipient_user_name			= $("input[name='recipient_user_name']").val();
		var recipient_email				= $("input[name='recipient_email']").val();
		$("input[name='recipient_input_user_name']").val(recipient_user_name);
		$("input[name='recipient_input_email']").val(recipient_email);

		if(add_nation == 'KOREA'){
			var recipient_new_zipcode		= $("input[name='recipient_new_zipcode']").val();
			var recipient_address_type		= $("input[name='recipient_address_type']").val();
			var recipient_address_street	= $("input[name='recipient_address_street']").val();
			var recipient_address			= $("input[name='recipient_address']").val();
			var recipient_address_detail	= $("input[name='recipient_address_detail']").val();
			var phone_obj					= $("input[name='recipient_phone[]']");
			var cellphone_obj				= $("input[name='recipient_cellphone[]']");

			$("input[name='recipient_input_new_zipcode']").val(recipient_new_zipcode);
			$("input[name='recipient_input_address_type']").val(recipient_address_type);
			$("input[name='recipient_input_address_street']").val(recipient_address_street);
			$("input[name='recipient_input_address']").val(recipient_address);
			$("input[name='recipient_input_address_detail']").val(recipient_address_detail);

			cellphone_obj.each(function(idx){
				$("input[name='recipient_input_cellphone[]']").eq(idx).val(cellphone_obj.eq(idx).val());
			});
			phone_obj.each(function(idx){
				$("input[name='recipient_input_phone[]']").eq(idx).val(phone_obj.eq(idx).val());
			});
		}else{
			var gl_address					= $("input[name='international_address']").val();
			var gl_town_city				= $("input[name='international_town_city']").val();
			var gl_county					= $("input[name='international_county']").val();
			var gl_postcode					= $("input[name='international_postcode']").val();
			var gl_country					= $("input[name='international_country']").val();
			var phone_obj					= $("input[name='international_recipient_phone[]']");
			var cellphone_obj				= $("input[name='international_recipient_cellphone[]']");

			$("input[name='international_address_input']").val(gl_address);
			$("input[name='international_town_city_input']").val(gl_town_city);
			$("input[name='international_county_input']").val(gl_county);
			$("input[name='international_postcode_input']").val(gl_postcode);
			$("input[name='international_country_input']").val(gl_country);

			cellphone_obj.each(function(idx){
				$("input[name='recipient_input_cellphone[]']").eq(idx).val(cellphone_obj.eq(idx).val());
				$("input[name='international_recipient_cellphone_input[]']").eq(idx).val(cellphone_obj.eq(idx).val());
			});
			phone_obj.each(function(idx){
				$("input[name='recipient_input_phone[]']").eq(idx).val(phone_obj.eq(idx).val());
				$("input[name='international_recipient_phone_input[]']").eq(idx).val(phone_obj.eq(idx).val());
			});
		}

		// 금액 재계산
		order_price_calculate();
	}

	// 신버전 국내/해외 구분값 :: 2016-08-16 lwh
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
			$("input[name='international_country_input']").val(nation);
			$("input[name='international_country']").hide();
			$("input[name='international_country_input']").hide();
			$(".international_nation").html(nation);
			$(".international").show();
			$(".domestic").hide();
		}

		order_price_calculate();
	}

	// 전체 재 계산 :: 2016-08-16 lwh
	function order_price_calculate(){
		var f = $("form#orderFrm");
		var action = "/order/calculate?mode="+gl_mode;

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
		if(typeof gl_mobile_mode!="undefined" && gl_mobile_mode){
			var	btn_order_pay1 = '결제하기';
			var	btn_order_pay2 = '주문하기';
		}else{
			var btn_order_pay1 = "<img src='/data/skin/"+gl_skin+"/images/design/btn_order_pay.gif' />";
			var btn_order_pay2 = "<img src='/data/skin/"+gl_skin+"/images/design/btn_order.gif' />";
		}
		$.ajax({
			'url' : '../order/settle_order_images',
			'dataType': 'json',
			'success': function(data) {
				if(typeof gl_mobile_mode=="undefined" || !gl_mobile_mode){
					if(data.btn_order_pay1) btn_order_pay1 = "<img src='/data/skin/"+data.btn_order_pay1+"/images/design/btn_order_pay.gif' />";
					if(data.btn_order_pay2) btn_order_pay2 = "<img src='/data/skin/"+data.btn_order_pay2+"/images/design/btn_order_pay.gif' />";
				}

				$("#pay").html(btn_order_pay1);
				$("input[name='payment']:checked").each(function(){
					if( $(this).val() == "bank" ){
						$("#pay").html(btn_order_pay2);
					}else{
						//쿠폰의 무통장쿠폰인 경우 점검
						if( eval('$("#coupon_sale_payment_b")') ){
							var coupon_sale_payment_b = $("#coupon_sale_payment_b").val();
							if(coupon_sale_payment_b>0){
								//현재 무통장 전용 쿠폰을 사용하셨습니다.<br />결제수단을 무통장으로 변경해 주세요!
								openDialogAlert(getAlert('os159'),400,150);
								return false;
							}
						}
					}
					/*0원일 경우 증빙x*/
					gl_chk_tot_price = $(".total").find(".settle_price").html();
					if(gl_chk_tot_price == '0') {
						$("#typereceiptcardlay").hide();
						$("#typereceipttablelay").show();
					} else {
						typereceipt_layout_toggle($(this));
					}
				});
			}
		});
	}

	function reverse_pay_button(){
		$("div.pay_layer").eq(0).show();
		$("div.pay_layer").eq(1).hide();
	}

	function reverse_pay_layer(){

		$('#wrap').show();
		$('#layer_pay').hide();
		reverse_pay_button();
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
		$('#creceipt_number').removeClass('required').val('');
	}

	//쿠폰적용하시기 단독쿠폰체크
	function sametime_coupon_dialog(){
		if(typeof gl_mobile_mode!="undefined" && gl_mobile_mode){
			getCouponAjaxList();
			apply_division('coupon');
		}else{
			getCouponAjaxList();
			//openDialog("쿠폰 적용하기", "coupon_apply_dialog", {"width":800,"height":600});
		}
	}

	// 슬라이드 사용 및 닫기 :: 2016-08-16 lwh
	function apply_division(type){
		if(typeof gl_mobile_mode!="undefined" && gl_mobile_mode){
			if($("#"+type+"_apply_division").is(":visible")){
				$("#"+type+"_apply_division").children().slideUp(function(){
					$("#"+type+"_apply_division").hide();
				});
				$("."+type+"_btn_dn").show();
				$("."+type+"_btn_up").hide();
			}else{
				$("#"+type+"_apply_division").show();
				$("#"+type+"_apply_division").children().hide().slideDown();
				$("."+type+"_btn_dn").hide();
				$("."+type+"_btn_up").show();
			}
		}
	}


	function getPromotionckloding(cartpromotioncode) {
		if( cartpromotioncode ) {
			$.ajax({
				'url' : '../promotion/getPromotionJson?mode='+gl_mode,
				'data' : {'cartpromotioncode':cartpromotioncode},
				'type' : 'post',
				'dataType': 'json',
				'success': function(data) {
					order_price_calculate();
				}
			});
		}
	}
	function getPromotionck() {
		var cartpromotioncode = $("#cartpromotioncode").val();
		if(!cartpromotioncode){
			//할인코드를 정확히 입력해 주세요.
			openDialogAlert(getAlert('os026'),'400','140');
			return false;
		}

		$.ajax({
			'url' : '../promotion/getPromotionJson?mode='+gl_mode,
			'data'		: {'cartpromotioncode':cartpromotioncode},
			'type'		: 'post',
			'dataType'	: 'json',
			'cache'		: false,
			'success'	: function(data) {

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
				if(data.sale_type == 'shipping_free'){//기본배송비 무료
					//기본배송비 무료
					promotionDetailhelphtml +=  "<li>- <b>"+getAlert('os190')+"</b></li>";// (최대 " + comma(data.max_percent_shipping_sale) + "원)
					//원 이상 구매 시
					promotionDetailhelphtml +=  "<li>- "+ comma(data.limit_goods_price) +getAlert('os191')+"</li>";
				}else if(data.sale_type == 'shipping_won'){//**원배송비 할인
					var realprice = comma(data.won_shipping_sale);
					//기본배송비 "+ realprice +"원 할인
					promotionDetailhelphtml +=  "<li>- <b>"+getAlert('os192',realprice)+"</b></li>";
					promotionDetailhelphtml +=  "<li>- "+ get_currency_price(data.limit_goods_price,2) +getAlert('os191')+"</li>";
				}else if(data.sale_type == 'won'){//**원 주문상품할인
					var realprice = get_currency_price(data.won_goods_sale);

					//원 할인
					promotionDetailhelphtml +=  "<li>- <b>"+ realprice +getAlert('os193')+" </b></li>";
					//원 이상 구매 시
					promotionDetailhelphtml +=  "<li>- "+ get_currency_price(data.limit_goods_price) +getAlert('os191')+"</li>";
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
					promotionDetailhelphtml +=  "<li>- <b>" + realpercent + "% "+getAlert('os201')+"</b></li>";// (최대 " + comma(data.max_percent_goods_sale) + "원)
					//원 이상 구매 시
					promotionDetailhelphtml +=  "<li>- "+ comma(data.limit_goods_price) +getAlert('os191')+"</li>";
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

	/* 할인코드 초기화하기 */
	function getPromotionCartDel(){
		$.ajax({
			'url' : '/promotion/getPromotionCartDel',
			'success' : function(){
				$(".cartPromotionTh").hide();
				$(".cartPromotionTd").hide();
				$("#pricePromotionTd").hide();
				$(".cartpromotioncodedellay").hide();
				$("#promotion_shipping_salse").empty();
				$(".cartpromotioncodeinputlay").show();
				order_price_calculate();
			}
		});
	}

	// facebook 라이크 할인 적용 및 오픈그라피
	function getfblikeopengraph(){
		$.get('../order/fblike_opengraph', function(data) {
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

	// 쿠폰 사용
	function useCouponSelect(obj){

		var objSel	= obj.find("option:selected");
		var tmpObj	= '';

		// 상품쿠폰
		$(".coupon_select").each(function(){
			tmpObj	= $("select#"+$(this).attr("id")+" option[value='"+obj.find("option:selected").val()+"']");
			if( $(this).find("option:selected").attr('couponsametime') == 'N' || obj.find("option:selected").attr('couponsametime') == 'N'){
				if	(objSel.attr('duplication') == 1 && tmpObj.val()){
					tmpObj.attr("selected",true);
					$(this).parents('tr').find("span.sale").html( comma( $(this).find("option:selected").attr("sale") ) );
				}else{
					cancelCouponSelect($(this));
				}
			}else{
				$(this).parents('tr').find("span.sale").html( comma( $(this).find("option:selected").attr("sale") ) );
			}
		});

		// 배송비쿠폰
		$(".shipping_coupon_select").each(function(){
			tmpObj	= $("select#"+$(this).attr("id")+" option[value='"+obj.find("option:selected").val()+"']");
			if( $(this).find("option:selected").attr('couponsametime') == 'N' || obj.find("option:selected").attr('couponsametime') == 'N'){
				if	(objSel.attr('duplication') == 1 && tmpObj.val()){
					tmpObj.attr("selected",true);
					$(this).parent().next().find("span.shipping_sale").html( comma( $(this).find("option:selected").attr("sale") ) );
				}else{
					cancelShippingCouponSelect($(this));
				}
			}else{
				$(this).parent().next().find("span.shipping_sale").html( comma( $(this).find("option:selected").attr("sale") ) );
			}
		});

		// 선택한 쿠폰 적용
		objSel.attr("selected",true);
		if	(obj.hasClass('shipping_coupon_select')){
			objSel.parent().next().find("span.shipping_sale").html( comma( objSel.find("option:selected").attr("sale") ) );
		}else{
			objSel.parents('tr').find("span.sale").html( comma( objSel.find("option:selected").attr("sale") ) );
		}
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
					//단독으로만 적용할 수 있는 쿠폰을 선택하셨습니다.<br/>기존에 적용된 쿠폰은 모두 해제됩니다. 적용하시겠습니까?
					var msg = getAlert('os205');
				}
				openDialogConfirm(msg,400,150,function(){
					getCouponsametimeselect(obj,'goods');//선택해제
					getCouponselectreal(obj);//단독쿠폰 중복쿠폰여부
					$.cookie( "couponsametimeuse", true );
				},function(){
					if(oldidx){
						obj.find("option").eq(oldidx).attr("selected",true);
						obj.parents('tr').find("span.sale").html( comma( oldsale ) );
					}else{
						obj.val("").prop("selected", true); //IE7
						obj.find("option:selected").attr("selected",false);
						obj.parents('tr').find("span.sale").html( comma( 0 ) );
					}
					return false;});
			}else{//단독쿠폰아닌경우
				if( $.cookie( "couponsametimeuse") ) {//이전에 단독쿠폰 선택된 경우
					//이전에 적용한 쿠폰은 단독으로만 사용가능한 쿠폰입니다.<br/>본 쿠폰을 사용하시면 이전에 적용된 쿠폰은 모두 해제 됩니다. 적용하시겠습니까?
					var msg = getAlert('os204');
					openDialogConfirm(msg,400,150,function() {
						getCouponsametimeselect(obj,'goods');//선택해제
						getCouponselectreal(obj);
						$.cookie( "couponsametimeuse", null );
					},function(){
					if(oldidx){
						obj.find("option").eq(oldidx).attr("selected",true);
						obj.parents('tr').find("span.sale").html( comma( oldsale ) );
					}else{
						obj.val("").prop("selected", true); //IE7
						obj.find("option:selected").attr("selected",false);
						obj.parents('tr').find("span.sale").html( comma( 0 ) );
					}
						return false;});
				}else{
					getCouponselectreal(obj);
				}
			}
		}else{
			obj.val("").prop("selected", true); //IE7
			obj.find("option").attr("selected",false); //선택제외
			obj.parents('tr').find("span.sale").html( comma( 0 ) );
			obj.parents('tr').find("button.ordercoupongoodsreviewbtn").attr("download_seq",'');
			if( $.cookie( "couponsametimeuse") ) {
				$.cookie( "couponsametimeuse", null );
			}
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
					//단독으로만 적용할 수 있는 쿠폰을 선택하셨습니다.<br/>기존에 적용된 쿠폰은 모두 해제됩니다. 적용하시겠습니까?
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
						obj.parents('tr').find("span.shipping_sale").html( comma( oldsale ) );
					}else{
						obj.val("").prop("selected", true); //IE7
						obj.find("option:selected").attr("selected",false);
						obj.parents('tr').find("span.shipping_sale").html( comma( 0 ) );
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
							obj.parents('tr').find("span.shipping_sale").html( comma( oldsale ) );
						}else{
							obj.val("").prop("selected", true); //IE7
							obj.find("option:selected").attr("selected",false);
							obj.parents('tr').find("span.shipping_sale").html( comma( 0 ) );
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
			obj.parents('tr').find("span.shipping_sale").html( comma( 0 ) );
			if( $.cookie( "couponsametimeuse") ) {
				$.cookie( "couponsametimeuse", null );
			}
		}
		//쿠폰정보정의
		var download_seq = obj.parents('tr').find("select.shipping_coupon_select option:selected").val();
		if(download_seq) {
			var shipping_sale = obj.parents('tr').find("select.shipping_coupon_select option:selected").attr("sale");
			obj.parents('tr').find(".shippingcoupongoodsreviewbtn").attr("download_seq",download_seq);
			obj.parents('tr').find(".shipping_sale").html( comma( shipping_sale ) );
		}else{
			obj.parents('tr').find(".shippingcoupongoodsreviewbtn").attr("download_seq",'');
			obj.parents('tr').find("span.shipping_sale").html( comma( 0 ) );
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
				$(".shipping_coupon_sale").html( comma( 0 ) );
			});
		}
		$("select.coupon_select").each(function(){
			if( !$(this).find("option:selected").val() ) return true; //continue;
			if( obj.attr('id') != $(this).attr("id") ) {
				$(this).val("").prop("selected", true); //IE7
				$(this).find("option").attr("selected",false); //선택제외
				$(this).parents('tr').find("span.sale").html( comma( 0 ) );
				$(this).parents('tr').find("button.ordercoupongoodsreviewbtn").attr("download_seq",'');
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
			//if( !$(this).find("option:selected").val() ) return true; //continue;
			//if( obj.attr('id') != $(this).attr("id") ) {
				$(this).val("").prop("selected", true); //IE7
				$(this).find("option").attr("selected",false); //선택제외
				$(this).parents('tr').find("span.sale").html( comma( 0 ) );
			//}
		});

		$("select.ordersheet_coupon_select").each(function(){
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
					$(this).parents('tr').find("span.sale").attr("oldidx",obj.find("option:selected").index());
					$(this).parents('tr').find("span.sale").attr("oldsale", $(this).find("option:selected").attr("sale"));
				}
					$(this).parents('tr').find("span.sale").html( comma( $(this).find("option:selected").attr("sale") ) );
					$(this).parents('tr').find("button.ordercoupongoodsreviewbtn").attr("download_seq",$(this).find("option:selected").val());
			}else{

				if( obj.attr('id') != $(this).attr("id") && obj.find("option:selected").attr("value") ){
					if(obj.find("option:selected").val() == $(this).find("option:selected").val()){
						$(this).find("option").eq(0).attr("selected",true);
					}
				}

				if( obj.attr('id') == $(this).attr("id") ) {
					$(this).parents('tr').find("span.sale").attr("oldidx",$(this).find("option:selected").index());
					$(this).parents('tr').find("span.sale").attr("oldsale",$(this).find("option:selected").attr("sale"));
				}
				$(this).parents('tr').find("span.sale").html( comma( $(this).find("option:selected").attr("sale") ) );
				$(this).parents('tr').find("button.ordercoupongoodsreviewbtn").attr("download_seq",$(this).find("option:selected").val());

			}
		});
	}

	//배송비쿠폰선택
	function getCouponshselectreal(obj) {
		$("select.shipping_coupon_select").each(function(idx){//
			if( obj.find("option:selected").attr('duplication') == 1 ) {//중복쿠폰
				if( obj.attr('id') != $(this).attr("id") && !$(this).find("option:selected").val() ) {//선택하지 않는 상품인경우
					$("select#"+$(this).attr("id")+" option[value='"+obj.find("option:selected").val()+"']").attr("selected",true);
				}

				if( obj.attr('id') == $(this).attr("id") )  {
					$(this).parents('tr').find("span.shipping_sale").attr("oldidx",obj.find("option:selected").index());
					$(this).parents('tr').find("span.shipping_sale").attr("oldsale", $(this).find("option:selected").attr("sale"));
				}
				$(this).parents('tr').find("span.shipping_sale").html( comma( $(this).find("option:selected").attr("sale") ) );
				$(this).parents('tr').find("button.ordercoupongoodsreviewbtn").attr("download_seq",$(this).find("option:selected").val());
			}else{

				if( obj.attr('id') != $(this).attr("id") && obj.find("option:selected").attr("value") ){
					if(obj.find("option:selected").val() == $(this).find("option:selected").val()){
						$(this).find("option").eq(0).attr("selected",true);
					}
				}

				if( obj.attr('id') == $(this).attr("id") ) {
					$(this).parents('tr').find("span.shipping_sale").attr("oldidx",$(this).find("option:selected").index());
					$(this).parents('tr').find("span.shipping_sale").attr("oldsale",$(this).find("option:selected").attr("sale"));
				}
				$(this).parents('tr').find("span.shipping_sale").html( comma( $(this).find("option:selected").attr("sale") ) );
				$(this).parents('tr').find("button.ordercoupongoodsreviewbtn").attr("download_seq",$(this).find("option:selected").val());

			}
		});
	}

	// 쿠폰 사용 취소
	function cancelCouponSelect(obj){
		obj.val("").prop("selected", true); //IE7
		obj.find("option:selected").attr("selected",false);
		obj.parents('tr').find("span.sale").html( comma( 0 ) );
		obj.parents('tr').find("button.ordercoupongoodsreviewbtn").attr("download_seq",'');
	}

	// 배송 쿠폰 사용 취소
	function cancelShippingCouponSelect(obj){
		obj.find("option").eq(0).attr("selected",true);
		obj.parents('tr').find("span.shipping_sale").html( comma( 0 ) );
	}

	// 쿠폰선택
	function getCouponselect(e){
		var obj						= $(e);
		// 쿠폰 선택 시
		if( obj.find("option:selected").val() ) {
			// 단독 쿠폰의 경우
			if( obj.find("option:selected").attr('couponsametime') == 'N' ) {
				//단독으로만 적용할 수 있는 쿠폰을 선택하셨습니다.<br/>기존에 적용된 쿠폰은 모두 해제됩니다. 적용하시겠습니까?
				var msg = getAlert('os205');
			}else if( chkCouponSameTimeUse() ){
				//이전에 적용한 쿠폰은 단독으로만 사용가능한 쿠폰입니다.<br/>본 쿠폰을 사용하시면 이전에 적용된 쿠폰은 모두 해제 됩니다. 적용하시겠습니까?
				var msg = getAlert('os204');
			}

			if	(msg)	openDialogConfirm(msg,400,150,function(){useCouponSelect(obj)},function(){cancelCouponSelect(obj)});
			else		useCouponSelect(obj);
		}else{
			obj.parents('tr').find("span.sale").html( comma( 0 ) );
		}
	}

	/**
	 * 쿠폰을 ajax로 검색한다.
	 */
	function getCouponAjaxList() {
		var f = $("form#orderFrm");
		var queryString = f.formSerialize();
		$.ajax({
			type: 'post',
			url: './settle_coupon?mode={mode}',
			data: queryString,
			dataType: 'json',
			success: function(data) {
				if( data ){
					if( data.coupon_error ){
						$('#coupon_goods_lay').html('');
						closeDialog("coupon_apply_dialog");
						//적용 가능한 쿠폰이 없습니다.
						openDialogAlert(getAlert('os004'),'400','140');
					}else{
						//쿠폰 적용 하기
						openDialog(getAlert('os005'), "coupon_apply_dialog", {"width":900,"height":600});
				if(data.coupongoods){
					$('#coupon_goods_lay').html(data.coupongoods);
				}
				if(data.checkshippingcoupons>0){
					$('#coupon_shipping_lay').show();
					$('#coupon_shipping_select').html(data.couponshipping);
				}else{
					$('#coupon_shipping_lay').hide();
				}
				if(data.checkordersheetcoupons>0){
					$('#coupon_ordersheet_lay').show();
					$('#coupon_ordersheet_select').html(data.couponordersheet);
				}else{
					$('#coupon_ordersheet_lay').hide();
				}
			}
				}
			}
		});
	}

	// 쿠폰사용
	function getCouponuse(seqno){
		$(".couponlay_"+seqno).hide();
		$.getJSON('../coupon/goods_coupon_max?no='+seqno, function(data) {
			if(data){
				$(".couponlay_"+seqno).show();
				if(data.sale_type == 'won'){
					//원<br/>쿠폰받기
					$(".couponlay_"+seqno+" .cb_percent").html('▶'+comma(data.won_goods_sale) + getAlert('os207'));
				}else{
					//%<br/>쿠폰받기
					$(".couponlay_"+seqno+" .cb_percent").html('▶'+comma(data.percent_goods_sale) + getAlert('os208'));
				}
			}
		});
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

	function mobile_pay_layer(){
		$("#layer_pay").html('');
		var divLayer = $("#payprocessing").clone().wrapAll("<div/>").parent().html();
		divLayer = divLayer + '<iframe name="tar_opener" frameborder="0" border="0" width="350" height="100%" scrolling="auto" style="margin:0px auto;"></iframe>';
		$("#layer_pay").html(divLayer);
		$("#layer_pay").css("height","100%");
		$("#layer_pay").css("display","block");
		$('html,body').animate({scrollTop:0},'fast');
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
		if($("input[name='cash_view']").val() < 1){
			//예치금를 정확히 입력해 주세요.
			openDialogAlert(getAlert('os209'),'400','140');
			return false;
		}

		if($("input[name='cash_view']").val() > 0){
			$("input[name='cash']").val( $("input[name='cash_view']").val() );
			$(".cash_input_button").addClass("hide");
			$(".cash_cancel_button").removeClass("hide");
		}
		order_price_calculate();
	}

	function cancel_cash(){
		$("input[name='cash']").val(0);
		$("input[name='cash_view']").val(0);
		$(".cash_cancel_button").hide();
		$(".cash_input_button").show();
		$("#priceCashTd").addClass("hide");
		order_price_calculate();
	}

	function use_all_cash(){
		if($("input[name='cash_all']").is(':checked')){
			$("input[name='cash_all']").val('y');
			$("input[name='cash']").val(0);
			$(".cash_input_button").hide();
			$(".cash_all_input_button").hide();
			$(".cash_cancel_button").show();
		}else{
			$(".cash_input_button").show();
			$(".cash_cancel_button").hide();
			$("input[name='cash']").val(0);
			$("input[name='cash_view']").val(0);
		}
		order_price_calculate();
	}

	function use_emoney(){
		if($("input[name='emoney_view']").val() < 1 ){
			//마일리지을 정확히 입력해 주세요.
			openDialogAlert(getAlert('os040'),'400','140');
			return false;
		}
		if($("input[name='emoney_view']").val() > 0){
			$("input[name='emoney']").val( $("input[name='emoney_view']").val() );
			$(".emoney_input_button").addClass("hide");
			$(".emoney_cancel_button").removeClass("hide");
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

	function cancel_emoney(){
		$("input[name='emoney']").val(0);
		$("input[name='emoney_view']").val(0);
		$(".emoney_cancel_button").hide();
		$(".emoney_input_button").show();
		$("#priceEmoneyTd").addClass("hide");
		order_price_calculate();
	}

	function use_all_emoney(){
		if($("input[name='emoney_all']").is(':checked')){
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
		}else{
			$(".emoney_input_button").show();
			$(".emoney_cancel_button").hide();
			$("input[name='emoney']").val(0);
			$("input[name='emoney_view']").val(0);
		}
		order_price_calculate();
	}

	function toggleCartSaleDetailView(obj,key){
		if($(key).is(":hidden")){
			$(key).stop(true,true).show();
			$(obj).removeClass('btn_arw_dn_gray').addClass('btn_arw_up_gray');
			$('span',obj).removeClass('btn_arw_dn_icon').addClass('btn_arw_up_icon');
		}else{
			$(key).stop(true,true).hide();
			$(obj).removeClass('btn_arw_up_gray').addClass('btn_arw_dn_gray');
			$('span',obj).removeClass('btn_arw_up_icon').addClass('btn_arw_dn_icon');
		}
	}
	// 카카오 페이 클릭시 동작 :: 2015-02-26 lwh
	function click_Kakaopay(obj){
		$("input[name='payment']:radio[value='kakaopay']").attr('checked',true).trigger('change');
	}

	// 배송 주소록 로드 :: 2016-08-12 lwh
	function delivery_address_ajax(nowpage){
		$.ajax({
		'url' : '../order/pop_delivery_address',
		'type'	: 'get',
		'dataType': 'html',
		'data' : '?page='+nowpage,
		'success': function(res) {
				if(res){
					$(".delivery_often").html(res);
				}else{
					//주소록을 로드하지 못했습니다.
					alert(getAlert('os152'));
					document.location.reload();
				}
			}
		});
	}

	// 배송지/주문자 수정 분기처리 :: 2017-05-30 lwh
	function address_modify(type){
		if(skin_order_settle_ver >= 1){ // 신규구조
			address_modify_new(type);
		}else{ // 기존 구조
			address_modify_old(type);
		}
	}

	// 배송지/주문자 수정 예전꺼.. :: 2016-08-12 lwh
	function address_modify_old(type){

		var international = $("#address_nation").val();
		if(!type) type = 'delivery';
		if(type == 'delivery' || type == 'delivery_input'){
			$(".delivery_selecter").show();
			if(is_members == true){
				delivery_address_ajax(1);
			}else{
				$(".delivery_often").hide();
				$(".default_table_style.recipient").hide();
			}
			if(international == 'KOREA'){
				$(".domestic").show();
				$(".international").hide();
			}else{
				$(".domestic").hide();
				$(".international").show();
			}
			$("#chkQuickAddress_new").attr("checked",true);

			if(type == 'delivery_input'){
				$(".settle_tab li").eq(1).trigger('click');
				type = 'delivery';
			}

			//닫기
			$(".address_chg_"+type).html(getAlert('os212'));
			$(".address_chg_"+type).attr("onclick","address_close('"+type+"');");
		}else{
			$("." + type + "_member").hide();
			$("." + type + "_input").show();
		}

		if(is_goods != true){ $(".goods_delivery_info").hide();}
	}

	// 배송지/주문자 수정 신규 :: 2017-05-30 lwh
	function address_modify_new(type){
		var international = $("#address_nation").val();
		if(!type) type = 'delivery';

		if(type == 'delivery'){ // 배송지
			$(".settle_tab li").eq(1).trigger('click');
			if(is_members == true){
				delivery_address_ajax(1);
			}
			if(international == 'KOREA'){
				$(".domestic").show();
				$(".international").hide();
				$("input[name='recipient_input_address']").attr('readonly','readonly');
			}else{
				$(".domestic").hide();
				$(".international").show();
			}
		}else{ // 주문자
		}

		$("." + type + "_info_member").hide();
		$("." + type + "_info_input").show();

		set_shipping(type);

		/* 주소지가 도로명 기준일때 도로명 주소지 노출 */
		if($("input[name='recipient_input_address_type']").val() == "street"){
			$("input[name='recipient_input_address_street']").show();
			$("input[name='recipient_input_address']").hide();
		}

	}

	// 배송지/주문자 수정 닫기 :: 2017-05-31 lwh
	function address_close(type){
		if(skin_order_settle_ver >= 1){ // 신규구조
			address_close_new(type);
		}else{ // 기존 구조
			address_close_old(type);
		}
	}

	// 배송지 수정 닫기 예전꺼.. :: 2016-08-12 lwh
	function address_close_old(type){
		var msg = '';
		if(type == 'delivery'){
			//변경
			msg = getAlert('os211');
			$(".delivery_selecter").hide();
		}else{
			msg = '수정';
		}

		$(".address_chg_"+type).html(msg);
		$(".address_chg_"+type).attr("onclick","address_modify('"+type+"');");

		if(is_goods != true){ $(".goods_delivery_info").hide();}
	}

	// 배송지 수정 닫기 신규 :: 2017-05-31 lwh
	function address_close_new(type){
		if(!type) type = 'delivery';

		if(type == 'delivery'){ // 배송지
			$("." + type + "_info_member").show();
			$("." + type + "_info_input").hide();
		}else{ // 주문자
		}
	}

	// 모바일용 주문상품갯수 표기 :: 2017-05-29 lwh
	function area_close_chk(){

		// 주문상품 갯수 표기
		if($(".order_goods_detail").css('display') == 'block')
				$("#total_ship_ea").hide();
		else	$("#total_ship_ea").show();

		// 사은품 갯수표기
		if($(".gift_detail").css('display') == 'block')
				$("#total_gift_ea").hide();
		else	$("#total_gift_ea").show();

		// 주문자 표기
		if($(".order_info_detail").css('display') == 'block'){
			$("#order_info").hide();
		}else{
			var cellphone		= new Array();
			var order_user_name = $("input[name='order_user_name']").val();
			$("input[name='order_cellphone[]']").each(function(idx){
				cellphone[idx]	= $("input[name='order_cellphone[]']").eq(idx).val();
			});
			$("#order_info").html("("+order_user_name+" "+cellphone.join("-")+")");
			$("#order_info").show();
		}
	}

	// 배송지 정보 채우기 :: 2017-05-30 lwh
	function copy_order_info(){
		$("input[name='recipient_input_user_name']").val( $("input[name='order_user_name']").val() );

		$("input[name='order_phone[]']").each(function(idx){
			$("input[name='recipient_input_phone[]']").eq(idx).val( $("input[name='order_phone[]']").eq(idx).val() );
		});

		$("input[name='order_cellphone[]']").each(function(idx){
			$("input[name='recipient_input_cellphone[]']").eq(idx).val( $("input[name='order_cellphone[]']").eq(idx).val() );
		});

		$("input[name='recipient_email']").val( $("input[name='order_email']").val() );
		order_price_calculate();
	}

	// 주문자 정보 채우기 :: 2017-05-30 lwh
	function copy_delivery_info(){
		if($("input[name='order_user_name']").attr('readonly') != 'readonly'){
			$("input[name='order_user_name']").val( $("input[name='recipient_input_user_name']").val() );
		}

		$("input[name='recipient_input_phone[]']").each(function(idx){
			$("input[name='order_phone[]']").eq(idx).val( $("input[name='recipient_input_phone[]']").eq(idx).val() );
		});

		$("input[name='recipient_input_cellphone[]']").each(function(idx){
			$("input[name='order_cellphone[]']").eq(idx).val( $("input[name='recipient_input_cellphone[]']").eq(idx).val() );
		});

		$("input[name='order_email']").val( $("input[name='recipient_email']").val() );

		order_price_calculate();
	}

	// 모바일 전용 사은품 받기 체크
	function gift_chk(){
		$(".gift_txt").hide();
		if($("input[name='gift_use']").is(':checked')){
			$(".gift_y").show();
		}else{
			$(".gift_n").show();
		}
	}

	// bind START ---------------------------------------------------------
	$(window).load(function(){
		// 배송지 정보 채우기 :: 구버전용
		$("input#copy_order_info").bind("click",function(){
			if( $(this).attr("checked") ){

				$("input[name='recipient_input_user_name']").val( $("input[name='order_user_name']").val() );

				$("input[name='order_phone[]']").each(function(idx){
					$("input[name='recipient_input_phone[]']").eq(idx).val( $("input[name='order_phone[]']").eq(idx).val() );
				});

				$("input[name='order_cellphone[]']").each(function(idx){
					$("input[name='recipient_input_cellphone[]']").eq(idx).val( $("input[name='order_cellphone[]']").eq(idx).val() );
				});

				if(is_members == true) $("input[name='recipient_input_email']").val( $("input[name='order_email']").val() );

				$('.phone_num2').each(function(){
					if	(check_input_value($(this)))
						$(this).show().parent().find('.phone_num1').hide();
				});
			}else{

				$("input[name='recipient_input_user_name']").val("");

				$("input[name='order_phone[]']").each(function(idx){
					$("input[name='recipient_input_phone[]']").eq(idx).val("");
				});

				$("input[name='order_cellphone[]']").each(function(idx){
					$("input[name='recipient_input_cellphone[]']").eq(idx).val("");
				});

				$("input[name='recipient_input_email']").val("");

			}

			order_price_calculate();
		});

		// 해외/국내 배송 선택
		$("select[name='international']").bind("change",function(){
			check_shipping_method();
		});

		// 해외배송 방법 선택 시
		$("input[name='shipping_method_international']").bind("click",function(){
			$("select[name='region'] option").remove();
			var idx = $(this).val();
			for(var i=0;i<region[idx].length;i++){
				$("select[name='region']").append("<option value='"+i+"'>"+region[idx][i]+"</option>");
			}
		});

		// 선택된 배송 국가지정
		if ($("#address_nation").val()) {
			$("input[name='international_country']").val($("#address_nation").val());
		}

		// 결제 방법 선택
		$("input[name='payment']").on("click",function(){
			change_payment_type(this);
		});
		$("input[name='payment']").first().attr("checked",true).trigger('change');
		if($("input[name='payment']").length%2==1) $("input[name='payment']").closest('ul#payment_type').children('li').last().width('100%');


		// 결제금액 계산
		$("button#coupon_order, button#coupon_cancel").bind("click",function(){

			if($(this).attr('id')=='coupon_cancel'){
				$("select.coupon_select").val('').change();
			}

			$("select.coupon_select").each(function(){
				var str = $(this).attr('id');
				var arr = str.split('_');
				var cart_seq = arr[1];
				var cart_option_seq = arr[2];
				$("input[name='coupon_download["+cart_seq+"]["+cart_option_seq+"]']").val($(this).find("option:selected").val());
			});

			$("select.shipping_coupon_select").each(function(){
				var str				= $(this).attr('id');
				str = str.replace('shippingcoupon_','');
				$("input[name='shippingcoupon_download["+str+"]']").val($(this).find("option:selected").val());
			});

			$("select.ordersheet_coupon_select").each(function(){
				$("input[name='ordersheet_coupon_download_seq").val($(this).find("option:selected").val());
			});
			order_price_calculate();

			if(typeof gl_mobile_mode!="undefined" && gl_mobile_mode){
				$("#coupon_apply").click();
			}else{
				closeDialog("coupon_apply_dialog");
			}

		});

		$("select[name='international'], select[name='region']").bind("change",function(){
			order_price_calculate();
		});
		$("input[name='shipping_method'], input[name='shipping_method_international']").bind("click",function(){
			order_price_calculate();
		});
		$("input[name='recipient_zipcode[]'], input[name='recipient_address'], input[name='emoney']").bind("blur",function(){
			order_price_calculate();
		});

		//레이어 결제창
		var mobile_new = '';
		if((gl_pg_company == 'inicis') && $("input[name='mobilenew']")){
			$("input[name='mobilenew']").val('N');
		}	//이니시스는 iframe 사용 안함

		if($("#layer_pay").length > 0 && $("input[name='mobilenew']")) mobile_new = $("input[name='mobilenew']").val();

		$("#pay").bind("click",function(){
			if ( typeof order_version == 'undefined' && $("input[name='order_version']").length > 0 ) order_version = $("input[name='order_version']").val();

			// 최종 배송지 결정 및 validation :: 2017-06-01 lwh
			if(!succ_input('delivery')){ return false; }
			requiredChange('','');

			var add_nation			= $("#address_nation").val();

			if(add_nation != "KOREA"){ add_nation = "INTERNATIONAL"; }
			if(is_goods == false) required_field = ticket_required_field;

			/* 모바일 배송지 정보 입력 후 적용 버튼 클릭 확인 */
			if(!is_members){
				var recipient_null = 0;
				for(key in required_field){
					if	(typeof required_field[key] != 'object' && required_field[key].hasOwnProperty('length') == false)
						continue;
					if(key == add_nation){
						for(var i=0; i < required_field[add_nation].length; i++){
							if($("input[name='"+required_field[add_nation][i]+"']").attr("required") && $("input[name='"+required_field[add_nation][i]+"']").val().trim() == ""){
								recipient_null++;
							}
						}
					}
				}

				if(recipient_null > 0){
					openDialogAlert("배송지 정보를 모두 입력 후 적용 버튼을 눌러 주세요",400,150);
					return false;
				}
			}

			var validation_chk = validationCheck('all');
			if(validation_chk != true) return false;

			if(!gl_isuser){ //비회원 개인정보 동의}
				if(typeof order_version != 'undefined' && parseFloat(order_version) >= 2) { // 신 스킨
					if($("input[name='agree1']").length > 0 && $("input[name='agree1']:checked").val()!='Y'){
						// 서비스 이용 약관에 동의하셔야 합니다.
						alert(getAlert('os236'));
						$("input[name='agree1']").focus();
						return false;
					}
					if($("input[name='agree2']").length > 0 && $("input[name='agree2']:checked").val()!='Y'){
						//개인정보 수집ㆍ이용에 동의하셔야 합니다.
						alert(getAlert('os162'));
						$("input[name='agree2']").focus();
						return false;
					}
				} else { // 구 스킨
					if($("input[name='agree']").length > 0 && $("input[name='agree']:checked").val()!='Y'){
						//개인정보 수집ㆍ이용에 동의하셔야 합니다.
						alert(getAlert('os085'));
						$("input[name='agree']").focus();
						return false;
					}
				}
			}

			if(typeof order_version != 'undefined' && parseFloat(order_version) >= 2) { // 신 스킨
				if($("input[name='agree3']").length > 0 && $("input[name='agree3']:checked").val()!='Y'){
					//개인정보 제3자 제공 동의하셔야 합니다.
					alert(getAlert('os147'));
					$("input[name='agree3']").focus();
					return false;
				}
			}else{
				if($("input[name='agree2']").length > 0 && $("input[name='agree2']:checked").val()!='Y'){
					//개인정보 제3자 제공 동의하셔야 합니다.
					alert(getAlert('os147'));
					$("input[name='agree2']").focus();
					return false;
				}
			}

			if (typeof gl_iscancellation !== 'undefined' && gl_iscancellation){ //청약철회 관련방침 }
				if($("input[name='cancellation']:checked").val()!='Y'){
					//청약철회 관련방침에 동의하셔야 합니다.
					alert(getAlert('os086'));
					$("input[name='cancellation']").focus();
					return false;
				}
			}

			if(typeof gl_isdelegation !== 'undefined'){ //개인정보 취급위탁에 대한 동의
				if(gl_isdelegation){
					var chkObj = $("input[name='policy_delegation']");
					if(chkObj.length == 0) { 
						chkObj =  $("input[name='delegation']"); 
					}
					if(chkObj.is(":checked") != true){
						//개인정보 취급위탁에 동의해 주세요.
						alert(getAlert('os250'));
						chkObj.focus();
						return false;
					}
				}
			}

			var f = $("form#orderFrm");
			f.attr("action",gl_ssl_action);

			// 현재 선택한 결제방법 기준
			var sel_payment	= $("input[name='payment']:checked").val();
			var target_actionFrame = ['kakaopay','payco','naverpayment'];
			if ( $.inArray(sel_payment, target_actionFrame) > 0 ) {
				// 카카오페이, 페이코, 네이버페이
				f.attr("target","actionFrame");
				$("iframe[name='actionFrame']").hide();
			} else if ( sel_payment == 'eximbay' || sel_payment == 'paypal' ) {
				// 엑심베이, 페이팔
				f.attr("target","tar_opener");
				mobile_pay_layer();
			} else if ( sel_payment == 'bank' ) {
				// 무통장
				f.attr("target","actionFrame");
			} else {
				// 그외
				// 이니시스 , kcp , 토스(lg)예외
				var target_self = ['inicis','kspay','lg'];
				if ($.inArray(gl_pg_company, target_self) == -1) {
					f.attr("target","tar_opener");
					mobile_pay_layer();
				}
			}

			f.submit();
		});


		// 쿠폰사용 다이얼로그
		$("button#coupon_apply").bind("click",function(){
			sametime_coupon_dialog();
		});

		// 쿠폰사용가능한 상품 조회하기 (적용대상조회)
		$('.ordercoupongoodsreviewbtn, .shippingcoupongoodsreviewbtn, .ordersheetcoupongoodsreviewbtn').live("click",function(){
			var arr_class = {
				'goods'			: 'ordercoupongoodsreviewbtn'
				, 'shipping'	: 'shippingcoupongoodsreviewbtn'
				, 'ordersheet'	: 'ordersheetcoupongoodsreviewbtn'
			};
			var mode = "goods";
			for(var tmp_mode in arr_class){
				if($(this).hasClass(arr_class[tmp_mode])){
					mode = tmp_mode;
				}
			}

			var download_seq = $(this).attr("download_seq");
			if(!download_seq) {
				//상품쿠폰을 선택해 주세요!
				var msg = getAlert('os165');
				if(mode=="goods"){
					//상품쿠폰을 선택해 주세요!
					msg = getAlert('os165');
				}else if(mode=="shipping"){
					msg = getAlert('os089');
				}
				openDialogAlert(msg,400,150);
				return false;
			}
			var coupongoodsreviewerurl = '../coupon/coupongoodsreviewer?no='+download_seq+'&download_seq='+download_seq;
			var coupon_name = $(this).attr("coupon_name");
			//쿠폰정보 확인하기
			addFormDialog(coupongoodsreviewerurl, '450', '', getAlert('os172'),'false');
		});

		//상품 조회후 상품검색창
		$("input:button[name=goodssearchbtn]").live("click",function(){
			var goods_seq		= $("#coupongoods_goods_seq").val();
			var coupon_seq	= $(this).attr("coupon_seq");

			if(!goods_seq) {
				//상품 고유값을 정확히 입력해 주세요.
					openDialogAlert(getAlert('os091'),'260','140',function(){$("#coupongoods_goods_seq").focus();return;});
			}else{
				$.ajax({
					'url' : '../coupon/coupongoodssearch',
					'data' : {'coupon':coupon_seq,'goods':goods_seq},
					'type' : 'post',
					'dataType': 'json',
					'success' : function(res){
						$(".coupongoodsreviewerno").hide();//상품사용불가
						$(".coupongoodsrevieweryes").hide();//쿠폰사용가능
						if( res.result == 'goodsyes' ) {
							var imgsrc = (eval("res.goods.src"))?res.goods.src:"../images/common/noimage_list.gif";
							$(".coupongoodsrevieweryes").show();
							$(".coupongoodsrevieweryes .issueGoods").find(".image").html('<'+'img class="goodsThumbView" alt="" src="'+imgsrc+'" width="50" height="50">');
							$(".coupongoodsrevieweryes .issueGoods").find(".name").html(res.goods.name);
							$(".coupongoodsrevieweryes .issueGoods").find(".price").html(res.goods.price);
							$(".coupongoodsrevieweryes .issueGoods").attr("goods_seq",goods_seq);

							//상품 고유번호 찾기
							openDialog(getAlert('os092'),"coupongoodsreviewerpopup",{"width":"480","height":"250"});
						}else if( res.result == 'goodsno' ) {
							var imgsrc = (eval("res.goods.src"))?res.goods.src:"../images/common/noimage_list.gif";
							$(".coupongoodsreviewerno").show();
							$(".coupongoodsrevieweryes .issueGoods").find(".image").html('<'+'img class="goodsThumbView" alt="" src="'+imgsrc+'" width="50" height="50">');
							$(".coupongoodsrevieweryes .issueGoods").find(".name").html(res.goods.name);
							$(".coupongoodsrevieweryes .issueGoods").find(".price").html(res.goods.price);
							$(".coupongoodsrevieweryes .issueGoods").attr("goods_seq",goods_seq);
							//상품 고유번호 찾기
							openDialog(getAlert('os092'),"coupongoodsreviewerpopup",{"width":"400","height":"250"});
						}else{
							//상품을 찾을 수 없습니다.<br/>확인 후 다시 입력하시기 바랍니다.
							openDialogAlert(getAlert('os093'),'250','160');
						}
					}
				});
			}
		});

		//상품상세보기
		$('.coupongoodsdetail').live("click",function(){
			var openurl = url;
			window.open(openurl + "/goods/view?no="+$(".coupongoodsrevieweryes .issueGoods").attr("goods_seq"),'','');
		});


		set_pay_button();
		check_shipping_method();
		if(shipping_policy_count > 1){
			$("tr.shipping_tr").show();
		}else{
			$("tr.shipping_tr").hide();
		}
		if(shipping_policy_count_detail == true){ $("div.international_layer").show(); }

		$('.couponDownload').bind("click",function() {
			if(!gl_isuser){
				location.href="/member/login?return_url="+gl_request_uri;
				return false;
			}
			var gl_goods_seq = $(this).attr("goods_seq");
			coupondownlist(gl_goods_seq,gl_request_uri);
		});

		$("button[name='couponDownloadButton']").live("click",function(){
			var url = '../coupon/download?goods='+$(this).attr('goods')+'&coupon='+$(this).attr('coupon');
			actionFrame.location.href = url;
		});

		order_price_calculate();

		//현금영수증 개인공제용
		$("#cuse0").click(function(){
			$("#personallay").show();
			$("#businesslay").hide();
		});
		//현금영수증 사업자지출증빙용
		$("#cuse1").click(function(){
			$("#personallay").hide();
			$("#businesslay").show();
		});


		// 에스크로
		if(escrow_view == false){ $("#escrow").hide(); }

		// facebook 라이크 할인 적용 및 오픈그라피
		$.get('../order/fblike_opengraph', function(data) {
			$("#facebook_mgs").html("");
			order_price_calculate();
		});


		$(".setttlefblikebtn").click(function(){
			window.open('http://{config_system.subDomain}/admin/sns/domain_facebook?fblike_return_url={_SERVER.HTTP_HOST}','','width=750px,height=500px,toolbar=no,location=no,resizable=yes, scrollbars=yes');
		});

		$("input[name='gift_use']").change(function(){
			var type = $(this).attr('type');
			var value = $(this).val();

			if(type=='checkbox') value = $(this).is(":checked") ? 'Y' : 'N';

			if(value=='Y'){
				$(".giftTable").show();

				if($("#multiShippingChk").is(":checked")){
					$(".giftReceiveTr").show();
				}
			}else if(value=='N'){
				$(".giftTable").hide();

				if($("#multiShippingChk").is(":checked")){
					$(".giftReceiveTr").hide();
				}
			}
		});

		//emoney 입력후 엔터
		$("input[name='cash_view']").bind("keydown", function(e) {
			if (e.keyCode == 13) { // enter key
				use_cash();
				return false
			}
		});

		//emoney 입력후 엔터
		$("input[name='emoney_view']").bind("keydown", function(e) {
			if (e.keyCode == 13) { // enter key
				use_emoney();
				return false
			}
		});


		//할인코드입력후 엔터
		$("#cartpromotioncode").bind("keydown", function(e) {
			if (e.keyCode == 13) { // enter key
				getPromotionck();
				return false
			}
		});

		if(cart_promotioncode != ""){ getPromotionckloding(cart_promotioncode); }


		// 영수증 발급을 클릭했을경우
		$("select[name='typereceipt']").change(function() { check_typereceipt(); });

		// 배송국가 변경시 :: 2016-08-03 lwh  // 재계산
		$("#address_nation").bind('change',function(){ order_price_calculate(); });

		// 전체 동의 :: 2017-06-02 lwh
		$("#all_agree").on('change', function(){
			if($(this).is(":checked")){
				$(".agree_chk").each(function(){
					$(this).closest("div.ez-checkbox").addClass('ez-checkbox-on');
				});
				$(".agree_chk").prop("checked", true);
			}else{
				$(".agree_chk").each(function(){
					$(this).closest("div.ez-checkbox").removeClass('ez-checkbox-on');
				});
				$(".agree_chk").prop("checked", false);
			}
		});

		// 동의 상세 설명 보기 :: 2017-06-02 lwh
		$(".btn_policy").on("click", function() {
			var stat = $(this).attr('stat');
			if(stat == 'close'){
				$(this).attr('stat','open');
				$(this).closest('div.policy_area').find('div.policy_contents').show();
				$(this).html('▲');
			}else{
				$(this).attr('stat','close');
				$(this).closest('div.policy_area').find('div.policy_contents').hide();
				$(this).html('▼');
			}
		});

		if ( is_members == true ) {
			// 모바일 기본 주소 노출
			if ( !$("input[name='recipient_input_address']").val() && !$("input[name='international_address_input']").val() ) {
				$.ajax({
					'url' : 'ajax_get_delivery_address',
					'data' : {'type':'often'},
					'dataType' : 'json',
					'success' : function(res){
						if(res){

							$("input[name='address_group']").val(res.address_group);

							if(res.defaults=='Y'){
								$("input[name='save_delivery_address']").closest("div.ez-checkbox").removeClass("ez-checkbox-on");
								$("input[name='save_delivery_address']").closest("div.ez-checkbox").addClass("ez-checkbox-on");
								$("input[name='save_delivery_address']").attr('checked',true);
							}else{
								$("input[name='save_delivery_address']").removeAttr('checked');
							}

							// 국가 정보 없는경우 재 등록요구 :: 2016-08-03 lwh
							if(!res.nation && res.international == 'international'){
								alert('배송국가가 지정되지 않았습니다.\n수정을 통해 배송국가를 지정해주세요.');
								return;
							}

							if(res.nation == 'KOREA' || res.international == 'domestic'){
								// input values
								$("#international").val('0');
								$(".kr_zipcode").show();

								$("input[name='recipient_input_address_type']").val(res.recipient_address_type);
								$("input[name='recipient_input_address']").val(res.recipient_address);
								$("input[name='recipient_input_address_street']").val(res.recipient_address_street);
								$("input[name='recipient_input_address_detail']").val(res.recipient_address_detail);
								$("input[name='recipient_input_new_zipcode']").eq(0).val(res.recipient_new_zipcode);

								// span values
								$(".recipient_zipcode").html(res.recipient_new_zipcode);
								if(res.recipient_address_type == 'street'){
									$(".recipient_address").html(res.recipient_address_street);
								}else{
									$(".recipient_address").html(res.recipient_address);
								}
								$(".recipient_address_detail").html(res.recipient_address_detail);
								$(".international_nation").html('대한민국');
								$("#address_nation").val('KOREA').trigger('change');
							}else{
								// input values
								$("#international").val('1');
								$(".kr_zipcode").hide();

								$("select[name='region']").val(res.region);
								$("input[name='international_county_input']").val(res.international_county);
								$("input[name='international_address_input']").val(res.international_address);
								$("input[name='international_town_city_input']").val(res.international_town_city);
								$("input[name='international_postcode_input']").val(res.international_postcode);
								$("input[name='international_country_input']").val(res.international_country);

								// span values
								var international_address = res.international_address + ',' + res.international_town_city + ',' + res.international_county + ',' + data.international_postcode + ',' + data.international_country;
								$(".recipient_address").html(international_address);
								$(".international_nation").html(res.nation);
								$("#address_nation").val(res.nation).trigger('change');
							}

							// 공통 부분 input values
							$("input[name='address_description_input']").val(res.address_description);
							$("input[name='recipient_input_user_name']").val(res.recipient_user_name);
							if (res.recipient_phone != null) {
								phone = new Array();
								phone = res.recipient_phone.split('-');
								$(".delivery_input").find("input[name='recipient_input_phone[]']").each(function(idx){
									$(".delivery_input").find("input[name='recipient_input_phone[]']").eq(idx).val(phone[idx]);
								});
							}
							if (res.recipient_cellphone != null) {
								cellphone = new Array();
								cellphone = res.recipient_cellphone.split('-');
								$(".delivery_input").find("input[name='recipient_input_cellphone[]']").each(function(idx){
									$(".delivery_input").find("input[name='recipient_input_cellphone[]']").eq(idx).val(cellphone[idx]);
								});
							}
							// 공통 부분 span values
							$(".recipient_user_name").html(res.recipient_user_name);
							$(".cellphone").html(res.recipient_cellphone);
							$(".phone").html(res.recipient_phone);

						}
					}
				});
			}

			const default_add_nation = $("#default_address_nation").val();

			// 기본배송지 국가체크
			if (typeof default_add_nation != 'undefined' && default_add_nation.length > 0 && default_add_nation !== 'KOREA') {
				$("#international").val('1');
				$(".kr_zipcode").hide();

				var international_county = $("input[name='international_county_input']").val();
				var international_address = $("input[name='international_address_input']").val();
				var international_town_city = $("input[name='international_town_city_input']").val();
				var international_postcode = $("input[name='international_postcode_input']").val();
				var international_country = $("input[name='international_country_input']").val();

				// span values
				var international_address = international_address + ',' + international_town_city + ',' + international_county + ',' + international_postcode + ',' + international_country;
				$(".recipient_address").html(international_address);
				$(".international_nation").html(default_add_nation);
				$("#address_nation").val(default_add_nation).trigger('change');
			}
		}

		// 디자인팀 수정
		$(".ship_message .click").bind("click", function(){
			$(".add_message").toggle();
			$(this).closest(".ship_message").find(".add_message").show();
		});
		$(".add_message li").bind("click", function(){
			var sel_message = $(this).html();
			sel_message = sel_message.replace(/^\<span class\=\"lately desc\"\>[^\<]*\<\/span\>/,'');
			$(this).closest(".ship_message").find(".ship_message_txt").val(sel_message).trigger('change');
			$(".add_message").hide();
		});
		$(".detailDescriptionLayerCloseBtn").click(function(){
			$(this).closest('div.detailDescriptionLayer').toggle()
		});

		/* 탭메뉴 */
		$(".settle_tab li").click(function(){
			// 직접입력 부분 초기화
			//$(".delivery_input").find("input").val('');
			$(".settle_tab li").removeClass("current");
			$(this).addClass("current");
			var $boxVar = $(this).index() + 1;
			$(".settle_tab_contents").css("display","none");
			$(".settle_tab_contents.tab_box"+$boxVar).css("display","block");
			if(is_goods != true){ $(".goods_delivery_info").hide();}
		});

		$("#payment_type > li:first-child > div").addClass("active");
		$("#payment_type > li > div").click(function(){
			if (isPaymentOnlyKakaoPay() === true) {
				// 카카오페이 결제만 가능
				return false;
			}

			$("#payment_type > li > div").removeClass("active");
			$(this).addClass("active");
		});

		$(".nation_show").bind("click",function(){ $('.nation').show(); });

		// ### 기본 초기 설정 :: START
		if(is_members == true){
			if(skin_order_settle_ver >= 1){ // 신규구조 :: 2017-06-01 lwh
				$(".order_info_member").show();
				$(".order_info_input").hide();
				$(".delivery_info_member").show();
				$(".delivery_info_input").hide();
			}else{
				$(".delivery_info").show();
				$(".order_member").show();
				$(".delivery_input").hide();
				$(".order_input").hide();
			}
		}else{
			if(skin_order_settle_ver >= 1){ // 신규구조 :: 2017-06-01 lwh
				$(".order_info_member").hide();
				$(".order_info_input").show();
				$(".delivery_info_member").hide();
				$(".delivery_info_input").show();
				$(".delivery_input").show();
			}else{
				$(".delivery_info").hide();
				$(".delivery_choice").hide();
				if(is_goods == true) { address_modify('delivery'); }
				$(".order_member").hide();
				$(".delivery_input").show();
				$(".order_input").show();
				$(".order_input").removeClass("hide");
			}
		}

		if ($("#address_nation").val() != 'KOREA') {
			$(".domestic").hide();
			$(".international").show();
		}else{
			$(".domestic").show();
			$(".international").hide();
		}

		if(is_goods != true){ $(".goods_delivery_info").hide();}
		if(typeof is_address != 'undefined' && is_address){
			address_modify('delivery');
			address_modify('order');
		}
		order_price_calculate();
		area_close_chk(); // 각 레이어별 닫힘 체크 :: 2017-05-29 lwh
		// ### 기본 초기 설정 :: END

		//증빙서류 발급
		$("input[name='typereceiptuse']").click(function() {
			check_typereceiptuse();
		});
	});

	/*PG 결제 스크립트 함수 (절대 수정하지 마시오)*/

	/*이니시스*/
	function pay() {
		INIStdPay.pay('SendPayForm_id');
	}

// 주소록 페이징
function popDeliverypage(params){
	$.ajax({
		'url'	: '/order/pop_delivery_address',
		'data'	: params,
		'type'	: 'get',
		'dataType': 'text',
		'success': function(html) {
			if(html){
				$(".delivery_often").html(html);
			}else{
				alert("주소록을 로드하지 못했습니다.");
				document.location.reload();
			}
		}
	});
}

// 배송지 수정
function delivery_modify(seq){
	// 두번째 입력탭으로 이동시킴
	$(".settle_tab li").removeClass("current");
	$(".input_tab").addClass("current");
	var $boxVar = $(".input_tab").index() + 1;
	$(".settle_tab_contents").css("display","none");
	$(".settle_tab_contents.tab_box"+$boxVar).css("display","block");

	// 데이터를 추출하여 input에 강제로 삽입
	$.ajax({
		url: '/mypage/delivery_address_ajax',
		data : {'address_seq':seq},
		dataType : 'json',
		success: function(data) {

			// input box 초기화
			$(".delivery_input").find("input").val('');
			$(".delivery_input").find("input[name='address_group_input']").val(data.address_group);

			if(data.defaults=='Y'){
				$(".delivery_input").find("input[name='save_delivery_address_input']").attr('checked',true);
			}else{
				$(".delivery_input").find("input[name='save_delivery_address_input']").removeAttr('checked');
			}
			chg_shipping_nation(data.nation);
			if(data.nation == 'KOREA' || data.international == 'domestic'){
				$(".delivery_input").find("select[name='nation_select']").val('KOREA');
				$(".delivery_input").find("input[name='address_description_input']").val(data.address_description);
				$(".delivery_input").find("input[name='recipient_input_user_name']").val(data.recipient_user_name);
				$(".delivery_input").find("input[name='recipient_input_address_type']").val(data.recipient_address_type);
				$(".delivery_input").find("input[name='recipient_input_address']").val(data.recipient_address);
				$(".delivery_input").find("input[name='recipient_input_address_street']").val(data.recipient_address_street);
				$(".delivery_input").find("input[name='recipient_input_address_detail']").val(data.recipient_address_detail);
				$(".delivery_input").find("input[name='recipient_input_new_zipcode']").eq(0).val(data.recipient_new_zipcode);


				phone = new Array();
				phone = data.recipient_phone.split('-');
				$(".delivery_input").find("input[name='recipient_input_phone[]']").each(function(idx){
					$(".delivery_input").find("input[name='recipient_input_phone[]']").eq(idx).val(phone[idx]);
				});

				cellphone = new Array();
				cellphone = data.recipient_cellphone.split('-');
				$(".delivery_input").find("input[name='recipient_input_cellphone[]']").each(function(idx){
					$(".delivery_input").find("input[name='recipient_input_cellphone[]']").eq(idx).val(cellphone[idx]);
				});
			}else{
				$(".delivery_input").find("select[name='nation_select']").val(data.nation);
				$(".delivery_input").find("input[name='address_description']").val(data.address_description);
				$(".delivery_input").find("input[name='recipient_input_user_name']").val(data.recipient_user_name);
				$(".delivery_input").find("select[name='region']").val(data.region);
				$(".delivery_input").find("input[name='international_county_input']").val(data.international_county);
				$(".delivery_input").find("input[name='international_address_input']").val(data.international_address);
				$(".delivery_input").find("input[name='international_town_city_input']").val(data.international_town_city);
				$(".delivery_input").find("input[name='international_postcode_input']").val(data.international_postcode);
				$(".delivery_input").find("input[name='international_country_input']").val(data.international_country);

				phone = new Array();
				phone = data.recipient_phone.split('-');
				$(".delivery_input").find("input[name='international_recipient_phone_input[]']").each(function(idx){
					$(".delivery_input").find("input[name='international_recipient_phone_input[]']").eq(idx).val(phone[idx]);
				});

				cellphone = new Array();
				cellphone = data.recipient_cellphone.split('-');
				$(".delivery_input").find("input[name='international_recipient_cellphone_input[]']").each(function(idx){
					$(".delivery_input").find("input[name='international_recipient_cellphone_input[]']").eq(idx).val(cellphone[idx]);
				});
			}

			chg_address_nation($(".delivery_input").find("select[name='nation_select']"));

			$(".delivery_input").find("input[name='insert_mode']").val('update');
			$(".delivery_input").find("input[name='address_seq']").val(seq);

			openDialog("배송지 수정 하기", "inAddress", {"width":550,"height":420});
		}
	});
}

// 배송지 삭제
function delivery_delete(seq){
	//정말 삭제하시겠습니까?
	var chk = confirm(getAlert('os154'));
	if(chk == true){
		var str="../mypage_process/delete_address?address_seq=" + seq + "&page_type=mobile";
		$("iframe[name='actionFrame']").attr('src',str);
	}
}
