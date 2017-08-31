<?php
/**
 * Part of the Joomla Tracker Controller Package
 *
 * @copyright  Copyright (C) 2017 Open Source Matters, Inc. All rights reserved.
 * @license    http://www.gnu.org/licenses/gpl-2.0.txt GNU General Public License Version 2 or Later
 */

namespace JTracker\Controller;

use Joomla\DI\ContainerAwareInterface;
use Joomla\DI\ContainerAwareTrait;
use Joomla\Event\DispatcherAwareInterface;
use Joomla\Event\DispatcherAwareTrait;
use Joomla\Event\Event;
use Joomla\Input\Input;
use Joomla\Renderer\RendererInterface;

use JTracker\Authentication\GitHub\GitHubLoginHelper;
use JTracker\GitHub\Github;
use JTracker\Github\GithubFactory;
use JTracker\View\AbstractTrackerHtmlView;

/**
 * Abstract Controller class for the Tracker Application
 *
 * @since __DEPLOY_VERSION__
 */
abstract class AbstractTrackerController implements ContainerAwareInterface, DispatcherAwareInterface
{
    use ContainerAwareTrait, DispatcherAwareTrait;

    /**
    * The default view for the app
    *
    * @var    string
    * @since __DEPLOY_VERSION__
    */
    protected $defaultView = '';

    /**
    * The default layout for the app
    *
    * @var    string
    * @since __DEPLOY_VERSION__
    */
    protected $defaultLayout = 'index';

    /**
    * The app being executed.
    *
    * @var    string
    * @since __DEPLOY_VERSION__
    */
    protected $app;

    /**
    * View object
    *
    * @var    \Joomla\View\BaseHtmlView
    * @since __DEPLOY_VERSION__
    */
    protected $view;

    /**
    * Model object
    *
    * @var    \Joomla\Model\AbstractModel
    * @since __DEPLOY_VERSION__
    */
    protected $model;

    /**
    * Flag if the event listener is set for a hook
    *
    * @var    boolean
    * @since __DEPLOY_VERSION__
    */
    protected $listenerSet = false;

    /**
    * The GitHub object.
    *
    * @var GitHub
    * @since __DEPLOY_VERSION__
    */
    protected $github = null;

    /**
    * Constructor.
    *
    * @since   __DEPLOY_VERSION__
    */
    public function __construct()
	{
		// Detect the App name
		if (empty($this->app))
		{
			// Get the fully qualified class name for the current object
			$fqcn = (get_class($this));

			// Strip the base app namespace off
			$className = str_replace('App\\', '', $fqcn);

			// Explode the remaining name into an array
			$classArray = explode('\\', $className);

			// Set the app as the first object in this array
			$this->app = $classArray[0];
		}
	}

    /**
    * Initialize the controller.
    *
    * This will set up default model and view classes.
    *
    * @return  $this  Method allows chiaining
    *
    * @since   __DEPLOY_VERSION__
    * @throws  \RuntimeException
    */
    public function initialize()
	{
		// Get the input
		/* @type Input $input */
		$input = $this->getContainer()->get('app')->input;

		// Get some data from the request
		$viewName   = $input->getWord('view', $this->defaultView);
		$viewFormat = $input->getWord('format', 'html');
		$layoutName = $input->getCmd('layout', $this->defaultLayout);

		if (!$viewName)
		{
			$parts = explode('\\', get_class($this));
			$viewName = strtolower($parts[count($parts) - 1]);
		}

		$base = '\\App\\' . $this->app;

		$viewClass  = $base . '\\View\\' . ucfirst($viewName) . '\\' . ucfirst($viewName) . ucfirst($viewFormat) . 'View';
		$modelClass = $base . '\\Model\\' . ucfirst($viewName) . 'Model';

		// If a model doesn't exist for our view, revert to the default model
		if (!class_exists($modelClass))
		{
			$modelClass = $base . '\\Model\\DefaultModel';

			// If there still isn't a class, panic.
			if (!class_exists($modelClass))
			{
				throw new \RuntimeException(
					sprintf(
						'No model found for view %s or a default model for %s', $viewName, $this->app
					)
				);
			}
		}

		// If there isn't a specific view class for this view, see if the app has a default for this format
		if (!class_exists($viewClass))
		{
			$viewClass = $base . '\\View\\Default' . ucfirst($viewFormat) . 'View';

			// Make sure the view class exists, otherwise revert to the default
			if (!class_exists($viewClass))
			{
				$viewClass = '\\JTracker\\View\\TrackerDefaultView';
			}
		}

		$this->model = new $modelClass($this->getContainer()->get('db'), $this->getContainer()->get('app')->input);

		// Create the view
		/* @type AbstractTrackerHtmlView $view */
		$this->view = new $viewClass(
			$this->model,
			$this->fetchRenderer($viewName, $layoutName)
		);

		$this->view->setLayout($viewName . '.' . $layoutName . '.twig');

		$this->getContainer()->get('app')->mark('Model: ' . $modelClass);
		$this->getContainer()->get('app')->mark('View: ' . $viewClass);
		$this->getContainer()->get('app')->mark('Layout: ' . $layoutName);

		return $this;
	}

    /**
    * Execute the controller.
    *
    * This is a generic method to execute and render a view and is not suitable for tasks.
    *
    * @return  string
    *
    * @since   __DEPLOY_VERSION__
    */
    public function execute()
	{
		try
		{
			// Render our view.
			$contents = $this->view->render();

			$this->getContainer()->get('app')->mark('View rendered: ' . $this->view->getLayout());
		}
		catch (\Exception $e)
		{
			$contents = $this->getContainer()->get('app')->renderException($e);
		}

		return $contents;
	}

    /**
    * Returns the current app
    *
    * @return  string  The app being executed.
    *
    * @since   __DEPLOY_VERSION__
    */
    public function getApp()
	{
		return $this->app;
	}

    /**
    * Get a renderer object.
    *
    * @param   string  $view    The view to render
    * @param   string  $layout  The layout in the view
    *
    * @return  RendererInterface
    *
    * @since   __DEPLOY_VERSION__
    * @throws  \RuntimeException
    */
    protected function fetchRenderer($view, $layout)
	{
		/* @type \JTracker\Application $application */
		$application = $this->getContainer()->get('app');

		$rendererName = $application->get('renderer.type');

		// The renderer should exist in the container
		if (!$this->getContainer()->exists("renderer.$rendererName"))
		{
			throw new \RuntimeException('Unsupported renderer: ' . $rendererName);
		}

	    /** @var RendererInterface $renderer */
		$renderer = $this->getContainer()->get("renderer.$rendererName");

		// Alias the renderer to the interface if not set already
		if (!$this->getContainer()->exists(RendererInterface::class))
		{
			$this->getContainer()->alias(RendererInterface::class, "renderer.$rendererName");
		}

		// Add the app path if it exists
		$path = JPATH_TEMPLATES . '/' . strtolower($this->app);

		if (is_dir($path))
		{
			$renderer->addFolder($path);
		}

		$renderer
			->set('user', $application->getUser())
			->set('view', $view)
			->set('layout', $layout)
			->set('app', strtolower($this->getApp()));

		// Retrieve and clear the message queue
		$renderer->set('flashBag', $application->getMessageQueue());
		$application->clearMessageQueue();

		// Add build commit if available
		if (file_exists(JPATH_ROOT . '/current_SHA'))
		{
			$data = trim(file_get_contents(JPATH_ROOT . '/current_SHA'));
			$renderer->set('buildSHA', $data);
		}
		else
		{
			$renderer->set('buildSHA', '');
		}

		return $renderer;
	}

    /**
    * Registers the event listener for the current project.
    *
    * @param   string  $type  The event listener type.
    *
    * @return  $this
    *
    * @since   __DEPLOY_VERSION__
    */
    protected function addEventListener($type)
	{
		/* @type \JTracker\Application $application */
		$application = $this->getContainer()->get('app');

		/*
	    * Add the event listener if it exists.  Listeners are named in the format of <project><type>Listener in the Hooks\Listeners namespace.
	    * For example, the listener for a joomla-cms pull activity would be JoomlacmsPullsListener
	    */
		$baseClass = ucfirst(str_replace('-', '', $application->getProject()->gh_project)) . ucfirst($type) . 'Listener';
		$fullClass = 'App\\Tracker\\Controller\\Hooks\\Listeners\\' . $baseClass;

		if (class_exists($fullClass))
		{
			$listener = new $fullClass;
			$listener->setContainer($this->getContainer());
			$this->dispatcher->addListener($listener);
			$this->listenerSet = true;
		}

		return $this;
	}

    /**
    * Triggers an event if a listener is set.
    *
    * @param   string  $eventName  Name of the event to trigger.
    * @param   array   $arguments  Associative array of arguments for the event.
    *
    * @return  void
    *
    * @since   __DEPLOY_VERSION__
    */
    protected function triggerEvent($eventName, array $arguments)
	{
		if (!$this->listenerSet)
		{
			return;
		}

		/* @type \JTracker\Application $application */
		$application = $this->getContainer()->get('app');

		// Create the event with default arguments.
		$event = (new Event($eventName))
			->addArgument('github', ($this->github ?: GithubFactory::getInstance($application)))
			->addArgument('project', $application->getProject());

		// Add event arguments passed as parameters.
		foreach ($arguments as $name => $value)
		{
			$event->addArgument($name, $value);
		}

		// Add the logger if the event doesn't already have it
		if (!$event->hasArgument('logger'))
		{
			$event->addArgument('logger', $application->getLogger());
		}

		// Trigger the event.
		$this->getDispatcher()->triggerEvent($event);
	}
}