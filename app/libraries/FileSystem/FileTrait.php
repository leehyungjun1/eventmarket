<?php
namespace App\Libraries\FileSystem;

trait FileTrait
{
	// 업로드 허용확장자
	public $ACCEPT_FILE_EXTENSION = [
		'image' => ['jpg', 'jpeg', 'png', 'gif', 'pic', 'tif', 'tiff', 'jfif', 'bmp', ],
		'document' => ['txt', 'hwp', 'docx', 'docm', 'doc', 'ppt', 'pptx', 'pptm', 'pps', 'ppsx', 'xls', 'xlsx', 'xlsm', 'xlam', 'xla'],
		'etc' => ['ai', 'psd', 'eps', 'pdf', 'ods', 'ogg', 'mp4', 'avi', 'wmv', 'zip', 'rar', 'tar', '7z', 'tbz', 'tgz', 'lzh', 'gz', 'dwg']
	];

	// 리사이징 처리하려면 기존 이름 생성방식을 사용함
	public function randomResizeFileName()
	{
		return 'temp_' . time() . sprintf('%04d', mt_rand(0, 9999));
	}

	public function randomFileName($originfileName)
	{
		if (isset($originfileName) === false) {
			return false;
		}

		$fileExtension = pathinfo($originfileName, PATHINFO_EXTENSION);

		return md5($originfileName) . substr(date('YmdHisw'), 8, 14) . '.' . $fileExtension;
	}

	public function getFileExtension($fileType)
	{
		// 허용하는 파일타입이 존재하는경우 파일 확장자
		if (isset($this->ACCEPT_FILE_EXTENSION[$fileType]) === true) {
			return $this->ACCEPT_FILE_EXTENSION[$fileType];
		}

		// 모든파일 확장자
		$extensions = [];
		if ($fileType === 'all') {
			foreach ($this->ACCEPT_FILE_EXTENSION as $extenstions) {
				$extensions = array_merge($extensions, array_map('strtolower', $extenstions));
			}
		}

		return $extensions;
	}

	public function isImage($fileType)
	{
		$fileType = explode('/', $fileType);

		$isImage = true;

		if ($fileType[0] !== 'image' || in_array($fileType[1], $this->ACCEPT_FILE_EXTENSION['image']) === false) {
			$isImage = false;
		}

		return $isImage;
	}
}
