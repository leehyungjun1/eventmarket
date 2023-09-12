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
-- FMDEV_2012_1_휴면탈퇴_회원_주문_분리.sql 실행 쿼리
-- 개인정보 보호 주문 리스트 테이블 생성
CREATE TABLE IF NOT EXISTS `fm_personal_info` (
	`seq` INT(11) NOT NULL AUTO_INCREMENT COMMENT '고유번호',
	`member_seq` INT(11) NOT NULL COMMENT '회원번호',
	`userid` VARCHAR(100) NULL COMMENT '사용자아이디',
	`status` ENUM('withdrawal','dormancy') NOT NULL COMMENT '상태',
	`order_seq` BIGINT(20) NOT NULL COMMENT '주문번호',
	`export_code` VARCHAR(255) NULL COMMENT '출고번호',
	`refund_code` VARCHAR(255) NULL COMMENT '환불번호',
	`return_code` VARCHAR(255) NULL COMMENT '반품번호',
	`order_date` DATETIME NOT NULL COMMENT '주문일',
	`deposit_date` DATETIME NULL COMMENT '결제일',
	`order_data` JSON NULL COMMENT '주문 개인정보 백업',
	`return_data` JSON NULL COMMENT '반품 개인정보 백업',
	`regist_date` DATETIME NULL COMMENT '등록일시',
	PRIMARY KEY (`seq`),
	KEY `order_seq` (`order_seq`),
	KEY `personal_idx` (`userid`,`member_seq`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='개인정보 보호 주문 리스트';


-- 휴면/탈퇴 회원 주문 조회 보기 권한
INSERT INTO `fm_code` (`groupcd`, `codecd`, `value`, `regist_date`) VALUES
('auth_order', 'personal_info_view', '휴면/탈퇴 회원 주문 조회', '2023-04-12 12:10:10');
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