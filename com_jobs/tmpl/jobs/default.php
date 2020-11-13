<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_jobs
 *
 * @copyright   Copyright (C) 2005 - 2020 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;

HTMLHelper::_('behavior.multiselect');

$user      = Factory::getUser();
$listOrder = $this->escape($this->state->get('list.ordering'));
$listDirn  = $this->escape($this->state->get('list.direction'));
?>
<form action="<?php echo Route::_('index.php?option=com_jobs&view=jobs'); ?>" method="post" name="adminForm" id="adminForm">
	<div id="j-main-container" class="j-main-container">
		<?php echo LayoutHelper::render('joomla.searchtools.default', array('view' => $this)); ?>
		

		<?php if (empty($this->items)) : ?>
			<div class="alert alert-info">
				<span class="icon-info-circle" aria-hidden="true"></span><span class="sr-only"><?php echo Text::_('INFO'); ?></span>
				<?php echo Text::_('JGLOBAL_NO_MATCHING_RESULTS'); ?>
			</div>
		<?php else : ?>
			<table class="table">
				<caption id="captionTable" class="sr-only">
					<?php echo Text::_('COM_JOBS_TABLE_CAPTION'); ?>,
							<span id="orderedBy"><?php echo Text::_('JGLOBAL_SORTED_BY'); ?> </span>,
							<span id="filteredBy"><?php echo Text::_('JGLOBAL_FILTERED_BY'); ?></span>
				</caption>
				<thead>
					<tr>
						<td class="w-1 text-center">
							<?php echo HTMLHelper::_('grid.checkall'); ?>
						</td>
						<th scope="col" class="w-1 d-none d-md-table-cell">
							<?php echo HTMLHelper::_('searchtools.sort', 'COM_JOBS_HEADING_JOBNAME', 'a.taskname', $listDirn, $listOrder); ?>
						</th>
						<th scope="col" class="w-1 d-none d-md-table-cell">
							<?php echo HTMLHelper::_('searchtools.sort', 'COM_JOBS_HEADING_RUNNED', 'a.taskid', $listDirn, $listOrder); ?>
						</th>
						<th scope="col" class="w-1 d-none d-md-table-cell">
							<?php echo HTMLHelper::_('searchtools.sort', 'COM_JOBS_HEADING_EXECUTION', 'a.lastdate', $listDirn, $listOrder); ?>
						</th>
						<th scope="col" class="w-1 d-none d-md-table-cell">
							<?php echo HTMLHelper::_('searchtools.sort', 'COM_JOBS_HEADING_DURATION', 'a.duration', $listDirn, $listOrder); ?>
						</th>
						<th scope="col" class="w-1 d-none d-md-table-cell">
							<?php echo HTMLHelper::_('searchtools.sort', 'COM_JOBS_HEADING_EXIT_CODE', 'a.exitcode', $listDirn, $listOrder); ?>
						</th>
						<th scope="col" class="w-1 d-none d-md-table-cell">
							<?php echo HTMLHelper::_('searchtools.sort', 'COM_JOBS_HEADING_NEXTEXECUTION', 'a.nextdate', $listDirn, $listOrder); ?>
						</th>
						<th scope="col" class="w-1 d-none d-md-table-cell">
							<?php echo HTMLHelper::_('searchtools.sort', 'JGRID_HEADING_ID', 'a.id', $listDirn, $listOrder); ?>
						</th>
					</tr>
				</thead>
				<tbody>
				<?php foreach ($this->items as $i => $item) :
					$canEdit   = $user->authorise('core.edit',       'com_jobs');
					$canChange = $user->authorise('core.edit.state', 'com_jobs');
					?>
					<tr class="row<?php echo $i % 2; ?>">
						<td class="text-center">
							<?php echo HTMLHelper::_('grid.id', $i, $item->id); ?>
						</td>
						<th scope="row" class="d-none d-md-table-cell">
							<?php if ($canEdit) : ?>
								<a href="<?php echo Route::_('index.php?option=com_plugins&task=plugin.edit&extension_id=' . $item->jobid); ?>" title="<?php echo Text::_('JACTION_EDIT'); ?> <?php echo $this->escape($item->taskname); ?>">
									<?php echo $this->escape(str_replace(Uri::root(), '', rawurldecode($item->taskname))); ?>
								</a>
							<?php else : ?>
									<?php echo $this->escape(str_replace(Uri::root(), '', rawurldecode($item->taskname))); ?>
							<?php endif; ?>
						</th>
						<td class="d-none d-md-table-cell">
							<?php echo (int) $item->taskid; ?>
						</td>
						
						<td class="d-none d-md-table-cell">
							<?php echo HTMLHelper::_('date', $item->lastdate, Text::_('DATE_FORMAT_LC6')); ?>
						</td>
						<td class="d-none d-md-table-cell">
							<?php echo $item->duration; ?>
						</td>
						<td class="d-none d-md-table-cell">
							<?php echo (int) $item->exitcode; ?>
						</td>
						<td class="d-none d-md-table-cell">
							<?php echo HTMLHelper::_('date', $item->nextdate, Text::_('DATE_FORMAT_LC6')); ?>
						</td>
						<td class="d-none d-md-table-cell">
							<?php echo (int) $item->id; ?>
						</td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<?php // load the pagination. ?>
			<?php echo $this->pagination->getListFooter(); ?>

		<?php endif; ?>

		<input type="hidden" name="task" value="">
		<input type="hidden" name="boxchecked" value="0">
		<?php echo HTMLHelper::_('form.token'); ?>
	</div>
</form>
