<?php


/**
 * Controller is a simple implementation of a Controller.
 *
 * It provides methods to common features needed in controllers.
 */
abstract class Controller
{
    /**
     * Renders a view.
     *
     * @param string   $rcViewName The view name
     * @param array    $parameters An array of parameters to pass to the view
     *
     * @return void		Outputs HTML of view
     */
    public function render($rcViewName, $rcViewParameters=array())
    {
		// Inject the parameters that were passed
		if (is_array($rcViewParameters) && !empty($rcViewParameters)) extract($rcViewParameters);
		// Include the view if it exists
		if (file_exists(APP_PATH_VIEWS . $rcViewName)) include APP_PATH_VIEWS . $rcViewName;
		else throw new Exception("The view \"$rcViewName\" was not found in the directory \"" . APP_PATH_VIEWS . "\".");
    }
}