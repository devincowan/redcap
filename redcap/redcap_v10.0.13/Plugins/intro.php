<?php


// Call config file
require_once dirname(dirname(__FILE__)) . '/Config/init_global.php';
?>

<h3>Introduction to Plugins, Hooks, & External Modules</h3>

<p>This is the official documentation for developers of hooks, plugins, and external modules in REDCap.
It provides an introduction to the basics
of what hooks, plugins, and modules are and how they might be used. It includes FAQ pages for frequently asked questions regarding
hooks, plugins, and modules, and it also serves as documentation for all the official REDCap developer methods that may be used in a REDCap hook, plugin, or module.</p>

<p>
REDCap hooks, plugins, and modules serve the purpose of allowing one to extend and add to REDCap's functionality without
having to modify the REDCap base code. In the past, if some institutions wanted to add a feature or functionality
to REDCap, they would have to modify the REDCap code in order to do so. However, this becomes hard to maintain over
time since such changes will thus have to be made for each subsequent upgrade performed afterward. Some institutions
had even modified REDCap so much that at some point it became impossible for them to upgrade REDCap any further, which
meant that they ended up being stuck on that version of REDCap forever. So essentially, modifying the REDCap base code is not
viewed as a very prudent thing to do. But hooks, plugins, and modules allow one to add new features and functionality to
REDCap without having to modify the REDCap code. Additionally, another benefit of utilizing hooks, plugins, and modules is that their code
does not have to be modified as you upgrade to new versions of REDCap.
</p>


<h3>What are hooks, plugins, and modules?<br>How are they different?</h3>
<p>
REDCap hooks, plugins, and modules are very different animals, so to speak, and serve very different purposes.
They are all PHP scripts utilized by REDCap, in which a programmer will write the hooks, plugins, and modules,
and then those scripts will be placed on the REDCap web server so that they can be accessed by REDCap.
Hooks, plugins, and modules are meant to be
written by a programmer who is part of (or associated with) the REDCap team at the local institution.
Hooks, plugins, and modules have the ability to access both your web server and database server directly, so
they will have access to your web server's file system and all REDCap data stored in MySQL.
Thus only trusted, high-level individuals should be given the ability to author hooks, plugins, and modules.
While the REDCap API is a user-level feature, hooks, plugins, and modules are not. Although they may indeed get used
by end-users, hooks, plugins, and modules will not be created by end-users. (If hooks, plugins, or modules are ever created by
technically knowledgeable end-users, which is not advised in most cases,
it is highly recommended that a programmer associated with the REDCap team at the local institution review the code thoroughly
for security and quality purposes before the hook or plugin is placed on the REDCap web server.)
</p>


<p>
A <b>REDCap plugin</b> is essentially a custom PHP script or a collection of PHP scripts
(and may also include HTML, CSS, or Javascript files) that exist independently of - but work in conjunction with -
the official REDCap base code. Plugins allow developers to build scripts that connect to the REDCap framework from
outside of REDCap in order to utilize REDCap's many resources. This includes REDCap's authentication, the database connection to
REDCap's MySQL back end, and all available PHP variables, constants, and functions that are defined by and utilized by REDCap.
Plugins are autonomous scripts that live outside of REDCap. Thus plugins can never alter the look or behavior of existing
REDCap pages. Plugins allow you to take advantage of REDCap's resources in a webpage that sits outside of
REDCap proper. Plugins can also render REDCap's web page headers and footers on your custom plugin web page so that it
appears as if you are actually in REDCap proper by having your page framed with the official REDCap header/footer,
which is completely optional. So plugins can look and act as if they are a REDCap page, and you can even link to plugins
using Project Bookmarks (which is a very popular option), but plugins will only ever be an addition to REDCap.

It is also important to note that there is a
<a target="_blank" style="text-decoration:underline;" href="https://community.projectredcap.org/articles/190/redcap-plugin-library.html">Plugin Library</a>
page on the consortium community website
where anyone with access can upload their plugin to share. And as a plugin developer, you may also download
any of the plugins that have already been uploaded and shared on that page. If you do not have access to the
consortium community website, then someone from your institution most likely does, in which case they can request that you be given access
by filling out the <a target="_blank" style="text-decoration:underline;" href="https://redcap.vanderbilt.edu/surveys/?s=ETFXFYD8WA">Request for Access to REDCap Consortium Support Tools survey</a>
for you.
</p>

<p>
Unlike a plugin, a <b>REDCap hook</b> is not a whole PHP script but instead is a PHP function with a
designated name, in which the hook function gets executed in a predetermined location inside REDCap. And differing from plugins
in another respect, hooks *can* modify the look and behavior (to a certain extent) of specific REDCap pages.
In this way, hooks are much more powerful than plugins.
Similar to plugins, hooks may utilize other PHP, HTML, CSS, and/or Javascript files in its implementation, depending on
how it is being used. Additionally, because hook functions are executed by REDCap itself, hooks can utilize REDCap's many resources,
such as the database connection to REDCap's MySQL back end, and all available PHP variables, constants, and functions
that are defined by and utilized by REDCap. In order to begin utilizing hooks, the Hook Functions file must first
be created as a PHP file on the web server (its name and location on the server do not matter), after which you will
need to register the full path of the Hook Functions file on the General Configuration page in the Control Center.
Once all that is done, then whenever REDCap is executing a page that calls a hook, it will execute that
particular hook function, which will be defined in your Hook Function file. If your Hook Function file does not contain
that specific hook function, then REDCap will just ignore it and move on. All the hook functions that are available for use are listed
near the bottom of the left-hand menu on this page.
Click any hook function listed on the menu to navigate to the page documenting how to utilize that hook, which includes
how that hook may be used, including examples, as well as listing what values are passed as parameters to the hook function.
</p>

<p>
A <b>REDCap external module</b> is a set of PHP files and other files that can utilize both plugins and hooks to extend or customize REDCap
in a variety of ways. Modules work on top of REDCap's External Module functionality, which is a class-based framework for plugins and hooks, 
in which modules are easily reusable and shareable among REDCap institutional partners. Thus modules have all the capabilities of both plugins
and hooks together, while also being easy to disseminate and share with others.
</p>


<h3>What are REDCap developer methods?</h3>

<p>The static PHP methods listed on the left-hand menu of this page are the official REDCap developer methods
that can be utilized by hooks, plugins, and modules to allow you to perform common
tasks or retrieve information in REDCap with very little effort and without having to know REDCap's back-end
database structure or its internal methods/classes. The developer methods are static PHP methods that belong to a PHP class named
"REDCap" and are documented here as developer tools to
help make creating REDCap plugins and hooks easier and faster. The documentation for the developer methods will always reflect
all the methods that are available in the REDCap version listed at the top of this page.</p>

<p>Click any developer method listed on the left to navigate to the page documenting how to utilize that method.
A description is provided to note what each method does. It lists all the parameters used by the method
(including their data types and a description of what each parameter represents), it describes the return
values of the method (including its data type), it notes any restrictions for the method, and also provides
one or more real-world code examples of how to use the method in a PHP script.
As time goes on, more and more methods will be added to this library of official REDCap developer methods.</p>

<p>
NOTE: External modules have their own set of methods that can be utilized within them (in addition to the REDCap class methods).
For more information, see the External Modules Documentation page.
</p>



<h3>DISCLAIMERS</h3>

<p>
If you are building a REDCap hook or plugin, please note that <strong>you are <i>completely</i> responsible for maintaining
general security practices within your hook/plugin</strong> (with the exception of authentication inside a plugin if you are utilizing
REDCap's authentication) just as you would while building any web application.
The REDCap base code employs certain measures to protect itself from various types of malicious attacks
(e.g., Cross-Site Scripting, SQL Injection, Cross-Site Request Forgery). However, this has to be manually
implemented on a page-by-page basis, so REDCap is <i>NOT</i> able to implement such security measures automatically in your own
hooks/plugins.
</p>
