<?php
/**
 * Part of the Joomla Tracker's Text Application
 *
 * @copyright  Copyright (C) 2017 Open Source Matters, Inc. All rights reserved.
 * @license    http://www.gnu.org/licenses/gpl-2.0.txt GNU General Public License Version 2 or Later
 */

namespace App\Text\Model;

use App\Text\Table\ArticlesTable;
use JTracker\Model\AbstractTrackerDatabaseModel;

/**
 * Page model class.
 *
 * @since __DEPLOY_VERSION__
 */
class PageModel extends AbstractTrackerDatabaseModel
{
    /**
    * Get an item.
    *
    * @param   string  $alias  The item alias.
    *
    * @return  ArticlesTable
    *
    * @since   __DEPLOY_VERSION__
    */
    public function getItem($alias)
	{
		return (new ArticlesTable($this->db))
			->load(['alias' => $alias]);
	}
}
