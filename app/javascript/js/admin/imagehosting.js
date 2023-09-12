if (typeof imagehostingScriptLoaded == 'undefined') {		// ready 는 1회만 실행되도록
	var imagehostingScriptLoaded = true;
	$(function () {
		/*
			비밀번호 변경 여부
		*/
		$("input[name='passwd_chg']").live("click", function () {
			if ($(this).attr("checked")) {
				$("input[name='store_password']").attr("disabled", false);
				$("input[name='store_password']").val('');
			} else {
				$("input[name='store_password']").attr("disabled", true);
				$("input[name='store_password']").val(gl_store_password);
			}
		});

	});
}


/*
	  도메인 입력시 저장 경로 노출
*/
function change_domain() {
	var store_url = "";
	if ($("input[name='store_url']").val() != '') {
		store_url = $("select[name='store_url_protocol'] option:selected").text() + $("input[name='store_url']").val() + "/";
	}
	var url_information = store_url + $("input[name='store_dir']").val();

	$("#url_information").text(url_information);
}


/*
	이미지호스팅 계정 추가
*/
function save_imagehosting() {
	openDialogConfirm('입력한 정보로 저장하시겠습니까?', 500, 170,
		function () {
			$('[name="mode"]').val('save');
			$('#imagehostingSettingForm').submit();
		}, function () { });
}


/*
	이미지호스팅 계정 수정
*/
function popup_imagehosting(imagehosting_seq) {
	/* 권한 체크 */
	if(!imagehosting_act){
		openDialogAlert('권한이 없습니다.',400,140,'parent','parent.location.reload();');
		return;
	}
	$.get('imagehosting_setting?imagehosting_seq=' + imagehosting_seq, function (data) {
		$('#imagehostinglay').html(data);
		openDialog("저장소 계정 추가", "imagehostinglay", { "width": 570, "height": 950 });

		// 안내 기본 도메인
		change_domain();
		if (gl_connection_type) {
			$("input[name='connection_type'][value='" + gl_connection_type + "']").attr('checked', true);
		}

		// 신규/수정에 따른 비밀번호 노출
		if(imagehosting_seq){
			$("input[name='store_password']").attr("size", "30");
			$(".store_password_change").show();
		}else{
			$("input[name='store_password']").attr("size", "40");
			$(".store_password_change").hide();
		}
	});
}

/*
	접속타입에 따라 포트 기본 값 설정
*/
function check_connection_type(connection_type) {
	if(connection_type == 'sftp'){
		$("input[name='store_port']").val('22');
	}else{
		$("input[name='store_port']").val('21');
	}
}

/*
	이미지호스팅 접속 테스트
*/
function check_imagehosting() {
	$('[name="mode"]').val('test');
	$('#imagehostingSettingForm').submit();
}

/*
	이미지호스팅 계정 삭제
*/
function delete_imagehosting(imagehosting_seq) {
	if (imagehosting_seq == '' || imagehosting_seq == null || typeof imagehosting_seq == 'undefined') {
		openDialogAlert("이미지호스팅 값이 정상적이지 않습니다.", 400, 140, 'parent');
		return;
	}
	openDialogConfirm('저장소를 삭제하시겠습니까?', 500, 170,
		function () {
			$.ajax({
				url: '../setting_process/delete_imagehosting',
				data: { 'imagehosting_seq': imagehosting_seq },
				type: 'POST',
				dataType: "json",
				success: function (res) {
					if(res.auth == '100'){ // 권한 체크
						openDialogAlert('권한이 없습니다.', 400, 200, function () {});
						return;
					}
					if (res.code == '000') {
						document.location.reload();
						return;
					}
					if (res.code == '100') {
						openDialogConfirm(res.message, 400, 270, function () { document.location.href = '../setting/imagestore'; });
						return;
					}
					openDialogAlert(res.message, 400, 270, function () { document.location.reload(); });
				}
			});
		}, function () { });

}

/******************************************************/
/****************** 기능별 저장소 설정 *****************/
/******************************************************/

/*
	기능별 저장소 일괄 설정
*/
function setting_all_store() {
	var selected_val = $("select[name='imagehosting_all'] option:selected").val();
	$("select[class='imagehosting_each']").find("option[value='" + selected_val + "']").attr('selected', true);
}


/*
	기능별 저장소 설정 저장
*/
function save_imagestores() {
	openDialogConfirm('저장소 설정을 수정하시겠습니까?', 500, 170,
		function () {
			$('#imagestoreSettingForm').submit();
		}, function () { });
}


/*
	기능별 저장소 설정 저장 버튼 노출
*/
$(document).ready(function(){
	$("#save_imagestores_show").on('click', function(event) {
		$(".imagestore_save_btn").show();
	});

	$("#save_imagestores_hide").on('click', function(event) {
		$(".imagestore_save_btn").hide();
	});
});

/*
	기능별 저장소 도메인 일괄 변경 팝업 노출
*/
function popup_imagestore(imagestore_seq) {

	if(imagestore_seq == 10){ // 디자인편집>이미지넣기
		openDialogAlert("이미지넣기 기능은 스킨에 이미지가 추가되는 방식으로 일괄 변경이 불가능합니다.", 400, 140, 'parent');
		return;
	}

	if(!imagehosting_act){
		openDialogAlert('권한이 없습니다.',400,140,'parent','parent.location.reload();');
		return;
	}

	$.get('imagestore_setting?imagestore_seq=' + imagestore_seq, function (data)  {
		$('#imagestorelay').html(data);
		openDialog("도메인 일괄 변경", "imagestorelay", { "width": 700, "height": 630 });
		// 로컬저장소 기본값 세팅
		input_domain('origin');
		input_domain('change');
	});
}

/*
	기능별 저장소 도메인 일괄 변경 팝업> 이미지호스팅 선택
*/
function input_domain(mode) {
	
	store_url = $("select[name='imagestore_" + mode + "'] option:selected").val();
	if(store_url == "direct"){
		$("input[name='store_url_" + mode + "']").attr("readonly", false);
		$("input[name='store_url_" + mode + "']").val("");
	}else{
		$("input[name='store_url_" + mode + "']").attr("readonly", true);
		$("input[name='store_url_" + mode + "']").val(store_url);
	}
}


/*
	이미지호스팅 계정 수정
*/
function change_imageurl() {
	openDialogConfirm('컨텐츠내 이미지경로의 도메인을 일괄 변경하며 변경 시 복구가 불가능합니다. 변경 하시겠습니까?', 500, 170,
		function () {

			// 직접입력 시 http 혹은 https 로 시작하는지 체크
			if($("select[name='imagestore_origin'] option:selected").val() == "direct"){
				var store_url_origin = $("input[name='store_url_origin']").val();
				if(store_url_origin.substr(0,7) != "http://" && store_url_origin.substr(0,7) != "https:/"){
					openDialogAlert("변경 전 도메인 직접입력 시 http, https를 포함한 도메인을 입력해주세요.", 400, 140, 'parent');
					return;
				}
			}

			if($("select[name='imagestore_change'] option:selected").val() == "direct"){
				var store_url_change = $("input[name='store_url_change']").val();
				if(store_url_change.substr(0,7) != "http://" && store_url_change.substr(0,7) != "https:/"){
					openDialogAlert("변경 후 도메인 직접입력 시 http, https를 포함한 도메인을 입력해주세요.", 400, 140, 'parent');
					return;
				}
			}

			$('#imageUrlSettingForm').submit();

		}, function () { });
}

 