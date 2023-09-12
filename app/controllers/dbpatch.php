<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');
require_once(APPPATH ."controllers/base/front_base".EXT);
class dbpatch extends front_base {

	public function index(){
		redirect('dbpatch/patch');
	}
	public function patch()
	{
		ob_start();
?>
-- FMDEV_1319_1_상품옵션별_또는_창고_별_로케이션_재고_및_평균_매입_가_추출_쿼리_수정.sql 실행 쿼리
ALTER TABLE fm_member ADD INDEX idx_user_name (user_name);

ALTER TABLE fm_member_dr ADD INDEX idx_user_name (user_name);


ALTER TABLE fm_member_business ADD INDEX idx_bname (bname);

ALTER TABLE fm_member_business_dr ADD INDEX idx_bname (bname);



-- FMDEV-608_1_상품_입력옵션에_이모지_입력_안되도록_개선.sql 실행 쿼리
INSERT IGNORE INTO fm_alert (location, comment, code, alert_type, isTitle, KR_ORI, US_ORI, CN_ORI, JP_ORI, KR, US, CN, JP)
VALUES ('상품상세', '입력옵션 이모지 체크', 'gv115', 'dialog', 0,
        '이모지 입력은 불가능합니다. 입력된 이모지는 삭제됩니다.',
        'Emoji input is not possible. The emoji entered will be deleted.',
        '不能输入表情符号。 输入的图片将被删除。',
        'Emoji input is not possible. The emoji entered will be deleted.',
        '이모지 입력은 불가능합니다. 입력된 이모지는 삭제됩니다.',
        'Emoji input is not possible. The emoji entered will be deleted.',
        '不能输入表情符号。 输入的图片将被删除。',
        'Emoji input is not possible. The emoji entered will be deleted.'
        );
<?
		$sQuery = ob_get_contents();
		ob_clean();
		
		$aSql	= explode("delimiter", $sQuery);
		if(count($aSql) > 1){ // trigger 처리
			foreach($aSql as $sKeySql => $sSql){
				if($sKeySql == 0){
					$aExecQueries	= explode(';', $sSql);
				}else if($sKeySql != 0 && $sKeySql%2 == 1){
					$sSql			= substr(trim(str_replace(";//","",$sSql)),2);
					$aExecQueries	= array_merge($aExecQueries, array($sSql));
				}else{
					$arr	= explode(';', $sSql);
					$aExecQueries	= array_merge($aExecQueries, $arr);
				}
			}
		}else{
			$aExecQueries	= explode(';', $sQuery);
		}		
		foreach($aExecQueries as $query){
			if(trim($query)){
				debug_var(trim($query));
				mysqli_query($this->db->conn_id, trim($query));
			}
		}
		debug_var('OK!');
	}
}