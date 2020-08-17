	</div>
</div>

<?php
// REDCap Hook injection point
Hooks::call('redcap_control_center', array());

// Footer
$objHtmlPage->PrintFooter();