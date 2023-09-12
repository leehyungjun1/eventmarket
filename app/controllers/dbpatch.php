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
-- FMDEV-2026_1_회원_관련_슬로우쿼리_개선.sql 실행 쿼리
ALTER TABLE fm_member MODIFY COLUMN auth_code varchar(255) NULL COMMENT '본인인증코드';

ALTER TABLE fm_member ADD INDEX idx_auth_code (auth_code);
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