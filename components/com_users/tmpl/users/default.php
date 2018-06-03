<?php
/**
 * @package     Joomla.Site
 * @subpackage  com_users
 *
 * @copyright   Copyright (C) 2005 - 2018 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Router\Route;
use Joomla\CMS\HTML\HTMLHelper;
\JLoader::register('UsersHelperRoute', JPATH_SITE . '/components/com_users/helpers/route.php');


HTMLHelper::addIncludePath(JPATH_COMPONENT . '/helpers');

$app = JFactory::getApplication();
?>

<div>
	<?php foreach ($this->items as $item) : ?>
		<div>
			<a href="<?php echo Route::_(UsersHelperRoute::getUserRoute($item->id . ':' . $item->name)); ?>" itemprop="url">
				<?php
				echo $item->name;
				?>
			</a>
			<p> <?php echo $item->id; ?></p>
		</div>
	<?php endforeach; ?>
</div>
