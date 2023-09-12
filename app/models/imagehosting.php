<?php
/**
 * 이미지호스팅 관련 모듈
 * @author gabia
 * @since version 1.0 -2014-05-27
 * 이미지호스팅 개편 2022-09-23
 */
class Imagehosting extends CI_Model
{
	public $imgmatch = "/<IMG[^>]*src=[\"']?([^>\"']+)[\"']?[^>]*>/i";

	public function __construct()
	{
		parent::__construct();
		$this->load->library('ftp');
	}

	/**
	 * 이미지호스팅 계정 리스트 추출
	 * @return array
	 */
	public function get_imagehostingList()
	{
		$query = $this->db->select('*')->from('fm_imagehosting');
		$query = $query->get();

		return $query->result_array();
	}

	/**
	 * 이미지호스팅 계정 1건 추출
	 * @return array
	 */
	public function get_imagehostingEach($imagehosting_seq)
	{
		$query = $this->db->select('*')->from('fm_imagehosting');
		$query->where('imagehosting_seq', $imagehosting_seq);
		$query = $query->get();

		return $query->row_array();
	}

	/**
	 * 이미지호스팅 계정을 등록한다.
	 * @param array $params
	 * @return boolean
	 */
	public function insert_imagehosting($params = [])
	{
		$filter_params = filter_keys($params, $this->db->list_fields('fm_imagehosting'));
		$result = $this->db->insert('fm_imagehosting', $filter_params);
		if ($result !== true) {
			return false;
		}

		return $this->db->insert_id();
	}

	/**
	 * 이미지호스팅 계정을 수정한다.
	 * @param array $params
	 * @return boolean
	 */
	public function update_imagehosting($params = [])
	{
		$imagehosting_seq = $params['imagehosting_seq'];
		$filter_params = filter_keys($params, $this->db->list_fields('fm_imagehosting'));

		return $this->db->where('imagehosting_seq', $imagehosting_seq)->update('fm_imagehosting', $filter_params);
	}

	/**
	 * 이미지호스팅 계정을 삭제한다.
	 * @param $seq
	 * @return boolean
	 */
	public function delete_imagehosting($seq)
	{
		return $this->db->where('imagehosting_seq', $seq)->delete('fm_imagehosting');
	}

	/**
	 * 기능별 저장소 리스트 추출
	 * @return array
	 */
	public function get_imagestoreList()
	{
		$query = $this->db->select('*')->from('fm_imagestore');
		$query = $query->get();

		return $query->result_array();
	}

	/**
	 * 기능별 저장소 리스트 추출
	 * @param $imagehosting_seq
	 * @return 결과 count
	 */
	public function count_matched_imagestore($imagehosting_seq)
	{
		$query = $this->db->select('count(*)')->from('fm_imagestore');
		$query->where('imagehosting_seq', $imagehosting_seq);

		return $query->count_all_results();
	}

	/**
	 * 기능별 저장소 1건 추출
	 * @param array $where_params
	 * @return array
	 */
	public function get_imagestoreEach($where_params)
	{
		$query = $this->db->select('*')->from('fm_imagestore');
		$query->where($where_params);
		$query = $query->get();

		return $query->row_array();
	}

	/**
	 * 기능별 저장소 1건에 연결된 이미지호스팅 정보 추출
	 * @param array $where_params
	 * @return array
	 */
	public function get_linked_imagehosting($where_params)
	{
		$query = $this->db->select('*')->from('fm_imagestore');
		$query->where($where_params);
		$result_imagestore = $query->get()->row_array();

		$query = $this->db->select('*')->from('fm_imagehosting');
		$query->where('imagehosting_seq', $result_imagestore['imagehosting_seq']);
		$query = $query->get();

		return $query->row_array();
	}

	/**
	 * 기능별 저장소 설정을 수정한다.
	 * @param array $params
	 * @return boolean
	 */
	public function update_imagestore($params = [])
	{
		$filter_params = filter_keys($params, $this->db->list_fields('fm_imagestore'));
		$this->db->where('imagestore_seq', $params['imagestore_seq'])->update('fm_imagestore', $filter_params);

		return $this->db->affected_rows();
	}

	/**
	 * 기능별 저장소 이미지 경로 치환
	 * @param array $params
	 * @return boolean
	 */
	public function update_table_field($params = [])
	{	
		$update_type = $params['update_type'];
		$update_table = $params['table'];
		$update_select = $params['select'];
		$update_where = $params['where'];

		# update 대상이 되는 field 가 여러 개인 경우, 배열로 받아서 처리
		foreach($update_select['key'] as $idx => $update_select_key){
			// DB저장타입: 로컬경로
			if($update_type == "directory"){
				$update_select['update_select_key'] = $update_select_key;
				$update_set_sql = $this->update_table_field_directory($update_select);

			// DB저장타입: 파일명
			}else if($update_type == "file"){

				$update_select['update_select_key'] = $update_select_key;
				$update_select['idx'] = $idx;
				$update_set_sql = $this->update_table_field_file($update_select);
				
				$this->db->set($update_select_key, $update_set_sql, false);
				$update_set_sql = ""; // 이미 set 했으므로 초기화		

			// DB저장타입: editor
			}else if($update_type == "editor"){
				// <img src=" 퍼스트몰 이미지파일 첨부 시 기본 형태만 치환 대상
				$update_set_sql = "REPLACE(".$update_select_key.", '<img src=\"".$update_select['select_string']."', '<img src=\"".$update_select['change_string']."') ";
			}

			# Query builder 실행
			if($update_set_sql){ // SET
				$this->db->set($update_select_key, $update_set_sql, false);
			}
		}

		if($update_where){ // where
			$this->db->where($update_where, NULL, false);
		}

		$result = $this->db->update($update_table);
		//debug_var($this->db->last_query());
		if(! $result) return $result;

		return $result;
	}

	/**
	 * 기능별 저장소 이미지 경로 치환 - directory
	 * @param array $params
	 * @return update_set_sql
	 */
	public function update_table_field_directory($params = [])
	{
		$update_set_sql = "(
			CASE ";
	
		//변경 전 도메인: 로칼저장소
		if($params['select_string'] == "/"){
			//문자열 합치는(CONCAT) 경우, 맨 끝에 /를 제거해줘야 함
			$params['change_string'] = substr($params['change_string'], 0, -1);
			$update_set_sql .= "
				WHEN 
					LEFT(".$params['update_select_key'].", 5) = '/data'
				THEN 
					CONCAT('".$params['change_string']."', ".$params['update_select_key'].")";

		// 변경 전 도메인: 외부 저장소
		}else{
			$update_set_sql .= "
				WHEN 
					LEFT(".$params['update_select_key'].", 4) = 'http'
				THEN 
					REPLACE(". $params['update_select_key'] . ", '".$params['select_string']."', '".$params['change_string']."')";
		}
		$update_set_sql .= "
			ELSE 
				".$params['update_select_key']."
			END
		)";
		
		return $update_set_sql;
	}

	/**
	 * 기능별 저장소 이미지 경로 치환 - file
	 * @param array $params
	 * @return update_set_sql
	 */
	public function update_table_field_file($params = [])
	{
		$update_set_sql = "(
			CASE ";
	
		//변경 전 도메인: 로컬저장소
		if($params['select_string'] == "/"){
			///data/skin/%스킨명%/images/banner/%배너번호%/

			$update_set_sql .= "
				WHEN 
					LEFT(".$params['update_select_key'].", 4) != 'http' AND (".$params['update_select_key']." != '' AND ".$params['update_select_key']." is NOT NULL)
				THEN 
					CONCAT('".$params['change_string']."', ".$params['local_diractory'][$params['idx']].", ".$params['update_select_key'].")";
		
		// 변경 후 도메인: 로컬저장소
		}else if($params['change_string'] == "/"){
			$update_set_sql .= "
				WHEN 
					LEFT(".$params['update_select_key'].", 4) = 'http'
				THEN 
					REPLACE(".$params['update_select_key'].", concat('".$params['select_string']."', ".$params['local_diractory'][$params['idx']]."), '')";

		// 변경 전 도메인: 외부 저장소
		}else{
			$update_set_sql .= "
				WHEN 
					LEFT(".$params['update_select_key'].", 4) = 'http'
				THEN 
					REPLACE(".$params['update_select_key'].", '".$params['select_string']."', '".$params['change_string']."')";
		}
		$update_set_sql .= "
			ELSE 
				".$params['update_select_key']."
			END
		)";
		
		return $update_set_sql;
	}

	/**
	 * 저장소별 이미지 경로 치환 시 에외 상황 처리:: 공용정보 업로드
	 * @param update_where
	 * @return table_where
	 */
	public function changeImageurl_common_info($param)
	{
		// info_seq 가져오기
		$query = $this->db->select('info_seq')->from('fm_goods')->where($param, null, false);
		$result = $query->get()->row_array();
		$table_where = " info_seq = '{$result['info_seq']}' ";
		return $table_where;

	}

}
/* End of file imagehosting.php */
/* Location: ./app/models/imagehosting */
