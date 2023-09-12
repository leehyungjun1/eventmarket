/* TOP 책갈피 이동 */
function moveBookmark(tapNum, idx){
	var offset = $("a[name='" + tapNum + "']").offset();
	$('html, body').animate({scrollTop : offset.top-60},400);
	$(".page-goods-helper-btn td").removeClass("on");
	$(".page-goods-helper-btn td:eq(" + idx + ")").addClass("on");
}

/* 카테고리 연결 */
function add_category(cateCode,cateName){
	$("#connectCategoryInfo").hide();
	var t = $("input[name='connectCategory[]'][value='"+cateCode+"']").attr("type");
	if(t == "hidden") return false;
	var trStr = "<tr>";
	trStr += "<td class=\"center\"><label><input type=\"radio\" name=\"firstCategory\" value=\""+cateCode+"\" /></label>";
	trStr += "<input type=\"hidden\" name=\"connectCategory[]\" value=\""+cateCode+"\" />";
	trStr += "</td>";
	trStr += "<td class=\"its-td\"><div class=\"connectCategory\">"+cateName+"</div><div><span class=\"btn-minus\"><button type=\"button\" class=\"categoryDelete\"></button></span></div></td>";
	trStr += "</tr>";
	$("#connectCategoryTable").append(trStr);
	$("input:radio[name='firstCategory']").val([cateCode]);
}

/* 브랜드 연결 */
function add_brand(cateCode,cateName,charge){
	$("#connectBrandInfo").hide();

	charge = num(charge);

	var t = $("input[name='connectBrand[]'][value='"+cateCode+"']").attr("type");
	if(t == "hidden") return false;
	var trStr = "<tr>";
	trStr += "<td class=\"center\"><label><input type=\"radio\" name=\"firstBrand\" value=\""+cateCode+"\" /></label>";
	trStr += "<input type=\"hidden\" name=\"connectBrand[]\" value=\""+cateCode+"\" />";
	trStr += "</td>";
	trStr += "<td class=\"its-td\"><div class=\"connectBrand\">"+cateName+"</div><div><span class=\"btn-minus\"><button type=\"button\" class=\"brandDelete\"></button></span></div></td>";
	trStr += "</tr>";
	$("#connectBrandTable").append(trStr);
	$("input:radio[name='firstBrand']").val([cateCode]);

	if(charge){
		$("input[name='commissionRate[]'],input[name='subCommissionRate[]']").each(function(){
			if($(this).val()=='0'){
				$(this).val(charge);
			}
		});
	}
}

/* 지역 연결 */
function add_location(cateCode,cateName,charge){
	$("#connectLocationInfo").hide();
	var t = $("input[name='connectLocation[]'][value='"+cateCode+"']").attr("type");
	if(t == "hidden") return false;


	var trStr = "<tr>";
	trStr += "<td class=\"center\"><label><input type=\"radio\" name=\"firstLocation\" value=\""+cateCode+"\" /></label>";
	trStr += "<input type=\"hidden\" name=\"connectLocation[]\" value=\""+cateCode+"\" />";
	trStr += "</td>";
	trStr += "<td class=\"its-td\"><div class=\"connectLocation\">"+cateName+"</div><div><span class=\"btn-minus\"><button type=\"button\" class=\"locationDelete\"></button></span></div></td>";
	trStr += "</tr>";
	$("#connectLocationTable").append(trStr);
	$("input:radio[name='firstLocation']").val([cateCode]);
}


/* 이미지 업로드 레이어 보기 */
function showImageUploadDialog(){
	nowPath = "data/tmp";
	$("#imageUploadDialog .uploadPath").html(nowPath);

	$("#imageUploadDialog").dialog("open");

	/* Uploadify path 변경 */
	//$("#imageUploadButton").uploadifySettings('folder','/' + nowPath);
}

function set_goodsImage_cut(){
	$("#goodsImageTable .goodsImageDetail tbody tr").each(function(idx){
		var cutname = "<img src='/admin/skin/default/images/common/icon_move.gif'> 대표";
		if(idx > 0){
			cutname = "<img src='/admin/skin/default/images/common/icon_move.gif'>";
			cutname += idx + 1;
			cutname += "번째 컷";
		}

		$html = $(this).find("td").eq(1).find(".fileColorTitle").html();
		$color = $(this).find("td").eq(1).find(".fileColorTitle").css("color");

		if($html) {
			cutname += " <input type=\"hidden\" name=\"goodsImageColor[]\" value=\""+rgb2hex($color)+"\" /><span class=\"fileColorTitle\" style=\"color:"+rgb2hex($color)+"\">"+$html+"</span>";
		} else {
			cutname += " <input type=\"hidden\" name=\"goodsImageColor[]\" value=\"\" /> <span class=\"fileColorTitle\"></span>";
		}

		$(this).find("td").eq(1).html(cutname);
	});
}

//필수 옵션 조합
function merge_option(opt1,opt2){
	var result = new Array();
	var k = 0;
	for (var i=0;i<opt1.length;i++){
		for (var j=0;j<opt2.length;j++){
			result[k] = opt1[i]+","+opt2[j];
			k++;
		}
	}
	return result;
}

function calulate_option_price(tmpidx){
	var container = $("#optionLayer");
	var idx;

	if(typeof tmpidx == 'undefined'){
		var selector = $("input[name='price[]']",container);
		var tmpidxch = false;
	}else{
		var selector = $("input[name='price[]']",container).eq(tmpidx);
		var tmpidxch = true;
	}

	selector.each(function(eachidx){
		if(tmpidxch) {
			idx = tmpidx;
		}else{
			idx = eachidx;
		}
		var rate = 0;
		var priceObj 				= $(this);
		var supplyPriceObj 			= $("input[name='supplyPrice[]']",container).eq(idx);
		var consumerPriceObj 		= $("input[name='consumerPrice[]']",container).eq(idx);
		var reserveObj 				= $("input[name='reserve[]']",container).eq(idx);
		var taxObj 					= $(".tax",container).eq(idx);
		var supplyRateObj 			= $(".supplyRate",container).eq(idx);
		var discountRateObj 		= $(".discountRate",container).eq(idx);

		var settlementAmountObj		= $(".settlementAmount",container).eq(idx);
		var commissionRateObj		= $("input[name='commissionRate[]']",container).eq(idx);
		var commissionTypeObj		= $("select[name='commissionType[]']",container).eq(idx);
		var defaultCommissionType	= $("input[name='default_commission_type']").val();
		if (goodsObj.sellerMode == 'SELLER') {
			var reserveRateObj 	= $("input[name='baseReserveRate[]']",container).eq(idx);
			var reserveUnit 	= $("input[name='baseReserveUnit[]']",container).eq(idx).val();
		} else {
			var reserveRateObj 	= $("input[name='reserveRate[]']",container).eq(idx);
			var reserveUnit 	= $("select[name='reserveUnit[]']",container).eq(idx).find("option:selected").attr("value");
		}

		if(typeof(commissionTypeObj.val()) == 'undefined'){
			var commissionTypeObj	= $("input[name='commissionType[]']",container).eq(idx);
		}

		var obj = { 0:supplyPriceObj.val(), 1:consumerPriceObj.val(), 2:priceObj.val(), 3:reserveRateObj.val(), 4:reserveUnit };
		var result = calulate_price(obj);

		if(result[0]>0 && result[0]!='Infinity') result[0] = result[0]+"%";
		else result[0] = "0%";
		supplyRateObj.html(result[0]);

		if(result[1]>0 && result[1]!='Infinity') result[1] = result[1]+"%";
		else result[1] = "0%";
		discountRateObj.html(result[1]);

		// TAX @2014-03-17
		if( (eval("$(\"input[name='tax']:checked\").val()") && $("input[name='tax']:checked").val()!='tax') || (eval("$('.goodsTax').val()") && $(".goodsTax").val()!='tax') ){
			result[2] = 0;
		}

		if(result[2] == '' || result[2] == null) result[2] = '0';

		taxObj.html(result[2]);
		reserveObj.val(result[3]);

		if (goodsObj.sellerMode == 'SELLER')
			$(".reserveRateValue",container).eq(idx).text(result[3]);

		// 마진출력
		var net_profit = priceObj.val() - supplyPriceObj.val();
		$(".net_profit",container).eq(idx).html(comma(net_profit));

		if(commissionTypeObj.val() != 'SUPR'){
			commissionRateObj.val(num_float(commissionRateObj.val()));
			if(commissionRateObj.val() > 100) {
				alert("수수료율이 100% 보다 크므로 최대 100%로 조정됩니다.");
				commissionRateObj.val('100');
			}
			commissionRateObj.attr("size",4);
		}else{
			commissionRateObj.attr("size",6);
		}

		//정산기준금액
		var commissionPriceKRW = priceObj.val();

		if(defaultCommissionType!='SACO' && commissionTypeObj.val() == "SUCO"){
			if(consumerPriceObj.val() > 0){
				commissionPriceKRW = consumerPriceObj.val();	//정가기준
			}else{
				commissionPriceKRW = 0;
			}
		}else{
			commissionPriceKRW = priceObj.val();	//판매가기준
		}

		if(commissionPriceKRW == 0){
			settlementAmountObj.html('0');
			return true;		//continue;
		}

		// 정산기준금액(원화로 변경)
		if(gl_basic_currency != "KRW"){
			commissionPriceKRW = get_currency_exchange(commissionPriceKRW,gl_basic_currency,gl_krw_exchange_rate);
			commissionPriceKRW = get_cutting_price(commissionPriceKRW,"KRW","backoffice")
		}

		// 정산금액 출력
		if(defaultCommissionType=='SACO'){
			//수수료방식 : 수수료액에서 소숫점 반올림
			var feePrice			= Math.round(num(commissionPriceKRW) * num_float(commissionRateObj.val()) / 100);
			var settlementAmount	= num(commissionPriceKRW) - feePrice;
		}else{
			switch(commissionTypeObj.val()){
				case	'SUCO' :	//공급가율 : 정산금액에서 소숫점 반올림
					var settlementAmount	= Math.round(num(commissionPriceKRW) / 100 * num_float(commissionRateObj.val()));
					break;
				case	'SUPR' :	//공급가액 : 정산금액에서 소숫점 반올림
					var settlementAmount	= Math.round(commissionRateObj.val());
					break;
				default	:
					var feePrice			= Math.round(num(commissionPriceKRW) * num_float(commissionRateObj.val()) / 100);
					var settlementAmount	= num(commissionPriceKRW) - feePrice;
					break;
			}
		}
		settlementAmountObj.html(comma(settlementAmount));

	});
}

function calulate_subOption_price(){
	var container = $("#suboptionLayer");
	// subSupplyPrice	subConsumerPrice	subPrice
	$("input[name='subPrice[]']",container).each(function(idx){

		$selector 					= $(this).closest("tr");
		var priceObj 				= $(this);
		var supplyPriceObj 			= $selector.find("input[name='subSupplyPrice[]']");
		var consumerPriceObj 		= $selector.find("input[name='subConsumerPrice[]']");
		var subReserveRateObj	 	= $selector.find("input[name='subReserveRate[]']");
		var subReserveUnit 			= $selector.find("select[name='subReserveUnit[]']").find("option:selected").val();
		var subReserveObj 			= $selector.find(".subReserve");
		var taxObj 					= $selector.find(".subTax");
		var supplyRateObj 			= $selector.find(".subSupplyRate");
		var discountRateObj 		= $selector.find(".subDiscountRate");
		var obj 					= { 0:supplyPriceObj.val(), 1:consumerPriceObj.val(), 2:priceObj.val(), 3:subReserveRateObj.val(), 4:subReserveUnit };
		var result 					= calulate_price(obj);
		var subSettlementAmountObj	= $selector.find(".subSettlementAmount");
		var subCommissionRateObj	= $selector.find("input[name='subCommissionRate[]']");
		var subCommissionTypeObj	= $selector.find("select[name='subCommissionType[]']");
		var defaultCommissionType	= $selector.find("input[name='default_commission_type']").val();

		if(typeof(subCommissionTypeObj.val()) == 'undefined'){
			var subCommissionTypeObj	= $selector.find("input[name='subCommissionType[]']");
		}

		if(result[0]>0 && result[0]!='Infinity') result[0] = result[0]+"%";
		else result[0] = "0%";
		supplyRateObj.html(result[0]);

		if(result[1]>0 && result[1]!='Infinity') result[1] = result[1]+"%";
		else result[1] = "0%";
		discountRateObj.html(result[1]);

		// TAX @2014-03-17
		if( (eval("$(\"input[name='tax']:checked\").val()") && $("input[name='tax']:checked").val()!='tax') || (eval("$('.goodsTax').val()") && $(".goodsTax").val()!='tax') ){
			result[2] = 0;
		}

		taxObj.html(result[2]);

		if(typeof result[3] != 'undefined') subReserveObj.html(result[3]);

		// 마진출력
		var net_profit = priceObj.val() - supplyPriceObj.val();
		$(".sub_net_profit",container).eq(idx).html(comma(net_profit));

		if(subCommissionTypeObj.val() != 'SUPR'){
			subCommissionRateObj.val(num_float(subCommissionRateObj.val()));
			if(subCommissionRateObj.val()>100) {
				subCommissionRateObj.val('100');
			}
		}

		//정산기준금액
		var commissionPriceKRW = priceObj.val();

		if(defaultCommissionType!='SACO' && subCommissionTypeObj.val() == "SUCO"){
			if(consumerPriceObj.val() > 0){
				commissionPriceKRW = consumerPriceObj.val();	//정가기준
			}else{
				commissionPriceKRW = 0;
			}
		}else{
			commissionPriceKRW = priceObj.val();	//판매가기준
		}

		if(commissionPriceKRW == 0){
			subSettlementAmountObj.html('0');
			return true;		//continue;
		}

		// 정산금액 출력
		if(defaultCommissionType=='SACO'){
			//수수료방식 : 수수료액에서 소숫점 반올림
			var feePrice			= Math.round(num(commissionPriceKRW) * num_float(subCommissionRateObj.val()) / 100);
			var subSettlementAmount	= num(commissionPriceKRW) - feePrice;
		}else{
			// 정산금액 출력
			switch(subCommissionTypeObj.val()){
					case	'SUCO' :	//공급가율 : 정산금액에서 소숫점 반올림
						var subSettlementAmount	= Math.round(num(commissionPriceKRW) / 100 * num_float(subCommissionRateObj.val()));
					break;
					case	'SUPR' :	//공급가액 : 정산금액에서 소숫점 반올림
						var subSettlementAmount	= Math.round(subCommissionRateObj.val());
					break;
				default	:
						var feePrice			= Math.round(num(commissionPriceKRW) * num_float(subCommissionRateObj.val()) / 100);
						var subSettlementAmount	= num(commissionPriceKRW) - feePrice;
					break;
			}
		}

		// 정산가 소수점 버림 처리 - 이정록 - 2016-07-08
		subSettlementAmountObj.html(comma(subSettlementAmount));
	});
}

function calulate_price(obj){
	// supply,consumer,price,reserveRate,reserveUnit
	// 금액에 콤마제거
	if(typeof obj != "undefined") {
		$.each(obj,function(e,val){
			if(e != '4') {
				if(typeof val == "undefined")  obj[e] = 0;
				else obj[e] = uncomma(val);
			}
		});
	}
	var rate;
	var result 		= new Array('','','');
	var supply 		= obj[0];
	var consumer 	= obj[1];
	var price 		= obj[2];
	if( obj[3] ) var reserveRate = obj[3];
	if( obj[4] ) var reserveUnit = obj[4];

	// 매입율
	if( consumer && supply ){
		result[0] =  Math.floor( supply / consumer * 100 );
	}

	// 할인율
	if( consumer && price ){
		result[1] =  100 - Math.floor( price / consumer * 100);
	}

	// 부가세
	if( price ){
		result[2] =  get_currency_price(Math.floor( price - (price / 1.1)));
	}

	// 지급마일리지
	//{ 0:supplyPriceObj.val(), 1:consumerPriceObj.val(), 2:priceObj.val(), 3:subReserveRateObj.val(), 4:subReserveUnit };
	if( reserveRate && reserveUnit ){
		if( reserveUnit == 'percent' ) {
			if( price ){
				rate 		= reserveRate / 100;
				result[3] =  get_currency_price( price * rate ,2) ;
			}
		}else{
			result[3] =  get_currency_price(reserveRate,2);
		}
	}
	return result;
}

// 옵션일괄적용 활성화
function check_button_optionBatch(){
	if($("input[name='defaultOption']")) $("#optionBatch").attr("disabled",false);
	else $("#optionBatch").attr("disabled",true);
}

function default_option_input(){
	var copyOptionTd;
	var len = $("#optionLayer table tr.optionTr").eq(0).find("td").length;
	if(len > 11){
		for(var i=0;i<len-11;i++){
			$("#optionLayer table tr.optionTr").eq(0).find("td").eq(0).remove();
		}
	}
	if( $("#optionLayer table tr").eq(1).children("td").html() ) copyOptionTd = $("#optionLayer table tr").eq(1).children("td");
	else copyOptionTd = $("#optionLayer table tr").eq(2).children("td");
	return copyOptionTd;
}

function get_option_title(){
	var point_text = $("#optionLayer .point_text").text();
	var tag;
	tag = '<tr>';
	tag += '<th class="its-th-align center" rowspan="2"><span class="btn-plus"><button type="button" id="addOption"></button></span></th>';
	tag += '<th class="its-th-align center" rowspan="2">기준할인가</th>';
	tag += '<th class="its-th-align center">필수옵션</th>';
	tag += '<th class="its-th-align center" rowspan="2">정산 금액</th>';
	tag += '<th class="its-th-align center" rowspan="2">수수료율</th>';
	tag += '<th class="its-th-align center" rowspan="2">평균 매입가</th>';
	tag += '<th class="its-th-align center" rowspan="2">평균 매입율</th>';
	tag += '<th class="its-th-align center" rowspan="2">정가(소비자가/시중가)</th>';
	tag += '<th class="its-th-align center" rowspan="2">마진 / 할인가(판매가)</th>';
	tag += '<th class="its-th-align center" rowspan="2">할인율</th>';
	tag += '<th class="its-th-align center" rowspan="2">부가세</th>';
	tag += '<th class="its-th-align center" rowspan="2">재고(가용)\n';
	tag += '</th>';
	tag += '<th class="its-th-align center" rowspan="2">지급 마일리지';
	tag += '<select name="reserve_policy">';
	tag += '<option value="shop">통합정책</option>';
	tag += '<option value="goods">개별정책</option>';
	tag += '</select>';
	tag += "<div style='color:#999999;' class='point_text'>"+point_text+"</div>";
	tag += '</th>';
	tag += '<th class="its-th-align center" rowspan="2">옵션정보</th>';
	tag += '</tr>';

	tag += '<tr>';
	$("#optionMakePopup table tbody tr").each(function(idx){

		tag += '<th class="its-th-align center">';
		tag += $(this).find("input[name='optionMakeName[]']").val();
		tag += '<input type="hidden" name="optionTitle[]" value="'+$(this).find("input[name='optionMakeName[]']").val()+'" />';
		tag += '<input type="hidden" name="optionType[]" value="'+$(this).find("select[name='optionMakeId[]'] option:selected").val()+'" />';
		tag += '</th>';
	});
	tag += '</tr>';


	return tag;
}

function make_option(){
	var key = 0;
	var cols = 0;
	var optName = new Array();
	var optionType = new Array();
	var optValue = new Array();
	var optPrice = new Array();
	var optCode = new Array();
	var result = new Array();
	var resulttype = new Array();
	var resultPrice = new Array();
	var preTag, tmp;
	var pattern = /[\,]/;

	$("#optionMakePopup table tbody tr").each(function(idx){
		optionType[idx] = $(this).find("select[name='optionMakeId[]'] option:selected").val();

		tmp = $(this).find("input[name='optionMakeValue[]']").val();
		optValue[idx] = tmp.split(',');
		tmp = $(this).find("input[name='optionMakePrice[]']").val();
		optPrice[idx] = tmp.split(',');

		tmp = $(this).find("input[name='optionMakeCode[]']").val();
		optCode[idx] = tmp.split(',');

		cols = idx;
	});

	/* 옵션값 공백 체크 */
	for(var i=0;i<optValue.length;i++){
		for(var j=0;j<optValue[i].length;j++){
			if(optValue[i][j].length==0) {
				openDialogAlert("옵션값을 입력해주세요.",400,140,function(){
					$("#optionMakePopup input[name='optionMakeValue[]']").filter(function(){
						return $(this).val().length==0;
					}).eq(0).focus();
				});
				return false;
			}
		}
	}

	var clone = $("#optionLayer table").clone();
	var copyOptionTd = default_option_input();
	var tag = get_option_title();

	/* 가용재고 : 원래는 유지해야하지만, 일단 첫번 0으로 초기화시킴 */
	//copyOptionTd.find("input[name='badstock[]']").val('0');
	//copyOptionTd.find("input[name='reservation25[]']").val('0');
	//copyOptionTd.find("input[name='reservation25[]']").val('0');
	//copyOptionTd.find("input[name='unUsableStock[]']").val('0');
	//copyOptionTd.find("span.optionUsableStock").html(comma(num(copyOptionTd.find("input[name='stock[]']").val())));

	for ( var i=0;i<optValue.length;i++ ){
			if(!optValue[i]) continue;
		for ( var j=0;j<optValue[i].length;j++ ){
			if(! optPrice[i][j] ) optPrice[i][j] = 0;
		}
	}

	for (var i=0;i<optValue.length;i++){
		if(!optValue[i]) continue;
		if( i == 0 ){
			result = optValue[i];
			resultcode = optCode[i];
		} else {
			result = merge_option(result,optValue[i]);
			resultcode = merge_option(resultcode,optCode[i]);
		}
	}


	for (var i=0;i<result.length;i++){
		result[i] = result[i].split(',');
		if(resultcode[i]){
			resultcode[i] = resultcode[i].split(',');
		}else{
			resultcode[i] = '';
		}
	}

	for (var i=0;i<optPrice.length;i++){
		if(i == 0 && optPrice[i] ){
			resultPrice = optPrice[i];
		}else if( optPrice[i] ){
			resultPrice = merge_option(resultPrice,optPrice[i]);
		}
	}

	for (var i=0;i<resultPrice.length;i++){
		if (pattern.test(resultPrice[i])){
			resultPrice[i] = resultPrice[i].split(',');
		}
	}

	for (var i=0;i<result.length;i++){
		key=0;
		tag += '<tr class="optionTr">';
		for (var j=0;j<result[i].length;j++){
			var tmpType = $.trim(optionType[key]);
			var tmpValue = $.trim(result[i][j]);
			var tmpCode = $.trim(resultcode[i][j]);
			//tag += '<td class="center"><input type="text" size="10" name="opt['+key+'][]" value="' + tmpValue + '" /></td>';
			if( tmpType == 'direct' || !tmpType ) {
				tag += '<td class="center"><input type="text" size="10" name="opt['+key+'][]" value="' + tmpValue + '" class="line "/>';
			}else{
				tag += '<td class="center"><input type="text" size="10" name="opt['+key+'][]" value="' + tmpValue + '" class="line input-box-default-text-code "/>';
			}
			tag += '<input type="hidden" size="10" name="optcode['+key+'][]" value="' + tmpCode + '" /></td>';
			key++;
		}
		tag += '</tr>';
	}

	clone.empty();//clone.find("*").remove();
	clone.html(tag);
	clone.find("tr").eq(0).find("th").eq(2).attr('colspan',cols+1);

	key = 0;
	clone.find("tr").each(function(idx){
		if(idx > 1) {
			preTag = '<td class="center">';
			preTag += '<span class="btn-minus"><button type="button" class="removeOption"></button></span>';
			preTag += '</td>';
			if(key == 0){
				preTag += '<td class="center"><input type="radio" name="defaultOption" value="'+result[key]+'" checked="checked" /></td>';
			}else{
				preTag += '<td class="center"><input type="radio" name="defaultOption" value="'+result[key]+'" /></td>';
			}
			sum = 0;
			if(result[key]){
				for (var i=0;i<result[key].length;i++){
					if(result[key]==result[key][i]){
						sum += Math.floor(resultPrice[key]);
					}else{
						sum += Math.floor(resultPrice[key][i]);
					}
				}
			}

			copyOptionTd.children("input[name='price[]']").val(sum);
			$(this).prepend(preTag);
			$(this).append(copyOptionTd.clone());
			key++;
		}
	});

	// 수수료
	var default_commission_rate = get_default_commission_rate();

	$("input[name='commissionRate[]']",clone).val(default_commission_rate);

	$("#optionLayer").html( clone );
	calulate_option_price();

	try{
	if(eval("provider_seq"))
	{
		var provider_seq = provider_seq;
	}
	}catch(e){
		var provider_seq = '';
	}

	if(provider_seq != undefined && provider_seq!=''){
		$("select[name='reserve_policy'] option").each(function(){
			if($(this).parent().val()!=$(this).attr("value")){
				$(this).attr("disabled",true);
			}
		});
	}
}

function batch_option_price(){
	var defaultSupplyPrice;
	var defaultConsumerPrice;
	var reserveRate,reserveUnit,reserve,price,stock;
	var tax,supplyRate,discountRate,infomation;
	var socialcpuseopen = $("#socialcpuseopen").val();

	$("input[name='defaultOption']").each(function(idx){
		if( $(this).attr("checked") ){
			supplyPrice		= $("input[name='supplyPrice[]']").eq(idx).val();
			if(socialcpuseopen){
				coupon_input	= $("input[name='coupon_input[]']").eq(idx).val();
			}
			coupon_input	= $("input[name='coupon_input[]']").eq(idx).val();
			consumerPrice	= $("input[name='consumerPrice[]']").eq(idx).val();
			price			= $("input[name='price[]']").eq(idx).val();
			reserveRate 	= $("input[name='reserveRate[]']").eq(idx).val();
			reserveUnit 	= $("select[name='reserveUnit[]']").eq(idx).find("option:selected").attr("value");
			reserve			= $("input[name='reserve[]']").eq(idx).val();
			stock			= $("input[name='stock[]']").eq(idx).val();
			infomation		= $("textarea[name='infomation[]']").eq(idx).val();
			tax 			= $(".tax").eq(idx).html();
			supplyRate 		= $(".supplyRate").eq(idx).html();
			discountRate    = $(".discountRate").eq(idx).html();
			commissionRate	= $("input[name='commissionRate[]']").eq(idx).val();
		}
	});
	$("input[name='defaultOption']").each(function(idx){
		$("input[name='supplyPrice[]']").eq(idx).val(supplyPrice);
		if(socialcpuseopen){
			$("input[name='coupon_input[]']").eq(idx).val(coupon_input);
		}
		$("input[name='coupon_input[]']").eq(idx).val(coupon_input);
		$("input[name='consumerPrice[]']").eq(idx).val(consumerPrice);
		$("input[name='price[]']").eq(idx).val(price);
		$("input[name='reserveRate[]']").eq(idx).val(reserveRate);
		$("select[name='reserveUnit[]']").eq(idx).find("option[value='"+reserveUnit+"']").attr("selected",true);
		$("input[name='reserve[]']").eq(idx).val(reserve);
		$("input[name='stock[]']").eq(idx).val(stock);
		$("textarea[name='infomation[]']").eq(idx).val(infomation);
		$(".tax").eq(idx).html(tax);

		$(".supplyRate").eq(idx).html(supplyRate);
		$(".discountRate").eq(idx).html(discountRate);
		$("input[name='commissionRate[]']").eq(idx).val(commissionRate);
	});
}

// 옵션 사용안함 시 기본 옵션 초기화
function get_default_option(){
	var goodsSeq		= $("input[name='goodsSeq']").val();
	var point_text		= $("#optionLayer .point_text").html();
	var policy			= $("select[name='reserve_policy'] option:selected").val();
	if	(!policy)	policy			= $("input[name='reserve_policy']").val();
	var policy_shop		= '';
	var policy_goods	= '';
	if	(policy == 'goods')	policy_goods	= 'selected';
	else					policy_shop		= 'selected';

	if ( socialcpuse_flag ) {
		var socialcp_input_type = $("input[name='socialcp_input_type']:checked").val();
		var couponinputsubtitle = '';
		if( socialcp_input_type == 'price' ) {
			couponinputsubtitle = '금액';
		}else{
			couponinputsubtitle = '횟수';
		}
	}

	// 수수료
	var default_commission_rate = get_default_commission_rate();
	var default_commission_type	= $("input[name='default_commission_type']").val();
	var store_text			= $("#optionLayer .store_text").attr('title');
	var price_text			= $("#optionLayer .price_text").attr('title');
	var point_text			= $("#optionLayer .point_text").attr('title');
	var optionmemo_text		= $("#optionLayer .optionmemo_text").attr('title');
	var stock_text			= $("#optionLayer .stock_text").attr('title');
	var ablestock_text		= $("#optionLayer .ablestock_text").attr('title');
	var safestock_text		= $("#optionLayer .safestock_text").attr('title');
	var storeinfo_title		= $("#optionLayer .storeinfo_title").text();
	var goodsCode			= $('#goodsCode').val();
	var scmStatus			= chk_scm_status();
	var provider_seq	= $("select[name='provider_seq_selector']").val();
	if	(!provider_seq)	provider_seq = $("input[name='provider_seq']").val();
	if	(!provider_seq)	provider_seq = 999999;

	var default_commission_symbol = '%';
	if(default_commission_type == "SUPR") default_commission_symbol = gl_basic_currency;

	var firstTds = $("#optionDefaultForm").html();

	return firstTds;
}




function default_suboption_input(){

	// 수수료
	var default_commission_rate = get_default_commission_rate();

	var firstTds = '<td class="center subSettlementAmount"></td>';
	firstTds += '<td style="padding-right: 10px;" class="right"><input style="text-align: right;" class="line onlynumber input-box-default-text" name="subCommissionRate[]" value="'+default_commission_rate+'" size="3" type="text">%</td>';
	firstTds += '<td class="center"><input type="text" name="subSupplyPrice[]" class="line onlynumber" style="text-align:right" size="10" /></td>';
	firstTds += '<td class="right subSupplyRate" style="padding-right:10px"></td>';
	firstTds += '<td class="center"><input type="text" name="subConsumerPrice[]" class="line onlynumber" style="text-align:right" size="10" /></td>';
	firstTds += '<td class="center"><input type="hidden" class="sub_net_profit" value="0" > <input type="text" name="subPrice[]" class="line onlynumber" style="text-align:right" size="10" /></td>';
	firstTds += '<td class="right subDiscountRate" style="padding-right:10px"></td>';
	firstTds += '<td class="right subTax" style="padding-right:10px"></td>';
	firstTds += '<td class="center"><input type="text" name="subStock[]" class="line onlynumber" style="text-align:right" size="5" /></td>';
	firstTds += '<td class="center"><input style="text-align: right;" class="line onlynumber input-box-default-text" name="subReserveRate[]" value="0" size="3" type="text"><select class="line" name="subReserveUnit[]"><option value="percent" selected>%</option><option value="won">원</option>	</select><input style="text-align: right;" class="line onlynumber input-box-default-text" name="subReserve[]" value="0" size="5" type="text" readonly /></td>';
	return firstTds;
}

function get_suboption_title(){
	var point_text = $("#optionLayer .point_text").text();
	tagTitle = '<thead>';
	tagTitle += '<tr>';
	tagTitle += '<th class="its-th-align center" rowspan="2"><span class="btn-minus"><button type="button" id="addSuboptionButton"></button></span></th>';
	tagTitle += '<th class="its-th-align center" colspan="3">추가옵션</th>';
	tagTitle += '<th class="its-th-align center" rowspan="2">정산 금액</th>';
	tagTitle += '<th class="its-th-align center" rowspan="2">수수료율</th>';
	tagTitle += '<th class="its-th-align center" rowspan="2">평균 매입가</th>';
	tagTitle += '<th class="its-th-align center" rowspan="2">평균 매입율</th>';
	tagTitle += '<th class="its-th-align center" rowspan="2">정가(소비자가/시중가)</th>';
	tagTitle += '<th class="its-th-align center" rowspan="2">마진 / 할인가(판매가)</th>';
	tagTitle += '<th class="its-th-align center" rowspan="2">할인율</th>';
	tagTitle += '<th class="its-th-align center" rowspan="2">부가세</th>';
	tagTitle += '<th class="its-th-align center" rowspan="2">재고(가용)</th>';
	tagTitle += '<th class="its-th-align center" rowspan="2">';
	tagTitle += "지급 마일리지<div style='color:#999999;' class='point_text'>"+point_text+"</div>";
	tagTitle += '</th>';
	tagTitle += '</tr>';
	tagTitle += '<tr>';
	tagTitle += '<th class="its-th-align center">필수여부</th>';
	tagTitle += '<th class="its-th-align center">옵션명</th>';
	tagTitle += '<th class="its-th-align center">옵션값</th>';
	tagTitle += '</tr>';
	tagTitle += '</thead>';

	return tagTitle;
}

function get_suboption_input(optName, optValue, optCode, optType){

	var tag = '<tbody>';
	for (var i=0;i<optValue.length;i++){

			if(!optValue[i]) continue;
		for (var j=0;j < optValue[i].length;j++){
			var tmpName = $.trim(optName[i]);
			var tmpType = $.trim(optType[i]);
			var tmpCode = $.trim(optCode[i][j]);
			var tmpValue = $.trim(optValue[i][j]);
			if(tmpValue){
				tag += '<tr class="suboptionTr">';
				tag += '<td class="center"><span class="btn-minus"><button type="button" class="delSuboptionButton"></button></span></td>';
				if(j == 0){
					tag += '<td class="center"><input type="checkbox" size="10" name="subRequired[]" value="y" /></td>';
					//tag += '<td class="center"><input type="text" size="10" name="suboptTitle[]" value="' + tmpName + '" />';
					if( tmpType == 'direct' || !tmpType ) {
						tag += '<td class="center"><input type="text" size="10" name="suboptTitle[]" value="' + tmpName + '"  class="line " />';
					}else{
						tag += '<td class="center"><input type="text" size="10" name="suboptTitle[]" value="' + tmpName + '"  class="line input-box-default-text-code " />';
					}
					tag += '<input type="hidden" size="10" name="suboptType[]" value="' + tmpType + '" /></td>';
				}else{
					tag += '<td class="center"></td>';
					tag += '<td class="center"></td>';
				}

				//tag += '<td class="center"><input type="text" size="10" name="subopt[' + i + '][]" value="' + tmpValue + '" />';
				if( tmpType == 'direct' || !tmpType ) {
					tag += '<td class="center"><input type="text" size="10" name="subopt[' + i + '][]" value="' + tmpValue + '"  class="line " />';
				}else{
					tag += '<td class="center"><input type="text" size="10" name="subopt[' + i + '][]" value="' + tmpValue + '"  class="line input-box-default-text-code " />';
				}
				tag += '<input type="hidden" size="10" name="suboptCode[' + i + '][]" value="' + tmpCode + '" /></td></tr>';
			}
		}
	}
	tag += '</tbody>';
	return tag;
}

function get_suboption_price(optPrice){
	var k = 0;
	var subPrice = new Array();
	for (var i=0;i<optPrice.length;i++){
		for (var j=0;j < optPrice[i].length;j++){
			subPrice[k] = optPrice[i][j];
			k++;
		}
	}
	return subPrice;
}

function make_suboption(){
	var optValue = new Array();
	var optPrice = new Array();
	var optName = new Array();
	var subPrice = new Array();

	var optCode = new Array();
	var optType = new Array();

	var copyOptionTd, tmp, tag;
	var makeNameObj = $("input[name='suboptionMakeName[]']");
	var makeValueObj = $("input[name='suboptionMakeValue[]']");
	var makePriceObj = $("input[name='suboptionMakePrice[]']");
	var makeCodeObj = $("input[name='suboptionMakeCode[]']");
	var makeTypeObj = $("input[name='suboptionMakeType[]']");
	$("#suboptionLayer").html("<table class='info-table-style' width='100%'></table>");
	var clone = $("div#suboptionLayer table").clone();

	var tagTitle = get_suboption_title();
	var firstTdsTag = default_suboption_input();

	var nameArr = new Array();
	makeNameObj.each(function(idx){
		if(!$.inArray($(this).val(),nameArr)){
			alert("옵션명이 중복되었습니다.");
			return;
		}
		nameArr[idx] = $(this).val();
		optName[idx] = $(this).val();
		tmp = makeValueObj.eq(idx).val();
		optValue[idx] = tmp.split(',');
		tmp = makePriceObj.eq(idx).val();
		optPrice[idx] = tmp.split(',');

		tmp = makeCodeObj.eq(idx).val();
		optCode[idx] = tmp.split(',');
		tmp = makeTypeObj.eq(idx).val();
		optType[idx] = tmp.split(',');

		cols = idx;
	});

	for (var i=0;i < optValue.length;i++){
			if(!optValue[i]) continue;
		for (var j=0;j < optValue[i].length;j++){
			if( ! optPrice[i] ) optPrice[i] = new Array();
			if( ! optPrice[i][j] ){
				optPrice[i][j] = 0;
			}
		}
	}

	subPrice = get_suboption_price(optPrice);
	tag = get_suboption_input(optName, optValue, optCode, optType);
	clone.children("thead").remove();
	clone.children("tbody").remove();

	clone.html( tagTitle );
	clone.append( tag );

	clone.find("tbody tr").each(function(idx){
		$(this).append( firstTdsTag );
	});

	clone.find("input[name='subRequired[]']").each(function(idx){
		   $(this).val(idx);
	});

	clone.find("input[name='subPrice[]']").each(function(idx){
		$(this).val(subPrice[idx]);
	});

	// 수수료
	var default_commission_rate = get_default_commission_rate();

	$("input[name='commissionRate[]']",clone).val(default_commission_rate);

	$("#suboptionLayer").html( clone );
	calulate_subOption_price();
}

/* 추가옵션가격 일괄 적용 */
function batch_suboption_price(){
	var n = 0;
	var supplyPrice,consumerPrice,price;
	var subReserveRate,subReserveUnit,subReserve,subStock;
	$("div#suboptionLayer input[name='suboptTitle[]']").each(function(i){
		$("div#suboptionLayer input[name='subopt["+i+"][]']").each(function(j){
			if(j == 0){
				supplyPrice		= $("input[name='subSupplyPrice[]']").eq(n).val();
				//subcoupon_input	= $("input[name='subcoupon_input[]']").eq(n).val();
				consumerPrice	= $("input[name='subConsumerPrice[]']").eq(n).val();
				price			= $("input[name='subPrice[]']").eq(n).val();
				subStock			= $("input[name='subStock[]']").eq(n).val();
				subSafeStock		= $("input[name='subSafeStock[]']").eq(n).val();
				subReserveRate 	= $("input[name='subReserveRate[]']").eq(n).val();
				subReserveUnit 	= $("select[name='subReserveUnit[]']").eq(n).find("option:selected").attr("value");
				subReserve			= $("input[name='subReserve[]']").eq(n).val();
			}else{
				$("input[name='subSupplyPrice[]']").eq(n).val(supplyPrice);
				$("input[name='subConsumerPrice[]']").eq(n).val(consumerPrice);
				//$("input[name='subcoupon_input[]']").eq(n).val(subcoupon_input);
				$("input[name='subPrice[]']").eq(n).val(price);

				$("input[name='subStock[]']").eq(n).val(subStock);
				$("input[name='subSafeStock[]']").eq(n).val(subSafeStock);
				$("input[name='subReserveRate[]']").eq(n).val(subReserveRate);
				$("select[name='subReserveUnit[]']").eq(n).find("option[value='"+subReserveUnit+"']").attr("selected",true);
				$("input[name='subReserve[]']").eq(n).val(get_currency_price(subReserve,2));
			}
			n++;
		});
	});
}

/* 추가입력 폼 입력타입 체크 */
function check_memberInputMakeForm(){
	var inputlimit;
	var text	= "";
	var img		= "<input type=\"hidden\" name=\"memberInputMakeLimit[]\" value=\"file\" /> 2M이하";
	$("select[name='memberInputMakeForm[]'] option:selected").each( function(n){
		if( $(this).val() == 'file'){
			$(this).parents('td').children("span.textLimit").hide();
			$(this).parents('td').children("span.uploadLimit").show();
			$(this).parents('td').children("span.textLimit").html("");
			$(this).parents('td').children("span.uploadLimit").html(img);
		}else{
			inputlimit		= $("input[name='memberInputMakeLimit[]']").eq(n).val();
			if(inputlimit>0){
				text	= "<input type=\"text\" name=\"memberInputMakeLimit[]\" class=\"line\" size=\"2\" value=\""+inputlimit+"\" />자 이내";
			}else{
				text = "<input type=\"text\" name=\"memberInputMakeLimit[]\" class=\"line\" size=\"2\" value=\"0\" />자 이내";
			}
			$(this).parents('td').children("span.textLimit").show();
			$(this).parents('td').children("span.uploadLimit").hide();
			$(this).parents('td').children("span.textLimit").html(text);
			$(this).parents('td').children("span.uploadLimit").html("");
		}
	});
}

/* 추가입력 폼 생성 */
function get_memberInput_title(){
	var tag = '';
	tag += '<table  class="table_basic v7 pd5">';
	tag += '<thead>';
	tag += '<tr>';
	tag += '	<th width="100">입력필수</th>';
	tag += '	<th width="250">옵션명</th>';
	tag += '	<th>옵션값</th>';
	tag += '</tr>';
	tag += '</thead>';
	tag += '<tbody>';
	tag += '</tbody>';
	tag += '</table>';
	return tag;
}


/* 추가입력 폼 내용 생성 */
function get_memberInput(){
	var tag = '';
	var iName,iForm,iRequire,iRequireView;

	$("div#memberInputDialog input[name='memberInputMakeName[]']").each(function(i){
		iName		= $(this).val();
		iForm		= $("select[name='memberInputMakeForm[]']").eq(i).children("option:selected").val();
		iLimit		= $("input[name='memberInputMakeLimit[]']").eq(i).val();
		iRequire	= ($("input[name='memberInputRequire[]']").eq(i).is(':checked')) ? 'require' : '';
		iRequireYN	= (iRequire == 'require') ? 'Y' : 'N';

		switch (iForm) {
			case 'text' :
				iFormTxt	= '텍스트박스('+iLimit+'자 이내)';
				break;

			case 'edit' :
				iFormTxt	= '에디트박스('+iLimit+'자 이내)';
				break;

			case 'file' :
				iFormTxt	= '이미지 업로드 (2M이하)';
				break;
		}

		tag			+= '<tr>';
		tag			+= '<td class="center">'+ iRequireYN;
		tag			+= '<input type="hidden" name="memberInputRequire[]" value="' + iRequire + '" />';
		tag			+= '<input type="hidden" name="memberInputName[]" value="' + iName + '">';
		tag			+= '<input type="hidden" name="memberInputForm[]" value="' + iForm + '">';
		tag			+= '<input type="hidden" name="memberInputLimit[]" value=" '+ iLimit + '">';
		tag			+= '</td>';
		tag			+= '<td class="center">' + iName + '</td>';
		tag			+= '<td>' + iFormTxt + '</td>';
		tag			+= '</tr>';
	});
	return tag;
}

function iconUpdateComplete(){
	//$(obj).closest("form").submit();
	$("form[name='iconUpdateForm']").submit();
}
function set_goods_icon(){
	$.getJSON('icon', function(res) {
		var width_sum = 0;
		$("div#goodsIconPopup .icon_list>tbody tr").not('tr.nothing').remove();
		if(res.length > 0 ) $("div#goodsIconPopup .icon_list>tbody>tr.nothing").addClass("hide");
		$.each(res, function(_key, _data){
			var tag = '<tr>';

			width_sum += _data.width+60;
			if (width_sum >= 450) {
				//tag += '</ul><ul>';
				width_sum = 0;
			}
			
			tag += '<td class="center">'+(res.length - _key)+'</td>';
			tag += '<td class="center">';
			tag += '	<button type="button" class="resp_btn v2 icon">선택</button>';
			tag += '	<input type="hidden" name="goodsIconCode[]" value="'+_data.codecd+'">';
			tag += '</td>';
			if(_data.value == "사용자"){
				tag += '<td class="center">추가</td>';
			}else{
				tag += '<td class="center">기본</td>';
			}
			tag += '<td class="center"><img src="/data/icon/goods/'+_data.codecd+'.gif" class="hand icon" style="max-height:20px;" align="absmiddle" />('+_data.codecd+')</td>';
			if(_data.value == "사용자"){
				tag += '<td class="center"><button type="button" class="btn_minus" onClick=\"iconDel(this)\"" data-code="'+_data.codecd+'"></button></td>';
			}else{
				tag += '<td class="center"></td>';
			}
			tag += '</tr>';

			$("div#goodsIconPopup .icon_list>tbody").append(tag);

		});
	});
}

// 아이콘 삭제
function iconDel(obj){

	if(typeof $(obj).attr("data-code")  == "undefined"){
		alert("삭제할 아이콘이 없습니다.");
		return false;
	}

	var msg = "아이콘 삭제 시 해당 아이콘을 사용 중인 모든 상품에서 삭제되며 복구가 불가합니다. 아이콘을 삭제하시겠습니까?"
	if(confirm(msg) ) {
		$.ajax({
			type: "get",
			url: "../goods_process/set_icon_delete?code="+$(obj).attr("data-code"),
			success: function(result){
				if(result == "success"){
					set_goods_icon();
				}else{
					alert("아이콘 삭제 실패!");
					return false;
				}
			}
		});
	}
}

/* 가격 대체 문구 */
function show_stringPrice(){
	var obj = $("input[name='stringPriceUse']");
	if( obj.attr("checked") ){
		obj.parent().parent().find("span").show();
	}else{
		obj.parent().parent().find("span").hide();
	}
}

/* 복수구매 할인 */
function show_multiDiscountUse(){
	var obj = $("input[name='multiDiscountUse']");
	if( obj.attr("checked") ){
		obj.parent().parent().find("span").show();
	}else{
		obj.parent().parent().find("span").hide();
	}
}

/* 필수옵션 사용/사용안함 */
function show_optionUse(useCheck){

	var obj			= $("input[name='optionUse'][value='1']");
	if(typeof useCheck == "undefined") useCheck = false;

	if(useCheck == false){
		useCheck = obj.is(':checked');
	}

	var hideSpanObj	= obj.closest('td').find("span.optionuse-lay");
	// 사용함 체크
	if( useCheck ){
		//$("#optionPreview").parent().show();
		obj.closest("table").find("tr.optionCreate").show();
		//hideSpanObj.find("select[name='optionViewType']").attr('disabled', false);
		hideSpanObj.find("input[name='frequentlytypeoptck']").attr('disabled', false);
		hideSpanObj.find("select[name='frequentlytypeopt']").attr('disabled', false);
		hideSpanObj.find('button#optionMake').closest('span').removeClass('gray').addClass('cyanblue');
		$("form div#optionRegistLayer").show();
		$("form div.package_setting").hide();
	}else{
		//필수옵션 사용안함.
		var default_option = get_default_option();
		$("#optionLayer").html(default_option);			// 기본 필수 옵션 1개 초기화
		//help_tooltip();
		//setOptionStockSetText();

		$("input[name='consumerPrice[]']").on("blur",function(){calulate_option_price();});
		$("input[name='price[]']").on("blur",function(){calulate_option_price();});
		if (goodsObj.sellerMode != 'SELLER') {
			$("input[name='supplyPrice[]']").on("blur",function(){calulate_option_price();});
			$("input[name='reserveRate[]']").on("blur",function(){calulate_option_price();});
			$("select[name='reserveUnit[]']").on("change",function(){calulate_option_price();});
			$("input[name='reserve[]']").on("blur",function(){calulate_option_price();});
			$("input[name='tax']").on("click",function(){calulate_option_price();});
			$("input[name='commissionRate[]']").on("blur",function(){calulate_option_price();});
			$("input[name='commissionRate[]']").on("change",function(e){
				var float_cnt	= this.value.match(/\.[0-9]+/g);
				if(float_cnt > 0 && float_cnt.toString().length > 3){
					alert('소숫점 2자리까지 가능합니다.(2자리 초과 절삭)');
				}
				var charge		= Math.floor(this.value * 100) / 100;
				this.value		= charge;

			});
		}
		$("#optionPreview").parent().hide();
		obj.closest("table").find("tr.optionCreate").hide();
		//hideSpanObj.find("select[name='optionViewType']").attr('disabled', true);
		hideSpanObj.find("input[name='frequentlytypeoptck']").attr('disabled', true);
		hideSpanObj.find("select[name='frequentlytypeopt']").attr('disabled', true);
		hideSpanObj.find('button#optionMake').closest('span').addClass('gray').removeClass('cyanblue');
		$("form div#optionRegistLayer").hide();
		$("form div.package_setting").show();
	}

	try{
		if(eval("provider_seq"))
		{
			var provider_seq = provider_seq;
		}
	}catch(e){
		var provider_seq = '';
	}

	if(provider_seq != undefined && provider_seq!=''){
		$("select[name='reserve_policy'] option").each(function(){
			if($(this).parent().val()!=$(this).attr("value")){
				$(this).attr("disabled",true);
			}
			$("input[name='reserveRate[]'], select[name='reserveUnit[]'], input[name='reserve[]'], input[name='subReserveRate[]'], select[name='subReserveUnit[]'], input[name='subReserve[]']").css('opacity',0.5);
			$("input[name='reserveRate[]'], select[name='reserveUnit[]'], input[name='subReserveRate[]'], select[name='subReserveUnit[]']").attr('readonly',true);
			$("select[name='reserveUnit[]'] option[value!='percent'], select[name='subReserveUnit[]'] option[value!='percent']").attr('disabled',true);
		});
	}
	calulate_option_price();
	package_unit_ea_display();
}

/* 추가옵션 사용 */
function show_subOptionUse(){
	var obj			= $("input[name='subOptionUse'][value='1']");
	var hideSpanObj	= $("span.hideSpan");

	if( obj.is(":checked")){
		//$(".suboptionPriview").show();
		obj.closest("table").find("tr.subOptionCreate").show();		// 추가구성옵션 사용여부에 따라 추가구성옵션 생성 버튼
		hideSpanObj.find("input[name='frequentlytypesuboptck']").attr('disabled', false);
		hideSpanObj.find("select[name='frequentlytypesubopt']").attr('disabled', false);
		hideSpanObj.find('button#subOptionMake').closest('span').removeClass('gray').addClass('cyanblue');
		//$("#suboptionIndividualSettingLayer").show();
	}else{
		$(".suboptionPriview").hide();
		obj.closest("table").find("tr.subOptionCreate").hide();
		hideSpanObj.find("input[name='frequentlytypesuboptck']").attr('disabled', true);
		hideSpanObj.find("select[name='frequentlytypesubopt']").attr('disabled', true);
		hideSpanObj.find('button#subOptionMake').closest('span').addClass('gray').removeClass('cyanblue');
		$("#suboptionLayer").html("");
		//$("#suboptionIndividualSettingLayer").hide();
	}
	calulate_subOption_price();
}

/* 구매자 추가입력 */
function show_memberInputUse(){
	var obj = $("input[name='memberInputUse'][value='1']");
	var hideSpanObj	= obj.parent().parent().find("span.hideSpan");
	if( obj.is(':checked') ){
		obj.closest("table").find("tr.subInputCreate").show();		// 추가입력옵션 사용여부에 따라 추가구성옵션 생성 버튼
		//hideSpanObj.find("input[name='frequentlytypeinputoptck']").attr('disabled', false);
		//hideSpanObj.find("select[name='frequentlytypeinputopt']").attr('disabled', false);
		//hideSpanObj.find('button#memberInputMake').closest('span').removeClass('gray').addClass('cyanblue');
	}else{
		obj.closest("table").find("tr.subInputCreate").hide();		// 추가입력옵션 사용여부에 따라 추가구성옵션 생성 버튼
		//hideSpanObj.find("input[name='frequentlytypeinputoptck']").attr('disabled', true);
		//hideSpanObj.find("select[name='frequentlytypeinputopt']").attr('disabled', true);
		//hideSpanObj.find('button#memberInputMake').closest('span').addClass('gray').removeClass('cyanblue');
		$("#memberInputLayer").html("");
	}
}

/* 다량옵션오류방지를 위하여 인코딩된 옵션폼값을 인코딩 : disable 처리 */
function encodeFormValue(containerSelector){
	var container = $(containerSelector);
	var selector = "input[type='text'],input[type='hidden'],input[type='radio'],input[type='checkbox'],select,textarea";
	var data = new Array();
	$(selector,container).each(function(){
		var name = $(this).attr("name");
		var type = $(this).attr("type");

		if(name=='encodedFormValue') return;
		if($(this).is(":disabled")) return;
		if((type=='radio' || type=='checkbox') && !$(this).is(":checked")) return;

		data.push($(this).attr('name') + "=" + encodeURIComponent($(this).val()));
	});

	$(selector,container).each(function(){
		var name = $(this).attr("name");
		if(name=='encodedFormValue') return;
		var oriDisabled = $(this).is(":disabled")?true:false;
		$(this).data("oriDisabled",oriDisabled).attr("disabled",true);
	});

	$("textarea[name='encodedFormValue']").val(data);
}

/* 다량옵션오류방지를 위하여 인코딩된 옵션폼값을 인코딩 : disable 해제 */
function encodeFormValueOff(containerSelector){
	var container = $(containerSelector);
	var selector = "input[type='text'],input[type='hidden'],input[type='radio'],input[type='checkbox'],select,textarea";
	$(selector,container).each(function(){
		var name = $(this).attr("name");
		if(name=='encodedFormValue') return;
		var oriDisabled = $(this).data("oriDisabled")?true:false;
		$(this).attr("disabled",oriDisabled);
	});

	$("textarea[name='encodedFormValue']").val('');
}

/* 상품등록 에디터로딩 세팅 */
function setting_editor()
{
	$.ajax({
		type: "get",
		url: "../setting/setting_editor_popup",
		success: function(result){
			$("div#setting_editor_popup").html(result);
		}
	});
	openDialog("상품등록/수정 페이지에서의 상품 설명 보기 설정", "setting_editor_popup", {"width":"650","height":"180","show" : "fade","hide" : "fade"});
}

//레이어팝업 > 상품사진 멀티일괄/일괄 등록시
function save_image_config(){
	var saveinfo = {'largeImageWidth':$('.save_image_input').filter('#largeImageWidth').val(), 'largeImageHeight':$('.save_image_input').filter('#largeImageHeight').val(),
	'viewImageWidth':$('.save_image_input').filter('#viewImageWidth').val(), 'viewImageHeight':$('.save_image_input').filter('#viewImageHeight').val(),
	'list1ImageWidth':$('.save_image_input').filter('#list1ImageWidth').val(), 'list1ImageHeight':$('.save_image_input').filter('#list1ImageHeight').val(),
	'list2ImageWidth':$('.save_image_input').filter('#list2ImageWidth').val(), 'list2ImageHeight':$('.save_image_input').filter('#list2ImageHeight').val(),
	'thumbViewWidth':$('.save_image_input').filter('#thumbViewWidth').val(), 'thumbViewHeight':$('.save_image_input').filter('#thumbViewHeight').val(),
	'thumbCartWidth':$('.save_image_input').filter('#thumbCartWidth').val(), 'thumbCartHeight':$('.save_image_input').filter('#thumbCartHeight').val(),
	'thumbScrollWidth':$('.save_image_input').filter('#thumbScrollWidth').val(), 'thumbScrollHeight':$('.save_image_input').filter('#thumbScrollHeight').val()};

	$.ajax({
		'type': "POST",
		'url': "../goods_process/save_image_config",
		'data': saveinfo,
		'dataType' : 'json',
		'success': function(result){
			if( result.result ) {
				$("#largeImageWidth").val(result.largeImageWidth);
				$("#largeImageHeight").val(result.largeImageHeight);

				$("#viewImageWidth").val(result.viewImageWidth);
				$("#viewImageHeight").val(result.viewImageHeight);

				$("#list1ImageWidth").val(result.list1ImageWidth);
				$("#list1ImageHeight").val(result.list1ImageHeight);

				$("#list2ImageWidth").val(result.list2ImageWidth);
				$("#list2ImageHeight").val(result.list2ImageHeight);

				$("#thumbViewWidth").val(result.thumbViewWidth);
				$("#thumbViewHeight").val(result.thumbViewHeight);

				$("#thumbCartWidth").val(result.thumbCartWidth);
				$("#thumbCartHeight").val(result.thumbCartHeight);

				$("#thumbScrollWidth").val(result.thumbScrollWidth);
				$("#thumbScrollHeight").val(result.thumbScrollHeight);


				$(".largeImageWidth").text(result.largeImageWidth);
				$(".largeImageHeight").text(result.largeImageHeight);

				$(".viewImageWidth").text(result.viewImageWidth);
				$(".viewImageHeight").text(result.viewImageHeight);

				$(".list1ImageWidth").text(result.list1ImageWidth);
				$(".list1ImageHeight").text(result.list1ImageHeight);

				$(".list2ImageWidth").text(result.list2ImageWidth);
				$(".list2ImageHeight").text(result.list2ImageHeight);

				$(".thumbViewWidth").text(result.thumbViewWidth);
				$(".thumbViewHeight").text(result.thumbViewHeight);

				$(".thumbCartWidth").text(result.thumbCartWidth);
				$(".thumbCartHeight").text(result.thumbCartHeight);

				$(".thumbScrollWidth").text(result.thumbScrollWidth);
				$(".thumbScrollHeight").text(result.thumbScrollHeight);

				//$(".save_image_input").attr("disabled","disabled").attr("readonly","readonly");
				//$("button.save_image_config").parent().addClass("gray");
				//$("#save_image_config_ck").removeAttr("checked")
				openDialogAlert(result.msg,400,150);
				closeDialog("goods_resize_formlay");

			}else{
				openDialogAlert(result.msg,400,150);
			}
		}
	});
}

// 옵션 선택박스 layout 설정 사용여부 변경
var sub_require	= 'N';
function set_option_select_layout(){

	var option_layout_button_status			= false;
	var chk_option_use						= false;
	var chk_suboption_use					= false;
	var chk_suboption_require				= false;
	var chk_inputoption_use					= false;

	// 추가옵션 위치 선택 disable ( 설정 불가 )
	$("select[name='set_suboption_select_layout_position']").addClass('disable').unbind('change');
	$("select[name='set_suboption_select_layout_position']").on("change",function(){
		$(this).find('option').eq(0).attr('selected', true);
	});
	// 입력옵션 묶임 선택 disable ( 설정 불가 )
	$("select[name='set_inputoption_select_layout_group']").addClass('disable').unbind('change');
	$("select[name='set_inputoption_select_layout_group']").on("change",function(){
		$(this).find('option').eq(0).attr('selected', true);
	});

	// 기존 db의 설정값
	if	($("input[name='optionUse']:checked").val() == "1" && $('div#optionLayer tbody tr').length > 0 &&
		!$('div#optionLayer tbody').find("input[name='price[]'][type='text']").length){

			chk_option_use				= true;

		// 추가옵션 묶임 선택 disable
		$("select[name='set_suboption_select_layout_group']").addClass('disable').unbind('change');
		$("select[name='set_suboption_select_layout_group']").on("change",function(){
			$(this).find('option').eq(0).attr('selected', true);
		});

		// 추가옵션 처리
		if	($("input[name='subOptionUse']:checked").val() == "1" && $('tr.suboptionTr').length > 0){
			chk_suboption_use	= true;
			sub_require			= 'N';
			$('tr.suboptionTr').each(function(){
				if	($(this).find('td').eq(1).text().search(/Y/) != -1){
					sub_require	= 'Y';
					return false;
				}
			});

			// 추가구성옵션 필수사용여부
			if	(sub_require == 'Y'){
				chk_suboption_require		= true;

				// 레이아웃 설정 필수 추가옵션 사용 처리
				$("#set_suboption_require").html(' : 필수포함됨');
				$("select[name='set_suboption_select_layout_group']").val('group');
				$("select[name='set_suboption_select_layout_position']").val('up');
				//$("#display_suboption_select_layout_group").html('필수옵션과 쌍으로 묶임 : 해당 필수상품에 → 해당되는 추가상품 정확히 출고');
				//$("#display_suboption_select_layout_position").html('옵션선택 영역에 노출되어');
			}else{
				option_layout_button_status	= true;

				// 기존 db의 설정값과 비교 후 레이아웃 설정 일반 추가옵션 사용 처리
				$("#set_suboption_require").html(' : 필수없음');
				if	(!gl_suboption_layout_group)
					gl_suboption_layout_group		= 'group';
				if	(!gl_suboption_layout_position)
					gl_suboption_layout_position	= 'up';

				$("select[name='set_suboption_select_layout_group']").val(gl_suboption_layout_group);
				$("select[name='set_suboption_select_layout_position']").val(gl_suboption_layout_position);
				/*
				if (gl_suboption_layout_group == 'first')
					$("#display_suboption_select_layout_group").html('첫번째 필수옵션에 묶임 : 첫번째 필수상품에 → 해당되는 추가상품 정확히 출고');
				else
					$("#display_suboption_select_layout_group").html('필수옵션과 쌍으로 묶임 : 해당 필수상품에 → 해당되는 추가상품 정확히 출고');

				if (gl_suboption_layout_position == 'down')
					$("#display_suboption_select_layout_position").html('선택된 옵션 영역에 노출되어');
				else
					$("#display_suboption_select_layout_position").html('옵션선택 영역에 노출되어');
				*/
				// 추가옵션 묶임 선택
				$("select[name='set_suboption_select_layout_group']").removeClass('disable').unbind('change');
				/*$("select[name='set_suboption_select_layout_group']").change(function(){
					set_option_select_display('apply');
				});*/
			}
		}else{
			// 레이아웃 설정 미사용 처리
			$("#set_suboption_require").html('');
			$("select[name='set_suboption_select_layout_group']").val('group');
			$("select[name='set_suboption_select_layout_position']").val('up');
			//$("#display_suboption_select_layout_group").html('<span style="color:#9a9a9a;">미사용</span>');
			//$("#display_suboption_select_layout_position").html('');
		}

		// 입력옵션 처리
		if	($("input[name='memberInputUse']:checked").val() == "1" && $("input[name='memberInputName[]']").length > 0){

			chk_inputoption_use			= true;
			option_layout_button_status	= true;

			// 기존 db의 설정값과 비교 후 레이아웃 설정 입력옵션 사용 처리
			if	(!gl_inputoption_layout_group)
				gl_inputoption_layout_group		= 'group';
			if	(!gl_inputoption_layout_position)
				gl_inputoption_layout_position		= 'up';

			$("select[name='set_inputoption_select_layout_group']").val(gl_inputoption_layout_group);
			$("select[name='set_inputoption_select_layout_position']").val(gl_inputoption_layout_position);
			/*
			if	(gl_inputoption_layout_group == 'first')
				$("#display_inputoption_select_layout_group").html('첫번째 필수옵션에 묶임 : 해당 필수상품에 → 해당되는 입력정보 정확히 확인');
			else
				$("#display_inputoption_select_layout_group").html('필수옵션과 쌍으로 묶임 : 해당 필수상품에 → 해당되는 입력정보 정확히 확인');

			if	(gl_inputoption_layout_position == 'down')
				$("#display_inputoption_select_layout_position").html('선택된 옵션 영역에 노출되어');
			else
				$("#display_inputoption_select_layout_position").html('옵션선택 영역에 노출되어');
			*/
			// 추가옵션 위치 선택 disable
			$("select[name='set_inputoption_select_layout_position']").removeClass('disable').unbind('change');
			/*$("select[name='set_inputoption_select_layout_position']").change(function(){
				set_option_select_display('apply');
			});*/

		}else{
			// 레이아웃 설정 미사용 처리
			$("select[name='set_inputoption_select_layout_group']").val('group');
			$("select[name='set_inputoption_select_layout_position']").val('up');
			//$("#display_inputoption_select_layout_group").html('<span style="color:#9a9a9a;">미사용</span>');
			//$("#display_inputoption_select_layout_position").html('');

			// 입력옵션 위치 선택 disable
			$("select[name='set_inputoption_select_layout_position']").addClass('disable').unbind('change');
			$("select[name='set_inputoption_select_layout_position']").change(function(){
				$(this).find('option').eq(0).attr('selected', true);
			});
		}

		set_option_select_display('');

	}else{

		option_layout_button_status	= false;

		// 추가옵션 처리
		if	($("input[name='subOptionUse']:checked").val() == 1 && $('tr.suboptionTr').length > 0){
			//$("#display_suboption_select_layout_group").html('<span style="color:#9a9a9a;">필수옵션과 쌍으로 묶임</span>');
			//$("#display_suboption_select_layout_position").html('<span style="color:#9a9a9a;">옵션선택 영역에 노출</span>');
		}else{
			//$("#display_suboption_select_layout_group").html('<span style="color:#9a9a9a;">미사용</span>');
			//$("#display_suboption_select_layout_position").html('');
		}

		// 입력옵션 처리
		if	($("input[name='memberInputUse']:checked").val() == "1" && $("input[name='memberInputName[]']").length > 0){
			//$("#display_inputoption_select_layout_group").html('<span style="color:#9a9a9a;">필수옵션과 쌍으로 묶임</span>');
			//$("#display_inputoption_select_layout_position").html('<span style="color:#9a9a9a;">옵션선택 영역에 노출</span>');
		}else{
			//$("#display_inputoption_select_layout_group").html('<span style="color:#9a9a9a;">미사용</span>');
			//$("#display_inputoption_select_layout_position").html('');
		}

		$("select[name='set_suboption_select_layout_group']").val('group');
		$("select[name='set_suboption_select_layout_position']").val('up');
		$("select[name='set_inputoption_select_layout_group']").val('group');
		$("select[name='set_inputoption_select_layout_position']").val('up');

		// 추가옵션 위치 선택 disable ( 설정 불가 )
		$("select[name='set_suboption_select_layout_group']").addClass('disable').unbind('change');
		$("select[name='set_suboption_select_layout_group']").on("change",function(){
			$(this).find('option').eq(0).attr('selected', true);
		});
		// 입력옵션 묶임 선택 disable ( 설정 불가 )
		$("select[name='set_inputoption_select_layout_position']").addClass('disable').unbind('change');
		$("select[name='set_inputoption_select_layout_position']").on("change",function(){
			$(this).find('option').eq(0).attr('selected', true);
		});
	}

	// 옵션 레이아웃 설정 버튼 제어
	if	(option_layout_button_status){
		$(".option_layout_button").off('click');
		$(".option_layout_button").on("click",function(){
			openSetOptionLayout($(this).attr("data-mode"));
		});
	}else{
		var msg	= '';
		if			(!chk_option_use){
			msg	= '필수옵션 미사용';
		}else if	(!chk_suboption_use && !chk_inputoption_use){
			msg	= '추가구성옵션 미사용<br/>추가입력옵션 미사용';
		}else if	(chk_suboption_use && chk_suboption_require && !chk_inputoption_use){
			msg	= '추가구성옵션 사용<font color="red">(필수포함됨)</font><br/>추가입력옵션 미사용';
		}
		//$("#option_layout_button").unbind('click');
		if	(msg){
			msg		+= '<br/><br/>위 사유로 화면설정 버튼이 비활성화되었습니다.';
			$(".option_layout_button").on("click",function(){
				openDialogAlert(msg, 400, 270);
			});
		}
	}

	international_shipping_info();

	return false;
}

// 옵션 노출 레이아웃 설정 팝업 오픈
function openSetOptionLayout(mode){
	
	$("#set_option_select_layout tr").hide();
	$("#set_option_select_layout tr."+mode).show();
	var title = "추가 구성 옵션 구매 방법 설정";
	if(mode == "inputoption"){
		title = "추가 입력 옵션 구매 방법 설정";
	}
	openDialog(title, 'set_option_select_layout', {'width':'550','height':'720'});
	/*openDialog('추가(구성/입력) 옵션 구매화면 설정', 'set_option_select_layout', {'width':'1000','height':'450','close':function(){
		set_option_select_display('apply');
	}});*/
}

// 옵션 노출 설정 변경에 따른 화면 변경 처리
function set_option_select_display(type){
	var useOption		= false;
	var useSubOption	= false;
	var useInputOption	= false;
	var sub_require		= 'N';
	var sSubGroup		= $("select[name='set_suboption_select_layout_group']").val();
	var sSubPosition	= $("select[name='set_suboption_select_layout_position']").val();
	var sInputGroup		= $("select[name='set_inputoption_select_layout_group']").val();
	var sInputPosition	= $("select[name='set_inputoption_select_layout_position']").val();


	// 필수옵션 사용여부 체크
	if	($("input[name='optionUse'][value='1']").attr('checked') && $('div#optionLayer tbody tr').length > 0 &&
		!$('div#optionLayer tbody').find("input[name='price[]'][type='text']").length){
		useOption		= true;
	}
	// 추가옵션 사용여부 및 필수 존재여부 체크
	if	($("input[name='subOptionUse']:checked").val() == "1" && $('tr.suboptionTr').length > 0){
		useSubOption	= true;
		$('tr.suboptionTr').each(function(){
			if	($(this).find('td').eq(1).text().search(/Y/) != -1){
				sub_require	= 'Y';
				return false;
			}
		});
	}
	// 입력옵션 사용여부 체크
	if	($("input[name='memberInputUse']:checked").val() == "1" && $("input[name='memberInputName[]']").length > 0){
		useInputOption	= true;
	}

	// 추가옵션에 필수가 있는 경우 무조건 쌍으로 묶이며 위에 노출한다.
	if	(!useSubOption || sub_require == 'Y'){
		sSubGroup		= 'group';
		sSubPosition	= 'up';
		$("select[name='set_suboption_select_layout_group']").find('option').eq(0).attr('selected', true);
		$("select[name='set_suboption_select_layout_position']").find('option').eq(0).attr('selected', true);
	}

	// 입력옵션은 쌍으로 묶음만 사용가능
	if	(sInputGroup != 'group'){
		sInputGroup		= 'group';
		$("select[name='set_inputoption_select_layout_group']").find('option').eq(0).attr('selected', true);
	}

	// 입력옵션 미사용 처리
	if	(!useInputOption){
		sInputPosition	= 'up';
		$("select[name='set_inputoption_select_layout_position']").find('option').eq(0).attr('selected', true);
	}

	// type1 or type2
	if			(sSubGroup == 'group' && sSubPosition == 'up'){
		if	(type == 'apply'){
			//$("#display_suboption_select_layout_group").html('필수옵션과 쌍으로 묶임');
			//$("#display_suboption_select_layout_position").html('옵션선택 영역에 노출');
		}

		// type1
		if			(sInputGroup == 'group' && sInputPosition == 'up'){

			if( useInputOption ) {
				$('img#display_option_select_layout_image').attr('src', '/admin/skin/default/images/common/option_select_layout_1.jpg');
			}else{
				$('img#display_option_select_layout_image').attr('src', '/admin/skin/default/images/common/option_select_layout_6.jpg');
			}
			if	(type == 'apply'){
				//$("#display_inputoption_select_layout_group").html('필수옵션과 쌍으로 묶임');
				//$("#display_inputoption_select_layout_position").html('옵션선택 영역에 노출');
			}

		// type2
		}else if	(sInputGroup == 'group' && sInputPosition == 'down'){
			$('img#display_option_select_layout_image').attr('src', '/admin/skin/default/images/common/option_select_layout_2.jpg');
			if	(type == 'apply'){
				//$("#display_inputoption_select_layout_group").html('필수옵션과 쌍으로 묶임');
				//$("#display_inputoption_select_layout_position").html('선택된 옵션 영역에 노출');
			}
		}

	// type3 or type4
	}else if	(sSubGroup == 'first' && sSubPosition == 'up'){
		if	(type == 'apply'){
			//$("#display_suboption_select_layout_group").html('첫번째 필수옵션에 묶임');
			//$("#display_suboption_select_layout_position").html('옵션선택 영역에 노출');
		}

		// type3
		if			(sInputGroup == 'group' && sInputPosition == 'up'){
			if( useInputOption ) {
				$('img#display_option_select_layout_image').attr('src', '/admin/skin/default/images/common/option_select_layout_3.jpg');
			}else{
				$('img#display_option_select_layout_image').attr('src', '/admin/skin/default/images/common/option_select_layout_5.jpg');
			}
			if	(type == 'apply'){
				//$("#display_inputoption_select_layout_group").html('필수옵션과 쌍으로 묶임');
				//$("#display_inputoption_select_layout_position").html('옵션선택 영역에 노출');
			}

		// type4
		}else if	(sInputGroup == 'group' && sInputPosition == 'down'){
			$('img#display_option_select_layout_image').attr('src', '/admin/skin/default/images/common/option_select_layout_4.jpg');
			if	(type == 'apply'){
				//$("#display_inputoption_select_layout_group").html('필수옵션과 쌍으로 묶임');
				//$("#display_inputoption_select_layout_position").html('선택된 옵션 영역에 노출');
			}
		}
	}

	// 추가옵션 화면 설정 미사용 처리
	if	(!useSubOption){
		//$("#display_suboption_select_layout_group").html('<span style="color:#9a9a9a;">미사용</span>');
		//$("#display_suboption_select_layout_position").html('');
	}
	// 입력옵션 화면 설정 미사용 처리
	if	(!useInputOption){
		//$("#display_inputoption_select_layout_group").html('<span style="color:#9a9a9a;">미사용</span>');
		//$("#display_inputoption_select_layout_position").html('');
	}

	if	(type == 'apply'){
		$("input[name='suboption_layout_group']").val(sSubGroup);
		$("input[name='suboption_layout_position']").val(sSubPosition);
		$("input[name='inputoption_layout_group']").val(sInputGroup);
		$("input[name='inputoption_layout_position']").val(sInputPosition);
		closeDialog('set_option_select_layout');
	}
}

// 해당 input박스의 입력된 문자 byte수를 계산
function calculate_input_byte(obj){
	$(obj).closest('byte').find('span.view-byte').html(comma(chkByte($(obj).val())));
}

// 해당 input박스의 입력된 글자수를 계산
function calculate_input_len(obj){
	var mobj	= $(obj).closest('len').find('span.view-len');
	var len	= $(obj).val().length;
	var max	= $(obj).attr('maxlength');
	mobj.removeClass('red');
	if(len < max){
		msg	= '<b>'+comma( len ) + '</b>/' + comma( max );
	}else{
		$(obj).val( $(obj).val().substring(0,max) );
		msg	= '<b>'+comma( max ) + '</b>/' + comma( max );
	}
	mobj.html( msg );
	if( len >= max ) mobj.find("b").addClass('red');
}

// 창고별 상세 재고 노출
function scm_warehouse_on(goods_seq, obj){
	var option_seq = '';
	var spanObj	= $(obj).find('span.option-stock');
	if( spanObj.attr('optType') ){
		var option_seq = spanObj.attr('optSeq');
		var optstr	= goods_seq + spanObj.attr('optType') + option_seq;
	}else{
		var optstr	= goods_seq + 'option';
	}

	var whLayId = 'stock-wh-info';
	var useWarehouse	= $("input[name='use_warehouse']").eq(0).val();

	$(obj).find('div#' + whLayId).remove();
	var whLay	= $('<div class="hide" id="' + whLayId + '"></div>').appendTo(obj);

	$.ajax({
		type: "post",
		url: "/scm/request_getstock_total",
		dataType : 'json',
		data: 'goods_seq=' + goods_seq + '&option_type=option&option_seq=' + option_seq,
		success: function(result) {
			var i 			= 0;
			var wh_count 	= 0;
			var data 		= '';
			var data_wh 	= '';
			var html 		= '<div class="content" style="overflow-x:auto;">';
			var title 		= '';
			var option 		= '';
			var all_cnt 	= 0; //all_count 확인
			var line_color 	= '#2D77F0';
			
			for(var index in result) {
				all_cnt++;
			}

			for(var index in result) {
				if (result.hasOwnProperty(index)) {
					data 		= result[index];
					var wh_count_tot = 0;
					for(var index_wh in data.wh) {
						if (data.wh.hasOwnProperty(index_wh)) {
							wh_count_tot++;
						}
					}

					if(data.weight == 'null' || data.weight == null) data.weight = 0;

					if( i == 0 ){
						var width = data.option_count * 100 + wh_count_tot * 80 + 400;
						html	+= '<table width="'+width+'" class="table_basic v7">';
						html	+= '<thead>';
						html	+= '<tr>';
						/*
						if( data.option1 ){
							html	+= '<th class="center" colspan="'+data.option_count+'">필수옵션</th>';
						}
						*/
						if( data.option1 ){
							for(var j=1;j <= data.option_count;j++){
								eval('title = data.title'+j);
								html	+= '<th class="center" width="100">' + title + '</th>';
							}
						}
						html	+= '<th class="center" rowspan="2" width="50">무게<br/>(Kg)</th>';
						if( data.package_yn != 'y' ){
							for(var index_wh in data.wh) {
								if (data.wh.hasOwnProperty(index_wh)) {
									wh_count++;
									data_wh = data.wh[index_wh];
									if(data_wh.wh_name == null){
										data_wh.wh_name = ' ';
									}
									html	+= '<th class="center" width="90">';
									html	+= data_wh.wh_name;
									if(data_wh.use == 'y'){
										html	+= '<br/>[사용]';
									}else{
										html	+= '<br/>[미사용]';
									}
									html	+= '</th>';
								}
							}
						}
						html	+= '<th class="center" width="80">합계</th>';
						html	+= '<th class="center store-box-title" width="100" style="border-top:2px solid '+line_color+' !important; border-left:2px solid '+line_color+' !important;">';
						html	+= data.admin_env_name;
						html	+= '<br/>재고</th>';
						html	+= '<th class="center" width="100" style="border-top:2px solid '+line_color+' !important;">';
						html	+= data.admin_env_name;
						html	+= '<br/>가용</th>';
						html	+= '<th class="center" width="30" style="border-top:2px solid '+line_color+' !important; border-right:2px solid '+line_color+' !important;">노출</th>';
						html	+= '</tr>';
						html	+= '<tr>';
						/*
						if( data.option1 ){
							for(var j=1;j <= data.option_count;j++){
								eval('title = data.title'+j);
								html	+= '<th class="center" width="100">' + title + '</th>';
							}
						}
						*/
						html	+= '</tr>';
						html	+= '</thead>';
						html	+= '<tbody>';
					}

					if(($(obj).attr('option_code1') != '') && (data.option1 == $(obj).attr('option_code1') && data.option2 == $(obj).attr('option_code2') && data.option3 == $(obj).attr('option_code3') && data.option4 == $(obj).attr('option_code4') && data.option5 == $(obj).attr('option_code5'))) {
						html	+= '<tr style="border:2px solid red;">';
					} else {
						html	+= '<tr>';
					}

					if( data.option1 ){
						for(var j=1;j <= data.option_count;j++){
							eval('option = data.option'+j);
							html	+= '<td class="center">' + option + '</td>';
						}
					}
					html		+= '<td class="right">' + data.weight + '</td>';
					if( data.package_yn != 'y' ){
						for(var index_wh in data.wh) {
							if (data.wh.hasOwnProperty(index_wh)) {
								data_wh = data.wh[index_wh];
								if(data_wh.use == 'y'){
									html	+= '<td class="right valign-top pdr5 bgyellow">';
								}else{
									html	+= '<td class="right valign-top pdr5">';
								}
								html	+= comma(data_wh.stock);
								html	+= '('+comma(data_wh.bad_stock)+')';
								html	+= '<br/>'+comma(data_wh.supply_price);
								html	+= '<br/>'+data_wh.location_code;
								html	+= '</td>';
							}
						}
					}

					html	+= '<td class="right valign-top pdr5">';
					html	+= '	<span>'+comma(data.total_stock)+'</span> ('+comma(data.total_badstock)+')';
					html	+= '</td>';
					
					if(i == all_cnt -1) {						
						html	+= '<td class="right store-box-stock" style="border-left:2px solid '+line_color+' !important;border-bottom:2px solid '+line_color+' !important; ">';
					}else{
						html	+= '<td class="right store-box-stock" style="border-left:2px solid '+line_color+' !important; ">';
					}
					html	+= '	<span>'+comma(data.stock)+'</span> ('+comma(data.badstock)+')';
					html	+= '	<br/>'+comma(data.supply_price);
					html	+= '</td>';
					
					if(i == all_cnt -1) {
						html	+= '<td class="right valign-top pdr5" style="border-bottom:2px solid '+line_color+' !important;">';
					} else {
						html	+= '<td class="right valign-top pdr5">';
					}
					html	+= comma(data.ablestock);
					html	+= '</td>';
					
					if(i == all_cnt -1) {
						html	+= '<td class="center" style="border-bottom:2px solid '+line_color+' !important;border-right:2px solid '+line_color+' !important;">';
					} else {
						html		+= '<td class="center" style="border-right:2px solid '+line_color+' !important;">';
					}
					html	+= (data.option_view == 'Y') ? '○' : 'X';
					html	+= '</td>';
					html	+= '</tr>';
				}
				i++;
			}

			html	+= '</tbody></table>';
			html	+= '<ul class="bullet_hyphen resp_message"><li>창고별 정보 : 첫번째줄-재고(불량재고), 두번째줄-매입가, 세번째줄-로케이션</li>';
			html	+= '<li>창고별 사용/미사용 : ' + data.admin_env_name + '에서 사용하는 창고에 대하여 \'사용\'이라고 표기함</li></ul>';
			html	+= '</div>';

			html	+= '<div class="footer">';
			html	+= '	<button type="button" onClick="$(\'.ui-icon-closethick\').trigger(\'click\');" class="resp_btn v3 size_XL">닫기</button>';
			html	+= '</div>';

			width += 90;
			var height = i*65+350;

			if(i > 10){
				height = 600;
			}

			if(width > 1300){
				width = 1300;
			}

			whLay.append(html);
			help_tooltip();
			openDialog("현재 재고", whLayId, {"width":width,"height":height,"show" : "fade","hide" : "fade"});
		}
	});
}

// 패키지 연결상품 재고 노출
function package_stock_on(goods_seq, obj){
	var option_seq 	= '';
	var spanObj		= $(obj).find('span.option-stock');
	if( spanObj.attr('optType') ){
		var option_seq = spanObj.attr('optSeq');
		var optstr	= goods_seq + spanObj.attr('optType') + option_seq;
	}else{
		var optstr	= goods_seq + 'option';
	}
	var whLayId = 'stock-wh-info';
	var useWarehouse	= $("input[name='use_warehouse']").eq(0).val();

	$(obj).find('div#' + whLayId).remove();

	var whLay	= $('<div class="hide" id="' + whLayId + '"></div>').appendTo(obj);
	$.ajax({
		type: "post",
		url: "/goods/request_getstock_all",
		dataType : 'json',
		data: 'goods_seq=' + goods_seq + '&option_type=option&option_seq=' + option_seq,
		success: function(result) {
			var i 				= 0;
			var wh_count 		= 0;
			var data			= '';
			var data_wh 		= '';
			var html 			= '<div class="content">';
			var title 			= '';
			var option 			= '';
			var packages_count 	= 0;
			var all_cnt 		= 0; //all_count 확인
			var line_color 		= '#2D77F0';
			for(var index in result) {
				all_cnt++;
			}

			for(var index in result) {
				if (result.hasOwnProperty(index)) {
					data = result[index];

					if( i == 0 ){
						var width = data.option_count * 100 + data.package_count * 180 + 390;

						html	+= '<table width="'+width+'" class="table_basic v7">';
						html	+= '<thead>';
						html	+= '<tr>';
						if( data.option1 ){
							for(var j=1;j <= data.option_count;j++){
								eval('title = data.title'+j);
								html	+= '<th class="center" width="100">' + title + '</th>';
							}
						}
						html		+= '<th class="center"  width="60">무게<br/>(Kg)</td>';
						if(window.Firstmall.Config.Environment.isAdmin == true){
						html		+= '<th class="center"  width="80">매입가</td>';
						}
						/*
						if( data.package_count > 0 ){
							html	+= '<th class="center" colspan="'+data.package_count+'" style="border-top:2px solid '+line_color+' !important; border-left:2px solid '+line_color+' !important;">실제상품</th>';
						}
						*/
						for(var ig=1; ig <= data.package_count;ig++ ) {
							if(ig == 1) {
								html	+= '<th class="center" width="100" style="border-top:2px solid '+line_color+' !important; border-left:2px solid '+line_color+' !important;">';
							} else {
								html	+= '<th class="center" width="100" style="border-top:2px solid '+line_color+' !important;">';
							}
							html	+= '상품 '+ ig;
							html	+= '</th>';
						}
						if(data.package_count == 0 || !data.package_count){
							html	+= '<th class="center" rowspan="2" width="100">';
							if(data.admin_env_name!=null) html	+= data.admin_env_name;
							else html	+= '재고';
							html	+= '<br/>(불량재고)</th>';
							html	+= '<th class="center" rowspan="2" width="90" style="border-top:2px solid '+line_color+' !important; border-left:2px solid '+line_color+' !important;">';
							if(data.admin_env_name!=null) html	+= data.admin_env_name;
							else html	+= '가용재고';
							html	+= '</th>';
						}
						html	+= '<th class="center" rowspan="2" width="60" style="border-top:2px solid '+line_color+' !important; border-right:2px solid '+line_color+' !important;">노출</td>';
						html	+= '</tr>';
						html	+= '<tr>';
						/*
						if( data.option1 ){
							for(var j=1;j <= data.option_count;j++){
								eval('title = data.title'+j);
								html	+= '<th class="center" width="100">' + title + '</th>';
							}
						}
						*/

						html	+= '</tr>';
						html	+= '</thead>';
						html	+= '<tbody>';
					}

					/*if(($(obj).attr('option_code1') != '') && (data.option1 == $(obj).attr('option_code1') && data.option2 == $(obj).attr('option_code2') && data.option3 == $(obj).attr('option_code3') && data.option4 == $(obj).attr('option_code4') && data.option5 == $(obj).attr('option_code5'))) {
						html	+= '<tr style="border:2px solid '+line_color+';">';
					} else {
						html	+= '<tr>';
					}*/
					html	+= '<tr>';

					if(data.weight == 'null' || data.weight == null) data.weight = 0;

					if( data.option1 ){
						for(var j=1;j <= data.option_count;j++){
							eval('option = data.option'+j);
							html	+= '<td class="center" width="80">' + option + '</td>';
						}
					}
					html		+= '<td class="right">' + comma(data.weight) + '</td>';
					if(window.Firstmall.Config.Environment.isAdmin == true){
					html		+= '<td class="right">' + get_currency_price(data.supply_price) + '</td>';
					}
					packages_count = 0;
					for(var index_packages in data.packages) {
						if (data.packages.hasOwnProperty(index_packages)) {
							packages_count++;
							data_packages = data.packages[index_packages];
							if(i == all_cnt -1 && packages_count == 1) { //왼쪽 맨 아래
								html	+= '<td class="center" style="border-left:2px solid '+line_color+' !important;border-bottom:2px solid '+line_color+' !important;">';
							} else if(packages_count == 1) {
								html	+= '<td class="center" style="border-left:2px solid '+line_color+' !important;">';
							} else if(i == all_cnt -1) {
								html	+= '<td class="center" style="border-bottom:2px solid '+line_color+' !important;">';
							} else {
								html	+= '<td class="center">';
							}
							html	+='<span>'+'['+data_packages.package_goods_seq+'] '+ data_packages.package_goods_name+'</span><br/>';
							html	+= data_packages.package_option+'<br/>';
							html	+= '주문당 '+data_packages.package_unit_ea+'개 발송<span class="tooltip_btn" onClick="showTooltip(this, \'/admin/tooltip/goods\', \'#regist_package_ea\')" ></span><br/>';
							if( data_packages.stock != null ) html	+= '<span>'+data_packages.stock+'</span>';
							if( data_packages.badstock != null ) html	+= '('+data_packages.badstock+')';
							if( data_packages.ablestock != null ) html	+= '/'+data_packages.ablestock;
							if( data_packages.safe_stock != null ) html	+= '/'+data_packages.safe_stock;
							html	+= '</td>';
						}
					}
					if(packages_count){
						for(var ig=packages_count; ig < data.package_count;ig++ ) {
								html	+= '<td class="center">';
								html	+= '</td>';
						}
					}
					if(packages_count == 0){
						html	+= '<td class="right valign-top pdr5 bgyellow">';
						html	+= '	<span>'+comma(data.stock)+'</span>';
						html	+= '	<br/>'+comma(data.badstock);
						html	+= '</td>';
						if(i == all_cnt -1) {
							html	+= '<td class="right valign-top pdr5" style="border-left:2px solid '+line_color+' !important; border-bottom:2px solid '+line_color+' !important;">';
						} else {
							html	+= '<td class="right valign-top pdr5" style="border-left:2px solid '+line_color+' !important;">';
						}
						html	+= comma(data.ablestock);
						html	+= '</td>';
					}
					if(i == all_cnt -1) {
						html	+= '<td class="center" style="border-bottom:2px solid '+line_color+' !important;border-right:2px solid '+line_color+' !important;">';
					} else {
						html		+= '<td class="center" style="border-right:2px solid '+line_color+' !important;">';
					}
					html		+= (data.option_view == 'Y') ? '노출' : '미노출';
					html		+= '</td>';
					html		+= '</tr>';
				}
				i++;
			}

			html	+= '</tbody></table></div>';
			html	+= '<div class="footer">';
			html	+= '	<button type="button" onClick="$(\'.ui-icon-closethick\').trigger(\'click\');" class="resp_btn v3 size_XL">닫기</button>';
			html	+= '</div>';

			width 		+= 40;

			var fixing_h = 60;
			if(i < 3) { fixing_h = 50; }
			if(packages_count > 0) fixing_h = 80;

			var height 	= (i * fixing_h) + 320;
			if(i > 10){ height = 600; }
			if(width > 1300){ width = 1300; }

			whLay.append(html);
			help_tooltip();

			openDialog("현재 재고", whLayId, {"width":width,"height":height,"show" : "fade","hide" : "fade"});
		}

	});

}


// 패키지 관련 함수
// 필수옵션 마켓별 금액 조정 결과 처리
function set_market_option_price(tmpseq){
	if	(tmpseq)
		$("input[name='market_tmp_seq']").val(tmpseq);

	closeDialog('market_option_price_lay');
}

// 판매마켓 전송몰 목록 데이터
function set_send_mall(data){
	var viewName	= '';
	var inputbox	= '';
	var cnt			= data.length;
	for	(var m = 0; m < cnt; m++){
		inputbox	+= '<input type="hidden" name="openmarket_send_mall_id[]" value="'+data[m].mall_code+'" />'
					+ '<input type="hidden" name="openmarket_send_mall_name[]" value="'+data[m].mall_name+'" />';

		if	(m == 0){
			viewName	+= data[m].mall_name;
		}else{
			viewName	+= ', ' + data[m].mall_name;
		}
	}
	$("div.mall-input-lay").html(inputbox);

	if	(viewName){
		$("span.mall-view-list").text(viewName);
	}else{
		$("span.mall-view-list").text('없음');
	}

	closeDialog("openmarket_lay");
}


function openGoodsRelationDisplayPopup(kind){
	window.open("../design/display_edit?kind="+kind+"&goods_seq="+gl_goods_seq+"&popup=1",'',"width=1080,height=700,scrollbars=1");
}

//티켓값어치문구변경
function socialcpinputtype() {
		var socialcp_input_type = $("input[name='socialcp_input_type']:checked").val();
		var couponinputsubtitle = '';
		$(".couponinputtitle").show();
		if( socialcp_input_type == 'price' ) {
			couponinputsubtitle = '금액';
		}else{
			couponinputsubtitle = '횟수';
		}
		$(".couponinputsubtitle").text(couponinputsubtitle);
		//티켓값어치수정;;;
}

function goodinfochangeok() {
	$("#goodsinfochage").val("ok");
	 document.goodsRegist.submit();
}

function goodinfochangeno() {
	$("#goodsinfochage").val("");
}



/*
입력옵션 불러오기
**/
function inputoption_frequently_load(goods_seq){
	$.ajax({
		type: "get",
		'dataType' : 'json',
		url: "../goods/set_goods_inputoptions",
		data: "goods_seq="+goods_seq,
		success: function(result) {
			$("#memberInputDialog .optionList tbody").html(result.html);
			$("select[name='memberInputMakeForm[]']").on("change",function(){check_memberInputMakeForm($("select[name='memberInputMakeForm[]']").index(this));});
			//openDialog("추가입력옵션 만들기", "memberInputDialog", {"width":"750","height":"500","show" : "fade","hide" : "fade"});
		}
	});
}

//추가정보 >> 추가옵션코드추가
function goodsaddinfocode_default(obj){
	obj.find(".goodsaddinfolay").hide();
	obj.find(".etcContents").show();
	var selectectid = obj.find("select[name='selectEtcTitle[]'] option:selected").val();
	var selectecttitle = obj.find("select[name='selectEtcTitle[]'] option:selected").text();
	if(  selectectid.substr(0,2) == 'goodsaddinfo'){
		 obj.find(".goodsaddinfolay").show();
		 obj.find(".goodsaddinfolay").find(".goodsaddinfosublay").hide();
		 obj.find(".etcContents").hide();
		 obj.find(".etcContents").val(obj.find("."+selectectid+" option:selected").val());
		 obj.find(".etcContents_title").val(obj.find("."+selectectid+" option:selected").text());
		 obj.find(".goodsaddinfolay").find("."+selectectid).show();
		 obj.find(".etcTitle").val(selectecttitle);
	}else{
		obj.find(".etcTitle").val('');
		obj.find(".etcContents_title").val('');
		obj.find(".etcContents").val('');
	}
}

function goodsaddinfocode(obj){
	var tageetRootSet	= obj.parent().parent();
	var targetBox		= tageetRootSet.find(".etcContents");

	tageetRootSet.find(".goodsaddinfolay").hide();
	targetBox.show();

	var selectectid = tageetRootSet.find("select[name='selectEtcTitle[]'] option:selected").val();
	var selectecttitle = tageetRootSet.find("select[name='selectEtcTitle[]'] option:selected").text();
	if( selectectid.substr(0,12) == 'goodsaddinfo'){
		tageetRootSet.find(".goodsaddinfolay").show();
		tageetRootSet.find(".goodsaddinfolay").find(".goodsaddinfosublay").hide();
		tageetRootSet.find(".etcContents_title").val(tageetRootSet.find("."+selectectid+" option:selected").text());
		tageetRootSet.find(".goodsaddinfolay").find("."+selectectid).show();
		tageetRootSet.find(".goodsaddinfolay").closest("tr").find(".resp_limit_text").hide();
		tageetRootSet.find(".etcTitle").val(selectecttitle);

		targetBox.hide();
		targetBox.val(tageetRootSet.find("."+selectectid+" option:selected").val());
	}else{
		tageetRootSet.find(".etcTitle").val('');
		tageetRootSet.find(".etcContents_title").val('');
		tageetRootSet.find(".goodsaddinfolay").closest("tr").find(".resp_limit_text").show();

		targetBox.val('');
		targetBox.siblings('.etcContent_size').html('0');
	}
}

/* 상품정보제공고시 품목 선택 */
function chgGoodSubInfo(str){
	var goodSubInfo = new Array();
	var title = '';
	if(str && str != 'keep' && str != 'delete'){
		$.ajax({
			'url' : 'goods_sub_info',
			'data' : {'category':str},
			'dataType' : 'json',
			'success' : function(res){
				$("#subInfoTable").html("");
				$("#subInfoTable").closest("table").find("tr.nothing").addClass('hide');
				if(res.length){
					$("#subInfoViewTable tr.nothing").addClass('hide');
					for(var i=0;i<res.length;i++){
						if( res[i].title ) title = res[i].title; 
						var html = '';
						html = '<tr>';
						html += '<td class="center"><img src="/admin/skin/default/images/common/icon_move.png"></td>';
						html += '<td class="center"><input type="text" name="subInfoTitle[]" class="resp_text" size="40" value="'+title+'" /></td>';
						html += '<td class="center"><input type="text" name="subInfoDesc[]" class="resp_text" size="30" value="" /></td>';
						html += '<td class="center"><button type="button" class="btn_minus" onclick="del_subInfo(this);"></button></td>';
						html += '</tr>';
						$("#subInfoTable").append(html);
					}
				}else{
					if( goodSubInfo[i] ) title = goodSubInfo[i];
					$("#subInfoTable").append('<tr><td class="center"><img src="/admin/skin/default/images/common/icon_move.png" /></td><td class="center"><input type="text" name="subInfoTitle[]" value="'+title+'" class="wp85"/></td><td class="center"><input type="text" name="subInfoDesc[]" value="" class="wp85"/></td><td class="center"><button class="goodsSubInfoDel btn_minus" type="button" onclick="del_subInfo(this);"></button></tr>');
				}
				$(".tablednd").tableDnD({onDragClass: "dragRow"});
			}

			
		});
		$("#subInfoTable").closest('tr').show();
		
	}else{
		$("#subInfoTable").html("");
		$("#subInfoTable").closest("table").find("tr.nothing").removeClass('hide');
		//$("#subInfoTable").closest('tr').hide();
		//$("#btnGoodsSubInfoDescrioption").prop("checked",false);
	}
}

function chgfeedinfo(){
	if	($("input[name='feed_status']").is(':checked')){
		$("#feed_table").addClass('desc2');
		$("select[name='feed_goods_use']").attr('disabled', 'disabled');
		$("input[name='feed_evt_sdate']").attr('disabled', 'disabled');
		$("input[name='feed_evt_edate']").attr('disabled', 'disabled');
		$("input[name='feed_evt_text']").attr('disabled', 'disabled');
		$("input[name='feed_pay_type']").attr('disabled', 'disabled');
		$("input[name='feed_std_fixed']").attr('disabled', 'disabled');
		$(".feed_shipp_type").attr('disabled', 'disabled');
		$("#feed_add_txt").attr('disabled', 'disabled');
		$("#feed_table").find(".datepicker").datepicker("disable");
		$(".setlink").css('color', '#a8a8a8');
		$("select[name='feed_condition']").attr('disabled', 'disabled');
		$("select[name='product_flag']").attr('disabled', 'disabled');
		$("select[name='compound_state']").attr('disabled', 'disabled');
		$("select[name='installation_costs']").attr('disabled', 'disabled');
		$("input[name='openmarket_keyword']").attr('disabled', 'disabled');
		
	}else{
		$("#feed_table").removeClass('desc2');
		$("select[name='feed_goods_use']").removeAttr('disabled');
		$("input[name='feed_evt_sdate']").removeAttr('disabled');
		$("input[name='feed_evt_edate']").removeAttr('disabled');
		$("input[name='feed_evt_text']").removeAttr('disabled');
		$("input[name='feed_pay_type']").removeAttr('disabled');
		$("input[name='feed_std_fixed']").removeAttr('disabled');
		$(".feed_shipp_type").removeAttr('disabled');
		$("#feed_add_txt").removeAttr('disabled');
		$("#feed_table").find(".datepicker").datepicker("enable");
		$(".setlink").css('color', '#ff6600');
		$("select[name='feed_condition']").removeAttr('disabled', 'disabled');
		$("select[name='product_flag']").removeAttr('disabled', 'disabled');
		$("select[name='compound_state']").removeAttr('disabled', 'disabled');
		$("select[name='installation_costs']").removeAttr('disabled', 'disabled');
		$("input[name='openmarket_keyword']").removeAttr('disabled', 'disabled');

		var feed_pay_type = $("input[name='feed_pay_type']:checked").val();
		if(feed_pay_type == 'free'){
			$("input[name='feed_std_fixed']").attr('disabled', 'disabled');
		}else if(feed_pay_type == 'postpay'){
			$("input[name='feed_std_fixed']").attr('disabled', 'disabled');
			$("#feed_add_txt").attr('disabled', 'disabled');
		}
	}
}

// 입점 마케팅 설정
function chgfeedinfoNew(val){

	if (val == "Y"){
		$("#feed_table").removeClass('desc2');
		$("select[name='feed_goods_use']").removeAttr('disabled');
		$("input[name='feed_evt_sdate']").removeAttr('disabled');
		$("input[name='feed_evt_edate']").removeAttr('disabled');
		$("input[name='feed_evt_text']").removeAttr('disabled');
		$("input[name='feed_pay_type']").removeAttr('disabled');
		$("input[name='feed_std_fixed']").removeAttr('disabled');
		$("#installation_costs").removeAttr('disabled');
		$("#feed_condition").removeAttr('disabled');
		$(".feed_shipp_type").removeAttr('disabled');
		$("#product_flag").removeAttr('disabled');
		$("#compound_state").removeAttr('disabled');
		$("#feed_add_txt").removeAttr('disabled');
		$("#openmarket_keyword").removeAttr('disabled');
		$("#feed_table").find(".datepicker").datepicker("enable");
		$(".setlink").css('color', '#ff6600');

		var feed_pay_type = $("input[name='feed_pay_type']:checked").val();
		if(feed_pay_type == 'free'){
			$("input[name='feed_std_fixed']").attr('disabled', 'disabled');
			$("input[name='feed_std_postpay']").attr('disabled', 'disabled');
		}else if(feed_pay_type == 'fixed'){
			$("input[name='feed_std_postpay']").attr('disabled', 'disabled');
			//$("#feed_add_txt").attr('disabled', 'disabled');
		}else if(feed_pay_type == 'postpay'){
			$("input[name='feed_std_fixed']").attr('disabled', 'disabled');
			$("#feed_add_txt").attr('disabled', 'disabled');
		}
		$("#feed_table").find("tr").show();
	} else {
		$("#feed_table").addClass('desc2');
		$("select[name='feed_goods_use']").attr('disabled', 'disabled');
		$("input[name='feed_evt_sdate']").attr('disabled', 'disabled');
		$("input[name='feed_evt_edate']").attr('disabled', 'disabled');
		$("input[name='feed_evt_text']").attr('disabled', 'disabled');
		$("input[name='feed_pay_type']").attr('disabled', 'disabled');
		$("input[name='feed_std_fixed']").attr('disabled', 'disabled');
		$("input[name='feed_std_postpay']").attr('disabled', 'disabled');
		$("#feed_condition").attr('disabled', 'disabled');
		$("#installation_costs").attr('disabled', 'disabled');
		$("#product_flag").attr('disabled', 'disabled');
		$("#compound_state").attr('disabled', 'disabled');
		$(".feed_shipp_type").attr('disabled', 'disabled');
		$("#feed_add_txt").attr('disabled', 'disabled');
		$("#openmarket_keyword").attr('disabled', 'disabled');
		$("#feed_table").find(".datepicker").datepicker("disable");
		$("#openmarket_keyword").val('');
		$(".setlink").css('color', '#a8a8a8');
		$("#feed_table").find("tr").not(":first-child").hide();
	}

}

function goSetLink(link){
	if	(!$("input[name='feed_status']").is(':checked')){
		window.open(link);
	}
}

function sale_change(str){
	$.ajax({
		'url' : 'member_sale_change',
		'data' : {'sale_seq':str},
		'dataType' : 'html',
		'success' : function(data){
			$("#sale_info_table").html(data);
		}
	});

}


function openSettingOption(){
	var tmp_seq			= $("input[name='tmp_option_seq']").val();
	var goodsTax		= $("input[name='tax']:checked").val();
	if	(!goodsTax)	goodsTax	= 'tax';
	
	var policy			= goodsObj.reserve_policy;
	if	(!policy)	policy = $("input[name='reserve_policy']").val();
	if	(!policy)	policy =$("select[name='reserve_policy'] option:selected").val();

	var frequentlyopt	= $("input[name='frequentlyopt']").val();

	var windowOption	= 'width='+($(window).width()-80)+'px,height=900px,toolbar=no,titlebar=no,scrollbars=no,resizeable';

	var socialcp_input_type = eval('$("input[name=\'socialcp_input_type\']:checked").val()');
	var provider_seq = gl_provider_seq;
	if(!provider_seq){
		provider_seq			= $("input[name='provider_seq']").val();
	}
	if($("input[name='goods_gubun']:checked").val() == "provider" && provider_seq == 1){
		provider_seq = '';
	}


	if( $("input[name='frequentlytypeoptck']:checked").val() ) {
		var add_goods_seq = $("select[name='frequentlytypeopt']").find("option:selected").val();
		var goods_name = $("select[name='frequentlytypeopt']").find("option:selected").text();
		if( add_goods_seq<=0 ){
			alert("옵션정보를 가져올 상품을 선택해 주세요!");
			return false;
		}

		openDialogConfirm('정말로 ['+goods_name+'] 상품의 <br/>필수옵션 정보를 가져오시겠습니까?',400,200,function(){
			var url	= 'set_goods_options?provider_seq='+provider_seq+'&add_goods_seq='+add_goods_seq+'&goods_seq='+gl_goods_seq+'&tmp_policy='+policy+"&goodsTax="+goodsTax+"&socialcp_input_type="+socialcp_input_type+'&package_yn='+gl_package_yn;
			optionTmpPopup	= window.open(url, 'OPTION_POP', windowOption);
		},function(){
			if	(tmp_seq)	var url	= 'set_goods_options?provider_seq='+provider_seq+'&tmp_seq='+tmp_seq+'&tmp_policy='+policy+"&goodsTax="+goodsTax+"&socialcp_input_type="+socialcp_input_type+'&package_yn='+gl_package_yn;
			else			var url	= 'set_goods_options?provider_seq='+provider_seq+'&goods_seq='+gl_goods_seq+'&tmp_policy='+policy+"&goodsTax="+goodsTax+"&socialcp_input_type="+socialcp_input_type+'&package_yn='+gl_package_yn;
			optionTmpPopup	= window.open(url, 'OPTION_POP', windowOption);
		});
	}else{
		if	(tmp_seq)	var url	= 'set_goods_options?provider_seq='+provider_seq+'&tmp_seq='+tmp_seq+'&tmp_policy='+policy+"&goodsTax="+goodsTax+"&socialcp_input_type="+socialcp_input_type+'&package_yn='+gl_package_yn+'&frequentlyopt='+frequentlyopt;
		else			var url	= 'set_goods_options?provider_seq='+provider_seq+'&goods_seq='+gl_goods_seq+'&tmp_policy='+policy+"&goodsTax="+goodsTax+"&socialcp_input_type="+socialcp_input_type+'&package_yn='+gl_package_yn+'&frequentlyopt='+frequentlyopt;
		optionTmpPopup	= window.open(url, 'OPTION_POP', windowOption);
	}
}


function openSettingSubOption(){
	var tmp_seq				= $("input[name='tmp_suboption_seq']").val();
	var goodsTax			= $("input[name='tax']:checked").val();
	var policy				= $("input[name='sub_reserve_policy']").val();
	var windowOption		= 'width='+$(window).width()+'px,height=750px,toolbar=no,titlebar=no,scrollbars=yes,resizeable';
	var socialcp_input_type	= eval('$("input[name=\'socialcp_input_type\']:checked").val()');
	var provider_seq		= gl_provider_seq;
	var frequentlysub	= $("input[name='frequentlysub']").val();

	if (!goodsTax)
		goodsTax			= 'tax';

	if(!provider_seq)
		provider_seq		= $("input[name='provider_seq']").val();


	var package_yn_suboption = $("input[name='package_yn_suboption']").val();

	if( $("input[name='frequentlytypesuboptck']:checked").val() ) {
		var add_goods_seq = $("select[name='frequentlytypesubopt']").find("option:selected").val();
		var goods_name = $("select[name='frequentlytypesubopt']").find("option:selected").text();
		if( add_goods_seq<=0 ){
			alert("옵션정보를 가져올 상품을 선택해 주세요!");
			return false;
		}
		openDialogConfirm('정말로  ['+goods_name+'] 상품의 <br/>추가구성옵션 정보를 가져오시겠습니까?',400,200,function() {
			var url	= 'set_goods_suboptions?provider_seq='+provider_seq+'&add_goods_seq='+add_goods_seq+'&goods_seq='+gl_goods_seq+'&sub_tmp_policy='+policy+"&goodsTax="+goodsTax+"&socialcp_input_type="+socialcp_input_type+"&package_yn="+package_yn_suboption;
			window.open(url, 'SUB_OPTION_POP', windowOption);
		},function(){
			if	(tmp_seq)	var url	= 'set_goods_suboptions?provider_seq='+provider_seq+'&tmp_seq='+tmp_seq+'&sub_tmp_policy='+policy+"&goodsTax="+goodsTax+"&socialcp_input_type="+socialcp_input_type+"&package_yn="+package_yn_suboption;
			else			var url	= 'set_goods_suboptions?provider_seq='+provider_seq+'&goods_seq='+gl_goods_seq+'&sub_tmp_policy='+policy+"&goodsTax="+goodsTax+"&socialcp_input_type="+socialcp_input_type+"&package_yn="+package_yn_suboption;
			window.open(url, 'SUB_OPTION_POP', windowOption);
		});
	}else{
		if	(tmp_seq)	var url	= 'set_goods_suboptions?provider_seq='+provider_seq+'&tmp_seq='+tmp_seq+'&sub_tmp_policy='+policy+"&goodsTax="+goodsTax+"&socialcp_input_type="+socialcp_input_type+"&package_yn="+package_yn_suboption+'&frequentlysub='+frequentlysub;
		else			var url	= 'set_goods_suboptions?provider_seq='+provider_seq+'&goods_seq='+gl_goods_seq+'&sub_tmp_policy='+policy+"&goodsTax="+goodsTax+"&socialcp_input_type="+socialcp_input_type+"&package_yn="+package_yn_suboption+'&frequentlysub='+frequentlysub;

		window.open(url, 'SUB_OPTION_POP', windowOption);
	}
}


//새창에서 추가구성옵션 가져오기시
function openSettingSubOptionnew(add_goods_seq){
	var tmp_seq				= $("input[name='tmp_suboption_seq']").val();
	var goodsTax		= $("input[name='tax']:checked").val();
	if	(!goodsTax)	goodsTax	= 'tax';
	var policy				= $("select[name='reserve_policy'] option:selected").val();
	if	(!policy)	policy	= $("input[name='reserve_policy']").val();
	var windowOption		= 'width=1010px,height=700px,toolbar=no,titlebar=no,scrollbars=yes,resizeable';

	var socialcp_input_type = eval('$("input[name=\'socialcp_input_type\']:checked").val()');

	var provider_seq = gl_provider_seq;
	if(!provider_seq){
		provider_seq			= $("input[name='provider_seq']").val();
	}

	var url	= 'set_goods_suboptions?provider_seq='+provider_seq+'&add_goods_seq='+add_goods_seq+'&goods_seq='+gl_goods_seq+'&tmp_policy='+policy+"&goodsTax="+goodsTax+"&socialcp_input_type="+socialcp_input_type;
	window.open(url, 'SUB_OPTION_POP', windowOption);
}

// 새창에서 추가구성 옵션 생성 후 '확인' 클릭 시
function setSubOptionTmp(tmp_opno, tmp_frequently, subReservePolicy){
	
	var socialcp_input_type = eval('$("input[name=\'socialcp_input_type\']:checked").val()');

	var goodsTax		= $("input[name='tax']:checked").val();
	if	(!goodsTax)	goodsTax	= 'tax';
	var policy				= $("select[name='reserve_policy'] option:selected").val();
	if	(!policy)	policy	= $("input[name='reserve_policy']").val();

	var provider_seq = gl_provider_seq;
	if(!provider_seq){
		provider_seq			= $("input[name='provider_seq']").val();
	}
	$("iframe[name='actionFrame']").attr('src', 'set_goods_suboptions?provider_seq='+provider_seq+'&mode=view&tmp_seq='+tmp_opno+'&sub_tmp_policy='+subReservePolicy+"&goodsTax="+goodsTax+"&socialcp_input_type="+socialcp_input_type);
	$("input[name='tmp_suboption_seq']").val(tmp_opno);
	$("input[name='frequentlysub']").val(tmp_frequently);
	$("input[name='sub_reserve_policy']").val(subReservePolicy);
	if	(tmp_opno){
		set_option_select_layout();
	}
}

function viewSubOptionTmp(islimit){
	var tmp_seq				= $("input[name='tmp_suboption_seq']").val();
	var socialcp_input_type = eval('$("input[name=\'socialcp_input_type\']:checked").val()');
	var provider_seq 		= gl_provider_seq;
	if(!provider_seq){
		provider_seq		= $("input[name='provider_seq']").val();
	}
	var linkurl				= 'set_goods_suboptions?provider_seq='+provider_seq+'&mode=view&goods_seq='+gl_goods_seq+'&tmp_seq='+tmp_seq+'&goodsTax='+gl_tax+'&socialcp_input_type='+socialcp_input_type+'&islimit='+islimit;
	actionFrame.location.replace(linkurl);
}

function chgSuboptionReservePolicy(policy){
	var goods_seq		= gl_goods_seq;
	var goodsTax		= $("input[name='tax']:checked").val();
	if	(!goodsTax)	goodsTax	= 'tax';
	var tmp_seq			= $("input[name='tmp_suboption_seq']").val();

	var socialcp_input_type = $("input[name='socialcp_input_type']:checked").val();

	var provider_seq = gl_provider_seq;
	if(!provider_seq){
		provider_seq			= $("input[name='provider_seq']").val();
	}
	if			(tmp_seq){
		$("iframe[name='actionFrame']").attr('src', 'set_goods_suboptions?provider_seq='+provider_seq+'&mode=chgPolicy&tmp_seq='+tmp_seq+'&tmp_policy='+policy+"&goodsTax="+goodsTax+"&socialcp_input_type="+socialcp_input_type);
	}else if	(goods_seq){
		$("iframe[name='actionFrame']").attr('src', 'set_goods_suboptions?provider_seq='+provider_seq+'&mode=chgPolicy&goods_seq='+gl_goods_seq+'&tmp_policy='+gl_reserve_policy+'&goodsTax='+gl_tax+'&socialcp_input_type='+socialcp_input_type);
	}
}

//브랜드창닫기
function brand_close(){
	$("#brandPopup").hide();
}

//지역창닫기
function location_close(){
	$("#locationPopup").hide();
}

// 상품 사진 상세 Row 추가 
function goodsImageAdd(){

	var tag		= '';
	var img_idx = $(".goodsImageDetail > tbody > tr").not("tr.no_goods_image").length + 1;	// 이미지 row 갯수
	tag			+= '<tr class="cut-tr cutnum'+img_idx+'">';

	if (img_idx == 1) {
		//$("#multiadd").hide();
		$("#multitxt").show();
		$("#goodsImagePriview").show();
		$(".goodsRegistView").show();
	}
	
	tag			+= '<td class="center">'+img_idx+'</td>';
	tag			+= '<td class="center"><input type="hidden" name="goodsImageColor[]" value="" /> <span class="fileColorTitle"></span><button type="button" class="batchImageRegist resp_btn v2" data-divisoin="all">파일 선택</button></td>';
	tag			+= '<td class="center"><span class="goodsview view resp_btn" imageType="view">보기<input type="hidden" name="viewGoodsImage[]" value=""><input type="hidden" name="viewGoodsLabel[]" value=""></span></td>';
	tag			+= '<td class="center"><span class="goodslarge view resp_btn" imageType="large">보기<input type="hidden" name="largeGoodsImage[]" value=""><input type="hidden" name="largeGoodsLabel[]" value=""></span></td>';
	tag			+= '<td class="center"><span class="goodslist1 view resp_btn" imageType="list1">보기<input type="hidden" name="list1GoodsImage[]" value=""><input type="hidden" name="list1GoodsLabel[]" value=""></span></td>';
	tag			+= '<td class="center"><span class="goodslist2 view resp_btn" imageType="list2">보기<input type="hidden" name="list2GoodsImage[]" value=""><input type="hidden" name="list2GoodsLabel[]" value=""></span></td>';
	tag			+= '<td class="center"><span class="goodsthumbView view resp_btn" imageType="thumbView">보기<input type="hidden" name="thumbViewGoodsImage[]" value=""><input type="hidden" name="thumbViewGoodsLabel[]" value=""></span></td>';
	tag			+= '<td class="center"><span class="goodsthumbCart view resp_btn" imageType="thumbCart">보기<input type="hidden" name="thumbCartGoodsImage[]" value=""><input type="hidden" name="thumbCartGoodsLabel[]" value=""></span></td>';
	tag			+= '<td class="center"><span class="goodsthumbScroll view resp_btn" imageType="thumbScroll">보기<input type="hidden" name="thumbScrollGoodsImage[]" value=""><input type="hidden" name="thumbScrollGoodsLabel[]" value=""></span></td>';
	tag			+= '</tr>';

	$('#goodsImageTable .goodsImageDetail > tbody').append(tag);
}


//GOODS IMG 미리보기
function default_img(){

	var idx = 0;
	var imgUrl			= ($("input[name='viewGoodsImage[]']").eq(idx).val())?$("input[name='viewGoodsImage[]']").eq(idx).val():"";
	var imgUrlArray		= imgUrl.split('?');
	var _tmpsrc			= (imgUrlArray[1]) ? imgUrl+ '&' + new Date().getTime():imgUrl+ '?' + new Date().getTime();
	var src				= (imgUrl)?_tmpsrc:"";

	if	($('#goodsImageTable .goodsImageDetail tbody tr').not(".no_goods_image").length > 0){

		$('table#watermark_tb').show();
		
		if(!src) return;

		var cutname = "1번째";
		if(idx > 0){
			cutname = Number(idx) + 1;
			cutname += "번째";
		}
		var clone = $("#goodsImageMake").clone();
		clone.find("th img").attr("src",src);
		clone.find("table tr").eq(0).find("td span").html( cutname + " - 대표 사진" );
		$("#goodsImagePriview").html(clone);
		$("#fileurl").html(src);
		$("input[name='idx']").val(idx);
		$("input[name='imgKind']").val('view');
		// 레이블 표시 :: 2016-04-28 lwh
		var label_view = $("form input[name='viewGoodsLabel[]']").eq(idx).val();
		if(label_view)	label_view = label_view;
		else			label_view = '-';
		$("#goodsImgLabel_view").html(label_view);
		$("#fileOptionAble").attr("data-target", idx);

		if($('#goodsImageTable .goodsImageDetail tbody').find('tr').eq(idx).find('.fileColorTitle').html()){
			$("#filecolor").html($('#goodsImageTable .goodsImageDetail tbody').find('tr').eq(idx).find('.fileColorTitle').clone());
		}else{
			$("#filecolor").html('<span class="gray">본 이미지에 매칭된 색상 없음</span>');
		}

		var imgWidth 	= $("input[name='viewGoodsImageWidth[]']").eq(idx).val();
		var imgHeight 	= $("input[name='viewGoodsImageHeight[]']").eq(idx).val();
		// 미리보기 이미지 사이즈 체크를 위해 마지막에 위치함
		$("#goodsImagePriview #viewImg").load(function () { //이미지가 로딩이 완료 된 후

			var sizetxt 		= '-';
			var halfImgWidth	= $(this).width() / 2;
			var halfImgHeight 	= $(this).height() / 2;
			var positionW 		= ($(this).parent().width() - $(this).width()) / 2;
			var version 		= Browser.detectIE();
			
			if(typeof imgWidth != 'undefined' && typeof imgHeight !='undefined'){
				sizetxt = imgWidth + ' X ' + imgHeight;
			}

			if( version) {
				$(this).attr("style","margin-top:-"+halfImgHeight+"px;margin-left:"+positionW+"px;");
			}else{
				$(this).attr("style","margin-top:-"+halfImgHeight+"px;-webkit-margin-start:-"+halfImgWidth+"px;");
			}

			$("#goodsImagePriview #filesize").html(sizetxt);
		});
		//이미지사이즈 체크를 위해 마지막에 위치함

		$(".ImageSort").parent().removeClass("hide");
		$(".goodsImageList").show();

	}else{
		//'등록된 사진이 없습니다' 노출.
		if($('#goodsImageTable .goodsImageDetail tbody tr.no_goods_image').length == 0){
			var noimgtd	= '<tr class="no_goods_image"><td class="center" colspan="9">등록된 사진이 없습니다.</td></tr>';
			$('#goodsImageTable .goodsImageDetail tbody').append(noimgtd);
		}
		$('table#watermark_tb').hide();
		$("#goodsImagePriview").html("등록된 사진이 없습니다.");
		$(".goodsImageList").hide();
	}
}

function fileColorOptionUse(){
	if($("input:checkbox[id='fileOptionAble']").is(":checked")) {
		$("input[name='fileColorradio']").removeAttr("disabled");
		$("#fileOptionTxt").removeClass("gray");
		$("#fileOptionValue").removeClass("gray");
	} else {
		$("input[name='fileColorradio']").attr("disabled","disabled");
		$("#fileOptionTxt").addClass("gray");
		$("#fileOptionValue").addClass("gray");

		$idx = $("#fileOptionAble").attr("data-target");
		$("#goodsImageTable .goodsImageDetail tbody tr").eq($idx).find(".fileColorTitle").html("");
		$("#goodsImageTable .goodsImageDetail tbody tr").eq($idx).find(".fileColorTitle").removeAttr("style");
		$("#goodsImageTable .goodsImageDetail tbody tr").eq($idx).find("input[name='goodsImageColor[]']").val("");
		$("#fileOptionValue").find("input[name='fileColorradio']").removeAttr("checked");
	}
}

function addFileColorOption($idx){
	var imgKind = $("input[name='imgKind']").val();
	$isColor = false;
	$fileOptionValueHtm = "";

	var colorStr = getColor();

	if (colorStr) {
		$isColor = true;
		$fileOptionValueHtm += colorStr;
	}

	if ($isColor) {
		if (imgKind == "view") {
			$("#fileOptionTitle").hide();
		} else {
			$("#fileOptionTitle").show();
		}

		$("#fileOptionAble").show();
		$("#fileOptionTxtAdd").show();
		$("#fileOptionTxt").show();
		$("#fileOptionValue").show();
		$("#fileNoOptionTxt").hide();
		$("#fileOptionValue").html($fileOptionValueHtm);
	} else {
		$("#fileOptionAble").hide();
		$("#fileNoOptionTxt").show();
		$("#fileOptionTxt").hide();
		$("#fileOptionValue").hide();
		$("#fileOptionValue").html("");
	}

	$ccolor = $("#goodsImageTable .goodsImageDetail tbody tr").eq($idx).find("input[name='goodsImageColor[]']").val();
	try {
		if($ccolor) {
			if (imgKind == "view") {
				$("#fileOptionAble").attr("disabled", false);
				$("#fileOptionAble").attr("checked", "checked");
				$("#fileOptionTxt").removeClass("gray");
				$("#fileOptionValue").removeClass("gray");
				$("input[name='fileColorradio']").removeAttr("disabled");

				// 컬러가 소문자일 경우
				if ($("input[name='fileColorradio'][value='"+$ccolor.toLowerCase()+"']").length) {
					$("input[name='fileColorradio'][value='"+$ccolor.toLowerCase()+"']").attr("checked", "checked");
				}

				// 컬러가 대문자일 경우
				if ($("input[name='fileColorradio'][value='"+$ccolor.toUpperCase()+"']").length) {
					$("input[name='fileColorradio'][value='"+$ccolor.toUpperCase()+"']").attr("checked", "checked");
				}
			} else {
				$("#fileOptionAble").removeAttr("checked");
				$("#fileOptionAble").attr("disabled", true);
			}
		} else {
			if (imgKind == "view") {
				if($("input:checkbox[id='fileOptionAble']").is(":checked")) {
					$("input[name='fileColorradio']").removeAttr("disabled");
				} else {
					$("#fileOptionAble").removeAttr("disabled");
					$("input[name='fileColorradio']").attr("disabled","disabled");
				}
			} else {
				$("#fileOptionAble").removeAttr("checked");
				$("#fileOptionAble").attr("disabled", true);
			}
		}
	} catch (e) {
		if (imgKind == "view") {
			$("#fileOptionAble").removeAttr("disabled");
			$("input[name='fileColorradio']").removeAttr("disabled");
		} else {
			$("#fileOptionAble").removeAttr("checked");
			$("#fileOptionAble").attr("disabled", true);

		}
	}
}

function chgFileColor(ccolor){
	$idx = $("#fileOptionAble").attr("data-target");
	$("#goodsImageTable .goodsImageDetail tbody tr").eq($idx).find("input[name='goodsImageColor[]']").val(ccolor);
	$("#goodsImageTable .goodsImageDetail tbody tr").eq($idx).find(".fileColorTitle").html("<span style='width:30px; height:30px; margin-top:2px; margin-left:2px; border:0px solid #e8e8e8; color:"+ccolor+";size:25px;'><font style='display:inline-block;width:18px; height:18px; border:1px solid #ccc; background-color:"+ccolor+"; cursor:pointer;' >■</font></span>");
	$("#goodsImageTable .goodsImageDetail tbody tr").eq($idx).find(".fileColorTitle").css("color", ccolor);
}

function index_img(idx, key, name){
	var imgUrl			= ($("input[name='"+key+"GoodsImage[]']").eq(idx).val())?$("input[name='"+key+"GoodsImage[]']").eq(idx).val():"";
	var imgUrlArray		= imgUrl.split('?');
	var _tmpsrc			= (imgUrlArray[1]) ? imgUrl+ '&' + new Date().getTime():imgUrl+ '?' + new Date().getTime();
	var src				= (imgUrl)?_tmpsrc:"";

	if(!src)return;
	var cutname = "대표컷";
	if(idx > 0){
		cutname = Number(idx) + 1;
		cutname += "번째";
	}
	var clone = $("#goodsImageMake").clone();
	clone.find("th img").attr("src",src);
	//clone.find("td div").eq(0).html( cutname + " - " + name );
	$("#goodsImagePriview").html(clone);
	$("#fileurl").html(src);
	$("input[name='idx']").val(idx);
	$("input[name='imgKind']").val(key);
	// 레이블 표시 :: 2016-04-28 lwh
	var label_view = $("form input[name='"+key+"GoodsLabel[]']").eq(idx).val();
	if(label_view)	label_view = label_view;
	else			label_view = '-';
	$("#goodsImgLabel_view").html(label_view);
	$("#fileOptionAble").attr("data-target", idx);
	if(key == 'view'){
		if($('#goodsImageTable .goodsImageDetail tbody').find('tr').eq(idx).find('.fileColorTitle').html()){
			$("#filecolor").html($('#goodsImageTable .goodsImageDetail tbody').find('tr').eq(idx).find('.fileColorTitle').clone());
		}else{
			$("#filecolor").html('<span class="gray">본 이미지에 매칭된 색상 없음</span>');
		}
	}
	$('#goodsImageTable .goodsImageDetail tbody tr').eq(idx).find('td').eq(2).find('span.view').removeClass("v3");

	var imgWidth 	= $("input[name='viewGoodsImageWidth[]']").eq(idx).val();
	var imgHeight 	= $("input[name='viewGoodsImageHeight[]']").eq(idx).val();
	// 미리보기 이미지 사이즈 체크를 위해 마지막에 위치함
	$("#viewImg").load(function () { //이미지가 로딩이 완료 된 후

		var sizetxt 		= '-';
		var halfImgWidth	= $(this).width() / 2;
		var halfImgHeight 	= $(this).height() / 2;
		var positionW 		= ($(this).parent().width() - $(this).width()) / 2;
		var version 		= Browser.detectIE();
		
		if(typeof imgWidth != 'undefined' && typeof imgHeight !='undefined'){
			sizetxt = imgWidth + ' X ' + imgHeight;
		}
			
		if( version) {
			$(this).attr("style","margin-top:-"+halfImgHeight+"px;margin-left:"+positionW+"px;");
		}else{
			$(this).attr("style","margin-top:-"+halfImgHeight+"px;-webkit-margin-start:-"+halfImgWidth+"px;");
		}

		$("#filesize").html(sizetxt);
	});
	//이미지사이즈 체크를 위해 마지막에 위치함

	//이미지사이즈 체크를 위해 마지막에 위치함

}
/*
function each_goods_image()
{
	var division 	= $("input[name='imgKind']").val();
	var divisionIdx = $("input[name='idx']").val();
	var height = 280;
	if(division == 'view') height = 430;

	if(gl_goods_seq)
		goodsLink	= '&no=' + gl_goods_seq;
	else
		goodsLink	= '';

	window.open('popup_image?division=' + division + goodsLink + '&idx='+divisionIdx,'','width=550,height='+height);
}
*/

function each_goods_image_download()
{
	var src	= $("#viewImg").attr("src");
	src		= src.split('?');
	actionFrame.location.href = "../../common/download?downfile="+escape(src[0]);
}

function relation_count_chk(){
	var width	= $("input[name='relation_count_w']").val();
	var height  = $("input[name='relation_count_h']").val();
	var sum		= parseInt(width) * parseInt(height);
	$("#relation_count_total").html(sum);
}

function optReplace(str){
	var tmp = "";
	tmp = str.replace(/\"/gi, "");
	return tmp;
}

function reserve_policy(){
	var policy	= $("select[name='reserve_policy'] option:selected").val();
	if	(!policy)	policy	= $("input[name='reserve_policy']").val();

	if(policy == 'shop'){
		if(gl_default_reserve_percent){
			$("input[name='reserveRate[]']").val(gl_default_reserve_percent);
			$("select[name='reserveUnit[]'] option[value='percent']").attr('selected',true);

			$("input[name='subReserveRate[]']").val(gl_default_reserve_percent);
			$("select[name='subReserveUnit[]'] option[value='percent']").attr('selected',true);
		}

		$("input[name='reserveRate[]'], select[name='reserveUnit[]'], input[name='reserve[]'], input[name='subReserveRate[]'], select[name='subReserveUnit[]'], input[name='subReserve[]']").attr('readonly',true).css('opacity',0.5);
		$("select[name='reserveUnit[]'] option[value!='percent'], select[name='subReserveUnit[]'] option[value!='percent']").attr('disabled',true);
		$(".policy_str").each(function(){$(this).text("통합정책")});
	}else{
		$("input[name='reserveRate[]'], select[name='reserveUnit[]'], input[name='reserve[]'], input[name='subReserveRate[]'], select[name='subReserveUnit[]'], input[name='subReserve[]']").removeAttr('readonly').css('opacity',1);
		$("select[name='reserveUnit[]'] option[value!='percent'], select[name='subReserveUnit[]'] option[value!='percent']").removeAttr('disabled');
		$(".policy_str").each(function(){$(this).text("개별정책")});
	}
	calulate_option_price();
	calulate_subOption_price();
}

function able_save(){
	save_flag = true;
}


function chk_stockDesc2()
{
	var totstock = 0;
	$("#optionLayer input[name='stock[]']").each(function(){
		totstock += parseInt($(this).val());
	});
	if(!totstock) totstock = 0;

	var totablestock = 0;
	$("#optionLayer input[name='unUsableStock[]']").each(function(k){
		stock = $("#optionLayer input[name='stock[]']").eq(k).val() - $(this).val()
		if(stock>0){
			totablestock += parseInt(stock);
		}
	});

	var runout = gl_runout;
	var ableStockLimit = 0;

	if (gl_runout == 'ableStock')
		ableStockLimit = gl_ableStockLimit;

	if( $("input[name='runout_type']:checked").val() == 'goods' ){
		runout = $("input[name='runout']:checked").val();
		ableStockLimit = parseInt($("input[name='ableStockLimit']").val())+1;
	}

	if(totstock<1 && runout=='stock' ){
		return false;
	}

	if(totablestock < ableStockLimit && runout=='ableStock'){
		return false;
	}

	return true;
}

function chk_stockDesc()
{

	var totablestock  	= check_option_stock();							// 실 판매한 총 재고수량 (재고설정, 주문설정에 따라 실제 판매가능한 총 재고수량 계산)

	var runout_type		= $("input[name='runout_type']:checked").val();	// 통합재고 shop, 개별재고 goods
	var runout 			= gl_runout;									// 통합재고체크 기준 : 재고(stock) or 가용재고(ablestock) or 재고상관없음(unlimited)
	var ableStockLimit 	= 0;											// 정상판매 기준 수량 (주문설정) - 가용재고 기준 체크일때에만 사용됨.

	// 가용재고 기준일 때 정상판매 기준 수량
	if (runout == 'ableStock') {
		ableStockLimit = gl_ableStockLimit;
	}
	// 개별(goods) 설정일 때 
	if( runout_type == 'goods' ){
		runout 			= $("input[name='runout']:checked").val();
		ableStockLimit 	= parseInt($("input[name='ableStockLimit']").val())+1;
	}

	var chkStockVal 	= false;											// 주문 가능한 재고 상태
	var chkStatusVal	= $("input[name='goodsStatus']:checked").val();		// 선택된 판매 상태
	var order_runout	= runout;
	var goods_status 	= null;												// 상품 수정인 경우 저장된 판매 상태 값

	if(typeof goodsObj.goods_status != 'undefined' && goodsObj.goods_status != null){
		goods_status = goodsObj.goods_status;
	}

	if (totablestock > 0){
		chkStockVal = true;
	}

	var runout_name = "재고";
	if(runout == "ableStock") {
		runout_name = "가용재고";
	}

	// 판매상태 : 정상 선택 시
	if(chkStatusVal == "normal"){

		// 판매 가능 재고(가용재고)가 없으면 수정불가
		if(!chkStockVal) {
			// 선택되어 있던 상태값으로 변경.
			if (goods_status != 'normal' && goods_status != null && goods_status != 'null') {
				$("input[name='goodsStatus'][value='" + goods_status + "']").attr('checked',true);
			} else {
				$("input[name='goodsStatus'][value='runout']").attr('checked',true);
			}
		}
		
		if (!chkStockVal && runout == 'stock' ){
			var msg = "";
			msg += "현재 판매방식은 재고가 '1'개 이상일 때 '정상'상태로 판매가 가능합니다.<br /><br />";
			msg += "재고 수량을 입력하시거나 또는 상품의 상태를 '정상'이 아닌 것으로 변경한 후 다시 저장해 주세요.";
					openDialogAlert(msg,630,260);
			return false;
		}

		if(!chkStockVal && runout=='ableStock'){
			var msg = "";
			msg += "현재 판매방식은 가용재고가 '"+ableStockLimit+"'개 이상일 때 '정상'상태로 판매가 가능합니다.<br /><br />";
			msg += "재고 수량을 입력하시거나 또는 상품의 상태를 '정상'이 아닌 것으로 변경한 후 다시 저장해 주세요.";
			openDialogAlert(msg,630,260);
			return false;
		}

	}else if (chkStatusVal == 'runout') {

		if (chkStockVal){
			
			if (goods_status != 'runout' && goods_status != null && goods_status != 'null') {
				$("input[name='goodsStatus'][value='" + goods_status + "']").attr('checked',true);
			} else {
				$("input[name='goodsStatus'][value='normal']").attr('checked',true);
			}

			if ((runout_type == 'goods' && runout == 'unlimited') || (runout_type == 'shop' && order_runout == 'unlimited') ){
				openDialogAlert("재고와 상관없이 판매할경우 품절로 변경이 불가능합니다.<br/>판매를 중지하시려면 <b style='color:red;'>'재고 확보중'</b> 또는 <b style='color:red;'>'판매중지'</b>로 변경하시면 됩니다.",600,150);
			} else {
				openDialogAlert(runout_name + "가 남아 있는 상품은 품절처리 불가합니다.",400,150);
			}

			return false;
		}
	}


	return true;
}

// 공통 정보 삭제
function goods_info_del(){

	var seq = $("select[name='info_select_tmp']").val();

	if(seq == ""){
		alert("삭제할 공용정보를 선택하세요");
	}else{
		if(confirm("선택한 공용정보를 삭제하시겠습니까?\n삭제후에는 복구 할 수 없습니다.")){

			$.ajax({
				type: "get",
				url: "../goods_process/goods_info_del",
				data: "seq="+seq,
				success: function(result){
					var select_index = $("select[name='info_select_tmp'] option:selected").index();
					$('select[name="info_select_tmp"] option:eq('+select_index+')').remove();
					$('input[name="info_name"]').val("");
					if($("input[name='info_select_seq']").val() == seq) $("input[name='info_select_seq']").val('');

					alert("상품 공통 정보가 삭제 되었습니다.");
				}
			});

		}
	}
}

// 복수구매할인 체크
function chk_multisale(){
	if	(!$("input[name='multiDiscountUse']").attr('checked') || $("input[name='multiDiscountEa']").val() > 1){
		return true;
	}else{
		openDialogAlert("복수구매 할인은 최소 2개 이상부터 가능합니다.",400,140);
		return false;
	}
}

/* 저장하기 */
function goods_save(saveType){
	var goodsSeq	= gl_goods_seq;
	if($("input[name='provider_seq']").val()==''){
		openDialogAlert("입점사를 선택해주세요.",400,150,function(){
			$("select[name='provider_seq_selector']").next(".ui-combobox").children("input").eq(0).focus();
		});
		return false;
	}

	// 저장 후 동작 설정
	$("input[name='save_type']").val(saveType);

	// 초기화
	$("#goods_permit_lay").html('');

	// 상품 승인시 알림창 추가 :: 2016-03-09 lwh
	var old_status	= goodsObj.provider_status;
	var now_status	= $("input[name='provider_status']:checked").val();
	var permit_diff = '';
	var otp_html	= '';
	const deploymentId = document.getElementsByName('mobile_deploymentId')[0];
	const geditorExcute = document.getElementsByName('geditorExcute')[0];

	if(old_status == '0' && now_status == '1'){
		/* PC, 모바일 상품 설명 대량 업로드 되는 부분 방지를 위해 disable 처리 */
		if ((goodsSeq && gl_operation_type === 'heavy') || (goodsSeq &&  deploymentId.value === '' && geditorExcute.value === 'false')) {
			$("#goodscontents").attr('disabled',true);
			$("#mobile_contents").attr('disabled',true);
			$("#commonContents").attr('disabled',true);
		}
		$.ajax({
			type: "post",
			url: "../goods/goods_opt_permit",
			data: "goods_seq="+goodsObj.goods_seq+"&data="+$("#goodsRegist").serialize(),
			success: function(html){
				/* PC, 모바일 상품 설명 대량 업로드 되는 부분 방지를 위해 disable 해제 */
				if ((goodsSeq && gl_operation_type === 'heavy') || (goodsSeq &&  deploymentId.value === '' && geditorExcute.value === 'false')) {
					$("#goodscontents").attr('disabled',false);
					$("#mobile_contents").attr('disabled',false);
					$("#commonContents").attr('disabled',true);
				}
				$("#goods_permit_lay").append(html);
				openDialog("상품 승인 확인", "goods_permit_lay", {"width":"900","height":"620","show" : "fade","hide" : "fade"});
			}
		});
	}else{
		goods_save_submit();
	}
}

/* 폼전송 */
function goods_save_submit(){
	var goodsSeq	= gl_goods_seq;
	const deploymentId = document.getElementsByName('mobile_deploymentId')[0];
	const geditorExcute = document.getElementsByName('geditorExcute')[0];

	if(chk_stockDesc()){
		loadingStart();
		/* 다량옵션오류방지를 위하여 인코딩된 옵션폼값을 인코딩 : disable 처리 */
		encodeFormValue("#optionLayer,#suboptionLayer");

		/* PC, 모바일 상품 설명 대량 업로드 되는 부분 방지를 위해 disable 처리 */
		// 전용스킨이면서 상품번호가 있거나 반응형스킨이면서 발행번호가 존재하지 않은 경우만 초기화

		var protection = false;
		if (goodsSeq) {
			if (gl_operation_type === 'light'){
				if (deploymentId.value === '' && geditorExcute.value === 'false') {
					protection = true;
				}
			} else {
				protection = true;
			}
		}

		if (protection === true) {
			$("#goodscontents").attr('disabled',true);
			$("#mobile_contents").attr('disabled',true);
			$("#commonContents").attr('disabled',true);
		}

		/* 폼 전송 */
		submitEditorForm(document.goodsRegist);

		/* 다량옵션오류방지를 위하여 인코딩된 옵션폼값을 인코딩 : disable 해제 */
		encodeFormValueOff("#optionLayer,#suboptionLayer");

		/* PC, 모바일 상품 설명 대량 업로드 되는 부분 방지를 위해 disable 해제 */
		if (protection === true) {
			$("#goodscontents").attr('disabled',false);
			$("#mobile_contents").attr('disabled',false);
			$("#commonContents").attr('disabled',true);
		}
	}
}


/* 워터마크 적용 */
function watermark()
{
	var target_image = new Array();
	// largeGoodsImage[] viewGoodsImage[] list1GoodsImage[] list2GoodsImage[]
	$("input[name='largeGoodsImage[]'],input[name='viewGoodsImage[]']").each(function(i){
		target_image[i] = $(this).val();
	});
	target_image = target_image.join("|");
	target_image = encodeURIComponent(target_image);

	$.ajax({
		type: "post",
		url: "../goods_process/watermark_goods",
		data: "goods_seq="+gl_goods_seq+"&target_image="+encodeURIComponent(target_image),
		success: function(result){
			if(result == 'OK'){
				openDialogAlert("워터마크가 적용되었습니다.",400,140);
				default_img();
			}
			if(result == 'ERR' || result == "FALSE"){
				openDialogAlert("적용할 워터마크가 없습니다.<br/>워터마크를 먼저 설정해 주세요.",400,160);
			}
			if(result == 'ERRGIF'){
				openDialogAlert("gif 이미지에 대한 워터마크 기능은 추후 지원 예정입니다.",400,140);
			}
		}

	});


}

function watermark_recovery()
{
	var target_image = new Array();
	// largeGoodsImage[] viewGoodsImage[] list1GoodsImage[] list2GoodsImage[]
	$("input[name='largeGoodsImage[]'],input[name='viewGoodsImage[]'],input[name='list1GoodsImage[]'],input[name='list2GoodsImage[]']").each(function(i){
		target_image[i] = $(this).val();
	});
	target_image = target_image.join("|");
	target_image = encodeURIComponent(target_image);
	$.ajax({
		type: "post",
		url: "../goods_process/watermark_recovery",
		data: "goods_seq=" + gl_goods_seq + "&target_image="+encodeURIComponent(target_image),
		success: function(result){
			if(result == 'OK'){
				openDialogAlert("워터마크가 제거되었습니다.",400,140);
				default_img();
			}
			if(result == 'ERR'){
				openDialogAlert("제거할 워터마크가 없습니다",400,140);
			}
		}
	});
	default_img();
}

// 재고에 따른 판매 - 개별 설정 확인
function check_runout(closeLay)
{
	var msg 			= "";
	var couponmsg 		= "";

	if(typeof closeLay == "undefined") closeLay = "";
	//$("table.stock-qa-table tr").removeClass("red");

	if (socialcpuse_flag && $("input[name='coupon_serial_type'][value='n']").is(":checked"))
		couponmsg = "<font class='red' >+ 유효한 제휴사 티켓번호가 </font>";

	if( $("input[name='runout_type']:checked").val()=='goods' ){

		//$("input[name='runout']:checked").closest('tr').addClass("red");
		//$("input[name='runout']:checked").closest('tr').next().addClass("red");

		var ableStockLimit_org	= parseInt($("input[name='ableStockLimit'").val());
		var ableStockLimit		= parseInt($("input[name='ableStockLimit'").val())+1;
		//$("#ableStockLimitMsg").html(ableStockLimit);

		var goods_runout		= $("input[name='runout']:checked").val();

		switch (goods_runout) {
			case	'stock' :
				msg = "(재고가 있으면 판매)";
				$(".ableStock_sub").addClass('hide');
				break;
			case	'ableStock' :
				msg = "(가용 재고가 있으면 판매)";
				$(".ableStock_sub").removeClass('hide');
				break;
			case	'unlimited' :
				msg = "(재고와 상관없이 판매)";
				$(".ableStock_sub").addClass('hide');
			break;
		}

		$("input[name='runout_policy']").val($("input[name='runout']:checked").val());
		$("input[name='able_stock_limit']").val($("input[name='ableStockLimit'").val());

		$("#runout_policy_msg").removeClass("hide");
		$("#runout_policy_msg").html(msg);
	}else{

		msg = "";

		/*ableStockLimit_org count가 설정되지 않았을때*/
		if(gl_runout == 'ableStock' && !ableStockLimit_org) {
			var ableStockLimit_org	= parseInt($("input[name='ableStockLimit'").val());
			var ableStockLimit		= parseInt($("input[name='ableStockLimit'").val())+1;
		}
		/*end*/

		$("input[name='runout_policy']").val('');
		$("input[name='able_stock_limit']").val('');

		$("#runout_policy_msg").addClass("hide");
		$("#runout_policy_msg").html('');
	}

	if(closeLay) closeDialog(closeLay);

	//setOptionStockSetText();

}

// 재고에 따른 판매 - 개별 설정
function check_runout_type(obj)
{
	var runout_policy = '';
	var able_stock_limit = 0;

	//$("table#goods_runout").attr('disabled',true);
	if( $("input[name='runout_type']:checked").val()=='goods' ){
	// 개별 설정일 때
		runout_policy 		= goodsObj.runout_policy;
		able_stock_limit 	= goodsObj.able_stock_limit;
		$("table#goods_runout").attr('disabled',false);
	}

	if( !runout_policy ){
		runout_policy 		= gl_runout;
		able_stock_limit 	= gl_ableStockLimit_org;
	}

	// 재고 개별 설정 값
	$("input[name='runout'][value='"+runout_policy+"']").attr("checked",true);
	$("input[name='ableStockLimit']").val(able_stock_limit);
}



//가격 디스플레이
function check_string_price(radioObj) {

	$(radioObj).each(function() {

		if (typeof radioObj == 'object' && radioObj.name != this.name)
			return;

		var selValue		= parseInt($(this).val(), 10);
		var name			= this.name;
		var target			= name.replace(/_use$/, '');

		if ($(this).is(":checked") == true) {
			$(this).closest("td").find("div.detail").show();
			$('input[name="' + target + '"').attr('disabled', false);
			$('input[name="' + target + '_color"').attr('disabled', false);
			$('select[name="' + target + '_link"').attr('disabled', false);
			$('select[name="' + target + '_link_target"').attr('disabled', false);

			if ($('select[name="' + target + '_link"').val() == 'direct')
				$('input[name="' + target + '_link_url"').attr('disabled', false);
		}else{
			$(this).closest("td").find("div.detail").hide();
			$('input[name="' + target + '"').attr('disabled', true);
			$('input[name="' + target + '_color"').attr('disabled', true);
			$('input[name="' + target + '_link_url"').attr('disabled', true);
			$('select[name="' + target + '_link"').attr('disabled', true);
			$('select[name="' + target + '_link_target"').attr('disabled', true);
		}
	})

	// 구매자별 판매가격 세팅별 문구 노출
	setting_guide_msg();
}

// 구매 대상 제한 설정값 확인
function apply_string_price() {
	var arr = new Array();
	arr[0] = 'string_price';
	arr[1] = 'member_string_price';
	arr[2] = 'allmember_string_price';
	arr[3] = 'string_button';
	arr[4] = 'member_string_button';
	arr[5] = 'allmember_string_button';

	for(var i=0;i<6;i++){

		var target_name 		= arr[i];

		// 팝업창 설정 필드
		var source_use 			= "div#tb_string_price input[name='" + target_name+"_use']";
		var source 				= "div#tb_string_price input[name='" + target_name+"']";
		var source_color 		= "div#tb_string_price input[name='" + target_name+"_color']";
		var source_link 		= "div#tb_string_price select[name='" + target_name+"_link']";
		var source_link_url 	= "div#tb_string_price input[name='" + target_name+"_link_url']";
		var source_link_target 	= "div#tb_string_price select[name='" + target_name+"_link_target']";

		// 부모창 저장 필드
		var target_use 			= "div#frmStringPrice input[name='" + target_name+"_use']";
		var target 				= "div#frmStringPrice input[name='" + target_name+"']";
		var target_color 		= "div#frmStringPrice input[name='" + target_name+"_color']";
		var target_link 		= "div#frmStringPrice input[name='" + target_name+"_link']";
		var target_link_url 	= "div#frmStringPrice input[name='" + target_name+"_link_url']";
		var target_link_target 	= "div#frmStringPrice input[name='" + target_name+"_link_target']";

		if( $(source_use).is(":checked") == true && !$(source).val() ){
			alert('대체 문구를 입력해주세요!');
			$(source).focus();
			return false;
		}

		if($(source_use).is(":checked")) $(target_use).val(1); else  $(target_use).val('');
		if($(source)) $(target).val( $(source).val() );
		if($(source_color)) $(target_color).val( $(source_color).val() );
		if($(source_link)) $(target_link).val( $(source_link).val() );
		if($(source_link_url)) $(target_link_url).val( $(source_link_url).val() );
		if($(source_link_target)) $(target_link_target).val( $(source_link_target).val() );

	}

	print_string_price('set');
	closeDialog('popStringPrice');
}

// 구매 대상 제한 설정값 노출 적용
function print_string_price(mode)
{
	var arr = {
		'price':{
			'string_price':'비회원',
			'member_string_price':'기본 등급',
			'allmember_string_price':'추가 등급',
			},
		'button':{
			'string_button':'비회원',
			'member_string_button':'기본 등급',
			'allmember_string_button':'추가 등급',
			}
	};

	if(typeof mode == 'undefined') mode = '';

	if(mode == 'get'){
		$selector = "div#frmStringPrice";
	}else{
		$selector = "div#tb_string_price";
	}

	var _price_list_arr 	= [];
	var _button_list_arr 	= [];
	var i 					= 0;
	$.each(arr.price,function(key,value){
		if(mode == 'get'){
			var source_use		= $($selector + " input[name='" + key+"']").val();
			if(source_use != ""){
				_price_list_arr[i] = value;
				i++;
			}
		}else{
			var source_use		= $($selector + " input[name='" + key+"_use']");
			if(source_use.is(":checked")){
				_price_list_arr[i] = value;
				i++;
			}
		}
	});
	i = 0;
	$.each(arr.button,function(key,value){
		if(mode == 'get'){
			var source_use		= $($selector + " input[name='" + key+"']").val();
			if(source_use != ""){
				_button_list_arr[i] = value;
				i++;
			}
		}else{
			var source_use		= $($selector + " input[name='" + key+"_use']");
			if(source_use.is(":checked")){
				_button_list_arr[i] = value;
				i++;
			}
		}
	});

	var _html = '(';
	if(_price_list_arr.length > 0){
		_html += "가격 노출 제한 - "+_price_list_arr.join("/");
	}
	if(_button_list_arr.length > 0){
		if(_price_list_arr.length > 0) _html += ", ";
		_html += "구매 버튼 제한 - "+_button_list_arr.join("/");
	}
	_html += ')';
	if(_price_list_arr.length > 0 || _button_list_arr.length > 0){
		$("div#frmStringPrice").closest("td").find(".stringPriceMessage").html(_html).show();
	}else{
		$("div#frmStringPrice").closest("td").find(".stringPriceMessage").html('');
	}

	/*
		var target_name		= arr[i];
		var target			= $("div#frmStringPrice input[name='" + target_name+"']").val();
		var target_color	= $("div#frmStringPrice input[name='" + target_name+"_color']").val();

		if(target_use == '0' || !target_use){
			if (i < 3) {
				$("#"+target_name+"_msg").html('판매가격 표기');
				if( $("div#frmStringPrice input[name='" + arr[i+3]+"_use']").val() == 0) $("#"+target_name+"_sale_msg").html('상품상태에 따라 구매 가능');
				else $("#"+target_name+"_sale_msg").html('구매 불가');
			} else {
				$("#"+target_name+"_msg").html('상품상태별 버튼');
				if( $("div#frmStringPrice input[name='" + arr[i-3]+"_use']").val() == 0) $("#"+target_name+"_msg").append(' 노출');
				else $("#"+target_name+"_msg").append(' 미노출');
			}
		}else{
			$("#"+target_name+"_msg").html('<span style="color: ' + target_color + '">' + target + '</span>');
			if (i < 3)
				$("#"+target_name+"_sale_msg").html('구매 불가');
		}
	}
	*/
}



// 구매자별 판매가격 세팅별 문구 노출 leewh 2015-01-29
function setting_guide_msg() {
	var obj_guide_msg = $("#setting_guide_msg");
	var string_use = $("div#tb_string_price input[name='string_price_use']:checked").val();
	var mem_use = $("div#tb_string_price input[name='member_string_price_use']:checked").val();
	var allmem_use = $("div#tb_string_price input[name='allmember_string_price_use']:checked").val();

	var msg1 = "모든 소비자(비회원,회원)가 구매 가능합니다.<br/>특이사항이 없습니다.";
	var msg2 = "비회원은 구매하지 못하며, 회원만 구매 가능합니다.";
	var msg3 = "비회원과 일반등급은 구매하지 못하며, 상위등급만 구매 가능합니다.";
	var dmsg1 = "비회원만 구매 가능하고 회원은 구매하지 못하는 비정상적인 상황입니다.<br/>다시 설정해 주세요.";
	var dmsg2 = "비회원과 일반등급만 구매 가능하고 상위등급은 구매하지 못하는 비정상적인 상황입니다.<br/>다시 설정해 주세요.";
	var dmsg3 = "비회원과 상위등급만 구매 가능하고 일반등급은 구매하지 못하는 비정상적인 상황입니다.<br/>다시 설정해주세요.";
	var dmsg4 = "일반등급만 구매 가능하고 비회원과 상위 등급은 구매하지 못하는 비정상적인 상황입니다.<br/>다시 설정해주세요.";
	var dmsg5 = "모든 소비자(비회원,회원)가 구매하지 못하는 비정상적인 상황입니다.";

	if (obj_guide_msg.length) {
		obj_guide_msg.html("");
		$("#btn_apply_string").attr("disabled",false);
		obj_guide_msg.removeClass("red");

		if (string_use==0 && mem_use==0 && allmem_use==0) {
			obj_guide_msg.html(msg1);
		}

		if (string_use==1 && mem_use==0 && allmem_use==0) {
			obj_guide_msg.html(msg2);
		}

		if (string_use==1 && mem_use==1 && allmem_use==0) {
			obj_guide_msg.html(msg3);
		}

		if (string_use==0 && mem_use==1 && allmem_use==1) {
			obj_guide_msg.addClass("red");
			obj_guide_msg.html(dmsg1);
			$("#btn_apply_string").attr("disabled",true);
		}

		if (string_use==0 && mem_use==0 && allmem_use==1) {
			obj_guide_msg.addClass("red");
			obj_guide_msg.html(dmsg2);
			$("#btn_apply_string").attr("disabled",true);
		}

		if (string_use==0 && mem_use==1 && allmem_use==0) {
			obj_guide_msg.addClass("red");
			obj_guide_msg.html(dmsg3);
			$("#btn_apply_string").attr("disabled",true);
		}

		if (string_use==1 && mem_use==0 && allmem_use==1) {
			obj_guide_msg.addClass("red");
			obj_guide_msg.html(dmsg4);
			$("#btn_apply_string").attr("disabled",true);
		}

		if (string_use==1 && mem_use==1 && allmem_use==1) {
			obj_guide_msg.addClass("red");
			obj_guide_msg.html(dmsg5);
		}

		if( string_use == 0 ) $("span#string_price_use_guide").html("노출");
		else $("span#string_price_use_guide").html("미노출");

		if( mem_use == 0 ) $("span#member_string_price_use_guide").html("노출");
		else $("span#member_string_price_use_guide").html("미노출");

		if( allmem_use == 0 ) $("span#allmember_string_price_use_guide").html("노출");
		else $("span#allmember_string_price_use_guide").html("미노출");

		obj_guide_msg.show();
	}
}

function onByteCheck(obj){
	var str			= obj.value;
	var bytes		= encodeURI(str).split(/%..|./).length - 1;
	if(bytes > 255){
		obj.value	= substr_utf8_bytes(str, 0, 255);
		bytes		= encodeURI(obj.value).split(/%..|./).length - 1;
	}
	$(obj).siblings('.etcContent_size').html(bytes);
}

function substr_utf8_bytes(str, startInBytes, lengthInBytes) {
	var resultStr = '';
	var startInChars = 0;

	for (bytePos = 0; bytePos < startInBytes; startInChars++) {
		ch = str.charCodeAt(startInChars);
		bytePos += (ch < 128) ? 1 : encode_utf8(str[startInChars]).length;
	}

	end = startInChars + lengthInBytes - 1;

	for (n = startInChars; startInChars <= end; n++) {
		ch = str.charCodeAt(n);
		end -= (ch < 128) ? 1 : encode_utf8(str[n]).length;

		resultStr += str[n];
	}

    return resultStr;
}

function encode_utf8(s) {
	return unescape( encodeURIComponent( s ) );
}

function dialog_hscode()
{
	openDialog("안내) 수출입상품코드", "dialog_hscode", {"width":570,"height":200});
}

function international_shipping_info(){

	if( $("input[name='option_international_shipping_status']").val() == 'y'){
		$("div.international_shipping_info").show();
		$("td.view_option_international_shipping_status").each(function(){
			$(this).html("Y");
		});

	}else{
		$("div.international_shipping_info").hide();
		$("td.view_option_international_shipping_status").each(function(){
			$(this).html("N");
		});
	}
}

//기본옵션상품의 필수옵션 해외배송여부
function set_option_international_shipping_status(bobj){
	var select_obj = $(bobj);
	var selected_val = select_obj.find("option:selected").val();
	$("input[name='option_international_shipping_status']").val(selected_val);

	international_shipping_info();
}
// 필수옵션 변경 팝업에서 필수옵션 해외배송여부
function set_option_international_shipping_popup(selected_val){
	$("input[name='option_international_shipping_status']").val(selected_val);

	international_shipping_info();
}

function clip_copy() {
	var meintext = $("#realvideourl").val();//'{realvideourl}';

　 if (window.clipboardData) {
　 　 window.clipboardData.setData("Text", meintext);
　 } else if (window.netscape) {
　 　 netscape.security.PrivilegeManager.enablePrivilege('UniversalXPConnect');
　 　 var clip = Components.classes['@mozilla.org/widget/clipboard;1'].createInstance(Components.interfaces.nsIClipboard);
　 　 if (!clip) return;
　 　 var trans = Components.classes['@mozilla.org/widget/transferable;1'].createInstance(Components.interfaces.nsITransferable);
　 　 if (!trans) return;
　 　 trans.addDataFlavor('text/unicode');
　 　 var str = new Object();
　 　 var len = new Object();
　 　 var str = Components.classes["@mozilla.org/supports-string;1"].createInstance(Components.interfaces.nsISupportsString);
　 　 var copytext=meintext;
　 　 str.data=copytext;
　 　 trans.setTransferData("text/unicode",str,copytext.length*2);
　 　 var clipid=Components.interfaces.nsIClipboard;
　 　 if (!clip) return false;
　 　 clip.setData(trans,null,clipid.kGlobalClipboard);
　 }

}

function check_goodsShippingPolicy(){
	var sel = $("select[name='shippingPolicy'] option:selected").val();
	var obj = $("input[name='goodsShippingPolicy']");
	obj.closest('td').attr('disabled',false);
	if( sel == 'shop'){
		obj.closest('td').attr('disabled',true);
	}

	$("input[name='goodsShippingPolicy']").closest("div").find("span").attr("disabled",true);
	$("input[name='goodsShippingPolicy']:checked").closest("div").find("span").attr("disabled",false);

}

function active_optionLayer(bobj,val,exist_val){

	$("#optionLayer").find("select[name='option_international_shipping_status_view']").attr('disabled', val);
	$("#optionLayer").find("select[name='reserve_policy']").attr('disabled', val);
	$("#optionLayer").find("select[name='reserveUnit[]']").attr('disabled', val);
	if( val ){
		$("#optionLayer").find("button.package_goods_make").closest("span").hide();
	}else{
		$("#optionLayer").find("button.package_goods_make").closest("span").show();
	}

	$("#optionLayer").find('tr.optionTr').find('input').each(function(){
		if($(this).attr('type') == 'text') $(this).attr('disabled', val);
	});
	if( val ){
		show_optionUse();
		bobj.closest("span").next().removeClass("hide");
		set_option_select_layout();
	}else{
		bobj.closest("span").next().addClass("hide");
		if(exist_val){
			if(confirm("필수옵션 사용을 해제 할 경우 기존에 작성한 내용은 사라집니다.\n다만, 필수옵션 만들기 클릭시 옵션명,값,가격등의 기초정보는 확인하실 수 있습니다.")){
				return;
			}
		}
	}
}

//마일리지 정책 변경
function chgReservePolicy(obj,tmp_seq){
	if	($(obj).val() == 'shop'){
		$("input[name='reserve_rate_all']").attr('disabled', true);
		setDisableSelectbox($("select[name='reserve_unit_all']"), true);
	}else{
		$("input[name='reserve_rate_all']").attr('disabled', false);
		setDisableSelectbox($("select[name='reserve_unit_all']"), false);
	}
	if(tmp_seq){
		optionFrame.location.href	= '../goods_process/save_tmpoption_cell?tmpSeq='+tmp_seq+'&target=tmp_policy_all&value='+$(obj).val();
	}
}

//selectbox disabled
function setDisableSelectbox(obj, disable){
	if	(disable){
		var orgVal	= $(obj).val();
		$(obj).css('background-color', '#f0f0f0');
		$(obj).find('option').css('background-color', '#f0f0f0');
		$(obj).bind("change", function(){
			$(obj).find("option[value='"+orgVal+"']").attr('selected', true);
		});
	}else{
		$(obj).css('background-color', '#fff');
		$(obj).find('option').css('background-color', '#fff');
		$(obj).unbind("change");
	}
}

// 패키지 상품 수 select
function reg_select_package_count(target){

	if(typeof target == "undefined") target = $("body");

	if( $("select[name='reg_package_count'] option:selected").val() ){
		var basic_package_count	= $("select[name='reg_package_count'] option:selected").val();
	}else{
		var basic_package_count	= $("input[name='reg_package_count']").val();
	}

	var td_len 				= $("th.reg_package_option_title_tbl",target).length;
	var num 				= 0;
	var package_goods_info 	= '';
	var _width				= 230;

	switch(basic_package_count){
		case "5": _width = 92; break;
		case "4": _width = 115; break;
		case "3": _width = 153; break;
		case "2": _width = 230; break;
		case "1": _width = 450; break;
	}
	if(td_len <= basic_package_count){
		for(var i = td_len; i < basic_package_count; i++){
			var tr_obj 				= $("th.reg_package_option_title_tbl",target).last();
			var tr_opt_obj 			= $("td.reg_package_option_tbl",target).last();
			
			if(tr_obj.length == 0){
				tr_obj 				= $("th.reg_option_title_tbl",target).last();
			}
			num = i+1;
			tr_obj.after("<th class='reg_package_option_title_tbl'>상품"+num+"</th>");
			package_goods_info = "<td class='reg_package_option_tbl'>";
			package_goods_info += "	<div class=\"reg_package_goods_name"+num+"\">";
			package_goods_info += "	<a href=\"/goods/view?no=\">";
			package_goods_info += "	<span class=\"reg_package_goods_seq"+num+"\"></span>";
			package_goods_info += "	<span class=\"reg_package_goods_name"+num+"\"></span>";
			package_goods_info += "	</a>";
			package_goods_info += "	</div>";
			package_goods_info += "	<div class=\"reg_package_option reg_package_option"+num+"\"></div>";
			package_goods_info += "	<div class=\"reg_package_unit_ea reg_package_unit_ea"+num+" hide \"> 주문당";
			package_goods_info += " 	<input type=\"text\" name=\"package_unit_ea"+num+"[]\" class=\"onlynumber\" style=\"text-align:right\" size=\"2\" value=\"\"/>";
			package_goods_info += " 	발송";
			package_goods_info += " 	<span class=\"tooltip_btn\" onClick=\"showTooltip(this, '../tooltip/goods', '#regist_package_ea')\" ></span>";
			package_goods_info += "	</div>";
			package_goods_info += "	<div class=\"reg_package_option_seq"+num+"\"></div>";
			package_goods_info += "	<input type=\"hidden\" name=\"reg_package_option_seq"+num+"[]\" value=\"\"/>";
			package_goods_info += "	</td>";

			tr_opt_obj.after(package_goods_info);
		}
	}
	if(td_len > basic_package_count){
		
		for(var i=td_len;i>basic_package_count;i--){
			num = i-1;
			$("th.reg_package_option_title_tbl",target).eq(num).remove();
			$(".optionTr td.reg_package_option_tbl",target).eq(num).remove();
		}
	}
	$(".optionTr td.reg_package_option_tbl .reg_package_goods_name",target).attr("style","width:"+_width+"px !important;");
	$(".optionTr td.reg_package_option_tbl .reg_package_option",target).attr("style","width:"+_width+"px !important;");
	
	//help_tooltip();
}

// 패키지 상품 연결상태 확인
function package_goods_make()
{
	if( $("div#optionLayer table tr.optionTr").length == 0 ){
		alert("적용할 옵션을 먼저 생성해 주세요.");
		return false;
	}

	var goods_seq			= '';
	var tmp_seq				= '';
	var provider_seq		= '';

	provider_seq			= $("input[name='provider_seq']").val();
	tmp_seq					= $("input[name='tmp_option_seq']").val();

	// 상품신규등록 시 구분값(본사/입점사)과 실제 선택된 입점사번호(provider_seq) 가 올바른지 확인.
	if(typeof scObj == 'undefined'){
		providerChk();
	}

	if( $("input[name='goodsSeq']").val() ) goods_seq = $("input[name='goodsSeq']").val();

	if( $("select[name='reg_package_count'] option:selected").val() ){
		var package_count	= $("select[name='reg_package_count'] option:selected").val();
	}else{
		var package_count	= $("input[name='reg_package_count']").val();
	}

	var data_url ="opt_type=opt&goods_seq="+goods_seq+"&tmp_seq="+tmp_seq+"&package=1";
	if(package_count){
		data_url += "&package_count="+package_count;
	}
	if(provider_seq){
		data_url += "&provider_seq="+provider_seq;
	}else{
		alert('입점사를 선택해주십시요.');
		return false;
	}

	// 상품등록페이지에서 직접 검색시
	if( $("input[name='goodsName']").length > 0 ){
		data_url += "&reg_type=1";
	}

	$.ajax({
		type: "get",
		url: "../goods/select_goods_options",
		data: data_url,
		async : false,
		success: function(result){
			$("#selectGoodsOptionsDialog").html(result);
		}
	});
	openDialog("상품 검색", "selectGoodsOptionsDialog", {"width":1100,"height":850});
}

function check_allpackage(idx,obj){
	if( $(obj).attr("checked") ){
		$("input[name='check_package_option" + idx + "[]']").attr("checked",true);
	}else{
		$("input[name='check_package_option" + idx + "[]']").attr("checked",false);
	}
}

function package_select_goods(goods_seq)
{
	$.ajax({
		type: "get",
		url: "../goods/select_goods_options_view",
		data: "goods_seq="+goods_seq,
		async : true,
		dataType : "html",
		success: function(result){
			$("#selectGoodsOptionsView").html(result);
		}
	});
}

function package_toggle_option_all(obj){
	var bObj 	= $(obj);
	var tblObj 	= bObj.closest("table");
	tblObj.find("tr td input[type='checkbox']").each(function(){
		if( bObj.attr("checked") ){
			$(this).attr("checked",true);
		}else{
			$(this).attr("checked",false);
		}
	});
	package_toggle_option_color();
}

function package_toggle_option(bobj){
	var objtr = $(bobj).closest("tr");
	if( objtr.find("input[type='checkbox']").attr('checked') ){
		objtr.find("input[type='checkbox']").attr('checked',false);
	}else{
		// objtr.css("background-color","#EBF1DE");
		objtr.find("input[type='checkbox']").attr('checked',true);
	}

	package_toggle_option_color();
}

function package_toggle_option_color(){
	var tblObj = $("table.option-select-table");
	var option_seq		= '';
	var goods_name		= '';
	var goods_seq		= '';
	var combine_option	= '';
	var stock			= '';
	var rstock			= '';
	var badstock		= '';
	var safe_stock		= '';
	var optioncode		= '';
	var weight			= '';
	tblObj.find("tr td input[type='checkbox']").each(function(){
		if( $(this).attr("checked") ){
			$(this).closest("tr").css("background-color","#EBF1DE");
			option_seq		+= $(this).val() + '|';
			goods_name		+= $(this).attr("goods_name") + '|';
			goods_seq		+= $(this).attr("goods_seq") + '|';
			combine_option	+= $(this).attr("combine_option") + '|';
			stock			+= $(this).attr("stock") + '|';
			rstock			+= $(this).attr("rstock") + '|';
			badstock		+= $(this).attr("badstock") + '|';
			safe_stock		+= $(this).attr("safe_stock") + '|';
			optioncode		+= $(this).attr("optioncode") + '|';
			weight			+= $(this).attr("weight") + '|';
		}else{
			$(this).closest("tr").css("background-color","#FFFFFF");
		}
	});
	var params = {
		'option_seq':option_seq,
		'goods_name':goods_name,
		'combine_option':combine_option,
		'goods_seq':goods_seq,
		'stock':stock,
		'rstock':rstock,
		'badstock':badstock,
		'safe_stock':safe_stock,
		'optioncode':optioncode,
		'weight':weight
	};
	package_select_option(params);
}

function package_select_option(obj){
	$("input[name='selected_option_seq']").val(obj.option_seq);
	$("input[name='selected_goods_name']").val(obj.goods_name);
	$("input[name='selected_options']").val(obj.combine_option);
	$("input[name='selected_goods_seq']").val(obj.goods_seq);
	$("input[name='selected_stock']").val(obj.stock);
	$("input[name='selected_rstock']").val(obj.rstock);
	$("input[name='selected_badstock']").val(obj.badstock);
	$("input[name='selected_safe_stock']").val(obj.safe_stock);
	$("input[name='selected_optioncode']").val(obj.optioncode);
	$("input[name='selected_weight']").val(obj.weight);
}

function del_package_selected(obj){
	var cellObj = $(obj).closest("tr");
	cellObj.find("input.package_option_seq").val('');
	cellObj.find("span.package_goods_seq").html('');
	cellObj.find("span.package_goods_name").html('');
	cellObj.find("div.package_option_name").html('');
}

// 패키지 옵션 연결
function apply_package_option(){
	var tmp_seq		= $("input[name='selected_tmp_seq']").val();
	var option_seq	= $("input[name='selected_option_seq']").val().split("|");
	var goods_name	= $("input[name='selected_goods_name']").val().split("|");
	var goods_seq	= $("input[name='selected_goods_seq']").val().split("|");
	var option_vals	= $("input[name='selected_options']").val().split("|");
	var stocks		= $("input[name='selected_stock']").val().split("|");
	var badstocks	= $("input[name='selected_badstock']").val().split("|");
	var safe_stocks	= $("input[name='selected_safe_stock']").val().split("|");
	var rstocks		= $("input[name='selected_rstock']").val().split("|");
	var optioncode	= $("input[name='selected_optioncode']").val().split("|");
	var weight		= $("input[name='selected_weight']").val().split("|");

	if(!option_seq){
		alert("검색한 상품의 옵션을 선택해주세요.");
		return false;
	}

	var check_var = false;
	var num = 0;
	var pos = 0;

	$("input.check_package_option").each(function(idx){
		if( $(this).attr("checked") ){
			pos = num % (option_seq.length-1);
			if(option_seq[pos]){
				$(this).closest("tr").find("input[name='package_option_seq"+$(this).val()+"[]']").val(option_seq[pos]);
				$(this).closest("tr").find("span.package_goods_name").html(goods_name[pos]);
				$(this).closest("tr").find("span.package_goods_seq").html('['+goods_seq[pos]+']');
				$(this).closest("tr").find("div.package_option_name").html(option_vals[pos]);
				$(this).closest("tr").find("div.package_option_name").attr("title",option_vals[pos]);
				$(this).closest("tr").find("div.package_option_etc").html(optioncode[pos] + '|' + weight[pos] + 'kg');
				$(this).closest("tr").find(".package_goods_info").removeClass("hide");
				$(this).closest("tr").find(".package_error").hide();
				var stockobj = $(this).closest("tr").find("input[name='package_option_stock"+$(this).val()+"[]']");
				stockobj.val(stocks[pos]);
				stockobj.attr('rstock',rstocks[pos]);
				stockobj.attr('badstock',badstocks[pos]);
				stockobj.attr('safe_stock',safe_stocks[pos]);
				stockobj.attr('optioncode',optioncode[pos]);
				stockobj.attr('weight',weight[pos]);
			}
			$(this).attr("checked",false);
			check_var = true;
			num++;
		}
	});

	if( !check_var ){
		alert("적용 대상 상품을 선택하세요.");
		return false;
	}

	$("input.check_allpackage").attr("checked",false);
}

function goods_package_suboption_apply(){
	var check_var = true;
	$("input[name='package_option_seq1[]']").each(function(idx){
		if( !$(this).val() ) check_var = false;
	});
	if(!check_var){
		alert('상품1을 연결해주세요.');
		return false;
	}

	$("input[name='package_option_seq1[]']").each(function(idx){
		var obj = $(this);
		var package_option_seq	= obj.val();
		var goods_name			= $("span.package_goods_name").eq(idx).html();
		var package_option		= $("div.package_option_name").eq(idx).html();
		var goods_seq			= $("span.package_goods_seq").eq(idx).html();
		var option_etc			= $("div.package_option_etc").eq(idx).html();

		$("span.tmp_package_goods_seq1").eq(idx).html(goods_seq);
		$("span.tmp_package_goods_name1").eq(idx).html(goods_name);
		$("div.tmp_package_option_name1").eq(idx).html(package_option);
		$("span.tmp_package_goodscode1").eq(idx).html(option_etc);

		$("input[name='tmp_package_unit_ea1[]'").eq(idx).val(1);
		$("input[name='tmp_package_option_seq1[]'").eq(idx).val(package_option_seq);
		$("input[name='tmp_package_option1[]'").eq(idx).val(package_option);
		$("input[name='tmp_package_goods_name1[]'").eq(idx).val(goods_name);
	});
	package_unit_ea_display_sub();
	closeDialog('selectGoodsOptionsDialog');
}

// 필수옵션 패키지 상품 적용
function goods_package_apply(){
	var check_var = true;
	$("input[name='package_option_seq1[]']").each(function(idx){
		if( !$(this).val() ) check_var = false;
	});
	if(!check_var){
		alert('상품1을 연결해주세요.');
		return false;
	}
	$("input[name='package_option_seq1[]']").each(function(idx){
		apply_package_option_seq($(this),idx);
	});
	$("input[name='package_option_seq2[]']").each(function(idx){
		apply_package_option_seq($(this),idx);
	});
	$("input[name='package_option_seq3[]']").each(function(idx){
		apply_package_option_seq($(this),idx);
	});
	$("input[name='package_option_seq4[]']").each(function(idx){
		apply_package_option_seq($(this),idx);
	});
	$("input[name='package_option_seq5[]']").each(function(idx){
		apply_package_option_seq($(this),idx);
	});

	set_stock_package();
	package_unit_ea_display();
	$(".package_error").hide();
	closeDialog('selectGoodsOptionsDialog');
	$("#connect_chkResult").hide(); //연결상태확인 hide
}

function apply_package_option_seq(obj,idx){
	var cobj				= obj.closest("tr");
	var num					= cobj.find("input.check_package_option").val();
	var package_option_seq	= obj.val();
	var goods_name			= cobj.find("span.package_goods_name"+num).html();
	var package_option		= cobj.find("div.package_option_name"+num).html();
	var goods_seq_str		= cobj.find("span.package_goods_seq"+num).html();
	var stockobj			= cobj.find("input.package_option_stock");
	var stock				= parseInt(stockobj.val());
	var badstock			= parseInt(stockobj.attr('badstock'));
	var rstock				= parseInt(stockobj.attr('rstock'));
	var safe_stock			= parseInt(stockobj.attr('safe_stock'));
	var optioncode			= parseInt(stockobj.attr('optioncode'));
	var weight				= parseInt(stockobj.attr('weight'));
	if	(isNaN(stock))		stock		= 0;
	if	(isNaN(badstock))	badstock	= 0;
	if	(isNaN(rstock))		rstock		= 0;
	if	(isNaN(safe_stock))	safe_stock	= 0;
	if	(isNaN(weight))		weight		= 0;
	if	(isNaN(optioncode))		optioncode	= '';

	var goods_seq = '';
	if(goods_seq_str){
		goods_seq = goods_seq_str.replace("[", "");
		goods_seq = goods_seq.replace("]", "");
	}

	if(optioncode ){
		optioncode += '|' + weight + 'kg';
	}else if(!optioncode){
		if(weight > 0){
			optioncode = weight + 'kg';
		}
	}
	var _stock = '';

	if(goods_name != ''){
		_stock = stock+' ('+badstock+')'+' / '+rstock+' / '+safe_stock;
	}

	$("span.reg_package_goods_seq" + num).eq(idx).html(goods_seq_str);
	$("span.reg_package_goods_name" + num).eq(idx).html(goods_name);
	$("span.reg_package_goods_name" + num).eq(idx).parent().attr('href','/goods/view?no='+goods_seq);
	$("div.reg_package_option" + num).eq(idx).html(package_option);
	$("input[name='package_unit_ea" + num + "[]']").eq(idx).val(1);
	$("input[name='reg_package_option_seq" + num+"[]']").eq(idx).val(package_option_seq);
	$("input[name='reg_package_option_stock" + num+"[]']").eq(idx).val(stock);
	$("div.reg_package_option_seq"+num).eq(idx).html(_stock);
	$("div.reg_package_option_code"+num).eq(idx).html(optioncode);
	$("span.reg_package_goods_seq" + num).closest("td").find(".package_error").hide();

}

// 패키지 상품 선택
function package_suboption_make()
{
	var tmp_seq 		= $("input[name='tmp_seq']").val();
	var provider_seq	= $("input[name='provider_seq']").val();

	var data_url 		= "tmp_seq="+tmp_seq;
	if(provider_seq){
		data_url += "&provider_seq="+provider_seq;
	}else{
		alert('입점사를 선택해주십시요.');
		return false;
	}
	$.ajax({
		type: "get",
		url: "../goods/select_goods_options",
		data: data_url,
		async : false,
		success: function(result){
			$("#selectGoodsOptionsDialog").html(result);
		}
	});

	openDialog("상품 선택", "selectGoodsOptionsDialog", {"width":1100,"height":window.innerHeight});
}

function select_create_package_count(bobj){
	var obj = $('bobj');
	$("input[name='reg_package_count']").val(obj.find("option:selected").val());
}


function goods_option_btn(goods_seq,obj,mode){
	var btnObj = $(obj).find("button");
	if( mode > 1 ){
		package_stock_on(goods_seq, obj);
	}else{
		scm_warehouse_on(goods_seq, obj);
	}
}

function set_stock_package(){
	$("input[name='reg_package_option_stock1[]']").each(function(idx){
		var option_stock = $(this).val();
		var unit_ea = $("input[name='package_unit_ea1[]']").eq(idx).val();
		var cstock = option_stock / unit_ea;
		var stock = cstock;
		for(var i=2;i<6;i++){
			option_stock = $("input[name='reg_package_option_stock"+i+"[]']").eq(idx).val();
			unit_ea = $("input[name='package_unit_ea"+i+"[]']").eq(idx).val();
			cstock = option_stock / unit_ea;
			if(stock > cstock){
				stock = cstock;
			}
		}
		$("input[name='stock[]']").val(parseInt(stock));
	});
}

function package_error_msg(code){

	switch(code) {
		case	'10':
			msg		= '연결되었던 실제 상품이 없어짐';
			break;

		case	'20':
			msg		= '연결되었던 실제 상품의 옵션이 없어짐';
			break;

		case	'30':
			msg		= '연결되었던 실제 상품의 옵션명이 달라짐';
			break;
	}
	msg += ' <img src="../skin/default/images/common/btn_help.gif" style="vertical-align:top;" class="hand" onclick="package_error_alert(\''+code+'\')" />';

	document.write(msg);
}

function package_error_alert(code){
	var detail			= '';
	switch(code) {
		case	'10':
			detail		= '연결되었던 실제 상품이 없어졌습니다.<br/>실제 상품을 다시 연결해 주십시오';
			break;

		case	'20':
			detail		= '연결되었던 실제 상품의 옵션이 없어졌습니다.<br/>실제 상품을 다시 연결해 주십시오.';
			break;

		case	'30':
			detail		= '연결되었던 실제 상품의 옵션명이 달라졌습니다.<br/>실제 상품을 다시 연결해 주십시오.';
			break;
	}

	$('#packageErrorDialog').html(detail);

	openDialog("연결 문제 알림", "packageErrorDialog", {"width":"370","height":"150","show" : "fade","hide" : "fade"});
}

function package_error_check(mode){
	openDialog("연결 상태 확인", "packageErrorDialog", {"width":"700","height":"500","show" : "fade"});
	var htmlTag = "<div class='content'><table class='table_basic'>";
	htmlTag += "<tr>";
	htmlTag += "	<td>";
	htmlTag += "	실제 상품이 삭제되어서";
	htmlTag += "	</td>";
	htmlTag += "	<td>";
	htmlTag += "	→";
	htmlTag += "	</td>";
	htmlTag += "	<td>";
	htmlTag += "	연결이 안되고 있는 실제 상품을 확인 할 수 있습니다.";
	htmlTag += "	</td>";
	htmlTag += "</tr>";
	htmlTag += "<tr>";
	htmlTag += "	<td>";
	htmlTag += "	실제 상품의 옵션이 새롭게 변경되어";
	htmlTag += "	</td>";
	htmlTag += "	<td>";
	htmlTag += "	→";
	htmlTag += "	</td>";
	htmlTag += "	<td>";
	htmlTag += "	연결이 안되고 있는 실제 상품을 확인 할 수 있습니다.";
	htmlTag += "	</td>";
	htmlTag += "</tr>";
	htmlTag += "<tr>";
	htmlTag += "	<td>";
	htmlTag += "	실제 상품의 옵션명이 변경되어";
	htmlTag += "	</td>";
	htmlTag += "	<td>";
	htmlTag += "	→";
	htmlTag += "	</td>";
	htmlTag += "	<td>";
	htmlTag += "	연결이 안되고 있는 실제 상품을 확인 할 수 있습니다.";
	htmlTag += "	</td>";
	htmlTag += "</tr>";
	htmlTag += "<tr>";
	htmlTag += "	<td colspan='3' style='height:30px;'>";
	htmlTag += "	</td>";
	htmlTag += "</tr>";
	htmlTag += "<tr>";
	htmlTag += "	<td colspan='3' class='center'>";
	htmlTag += "	연결에 문제가 있는 해당 옵션은 판매가 제한됩니다.";
	htmlTag += "	</td>";
	htmlTag += "</tr>";
	htmlTag += "<tr>";
	htmlTag += "	<td colspan='3' class='center'>";
	htmlTag += "	실제 상품을 다시 연결하여 문제를 해결해 주십시오.";
	htmlTag += "	</td>";
	htmlTag += "</tr>";
	htmlTag += "<tr>";
	htmlTag += "	<td colspan='3' style='height:30px;'>";
	htmlTag += "	</td>";
	htmlTag += "</tr>";
	htmlTag += "</table></div>";
	htmlTag += "<div class='footer'>";
	htmlTag += "	<button type='button' class='resp_btn size_XL v3' onclick=\"package_error_check_proc('"+mode+"');\">연결 상태 확인</button>";
	htmlTag += "</div>";
	$('#packageErrorDialog').html(htmlTag);
}

function package_error_result_option(suc){
	closeDialog("packageErrorDialog");
	openDialog("연결 상태 확인", "packageErrorDialog", {"width":"570","height":"300","show" : "fade"});
	var htmlTag = "<div class='content'><table width ='98%'>";
	htmlTag += "<tr>";
	htmlTag += "	<td>";
	htmlTag += "	연결 상태 확인 결과";
	htmlTag += "	</td>";
	htmlTag += "</tr>";
	if(suc){
		htmlTag += "<tr>";
		htmlTag += "	<td>";
		htmlTag += "	필수옵션에 ∞ 연결된 실제 상품 : 연결이 정상적 입니다.";
		htmlTag += "	</td>";
		htmlTag += "</tr>";
	}else{
		htmlTag += "<tr>";
		htmlTag += "	<td>";
		htmlTag += "	필수옵션에 ∞ 연결된 실제 상품 : 연결이 올바르지 않은 필수옵션이 존재합니다.";
		htmlTag += "	</td>";
		htmlTag += "</tr>";
		htmlTag += "<tr>";
		htmlTag += "	<td>";
		htmlTag += "	해당 필수옵션에 실제 상품을 다시 연결하세요.";
		htmlTag += "	</td>";
		htmlTag += "</tr>";
	}
	htmlTag += "<tr>";
	htmlTag += "	<td colspan='3' style='height:20px;'>";
	htmlTag += "	</td>";
	htmlTag += "</tr>";
	htmlTag += "<tr>";
	htmlTag += "	<td colspan='3' class='center'>";
	htmlTag += "	</td>";
	htmlTag += "</tr>";
	htmlTag += "</table></div>";
	htmlTag += "<div class='footer'>";
	if(suc){
		htmlTag += "	<button type='button' class='resp_btn size_XL v3' onclick=\"closeDialog('packageErrorDialog');\">닫기</button>";
	}else{
		htmlTag += "	<button type='button' class='resp_btn size_XL v3' onclick=\"closeDialog('packageErrorDialog');location.reload();\">닫기</button>";
	}
	htmlTag += "</div>";
	$('#packageErrorDialog').html(htmlTag);
}

function package_error_result_suboption(suc){
	closeDialog("packageErrorDialog");
	openDialog("연결 상태 확인", "packageErrorDialog", {"width":"570","height":"300","show" : "fade"});
	var htmlTag = "<div class='content'><table width='98%'>";
	htmlTag += "<tr>";
	htmlTag += "	<td>";
	htmlTag += "	연결 상태 확인 결과";
	htmlTag += "	</td>";
	htmlTag += "</tr>";
	if(suc){
		htmlTag += "<tr>";
		htmlTag += "	<td>";
		htmlTag += "	추가구성상품에 ∞ 연결된 실제 상품 : 연결이 정상적 입니다.";
		htmlTag += "	</td>";
		htmlTag += "</tr>";
	}else{
		htmlTag += "<tr>";
		htmlTag += "	<td>";
		htmlTag += "	추가구성상품에 ∞ 연결된 실제 상품 : 연결이 올바르지 않은 추가구성상품이 존재합니다.";
		htmlTag += "	</td>";
		htmlTag += "</tr>";
		htmlTag += "<tr>";
		htmlTag += "	<td>";
		htmlTag += "	해당 추가구성상품에 실제 상품을 다시 연결하세요.";
		htmlTag += "	</td>";
		htmlTag += "</tr>";
	}
	htmlTag += "<tr>";
	htmlTag += "	<td colspan='3' style='height:20px;'>";
	htmlTag += "	</td>";
	htmlTag += "</tr>";
	htmlTag += "</table>";
	htmlTag += "</div>";
	htmlTag += "<div class='footer'>";
	if(suc){
		htmlTag += "	<button type='button' class='resp_btn size_XL v3' onclick=\"closeDialog('packageErrorDialog');\">확인</button>";
	}else{
		htmlTag += "	<button type='button' class='resp_btn size_XL v3' onclick=\"closeDialog('packageErrorDialog');location.reload();\">확인</button>";
	}
	htmlTag += "</div>";

	$('#packageErrorDialog').html(htmlTag);
}

function package_error_check_proc(mode){
	var saveinfo	= {'goods_seq':gl_goods_seq,'mode':mode};

	var processMsg	= '<table width="98%" height="100%"><tr><td align="center" valign="middle">';
	processMsg		+= '실행 중입니다...<br/>잠시만 기다려 주십시오.'
	processMsg		+= '</td></tr></table>';
	$('#packageErrorDialog').html(processMsg);

	$.ajax({
		'type': "GET",
		'url': "../goods/package_check",
		'data': saveinfo,
		'dataType' : 'json',
		'success': function(result){
			if( result ){
				var suc = false;
			}else{
				var suc = true;
			}
			if(mode == 'option'){
				package_error_result_option(suc);
			}else if(mode == 'suboption'){
				package_error_result_suboption(suc);
			}
		}
	});
}

function package_unit_ea_display(){
	for(var i=1;i<=5;i++){
		var goods_name_obj = $("span.reg_package_goods_name"+i);
		var unit_ea_obj = goods_name_obj.closest("td").find("div.reg_package_unit_ea"+i);
		if( ! goods_name_obj.html() ){
			unit_ea_obj.hide();
		}else{
			unit_ea_obj.show();
		}
	}
}

function package_unit_ea_display_sub(){
	var goods_name_obj = $("div.tmp_package_option_name1");
	var unit_ea_obj = goods_name_obj.next();
	if( ! goods_name_obj.html() ){
		unit_ea_obj.hide();
	}else{
		unit_ea_obj.show();
	}
}


function open_criteria_condition(displayResultId,auto_condition_use_id,criteria,kind,mode,callpage){

	var title = '';
	switch(kind){
		case "bigdata":
			title = "추천 상품 ";
			break;
		case "relation":
			title = "관련 상품 상품 ";
			break;
		case "relation_seller":
			title = "판매자 인기 상품 ";
			break;
	}
	if(typeof mode == 'undefined') mode = '';

	// 순위 설정 없이 조건 설정
	if(typeof gl_operation_type != 'undefined' && gl_operation_type == 'light' && (kind == 'relation' || kind == 'relation_seller')){
		// 반응형
		$.ajax({
			type: "get",
			url: "../goods/select_auto_condition",
			data: "displayKind="+kind+"&kind=none&auto_condition_use_id="+auto_condition_use_id+"&inputGoods="+displayResultId+"&goods_seq="+gl_goods_seq+"&mode="+mode+"&condition="+criteria+"&callpage"+callpage,
			success: function(result){
				if($("div#condition_change_option").length==0) {
					$("#layout-body").append('<div id="condition_change_option"></div>');
				}
				$("div#condition_change_option").html(result);
				openDialog(title + '조건 변경', 'condition_change_option', {"width":"800","height":"600","show" : "fade","hide" : "fade"});
			}
		});
	}else{
		// 전용스킨
		openDialog(title + "조건 설정", "#displayGoodsSelectPopup", {"width":"800","height":"530","show" : "fade","hide" : "fade"});
		if(criteria.indexOf('∀') > -1 || criteria == '' || !criteria){
			set_goods_list_auto("displayGoodsSelect",displayResultId,criteria,auto_condition_use_id,kind,mode,callpage);
		}else{
			set_goods_list("displayGoodsSelect",displayResultId,'criteria',criteria,mode);
		}
	}
};

function open_goods_search(displayResultId){
	openDialog("상품 검색", "#displayGoodsSelectPopup", {"width":"99%","show" : "fade","hide" : "fade"});
	set_goods_list("displayGoodsSelect",displayResultId,'goods','');
}


function set_goods_list(displayId,inputGoods,type,criteria){
	$.ajax({
		type: "get",
		url: "../goods/select",
		data: "innerMode=2&type="+type+"&containerHeight=230&page=1&inputGoods="+inputGoods+"&displayId="+displayId+"&displayKind=relation&criteria="+encodeURIComponent(criteria)+'&prefix=relation_&relation_goods_seq='+gl_goods_seq,
		success: function(result){
			$("div#"+displayId).html(result).show();
			$("#"+displayId+"Container").show();
		}
	});
}

function set_goods_list_auto(displayId,inputGoods,criteria,auto_condition_use_id,kind,mode,callpage){
	if(typeof mode == 'undefined') mode = '';
	$.ajax({
		type: "get",
		url: "../goods/select_auto",
		data: "inputGoods="+inputGoods+"&displayKind="+kind+"&displayId="+displayId+"&criteria="+encodeURIComponent(criteria)+"&auto_condition_use_id="+auto_condition_use_id+"&provider_seq="+gl_provider_seq+"&mode="+mode+"&callpage="+callpage,
		success: function(result){
			$("div#"+displayId).html(result);
			$("#"+displayId+"Container").show();
		}
	});
}

// 외부 티켓 등록
function setCouponSerial(tcnt, couponSerialStr){
	$("#coupon_result").show();

	if (gl_goods_seq) {
		var tcnt	= parseInt(tcnt) + parseInt($(".tcnt").text());
		$("input[name='coupon_serial_upload']").val($("input[name='coupon_serial_upload']").val()+','+couponSerialStr);
		$(".tcnt").text(comma(tcnt));
	} else {
		$("input[name='coupon_serial_upload']").val(couponSerialStr);
		$(".tcnt").text(comma(tcnt));
	}

	closeDialog("coupon_serial_upload_lay");
}


function get_default_commission_rate(){
	var default_charge = num_float($("input[name='default_charge']").val());
	if($("input[name='firstBrand']").length){
		if($("input[name='firstBrand']").attr('charge')){
			default_charge = num($("input[name='firstBrand']").attr('charge'));
		}
	}

	if (gl_default_charge !== false)
		default_charge	= gl_default_charge;

	return default_charge;
}

// 상품 공통 정보 갯수 최대치 여부 확인
function getGoodCommonInfoCheck() {
	var provider_seq = $("input[name='provider_seq']").val();
	if (!provider_seq) {
		provider_seq = '1';
	}

	let check = false;

	$.ajax({
		type: 'get',
		url: '../goods/get_good_common_info_check',
		data: 'provider_seq=' + provider_seq,
		async: false,
		dataType: 'json',
		success: function(result){
			if (result) {
				check = true;
				openDialogAlert("공통정보는 최대 20개까지 저장가능합니다", 400, 170, '', '');
			}
		}
	});

	return check;
}

// 에디터 팝업 :: 2016-05-04 lwh
function view_editor_pop(contants,viewType){
	var goodsSeq	= gl_goods_seq;
	var descTxt		= "";
	var height		= 400;
	var goods_contents_top = $("#" + contants).offset().top - 100;
	var contentsHtml = "";
	if(goodsSeq) descTxt = "<span class='desc'>- 저장을 누르면 실시간으로 저장됩니다.</span>";

	var r_info_tmp	= '';
	if(contants == 'commonContents'){
		var info_seq	= $("#info_select_seq").val();
		// 공통 정보 설정: 기존 정보 불러오기 && 기존 정보 셀렉트 박스에서 공통정보를 선택하지 않고 '선택'상태인 경우 => 신규 등록으로 처리
		if(info_seq == 0 || !info_seq) {
			r_info_tmp = "create_info";
		}else{
			r_info_tmp	= $("input[name='r_info_tmp']:checked").val();
			var info_select = $("select[name='info_select_tmp'] option:selected").val();
			$("form[name='tmpContentsFrm'] input[name='info_select_seq']").val(info_select);	
			chg_common_info_list();
		}	
		height = 350;
	}

	if(r_info_tmp == "create_info"){
		contentsHtml = '';		
		// 상품 공통 정보 갯수 최대치 여부 확인
		if(contants == 'commonContents'){
			if (getGoodCommonInfoCheck()) return;
		}
	}else{		
		contentsHtml = $("#"+contants).val();		
		if(!goodsSeq) contentsHtml = '';
	}
	var newContant = '<textarea name="view_textarea" id="view_textarea" class="daumeditor" style="width:100%;height:400px;" contentHeight="' + height + '" fullMode="1">' + contentsHtml + '</textarea>';
	$(".view_contents_area").html(newContant);

	$("input[name='goodsSeq']").val(goodsSeq);
	$("input[name='contents_type']").val(contants);
	
	DaumEditorLoader.init("#view_textarea");
	//Editor.modify({"content" : contentsHtml});

	$(".view_common_info").hide();
	var title = '상품 설명';
	if(contants == 'mobile_contents') title = '상품 설명';
	else if(contants == 'commonContents') {
		title = '상품 공통 정보';
		$(".view_common_info").show();
	}

	if(viewType == 'save')	$(".contents_saveBtn").show();
	else					$(".contents_saveBtn").hide();

	openDialog(title+" "+descTxt, "view_editor_div", {"width":"1200","height":"720","draggable":false,position: ['center']});
}

// 공통정보 불러오기
function chg_common_info_list(mode){
	var provider_seq	= $("input[name='provider_seq']").val();
	var goods_kind		= 'goods';
	var info_seq		= $("#info_select_seq").val();
	var r_info_tmp		= $("input[name='r_info_tmp']:checked").val();
	if	(!provider_seq)		provider_seq	= '1';
	if	(socialcpuse_flag)	goods_kind		= 'coupon';

	$("select[name='info_select_tmp']").find('option').each(function(){
		if	($(this).attr('defaultOption') != '1')	$(this).remove();
	});
	
	if(!info_seq){
		if( parseInt(gl_common_info_goods) > 0 ) {
			info_seq = gl_common_info_goods;
		} else if( parseInt(gl_common_info_cfg) > 0 ) {
			info_seq = gl_common_info_cfg;
		}
	}

	if(typeof mode == "undefined") mode = '';

		$.ajax({
			type: 'get',
			url: 'get_goods_common_info',
			data: 'provider_seq=' + provider_seq + '&goods_kind=' + goods_kind,
			dataType: 'json',
			success: function(result){
				if	(result){
					if	(result.length > 0){
						var data		= '';
						var dataCnt		= result.length;
						var optionHTML	= '';
						var infoValue	= '';
						for (var k = 0; k < dataCnt; k++){
							data		= result[k];
							if(info_seq == data.info_seq){
								data.default_selected = 'Y';
							}
							if	(data.default_selected == 'Y'){
								infoValue 	= data.info_value;
							}

							optionHTML	= '<option value="' + data.info_seq + '">';
							optionHTML	+= data.info_name + ' &nbsp;';
							optionHTML	+= '[고유번호 : ' + data.info_seq + ']';
							optionHTML	+= '</option>';

							$("select[name='info_select_tmp']").append(optionHTML);

							if(r_info_tmp != "create_info" && info_seq == data.info_seq) {
								$("input[name='info_name']").val(data.info_name + '  '+'[고유번호 : ' + data.info_seq + ']').attr('readonly',true);
							}
						}

						$("select[name='info_select_tmp'] option[value='"+info_seq+"']").prop("selected",true);
						if(r_info_tmp != "create_info" && mode != "new"){
							setInfoSelectContent($("select[name='info_select_tmp'] option:selected"));
						}

					}
				}
			}
		});
}


function setInfoSelectContent(obj){
	var text 	= obj.text();
	$("input[name='info_name']").attr("readonly",true).val(text);
	$.get('../goods_process/get_info?seq='+obj.val(), function(response) {
		var data = eval(response)[0];
		// 팝업에 공용정보 내용 집어넣기 :: 2016-05-09 lwh
		$(".view_common_info #view_textarea").text(data.contents);
		Editor.switchEditor($("#view_textarea").data("initializedId"));
		Editor.modify({"content" : data.contents});
		obj.closest("table").find(".contents_view").html(data.contents);
		$("#commonContents").val(data.contents);
	});
}

// 에디터 내용 저장 :: 2016-05-04 lwh
function view_editor_save(){
	var goodsSeq	= gl_goods_seq;
	var editTxt		= Editor.getContent();
	var cont_type	= $("input[name='contents_type']").val();

	var info_name	= $('input[name="info_name"]').val();
	var info_select = $("select[name='info_select_tmp'] option:selected").val();

	// 공용정보 검사 :: 2016-05-09 lwh
	if (editTxt=="<p><br></p>") editTxt = "";
	if (cont_type=='commonContents' && !info_name) {
		alert('공용정보명을 입력해 주세요.');
		return false;
	}else if (cont_type=='goodscontents' || cont_type=='mobile_contents'){
		$("input[name='mobile_contents_copy'][value='N']").prop("checked",true);
		$("#mobile_contents_view").show();
		//$("#mobile_contents_desc").hide();
	}

	// 실시간 저장
	if(goodsSeq){
		submitEditorForm(document.tmpContentsFrm);
		$("#tmpContentsFrm").submit();
	}else{
		// 상품 공통 정보 갯수 최대치 여부 확인
		if(cont_type == "commonContents"){
			var r_info_tmp		= $("input[name='r_info_tmp']:checked").val();
			if (r_info_tmp == 'create_info' && getGoodCommonInfoCheck()) {
				return;
			}
		}

		$("input[name='info_name_view']").val(info_name);
		$("input[name='info_select_view']").val(info_select);

		// 현재 내용 COPY
		$("#"+cont_type+"_view").html(editTxt);
		$("#"+cont_type).val(editTxt);
		alert('임시 저장되었습니다.');
	}

	closeDialog('view_editor_div');
}

// 모바일 상품 설명 복사 :: 2016-05-11 lwh
function contents_copy(){
	var goodsSeq		= gl_goods_seq;
	var goodscontents	= $("#goodscontents").val();

	if(!goodscontents){
		alert('PC/테블릿용 상품 설명을 먼저 등록해주세요.');
		return;
	}

	// 실시간 저장
	if(goodsSeq){
		$.ajax({
			type: "post",
			url: "../goods_process/edit_mobile_copy",
			data: "goodsSeq="+goodsSeq,
			success: function(result){
				$("#mobile_contents_view").html(result);
				$("#mobile_contents").val(result);
				$(".mobile_view_desc").html('PC용 상품설명과 동일 <a href="javascript:view_editor_pop(\'mobile_contents\',\'view\');"><span class="highlight-link hand">미리보기></span></a>');
				alert('(현재 등록된 상품설명으로) 모바일용 설명에 저장되었습니다.');
			}
		});
	}else{
		$(".mobile_view_desc").html('상품 저장 시 PC용 상품설명과 동일하게 등록됩니다. <a href="javascript:view_editor_pop(\'mobile_contents\',\'view\');"><span class="highlight-link hand">미리보기></span></a>');
		$("#mobile_contents_view").html(goodscontents);
		$("#mobile_contents").val(goodscontents);
		alert('(현재 등록된 상품설명으로) 모바일용 설명에 임시 저장되었습니다.');
	}

	$("input[name='mobile_contents_copy'][value='Y']").prop("checked",true);
	$("#mobile_contents_view").hide();
	//$("#mobile_contents_desc").show();
}

// mapView 기능 추가 lwh 2014-03-31
function show_mapView(){
	if (socialcpuse_flag)
		$(".mapView").attr('disabled',false);
}

function hide_mapView(){
	if (socialcpuse_flag)
		$(".mapView").attr('disabled',true);
}


function hscodeSet() {


	var hscodeValues	= $('form[name=hscode_form]:not(.disabled)').serialize();
	$.post('../goods_process/hscode_save',hscodeValues, function() {

	});
}



function payMethodChange(type) {

	if (type == 'goods') {
		var nowSetId	= '#pay_method_goods';
		var preSetId	= '#pay_method_basic';
		$('#pay_method_goods > span').addClass('red');
		$('input[name="possible_pay[]"].able').attr('disabled', false);
	} else {
		var nowSetId	= '#pay_method_basic';
		var preSetId	= '#pay_method_goods';
		$('#pay_method_goods > span').removeClass('red');
		$('input[name="possible_pay[]"].able').attr('disabled', true);
	}


	$(preSetId + ',' + preSetId + '_set .its-td').addClass('gray');
	$(nowSetId + ',' + nowSetId + '_set .its-td').removeClass('gray');

}

function displayTermsChange(mode) {
	if (mode == 'AUTO') {
		$('.display-auto').removeClass('gray');
		$('.display-form').attr('disabled', false)
	} else {
		$('.display-auto').addClass('gray');
		$('.display-form').attr('disabled', true)
	}
}

function displayTermsSet() {

	var displayTerms		= $('input[name="display_terms"]').val();
	var displayTermsBegin	= $('#display_terms_begin').val();
	var displayTermsEnd		= $('#display_terms_end').val();
	var termsBeginTime		= displayTermsBegin + ' 00:00:00'
	var termsEndTime		= displayTermsEnd + ' 23:59:59';

	var todayObj			= new Date();
	var beginObj			= new Date(termsBeginTime);
	var endObj				= new Date(termsEndTime);


	//$('#display_terms_auto').hide();
	//$('#display_terms_menual').hide();

	if (displayTerms == 'AUTO') {

		if (displayTermsBegin.length < 10 || beginObj.getTime() > endObj.getTime()) {
			alert('시작일을 정확히 입력하세요.');
			return;
		}

		if (displayTermsEnd.length < 10 || endObj.getTime() < todayObj.getTime()) {
			alert('종료일을 정확히 입력하세요.');
			return;
		}

		//$('#display_terms_auto').show();
	} else {
		//$('#display_terms_menual').show();
	}


	var displayTermsType	= $('input:checked[name="display_terms_type_tmp"]:checked').val();
	var displayTermsText	= $.trim($('#display_terms_text').val());
	var displayTermsColor	= $('#display_terms_color').val();
	var displayTermsBefore	= $('input:radio[name="display_terms_before"]:checked').val();
	var displayTermsAfter	= $('input:radio[name="display_terms_after"]:checked').val();

	/*
	$('input[name="display_terms"]').val(displayTerms);
	$('input[name="display_terms_begin"]').val(displayTermsBegin);
	$('input[name="display_terms_end"]').val(displayTermsEnd);
	$('input[name="display_terms_type"]').val(displayTermsType);
	$('input[name="display_terms_text"]').val(displayTermsText);
	$('input[name="display_terms_color"]').val(displayTermsColor);
	$('input[name="display_terms_before"]').val(displayTermsBefore);
	$('input[name="display_terms_after"]').val(displayTermsAfter);
	*/

	var beforeStatus	= (displayTermsBefore == 'DISPLAY') ? '노출' : '미노출';
	var afterStatus		= (displayTermsAfter == 'DISPLAY') ? '노출' : '미노출';
	var termsTypeText	= (displayTermsType == 'LAYAWAY') ? '예약상품' : '상품';

	/*
	$('.display_before').hide();
	$('.display_ing').hide();
	$('.display_terms_text').html('');

	if (beginObj.getTime() < todayObj.getTime())
		$('.display_ing').show();
	else
		$('.display_before').show();

	$('.display_terms_begin_before').html($('#display_terms_begin_before').html());
	$('.display_terms_end_after').html($('#display_terms_end_after').html() + '부터는');
	$('.display_terms_before').html(beforeStatus + '→');
	$('.display_terms_begin').html(displayTermsBegin + ' ~ ');
	$('.display_terms_end').html(displayTermsEnd);
	$('.display_terms_type').html(termsTypeText);
	$('.display_terms_after').html(afterStatus);
	*/

	if (displayTerms == 'AUTO' && displayTermsType == 'LAYAWAY') {
		var shippingText	= $('#possible_shipping_text').val();
		var shippingDate	= $('#possible_shipping_date').val();
		var shippingObj		= new Date(shippingDate);


		if (shippingDate.length < 10 || endObj.getTime() > shippingObj.getTime()) {
			alert('예약 발송일을 정확히 입력하세요.');
			return;
		}

		//shippingInfo		= '<span style="font-weight:bold;">예약 발송일 안내 : <span style="color:blue">' + shippingDate + '</span> ' + shippingText + '</span>';

		$('input[name="possible_shipping_date"]').val(shippingDate);
		$('input[name="possible_shipping_text"]').val(shippingText);
		//$('.shipping_info').html(shippingInfo);
	} else {
		$('input[name="possible_shipping_text"]').val('');
		$('input[name="possible_shipping_date"]').val('');
		//$('.shipping_info').html('');
	}

	/*
	if (displayTermsText)
		var showText	= '(<span style="color:' + displayTermsColor + '">' + displayTermsText + '</span>)으로 노출';
	else
		var showText	= '으로 노출';

	$('.display_terms_text').html(showText);
	*/

	// 배송관련 연결 제어 - 예약상품 선택시 배송그룹 삭제 :: 2017-01-06 lwh
	if(displayTerms == 'AUTO' && displayTermsType == 'LAYAWAY'){
		$("#shipping_group_seq").val('');
		$("#trust_shipping").val('N');
		$(".shipping_group_tb").find("tbody").html('');
		$(".shipping_group_tb").hide();
		$(".reservation").show();
	}else{
		$(".reservation").hide();
	}

}


function makeGoodsCode() {
	var goodscetegory		= '';//대표카테고리
	var goodsbrand			= '';//대표브랜드
	var goodslocation		= '';//대표지역

	var selectectid			= '';
	var selectectseq		= '';
	var selectectcode		= '';
	var goodsaddinfoseqar	= new Array();//추가정보처리
	var goodsaddinfocodear	= new Array();//추가정보처리

	goodscetegory			= $("input:radio[name='firstCategory']:checked").val();
	goodsbrand				= $("input:radio[name='firstBrand']:checked").val();
	goodslocation			= $("input:radio[name='firstLocation']:checked").val();

	$("select[name='selectEtcTitle[]']").each(function(){

		selectectid			= $(this).find("option:selected").val();
		if (selectectid) {

			var idx			= $(this).index($(this));

			if (selectectid.substr(0,12) == 'goodsaddinfo') {
				selectectcode	= $(this).parent().parent().find("."+selectectid+" option:selected").val();
				selectectseq	= $(this).parent().parent().find("."+selectectid).attr("label_codeform_seq");
				goodsaddinfoseqar.push(selectectid.replace("goodsaddinfo_",""));
				goodsaddinfocodear.push(selectectcode);
			}
		}

	});

	var goodsaddinfoseq		= goodsaddinfoseqar.join(',');
	var goodsaddinfocode	= goodsaddinfocodear.join(',');

	$.ajax({
		type: "post",
		url: "../goods_process/tmpgoodscode",
		data: "no=" + gl_goods_seq + "&category_goods_code="+goodscetegory+"&brand_goods_code="+goodsbrand+"&location_goods_code="+goodslocation+"&addtion_goods_seq="+goodsaddinfoseq+"&addtion_goods_code="+goodsaddinfocode,
		success: function(result){
			if(result){
				$("#goodsCode").val(result);
				$(".goodsCode").html(result);
			}else{
				alert('자동생성 정보가 부족합니다.');
			}
			$("#makeGoodsCodLay").dialog('close');
		}
	});
}

function setSubOptionProcess() {

	var individual_refund			= ($('#individual_refund_set_1').is(':checked')) ? 1 : 0;
	var individual_refund_inherit	= ($('#individual_refund_inherit_set').is(':checked')) ? 1: 0;
	var individual_export			= ($('#individual_export_set_1').is(':checked')) ? 1 : 0;
	var individual_return			= ($('#individual_return_set_1').is(':checked')) ? 1 : 0;


	$('#individual_refund_1').hide();
	$('#individual_refund_inherit_show').hide();
	$('#individual_refund_0').hide();
	$('#individual_export_1').hide();
	$('#individual_export_0').hide();
	$('#individual_return_1').hide();
	$('#individual_return_0').hide();

	$('#individual_refund_' + individual_refund).show();
	$('#individual_export_' + individual_export).show();
	$('#individual_return_' + individual_return).show();

	if (individual_refund_inherit == 1)
		$('#individual_refund_inherit_show').show();


	$('#individual_refund').val(individual_refund);
	$('#individual_refund_inherit').val(individual_refund_inherit);
	$('#individual_export').val(individual_export);
	$('#individual_return').val(individual_return);

	closeDialog('subOptionProcessSet');
}

function changeDiscountSet(nowIndex,firstdel) {

	var totalSetCnt	= $('input[name="discountUnderQty[]"]').length;

	for (i = nowIndex; i < totalSetCnt; i++) {
		lastUnder	= parseInt($('input[name="discountUnderQty[]"]').eq(i - 1).val(),10);
		nowUnder	= parseInt($('input[name="discountUnderQty[]"]').eq(i).val(),10);

		$('input[name="discountOverQty[]"]').eq(i).val(lastUnder);

		if(totalSetCnt == 1 && firstdel == true){
			$('input[name="discountUnderQty[]"]').eq(i).val('');
		}else{
			if (nowUnder <= lastUnder) {
				$('input[name="discountUnderQty[]"]').eq(i).val(lastUnder + 1);
			}
		}
	}

	$('input[name="discountUnderQty[]"]:last').trigger('change');
}

//구매수량할인 - 마지막 Row ( 00개 이상~) 보이기/숨기기
function checkDiscountSet(firstdel)
{
	var totalSetCnt	= $("#multiDiscountTable tbody tr").length;

	// 삭제 모드이고, 1개 Row만 남았을 때. 00개 이상 input value 초기화.
	if(totalSetCnt == 1 && firstdel == true){
		$('#multiDiscountTable').find("input[name='discountMaxOverQty']").val('');
	}
	if	($('#multiDiscountTable').find("input[name='discountMaxOverQty']").val() > 0){
		totalSetCnt++;
	}
	// 첫행만 남은 상태에서 - 누르면 2로 초기화
	if( firstdel == true && $('.max_qty_set').css("display") == "none") {
		$('input[name="discountOverQty[]"]').eq(0).val("2");
	}
	if(firstdel == true){
		$('.max_qty_set').hide();
		$("#multiDiscountTable tbody tr td span.discount_under_qty").hide();
	}else{
		$("#multiDiscountTable tbody tr td span.discount_under_qty").show();
		$('.max_qty_set').show();
	}
}

//message
function helperMessage(mode, addText) {
	var title		= '';
	var message		= '';
	$('#helper_message').html('');

	switch (mode) {

		case	'keyword' :
			title	= '검색어 - 자동';
			message	= '상품명, 상품번호, 상품코드로 검색어를 자동 생성합니다.';
			break;

		case	'tax' :
			title	= '안내';
			message	=  '<div class="pd5"><table class="info-table-style" style="width:100%"><colgroup><col width="10%" /><col width="40%" /><col width="30%" /></colgroup>';
			message	+= '<tr><th class="its-th-align center"></th><th class="its-th-align center">과세</th><th class="its-th-align center">비과세</th></tr>';
			message	+= '<tr><th class="its-th-align center">설명</th><td class="its-td">일반적으로 부가세가 붙는 상품</td><td class="its-td">부가세가 없는 상품</td></tr>';
			message	+= '<tr><th class="its-th-align center">상품</th><td class="its-td">의류,화장품 등 대부분의 공산품</td><td class="its-td">농축산품과 생필품</td></tr>';
			message	+= '<tr><th class="its-th-align center">매출증빙</th><td class="its-td">설정한 부가세에 따라 매출 증빙 자료에  반영, <span class="red">단 한국 PG 이용시</span></td><td class="its-td">부가세가 0</td></tr>';
			message	+= '</table><p class="pdt5">※ 해외 PG 이용시에는 과세와 비과세 여부에 상관없이 부가세는 0으로 전달됩니다.<br />(해외 PG 거래는 수출로 간주되어 영세율을 적용 받음)</p></div>';
			break;

		case	'cancel' :
			title	= '청약철회불가';
			message	= '청약철회불가 상품은 결제를 하지 않은 주문접수 상태에서 주문 무효를 할 수 있습니다.<br/><br/>';
			message	+= '그러나 결제확인 이후로는 구매 대상자가 MY페이지에서 결제취소 또는 반품/교환 신청를 할 수 없습니다.';
			break;

		case	'adult' :
			title	= '성인상품';
			message	= '성인상품은 성인인증수단(휴대폰 또는 아이핀)으로 인증 후 상품을 보고 구매할 수 있습니다.<br/>';
			message	+= '성인인증수단은 <a href="../setting/member" target ="_set"><span style="color:blue">본인인증</span></a>에서 설정 가능힙니다.<br/><br/>'
			message	+= '관리자 로그인 상태에서 성인인증 없이 상품페이지를 볼 수 있습니다.';
			break;

		case	'oversea' :
			title	= '해외 구매 대행';
			message	= '해외에서 구매 대행한 상품을 대한민국으로 배송 시 대한민국 관세청 통관신고를 위해';
			message	+= ' 주문 시 구매 대상자(구매대행요청자)의 개인통관고유부호를 수집합니다.';
			break;

		case	'SACO' :
			title	= '수수료 정산방식';
			message	= '[수수료율 방식] 정산금액 = 판매가 - 수수료<br/><br/>';
			message	+= '※ 수수료(소수점 첫째자리 반올림) = 판매가 × 수수료율(%)';
			break;

		case	'SUPPLY' :
			title	= '공급가 정산방식';
			message	= '[공급가율 방식] 정산금액 = 정가 × 공급율(%)<br/>';
			message	+= '[공급가액 방식] 정산금액 = 공급가<br/><br/>';
			message	+= '※ 정산금액(소수점 첫째자리 반올림)';
			break;

		case	'goodsCode' :
			title	= '상품코드';
			message	= '등록된 상품코드는 바코드로 사용할 수 있습니다.<br/>';
			message	+= '- 지원 바코드 형식 : Code39, Code128-A, Code128-B, Code128-C, ISBN<br/>';
			message	+= '- 지원 프린트 용지 : 롤지, [폼텍3104]A4:3x9, [폼텍3102]A4:4x10, [폼텍3112]A4:3x4<br/>';
			message	+= '<br/>';
			message	+= '<span class="highlight-link hand" onclick="openDialog(\'바코드 정보 입력방법\', \'barcode_regist_info\', {\'width\':\'1000\',\'height\':\'630\',\'show\' : \'fade\',\'hide\' : \'fade\'});">바코드 입력방법 안내</span>';
			break;

		case	'stock' :
			title	= '재고';
			message	= '재고 = 정상 재고 + 불량 재고';
			break;

		case	'safeStock' :
			title	= '안전재고';
			message	= '재고 : ' + addText + ' → 해당 상품의 안전재고입니다.';
			break;

		case	'safeStockForScm' :
			title	= '안전재고';
			message	= '재고 : ' + addText + ' → 해당 상품의 안전재고입니다.<br/>';
			message	+= '해당 상품의 재고수량이 안전재고 이하로 떨어질 경우 자동 발주가 생성됩니다.';
			break;

		case	'price' :
			title	= '정가, 판매가';
			message	= '정가는 소비자가격이며,<br/>';
			message	+= '판매가는 할인가격입니다.';
			break;

		case	'optionInfomation' :
			title	= '설명';
			message	= '해당 필수옵션에 대한 안내 문구입니다.<br/>';
			message	+= '옵션 셜명이 있는 경우 소비자에게 해당 옵션을 선택하면, 소비자에게 옵션설명이 보여지게 됩니다.';
			break;

		case	'solubleStock' :
			title	= '가용재고';
			message	= '가용재고 = 재고 - 출고예약량 - 불량재고<br/>';
			message	+= '출고예약량 = 결제확인 수량 + 상품준비 수량 + 출고준비 수량<br/>';
			message	+= '※ 가용재고는 판매 가능한 수량입니다.     <a href="/admin/setting/order" target="_blank" style="color:#FF8224;">설정></a><br/>';
			message	+= '※ 출고예약량은 배송이 예정되어 있는 수량입니다.';
			break;
		case	'solubleStock_seller' : //입점사페이지쪽 가용설명 (설정 뺌)
			title	= '가용재고';
			message	= '가용재고 = 재고 - 출고예약량 - 불량재고<br/>';
			message	+= '출고예약량 = 결제확인 수량 + 상품준비 수량 + 출고준비 수량<br/>';
			message	+= '※ 가용재고는 판매 가능한 수량입니다.<br/>';
			message	+= '※ 출고예약량은 배송이 예정되어 있는 수량입니다.';
			break;
		case	'replaceCode' :
			title	= '치환 코드';
			message	= '<p>※ 아래 치환코드를 조합하여 입력 가능</p><br/>';
			message	+= '<p>① 상품명 : <span class="red">&#123;product_name&#125;</span></p>';
			message	+= '<p>② 대표카테고리 : <span class="red">&#123;product_category&#125;</span></p>';
			message	+= '<p>③ 대표브랜드 : <span class="red">&#123;product_brand&#125;</span></p>';
			message	+= '<p>④ 상품검색태그 : <span class="red">&#123;product_tag&#125;</span></p>';
			break;
		
		case	'marketing' :
			title	= '필수 항목 안내';
			message	= '<p>입점 마케팅 상품 데이터 전달을 위한 필수 입력 항목입니다. 필수 항목 미 설정 시, 상품이 전달되지 않으니 주의하세요.</p><br/>';
			message	+= '<table class="table_basic">';
			message	+= '<colgroup><col width="30%" /></colgroup><tbody>';
			message	+= '<tr><th>구분</th><th>네이버</th><th>다음</th><th>페이스북</th><th>구글</th></tr>';
			message	+= '<tr><th>상품명</th><td class="center"><span style="color:orange; font-weight:bold;">필수</span></td><td class="center"><span style="color:orange; font-weight:bold;">필수</span></td><td class="center"><span style="color:orange; font-weight:bold;">필수</span></td><td class="center"><span style="color:orange; font-weight:bold;">필수</span></td></tr>';
			message	+= '<tr><th>대표 카테고리</th><td class="center"><span style="color:orange; font-weight:bold;">필수</span></td><td class="center"><span style="color:orange; font-weight:bold;">필수</span></td><td class="center">선택</td><td class="center">선택</td></tr>';
			message	+= '<tr><th>검색어</th><td class="center">선택</td><td class="center">선택</td><td class="center">해당 없음</td><td class="center">해당 없음</td></tr>';
			message	+= '<tr><th>검색어</th><td class="center">선택</td><td class="center">선택</td><td class="center">해당 없음</td><td class="center">해당 없음</td></tr>';
			message	+= '<tr><th>간략설명</th><td class="center">해당 없음</td><td class="center">해당 없음</td><td class="center"><span style="color:orange; font-weight:bold;">필수</span></td><td class="center"><span style="color:orange; font-weight:bold;">필수</span></td></tr>';
			message	+= '<tr><th>재고</th><td class="center">해당 없음</td><td class="center">해당 없음</td><td class="center"><span style="color:orange; font-weight:bold;">필수</span></td><td class="center"><span style="color:orange; font-weight:bold;">필수</span></td></tr>';
			message	+= '<tr><th>판매방식 구분</th><td class="center"><span style="color:orange; font-weight:bold;">해당상품필수</span></td><td class="center">해당 없음</td><td class="center">해당 없음</td><td class="center">해당 없음</td></tr>';
			message	+= '<tr><th>상품 상태</th><td class="center"><span style="color:orange; font-weight:bold;">필수</span></td><td class="center"><span style="color:orange; font-weight:bold;">필수</span></td><td class="center"><span style="color:orange; font-weight:bold;">필수</span></td><td class="center"><span style="color:orange; font-weight:bold;">필수</span></td></tr>';
			message	+= '<tr><th>별도 설치비 유무</th><td class="center"><span style="color:orange; font-weight:bold;">해당상품필수</span></td><td class="center">선택</td><td class="center">해당 없음</td><td class="center">해당 없음</td></tr>';
			message	+= '<tr><th>병행수입 및 주문 제작</th><td class="center"><span style="color:orange; font-weight:bold;">해당상품필수</span></td><td class="center">해당 없음</td><td class="center">해당 없음</td><td class="center">해당 없음</td></tr>';
			message	+= '<tr><th>이벤트</th><td class="center">선택</td><td class="center">선택</td><td class="center">해당 없음</td><td class="center">해당 없음</td></tr>';
			message	+= '<tr><th>노출 배송비</th><td class="center"><span style="color:orange; font-weight:bold;">필수</span></td><td class="center"><span style="color:orange; font-weight:bold;">필수</span></td><td class="center">선택</td><td class="center"><span style="color:orange; font-weight:bold;">필수</span></td></tr>';
			break;
			
		default :
			return;
	}

	$('#helperMessage').html(message);
	openDialog(title, "helperMessageShow", {"width":"600","show" : "fade","hide" : "fade"});
}


function helperMessageLayer(mode) {
	switch(mode) {
		case	'getOptions' :
			openDialog('옵션정보 가져오기', "getOptionsMessage",{"width":"400"});
			break;

		case	'optionPrice' :
			openDialog('옵션가격', "optionPriceMessage",{"width":"400"});
			break;

		case	'specialOption' :
			openDialog('특수옵션', "specialOptionMessage",{"width":"800"});
			break;

		case	'viewOption' :
			openDialog('옵션 설명', "viewOptInfomation", {"width":"600","show" : "fade","hide" : "fade"});
			break;

		default :
			return;
	}
}


function setOptionStockSetText() {

	/*
	if ($('input[name="runout_type"]:checked').val() == 'goods')
		var nowRunoutPolicy	= $('input[name="runout"]:checked').val();
	else
		var nowRunoutPolicy	= gl_runout;

	switch(nowRunoutPolicy) {
		case	'stock' :
			var optionStockSetText	= '재고가 있으면 노출';
			break;

		case	'ableStock' :
			var optionStockSetText	= '가용 재고가 있으면 노출';
			break;

		case	'unlimited' :
			var optionStockSetText	= '재고와 상관 없이 노출';
			break;
	}
	$('.optionStockSetText').html(optionStockSetText);
	*/
	var optionStockSetText = '옵션 노출';
	return optionStockSetText;
}

function confirm_first_goods(first_date,currency,hangul,nation,msg,func)
{
	var params = {'yesMsg':'예','noMsg':'아니오'};
	var ph = 180;
	if( !first_date ){
		params = {'yesMsg':'저장','noMsg':'취소'};
		msg = '<div align="left">';
		msg	+= '현재 기본통화는 '+currency+'('+nation+', '+hangul+') 입니다.<br><br>';
		msg	+= '최초 상품 등록 이후에는 기본통화 변경이 불가능합니다.<br>';
		msg	+= '기본통화를 바꾸려면 <a href="../setting/multi"><span class="highlight-link">상점정보</span></a> 에서 하실 수 있습니다.<br>';
		msg	+= '현재 기본통화로 상품을 등록하려면 “저장’ 을 취소하려면 ‘취소’를<br>';
		msg	+= '클릭해주세요</div>';
		ph = 250;
	}

	if(msg){
		openDialogConfirm(msg,400,ph,function(){
			eval( func );
		},function(){
		},params);
	}else{
			eval( func );
	}
}

function copy_goods(goods_seq, mode)
{
	$.ajax({
		type: "get",
		url: "../goods_process/goods_copy",
		data: "goods_seq="+goods_seq,
		success: function(result){
			switch(result){
				case	'diskfull' :
					customOptions				= [];
					customOptions['btn_title']	= '용량추가';
					customOptions['btn_class']	= 'btn large';
					customOptions['btn_action']	= "window.open('http://firstmall.kr/myshop','_blank')";
					openDialogAlert("용량이 초과되어 상품을 등록 또는 수정할 수 없습니다.<br/>용량 추가를 하시길 바랍니다.",400,175,'',customOptions);
				break;
				default :
					if( mode == 'regist' ){
						alert("등록 되었습니다.");
						if (gl_package_yn == 'y')
							location.href = "../goods/package_catalog";
						else if (socialcpuse_flag)
							location.href = "../goods/social_catalog";
						else if(typeof gift != "undefined" && gift === true) 
							location.href = "../goods/gift_catalog";
						else
							location.href = "../goods";
					}else{
						location.reload();
					}
				break;
			}
		}
	});
}

function groupsale_choice(sale_seq) {
	//var goods_seq = $('[name="goodsSeq"]').val();
	//var category_code = $('[name="firstCategory"]:checked').val();

	$('[name="sale_seq"]').val(sale_seq);
	/*
	$.post('get_promotion_grade_ajax', { sale_seq: sale_seq, goods_seq: goods_seq, category_code: category_code }, function(data){
		// reset
		var trEm			= $('tr.row-groupsale').eq(0), rowspan = 1;
		var discount = data.discount||[], save = data.save||[];

		// row-remove
		$('.info-table-style .row-groupsale').not(':first').remove();

		// no-data
		if(!data.length) {
			$('td:eq(0), td:eq(1)', trEm).html('');

			// set-rowspan
			rowspan = $('.info-table-style .row-groupsale').length;
			$('th:eq(0), th:eq(1)', trEm).attr('rowspan', rowspan);
		}

		// only 할인
		$.each(discount, function(k, v){
			var toHTML1	= '', toHTML2 = '';

			toHTML1 = '<div style="margin:5px 0;">'+v.sale_use + ' : ' + v.group_name+'</div>';
			toHTML2 = ''
			+'<table width="100%" cellpadding="0" cellspacing="0"><colgroup><col width="50%" /><col width="50%" /></colgroup><tr>'
			+'<td'+(!Number(v.sale_price)?' style="color:#747474;"':'')+'>필수옵션 상품 '+(Number(v.sale_price) ? v.sale_price+v.sale_price_type:'추가 할인 없음')+'</td>'
			+'<td'+(!Number(v.sale_option_price)?' style="color:#747474;"':'')+'>추가옵션 상품 '+(Number(v.sale_option_price) ? v.sale_option_price+v.sale_option_price_type:'추가 할인 없음')+'</td>'
			+'</tr></table>';

			if(k==0) {
				$('td:eq(0)', trEm).html(toHTML1);
				$('td:eq(1)', trEm).html(toHTML2);
			} else {
				$('tr.row-groupsale').last().after('<tr class="row-groupsale"><td class="its-td">'+toHTML1+'</td><td class="its-td">'+toHTML2+'</td></tr>');
			}
		});

		// only 적립
		$.each(save, function(k, v){
			var toHTML1	= '', toHTML2 = '';

			toHTML1 = '<div style="margin:5px 0;">'+v.point_use + ' : ' + v.group_name+'</div>';
			toHTML2 = ''
			+'<table width="100%" cellpadding="0" cellspacing="0"><colgroup><col width="50%" /><col width="50%" /></colgroup><tr>'
			+'<td'+(!Number(v.reserve_price)?' style="color:#747474;"':'')+'>마일리지 '+(Number(v.reserve_price) ? Number(v.reserve_price)+v.reserve_price_type+' <span style="font-size:11px; color:#777777;">('+gl_reservetitle+')</span>' : '추가 적립 없음')+'</td>'
			+'<td'+(!Number(v.point_price)?' style="color:#747474;"':'')+'>포인트 '+(Number(v.point_price) ? Number(v.point_price)+v.point_price_type+' <span style="font-size:11px; color:#777777;">('+gl_pointtitle+')</span>' : '추가 적립 없음')+'</td>'
			+'</tr></table>';

			$('tr.row-groupsale').last().after('<tr class="row-groupsale"><td class="its-td">'+toHTML1+'</td><td class="its-td">'+toHTML2+'</td></tr>');
		});

		// set-rowspan
		rowspan = $('.info-table-style .row-groupsale').length;
		$('th:eq(0), th:eq(1)', trEm).attr('rowspan', rowspan);
	}, 'json');
	*/

}

// "프로모션-회원 등급" 가져오기
function gradeDiscount() {
	!$('#groupScalePopup').length && $('body').append('<div id="groupScalePopup" style="display:none;"></div>');
	var sale_seq = $('[name="sale_seq"]').val();

	$.ajax({
		type: "GET",
		async:  false,
		url: "../popup/groupsale_choice?sale_seq="+sale_seq,
		dataType: "html",
		success: function(data) {
			$('#groupScalePopup').html(data);
		}
	});

	openDialog("회원 등급별 할인", "groupScalePopup", {"width":450});
}

// "프로모션-유입경로" 가져오기
function refererDiscountRows(mode) {
	var trEm = $('.row-referer').first(), provider_seq = $('[name="provider_seq"]').val();

	if(typeof mode == "undefined") mode = "";

	$.post("get_promotion_referer", { provider_seq: provider_seq, goods_seq: gl_goods_seq }, function(data){
		$('tr.row-referer').remove();	// row-remove

		var targetReferferSelector = $('tr.row-event');
		if($('tr.row-referer').length > 0){
			targetReferferSelector = $('tr.row-referer');
		}
		$.each(data, function(k, v){

			var toHTML1 = '', toHTML2 = '', toHTML3 = '';

			switch(mode) {
				case 'view':
					toHTML1 = v.referersale_name;
					toHTML2 = ''+'<span class="desc">(http://'+v.referersale_url+' '+(v.url_type == 'like' ? '포함 시' : '일치 시')+')</span>'
							+'<span class="desc">'+v.issue_startdate+' ~ '+v.issue_enddate+'</span>';
					break;
				default:
					toHTML1 = ''+'<a href="../referer/referersale?no='+v.referersale_seq+'" target="_blank" style="text-decoration:underline !important" class="black">'+v.referersale_name + '</a>';
					toHTML2 = ''+v.issue_startdate+' ~ '+v.issue_enddate;
					toHTML3 = ''+'http://'+v.referersale_url+' '+(v.url_type == 'like' ? '포함 시' : '일치 시');
			}

			switch(v.sale_type) {
				case 'won':
					toHTML3 = ''
					+'판매가격의 '+v.won_goods_sale;
					break;
				default:
					toHTML3 = ''
					+'판매가 '+v.percent_goods_sale+'% 추가 할인, 최대 '+v.max_percent_goods_sale;
			}
			targetReferferSelector.last().after('<tr class="row-referer"><th>할인 유입경로</th><td>'+toHTML1+'</td><td class="center">'+toHTML2+'</td><td>'+toHTML3+'</td></tr>');
			/*
			if(k==0) {
				$('td:eq(0)', trEm).attr('colspan',1);
				$('td:eq(0)', trEm).html(toHTML1).removeClass("center");
				$(trEm).append('<td class="center">'+toHTML2+'</td>');
				$(trEm).append('<td>'+toHTML3+'</td>');
			} else {
				
			}
			*/
		});

		// no-data
		if($('tr.row-referer').length == 0){
			var text = '';
			switch(mode) {
				case 'view':
					text = "혜택 없음(본사와 할인분담금을 협의하여 진행해 주십시오)";
					break;
				default:
					text = "혜택 없음";
			}
			$('tr.row-event').last().after('<tr class="row-referer"><th>할인 유입경로</th><td class="center" colspan="3">'+text+'</td></tr>');
		}
		
	}, "json");
}

// "프로모션-사은품" 가져오기
function giftRows(mode){
	var trEm = $('.row-gift').first(), category_codes = [], provider_seq = $('[name="provider_seq"]').val();
	var shipping_group_seq	= $('input#shipping_group_seq').val();

	if(typeof mode == "undefined") mode = "";
	$('[name="firstCategory"]').length && $('[name="firstCategory"]').each(function(){
		category_codes.push($(this).val());
	});
	var params = { provider_seq: provider_seq, goods_seq: gl_goods_seq, category_codes: category_codes, shipping_group_seq: shipping_group_seq };

	$.post("get_promotion_gift", params, function(data){
		
		$('.row-gift').remove();	// row-remove

		var targetGiftSelector = $('tr.row-referer');
		if($('tr.row-gift').length > 0){
			targetGiftSelector = $('tr.row-gift');
		}

		$.each(data, function(pk, pv){
			var toHTML1 = '', toHTML1_benefit = '', toHTML2 = '', toHTML3 = '';

			$.each(pv, function(ck, cv){
				if(ck == 'common') {
					switch(mode) {
						case 'view':
							toHTML1 = cv.title;
							toHTML2 = ''
								+cv.start_date+' ~ '+cv.end_date;
							break;
						default:
							toHTML1 = ''
							+'<a href="/admin/event/gift_regist?event_seq='+cv.gift_seq+'" target="_blank" style="text-decoration:underline !important" class="black">'+cv.title + '</a>';
							toHTML2 = ''
							+ cv.start_date+' ~ '+cv.end_date;
					}
					return false;
				}
			});

			toHTML2 += toHTML1_benefit;
			toHTML3 += '<div style="margin:5px 0 0 0; letter-spacing:-1px;">구매자가 주문 시 사은품 선택</div>';

			targetGiftSelector.last().after('<tr class="row-gift"><th>사은품 이벤트</th><td>'+toHTML1+'</td><td class="center">'+toHTML2+'</td><td>'+toHTML3+'</td></tr>');

		});

		// no-data
		if($('tr.row-gift').length == 0){
			var text = '';
			switch(mode) {
				case 'view':
					text = "혜택 없음(본사와 할인분담금을 협의하여 진행해 주십시오)";
					break;
				default:
					text = "혜택 없음";
			}
			$('tr.row-referer').last().after('<tr class="row-gift"><th>사은품 이벤트</th><td class="center" colspan="3">'+text+'</td></tr>');
		}

	}, "json");
}

// 미승인 처리 관련 안내 팝업
function open_provider_status(){
	openDialog('상품 수정 시 자동 미승인 처리 기준', 'unable_provider_status', {'width':'600','height':'260'});
}


// 티켓 그룹 찾기
function coupon_grp_find(){
	var group_seq		= ($("#social_goods_group").val())?$("#social_goods_group").val():'0';
	addFormDialog('./social_goods_group?type=write&sel_group_seq='+group_seq, '700', '475', '티켓상품그룹 찾기 ','false', 'resp_btn v3 size_XL');
}

// 옵션설명 팝업 노출
function viewOptionInfomation(obj){
	var html	= '';
	var trObj	= '';
	var headObj	= $('div#optionInfomationLay').find('thead');
	var bodyObj	= $('div#optionInfomationLay').find('tbody');

	// 초기화
	headObj.find('tr th').each(function(){
		if	(!$(this).hasClass('infomation-th'))	$(this).remove();
	});
	bodyObj.find('tr').remove();

	// 옵션 타이틀 생성
	$('div#optionLayer').find("input[name='optionTitle[]']").each(function(){
		html	= '<th style="width:80px;">' + $(this).val() + '</th>';
		$(html).insertBefore(headObj.find('th.infomation-th'));
	});
	// 옵션 목록 추가
	$('div#optionLayer').find('tr.optionTr').each(function(){
		trObj	= $('<tr></tr>').appendTo(bodyObj);
		$(this).find("input[name='optionNames[]']").each(function(){
			trObj.append('<td class="center">' + $(this).val() + '</td>');
		});
		if	($(this).find('textarea.optionInfomation').val()){
			trObj.append('<td class="left pdl5">' + $(this).find('textarea.optionInfomation').val() + '</td>');
		}else{
			trObj.append('<td class="left pdl5"></td>');
		}
	});

	openDialog('옵션 설명', 'optionInfomationLay', {'width':600,'height':450});
}

function del_subInfo(obj){
	var bobj = $(obj);
	if( bobj.closest("tbody").find("tr").length > 1){
		bobj.closest("tr").remove();
	}
}

function add_subInfo(){
	
	var html = "";
	html += '<tr>';
	html += '	<td class="center"><img src="../skin/default/images/common/icon_move.png"></td>';
	html += '	<td class="center"><input type="text" name="subInfoTitle[]" value="" size="40" class="resp_text"/></td>';
	html += '	<td class="center"><input type="text" name="subInfoDesc[]" value="" size="30" class="resp_text"/></td>';
	html += '	<td class="center"><button type="button" class="btn_minus goodsSubInfoDel"  onclick="del_subInfo(this);"></button></td>';
	html += '</tr>';

	$("tbody#subInfoTable").closest("table").find("tr.nothing").addClass("hide");
	$("tbody#subInfoTable").append(html);
}

// 오픈마켓 연동 상품 정보
function initOpenmarketProd(goodsSeq){
	$("#openmarketLists .openmarket-no-data").show();
	$("#openmarketLists .openmarket-row").remove();
	$("#openmarketLists .showLastResult").unbind("click");
	if(typeof goodsSeq !== "undefined" && goodsSeq!="" && goodsSeq>0){
		var params = {
			"searchType" 	: "fmGoodsSeq",
			"keyword" 		: goodsSeq,
			"mode"			: "goodsModify",
			"limit" 		: "999"
		};
		$.ajax({
			type: "get",
			url: "../market_connector_process/getMarketProductList",
			data : params,
			success: function(result){
				var jsonResult = $.parseJSON(result);
				if(jsonResult){
					if(jsonResult.totlCount>0){
						$("#openmarketLists .openmarket-no-data").hide();
						//$("#openmarketLists .openmarket-rowspan").attr("rowspan",parseInt(jsonResult.totlCount)+1);
						var idx = 1;
						for(var i in jsonResult.marketProductList){
							var row = jsonResult.marketProductList[i];
							if(typeof row === "object"){
								var tr = $("<tr/>").addClass("openmarket-row");
								var tdOpenmarketNumber 				= $("<td/>").addClass("center");
								var tdOpenmarketName 				= $("<td/>").addClass("center");
								var tdOpenmarketSellerId 			= $("<td/>").addClass("center");
								var tdOpenmarketProdLink 			= $("<td/>").addClass("center");
								var tdOpenmarketStatus 				= $("<td/>").addClass("center");
								var tdOpenmarketLastResult 			= $("<td/>").addClass("center");
								var tdOpenmarketLastDistributedDate = $("<td/>").addClass("center");

								var detailLastResult = "";
								if(row.last_result != "Y"){
									detailLastResult = $("<sapn/>").addClass("getLastResult").attr("style","cursor: pointer;");
									detailLastResult.html(" [사유]")
									detailLastResult.data("fm_market_product_seq",row.fm_market_product_seq);
								}

								tdOpenmarketNumber.append(idx);
								tdOpenmarketName.append(row.market_name);
								tdOpenmarketSellerId.append(row.seller_id);
								tdOpenmarketProdLink.append(row.market_product_link);
								tdOpenmarketStatus.append(row.market_sale_status_text);
								tdOpenmarketLastResult.append(row.list_result_text);
								tdOpenmarketLastResult.append(detailLastResult);
								tdOpenmarketLastDistributedDate.append(row.last_distributed_time);


								tr.append(tdOpenmarketNumber);
								tr.append(tdOpenmarketName);
								tr.append(tdOpenmarketSellerId);
								tr.append(tdOpenmarketProdLink);
								tr.append(tdOpenmarketStatus);
								tr.append(tdOpenmarketLastResult);
								tr.append(tdOpenmarketLastDistributedDate);

								$("#openmarketLists").append(tr);
								idx++;
							}
						}
						// 이벤트 바인드
						$("#openmarketLists .getLastResult").on("click",function(){
							var fm_market_product_seq = $(this).data("fm_market_product_seq");
							getOpenmarketLastStatus(fm_market_product_seq);
						});
					}
				}
			}
		});
	}else{
		// 등록 상태에서는 오픈마켓 연동 영역 미노출
		$("#openmarketArea").hide();
	}
}
// 오픈마켓 상품 연동 마지막 상태 조회
function getOpenmarketLastStatus(fm_market_product_seq){
	var params = {
		"fmMarketProduceSeq" : fm_market_product_seq
	};
	$.ajax({
		type: "get",
		url: "../market_connector_process/getMarketProductLog",
		data : params,
		success: function(result){
			var jsonResult = $.parseJSON(result);
			if(jsonResult){
				var lastRow = jsonResult[0];
				openDialogAlert(lastRow.log_text,400,200);
			}
		}
	});
}

//옵션관리
function delFreqOption(goods_seq, type, page, packageyn, popupID){
	if( !goods_seq || goods_seq <= 0 ){
		alert("상품 번호를 찾을 수 없습니다.");
		return false;
	}
	
	if( !type ){
		alert("타입을 찾을 수 없습니다.");
		return false;
	}

	$.ajax({
		'url' : '../goods_process/del_freq_option',
		'data' : {'goods_seq': goods_seq, 'type': type},
		'type' : 'post',
		'success' : function(res){
			if(res === false){
				alert("삭제 실패");
			} else {
				$(".delFreqOptionName_"+goods_seq).parent().parent().remove();
				if (type == "opt") {
					$('select[name="frequentlytypeopt"] option[value="'+goods_seq+'"]').remove();
				} else if (type == "sub") {
					$('select[name="frequentlytypesubopt"] option[value="'+goods_seq+'"]').remove();
				} else if (type == "inp") {
					$('select[name="frequentlytypeinputopt"] option[value="'+goods_seq+'"]').remove();
				}
				
				frequentlypaging(page, type, packageyn, popupID);
				alert("삭제 성공");
			}
		}
	});
}

function frequentlypaging(page, type, packageyn, popupID){
	$.ajax({
		'url' : '../goods_process/get_freq_paging',
		'data' : {'page': page, 'type': type, 'packageyn': packageyn, 'popupID': popupID},
		'type' : 'post',
		'success' : function(res){
			var data = jQuery.parseJSON(res);
			var result = data.result;
			
			if(result.length > 0){
				$("#"+popupID+" table tbody").html('');
				
				$.each(result, function(key, item) {
					var contents = '<tr>';
					contents += '<td><span class="delFreqOptionName_'+item.goods_seq+'">'+item.goods_name+'</span></td>';
					contents += '<td class="its-th-align center">';
					contents += '<span class="btn small"><button type="button" class="delFreqOption" value="'+item.goods_seq+'" data-type="opt">삭제</button></span>';
					contents += '</td>';
					contents += '</tr>';
					
					$("#"+popupID+" table tbody").append(contents);
				});
			} else {
				$("#"+popupID+" table tbody").html('');
				$("#"+popupID+" table tbody").html('<tr> <td colspan="2" class="its-th-align center">데이터 없음</td></tr>');
			}
			
			$("#"+popupID+" .paging_navigation").html(data.paging);
		}
	});
}

//검색어 가져오기시
function openmarketKeyword(){
	var feed_status = $('input[name="feed_status"]:checked').val();
	if(feed_status == 'Y'){
		var keyword_val = $("input[name='keyword']").val();
		$("input[name='openmarket_keyword']").val(keyword_val);
	}
}

// 옵션보기 설정 저장 완료처리
function optionViewSave(){
	loadingStop();
	closeDialog("set_option_view_lay");
}

function socialcpcancelDel(obj){
	$(obj).closest("tr").remove();
}

function providerChk(){
	var provider_seq = $("input[name='provider_seq']").val();
	if(!provider_seq && gl_goods_seq != ''){
		provider_seq			= gl_provider_seq;
	}	

	// 상품신규등록 시 구분값(본사/입점사)과 실제 선택된 입점사번호(provider_seq) 가 올바른지 확인.
	if(goodsObj.sellerMode != 'SELLER' && $("input[name='goods_gubun']:checked").length > 0){

		var goods_gubun			= $("input[name='goods_gubun']:checked").val();

		if(goods_gubun == "provider"){
			if(provider_seq < 2){
				alert("입점사를 먼저 선택 해 주세요.");
				return false;
			}else{
				return true;
			}
		}else{
			if(provider_seq != 1){
				alert("본사 정보가 잘못되었습니다. 새로고침 해주세요.");
				return false;
			}else{
				return true;
			}
		}
	}else{
		return true;
	}
}

// 재고관리 input 창 입력가능 여부 재세팅
function resetStockInput(goods_gubun){
	if(gl_scm_use == 'Y' && $("input[name='optionUse']:checked").val() != "1"){
		if(goods_gubun == 'admin'){
			$("input[name='stock[]']").each(function(){ $(this).prop("type","hidden"); $(this).closest("td").removeClass("center").addClass("right").addClass("pdr10").find("span").show(); });
			$("input[name='badstock[]']").each(function(){ $(this).prop("type","hidden"); $(this).closest("td").removeClass("center").addClass("right").addClass("pdr10").find("span").show(); });
		}else{
			if(gl_package_yn != 'y'){
				$("input[name='stock[]']").each(function(){ $(this).prop("type","text"); $(this).closest("td").removeClass("right").removeClass("pdr10").addClass("center").find("span").hide(); });
				$("input[name='badstock[]']").each(function(){ $(this).prop("type","text"); $(this).closest("td").removeClass("right").removeClass("pdr10").addClass("center").find("span").hide(); });
			}
		}
	}
}

// 필수옵션 판매가능한 재고/가용 수량 구하기
function check_option_stock() {

	var optionTmpSeq = $("#optionTmpSeq").val();
	if(typeof optionTmpSeq == "undefined") optionTmpSeq = '';

	// 옵션 사용 여부 
	var optionUse = $("input[name='optionUse']:checked").val();
	if(typeof optionUse == "undefined"){
		optionUse = '';
	}
	var params = {'goods_seq':gl_goods_seq};
	params.optionUse 	= optionUse;
	params.optionType 	= 'opt';
	params.tmp_seq 		= optionTmpSeq;
	params.package_yn 	= gl_package_yn;
	params.runout_type	= $("input[name='runout_type']:checked").val();

	if(params.runout_type == "goods") {
		params.select_runout	= $("input[name='runout']:checked").val();
		params.ableStockLimit	= $("input[name='ableStockLimit']").val();
	}

	//필수옵션 사용안함 일 때
	if(params.optionUse == ''){
		// 패키지 상품  '주문당 발송 수량' 가져오기 
		if(gl_package_yn == "y"){
			params.tmp_seq			= '';	// 필수옵션 사용안함 일 때에는 임시 옵션 정보 없음.
			var package_count 		= $("select[name='reg_package_count'] option:selected").val();
			var package_option_seq 	= new Array();
			var package_unit_ea 	= new Array();
			for(var i = 1; i <= package_count ; i++){
				package_option_seq[i-1] = $("#optionLayer input[name='reg_package_option_seq"+i+"[]']").val();		// 선택한 상품옵션 시퀀스
				package_unit_ea[i-1] 	= $("#optionLayer input[name='package_unit_ea"+i+"[]']").val();	// 주문당 발송 수량
			}
			params.package_option_seq 	= package_option_seq;
			params.package_unit_ea 		= package_unit_ea;

		// 일반 상품 입력한 재고 수량(SCM버전 아닐 때)
		}else{
			params.inputStock = $("#optionLayer input[name='stock[]']").eq(0).val();
			params.inputBadstock 	= $("#optionLayer input[name='badstock[]']").eq(0).val();
		}
	}

	var totablestock = 0;

	$.ajax({
		type: "get",
		url: "../goods/check_option_stock",
		dataType: "json",
		async: false, //동기식으로 실행
		data: params,
		success: function(result){
			totablestock = result.totablestock;			
		}
	});

	return totablestock;

}

// 동영상 설정 알림 메시지
function videoSettingAlert(){
	openDialogAlert("<a href='../setting/video' class='link_blue_01'>동영상</a>에서 세팅정보를 설정해 주세요.",400,160);
}

// 지에디터 발행번호 등록
function setGeditorDeploymentId(){
	const deploymentId = document.getElementsByName('mobile_deploymentId')[0];
	const service_key = document.getElementsByName('service_key')[0]; 
	const params = {'deploymentId':deploymentId.value, 'service_key': service_key.value };

	if (deploymentId === '') {
		alert('발행번호를 입력하세요.');
		return false;
	}

	$.post("/admin/goods_process/setDeploymentId", params, function(response) {
		const allowHttp = [200,201];
		let deployment_html = response.result['deployment_html'];

		if (response.result === false) {
			alert('존재하지 않는 발행번호입니다. 다시 확인해 주세요.');
			deploymentId.value = '';
			return false;
		}

		if (response['httpCode'] && allowHttp.includes(response['httpCode']) === false) {
			alert(response['message']);
			deploymentId.value = '';
			return false;
		}

		// 신규인 경우
		$('.noContnets').hide();
		$('#geditor_mobile_contents_view').show();
		$("#geditor_mobile_contents_view").html(deployment_html);
		$("#mobile_contents").val(deployment_html);
		$('#mobile_contents_view').html(deployment_html);
		$("input[name='geditorExcute']").val('true');
		alert('임시 저장되었습니다.');		
	  },"json")
	  .error(function(request,status,error) { 
		const errorResult = JSON.parse(request.responseText);
		if (errorResult.key === 'service_key') {
			let geditorUrl = "https://geditor.co.kr/product/service_interlocking_info";
			errorResult.message = "<a target='_blank' href='"+geditorUrl+"' class='link_blue_01'>제디터</a>에서 서비스 연동 후 사용해 주세요.";
		}
		openDialogAlert(errorResult.message,400,160);
		deploymentId.value = '';
		return false;
	   });
}
