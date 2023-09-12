var marketProductGrid	= {};
var logGrid				= {};

$('document').ready(function(){
	
	gSearchForm.init({'pageid':'market_product_list','divSelectLayId':'distTop','sc':searchObj});

	marketProductGrid	= $("#marketProductGrid");	
	standByFields		= [
		{ title : '선택',
		  name  : 'checkbox',
		  checkbox : true,
		  headerTemplate: function() {
			return $("<label class='resp_checkbox'><input type='checkbox' class='headerSelector'></label>").on("click", function () {
				if($('.gridCheck:checked').length > 0) {
					$('.gridCheck').attr('checked', false);
				} else {
					$('.gridCheck').attr('checked', true);
				}

				$('.gridCheck').trigger('change');
			});
		  },
		  itemTemplate: function(_, item) {
				//대기열 상태는 변경안됨.
				if (item.last_result == 'P')
					return;

				var checkOpt		= {};
				checkOpt.type		= "checkbox";
				checkOpt.class		= "gridCheck";
				checkOpt.target		= 'check_'+item.fm_market_product_seq;
				checkOpt.checkType	= 'distSelect';
				return $("<input>").attr(checkOpt).on("change", function () {
					if($(this).is(":checked")) {
						item.selected		= true;
						marketProductGrid.jsGrid("editItem", item);
						marketProductGrid.jsGrid("checkedRow", 'checked', item);
					}else{
						item.selected		= false;
						marketProductGrid.jsGrid("editItem", item);
						marketProductGrid.jsGrid("checkedRow", 'unchecked', item);
					}

					if ($('.gridCheck:checked').length > 0)
						$('.headerSelector').text('해제');
					else
						$('.headerSelector').text('선택');

				}).val(item.fm_market_product_seq);
			},
			align: "center",
			width: 40
		},
		{ title:'순번', name: "no", type: "number", width: 40 },
		{ title:'마켓명', name: "market_name", type: "text", width: 80 },
		{ title:'수정', name: "market_product_edit", type: "text",align: "center",width: 60 },
		{ title:'판매자<br/>아이디', name: "seller_id", type: "text", width: 80 },
		{ title:'쇼핑몰<br/>상품번호', name: "fm_product_link", type: "text",
			itemTemplate: function(fm_product_link, item) {
				if(item.delete_goods_seq == "D"){
					fm_product_link = "삭제상품"
				}
				return fm_product_link;
			},
			width: 80 },
		{ title:'마켓<br/>상품번호', name: "market_product_link", type: "text", width: 100 },
		{ title:'마켓 상품명', name: "market_product_name", type: "text",
			itemTemplate: function(productName, item) {
				//$row['market_product_name_link']	= "<a href='/admin/market_connector/{$row['market']}_add_info?fmMarketProduceSeq={$row['fm_market_product_seq']}' target='_fmAddInfoLink'>{$row['market_product_name']}</a>";
				if (item.manual_matched == 'Y')
					return  "<span style='color:blue'>[수동매칭]</span>" + productName;
				else
					return productName;

			},
			width: 200 },
		{ title:'마켓<br/>판매상태', name: "market_sale_status_text", type: "text", width: 70, align:'center'},
		{ title:'판매 종료일', name: "market_close_date", type: "text", width: 100, align:'center'},
		{ title:'최종<br/>전송', name: "list_result_text", type: "text", width: 50, align:'center' },
		{ title:'마지막 전송일시<br/>마켓 등록일시', name: "distribute_time", type: "text", width: 140, align:'center'},
	];


	marketProductAddOpt					= {};
	marketProductAddOpt.clickSelect		= true;
	marketProductAddOpt.checkboxForm	= true;
	marketProductAddOpt.confirmDeleting	= false;
	marketProductAddOpt.autoScrolling	= true;
	marketProductAddOpt.noDataContent	= "수정 대기 상품이 없습니다.";
	marketProductAddOpt.clickFunction	= function(args) {
		
		logGrid.jsGrid('setData', []);

		if (args.event.srcElement.type == 'checkbox')
			checkMarketProduct();
		else
			getMarketProductLog();
		

		function checkMarketProduct() {
		}

		function getMarketProductLog() {
			nowItem		= marketProductGrid.jsGrid('getDataByIdx', args.itemIndex);
			nowRow		= marketProductGrid.jsGrid('rowByItem', nowItem);
			marketProductGrid.jsGrid('oneSelectRow', nowRow);

			$.get('../market_connector_process/getMarketProductLog?fmMarketProduceSeq=' + args.item.fm_market_product_seq, function(response){
			if (response.length > 0)
				logGrid.jsGrid('setData', response);
			}, 'json');
		}
	};

	makeJsGrid(marketProductGrid, standByFields, marketProductAddOpt);

	logGrid		= $("#logGrid");	
	resultFields	= [
		{ title:'전송일시', name: "registered_time", type: "text", width: 120, align:'center'},
		{ title:'메세지', name: "log_text", type: "text", width: 200 }
	];
	

	logAddOpt					= {};
	logAddOpt.autoScrolling		= false;
	logAddOpt.noDataContent		= "수정 로그가 없습니다.";
	logAddOpt.clickFunction		= function(args) {}
	makeJsGrid(logGrid, resultFields, logAddOpt);
	
	layResize();
	$(window).resize(function() {layResize()});
	
	$('#saleStatus').multipleSelect({
		placeholder		: '판매상태',
		selectAll		: true,
		selectAllText	: '전체 선택',
		allSelected		: '전체 상태',
		minimumCountSelected : 100
	});

	
	$('#saleStatus').multipleSelect('setSelects', []);

	if (typeof searchObj.status == 'object')
		$('#saleStatus').multipleSelect('setSelects', searchObj.status);

	
	getMarketProductList();

});


function layResize() {
	var minHeight		= 530;
	var windowHeight	= $(window).height();
	var basHeight		= 310;
	var topHeight		= $('#distTop').height();
	var bottomHeigth	= $('#distBottom').height();
	var caclHeight		= windowHeight - basHeight - topHeight - bottomHeigth;
	var newHeight		= (caclHeight > minHeight) ? caclHeight : minHeight;
	var messigeHeight	= 100;

	$('#container').height(newHeight);
	$('#distBottom').height(bottomHeigth);

	layHeight			= $('#marketProductList').height();
	console.log(layHeight);
	marketProductGrid.jsGrid('option','height', layHeight - 53);
	logGrid.jsGrid('option','height', layHeight - 110);
}


function searchFormSubmit(page) {
	$("#nowPage").val(page);
	$("form[name='market_search_form']").submit();
}


function getMarketProductList(page) {
	if (parseInt(page,10) > 0)
		$('#nowPage').val(page);

	var params		= {};
	var params		= $("form[name='market_search_form']").serialize();

	$.get('../market_connector_process/getMarketProductList', params, function(response){
		if (response.marketProductList.length > 0)
			marketProductGrid.jsGrid('setData', response.marketProductList);
		else
			marketProductGrid.jsGrid('setData', []);

		$('#pagingNavigation').html(response.paging);

		marketProductGrid.find(".gridCheck").wrap("<label class='resp_checkbox'></label>");

	}, 'json');



}


function distStart(mode) {

	var nowStatus	= distributor.getStatus();
	if (nowStatus == 'ing') {
		alert('이미 진행중입니다.');
		return;
	}
	

	if (nowStatus != 'pause')
		distributor.setGrid(marketProductGrid, mode);

	distributor.start();
}

function distStop() {
	var nowStatus	= distributor.getStatus();
	distributor.stop();
}

function distPause() {
	var nowStatus	= distributor.getStatus();
	if (nowStatus == 'ing')
		distributor.pause();
}


function viewMessage(message) {
	message	= message.replace(/\n/g,"<br/>");
	$('#message').html(message);
}

function goAddinfo(getParam, groupId){
	window.open('', 'addInfoPopup', 'width=1020px,height=1000px,menubar=no,resizable=no,scrollbars=no');
	$form = $('#processFrom');
	
	$form.find('input[name=detailMarket]').val(getParam);
	$form.find('input[name=pageMode]').val('AddInfoDetail');
	$form.find('input[name=linkMode]').val('modify');
	$form.find('input[name=groupId]').val(groupId);
	$form.attr('action','/admin/market_connector/shoplinker_add_info_detail');
	$form.attr('target','addInfoPopup');			
	$form.submit();	
}


var distributor = (function() {
	var _recursionList	= [];
	var _doneList		= [];
	var _failList		= [];
	var _allCount		= 0;
	var _sleepSec		= 0;
	var _girdObj		= {};
	var _status			= 'standby';
	var _doMode			= '';
	var _doModeText		= '';
	

	function setGrid(girdObj, mode) {
		_recursionList	= [];
		_girdObj		= girdObj;
		_doMode			= mode;
		allKeys			= _girdObj.jsGrid('getDataKeys');
		_doCheckDelete	= false;
		_deleteList		= [];

		switch(_doMode) {
			case	'productSync' :
				_doModeText	= '판매상태 동기화';
				break;
			case	'productConfirm' :
				_doModeText	= '판매승인 요청';
				break;
			case	'productDelete' :
				_doModeText	= '판매상품 삭제';
				_doCheckDelete = true;
				break;
			default :
				alert(_doMode);
				alert('잘못된 호출입니다.');
				return;
		}

		if(_girdObj.jsGrid('option','checkboxForm') == true) {
			for(cnt = allKeys.length, i = 0; i < cnt; i++) {
				nowItem		= _girdObj.jsGrid('getDataByIdx',allKeys[i]);
				if(nowItem.selected){
					_recursionList.push(allKeys[i]);
				}

				if(_doCheckDelete && nowItem.selected && nowItem.delete_goods_seq!="D"){
					_deleteList.push(allKeys[i]);
				}
			}
		} else {
			_recursionList	= allKeys;
		}
		
		if (_recursionList.length < 1) {
			alert('선택된 상품이 없습니다.');
			return;
		}
		
		if (_deleteList.length > 0) {
			alert('선택삭제는 내 쇼핑몰 상품이 삭제된 상품만 가능합니다.');
			return;
		}

		_allCount		= _recursionList.length;
	}

	function start() {
		if (_status != 'ing') {
			// 로딩바 노츨 중지
			$("#ajaxLoadingLayer").attr('id', 'ajaxLoadingLayer_bak');
			_status	= 'ing';
			_do();
		}
	}

	function setPaush() {

		if(_recursionList.length > 0 && _status == 'ing')
			_status = 'pause';
		
	}


	function _do() {
		if(_recursionList.length < 1) {
			_reset();
			return;
		}
		if (_deleteList.length > 0) {
			_reset();
			return;
		}

		if (_status != 'ing')
			return;

		
		idx			= _recursionList.shift();
		nowItem		= _girdObj.jsGrid('getDataByIdx',idx);
		nowRow		= _girdObj.jsGrid('rowByItem', nowItem);
		_girdObj.jsGrid('selectDoRow', nowRow);
		viewMessage('"' + nowItem.market_product_name + '" 상품 "' + _doModeText + '" 처리중.....' );
		
		actionUrl	= '';
		params		= {};
		switch(_doMode) {
			case	'productSync' :
				actionUrl	= '../market_connector_process/marketProductStatusSync';
				params		= nowItem;		
				break;
			
			case	'productConfirm' :
				actionUrl	= '../market_connector_process/marketProductConfirm';
				params		= nowItem;		
				break;

			case	'productDelete' :
				actionUrl	= '../market_connector_process/marketProductDelete';
				params		= nowItem;		
				if(!confirm("삭제 처리 후에는 상품 관리 리스트에서 보여지지 않습니다. \n\n처리 하시겠습니까?")){
					_reset();
					return;
				}
				break;

		}
		
		$.post(actionUrl, params, function(response){
			if(typeof _girdObj.jsGrid != 'undefined')
				_girdObj.jsGrid('doneSelectRow', nowRow);
			
			viewMessage('');
			if(response != null && (response.result_type == 'productDelete' || response.result_message)){
				alert(response.result_message);
			}


			setTimeout(_do, _sleepSec);
			/*
			if (response.result_type == 'Y') {
				_doneList.push(nowItem);
			} else {
				nowItem.fail_yn			= 'Y';
				nowItem.fail_text		= '<span class="bold" style="color:red">실패</span>';
				nowItem.result_message	= response.result_message;
				_failList.push(nowItem);
			}
			resultGrid.jsGrid("insertItem", response).done(function() {
				resultGrid.jsGrid("moveToBottom");
				
				if (_status == 'ing')
					setTimeout(_do, _sleepSec);
			});
			*/
		}, 'json');
	}

	function _reset() {

		if (_girdObj.hasOwnProperty('selector') == true) {
			for(cnt = _doneList.length, i = 0; i < cnt; i++) {
				nowItem			= _doneList.shift();
				_girdObj.jsGrid("deleteItem", nowItem);
			}

			for(cnt = _failList.length, i = 0; i < cnt; i++) {
				nowItem			= _failList.shift();
				_girdObj.jsGrid("updateItem", nowItem);
			}

			_girdObj.jsGrid('allReset');
			getMarketProductList();
		}
		
		// 로딩바 노츨 중지 해제
		$("#ajaxLoadingLayer_bak").attr('id', 'ajaxLoadingLayer');

		_allCount		= 0;
		_status			= 'standby';

		_recursionList	= [];
		_deleteList		= [];
		_doneList		= [];
		_failList		= [];
		
		_girdObj		= {};
		_lastData		= {};
	}
	

	return {
		setSleep	: function (sec) {_sleepSec = sec * 1000;},
		getStatus	: function () {return _status;},
		setGrid		: setGrid,
		start		: start,
		reStart		: start,
		pause		: setPaush,
		stop		: _reset,
	}
}());