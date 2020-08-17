<?php

class UserRightsController extends Controller
{
	// Render the user rights/roles table
	public function displayRightsRolesTable()
	{
		print 	RCView::div(array('id'=>'user_rights_roles_table_parent', 'style'=>'margin:0 20px 20px 0;'),
					UserRights::renderUserRightsRolesTable()
				);
	}

	// Impersonate a user (admins only)
	public function impersonateUser()
	{
		UserRights::impersonateUser();
	}
}