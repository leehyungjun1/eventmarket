<?php
if ( ! defined('BASEPATH')) exit('No direct script access allowed');
/**
 * 비디오 커머스 API 에서 사용하는 Trait
 * controller 소스에는 API 함수만 포함하고 기능적인 private함수는 이곳에 포함
 *
 * 2019-12-06
 */
trait BroadcastTrait
{
    /**
     * 방송 편성표 등록 폼 검증
     */
    private function registValid($params, $grant='admin') {
        $this->load->library('api_validation');
        // $this->validation->set_data($params);
        if(count($_POST)<=0 && count($params)>0) {
            $_POST = $params;
		}

		// 폼 검증
		$this->api_validation->set_rules('title', '방송제목', 'required');
		$this->api_validation->set_rules('provider_seq', '입점사', 'required');
		$this->api_validation->set_rules('manager_seq', '관리자', 'required');
		$this->api_validation->set_rules('broadcastGoods[]', '상품', 'required');
		if($grant == 'admin') {
			$this->api_validation->set_rules('image', '대표 이미지', 'required');
			$this->api_validation->set_rules('start_date_day', '시작일자', 'required');
			$this->api_validation->set_rules('start_date_hour', '시작시간(시)', 'required|greater_than_equal_to[0]|less_than_equal_to[23]');
			$this->api_validation->set_rules('start_date_min', '시작시간(분)', 'required|max_length[2]|min_length[2]');
		} else {
			$this->api_validation->set_rules('start_date', '시작시간', 'required');
		}

        if($this->api_validation->exec()===false){
            $err = $this->api_validation->error_array;
            $data = array(
                'elementName' => $err['key'],
                'message' => $err['value'],
            );
            return array('result'=>false, 'data'=>$data);
		}

		// 날짜 포맷 변환
		if( !isset($params['start_date'])) {
			$params['start_date'] = date( 'Y-m-d H:i:s', strtotime( $params['start_date_day'].$params['start_date_hour'].":".$params['start_date_min'] ) );
		}

		// 승인여부
		if(!isset($params['approval'])) {
			if(intval($params['provider_seq']) >1) {
				$params['approval'] = 'regist';
			} else {
				$params['approval'] = 'apply';
			}
		}

		$start_date = new DateTime($params['start_date']);
		$now_date = new DateTime(date("Y-m-d H:i:s"));
		if($start_date < $now_date) {
			$data = array(
                'elementName' => 'start_date_day',
                'message' => '방송일은 현재 시간 이후로 설정해주세요.',
            );
            return array('result'=>false, 'data'=>$data);
		}

        return array('result'=>true, 'data'=>$params);

	}

	/**
	 * 방송 대표이미지 업로드
	 * 송출앱에서는 FORM 태그로 전송받기 때문에 $_FILES 로 업로드
	 * 파일 경로는 /data/broadcast/업로드연도/업로드월 (한 폴더에 대량 파일 방지)
	 * 
	 * @param string $bsSeq 방송 SEQ
	 * @return string 업로드 된 경로 반환
	 */
	private function uploadImage($bsSeq)
	{
		// admin > post['image] , app > files
		$this->load->library('upload');

		$uriPath = '/data/broadcast/' . date('Y') . '/' .date('m');
		$uploadPath = ROOTPATH . $uriPath;
		$this->makePath($uploadPath);

		$config['upload_path'] = $uploadPath;
		$config['allowed_types'] = implode('|', array('jpg','jpeg','png','gif','bmp','tif','pic'));
		$config['max_size']	= $this->config_system['uploadLimit'];
		$config['file_name'] = "banner_".$bsSeq;

		$this->upload->initialize($config, true);
		if (!$this->upload->do_upload('image')) {
			$fileUploadErr = [
				'msg' => '방송 이미지 업로드 오류 발생',
				'error' => $this->upload->display_errors(),
			];
			writeCsLog($fileUploadErr, "broadcast" , "api", "hour");
			return '';
		}

		return $uriPath . '/' . $this->upload->file_name;
	}

	/**
	 * 방송 대표이미지 이동
	 * 관리자에서는 ajax로 이미 업로드해서 tmp 경로에 담기기 때문에 해당 경로 이미지를 방송 이미지 경로로 변경하여 이동처리
	 * 파일 경로는 /data/broadcast/업로드연도/업로드월 (한 폴더에 대량 파일 방지)
	 * 
	 * @param string $bsSeq 방송 SEQ
	 * @param string $originPath 이동 시킬 이미지 경로
	 * @return string 업로드 된 경로 반환
	 */
	private function moveImage($bsSeq, $originPath)
	{
		$targetPath = '/data/broadcast/' . date('Y') . '/' .date('m');
		$targetFileExt = end(explode('.', $originPath));
		$targetFileName = '/banner_' . $bsSeq . '.' .$targetFileExt;
		$targetFullPath = $targetPath . $targetFileName;
		$targetAbsolutePath = ROOTPATH . $targetPath;
		$targetAbsoluteFullPath = ROOTPATH . $targetFullPath;
		$originAbsoluteFullPath = ROOTPATH . $originPath;

		$this->makePath($targetAbsolutePath);
		if (!file_exists($originAbsoluteFullPath)) {
			$fileUploadErr = ['msg' => '원본 이미지 없음',];
			writeCsLog($fileUploadErr, "broadcast" , "api", "hour");
			return '';
		}

		if (!copy($originAbsoluteFullPath, $targetAbsoluteFullPath)) {
			$fileUploadErr = ['msg' => '원본 이미지 이동 실패',];
			writeCsLog($fileUploadErr, "broadcast" , "api", "hour");
			return '';
		}

		unlink($originAbsoluteFullPath);
		return $targetFullPath;
	}

	/**
	 * 파일 업로드 처리 
	 * 요청 타입에 따라 업로드인지 이동인지 다르며, 이미지 처리 후 DB에 이미지 경로를 업데이트 한다.
	 * 
	 * @param mixed $params 통합 파라미터
	 * @param string $grant 요청 타입 {admin: 관리자, app: 송출앱}
	 * @return bool
	 */
	private function imageUploadProcess($params, $grant) {
		$imageName = false;
		$bsSeq = $params['bsSeq'];

		if ($grant == 'admin' && preg_match("/^\/?data\/tmp/i",$params['image'])) {
			$imageName = $this->moveImage($bsSeq, $params['image']);
		} else if ($grant == 'app' && is_array($_FILES['image'])) {
			$imageName = $this->uploadImage($bsSeq);
		}

		if (!$imageName) {
			return false;
		}

		// db 업데이트
		$this->updateBroadcastImage($bsSeq, $imageName);
		return true;
	}

	/*
	* 업로드 하기 전 폴더 있는지 체크 및 폴더 생성
	*/
	private function makePath($newpath) {
		$path_map = explode("/", $newpath);

		$path_tmp = '';
		foreach($path_map as $path) {
			$path_tmp .= $path."/";
			if(!file_exists($path_tmp)){
				@mkdir($path_tmp);
				@chmod($path_tmp,0777);
			}
		}
	}

	/*
	* 파일 삭제하기
	*/
	private function unlinkBroadcastImage($path) {
		$unlink = ROOTPATH."/".$path;
		unlink($unlink);
	}

	/**
     * 방송 연결를 업데이트하는 멤버 함수
     */
    private function disconnectUpdate()
    {
		$bsSeq = $this->uri->segment(4);
		$disconnect = $this->uri->segment(5);

        if(empty($bsSeq) || !isset($disconnect)) {
            return $this->response404();
        }

		$sch = $this->broadcastmodel->getSch(array('bs_seq' => $bsSeq));
		$sch = reset($sch);
		if(!is_array($sch)) {
			$this->response(array(
				'success' => false,
				'code' => "not_found",
				'message' => "존재하지 않는 방송입니다.",
			), parent::HTTP_NOT_FOUND);
		}
		$this->db->trans_begin();

		$result = $this->broadcastmodel->setBroadcastDisconnect($bsSeq, $disconnect);

		if ($this->db->trans_status() === false)
        {
            $this->db->trans_rollback();
            return false;
        }
		$this->db->trans_commit();
		return true;
	}

    /**
     * 방송 상태를 업데이트하는 멤버 함수
     * @param string $status : ['live','end']
     */
    private function statusUpdate($action)
    {
		// 방송 상태 (이전)
		$statusBefore = array(
			'live' => 'create',
			'end' => 'live'
		);


		$bsSeq = $this->uri->segment(4);

        if(empty($bsSeq) || !in_array($action, array_keys($this->broadcastmodel->cfg_status))) {
            return $this->response404();
        }

		$sch = $this->broadcastmodel->getSch(array('bs_seq' => $bsSeq));
		$sch = reset($sch);
		if(empty($sch['status']) || $sch['status'] !== $statusBefore[$action]) {
			$this->response(array(
				'success' => false,
				'code' => "not_" . $statusBefore[$action],
				'message' => "'{$this->broadcastmodel->cfg_status[$statusBefore[$action]]}' 상태가 아닙니다.",
			), parent::HTTP_NOT_FOUND);
		}
		$this->db->trans_begin();

		// vod 서버랑 통신 후 db 상태 변경함
		$this->load->library('broadcast/stream');
		$func = ucfirst($action);
		$result = $this->{"statusUpdate{$func}"}($sch);

		if ($this->db->trans_status() === false)
        {
            $this->db->trans_rollback();
            return false;
        }
		$this->db->trans_commit();
		return $result;
	}

    /**
     * 방송 시작 시 vod 서버 방송 생성 및 시작 처리
     * @param array $sch
     */
	protected function statusUpdateLive($sch) {
		$params = array();
		$params['title'] = $sch['title'];
		$params['shop_broadcast_seq'] = $sch['bs_seq'];
		// 강제 종료 시 콜백 처리
		$params['callback_url'] = 'https://'.$_SERVER['HTTP_HOST']."/api/broadcast/endforce/".$sch['bs_seq'];
		$streamdata = $this->stream->sendMethod("addBroadcast",$params);

		if(!isset($streamdata['channel'])) {
			$this->response(array(
				'code'  =>  'stream_error',
				'message'   =>  '[vod]'.$streamdata['msg'],
			), parent::HTTP_FORBIDDEN);
		}

		// vod(stream) 키 등록
		$result = $this->broadcastmodel->setStreamKey($sch['bs_seq'], $streamdata['channel'], $streamdata['origin_server']);

		if($result !== true) {
			$this->response(array(
				'code'  =>  'not_update',
				'message'   =>  '방송 등록 실패',
			), parent::HTTP_FORBIDDEN);
		}

		$path = array();
		$path['channel'] = $streamdata['channel'];
		$path['status'] = 'onair';
		$statusdata = $this->stream->sendMethod("modifyChannelStatus",array(), $path);
		if(!isset($statusdata['channel'])) {
			$this->response(array(
				'code'  =>  'not_update',
				'message'   =>  '[vod]'.$statusdata['msg'],
			), parent::HTTP_FORBIDDEN);
		}

		// 방송 상태 update
		$result = $this->broadcastmodel->setBroadcastStatus($sch['bs_seq'], "live");
		$result = $this->broadcastmodel->setBroadcastDatetime($sch['bs_seq'],"real_start_date");

		if($result !== true) {
			$this->response(array(
				'code'  =>  'not_update',
				'message'   =>  '방송 시작 실패',
			), parent::HTTP_FORBIDDEN);
		}

		$result = array(
			'success' => true,
			'data' => array(
				'channel' => $streamdata['channel'],
				'origin_server' => $streamdata['origin_server']
			)
		);
		return $result;
	}

    /**
     * 방송 종료 시 vod 서버 종료 처리
     * @param array $sch
     */
	protected function statusUpdateEnd($sch) {
		$is_save = $this->put('isSave');

		$path = array();
		$path['channel'] = $sch['stream_key'];
		$path['status'] = 'end';

		$param = array();
		$param['is_save'] = isset($is_save) ? $is_save : '1';
		$param['callback_url'] = 'https://'.$_SERVER['HTTP_HOST']."/api/broadcast/callback/".$sch['bs_seq']; // 실제 종료 후 콜백 처리 리턴

		$statusdata = $this->stream->sendMethod("modifyChannelStatus",$param, $path);
		// vod 에서 방송종료 못 시킨 경우
		if(!isset($statusdata['channel'])) {
			// 그럼 일단 방송 상태 체크한다
			$vod_sch = $this->stream->sendMethod("getBroadcast",array(),array('channel'=>$path['channel']));
			// 이미 vod에서 종료되었다면 강제 종료 변경
			if($vod_sch['status'] == 'end') {
				//솔루션 db update
				$data['is_save'] = $vod_sch['is_save'];
				if($vod_sch['is_save']=='1') {
					$data['vod_key'] = $vod_sch['channel'];
				}
				$data['status'] = $vod_sch['status'];
				$data['real_end_date'] = $vod_sch['completed_at'];
				$result = $this->broadcastmodel->updateBroadcast($data,$sch['bs_seq']);
			} else {
				// 다른 사유라면 그대로 노출
				$this->response(array(
					'code'  =>  'not_update',
					'message'   =>  '[vod]'.$statusdata['msg'],
				), parent::HTTP_FORBIDDEN);
			}
		} else {
			// 정상 방송 종료
			// 방송 상태 update
			$result = $this->broadcastmodel->setBroadcastStatus($sch['bs_seq'], "end");
			$result = $this->broadcastmodel->setBroadcastDatetime($sch['bs_seq'],"real_end_date");
			$result = $this->broadcastmodel->updateBroadcast(array('is_save'=>$param['is_save']),$sch['bs_seq']);
		}

		if($result !== true) {
			$this->response(array(
				'code'  =>  'not_update',
				'message'   =>  '방송 종료 실패',
			), parent::HTTP_FORBIDDEN);
		}

		$result = array(
			'success' => true,
			'data' => array(
				'channel' => $sch['stream_key'],
				'origin_server' => $sch['origin_server']
			)
		);
		return $result;
	}

	/**
	 * 방송 종료 시 callback update
	 */
	protected function statsUpdate($bsSeq) {
		$this->load->model('broadcastmodel');
		$post = $this->input->post();

		if($post['is_save']=='1') {
			$params['vod_key'] = $post['vod_channel'] ? $post['vod_channel'] : $post['channel'];
		}
		$params['views'] = $post['views'];
		$params['likes'] = $post['likes'];
		$params['visitors'] = $post['visitors'];
		$params['traffics'] = $post['traffics'];
		$params['status'] = 'end';

		$this->db->trans_begin();

		$updateRes = $this->broadcastmodel->updateBroadcast($params, $bsSeq);
		if($updateRes !== true) {
            $this->db->trans_rollback();
            return false;
		}
        if ($this->db->trans_status() === false)
        {
            $this->db->trans_rollback();
            return false;
        }
        $this->db->trans_commit();
        return true;
	}

	/**
	* 좋아요 클릭 시
	*/
	public function likes($channel) {
		$res = array('success'=>false);
		$this->load->library('broadcast/stream');
		$result = $this->stream->sendMethod("channelLikes",array('channel'=>$channel),array('channel'=>$channel));
		if($result['channel']) {
			$res = $result;
			$res['success'] =  true;
		}
		return $res;
	}

	/**
	 * vod 방송 정보 조회
	 */
	public function info($channel, $bs_seq) {
		$res = array('success'=>false);
		$this->load->library('broadcast/stream');
		$result = $this->stream->sendMethod("getBroadcast",array(),array('channel'=>$channel));
		if($result['channel']) {
			$res = $result;
			$res['success'] =  true;

			// 넘어온 데이터로 visitors,views,likes 저장
			if($result['status'] == 'end') {
				$params = array();
				$params['visitors'] = $result['visitors'];
				$params['views'] = $result['views'];
				$params['likes'] = $result['likes'];
				$params['traffics'] = $result['traffics'];
				$params['vodviews'] = $result['vods']->views;
				$params['vodvisitors'] = $result['vods']->visitors;
				$this->broadcastmodel->updateBroadcastInfo($params, $bs_seq);
			} else if($result['status'] == 'onair') {
				// 조회 수는 방송중에도 업뎃
				$params['vodvisitors'] = $result['visitors'];
				$this->broadcastmodel->updateBroadcastInfo($params, $bs_seq);
			}
		}
		return $res;
	}

	/**
	 * vod chat 읽기
	 */
	public function vodchat($channel, $bs_seq) {
		$res = array('success'=>false);
		$this->load->library('broadcast/stream');
		$result = $this->stream->sendMethod("getVodChat",array(),array('channel'=>$channel));
		if(!isset($result['httpCode'])) {
			$res = $result;
		} else {
			$this->response(array(
				'code'  =>  'not_read',
				'message'   =>  '[vod]'.$result['msg'],
			), parent::HTTP_FORBIDDEN);
		}
		return $res;
	}

    /**
     * 방송 노출 상태를 업데이트하는 멤버 함수
     */
    private function displayUpdate()
    {
        $bsSeq = $this->uri->segment(3);
        $display = $this->put('display');

        if(empty($display) || empty($bsSeq)) {
            return $this->response404();
        }

        // 관리자 권한 체크
        $this->load->model('managermodel');
		$this->load->model('broadcastmodel');
		$managerInfo = $this->session->userdata('manager');
		$providerInfo = $this->session->userdata('provider');
        if(!empty($managerInfo['manager_seq'])) {
            // 권한 체크
            $this->load->library('broadcast/permit');
            $this->permit->setManagerSeq($managerInfo['manager_seq']);

            $result = $this->broadcastmodel->setBroadcastDisplay($bsSeq, $display);
            if($result === true) {
				// 로깅
				$logParams = array(
					'provider_seq' => $providerInfo['provider_seq'] ? $providerInfo['provider_seq'] : 1,
					'manager_seq' => $managerInfo['manager_seq'],
					'memo' => '방송 노출 상태 변경',
					'device' => $this->check(array('admin','app'))
				);
                // db logging
                $this->load->library('broadcast/logs', $logParams);
                $this->logs->logging($display, $bsSeq);
                $res = array(
                    'success' => true,
                );
                $this->response($res, parent::HTTP_OK);
            } else {
                $this->response(array(
                    'code' => "internal_server_error",
                    'message' => "서버 오류입니다.",
                ), parent::HTTP_INTERNAL_ERROR);
            }
        }

        $this->response(array(
            'code'  =>  'not_allowed',
            'message'   =>  '권한이 없습니다.',
        ), parent::HTTP_FORBIDDEN);
    }

    /**
     * 라이브 사용 유무를 설정함
     */
    protected function useUpdate()
    {
		$use = $this->put('use');
        if(empty($use)) {
            return $this->response404();
		}
		$config_system = config_load('system');

		// vod 계정 있는지 체크
		$res = setBroadcast();

		if($res['success'] == 'OK') {
			// vod 계정 있으면 상태변경
			$res = array(
				'success' => true,
			);
		} else {
			// vod 계정 있으면 상태변경
			$res = array(
				'success' => false,
			);
		}

		$this->response($res, parent::HTTP_OK);
	}

    /**
     * 방송-편성표
     * 편성표 조회
     *
     * @param date : (Optional) 조회일(yyyymmdd) (default:오늘날짜)
     * @param status : (Optional) 방송 상태(schedule|standby...) (default:schedule)
     */
    private function catalog($param=array())
    {
		$this->load->model('broadcastmodel');
		$this->load->helper('broadcast');

		// param 데이터 우선 param 데이터 없을 때 get
		if(empty($param)) {
			$sc = $this->input->get();
			$sc['perpage']			= (!empty($sc['perpage'])) ? intval($sc['perpage']):10;
			$sc['page']				= (!empty($sc['page'])) ? intval($sc['page']):0;
			$sc['searchcount']		= true;
			if($sc['search_text'] && !$sc['search_field']) {
				$sc['search_field'] = 'all';
			}
			$param = $sc;
		}
		$result = $this->broadcastmodel->getSch($param);

		$page['searchcount'] = $this->broadcastmodel->getSchCount();
		$page['totalcount'] = $this->broadcastmodel->getSchTotal($param['is_save']);
		$page['html'] = pagingtag($page['searchcount'], $param['perpage'],getPageUrl($this->file_path).'?', getLinkFilter('',array_keys($param)) );
		if($param['select_status']) {
			$nowpage = ceil($param['page']/$param['perpage'])+1;

			$totalPage			= ceil($page['totalcount']/$param['perpage']);
			$nowEndPage			= ($nowpage <= $param['perpage']) ? $param['perpage'] : (ceil($nowpage / $param['perpage'])) * $param['perpage'];
			$nowStartPage		= ($nowpage < $param['perpage']) ? 1 : $nowEndPage - $param['perpage'] + 1;
			$nowEndPage			= ($nowEndPage > $totalPage) ?  $totalPage :  $nowEndPage;
			$pages				= array();

			for($i = $nowStartPage; $i <= $nowEndPage; $i ++)
				$pages[]		= $i;

			$page['html'] = pagingtagjs($nowpage, $pages, round( $page['searchcount'] / $param['perpage']), 'broadcastSearchPaging([:PAGE:])');
		}
		$page['html'] = (!empty($page['html'])) ? $page['html']:'<p><a class="on red">1</a><p>';
		$no	= $page['searchcount'] - ( $param['page'] / $param['perpage'] * $param['perpage'] );

		// broadcast_helper 참조
		broadcastlist($result, $no);

		return array(
			'result' => $result,
			'page' => $page
		);
    }


    /**
     * 방송-편성표
     * 단일건 조회
     * 접근 권한 : admin
     * @param int $bsSeq
     */
    private function view($bsSeq)
    {
        $this->load->model('broadcastmodel');
		$this->load->model('goodsmodel');
		$this->load->model('managermodel');
		$this->load->model('providermodel');

        $sch = $this->broadcastmodel->getSch(array('bs_seq' => $bsSeq));
        if(count($sch)>0) {
			$sch = reset($sch);

			// 이미지 수정 시 초기화 필요
			$sch['image'] .= "?dummy=".time();

			// 관리자 정보
			$managerInfo = $this->managermodel->get_manager($sch['manager_seq']);
			$sch['mname'] = $managerInfo['mname'];
			$sch['manager_id'] = $managerInfo['manager_id'];

			// 입점사 정보
			$providerInfo = $this->providermodel->get_provider_one($sch['provider_seq']);
			$sch['provider_name'] = $providerInfo['provider_name'];

			$bsGoods = $this->broadcastmodel->getBroadcastGoods(array('bs_seq'=>$bsSeq));
            if(count($bsGoods)>0) {
                $sch['goodsData'] = array();
                $goodsSeqs = array();
                $bsGoodsData = array();
                foreach($bsGoods as $bsGoodsRow) {
                    $bsGoodsData[$bsGoodsRow['goods_seq']] = $bsGoodsRow;
                    $goodsSeqs[] = $bsGoodsRow['goods_seq'];
                }
                $goodsTmp = $this->broadcastmodel->getGoodsList($goodsSeqs);
                $goodsRes = array();
                if(count($goodsTmp)>0) {
                    foreach($goodsTmp as $goodsTmpRow) {
                        $goodsRes[$goodsTmpRow['goods_seq']] = $goodsTmpRow;
                    }
                }
                if(count($goodsSeqs)>0) {
                    $this->load->helper('common');
                    foreach($goodsSeqs as $goodsSeq) {
						$goodsData = array();

						$goodsData['goods_seq'] = $goodsSeq;

						// broadcast 데이터
						$goodsData['broadcastGoodsMain'] = $bsGoodsData[$goodsSeq]['main'];

						// goods 데이터
						$goodsData['goods_code'] = $goodsRes[$goodsSeq]['goods_code'];
						$goodsData['goods_name'] = $goodsRes[$goodsSeq]['goods_name'];
						$goodsData['goods_img'] = viewImg($goodsSeq,'thumbView');
						$goodsData['goods_kind'] = $goodsRes[$goodsSeq]['goods_kind'];
						$goodsData['provider_name'] = $goodsRes[$goodsSeq]['provider_name'];

						$goodsData['sale_rate'] = 0;
						if((int)$goodsRes[$goodsSeq]['default_consumer_price'] > (int)$goodsData[$goodsSeq]['default_price']) {
							$goodsData['sale_rate'] = 100 - floor(( $goodsRes[$goodsSeq]['default_price'] / $goodsRes[$goodsSeq]['default_consumer_price'] ) * 100);
						}

						$goodsData['default_price'] = get_currency_price($goodsRes[$goodsSeq]['default_price'], 4);
                        $sch['goodsData'][] = $goodsData;
                    }
                }
			}

			if($sch['real_start_date'] && $sch['real_end_date']) {
				$real_time = date_diff(date_create($sch['real_end_date']),date_create($sch['real_start_date']));
				$sch['real_time'] = str_pad($real_time->h,2,0,STR_PAD_LEFT).":".str_pad($real_time->i,2,0,STR_PAD_LEFT).":".str_pad($real_time->s,2,0,STR_PAD_LEFT);
			}


			// 다운로드 링크
			$sch['download'] = '';
			if($sch['vod_key']) {
				$sch['download'] = 'https://vod.firstmall.kr/download/'.$sch['vod_key'];
			}

			// 모바일앱인 경우 본창 그외는 새창
			$sch['link_target'] = '_blank';
			if($this->_is_mobile_app_agent) {
				$sch['link_target'] = '_self';
			}

        }
        return $sch;
    }

    /**
     * 방송 편성표 생성
     * @param array @params
     */
    private function createBroadcast($params ,$grant='admin')
    {
        $params['regist_date'] = date("Y-m-d H:i:s");

        $this->load->model("broadcastmodel");
        $this->db->trans_begin();
        $bsSeq = $this->broadcastmodel->insertBroadcast($params);

		$params['bsSeq'] = $bsSeq;

		// 대표이미지 업로드 / db update
		$this->imageUploadProcess($params, $grant);
		
		// 상품 정보
		if(is_array($params['broadcastGoods']) === false) {
			$params['broadcastGoods'] = explode("|",$params['broadcastGoods']);
		}
        if(count($params['broadcastGoods'])>0) {
            $this->load->model('goodsmodel');
            $this->load->helper('broadcast');
            $goodsParams = array();
            $sort = 0;
            foreach($params['broadcastGoods'] as $goods_seq) {
				$goodsParam = getGoodsParam($goods_seq, $params['broadcastGoodsMain']);
                $goodsParams[] = $goodsParam;

            }
        }
        if(count($goodsParams)>0 && !empty($bsSeq)) {
            foreach($goodsParams as $goodsParam) {
                $insertRes = $this->broadcastmodel->insertBroadcastGoods($goodsParam, $bsSeq);
                if(empty($insertRes)) {
                    $this->db->trans_rollback();
                    return false;
                }
            }
        }

        if ($this->db->trans_status() === false)
        {
            $this->db->trans_rollback();
            return false;
        }
        $this->db->trans_commit();
        return $bsSeq;
	}

	/**
	 * 방송 이미지 경로 db 업데이트 처리
     * @param int $bsSeq
     * @param string $image
	 */
	private function updateBroadcastImage($bsSeq, $image) {
		// 단순 이미지 path 수정
        if(empty($bsSeq) || empty($image)) {
            return array('result'=>false);
		}
		$params['image'] = $image;
        // 방송 편성표 업데이트
        $this->broadcastmodel->updateBroadcast($params, $bsSeq);
	}

    /**
     * 방송 편성표를 수정한다.
     * @param int $bsSeq
     * @param array $params
     */
    private function modifyBroadcast($bsSeq, $params, $grant = "admin")
    {
        if(empty($bsSeq)) {
            return array('result'=>false);
        }
        $this->load->model("broadcastmodel");
        $sch = $this->broadcastmodel->getSch(array('bs_seq' => $bsSeq));
        if(empty($sch)) {
            return array('result'=>false);
        }
        $sch = reset($sch);
        if($sch['status'] !== 'create') { // 방송 상태가 방송예정이 아니면 수정 불가
            return array('result'=>false, 'err' => array('code'=>'not_schedule', 'message'=> '방송 예약 중인 경우에 수정이 가능합니다.'));
        }

        $this->db->trans_begin();

		// 방송 편성표 업데이트
        $updateRes = $this->broadcastmodel->updateBroadcast($params, $bsSeq);

        if($updateRes !== true) {
            $this->db->trans_rollback();
            return array('result'=>false);
		}

		// 대표이미지 업로드 / db update
		$params['bsSeq'] = $bsSeq;
		$this->imageUploadProcess($params, $grant);

        // 방송 편성표 상품 삭제
        $deleteRes = $this->broadcastmodel->deleteBroadcastGoods($bsSeq);

        if($deleteRes !== true) {
            $this->db->trans_rollback();
            return array('result'=>false);
        }

		// 상품 정보
		if(is_array($params['broadcastGoods']) === false) {
			$params['broadcastGoods'] = explode("|",$params['broadcastGoods']);
		}
        if(count($params['broadcastGoods'])>0) {
            $this->load->model('goodsmodel');
            $this->load->helper('broadcast');
            $goodsParams = array();
            $sort = 0;
            foreach($params['broadcastGoods'] as $goods_seq) {
				$goodsParam = getGoodsParam($goods_seq, $params['broadcastGoodsMain']);
                $goodsParams[] = $goodsParam;

            }
        }
        if(count($goodsParams)>0 && !empty($bsSeq)) {
            foreach($goodsParams as $goodsParam) {
                $insertRes = $this->broadcastmodel->insertBroadcastGoods($goodsParam, $bsSeq);
                if(empty($insertRes)) {
                    $this->db->trans_rollback();
                    return false;
                }
            }
        }

        if ($this->db->trans_status() === false)
        {
            $this->db->trans_rollback();
            return array('result'=>false);
        }
        $this->db->trans_commit();
        return array('result'=>true);
    }

    /**
     * 방송 편성표 및 상품을 삭제한다.
     * @param int $bsSeq
     */
    private function dropBroadcast($bsSeq)
    {
        $this->load->model("broadcastmodel");
        $sch = $this->broadcastmodel->getSch(array('bs_seq' => $bsSeq));
        if(empty($sch)) {
            return array('result'=>false);
        }
		$sch = reset($sch);
        if(!in_array($sch['status'],array('create','cancel'))) { // 방송 상태 생성 일때만 삭제 가능
            return array('result'=>false, 'err' => array('code'=>'not_schedule', 'message'=> '방송 예정 상태에서만 삭제가 가능합니다.'));
        }

        $this->db->trans_begin();

        $bsRes = $this->broadcastmodel->deleteBroadcast($bsSeq);
        if($bsRes !== true) {
            $this->db->trans_rollback();
            return array('result'=>false);
        }
        $bsGoodsRes = $this->broadcastmodel->deleteBroadcastGoods($bsSeq);
        if($bsGoodsRes !== true) {
            $this->db->trans_rollback();
            return array('result'=>false);
		}

		// 파일 삭제
		$this->unlinkBroadcastImage($sch['image']);

        if ($this->db->trans_status() === false)
        {
            $this->db->trans_rollback();
            return array('result'=>false);
        }
        $this->db->trans_commit();
        return array('result'=>true);
	}

	/**
     * 방송 편성표 상태를 delete 로 변경
     * @param int $bsSeq
     */
    private function deleteBroadcast($bsSeq)
    {
		$this->load->model("broadcastmodel");

		$sch = $this->broadcastmodel->getSch(array('bs_seq' => $bsSeq));
        if(empty($sch)) {
            return array('result'=>false);
        }
        $sch = reset($sch);

		$this->db->trans_begin();

		// vod_key 가 있으면 stream 서버의 상태도 변경필요
		if($sch['vod_key']) {
			$this->load->library('broadcast/stream');
			$streamdata = $this->stream->sendMethod("deleteVod",array(),array('channel'=>$sch['stream_key']));
			if(!isset($streamdata['channel'])) {
				$this->response(array(
					'code'  =>  'stream_error',
					'message'   =>  '[vod]'.$streamdata['msg'],
				), parent::HTTP_FORBIDDEN);
			}
		}
		$result = $this->broadcastmodel->updateBroadcast(array('vod_key'=>null),$sch['bs_seq']);
		$bsRes = $this->broadcastmodel->setBroadcastStatus($bsSeq,"delete");

        if($bsRes !== true) {
            $this->db->trans_rollback();
            return array('result'=>false);
		}

        if ($this->db->trans_status() === false)
        {
            $this->db->trans_rollback();
            return array('result'=>false);
        }
        $this->db->trans_commit();
        return array('result'=>true);
	}
	/**
	 * 방송 생성 시 상품 조회
	 */
	private function searchGoods() {

		$this->load->model("goodsmodel");

		$sc = $this->input->get();
		$sc['page']	 		= ($sc['page']) ? intval($sc['page']):'1';
		$sc['perpage'] 		= ($sc['perpage']) ? intval($sc['perpage']):'10';
		$sc['goods_type']	= 'goods';
		$sc['searchcount']	= true;
		$sc['provider_seq'] = ($sc['provider_seq']) ? intval($sc['provider_seq']):'1';
		$sc['goodsStatus']  = array('normal');

		if($sc['keyword'] && !$sc['search_type']) {
			$sc['search_type'] = 'goods_name';
		}

		$result = $this->goodsmodel->admin_goods_list_new($sc);

		$goodsData['page'] = $result['page'];
		foreach($result['record'] as $row) {
			$data = array();
			$data['goods_seq'] = $row['goods_seq'];
			$data['goods_name'] = $row['goods_name'];
			$data['provider_name'] = $row['provider_name'];
			$data['default_price'] = get_currency_price($row['default_price'], 4);
			$data['sale_rate'] = 0;
			if((int)$row['default_consumer_price'] > (int)$row['default_price']) {
				$data['sale_rate'] = 100 - floor(( $row['default_price'] / $row['default_consumer_price'] ) * 100);
			}

			$data['goods_img'] = viewImg($row['goods_seq'],'thumbView');
			$goodsData['record'][] = $data;
		}

		return $goodsData;
	}

	/**
	 * 채팅
	 */
	protected function sendChat($param,$channel) {
		$res = array('success'=>false);
		$this->load->library('broadcast/stream');
		$result = $this->stream->sendMethod("sendChat",$param,array('channel'=>$channel));
		if($result['channel']) {
			$res = $result;
			$res['success'] =  true;
		}
		return $res;
	}

	/**
	 * 상품상세 유입 체크
	 */
	protected function goodsStats($param) {
		$this->load->model("broadcastmodel");
		$this->broadcastmodel->updateGoodsStats($param);
	}

	/**
	 * vod서버 user 계정 리턴
	 * @param with_used_vod : vod 사용량 조회 여부
	 */
	protected function getUserInfo($with_used_vod = false) {
		$this->load->helper('broadcast');
		$config = getBroadcastConf();

		$this->load->library('broadcast/stream');
		$streamdata = $this->stream->sendMethod("getUserInfo",array('with_used_vod' => $with_used_vod),array('username'=>$config['username']));
		if(!isset($streamdata['username'])) {
			$this->response(array(
				'code'  =>  'stream_error',
				'message'   =>  '[vod]'.$streamdata['msg'],
			), parent::HTTP_FORBIDDEN);
		}

		return $streamdata;
	}

	/**
	 * 라이브커머스 통신 서버 정보 전역변수 세팅
	 */
	protected function initBroadcastServerConfig() {
		$this->load->config('broadcast');
		$this->broadcast_config = $this->config->item('broadcast');
	}
}