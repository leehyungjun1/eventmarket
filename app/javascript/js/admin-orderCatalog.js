
	$(document).ready(function() {

		if (typeof no_receipt_address === 'undefined') {
			no_receipt_address = 0;
		}
		
		$( "select[name='provider_seq_selector']" )
		.combobox()
		.change(function(){
			if( $(this).val() > 0 ){
				$("input[name='provider_seq']").val($(this).val());
				$("input[name='provider_name']").val($("option:selected",this).attr("provider_id"));
			}else{
				$("input[name='provider_seq']").val('');
				$("input[name='provider_name']").val('');
			}
		})
		.next(".ui-combobox").children("input")
		.bind('focus',function(){
			if($(this).val()==$( "select[name='provider_seq_selector'] option:first-child" ).text()){
				$(this).val('');
			}
		})
		.bind('mouseup',function(){
			if($(this).val()==''){
				$( "select[name='provider_seq_selector']").next(".ui-combobox").children("a.ui-combobox-toggle").click();
			}
		});

		$(".all-check").toggle(function(){
			$(this).parent().find('input[type=checkbox]').not('[name="chk_bundle_yn"]').attr('checked',true);
		},function(){
			$(this).parent().find('input[type=checkbox]').not('[name="chk_bundle_yn"]').attr('checked',false);
		});

		// 바코드스캔출고처리순서 안내버튼
		$(".barcode-btn").click(function(){
			if($(this).hasClass('opened')){
				$(this).removeClass('opened');
				$(".barcode-description").stop(true,true).slideUp();
			}else{
				$(this).addClass('opened');
				$(".barcode-description").stop(true,true).slideDown();
			}
		});

		$("button[name='order_admin_settle']").bind("click",function(){
			order_settle("orderAdminSettle","issueGoods", 'admin');
		});


		$("button[name='order_admin_person']").bind("click",function(){
			order_settle("orderAdminSettle","issueGoods", 'person');
		});

		$(window).css('overflow', 'scroll');
		$(window).scroll(function(){
			//if	($(window).scrollTop() == ($(document).height() - $(window).height())){//일부브라우져에서 페이징오류로 개선 @2016-10-07 ysm
			if	((($(document).height() - $(window).height()) - $(window).scrollTop()) < 100 ){
				get_catalog_ajax();
			}
		});


		$("form[name='search-form']").submit(function(){
			var vals = [];
			$("input[name='regist_date[]']").each(function(k, v) {  
					vals[k] = v.value; 		
			});
			
			if(vals[0].length > 0){

				if(vals[1].length <= 0){
					var dt = new Date();
					vals[1] = dt.getFullYear() + "-" + ("0" + (dt.getMonth() + 1)).slice(-2) + "-" + dt.getDate();
				}				
				
				if(vals[1].length <= 0 || vals[0] > vals[1]){
					alert("검색 시작 날짜보다 검색 끝 날짜가 더 빠릅니다.\n검색 날짜를 다시 확인해 주세요.");
					return false;
				}				
				
				var diff = new Date(Date.parse(vals[1]) - Date.parse(vals[0]))
				var days = diff/1000/60/60/24;

				if(days > 365){
					alert("1년 이내 주문만 검색 가능합니다.");
					return false;
				}
			}

			var submit = true;

			// 바코드 검색 체크
			var keyword = $("input[name='keyword']",this).val();
			if(keyword.length==21 && keyword.substring(0,1)=='A' && keyword.substring(keyword.length-1,keyword.length)=='A'){
				var order_seq = keyword.substring(1,20);
				$.ajax({
					'url' : 'order_seq_chk',
					'data' : {'order_seq':keyword},
					'async' : false,
					'success' : function(res){
						if(res=='1'){
							window.open('/admin/order/view?no='+order_seq+'&directExport=1');
							$("form[name='search-form'] input[name='keyword']").val('');
							submit = false;
						}
					}
				});
			}

			return submit;
		});

		$("button[name='print_setting']").click(function(){
			var url = "../setting/popup_print_setting";
			var win = window.open(url,'print_setting','toolbar=no, scrollbars=yes, resizable=no, width=1074, height=800');
		});
		
		if(npay_use != ""){
			// 네이버페이 주문 수동 수집
			$("#npay_order_receive").click(function(){
				loadingStart();
				if($(this).attr("disabled") != "disabled"){
					$(this).attr("disabled",true);
					actionFrame.location.href = '/naverpay/get_order_receive';
				}
			});
		}

		if(talkbuy_use != ""){
			// 톡체크아웃 주문 수동 수집
			// 여기까지만 개발하고 안했네?
			$("#talkbuy_use_receive").click(function(){
				loadingStart();
				if($(this).attr("disabled") != "disabled"){
					$(this).attr("disabled",true);
					actionFrame.location.href = '/talkbuy/get_order_receive';
				}
			});
		}

		$("button[name='openmarket_order_receive']").on('click',function(){openmarket_order_receive();});
		$("button[name='openmarket_order_receive_guide']").on('click',function(){openmarket_order_receive_guide();});

		get_catalog_ajax();


	});

	function removeCenterLayer( selector ) {
		//스크립트 에러 방지를 위한 공함수 19.03.21 kmj
	}

	function npay_order_receive_undisabled(){
		$("#npay_order_receive").attr("disabled",false);
	}

	//일괄 상품준비
	function batch_goods_ready(bobj)
	{
		var Bobj = $(bobj);
		var step = Bobj.attr('id');
		var order_seq = new Array();
		$("tr.step"+step).find("input[type='checkbox'][name='order_seq[]']:checked").each(function(idx){
			order_seq[idx] = 'seq[]='+$(this).val();
		});
		if(order_seq.length > 0){
			openDialogConfirm('선택된 주문건의 결제확인 주문수량을 → 상품준비로 변경하시겠습니까?',500,240,function(){
				var str = order_seq.join('&');
				$.ajax({
					type: "POST",
					url: "../order_process/batch_goods_ready",
					data: str,
					success: function(result){
						openDialogAlert(result,600,240,function(){
							document.location.reload();
						});
					}
				});
			},function(){
			});
		}else{
			alert("선택하세요.");
			return;
		}

	}
	
	// 개별 상품준비
	function goods_ready(bobj)
	{
		var Bobj = $(bobj);
		var order_seq = $(this).attr('id').replace("goods_ready_","");
		var url = "../order_process/goods_ready?seq="+order_seq;
		$.get(url, function(result) {
			openDialogAlert(result,400,140,function(){
				document.location.reload();
			});
		});

	}

	// 일괄출고완료
	function batch_complete_export(bobj)
	{
		var Bobj = $(bobj);
		var step = Bobj.attr('id');

		var url = "../export/batch_status?mode=goods&step=25&status="+step;
		var win = window.open(url,'export_popup','toolbar=no, scrollbars=yes, resizable=yes, width=1265, height=954');
	}

	// 일괄배송중
	function batch_going_delivery(bobj)
	{
		var Bobj = $(bobj);
		var step = Bobj.attr('id');

		var url = "../export/batch_status?mode=goods&step=25&status="+step;
		var win = window.open(url,'export_popup','toolbar=no, scrollbars=yes, resizable=yes, width=1265, height=954');
	}

	// 일괄배송완료처리
	function batch_complete_delivery(bobj)
	{
		var Bobj = $(bobj);
		var step = Bobj.attr('id');

		var url = "../export/batch_status?mode=goods&step=25&status="+step;
		var win = window.open(url,'export_popup','toolbar=no, scrollbars=yes, resizable=yes, width=1265, height=954');
	}


	// 체크박스 색상
	function color_order_seq(iobj)
	{
		var Iobj = $(iobj);
		if(Iobj.is(':checked')){
			Iobj.closest('tr').addClass('checked-tr-background');
		}else{
			Iobj.closest('tr').removeClass('checked-tr-background');
		}
	}

	// 개별 주문 인쇄
	function order_print(iobj)
	{
		var Iobj = $(iobj);
		var step = Iobj.attr('id');
		var order_seq = new Array();
		var text = "";

		$("tr.step"+step).find("input[type='checkbox'][name='order_seq[]']:checked").each(function(idx){
			//order_seq[idx] = 'seq[]='+$(this).val();
			text += $(this).val()+"|";
		});
		if(text){
			printOrderView(text);
		}else{
			alert("선택값이 없습니다.");
			return;
		}
	}

	// 개별주문되돌리기
	function order_reverse(bobj)
	{
		var Bobj = $(bobj);
		var order_seq = Bobj.attr('id').replace("order_reverse_","");
		actionFrame.location.href = '../order_process/order_reverse?seq='+order_seq;
	}

	// 판매마켓설정
	function not_linkage_order(bobj)
	{
		var Bobj = $(bobj);
		if(Bobj.is(":checked")){
			$("select[name='referer']").removeAttr('disabled');
		}else{
			$("select[name='referer']").attr('disabled',true);
		}
	}

	function openmarket_order_receive_guide()
	{
		// $("button[name='openmarket_service_guide']") "click"
		openDialog("다중 판매마켓 서비스 안내<span class='desc'></span>", "openmarket_order_receive_guide", {"width":"400","height":"200","show" : "fade","hide" : "fade"});
	}

	function openmarket_service_guide()
	{
		// $("button[name='openmarket_service_guide']") "click"
		openDialog("다중 판매마켓 서비스 안내<span class='desc'></span>", "openmarket_service_guide", {"width":"400","height":"200","show" : "fade","hide" : "fade"});
	}

	function order_type_help(){
		openDialog("주문유형 안내", "order_type_help", {"width":1000,"height":300});
	}

	// 모두열기
	function btn_open_all(bobj)
	{
		var Bobj = $(bobj);
		var step	=Bobj.attr("id");
		$("tr.step"+step).find("span.btn-direct-open").each(function(){
			orderViewOnOff('open', $(this));
		});
		var src	= Bobj.attr('src');
		Bobj.attr('src', src.replace('_open_', '_close_'));
		Bobj.attr("class", "btn_close_all");
		Bobj.attr("onclick", "btn_close_all(this)");

		allOpenStep[step]	= 'open';
	}

	// 모두닫기
	function btn_close_all(bobj)
	{
		var Bobj = $(bobj);
		var step	= Bobj.attr("id");
		$("tr.step"+step).find("span.btn-direct-open").each(function(){
			orderViewOnOff('close', $(this));
		});
		var src	= Bobj.attr('src');
		Bobj.attr('src', src.replace('_close_', '_open_'));
		Bobj.attr("class", "btn_open_all");
		Bobj.attr("onclick", "btn_open_all(this)");

		allOpenStep[step]	= 'close';
	}


	// 할인내역 열기 닫기
	function open_sale_contents(obj)
	{
		var btnobj	= $(obj);
		var trobj	= $(obj).closest('tr').next();
		var tdobj	= $(obj).closest('td');
		var divobj	= trobj.find("td").eq(tdobj.index()).find("div.detail-sale-box");
		if(divobj.hasClass('hide')){
			divobj.removeClass('hide');
			btnobj.attr('src','../skin/default/images/common/btn_close.gif');
		}else{
			divobj.addClass('hide');
			btnobj.attr('src','../skin/default/images/common/btn_open.gif');
		}
	}

	// 열기닫기
	function btn_direct_open(bobj)
	{
		var Bobj = $(bobj);
		var nClass		= Bobj.attr("class");
		if	(nClass.search(/opened/) == -1)	orderViewOnOff('open', Bobj);
		else								orderViewOnOff('close', Bobj);
	}

	// 택배업무 자동화
	function invoice_manual_button()
	{
		// $("span#invoice_manual_button").live("click",function(){
		var title = '택배 업무 자동화 서비스 사용방법';
		openDialog(title, "invoice_manual_dialog", {"width":"1000","height":"600"});
	}

	//개별 출고처리
	function goods_export(obj)
	{
		// button.goods_export
		var Bobj		= $(bobj);
		var order_seq	= $(this).attr('id').replace("goods_export_","");
		var url = "goods_export?seq="+order_seq;
		$.get(url, function(data) {
			$('#goods_export_dialog').html(data);
		});
		openDialog("출고처리<span class='desc'> - "+order_seq+"</span>", "goods_export_dialog", {"width":"95%","height":500});
	}


	//일괄 상태 되돌리기
	function batch_reverse(bobj)
	{
		var Bobj		= $(bobj);
		var step		= Bobj.attr('id');
		var order_seq	= new Array();
		var sale_stat	= true;
		var width		= 400;
		var height		= 140;
		var order_msg	= '';

		$("tr.step"+step).find("input[type='checkbox'][name='order_seq[]']:checked").each(function(idx){
			if($(this).attr('accumul_mark') == 'Y')	sale_stat = false;
			order_seq[idx] = 'seq[]='+$(this).val();
		});
		if(order_seq.length < 1){
			alert("선택값이 없습니다.");
			return;
		}

		// 주문상태 확인 주문접수 되돌리기만. :: 2015-09-07 lwh
		if(!sale_stat && step == '25'){
			order_msg	= '선택된 주문에는 매출이 확정된 주문이 존재합니다.<br/>매출이 확정된 주문(오늘 이전에 결제확인된 주문)건을 ‘주문접수’로 되돌릴 경우<br/>월별 매출 통계 화면에서 오늘 날짜의 ‘되돌리기’건으로 반영됩니다.';
			width	= 550;
			height	= 290;
		}

		if	(step == '25' && $(bobj).attr('autodepositKey') > 0){
			if	(order_msg)	order_msg	= order_msg + '<br/><br/>';
			order_msg	= order_msg + '선택된 주문에는 은행입금내역 자동입금확인 메뉴에서의 입금내역과 매칭된 주문이 존재합니다.<br/>주문접수로 되돌릴 경우 입금 내역과의 매칭 또한 해제됩니다';
			width		= 580;
			height		= parseInt(height) + 80;
		}

		if	(order_msg)	order_msg	= order_msg + '<br/><br/>';
		order_msg		= order_msg + '주문상태를 되돌리시겠습니까?';

		openDialogConfirm(order_msg,width,height,
		function(){
			var str = order_seq.join('&');
			$.ajax({
				type: "POST",
				url: "../order_process/batch_reverse",
				data: str+'&mode=json',
				dataType: 'json',
				success: function(result){
					if(result.result){
						openDialogAlert(result.msg,400,140,function(){
							document.location.reload();
						});
					}else{
						openDialogAlert(result.msg,400,140,'');
					}
				}
			});
		},function(){
			return false;
		});
	}

	//일괄출고처리 주문별
	function batch_order_export_popup(step)
	{
		var url = "../order/order_export_popup?mode=order&step["+step+"]="+step+"&status=45&start_search_date="+start_search_date+"&end_search_date="+end_search_date;
		alert(url);
		var win = window.open(url,'export_popup','toolbar=no, scrollbars=yes, resizable=yes, width=1265, height=954');
		win.focus();
	}


	//일괄출고처리 상품별
	function batch_goods_export_popup(step){
		var url = "../order/order_export_popup?";
		if	(pagemode == 'company_catalog')	url	= url + 'provider_seq=1&';
		var order_seq = new Array();
		if(step){
			$("tr.step"+step).find("input[type='checkbox'][name='order_seq[]']:checked").each(function(idx){
				order_seq[idx] = $(this).val();
			});

			if(order_seq.length == 0){
				alert("출고할 주문을 선택해주세요.");
				return;
			}

			if(order_seq.length > 0){
				url += "step["+step+"]=" + step + "&seq=" + order_seq.join('|');
			}
		}
		var win = window.open(url,'export_popup','toolbar=no, scrollbars=yes, resizable=yes, width=1265, height=954');
		win.focus();
	}

	//일괄출고처리 주문별
	function batch_order_export(step)
	{
		batch_order_export_popup(step);
	}


	//일괄출고처리 상품별
	function batch_goods_export(step)
	{
		batch_goods_export_popup(step);
	}
	
	//주문무효
	function batch_cancel_order(bobj)
	{
		// cancel_order
		var Bobj = $(bobj);
		var step = Bobj.attr('id');
		var order_seq = new Array();
		$("tr.step"+step).find("input[type='checkbox'][name='order_seq[]']:checked").each(function(idx){
			order_seq[idx] = 'seq[]='+$(this).val();
		});
		if(order_seq.length > 0){
			openDialogConfirm('선택된 주문을 주문무효처리 하시겠습니까1?',400,140,function(){
				var str = order_seq.join('&');
				$.ajax({
					type: "POST",
					url: "../order_process/batch_cancel_order?ajaxcall=Y",
					data: str,
					dataType : 'html',
					success: function(result){
						if(result == 'auth'){
							msg = '관리자 권한이 없습니다.';
							width = 300;
							height = 150;
							openDialogAlert(msg,width,height,function(){location.reload();});
						}else{
							if(result == 'partner'){
								openDialogAlert('간편결제 주문건은 직접 주문무효처리 할 수 없습니다.',500,160,function(){location.reload();});
							}else{
								location.reload();
							}
						}
					}
				});
			},function(){
			});
		}else{
			alert("선택값이 없습니다.");
			return;
		}
	}

	// 결제확인
	function batch_order_deposit(bobj)
	{
		var Bobj = $(bobj);
		var step = Bobj.attr('id');
		var order_seq = new Array();
		$("tr.step"+step).find("input[type='checkbox'][name='order_seq[]']:checked").each(function(idx){
			order_seq[idx] = 'seq[]=' + $(this).val();
		});

		if(order_seq.length > 0){
			openDialogConfirm('선택된 주문을 결제확인 하시겠습니까?',400,140,function(){
				var str = order_seq.join('&');
				$.ajax({
					type: "POST",
					url: "../order_process/batch_deposit?ajaxcall=Y",
					data: str,
					success: function(result){

						if(result != "") result	= result.trim();
						var msg				= "";
						var return_reload	= false;
						if(result != 'auth' ){
							msg					= "<div><b>결제가 확인되었습니다.</b></div><br/>";
							if(result == 'all'){
								msg				= msg + "<div style=\"text-align:left;\">▶ 실물상품 : 출고처리하여 상품을 발송하세요.<br/>▶ 티켓상품 : 티켓번호가 발송되었습니다.</div><br/>";
								return_reload	=  true;
							}else if(result == 'coupon'){
								msg				= msg + "<div style=\"text-align:left;\">▶ 티켓상품 : 티켓번호가 발송되었습니다.</div><br/>";
								return_reload	=  true;
							}else if(result == "npay"){
								msg				= "<div><b>네이버페이 주문 건은 직접 결제확인 할 수 없습니다.</b></div>";
								return_reload	=  true;
							}else if(result == "talkbuy"){
								msg				= "<div><b>톡체크아웃 주문 건은 직접 결제확인 할 수 없습니다.</b></div>";
								return_reload	=  true;
							}else if(result == "goods"){
								msg				= msg + "<div style=\"text-align:left;\">▶ 실물상품 : 출고처리하여 상품을 발송하세요.</div><br/>";
								return_reload	=  true;
							}else{
								msg				= "결제확인 오류!";
							}

							width	= 500;
							height	= 280;

						}else if(result == ""){
							msg		= "결제확인 오류1!";
							width	= 300;
							height	= 180;
						}else{
							msg		= "관리자 권한이 없습니다.";
							width	= 300;
							height	= 180;
						}

						if(return_reload){
							openDialogAlert(msg,width,height,function(){location.reload();});
						}else{
							openDialogAlert(msg,width,height,function(){});
						}

					}
				});
			},function(){
			});
		}else{
			openDialogAlert("선택값이 없습니다.",300,150,function(){});
			return;
		}

	}

	// 삭제처리
	function batch_delete_order(bobj)
	{
		var Bobj = $(bobj);
		var step = Bobj.attr('id');
		var order_seq = new Array();
		$("tr.step"+step).find("input[type='checkbox'][name='order_seq[]']:checked").each(function(idx){
			order_seq[idx] = 'seq[]='+$(this).val();
		});
		if(order_seq.length > 0){
			openDialogConfirm('선택된 주문을 삭제처리 하시겠습니까?',400,140,function(){
				var str = order_seq.join('&');
				$.ajax({
					type: "POST",
					url: "../order_process/batch_temps_order",
					data: str,
					success: function(result){
						location.reload();
					}
				});
			},function(){
			});
		}else{
			alert("선택값이 없습니다.");
			return;
		}
	}

	//개별 결제확인
	function order_deposit(bobj)
	{
		var Bobj = $(bobj);
		var order_seq = Bobj.attr('id').replace("order_deposit_","");
		actionFrame.location.href = '../order_process/deposit?seq='+order_seq;
	}

	//엑셀다운로드
	function excel_down(step)
	{
		var order_seq		= '';
		var cnt_order_seq	= 0;
		var sobj			= $("#select_down_"+step);
		var excel_type		= $("#excel_type_"+step).val();

		if(!excel_type){
			alert("타입을 선택해 주세요.");
			return;
		}

		if(!sobj.val()){
			alert("양식을 선택해 주세요.");
			return;
		}

		if( excel_type == 'search' ){
			order_seq = 'search';
			var params = $('form[name="search-form"]').serialize();
		} else {
			if(step > 0){
				$("tr.step"+step).find("input[type='checkbox'][name='order_seq[]']:checked").each(function(idx){
					order_seq += $(this).val() + "|";
					cnt_order_seq++;
				});
			} else {
				$("tr[class^='list-row step']").find("input[type='checkbox'][name='order_seq[]']:checked").each(function(idx){
					order_seq += $(this).val() + "|";
					cnt_order_seq++;
				});
			}
			
			if(!order_seq){
				alert("선택값이 없습니다.");
				return;
			}

			if(cnt_order_seq>1000) {
				alert("주문 1,000건 이상은 검색 다운로드를 이용해주세요.");
				return;
			}
		}

		var title = sobj.find("option:selected").html()+' 다운로드';		
		$("form#excel_down_form input[name='order_seq']").val(order_seq);
		$("form#excel_down_form input[name='seq']").val(sobj.val());
		$("form#excel_down_form input[name='excel_type']").val(excel_type);
		$("form#excel_down_form input[name='excel_step']").val(step);
		$("form#excel_down_form input[name='params']").val(params);
		$("form#excel_down_form input[name='callPage']").val($('input[name="callPage"]').val());
		//openDialog(title, "excel_download_dialog", {"width":640,"height":240}); //엑셀 팝업 제거 kmj
		
		var queryString = $('form').serializeArray();
		ajaxexceldown_spout('/cli/excel_down/create_order', queryString);
	}

	function ajaxexceldown_spout(url, queryString){
		var params = {};
		jQuery.each(queryString, function(i, field){
			params[field.name] = field.value;
		});

		$.ajax({      
			type: "POST",  
			url: url,      
			data: params, 
			success:function(args){ 
				closeDialog("excel_download_dialog");
				var exe = args.split('.').pop();
				if(exe == "csv" || exe == "zip" || exe == "xlsx") {
					window.location.href = '/admin/excel_spout/file_download?url=' + args; 
				} else if(args.indexOf('openDialogAlert') >= 0) {
					$('body').append(args);
				} else {
					alert(args);
				}
			}, error:function(e){  
				alert(e.responseText);  
			}  
		});
	}

	// 중요 체크
	function list_important(obj)
	{
		var bobj = $(obj);
		var param = "?no="+bobj.attr('id');
		if( bobj.hasClass('checked') ){
			bobj.removeClass('checked');
			param += "&val=0";
			$.get('important'+param,function(data) {});

		}else{
			bobj.addClass('checked');
			param += "&val=1";
			$.get('important'+param,function(data) {});
		}

	}


	//주문한 sns 계정 정보 확인
	function snsdetailview(m,snscd,mem_seq,no){

		var disp = $("div#snsdetailPopup"+no).css("display");
		$(".snsdetailPopup").hide();

		var obj	= $("div#snsdetailPopup"+no);
		//$("div.snsdetailPopup").hide();
		if(obj.html() == ''){
			$.get('../member/sns_detail?snscd='+snscd+'&member_seq='+mem_seq+'&no='+no, function(data) {
				obj.html(data);
			});
		}

		if(disp == "none"){ obj.show(); }
	}
	
	function get_catalog_ajax(){
		if	(loading_status == 'n'){
			loading_status	= 'y';
			var stepArrCnt			= stepArr.length;
			var addParam			= '';
			for (var s = 0; s < stepArrCnt; s++ ){
				if	(stepArr[s]){
					addParam	+= '&stepBox%5B'+s+'%5D='+stepArr[s];
				}
			}
			
			var last_step_cnt 			= $("#"+(npage-1)+"_last_step_cnt").val();
			var last_step_settleprice 	= $("#"+(npage-1)+"_last_step_settleprice").val();

			$("#ajaxLoadingLayer").ajaxStart(function() { loadingStop(this); });
			$.ajax({
				type: 'post',
				url: 'catalog_ajax',
				data: queryString +'&no_receipt_address='+no_receipt_address+'&page='+npage+'&pagemode='+pagemode+'&detailmode='+detailmode+'&shipping_provider_seq='+shipping_provider_seq+'&bfStep='+nstep+'&nnum='+nnum+'&bankChk='+bankChk+'&last_step_cnt='+last_step_cnt+'&last_step_settleprice='+last_step_settleprice+'&searchTime='+searchTime+addParam,
				dataType: 'html',
				success: function(result) {
					$(".order-ajax-list").append(result);
					$(".custom-select-box").customSelectBox();
					$(".custom-select-box-multi").customSelectBox({'multi':true});

					if (allOpenStep[nstep] == 'open'){
						$("tr.step"+nstep).find("span.btn-direct-open").each(function(){
							orderViewOnOff('open', $(this));
						});
					}else if	(allOpenStep[nstep] == 'close'){
						$("tr.step"+nstep).find("span.btn-direct-open").each(function(){
							orderViewOnOff('close', $(this));
						});
					}

					nstep	= $("#"+npage+"_step").val();
					nnum	= $("#"+npage+"_no").val();
					npage++;

					$("tr.pageoverflow").hide();
					if(nnum>0) loading_status	= 'n';
				}
			});
			if(nnum>0)$("tr.pageoverflow:last").show();
		}
	}


	// 전체선택
	function list_select(obj){
		var sobj = $(obj);

		var nm = sobj.attr("name");
		var value_str = sobj.val();
		var that = obj;

		$("select[name='"+nm+"']").not(this).each(function(idx){
			$(this).find("option[value='"+value_str+"']").attr("selected",true);
			this.selectedIndex = that.selectedIndex;
			$(this).customSelectBox("selectIndex",that.selectedIndex);
		});

		var step = nm.replace('select_', "");
		var iobj = $(".important-"+step);
		stepArr[step]	= value_str;

		$(".step"+step).removeClass('checked-tr-background');
		if(  value_str == 'select' )
			$(".step"+step).addClass('checked-tr-background');


		iobj.each(function(){
			if( value_str ){
				$(this).parent().parent().find("td").eq(0).find("input").attr("checked",false);
				if(  value_str == 'important' && $(this).hasClass('checked') ){
					$(this).parent().parent().find("td").eq(0).find("input").attr("checked",true);
					$(this).parent().parent().parent().find("."+$(this).attr('id')).addClass('checked-tr-background');
				}else if( value_str == 'not-important' && !$(this).hasClass('checked') ){
					$(this).parent().parent().find("td").eq(0).find("input").attr("checked",true);
					$(this).parent().parent().parent().find("."+$(this).attr('id')).addClass('checked-tr-background');
				}else if(  value_str == 'select' ){
					$(this).parent().parent().find("td").eq(0).find("input").attr("checked",true);
				}
			}
		});
	}

	function orderViewOnOff(openType, thisObj){
		var nextTr		= $(thisObj).parent().parent().next();
		var nClass		= $(thisObj).attr('class');
		if	(!pagemode)	pagemode	= 'order_catalog';
		if	(openType == 'open'){
			if	(nClass.search(/opened/) == -1){
				var order_seq	= $(thisObj).parent().find("a span").eq(0).html();
				$.get('view?no='+order_seq+"&pagemode="+pagemode+"&shipping_provider_seq="+shipping_provider_seq, function(data) {
					nextTr.find('div.order_info').html(data);
				});
				nextTr.removeClass('hide');
				$(thisObj).addClass("opened");
			}
		}else{
			if	(nClass.search(/opened/) != -1){
				nextTr.find('div.order_info').html('');
				nextTr.addClass('hide');
				$(thisObj).removeClass("opened");
			}
		}
	}

	function ajaxexceldown(url, order_seq, seq, step){
			var inputs ='<input type="hidden" name="order_seq" value="'+ order_seq +'" />';
			inputs +='<input type="hidden" name="seq" value="'+ seq +'" />';
			inputs +='<input type="hidden" name="step" value="'+ step +'" />';
			jQuery('<form action="'+ url +'" method="post" target="actionFrame" >'+inputs+'</form>')
			// jQuery('<form action="'+ url +'" method="post" >'+inputs+'</form>')
			.appendTo('body').submit().remove();
	}

	function addFormDialogSel(url, width, height, title, btn_yn) {
		newcreateElementContainer(title);
		newrefreshTable(url);

		$('#dlg').dialog({
			bgiframe: true,
			autoOpen: false,
			width: width,
			height: height,
			resizable: false,
			draggable: false,
			modal: true,
			overlay: {
				backgroundColor: '#000000',
				opacity: 0.8
			},
			buttons: {
				'닫기': function() {
					$(this).dialog('close');
				}
			}
		}).dialog('open');
		return false;
	}

	function set_date(start,end){
		$("input[name='regist_date[]']").eq(0).val(start);
		$("input[name='regist_date[]']").eq(1).val(end);
	}

	function closeExportPopup(){
		openDialogAlert("처리할 주문이 없습니다.", 400, 150, function(){closeDialog("goods_export_dialog");});
	}

	// export_upload
	function view_excel_upload_help(){
		// button abled / information hide
		$("#upload_submit_layer").removeClass("hide");
		$("#upload_information_layer").addClass("hide");
		openDialog("엑셀파일로 발송(출고)처리", "export_upload", {"width":"1150","height":"820","show" : "fade","hide" : "fade"});
	}

	function view_excel_code_help(){
		openDialog("택배사  안내", "excel_code_help", {"width":"700","height":"408","show" : "fade","hide" : "fade"});
	}

	function upload_excel()
	{
		$("#excelRegist").attr('action','../order_process/excel_upload');
		$("#excelRegist").submit();
		$("#excelRegist").attr('action','../order_process/excel_upload_check');

		// button disabled / information
		$("#upload_submit_layer").addClass("hide");
		$("#upload_information_layer").removeClass("hide");

	}

	function upload_excel_new()
	{
		$('input[name=isCheck]').val('N');
		$("#excelRegist").submit();

		// button disabled / information
		$("#upload_submit_layer").addClass("hide");
		$("#upload_information_layer").removeClass("hide");
	}

	function check_excel_stock_policy_step(){
		var obj = $("select#excel_export_step");
		var sobj = $("select#excel_export_stockable");

		if( obj.val() == 45) {
			obj.parent().find("span").addClass("hide");
			sobj.find("option[value='unlimit']").attr("selected",true);
		}
		if( obj.val() == 55) {
			obj.parent().find("span").removeClass("hide");
		}
	}

	// 개인결제 및 관리자 주문 팝업 오픈
	function order_settle(displayId,inputGoods,ordertype){
		if	(!ordertype)	ordertype	= 'admin';

		$('#optional_changes_dialog').remove();	// dialog clone버그로 인해 dummy 제거 추가

		var url				= '../order/order_settle?ordertype=' + ordertype;
		var dialog_title	= '관리자 수동 주문';
		if	(ordertype == 'person')	dialog_title	= '개인 결제 만들기';

		$.ajax({
			type: "get",
			url: url,
			data: "page=1&inputGoods="+inputGoods+"&displayId="+displayId+"&ordertype="+ordertype+"&ajaxcall=Y",
			success: function(result){
				if(result == 'auth'){
					openDialogAlert('관리자 권한이 없습니다.',300,150,function(){location.reload();});
				}else if(result == 'noshipping'){
					openDialogAlert('배송방법이 존재하지 않습니다.<br/>쇼핑몰 고객센터에 문의 해 주세요.',400,150);
				}else{
					$("div#"+displayId).html(result);
					openDialog(dialog_title, displayId, {"width":"600","height":"410","show" : "fade","hide" : "fade"});
				}
			}
		});
	}

	function openmarket_order_receive_submit(mall_code){
		loadingStart();
		$("form[name='orderReceiveForm'] input[name='mall_code']").val(mall_code);
		$("form[name='orderReceiveForm']").submit();
	}

	/*
	// 사은품 지급 조건 상세 2015-05-14 pjm
	function gift_use_log(order_seq,item_seq){
			$.ajax({
				type: "post",
				url: "../event/gift_use_log",
				data: "order_seq="+order_seq+"&item_seq="+item_seq,
				success: function(result){
					if	(result){
						$("#gift_use_lay").html(result);
						openDialog("사은품 이벤트 정보", "gift_use_lay", {"width":"450","height":"250"});
					}
				}
			});
	}
	*/

	function batch_status_popup(mode,str_export_code,ticket_export_cnt,result_obj)
	{
		var data_str  = "codes="+str_export_code+"&mode="+mode;
		if( result_obj.req_cnt ){
			data_str += "&req_cnt="+result_obj.req_cnt;
			data_str += "&err_cnt="+result_obj.err_cnt;
		}else{
			data_str += "&ticket="+ticket_export_cnt;
			data_str += "&cnt_export_request_45="+result_obj.cnt_export_request_45;
			data_str += "&cnt_export_result_goods_45="+result_obj.cnt_export_result_goods_45;
			data_str += "&cnt_export_error_45="+result_obj.cnt_export_error_45;
			data_str += "&cnt_export_request_55="+result_obj.cnt_export_request_55;
			data_str += "&cnt_export_result_coupon_55="+result_obj.cnt_export_result_coupon_55;
			data_str += "&cnt_export_result_goods_55="+result_obj.cnt_export_result_goods_55;
			data_str += "&cnt_export_error_55="+result_obj.cnt_export_error_55;
			data_str += "&export_result_error_msg="+result_obj.export_result_error_msg;
		}

		$.ajax({
			type: "post",
			url: "../export/batch_status_popup",
			data: data_str,
			success: function(result){
				if	(result){
					$("#batch_status_popup_layer").html(result);
					openDialog("일괄출고처리", "batch_status_popup_layer", {"width":"930","height":"300","close":function(){document.location.reload();}});
				}
			}
		});
	}

	// 오픈마켓 주문 받기
	function openmarket_order_receive()
	{
		if(parseInt(linkage_mallnames_cnt) > 1){
			openDialog("주문수집","openmarket_order_receive_dialog",{'width':300});
		}else{
			openmarket_order_receive_submit(linkage_mallnames);
		}
	}

	function scm_select_warehouse(sid){
		var jobj =$('#'+sid);
		$.get('../common/scm_select_warehouse?box='+sid,function(data) {
			jobj.html(data );
		});
	}

	function set_address(addr){
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
					$(".cellphone").html(addr.recipient_cellphone);
			else	$(".cellphone").html('휴대폰번호 없음');
			
			if(addr.recipient_phone)
					$(".phone").html(addr.recipient_phone);
			else	$(".phone").html('추가연락처 없음');

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
			$(".cellphone").html(addr.recipient_cellphone);
			$(".phone").html(addr.recipient_phone);
			$(".recipient_email").html(addr.recipient_email);
			$(".international_nation").html(addr.nation);
			$("#address_nation").val(addr.nation).trigger('change');
		} // end nation if

		if(addr.recipient_email)
				$(".recipient_email").html(addr.recipient_email);
		else	$(".recipient_email").html('이메일주소 없음');

		set_shipping('view');
	}
	
	// 관리자 수동 주문창에서 배송비 변경 버튼 클릭시 오류로 추가함 :: rsh :: 2019-05-02
	if(typeof radioCheckUI === 'undefined') {
		/* checkbox, radiobox UI */
		function radioCheckUI() {
			$('input[type=radio]:checked').parent('label').addClass('on');
			$('input[type=radio]').on('change', function() {
				if ( $(this).prop('checked') ) {
					$(this).parent('label').siblings('label').removeClass('on');
					$(this).parent('label').addClass('on');
				}
			});
		}
		/* //checkbox, radiobox UI */
	}

	//스크립트 에러 방지를 위한 공함수
	if(typeof hideCenterLayer !== "function"){
		function hideCenterLayer(){
		}
	}
