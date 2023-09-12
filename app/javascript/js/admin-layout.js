$(function(){
	/* 상단 대쉬보드 스크롤처리 */
	var dashBoardObj = $("#page-title-bar");	
	var LNBObj = $(".LNB");
	var headerObj = $("#layout-header");

	if(dashBoardObj[0]){
		var defaultDashBoardTop = parseInt(dashBoardObj.offset().top);
		var pageButtons	= ".page-buttons-right";

		if($("#page-title-bar .page-buttons-right > *").length == 0) pageButtons = ".page-buttons-left";	

		$(window).on('scroll resize',function(){
			var scrollTop = parseInt($(document).scrollTop());

			if(scrollTop > defaultDashBoardTop){
				headerObj.addClass('flyingMode');
				dashBoardObj.addClass('flyingMode');
				LNBObj.addClass('flyingMode');	
				if(parseInt($(".LNB").css("left")) < 0) dashBoardObj.addClass('close');		
				dashBoardObj.find("span.btn.black").removeClass('black').addClass('cyanblue');
				dashBoardObj.find(".icon-arrow-right").removeClass('icon-arrow-right').addClass('icon-arrow-right_gray');
				$('.page-env-btn').addClass('page-env-btn-flying');			
				if(LNBObj.length==0)dashBoardObj.addClass('v2');				
				$("#page-title-bar.flyingMode " +pageButtons + ", #page-title-bar.flyingMode .page-goods-helper-btn > table").css("padding-right",  $(window).width() - $("#layout-body").width() + 40);
				$(".ico_floating_top").removeClass("off");			
			}else{
				headerObj.removeClass('flyingMode');
				dashBoardObj.removeClass('flyingMode');
				dashBoardObj.removeClass('motion');
				dashBoardObj.removeClass('close');
				LNBObj.removeClass('flyingMode');
				dashBoardObj.find("span.btn.cyanblue").removeClass('cyanblue').addClass('black');
				dashBoardObj.find(".icon-arrow-right_gray").removeClass('icon-arrow-right_gray').addClass('icon-arrow-right');
				$('.page-env-btn').removeClass('page-env-btn-flying');
				$("#page-title-bar " + pageButtons + ", #page-title-bar .page-goods-helper-btn > table").css("padding-right", 0)				
				$(".ico_floating_top").addClass("off")	
			}

			dashBoardObj.trigger('classChange');
		});
	}

	/* 셀렉트박스 스타일 */
	$(".custom-select-box").customSelectBox();
	$(".custom-select-box-multi").customSelectBox({'multi':true});

	/* 이미지체크박스 스타일 */
	$(".imageCheckboxContainer").each(function(){
		var imageCheckboxContainer = this;
		$(".imageCheckboxItem img",this).bind('click',function(event){
			event.preventDefault();
			$(this).closest("label").children('input').click().change();
		});
		$(".imageCheckboxItem input[type='radio']",this).bind('change',function(){
			if($(this).is(":checked")){
				$(imageCheckboxContainer).find(".imageCheckboxItem").removeClass('selected').children().css('opacity',1);
				$(this).closest(".imageCheckboxItem").addClass('selected').children().css('opacity',0.8);
			}
		});

		if($(".imageCheckboxItem input[type='radio'][checked]",this).length){
			$(".imageCheckboxItem input[type='radio'][checked]",this).change();
		}else{
			$(".imageCheckboxItem:eq(0) input[type='radio']",this).attr('checked',true).change();
		}
	});

	/* body 리사이징 */
	$(".body-height-resizing").each(function(){
		var thisOffsetTop = ($(this).offset().top-$(this).parent().offset().top)+15;
		var thisHeight = $(this).outerHeight();
		var parentHeight = $(this).parent().innerHeight();

		if((thisOffsetTop+thisHeight)<parentHeight){
			$(this).css('min-height',parentHeight-thisOffsetTop);
		}
	});

	/* Ajax 로딩미이지 */
	$("#ajaxLoadingLayer").ajaxStart(function() {
		loadingStart(this);
	});
	$("#ajaxLoadingLayer").ajaxStop(function() {
		loadingStop(this);
	});

	/* 가비아 출력 패널 (배너,팝업,공지,업그레이드) */
	$("div.gabia-pannel").each(function(){
		var pannel = this;
		var code	= $(this).attr("code");
		var html	= '';
		var version		= $(this).attr("ver");

		if(!$(this).attr("noAnimation")){
			$(pannel).activity({segments: 8, width: 3.5, space: 1, length: 7, color: '#666', speed: 1.5});
		}

		if(!$(this).attr("getdata") ) {
			getdata = false;
		}else{
			getdata = $(this).attr("getdata");
		}

		$.ajax({
			'url' : '/admin/common/getGabiaPannel',
			'data' : {'code':code,'getdata':getdata,'version':version},
			'global' : false,
			'success' : function(html){
				if(html){
					// 스킨페이지 광고 예외처리 :: 2016-01-27 lwh
					if(code == 'solution_skin_page_popupAD'){
						if(html.indexOf('||') > 1 && !$.cookie("ad_popup")){
							var size	= html.substr(0,html.indexOf('||'));
							var width	= html.substr(0,html.indexOf('x'));
							var height	= html.substr(html.indexOf('x')+1,html.indexOf('||')-html.indexOf('x')-1);
							html = html.replace(size+'||','');
							$("#adDialogLayer").width(width);
							$("#adDialogLayer").height(height);
							if($(window).height() > height)
									$("#adDialogLayer").css('margin-top','-'+(height/2)+'px');
							else	$("#adDialogLayer").css('top','0px');
							$("#adDialogLayer").css('margin-left','-'+(width/2)+'px');
							$(".ui-widget-overlay").height($(document).height()+1810);

							$(".adDialogLayer").show();
							$(pannel).html(html);
						}else{
							$(".adDialogLayer").hide();
						}
					}else{
						$(pannel).show().html(html);
					}
					if(!$(this).attr("noAnimation")){
						$(pannel).activity(false);
					}
				}else{
					$(pannel).hide();
				}
			}
		});
	});

	/* 가비아 출력 패널 (배너,팝업,공지,업그레이드) */
	$("div.gabia-pannel-pay").each(function(){
		var pannel = this;
		var service	= $(this).attr("service");
		var group	= $(this).attr("group");
		var html	= '';
		var pc		= $(this).attr("pc");

		if(!$(this).attr("noAnimation")){
			$(pannel).activity({segments: 8, width: 3.5, space: 1, length: 7, color: '#666', speed: 1.5});
		}

		$.ajax({
			'url' : '/admin/common/getGabiaPannelPay',
			'data' : {'service':service,'group':group,'pc':pc},
			'global' : false,
			'success' : function(html){
				if(html){
					$(pannel).show().html(html);

					if(!$(this).attr("noAnimation")){
						$(pannel).activity(false);
					}
				}else{
					$(pannel).hide();
				}
			}
		});
	});

	//전자 결제 설정 view 호출
	$("div.pgInfoContents").each(function(){
		var pannel = this;
		var code	= $(this).attr("code");
		var html	= '';

		if(!$(this).attr("noAnimation")){
			$(pannel).activity({segments: 8, width: 3.5, space: 1, length: 7, color: '#666', speed: 1.5});
		}

		$.ajax({
			'url' : '/admin/setting/pgInfo',
			'data' : {'pgCompany':code},
			'global' : false,
			'success' : function(html){
				if(html){
					$(pannel).show().html(html);
					if(!$(this).attr("noAnimation")){
						$(pannel).activity(false);
					}
				}else{
					$(pannel).hide();
				}
			}
		});
	});	

	/* 혜택설정바로가기 */
	$(".gnb-benifit").bind("click",function(){
		$(".benifit-popup").toggleClass('current');
		if	( $(".benifit-popup").hasClass('current') ){
			if($(".benifit-popup").html()==""){
				$(".benifit-popup").load("/admin/common/benifit");
			}
			$(".benifit-popup").slideDown('fast');
			$(".gnb-benifit").addClass('gnb-benifit-on');
			$(".gnb-benifit").removeClass('gnb-benifit-off');
		}else{
			$(".benifit-popup").slideUp('fast');
			$(".gnb-benifit").addClass('gnb-benifit-off');
			$(".gnb-benifit").removeClass('gnb-benifit-on');
		}
	});

	$("button.benifit-popup-close-btn").bind("click",function(){
		$(".benifit-popup").removeClass('current');
		$(".benifit-popup").slideUp('fase');
	});

	/* 상단 메뉴스타일 변경 */
	$(".mitem-menu-icon-view").bind('click',function(){
		if($("#layout-header").hasClass("icon-view")){
			$("#layout-header").removeClass("icon-view");
			$("#layout-background").removeClass("icon-view");
		}else{
			$("#layout-header").addClass("icon-view");
			$("#layout-background").addClass("icon-view");
		}

		$(window).scroll().resize();

		$.ajax({
			'global' : false,
			'url' : '/admin/common/setManagerIconView?val=' + ($("#layout-header").hasClass("icon-view")?'y':'n')
		});

		return false;
	});

	/* 상단 전체메뉴 보기 */
	$(".mitem-menu-all").click(function(){
		$(".mitem-menu-all").toggleClass('current');
		if	( $(".mitem-menu-all").hasClass('current') )
			$(".header-menu-all").slideDown('fast');
		else
			$(".header-menu-all").slideUp('fast');

	});

	$(".menu-all-close-btn").click(function(){
		$(".mitem-menu-all").removeClass('current');
		$(".header-menu-all").slideUp('fase');
	});

	$(".menu-item").each(function(){
		if	( $(this).attr('lines') > 1)	var currentName	= 'current2';
		else								var currentName	= 'current';
		$(this)
		.bind('mouseenter', function(){
			$(".title-text-area").addClass(currentName)
			$(".title-text-area").html($(this).text().replace(/\[\:BR\:\]/g, '<br/>'))
		})
		.bind('mouseleave', function(){
			$(".title-text-area").removeClass(currentName)
			$(".title-text-area").html($('.title-text-default').text())
		});
	});

	/* 상단 검색폼 */
	$("#layout-header select.hsb-kind").each(function(){
		switch($("option:selected",this).text())
		{
			case '주문': var keywordTitle = "주문자,수령자,입금자,아이디 등"; break;
			case '출고': var keywordTitle = "아이디,주문자,수령자,티켓번호 등"; break;
			case '회원': var keywordTitle = "이름,아이디,이메일,연락처,주소"; break;
			case '상품': var keywordTitle = "상품명,상품코드"; break;
			case '티켓': var keywordTitle = "티켓상품명,티켓상품코드,고유값,태그"; break;
			case '쿠폰': var keywordTitle = "티켓상품명,티켓상품코드,고유값,태그"; break;
			default : var keywordTitle = "검색";
		}
		$("#layout-header input[name='header_search_keyword']").attr("title",keywordTitle);
	}).bind('keyup change',function(){
		var keywordObj = $("#layout-header input[name='header_search_keyword']");
		switch($("option:selected",this).text())
		{
			case '주문':
				var keywordTitle	= "주문자,수령자,입금자,아이디 등";
				var action			= "/admin/order/catalog";
				break;
			case '출고':
				var keywordTitle	= "아이디,주문자,수령자 등";
				var action			= "/admin/export/catalog";
				break;
			case '회원':
				var keywordTitle	= "이름,아이디,이메일,연락처,주소";
				var action			= "/admin/member/catalog";
				break;
			case '상품':
				var keywordTitle	= "상품명,상품코드";
				var action			= "/admin/goods/catalog";
				break;
			case '티켓':
				var keywordTitle	= "티켓상품명,티켓상품코드,고유값,태그";
				var action			= "/admin/goods/social_catalog";
				break;
			case '쿠폰':
				var keywordTitle	= "티켓상품명,티켓상품코드,고유값,태그";
				var action			= "/admin/goods/social_catalog";
				break;
			default : var keywordTitle = "검색";
		}

		$("#headForm").attr("action",action);

		if(keywordObj.val()==keywordObj.attr('title')){
			keywordObj.attr("title",keywordTitle);
			keywordObj.val('').focusout();
		}

		keywordObj.attr("title",keywordTitle);

	}).change();

	$("#headForm").submit(function(){
		var submit = true;
		var chk_type = $("a.header_link_keyword").attr('s_type');

		if(chk_type=='' || chk_type=='order'){
			// 바코드 검색 체크
			var keyword = $("#layout-header input[name='header_search_keyword']").val();
			if(keyword.length==21 && keyword.substring(0,1)=='A' && keyword.substring(keyword.length-1,keyword.length)=='A'){
				var order_seq = keyword.substring(1,20);
				$.ajax({
					'url' : 'order_seq_chk',
					'data' : {'order_seq':keyword},
					'async' : false,
					'success' : function(res){
						if(res=='1'){
							window.open('/admin/order/view?no='+order_seq+'&directExport=1');
							$("#layout-header input[name='header_search_keyword']").val('');
							submit = false;
						}
					}
				});
			}
		}

		return submit;
	});

	// 상단 검색어 레이어 박스 : start
	$("#header_search_keyword").keyup(function () {
		if ($(this).val()) {
			$('.header_txt_keyword').text($(this).val());
			headerSearchLayerOpen();
		}else{
			$('.header_searchLayer').hide();
		}
	});

	$("#header_search_keyword").focus(function () {
		if ($(this).val() && $(this).val()!=$(this).attr('title')) {
			$('.header_txt_keyword').text($(this).val());
			headerSearchLayerOpen();
		}
	});

	$(".searchBtn").click(function () {
		var sType = $('#header_search_type').val();
		var action = "/admin/order/catalog";
		if (sType=='export') action = "/admin/export/catalog";
		if (sType=='member') action = "/admin/member/catalog";
		if (sType=='goods') action = "/admin/goods/catalog";
		if (sType=='coupon') action = "/admin/goods/social_catalog";
		$("#headForm").attr("action",action);
		$('.header_searchLayer').hide();
		setHeaderSearchTxt(sType);
		$("#headForm").submit();
	});
	
	$("#header_search_keyword").keydown(function(key) {
		if (key.keyCode == 13) {
			$(".searchBtn").trigger('click');
		}
	});

	var offset = $("#header_search_keyword").offset();
	$('.header_search_type_text').css({
		'position' : 'absolute',
		'z-index' : 999,
		'left' : '1px',
		'top' : '1px',
		'width':$("#header_search_keyword").width()-1,
		'height':$("#header_search_keyword").height()+5
	});

	$(".header_search_type_text").click(function () {
		$(".header_search_type_text").hide();
		$("#header_search_keyword").focus();
	});

	$(".header_searchLayer ul li").hover(function() {
		$(".header_searchLayer ul li").removeClass('hoverli');
		$(this).addClass('hoverli');
	});

	$("#header_search_keyword").keydown(function (e) {
		var searchbox = $(this);

		switch (e.keyCode) {
			case 40:
				if($('.headerSearchUl').find('li.hoverli').length == 0){
					$('.headerSearchUl').find('li:first-child').addClass('hoverli');
				}else{
					if($('.headerSearchUl').find('li:last-child').hasClass("hoverli") ){
						$('.headerSearchUl').find('li::last-child.hoverli').removeClass('hoverli');
						$('.headerSearchUl').find('li:first-child').addClass('hoverli');
					}else{
						$('.headerSearchUl').find('li:not(:last-child).hoverli').removeClass('hoverli').next().addClass('hoverli');
					}
				}
				break;
			case 38:
				if($('.headerSearchUl').find('li.hoverli').length == 0){
					$('.headerSearchUl').find('li:last-child').addClass('hoverli');
				}else{
					if($('.headerSearchUl').find('li:first-child').hasClass("hoverli")){
						$('.headerSearchUl').find('li::first-child.hoverli').removeClass('hoverli');
						$('.headerSearchUl').find('li:last-child').addClass('hoverli');
					}else{
						 $('.headerSearchUl').find('li:not(:first-child).hoverli').removeClass('hoverli').prev().addClass('hoverli');
					}
				}
				break;
			case 13 :
				var index=0;
				 $('.headerSearchUl').find('li').each(function(){
					if($(this).hasClass("hoverli")){
						index=$(this).index();
					}
				});

				$('.headerSearchUl').find('li>a').eq(index).click();
				//$('.header_searchLayer').hide();
				$("#header_search_keyword").blur();
				e.keyCode = null;
				return false;
				break;
		}
		
	});
	// 상단 검색어 레이어 박스 : end

	/* QR코드 안내 */
	$(".qrcodeGuideBtn").bind('click',function(){
		if($(this).attr('target')=='parent'){
			parent.openDialog("QR 코드","qrcodeGuideLayer",{"width":950,"height":820});
			var doc = parent.document;
		}else{
			openDialog("QR 코드","qrcodeGuideLayer",{"width":950,"height":820});
			var doc = document;
		}

		$("#qrcodeGuideLayer",doc).html('');
		$.ajax({
			'url' : '/admin/common/qrcode_guide?key=' + $(this).attr('key') + '&value=' + $(this).attr('value'),
			'success' : function(result){
				$("#qrcodeGuideLayer",doc).html(result);
			}
		});
		return false;
	});	

	//전체보기 메뉴
	$(".totalMenu").click(function(){	
		$("#totalMenuDialog").load('/admin/common/total_menu');
		$("#totalMenuDialog").show()
		$("html").css('overflow-y', 'hidden');		
	});		

	//GNB 영역이 없을때(로그인, 팝업, crm) 스크립트 미실행
	if($(".header-gnb-container").length == 0) return;					

	// 매뉴얼 버튼 링크
	$("#page-title-bar").each(function(){
		if(gl_admin_menual_hidden) return;
		$(".page-global-btn" + gl_goods_quick_topmenu).appendTo($(this)).show().children("a");

		var url = 'https://gmanual.firstmall.kr/html/manual.php?url=' + gl_admin_menual_url;

		if (category_use) {
			url = 'https://gmanual.firstmall.kr/manual/view?category='+ cate_code;
		} 

		$(".page-manual-btn" + gl_goods_quick_topmenu).appendTo($(this)).show().children("a").attr('href', url);


	});

	// 매뉴얼 설정창 띄우기
	$("#hs-global, #page-global-btn").bind("click", function(){
		env = $(this).attr('env');
		if	(env != 'all'){
			title = ' 관리환경 이동';
			$("#global-setting .global-setting-layer").fadeIn("slow");
			$("#global-setting .global-setting-bg").fadeIn();
			$("#global-setting .global-setting-layer a.btn_link").bind("click", function(){
				$(this).css({"border":"1px solid #b9b9b9",  "color":"#b9b9b9"});
			});
			$('#global-setting #global-title').html(title);
		}
	});
	$(".global-setting-bg, .global-setting-layer .close").bind("click", function(){
		$("#global-setting .global-setting-layer").fadeOut("slow");
		$("#global-setting .global-setting-bg").fadeOut();
	});

	$("#layout-header .header-gnb-container ul.header-gnb li.mitem-td").each(function(){
		$(this)
		.bind('mouseenter',function(){
			$("div.submenu",this).stop(true,true).show();
		})
		.bind('mouseleave',function(){
			$("div.submenu",this).stop(true,true).hide();
		});
	});

	$(".manager_alert_view_btn").bind('click',function(){
		$("#manager_alert_dialog").load('/admin/common/manager_alert_history');
		openDialog("중요행위발생을 알려 드립니다!", "manager_alert_dialog", {"width":"900", "height":"770"});
	});	

	// 상단 메모, 중요알림,myfirstmall link,관리자정보 메뉴 활성 비활성
	var hsnbClass = ["hsnb-memo", "hsnb-notice", "hsnb-link", "hsnb-admin"];
	for(var hsnbnum=0; hsnbnum<hsnbClass.length; hsnbnum++){
		$("#layout-header .header-snb ."+hsnbClass[hsnbnum]+" .openBtn").eq(0).click(function(){
			if( $(this).hasClass('opened') ){
				$("#layout-header .header-snb .item .openBtn, #layout-header .header-snb .item .hsnbm-menu").removeClass('opened');
			}else{
				$("#layout-header .header-snb .item .openBtn, #layout-header .header-snb .item .hsnbm-menu").removeClass('opened');
				$(this).addClass('opened');
				$(this).next().addClass('opened');
			}
		});
	}	

	// 구신 스위치버튼 이벤트
	$(document).on('click',".ver_change_btn", function(){
		skinSwipeToggleEvent(this);			
	});
	$(".ver_change_btn").show();
	$(".ver_change_btn").css("right", $(".header-snb").width() + 10)
	
	$(".page-buttons-left").show();	

	$(".require_info_btn").closest(".page-buttons-left").css("float", "left")
	$(".require_info_btn").css("position", "absolute");	
	
	if($(".shortcut_wrap").length > 0) {
		$(".shortcutBtn").on("mouseover", function(){$(this).find(".shortcut_wrap ").show()});		
		$(".shortcutBtn").on("mouseout", function(){$(this).find(".shortcut_wrap ").hide()});
	}

	$(window).on("resize", function(){			
		resize()
	})
	
	resize();

	$(".LNB").each(function(){

		//LNB 활성 이벤트 
		$(".submenu > ul > li > div").click(function(){
			var obj = $(this).closest("li");				
			$(this).parents(".submenu").siblings().children().children().removeClass("current") 
			obj.addClass("current");		
			$(this).parents(".submenu").siblings().find(".sub").css("height", "0")
			$(this).next(".sub").css("height", $(this).next(".sub").find("li").length * 33);
		});

		//LNB 오픈 시 세팅된 서브메뉴 height  
		$(".submenu > ul > li.current .sub").css("height", $(".submenu > ul > li.current .sub").find("li").length * 33 );
		
		//LNB 열고 닫기 이벤트 			
		$("#lnbCloseBtn").on("click", function(){	
							
			if(parseInt($(".LNB").css("left")) < 0){	
				//LNB 열림				
				$(".LNB, .total_menu").removeClass("close");						
				if($(window).width() > 1450) $(".contentsWarp, #page-title-bar.flyingMode").removeClass("close");					
			}else{	
				//LNB 닫힘				
				$(".LNB, .contentsWarp, .total_menu, #page-title-bar.flyingMode").addClass("close");											
			}
			
			$("#page-title-bar.flyingMode").addClass("motion");	
		})
	})

	
	// top버튼 이벤트	
	$(".ico_floating_top").on("click", function() {
		$("html, body").animate({ scrollTop: 0 }, "fase");
		return false;
	});
});


//화면 리사이징 시 컨테츠 영역 사이즈 조절 
function resize(){

	/* 전제보기 상세 화면 */	
	$("#totalMenuDialog").each(function(){
		$("#totalMenuDialog .pannel").css("width", $(window).width() - 190)
	})	
	
	var folder = $(location).attr('pathname').split("/")[2];
	if(folder=="setting") requirePostion();

	if($(".LNB").length == 0) return

	// 화면크기 또는 LNB 노출 설정에 따라
	if($(window).width() < 1450 || gl_lnb_close_yn == 'y'){
		//LNB 닫힘				
		$(".contentsWarp, #page-title-bar.flyingMode").addClass("close");								
		if($(".LNB").offset().left == 0) $(".LNB, .total_menu").addClass("close");							
	}else{	
		//LNB 열림			
		$(".contentsWarp, #page-title-bar.flyingMode").removeClass("close");			
		if($(".LNB").offset().left < 0) $(".LNB, .total_menu").removeClass("close");							
	}
		
	$("#page-title-bar.flyingMode").addClass("motion");	
	$(".LNB .submenu-wrapper").css("height",($(window).height()-108))
	
	if($(".LNB .submenu-wrapper").hasScrollBar()){	
		$('.LNB, .LNB .submenu > ul > li > div, .LNB .submenu .sub a').off('scroll touchmove mousewheel');
	}else{
		LNBStopScroll()		
	}		
}

//구신 어드민 스킨 토글
function skinSwipeToggleEvent(el) {		
	var _obj 		= $(el);
	var skinType 	= _obj.attr("data-admin-skin-type");

	if(skinType == 'new') {
		skinType = 'org';
	} else {
		skinType = 'new';
	}

	$.ajax({
		'url' : '../common/setSkinSwipeToggle',
		'data' : {'skinType': skinType},
		'type': 'POST',
		'datatype': 'json',
		'success' : function(res){
			_obj.attr("data-admin-skin-type", skinType);
			location.reload();
		}
	});

}

//LNB 스클로 막기
function LNBStopScroll(){
	$('.LNB, .LNB .submenu > ul > li > div, .LNB .submenu .sub a').on('scroll touchmove mousewheel', function(event) {
		event.preventDefault();
		event.stopPropagation();
		return false;
	});			
}

//메뉴얼 버튼 위치
function requirePostion()
{		
	var len = $(".page-title > h2").length;	
	var obj;

	len>1 ? obj = $(".page-title > h2:last-child") : obj = $(".page-title > h2");

	if( obj.length > 0){
		$(".require_info_btn").css("left", obj.position().left+obj.width()+50);			
		var select = $(".page-title > select");			
		if(select.width() > 0 && select.css("display")!="none")	$(".require_info_btn").css("left", select.position().left + select.width()+15);				
	}
}


function setHeaderSearchTxt(sType) {
	var search_type_array = new Array();
	search_type_array['order'] = "주문검색";
	search_type_array['export'] = "출고검색";
	search_type_array['member'] = "회원검색";
	search_type_array['goods'] = "실물상품";
	search_type_array['coupon'] = "티켓상품";
	var search_keyword = $("#header_search_keyword").val();
	search_keyword = search_keyword.replace(/(<([^>]+)>)/gi, "");
	$('.header_search_type_text').html(search_type_array[sType]+ " : " + search_keyword).show();
}

function headerSearchLayerOpen() {
	var offset = $("#header_search_keyword").offset();
	if( offset) {
		$('.header_searchLayer').css({
			'position' : 'absolute',
			'z-index' : 999,
			'left' : -1,
			'top' : '100%',
			//'width':$("#header_search_keyword").width()+32
			'width':$("#headForm").width()-5
		}).show();
	}
}

/* 주문상세정보 열기,닫기 처리 */
function toggleOrderDetailBody(btn){
	$(btn).toggleClass("opened");
	$(btn).parents(".order-list-summary-row").find(".order-detail-table").toggleClass("summary-mode");
}

// 우편번호 다이얼로그 박스
function openDialogZipcode(zipcodeFlag,idx,ziptype){
	if(! $(this).is("#"+zipcodeFlag+"Id") ){
		$("body").append("<div id='"+zipcodeFlag+"Id'></div>");

		var url = '/admin/popup/zipcode';
		var params = {'zipcodeFlag':zipcodeFlag,'keyword':'','zipcode_type':ziptype};

		if(idx) params.idx = idx;

		$.get(url,params, function(data) {
			$("#"+zipcodeFlag+"Id").html(data);
		});
		openDialog("우편번호 검색 <span class='desc'>지역명으로 우편번호를 검색합니다.</span>",zipcodeFlag+"Id", {"width":750,"height":600});
		setDefaultText();
	}
}

function set_shipping_otp(datas){
	var shipping_set_seq = datas['shipping_set_seq'];
	var shipping_group_seq = datas['shipping_group_seq'];
	var shipping_set_type = datas['shipping_set_type'];
	
	$.ajax({
		'url' : '../setting/shipping_otp_insert',
		'data' : {'shipping_set_seq': shipping_set_seq, 'shipping_group_seq': shipping_group_seq, 'shipping_set_type': shipping_set_type},
		'dataType' : 'json', //'json'
		'success' : function(res){
			if(res == 'ERROR'){
				alert('옵션 등록 실패');
				return false;
			} else {
				console.log(res);
			}
		}
	});
}

function shipping_zone_list(id, shipping_cost_seq, total, perpage, keyword){
	var html = '';
	var offset = 20;
	
	$.ajax({
		'url' : '../setting/shipping_zone_list',
		'data' : {'shipping_cost_seq': shipping_cost_seq, 'total': total, 'perpage': perpage, 'offset': offset, 'type': 'shipping_zone_list', 'keyword': keyword},
		'dataType' : 'json', //'json'
		'success' : function(res){
			if(res.list.length > 0){
				$.each(res.list, function( index, value ) {
					var num = ((((perpage-1) * offset) + index) + 1);
					html += '<tr id="zone_'+value.area_detail_seq+'">';
					html += '<td><label class="resp_checkbox"><input type="checkbox" class="chk" value="'+value.area_detail_seq+'"></label></td>';
					html += '<td>'+ num + '</td>';
					html += '<td>'+ value.area_detail_address_txt + '</td>';
					html += '</tr>';
				});
			} else {
				html = '<tr><td colspan="4">지역이 없습니다.</td></tr>';
				res.paging = '';
			}
			
			if(id == ''){
				$("#select_address_tb tbody").html(html);
				$(".paging_navigation").html(res.paging);
			} else {
				$("#"+id+" #select_address_tb tbody").html(html);
				$("#"+id+" .paging_navigation").html(res.paging);
			}
			
			
			if(total <= 0){
				$("#price_"+get_p_type).find(".zone_idx_"+get_idx+" .add_zone").attr("data-total", res.total);
				$("#price_"+get_p_type).find(".zone_idx_"+get_idx+" .zone_cnt").text("("+res.total+")");
				$("#price_"+get_p_type).find(".zone_idx_"+get_idx+" .issue").val(res.total);
				
				if(res.total <= 0){
					$("#price_"+get_p_type).find(".zone_idx_"+get_idx+" .std_btn_area").show();
				} else {
					$("#price_"+get_p_type).find(".zone_idx_"+get_idx+" .std_btn_area").hide();
				}
			}
		}
	});
}

// 배송비 지역 다이얼로그 박스 :: 2016-05-30 lwh
function openDialogzone(nation,p_type,idx,datas){
	var id = nation+"_"+idx;
	var title = '대한민국'
		
	if(! $(this).is("#"+id) ){
		$("body").append("<div id='"+id+"'></div>");

		var url = '../popup/zipcode_zone';
		var params = {'keyword':'','nation':nation,'p_type':p_type,'idx':idx};

		if(nation != 'korea'){
			$.get(url, params, function(data) {
				$("#"+id+"").html(data);
			});

			title = '해외 국가';
		} else {
			$.get(url, params, function(data) {
				$("#"+id+"").html(data);
				
				//등록 리스트 가져오기
				if(datas['shipping_cost_seq'] > 0){
					var shipping_cost_seq = datas['shipping_cost_seq'];
					var total = datas['total'];
					
					shipping_zone_list(id, shipping_cost_seq, total, 1);
					$("#"+id+" input[name='shipping_cost_seq']").val(shipping_cost_seq);
					$("#"+id+" input[name='option_seqs']").val(datas.option_seqs);
					$("#"+id+" input[name='total_count']").val(datas.total);
				}
			});
		}
		
		openDialog(title, id, {"width":900,"height":750,"close":function(){$("#"+id).remove();}});
		setDefaultText();
	}
}

/* 파일첨부 Input박스 버튼 변경 */
function changeFileStyle(){
	$("input[type='file']").each(function(){

		var oriFilebox = $(this);

		//input 박스 버튼 변경 필요없는 항목에 대해 예외 처리
		const exception_array = ['goodsIconImg'];
		if(exception_array.includes(oriFilebox.attr('id'))) return;

		if(oriFilebox.attr('mode') == 'new') {
			var newFilebox = $('<input type="text" value="" size="'+this.size+'" class="'+oriFilebox.attr('iclass')+'" readonly style="cursor:default;" />');
			var newBtn = $('<label class="resp_btn v2 mr5">파일 선택</label>');
	
			oriFilebox.css({
				'width'	: '82px',
				'height' : '20px',
				'cursor' : 'pointer',
				'opacity' : '0'
			});
	
			oriFilebox.before(newBtn).after(newFilebox);
			newBtn.append(oriFilebox);
	
			oriFilebox.bind("change",function(){
				newFilebox.val(oriFilebox.val().replace(/C:\\fakepath\\/i, ''));
			});
		} else {
			var newFilebox = $('<input type="text" value="" size="'+this.size+'" class="'+this.className+' line uploadifyViewBox" readonly style="cursor:default" />');
			var newBtn = $('<label class="resp_btn v2 mr5">파일 선택</label>');
	
			oriFilebox.css({
				'width'	: '82px',
				'height' : '20px',
				'cursor' : 'pointer',
				'opacity' : '0'
			});
	
			oriFilebox.after(newBtn).after(newFilebox);
			newBtn.append(oriFilebox);
	
			oriFilebox.bind("change",function(){
				newFilebox.val(oriFilebox.val());
			});
		}
	});
}
/* 파일첨부 Input박스 버튼 언셋 */
function changeFileStyleUnset(wrap){
	$("input[type='file']",wrap).each(function(){
		var oriFilebox = $(this);

		if($(this).hasClass("uploadify")) {
			oriFilebox.unwrap(".file-search-btn");
			oriFilebox.prev(".uploadifyViewBox").remove();
			oriFilebox.unbind("change");

			oriFilebox.removeClass("uploadify");
		}
	});
}

/* copyContent 클립보드 복사 */
function copyContent(str)
{
	browser = navigator.userAgent.toLowerCase();
	if(window.clipboardData && (document.selection || window.getSelection)) { // IE
		bResult = window.clipboardData.setData("Text",str);
	}else if(browser.indexOf('chrome') > -1){
		bResult = copyTextToClipboard(str);
	}else{
		alert("해당 브라우저는 클립보드를 사용할 수 없습니다.");
		return;
	}

	if(bResult){
		alert('클립보드에 저장되었습니다.');
	}
};

function copyTextToClipboard(text) {
	var copyFrom = $('<textarea/>');
	copyFrom.text(text);
	$('body').append(copyFrom);
	copyFrom.select();
	document.execCommand('copy');
	copyFrom.remove();

	return 'OK';
}

/* 파일업로드버튼(Uploadify) 적용 */
function setUploadifyButton(uplodifyButtonId, setting){
	//한글도메인체크@2013-03-12
	var fdomain = document.domain;
	var kordomainck = false;
	for(i=0; i<fdomain.length; i++){
	 if (((fdomain.charCodeAt(i) > 0x3130 && fdomain.charCodeAt(i) < 0x318F) || (fdomain.charCodeAt(i) >= 0xAC00 && fdomain.charCodeAt(i) <= 0xD7A3)))
	{
		kordomainck = true;
		break;
	}
	}
	if( !kordomainck ){
	krdomain = '';
	}

	var defaultSetting = {
		'script'			: krdomain+'/admin/webftp/upload_file',
	    'uploader'			: '/app/javascript/plugin/jquploadify/uploadify.swf',
	    'buttonImg'			: '/app/javascript/plugin/jquploadify/uploadify-search.gif',
	    'cancelImg'			: '/app/javascript/plugin/jquploadify/uploadify-cancel.png',
	    'fileTypeExts'		: '*.jpg;*.gif;*.png;*.jpeg',
	    'fileTypeDesc'		: 'Image Files (.JPG, .GIF, .PNG)',
	    'removeCompleted'	: true,
		'width'				: 64,
		'height'			: 20,
	    'folder'			: '/data/tmp',
	    'auto'				: true,
	    'multi'				: false,
	    'scriptData'		: {'randomFilename':1},
	    'completeMsg'		: '적용 가능',
		'onCheck'     : function(event,data,key) {
			$("#"+uplodifyButtonId+key).find(".percentage").html("<font color='red'> - 파일명 중복</font>");
	    },
	    'onComplete'		: function (event, ID, fileObj, response, data) {
	    	var result = eval(response)[0];

			if(result.status!=1){
				openDialogAlert(result.msg,400,150);
				$("#"+uplodifyButtonId+ID).find(".percentage").html("<font color='red'> - "+result.desc+"</font>");
				return false;
			}else{
				/* mini_webftp 연동을 위한 세팅 */
				//if(typeof(useWebftpFormItem)!='undefined')
				{
					//if(useWebftpFormItem)
					{
						var webftpFormItemObj = $("#"+uplodifyButtonId+ID).closest(".webftpFormItem");
						webftpFormItemObj.find(".webftpFormItemInput").val(result.filePath);
						webftpFormItemObj.find(".webftpFormItemInputOriName").val(result.fileInfo.client_name);
						webftpFormItemObj.find(".webftpFormItemPreview").attr('src',krdomain+'/'+result.filePath).show()
							.attr("onclick","window.open('"+krdomain+'/'+result.filePath+"')").css('cursor','pointer');
						if(webftpFormItemObj.find(".webftpFormItemInput").length){
							webftpFormItemObj.find(".webftpFormItemInput").trigger('change');
						}
						if(webftpFormItemObj.find(".webftpFormItemInput").closest('form').length){
							webftpFormItemObj.find(".webftpFormItemInput").closest('form').trigger('change');
						}
						if(webftpFormItemObj.find(".webftpFormItemPreviewSize").length){
							webftpFormItemObj.find(".webftpFormItemPreviewSize").html(result.fileInfo.image_width + " x " + result.fileInfo.image_height);
						}
					}
				}
			}
		},
		'onError'			: function (event,ID,fileObj,errorObj) {
			alert(errorObj.type + ' Error: ' + errorObj.info);
		}
	};

	if(setting){
		for(var i in setting){
			if(i=='scriptData'){
				for(var j in setting[i]){
					defaultSetting[i][j] = setting[i][j];
				}
			}else{
				defaultSetting[i] = setting[i];
			}
		}
	}

	$("#"+uplodifyButtonId).uploadify(defaultSetting);
}

/*	Select박스 자동완성
 * 	$(this).combobox();
 * */
(function( $ ) {
	$.widget( "ui.combobox", {
		_create: function() {
			var input,
				that = this,
				select = this.element.hide(),
				selected = select.children( ":selected" ),
				value = selected.val() ? selected.text() : "",
				wrapper = this.wrapper = $( "<span>" )
					.addClass( "ui-combobox" )
					.insertAfter( select );

			function removeIfInvalid(element) {
				var value = $( element ).val(),
					matcher = new RegExp( "^" + $.ui.autocomplete.escapeRegex( value ) + "$", "i" ),
					valid = false;
				select.children( "option" ).each(function() {
					if ( $( this ).text().match( matcher ) ) {
						this.selected = valid = true;
						return false;
					}
				});
				if ( !valid ) {
					// remove invalid value, as it didn't match anything
					$( element )
						.val( "" );
					select.val( "" );
					input.data( "autocomplete" ).term = "";
					return false;
				}
			}

			input = $( "<input>" )
				.appendTo( wrapper )
				.val( value )
				.attr( "title", "" )
				.addClass( "ui-state-default ui-combobox-input" )
				.autocomplete({
					delay: 0,
					minLength: 0,
					source: function( request, response ) {
						var matcher = new RegExp( $.ui.autocomplete.escapeRegex(request.term), "i" );
						response( select.children( "option" ).map(function() {
							var text = $( this ).text();
							if ( this.value && ( !request.term || matcher.test(text) ) )
								return {
									label: text.replace(
										new RegExp(
											"(?![^&;]+;)(?!<[^<>]*)(" +
											$.ui.autocomplete.escapeRegex(request.term) +
											")(?![^<>]*>)(?![^&;]+;)", "gi"
										), "<strong>$1</strong>" ),
									value: text,
									option: this
								};
						}) );
					},
					select: function( event, ui ) {
						ui.item.option.selected = true;
						that._trigger( "selected", event, {
							item: ui.item.option
						});
						select.change();
					},
					change: function( event, ui ) {
						if ( !ui.item )
							return removeIfInvalid( this );
					}
				})
				.addClass( "ui-widget ui-widget-content ui-corner-left" );

			input.data( "autocomplete" )._renderItem = function( ul, item ) {
				return $( "<li>" )
					.data( "item.autocomplete", item )
					.append( "<a>" + item.label + "</a>" )
					.appendTo( ul );
			};

			$( "<a>" )
				.attr( "tabIndex", -1 )
				.attr( "title", "Show All Items" )
				.appendTo( wrapper )
				.button({
					icons: {
						primary: "ui-icon-triangle-1-s"
					},
					text: false
				})
				.removeClass( "ui-corner-all" )
				.addClass( "ui-corner-right ui-combobox-toggle" )
				.click(function() {
					// close if already visible
					if ( input.autocomplete( "widget" ).is( ":visible" ) ) {
						input.autocomplete( "close" );
						removeIfInvalid( input );
						return;
					}

					// work around a bug (likely same cause as #5265)
					$( this ).blur();

					// pass empty string as value to search for, displaying all results
					input.autocomplete( "search", "" );
					input.focus();
				});

		},

		destroy: function() {
			this.wrapper.remove();
			this.element.show();
			$.Widget.prototype.destroy.call( this );
		}
	});
})( jQuery );

/* 쿼리로 엑셀로 다운로드하기 :: 2014-10-31 lwh */
function DirectExcelDownload(title, excel_type, queryString){
	// EX : DirectExcelDownload('유입경로','visitor_referer_table', '{_SERVER.QUERY_STRING}')
	var randKey = Math.floor(Math.random()*1000000);

	var html = "";
	html += "<form id='DirectExcelDownloadForm"+randKey+"' action='../common/DirectExcelDownload' method='post' class='hide'>";
	html += "<textarea name='title' id='DirectExcelDownloadTitle"+randKey+"'></textarea>";
	html += "<input type='text' name='excel_type' id='DirectExcelDownloadType"+randKey+"' />";
	html += "<input type='text' name='param' id='DirectExcelDownloadParam"+randKey+"' />";
	html += "</form>";

	$(html).appendTo("body");

	$("#DirectExcelDownloadTitle"+randKey).val(title);
	$("#DirectExcelDownloadType"+randKey).val(excel_type);
	$("#DirectExcelDownloadParam"+randKey).val(queryString);
	$("#DirectExcelDownloadForm"+randKey).submit();
}

/* 특정영역의 테이블을 엑셀로 다운로드하기 */
function divExcelDownload(title, selector){
	var clone = $(selector).clone();

	//기간 클릭 시 도메인이 없어 엑셀 내 오류 수정 pjw
	if(selector == '#account_table'){
		clone.find("a").each(function(){
			var lnk			= document.location.href.split('//');
			var sublinks	= lnk[1].split('/');
			var sublink		= '';
			for(var i = 0; i < (sublinks.length - 1); i++){
				sublink		+= sublinks[i]+'/';
			}
			var dmin		= lnk[0] + '//' + sublink;

			$(this).attr("href", dmin + $(this).attr("href"));
		});
	}

	// input 박스 제거
	clone.find("input[type='text'],textarea,select").each(function(){
		$(this).after($(this).val());
		$(this).remove();
	});
	clone.find("*.hide").each(function(){
		$(this).remove();
	});
	// alpinejs template 태그 제거
	clone.find("template").remove();
	/*
	// a 태그 제거
	clone.find("a").each(function(){
		$(this).after($(this).html());
		$(this).remove();
	});
	*/
	// class,style,width 제거
	//clone.find("*[class]").removeClass();
	//clone.find("*[style]").removeAttr('style');
	//clone.find("*[width]").removeAttr('width');
	clone.find("table").attr('border',1);
	clone.find("th").css('background-color','#e5e5e5');
	clone.find(".except_divExcel").remove();

	var randKey = Math.floor(Math.random()*1000000);

	var html = "";
	html += "<form id='divExcelDownloadForm"+randKey+"' action='../common/divExcelDownload' method='post' class='hide'>";
	html += "<input type='hidden' name='encode' value='1'/>";
	html += "<textarea name='title' id='divExcelDownloadTitle"+randKey+"'></textarea>";
	html += "<textarea name='contents' id='divExcelDownloadContents"+randKey+"'></textarea>";
	html += "</form>";

	$(html).appendTo("body");

	$("#divExcelDownloadTitle"+randKey).val(title);
	$("#divExcelDownloadContents"+randKey).val(window.btoa(unescape(encodeURIComponent(clone.html()))));
	$("#divExcelDownloadForm"+randKey).submit();

}

/* 메뉴별 이슈 카운트 출력 */
function loadIssueCounts(){

	var browserArr	= $.browser.version.split('.');
	var browserVer	= browserArr[0];

	$.ajax({
		"url" : "../common/getIssueCount",
		"dataType" : "json",
		"global" : false,
		"success" : function(data){			
			for(var i in data){

				if(i=="scmwarehousing"){
					$(".header-gnb .mitem-td[code='scm'] .header-gnb-issueCount-layer").append(getIssueCountIcon(data['scmwarehousing']['total'])).effect('bounce');
					$(".header-gnb .mitem-td[code='scm'] .header-gnb-issueCount-layer").attr("style", "left:28px; display:inline-block;")
					// 발주대기건수
					$(".header-gnb .mitem-td[code='scm'] li a[href*='scm_warehousing/sorder']").append(getIssueCountIcon(data['scmwarehousing']['sorder']));
					// 입고대기건수
					$(".header-gnb .mitem-td[code='scm'] li a[href*='scm_warehousing/warehousing']").append(getIssueCountIcon(data['scmwarehousing']['warehousing']));					
				}
				
				$(".header-gnb .mitem-td[code='"+i+"']").each(function(){	
								
					$(this).find(".header-gnb-issueCount-layer[code='"+i+"']").append(getIssueCountIcon(data[i]['total'])).effect('bounce');					
					$(this).find(".header-gnb-issueCount-layer[code='"+i+"']").attr("style", "left:"+$(this).find("a > span").width()+"px; display:inline-block;");			

					if(data[i]['title']){
						if(!$.browser.msie || ($.browser.msie && browserVer > 8))
						$(this).find(".header-gnb-issueCount-layer[code='"+i+"']").attr('title',data[i]['title']);
					}					
					
					$("li a[href*='order/catalog']", this).append(getIssueCountIcon(data[i]['order']));
					$("li a[href*='export/catalog']",this).append(getIssueCountIcon(data[i]['export']));
					$("li a[href*='refund/catalog']",this).append(getIssueCountIcon(data[i]['refund']));
					$("li a[href*='returns/catalog']",this).append(getIssueCountIcon(data[i]['returns']));
					$("li a[href*='board/index']", this).append(getIssueCountIcon(data[i]['mbqna']));
					$(".sub_item a[href*='board/index']", this).append(getIssueCountIcon(data[i]['mbqna']));
					// 신규회원
					$("li a[href*='member/catalog']",this).append(getIssueCountIcon(data[i]['member']));
					// 할인이벤트
					$("li a[href*='event/catalog']",this).append(getIssueCountIcon(data[i]['event']));
					// 사은품 이벤트
					$("li a[href*='event/gift_catalog']",this).append(getIssueCountIcon(data[i]['gift']));
					// 출석체크
					$("li a[href*='joincheck/catalog']",this).append(getIssueCountIcon(data[i]['joincheck']));

					// 발주대기건수
					$("li a[href*='scm_warehousing/sorder']",this).append(getIssueCountIcon(data[i]['sorder']));
					// 입고대기건수
					$("li a[href*='scm_warehousing/warehousing']",this).append(getIssueCountIcon(data[i]['warehousing']));

			
					// 오픈마켓 - 주문수집/등록
					$("li a[href*='market_connector/market_order_list']",this).append(getIssueCountIcon(data[i]['regist']));
					// 오픈마켓 - 취소관리
					$("li a[href*='market_connector/market_cancel_list']",this).append(getIssueCountIcon(data[i]['cancel']));
					// 오픈마켓 - 반품관리
					$("li a[href*='market_connector/market_return_list']",this).append(getIssueCountIcon(data[i]['return']));
					// 오픈마켓 - 교환관리
					$("li a[href*='market_connector/market_exchange_list']",this).append(getIssueCountIcon(data[i]['exchange']));
					// 오픈마켓 - 문의관리
					$("li a[href*='market_connector/market_qna_list']",this).append(getIssueCountIcon(data[i]['qna']));
				});

				if(i == 'setting'){						
					if(data[i]['basic']){	
						$(".submenu .basic a").append(" <span class='red bold settingIssue' style='font-size:11px'>!</span>");
					}
									
					if(data[i]['pg']){
						$(".submenu .pg").closest(".submenu").find(".name").append(" <span class='red bold settingIssue' style='font-size:11px'>!</span>");
						$(".submenu .pg a").append(" <span class='red bold settingIssue' style='font-size:11px'>!</span>");												
					}					
					
					if(data[i]['bank']){
						$(".submenu .bank").closest(".submenu").find(".name").append(" <span class='red bold settingIssue' style='font-size:11px'>!</span>");
						$(".submenu .bank a").append(" <span class='red bold settingIssue' style='font-size:11px'>!</span>");					
					}					

					if(data[i]['total']){
						if(!$.browser.msie || ($.browser.msie && browserVer > 8))
							$(this).find(".header-gnb-issueCount-layer[code='setting']").attr('title','필수 설정');

						$(".qnb-config").append("<span class='issueCount' style='left:60px; top:10px; position:absolute; border-radius: 50%;'><span class='hgi-left'><span class='hgi-right'><span class='hgi-bg'>!</span></span></span></span>");						
					}							
				}
			}

			$(".header-gnb-issueCount-layer[title]").poshytip({
				className: 'tip-darkgray',
				bgImageFrameSize: 8,
				alignTo: 'target',
				alignX: 'right',
				alignY: 'inner-top',
				offsetY: 0,
				offsetX: 5,
				allowTipHover: false,
				slide: false,
				showTimeout : 0
			});
		}
	});


}

/* 이슈카운트 아이콘 반환 */
function getIssueCountIcon(count){
	if(parseInt(count)>0){
		return " <span class='issueCount'><span class='hgi-left'><span class='hgi-right'><span class='hgi-bg'>"+count+"</span></span></span></span>";
	}else{
		return " <span class='issueCountZero'>0</span>";
	}
}

function layerPopupOpen(ID){
	var popupKey = "layerPopup"+ID;

	if(!$.cookie(popupKey)){
		$("#"+ID).show();
	}
}


function layerPopupClose(ID){
	var popupKey = "layerPopup"+ID;
	if($("#"+ID+" input[name='hiddenToday']").is(':checked')){
		$.cookie(popupKey,1,{expires:86500,path:'/'});
	}
	$("#"+ID).fadeOut();
}

// depth 별 목록 반환 :: 2018-11-06 lwh
function depth_select_load(preSelectName,selectName,code,target,depth){
	$("select[name='" + selectName + "'] option").each(function(){ if( $(this).val() ) $(this).remove(); });
	if(preSelectName && !code) return;
	$.ajax({
		type: "GET",
		url: "/admin/common/"+target+"2json",
		data: "categoryCode=" + code,
		dataType: 'json',
		success: function(result){
			var options = "";
			for(var i=0;i<result.length;i++) options += "<option value='"+result[i].category_code+"'>"+result[i].title+"</option>";
			$("select[name='" + selectName + "']").append(options);
			if(options){
				$("select[name='" + selectName + "']").show();
			}
			if(preSelectName){
				$("select[name='" + preSelectName + "'] option[value='"+code+"']").attr("selected",true);
			}
			if($("select[name='" + selectName + "']").attr("defaultValue")){
				$("select[name='" + selectName + "'] option[value='"+$("select[name='" + selectName + "']").attr("defaultValue")+"']").attr("selected",true).change();
			}
		}
	});
}

/* 관리자 카테고리 가져오기*/
function category_admin_select_load(preSelectName,selectName,code,callbackFunction){
	$("select[name='" + selectName + "'] option").each(function(){ if( $(this).val() ) $(this).remove(); });
	if(preSelectName && !code) return;
	$.ajax({
		type: "GET",
		url: "/admin/common/category2json",
		data: "categoryCode=" + code,
		dataType: 'json',
		success: function(result){
			var options = "";
			for(var i=0;i<result.length;i++) options += "<option value='"+result[i].category_code+"'>"+result[i].title+"</option>";
			$("select[name='" + selectName + "']").append(options);
			if(options){
				$("select[name='" + selectName + "']").show();
			}
			if(preSelectName){
				$("select[name='" + preSelectName + "'] option[value='"+code+"']").attr("selected",true);
			}
			if($("select[name='" + selectName + "']").attr("defaultValue")){
				$("select[name='" + selectName + "'] option[value='"+$("select[name='" + selectName + "']").attr("defaultValue")+"']").attr("selected",true).change();
			}

			if(callbackFunction){
				callbackFunction(result);
			}
		}
	});
}

/* 관리자 브랜드 가져오기*/
function brand_admin_select_load(preSelectName,selectName,code,callbackFunction){
	$("select[name='" + selectName + "'] option").each(function(){ if( $(this).val() ) $(this).remove(); });
	if(preSelectName && !code) return;
	$.ajax({
		type: "GET",
		url: "/admin/common/brand2json",
		data: "categoryCode=" + code,
		dataType: 'json',
		success: function(result){
			var options = "";
			for(var i=0;i<result.length;i++) options += "<option value='"+result[i].category_code+"'>"+result[i].title+"</option>";
			$("select[name='" + selectName + "']").append(options);
			if(options){
				$("select[name='" + selectName + "']").show();
			}
			if(preSelectName){
				$("select[name='" + preSelectName + "'] option[value='"+code+"']").attr("selected",true);
			}
			if($("select[name='" + selectName + "']").attr("defaultValue")){
				$("select[name='" + selectName + "'] option[value='"+$("select[name='" + selectName + "']").attr("defaultValue")+"']").attr("selected",true).change();
			}

			if(callbackFunction){
				callbackFunction(result);
			}
		}
	});
}


/* 관리자 이벤트 가져오기*/
function event_admin_select_load(preSelectName,selectName,event_seq,callbackFunction){
	$("select[name='" + selectName + "'] option").each(function(){ if( $(this).val() ) $(this).remove(); });
	if(preSelectName && !event_seq) return;
	$.ajax({
		type: "GET",
		url: "/admin/common/event2json",
		data: "event_seq=" + event_seq,
		dataType: 'json',
		success: function(result){
			var options = "";
			for(var i=0;i<result.length;i++) options += "<option value='"+result[i].event_benefits_seq+"'>"+result[i].title+"</option>";
			$("select[name='" + selectName + "']").append(options);
			if(options){
				$("select[name='" + selectName + "']").show();
			}
			if(preSelectName){
				$("select[name='" + preSelectName + "'] option[value='"+event_seq+"']").attr("selected",true);
			}
			if($("select[name='" + selectName + "']").attr("defaultValue")){
				$("select[name='" + selectName + "'] option[value='"+$("select[name='" + selectName + "']").attr("defaultValue")+"']").attr("selected",true).change();
			}

			if(callbackFunction){
				callbackFunction(result);
			}
		}
	});
}

/* 관리자 지역 가져오기*/
function location_admin_select_load(preSelectName,selectName,code,callbackFunction){
	$("select[name='" + selectName + "'] option").each(function(){ if( $(this).val() ) $(this).remove(); });
	if(preSelectName && !code) return;
	$.ajax({
		type: "GET",
		url: "/admin/common/location2json",
		data: "locationCode=" + code,
		dataType: 'json',
		success: function(result){
			var options = "";
			for(var i=0;i<result.length;i++) options += "<option value='"+result[i].location_code+"'>"+result[i].title+"</option>";
			$("select[name='" + selectName + "']").append(options);
			if(options){
				$("select[name='" + selectName + "']").show();
			}
			if(preSelectName){
				$("select[name='" + preSelectName + "'] option[value='"+code+"']").attr("selected",true);
			}
			if($("select[name='" + selectName + "']").attr("defaultValue")){
				$("select[name='" + selectName + "'] option[value='"+$("select[name='" + selectName + "']").attr("defaultValue")+"']").attr("selected",true).change();
			}

			if(callbackFunction){
				callbackFunction(result);
			}
		}
	});
}

/* 카테고리,브랜드 상품 정렬 레이어팝업 띄우기 */
function show_goods_sort_popup(kind,code,count_w,count_h,mobile_setting){
	top.openDialogPopup("상품 순서 변경", "goods-sort-popup", {
		'url' : '/admin/common/goods_sort_popup',
		'data' : {'kind':kind,'code':code,'count_w':count_w,'count_h':count_h,'mobile_setting':mobile_setting},
		'modal' : true,
		'width' : '1024px',
		'height' : 600
	});
}

function printOrderView(ordno, pagemode){
	if(typeof pagemode == 'undefined') var pagemode = '';
	if(!pagemode) pagemode = '';
	window.open('/admin/order/order_print?pagemode=' + pagemode + '&ordno='+ordno, '', 'width=850px,height=800px,toolbar=no,location=no,resizable=yes,scrollbars=yes');
}

function printExportView(ordno, code, pagemode){
	if(typeof pagemode == 'undefined') var pagemode = '';
	if(!pagemode) pagemode = '';
	if(!code){
		code = '';
	}
	window.open('/admin/export/export_print?pagemode='+pagemode+'&export='+code+'&ordno='+ordno, '', 'width=850px,height=800px,toolbar=no,location=no,resizable=yes,scrollbars=yes');
}

function printInvoiceView(ordno, code){
	if(!code){
		code = '';
	}
	window.open('/admin/export/invoice_prints?export='+code+'&ordno='+ordno, '', 'width=800px,height=800px,toolbar=no,location=no,resizable=yes,scrollbars=yes,menubar=yes');
}

/* 단일상품 검색창 띄우기 */
/*
사용예
$(".goods_matching").bind('click',function(){
	var order_item_seq = $(this).attr('order_item_seq');
	select_one_goods("select_one_goods_callback|"+order_item_seq);
});
function select_one_goods_callback(order_item_seq,goods_seq){
	alert(order_item_seq+"=>"+goods_seq);
}
*/
function select_one_goods(select_one_goods_callback_func){
	if($("#selectOneGoodsPopup").length==0){
		$('body').append("<div id='selectOneGoodsPopup'><div id='selectOneGoodsInner'></div></div>");
	}
	openDialog("상품 선택", "#selectOneGoodsPopup", {"width":"99%","show" : "fade","hide" : "fade", "height": "600"});
	$.ajax({
		type: "get",
		url: "../goods/select",
		data: "innerMode=2&type=select_one_goods&containerHeight=230&page=1&select_one_goods_callback="+select_one_goods_callback_func,
		success: function(result){
			$("#selectOneGoodsInner").html(result).show();
			$("#selectOneGoodsInnerContainer").show();
		}
	});
}

/* 에디터사용(이벤트시 에디터로딩) */
function view_editor(textid,buttonid)
{	
	$("#"+textid).addClass("daumeditor");
	if(buttonid != "") $("#"+buttonid).css("display","none");
	if(typeof $("#"+textid).attr('fullMode') == 'undefined') {
		$("#"+textid).attr('fullMode','1');
	}
	DaumEditorLoader.init(".daumeditor");
}

/* 디자인창 : 아이에디터 */
var EYEEDITOR_WINDOW = null;
function DM_window_eyeeditor(template_path){
	var queryString = template_path ? "?template_path="+encodeURIComponent(template_path?template_path:'') : "";
	//if(!EYEEDITOR_WINDOW || (EYEEDITOR_WINDOW && typeof EYEEDITOR_WINDOW.document == 'unknown')){
		EYEEDITOR_WINDOW = window.open("/admin/design/eye_editor"+queryString,"eyceditorWindowAdmin","width=950,height=650,scrollbar=no,resizable=yes");
	//}else{
		//EYEEDITOR_WINDOW.document.focus();
	//}
}

function reset_iframe(frame_name,src_url)
{
	if(!frame_name) frame_name = "actionFrame";
	if(!src_url) src_url = "/main/blank";
	$("iframe[name='"+frame_name+"']").attr("src",src_url);
}

function admin_goods_image(iobj,goods_seq,idx,image_type){
	var img_obj = $(iobj);
	img_obj.attr('src','/admin/skin/default/images/common/noimage_list.gif');
	/*
	$.get('../common_cb/admin_goods_image?goods_seq='+goods_seq+'&idx='+idx+'&image_type='+image_type, function(data) {
		if( data ){
			img_obj.attr('src',data);
		}else{
			img_obj.attr('src','/admin/skin/default/images/common/noimage_list.gif');
		}
	});*/
}

function design_mobile_skin_chk(skinVersion){
	if(skinVersion < 3) parent.openDialogConfirm("모바일 전용 상품 디스플레이 설정은<br/> [mobile_ver3] 이상 스킨에서 작동 합니다<br/> 변경 하러 가시겠습니까?",'400','160',function(){parent.location.href='/admin/design/skin?prefix=mobile'});
}

function open_crm_summary(obj,member_seq,order_seq,layerWay){
	var bobj = $(obj);
	var url = '';

	if(!member_seq && !order_seq){
		return false;
	}

	var btnOffset = bobj.offset();
	$("#member_info_layer").hide();
	$("#member_info_layer").css("top", (btnOffset.top+0)+"px");

	if(layerWay == "left"){
		$("#member_info_layer").css("left", (btnOffset.left-380+($(bobj).width()*2))+"px");
	}else{
		$("#member_info_layer").css("left", btnOffset.left+"px");
	}

	num = 10;
	$("div").each(function(){
		var z = $(this).css("z-index");
		if( z != "auto" && parseInt(z) >= num ){
			num = z+1;
		}
	});
	$("#member_info_layer").css("z-index", num);

	if(member_seq > 0){
		url = '/admin/member/member_crm_detail?member_seq='+member_seq;
	}else if(order_seq) {
		url = '/admin/member/nomember_crm_detail?order_seq='+order_seq;
	}

	$.get(url, function(data) {
		$("#member_info_layer").html(data);
		$("#member_info_layer").show();
	});

}

// 상품 선택 배송그룹연결 :: 2016-07-01 lwh
function popup_goods_list(displayId,inputGoods,provider_seq){

	$("div#"+displayId).remove();
	$("body").append("<div id='"+displayId+"'></div>");
	$.ajax({
		type: "get",
		url: "/admin/goods/select_goods",
		data: "page=1&inputGoods="+inputGoods+"&displayId="+displayId+"&provider_seq="+provider_seq,
		success: function(result){
			$("div#"+displayId).html(result);
		}
	});
	openDialog("상품 검색", displayId, {"width":"970","height":"820","show" : "fade","hide" : "fade"});
}

var get_sns_guide_ajax = function(mode,popup_title,popup_id,w,h){
	that = $('#'+popup_id);
	if	(that.html().length < 2){
		$.ajax({
			url: "/admin/setting/get_sns_guide_ajax",
			type: "get",
			data : {"mode":mode},
			success : function(e){
				that.html(e);
				openDialog(popup_title,popup_id, {"width":w,"height":h,"show" : "fade","hide" : "fade","modal":false});
			}
		});
	}else{
		openDialog(popup_title,popup_id, {"width":w,"height":h,"show" : "fade","hide" : "fade","modal":false});
	}
};

var env_move = function(url){
	if	(!url)
		return;
	var now_href	= $(location).attr('href');
	var now_host	= $(location).attr('host')
	env_loc			= now_href.replace(now_host,url);
	window.open(env_loc);
}


// 노출 설정 팝업 ( 목록용 : 실시간 저장 )
function openGoodsDisplayTerms(goods_seq){

	var lay_id	= 'set_display_terms_lay';	// closeGoodsDisplayTerms 함수도 수정해야 함.

	$.ajax({
		url: '/admin/goods/get_display_terms',
		type: "get",
		data : {"goods_seq":goods_seq},
		success : function(result){
			var layObj	= $('body').find('div#' + lay_id);
			if	(!layObj.attr('isset')){
				layObj	= $('<div id="' + lay_id + '" isset="Y"></div>').appendTo($('body'));
			}
			layObj.html(result);

			setDatepicker();
			$(".colorpicker").customColorPicker();

			openDialog('노출 설정', lay_id, {"width":"950","height":"440","show" : "fade","hide" : "fade"});
		}
	});
}

// 노출설정 팝업 닫기
function closeGoodsDisplayTerms(goods_seq, dst_terms){
	// 수동방식일 경우 자동노출 설정 제거
	if	(dst_terms == 'MENUAL'){
		$('span.display-terms-' + goods_seq).hide();
		$('span.display-goods-view-' + goods_seq).show();
	}
	closeDialog('set_display_terms_lay');
}

