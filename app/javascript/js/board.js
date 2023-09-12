if(!$.isFunction("getAuthLogin")){
    function getAuthLogin(){
		//접근권한이 없습니다.!
		openDialogAlert(getAlert('et217'),'400','150','');
	}
}
if(!$.isFunction("getLogin")){
    function getLogin(){
		//이용하시려면 로그인이 필요합니다!
		openDialogAlert(getAlert('et218'),'400','155','');
	}
}
if(!$.isFunction("getMbLogin")){
	function getMbLogin(){
		//이용하시려면 로그인이 필요합니다!
		openDialogAlert(getAlert('et218'),'400','155','');
	}
}

//이미지보기
function board_file_review(url,width,height)
{
	if(width >= 1000 ) {
		width = '1000';
	}
	popup('/board/zoom?popup=1&id='+board_id+'&url='+url+'&width='+width+'&height='+height,width,height);
	}

//게시글 상세 이미지 리사이즈
function imageResize(image,default_width){
	var width = image.width;
	var height = image.height;
	var maxWidth = (default_width) ? default_width : 750;
	var widthPercent = maxWidth / width;

	if (width > maxWidth) {
		width = width * widthPercent;
		height = height * widthPercent;
	}
	image.width = width;
	image.height = height;

	if( width ) {
		$(image).css('width',width);
		$(image).css('height',height);
	}
}

var submitFlag = false;
function submitck(){
	submitFlag = false;
	setDefaultText();
}

/*
 * 게시판 관련 자바스크립트
 */
$(document).ready(function() {

	/* 등록/수정 시 > 첨부파일추가*/
	$("#boardfileadd").click(function(){
		var trObj = $("#BoardFileTable tbody tr");
		var trClone = trObj.last().clone();
		trClone.find("input[type='file']").each(function(){
			$(this).val("");
		});
		trClone.find("span.realfilelist").remove();
		trClone.find("input.orignfile_info").remove();

		trObj.parent().append(trClone);
	});

	/* 등록/수정 시 > 첨부파일정보 삭제 */
	$("#BoardFileTable .etcDel").live("click",function(){
		var deletefile = $(this).parent().parent();
		
		if(deletefile.find("span.realfilelist").attr("realfiledir")){
			//정말로 파일을 삭제하시겠습니까?
			let deleteFileBoardSeq = $(this).closest("#writeform").find("#seq").val();
			if(confirm(getAlert('et219')) ) {
				var realfiledir = deletefile.find("span.realfilelist").attr("realfiledir");
				var realfilename = deletefile.find("span.realfilelist").attr("realfilename");
				//var board_id =deletefile.find("span.realfilelist").attr("board_id");
				$.ajax({
					'url' : '../board_process',
					'data' : {'mode':'board_file_delete', 'realfiledir':realfiledir,  'realfilename':realfilename, 'board_id':board_id, 'board_seq': deleteFileBoardSeq},
					'type' : 'post',
					'target' : 'actionFrame',
					'success' : function(res) {
						alert(res);
						return ;
					}
				});

				$(this).parent().parent().remove();
			}
		}else{
			if($("#BoardFileTable tbody tr").length > 1) $(this).parent().parent().remove();
		}
	});

	$("span.realfilelist").live("click",function(){
		var filedown = $(this).attr("filedown");
		//파일을 다운받으시겠습니까?
		if(confirm(getAlert('et220')) ) {
			if(typeof gl_mobile_mode!="undefined" && gl_mobile_mode){
					document.location.href=filedown;
			}else{
				if( $("iframe[name=actionFrame]").length ) {
					$("iframe[name=actionFrame]").attr("src", filedown);
				}else{
					document.location.href=filedown;
				}
			}
		}
	});

		/* 첨부파일순서변경 */
	$("table.boardfileliststyle tbody").sortable({items:'tr'});

	// 게시글 등록
	$('#boad_write_btn').live('click', function() {
		document.location.href=boardwriteurl;
	});

	// 게시글 등록
	$('#boad_write_btn_no').live('click', function() {
		getLogin();
		//document.location.href=boardrpermurl;
	});

	//회원글인경우 로그인으로 이동
	$('.boad_view_btn_mbno').live('click', function() {
		getMbLogin();
	});

	//접근권한이 없는경우
	$('.boad_view_btn_authno').live('click', function() {
		getAuthLogin();
	});


	// 게시글 수정
	$(":input[name=boad_modify_btn]").live("click", function() {
		var seq = $(this).attr("board_seq");
		document.location.href=boardmodifyurl+seq;
	});


	// 비회원 > 게시글 수정
	$(":input[name=boad_modify_btn_no]").live("click", function() {
		$('#ModDelBoardPwcheckForm')[0].reset();//초기화
		var seq = $(this).attr('board_seq');
		var viewlink = $(this).attr('viewlink');
		$("#moddel_pwck_seq").val(seq);
		$("#moddel_pwck_returnurl").val(boardmodifyurl+seq);
		$("#modetype").val('board_modify');
		//게시글수정 <span class='desc'>비밀번호를 입력해 주세요.</span>
		openDialog(getAlert('et221'), "ModDelBoardPwCk", {"width":"370","height":"250"});
	});


	// 비회원 > 게시글 삭제
	$(":input[name=boad_delete_btn_no]").live("click", function() {
		$('#ModDelBoardPwcheckForm')[0].reset();//초기화
		var seq = $(this).attr('board_seq');
		var viewlink = $(this).attr('viewlink');
		$("#moddel_pwck_seq").val(seq);
		$("#moddel_pwck_returnurl").val(boardlistsurl);
		$("#modetype").val('board_delete');
		//게시글삭제  <span class='desc'>비밀번호를 입력해 주세요.</span>
		openDialog(getAlert('et222'), "ModDelBoardPwCk", {"width":"370","height":"250"});
	});


	//비밀글 > 비번체크
	$("#ModDelBoardPwcheckForm").validate({
		submitHandler: function(form) {
			var seq = $("#moddel_pwck_seq").val();
			var pw = $("#moddel_pwck_pw").val();
			var modetype = $("#modetype").val();
			var returnurl = $("#moddel_pwck_returnurl").val();
			if(!pw){
				setDefaultText();
				//비밀번호를 입력해 주세요.
				alert(getAlert('et223'));
				$("#moddel_pwck_pw").focus();
				return false;
			}else{
				$.ajax({
					'url' : '../board_process',
					'data' : {'mode':'board_modifydelete_pwckeck','modetype':modetype, 'seq':seq, 'pw':pw, 'board_id':board_id},
					'type' : 'post',
					'dataType': 'json',
					'success' : function(res) {
						if(res.result == true){
							if(res.msg){
								openDialogAlert(res.msg,'400','150',function(){document.location.href=returnurl;});
							}else{
								if(modetype == 'board_delete' ){
									//정상적으로 삭제되었습니다.
									openDialogAlert(getAlert('et224'),'400','150',function(){document.location.href=returnurl;});
								}else{
									document.location.href=returnurl;
								}
							}
						}else{
							if(res.msg){
								openDialogAlert(res.msg,'400','150',function(){});
							}else{
								//잘못된 접근입니다.
								openDialogAlert(getAlert('et225'),'400','150',function(){});
							}
						}
					}
				});
			}//endif
			return false;
		}
	});

	//
	$("span.captcha_code_refresh").live("click", function() {
		$.ajax({
			'url' : '../board_process',
			'data' : {'mode':'captcha_code_refresh', 'board_id':board_id},
			'type' : 'post',
			'dataType': 'json',
			'success' : function(res) {
				if(res.result == true ) {
					$("div.board_captcha").html(res.img);
				}
			}
		});
	});


	// 게시글 답변
	$(":input[name=boad_reply_btn]").live("click", function() {
		var seq = $(this).attr("board_seq");
		document.location.href=boardreplyurl+seq;
	});

	// 게시글 삭제
	$(":input[name=boad_delete_btn],:input[name=goods_boad_delete_btn]").live("click", function() {
		//var board_id = $(this).attr('board_id');
		var delseq = $(this).attr('board_seq');
		boarddeleteless(board_id, delseq );
	});

	// 게시글 보기
	$('.boad_view_btn').live('click', function() {
		var viewlink = $(this).attr('viewlink');
		var board_seq = $(this).attr('board_seq');
		var viewtype = $(this).attr('viewtype');
		var pagetype = $(this).attr('pagetype');
		if(viewtype == 'layer' && (pagetype == 'goods' || pagetype == 'mypage') ){ //상품상세랑 mypage 에서만처리됨
			boardviewtype(viewlink,board_seq,viewtype);
		}else{
			document.location.href=viewlink;
		}

	});


	// 비밀글 비밀번호입력 > 게시글 보기
	$('.boad_view_btn_no').live('click', function() {
		$('#BoardPwcheckForm')[0].reset();//초기화
		var seq = $(this).attr('board_seq');
		var viewlink = $(this).attr('viewlink');
		$("#pwck_seq").val(seq);
		$("#pwck_returnurl").val(viewlink);
		//비밀글  <span class='desc'>비밀번호를 입력해 주세요.</span>
		openDialog(getAlert('et226'), "BoardPwCk", {"width":"370","height":"250"});
	});

	//비밀글 > 비번체크
	$("#BoardPwcheckForm").validate({
		submitHandler: function(form) {
			var seq = $("#pwck_seq").val();
			var pw = $("#pwck_pw").val();
			var returnurl = $("#pwck_returnurl").val();
			if(!pw){
				setDefaultText();
				//비밀번호를 입력해 주세요.
				alert(getAlert('et223'));
				$("#pwck_pw").focus();
				return false;
			}else{
				$.ajax({
					'url' : '../board_process',
					'data' : {'mode':'board_hidden_pwcheck', 'seq':seq, 'pw':pw, 'board_id':board_id},
					'type' : 'post',
					'dataType': 'json',
					'success' : function(res) {
						if(res.result == true) {
							if(res.msg){
								openDialogAlert(res.msg,'400','150',function(){document.location.href=returnurl;});
							}else{
								document.location.href=returnurl;
							}
						}else{
							if(res.msg){
								openDialogAlert(res.msg,'400','150',function(){});
							}else{
								//잘못된 접근입니다.
								openDialogAlert(getAlert('et225'),'400','150',function(){});
							}
						}
					}
				});
			}//endif
			return false;
		}
	});

	/**
	* 상품문의/상품후기 등록/수정/삭제 ---------------------
	**/
	// 게시글 등록
	$('#goods_boad_write_btn').live('click', function() {
		document.location.href=boardwriteurl;
	});


	// 게시글 답변
	$("#goods_boad_reply_btn").live("click", function() {
		var seq = $(this).attr("board_seq");
		document.location.href=boardreplyurl+seq;
	});


	// 게시글 등록
	$('#goods_boad_write_btn_no').live('click', function() {
		getLogin();
	});


	// 게시글 수정
	$(":input[name=goods_boad_modify_btn]").live("click", function() {
		var seq = $(this).attr("board_seq");
		boardmodifyurl +=seq;
		//popup(boardmodifyurl, '750', '850');
		document.location.href=boardmodifyurl;
	});


	// 비회원 > 게시글 수정
	$(":input[name=goods_boad_modify_btn_no]").live("click", function() {
		$('#ModDelBoardPwcheckForm')[0].reset();//초기화
		var seq = $(this).attr('board_seq');
		var viewlink = $(this).attr('viewlink');
		$("#moddel_pwck_seq").val(seq);
		$("#moddel_pwck_returnurl").val(boardmodifyurl+seq);
		$("#modetype").val('board_modify');
		//게시글수정 <span class='desc'>비밀번호를 입력해 주세요.</span>
		openDialog(getAlert('et221'), "ModDelBoardPwCk", {"width":"370","height":"250"});
	});

	// 비회원 > 게시글 삭제
	$(":input[name=goods_boad_delete_btn_no]").live("click", function() {
		$('#ModDelBoardPwcheckForm')[0].reset();//초기화
		var seq = $(this).attr('board_seq');
		var viewlink = $(this).attr('viewlink');
		$("#moddel_pwck_seq").val(seq);
		$("#moddel_pwck_returnurl").val(boardlistsurl);
		$("#modetype").val('board_delete');
		//게시글삭제  <span class='desc'>비밀번호를 입력해 주세요.</span>
		openDialog(getAlert('et222'), "ModDelBoardPwCk", {"width":"370","height":"250"});
	});
	/**
	* 상품문의 후기 등록/수정/삭제---------------------
	**/


	//삭제글
	$('input#searchdisplay').live('click', function() {
		$("#search_text").focus();//검색
		$("#boardsearch").attr("action",boardlistsurl);
		$('#boardsearch').submit();
	});

	//비밀글
	$('input#searchhidden').live('click', function() {
		$("#search_text").focus();//검색
		$("#boardsearch").attr("action",boardlistsurl);
		$('#boardsearch').submit();
	});


	// FAQ 게시글 보기
	$('span.boad_faqview_btn').live('click', function() {
		var faqview_btn = $(this);
		call_faq_view(faqview_btn);
	});


	$("#board_comment_cancel").live("click", function() {
		$('#board_commentsend').text('댓글등록');
	});

	$("button[name=boardviewclose]").live("click", function() {
		document.location.href=boardlistsurl;
	});

	$('select#multichkec').change(function() {
		if($(this).val() == 'false'){
			$('#checkboxAll').removeAttr("checked");
			$('.checkeds').each(function(e, el) {
				if( $(el).attr('disabled') != 'disabled' ){//제외
					$(el).removeAttr("checked");
				}
			});
		}else{
			$('#checkboxAll').attr("checked","checked");
			$('.checkeds').each(function(e, el) {
				if( $(el).attr('disabled') != 'disabled' ){//제외
					$(el).attr('checked', true);
				}
			});
		}
	});

	$('#checkboxAll').live('click', function() {
		checkAll(this, '.checkeds');
	});

	// 날짜 선택시 해당 일자로 셋팅한다.
	$("input.select_date").click(function() {
		switch(this.id) {
		case 'today' :  $('#rdate_s').val(getDate(0));
			$('#rdate_f').val(getDate(0));
			break;
		case '3day' :   $('#rdate_s').val(getDate(3));
			$('#rdate_f').val(getDate(0));
			break;
		case '1week' :  $('#rdate_s').val(getDate(7));
			$('#rdate_f').val(getDate(0));
			break;
		case '1month' : $('#rdate_s').val(getDate(30));
			$('#rdate_f').val(getDate(0));
			break;

		case '3month' : $('#rdate_s').val(getDate(90));
			$('#rdate_f').val(getDate(0));
			break;
		case 'select_date_all' :$('#rdate_s').val('');
			$('#rdate_f').val('');
			break;
		default:
			$('#rdate_s').val('');
			$('#rdate_f').val('');
		}
	});

 $("#boardsearch").submit(function() {
		$("#boardsearch").attr("action",boardlistsurl);
	 });

	$('#display_quantity').bind('change', function() {
		$.cookie( "itemlist_qty", $(this).val() );
		$("#perpage").val($(this).val());
		$("#boardsearch").attr("action",boardlistsurl);
		$("#boardsearch").submit();
	});

	if($.cookie("itemlist_qty")) {
		$('#display_quantity').val($.cookie("itemlist_qty") );
	}

	$("#addcategory").bind("change",function(){
		if( $(this).val() == 'newadd') {
			$("#newcategory").removeClass("hide").addClass("show");
		}else{
			$("#newcategory").removeClass("show").addClass("hide");
		}
	});

	$("#searchcategory").bind("change",function(){
		$("#category").val($(this).val());
		$("#boardsearch").attr("action",boardlistsurl);
		$("#page").val('');
		$("#boardsearch").submit();
	});


	$("#searchscore").bind("change",function(){
		$("#score").val($(this).val());
		$("#boardsearch").attr("action",boardlistsurl);
		$("#page").val('');
		$("#boardsearch").submit();
	});


	//동영상등록폼
	$("button.batchVideoRegist").click(function(){
		var seq = $(this).attr("board_seq");
		window.open('./popup_video?seq='+seq+'&id='+board_id,'','width=550,height=350');
	});

	/**
	* 게시글 평가하기
	**/
	$("span.icon_recommend_lay_y, span.icon_none_rec_lay_y, span.icon_recommend1_lay_y,  span.icon_recommend2_lay_y,  span.icon_recommend3_lay_y,  span.icon_recommend4_lay_y,  span.icon_recommend5_lay_y").live("click", function() {
		//이미 평가하신 게시글입니다.
		openDialogAlert(getAlert('et227'),'400','150');
	});

	$("span.icon_recommend_lay, span.icon_none_rec_lay, span.icon_recommend1_lay, span.icon_recommend2_lay,  span.icon_recommend3_lay,  span.icon_recommend4_lay,  span.icon_recommend5_lay").live("click", function() {//
		try{
			if(eval("board_seq"))
			{
				var board_seq_new = board_seq;
			}
		}catch(e){
			var board_seq_new = $(this).attr("board_seq");
		}

		if(!eval('board_id')) {
			var board_id_new = $(this).attr("board_id");
		}else{
			var board_id_new = board_id;
		}
		var board_recommend = $(this).attr("board_recommend");
		boardscoresave(board_recommend, board_seq_new, board_id_new);
	});

	/**
	* 댓글 평가하기
	**/
	$("span.icon_cmt_recommend_lay_y, span.icon_cmt_none_rec_lay_y").live("click", function() {
		//이미 평가하신 댓글입니다.
		openDialogAlert(getAlert('et228'),'400','150');
	});

	$("span.icon_cmt_recommend_lay, span.icon_cmt_none_rec_lay").live("click", function() {
		try{
			if(eval("board_seq"))
			{
				var board_seq_new = board_seq;
			}
		}catch(e){
			var board_seq_new = $(this).attr("board_seq");
		}
		if(!eval('board_id')) {
			var board_id_new = $(this).attr("board_id");
		}else{
			var board_id_new = board_id;
		}
		var cparent = $(this).attr("board_cmt_seq");
		var board_recommend = $(this).attr("board_recommend");
		boardcmtscoresave(board_recommend, board_seq_new, cparent, board_id_new);
	});

	// 신고하기
	$(".report_btn").live('click', function () {
		var board_cmt_seq = $(this).attr('board_cmt_seq');
		reportboard(board_cmt_seq);
	});

	// 차단하기
	$(".block_btn").live('click', function () {
		var board_cmt_seq = $(this).attr('board_cmt_seq');
		var block_onoff = $(this).attr('block_onoff');
		blockboard(block_onoff, board_cmt_seq);
	});
});

/**
 * 게시글 차단하기
 * @param {int} board_cmt_seq 
 * @returns 
 */
 function blockboard(block_onoff, board_cmt_seq) {
	// boardid, boardseq
	if (typeof board_id == 'undefined') return;
	if (typeof board_seq == 'undefined') return;

	if (gl_isuser == false) {
		getLogin();
		return;
	}

	var data = {
		'board_id': board_id,
		'board_seq': board_seq,
		'board_type': 'board',
		'block_onoff' : block_onoff,
	};

	if (typeof board_cmt_seq != 'undefined') {
		data['board_type'] = 'comment';
		data['board_seq'] = board_cmt_seq;
	}

	$.ajax({
		'url' : '/board_process/block_check',
		'data': data,
		'type' : 'post',
		'dataType': 'json',
		'success': function (res) {
			if (!res.result) {
				openDialogAlert(res.msg, '400', '150');
				return;
			}
			if (!res.msg) return;
			openDialogConfirm(res.msg, '400', '200', function () {
				$.ajax({
					'url': '/board_process/block',
					'data': data,
					'type': 'post',
					'dataType' : 'json',
					'success': function (res) {
						if (res.result) {
							location.reload();
						} else {
							openDialogAlert(res.msg, '400', '150');
						}
					}
				});
			});
		}
	});
}


/**
 * 게시글 신고하기
 * @param {int} board_cmt_seq 
 * @returns 
 */
function reportboard(board_cmt_seq) {
	// boardid, boardseq
	if (typeof board_id == 'undefined') return;
	if (typeof board_seq == 'undefined') return;

	if (gl_isuser == false) {
		getLogin();
		return;
	}

	var data = {
		'board_id': board_id,
		'board_seq': board_seq,
		'board_type' : 'board',
	};

	// 댓글인경우
	if (typeof board_cmt_seq != 'undefined') {
		data['board_type'] = 'comment';
		data['board_seq'] = board_cmt_seq;
	}

	$.ajax({
		'url' : '/board_process/report_check',
		'data': data,
		'type' : 'post',
		'dataType': 'json',
		'success': function (res) {
			if (res.result) {
				showreportform(data);
			} else {
				// 중복해서 신고하실 수 없습니다. 경고창
				openDialogAlert(res.msg,'400','150');
			}
		}
	});
}

/**
 * 신고하기 폼 노출
 * @param {array} data 
 */
function showreportform(data) {
	$.ajax({
		'url' : '/board/report',
		'data': data,
		'type' : 'post',
		'success': function (html) {
			$('#report_content .layer_pop_contents').html(html);
			if (typeof gl_operation_type != 'undefined' && gl_operation_type == 'light') {	// 반응형
				showCenterLayer('#report_content');
			}else if(typeof gl_mobile_mode != 'undefined' && gl_mobile_mode > 0){	// 모바일
				openDialogModal("reportDialog", getAlert('et427'), '' , 'report_content' );
			} else {	// pc
				openDialog(getAlert('et427'), "report_content", { "width": 600, "height": 320 });
			}
		}
	});	
}

/**
* 게시글 평가하기
**/
function boardscoresave(scoreid, boardseq, board_id) {
	if( eval('gl_isuser') ) {//회원전용

		//openDialogConfirmtitle('게시글 평가하기','정말로 평가하시겠습니까?','240','150',function() {
			$.ajax({
				'url' : '../board_process/board_score_save',
				'data' : {'parent':boardseq, 'board_id':board_id, 'scoreid':scoreid},
				'type' : 'post',
				'dataType': 'json',
				'success' : function(res){
					if(res.result) {
						$("span.idx-"+scoreid+"-"+boardseq).text(res.scoreid);
						$("span.icon_recommend_"+boardseq+"_lay").addClass("icon_recommend_lay_y").removeClass("icon_recommend_lay");
						$("span.icon_none_rec_"+boardseq+"_lay").addClass("icon_none_rec_lay_y").removeClass("icon_none_rec_lay");
						$("span.icon_recommend1_"+boardseq+"_lay").addClass("icon_recommend1_lay_y").removeClass("icon_recommend1_lay");
						$("span.icon_recommend2_"+boardseq+"_lay").addClass("icon_recommend2_lay_y").removeClass("icon_recommend2_lay");
						$("span.icon_recommend3_"+boardseq+"_lay").addClass("icon_recommend3_lay_y").removeClass("icon_recommend3_lay");
						$("span.icon_recommend4_"+boardseq+"_lay").addClass("icon_recommend4_lay_y").removeClass("icon_recommend4_lay");
						$("span.icon_recommend5_"+boardseq+"_lay").addClass("icon_recommend5_lay_y").removeClass("icon_recommend5_lay");
						$("input[name='input-recommend["+boardseq+"]']").hide();
						if(typeof gl_mobile_mode!="undefined" && gl_mobile_mode){
							$("input[name='input-recommend["+boardseq+"]']").parents(".ez-radio").hide();
						}
						openDialogAlert(res.msg,'400','150');
					}else{
						openDialogAlert(res.msg,'400','150');
					}
				}
			});
		//},function(){$("input[name='input-recommend["+boardseq+"]']").attr('checked', false);});
	}else{
		getLogin();
	}
}

/**
* 댓글 평가하기
**/
function boardcmtscoresave( scoreid, boardseq, cseq, board_id) {
	if( eval('gl_isuser') ) {//회원전용
		//openDialogConfirmtitle('댓글 평가하기','정말로 평가하시겠습니까?','240','150',function() {
				$.ajax({
					'url' : '../board_comment_process/board_score_save',
					'data' : {'parent':boardseq,  'cparent':cseq, 'board_id':board_id, 'scoreid':scoreid},
					'type' : 'post',
					'dataType': 'json',
					'success' : function(res){
						if(res.result) {
							$("span.idx-cmt-"+scoreid+"-"+boardseq+"-"+cseq).text(res.scoreid);
							$("span.icon_cmt_recommend_"+boardseq+"_"+cseq+"_lay").addClass("icon_cmt_recommend_lay_y").removeClass("icon_cmt_recommend_lay");
							$("span.icon_cmt_none_rec_"+boardseq+"_"+cseq+"_lay").addClass("icon_cmt_none_rec_lay_y").removeClass("icon_cmt_none_rec_lay");
							openDialogAlert(res.msg,'400','150');
						}else{
							openDialogAlert(res.msg,'400','150');
						}
					}
				});
		//},function(){});
	}else{
		getLogin();
	}
}

//게시글 삭제시 마일리지 또는 포인트 회수
function boarddeleteless(board_id, delseq ){
	if( board_id == 'goods_review' ) {
		$.ajax({
			'url' : '../board_goods_process',
			'data' : {'mode':'goods_review_less_view', 'delseq':delseq, 'board_id':board_id},
			'type' : 'post',
			'dataType': 'json',
			'success' : function(res){
				if(res.result == "delete") {
					//삭제된 게시글은 복구할 수 없습니다.\n정말로 삭제하시겠습니까?
					var msg = getAlert('et229');
					//삭제
					openDialogConfirmtitle(res.name+' '+getAlert('et230'),msg,'450','140',function(){
					loadingStart("body",{segments: 12, width: 15.5, space: 6, length: 13, color: '#000000', speed: 1.5});
						$.ajax({
						'url' : '../board_process',
						'data' : {'mode':'board_delete', 'delseq':delseq, 'board_id':board_id},
						'type' : 'post',
						'success' : function(res){
							//정상적으로 삭제되었습니다.
							openDialogAlert(getAlert('et231'),'400','150',function(){document.location.href=boardlistsurl;});
						}
					});},function(){});
				}else if(res.result == "lees") {
					openDialogConfirmtitle(res.name+' '+getAlert('et230'),res.msg,'480','320',function() {
					loadingStart("body",{segments: 12, width: 15.5, space: 6, length: 13, color: '#000000', speed: 1.5});
						$.ajax({
							'url' : '../board_process',
							'data' : {'mode':'board_delete', 'delseq':delseq, 'board_id':board_id},
							'type' : 'post',
							'success' : function(res){
							//정상적으로 삭제되었습니다.
							openDialogAlert(getAlert('et231'),'400','150',function(){document.location.href=boardlistsurl;});
							}
						});
					},function(){});
				}else if(res.result == "lees_none") {
					openDialogAlerttitle(res.name+' '+getAlert('et230'),res.msg,'400','270','',{hideButton:1});
				}else{
					openDialogAlert(res.msg,'400','140');
				}
			}
		});
	}else{
		$.ajax({
			'url' : '../board_goods_process',
			'data' : {'mode':'board_less_view', 'delseq':delseq, 'board_id':board_id},
			'type' : 'post',
			'dataType': 'json',
			'success' : function(res){
				if(res.result == "delete") {
					//삭제된 게시글은 복구할 수 없습니다.\n정말로 삭제하시겠습니까?
					var msg = getAlert('et229');
					openDialogConfirmtitle(res.name+' '+getAlert('et230'),msg,'450','140',function(){
					loadingStart("body",{segments: 12, width: 15.5, space: 6, length: 13, color: '#000000', speed: 1.5});
						$.ajax({
						'url' : '../board_process',
						'data' : {'mode':'board_delete', 'delseq':delseq, 'board_id':board_id},
						'type' : 'post',
						'success' : function(res){
							openDialogAlert(getAlert('et231'),'400','150',function(){document.location.href=boardlistsurl;});
						}
					});},function(){});
				}else if(res.result == "lees") {
					openDialogConfirmtitle(res.name+' '+getAlert('et230'),res.msg,'480','250',function() {
					loadingStart("body",{segments: 12, width: 15.5, space: 6, length: 13, color: '#000000', speed: 1.5});
						$.ajax({
							'url' : '../board_process',
							'data' : {'mode':'board_delete', 'delseq':delseq, 'board_id':board_id},
							'type' : 'post',
							'success' : function(res){
							openDialogAlert(getAlert('et231'),'400','150',function(){document.location.href=boardlistsurl;});
							}
						});
					},function(){});
				}else if(res.result == "lees_none") {
					openDialogAlerttitle(res.name+' '+getAlert('et230'),res.msg,'400','270','',{hideButton:1});
				}else{
					openDialogAlert(res.msg,'400','140');
				}
			}
		});
	}
}

//상품후기 > 마일리지지급시 몇자이상 마일리지 작성여부 체크
function chk_review_reserve() {
	if( eval('$(".review_reserve_ok")') ){
		$(".review_reserve_ok").val("ok");
	}else{
		$("form#writeform").append('<input type="hidden" name="review_reserve_ok"  class="review_reserve_ok" value="ok" >');
	}
	$("form#writeform").submit();
}

//게시글보기방식변경됨(상품후기/상품문의만)
function boardviewtype(viewlink,board_seq,viewtype) {

	if(typeof gl_operation_type != 'undefined' && gl_operation_type == 'light'){
		$(".goodsviewer").hide();
	}else{
		$("tr.goodsviewer").hide();
		$("div.goodsviewer").html('');
	}

	if($("#tdviewer"+board_seq).css("display") == "none") {
		boardviewtypeshow(viewlink,board_seq,viewtype);
	}

}
/*
function boardviewtype(viewlink,board_seq,viewtype) {
	if(typeof gl_operation_type != 'undefined' && gl_operation_type == 'light'){
		$("ul.goodsviewer").hide();
	}else{
		$("tr.goodsviewer").hide();
	}
	$("div.goodsviewer").html('');

	if($("#tdviewer"+board_seq).css("display") == "none") {
		boardviewtypeshow(viewlink,board_seq,viewtype);
	}
}
*/

//댓글보여주기
function boardviewtypeshow(viewlink,board_seq,viewtype) {
	if(typeof gl_operation_type != 'undefined' && gl_operation_type == 'light') {
		$.ajax({
			'url' : viewlink,
			'data': {'mode':'layer','iframe':'1'},
			'success' : function(res){
				$("#viewer"+board_seq).html(res);
			},
			'complete' : function() {
				showCenterLayer('#tdviewer'+board_seq);
				//setDefaultText();
			}
		});
	} else {
		$.ajax({
			'url' : viewlink,
			'data': {'mode':'layer','iframe':'1'},
			'success' : function(res){
				$("#tdviewer"+board_seq).show();
				$("#viewer"+board_seq).show();
				$("#viewer"+board_seq).html(res);
				setDefaultText();
			}
		});
	}
}

//sns 연동 관련 함수
function goTwitter(msg,url) {
	var href = "http://twitter.com/home?status=" + encodeURIComponent(msg) + " " + encodeURIComponent(url);
	var a = window.open(href, 'twitter', '');
	if ( a ) {
		a.focus();
	}
}
function goMe2Day(msg,url,tag) {
	var href = "http://me2day.net/posts/new?new_post[body]=" + encodeURIComponent(msg) + " " + encodeURIComponent(url) + "&new_post[tags]=" + encodeURIComponent(tag);
	var a = window.open(href, 'me2Day', '');
	if ( a ) {
		a.focus();
	}
}
function goFaceBook(msg,url) {
	var href = "http://www.facebook.com/sharer.php?u=" + encodeURIComponent(url) + "&t=" + encodeURIComponent(msg);
	var a = window.open(href, 'facebook', '');
	if ( a ) {
		a.focus();
	}
}
function goCyWorld(no) {
	var href = "http://api.cyworld.com/openscrap/post/v1/?xu=http://ticketmonster.co.kr/html/cyworldConnectToXml.php?no=" + no +"&sid=suGPZc14uNs4a4oaJbVPWkDSZCwgY8Xe";
	var a = window.open(href, 'facebook', 'width=450,height=410');
	if ( a ) {
		a.focus();
	}
}
function goYozmDaum(link,prefix,parameter) {
	var href = "http://yozm.daum.net/api/popup/post?sourceid=&link=" + encodeURIComponent(link) + "&prefix=" + encodeURIComponent(prefix) + "&parameter=" + encodeURIComponent(parameter);
	var a = window.open(href, 'yozmSend', 'width=466, height=356');
	if ( a ) {
		a.focus();
	}
}

/**
 * 현재 시각을 Time 형식으로 리턴
 */
function getCurrentTime(date) {
	return toTimeString(new Date(date));
}

/**
 * 자바스크립트 Date 객체를 Time 스트링으로 변환
 * parameter date: JavaScript Date Object
 */
function toTimeString(date) {
	var year  = date.getFullYear();
	var month = date.getMonth() + 1; // 1월=0,12월=11이므로 1 더함
	var day   = date.getDate();

	if (("" + month).length == 1) {month = "0" + month;}
	if (("" + day).length   == 1) {day   = "0" + day;}

	return ("" + year + month + day)
}

/**
 * 현재 年을 YYYY형식으로 리턴
 */
function getYear(date) {
	return getCurrentTime(date).substr(0,4);
}

/**
 * 현재 月을 MM형식으로 리턴
 */
function getMonth(date) {
	return getCurrentTime(date).substr(4,2);
}

/**
 * 현재 日을 DD형식으로 리턴
 */
function getDay(date) {
	return getCurrentTime(date).substr(6,2);
}

function getDate(day) {
	var d = new Date();
	var dt = d - day*24*60*60*1000;
	return getYear(dt) + '-' + getMonth(dt) + '-' + getDay(dt);
}
/**
 * 체크박스 전체 선택
 * @param string el 전체 선택 체크박스
 * @param string targetEl 적용될 체크박스 클래스명
 */
function checkAll(el, targetEl) {
	if( $(el).attr('rel') == 'yes' ) {
		var do_check = false;
		$(el).attr('rel', 'no');
	} else {
		var do_check = true;
		$(el).attr('rel', 'yes');
	}
	$(targetEl).each(function(e, el) {
		if( $(el).attr('disabled') != 'disabled' ){//제외
			$(el).attr('checked', do_check);
		}
	});
}



/**
 * 신규생성 다이얼로그 창을 띄운다.
 * <pre>
 * 1. createElementContainer 함수를 이용하여 매번 div 태그를 입력하지 않고 다이얼로그 생성시 자동으로 생성한다.
 * 2. refreshTable 함수를 이용하여 다이얼로그 내용 부분을 불러온다.
 * </pre>
 * @param string url 폼화면 주소
 * @param int width 가로 사이즈
 * @param int height 세로 사이즈
 * @param string title 제목
 * @param string btn_yn 'false'이면 닫기버튼만 나타낸다.
 */
function boardaddFormDialog(url, width, height, title, btn_yn) {
	createElementContainer(title);
	refreshTable(url);

	if (btn_yn != 'false') {
		var buttons = {
			'닫기': function() {
				$(this).dialog('close');
			},
			'저장하기': function() {
				$('#form1').submit();
			}
		}
	}
	/**
	else
	{
		var buttons =  {
			'닫기': function() {
				$(this).dialog('close');
			}
		}
	}
	**/

	$('#dlg').dialog({
		bgiframe: true,
		autoOpen: false,
		width: width,
		//height: height,
		resizable: false,
		draggable: false,
		modal: true,
		overlay: {
			backgroundColor: '#000000',
			opacity: 0.8
		},
		position: {my:'center',at:'top',of:window},
		buttons: buttons
	}).dialog('open');
	return false;
}


/* 상품후기 > 주문가져오기 회원/비회원 접근가능 */
if(!$.isFunction("goods_review_order_load")){
	function goods_review_order_load(goods_seq, order_seq, callbackFunction){
	$("#orderbtnlay").show();
	$("#ordertxtlay").show();
	$("select[name='ordergoodslist'] option").each(function(){ if( $(this).val() ) $(this).remove(); });
	if(!goods_seq) {
		//먼저 상품을 선택하세요.
		$("#orderbtnlay").text(getAlert('et232'));
		return;
	}
	$("#ordertxtlay").hide();
			$("#orderbtnlay").html('');
		//$("#orderbtnlay").html('<span class="btn small cyanblue"><button type="button" id="OrderauthButton" >주문조회하기</button></span> 해당 상품의 주문내역이 없습니다.');
	$.ajax({
		type: "POST",
		url: "/common/orderlistjson",
		data: "goods_seq=" + goods_seq+"&order_seq=" + order_seq,
		dataType: 'json',
		success: function(result){
				if( result.auth_write == 'onlybuyer' ) {
			if(result.nonorder == true && result.data.length == 0){//비회원인경우
				$("#orderbtnlay").html('<span class="btn small cyanblue"><button type="button" id="OrderauthButton" >주문조회하기</button></span> 해당 상품의 주문내역이 없습니다.');
				$("select[name='ordergoodslist']").hide();
				return false;
			}else if(result.data.length == 0) {
				//배송완료된 상품에 대해서 상품후기를 작성할 수 있습니다. 위 상품에 대한 배송완료된 주문건이 없습니다.
				$("#orderbtnlay").text(getAlert('et233'));
				$("select[name='ordergoodslist']").hide();
				return false;
			}
				}else{
					$("#orderbtnlay").html('');
					$("select[name='ordergoodslist']").hide();
				}
			//
			var options = "";
			var stepval = 0;
			if(result.data.length > 0) {
				var seltmp =  (result.data.length == 1)?' selected ':'';
				var sel;
				for(var i=0;i<result.data.length;i++) {
					if( result.data[i].step == '70' ||  result.data[i].step == '75' ) {//배송중인경우에만
						sel = (order_seq == result.data[i].order_seq )?' selected ':seltmp;
						options += "<option value='"+result.data[i].order_seq+"' "+sel+" >"+result.data[i].order_seq+"</option>";//
						stepval++;
					}else{
						options += "<option readonly value='' >"+result.data[i].order_seq+" ["+result.data[i].mstep+"] </option>";//
					}
				}
			}
			$("select[name='ordergoodslist']").append(options);

				if( result.auth_write == 'onlybuyer' ) {
			if(options && stepval > 0 ){
				$("select[name='ordergoodslist']").show();
				$("select[name='ordergoodslist']").closest("tr").show();
				//해당 상품이 배송완료된 주문내역입니다.
				$("#orderbtnlay").text(getAlert('et234'));
			}else{
				if(stepval  == 0){
					if(result.nonorder == true){//비회원인경우
						$("#orderbtnlay").html('<span class="btn small cyanblue"><button type="button" id="OrderauthButton" >주문조회하기</button></span> 배송완료 후 상품후기를 작성할 수 있습니다.');
					$("select[name='ordergoodslist']").hide();
					}else{
						//배송완료 후 상품후기를 작성할 수 있습니다.
						$("#orderbtnlay").html(getAlert('et235'));
						$("select[name='ordergoodslist']").show();
						$("select[name='ordergoodslist']").closest("tr").show();
					}
				}
			}
				}else{
					if(options && stepval > 0 ){
						$("select[name='ordergoodslist']").show();
						$("select[name='ordergoodslist']").closest("tr").show();
						//해당 상품이 배송완료된 주문내역입니다.
						$("#orderbtnlay").text(getAlert('et234'));
					}else{
						$("#orderbtnlay").html('');
						$("select[name='ordergoodslist']").hide();
					}
				}

			/**
			if(callbackFunction){
				callbackFunction(result);
			}
			**/
		}
	});

	}
}


/* 1:1문의 > 주문리스트 */
function myqna_order_load(){
	$("#orderbtnlay").show();
	$("#ordertxtlay").show();
	$("select[name='ordergoodslist'] option").each(function(){ if( $(this).val() ) $(this).remove(); });
	$("#ordertxtlay").hide();
	$.ajax({
		type: "POST",
		url: "/common/myqanorderlistjson",
		dataType: 'json',
		success: function(result){
			if( result.auth_write == 'onlybuyer' ) {
			 if(result.data.length == 0) {
				 //배송완료된 상품에 대해서 상품후기를 작성할 수 있습니다. 위 상품에 대한 배송완료된 주문건이 없습니다.
				$("#orderbtnlay").text(getAlert('et233'));
				$("select[name='ordergoodslist']").hide();
				return false;
				}
			}else{
				$("#orderbtnlay").html('');
				$("select[name='ordergoodslist']").hide();
			}
			var options = "";
			var stepval = 0;
			if(result.data.length > 0) {
				//var sel =  (result.data.length == 1)?' selected ':'';
				for(var i=0;i<result.data.length;i++) {
					if(result.data[i].item_cnt > 1 ) {
						options += "<option value='"+result.data[i].order_seq+"'  >["+result.data[i].order_seq+"] "+result.data[i].goods_name+" 외 "+result.data[i].item_cnt+" 건 </option>";//
					}else{
						options += "<option value='"+result.data[i].order_seq+"'  >["+result.data[i].order_seq+"] "+result.data[i].goods_name+"</option>";//
					}
						stepval++;
				}
			}
			$("select[name='ordergoodslist']").append(options);
		}
	});
}

function createElementContainer(title) {
	var dlg_title = title ? title : '등록 폼';
	var el = '<div id="dlg" title="' + dlg_title + '"   ><div id="dlg_content" ></div></div>';
	$('#dlg').remove();
	$(el).appendTo('body');
}

function refreshTable(url) {
	$.get(url, {}, function(data, textStatus) {
		$('#dlg_content').html(data);
	});
}


function BoardchkByte(str){
	var cnt = 0;
	for(i=0;i<str.length;i++) {
		cnt += str.charCodeAt(i) > 128 ? 2 : 1;
		if(str.charCodeAt(i)==10) cnt++;
	}
	return cnt;
}


/**
 * 새창으로 팝업을 띄웁니다 / 퍼스트몰 2.0 js
 * popup('zoom.php?seq=7',750,550)
 */
function popup(src,width,height) {
	var scrollbars = "1";
	var resizable = "no";
	if (typeof(arguments[3])!="undefined") scrollbars = arguments[3];
	if (arguments[4]) resizable = "yes";
	window.open(src,'','width='+width+',height='+height+',scrollbars='+scrollbars+',toolbar=no,status=no,resizable='+resizable+',menubar=no');
}

// 크로스도메인 용 iframe 리사이징
function cross_iframe_resize(){
	if (parent.postMessage) {
		var protocol = 'https:';
		if(typeof location.protocol === 'string') {
			protocol = location.protocol;
		}
		
		parent.postMessage({
			height: $('#boardlayout').height()+100
			, id:board_id+'_frame'
		}, protocol + "//"+window.location.hostname);
	}
}
$(document).ready(function(){
	cross_iframe_resize();

	$("input[name='pw']").each(function(){
		var id = $(this).attr("id");
		if(id != 'moddel_pwck_pw' && id != 'pwck_pw' ){
			init_check_password_validation($(this));
		}
	});
});