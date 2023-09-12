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
-- FMDEV-2138_1_제디터_연동.sql 실행 쿼리
ALTER TABLE `fm_goods` ADD `mobile_deploymentId` int(11) NULL COMMENT '반응형 및 전용스킨 Mobile 제디터에 대한 발행번호';

ALTER TABLE `fm_goods` ADD `deploymentId` int(11) NULL COMMENT '전용스킨 PC 제디터에 대한 발행번호';
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