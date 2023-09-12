$(document).ready(function() {
    if(page_tab == 'banner'){
        Editor.onPanelLoadComplete(function(){
            $(document).resize();
        });
    }

    // 바로가기 selectbox
    $(".pageselect").click(function(){
        $(this).toggleClass('opened');
    });

    $("input[name='catalog_allow']").on("click",function(){
        if($(this).val() == 'period'){
            $("#popModifyLayer_access_limit tr.period").show();
        }else{
            $("#popModifyLayer_access_limit tr.period").hide();
        }
    });

    ajax_main_body_layer();
    help_tooltip();
});

	// 전체 선택 값 이벤트 추가
	function bindChkAll(){
		$('.chk_all').unbind('click');
		$('.chk_all').bind('click', function(){
			if($(this).is(':checked')){
				$('input[name="code[]"]').prop('checked', true).closest('tbody').find('tr').addClass('checked-tr-background');
			}else{
				$('input[name="code[]"]').prop('checked', false).closest('tbody').find('tr').removeClass('checked-tr-background');
			}
		});
	}

	// 리스트 동적 Call
	function ajax_main_body_layer(){
		$.ajax({
			type	: 'GET',
			url		: './page_layout_list',
			data	: {'cmd':page_type, 'tab':page_tab},
			dataType: 'html',
			success	: function(res){
				$("#ajax_main_body").html(res);
			}
		});
	}

	// 차수별 설정 버튼
	function setCtrlBtn(depth){

		// 설정 팝업 사이즈
		switch (page_tab){
			case 'access_limit'	  :	width = '1000'; height = '700'; break;
			case 'banner'		  :	width = '1000'; height = '700'; break;
			case 'recommend'	  :	width = '1200'; height = '700'; break;
			case 'navigation'	  :
			case 'all_navigation' :	width = '1200'; height = '700'; break;
			default :				width = '500'; height = '188'; break;
        }
		$.ajax({
			type	: 'POST',
			url		: './ajax_set_'+page_tab,
			data	: {'cmd':page_type,'page_type':page_type, 'depth':depth},
			dataType: 'html',
			success	: function(res){
				$("#setCtrlLayer").empty();
				$("#setCtrlLayer").html(res);

				openDialog(depth + "차 "+grp_extra_txt, "setCtrlLayer", {"width":width,"height":height});
			}
		});
	}

	// 예외 TOP 설정 버튼
	function extraTopCtrlBtn(){
		switch (page_tab){
			case 'page_goods'		:
				var pop_url = "/admin/design/display_edit?kind="+page_type+"&sub_kind=batch&popup=1";
				window.open(pop_url,'display_edit',"width=1200,height=700,scrollbars=1");
			break;
			case 'navigation'		:
			case 'all_navigation'	:
				copy_navigation(page_tab);
				// 네비게이션 소스
				//openDialog('소스', 'popSource_'+page_tab, {"width":500,"height": 200});
			break;
			case 'image'			:
				openDialog('베스트 아이콘 등록/수정', 'popSource_'+page_tab, {"width":500,"height": 350});
			break;
			default :
				alert('잘못된 접근입니다.');
			break;
		}
	}

	// 예외 설정 버튼
	function extraCtrlBtn(){
		// 설정 팝업 사이즈
		var page_tab_call = page_tab;
	
		switch (page_tab){
			case 'page_goods'	:
				if(operation_type == 'light'){
					width = '700'; height = '600'; break;
				}else{
					width = '900'; height = '550'; break;
				}
			case 'navigation'		:	width = '800'; height = '500'; break;
			case 'all_navigation'	:	width = '500'; height = '330'; break;
			case 'image'			:
				width = '1000'; height = '800';
				page_tab_call = 'brand_' + page_tab;
				break;
			default :					width = '500'; height = '350'; break;
		}
		
		$.ajax({
			type	: 'POST',
			url		: './ajax_set_extra_'+page_tab_call,
			data	: {'page_type':page_type,'page_tab':page_tab},
			dataType: 'html',
			success	: function(res){
				$("#setCtrlLayer").empty();
				$("#setCtrlLayer").html(res);

				openDialog(grp_extra_txt, "setCtrlLayer", {"width":width,"height":height});
			}
		});
	}

	// 예외 서브 설정 버튼
	function extraSubCtrlBtn(target_code){
		// 설정 팝업 사이즈
		switch (page_tab){
			case 'navigation'		:
			case 'all_navigation'	:
										width = '800'; height = '400'; break;
			case 'image'			:	width = '800'; height = '500'; break;
			default :					width = '500'; height = '188'; break;
		}
		$.ajax({
			type	: 'POST',
			url		: './ajax_get_extra_'+page_tab,
			data	: {'page_type':page_type,'page_tab':page_tab, 'target_code' : target_code},
			dataType: 'html',
			success	: function(res){
				$("#setCtrlLayer").empty();
				$("#setCtrlLayer").html(res);

				openDialog(grp_extra_txt, "setCtrlLayer", {"width":width,"height":height});
			}
		});
	}

	// 노출여부 설정 버튼
	function viewCtrlBtn(target_code){
		// 설정 팝업 사이즈
		switch (page_tab){
			case 'navigation'		:
			case 'all_navigation'	:	width = '520'; height = '180'; break;
			case 'image'			:	width = '520'; height = '150'; break;
			default :					width = '500'; height = '188'; break;
		}

		if(page_tab == 'image'){ // 브랜드 메인 추가 타입 예외처리
			var best_yn = $('.codenm_'+target_code).find('.best_yn').html();
			if(best_yn != 'Y')	height = '180';
			setViewCtrlProcess(target_code, best_yn);
		}else{
			viewCtrlBtn_func(target_code, page_tab);
		}
	}

	// 노출여부 설정 실행 함수
	function viewCtrlBtn_func(target_code, page_tab){
		$.ajax({
			type	: 'POST',
			url		: './ajax_get_extra_view_'+page_tab,
			data	: {'page_type':page_type,'page_tab':page_tab, 'target_code' : target_code},
			dataType: 'json',
			success	: function(res){
				if(res.state){
					openDialogConfirmtitle('노출 정보', res.msg, width, height, function(){setViewCtrlProcess(target_code, res.next);}, null, '');
				}else{
					openDialogAlert(res.msg, width, height, null, '');
				}

			}
		});
	}

	// 업데이트 프로세스 실행 함수
	function setViewCtrlProcess(target_code, next){
		if(page_tab == 'image'){
			var msg		= '';
			var best_yn	= next;
			if(best_yn == 'Y'){
				msg = '베스트 브랜드 설정을 해제 하시겠습니까?';
			}else{
				msg = '베스트 브랜드로 설정하시겠습니까?';
				msg += '<br/><span class="desc">설정된 베스트 브랜드는 브랜드 메인페이지, 브랜드 검색필터에서 베스트 아이콘이 노출되며<br/>좌측 삼선 네비게이션 내 베스트 브랜드 영역에 노출됩니다.</span>';
				height ="260";
			}
			openDialogConfirmtitle('베스트 브랜드', msg, width, height, function(){
				$.ajax({
					type	: 'POST',
					url		: '../page_manager_process/modify_best_brand',
					data	: {'page_type':page_type,'page_tab':page_tab, 'target_code': target_code, 'best_yn': best_yn},
					dataType: 'json',
					success	: function(res){
						if(res.state){
							alermSuccess();
							ajax_main_body_layer();
						}else{
							openDialogAlert(res.msg, '400', '150', null, '');
						}
					}
				});
			}, null);
		}else{
			$.ajax({
				type	: 'POST',
				url		: '../page_manager_process/modify_hide_'+page_tab,
				data	: {'page_type':page_type,'page_tab':page_tab, 'target_code': target_code, 'next': next},
				dataType: 'json',
				success	: function(res){
					if(res.state){
						alermSuccess();
						ajax_main_body_layer();
					}else{
						openDialogAlert(res.msg, '400', '150', null, '');
					}
				}
			});
		}
	}

	// 해당 설정 View 버튼
	function getCtrlBtn(code){

		// 설정 예외처리
		if(page_tab == 'image')		return false;

		// 선택 명 호출
		var code_name = $(".codenm_"+code).html();

		// view 팝업 사이즈
		switch (page_tab){
			case 'access_limit':
				width = '550'; height = '330'; break;
			case 'banner':
				width 	= $(document).width() * 0.6;
				height 	= document.body.clientHeight * 0.8;	//
				break;
			case 'recommend':		width = '1000'; height = '500'; break;
			case 'page_goods':		width = '1000'; height = '750'; break;
			case 'navigation':
			case 'all_navigation':	width = '640'; height = '270'; break;
			default :				width = '500'; height = '288'; break;
		}

		$.ajax({
			type	: 'POST',
			url		: './ajax_get_'+page_tab,
			data	: {'page_type':page_type, 'code':code},
			dataType: 'html',
			success	: function(res){
				$("#getCtrlLayer").empty();
				$("#getCtrlLayer").html(res);

				openDialog( code_name + " "+grp_extra_txt, "getCtrlLayer", {"width":width,"height":height});
			}
		});
	}

	// 해당 설정 서브 팝업 버튼
	function setSubCtrlPop(width, height, chk_cnt, extra){
        var extraPop = extra != null ? extra+'_' : '';
        var layId = "popModifyLayer_"+extraPop+page_tab;

		var option = {"width":width,"height":height};
		if(page_tab == 'banner'){
			option = {"width":width,"height":height,"draggable":false, position: ['center', 'top']};
		}

        if(extraPop == 'access_limit'){
            document.getElementById('targerSettingForm').reset();
        }

		if (chk_cnt > 0){
			$(".chk_layer").show();
			$(".chk_cnt").html(chk_cnt);
		}else{
			$(".chk_layer").hide();
        }
		openDialog(grp_extra_txt + " 수정", layId, option);
	}

	// 이미지 보기 레이어 팝업 이벤트
	function bindImagePopup(){
		$('.imgPopup').unbind('click');
		$('.imgPopup').bind('click', function(){
			$img = $('<img/>').attr('src', $(this).attr('value'));
			$('#imgPopupWrap').html($img);
			openDialog('이미지보기', 'imgPopupWrap', {width: '800', height: '600'});
		});
	}

	// 저장 완료 팝업
	function alermSuccess(){
		$('#suckPopup').fadeIn("slow", function(){
			setTimeout(function(){
				$('#suckPopup').fadeOut("fast");
			}, 1200);
		});

	}

	// 네비게이션 소스 복사
	function copy_navigation(page_tab){
		var source_code  = '';
		var uc_page_type = '';

		switch(page_tab){
			case 'navigation':

				uc_page_type = '{\=show'+page_type2+'LightNavigation()}';
				break;

			case 'all_navigation':

				uc_page_type = '<a class="hand '+page_type+'AllBtn" title="전체 네비게이션"></a>';
				break;

			default:
				break;
		}

		clipboard_copy(uc_page_type);
		alert('클립보드에 복사되었습니다.');
	}

	// 베스트 브랜드 아이콘 저장
	function set_bestbrand_img(){
		var tmp_file = $("input[name='tmp_image_best']").val();

		$.ajax({
			type	: 'POST',
			url		: '../page_manager_process/modify_best_icon',
			data	: {'tmp_file':tmp_file},
			dataType: 'json',
			success	: function(res){
				if(res.img_path){
					$("#preview_best_img").html('<input type="image" width="30px" src="' + res.img_path + '" />');
					closeDialog('popSource_'+page_tab);
					alermSuccess();
				}else{
					alert('업로드에 실패했습니다.');
				}
			}
		});
	}
