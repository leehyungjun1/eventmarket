$(document).ready(function() {

	var goods_gubun = 'admin';
	gl_provider_seq
	$('input.cal-len').each(function(){calculate_input_len(this);});

	// 상품사진 개별등록 :: 2016-05-02 lwh
	var goodsLink		= '';
	$("#goodsImageTable").on("click", '#eachImageRegist', function(){
		var division = $("input[name='imgKind']").val();
		var divisionIdx = $("input[name='idx']").val();
		var height = 280;
		if(division == 'view') height = 430;
		
		if(gl_goods_seq)
			goodsLink	= '&no=' + gl_goods_seq;
		else 
			goodsLink	= '';

		window.open('popup_image?division=' + division + goodsLink + '&idx='+divisionIdx,'','width=550,height='+height);
	});

	if(gl_goods_seq)
		goodsLink	= '?no=' + gl_goods_seq;
	else 
		goodsLink	= '';

	// 순서변경 및 삭제 :: 2016-05-03 lwh
	$("#goodsImageTable").on("click", ".ImageSort", function(){
		
		$.ajax({
			type: "get",
			url: "./popup_image_sort",
			data: "no="+gl_goods_seq,
			success: function(html){
				$("#set_popup_image_sort_lay").html(html);
				openDialog("순서 변경 및 삭제", "set_popup_image_sort_lay", {'width':550,'height':500,'show':'fade','hide' : 'fade'});
			}
		});

	});
	

	$("#goodsImageTable").on("click", "#imgDownload", function(){
		var src	= $("#viewImg").attr("src");
		src		= src.split('?');

		actionFrame.location.href = "../../common/download?downfile="+escape(src[0]);
	});


	if (goodsObj.imageLabel)
		$("input[name='goodsImgLabel']").val(goodsObj.imageLabel);

	//원본이미지 삭제여부
	$("#imagedelete").bind("click",function(){
		if ($("#imagedelete").is(':checked')) {
			openDialogConfirm('원본이미지를 삭제하겠습니까?<br/>삭제된 이미지는 복구 되지 않습니다!',500,160,function(){
				$("#imagedelete").attr("checked",true);
			},function(){
				$("#imagedelete").removeAttr("checked");
			});
		}
	});
	
	$(".waterMarkImageSetting").on("click",function(){
		$.ajax({
			type: "get",
			url: "../setting/watermark_setting?layerid=watermark_setting_popup",
			success: function(result){
				$("div#watermark_setting_popup").html(result);
			}
		});
		openDialog("워터마크 설정", "watermark_setting_popup", {"width":"700","height":"510","show" : "fade","hide" : "fade"});
	});

	$(".waterMarkImageApply").on("click",function(){watermark();});
	$(".waterMarkImageCancel").on("click",function(){watermark_recovery();});



	//상품승인 -> 미승인시 판매중지자동
	/*
	$("input[name='provider_status']").on("click", function(){

		if ($(this).is(':checked') == true && $(this).val() != '1') {
			alert("해당 상품은 '미승인'처리되어 상품 상태는 '판매중지'가 됩니다.");
			$("form[name='goodsRegist'] input[name='goodsStatus'][value='unsold']").attr("checked",true);
		}

	});
	*/

	//상품상태 -> 미승인시 판매중지만가능
	/*
	$("input[name='goodsStatus']").on("click", function(){

		if ($("input[name='provider_status']:checked").val() == 0 && $(this).val() != 'unsold') {//미승인시
			alert("해당 상품은 '미승인'처리되어 상품 상태는 '판매중지'가 됩니다.");
			$("form[name='goodsRegist'] input[name='goodsStatus'][value='unsold']").attr("checked",true);
		}

	});
	*/

	/* 상품상태 변경시 가용재고체크 */
	/*
	$("input[name='goodsStatus']").on("change", function(){
		var chkStockVal		= chk_stockDesc2();
		var chkStatusVal	= $(this, "checked").val();
		var order_runout	= gl_runout;

		if (chkStatusVal == 'runout') {
			if (chkStockVal){
				if (($("input[name='runout_type']:checked").val() == 'goods' && $("input[name='runout']:checked").val() == 'unlimited') || ($("input[name='runout_type']:checked").val() == 'shop' && order_runout == 'unlimited') )
					openDialogAlert("재고와 상관없이 판매할경우 품절로 변경이 불가능합니다.<br/>판매를 중지하시려면 <b style='color:red;'>'재고 확보중'</b> 또는 <b style='color:red;'>'판매중지'</b>로 변경하시면 됩니다.",600,150);
				else
					openDialogAlert("가용재고가 남아 있는 상품으로 품절이 되지 않습니다.",400,150);

				$("input[name='goodsStatus'][value='normal']").attr('checked',true);
				$(".goodsStatusDesc").css('opacity',0.5);
				$("input[name='goodsStatus'][value='normal']").closest('tr').find(".goodsStatusDesc").css('opacity',1);
			}
		} else {
			if($(this).is(":checked")){
				if(!chkStockVal && chkStatusVal == 'normal' ) {

					if (goodsObj.goods_status) {
						$("input[name='goodsStatus'][value='" + goodsObj.goods_status + "']").attr('checked',true);
					} else {
						$("input[name='goodsStatus'][value='runout']").attr('checked',true);
					}

					return;
				}
			}
		}
	});
	*/

	$("input[name='ableStockLimit']").on("blur",function(){
		check_runout();
	});


	if (goodsObj.view_layout)
		$("form[name='goodsRegist'] input[name='viewLayout'][value='" + goodsObj.view_layout + "']").attr("checked",true);

	if (goodsObj.goods_status)
		$("form[name='goodsRegist'] input[name='goodsStatus'][value='" + goodsObj.goods_status +"']").attr("checked",true);

	
	if (gl_goods_seq) {
		if (goodsObj.goods_view)
			$("form[name='goodsRegist'] input[name='goodsView'][value='" + goodsObj.goods_view + "']").attr("checked",true);
		else
			$("form[name='goodsRegist'] input[name='goodsView'][value='notLook']").attr("checked",true);
	}


	if (goodsObj.provider_status)
		$("form[name='goodsRegist'] input[name='provider_status'][value='" + goodsObj.provider_status + "']").attr("checked",true);
	else
		$("form[name='goodsRegist'] input[name='provider_status'][value='0']").attr("checked",true);

	
	if (goodsObj.string_price_use) {
		$("form[name='goodsRegist'] input[name='stringPriceUse']").attr("checked",true);
		show_stringPrice();
	}


	check_runout_type();
	check_runout();

	calulate_option_price();

	// default
	if (goodsObj.provider_status)
		$("form[name='goodsRegist'] input[name='provider_status'][value='" + goodsObj.provider_status + "']").attr("checked",true);
	else
		$("form[name='goodsRegist'] input[name='provider_status'][value='0']").attr("checked",true);



	/* 상품코드 코드생성넣기*/
	$("#optionLayer").on("click", "#goodsCodeBtn", function(){openDialog("기본코드 자동생성", "makeGoodsCodLay", {"width":"400","height":"300"});});

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
					console.log(res);

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

	$("#hscode_detail").bind("click",function(){
		window.open("about:blank").location.href="https://unipass.customs.go.kr/clip/index.do";
	});
	$("#hscode_set").bind("click",function(){
		window.open("about:blank").location.href="../goods/hscode_setting";
	});

	$("#star_select").click(function(){
		var status = "";
		if($(this).hasClass("checked")){
			$(this).removeClass("checked");
			status = "none";
		}else{
			$(this).addClass("checked");
			status = "checked";
		}

		$.ajax({
			type: "get",
			url: "../goods/set_favorite",
			data: "status="+status+"&goods_seq="+$(this).attr("goods_seq"),
			success: function(result){
				//alert(result);
			}
		});
	});

	if (gl_goods_seq)
		chk_stockDesc();

	//입점사 버전일경우
	if(window.Firstmall.Config.Environment.serviceLimit.H_AD == true){

		var _provider_info = function(gubun,provider_seq){

			if(gubun == "provider"){
				if(gl_provider_seq == ''){
					do_rollback(provider_seq);
					$("select[name='provider_seq_selector']").trigger("change",[provider_seq]);
				}
				$(".tr_provider, .tr_account").show();
				$(".base").hide();
				$(".provider").show();
				$(".base").find('input').attr('disabled',true);
				$(".provider").find('input').attr('disabled',false);
			}else{
				$(".tr_provider, .tr_account").hide();
				$(".base").show();
				$(".provider").hide();
				$(".base").find('input').attr('disabled',false);
				$(".provider").find('input').attr('disabled',true);
			}
		}
		
		var do_provider_change = function(provider_seq){

			if(provider_seq == '' || provider_seq == undefined || typeof provider_seq == "undefined"){
				var selector = $("select[name='provider_seq_selector']");
				provider_seq = selector.find(":selected").val();
			}
			if(provider_seq == '' || provider_seq == '0') provider_seq = 1;

			if(provider_seq == '1'){
				//$('.not_for_provider').show();
				//$('.not_for_seller').hide();
				
				if(gl_provider_seq == ''){
					$("select[name='provider_seq_selector'] option").eq(0).prop("select",true);
					$("select[name='provider_seq_selector']").next(".ui-combobox").children("input").val(	$("select[name='provider_seq_selector'] option:selected").text());
					$(".ptc-charges").html('입점사를 선택 하세요.');
					$("input[name='provider_seq']").val(0);
				}
			}else{
				
				//$('.not_for_provider').hide();
				//$('.not_for_seller').show();
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
									commission_unit = '%';
									if(commission_info.commission_type =='SUPR') commission_unit = Firstmall.Config.Environment.Currency.Basic.Symbol;

									html += '(' + comma(num(res[i].charge)) + commission_unit+')';
									$("input[name='default_charge']").val(commission_info.charge);
									$("input[name='default_commission_type']").val(commission_info.commission_type);
									if($("input[name='commissionRate[]']").length==1 && $("input[name='commissionRate[]']").val()=='0'){
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
								alert("reset");
								reset_option_commition_info(commission_info);
								have_to_subopt_reset	= false;
							}

							//기본옵션이 없는경우에만 추가옵션 리셋(기본 옵션 완료 후 자동 리셋됨)
							if(have_to_subopt_reset === true && $("input[name='tmp_suboption_seq']").val() && $('input[name="subOptionUse"]').is(':checked')){
								reset_suboption_commition_info(commission_info);
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

			var selector = $("select[name='provider_seq_selector']");
			if(provider_seq == '' || provider_seq == undefined || typeof provider_seq == "undefined"){
				provider_seq = selector.find(":selected").val();
			}

			if(provider_seq > 0){
				var selector_option = selector.find("option[value='"+provider_seq+"']");
				selector_option.attr("selected","selected");
				$("input[name='provider_seq']").val(provider_seq);
				selector.next('.ui-combobox').children('input').val(selector_option.text());
				$("input[name='provider_name']").val(selector_option.text());
			}
		}

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
		});

		if(gl_provider_seq > 1){
			do_provider_change(gl_provider_seq);
			_provider_info('provider',gl_provider_seq);
			//$("input[name='goods_gubun'][value='provider']").prop("checked",true);
		}

		// 배송비 선택
		$(".shipping_group_select").on("click",function(){
			var provider_seq = gl_provider_seq;
			if($("input[name='goods_gubun']").val() == "admin"){
				provider_seq = 1;
			}else{
				provider_seq = $("input[name='provider_seq']").val()
			}
			ship_grp_sel('select',provider_seq);
		});

		/* 입점사 세션 수수료 수정 차단 */
		if (gl_adminSessionType == 'provider' || gl_provider_seq == '1') {
			$("input[name='commissionRate[]'], input[name='subCommissionRate[]']").on('keydown change focusin selectstart',function(){
				$(this).blur();
				return false;
			});
		}

		/* 본사에서 입점사 상품등록시 입점사 수수료율 출력 */
		$("select[name='provider_seq_selector']").on('change',function(event,provider_seq){

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
			$("select[name='provider_seq_selector']").trigger("change");
		}
	}
	$( "select[name='provider_seq_selector']" ).combobox().on("change", function(){
		if	($(this).find('option:selected').attr('disabled')){
			openDialogAlert('종료된 입점사입니다.', 400, 150, function(){});
			if	($("input[name='provider_seq']").val()){
				$(this).find("option[value='" + $("input[name='provider_seq']").val() + "']").attr('selected', true).change();
			}else{
				$(this).find('option:selected').attr('selected', false);
			}
			$(this).combobox('destroy').combobox();
		}else{
			$("input[name='provider_seq']").val($(this).val());
			$("input[name='provider_name']").val($("option:selected",this).text());
		}
	});


});