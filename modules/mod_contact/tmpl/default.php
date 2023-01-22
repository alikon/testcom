<?php

/**
 * @package     Joomla.Site
 * @subpackage  com_contact
 *
 * @copyright   (C) 2023 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Router\Route;
use Joomla\Component\Contact\Site\Helper\RouteHelper;

if (!$list) {
    return;
}

?>
<ul class="mod-list">
<?php foreach ($list as $item) : ?>
    <li  itemscope itemtype="https://schema.org/Person">
        <a href="<?php echo Route::_(RouteHelper::getContactRoute($item->id, $item->catid, $item->language)); ?>" itemprop="url">
            <span itemprop="name">
                <?php echo $item->name; ?>
            </span>
        </a>
    </li>
<?php endforeach; ?>
</ul>