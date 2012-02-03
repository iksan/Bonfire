<?php if (!defined('BASEPATH')) exit('No direct script access allowed');
/*
	Copyright (c) 2011 Lonnie Ezell

	Permission is hereby granted, free of charge, to any person obtaining a copy
	of this software and associated documentation files (the "Software"), to deal
	in the Software without restriction, including without limitation the rights
	to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
	copies of the Software, and to permit persons to whom the Software is
	furnished to do so, subject to the following conditions:

	The above copyright notice and this permission notice shall be included in
	all copies or substantial portions of the Software.

	THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
	IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
	FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
	AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
	LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
	OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
	THE SOFTWARE.
*/

class Settings extends Admin_Controller {

	//--------------------------------------------------------------------

	public function __construct()
	{
		parent::__construct();

		$this->auth->restrict('Site.Settings.View');
		$this->auth->restrict('Bonfire.Users.View');

		$this->load->model('roles/role_model');

		$this->lang->load('users');

		Template::set_block('sub_nav', 'settings/sub_nav');
	}

	//--------------------------------------------------------------------

	public function _remap($method)
	{
		if (method_exists($this, $method))
		{
			$this->$method();
		}
	}

	//--------------------------------------------------------------------

	public function index()
	{
		$roles = $this->role_model->select('role_id, role_name')->where('deleted', 0)->find_all();
		Template::set('roles', $roles);

		// Do we have any actions?
		if ($action = $this->input->post('submit'))
		{
			$checked = $this->input->post('checked');

			switch(strtolower($action))
			{
				case 'ban':
					$this->ban($checked);
					break;
				case 'delete':
					$this->delete($checked);
					break;
			}
		}

		// Filters
		$filter = $this->input->get('filter');
		switch($filter)
		{
			case 'banned':
				$this->user_model->where('users.banned', 1);
				break;
			case 'deleted':
				$this->user_model->where('users.deleted', 1);
				break;
			case 'role':
				$role_id = (int)$this->input->get('role_id');
				$this->user_model->where('users.role_id', $role_id);
				foreach ($roles as $role)
				{
					if ($role->role_id == $role_id)
					{
						Template::set('filter_role', $role->role_name);
						break;
					}
				}
				break;
			default:
				$this->user_model->where('users.deleted', 0);
				break;
		}

		// First Letter
		$first_letter = $this->input->get('firstletter');
		if (!empty($first_letter))
		{
			$this->user_model->where('SUBSTRING( LOWER(username), 1, 1)=', $first_letter);
		}

		$this->load->helper('ui/ui');

		$this->load->library('ui/dataset');
		$this->dataset->set_source('user_model', 'find_all');
		$this->dataset->set_selects('users.id, users.role_id, username, display_name, email, last_login, banned, users.deleted, role_name');

		$columns = array(
			array(
				'field'		=> 'id',
				'title'		=> 'ID',
				'width'		=> '3em'
			),
			array(
				'field'		=> 'username',
			),
			array(
				'field'		=> 'display_name',
			),
			array(
				'field'		=> 'email',
			),
			array(
				'field'		=> 'last_login',
				'width'		=> '10em'
			)
		);

		$this->dataset->columns($columns);

		$bulk_actions = array('ban', 'delete');

		$this->dataset->actions($bulk_actions);

		$this->dataset->initialize();

		Template::set('current_url', current_url());
		Template::set('filter', $filter);

		Template::set('toolbar_title', lang('us_user_management'));
		Template::render();
	}

	//--------------------------------------------------------------------

	public function create()
	{
		$this->auth->restrict('Bonfire.Users.Add');

		$this->load->config('address');
		$this->load->helper('address');

		if ($this->input->post('submit'))
		{
			if ($id = $this->save_user())
			{
				$user = $this->user_model->find($id);
				$log_name = $this->settings_lib->item('auth.use_own_names') ? $this->auth->user_name() : ($this->settings_lib->item('auth.use_usernames') ? $user->username : $user->email);
				$this->activity_model->log_activity($this->auth->user_id(), lang('us_log_create').' '. $user->role_name . ': '.$log_name, 'users');

				Template::set_message('User successfully created.', 'success');
				Template::redirect(SITE_AREA .'/settings/users');
			}

		}

		Template::set('roles', $this->role_model->select('role_id, role_name, default')->where('deleted', 0)->find_all());

		Template::set('toolbar_title', lang('us_create_user'));
		Template::set_view('settings/user_form');
		Template::render();
	}

	//--------------------------------------------------------------------

	public function edit()
	{
		$this->auth->restrict('Bonfire.Users.Manage');

		$this->load->config('address');
		$this->load->helper('address');
		$this->load->helper('form');
		$this->load->library('meta/meta');

		$user_id = $this->uri->segment(5);
		if (empty($user_id))
		{
			Template::set_message(lang('us_empty_id'), 'error');
			redirect(SITE_AREA .'/settings/users');
		}

		if ($this->input->post('submit'))
		{
			if ($this->save_user('update', $user_id))
			{
				$user = $this->user_model->find($user_id);
				$log_name = $this->settings_lib->item('auth.use_own_names') ? $this->auth->user_name() : ($this->settings_lib->item('auth.use_usernames') ? $user->username : $user->email);
				$this->activity_model->log_activity($this->auth->user_id(), lang('us_log_edit') .': '.$log_name, 'users');

				Template::set_message('User successfully updated.', 'success');
			}

		}

		$user = $this->user_model->find($user_id);
		if (isset($user) && has_permission('Permissions.'.$user->role_name.'.Manage'))
		{
			Template::set('user', $user);
			Template::set('roles', $this->role_model->select('role_id, role_name, default')->find_all());
			Template::set_view('settings/user_form');
		}
		else
		{
			Template::set_message(sprintf(lang('us_unauthorized'),$user->role_name), 'error');
			redirect(SITE_AREA .'/settings/users');
		}

		Template::set('toolbar_title', lang('us_edit_user'));

		Template::render();
	}

	//--------------------------------------------------------------------

	public function ban($users=false, $ban_message='')
	{
		if (!$users)
		{
			return;
		}

		$this->auth->restrict('Bonfire.Users.Manage');

		foreach ($users as $user_id)
		{
			$data = array(
				'banned'		=> 1,
				'ban_message'	=> $ban_message
			);

			$this->user_model->update($user_id, $data);
		}
	}

	//--------------------------------------------------------------------

	public function delete($users=null)
	{
		if (empty($users))
		{
			$users = array($this->uri->segment(5));
		}

		if (!empty($users))
		{
			$this->auth->restrict('Bonfire.Users.Manage');

			foreach ($users as $id)
			{
				$user = $this->user_model->find($id);

				if (isset($user) && has_permission('Permissions.'.$user->role_name.'.Manage') && $user->id != $this->auth->user_id())
				{
					if ($this->user_model->delete($id))
					{
						$user = $this->user_model->find($id);
						$log_name = $this->settings_lib->item('auth.use_own_names') ? $this->auth->user_name() : ($this->settings_lib->item('auth.use_usernames') ? $user->username : $user->email);
						$this->activity_model->log_activity($this->auth->user_id(), lang('us_log_delete') . ': '.$log_name, 'users');
						Template::set_message('The User was successfully deleted.', 'success');
					}
					else
					{
						Template::set_message(lang('us_action_not_deleted'). $this->user_model->error, 'error');
					}
				}
				else
				{
					if ($user->id == $this->auth->user_id())
					{
						Template::set_message(lang('us_self_delete'), 'error');
					}
					else
					{
						Template::set_message(sprintf(lang('us_unauthorized'),$user->role_name), 'error');
					}
				}
			}
		}
		else
		{
			Template::set_message(lang('us_empty_id'), 'error');
		}

		redirect(SITE_AREA .'/settings/users');
	}

	//--------------------------------------------------------------------

	public function purge()
	{
		$user_id = $this->uri->segment(5);

		// Handle a single-user purge
		if (!empty($user_id) && is_numeric($user_id))
		{
			$this->user_model->delete($user_id, true);
		}
		// Handle purging all deleted users...
		else
		{
			// Find all deleted accounts
			$users = $this->user_model->where('users.deleted', 1)
									  ->find_all(true);

			if (is_array($users))
			{
				foreach ($users as $user)
				{
					$this->user_model->delete($user->id, true);
				}
			}
		}

		Template::set_message('Users Purged.', 'success');

		Template::redirect(SITE_AREA .'/settings/users');
	}

	//--------------------------------------------------------------------

	public function restore()
	{
		$id = $this->uri->segment(5);

		if ($this->user_model->update($id, array('users.deleted'=>0)))
		{
			Template::set_message('User successfully restored.', 'success');
		}
		else
		{
			Template::set_message('Unable to restore user: '. $this->user_model->error, 'error');
		}

		Template::redirect(SITE_AREA .'/settings/users');
	}

	//--------------------------------------------------------------------


	//--------------------------------------------------------------------
	// !HMVC METHODS
	//--------------------------------------------------------------------

	public function access_logs($limit=15)
	{
		$logs = $this->user_model->get_access_logs($limit);

		return $this->load->view('settings/access_logs', array('access_logs' => $logs), true);
	}

	//--------------------------------------------------------------------



	//--------------------------------------------------------------------
	// !PRIVATE METHODS
	//--------------------------------------------------------------------

	public function unique_email($str)
	{
		if ($this->user_model->is_unique('email', $str))
		{
			return true;
		}
		else
		{
			$this->form_validation->set_message('unique_email', lang('us_email_in_use'));
			return false;
		}
	}

	//--------------------------------------------------------------------

	private function save_user($type='insert', $id=0)
	{
		$db_prefix = $this->db->dbprefix;
		
		if ($type == 'insert')
		{
			$this->form_validation->set_rules('email', 'Email', 'required|trim|callback_unique_email|valid_email|max_length[120]|xss_clean');
			$this->form_validation->set_rules('password', 'Password', 'required|trim|strip_tags|max_length[40]|xss_clean');
			$this->form_validation->set_rules('pass_confirm', 'Password (again)', 'required|trim|strip_tags|matches[password]|xss_clean');
		} else
		{
			$this->form_validation->set_rules('email', 'Email', 'required|trim|valid_email|max_length[120]|xss_clean');
			$this->form_validation->set_rules('password', 'Password', 'trim|strip_tags|max_length[40]|xss_clean');
			$this->form_validation->set_rules('pass_confirm', 'Password (again)', 'trim|strip_tags|matches[password]|xss_clean');
		}

		if ($this->settings_lib->item('auth.use_usernames'))
		{
			$_POST['id'] = $id;
			$this->form_validation->set_rules('username', 'Username', 'required|trim|strip_tags|max_length[30]|unique['.$db_prefix.'users.username,'.$db_prefix.'users.id]|xsx_clean');
		}

		$required = false;
		if ($this->settings_lib->item('auth.use_own_names'))
		{
			$required = 'required|';
		}

		if  ( ! $this->settings_lib->item('auth.use_extended_profile'))
		{
			$this->form_validation->set_rules('first_name', lang('us_first_name'), $required.'trim|strip_tags|max_length[20]|xss_clean');
			$this->form_validation->set_rules('last_name', lang('us_last_name'), $required.'trim|strip_tags|max_length[20]|xss_clean');
			$this->form_validation->set_rules('street1', 'Street 1', 'trim|strip_tags|xss_clean');
			$this->form_validation->set_rules('street2', 'Street 2', 'trim|strip_tags|xss_clean');
			$this->form_validation->set_rules('city', 'City', 'trim|strip_tags|xss_clean');
			$this->form_validation->set_rules('zipcode', 'Zipcode', 'trim|strip_tags|max_length[20]|xss_clean');
		}
		if ($this->form_validation->run() === false)
		{
			return false;
		}

		if ($type == 'insert')
		{
			return $this->user_model->insert($_POST);
		}
		else	// Update
		{
			return $this->user_model->update($id, $_POST);
		}
	}

	//--------------------------------------------------------------------


}

// End User Admin class
