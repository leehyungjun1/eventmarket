$(document).ready(function() {

	var bxArrowOpen = function(obj,bxCode){
		if(bxCode == "OPEN"){
			obj.closest(".bx-lay").find("div.cont").show();
			obj.removeClass("CLOSE").addClass(bxCode).attr("data-mode","CLOSE");
		}else{
			obj.closest(".bx-lay").find("div.cont").hide();
			obj.removeClass("OPEN").addClass(bxCode).attr("data-mode","OPEN");
		}
	}
	
	if(typeof batchModify == 'undefined') {
		batchModify = false;
	}

	//////////////////////////////////////////////////////////////
	// 열기고정, 열기/닫기 버튼 삽입
	// 열기 고정 체크박스가 전체 해제된 2가지 경우
	// 1. 한번도 설정된 적이 없을 때(기능 패치 직 후)	:: 미리 지정된 기본 설정 값에 따름. 해당 컨텐츠 view(display) 여부 종속
	// 2. 관리자 의도로 전체 해제 한 경우 				:: 체크 박스 전체 해제 및 해당 컨텐트 display:none
	$(".bx-lay").each(function(){
		var bxChkHtml 		= "";
		var bxCode			= $(this).attr("data-bxcode");
		var openClose	 	= bxOpenSetObj['default'][bxCode][0];	// 기본 설정 값 : 열기/닫기
		var openFixingUse 	= bxOpenSetObj['default'][bxCode][1];	// 열기 고정 옵션 제공 여부(열기고정 제공 아닌 경우 무조건 Open상태)

		// 열기고정 제공
		if(openFixingUse){
			bxChkHtml 	+= '<label class="resp_checkbox"><input type="checkbox" name="bxOpenFixing[]" value="'+bxCode+'"';
			if(( bxOpenSetObj['fixing'] == 'null') || $.inArray(bxCode,bxOpenSetObj['fixing']) != -1){
				bxChkHtml	+= ' checked';
				openClose 	= "OPEN";
			}
			bxChkHtml	+= '> <span >열기 고정</label>';
		}
		bxChkHtml += '<a href="javascript:void(0)" class="bx_arrow" data-mode="CLOSE"></a>';
		var pattern = new RegExp('bx_arrow');
		if(pattern.test($(this).find('.right').html()) == false) {
			$(this).find(".bx-title .right").html(bxChkHtml);
		}
		bxArrowOpen($(this).find("a.bx_arrow"),openClose);
	});

	/* box 열기/닫기 event */
	$("a.bx_arrow, .bx-lay > .bx-title > .item-title").on("click",function(e,bxCode){
		bxArrowOpen($(this).closest(".bx-lay").find("a.bx_arrow"),$(this).closest(".bx-lay").find("a.bx_arrow").attr("data-mode"));
	});
	if(typeof gift == "undefined" && batchModify !== true) {
		gCategorySelect.open({'openType':'self','closeMessageUse':false,'fieldName':'connectCategory[]','divSelectLay':'lay_category_select','callPoint':'goods',});
	}


	$("input[name='chkall']").on("click",function(){
		var selector = $(this).closest("table").find(".chk");
		if($(this).val() == "goods"){
			selector = $(this).closest("div").parent().next(".goods_list").find(".chk");
		}
		var checked = $(this).is(":checked");
		selector.each(function(){
			$(this).prop("checked",checked);
		});
	});

	// 배송정보 :: 2016-10-21 lwh
	if(goodsObj.shipping_group_seq != '' && typeof gift == "undefined" && batchModify === false){
		get_shipping_group_info(goodsObj.shipping_group_seq);
	}

	if(window.Firstmall.Config.Environment.serviceLimit.H_NFR == true){
		payMethodChange(goodsObj.possible_pay_type);
	}

	//입점사 버전일경우
	if(typeof gift == "undefined" && batchModify === false && window.Firstmall.Config.Environment.serviceLimit.H_AD == true){

		// 본사/입점사 선택에 따라 노출 필드 세팅
		var _provider_info = function(gubun,provider_seq){

			if(gubun == "provider"){
				if((gl_goods_seq == '' || gl_goods_seq == 0 ) && $("form[name='goodsRegist'] select[name='provider_seq_selector'] option:selected").val() != 0){
					provider_seq = $("form[name='goodsRegist'] select[name='provider_seq_selector'] option:selected").val();
				}
				do_rollback(provider_seq);
				$("form[name='goodsRegist'] select[name='provider_seq_selector']").trigger("change",[provider_seq]);

				do_provider_change(provider_seq);
				$(".tr_provider, .tr_account").show();
			}else{
				$(".tr_provider, .tr_account").hide();
				$('.not_for_provider').removeClass('hide');
				$('.not_for_seller').addClass('hide');
				$("form[name='goodsRegist'] input[name='provider_seq']").val(1);
				gl_provider_seq = 1;
			}
		}
		
		// 입점사 선택 시 기본 정보 세팅(입점사명, 정산방법, 배송방법 등)
		var do_provider_change = function(provider_seq){

			if(provider_seq == '' || provider_seq == undefined || typeof provider_seq == "undefined"){
				var selector = $("form[name='goodsRegist'] select[name='provider_seq_selector']");
				provider_seq = selector.find(":selected").val();
			}
			if(provider_seq == ''){
				provider_seq = 0;
			}else{
				provider_seq = parseInt(provider_seq);
			}
			var goods_gubun = $("input[name='goods_gubun']:checked").val();
			if(goods_gubun == 'admin' && provider_seq != 0) provider_seq = 1;

			// 배송그룹 세팅 

			if(!goodsObj.shipping_group_seq) {
					
				if(provider_seq == 0){
					$("input[name='old_group_seq']").val('');
					$("input[name='shipping_group_seq']").val('');
				}else{
					if(socialcpuse_flag) { // 티켓일 경우 배송그룹정보 추출
						$.ajax({
							'url' : 'get_coupon_shippinggrp',
							'data' : {'provider_seq':provider_seq},
							'dataType' : 'text',
							'success' : function(res){
								$("input[name='old_group_seq']").val(res);
								$("input[name='shipping_group_seq']").val(res);
							}
						});
					}else{ // 상품일 경우 기본 배송그룹정보 추출
						$.ajax({
							'url' : 'get_base_shippinggrp',
							'data' : {'provider_seq':provider_seq},
							'dataType' : 'text',
							'success' : function(res){
								if(res){
									get_shipping_group_info(res);
									$("input[name='old_group_seq']").val(res);
									$("#shipping_group_seq").val(res).trigger('change');
								}
							}
						});
					}
				}
			}

			if(provider_seq == 0){
				// 선택된 입점사 없을 때 정산방식 및 수수료금액 등 초기화
				$(".ptc-charges").html('입점사를 선택 하세요.');
				
				$("input[name='default_charge']").val('');
				$("input[name='default_commission_type']").val('');
				$("input[name='commissionRate[]']").val('');
			}else if(provider_seq == 1){
				$('.not_for_provider').removeClass('hide');
				$('.not_for_seller').addClass('hide');
				
				if(gl_provider_seq == ''){
					$("form[name='goodsRegist'] select[name='provider_seq_selector'] option").eq(0).prop("select",true);
					$("form[name='goodsRegist'] select[name='provider_seq_selector']").next(".ui-combobox").children("input").val(	$("select[name='provider_seq_selector'] option:selected").text());
					$(".ptc-charges").html('입점사를 선택 하세요.');
					$("input[name='provider_seq']").val(0);
				}

				$("input[name='commissionRate[]'], input[name='subCommissionRate[]']").off('keydown change focusin selectstart',function(){
				});
			}else{
				$('.not_for_provider').addClass('hide');
				$('.not_for_seller').removeClass('hide');

				if(window.Firstmall.Config.Environment.isSellerAdmin){
					$("input[name='commissionRate[]'], input[name='subCommissionRate[]']").on('keydown change focusin selectstart',function(){
						$(this).blur();
						return false;
					});					
				}

				// 선택한 입점사 기본 수수료율 세팅
				$.ajax({
					'url' 		: 'provider_charge_list',
					'data' 		: {'provider_seq':provider_seq},
					'dataType' 	: 'json',
					'global' 	: false,
					'success' 	: function(res){
						var html = "";
						$(".ptc-charges").empty();
						if(res.length){
							for(var i=0;i<res.length;i++){
								if(i) html += ' / ';
								//html += res[i].link=='1' ? '기본' : res[i].brand_name;

								if(res[i].link=='1'){

									var commission_info	= res[i];

									$('.commission_type_desc').hide();
									$('.commission_type').hide();

									commission_unit = '%';
									if(commission_info.commission_type =='SUPR') commission_unit = Firstmall.Config.Environment.Currency.Basic.Symbol;
									
									if(commission_info.commission_type == 'SACO' || commission_info.commission_type == ''){
										//수수료 방식
										$('.commission_type_title').text('수수료');
										$('.SACO_desc').show();
										$('.SACO_unit').show();
										html += '수수료 방식';
									}else{
										$('.commission_type_title').text('공급가');
										$('.SUPPLY_desc').show();
										$('.SUPPLY_unit').show();
										html += '공급가 방식';
									}

									if(commission_info.commission_type == 'SACO' || commission_info.commission_type == 'SUCO'){
										html += '(' + res[i].charge + '' + commission_unit+')';
									}else{
										html += '(' + comma(num(res[i].charge)) + commission_unit+')';
									}

									if(!goodsObj.goods_seq){
										$("input[name='default_charge']").val(commission_info.charge);
										$("input[name='default_commission_type']").val(commission_info.commission_type);
										$("input[name='commissionRate[]']").val(commission_info.charge);
										if(commission_info.commission_type == 'SUPR'){
											$('select[name="commissionType[]"]>option[value="SUPR"]').attr('selected',true)
										}else{
											$('select[name="commissionType[]"]>option[value="SUCO"]').attr('selected',true)
										}

										$('select[name="commissionType[]"]').trigger('change');

									}
									$(".storeinfo_title").html("재고: " + res[i].provider_name);
								}
							}

							//기본 옵션이 있을경우 리셋
							var have_to_subopt_reset	= true;
							if($("input[name='tmp_option_seq']").val() != '' && $('input[name="optionUse"]').is(':checked')){
								//reset_option_commition_info(commission_info);		//함수 정의된 곳 없어 삭제 20201127
								have_to_subopt_reset	= false;
							}

							//기본옵션이 없는경우에만 추가옵션 리셋(기본 옵션 완료 후 자동 리셋됨)
							if(have_to_subopt_reset === true && $("input[name='tmp_suboption_seq']").val() && $('input[name="subOptionUse"]').is(':checked')){
								//reset_suboption_commition_info(commission_info);	//함수 정의된 곳 없어 삭제 20201127
							}

						}else{
							html = "입점사 "+provider_seq+" 에 설정된 브랜드가 없습니다.";
						}

						$(".ptc-charges").html(html);
					}
				});
			}

			gl_provider_seq = provider_seq;
		}

		var do_rollback = function(provider_seq){

			var selector = $("form[name='goodsRegist'] select[name='provider_seq_selector']");
			if(provider_seq == '' || provider_seq == undefined || typeof provider_seq == "undefined"){
				provider_seq = selector.find(":selected").val();
			}

			if(provider_seq > 0){
				var selector_option = selector.find("option[value='"+provider_seq+"']");
				selector_option.attr("selected","selected");
				$("form[name='goodsRegist'] input[name='provider_seq']").val(provider_seq);
				selector.next('.ui-combobox').children('input').val(selector_option.text());
				$("form[name='goodsRegist'] input[name='provider_name']").val(selector_option.text());
			}
		}

		// 본사상품, 입점사 상품 선택
		$("input[name='goods_gubun']").on("click",function(e,provider_seq){

			if(typeof provider_seq == 'undefined') provider_seq = '';
			var msg 	= '본사 상품으로 변경 시, 입력한 상품 정보가 초기화 됩니다.';
			if($(this).val() == "provider"){
				msg 	= '입점사 상품으로 변경 시, 입력한 상품 정보가 초기화 됩니다.';
			}
			if(confirm(msg)){
				_provider_info($(this).val(),provider_seq);
			}else{
				e.preventDefault();
			}
			
			if($(this).val() == 'admin'){
				$("th.admin, td.admin,label.admin").show();
				$("th.provider, td.provider, label.provider").hide();
			}else{
				$("th.admin, td.admin, label.admin").hide();
				$("th.provider, td.provider, label.provider").show();
			}
			resetStockInput($(this).val());		// 재고관리 input 창 입력가능 여부 재세팅
			
		});

		if(gl_provider_seq > 1){
			_provider_info('provider',gl_provider_seq);		// 본사/입점사 선택에 따라 노출 필드 세팅
			//$("input[name='goods_gubun'][value='provider']").prop("checked",true);
		}
		/* 본사에서 입점사 상품등록시 입점사 수수료율 출력 */
		$("form[name='goodsRegist'] select[name='provider_seq_selector']").on('change',function(event,provider_seq){

			var tmp_option_seq		= $("input[name='tmp_option_seq']").val();
			var tmp_suboption_seq	= $("input[name='tmp_suboption_seq']").val();
			var selected_val		= this;

			if(	(tmp_option_seq != '' && $('input[name="optionUse"]').is(':checked')) ||
				(tmp_suboption_seq != '' && $('input[name="subOptionUse"]').is(':checked'))
			){
				openDialogConfirm('입점사를 변경하면 입력하신 옵션의 수수료는 리셋 됩니다.<br/>계속 하시겠습니까?',400,200,do_provider_change,do_rollback);
			}else{
				do_provider_change(provider_seq);
			}

		});

		if (gl_provider_seq == '1') {
			$("input[name='commissionRate[]'], input[name='subCommissionRate[]']").val('100');
			$("input[name='commissionRate[]']").eq(0).change();
			$("input[name='subCommissionRate[]']").eq(0).change();
		}else{
			//$("form[name='goodsRegist'] select[name='provider_seq_selector']").trigger("change");
		}
	}

	// 배송비 선택
	$(".shipping_group_select").on("click",function(){
		var provider_seq = gl_provider_seq;
		if($("input[name='goods_gubun']:checked").val() == "admin"){
			provider_seq = 1;
		}else{
			provider_seq = $("form[name='goodsRegist'] input[name='provider_seq']").val()
		}
		ship_grp_sel('select',provider_seq);
	});
	
	$(".goods_shipping_group_name").on("click",function(){
		if($(this).html() == ""){
			alert("배송비를 먼저 선택하세요");
			return false;
		}
		window.open("../setting/shipping_group_regist?shipping_group_seq="+$(this).attr("data-shipping_group_seq")+"&provider_seq="+gl_provider_seq);
	});

	// 상품추가/수정-프로모션
	// unbind
	$('#gradeDiscountSet, #getRefererDiscountRows, #getGiftRows').unbind('click');

	// bind
	// 회원등급
	$('#gradeDiscountSet').on('click', function(){
		gradeDiscount();
	});

	// 회원등급 할인 상세
	$(".btnMemberGradeEventView").on("click",function(){
		openDialog("혜택 세트 상세", "viewMemberGradeEvent", {"width":"700","height":"600","show" : "fade","hide" : "fade"});
	});

	//동영상넣기의 동영상클릭시-> 동영상자동실행설정되어있어야함
	$(document).on("click", ".GDDisplayVideoWrap", function(e){
		$(this).find("img").addClass("hide");
		$(this).find(".gddisplaythumbnailvideo").addClass("hide");
		$(this).find("iframe").removeClass("hide");
	});
	
	$("input[type='radio'][name='tax']").on('change', function() {
		if(this.value == 'exempt') {
			$('input[name="tax_chk"]').attr('disabled', false);
		} else {
			$('input[name="tax_chk"]').attr('disabled', true);
			$('input[name="tax_chk"]').attr('checked', false);
		}
	});

	if ( batchModify === false &&  gl_tax) {
		$("input[type='radio'][name='tax'][value='" + gl_tax + "']").attr("checked",true);
		$("input[type='radio'][name='tax'][value='" + gl_tax + "']").trigger('change');
	} else if(batchModify === false) {
		$("input[type='radio'][name='tax'][value='tax']").attr("checked",true);
		$("input[type='radio'][name='tax'][value='tax']").trigger('change');
	}

	//이미지 미리 보기
	$("#goodsImageTable").on("click", '.goodsImageDetail .view', function() {

		var nowImageKey		= $(this).attr('imageType');
		var nowImageName	= goodsObj.goods_image_size[nowImageKey]['name'];
		
		var selector		= $(this).closest("tr");
		var idx				= selector.index();
		var imgUrl			= (selector.find("input[name='" + nowImageKey + "GoodsImage[]']").val())? selector.find("input[name='" + nowImageKey + "GoodsImage[]']").val():"";
		var imgWidth		= (selector.find("input[name='" + nowImageKey + "GoodsImageWidth[]']").val())? selector.find("input[name='" + nowImageKey + "GoodsImageWidth[]']").val():"";
		var imgHeight		= (selector.find("input[name='" + nowImageKey + "GoodsImageHeight[]']").val())? selector.find("input[name='" + nowImageKey + "GoodsImageHeight[]']").val():"";
		var imgUrlArray		= imgUrl.split('?');
		var _tmpsrc			= (imgUrlArray[1]) ? imgUrl+ '&' + new Date().getTime():imgUrl+ '?' + new Date().getTime();
		var src				= (imgUrl)?_tmpsrc:"";

		if(!src) src = "/admin/skin/default/images/common/noimage_list.gif";

		var cutname		= "1번째";
		if(idx > 0){
			cutname		= idx + 1;
			cutname		+= "번째";
		}

		var clone		= $("#goodsImageMake").clone();

		clone.find("th img").attr("src",src);		
		clone.find("table td span").eq(0).html( cutname + " - " + nowImageName );

		$("#goodsImagePriview").html(clone);
		$("#fileurl").html(src);
		$("input[name='idx']").val(idx);
		$("input[name='imgKind']").val(nowImageKey);

		// 레이블 표시 :: 2016-04-28 lwh
		var label_view	= $("form input[name='" + nowImageKey + "GoodsLabel[]']").eq(idx).val();
		if(label_view)
			label_view	= label_view;
		else
			label_view	= '-';

		$("#goodsImgLabel_view").html(label_view)
		$("#fileOptionAble").attr("data-target", idx);
		
		if (nowImageKey == 'view') {
			$("#FileColorView").show();
			if(selector.find('.fileColorTitle').html())
				$("#filecolor").html(selector.find('.fileColorTitle').clone());
			else
				$("#filecolor").html('<span class="gray">본 이미지에 매칭된 색상 없음</span>');
				
		} else {
			$("#FileColorView").hide();
		}

		//이미지사이즈 체크를 위해 마지막에 위치함
		$("#goodsImagePriview #viewImg").load(function () { //이미지가 로딩이 완료 된 후
			//var sizetxt = $("#viewImg").width() + ' X ' + $("#viewImg").height();
			var sizetxt = '-';
			if(imgWidth || imgHeight){
				sizetxt = imgWidth + ' X ' + imgHeight;
			}
			var halfImgWidth = $(this).width() / 2;
			var halfImgHeight = $(this).height() / 2;

			var positionW = ($(this).parent().width() - $(this).width()) / 2;
			var version = Browser.detectIE();
		
			if( version) {
				$(this).attr("style","margin-top:-"+halfImgHeight+"px;margin-left:"+positionW+"px;");
			}else{
				$(this).attr("style","margin-top:-"+halfImgHeight+"px;-webkit-margin-start:-"+halfImgWidth+"px;");
			}

			$("#goodsImagePriview #filesize").html(sizetxt);
		});
		//이미지사이즈 체크를 위해 마지막에 위치함
	});

	openDialog("이미지 업로드 <span class='desc'>이미지 파일을 업로드합니다.</span>", "imageUploadDialog", {"width":500,"height":300,"autoOpen":false,"close":function(){
		$("#imageUploadButton").uploadifyCancel();
		$("#imageUploadButtonQueue").empty();
		$("#imageUploadButton").uploadifyClearQueue();
	}});

	$("#goodsRegist").on("click", '.connectCategory', function(){$(this).parent().parent().find("input[type='radio']").attr("checked",true);});
	$("#goodsRegist").on("click", '.connectBrand', function(){$(this).parent().parent().find("input[type='radio']").attr("checked",true);});
	$("#goodsRegist").on("click", '.connectLocation', function(){$(this).parent().parent().find("input[type='radio']").attr("checked",true);});

	/* 카테고리 연결 팝업*/
	$("#categoryConnectPopup").on("click", function(){

		var opts = {
			'openType'			:'popup',
			'categoryType'		:'category',
			'selectMode'		:'lastCategory',
			'closeMessageUse'	:true,
			'fieldName'			:'connectCategory[]',
			'divSelectLay'		:'categoryPopup',
			'divSelectTitle'	:'최근 연결 카테고리',
			'callPoint'			:'goods',
			'provider_seq'		: $("form[name='goodsRegist'] input[name='provider_seq']").val(),
		}
		gCategorySelect.open(opts);
	});

	/* 브랜드 연결 팝업*/
	$(".brandConnectPopup").on("click",function(){
		var opts = {
						'categoryType'		:'brand',
						'openType'			:'popup',
						'selectMode'		:'lastCategory',
						'closeMessageUse'	:true,
						'fieldName'			:'connectBrand[]',
						'divSelectLay'		:'categoryPopup',
						'divSelectTitle'	:'최근 연결 브랜드',
						'callPoint'			:'goods',
					};
		if($(this).attr("data-selectMode") != "lastCategory"){
			opts.selectMode 		= 'category';
			opts.divSelectTitle 	= '브랜드 연결';
			opts.closeMessageUse	= false;
		}
		gCategorySelect.open(opts);
	});

	/* 지역 연결 팝업*/
	$(".locationConnectPopup").on("click",function(){
		var opts = {
						'categoryType'		:'location',
						'openType'			:'popup',
						'selectMode'		:'lastCategory',
						'closeMessageUse'	:true,
						'fieldName'			:'connectLocation[]',
						'divSelectLay'		:'categoryPopup',
						'divSelectTitle'	:'최근 연결 지역',
						'callPoint'			:'goods',
					};
		if($(this).attr("data-selectMode") != "lastCategory"){
			opts.selectMode 		= 'category';
			opts.divSelectTitle 	= '지역 연결';
			opts.closeMessageUse	= false;
		}
		gCategorySelect.open(opts);
	});

	// 아이콘 설정
	if(batchModify === false || (batchModify == true && mode == 'icon')) {
		var iconClone		= $("#iconTr tr").eq(0).clone();
		iconClone.find("input[type='text']").each(function(){$(this).val("");});
		iconClone.removeClass("hide");

		$("#iconViewTable").find("input.iconDate").addClass('datepicker');

		setDatepicker($("#iconViewTable").find("input.iconDate"));

		iconClone.find("select").each(function(){$(this).find("option").eq(0).attr("selected",true);});

		if (goodsObj.iconCount < 1){
			$("#iconViewTable tbody tr").eq(2).remove();
			$("#iconViewTable tbody tr.nothing").removeClass('hide');
		}else{
			$("#iconViewTable tbody tr.nothing").addClass('hide');
		}

		/* 아이콘 추가 */
		$("#iconAdd").on("click", function(){
			var newClone 	= iconClone.clone();
			var trObj 		= $("#iconViewTable tbody");
			newClone.find("input[type='text']").addClass('datepicker');
			trObj.append(newClone);
			if ($("#iconViewTable tbody tr").length > 1){
				$("#iconViewTable tbody tr.nothing").addClass('hide');
			}else{
				$("#iconViewTable tbody tr.nothing").removeClass('hide');
			}
			apply_input_style(newClone.find("input[type='text']"));
		});

		/* 아이콘 삭제 */
		$("#iconViewTable").on("click", '.iconDel', function(){
			$(this).closest("tr").remove();
			if ($("#iconViewTable tbody tr").length < 2){
				$("#iconViewTable tbody tr.nothing").removeClass('hide');
			}else{
				$("#iconViewTable tbody tr.nothing").addClass('hide');
			}
		});

		/* 선택된 아이콘 출력 */
		$("#iconViewTable").on("click", ".goodsIcon", function(){
			var trObj	= $("#iconViewTable tbody").children("tr");
			var idx		= trObj.index(trObj.has(this));

			console.log("AAA");

			$("input[name='iconIndex']").val(idx);
			set_goods_icon();

			closeDialog("goodsIconPopup");
			openDialog("아이콘 선택", "goodsIconPopup", {"width":"570","height":"600","show" : "fade","hide" : "fade"});
		});

		/* 파일업로드버튼 ajax upload 적용 */
		var opt			= {
			"addData" : "allow_types=gif",
		};
		var callback	= function(res){
			var result	= eval(res);
			if(result.status){
				$("input[name='goodsIconImg']").val( result.filePath + result.fileInfo.file_name);
				$("#goodsIconPopup").find('.preview_image > img').attr('src', result.filePath + result.fileInfo.file_name).parents('.preview_image').removeClass('hide');
			}else{ // 업로드 실패
				alert('[' + result.desc + '] ' + result.msg);
				return false;
			}
		};

		$('#goodsIconImg').createAjaxFileUpload(opt, callback);	
	}

	//changeFileStyle();

	/* 아이콘 선택 */
	$("#goodsIconPopup").on("click", ".icon", function(){
		var idx = $("input[name='iconIndex']").val();
		var src = $(this).attr("src");
		if( src != "") {
			src = $(this).parents("tr").find("img.icon").attr("src");
		}

		$("#iconViewTable tbody tr").eq(idx).children("td").eq(1).find("img").attr("src",src);
		var arr = src.split(".");
		var selectedIndex = arr[0].replace("/data/icon/goods/","");
		$("input[name='goodsIcon[]']").eq(idx-1).val(selectedIndex);
		closeDialog("goodsIconPopup");
	});

	/* 추가정보 추가*/
	var etcClone = $("#etcViewTable tbody tr").eq(1).clone();

	etcClone.find("input[type='text']").each(function(){$(this).val("");});
	etcClone.find("input[type='hidden']").each(function(){$(this).val("model");});
	etcClone.find("select").each(function(){$(this).find("option").eq(0).attr("selected",true);});
	etcClone.find(".etcContent_size").html('0');

	$("#etcViewTable tbody tr").eq(1).remove();


	/* 추가 정보 항목 추가 */
	$("#etcAdd").on("click", function(){
		var trObj = $("#etcViewTable tbody tr");
		trObj.parent().append(etcClone.clone().removeClass("hide"));
		trObj.parent().find("tr.nothing").addClass("hide");
		addLimitTextEvent();
	});
	
	// 결제수단 : 개별 설정
	$("#possible_pay_button").on("click", function(){
		var obj							= $("input:radio[name=possible_pay_type]:checked");
		var payObj						= $("input[name='possible_pay[]']");
		var possible_pay_text 			= '';
		var possible_pay				= new Array();
		possible_pay["card"]			= "신용카드";
		possible_pay["account"]			= "계좌이체";
		possible_pay["virtual"]			= "가상계좌";
		possible_pay["cellphone"]		= "핸드폰";
		possible_pay["escrow_account"]	= "에스크로 계좌이체";
		possible_pay["escrow_virtual"]	= "에스크로 가상계좌";
		possible_pay["bank"]			= "무통장 입금";
		possible_pay["kakaopay"]		= "카카오페이";
		possible_pay["payco"]			= "페이코";
		possible_pay["naverpayment"]	= "네이버페이 결제형";
		possible_pay["paypal"]			= "페이팔";
		possible_pay["alipay"]			= "알리페이";
		possible_pay["axes"]			= "엑시즈";
		possible_pay["eximbay"]			= "엑심베이";

		if (obj.val() != "goods") {
			alert("결제 수단이 '개별 설정'인 경우에만 사용 가능합니다.");
			return false;
		}
		if(payObj.length == 0){
			alert("사용할 결제 수단을 1개 이상 선택 하세요.");
			return false;
		}
		var pc_text					= "";
		var pc_value				= "";

		for (i=0; i<payObj.length; i++) {
			if(payObj.eq(i).is(":checked") && payObj.eq(i).val() != 'all'){
				if(pc_text == ""){
					pc_text			= possible_pay[payObj.eq(i).val()];
					pc_value		= payObj.eq(i).val();
				}else{
					pc_text			= pc_text + ","+possible_pay[payObj.eq(i).val()];
					pc_value		= pc_value+","+payObj.eq(i).val();
				}
			}
		}

		possible_pay_text			= "(" + pc_text + ")";

		$("input[name='possible_pay_type_hidden']").val(obj.val());
		$("input[name='possible_pay_hidden']").val(pc_value);
		$("#possible_pay_td").html(possible_pay_text);

		closeDialog("possible_pay");

	});

	$("input[name='possible_pay[]']").on("click",function(){
		var obj = $(this);
		var chkPayLay = obj.parent().parent().parent().find("div");
		var chkPayList = chkPayLay.find("input:checkbox");

		if (obj.val() == 'all') {
			chkPayList.each(function() { if(!$(this).prop("disabled")) $(this).prop("checked",obj.is(":checked")); });
		} else {
			if(obj.is(":checked") == false){
				chkPayList.parent().find(".chkall").prop("checked",false);
			}else{
				if(chkPayList.parent().find(":checked").length == (chkPayList.length - chkPayLay.find("input:disabled").length - 1)){
					chkPayList.parent().find(".chkall").prop("checked",true);
				}
			}
		}
	});

	$("input:radio[name=possible_pay_type]").on("click",function(){
		if($(this).val() == "goods" && window.Firstmall.Config.Environment.serviceLimit.H_FR == true){
			getNoServiceAlert();
			$("input[name='possible_pay_type'][value='shop']").prop("checked",true);
			return false;
		}else{
			$("input[name='possible_pay_type_hidden']").val($(this).val());
		}

	});

	$("#possible_pay_setting").trigger("click");

	/* 추가정보 삭제 */
	$("#etcViewTable").on("click", '.etcDel',function(){
		if ($("#etcViewTable tbody tr").length > 1){
			$(this).closest("tr").remove();
			$("#etcViewTable .nothing").removeClass("hide");
			$("#etcViewTable .nothing").addClass("hide");
		}

		if($("#etcViewTable tbody tr").length == 1)
			$("#etcViewTable .nothing").removeClass("hide");
		 
	});

	/* 추가정보 선택시 */
	$("#etcViewTable").on("change", "select[name='selectEtcTitle[]']", function(){
		
		var etcTitle = $(this).next('span');

		if($(this).val() == 'direct') {
			etcTitle.show();
			$(this).removeClass("wp85");
			$(this).addClass("wp40");
		} else {
			etcTitle.hide();
			$(this).removeClass("wp40");
			$(this).addClass("wp85");
		}
		goodsaddinfocode($(this));//추가정보 >> 추가옵션코드추가
	});

	//추가정보 >> 정보값 선택
	$("#etcViewTable").on("change", ".goodsaddinfo", function(){
		$(this).parent().parent().parent().find(".etcContents").val($(this).val());
		$(this).parent().parent().parent().find(".etcContents_title").val($(this).find("option:selected").text());
	});

	$("#star_select").on("click", function(){

		var status	= "";
		if ($(this).hasClass("checked")) {
			$(this).removeClass("checked");
			status	= "none";
		} else {
			$(this).addClass("checked");
			status	= "checked";
		}

		$.ajax({
			type: "get",
			url: "../goods/set_favorite",
			data: "status="+status+"&goods_seq=" + gl_goods_seq,
			success: function(result){
				//alert(result);
			}
		});

	});

	// 상품사진 여러 컷 일괄등록 :: 2020-11-41
	$("#goodsImageTable").on("click", ".batchImageMultiRegist, .batchImageRegist, .eachImageRegist", function(){
		var _type 		= $(this).attr("data-type");
		var idxcnt		= $(".batchImageRegist").length;
		var imgcnt		= goodsObj.imagesCount;
		var uploadUrl 	= "./popup_image";
		var params		= {'no':gl_goods_seq,'division':'all','idx':0};
		var title		= "사진 등록";
		var width		= 550;
		var height		= 580;
		var divLay 		= "set_popup_image_lay";

		// 다중등록(여러장-대표사진 외 6장)
		if(_type == "multi"){
			uploadUrl 		= "./popup_image_multi";
			params			= "no="+gl_goods_seq;
			divLay 			= "set_popup_image_multi_lay";
		// 개별등록(해당 사이즈 1장)
		}else if(_type == "each"){
			
			if(gl_goods_seq == null) {	//등록 전 이미지 수정할 때 null 방지 (2018-06-04 ldb)
				alert('상품을 저장 후 진행하시거나, 상품 사진을 삭제 후 재 등록 해 주시기 바랍니다.');
				return false;
			}

			title 			= "개별 등록";	
			params.division = $("#goodsImageMake input[name='imgKind']").val();
			params.idx 		= $("#goodsImageMake input[name='idx']").val();
			if(params.division == 'view') height = 620;
		// 순번 사진(대표사진 외 6장)
		}else{
			params.idx = $(this).closest("tr").index();
			if(gl_goods_seq == null) {	//등록 전 이미지 수정할 때 null 방지 (2018-06-04 ldb)
				alert('상품을 저장 후 진행하시거나, 상품 사진을 삭제 후 재 등록 해 주시기 바랍니다.');
				return false;
			}
		}
		$.ajax({
			type: "get",
			url: uploadUrl,
			data: params,
			success: function(html){
				$("#"+divLay).html(html);
				openDialog(title, divLay, {'width':width,'height':height,'show':'fade','hide' : 'fade'});
			}
		});

		//window.open('popup_image_multi' + goodsLink,'','width=900,height=450,toolbar=no,titlebar=no,scrollbars=yes,resizeable');
	});

	// 순서변경 및 삭제 :: 2016-05-03 lwh
	$("#goodsImageTable").on("click", ".ImageSort", function(){
		
		$.ajax({
			type: "get",
			url: "./popup_image_sort",
			data: "no="+gl_goods_seq,
			success: function(html){
				$("#set_popup_image_sort_lay").html(html);
				openDialog("순서 변경 및 삭제", "set_popup_image_sort_lay", {'width':850,'height':500,'show':'fade','hide' : 'fade'});
			}
		});

	});

	//동영상등록폼
	$(".batchVideoRegist").on("click",function(){
		var uptype = $(this).attr("uptype");
		if (uptype == 'image') {

			if ( goodsObj.file_key_w && goodsObj.cfg_goods.goods_uccdomain) {
				alert('상품이미지영역의 동영상은 1개까지만 등록가능합니다.\n다시 등록하기 위해서는 [연결해제] 후 재등록해 주세요.');
				 return false;
			}

			var videotrlength = ($("table.videofiles_tables_images tbody").find("tr").length/2);
			if(videotrlength >= 1 ){
				alert('상품이미지영역의 동영상은 1개까지만 등록가능합니다.\n다시 등록하기 위해서는 [연결해제] 후 재등록해 주세요.');
				return false;
			}

		} else {

			if (goodsObj.videototal >= goodsObj.cfg_goods.videototalcut ) {
				alert('상품설명영역의 동영상은 {cfg_goods.videototalcut개}까지만 등록가능합니다.\n다시 등록하기 위해서는 [연결해제] 후 재등록해 주세요.');
				 return false;
			}

			var videotrlength = ($("table.videofiles_tables tbody").find("tr").length/2);
			var videototalcut = '{cfg_goods.videototalcut}';
			if (videotrlength >= videototalcut) {
				alert('상품설명영역의 동영상은 ' + goodsObj.cfg_goods.videototalcut + '개까지만 등록가능합니다.\n다시 등록하기 위해서는 [연결해제] 후 재등록해 주세요.');
				return false;
			}

		}
		window.open('video_upload?no='+gl_goods_seq+'&uptype='+uptype,'VideoPopup','width=550,height=300');
	});


	//동영상 링크태그 보기 videoDialog.js 호출된 페이지에서만 사용하도록 수정 2020-07-03
	if(typeof videoDialog == 'function') {
		$(document).on('click', ".videourlbtn", videoDialog);
	}

	/* 상품상세페이지 상품설명영역 노출 순서변경 */
	$(".tablednd").tableDnD({onDragClass: "dragRow"});
	//$("table.videofiles_tables tbody").sortable({items:'tr'});

	// 상품상세페이지 상품설명영역 선택색상
	$("#videofiles_tables").on("change", '.viewer_uselay', function(){

		if ($(this).is(':checked'))
			$(this).closest('tr').addClass('checked-tr-background');
		else
			$(this).closest('tr').removeClass('checked-tr-background');

	}).change();	

	// 사진 상세 리스트 추가
	$("#goodsImageTable").on("click", "#goodsImageAdd", function(){
		goodsImageAdd();
	});

	//상품승인 -> 미승인시 판매중지자동
	$("input[name='provider_status']").on("click", function(){

		if ($(this).is(':checked') == true && $(this).val() != '1') {
			alert("해당 상품은 '미승인'처리되어 상품 상태는 '판매중지'가 됩니다.");
			$("form[name='goodsRegist'] input[name='goodsStatus'][value='unsold']").attr("checked",true);
		}

	});

	/*
	//상품상태 -> 미승인시 판매중지만가능
	$("input[name='goodsStatus']").on("click", function(){

		if ($("input[name='provider_status']:checked").val() == 0 && $(this).val() != 'unsold') {//미승인시
			alert("해당 상품은 '미승인'처리되어 상품 상태는 '판매중지'가 됩니다.");
			$("form[name='goodsRegist'] input[name='goodsStatus'][value='unsold']").attr("checked",true);
		}

	});
	*/

	//수정불가
	$("form").on("keydown change focusin selectstart", ".input-box-default-text-code", function(){
		$(this).blur();
		return false;
	});

	/* 상품코드 코드생성넣기*/
	$("#optionLayer").on("click", "#goodsCodeBtn", function(){openDialog("기본코드 자동생성", "makeGoodsCodLay", {"width":"400","height":"300"});});
	$('#optionLayer').on('blur', 'input[name="goodsCode"]' , function(){
				 if(typeof this.value != "undefined"){ 
					 $('.goodsCode').html(this.value); 
				}else{
					$('.goodsCode').html(''); 
				}
		});

	// 상품관리 기본값 설정 불러오기
	$(".btn_goods_default_set").on("click", function(){
		var _type 		= $(this).attr("data-type");
		var _title 		= "";
		var _options 	= {'width':420,'height':380,'show':'fade','hide' : 'fade'};
		var _goods_kind	= 'goods';
		if(_type == "option"){
			_title = "옵션 보기 기본 설정";
			_goods_kind = goodsObj.cfg_goods.goods_kind;
		}else if(_type == "relation"){
			_title = "관련 상품 기본 설정";
		}else if(_type == "commonContents"){
			_title = "상품 공통 정보 기본 설정";
			_options.width = 480;
		}
		$.ajax({
			type: "get",
			url: "./option_default_setting",
			data: "goods_kind="+_goods_kind+"&sub_kind="+_type,
			success: function(html){
				$("#set_option_view_lay").html(html);
				openDialog(_title, "set_option_view_lay", _options);
			}
		});
	});
	

	/* 구매자 추가입력 사용 만들기 다이얼로그박스 */
	$("#memberInputMake").on("click", function(){
		if	(!$(this).closest('table').find("input[name='memberInputUse']:checked").val() == "1"){
			//if ($("input[name='frequentlytypeinputoptck']:checked").val()) {
				var add_goods_seq	= $("select[name='frequentlytypeinputopt']").find("option:selected").val();

				if (add_goods_seq <= 0) {
					alert("옵션정보를 가져올 상품을 선택해 주세요!");
					return false;
				}

				var goods_name		= $("select[name='frequentlytypeinputopt']").find("option:selected").text();
				openDialogConfirm('['+goods_name+'] 상품의 <br/>추가입력옵션 정보를 가져오시겠습니까?',400,200,function(){
					inputoption_frequently_load(add_goods_seq);
					},function(){
						openDialog("추가 입력 옵션 생성/수정", "memberInputDialog", {"width":"750","height":"600","show" : "fade","hide" : "fade"});
					});
				//}
		}else{
			openDialog("추가 입력 옵션 생성/수정", "memberInputDialog", {"width":"750","height":"600","show" : "fade","hide" : "fade"});
		}
	});

	$("#frequentlytypeoptbtn").on("click", function() {
		var add_goods_seq	= $("select[name='frequentlytypeinputopt']").find("option:selected").val();
		if (add_goods_seq <= 0) {
			alert("옵션정보를 가져올 상품을 선택해 주세요!");
			return false;
		}
		inputoption_frequently_load(add_goods_seq);
	});
	
	// 추가 입력 옵션 관리
	$("#memberInputSetting").on("click",function(){
		openDialog("추가 입력 옵션 관리", "inpoptionSettingPopup", {"width":"500","height":"540","show" : "fade","hide" : "fade"});
	});

	/* 구매자 추가입력 한줄 추가 */
	$(".addMemberInputMake").on("click",function(){
		var objTr	= $(this).closest('table').find("tr").eq(1);
		var clone	= objTr.clone();
		clone.attr("id","");
		clone.attr("class","memberInput");
		clone.find("button").attr("id","");
		clone.find("span").removeClass("hide");
		clone.find("button").addClass("delMemberInputMake");

		$("div#memberInputDialog table.optionList tbody").append(clone);
		clone.find(".textLimit").show();
		clone.find("input[name='memberInputMakeName[]']").val('');
		clone.find("input[name='memberInputRequire[]']").attr('checked',false);
		clone.find("input[name='memberInputMakeLimit[]']").val(0);
		$("select[name='memberInputMakeForm[]']").on("change",function(){check_memberInputMakeForm($("select[name='memberInputMakeForm[]']").index(this));});

	});

	$("select[name='memberInputMakeForm[]']").on("change",function(){check_memberInputMakeForm($("select[name='memberInputMakeForm[]']").index(this));});

	/* 구매자 추가입력  만들기삭제 */
	$("div#memberInputDialog").on("click", ".delMemberInputMake", function(){$(this).parent().parent().parent().remove();});


	/* 구매자 추가입력 만들기 적용 */
	$("#memberInputMakeApply").on("click", function(){

		for (i=0; i<$("input[name='memberInputMakeName[]']").length; i++) {
			if ($("input[name='memberInputMakeName[]']").eq(i).val() == "") {
				alert('추가 입력명을 입력해 주세요!');
				return false;
			}
		}

		var tag		= get_memberInput_title();
		var target	= $("form div#memberInputLayer");
		target.html(tag);

		tag			= get_memberInput();
		target.find("table").append(tag);
		changeFileStyle();

		var tmp_frequently	= ($("input[name='frequentlyinpbtn']:checked"))?$("input[name='frequentlyinpbtn']:checked").val():0;
		$("input[name='frequentlyinp']").val(tmp_frequently);
		closeDialog("memberInputDialog");
		set_option_select_layout();
	});

	// 추가 입력 옵션 생성
	$("input[name='optionCreateType']").on("click",function(){

		if($(this).val() == "new"){
			//$(this).closest("table").find("tr.newOption").show();
			$(this).closest("table").find("tr.oldOption").hide();
		}else{
			//$(this).closest("table").find("tr.newOption").hide();
			$(this).closest("table").find("tr.oldOption").show();
		}
	});
	
	/* 구매자 추가입력 */
	$("input[name='memberInputUse']").on("click", function(){

		if (goodsObj.inputs && $(this).val() == "") {
			if (!confirm("추가 입력 옵션 작성한 내용이 사라집니다.\n다만, 추가입력옵션 만들기 클릭시 확인하실 수 있습니다.")) {
				$(this).eq(1).attr("checked",true);
				return;
			}
		}

		show_memberInputUse();
		set_option_select_layout();
	});
	
	if (goodsObj.view_layout)
		$("form[name='goodsRegist'] input[name='viewLayout'][value='" + goodsObj.view_layout + "']").attr("checked",true);

	
	if (goodsObj.goods_status)
		$("form[name='goodsRegist'] input[name='goodsStatus'][value='" + goodsObj.goods_status +"']").attr("checked",true);

	if (goodsObj.provider_status)
		$("form[name='goodsRegist'] input[name='provider_status'][value='" + goodsObj.provider_status + "']").attr("checked",true);
	else
		$("form[name='goodsRegist'] input[name='provider_status'][value='0']").attr("checked",true);

	
	if (goodsObj.string_price_use) {
		$("form[name='goodsRegist'] input[name='stringPriceUse'][val='"+goodsObj.string_price_use+"']").attr("checked",true);
		show_stringPrice();
	}

	$("input[name='minPurchaseLimit']").on('change', function(){
		if (this.value == 'limit') {
			$('input[name="minPurchaseEa"]').attr('disabled', false);
			var minPurchaseEa	= $('input[name="minPurchaseEa"]').val();
			if (minPurchaseEa < 2)
				$('input[name="minPurchaseEa"]').val(2);

		}else {
			$('input[name="minPurchaseEa"]').attr('disabled', true);
			$('input[name="minPurchaseEa"]').val(1);
		}
	});
	$("input[name='minPurchaseLimit']:checked").trigger('change');


	$("input[name='maxPurchaseLimit']").on('change', function(){
		var minPurchaseEa	= parseInt($('input[name="minPurchaseEa"]').val(), 10);
		var maxPurchaseEa	= parseInt($('input[name="maxPurchaseEa"]').val(), 10);

		if (this.value == 'limit') {
			$('input[name="maxPurchaseEa"]').attr('disabled', false);
			if (minPurchaseEa > maxPurchaseEa)
				$('input[name="maxPurchaseEa"]').val(minPurchaseEa + 1);
		}else {
			$('input[name="maxPurchaseEa"]').attr('disabled', true);
			$('input[name="maxPurchaseEa"]').val(minPurchaseEa + 1);
		}
	});
	$("input[name='maxPurchaseLimit']:checked").trigger('change');


	//if (goodsObj.max_purchase_limit)
	//	$("form[name='goodsRegist'] input[name='maxPurchaseLimit'][value='" + goodsObj.max_purchase_limit + "']").attr("checked",true);

	//if (goodsObj.max_purchase_order_limit)
	//	$("form[name='goodsRegist'] select[name='maxPurchaseOrderLimit'] option[value='" + goodsObj.max_purchase_order_limit + "']").attr("selected",true);

	if (goodsObj.member_input_use == '1') {
		$("form[name='goodsRegist'] input[name='memberInputUse'][value='1']").attr("checked",true);
		show_memberInputUse();
	}

	if (goodsObj.shipping_policy)
		$("input[name='shippingPolicy'][value='" + goodsObj.shipping_policy + "']").attr("checked",true);


	if (goodsObj.goods_shipping_policy)
		$("input[name='goodsShippingPolicy'][value='" + goodsObj.goods_shipping_policy + "']").attr("checked",true);

	if (goodsObj.shipping_weight_policy)
		$("input[name='shippingWeightPolicy'][value='" + goodsObj.shipping_weight_policy + "']").attr("checked",true);


	/*
	UI 변경으로 아래 코드는 필요없음.
	if (goodsObj.info_seq) {

		$("form[name='tmpContentsFrm'] select[name='info_select'] option[value='" + goodsObj.info_seq + "']").attr("selected",true);

		if($("form[name='tmpContentsFrm'] select[name='info_select'] option:selected").val())
			$("input[name='info_name']").attr("readonly",true).val($("form[name='tmpContentsFrm'] select[name='info_select'] option:selected").text());

	} else if (batchModify === false && !gl_goods_seq && goodsObj.common_info_seq) {

		$("form[name='tmpContentsFrm'] select[name='info_select'] option[value='" + goodsObj.common_info_seq + "']").attr("selected",true);

		if($("form[name='tmpContentsFrm'] select[name='info_select'] option:selected").val())
			$("input[name='info_name']").attr("readonly",true).val($("form[name='tmpContentsFrm'] select[name='info_select'] option:selected").text());
	}
	*/
	
	/*
	if (goodsObj.editor_view == 'Y') {
		// 상품관리 기본값 설정 에디터 보기 사용으로 설정시 표시
		// 상품 UI 수정으로 인한 에디터 미사용 고정 :: 2016-04-28 lwh
		$("#goodscontentsbtn > button").trigger("click");
		$("#mobilecontentsbtn > button").trigger("click");
		$("#commoncontentsbtn > button").trigger("click");
	}
	*/

	$("input[name$='relation_type'], input[name$='relation_seller_type']").on("click",function(){
		var _type 		= $(this).attr("data-type");
		var goodsAuto 	= "relationGoodsAutoContainer";
		var goodsSelect = "relationGoodsSelectContainer";
		if(_type == "seller"){
			goodsAuto 	= "relationSellerGoodsAutoContainer";
			goodsSelect = "relationSellerGoodsSelectContainer";
		}

		if($(this).is(":checked") == true){
			if($(this).val() == "AUTO"){
				$("#"+goodsAuto).show();
				$("#"+goodsSelect).hide();
			}else{
				$("#"+goodsAuto).hide();
				$("#"+goodsSelect).show();
			}
		}
	});

	// 관련상품/판매자 인기상품 : 자동/직접선정 선택
	if (goodsObj.relation_type) {
		$("input[name='relation_type'][value='" + goodsObj.relation_type + "']").attr("checked",true).trigger("click");
	}
	if (goodsObj.relation_seller_type){
		$("input[name='relation_seller_type'][value='" + goodsObj.relation_seller_type + "']").attr("checked",true).trigger("click");
	}

	default_img();
	
	////////////////////////////////////////////////////////////////
	// 추가입력옵션 주문 단계별 설정 시작
	var individual_refund	= (goodsObj.individual_refund == 1) ? 1 : 0;
	$('#individual_refund_1').hide();
	$('#individual_refund_0').hide();
	$('#individual_refund_' + individual_refund).show();
	
	// 취소시
	$("input[name='individual_refund'][value='" + individual_refund + "']").attr("checked",true);
	if(individual_refund != 1) $("input[name='individual_refund_inherit']").attr("disabled",true);

	$("input[name='individual_refund']").on("click", function(){
		if($("input[name='individual_refund'][value='1']").is(":checked")){
			$("input[name='individual_refund_inherit']").removeAttr("disabled");
		}else{
			$("input[name='individual_refund_inherit']").attr("disabled",true);
		}
	});

	$("input[name='individual_export']").on("click", function(){
		$("input[name='individual_return'][value='"+$("input[name='individual_export']:checked").val()+"']").attr("checked",true);
	});

	$("input[name='individual_return']").on("click", function(){
		$("input[name='individual_export'][value='"+$("input[name='individual_return']:checked").val()+"']").attr("checked",true);
	});
	
	if (goodsObj.individual_refund_inherit == 1) {
		$('#individual_refund_inherit_show').show();
		$("input[name='individual_refund_inherit']").attr("checked", true);
	} else {
		$('#individual_refund_inherit_show').hide();
		$("input[name='individual_refund_inherit']").attr("checked", false);
	}

	var individual_export	= (goodsObj.individual_export == 1) ? 1 : 0;
	$('#individual_export_1').hide();
	$('#individual_export_0').hide();
	$('#individual_export_' + individual_export).show();
	$("input[name='individual_export'][value='" + individual_export + "']").attr("checked",true);

	var individual_return	= (goodsObj.individual_return == 1) ? 1 : 0;
	$('#individual_return_1').hide();
	$('#individual_return_0').hide();
	$('#individual_return_' + individual_return).show();
	$("input[name='individual_return'][value='" + individual_return + "']").attr("checked",true);

	if (batchModify === false && gl_service_code =='P_FREE') {
		$("input[name='individual_refund_inherit']").removeAttr("checked").attr("disabled",true);
		$("input[name='individual_refund'][value='1'],input[name='individual_export'][value='1'],input[name='individual_return'][value='1']").attr("checked",true);
		$("input[name='individual_refund'][value='0'],input[name='individual_export'][value='0'],input[name='individual_return'][value='0']").attr("disabled",true);
	}
	// 추가입력옵션 주문 단계별 설정 종료

	// 공통 정보 선택
	$("input[name='r_info_tmp']").on("click",function(){

		var chkValue = $("input[name='r_info_tmp']:checked").val();

		if(chkValue == '' || typeof chkValue == "undefined"){
			chkValue = 'default';
			$(this).find("input[value='default']").prop("checked",true);
		}

		$("input[name='r_info']").val(chkValue);
		// 기본 정보
		if(chkValue == "default"){
			$(".detail").hide();

		// 신규 등록
		}else if(chkValue == "create_info"){
			// 상품 공통 정보 갯수 최대치 여부 확인
			if(getGoodCommonInfoCheck()) {
				$("input[name='r_info_tmp'][value='loading_info']").prop('checked',true).trigger("click");
				return;
			}

			$("input[name='info_name']").attr("readonly",false).val('');

			$("#view_textarea").val('');
			Editor.switchEditor($("#view_textarea").data("initializedId"));
			Editor.modify({"content" : " "});
			$(this).closest("table").find(".contents_view").html('');

			$("input[name='info_select_seq']").val('');
			$(".view_common_info").show();
			$(".detail").show();
			$(".s_info").hide();
		// 기존 정보 불러오기		
		} else if (chkValue == "loading_info") {

			$("select[name='info_select_tmp']").trigger("change");

			$(".view_common_info").hide();
			$(".detail").show();
		}
	});

	chg_common_info_list('new');	// 공통 정보 selectbox 리스트업

	if(gl_common_info_cfg == '') gl_common_info_cfg = 0;	
	
	if( parseInt(gl_common_info_cfg) > 0 || parseInt(gl_common_info_goods) > 0 ) {
		//radio 기존 정보 불러오기 
		$("input[name='r_info_tmp'][value='loading_info']").prop('checked',true).trigger("click");
	} else {
		// radio 기존 정보 불러오기 
		$("input[name='r_info_tmp'][value='create_info']").prop('checked',true);
	}

	// 공통 정보 : 기존 정보 불러오기 SELECT 
	$("select[name='info_select_tmp']").on("change", function(){
		var obj 	= $("select[name='info_select_tmp'] option:selected");
		var val 	= obj.val();
		if(val == "" || val==1 || val==3) {
			$("#info_del").hide();
		}else{
			$("#info_del").show();
		}
		$("input[name='info_select_seq']").val(val);
		if(!$(this).val()){
			$("input[name='info_name']").attr("readonly",false).val(''); 
			return;
		}
		setInfoSelectContent(obj);
	});

	// 적용된 이벤트
	$(".btnViewEvent").on("click",function(){
		if(window.Firstmall.Config.Environment.serviceLimit.H_NFR == true){
			refererDiscountRows(); 	//유입경로 할인
			giftRows();				// 사은품
		}
		openDialog("적용 된 이벤트", "eventDetailViewLay", {"width":"950","height":"600","show" : "fade","hide" : "fade"});
	});
	
	if (goodsObj.imageLabel)
		$("input[name='goodsImgLabel']").val(goodsObj.imageLabel);


	$("#memberInputPreview").on("click", function(){
		var optCnt = $("input:[name='memberInputForm[]']").length;
		var height = 100;
		if(optCnt){
			var html = "<div class='contents' style='border:1px #aaaaaa solid;background-color:#eeeeee;'>";
			html = "<table class=\"goods_option_table\" width=\"100%\" cellpadding=\"0\" cellspacing=\"5\" border=\"0\">";

			for(var i=0;i<optCnt;i++){
				html += "<tr><th style='text-align:left;'>"+$("input:[name='memberInputName[]']").eq(i).val()+"</th></tr>";
				html += "<tr><td>";
				var value = $("input:[name='memberInputForm[]']").eq(i).val();
				if(value=='edit'){
					html += "<textarea style='width:300px;' rows='3'></textarea>";
					height += 80;
				}else if(value=='file'){
					html += "<input type='file' class='line' style='width:300px;'>";
					height += 50;
				}else{
					html += "<input type='text' class='line' style='width:300px;'			 style='width:300px;'>";
					height += 50;
				}
				html += "</td></tr>";
			}

			html += "<tr>";
			html += "</tr>";

			html += "</table></div>";
			html += "<div class=\"footer\">";
			html += "	<button type=\"button\" class=\"resp_btn v3 size_XL\" onclick=\"closeDialog('popPreviewOpt')\">취소</button>";
			html += "</div></div>";

			$("#popPreviewOpt").html(html);
			openDialog("추가입력 미리보기", "popPreviewOpt", {"width":"370","height":height,"show" : "fade","hide" : "fade"});
		}
	});


	$("input[name='relation_count_w']").on("keyup",function(){relation_count_chk();});
	$("input[name='relation_count_h']").on("keyup",function(){relation_count_chk();});


	$("#manager_copy_btn").on("click", function(){
		var goods_seq 	= $(this).attr("goods_seq");
		var gift 		= $(this).attr("data-gift");
		var func 		= "copy_goods('"+goods_seq+"', 'regist');";

		var goods_type = "상품";
		if(typeof gift != "undefined" && gift === 'true') {
			goods_type = "사은품";
		}

		confirm_first_goods(gl_first_goods_date,gl_basic_currency,gl_basic_currency_hangul,gl_basic_currency_nation,'이 '+goods_type+'을 복사해서 '+goods_type+'을 등록하시겠습니까?',func);
	});


	// GOODS
	$(document).on("keydown", "Ctrl+s", function(){
		goods_save('view');
		return false;
	});

	$("input:text").on("keydown", "Ctrl+s", function(){
		goods_save('view');
		return false;
	});

	// 추가혜택 통합설정
	$("#goods_benefits_btn").on("click", function(event){
		$.ajax({
			type: "get",
			url: "../goods/benefits_info",
			data: "goods_seq={goods.goods_seq}&socialcpuse={socialcpuse}",
			success: function(result){
				$("#popup_benefits").html(result);
			}
		});
		openDialog("추가 혜택  통합 설정", "popup_benefits", {"width":"1000","height":"340","show" : "fade","hide" : "fade"});
		event.preventDefault();
		return false;
	});

	/* 화면보기 버튼 */
	if(gl_operation_type != "light"){
		$("#viewGoods").closest("li").on("mouseenter", function(){
			$("ul.gnb-subnb",this).stop(true,true).slideDown('fast');
		}).on("mouseleave", function(){
			$("ul.gnb-subnb",this).stop(true,true).slideUp('fast');
		});
	}else{
		$("#viewGoods").on("click", function(){
			window.open("/goods/view?no=" + gl_goods_seq,'','');
		});
	}

	/* 상품상태 설명색상 */
	/*
	$("input[name='goodsStatus']").on("change", function(){

		if ($(this).is(":checked")) {
			$(".goodsStatusDesc").css('opacity',0.5);
			$(this).closest('tr').find(".goodsStatusDesc").css('opacity',1);
		}

	}).change();
	*/

	// 재고에 따른 판매 - 개별 설정
	$("input[name='runout']").on("click",function(){
		if($(this).is(":checked") && $(this).val() == "ableStock"){
			$(".ableStock_sub").removeClass("hide");
		}else{
			$(".ableStock_sub").addClass("hide");
		}
	});

	/* 상품상태 변경시 가용재고체크 */
	$("input[name='goodsStatus']").on("change", function(){		

		var provider_status = $("input[name='provider_status']:checked").val();
		if(typeof $("input[name='provider_status']").val() == "undefined") provider_status = '';

		if (eval(gl_provider_seq) > 1 && provider_status == 0 && $(this).val() != 'unsold') {//미승인시
			alert("해당 상품은 '미승인'처리되어 상품 상태는 '판매중지'가 됩니다.");
			$("form[name='goodsRegist'] input[name='goodsStatus'][value='unsold']").attr("checked",true);
		}else{
			chk_stockDesc();
		}
	});


	$("#optionLayer input[name='stock[]']").on("keyup", function(){
		setAbleStock($(this));
	});

	$("#optionLayer input[name='badstock[]']").on("keyup", function(){
		setAbleStock($(this));
	});

	var setAbleStock = function (obj) {

		if($("input[name='optionUse']:checked").val() == "") {
			var ablestock 	= 0;
			var stock 		= obj.closest("tr").find("input[name='stock[]']").val();
			var badstock 	= obj.closest("tr").find("input[name='badstock[]']").val();
			var unUsableStock 	= obj.closest("tr").find("input[name='unUsableStock[]']").val(); // 출고예약량(주문접수(결제확인)~출고준비상태 수량)

			if(stock == '') {
				stock = 0;
			} else {
				stock = eval(stock);
			}
			if(badstock == '') {
				badstock = 0;
			} else {
				badstock = eval(badstock);
			}

			if(unUsableStock == '') {
				unUsableStock = 0;
			} else {
				unUsableStock = eval(unUsableStock);
			}
			// 가용재고 = 재고 - 불량재고 - 출고예약량
			ablestock = stock - badstock - unUsableStock;
			obj.closest("tr").find(".optionUsableStock").html(ablestock);
		}

	}

	/* 상품상태별 이미지 세팅*/
	$("#goodsStatusImage").on("click",function(e){
		$('#popGoodsStatusImage').empty();

		$.ajax({
			type: "get",
			url: "../goods/goods_status_images_setting",
			success: function(result){
				$("#popGoodsStatusImage").html(result);
			}
		});
		openDialog("상품 상태별 이미지 세팅", "popGoodsStatusImage", {"width":"900","height":"630","show" : "fade","hide" : "fade"});
		e.preventDefault();
		return false;
	});

	/* 선택된 상품상태별이미지 변경창 출력 */
	$(".goodsStatusImage").on("click",function(){
		var codecd = $(this).attr('codecd');
		$("input[name='goodsStatusImageCode']").val(codecd);
		$(".nowGoodsStatusImage").html("<img src='"+$(this).attr('src')+"' />");
		closeDialog("popGoodsStatusImageChoice");
		openDialog("이미지 변경", "popGoodsStatusImageChoice", {"width":"570","height":"250","show" : "fade","hide" : "fade"});
	});

	// 재고에 따른 판매 설정
	$("input[name='runout_type']").on("change",function(){
		if($(this).val() == "goods" && window.Firstmall.Config.Environment.serviceLimit.H_FR == true){
			getNoServiceAlert();
			$("input[name='runout_type'][value='shop']").prop("checked",true);
			return false;
		}
		check_runout_type();
		//check_runout();
		if($(this).val() == "goods"){			//개별설정
			$(".runout_setting").parent().show();
		}else{									// 통합설정
			$(".runout_setting").parent().hide();
			check_runout();
		}
	});
	
	/*
	$("input[name='runout']").on("change",function(){
		check_runout();
	});
	$("input[name='ableStockLimit']").on("blur",function(){
		check_runout();
	});
	*/

	if(batchModify === false) {
		check_runout_type();
		check_runout();
		
		if (gl_goods_seq)
			chk_stockDesc();	
	}

	$("#btn_option_infomation").on("click", function(){
		openDialog("옵션이란?", "option_infomation", {"width":"760","height":"760","show" : "fade","hide" : "fade"});
	});

	//구매 대상 제한 : 가격 디스플레이
	$('button#popStringPriceBtn').on("click",function(){
		var urlstr = '';
		$("input[name='stringPriceUse'][value='y']").prop("checked",true);
		if($('#popStringPrice').html()==''){
			if(batchModify === false) {
				urlstr = '?no='+gl_goods_seq;
			}
			$.ajax({
				type: "get",
				url: "../goods/popup_string_price" + urlstr,
				success: function(result){
					$("#popStringPrice").html(result);
				}
			});
		}
		check_string_price();
		openDialog("구매 가능 대상 설정", "popStringPrice", {"width":"800","height":"660","show" : "fade","hide" : "fade"});
	});

	$("#popStringPrice").on("change", '.string_link_select', function(){
		if ($(this).val() == 'direct'){
			$('input[name="' + $(this).attr("name") + '_url"').attr('disabled', false);
			$(this).closest("td").find('select[name="' + $(this).attr("name") + '_target"').show();
		}else if($(this).val() == "none"){
			$('input[name="' + $(this).attr("name") + '_url"').attr('disabled', true);
			$(this).closest("td").find('select[name="' + $(this).attr("name") + '_target"').hide();
		}else{
			$('input[name="' + $(this).attr("name") + '_url"').attr('disabled', true);
			$(this).closest("td").find('select[name="' + $(this).attr("name") + '_target"').show();
		}
	});

	$("#popStringPrice").on("click", '.string_use_radio', function(){check_string_price(this);});
	
	print_string_price('get');

	$("#btnGoodsSubInfoDescrioption").on("click", function(){
		if($("input[name='subInfoDesc[]']").length == 0){
			alert("상품정보제공고시 항목을 먼저 추가해 주세요.");
			return false;
		}
		$("input[name='subInfoDesc[]']").val("상세설명참조");
		/*
		if ($(this).is(":checked")){
			$("input[name='subInfoDesc[]']").val("상세설명참조");
		} else{
			$("input[name='subInfoDesc[]']").val("");
		}
		*/
	});
	
	/*
	// 추가입력옵션 자주쓰는 상품의 추가입력옵션 사용 체크 시 이벤트
	$("input[name='frequentlytypeinputoptck']").on("click", function(){
		if ($(this).attr("checked") == "checked") {
			$("#frequentlytypeinputoptlay").removeAttr("disabled");
			$("#frequentlytypeinputoptlay").removeClass("gray");
		} else {
			$("#frequentlytypeinputoptlay").attr("disabled","disabled");
			$("#frequentlytypeinputoptlay").addClass("gray");
		}
	});
	*/


	//상품 기본코드 안내
	$(".goods_code_helper").on("click",function(){openDialog("안내) 상품코드 자동생성", "goods_code_helper_lay", {"width":"1130","height":"355","show" : "fade","hide" : "fade"});});

	// 상품 수정시 자동 미승인 처리 기준 안내
	$(".goods_modify_helper").on("click",function(){openDialog('상품 수정 시 자동 미승인 처리 기준', 'unable_provider_status', {'width':'680','height':'260'});});

	//안내)특수 정보 활용
	$("#btn_goods_special_list").on("click",function(){openDialog("특수 정보 활용 안내", "special_newlist", {"width":"760","height":"826","show" : "fade","hide" : "fade"});});

	//티켓상품 > 유효기간 시작 전 카드결제 자동취소 안내
	$("#btn_socialcp_cancel_card").on("click",function(){openDialog("안내) 카드결제 자동취소", "lay_socialcp_cancel_card", {"width":"320","height":"180","show" : "fade","hide" : "fade"});});

	/* 티켓상품 - 취소(환불) 설정 추가 */
	$("#socialcpcancelViewTable button#socialcpcancelAdd").on("click",function() {

		var socialcp_cancel_type	= $("input[name='socialcp_cancel_type']:checked").val();
		if( socialcp_cancel_type == 'payoption' ) {//결제확인후 100% 취소(환불)

			var newClone 	= $(this).closest("table").find("tbody tr").eq(0).clone();
			newClone.find("td").eq(0).html('<button type="button" onClick="socialcpcancelDel(this)" class="btn_minus"></button>');
			newClone.find("td span.socialcp_cancel_percent_title").removeClass("hide");
			
			var trObj		= $("#socialcpcancelViewTable tbody");

			if ($("#socialcpcancelViewTable tbody tr").length > 9) {
				alert("최대 10개까지만 가능합니다.");
				return false;
			}
			
			trObj.append(newClone);
			apply_input_style(".socialcpcancelViewTabletr");
		}

	});

	/* 티켓상품 - 취소(환불) 설정 삭제 */
	/*
	$("#socialcpcancelViewTable button.socialcpcancelDel").on("click",function(){
		if ($("#socialcpcancelViewTable tbody tr").length > 1)
			$(this).parents(".socialcpcancelViewTabletr").remove();
	});
	*/

	$("input[name='socialcp_input_type']").on("click", function(){
		alert("티켓1장의 값어치 설정변경시 옵션의 티켓1장의 값어치를 다시 설정해 주세요!");
		socialcpinputtype();
	});

	$("input[name='socialcp_use_return']").on("click", function() {

		var socialcp_use_return = $("input[name='socialcp_use_return']:checked").val();

		if (socialcp_use_return == '1') {
			$(".socialcp_use_return th span").addClass('required_chk');
			$(".socialcp_use_returnlay").removeAttr('disabled');
		} else {
			$(".socialcp_use_return th span").removeClass('required_chk');
			$(".socialcp_use_returnlay").attr('disabled', 'disabled');
			$(".socialcp_use_returnlay").val('');
			$(".socialcp_use_returnlay").removeAttr('checked');
		}

	});

	$("input[name='socialcp_cancel_type']").on("click", function() {

		$("tr.socialcpcancelViewTabletr").find("input").attr('disabled', 'disabled');
		$("div.socialcpcancelViewdiv").find("input").attr('disabled', 'disabled');
		$("#socialcp_cancel_day_0").attr('disabled', 'disabled');

		var socialcp_cancel_type = $("input[name='socialcp_cancel_type']:checked").val();
		
		switch (socialcp_cancel_type) {
			case	'pay' :
				//결제확인후 100% 취소(환불)
				$("#socialcp_cancel_day_0").removeAttr('disabled');
				$(".socialcp_cancel_type_pay").show();
				$(".socialcp_cancel_type_dayoption").hide();
				break;

			case	'option' :
				//유효기간 100% 취소(환불)
				$(".socialcp_cancel_type_pay").hide();
				$(".socialcp_cancel_type_dayoption").hide();
				break;

			default :
				$(".socialcp_cancel_type_pay").hide();
				$(".socialcp_cancel_type_dayoption").show();
				$("tr.socialcpcancelViewTabletr").find("input").removeAttr('disabled');
				$("div.socialcpcancelViewdiv").find("input").removeAttr('disabled');

				if ($("input[name='socialcp_cancel_payoption']").is(":checked"))
					$("input[name='socialcp_cancel_payoption_percent']").removeAttr('disabled');
				else
					$("input[name='socialcp_cancel_payoption_percent']").attr('disabled', 'disabled');
		}

	});

	$("input[name='socialcp_cancel_payoption']").on("click", function() {

		if ($(this).is(":checked"))
			$("input[name='socialcp_cancel_payoption_percent']").removeAttr('disabled');
		else
			$("input[name='socialcp_cancel_payoption_percent']").attr('disabled', 'disabled');

	})


	$("#socialcp_event_tmp").on("click", function(){
		if ($(this).is(":checked"))
			var socialcpevent	= 1;
		else
			var socialcpevent	= 0;

		$("input[name='socialcp_event']").val(socialcpevent);
	});

	$("input[name='goodsShippingPolicy']").on("change",function(){check_goodsShippingPolicy();});
	//$("select[name='shippingPolicy']").bind("change",function(){check_goodsShippingPolicy();});

	check_goodsShippingPolicy();


	$("input[name='coupon_serial_type']").on("click", function() {

		if($(this).is(":checked") && $(this).val() == "n"){
			$("tr.excelupload").show();
		}else{
			$("tr.excelupload").hide();
		}
		check_runout();

	});

	$("#coupon_serial_upload").on("click", function(){

		if ($("input[name='coupon_serial_type'][value='n']").is(":checked")) 
			openDialog("엑셀 등록", "coupon_serial_upload_lay", {"width":500,"height":430});

	});

	$(".sum_number").on("click", function(){
		if ($("input[name='coupon_serial_upload']").val()) {
			var listData	= $("input[name='coupon_serial_upload']").val().split(',');
			var listCnt		= listData.length;
			var data		= '';
			var listHTML	= '';
			for (var i=0; i < listCnt; i++) {
				data	= listData[i].split('|');
				if (data[0]) {
					listHTML	+= '<tr>';
					listHTML	+= '<td align="center">'+data[0]+'</td>';
					if (data[1] == 'a') {

						if (data[2])
							listHTML	+= '<td class="center">발송완료</td>';
						else
							listHTML	+= '<td class="center">발송대기</td>';

					} else if (data[1] == 'y') {
						listHTML	+= '<td class="center">등록가능</td>';
					} else {
						listHTML	+= '<td class="center">등록불가</td>';
					}

					listHTML	+= '</tr>';
				}
			}

			$("#coupon_serial_list").html(listHTML);

			openDialog("외부 제휴사 시스템 티켓 목록", "coupon_serial_list_lay", {"width":400,"height":750});
		}
	});

	/* 관련상품 조건 선택 버튼 */
	$("button.relationCriteriaButton").on("click",function(){
		var displayResultId			= $(this).attr('dp_id');
		var auto_condition_use_id	= $(this).attr('use_id');
		var criteria				= $("#"+displayResultId).val();
		var kind					= $(this).attr('kind');
		var goods_seq				= $(this).attr('goods_seq');
		var callpage				= $(this).attr('data-callpage');
		open_criteria_condition(displayResultId,auto_condition_use_id,criteria,kind,goods_seq,callpage);
	});

	/* 상품 검색 버튼 */
	/*$("button.relationGoodsButton").on("click",function(){
		var displayResultId = $(this).attr('dp_id');
		open_goods_search(displayResultId);
	});
	*/
	// 상품선택
	$(".btnSelectGoods").on("click",function(){

		var displayResultId = $(this).attr('dp_id');
		var dataType 		= $(this).attr('data-type');
		
		var params = {
				'goodsNameStrCut'	: 30,
				'selector'			: "#"+displayResultId+'SelectContainer',
				'select_goods'		: displayResultId,
				'service_h_ad'		: window.Firstmall.Config.Environment.serviceLimit.H_AD,
				'parentCode'		:'goods',
			};

		// 관련상품이 아닌 경우에만 해당 입점사 상품으로 고정
		if(displayResultId != 'relationGoods'){
			var select_provider = 1;
			if(window.Firstmall.Config.Environment.serviceLimit.H_AD == true){
				select_provider = $("form[name='goodsRegist'] input[name='provider_seq']").val();;
			}
			params.selectProviders =  select_provider;
		}
		if($(this).attr("data-selleradminMode") == 'y') params.sellerAdminMode = true;

		gGoodsSelect.open(params);
	});
	//선택삭제
	$(".btnSelectGoodsDel").on("click",function(){
		gGoodsSelect.select_delete('chk',$(this));
	});

	if (batchModify === false && socialcpuse_flag) {
		/* 티켓상품그룹 찾기버튼 */
		$("button#coupon_group_search").on("click", function() {
			var group_seq		= ($("#social_goods_group").val())?$("#social_goods_group").val():'0';
			var provider_seq	= $("form[name='goodsRegist'] input[name='provider_seq']").val();
			var provider_name;

			if(provider_seq){
				if(provider_seq == 1){
					provider_name ='본사';
				}else{

					if (gl_provider_name)
						provider_name = gl_provider_name;
					else
						provider_name =  $("input[name='provider_name']").val();
				}
				addFormDialog('./social_goods_group?type=write&sel_group_seq='+group_seq+'&provider_seq='+provider_seq, '700', '490', '['+provider_name+']티켓상품그룹 찾기 ','false','resp_btn v3 size_XL');
			}else{
				openDialogAlert("입점사를 선택해주세요.",400,150,function(){
					$("form[name='goodsRegist'] select[name='provider_seq_selector']").next(".ui-combobox").children("input").eq(0).focus();
				});
				return false;
			}
		});

		//티켓상품그룹 선택시
		$("body").on("click", ".social_goods_group_sel", function(){
			var social_goods_group_seq	= $(this).attr("social_goods_group_seq");
			var social_goods_group_name	= $(this).attr("social_goods_group_name");
			$("#social_goods_group").val(social_goods_group_seq);
			$(".social_goods_group_name").val(social_goods_group_name);
			$('#dlg').dialog('close');
		});

		// 티켓상품 취소(환불) 설명예시화면
		$("button#btn_ticket_goods_refund_helper").on("click", function(){
			openDialog("설명 예시", "ticket_goods_refund_helper", {"width":"590","height":"390"});
		});

		// mapView 기능 추가 :: lwh 2014-03-31
		if (goodsObj.isAddr == 'Y')
			$(".mapView").attr('disabled',false);
		else
			$(".mapView").attr('disabled',true);
	}

	//빅데이터 설정값 불러오기
	if(typeof gift == "undefined" &&  batchModify === false) {
		setCriteriaDescription();
		setCriteriaDescription_upgrade('goodsview');
		setCriteriaDescription_bigdata('goodsview');	
	}


	// 판매마켓 설정
	$(".set-openmarket").on("click", function(){
		$.ajax({
			type: "get",
			url: "../openmarket/set_use_mall",
			data: "openType=div&orgvalinputname=openmarket_send_mall_id[]&resfunc=set_send_mall",
			success: function(result) {
				$("#openmarket_lay").html(result);
				openDialog("판매마켓별 상품정보 전송 여부 설정", "openmarket_lay", {"width":"600","height":"450"});
			}
		});
	});

	// 연동 업체 설정 확인
	$(".chk-linkage-company-mall").on("click", function(){

		var resultHTML	= '';
		$.getJSON('../openmarket/chk_linkagemall_status?goodsSeq=' + gl_goods_seq, function(result) {
			resultHTML	= '<div>해당 상품은 연동 업체 관리환경에서는</div>';
			if (result.status) {
				resultHTML	+= '<div><span class="red">';
				var data	= result.mall;
				var d		= 0;
				for	(var mallcode in data){
					if	(d > 0)	resultHTML	+= ', ';
					resultHTML	+= data[mallcode];
					d++;
				}
				resultHTML	+= '</span>에 판매하겠다고 설정되어 있습니다.</div>';
			}else{
				resultHTML	+= '<div class="red">판매마켓이 설정되어 있지 않습니다.</div>';
				resultHTML	+= '<div>연동 업체 관리환경에서 해당 상품의 판매마켓을 설정해 주십시오.</div>';
			}
			$("#chk_linkagemall_lay").html(resultHTML);
			openDialog("연동 업체 설정 확인하기", "chk_linkagemall_lay", {"width":"500","height":"150"});
		});
	});
	

	/* 이미지 호스팅 일괄 업데이트 팝업*/
	$("#openmarketimghostingftp").on("click",function(){
		openDialog("이미지 호스팅 설정", "openmarketimghostinglay", {"width":550,"height":560});
	});

	//원본이미지 삭제여부
	$("#imagedelete").on("click",function(){
		if ($("#imagedelete").is(':checked')) {
			openDialogConfirm('원본이미지를 삭제하겠습니까?<br/>삭제된 이미지는 복구 되지 않습니다!',500,160,function(){
				$("#imagedelete").attr("checked",true);
			},function(){
				$("#imagedelete").removeAttr("checked");
			});
		}
	});

	// 상품 설명 :: 복사
	$("input[name='mobile_contents_copy']").on("click",function(e,param){
		if(typeof param == "undefined") param = "";
		if(param == "Y" || ($(this).is(":checked") && $(this).val() == "Y")){
			if(param == "") contents_copy();
			$(".mobileContentView").hide();
			$("input[name='mobile_contents_copy'][value='Y']").prop("checked",true);
			$("#mobile_contents_view").show();
		}else{
			$(this).find("input[value='N']").prop("checked",true);
			$(".mobileContentView").show();
		}
	});

	if(goodsObj.mobile_contents_copy){
		$("input[name='mobile_contents_copy'][value='"+goodsObj.mobile_contents_copy+"']").prop("checked",true).trigger("click",[goodsObj.mobile_contents_copy]);
	}
	

	/* 이미지 호스팅 일괄업데이트 */
	// 반응형 조건 추가 :: 2019-03-20
	$("#imagehostinggoodssave").on("click",function(){
		var hostname = $("#imghostinghostname").val();
		var username	= $("#imghostingusername").val();
		var password	= $("#imghostingpassword").val();
		var hostinguse	= $("#imghostinguse").val();
		var imagehostingDomainType	= $("input[name='imagehostingDomainType']:checked").val();
		var targettxt	= typeof gl_operation_type != 'undefined' && gl_operation_type == 'light' ? '반응형 전용' : 'PC/테블릿용';

		if (!hostname || !username || !password) {
			alert("이미지 호스팅 FTP 정보를 정확히 입력해 주세요!");
			return;
		}


		openDialogConfirm(targettxt + ' 상품설명정보를 변경하겠습니까?<br/>변경된 데이터는 복구 되지 않습니다!',500,160,function(){
			
			$("#imghostingsavegoods_seq").val(gl_goods_seq);
			$("#imghostingsavemode").val('onlyone');
			contentObj		= typeof gl_operation_type != 'undefined' && gl_operation_type == 'light' ? $("#mobile_contents") : $("#goodscontents");
			contentPrmName	= typeof gl_operation_type != 'undefined' && gl_operation_type == 'light' ? 'mobile_contents' : 'contents';


			var initializedId = $(contentObj).data('initializedId');
			Editor.switchEditor(initializedId);
			//var goodscontents = Editor.getContent(); // 에디터미사용 :: 2016-05-04 lwh
			var goodscontents = $(contentObj).val();
			var mobile_contents	= $("#mobile_contents").val();
			if(!goodscontents || goodscontents.toLowerCase() == "<p>&nbsp;</p>"  || goodscontents.toLowerCase() == "<p><br></p>" ){
				alert(targettxt + ' 설명을 입력해 주세요.');
				$(contentObj).focus();
				return false;
			}

			$.ajax({
				type: "post",
				'dataType' : 'json',
				url: "../goods_process/batch_modify_imagehostgin",
				data: "no="+gl_goods_seq+"&hostname=" + hostname + "&username=" + username + "&password=" + password + "&imagehostingDomainType=" + imagehostingDomainType + "&mobile_contents=" + encodeURIComponent(mobile_contents)+"&contents=" + encodeURIComponent(goodscontents),
				success: function(result) {
					if( result.result ) {
						var goodsSeq		= gl_goods_seq;
						if(typeof gl_operation_type != 'undefined' && gl_operation_type == 'light'){
							var goodscontents	= result.mobile_contents;
							if(goodscontents){
								$("#mobile_contents_view").html(goodscontents);
								$(contentObj).val(goodscontents);
							}
							
							// 바로 저장 또는 임시저장
							if(goodsSeq){
								$("input[name='goodsSeq']").val(goodsSeq);
								$("input[name='contents_type']").val('mobile_contents');
								var newContant = '<input type="hidden" name="mode" val="ftp"/><textarea name="view_textarea" id="view_textarea" class="daumeditor" style="width:100%;height:500px;" contentHeight="500px">'+goodscontents+'</textarea>';
								$(".view_contents_area").html(newContant);
							}
						}else{
							var goodscontents	= result.contents;
							if(goodscontents){
								$("#goodscontents_view").html(goodscontents);
								$(contentObj).val(goodscontents);
							}
							
							// 바로 저장 또는 임시저장
							if(goodsSeq){
								$("input[name='goodsSeq']").val(goodsSeq);
								$("input[name='contents_type']").val('goodscontents');
								var newContant = '<input type="hidden" name="mode" val="ftp"/><textarea name="view_textarea" id="view_textarea" class="daumeditor" style="width:100%;height:500px;" contentHeight="500px">'+goodscontents+'</textarea>';
								$(".view_contents_area").html(newContant);
								if( $("input[name='mobile_contents_copy']:checked").val() == 'N' && result.mobile_contents ){
									$("#mobile_contents").html(result.mobile_contents);
									$("#mobile_contents_view").html(result.mobile_contents);
									$("#mobile_contents_view").show();
									//$("#mobile_contents_desc").hide();
								}
							}
						}
					}
					alert(result.msg);
					$("form#goodsImagehostingForm")[0].reset();
					closeDialog("openmarketimghostinglay");
				}
			});
		},function(){
		});
	});

	$("button.px_infomation").on("click", function(){
		$.ajax({
			type: "get",
			url: "../openmarket/notice_pop",
			data: {'type':'info'},
			success: function(result){
				$("#px_infomation").html(result);
				openDialog("다중 판매마켓 - 반드시 정독하셔야 하는 자주하는 질문", "px_infomation", {"width":"80%","height":"870"});
			}
		});
	});

	$(".goods-name-copy").on("click", function(){
		$("input[name='goodsNameLinkage']").val($("input[name='goodsName']").val());
		$("input[name='goodsNameLinkage']").trigger('keyup').trigger('focus');
	});

	$(".keyword-copy").on("click", function(){
		$("input[name='keywordLinkage']").val($("input[name='keyword']").val());
		$("input[name='keywordLinkage']").trigger('keyup').trigger('focus');
	});

	$("button.keyword_preview").on("click", function(){
		if($("input[name='keywordLinkage']").val() == "") {
			alert("오픈마켓 검색어를 입력 후 확인해주세요.");
			return false;
		}

		var layID = "keyword_linkage_info_dialog";
		var height = 500;
		$.ajax({
			type: "post",
			url: "../goods/openmarket_keyword",
			data: {'keyword':$("input[name='keywordLinkage']").val()},
			dataType : 'json',
			success: function(result){
				if(result.shoplinker) {
					$("#"+ layID + " .keyword").html('샵링커 연동');
					$(".shoplinker").show();
					$(".shoplinker_keyword").html(result.shoplinker);
					height = 470;
				} else {
					$("#"+ layID + " .keyword").html('직접 연동');
					var api = ['storefarm','coupang','open11st'];
					$(".shoplinker").hide();
					$.each(api, function( i, val) {
						var g = $("."+val);
						if ( typeof result[val] != 'undefined' ) {
							$(g).find('td').html(result[val]);
							$(g).show();
						} else {
							$(g).hide();
						}
						
					});
				}
				openDialog('오픈마켓 검색어 미리 보기', 'keyword_linkage_info_dialog', {'width':550,'height':height} );
			}
		});
	});
	
	if (goodsObj.linkage_service) {

		$("input[name='chkGoodsNameLinkage']").on("change", function(){
			if ($(this).is(":checked"))
				$(".divisionGoodsNameLinkage").show();
			else
				$(".divisionGoodsNameLinkage").hide();
		});

		if (goodsObj.linkage_service)
			$("input[name='chkGoodsNameLinkage']").attr('checked',true).trigger('change');

	}

	$("button.src_ready_send_goods").on("click", function(){
		$.ajax({
			type: "get",
			url: "../openmarket/src_ready_send_goods",
			data: {},
			success: function(result){
				$("#src_ready_send_goods").html(result);
				openDialog("전송 대기 상품 검색", "src_ready_send_goods", {"width":"80%","height":"600"});
			}
		});
	});

	//동영상 노출안내
	$("#btnVideoGuide").on("click",function(){
		openDialog("상품 상세페이지에서의 상품사진 또는 동영상 노출안내", "#displayVideoGuide", {"width":"900","show" : "fade","hide" : "fade"});
	});

	// 워터마크 설정
	$(".waterMarkImageSetting").on("click",function(){
		$.ajax({
			type: "get",
			url: "../setting/watermark_setting?layerid=watermark_setting_popup",
			success: function(result){
				$("div#watermark_setting_popup").html(result);
			}
		});
		openDialog("워터마크 설정", "watermark_setting_popup", {"width":"550","height":"550","show" : "fade","hide" : "fade"});
	});

	$(".waterMarkImageApply").on("click",function(){watermark();});
	$(".waterMarkImageCancel").on("click",function(){watermark_recovery();});


	// 빅데이터 PC 안내 화면
	$("button#btn_bigdata_screen_p").on("click", function(){openDialog("안내) 데스크탑 화면", "bigdata_pc_screen", {"width":"1030","height":"780"});});

	// 빅데이터 MOBILE 안내 화면
	$("button#btn_bigdata_screen_m").on("click", function(){openDialog("안내) 모바일 화면", "bigdata_mobile_screen", {"width":"860","height":"750"});});

	//입점 마케팅 상품명 개별설정 선택시
	$(".feed_goods_use").on("change", function() {
		$('#feed_goods_name_view').hide();
		$("#feed_goods_name").attr('disabled', true);

		if (this.value == 'Y') {
			$("#feed_goods_name").attr('disabled', false);
			$('#feed_goods_name_view').show();
			$("#feed_goods_name").val("{"+"product_name"+"}");
			$("#feed_goods_name").trigger('keyup').trigger('focus');
		}
	});

	// 성인상품 설정여부 체크 :: 2015-03-10 lwh
	$("input:radio[name='adult_goods']").on("click", function(){
		if (goodsObj.adult_chk != 'Y' && $(this).val() == "Y") {
			if(window.Firstmall.Config.Environment.isSellerAdmin){
				alert("관리자에게 성인 상품 판매를 위한 본인 인증 서비스 신청 여부를 확인해주세요.");
			}else{
				if(confirm("성인 쇼핑몰을 운영 하시려면 먼저 휴대폰인증&아이핀 서비스를 설정하셔야 합니다.\n설정>회원>본인확인으로 이동하시겠습니까?")){
					location.href = "/admin/setting/member?gb=realname";
				}
			}
			$(this).closest("td").find("input[value='N']").prop('checked',true);
		}

	});

	$('input.cal-len').each(function(){calculate_input_len(this);});

	// 입점 마케팅 설정
	chgfeedinfoNew($('input[name="feed_status"]:checked').val());

	$('input[name="marketing_event_set"]').on("click",function(){
		if($(this).is(":checked")){
			$(this).parent().next(".marketing_event_set").show();
		}else{
			$(this).parent().next(".marketing_event_set").hide();
		}
	});
	if(goodsObj.feed_evt_sdate && goodsObj.feed_evt_sdate != "0000-00-00" && goodsObj.feed_evt_edate && goodsObj.feed_evt_edate != "0000-00-00"){
		$('input[name="marketing_event_set"]').trigger("click").prop("checked",true);
	}else{
		$('input[name="marketing_event_set"]').trigger("click").prop("checked",false);
	}
	
	$(".btn_markting_required").on("click",function(){
		openDialog("입점 마케팅 필수 항목 안내", "regist_marketing_required", {"width":"720","height":"660","show" : "fade","hide" : "fade"});
	});


	$("button.bigdataCriteriaButton").on("click",function(){
		var displayResultId	= $(this).attr('dp_id');
		var criteria		= $("#"+displayResultId).val();
		var kind			= $(this).attr('kind');
		open_criteria_condition(displayResultId,'',criteria,kind);
	});

	$(".color-check label input").on("click", function(){
		if ($(this).is(':checked'))
			$(this).parents().addClass("active");
		else
			$(this).parents().removeClass("active");
	});

	$( "form[name='goodsRegist'] select[name='provider_seq_selector']" ).combobox().on("change", function(){
		if	($(this).find('option:selected').attr('disabled')){
			openDialogAlert('종료된 입점사입니다.', 400, 150, function(){});
			if	($("form[name='goodsRegist'] input[name='provider_seq']").val()){
				$(this).find("option[value='" + $("form[name='goodsRegist'] input[name='provider_seq']").val() + "']").attr('selected', true).change();
			}else{
				$(this).find('option:selected').attr('selected', false);
			}
			$(this).combobox('destroy').combobox();
		}else{
			$("form[name='goodsRegist'] input[name='provider_seq']").val($(this).val());
			$("form[name='goodsRegist'] input[name='provider_name']").val($("option:selected",this).text());
		}
	});

	$("#nation_all").on("click", function() {
		if (this.checked === true) {
			$('.nations').attr('checked', true);
			$('.hscode_num').attr('disabled', false);
			$('.hscode_tax').attr('disabled', false);
			$('.hscode_type_text').removeClass('disabled');
			$('.hscode_unit_text').removeClass('disabled');
			
		} else {
			$('.nations').attr('checked', false);
			$('.hscode_num').attr('disabled', true);
			$('.hscode_tax').attr('disabled', true);
			$('.hscode_type_text').attr('disabled', true);
			$('.hscode_type_text').addClass('disabled');
			$('.hscode_unit_text').removeClass('disabled');
		}

	});

	$('.nations').on("change", function() {
		var nationKey	= this.value;
		$("#nation_all").attr('checked', false);
		
		if (this.checked === true) {
			$('input[name="hscode_num[' + nationKey + ']"').attr('disabled', false);
			$('input[name="hscode_tax[' + nationKey + ']"').attr('disabled', false);
			$('.' + nationKey + '_type_text').removeClass('disabled');
			$('.' + nationKey + '_unit_text').removeClass('disabled');
		} else {
			$('input[name="hscode_num[' + nationKey + ']"').attr('disabled', true);
			$('input[name="hscode_tax[' + nationKey + ']"').attr('disabled', true);
			$('.' + nationKey + '_type_text').addClass('disabled');
			$('.' + nationKey + '_unit_text').addClass('disabled');
		}
	});


	$('.hscode_all_btn').on("click", function() {
		var target			= $(this).attr('target');
		var targetName		= $(this).attr('target_name');
		var targetValue		= $.trim($('#' + target + '_all').val());
		var targetValueInt	= parseInt(targetValue,10);
		var targetText		= targetValue;

		if (targetValueInt < 1)
			targetText		= '';
		else if (targetValueInt < 10)
			targetText		= '0' + targetValueInt;
		else
			targetText		= targetValueInt;
		
		switch (target) {
			case	'hscode_type' :
				if (targetValueInt > 0 && typeof hscodeTypeList[targetValueInt] != 'string') {
					$('#hscode_type_all').val('')
					alert("유효하지 않는 '류' 코드입니다. 다시 확인해 주세요");
					return;
				}
				
				targetText	= (targetValueInt > 0) ? '제' + targetText + '류' : '';

			case	'hscode_unit' :
				$('.' + target + '_text:not(.disabled)').html(targetText);


			default :
				$('.' + target + ':enabled').val(targetValue);
		}
			
	});

	// 상품 결제수단 변경
	//$('input[name="possible_pay_type"]').on("change", function() { payMethodChange(this.value) });

	var setGoodsView = function(mode){
		if(mode == "reservation"){
			$(".tr_goodsView_reservation").show();
			$("form[name='goodsRegist'] input[name='goodsView']").val('look');
			$("form[name='goodsRegist'] input[name='display_terms']").val('AUTO');
			displayTermsChange('AUTO') ;
		}else{
			$(".tr_goodsView_reservation").hide();
			$("form[name='goodsRegist'] input[name='goodsView']").val(mode);
			displayTermsChange('MENUAL') ;
		}
	}

	// 상품 노출 설정
	if (batchModify === false && gl_goods_seq) {
		// 노출예약 인 경우에는 reservation 체크되도록 수정 2021-05-17
		var goodsViewMode = goodsObj.display_terms == 'AUTO' ? 'reservation' : goodsObj.goods_view;
		$("form[name='goodsRegist'] input[name='tmp_goodsView'][value='"+goodsViewMode+"']").prop("checked",true);
		setGoodsView(goodsViewMode);
	}
	$("input[name='tmp_goodsView']").on("click",function(){
		setGoodsView($(this).val());
	});


	/* 예약 상품 버튼 이벤트 */
	$("input[name='display_terms_type_tmp']").on("click",function(){
		var val = '';
		if($(this).is(":checked")) val = "LAYAWAY"; else val = "SELLING";
		$('input[name="display_terms_type"]').val(val);
		setDisplayTermsType($(this).is(":checked"));
	});

	/* 예약상품 상세 설정 노출 */
	var setDisplayTermsType = function(mode){
		if(mode == true){
			$("input[name='display_terms_text']").val('[예약 판매]');
			$("tr.ableShippingDateLay").show();
		}else{
			$("input[name='display_terms_text']").val('');
			$("tr.ableShippingDateLay").hide();
		}

	}
	
	if( typeof gift == "undefined" ) {
		$(".colorpicker").customColorPicker();
	}

	$('#display_terms_begin, #display_terms_end').on("change", function() {
		var target			= this.id;
		var selectDate		= this.value;
		var splitDate		= selectDate.split('-');

		if (splitDate.length < 3)
			return;
		
		var dateObj			= new Date(selectDate);
		
		if (isNaN(dateObj.getTime()))
			return;

		if (target == 'display_terms_begin') {
			var toMsTime	= dateObj.getTime() - 86400000;
			var toDisplay	= '#display_terms_begin_before';
		} else {
			var toMsTime	= dateObj.getTime() + 86400000;
			var toDisplay	= '#display_terms_end_after';
		}

		dateObj.setTime(toMsTime);
		
		var toDate			= dateObj.toISOString().substring(0,10);
		$(toDisplay).html(toDate);

	});

	displayTermsChange(goodsObj.display_terms);
	$('#display_terms_begin, #display_terms_end').trigger('change');

	$('#subOptionProcessBtn').on("click", function(){
		if(window.Firstmall.Config.Environment.serviceLimit.H_FR == true){
			getNoServiceAlert();
		}else{
			openDialog("추가 구성 옵션 구매 방법 설정", "subOptionProcessSet", {"width":"700", "height":"380","show" : "fade","hide" : "fade"});
		}
	});


	//구매수량할인 설정 ↓
	$("#promotionViewSet input[name='multiDiscountSet']").on("click", function() {
		if($(this).val() == "y"){
			$(".multiDiscountLay").show();
		}else{
			$(".multiDiscountLay").hide();
		}
	});

	$('select[name="discountUnit"]').on('change', function(){
		
		var unitText	= $(this).find(":selected").text();

		if (unitText == '%')
			$('.discount_unit').text(unitText);
		else
			$('.discount_unit').text(unitText + ' / 1개');
	});
	
	$('#multiDiscountTable').on("blur", 'input[name="discountOverQty[]"]:eq(0)', function(){
		var nowOver		= parseInt(this.value, 10);
		var nowUnder	= parseInt($('input[name="discountUnderQty[]"]').eq(0).val(),10);
		
		if (isNaN(nowOver) || nowOver < 2) {
			alert('시작 수량이 2 보다작습니다.');
			this.value	= 2;
		}

		if (nowOver >= nowUnder) {
			alert('미만 수량이 ' + nowOver + '보다 같거나 작습니다.');
			$('input[name="discountUnderQty[]"]').eq(0).val(nowOver + 1);
			$('input[name="discountUnderQty[]"]').eq(0).trigger('blur');
		}
	});

	$('#multiDiscountTable').on("blur", 'input[name="discountUnderQty[]"]', function(){
		var nowIndex	= $('input[name="discountUnderQty[]"]').index(this);		
		var nowOver		= parseInt($('input[name="discountOverQty[]"]').eq(nowIndex).val(),10);
		var nowUnder	= parseInt(this.value, 10);

		if (nowIndex == 0 && (isNaN(nowOver) || nowOver < 2)) {
			nowOver		= 2;
			$('input[name="discountOverQty[]"]').eq(0).val(2);
		}

		if (nowOver >= nowUnder) {
			alert('미만 수량이 ' + nowOver + '보다 같거나 작습니다.');
			$('input[name="discountUnderQty[]"]').eq(nowIndex).val(nowOver + 1);
		}

		changeDiscountSet(nowIndex + 1)
	});

	// 구매 수량 할인 Row 추가
	$('.addDiscountSet').on("click", function(){
		
		var unitText	= $('select[name="discountUnit"] option:selected').text();
		unitText		= (unitText == '%') ? unitText : unitText + ' / 1개';

		var baseTr	 = '<tr>';
		baseTr		+= '	<td>';
		baseTr		+= '	<input type="text" name="discountOverQty[]" value="0" class="resp_text onlynumber" size="4" maxlength="5"/> 개 이상';
		baseTr		+= '	<span class="discount_under_qty">';
		baseTr		+= ' 	<input type="text" name="discountUnderQty[]" value="0" class="resp_text onlynumber" size="4" maxlength="5"/> 개 미만';
		baseTr		+= '	</span>';
		baseTr		+= '	</td>';
		baseTr		+= '	<td>';
		baseTr		+= '	<input type="text" name="discountAmount[]" value="0" class="resp_text onlynumber right" size="7" maxlength="10"/>';
		baseTr		+= '	<span class="discount_unit">' + unitText + '</span>';
		baseTr		+= '	</td>';
		baseTr		+= '	<td class="center"><span><button type="button" class="delDiscountSet btn_minus"></button></span></td>';
		baseTr		+= '</tr>';

		var lastOver	= parseInt($('input[name="discountOverQty[]"]:last').val(),10);
		var lastUnder	= parseInt($('input[name="discountUnderQty[]"]:last').val(),10);
		var totalSetCnt	= $("#multiDiscountTable tbody tr").length;
		if	($('#multiDiscountTable').find("input[name='discountMaxOverQty']").val() > 0){
			totalSetCnt++;
		}

		lastOver		= (isNaN(lastOver)) ? 0 : lastOver;
		lastUnder		= (isNaN(lastUnder)) ? 0 : lastUnder;
		
		if(lastUnder == 0) lastUnder = lastOver + 1;
		$('input[name="discountUnderQty[]"]:last').val(lastUnder);

		if ((lastOver > 0 ||  lastUnder > 0) && lastOver >= lastUnder && totalSetCnt > 0) {
			alert('수량을 확인해주세요');
			return;
		}

		if (totalSetCnt == 0) {
			var targetElement	= $(baseTr);
			var lastUnder		= 2;
			$('.max_qty_set').show();
		} else if (totalSetCnt == 1) {
			$('#multiDiscountTable tfoot').find("input[name='discountMaxOverQty']").attr('readonly', true).addClass('readonly-color');
			//$('#multiDiscountTable tfoot').find('.btn-minus').hide();
		//	$('#multiDiscountTable tbody').append(baseTr);
		} else {
			var targetElement	= $("#multiDiscountTable tbody tr:last").clone();
			targetElement.find('input[name="discountOverQty[]"').val(lastUnder);
			targetElement.find('input[name="discountUnderQty[]"').val(lastUnder + 1);
			targetElement.find('input[name="discountAmount[]"').val(0);
	
			$("#multiDiscountTable tbody").append(targetElement);
		}

		$('input[name="discountUnderQty[]"]:last').trigger('change');
		checkDiscountSet();
	});


	$('#multiDiscountTable').on("change", "input[name='discountUnderQty[]']:last", function(){$('input[name="discountMaxOverQty"]').val(this.value);});

	// 구매 수량 할인 : Row 삭제
	$('#multiDiscountTable').on("click", ".delDiscountSet", function(){
		var targetTr	= $(this).closest('tr');
		var trIndex		= $('.delDiscountSet').index(this) + 1;
		var firstdel	= false;
		if($("#multiDiscountTable tbody tr").length > 1){
			targetTr.remove();
		}else{
			firstdel = true;
		}

		if(firstdel == true && trIndex == 1){
			$("input[name='discountOverQty[]']").eq(0).prop("readonly", false);
			$("input[name='discountOverQty[]']").eq(0).removeClass("readonly-color");
		}

		changeDiscountSet(trIndex - 1,firstdel);
		checkDiscountSet(firstdel);
		//$("#multiDiscountTable tbody tr").length;
	});
	
	$('.open_new_popup').click(function() {
		var openLink	= $(this).attr('href');
		window.open(openLink);
	});

	setOptionStockSetText();


	$('.shipping_group_tb').on('DOMSubtreeModified', function(){
		if ($('.direct_store_use').val() == '1')
			$('.shopPickUp').show();
		else
			$('.shopPickUp').hide();
	});
	
	$('#colorMultiCheck').on('click', function(){
		if ($('input[name="color_pick[]"]:not(:checked)').length < 1) {
			$('input[name="color_pick[]"]').attr('checked', false);
			$('input[name="color_pick[]"]').parent().removeClass('active');
		} else {
			$('input[name="color_pick[]"]').attr('checked', true);
			$('input[name="color_pick[]"]').parent().addClass('active');
		}
	});
	
	// HSCODE관련
	$("select[name='hscode_selector']").on("change",function(){

		var selectedHSCode	= $(this).val();

		if( selectedHSCode != 0 ){
			$("input[name='hscode']").val(selectedHSCode);
			$("input[name='hscode_name']").val($("option:selected",this).text());

			$.ajax({
				'type'		: "get",
				'url'		: '../goods/hscode_setting_regist',
				'data'		: {'hscode_common':selectedHSCode,'mode':'json'},
				'dataType'	: 'json',
				'global'	: false,
				'success'	: function(res){

					$("#hscode_view").find("thead tr:last-child").remove();
					$("#hscode_view").find("tbody tr").remove();

					/* title 재정의*/
					var titleHtml = "<tr>";
					titleHtml = titleHtml + '<th class="center nation">수입국가</th>';
					titleHtml = titleHtml + '<th class="center nation_code">수입국가코드</th>';
					for(var j=0; j< res.hscode_items[0].export_nation_name.length; j++){
						titleHtml = titleHtml + '<th class="center tax">'+res.hscode_items[0].export_nation_name[j]+'</th>';
					}

					$("#hscode_view thead .rate").attr("colspan",res.hscode_items[0].export_nation_name.length);
					$("#hscode_view").find("thead").append(titleHtml);
		
					/* 선택한 hscode데이터 그리기 */
					var row_cnt		= res.hscode_items.length;
					var sectionHtml = "<tr>";

					sectionHtml = sectionHtml + '<td class="its-td center pd10" rowspan="'+row_cnt+'">'+res.hscode_name+'</td>';
					sectionHtml = sectionHtml + '<td class="its-td center pd10" rowspan="'+row_cnt+'">'+res.hscode_common+'</td>';
						
					for(var i=0; i < row_cnt; i++){

						if(i > 0){ sectionHtml = sectionHtml + '<tr>'; }

						sectionHtml = sectionHtml + '<td class="its-td center pd10">'+res.hscode_items[i].nation_name+'</td>';
						sectionHtml = sectionHtml + '<td class="its-td center pd10">'+res.hscode_items[i].hscode_nation+'</td>';

						for(var j=0; j< res.hscode_items[i].customs_tax.length; j++){
							sectionHtml = sectionHtml + '<td class="its-td center pd10">'+res.hscode_items[i].customs_tax[j]+'%</td>';
						}
						if(i > 0){ sectionHtml = sectionHtml + '</tr>'; }
					}

					sectionHtml = sectionHtml + '</tr>';

					$("#hscode_view").parent().show();
					$("#hscode_view").find("tbody").append(sectionHtml);
				},
				'error': function(e){
				}
			});

		}else{
			$("input[name='hscode']").val('');
			$("input[name='hscode_name']").val('');
			
			$("#hscode_view").find("thead tr:last-child").remove();
			$("#hscode_view").find("tbody tr").remove();

			/* title 재정의*/
			var titleHtml = "<tr>";
			titleHtml = titleHtml + '<th class="center nation">수입국가</th>';
			titleHtml = titleHtml + '<th class="center nation_code">수입국가코드</th>';
			titleHtml = titleHtml + '<th class="center tax"></th>';

			$("#hscode_view thead .rate").attr("colspan",1);
			$("#hscode_view").find("thead").append(titleHtml);
			$("#hscodeRegistLayer").hide();

		}
	});

	$("#hscode_detail").on("click",function(){
		window.open("about:blank").location.href="https://unipass.customs.go.kr/clip/index.do";
	});
	$("#hscode_set").on("click",function(){
		window.open("about:blank").location.href="../goods/hscode_setting";
	});

	$(".tablednd").tableDnD({onDragClass: "dragRow"});
	//$("table tbody#subInfoTable").sortable({items:'tr'});

	// EP 노출 배송비 설정 :: 2017-02-22 lwh
	$("input[name='feed_pay_type']").on('change', function(){
		ep_market_set();
	});

	// 추가 배송비 텍스트 체크 :: 2017-02-22 lwh
	$("#feed_add_txt").on('keyup', function(){
		ep_addtxt_chk();
	});

	// 배송 변경시 마켓팅정보 :: 2017-02-23 lwh
	$("#shipping_group_seq, .feed_shipp_type").on('change', function(){
		feed_ship_chk();
	});

	// 지역 설정 체크박스 클릭 이벤트
	$("input[name='location_setting']").on("click",function(){
		if(window.Firstmall.Config.Environment.serviceLimit.H_FR == true){
			getNoServiceAlert();
			$(this).prop("checked",false);
		}else{
			$(this).closest("td").find(".sub_cont").toggle();
			if($(this).is(":checked") == true){
				//$(this).closest("div").attr("style","border-bottom: 1px dashed #ccc; padding-bottom:3px; margin-bottom:10px; width:100%;");
			}else{
				//$(this).closest("div").attr("style","");
			}
		}
	});

	if(window.Firstmall.Config.Environment.serviceLimit.H_FR == true){
		$("input[name='restockNotifyUse']").on("click",function(){
			getNoServiceAlert();
			$(this).prop("checked",false);
		});
	}

	if(goodsObj.locationUse == 'y') $("input[name='location_setting']").trigger("click");
	
	// 재고에 따른 판매 - 설정
	$(".runout_setting").on("click",function(){
		if(window.Firstmall.Config.Environment.serviceLimit.H_FR == true){
			getNoServiceAlert();
		}else{
			if($("input[name='runout_type']:checked").val() == "goods"){
				openDialog("재고에 따른 판매 - 개별 설정", "popup_runout_setting", {"width":800,"height":360, "show" : "fade","hide" : "fade"});
			}else{
				alert("개별 설정일 때에만 설정 가능합니다.")
			}
		}
	});

	$("#possible_pay_setting").on("click",function(){
		if(window.Firstmall.Config.Environment.serviceLimit.H_FR == true){
			getNoServiceAlert();
		}else{
			$("input[name='possible_pay_type'][value='goods']").prop("checked",true);
			openDialog("결제 수단 개별 설정", "possible_pay", {"width":"600", "height":"550","show" : "fade","hide" : "fade"});
		}
	});


	if(typeof gift == "undefined") {
		gift = false;
	}

	// 마켓연동상품 가져오기
	if(goodsObj.sellerMode != "SELLER" && gift != true){
		initOpenmarketProd(goodsObj.goods_seq);
	}
	/*{/}*/

	
	if(goodsObj.goods_seq){
		groupsale_choice($("input[name='sale_seq']").val());
	}

	/*  상품 수정 시 기본 show 이기 때문에 실행 안함
	if(goodsObj.goods_sub_info != ''){
		chgGoodSubInfo(goodsObj.goods_sub_info);
	} */

	$(document).on("click", '.delFreqOption', function(){
		var goods_seq = $(this).val();
		var type = $(this).data('type');
		if(!goods_seq){
			alert("상품 번호를 찾을 수 없습니다.");
			return false;
		}
		
		if(!type){
			alert("타입을 찾을 수 없습니다.");
			return false;
		}
		
		var popupID		= $(this).parents('div').attr('id');
		var page		= $(this).closest('div').find('.paging_navigation .on').text();
		var packageyn	= $(this).data('packageyn');
		
		var name = $('.delFreqOptionName_'+goods_seq).text();
		
		if(confirm(name + '를 삭제 하시겠습니까?')){
			delFreqOption(goods_seq, type, page, packageyn, popupID);
		}
	});
	
	//신규 등록시 기본 값 세팅
	if(!goodsObj.goods_seq){
		//	$("input[name='goodsView']").val('look');
	}

	if( typeof gift == "undefined" ) {
		// 전달 데이터 체크 :: 2017-02-23 lwh
		feed_ship_chk();
	}	

	if(typeof gift == "undefined" && batchModify !== true) {
		gCategorySelect.open({'openType':'self','closeMessageUse':false,'fieldName':'connectCategory[]','divSelectLay':'lay_category_select','callPoint':'goods',});
	}

	pageGoodsHelperscrollEvent();

	// 개인통관부호 수집 시 선물하기 사용 불가
	$("input[name='option_international_shipping_status'],input[name='present_use']").on('change', function () {
		international_present_use();
	});
});

// 개인통관부호 수집 시 선물하기 사용 불가
function international_present_use() {
	var option_international_shipping_status = $("input[name='option_international_shipping_status']:checked").val();
	var present_use = $("input[name='present_use']:checked").val();

	if (option_international_shipping_status == 'y' && present_use == '1') {
		alert('선물하기 상품은 국내배송 및 택배수령만 가능합니다. \n해외 구매 대행 상품의 경우 쇼핑몰 페이지에서 선물하기 아이콘이 적용되지 않습니다.');
	}
}
/*스크롤 이벤트 시 탭 활성*/
function pageGoodsHelperscrollEvent(){
	$(document).on("scroll", function(){	
		clearTimeout($.data(this, 'scrollTimer'));		
		
		$.data(this, 'scrollTimer', setTimeout(function() {
			var _scrollTop = $(document).scrollTop();
			var _isChk = false;
			
			$("form > a").each(function(){					
				var _name = $(this).attr("name"); 				
				
				//오픈 마켓 연동 제외 활성								
				if($(this).offset().top > _scrollTop && _isChk == false && _name != "15"){	
					$(".page-goods-helper-btn td").removeClass("on");						
					$(".page-goods-helper-btn a[data-key=" + _name + "]").parent().addClass("on");
					_isChk = true;			
				}
			})			
		}, 50));	
	});
}

function viewOptionTmp(islimit){

	var tmp_seq				= $("input[name='tmp_option_seq']").val();
	var policy				= $("select[name='reserve_policy'] option:selected").val();
	if	(!policy)	policy	= $("input[name='reserve_policy']").val();
	var socialcp_input_type = eval('$("input[name=\'socialcp_input_type\']:checked").val()');
	var provider_seq 		= gl_provider_seq;
	if(!provider_seq){
		provider_seq		= $("form[name='goodsRegist'] input[name='provider_seq']").val();
	}
	var set_code			= $("input[name='goodsCode']").val();
	if(typeof set_code == "undefined") set_code = '';
	var linkurl				= 'set_goods_options?provider_seq='+provider_seq+'&mode=view&goods_seq='+gl_goods_seq+'&package_yn='+gl_package_yn+'&tmp_seq='+tmp_seq+'&goodsTax='+gl_tax+'&socialcp_input_type='+socialcp_input_type+'&tmp_policy='+policy+'&islimit='+islimit+'&goodsCode='+set_code;
	actionFrame.location.replace(linkurl);
}

function setOptionTmp(tmp_opno,tmp_frequently,goodsCode,_options){
	var goodsTax				= $("input[name='tax']:checked").val();
	var socialcp_input_type 	= eval('$("input[name=\'socialcp_input_type\']:checked").val()');
	var provider_seq 			= gl_provider_seq;
	if(!provider_seq) provider_seq	= $("form[name='goodsRegist'] input[name='provider_seq']").val();
	if(!goodsTax)	goodsTax		= 'tax';

	$("#optionTmpSeq").val(tmp_opno);
	$("iframe[name='actionFrame']").attr('src', 'set_goods_options?provider_seq='+provider_seq+'&mode=view&tmp_seq='+tmp_opno+"&goodsTax="+goodsTax+'&package_yn='+gl_package_yn+"&socialcp_input_type="+socialcp_input_type+"&goodsCode="+goodsCode);
	$("input[name='tmp_option_seq']").val(tmp_opno);
	$("input[name='frequentlyopt']").val(tmp_frequently);
	$("input[name='optionViewType']").val(_options.optionViewType);
	
	if	(tmp_opno)	set_option_select_layout();

	international_shipping_info();
}

function freqOptionsReload(type){
	//자주쓰는 상품의 필수옵션 관련 수정 사항 적용
	$.ajax({
		'url' : '../goods_process/set_freq_option',
		'data' : {'package_yn': gl_package_yn, 'type': type},
		'type' : 'get',
		'success' : function(res){
			if(res){
				if(type == 'sub'){
					$('select[name="frequentlytypesubopt"]').find('option').remove();
					$('select[name="frequentlytypesubopt"]').append('<option value="0">자주 쓰는 상품의 추가구성옵션</option>');
					$.each(jQuery.parseJSON(res), function(key, value) {
						$('select[name="frequentlytypesubopt"]').append('<option value="'+value.goods_seq+'">'+value.goods_name+'</option>');
					});
				} else {
					$('select[name="frequentlytypeopt"]').find('option').remove();
					$('select[name="frequentlytypeopt"]').append('<option value="0">자주 쓰는 상품의 필수옵션</option>');
					$.each(jQuery.parseJSON(res), function(key, value) {
						$('select[name="frequentlytypeopt"]').append('<option value="'+value.goods_seq+'">'+value.goods_name+'</option>');
					});
				}
				
			}
		}
	});
}

//새창에서 필수옵션 가져오기시
function openSettingOptionnew(add_goods_seq,params){

	var tmp_seq			= $("input[name='tmp_option_seq']").val();
	var goodsTax		= $("input[name='tax']:checked").val();
	if	(!goodsTax)	goodsTax	= 'tax';
	var policy			= $("select[name='reserve_policy'] option:selected").val();
	if	(!policy)	policy			= $("input[name='reserve_policy']").val();
	var windowOption	= 'width=1200px,height=700px,toolbar=no,titlebar=no,scrollbars=yes,resizeable';

	var socialcp_input_type = eval('$("input[name=\'socialcp_input_type\']:checked").val()');

	var provider_seq = gl_provider_seq;
	if(!provider_seq){
		provider_seq			= $("form[name='goodsRegist'] input[name='provider_seq']").val();
	}

	if(typeof params == "undefined") params = "";
	var url	= 'set_goods_options?provider_seq='+provider_seq+'&add_goods_seq='+add_goods_seq+'&goods_seq='+gl_goods_seq+'&tmp_policy='+policy+"&goodsTax="+goodsTax+"&socialcp_input_type="+socialcp_input_type+'&package_yn='+gl_package_yn+''+params;
	//url += 'optionViewTypeTmp='params._optionViewTypeTmp+'&optionCreateType='+params.optionCreateType;

	optionTmpPopup.location.replace(url);
//	window.open(url, 'OPTION_POP', windowOption);
}

