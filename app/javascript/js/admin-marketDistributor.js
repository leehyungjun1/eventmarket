var standByGrid		= {};
var resultGrid		= {};
var alreadyList		= [];

$('document').ready(function(){
	
	if (marketObj.hasOwnProperty('ClauseAgree') == true && marketObj.ClauseAgree == false) {
		notClauseAgree();
		return;
	}


	standByGrid		= $("#standByGrid");	
	standByFields	= [
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
				var checkOpt		= {};
				checkOpt.type		= "checkbox";
				checkOpt.class		= "gridCheck";
				checkOpt.dist_seq	= 'check_'+item.dist_seq;
				checkOpt.checkType	= 'distSelect';
				return $("<input>").attr(checkOpt).on("change", function () {
					if($(this).is(":checked")) {
						item.selected		= true;
						standByGrid.jsGrid("editItem", item);
						standByGrid.jsGrid("checkedRow", 'checked', item);
					}else{
						item.selected		= false;
						standByGrid.jsGrid("editItem", item);
						standByGrid.jsGrid("checkedRow", 'unchecked', item);
					}
					/*
					if ($('.gridCheck:checked').length > 0)
						$('.headerSelector').text('해제');
					else
						$('.headerSelector').text('선택');
					*/
				}).val(item.dist_seq);
		  },
		  align: "center",
		  width: 40
		},
		{ title:'번호', name: "no", type: "number", width: 40 },
		{ title:'마켓명', name: "market_name", type: "text", width: 70 },
		{ title:'판매자아이디', name: "seller_id", type: "text", width: 100 },
		{ title:'필수정보', name: "add_info_text", type: "text", width: 150 },
		{ title:'상품번호', name: "goods_seq_text", type: "text", width: 100 },
		{ title:'실패', name: "fail_text", type: "text", width: 50, align:'center' },
		{ title:'이미지', name: "image_src", type: "text", width: 60, align:'center' },
		{ title:'상품명', name: "goods_name", type: "text", width: 200 },
		{ title:'카테고리 타입', name: "category_type_text", type: "text", width: 100 },
	];


	standByAddOpt					= {};
	standByAddOpt.clickSelect		= true;
	standByAddOpt.checkboxForm		= true;
	standByAddOpt.confirmDeleting	= false;
	standByAddOpt.autoScrolling		= true;
	standByAddOpt.noDataContent		= "등록 대기 상품이 없습니다.";
	standByAddOpt.clickFunction		= function(args) {
		if (args.item.hasOwnProperty('result_message') === true)
			viewMessage(args.item.result_message);
		else
			viewMessage('');

		var checkbox		= $('input[dist_seq="check_' + args.item.dist_seq + '"]');

		if($(args.event.target).attr('checkType') == 'distSelect')
			var checkType	= $(args.event.target).is(":checked");
		else
			var checkType	= (checkbox.is(':checked') === true) ? false : true;
		
		var changeMode		= (checkType == true) ? '' : '';

		if (checkType == true) {
			args.item.selected	= true;
			changeMode			= 'checked';
		} else {
			args.item.selected	= false;
			changeMode			= 'unchecked';
		}

		standByGrid.jsGrid("editItem", args.item);
		checkbox.attr('checked', checkType);
		standByGrid.jsGrid("checkedRow", changeMode, args.item);
	}
	
	makeJsGrid(standByGrid, standByFields, standByAddOpt);

	resultGrid		= $("#resultGrid");	
	resultFields	= [
		{ title:'마켓명', name: "market_name", type: "text", width: 90 },
		{ title:'판매자아이디', name: "seller_id", type: "text", width: 100 },
		{ title:'결과', name: "result_text", type: "text", width: 50 },
		{ title:'마켓 상품번호', name: "market_product_link", type: "text", width: 100, align:'center' },
		{ title:'마켓 상품명', name: "market_goods_name", type: "text", width: 200 },
		{ title:'마켓 카테고리', name: "market_category_name", type: "text", width: 350 }
	];
	
	resultAddOpt					= {};
	resultAddOpt.autoScrolling		= false;
	resultAddOpt.noDataContent		= "등록 결과가 없습니다.";
	resultAddOpt.clickFunction		= function(args) {

		if (args.item.hasOwnProperty('result_message') === true)
			viewMessage(args.item.result_message);
		else
			viewMessage('');		

	}
	makeJsGrid(resultGrid, resultFields, resultAddOpt);
	
	layResize();
	$(window).resize(function() {layResize()});
	

	for(market in marketObj)
		$('#market').append('<option value="' + market + '">' + marketObj[market].name + '</option>');


	$('#market').change(function() {
		var market		= this.value;

		// 마켓 선택시 초기화
		$('#sellerId > option').remove();
		$('#sellerId').append('<option value="">선택</option>');

		$('#addInfo > option').remove();
		$('#addInfo').append('<option value="">판매 마켓 및 아이디를 선택해주세요</option>');
		$('.sellerSelectChk').attr('disabled', true);
		$('.sellerSelectChk').parent().addClass('disabled');
		
		if (market == '')
			return;
		
		var sellerList	= marketObj[market].sellerList;
		var sellerCnt	= sellerList.length;
			
			
		for (i = 0; i < sellerCnt; i++){
			if(market.substr(0,3) == 'API'){
				$('#sellerId').append('<option value="' + sellerList[i] + '">' + sellerList[i] + '</option>');
				
			}else{
				$('#sellerId').append('<option value="' + sellerList[i] + '">' + sellerList[i] + '</option>');
			}
		}
			

		$('.alreadyDistGoods').remove();
		$('#alreadyInfo').html('');
	});



	$('#distProductAdd').click(function(){
		$('#distributorSelected').html('');
		$('#distributorGoods').html('');
		$('#alreadyInfo').html('');
		$('#addGoodsWrap').css({'height':'140'});
		
		$('#market').val('');
		$('#market').trigger('change');
		closeDialog("#distributorLay");
		closeDialog("#openDialogLayer");
		openDialog('상품 추가', 'distributorLay', {'width':850,'height':800});
	});

	$('#sellerId').change(function() {
		
		$('#addInfo > option').remove();
		$('#addInfo').append('<option value="">판매 마켓 및 아이디를 선택해주세요</option>');
		$('.sellerSelectChk').attr('disabled', true);
		$('.sellerSelectChk').parent().addClass('disabled');

		if(this.value == '')
			return;

		var params		= {};
		params.market	= $('#market').val();
		params.sellerId	= this.value;
		
		if(market.substr(0,3) != 'API'){
			$.get('../market_connector_process/getAddInfoList', params, function(response) {
				var cnt		= response.length;
				$('#addInfo > option').remove();
	
				if (cnt < 1) {
					$('#addInfo').append('<option value="">등록된 필수정보가 없습니다.</option>');
					return;
				}	
				
				for (i = 0, cnt = response.length; i < cnt; i++)
					$('#addInfo').append('<option value="' + response[i].seq + '">' + response[i].add_info_title + '</option>');
				
				
				$('.sellerSelectChk').attr('disabled', false);
				$('.sellerSelectChk').parent().removeClass('disabled');
	
				checkAlreadyRegisted();
	
			}, 'json');
		}else{
			$.get('../market_connector_process/getShoplinkerGroupList', params, function(response) {
				var cnt		= response.length;
				$('#addInfo > option').remove();
	
				if (cnt < 1) {
					$('#addInfo').append('<option value="">등록된 그룹이 없습니다.</option>');
					return;
				}
	
	
				
				for (i = 0, cnt = response.length; i < cnt; i++)
					$('#addInfo').append('<option value="' + response[i].seq + '|' + response[i].add_info_title + '">' + response[i].add_info_title + '</option>');
				
				
				$('.sellerSelectChk').attr('disabled', false);
				$('.sellerSelectChk').parent().removeClass('disabled');
	
				checkAlreadyRegisted();
	
			}, 'json');
		}

	});

	
	$('#openSelectGoodsLay').click(function(){
		if ($("input[name='allList']").val())
			$('#distributorGoods').html('');
	
		var url				= '../goods/select';
		var params			= {};
		params.allList		= '1';
		params.page			= '1';
		params.inputGoods	= 'distributorGoods';
		params.displayId	= 'distributorSelected';
		

		$.get(url, params, function(response){
			$("#distributorSelected" ).html(response);
			openDialog("상품 검색", 'distributorSelected', {"width":"1000","height":"750"}, checkAlreadyRegisted);
		});

	});

	$('html').css('overflow', 'hidden');
	getDistList();
});


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


function notClauseAgree() {
	var content	= "오픈마켓 연동 서비스 이용 약관 동의 후 이용 가능합니다.<br/><br/>";
	content	+= '오픈마켓 > 연동설정에서 약관 동의 후 이용하시기 바랍니다.<br/><br/>';
	content	+= '<div class="center"><span class="btn large cyanblue"><a href="./market_setting">바로가기</a></span></div>';

	$('body').append('<div id="notClauseAgree">' + content + '</div>');
	openDialog("오픈마켓 연동 서비스 안내", "notClauseAgree", {"width":"500","noClose":true});
}


function checkAlreadyRegisted() {
	var allGoodsList	= [];
	var alreadText		= '<span class="alreadyDistGoods red bold">[배포상품] <br/></span>';

	$('.alreadyDistGoods').remove();
	$('#alreadyInfo').html('');

	$('.goods').css('height',155);
	$('input[name="distributorGoods[]"]').each(function(){
		newGoodsName	= $(this).siblings('.name').html();
		if(typeof newGoodsName != "undefined" && newGoodsName != ""  && newGoodsName != null){
			newGoodsName	= $.trim(newGoodsName.replace(/^\[배포상품\]/,''));
			$(this).siblings('.name').html(newGoodsName);
		}
	});
	
	if ($('input[name="distributor_goods[]"]').length > 0)
		$('input[name="distributor_goods[]"]').map(function(idx) { allGoodsList[idx]	= this.value; });
	else
		$('input[name="distributorGoods[]"]').map(function(idx) { allGoodsList[idx]	= this.value; });
	

	if (allGoodsList.length < 1) 
		return;

	var params			= {};
	params.goodsList	= allGoodsList;
	params.market		= $('#market').val();
	params.sellerId		= $('#sellerId').val();

	$.post('../market_connector_process/doCheckAlreadyDistributed', params, function(response){
		
		alreadyList		= response;
		alreadyCnt		= alreadyList.length;
		if (alreadyCnt > 0) {
			$('#alreadyInfo').html('이미 배포된 상품이' + alreadyList.length + '건 있습니다.');

			for (i = 0; i < alreadyCnt; i++) {
				goodsSeq	= alreadyList[i];
				$nowGoods	= $('input[name="distributorGoods[]"][value="' + goodsSeq + '"]').siblings('.name');
				
				goodsName	= alreadText + $nowGoods.html();
				$nowGoods.html(goodsName);
			}
		}

	},'json');


}


function distStart() {
	
	
	var nowStatus	= distributor.getStatus();
	if (nowStatus == 'ing') {
		alert('이미 배포중입니다.');
		return;
	}
	

	if (nowStatus != 'pause')
		distributor.setGrid(standByGrid);

	distributor.start();
}

function viewMessage(message) {
	
	if (typeof message != 'string')
		message	= '';

	message	= message.replace(/\n/g,"<br/>");
	$('#message').html(message);
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

function movePage(page) {
	$('#page').val(page);
	getDistList();
}

function getDistList() {
	var params		= {}
	params.page		= $('#page').val();
	params.limit	= $('#limit').val();
	$.get('../market_connector_process/getDistributeList', params, function(response){
		standByGrid.jsGrid('setData', response.distList);
		$('#totalCount').val(response.totalCount);
		$('#pagingNavigation').html(response.paging);
		checkFailRows(standByGrid);
		standByGrid.find(".gridCheck").wrap("<label class='resp_checkbox'></label>");
	}, 'json');
}

function layResize() {
	var minHeight		= 500;
	var windowHeight	= $(window).height();
	var basHeight		= 210;
	var topHeight		= $('#distTop').height();
	var bottomHeigth	= $('#distBottom').height();
	var caclHeight		= windowHeight - basHeight - topHeight - bottomHeigth;
	var newHeight		= (caclHeight > minHeight) ? caclHeight : minHeight;
	var messigeHeight	= 100;

	$('#container').height(newHeight - 80);
	$('#distBottom').height(bottomHeigth);

	layHeight			= $('#standByList').height();
	standByGrid.jsGrid('option','height', layHeight - 77);

	resultGrid.jsGrid('option','height', layHeight - 119- messigeHeight);
}


function checkFailRows(gridObj, item) {
	var gridData		= gridObj.jsGrid('getData');
	for (cnt = gridData.length, i = 0; i < cnt; i++) {
		item			= gridData[i];
		if(item.dist_fail_yn == 'Y')
			gridObj.jsGrid('addClass', item,'jsgrid-fail-row');
	}
}

function distDelete(){
	seq = $('.gridCheck:checkbox:checked').map(function(){
		return this.value;
	}).get().join(',');
	if( !seq ){
		alert('삭제할 상품을 선택해주세요.');
		return;
	}

	$.ajax({
		type: "post",
		url: "../market_connector_process/del_distributor",
		data: "seq="+seq,
		success: function(result){
			getDistList();
		}
	});
}



function addDistributeGoods() {
	
	if ($('#addInfo').is(':disabled') == true) {
		alert('마켓 및 판매자 아이디를 선택해 주세요.');
		return false;
	}


	var allGoodsList	= [];

	if ($('input[name="distributor_goods[]"]').length > 0)
		$('input[name="distributor_goods[]"]').map(function(idx) { allGoodsList[idx]	= this.value; });
	else
		$('input[name="distributorGoods[]"]').map(function(idx) { allGoodsList[idx]	= this.value; });
	
	var alreadyCnt	= alreadyList.length;	// 이미 배포된 상품
	var allGoodsCnt	= allGoodsList.length;	// 배포 선택된 상품

	if (allGoodsCnt < 1) {
		alert('선택된 상품이 없습니다.');
		return;
	}
	
	var alreadyRegisted	= $('input[name="registed"]:checked').val();
	var targetGoods		= [];
	if (alreadyRegisted == 'N') {
		if (alreadyCnt == allGoodsCnt) {
			alert('배포가능한 상품이 없습니다.');
			return;
		}
		
		for (i = 0; i < allGoodsCnt; i++) {
			goodsSeq	= allGoodsList[i];
			arrayIdx	= alreadyList.indexOf(goodsSeq);

			if (arrayIdx === -1)
				targetGoods.push(goodsSeq);
		}
		
		var subMessage	= '제외한 ';
	} else {
		targetGoods		= allGoodsList;
		var subMessage	= '포함한 ';
	}
	
	var sellerId		= $("#sellerId").val();
	var market			= $("#market").val();
	var addInfoSeq		= $("#addInfo").val();
	var categoryType	= $("input[name='categoryType']:checked").val();

	var marketText		= $("#market option:selected").text();
	var addInfoText		= $("#addInfo option:selected").text();
	
	var targetCount		= targetGoods.length;

	var message	= '총 <span class="bold">' + targetCount + '</span>건의 상품을<br/> 등록 대기 리스트에 추가하시겠습니까?';

	if (alreadyCnt > 0)
		message	= '이미 등록된 <span class="red bold">' + alreadyCnt + '</span>건을 ' + subMessage + ' ' + message;

	message		+= '<br/><br/>연동 마켓(아이디) : <span class="bold">' + marketText;
	message		+= '(' + sellerId + ')</span>';
	
	if(market != "shoplinker"){
		message		+= '<br/>필수정보 : <span class="bold">' + addInfoText + '</span><br/><br/>';
	}else{
		message		+= '<br/>그룹 정보 : <span class="bold">' + addInfoText + '</span><br/><br/>';
	}
	
	message		+= '※ 이미 등록된 상품은 제외됩니다.<br/>';

	closeDialog("#openDialogLayer");

	var btnOpt	= {'yesMsg':'예', 'noMsg':'아니오'};

	openDialogConfirm(message, 470, 270,function(){
		var params				= {};
		params.market			= market;
		params.sellerId			= sellerId;
		params.addInfoSeq		= addInfoSeq;
		params.goodsList		= targetGoods;
		params.categoryType		= categoryType;
		params.alreadyRegisted	= alreadyRegisted;
		
		$.post('../market_connector_process/doAddDistributor', params, function(response){

			alert(response.message);
			getDistList();
			closeDialog("#openDialogLayer");

		}, 'json')

	},function(){}, btnOpt);

}



var distributor = (function() {
	var _recursionList	= [];
	var _doneList		= [];
	var _failList		= [];
	var _allCount		= 0;
	var _sleepSec		= 0;
	var _girdObj		= {};
	var _status			= 'standby';
	

	function setGrid(girdObj) {
		_recursionList	= [];
		_girdObj		= girdObj;
		allKeys			= _girdObj.jsGrid('getDataKeys');

		if(_girdObj.jsGrid('option','checkboxForm') == true) {
			for(cnt = allKeys.length, i = 0; i < cnt; i++) {
				nowItem		= _girdObj.jsGrid('getDataByIdx',allKeys[i]);
				if(nowItem.selected)
					_recursionList.push(allKeys[i]);
			}
		} else {
			_recursionList	= allKeys;
		}
		
		if (_recursionList.length < 1) {
			alert('선택된 상품이 없습니다.');
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

		if (_status != 'ing')
			return;

		
		idx			= _recursionList.shift();
		nowItem		= _girdObj.jsGrid('getDataByIdx',idx);
		nowRow		= _girdObj.jsGrid('rowByItem', nowItem);
		_girdObj.jsGrid('selectDoRow', nowRow);
		
		viewMessage('"' + nowItem.goods_name + '" 상품 "' + nowItem.market_name + '" 전송중.....' );

		params		= nowItem;

		$.post('../market_connector_process/marketGoodsRegister', params, function(response){
			if(typeof _girdObj.jsGrid != 'undefined')
				_girdObj.jsGrid('doneSelectRow', nowRow);

			viewMessage('');

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
			checkFailRows(standByGrid);
			getDistList();
		}
		
		// 로딩바 노츨 중지 해제
		$("#ajaxLoadingLayer_bak").attr('id', 'ajaxLoadingLayer');

		_allCount		= 0;
		_status			= 'standby';

		_recursionList	= [];
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