<?php
/**
 * CB Menu Resolver — one canonical profile/login/edit URL for the whole site.
 * ---------------------------------------------------------------------------
 * Finds the canonical public Joomla menu item for Community Builder
 * (option=com_comprofiler) by component / view, or uses an explicitly
 * configured Itemid when provided. NEVER hardcodes aliases like "cb-profile"
 * and NEVER emits raw /component/com_comprofiler/ URLs: links are built
 * through JRoute::_() with the menu item's Itemid. #__menu is the single
 * source of truth (the Joomla menu system decides what is canonical).
 *
 * Collision-safe: the class_exists() guard lets multiple packages ship this
 * file without redefining the class.
 *
 * @version 1.10.1
 */
defined('_JEXEC') or die;

if (!class_exists('SccCbMenuResolver'))
{
class SccCbMenuResolver
{
	/** Shipped resolver version for collision/version-skew diagnostics. */
	const VERSION = '1.10.1';

	protected static $instance = null;

	public static function instance()
	{
		if (self::$instance === null)
		{
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Find the best public menu item for an option/view.
	 * Order: 1) configured Itemid; 2) option+view; 3) option alone.
	 */
	public function findMenuItem($preferredItemId = 0, $option = 'com_comprofiler', $view = '')
	{
		$levels = JFactory::getUser()->getAuthorisedViewLevels();
		$pref   = (int) $preferredItemId;

		if ($pref > 0)
		{
			$item = $this->menuItemById($pref, $levels, $option);
			if ($this->matches($item, $option, $view))
			{
				return $item;
			}
		}

		$items = $this->menuItemsForOption($option, $levels);

		foreach ($items as $item)
		{
			if ($view === '' || $this->hasView($item, $view))
			{
				return $item;
			}
		}

		if ($view !== '' && !empty($items))
		{
			return $items[0];
		}

		return null;
	}

	protected function menuItemById($id, $levels, $option)
	{
		$db = JFactory::getDbo();
		$q  = $db->getQuery(true)
			->select(array('id', 'title', 'link', 'access', 'published'))
			->from('#__menu')
			->where('id = ' . (int) $id)
			->where('client_id = 0')
			->where('published = 1')
			->where('type = ' . $db->q('component'))
			->where('link LIKE ' . $db->q('index.php?option=' . $option . '%'));
		$db->setQuery($q);
		$item = $db->loadObject();

		if (!$item || !in_array((int) $item->access, $levels))
		{
			return null;
		}

		return $item;
	}

	protected function menuItemsForOption($option, $levels)
	{
		$db = JFactory::getDbo();
		$q  = $db->getQuery(true)
			->select(array('id', 'title', 'link', 'access', 'published'))
			->from('#__menu')
			->where('client_id = 0')
			->where('published = 1')
			->where('type = ' . $db->q('component'))
			->where('link LIKE ' . $db->q('index.php?option=' . $option . '%'))
			->order('access ASC, id ASC');
		$db->setQuery($q);

		$out = array();
		foreach ((array) $db->loadObjectList() as $item)
		{
			if (in_array((int) $item->access, $levels))
			{
				$out[] = $item;
			}
		}

		return $out;
	}

	protected function matches($item, $option, $view)
	{
		return $item && !empty($item->link)
			&& strpos($item->link, 'option=' . $option) !== false
			&& ($view === '' || strpos($item->link, 'view=' . $view) !== false);
	}

	protected function hasView($item, $view)
	{
		return $item && !empty($item->link) && strpos($item->link, 'view=' . $view) !== false;
	}

	public function buildMenuUrl($item = null, $extra = array(), $fallback = array())
	{
		$query = array();

		if ($item && !empty($item->link))
		{
			$q = parse_url($item->link, PHP_URL_QUERY);
			if ($q)
			{
				parse_str($q, $query);
			}

			if (!isset($query['Itemid']))
			{
				$query['Itemid'] = (int) $item->id;
			}
		}
		else
		{
			$query = array_merge(array('option' => 'com_comprofiler'), (array) $fallback);
		}

		foreach ((array) $extra as $k => $v)
		{
			if ($v !== null && $v !== '')
			{
				$query[$k] = $v;
			}
		}

		return JRoute::_('index.php?' . http_build_query($query), false);
	}

	public function getProfileUrl($userId = 0, $preferredItemId = 0)
	{
		$item = $this->findMenuItem($preferredItemId, 'com_comprofiler', 'userprofile');

		if (!$item)
		{
			return '';
		}

		$extra = array();
		if ((int) $userId > 0)
		{
			$extra['user'] = (int) $userId;
		}

		return $this->buildMenuUrl($item, $extra, array('view' => 'userprofile'));
	}

	public function getEditProfileUrl($userId = 0, $preferredItemId = 0)
	{
		$item = $this->findMenuItem($preferredItemId, 'com_comprofiler', 'userprofile');

		if (!$item)
		{
			return '';
		}

		$extra = array('task' => 'edit');
		if ((int) $userId > 0)
		{
			$extra['user'] = (int) $userId;
		}

		return $this->buildMenuUrl(
			$item,
			$extra,
			array('view' => 'userprofile', 'task' => 'edit')
		);
	}

	public function getLoginUrl()
	{
		$item = $this->findMenuItem(0, 'com_comprofiler', 'login');

		return $this->buildMenuUrl($item, array('view' => 'login'), array('view' => 'login'));
	}

	public function getLogoutUrl()
	{
		$item = $this->findMenuItem(0, 'com_comprofiler', 'logout');

		return $this->buildMenuUrl(
			$item,
			array('view' => 'logout', 'task' => 'logout'),
			array('view' => 'logout', 'task' => 'logout')
		);
	}

	public function getForgotUrl($preferredItemId = 0)
	{
		$item = $this->findMenuItem($preferredItemId, 'com_comprofiler', 'lostpassword');

		return $this->buildMenuUrl($item, array('view' => 'lostpassword'), array('view' => 'lostpassword'));
	}

	public function getRegisterUrl()
	{
		$item = $this->findMenuItem(0, 'com_users', 'registration');

		return $this->buildMenuUrl(
			$item,
			array('view' => 'registration'),
			array('option' => 'com_users', 'view' => 'registration')
		);
	}
}
}