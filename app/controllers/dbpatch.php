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
UPDATE fm_config SET VALUE='톡체크아웃(카드)', regist_date=NOW() WHERE groupcd='talkbuy_payment' AND codecd='talkbuy_card';

UPDATE fm_config SET VALUE='톡체크아웃(포인트)', regist_date=NOW() WHERE groupcd='talkbuy_payment' AND codecd='talkbuy_point';

UPDATE fm_config SET VALUE='톡체크아웃(휴대폰)', regist_date=NOW() WHERE groupcd='talkbuy_payment' AND codecd='talkbuy_cellphone';

UPDATE fm_config SET VALUE='톡체크아웃(가상계좌)', regist_date=NOW() WHERE groupcd='talkbuy_payment' AND codecd='talkbuy_virtual';


-- 게시판 이름 수정
UPDATE fm_boardmanager SET name='톡체크아웃 문의' WHERE id='talkbuy_qna' AND skin='_mbqna';
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