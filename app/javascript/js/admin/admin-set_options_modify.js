$(function(){
    // 목록형태로 노출되지 않는 object만 여기서 event를 일괄로 잡는다.
    // 목록형태로 n개 노출되는 object는 해당 object에 각각 event를 잡아야 함.
    
    if(reload == 'y' ){
        var link = new Array();
        var idx = 0;
        $.each(scObj, function(key,val){
            if(val != null) {
                link[idx] = key+'='+val;
                idx++;
            }
        });
        location.replace('?'+link.join("&"));
    }
    /* 옵션 등록/수정 window 리사이징 */
    var _getWinSize = function(){
        var _resize 	        = false;
        var _winInnerSize 	    = window.innerWidth - 42;		    // 스크롤 box
        var _tableSize 		    = 1435;                          	// 옵션 리스트 테이블 기본 최소 사이즈
        var _tOptCell 	        = 200;
        var _scrolllbarWidth    = 16;
        if(typeof sellerAdmin != "undefined" && sellerAdmin == true){
            _tableSize = 1300;
        }else{
            if(scObj.socialcp_input_type){
                _tableSize 		    = 1525;
                _tOptCell 	        = 320;
            }else if(scObj.package_yn == 'y'){
                if(scObj.package_count == 1){
                    _tableSize          = 1065;
                } else{
                    _tableSize          = 1295;
                }
                _tOptCell 	        = 150;
            }
            if(scObj.provider_seq > 1) _tableSize += 50;            // 매입가보다 정산금액 width가 50 큼
        }
        
        var _optionCnt 	    = scObj.options_cnt;		            // 옵션 갯수
        var _tableWidth		= _tableSize;                           // 옵션 리스트 테이블 사이즈

        if(_optionCnt == 0) _optionCnt = 1;
        if(_optionCnt > 1) _tableWidth += _tOptCell * (_optionCnt - 1);

        if(scObj.package_yn == 'y'){
            _tableWidth += (scObj.package_count - 2) * 220;		//패키지 상품 갯수에 따라 width 추가
        }

        if(_winInnerSize != (_tableSize + _scrolllbarWidth)) { _winInnerSize = (_tableSize + _scrolllbarWidth); _resize = true; }

        if(scObj.package_yn == 'y'){
            var scrollLayHeight =  $(".content").height() - 440;
        }else{
            var scrollLayHeight = $(".content").height() - 397;
        }
        $("#table-grid table.optionList").attr("style", "min-width:"+_tableSize+"px;width:"+_tableWidth+"px");

        if(_resize == true){
            window.resizeTo(_winInnerSize+59,window.outerHeight);
        }
        $("#table-grid").height(scrollLayHeight);
    }
    _getWinSize();
    
    if(scObj.mode == "view" && scObj.options_cnt > 0){
        $("#optionLayer", parent.document).html($("#optionLayer").html());
        $("#preview_option_divide", parent.document).html($("#preview_option_divide").html());
        $("#preview_option_sum", parent.document).html($("#preview_option_sum").html());
        parent.chgSuboptionReservePolicy(scObj.tmp_policy);
    }

    help_tooltip();
    if(scObj.socialcp_input_type != ''){
        socialcpinputtype();
    }

    $(".closeOptInfo").on("click",function(){
        if($(this).attr("data-type") == "CLOSE"){
            $("table.optionInfo tr").not("tr:eq(0)").not(".hide").hide();
            $("#table-grid").height($(".content").height() - 265);
            $(this).removeClass("OPEN").addClass('CLOSE').attr("data-type","OPEN");
        }else{
            $("table.optionInfo tr").not("tr:eq(0)").not(".hide").show();
            $("#table-grid").height($(".content").height() - 387);
            $(this).removeClass("CLOSE").addClass('OPEN').attr("data-type","CLOSE");
        }
    });

    $("input[name='optionCreateType']").on("click",function(){
        if($(this).val() == "new"){
            $("tr.newOption").show();
            $("tr.oldOption").hide();
        }else{
            $("tr.newOption").hide();
            $("tr.oldOption").show();
        }
    });

    $("input[name='chkall']").on("click",function(){
        var checked = $(this).is(":checked");
        $(this).closest("table").find(".chk").each(function(){
            $(this).prop("checked",checked);
        });
    });
    // 옵션 생성
    $("#optionMake").on("click",function(){
        openDialog("필수 옵션 생성", "optionMakePopup", {"width":"1200","height":"580","show" : "fade","hide" : "fade"});
        $("input[name='optionMakeDepth']").val(scObj.options_cnt + 1);
        $("input[name='optionName']").val('');
        $("input[name='optionPrice']").val('');
    });
    
    // 옵션 관리
    $("#optionSetting").on("click",function(){
        openDialog("자주쓰는 상품의 옵션 관리", "optionSettingPopup", {"width":"500","height":"630","show" : "fade","hide" : "fade"});
    });

    // 특수옵션 개별 수정 시 저장
    $("#goodsoptiondirectmodifybtn").on("click",function(){
        var newtype		= $(this).attr("newtype");
        var opttblidx	= $(this).attr("opttblidx");
        goodsoptiondirectmodify(opttblidx, newtype);

        if	(newtype == 'color' || newtype == 'address' || newtype == 'date'){
            $("#gdoptdirectmodifylay input[name='newType']").val(newtype);
            $("#gdoptdirectmodifylay input[name='tmpSeq']").val(scObj.tmp_seq);
            loadingStart();
            $("#specialOption").submit();
        }else{
            closeDialog("gdoptdirectmodifylay");
        }
    });

    // 자주사용하는 옵션 가져오기
    $("#frequentlytypeoptbtn").on("click",function(){

        var add_goods_seq = $("select[name='frequentlytypeopt']").find("option:selected").val();
        if( add_goods_seq<=0 ){
            alert("옵션정보를 가져올 상품을 선택해 주세요!");
            return false;
        }
        var goods_name = $("select[name='frequentlytypeopt']").find("option:selected").text();
        openDialogConfirm('정말로 ['+goods_name+'] 상품의 <br/>필수옵션 정보를 가져오시겠습니까?',400,200,function(){
            var params = "";
            params = "&optionViewTypeTmp="+$("input[name='optionViewTypeTmp']:checked").val()+"&optionCreateType="+$("input[name='optionCreateType']:checked").val();
            opener.openSettingOptionnew(add_goods_seq,params);
        });
    });

    // 우편번호 검색
    $(".direct_zipcode_btn").on("click",function(){
        openDialogZipcode('direct_');
    });

    // 일괄저장 버튼
    $(".save_all").on("click", function(){

        var targetId		= $(this).attr('id');

        if	(targetId == 'reserve_rate_all' && $("select[name='reserve_policy']").val() == 'shop'){
            openDialogAlert("마일리지 일괄적용은 마일리지 지급정책이 개별정책 입력일 경우에만 가능합니다.", 550, 150 );
            return;
        }else{
            var commission_type = $('select[name="commission_type_all"]').val();
            if(typeof sellerAdmin != "undefined" && sellerAdmin == true){
                commission_type = $("input[name='default_commission_type']").val();
            }
            if(commission_type != 'SUPR' && targetId == 'commission_rate_all'){
                if($("input[name='"+targetId+"']").val() > 100){
                    $("input[name='"+targetId+"']").val('');
                    openDialogAlert("수수료율은 100%를 넘을 수 없습니다.", 400, 160 );
                    return false;
                }
            }
            
            var href	= '../goods_process/save_tmpoption_cell?tmpSeq='+scObj.tmp_seq+'&target='+targetId;

            if (targetId == 'option_view_all' ) {
                href	+= '&value='+$("select[name='"+targetId+"']").val();
            } else if (targetId != 'infomation_all') {
                href	+= '&value='+$("input[name='"+targetId+"']").val();
            } else {
                href	+= '&value='+$("textarea[name='"+targetId+"']").val();
                $("#applyAllOptInfomation").dialog("close");
            }

            if(targetId == 'commission_rate_all' && $(this).attr('commission_type') != 'SACO')
                href	+= "&commission_type="+$('select[name="commission_type_all"]').val();
            else if(targetId == 'commission_rate_all' && $(this).attr('commission_type') == 'SACO')
                href	+= "&commission_type=SACO"

            // 마일리지은 단위까지 넘어가야 한다.
            if	(targetId == 'reserve_rate_all'){
                if($("input[name='reserve_rate_all']").val() > 100 && $("select[name='reserve_unit_all']").val() == "percent"){
                    $("input[name='reserve_rate_all']").val('');
                    alert("마일리지 지급률은 100%를 넘을 수 없습니다.");
                    return false;
                }
                href	+= '&reserve_unit='+$("select[name='reserve_unit_all']").val();
            }

            // 할인가(판매가) 일괄변경시 마일리지설정 상태 전달
            if	( targetId == 'price_all' ){
                href	+= '&reserve_policy='+$("select[name='reserve_policy']").val();
            }

            if(targetId == 'infomation_all') {
                var infoText	= $.trim($("textarea[name='"+targetId+"']").val());
                if (!infoText)
                    $('.viewInfomationTextAll').html('미입력');
                else
                    $('.viewInfomationTextAll').html('<span class="underline">보기</span>');
            }

            optionFrame.location.href	= href;
        }
    });

    // 상품 상세 페이지에 적용
    $("#setTmpSeq").on("click", function(){
        let optionValueArray = [];
        if($("#table-grid table tbody tr").find("input[name='option1']").length != 0) optionValueArray.push('3');
        if($("#table-grid table tbody tr").find("input[name='option2']").length != 0) optionValueArray.push('5');
        if($("#table-grid table tbody tr").find("input[name='option3']").length != 0) optionValueArray.push('7');
        if($("#table-grid table tbody tr").find("input[name='option4']").length != 0) optionValueArray.push('9');
        if($("#table-grid table tbody tr").find("input[name='option5']").length != 0) optionValueArray.push('11');
        
        

        $("#table-grid table tbody tr.optionTr").each(function(idx){
            for(let i = 0; i < optionValueArray.length; i++){
                let findOptionValue = "td:eq("+optionValueArray[i]+") input[type='text']";
                if($(this).find(findOptionValue).val().trim() == ''){
                    alert('옵션값을 입력해 주세요.');
                    $(this).find(findOptionValue).focus();
                    return false;
                }
            }
        });
        $("form[name='tmp_option_form']").attr('action', '../goods_process/chk_tmpoption_require');
        $("form[name='tmp_option_form']").submit();
    });

    setDatepicker($(".datepicker"));

    // 옵션 한줄 추가
    $(".addOption").on("click",function(){
        var trobj	= $("input[name='default_option']:checked").closest('tr');
        var seq		= trobj.find("input[name='option_seq[]']").val();
        optionFrame.location.replace('../goods_process/save_option_one_row?saveType=add&tmpSeq='+scObj.tmp_seq+'&optionSeq='+seq);
    });

    $(".onlyfloat").on("keydown",function(e){
        if (e.keyCode!=190 && e.keyCode!=110) return onlynumber(e);
    }).on('focusin',function(){
        if($(this).val()=='0') $(this).val('');
    }).on('focusout',function(){
        if($(this).val()=='') $(this).val('0');
    });

    $("input[name='commission_rate_all'], input[name='commission_rate']").live("change",function(){
        var float_cnt	= this.value.match(/\.[0-9]+/g);
        if(float_cnt > 0 && float_cnt.toString().length > 3){
            alert('소숫점 2자리까지 가능합니다.(2자리 초과 절삭)');
        }
        var charge		= Math.floor((this.value * 100).toFixed(2)) / 100;
        this.value		= charge;
    });

    check_option_international_shipping(); // 해외 배송여부에 따른 레이어 폰트색상

    /* 상품코드 코드생성넣기*/
    $("#goodsCodeBtn").on("click", function(){openDialog("기본 코드 자동 생성", "makeGoodsCodLay", {"width":"400", "height":"370"});});
    $('#goodsCodeOpt').val($('#goodsCode').val());

    if(scObj.mode == "view"){
        var optionStockSetText	= setOptionStockSetText();
    }else{
        var optionStockSetText	= opener.setOptionStockSetText();
    }
    $('.optionStockSetText').html(optionStockSetText);
    
    $('input[name="default_option"]').on('change', function(){
        $('select[name="option_view"]').show();
        $('.option_view_only').hide();

        if (this.value == 'y') {
            var $option_view	= $(this).closest("tr").find('select[name="option_view"]');
            $option_view.val('Y').hide();
            $(this).closest("tr").find('.option_view_only').show();
            ready_input_save($option_view);
        }
        
    });
    $("input[name='optionViewTypeTmp']").on("click",function(){
		$("input[name='optionViewType']").val($(this).val());
		var tmp_seq = $("input[name='tmp_seq']").val();
		$.cookie('optionViewType_'+tmp_seq, $(this).val());
    });

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
	
	// 상품등록화면에 있는 옵션코드 유지하도록 (상품UI/UX개선이전에도 있었음)
	$('#goodsCodeOpt').val($('#goodsCode', opener.document).val());
});

    function set_option_to_opener(){
        set_option_internation_shipping();
        var tmp_frequently = ($("input[name='frequently']").is(":checked"))?$("input[name='frequently']:checked").val():0;

        var _options = {};
        _options.optionViewType = $("input[name='optionViewTypeTmp']:checked").val();
        if(scObj.package_yn == 'y'){
            save_package_tmp(tmp_frequently, _options.optionViewType );
        }else{
            // 기본코드 자동생성 값
            goodsCode	= $('#goodsCodeOpt').val();
            if(typeof goodsCode == "undefined") goodsCode = '';
            opener.setOptionTmp(scObj.tmp_seq,tmp_frequently, goodsCode,_options);
            self.close();
        }
    }

    /****************** 리스트 수정 및 변경 처리 ******************************/

    var saverObj	= new DomSaver('hideFormLay', 'post', '../goods_process/save_tmpoption_piece', 'optionFrame');
    // 각 input을 클릭 시 저장 대기로 돌린다.
    function ready_input_save(obj){
        //공급가 방식의 경우 단위까지 함께 적용
        saverObj.setTarget(obj);
        if	($(obj).val() == $(obj).attr('title') || $(obj).val() == 0){
            $(obj).val('');
        }
    }

    // 키 이벤트 처리
    function key_input_value(evt, obj){
        var e			= evt || window.event;

        if	(e.keyCode == 13){
            var name	= $(obj).attr('name');
            var idx		= $("input[name='"+name+"']").index(obj) + 1;
            if	($("input[name='"+name+"']").eq(idx).attr('name')){
                $("input[name='"+name+"']").eq(idx).focus();
                ready_input_save(obj);
            }
        }
    }


    // 각 input의 폼값 저장
    function save_input_value(obj){

        if	($(obj).attr('name') == 'default_option')	saverObj.setTarget(obj);

        var optionSeq				= $(obj).closest('tr').find("input[name='option_seq[]']").val();
        var param					= new Array();
        param['tmpSeq']				= scObj.tmp_seq;
        param['optionSeq']			= optionSeq;
        param[$(obj).attr('name')]	= $(obj).val();

        if	($(obj).attr('name') == 'default_option') {
            $(obj).closest('table').find("input[name='optDel[]'").prop("disabled",false);
            $(obj).closest('tr').find("input[name='optDel[]'").prop("disabled",true);
        }

        if	($(obj).attr('name') == 'default_option' && $(obj).val() == 'y') {
            param['option_view'] = $(obj).parents('tr').find('[name="option_view"]');
        }

        // 입력 제한 체크
        if	($(obj).attr('name') == 'coupon_input' && (!$(obj).val() || $(obj).val() < 1 || $(obj).val().search(/[^0-9]/) != -1)){
            openDialogAlert('쿠폰 1장의 값어치는 반드시 숫자 1이상이어야 합니다.', 400, 150, function(){
                $(obj).val('').css('border', '2px solid red').focus();
            });
            return false;
        }else{
            //$(obj).css('border', '1px solid #cccccc');
        }

        // 변경 시 계산이 필요한 컬럼들 계산처리
        if($.inArray($(obj).attr('name'),['price','commission_rate','commission_type','consumer_price','reserve_rate','reserve_unit']) != -1){
            var reserve = tmpOptCalculate(obj);

            if(reserve === false)	return;
            param['reserve']	= reserve;
        }

        saverObj.sendValue(param);
        if (!$(obj).val()){
            if ($(obj).attr('name').includes('optioncode')) $(obj).val($(obj).attr('title'));
            else if ($(obj).attr('name').includes('option')) $(obj).val('');
            else $(obj).val('0');
        }
    }

    // 정산율 및 할인금액 변경 시 계산 처리
    function tmpOptCalculate(obj){

        var calulateReserve	= false;
        var commission_cal	= 'NONE';
        var commission_type	= $(obj).closest("tr").find("select[name='commission_type']").val();
        var provider_seq	= scObj.provider_seq;

        if(typeof sellerAdmin != "undefined" && sellerAdmin == true){
            commission_type = $("input[name='default_commission_type']").val();
        }

        commission_type	= (commission_type) ? commission_type : 'SACO';

        switch($(obj).attr('name')){
            case	'commission_type':	//수수료 변경시
            case	'commission_rate':	//수수료 변경시
                commission_cal	= commission_type;
                break;
            case	'consumer_price':	//정가 변경시
                commission_cal	= (commission_type != 'SACO') ? commission_type : 'NONE';
                break;
            case	'price' :			//판매가 변경시
                commission_cal	= (commission_type == 'SACO') ? commission_type : 'NONE';
            case	'reserve_rate' :	//적립율 변경시
            case	'reserve_unit' :	//적립율 단위 변경시
                calulateReserve	= true;
                break

        }

        if(commission_cal != 'NONE'){
            var sale_price			= $(obj).closest("tr").find("input[name='price']").val();
            var consumer_price		= $(obj).closest("tr").find("input[name='consumer_price']").val();
            var commission_rate		= $(obj).closest("tr").find("input[name='commission_rate']").val();

            switch(commission_type){
                case	'SACO' :
                    var commission_price	= sale_price - get_currency_price(sale_price * (commission_rate / 100));
                    break;
                case	'SUCO' :
                    var commission_price	= consumer_price - get_currency_price(consumer_price * ((100-commission_rate) / 100));
                    break;
                case	'SUPR' :
                    var commission_price	= commission_rate;
                    break;

            }

            if(commission_type != 'SUPR' && commission_rate > 100){
                var org_commission_rate		= $(obj).closest("tr").find("input[name='org_commission_rate']").val();
                $(obj).closest("tr").find("input[name='commission_rate']").val(org_commission_rate);

                if(commission_type == 'SUCO'){
                    var org_commission_type		= $(obj).closest("tr").find("input[name='org_commission_type']").val();
                    $(obj).closest("tr").find("select[name='commission_type']").val(org_commission_type);
                }

                openDialogAlert("수수료율은 100%를 넘을 수 없습니다.", 400, 160 );
                return false;
            }

            $(obj).closest("tr").find("input[name='org_commission_rate']").val(commission_rate);
            $(obj).closest("tr").find("input[name='org_commission_type']").val(commission_type);

            commission_price	= (provider_seq > 1) ? commission_price : 0;

            $(obj).closest("tr").find('.oCommissionPrice').html(commission_price);
        }


        // 정산율 변경 시 ==> 정산금액 계산
        if			($(obj).attr('name') == 'commission_rate'){
            // 정산금액 계산
            var price				= $(obj).closest("tr").find("input[name='price']").val();
            var commission_price	= num(price) - Math.floor( num(price) * (num_float($(obj).val()) / 100));

            //$(obj).closest("tr").find('.oCommissionPrice').html(comma(commission_price));

        // 할인금액 변경 시 ==> 정산금액 및 마일리지 계산
        }else if	($(obj).attr('name') == 'price'){
            // 정산금액 계산
            var commission_rate		= $(obj).closest("tr").find("input[name='commission_rate']").val();
            var commission_price	= $(obj).val() - Math.floor($(obj).val() * (commission_rate / 100));
            //$(obj).closest("tr").find('.oCommissionPrice').html(comma(commission_price));

            calulateReserve	= true;

        // 적립율 또는 적립율 단위 변경 시 ==> 마일리지 계산
        }else if	($(obj).attr('name') == 'reserve_rate' || $(obj).attr('name') == 'reserve_unit'){
            calulateReserve	= true;
        }

        // 마일리지 계산
        if	(calulateReserve){
            var reserve				= '';
            var price				= $(obj).closest("tr").find("input[name='price']").val();
            // 통합정책
            if	($("select[name='reserve_policy']").val() == 'shop'){
                var reserve_rate	= defaultReservePercent;
                var reserve_unit	= 'percent';

            // 개별정책
            }else{
                var reserve_rate	= $(obj).closest("tr").find("input[name='reserve_rate']").val();
                var reserve_unit	= $(obj).closest("tr").find("select[name='reserve_unit']").val();
            }

            if	(reserve_unit == 'percent')	reserve		= get_currency_price(price * (reserve_rate / 100),2);
            else reserve		= get_currency_price(reserve_rate,2);

            $(obj).closest("tr").find(".reserve-shop").html(reserve);
            $(obj).closest("tr").find(".reserve").html(reserve);    // %일때만 통화기준 금액 보여주기

            return reserve;
        }
    }

    // 전체 마일리지 일괄 계산
    function tmpReserveCalculate(){
        var reserve				= '';
        var reserve_rate		= '';
        var reserve_unit		= '';
        // 통합정책
        if	($("select[name='reserve_policy']").val() == 'shop'){
            reserve_rate		= defaultReservePercent;
            reserve_unit		= 'percent';
        }

        $("input[name='price']").each(function(){
            // 통합정책
            if	($("select[name='reserve_policy']").val() == 'goods'){
                reserve_rate	= $(this).closest("tr").find("input[name='reserve_rate']").val();
                reserve_unit	= $(this).closest("tr").find("select[name='reserve_unit']").val();
            }

            if	(reserve_unit == 'percent')	reserve		= get_currency_price($(this).val() * (reserve_rate / 100),2);
            else reserve		= get_currency_price(reserve_rate,2);

            $(this).closest("tr").find(".reserve-shop").html(reserve);
            $(this).closest("tr").find(".reserve").html(reserve);
        });
    }

    // selectbox disabled
    function setDisableSelectbox(obj, disable){
        if	(disable){
            var orgVal	= $(obj).val();
            $(obj).css('background-color', '#f0f0f0');
            $(obj).find('option').css('background-color', '#f0f0f0');
            $(obj).bind("change", function(){
                $(obj).find("option[value='"+orgVal+"']").attr('selected', true);
            });
            $("select[name='reserve_unit_all']").attr('disabled', true);
        }else{
            $(obj).css('background-color', '#fff');
            $(obj).find('option').css('background-color', '#fff');
            $(obj).unbind("change");
            $("select[name='reserve_unit_all']").attr('disabled', false);
        }
    }

    // 마일리지 정책 변경
    function chgReservePolicy(obj){
        if	($(obj).val() == 'shop'){
            $("input[name='reserve_rate_all']").attr('disabled', true);
            setDisableSelectbox($("select[name='reserve_unit_all']"), true);
        }else{
            $("input[name='reserve_rate_all']").attr('disabled', false);
            setDisableSelectbox($("select[name='reserve_unit_all']"), false);
        }
        optionFrame.location.href	= '../goods_process/save_tmpoption_cell?tmpSeq='+scObj.tmp_seq+'&target=tmp_policy_all&value='+$(obj).val();
    }

    // 일괄 변경 시 계산 처리
    function tmpSaveAll(target, value, commission_type){

        if	(target == 'tmp_policy'){
            if	(value == 'goods'){
                $(".reserve-shop-lay").hide();
                $(".reserve-goods-lay").show();
            }else{
                $(".reserve-goods-lay").hide();
                $(".reserve-shop-lay").show();
            }

            tmpReserveCalculate();
        }else{

            if(target == 'commission_rate' && commission_type != 'SACO'){
                $("select[name='commission_type']").val(commission_type);
            }

            $("input[name='"+target+"'],select[name='"+target+"']").each(function(){
                
                if	(target == 'reserve_rate' || target == 'option_view'){
                    
                    if (target == 'reserve_rate') {
                        if(value >= 0){
                            $(this).val(value);
                        }
                        $("select[name='reserve_unit']").val($("select[name='reserve_unit_all']").val());
                    } else {
                        $("select[name='" + target + "']:visible").val($("select[name='" + target + "_all']").val());
                    }
                    
                } else {

                    $(this).val(value);

                }
                tmpOptCalculate($(this));
            });
        }
    }

    // 옵션 한줄 추가 ( script )
    function add_option_row(optionSeq){
        var trobj	= $("input[name='default_option']:checked").closest('tr');
        var clone	= trobj.clone();
        clone.find("input[name='optDel[]']").prop("disabled",false).val(optionSeq);
        //clone.find("input[type='text']").val('');
        clone.find("input[name='default_option']").prop('checked', false);
        clone.find("input[name='option_seq[]']").val(optionSeq);
        clone.find(".option_view_only").addClass('hide');
        clone.find(".option_view").removeClass('hide');
        clone.find(".reserve-shop").html(get_currency_price('0',2));
        if( scObj.scm_use == 'Y'){
            clone.find("td.scm-td-stock").html("");
            clone.find("td.scm-td-stock").html("<span>0</span><input type='hidden' name='stock' value='0' />");
            clone.find("td.scm-td-stock").removeClass("hand");
            clone.find("td.scm-td-stock").removeAttr("onClick");
            clone.find("td.scm-td-badstock").html("");
            clone.find("td.scm-td-badstock").html("<span>0</span><input type='hidden' name='badstock' value='0' />");
            clone.find("td.scm-td-supplyprice").html("");
            clone.find("td.scm-td-supplyprice").html("<span>0</span><input type='hidden' name='supply_price' value='0' />");
            clone.find("input[name='safe_stock']").val(0);
        }
        clone.find("div.package_error script").remove();
        trobj.closest('tbody').append(clone);
        
		helpicon_style();
    }

    // 옵션 선택 제거
    function removeOption(){

        if($(".optionList input[name='optDel[]']:checked").length == 0){
            alert("삭제할 옵션을 먼저 선택해 주세요.");
            return false;
        }
        var seqs = [];
        var idx  = 0;
        //var seq		= $(obj).closest('td').find("input[name='option_seq[]']").val();
        $(".optionList input[name='optDel[]']").each(function(e){

            var obj         = this;
            if($(obj).is(":checked") && (typeof $(obj).attr("disabled") == 'undefined' || $(obj).attr("disabled") != 'disabled')){
                var seq         = $(obj).val();
                var isChecked 	= false;
                var tr 			= $(obj).closest('tr.optionTr');
                var defaultObj 	= tr.find("input[name='default_option']");
                if( defaultObj.length === 1 ) {
                    isChecked = defaultObj.is(':checked');
                }

                if(isChecked === true) {
                    //alert("필수 옵션은 삭제할 수 없습니다.");
                    //defaultObj.focus();
                //return false;
                }else{
                    seqs[idx]		= seq;
                    idx++;
                }
            }
            
        });

        if(seqs.length > 1){
            seqsList = seqs.join("|");
        }else{
            seqsList = seqs.join("");
        }
    
        optionFrame.location.replace('../goods_process/save_option_one_row?saveType=del&tmpSeq='+scObj.tmp_seq+'&optionSeq='+seqsList);
    }

    // 옵션 한줄 제거 ( script )
    function del_option_row(optionSeq){
        if	($("input[name='option_seq[]'][value='"+optionSeq+"']").closest('tr').find("input[name='default_option']").is(':checked')){
            $("input[name='default_option']").eq(0).attr('checked', true);
        }
        $("input[name='option_seq[]'][value='"+optionSeq+"']").closest('tr').remove();
    }
    /****************** 리스트 수정 및 변경 처리 ******************************/



    /****************** 특수옵션 개별 수정 팝업 ******************************/


    //직접입력 > 색상
    function chgColorOption(obj){
        var optSeq	= $(obj).closest('tr').find("input[name='option_seq[]']").val();
        if( $(obj).attr("opttype") && optSeq){
            $("#gdoptdirectmodifylay input[name='same_spc_save_type'][value='y']").attr('checked', true);
            $("#gdoptdirectmodifylay input[type='text']").val('');
            var opttblobj = $(obj).parents("tr");
            var opttblidx = opttblobj.index() - 1;
            $("#gdoptdirectmodifylay input[name='optionSeq']").val(optSeq);
            $("#gdoptdirectmodifylay input[name='optionNo']").val($(obj).attr('optno'));
            $("#goodsoptiondirectmodifybtn").attr("opttblidx",opttblidx);
            $("#goodsoptiondirectmodifybtn").attr("newtype","color");
            $("#gdoptdirectmodifylay .goodsoptiondirectlay").hide();
            $("#gdoptdirectmodifylay .colordateaddresslay").show();
            $("#gdoptdirectmodifylay .colorlay").show();
            $("#gdoptdirectmodifylay .datelay").hide();
            $("#gdoptdirectmodifylay .addresslay").hide();
            $($("#gdoptdirectmodifylay input[name='direct_color']")).customColorPicker("destroy");
            $("#gdoptdirectmodifylay input[name='direct_color']").val(opttblobj.find("input[name='optcolor[]']").val());
            $($("#gdoptdirectmodifylay input[name='direct_color']")).customColorPicker();
            openDialog("색상 변경", "gdoptdirectmodifylay", {"width":"450","height":"400","show" : "fade","hide" : "fade"});
        }
    }

    //직접입력 > 지역
    function chgAddressOption(obj){
        var optSeq	= $(obj).closest('tr').find("input[name='option_seq[]']").val();
        if( $(obj).attr("opttype") && optSeq){
            $("#gdoptdirectmodifylay input[name='same_spc_save_type'][value='y']").attr('checked', true);
            $("#gdoptdirectmodifylay input[type='text']").val('');
            var opttblobj = $(obj).parents("tr");
            var opttblidx = opttblobj.index() - 1;
            $("#gdoptdirectmodifylay input[name='optionSeq']").val(optSeq);
            $("#gdoptdirectmodifylay input[name='optionNo']").val($(obj).attr('optno'));
            $("#goodsoptiondirectmodifybtn").attr("opttblidx",opttblidx);
            $("#goodsoptiondirectmodifybtn").attr("newtype","address");
            $("#gdoptdirectmodifylay .goodsoptiondirectlay").hide();
            $("#gdoptdirectmodifylay .colordateaddresslay").show();
            $("#gdoptdirectmodifylay .addresslay").show();
            $("#gdoptdirectmodifylay .colorlay").hide();
            $("#gdoptdirectmodifylay .datelay").hide();
            var zipcode = new Array();
            zipcode = opttblobj.find("input[name='optzipcode[]']").val();
            $("#gdoptdirectmodifylay input.direct_zipcode1").val(zipcode);
            $("#gdoptdirectmodifylay input[name='direct_address_type']").val(opttblobj.find("input[name='optaddress_type[]']").val());
            $("#gdoptdirectmodifylay input[name='direct_address']").val(opttblobj.find("input[name='optaddress[]']").val());
            $("#gdoptdirectmodifylay input[name='direct_address_street']").val(opttblobj.find("input[name='optaddress_street[]']").val());
            $("#gdoptdirectmodifylay input[name='direct_addressdetail']").val(opttblobj.find("input[name='optaddressdetail[]']").val());
            $("#gdoptdirectmodifylay input[name='direct_biztel']").val(opttblobj.find("input[name='optbiztel[]']").val());
            $("#gdoptdirectmodifylay input[name='direct_address_commission']").val(opttblobj.find("input[name='optaddress_commission[]']").val());
            openDialog("지역 상세", "gdoptdirectmodifylay", {"width":"500","height":"510","show" : "fade","hide" : "fade"});
            setDefaultText();
        }
    }

    //직접입력 > 날짜
    function chgDateOption(obj){
        var optSeq	= $(obj).closest('tr').find("input[name='option_seq[]']").val();
        if( $(obj).attr("opttype") && optSeq){
            $("#gdoptdirectmodifylay input[name='same_spc_save_type'][value='y']").attr('checked', true);
            $("#gdoptdirectmodifylay input[type='text']").val('');
            var opttblobj = $(obj).parents("tr");
            var opttblidx = opttblobj.index() - 1;
            $("#gdoptdirectmodifylay input[name='optionSeq']").val(optSeq);
            $("#gdoptdirectmodifylay input[name='optionNo']").val($(obj).attr('optno'));
            $("#goodsoptiondirectmodifybtn").attr("opttblidx",opttblidx);
            $("#goodsoptiondirectmodifybtn").attr("newtype","date");
            $("#gdoptdirectmodifylay .goodsoptiondirectlay").hide();
            $("#gdoptdirectmodifylay .colordateaddresslay").show();
            $("#gdoptdirectmodifylay .datelay").show();
            $("#gdoptdirectmodifylay .colorlay").hide();
            $("#gdoptdirectmodifylay .addresslay").hide();
            $("#gdoptdirectmodifylay input[name='direct_codedate']").val(opttblobj.find("input[name='codedate[]']").val());

            openDialog("날짜 상세", "gdoptdirectmodifylay", {"width":"450","height":"320","show" : "fade","hide" : "fade"});
        }
    }

    //직접입력 > 수동기간
    function chgInputDateOption(obj){
        var optSeq	= $(obj).closest('tr').find("input[name='option_seq[]']").val();
        if( $(obj).attr("opttype") && optSeq){
            var opttblobj = $(obj).parents("tr");
            var opttblidx = opttblobj.index() - 1;
            $("#gdoptdirectmodifylay input[name='optionSeq']").val(optSeq);
            $("#gdoptdirectmodifylay input[name='optionNo']").val($(obj).attr('optno'));
            $("#goodsoptiondirectmodifybtn").attr("opttblidx",opttblidx);
            $("#goodsoptiondirectmodifybtn").attr("newtype","dayinput");
            $("#gdoptdirectmodifylay .goodsoptiondirectlay").hide();
            $("#gdoptdirectmodifylay .colordateaddresslay").hide();
            $("#gdoptdirectmodifylay div.dayinputlay").show();
            openDialog("수동기간 변경", "gdoptdirectmodifylay", {"width":"350","height":"180","show" : "fade","hide" : "fade"});
        }
    }

    //직접입력 > 자동기간
    function chgAutoDateOption(obj){
        var optSeq	= $(obj).closest('tr').find("input[name='option_seq[]']").val();
        if( $(obj).attr("opttype") && optSeq){
            var opttblobj = $(obj).parents("tr");
            var opttblidx = opttblobj.index() - 1;
            $("#gdoptdirectmodifylay input[name='optionSeq']").val(optSeq);
            $("#gdoptdirectmodifylay input[name='optionNo']").val($(obj).attr('optno'));
            $("#goodsoptiondirectmodifybtn").attr("opttblidx",opttblidx);
            $("#goodsoptiondirectmodifybtn").attr("newtype","dayauto");
            $("#gdoptdirectmodifylay .goodsoptiondirectlay").hide();
            $("#gdoptdirectmodifylay .colordateaddresslay").hide();
            $("#gdoptdirectmodifylay div.dayautolay").show();
            openDialog("자동기간 변경", "gdoptdirectmodifylay", {"width":"350","height":"180","show" : "fade","hide" : "fade"});
        }
    }

    //직접입력 > 개별수정레이어창에서 수정하기
    function goodsoptiondirectmodify(opttblidx, newtype) {
        var opttblobj = $("div#optionLayer tr.optionTr").eq(opttblidx);
        switch(newtype){
            case "color":
                var optcolor = $("#gdoptdirectmodifylay input[name='direct_color']").val();
                if	($("#gdoptdirectmodifylay input[name='same_spc_save_type']:checked").val() == 'y'){
                    var optName = $(opttblobj.find(".colorhelpicon")).closest("td").find("input.optionval").attr("name");
                    var optValue = $(opttblobj.find(".colorhelpicon")).closest("td").find("input.optionval").val();
                    $("div#optionLayer tr.optionTr").find("input[name='"+optName+"'][value='"+optValue+"']").each(function(i){
                        var otherOpttblobj	= $(this).closest("tr.optionTr");
                        otherOpttblobj.find("input[name='optcolor[]']").val(optcolor);
                        otherOpttblobj.find(".colorhelpicon").css("background-color",optcolor);
                        otherOpttblobj.find(".colorhelpicon").attr("title", "[색상]을 클릭하여 변경할 수 있습니다.");
                        $(otherOpttblobj.find(".colorhelpicon")).customColorPicker(optcolor);//colorpickerlay();
                    });
                }else{
                    opttblobj.find("input[name='optcolor[]']").val(optcolor);
                    opttblobj.find(".colorhelpicon").css("background-color",optcolor);
                    opttblobj.find(".colorhelpicon").attr("title", "[색상]을 클릭하여 변경할 수 있습니다.");
                    $(opttblobj.find(".colorhelpicon")).customColorPicker(optcolor);//colorpickerlay();
                }
            break;
            case "address":
                var direct_zipcode1 	= $("#gdoptdirectmodifylay input.direct_zipcode1").val();
                var direct_zipcode2 	= $("#gdoptdirectmodifylay input.direct_zipcode2").val();
                var optaddress_type 	= $("#gdoptdirectmodifylay input[name='direct_address_type']").val();
                var optaddress 			= $("#gdoptdirectmodifylay input[name='direct_address']").val();
                var optaddress_street 	= $("#gdoptdirectmodifylay input[name='direct_address_street']").val().replace(",","&");
                var optaddressdetail 	= $("#gdoptdirectmodifylay input[name='direct_addressdetail']").val();
                var optbiztel 			= $("#gdoptdirectmodifylay input[name='direct_biztel']").val();
                var optaddress_commission = $("#gdoptdirectmodifylay input[name='direct_address_commission']").val();
                var addresstitle 		= "["+direct_zipcode1+"] <br> (지번) "+optaddress + optaddressdetail + " <br>(도로명) "+optaddress_street + optaddressdetail + " <br>  연락처:" + optbiztel + "<br/>[지역]을 클릭하여 변경할 수 있습니다.";
                addresstitle 			+= "<br/>수수료 : "+optaddress_commission+"%";
                if	($("#gdoptdirectmodifylay input[name='same_spc_save_type']:checked").val() == 'y'){
                    var optName = $(opttblobj.find(".addrhelpicon")).closest("td").find("input.optionval").attr("name");
                    var optValue = $(opttblobj.find(".addrhelpicon")).closest("td").find("input.optionval").val();
                    $("div#optionLayer tr.optionTr").find("input[name='"+optName+"'][value='"+optValue+"']").each(function(i){
                        var otherOpttblobj	= $(this).closest("tr.optionTr");
                        otherOpttblobj.find("input[name='optbiztel[]']").val(optbiztel);
                        otherOpttblobj.find("input[name='optaddressdetail[]']").val(optaddressdetail);
                        otherOpttblobj.find("input[name='optaddress_type[]']").val(optaddress_type);
                        otherOpttblobj.find("input[name='optaddress[]']").val(optaddress);
                        otherOpttblobj.find("input[name='optaddress_street[]']").val(optaddress_street);
                        otherOpttblobj.find("input[name='optzipcode[]']").val(direct_zipcode1+"-"+direct_zipcode2);
                        otherOpttblobj.find("input[name='optaddress_commission[]']").val(optaddress_commission);
                        otherOpttblobj.find(".addrhelpicon").attr("title",addresstitle);
                    });
                }else{
                    opttblobj.find("input[name='optbiztel[]']").val(optbiztel);
                    opttblobj.find("input[name='optaddressdetail[]']").val(optaddressdetail);
                    opttblobj.find("input[name='optaddress_type[]']").val(optaddress_type);
                    opttblobj.find("input[name='optaddress[]']").val(optaddress);
                    opttblobj.find("input[name='optaddress_street[]']").val(optaddress_street);
                    opttblobj.find("input[name='optzipcode[]']").val(direct_zipcode1+"-"+direct_zipcode2);
                    opttblobj.find("input[name='optaddress_commission[]']").val(optaddress_commission);
                    opttblobj.find(".addrhelpicon").attr("title",addresstitle);
                }
            break;
            case "date":
                var codedate = $("#gdoptdirectmodifylay input[name='direct_codedate']").val();
                if	($("#gdoptdirectmodifylay input[name='same_spc_save_type']:checked").val() == 'y'){
                    var optName = $(opttblobj.find(".codedatehelpicon")).closest("td").find("input.optionval").attr("name");
                    var optValue = $(opttblobj.find(".codedatehelpicon")).closest("td").find("input.optionval").val();
                    $("div#optionLayer tr.optionTr").find("input[name='"+optName+"'][value='"+optValue+"']").each(function(i){
                        var otherOpttblobj	= $(this).closest("tr.optionTr");
                        otherOpttblobj.find("input[name='codedate[]']").val(codedate);
                        otherOpttblobj.find(".codedatehelpicon").attr("title",codedate + "<br/>[날짜]를 클릭하여 변경할 수 있습니다.");
                    });
                }else{
                    opttblobj.find("input[name='codedate[]']").val(codedate);
                    opttblobj.find(".codedatehelpicon").attr("title",codedate + "<br/>[날짜]를 클릭하여 변경할 수 있습니다.");
                }
            break;
        }
        help_tooltip();
        closeDialog("gdoptdirectmodifylay");
    }

    function check_able_option(iobj){
        var obj = $(iobj);
        if( setTmpSeq == 0 ){
            obj.attr("checked",false);
            alert("적용할 옵션을 생성해 주세요.");
            return false;
        }

        return true;
    }

    /****************** 특수옵션 개별 수정 팝업 ******************************/

    /* 해외 배송여부에 따른 레이어 폰트색상*/
    function check_option_international_shipping()
    {
        var input_checked = $("input[name='option_international_shipping_status_view']").attr("checked");

        if ( input_checked ){
            $("div#option_international_shipping_layer").css("color","#000000");
        }else{
            $("div#option_international_shipping_layer").css("color","#6d6d6d");
        }
    }

    function dialog_international_shipping()
    {
        openDialog("해외 배송 안내", "dialog_international_shipping", {"width":570,"height":340});
    }

    function set_option_internation_shipping(){
        var chk = $("input[name='option_international_shipping_status_view']").attr("checked");
        if(chk){
            opener.set_option_international_shipping_popup('y');
        }else{
            opener.set_option_international_shipping_popup('n');
        }
    }

    function save_package_tmp(tmp_frequently, optionViewType){
        var fobj = $("form#save_package_tmp");
        fobj.html("<input type='hidden' name='save_tmp_package_count[]' value='"+$("input[name='reg_package_count']").val()+"'>");
        fobj.append("<input type='hidden' name='tmp_no' value='"+scObj.tmp_seq+"'>");
        fobj.append("<input type='hidden' name='tmp_frequently' value='"+tmp_frequently+"'>");
        fobj.append("<input type='hidden' name='optionViewType' value='"+optionViewType+"'>");
        $("input[name='reg_package_option_seq1[]'").each(function(){

            fobj.append("<input type='hidden' name='save_tmp_package_option_seq[]' value='"+$(this).closest('tr').find("input[name='option_seq[]']").val()+"'>");

            fobj.append("<input type='hidden' name='save_tmp_package_option_seq1[]' value='"+$(this).val()+"'>");
        });
        $("input[name='reg_package_option_seq2[]'").each(function(){
            fobj.append("<input type='hidden' name='save_tmp_package_option_seq2[]' value='"+$(this).val()+"'>");
        });
        $("input[name='reg_package_option_seq3[]'").each(function(){
            fobj.append("<input type='hidden' name='save_tmp_package_option_seq3[]' value='"+$(this).val()+"'>");
        });
        $("input[name='reg_package_option_seq4[]'").each(function(){
            fobj.append("<input type='hidden' name='save_tmp_package_option_seq4[]' value='"+$(this).val()+"'>");
        });
        $("input[name='reg_package_option_seq5[]'").each(function(){
            fobj.append("<input type='hidden' name='save_tmp_package_option_seq5[]' value='"+$(this).val()+"'>");
        });

        $("input[name='package_unit_ea1[]'").each(function(){
            fobj.append("<input type='hidden' name='save_tmp_package_unit_ea1[]' value='"+$(this).val()+"'>");
        });
        $("input[name='package_unit_ea2[]'").each(function(){
            fobj.append("<input type='hidden' name='save_tmp_package_unit_ea2[]' value='"+$(this).val()+"'>");
        });
        $("input[name='package_unit_ea3[]'").each(function(){
            fobj.append("<input type='hidden' name='save_tmp_package_unit_ea3[]' value='"+$(this).val()+"'>");
        });
        $("input[name='package_unit_ea4[]'").each(function(){
            fobj.append("<input type='hidden' name='save_tmp_package_unit_ea4[]' value='"+$(this).val()+"'>");
        });
        $("input[name='package_unit_ea5[]'").each(function(){
            fobj.append("<input type='hidden' name='save_tmp_package_unit_ea5[]' value='"+$(this).val()+"'>");
        });
        fobj[0].submit();
    }

    function applyAllOptInfomation() {openDialog('옵션 설명', "applyAllOptInfomation", {"width":"450","show" : "fade","hide" : "fade"});}

    function applyOptInfomation(infomationIdx) {
        var orgValue	= $('#infomation_' + infomationIdx).val();
        $('#tmpInfomation').val(orgValue);
        $('#tmpInfomationIdx').val(infomationIdx);
        openDialog('옵션 설명', "applyOptInfomation", {"width":"450","show" : "fade","hide" : "fade"});
    }

    function doApplyOptInfomation() {
        $('#applyOptInfomation').dialog('close');
        var infomationIdx	= $('#tmpInfomationIdx').val();
        var newValue		= $.trim($('#tmpInfomation').val());
        $('#infomation_' + infomationIdx).val(newValue);
        ready_input_save($('#infomation_' + infomationIdx))
        save_input_value($('#infomation_' + infomationIdx));
        
        if (!newValue)
            $('#viewInfomationText_' + infomationIdx).html('미입력');
        else
            $('#viewInfomationText_' + infomationIdx).html('<span class="underline">보기</span>');

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

        goodscetegory			= $("input:radio[name='firstCategory']:checked", opener.document).val();
        goodsbrand				= $("input:radio[name='firstBrand']:checked", opener.document).val();
        goodslocation			= $("input:radio[name='firstLocation']:checked", opener.document).val();

        $("select[name='selectEtcTitle[]']",opener.document).each(function(){


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
                $("#goodsCode", opener.document).val(result);
                $(".goodsCode", opener.document).html(result);
                $("#goodsCodeOpt").val(result);
                $("#makeGoodsCodLay").dialog('close');
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
                        contents += '<td class="center">';
                        contents += '<button type="button" class="delFreqOption resp_btn v3 size_S" value="'+item.goods_seq+'" data-type="opt">삭제</button>';
                        contents += '</td>';
                        contents += '</tr>';
                        
                        $("#"+popupID+" table tbody").append(contents);
                    });
                } else {
                    $("#"+popupID+" table tbody").html('');
                    $("#"+popupID+" table tbody").html('<tr> <td colspan="2">데이터 없음</td></tr>');
                }
                
                $("#"+popupID+" .paging_navigation").html(data.paging);
            }
        });
    }

    function helpicon_style(){
        /* 툴팁 */
        $(".helpicon, .help").each(function(){
    
            var options = {
                className: 'tip-darkgray',
                bgImageFrameSize: 8,
                alignTo: 'target',
                alignX: 'right',
                alignY: 'center',
                offsetX: 10,
                allowTipHover: false,
                slide: false,
                showTimeout : 0
            }
    
            if($(this).attr('options')){
                var customOptions = eval('('+$(this).attr('options')+')');
                for(var i in customOptions){
                    options[i] = customOptions[i];
                }
            }
    
            $(this).poshytip(options);
        });
    }
    