<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

/*
	Class: Contexts

	Provides helper methods for displaying Context Navigation.
*/
class Contexts {

	/*
		Var: $actions
		Stores the available menu actions.
	*/
	protected static $actions = array();

	/*
		Var: $menu
		Stores the organized menu actions.
	*/
	protected static $menu	= array();

	/*
		Var: $outer_class
		The class name to attach to the outer ul tag.

		Default:
			nav
	*/
	protected static $outer_class	= 'nav';

	/*
		Var: $outer_id
		The id to apply to the outer ul tag.

		Default:
			null
	*/
	protected static $outer_id	= null;

	/*
		Var: $parent_class
		The class to attach to li tags with children

		Default:
			dropdown
	*/
	protected static $parent_class	= 'dropdown';

	/*
		Var: $child_class
		The class to apply to ul tags within li tags.

		Default:
			dropdown-menu
	*/
	protected static $child_class	= 'dropdown-menu';

	/*
		Var: $ci
		Pointer to the CodeIgniter instance.

		Access:
			Protected
	*/
	protected static $ci;

	//--------------------------------------------------------------------

	public function __construct()
	{
		self::$ci =& get_instance();
		self::init();
	}

	//--------------------------------------------------------------------

	protected static function init()
	{
		if (!function_exists('module_list'))
		{
			self::$ci->load->helper('application');
		}

		log_message('debug', 'UI/Contexts library loaded');
	}

	//--------------------------------------------------------------------

	/*
		Method: render_menu()

		Renders a list-based menu (with submenus) for each context.

		Parameters:
			$mode	- What to display in the top menu. Either 'icon', 'text', or 'both'.
			$top_level_only	- If TRUE, will only display the top-level links.

		Returns:
			A string with the built navigation.
	*/
	public static function render_menu($mode='icon', $top_level_only = false)
	{
		self::$ci->benchmark->mark('context_menu_start');

		$contexts = self::$ci->config->item('contexts');

		if (empty($contexts) || !is_array($contexts) || !count($contexts))
		{
			die(lang('bf_no_contexts'));
		}

		// Ensure settings context exists
		if (!in_array('settings', $contexts))
		{
			array_push($contexts, 'settings');
		}

		// Ensure developer context exists
		if (!in_array('developer', $contexts))
		{
			array_push($contexts, 'developer');
		}

		$nav = '<ul class="'. self::$outer_class .'" id="'. self::$outer_id .'">';

		/*
			Build out our navigation.
		*/
		foreach ($contexts as $context)
		{
			if (has_permission('Site.'. ucfirst($context) .'.View'))
			{
				$url = site_url(SITE_AREA .'/'.$context);
				$class = check_class($context, true);
				$id = 'tb_'. $context;
				$title = lang('bf_context_'. $context);

				$nav .= "<li class='dropdown menu-link {$class}'><a href='{$url}' id='{$id}' class='dropdown-toggle' title='{$title}' data-id='{$context}_menu'>";

				// Image
				if ($mode=='icon' || $mode=='both')
				{
					$nav .= "<img src='". Template::theme_url('images/context_'. $context .'.png') ."' alt='{$title}' />";
				}

				// Display String
				if ($mode=='text' || $mode=='both')
				{
					$nav .= "<span>$title</span>";
				}

				$nav .= "</a>";

				if (!$top_level_only)
				{
					$nav .= self::context_nav($context);
				}

				$nav .= "</li>";
			}

		}

		$nav .= '</ul>';

		self::$ci->benchmark->mark('context_menu_end');

		return $nav;
	}

	//--------------------------------------------------------------------

	/*
		Method: render_mobile_navs()

		Creates a series of divs that each contain a <ul> of links within
		that context. This is intended for the tab-style mobile navigation.

		Parameters:
			none

		Returns:
			A string with the navigation lists.
	*/
	public static function render_mobile_navs()
	{
		$contexts = self::$ci->config->item('contexts');

		$out = '';

		foreach ($contexts as $context)
		{
			$out .= "<ul id='{$context}_menu' class='mobile_nav'>";
			$out .= self::context_nav($context, '', true);
			$out .= "</ul>";
		}

		return $out;
	}

	//--------------------------------------------------------------------


	/*
		Method: context_nav()

		Builds the main navigation menu for each context.

		Parameters:
			$context	- The context to build the nav for.

		Returns:
			The HTML necessary to display the menu.
	*/
	public function context_nav($context=null, $class='dropdown-menu', $ignore_ul=false)
	{
		// Get a list of modules with a controller matching
		// $context ('content', 'settings', 'reports', or 'developer')
		$module_list = module_list();

		foreach ($module_list as $module)
		{
			if (module_controller_exists($context, $module) === true)
			{
				self::$actions[] = $module;
			}
		}

		unset($module_list);

		// Do we have any actions?
		if (!count(self::$actions))
		{
			return '<ul class="'. $class .'"></ul>';
		}

		// Grab our module permissions so we know who can see what on the sidebar
		$permissions = self::$ci->config->item('module_permissions');

		// Build up our menu array
		foreach (self::$actions as $module)
		{
			// Make sure the user has permission to view this page.
			if ((isset($permissions[$context][$module]) && has_permission($permissions[$context][$module])) || (isset($permissions[$context]) && is_array($permissions[$context]) && !array_key_exists($module, $permissions[$context])))
			{
				// Grab our module config array, if any.
				$mod_config = module_config($module);

				$display_name = isset($mod_config['name']) ? $mod_config['name'] : $module;
				$title = isset($mod_config['description']) ? $mod_config['description'] : $module;

				$menu_topic = isset($mod_config['menu_topic'][$context]) ? $mod_config['menu_topic'][$context] : $display_name;

				// Drop-down menus?
				if (isset($mod_config['menus']) && isset($mod_config['menus'][$context]))
				{
					$menu_view = $mod_config['menus'][$context];
				} else
				{
					$menu_view = '';
				}

				self::$menu[$menu_topic][$module] = array(
						'title'			=> $title,
						'display_name'	=> $display_name,
						'menu_view'		=> $menu_view,
						'menu_topic'	=> $menu_topic
				);
			}
		}

		$menu = self::build_sub_menu($context, $ignore_ul);

		self::$actions = array();

		return $menu;
	}

	//--------------------------------------------------------------------



	//--------------------------------------------------------------------
	// !UTILITY METHODS
	//--------------------------------------------------------------------

	/*
		Method: set_attrs()

		Takes an array of key/value pairs and sets the class/id names.

		Parameters:
			$attrs	- an array of key/value pairs that correspond to the
						class methods for classes and ids.

		Returns:
			void
	*/
	public static function set_attrs($attrs=array())
	{
		if (!is_array($attrs))
		{
			return null;
		}

		foreach ($attrs as $attr => $value)
		{
			if (isset(self::$attr))
			{
				self::$attr = $value;
			}
		}
	}

	//--------------------------------------------------------------------

	/*
		Method: build_sub_menu()

		Handles building out the HTML for the menu.

		Parameters:
			$actions	- an array of action name and action url.
	*/
	public static function build_sub_menu($context, $ignore_ul=false)
	{
		$list = '';

		// Build a ul to return
		if (!$ignore_ul)
		{
			$list = "<ul class='". self::$child_class ."'>\n";
		}

		foreach (self::$menu as $topic_name => $topic)
		{
			// If the topic has other items, we're not closed.
			$closed = true;

			// If there is more than one item in the topic, we need to build
			// out a menu based on the multiple items.
			if (count($topic) > 1)
			{
				$list .= '<li><a href="#" class="no-link parent-menu">'. ucwords($topic_name) .'</a>';
				$list .= '<ul>';

				foreach ($topic as $module => $vals)
				{
					$class = $module == self::$ci->uri->segment(3) ? ' class="active"' : '';

					// If it has a sub-menu, echo out that menu only…
					if (isset($vals['menu_view']) && !empty($vals['menu_view']))
					{
						$view = self::$ci->load->view($vals['menu_view'], null, true);

						// To maintain backwards compatility, strip out and <ul> tags
						$view = str_ireplace('<ul>', '', $view);
						$view = str_ireplace('</ul>', '', $view);

						$list .= $view;
					}
					// Otherwise, it's a single item, so add it like normal
					else
					{
						$list .= self::build_item($module, $vals['title'], $vals['display_name'], $context, $vals['menu_view']);
					}
				}

				$list .= '</ul></li>';
			}
			else
			{
				foreach ($topic as $module => $vals)
				{
					$list .= self::build_item($module, $vals['title'], $vals['display_name'], $context, $vals['menu_view']);
				}
			}

		}

		if (!$ignore_ul)
		{
			$list .= "</ul>\n";
		}

		self::$menu = array();

		return $list;
	}

	//--------------------------------------------------------------------


	/*
		Method: build_item()

		Handles building an individual list item (with sub-menus) for the menu.

		Parameters:
			$module			- The name of the module this link belongs to
			$title			- The title used on the link
			$display_name	- The name to display in the menu
			$context		- The name of the context
			$menu_view		- The name of the view file that contains the sub-menu

		Returns:
			The HTML necessary for a single item and it's sub-menus.
	*/
	private static function build_item($module, $title, $display_name, $context, $menu_view='')
	{
		$item  = '<li><a href="'. site_url(SITE_AREA .'/'. $context .'/'. $module) .'" class="{class}"';
		$item .= ' title="'. $title .'">'. ucwords(str_replace('_', '', $display_name)) ."</a>\n";

		// Sub Menus?
		if (!empty($menu_view))
		{
			// Only works if it's a valid view…
			$view = self::$ci->load->view($menu_view, null, true);

			$item .= $view;
		}

		// Is this the current module?
		$class = $module == self::$ci->uri->segment(3) ? 'active' : '';
		if (!empty($menu_view))
		{
			$class .= ' parent-menu';
		}

		$item = str_replace('{class}', $class, $item);

		$item .= "</li>\n";

		return $item;
	}

	//--------------------------------------------------------------------
}
