<?php
/**
 * Part of the Joomla Tracker's Tracker Application
 *
 * @copyright  Copyright (C) 2017 Open Source Matters, Inc. All rights reserved.
 * @license    http://www.gnu.org/licenses/gpl-2.0.txt GNU General Public License Version 2 or Later
 */

namespace App\Tracker\Controller\Issue;

use App\Tracker\Model\IssueModel;
use App\Tracker\View\Issue\IssueHtmlView;

use JTracker\Authentication\Exception\AuthenticationException;
use JTracker\Controller\AbstractTrackerController;

/**
 * Controller class to edit an item via the tracker component.
 *
 * @since __DEPLOY_VERSION__
 */
class Edit extends AbstractTrackerController
{
    /**
    * The default view for the component
    *
    * @var    string
    * @since __DEPLOY_VERSION__
    */
    protected $defaultView = 'issue';

    /**
    * The default view for the component
    *
    * @var    string
    * @since __DEPLOY_VERSION__
    */
    protected $defaultLayout = 'edit';

    /**
    * View object
    *
    * @var    IssueHtmlView
    * @since __DEPLOY_VERSION__
    */
    protected $view = null;

    /**
    * Model object
    *
    * @var    IssueModel
    * @since __DEPLOY_VERSION__
    */
    protected $model = null;

    /**
    * Initialize the controller.
    *
    * This will set up default model and view classes.
    *
    * @throws \Exception
    * @throws AuthenticationException
    *
    * @return  $this  Method supports chaining
    *
    * @since   __DEPLOY_VERSION__
    */
    public function initialize()
	{
		parent::initialize();

		/* @type \JTracker\Application $application */
		$application = $this->getContainer()->get('app');
		$project     = $application->getProject();
		$user        = $application->getUser();

		$this->model->setProject($project);

		$item = $this->model->getItem($application->input->getUint('id'));

		$sha = false;

		if ($item->commits)
		{
			$commits = json_decode($item->commits);
			$lastCommit = end($commits);
			$sha = $lastCommit->sha;
		}

		$item->userTest = $this->model->getUserTest($item->id, $user->username, $sha);

		$item->categoryids = [];

		foreach ($item->categories as $category)
		{
			$item->categoryids[] = $category->id;
		}

		try
		{
			// Check if the user has full "edit" permission
			$user->authorize('edit');
		}
		catch (AuthenticationException $e)
		{
			// Check if the user has "edit own" permission
			if (false === $user->canEditOwn($item->opened_by))
			{
				throw $e;
			}
		}

		$this->view
			->setItem($item)
			->setProject($project);

		return $this;
	}
}