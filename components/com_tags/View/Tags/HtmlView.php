<?php
/**
 * @package     Joomla.Site
 * @subpackage  com_tags
 *
 * @copyright   Copyright (C) 2005 - 2018 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */
namespace Joomla\Component\Tags\Site\View\Tags;

defined('_JEXEC') or die;

use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\Registry\Registry;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Factory;

/**
 * HTML View class for the Tags component
 *
 * @since  3.1
 */
class HtmlView extends BaseHtmlView
{
	/**
	 * The model state
	 *
	 * @var    \JObject
	 * @since  3.1
	 */
	protected $state;

	/**
	 * The list of tags
	 *
	 * @var    array|false
	 * @since  3.1
	 */
	protected $items;

	/**
	 * UNUSED PROPERTY
	 *
	 * @var         array|null
	 * @since       3.1
	 * @deprecated  4.0
	 */
	protected $item;

	/**
	 * The pagination object
	 *
	 * @var    \Joomla\CMS\Pagination\Pagination
	 * @since  3.1
	 */
	protected $pagination;

	/**
	 * The page parameters
	 *
	 * @var    \Joomla\Registry\Registry|null
	 * @since  3.1
	 */
	protected $params = null;

	/**
	 * The page class suffix
	 *
	 * @var    string
	 * @since  4.0.0
	 */
	protected $pageclass_sfx = '';

	/**
	 * The logged in user
	 *
	 * @var    \JUser|null
	 * @since  4.0.0
	 */
	protected $user = null;

	/**
	 * Execute and display a template script.
	 *
	 * @param   string  $tpl  The name of the template file to parse; automatically searches through the template paths.
	 *
	 * @return  mixed   A string if successful, otherwise an Error object.
	 */
	public function display($tpl = null)
	{
		$app    = Factory::getApplication();
		$params = $app->getParams();

		// Get some data from the models
		$state      = $this->get('State');
		$items      = $this->get('Items');
		$item       = $this->get('Item');
		$pagination = $this->get('Pagination');

		if (count($errors = $this->get('Errors')))
		{
			throw new \JViewGenericdataexception(implode("\n", $errors), 500);
		}

		// Check whether access level allows access.
		// @todo: Should already be computed in $item->params->get('access-view')
		$user   = Factory::getUser();
		$groups = $user->getAuthorisedViewLevels();

		if (!empty($items))
		{
			foreach ($items as $itemElement)
			{
				if (!in_array($itemElement->access, $groups))
				{
					unset($itemElement);
				}

				// Prepare the data.
				$temp = new Registry($itemElement->params);
				$itemElement->params = clone $params;
				$itemElement->params->merge($temp);
				$itemElement->params = (array) json_decode($itemElement->params);
			}
		}

		$this->state      = &$state;
		$this->items      = &$items;
		$this->pagination = &$pagination;
		$this->user       = &$user;
		$this->item       = &$item;

		// Escape strings for HTML output
		$this->pageclass_sfx = htmlspecialchars($params->get('pageclass_sfx'));

		// Merge tag params. If this is single-tag view, menu params override tag params
		// Otherwise, article params override menu item params
		$this->params = $this->state->get('params');
		$active       = $app->getMenu()->getActive();
		$temp         = clone $this->params;

		// Check to see which parameters should take priority
		if ($active)
		{
			$currentLink = $active->link;

			// If the current view is the active item and the tags view, then the menu item params take priority
			if (strpos($currentLink, 'view=tags'))
			{
				$this->params = $active->params;
				$this->params->merge($temp);

				// Load layout from active query (in case it is an alternative menu item)
				if (isset($active->query['layout']))
				{
					$this->setLayout($active->query['layout']);
				}
			}
			else
			{
				// Current view is not a single tag, so the tag params take priority here
				// Merge the menu item params with the tag params so that the tag params take priority
				$temp->merge($item->params);
				$item->params = $temp;

				// Check for alternative layouts (since we are not in a single-article menu item)
				// Single tag menu item layout takes priority over alt layout for a tag
				if ($layout = $item->params->get('tag_layout'))
				{
					$this->setLayout($layout);
				}
			}
		}
		elseif (!empty($items[0]))
		{
			// Merge so that tag params take priority
			$temp->merge($items[0]->params);
			$items[0]->params = $temp;

			// Check for alternative layouts (since we are not in a single-tag menu item)
			// Single-tag menu item layout takes priority over alt layout for a tag
			if ($layout = $items[0]->params->get('tag_layout'))
			{
				$this->setLayout($layout);
			}
		}

		$this->_prepareDocument();

		parent::display($tpl);
	}

	/**
	 * Prepares the document
	 *
	 * @return void
	 */
	protected function _prepareDocument()
	{
		$app   = Factory::getApplication();
		$menus = $app->getMenu();
		$title = null;

		// Because the application sets a default page title,
		// we need to get it from the menu item itself
		$menu = $menus->getActive();

		if ($menu)
		{
			$this->params->def('page_heading', $this->params->get('page_title', $menu->title));
		}
		else
		{
			$this->params->def('page_heading', Text::_('COM_TAGS_DEFAULT_PAGE_TITLE'));
		}

		if ($menu && $menu->query['option'] !== 'com_tags')
		{
			$this->params->set('page_subheading', $menu->title);
		}

		// Set metadata for all tags menu item
		if ($this->params->get('menu-meta_description'))
		{
			$this->document->setDescription($this->params->get('menu-meta_description'));
		}

		if ($this->params->get('menu-meta_keywords'))
		{
			$this->document->setMetaData('keywords', $this->params->get('menu-meta_keywords'));
		}

		if ($this->params->get('robots'))
		{
			$this->document->setMetaData('robots', $this->params->get('robots'));
		}

		// If this is not a single tag menu item, set the page title to the tag titles
		$title = '';

		if (!empty($this->item))
		{
			foreach ($this->item as $i => $itemElement)
			{
				if ($itemElement->title)
				{
					if ($i != 0)
					{
						$title .= ', ';
					}

					$title .= $itemElement->title;
				}
			}

			if (empty($title))
			{
				$title = $app->get('sitename');
			}
			elseif ($app->get('sitename_pagetitles', 0) == 1)
			{
				$title = Text::sprintf('JPAGETITLE', $app->get('sitename'), $title);
			}
			elseif ($app->get('sitename_pagetitles', 0) == 2)
			{
				$title = Text::sprintf('JPAGETITLE', $title, $app->get('sitename'));
			}

			$this->document->setTitle($title);

			foreach ($this->item as $itemElement)
			{
				if ($itemElement->metadesc)
				{
					$this->document->setDescription($this->item->metadesc);
				}
				elseif ($this->params->get('menu-meta_description'))
				{
					$this->document->setDescription($this->params->get('menu-meta_description'));
				}

				if ($itemElement->metakey)
				{
					$this->document->setMetaData('keywords', $this->tag->metakey);
				}
				elseif ($this->params->get('menu-meta_keywords'))
				{
					$this->document->setMetaData('keywords', $this->params->get('menu-meta_keywords'));
				}

				if ($this->params->get('robots'))
				{
					$this->document->setMetaData('robots', $this->params->get('robots'));
				}

				if ($app->get('MetaAuthor') == '1')
				{
					$this->document->setMetaData('author', $itemElement->created_user_id);
				}

				$mdata = $this->item->metadata->toArray();

				foreach ($mdata as $k => $v)
				{
					if ($v)
					{
						$this->document->setMetaData($k, $v);
					}
				}
			}
		}

		// Respect configuration Sitename Before/After for TITLE in views All Tags.
		if (!$title && ($pos = $app->get('sitename_pagetitles', 0)))
		{
			$title = $this->document->getTitle();

			if ($pos == 1)
			{
				$title = Text::sprintf('JPAGETITLE', $app->get('sitename'), $title);
			}
			else
			{
				$title = Text::sprintf('JPAGETITLE', $title, $app->get('sitename'));
			}

			$this->document->setTitle($title);
		}

		// Add alternative feed link
		if ($this->params->get('show_feed_link', 1) == 1)
		{
			$link    = '&format=feed&limitstart=';
			$attribs = array('type' => 'application/rss+xml', 'title' => 'RSS 2.0');
			$this->document->addHeadLink(Route::_($link . '&type=rss'), 'alternate', 'rel', $attribs);
			$attribs = array('type' => 'application/atom+xml', 'title' => 'Atom 1.0');
			$this->document->addHeadLink(Route::_($link . '&type=atom'), 'alternate', 'rel', $attribs);
		}
	}
}
