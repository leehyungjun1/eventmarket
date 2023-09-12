	if(is_file_facebook_tag == true){
		try{
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
		} catch (facebookjsok) {
		}
	}

	function getfblikeopengraph(){
	}

	function click_Kakaopay(obj){
		$("input[name='payment']:radio[value='kakaopay']").attr('checked',true).trigger('change');
	}

	function mobile_pay_layer(){
		var divLayer = $("#payprocessing").clone().wrapAll("<div/>").parent().html();
		divLayer = divLayer + '<iframe name="tar_opener" frameborder="0" border="0" width="350" height="100%" scrolling="auto" style="margin:0px auto;"></iframe>';
		$("#layer_pay").html(divLayer);
		$("#layer_pay").css("position","fixed");
		$("#layer_pay").css("display","inline-block");
		window.parent.$("body").scrollTop(0);
	}

	function mobile_popup(){
		var xpos = 100;
		var ypos = 100;
		var position = "top=" + ypos + ",left=" + xpos;
		var features = position + ", width=320, height=440";
		var wallet = window.open("", "tar_opener", features);
		wallet.focus();
	}

	// 배송지 수정 - input box show :: 2016-08-02 lwh
	function address_modify(type){
		var international = $("#address_nation").val();
		if(!type) type = 'delivery';
		$("." + type + "_member").hide();
		$("." + type + "_input").show();

		// 추가 연락처 체크 :: 2017-05-16 lwh
		add_phone($("#btn_" + type + "_add_phone"),'check');

		if(type == 'delivery'){
			set_shipping('input');
			$("#chkQuickAddress_new").attr("checked",true);
		}
	}

	// PC용 주소지정 - 주소록 지정 / 배송지 선택지정
	function set_address(addr){
		if(addr == 'new'){ // 신규 배송지
			$("input[name='recipient_new_zipcode']").val('');
			$("input[name='recipient_address_type']").val('');
			$("input[name='recipient_address']").val('');
			$("input[name='recipient_address_street']").val('');
			$("input[name='recipient_address_detail']").val('');
			$("input[name='recipient_user_name']").val('');
			$("input[name='international_address']").val('');
			$("input[name='international_town_city']").val('');
			$("input[name='international_county']").val('');
			$("input[name='international_postcode']").val('');
			$("input[name='recipient_phone[]']").each(function(idx){
				$("input[name='recipient_phone[]']").eq(idx).val("");
			});
			$("input[name='recipient_cellphone[]']").each(function(idx){
				$("input[name='recipient_cellphone[]']").eq(idx).val("");
			});
			$("input[name='recipient_email']").val('');
			$(".international").hide();
			if(!$("#address_nation").val()){
				$("#address_nation").val('KOREA').trigger('change');
			}
			set_shipping('input');
			$("#chkQuickAddress_new").attr("checked",true);
		}else if(addr == 'modify'){
			$(".delivery_input").show();
			$(".delivery_member").hide();
			$(".international").hide();
			$("#address_nation").val('KOREA').trigger('change');
		}else{
			$(".kr_zipcode").show();
			if(addr.nation == 'KOREA' || addr.international == 'domestic'){
				// input values
				$("#international").val('0');
				$(".kr_zipcode").show();
				$("input[name='address_description']").val(addr.address_description);
				$("input[name='recipient_user_name']").val(addr.recipient_user_name);
				$("input[name='recipient_address_type']").val(addr.recipient_address_type);
				$("input[name='recipient_address']").val(addr.recipient_address);
				$("input[name='recipient_address_street']").val(addr.recipient_address_street);
				$("input[name='recipient_address_detail']").val(addr.recipient_address_detail);
				$("input[name='recipient_new_zipcode']").eq(0).val(addr.recipient_new_zipcode);
				$("input[name='recipient_email']").val(addr.recipient_email);
				if (addr.recipient_phone != null) {
					$("input[name='recipient_phone[]']").each(function(idx){
						$(this).val( addr.recipient_phone.split('-')[idx] );
					});
				}
				if (addr.recipient_cellphone != null) {
					$("input[name='recipient_cellphone[]']").each(function(idx){
						$(this).val( addr.recipient_cellphone.split('-')[idx] );
					});
				}

				// span values
				if(addr.recipient_user_name)
						$(".recipient_user_name").html(addr.recipient_user_name);
				else	$(".recipient_user_name").html('받는분 없음');

				if(addr.recipient_new_zipcode){
					$(".recipient_zipcode").html(addr.recipient_new_zipcode);
					if(addr.recipient_address_type == 'street'){
						$(".recipient_address").html(addr.recipient_address_street);
					}else{
						$(".recipient_address").html(addr.recipient_address);
					}
					$(".recipient_address_detail").html(addr.recipient_address_detail);
				}else{
					$(".kr_zipcode").hide();
					$(".recipient_address").html('배송주소 없음');
				}

				if(addr.recipient_cellphone)
						$(".delivery_member").find(".cellphone").html(addr.recipient_cellphone);
				else	$(".delivery_member").find(".cellphone").html('휴대폰번호 없음');

				if(addr.recipient_phone)
						$(".delivery_member").find(".phone").html(addr.recipient_phone);
				else	$(".delivery_member").find(".phone").html('추가연락처 없음');

				$(".international_nation").html('대한민국');
				$("#address_nation").val('KOREA').trigger('change');
			}else{
				// input values
				$("#international").val('1');
				$(".kr_zipcode").hide();
				$("input[name='address_description']").val(addr.address_description);
				$("input[name='recipient_user_name']").val(addr.recipient_user_name);
				$("select[name='region']").val(addr.region);
				$("input[name='international_county']").val(addr.international_county);
				$("input[name='international_address']").val(addr.international_address);
				$("input[name='international_town_city']").val(addr.international_town_city);
				$("input[name='international_postcode']").val(addr.international_postcode);
				$("input[name='international_country']").val(addr.international_country);
				$("input[name='recipient_email']").val(addr.recipient_email);
				if (addr.recipient_phone != null) {
					$("input[name='recipient_phone[]']").each(function(idx){
						$(this).val( addr.recipient_phone.split('-')[idx] );
					});
				}
				if (addr.recipient_cellphone != null) {
					$("input[name='recipient_cellphone[]']").each(function(idx){
						$(this).val( addr.recipient_cellphone.split('-')[idx] );
					});
				}

				// span values
				$(".recipient_user_name").html(addr.recipient_user_name);
				var international_address = addr.international_address + ',' + addr.international_town_city + ',' + addr.international_county + ',' + addr.international_postcode + ',' + addr.international_country;
				$(".recipient_address").html(international_address);
				$(".delivery_member").find(".cellphone").html(addr.recipient_cellphone);
				$(".delivery_member").find(".phone").html(addr.recipient_phone);
				$(".recipient_email").html(addr.recipient_email);
				$(".international_nation").html(addr.nation);
				$("#address_nation").val(addr.nation).trigger('change');
			} // end nation if

			// 이메일 주소 인덱스가 존재할 때만 덮어쓰게 수정 : rsh 2019-03-15
			if(addr.recipient_email !== '' && addr.recipient_email !== undefined) {
				$(".recipient_email").html(addr.recipient_email);
			} else if (addr.recipient_email === '') {
				$(".recipient_email").html('이메일주소 없음');
			}

			set_shipping('view');
			$("#chkQuickAddress_often").attr("checked",true);
		} // end address if

		$("input[name='recipient_address']").attr('readonly','readonly');
	}

	/**
	 * 결제버튼노출
	 */
	function set_pay_button(){
		$.ajax({
			'url' : '../order/settle_order_images',
			'dataType': 'json',
			'cache': false,
			'success': function(data) {

				//결제하기
				$("#pay").html(getAlert('os157'));
				$("input[name='payment']:checked").each(function(){
					if( $(this).val() == "bank" ){
						//주문하기
						$("#pay").html(getAlert('os158'));
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

	function reverse_pay_layer(){

		$('#wrap').show();
		$('#layer_pay').hide();
		reverse_pay_button();
	}

	/* required 체크 항목 재정의 시작 */
	var required_field		= new Array();
	var ticket_required_field		= new Array();

	required_field['KOREA']				= [ 'recipient_user_name',
											'recipient_new_zipcode',
											//'recipient_address_type',
											//'recipient_address_street',
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

	ticket_required_field['KOREA']				= [ 'recipient_user_name',
											'recipient_cellphone[]'
										];
	ticket_required_field['INTERNATIONAL']		= [ 'international_recipient_cellphone[]'];
	ticket_required_field['ORDER']				= [ 'order_user_name',
											'order_email',
											'order_cellphone[]'
										];

	function requiredChange(){
		var add_nation			= $("#address_nation").val();
		var address_type		= $("input[name='recipient_address_type']").val();

		// 주소타입 재정의
		if(!address_type){
			if($("input[name='recipient_address_street']").hasClass('hide')){
				address_type == 'zibun';
			}else{
				address_type == 'street';
			}
		}
		if(address_type == 'street'){
			required_field['KOREA'][required_field['KOREA'].length] = 'recipient_address_street';
		}

		if(add_nation != "KOREA"){ add_nation = "INTERNATIONAL"; }
		for(key in required_field){
			for(var i=0; i < required_field[key].length; i++){
				$("form[name='orderFrm'] input[name='"+required_field[key][i]+"']").removeAttr("required");
			}
		}
		if(is_goods == false)	required_field = ticket_required_field;

		for(var i=0; i < required_field[add_nation].length; i++){
			$("form[name='orderFrm'] input[name='"+required_field[add_nation][i]+"']").attr("required",true);
		}
		for(var i=0; i < required_field['ORDER'].length; i++){
			$("form[name='orderFrm'] input[name='"+required_field['ORDER'][i]+"']").attr("required",true);
		}
	}
	/* required 체크 항목 재정의 끝 */

	$(window).load(function(){
		// 배송지 등록 / 수정 :: 2016-08-02 lwh
		$("select[name='select_address_group']").bind('change',function(){
			if($(this).val()==""){
				$("input[name='address_group']").val('').show();
			}else{
				$("input[name='address_group']").val($(this).val()).hide();
			}
		}).trigger('change');

		// 배송그룹 등록/수정 :: 2016-08-02 lwh
		$("#insert_address").bind("click",function(){
			var f = $("form#in_Address");
			f.attr("action","../mypage_process/delivery_address");
			f.attr("target","actionFrame");
			f[0].submit();
		});

		// 배송지 정보 채우기
		$("#copy_order_info").bind("click",function(){
			$("input[name='recipient_user_name']").val( $("input[name='order_user_name']").val() );

			$("input[name='order_phone[]']").each(function(idx){
				$("input[name='recipient_phone[]']").eq(idx).val( $("input[name='order_phone[]']").eq(idx).val() );
			});

			$("input[name='order_cellphone[]']").each(function(idx){
				$("input[name='recipient_cellphone[]']").eq(idx).val( $("input[name='order_cellphone[]']").eq(idx).val() );
			});

			$("input[name='recipient_email']").val( $("input[name='order_email']").val() );
		});

		// 주문자 정보 채우기 :: 2017-05-16 lwh
		$("#copy_delivery_info").bind("click",function(){
			if($("input[name='order_user_name']").attr('readonly') != 'readonly'){
				$("input[name='order_user_name']").val( $("input[name='recipient_user_name']").val() );
			}

			$("input[name='recipient_phone[]']").each(function(idx){
				$("input[name='order_phone[]']").eq(idx).val( $("input[name='recipient_phone[]']").eq(idx).val() );
			});

			$("input[name='recipient_cellphone[]']").each(function(idx){
				$("input[name='order_cellphone[]']").eq(idx).val( $("input[name='recipient_cellphone[]']").eq(idx).val() );
			});

			$("input[name='order_email']").val( $("input[name='recipient_email']").val() );
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
		$("input[name='payment']").on("click change",function(){
			change_payment_type(this);
		});
		$("input[name='payment']").first().attr("checked",true).trigger('change');

		// 결제금액 계산
		$("button#coupon_order").bind("click",function(){
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
			closeDialog("coupon_apply_dialog");
		});
		$("input[name='shipping_method'], input[name='shipping_method_international']").bind("click",function(){
			order_price_calculate();
		});
		$("input[name='recipient_zipcode[]'], input[name='recipient_address'], input[name='emoney'], input[name='cash']").bind("blur",function(){
			order_price_calculate();
		});


		if(isMobile.any() && gl_set_mode == 'pc') {
			var toHTML = ''
			+'<!-- 결제창을 레이어 형태로 구현-->'
			+'<style type="text/css">'
			+'#layer_pay {position:absolute;top:0px;width:100%;height:100%;background-color:#ffffff;text-align:center;z-index:999999;}'
			+'#payprocessing {text-align:center;position:absolute;width:100%;top:150px;z-index:99999999px;}'
			+'</style>'
			+'<div id="layer_pay" class="hide"></div>'
			+'<div id="payprocessing" class="pay_layer hide">'
			+'<div style="margin:auto;"><img src="../images/design/img_paying.gif" /></div>'
			+'<div style="margin:auto;padding-top:20px;"><img src="../images/design/progress_bar.gif" /></div>'
			+'</div>';

			$('body').append(toHTML);
			$('[name="orderFrm"]').append('<input type="hidden" name="mobilenew" value="y" />');







			//레이어 결제창
			var mobile_new = '';
			if((gl_pg_company == 'inicis') && $("input[name='mobilenew']")){
				$("input[name='mobilenew']").val('N');
			}	//이니시스는 iframe 사용 안함

			if($("#layer_pay").length > 0 && $("input[name='mobilenew']")) mobile_new = $("input[name='mobilenew']").val();
		}



		// 최종 결제 하기 :: 2016-08-03 lwh
		$("#pay").bind("click",function(){

			$("#actionFrame").attr("frameborder",0);
			//$("#actionFrame").css("height",0);
			$("#actionFrame").removeClass("hide");

			requiredChange();	//배송지/주문자 입력창 필수 입력 재정의
			var validation_chk = validationCheck('all');
			if(validation_chk != true) return false;

			var f = $("form#orderFrm");
			f.attr("action",gl_ssl_action);
			f.attr("target","actionFrame");

			// 개인통관고유부호 수집 동의
			if( $("input[name='agree_international_shipping1']").length > 0 && $("input[name='agree_international_shipping1']").is(':checked') === false ){
				//개인통관고유부호 수집에 동의하셔야 합니다.
				alert(getAlert('os160'));
				$("input[name='agree_international_shipping1']").focus();
				return false;
			}
			/*
			if( $("input[name='agree_international_shipping2']").length > 0 && $("input[name='agree_international_shipping2']").is(':checked') === false ){
				//개인통관고유부호 수집에 동의하셔야 합니다.
				alert(getAlert('os160'));
				$("input[name='agree_international_shipping2']").focus();
				return false;
			}

			if( $("input[name='agree_international_shipping3']").length > 0 && $("input[name='agree_international_shipping3']").is(':checked') === false ){
				//관부가세 발생 관련 공지를 확인하셔야 합니다.
				alert(getAlert('os161'));
				$("input[name='agree_international_shipping3']").focus();
				return false;
			}
			*/

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

			if(gl_iscancellation){ //청약철회 관련방침
				if($("input[name='cancellation']:checked").val()!='Y'){
					//청약철회 관련방침에 동의하셔야 합니다.
					alert(getAlert('os163'));
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

			if(isMobile.any()) {
				if(gl_pg_company != 'inicis') {
					if(mobile_new == 'y' && gl_mobile && gl_pg_company && $("input[name='payment']:checked").val() != 'bank' ){
						f.attr("target","tar_opener");
					}else{
						f.attr("target","actionFrame");
					}
				}

				// 카카오페이 일경우 다른 PG 레이어를 타지 않음.
				var sel_payment	= $("input[name='payment']:checked").val();
				if(sel_payment == 'kakaopay'){
					f.attr("target","actionFrame");
					$("iframe[name='actionFrame']").hide();
				} else if ( sel_payment == 'payco' ) {
					// 페이코
					f.attr("target","actionFrame");
					$("iframe[name='actionFrame']").hide();
					// window.open('about:blank','childwin','width=420,height=550');
				} else if ( sel_payment == 'naverpayment' ) {
					// 네이버페이
					f.attr("target","_self");
					$("iframe[name='actionFrame']").hide();
				} else {
					if(gl_pg_company != 'inicis' && gl_pg_company != 'kspay') {
						if(mobile_new == 'y' && gl_mobile && gl_pg_company && $("input[name='payment']:checked").val() != 'bank'){
							f.attr("target","tar_opener");
						}else{
							f.attr("target","actionFrame");
						}

						if(gl_mobile && $("input[name='payment']:checked").val() != 'bank'){
							if(mobile_new == 'y'){
								mobile_pay_layer();
							}else{

								// 2014-10-23 iphone 버전이 8.1 일경우 결제팝업은 ssl 암호화 리턴 이후 띄운다. (app/controllers/order.php)
								var iphone_ver = 0;
								if(navigator.userAgent.match(/iPhone/i)){
									if(navigator.userAgent.match(/8_1/)) iphone_ver = 81;
								}
								if(iphone_ver == 0){
									if(gl_pg_company == 'inicis'){
										//inicis_mobile_popup();
									}else{
										mobile_popup();
									}
								}
							}
						}
					}
				}
			} else {
				var sel_payment	= $("input[name='payment']:checked").val();
				if( sel_payment == 'eximbay') {
					eximbapy_popup();
					f.attr("target","payment2");
				} else if ( sel_payment == 'payco' ) {
					// 페이코
					f.attr("target","actionFrame");
					$("iframe[name='actionFrame']").hide();
					// window.open('about:blank','childwin','width=420,height=550');
				} else if ( sel_payment == 'naverpayment' ) { // 네이버페이
					f.attr("target","actionFrame");
					$("iframe[name='actionFrame']").hide();
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
					openDialogAlert(getAlert('os167'),'260','140',function(){$("#coupongoods_goods_seq").focus();return;});
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
							var imgsrc = (eval("res.goods.src"))?res.goods.src:"/admin/skin/default/images/common/noimage_list.gif";
							$(".coupongoodsrevieweryes").show();
							$(".coupongoodsrevieweryes .issueGoods").find(".image").html('<'+'img class="goodsThumbView" alt="" src="'+imgsrc+'" width="50" height="50">');
							$(".coupongoodsrevieweryes .issueGoods").find(".name").html(res.goods.name);
							$(".coupongoodsrevieweryes .issueGoods").find(".price").html(res.goods.price);
							$(".coupongoodsrevieweryes .issueGoods").attr("goods_seq",goods_seq);

							//상품 고유번호 찾기
							openDialog(getAlert('os168'),"coupongoodsreviewerpopup",{"width":"480","height":"250"});
						}else if( res.result == 'goodsno' ) {
							var imgsrc = (eval("res.goods.src"))?res.goods.src:"/admin/skin/default/images/common/noimage_list.gif";
							$(".coupongoodsreviewerno").show();
							$(".coupongoodsrevieweryes .issueGoods").find(".image").html('<'+'img class="goodsThumbView" alt="" src="'+imgsrc+'" width="50" height="50">');
							$(".coupongoodsrevieweryes .issueGoods").find(".name").html(res.goods.name);
							$(".coupongoodsrevieweryes .issueGoods").find(".price").html(res.goods.price);
							$(".coupongoodsrevieweryes .issueGoods").attr("goods_seq",goods_seq);

							//상품 고유번호 찾기
							openDialog(getAlert('os168'),"coupongoodsreviewerpopup",{"width":"400","height":"250"});
						}else{
							//상품을 찾을 수 없습니다.<br/>확인 후 다시 입력하시기 바랍니다.
							openDialogAlert(getAlert('os169'),'250','160');
						}
					}
				});
			}
		});

		//상품상세보기
		$('.coupongoodsdetail').live("click",function(){
			window.open("/goods/view?no="+$(".coupongoodsrevieweryes .issueGoods").attr("goods_seq"),'','');
		});

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

		// 영수증 발급을 클릭했을경우
		$("input[name='typereceipt']").click(function() {
			//$("select[name='typereceipt']").bind("change",function(){
			check_typereceipt();
		});

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

		//증빙서류 발급
		$("input[name='typereceiptuse']").click(function() {
			check_typereceiptuse();
		});

		// 에스크로
		if(escrow_view == false){ $("#escrow").hide(); }

		$(".setttlefblikebtn").click(function(){
			window.open('http://{config_system.subDomain}/admin/sns/domain_facebook?fblike_return_url={_SERVER.HTTP_HOST}','','width=750px,height=500px,toolbar=no,location=no,resizable=yes, scrollbars=yes');
		});

		$("input[name='gift_use']").click(function(){
			var value = $(this).val();
			if(value=='Y'){
				$(".giftTable").show();
			}else if(value=='N'){
				$(".giftTable").hide();
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

		//프로모션코드입력후 엔터
		$("#cartpromotioncode").bind("keydown", function(e) {
			if (e.keyCode == 13) { // enter key
				getPromotionck();
				return false
			}
		});

		// 배송국가 변경시 :: 2016-08-03 lwh
		$("#address_nation").bind('change',function(){
			order_price_calculate(); // 재계산
		});

		// 배송지 간편 선택 :: 2016-08-01 lwh
		$("input[name='chkQuickAddress']").bind('click',function(){
			if($(this).attr('type')=='radio' && !$(this).is(":checked")) return;

			var sel_type = $(this).val();
			var late_idx = $(this).attr('idx');
			var nation = '';
			var nationKey = '';
			var setchk = false;

			if($(this).attr('type')=='checkbox' && val=='copy'){
				if(!$(this).is(":checked")){
					val = 'new';
				}
			}

			switch(sel_type){
				case "often":
				case "lately":
				case "member":
					var data = {'type':$(this).val()};
					if(sel_type=='lately'){
						data['idx'] = late_idx;
					}
					$.ajax({
						'url' : 'ajax_get_delivery_address',
						'data' : data,
						'dataType' : 'json',
						'success' : function(res){
							if (res && $("#address_nation").val()) {
								var rnation = res.nation;
								if (rnation.indexOf($("#address_nation").val()) == -1){
									set_address('new');
									setchk = true;
								}
							}
							if(res && ! setchk)	{
								set_address(res);
							}
						}
					});
					$(".delivery_member").show();
					$(".delivery_input").hide();
				break;
				case "new":
					set_address('new');
				break;
			}

		}).first().attr('checked',true).trigger('click').trigger('change');

		$(".price_area").bind("mouseover",function(){
			$(this).parent().find(".sale_price_layer").show();
		}).bind("mouseout",function(){
			$(this).parent().find(".sale_price_layer").hide();
		});

		$(".detailDescriptionLayerBtn").click(function(){
			$('div.detailDescriptionLayer').not($(this).siblings('div.detailDescriptionLayer')).hide();
			$(this).siblings('div.detailDescriptionLayer').toggle();
		});
		$(".detailDescriptionLayerCloseBtn").click(function(){
			$(this).closest('div.detailDescriptionLayer').hide();
		});

		// 배송 변경 :: 2016-08-01 lwh
		$("button.btn_shipping_modify").bind("click",function() {
			var cart_seq	= $(this).attr('cart_seq');
			var prepay_info = $(this).attr('prepay_info');
			var nation		= $(this).attr('nation');
			var hop_date	= $(this).attr('hop_date');
			var goods_seq	= $(this).attr('goods_seq');
			var reserve_txt	= $(this).attr('reserve_txt');
			var cart_table	= parseInt($(this).attr('person_seq')) > 0 ? 'person' : '';

			$.ajax({
				'url'	: '/goods/shipping_detail_info',
				'data'	: {'mode':'cart','cart_seq':cart_seq,'prepay_info':prepay_info,'nation':nation,'hop_date':hop_date,'goods_seq':goods_seq,'reserve_txt':reserve_txt,'cart_table':cart_table},
				'type'	: 'get',
				'dataType': 'text',
				'success': function(html) {
					if(html){
						$("div#shipping_detail_lay").html(html);
						//배송방법 안내 및 변경
						openDialog(getAlert('os170'), "shipping_detail_lay", {"width":500,"height":650});
					}else{
						//오류가 발생했습니다. 새로고침 후 다시시도해주세요.
						alert(getAlert('os171'));
						document.location.reload();
					}
				}
			});
		});


		//디자인팀 추가 ------
		/**
		 * 결제정보
		*/
		$("#payment_type > li:first-child > div").addClass("active");
		$("#payment_type > li > div").click(function(){
			if (isPaymentOnlyKakaoPay() === true) {
				// 카카오페이 결제만 가능
				return false;
			}
			$("#payment_type > li > div").removeClass("active");
			$(this).addClass("active");
			change_payment_type($(this).find('input'));
			$("#typereceipt0").attr("checked",true).trigger("click");
		});

		/**
		 * 배송메시지
		*/
		$(".ship_message .click").bind("click", function(){
			if($(this).closest(".ship_message").find(".add_message").css("display")=='none'){
				$(".add_message").hide();
				$(this).closest(".ship_message").find(".add_message").show();
			}else{
				$(".add_message").hide();
				$(this).closest(".ship_message").find(".add_message").hide();
			}
		});
		$(".ship_message").bind("blur", function(){
			$(".add_message").hide();
		});
		$(".add_message li").bind("click", function(){
			var sel_message = $(this).html();
			sel_message = sel_message.replace(/^\<span class\=\"lately desc\"\>[^\<]*\<\/span\>/,'');
			$(this).closest(".ship_message").find(".ship_message_txt").val(sel_message).trigger('change');
			$(".add_message").hide();
		});
		// 배송메세지 카운터
		$(".ship_message_txt").bind("keyup change", function(){
			var obj			= $(this).closest(".ship-lay");
			var message		= obj.find(".ship_message_txt").val();
			var message_cnt	= message.length;
			if(message_cnt <= 300){
				obj.find(".cnt_txt").html(message_cnt);
			}else{
				//배송메세지는 300자 이하까지만 가능합니다.
				alert(getAlert('os151'));
				obj.find(".cnt_txt").html(300);
				obj.find(".ship_message_txt").val(message.substr(0,300));
			}
		});

		// ### 기본 초기 설정 :: START
		set_pay_button();
		if(shipping_policy_count > 1){
			$("tr.shipping_tr").show();
		}else{
			$("tr.shipping_tr").hide();
		}

		if(cart_promotioncode != ""){ getPromotionckloding(cart_promotioncode); }

		if(is_members == true){
			$(".delivery_member").show();
			if($("input[name='order_zipcode[]']").eq(0).val()){
				$(".order_member").show();
				$(".order_input").hide();
			}else{
				$(".order_member").hide();
				$(".order_input").show();
			}
			$(".order_member").show();
			$(".order_input").hide();
			$(".delivery_input").hide();
		}else{
			$(".delivery_member").hide();
			$(".order_member").hide();
			$(".delivery_input").show();
			$(".order_input").show();
			if(get_nation == 'KOREA' ){
				$(".domestic").show();
				$(".international").hide();
			}else{
				$(".domestic").hide();
				$(".international").show();
			}
			set_shipping('input');
		}
		if(is_goods == false){ $(".goods_delivery_info").hide();}
		if(typeof is_address != 'undefined' && is_address){
			address_modify('delivery');
			address_modify('order');
		}
		order_price_calculate();
		// ### 기본 초기 설정 :: END		
		
		// 약관 전체 동의
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
	});	
	
	/*PG 결제 스크립트 함수 (절대 수정하지 마시오)*/

	/*이니시스*/
	function pay() {
		INIStdPay.pay('SendPayForm_id');
	}

	/*LG U+*/
	var LGD_window_type = 'iframe';

	function launchCrossPlatform(CST_PLATFORM){
		lgdwin = openXpay(document.getElementById('LGD_PAYINFO'), CST_PLATFORM, LGD_window_type, null, "", "");
	}

	function getFormObject() {
			return document.getElementById("LGD_PAYINFO");
	}

	function payment_return() {
		var fDoc;

		fDoc = lgdwin.contentWindow || lgdwin.contentDocument;


		if (fDoc.document.getElementById('LGD_RESPCODE').value == "0000") {

				document.getElementById("LGD_PAYKEY").value = fDoc.document.getElementById('LGD_PAYKEY').value;
				document.getElementById("LGD_PAYINFO").target = "_self";
				document.getElementById("LGD_PAYINFO").action = "/lg/receive";
				document.getElementById("LGD_PAYINFO").submit();
		} else {
			alert("LGD_RESPCODE (결과코드) : " + fDoc.document.getElementById('LGD_RESPCODE').value + "\n" + "LGD_RESPMSG (결과메시지): " + fDoc.document.getElementById('LGD_RESPMSG').value);
			closeIframe();
		}
	}

	/*KCP*/
	/****************************************************************/
    /* m_Completepayment  설명                                      */
    /****************************************************************/
    /* 인증완료시 재귀 함수                                         */
    /* 해당 함수명은 절대 변경하면 안됩니다.                        */
    /* 해당 함수의 위치는 payplus.js 보다먼저 선언되어여 합니다.    */
    /* Web 방식의 경우 리턴 값이 form 으로 넘어옴                   */
    /* EXE 방식의 경우 리턴 값이 json 으로 넘어옴                   */
    /****************************************************************/
	function m_Completepayment( FormOrJson, closeEvent )
    {
        var frm = document.order_info;

        /********************************************************************/
        /* FormOrJson은 가맹점 임의 활용 금지                               */
        /* frm 값에 FormOrJson 값이 설정 됨 frm 값으로 활용 하셔야 됩니다.  */
        /* FormOrJson 값을 활용 하시려면 기술지원팀으로 문의바랍니다.       */
        /********************************************************************/
        GetField( frm, FormOrJson );


        if( frm.res_cd.value == "0000" )
        {
		   	//alert("결제 승인 요청 전,\n\n반드시 결제창에서 고객님이 결제 인증 완료 후\n\n리턴 받은 ordr_chk 와 업체 측 주문정보를\n\n다시 한번 검증 후 결제 승인 요청하시기 바랍니다."); //업체 연동 시 필수 확인 사항
            frm.submit();
        }
        else
        {
            alert( "[" + frm.res_cd.value + "] " + frm.res_msg.value );
            parent.location.reload();
        }
    }

    function jsf__pay( form )
    {
		try
		{
            KCP_Pay_Execute( form );
		}
		catch (e)
		{
			console.log(e);
			//parent.location.reload();
		}
    }
	/*PG 결제 스크립트 함수 (절대 수정하지 마시오)*/

