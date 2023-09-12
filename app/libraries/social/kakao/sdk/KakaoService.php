<?php
//모듈 수정 원칙
//1. Kakao Response를 가급적 가공 안함
//2. 리턴타입은 Objec, Json, Echo 선택
//3. KakaoAPIService는 API 호출 스펙 정의
//4. KakaoService는 환경설정과 출력 유틸
namespace App\Libraries\Social\Kakao\Sdk;

class KakaoService extends KakaoBaseService
{
    const KAUTH = "https://kauth.kakao.com/oauth";
    const KAPI  = "https://kapi.kakao.com";
    const DAPI  = "https://dapi.kakao.com/v2";

    public function __construct($return_type="")
    {
        parent::__construct($return_type);
    }

    public function getKakaoLoginLink()
    {
        return $this->rtn(self::KAUTH . "/authorize?client_id=" . $this->REST_API_KEY . "&redirect_uri=" . $this->REDIRECT_URI . "&response_type=code&state=accessToken");
    }

    public function getKakaoLoginLinkAndReturnUrl($state)
    {
        return $this->rtn(self::KAUTH . "/authorize?client_id=" . $this->REST_API_KEY . "&redirect_uri=" . $this->REDIRECT_URI . "&response_type=code&state=".$state);
    }

    public function getAuthorizeLink($scope, $state = "accessAgree")
    {
        return $this->rtn(self::KAUTH . "/authorize?client_id=" . $this->REST_API_KEY . "&redirect_uri=" . $this->REDIRECT_URI . "&response_type=code&state=".$state."&scope=" . $scope);
    }

    public function getAutoKakaoKLoginLink()
    {
        return $this->rtn(self::KAUTH . "/authorize?client_id=" . $this->REST_API_KEY . "&redirect_uri=" . $this->REDIRECT_URI . "&response_type=code&prompt=none");
    }

    public function getKakaoWithLogOutLink()
    {
        return $this->rtn(self::KAUTH . "/logout?client_id=" . $this->REST_API_KEY . "&logout_redirect_uri=" . $this->LOGOUT_REDIRECT_URI . "&state=logout");
    }

    public function getToken()
    {
        $code = $_GET["code"]; // 서버로 부터 토큰을 발급받을 수 있는 코드를 받아옵니다.
        $callUrl = self::KAUTH . "/token?grant_type=authorization_code&client_id=" . $this->REST_API_KEY . "&redirect_uri=" . $this->REDIRECT_URI . "&code=" . $code . "&client_secret=" . $this->CLIENT_SECRET;
        return $this->excuteCurl($callUrl, "POST", [], [], "accessToken");
    }

    public function getProfile()
    {
        $callUrl = self::KAPI . "/v2/user/me";
        $headers[] = "Authorization: Bearer " . $_SESSION["kakao_access_token"];
        return $this->excuteCurl($callUrl, "POST", $headers);
    }

    public function getScopes($params)
    {
        $callUrl = self::KAPI . "/v2/user/scopes";
        $callUrl .= '?'.http_build_query($params);
        $headers[] = "Authorization: Bearer " . $_SESSION["kakao_access_token"];
        return $this->excuteCurl($callUrl, "GET", $headers);
    }

    public function getShippingAddress()
    {
        $callUrl = self::KAPI . "/v1/user/shipping_address";
        $headers[] = "Authorization: Bearer " . $_SESSION["kakao_access_token"];
        return $this->excuteCurl($callUrl, "GET", $headers);
    }

    public function getTerms()
    {
        $callUrl = self::KAPI . "/v1/user/service/terms";
        $headers[] = "Authorization: Bearer " . $_SESSION["kakao_access_token"];
        return $this->excuteCurl($callUrl, "GET", $headers);
    }

    public function setLogOut()
    {
        $callUrl = self::KAPI . "/v1/user/logout";
        $headers[] = "Authorization: Bearer " . $_SESSION["kakao_access_token"];
        return $this->excuteCurl($callUrl, "POST", $headers);
    }

    public function setLogOutForAdmin($id)
    {
        $callUrl = self::KAPI . "/v1/user/logout";
        $data = 'target_id_type=user_id&target_id=' . $id;
        $headers[] = "Authorization: KakaoAK " . $this->ADMIN_KEY;
        return $this->excuteCurl($callUrl, "POST", $headers, $data);
    }

    public function setUnLink()
    {
        $callUrl = self::KAPI . "/v1/user/unlink";
        $headers[] = "Authorization: Bearer " . $_SESSION["kakao_access_token"];
        return $this->excuteCurl($callUrl, "POST", $headers);
    }

    public function setUnLinkForAdmin($id)
    {
        $callUrl = self::KAPI ."/v1/user/unlink";
        $data = 'target_id_type=user_id&target_id=' . $id;
        $headers[] = "Authorization: KakaoAK " . $this->ADMIN_KEY;
        return $this->excuteCurl($callUrl, "POST", $headers, $data);
    }

    public function getAccessTokenInfo()
    {
        $callUrl = self::KAPI . "/v1/user/access_token_info";
        $headers[] = "Authorization: Bearer " . $_SESSION["kakao_access_token"];
        return $this->excuteCurl($callUrl, "GET", $headers);
    }

    public function setTokenRefresh()
    {
        $callUrl = self::KAPI . "/token?grant_type=refresh_token&client_id=" . $this->REST_API_KEY . "&refresh_token=" . $_SESSION["kakao_refresh_token"] . "&client_secret=" . $this->CLIENT_SECRET;
        return $this->excuteCurl($callUrl, "POST");
    }

    public function setUpdateProfile($nickname)
    {
        $callUrl = self::KAPI . "/v1/user/update_profile";
        $data = 'properties={"nickname":"' . $nickname . '"}';
        $headers = array('Content-type:application/x-www-form-urlencoded;charset=utf-8');
        $headers[] = "Authorization: Bearer " . $_SESSION["kakao_access_token"];
        return $this->excuteCurl($callUrl, "POST", $headers, $data);
    }

    public function getUserListForAdmin()
    {
        $callUrl = self::KAPI . "/v1/user/ids";
        $headers = array('Content-type:application/x-www-form-urlencoded;charset=utf-8');
        $headers[] = "Authorization: KakaoAK " . $this->ADMIN_KEY;
        return $this->excuteCurl($callUrl, "GET", $headers);
    }

    //Message
    public function sendMessage($data)
    {
        $callUrl = self::KAPI . "/v2/api/talk/memo/default/send";
        $headers = array('Content-type:application/x-www-form-urlencoded;charset=utf-8');
        $headers[] = "Authorization: Bearer " . $_SESSION["kakao_access_token"];
        return $this->excuteCurl($callUrl, "POST", $headers, $data);
    }

    public function sendScrap($request_url)
    {
        $callUrl = self::KAPI . "/v2/api/talk/memo/scrap/send";
        $headers = array('Content-type:application/x-www-form-urlencoded;charset=utf-8');
        $data = 'request_url='.urlencode($request_url);
        $headers[] = "Authorization: Bearer " . $_SESSION["kakao_access_token"];
        return $this->excuteCurl($callUrl, "POST", $headers, $data);
    }

    public function sendCustomTemplate($template_id)
    {
        $callUrl = self::KAPI . "/v2/api/talk/memo/send?template_id=".$template_id;
        $headers = array('Content-type:application/x-www-form-urlencoded;charset=utf-8');
        $headers[] = "Authorization: Bearer " . $_SESSION["kakao_access_token"];
        return $this->excuteCurl($callUrl, "POST", $headers);
    }

    public function sendMessageForFriend($receiver_uuids, $message)
    {
        $callUrl = self::KAPI . "/api/talk/memo/default/send";
        $headers = array('Content-type:application/x-www-form-urlencoded;charset=utf-8');
        $headers[] = "Authorization: Bearer " . $_SESSION["kakao_access_token"];
        $data = array($receiver_uuids);
        $data[] = $message;
        return $this->excuteCurl($callUrl, "POST", $headers, $data);
    }

    public function sendScrapForFriend($receiver_uuids, $request_url)
    {
        $callUrl = self::KAPI . "/v2/api/talk/memo/scrap/send";
        $headers = array('Content-type:application/x-www-form-urlencoded;charset=utf-8');
        $headers[] = "Authorization: Bearer " . $_SESSION["kakao_access_token"];
        $data = array($receiver_uuids);
        $data[] = 'request_url='.urlencode($request_url);
        return $this->excuteCurl($callUrl, "POST", $headers, $data);
    }

    public function sendCustomTemplateForFriend($receiver_uuids, $template_id)
    {
        $callUrl = self::KAPI . "/v2/api/talk/memo/send?template_id=".$template_id;
        $headers = array('Content-type:application/x-www-form-urlencoded;charset=utf-8');
        $headers[] = "Authorization: Bearer " . $_SESSION["kakao_access_token"];
        $data = array($receiver_uuids);
        return $this->excuteCurl($callUrl, "POST", $headers, $data);
    }

    //Local
    public function getAddress($query)
    {
        $callUrl = self::DAPI . "/local/search/address.json?query=" . urlencode($query);
        $headers[] = "Authorization: KakaoAK " . $this->REST_API_KEY;
        return $this->excuteCurl($callUrl, "GET", $headers);
    }

    public function getCoord2regioncode($x, $y)
    {
        $callUrl = self::DAPI . "local/geo/coord2regioncode.json?x=" . $x . "&y=" . $y;
        $headers[] = "Authorization: KakaoAK " . $this->REST_API_KEY;
        return $this->excuteCurl($callUrl, "GET", $headers);
    }

    public function getCoord2address($x, $y)
    {
        $callUrl = self::DAPI . "/local/geo/coord2address.json?x=" . $x . "&y=" . $y;
        $headers[] = "Authorization: KakaoAK " . $this->REST_API_KEY;
        return $this->excuteCurl($callUrl, "GET", $headers);
    }

    public function getTranscoord($x, $y, $input_coord = "WTM", $output_coord = "WGS84")
    {
        $callUrl = self::DAPI . "/local/geo/transcoord.json?x=" . $x . "&y=" . $y . "&input_coord=" . $input_coord . "&output_coord=" . $output_coord;
        $headers[] = "Authorization: KakaoAK " . $this->REST_API_KEY;
        return $this->excuteCurl($callUrl, "GET", $headers);
    }

    public function getKeywordAddress($query, $x, $y, $radius = 1000)
    {
        $callUrl = self::DAPI . "/local/search/keyword.json?query=" . urlencode($query) . "&x=" . $x . "&y=" . $y . "&radius=" . $radius;
        $headers[] = "Authorization: KakaoAK " . $this->REST_API_KEY;
        return $this->excuteCurl($callUrl, "GET", $headers);
    }

    public function getCategoryAddress($category_group_code, $x, $y, $radius = 1000)
    {
        $callUrl = self::DAPI . "/local/search/category.json?category_group_code=" . $category_group_code . "&x=" . $x . "&y=" . $y . "&radius=" . $radius;
        $headers[] = "Authorization: KakaoAK " . $this->REST_API_KEY;
        return $this->excuteCurl($callUrl, "GET", $headers);
    }
}
?>
