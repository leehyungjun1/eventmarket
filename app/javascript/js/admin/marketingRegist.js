/*
입점사, 카테고리, 상품 선택 javascript
@2020.02.06
*/
$(function(){

	$(".categoryList .btn_minus").on("click",function(){
		gCategorySelect.select_delete('minus',$(this));
	});
	
	//선택삭제
	$(".select_goods_del").on("click",function(){
		gGoodsSelect.select_delete('chk',$(this));
	});

	// 상품선택
	$(".btn_select_goods").on("click",function(){
		
		var params = {
					'goodsNameStrCut':30,
					'select_goods':$(this).attr("data-goodstype"),
					'selector':this,
					'service_h_ad':window.Firstmall.Config.Environment.serviceLimit.H_AD
					};
		gGoodsSelect.open(params);

	});

	// 카테고리 선택
	$(".btn_category_select").on("click",function(){
		if($(this).data("fieldname")) {
			var fieldName = $(this).data("fieldname");
		}else{
			var fieldName = 'issueCategoryCode';
		}

		if($(this).data("listlay")) {
			var listlay = '.'+$(this).data("listlay");
		}else{
			var listlay = '.category_list';
		}
		gCategorySelect.open({'fieldName':fieldName,'listLay':listlay});
	});
	
	// 추가 배송비 텍스트 체크 :: 2017-02-22 lwh
	$("#feed_add_txt").on("keyup", function(){
		ep_addtxt_chk();
	});

	if($("input[name='naver_mileage_yn']").length>0) {
		$("input[name='naver_mileage_yn']").on("click",function(){
			check_naver_mileage_yn('{=naver_mileage.naver_mileage_api_id}');
		});
	
		check_naver_mileage_yn('{=naver_mileage.naver_mileage_api_id}');
	}

	


	$("input[name='navercheckout_culture']").live("click",function(){
		if($(this).val()=='choice'){
			$(".culture_choice").show();
		} else {
			$(".culture_choice").hide();
		}
	});

	$(".info_code").on("click",function(){
		openDialog("치환 코드", "info_code_lay", {"width":"750","show" : "fade","hide" : "fade"});
	});

	$(".commonInfoBtn").on("click",function(){
		openDialog("상품 DB URL 통합 설정", "commonInfo", {"width":"1000","height":"600","show" : "fade","hide" : "fade"});
	});

	$(".shippingGroupInfoBtn").on("click",function(){
		openDialog("네이버 페이 배송비", "shippingGroupInfo", {"width":"1000","height":"600","show" : "fade","hide" : "fade"});
	});	

	$(".shippingTalkbuyGroupInfoBtn").on("click",function(){
		openDialog("톡체크아웃 배송비", "shippingTalkbuyGroupInfo", {"width":"1000","height":"600","show" : "fade","hide" : "fade"});
	});	
	
	if((marketingData.npayUse == 'y' || marketingData.npayUse == 'test') && marketingData.npayVersion != '2.1'){
		$(".npay_ver").on("click",function(){
			if($(this).val() == "2.1"){
			openDialog("네이버페이 연동버전 업그레이드 신청","npay_ver2_lay",{"width":650,"height":670,"show" : "fade","hide" : "fade"});
				$("input[name='navercheckout_ver'][value='1.0']").attr("checked",true);
			}
		});
	}

	$("#naverpay_upgrade").on("click",function(){

		if(!$("input[name='naverpay_mall_id']").val()){
			openDialogAlert("페이가맹점 ID를 입력하세요.");
			return false;
		}
		if(!$("input[name='naverpay_email[]']").eq(0).val() || !$("input[name='naverpay_email[]']").eq(1).val()){
			openDialogAlert("이메일 주소를 입력하세요.");
			return false;
		}
		if(!$("input[name='naverpay_user_phone']").val()){
			openDialogAlert("휴대폰번호를 입력하세요.");
			return false;
		}

		var upgrade_form = $("form[name='naverpay_upgrade']");

		openDialogConfirm("<span class='fx12'>업그레이드 신청부터 완료기간까지 네이버페이 버튼이 노출되지<br />않습니다.(테스트모드)<br />업그레이드 하시겠습니까?</span>",450,200,function(){ upgrade_form.attr("action","../marketing_process/naverpay_upgrade");upgrade_form.submit(); } ,function(){}) ;
	});
	
	if(marketingData.npayUse == "y" && marketingData.npayUse == ''){
		$(".navercheckout_use").on("click",function(){

			if($(this).val() == "n" || $(this).val() == "test"){
				var msg = "추후 사용함으로 설정 시 상품연동 2.0 버전(주문연동 포함)으로만 연동 가능합니다.<br />사용안함(또는 테스트)으로 설정하시겠습니까?";
				openDialogConfirm(msg,550,170,function(){},function(){$("input[name='navercheckout_use'][value='y']").attr("checked",true);});
			}
		});
	}
	
	if(marketingData.npayUse == "y"){
		$('[name="naver_use"]').on('click', function(){
			var v = $(this).val();

			if(v == 'Y') {
				$('.naver-ep-sec-guide').show();
			} else {				
				openDialogConfirm(
					"[미사용]으로 변경 시 네이버쇼핑 3.0 버전만 사용 가능합니다.<br/>변경하시겠습니까?",
					550, 170,
					function(){
						$('.naver-ep-sec-guide').hide();
					},
					function(){
						$('[name="naver_use"]:eq(0)').attr('checked', true);
						$('.naver-ep-sec-guide').show();
					}
				);	
			}
		});
	}
	
	/* 네이버페이 설정-사용여부가 사용/테스트일 경우 네이버 공통 인증 설정의 사용 여부를 자동으로 "사용" 으로 변경 */
	$("input[name='navercheckout_use']").change(function() {
		if( $.inArray($(this).val(), ["y", "test"]) !== -1 ) {
			$("input[name='naver_wcs_use']").removeAttr("checked");
			$("input[name='naver_wcs_use'][value='y']").attr("checked", true);
			$("input[name='naver_wcs_use'][value='y']").focus();
		}
	});

	//페이스북 피드 갱신 kmj
	$('[id="facebookReload"]').on('click', function(){
		var url = window.location.protocol + "//" + window.location.hostname + "/partner/facebook";
		$.ajax({
			type: "get",
			url: url,
			data: "reload=y",
			success: function(result){
				var res = $.parseJSON(result);
				$('[id="facebook_update"]').text(res['facebook_update']);
				$('[id="facebook_file_size"]').text(res['facebook_file_size']);
			}
		});
	});
	
	//구글 피드 갱신 kmj
	$('[id="googleReload"]').on('click', function(){
		var url = window.location.protocol + "//" + window.location.hostname + "/partner/google";
		$.ajax({
			type: "get",
			url: url,
			data: "reload=y",
			success: function(result){
				var res = $.parseJSON(result);
				$('[id="google_update"]').text(res['google_update']);
				$('[id="google_file_size"]').text(res['google_file_size']);
			}
		});
	});
	
	$('[name=navercheckout_use]').on('click', function(){
		if($("[name='navercheckout_use']:checked").val() != "n" && $("#no_npay_shipping").length == 1){
			alert("네이버페이 결제가 가능한 배송그룹이 없습니다. \n배송그룹을 먼저 설정해주세요.");
			$('input:radio[name=navercheckout_use]:input[value=n]').attr("checked", true);
		}
	});

	$(".btnLayClose").on('click',function()
	{	
		var id = $(this).closest(".ui-dialog-content").attr("id");		
		closeDialog(id);		
	});
	
	//White List택 추가(+)
	$(".plusBtn").on("click", function()
	{			
		var newClone = $("#checkoutWhitelist .cloneTr").eq(0).clone();	
		var trObj = $("#checkoutWhitelist > tbody > tr");			
		newClone.find("input[type='text']").val("");
		trObj.parent().append(newClone);	
		newClone.find(".cloneTr").html("");				
	});

	$(".selectBtn").on("click", function()
	{			
		selectType =  $(this).attr("selectType");			
	})


	//회원 할인
	$("input[name='marketing_sale_member']").on("click", function(){
		var _radio = $("input[name='member_sale_type']");
		
		if($(this).attr("checked"))
		{
			_radio.attr("disabled", false);
			_radio.closest("label").removeClass("disabled");

		}else{
			_radio.attr("disabled", true);
			_radio.closest("label").addClass("disabled");
		}
	});

	// 카카오페이 사용여부 설정
	$('[name=talkbuy_use]').on('click', function(){
		if($("[name='talkbuy_use']:checked").val() == "y"){
			// 정상, 일시정지 상태가 아닌 경우에는 사용으로 변경 못함
			if(!($("[name='talkbuyServiceStatus']").val() == "ACTIVE" || $("[name='talkbuyServiceStatus']").val() == "PAUSE")) {
				openDialogAlert("연동 심사가 완료된 일시 정지 상태에서만 사용 설정을 \n'사용'으로 변경 가능합니다.", 500, 160);
				$('input:radio[name=talkbuy_use]:input[value=n]').attr("checked", true);
				return;
			}
			if($("#no_talkbuy_shipping").length == 1) {
				openDialogAlert("톡체크아웃 결제가 가능한 배송그룹이 없습니다. \n배송그룹을 먼저 설정해주세요.", 500, 160);
				$('input:radio[name=talkbuy_use]:input[value=n]').attr("checked", true);
			}
		}
	});
	if($("[name='talkbuyServiceStatus']").val() == "ACTIVE" || $("[name='talkbuyServiceStatus']").val() == "PAUSE") {
		$(".talkbuy_use_show").removeClass("hide");
	} else {
		$(".talkbuy_use_show").removeClass("show");
	}

	// 톡체크아웃 - DB URL 공통 설정 미노출 처리
	$('.tabEvent').find("li > a").on("click", function() {
		showcontent = $(this).closest('ul').find(".current").data('showcontent');
		if(showcontent == 'talkbuy') {
			$(".goods_common_setting").hide();
		} else {
			$(".goods_common_setting").show();
		}
	});
	if(location.hash == '#talkbuy') $(".goods_common_setting").hide();
	
});


var max_cnt		= 0;
var pageline	= 1000;
var now_page	= 0;
var max_page	= 0;
var file_mode	= '';
var mode		= '';

function make_market_file(row_cnt,sel_file_mode,sel_mode){
	max_cnt		= row_cnt;
	now_page	= 0;
	max_page	= Math.ceil(max_cnt/pageline);
	file_mode	= sel_file_mode;
	mode		= sel_mode

	openDialogAlert('페이지를 이탈하거나 브라우저를 종료하지 마세요. <Br/><span id="process_list_cnt" style="font-size:12px;font-weight: bold;"></span> 파일 생성 중',400,120,function(){},{'hideButton' : true});

	loadingStart();
	do_make_market_file();
}


function do_make_market_file(){
	if(max_page == now_page){
		loadingStop();
		openDialogAlert('파일이 생성되었습니다.',400,120,function(){},{'hideButton' : true});
		return true;
	}else{
		now_page++;
	}

	$('#process_list_cnt').html(now_page + ' / ' + max_page);

	make_url	= '/partner/file_write?page=' + now_page +'&filemode='+file_mode+'&mode='+mode+'&rows=' + max_cnt + '&pageline=' + pageline;
	$.ajax({url: make_url,global:false}).always(function(){
		window.setTimeout(function(){do_make_market_file();}, 10000 );
	});

	return true;
}

// Firstmall 에서 호출하는 팝업
function popup_event(){
	openDialog("이벤트 안내", "info_event_lay", {"width":"800","height":"700","show" : "fade","hide" : "fade"});
}


function npay_btn_style(mode){
	var title	= '';
	var w		= 1200;
	var h		= 650;
	if(mode == "pc_goods"){
		title = "네이버 페이 버튼 (PC)";
	}else if (mode == "mobile_goods"){
		title	= "네이버 페이 버튼 (Mobile)";
		h		= 570;
	}
	$("#npay").attr("src","../marketing/npay_btn_style?mode="+mode);
	openDialog(title,"lay_npay_btn_style", {"width":w,"height":h,"show" : "fade","hide" : "fade"});
}

//네이버페이 버튼 노출 설정
function lay_npay_close(mode,npay_btn_text,h){

	$("#npay_"+mode+"_text").html(npay_btn_text);
	$("#npay_"+mode).attr("height",h);
	$("#npay_"+mode+"_cart").attr("height",h);
	// 상세페이지 버튼 갱신
	$("#npay_" + mode).attr("src", "../marketing/npay_btn_style_iframe?mode=" + mode);
	// 장바구니 버튼 갱신
	$("#npay_" + mode + '_cart').attr("src", "../marketing/npay_btn_style_iframe?mode=" + mode + '&type=cart');

	$("#lay_npay_btn_style").dialog("close");
}

function lay_npay_popup_close(){		
	//$("#lay_npay_btn_style").dialog("close");
	closeDialogEvent("#lay_npay_btn_style");
}

// 카카오톡 구매 버튼 스타일 로드
function talkbuy_btn_style(mode){
	var title	= '';
	var w		= 900;
	var h		= 650;
	if(mode == "pc_goods"){
		title = "톡체크아웃 버튼 (PC)";
	}else if (mode == "mobile_goods"){
		title = "톡체크아웃 버튼 (Mobile)";
	}
	$("#npay").attr("src","../marketing/talkbuy_btn_style?mode="+mode);
	openDialog(title,"lay_npay_btn_style", {"width":w,"height":h,"show" : "fade","hide" : "fade"});
}

//톡구매 버튼 노출 설정
function lay_talkbuy_close(mode,btn_text,h){

	$("#talkbuy_"+mode+"_text").html(btn_text);
	$("#talkbuy_"+mode).attr("height",h);
	$("#talkbuy_"+mode+"_cart").attr("height",h);

	$("#talkbuy_" + mode).attr("src", "../marketing/talkbuy_btn_style_iframe?mode=" + mode);
	$("#talkbuy_" + mode + '_cart').attr("src", "../marketing/talkbuy_btn_style_iframe?mode=" + mode + '&type=cart');

	$("#lay_npay_btn_style").dialog("close");
}

function confirm_cancel_facebook()
{
	openDialogConfirm('페이스북에 상품 데이터 전달을 종료하시겠습니까?',400,200,function(){
		actionFrame.location.href = '../marketing_process/cancel_facebook';
	},function(){
	});
}

function confirm_cancel_google()
{
	openDialogConfirm('구글에 상품 데이터 전달을 종료하시겠습니까?',400,200,function(){
		actionFrame.location.href = '../marketing_process/cancel_google';
	},function(){
	});
}

function check_naver_mileage_yn(naver_mileage_api_id){

	$(".naver_mileage").find("input[type='text']").each(function(){
		$(this).css("background-color","#f3f3f3");
		$(this).css("color","#c6c6c6");
		if( !$("input[name='naver_mileage_yn'][value='n']").attr("checked") ){
			$(this).css("background-color","");
			$(this).css("color","#000000");
		}
	});

	if(naver_mileage_api_id){
		// 네이버 마일리지 조회
		$.get('/naver_mileage/get_accum_rate', function(data) {
			var naver_mileage_rate = 0;
			if(data.baseAccumRate){
				$("#naver_mileage_baseAccumRate").html(data.baseAccumRate);
				naver_mileage_rate += data.baseAccumRate;
			}
			if(data.addAccumRate){
				$("#naver_mileage_addAccumRate").html(data.addAccumRate);
				naver_mileage_rate += data.addAccumRate;
			}
			$("#naver_mileage_rate").html(naver_mileage_rate);
		});
	}
}

function check_naver_wcs_yn(){
	$(".naver_wcs").each(function(){
		$(this).attr("readonly",true);
		$(this).css("background-color","#f3f3f3");
		$(this).css("color","#c6c6c6");
		if( $("input[name='naver_wcs_yn']").attr("checked") ){
			$(this).attr("readonly",false);
			$(this).css("background-color","");
			$(this).css("color","#000000");
		}
	});
}

//White List 삭제(-)
function trDel(tg)
{
	var len = $(tg).closest("table").find("tr").length;
	if(len==2) return;
	$(tg).parent().parent().remove();		
}

