<?php


// Call config file
require_once dirname(dirname(__FILE__)) . '/Config/init_global.php';
?>

<div id="faq" style="max-width:700px;">


<h4 id="WhywouldsomeonebuildaREDCapplugin">Why would someone utilize a REDCap hook?<a class="anchor" href="#WhywouldsomeonebuildaREDCapplugin" title="Link to this section"></a></h4>
<p>
A REDCap hook is often utilized in order to customize or enhance an existing page in REDCap. In the past, if some institutions wanted to add a feature or
functionality to REDCap, they would have to modify the REDCap code in order to do so.
However, this becomes hard to maintain over time since such changes will thus have to be made for each subsequent upgrade performed afterward.
Hooks allow one to add new features or customizations to an installation of REDCap without having to modify the REDCap
base code.
</p>


<h4 id="WhocanbuildaREDCapplugin">Who can build a REDCap hook?<a class="anchor" href="#WhocanbuildaREDCapplugin" title="Link to this section"></a></h4>
<p>
In order to utilize a hook, one must have direct access to the file system on the web server
that hosts REDCap at your local institution. This is required because your Hook Functions file, which is a PHP file that contains
all the hook functions, will need to be hosted on the REDCap web server.
A person with such high level access would likely be a REDCap administrator (i.e. super user) or a web developer on their team.
Such a person would NOT include typical REDCap end-users. Building a hook requires knowledge about creating and
programming web applications, specifically knowing PHP, which is the scripting language in which REDCap is programmed.
If you wish to query REDCap's database tables, you will need to know SQL in order to write queries.
If the hook is meant to output text or HTML to a REDCap page in the user's web browser,
then knowing HTML is necessary (and perhaps some Javascript and CSS if you want to get fancy).
</p>


<h4 id="WhocanuseaREDCapplugin">Who can use a REDCap hook?<a class="anchor" href="#WhocanuseaREDCapplugin" title="Link to this section"></a></h4>
<p>
Answer: Anyone you allow to use it. Since you are creating all your hook's business logic (i.e., the way it behaves),
you control how it is used and who can use it. Most hooks will probably not be limited to certain users, but some could be, if desired.
Most hooks will likely be constructed in order to behave differently for different projects.
It's up to you. You are the architect.
</p>


<h4 id="WhereshouldmypluginfilesbelocatedontheREDCapwebserver">Where should my Hook Functions file be located on the REDCap web server?<a class="anchor" href="#WhereshouldmypluginfilesbelocatedontheREDCapwebserver" title="Link to this section"></a></h4>
<p>
There will be a single PHP file on the web server that contains all your hook functions. The filename and location of that file
does not matter. All that needs to be done for REDCap to begin utilizing your hooks is to set the full path of the Hook Functions file
on the General Configuration page in the Control Center. Once it is set there, REDCap will always check that file when it
needs to call a hook. As an example, you might wish to create a file named hooks.php in your main "redcap" directory, and then create a sub-directory named
"hooks" in that same directory, in which all files utilized by hooks (e.g., PHP, HTML, JavaScript, CSS) might be placed in that
sub-directory.
</p>


<h4 id="WhatisthepreferredmethodinapluginforqueryingREDCapsdatabasetables">What is the preferred method in a hook for querying REDCap's database tables?<a class="anchor" href="#WhatisthepreferredmethodinapluginforqueryingREDCapsdatabasetables" title="Link to this section"></a></h4>
<p>
<strong>It is preferred that you utilize the MySQLi extension</strong> when making calls to the database.
It is also acceptable to utilize PEAR DB or PDO (if your web server supports it).
Please note that REDCap automatically performs a MySQLi connection, so if you wish, your hook may utilize REDCap's existing database connection link,
which is a global variable named <strong>$conn</strong>. If you do, first remember to add the line
<span style="font-family: monospace;white-space: pre;color:#555;font-size: 13px;">global $conn;</span>
near the beginning of your hook's code, which brings the database connection link into the scope of the hook function.
</p>



<h4>Can I share my hook with other REDCap partners?</h4>
<p>
Yes, we highly encourage hook developers to share their hooks with other REDCap partners when applicable.
</p>











<hr style="margin:100px 0 10px;">
<h3 id="HookExamples">Hook Examples</h3>



<h4 style="margin-top:30px;">Example 1: Using the "redcap_survey_page" hook to alter the CSS style of a survey page</h4>
<p>
This hook example demonstrates how to make all survey pages have a red background. While this example may not be
very practical in itself, it illustrates very well how easily hooks can be used to do big things, even with very little code.
</p>
<div class="code"><pre><span class="cp">&lt;?php</span>
/**
 * MAKE ALL SURVEY PAGES HAVE A RED BACKGROUND
 */
function redcap_survey_page()
{
    ?&gt;&lt;style type="text/css"&gt;body { background: red; }&lt;/style&gt;&lt;?php
}
</div>



<h4 style="margin-top:30px;">Example 2: Using the "redcap_control_center" hook to add a new menu item to the left-hand menu in the Control Center</h4>
<p>
This hook example demonstrates how to perform a specific operation on every page in the Control Center,
in which some HTML is output to the page and then manipulated using JavaScript/jQuery to move it to a different location on the page.
This specific example shows how to add new menu items to the left-hand menu, whose DIV has an ID of "control_center_menu".
</p>
<div class="code"><pre><span class="cp">&lt;?php</span>
/**
 * ADD NEW ITEMS TO THE CONTROL CENTER'S LEFT-HAND MENU
 */
function redcap_control_center()
{
    // Output a link inside a div onto the page
    print  "&lt;div id='my_custom_cc_link'&gt;
                &lt;a href='https://mysite.edu/otherpage/'>My Custom link to another page&lt;/a&gt;
            &lt;/div&gt;";

    // Use JavaScript/jQuery to append our link to the bottom of the left-hand menu
    print  "&lt;script type='text/javascript'&gt;
            $(document).ready(function(){
                // Append link to left-hand menu
                $( 'div#my_custom_cc_link' ).appendTo( 'div#control_center_menu' );
            });
            &lt;/script&gt;";
}
</div>



<h4 style="margin-top:30px;">Example 3: Using the "redcap_custom_verify_username" hook to validate LDAP usernames when someone attempts to add
a user to a REDCap project</h4>
<p>
The hook function code below is used by Vanderbilt University in order to verify LDAP usernames when a person is granted access
to a REDCap project. (As background, Vanderbilt's LDAP requires binding with a valid user/password *before* one can search the LDAP directory,
although many LDAP setups do not require binding for searching.) <b>NOTE: If you wish to use this code, it may require some modification
depending on your LDAP setup.</b> Essentially, the hook searches the LDAP directory to verify the username of the REDCap user
that someone is attempting to grant access to in a REDCap project. So if they try to add them and the hook returns an error message,
REDCap will display the custom error message output by the hook function
(e.g., "ERROR: The user $user cannot be granted access because it is not a valid VUnetID or REDCap username.
Please check the username and try again.").
</p>
<div class="code" style="width:850px;"><pre><span class="cp">&lt;?php</span>
/**
 * VERIFY LDAP USER BEFORE ALLOWING NEW USER TO BE GRANTED ACCESS TO A REDCAP PROJECT
 * NOTE: Vanderbilt's LDAP requires binding with a valid user/password *before* you can search the LDAP directory.
 */
function redcap_custom_verify_username($user)
{
	// Obtain $ldapuser and $ldappass to bind with LDAP server, which are stored in a file named
	// "con_redcap_ldap_user.php" on the REDCap web server and are used solely to bind with the LDAP server.
	include "/app001/nrrapp/appsrv/con/con_redcap_ldap_user.php";

	// LDAP setup
	$ldapdsn = array(
		'url'		=> 'ldaps://ldap.vunetid.vanderbilt.edu',
		'port'		=> '636',
		'version'  	=> '3',
		'binddn'   	=> 'uid='.$ldapuser.',ou=special users,dc=vanderbilt,dc=edu',
		'basedn'   	=> 'ou=people,dc=vanderbilt,dc=edu',
		'bindpw'	=> $ldappass
	);

	## CONNECT TO AND QUERY LDAP SERVER
	// Double check LDAP connection variables
	$url = (isset($ldapdsn['host']) && (!isset($ldapdsn['url']) || $ldapdsn['url'] == ""))
		? $ldapdsn['host'] : $ldapdsn['url'];
	$url = (strpos($url, 'ldap://') !== false || strpos($url, 'ldaps://') !== false)
		? $url : "ldap://$url";
	$basedn = (isset($ldapdsn['basedn']) && $ldapdsn['basedn'] != "")
		? $ldapdsn['basedn'] : $ldapdsn['binddn'];
	// Connect to LDAP server. Detect if port is specified, then connect.
	if (isset($ldapdsn['port']) && $ldapdsn['port'] != "") {
		$ldapconn = ldap_connect($url, $ldapdsn['port']);
	} else {
		$ldapconn = ldap_connect($url);
	}
	// Begin searching LDAP server for $user
	$found_user = false;
	if ($ldapconn) {
		// Bind to LDAP server
		$bind = ldap_bind($ldapconn, $ldapdsn['binddn'], $ldapdsn['bindpw']);
		// Search for user in LDAP directory
		if ($bind) {
			// Loop through each user
			$sr = ldap_search($ldapconn, $basedn, "uid=$user");
			if ($sr) {
				$data = ldap_get_entries($ldapconn, $sr);
				$found_user = (isset($data[0]));
			}
		}
		// Disconnect from LDAP server
		ldap_close($ldapconn);
	}
	if (!$found_user) {
		return array('status'=>false, 'message'=>"<b>ERROR:</b> The user \"<b>$user</b>\" cannot be granted access because
			it is not a valid VUnetID or REDCap username. Please check the username and try again.");
	} else {
		return array('status'=>true, 'message'=>'');
	}
}
</div>


</div>