<?php


// Call config file
require_once dirname(dirname(__FILE__)) . '/Config/init_global.php';
?>

<div id="faq" style="max-width:700px;">


<h4 id="WhywouldsomeonebuildaREDCapplugin">Why would someone build a REDCap plugin?<a class="anchor" href="#WhywouldsomeonebuildaREDCapplugin" title="Link to this section"></a></h4>
<p>
A REDCap plugin can be built for a variety of reasons. It may be to build a custom PHP page that simply piggybacks REDCap's authentication (if your plugin's users would also be REDCap users), thus preventing one from having to construct their own web application's method of authentication from scratch. Also, it could be used just for connecting to and querying REDCap's database tables in a quick and easy fashion. Of course, the major advantage of having a REDCap plugin is that you can construct it such that it can be utilized in REDCap as if it were an official page within REDCap itself. Such could be accomplished in tandem with the Project Bookmarks functionality, which allows you to create a link to your plugin on a REDCap project's left-hand menu to allow easy navigation between that project and your plugin, thus making creating seamless navigation to and from your plugin.
</p>

<h4 id="HowarepluginsaccessedinREDCap">How are plugins accessed in REDCap?<a class="anchor" href="#HowarepluginsaccessedinREDCap " title="Link to this section"></a></h4>
<p>
Since plugins have their own specific URL where they can be accessed, a user must know the exact URL in order to navigate to a plugin, or the user may use
one of the two types of configurable links in REDCap to invoke a plugin (seen below):
<ul><li>Project Bookmarks</li><li>Custom Application Links</li></ul>
</p>
<p>Custom Application Links are system-level, that is, they apply to all projects. They can be set up on the Custom Application Links page in the Control Center.
Once defined, they will appear for all projects in the left hand menu at the bottom of the "Applications" panel. </p>
<p>Project Bookmarks are project-level, applying only to the project in which they are defined.
They can be set up by clicking the button in the "Set up project bookmarks" section of the Project Setup page in a given REDCap project.
Once defined, these will appear only for that single project in the left hand menu in the "Project Bookmarks" panel. </p>
<p>Apart from these differences, Custom Application Links and Project Bookmarks operate identically. </p>
</p>


<h4 id="WhocanbuildaREDCapplugin">Who can build a REDCap plugin?<a class="anchor" href="#WhocanbuildaREDCapplugin" title="Link to this section"></a></h4>
<p>
In order to build a plugin that interfaces with REDCap properly, one must have direct access to the file system on the web server that hosts REDCap at your local institution. This is required because your plugin file(s) will need to be hosted on the REDCap web server. A person with such high level access would likely be a REDCap administrator (i.e. super user) or a web developer on their team. Such a person would NOT include typical REDCap end-users. Building a plugin requires knowledge about creating and programming web applications, specifically knowing PHP, which is the scripting language in which REDCap is programmed. If you don't know much PHP at all, you can probably still get by by utilizing parts of the examples below if your plugin is mostly just HTMl and uses PHP very little. If you wish to query REDCap's database tables, you will need to know SQL in order to write queries. If the plugin is meant to output a web page in the user's web browser, then knowing HTML is necessary (and perhaps some Javascript and CSS if you want to get fancy).
</p>
<h4 id="WhocanuseaREDCapplugin">Who can use a REDCap plugin?<a class="anchor" href="#WhocanuseaREDCapplugin" title="Link to this section"></a></h4>
<p>
Answer: Anyone you allow to use it. Since you are creating your own custom plugin, you control how it is used and who can use it. You may limit access to the plugin to only specific REDCap projects and/or to only specific REDCap users, or conversely, you may make it fully available to all projects and users in REDCap in your REDCap installation. It's up to you. You are the architect.
</p>
<h4 id="Howdoesonecalltheredcap_connect.phpfile">How does one call the redcap_connect.php file?<a class="anchor" href="#Howdoesonecalltheredcap_connect.phpfile" title="Link to this section"></a></h4>
<p>
<b>In order for your plugin to connect to REDCap's framework, the plugin must call the redcap_connect.php file.</b> You can call redcap_connect.php by doing a "require_once" (or "include_once") of the file (see the examples at the bottom of this page). When doing that, there are 3 ways to successfully include the file:
</p>
<ol><li>Use a <strong>full path</strong> (e.g., for Linux: require_once "/home/username/www/redcap/redcap_connect.php"; for Windows: require_once "C:\\xampp\\htdocs\\redcap\\redcap_connect.php";).
</li><li>Use a <strong>relative path</strong> (e.g., if in the same directory: require_once "redcap_connect.php"; if one subdirectory down: require_once "../redcap_connect.php"; if three subdirectories down: require_once "../../../redcap_connect.php";).
</li><li>Add your main "redcap" directory to the <strong>PHP include_path</strong> in PHP.INI, thus you only ever have to specify <i>require_once "redcap_connect.php";</i> regardless of where your plugin is located on the web server.
</li></ol><p>
<strong>NOTE:</strong> The redcap_connect.php file should be located in the main REDCap directory where database.php is located.
If redcap_connect.php is not in that location, you may obtain the file from the install zip of the latest version of REDCap, 
and then place the redcap_connect.php file the main REDCap directory on your web server.
</p>
<h4 id="WhereshouldmypluginfilesbelocatedontheREDCapwebserver">Where should my plugin file(s) be located on the REDCap web server?<a class="anchor" href="#WhereshouldmypluginfilesbelocatedontheREDCapwebserver" title="Link to this section"></a></h4>
<p>
Your plugin file(s) can actually be located anywhere under your web server's web root (i.e. any directory accessible via the web).
The most common place to put a plugin file is in a directory named "plugins" directly under the main "redcap" folder on the web server.
</p>

<h4 id="Whatisthedifferencebetweenproject-levelandsystem-levelplugins">What is the difference between project-level and system-level plugins?<a class="anchor" href="#Whatisthedifferencebetweenproject-levelandsystem-levelplugins" title="Link to this section"></a></h4>
<p>
REDCap classifies its pages as falling into two basic categories: system-level pages and project-level pages. Project-level pages are any pages in REDCap that are used when accessing a specific REDCap project (e.g., Data Export Tool, user-defined data entry forms, User Rights). At the most basic level, a project-level page can be defined as any page containing "pid", which is the project_id, in the URL query string (e.g., https://YOURSERVERNAME/redcap_v4.5.0/index.php?pid=2). Conversely, system-level pages are pages that are not project-centric (i.e. anything that's not a project-level page), such as the My Projects page, the Help &amp; FAQ page, or any Control Center pages. When using a REDCap plugin, if "pid" is not found in the query string of the URL, then it treats the plugin as a system-level page, thus utilizing all system-level values, such as authentication and so forth. And if "pid" is found in the query string, then it treats the plugin as a project-level page, thus employing that specific project's authentication (which may be different from the system-level authentication method used), as well as adding the project's project-level PHP variables to PHP's global scope. For project-level plugin pages, REDCap will institute the project-level user rights also. So if the user accessing the plugin is not a user on that particular project, then they will not be able to access the plugin (unless they are a super user since super users have access to all projects).
</p>
<p>
If you wish to display REDCap's official page headers and footers that are normally seen in the application, thus giving your plugin the appearance of being a traditional page within REDCap, then the header/footer are called via PHP in different ways for the project-level pages vs. the system-level pages. Calling a header or footer only takes 1 or 2 lines of PHP code in your plugin. Example 3 below shows how to call headers/footers for system-level pages, whereas Example 4 shows how to call them for project-level pages. If someone building a plugin mistakenly tries to display a project-level header or footer on a system-level page, then the header and footer will not display correctly on the page and will provide a warning message explaining why the header/footer cannot be displayed properly. However, project-level plugins CAN display system-level page headers/footers.
</p>
<h4 id="IsitpossibletodisableREDCapsauthenticationbutstillutilizeotherREDCapresources">Is it possible to disable REDCap's authentication but still utilize other REDCap resources?<a class="anchor" href="#IsitpossibletodisableREDCapsauthenticationbutstillutilizeotherREDCapresources" title="Link to this section"></a></h4>
<p>
Answer: Yes. By default, redcap_connect.php will enforce REDCap's authentication, thus prompting a REDCap login screen and maintaining your session as REDCap normally does. But you may disable authentication if you wish. To disable REDCap's authentication (i.e. prevent the login page from displaying, thus making the web page completely public and viewable to anyone), all you need to do is add the following PHP code before you include the redcap_connect.php file: <strong>define("NOAUTH", true);</strong>. Below is an example of this. <strong>NOTE:</strong> If authentication is disabled, then REDCap's user-related PHP constants USERID and SUPER_USER will not be defined.
</p>
<div class="code"><pre><span class="cp">&lt;?php</span>
<span class="c">// Disable REDCap's authentication
</span><span class="nb">define</span><span class="p">(</span><span class="s2">"NOAUTH"</span><span class="p">,</span> <span class="k">true</span><span class="p">);</span>

<span class="c">// Call the REDCap Connect file in the main "redcap" directory
</span><span class="k">require_once</span> <span class="s2">"../redcap_connect.php"</span><span class="p">;</span>

<span class="c">## Your custom PHP code goes here.
</span></pre></div><h4 id="Howtolimitpluginaccesstospecificprojectsandorspecificusers">How to limit plugin access to specific projects and/or specific users<a class="anchor" href="#Howtolimitpluginaccesstospecificprojectsandorspecificusers" title="Link to this section"></a></h4>
<p>
By default, a plugin will be fully accessible for all REDCap users and all REDCap projects. (If authentication is manually disabled, then it will be accessible to the world - i.e. anyone who can reach that URL.) It is likely that one may wish to lock down a plugin so that only specific projects can access it or perhaps specific users (or specific users within a specific project). This can be done by utilizing two different methods built into REDCap: <strong>REDCap::allowProjects()</strong> and <strong>REDCap::allowUsers()</strong>. NOTE: REDCap::allowProjects() is only to be used for project-level plugins (it will be ignored if called in a system-level plugin). You will want to probably place these functions immediately after including the redcap_connect.php file (see examples below). You may use one, both, or neither of the functions together in a plugin. For both functions, you may pass in the parameters either as an array of values OR as separate multiple parameters to the function. For REDCap::allowProjects(), you will need to pass in the project_id's of the projects that you wish to be able to access this plugin (all other projects will not be able to access it). For REDCap::allowUsers, you will need to pass in the REDCap usernames of the users that you wish to be able to access it (all other users will not be able to access it). If a user tries to access a plugin when they are not listed in the REDCap::allowUsers() method (or if someone in a project tries to access it when the project's project_id is not listed in the REDCap::allowProjects() method), then the user will receive a red error box saying that they cannot access the page because they do not have proper access rights. This error also halts the rendering of the rest of the page.
</p>
<div class="code"><pre><span class="cp">&lt;?php</span>
<span class="c">// Call the REDCap Connect file in the main "redcap" directory
</span><span class="k">require_once</span> <span class="s2">"../redcap_connect.php"</span><span class="p">;</span>

<span class="c">// Limit this plugin only to project_id's 3, 12, and 45
</span><span class="nv">$projects</span> <span class="o">=</span> <span class="k">array</span><span class="p">(</span><span class="m">3</span><span class="p">,</span> <span class="m">12</span><span class="p">,</span> <span class="m">45</span><span class="p">);</span>
<span class="nx">REDCap::allowProjects</span><span class="p">(</span><span class="nv">$projects</span><span class="p">);</span>

<span class="c">## Your custom PHP code goes here.
</span>
</pre></div><div class="code"><pre><span class="cp">&lt;?php</span>
<span class="c">// Call the REDCap Connect file in the main "redcap" directory
</span><span class="k">require_once</span> <span class="s2">"../redcap_connect.php"</span><span class="p">;</span>

<span class="c">// Limit this plugin only to users 'taylorr4' and 'harrispa' in projects with project_id 56 and 112
</span><span class="nx">REDCap::allowProjects</span><span class="p">(</span><span class="m">56</span><span class="p">,</span> <span class="m">112</span><span class="p">);</span>
<span class="nx">REDCap::allowUsers</span><span class="p">(</span><span class="s1">'taylorr4'</span><span class="p">,</span> <span class="s1">'harrispa'</span><span class="p">);</span>

<span class="c">## Your custom PHP code goes here.
</span>
</pre></div><div class="code"><pre><span class="cp">&lt;?php</span>
<span class="c">// Call the REDCap Connect file in the main "redcap" directory
</span><span class="k">require_once</span> <span class="s2">"../redcap_connect.php"</span><span class="p">;</span>

<span class="c">// Limit this plugin only to users 'taylorr4' and 'harrispa'
</span><span class="nv">$users</span> <span class="o">=</span> <span class="k">array</span><span class="p">(</span><span class="s1">'taylorr4'</span><span class="p">,</span> <span class="s1">'harrispa'</span><span class="p">);</span>
<span class="nx">REDCap::allowUsers</span><span class="p">(</span><span class="nv">$users</span><span class="p">);</span>

<span class="c">## Your custom PHP code goes here.
</span>
</pre></div><h4 id="HowdoesonebegincreatingaREDCapplugin">How does one begin creating a REDCap plugin?<a class="anchor" href="#HowdoesonebegincreatingaREDCapplugin" title="Link to this section"></a></h4>
<p>
As of REDCap version 5.5.0, the installation package of REDCap comes pre-loaded with the redcap_connect.php file and some example plugin files that are simple examples of how one might create a plugin. Aside from doing the initial "require_once" (or "include_once") of the redcap_connect.php file, the sky is the limit for what you can do next. For your convenience, REDCap has a PHP function called <strong>redcap_info()</strong> (similar to <i>phpinfo()</i>) that can be called from anywhere within a REDCap page or plugin page. Calling the <i>redcap_info()</i> function will automatically render a web page (when viewing the script in a web browser) that will display a table listing all PHP constants and variables that have been pre-defined by REDCap and are thus available for utilization in a plugin. Thus it gives you a head start by letting you know what resources are available that you may utilize in your plugin code. You can see the output of redcap_info() by clicking its link on the left-hand menu on this page. Also seen below are other basic examples of plugins that you may peruse for getting an idea of how to begin building your own.
</p>
<h4 id="WhatisthepreferredmethodinapluginforqueryingREDCapsdatabasetables">What is the preferred method in a plugin for querying REDCap's database tables?<a class="anchor" href="#WhatisthepreferredmethodinapluginforqueryingREDCapsdatabasetables" title="Link to this section"></a></h4>
<p>
<strong>It is preferred that you utilize the MySQLi extension</strong> when making calls to the database. It is also acceptable to utilize PEAR DB or PDO (if your web server supports it). Please note that as of 01/14/2013, the redcap_connect.zip file (which can be downloaded at the top of the page if you do not have it) also automatically performs a MySQLi connection in addition to the existing MySQL connection, in which the database connection link is a global variable named <strong>$conn</strong> that one may utilize in your plugin code. You may use the MySQL extension (i.e. mysql_* functions) in plugins, but please keep in mind that the MySQL extension is deprecated in PHP 5.5. So it is recommended that you utilize MySQLi instead, although both the MySQL and MySQLi extensions can be utilized in plugins if using the latest REDCap Connect file that includes the MySQLi auto-connection (or if you make your own independent MySQLi database connection in your plugin).
</p>



<h4>Can I share my plugin with other REDCap partners?</h4>
<p>
Yes, we highly encourage plugin developers to share their plugins with other REDCap partners when applicable. There is a
<a target="_blank" style="font-weight:bold;" href="https://community.projectredcap.org/articles/190/redcap-plugin-library.html">Plugin Library</a>
page on the consortium community website
where anyone with access can upload their plugin to share. And as a plugin developer, you may also download
any of the plugins that have already been uploaded and shared on that page. If you do not have access to the
consortium community website, then someone from your institution most likely does, in which case they can request that you be given access
by filling out the <a target="_blank" style="font-weight:bold;" href="https://redcap.vanderbilt.edu/surveys/?s=ETFXFYD8WA">Request for Access to REDCap Consortium Support Tools survey</a>
for you.
</p>











<hr style="margin:100px 0 10px;">
<h3 id="PluginExamples">Plugin Examples<a class="anchor" href="#PluginExamples" title="Link to this section"></a></h3>
<p>
The "redcap_connect.php" file and plugin example files can be obtained from the install zip of the latest version of REDCap
if you do not yet have them.
<br>
</p>
<h4 id="NOTE:Alltheexamplesbelowassumethatthepluginpageexistsinadirectorybelowtheredcap_connect.phpfile.">** NOTE: All the examples below assume that the plugin page exists in a directory below the redcap_connect.php file. **<a class="anchor" href="#NOTE:Alltheexamplesbelowassumethatthepluginpageexistsinadirectorybelowtheredcap_connect.phpfile." title="Link to this section"></a></h4>
<p>
<br>
</p>
<h4 id="Example1:SimpleplugintemplatewithnoREDCapheaderfooter">Example 1: Simple plugin template (with no REDCap header/footer)<a class="anchor" href="#Example1:SimpleplugintemplatewithnoREDCapheaderfooter" title="Link to this section"></a></h4>
<p>
This will first provide a login screen to log in to REDCap. Once logged in, it will display a blank page.
</p>
<div class="code"><pre><span class="cp">&lt;?php</span>
<span class="c">// Call the REDCap Connect file in the main "redcap" directory
</span><span class="k">require_once</span> <span class="s2">"../redcap_connect.php"</span><span class="p">;</span>

<span class="c">## Your custom PHP code goes here. You may use any constants/variables listed in redcap_info().
</span>
<span class="c">## Your HTML page content goes here
</span></pre></div><h4 id="Example2:Pluginpagethatonlydisplaystheredcap_infotable">Example 2: Plugin page that only displays the redcap_info() table<a class="anchor" href="#Example2:Pluginpagethatonlydisplaystheredcap_infotable" title="Link to this section"></a></h4>
<div class="code"><pre><span class="cp">&lt;?php</span>
<span class="c">// Call the REDCap Connect file in the main "redcap" directory
</span><span class="k">require_once</span> <span class="s2">"../redcap_connect.php"</span><span class="p">;</span>

<span class="c">// Display table of REDCap variables, constants, and settings - similar to phpinfo()
</span><span class="nx">redcap_info</span><span class="p">();</span>
</pre></div><h4 id="Example3:SimpleplugintemplatedisplayingREDCapssystem-levelpageheaderfooter">Example 3: Simple plugin template displaying REDCap's system-level page header/footer<a class="anchor" href="#Example3:SimpleplugintemplatedisplayingREDCapssystem-levelpageheaderfooter" title="Link to this section"></a></h4>
<div class="code"><pre><span class="cp">&lt;?php</span>
<span class="c">// Call the REDCap Connect file in the main "redcap" directory
</span><span class="k">require_once</span> <span class="s2">"../redcap_connect.php"</span><span class="p">;</span>

<span class="c">## Your custom PHP code goes here. You may use any constants/variables listed in redcap_info().
</span>
<span class="c">// OPTIONAL: Display the header
</span><span class="nv">$HtmlPage</span> <span class="o">=</span> <span class="k">new</span> <span class="nx">HtmlPage</span><span class="p">();</span>
<span class="nv">$HtmlPage</span><span class="o">-&gt;</span><span class="na">PrintHeaderExt</span><span class="p">();</span>

<span class="c">## Your HTML page content goes here
</span>
<span class="c">// OPTIONAL: Display the footer
</span><span class="nv">$HtmlPage</span><span class="o">-&gt;</span><span class="na">PrintFooterExt</span><span class="p">();</span>
</pre></div><h4 id="Example4:SimpleplugintemplatedisplayingREDCapsproject-levelpageheaderfooter">Example 4: Simple plugin template displaying REDCap's project-level page header/footer<a class="anchor" href="#Example4:SimpleplugintemplatedisplayingREDCapsproject-levelpageheaderfooter" title="Link to this section"></a></h4>
<p>
This plugin page assumes that "pid" (i.e. the project_id) exists in the URL query string. <strong>NOTE:</strong> If someone building a plugin mistakenly tries to display a project-level header or footer on a system-level page, then the header and footer will not display correctly on the page and will provide a warning message explaining why the header/footer cannot be displayed properly. However, project-level plugins CAN display system-level page headers/footers.
</p>
<div class="code"><pre><span class="cp">&lt;?php</span>
<span class="c">// Call the REDCap Connect file in the main "redcap" directory
</span><span class="k">require_once</span> <span class="s2">"../redcap_connect.php"</span><span class="p">;</span>

<span class="c">## Your custom PHP code goes here. You may use any constants/variables listed in redcap_info().
</span>
<span class="c">// OPTIONAL: Display the project header
</span><span class="k">require_once</span> <span class="nx">APP_PATH_DOCROOT</span> <span class="o">.</span> <span class="s1">'ProjectGeneral/header.php'</span><span class="p">;</span>

<span class="c">## Your HTML page content goes here
</span>
<span class="c">// OPTIONAL: Display the project footer
</span><span class="k">require_once</span> <span class="nx">APP_PATH_DOCROOT</span> <span class="o">.</span> <span class="s1">'ProjectGeneral/footer.php'</span><span class="p">;</span>
</pre></div><h4 id="Example5:PluginthatqueriestheREDCapdatabase">Example 5: Plugin that queries the REDCap database<a class="anchor" href="#Example5:PluginthatqueriestheREDCapdatabase" title="Link to this section"></a></h4>
<div class="code"><pre><span class="cp">&lt;?php</span>
<span class="c">// Call the REDCap Connect file in the main "redcap" directory
</span><span class="k">require_once</span> <span class="s2">"../redcap_connect.php"</span><span class="p">;</span>

<span class="c">// Query the redcap_data table using the MySQLi connection $conn
</span><span class="nv">$query</span> <span class="o">=</span> <span class="s2">"select * from redcap_data where project_id = </span><span class="si">$project_id</span><span class="s2">"</span><span class="p">;</span>
<span class="nv">$result</span> <span class="o">=</span> <span class="nb">mysqli_query</span><span class="p">(</span><span class="nv">$conn</span><span class="p">,</span> <span class="nv">$query</span><span class="p">);</span>
<span class="k">while</span> <span class="p">(</span><span class="nv">$row</span> <span class="o">=</span> <span class="nb">mysqli_fetch_assoc</span><span class="p">(</span><span class="nv">$result</span><span class="p">))</span> <span class="p">{</span>
    <span class="c">// Do something with this row from redcap_data
</span><span class="p">}</span>
</pre></div><p>
<br>
<br>
<br>
<br>
</p>



      </div>