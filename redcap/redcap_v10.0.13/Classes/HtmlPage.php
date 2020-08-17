<?php

class HtmlPage
{
    /*
    * PUBLIC PROPERTIES
    */

    // @var htmltitle string
    // @access public
    var $htmltitle;

    // @var stylesheets array
    // @access public
    var $stylesheets;

	// @var inlinestyle array
	// @access public
	var $inlinestyle;

    // @var externalJS array
    // @access public
    var $externalJS;

    /*
    * PRIVATE FUNCITONS
    */

    // @return HtmlPage
    // @access private
    function __construct()
    {
        // Default page title
        $this->htmltitle    = 'REDCap';
        // Array of stylesheets
        $this->stylesheets = array(
                                array('media'=>'screen,print', 'href'=>APP_PATH_WEBPACK . 'css/bundle.css'),
                                array('media'=>'screen,print', 'href'=>APP_PATH_WEBPACK . 'css/fontawesome/css/all.min.css'),
                                array('media'=>'screen,print', 'href'=>APP_PATH_CSS . 'messenger.css'),
			                    array('media'=>'screen,print', 'href'=>APP_PATH_CSS . 'style.css')
        					 );
		// Array of inline style
		$this->inlinestyle  = array();
        // Array external javascript files
        $this->externalJS   = array(APP_PATH_WEBPACK . 'js/bundle.js', APP_PATH_JS . 'Libraries/bundle.js', APP_PATH_JS . 'base.js');
    }

	/**
     * PUBLIC FUNCITONS
     */

	public function ProjectHeader()
	{
		extract($GLOBALS);
		include APP_PATH_DOCROOT . 'ProjectGeneral/header.php';
	}

	public function ProjectFooter()
	{
		extract($GLOBALS);
		include APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';
	}

	public function CacheBuster($path)
    {
		// Cache-busting: Get full file path of the JS file
		list ($nothing, $pathUnderResources) = explode("/redcap_v".REDCAP_VERSION."/Resources/", $path, 2);
		// Prepend path to Resources
		$fullLocalPath = APP_PATH_DOCROOT."Resources".DS.str_replace("/", DS, $pathUnderResources);
		if (file_exists($fullLocalPath) && is_file($fullLocalPath)) {
			// Clear the cache in PHP so that filemtime() will work as desired on each request
			clearstatcache(true, $fullLocalPath);
			// Set path path with timestamp appended
			$path .= '?' . filemtime($fullLocalPath);
		}
		// Return new path
        return $path;
    }

    // @return void
    // @access public
    function PrintHeader($addDivContainerWrapper=true)
    {
        global $isIOS;

        print   '<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd">' . "\n" .
                '<html lang="en">' . "\n" .
                '<head>' . "\n" .
                '<meta name="googlebot" content="noindex, noarchive, nofollow, nosnippet">' . "\n" .
                '<meta name="robots" content="noindex, noarchive, nofollow">' . "\n" .
                '<meta name="slurp" content="noindex, noarchive, nofollow, noodp, noydir">' . "\n" .
                '<meta name="msnbot" content="noindex, noarchive, nofollow, noodp">' . "\n" .
                '<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />' . "\n" .
                '<meta http-equiv="Cache-Control" content="no-cache">' . "\n" .
                '<meta http-equiv="Pragma" content="no-cache">' .  "\n" .
                '<meta http-equiv="expires" content="0">' .  "\n" .
                '<meta charset="utf-8">' . "\n" .
                '<meta http-equiv="X-UA-Compatible" content="IE=edge">' . "\n" .
                '<meta name="viewport" content="width=device-width, initial-scale=1">' . "\n" .
                '<title>' . $this->htmltitle . '</title>' . "\n" .
                '<link rel="shortcut icon" href="' . APP_PATH_IMAGES . 'favicon.ico">' . "\n" .
                '<link rel="apple-touch-icon-precomposed" href="' . APP_PATH_IMAGES . 'apple-touch-icon.png">' . "\n";

		// Add all stylesheets
		foreach ($this->stylesheets as $this_css)
		{
			// Cache-busting
			$this_css['href'] = $this->CacheBuster($this_css['href']);
			// Output to HEAD
			print '<link rel="stylesheet" type="text/css" media="' . $this_css['media'] . '" href="' . $this_css['href'] . '"/>' . "\n";
		}
		
        // Add all external javascript file
        foreach (array_unique($this->externalJS) as $path)
        {
            // Cache-busting
            $path = $this->CacheBuster($path);
			// Output to HEAD
            print  '<script type="text/javascript" src="' . $path . '"></script>' . "\n";
        }

		?>
		<!--[if IE 9]>
		<link rel="stylesheet" type="text/css" href="<?php echo APP_PATH_CSS ?>bootstrap-ie9.min.css">
		<script type="text/javascript">$(function(){ie9FormFix()});</script>
		<![endif]-->
		<?php

        print  '</head>' . "\n";
		print  '<body>';

		// REDCap Hook injection point: Pass PROJECT_ID constant (if defined).
		Hooks::call('redcap_every_page_top', array(defined('PROJECT_ID') ? PROJECT_ID : null));

		// iOS CSS Hack for rendering drop-down menus with a background image
		if ($isIOS)
		{
			print  '<style type="text/css">select { padding-right:14px !important; background-image:url("'.APP_PATH_IMAGES.'arrow_state_grey_expanded.png") !important; background-position:right !important; background-repeat:no-repeat !important; }</style>';
		}

		// Add all inlinestyle
		// -------------------
		foreach($this->inlinestyle AS $csstag) {
			print  $csstag;
		}

		// Do CSRF token check (using PHP with jQuery)
		System::createCsrfToken();

		// Render Javascript variables needed on all pages for various JS functions
		renderJsVars();

		// Initialize auto-logout popup timer and logout reset timer listener
		initAutoLogout();

		// Render hidden divs used by showProgress() javascript function
		renderShowProgressDivs();

		// Render divs holding javascript form-validation text (when error occurs), so they get translated on the page
		renderValidationTextDivs();

		// Display notice that password will expire soon (if utilizing $password_reset_duration for Table-based authentication)
		Authentication::displayPasswordExpireWarningPopup();

		// Check if need to display pop-up dialog to SET UP SECURITY QUESTION for table-based users
		Authentication::checkSetUpSecurityQuestion();

		// Returns hidden div with X number of random characters. This helps mitigate hackers attempting a BREACH attack.
		getRandomHiddenText();

		// Main page container div for non-project pages
		if ($addDivContainerWrapper) {
			print  '<div id="pagecontainer" class="container-fluid" role="main">' .
				'<div id="container">' .
				'<div id="pagecontent">';
		}
    }

    // @return void
    // @access public
    function PrintHeaderExt() {
		$this->addStylesheet("home.css", 'screen,print');
		$this->PrintHeader();
		// Adjust some CSS
		print  "<style type='text/css'>#pagecontent {margin: 0px !important;} #footer { display:none !important; }</style>";
	}

    // @return void
    // @access public
    function PrintFooterExt() {
		$this->PrintFooter();
	}

    // @return void
    // @access public
    function PrintFooter() {

		global $redcap_version;

		print   		'</div>' .
					'</div>';

		// Display REDCap copyright (but not in Mobile Site view), and for survey pages only display "Powered by REDCap"
		$isSurveyPage = (PAGE == "surveys/index.php" || (defined("NOAUTH") && isset($_GET['s'])));
		$copyrightText = $isSurveyPage ? 'Powered by REDCap</a>' : 'REDCap ' . $redcap_version . '</a> - &copy; ' . date("Y") . ' Vanderbilt University';
		print 	'<div id="footer" class="d-none d-sm-block col-md-12" aria-hidden="true">' .
					'<a href="https://projectredcap.org" tabindex="-1" target="_blank">' . $copyrightText .
				'</div>';
		print	'</div>';

		// Messenger panel for non-project pages
		if (!defined("EHR") && PAGE != 'surveys/index.php') {
		    print Messenger::renderMessenger();
		}

		print '</body></html>';

    }

	// @return void
	// @access public
	function addInlineStyle($css_string)
	{
		array_push($this->inlinestyle, "\n<style type=\"text/css\">\n$css_string\n</style>\n");
	}

    // @return void
    // @access public
    function addStylesheet($file, $media)
    {
		$this->stylesheets[] = array('media'=>$media, 'href'=>APP_PATH_CSS . $file);
    }

    // @return void
    // @access public
    function addExternalJS($path)
    {
        array_push($this->externalJS, $path);
    }

    function setPageTitle($var)
    {
		$this->htmltitle = $var;
    }

}
