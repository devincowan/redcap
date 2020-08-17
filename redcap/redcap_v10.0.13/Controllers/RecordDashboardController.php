<?php

class RecordDashboardController extends Controller
{
	// Save the custom dashboard
	public function save()
	{
		RecordDashboard::saveDashboard();
	}
	
	// Delete the custom dashboard
	public function delete()
	{
		RecordDashboard::deleteDashboard();
	}
}