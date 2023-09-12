<?php
/**
 * 관리자 > 디자인 "스킨명", "폴더명" 수정 로직
 */
namespace App\libraries\Design\Skin;

use App\libraries\Design\Skin\SkinNameValidationTrait;

class SkinRename
{
    use SkinNameValidationTrait;

    // 스킨 저장경로
	const ROOT_SKIN_PATH = ROOTPATH . 'data/skin/';

    // codeigniter 인스턴스
    private $CI = null;

    // 이전 스킨폴더명
    private $prevSkinFolder = null;
    // 변경할 스킨명
    private $skinName = null;
    // 변경할 스킨 폴더명
    private $skinFolder = null;
    // 이름이 지정된 특별한 스킨타입
    private $skinPrefix = null;


    public function __construct()
    {
        $this->CI = &get_instance();
    }

    public function change($params)
    {
        $this->setProperty($params);

        // 스킨 & 폴더명 검사
		$checkResult = $this->validation();

		if ($checkResult['result'] === true) {
            // 스킨이름 변경
            $this->skinRename();

            // 폴더 이름 변경
            $this->skinFolderRename($this->skinPrefix);
		}

        return $checkResult;
    }

    private function setProperty($propertys)
    {
        $this->prevSkinFolder = $propertys['prevSkinFolder'];
        $this->skinName = $propertys['skinName'];
        $this->skinFolder = $propertys['skinFolder'];
        $this->skinPrefix = $propertys['skinPrefix'];
    }

    private function validation()
    {
	    // 1. 기존 스킨폴더 명칭 검사
		$checkResult = $this->folderNameValidation([
			'folderName' => $this->prevSkinFolder,
			'skinPrefix' => $this->skinPrefix,
		]);

        // 2. 신규 스킨폴더 명칭 검사
        if ($checkResult['result'] === true) {
            $checkResult = $this->folderNameValidation([
                'folderName' => $this->skinFolder,
                'skinPrefix' => $this->skinPrefix,
            ]);
        }

        // 3. 신규 스킨 명칭 검사
		if ($checkResult['result'] === true) {
            $checkResult = $this->skinNameValidation($this->skinName);
		}

        // 4. 동일한 스킨폴더 존재하는지 검사
        if (
            $this->isFolderRename() === true 
            &&$checkResult['result'] === true
        ) {
            $checkResult = $this->isSkinFolderDuplicate(self::ROOT_SKIN_PATH . $this->skinFolder);
        }

        return $checkResult;
    }

    private function skinRename()
    {
		// 스킨 폴더내 설정파일 변경
		skin_configuration_save($this->prevSkinFolder, 'name', $this->skinName);
		// 변경된 설정파일 다시 읽어 오기
		skin_configuration($this->prevSkinFolder);
    }

    
    private function isFolderRename() 
    {   
        // 폴더명이 서로 다를 경우 변경한다
        return ($this->prevSkinFolder === $this->skinFolder) ? false : true;
    } 

    private function skinFolderRename($skinPrefix)
    {   
		if ($this->isFolderRename() === true) {
			$new_skin_path = self::ROOT_SKIN_PATH . $this->skinFolder;
		    $skin_path = self::ROOT_SKIN_PATH . $this->prevSkinFolder;

			@rename($skin_path, $new_skin_path);
			@chmod($new_skin_path, 0777);

			$sql = 'update fm_config_layout set skin=? where skin=?';
			$query = $this->CI->db->query($sql, [$this->skinFolder, $this->prevSkinFolder]);

			$sql = 'update fm_design_flash set skin=? where skin=?';
			$query = $this->CI->db->query($sql, [$this->skinFolder, $this->prevSkinFolder]);

			$sql = 'update fm_design_banner set skin=? where skin=?';
			$query = $this->CI->db->query($sql, [$this->skinFolder, $this->prevSkinFolder]);

			$sql = 'update fm_design_banner_item  set skin=? where skin=?';
			$query = $this->CI->db->query($sql, [$this->skinFolder, $this->prevSkinFolder]);

			skin_configuration_save($this->skinFolder, 'skin', $this->skinFolder);

			switch ($skinPrefix) {
				case 'mobile':
					if ($this->CI->config_system['mobileSkin'] == $this->prevSkinFolder) {
						config_save('system', ['mobileSkin' => $this->skinFolder]);
					}
					if ($this->CI->config_system['workingMobileSkin'] == $this->prevSkinFolder) {
						config_save('system', ['workingMobileSkin' => $this->skinFolder]);
					}

				break;

				case 'fammerce':
					if ($this->CI->config_system['fammerceSkin'] == $this->prevSkinFolder) {
						config_save('system', ['fammerceSkin' => $this->skinFolder]);
					}
					if ($this->CI->config_system['workingFammerceSkin'] == $this->prevSkinFolder) {
						config_save('system', ['workingFammerceSkin' => $this->skinFolder]);
					}

				break;

				case 'responsive':
					if ($this->CI->config_system['skin'] == $this->prevSkinFolder) {
						config_save('system', ['skin' => $this->skinFolder]);
						config_save('system', ['mobileSkin' => $this->skinFolder]);
					}
					if ($this->CI->config_system['workingSkin'] == $this->prevSkinFolder) {
						config_save('system', ['workingSkin' => $this->skinFolder]);
						config_save('system', ['workingMobileSkin' => $this->skinFolder]);
					}

				break;

				default:
					if ($this->CI->config_system['skin'] == $this->prevSkinFolder) {
						config_save('system', ['skin' => $this->skinFolder]);
					}
					if ($this->CI->config_system['workingSkin'] == $this->prevSkinFolder) {
						config_save('system', ['workingSkin' => $this->skinFolder]);
					}

				break;
			}

		}

    }
}
