/*
옵션 선택 기능을 위한 javascript
( make class model because of too many function for option )
*/
var gl_option_select_ver				= '0.2';

var currency							= 'KRW';
var currency_symbol						= '원';
var currency_symbol_position			= 'after';

// 옵션 selectbox class
var jscls_option_select	= function(){

	// 전역변수 선언
	this.select_option_wrap				= 'select_option_lay';
	this.selected_option_name			= 'option';
	this.selected_optionTitle_name		= 'optionTitle';
	this.selected_suboption_name		= 'suboption';
	this.selected_suboptionTitle_name	= 'suboptionTitle';
	this.selected_inputoption_name		= 'inputsValue';
	this.selected_inputoptionTitle_name	= 'inputsTitle';
	this.apply_option_button_name		= 'viewOptionsApply';
	this.options_selectbox_name			= 'viewOptions[]';
	this.suboptions_selectbox_name		= 'viewSuboption[]';
	this.inputs_inputbox_name			= 'viewInputs[]';
	this.input_option_ea_name			= 'optionEa';
	this.input_suboption_ea_name		= 'suboptionEa';
	this.option_special_lay				= 'viewOptionsspecialays';
	this.option_special_btn				= 'viewOptionsspecialbtn';
	this.suboption_special_btn			= 'viewSubOptionsspecialbtn';
	this.inputs_upload_button			= 'inputsUploadButton';
	this.add_suboption_btn				= 'btn_add_suboption';
	this.ea_plus_class					= 'eaPlus';
	this.ea_minus_class					= 'eaMinus';
	this.option_price_class				= 'optionPrice';
	this.suboption_price_class			= 'suboptionPrice';
	this.option_price_out_class			= 'out_option_price';
	this.suboption_price_out_class		= 'out_suboption_price';
	this.ea_input_class					= 'ea_change';
	this.total_goods_price_lay			= 'total_goods_price';
	this.remove_option_class			= 'removeOption';
	this.skin							= 'default';
	this.result_table_class				= 'goods_quantity_table';
	this.fm_upload_id					= 'fmupload';
	this.option_view_type				= 'divide';
	this.suboption_layout_group			= 'group';
	this.suboption_layout_position		= 'up';
	this.inputoption_layout_group		= 'group';
	this.inputoption_layout_position	= 'up';
	this.selectboxOnOpen				= '';
	this.selectboxOnClose				= '';
	this.use_option						= false;
	this.add_action_to_button			= false;
	this.option_change_type				= false;
	this.mobile_mode					= false;
	this.debug_mode						= false;
	this.goods_seq						= 0;
	this.goods_price					= 0;
	this.apply_option_seq				= 0;
	this.option_current_no				= 0;
	this.option_divide_title_count		= 0;
	this.member_seq						= 0;
	this.member_group					= 0;
	this.multi_discount_ea				= 0;
	this.multi_discount					= 0;
	this.cutting_sale_price				= 0;
	this.string_price_use				= '';
	this.multi_discount_policylist		= '';
	this.multi_discount_unit			= '';
	this.cutting_sale_action			= '';
	this.inputoption_data				= '';
	this.total_compare_currency_lay		= 'total_compare_currency';
	this.currency						= currency;
	this.currency_symbol				= currency_symbol;
	this.currency_symbol_position		= currency_symbol_position;
	this.string_button_use				= '';
	this.org_price						= 0;
	this.event_sale_unit				= 0;
	this.event_target_sale				= '';
	this.mobile_sale_unit				= 0;
	this.member_sale_unit				= 0;
	this.member_sale_type				= 0;
	this.min_apply_option_seq			= 0;

///////////////// 재정의 및 값 전달을 위한 외부 호출 함수 /////////////////

	// selector명 앞에 일괄로 구분자 붙이기 ( 기본 사용 시 재정의 불필요 )
	this.set_prefix_all_selector		= function(prefix){
		this.select_option_wrap				= prefix + 'select_option_lay';
		this.selected_option_name			= prefix + 'option';
		this.selected_optionTitle_name		= prefix + 'optionTitle';
		this.selected_suboption_name		= prefix + 'suboption';
		this.selected_suboptionTitle_name	= prefix + 'suboptionTitle';
		this.selected_inputoption_name		= prefix + 'inputsValue';
		this.selected_inputoptionTitle_name	= prefix + 'inputsTitle';
		this.apply_option_button_name		= prefix + 'viewOptionsApply';
		this.options_selectbox_name			= prefix + 'viewOptions[]';
		this.inputs_inputbox_name			= prefix + 'viewInputs[]';
		this.suboptions_selectbox_name		= prefix + 'viewSuboption[]';
		this.input_option_ea_name			= prefix + 'optionEa';
		this.input_suboption_ea_name		= prefix + 'suboptionEa';
		this.option_special_lay				= prefix + 'viewOptionsspecialays';
		this.option_special_btn				= prefix + 'viewOptionsspecialbtn';
		this.suboption_special_btn			= prefix + 'viewSubOptionsspecialbtn';
		this.inputs_upload_button			= prefix + 'inputsUploadButton';
		this.add_suboption_btn				= prefix + 'btn_add_suboption';
		this.ea_plus_class					= prefix + 'eaPlus';
		this.ea_minus_class					= prefix + 'eaMinus';
		this.option_price_class				= prefix + 'optionPrice';
		this.suboption_price_class			= prefix + 'suboptionPrice';
		this.option_price_out_class			= prefix + 'out_option_price';
		this.suboption_price_out_class		= prefix + 'out_suboption_price';
		this.ea_input_class					= prefix + 'ea_change';
		this.total_goods_price_lay			= prefix + 'total_goods_price';
		this.remove_option_class			= prefix + 'removeOption';
		this.result_table_class				= prefix + 'goods_quantity_table';
		this.fm_upload_id					= prefix + 'fmupload';

		$('#' + this.total_goods_price_lay).closest('form').append('<input type="hidden" name="select_option_prefix" value="' + prefix + '" />');
	};

	// selector명 뒤에 일괄로 구분자 붙이기 ( 기본 사용 시 재정의 불필요 )
	this.set_suffix_all_selector		= function(suffix){
		this.select_option_wrap				= 'select_option_lay' + suffix;
		this.selected_option_name			= 'option' + suffix;
		this.selected_optionTitle_name		= 'optionTitle' + suffix;
		this.selected_suboption_name		= 'suboption' + suffix;
		this.selected_suboptionTitle_name	= 'suboptionTitle' + suffix;
		this.selected_inputoption_name		= 'inputsValue' + suffix;
		this.selected_inputoptionTitle_name	= 'inputsTitle' + suffix;
		this.apply_option_button_name		= 'viewOptionsApply' + suffix;
		this.options_selectbox_name			= 'viewOptions' + suffix + '[]';
		this.inputs_inputbox_name			= 'viewInputs' + suffix + '[]';
		this.suboptions_selectbox_name		= 'viewSuboption' + suffix + '[]';
		this.input_option_ea_name			= 'optionEa' + suffix;
		this.input_suboption_ea_name		= 'suboptionEa' + suffix;
		this.option_special_lay				= 'viewOptionsspecialays' + suffix;
		this.option_special_btn				= 'viewOptionsspecialbtn' + suffix;
		this.suboption_special_btn			= 'viewSubOptionsspecialbtn' + suffix;
		this.inputs_upload_button			= 'inputsUploadButton' + suffix;
		this.add_suboption_btn				= 'btn_add_suboption' + suffix;
		this.ea_plus_class					= 'eaPlus' + suffix;
		this.ea_minus_class					= 'eaMinus' + suffix;
		this.option_price_class				= 'optionPrice' + suffix;
		this.suboption_price_class			= 'suboptionPrice' + suffix;
		this.option_price_out_class			= 'out_option_price' + suffix;
		this.suboption_price_out_class		= 'out_suboption_price' + suffix;
		this.ea_input_class					= 'ea_change' + suffix;
		this.total_goods_price_lay			= 'total_goods_price' + suffix;
		this.remove_option_class			= 'removeOption' + suffix;
		this.result_table_class				= 'goods_quantity_table' + suffix;
		this.fm_upload_id					= 'fmupload' + suffix;

		$('#' + this.total_goods_price_lay).closest('form').append('<input type="hidden" name="select_option_suffix" value="' + suffix + '" />');
	};

	// bind 등 옵션에 필요한 표시값들 직접 재정의 ( 기본 사용 시 재정의 불필요 )
	this.set_select_option_lay				= function(mark_name){if	(mark_name)	this.select_option_wrap				= mark_name;};
	this.set_selected_option_name			= function(mark_name){if	(mark_name)	this.selected_option_name			= mark_name;};
	this.set_selected_optionTitle_name		= function(mark_name){if	(mark_name)	this.selected_optionTitle_name		= mark_name;};
	this.set_selected_suboption_name		= function(mark_name){if	(mark_name)	this.selected_suboption_name		= mark_name;};
	this.set_selected_suboptionTitle_name	= function(mark_name){if	(mark_name)	this.selected_suboptionTitle_name	= mark_name;};
	this.set_selected_inputoption_name		= function(mark_name){if	(mark_name)	this.selected_inputoption_name		= mark_name;};
	this.set_selected_inputoptionTitle_name	= function(mark_name){if	(mark_name)	this.selected_inputoptionTitle_name	= mark_name;};
	this.set_apply_option_button_name		= function(mark_name){if	(mark_name)	this.apply_option_button_name		= mark_name;};
	this.set_option_select_name				= function(mark_name){if	(mark_name)	this.options_selectbox_name			= mark_name;};
	this.set_inputs_inputbox_name			= function(mark_name){if	(mark_name)	this.inputs_inputbox_name			= mark_name;};
	this.set_suboptions_selectbox_name		= function(mark_name){if	(mark_name)	this.suboptions_selectbox_name		= mark_name;};
	this.set_input_option_ea_name			= function(mark_name){if	(mark_name)	this.input_option_ea_name			= mark_name;};
	this.set_input_suboption_ea_name		= function(mark_name){if	(mark_name)	this.input_suboption_ea_name		= mark_name;};
	this.set_option_special_lay				= function(mark_name){if	(mark_name)	this.option_special_lay				= mark_name;};
	this.set_option_special_btn				= function(mark_name){if	(mark_name)	this.option_special_btn				= mark_name;};
	this.set_suboption_special_btn			= function(mark_name){if	(mark_name)	this.suboption_special_btn			= mark_name;};
	this.set_inputs_upload_button			= function(mark_name){if	(mark_name)	this.inputs_upload_button			= mark_name;};
	this.set_add_suboption_btn				= function(mark_name){if	(mark_name)	this.add_suboption_btn				= mark_name;};
	this.set_ea_plus_class					= function(mark_name){if	(mark_name)	this.ea_plus_class					= mark_name;};
	this.set_ea_minus_class					= function(mark_name){if	(mark_name)	this.ea_minus_class					= mark_name;};
	this.set_option_price_class				= function(mark_name){if	(mark_name)	this.option_price_class				= mark_name;};
	this.set_suboption_price_class			= function(mark_name){if	(mark_name)	this.suboption_price_class			= mark_name;};
	this.set_option_price_out_class			= function(mark_name){if	(mark_name)	this.option_price_out_class			= mark_name;};
	this.set_suboption_price_out_class		= function(mark_name){if	(mark_name)	this.suboption_price_out_class		= mark_name;};
	this.set_ea_input_class					= function(mark_name){if	(mark_name)	this.ea_input_class					= mark_name;};
	this.set_total_goods_price_lay			= function(mark_name){if	(mark_name)	this.total_goods_price_lay			= mark_name;};
	this.set_remove_option_class			= function(mark_name){if	(mark_name)	this.remove_option_class			= mark_name;};
	this.set_result_table_class				= function(mark_name){if	(mark_name)	this.result_table_class				= mark_name;};
	this.set_fm_upload_id					= function(mark_name){if	(mark_name)	this.fm_upload_id					= mark_name;};

	// 전역변수 값 조정 ( arguments를 이용하여 순서대로 추가 )
	this.set_init		= function(n){
		var that		= this;

		if	(arguments[0])	this.option_divide_title_count		= arguments[0];
		if	(arguments[1])	this.goods_seq						= arguments[1];
		if	(arguments[2])	this.goods_price					= arguments[2];
		if	(arguments[3])	this.string_price_use				= arguments[3];
		if	(arguments[4])	this.skin							= arguments[4];
		if	(arguments[5])	this.mobile_mode					= arguments[5];
		if	(arguments[6])	this.min_purchase_ea				= arguments[6];
		if	(arguments[7])	this.max_purchase_ea				= arguments[7];
		if	(arguments[8])	this.member_seq						= arguments[8];
		if	(arguments[9])	this.member_group					= arguments[9];
		if	(arguments[10])	this.option_view_type				= arguments[10];
		if	(arguments[11])	this.suboption_layout_group			= arguments[11];
		if	(arguments[12])	this.suboption_layout_position		= arguments[12];
		if	(arguments[13])	this.inputoption_layout_group		= arguments[13];
		if	(arguments[14])	this.inputoption_layout_position	= arguments[14];
		if	(arguments[15])	this.currency						= arguments[15];
		if	(arguments[16])	this.currency_symbol				= arguments[16];
		if	(arguments[17])	this.currency_symbol_position		= arguments[17];
		if	(arguments[18])	this.string_button_use				= arguments[18];

		// 필수옵션 사용여부
		if	($("select[name='" + this.options_selectbox_name + "']").length > 0){
			this.use_option	= true;
		}

		// 버튼을 통한 추가 방식 설정
		if	($("select[name='" + this.options_selectbox_name + "']").length > 0 &&
				(		( $("select[name='" + this.suboptions_selectbox_name + "']").length > 0 && this.suboption_layout_group != 'first' )
					||	( $("input[name='" + this.inputs_inputbox_name + "'],textarea[name='" + this.inputs_inputbox_name + "']").length > 0 && this.inputoption_layout_position != 'down' )
			)	) {
			this.add_action_to_button	= true;
		}
	};

	// selectbox plugin 옵션 추가
	this.set_selectbox_option	= function(onOpen, onClose){
		if	(onOpen)	this.selectboxOnOpen	= onOpen;
		if	(onClose)	this.selectboxOnClose	= onClose;
	};

	// 테스트 모드일 경우
	this.set_debug_mode			= function(){
		this.debug_mode			= true;
	};

	// 기존 옵션이 있는 경우 current값 변경
	this.set_apply_option_seq	= function(seq){
		this.apply_option_seq	= seq;
	};

	// 기존 옵션이 있는 경우 옵션 변경창으로 인식
	this.set_option_change_type	= function(status){
		this.option_change_type	= status;
		this.calculate_goods_price();
	};

	// 모바일할인 정의 sale_lib 값 그대로 사용 2018-07-12 pjw
	this.set_mobile_sale = function (mobile_sale_unit){
		if(mobile_sale_unit)	this.mobile_sale_unit = mobile_sale_unit;
	};

	// 이벤트할인 정의 sale_lib 값 그대로 사용 2018-07-12 pjw
	this.set_event_sale = function (event_sale_unit, event_target_sale){
		if(event_sale_unit && event_target_sale == '') {
			this.event_sale_unit	= event_sale_unit;
		} else {
			this.event_sale_unit	= event_sale_unit;
			this.event_target_sale	= event_target_sale;
		}
	};

	//  회원구매할인 정의 sale_lib 값 그대로 사용 2018-07-12 pjw
	this.set_member_sale		= function(member_sale_unit, member_sale_type){
		if(member_sale_unit && member_sale_type == '') {
			this.member_sale_unit	= member_sale_unit;
		} else {
			this.member_sale_unit	= member_sale_unit;
			this.member_sale_type	= member_sale_type;
		}
	};

	// 복수구매할인 설정값 정의
	this.set_multi_sale		= function(policyList, ea, val, unit){
		if	(policyList)	this.multi_discount_policylist	= policyList;
		if	(ea)			this.multi_discount_ea			= ea;
		if	(val)			this.multi_discount				= val;
		if	(unit)			this.multi_discount_unit		= unit;
	};

	// 할인 절사 설정
	this.set_cutting_sale_price	= function(price, action){
		if	(price)		this.cutting_sale_price		= price;
		if	(action)	this.cutting_sale_action	= action;
	};

	// 입력옵션 데이터 ( Json )
	this.set_inputoption_data	= function(data){
		this.inputoption_data	= data;
	};

	// set bind
	this.set_bind_option	= function(){
		var that		= this;

		// 필수옵션 변경
		$("select[name='" + this.options_selectbox_name + "']").unbind('change');
		$("select[name='" + this.options_selectbox_name + "']").bind('change', function(){
			that.set_infomation(this);
			that.option_current_no	= parseInt($(this).attr('id')) + 1;
			if	($("select[name='" + that.options_selectbox_name + "']").length == that.option_current_no){
				if	(!that.add_action_to_button)	that.viewOptionsApply(this);
			}else{
				that.set_next_option();
			}
		});

		// 옵션 선택 버튼
		$('.' + this.apply_option_button_name).unbind('click');
		$('.' + this.apply_option_button_name).bind('click', function(){
			that.viewOptionsApply(this);
		});

		// 추가옵션 변경
		$("select[name='" + this.suboptions_selectbox_name + "']").unbind('change');
		$("select[name='" + this.suboptions_selectbox_name + "']").bind('change', function(){
			if	(!that.add_action_to_button || that.suboption_layout_group == 'first'){
				submsg	= that.apply_suboptions();
				if	(that.suboption_layout_group == 'first'){
					var first_opt_group	= $('table.'+that.result_table_class).find('tr.option_tr').eq(0).attr('opt_group');
					if			($('table.'+that.result_table_class).find("tr[opt_group='" + first_opt_group + "'].suboption_tr").attr('class')){
						$('table.'+that.result_table_class).find("tr[opt_group='" + first_opt_group + "'].suboption_tr").last().after(submsg);
					}else if	($('table.'+that.result_table_class).find("tr[opt_group='" + first_opt_group + "'].inputoption_tr").attr('class')){
						$('table.'+that.result_table_class).find("tr[opt_group='" + first_opt_group + "'].inputoption_tr").last().after(submsg);
					}else if	($('table.'+that.result_table_class).find('tr.option_tr').eq(1).attr('class')){
						$('table.'+that.result_table_class).find('tr.option_tr').eq(1).before(submsg);
					}else{
						$('table.'+that.result_table_class).append(submsg);
					}
				}else{
					$('table.'+that.result_table_class).append(submsg);
				}

				$('div.'+that.result_table_class+'_container').show();
				that.calculate_goods_price();

				// 선택된 옵션들 초기화
				that.reset_suboptions();
				that.set_bind_option();
			}
		});

		// 필수옵션의 특수옵션 버튼
		$('.' + this.option_special_btn).unbind('click');
		$('.' + this.option_special_btn).bind('click', function(){
			that.click_color_btn(this);
		});
		$('.' + this.option_special_btn).unbind('mouseenter');
		$('.' + this.option_special_btn).bind('mouseenter', function(){
			$("#goods_thumbs .pagination>li img[color='"+($(this).parent().attr('class'))+"']").eq(0).trigger("click");
		});

		// 추가구성옵션의 특수옵션 버튼
		$('.' + this.suboption_special_btn).unbind('click');
		$('.' + this.suboption_special_btn).bind('click', function(){
			that.subclick_color_btn(this);
		});

		// 추가구성옵션 추가
		$('.' + this.add_suboption_btn).unbind('click');
		$('.' + this.add_suboption_btn).bind('click', function(){
			that.add_suboption(this);
		});

		// 수량 증가 버튼
		$('.' + this.ea_plus_class).unbind('click');
		$('.' + this.ea_plus_class).bind('click', function(){
			that.eaPlus(this);
		});

		// 수량 감소 버튼
		$('.' + this.ea_minus_class).unbind('click');
		$('.' + this.ea_minus_class).bind('click', function(){
			that.eaMinus(this);
		});

		// 수량 직접 변경
		$('.' + this.ea_input_class).unbind('keyup');
		$('.' + this.ea_input_class).bind('change keyup input', function(){
			that.calculate_goods_price();
		});

		// 선택된 옵션 삭제 버튼
		$('.' + this.remove_option_class).unbind('click');
		$('.' + this.remove_option_class).bind('click', function(){
			that.removeSelectedOptions(this);

			//mobile ver3 옵션 포커스이동
			if( typeof gl_mobile_mode != 'undefined' && gl_mobile_mode == 3){
				$("div.goods_option").scrollTop($('div#select_option_lay').prop('scrollHeight'));
			}
		});

		// 입력옵션 입력 글자 제한이 있는 경우
		$('.inputlimit').each(function(){
			$(this).bind('keyup', function(){
				if	($(this).attr('inputlimit') > 0){
					var opt_group	= $(this).attr('opt_group');
					var opt_seq		= $(this).attr('opt_seq');
					var inputlimit	= $(this).attr('inputlimit');
					$('.inputByte_' + opt_group + '_' + opt_seq).html($(this).val().length);

					if	($(this).val().length > inputlimit){
						$(this).val($(this).val().substring(0, inputlimit));
						//자 이내로 입력해 주세요.
						openDialogAlert(inputlimit+getAlert('gv033'), 400, 140,'');
					}
				}
			});
			$(this).focusout(function() {
				var emojiRegex = /([\u2700-\u27BF]|[\uE000-\uF8FF]|\uD83C[\uDC00-\uDFFF]|\uD83D[\uDC00-\uDFFF]|[\u2011-\u26FF]|\uD83E[\uDD10-\uDDFF])/g;
				var insertOptionValue = $(this).val();
				if(emojiRegex.test(insertOptionValue)){
					$(this).val(insertOptionValue.replace(emojiRegex, ''));
					openDialogAlert(getAlert('gv115'), 400, 140,'');
				}
			});
		});

		// plug-in 적용 ( 위에서 적용되었을 수도 있음 )
		try{
			$('#' + this.select_option_wrap).find('.' + this.inputs_upload_button).each(function(){
				if	($(this).attr('uploadType') == 'fmupload'){
					var id	= $(this).attr('id');
					$('#' + id).fmupload({
						onComplete : function(id, filename, filepath){
							if	(filename){
								$('#'+id).closest('table').find('img.prevImg').attr('src', filepath+filename).show();
								$('#'+id).closest('table').find('.prevTxt').html(filename);
							}else{
								$('#'+id).closest('table').find('img.prevImg').attr('src','about:blank;').hide();
								$('#'+id).closest('table').find('.prevTxt').html('');
							}
							filepath	= filepath.replace(/^\//, '');	// 첫 문자열이 "/"인 경우 제거
							$('#'+id).closest('table').find('input.fmuploadInputs').val(filepath+filename);
						}
					});
				}else{
					// upload버튼 무한증식 문제로 예외처리함
					if	($(this).attr('setUploadify') != 'enable'){
						$(this).attr('setUploadify', 'enable');
						that.setUserUploadifyButton($(this).attr('id'));
					}
				}
			});
			that.setSelectBoxPlugin($("select[name='" + this.options_selectbox_name + "']"), '');
			that.setSelectBoxPlugin($("select[name='" + this.suboptions_selectbox_name + "']"), '');
			$("select[name='" + this.options_selectbox_name + "']").each(function(){
				that.chg_selectbox_selected($(this));
				if	(that.string_price_use || that.string_button_use){
					that.setSelectBoxPlugin($(this), 'disable');
				}
			});
		}catch(e){}
	};


///////////////// 필수옵션 관련 처리 함수 /////////////////


	// 필수옵션 분리형일때 다음 옵션 추출
	this.set_next_option	= function(){
		var that				= this;
		var gdata				= 'no=' + this.goods_seq + '&max=' + this.option_divide_title_count + '&member_seq=' + this.member_seq;
		$("select[name='" + this.options_selectbox_name + "']").each(function(i){
			if	(i < that.option_current_no){
				gdata += '&options[]=' + encodeURIComponent($(this).val());
			}
		});

		$.ajax({
			type: 'get',
			url: '/goods/option',
			data: gdata,
			success: function(result){
				that.write_option(result);
			}
		});
	};

	// 필수옵션의 option tag 생성
	this.write_option	= function(result){
		var that				= this;
		var data				= eval(result);
		var add_price			= 0;
		var Optionsspecialhtml	= '';
		var n					= this.option_current_no;
		$("select[name='" + this.options_selectbox_name + "']").eq(n).find('option').each(function(i){
			if	( i != 0 )	$(this).remove();
		});

		var obj		= '';
		for	(var i = 0; i < data.length; i++){
			obj			= data[i];

			add_price	= obj.price - this.goods_price;
			obj.price	= obj.ori_price;
			var x		= this.option_divide_title_count - n;
			var tmp		= '<option value="' + obj.opt + '" price="' + obj.price + '" infomation="' + obj.infomation + '"';
			if	( obj.chk_stock ){
				if	( obj.color && $("select[name='" + this.options_selectbox_name + "']").eq(n).attr('opttype') == 'color'  ) {
					if	( obj.ismobile ){
						Optionsspecialhtml	+= '<span class="' + obj.color + '">'
											+ '<span name="viewOptionsspecialbtn" opspecialtype="color" '
											+ 'class="' + this.option_special_btn + ' hand bbs_btn ' + obj.color + '" '
											+ 'style="color:' + obj.color + ';"  value="' + obj.opt + '" '
											+ 'optvalue="' + obj.opt + '" price="' + obj.price + '" '
											+ 'infomation="' + obj.infomation + '" eqindex="' + n + '" '
											+ 'opspecial_location="' + obj.opspecial_location.color + '">'
											+ '<font style="background-color:' + obj.color + ';">■</font></span></span>';
					}else{
						Optionsspecialhtml	+= '<span class="' + obj.color + '">'
											+ '<span name="viewOptionsspecialbtn" opspecialtype="color" '
											+ 'class="' + this.option_special_btn + ' hand bbs_btn ' + obj.color + '" '
											+ 'style="color:' + obj.color + ';" value="' + obj.opt + '" '
											+ 'optvalue="' + obj.opt + '" price="' + obj.price + '" '
											+ 'infomation="' + obj.infomation + '" eqindex="' + n + '" '
											+ 'opspecial_location="' + obj.opspecial_location.color + '">'
											+ '<font style="background-color:' + obj.color + ';">■</font></span></span>';
					}
				}

				if			( add_price == 0 || x > 1 ){
					tmp	+= '>' + obj.opt + '</option>';
				}else if	(add_price > 0) {
					tmp	+= '>' + obj.opt + '(+' + this.comma(add_price) + ')</option>';
				}else if	(add_price < 0){
					tmp	+= '>' + obj.opt + '(-' + this.comma(add_price*-1) + ')</option>';
				}
			}else{
				if	( obj.color && $("select[name='" + this.options_selectbox_name + "']").eq(n).attr('opttype') == 'color'  ) {
					if	( obj.ismobile ){
						Optionsspecialhtml	+= '<span class="' + obj.color + '">'
											+ '<span name="viewOptionsspecialbtnDisable" opspecialtype="color" '
											+ 'class="viewOptionsspecialbtnDisable hand bbs_btn ' + obj.color + '" '
											+ 'style="color:' + obj.color + ';"  value="' + obj.opt + '" '
											+ 'optvalue="' + obj.opt + '" price="' + obj.price + '" '
											+ 'infomation="' + obj.infomation + '" eqindex="' + n + '" '
											+ 'opspecial_location="' + obj.opspecial_location.color + '">'
											+ '<font style="background-color:' + obj.color + ';">■</font>(' + getAlert('sy001') + ')</span></span>';
					}else{
						Optionsspecialhtml	+= '<span class="' + obj.color + '">'
											+ '<span name="viewOptionsspecialbtnDisable" opspecialtype="color" '
											+ 'class="viewOptionsspecialbtnDisable hand bbs_btn ' + obj.color + '" '
											+ 'style="color:' + obj.color + ';" value="' + obj.opt + '" '
											+ 'optvalue="' + obj.opt + '" price="' + obj.price + '" '
											+ 'infomation="' + obj.infomation + '" eqindex="' + n + '" '
											+ 'opspecial_location="' + obj.opspecial_location.color + '">'
											+ '<font style="background-color:' + obj.color + ';">■</font>(' + getAlert('sy001') + ')</span></span>';
					}
				}

				tmp	+= ' disabled="disabled">' + obj.opt + ' (' + getAlert('sy001') + ')</option>';
			}
			$("select[name='" + this.options_selectbox_name + "']").eq(n).append(tmp);

			// 컬러 옵션 클릭시 상품상세컷 연결 2014-05-13 leewh
			if (obj.color) {
				var colorIdx	= parseInt( i + 1 );
				$("select[name='" + this.options_selectbox_name + "'] option:eq(" + colorIdx + ")").eq(n).attr("color", obj.color);
			}
			// 컬러 옵션 end
		}

		//색상노출
		if	( $("select[name='" + this.options_selectbox_name + "']").eq(n).attr('opttype') == 'color' ) {
			$("select[name='" + this.options_selectbox_name + "']").eq(n).closest('td').find('.' + this.option_special_lay).html(Optionsspecialhtml);
		}

		this.set_bind_option();
	};

	// 필수옵션 색상 버튼 선택
	this.click_color_btn	= function(obj){
		$(obj).closest('tr').find("select[name='" + this.options_selectbox_name + "']").val($(obj).attr('optvalue')).change();
		this.chg_selectbox_selected($(obj).closest('tr').find("select[name='" + this.options_selectbox_name + "']"));
	};

	// 필수옵션 선택 유효성 체크
	this.chkOptionRequired	= function(){
		var result		= true;
		$("select[name='" + this.options_selectbox_name + "']").each(function(){
			if	(this.string_price_use || this.string_button_use){
				$(this).find('option').eq(0).attr('selected', true);
				result	= false;
				return false;
			}

			if	($(this).find('option:selected').val() == '' ){
				//옵션을 선택해 주세요2
				openDialogAlert(getAlert('gv034'), 400, 140,'');
				$(this).focus();
				result	= false;
				return false;
			}
		});

		return result;
	};

	// 필수옵션 선택 처리
	this.viewOptionsApply	= function(obj){
		var msg				= '';
		var submsg			= '';
		var optTag			= '';
		var price			= 0;
		var optTitle		= '';
		var result			= false;

		// 필수옵션 체크
		var chkOption		= this.chkOptionRequired();
		if	(!chkOption)	return false;

		// 입력옵션 체크
		var chkInput		= this.chkInputRequired();
		if	(!chkInput)		return false;

		// 추가옵션 체크
		var chkSuboption	= this.chkSuboptionRequired();
		if	(!chkSuboption)	return false;

		// 중복옵션 체크
		var chkDuplicate	= this.chkDuplicateRequired(obj);
		if	(!chkDuplicate)	return false;

		// 색상컷변경
		var color = $(".viewOptionsspecialbtn[value='"+$(obj).find('option:selected').val()+"']").parent().attr('class');
		if(color){
			$("#goods_thumbs .pagination>li img[color='"+color+"']").eq(0).trigger("click");
		}

		if	(typeof $(obj).find('option:selected').attr('infomation') != 'undefined' && $(obj).find('option:selected').attr('infomation') != '' && $(obj).find('option:selected').attr('infomation') != 'null'){
			$('#viewoptionsInfoTr').show();
			$('#viewOptionsInfo').html($(obj).find('option:selected').attr('infomation'));
		}else{
			$('#viewoptionsInfoTr').hide();
			$('#viewOptionsInfo').html('');
		}

		// 선택된 옵션들 추가
		price			= $("select[name='" + this.options_selectbox_name + "']").last().find('option:selected').attr('price');

		price			= get_currency_price(price,1,this.currency,this.currency_symbol,this.currency_symbol_position);

		consumer_price			= $("select[name='" + this.options_selectbox_name + "']").last().find('option:selected').attr('consumer_price');
		consumer_price			= get_currency_price(consumer_price,1,this.currency,this.currency_symbol,this.currency_symbol_position);

		msg				= this.apply_options();
		inputmsg		= this.apply_inputs();
		submsg			= this.apply_suboptions();

		/** 선택 옵션의 val()을 조합하여 중복체크필요
		var opt_seq		= $("select[name='" + this.options_selectbox_name + "']").find('option:selected').attr('seq');
		var subopt_seq	= $("select[name='" + this.suboptions_selectbox_name + "']").find('option:selected').attr('seq');
		if(opt_seq != 'undefined' ){
			if($('.quanity_row[seq="'+opt_seq+'"]').length > 0){
				//중복 된 상품입니다.
				openDialogAlert(getAlert('gv035'), 400, 150);
				return false;
			}
		}else if(subopt_seq != 'undefined' ){
			if($('.quanity_row[subseq="'+subopt_seq+'"]').length > 0){
				//중복 된 상품입니다.
				openDialogAlert(getAlert('gv035'), 400, 150);
				return false;
			}
		}***/

		var addStyle	= '';
		if (typeof gl_operation_type != 'undefined' && gl_operation_type == 'light') {
			// 반응형
			optTag += '<tr class="quanity_row option_tr" opt_group="' + this.apply_option_seq + '">';
			optTag += '<td class="quantity_cell option_col_text" ' + addStyle + '>';
			optTag += '<div class="option_text">' + msg + '</div>';
			optTag += '<ul class="num_price">';
			optTag += '<li><button type="button" class="btn_graybox ' + this.ea_minus_class + '">-</button><input type="text" name="' + this.input_option_ea_name + '[' + this.apply_option_seq + ']" value="1" class="onlynumber ' + this.ea_input_class + '" /><button type="button" class="btn_graybox ' + this.ea_plus_class + '">+</button></li>';
			optTag += '<li class="quantity_cell option_col_price" ' + addStyle + '>';
			optTag += '    <span class="consumer_price hide">'+consumer_price+'</span>';
			optTag += '    <span class="' + this.option_price_class + ' hide">' + price + '</span><strong class="' + this.option_price_out_class + '">' + get_currency_price(price,'',this.currency,this.currency_symbol,this.currency_symbol_position) + '</strong> <img src="/data/skin/' + this.skin + '/images/common/icon_close_gray.png" class="hand ' + this.remove_option_class + '" />';
			optTag += '</li>';
			optTag += '</ul>';
			optTag += '</td>';
			optTag += '</tr>';
		} else {
			// 적응형
			if	(this.apply_option_seq > 0)	addStyle	= ' style="border-width:1px;"';
			if	(this.mobile_mode){
				optTag += '<tr class="quanity_row option_tr" opt_group="' + this.apply_option_seq + '">';
				optTag += '<td class="quantity_cell option_col_text" ' + addStyle + '>';
				optTag += '	<table align="left" border="0" cellpadding="0" cellspacing="0">';
				optTag += '	<tr>';
				optTag += '	    <td class="option_text">' + msg + '</td>';
				optTag += '	</tr>';
				optTag += '	<tr>';
				optTag += '		<td style="white-space:nowrap;" class="pdt5"><button type="button" class="btn_graybox ' + this.ea_minus_class + '">-</button><input type="text" name="' + this.input_option_ea_name + '[' + this.apply_option_seq + ']" value="1" class="onlynumber ' + this.ea_input_class + '" style="text-align:center; width:31px; height:31px; border:1px solid #d0d0d0;" /><button type="button" class="btn_graybox ' + this.ea_plus_class + '">+</button></td>';
				optTag += '	</tr>';
				optTag += '	</table>';
				optTag += '</td>';
				optTag += '<td class="quantity_cell option_col_price" ' + addStyle + ' align="right" colspan="2" valign="bottom" class="pdb5">';
				optTag += ' <span class="consumer_price hide">'+consumer_price+'</span>';
				optTag += '	<span class="' + this.option_price_class + ' hide">' + price + '</span><strong class="' + this.option_price_out_class + '">' + get_currency_price(price,'',this.currency,this.currency_symbol,this.currency_symbol_position) + '</strong> <img src="/data/skin/' + this.skin + '/images/common/icon_close_gray.png" class="hand ' + this.remove_option_class + '" />';
				optTag += '</td>';
				optTag += '</tr>';
			}else{
				optTag += '<tr class="quanity_row option_tr" opt_group="' + this.apply_option_seq + '">';
				optTag += '<td class="quantity_cell option_text" ' + addStyle + '>' + msg + '</td>';
				optTag += '<td class="quantity_cell" ' + addStyle + ' align="center">';
				optTag += '	<table border="0" cellpadding="1" cellspacing="0">';
				optTag += '	<tr>';
				optTag += '		<td><img src="/data/skin/' + this.skin + '/images/common/btn_minus.gif" class="hand ' + this.ea_minus_class + '" /><input type="text" name="' + this.input_option_ea_name + '[' + this.apply_option_seq + ']" value="1" class="onlynumber ' + this.ea_input_class + '" /><img src="/data/skin/' + this.skin + '/images/common/btn_plus.gif" class="hand ' + this.ea_plus_class + '" /></td>';
				optTag += '	</tr>';
				optTag += '	</table>';
				optTag += '</td>';
				optTag += '<td class="quantity_cell option_col_price" ' + addStyle + ' align="right" valign="bottom" class="pdb5">';
				optTag += ' <span class="consumer_price hide">'+consumer_price+'</span>';
				optTag += '	<span class="' + this.option_price_class + ' hide">' + price + '</span><strong class="' + this.option_price_out_class + '">' + get_currency_price(price,'',this.currency,this.currency_symbol,this.currency_symbol_position) +' </strong> <img src="/data/skin/' + this.skin + '/images/common/icon_close_gray.png" class="hand ' + this.remove_option_class + '" />';
				optTag += '</td>';
				optTag += '</tr>';
			}
		}

		// 선택된 옵션들 초기화
		this.reset_options();
		this.reset_inputs();
		this.reset_suboptions();

		$('table.'+this.result_table_class).append(optTag+inputmsg+submsg);
		$('div.'+this.result_table_class+'_container').show();

		this.calculate_goods_price();

		this.set_bind_option();
		this.apply_option_seq++;

		//mobile ver3 옵션 포커스이동
		if( typeof gl_mobile_mode != 'undefined' && gl_mobile_mode == 3){
			$("div.goods_option").scrollTop($('div#select_option_lay').prop('scrollHeight'));
		}
		setDefaultText();
	};

	// 필수옵션 추가 html
	this.apply_options		= function(){
		var that		= this;
		var result		= '';

		$("select[name='" + this.options_selectbox_name + "']").each(function(idx){
			if	(result) result += '<br />';
			optTitle = $('.optionTitle').eq(idx).html();
			result	+= optTitle;
			result	+= ' : ' + $(this).find('option:selected').val();

			if	(that.option_view_type == 'join'){
				var optTitleArr	= optTitle.split(',');
				var optTitleLen	= optTitleArr.length;
				var optKey		= '';
				for ( var o = 0; o < optTitleLen; o++ ){
					optKey	= 'opt' + (o + 1);
					result	+= '<input type="hidden" name="' + that.selected_option_name + '[' + that.apply_option_seq + '][' + o + ']" class="selected_options" opt_group="' + that.apply_option_seq + '" opt_seq="' + o + '" value="' + $(this).find('option:selected').attr(optKey) + '" />';
					result	+= '<input type="hidden" name="' + that.selected_optionTitle_name + '[' + that.apply_option_seq + '][' + o + ']" class="selected_options_title" opt_group="' + that.apply_option_seq + '" opt_seq="' + o + '" value="' + optTitleArr[o] + '" />';
				}
			}else{
				result	+= '<input type="hidden" name="' + that.selected_option_name + '[' + that.apply_option_seq + '][' + idx + ']" class="selected_options" opt_group="' + that.apply_option_seq + '" opt_seq="' + idx + '" value="' + $(this).find('option:selected').val() + '" />';
				result	+= '<input type="hidden" name="' + that.selected_optionTitle_name + '[' + that.apply_option_seq + '][' + idx + ']" class="selected_options_title" opt_group="' + that.apply_option_seq + '" opt_seq="' + idx + '" value="' + optTitle + '" />';
			}
		});

		return result;
	};

	// 필수옵션 선택 값 초기화
	this.reset_options		= function(){
		$("select[name='" + this.options_selectbox_name + "']").each(function(idx){
			$(this).find('option').eq(0).attr('selected', true);
		});
	};

	// 옵션 설명 노출
	this.set_infomation		= function(obj){
		if	(typeof $(obj).find('option:selected').attr('infomation') != 'undefined' && $(obj).find('option:selected').attr('infomation') != '' && $(obj).find('option:selected').attr('infomation') != 'null'){
			$('#viewoptionsInfoTr').show();
			$('#viewOptionsInfo').html($(obj).find('option:selected').attr('infomation'));
		}else{
			$('#viewoptionsInfoTr').hide();
			$('#viewOptionsInfo').html('');
		}
	};


///////////////// 추가옵션 관련 처리 함수 /////////////////


	// 추가옵션 유효성 체크
	this.chkSuboptionRequired	= function(){

		var result					= true;
		var required_group			= new Array();
		var suboption_title			= '';

		$("select[name='" + this.suboptions_selectbox_name + "']").each(function(){
			suboption_title		= $(this).closest('tr').find('.suboptionTitle').text();
			if	($(this).attr('isrequired') == 'y' && suboption_title && !required_group[$(this).attr('requiredgroup')]){
				required_group[$(this).attr('requiredgroup')]	= suboption_title;
			}
		});

		if	(required_group.length > 0){
			for	(var idx in required_group) {
				if	(!isNaN(idx)){	// for in 문의 버그로 key가 숫자일 경우만 체크하게 수정
					result	= false;
					$("select[name='" + this.suboptions_selectbox_name + "'][requiredgroup='" + idx + "']").each(function(){
						if	($(this).val())	result	= true;
					});

					if	(!result){
						//옵션은 필수입니다.
						openDialogAlert(required_group[idx] + ' '+ getAlert('gv036'), 400, 140,'');
						$("select[name='" + this.suboptions_selectbox_name + "'][requiredgroup='" + idx + "']").eq(0).focus();
						result	= false;
						break;
					}
				}
			}
		}

		return result;
	};

	// 추가구성옵션 복사
	this.add_suboption		= function(obj){
		var cur_tr		= $(obj).closest('tr');
		var cur_grp		= cur_tr.attr('subGroupIdx');

		var copy_obj	= cur_tr.clone();
		copy_obj.find('td.suboptionTitle').html('');
		copy_obj.find('td.btn_pm_td').html('<span class="btn-minus gray" style="padding-top:10px;"><button class="btn_del_suboption btn_graybox" type="button"></button></span>');
		copy_obj.find("select[name='" + this.suboptions_selectbox_name + "']").removeAttr('sb').removeAttr('style');
		copy_obj.find("select[name='" + this.suboptions_selectbox_name + "'] option").eq(0).attr('selected', true);
		copy_obj.find('.sbHolder').remove();
		copy_obj.find('button.btn_del_suboption').bind('click', function(){
			$(this).closest('tr').remove();
		});

		var final_tr	= $("tr.suboptionTr[subGroupIdx='" + cur_grp + "']").last();
		var new_idx		= parseInt($('tr.suboptionTr').index(final_tr)) + 1;
		final_tr.after(copy_obj);

		// clone내에서는 selectbox의 bind 추가가 안되서 clone 추가 후 attach한다.
		this.setSelectBoxPlugin($("select[name='" + this.suboptions_selectbox_name + "']").eq(new_idx), '');
		this.set_bind_option();
	};

	// 추가옵션 색상 버튼 선택
	this.subclick_color_btn	= function(obj){
		$(obj).closest('tr').find("select[name='" + this.suboptions_selectbox_name + "']").val($(obj).attr('suboptvalue')).change();
		this.chg_selectbox_selected($(obj).closest('tr').find("select[name='" + this.suboptions_selectbox_name + "']"));
	};

	// 추가옵션 추가
	this.apply_suboptions		= function(){

		var that				= this;
		var msg					= '';
		var result				= '';
		var price				= 0;
		var grpidx				= 0;
		var title				= '';
		var suboption			= '';
		var apply_option_seq	= this.apply_option_seq;
		if	(this.suboption_layout_group == 'first')	apply_option_seq	= that.min_apply_option_seq;


		$("select[name='" + this.suboptions_selectbox_name + "']").each(function(idx){
			if	($(this).val()){
				grpidx		= $(this).attr('requiredgroup');
				title		= $("select[name='"+that.suboptions_selectbox_name+"'][requiredgroup='"+grpidx+"']").eq(0).closest('tr').find('.suboptionTitle').html();
				suboption	= $(this).find('option:selected').val();
				price		= $(this).find('option:selected').attr('price');

				// 선택 버튼이 없는 경우 idx를 수동 계산해서 변경해 준다.
				if	(!that.add_action_to_button){
					if	($('input.suboption').length > 0)		idx		= $('input.suboption').length;
					else										idx		= 0;
				}

				msg		= title + ' : ' + suboption;
				msg		+= '<input type="hidden" name="' + that.selected_suboption_name + '[' + apply_option_seq + '][' + idx + ']" class="suboption selected_suboptions" opt_group="' + apply_option_seq + '" opt_seq="' + idx + '" value="' + suboption + '">';
				msg		+= '<input type="hidden" name="' + that.selected_suboptionTitle_name + '['+apply_option_seq + '][' + idx + ']" class="selected_suboptions_title" opt_group="' + apply_option_seq + '" opt_seq="' + idx + '" value="' + title + '">';

				if (typeof gl_operation_type != 'undefined' && gl_operation_type == 'light') {
					// 반응형
					result += '<tr class="quanity_row suboption_tr" opt_group="' + apply_option_seq + '">';
					result += '<td class="quantity_cell_sub">';

					result += '<div class="option_text"><span class="btn_small_normal">' + getAlert("oc043") + '</span> ' + msg + '</div>';  // 추가
					result += '<ul class="num_price">';
					result += '<li><button type="button" class="btn_graybox ' + that.ea_minus_class + '">-</button><input type="text" name="' + that.input_suboption_ea_name + '[' + apply_option_seq + '][' + idx + ']" value="1" class="onlynumber ' + that.ea_input_class + '" style="text-align:center; width:31px; height:31px; border:1px solid #d0d0d0;" /><button type="button" class="btn_graybox ' + that.ea_plus_class + '">+</button></li>';
					result += '<li class="quantity_cell_sub_price">';
					result += '	<span class="' + that.suboption_price_class + ' hide">' + price + '</span><strong class="' + that.suboption_price_out_class + '">' + get_currency_price(price,'',that.currency,that.currency_symbol,that.currency_symbol_position) + '</strong> <img src="/data/skin/' + that.skin + '/images/common/icon_close_gray.png" class="hand ' + that.remove_option_class + '" /></li>';
					result += '</ul>';
					result += '</td>';
					result += '</tr>';
				} else {
					// 적응형
					if	(that.mobile_mode){
						result += '<tr class="quanity_row suboption_tr" opt_group="' + apply_option_seq + '">';
						result += '<td class="quantity_cell_sub">';
						result += '	<table align="left" border="0" cellpadding="0" cellspacing="0">';
						result += '	<tr>';
						result += '     <td class="option_text"><span class="btn_small_normal">' + getAlert("oc043") + '</span> ' + msg + '</td>';  // 추가
						result += '	</tr>';
						result += '	<tr>';
						result += '		<td class="pdt5" ><button type="button" class="btn_graybox ' + that.ea_minus_class + '">-</button><input type="text" name="' + that.input_suboption_ea_name + '[' + apply_option_seq + '][' + idx + ']" value="1" class="onlynumber ' + that.ea_input_class + '" style="text-align:center; width:31px; height:31px; border:1px solid #d0d0d0;" /><button type="button" class="btn_graybox ' + that.ea_plus_class + '">+</button></td>';
						result += '	</tr>';
						result += '	</table>';
						result += '</td>';
						result += '<td class="quantity_cell_sub_price" align="right" colspan="2" valign="bottom" class="pdt5" >';
						result += '	<span class="' + that.suboption_price_class + ' hide">' + price + '</span><strong class="' + that.suboption_price_out_class + '">' + get_currency_price(price,'',that.currency,that.currency_symbol,that.currency_symbol_position) + '</strong> <img src="/data/skin/' + that.skin + '/images/common/icon_close_gray.png" class="hand ' + that.remove_option_class + '" />';
						result += '</td>';
						result += '</tr>';
					}else{
						result += '<tr class="quanity_row suboption_tr" opt_group="' + apply_option_seq + '">';
						result += '<td class="option_text quantity_cell_sub"><span class="btn_small_normal">' + getAlert("oc043") + '</span> ' + msg + '</td>';  // 추가
						result += '<td class="quantity_cell_sub" align="center">';
						result += '	<table border="0" cellpadding="1" cellspacing="0">';
						result += '	<tr>';
						result += '		<td><img src="/data/skin/' + that.skin + '/images/common/btn_minus.gif" class="hand ' + that.ea_minus_class + '" /><input type="text" name="' + that.input_suboption_ea_name + '[' + apply_option_seq + '][' + idx + ']" value="1" class="onlynumber ' + that.ea_input_class + '" /><img src="/data/skin/' + that.skin + '/images/common/btn_plus.gif" class="hand ' + that.ea_plus_class + '" /></td>';
						result += '	</tr>';
						result += '	</table>';
						result += '</td>';
						result += '<td class="quantity_cell_sub_price" align="right" valign="bottom" class="pdt5" >';
						result += '	<span class="' + that.suboption_price_class + ' hide">' + price + '</span><strong class="' + that.suboption_price_out_class + '">' + get_currency_price(price,'',that.currency,that.currency_symbol,that.currency_symbol_position) + '</strong> <img src="/data/skin/' + that.skin + '/images/common/icon_close_gray.png" class="hand ' + that.remove_option_class + '" />';
						result += '</td>';
						result += '</tr>';
					}
				}
			}
		});

		// 추가옵션이 첫번째 묶이는 경우 필수옵션이 선행 선택되었는지 체크
		if	(result && this.use_option && this.suboption_layout_group == 'first'){
			if	($('input.selected_options').length > 0){
			}else{
				//선택된 상품옵션이 없습니다.
				openDialogAlert(getAlert('gv037'), 400, 140,'');
				result	= '';
			}
		}

		return result;
	};

	// 추가옵션 선택 값 초기화
	this.reset_suboptions		= function(){
		var that					= this;
		var suboption_group			= new Array();
		$("select[name='" + this.suboptions_selectbox_name + "']").each(function(idx){
			if	(!suboption_group[$(this).attr('requiredgroup')])
				suboption_group[$(this).attr('requiredgroup')]	= 1;
		});

		for	(var grpidx in suboption_group) {
			$("select[name='" + this.suboptions_selectbox_name + "'][requiredgroup='"+grpidx+"']").each(function(idx){
				if	(idx > 0){
					$(this).closest('tr.suboptionTr').remove();
				}else{
					$(this).find('option').eq(0).attr('selected', true);
					that.chg_selectbox_selected($(this));
				}
			});
		}
	};

///////////////// 입력옵션 관련 처리 함수 /////////////////


	// 입력옵션 유효성 체크
	this.chkInputRequired	= function(){

		var result		= true;
		$("input[name='" + this.inputs_inputbox_name + "'],textarea[name='" + this.inputs_inputbox_name + "']").each(function(){
			var inputsTitle		= $(this).closest('tr').find('.inputsTitle').html();
			if	($(this).attr('isrequired') == 'y' && !$(this).val().length){
				//옵션을 입력해 주세요.
				openDialogAlert(inputsTitle + ' '+getAlert('gv038'), 400, 140,'');
				$(this).focus();
				result	= false;
				return false;
			}

			if	($(this).attr('inputlimit') > 0 && $(this).val().length > $(this).attr('inputlimit')){
				//'은 ' + $(this).attr('inputlimit') + '자 이하로 입력해 주세요.'
				openDialogAlert(inputsTitle +getAlert('gv039'), 400, 140,'');
				$(this).focus();
				result	= false;
				return false;
			}
		});

		return result;
	};


	// 파일업로드버튼(Uploadify) 적용
	this.setUserUploadifyButton	= function(uplodifyButtonId, setting){
		//한글도메인체크@2013-03-12
		var fdomain		= document.domain;
		var kordomainck	= false;
		for	(var i = 0; i < fdomain.length; i++){
			if	(((fdomain.charCodeAt(i) > 0x3130 && fdomain.charCodeAt(i) < 0x318F) || (fdomain.charCodeAt(i) >= 0xAC00 && fdomain.charCodeAt(i) <= 0xD7A3))){
				kordomainck	= true;
				break;
			}
		}
		if	( !kordomainck )	krdomain	= '';

		var defaultSetting	= {
			'script'			: krdomain+'/common/upload_file',
			'uploader'			: '/app/javascript/plugin/jquploadify/uploadify.swf',
			'buttonImg'			: '/app/javascript/plugin/jquploadify/uploadify-search.gif',
			'cancelImg'			: '/app/javascript/plugin/jquploadify/uploadify-cancel.png',
			'fileTypeExts'		: '*.jpg;*.gif;*.png;*.jpeg',
			'fileTypeDesc'		: 'Image Files (.JPG, .GIF, .PNG)',
			'removeCompleted'	: true,
			'width'				: 64,
			'height'			: 20,
			'auto'				: true,
			'multi'				: false,
			'scriptData'		: {'randomFilename':1},
			'completeMsg'		: '적용 가능',
			'onCheck'			: function(event,data,key) {
				$("#"+uplodifyButtonId+key).find('.percentage').html('<font color="red"> - 파일명 중복</font>');
			},
			'onComplete'		: function (event, ID, fileObj, response, data) {
				var result		= eval(response)[0];
				if	(result.status != 1){
					openDialogAlert(result.msg,400,150);
					$('#' + uplodifyButtonId + ID).find('.percentage').html('<font color="red"> - ' + result.desc + '</font>');
					return false;
				}else{
					var webftpFormItemObj	= $('#' + uplodifyButtonId + ID).closest('.webftpFormItem');
					webftpFormItemObj.find('.webftpFormItemInput').val(result.filePath);
					webftpFormItemObj.find('.webftpFormItemInputOriName').val(result.fileInfo.client_name);
					webftpFormItemObj.find('.webftpFormItemPreview').attr('src', krdomain + '/' + result.filePath).show().attr('onclick', 'window.open("' + krdomain + '/' + result.filePath + '");').css('cursor', 'pointer');
					if	(webftpFormItemObj.find('.webftpFormItemInput').length){
						webftpFormItemObj.find('.webftpFormItemInput').trigger('change');
					}
					if	(webftpFormItemObj.find('.webftpFormItemInput').closest('form').length){
						webftpFormItemObj.find('.webftpFormItemInput').closest('form').trigger('change');
					}
					if	(webftpFormItemObj.find('.webftpFormItemPreviewSize').length){
						webftpFormItemObj.find('.webftpFormItemPreviewSize').html(result.fileInfo.image_width + ' x ' + result.fileInfo.image_height);
					}
					webftpFormItemObj.find('object').css('vertical-align', 'middle');
				}
			},
			'onError'			: function (event,ID,fileObj,errorObj) {
				openDialogAlert(errorObj.type + ' Error: ' + errorObj.info, 400, 140, '');
			}
		};

		if	(setting){
			for(var i in setting){
				if	( i == 'scriptData' ){
					for(var j in setting[i]){
						defaultSetting[i][j]	= setting[i][j];
					}
				}else{
					defaultSetting[i]	= setting[i];
				}
			}
		}

		$('#' + uplodifyButtonId).uploadify(defaultSetting);
	};

	// 입력옵션 추가 html
	this.apply_inputs		= function(){

		var that		= this;
		var result		= '';

		if	(this.inputoption_layout_position == 'down' && this.inputoption_data){
			result		= this.add_input_down_form();
		}else{
			if	($("input[name='" + this.inputs_inputbox_name + "'],textarea[name='" + this.inputs_inputbox_name + "']").length){
				$("input[name='" + this.inputs_inputbox_name + "'],textarea[name='" + this.inputs_inputbox_name + "']").each(function(idx){
					inputsTitle		= $('.inputsTitle').eq(idx).text();
					result			+= '<tr class="quanity_row inputoption_tr" opt_group="' + that.apply_option_seq + '">';
					if (typeof gl_operation_type != 'undefined' && gl_operation_type == 'light') {
						result			+= '<td class="quantity_cell option_text" style="border-top:none;">';
					} else {
						result			+= '<td class="quantity_cell option_text" style="border-top:none;" colspan="3">';
					}
					result			+= inputsTitle;
					result			+= '<input type="hidden" name="' + that.selected_inputoptionTitle_name + '[' + that.apply_option_seq + '][' + idx + ']" class="selected_inputs_title" opt_group="' + that.apply_option_seq + '" opt_seq="' + idx + '"  value="' + inputsTitle + '" />';
					//result			+= '</td>';
					//result			+= '</tr>';
					//result			+= '<tr class="quanity_row inputoption_tr" opt_group="' + that.apply_option_seq + '">';
					//result			+= '<td class="quantity_cell option_text" style="border-top:none;" colspan="3">';
					//result			+= '<div style="width:100%;">';
					var inputText;
					if			($(this).closest('.webftpFormItem').length){
						result		+= $(this).closest('.webftpFormItem').find('.webftpFormItemInputOriName').val();
						inputText = $(this).closest('.webftpFormItem').find('.webftpFormItemInputOriName').val();
					}else if	($(this).hasClass('fmuploadInputs')){
						if($(this).closest('table.upload-tb').find('.prevTxt').text())
								result		+= $(this).closest('table.upload-tb').find('.prevTxt').text();
						else	result		+= ' : ' + getAlert('gv112');

						if ( $(this).closest('table.upload-tb').find('.prevTxt').text() )
							inputText = "data/tmp/" + $(this).closest('table.upload-tb').find('.prevTxt').text();
						else
							inputText = $(this).closest('table.upload-tb').find('.prevTxt').text();
					}else{
						checkVal	= $.trim($(this).val());
						if(checkVal.length > 0)		result	+= ' : ' + $(this).val();
						else						result	+= ' : ' + getAlert('gv111');
						inputText = $(this).val();
					}
					inputText = inputText.replace(/\"/gm, "&quot;");
					//result			+= '</div>';
					result			+= '<input type="hidden" name="' + that.selected_inputoption_name + '[' + that.apply_option_seq + '][' + idx + ']" class="selected_inputs" opt_group="' + that.apply_option_seq + '" opt_seq="' + idx + '" value="' + inputText + '" />';
					result			+= '</td>';
					result			+= '</tr>';
				});
			}
		}

		return result;
	};

	// 입력옵션 선택 값 초기화
	this.reset_inputs		= function(){
		$("input[name='" + this.inputs_inputbox_name + "'],textarea[name='" + this.inputs_inputbox_name + "']").each(function(idx){
			$(this).val('');
			if			($(this).closest('.webftpFormItem').length){
				$(this).closest('.webftpFormItem').find('input').val('');
				$(this).closest('.webftpFormItem').find('.webftpFormItemPreview').attr('src', '').hide();
			}else if	($(this).closest('.upload-tb').length){
				$(this).closest('.upload-tb').find('.prevImg').attr('src', 'about:blank;').hide();
				$(this).closest('.upload-tb').find('.prevTxt').html('');
				$(this).closest('.upload-tb').find('.fmuploadInputs').val('');
			}
		});
	};

	// 입력옵션 하단 폼 추가
	this.add_input_down_form	= function(){

		var result		= '';
		var addRequire	= '';
		var data		= '';
		var dataLen		= 0;
		var i			= 0;

		if	(this.inputoption_layout_position == 'down' && this.inputoption_data){
			dataLen	= this.inputoption_data.length;
			for	( i = 0; i < dataLen; i++){
				data		= this.inputoption_data[i];
				addRequire	= '';
				if			(data.input_require > 0)	addRequire	= ' isrequired="y" title="(필수)"';

				result	+= '<tr class="quanity_row inputoption_tr" opt_group="' + this.apply_option_seq + '">';
				if (typeof gl_operation_type != 'undefined' && gl_operation_type == 'light') {
					result			+= '<td class="quantity_cell option_text" style="border-top:none;">';
				} else {
					result			+= '<td class="quantity_cell option_text" style="border-top:none;" colspan="3">';
				}
				result	+= '<input name="' + this.selected_inputoptionTitle_name + '[' + this.apply_option_seq + '][' + i + ']" class="selected_inputs_title" type="hidden" value="' + data.input_name + '" opt_group="' + this.apply_option_seq + '" opt_seq="' + i + '" />';
				result	+= '<table width="100%" border="0" cellpadding="0" cellspacing="0">';
				result	+= '<tr>';
				if	(data.input_form == 'file'){
					result	+= '<td style="width:' + (data.input_name.length * 10 + 20) + 'px;max-width:200px;">' + data.input_name + ' : </td>';
					result	+= '<td>';
					result	+= '<table class="upload-tb" border="0" cellpadding="0" cellspacing="0">';
					result	+= '<tr>';
					result	+= '<td><div class="' + this.inputs_upload_button + '" id="' + this.fm_upload_id + '_' + this.apply_option_seq + '_' + i + '" uploadType="fmupload"></div></td>';
					result	+= '<td><img src="about:blank;" class="prevImg" style="display:none;height:20px;" /></td>';
					result	+= '<td><span class="prevTxt"></span><input type="hidden" name="' + this.selected_inputoption_name + '[' + this.apply_option_seq + '][' + i + ']" class="selected_inputs fmuploadInputs" type="hidden" value="" ' + addRequire + ' opt_group="' + this.apply_option_seq + '" opt_seq="' + i + '" /></td>';
					result	+= '</tr>';
					result	+= '</table>';
					result	+= '</td></tr></table>';
					result	+= '</td></tr>';
				}else{
					result	+= '<td>' + data.input_name + '</td>';
					if	(data.input_limit > 0)
						result	+= '<td style="text-align:right;color:#6c6c6c;"><span class="inputByte_' + this.apply_option_seq + '_' + i + '">0</span>/' + data.input_limit + '</td>';
					else
						result	+= '<td style="text-align:right;color:#6c6c6c;"><div style="display:none;"><span class="inputByte_' + this.apply_option_seq + '_' + i + '">0</span>/' + data.input_limit + '</div></td>';
					result	+= '</tr></table>';
					result	+= '</td></tr>';
					result	+= '<tr class="quanity_row inputoption_tr" opt_group="' + this.apply_option_seq + '">';
					if (typeof gl_operation_type != 'undefined' && gl_operation_type == 'light') {
						result			+= '<td class="quantity_cell option_text" style="border-top:none;">';
					} else {
						result			+= '<td class="quantity_cell option_text" style="border-top:none;" colspan="3">';
					}
					if			(data.input_form == 'edit'){
						result	+= '<div class="viewInputTextareaLay"><textarea rows="2" name="' + this.selected_inputoption_name + '[' + this.apply_option_seq + '][' + i + ']" class="selected_inputs inputlimit" inputlimit="' + data.input_limit + '" ' + addRequire + ' opt_group="' + this.apply_option_seq + '" opt_seq="' + i + '" style="width:100%;"></textarea></div>';
					}else{
						result	+= '<div class="viewInputLay" style="width:100%;"><input type="text" name="' + this.selected_inputoption_name + '[' + this.apply_option_seq + '][' + i + ']" class="selected_inputs inputlimit" inputlimit="' + data.input_limit + '" ' + addRequire + ' opt_group="' + this.apply_option_seq + '" opt_seq="' + i + '" value="" style="width:100%;"/></div>';
					}
					result	+= '</td>';
					result	+= '</tr>';
				}
			}
		}

		return result;
	};

//////////////// Util 함수 ////////////////

	// selectbox plugin 적용해 주는 함수
	this.setSelectBoxPlugin		= function(obj, disableType){
		try{
			if	(disableType){
				obj.selectbox(disableType);
			}else if	(this.selectboxOnOpen || this.selectboxOnClose){
				var openFunc	= window[this.selectboxOnOpen];
				var closeFunc	= window[this.selectboxOnClose];
				obj.selectbox({
					'onOpen'	: function(inst){openFunc(inst);},
					'onClose'	: function(inst){closeFunc(inst);}
				});
			}else if	(this.selectboxOnOpen){
				var openFunc	= window[this.selectboxOnOpen];
				obj.selectbox({'onOpen'	: function(inst){openFunc(inst);}});
			}else if	(this.selectboxOnClose){
				var closeFunc	= window[this.selectboxOnClose];
				obj.selectbox({'onClose'	: function(inst){closeFunc(inst);}});
			}else{
				obj.selectbox();
			}
		}catch(e){}
	};

	// 선택된 옵션 삭제
	this.removeSelectedOptions	= function(obj){

		var that		= this;
		var tbody_obj	= $(obj).closest('tbody');
		var tr_obj		= $(obj).closest('tr');
		var grp_idx		= tr_obj.attr('opt_group');

		// 추가옵션 삭제
		if	(tr_obj.hasClass('suboption_tr')){

			var delete_status			= false;
			var suboption_title			= '';
			var current_title			= tr_obj.find('input.selected_suboptions_title').val();
			var current_idx				= tr_obj.find('input.selected_suboptions_title').attr('opt_seq');
			var current_required		= 'n';

			// 삭제하려는 추가옵션이 필수인지 체크
			$("select[name='" + this.suboptions_selectbox_name + "']").each(function(){
				suboption_title		= $(this).closest('tr').find('.suboptionTitle').text();
				if	($(this).attr('isrequired') == 'y' && suboption_title == current_title){
					current_required	= 'y';
					return false;
				}
			});

			// 필수인 추가옵션 삭제 시 대체 추가옵션이 있는지 체크
			if	(current_required == 'y'){
				tbody_obj.find("input[opt_group='" + grp_idx + "'].selected_suboptions_title").each(function(){
					if	($(this).attr('opt_seq') != current_idx &&  $(this).val() == current_title){
						delete_status	= true;
						return false;
					}
				});
			}else{
				delete_status	= true;
			}

			// 추가옵션 삭제
			if	(delete_status){

				tr_obj.remove();
			}else{
				//필수 선택 추가옵션입니다.
				openDialogAlert(getAlert('gv040'), 400, 140, '');
				return false;
			}

		// 필수옵션 삭제
		}else{

			if	(this.option_change_type && tbody_obj.find('tr.option_tr').length == 1){
				//더이상 선택된 옵션을 삭제할 수 없습니다.
				openDialogAlert(getAlert('gv041'), 400, 140, '');
				return false;
			}

			// 하위 추가옵션 제거
			tbody_obj.find('tr.suboption_tr').each(function(){
				if	($(this).attr('opt_group') == grp_idx){
					$(this).closest('tr').remove();
				}
			});

			// 하위 입력옵션 제거
			tbody_obj.find('tr.inputoption_tr').each(function(){
				if	($(this).attr('opt_group') == grp_idx){
					$(this).closest('tr').remove();
				}
			});

			// 필수옵션 제거
			tr_obj.remove();

			// 모든 필수옵션이 제거 되면 선택된 옵션 영역을 감춤
			if	(tbody_obj.find('tr.quanity_row').length < 1){
				tbody_obj.closest('div.'+this.result_table_class+'_container').hide();
			}

			// 추가 옵션의 그룹 방법이 첫번째 옵션에 그룹화 시킬 경우 필수 옵션최소 인덱스 키 저장
			if	(that.suboption_layout_group == 'first'){
				var min_apply_option_seq = 0;
				if(tbody_obj.find('tr.option_tr').length > 0){
					min_apply_option_seq = tbody_obj.find('tr.option_tr').eq(0).attr('opt_group');
                }else{
					min_apply_option_seq = that.apply_option_seq;
				}
				that.min_apply_option_seq	= min_apply_option_seq;
			}
		}

		this.calculate_goods_price();
	};

	// global의 comma와 같은 함수
	this.comma		= function(x){
		var temp	= '';
		var x		= String(this.uncomma(x));
		var num_len	= x.length;
		var co		= 3;

		while(num_len > 0){
			num_len		= num_len - co;
			if	(num_len < 0){
				co		= num_len + co;
				num_len	= 0;
			}
			temp	= ',' + x.substr(num_len, co) + temp;
		}
		return temp.substr(1);
	};

	// global의 uncomma와 같은 함수
	this.uncomma		= function(x){
		var reg		= /(,)*/g;
		x			= parseInt(String(x).replace(reg, ""));
		return (isNaN(x))	? 0		: x;
	};

	// 수량증가
	this.eaPlus		= function(obj){
		var eaObj	= $(obj).closest('tr').find('input.' + this.ea_input_class);
		var val		= parseInt(eaObj.val()) + 1;
		if	(val > 0) eaObj.val(val);
		this.calculate_goods_price();
		return false;
	};

	// 수량감소
	this.eaMinus	= function(obj){
		var eaObj	= $(obj).closest('tr').find('input.' + this.ea_input_class);
		var val		= parseInt(eaObj.val()) - 1;
		if	(val > 0)	eaObj.val(val);
		this.calculate_goods_price();
		return false;
	};

	// global의 calculate_goods_price와 같은 함수
	this.calculate_goods_price	= function(){
		var that			= this;
		var ea				= 0;
		var consumer_price  = 0;
		var tot				= 0;
		var price			= 0;
		var tot_ea			= 0;
		var goods_price		= this.goods_price;

		$('.' + this.option_price_class).each(function(){
			tot_ea	+= parseInt($(this).closest('tr').find('input.' + that.ea_input_class).val());
		});

		$('.' + this.option_price_class).each(function(idx){
			ea		= parseInt($(this).closest('tr').find('input.' + that.ea_input_class).val());
			if	(isNaN(ea) || !ea){
				$(this).closest('tr').find('input.' + that.ea_input_class).val('1');
				ea	= 1;
			}
			consumer_price		= parseInt(uncomma($(this).closest('tr').find('.consumer_price').html()));

			price		= uncomma($(this).html());
			fixed_price = price;					// 할인 계산을 위한 고정값 변수 추가 (기본할인만 적용 된 가격기준으로 할인 계산해야함) 2018-07-11 pjw


			// ################# 할인하는 기준값을 기본할인가격으로 고정 ################
			// #### 이벤트, 모바일, 등급은 sale_lib 값 그대로 사용 2018-07-12 pjw #####


			// 이벤트할인
			if(that.event_target_sale != '') {
				price	= that.calculate_event(price,consumer_price);
			} else {
				price = price - parseInt(that.event_sale_unit);
			}

			// 모바일할인 (모바일은 할인정보가 pc여도 나와서 mobile_sale_unit 값으로 직접 할인처리)
			price = price - parseInt(that.mobile_sale_unit);

			// 복수구매할인
			price	= that.calculate_muti_discount(tot_ea,price,fixed_price);

			// 등급할인
			if(that.member_sale_type != '') {
				price	= that.calculate_member(price, fixed_price);
			} else {
				that.member_sale_unit = isNaN(parseInt(that.member_sale_unit)) ? 0 : parseInt(that.member_sale_unit);
				price = price - parseInt(that.member_sale_unit);
			}

			// ##############################################################

			if(that.currency == "KRW" || that.currency == "JPY"){
				price = parseInt(price);
			}else{
				//기본통화가 원화/엔화가 아닐 경우 소수 둘째자리까지 표기
				price	= Math.floor(parseFloat(price)*100) / 100;
			}
			if	(price < 0) price = 0;
			$('.' + that.option_price_out_class).eq(idx).html(get_currency_price(price * ea,3,that.currency,that.currency_symbol,that.currency_symbol_position));
			tot		+= price * ea;
		});

		$('.' + this.suboption_price_class).each(function(i){
			ea		= parseInt($(this).closest('tr').find('input.' + that.ea_input_class).val());
			if	(isNaN(ea) || !ea){
				$(this).closest('tr').find('input.' + that.ea_input_class).val('1');
				ea	= 1;
			}
			price	= uncomma($(this).html());
			if	(price < 0) price = 0;
			$('.' + that.suboption_price_out_class).eq(i).html(get_currency_price(price * ea,2,that.currency,that.currency_symbol,that.currency_symbol_position));

			tot		+= price * ea;

		});
		
		if	(tot <= 0)	tot	= 0;
		
		tot = comma(get_currency_price(tot,'',that.currency,that.currency_symbol,that.currency_symbol_position));
		$('#' + this.total_goods_price_lay).html(tot);

		// 변경된 총 상품금액의 비교통화 변경
		chg_compare_currency(tot);
	};

	// 이벤트할인 계산
	this.calculate_event = function(price, consumer_price) {
		var event_target_sale	= this.event_target_sale;
		var event_sale_unit		= this.event_sale_unit;
		var sale_price			= 0;

		switch( event_target_sale ) {
			case '0' :
				// 할인가
				sale_price = (price * event_sale_unit) / 100;
			break;
			case '1' :
				// 정가
				sale_price = (consumer_price * event_sale_unit) / 100;
			break;
			case '2' :
				// 금액
				sale_price = event_sale_unit;
			break;
		}

		price = price - this.sale_price_cutting(sale_price);

		return price;
	}

	// 회원할인 계산
	this.calculate_member = function(price, fixed_price) {
		var member_sale_unit = this.member_sale_unit;
		var member_sale_type = this.member_sale_type;
		_this				= this;
		if( member_sale_type == 'PER') {
			var sale_price =  (fixed_price * parseFloat(member_sale_unit)/100);
			sale_price = _this.sale_price_cutting(sale_price);
			price = price - sale_price;
		} else {
			price = price - member_sale_unit;
		}

		return price;
	}

	// 복수구매할인 계산
	this.calculate_muti_discount	= function(ea, price, fixed_price){
		if	(!this.multi_discount_policylist && !this.multi_discount) return price;
		var multi_min_ea	= 0;
		_this				= this;
		// 슬라이딩일 경우
		$.each(this.multi_discount_policylist,function(){
			policyArr		= this.split('/');
			min_ea			= policyArr[0];
			max_ea			= policyArr[1];
			disc			= policyArr[2];
			multi_min_ea	= max_ea;
			if 	(min_ea && max_ea && disc){
				if	(min_ea <= ea && ea < max_ea){
					if	( _this.multi_discount_unit == 'PER' && disc <= 100 ){
						price -= _this.sale_price_cutting( Math.floor(fixed_price * disc / 100) );
					}else if	(price > parseFloat(disc) ) {
						price -= _this.sale_price_cutting(disc);
					}
				}
			}else if(min_ea && disc && _this.multi_discount_policylist.length == 1 && ea >= min_ea ){
				if	( _this.multi_discount_unit == 'PER' && disc <= 100 ){
					price -= _this.sale_price_cutting( Math.floor(fixed_price * disc / 100) );
				}else if	(price > parseFloat(disc) ) {
					price -= _this.sale_price_cutting(disc);
				}
			}
		});
		// 마지막 슬라이딩일 경우
		if( _this.multi_discount && _this.multi_discount_unit && multi_min_ea <= ea ){
			if	( _this.multi_discount_unit == 'PER' && _this.multi_discount <= 100 ){
				price -= _this.sale_price_cutting( Math.floor(fixed_price * _this.multi_discount / 100) );
			}else if	(price > parseFloat(_this.multi_discount) ) {
				price -= _this.sale_price_cutting(_this.multi_discount);
			}
		}
		return price;
	};

	// 할인 금액 절사
	this.sale_price_cutting		= function(price){
		if	(this.cutting_sale_price > 0){
			if	(this.cutting_sale_action == 'dscending'){
				price	= Math.floor(price / this.cutting_sale_price) * this.cutting_sale_price;
			}
			if	(this.cutting_sale_action == 'rounding'){
				price	= Math.round(price / this.cutting_sale_price) * this.cutting_sale_price;
			}
			if	(this.cutting_sale_action == 'ascending'){
				price	= Math.ceil(price / this.cutting_sale_price) * this.cutting_sale_price;
			}
		}
		return price;
	};

	// select박스의 값을 변경 후 selectbox plugin에 적용해 주는 함수
	this.chg_selectbox_selected	= function(obj){
		try{
			var sb		= $(obj).attr('sb');
			$(obj).removeData('selectbox').show();
			$('#sbHolder_' + sb).remove();
			this.setSelectBoxPlugin($(obj), '');
		}catch(e){};
	};

		// 중복옵션 체크
		// 현재 선택된 옵션 정보를 확인한 후 이미 입력되어 있는 옵션 전체와 비교하여 중복 옵션 입력을 방지한다.
		this.chkDuplicateRequired	   = function(obj){
			var result = true;
			var that		= this;
			
			// 중복 여부
			var duplicated = false;
			
			// 현재 선택 옵션 확인
			var objSelectedOption = [];
			var objSelectedInput = [];
			var objSelectedSuboption = [];
			
			$("select[name='" + this.options_selectbox_name + "']").each(function(){
				var idx = $(this).attr("id");
				var value = $(this).val();
				if(typeof(idx) !== 'undefined'){
					objSelectedOption[idx] = value;
				}
			});
			
			$("input[name='" + this.inputs_inputbox_name + "']").each(function(index){
				var idx = index;
				var value = $(this).val();
				if(typeof(idx) !== 'undefined'){
					objSelectedInput[idx] = value;
				}
			});
			$("select[name='" + this.suboptions_selectbox_name + "']").each(function(){
				var idx = $(this).attr("requiredgroup");
				var value = $(this).val();
				if(typeof(idx) !== 'undefined'){
					objSelectedSuboption[idx] = value;
				}
			});
			
			// 이미 입력되어 있는 옵션 확인
			var arrInsertedOption = [];
			var arrInsertedInput = [];
			var arrInsertedSuboption = [];
			
			for(var i=0;i<that.apply_option_seq;i++){
				arrInsertedOption[i] = [];
				$("input[name^='" + this.selected_option_name + "\["+i+"\]']").each(function(index){
					var idx = $(this).attr("opt_seq");
					var value = $(this).val();
					arrInsertedOption[i][idx] = value;
				});
			}
			for(var i=0;i<that.apply_option_seq;i++){
				arrInsertedInput[i] = [];
				$("input[name^='" + this.selected_inputoption_name + "\["+i+"\]']").each(function(index){
					var idx = $(this).attr("opt_seq");
					var value = $(this).val();
					arrInsertedInput[i][idx] = value;
				});
			}
			for(var i=0;i<that.apply_option_seq;i++){
				arrInsertedSuboption[i] = [];
				$("input[name^='" + this.selected_suboptionTitle_name + "\["+i+"\]']").each(function(index){
					var idx = $(this).attr("opt_seq");
					var value = $(this).val();
					arrInsertedSuboption[i][idx] = value;
				});
			}
			
			// 2019-07-23 #29702 기준으로 필수옵션과 입력옵션만 비교, 입력옵션은 있을 때만 비교
			if(arrInsertedOption.length > 0){
				for(var i=0;i<arrInsertedOption.length;i++){
					var compare_cnt = 0;
					var check_cnt = objSelectedOption.length + objSelectedInput.length;
					
					for(var obj_i=0;obj_i<objSelectedOption.length;obj_i++){
						if(
							typeof(arrInsertedOption[i][obj_i]) !== 'undefined'
							&& objSelectedOption[obj_i] == arrInsertedOption[i][obj_i]
						){	// 값이 있을 때만 비교
							compare_cnt++;
						}
					}
					for(var obj_i=0;obj_i<objSelectedInput.length;obj_i++){
						if(
							typeof(arrInsertedInput[i][obj_i]) !== 'undefined'
							&& objSelectedInput[obj_i] == arrInsertedInput[i][obj_i]
						){	// 값이 있을 때만 비교
							compare_cnt++;
						}
					}
					
					if(check_cnt == compare_cnt){
						duplicated = true;
					}
				}
			}
			// duplicated = true;
			if(duplicated){
				//옵션을 선택해 주세요2
				openDialogAlert(getAlert('gv035'), 400, 140,'');
				result	= false;
			}
			return result;
		};
};

// 기존 함수 대체용 ( 옵션 체크 )
if	(typeof chg_compare_currency == 'undefined' ){
	// 변경된 총 상품금액의 비교통화 변경
	function chg_compare_currency(tot){
		var gdata = "price="+uncomma(tot);
		$.ajax({
			type: "get",
			url: "/goods/get_compare_currency",
			data:gdata,
			success: function(result){
				$("#total_compare_currency").html(result);

				// 상세 레이어창 열기 :: 2016-08-17 lwh
				$(".detailDescriptionLayerBtn").unbind("click").bind("click",function(){
					currencyCompareList($(this));
				});

				//상세 레이어창 열기 :: 마우스 오버용 @2016-11-08
				$(".detailDescriptionLayerBtn.over").unbind("mouseover").bind("mouseover",function(){
					currencyCompareList($(this));
				});
				$(".detailDescriptionLayerBtn.over").unbind("mouseout").bind("mouseout",function(){
					$('div.detailDescriptionLayer').hide();
				});

				$(".currency_compare_lay .detailDescriptionLayerCloseBtn").bind("click",function(){
					$('div.detailDescriptionLayer').hide();
				});
			}
		});
	}
}



// 기존 함수 대체용 ( 옵션 체크 )
if	(typeof check_option == 'undefined' ){
	function check_option(obj){

		var groupArr		= new Array();
		var required_group	= new Array();
		var required_count	= 0;
		var tmp_idx			= 0;

		// 장바구니 버튼에 따른 영역 선택
		if	(obj){
			var id	= '';
			var seq	= $(obj).attr('seq');
			if		($(obj).attr('id').search(/ordercart/) != -1)	id	= $(obj).attr('id').replace('ordercart_', '');
			else if	($(obj).attr('id').search(/wishcart/) != -1)	id	= $(obj).attr('id').replace('wishcart_', '');
			else													id	= $(obj).attr('id').replace('goodscart_', '');
			if	(seq > 0 && id != seq)	id	= seq;

			var parent		= $('form#optional_changes_form_' + id).closest('div');
		}else if($("#floating_window").is(":visible")){
			var parent		= $('div#floating_window');
		}else{
			var parent		= $('div#select_option_lay');
		}

		// 필수옵션 기준 선택된 그룹들 추출
		$(parent).find('.selected_options').each(function(){
			groupArr[$(this).attr('opt_group')]	= 1;
		});

		if	(!$(parent).find('.selected_options').length){
			//선택된 옵션이 없습니다.
			openDialogAlert(getAlert('gv010'), 400, 140, '');
			return false;
		}

		//---------------> 필수옵션 체크
		// 필수옵션 추출
		tmp_idx			= 0;
		$(parent).find('tr.optionTr').each(function(){
			required_group[tmp_idx]	= $(this).find('.optionTitle').text();
			tmp_idx++;
		});
		required_count		= required_group.length;

		var chk_count	= 0;
		for	(var group_idx in groupArr) {
			if	(!isNaN(group_idx) && group_idx > 0){
				chk_count	= $(parent).find("input[opt_group='" + group_idx + "'].selected_options").length;
				if	(chk_count < required_count){
					//옵션을 선택해 주세요.
					openDialogAlert(getAlert('gv028'), 400, 140, '');
					return false;
				}
			}
		}
		//<--------------- 필수옵션 체크


		//---------------> 입력옵션 체크
		// 필수 입력옵션 추출
		tmp_idx			= 0;
		required_group	= new Array();
		$(parent).find('tr.inputoptionTr').each(function(){
			if	($(this).find("input[isrequired='y'],textarea[isrequired='y']").length > 0){
				required_group[tmp_idx]	= $(this).find('.inputsTitle').text();
				tmp_idx++;
			}
		});
		required_count		= required_group.length;

		// 그룹별 체크
		if	(required_count > 0){
			var tmp_title	= '';
			var chk_result	= true;
			for	(var group_idx in groupArr) {
				if	(!isNaN(group_idx)){
					chk_count	= 0;
					chk_result	= $(parent).find("input[opt_group='" + group_idx + "'].selected_inputs_title").each(function(){
						tmp_idx		= $(this).attr('opt_seq');
						tmp_title	= $(this).val();
						// 필수 입력옵션이 있는지 체크
						for	(var required_idx in required_group) {
							if	(required_group[required_idx] == tmp_title &&
								!$(parent).find("input[opt_group='" + group_idx + "'][opt_seq='" + tmp_idx + "'].selected_inputs").val()){
								//를 입력해 주세요.
								openDialogAlert(required_group[required_idx] + ''+getAlert('gv029'), 400, 140, '');
								return false;
							}
						}
					});

					if	(!chk_result){
						return false;
					}
				}
			}
		}else{
			// 선택영역의 입력옵션 형태일 경우
			var grpIdx		= 0;
			var optSeq		= 0;
			var that		= '';
			var inputTitle	= '';
			var inputResult	= true;
			$(parent).find("textarea.selected_inputs,input.selected_inputs").each(function(){
				grpidx		= $(this).attr('opt_group');
				optseq		= $(this).attr('opt_seq');
				inputTitle	= $(parent).find("input[opt_group='" + grpidx + "'][opt_seq='" + optseq + "'].selected_inputs_title").val();
				if	($(this).attr('isrequired') == 'y' && !$(this).val()){
					that	= this;
					//을(를) 입력해 주세요.
					openDialogAlert(inputTitle + getAlert('gv030'), 400, 140, function(){
						$(that).focus();
					});
					inputResult	= false;
					return false;
				}
				if	($(this).attr('inputlimit') > 0){
					if	($(this).val().length > $(this).attr('inputlimit')){
						$(this).val($(this).val().substring(0, $(this).attr('inputlimit')));
						that	= this;
						//inputTitle + '은(는) ' + $(this).attr('inputlimit') + '자 이하로 입력해 주세요.
						openDialogAlert(inputTitle + getAlert('gv031',$(this).attr('inputlimit')), 400, 140, function(){
							$(that).focus();
						});
						inputResult	= false;
						return false;
					}
				}
			});
			if	(!inputResult)	return false;
		}
		//<--------------- 입력옵션 체크


		//---------------> 추가옵션 체크
		// 필수 추가옵션 추출
		tmp_idx			= 0;
		required_group	= new Array();
		$(parent).find('tr.suboptionTr').each(function(){
			if	($(this).find("select[isrequired='y']").find('option').length > 0){
				if	(!required_group[tmp_idx]){
					required_group[tmp_idx]	= $(this).find('.suboptionTitle').text();
					tmp_idx++;
				}
			}
		});
		required_count		= required_group.length;

		// 그룹별 체크
		if	(required_count > 0){
			var tmp_title			= '';
			var chk_count			= 0;
			var tmp_required_group	= new Array();
			for	(var group_idx in groupArr) {
				if	(!isNaN(group_idx)){
					chk_count			= 0;
					for	(var required_idx in required_group) {
						if	(!isNaN(required_idx)){
							tmp_required_group[required_idx]	= required_group[required_idx];
						}
					}
					$(parent).find("input[opt_group='" + group_idx + "'].selected_suboptions_title").each(function(){
						tmp_title	= $(this).val();
						// 필수 추가옵션이 있는지 체크
						for	(var required_idx in tmp_required_group) {
							if	(!isNaN(required_idx)){
								if	(tmp_required_group[required_idx] == tmp_title){
									chk_count++;
									tmp_required_group[required_idx]	= '';
								}
							}
						}
					});

					if	(chk_count != required_count){
						//필수 추가옵션을 선택해 주세요.
						openDialogAlert(getAlert('gv032'), 400, 140, '');
						return false;
					}
				}
			}
		}
		//<--------------- 추가옵션 체크

		return true;
	}
}

// 기존 함수 대체용 ( 재입고 알림 )
if	(typeof set_option_ReStock == 'undefined' ){
	function set_option_ReStock(n,isMobile){
		var gdata = "no="+gl_goods_seq;
		var st = "viewOptionsReStock[]";

		temp = isMobile ? $('#restock').find($("select[name='"+st+"']")) : $("select[name='"+st+"']");

		$(temp).each(function(i){
			if(i < n) gdata += '&max='+gl_option_divide_title_count+'&options[]='+encodeURIComponent($(this).val());
		});

		$.ajax({
			type: "get",
			url: "../goods/option",
			data: gdata,
			success: function(result){
				var data = eval(result);
				temp = isMobile ? $('#restock').find($("select[name='"+st+"']")) : $("select[name='"+st+"']");
				$(temp).eq(n).bind("change",function(){
					$(temp).each(function(i){
						if(i>n){
							$(temp).eq(i).find("option").each(function(n){
								if(n!=0)$(this).remove();
							});
						}
					});
				});
				for(var i=0;i<data.length;i++){
					var obj = data[i];
					$(temp).eq(n).append("<option value=\""+obj.opt+"\">"+obj.opt+"</option>");
					/**
					var able = "";
                    var able_txt ="";
                    if(obj.stock <= 0){
                        able_txt = " (품절)";
                    }
                    if(n==gl_option_divide_title_count-1 && obj.stock>0){
                        able = "disabled";
                    }

                    $(temp).eq(n).append("<option value=\""+obj.opt+"\" stock=\""+obj.stock+"\" "+able+">"+obj.opt+able_txt+"</option>");
					**/
				}
			}
		});
	}
}

//////////////// js 호출 함수 ////////////////

// plugin으로 필요한 js들 호출
function load_plugin_js(){
	var scripts			= document.getElementsByTagName('script');
	var jslen			= scripts.length;
	var jsobj			= '';
	var	tmp				= new Array();
	var required_chk	= new Array();
	var required_js		= new Array();
	required_js[0]		= '/app/javascript/jquery/jquery.min.js';
//	required_js[1]		= '/app/javascript/plugin/jquploadify/swfobject.js';
//	required_js[2]		= '/app/javascript/plugin/jquploadify/jquery.uploadify.v2.1.4.js';
	required_js[1]		= '/app/javascript/plugin/jquery_selectbox/js/jquery.selectbox-0.2.js';
	var required_len	= required_js.length;
	var jssrc			= '';


	for	( var j = 0; j < jslen; j++){
		jsobj		= scripts[j];
		jssrc		= jsobj.src;
		if	(jssrc){
			if	(jssrc.search(/^http/i) != -1)	jssrc	= jssrc.replace(/http\:\/\/[^\/]*/i, '');
			tmp		= jssrc.split('?');
			if	(tmp[0]){
				for	( var r = 0; r < required_len; r++){
					if	(tmp[0] == required_js[r])	required_chk[r]	= true;
				}
			}
		}
	}

	for	( var r = 0; r < required_len; r++){
		if	(!required_chk[r])	load_script(required_js[r]);
	}
}

// 스크립트 동적 로드
function load_script(js_src){
	var dynamic_js		= document.createElement('script');
	dynamic_js.type		= 'text/javascript';
	dynamic_js.async	= false;
	dynamic_js.defer	= true;
	dynamic_js.src		= js_src;
	document.getElementsByTagName('head')[0].appendChild(dynamic_js);
}

// js sleep
function jssleep(milliseconds) {
    var start = new Date().getTime();
    for (var i = 0; i < 1e7; i++) {
        if ((new Date().getTime() - start) > milliseconds) {
            break;
        }
    }
}

// 동적로드가 ajax페이지에서는 불안정하여 우선 제거함
//window.onload	= load_plugin_js();
