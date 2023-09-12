<?php
/**
 * 네이버 연동 클라이언트
 * 해당 클라이언트 클래스를 통해 네이버 연동 관련 기능을 제공
 * Client 추상메소드를 재정의 하여 사용
 *
 * @author pjw <pjw@gabiacns.com>
 */

namespace App\Libraries\Social\Naver;

use App\Libraries\Social\Client;
use App\Libraries\Social\ClientRequestData;
use App\Libraries\Social\Naver\Sdk\NaverService;

class NaverClient extends Client
{
	// 요청 세션
	protected $requestSession;

    public function __construct(ClientRequestData $clientRequestData)
    {
        $config = new NaverConfig($clientRequestData);
        $instance = new NaverService($config->getAllConfig());
		$this->requestSession = $clientRequestData->getSession();
        parent::__construct($config, $instance);
    }

    // 인증 URL 생성
    public function getAuthorizeUrl() {
        return $this->instance->getAuthorizeUrl();
    }

    // 인증 처리
    public function authenticate() {
		// 네이버에서 전달받은 에러 문구
		$error = $this->getErrorMessage($this->config->getConfig('error'));

		// 에러 문구를 리턴
		if($error) {
			$exception = $this->thrownException($error);
			return $exception;
		}

        // 액세스 토큰 발급
        $tokenResult = $this->getAccessToken();

        // Code 값이 없는 경우
        if (!$tokenResult['result']) {
            return $tokenResult;
        }

        // 토큰 정보 파싱
        $decodeResult = json_decode($tokenResult, true);

        // 인증 실패
        if ($decodeResult['error'] || !$decodeResult['access_token']) {
            return [
                'result' => false,
                'msg' => $this->getErrorMessage($decodeResult['error']),
            ];
        }

        // 유저정보 조회
        $accessToken = $decodeResult['access_token'];
        $tokenExpire = strtotime(date("Y-m-d H:i:s")) + ($decodeResult['expires_in'] - 3500);
        $userProfile = $this->getUserProfile($accessToken);

        // 유저 정보 조회 실패 시
        if ($userProfile['resultcode'] !== '00') {
            return [
                'result' => false,
                'msg' => '회원정보 가져오기 실패하였습니다. ',
            ];
        }

        // 네이버 프로필 정보
        $naverUser = $userProfile['response'];


        // 로그인, 회원가입에 사용할 파라미터 생성
        $naverMemberData = [
            'userid' => $naverUser['email'] ? $naverUser['email'] : $naverUser['id'], // 사용자 아이디
            'sns_n' => $naverUser['id'], // 네이버 회원고유번호(Client ID별)
            'sns_n_old'=> $naverUser['enc_id'],
            'nickname'	=> ($naverUser['nickname']) ? $naverUser['nickname'] : '-', // 닉네임이 없을 시 기본값 처리
            'user_name'=> ($naverUser['name']) ? $naverUser['name'] : $naverUser['nickname'],
            'email'	=> $naverUser['email'],
			'cellphone'	=> $naverUser['mobile'],
        ];
		// gender(sex)
		if($naverUser['gender']) {
			$naverMemberData['sex'] = $naverUser['gender'] == 'F' ? 'female' : 'male';
		}
		// birthyear-birthday 합치기
		if($naverUser['birthday'] && $naverUser['birthyear']) {
			$naverMemberData['birthday'] = $naverUser['birthyear'].'-'.$naverUser['birthday'];
		}
		
        // 신규 값을 추가하고 전체 회원 정보를 반환받는다
        $data = $this->config->addMemberData($naverMemberData);

        // 회원정보 파싱
        // 네이버 프로필 조회 시 다른 정보가 추가되어있는 경우 레거시에서 동작이 다를 것을 대비하여 정보를 맞춰준다
        $nvuser = [];
        $nvuser['nickname']		= $naverUser['nickname'];
        $nvuser['name']			= $naverUser['name'];
        $nvuser['age']			= $naverUser['age'];
        $nvuser['gender']		= $naverUser['gender'];
        $nvuser['birthday']		= $naverUser['birthday'];
        $nvuser['email']		= $naverUser['email'];
        $nvuser['enc_id']		= $naverUser['enc_id'];	// 회원고유id(네이버에서 삭제 예정)
        $nvuser['id']			= $naverUser['id'];		// Client ID별 회원고유 id

        // 세션에 저장할 데이터 생성
        $sessions = [
            'snslogn' => 'naver',
            'naver_access_token' => $accessToken,
            'naver_token_time' => $tokenExpire,
            'nvuser' => $nvuser,
        ];

        // 연동 정보를 리턴
        return [
            'result' => true,
            'data' => $data,
            'sessions' => $sessions,
        ];
    }

    // 액세스 토큰 발급
    public function getAccessToken() {
        if (!$this->config->getConfig('code')) {
            return [
                'result' => false,
                'msg' => 'code 값이 없습니다.',
            ];
        }

        $tokenResult = $this->instance->getAccessToken($this->config->getConfig('code'), $this->config->getConfig('state'));
        return $tokenResult;
    }

    // 회원 정보 조회
    public function getUserProfile($accessToken) {
        $user = $this->instance->getUser($accessToken);
        $user = $this->convertObjectToArray($user);

        return $user;
    }

    // 네이버 로그인 오류 메세지
	private function getErrorMessage($cd) {
		$msg = '';

		switch ($cd) {
			case 'session_error':
				// 인증받은 세션이 종료되었습니다.\\n새로고침 후 다시 시도해 주세요
				$msg = getAlert('mb108');
				break;
			case 'invalid_request':
				// 요청문이 정상적이지 않습니다.\\nCallback URL, Client ID, Client Key 값을 다시 한번 확인해 주세요.
				$msg = getAlert('mb109');
				break;
			case 'unauthorized_client':
				// 인증받지 않은 '인증허가코드' 입니다.\\n시스템관리자에게 문의해 주세요.
				$msg = getAlert('mb110');
				break;
			case 'unsupported_response_type':
				// 정의되어있지 않은 response type 입니다.\\n시스템관리자에게 문의해 주세요.
				$msg = getAlert('mb111');
				break;
			case 'server_error':
				// 네이버 인증서버 오류입니다.\\n시스템관리자에게 문의해 주세요.
				$msg = getAlert('mb112');
				break;
			case 'access_denied':
				// 취소되었습니다.
				$msg = getAlert('mb106');
				break;
			default:
				break;
		}

		return $msg;
	}

	// 네이버 에러 응답 핸들링
	private function thrownException($error) {
		if ($this->requestSession['mform'] === 'login') {
			$redirectUrl = '/member/login';
		} elseif ($this->requestSession['mform'] === 'join') {
			$redirectUrl = '/member/agreement';
		} else {
			$redirectUrl = $this->requestSession['returnUrl'];
		}

		return $this->errorResult($error, $redirectUrl);
	}

    // object를 array로 변환하는 함수
    private function convertObjectToArray($obj) {
		if (is_object($obj)) {
            $obj = (array) $obj;
        }

		if (is_array($obj)) {
			$new = [];
			foreach ($obj as $key => $val) {
				$new[$key] = $this->convertObjectToArray($val);
			}
		} else {
			$new = $obj;
		}

		return $new;
	}

    // 네이버에서 사용하는 세션 키 목록
    public function getSessionKeys() {
        return [
            'snslogn',
            'naver_access_token',
            'naver_token_time',
            'naver_state',
            'nvuser',
        ];
    }
}