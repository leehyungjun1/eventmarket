<?php
/**
 * 클라이언트에서 로직을 수행하기 위한 초기 설정값 지정 클래스
 *
 * @author pjw <pjw@gabiacns.com>
 */

namespace App\Libraries\Social\Naver;

use App\Libraries\Social\ClientConfig;
use App\Libraries\Social\ClientRequestData;

class NaverConfig extends ClientConfig
{
    public function __construct(ClientRequestData $clientRequestData) {
        parent::__construct($clientRequestData);
    }

    // Redirect Url 설정
    public function getRedirectUrl() {
        return get_connet_protocol().$_SERVER['HTTP_HOST'].'/sns_process/naveruserck';
    }

    // fm_config 에서 애플 관련 정보만 저장
    public function initConfig($config, $requestParameter) {
        $this->config = [
            'use_n' => $config['use_n'],
            'client_id' => $config['nid_client_id'] ?? $config['key_n'],
            'client_secret' => $config['nid_client_secret'] ?? $config['secret_n'],
            'redirect_uri' => get_connet_protocol().$config['nid_callbackurl'],
            'code' => $requestParameter['code'],
            'state' => $requestParameter['state'],
            'user' => $requestParameter['user'],
            'error' => $requestParameter['error'],
        ];
    }

    // fm_member 에서 사용 될 컬럼 목록
    public function initMemberColumnKeys() {
        $this->memberColumnKeys = [
            'rute' => 'naver',
            'prefix' => 'nv',
            'key' => 'sns_n',
            'app_key' => 'key_n',
            'use_key' => 'use_n',
        ];
    }
}