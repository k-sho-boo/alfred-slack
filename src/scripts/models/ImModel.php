<?php

namespace AlfredSlack\Models;

class ImModel extends Model {

	protected $_type = 'im';

	protected $id;
	protected $is_im;
	protected $user;
	protected $created;
	protected $is_user_deleted;

	protected $auth;
}
