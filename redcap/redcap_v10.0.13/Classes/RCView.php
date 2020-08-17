<?php



/**
 * REDCap View is a class of static functions that build HTML elements.
 */
class RCView
{
	/** The amount to indent each level of HTML. */
	const INDENT = "\t";

	/** HTML for non-breaking space. */
	const SP = '&nbsp;';

	/** Used to generate unique IDs for HTML elements. */
	private static $jsId = 0;

	/** Returns a unique HTML element ID. */
	static function getId() {
		self::$jsId++;
		return 'redcapJSAutoId_' . self::$jsId;
	}

	/** 
	 * Returns a string from the language file, wrapped in a span (unless otherwise specified).
	 * @param string $lang_key The language key
	 * @param string $wrap The element type to wrap the string in (default: span)
	 * @return string 
	 */
	static function tt($lang_key, $wrap = "span") {
		$s = $GLOBALS["lang"][$lang_key];
		if (empty($wrap)) return $s;
		return "<$wrap data-rc-lang=\"$lang_key\">$s</$wrap>";
	}

	/** 
	 * Returns the string passed as an argument, wrapped in a span (unless otherwise specified).
	 * This is a marker for text that yet needs to be made translatable (i.e. added to English.ini).
	 * @param string $s The string that eventually should be made translatable
	 * @param string $wrap The element type to wrap the string in (default: span)
	 * @return string
	 */
	static function ttfy($s, $wrap = "span") {
		return empty($wrap) ? $s : "<$wrap class=\"ttfy\">$s</$wrap>";
	}

	/** Returns a Font Awesome icon */
	static function fa($classes, $style="") {
		$h = "<i";
		if ($style != "") $h .= " style=\"$style\"";
		$h .= " class=\"$classes\"></i>";
		return $h;
	}

	/**#@+ Convenience functions for various HTML elements. */
	static function i($attrs, $html='') { 
		if (!is_array($attrs)) {
			$html = $attrs;
			$attrs = array();
		}
		return self::toHtml('i', $attrs, $html, true); 
	}
	static function u($attrs, $html='') { 
		if (!is_array($attrs)) {
			$html = $attrs;
			$attrs = array();
		}
		return self::toHtml('u', $attrs, $html, true); 
	}
	static function b($attrs, $html='') { 
		if (!is_array($attrs)) {
			$html = $attrs;
			$attrs = array();
		}
		return self::toHtml('b', $attrs, $html, true); 
	}
	static function br() { return self::toHtml('br', array(), false, true); }
	static function input($attrs=array()) { return self::toHtml('input', $attrs, false); }
	static function hidden($attrs=array()) {
		$attrs['type'] = 'hidden';
		return self::toHtml('input', $attrs, false);
	}
	static function submit($attrs=array()) {
		$attrs['type'] = 'submit';
		return self::toHtml('input', $attrs, false);
	}
	static function file($attrs=array()) {
		$attrs['type'] = 'file';
		return self::toHtml('input', $attrs, false);
	}
	static function checkbox($attrs=array()) {
		$attrs['type'] = 'checkbox';
		return self::toHtml('input', $attrs, false);
	}
	static function text($attrs=array()) {
		$attrs['type'] = 'text';
		return self::toHtml('input', $attrs, false);
	}
	static function radio($attrs=array()) {
		$attrs['type'] = 'radio';
		return self::toHtml('input', $attrs, false);
	}
	static function font($attrs, $html, $suppressNewlines=true) {
		return self::toHtml('font', $attrs, $html, $suppressNewlines);
	}
	static function label($attrs, $html, $suppressNewlines=true) {
		return self::toHtml('label', $attrs, $html, $suppressNewlines);
	}
	static function button($attrs=array(), $html='') {
		return self::toHtml('button', $attrs, $html);
	}
	static function iframe($attrs=array(), $html='') {
		return self::toHtml('iframe', $attrs, $html);
	}
	static function div($attrs=array(), $html='') {
		return self::toHtml('div', $attrs, $html);
	}
	static function span($attrs, $html, $suppressNewlines=true) {
		return self::toHtml('span', $attrs, $html, $suppressNewlines);
	}
	static function form($attrs=array(), $html='') {
		return self::toHtml('form', $attrs, $html);
	}
	static function a($attrs=array(), $html='') {
		return self::toHtml('a', $attrs, $html, true, true);
	}
	static function h1($attrs=array(), $html='') {
		return self::toHtml('h1', $attrs, $html);
	}
	static function h2($attrs=array(), $html='') {
		return self::toHtml('h2', $attrs, $html);
	}
	static function h3($attrs=array(), $html='') {
		return self::toHtml('h3', $attrs, $html);
	}
	static function h4($attrs=array(), $html='') {
		return self::toHtml('h4', $attrs, $html);
	}
	static function h5($attrs=array(), $html='') {
		return self::toHtml('h5', $attrs, $html);
	}
	static function h6($attrs=array(), $html='') {
		return self::toHtml('h6', $attrs, $html);
	}
	static function table($attrs=array(), $html='') {
		return self::toHtml('table', $attrs, $html);
	}
	static function tbody($attrs=array(), $html='') {
		return self::toHtml('tbody', $attrs, $html);
	}
	static function tr($attrs=array(), $html='') {
		return self::toHtml('tr', $attrs, $html);
	}
	static function td($attrs=array(), $html='') {
		return self::toHtml('td', $attrs, $html);
	}
	static function thead($attrs=array(), $html='') {
		return self::toHtml('thead', $attrs, $html);
	}
	static function th($attrs=array(), $html='') {
		return self::toHtml('th', $attrs, $html);
	}
	static function fieldset($attrs=array(), $html='') {
		return self::toHtml('fieldset', $attrs, $html);
	}
	static function legend($attrs=array(), $html='') {
		return self::toHtml('legend', $attrs, $html);
	}
	static function img($attrs=array()) {
		// Does not require that APP_PATH_IMAGES be used in SRC attr, but if added, then don't add a second time.
		if (isset($attrs['src']) && substr($attrs['src'], 0, strlen(APP_PATH_IMAGES)) != APP_PATH_IMAGES) {
			$attrs['src'] = APP_PATH_IMAGES . $attrs['src'];
		}
		return self::toHtml('img', $attrs, false);
	}
	static function p($attrs=array(), $html='') {
		return self::toHtml('p', $attrs, $html);
	}
	static function li($attrs=array(), $html='') {
		return self::toHtml('li', $attrs, $html);
	}
	static function ul($attrs=array(), $html='') {
		return self::toHtml('ul', $attrs, $html);
	}
	static function ol($attrs=array(), $html='') {
		return self::toHtml('ol', $attrs, $html);
	}
	static function textarea($attrs=array(), $html='') {
		return self::toHtml('textarea', $attrs, $html, false, true);
	}
	static function pre($attrs=array(), $html='') {
		return self::toHtml('pre', $attrs, $html, false, true);
	}
	static function code($attr, $html) {
		$attr['style'] = 'font-family: monospace;';
		return self::pre($attr, "<code>$html</code>");
	}
	/**#@-*/

	/** Makes a link using an icon.
	 * @param string $id the ID attribute of the link.
	 * @param string $icon the image src.
	 * @param string $title the title and alt for the image.
	 * @param string $url the href for the link (minus query string).
	 * @param array $qvars the variables used to make the query string. The values will be automatically
	 * encoded for URL.
	 */
	static function iconLink($id, $icon, $title, $url, $qvars) {
		$imgAttrs = array('src' => $icon, 'title' => $title, 'alt' => $title);
		$imgHtml = self::toHtml('img', $imgAttrs, false, true);
		if (is_array($qvars) && count($qvars) > 0) {
			$pairs = array();
			foreach ($qvars as $key => $val) $pairs[] = "$key=" . urlencode($val);
			$url .= '?' . implode('&', $pairs);
		}
		$aAttrs = array('id' => $id, 'href' => $url);
		return self::toHtml('a', $aAttrs, $imgHtml);
	}

	/** Makes a very simple unordered list. */
	static function simpleList($items, $escape=false, $listAttrs=array()) {
		foreach ($items as $i) $html .= self::toHtml('li', array(), $escape ? self::escape($i) : $i);
		return self::toHtml('ul', $listAttrs, $html);
	}

	/**
	 * Makes a select box.
	 * @param array $attrs see self::toHTML() $attrs param.
	 * @param array $opts the select box options. Values will be automatically escaped for HTML and
	 * trimmed to a reasonable size.
	 * @param mixed $selKey the option to select by default. Is an array if multiple options selected on multi-select dropdown
	 */
	static function select($attrs, $opts, $selKey=null, $maxOptChars=55)
	{
		$o = '';
		$maxNormal = $maxOptChars;
		$maxShout = round($maxOptChars/2); // show less characters if the option is SHOUTING
		// Set values for multiple selection drop-downs
		$isMultiSelect = isset($attrs['multiple']);
		if (!$isMultiSelect && is_array($selKey)) $selKey = array_pop($selKey);
		if (is_array($selKey) && empty($selKey)) $selKey = null;
		$hasMultiplePreselected = is_array($selKey);
		// Make sure all values in preselection array are strings (so we can do a proper strict in_array later)
		if ($hasMultiplePreselected) {
			foreach ($selKey as $key=>$val) {
				$selKey[$key] = $val."";
			}
		}
		// Loop through all choices
		foreach ($opts as $key => $val) {
			// HTML for this loop
			// If $val is an array, then assume it's an OPTGROUP
			if (is_array($val)) {
				// Optgroup
				$this_o = '';
				foreach ($val as $key2 => $val2) {
					// Normal option inside optgroup
					// $max = preg_match('/[a-z]/', $val2) ? $maxNormal : $maxShout;
					$max = $maxNormal;
					$oAttrs = array('value' => $key2);
					// Determine if we should pre-select this option
					if ((!$hasMultiplePreselected && $selKey."" === $key2."")
						|| ($hasMultiplePreselected && in_array($key2."", $selKey, true))) {
						$oAttrs['selected'] = 'selected';
					}
					// Truncate if option length too long
					if (strlen($val2) > $max) $val2 .= '...';
					// Output choice
					$this_o .= self::toHtml('option', $oAttrs, self::escape($val2));
				}
				// Wrap the options with the optgroup tag
				$o .= self::toHtml('optgroup', array('label'=>$key), $this_o);
			} else {
				// Normal option
				// $max = preg_match('/[a-z]/', $val) ? $maxNormal : $maxShout;
				$max = $maxNormal;
				$oAttrs = array('value' => $key);
				// Determine if we should pre-select this option
				if ((!$hasMultiplePreselected && $selKey."" === $key."")
					|| ($hasMultiplePreselected && in_array($key."", $selKey, true))) {
					$oAttrs['selected'] = 'selected';
				}
					// Truncate if option length too long
				if (strlen($val) > $max) $val .= '...';
					// Output choice
				$o .= self::toHtml('option', $oAttrs, self::escape($val));
			}
		}
		return self::toHtml('select', $attrs, $o);
	}

	/**
	 * Creates an Enabled/Disabled select box.
	 * @param array $attrs see self::toHTML() $attrs param.
	 * @param boolean $enabled true if the select box should default to Enabled;
	 * false to default to Disabled.
	 */
	static function selectEnabledDisabled($attrs, $enabled) {
		$opts = array(0 => RCL::disabled(), 1 => RCL::enabled());
		return self::select($attrs, $opts, ($enabled ? 1 : 0));
	}

	/** Displays a box with an error message inside, prepended with error icon/text. */
	static function errorBox($html, $id='') {
		global $lang;
		$h = '';
		$h .= self::toHtml('img', array('src' => APP_PATH_IMAGES . 'exclamation.png'));
		$h .= $lang['global_01'] . $lang['colon'] . ' ';
		$attrs = array('class' => 'red', 'style' => 'margin-bottom: 20px;', 'id' => $id, 'title' => $lang['global_01']);
		return self::toHtml('div', $attrs, $h . $html);
	}

	/** Displays a box with a success message inside, prepended with check icon. */
	static function successBox($html, $id='') {
		global $lang;
		$h = '';
		$h .= self::toHtml('img', array('src' => APP_PATH_IMAGES . 'tick.png'));
		$h .= $lang['setup_08'] . ' ';
		$attrs = array('class' => 'darkgreen', 'style' => 'margin-bottom: 20px;', 'id' => $id, 'title' => $lang['setup_08']);
		return self::toHtml('div', $attrs, $h . $html);
	}

	/** Displays a box with a warning message inside, prepended with warning icon. */
	static function warnBox($html, $id='') {
		global $lang;
		$h = '';
		$h .= self::toHtml('img', array('src' => APP_PATH_IMAGES . 'error.png'));
		$h .= $lang['global_03'] . $lang['colon'] . ' ';
		$attrs = array('class' => 'yellow', 'style' => 'margin-bottom: 20px;', 'id' => $id, 'title' => $lang['global_03']);
		return self::toHtml('div', $attrs, $h . $html);
	}

	/** Displays a box with a confirmation message inside, prepended with confirmation icon. */
	static function confBox($html, $id='') {
		global $lang;
		$h = '';
		$h .= self::toHtml('img', array('src' => APP_PATH_IMAGES . 'exclamation_orange.png'));
		$attrs = array('class' => 'yellow', 'style' => 'margin-bottom: 20px;', 'id' => $id, 'title' => $lang['global_02']);
		return self::toHtml('div', $attrs, $h . $html);
	}

	/**
	 * Uses flexigrid to build a very simple table.
	 * @param array $rows the first element is the table title (string), the
	 * second element is an array of column headers, and the subsequent elements
	 * are arrays of column data.
	 * @param type $widths the width in pixels of each column.
	 */
	static function simpleGrid($rows, $widths) {
		$r = '';
		// build the title row
		$title = array_shift($rows);
		if (!empty($title))
			$r .= self::div(array('class' => 'mDiv'), self::div(array('class' => 'ftitle'), $title));
		// build the header row
		$hdr = array_shift($rows);
		if ($hdr !== null) {
			$h = '';
			for ($i = 0; $i < count($widths); $i++) {
				$h .= self::th(array(), self::div(array('style' => 'width: ' . $widths[$i] . 'px;'), $hdr[$i]));
			}
			$r .= self::div(array('class' => 'hDiv'), self::div(array('class' => 'hDivBox'),
							self::table(array('cellspacing' => '0'), self::tr(array(), $h))));
		}
		// build the data rows
		$h = ''; $rowCnt = 1;
		foreach ($rows as $row) {
			$cells = '';
			for ($i = 0; $i < count($widths); $i++) {
				$cells .= self::td(array(), self::div(array('style' => 'width: ' . $widths[$i] . 'px;'), $row[$i]));
			}
			$rowAttrs = $rowCnt % 2 == 0 ? array('class' => 'erow') : array();
			$h .= self::tr($rowAttrs, $cells);
			$rowCnt++;
		}
		$r .= self::div(array('class' => 'bDiv'), self::table(array('cellspacing' => '0'), $h));
		$totalWidth = array_sum($widths) + count($widths);
		return self::div(array('class' => 'flexigrid', 'style' => 'width: ' . $totalWidth . 'px;'), $r);
	}

	/**
	 * Builds a simple 2-column table intended for user input.
	 * @param string $title a title displayed at the top of the table.
	 * @param array $rowArr each element is an array representing a row:
	 * $arr = current($rowArr);
	 * $arr['label'] = HTML explanation of the required input
	 * $arr['input'] = HTML representing the input field(s)
	 * $arr['info'] = HTML additional instructions to appear under the input.
	 */
	static function simpleInputTable($title, $rowArr) {
		$h = '';
		if (!empty($title)) {
			$h .= self::tr(array(),
							self::td(array('colspan' => '2', 'style' => 'padding: 10px;'),
											self::font(array('class' => 'redcapBlockTitle'), $title)));
		}
		foreach ($rowArr as $arr) {
			$label = empty($arr['label']) ? '' : $arr['label'];
			$input = empty($arr['input']) ? '' : $arr['input'];
			$info = empty($arr['info']) ? '' : $arr['info'];
			$h .= self::tr(array(),
							self::td(array('class' => 'cc_label'), $label) .
							self::td(array('class' => 'cc_data'), $input .
											(empty($info) ? '' : self::div(array('class' => 'cc_info'), $info))));
		}
		return self::table(array('style' => 'border: 1px solid #ccc; background-color: #f0f0f0; margin: 20px 0;'), $h);
	}

	/**
	 * Escapes a string for use in HTML.
	 * @param boolean/integer $removeAllTags true to blindly escape all HTML tags;
	 * false to first perform some user-friendly sanitation in cases where the
	 * HTML is displayed to the user (e.g., in a title).
	 */
	static function escape($s, $removeAllTags=true) {
		// Set temporary replacement for &nbsp; HTML character code so that html_entity_decode() doesn't mangle it
		$nbsp_replacement = '|*|RC_NBSP|*|';
	// Replace &nbsp; characters
		$s = str_replace(array("&amp;nbsp;", "&nbsp;"), array($nbsp_replacement, $nbsp_replacement), $s);
		// HTML decode
		$s = html_entity_decode($s, ENT_QUOTES);
		// Replace &nbsp; characters
		$s = str_replace($nbsp_replacement, "&nbsp;", $s);
		// Remove tags if needed
		if ($removeAllTags) $s = strip_tags2($s);
		// Now escape it
		$s_esc = htmlspecialchars($s, ENT_QUOTES);
		// If it has issues with non-characters getting escaped, then fall back to using ENT_SUBSTITUTE
		if (strlen($s_esc) == 0 && strlen($s) > 0) {
			$s = htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
		} else {
			$s = $s_esc;
		}
		// Return string
		return $s;
	}

	/**
	 * Quotes a PHP string for inclusion in JavaScript. Example:
	 * PHP:
	 * $foo = 'I love "quotes"';
	 * JS:
	 * alert(<?php RCView::strToJS($foo); ?>);
	 * Also replaces newlines with spaces because INI strings can have newlines
	 * purely for code-formatting reasons.
	 */
	static function strToJS($s) {
		return '"' . str_replace(array('"', "\r\n", "\n"), array('\\"', " ", " "), $s) . '"';
	}

	/**
	 * Builds an HTML element as requested by the public functions of this class.
	 * @param string $elemType e.g. input, form, hidden, etc.
	 * @param array $attrs keys are attribute names and values are the attribute values. All values will
	 * be encoded for HTML, therefore JavaScript should *NOT* be used here; instead, use jQuery to
	 * bind to events of this element within a $(function(){}) block.
	 * @param string $html any HTML to be included within the open/close tags of this element. If this
	 * is === FALSE, then the tag of this element will self-close.
	 * @param boolean $suppressNewline true if you do not want to follow this element with a newline.
	 * @param boolean $forceOneLiner if true, no indenting will be done and no newlines
	 * will be added that preceed or follow the $html.
	 */
	private static function toHtml($elemType, $attrs=array(), $html=false, $suppressNewline=false, $forceOneLiner=false) {
		$h = "<$elemType";
		if(!empty($attrs))
		{
			foreach ($attrs as $key => $val) {
				if ($key == "") continue;
				$h .= " $key=\"" . self::escape($val, true) . '"';
			}
		}
		if ($html === false) $h .= "/>";
		else {
			$h .= ">";
			if (strlen($html) > 0) {
				// if there are no newlines in the HTML then assume we want a one-liner
				if (strpos($html, "\n") === false || $forceOneLiner) $h .= $html;
				// newlines in the HTML imply that we should add a level of indentation
				else {
					$h .= "\n";
					// ugly hack to deal with elements that contain newline-sensitive text
					$hackMap = array();
					foreach (array('textarea', 'pre') as $elem) {
						$elem . ' ' . preg_match_all("|<$elem.*?>.*?</$elem>|is", $html, $matches);
						foreach ($matches[0] as $match) {
							$hackKey = '{REPLACEME_HACK_' . count($hackMap) . '}';
							$hackMap[$hackKey] = $match;
							$html = str_replace($match, $hackKey, $html);
						}
					}
					$lines = explode("\n", $html);
					foreach ($lines as $line)
						if (!empty($line)) $h .= self::INDENT . "$line\n";
					// clean up after our ugly hack
					foreach ($hackMap as $hackKey => $hackStr)
						$h = str_replace($hackKey, $hackStr, $h);
				}
			}
			$h .= "</$elemType>";
		}
		if (!$suppressNewline) $h .= "\n";
		return $h;
	}

	/**
	* Exports file data, causing the user's browser to download/open the file.
	* @param string $filename the name that the file will be given.
	* @param string $content the contents of the file.
	* @param string $type the MIME type.
	*/
	static function exportFile($filename, $content, $type) {
		header('Pragma: anytextexeptno-cache', true);
		header('Content-type: ' . $type);
		header('Content-Disposition: attachment; filename="'.$filename.'"');
		header('Content-length: ' . strlen($content));
		echo $content;
		exit();
	}

	/** Returns button to return to previous page (with default text) **/
	public static function btnGoBack($text=null) {
		global $lang;
		if ($text == null) $text = $lang['global_77'];
		return self::button(array('class'=>'jqbuttonmed','onclick'=>'history.go(-1);return false;'), "<i class=\"fas fa-chevron-circle-left\"></i> $text");
	}

	/** Returns a note to display to the user regarding disabled API status. */
	static function disabledAPINote() {
		global $lang;
		global $super_user;
		$note = '';
		$note .= $lang['api_01'] . ' ';
		if ($super_user) {
			$note .= $lang['api_07'] . ' ';
			$note .= RCView::a(array('target' => '_blank',
					'style' => 'text-decoration:underline;',
					'href' => APP_PATH_WEBROOT . 'ControlCenter/modules_settings.php'),
						$lang['graphical_view_78']);
		}
		else $note .= $lang['api_06'];
		return $note;
	}

	/** Returns hidden div with simpleDialog class to be displayed via jQueryUI dialog() function **/
	public static function simpleDialog($content="",$title="",$id="") {
		$titleAttr = ($title == "") ? "" : "title";
		$idAttr    = ($id == "") ? "" : "id";
		return self::div(array('class'=>'simpleDialog',$titleAttr=>$title,$idAttr=>$id), $content);
	}

	/**
	 * RENDER TABS FROM ARRAY WITH 'PAGE' AS KEY AND LABEL AS VALUE
	 */
	public static function renderTabs($tabs=array())
	{
		// Get request URI
		$request_uri = $_SERVER['REQUEST_URI'];
		// If request URI ends with ".php?", then remove "?"
		if (substr($request_uri, -5) == '.php?') $request_uri = substr($request_uri, 0, -1);
		// Get query string parameters for the current page's URL
		$params = (strpos($request_uri, ".php?") === false) ? array() : explode("&", parse_url($request_uri, PHP_URL_QUERY));
		?>
		<div id="sub-nav" style="margin:5px 0 20px;">
			<ul>
				<?php
				foreach ($tabs as $this_url=>$this_label)
				{
					// Parse any querystring params in $this_url and check for match to see if this should be the Active tab
					$these_params = (strpos($this_url, ".php?") === false) ? array() : explode("&", parse_url($this_url, PHP_URL_QUERY));
					// Get $this_page. Check if has route in query string.
					parse_str(parse_url($this_url, PHP_URL_QUERY), $these_param_pairs);
					if (isset($these_param_pairs['route'])) {
						$this_page = $these_param_pairs['route'];
					} else {
						$this_page = parse_url($this_url, PHP_URL_PATH);
					}
					// Add project_id if on a project-level page
					if (defined("PROJECT_ID")) {
						$these_params[] = "pid=" . PROJECT_ID;
					}
					// Format query string for the url to add 'pid'
					$this_url = parse_url($this_url, PHP_URL_PATH);
					if (!empty($these_params)) {
						$this_url .= "?" . implode("&", $these_params);
					}
					// Check for Active tab
					$isActive = false;
					if ($this_page == PAGE && count($these_params) == count($params)) {
						// Make sure all params are same. Loop till it finds mismatch.
						$isActive = true;
						foreach ($params as $this_param) {
							if (!in_array($this_param, $these_params)) $isActive = false;
						}
					}
					?>
					<li <?php if ($isActive) echo 'class="active"'?>>
						<a href="<?php echo APP_PATH_WEBROOT . $this_url ?>" style="font-size:13px;color:#393733;padding:6px 9px 5px 10px;"><?php echo $this_label ?></a>
					</li>
					<?php
				} ?>
			</ul>
		</div>
		<div class="clear"></div>
		<?php
	}
}
