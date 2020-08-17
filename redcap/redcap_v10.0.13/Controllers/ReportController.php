<?php

class ReportController extends Controller
{
	// Output HTML for setting up Report Folders
	public function reportFoldersDialog()
	{
		print DataExport::outputReportFoldersDialog();
	}
	
	// Create new Report Folder
	public function reportFolderCreate()
	{
		print DataExport::reportFolderCreate();
	}
	
	// Edit Report Folder
	public function reportFolderEdit()
	{
		print DataExport::reportFolderEdit();
	}
	
	// Delete Report Folder
	public function reportFolderDelete()
	{
		print DataExport::reportFolderDelete();
	}
	
	public function reportFolderDisplayTable()
	{
		print DataExport::outputReportFoldersTable();
	}
	
	public function reportFolderDisplayTableAssign()
	{
		print DataExport::outputReportFoldersTableAssign($_POST['folder_id'], $_POST['hide_assigned']);
	}
	
	public function reportFolderDisplayDropdown()
	{
		print DataExport::outputReportFoldersDropdown();
	}
	
	public function reportFolderAssign()
	{
		print DataExport::reportFolderAssign();
	}
	
	public function reportFolderResort()
	{
		print DataExport::reportFolderResort($_POST['data']);
	}
	
	public function reportSearch()
	{
		print DataExport::reportSearch($_GET['term']);
	}
}