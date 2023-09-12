var excelTableOption	= '';
var excelTableObj		= '';
var current_page		= '';
var current_currency	= 'KRW';
var current_trade_terms	= 'CIF';
var exchanges			= [];
var openerObj			= '';
var progObj				= '';
var common_no_img		= '/admin/skin/default/images/common/noimage_list.gif';

//------------ ↑↑ define global value ↑↑------ ↓↓ excel table ↓↓--------------//

// 엑셀 테이블 공통 설정 부분
function commonExcelTable(kind, viewMode, goodsData, perpage, totalCount){

	if	(!perpage)		perpage		= 0;
	if	(!totalCount)	totalCount	= 0;
	current_page		= kind;
	excelTableOption	= {'viewMode' : viewMode, 'dataPer' : perpage, 'dataCount' : totalCount};
	if	(goodsData)	var data	= goodsDataArrayValues(goodsData);
	else			var data	= [];

	// 일괄 적용 html 추가
	if	(!viewMode){
		var ea_batch			= '<div><input type="text" class="excel_table_batch_val onlynumber" style="width:60%;" /> <span class="btn small gray"><button type="button" onclick="batch_all_price(this, \'ea\');" class="resp_btn v2 arrow">▼</button></span></div>';
		var supply_batch		= '<div><input type="text" class="excel_table_batch_val onlynumber" style="width:60%;" /> <span class="btn small gray"><button type="button" onclick="batch_all_price(this, \'supply\');" class="resp_btn v2 arrow">▼</button></span></div>';
		var weight_batch		= '<div><input type="text" class="excel_table_batch_val onlynumber" style="width:60%;" /> <span class="btn small gray"><button type="button" onclick="batch_all_price(this, \'weight\');" class="resp_btn v2 arrow">▼</button></span></div>';
		var freight_batch		= '<div><input type="text" class="excel_table_batch_val onlynumber" style="width:60%;" /> <span class="btn small gray"><button type="button" onclick="batch_all_price(this, \'freight\');" class="resp_btn v2 arrow">▼</button></span></div>';
		var insurance_batch		= '<div><input type="text" class="excel_table_batch_val onlynumber" style="width:60%;" /> <span class="btn small gray"><button type="button" onclick="batch_all_price(this, \'insurance\');" class="resp_btn v2 arrow">▼</button></span></div>';
		var duty_batch			= '<div><input type="text" class="excel_table_batch_val onlynumber" style="width:60%;" /> <span class="btn small gray"><button type="button" onclick="batch_all_price(this, \'duty\');" class="resp_btn v2 arrow">▼</button></span></div>';
		var accessorial_batch	= '<div><input type="text" class="excel_table_batch_val onlynumber" style="width:60%;" /> <span class="btn small gray"><button type="button" onclick="batch_all_price(this, \'accessorial\');" class="resp_btn v2 arrow">▼</button></span></div>';
		var tax_batch			= '<div><input type="text" class="excel_table_batch_val onlynumber" style="width:60%;" /> <span class="btn small gray"><button type="button" onclick="batch_all_price(this, \'taxprice\');" class="resp_btn v2 arrow">▼</button></div>';
	}
	var descriptions			= getPageDescriptions();

	if	(current_currency != 'KRW'){
		// USD인 경우 소수점 등록 필요하여 onlynumber 클래스 적용하지 않도록 수정 
		supply_batch		= '<div><input type="text" class="excel_table_batch_val " style="width:60%;" /> <span class="btn small gray"><button type="button" onclick="batch_all_price(this, \'supply\');" class="resp_btn v2 arrow">▼</button></span></div>';
		var thHeader		= [[{'title':'', 'chkClass':'chk', 'rowspan':2}, 
								{'title':'상품번호<br/>바코드<br/><span class="tooltip_btn" onclick="showTooltip(this, \'/admin/tooltip/scm\', \'#tip6\')"></span>', 'rowspan':2}, 
								{'title':'상품명/매입용상품명', 'colspan':2, 'rowspan':2}, 
								{'title':'옵션', 'rowspan':2}, 
								{'title':'로케이션', 'rowspan':2}, 
								{'title':'재고<span class="tooltip_btn" onclick="showTooltip(this, \'/admin/tooltip/scm\', \'#tip12\', \'190\')', 'rowspan':2}, 
								{'title':'수량', 'addHTML':ea_batch, 'rowspan':2}, 
								{'title':'단가(' + current_currency + ')', 'colspan':2, 'addHTML':supply_batch, 'rowspan':2}, 
								{'title':'무게(KG)', 'rowspan':2, 'addHTML':weight_batch},
								{'title':'외화(' + current_currency + ')', 'colspan':3}, 
								{'title':'금액(KRW) <span class="tooltip_btn" onclick="showTooltip(this, \'/admin/tooltip/scm\', \'#tip13\', \'180\')', 'colspan':3}, 
								{'title':'부가세<span class="helpicon" title="' + descriptions.tax + '"></span>', 'rowspan':2, 'addHTML':tax_batch}], 
								[{'title':'상품'}, 
								{'title':'운임', 'addHTML':freight_batch},
								{'title':'보험료', 'addHTML':insurance_batch},
								{'title':'CIF'},
								{'title':'관세', 'addHTML':duty_batch},
								{'title':'부대비용<span class="helpicon" title="' + descriptions.accessorial + '"></span>', 'addHTML':accessorial_batch}]];
		var colWidth			= ['3%', '6%', '4%', '12%', '10%', '5%', '4%', '5%', '4%', '5%', '5%', 
									'4%', '5%', '5%', '4%', '5%', '5%', '5%', '0', '0', '0', '0', '0'];
		var tdBody			= [	{'type':'checkbox','boxName':'option_seq[]', 'tdClass':'center chk'}, 
								{'type':'plain','boxName':'goods_info_str', 'tdClass':'break-all left pdl5'}, 
								{'type':'image','boxName':'goods_image[]', 'tdClass':'center','w':'30','h':'30','noimg':common_no_img}, 
								{'type':'autoComplete','boxName':'goods_name[]', 'tdClass':'break-all left pdl5','userFunc':'getGoodsData'}, 
								{'type':'autoComplete','boxName':'option_name[]', 'tdClass':'break-all left pdl5','userFunc':'getOptionData'}, 
								{'type':'autoComplete','boxName':'location_code[]','userFunc':'getLocationData'}, 
								{'type':'plain','boxName':'tmp_org_ea', 'tdClass':'right'}, 
								{'type':'text','boxName':'ea[]', 'tdClass':'right ea','userFunc':'calculateExcelTable'},
								{'type':'plain','boxName':'btn_lastdata'}, 
								{'type':'text','boxName':'supply_price[]', 'tdClass':'right supply','userFunc':'calculateExcelTable'}, 
								{'type':'text','boxName':'weight[]', 'tdClass':'right weight', 'userFunc':'calculateExcelTable'}, 
								{'type':'view','boxName':'goods_price[]', 'tdClass':'right bg-lightyellow'}, 
								{'type':'text','boxName':'freight_price[]', 'tdClass':'right bg-lightyellow freight','userFunc':'calculateExcelTable'}, 
								{'type':'text','boxName':'insurance_price[]', 'tdClass':'right bg-lightyellow insurance','userFunc':'calculateExcelTable'}, 
								{'type':'view','boxName':'cif_price[]', 'tdClass':'right'}, 
								{'type':'text','boxName':'duty_price[]', 'tdClass':'right duty','userFunc':'calculateExcelTable'}, 
								{'type':'text','boxName':'accessorial_price[]', 'tdClass':'right accessorial','userFunc':'calculateExcelTable'}, 
								{'type':'text','boxName':'supply_tax[]', 'tdClass':'right taxprice','userFunc':'calculateExcelTable'},
								{'type':'hide','boxName':'goods_seq[]'}, 
								{'type':'hide','boxName':'goods_code[]'}, 
								{'type':'hide','boxName':'location_position[]'}, 
								{'type':'hide','boxName':'supply_goods_name[]'},
								{'type':'hide','boxName':'stock[]'}];
	}else{
		var thHeader		= [[{'title':'', 'chkClass':'chk'}, 
								{'title':'상품번호<br/>바코드<br/><span class="tooltip_btn" onclick="showTooltip(this, \'/admin/tooltip/scm\', \'#tip6\')"></span>'}, 
								{'title':'상품명/매입용상품명', 'colspan':2}, 
								{'title':'옵션'}, 
								{'title':'로케이션'}, 
								{'title':'재고 <span class="tooltip_btn" onclick="showTooltip(this, \'/admin/tooltip/scm\', \'#tip12\', \'190\')"></span>'}, 
								{'title':'수량', 'addHTML':ea_batch}, 
								{'title':'단가(KRW)', 'colspan':2, 'addHTML':supply_batch}, 
								{'title':'금액(KRW) <span class="tooltip_btn" onclick="showTooltip(this, \'/admin/tooltip/scm\', \'#tip13\', \'180\')"></span>'}, 
								{'title':'부가세', 'colspan':2, 'chkClass':'tax', 'userFunc':'calculateExcelTableAllRow'}]];
		var colWidth			= ['3%', '8%', '5%', '20%', '11%', '7%', '6%', '8%', '5%', '7%', '10%', '3%', '5%', '0', '0', '0', '0', '0'];
		var tdBody			= [	{'type':'checkbox','boxName':'option_seq[]', 'tdClass':'center chk'}, 
								{'type':'plain','boxName':'goods_info_str', 'tdClass':'break-all left'}, 
								{'type':'image','boxName':'goods_image[]', 'tdClass':'center','w':'30','h':'30','noimg':common_no_img}, 
								{'type':'autoComplete','boxName':'goods_name[]', 'tdClass':'break-all left','userFunc':'getGoodsData'}, 
								{'type':'autoComplete','boxName':'option_name[]', 'tdClass':'break-all left','userFunc':'getOptionData'}, 
								{'type':'autoComplete','boxName':'location_code[]','userFunc':'getLocationData'}, 
								{'type':'plain','boxName':'tmp_org_ea', 'tdClass':'right'}, 
								{'type':'text','boxName':'ea[]', 'tdClass':'right ea','userFunc':'calculateExcelTable'},
								{'type':'plain','boxName':'btn_lastdata'}, 
								{'type':'text','boxName':'supply_price[]', 'tdClass':'right supply','userFunc':'calculateExcelTable'}, 
								{'type':'view','boxName':'sum_supply_price[]', 'tdClass':'right bg-lightyellow'}, 
								{'type':'checkbox','boxName':'tax[]', 'tdClass':'center tax bg-lightyellow','userFunc':'calculateExcelTable','value':'Y','checked':true}, 
								{'type':'view','boxName':'supply_tax[]', 'tdClass':'right bg-lightyellow'},
								{'type':'hide','boxName':'goods_seq[]'}, 
								{'type':'hide','boxName':'goods_code[]'}, 
								{'type':'hide','boxName':'location_position[]'}, 
								{'type':'hide','boxName':'supply_goods_name[]'},
								{'type':'hide','boxName':'stock[]'}];
	}

	excelTableOption	= {
		'wrapWidth'			: '100%', 
		'viewMode'			: viewMode, 
		'rowController'		: false, 
		'tableClass'		: 'table_row_basic v2 pd5', 
		'thDefaultClass'	: '', 
		'tdDefaultClass'	: '', 
		'thClass'			: 'center', 
		'tdClass'			: 'center', 
		'colWidth'			: colWidth, 
		'thHeader'			: thHeader, 
		'tdBody'			: tdBody, 
		'data'				: data, 
		'dataPer'			: perpage, 
		'dataCount'			: totalCount,
		'chgViewDataFunc'	: 'chgDisplayValue' 
	};
	exceptExcelTableOption();
	excelTableObj		= $('div#excelTable').fmexceltable(excelTableOption);

	// 데이터가 있을 시
	calculateExcelTableAllRow();
	area_help_tooltip($('div#excelTable'));
}

// 특정 페이지에 대한 예외처리
function exceptExcelTableOption(){
	// 공통
	if	(excelTableOption.viewMode){
		excelTableOption.thHeader[0][5].hide	= 'y';
		excelTableOption.tdBody[6].type			= 'hide';
		excelTableOption.thHeader[0][7].colspan	= '1';
		excelTableOption.tdBody[8].type			= 'hide';
	}

	// 페이지별 시 추가 정보
	switch(current_page){
		case 'revision':
			excelTableOption.thHeader[0][9].hide	= 'y';
			excelTableOption.tdBody[11].type		= 'hide';
			excelTableOption.tdBody[12].type		= 'hide';
		break;
		case 'stockmove':
			var descriptions	= getPageDescriptions();

			var thHeader		= [[{'title':'', 'chkClass':'chk','rowspan':2}, 
									{'title':'상품번호<br/>바코드<br/><span class="tooltip_btn" onclick="showTooltip(this, \'/admin/tooltip/scm\', \'#tip6\')"></span>','rowspan':2}, 
									{'title':'상품명/매입용상품명', 'colspan':2,'rowspan':2}, 
									{'title':'옵션','rowspan':2}, 
									{'title':'출고창고','colspan':3}, 
									{'title':'이동','colspan':3}, 
									{'title':'입고창고','colspan':3}],
									[{'title':'로케이션'}, 
									{'title':'이동전<span class="tooltip_btn" onclick="showTooltip(this, \'/admin/tooltip/scm\', \'#tip14\', \'180\')"></span>'}, 
									{'title':'이동후'}, 
									{'title':'수량', 'addHTML':'<div><input type="text" class="excel_table_batch_val" style="width:40%;" /> <span class="btn small gray"><button type="button" onclick="batch_all_price(this, \'ea\');" class="resp_btn v2 arrow">▼</button></span></div>'}, 
									{'title':'단가(KRW)', 'addHTML':'</br><div><input type="text" class="excel_table_batch_val " style="width:40%;" /> <span class="btn small gray"><button type="button" onclick="batch_all_price(this, \'supply\');" class="resp_btn v2 arrow">▼</button></span></div>'}, 
									{'title':'금액(KRW) <span class="tooltip_btn" onclick="showTooltip(this, \'/admin/tooltip/scm\', \'#tip13\', \'180\')"></span>'}, 
									{'title':'로케이션'}, 
									{'title':'이동전<span class="tooltip_btn" onclick="showTooltip(this, \'/admin/tooltip/scm\', \'#tip15\', \'180\')"></span>'}, 
									{'title':'이동후'}]];
			var colWidth		= ['3%', '8%', '5%', '', '5%', '7%', '8%', '7%', '8%', '8%', '9%', '7%', '0', '7%', '7%', '0', '0', '0', '0', '0'];
			var tdBody			= [	{'type':'checkbox','boxName':'option_seq[]', 'tdClass':'center chk'}, 
									{'type':'plain','boxName':'goods_info_str', 'tdClass':'break-all left pdl5'}, 
									{'type':'image','boxName':'goods_image[]', 'tdClass':'center','w':'30','h':'30','noimg':common_no_img}, 
									{'type':'autoComplete','boxName':'goods_name[]', 'tdClass':'break-all left pdl5','userFunc':'getGoodsData'}, 
									{'type':'autoComplete','boxName':'option_name[]', 'tdClass':'break-all left pdl5','userFunc':'getOptionData'}, 
									{'type':'view','boxName':'out_location_code[]'}, 
									{'type':'plain','boxName':'out_bf_stock[]'}, 
									{'type':'plain','boxName':'out_af_stock[]'}, 
									{'type':'text','boxName':'ea[]', 'tdClass':'right ea','userFunc':'calculateExcelTable'},
									{'type':'text','boxName':'supply_price[]', 'tdClass':'right supply','userFunc':'calculateExcelTable'}, 
									{'type':'view','boxName':'sum_supply_price[]', 'tdClass':'right bg-lightyellow'}, 
									{'type':'autoComplete','boxName':'in_location_code[]','userFunc':'getLocationData'}, 
									{'type':'hide','boxName':'in_location_position[]'}, 
									{'type':'plain','boxName':'in_bf_stock[]'}, 
									{'type':'plain','boxName':'in_af_stock[]'}, 
									{'type':'hide','boxName':'goods_seq[]'}, 
									{'type':'hide','boxName':'goods_code[]'}, 
									{'type':'hide','boxName':'out_location_position[]'}, 
									{'type':'hide','boxName':'supply_goods_name[]'},
									{'type':'hide','boxName':'stock[]'}];
			if	(excelTableOption.viewMode){
				thHeader[1][1].title	= '이동전';
				thHeader[1][2].title	= '이동후<span class="tooltip_btn" onclick="showTooltip(this, \'/admin/tooltip/scm\', \'#tip16\', \'190\')"></span>';
				thHeader[1][3].addHTML	= '';
				thHeader[1][4].addHTML	= '';
				thHeader[1][7].title	= '이동전';
				thHeader[1][8].title	= '이동후<span class="tooltip_btn" onclick="showTooltip(this, \'/admin/tooltip/scm\', \'#tip17\', \'190\')"></span>';
			}
			excelTableOption.thHeader	= thHeader;
			excelTableOption.colWidth	= colWidth;
			excelTableOption.tdBody		= tdBody;
		break;
		case 'sorder':
			excelTableOption.thHeader[0][4].hide	= 'y';
			excelTableOption.tdBody[5].type			= 'hide';

			if	(current_currency != 'KRW'){
				excelTableOption.thHeader[0].push({'title':'미입고', 'rowspan':2});
				excelTableOption.thHeader[0].push({'title':'비고', 'rowspan':2});
			}else{
				excelTableOption.thHeader[0].push({'title':'미입고'});
				excelTableOption.thHeader[0].push({'title':'비고'});
			}
			excelTableOption.tdBody.push({'type':'view','boxName':'remain_ea[]', 'tdClass':'right'});
			excelTableOption.tdBody.push({'type':'view','boxName':'aooSeq[]', 'tdClass':'left'});
			excelTableOption.colWidth.push('4%');
			excelTableOption.colWidth.push('4%');
		break;
		case 'warehousing':
			if	($("input[name='whs_type']").val() != 'E'){
				excelTableOption.tdBody[3].type			= 'view';
				excelTableOption.tdBody[4].type			= 'view';
				excelTableOption.tdBody[3].userFunc		= '';
				excelTableOption.tdBody[4].userFunc		= '';
			}
		break;
		case 'carryingout':
			excelTableOption.tdBody[5].type				= 'view';
			excelTableOption.tdBody[5].userFunc			= '';
		break;
	}

	return excelTableOption;
}

// 거래처 변경에 따른 form 변경 처리
function scmChangeTrader(obj){
	if	(obj){
		current_currency	= $(obj).find('option:selected').attr('currency_unit');
		var trader_name		= $(obj).find('option:selected').text();
		if	(!current_currency)	current_currency	= 'KRW';
		if	($(obj).val() > 0)	$('.trader-name').html(trader_name);
		else					$('.trader-name').html('');
	}
	$('.currency-unit').html(current_currency);
	$('.totals').html('0');

	if	(current_currency == 'KRW'){
		$('div.total-division-lay').eq(0).show();
		$('div.total-division-lay').eq(1).hide();
		$('.krw-display-target').show();
		$('.fc-display-target').hide();
		$('.change-bg-currency').removeClass('bg-lightyellow').find('input').attr('checked', true);
		$('.change-bg-currency2').addClass('bg-lightyellow').html('●');
		$("input[name='goods_exchange']").val('0');
		$("input[name='fi_exchange']").val('0');
		excelTableObj.destroyTable();
		commonExcelTable(current_page, excelTableOption.viewMode, '', '', '');
	}else{
		current_trade_terms		= 'CIF';
		$('div.total-division-lay').eq(0).hide();
		$('div.total-division-lay').eq(1).show();
		$('.krw-display-target').hide();
		$('.fc-display-target').show();
		$('.change-bg-currency').addClass('bg-lightyellow').find('input').attr('checked', true);
		$('.change-bg-currency2').removeClass('bg-lightyellow').html('X');
		$("input[name='goods_exchange']").val(exchanges[current_currency].currency_exchange);
		$("input[name='fi_exchange']").val(exchanges[current_currency].currency_exchange);
		excelTableObj.destroyTable();
		commonExcelTable(current_page, excelTableOption.viewMode, '', '', '');
	}
}

// 외화 매입 시 cif 선택에 따른 상품 form 변경
function apply_cif_set(){
	if	(current_currency == 'KRW'){
		current_trade_terms		= 'CIF';
	}else{
		current_trade_terms				= 'FOB';	// free on board ( 상품 )
		if			($("input[name='inclusion_freight']").attr('checked')){
			current_trade_terms			= 'CFR';	// cost and freight ( 상품 + 운임 )
			$('td.freight').addClass('bg-lightyellow');
			if		($("input[name='inclusion_insurance']").attr('checked')){
				current_trade_terms		= 'CIF';	// cost insurance and freight ( 상품 + 운임 + 보험 )
				$('td.insurance').addClass('bg-lightyellow');
			}else{
				$('td.insurance').removeClass('bg-lightyellow');
			}
		}else{
			$('td.freight').removeClass('bg-lightyellow');
			if	($("input[name='inclusion_insurance']").attr('checked')){
				// 무역거래조건에서는 존재하지 않는 조건임.
				current_trade_terms			= 'CIN';	// cost and insurance ( 상품 + 보험 )
				$('td.insurance').addClass('bg-lightyellow');
			}else{
				$('td.insurance').removeClass('bg-lightyellow');
			}
		}
	}

	calculateExcelTableTotal();
}

// 최근 입고내역에서 매입단가 선택 팝업
function openSelectWarehousing(targetID, returnFunc, goods_seq, option_type, option_seq, limit){
	var params	= 'goods_seq=' + goods_seq + '&option_type=' + option_type + '&option_seq=' + option_seq + '&returnFunc=' + returnFunc + '&targetID=' + targetID;
	var height	= 170;
	if	(limit > 0){
		params	+= '&limit=' + limit;
		height	= parseInt(height) + (limit * 30);
	}

	$.ajax({
		type	: 'get',
		url		: '../scm/get_lastwarehousing',
		data	: params,
		global	: false,
		success	: function(result){
			$('div#' + targetID).html(result);
			openDialog('최근 입고내역', targetID, {'width':600,'height':height});
		}
	});
}

// 페이지별 description 문구 정의
function getPageDescriptions(){
	var descriptions	= {	'stock'			: '창고의 현재고',
							'price'			: '원가 반영 금액', 
							'tax'			: '매입 부가가치세', 
							'accessorial'	: '부가세 제외 부대비용'}
	switch(current_page){
		case 'sorder':
			descriptions.stock	= '입고예정 창고의 현재고';
		break;
		case 'warehousing':
			descriptions.stock	= '입고 창고의 현재고';
		break;
		case 'carryingout':
			descriptions.stock	= '반출 창고의 현재고';
		break;
		case 'revision':
			descriptions.stock	= chgRevisionStockTitle();
		break;
	}

	return descriptions;
}

// 상품 및 옵션 검색 전 필수값 체크
function chkBeforeSelectGoodsOptionData(params){

	var result	= {};

	// 거래처 선택이 있는 경우
	if	($("select[name='trader_seq']").attr('name')){
		if	(!$("select[name='trader_seq']").val()){
			openDialogAlert('거래처를 먼저 선택해 주세요.', 400, 170, function(){});
			return false;
		}
		result['trader_seq']	= $("select[name='trader_seq']").val();
	}
	// 창고 선택이 있는 경우
	if	($("select[name='wh_seq']").attr('name')){
		if	(!$("select[name='wh_seq']").val()){
			openDialogAlert('창고를 먼저 선택해 주세요.', 400, 170, function(){});
			return false;
		}
		params					+= '&wh_seq=' + $("select[name='wh_seq']").val();
		result['wh_seq']		= $("select[name='wh_seq']").val();
	}
	// 출고창고 선택이 있는 경우
	if	($("select[name='out_wh_seq']").attr('name')){
		if	(!$("select[name='out_wh_seq']").val()){
			openDialogAlert('출고창고를 먼저 선택해 주세요.', 400, 170, function(){});
			return false;
		}
		params					+= '&out_wh_seq=' + $("select[name='out_wh_seq']").val();
		result['out_wh_seq']	= $("select[name='out_wh_seq']").val();
	}
	// 입고창고선택이 있는 경우
	if	($("select[name='in_wh_seq']").attr('name')){
		if	(!$("select[name='in_wh_seq']").val()){
			var msg	= '입고창고를 먼저 선택해 주세요';
			if	(current_page == 'sorder')	msg	= '입고예정창고를 먼저 선택해 주세요';
			openDialogAlert(msg, 400, 170, function(){});
			return false;
		}
		params					+= '&in_wh_seq=' + $("select[name='in_wh_seq']").val();
		result['in_wh_seq']		= $("select[name='in_wh_seq']").val();
	}
	// 발주서 검색 버튼이 있는 경우
	if	($('button#search_sorder_btn').attr('id')){
		if	(!$("input[name='sorder_seq']").val()){
			openDialogAlert('발주서를 먼저 선택해 주세요.', 400, 170, function(){});
			return false;
		}
		params					+= '&sorder_seq=' + $("input[name='sorder_seq']").val();
		result['sorder_seq']		= $("select[name='sorder_seq']").val();
	}

	result['status']	= true;
	result['params']	= params;

	return result;
}

// 상품 검색
function getGoodsData(obj, process){

	var keyword		= encodeURIComponent($(obj).val());
	var chkVal		= chkBeforeSelectGoodsOptionData('');
	var params		= chkVal['params'];
	if	(!chkVal['status'])	return false;
	params			= 'keyword=' + keyword + params;

	$.ajax({
		type		: 'get',
		url			: '../scm/getGoodsListData',
		data		: params,
		dataType	: 'json', 
		global		: false,
		success		: function(result){
			var goods_name_list	= result.goods_name_list;
			var addParam		= result.data;
			process(goods_name_list, 'selectGoodsData', addParam);
		}
	});
}

// 상품 선택
function selectGoodsData(sIdx, obj, goodsName, params){

	// 초기화
	resetRowData(obj, '');

	var goods_info_str		= '<font color="red">' + params[sIdx].goods_seq + '</font>'
							+ '<br/>' + params[sIdx].goods_code;

	// 상품 데이터 채우기
	$(obj).closest('tr').find("input[name='goods_seq[]']").val(params[sIdx].goods_seq);
	$(obj).closest('tr').find("input[name='goods_code[]']").val(params[sIdx].goods_code);
	$(obj).closest('tr').find("input[name='goods_name[]']").val(params[sIdx].goods_name);

	$(obj).closest('tr').find('span.goods_info_str').html(goods_info_str);
	$(obj).closest('tr').find("input[name='goods_name[]']").closest('td').find('span').html(params[sIdx].goods_name);
	if	(params[sIdx].image){
		$(obj).closest('tr').find("input[name='goods_image[]']").val(params[sIdx].image);
		$(obj).closest('tr').find("input[name='goods_image[]']").closest('td').find('img').attr('src', params[sIdx].image);
	}else{
		$(obj).closest('tr').find("input[name='goods_image[]']").val(common_no_img);
		$(obj).closest('tr').find("input[name='goods_image[]']").closest('td').find('img').attr('src', common_no_img);
	}
}

// 옵션 검색
function getOptionData(obj, process){
	if	( $(obj).closest('tr').find("input[name='goods_seq[]']").val() > 0){
		var goods_seq	= $(obj).closest('tr').find("input[name='goods_seq[]']").val();
		var keyword		= encodeURIComponent($(obj).val());
		var chkVal		= chkBeforeSelectGoodsOptionData('');
		var params		= chkVal['params'];
		if	(!chkVal['status'])	return false;
		params			= 'goods_seq=' + goods_seq + '&trader_seq=' + chkVal['trader_seq'] + '&keyword=' + keyword + params;

		$.ajax({
			type		: 'get',
			url			: '../scm/getOptionListData',
			data		: params,
			dataType	: 'json', 
			global		: false,
			success		: function(result){
				var option_name_list	= result.option_name_list;
				var addParam			= result.data;
				process(option_name_list, 'selectOptionData', addParam);
			}
		});
	}else{
		openDialogAlert('먼저 상품을 검색하세요', 400, 170, function(){});
		process([], 'selectOptionData', []);
	}
}

// 옵션 선택
function selectOptionData(sIdx, obj, optionName, params){
	// 초기화
	resetRowData(obj, 'goods');
	var standVal		= params[sIdx].option_type + '|' + params[sIdx].option_seq;
	var chkDuple		= excelTableObj.chkDuplicateRow('option_seq[]', standVal);
	if	(chkDuple){
		openDialogAlert('중복된 상품입니다.', 400, 170, function(){});
		return false;
	}
	var goods_seq			= $(obj).closest('tr').find("input[name='goods_seq[]']").val();
	var option_type			= params[sIdx].option_type;
	var option_seq			= params[sIdx].option_seq;
	var option_code			= $(obj).closest('tr').find("input[name='goods_code[]']").val() + params[sIdx].option_code;
	var goods_info_str		= '<font color="red">' + goods_seq + '</font>' + option_seq
							+ '<br/>' + option_code;
	var goods_name			= $(obj).closest('tr').find("input[name='goods_name[]']").val();
	var supply_price		= '0';

	if	(params[sIdx].default_sorder_info){
		if	(params[sIdx].default_sorder_info.supply_goods_name)
			goods_name		= goods_name + '/' + params[sIdx].default_sorder_info.supply_goods_name;
		if	(params[sIdx].default_sorder_info.supply_price)
			supply_price	= params[sIdx].default_sorder_info.supply_price;
	}

	var stockHtml			= '<span class="location_ea resp_btn_txt v2" onclick="openGoodsWarehouseStock(\'scmWarehouseStock\', \'\', \'' + goods_seq + '\', \'' + option_type + '\', \'' + option_seq + '\');">[:LOCATION_EA:]([:LOCATION_BADEA:])</span>';
	var location_ea			= (params[sIdx].location_ea) ? params[sIdx].location_ea : '0';
	var location_badea		= (params[sIdx].location_badea) ? params[sIdx].location_badea : '0';
	var tmpHTML1			= stockHtml.replace('[:LOCATION_EA:]', comma(location_ea)).replace('[:LOCATION_BADEA:]', comma(location_badea));
	var tmpHTML2			= '<span class="resp_btn_txt v2" onclick="open_lastdata(\'' + goods_seq + '\', \'' + option_seq + '\', \'\');">최신정보</span>';

	$(obj).closest('tr').find("input[name='option_seq[]']").val(standVal);
	$(obj).closest('tr').find("input[name='goods_name[]']").closest('td').find('span').html(goods_name);
	$(obj).closest('tr').find("input[name='option_name[]']").val(params[sIdx].option_name);
	$(obj).closest('tr').find("input[name='goods_code[]']").val(option_code);
	$(obj).closest('tr').find("input[name='location_position[]']").val(params[sIdx].location_position);
	$(obj).closest('tr').find("input[name='location_code[]']").val(params[sIdx].location_code);
	$(obj).closest('tr').find("input[name='ea[]']").val('1');
	$(obj).closest('tr').find("input[name='supply_price[]']").val(supply_price);
	$(obj).closest('tr').find("input[name='supply_tax[]']").val('0');

	$(obj).closest('tr').find('span.goods_info_str').html(goods_info_str);
	$(obj).closest('tr').find("input[name='option_name[]']").closest('td').find('span').html(params[sIdx].option_name);
	$(obj).closest('tr').find("input[name='goods_code[]']").closest('td').find('span').html(option_code);
	$(obj).closest('tr').find("input[name='location_code[]']").closest('td').find('span').html(params[sIdx].location_code);
	$(obj).closest('tr').find("input[name='ea[]']").closest('td').find('span').html('1');
	$(obj).closest('tr').find("input[name='supply_price[]']").closest('td').find('span').html(float_comma(supply_price));
	$(obj).closest('tr').find("input[name='supply_tax[]']").closest('td').find('span').html('0');
	$(obj).closest('tr').find('span.tmp_org_ea').html(tmpHTML1);
	$(obj).closest('tr').find('span.btn_lastdata').html(tmpHTML2);

	// 발주 페이지 추가 정보
	if			(current_page == 'sorder'){
		$(obj).closest('tr').find("input[name='remain_ea[]']").val('0');
		$(obj).closest('tr').find("input[name='remain_ea[]']").closest('td').find('span').html('<center>-</center>');
		$(obj).closest('tr').find("input[name='aooSeq[]']").val('수동');
		$(obj).closest('tr').find("input[name='aooSeq[]']").closest('td').find('span').html('수동');
	}else if	(current_page == 'stockmove'){
		tmpHTML1	= stockHtml.replace('[:LOCATION_EA:]', comma(params[sIdx].out_location_ea)).replace('[:LOCATION_BADEA:]', comma(params[sIdx].out_location_badea));
		tmpHTML2	= stockHtml.replace('[:LOCATION_EA:]', comma(params[sIdx].in_location_ea)).replace('[:LOCATION_BADEA:]', comma(params[sIdx].in_location_badea));
		$(obj).closest('tr').find("input[name='out_bf_stock[]']").val(params[sIdx].out_location_ea);
		$(obj).closest('tr').find("input[name='out_bf_stock[]']").closest('td').find('span').html(tmpHTML1);
		$(obj).closest('tr').find("input[name='in_bf_stock[]']").val(params[sIdx].in_location_ea);
		$(obj).closest('tr').find("input[name='in_bf_stock[]']").closest('td').find('span').html(tmpHTML2);
		$(obj).closest('tr').find("input[name='out_af_stock[]']").val('0');
		$(obj).closest('tr').find("input[name='out_af_stock[]']").closest('td').find('span').html('-');
		$(obj).closest('tr').find("input[name='in_af_stock[]']").val('0');
		$(obj).closest('tr').find("input[name='in_af_stock[]']").closest('td').find('span').html('-');
		$(obj).closest('tr').find("input[name='out_location_position[]']").val(params[sIdx].out_location_position);
		$(obj).closest('tr').find("input[name='out_location_code[]']").val(params[sIdx].out_location_code);
		$(obj).closest('tr').find("input[name='out_location_code[]']").closest('td').find('span').html(params[sIdx].out_location_code);
		$(obj).closest('tr').find("input[name='in_location_position[]']").val(params[sIdx].in_location_position);
		$(obj).closest('tr').find("input[name='in_location_code[]']").val(params[sIdx].in_location_code);
		$(obj).closest('tr').find("input[name='in_location_code[]']").closest('td').find('span').html(params[sIdx].in_location_code);
	}

	calculateExcelTable(obj, '');
}

// 입고 로케이션 검색
function getLocationData(obj, process){

	var goods_seq	= $(obj).closest('tr').find("input[name='goods_seq[]']").val();
	var optionStr	= $(obj).closest('tr').find("input[name='option_seq[]']").val();
	var keyword		= encodeURIComponent($(obj).val());
	var chkVal		= chkBeforeSelectGoodsOptionData('');
	var params		= chkVal['params'];
	if	(!chkVal['status'])	return false;
	var tmp			= optionStr.split('|');
	var option_type	= tmp[0];
	var option_seq	= tmp[1];
	if	(!goods_seq){
		openDialogAlert('상품을 선택해 주세요.', 400, 170, function(){});
		return false;
	}
	if	(!option_seq){
		openDialogAlert('옵션을 선택해 주세요.', 400, 170, function(){});
		return false;
	}
	params			= 'goods_seq=' + goods_seq + '&option_type=' + option_type + '&option_seq=' + option_seq + '&keyword=' + keyword + params;

	$.ajax({
		type		: 'get',
		url			: '../scm/getLocationList',
		data		: params,
		dataType	: 'json', 
		global		: false,
		success		: function(result){
			var code_list	= result.code_list;
			var addParam	= result.data;
			process(code_list, 'selectLocationData', addParam);
		}
	});
}

// 입고 로케이션 선택
function selectLocationData(sIdx, obj, location_code, positionList){
	$(obj).closest('tr').find("input[name='location_position[]']").val(positionList[sIdx]);
	$(obj).closest('tr').find("input[name='location_position[]']").closest('td').find('span').html(location_code);
	$(obj).closest('tr').find("input[name='in_location_position[]']").val(positionList[sIdx]);
	$(obj).closest('tr').find("input[name='in_location_position[]']").closest('td').find('span').html(location_code);
}

// goodsData json 데이터의 key값을 리스트에 맞게 가공
function goodsDataArrayValues(data){
	var result	= [];
	if	(data.length > 0){

		for	( var d = 0; d < data.length; d++){
			var row						= data[d];
			var option					= row.option_type + '|' + row.option_seq;
			var goods_code				= (row.goods_code) ? row.goods_code : '';
			if	(row.option_code)	goods_code	+= row.option_code;
			var goods_image				= (row.image) ? row.image : common_no_img;
			var ea						= (row.ea > 0) ? parseInt(row.ea) : 1;
			var location_ea				= (row.location_ea > 0) ? parseInt(row.location_ea) : 0;
			var location_badea			= (row.location_badea > 0) ? parseInt(row.location_badea) : 0;
			var whs_ea					= (row.whs_ea > 0) ? parseInt(row.whs_ea) : 0;
			var org_ea					= '<span class="location_ea resp_btn_txt v2" '
										+ 'onclick="openGoodsWarehouseStock(\'scmWarehouseStock\', '
										+ '\'\', \'' + row.goods_seq + '\', \'' + row.option_type + '\', '
										+ '\'' + row.option_seq + '\');">' + comma(location_ea)
										+ '(' + comma(location_badea) + ')</span>';
			var supply_price			= (row.supply_price) ? row.supply_price : '0';
			var use_tax					= (!row.use_tax || row.use_tax == 'Y' || row.use_tax == '과세') ? 'checked' : 'unchecked';
			var supply_tax				= (row.supply_tax) ? row.supply_tax : '0';
			var remain_ea				= '0';
			var stock			= location_ea;
			var add_reason				= '수동';
			if		(row.add_reason){
				if		(row.add_reason == '자동'){
					add_reason	= row.aooSeq;
				}else{
					add_reason	= row.add_reason;
				}
			}
			var goods_info_str			= '<font color="red">' + row.goods_seq + '</font>'
										+ row.option_seq;
			if	(goods_code)	goods_info_str	= goods_info_str + '<br/>' + goods_code;

			// 사전 처리
			if	(excelTableOption.viewMode){
				if	(current_page == 'sorder'){
					remain_ea					= row.ea - row.whs_ea;
				}
			}else{
				if			(current_page == 'warehousing' && row.whs_ea > 0){
					ea							= ea - row.whs_ea;
				}else if	(current_page == 'sorder'){
					remain_ea					= '<center>-</center>';
					if	(row.default_sorder_info){
						supply_price			= row.default_sorder_info.supply_price;
						use_tax					= (row.default_sorder_info.use_supply_tax == 'Y') ? 'checked' : 'unchecked';
					}
				}
			}

			// 매입용 상품명이 있는 경우에는 함께 노출함
			if (row.supply_goods_name) {
				row.goods_name += " / " + row.supply_goods_name;
			}

			// excel table에 채울 데이터 목록
			var tmp						= [	option, goods_info_str, goods_image,
											row.goods_name, row.option_name];
			if	(current_page == 'stockmove'){
				var sum_supply_price	= supply_price * ea;
				if	(excelTableOption.viewMode){
					var out_bf_stock	= row.out_bf_stock + '(' + row.out_bf_badstock + ')';
					var out_af_stock	= '<span class="location_ea resp_btn_txt v2" '
										+ 'onclick="openGoodsWarehouseStock(\'scmWarehouseStock\', '
										+ '\'\', \'' + row.goods_seq + '\', \'' + row.option_type + '\', '
										+ '\'' + row.option_seq + '\');">' + comma(row.out_af_stock)
										+ '(' + comma(row.out_af_badstock) + ')</span>';
					var in_bf_stock		= row.in_bf_stock + '(' + row.in_bf_badstock + ')';
					var in_af_stock		= '<span class="location_ea resp_btn_txt v2" '
										+ 'onclick="openGoodsWarehouseStock(\'scmWarehouseStock\', '
										+ '\'\', \'' + row.goods_seq + '\', \'' + row.option_type + '\', '
										+ '\'' + row.option_seq + '\');">' + comma(row.in_af_stock)
										+ '(' + comma(row.in_af_badstock) + ')</span>';
				}else{
					var out_bf_stock	= org_ea;
					var out_af_stock	= '-';
					var in_bf_stock		= '<span class="location_ea resp_btn_txt v2" '
										+ 'onclick="openGoodsWarehouseStock(\'scmWarehouseStock\', '
										+ '\'\', \'' + row.goods_seq + '\', \'' + row.option_type + '\', '
										+ '\'' + row.option_seq + '\');">' + comma(row.in_location_ea)
										+ '(' + comma(row.in_location_badea) + ')</span>';
					var in_af_stock		= '-';
				}
				tmp.push(row.out_location_code);
				tmp.push(out_bf_stock);
				tmp.push(out_af_stock);
				tmp.push(ea);
				tmp.push(supply_price);
				tmp.push(sum_supply_price);
				tmp.push(row.in_location_code);
				tmp.push(row.in_location_position);
				tmp.push(in_bf_stock);
				tmp.push(in_af_stock);
				tmp.push(row.goods_seq);
				tmp.push(goods_code);
				tmp.push(row.out_location_position);
				tmp.push(row.supply_goods_name);
				tmp.push(stock);
			}else{
				tmp.push(row.location_code);
				tmp.push(org_ea);
				tmp.push(ea);
				tmp.push('');
				tmp.push(supply_price);

				if	(current_currency == 'KRW'){
					var sum_supply_price	= supply_price * ea;
					tmp.push(sum_supply_price);
					tmp.push(use_tax);
					tmp.push(supply_tax);
				}else{
					tmp.push(row.weight);
					tmp.push('0');
					tmp.push(row.freight_price);
					tmp.push(row.insurance_price);
					tmp.push('0');
					tmp.push(row.duty_price);
					tmp.push(row.accessorial_price);
					tmp.push(row.supply_tax);
				}
				tmp.push(row.goods_seq);
				tmp.push(goods_code);
				tmp.push(row.location_position);
				tmp.push(row.supply_goods_name);
				tmp.push(stock);

				// 후 처리
				if	(current_page == 'sorder'){
					tmp.push(remain_ea);
					tmp.push(add_reason);
				}
			}
			result.push(tmp);
		}
	}

	return result;
}

// 노출용 값 변경
function chgDisplayValue(fld, val, obj){
	switch(fld){
		case 'ea[]':
			val	= comma(val);
		break;
		case 'supply_price[]':
			val	= float_comma(val);
		break;
		case 'supply_tax[]':
			val	= float_comma(val);
		break;
		case 'goods_price[]':
			val	= float_comma(val);
		break;
		case 'freight_price[]':
			val	= float_comma(val);
		break;
		case 'insurance_price[]':
			val	= float_comma(val);
		break;
		case 'cif_price[]':
			val	= float_comma(val);
		break;
		case 'duty_price[]':
			val	= float_comma(val);
		break;
		case 'accessorial_price[]':
			val	= float_comma(val);
		break;
	}

	return val;
}

// 현재 row 데이터 초기화
function resetRowData(obj, exceptType){

	var val				= '';
	var resetStatus		= true;
	var imgPattern		= new RegExp('image');
	var intPattern		= new RegExp('(price|ea)');
	var exceptPattern	= new RegExp('(' + exceptType + ')');

	$(obj).closest('tr').find('td').each(function(){
		val			= '';
		resetStatus	= true;
		if			(imgPattern.test($(this).find('input').attr('name'))){
			val	= common_no_img;
		}else if	(intPattern.test($(this).find('input').attr('name'))){
			val	= '0';
		}

		// 초기화 제외 대상 처리
		if	(exceptType){
			if	(exceptPattern.test($(this).find('input').attr('name'))){
				resetStatus	= false;
			}
		}

		if	(resetStatus){
			if	($(this).find('span'))	$(this).find('span').html(val);
			if	($(this).find('img'))	$(this).find('img').attr('src', val);
			if	($(this).find('input'))	$(this).find('input').html(val);
		}
	});

	calculateExcelTableTotal();
}

// 엑셀 테이블 상품 초기화
function resetExcelTableData(){

	// 재고 이동이 아닌 경우에 인/아웃 창고 다른지 체크
	if (current_page != 'stockmove') {
		differentInOutWhseq();
	}
	$("input[name='option_seq[]']").each(function(){
		$(this).closest('tr').remove();
	});
}

// 입출고 창고가 다른지 체크함
function differentInOutWhseq() {
	if	($('select.out_wh_seq').attr('name') && $('select.in_wh_seq').attr('name')){
		if	($('select.out_wh_seq').val() == $('select.in_wh_seq').val()){
			openDialogAlert('출고창고와 입고창고를 서로 다른 창고로 선택해 주세요.', 400, 170, function(){});
			$('select.out_wh_seq').find('option:selected').attr('selected', false);
			$('select.in_wh_seq').find('option:selected').attr('selected', false);
			$('select.out_wh_seq').find('option').eq(0).attr('selected', 'selected');
			$('select.in_wh_seq').find('option').eq(0).attr('selected', 'selected');
		}
	}
}

// 거래처/창고 변경에 따른 상품 목록 초기화
function resetExcelTableList(){
	resetExcelTableData();
	//excelTableObj.addDefaultRow('', []);
	calculateExcelTableTotal();

	if	(current_page == 'revision'){
		var stockTitle	= chgRevisionStockTitle();
		if	(stockTitle){
			$('span.stockhelp').attr('title', stockTitle);
			area_help_tooltip($('span.stockhelp').parent());
		}
	}
}

// 입력 전 처리가 필요한 영역의 경우
function excelTableAfterInput(obj){
}

// 전체 Row 한줄 한줄에 대해서 재계산
function calculateExcelTableAllRow(){
	$('div#excelTable').find("input[name='option_seq[]']").each(function(){
		if	($(this).val())	calculateExcelTable($(this), 0);
	});
}

// 금액 변경에 따른 금액, 부가세, 합계 계산
function calculateExcelTable(obj, val){
	var goods_seq		= $(obj).closest('tr').find("input[name='goods_seq[]']").val();
	var optioninfo		= $(obj).closest('tr').find("input[name='option_seq[]']").val();
	var tmpArr			= optioninfo.split('|');
	var option_type		= tmpArr[0];
	var option_seq		= tmpArr[1];
	var ea				= $(obj).closest('tr').find("input[name='ea[]']").val();
	var supply_price	= $(obj).closest('tr').find("input[name='supply_price[]']").val();
	var freight_price	= $(obj).closest('tr').find("input[name='freight_price[]']").val();
	var insurance_price	= $(obj).closest('tr').find("input[name='insurance_price[]']").val();
	var chk_tax			= $(obj).closest('tr').find("input[name='tax[]']").attr('checked');	
	var krw_price		= 0;
	var cif_price		= 0;
	var goods_price		= 0;
	var goods_exchange	= $("input[name='goods_exchange']").val();
	var fi_exchange		= $("input[name='fi_exchange']").val();
	var sum_price		= 0;
	var tax_price		= 0;
	apply_cif_set();

	if	(current_currency == 'KRW'){
		sum_price		= supply_price * ea;
		sum_price		= calculate_cut_price(current_currency, sum_price);
		tax_price		= (chk_tax) ? calculate_tax_price('KRW', sum_price) : 0;

		$(obj).closest('tr').find("input[name='sum_supply_price[]']").val(sum_price);
		$(obj).closest('tr').find("input[name='sum_supply_price[]']").closest('td').find('span').html(comma(sum_price));
		$(obj).closest('tr').find("input[name='supply_tax[]']").val(tax_price);
		$(obj).closest('tr').find("input[name='supply_tax[]']").closest('td').find('span').html(comma(tax_price));
	}else{
		goods_price		= supply_price * ea;
		goods_price		= calculate_cut_price(current_currency, goods_price);
		krw_price		= krw_exchange(current_currency, goods_price, goods_exchange);
		freight_price	= krw_exchange(current_currency, freight_price, fi_exchange);
		insurance_price	= krw_exchange(current_currency, insurance_price, fi_exchange);
		cif_price		= parseInt(krw_price) + parseInt(freight_price) + parseInt(insurance_price);
		cif_price		= calculate_cut_price(current_currency, cif_price);

		$(obj).closest('tr').find("input[name='goods_price[]']").val(goods_price);
		$(obj).closest('tr').find("input[name='goods_price[]']").closest('td').find('span').html(float_comma(goods_price));
		$(obj).closest('tr').find("input[name='cif_price[]']").val(cif_price);
		$(obj).closest('tr').find("input[name='cif_price[]']").closest('td').find('span').html(float_comma(cif_price));
	}

	// 발주일때 주문번호 display 변경
	if	(current_page == 'sorder'){
		var aooSeq	= $(obj).closest('tr').find("input[name='aooSeq[]']").val();
		if	(aooSeq.search(/\,/) != -1){
			aooSeq	= aooSeq.replace(/\,/g, '<br/>');
			$(obj).closest('tr').find("input[name='aooSeq[]']").closest('td').find('span').html(aooSeq);
		}
	}

	// 재고 이동일 때
	if (current_page == 'stockmove') {
		//입출고 창고 같은 경우 readonly
		if ($('select.out_wh_seq').val() == $('select.in_wh_seq').val()) {
			$(obj).closest('tr').find("input[name='ea[]']").attr('readonly', true);
		}
	}

	var tmpHTML1			= '<span class="resp_btn_txt v2" onclick="open_lastdata(\'' + goods_seq + '\', \'' + option_seq + '\', \'\');">최신정보</span>';
	$(obj).closest('tr').find('span.btn_lastdata').html(tmpHTML1);

	calculateExcelTableTotal();
}

// 금액 변경에 따른 금액, 부가세, 합계 계산
function calculateExcelTableTotal(){
	var trObj					= '';
	var ea						= 0;
	var total_ea				= 0;
	var total_weight			= 0;
	var total_supply_price		= 0;
	var total_goods				= 0;
	var total_freight			= 0;
	var total_insurance			= 0;
	var total_cif				= 0;
	var total_duty				= 0;
	var total_accessorial		= 0;
	var total_supply_tax		= 0;
	var total_price				= 0;
	var total_account_goods		= 0;
	var total_account_freight	= 0;
	var total_account_insurance	= 0;
	var total_account_price		= 0;

	$('div#excelTable').find("input[name='option_seq[]']").each(function(){
		if	($(this).val()){
			trObj					= $(this).closest('tr');
			ea						= trObj.find("input[name='ea[]']").val();
			total_ea				= parseInt(total_ea) + parseInt(ea);
			total_supply_price		= parseFloat(total_supply_price) + ( ea * trObj.find("input[name='supply_price[]']").val());
			total_weight			= parseFloat(total_weight) + ( ea * trObj.find("input[name='weight[]']").val());
			total_goods				= parseFloat(total_goods) + parseFloat(trObj.find("input[name='goods_price[]']").val());
			total_freight			= parseFloat(total_freight) + parseFloat(trObj.find("input[name='freight_price[]']").val());
			total_insurance			= parseFloat(total_insurance) + parseFloat(trObj.find("input[name='insurance_price[]']").val());
			total_cif				= parseFloat(total_cif) + parseFloat(trObj.find("input[name='cif_price[]']").val());
			total_duty				= parseFloat(total_duty) + parseFloat(trObj.find("input[name='duty_price[]']").val());
			total_accessorial		= parseFloat(total_accessorial) + parseFloat(trObj.find("input[name='accessorial_price[]']").val());
			total_supply_tax		= parseFloat(total_supply_tax) + parseFloat(trObj.find("input[name='supply_tax[]']").val());
		}
	});
	if	(current_currency == 'KRW'){
		total_price	= parseFloat(total_supply_price) + parseFloat(total_supply_tax);
	}else{
		total_supply_price	= parseFloat(total_cif) + parseFloat(total_duty) + parseFloat(total_accessorial);
		total_price			= parseFloat(total_supply_price) + parseFloat(total_supply_tax);
	}

	total_account_goods				= total_goods;
	switch(current_trade_terms){
		case 'FOB':
			total_account_freight	= 0;
			total_account_insurance	= 0;
		break;
		case 'CFR':
			total_account_freight	= total_freight;
			total_account_insurance	= 0;
		break;
		case 'CIN':
			total_account_freight	= 0;
			total_account_insurance	= total_insurance;
		break;
		default:
		case 'CIF':
			total_account_freight	= total_freight;
			total_account_insurance	= total_insurance;
		break;
	}
	total_account_price				= parseFloat(total_account_goods) + parseFloat(total_account_freight) + parseFloat(total_account_insurance);

	// 절사 설정에 따른 절사 처리
	total_supply_price		= calculate_cut_price(current_currency, total_supply_price);
	total_goods				= calculate_cut_price(current_currency, total_goods);
	total_freight			= calculate_cut_price(current_currency, total_freight);
	total_insurance			= calculate_cut_price(current_currency, total_insurance);
	total_cif				= calculate_cut_price(current_currency, total_cif);
	total_duty				= calculate_cut_price(current_currency, total_duty);
	total_accessorial		= calculate_cut_price(current_currency, total_accessorial);
	total_supply_tax		= calculate_cut_price(current_currency, total_supply_tax);
	total_account_goods		= calculate_cut_price(current_currency, total_account_goods);
	total_account_freight	= calculate_cut_price(current_currency, total_account_freight);
	total_account_insurance	= calculate_cut_price(current_currency, total_account_insurance);
	total_account_price		= calculate_cut_price(current_currency, total_account_price);
	total_price				= calculate_cut_price(current_currency, total_price);

	// total값 입력
	$('div#excelTotalTable').find('.total-ea').html(comma(total_ea));
	$('div#excelTotalTable').find('.total-weight').html(total_weight);
	$('div#excelTotalTable').find('.total-supply').html(float_comma(total_supply_price));
	$('div#excelTotalTable').find('.total-tax').html(float_comma(total_supply_tax));
	$('div#excelTotalTable').find('.total-goods').html(float_comma(total_goods));
	$('div#excelTotalTable').find('.total-freight').html(float_comma(total_freight));
	$('div#excelTotalTable').find('.total-insurance').html(float_comma(total_insurance));
	$('div#excelTotalTable').find('.total-cif').html(float_comma(total_cif));
	$('div#excelTotalTable').find('.total-duty').html(float_comma(total_duty));
	$('div#excelTotalTable').find('.total-accessorial').html(float_comma(total_accessorial));
	$('div#excelTotalTable').find('.total-account-goods').html(float_comma(total_account_goods));
	$('div#excelTotalTable').find('.total-account-freight').html(float_comma(total_account_freight));
	$('div#excelTotalTable').find('.total-account-insurance').html(float_comma(total_account_insurance));
	$('div#excelTotalTable').find('.total-account-price').html(float_comma(total_account_price));
	$('div#excelTotalTable').find('.total-price').html(float_comma(total_price));
}

// 일괄 변경
function batch_all_price(obj, markingClass) {
	if (current_page ==='stockmove' && markingClass == 'ea') {
		if ($('select.out_wh_seq').val() == $('select.in_wh_seq').val()) {
			openDialogAlert('같은 창고 내 재고 이동은 보유 수량 전체가 이동됩니다.', 400, 170, function(){});
			return false;
		}
	}
	var val		= $(obj).closest('div').find('input.excel_table_batch_val').val();
	$('div#excelTable').find('td.' + markingClass).each(function(){
		if	($(this).closest('tr').find("input[name='option_seq[]']").val()){
			$(this).find('input').val(val);
			$(this).find('span').html(float_comma(val));
			calculateExcelTable($(this), '');
		}
	});
}

// 상품 검색 팝업
function selectGoodsPopup(type){

	var chkVal		= chkBeforeSelectGoodsOptionData('');
	if	(!chkVal['status'])	return false;

	var params		= chkVal['params'];
	if		(current_page == 'sorder')
		params	+= (params) ? '&trader_seq=' + chkVal['trader_seq'] : 'trader_seq=' + chkVal['trader_seq'];
	else if	(current_page == 'revision')
		params	+= (params) ? '&revisionType=' + $("input[name='revision_type']:checked").val() : 'revisionType=' + $("input[name='revision_type']:checked").val();

	selectGoodsPopupContents('1', params, type, '');

	openDialog('상품 검색', 'selectGoodsPopup', {'width':900,'height':800});
}

// 상품 검색 팝업 contents
function selectGoodsPopupContents(page, params, userParam1, userParam2){

	if	(params)		params	+= '&page=' + page;
	else				params	= 'page=' + page;
	params	+= '&currency=' + current_currency;
	params	+= '&curpage=' + current_page;
	if	(userParam1)	params += '&userParam1=' + userParam1;
	if	(userParam2)	params += '&userParam2=' + userParam2;
	params	+= '&pagedisplay=ajax&moveUserFunc=selectGoodsPopupContents&selectUserFunc=selectedPopupGoods';

	$.ajax({
		type	: 'get',
		url		: '../scm/select_goods_popup',
		data	: params,
		success	: function(result){
			$('div#' + 'selectGoodsPopup').html(result);
		}
	});
}

// 상품 검색 팝업 상품 선택 처리
function selectedPopupGoods(jsonData, userParam1, userParam2){
	var result	= [];
	result.push(jsonData);

	var excelResult		= goodsDataArrayValues(result);
	var chkDuple		= excelTableObj.chkDuplicateRow('option_seq[]', excelResult[0][0]);
	if	(chkDuple){
		openDialogAlert('중복된 상품입니다.', 400, 170, function(){});
		return false;
	}
	excelTableObj.delDefaultRow('option_seq[]');
	var trObj	= excelTableObj.addDefaultRow('datas', excelResult[0]);
	//excelTableObj.addDefaultRow('', []);

	// 상품명 display에 매입용 상품명 추가
	if	(jsonData.supply_goods_name){
		trObj.find("input[name='goods_name[]']").closest('td').find('span').append('/' + jsonData.supply_goods_name);
	}

	calculateExcelTable(trObj.find("input[name='option_seq[]']"), '');
	area_help_tooltip(trObj);
}

// 바코드 스캔 상품 데이터 입력 2016.05.24 pjw
function scanBarcodeData(type){
	var barcode_reader	= $('input[name="barcode_reader"]').val();
	if(barcode_reader == ''){
		openDialogAlert('바코드를 입력해 주세요.', 400, 170, function(){});
		return false;
	}

	var chkVal		= chkBeforeSelectGoodsOptionData('');
	if	(!chkVal['status'])	return false;

	var params		= chkVal['params'];
	if	(type)	params += '&userParam1=' + type;
	params			+= '&barcode_reader='+barcode_reader;
	if	(current_page == 'sorder')
		params	+= (params) ? '&trader_seq=' + chkVal['trader_seq'] : 'trader_seq=' + chkVal['trader_seq'];

	$.ajax({
		type	: 'get',
		url		: '../scm/get_goods_data_barcode',
		data	: params,
		async	: false,
		success	: function(result){
			if(result && result != 'null'){
				var jsonObj = JSON.parse(result);

				for(var i=0; i<jsonObj.length; i++){
					pushBarcodeData(JSON.parse(jsonObj[i]), type);	
				}
			}else{
				openDialogAlert('해당 창고에 바코드 상품이 없습니다.', 400, 170, function(){});
				return false;
			}
		}
	});
}

// 상품 검색 팝업 상품 선택 처리
function pushBarcodeData(jsonData, userParam1, addCondition){
	var result	= [];
	result.push(jsonData);

	var excelResult		= goodsDataArrayValues(result);
	var chkDuple		= excelTableObj.chkDuplicateRow('option_seq[]', excelResult[0][0]);
	if	(chkDuple){
		// 수량 증가
		var trObj		= $(excelTableObj).find("input[name='option_seq[]'][value='" + excelResult[0][0] + "']").closest('tr');
		var ea			= trObj.find("input[name='ea[]']").val();
		ea++;
		trObj.find("input[name='ea[]']").val(ea);
		trObj.find("input[name='ea[]']").closest('td').find('span').html(comma(ea));
	}else{
		// 상품 추가
		excelTableObj.delDefaultRow('option_seq[]');
		var trObj	= excelTableObj.addDefaultRow('datas', excelResult[0]);
		excelTableObj.addDefaultRow('', []);

		// 상품명 display에 매입용 상품명 추가
		if	(jsonData.supply_goods_name){
			trObj.find("input[name='goods_name[]']").closest('td').find('span').append('/' + jsonData.supply_goods_name);
		}
	}

	calculateExcelTable(trObj.find("input[name='option_seq[]']"), '');
	area_help_tooltip(trObj);
}

// 선택된 상품 row 삭제
function delGoodsRow(){
	$('div#excelTable').find("input[name='option_seq[]']").each(function(){
		if	($(this).attr('checked'))	$(this).closest('tr').remove();
	});
	// 모두 삭제 시 빈 Row 추가

	if	(!($('div#excelTable').find("input[name='option_seq[]'][value='']").length > 0) && $('div#excelTable').find(".noMess").length<1){
		excelTableObj.addDefaultRow('', []);
	}

	calculateExcelTableTotal();
}

// page submit 시 excel table 관련 선처리
function excelTableSubmit(){
	var tax	= '';
	var cnt	= 0;
	$('div#excelTable').find("input[name='option_seq[]']").each(function(){
		if	($(this).val()){
			$(this).attr('checked', true);
			tax	= 'N';
			if	($(this).closest('tr').find("input[name='tax[]']").attr('checked'))	tax	= 'Y';

			if	($(this).closest('tr').find("input[name='hide_tax[]']").attr('name')){
				$(this).closest('tr').find("input[name='hide_tax[]']").val(tax);
			}else{
				$(this).closest('tr').find("input[name='tax[]']").closest('td').append('<input type="hidden" name="hide_tax[]" orgTagName="tax" value="' + tax + '" />');
			}
			cnt++;
		}else{
			$(this).attr('checked', false);
		}
	});

	if	(cnt > 0){
		scmSubmit();
	}else{
		openDialogAlert('선택된 상품이 없습니다.', 400, 170, function(){});
		return false;
	}
}

// 최근 발주/입고/매입정보 오픈
function open_lastdata(goods_seq, option_seq, limit){
	if	(!limit)	limit		= 5;
	if	(!$('div#lastdata_lay').attr('id')){
		$('body').append('<div id="lastdata_lay"></div>');
	}
	$('div#lastdata_lay').html('');
	get_lastdata('warehousing', goods_seq, option_seq, limit, '', '');

	openDialog('최신 정보', 'lastdata_lay', {'width':850,'height':500});
}

// 최근 발주/입고/매입정보 추출
function get_lastdata(list_type, goods_seq, option_seq, limit, trader_group, trader_seq){
	var params	= 'list_type=' + list_type + '&goods_seq=' + goods_seq + '&option_seq=' + option_seq
				+ '&currency=' + current_currency + '&limit=' + limit 
				+ '&trader_group=' + trader_group + '&trader_seq=' + trader_seq;
	$.ajax({
		type	: 'get',
		url		: '../scm/get_lastdata',
		data	: params,
		success	: function(result){
			$('div#lastdata_lay').html(result);
		}
	});
}

// 선택한 금액 적용
function set_lastdata(option_seq, supply_price){
	var supplyObj	= $("input[name='option_seq[]'][value='option|" + option_seq + "']").closest('tr').find("input[name='supply_price[]']");
	supplyObj.closest('td').find('span').html(float_comma(supply_price));
	supplyObj.val(supply_price);
	calculateExcelTable(supplyObj, supplyObj.val());
}

// 단가에서 단가와 부가세 분리
function calculate_price_divide_tax(){
	var supply_price		= 0;
	$('div#excelTable').find("input[name='option_seq[]']").each(function(){
		if	($(this).val()){
			supply_price	= Math.round($(this).closest('tr').find("input[name='supply_price[]']").val() / 1.1);
			$(this).closest('tr').find("input[name='supply_price[]']").val(supply_price);
			$(this).closest('tr').find("input[name='supply_price[]']").closest('td').find('span').html(comma(supply_price));
			calculateExcelTable($(this).closest('tr').find("input[name='supply_price[]']"), supply_price);
		}
	});
}

// 원가배부 팝업 오픈
function open_cost_application(){
	if	(!$('div#costApplicationLay').html()){
		$.ajax({
			type	: 'get',
			url		: '../scm/cost_application_popup',
			data	: 'currency=' + current_currency, 
			success	: function(result){
				$('div#costApplicationLay').html(result);
			}
		});
	}
	openDialog('기타 비용 배부', 'costApplicationLay', {'width':1000, 'height':500});
}

// 원가배부 실행
function exec_cost_application(obj){
	var standard			= $(obj).closest('tr').find('input.standard:checked').val();
	var target				= $(obj).closest('tr').find('input.cost_target').val();
	var input_total_cost	= $(obj).closest('tr').find("input[name='input_total_cost']").val();
	var totals				= {	'goods'		: $('.total-goods').eq(0).text().replace(/\,/g, ''),
								'cif'		: $('.total-cif').eq(0).text().replace(/\,/g, ''),
								'ea'		: $('.total-ea').eq(0).text().replace(/\,/g, ''),
								'weight'	: $('.total-weight').eq(0).text().replace(/\,/g, '')	};
	var trObj				= '';
	var unit				= 0;
	var cost				= 0;
	$('div#excelTable').find("input[name='option_seq[]']").each(function(){
		if	($(this).val()){
			trObj	= $(this).closest('tr');
			if	(standard == 'goods' || standard == 'cif')
				unit		= trObj.find("input[name='" + standard + "_price[]']").val();
			else
				unit		= trObj.find("input[name='" + standard + "[]']").val();
			if	(standard == 'weight')	unit	= unit * trObj.find("input[name='ea[]']").val();
			cost	= Math.round(((unit / totals[standard]) * input_total_cost), 2);
			trObj.find("input[name='" + target + "_price[]']").closest('td').find('span').html(float_comma(cost));
			trObj.find("input[name='" + target + "_price[]']").val(cost);
			calculateExcelTable(trObj.find("input[name='" + target + "_price[]']"), cost);
		}
	});
}

//------------ ↑↑ excel table ↑↑------ ↓↓ config ↓↓--------------//

// datepicker disable 
function selectDatePicker(obj, inst){	
	if	($(obj).closest('td').find('input.use_default_revision_date').attr('checked'))	return true;
	else																				return false;
}

// input disable
function chk_open_date(obj){
	if	($(obj).attr('checked')){
		$(obj).closest('td').find('input.datepicker').css('background-color', '#ffffff');
	}else{
		$(obj).closest('td').find('input.datepicker').css('background-color', '#ececec');
	}
}

// 설정 저장 처리
function configSubmit(){
	var dateObj			= new Date();
	var year			= dateObj.getFullYear();
	var month			= dateObj.getMonth() + 1;
	if	(month < 10)	month	= '0' + month;
	var day				= dateObj.getDate();
	if	(day < 10)		day		= '0' + day;
	var toDate			= year + '' + month + '' + day;
	var toDate_view		= year + '-' + month + '-' + day;
	var tmpDate			= '';
	var confirmMsg		= '';
	var chk_status		= true;

	$('input.currency_exchange').each(function(){
		if	(!$(this).val() || !($(this).val() > 0) || $(this).val().search(/[^0-9\.]/) != -1){
			var that	= this;
			openDialogAlert('환율정보를 입력하세요', 400, 170, function(){
				$(that).focus();
			});
			chk_status	= false;
			return false;
		}
	});
	if	(!chk_status)	return false;

	if	($("input[name='use_scm_setting_default_date']").attr('checked')){
		if	(!confirmMsg)	confirmMsg	= '기준일자는 설정 후 수정이 불가합니다.<br/>';
		tmpDate		= $("input[name='scm_setting_default_date']").val().replace(/\-/g, '');
		if			(!$("input[name='scm_setting_default_date']").val()){
			openDialogAlert('기초재고 기준일자를 입력해 주세요', 400, 170, function(){});
			return false;
		}else if	(tmpDate >= toDate){
			openDialogAlert('기초재고 기준일자를<br/>오늘(' + toDate_view + ') 이전날짜로 입력해 주세요', 400, 170, function(){});
			return false;
		}
		confirmMsg	+= '<br/>지정한 기초재고 기준일자는 <br/> ' + $("input[name='scm_setting_default_date']").val() + ' 입니다.<br/>';
	}
	if	($("input[name='use_scm_setting_account_date']").attr('checked')){
		if	(!confirmMsg)	confirmMsg	= '기준일자는 설정 후 수정이 불가합니다.<br/>';
		tmpDate	= $("input[name='scm_setting_account_date']").val().replace(/\-/g, '');
		if			(!$("input[name='scm_setting_account_date']").val()){
			openDialogAlert('미지급잔액 기준일자를 입력해 주세요', 400, 170, function(){});
			return false;
		}else if	(tmpDate >= toDate){
			openDialogAlert('미지급잔액 기준일자를<br/>오늘(' + toDate_view + ') 이전날짜로 입력해 주세요', 400, 170, function(){});
			return false;
		}
		confirmMsg	+= '<br/>지정한 미지급잔액 기준일자는 <br/> ' + $("input[name='scm_setting_account_date']").val() + ' 입니다.<br/>';
	}

	if	(confirmMsg){
		confirmMsg	+= '기준일자를 설정하시겠습니까?';
		openDialogConfirm(confirmMsg, 400, 300, function(){
			$("input[name='scm_setting_default_date']").attr('disabled', false);
			$("input[name='scm_setting_account_date']").attr('disabled', false);
			scmSubmit();
		}, function(){
			return false;
		});
	}else{
		scmSubmit();
	}
}

// 환율정보 개발사 기본값으로 반영 ( 공지 없이 변경될 수 있습니다. )
function set_default_exchange(){
	$.getJSON('../scm/default_exchange_set', function(result){
		if	(result){
			var cfg				= '';
			var lower_currency	= '';
			var price			= 0;
			for	( var currency in result){
				cfg				= result[currency];
				if	(currency == 'JPY')	price	= cfg.price * 100;
				else					price	= cfg.price;
				lower_currency	= currency.toLowerCase();
				$("select[name='exchange[" + lower_currency + "_cut_unit]']").find('option:selected').attr('selected', false);
				$("select[name='exchange[" + lower_currency + "_cut_unit]']").find("option[value='" + cfg.unit + "']").attr('selected', true);
				$("select[name='exchange[" + lower_currency + "_cut_type]']").find('option:selected').attr('selected', false);
				$("select[name='exchange[" + lower_currency + "_cut_type]']").find("option[value='" + cfg.type + "']").attr('selected', true);
				
			}
		}
	});
}

function set_default_currency(){
	$.getJSON('../scm/default_exchange_set', function(result){		
		if	(result){
			var cfg				= '';
			var lower_currency	= '';
			var price			= 0;
			for	( var currency in result){
				cfg				= result[currency];
				if	(currency == 'JPY')
				{
					price	= cfg.price * 100;
					console.log(price);
				}else{
					price	= cfg.price;
						
					}
				lower_currency	= currency.toLowerCase();
				
				$("input[name='exchange[" + lower_currency + "_currency_exchange]']").val(price);
			}
		}
	});
}

//------------ ↑↑ config ↑↑------ ↓↓ store ↓↓--------------//

// 매입기준정보 설정 전체 open/close
function allChkStoreWarehouse(obj){
	var chkStatus	= $(obj).attr('checked');
	$("input[name='chk_wh[]']").each(function(){
		if	(chkStatus) $(this).attr('checked', true);
		else			$(this).attr('checked', false);
	});
}

//------------ ↑↑ store ↑↑------ ↓↓ traders ↓↓--------------//

// 거래처 아이디 중복 체크
function chkTraderId(){

	var trader_id	= $("input[name='trader_id']").val();
	if	(!trader_id){
		openDialogAlert('아이디를 입력해 주세요.', 400, 150, function(){});
		return false;
	}
	$.ajax({
		type	: 'post',
		url		: '../scm_process/chk_duplication_trader_id',
		data	: 'trader_id=' + trader_id,
		success	: function(result){
			if	(result > 0){
				openDialogAlert('사용 가능한 아이디입니다.', 400, 170, function(){});
			}else{
				openDialogAlert('중복된 아이디입니다.', 400, 170, function(){$("input[name='trader_id']").focus();});
			}
		}
	});

	return false;
}

// 거래처 정보 저장
function submitTrader(){

	var that			= '';
	var submit_status	= true;
	var trader_seq		= $("input[name='trader_seq']").val();

	// 필수값 체크
	$("form[name='detailForm']").find('input,select,textarea').each(function(){
		if	($(this).attr('isrequired') && !$(this).val()){
			that	= this;
			openDialogAlert($(this).attr('isrequired') + '을(를) 입력해 주세요.', 400, 170, function (){$(that).focus();});
			submit_status	= false;
			return false;
		}
	});

	// 최초 등록 시 확인 창
	if			(submit_status && !trader_seq){
		var dialog_height	= 280;
		var currency_unit	= $("select[name='currency_unit']").val();
		var confirm_msg		= '거래처 등록 후 거래/정산 통화는 수정할 수 없습니다.<br/>'
							+ '해당 거래처의 거래/정산 통화는 ' + currency_unit + '입니다.<br/><br/>';
		if	($("input[name='use_trader_account']:checked").val()=="Y"){
			var act_price	= $("input[name='act_price']").val();
			if	(act_price && ( act_price > 0 || act_price < 0) ){
				dialog_height	= parseInt(dialog_height) + 60;
				confirm_msg		+= '거래처 등록 후 미지급잔액은 수정할 수 없습니다.<br/>'
								+ '해당 거래처의 미지급잔액은 ' + act_price + ' ' + currency_unit + '입니다.<br/><br/>';
			}else{
				openDialogAlert('미지급잔액을 입력하세요.', 400, 170, function(){
					$("input[name='act_price']").focus();
				});
				submit_status	= false;
			}
		}
		confirm_msg	+= '거래처를 생성하시겠습니까?';

		if	(submit_status){
			openDialogConfirm(confirm_msg, 500, dialog_height, function(){scmSubmit();}, function(){});
		}
	}else if	(submit_status){
		scmSubmit();
	}
}

// 거래처분류 직접입력 선택 시 form변경
function chgTraderGroup(obj){
	if	($(obj).val() == 'direct'){
		$(obj).closest('td').find('span').show();
		$("input[name='trader_group']").val('');
	}else{
		$(obj).closest('td').find('span').hide();
		$("input[name='trader_group']").val($(obj).val());
	}
}

// 비밀번호 변경 폼 open/close
function chgTraderPasswd(){
	if	($('input.chkPasswdModify').attr('checked')){
		$('div.chgPasswdLay').show();
		$('div.chgPasswdLay').find('input').attr('disabled', false);
		$('div.chgPasswdLay').find("input[name='manager_pw']").attr('isrequired', '현재 비밀번호');
		$('div.chgPasswdLay').find("input[name='trader_pw']").attr('isrequired', '비밀번호 변경');
		$('div.chgPasswdLay').find("input[name='trader_pw_cf']").attr('isrequired', '비밀번호 변경 확인');
	}else{
		$('div.chgPasswdLay').hide();
		$('div.chgPasswdLay').find('input').attr('disabled', true);
		$('div.chgPasswdLay').find("input[name='manager_pw']").removeAttr('isrequired');
		$('div.chgPasswdLay').find("input[name='trader_pw']").removeAttr('isrequired');
		$('div.chgPasswdLay').find("input[name='trader_pw_cf']").removeAttr('isrequired');
	}
}

// 비밀번호 유효성 체크
function chkPasswdRules(obj){
	var passwd	= $(obj).val();

	// 자릿수 체크
	if	(passwd.length < 10){
		openDialogAlert('비밀번호는 10자 이상 입력해 주시기 바랍니다.', 400, 170, function(){});
		return false;
	}
	if	(passwd.length > 20){
		openDialogAlert('비밀번호는 20자 이하로 입력해 주시기 바랍니다.', 400, 170, function(){});
		return false;
	}
	// 문자열 혼합 체크
	var mixCnt	= 0;
	if	(passwd.search(/[0-9]/) != -1)							mixCnt++;
	if	(passwd.search(/[a-zA-Z]/) != -1)						mixCnt++;
	if	(passwd.search(/[^0-9a-zA-Zㄱ-ㅎ가-힣ㅏ-ㅣ]/) != -1)		mixCnt++;
	if	(!(mixCnt > 1)){
		openDialogAlert('비밀번호는 영문 대소문자, 숫자, 특수문자 중 2가지 이상의 조합으로 입력해 주세요.', 600, 150, function(){});
		return false;
	}
	// 허용 문자열 체크
	if	(passwd.search(/[^0-9a-zA-Z\!\#\$\%\&\(\)\*\+\-\/\:\<\=\>\?\@\[\＼\]\^\_\{\|\}\~]/) != -1){
		openDialogAlert('허용되지 않는 문자가 있습니다.', 400, 170, function(){});
		return false;
	}

	return true;
}

// 거래처 기초 정산 사용 체크
function useTraderAccount(obj){
	if	($(obj).val()=="Y"){
		if	(!$(obj).closest('tbody').find('input.set_account_date').val()){
			openDialogAlert('먼저 미지급잔액 기준일자를 설정하세요.', 400, 180, function(){});
			$(obj).attr('checked', false);
			return false;
		}else{
			$(obj).closest('td').find("input[name='act_price']").attr('disabled', false);
			$(obj).closest('td').find("button").attr('disabled', false);
		}
	}else{
		$(obj).closest('td').find("input[name='act_price']").attr('disabled', true);
		$(obj).closest('td').find("button").attr('disabled', true);
	}
}

// 거래처 거래/정산 통화 변경에 따른 처리
function chg_trager_currency_unit(obj){
	var currency		= $(obj).val();
	var currency_name	= '';
	if	(!currency){
		$('span.description').html('');
		$("input[name='use_trader_account']").attr('checked', false);
		useTraderAccount($("input[name='use_trader_account']"));
	}else{
		currency_name	= $(obj).find('option:selected').attr('cname');
		$('span.description').html(currency + ' (' + currency_name + ')');
	}
}

//------------ ↑↑ traders ↑↑------ ↓↓ warehouse ↓↓--------------//

// 창고 정보 저장
function submitWarehouse(){

	var that			= '';
	var submit_status	= true;

	// 필수값 체크
	$("form[name='detailForm']").find('input,select,textarea').each(function(){
		if	($(this).attr('isrequired') && !$(this).val()){
			that	= this;
			openDialogAlert($(this).attr('isrequired') + '을(를) 입력해 주세요.', 400, 170, function(){$(that).focus();});
			submit_status	= false;
		}
	});

	if	(submit_status){
		scmSubmit();
	}
}

// 창고분류 직접입력 선택 시 form변경
function chgWarehouseGroup(obj){
	if	($(obj).val() == 'direct'){
		$(obj).closest('td').find('input').show();
		$("input[name='wh_group']").val('');
	}else{
		$(obj).closest('td').find('input').hide();
		$("input[name='wh_group']").val($(obj).val());
	}
}

// 로케이션 설정 팝업 오픈
function openLocation(){
	var title	= '로케이션 생성';
	openDialog(title, 'set_location_lay', {'width':700});
}

// 숫자에 해당하는 영문진수값 반환
function getAlpharToNum(num){
	var apArr	= new Array('a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'j', 'k', 'l',
							'm', 'n', 'o', 'p', 'q', 'r', 's', 't', 'u', 'v', 'w', 'x', 
							'y', 'z');
	var apMax	= apArr.length;
	var result	= '';
	var r		= 0;
	var k		= 0;
	while ( num > apMax ){
		r		= num % apMax;
		num		= Math.floor(num / apMax);
		k		= r - 1;
		result	= apArr[k] + result;
	}
	k		= num - 1;
	result	= apArr[k] + result;

	return result;
}

// 다음 영문진수값 계산
function getNextAlphar(ord){
	var apArr	= new Array('a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'j', 'k', 'l',
							'm', 'n', 'o', 'p', 'q', 'r', 's', 't', 'u', 'v', 'w', 'x', 
							'y', 'z');
	var apMax	= apArr.length;
	var ordLen	= ord.length;
	var ap		= '';
	var num		= 0;
	var upAdd	= 1;
	var result	= '';
	for	( var o = ordLen; o > 0; o--){
		ap		= ord.substring((o-1), o);
		num		= $.inArray(ap, apArr) + 1;
		if	(upAdd > 0){
			num++;
			upAdd	= 0;
		}
		if	(num > apMax){
			upAdd	= 1;
			num	= num - apMax;
		}
		num--;

		result	= apArr[num] + result;
	}
	if	(upAdd > 0){
		result	= 'a' + result;
	}

	return result;
}

// mouseover/mouseout에 따라 div show/hide 처리
function overlayonoff(obj, overlayClass, type){
	if	(type == 'on')	$(obj).find('div.' + overlayClass).show();
	else				$(obj).find('div.' + overlayClass).hide();
}

// 로케이션 박스 UI 노출
function crtLocationBox(){

	$('div#location_lay').show();

	var w			= $("input[name='location_width']").val();
	var l			= $("input[name='location_length']").val();
	var h			= $("input[name='location_height']").val();
	var wt			= $("select[name='location_width_type']").val();
	var lt			= $("select[name='location_length_type']").val();
	var ht			= $("select[name='location_height_type']").val();
	var thead		= $('div#location_lay').find('thead.location-draw-lay');
	var tbody		= $('div#location_lay').find('tbody.location-draw-lay');
	var trObj		= '';
	var tdObj		= '';
	var ws			= '';
	var ls			= '';
	var hs			= '';
	var code		= '';
	var position	= '';

	if			( !(w > 0 && l > 0 && h > 0) ){
		openDialogAlert('가로, 세로, 높이는 최소 1이상이어야 합니다.', 400, 170, function(){});
		return false;
	}else if	(w > 100 || l > 100 || h > 50){
		openDialogAlert('가로 100, 세로 100, 높이 50이 최대입니다.', 400, 170, function(){});
		return false;
	}

	var tmpHTML		= '';

	// 가로 타이틀 생성
	thead.find('th.th-title-width').attr('colspan', w);
	thead.find('.tr-title-width').find('th').remove();

	for	(var iw = 1; iw <= w; iw++){
		tmpHTML		= '<th class="its-th-align center">' + iw + '</th>';
		thead.find('.tr-title-width').append(tmpHTML);
	}

	// 새로 생성
	tbody.html('');
	for	( var il = 1; il <= l; il++){
		if		(lt == 'A')	ls	= getAlpharToNum(il).toUpperCase();
		else if	(lt == 'a')	ls	= getAlpharToNum(il);
		else				ls	= il;

		tmpHTML		= '<tr>';
		// 새로 타이틀 생성
		if	(il == 1)	tmpHTML		+= '<th class="its-th-align center" rowspan="' + l + '">세로</th>';

		tmpHTML		+= '<th class="its-th-align center">' + il + '</th>';
		for	(var iw = 1; iw <= w; iw++){
			if		(wt == 'A')	ws	= getAlpharToNum(iw).toUpperCase();
			else if	(wt == 'a')	ws	= getAlpharToNum(iw);
			else				ws	= iw;

			tmpHTML	+= '<td class="its-td-align center">';
			tmpHTML	+= '<div class="location-select-over-lay"></div>';
			tmpHTML	+= '<table width="90%" class="table_basic" align="center" style="margin:0 auto;border-top:1px solid #d7d7d7;">';
			tmpHTML	+= '<col width="60%" /><col />';
			tmpHTML	+= '<thead>';
			tmpHTML	+= '<tr>';
			tmpHTML	+= '<th class="its-th-align center">가로-세로</th>';
			tmpHTML	+= '<th class="its-th-align center">높이</th>';
			tmpHTML	+= '</tr>';
			tmpHTML	+= '</thead>';
			tmpHTML	+= '<tbody>';
			for	(var ih = 1; ih <= h; ih++){
				if		(ht == 'A')	hs	= getAlpharToNum(ih).toUpperCase();
				else if	(ht == 'a')	hs	= getAlpharToNum(ih);
				else				hs	= ih;

				tmpHTML	+= '<tr>';
				if	(ih == 1){
					code		= ws + '-' + ls;
					position	= iw + '-' + il;
					tmpHTML		+= '<td class="its-td-align center location-code-wl" code="' + code + '" position="' + position + '" rowspan="' + h + '">' + code + '<br/><span style="color:#005cc4;cursor:pointer;" onclick="getLocationDetail(this);">상품 검색 ></span></td>';
				}
				position	= iw + '-' + il + '-' + ih;
				tmpHTML		+= '<td class="its-td-align center location-code-h" code="' + hs + '"  position="' + position + '">' + hs + '</td>';
				tmpHTML		+= '</tr>';
			}
			tmpHTML	+= '</tbody>';
			tmpHTML	+= '</table>';
			tmpHTML	+= '</td>';
		}
		tmpHTML	+= '</tr>';
		tbody.append(tmpHTML);
	}
}

// 로케이션 정보 적용
function locationApply(){
	var w			= $('#set_location_lay').find('input.set_location_width').val();
	var l			= $('#set_location_lay').find('input.set_location_length').val();
	var h			= $('#set_location_lay').find('input.set_location_height').val();
	var wt			= $('#set_location_lay').find('select.set_location_width_type').find('option:selected').val();
	var lt			= $('#set_location_lay').find('select.set_location_length_type').find('option:selected').val();
	var ht			= $('#set_location_lay').find('select.set_location_height_type').find('option:selected').val();

	// 로케이션 구조 저굥
	var loc_struct	= '( 가로 ' + w + ' X 세로 ' + l + ' X 높이 ' + h + ' )';
	$('span.location-config-lay').html(loc_struct);
	$("input[name='location_width']").val(w);
	$("input[name='location_length']").val(l);
	$("input[name='location_height']").val(h);

	// 로케이션 레이블 적용
	$("select[name='location_width_type']").find("option[value='" + wt + "']").attr('selected', true);
	$("select[name='location_length_type']").find("option[value='" + lt + "']").attr('selected', true);
	$("select[name='location_height_type']").find("option[value='" + ht + "']").attr('selected', true);

	crtLocationBox();

	closeDialog('set_location_lay');
}

// 로케이션 상세 목록 ( 높이를 포함한 상품 매칭 목록 )
function getLocationDetail(req){
	if	(typeof(req) != 'string'){
		code	= jsTrim($(req).find('td.location-code-wl').text());
		if	(!code){
			code	= jsTrim($(req).closest('td').find('span.location-code-wl').text());
		}
	}else{
		code	= req;
	}

	$('input.src_location_code').val(code);

	ajaxSubmitLocationSearch(1);

	openDialog('창고별 상품검색', 'location_detail_lay', {'width':900,'height':550});
}

// 로케이션 상품 검색
function ajaxSubmitLocationSearch(current_page){

	var wh_seq	= $("select[name='src_wh_seq']").val();
	if	($('input.src_location_code').val() == $('input.src_location_code').attr('title'))	$('input.src_location_code').val('');
	if	($('input.src_goods_name').val() == $('input.src_goods_name').attr('title'))		$('input.src_goods_name').val('');

	var params		= 'src_wh_seq=' + $("select[name='src_wh_seq']").val()
					+ '&src_location_code=' + $('input.src_location_code').val()
					+ '&src_goods_name=' + encodeURIComponent($('input.src_goods_name').val())
					+ '&page=' + current_page;
	$.ajax({
		type		: 'get',
		url			: '../scm/get_location_goods',
		data		: params,
		dataType	: 'json', 
		success		: function(result){
			if	(result.data){
				// 목록 HTML 생성
				var datas			= '';
				var sumPrice		= 0;
				var ea				= 0;
				var supply_price	= 0;
				var listCnt			= result.data.length;
				$('#location_detail_lay').find('tbody.location-detail-list').html('');
				for	( var i = 0; i < listCnt; i++){
					datas		= result.data[i];

					if		(datas.location_supply_price > 0)	supply_price	= datas.location_supply_price;
					else if	(datas.supply_price > 0)			supply_price	= datas.supply_price;
					else										supply_price	= '0';
					if		(datas.location_ea > 0)				ea				= datas.location_ea;
					else if	(datas.ea > 0)						ea				= datas.ea;
					else										ea				= '0';
					sumPrice	= float_calculate('multiply', supply_price, ea);
					listHTML	= '<tr>'
								+ '<td class="its-td-align center">' + datas.goods_seq + '</td>'
								+ '<td class="its-td-align center">' + datas.option_seq + '</td>'
								+ '<td class="its-td-align left">' + datas.goods_name + '</td>'
								+ '<td class="its-td-align left">' + datas.option_name + '</td>'
								+ '<td class="its-td-align center">' + datas.location_code + '</td>'
								+ '<td class="its-td-align right">' + ea + '</td>'
								+ '<td class="its-td-align right">' + float_comma(supply_price) + '</td>'
								+ '<td class="its-td-align right">' + float_comma(sumPrice) + '</td>'
								+ '</tr>';
					$('#location_detail_lay').find('tbody.location-detail-list').append(listHTML);
				}

				// js paging HTML 생성
				$('#location_detail_lay').find('div.page-html-lay').html(getPagingHTML(result.page, 'ajaxSubmitLocationSearch'));

			}else{
				$('#location_detail_lay').find('tbody.location-detail-list').html('<tr><td class="its-td-align center" colspan="8">검색된 데이터가 없습니다.</td></tr>');
				$('#location_detail_lay').find('div.page-html-lay').html('');
			}
		}
	});
}

// 적재상품에 대한 정보 노출
function ajaxGetGoodsLocationLinkData(goods_seq){

	if	(goods_seq > 0){
		var params		= 'goods_seq=' + goods_seq;
		$.ajax({
			type		: 'get',
			url			: '../scm/get_location_goods_option',
			data		: params,
			dataType	: 'json', 
			success		: function(result){
				if	(result.status){

					// 초기화
					$('#goods_option_detail_lay').find('.option_list_thead tr').eq(0).find('th').eq(0).attr('colspan', '1');
					var thCnt	= $('#goods_option_detail_lay').find('.option_list_thead tr').eq(1).find('th').length;
					thCnt		= thCnt - 4;
					for	( var t = thCnt; t >= 0; t--){
						$('#goods_option_detail_lay').find('.option_list_thead tr').eq(1).find('th').eq(t).remove();
					}
					$('#goods_option_detail_lay').find('.option_list_tbody').html('');


					// 타이틀
					if	(result.division_title.length > 0){
						var thHTML	= '';
						var depth	= result.division_title.length;
						$('#goods_option_detail_lay').find('.option_list_thead tr').eq(0).find('th').eq(0).attr('colspan', depth);
						for	( var t = 0; t < depth; t++){
							thHTML	+= '<th class="its-th-align center">' + result.division_title[t] + '</th>';
						}
						$('#goods_option_detail_lay').find('.option_list_thead tr').eq(1).prepend(thHTML);
					}


					// 옵션
					var trHTML	= '';
					var optKey	= '';
					var whName	= '';
					var ea		= '';
					var badEa	= '';
					var data	= '';
					var rowSpan	= '';
					for	( var optSeq in result.data){
						data	= result.data[optSeq];
						whName	= (data.wh_name)	? data.wh_name	: '';
						ea		= (data.ea)			? data.ea		: '';
						badEa	= (data.bad_ea)		? data.bad_ea	: '';
						rowSpan	= '';

						if	(data.locdata.length > 1)	rowSpan	= ' rowspan="' + data.locdata.length + '"';


						trHTML	= '<tr>';
						for	( var t = 1; t <= depth; t++){
							optKey	= 'option' + t;
							trHTML	+= '<td class="its-td-align left" ' + rowSpan + '>' + data[optKey] + '</td>';
						}
						trHTML	+= '<td class="its-td-align left">' + whName + '</td>';
						trHTML	+= '<td class="its-td-align right">' + ea + '</td>';
						trHTML	+= '<td class="its-td-align right">' + badEa + '</td>';
						trHTML	+= '</tr>';

						if	(data.locdata.length > 1){
							for	( var l = 1; l < data.locdata.length; l++){
								whName	= (data.locdata[l].wh_name)		? data.locdata[l].wh_name	: '';
								ea		= (data.locdata[l].ea)			? data.locdata[l].ea		: '';
								badEa	= (data.locdata[l].bad_ea)		? data.locdata[l].bad_ea	: '';
								trHTML	+= '<tr>';
								trHTML	+= '<td class="its-td-align left">' + whName + '</td>';
								trHTML	+= '<td class="its-td-align right">' + ea + '</td>';
								trHTML	+= '<td class="its-td-align right">' + badEa + '</td>';
								trHTML	+= '</tr>';
							}
						}

						$('#goods_option_detail_lay').find('.option_list_tbody').append(trHTML);
					}

					openDialog('상품 재고 상세', 'goods_option_detail_lay', {'width':600,'height':400});
				}
			}
		});
	}
}

// 창고 로케이션 선택 팝업 생성
function selectReturnLocation(obj, return_item_seq){
	var scm_wh		= $("select[name='scm_wh']").val();
	var params		= 'wh_seq=' + scm_wh;
	var package_option_code = '';
	package_option_code = $(obj).attr('package_option_code');

	if	($('div#location_select_lay').find('tbody').attr('whSeq') != scm_wh){
		$('div#location_select_lay').find('tbody').attr('retItemSeq', return_item_seq);
		$('div#location_select_lay').find('tbody').attr('whSeq', scm_wh);
		$('div#location_select_lay').find('tbody').html('');
		$.ajax({
			type		: 'post',
			url			: '/scm/get_location_info',
			data		: params,
			dataType	: 'json', 
			success		: function(result){
				if	(result){
					var data	= '';
					var html	= '';
					for (var lPos in result){
						html			+= '<tr class="list-row">';
						for (var wPos in result[lPos]){
							data		= result[lPos][wPos];
							html		+= '<td class="its-td-align center">';
							html		+= data[1].location_w + '-' + data[1].location_l;
							html		+= '-<select class="select-location-position">'
							for (var hPos in data){
								html	+= '<option value="' + data[hPos].location_position + '" code="' + data[hPos].location_code + '">'
										+ data[hPos].location_h
										+ '</option>';
							}
							html		+= '</select>';
							html		+= ' <span style="color:#3399ff;cursor:pointer;" onclick="selectedLocation(this);">선택</span></td>';
						}
						html			+= '<tr>';
					}
					$('div#location_select_lay').find('tbody').html(html);
				}
			}
		});
	}else if	($('div#location_select_lay').find('tbody').attr('retItemSeq') != return_item_seq){
		$('div#location_select_lay').find('tbody').attr('retItemSeq', return_item_seq);
	}

	$('div#location_select_lay').find('tbody').attr('package_option_code', package_option_code);

	openDialog('로케이션 선택', 'location_select_lay', {'width':800,'height':600});
}

// 로케이션 선택에 대한 처리
function selectedLocation(obj){
	var retItemSeq	= $(obj).closest('tbody').attr('retItemSeq');
	var package_option_code	= $(obj).closest('tbody').attr('package_option_code');
	var optObj		= $(obj).closest('td').find('select.select-location-position option:selected');
	var tarObj		= $("input[name='location_position[" + retItemSeq + "]']");
	if( package_option_code ){
		tarObj		= $("input[name='location_position[" + retItemSeq + "][" + package_option_code + "]']");
	}
	tarObj.closest('tr').find('.location-code-title').html(optObj.attr('code'));
	tarObj.closest('tr').find('.location_code_val').val(optObj.attr('code'));
	tarObj.closest('tr').find('.location_position_val').val(optObj.val());

	closeDialog('location_select_lay');
}


//------------ ↑↑ warehouse ↑↑------ ↓↓ defaultinfo ↓↓--------------//

// 상품관리 설정 전체 open/close
function allChkDefaultinfo(obj){
	if	($(obj).attr('checked') ){
		//$(obj).attr('chkType', '2');
		$(obj).closest('span').addClass('black');
		$("input[name='chk_option[]']").each(function(){
			$(this).attr('checked', true);
		});
	}else{
		//$(obj).attr('chkType', '1');
		$(obj).closest('span').removeClass('black');
		$("input[name='chk_option[]']").each(function(){
			$(this).attr('checked', false);
		});
	}
}

// 상품관리 일괄수정
function openDefaultinfoBatch(){
	var chk	= false;
	$("input[name='chk_option[]']").each(function(){
		if	($(this).attr('checked')){
			chk	= true;
			return true;
		}
	});
	if	(!chk){
		openDialogAlert('일괄등록할 옵션을 선택해 주세요', 400, 170, function(){});
		return false;
	}

	// 수정창 초기화
	$('div#defaultinfo_modify').find('tbody.defaultinfo-tbody').html('<tr class="noData"><td colspan="9" class="center">상품을 추가해 주세요.</td></tr>');
	$('div#defaultinfo_modify').find('div.btn-default-batchmode-lay').show();
	$('div#defaultinfo_modify').find('div.btn-default-modifymode-lay').hide();
	$('div#defaultinfo_modify').find("input[name='del_temp_default_seq[]']").remove();

	openDialog('발주 정보 일괄 등록', 'defaultinfo_modify', {'width':1200,'height':700});
}

// 상품관리 수정
var default_global_option_seq	= '';
var default_global_option_type	= '';
function openDefaultinfoModify(optionType, optionSeq){

	// 수정창 초기화
	$('div#defaultinfo_modify').find('tbody.defaultinfo-tbody').html('');
	$('div#defaultinfo_modify').find('div.btn-default-batchmode-lay').hide();
	$('div#defaultinfo_modify').find('div.btn-default-modifymode-lay').show();
	$('div#defaultinfo_modify').find("input[name='del_temp_default_seq[]']").remove();

	// 현재 데이터 수정창에 적용
	var html	= '';
	var idx		= 0;
	default_global_option_seq	= optionSeq;
	default_global_option_type	= optionType;
	var atChk_N = atChk_Y = auto_type = supply_price = supply_type = use_supply_tax = krw_supply_price = '';

	if( $('div#defaultinfo_' + optionType + '_' + optionSeq).find(".noData").length==0)
	{
		$('div#defaultinfo_' + optionType + '_' + optionSeq).find('tbody.defaultinfo-tbody tr').each(function(){
			addDefaultRow($(this));
		});
	} else {
		$('div#defaultinfo_modify').find('tbody.defaultinfo-tbody').html('<tr class="noData"><td colspan="9" class="center">상품을 추가해 주세요.</td></tr>');
	}

	openDialog('발주 정보 수정', 'defaultinfo_modify', {'width':1200,'height':700});
}

// 상품관리 한줄 추가
function addDefaultRow(currentTrObj){
	var modifyLay	= $('div#defaultinfo_modify');
	var idx			= 0;
	var default_seq = option_type = option_seq = use_status = main_trade_type = trader_seq = trader_name = '';
	var currency = supply_goods_name = auto_type = supply_price_type = supply_price = use_supply_tax = '';

	$(".ui-dialog .noData").remove();

	if	(currentTrObj){
		rownum				= currentTrObj.find("input[name='rownum[]']").val();
		default_seq			= currentTrObj.find("input[name='default_seq[]']").val();
		option_type			= currentTrObj.find("input[name='option_type[]']").val();
		option_seq			= currentTrObj.find("input[name='option_seq[]']").val();
		use_status			= currentTrObj.find("input[name='use_status[]']").val();
		main_trade_type		= currentTrObj.find("input[name='main_trade_type[]']").val();
		trader_seq			= currentTrObj.find("input[name='trader_seq[]']").val();
		trader_name			= currentTrObj.find("input[name='trader_name[]']").val();
		currency			= currentTrObj.find("input[name='currency[]']").val();
		supply_goods_name	= currentTrObj.find("input[name='supply_goods_name[]']").val();
		supply_price		= currentTrObj.find("input[name='supply_price[]']").val();
		krw_supply_price	= currentTrObj.find("input[name='krw_supply_price[]']").val();
		use_supply_tax		= currentTrObj.find("input[name='use_supply_tax[]']").val();
	}else{
		// 기존row에 option_type과 option_seq가 있는 경우 추가한다.
		modifyLay.find('input.option_seq').each(function(){
			if	($(this).val() > 0){
				option_seq	= $(this).val();
				option_type	= $(this).closest('tr').find('input.option_type').val();
			}
		});

		// 기본값 정의
		currency		= 'KRW';
		supply_price	= '0';
		use_status		= 'N';
		main_trade_type	= 'N';
		use_supply_tax	= 'Y';
		rownum			= '1';
	}

	html	= '<tr>';
	html	+= '<td class="center hand link-lay" onclick="delSupplyInfoRow(this);">';
	html	+= '<span class="btn_minus"></span>';
	html	+= '<input type="hidden" class="default_seq" value="' + default_seq + '" />';
	html	+= '<input type="hidden" class="option_type" value="' + option_type + '" />';
	html	+= '<input type="hidden" class="option_seq" value="' + option_seq + '" />';
	html	+= '<input type="hidden" class="use_status" value="' + use_status + '" />';
	html	+= '<input type="hidden" class="main_trade_type" value="' + main_trade_type + '" />';
	html	+= '<input type="hidden" class="trader_seq" value="' + trader_seq + '" />';
	html	+= '<input type="hidden" class="trader_name" value="' + trader_name + '" />';
	html	+= '<input type="hidden" class="currency" value="' + currency + '" />';
	html	+= '<input type="hidden" class="rownum" value="' + rownum + '" />';
	html	+= '</td>';
	html	+= '<td class="center rownum">'+rownum+'</td>';
	html	+= '<td><input type="text" class="supply_goods_name" value="' + supply_goods_name + '" /></td>';
	html	+= '<td class="center hand link-lay use-status-td" onclick="setUseStatus(this);">';
	if	(use_status == 'Y')		html	+= '○';
	else						html	+= 'X';
	html	+= '</td>';
	html	+= '<td class="center hand link-lay main-trade-type-td" onclick="setMainTrader(this);">';
	if	(main_trade_type == 'Y')	html	+= '○';
	else							html	+= 'X';
	html	+= '</td>';
	html	+= '<td class="hand link-lay trader_str_lay center" onclick="openSelectTraders(this);">';
	if	(!trader_name && !trader_seq){
		html	+= '<span class="resp_btn v2">검색</span>';
	}else{
		html	+= trader_name + '(' + trader_seq + ')(' + currency + ')';
	}
	html	+= '</td>';
	html	+= '<td class="right supply-td">';
	html	+= '<input type="text" size="10" class="supply_price" value="' + supply_price + '" onblur="supply_price_event(this);"/>';
	html	+= '</td>';
	html	+= '<td class="center">';
	html	+= '<label class="resp_checkbox"><input type="checkbox" class="use_supply_tax" value="Y" ';
	html	+= (use_supply_tax == 'Y') ? 'checked' : '';
	html	+= (currency != 'KRW') ? 'disabled' : '';
	html	+= ' onclick="supply_price_event(this);"></label></td>';
	html	+= '<td class="supply_tax_td right">';
	if	(use_supply_tax == 'Y'){
		html	+= calculate_tax_price(currency, supply_price);
	}else{
		html	+= '0';
	}
	html	+= '</td>';
	html	+= '</tr>';
	modifyLay.find('tbody.defaultinfo-tbody').append(html);

	//번호 업데이트
	modifyLay.find('tbody.defaultinfo-tbody').find('tr').each(function(idx){
		var size = modifyLay.find('tbody.defaultinfo-tbody').find('tr').length;
		$(this).find('.rownum').text(size - idx);
	});
}

function copyFirstRowData(){
	if($(".noData").length>0) return;
	var modifyLay	= $('div#defaultinfo_modify');
	var firstTrObj	= modifyLay.find('tbody.defaultinfo-tbody').find('tr').eq(0);
	use_status			= firstTrObj.find('input.use_status').val();
	trader_seq			= firstTrObj.find('input.trader_seq').val();
	trader_name			= firstTrObj.find('input.trader_name').val();
	currency			= firstTrObj.find('input.currency').val();
	supply_goods_name	= firstTrObj.find('input.supply_goods_name').val();
	supply_price		= firstTrObj.find('input.supply_price').val();
	use_supply_tax		= (firstTrObj.find('input.use_supply_tax').attr('checked')) ? 'Y' : 'N';

	var cur_main_trade	= '';
	var cur_use_status	= '';
	modifyLay.find('tbody.defaultinfo-tbody').find('tr').each(function(){
		cur_main_trade	= $(this).find('input.main_trade_type').val();
		cur_use_status	= $(this).find('input.use_status').val();
		if	(cur_main_trade != 'Y' && cur_use_status != use_status){
			setUseStatus($(this).find('td.use-status-td'));
		}
		$(this).find('input.supply_goods_name').val(supply_goods_name);
		$(this).find('input.trader_seq').val(trader_seq);
		$(this).find('input.trader_name').val(trader_name);
		$(this).find('input.currency').val(currency);
		$(this).find('td.trader_str_lay').html(trader_name + '(' + trader_seq + ')(' + currency + ')');
		$(this).find('input.supply_price').val(supply_price);
		if	(use_supply_tax == 'Y')	$(this).find('input.use_supply_tax').attr('checked', true);
		else						$(this).find('input.use_supply_tax').attr('checked', false);

		supply_price_event($(this).find('input.supply_price'));
	});
}

// 상품관리 일괄등록 처리
function applyBatchDefaultinfo(){

	var closeStatus	= true;
	var lay_id		= '';
	var tmpArr		= new Array();
	var option_type	= '';
	var option_seq	= '';
	$('.defaultinfo-con').each(function(){
		
		if	($(this).find("input[name='chk_option[]']").attr('checked')){
			lay_id		= $(this).find(".defaultinfo-lay").attr('id');
			tmpArr		= new Array();
			option_type	= '';
			option_seq	= '';
			if	(lay_id != 'defaultinfo_modify'){
				tmpArr		= lay_id.split('_');
				option_type	= tmpArr[1];
				option_seq	= tmpArr[2];
				if	(option_type && option_seq)	closeStatus	= applyModifyDefaultinfo(option_type, option_seq);
			}

			// 주거래처가 한개도 없을 경우 첫번째 거래처를 주거래처로 강제 적용
			if	(!$(this).find("input.main_trade_type[value='Y']").val()){
				$(this).find("input.main_trade_type").eq(0).val('Y');
				$(this).find("td.main-trade-type-td").eq(0).html('○');
				$(this).find("input.use_status").eq(0).val('Y');
				$(this).find("td.use-status-td").eq(0).html('○');
			}
		}
	});

	if	(closeStatus)	closeDialog('defaultinfo_modify');
}

// 상품관리 수정 처리
function applyModifyDefaultinfo(option_type, option_seq){

	var chkStatus	= true;
	var trLay		= $('div#defaultinfo_modify').find('tbody.defaultinfo-tbody tr');

	// 적용 전 필수값 체크
	trLay.each(function(){		
		if	(!$(this).find('input.trader_seq').val()){
			openDialogAlert('거래처가 없는 발주 정보가 존재합니다.<br/>거래처를 선택해 주세요.', 400, 200, function(){});
			chkStatus	= false;
			return false;
		}
	});

	if	(!chkStatus)	return false;

	// 일괄등록이 아닌 경우 부모영역은 초기화함.
	if	(!option_type && !option_seq){
		if	(!option_type)	option_type	= trLay.eq(0).find('input.option_type').val();
		if	(!option_seq)	option_seq	= trLay.eq(0).find('input.option_seq').val();
		if	(!option_type)	option_type	= default_global_option_type;
		if	(!option_seq)	option_seq	= default_global_option_seq;
		$('div#defaultinfo_' + option_type + '_' + option_seq).find('tbody.defaultinfo-tbody tr').remove();
		trLay.each(function(){
			applyDefaultInfo(option_type, option_seq, $(this));
		});
		// 주거래처가 한개도 없을 경우 첫번째 거래처를 주거래처로 강제 적용
		if	(!$('div#defaultinfo_' + option_type + '_' + option_seq).find("input.main_trade_type[value='Y']").val()){
			$('div#defaultinfo_' + option_type + '_' + option_seq).find("input.main_trade_type").eq(0).val('Y');
			$('div#defaultinfo_' + option_type + '_' + option_seq).find("td.main-trade-type-td").eq(0).html('○');
			$('div#defaultinfo_' + option_type + '_' + option_seq).find("input.use_status").eq(0).val('Y');
			$('div#defaultinfo_' + option_type + '_' + option_seq).find("td.use-status-td").eq(0).html('○');
		}
	}else{
		trLay.each(function(){			
			if	($('div#defaultinfo_' + option_type + '_' + option_seq).closest(".defaultinfo-con").find("input[name='chk_option[]']").attr('checked')){
				applyDefaultInfo(option_type, option_seq, $(this));
			}
		});
	}

	$('div#defaultinfo_modify').find("input[name='del_temp_default_seq[]']").each(function(){
		$("form[name='detailForm']").append('<input type="hidden" name="del_default_seq[]" value="' + $(this).val() + '" />');
	});
	
	closeDialog('defaultinfo_modify');
	return false;
}

// 일괄등록 및 수정 팝업의 데이터를 부모로 이동
function applyDefaultInfo(option_type, option_seq, currentTrObj){

	if	(option_seq > 0 && ( option_type == 'option' || option_type == 'suboption' ) ){
		var parentLay	= $('div#defaultinfo_' + option_type + '_' + option_seq);
		var default_seq = use_status = main_trade_type = trader_seq = trader_name = currency = '';
		var supply_goods_name = supply_price = use_supply_tax = html = '';
		if	(currentTrObj){
			// 부모영역에 주거래처가 있을 경우 일괄 등록에서 주거래처는 N
			var parent_main_trade = parentLay.find('tbody.defaultinfo-tbody').find('input.main_trade_type[value="Y"]');
			
			rownum				= currentTrObj.find('input.rownum').val();
			default_seq			= currentTrObj.find('input.default_seq').val();
			use_status			= currentTrObj.find('input.use_status').val();
			main_trade_type		= parent_main_trade.length > 0 ? 'N' : currentTrObj.find('input.main_trade_type').val();
			trader_seq			= currentTrObj.find('input.trader_seq').val();
			trader_name			= currentTrObj.find('input.trader_name').val();
			currency			= currentTrObj.find('input.currency').val();
			supply_goods_name	= currentTrObj.find('input.supply_goods_name').val();
			supply_price		= currentTrObj.find('input.supply_price').val();
			use_supply_tax		= (currentTrObj.find('input.use_supply_tax').attr('checked')) ? 'Y' : 'N';
		}	

		html	= '<tr>';
		html	+= '<td>'+supply_goods_name+'</td>';
		html	+= '<td class="center hand link-lay use-status-td" onclick="setUseStatus(this);">';
		html	+= (use_status == 'Y') ? '○' : 'X';
		html	+= '</td>';
		html	+= '<td class="center hand link-lay main-trade-type-td" onclick="setMainTrader(this);">';
		html	+= (main_trade_type == 'Y') ? '○' : 'X';
		html	+= '</td>';
		html	+= '<td class="resp_btn_txt v2" onclick="openSelectTraders(this);">';
		html	+= trader_name + '(' + trader_seq + ')(' + currency + ')';
		html	+= '</td>';
		html	+= '<td class="right">';
		html	+= float_comma(supply_price);
		html	+= '</td>';
		html	+= '<td class="right">';
		html	+= (currency == 'KRW' && use_supply_tax == 'Y') ? comma(calculate_tax_price('KRW', supply_price)) : '0';
		html	+= '</td>';
		html	+= '<td class="center">';
		html	+= '<span class="resp_btn v3" onclick="delSupplyInfoRow(this);">삭제</span>';
		html	+= '<input type="hidden" name="default_seq[]" value="' + default_seq + '" />';
		html	+= '<input type="hidden" name="option_type[]" value="' + option_type + '" />';
		html	+= '<input type="hidden" name="option_seq[]" value="' + option_seq + '" />';
		html	+= '<input type="hidden" name="use_status[]" value="' + use_status + '" class="use_status" />';
		html	+= '<input type="hidden" name="main_trade_type[]" value="' + main_trade_type + '" class="main_trade_type" />';
		html	+= '<input type="hidden" name="trader_seq[]" value="' + trader_seq + '" class="trader_seq" />';
		html	+= '<input type="hidden" name="trader_name[]" value="' + trader_name + '" class="trader_name" />';
		html	+= '<input type="hidden" name="currency[]" value="' + currency + '" class="currency" />';
		html	+= '<input type="hidden" name="supply_goods_name[]" value="' + supply_goods_name + '" />';
		html	+= '<input type="hidden" name="supply_price[]" value="' + supply_price + '" />';
		html	+= '<input type="hidden" name="use_supply_tax[]" value="' + use_supply_tax + '" />';
		html	+= '<input type="hidden" name="rownum[]" class="rownum" value="' + rownum + '" />';
		html	+= '</td>';
		html	+= '</tr>';

		parentLay.find('tbody.defaultinfo-tbody').append(html);
		//parentLay.find('tbody.defaultinfo-tbody').find('tr').each(function(idx){
			//var size = parentLay.find('tbody.defaultinfo-tbody').find('tr').length;
			//$(this).find('td:first-child').text(size - idx);
		//});
	}
}

// 상품관리 설정 Row 삭제
function delSupplyInfoRow(obj){
	// 본창에서 주거래처 체크
	if	( $(obj).parents('#defaultinfo_modify').length == 0 ){
		if	($(obj).closest('td').find("input[name='main_trade_type[]']").val() == 'Y'){
			openDialogAlert('주거래는 삭제할 수 없습니다.', 400, 170, function(){});
			return false;
		}
	}

	if( $(obj).parents('#defaultinfo_modify').length > 0 ){
		var seq		= $(obj).find('.default_seq').val();

		if	(seq > 0)	{
			$('#defaultinfo_modify').append('<input type="hidden" name="del_temp_default_seq[]" value="' + seq + '" />');
		}
	}else{
		var seq		= $(obj).closest('td').find("input[name='default_seq[]']").val();
		if	(seq > 0)	$("form[name='detailForm']").append('<input type="hidden" name="del_default_seq[]" value="' + seq + '" />');
	}

	if($(obj).closest('.defaultinfo-tbody').find("tr").length==1){
		$('div#defaultinfo_modify').find('tbody.defaultinfo-tbody').html('<tr class="noData"><td colspan="9" class="center">상품을 추가해 주세요.</td></tr>');
	}else{
		$(obj).closest('tr').remove();
	}
}

// 상품관리 사용여부 변경
function setUseStatus(obj){
	if	($(obj).closest('tr').find('input.use_status').val() != 'Y'){
		$(obj).html('○');
		$(obj).closest('tr').find('input.use_status').val('Y');
	}else{
		if	($(obj).closest('tr').find('input.main_trade_type').val() == 'Y'){
			openDialogAlert('주거래처는 미사용으로 설정할 수 없습니다.', 400, 170, function(){});
			return false;
		}else{
			$(obj).html('X');
			$(obj).closest('tr').find('input.use_status').val('N');
		}
	}
}

// 상품관리 주거래처 변경
function setMainTrader(obj){

	var locType			= ($(obj).closest('tbody').hasClass('modify-tbody')) ? 'modify' : 'list';
	var now_main_type	= $(obj).closest('tr').find('input.main_trade_type').val();
	var chg_main_type	= (now_main_type == 'Y') ? 'N' : 'Y';

	if	(locType == 'list'){
		if	(now_main_type == 'Y'){
			openDialogAlert('현재 주거래처로 설정되어 있습니다.', 400, 170, function(){});
			return false;
		}
	}

	// 전체 주거래처를 초기화
	$(obj).closest('tbody').find('input.main_trade_type').each(function(){
		$(this).val('N');
		$(this).closest('tr').find('td.main-trade-type-td').html('X');
	});

	if	(chg_main_type == 'Y'){
		// 현재 매입정보를 주거래처로 변경
		$(obj).closest('tr').find('input.main_trade_type').val('Y');
		$(obj).closest('tr').find('input.use_status').val('Y');
		$(obj).closest('tr').find('td.use-status-td').html('○');
		$(obj).closest('tr').find('td.main-trade-type-td').html('○');
	}
}

// 매입가 event 설정
function supply_price_event(obj){
	var supply_tax			= '';
	var percent				= '';
	var currency			= $(obj).closest('tr').find('input.currency').val();
	var supply_price		= $(obj).closest('tr').find('input.supply_price').val();
	supply_price			= supply_price.replace(/[^0-9\.]/g, '');
	$(obj).val(supply_price);

	if	(currency == 'KRW' && $(obj).closest('tr').find('input.use_supply_tax').attr('checked'))
		supply_tax	= calculate_tax_price(currency, supply_price);
	else
		supply_tax	= '0';

	$(obj).closest('tr').find('td.supply_tax_td').html(supply_tax);
}

// 거래처 선택
function openSelectTraders(obj){
	openerObj	= obj;
	getTradersList('1');
	openDialog('거래처 검색', 'select_trader_lay', {'width':850,'height':500,'close':function(){openerObj = '';}});
}

// 거래처 검색
function getTradersList(page){
	var perpage = 5;
	if(page > 1) page = perpage * (page-1);
	var params		= 'sc_trader_use=Y&perpage='+perpage+'&page=' + page;
	var keywordObj	= $('#select_trader_lay').find("input[name='keyword']");
	if	(keywordObj.attr('title') == keywordObj.val())	keywordObj.val('');
	if	(keywordObj.val()){
		params		+= '&trader_use=Y&keyword=' + encodeURIComponent($('#select_trader_lay').find("input[name='keyword']").val())
					+ '&keyword_sType=' + encodeURIComponent($('#select_trader_lay').find("input[name='keyword_sType']").val());
	}

	$.ajax({
		type		: 'get',
		url			: '../scm/getTraderData',
		data		: params,
		dataType	: 'json', 
		success		: function(result){
			$('#select_trader_lay').find('tbody.trader-list').html('');
			if	(result){
				var data	= '';
				var cnt		= result.record.length;
				var rowHtml	= '';
				for	( var i = 0; i < cnt; i++){
					data	= result.record[i];
					rowHtml	= '<tr>';
					rowHtml	+= '<td>' + data._no + '</td>';
					rowHtml	+= '<td>' + data.trader_use_str + '</td>';
					rowHtml	+= '<td>' + data.trader_type_str + '</td>';
					rowHtml	+= '<td>' + data.currency_title + ' ' + data.currency_unit + '</td>';
					rowHtml	+= '<td>' + data.trader_id + '</td>';
					rowHtml	+= '<td>' + data.trader_name + '</td>';
					rowHtml	+= '<td>' + data.company_owner + '</td>';
					rowHtml	+= '<td class="trader_select_btn"><button class="resp_btn v2" type="button" data-no="'+ data._no +'">선택</button></td>';
					rowHtml	+= '</tr>';

					$('#select_trader_lay').find('tbody.trader-list').append(rowHtml);
				}
				
				// 거래처 선택 이벤트 처리
				$(".trader_select_btn button").each(function(index, button) {
					$(button).on("click").on("click", function() {
						let no = $(this).data('no');
						data = result.record.find(trader => trader._no == no);
						selectTrader(data.trader_seq, data.trader_name, data.currency_unit);
					});
				});

				pagenumber = parseInt(result.page.nowpage/perpage)+1;
				$('#select_trader_lay').find('div.page-html-lay').pager({ pagenumber: pagenumber , pagecount: result.page.pagecount, buttonClickCallback: getTradersList,  });

				//$('#select_trader_lay').find('div.page-html-lay').html(getPagingHTML(result.page, 'getTradersList'));

			}else{
				rowHtml	= '<tr>';
				rowHtml	+= '<td class="its-td-align center" colspan="8" height="30px">등록된 거래처가 없습니다.</td>';
				rowHtml	+= '</tr>';
				$('#select_trader_lay').find('tbody.trader-list').append(rowHtml);
			}
		}
	});
}

// 거래처 선택 처리
function selectTrader(seq, name, currency){

	$(openerObj).closest('tr').find('input.trader_seq').val(seq);
	$(openerObj).closest('tr').find('input.trader_name').val(name);
	$(openerObj).closest('tr').find('input.currency').val(currency);
	if	(currency == 'KRW'){
		$(openerObj).closest('tr').find('input.use_supply_tax').attr('disabled', false);
	}else{
		$(openerObj).closest('tr').find('input.use_supply_tax').attr('checked', false).attr('disabled', true);
		$(openerObj).closest('tr').find('td.supply_tax_td').html('0');
	}

	var html		= name + '(' + seq + ')' + '(' + currency + ')';
	$(openerObj).html(html);

	closeDialog('select_trader_lay');
}

// 자동 채우기
function default_all_copy(obj){

	var tbodyLay	= $(obj).closest('table').find('tbody.defaultinfo-tbody');

	switch($(obj).attr('id')){
		case 'supply_goods_name_all':
			var supply_goods_name	= $(obj).closest('th').find('input.supply_goods_name_all').val();
			tbodyLay.find('input.supply_goods_name').val(supply_goods_name);
		break;
		case 'select_trade_all':
			var trader_seq	= $(obj).closest('th').find('input.trader_seq').val();
			var trader_name	= $(obj).closest('th').find('input.trader_name').val();
			var currency	= $(obj).closest('th').find('input.currency').val();
			if	(trader_seq > 0){
				tbodyLay.find('input.trader_seq').val(trader_seq);
				tbodyLay.find('input.trader_name').val(trader_name);
				tbodyLay.find('input.currency').val(currency);
				tbodyLay.find('td.trader_str_lay').html(trader_name + '(' + trader_seq + ')(' + currency + ')');
			}else{
				openDialogAlert('일괄 적용할 거래처를 선택해 주세요.', 400, 170, function(){});
				return false;
			}
		break;
		case 'supply_price_all':
			var supply_price		= $(obj).closest('th').find('input.supply_price_all').val();

			// 변경 시 이벤트 처리를 위해서 일일이 loop를 돌린다.
			tbodyLay.find('input.supply_price').each(function(){
				$(this).val(supply_price);
				supply_price_event($(this));
			});
		break;
		case 'use_supply_tax_all':
			var checkType	= $(obj).attr('checked');

			tbodyLay.find('input.use_supply_tax').each(function(){
				if	($(this).closest('tr').find('input.currency') && checkType)	$(this).attr('checked', true);
				else															$(this).attr('checked', false);
				supply_price_event(this);
			});
		break;
	}
}

// 부가세 재계산
function divide_tax(obj){
	var supply_price	= 0;
	$(obj).closest('div').find('input.supply_price').each(function(){
		if	($(this).closest('tr').find('input.currency').val() == 'KRW' && $(this).closest('tr').find('input.use_supply_tax').attr('checked')){
			supply_price	= Math.round($(this).val() / 1.1);
			$(this).val(supply_price);
			supply_price_event(this);
		}
	});
}

// 자동 채우기 (Grid 용)
function default_all_copy2(obj){

	var tbodyLay	= $(obj).closest('table').find('tbody.defaultinfo-tbody');

	switch($(obj).attr('id')){
		case 'supply_ea_all':
			var supply_ea	= $(obj).closest('th').find('input.excel_table_batch_val').val();
			
			tbodyLay.find('input[name="ea[]"]').closest('td').find('span').text(supply_ea);
			tbodyLay.find('input[name="ea[]"]').val(supply_ea);
		break;
		case 'supply_price_all':
			var supply_price		= $(obj).closest('th').find('input.excel_table_batch_val').val();

			tbodyLay.find('input[name="supply_price[]"]').each(function(){
				$(this).closest('td').find('span').text(float_comma(supply_price));
				$(this).val(supply_price);

				var cellEa = $(this).closest('tr').find('input[name="ea[]"]').val();
				$(this).closest('tr').find('input[name="total_price[]"]').val(supply_price * cellEa);
				$(this).closest('tr').find('input[name="total_price[]"]').closest('td').find('span').text(supply_price * cellEa);
			});			
		break;
		case 'use_supply_tax_all':
			var checkType	= $(obj).attr('checked');

			tbodyLay.find("input[name='use_supply_tax[]']").each(function(){
				if	($(this).closest('tr').find("input[name='currency[]']") && checkType)	$(this).attr('checked', true);
				else																		$(this).attr('checked', false);
			});
		break;
		case 'location_position_all':
			var location_position	= $(obj).closest('th').find('input.excel_table_batch_val').val();
			
			tbodyLay.find('input[name="in_location_code[]"]').closest('td').find('span').text(location_position);
			tbodyLay.find('input[name="in_location_code[]"]').val(location_position);
		break;
	}

	sorderCalculateTotal();
}

// 자동발주상품 등록 팝업 오픈
function openAddAutoOrderGoodsPopup(){
	var optioninfo_list	= '';
	$("form[name='listFrm']").find('input.chk').each(function(){
		if	($(this).attr('checked')){
			optioninfo_list	+= $(this).val() + ',';
		}
	});

	if	(!optioninfo_list){
		openDialogAlert('자동발주상품을 선택해 주세요.', 400, 170, function(){});
		return false;
	}

	$('div#add_auto_order_goods').find("input[name='optioninfo_list']").val(optioninfo_list);
	openDialog('자동발주상품 등록', 'add_auto_order_goods', {'width':600});
}

// 자동발주상품 등록
function addAutoOrderSubmit(){

	var frm	= $("form[name='autoOrderFrm']");

	// 선택된 상품이 있는지 확인
	if	(!frm.find("input[name='optioninfo_list']").val()){
		openDialogAlert('자동발주상품을 선택해 주세요.', 400, 170, function(){});
		return false;
	}

	// 수량 직접 입력 시 수량 체크
	if	(frm.find("input[name='add_ea_type']").eq(0).attr('checked') == 'checked'){
		var direct_ea	= frm.find("input[name='direct_ea']").val();

		if	(direct_ea.search(/[^0-9]/) != -1 ){
			openDialogAlert('자동발주 수량은 숫자로만 입력해주세요.', 400, 170, function(){});
			return false;
		}
		if	(direct_ea < 1){
			openDialogAlert('자동발주 수량을 1이상 입력해 주세요.', 400, 170, function(){});
			return false;
		}
	}

	frm.submit();
}
// 자동발주상품 등록 팝업 닫기
function addAutoOrderClose(){
	closeDialog('add_auto_order_goods');
}

// 창고 변경에 따른 목록의 창고 정보 변경 처리
function chg_list_warehouse_data(obj){
	var params	= 'wh_seq=' + $(obj).val();
	$('input.optioninfo').each(function(){
		params	+= '&optioninfo[]=' + $(this).val();
		if	($(obj).val() > 0){
			$('.wh-supply_price-lay-' + $(this).val()).html('0');
			$('.wh-location-lay-' + $(this).val()).html('');
			$('.wh-stock-lay-' + $(this).val()).html('0(0)');
		}else{
			$('.wh-supply_price-lay-' + $(this).val()).html('<center>-</center>');
			$('.wh-location-lay-' + $(this).val()).html('-');
			$('.wh-stock-lay-' + $(this).val()).html('0(0)');
		}
	});

	$.ajax({
		type		: 'get',
		url			: '../scm/get_warehouse_stock_info',
		data		: params, 
		dataType	: 'json', 
		success		: function(result){
			if	(result.status){
				var data	= '';
				var optKey	= '';
				for	( var i in result.data){
					data	= result.data[i];
					optKey	= data.goods_seq + data.option_type + data.option_seq;
					if	($(obj).val()){
						$('.wh-supply_price-lay-' + optKey).html(float_comma(data.supply_price));
						$('.wh-location-lay-' + optKey).html(data.location_code);
					}
					$('.wh-stock-lay-' + optKey).html(float_comma(data.ea) + '(' + float_comma(data.bad_ea) + ')');
				}
			}
		}
	});
}

// 안전재고 검색 방식 선택
function chg_safestock_checktype(obj){
	var chkType		= $(obj).val();
	$(obj).closest('td').find('span.sc_safestock_sstock').hide();
	$(obj).closest('td').find('span.sc_safestock_estock').hide();
	if	(chkType > 1){
		$(obj).closest('td').find('span.sc_safestock_sstock').show();
	}
	if	(chkType > 2){
		$(obj).closest('td').find('span.sc_safestock_estock').show();
	}
}

// 상점 변경에 따른 목록의 상점 정보 변경 처리
function chg_list_store_data(obj){
}

//------------ ↑↑ defaultinfo ↑↑------ ↓↓ revision ↓↓--------------//

// 재고조정 저장
function submitRevision(){
	if	($("input[name='revision_status']").attr('org') && $("input[name='revision_status']").attr('org') != $("input[name='revision_status']").val()){
		$("input[name='revision_status']").val($("input[name='revision_status']").attr('org'));
	}
	excelTableSubmit();
}

// 재고조정 저장
function applyRevision(){
	$("input[name='revision_status']").attr('org', $("input[name='revision_status']").val());
	$("input[name='revision_status']").val('1');
	excelTableSubmit();
}

// 기초재고 선택 시 처리
function chkRevisionDefault(){
	openDialogConfirm('기초재고 입력은 ‘상품 빠른등록’ 메뉴에서 가능합니다.<br/>기초재고 입력 페이지로 이동하시겠습니까?', 500, 170
	, function(){
		location.href	= '../goods/batch_regist';
	}, function(){
		$("input[name='revision_type']").eq(0).attr('checked', true).click();
	});
}

// 조정방식 변경에 따른 재고 안내타이틀 변경
function chgRevisionStockTitle(){
	if	($("input[name='revision_status']").val() != 1){
		if			($("input[name='revision_type']").eq(0).attr('checked')){
			return '입고조정 창고의 현재고';
		}else if	($("input[name='revision_type']").eq(1).attr('checked')){
			return '출고조정 창고의 현재고';
		}else if	($("input[name='revision_type']").eq(2).attr('checked')){
			return '불량폐기 창고의 현재고';
		}
	}
}

//------------ ↑↑ revision ↑↑------ ↓↓ stockmove ↓↓--------------//

// 재고이동 form submit
function submitStockmove(status){

	var chkVal		= chkBeforeSelectGoodsOptionData('');
	var params		= chkVal['params'];
	if	(!chkVal['status'])	return false;

	var submitStatus	= true;
	var loc_ea			= 0;
	var ea				= 0;
	$('div#excelTable').find("input[name='option_seq[]']").each(function(){
		if	($(this).val()){

			loc_ea	= $(this).closest('tr').find('.location_ea').text().replace(/\([0-9]*\)/, '');
			ea		= $(this).closest('tr').find("input[name='ea[]']").val();
			if			( !(parseInt(ea) > 0) ){
				openDialogAlert('이동수량은 1이상이어야 합니다. 이동수량을 확인해주세요.', 500, 170, function(){});
				submitStatus	= false;
				return false;
			}
		}
	});
	if	(!submitStatus)	return false;

	if	(status > 0 && status != $("input[name='move_status']").attr('org')){
		if			(status == '1'){
			var out_wh_name	= $("select[name='out_wh_seq'] option:selected").text();
			openDialogConfirm('해당 상품의 재고를 ' + out_wh_name + '에서 이동중 처리하시겠습니까?', 500, 170, function(){
				$("input[name='move_status']").val('1');
				excelTableSubmit();
			});
		}else if	(status == '2'){
			var out_wh_name	= $("select[name='out_wh_seq'] option:selected").text();
			var in_wh_name	= $("select[name='in_wh_seq'] option:selected").text();
			openDialogConfirm('해당 상품의 재고를 ' + out_wh_name + '에서 → ' + in_wh_name + '으로 이동하시겠습니까?', 500, 170, function(){
				$("input[name='move_status']").val('2');
				excelTableSubmit();
			});
		}else if	(status == '3'){
			var in_wh_name	= $("select[name='in_wh_seq'] option:selected").text();
			openDialogConfirm('이동중인 상품을 ' + in_wh_name + '으로 이동완료하시겠습니까?', 500, 170, function(){
				$("input[name='move_status']").val('3');
				excelTableSubmit();
			});
		}
	}else{
		excelTableSubmit();
	}
}

//------------ ↑↑ stockmove ↑↑------ ↓↓ sorder ↓↓--------------//

// 발주서 SMS/이메일 발송
function sorder_sender(sono,mode){
	
	var params	= {};
	params.sono	= sono;
	params.mode	= (mode) ? mode : 'mail';

	$.get('../scm/get_sorder_draft_form',params , function(response){

		if(mode == 'sms'){

			$('#sms_body_byte').text(response.replace_text.byteLength());

			$('#left_sms_count').text(response.sms_count_text);
			$('#to_cellphone_view').text(response.to_cellphone);
			$('#to_cellphone').val(response.to_cellphone);
			$('#sms_body').val(response.replace_text);

			openDialog('SMS 발송','sorderDraftSMSForm', {'width':700});
		}else{				

			$('#recent_email_list > option').remove();			

			if(response.recent_email.length > 0){
				
				$('#recent_email_list').append('<option value="now_sorder">선택한 발주서 양식</option>');
				
				$.each(response.recent_email, function(key,val){
					$('#recent_email_list').append('<option value="' + val.mail_seq + '" sono = "' + sono + '">' + val.subject + '</option>');
				});
			}else{
				
				$('#recent_email_list').append('<option value="none"> = 최근 발송한 이메일이 없습니다. =  </option>');
			}

			openDialog('EMAIL 발송','sorderDraftEmailForm', {'width':1000,'height':810});

			$('#email_title').val(response.replace_text);
			$('#sender_email').val(response.sender_email);
			$('#sender_name').val(response.sender_name);
			$('#to_email').val(response.to_email);
			$('#left_email_count').text(response.email_count_text);
			
			//다음 에디터 재 정의
			var config	= Editor.config;
			EditorJSLoader.ready(function (Editor) {
				var editor = new Editor(config);
			});

			Editor.modify({"content" : response.email_body});
		}
	},'json');	
}

// 발주서 form submit
function submitSorder(status){

	if	(!$("select[name='trader_seq']").val()){
		openDialogAlert('거래처를 선택해 주세요.', 400, 170, function(){});
		return false;
	}

	if	(status == '1'){
		var msg	= '발주완료 시 해당 거래처에게 문자와 이메일이 자동 발송됩니다. (자동 발송 설정 시)<br/>해당 발주서를 발주완료 하시겠습니까?';
		openDialogConfirm(msg, 600, 200, function(){
			submitProcSorder(status);
		}, function(){ });
	}else{
		submitProcSorder(status);
	}
}

// 발주서 form submit
function submitProcSorder(status){
	$("input[name='sorder_status']").val(status);
	excelTableSubmit();
}

// 발주서 복사
function copy_sorder(sorder_seq){
	if	(sorder_seq){
		loadingStart();
		var tmpForm	= $('<form method="post" action="../scm_process/copy_sorder" target="actionFrame"></form>').appendTo($('body'));
		tmpForm.append('<input type="hidden" name="sorder_seq" value="' + sorder_seq + '" />');
		tmpForm.submit();
		tmpForm.remove();
	}
}

// 발주서 인쇄
function sorderPrints(obj){
		var print_sono_list	= '';
		var print_sono_cnt	= 0;
		
		$.each($('input[name="sorder_seq[]"]:checked'), function(){
			print_sono_list		+= (print_sono_list != '') ? '&sono[]=' + this.value : 'sono[]=' + this.value;
			print_sono_cnt++;
		});

		if(print_sono_cnt < 1){
			openDialogAlert('발주서를 선택하세요', 400, 170, function(){});
			return;
		}
		
		sorderPrint(print_sono_list,'multi');
}

// 발주서 인쇄
function sorderPrint(sono,mode){
	if(mode == 'multi'){
		var get_params	= sono;
	}else{
		var get_params	= "sono=" + sono
	}
	window.open('../scm/sorder_prints?' + get_params,'_print','width=800,height=900,menubar=no,status=no,toobar=no,scrollbars=yes,resizable=no');
}

// 발주서 삭제
function sorderRemove(obj){
	var print_sono_list	= '';
	var print_sono_cnt	= 0;
	var flag = false;

	$.each($('input[name="sorder_seq[]"]:checked'), function(){
		var stat = $(this).closest('td').find('input[name="sorder_status[]"]').val();
		if(stat > 0){			
			flag = true;
			return false;
		}

		print_sono_list		+= (print_sono_list != '') ? '&sono[]=' + this.value : 'sono[]=' + this.value;
		print_sono_cnt++;
	});

	if(flag){
		alert('대기 상태만 삭제 가능합니다.');
		return false;
	}

	if(print_sono_cnt < 1){
		openDialogAlert('발주서를 선택하세요', 400, 150, function(){});
		return;
	}

	openDialogConfirm('선택하신 ' + print_sono_cnt + '개의 발주대기 내역을 삭제하시겠습니까?<br/>',400,170,function(){
			loadingStart();
			var orginAct = $("form[name='listFrm']").attr('action');
			$("form[name='listFrm']").attr('method', 'post');
			$("form[name='listFrm']").attr('action', '../scm_process/remove_sorder');
			$("form[name='listFrm']").submit();
			$("form[name='listFrm']").attr('method', 'get');
			$("form[name='listFrm']").attr('action', orginAct);
		});
}	

// 발주서 전송
function email_seanding(obj){
	submitEditorForm(document.emailForm);
}

// 최근 이메일 발송 목록
function recent_email_list(obj){
	switch(this.value){
		case	'none' :
			return false;
		break;

		case	'now_sorder' :	// 선택한 발주서 양식
			sorder_sender('{_GET.sono}', 'email');
		break;
		default :	// 선택한 최근 발송 이메일
			$.post('../scm/get_recent_email?mail_idx=' + obj.value, function(response){
				if(response.length > 0){
					var data	= response[0];
					$('#sender_email').val(data.sender_email);
					$('#email_title').val(data.subject);
					$('#to_email').val(data.to_email);
					Editor.modify({"content" : data.contents});
				}else{
					openDialogAlert('발송된 이메일 정보가 없습니다.', 400, 170, function(){});
				}
			}, 'json');
		break;
	}
}

// 문자 내용
function sms_body(obj){
	$('#sms_body_byte').text(obj.value.byteLength());
}

// 문자 전송
function sms_seanding(obj){
	/*
	if($('#sms_sand_chk').attr('checked') != 'checked'){
		openDialogAlert('SMS 전송에 체크해 주셔야 합니다..', 400, 170, function(){});
		return;
	}*/

	var params			= {};
	params.to_cellphone	= $('#to_cellphone').val();
	params.sms_body		= $('#sms_body').val();

	$.post("../scm_process/sorder_draft_sms_sender", params, function(response){
		openDialogAlert(response.message, 400, 170, function(){});
		if(response.success === true)	closeDialog("sorderDraftSMSForm");
		$('#sms_sand_chk').removeAttr('checked');
	},'json');
}

// 발주 취소
function sorder_cancel(sono){
	var confirm_msg = '<div><b class="red">발주취소 시</b> 해당 발주서로 <b class="red">입고대기인 입고건은 삭제됩니다.</b></div>';
	confirm_msg += '<div class="mt10">해당 발주서를 발주취소합니까?</div>';

	openDialogConfirm(confirm_msg, 450, 200, function(){
		$.post('../scm_process/sorder_cancel', {'sono':sono}, function(res){
			if (!res.result && res.error){
				openDialogAlert(res.error, 400, 170, function(){});
			}else{
				openDialogAlert('발주서가 취소되었습니다.', 400, 170, function(){
					location.reload();
				});
			}
		}, 'json');
	}, function(){ });
}

// 수정 발주 페이지로 이동
function sorder_modify(sono){
	location.href = './sorder_modify?sono=' + sono;
}

// 수정발주서 form submit
function submitModifySorder(){
	var confirm_msg = '<div><b class="red">수정발주 시</b> 기존 발주서로 <b class="red">입고대기인 입고건은 삭제됩니다.</b></div>';
	confirm_msg += '<div class="mt10">해당 발주서를 수정발주합니까?</div>';

	openDialogConfirm(confirm_msg, 450, 250, function(){
		submitProcSorder('1');
	}, function(){ });

	return false;
}

//------------ ↑↑ sorder ↑↑------ ↓↓ warehousing ↓↓--------------//

// 발주목록 조회
function searchSorderList(data){
	if	(!$("select[name='in_wh_seq']").val()){
		openDialogAlert('입고창고를 선택해 주세요.', 400, 170, function(){});
		return false;
	}

	var params	= (data) ? data : 'in_wh_seq=' + $("select[name='in_wh_seq']").val();

	// 초기화
	$('div#sorder_search_popup').html('').hide();

	$.ajax({
		type		: 'get',
		url			: '../scm/getAjaxSorderList',
		data		: params,
		dataType	: 'json', 
		global		: false,
		success		: function(result){
			if	(result.status){
				makeSorderPopup(result.data, result.page);
			}else{
				openDialogAlert('검색된 발주서가 없습니다.', 1000, 170, function(){});
				return false;
			}
		}
	});
}

// 발주서 선택 팝업
function makeSorderPopup(data, page){
	// 초기화
	$('div#sorder_search_popup').html('').hide();

	var row		= '';
	var divObj	= '';
	var html	= '';
	var cnt		= data.length;
	if	(cnt > 0){

		html	= '<div class="content"><table class="table_basic tdc">';
		html	= html + '<colgroup>';
		html	= html + '	<col width="8%" />';
		html	= html + '	<col width="10%" />';
		html	= html + '	<col width="10%" />';
		html	= html + '	<col width="*" />';
		html	= html + '	<col width="7%" />';
		html	= html + '	<col width="8%" />';
		html	= html + '	<col width="10%" />';
		html	= html + '	<col width="9%" />';
		html	= html + '	<col width="11%" />';
		html	= html + '	<col width="8%" />';
		html	= html + '</colgroup>';
		html	= html + '<tbody>';
		html	= html + '	<tr>';
		html	= html + '		<th>구분</th>';
		html	= html + '		<th>발주번호</th>';
		html	= html + '		<th>거래처(통화)</th>';
		html	= html + '		<th>상품</th>';
		html	= html + '		<th>미입고</th>';
		html	= html + '		<th>발주수량</th>';
		html	= html + '		<th>발주가액</th>';
		html	= html + '		<th>부가세</th>';
		html	= html + '		<th>상태</th>';
		html	= html + '		<th>관리</th>';
		html	= html + '	</tr>';

		var sorder_status	= '대기';
		var sorder_type		= '비정규';
		var status_complete_date = '';
		for	( var d = 0; d < cnt; d++){
			row			= data[d];
			status_complete_date = '(' + row.complete_date.substring(0, 10) + ')';

			if	(row.sorder_status == '1')	sorder_status	= '완료';
			else if (row.sorder_status == '2') {
				sorder_status = '<span style="color:red;">취소</span>';
				status_complete_date = '<span style="color:red;">' + status_complete_date + '</span>';
			}
			
			status_complete_date = sorder_status + '<br/>' + status_complete_date;

			sorder_type		= '비정규';
			if	(row.sorder_type == 'A')	{
				sorder_type		= '정규';
			}else if (row.sorder_type == 'T') {
				sorder_type		= '비정규[임]';
			}
			html	= html + '	<tr>';
			html	= html + '		<td>' + sorder_type + '</td>';
			html	= html + '		<td class="resp_btn_txt v2" onclick="window.open(\'../scm_warehousing/sorder_regist?sono=' + row.sorder_seq + '\');">' + row.sorder_code + '</td>';
			html	= html + '		<td>' + row.trader_name + '(' + row.trader_currency + ')</td>';
			html	= html + '		<td class="left">' + row.goods_name + '</td>';
			html	= html + '		<td class="right red">' + row.remain_ea + '</td>';
			html	= html + '		<td class="right">' + row.ea + '</td>';
			html	= html + '		<td class="right">' + comma(parseInt(row.krw_total_supply_price)) + '</td>';
			html	= html + '		<td class="right">' + comma(parseInt(row.krw_total_supply_tax)) + '</td>';
			html	= html + '		<td>' + status_complete_date +'</td>';
			html	= html + '		<td>';
			html	= html + '			<input type="hidden" name="trader_seq[]" value="'+row.trader_seq+'"/>';
			html	= html + '			<input type="hidden" name="trader_name[]" value="'+row.trader_name+'"/>';
			html	= html + '			<input type="hidden" name="currency[]" value="'+row.currency+'"/>';
			html	= html + '			<input type="hidden" name="trade_terms[]" value="'+row.trade_terms+'"/>';
			html	= html + '			<input type="hidden" name="whs_seq[]" value="'+row.whs_seq+'"/>';
			html	= html + '			<input type="hidden" name="remain_ea[]" value="'+row.remain_ea+'"/>';
			html	= html + '			<input type="hidden" name="sorder_seq[]" value="'+row.sorder_seq+'"/>';
			html	= html + '			<input type="hidden" name="sorder_code[]" value="'+row.sorder_code+'"/>';
			html	= html + '			<button type="button" onclick="sorderSelectEvent(this);" class="resp_btn v2">선택</button>';
			html	= html + '		</td>';
			html	= html + '	</tr>';			
		}

		html	= html + '</tbody>';
		html	= html + '</table>';	
		
		// 2016.07.20 페이징 추가 pjw
		if(page != null){
			html	= html + '<div class="paging_navigation footer">'+page.html+'</div></div>';
		}

		html	= html + '<div class="footer">';
		html	= html + '<button type="button" class="resp_btn v3 size_XL" onclick="closeDialogEvent(this);">닫기</button>';
		html	= html + '</div>';

		$('#sorder_search_popup').html(html);
		openDialog('발주서 조회', 'sorder_search_popup', {'width': '1150', 'height' : '700'});
		
	}
}

// 발주서 선택 이벤트
function sorderSelectEvent(obj, target){
	var parentObj = $(obj).closest('td');

	if($(parentObj).find('[name="whs_seq[]"]').val() > 0){
		alert('해당 발주에 대한 입고대기 상태의 입고건이 존재합니다.\n입고대기 상태의 입고건을 먼저 입고완료 처리해 주세요.');
		return false;
	}

	if($(parentObj).find('[name="remain_ea[]"]').val() > 0){
		var seq				= $(parentObj).find("input[name='sorder_seq[]']").val();
		var code			= $(parentObj).find("input[name='sorder_code[]']").val();
		var trader_seq		= $(parentObj).find("input[name='trader_seq[]']").val();
		var trader_name		= $(parentObj).find("input[name='trader_name[]']").val();
		var currency		= $(parentObj).find("input[name='currency[]']").val();
		var trade_terms		= $(parentObj).find("input[name='trade_terms[]']").val();
		current_currency	= currency;
		scmChangeTrader('');

		$("input[name='inclusion_freight']").attr('checked', false);
		$("input[name='inclusion_insurance']").attr('checked', false);
		if	(trade_terms == 'CIF' || trade_terms == 'CFR')	$("input[name='inclusion_freight']").attr('checked', true);
		if	(trade_terms == 'CIF' || trade_terms == 'CIN')	$("input[name='inclusion_insurance']").attr('checked', true);

		$('form[name="detailForm"]').find('input[name="sorder_seq"]').val(seq);
		$('form[name="detailForm"]').find('input[name="sorder_code"]').val(code);
		$('span.trader_name').html(trader_name);
		$('form[name="detailForm"]').find('input[name="trader_seq"]').val(trader_seq);

		var targetObj = this;
		if(target == 'parent') targetObj = parent;

		autoAddSorderGoods(seq, targetObj);
		targetObj.closeDialog('sorder_search_popup');
	}else{
		alert('미입고 수량이 없습니다.');
		return false;
	}
}

// 발주서 선택에 따른 상품 자동 채우기
function autoAddSorderGoods(sorder_seq, target){

	if	(!$("select[name='in_wh_seq']").val()){
		openDialogAlert('입고창고를 선택해 주세요.', 400, 170, function(){});
		return false;
	}

	if	(sorder_seq > 0){
		$.ajax({
			type		: 'get',
			url			: '../scm/getSorderGoodsData',
			data		: 'sono=' + sorder_seq + '&in_wh_seq=' + $("select[name='in_wh_seq']").val(),
			dataType	: 'json', 
			global		: false,
			success		: function(result){
				target = target == null ? this : target;
				if	(result.length > 0){
					resetExcelTableData();
					var data	= target.goodsDataArrayValues(result, 'sorder');
					for	( var r = 0; r < data.length; r++){
						if	(result[r].ea > result[r].whs_ea){
							var tmp_tr = target.excelTableObj.addDefaultRow('datas', data[r]);
							calculateExcelTable(tmp_tr.find("input[name='option_seq[]']"), '');
							area_help_tooltip(tmp_tr);
						}
					}				
					excelTableObj.delDefaultRow('option_seq[]');					
				}
				apply_cif_set();
			}
		});
	}
}

// 거래처 변경에 따른 form 초기화
function resetWarehousingForm(){

	var whs_type	= $("input[name='whs_type']").val();

	// 초기화
	$('div#sorder_list_lay').html('').hide();
	$('span#sorder_detail').html('');
	$("input[name='trader_seq']").val('');
	$("input[name='sorder_seq']").val('');
	$("input[name='sorder_code']").val('');

	if	(whs_type != 'E'){
		current_currency		= 'KRW';
		$('span.trader_name').html('');
		$('span.dis_sorder_code').html('');
		scmChangeTrader($("input[name='trader_seq']"));
	}

	resetExcelTableList();
}

// 입고 저장
function submitWarehousing(nowStatus, status){
	if	(!nowStatus || nowStatus == '0'){
		$("input[name='status']").val(status);
		var whs_type	= $("input[name='whs_type']").val();
		
		// 거래처 체크
		if	(!$("select[name='trader_seq']").val() && !$("input[name='trader_seq']").val()){
			openDialogAlert('거래처를 선택해 주세요.', 400, 170, function(){});
			return false;
		}
		// 입고창고 체크
		if	(!$("select[name='in_wh_seq']").val()){
			openDialogAlert('입고창고를 선택해 주세요.', 400, 170, function(){});
			return false;
		}
		
		if	(whs_type != 'E'){
			// 발주서 체크
			if	(!$("input[name='sorder_seq']").val()){
				openDialogAlert('발주서를 선택해 주세요.', 400, 170, function(){});
				return false;
			}
		}
	}

	if	(status == '1'){
		var msg	= '입고완료 후에는 입고상품을 수정할 수 없습니다.<br/>해당 입고를 입고완료 하시겠습니까?';
		openDialogConfirm(msg, 500, 170, function(){
			excelTableSubmit();
		}, function(){ });
	}else{
		excelTableSubmit();
	}
}


// 입고서 인쇄
function wareHousingPrint(target_name, mode){

	var print_list			= '';
	var print_cnt			= 0;
	$("input[name='" + target_name + "']").each(function(){
		if	(mode != 'multi' || $(this).attr('checked')){
			print_list		+= (print_list) ? '&whno[]=' + $(this).val() : 'whno[]=' + $(this).val();
			print_cnt++;
		}
	});

	if	(!print_cnt){
		openDialogAlert('인쇄할 입고내역이 없습니다.', 400, 170, function(){});
		return;
	}

	window.open('../scm/warehousing_prints?' + print_list, 'wareHousingPrintPopup', 'width=1000,height=700,menubar=no,status=no,toobar=no,scrollbars=yes,resizable=no');
}

// 자동 입고데이터 삭제
function autoRemoveWarehousing(){
	var print_list	= '';
	var print_cnt	= 0;
	var flag = false;
	
	$.each($('input[name="whsSeqArr[]"]:checked'), function(){
		var stat = $(this).closest('td').find('input[name="whs_status[]"]').val();
		if(stat > 0){
			flag = true;
			return false;
		}

		print_list		+= (print_list != '') ? '&whno[]=' + this.value : 'whno[]=' + this.value;
		print_cnt++;
	});

	if(flag){
		alert('대기 상태만 삭제 가능합니다.');
		return false;
	}

	if(print_cnt < 1){
		openDialogAlert('입고내역을 선택하세요', 400, 170, function(){});
		return;
	}
	
	openDialogConfirm('선택하신 ' + print_cnt + '개의 입고대기 내역을 삭제하시겠습니까?<br/>',400,170,function(){
		loadingStart();
		var orginAct = $("form[name='listFrm']").attr('action');
		$("form[name='listFrm']").attr('method', 'post');
		$("form[name='listFrm']").attr('action', '../scm_process/remove_auto_warehounsing');
		$("form[name='listFrm']").submit();
		$("form[name='listFrm']").attr('method', 'get');
		$("form[name='listFrm']").attr('action', orginAct);
	});
	
}

//------------ ↑↑ warehousing ↑↑------ ↓↓ carryingout ↓↓--------------//

// 반출 저장
function submitCarryingout(nowStatus, status){
	if	(!nowStatus || nowStatus == '0'){
		$("input[name='status']").val(status);
		// 반출창고 체크
		if	(!$("select[name='wh_seq']").val()){
			openDialogAlert('반출창고를 선택해 주세요.', 400, 170, function(){});
			return false;
		}
		// 거래처 체크
		if	(!$("select[name='trader_seq']").val()){
			openDialogAlert('거래처를 선택해 주세요.', 400, 170, function(){});
			return false;
		}

		// 입고상품 체크
		var optCnt		= 0;
		var chkStatus	= true;
		$("input[name='option_seq[]']").each(function(){
			if	($(this).val()){
				optCnt++;
				if	( !(parseInt($(this).closest('tr').find("input[name='ea[]']").val()) > 0) ){
					chkStatus	= false;
					openDialogAlert('반출수량은 1이상이어야 합니다. 반출수량을 확인해 주세요.', 400, 170, function(){});
					return false;
				}
				if	( parseInt($(this).closest('tr').find("input[name='ea[]']").val()) > parseInt($(this).closest('tr').find("input[name='stock[]']").val()) ){
					chkStatus	= false;
					openDialogAlert('반출수량은 반출창고에 있는 재고수량을 초과할 수 없습니다.', 400, 170, function(){});
					$(this).closest('tr').find("input[name='ea[]']").val('0');
					$(this).closest('tr').find("input[name='ea[]']").closest('td').find('span').html('0');
					$(".excel_table_batch_val").val('');
					return false;
				}
			}
		});
	}
	if(chkStatus == true) {
		if	(status == '1'){
			var msg	= '반출완료 후에는 반출상품을 수정할 수 없습니다.<br/>해당 반출을 완료하시겠습니까?';
			openDialogConfirm(msg, 500, 170, function(){
				excelTableSubmit();
			}, function(){ });
		}else{
			excelTableSubmit();
		}
	}
}

// 반출 명세서 인쇄
function carryingoutPrints(obj){
	var print_crono_list	= '';
	var print_crono_cnt	= 0;
	
	$.each($('input[name="cro_seq[]"]:checked'), function(){
		print_crono_list		+= (print_crono_list != '') ? '&crono[]=' + this.value : 'crono[]=' + this.value;
		print_crono_cnt++;
	});

	if(print_crono_cnt < 1){
		openDialogAlert('반출명세서를 선택하세요', 400, 170, function(){});
		return;
	}

	carryingoutPrint(print_crono_list,'multi');
}

// 반출 명세서 인쇄
function carryingoutPrint(crono,mode){
	if(mode == 'multi'){
		var get_params	= crono;
	}else{
		var get_params	= "crono[]=" + crono;
	}
	window.open('../scm/carryingout_prints?' + get_params,'_print','width=1000,height=700,menubar=no,status=no,toobar=no,scrollbars=yes,resizable=no');
}

//------------ ↑↑ carryingout ↑↑------ ↓↓ autoorder ↓↓--------------//

// 자동 발주서 등록
function autoOrderSubmit(){
	var chkCnt		= 0;
	var noTraderCnt	= 0;
	var chkTrader	= true;
	$('input.chk').each(function(){
		if	($(this).attr('checked')){
			if	(!($(this).closest('tr').find('input.trader_seq').val() > 0))	noTraderCnt++;
			chkCnt++;
		}
	});
	if	(noTraderCnt > 0){
		if	($("input[name='substitute_trader_seq']").val() > 0)	chkTrader	= true;
		else														chkTrader	= false;
	}

	if	(chkTrader){
		if	(chkCnt > 0){
			loadingStart();
			$("form[name='listFrm']").submit();
		}else{
			openDialogAlert('선택된 상품이 없습니다.', 400, 170, function(){});
			return false;
		}
	}else{
		$('span#no_trader').html(comma(noTraderCnt));
		openDialog('정규 발주 등록', 'regist_fail_popup', {'width':600});
	}
}

// 대체 거래처 등록
function set_substitute_trader_seq(){
	var trader_seq	= $('div#regist_fail_popup').find("select[name='select_trader_seq']").val();
	if	(trader_seq){
		$("input[name='substitute_trader_seq']").val(trader_seq);
	}else{
		openDialogAlert('거래처 정보가 없는 상품의 거래처를 선택해 하세요..', 400, 170, function(){});
		return false;
	}
	autoOrderSubmit();
}

// 자동 발주서 삭제
function autoRemoveOrder(){
	var chkCnt		= 0;
	$('input.chk').each(function(){
		if	($(this).attr('checked'))	chkCnt++;
	});

	if	(chkCnt > 0){
		loadingStart();
		var orginAct = $("form[name='listFrm']").attr('action');
		$("form[name='listFrm']").attr('action', '../scm_process/remove_auto_order');
		$("form[name='listFrm']").submit();
		$("form[name='listFrm']").attr('action', orginAct);
	}else{
		openDialogAlert('선택된 상품이 없습니다.', 400, 170, function(){});
		return false;
	}
}

//자동발주조건 팝업
function auto_cond_popup(){
	openDialog('자동 발주 상품', 'auto_condition_popup', {'width':850});
}

//발주서 등록 결과 팝업 (정상)
function regist_success_popup(){
	openDialog('발주서 등록 완료', 'regist_success_popup', {'width':600});
}

//거래처 등록 시 선택 여부 확인
function chkTrader(obj){
	var groupVal = $(obj).find('select[name="sc_trader_group"] option:selected').val();
	var traderVal = $(obj).find('select[name="sc_trader"] option:selected').val();

	if(groupVal == '' || traderVal == ''){
		alert('거래처를 선택해 주세요.');
		return false;
	}

	closeDialog('regist_fail_popup');
	return true;
}

function chgHighLight(targetClass, pointClass, obj){
	$(obj).closest('div.highlight-lay').find('.' + targetClass).removeClass(pointClass);
	$(obj).addClass(pointClass);
}

//------------ ↑↑ autoorder ↑↑------ ↓↓ sorder_whs ↓↓--------------//

// 월 선택에 따른 검색값 변경
function chgSorderWhsSearchMonth(m){
	loadingStart();
	$("input[name='sc_month']").val(m);
	$("form[name='listSrcForm']").submit();
}

//------------ ↑↑ sorder_whs ↑↑------ ↓↓ traderaccount ↓↓--------------//

// 거래명세서 인쇄
function openTraderAccountPrint(trader_seq){
	var url				= '../scm_warehousing/traderaccount_print?ispopup=y&' + QUERY_STRING;
	if		(trader_seq > 0)	url		+= '&sc_trader_seq=' + trader_seq;

	window.open(url, 'TRADERACCOUNT_PRINT', 'width=1000px,height=800px,titlebar=no,toolbar=no,scrollbars=yes');
}

// 지급 등록 팝업
function openAddAccount(){
	var params		= '';

	$.ajax({
		type		: 'get',
		url			: './traderaccount_add',
		data		: params, 
		success		: function(result){
			$('div#traderaccount_add').html(result);
			openDialog('지급 등록', 'traderaccount_add', {'width':800,'height':650});
		}
	});
}

// 입력폼 초기화
function inputFormReset(){
	$("input[name='modify_idx']").val('none');
	$("input[name='act_price']").val('0');
	$("input[name='act_memo']").val('');

	$("select[name='trader_group'] option").eq(0).attr('selected', true).change();
	$("select[name='trader_seq'] option").eq(0).attr('selected', true).change();
}

// 거래처 선택에 따른 처리
function choice_trader(obj){
	var optObj		= $(obj).find('option:selected');

	if	(optObj.attr('currency_unit')){
		$('input.trader-currency').val(optObj.attr('currency_unit'));
		$('span.trader-currency').html(optObj.attr('currency_unit'));
	}
	if	(optObj.attr('currency_name')){
		$('input.trader-currency-name').val(optObj.attr('currency_name'));
		$('span.trader-currency-name').html(optObj.attr('currency_name'));
	}
	if	(optObj.attr('currency_exchange')){
		$('input.currency-exchange').val(optObj.attr('currency_exchange'));
		$('span.currency-exchange').html(optObj.attr('currency_exchange'));
		calculate_krw_account();
	}
	if	(optObj.attr('account')){
		$('input.trader-account').val(optObj.attr('account'));
		$('span.trader-account').html(float_comma(optObj.attr('account')));
	}
}

// 원화금액 계산
function calculate_krw_account(){
	var optObj		= $("select[name='trader_seq']").find('option:selected');
	var price		= $("input[name='act_price']").val();
	var exchage		= $("input[name='exchange_price']").val();
	var krw_price	= price * exchage;

	$('input.krw-price').val(krw_price);
	$('.display-krw-price').html(float_comma(krw_price));
}

// 임시 목록에 추가
function addAccountTmp(obj){
	if(!trader_account_form_check()){
		return false;
	}
	var tmpObj			= $('div.add_pay_tmp_list');
	var pay_date		= $("input[name='pay_date']").val();
	var trader_seq		= $("select[name='trader_seq']").val();
	var trader_name		= $("select[name='trader_seq']").find('option:selected').attr('trader_name');
	var currency		= $("select[name='trader_seq']").find('option:selected').attr('currency_unit');
	var act_price		= $("input[name='act_price']").val();
	var exchange		= $("input[name='exchange_price']").val();
	var krw_price		= $("input.krw-price").val();
	var act_memo		= $("input[name='act_memo']").val();
	var cnt				= tmpObj.find('span.tmp-list-count').text().replace('건', '');

	// 추가모드
	if	($("input[name='modify_idx']").val() == 'none'){
		cnt++;
		var total			= 0;
		var appendHTML		= '';
		appendHTML			+= '<tr>';
		appendHTML			+= '<td>' + pay_date + '</td>';
		appendHTML			+= '<td class="its-td-align left">' + trader_name + '(' + currency + ')</td>';
		appendHTML			+= '<td>' + float_comma(act_price) + '</td>';
		appendHTML			+= '<td>';
		appendHTML			+= '<input type="hidden" frmname="거래처" name="tmp_trader_seq[]" value="' + trader_seq + '" />';
		appendHTML			+= '<input type="hidden" name="tmp_currency[]" value="' + currency + '" />';
		appendHTML			+= '<input type="hidden" frmname="금액" isprice="1" name="tmp_act_price[]" value="' + act_price + '" />';
		appendHTML			+= '<input type="hidden" name="tmp_exchange_price[]" value="' + exchange + '" />';
		appendHTML			+= '<input type="hidden" name="tmp_krw_price[]" value="' + krw_price + '" />';
		appendHTML			+= '<input type="hidden" name="tmp_act_memo[]" value="' + act_memo + '" />';
		appendHTML			+= '<input type="hidden" name="tmp_pay_date[]" value="' + pay_date + '" />';
		appendHTML			+= '<button type="button" onclick="modAccountTmp(this);" class="resp_btn v2">수정</button>';
		appendHTML			+= '<button type="button" onclick="delAccountTmp(this);" class="resp_btn v3 ml5">삭제</button>';		
		appendHTML			+= '</td>';
		appendHTML			+= '</tr>';
		tmpObj.find('span.tmp-list-count').html(comma(cnt) + '건');
		tmpObj.find('tbody').append(appendHTML);
		tmpObj.show();

	// 변경모드
	}else{
		var trObj	= tmpObj.find('tbody tr').eq($("input[name='modify_idx']").val());
		trObj.find('td').eq(0).html(trader_name + '(' + currency + ')');
		trObj.find('td').eq(1).html(float_comma(act_price));
		trObj.find('td').find("input[name='tmp_trader_seq[]']").val(trader_seq);
		trObj.find('td').find("input[name='tmp_currency[]']").val(currency);
		trObj.find('td').find("input[name='tmp_act_price[]']").val(act_price);
		trObj.find('td').find("input[name='tmp_exchange_price[]']").val(exchange);
		trObj.find('td').find("input[name='tmp_krw_price[]']").val(krw_price);
		trObj.find('td').find("input[name='tmp_act_memo[]']").val(act_memo);
		trObj.find('button').eq(1).html('변경');
		modAccountFlag = false;
	}

	inputFormReset();
}

// 임시 지급내역 삭제
function delAccountTmp(obj){
	if (modAccountFlag){
		alert('변경 중엔 지급 건수를 삭제하실 수 없습니다.');
		return false;
	}
	var tmpObj	= $('div.add_pay_tmp_list');
	var cnt		= 0;
	var total	= 0;
	$(obj).closest('tr').remove();
	if	(tmpObj.find('tbody tr').length){
		tmpObj.find('tbody tr').each(function(){
			cnt++;
			total		= parseInt(total) + parseInt($(this).find("input[name='tmp_act_price[]']").val());
		});
		$('span.tmp-list-count').html(comma(cnt) + '건');
		$('span.total-price').html(comma(total));
		tmpObj.show();
	}else{
		tmpObj.hide();
	}
}

// 임시 지급내역 수정
var modAccountFlag = false;
function modAccountTmp(obj){	
	if	($(obj).html() == '변경취소'){
		modAccountFlag = false;
		inputFormReset();
		$("input[name='modify_idx']").val('none');
		$(obj).html('변경');
	}else{
		if (modAccountFlag){
			alert('변경 중인 지급내역이 있습니다.');
			return false;
		}
		modAccountFlag = true;
		var idx		= $('div.add_pay_tmp_list tbody tr').index($(obj).closest('tr'));
		$("input[name='modify_idx']").val(idx);

		var trader_seq		= $(obj).closest('tr').find("input[name='tmp_trader_seq[]']").val();
		var act_price		= $(obj).closest('tr').find("input[name='tmp_act_price[]']").val();
		var exchange		= $(obj).closest('tr').find("input[name='tmp_exchange_price[]']").val();
		var krw_price		= $(obj).closest('tr').find("input[name='tmp_krw_price[]']").val();
		var act_memo		= $(obj).closest('tr').find("input[name='tmp_act_memo[]']").val();

		$("input[name='act_price']").val(act_price);
		$("input[name='exchange_price']").val(exchange);
		$('input.krw-price').val(krw_price);
		$('span.display-krw-price').html(float_comma(krw_price));
		$("input[name='act_memo']").val(act_memo);
		$("select[name='trader_group']").find('option').eq(0).attr('selected', true).change();
		$("select[name='trader_seq']").find("option[value='" + trader_seq + "']").attr('selected', true).change();

		$(obj).html('변경취소');
	}
}

// 거래내역 링크 처리
function openAccountDetailInfo(obj){
	if	($(obj).find('input.act_type').val() == 'pay' || $(obj).find('input.act_type').val() == 'def'){
		var currency			= $(obj).find('input.currency').val();
		var currency_name		= $(obj).find('input.currency_name').val();
		var currency_exchange	= $(obj).find('input.currency_exchange').val();
		var price				= $(obj).find('input.act_price').val();
		var trader_name			= $(obj).find('input.trader_name').val() + '(' + currency + ')';
		if			(currency == 'KRW'){
			var priceStr		= float_comma(price) + '원';
		}else if	(currency == 'JPY'){
			var priceStr		= currency + ' ' + float_comma(price)
								+ ' ( 100' + currency_name + ' = ' 
								+ float_comma(krw_exchange(currency, price, currency_exchange))
								+ ' / ' + float_comma(currency_exchange * 100) + '원 )';
		}else{
			var priceStr		= currency + ' ' + float_comma(price)
								+ ' ( 1' + currency_name + ' = ' 
								+ float_comma(krw_exchange(currency, price, currency_exchange))
								+ ' / ' + float_comma(currency_exchange) + '원 )';
		}
		$('div#account_pay_detail').find('td.act-date').html($(obj).find('input.pay_date').val());
		$('div#account_pay_detail').find('td.trader-name').html(trader_name);
		$('div#account_pay_detail').find('td.act-price').html(priceStr);
		$('div#account_pay_detail').find('td.act-memo').html($(obj).find('input.act_memo').val());

		openDialog('지급 내역', 'account_pay_detail', {'width':500,'height':270});
	}else{
		if	($(obj).find('input.act_fkey').val() > 0){
			var url	= './warehousing_regist?whsno=' + $(obj).find('input.act_fkey').val();
			if	($(obj).find('input.act_type').val() == 'carryingout')
				url	= './carryingout_regist?crono=' + $(obj).find('input.act_fkey').val();
			window.open(url);
		}else{
			openDialogAlert('해당 내역을 찾을 수 없습니다.', 400, 170, function(){});
			return false;
		}
	}
}

// 거래처 지급내역 등록
function trader_account_submit(){
	if(trader_account_form_check(true)){
		loadingStart();		
		$('form[name="traderAccountFrm"]').submit();
	}	
}

// 거래처 지급 폼검사
function trader_account_form_check(issubmit){
	var frmObj = $('form[name="traderAccountFrm"]');
	var flag = true;
	
	if(issubmit && $(frmObj).find('input[name*="tmp_"], select[name*="tmp_"]').length > 0) return true;
	$(frmObj).find('input, select').each(function(){
	
		if($(this).attr('frmname') && $(this).val() == ''){
			openDialogAlert($(this).attr('frmname')+'을(를) 입력하세요.', 400, 170, function(){});
			flag = false;
			return false;			
		}else if($(this).attr('isprice') && $(this).val() == '0'){
			openDialogAlert($(this).attr('frmname')+'은(는) 0 이상 입력해 주세요.', 400, 170, function(){});
			flag = false;
			return false;
		}
	});

	return flag;
}

//------------ ↑↑ traderaccount ↑↑------ ↓↓ ledger ↓↓--------------//

// 기타 검색조건 변경시
function searchformchange(){
	$("form[name='listSrcForm']").submit();
}

// 선택시 결과 처리
function sel_ledger(goods_seq,option_type,option_seq,out_supply_price){
	var wh_seq		= $("select[name='sc_wh_seq']").val();
	var url			= './ledger_detail'
					+ '?goods_seq=' + goods_seq
					+ '&option_type=' + option_type
					+ '&option_seq=' + option_seq
					+ '&out_supply_price=' + out_supply_price
					+ '&' + server_query_string;
	var popup_name	= 'LEDGER_DETAIL_' + goods_seq + option_type + option_seq;
	window.open(url, popup_name, 'width=1200,height=800,titlebar=n,toolbar=n,scrollbars=yes');
}

// 재고수불부 인쇄
function ledgerPrint(){
	var chk		= false;
	var params	= server_query_string;
	$("input[name='optioninfo[]']").each(function(){
		if	($(this).attr('checked')){
			chk	= true;
			params	+= '&optioninfo[]=' + $(this).val();
		}
	});
	if	(!chk){
		openDialogAlert('선택된 상품이 없습니다.', 400, 170, function(){});
		return false;
	}

	var url	= './ledger_print?' + params;
	window.open(url, 'LEDGER_PRINT', 'width=600,height=800,titlebar=n,toolbar=n,scrollbars=yes');
}

// 검색 년도 선택에 따른 처리
function chgYearVal(obj){
	var _val = $(obj).closest('table').find("select[name='sc_quater'] option:selected").val();	
	$(obj).closest('table').find("select[name='sc_quater'] option:eq(6)").replaceWith("<option value='year'>"+$(obj).val()+"년</button>");
	if(_val=="year")$(obj).closest('table').find("select[name='sc_quater'] option:eq(6)").attr("selected", "selected");	
}

// 월/분기 선택 영역 변경
function chgMonthQuaterArea(obj){
	var className	= 'sc-' + $(obj).val() + '-select-area';
	$(obj).closest('td').find('span.sc-month-select-area').hide();
	$(obj).closest('td').find('span.sc-quater-select-area').hide();
	$(obj).closest('td').find('span.' + className).show();
}

// 월 선택 처리
function selected_sc_month(obj){
	$('span.sc-month-select-area').find('span.lightblue').removeClass('lightblue');
	$(obj).closest('span.btn').addClass('lightblue');
	$(obj).closest('span.sc-month-select-area').find('input.sc_month').val($(obj).attr('m'));
}

// 분기 선택 처리
function selected_sc_quater(obj){
	$('span.sc-quater-select-area').find('span.lightblue').removeClass('lightblue');
	$(obj).closest('span.btn').addClass('lightblue');
	$(obj).closest('span.sc-quater-select-area').find('input.sc_quater').val($(obj).attr('q'));
}

//------------ ↑↑ ledger ↑↑------ ↓↓ util & etc↓↓--------------//

// 기본 submit
function scmSubmit(){
	loadingStart();
	$("form[name='detailForm']").submit();
}

// 상품에 대한 창고별 재고 팝업
function openGoodsWarehouseStock(id, returnFunc, goods_seq, option_type, option_seq){
	if	(id && goods_seq > 0){
		var params	= 'targetID=' + id + '&goods_seq=' + goods_seq;
		if	(option_type && option_seq){
			params	+= '&option_type=' + option_type + '&option_seq=' + option_seq;
		}
		if	(returnFunc)
			params	+= '&returnFunc=' + returnFunc;

		// 새로 가져옴
		$.ajax({
			type	: 'get',
			url		: '../scm/scm_warehouse_stock',
			data	: params,
			success	: function(result){
				$('div#' + id).html(result);
				var width		= Number($('div#' + id).find('table').attr("width")) + 60;
				if	(width > 1000)	width	= 1000;
				var rowCount	= $('div#' + id).find('table tbody tr').length;
				var height		= 260 + (rowCount * 30);
				openDialog('창고별 재고', id, {'width':width,'height':height,'overflow-x':'scroll'});
			}
		});
	}else{
		openDialogAlert('상품이 없습니다.', 400, 170, function(){});
		return false;
	}
}

// 수입안내 팝업 오픈
function open_import_information(){
	
	if	($('div#importInformationLay').html()){
		openDialog('수입 안내', 'importInformationLay', {'width':1250,'height':800});
	}else{
		$.ajax({
			type	: 'get',
			url		: '../scm/import_information',
			success	: function(result){
				$('div#importInformationLay').html(result);
				openDialog('수입 안내', 'importInformationLay', {'width':1250,'height':800});
			}
		});
	}
}

// 정산안내 팝업 오픈
function open_account_information(){
	
	if	($('div#accountInformationLay').html()){
		openDialog('수입 – 정산금액 및 원가금액', 'accountInformationLay', {'width':1250,'height':620});
	}else{
		$.ajax({
			type	: 'get',
			url		: '../scm/account_information',
			success	: function(result){
				$('div#accountInformationLay').html(result);
				openDialog('수입 – 정산금액 및 원가금액', 'accountInformationLay', {'width':1250,'height':620});
			}
		});
	}
}

// javascript Trim 처리
function jsTrim(val){
	return val.replace(/(^\s*)|(\s*$)/gi, '');
}

// js 페이징 생성 ( pages = result.page, clickFunc = string )
function getPagingHTML(pages, clickFunc){
	var page			= 0;
	var pageHTML		= '';
	var pageCnt			= pages.page.length;

	if	(pageCnt > 1){

		// 이전 페이징 블럭
		if	(pages.page[0] > 1)								pageHTML	+= '<span onclick="' + clickFunc + '(\'' + (pages.page[0] - 10) + '\');">[이전]</span>';

		for	( var i = 0; i < pageCnt; i++){
			page	= pages.page[i];
			if	(pages.nowpage == page)						pageHTML	+= '<span class="current">' + page + '</span>';
			else											pageHTML	+= '<span class="page-' + page + '"  onclick="' + clickFunc + '(\'' + page + '\');">' + page + '</span>';
		}

		// 다음 페이징 블럭
		if	(pages.totalpage > pages.page[(pageCnt - 1)])	pageHTML	+= '<span onclick="' + clickFunc + '(\'' + (pages.page[0] + 10) + '\');">[다음]</span>';

	}

	return pageHTML;
}

// 환율에 따른 환전 ( 외화 -> 한화 )
function krw_exchange(type, price, user_exchange){
	var currency		= type.toUpperCase();
	var cfg				= exchanges[currency];
	var result			= price;
	var exchange		= cfg['currency_exchange'];
	if	(user_exchange != 'cfg')	exchange	= user_exchange;
		
	if	(currency != 'KRW')	result		= float_calculate('multiply', price, exchange);	// 환전
	result			= calculate_cut_price('KRW', result);	// 절사

	return result;
}

// 한화를 해당 외화로 환전 ( 한화 -> 외화 )
function exchange_krw(type, price){
	var currency	= type.toUpperCase();
	var cfg			= exchanges[currency];
	var result		= price;

	// 환전
	if	(currency != 'KRW'){
		result		= float_calculate('divide', price, cfg['currency_exchange']);
	}

	result			= calculate_cut_price(currency, result);

	return result;
}

// 해당 금액의 부가세 계산
function calculate_tax_price(currency, price){
	var tax_rate	= 0.1;
	// currency에 따라 tax_rate는 조정될 수 있다. ( 조정될 시 조정되는 값은 cfg_exchange에 넣을 예정 )

	var tax_price	= float_calculate('multiply', price, tax_rate);
	tax_price		= calculate_cut_price(currency, tax_price);

	return tax_price;
}

// 절사 설정에 따른
function calculate_cut_price(currency, price){
	var result	= price;
	var cfg		= exchanges[currency];
	if	(!cfg.cut_type) {
		openDialogAlert('기초정보가 없습니다. 재고기초 > 기초정보 설정해주세요.', 400, 170, function(){
			document.location.href = '/admin/scm_basic/config'; 
		});
		return false;
	}
	if	(cfg.use_status == 'Y'){
		result		= eval('Math.' + cfg.cut_type + '(result / cfg.cut_unit)');
		result		= float_calculate('multiply', result, cfg.cut_unit);
	}

	return result;
}

// javascript 소숫점 4칙연산에 버그가 있어서 별도 처리함. ( ex: 1.2 + 0.12 = 1.31999999999998이됨. )
function float_calculate(type, num1, num2){
	// 소숫점이 있는지 확인
	var tmp							= new Array();
	var dec = decCnt = dec1 = dec2 = result = 0;
	num1			= num1 + '';
	num2			= num2 + '';
	if	(num1.search(/\./) != -1){
		tmp		= num1.split('.');
		dec1	= tmp[1].length;
	}
	if	(num2.search(/\./) != -1){
		tmp		= num2.split('.');
		dec2	= tmp[1].length;
	}
	decCnt		= dec1;
	if	(type == 'multiply'){
		decCnt		= parseInt(dec1) + parseInt(dec2);
	}else{
		if	(dec1 < dec2)	decCnt	= dec2;
	}
	dec			= Math.pow(10, decCnt);

	// 사칙연산 처리
	switch(type){
		// 더하기
		case 'plus':
			result	= ( ( num1 * dec ) + ( num2 * dec ) ) / dec;
		break;
		// 빼기
		case 'minus':
			result	= ( ( num1 * dec ) - ( num2 * dec ) ) / dec;
		break;
		// 곱하기
		case 'multiply':
			result	= ( ( num1 * dec ) * ( num2 * dec ) ) / Math.pow(dec, 2);
		break;
		// 나누기
		case 'divide':
			result	= ( num1 * dec ) / ( num2 * dec );
		break;
	}

	result	= result + '';
	if	(result.search(/\./) != -1){
		tmp		= result.split('.');
		if	(decCnt < tmp[1].length)	result	= new Number(result).toFixed(decCnt);
	}

	return result;
}

// 진행바 생성
function setProgressBar(title, link){
	$('body').append('<div id="progressbar"></div>');
	var useTitle	= false;
	if	(title)	useTitle	= true;

	progObj		= $("#progressbar").fmprogressbar({
		'debugMode'			: false, 
		'useDetail'			: false, 
		'loadMode'			: false, 
		'useTitle'			: useTitle, 
		'zIndex'			: '1000', 
		'barHeight'			: '16', 
		'barOutPadding'		: '10', 
		'titleBarText'		: title, 
		'defaultLink'		: link, 
		'procgressEnd'		: 'completeSendStock' 
	});
}

// 진행바 오픈
function openProgress(){
	if	(progObj)	progObj.openProgress();
}

// 진행바 종료
function closeProgress(){
	if	(progObj)	progObj.closeProgress();
	progObj	= '';
}

// 처리 페이지 추가
function addProcFrame(link){
	if	(progObj)	progObj.addProcFrame(link);
}

// 처리 페이지 URL 변경
function chgProcFrameSrc(link, num){
	if	(progObj)	progObj.chgProcFrameSrc(link, num);
}

// 처리 퍼센트 증가
function addProgPercent(addPer){
	if	(progObj)	progObj.addPercent(addPer);
}

// 처리 로그 추가
function addDetailLog(str){
	if	(progObj)	progObj.addDetailLog(str);
}

// 하위 매장에 재고 전송 완료 후 메시지 및 이동 URL
var endTitle	= '';
var returnURL	= '';
function setSendCompleteBack(title, url){
	endTitle	= title;
	returnURL	= url;
}

// 하위 매장에 재고 전송 완료
function completeSendStock(){
	if	(returnURL == 'close'){
		window.close();
	}else{
		var msg	= '다른 매장에 재고정보 전송이 완료되었습니다.';
		if	(endTitle)	msg		= msg + '<br/>' + endTitle;
		closeProgress();
		openDialogAlert(msg, 400, 170, function(){
			if			(returnURL == 'reload'){
				location.reload();
			}else if	(returnURL){
				location.href	= returnURL;
			}
		});
	}
}

// 목록 삭제용 submit
function deleteSubmit(){
	var frmObj		= $("form[name='listFrm']");
	var chkStatus	= false;
	frmObj.find('input.chk').each(function(){
		if	($(this).attr('checked')){
			chkStatus	= true;
			return false;
		}
	});

	if	(!chkStatus){
		openDialogAlert('선택된 항목이 없습니다.', 400, 170, function(){});
		return false;
	}else{
		loadingStart();
		frmObj.submit();
	}
}

// 중료표시 일괄 처리
function chk_favorite_all(obj, type){
	if	($(obj).hasClass('checked'))	var chk	= '0';
	else								var chk	= '1';

	var params	= 'type=' + type + '&chk=' + chk;
	$('.select-star').each(function(){
		if	(($(this).hasClass('checked') && chk === '0') || (!$(this).hasClass('checked') && chk === '1')){
			params	= params + '&seq[]=' + $(this).attr('seq');
			if	($(this).hasClass('checked') && chk === '0')	$(this).removeClass('checked');
			else												$(this).addClass('checked');
		}
	});

	$.ajax({
		type	: 'post',
		url		: '../scm/chk_favorite',
		data	: params,
		global	: false,
		success	: function(result){
			if	(chk === '1')	$(obj).addClass('checked');
			else				$(obj).removeClass('checked');
		}
	});
}

// 중요표시 처리
function chk_favorite(obj, type){
	if	($(obj).attr('seq') > 0 && type){
		var params	= 'type=' + type + '&seq=' + $(obj).attr('seq');
		if	($(obj).hasClass('checked'))	var chk	= '0';
		else								var chk	= '1';
		params	= params + '&chk=' + chk;

		$.ajax({
			type	: 'post',
			url		: '../scm/chk_favorite',
			data	: params,
			global	: false,
			success	: function(result){
				if	(chk === '1')	$(obj).addClass('checked');
				else				$(obj).removeClass('checked');
			}
		});
	}
}

// 목록 검색 폼 submit
function list_src_form_submit(){
	var orderby = $(".contents_container").find("select[name='orderby']").val();
	var perpage = $(".contents_container").find("select[name='perpage']").val();
	var sc_display_type = $(".contents_container").find("select[name='sc_display_type']").val();

	$("form[name='listSrcForm']").find("input[name='orderby']").val(orderby);
	$("form[name='listSrcForm']").find("input[name='perpage']").val(perpage);
	$("form[name='listSrcForm']").find("input[name='sc_display_type']").val(sc_display_type);

	$("form[name='listSrcForm']").submit();
}

// 엑셀 다운로드
function excel_download(type){
	if	(type == 'select'){
		// 선택다운, 검색다운 값 목록에 추가
		if	($("form[name='listFrm']").find("input[name='excel_type']").attr('name')){
			$("form[name='listFrm']").find("input[name='excel_type']").val('select');
		}else{
			var addInput	= '<input type="hidden" name="excel_type" value="select" />';
			$("form[name='listFrm']").append(addInput);
		}

		// 검색에 정렬값이 있는 경우 목록에 정렬값 추가
		if	($("form[name='listSrcForm']").find("select[name='orderby']").val()){
			if	($("form[name='listFrm']").find("input[name='orderby']").val()){
				$("form[name='listFrm']").find("input[name='orderby']").val($("form[name='listSrcForm']").find("select[name='orderby']").val());
			}else{
				var addInput	= '<input type="hidden" name="orderby" value="' + $("form[name='listSrcForm']").find("select[name='orderby']").val() + '" />';
				$("form[name='listFrm']").append(addInput);
			}
		}

		// 엑셀 양식이 있는 경우 목록에 양식값 추가
		if	($("form[name='listSrcForm']").find("input[name='excel_form']").val()){			
			if	($("form[name='listFrm']").find("input[name='excel_form']").val()){
				$("form[name='listFrm']").find("input[name='excel_form']").val($("form[name='listSrcForm']").find("input[name='excel_form']").val());
			}else{
				var addInput	= '<input type="hidden" name="excel_form" value="' + $("form[name='listSrcForm']").find("input[name='excel_form']").val() + '" />';
				$("form[name='listFrm']").append(addInput);
			}
		}
		var tmp_target = $("form[name='listFrm']").attr('target');
		var tmp_method = $("form[name='listFrm']").attr('method');
		var tmp_action = $("form[name='listFrm']").attr('action');

		$("form[name='listFrm']").attr('target', 'actionFrame');
		$("form[name='listFrm']").attr('method', 'post');
		$("form[name='listFrm']").attr('action', $("input[name='actionURL']").val());
		$("form[name='listFrm']").submit();
		$("form[name='listFrm']").attr('target', tmp_target);
		$("form[name='listFrm']").attr('method', tmp_method);
		$("form[name='listFrm']").attr('action', tmp_action);
	}else{
		
		if	($("form[name='listSrcForm']").find("input[name='excel_type']").attr('name')){
			$("form[name='listSrcForm']").find("input[name='excel_type']").val('');
		}else{
			var addInput	= '<input type="hidden" name="excel_type" value="" />';
			$("form[name='listSrcForm']").append(addInput);			
		}	
		
		$("form[name='listSrcForm']").attr('target', 'actionFrame');
		$("form[name='listSrcForm']").attr('method', 'post');
		$("form[name='listSrcForm']").attr('action', $("input[name='actionURL']").val());
		$("form[name='listSrcForm']").submit();

		$("form[name='listSrcForm']").attr('target', '');
		$("form[name='listSrcForm']").attr('method', 'get');
		$("form[name='listSrcForm']").attr('action', '');		
	}
}

function excel_download_spout(type){
	var excel_type	= type;
	var params = {};

	if( excel_type == 'search' ){
		params['excel_type']	= 'search';
		params['scm_code']		= 'search';
	} else {
		var scm_code = $("input[type='checkbox'][name='option_info_arr[]']:checked:checked").map(function (k, v) {
		   return $(v).val();
		}).get();

		if(!scm_code.length){
			alert("선택값이 없습니다.");
			return;
		} else {
			params['scm_code']		= scm_code;
			params['excel_type']	= 'select';
		}
	}
	var queryString = $('form[name="listSrcForm"]').serializeArray();

	jQuery.each(queryString, function(i, field){
		if(field.name != 'actionURL'){
			if(field.name == 'sc_goods_kind[]' && field.value.length > 0){
				if(!params['sc_goods_kind']){
					params['sc_goods_kind'] = [];
				}
				params['sc_goods_kind'].push(field.value);
			} else {
				params[field.name] = field.value;
			}
		}
	});

	$.ajax({      
		type: "POST",  
		url: '/cli/excel_down/create_scmgoods',      
		data: params, 
		success:function(args){ 
			var exe = args.split('.').pop();
			if(exe == "csv" || exe == "zip" || exe == "xlsx"){
				window.location.href = '/admin/excel_spout/file_download?url=' + args; 
			} else {
				alert(args);
			}
		}, error:function(e){  
			alert(e.responseText);  
		}  
	});
}

// 창고 선택 시 하위 로케이션 selectbox 완성 ( 하위 로케이션 class sc-location )
function search_select_warehouse(obj){
	var wh_seq		= $(obj).val();
	$('select.sc-location').html('<option value="">전체</option>');
	if	(wh_seq > 0){
		$.ajax({
			type	: 'get',
			url		: '../scm/getWarehouseLocationList',
			data	: 'wh_seq=' + wh_seq,
			dataType: 'json',
			success	: function(result){
				if	(result.length > 0){
					for	(var i = 0; i < result.length; i++){
						$('select.sc-location').append('<option value="' + result[i].location_position + '">' + result[i].location_code + '</option>');
					}
				}
			}
		});
	}
}

// 분류관리 팝업 오픈 jtree menu event 호출 문제로 iframe으로 처리함
function openScmCategoryPopup(){
	openDialog('분류관리 - 쇼핑몰에 있는 판매용 분류(카테고리, 브랜드, 지역)와 관계 없는 <u>재고관리용 분류</u>입니다.', 'scm_category', {'width':800, 'height':492});
	$('div#scm_category').css({'padding':'0','overflow':'hidden'});
}

// 거래처 그룹 선택 시 해당 그룹의 거래처 목록 추출
function get_trader_to_group(obj){
	var groupName	= $(obj).val();
	var needData	= $(obj).attr('needData');
	var traderObj	= $(obj).next();
	var selectKey	= traderObj.val();
	var appendHTML	= '';

	if	(excelTableObj)	resetExcelTableList();

	traderObj.find('option').each(function(){
		if	($(this).attr('value'))	$(this).remove();
	});

	// 새로 가져옴
	$.ajax({
		type	: 'get',
		url		: '../scm/getTraderData',
		data	: 'perpage=99999999&sc_trader_group=' + encodeURIComponent(groupName),
		dataType: 'json',
		success	: function(result){
			if	(result.record.length > 0){
				var data	= '';
				for	(var i = 0; i < result.record.length; i++){
					data		= result.record[i];
					appendHTML	= '<option value="' + data.trader_seq + '" ';
					appendHTML  += selectKey == data.trader_seq ? 'selected="selected"' : '';
					if	(needData == 'y'){
						for	(var key in data){
							if	(data[key])	appendHTML	+= ' ' + key + '="' + data[key] + '"';
						}
					}
					appendHTML	+= '>' + data.trader_name + ' ' + data.currency_unit + '</option>';

					traderObj.append(appendHTML);
				}
				traderObj.change();
			}
		}
	});
}

// 분류 선택 팝업
function setScmCategory(id){
	var scm_category	= $('input#input_' + id).val();
	// 새로 가져옴
	$.ajax({
		type	: 'get',
		url		: '../scm/scm_category',
		data	: 'return_func=chgScmCategory&return_id=' + id + '&category=' + scm_category,
		success	: function(result){
			$('div#div_' + id).html(result);
		}
	});
	openDialog('분류연결', 'div_' + id, {'width':800});
}

// 분류 선택 처리
function chgScmCategory(id, code, name){
	var category_name	= name.replace(/^\>/, '').replace(/\>\>/, '').replace(/\>/g, ' > ');
	$('span#span_' + id).html(category_name);
	$('input#input_' + id).val(code);

	closeDialog('div_' + id);
}

// 상품에 대한 매장별 판매정보 팝업
function openGoodsStoreSaleInfo(id, goods_seq, option_type, option_seq){
	if	(id && goods_seq > 0){
		var params	= 'goods_seq=' + goods_seq;
		if	(option_type && option_seq)
			params	+= '&option_type=' + option_type + '&option_seq=' + option_seq;

		// 새로 가져옴
		$.ajax({
			type	: 'get',
			url		: '../scm/scm_stores_sale_info',
			data	: params,
			success	: function(result){
				$('div#' + id).html(result);
				var width	= Number($('div#' + id).find('table').attr("width")) + 60;				
				if	(width > 1000)	width	= 1000;
				//var height	= Math.round(width * 0.8);
				var height	= 500;
				area_help_tooltip($('div#' + id));
				openDialog('매장별 안전재고', id, {'width':width,'height':height,'overflow-x':'scroll'});
			}
		});
	}else{
		openDialogAlert('상품이 없습니다.', 400, 170, function(){});
		return false;
	}
}

// 환율정보창 on/off
function onoffExchangeinfo(obj){
	var infoLay	= $(obj).find('div');
	if	(infoLay.css('display') == 'none'){
		infoLay.show();
	}else{
		infoLay.hide();
	}
}

// 하위 분류를 추출해서 select에 추가해 줌
function getChildScmCategory(obj, selectorClass){
	var category_code	= $(obj).val();
	var depth			= $(obj).attr('depth');
	if	(category_code && depth < 4){
		// 하위 분류 초기화
		for	( var c = depth; c < 4; c++){
			$('select.' + selectorClass).eq(c).find('option').remove();
			$('select.' + selectorClass).eq(c).append('<option value="">' + ( parseInt(c) + 1 ) + '차 분류</option>');
		}

		// 새로 가져옴
		$.ajax({
			type		: 'get',
			url			: '../scm/scm_child_category',
			data		: 'category=' + category_code,
			dataType	: 'json', 
			success		: function(result){
				if	(result.status){
					var data	= '';
					var len		= result.category.length;
					for	( var c = 0; c < len; c++ ){
						data	= result.category[c];
						$('select.' + selectorClass).eq(depth).append('<option value="' + data.category_code + '">' + data.title + '</option>');
					}
				}
			}
		});
	}
}

// 하위 분류를 추출해서 select에 추가해 줌 (검색옵션용)
function getChildScmCategoryName(obj, selectorName, selectCode){
	var category_code	= $(obj).val();
	var depth			= $(obj).attr('depth');
	if	(category_code && depth < 4){
		// 하위 분류 초기화
		for	( var c = depth; c < 4; c++){
			$('select[name="' + selectorName + '"]').eq(c).find('option').remove();
			$('select[name="' + selectorName + '"]').eq(c).append('<option value="">' + ( parseInt(c) + 1 ) + '차 분류</option>');
		}

		// 새로 가져옴
		$.ajax({
			type		: 'get',
			url			: '../scm/scm_child_category',
			data		: 'category=' + category_code,
			dataType	: 'json', 
			success		: function(result){
				if	(result.status){
					var data	= '';
					var len		= result.category.length;

					for	( var c = 0; c < len; c++ ){
						data		= result.category[c];
						select_attr = '';
						if(selectCode){
							select_attr = data.category_code == selectCode[depth] ? 'selected="selected"' : '';
						}

						$('select[name="' + selectorName + '"]').eq(depth).append('<option value="' + data.category_code + '" '+select_attr+'>' + data.title + '</option>');						
					}

					getChildScmCategoryName($('select[name="' + selectorName + '"][depth="'+(parseInt(depth)+1)+'"]'), selectorName, selectCode);
				}
			}
		});
	}
}

// 카테고리 선택에 따른 하위 카테고리 추출
function selectScmCategory(obj){
	var depth	= $(obj).attr('depth');
	var code	= $(obj).val();

	if	(depth < 4){
		// 선택된 분류 하위 분류 선택박스 초기화
		$('select[name="sc_scm_category[]"]').each(function(){
			if($(this).attr('depth') > depth){
				var opt = $(this).find('option:first-child');
				$(this).html(opt);
			}
		});

		$.ajax({
			type		: 'get',
			url			: '../scm/scm_child_category',
			data		: 'category=' + code,
			dataType	: 'json', 
			success		: function(result){
				if	(result.status){
					var data	= '';
					var html	= '';
					var len		= result.category.length;
					for	( var i = 0; i < len; i++){
						data	= result.category[i];
						html	= '<option value="' + data.category_code + '" >' + data.title + '</option>';
						$('select[name="sc_scm_category[]"]').eq(depth).append(html);
					}
				}
			}
		});
	}
}

// 기간 간편설정
function set_date(obj, selectedType){
	if	(selectedType == 'all'){
		var sDate	= '';
		var eDate	= '';
	}else{
		var dateRes	= getRangeDate(selectedType);
		var sDate	= dateRes[0];
		var eDate	= dateRes[1];
	}

	$(obj).closest('span.btn-list').find('span.lightblue').removeClass('lightblue');
	$(obj).closest('span').addClass('lightblue');
	$("input[name='date_selected']").val(selectedType);
	$("input[name='sc_sdate']").val(sDate);
	$("input[name='sc_edate']").val(eDate);
}

// 종류에 따른 시작일과 종료일 계산
function getRangeDate(kind){
	var d1		= new Date();
	var d2		= new Date();
	var w		= d1.getDay();
	switch(kind){
		case 'yesterday':
			d1.setDate(d1.getDate() - 1);
			d2.setDate(d2.getDate() - 1);
		break;
		case 'calendar_thisweek':	// 달력기준 ( 일 ~ 토 )
			if	(w == 0)	w	= 1;
			else			w++;
			d1.setDate(d1.getDate() - (w - 1));
			d2.setDate(d2.getDate() + (7 - w));
		break;
		case 'calendar_lastweek':	// 달력기준 ( 일 ~ 토 )
			if	(w == 0)	w	= 1;
			else			w++;
			d1.setDate(d1.getDate() - (7 + w));
			d2.setDate(d2.getDate() - (w + 1));
		break;
		case 'work_thisweek':		// 회계기준 ( 월 ~ 일 )
			if	(w == 0)	w	= 7;
			d1.setDate(d1.getDate() - (w - 1));
			d2.setDate(d2.getDate() + (7 - w));
		break;
		case 'work_lastweek':		// 회계기준 ( 월 ~ 일 )
			if	(w == 0)	w	= 7;
			d1.setDate(d1.getDate() - (6 + w));
			d2.setDate(d2.getDate() - w);
		break;
		case 'thismonth':
			y	= d1.getFullYear();
			m	= d1.getMonth() + 1;
			d	= d1.getDate();
			d1	= new Date(m + '/1/' + y);
			d2	= new Date(m + '/1/' + y);
			d2.setMonth(d2.getMonth() + 1);
			d2.setDate(d2.getDate() - 1);
 		break;
		case 'lastmonth':
			y	= d1.getFullYear();
			m	= d1.getMonth() + 1;
			d	= d1.getDate();
			d1	= new Date(m + '/1/' + y);
			d2	= new Date(m + '/1/' + y);
			d1.setMonth(d1.getMonth() - 1);
			d2.setDate(d2.getDate() - 1);
		break;
	}
	var ret		= new Array();
	var y		= '';
	var m		= '';
	var d		= '';
	y			= d1.getFullYear();
	m			= (d1.getMonth() < 9) ? '0' + (d1.getMonth() + 1) : (d1.getMonth() + 1);
	d			= (d1.getDate() < 10) ? '0' + d1.getDate() : d1.getDate();
	ret[0]		= y + '-' + m + '-' + d;
	y			= d2.getFullYear();
	m			= (d2.getMonth() < 9) ? '0' + (d2.getMonth() + 1) : (d2.getMonth() + 1);
	d			= (d2.getDate() < 10) ? '0' + d2.getDate() : d2.getDate();
	ret[1]		= y + '-' + m + '-' + d;

	return ret
}

// 목록 체크박스 일괄 체크/해제
function scmAllCheck(obj){
	if	($(obj).attr('checked'))	$(obj).closest('table').find('input.chk:enabled').attr('checked', true);
	else							$(obj).closest('table').find('input.chk:enabled').attr('checked', false);
}

// 총평균법 안내
function openTotalAverageMethodInfo(){
	if (!$('body').find('div#TotalAverageMethodInfoLay').html()){
		var html	= '총평균법은 재고자산의 원가를 평가하는 방법 중 하나로<br/> '
					+ '기초자산 금액에 일정기간의 매입합계금액을 합하고<br/> '
					+ '이를 총수량(기초재고+매입수량)으로 나누어 평균원가를 산출하는 방식입니다.';
		$('body').append('<div id="TotalAverageMethodInfoLay">' + html + '</div>');
	}

	openDialog('총평균법', 'TotalAverageMethodInfoLay', {'width':500, 'height':140});
}

// 사용 창고 비활성화시 기본 출고/반품 창고 선택 값 해제 및 기본창고 자동 선택 by hed #23661	
function initStoreSelected(chk_wh, type){
	if(type == "") type='change';
	var arr_target = ['export_wh','return_wh'];
	if(typeof($(chk_wh).attr('checked')) === 'undefined'){
		var return_wh = $(chk_wh).closest("tr").find("input[name='return_wh']");
		var export_wh = $(chk_wh).closest("tr").find("input[name='export_wh']");
		var wh_radio =  $(chk_wh).closest("tr").find("input[type='radio']");
			
		if(return_wh.attr('checked')==="checked"||export_wh.attr('checked')==="checked") 
		{	
			alert('기본창고를 변경해주세요');
			$(chk_wh).attr('checked', 'checked')
			wh_radio.attr('disabled', false);			
		}else{
			wh_radio.attr('checked', false);			
			wh_radio.attr('disabled', true);			
		}
				
	}else{
		for(var index in arr_target){
			var target_el = $(chk_wh).parent().parent().parent().find("input[name='"+arr_target[index]+"']");
			target_el.attr('disabled', false);
		}
	}
	if(type == 'change'){
		for(var index in arr_target){
			var target_el = $("input[name='"+arr_target[index]+"']:enabled").eq(0);
			if(target_el.length > 0){
				target_el.attr('checked', true);
			}
		}
	}
}

function stockTypeDisabled(val) {
	var radio = $("input[name='sc_stock_type'][value='"+val+"']");
	radio.closest('td').find('select').attr('disabled',true);
	radio.closest('label').next('select').attr('disabled',false);
	$("#sc_stock_name").html(radio.closest('label').text());
}

$(document).ready(function(){
	$('input#chkAll').click(function(){
		scmAllCheck($(this));
	});

	$("input[name='barcode_reader']").bind('keyup', function(e){
		var evt	= e || window.event;
		if	(evt.keyCode == 13){
			$(this).closest('td').find('button').click();
			$(this).val('');
			$(this).focus();
		}
	});
	
	// 쇼핑몰 창고 수정 시 
	if(location.href.indexOf('/admin/scm_basic/store_regist') > -1){	
		// 사용 창고 비활성화시 기본 출고/반품 창고 선택 값 해제 및 기본창고 자동 선택 by hed #23661	
		$("input[name='chk_wh\[\]']").bind('click', function(){
			initStoreSelected(this);
		});
		$("input[name='chk_wh\[\]']").each(function(){
			initStoreSelected(this, 'init');
		});
	}

	// 엑셀 다운로드
	$(".excelDownloadBtn").on("click", function(){
		var cnt = $("form[name='listFrm']").find('input.chk:checked').length;
		$("#selectCnt").html(cnt);
		openDialog('엑셀 다운로드','excelDownload', {'width':650});
	})

	$("#downloadBtn").on("click", function(){
		var scope_type = $("input[name='scope_type']:checked").val();
		excel_download(scope_type);

		closeDialog('excelDownload')
	});
	

	// 상품관리 재고 검색 
	$("input[name='sc_stock_type']").on('click', function() {
		stockTypeDisabled($(this).val());
	});

});
