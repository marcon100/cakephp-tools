<?php

class CodeKey extends ToolsAppModel {

	var $name = 'CodeKey';

	var $displayField = 'key';
	var $order = array('CodeKey.created' => 'ASC');

	private $defaultLength = 22;

	var $validate = array(
		'type' => array(
			'notEmpty' => array(
				'rule' => array('notEmpty'),
				'message' => 'valErrMandatoryField',
			),
		),
		'key' => array(
			'isUnique' => array(
				'rule' => array('isUnique'),
				'message' => 'key already exists',
			),
			'notEmpty' => array(
				'rule' => array('notEmpty'),
				'message' => 'valErrMandatoryField',
			),
		),
		'content' => array(
			'maxLength' => array(
				'rule' => array('maxLength', 255),
				'message' => array('valErrMaxCharacters %s', 255),
				'allowEmpty' => true
			),
		),
		'used' => array('numeric')
	);

	//var $types = array('activate');

	/**
	 * stores new key in DB
	 * @param string type: neccessary
	 * @param string key: optional key, otherwise a key will be generated
	 * @param mixed user_id: optional (if used, only this user can use this key)
	 * @param string content: up to 255 characters of content may be added (optional)
	 * NOW: checks if this key is already used (should be unique in table)
	 * @return string key on SUCCESS, boolean false otherwise
	 * 2009-05-13 ms
	 */
	function newKey($type, $key = null, $uid = null, $content = null) {
		if (empty($type)) {		//  || !in_array($type,$this->types)
			return false;
		}

		if (empty($key)) {
			$key = $this->generateKey($this->defaultLength);
			$keyLength = $this->defaultLength;
		} else {
			$keyLength = mb_strlen($key);
		}

		$data = array(
			'type' => $type,
			'user_id' => (string)$uid,
			'content' => (string)$content,
			'key' => $key,
		);

		$this->set($data);
		$max = 99;
		while (!$this->validates()) {
			$data['key'] = $this->generateKey($keyLength);
			$this->set($data);
			$max--;
			if ($max == 0) { //die('Exeption in CodeKey');
			 	return false;
			}
		}

		$this->create();
		if ($this->save($data)) {
			return $key;
		}
		return false;
	}

	/**
	 * usesKey (only once!) - by KEY
	 * @param string type: neccessary
	 * @param string key: neccessary
	 * @param mixed user_id: needs to be provided if this key has a user_id stored
	 * @return ARRAY(content) if successfully used or if already used (used=1), FALSE else
	 * 2009-05-13 ms
	 */
	function useKey($type, $key, $uid = null) {
		if (empty($type) || empty($key)) {
			return false;
		}
		$conditions = array('conditions'=>array($this->alias.'.key'=>$key,$this->alias.'.type'=>$type));
		if (!empty($uid)) {
			$conditions['conditions'][$this->alias.'.user_id'] = $uid;
		}
		$res = $this->find('first', $conditions);
		if (empty($res)) {
			return false;
		} elseif(!empty($uid) && !empty($res[$this->alias]['user_id']) && $res[$this->alias]['user_id'] != $uid) {
			// return $res; # more secure to fail here if user_id is not provided, but was submitted prev.
			return false;
		} elseif ($res[$this->alias]['used'] == 1) {
			return $res;
		}

		# actually use key
		if ($this->spendKey($res[$this->alias]['id'])) {
			return $res;
		}
		$this->log('VIOLATION in CodeKey Model (method useKey)');
		return false;
	}

	/**
	 * sets Key to "used" (only once!) - directly by ID
	 * @param id of key to spend: neccessary
	 * @return boolean true on success, false otherwise
	 * 2009-05-13 ms
	 */
	function spendKey($id = null) {
		if (empty($id)) {
			return false;
		}
		$this->id = $id;
		if ($this->saveField('used', 1)) {
			return true;
		}
		return false;
	}

	/**
	 * remove old/invalid keys
	 * does not remove recently used ones (for proper feedback)!
	 * @return boolean success
	 * 2010-06-17 ms
	 */
	function garbigeCollector() {
		$conditions = array(
			$this->alias.'.created <'=>date(FORMAT_DB_DATETIME, time()-MONTH),
		);
		return $this->deleteAll($conditions, false);
	}


	/**
	 * @param length (defaults to defaultLength)
	 * @return string codekey
	 * 2009-05-13 ms
	 */
	function generateKey($length = null) {
		if (empty($length)) {
			$length = $defaultLength;
		} else {
			if ((class_exists('CommonComponent') || App::import('Component', 'Common')) && method_exists('CommonComponent', 'generatePassword')) {
				return CommonComponent::generatePassword($length);
			} else {
				return $this->_generateKey($length);
			}
		}
	}

	/**
	 * backup method - only used if no custom function exists
	 * 2010-06-17 ms
	 */
	function _generateKey($length = null) {
		$chars = "234567890abcdefghijkmnopqrstuvwxyz"; // ABCDEFGHIJKLMNOPQRSTUVWXYZ
		$i = 0;
		$password = "";
		$max = strlen($chars) - 1;

		while ($i < $length) {
			$password .= $chars[mt_rand(0, $max)];
			$i++;
		}
		return $password;
	}

}
?>